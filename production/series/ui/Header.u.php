<?php
namespace series;

class HeaderUi {

	public static function getSeries(\structure\Structure $eStructure, int $selectedSeason, string $selectedView, ?int $nSeries = NULL, ?bool $firstSeries = NULL, \structure\Structure $eStructureCommunityExport = new \structure\Structure()): string {

		$tools = '';
		$create = '';

		if($selectedView === \structure\Member::AREA) {

			if(
				$eStructure->canManage() and
				$firstSeries === FALSE
			) {
				$create = '<a href="/series/series:createFrom?structure='.$eStructure['id'].'&season='.$selectedSeason.'" class="btn btn-secondary" data-ajax-class="Ajax.Query">'.\Asset::icon('plus-circle').'<span class="hide-xs-down"> '.s("Nouvelle série").'</span></a>';
			}

			if($nSeries >= 1) {
				$tools .= '<a href="/series/series:downloadCultivation?id='.$eStructure['id'].'&season='.$selectedSeason.'" data-ajax-download="'.s("Plan de culture {value}", $selectedSeason).'.pdf" data-waiter="'.s("Création en cours").'" data-waiter-timeout="8" class="btn btn-outline-secondary">'.\main\DesignUi::getGestureLabel('file-pdf', s("PDF")).'</a>';
			}

			// ⚠️ Un lien et jamais un bouton de formulaire : `data-ajax-download` ne
			// sait servir un fichier que sur un lien (`ajax.js:1644`), et sur un
			// formulaire `form.js:21` appelle `download()` sur la classe puis lève. Le
			// CSV ne peut donc pas descendre dans la barre de sélection, qui est un
			// formulaire : il porte la saison entière, la barre porte la sélection.
			//
			// ⚠️ Et **seulement sur une ferme qu'on n'administre pas**. Le rôle `admin`
			// porte `productionExport`, donc `canExportProduction()` seul laisserait ce
			// bouton chez soi, où l'export du plan existe déjà sous un autre nom
			// (`csv:exportCultivations`, `Structure.u.php:1454`). Deux chemins pour le
			// même fichier se contrediraient le jour où l'un des deux change.
			if(
				$nSeries >= 1 and
				$eStructure->canManage() === FALSE and
				$eStructureCommunityExport->notEmpty() and
				$eStructureCommunityExport->grantsCommunityRight('shareProductionExport') and
				$eStructure->canExportProduction()
			) {
				$tools .= '<a href="'.CopyAiUi::urlCsv($eStructure, $eStructureCommunityExport, $selectedSeason).'" data-ajax-download data-waiter class="btn btn-outline-secondary">'.\Asset::icon('filetype-csv').'<span class="hide-xs-down"> '.s("CSV").'</span></a>';
			}

		}

		return \main\DesignUi::getPageHeader(
			s("Plan de culture"),
			season: new \structure\StructureUi()->getSeason($eStructure, fn(int $season) => \structure\StructureUi::urlCultivationSeries($eStructure, season: $season), $selectedSeason),
			tools: $tools,
			create: $create
		);

	}

	public static function getForecast(\structure\Structure $eStructure, int $selectedSeason): string {

		$create = '';

		if(new \plant\Forecast(['structure' => $eStructure])->canCreate()) {
			$create = '<a href="/plant/forecast:create?structure='.$eStructure['id'].'&season='.$selectedSeason.'" class="btn btn-secondary">'.\Asset::icon('plus-circle').'<span class="hide-xs-down"> '.s("Ajouter une espèce").'</span></a>';
		}

		return \main\DesignUi::getPageHeader(
			s("Prévisionnel financier"),
			season: new \structure\StructureUi()->getSeason($eStructure, fn(int $season) => \structure\StructureUi::urlCultivationForecast($eStructure, season: $season), $selectedSeason),
			create: $create
		);

	}

	public static function getSeedling(\structure\Structure $eStructure, int $selectedSeason): string {

		return \main\DesignUi::getPageHeader(
			s("Semences et plants"),
			season: new \structure\StructureUi()->getSeason($eStructure, fn(int $season) => \structure\StructureUi::urlCultivationSeedling($eStructure, season: $season), $selectedSeason)
		);

	}

	// Chaque option est une URL, jamais un champ de formulaire : l'état vit dans
	// la requête, le lien se partage et le bouton retour du navigateur retrouve
	// la recherche précédente sans rien à réémettre.
	public static function getSeedlingSearch(\structure\Structure $eStructure, int $season, \Search $search, \Collection $cSupplier): string {

		$url = fn(string $argument, ?string $value) => ($value === NULL) ?
			\util\HttpUi::removeArgument(LIME_REQUEST, $argument) :
			\util\HttpUi::setArgument(LIME_REQUEST, $argument, $value);

		$h = '<div class="util-filters mb-2">';

			$values = self::getSeedlingLabels();
			$seedling = $search->get('seedling');

			$options = [[$url('seedling', NULL), s("Tout"), $seedling === NULL]];

			foreach($values as $value => $label) {
				$options[] = [$url('seedling', $value), $label, $seedling === $value];
			}

			$h .= self::getSeedlingDropdown(
				s("Affichage"),
				$seedling ? $values[$seedling] : s("Tout"),
				$seedling !== NULL,
				$options
			);

			if($cSupplier->notEmpty()) {

				$eSupplier = $search->get('supplier');

				$options = [[$url('supplier', NULL), s("Tous"), $eSupplier->empty()]];

				foreach($cSupplier as $eSupplierOption) {
					$options[] = [$url('supplier', $eSupplierOption['id']), encode($eSupplierOption['name']), $eSupplier->is($eSupplierOption)];
				}

				$h .= self::getSeedlingDropdown(
					s("Fournisseur"),
					$eSupplier->empty() ? s("Tous") : encode($eSupplier['name']),
					$eSupplier->notEmpty(),
					$options
				);

			}

			$h .= '<div class="util-filter">';

				$h .= '<div class="util-filter-label">'.s("Semaine").'</div>';
				$h .= '<div>'.self::getSeedlingWeek($eStructure, $season, $search, $url).'</div>';

			$h .= '</div>';

			$h .= '<div class="util-filter">';

				$h .= '<div class="util-filter-label">'.s("Regrouper").'</div>';

				$h .= '<div class="util-filter-options">';
					$h .= self::getSeedlingOption(s("Par espèce"), $url('groupWeek', NULL), $search->get('groupWeek') === FALSE);
					$h .= self::getSeedlingOption(s("Par semaine"), $url('groupWeek', '1'), $search->get('groupWeek'));
				$h .= '</div>';

			$h .= '</div>';

			$h .= '<div class="util-filter">';

				$h .= '<div class="util-filter-label">'.s("Exporter").'</div>';
				$h .= '<div>'.self::getSeedlingExport($eStructure, $season, $search).'</div>';

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	// Le calendrier des semaines du produit, ouvert par le jeton : ses bulles sont des
	// liens, comme le reste de la barre, et il sait déjà naviguer d'une année à l'autre.
	protected static function getSeedlingWeek(\structure\Structure $eStructure, int $season, \Search $search, \Closure $url): string {

		$week = $search->get('week');

		$h = '<a class="util-chip'.($week === NULL ? '' : ' is-active').'" data-dropdown="bottom-start">';
			$h .= ($week === NULL) ? s("Toutes") : self::getSeedlingWeekLabel($week);
		$h .= '</a>';

		$h .= '<div class="dropdown-list dropdown-list-unstyled dropdown-list-minimalist">';

			if($week !== NULL) {
				$h .= '<a href="'.$url('week', NULL).'" class="btn btn-primary mb-1">'.s("Toutes les semaines").'</a>';
			}

			$h .= \util\FormUi::weekSelector(
				$week === NULL ? $season : (int)week_year($week),
				$url('week', '__CURRENT__'),
				defaultWeek: $week,
				minYear: $eStructure->conf(\map\Configuration::class)->getFirstValidSeason(),
				maxYear: $eStructure->conf(\map\Configuration::class)->getLastValidSeason()
			);

		$h .= '</div>';

		return $h;

	}

	// Le filtre ne nomme pas le mode d'implantation mais la colonne qu'il laisse à
	// l'écran : c'est ce que l'utilisateur vient chercher, et ce que les sections du
	// PDF portent déjà. Les libellés du describer disent autre chose, et servent
	// ailleurs à décrire une culture.
	protected static function getSeedlingLabels(): array {

		return [
			\series\Cultivation::SOWING => s("Semences à acheter"),
			\series\Cultivation::YOUNG_PLANT => s("Plants à produire"),
			\series\Cultivation::YOUNG_PLANT_BOUGHT => s("Plants à acheter")
		];

	}

	protected static function getSeedlingOption(string $label, string $url, bool $selected): string {

		return '<a href="'.$url.'" class="util-filter-option'.($selected ? ' is-selected' : '').'">'.$label.'</a>';

	}

	// Un groupe qui ouvre un déroulant plutôt que d'aligner ses options : au-delà
	// de deux valeurs, la rangée mange la largeur que la grille réclame.
	protected static function getSeedlingDropdown(string $label, string $chip, bool $active, array $options): string {

		$h = '<div class="util-filter">';

			$h .= '<div class="util-filter-label">'.$label.'</div>';

			$h .= '<div>';

				$h .= \main\DesignUi::getChipDropdown($chip, $options, $active);

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	// Un export filtré ne se distingue d'un export complet que par ce rappel :
	// sans lui, deux PDF du même jour sont impossibles à départager.
	public static function getSeedlingCriteria(\Search $search): string {

		$criteria = [];

		if($search->get('seedling')) {
			$criteria[] = self::getSeedlingLabels()[$search->get('seedling')];
		}

		if($search->get('supplier')->notEmpty()) {
			$criteria[] = s("Fournisseur {value}", encode($search->get('supplier')['name']));
		}

		if($search->get('week') !== NULL) {
			$criteria[] = self::getSeedlingWeekLabel($search->get('week'));
		}

		return implode(' &middot; ', $criteria);

	}

	// Les filtres composent aussi bien l'URL de la page que celle des exports :
	// une seule écriture, pour qu'ils ne divergent pas.
	public static function getSeedlingArguments(\Search $search, array $changes = []): string {

		$arguments = [];

		foreach(self::getSeedlingValues($search, $changes) as $name => $value) {
			if($value !== NULL) {
				$arguments[] = urlencode($name).'='.urlencode($value);
			}
		}

		return implode('&', $arguments);

	}

	protected static function getSeedlingValues(\Search $search, array $changes = []): array {

		return array_merge([
			'seedling' => $search->get('seedling'),
			'supplier' => $search->get('supplier')->empty() ? NULL : $search->get('supplier')['id'],
			'week' => $search->get('week'),
			'groupWeek' => $search->get('groupWeek') ? 1 : NULL
		], $changes);

	}

	protected static function getSeedlingExport(\structure\Structure $eStructure, int $season, \Search $search): string {

		$arguments = 'id='.$eStructure['id'].'&season='.$season;
		$filters = self::getSeedlingArguments($search);

		if($filters !== '') {
			$arguments .= '&'.$filters;
		}

		$urlPdf = 'href="/series/series:downloadSeedling?'.$arguments.'" data-ajax-download="'.encode(self::getSeedlingPdfFilename($season)).'" data-waiter="'.s("Création en cours").'" data-waiter-timeout="8"';

		if($eStructure->canManage() === FALSE) {
			return '<a '.$urlPdf.' class="btn btn-outline-primary">'.\Asset::icon('file-pdf').' '.s("PDF").'</a>';
		}

		$h = '<a data-dropdown="bottom-end" class="btn btn-outline-primary dropdown-toggle">'.s("Télécharger").'</a>';
		$h .= '<div class="dropdown-list">';

			$h .= '<a '.$urlPdf.' class="dropdown-item">';
				$h .= \Asset::icon('file-pdf').' '.s("Format PDF");
			$h .= '</a>';

			$h .= '<a href="/series/csv:exportSeedling?'.$arguments.'" class="dropdown-item" data-ajax-download data-waiter>';
				$h .= \Asset::icon('filetype-csv').' '.s("Format CSV");
			$h .= '</a>';

		$h .= '</div>';

		return $h;

	}

	public static function getSoilMenu(\structure\Member $eMember, ?string $rotation): string {

		$h = '<div class="dropdown-list util-soil-menu">';

			if($eMember->notEmpty()) {
				$h .= '<div class="dropdown-title">'.s("Personnalisation").'</div>';
				$h .= self::getSoilColorItems($eMember);
			}

			if($rotation !== NULL) {

				if($eMember->notEmpty()) {
					$h .= '<div class="dropdown-divider"></div>';
				}

				$h .= '<a href="'.$rotation.'" class="dropdown-item">'.s("Faire une rotation par jardin").'</a>';

			}

		$h .= '</div>';

		return $h;

	}

	protected static function getSoilColorItems(\structure\Member $eMember): string {

		$modes = [
			\structure\Member::WHITE => s("Couleurs claires"),
			\structure\Member::PLANT => s("Couleur des espèces"),
			\structure\Member::FAMILY => s("Couleur des familles"),
		];

		$h = '';

			foreach($modes as $mode => $label) {

				$isCurrent = ($mode === $eMember['viewSoilColor']);

				$h .= '<a data-ajax="/structure/member:doUpdateSoilColor" post-id="'.$eMember['id'].'" post-view-soil-color="'.$mode.'" class="dropdown-item util-soil-mode'.($isCurrent ? ' selected' : '').'" data-mode="'.$mode.'">';
					$h .= '<span class="util-soil-label">'.$label.'</span>';
					$h .= '<span class="util-soil-preview"></span>';
				$h .= '</a>';

			}

			$h .= '<div class="dropdown-divider"></div>';

			$h .= '<div class="dropdown-block util-soil-switch">';
				$h .= '<span>'.s("Semaines de semis, plantation et récolte").'</span>';
				$h .= \util\TextUi::switch(
					[
						'data-ajax' => '/structure/member:doUpdateSoilTasks',
						'post-id' => $eMember['id'],
						'post-view-soil-tasks' => $eMember['viewSoilTasks'] ? 0 : 1,
						'class' => 'field-switch-sm',
					],
					on: (bool)$eMember['viewSoilTasks'],
					textOn: s("Oui"),
					textOff: s("Non")
				);
			$h .= '</div>';

		return $h;

	}

	// Une saison déborde l'année civile : le numéro seul ne situe pas la semaine.
	protected static function getSeedlingWeekLabel(string $week): string {
		return s("Semaine {number}, {year}", ['number' => week_number($week), 'year' => week_year($week)]);
	}

	public static function getSeedlingPdfFilename(int $season): string {

		return s("Semences et plants {value}", $season).'.pdf';

	}

	public static function getSoilPdfFilename(string $view, int $season): string {

		return match($view) {
			\structure\Member::GARDEN => s("Plan d'assolement {value}", $season),
			\structure\Member::PLAN => s("Liste d'assolement {value}", $season),
		}.'.pdf';

	}

	public static function getSoilViews(\structure\Structure $eStructure, int $selectedSeason, string $selectedView, string $aside = ''): string {

		$tabs = '';

		foreach([
			\structure\Member::GARDEN => s("Plan"),
			\structure\Member::PLAN => s("Liste"),
			\structure\Member::ROTATION => s("Rotations"),
		] as $viewKey => $viewLabel) {
			$tabs .= '<a href="'.self::getSoilViewUrl($eStructure, $viewKey, $selectedSeason).'" class="tab-item '.($viewKey === $selectedView ? 'selected' : '').'">'.$viewLabel.'</a>';
		}

		return \main\DesignUi::getPageTabs(
			$tabs,
			tools: ($aside === '') ? '' : '<div class="tabs-item-label soil-views-aside">'.$aside.'</div>',
			attributes: ['class' => 'soil-views']
		);

	}

	public static function getSoilTitle(\structure\Structure $eStructure, int $selectedSeason, string $selectedView, \Collection $cZone, bool $hasGardenGroups = FALSE): string {

		$tools = '';
		$create = '';
		$settings = '';

		if($cZone->notEmpty()) {

			switch($selectedView) {

				case \structure\Member::PLAN :
				case \structure\Member::GARDEN :

					$eMember = $eStructure->getMember();

					$isSoilTouch = ($selectedView === \structure\Member::GARDEN);

					// Assoler écrit les séries : le geste suit ce que l'action exige.
					if(new Series(['structure' => $eStructure])->canWrite()) {
						$labelStart = '<span class="hide-xs-down">'.s("Assoler mes séries").'</span><span class="hide-sm-up">'.s("Assoler").'</span>';
						$create = '<a onclick="SeriesPlace.toggleEditing()" id="series-selector-button" class="btn btn-secondary'.($isSoilTouch ? '' : ' hide-lateral-down').'" data-label-start="'.encode($labelStart).'" data-label-stop="'.encode(s("Terminer")).'">';
							$create .= \Asset::icon('bounding-box').' <span>'.$labelStart.'</span>';
						$create .= '</a>';
					}

					$tools = '<a href="/series/series:downloadSoil?id='.$eStructure['id'].'&season='.$selectedSeason.'&view='.$selectedView.'" data-ajax-download="'.encode(self::getSoilPdfFilename($selectedView, $selectedSeason)).'" data-waiter="'.s("Création en cours").'" class="btn btn-outline-secondary">'.\Asset::icon('file-pdf').'<span class="hide-xs-down"> '.s("PDF").'</span></a>';

					$rotation = ($hasGardenGroups and $eStructure->canManage()) ? GardenChairsAiUi::url($eStructure, $selectedSeason) : NULL;

					// Les couleurs enregistrent une préférence **sur l'adhésion** : sans adhésion,
					// elles n'ont rien où écrire. Le plan se peint quand même, avec les
					// préférences par défaut du schéma que getMemberDisplay() rend.
					if(
						$eMember->notEmpty() or
						$rotation !== NULL
					) {
						$settings = '<a class="btn btn-color-primary dropdown-toggle" data-dropdown="bottom-end">'.\Asset::icon('gear-fill').'</a>';
						$settings .= self::getSoilMenu($eMember, $rotation);
					}

					break;

				case \structure\Member::ROTATION :

					$tools = '<a '.attr('onclick', 'Lime.Search.toggle("#bed-rotation-search")').' class="btn btn-only-icon btn-outline-secondary">'.\Asset::icon('search').'</a>';

					if($eStructure->canManage()) {
						$settings = '<a href="/map/configuration:updateCrop?id='.$eStructure->conf(\map\Configuration::class)['id'].'" class="btn btn-only-icon btn-color-primary">'.\Asset::icon('gear-fill').'</a>';
					}

					break;

			}

		}

		return \main\DesignUi::getPageHeader(
			self::getSoilName(),
			season: new \structure\StructureUi()->getSeason($eStructure, fn(int $season) => \structure\StructureUi::urlCultivationSoil($eStructure, season: $season), $selectedSeason),
			tools: $tools,
			create: $create,
			settings: $settings
		);

	}

	public static function getSoilName(): string {
		return s("Assolement");
	}

	public static function getSeriesViews(\structure\Structure $eStructure, int $season, string $selectedView): string {

		$tabs = '';

		foreach(self::getSeriesCategories($eStructure) as $key => $value) {
			$tabs .= '<a href="'.\structure\StructureUi::urlCultivationSeries($eStructure, $key, season: $season).'" class="tab-item '.($key === $selectedView ? 'selected' : '').'">'.$value.'</a>';
		}

		return \main\DesignUi::getPageTabs($tabs, tools: self::getPlanFilter(), attributes: ['class' => 'series-views']);

	}

	// Le filtre de la page : l'omnibox du design system, en mode local. Elle ne
	// recharge rien, elle masque les lignes qui ne portent pas les critères posés.
	//
	// Aucune suggestion n'est rendue ici : ce que le plan propose (espèces, variétés,
	// matériel) est exactement ce que ses lignes portent, et Series le relève dessus
	// quand le composant le demande.
	public static function getSoilFilter(): string {

		$h = '<div class="soil-filter">';

			$h .= \main\OmniboxUi::get(
				placeholder: s("Espèce, variété, matériel..."),
				countLabel: s("séries"),
				id: 'place-filter',
				class: 'place-filter util-omnibox-lg',
				singleByGroup: TRUE
			);

			$h .= '<div class="place-filter-empty hide">'.s("Aucune série ne correspond à cette recherche.").'</div>';

		$h .= '</div>';

		return $h;

	}

	public static function getPlanFilter(): string {

		$h = \main\OmniboxUi::get(
			placeholder: s("Espèce, variété, matériel..."),
			countLabel: s("séries"),
			id: 'series-filter',
			class: 'series-filter util-omnibox-lg'
		);

		$h .= '<div class="series-filter-empty hide">'.s("Aucune série ne correspond à cette recherche.").'</div>';

		// Le menu qu'ouvre un clic sur la graduation, vide : son contenu dépend de la
		// semaine visée, il est donc bâti au clic. Une seule liste pour les soixante
		// semaines, retrouvée par data-dropdown-id — le déroulant du framework sait la
		// chercher ailleurs que dans le frère immédiat du bouton.
		// data-dropdown-keep : le menu est un choix multiple, il ne doit pas se refermer
		// à la première case cochée. Le framework prévoit l'attribut, il n'y a pas de
		// raison de réécrire une fermeture.
		$h .= '<div class="dropdown-list series-week-list" data-dropdown-id="series-week-list" data-dropdown-keep></div>';

		$h .= '<script type="text/javascript">Series.weekLabels = '.json_encode([
			'family' => s("Semaines"),
			'title' => s("Semaine"),
			'titlePlural' => s("Semaines"),
			'to' => s("à"),
			'all' => s("Toutes"),
			'empty' => s("Rien cette semaine-là"),
			'months' => [s("janvier"), s("février"), s("mars"), s("avril"), s("mai"), s("juin"), s("juillet"), s("août"), s("septembre"), s("octobre"), s("novembre"), s("décembre")],
		]).';</script>';

		return $h;

	}

	public static function getSeriesCategories(\structure\Structure $eStructure): array {

		$categories = [
			\structure\Member::AREA => s("Séries"),
			\structure\Member::HARVESTING => s("Récoltes"),
			\structure\Member::WORKING_TIME => s("Temps de travail"),
		];

		if($eStructure->conf()['time'] === FALSE) {
			unset($categories[\structure\Member::WORKING_TIME]);
		}

		return $categories;

	}

	protected static function getSoilViewUrl(\structure\Structure $eStructure, string $view, ?int $season = NULL): string {

		return match($view) {
			\structure\Member::PLAN => \structure\StructureUi::urlSoil($eStructure, $season).'?view='.\structure\Member::PLAN,
			\structure\Member::GARDEN => \structure\StructureUi::urlSoil($eStructure, $season).'?view='.\structure\Member::GARDEN,
			\structure\Member::ROTATION => \structure\StructureUi::urlHistory($eStructure, $season),
		};

	}

	public static function getRotationSearch(\Search $search, array $seasons): string {

		$seen = [];
		$firstSeason = first($seasons);
		$lastSeason = last($seasons);

		foreach($seasons as $season) {

			if($season !== $firstSeason) {
				$seen[$season] = s("jamais entre {start} et {stop}", ['start' => $season, 'stop' => $firstSeason]);
			} else {
				$seen[$season] = s("jamais en {value}", $season);
			}

		}

		$seen[1] = s("au moins 1 fois entre {start} et {stop}", ['start' => last($seasons), 'stop' => first($seasons)]);

		for($i = 1; $i <= count($seasons); $i++) {
			$seen[$i] = p("exactement {value} fois entre {start} et {stop}", "exactement {value} fois entre {start} et {stop}", $i, ['start' => last($seasons), 'stop' => first($seasons)]);
		}

		$h = '<div id="bed-rotation-search" class="util-block-search '.($search->empty(['cFamily']) ? 'hide' : '').' mt-1">';

			$form = new \util\FormUi();
			$url = LIME_REQUEST_PATH;

			$h .= $form->openAjax($url, ['method' => 'get', 'class' => 'util-search']);

				$h .= '<fieldset>';
					$h .= '<legend>'.s("Famille").'</legend>';
					$h .= $form->select('family', $search->get('cFamily'), $search->get('family'), ['onchange' => 'Structure.changeSearchFamily(this)']);
					$h .= $form->inputGroup(
						$form->addon(s("Cultivée")).
						$form->select('seen', $seen, $search->get('seen', $lastSeason), ['required' => TRUE]),
						['class' => 'bed-rotation-search-seen '.($search->get('family')->notEmpty() ? NULL : 'hide')]
					);
				$h .= '</fieldset>';
				$h .= '<fieldset>';
					$h .= '<legend>'.s("Uniquement les planches permanentes").'</legend>';
					$h .= $form->select('bed', [
						0 => s("Non"),
						1 => s("Oui"),
					], (int)$search->get('bed'), ['required' => TRUE]);
				$h .= '</fieldset>';

				$h .= '<div class="util-search-submit">';
					$h .= $form->submit(s("Chercher"));
					$h .= '<a href="'.$url.'" class="btn btn-only-icon">'.\Asset::icon('x-lg').'</a>';
				$h .= '</div>';

			$h .= $form->close();

		$h .= '</div>';

		return $h;

	}

	public static function getAnalyzeWorkingTime(\structure\Structure $eStructure, array $years, int $selectedYear, ?int $selectedMonth, ?string $selectedWeek, string $selectedView): string {

		$tools = '';

		if($selectedView === \structure\Member::TIME or $selectedView === \structure\Member::TEAM) {

			$tools .= '<a class="dropdown-toggle btn btn-outline-secondary" data-dropdown="bottom-end">'.\Asset::icon('calendar3').'</a>';
			$tools .= '<div class="dropdown-list dropdown-list-minimalist">';
				$tools .= \util\FormUi::weekSelector(
					$selectedYear,
					\util\HttpUi::setArgument(LIME_REQUEST, 'week', '__CURRENT__'),
					\util\HttpUi::setArgument(LIME_REQUEST, 'month', '__CURRENT__'),
					defaultWeek: $selectedWeek,
					showYear: FALSE
				);
			$tools .= '</div>';

		}

		$categories = self::getAnalyzeWorkingTimeCategories();

		return \main\DesignUi::getPageHeader(
			$categories[$selectedView],
			context: new \work\AnalyzeUi()->getYear($years, $selectedYear, $selectedMonth, $selectedWeek, fn($year) => \structure\StructureUi::urlAnalyzeWorkingTime($eStructure, $year, $selectedView)),
			tools: $tools,
			pages: new \main\DesignUi()->getPagesFromCategories($categories, fn(string $view) => \structure\StructureUi::urlAnalyzeWorkingTime($eStructure, $selectedYear, $view), $selectedView)
		);

	}

	protected static function getAnalyzeWorkingTimeCategories(): array {
		return [
			\structure\Member::TIME => s("Temps de travail à la ferme"),
			\structure\Member::TEAM => s("Temps de travail de l'équipe"),
			\structure\Member::PACE => s("Productivité à la ferme"),
			\structure\Member::PERIOD => s("Saisonnalité du travail"),
		];
	}

	public static function getAnalyzeCultivation(\structure\Structure $eStructure, array $seasons, int $selectedSeason, string $selectedView): string {

		$tools = match($selectedView) {
			\structure\Member::PLANT => '<a '.attr('onclick', 'Lime.Search.toggle("#analyze-plant-search")').' class="btn btn-only-icon btn-outline-secondary">'.\Asset::icon('search').'</a>',
			\structure\Member::FAMILY => '<a '.attr('onclick', 'Lime.Search.toggle("#analyze-family-search")').' class="btn btn-only-icon btn-outline-secondary">'.\Asset::icon('search').'</a>',
			\structure\Member::AREA, \structure\Member::ROTATION => ''
		};

		$settings = match($selectedView) {
			\structure\Member::ROTATION => '<a href="/map/configuration:updateCrop?id='.$eStructure->conf(\map\Configuration::class)['id'].'" class="btn btn-only-icon btn-color-primary">'.\Asset::icon('gear-fill').'</a>',
			\structure\Member::AREA, \structure\Member::PLANT, \structure\Member::FAMILY => ''
		};

		$season = match($selectedView) {
			\structure\Member::AREA => '',
			\structure\Member::PLANT, \structure\Member::FAMILY, \structure\Member::ROTATION => new \plant\AnalyzeUi()->getSeason($seasons, $selectedSeason, fn(int $season) => \structure\StructureUi::urlAnalyzeCultivation($eStructure, $season, $selectedView))
		};

		$categories = self::getAnalyzeCultivationCategories();

		return \main\DesignUi::getPageHeader(
			$categories[$selectedView],
			tools: $tools,
			pages: new \main\DesignUi()->getPagesFromCategories($categories, fn(string $view) => \structure\StructureUi::urlAnalyzeCultivation($eStructure, $selectedSeason, $view), $selectedView),
			settings: $settings,
			season: $season
		);

	}

	protected static function getAnalyzeCultivationCategories(): array {
		return [
			\structure\Member::AREA => s("Surfaces cultivées"),
			\structure\Member::PLANT => s("Espèces cultivées"),
			\structure\Member::FAMILY => s("Familles cultivées"),
			\structure\Member::ROTATION => s("Rotations"),
		];
	}

}
?>
