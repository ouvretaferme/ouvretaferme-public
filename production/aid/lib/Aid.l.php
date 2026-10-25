<?php
namespace aid;

class AidLib extends AidCrud {

	/**
	 * Le semis reprend la vérification et sa date avec la fiche (NULL). L'équipe ne les saisit pas
	 * (`'admin'`) : la confiance vient du semis ou de confirm(), et seul « Marquer relue » date la
	 * relecture, avec son auteur (check()). L'équipe seule dit que l'organisme ne publie pas de taux,
	 * qu'aucune fiche ne porte ; la case se construit après `rate`, que son étape lit
	 * (Aid::properties()). Le site de dépôt et la règle d'achat sont aussi de l'équipe seule : aucune
	 * fiche ne les porte, et une reprise les remettrait à NULL. La case « liste non publiée » n'est
	 * construite que par son action (`doUpdatePieces`), sous Aid::acceptPieces(). La liste commune suit
	 * l'ordre du formulaire de l'équipe (AdminUi::getPanel()), que la revue d'un écart reprend
	 * (fillSeed()). Ce que seule l'équipe saisit s'y ajoute à la fin, quel que soit son fieldset.
	 */
	public static function getPropertiesCreate(?string $origin = NULL): array {

		$properties = [
			'name', 'summary', 'organization', 'parent', 'level', 'type', 'calendar',
			'rate', 'ratePercentMax', 'amountMax', 'amountUnit', 'floor', 'ceiling', 'boundBase', 'boundPeriod',
			'beneficiaries', 'direct', 'productions', 'labels', 'labelsBonus', 'conditions', 'keywords', 'notes'
		];

		return match($origin) {
			NULL => array_merge($properties, ['verification', 'checkedAt']),
			'admin' => array_merge($properties, ['rateUnpublished', 'depositUrl', 'commitmentRule']),
		};

	}

	/**
	 * L'aide neuve du panneau de création : le panneau la dessine avant tout POST, et chaque case à
	 * cocher y lit la valeur de sa propriété.
	 */
	public static function getCreateElement(): Aid {

		return new Aid([
			'productions' => Aid::model()->getDefaultValue('productions'),
			'labels' => NULL,
			'labelsBonus' => NULL,
			'direct' => Aid::model()->getDefaultValue('direct'),
			'amountUnit' => Aid::EURO,
		]);

	}

	/**
	 * Ce qu'une fiche et l'équipe écrivent toutes deux : la reprise d'une aide relue mesure son écart
	 * sur ces seules propriétés (ImportLib::seedAid()), et « Reprendre la fiche » n'écrit qu'elles
	 * (updateFromSeed()). Aucune fiche ne porte le résumé, le montant plafond et son unité, l'assiette et la
	 * période des bornes, le dispositif, les mots-clés, le site de dépôt ni la règle d'achat : le semis
	 * les pose à NULL, et les mesurer rapporterait comme un écart tout ce que l'équipe y écrit, que
	 * « Reprendre la fiche » effacerait.
	 */
	public static function getPropertiesSeed(): array {

		return array_values(array_diff(self::getPropertiesCreate('admin'), [
			'summary', 'amountMax', 'amountUnit', 'boundBase', 'boundPeriod', 'parent', 'keywords', 'rateUnpublished', 'depositUrl', 'commitmentRule'
		]));

	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return self::getPropertiesCreate($origin);
	}

	/**
	 * La condition d'affichage, posée sur une requête : la même règle qu'Aid::acceptDisplay() sur une
	 * aide chargée. La liste de l'outil, les compléments d'une fiche et le décompte de la reprise la
	 * lisent ici, pour qu'une aide close ou dont toutes les sources officielles sont mortes ne sorte par
	 * aucun des trois.
	 */
	public static function applyDisplay(AidModel $mAid): AidModel {

		return $mAid
			->whereStatus(Aid::PUBLISHED)
			->where(self::getCalendarShownSql())
			->where('NOT '.self::getSourceDeadSql());

	}

	// Toutes les sources officielles de l'aide m1 sont mortes : la même règle qu'Aid::hasSourcePrimaryAllDead().
	private static function getSourceDeadSql(): \Sql {

		$hasSourceDead = new SourceModel()
			->whereAid(new \Sql('m1.id'))
			->whereKind(Source::PRIMARY)
			->whereStatus(Source::DEAD)
			->existsSql();

		$hasSourceAlive = new SourceModel()
			->whereAid(new \Sql('m1.id'))
			->whereKind(Source::PRIMARY)
			->whereStatus('!=', Source::DEAD)
			->existsSql();

		return new \Sql('('.$hasSourceDead.' AND NOT '.$hasSourceAlive.')');

	}

	// Ce que le calendrier laisse afficher : permanente sans édition qui la borne, annuelle, sans
	// démarche, ou une édition ouverte ou à venir, la structure préparant son dossier avant l'ouverture.
	// Une aide publiée qui n'y répond pas est close (getReviewClosedSql()).
	private static function getCalendarShownSql(): \Sql {

		$hasEditionOpenOrUpcoming = self::getEditionOpenOrUpcomingSql();
		$hasEdition = self::getEditionSql();

		return new \Sql('((m1.calendar = '.Aid::model()->format(Aid::PERMANENT).' AND NOT '.$hasEdition.') OR m1.calendar IN ('.Aid::model()->format(Aid::CAMPAIGN).', '.Aid::model()->format(Aid::AUTOMATIC).') OR '.$hasEditionOpenOrUpcoming.')');

	}

	// Une édition ouverte ou à venir, pas encore close par sa date, corrélée à l'aide m1.
	private static function getEditionOpenOrUpcomingSql(): \Sql {

		return self::applyEditionOpenOrUpcoming(new EditionModel())
			->whereAid(new \Sql('m1.id'))
			->existsSql();

	}

	private static function getEditionSql(): \Sql {

		return new EditionModel()
			->whereAid(new \Sql('m1.id'))
			->existsSql();

	}

	/**
	 * La liste de l'administration, filtrée par la recherche : toutes les aides, quel que soit leur
	 * état. Le texte se cherche dans le nom, le résumé et les mots-clés, l'organisme par son nom.
	 */
	public static function applySearch(AidModel $mAid, \Search $search): AidModel {

		$search->filter('region', fn(string $region) => $mAid->whereId('IN', new AreaModel()
			->select('aid')
			->whereDepartment('IN', AidSetting::getDepartmentsByRegion($region))
			->getSql()));

		$search->filter('department', fn(string $department) => $mAid->whereId('IN', new AreaModel()
			->select('aid')
			->whereDepartment($department)
			->getSql()));

		$search->filter('project', fn(int $project) => $mAid->whereId('IN', new AidTagModel()
			->select('aid')
			->whereTag('IN', new TagModel()
				->select('id')
				->where(new \Sql('t1.projects & '.$project.' > 0'))
				->getSql('t'))
			->getSql()));

		$search->filter('organization', fn(string $organization) => $mAid->whereOrganization('IN', new OrganizationModel()
			->select('id')
			->whereName('LIKE', '%'.$organization.'%')
			->getSql()));

		$search->filter('text', function(string $text) use($mAid) {

			$like = $mAid->format('%'.$text.'%');

			$mAid->where('(m1.name LIKE '.$like.' OR m1.summary LIKE '.$like.' OR m1.keywords LIKE '.$like.')');

		});

		$search->filter('closed', fn() => $mAid
			->where(self::getEditionSql())
			->where('NOT '.self::getEditionOpenOrUpcomingSql()));

		$search->filter('status', fn(string $status) => $mAid->whereStatus($status));
		$search->filter('level', fn(string $level) => $mAid->whereLevel($level));
		$search->filter('verification', fn(string $verification) => $mAid->whereVerification($verification));

		$search->filter('review', fn(string $review) => match($review) {
			Aid::REVIEW_DUE => $mAid->where(self::getReviewDueSql()),
			Aid::REVIEW_NEVER => $mAid->where(self::getReviewNeverSql()),
		});

		return $mAid;

	}

	/**
	 * Une page de la liste de l'administration, et le nombre d'aides que la recherche trouve.
	 */
	public static function getForAdmin(\Search $search, int $page): array {

		$mAid = self::applySearch(new AidModel(), $search);

		$cAid = $mAid
			->select(array_merge([
				'id', 'name', 'level', 'type', 'calendar', 'rate', 'rateUnpublished', 'status', 'verification', 'checkedAt',
				'organization' => ['name'],
				'parent' => ['name'],
				'cEdition' => new EditionModel()->select(['id', 'aid', 'state', 'startDate', 'endDate'])->delegateCollection('aid'),
				'cSource' => new SourceModel()->select(['id', 'aid', 'kind', 'status'])->delegateCollection('aid'),
			], self::getSelectionReview()))
			->sort(new \Sql(self::getNameSortSql()))
			->option('count')
			->getCollection($page * AidSetting::PER_ADMIN, AidSetting::PER_ADMIN);

		self::fillAdmin($cAid);

		return [$cAid, $mAid->found()];

	}

	/**
	 * Le nom dans l'ordre naturel, « A2 » avant « A10 » : le texte qui précède le premier nombre, puis
	 * ce nombre, puis le nom entier. Les dispositifs numérotés d'un même organisme se suivent ainsi
	 * dans leur ordre ; un second nombre dans le nom reste rangé en texte.
	 */
	private static function getNameSortSql(): string {
		return 'REGEXP_SUBSTR(m1.name, "^[^0-9]*") ASC, CAST(REGEXP_SUBSTR(m1.name, "[0-9]+") AS UNSIGNED) ASC, m1.name ASC';
	}

	/**
	 * Pose sur chaque aide ce qui la cache aux structures (`hidden`, Aid::getHiddenReason()) et la date
	 * que l'administration lit à côté de son calendrier (`endDate`) : la clôture de l'édition close la
	 * plus récente quand elle est cachée pour cela, sinon la prochaine clôture d'une édition ouverte.
	 * Sans édition ouverte, l'édition à venir qui ouvre la première (`eEditionUpcoming`), que la liste date
	 * par son ouverture comme la carte des structures. Y pose aussi ce qui la rend à revoir (`review`,
	 * Aid::getReviewReason()), que la fiche dit dans sa case Relecture.
	 */
	private static function fillAdmin(\Collection $cAid): void {

		foreach($cAid as $eAid) {

			$eAid['hidden'] = $eAid->getHiddenReason();
			$eAid['review'] = $eAid->getReviewReason();
			$eAid['eEditionUpcoming'] = new Edition();

			if($eAid['hidden'] === Aid::HIDDEN_CLOSED) {
				$endDates = $eAid['cEdition']->getColumn('endDate');
			} else {

				// La clôture la plus proche parmi les éditions ouvertes ou à venir, comme la file la lit
				// (getReviewEndDateSql()) : la raison « une édition ferme » les compte toutes deux.
				$endDates = $eAid['cEdition']
					->find(fn(Edition $eEdition) => $eEdition->isOpenOrUpcoming())
					->getColumn('endDate');

				if($eAid['cEdition']->find(fn(Edition $eEdition) => $eEdition->isOpen())->empty()) {
					$eAid['eEditionUpcoming'] = $eAid['cEdition']
						->find(fn(Edition $eEdition) => $eEdition->isUpcoming())
						->sort(['startDate' => SORT_ASC, 'id' => SORT_ASC])
						->first() ?? new Edition();
				}

			}

			$endDates = array_filter($endDates, fn(?string $endDate) => $endDate !== NULL);

			if($endDates === []) {
				$eAid['endDate'] = NULL;
			} else {
				$eAid['endDate'] = match($eAid['hidden']) {
					Aid::HIDDEN_CLOSED => max($endDates),
					NULL, Aid::HIDDEN_SOURCE_DEAD => min($endDates),
				};
			}

		}

	}

	/**
	 * Ce que les trois compteurs de la liste annoncent, sous la recherche posée moins le critère que
	 * chacun pose lui-même : un compteur dit ce qu'on trouverait en le choisissant.
	 */
	public static function countForAdmin(\Search $search): array {

		$searchReview = new \Search($search->toArray(['verification', 'closed']));

		return [
			'conflict' => self::applySearch(new AidModel(), $searchReview)->whereVerification(Aid::CONFLICT)->count(),
			'relay' => self::applySearch(new AidModel(), $searchReview)->whereVerification(Aid::RELAY)->count(),
			'closed' => self::applySearch(new AidModel(), new \Search(['closed' => TRUE] + $searchReview->toArray()))->count(),
		];

	}

	/**
	 * Les aides publiées de chaque région, une fois chacune quel que soit le nombre de ses départements :
	 * le nombre que rend la liste sous `region` et `status=published`, closes comprises. La région se
	 * lit sur le département, comme le filtre `region` de applySearch() la lit.
	 */
	public static function countPublishedByRegion(): array {

		$cArea = Area::model()
			->select(['aid', 'department'])
			->whereAid('IN', new AidModel()
				->select('id')
				->whereStatus(Aid::PUBLISHED)
				->getSql())
			->getCollection();

		$aids = array_fill_keys(array_keys(AidSetting::REGIONS), []);

		foreach($cArea as $eArea) {
			$aids[AidSetting::DEPARTMENTS[$eArea['department']][1]][$eArea['aid']['id']] = TRUE;
		}

		return array_map(count(...), $aids);

	}

	/**
	 * Les aides d'une région qui portent exactement ce nom, archivées exclues. Le nom ne suffit pas : deux
	 * régions publient la même mesure sous le même intitulé, et c'est leur périmètre qui les sépare.
	 */
	public static function getByNameInRegion(string $name, string $region): \Collection {

		return Aid::model()
			->select(['id', 'name'])
			->whereName($name)
			->whereStatus('!=', Aid::ARCHIVED)
			->whereId('IN', new AreaModel()
				->select('aid')
				->whereDepartment('IN', AidSetting::getDepartmentsByRegion($region))
				->getSql())
			->getCollection();

	}

	/**
	 * Ce qui rend une aide à revoir, et ce qui la dit jamais relue, en colonnes calculées sur `m1` : la
	 * seule écriture de ces règles, que la fiche lit (Aid::getReviewReason(), case Relecture), que la
	 * liste pose en filtre (applySearch(), critère `review`) et que la file de revue range (getReview()).
	 */
	public static function getSelectionReview(): array {

		return [
			'reviewSource' => new \Sql(self::getReviewSourceSql(), 'bool'),
			'reviewSourcePrimary' => new \Sql(self::getReviewSourcePrimarySql(), 'bool'),
			'reviewSourcePieces' => new \Sql(self::getReviewSourcePiecesSql(), 'bool'),
			'reviewClosing' => new \Sql(self::getReviewClosingSql(), 'bool'),
			'reviewSeed' => new \Sql(self::getReviewSeedSql(), 'bool'),
			'reviewConflict' => new \Sql(self::getReviewConflictSql(), 'bool'),
			'reviewUndated' => new \Sql(self::getReviewUndatedSql(), 'bool'),
			'reviewUnreadable' => new \Sql(self::getReviewUnreadableSql(), 'bool'),
			'reviewClosed' => new \Sql(self::getReviewClosedSql(), 'bool'),
			'reviewStale' => new \Sql(self::getReviewStaleSql(), 'bool'),
			'reviewNever' => new \Sql(self::getReviewNeverSql(), 'bool'),
		];

	}

	// Une aide archivée ne se revoit plus : elle ne sort plus nulle part. Les raisons se calculent sur
	// chaque aide qui ne l'est pas : la file lit donc la table entière, ce qu'aucun index ne borne.
	private static function getReviewDueSql(): \Sql {
		$reasons = [
			self::getReviewSourceSql(),
			self::getReviewClosingSql(),
			self::getReviewSeedSql(),
			self::getReviewConflictSql(),
			self::getReviewUndatedSql(),
			self::getReviewUnreadableSql(),
			self::getReviewClosedSql(),
			self::getReviewStaleSql(),
		];

		return new \Sql('(m1.status != '.Aid::model()->format(Aid::ARCHIVED).' AND ('.implode(' OR ', $reasons).'))');
	}

	/**
	 * La file de revue : les aides à revoir, rangées par raison dans l'ordre d'Aid::getReviewReason(),
	 * puis par clôture la plus proche et par nom. Rend une page et le nombre d'aides à revoir. Les
	 * chiffres de la veille en tête de la file la restreignent à une de leurs raisons (`$reason`,
	 * getReviewFigures()). Les sources officielles et la liste des pièces viennent avec chaque aide, pour
	 * que sa ligne dise laquelle est en cause.
	 */
	public static function getReview(int $page, ?string $reason = NULL): array {

		$mAid = self::applyReviewReason(new AidModel()->where(self::getReviewDueSql()), $reason);

		$cAid = $mAid
			->select(array_merge([
				'id', 'name', 'status', 'calendar', 'verification', 'checkedAt',
				'organization' => ['name'],
				'endDate' => new \Sql(self::getReviewEndDateSql(), 'date'),
				'endDateLast' => new \Sql(self::getEndDateLastSql(), 'date'),
				'cEdition' => new EditionModel()
					->select(['id', 'aid', 'state', 'endDate'])
					->delegateCollection('aid'),
				'cSource' => new SourceModel()
					->select(['id', 'aid', 'kind', 'status', 'checkedAt', 'unreadSince'])
					->whereKind('IN', [Source::PRIMARY, Source::PIECES])
					->delegateCollection('aid'),
			], self::getSelectionReview()))
			->sort(new \Sql('reviewSource DESC, reviewClosing DESC, reviewSeed DESC, reviewConflict DESC, reviewUndated DESC, reviewUnreadable DESC, reviewClosed DESC, endDate IS NULL, endDate ASC, '.self::getNameSortSql()))
			->option('count')
			->getCollection($page * AidSetting::PER_ADMIN, AidSetting::PER_ADMIN);

		// Une ligne ne dit masquée que l'aide que le chiffre compte ainsi (applyReviewReason()).
		foreach($cAid as $eAid) {
			$eAid['hidden'] = $eAid->getHiddenReason();
			$eAid['review'] = $eAid->getReviewReason();
		}

		return [$cAid, $mAid->found()];

	}

	// Les raisons auxquelles les chiffres de la veille restreignent la file.
	public static function getReviewReasonsWatch(): array {
		return [Aid::HIDDEN_SOURCE_DEAD, Aid::REVIEW_SOURCE, Aid::REVIEW_UNREADABLE];
	}

	/**
	 * La file restreinte à une raison de la veille : une source à revoir qui masque l'aide, toutes ses
	 * sources officielles mortes, une autre source à revoir, ou une source illisible. Une aide masquée ne
	 * se compte pas parmi les sources à revoir : les deux premiers chiffres sont disjoints (arbitrage de
	 * V-Sub sur le glaçon 1 de L5). Une aide close est close avant d'être masquée (Aid::getHiddenReason()),
	 * ses sources mortes restent donc à revoir. Une source illisible peut s'ajouter à l'une ou l'autre.
	 */
	private static function applyReviewReason(AidModel $mAid, ?string $reason): AidModel {

		$hiddenSql = '(m1.status = '.Aid::model()->format(Aid::PUBLISHED).' AND '.self::getCalendarShownSql().' AND '.self::getSourceDeadSql().')';

		return match($reason) {
			NULL => $mAid,
			Aid::HIDDEN_SOURCE_DEAD => $mAid
				->where(self::getReviewSourceSql())
				->where($hiddenSql),
			Aid::REVIEW_SOURCE => $mAid
				->where(self::getReviewSourceSql())
				->where('NOT '.$hiddenSql),
			Aid::REVIEW_UNREADABLE => $mAid->where(self::getReviewUnreadableSql()),
		};

	}

	/**
	 * Ce que la veille a vu, en tête de la file : le nombre d'aides de chacune de ses raisons, et les
	 * pages changées depuis leur première lecture, comptées sans entrer dans la file pendant le mois de
	 * mesure (arbitrage Q3 de L5). `watched` dit si la veille a déjà lu, ou tenté de lire, une source.
	 */
	public static function getReviewFigures(): array {

		$figures = [];

		foreach(self::getReviewReasonsWatch() as $reason) {
			$figures[$reason] = self::applyReviewReason(new AidModel()->where(self::getReviewDueSql()), $reason)->count();
		}

		$mSourceChanged = new SourceModel()
			->whereKind('IN', [Source::PRIMARY, Source::RELAY])
			->whereChangedAt('!=', NULL)
			->whereAid('IN', new AidModel()->select(['id'])->whereStatus('!=', Aid::ARCHIVED)->getSql());

		$figures['changed'] = (clone $mSourceChanged)->select(['count' => new \Sql('COUNT(DISTINCT url)', 'int')])->get()['count'];
		$figures['changedAids'] = (clone $mSourceChanged)->select(['count' => new \Sql('COUNT(DISTINCT aid)', 'int')])->get()['count'];

		$figures['watched'] = new SourceModel()
			->or(
				fn() => $this->whereCheckedAt('!=', NULL),
				fn() => $this->whereUnreadSince('!=', NULL),
				fn() => $this->whereFailedAt('!=', NULL),
			)
			->exists();

		return $figures;

	}

	/**
	 * Ce que les gestes de relecture d'une aide ouverte depuis la file postent pour y revenir (page
	 * admin/aid.p.php) : où elle est ouverte (`view`, la colonne de côté de la file ou sa propre page),
	 * la page de la file, et l'aide qui suit l'aide ouverte dans l'ordre de l'écran, celle qui la
	 * précède quand elle est la dernière, aucune quand elle est seule. Une aide ouverte hors de la page
	 * passe la main à la première de la page.
	 */
	public static function getReviewQueue(\Collection $cAid, Aid $eAid, int $page, string $view, ?string $reason = NULL): array {

		$ids = array_values(array_diff($cAid->getIds(), [$eAid['id']]));
		$position = array_search($eAid['id'], $cAid->getIds(), TRUE);

		if($ids === []) {
			$next = NULL;
		} else if($position === FALSE) {
			$next = $ids[0];
		} else {
			$next = $ids[min($position, count($ids) - 1)];
		}

		return [
			'view' => $view,
			'page' => $page,
			'reason' => $reason,
			'next' => $next,
		];

	}

	// La prochaine clôture d'une édition ouverte ou à venir, que la file lit à côté de la raison.
	private static function getReviewEndDateSql(): \Sql {

		return self::applyEditionOpenOrUpcoming(new EditionModel())
			->select(['endDate' => new \Sql('MIN(s1.endDate)', 'date')])
			->whereAid(new \Sql('m1.id'))
			->whereEndDate('>=', \Clock::date())
			->getSql();

	}

	// Une reprise a rapporté sur l'aide relue un écart que l'équipe n'a pas encore tranché.
	private static function getReviewSeedSql(): \Sql {
		return new \Sql('(m1.seedDifferences IS NOT NULL)');
	}

	// Des sources qui divergent, qu'aucune personne de l'équipe n'a encore relues : une fois relue,
	// l'aide garde sa confiance CONFLICT, que la fiche dit, sans rester dans la file.
	private static function getReviewConflictSql(): \Sql {
		return new \Sql('(m1.verification = '.Aid::model()->format(Aid::CONFLICT).' AND '.self::getReviewNeverSql().')');
	}

	// Aucune personne de l'équipe ne l'a relue : la reprise date chaque aide sans nommer personne.
	private static function getReviewNeverSql(): \Sql {
		return new \Sql('(m1.checkedBy IS NULL)');
	}

	/**
	 * Une aide publiée dont une édition ouverte ou à venir ferme dans les quinze jours, et qu'aucune
	 * personne de l'équipe n'a relue depuis l'ouverture de cette fenêtre : relue dedans, l'aide a été lue avec sa
	 * clôture en vue. La date que pose la reprise ne vaut pas relecture (getReviewNeverSql()). La raison
	 * sert ce que les structures lisent : un brouillon, qu'elles ne voient pas, n'a pas de clôture qui presse.
	 */
	private static function getReviewClosingSql(): \Sql {

		$hasEditionClosing = self::applyEditionOpenOrUpcoming(new EditionModel())
			->whereAid(new \Sql('m1.id'))
			->whereEndDate('<=', date('Y-m-d', \Clock::shift('+'.AidSetting::REVIEW_CLOSING_DAYS.' days')))
			->where('('.self::getReviewNeverSql().' OR m1.checkedAt < s1.endDate - INTERVAL '.AidSetting::REVIEW_CLOSING_DAYS.' DAY)')
			->existsSql();

		return new \Sql('(m1.status = '.Aid::model()->format(Aid::PUBLISHED).' AND '.$hasEditionClosing.')');

	}

	// Une source que la veille a vue déplacée ou morte depuis sa dernière relecture : officielle, ou page
	// de la liste des pièces.
	private static function getReviewSourceSql(): \Sql {
		return new \Sql('('.self::getReviewSourcePrimarySql().' OR '.self::getReviewSourcePiecesSql().')');
	}

	/**
	 * Une source officielle que la veille a vue déplacée ou morte, et que personne de l'équipe n'a relue
	 * depuis. Un relais n'y entre pas : l'aide ne tient pas par lui (plan de L5, point 9). La relecture se
	 * compare au jour, `checkedAt` étant une date : une relecture le matin qui suit la nuit l'éteint. La
	 * raison vaut pour un brouillon aussi, qu'une source morte empêche de publier.
	 */
	private static function getReviewSourcePrimarySql(): \Sql {

		return new SourceModel()
			->whereAid(new \Sql('m1.id'))
			->whereKind(Source::PRIMARY)
			->whereStatus('IN', [Source::MOVED, Source::DEAD])
			->where('('.self::getReviewNeverSql().' OR m1.checkedAt < DATE(s1.statusAt))')
			->existsSql();

	}

	/**
	 * La liste des pièces que la veille a vue déplacée ou morte se compare à la relecture de la liste,
	 * que seul « Marquer la liste relue » date (checkPieces()), et non à celle de l'aide : relire la
	 * fiche ne dit rien des pièces (CL-15 de L6). Elle n'éteint pas l'aide (applyDisplay()). Les deux
	 * moitiés de la raison se lisent aussi à part dans la sélection : chacune s'éteint par son geste, que
	 * la fiche doit proposer (AdminUi::getCheck()). Ce geste ne vaut que pour une aide qui accepte des
	 * pièces (Aid::acceptPieces(), redite ici en SQL) : sans ce filtre, une aide qui cesse d'en accepter
	 * resterait à revoir sans aucun geste pour l'en sortir.
	 */
	private static function getReviewSourcePiecesSql(): \Sql {

		$acceptPieces = 'm1.direct = 1 AND m1.calendar != '.Aid::model()->format(Aid::AUTOMATIC).' AND m1.type NOT IN ('.Aid::model()->format(Aid::TAX).', '.Aid::model()->format(Aid::SOCIAL).')';

		$hasSource = new SourceModel()
			->whereAid(new \Sql('m1.id'))
			->whereKind(Source::PIECES)
			->whereStatus('IN', [Source::MOVED, Source::DEAD])
			->where('(m1.piecesCheckedAt IS NULL OR m1.piecesCheckedAt < DATE(s1.statusAt))')
			->existsSql();

		return new \Sql('('.$acceptPieces.' AND '.$hasSource.')');

	}

	/**
	 * Une source officielle que la veille n'a pas pu lire depuis AidSetting::WATCH_UNREAD_DAYS jours (robot
	 * refusé, défi, délai) : la veille ne dit plus rien de cette page, seule la relecture humaine la suit
	 * (arbitrage Q2 a de L5). Une relecture l'éteint tant que le site refuse sans interruption ; une
	 * source lue entre-temps puis de nouveau refusée repart d'un `unreadSince` postérieur, qui la rallume
	 * (arbitrage de la planche de L5).
	 */
	private static function getReviewUnreadableSql(): \Sql {

		$days = AidSetting::WATCH_UNREAD_DAYS;

		$hasSource = new SourceModel()
			->whereAid(new \Sql('m1.id'))
			->whereKind(Source::PRIMARY)
			->whereUnreadSince('<=', date('Y-m-d', \Clock::shift('-'.$days.' days')))
			->where('('.self::getReviewNeverSql().' OR m1.checkedAt < s1.unreadSince)')
			->existsSql();

		return new \Sql('('.$hasSource.')');

	}

	/**
	 * Une aide publiée que son calendrier cache depuis au moins un an, la dernière date de fin de ses
	 * éditions passée : la relecture la rouvre ou l'archive. Elle revient à chaque anniversaire de cette
	 * date tant que personne ne l'a relue depuis le dernier, puisqu'un appel clos peut rouvrir l'année
	 * suivante (arbitrage Q5 b de L5). Une aide dont aucune édition ne porte de date de fin n'a pas
	 * d'anniversaire à mesurer et reste sous l'ancienneté (getReviewStaleSql()).
	 */
	private static function getReviewClosedSql(): \Sql {

		$today = Aid::model()->format(\Clock::date());

		$endDate = self::getEndDateLastSql();

		$anniversary = 'DATE_ADD('.$endDate.', INTERVAL TIMESTAMPDIFF(YEAR, '.$endDate.', '.$today.') YEAR)';

		return new \Sql('(m1.status = '.Aid::model()->format(Aid::PUBLISHED).' AND NOT '.self::getCalendarShownSql().' AND '.$endDate.' <= '.$today.' - INTERVAL 1 YEAR AND ('.self::getReviewNeverSql().' OR m1.checkedAt < '.$anniversary.'))');

	}

	// La dernière date de fin des éditions de l'aide m1, que la file lit pour une aide close.
	private static function getEndDateLastSql(): \Sql {

		return new EditionModel()
			->select(['endDate' => new \Sql('MAX(s1.endDate)', 'date')])
			->whereAid(new \Sql('m1.id'))
			->getSql();

	}

	/**
	 * Un appel publié dont une édition ouverte ou à venir n'a pas de date de fin : affiché sans limite, il laisse la
	 * structure sans échéance (arbitrage Q5 a de L5). Une relecture du jour où l'édition est créée, ou
	 * d'après, l'éteint pour cette édition ; une nouvelle édition sans date de fin la rallume. Une date de
	 * fin retirée d'une édition déjà relue ne la rallume pas, `createdAt` ne suivant que la création.
	 */
	private static function getReviewUndatedSql(): \Sql {

		$hasEditionUndated = self::applyEditionOpenOrUpcoming(new EditionModel())
			->whereAid(new \Sql('m1.id'))
			->whereEndDate(NULL)
			->where('('.self::getReviewNeverSql().' OR m1.checkedAt < DATE(s1.createdAt))')
			->existsSql();

		return new \Sql('(m1.status = '.Aid::model()->format(Aid::PUBLISHED).' AND m1.calendar = '.Aid::model()->format(Aid::CALL).' AND '.$hasEditionUndated.')');

	}

	// Une date de plus de six mois, relecture ou date de la reprise : une fiche semée vieillit comme une
	// fiche relue. Une aide sans date n'a pas d'ancienneté à mesurer (NULL < x n'est jamais vrai).
	private static function getReviewStaleSql(): \Sql {

		$limit = Aid::model()->format(date('Y-m-d', \Clock::shift('-'.AidSetting::REVIEW_STALE_MONTHS.' months')));

		return new \Sql('(m1.checkedAt < '.$limit.')');

	}

	// Toute l'aide, et le nom de ce qu'elle désigne : son organisme, son dispositif et sa relectrice.
	public static function getSelectionAdmin(): array {

		return array_merge(array_diff(Aid::getSelection(), ['organization', 'parent', 'checkedBy']), [
			'organization' => ['name'],
			'parent' => ['name', 'organization' => ['name']],
			'checkedBy' => ['firstName', 'lastName'],
		]);

	}

	/**
	 * Une aide pour sa page d'administration, avec tout ce qu'elle porte. Ouverte depuis une page de la
	 * file de revue (`$reviewPage`, restreinte à `$reviewReason`), elle porte aussi le chemin du retour
	 * dans la file (`queue`, getReviewQueue()).
	 */
	public static function getForAdminPage(int $id, ?int $reviewPage = NULL, ?string $reviewReason = NULL): Aid {

		$eAid = new Aid();

		Aid::model()
			->select(self::getSelectionAdmin() + self::getSelectionReview() + [
				'cEdition' => new EditionModel()
					->select(Edition::getSelection())
					->sort(['startDate' => SORT_DESC, 'id' => SORT_DESC])
					->delegateCollection('aid'),
				'cSource' => new SourceModel()
					->select(SourceLib::getSelectionWrite())
					->sort(['kind' => SORT_ASC, 'id' => SORT_ASC])
					->delegateCollection('aid'),
				'cArea' => new AreaModel()
					->select(Area::getSelection())
					->sort(['department' => SORT_ASC])
					->delegateCollection('aid'),
				'cAidOrganization' => new AidOrganizationModel()
					->select(['id', 'aid', 'role', 'organization' => ['name']])
					->sort(['role' => SORT_ASC, 'id' => SORT_ASC])
					->delegateCollection('aid'),
				'cAidLink' => new AidLinkModel()
					->select(['id', 'aid', 'kind', 'target' => ['name', 'organization' => ['name']]])
					->sort(['kind' => SORT_ASC, 'id' => SORT_ASC])
					->delegateCollection('aid'),
				'cAidTag' => new AidTagModel()
					->select(['id', 'aid', 'tag' => ['name', 'position']])
					->delegateCollection('aid', callback: fn(\Collection $cAidTag) => $cAidTag->sort(['tag' => ['position' => SORT_ASC]])),
				'cAidPiece' => new AidPieceModel()
					->select(AidPieceLib::getSelectionAdmin())
					->sort(['moment' => SORT_ASC, 'id' => SORT_ASC])
					->delegateCollection('aid'),
				'cAidChild' => new AidModel()
					->select(['id', 'name', 'status', 'parent'])
					->sort(['name' => SORT_ASC])
					->delegateCollection('parent'),
			])
			->whereId($id)
			->get($eAid);

		if($eAid->notEmpty()) {

			self::fillAdmin(new \Collection([$eAid['id'] => $eAid]));

			$eAid['publishMissing'] = $eAid->getPublishMissing();
			$eAid['alerts'] = self::getAlerts($eAid);
			$eAid['seedFingerprint'] = ($eAid['seedDifferences'] === NULL) ? NULL : self::getSeedFingerprint($eAid['seedDifferences']);
			$eAid['conflictSentence'] = self::getConflictSentence($eAid);
			$eAid['queue'] = ($reviewPage === NULL) ? NULL : self::getReviewQueue(self::getReview($reviewPage, $reviewReason)[0], $eAid, $reviewPage, 'aid', $reviewReason);

			self::fillSeed($eAid);

			foreach($eAid['cSource'] as $eSource) {
				$eSource['deletable'] = $eSource->assertDelete();
			}

			$eAid['cSourceIssue'] = $eAid['cSource']->find(fn(Source $eSource) => $eSource->isAtIssue());

			// Les pièces courantes que la ligne d'ajout propose.
			$eAid['cPiece'] = $eAid->acceptPieces() ? PieceLib::getAll() : new \Collection();

		}

		return $eAid;

	}

	/**
	 * La phrase des notes de relevé qui a fait poser la confiance « sources divergentes » au semis
	 * (ImportLib::VERIFICATIONS), pour que la relecture dise sur quoi elles divergent. Une phrase finit
	 * sur sa ponctuation ou au bout de sa ligne, la recherche écrivant ses réserves une par ligne ou à
	 * la suite. Le semis lit aussi les conditions et le taux, que la fiche affiche en entier : sans phrase
	 * des notes qui réponde au motif, rien n'est cité.
	 */
	public static function getConflictSentence(Aid $eAid): ?string {

		if($eAid['verification'] !== Aid::CONFLICT or $eAid['notes'] === NULL) {
			return NULL;
		}

		$sentences = preg_split('/(?<=[.!?…])\s+|\R/u', $eAid['notes'], flags: PREG_SPLIT_NO_EMPTY);

		foreach($sentences as $sentence) {

			if(preg_match(ImportLib::VERIFICATIONS[Aid::CONFLICT], $sentence)) {
				return trim($sentence);
			}

		}

		return NULL;

	}

	/**
	 * Les rangées de la fiche qui demandent un geste (AdminUi::getItems()) : ce qui manque pour publier
	 * se marque là où il se saisit, le taux sur sa rangée, la source sur « Aux structures » faute de
	 * rangée à elle ; une aide masquée sur « Aux structures » ; une aide de la file de revue sur
	 * « Relecture », et elle seule : une aide jamais relue qui n'y entre pas n'attend aucun geste.
	 */
	private static function getAlerts(Aid $eAid): array {

		$alerts = [];

		// Seul un brouillon attend de se publier : la fiche d'une aide publiée ou archivée ne le dit pas.
		$missing = ($eAid['status'] === Aid::DRAFT) ? $eAid['publishMissing'] : [];

		if(in_array(Aid::MISSING_RATE, $missing, TRUE)) {
			$alerts[] = 'rate';
		}

		if(
			array_intersect($missing, [Aid::MISSING_SOURCE, Aid::MISSING_SOURCE_ALIVE]) !== [] or
			$eAid['hidden'] !== NULL
		) {
			$alerts[] = 'display';
		}

		if($eAid->getCheckReason() !== NULL) {
			$alerts[] = 'check';
		}

		if($eAid['reviewSourcePieces'] and $eAid->acceptPieces()) {
			$alerts[] = 'pieces';
		}

		return $alerts;

	}

	/**
	 * Ce que la revue d'un écart affiche à côté de l'aide (AdminUi::getSeedDifferences()) : ses
	 * propriétés dans l'ordre du formulaire (`seedProperties`), les listes filles de chaque côté en
	 * entier (`seedChildren`, chaque ligne marquée si elle diffère), les noms des organismes et des
	 * tags qu'elles désignent par identifiant, et pour chaque côté si l'aide vaut dans toute la France
	 * (`seedNational`).
	 */
	private static function fillSeed(Aid $eAid): void {

		$eAid['seedProperties'] = [];
		$eAid['seedChildren'] = [];
		$eAid['seedNational'] = ['aid' => FALSE, 'seed' => FALSE];
		$eAid['seedHeld'] = ($eAid['seedDifferences'] === NULL) ? FALSE : $eAid['seedDifferences']['held'];

		if($eAid['seedDifferences'] === NULL) {
			$eAid['cOrganizationSeed'] = new \Collection();
			$eAid['cTagSeed'] = new \Collection();
			return;
		}

		$differences = $eAid['seedDifferences'];

		// La colonne JSON de MySQL range les propriétés de l'écart à sa façon : la revue les reprend dans
		// l'ordre du formulaire.
		foreach(self::getPropertiesSeed() as $property) {
			if(array_key_exists($property, $differences['properties'])) {
				$eAid['seedProperties'][$property] = $differences['properties'][$property];
			}
		}

		$organizations = [];
		$tags = [];

		// L'organisme d'un écart peut être NULL : la fiche n'en nomme aucun.
		if(
			array_key_exists('organization', $differences['properties']) and
			$differences['properties']['organization'] !== NULL
		) {
			$organizations[] = $differences['properties']['organization'];
		}

		// L'écart ne porte que les lignes qui diffèrent : chaque côté se recompose en entier, pour que la
		// revue montre les lignes communes à côté de celles qui changent, et qu'une liste vide ne se
		// confonde pas avec une liste sans différence. Le côté de la fiche part des lignes de l'aide au
		// moment de la revue : une retouche faite à la main depuis la reprise s'y lit comme si la fiche
		// la portait aussi.
		foreach($differences['children'] as $module => $changes) {

			// Une colonne JSON de MySQL range les clés d'un objet à sa façon : chaque ligne reprend l'ordre
			// de ImportLib::CHILDREN, faute de quoi une ligne de l'écart ne retrouverait jamais celle de
			// l'aide, et les deux côtés se trient sur le même ordre.
			$orderKey = fn(array $key) => array_replace(array_fill_keys(ImportLib::CHILDREN[$module], NULL), $key);

			$keys = array_map($orderKey, ImportLib::getChildKeys($eAid, $module));
			$changes['added'] = array_map($orderKey, $changes['added']);
			$changes['removed'] = array_map($orderKey, $changes['removed']);

			$keysSeed = array_values(array_filter($keys, fn(array $key) => in_array($key, $changes['removed'], TRUE) === FALSE));

			foreach($changes['added'] as $key) {
				if(in_array($key, $keysSeed, TRUE) === FALSE) {
					$keysSeed[] = $key;
				}
			}

			sort($keys);
			sort($keysSeed);

			$eAid['seedChildren'][$module] = [
				'aid' => array_map(fn(array $key) => ['key' => $key, 'changed' => in_array($key, $changes['removed'], TRUE)], $keys),
				'seed' => array_map(fn(array $key) => ['key' => $key, 'changed' => in_array($key, $changes['added'], TRUE)], $keysSeed),
			];

			// Une aide nationale ou PAC sans département vaut dans toute la France (applyDepartment()). Les
			// régions se déduisent des départements : une aide sans département n'en a aucune.
			if($module === 'Area') {

				$levelSeed = array_key_exists('level', $differences['properties']) ? $differences['properties']['level'] : $eAid['level'];

				$eAid['seedNational'] = [
					'aid' => ($keys === [] and in_array($eAid['level'], [Aid::NATIONAL, Aid::PAC], TRUE)),
					'seed' => ($keysSeed === [] and in_array($levelSeed, [Aid::NATIONAL, Aid::PAC], TRUE)),
				];

			}

			foreach(array_merge($keys, $keysSeed) as $key) {

				if($module === 'AidOrganization') {
					$organizations[] = $key['organization'];
				} else if($module === 'AidTag') {
					$tags[] = $key['tag'];
				}

			}

		}

		$eAid['cOrganizationSeed'] = OrganizationLib::getByIds($organizations, ['id', 'name'], index: 'id');
		$eAid['cTagSeed'] = TagLib::getByIds($tags, ['id', 'name'], index: 'id');

	}

	// Le nombre d'aides à revoir, que l'onglet de la file annonce.
	public static function countReview(): int {
		return new AidModel()->where(self::getReviewDueSql())->count();
	}

	/**
	 * Les aides dont le nom contient la saisie, pour choisir un dispositif parent ou la cible d'un lien.
	 * L'aide en cours ne se propose pas : elle ne peut être ni son propre volet ni liée à elle-même.
	 */
	public static function getByQuery(string $query, Aid $eAidExclude): \Collection {

		return Aid::model()
			->select(['id', 'name', 'status', 'organization' => ['name']])
			->whereName('LIKE', '%'.$query.'%', if: $query !== '')
			->whereId('!=', $eAidExclude, if: $eAidExclude->notEmpty())
			->whereStatus('!=', Aid::ARCHIVED)
			->sort(['name' => SORT_ASC])
			->getCollection(0, 20);

	}

	/**
	 * L'aide qui porte cet identifiant de recherche parmi ses alias, c'est-à-dire celle qu'une reprise
	 * des fiches de recherche a déjà créée, ou une aide vide.
	 */
	public static function getByAlias(string $alias): Aid {

		$eAid = new Aid();

		Aid::model()
			->select(Aid::getSelection())
			->where(new \Sql('JSON_CONTAINS(aliases, '.Aid::model()->format(json_encode($alias)).')'))
			->get($eAid);

		return $eAid;

	}

	/**
	 * Comme le catalogue E-Phy (`toolbox\CatalogLib::open()`), l'outil n'enregistre rien : c'est sa
	 * première ouverture qui en fait un outil de la structure, et lui donne sa roue et son étoile.
	 */
	public static function open(\structure\Structure $eStructure): void {

		if(in_array(\toolbox\Favorite::AID, $eStructure->conf(\structure\Feature::class)['hasToolbox'])) {
			return;
		}

		\structure\FeatureLib::addToolbox($eStructure, \toolbox\Favorite::AID);

		$eFeature = $eStructure->conf(\structure\Feature::class);
		$eFeature['hasToolbox'] = [...$eFeature['hasToolbox'], \toolbox\Favorite::AID];

	}

	/**
	 * Ce que la structure dit d'elle et qui borne les aides : son département, ses productions actives,
	 * et si elle est en bio ou en conversion, comme le catalogue E-Phy le lit.
	 *
	 * Le département est celui du lieu de production, où se jouent les aides d'un territoire, même
	 * quand le siège est ailleurs. Tant que le lieu n'en a pas, qu'il manque ou que sa commune reste à
	 * déduire, le code postal du siège en tient lieu : un appel à geo.api.gouv.fr qui échoue ne fait
	 * jamais reculer une structure qui voyait déjà son département. `isPlaced` dit si le lieu est posé,
	 * pour distinguer un lieu à renseigner d'un département en attente.
	 *
	 * Le pays déclaré passe avant le lieu : une structure installée à l'étranger n'a pas de département
	 * à renseigner, et `SystemLib::prepareCodes()` ne lui en déduit aucun. Sans pays, elle est présumée
	 * française.
	 */
	public static function getProfile(\structure\Structure $eStructure): array {

		$eStructure->expects(['legalPostcode', 'legalCountry', 'organic']);

		$eSystem = $eStructure->system();

		$isFrance = ($eStructure['legalCountry']->empty() or $eStructure['legalCountry']->isFrance());

		if($isFrance === FALSE) {
			$department = NULL;
		} else if($eSystem['placeDepartment'] !== NULL) {
			$department = $eSystem['placeDepartment'];
		} else {
			$department = AidSetting::getDepartmentByPostcode($eStructure['legalPostcode']);
		}

		$productions = \structure\ProductionLib::getByStructure($eStructure)
			->find(fn(\structure\Production $eProduction) => $eProduction['status'] === \structure\Production::ACTIVE)
			->getColumn('fqn');

		return [
			'isFrance' => $isFrance,
			'isPlaced' => ($eSystem['placeLngLat'] !== NULL),
			'department' => $department,
			'productions' => array_values(array_intersect($productions, \structure\Production::getAll())),
			'organic' => \work\ToolLib::isOrganicOnly($eStructure),
		];

	}

	/**
	 * Les critères lus dans l'adresse, chacun ramené à la liste fermée de ses valeurs. Absent, un
	 * critère vaut ce que dit le profil de la structure ; vide (`department=`, `productions=`,
	 * `organic=0`), il est retiré. Une valeur qui ne désigne rien est ignorée, comme si le jeton n'avait
	 * pas été posé.
	 */
	public static function getFilters(\structure\Structure $eStructure, array $input): array {

		$profile = self::getProfile($eStructure);

		$department = $input['department'] ?? NULL;

		if($department === '') {
			$department = NULL;
		} else if(is_string($department) === FALSE or array_key_exists($department, AidSetting::DEPARTMENTS) === FALSE) {
			$department = $profile['department'];
		}

		$productions = $input['productions'] ?? NULL;

		if($productions === '') {
			$productions = [];
		} else if(is_string($productions)) {
			$productions = array_values(array_intersect(\structure\Production::getAll(), explode(',', $productions)));
			if($productions === []) {
				$productions = $profile['productions'];
			}
		} else {
			$productions = $profile['productions'];
		}

		$organic = $input['organic'] ?? NULL;

		$project = $input['project'] ?? NULL;

		if(is_string($project) === FALSE or in_array((int)$project, Tag::model()->getPropertySet('projects'), TRUE) === FALSE) {
			$project = NULL;
		} else {
			$project = (int)$project;
		}

		$fqn = $input['tag'] ?? NULL;

		$page = $input['page'] ?? NULL;

		$eTag = is_string($fqn) ?
			Tag::model()
				->select(Tag::getSelection())
				->whereFqn($fqn)
				->get() :
			new Tag();

		return [
			'profile' => $profile,
			'department' => $department,
			'placeMissing' => ($profile['isFrance'] and $profile['department'] === NULL and $department === NULL),
			'productions' => $productions,
			'organic' => ($organic === NULL) ? $profile['organic'] : ($organic === '1'),
			'project' => $project,
			'tag' => $eTag,
			'all' => array_key_exists('all', $input),
			'page' => (is_string($page) and ctype_digit($page)) ? (int)$page : 0,
		];

	}

	/**
	 * Les aides affichées que laissent le lieu, les productions et le bio, chacune avec ses tags
	 * (`cTag`), ses projets (`projects`, l'union de ceux de ses tags), sa prochaine clôture (`endDate`)
	 * et ses morceaux de périmètre. Un catalogue de quelques centaines d'aides : les comptes par projet
	 * et par tag se font sur cette collection plutôt qu'en une requête par compte.
	 *
	 * Le lieu se lit comme le dit applyDepartment(). Une aide sans production vaut pour toutes.
	 * Retirer le bio écarte les aides qui l'exigent seul (`labels`), jamais celles qu'il bonifie
	 * (`labelsBonus`) : `labels` se lit « l'un ou l'autre », et le profil ne dit rien des autres labels,
	 * qu'une structure hors bio peut avoir. Une aide de conversion reste : elle s'adresse justement
	 * à qui n'est pas encore en bio.
	 */
	public static function getByFilters(array $filters): \Collection {

		$mAid = self::applyDisplay(new AidModel());

		if($filters['department'] !== NULL) {
			self::applyDepartment($mAid, $filters['department']);
		}

		if($filters['productions'] !== []) {
			$mAid->where('(JSON_LENGTH(m1.productions) = 0 OR JSON_OVERLAPS(m1.productions, '.$mAid->format(json_encode($filters['productions'])).'))');
		}

		if($filters['organic'] === FALSE) {
			$hasConversion = new AidTagModel()
				->join(Tag::model(), 's1.tag = s2.id')
				->where('s1.aid', new \Sql('m1.id'))
				->where('s2.fqn', Tag::CONVERSION)
				->existsSql();

			$mAid->where('(m1.labels IS NULL OR m1.labels != '.Aid::AB.' OR '.$hasConversion.')');
		}

		$cAid = $mAid
			->select(self::getCardSelection())
			->sort(['name' => SORT_ASC])
			->getCollection(index: 'id');

		self::fill($cAid);

		// Les clôtures datées d'abord, puis les aides sans date, puis celles dont le dépôt n'ouvre pas encore,
		// et celles qui attendent leur campagne en dernier : aucune aide fermée aujourd'hui ne passe devant
		// une aide où déposer. Les aides ouvertes se rangent par leur clôture, les aides à venir par leur
		// ouverture, la prochaine date qui compte pour chacune (`nextDate`), que la carte et la fiche datent.
		$cAid->sort(fn(Aid $eAid1, Aid $eAid2) => (
			($eAid1['isBetweenCampaigns'] <=> $eAid2['isBetweenCampaigns']) ?:
			($eAid1['isUpcoming'] <=> $eAid2['isUpcoming']) ?:
			(($eAid1['nextDate'] === NULL) <=> ($eAid2['nextDate'] === NULL)) ?:
			strcmp((string)$eAid1['nextDate'], (string)$eAid2['nextDate'])
		));

		return $cAid;

	}

	/**
	 * Les aides qu'une structure de ce département peut demander. Une aide nationale ou de la PAC sans
	 * ligne de périmètre vaut partout ; les autres valent dans les départements de leurs lignes `Area`,
	 * y compris une mesure de l'État bornée à un département d'outre-mer, que la recherche classe
	 * nationale par son organisme.
	 */
	private static function applyDepartment(AidModel $mAid, string $department): AidModel {

		$hasArea = new AreaModel()
			->whereAid(new \Sql('m1.id'))
			->whereDepartment($department)
			->existsSql();

		$hasAnyArea = new AreaModel()
			->whereAid(new \Sql('m1.id'))
			->existsSql();

		return $mAid->where('((m1.level IN ('.$mAid->format(Aid::NATIONAL).', '.$mAid->format(Aid::PAC).') AND NOT '.$hasAnyArea.') OR '.$hasArea.')');

	}

	/**
	 * Ce qu'une carte d'aide lit, dans la liste comme sous « Se complète par ».
	 */
	protected static function getCardSelection(): array {

		return [
			'id', 'name', 'type', 'calendar', 'rate', 'ratePercentMax', 'amountMax', 'amountUnit', 'ceiling', 'boundBase', 'boundPeriod', 'level', 'productions', 'labels', 'beneficiaries', 'direct', 'verification',
			'organization' => ['name', 'type'],
		];

	}

	/**
	 * Pose sur chaque aide ses tags, ses projets, ses lieux, sa prochaine clôture, l'édition où déposer
	 * (`eEditionDeposit`, à venir quand `isUpcoming`), la prochaine date qui compte pour déposer (`nextDate`,
	 * son ouverture tant qu'elle est à venir, sa clôture ensuite) et son taux du moment, lus par quelques
	 * requêtes pour toute la collection. Le taux de cette édition l'emporte sur celui de l'aide le temps de l'édition
	 * (`aid.yml`).
	 *
	 * Une aide que ses sources contredisent, ou restée à vérifier (`isUnconfirmed`), renvoie l'agriculteur
	 * vers l'organisme, sur sa carte comme sur sa fiche, sans dire d'où vient le doute : d'où l'équipe
	 * tient un chiffre ne regarde qu'elle.
	 */
	protected static function fill(\Collection $cAid): void {

		if($cAid->empty()) {
			return;
		}

		$ids = $cAid->getIds();

		$cTag = self::getTags();

		$tags = [];

		foreach(AidTag::model()->select(['aid', 'tag'])->whereAid('IN', $ids)->getCollection() as $eAidTag) {
			$tags[$eAidTag['aid']['id']][] = $eAidTag['tag']['id'];
		}

		$departments = [];

		foreach(Area::model()->select(['aid', 'department'])->whereAid('IN', $ids)->getCollection() as $eArea) {
			$departments[$eArea['aid']['id']][] = $eArea['department'];
		}

		// L'édition où déposer : celle qui ferme la première parmi les ouvertes, sinon, pour une aide qui n'en
		// a pas d'ouverte, celle qui ouvre la première parmi les éditions à venir. La carte et la fiche datent
		// celle-ci par son ouverture, qu'un dépôt ne précède pas.
		$cEditionOpen = self::applyEditionOpen(new EditionModel())
			->select(['aid', 'startDate', 'endDate', 'rate', 'ceiling'])
			->whereAid('IN', $ids)
			->sort(new \Sql('m1.endDate IS NULL, m1.endDate ASC'))
			->getCollection();

		$editionsOpen = [];

		foreach($cEditionOpen as $eEdition) {
			$editionsOpen[$eEdition['aid']['id']] ??= $eEdition;
		}

		$idsWithoutOpen = array_values(array_diff($ids, array_keys($editionsOpen)));

		$editionsUpcoming = [];

		if($idsWithoutOpen !== []) {

			$cEditionUpcoming = self::applyEditionUpcoming(new EditionModel())
				->select(['aid', 'startDate', 'endDate', 'rate', 'ceiling'])
				->whereAid('IN', $idsWithoutOpen)
				->sort(['startDate' => SORT_ASC, 'id' => SORT_ASC])
				->getCollection();

			foreach($cEditionUpcoming as $eEdition) {
				$editionsUpcoming[$eEdition['aid']['id']] ??= $eEdition;
			}

		}

		$editions = $editionsOpen + $editionsUpcoming;

		// La dernière campagne d'une aide annuelle qui n'en a ni d'ouverte ni d'à venir, que la carte et la
		// fiche datent : une campagne passée, c'est-à-dire finie au plus tard aujourd'hui, ou sans date de fin mais close
		// ou épuisée. Une édition suspendue sans date de fin peut reprendre : elle n'est pas passée, pas
		// plus qu'une édition, quel que soit son état, qui finit à une date future. Une édition ouverte qui
		// finit aujourd'hui ne vient pas jusqu'ici : elle compte parmi les éditions ouvertes de l'aide.
		// Deux campagnes à la même date se départagent par la dernière saisie.
		$idsBetweenCampaigns = $cAid
			->find(fn(Aid $eAid) => $eAid['calendar'] === Aid::CAMPAIGN and isset($editions[$eAid['id']]) === FALSE)
			->getIds();

		$editionsLast = [];

		if($idsBetweenCampaigns !== []) {

			$cEditionLast = Edition::model()
				->select(['aid', 'startDate', 'endDate'])
				->whereAid('IN', $idsBetweenCampaigns)
				->or(
					fn() => $this->whereEndDate('<=', \Clock::date()),
					fn() => $this->whereEndDate(NULL)->whereState('IN', [Edition::CLOSED, Edition::EXHAUSTED])
				)
				->sort(new \Sql('COALESCE(m1.endDate, m1.startDate) DESC, m1.id DESC'))
				->getCollection();

			foreach($cEditionLast as $eEdition) {
				$editionsLast[$eEdition['aid']['id']] ??= $eEdition;
			}

		}

		$tomorrow = date('Y-m-d', \Clock::shift('+1 day'));

		foreach($cAid as $eAid) {

			$eEdition = $editions[$eAid['id']] ?? new Edition();

			if($eEdition->notEmpty() and $eEdition['rate'] !== NULL) {
				$eAid['rate'] = $eEdition['rate'];
			}

			// Les chiffres de l'aide ne se montrent que sur une subvention. Sur un prêt, un pourcentage se lirait
			// comme un taux d'intérêt, et sur une exonération c'est un maximum que peu atteignent. Le taux d'une
			// édition, que la fiche affiche, l'emporte de même sur eux.
			$canShowFigures = ($eAid['type'] === Aid::GRANT and ($eEdition->empty() or $eEdition['rate'] === NULL));

			if($canShowFigures === FALSE) {
				$eAid['ratePercentMax'] = NULL;
				$eAid['amountMax'] = NULL;
			}

			$eAid['cTag'] = $cTag->find(fn(Tag $eTag) => in_array($eTag['id'], $tags[$eAid['id']] ?? [], TRUE));
			$eAid['projects'] = array_reduce($eAid['cTag']->getColumn('projects'), fn(int $projects, \Set $set) => $projects | $set->get(), 0);
			$eAid['eEditionDeposit'] = $eEdition;
			$eAid['isUpcoming'] = isset($editionsUpcoming[$eAid['id']]);
			$eAid['isBetweenCampaigns'] = in_array($eAid['id'], $idsBetweenCampaigns, TRUE);
			$eAid['eEditionLast'] = $editionsLast[$eAid['id']] ?? new Edition();
			$eAid['endDate'] = $eEdition->empty() ? NULL : $eEdition['endDate'];
			// La prochaine date qui compte pour déposer : l'ouverture tant que le dépôt n'est pas ouvert, la clôture ensuite.
			$eAid['nextDate'] = $eAid['isUpcoming'] ? $eEdition['startDate'] : $eAid['endDate'];
			$eAid['endsTomorrow'] = ($eAid['endDate'] === $tomorrow);
			$eAid['isUnconfirmed'] = in_array($eAid['verification'], [Aid::CONFLICT, Aid::TO_CHECK], TRUE);
			$eAid['rate'] = self::cleanProvenance($eAid['rate']);
			$eAid['rateShort'] = self::getRateShort($eAid['rate'], $eAid['type']);

			// Le plafond vient en dernier. Il ne dit ce que la structure reçoit que posé sur l'aide, sur la dépense
			// il borne ce qu'elle engage, et la proposition chiffrée du taux, citée de la source, est plus juste
			// qu'une borne. Celui de l'édition où déposer l'emporte, comme sur la fiche.
			if(
				$canShowFigures and $eAid['boundBase'] === Aid::AID and
				$eAid['ratePercentMax'] === NULL and $eAid['amountMax'] === NULL and $eAid['rateShort'] === NULL
			) {
				$eAid['ceilingAid'] = ($eEdition->notEmpty() and $eEdition['ceiling'] !== NULL) ? $eEdition['ceiling'] : $eAid['ceiling'];
			} else {
				$eAid['ceilingAid'] = NULL;
			}

			$eAid['productionsShown'] = self::getProductionsShown($eAid['productions']);
			// « et 1 autre » prendrait la place du nom qu'il tait : la carte ne coupe que s'il en reste au moins deux.
			$eAid['productionsCard'] = (count($eAid['productionsShown']) > AidSetting::PRODUCTIONS_CARD + 1) ?
				array_slice($eAid['productionsShown'], 0, AidSetting::PRODUCTIONS_CARD) :
				$eAid['productionsShown'];
			$eAid['productionsMore'] = count($eAid['productionsShown']) - count($eAid['productionsCard']);
			$eAid['places'] = self::getPlaces($departments[$eAid['id']] ?? [], $eAid['organization']['type'] === Organization::WATER_AGENCY);

		}

	}

	/**
	 * Un texte de la fiche sans ses notes de provenance : la recherche a écrit entre parenthèses d'où elle
	 * tient un chiffre (« fourchette relevée par l'agrégateur », « (Fève) », « selon l'agrégateur »),
	 * pour la relecture de l'équipe. L'agriculteur ne lit que la source officielle, donc une parenthèse
	 * qui nomme un relevé, un agrégateur ou une base tierce tombe en entier. Les bornes de mot gardent
	 * « prélevé » et « relevant ». Une parenthèse qui porte une balise n'est pas touchée, le texte
	 * des conditions étant du HTML.
	 */
	public static function cleanProvenance(?string $text): ?string {

		if($text === NULL) {
			return NULL;
		}

		$text = preg_replace('/\s*\((?=[^()<>]*(?<!\pL)(?:agrégateur|aides-territoires|Fève|relev(?:é|ée|és|ées))(?!\pL))[^()<>]*\)/iu', '', $text);

		$text = trim($text);

		return ($text === '') ? NULL : $text;

	}

	/**
	 * Les productions d'une aide dans l'ordre du catalogue (Production::getAll()), quel que soit celui de
	 * la saisie. « Autre production » ne s'ajoute pas à des productions nommées : elle ne dit alors
	 * rien de plus qu'elles.
	 */
	public static function getProductionsShown(array $productions): array {

		$productionsShown = array_values(array_intersect(\structure\Production::getAll(), $productions));

		if(count($productionsShown) > 1) {
			$productionsShown = array_values(array_diff($productionsShown, \structure\Production::OTHER));
		}

		return $productionsShown;

	}

	/**
	 * La première proposition du taux que publie l'organisme, quand elle tient entière sur une ligne
	 * de carte (`AidSetting::RATE_SHORT_LENGTH`) et porte un chiffre, NULL sinon : une phrase coupée
	 * se lirait comme une règle complète, et la carte écrit cette proposition à la place du montant,
	 * où « Forfait » ou « Sans objet » passeraient pour un montant. La nature de l'aide en tient alors
	 * lieu. La carte préfère les champs chiffrés quand ils sont posés.
	 * La gratuité d'un service fait exception : c'est ce que la structure y gagne, son montant en somme.
	 */
	public static function getRateShort(?string $rate, string $type): ?string {

		if($rate === NULL) {
			return NULL;
		}

		$rate = trim(explode(';', $rate)[0]);

		$isFree = ($type === Aid::SERVICE and preg_match('/(?<!\pL)gratuit/iu', $rate) === 1);

		if(preg_match('/\d/', $rate) === 0 and $isFree === FALSE) {
			return NULL;
		}

		return (mb_strlen($rate) <= AidSetting::RATE_SHORT_LENGTH) ? $rate : NULL;

	}

	/**
	 * Les éditions pas encore closes par leur date, qu'elles reçoivent déjà les dépôts ou ne les reçoivent
	 * qu'à partir de leur date d'ouverture (Edition::isOpenOrUpcoming()). Les colonnes restent sans alias :
	 * la condition vaut sous `m1` dans une requête, et sous `s1` dans la citation d'applyDisplay().
	 */
	protected static function applyEditionOpenOrUpcoming(EditionModel $mEdition): EditionModel {

		return $mEdition
			->whereState(Edition::OPEN)
			->or(
				fn() => $this->whereEndDate(NULL),
				fn() => $this->whereEndDate('>=', \Clock::date())
			);

	}

	// Les éditions qui reçoivent les dépôts aujourd'hui (Edition::isOpen()) : ouvertes sans date d'ouverture, ou depuis.
	protected static function applyEditionOpen(EditionModel $mEdition): EditionModel {

		return self::applyEditionOpenOrUpcoming($mEdition)
			->or(
				fn() => $this->whereStartDate(NULL),
				fn() => $this->whereStartDate('<=', \Clock::date())
			);

	}

	// Les éditions dont le dépôt n'ouvre qu'après aujourd'hui (Edition::isUpcoming()).
	protected static function applyEditionUpcoming(EditionModel $mEdition): EditionModel {

		return self::applyEditionOpenOrUpcoming($mEdition)
			->whereStartDate('>', \Clock::date());

	}

	// La taxonomie entière, dans son ordre : une cinquantaine de tags.
	public static function getTags(): \Collection {

		return Tag::model()
			->select(Tag::getSelection())
			->sort(['position' => SORT_ASC])
			->getCollection(index: 'id');

	}

	/**
	 * Par projet, dans l'ordre du set, le nombre d'aides qui portent au moins un de ses tags. Une aide
	 * à cheval sur deux projets compte dans les deux.
	 */
	public static function countByProject(\Collection $cAid): array {

		$counts = array_fill_keys(Tag::model()->getPropertySet('projects'), 0);

		foreach($cAid as $eAid) {
			foreach($counts as $project => $count) {
				if($eAid['projects'] & $project) {
					$counts[$project]++;
				}
			}
		}

		return $counts;

	}

	/**
	 * Les tags d'un projet qui ont au moins une aide dans la collection, chacun avec son nombre
	 * (`count`), du plus fourni au moins fourni.
	 */
	public static function countByTag(\Collection $cAid, int $project): \Collection {

		$counts = [];

		foreach($cAid as $eAid) {
			foreach($eAid['cTag'] as $eTag) {
				$counts[$eTag['id']] = ($counts[$eTag['id']] ?? 0) + 1;
			}
		}

		$cTag = self::getTags()->find(fn(Tag $eTag) => (
			$eTag['projects']->value($project) and
			array_key_exists($eTag['id'], $counts)
		));

		foreach($cTag as $eTag) {
			$eTag['count'] = $counts[$eTag['id']];
		}

		$cTag->sort(fn(Tag $eTag1, Tag $eTag2) => ($eTag2['count'] <=> $eTag1['count']) ?: ($eTag1['position'] <=> $eTag2['position']));

		return $cTag;

	}

	// Les aides d'un projet, puis d'un tag, quand ils sont posés.
	public static function filterByProject(\Collection $cAid, ?int $project, Tag $eTag): \Collection {

		return $cAid->find(fn(Aid $eAid) => (
			($project === NULL or ($eAid['projects'] & $project)) and
			($eTag->empty() or $eAid['cTag']->offsetExists($eTag['id']))
		));

	}

	/**
	 * Le nombre de pages d'une liste, une page au moins : une liste vide s'affiche sur sa première.
	 */
	public static function countPages(\Collection $cAid): int {
		return max(1, (int)ceil($cAid->count() / AidSetting::PER_LIST));
	}

	/**
	 * Les aides d'une page, comptée à partir de zéro, dans l'ordre de la liste. La collection est
	 * entière en mémoire parce que son ordre suit la prochaine clôture, que `fill()` calcule après la
	 * requête : la base ne saurait pas paginer dans cet ordre.
	 */
	public static function getPage(\Collection $cAid, int $page): \Collection {

		$offset = $page * AidSetting::PER_LIST;

		return $cAid->slice($offset, min(AidSetting::PER_LIST, $cAid->count() - $offset));

	}

	/**
	 * Une aide publiée qui ne s'affiche pas, ou une aide vide. Son adresse, gardée par une structure, dit
	 * pourquoi sans en montrer la fiche (arbitrages de V-Sub sur le glaçon 1 de L5), par la raison que
	 * l'équipe lit aussi (`hidden`, Aid::getHiddenReason()). Rien d'autre ne s'y lit.
	 */
	public static function getHidden(int $id): Aid {

		$eAid = Aid::model()
			->select([
				'id', 'status', 'calendar',
				'cEdition' => new EditionModel()->select(['id', 'aid', 'state', 'endDate'])->delegateCollection('aid'),
				'cSource' => new SourceModel()->select(['id', 'aid', 'kind', 'status'])->delegateCollection('aid'),
			])
			->whereId($id)
			->get();

		if($eAid->empty()) {
			return $eAid;
		}

		$eAid['hidden'] = $eAid->getHiddenReason();

		if($eAid['hidden'] === NULL) {
			return new Aid();
		}

		return $eAid;

	}

	/**
	 * Une aide affichée pour sa fiche, avec ce que la fiche montre : ses tags, l'édition qui borne son
	 * dépôt, ses départements, les organismes qui la cofinancent, sa source officielle et les aides
	 * affichées qu'elle complète. Une aide vide quand elle n'est pas affichée : un brouillon, une aide
	 * close ou à source morte ne s'ouvre pas, même par son adresse. Les conditions perdent leurs notes
	 * de provenance, comme le taux (cleanProvenance()).
	 *
	 * Les compléments suivent le département de la liste quittée (`$department`, NULL pour toute la
	 * France), comme le dit applyDepartment() : le lien est éditorial, mais une aide que la structure ne
	 * peut pas demander là où elle cherche ne complète rien. Les productions et le bio ne les bornent
	 * pas : la ligne grise de leur carte les dit, et la structure juge si elle en relève.
	 */
	public static function getSheet(int $id, ?string $department): Aid {

		// La sélection de la carte d'abord : la fiche en est le détail et dit au moins le même chiffre.
		$eAid = Aid::model()
			->select(array_merge(self::getCardSelection(), [
				'summary', 'conditions', 'labelsBonus', 'floor', 'status', 'checkedAt', 'checkedBy',
				'depositUrl', 'commitmentRule', 'piecesUnpublished', 'piecesCheckedAt',
				'cEdition' => new EditionModel()->select(Edition::getSelection())->delegateCollection('aid'),
				'cSource' => new SourceModel()->select(Source::getSelection())->delegateCollection('aid'),
			]))
			->whereId($id)
			->get();

		if($eAid->empty() or $eAid->acceptDisplay() === FALSE) {
			return new Aid();
		}

		$cAid = new \Collection([$eAid['id'] => $eAid]);
		self::fill($cAid);

		// Le plafond de l'édition où déposer l'emporte sur celui de l'aide le temps de l'édition (`aid.yml`).
		if($eAid['eEditionDeposit']->notEmpty() and $eAid['eEditionDeposit']['ceiling'] !== NULL) {
			$eAid['ceiling'] = $eAid['eEditionDeposit']['ceiling'];
		}

		// Un taux écrit ne reprend pas toujours les bornes, que la fiche dit donc à côté de lui, avec leur
		// assiette. Sans assiette, le plancher peut porter sur la dépense et le plafond sur l'aide : les
		// afficher ensemble donnerait un montant faux, la fiche les tait. Hors subvention, fill() retire
		// les chiffres de l'aide pour une raison qui vaut aussi pour elles.
		if($eAid['type'] !== Aid::GRANT or $eAid['boundBase'] === NULL) {
			$eAid['floor'] = NULL;
			$eAid['ceiling'] = NULL;
		}

		$eAid['eSourcePrimary'] = $eAid['cSource']->find(fn(Source $eSource) => $eSource['kind'] === Source::PRIMARY, limit: 1, default: new Source());
		$eAid['eSourcePieces'] = $eAid['cSource']->find(fn(Source $eSource) => $eSource['kind'] === Source::PIECES, limit: 1, default: new Source());

		// Une aide que la structure ne dépose pas elle-même, ou sans dossier, n'a pas de pièces à dire
		// (Aid::acceptPieces()), même saisies avant que sa forme change.
		$eAid['cAidPiece'] = $eAid->acceptPieces() ?
			AidPiece::model()
				->select(['id', 'moment', 'piece' => ['name'], 'name', 'note', 'url'])
				->whereAid($eAid)
				->sort(['moment' => SORT_ASC, 'id' => SORT_ASC])
				->getCollection() :
			new \Collection();
		$eAid['conditions'] = self::cleanProvenance($eAid['conditions']);

		$eAid['cOrganizationCofunder'] = Organization::model()
			->select(['name'])
			->whereId('IN', new AidOrganizationModel()->select('organization')->whereAid($eAid)->whereRole(AidOrganization::COFUNDER)->getSql())
			->sort(['name' => SORT_ASC])
			->getCollection();

		// Une aide qu'un tiers dépose nomme ce tiers après son public (AidUi::getBeneficiaries()). Un
		// public sans exploitation est déjà celui qui dépose : la fiche ne le redit pas. Dès qu'une
		// exploitation y figure, la mention lui apprend qu'elle ne dépose pas elle-même, et reste.
		$eAid['isApplicantNamed'] = (
			$eAid['direct'] === FALSE and
			$eAid['beneficiaries'] !== NULL and
			$eAid['beneficiaries']->values() !== [] and
			array_intersect($eAid['beneficiaries']->values(), [Aid::FARM, Aid::YOUNG_FARMER, Aid::NEW_FARMER, Aid::LANDOWNER]) === []
		);

		$mAidComplement = self::applyDisplay(new AidModel())
			->whereId('IN', new AidLinkModel()->select('target')->whereAid($eAid)->whereKind(AidLink::COMPLEMENTS)->getSql());

		if($department !== NULL) {
			self::applyDepartment($mAidComplement, $department);
		}

		$cAidComplement = $mAidComplement
			->select(self::getCardSelection())
			->sort(['name' => SORT_ASC])
			->getCollection(index: 'id');

		self::fill($cAidComplement);

		$eAid['cAidComplement'] = $cAidComplement;

		return $eAid;

	}

	/**
	 * Les départements d'une aide, ramenés à leur région quand ils la couvrent toute : une aide
	 * régionale porte une ligne par département, et la fiche dirait douze noms pour en dire un. Une
	 * agence de l'eau (`$isWaterAgency`) intervient sur son bassin, que l'import ajoute entier : il se
	 * nomme quand les départements sont exactement les siens, ou ceux de deux bassins d'une même
	 * agence, la Corse et Rhône-Méditerranée. Un bassin d'un seul département, comme la Guadeloupe, se
	 * nomme comme tout département seul, ici par sa région. Les bassins se chevauchent et certains
	 * recouvrent une région (Artois-Picardie et les Hauts-de-France), donc l'égalité exacte et le seul
	 * organisme gardent une région de se lire comme un bassin.
	 */
	public static function getPlaces(array $departments, bool $isWaterAgency): array {

		$departments = array_values(array_unique(array_map(strval(...), $departments)));
		sort($departments, SORT_STRING);

		if($isWaterAgency) {

			foreach(ImportLib::WATER_AGENCIES as $basins) {

				$basinDepartments = AidSetting::getDepartmentsByBasins($basins);

				if(count($basinDepartments) > 1 and $basinDepartments === $departments) {
					return [
						'basins' => $basins,
						'regions' => [],
						'departments' => [],
					];
				}

			}

		}

		$regions = [];

		foreach(array_keys(AidSetting::REGIONS) as $region) {

			$regionDepartments = AidSetting::getDepartmentsByRegion((string)$region);

			if(array_diff($regionDepartments, $departments) === []) {
				$regions[] = (string)$region;
				$departments = array_diff($departments, $regionDepartments);
			}

		}

		return [
			'basins' => [],
			'regions' => $regions,
			'departments' => array_values($departments),
		];

	}

	/**
	 * Ce que la barre de recherche propose, hors des projets et des productions, dont le nom n'existe
	 * que traduit et se compare donc à l'écran : les tags et les aides affichées dont le nom contient la
	 * saisie, qui mènent à leur fiche. Une famille déjà posée ne se propose plus. Le département se
	 * choisit à part, à côté du titre (getDepartments()).
	 */
	public static function getSuggestions(array $filters, string $query, int $limit = 50): array {

		$needle = toFqn($query);

		$cTag = $filters['tag']->empty() ?
			self::getTags()->find(fn(Tag $eTag) => ($needle === '' or str_contains(toFqn($eTag['name']), $needle))) :
			new \Collection();

		$cAid = new \Collection();

		// Au-dessous de deux caractères, la saisie trouverait presque tout le catalogue. Le nom d'une aide
		// est celui que lui donne l'organisme et dit rarement ce qu'elle finance : la saisie se compare
		// aussi aux étiquettes, qui le disent, et à l'organisme, qu'on cherche par son sigle.
		if(mb_strlen($needle) >= 2) {
			$cAid = self::getByFilters($filters)->find(fn(Aid $eAid) => array_any(
				[$eAid['name'], $eAid['organization']['name'], ...$eAid['cTag']->getColumn('name')],
				fn(string $name) => str_contains(toFqn($name), $needle)
			), limit: $limit);
		}

		return [
			'cTag' => $cTag,
			'cAid' => $cAid,
		];

	}

	/**
	 * Les codes des départements dont un mot du nom ou le code commence par la saisie, dans l'ordre de
	 * leur code, tous quand elle est vide : « rhin » trouve les deux Rhin, « alpes » les trois
	 * départements alpins. `toFqn()` sépare les mots par un tiret, qui sert donc de bord de mot.
	 */
	public static function getDepartments(string $query): array {

		$needle = toFqn($query);
		$code = mb_strtoupper(trim($query));

		$departments = [];

		foreach(AidSetting::DEPARTMENTS as $department => [$name]) {
			if($needle === '' or str_contains('-'.toFqn($name), '-'.$needle) or str_starts_with((string)$department, $code)) {
				$departments[] = (string)$department;
			}
		}

		return $departments;

	}

	// Une aide neuve porte ce que la page ou le semis lisent ensuite (getPublishMissing()), que ses listes
	// de propriétés construisent ou non.
	public static function create(Aid $e): void {

		$e->add([
			'rateUnpublished' => FALSE,
			'seedDifferences' => NULL,
			'seedKept' => NULL,
		]);

		parent::create($e);

	}

	public static function update(Aid $e, array $properties): void {

		$e['updatedAt'] = \Clock::datetime();
		$properties[] = 'updatedAt';

		parent::update($e, $properties);

	}

	public static function publish(Aid $e): void {

		$e['status'] = Aid::PUBLISHED;
		$e['publishedAt'] = \Clock::datetime();

		self::update($e, ['status', 'publishedAt']);

	}

	// Comme une aide jamais publiée : sans date de publication.
	public static function unpublish(Aid $e): void {

		$e['status'] = Aid::DRAFT;
		$e['publishedAt'] = NULL;

		self::update($e, ['status', 'publishedAt']);

	}

	// La relecture dit qui a relu et quand, jamais la confiance : une aide sans source officielle ne
	// devient pas vérifiée parce qu'on l'a relue. La confiance vient du semis, ou de confirm().
	public static function check(Aid $e): void {

		$e['checkedAt'] = \Clock::date();
		$e['checkedBy'] = \user\ConnectionLib::getOnline();

		self::update($e, ['checkedAt', 'checkedBy']);

	}

	// La relecture de la liste des pièces, seule à la dater : ni une pièce ajoutée ou retouchée ni une
	// liste posée par un agent ne valent relecture, comme pour checkedBy.
	public static function checkPieces(Aid $e): void {

		$e['piecesCheckedAt'] = \Clock::date();

		self::update($e, ['piecesCheckedAt']);

	}

	// La confiance et la relecture qui la fonde s'écrivent ensemble : une aide confirmée a toujours son
	// relecteur, que l'outil nomme (Aid::isVerifiedByTeam()).
	public static function confirm(Aid $e): void {

		$e['verification'] = Aid::PRIMARY;
		$e['checkedAt'] = \Clock::date();
		$e['checkedBy'] = \user\ConnectionLib::getOnline();

		self::update($e, ['verification', 'checkedAt', 'checkedBy']);

	}

	/**
	 * Ce que « Reprendre la fiche » construit d'un écart : ses propriétés que l'équipe peut écrire, et la
	 * case du taux non publié quand l'écart porte le taux, pour que l'étape « publiée ⇒ taux ou case »
	 * juge le taux repris (Aid::properties()). La case se construit après le taux, que son étape lit.
	 */
	public static function getPropertiesFromSeed(array $differences): array {

		$properties = array_values(array_intersect(self::getPropertiesSeed(), array_keys($differences['properties'])));

		if(in_array('rate', $properties, TRUE)) {
			$properties[] = 'rateUnpublished';
		}

		return $properties;

	}

	// Un taux repris dément la case ; sans taux, la case reste celle de l'équipe.
	public static function getInputFromSeed(Aid $e, array $differences): array {

		$e->expects(['rateUnpublished']);

		$input = $differences['properties'];

		if(array_key_exists('rate', $input)) {
			$input['rateUnpublished'] = ($input['rate'] === NULL) ? $e['rateUnpublished'] : FALSE;
		}

		return $input;

	}

	/**
	 * L'empreinte d'un écart sur sa forme canonique, clés et ensembles triés : deux reprises qui
	 * rapportent le même écart dans un autre ordre ont la même. La reprise la compare à `seedKept`
	 * (ImportLib::seedAid()), keepSeed() l'y pose. La clé `held` n'y entre que vraie : un écart gardé
	 * avant qu'elle existe garde ainsi la même empreinte et ne revient pas.
	 */
	public static function getSeedFingerprint(array $differences): string {

		if($differences['held'] === FALSE) {
			unset($differences['held']);
		}

		return hash('sha256', json_encode(self::sortSeed($differences)));

	}

	private static function sortSeed(array $value): array {

		$value = array_map(fn($item) => is_array($item) ? self::sortSeed($item) : $item, $value);

		if(array_is_list($value)) {
			usort($value, fn($a, $b) => strcmp(json_encode($a), json_encode($b)));
		} else {
			ksort($value);
		}

		return $value;

	}

	/**
	 * L'équipe reprend ce que la fiche dit, propriétés construites par la page sur l'écart en base
	 * (getPropertiesFromSeed()). Les départements, sources, organismes, tags et éditions de l'écart se
	 * reprennent à la main sur la fiche : chacun a ses gardes, que ce geste ne double pas.
	 * `$fingerprint` est l'empreinte de l'écart que l'équipe a lu (acceptSeedLocked()).
	 */
	public static function updateFromSeed(Aid $e, array $differences, string $fingerprint): void {

		Aid::model()->beginTransaction();

			if(self::acceptSeedLocked($e, $differences, $fingerprint) === FALSE) {
				Aid::model()->commit();
				Aid::fail('seedDifferences.changed');
				return;
			}

			$e['seedDifferences'] = NULL;

			self::update($e, array_merge(self::getPropertiesFromSeed($differences), ['seedDifferences']));

		Aid::model()->commit();

	}

	// L'équipe garde son aide : la reprise suivante qui rapporte le même écart ne le réécrit pas.
	public static function keepSeed(Aid $e, array $differences, string $fingerprint): void {

		Aid::model()->beginTransaction();

			if(self::acceptSeedLocked($e, $differences, $fingerprint) === FALSE) {
				Aid::model()->commit();
				Aid::fail('seedDifferences.changed');
				return;
			}

			$e['seedDifferences'] = NULL;
			$e['seedKept'] = self::getSeedFingerprint($differences);

			self::update($e, ['seedDifferences', 'seedKept']);

		Aid::model()->commit();

	}

	/**
	 * Aid::acceptSeed() reposée dans la transaction qui écrit, l'aide verrouillée : une reprise jouée
	 * entre l'affichage et le geste a pu changer l'écart, et l'équipe trancherait sur ce qu'elle n'a
	 * pas lu. Trois écarts doivent être le même : celui que l'écran a montré, dont le geste poste
	 * l'empreinte, celui que la page a lu au clic pour construire, et celui de la ligne verrouillée.
	 */
	private static function acceptSeedLocked(Aid $e, array $differences, string $fingerprint): bool {

		$eAid = new Aid();

		Aid::model()
			->select('seedDifferences')
			->whereId($e)
			->option('for-update')
			->get($eAid);

		return (
			$eAid->notEmpty() and
			$eAid['seedDifferences'] !== NULL and
			self::getSeedFingerprint($eAid['seedDifferences']) === $fingerprint and
			self::getSeedFingerprint($differences) === $fingerprint
		);

	}

	public static function archive(Aid $e): void {

		$e['status'] = Aid::ARCHIVED;

		self::update($e, ['status']);

	}

}
?>
