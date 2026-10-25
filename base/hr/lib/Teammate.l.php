<?php
namespace hr;

class TeammateLib extends TeammateCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['user'];
	}

	/**
	 * `$lock` relit la ligne sous verrou, pour la transaction qui en dépend (`structure\MemberLib::deleteInternal()`) :
	 * `get()` et non `exists()`, qui écarte les options de la requête et donc le `for-update`.
	 */
	public static function isTeammate(\structure\Structure $eStructure, \user\User $eUser, bool $lock = FALSE): bool {

		if($lock) {
			Teammate::model()->option('for-update');
		}

		return Teammate::model()
			->select('id')
			->whereStructure($eStructure)
			->whereUser($eUser)
			->get()
			->notEmpty();

	}

	public static function getByStructure(\structure\Structure $eStructure): \Collection {

		$selectPresence = Presence::getSelection();

		// RH éteinte, rien de la RH ne se lit, et l'écran de l'équipe n'a pas de colonne Contrat.
		if($eStructure->acceptHr()) {
			$selectPresence['eContract'] = Contract::model()
				->select(Contract::getSelection())
				->delegateElement('presence');
		}

		return Teammate::model()
			->select(Teammate::getSelection() + [
				'cPresence' => Presence::model()
					->select($selectPresence)
					->whereStructure($eStructure)
					->sort([
						'from' => SORT_DESC
					])
					->delegateCollection('user', propertyParent: 'user')
			])
			->whereStructure($eStructure)
			->getCollection(index: 'user')
			->sort([
				'user' => ['firstName' => SORT_ASC, 'lastName' => SORT_ASC]
			], natural: TRUE);

	}

	/**
	 * Les personnes de l'équipe, sans leurs périodes ni leurs contrats, rangées par id.
	 */
	public static function getUsersByStructure(\structure\Structure $eStructure): \Collection {

		return Teammate::model()
			->select([
				'user' => Teammate::getSelection()['user']
			])
			->whereStructure($eStructure)
			->getCollection()
			->getColumnCollection('user', 'user')
			->sort([
				'firstName' => SORT_ASC,
				'lastName' => SORT_ASC
			]);

	}

	/**
	 * Les personnes que le panneau peut mettre au planning, depuis leur ligne « Hors planning » ou leur fiche :
	 * celles qui ont un accès et les personnes sans compte, tant qu'elles ne sont pas dans l'équipe.
	 */
	public static function getCandidates(\structure\Structure $eStructure): \Collection {

		$users = Teammate::model()
			->whereStructure($eStructure)
			->getColumn('user')
			->getIds();

		return \structure\MemberLib::getUsersByStructure($eStructure)
			->appendCollection(\structure\MemberLib::getGhostByStructure($eStructure)->getColumnCollection('user', 'user'))
			->find(fn(\user\User $eUser) => in_array($eUser['id'], $users) === FALSE)
			->sort([
				'firstName' => SORT_ASC,
				'lastName' => SORT_ASC
			]);

	}

	public static function getByUser(\structure\Structure $eStructure, \user\User $eUser): Teammate {

		return Teammate::model()
			->select(Teammate::getSelection())
			->whereStructure($eStructure)
			->whereUser($eUser)
			->get();

	}

	/**
	 * Les personnes de l'équipe présentes au moins un jour entre `$start` et `$stop`. Toute présence a son
	 * coéquipier (`Presence::user.teammate`), donc la règle de période reste celle de `PresenceLib::getBetween()`.
	 */
	public static function getForPeriod(\structure\Structure $eStructure, string $start, string $stop): \Collection {

		return PresenceLib::getBetween($eStructure, $start, $stop)->getColumnCollection('user', 'user');

	}

	/**
	 * Les personnes que le planning propose : l'équipe d'aujourd'hui et celle qui va arriver, plus ceux qui ont un
	 * accès à la structure, qui y saisissent leur temps sans être dans l'équipe. Une personne partie n'y revient
	 * pas ; une personne sans compte n'y entre que par sa période.
	 */
	public static function getUsersForPlanning(\structure\Structure $eStructure, bool $withPresenceAbsence = FALSE): \Collection {

		$cUser = \structure\MemberLib::getUsersByStructure($eStructure)
			->appendCollection(PresenceLib::getBetween($eStructure, \Clock::date(), NULL)->getColumnCollection('user', 'user'))
			->sort([
				'firstName' => SORT_ASC,
				'lastName' => SORT_ASC
			]);

		if($withPresenceAbsence) {
			PresenceLib::fillUsers($eStructure, $cUser);
			AbsenceLib::fillUsers($eStructure, $cUser);
		}

		return $cUser;

	}

	/**
	 * Les personnes pour qui le gérant saisit une absence : celles dont une période court encore ou a fini depuis
	 * `Absence::PRESENCE_MONTHS` mois au plus. C'est la population que l'étape `present` de `Absence::user`
	 * accepte.
	 */
	public static function getUsersForAbsence(\structure\Structure $eStructure): \Collection {

		// La règle de période de `PresenceLib::getBetween()`, sans le statut du compte que l'étape `present` ne lit
		// pas, et la sélection de l'équipe, qui met `name` en premier pour le libellé du menu déroulant.
		return Presence::model()
			->select([
				'user' => Teammate::getSelection()['user']
			])
			->whereStructure($eStructure)
			->or(
				fn() => $this->whereTo('>=', Absence::getPresenceSince()),
				fn() => $this->whereTo(NULL)
			)
			->getCollection()
			->getColumnCollection('user', 'user')
			->sort([
				'firstName' => SORT_ASC,
				'lastName' => SORT_ASC
			]);

	}

	/**
	 * Range l'équipe pour l'année `$year`. L'année en cours sépare ceux qui sont là ou vont arriver, ceux
	 * partis dans l'année et ceux partis avant ; une année passée sépare ceux qui y ont travaillé de ceux
	 * partis avant, et laisse de côté ceux arrivés après.
	 *
	 * Chaque personne reçoit la période dont la ligne donne les dates (`ePresenceShown`), ses périodes prévues
	 * après celle-ci, de la plus proche à la plus lointaine (`cPresenceComing`, vide hors de l'année en
	 * cours), le nombre de ses périodes plus anciennes (`periodsBefore`) et ses périodes bornées à l'année
	 * (`ranges`), en jours comptés depuis le 1er janvier, avec l'identifiant de chacune dans le même ordre
	 * (`rangesPresence`), que la frise ouvre en modification. Les présents et les partis de l'année se rangent par ancienneté, comme la
	 * planche ; les partis avant restent par nom.
	 */
	public static function group(\Collection $cTeammate, int $year): array {

		$today = \Clock::date();
		$isCurrent = ($year === \Clock::year());

		$yearStart = $year.'-01-01';
		$yearStop = $year.'-12-31';

		$groups = [
			'now' => new \Collection(),
			'year' => new \Collection(),
			'before' => new \Collection()
		];

		foreach($cTeammate as $eTeammate) {

			$cPresence = $eTeammate['cPresence'];
			$cPresenceComing = new \Collection();

			$cPresenceYear = $cPresence->find(fn(Presence $ePresence) => (
				$ePresence['from'] <= $yearStop and
				$ePresence->isOpenOn($yearStart)
			));

			if($isCurrent) {

				$cPresenceOpen = $cPresence->find(fn(Presence $ePresence) => $ePresence->isOpenOn($today));

				if($cPresenceOpen->notEmpty()) {
					$group = 'now';
					// Les périodes viennent de la plus récente à la plus ancienne : la dernière qui n'est pas finie
					// est celle en cours, sinon la prochaine.
					$ePresenceShown = $cPresenceOpen->last();
					// Les périodes prévues après celle de la ligne s'écrivent sous la barre, de la plus proche à la plus lointaine.
					$cPresenceComing = $cPresenceOpen->find(fn(Presence $ePresence) => $ePresence['from'] > $ePresenceShown['from'])->reverse();
				} else if($cPresenceYear->notEmpty()) {
					$group = 'year';
					$ePresenceShown = $cPresenceYear->first();
				} else if($cPresence->notEmpty()) {
					$group = 'before';
					$ePresenceShown = $cPresence->first();
				} else {
					// Une personne dont on a effacé toutes les périodes n'est plus là, sans date de départ.
					$group = 'before';
					$ePresenceShown = new Presence();
				}

			} else {

				if($cPresenceYear->notEmpty()) {
					$group = 'year';
					$ePresenceShown = $cPresenceYear->first();
				} else if($cPresence->find(fn(Presence $ePresence) => $ePresence['from'] < $yearStart)->notEmpty()) {
					$group = 'before';
					$ePresenceShown = $cPresence->first();
				} else {
					continue;
				}

			}

			$eTeammate['ePresenceShown'] = $ePresenceShown;
			$eTeammate['cPresenceComing'] = $cPresenceComing;
			$eTeammate['periodsBefore'] = $ePresenceShown->empty() ? 0 : $cPresence->find(fn(Presence $ePresence) => $ePresence['from'] < $ePresenceShown['from'])->count();
			$eTeammate['ranges'] = [];
			$eTeammate['rangesPresence'] = [];

			foreach($cPresenceYear as $ePresence) {

				$from = max($ePresence['from'], $yearStart);
				$to = min($ePresence['to'] ?? $yearStop, $yearStop);

				$eTeammate['ranges'][] = [
					self::getDayOfYear($from),
					self::getDayOfYear($to) + 1
				];

				$eTeammate['rangesPresence'][] = $ePresence['id'];

			}

			$groups[$group][$eTeammate['id']] = $eTeammate;

		}

		$groups['now']->sort(['ePresenceShown' => ['from' => SORT_ASC]]);
		$groups['year']->sort(['ePresenceShown' => ['from' => SORT_ASC]]);

		return [
			'groups' => $groups,
			'days' => self::getDayOfYear($yearStop) + 1,
			'today' => $isCurrent ? self::getDayOfYear($today) : NULL,
			'isCurrent' => $isCurrent
		];

	}

	/**
	 * Pose sur chaque personne de `group()` les absences validées de chacune de ses périodes (`segments`), dans
	 * l'ordre de `ranges` : pour chaque période, la liste des bandes `[début, fin]` en jours comptés depuis le
	 * 1er janvier, bornées à la période, vide quand la période n'a pas d'absence. La frise pose ces bandes
	 * par-dessus la période, qui reste d'un seul tenant (décidé le 17/10). `cAbsence` vient de
	 * `AbsenceLib::fillUsers()` borné à l'année.
	 *
	 * Une absence garde un pour cent de l'année, comme une période d'un jour (`TeammateUi::getRail()`), et deux
	 * absences que sépare moins d'un pour cent ne font qu'une bande : une structure qui saisit ses jours fériés
	 * en absences rendrait sinon une frise en code-barres.
	 *
	 * Chaque absence garde aussi son étendue propre (`segmentsAbsence`, `[id, début, fin]` dans l'ordre de
	 * `ranges`), pour que la frise ouvre sa modification au clic (décidé le 20/10) : dans une bande
	 * qui en réunit plusieurs, chacune reste un lien vers elle-même et non vers la première.
	 */
	public static function fillSegments(array $team, int $year): void {

		$minimum = $team['days'] / 100;

		$yearStart = $year.'-01-01';
		$yearStop = $year.'-12-31';

		foreach($team['groups'] as $cTeammate) {

			foreach($cTeammate as $eTeammate) {

				$absences = [];

				foreach($eTeammate['user']['cAbsence'] as $eAbsence) {

					$start = self::getDayOfYear(max(substr($eAbsence['from'], 0, 10), $yearStart));
					$stop = self::getDayOfYear(min(substr($eAbsence['to'], 0, 10), $yearStop)) + 1;

					$absences[] = [$start, max($stop, $start + $minimum), $eAbsence['id']];

				}

				sort($absences);

				$segments = [];
				$segmentsAbsence = [];

				foreach($eTeammate['ranges'] as [$rangeStart, $rangeStop]) {

					$bands = [];
					$links = [];

					foreach($absences as [$start, $stop, $absenceId]) {

						$start = max($start, $rangeStart);
						$stop = min($stop, $rangeStop);

						if($stop <= $start) {
							continue;
						}

						$links[] = [$absenceId, $start, $stop];

						if($bands !== [] and $start - $bands[array_key_last($bands)][1] < $minimum) {
							$bands[array_key_last($bands)][1] = max($bands[array_key_last($bands)][1], $stop);
						} else {
							$bands[] = [$start, $stop];
						}

					}

					$segments[] = $bands;
					$segmentsAbsence[] = $links;

				}

				$eTeammate['segments'] = $segments;
				$eTeammate['segmentsAbsence'] = $segmentsAbsence;

			}

		}

	}

	private static function getDayOfYear(string $date): int {
		return (int)date('z', strtotime($date));
	}

	public static function create(Teammate $e): void {

		$e->expects(['structure', 'user']);

		$e->add([
			'ePresence' => new Presence()
		]);

		Teammate::model()->beginTransaction();

			if($e['user']->exists() === FALSE) {
				\structure\MemberLib::createGhostUser($e['structure'], $e['user']);
			}

			parent::create($e);

			if($e['ePresence']->notEmpty()) {

				$e['ePresence']['structure'] = $e['structure'];
				$e['ePresence']['user'] = $e['user'];

				PresenceLib::create($e['ePresence']);

			}

		Teammate::model()->commit();

	}

	/**
	 * Le jour du geste n'est plus une présence : la période en cours finit la veille, sans quoi le récapitulatif en
	 * ferait dès le lendemain un jour à saisir (`Presence::isDateMissing()`), et une période qui commence aujourd'hui ou
	 * plus tard s'efface, aucune veille ne pouvant la terminer.
	 *
	 * @return array{0: \Collection, 1: \Collection} les périodes qui finissent la veille, puis celles qui s'effacent
	 */
	private static function splitLeave(\Collection $cPresence): array {

		$today = \Clock::date();

		return [
			$cPresence->find(fn(Presence $ePresence) => (
				$ePresence['from'] < $today and
				$ePresence->isOpenOn($today)
			)),
			$cPresence->find(fn(Presence $ePresence) => $ePresence['from'] >= $today)
		];

	}

	/**
	 * Sort la personne du planning (`splitLeave()`). Ses périodes passées restent, et sans aucune autre elle sort de
	 * l'équipe, son accès à la structure intact.
	 *
	 * Les périodes se lisent sous verrou dans la transaction, et la fin s'y construit : une période créée ou
	 * modifiée depuis l'affichage du bouton serait sinon laissée au planning, prolongée jusqu'à la veille, ou
	 * effacée avec la personne.
	 */
	public static function leave(Teammate $e): void {

		$e->expects(['structure', 'user']);

		// Ce FailWatch ne compte que les refus de la fin construite ici : la transaction n'écrit rien s'il y en a, et la
		// page les rend par le sien.
		$fw = new \FailWatch();

		Teammate::model()->beginTransaction();

			$cPresence = Presence::model()
				->select(['id', 'structure', 'user', 'from', 'to'])
				->whereStructure($e['structure'])
				->whereUser($e['user'])
				->option('for-update')
				->getCollection();

			[$cPresenceEnded, $cPresenceCanceled] = self::splitLeave($cPresence);

			$to = date('Y-m-d', \Clock::shift('- 1 DAY'));

			foreach($cPresenceEnded as $ePresence) {
				$ePresence->build(['to'], ['to' => $to], new \PropertyList('update'));
			}

			[$cAbsenceEnded, $cAbsenceCanceled] = self::splitLeaveAbsences($e, $to);

			if($fw->ok()) {

				foreach($cPresenceEnded as $ePresence) {
					PresenceLib::update($ePresence, ['to']);
				}

				foreach($cPresenceCanceled as $ePresence) {
					PresenceLib::delete($ePresence);
				}

				foreach($cAbsenceEnded as $eAbsence) {
					AbsenceLib::update($eAbsence, ['to', 'duration']);
				}

				foreach($cAbsenceCanceled as $eAbsence) {
					AbsenceLib::delete($eAbsence);
				}

				if($cPresence->count() === $cPresenceCanceled->count()) {
					self::delete($e);
				}

			}

		Teammate::model()->commit();

	}

	/**
	 * Les absences de la personne qui sort du planning, lues sous verrou dans la transaction de `leave()`, comme ses
	 * périodes (décidé le 22/10) : celles d'aujourd'hui ou à venir sont effacées, celles en cours
	 * finissent la veille, `$to`, avec la durée ramenée aux jours qu'elles prennent jusque-là. Une absence à l'heure
	 * tient dans une journée : commencée avant aujourd'hui, elle est déjà finie. Rend les absences à finir, bâties,
	 * puis celles à effacer.
	 */
	private static function splitLeaveAbsences(Teammate $e, string $to): array {

		$today = \Clock::date();

		$cAbsence = Absence::model()
			->select(['id', 'structure', 'user', 'from', 'to', 'duration', 'unit'])
			->whereStructure($e['structure'])
			->whereUser($e['user'])
			->whereTo('>=', $today.' '.AbsenceLib::DAY_START)
			->option('for-update')
			->getCollection();

		$cAbsenceEnded = new \Collection();
		$cAbsenceCanceled = new \Collection();

		foreach($cAbsence as $eAbsence) {

			if($eAbsence['from'] >= $today.' '.AbsenceLib::DAY_START) {
				$cAbsenceCanceled[] = $eAbsence;
				continue;
			}

			$start = substr($eAbsence['from'], 0, 10);

			// Effacée plutôt que bornée quand la personne n'est pas au planning de son début à la veille, ce que
			// `build()` refuserait (`Absence::to.outOfPresence`) : une absence hors présence ne compte pour rien
			// (`AbsenceLib::getDaysBetween()`), et une donnée qui l'a laissée passer ne doit pas bloquer la sortie
			// (décidé le 22/10).
			if(PresenceLib::isPresentThroughout($e['structure'], $e['user'], $start, $to) === FALSE) {
				$cAbsenceCanceled[] = $eAbsence;
				continue;
			}

			$eAbsenceEnded = clone $eAbsence;
			$eAbsenceEnded['to'] = $to.' '.AbsenceLib::DAY_END;

			$duration = min($eAbsence['duration'], AbsenceLib::getDaysBetween($eAbsenceEnded, $start, $to));

			if($duration <= 0) {
				$cAbsenceCanceled[] = $eAbsence;
				continue;
			}

			$eAbsence->build(['to', 'duration'], [
				'to' => $to.' '.AbsenceLib::DAY_END,
				'duration' => $duration
			], new \PropertyList('update'));

			$cAbsenceEnded[] = $eAbsence;

		}

		return [$cAbsenceEnded, $cAbsenceCanceled];

	}

	/**
	 * Sortir de l'équipe efface ses périodes, puis l'adhésion gardée de la personne à qui l'on avait retiré tout
	 * rôle (`structure\MemberLib::deleteInternal()`) : sans équipe, plus aucun écran ne la montrerait.
	 */
	public static function delete(Teammate $e): void {

		$e->expects(['structure', 'user']);

		Teammate::model()->beginTransaction();

			$cPresence = Presence::model()
				->select(['id'])
				->whereStructure($e['structure'])
				->whereUser($e['user'])
				->getCollection();

			foreach($cPresence as $ePresence) {
				PresenceLib::delete($ePresence);
			}

			// La ligne d'équipe avant l'adhésion, dans l'ordre où `structure\MemberLib::deleteInternal()` les verrouille :
			// un retrait de rôle et une sortie simultanés ne s'attendent pas l'un l'autre.
			parent::delete($e);

			\structure\MemberLib::deleteRevoked($e['structure'], $e['user']);

		Teammate::model()->commit();

	}

	/**
	 * Le compte a-t-il déjà, dans la structure, une fiche d'équipe, une période ou une journée de temps de
	 * travail : la fiche est unique par personne, et la journée aussi. Le verrou ne vaut que dans la
	 * transaction qui transfère (`structure\MemberLib::transferGhost()`) ; à la création de l'invitation,
	 * hors transaction, la lecture ne fait que prévenir. `get()` et non `exists()`, qui écarte les options de
	 * la requête et donc le `for-update` (`exists()`, `framework/core/Module.php`).
	 */
	public static function hasHistory(\structure\Structure $eStructure, \user\User $eUser): bool {

		return (
			Teammate::model()
				->select('id')
				->whereStructure($eStructure)
				->whereUser($eUser)
				->option('for-update')
				->get()
				->notEmpty() or
			Presence::model()
				->select('id')
				->whereStructure($eStructure)
				->whereUser($eUser)
				->option('for-update')
				->get()
				->notEmpty() or
			WorkingTime::model()
				->select('id')
				->whereStructure($eStructure)
				->whereUser($eUser)
				->option('for-update')
				->get()
				->notEmpty()
		);

	}

	/**
	 * La fiche d'équipe d'une personne sans compte passe au compte qui la remplace
	 * (`structure\MemberLib::transferGhost()`), qui n'en a pas (`hasHistory()`).
	 */
	public static function transferUser(\structure\Structure $eStructure, \user\User $eUserFrom, \user\User $eUserTo): void {

		// Une ligne au plus, par l'unicité de (structure, user), mais lue par la personne et non par son
		// identifiant : l'écriture garde la forme de ses voisines de `transferGhost()`.
		Teammate::model()
			->whereStructure($eStructure)
			->whereUser($eUserFrom)
			->update([
				'user' => $eUserTo
			]);

	}

}
?>
