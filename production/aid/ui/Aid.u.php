<?php
namespace aid;

/**
 * L'outil « Aides agricoles » de la Boîte à outils (planche subventions-l3-planche-b, piste B,
 * variante 1, retenue le 22/10) : une recherche en jetons posée d'office sur le profil de la
 * structure, onze projets à l'accueil, puis la liste d'un projet avec le rail de ce qu'il finance,
 * et la fiche d'une aide.
 */
class AidUi {

	public function __construct() {
		\Asset::css('aid', 'aid.css');
		\Asset::js('aid', 'aid.js');
	}

	public static function url(\structure\Structure $eStructure, array $filters = [], array $changes = []): string {

		$url = \toolbox\ToolUi::url($eStructure).'/aides';

		// Changer un critère change la liste, qui repart de sa première page.
		$query = self::getQuery($filters, ($changes === []) ? [] : $changes + ['page' => NULL]);

		return ($query === []) ? $url : $url.'?'.http_build_query($query);

	}

	// L'accueil de l'outil, qui garde le lieu, les productions et le bio de la liste quittée.
	public static function urlHome(\structure\Structure $eStructure, array $filters): string {
		return self::url($eStructure, $filters, ['project' => NULL, 'tag' => NULL, 'all' => NULL]);
	}

	// L'accueil montre les projets ; un projet, un tag ou « toutes les aides » ouvre une liste.
	public static function isHome(array $filters): bool {
		return ($filters['project'] === NULL and $filters['tag']->empty() and $filters['all'] === FALSE);
	}

	public static function urlSheet(\structure\Structure $eStructure, Aid $eAid, array $filters): string {

		$query = self::getQuery($filters, []);

		return \toolbox\ToolUi::url($eStructure).'/aides/'.$eAid['id'].(($query === []) ? '' : '?'.http_build_query($query));

	}

	/**
	 * Les critères tels que l'adresse les porte. Le lieu, les productions et le bio s'y écrivent
	 * toujours, vides quand on les a retirés : un lien copié montre la même liste à une structure qui
	 * n'a pas le même profil. La fiche les garde tous, pour que le retour mène à la liste quittée.
	 */
	protected static function getQuery(array $filters, array $changes): array {

		$values = ($filters === []) ? [] : [
			'department' => $filters['department'] ?? '',
			'productions' => implode(',', $filters['productions']),
			'organic' => $filters['organic'] ? '1' : '0',
			'project' => $filters['project'],
			'tag' => $filters['tag']->empty() ? NULL : $filters['tag']['fqn'],
			'all' => $filters['all'] ? '1' : NULL,
			'page' => ($filters['page'] > 0) ? $filters['page'] : NULL,
		];

		return array_filter($changes + $values, fn($value) => $value !== NULL);

	}

	public function getHeader(\structure\Structure $eStructure, \Collection $cFavorite, array $filters): string {

		return new \toolbox\ToolUi()->getHeader(
			$eStructure,
			\toolbox\Favorite::AID,
			$cFavorite,
			menu: '',
			back: self::isHome($filters) ? NULL : self::urlHome($eStructure, $filters),
			season: $this->getTerritory($eStructure, $filters)
		);

	}

	/**
	 * Le territoire borne tout l'écran : il se choisit à côté du titre, comme la saison d'un plan de
	 * culture, et non dans la recherche. Le menu propose le département de la structure et toute la
	 * France, puis le département posé quand c'est un autre, et un champ pour en choisir un autre.
	 */
	protected function getTerritory(\structure\Structure $eStructure, array $filters): string {

		$home = $filters['profile']['department'];
		$department = $filters['department'];

		$items = [];

		if($home !== NULL) {
			$items[] = [
				'label' => self::getDepartment($home),
				'url' => self::url($eStructure, $filters, ['department' => $home]),
				'selected' => ($department === $home),
			];
		}

		$items[] = [
			'label' => s("Toute la France"),
			'url' => self::url($eStructure, $filters, ['department' => '']),
			'selected' => ($department === NULL),
		];

		if($department !== NULL and $department !== $home) {
			$items[] = [
				'label' => self::getDepartment($department),
				'url' => self::url($eStructure, $filters, ['department' => $department]),
				'selected' => TRUE,
			];
		}

		$label = ($department === NULL) ? s("Toute la France") : AidSetting::DEPARTMENTS[$department][0];

		return new \main\DesignUi()->getSeason($label, $items, $this->getDepartmentField($eStructure, $filters), s("Changer de territoire"));

	}

	/**
	 * « Un autre département », par l'autocomplétion qui choisit une commune (`\main\PlaceUi::queryCity()`).
	 * Le choix mène à la liste dans ce département (aid.js), et `data-dropdown-keep` garde le menu ouvert
	 * quand on clique dans le champ. La liste prend la largeur du groupe, icône comprise, pour qu'un
	 * département et son numéro tiennent sur une ligne (« Alpes-de-Haute-Provence (04) »).
	 */
	protected function getDepartmentField(\structure\Structure $eStructure, array $filters): string {

		$form = new \util\FormUi();

		[
			'query' => $query,
			'results' => $results,
		] = $form->autocomplete('aidDepartment', '/aid/aid:departments', ['id' => $eStructure['id']] + self::getQuery($filters, []), NULL, [], FALSE, FALSE, [
			'placeholder' => s("Un autre département"),
			'data-autocomplete-width' => 'group',
		]);

		return '<div class="dropdown-text" data-dropdown-keep>'.$form->inputGroup($form->addon(\Asset::icon('geo-fill')).$query).$results.'</div>';

	}

	// Un département avec son numéro, comme on le cherche : « Meuse (55) ».
	public static function getDepartment(string $department): string {
		return AidSetting::DEPARTMENTS[$department][0].' ('.$department.')';
	}

	/**
	 * Les résultats de « Un autre département », chacun avec l'adresse de la liste dans ce département.
	 */
	public static function getDepartments(\structure\Structure $eStructure, array $filters, array $departments): array {

		return array_map(fn(string $department) => [
			'value' => $department,
			'itemText' => self::getDepartment($department),
			'itemHtml' => encode(self::getDepartment($department)),
			'url' => self::url($eStructure, $filters, ['department' => $department]),
		], $departments);

	}

	/**
	 * L'accueil quand ni projet, ni tag, ni « toutes les aides » n'est posé, la liste sinon.
	 * `$projects` compte les aides de chaque projet, `$cTag` porte les tags du projet posé avec leur
	 * nombre, `$cAidShown` les aides de la page parmi celles que laissent le projet et le tag, `$count`
	 * le nombre de ces dernières sur toutes les pages, `$pages` le nombre de pages, et `$countFrance`
	 * le nombre qu'on trouverait sans le département quand il ne laisse rien.
	 */
	public function get(\structure\Structure $eStructure, array $filters, \Collection $cAid, array $projects, \Collection $cTag, \Collection $cAidShown, int $count, int $pages, ?int $countFrance): string {

		$h = '';

		if($filters['placeMissing']) {
			$h .= $this->getPlaceMissing($eStructure, $filters['profile']['isPlaced']);
		}

		$h .= '<div class="util-chips">';
			$h .= $this->getSearch($eStructure, $filters);
			$h .= $this->getOrganicChip($eStructure, $filters);
		$h .= '</div>';

		if(self::isHome($filters)) {
			$h .= $this->getProjects($eStructure, $filters, $cAid, $projects);
		} else {
			$h .= $this->getList($eStructure, $filters, $cTag, $cAidShown, $count, $pages, $countFrance);
		}

		return $h;

	}

	/**
	 * Sans département, la liste couvre toute la France : la carte le dit, au-dessus de la recherche,
	 * jusqu'à ce qu'un département soit choisi dans l'outil. Un repère posé dont le département reste
	 * inconnu est souvent hors de toute commune (en mer, de l'autre côté d'une frontière), où aucune
	 * reprise ne le trouvera : la carte demande de le déplacer plutôt que de promettre qu'il viendra.
	 * Le lien vers le panneau « Lieu de production » va à qui gère la structure, seul à pouvoir
	 * l'écrire, et la carte dit aux autres membres à qui le demander.
	 */
	protected function getPlaceMissing(\structure\Structure $eStructure, bool $isPlaced): string {

		$button = $eStructure['canUpdatePlace'] ?
			'<a href="/structure/system:updatePlace?id='.$eStructure['eSystem']['id'].'" class="btn btn-outline-primary">'.($isPlaced ? s("Déplacer le repère") : s("Renseigner le lieu de production")).'</a>' :
			'';

		if($isPlaced) {
			$text = $eStructure['canUpdatePlace'] ?
				s("Si le repère de votre lieu de production est hors de France ou hors de toute commune, déplacez-le pour que la liste montre les aides de votre département.") :
				s("Si le repère du lieu de production est hors de France ou hors de toute commune, une personne qui gère la structure peut le déplacer pour que la liste montre les aides de votre département.");
			$card = \main\DesignUi::getActionCard('info', s("Département pas encore connu"), $text, $button);
		} else if($eStructure['canUpdatePlace']) {
			$card = \main\DesignUi::getActionCard('info', s("Indiquez où vous produisez"), s("La liste montre alors les aides de votre département."), $button);
		} else {
			$card = \main\DesignUi::getActionCard('info', s("Le lieu de production n'est pas renseigné"), s("Une personne qui gère la structure peut l'indiquer pour que la liste montre les aides de votre département."), '');
		}

		return '<div class="util-cards util-cards-fit mb-2">'.$card.'</div>';

	}

	/**
	 * Une barre pour les productions, le projet et ce qui est financé ; le territoire se choisit à côté
	 * du titre (getTerritory()), et l'effacement le garde. Chaque critère posé est un jeton, qui mène à
	 * la page sans lui ; les productions se cumulent, une par jeton.
	 */
	protected function getSearch(\structure\Structure $eStructure, array $filters): string {

		$tokens = [];

		$productions = \structure\ProductionUi::getList();

		foreach($filters['productions'] as $production) {
			$tokens[] = [
				'group' => s("Productions"),
				'label' => $productions[$production],
				'value' => $production,
				'url' => self::url($eStructure, $filters, ['productions' => implode(',', array_diff($filters['productions'], [$production]))]),
			];
		}

		if($filters['project'] !== NULL) {
			$tokens[] = [
				'group' => s("Projets"),
				'label' => TagUi::p('projects')->values[$filters['project']],
				'value' => $filters['project'],
				'url' => self::url($eStructure, $filters, ['project' => NULL, 'tag' => NULL]),
			];
		}

		if($filters['tag']->notEmpty()) {
			$tokens[] = [
				'group' => s("Ce qui est financé"),
				'label' => $filters['tag']['name'],
				'value' => $filters['tag']['fqn'],
				'url' => self::url($eStructure, $filters, ['tag' => NULL]),
			];
		}

		return \main\OmniboxUi::get(
			suggestionsUrl: '/aid/aid:query',
			suggestionsBody: ['id' => $eStructure['id']] + self::getQuery($filters, []),
			tokens: $tokens,
			placeholder: ($tokens === []) ? s("Projet, production, nom de l'aide...") : '',
			clear: self::url($eStructure, $filters, ['productions' => '', 'project' => NULL, 'tag' => NULL]),
			class: 'util-omnibox-lg'
		);

	}

	/**
	 * Le bio est posé d'office pour une structure en bio ou en conversion, comme dans le catalogue
	 * E-Phy (`toolbox\CatalogUi::getOrganicChip()`). Retiré, il écarte les aides réservées au bio, et
	 * jamais celles qu'il ne fait que bonifier.
	 */
	protected function getOrganicChip(\structure\Structure $eStructure, array $filters): string {

		$h = '<a class="util-chip'.($filters['organic'] ? ' is-active is-solid' : '').'" data-dropdown="bottom-start">'.($filters['organic'] ? s("Aides bio comprises") : s("Sans les aides réservées au bio")).'</a>';

		$h .= '<div class="dropdown-list">';
			$h .= '<a href="'.self::url($eStructure, $filters, ['organic' => '1']).'" class="dropdown-item'.($filters['organic'] ? ' selected' : '').'">'.s("Aides bio comprises").'</a>';
			$h .= '<a href="'.self::url($eStructure, $filters, ['organic' => '0']).'" class="dropdown-item'.($filters['organic'] ? '' : ' selected').'">'.s("Sans les aides réservées au bio").'</a>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les onze projets, chacun avec le nombre d'aides que le profil lui laisse, puis toutes les aides.
	 * Un projet sans aide reste à sa place : la grille ne change pas d'ordre d'un profil à l'autre.
	 */
	protected function getProjects(\structure\Structure $eStructure, array $filters, \Collection $cAid, array $projects): string {

		$h = '<h2 class="mt-2">'.s("Projets").'</h2>';

		$h .= '<div class="util-cards util-cards-3">';

			foreach($projects as $project => $count) {

				$h .= '<a href="'.self::url($eStructure, $filters, ['project' => $project]).'" class="util-deck">';
					$h .= '<div class="util-deck-head">';
						$h .= '<div class="util-icon util-icon-round util-icon-production">'.\Asset::icon(TagUi::getProjectIcon($project)).'</div>';
						$h .= '<div class="util-deck-body">';
							$h .= '<div class="util-deck-title">'.TagUi::p('projects')->values[$project].'</div>';
							$h .= '<div class="util-annotation">'.p("{value} aide", "{value} aides", $count).'</div>';
						$h .= '</div>';
						$h .= '<div class="util-deck-arrow">'.\Asset::icon('chevron-right').'</div>';
					$h .= '</div>';
				$h .= '</a>';

			}

		$h .= '</div>';

		$h .= '<div class="util-mores mt-2">';
			$h .= '<a href="'.self::url($eStructure, $filters, ['all' => '1']).'" class="util-more">'.s("Toutes les aides").' <span class="util-counter">'.$cAid->count().'</span></a>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La liste d'un projet sous le rail de ce qu'il finance, ou celle d'un tag choisi dans la recherche,
	 * ou toutes les aides, une page à la fois.
	 */
	protected function getList(\structure\Structure $eStructure, array $filters, \Collection $cTag, \Collection $cAidShown, int $count, int $pages, ?int $countFrance): string {

		// Le nombre suit le titre comme dans les groupes du catalogue E-Phy (Catalog.u.php:212) ; un projet
		// le porte déjà sur chacun des tags de son rail.
		if($filters['project'] !== NULL) {
			$title = TagUi::p('projects')->values[$filters['project']];
		} else if($filters['tag']->notEmpty()) {
			$title = encode($filters['tag']['name']).' <span class="util-counter">'.$count.'</span>';
		} else {
			$title = s("Toutes les aides").' <span class="util-counter">'.$count.'</span>';
		}

		$h = '<h2 class="mt-2">'.$title.'</h2>';

		if($filters['project'] !== NULL and $cTag->notEmpty()) {
			$h .= '<div class="mb-2">'.$this->getRail($eStructure, $filters, $cTag).'</div>';
		}

		if($cAidShown->empty()) {
			return $h.$this->getEmpty($eStructure, $filters, $countFrance);
		}

		$h .= '<div class="util-cards util-cards-1 aid-cards mb-2">';
			foreach($cAidShown as $eAid) {
				$h .= $this->getCard($eStructure, $filters, $eAid, $filters['project'], $filters['tag']);
			}
		$h .= '</div>';

		$h .= \util\TextUi::pagination($filters['page'], $pages);

		return $h;

	}

	/**
	 * « Toutes », puis les tags du projet qui ont au moins une aide pour le profil, du plus fourni au
	 * moins fourni, chacun avec son nombre.
	 */
	protected function getRail(\structure\Structure $eStructure, array $filters, \Collection $cTag): string {

		$h = '<div class="util-filter-options">';

			$h .= '<a href="'.self::url($eStructure, $filters, ['tag' => NULL]).'" class="util-filter-option'.($filters['tag']->empty() ? ' is-selected' : '').'">'.s("Toutes").'</a>';

			foreach($cTag as $eTag) {

				$selected = ($filters['tag']->notEmpty() and $filters['tag']['id'] === $eTag['id']);

				$h .= '<a href="'.self::url($eStructure, $filters, ['tag' => $eTag['fqn']]).'" class="util-filter-option'.($selected ? ' is-selected' : '').'">'.encode($eTag['name']).' <span class="util-counter">'.$eTag['count'].'</span></a>';

			}

		$h .= '</div>';

		return $h;

	}

	// L'adresse d'une aide publiée qui ne s'affiche pas, gardée par une structure (AidLib::getHidden()).
	// « Pour l'instant » ne se dit que d'une aide qui reviendra.
	public function getSheetHidden(\structure\Structure $eStructure, array $filters, Aid $eAid): string {

		$title = match($eAid['hidden']) {
			Aid::HIDDEN_CLOSED => s("Cette aide n'est plus proposée"),
			Aid::HIDDEN_SOURCE_DEAD => s("Cette aide n'est plus proposée pour l'instant, sa page officielle ne répond plus"),
		};

		$h = '<div class="util-block-empty">';
			$h .= '<h3>'.$title.'</h3>';
			$h .= '<div class="util-buttons">';
				$h .= '<a href="'.self::url($eStructure, $filters).'" class="btn btn-primary">'.s("Revenir aux aides").'</a>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Rien pour cette recherche : quand le département en est la cause, le bouton propose les aides de
	 * toute la France, avec leur nombre, et seulement s'il y en a.
	 */
	protected function getEmpty(\structure\Structure $eStructure, array $filters, ?int $countFrance): string {

		$h = '<div class="util-block-empty">';

			$h .= '<h3>'.s("Aucune aide pour cette recherche").'</h3>';

			if($filters['department'] !== NULL) {
				$h .= '<p>'.s("La recherche se limite au département {value}.", encode(self::getDepartment($filters['department']))).'</p>';
			}

			if($countFrance > 0) {
				$h .= '<div class="util-buttons">';
					$h .= '<a href="'.self::url($eStructure, $filters, ['department' => '']).'" class="btn btn-primary">'.p("Voir l'aide en France", "Voir les {value} aides en France", $countFrance).'</a>';
				$h .= '</div>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une aide en carte : à gauche ce qu'elle est (où elle vaut, au-dessus de son nom, puis qui l'accorde
	 * et pour qui, ce qu'elle finance), à droite ce qui en décide. Dans la liste d'un projet (`$project`),
	 * seuls les tags du projet ; dans la liste d'un tag (`$eTagFiltered`), ses cartes ne le redisent pas.
	 * Une aide à faire confirmer le dit en bande, de la teinte de sa rangée sur la fiche.
	 */
	protected function getCard(\structure\Structure $eStructure, array $filters, Aid $eAid, ?int $project, Tag $eTagFiltered): string {

		$h = '<a href="'.self::urlSheet($eStructure, $eAid, $filters).'" class="util-deck util-deck-top'.($eAid['isUnconfirmed'] ? ' util-deck-accent-warning' : '').'">';

			$h .= '<div class="util-deck-head">';

				$h .= '<div class="util-deck-body">';

					$places = $this->getPlaces($eAid);

					if($places !== '') {
						$h .= '<div class="util-deck-kicker">'.\Asset::icon('geo-fill').'<span>'.$places.'</span></div>';
					}

					$h .= '<div class="util-deck-title">'.encode($eAid['name']).$this->getUrgent($eAid).'</div>';
					$h .= '<div class="util-annotation">'.$this->getScope($eAid, $places).'</div>';
					$h .= TagUi::getBadges($eAid['cTag'], $project, $eTagFiltered);

				$h .= '</div>';

				$h .= $this->getAside($eAid);

				$h .= '<div class="util-deck-arrow">'.\Asset::icon('chevron-right').'</div>';

			$h .= '</div>';

			if($eAid['isUnconfirmed']) {
				$h .= '<div class="util-deck-band util-deck-band-state">'.\Asset::icon('exclamation-triangle-fill').' '.s("À faire confirmer par l'organisme").'</div>';
			}

		$h .= '</a>';

		return $h;

	}

	/**
	 * Ce qui décide de l'aide, dans une colonne de même largeur d'une carte à l'autre (`--deck-aside-width`,
	 * aid.css) : ce qu'elle apporte, seul chiffre en gras, puis sa nature, que la forme du chiffre ne
	 * nomme pas, et la clôture. Sans chiffre, la nature reste seule, sans le gras d'un montant.
	 */
	protected function getAside(Aid $eAid): string {

		$value = $this->getFigureValue($eAid);

		$h = '<ul class="util-deck-aside-list">';

			if($value !== NULL) {
				$h .= '<li class="util-deck-aside-total">'.\Asset::icon('cash-coin').' '.$value.'</li>';
			}

			$h .= '<li>'.\Asset::icon('award').' '.self::p('type')->values[$eAid['type']].'</li>';
			$h .= '<li>'.$this->getDeadlineAside($eAid).'</li>';

		$h .= '</ul>';

		return $h;

	}

	/**
	 * La clôture et son icône. Une icône de calendrier ne dit pas à elle seule qu'une date clôt le dépôt,
	 * d'où « Dépôt » devant la date ; un rythme se lit sans aide. Une aide sans démarche n'a rien à
	 * déposer : elle prend l'icône de ce qui se fait tout seul, pas celle d'une échéance. Un dépôt qui
	 * n'ouvre pas encore se date par son ouverture, qu'une clôture seule ferait croire déjà ouvert.
	 */
	protected function getDeadlineAside(Aid $eAid): string {

		if($eAid['isUpcoming']) {
			$eEdition = $eAid['eEditionDeposit'];
			$text = ($eEdition['endDate'] === NULL) ?
				s("Dépôt à partir du {value}", \util\DateUi::numeric($eEdition['startDate'])) :
				s("Dépôt du {start} au {end}", ['start' => \util\DateUi::numeric($eEdition['startDate']), 'end' => \util\DateUi::numeric($eEdition['endDate'])]);
			return \Asset::icon('calendar-event').' '.$text;
		}

		if($eAid['isBetweenCampaigns'] === FALSE and $eAid['endDate'] !== NULL) {
			return \Asset::icon('calendar-event').' '.s("Dépôt jusqu'au {value}", \util\DateUi::numeric($eAid['endDate']));
		}

		$icon = match($eAid['calendar']) {
			Aid::PERMANENT, Aid::CALL, Aid::CAMPAIGN => 'calendar-event',
			Aid::AUTOMATIC => 'magic'
		};

		return \Asset::icon($icon).' '.$this->getDeadline($eAid);

	}

	/**
	 * Ce que l'aide apporte, en entier sur une ligne : le taux ou le montant maximal d'une subvention
	 * quand l'organisme le chiffre, AidLib::fill() les retirant des autres types ; sinon la première
	 * proposition de son taux quand elle tient (`rateShort`), sinon le plafond quand il porte sur l'aide
	 * (`ceilingAid`), dit par an quand il l'est (`boundPeriod`), sinon rien, la nature de l'aide en tenant lieu et la fiche disant le reste.
	 */
	protected function getFigureValue(Aid $eAid): ?string {

		if($eAid['ratePercentMax'] !== NULL) {
			return s("Jusqu'à {value} %", \util\TextUi::number($eAid['ratePercentMax'], NULL));
		} else if($eAid['amountMax'] !== NULL) {
			// Un montant sans unité est en euros, l'unité ne se pose que pour dire une assiette.
			return s("Jusqu'à {value} {unit}", ['value' => \util\TextUi::number($eAid['amountMax'], NULL), 'unit' => self::p('amountUnit')->values[$eAid['amountUnit'] ?? Aid::EURO]]);
		} else if($eAid['rateShort'] !== NULL) {
			return encode($eAid['rateShort']);
		} else if($eAid['ceilingAid'] !== NULL) {

			$value = \util\TextUi::number($eAid['ceilingAid'], NULL);

			if($eAid['boundPeriod'] === NULL) {
				return s("Jusqu'à {value} €", $value);
			} else {
				return s("Jusqu'à {value} € {period}", ['value' => $value, 'period' => self::p('boundPeriod')->values[$eAid['boundPeriod']]]);
			}

		} else {
			return NULL;
		}

	}

	// L'intitulé et la valeur de la figure, que la fiche reprend quand l'aide n'a pas de taux écrit.
	protected function getFigureParts(Aid $eAid): array {

		$type = self::p('type')->values[$eAid['type']];
		$value = $this->getFigureValue($eAid);

		if($value !== NULL) {
			return [$type, $value];
		} else {
			return [s("Type d'aide"), $type];
		}

	}

	/**
	 * Le périmètre en une ligne, tiré des champs de l'aide : l'organisme qui l'accorde, puis pour qui
	 * quand elle ne s'adresse pas à toute structure, qui la demande quand ce n'est pas la structure, les
	 * productions et les labels quand ils la restreignent. Les morceaux se séparent par « · » et non par une
	 * virgule, que le nom d'un organisme peut porter. La ligne de lieu (`$places`) dit déjà où :
	 * l'organisme s'y nomme sans son territoire, sauf quand la carte n'a pas de lieu.
	 */
	protected function getScope(Aid $eAid, string $places): string {

		$organization = ($places === '') ? $eAid['organization']['name'] : self::getInstitution($eAid['organization']['name']);

		$parts = [encode($organization)];

		// La carte ne nomme un public que pour une aide qui lui est réservée : une aide ouverte à toutes
		// les exploitations l'est aussi aux jeunes agriculteurs ou aux propriétaires qu'elle cite en plus.
		if($eAid['beneficiaries'] !== NULL and $eAid['beneficiaries']->value(Aid::FARM) === FALSE) {

			if($eAid['beneficiaries']->value(Aid::YOUNG_FARMER)) {
				$parts[] = s("pour les jeunes agriculteurs");
			} else if($eAid['beneficiaries']->value(Aid::NEW_FARMER)) {
				$parts[] = s("pour les nouveaux installés");
			} else if($eAid['beneficiaries']->value(Aid::LANDOWNER)) {
				$parts[] = s("pour les propriétaires fonciers");
			}

		}

		if($eAid['direct'] === FALSE) {
			$parts[] = $this->getApplicant($eAid);
		}

		if($eAid['productionsCard'] !== []) {

			$productions = \structure\ProductionUi::getList();
			$names = array_map(fn(string $production) => mb_strtolower($productions[$production]), $eAid['productionsCard']);

			$parts[] = ($eAid['productionsMore'] === 0) ?
				s("en {value}", self::getEnumeration($names)) :
				p("en {list} et {value} autre", "en {list} et {value} autres", $eAid['productionsMore'], ['list' => implode(', ', $names)]);

		}

		if($eAid['labels'] !== NULL and $eAid['labels']->values() !== []) {
			$parts[] = self::getScopeLabels($eAid['labels']);
		}

		return implode(' · ', $parts);

	}

	// Une aide indirecte reste affichée : la structure en profite, mais une autre la demande. Le participe
	// s'accorde avec « dossier », écrit dans la mention, et non avec l'organisme que la carte place devant.
	protected function getApplicant(Aid $eAid): string {

		$beneficiaries = ($eAid['beneficiaries'] === NULL) ? [] : $eAid['beneficiaries']->values();

		if(in_array(Aid::CUMA, $beneficiaries)) {
			return s("dossier déposé par une CUMA");
		} else if(in_array(Aid::GROUP, $beneficiaries)) {
			return s("dossier déposé par un groupement");
		} else if(in_array(Aid::COMMUNITY, $beneficiaries)) {
			return s("dossier déposé par une collectivité");
		} else if(in_array(Aid::ASSOCIATION, $beneficiaries)) {
			return s("dossier déposé par une association");
		} else {
			return s("dossier déposé par un intermédiaire");
		}

	}

	// Une clôture datée, sinon le rythme de l'aide : un appel sans date attend sa prochaine session. Une
	// aide annuelle entre deux campagnes dit la dernière, jamais la prochaine, que personne n'a publiée.
	// Un dépôt qui n'ouvre pas encore se date par son ouverture.
	protected function getDeadline(Aid $eAid): string {

		if($eAid['isBetweenCampaigns']) {
			return $this->getCampaignLast($eAid['eEditionLast']);
		}

		if($eAid['isUpcoming']) {
			return self::getUpcoming($eAid['eEditionDeposit']);
		}

		if($eAid['endDate'] !== NULL) {
			return s("Jusqu'au {value}", \util\DateUi::numeric($eAid['endDate']));
		}

		return self::p('calendar')->values[$eAid['calendar']];

	}

	// La fenêtre d'une édition à venir, qui porte toujours sa date d'ouverture (AidLib::applyEditionUpcoming()).
	public static function getUpcoming(Edition $eEdition): string {

		if($eEdition['endDate'] === NULL) {
			return s("À partir du {value}", \util\DateUi::numeric($eEdition['startDate']));
		}

		return s("Du {start} au {end}", ['start' => \util\DateUi::numeric($eEdition['startDate']), 'end' => \util\DateUi::numeric($eEdition['endDate'])]);

	}

	protected function getCampaignLast(Edition $eEdition): string {

		if($eEdition->empty()) {
			return s("Campagne annuelle, dates non publiées");
		}

		if($eEdition['startDate'] !== NULL and $eEdition['endDate'] !== NULL) {
			return s("Dernière campagne du {start} au {end}", ['start' => \util\DateUi::numeric($eEdition['startDate']), 'end' => \util\DateUi::numeric($eEdition['endDate'])]);
		} else if($eEdition['endDate'] !== NULL) {
			return s("Dernière campagne close le {value}", \util\DateUi::numeric($eEdition['endDate']));
		} else if($eEdition['startDate'] !== NULL) {
			return s("Dernière campagne ouverte le {value}", \util\DateUi::numeric($eEdition['startDate']));
		} else {
			return s("Campagne annuelle, dates non publiées");
		}

	}

	// Le badge ne marque qu'une clôture à 24 heures, sur la carte comme sur la fiche.
	protected function getUrgent(Aid $eAid): string {

		return $eAid['endsTomorrow'] ? ' <span class="badge badge-warning">'.s("Se termine demain").'</span>' : '';

	}

	/**
	 * La fiche d'une aide. Les productions et les labels ne s'écrivent que lorsqu'ils la restreignent,
	 * comme sur la carte, et les productions toutes, la fiche étant le détail que la carte abrège. Les
	 * branches sont la seule déclinaison de `util-record` qui marque une ligne en alerte, ici une aide
	 * à faire confirmer par l'organisme.
	 */
	public function getSheet(\structure\Structure $eStructure, array $filters, Aid $eAid): string {

		$h = '<article class="util-record util-record-branches">';

			$h .= '<div class="util-record-main">';

				$h .= '<div class="util-record-head">';
					if($eAid['nextDate'] !== NULL) {
						$h .= \util\DateUi::calendar($eAid['nextDate']);
					}
					$h .= '<div class="util-record-identity">';
						$h .= '<h3 class="util-record-title">'.encode($eAid['name']).$this->getUrgent($eAid).'</h3>';
						$h .= '<div class="util-record-context">'.$this->getFunders($eAid).'</div>';
					$h .= '</div>';
				$h .= '</div>';

				// Le site de dépôt, quand la fiche le donne, devient le geste principal (planche de L6) : la
				// source se lit encore, en second.
				if($eAid['eSourcePrimary']->notEmpty()) {
					$class = $this->isDepositOffered($eAid) ? 'btn-outline-primary' : 'btn-primary';
					$h .= '<div class="util-record-actions">';
						$h .= '<a href="'.encode($eAid['eSourcePrimary']['url']).'" target="_blank" class="btn '.$class.'">'.\Asset::icon('box-arrow-up-right').' '.s("Voir sur {value}", encode(self::getHost($eAid['eSourcePrimary']['url']))).'</a>';
					$h .= '</div>';
				}

			$h .= '</div>';

			$h .= '<ul class="util-record-list">';
				$h .= $this->getSheetItems($eAid);
			$h .= '</ul>';

		$h .= '</article>';

		$h .= $this->getDeposit($eAid);

		if($eAid['cAidComplement']->notEmpty()) {

			$h .= '<h3 class="mt-2">'.s("Se complète par").'</h3>';

			$h .= '<div class="util-cards util-cards-1 aid-cards">';
				foreach($eAid['cAidComplement'] as $eAidComplement) {
					$h .= $this->getCard($eStructure, $filters, $eAidComplement, NULL, new Tag());
				}
			$h .= '</div>';

		}

		if($eAid->isVerifiedByTeam()) {
			$h .= '<p class="util-annotation mt-2">'.s("Vérifiée le {value} par l'équipe de Ouvretaferme.", \util\DateUi::numeric($eAid['checkedAt'])).'</p>';
		}

		return $h;

	}

	protected function hasDepositUrl(Aid $eAid): bool {
		return ($eAid->acceptPieces() and $eAid['depositUrl'] !== NULL);
	}

	// Le site de dépôt ne s'offre qu'une fois le dépôt ouvert : avant, son adresse n'a rien à recevoir.
	protected function isDepositOffered(Aid $eAid): bool {
		return ($this->hasDepositUrl($eAid) and $eAid['isUpcoming'] === FALSE);
	}

	/**
	 * Ce que la structure réunit pour déposer, en étapes dans l'ordre du temps (planche de L6, q1 c) : la
	 * demande d'aide, ses pièces et le site de dépôt, puis ce qu'il faut attendre avant de signer un devis
	 * ou d'acheter, là où le geste fait perdre l'aide, puis la demande de paiement. Une étape sans contenu
	 * saute. Rien quand l'aide n'a ni liste, ni règle, ni site de dépôt (CL-01), ni de dossier à elle
	 * (Aid::acceptPieces()).
	 *
	 * La fiche s'imprime pour réunir le dossier (CL-19) : le papier garde la règle d'achat, que le design
	 * system retire d'une page imprimée avec tout `util-warning`, et écrit en clair les adresses que l'écran
	 * donne en liens et en boutons, qui ne s'impriment pas. Chacun a sa copie pour le papier (aid.css).
	 */
	protected function getDeposit(Aid $eAid): string {

		if($eAid->acceptPieces() === FALSE) {
			return '';
		}

		$cAidPieceApplication = $eAid['cAidPiece']->find(fn(AidPiece $eAidPiece) => $eAidPiece['moment'] === AidPiece::APPLICATION);
		$cAidPiecePayment = $eAid['cAidPiece']->find(fn(AidPiece $eAidPiece) => $eAidPiece['moment'] === AidPiece::PAYMENT);

		// Une liste que l'organisme ne publie pas tient lieu de toutes les pièces : la phrase renvoie à lui,
		// et rien ne la date, faute de liste.
		if($eAid['piecesUnpublished']) {

			$application = '<p>'.s("La liste des pièces n'est pas publiée. Demandez-la à l'organisme, {value}, avant de déposer.", encode($eAid['organization']['name'])).'</p>';
			$payment = '';

		} else {

			// La date de la liste suit sa première partie, loin de la vérification de la fiche, qui la clôt.
			$application = $this->getPieces($cAidPieceApplication);
			$payment = $this->getPieces($cAidPiecePayment);

			if($application !== '') {
				$application .= $this->getPiecesRead($eAid);
			} else if($payment !== '') {
				$payment .= $this->getPiecesRead($eAid);
			}

		}

		if($this->hasDepositUrl($eAid) and $eAid['isUpcoming']) {

			// Celui qui prépare son dossier sait où il déposera, sans lien vers une adresse qui ne reçoit rien
			// encore : le domaine à l'écran, l'adresse entière sur le papier.
			$startDate = \util\DateUi::textual($eAid['eEditionDeposit']['startDate'], \util\DateUi::DATE);

			$upcoming = '<p class="util-print-hide">'.s("Dépôt en ligne à partir du {date}, sur {host}", ['date' => $startDate, 'host' => encode(self::getHost($eAid['depositUrl']))]).'</p>';
			$upcoming .= '<p class="aid-deposit-print util-print-block text-break">'.s("Dépôt en ligne à partir du {date}, sur {url}", ['date' => $startDate, 'url' => encode($eAid['depositUrl'])]).'</p>';

			$application = $upcoming.$application;

		} else if($this->isDepositOffered($eAid)) {

			$application = '<p class="aid-deposit-print util-print-block text-break">'.s("En ligne sur {value}", encode($eAid['depositUrl'])).'</p>'.$application;

			$application .= '<div class="flex-buttons mt-1 util-print-hide">';
				$application .= '<a href="'.encode($eAid['depositUrl']).'" target="_blank" class="btn btn-primary">'.\Asset::icon('box-arrow-up-right').' '.s("Déposer en ligne sur {value}", encode(self::getHost($eAid['depositUrl']))).'</a>';
			$application .= '</div>';

		}

		$steps = [];

		if($application !== '') {
			$steps[] = [s("Déposer la demande d'aide"), $application];
		}

		if($eAid['commitmentRule'] !== NULL) {
			$rule = nl2br(encode($eAid['commitmentRule']));
			$steps[] = [s("Attendre avant de signer un devis ou d'acheter"), '<p class="util-warning">'.$rule.'</p><p class="aid-deposit-print util-print-block">'.$rule.'</p>'];
		}

		if($payment !== '') {
			$steps[] = [s("Demander le paiement, une fois l'achat fait"), $payment];
		}

		if($steps === []) {
			return '';
		}

		$h = '<div class="aid-deposit">';

			$h .= '<div class="util-title mt-2">';
				$h .= '<h3>'.s("Pour déposer").'</h3>';
			$h .= '</div>';

			// Un numéro dit la place d'une étape dans une suite : une étape seule n'en porte pas (plan de L6, point 9).
			if(count($steps) === 1) {

				[$title, $content] = $steps[0];

				$h .= '<h4>'.$title.'</h4>';
				$h .= $content;

			} else {

				$h .= '<ol class="util-procedure">';
					foreach($steps as [$title, $content]) {
						$h .= '<li class="util-procedure-step">';
							$h .= '<h4 class="util-procedure-title">'.$title.'</h4>';
							$h .= $content;
						$h .= '</li>';
					}
				$h .= '</ol>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une pièce et sa condition sur la même ligne (CL-06), puis le modèle de l'organisme quand il en donne
	 * un (CL-07), par un lien à lui : le nom de la pièce reste un texte, celui de ce qu'on réunit.
	 */
	protected function getPieces(\Collection $cAidPiece): string {

		if($cAidPiece->empty()) {
			return '';
		}

		$h = '<ul class="util-check-list">';

			foreach($cAidPiece as $eAidPiece) {

				$h .= '<li>';
					$h .= encode(AidPieceUi::getName($eAidPiece));
					if($eAidPiece['note'] !== NULL) {
						$h .= ' ('.encode($eAidPiece['note']).')';
					}
					if($eAidPiece['url'] !== NULL) {
						$h .= ' <a href="'.encode($eAidPiece['url']).'" target="_blank" class="util-print-hide">'.\Asset::icon('box-arrow-up-right').' '.s("Modèle à remplir").'</a>';
						$h .= '<span class="aid-deposit-print util-print-block text-break">'.s("Modèle à remplir sur {value}", encode($eAidPiece['url'])).'</span>';
					}
				$h .= '</li>';

			}

		$h .= '</ul>';

		return $h;

	}

	/**
	 * D'où vient la liste : relue par l'équipe à la date de « Marquer la liste relue », sinon relevée
	 * sur le site de sa source, sans date, celle du relevé n'étant pas une relecture (arbitrage de
	 * V-Sub sur l'avis de l'architecte).
	 */
	protected function getPiecesRead(Aid $eAid): string {

		if($eAid['piecesCheckedAt'] !== NULL) {
			$text = s("Liste relue le {value}", \util\DateUi::numeric($eAid['piecesCheckedAt'], \util\DateUi::DATE));
		} else {

			$eSource = $eAid['eSourcePieces']->notEmpty() ? $eAid['eSourcePieces'] : $eAid['eSourcePrimary'];

			if($eSource->empty()) {
				return '';
			}

			$text = s("Liste relevée sur {value}", encode(self::getHost($eSource['url'])));

		}

		return '<p class="util-annotation">'.$text.'</p>';

	}

	protected function getSheetItems(Aid $eAid): string {

		$h = \main\DesignUi::getRecordItem(s("Dépôt"), $this->getDeadline($eAid));

		// Sans taux écrit, la fiche dit au moins ce que dit la carte.
		if($eAid['rate'] !== NULL) {
			$h .= \main\DesignUi::getRecordItem(self::p('type')->values[$eAid['type']], encode($eAid['rate']));
		} else {
			[$key, $value] = $this->getFigureParts($eAid);
			$h .= \main\DesignUi::getRecordItem($key, $value);
		}

		$h .= $this->getBounds($eAid);

		// Une même alerte couvre le montant comme la date d'une aide : son libellé n'en nomme aucun.
		if($eAid['isUnconfirmed']) {
			$h .= \main\DesignUi::getRecordItem(s("À vérifier"), s("À faire confirmer par l'organisme qui accorde l'aide avant de déposer votre dossier."), class: 'util-record-item-alert');
		}

		if($eAid['cTag']->notEmpty()) {
			$h .= \main\DesignUi::getRecordItem(s("Ce qui est financé"), TagUi::getBadges($eAid['cTag']));
		}

		$who = $this->getBeneficiaries($eAid);

		if($who !== '') {
			$h .= \main\DesignUi::getRecordItem(s("Pour qui"), $who);
		}

		$where = $this->getPlaces($eAid);

		if($where !== '') {
			$h .= \main\DesignUi::getRecordItem(s("Où"), $where);
		}

		if($eAid['productionsShown'] !== []) {
			// En minuscule comme sur la carte, sauf le premier nom, qui ouvre la valeur.
			$productions = \structure\ProductionUi::getList();
			$names = array_map(fn(string $production) => mb_strtolower($productions[$production]), $eAid['productionsShown']);
			$names[0] = $productions[$eAid['productionsShown'][0]];
			$h .= \main\DesignUi::getRecordItem(s("Productions"), self::getEnumeration($names));
		}

		if($eAid['labels'] !== NULL and $eAid['labels']->values() !== []) {
			$h .= \main\DesignUi::getRecordItem(s("Réservée à"), self::getLabels($eAid['labels']));
		}

		if($eAid['labelsBonus'] !== NULL and $eAid['labelsBonus']->values() !== []) {
			$h .= \main\DesignUi::getRecordItem(s("Bonifiée pour"), self::getLabels($eAid['labelsBonus']));
		}

		if($eAid['conditions'] !== NULL) {
			$h .= \main\DesignUi::getRecordItem(s("Conditions"), new \editor\EditorUi()->value($eAid['conditions']), class: 'util-record-item-wide');
		}

		return $h;

	}

	// Le plancher et le plafond, en euros, de la dépense éligible ou de l'aide versée (`boundBase`), par
	// an quand ils le sont (`boundPeriod`). La fiche ne reçoit des bornes qu'avec leur assiette
	// (AidLib::getSheet()), d'où le match sans cas vide.
	protected function getBounds(Aid $eAid): string {

		if($eAid['floor'] === NULL and $eAid['ceiling'] === NULL) {
			return '';
		}

		$floor = ($eAid['floor'] !== NULL) ? \util\TextUi::number($eAid['floor'], NULL) : NULL;
		$ceiling = ($eAid['ceiling'] !== NULL) ? \util\TextUi::number($eAid['ceiling'], NULL) : NULL;

		// Des titres de rangée, que les valeurs du describer ne peuvent pas fournir : celles-ci sont des
		// morceaux de la phrase du formulaire (« portent sur la dépense »).
		$label = match($eAid['boundBase']) {
			Aid::EXPENSE => s("Dépense éligible"),
			Aid::AID => s("Aide versée"),
		};

		if($floor !== NULL and $ceiling !== NULL) {
			$value = s("De {floor} € à {ceiling} €", ['floor' => $floor, 'ceiling' => $ceiling]);
		} else if($floor !== NULL) {
			$value = s("{value} € au moins", $floor);
		} else {
			$value = s("{value} € au plus", $ceiling);
		}

		if($eAid['boundPeriod'] !== NULL) {
			$value = s("{bounds} {period}", ['bounds' => $value, 'period' => self::p('boundPeriod')->values[$eAid['boundPeriod']]]);
		}

		return \main\DesignUi::getRecordItem($label, $value);

	}

	// L'organisme qui accorde l'aide, puis ceux qui la cofinancent.
	protected function getFunders(Aid $eAid): string {

		$organization = encode($eAid['organization']['name']);

		if($eAid['cOrganizationCofunder']->empty()) {
			return $organization;
		}

		$cofunders = $eAid['cOrganizationCofunder']->makeArray(fn(Organization $eOrganization) => encode($eOrganization['name']));

		return s("{organization}, avec {cofunders}", ['organization' => $organization, 'cofunders' => self::getEnumeration($cofunders)]);

	}

	// Le déposant ne suit le public que lorsque celui-ci ne le nomme pas déjà (AidLib::getSheet()).
	protected function getBeneficiaries(Aid $eAid): string {

		if($eAid['beneficiaries'] === NULL or $eAid['beneficiaries']->values() === []) {
			return '';
		}

		$values = self::p('beneficiaries')->values;

		$who = self::getEnumeration(array_map(fn(int $beneficiary) => $values[$beneficiary], $eAid['beneficiaries']->values()));

		return ($eAid['direct'] === FALSE and $eAid['isApplicantNamed'] === FALSE) ? $who.', '.$this->getApplicant($eAid) : $who;

	}

	/**
	 * Une aide nationale ou de la PAC sans périmètre vaut partout ; les autres, dans leur bassin, ou
	 * dans leurs régions puis leurs départements, numéro compris. Une aide locale dont le périmètre
	 * n'est pas saisi ne dit rien plutôt que de se dire nationale.
	 */
	protected function getPlaces(Aid $eAid): string {

		$places = [
			...array_map(fn(string $basin) => s("Bassin {value}", AidSetting::BASINS[$basin][0]), $eAid['places']['basins']),
			...array_map(fn(string $region) => AidSetting::REGIONS[$region], $eAid['places']['regions']),
			...array_map(fn(string $department) => self::getDepartment($department), $eAid['places']['departments']),
		];

		if($places === []) {
			return in_array($eAid['level'], [Aid::NATIONAL, Aid::PAC], TRUE) ? s("Toute la France") : '';
		}

		return encode(self::getEnumeration($places));

	}

	/**
	 * Le nom d'une institution sans son territoire, que la ligne de lieu dit déjà : « Département de la
	 * Meuse » devient « Département ». Le nom se rend sans article, comme celui de tout organisme, dont
	 * le genre ne se déduit pas (la MSA, FranceAgriMer). Le préfixe est un morceau du nom saisi, il ne se
	 * traduit pas plus que lui. Il se compare au mot entier, pour que « Régions (autorités de gestion…) »
	 * ou « Département-Région de Mayotte » gardent leur nom, comme tout organisme qui n'est pas une
	 * institution territoriale (FranceAgriMer, ODARC, une DAAF).
	 */
	public static function getInstitution(string $name): string {

		$institutions = [
			'Région',
			'Département',
			'Agence de l\'eau',
			'Office de l\'eau',
			'Collectivité territoriale',
			'Chambre d\'agriculture',
			'Parc naturel régional',
			'Métropole',
		];

		foreach($institutions as $institution) {
			if(str_starts_with($name, $institution.' ')) {
				return $institution;
			}
		}

		return $name;

	}

	// `labels` comme `labelsBonus` se lisent « l'un ou l'autre ».
	protected static function getLabels(\Set $labels): string {

		$values = self::p('labels')->values;
		$names = array_map(fn(int $label) => $values[$label], $labels->values());
		$last = array_pop($names);

		return ($names === []) ? $last : s("{list} ou {last}", ['list' => implode(', ', $names), 'last' => $last]);

	}

	/**
	 * Au-delà de trois labels, la carte ne les nomme plus. HVE se dit à part, parce qu'elle est
	 * une certification environnementale et non un signe de qualité.
	 */
	protected static function getScopeLabels(\Set $labels): string {

		if($labels->values() === [Aid::AB]) {
			return s("bio uniquement");
		}

		if(count($labels->values()) > 3) {
			return $labels->value(Aid::HVE) ? s("sous signe de qualité ou HVE") : s("sous signe de qualité");
		}

		return s("en {value}", self::getLabels($labels));

	}

	protected static function getHost(string $url): string {

		return preg_replace('/^www\./', '', parse_url($url, PHP_URL_HOST) ?? $url);

	}

	protected static function getEnumeration(array $words): string {

		if(count($words) === 1) {
			return reset($words);
		}

		$last = array_pop($words);

		return s("{list} et {last}", ['list' => implode(', ', $words), 'last' => $last]);

	}

	/**
	 * Les suggestions de la recherche, famille par famille. Les projets et les productions n'ont de nom
	 * que traduit : c'est ici, et non dans `AidLib::getSuggestions()`, que la saisie s'y compare.
	 */
	public static function getSuggestions(\structure\Structure $eStructure, array $filters, string $query, array $suggestions): array {

		$url = fn(array $changes) => self::url($eStructure, $filters, $changes);
		$needle = toFqn($query);
		$match = fn(string $label) => ($needle === '' or str_contains(toFqn($label), $needle));

		$results = [];

		if($filters['project'] === NULL) {
			foreach(TagUi::p('projects')->values as $project => $label) {
				if($match($label)) {
					$results[] = [
						'group' => s("Projets"),
						'label' => $label,
						'value' => $project,
						'url' => $url(['project' => $project, 'tag' => NULL, 'all' => NULL]),
					];
				}
			}
		}

		foreach($suggestions['cTag'] as $eTag) {
			$results[] = [
				'group' => s("Ce qui est financé"),
				'label' => $eTag['name'],
				'value' => $eTag['fqn'],
				'url' => $url(['tag' => $eTag['fqn']]),
			];
		}

		foreach(\structure\ProductionUi::getList() as $production => $label) {
			if(in_array($production, $filters['productions'], TRUE) === FALSE and $match($label)) {
				$results[] = [
					'group' => s("Productions"),
					'label' => $label,
					'value' => $production,
					'url' => $url(['productions' => implode(',', [...$filters['productions'], $production])]),
				];
			}
		}

		foreach($suggestions['cAid'] as $eAid) {
			$results[] = [
				'group' => s("Aides"),
				'label' => $eAid['name'],
				'value' => $eAid['id'],
				'url' => self::urlSheet($eStructure, $eAid, $filters),
			];
		}

		return $results;

	}

	// Une aide à choisir dans l'administration, quel que soit son état sauf archivée. Statique comme
	// getAutocomplete() : le constructeur charge la feuille et le script de l'outil des structures,
	// que l'administration n'a pas à porter.
	public static function query(\PropertyDescriber $d): void {

		$d->prepend = \Asset::icon('search');
		$d->field = 'autocomplete';

		$d->placeholder ??= s("Tapez le nom d'une aide");

		$d->autocompleteUrl = '/aid/admin/aid:query';
		$d->autocompleteResults = fn(Aid $e) => self::getAutocomplete($e);

	}

	public static function getAutocomplete(Aid $eAid): array {

		$item = encode($eAid['name']).'<br/><small class="color-muted">'.encode($eAid['organization']['name']).'</small>';

		return [
			'value' => $eAid['id'],
			'itemHtml' => $item,
			'itemText' => $eAid['name'],
		];

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Aid::model()->describer($property, [
			'name' => s("Nom"),
			'type' => s("Forme"),
			'calendar' => s("Calendrier"),
			'rate' => s("Taux ou montant"),
			'rateUnpublished' => s("L'organisme ne publie pas de taux"),
			'beneficiaries' => s("Pour qui"),
			'labels' => s("Réservée à l'un de ces labels"),
			'labelsBonus' => s("Labels qui bonifient"),
			'conditions' => s("Conditions"),
			'summary' => s("Résumé"),
			'organization' => s("Accordée par"),
			'parent' => s("Volet de"),
			'level' => s("Échelon"),
			'ratePercentMax' => s("Taux maximal"),
			'amountMax' => s("Montant maximal"),
			'floor' => s("Plancher"),
			'ceiling' => s("Plafond"),
			'boundBase' => s("Le plancher et le plafond portent sur"),
			'boundPeriod' => s("Le plancher et le plafond valent"),
			'productions' => s("Productions"),
			'direct' => s("La structure dépose elle-même"),
			'depositUrl' => s("Où déposer"),
			'commitmentRule' => s("Avant de signer un devis ou d'acheter"),
			'piecesUnpublished' => s("L'organisme ne publie pas la liste des pièces"),
			'keywords' => s("Mots-clés"),
			'notes' => s("Notes de relevé"),
		]);

		switch($property) {

			// Le nom de la valeur que la carte et la fiche donnent à lire, selon ce que l'aide apporte.
			case 'type' :
				$d->values = [
					Aid::GRANT => s("Subvention"),
					Aid::LOAN => s("Prêt"),
					Aid::ADVANCE => s("Avance remboursable"),
					Aid::GUARANTEE => s("Garantie"),
					Aid::TAX => s("Avantage fiscal"),
					Aid::SOCIAL => s("Exonération sociale"),
					Aid::SERVICE => s("Prise en charge"),
					Aid::LAND => s("Accès au foncier"),
				];
				break;

			// L'unité d'un montant, collée au nombre sur la carte.
			case 'amountUnit' :
				$d->values = [
					Aid::EURO => s("€"),
					Aid::PER_HECTARE => s("€/ha"),
					Aid::PER_UGB => s("€/UGB"),
					Aid::PER_HEAD => s("€/tête"),
					Aid::PER_METER => s("€/m"),
					Aid::PER_LITER => s("€/L"),
				];
				break;

			case 'calendar' :
				$d->values = [
					Aid::PERMANENT => s("Toute l'année"),
					Aid::CALL => s("Sur appel à projets"),
					Aid::CAMPAIGN => s("Chaque année"),
					Aid::AUTOMATIC => s("Sans démarche"),
				];
				break;

			case 'beneficiaries' :
				$d->values = [
					Aid::FARM => s("Exploitations agricoles"),
					Aid::YOUNG_FARMER => s("Jeunes agriculteurs"),
					Aid::NEW_FARMER => s("Nouveaux installés"),
					Aid::CUMA => s("CUMA"),
					Aid::GROUP => s("Groupements d'agriculteurs"),
					Aid::COMMUNITY => s("Collectivités"),
					Aid::ASSOCIATION => s("Associations"),
					Aid::LANDOWNER => s("Propriétaires fonciers"),
				];
				break;

			case 'summary' :
				$d->field = 'textarea';
				break;

			// organization et parent : un champ d'autocomplétion nomme son groupe d'après l'id de son
			// libellé, et le wrapper posé rattache le refus au champ plutôt qu'au bandeau.
			case 'organization' :
				new OrganizationUi()->query($d);
				$d->wrapper = 'organization';
				break;

			// Le dispositif dont l'aide est un volet ne peut pas être l'aide elle-même (Aid::parent.self) :
			// elle ne se propose pas.
			case 'parent' :
				$d->autocompleteBody = fn(\util\FormUi $form, Aid $e) => $e->exists() ? ['aid' => $e['id']] : [];
				self::query($d);
				$d->placeholder = s("Aucun, l'aide est un dispositif à part entière");
				$d->wrapper = 'parent';
				break;

			case 'level' :
				$d->values = [
					Aid::NATIONAL => s("National"),
					Aid::PAC => s("PAC"),
					Aid::REGION => s("Régional"),
					Aid::DEPARTMENT => s("Départemental"),
					Aid::INTERCOMMUNAL => s("Intercommunal"),
					Aid::OTHER => s("Autre"),
				];
				break;

			case 'rate' :
				$d->field = 'textarea';
				$d->labelAfter = \util\FormUi::info(s("Tel que l'organisme le publie. Sans taux ni montant, l'aide ne se publie que si l'organisme n'en publie aucun."));
				break;

			case 'ratePercentMax' :
				$d->append = s("%");
				break;

			case 'floor' :
			case 'ceiling' :
				$d->append = s("€");
				break;

			case 'boundBase' :
				$d->values = [
					Aid::EXPENSE => s("la dépense"),
					Aid::AID => s("l'aide"),
				];
				break;

			// Collée au montant sur la carte et la fiche (« 5 000 € par an »).
			case 'boundPeriod' :
				$d->values = [
					Aid::YEAR => s("par an"),
				];
				$d->placeholder = s("pour toute l'aide");
				break;

			// Aucune production cochée vaut pour toutes.
			case 'productions' :
				$d->field = fn(\util\FormUi $form, Aid $e) => $form->checkboxes('productions[]', \structure\ProductionUi::getList(), $e['productions'], [
					'columns' => 3,
					'all' => FALSE,
				]);
				$d->labelAfter = \util\FormUi::info(s("Aucune cochée pour toutes les productions"));
				break;

			case 'rateUnpublished' :
			case 'direct' :
				$d->field = 'yesNo';
				break;

			case 'notes' :
				$d->field = 'textarea';
				$d->labelAfter = \util\FormUi::info(s("Ce que le relevé n'a pas su ranger, jamais montré aux structures"));
				break;

			case 'depositUrl' :
				$d->labelAfter = \util\FormUi::info(s("Le site où la structure dépose son dossier, quand ce n'est pas la source officielle"));
				break;

			case 'commitmentRule' :
				$d->field = 'textarea';
				$d->labelAfter = \util\FormUi::info(s("Ce qui fait perdre l'aide s'il est fait trop tôt, tel que la source le dit"));
				break;

			case 'status' :
				$d->values = [
					Aid::DRAFT => s("Brouillon"),
					Aid::PUBLISHED => s("Publiée"),
					Aid::ARCHIVED => s("Archivée"),
				];
				break;

			case 'verification' :
				$d->values = [
					// D'où vient le contenu, pas qui l'a lu : la lecture d'une source par la veille se dit sur la
					// source (SourceUi::getStatus()), la relecture par l'équipe à part (Aid::isVerifiedByTeam()).
					Aid::PRIMARY => s("Tirée de la source officielle"),
					Aid::RELAY => s("Relais seul"),
					Aid::TO_CHECK => s("À confirmer"),
					Aid::CONFLICT => s("Sources divergentes"),
				];
				break;

			case 'labels' :
			case 'labelsBonus' :
				$d->values = [
					Aid::AB => s("Agriculture biologique"),
					Aid::AOP => s("AOP"),
					Aid::AOC => s("AOC"),
					Aid::IGP => s("IGP"),
					Aid::LABEL_ROUGE => s("Label Rouge"),
					Aid::STG => s("STG"),
					Aid::HVE => s("Haute valeur environnementale"),
				];
				// Sur trois colonnes comme les productions, la liste de cases voisine du panneau. Tout
				// décocher ne poste rien, et build() rend alors NULL, aucun label, à la colonne nullable.
				$d->field = fn(\util\FormUi $form, Aid $e) => $form->checkboxes($property.'[]', $d->values, $e[$property], [
					'columns' => 3,
					'all' => FALSE,
				]);
				break;

		}

		return $d;

	}

}
?>
