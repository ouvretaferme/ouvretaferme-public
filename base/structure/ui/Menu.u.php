<?php
namespace structure;

class MenuUi {

	private static array $moneyStepDoneByStructure = [];

	public function __construct() {
		\Asset::js('structure', 'structure.js');
	}

	/**
	 * Les onglets de la structure, et sur téléphone le panneau qui s'ouvre en feuille. Le gabarit passe
	 * toujours `$titles`, les univers de la barre du bas, avec les boutons « Saisir » du panneau, que la
	 * feuille de style ne montre que sur téléphone. Sans eux, le menu rend tous ses groupes sans les
	 * nommer, ce que lit un test qui n'éprouve que les entrées.
	 *
	 * `$home` est le contenu du groupe Bureau, que le gabarit fait rendre par le package de l'accueil
	 * (`dashboard\PilotageUi`) quand l'accueil est le point d'arrivée de la structure.
	 */
	public function getMainTabs(Structure $eStructure, ?string $nav, ?string $subNav, ?array $titles = NULL, array $creates = [], ?string $home = NULL): string {

		if(\Clock::date() < '2025-11-15') {

			$training = '<a href="https://blog.ouvretaferme.org/#news-190" class="structure-tab hide-lateral-down" target="_blank">';
				$training .= '<span class="structure-tab-icon">'.\Asset::icon('pen-fill').'</span>';
				$training .= '<span class="structure-tab-label">'.s("Formations").'</span>';
			$training .= '</a>';

		} else {
			$training = '';
		}

		$h = '';

		// ⚠️ Le voile sort du `<nav>` et non dedans : `util-sheet-scrim` est en `position: absolute`,
		// donc son bloc conteneur est l'entête, le seul ancêtre positionné, et c'est l'entête qui porte
		// la feuille entière.
		$h .= '<div onclick="Structure.closePanel();" class="structure-panel-scrim util-sheet-scrim"></div>';

		$h .= '<nav id="structure-nav" class="structure-panel">';

			$h .= '<div id="structure-breadcrumbs">'.$this->getBreadcrumbs($eStructure, $nav, $subNav).'</div>';

			$h .= $this->getTabSections($eStructure, $nav, $subNav, $training, $titles, $creates, $home);

		$h .= '</nav>';

		return $h;

	}

	/**
	 * Les groupes d'onglets, un par univers : le jeton `structure-section-<x>` du `<body>` choisit celui
	 * qui s'affiche, et `Structure.changeSection()` le bascule sans rien redemander au serveur. Les
	 * retirer côté serveur coûterait un aller-retour à chaque geste sur un univers.
	 *
	 * `$titles` nomme les univers de la barre du téléphone : `NULL` n'en nomme aucun et rend les cinq
	 * groupes, et une liste **vide** est un membre sans aucun univers, qui n'en rend aucun.
	 */
	protected function getTabSections(Structure $eStructure, ?string $nav, ?string $subNav, string $training, ?array $titles = NULL, array $creates = [], ?string $home = NULL): string {

		$h = '';

		$menus = $eStructure['menus?']();

		// Le panneau du téléphone ne rend que les univers dont la barre porte l'icône : un groupe sans
		// icône n'a aucun chemin pour être atteint.
		$isOnBar = fn(string $section) => ($titles === NULL or isset($titles[$section]));

		$getTitle = fn(string $section) => isset($titles[$section]) ? '<div class="structure-tabs-universe">'.encode($titles[$section]).$creates[$section].'</div>' : '';

		// Le groupe nomme son univers en donnée autant qu'en classe, comme l'icône de l'univers qui porte les
		// deux : la classe est pour la feuille de style, `data-section` est ce que `changeSection()` lit.
		$getGroup = fn(string $section) => '<div data-section="'.$section.'" class="structure-tabs structure-section-'.$section.'">';

		if(
			$isOnBar('home') and
			$home !== NULL
		) {

			$h .= $getGroup('home');
				$h .= $getTitle('home');
				$h .= $this->getCloseSection();
				$h .= $home;
			$h .= '</div>';

		}

		if(
			$isOnBar('production') and
			isset($menus['production'])
		) {

			$h .= $getGroup('production');
				$h .= $getTitle('production');
				$h .= $this->getCloseSection();
				$h .= $this->getProductionSection($eStructure, $menus['production'], $nav, $subNav);
				$h .= $training;
			$h .= '</div>';

		}

		if(
			$isOnBar('commercialisation') and
			isset($menus['commercialisation'])
		) {

			$h .= $getGroup('commercialisation');
				$h .= $getTitle('commercialisation');
				$h .= $this->getCloseSection();
				$h .= $this->getCommercialisationSection($eStructure, $menus['commercialisation'], $nav, $subNav);
				$h .= $training;
			$h .= '</div>';

		}

		if(
			$isOnBar('accounting') and
			$eStructure->assertAccounting()
		) {

			$h .= $getGroup('accounting');
				$h .= $getTitle('accounting');
				$h .= $this->getCloseSection();
				$h .= $this->getAccountingSection($eStructure, $nav, $subNav);
			$h .= '</div>';

		}

		if($isOnBar('settings')) {

			$h .= $getGroup('settings');
				$h .= $getTitle('settings');
				$h .= $this->getCloseSection();
				$h .= $this->getSettingsSection($eStructure, $menus['settings'], $nav, $subNav);
			$h .= '</div>';

		}

		return $h;

	}

	protected function getProductionSection(Structure $eStructure, array $menus, ?string $nav, ?string $subNav): string {

		$h = $this->getJoin($eStructure, 'production');

		if(in_array('planning', $menus)) {

			$h .= '<div class="structure-tab-wrapper structure-nav-planning">';

				$h .= $this->getNav('planning', $nav, link: StructureUi::urlPlanning($eStructure));

				$h .= '<a class="structure-tab-complement" data-dropdown="bottom-left" data-dropdown-id="structure-tab-planning" data-dropdown-hover="true">';
					$h .= '<span id="structure-tab-planning-period">'.$this->getCategoryName($eStructure, 'planning', $eStructure->getView('viewPlanning')).'</span> '.\Asset::icon('chevron-down');
				$h .= '</a>';
				$h .= $this->getPlanningMenu($eStructure, subNav: $subNav);


			$h .= '</div>';

		}

		if(in_array('planning-v2', $menus)) {

			$h .= '<div class="structure-tab-wrapper structure-nav-planning-v2">';
				$h .= $this->getNav('planning-v2', $nav, link: \planning\TabUi::url($eStructure), label: s("Nouveau planning").' <span class="badge badge-outline">'.s("beta").'</span>');
			$h .= '</div>';

		}

		if(in_array('cultivation', $menus)) {

			$h .= '<div class="structure-tab-wrapper structure-nav-cultivation">';

				$h .= $this->getNav('cultivation', $nav);

				$h .= $this->getCultivationMenu($eStructure, nav: $nav, subNav: $subNav);

			$h .= '</div>';

		}

		if(in_array('intelligence-vegetable', $menus)) {

			$h .= '<div class="structure-tab-wrapper structure-nav-intelligence-vegetable">';

				$h .= $this->getIntelligenceTab(
					$eStructure,
					$nav,
					$subNav,
					'vegetable'
				);

			$h .= '</div>';

		}

		if(in_array('toolbox', $menus)) {
			$h .= $this->getToolboxTab($eStructure, $nav, $subNav);
		}

		if(in_array('analyze-production', $menus)) {

			$h .= '<div class="structure-tab-wrapper structure-nav-analyze-production">';

				$h .= $this->getAnalyzeTab(
					$eStructure,
					$nav,
					$subNav,
					'production'
				);

			$h .= '</div>';

		}

		return $h;

	}

	// Publique : #structure-nav n'est pas remplacé en navigation ajax (main/static/js/instruction.js), la Boîte à outils renvoie donc elle-même son onglet quand un favori change.
	public function getToolboxTab(Structure $eStructure, ?string $nav, ?string $subNav): string {

		$h = '<div class="structure-tab-wrapper structure-nav-toolbox">';

			$h .= $this->getNav('toolbox', $nav, link: \toolbox\ToolUi::url($eStructure));
			$h .= $this->getSubNav($eStructure, 'toolbox', $nav, $subNav);

		$h .= '</div>';

		return $h;

	}

	/**
	 * L'encart du menu : aujourd'hui les relevés de prix, hier l'appel à adhérer.
	 *
	 * Une seule place, et une seule chose à la fois : deux encarts empilés dans un
	 * menu ne se lisent plus, et celui qu'on montre doit être celui du moment.
	 * Le package qui annonce apporte son encart par l'événement `getMenuJoins`
	 * et décide s'il le sert.
	 *
	 * Le conteneur garde la classe `structure-nav-join` : c'est elle que la feuille
	 * de style interroge (`:has()`) pour recalculer la hauteur des sections du menu.
	 */
	protected function getJoin(Structure $eStructure, string $origin): string {
/*
		if(
			$origin === 'production' and
			$eStructure->acceptVegetable() and
			$eStructure->canManage()
		) {

			$h = '<div class="structure-tab-wrapper structure-nav-join">';

				$h .= '<a href="'.StructureUi::urlIntelligenceVariety($eStructure).'" class="structure-tab-wrapper structure-nav-join-block structure-nav-quotation">';

					$h .= '<div class="structure-nav-quotation-header">';
						$h .= '<span class="structure-nav-quotation-icon">'.\Asset::icon('leaf').'</span>';
						$h .= '<b>'.s("Ce que sèment les maraîchers").'</b>';
					$h .= '</div>';

					$h .= '<p>'.s("Donnez maintenant votre avis sur les variétés que vous utilisez, et retrouvez les variétés préférées de la communauté !").'</p>';

					$h .= '<span class="btn btn-primary btn-xs">'.s("Découvrir").'</span>';

				$h .= '</a>';

			$h .= '</div>';

			return $h;

		}
*/
		if(StructureSetting::hasCommercialisationApp() === FALSE) {
			return '';
		}

		return implode('', StructureUi::getObserverEntries('getMenuJoins', $eStructure));

	}

	protected function getCommercialisationSection(Structure $eStructure, array $menus, ?string $nav, ?string $subNav): string {

		$h = $this->getJoin($eStructure, 'commercialisation');

		if(in_array('selling', $menus)) {

			$h .= '<div class="structure-tab-wrapper structure-nav-selling">';

				$h .= $this->getNav('selling', $nav);
				$h .= $this->getSellingMenu($eStructure, nav: $nav, subNav: $subNav);

			$h .= '</div>';

		}

		if(in_array('communications', $menus)) {

			$h .= '<div class="structure-tab-wrapper structure-nav-communication">';

				$h .= $this->getNav('communications', $nav);
				$h .= $this->getCommunicationsMenu($eStructure, nav: $nav, subNav: $subNav);

			$h .= '</div>';

		}

		if(in_array('analyze-commercialisation', $menus)) {

			$h .= '<div class="structure-tab-wrapper structure-nav-analyze-commercialisation">';

				$h .= $this->getAnalyzeTab(
					$eStructure,
					$nav,
					$subNav,
					'commercialisation'
				);

			$h .= '</div>';

		}

		return $h;

	}

	protected function getAccountingSection(Structure $eStructure, ?string $nav, ?string $subNav): string {

		$h = '';

		if($eStructure->producerType() === Structure::TRAINING) {

			$h .= '<div class="structure-tab-wrapper structure-nav-training">';
				$h .= $this->getNav('training', $nav, link: '/training/farm:back?structure='.$eStructure['id']);
				$h .= $this->getNav('training-reset', $nav, link: '/training/farm:reset?structure='.$eStructure['id']);
			$h .= '</div>';

		}

		$canAccounting = $eStructure->canAccounting();

		if($canAccounting and $eStructure->acceptInvoicing()) {

			$h .= '<div class="structure-tab-wrapper structure-nav-invoicing">';

			$h .= $this->getInvoicingTitle($eStructure, $nav);

			if($eStructure->conf(Feature::class)['hasPdp']) {
				$h .= $this->getInvoicingMenu($eStructure, nav: $nav, subNav: $subNav);
			}

			$h .= '</div>';

		}

		if($eStructure->canProof()) {

			$h .= '<div class="structure-tab-wrapper structure-nav-proof">';

				$h .= $this->getNav('proof', $nav, link: StructureUi::urlProofs($eStructure));

			$h .= '</div>';

		}

		if($canAccounting) {

			$h .= '<div class="structure-tab-wrapper structure-nav-bank">';

				if($eStructure['hasEnableBankingAccessToRenew?']()) {
					$alert = '<span class="structure-tab-alert" title="'.s("La connexion à votre compte bancaire est à renouveler").'">'.\Asset::icon('exclamation-triangle-fill').'</span>';
				} else {
					$alert = NULL;
				}

				$h .= $this->getNav('bank', $nav, link: StructureUi::urlBankOperations($eStructure), complement: $alert);

			$h .= '</div>';

			$h .= '<div class="structure-tab-wrapper structure-nav-cash">';

				$h .= $this->getNav('cash', $nav, link: StructureUi::urlCash(eStructure: $eStructure));

			$h .= '</div>';

		}

		if($eStructure->canObserveAccounting()) {

			$h .= '<div class="structure-tab-wrapper structure-nav-preaccounting">';

				$h .= $this->getNav('preaccounting', $nav, link: StructureUi::urlConnected('/preaccounting/', $eStructure));

			$h .= '</div>';

		}


		$h .= '<div class="structure-tab-wrapper structure-tab-subnav structure-nav-accounting">';

			$h .= '<div class="structure-nav-accounting-title">';

				$h .= $this->getNav('accounting', $nav);

				$cFinancialYear = $eStructure['cFinancialYear?']();

				if($cFinancialYear->notEmpty()) {
					$financialYearViewed = $eStructure->getViewCached('viewAccountingYear');
					$cFinancialYearSelected = $cFinancialYear->find(fn($e) => $e['id'] === $financialYearViewed);
					if($cFinancialYearSelected->empty()) {
						$eFinancialYearSelected = $cFinancialYear->first();
					} else {
						$eFinancialYearSelected = $cFinancialYearSelected->first();
					}
					$h .= $this->getFinancialYearsTitle($eStructure, $cFinancialYear, $eFinancialYearSelected);
				} else {
					$eFinancialYearSelected = new \financialYear\FinancialYear();
				}

			$h .= '</div>';

			$eStructure['eFinancialYear'] = $eFinancialYearSelected;
			$h .= $this->getAccountingMenu($eStructure, nav: $nav, subNav: $subNav);

		$h .= '</div>';

		/*$h .= '<div class="structure-tab-wrapper structure-tab-subnav structure-nav-help">';

			$h .= '<a data-nav="help-accounting" class="structure-tab '.($nav === 'help-accounting' ? 'selected' : '').'" href="'.MenuUi::urlAccountingArticles($eStructure).'">';
				$h .= '<span class="structure-tab-icon">'.\Asset::image('main', 'svg/help-accounting.svg', ['style' => 'height: 1.5rem']).'</span>';
				$h .= ' <span class="structure-tab-label">'.s("Aide à la comptabilité").'</span>';
			$h .= '</a>';

		$h .= '</div>';*/

		return $h;

	}

	private function getInvoicingTitle(Structure $eStructure, ?string $nav): string {

		if($eStructure->acceptInvoicing() and $eStructure->conf(Feature::class)['hasPdp'] === FALSE) {

			$content = '<a data-nav="invoicing" class="structure-tab-invoicing structure-tab '.($nav === 'invoicing' ? 'selected' : '').'" href="'.StructureUi::urlInvoicingOnboarding($eStructure).'">';
				$content .= '<span class="structure-tab-icon">'.\Asset::image('main', 'facturation-electronique-logo.png', ['style' => 'height: 1.5rem']).'</span>';
				$content .= ' <span class="structure-tab-label">'.s("Facturation électronique").'</span>';
			$content .= '</a>';

			return $content;

		}

		$h = '<div data-nav="invoicing" class="structure-tab structure-tab-subnav '.($nav === 'invoicing' ? 'selected' : '').'">';
			$h .= '<span class="structure-tab-icon">'.\Asset::image('main', 'facturation-electronique-logo.png', ['style' => 'height: 1.4rem']).'</span>';
			$h .= '<span class="structure-tab-label structure-tab-invoicing">'.s("Facturation électronique").'</span>';
		$h .= '</div>';

		return $h;

	}

	protected function getSettingsSection(Structure $eStructure, array $menus, ?string $nav, ?string $subNav): string {

		$h = $this->getJoin($eStructure, 'accounting');

		if(in_array('structure', $menus)) {

			$h .= '<div class="structure-tab-wrapper structure-nav-structure">';

				$h .= $this->getNav('structure', $nav);
				$h .= $this->getStructureMenu($eStructure, nav: $nav, subNav: $subNav);

			$h .= '</div>';

		}

		if(in_array('team', $menus)) {

			$h .= '<div class="structure-tab-wrapper structure-nav-team">';

				$h .= $this->getNav('team', $nav, link: StructureUi::urlStructureTeam($eStructure), label: HeaderUi::getTeamCategories($eStructure)[Member::TEAM]);

			$h .= '</div>';

		}

		if($this->hasObserverCategory($eStructure, 'subscription')) {

			$h .= '<div class="structure-tab-wrapper structure-nav-subscription">';

				$h .= $this->getNav('subscription', $nav, link: $this->getObserverCategoryUrl($eStructure, 'subscription', 'home'));

			$h .= '</div>';

		}

		if(in_array('feature', $menus)) {

			$h .= '<div class="structure-tab-wrapper structure-nav-feature">';

				$h .= $this->getNav('feature', $nav);
				$h .= $this->getFeatureMenu($eStructure, nav: $nav, subNav: $subNav);

			$h .= '</div>';

		}

		if(in_array('data', $menus)) {

			$h .= '<div class="structure-tab-wrapper structure-nav-data">';

				$h .= $this->getNav('data', $nav);
				$h .= $this->getDataMenu($eStructure, nav: $nav, subNav: $subNav);

			$h .= '</div>';

		}

		return $h;

	}

	private function getFinancialYearsTitle(Structure $eStructure, \Collection $cFinancialYear, \financialYear\FinancialYear $eFinancialYearSelected): string {

		$cFinancialYearOpen = $cFinancialYear->find(fn($e) => $e->isClosed() === FALSE and $e->is($eFinancialYearSelected) === FALSE);
		$cFinancialYearClosed = $cFinancialYear->find(fn($e) => $e->isClosed() and $e->is($eFinancialYearSelected) === FALSE);

		$canAdd = (
			$eStructure->canManage() and
			$cFinancialYear->find(fn($e) => $e['status'] === \financialYear\FinancialYear::OPEN)->count() < \financialYear\FinancialYearSetting::MAX_FINANCIAL_YEAR_OPEN
		);

		$h = '<div id="structure-financial-years">';

			$h .= '<a data-dropdown="bottom-start" class="structure-tab structure-tab-subtitle">';
				$h .= '<span>';
					if($eFinancialYearSelected->isClosed()) {
						$h .= \Asset::icon('lock-fill').'  ';
					}
					$h .= s("Exercice {value}", $eFinancialYearSelected->getLabel()).'  '.\Asset::icon('chevron-expand');
				$h .= '</span>';
			$h .= '</a>';

			$h .= '<div class="dropdown-list">';

				if($cFinancialYearOpen->notEmpty() or $cFinancialYearClosed->notEmpty()) {

					$h .= '<div class="dropdown-title">'.s("Les exercices comptables").'</div>';

					foreach($cFinancialYearOpen as $eFinancialYear) {
						$url = self::replaceFinancialYear($eFinancialYear);
						$h .= '<a class="dropdown-item" href="'.$url.'" data-ajax-navigation="never">'.s("Exercice {value}", $eFinancialYear->getLabel()).'</a>';
					}

					if($cFinancialYearClosed->notEmpty()) {
						$h .= '<div class="dropdown-subtitle">'.s("Exercices clôturés").'</div>';
					}
					foreach($cFinancialYearClosed as $eFinancialYear) {
						$url = self::replaceFinancialYear($eFinancialYear);
						$h .= '<a class="dropdown-item" href="'.$url.'" data-ajax-navigation="never">'.s("Exercice {value}", $eFinancialYear->getLabel()).'</a>';
					}

					if($canAdd) {
						$h .= '<div class="dropdown-divider"></div>';
					}

				}

				if($canAdd) {
					$h .= '<a href="'.StructureUi::urlConnected('/financialYear/:create').'" class="dropdown-item">'.\Asset::icon('plus-circle').' '.s("Créer un exercice comptable").'</a> ';
				}
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	private function replaceFinancialYear(\financialYear\FinancialYear $eFinancialYear): string {

		$eStructure = Structure::getConnected();

		$isAccountingUrl = str_starts_with(LIME_REQUEST_PATH, '/'.$eStructure['id'].'/');

		if($isAccountingUrl) {
			$url = preg_replace('/\/exercice\/[0-9]+\//si', '/exercice/'.$eFinancialYear['id'].'/', LIME_REQUEST);
		} else {
			$url = StructureUi::urlJournal($eStructure, $eFinancialYear).'/livre-journal';
		}

		$url = \util\HttpUi::setArgument($url, 'financialYear', $eFinancialYear['id']);
		$url = \util\HttpUi::removeArgument($url, 'from');
		$url = \util\HttpUi::removeArgument($url, 'to');

		return $url;

	}

	protected function getCloseSection(): string {

		$h = '<a onclick="Structure.closeSection();" class="structure-tab-close">';
			$h .= '<span>'.\Asset::icon('x-lg').'</span>';
		$h .= '</a>';

		return $h;

	}

	public function getBreadcrumbs(Structure $eStructure, ?string $nav, ?string $subNav): string {

		if($nav === NULL) {
			return '';
		}

		$section = $this->getMenu($nav);

		$h = '<div id="structure-breadcrumbs-section">';
			if($subNav !== NULL) {
				$h .= $section['icon'];
				$h .= '  '.\Asset::icon('chevron-right').'  ';
			} else {
				$url = $this->getCategoryUrl($eStructure, $nav, '');
				$label = ($nav === 'team') ? HeaderUi::getTeamCategories($eStructure)[Member::TEAM] : $section['label'];
				$h .= $section['icon'].'<a href="'.$url.'" class="structure-breadcrumbs-link structure-breadcrumbs-section-link">'.$label.'</a>';
			}
		$h .= '</div>';

		if($subNav !== NULL) {
			$h .= '<div class="structure-breadcrumbs-categories" '.Attr('onrender', 'Structure.scrollBreadCrumbs(this, "'.$subNav.'")').'>';
				foreach($this->getCategories($eStructure, $nav) as $category) {
					$h .= '<a href="'.$this->getCategoryUrl($eStructure, $nav, $category).'" class="structure-breadcrumbs-link '.($subNav === $category ? 'selected' : '').'" data-sub-nav-menu="'.$nav.'" data-sub-nav="'.$category.'">'.$this->getCategoryName($eStructure, $nav, $category).'</a>';
				}
			$h .= '</div>';
		}

		return $h;

	}

	protected function getNav(string $section, ?string $nav, bool $disabled = FALSE, ?string $link = NULL, ?string $complement = NULL, ?string $label = NULL): string {

		$h = ($link ? '<a href="'.$link.'"' : '<div').' data-nav="'.$section.'" class="structure-tab '.($link === NULL ? 'structure-tab-subnav' : '').' '.($disabled ? 'disabled' : '').' '.($nav === $section ? 'selected' : '').'">';
		$h .= '<span class="structure-tab-icon">'.$this->getMenu($section)['icon'].'</span>';
		$h .= '<span class="structure-tab-label">'.($label ?? $this->getMenu($section)['label']).'</span>';
		$h .= '<span class="structure-tab-chevron">'.\Asset::icon('chevron-right').'</span>';
		if($complement !== NULL) {
			$h .= $complement;
		}
		$h .= '</'.($link ? 'a' : 'div').'>';

		return $h;

	}

	protected function getSubNav(Structure $eStructure, string $menu, ?string $nav, ?string $subNav): string {

		$h = '<div class="structure-subnav-wrapper">';

			foreach($this->getCategories($eStructure, $menu) as $name) {

				$h .= $this->getSubNavItem(
					$menu,
					$name,
					$this->getCategoryUrl($eStructure, $menu, $name),
					$this->getCategoryName($eStructure, $menu, $name),
					($menu === $nav and $name === $subNav),
					$this->getCategoryIcon($eStructure, $menu, $name)
				);

			}

		$h .= '</div>';

		return $h;

	}

	protected function getSubNavItem(string $menu, string $name, string $url, string $label, bool $selected, string $icon = '', string $classes = ''): string {

		$h = '<a href="'.$url.'" class="structure-subnav-item '.$classes.' '.($selected ? 'selected' : '').'" data-sub-nav-menu="'.$menu.'" data-sub-nav="'.$name.'">';
			$h .= '<span class="structure-subnav-prefix">'.$icon.'</span> ';
			$h .= '<span>'.$label.'</span>';
		$h .= '</a>';

		return $h;

	}

	protected function getIntelligenceTab(Structure $eStructure, ?string $nav, ?string $subNav, string $target): string {

		$h = '';

		if($this->getCategories($eStructure, 'intelligence-'.$target)) {

			$h .= $this->getNav('intelligence-'.$target, $nav);

			$h .= $this->getSubNav(
				$eStructure,
				'intelligence-'.$target,
				$nav,
				$subNav
			);

		} else {

			$h .= $this->getNav('intelligence-'.$target, $nav, disabled: TRUE);

		}

		return $h;

	}

	protected function getAnalyzeTab(Structure $eStructure, ?string $nav, ?string $subNav, string $section): string {

		$h = '';

		if($this->getCategories($eStructure, 'analyze-'.$section)) {

			$h .= $this->getNav('analyze-'.$section, $nav);

			$h .= $this->getSubNav(
				$eStructure,
				'analyze-'.$section,
				$nav,
				$subNav
			);

		} else {

			$h .= $this->getNav('analyze-'.$section, $nav, disabled: TRUE);

		}

		return $h;

	}

	public function getPlanningMenu(Structure $eStructure, ?string $subNav = NULL): string {

		$h = '<div data-dropdown-id="structure-tab-planning-list" class="dropdown-list bg-production">';

			foreach($this->getCategories($eStructure, 'planning') as $name) {

				$h .= '<a href="'.$this->getCategoryUrl($eStructure, 'planning', $name).'" id="structure-tab-planning-'.$name.'" class="dropdown-item '.($name === $subNav ? 'selected' : '').'">'.$this->getCategoryName($eStructure, 'planning', $name).'</a>';

			}

		$h .= '</div>';

		return $h;

	}

	public function getCultivationMenu(Structure $eStructure, ?string $nav = NULL, ?string $subNav = NULL): string {

		return $this->getSubNav(
			$eStructure,
			'cultivation',
			$nav,
			$subNav
		);

	}


	public function getInvoicingMenu(Structure $eStructure, ?string $nav = NULL, ?string $subNav = NULL): string {

		return $this->getSubNav(
			$eStructure,
			'invoicing',
			$nav,
			$subNav
		);

	}

	public function getAccountingMenu(Structure $eStructure, ?string $nav = NULL, ?string $subNav = NULL): string {

		return $this->getSubNav(
			$eStructure,
			'accounting',
			$nav,
			$subNav
		);

	}

	public function getSellingMenu(Structure $eStructure, ?int $season = NULL, string $prefix = '', ?string $nav = NULL, ?string $subNav = NULL): string {

		return $this->getSubNav(
			$eStructure,
			'selling',
			$nav,
			$subNav
		);

	}

	public function getCommunicationsMenu(Structure $eStructure, ?string $nav = NULL, ?string $subNav = NULL): string {

		return $this->getSubNav(
			$eStructure,
			'communications',
			$nav,
			$subNav
		);

	}

	public function getStructureMenu(Structure $eStructure, ?string $nav = NULL, ?string $subNav = NULL): string {

		return $this->getSubNav(
			$eStructure,
			'structure',
			$nav,
			$subNav
		);

	}

	public function getFeatureMenu(Structure $eStructure, ?string $nav = NULL, ?string $subNav = NULL): string {

		return $this->getSubNav(
			$eStructure,
			'feature',
			$nav,
			$subNav
		);

	}

	public function getDataMenu(Structure $eStructure, ?string $nav = NULL, ?string $subNav = NULL): string {

		return $this->getSubNav(
			$eStructure,
			'data',
			$nav,
			$subNav
		);

	}

	protected function getMenu($name): array {

		return match($name) {
			'training' => [
				'icon' => \Asset::icon('arrow-left'),
				'label' => s("Revenir à la formation")
			],
			'training-reset' => [
				'icon' => \Asset::icon('arrow-repeat'),
				'label' => s("Recommencer à zéro")
			],
			'planning' => [
				'icon' => \Asset::icon('calendar3'),
				'label' => s("Planning")
			],
			'planning-v2' => [
				'icon' => \Asset::icon('calendar-week'),
				'label' => s("Nouveau planning")
			],
			'cultivation' => [
				'icon' => \Asset::icon('journals'),
				'label' => s("Cultures")
			],
			'intelligence-vegetable' => [
				'icon' => \Asset::icon('stars'),
				'label' => s("Communauté")
			],
			'toolbox' => [
				'icon' => \Asset::icon('tools'),
				'label' => s("Boite à outils")
			],
			'analyze-production' => [
				'icon' => \Asset::icon('bar-chart'),
				'label' => s("Analyse")
			],
			'selling' => [
				'icon' => \Asset::icon('wallet'),
				'label' => s("Commercialisation")
			],
			'communications' => [
				'icon' => \Asset::icon('megaphone'),
				'label' => s("Communication")
			],
			'analyze-commercialisation' => [
				'icon' => \Asset::icon('bar-chart'),
				'label' => s("Analyse")
			],
			'proof' => [
				'icon' => \Asset::icon('paperclip'),
				'label' => s("Justificatifs")
			],
			'bank' => [
				'icon' => \Asset::icon('bank'),
				'label' => s("Banque")
			],
			'preaccounting' => [
				'icon' => \Asset::icon('file-spreadsheet'),
				'label' => s("Précomptabilité")
			],
			'cash' => [
				'icon' => \Asset::icon('journal-text'),
				'label' => s("Cahiers de caisse")
			],
			'invoicing' => [
				'icon' => \Asset::image('main', 'facturation-electronique-logo.png', ['style' => 'height: 1.5rem']),
				'label' => s("Facturation électronique"),
			],
			'accounting' => [
				'icon' => \Asset::icon('journal-bookmark'),
				'label' => s("Comptabilité")
			],
			'structure' => [
				'icon' => \Asset::icon('house-fill'),
				'label' => s("Général")
			],
			'data' => [
				'icon' => \Asset::icon('database'),
				'label' => s("Données")
			],
			'team' => [
				'icon' => \Asset::icon('people-fill'),
				'label' => s("Équipe")
			],
			'subscription' => [
				'icon' => \Asset::icon('basket3-fill'),
				'label' => s("Abonnement")
			],
			'feature' => [
				'icon' => \Asset::icon('feather'),
				'label' => s("Modules")
			],
			'game-map' => [
				'icon' => \Asset::icon('map'),
				'label' => s("Carte")
			],
			'game-ranking' => [
				'icon' => \Asset::icon('trophy'),
				'label' => s("Classements")
			],
		};

	}

	/**
	 * Les sous-entrées d'un onglet que le membre peut ouvrir. Celles de la comptabilité et de la
	 * facturation électronique se décident ici, `MenuLib`, du code ouvert, ne lisant pas la comptabilité.
	 */
	public function getCategories(Structure $eStructure, string $menu): array {

		switch($menu) {

			case 'accounting':

				// Le droit d'analyse ne donne que la synthèse : ni le livre journal, ni le grand
				// livre, ni la balance, ni les immobilisations, ni le démarrage
				if($eStructure->canAccounting() === FALSE) {
					return ['analyze'];
				}

				$categories = [];

				$eFinancialYear = $eStructure['eFinancialYear'] ?? new \financialYear\FinancialYear();

				if(
					$eStructure->hasAccounting() === FALSE
					or $eFinancialYear->empty()
					or (self::$moneyStepDoneByStructure[$eStructure['id']] ??= $eStructure['moneyStepDone?']()) === FALSE
				) {
					array_unshift($categories, 'start');
				}

				$categories[] = 'operations';

				if(
					$eStructure->canObserveAccounting() and
					$eFinancialYear->notEmpty() and
					$eFinancialYear->isClosed() === FALSE
				) {

					$categories[] = 'sale';

					if($eStructure->conf(Feature::class)['hasPdp']) {
						$categories[] = 'purchase';
					}

				}

				if($eFinancialYear->isCashReceipts() === FALSE) {
					$categories[] = 'book';
					$categories[] = 'balance';
				}

				$categories[] = 'assets';
				$categories[] = 'analyze';

				return $categories;

			case 'invoicing':
				return ['sell', 'buy', 'e-reporting'];

			default :
				return $eStructure['menuCategories?']($menu);

		};

	}


	public function findCategory(Structure $eStructure, string $path): array {

		$request = $this->getComparablePath($path, TRUE);
		$requestPage = $this->getComparablePath($path, FALSE);

		$categoryFound = ['accounting', 'analyze', $this->getMenu('accounting')['label']];
		$lengthFound = 0;

		foreach($this->getClosableCategories($eStructure) as [$section, $name]) {

			foreach($this->getCategoryPages($eStructure, $section, $name) as $position => ['url' => $url, 'title' => $title]) {

				$title ??= $this->getEntryName($eStructure, $section, $name);

				if($this->getComparablePath($url, TRUE) === $request) {
					return [$section, $name, $title];
				}

				$page = $this->getComparablePath($url, FALSE);

				// Une page rattachée à l'entrée qui nomme une action ne couvre que cette action : les autres actions de la même page peuvent relever d'une autre entrée.
				if($position > 0 and $page !== $this->getComparablePath($url, TRUE)) {
					continue;
				}

				if($section === 'bank') {
					$page = substr($page, 0, strrpos($page, '/'));
				}

				if(
					($requestPage === $page or str_starts_with($requestPage, $page.'/')) and
					strlen($page) > $lengthFound
				) {
					$categoryFound = [$section, $name, $title];
					$lengthFound = strlen($page);
				}

			}

		}

		return $categoryFound;

	}

	protected function getEntryName(Structure $eStructure, string $section, ?string $name): string {

		if($name === NULL) {
			return $this->getMenu($section)['label'];
		}

		return $this->getCategoryName($eStructure, $section, $name);

	}

	protected function getClosableCategories(Structure $eStructure): array {

		$sections = [];

		if($eStructure->canAccounting()) {

			if($eStructure->acceptInvoicing()) {
				$sections['invoicing'] = $eStructure->conf(Feature::class)['hasPdp'] ? $this->getCategories($eStructure, 'invoicing') : [NULL];
			}

			$sections['bank'] = [NULL];
			$sections['cash'] = [NULL];

		}

		if($eStructure->canProof()) {
			$sections['proof'] = [NULL];
		}

		if($eStructure->canObserveAccounting()) {
			$sections['preaccounting'] = [NULL];
		}

		$sections['accounting'] = $this->getCategories($eStructure, 'accounting');
		$sections['feature'] = $this->getCategories($eStructure, 'feature');
		if(in_array('data', $eStructure['menus?']()['settings'])) {
			$sections['data'] = $this->getCategories($eStructure, 'data');
		}

		$categories = [];

		foreach($sections as $section => $names) {
			foreach($names as $name) {
				$categories[] = [$section, $name];
			}
		}

		return $categories;

	}

	protected function getCategoryPages(Structure $eStructure, string $section, ?string $name): array {

		$urls = [$this->getCategoryUrl($eStructure, $section, $name ?? '')];

		if($section === 'accounting') {
			$urls = array_merge($urls, match($name) {
				'operations' => [
					StructureUi::urlConnected('/journal/operation/', $eStructure),
					StructureUi::urlConnected('/journal/pattern:createFromOperations', $eStructure)
				],
				'balance' => [\journal\ThirdPartyBalanceAiUi::url($eStructure)],
				'assets' => [StructureUi::urlConnected('/immobilisation/', $eStructure)],
				default => []
			});
		}

		$pages = array_map(fn(string $url) => ['url' => $url, 'title' => NULL], $urls);

		if($section === 'feature' and $name === 'settings-accounting') {

			$pagesSettings = \company\CompanyUi::getSettingsPages($eStructure);

			$pages = array_merge($pages, array_values($pagesSettings));
			$pages[] = ['url' => StructureUi::urlConnected('/journal/pattern:update', $eStructure), 'title' => $pagesSettings['pattern']['title']];

		}

		return $pages;

	}

	protected function getComparablePath(string $url, bool $withAction): string {

		// /<structure>/x est réécrit par nginx en /@/x?structure=<structure>, et sert donc la même page que /x?structure=<structure>.
		$path = preg_replace(['#/exercice/[0-9]+#', '#^/[0-9]+(?=/|$)#'], '', parse_url($url, PHP_URL_PATH));

		if($withAction === FALSE) {
			$path = preg_replace('#:[^/]*$#', '', $path);
		}

		return rtrim($path, '/');

	}

	protected function getCategoryUrl(Structure $eStructure, string $section, string $name): string {

		return match($section) {

			'planning' => match($name) {
				Member::DAILY => StructureUi::urlPlanningDaily($eStructure),
				Member::WEEKLY => StructureUi::urlPlanningWeekly($eStructure),
				Member::YEARLY => StructureUi::urlPlanningYear($eStructure)
			},
			'planning-v2' => \planning\TabUi::url($eStructure),

			'cultivation' => StructureUi::urlCultivation($eStructure, $name),
			'analyze-production' => StructureUi::urlAnalyzeProduction($eStructure, $name),
			'intelligence-vegetable' => StructureUi::urlIntelligenceVegetable($eStructure, $name),
			'toolbox' => ($name === '') ? \toolbox\ToolUi::url($eStructure) : \toolbox\ToolUi::urlTool($eStructure, $name),

			'selling' => StructureUi::urlSelling($eStructure, $name),
			'communications' => StructureUi::urlCommunications($eStructure, $name),
			'analyze-commercialisation' => StructureUi::urlAnalyzeCommercialisation($eStructure, $name),

			'proof' => StructureUi::urlProofs($eStructure),
			'bank' => StructureUi::urlBankOperations($eStructure),
			'preaccounting' => StructureUi::urlConnected('/preaccounting'),
			'cash' => StructureUi::urlCash(eStructure:  $eStructure),
			'accounting' => match($name) {
				'sale' => StructureUi::urlBase($eStructure).'/import/ventes:synthese',
				'purchase' => StructureUi::urlBase($eStructure).'/import/achats:synthese',
				'start' => ($eStructure->hasAccounting() ? '/comptabilite/parametrer' : '/comptabilite/decouvrir').'?structure='.$eStructure['id'],
				'operations' => StructureUi::urlJournal($eStructure).'/livre-journal',
				'book' => StructureUi::urlJournal($eStructure).'/grand-livre',
				'balance' => StructureUi::urlJournal($eStructure).'/balance',
				'assets' => StructureUi::urlAssets($eStructure),
				'analyze' => StructureUi::urlOverview($eStructure),
			},
			'invoicing' => match($name) {
				'buy' => StructureUi::urlInvoicingBuy($eStructure),
				'sell' => StructureUi::urlInvoicingSell($eStructure),
				'e-reporting' => StructureUi::urlInvoicingReporting($eStructure),
				default => StructureUi::urlInvoicingOnboarding($eStructure)
			},

			'structure' => match($name) {
				'general' => StructureUi::urlStructureGeneral($eStructure),
				'profile' => StructureUi::urlStructureProfile($eStructure),
				'team' => StructureUi::urlStructureTeam($eStructure),
				'vat' => StructureUi::urlStructureVat($eStructure),
				'features' => StructureUi::urlSettingsFeatures($eStructure),
			},

			'feature' => match($name) {
				'settings-production' => StructureUi::urlSettingsProduction($eStructure),
				'settings-commercialisation' => StructureUi::urlSettingsCommercialisation($eStructure),
				'settings-amap', 'settings-duty', 'settings-membership', 'settings-form', 'settings-remind' => $this->getObserverCategoryUrl($eStructure, $section, $name),
				'settings-accounting' => StructureUi::urlSettingsAccounting($eStructure),
				'settings-invoicing' => StructureUi::urlSettingsInvoicing($eStructure),
			},

			'data' => match($name) {
				'export' => StructureUi::urlDataExport($eStructure),
				'import' => StructureUi::urlDataImport($eStructure),
				'customers' => StructureUi::urlDataCustomers($eStructure),
				'api' => StructureUi::urlStructureApi($eStructure),
			},

			'team' => StructureUi::urlStructureTeam($eStructure),
			'subscription' => $this->getObserverCategoryUrl($eStructure, 'subscription', 'home'),

		};

	}

	// Seul l'observateur connait l'adresse de ces réglages, que `acceptFeatureSection('amap')` précède.
	protected function getObserverCategoryUrl(Structure $eStructure, string $section, string $name): string {
		return StructureUi::getObserverEntries('getCategoryUrls', $eStructure)[$section][$name];
	}

	// Une section que seul un package fermé sert n'existe que s'il en donne l'adresse.
	protected function hasObserverCategory(Structure $eStructure, string $section): bool {
		return array_key_exists($section, StructureUi::getObserverEntries('getCategoryUrls', $eStructure));
	}

	protected function getCategoryIcon(Structure $eStructure, string $section, string $name): string {

		return match($section) {
			'accounting' => match($name) {
				'sale' => \Asset::icon('chevron-right'),
				'purchase' => \Asset::icon('chevron-right'),
				default => '',
			},
			default => '',
		};

	}

	protected function hasThirdPartyBalance(Structure $eStructure): bool {

		$eFinancialYear = $eStructure['eFinancialYear'] ?? new \financialYear\FinancialYear();

		return ($eFinancialYear->notEmpty() and $eFinancialYear->isAccrualEnabled());

	}

	protected function getCategoryName(Structure $eStructure, string $section, string $name): string {

		return match($section) {

			'planning' => match($name) {
				Member::DAILY => s("Quotidien"),
				Member::WEEKLY => s("Hebdomadaire"),
				Member::YEARLY => s("Annuel")
			},

			'cultivation' => match($name) {
				'series' => s("Plan de culture"),
				'soil' => s("Plan d'assolement"),
				'seedling' => s("Semences et plants"),
				'forecast' => s("Prévisionnel financier"),
				'sequence' => s("Itinéraires techniques"),
				'map' => s("Plan de la ferme"),
			},

			'intelligence-vegetable' => match($name) {
				'intelligence-crop-plan' => s("Bibliothèque de cultures"),
				'intelligence-variety' => s("Variétés préférées")
			},

			'toolbox' => \toolbox\ToolUi::getName($name),

			'analyze-production' => match($name) {
				'working-time' => s("Temps de travail"),
				'cultivation' => s("Cultures")
			},

			'selling' => match($name) {
				'sale' => s("Ventes"),
				'invoice' => s("Factures"),
				'customer' => s("Clients"),
				'wallet' => s("Cagnottes"),
				'product' => s("Produits"),
				'catalog' => s("Catalogues"),
				'stock' => s("Stocks"),
				'shop' => s("Boutiques en ligne"),
				'amap' => s("AMAP"),
				'market' => s("Caisse enregistreuse"),
			},

			'communications' => match($name) {
				'website' => s("Site internet"),
				'mailing' => s("E-mailing")
			},

			'analyze-commercialisation' => match($name) {
				'sales' => s("Ventes"),
				'report' => s("Rapports"),
				'quotation' => s("Relevés de prix")
			},

			'accounting' => match($name) {
				'start' => s("Démarrer la comptabilité"),
				'operations' => s("Livre journal"),
				'sale' => '<span class="hide-xxs-down">'.s("Importer les ventes").'</span><span class="hide-xs-up">'.s("Import des ventes").'</span>',
				'purchase' => '<span class="hide-xxs-down">'.s("Importer les achats").'</span><span class="hide-xs-up">'.s("Import des achats").'</span>',
				'book' => s("Grand livre"),
				'balance' => $this->hasThirdPartyBalance($eStructure) ? s("Balances") : s("Balance"),
				'assets' => s("Immobilisations"),
				'analyze' => s("Synthèse"),
			},


			'invoicing' => match($name) {
				'sell' => s("Factures de vente"),
				'buy' => s("Factures d'achat"),
				'e-reporting' => s("E-reporting"),
			},

			'structure' => match($name) {
				'general' => StructureUi::getText($eStructure, 'settingsGeneral'),
				'team' => HeaderUi::getTeamCategories($eStructure)[Member::TEAM],
				'profile' => s("Profil"),
				'vat' => s("TVA"),
				'features' => s("Fonctionnalités"),
			},

			'feature' => match($name) {
				'settings-production' => s("Production"),
				'settings-amap' => s("Fonctionnalités de l'AMAP"),
				'settings-commercialisation' => s("Commercialisation"),
				'settings-duty' => s("Permanences"),
				'settings-membership' => s("Cotisation"),
				'settings-form' => s("Formulaire d'adhésion"),
				'settings-remind' => s("Rappels aux adhérents"),
				'settings-accounting' => s("Comptabilité"),
				'settings-invoicing' => s("Facturation électronique"),
			},

			'data' => match($name) {
				'export' => s("Exporter les données"),
				'import' => s("Importer des données"),
				'customers' => s("Adhérents"),
				'api' => s("Intégrations"),
			},

		};

	}

	/**
	 * Le nom et l'adresse d'une entrée de menu, nommée `<menu>` pour un onglet sans sous-entrée et
	 * `<menu>/<catégorie>` sinon.
	 *
	 * @return array ['label' => string, 'url' => string]
	 */
	public function getEntry(Structure $eStructure, string $entry): array {

		[$menu, $category] = array_pad(explode('/', $entry, 2), 2, NULL);

		return [
			'label' => $this->getEntryName($eStructure, $menu, $category),
			'url' => $this->getCategoryUrl($eStructure, $menu, $category ?? '')
		];

	}

}
?>
