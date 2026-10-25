<?php
class StructureTemplate extends RootTemplate implements DynamicTemplate {

	public string $template = 'structure ';

	public ?string $mainYear = NULL;

	public ?string $mainTitle = NULL;

	public ?string $nav = NULL;
	public ?string $subNav = NULL;
	public ?string $subNavTarget = NULL;

	/**
	 * L'entrée « Du nouveau » du groupe Bureau paraît-elle ? Seuls les écrans qui ont lu les nouvelles
	 * le posent (`dashboard.v.php`, `preference.v.php`). Ailleurs, le groupe les demande au premier
	 * passage sur l'univers (`dashboard\PilotageUi`).
	 */
	public ?bool $news = NULL;

	/**
	 * La météo du groupe Bureau (L52), telle que `DashboardLib::loadHome()` la rend. Même régime que
	 * `$news` : seuls l'accueil et son réglage la lisent, et ailleurs le groupe la demande au premier
	 * passage sur l'univers, la prévision coûtant des requêtes que tous les écrans paieraient.
	 */
	public ?array $home = NULL;

	/**
	 * L'entrée choisie dans le groupe Bureau par un écran qui y vit, `dashboard` pour l'accueil et son
	 * réglage, `note` (L47) ou `news` (L50) : le groupe reste affiché et l'entrée choisie. Elle se nomme
	 * comme `$nav` et `$subNav`, le groupe étant la section `home` du gabarit.
	 */
	public ?string $homeNav = NULL;

	/**
	 * Le champ de saisie est-il rendu par l'écran plutôt que par le menu ? L'accueil le pose (S6) : le
	 * champ y prend la tête de la page (`getEntryInPage()`), et la colonne n'en garde que l'emplacement,
	 * pour qu'il n'y ait jamais deux `#entry-omnibox` dans la page.
	 */
	public bool $entryInPage = FALSE;

	private ?array $gestures = NULL;

	public function __construct() {

		parent::__construct();

		\Asset::css('main', 'template-structure.css');

		self::buildAssets();

	}
	public function build(Closure $callback): mixed {

		// Un groupe et une AMAP ont leur gabarit (`CommunityTemplate`, `AmapTemplate`) : une route qui les
		// rendrait ici leur montrerait les menus d'une ferme.
		switch($this->data->eStructure['type']) {

			case \structure\Structure::COMMUNITY :
				throw new NotExpectedAction('Unexpected community');

			case \structure\Structure::AMAP :
				throw new NotExpectedAction('Unexpected AMAP');

		}

		return parent::build($callback);

	}

	protected function buildAjax(string $stream): AjaxTemplate {

		$this->buildSections();

		// Le champ de saisie change de place avec l'écran, et l'en-tête n'est pas réécrit tant que la
		// structure reste la même (`updateHeader`, `main/static/js/instruction.js`). L'instruction posée ici
		// part avant celle qui écrit `main` (`copyInstructions()`, `RootTemplate::buildAjax()`) : le champ du
		// menu cède sa place avant que celui de l'écran arrive.
		if($this->entryInPage and $this->hasEntry()) {
			$this->qs('#entry-menu')->outerHtml(self::getEntryPlace());
		}

		$t = parent::buildAjax($stream);
		$t->qs('body')->removeAttribute('data-section');

		// En repartant de l'écran qui le rendait, le champ revient au menu, après que `main` a emporté
		// celui de l'écran. L'emplacement n'existe plus quand le champ y est déjà, et sa frappe reste alors
		// d'un écran à l'autre.
		if($this->entryInPage === FALSE and $this->hasEntry()) {
			$t->qs('#entry-menu-place')->outerHtml($this->getStructureEntry());
		}

		// Le `<body>` n'est pas remplacé en navigation ajax : un panneau laissé ouvert survivrait à la
		// navigation et s'afficherait par dessus l'écran d'arrivée.
		$t->qs('body')->removeAttribute('data-panel');

		// `#structure-nav` n'est pas remplacé non plus tant que la structure reste la même (`updateHeader`,
		// `main/static/js/instruction.js`), le groupe Bureau pas davantage : ce qu'il a lu vaut jusqu'au
		// rechargement, et seule l'entrée choisie suit l'écran.
		if($this->hasHome()) {

			// Un écran qui a lu les nouvelles confirme l'entrée qu'un écran hors du groupe Bureau a laissée avec
			// son adresse, ou la retire. Le sélecteur ne vise que l'entrée encore à confirmer, l'entrée déjà
			// confirmée restant.
			if($this->news !== NULL) {

				$node = $t->qs('#'.\dashboard\PilotageUi::getNewsId().'[data-url]');

				if($this->news) {
					$node->outerHtml(new \dashboard\PilotageUi()->getNews($this->data->eStructure, FALSE));
				} else {
					$node->remove();
				}

			}

			// La météo, au même régime : l'emplacement seul est visé, et ce que le groupe porte déjà reste
			// jusqu'au rechargement.
			if($this->home !== NULL) {
				$t->qs('#'.\dashboard\PilotageUi::getHomeId().'[data-url]')->outerHtml(new \dashboard\PilotageUi()->getHome($this->data->eStructure, $this->home));
			}

			$t->qsa('#structure-pilotage [data-home-nav].selected')->removeClass('selected');

			if($this->homeNav !== NULL) {
				$t->qs('#structure-pilotage [data-home-nav="'.$this->homeNav.'"]')->addClass('selected');
			}

		}

		return $t;

	}

	protected function buildHtml(string $stream): string {

		$this->buildSections();

		return parent::buildHtml($stream);

	}

	protected function buildAjaxHeader(AjaxTemplate $t): void {

		$t->package('main')->updateHeader(
			$this->nav,
			$this->subNav,
			$this->subNavTarget,
			$this->getStructureSections().$this->getStructureEntry(),
			// Le pied fait partie de l'en-tête : quand il est réécrit en entier, il le reçoit comme `getHeader()`.
			$this->getStructureNav().$this->getNavFoot(),
			new \structure\MenuUi()->getBreadcrumbs($this->data->eStructure, $this->nav, $this->subNav),
			$this->data->eStructure['id'],
		);

	}

	/**
	 * L'onglet Bureau n'est posé que là où l'accueil est le point d'arrivée : une AMAP ou une structure
	 * sans Pilotage a la navigation sans lui.
	 */
	protected function hasHome(): bool {
		return \dashboard\DashboardSetting::isLanding($this->data->eStructure);
	}

	/**
	 * Les univers ouverts à cette structure pour ce membre, sortis du rendu : les onglets et la barre
	 * du téléphone les lisent tous deux ici. Deux listes construites séparément divergeraient.
	 */
	protected function getSections(): array {

		$eStructure = $this->data->eStructure;

		$sections = [];

		if($eStructure->assertProduction()) {
			$sections['production'] = [\Asset::icon('leaf'), s("Production")];
		}

		if($eStructure->assertCommercialisation()) {
			$sections['commercialisation'] = [\Asset::icon('basket3'), s("Vente")];
		}

		if($eStructure->assertAccounting()) {
			$sections['accounting'] = [\Asset::icon('piggy-bank'), s("Gestion")];
		}

		if($eStructure->canManage() or $eStructure->canManageAccounting()) {
			$sections['settings'] = [\Asset::icon('sliders'), s("Paramètres")];
		}

		return $sections;

	}

	/**
	 * Les univers que portent les jetons de la colonne et de la tablette. Paramètres n'en est pas un : il
	 * se pose au pied de la colonne et, à la tablette, au bout de la rangée des univers, sans jeton
	 * (`getNavSettings()`), et reste dans `getSections()`, qui nomme aussi ses onglets et son icône dans
	 * la barre du téléphone.
	 */
	protected function getUniverseSections(): array {
		return array_diff_key($this->getSections(), ['settings' => TRUE]);
	}

	/**
	 * Le nombre d'icônes que la liste des univers porte. Bureau et `FEATURE_GAME` en ajoutent une au
	 * rendu sans entrer dans `getSections()` : le compte vit donc ici et non chez chaque appelant, sinon la classe
	 * de la liste désigne une autre largeur que celle de son contenu.
	 */
	protected function getSectionsCount(): int {

		$nSections = count($this->getUniverseSections());

		if($this->hasHome()) {
			$nSections++;
		}

		if(FEATURE_GAME) {
			$nSections++;
		}

		return $nSections;

	}

	protected function getStructureSections(): string {

		// La structure que l'en-tête porte, que `updateHeader` compare à celle de l'écran d'arrivée.
		$h = '<div id="structure-nav-sections" class="structure-nav-sections-'.$this->getSectionsCount().'" data-structure="'.$this->data->eStructure['id'].'">';

			if($this->hasHome()) {
				$h .= $this->getHome();
			}

			foreach($this->getUniverseSections() as $name => [$icon, $label]) {

				$h .= '<a '.$this->getSectionGestures().' data-section="'.$name.'" class="structure-nav-section structure-nav-section-'.$name.'">';
					$h .= '<span class="structure-nav-section-icon">';
						$h .= $icon;
					$h .= '</span>';
					$h .= '<span class="structure-nav-section-label">'.$label.'</span>';
					$h .= '<span class="structure-nav-section-down">'.Asset::icon('chevron-down').'</span>';
					$h .= '<span class="structure-nav-section-up">'.Asset::icon('chevron-up').'</span>';
				$h .= '</a>';

			}

			// Paramètres ferme la rangée à la tablette. Au bureau, il est au pied de la colonne, et le
			// téléphone retire la rangée pour sa barre du bas (`template-structure.css`).
			$h .= $this->getNavSettings();

			if(FEATURE_GAME) {

				$h .= '<a href="/jouer" data-ajax-navigation="never" class="structure-nav-section structure-nav-section-game">';
					$h .= '<span class="structure-nav-section-icon">🎅</span>';
					$h .= '<span class="structure-nav-section-label">'.s("Jouer").'</span>';
				$h .= '</a>';
			}

		$h .= '</div>';
		
		return $h;
		
	}

	protected function getSectionGestures(): string {
		return attr('onclick', 'Structure.changeSection(this, "click")').' '.attr('onmouseenter', 'Structure.changeSection(this, "mouseenter", 200)').' onmouseleave="Structure.clearSection(this)"';
	}

	/**
	 * Les onglets de la structure, qui sont aussi le panneau du téléphone : la barre d'univers en bas de
	 * l'écran, la bande des entrées de l'univers au-dessus, et le panneau qui s'ouvre en feuille. C'est
	 * la largeur de l'écran qui choisit (`template-structure.css`).
	 */
	protected function getStructureNav(): string {

		$gestures = $this->hasEntry() ? [] : $this->getGestures();

		$titles = [];
		$creates = [];

		foreach($this->getSections() as $section => [, $label]) {

			$titles[$section] = $label;
			$creates[$section] = array_key_exists($section, $gestures) ? $this->getTabsCreate([$section => $gestures[$section]]) : '';

		}

		if($this->hasHome()) {

			$titles = ['home' => s("Bureau")] + $titles;
			$creates['home'] = '';

			$home = new \dashboard\PilotageUi()->get($this->data->eStructure, $this->news, $this->home, $this->homeNav);

		} else {
			$home = NULL;
		}

		return new \structure\MenuUi()->getMainTabs($this->data->eStructure, $this->nav, $this->subNav, $titles, $creates, $home);

	}

	protected function getHeader(): string {

		$h = $this->getStructureSections();
		$h .= $this->getStructureEntry();
		$h .= $this->getStructureNav();
		$h .= $this->getNavFoot();

		return $h;

	}

	protected function getFooterUsageLinks(): array {

		$links = parent::getFooterUsageLinks();
		$links[] = ['title' => s("Données des producteurs"), 'href' => self::getSiteUrl('/presentation/data')];

		return $links;

	}

	protected function getFooter() {

		$h = '';

		if($this->footer === NULL) {
			$h .= $this->getDefaultFooter();
		} else {
			$h .= $this->footer;
		}

		$h .= parent::getFooter();

		return $h;

	}

	protected function getMain(string $stream):string {

		if($this->main) {

			$h = $this->getMainTitle();
			$h .= $this->main;
			$h .= $this->getInterstitial();

			return $h;

		} else {

			$h = $this->getMainTitle();

			// L'astuce ouvre le contenu, dans le container qui lui donne ses marges.
			$tip = $this->data->tip ? new \structure\TipUi()->getTip($this->data->eStructure, $this->data->tip) : '';

			if($this->mainContainer) {
				$h .= '<div class="container structure-template-main-container">'.$tip.$stream.'</div>';
			} else {
				$h .= $tip.$stream;
			}

			$h .= $this->getInterstitial();

			return $h;

		}

	}

	/**
	 * L'interstitiel demandé par l'URL, s'il y en a un.
	 *
	 * Ici plutôt que dans chaque écran : n'importe quelle page de ferme peut en
	 * porter un, il suffit que le lien qui y mène le demande. Le contenu arrive par
	 * une seconde requête, pour que la page s'affiche d'abord et la modale par
	 * dessus.
	 */
	protected function getInterstitial(): string {

		$name = \structure\InterstitialLib::getRequested($this->data->eStructure);

		if($name === NULL) {
			return '';
		}

		$url = \structure\StructureUi::urlInterstitial($this->data->eStructure, $name);

		// Le paramètre disparaît de la barre d'adresse avant même le chargement : il a
		// été consommé, et le laisser rejouerait l'interstitiel au moindre
		// rafraîchissement ou au retour arrière depuis la modale. `replaceState` plutôt
		// qu'une redirection : la page est déjà là, et la recharger pour une histoire
		// d'URL se verrait.
		$request = \util\HttpUi::removeArgument(LIME_REQUEST, \structure\InterstitialLib::PARAMETER);

		$h = '<div onrender="';
			$h .= 'history.replaceState(history.state, \'\', \''.$request.'\'); ';
			$h .= 'new Ajax.Navigation(this).url(\''.$url.'\').fetch()';
		$h .= '"></div>';

		return $h;

	}

	protected function getMainTitle():string {

		if($this->mainTitle) {

			$h = '<div class="container structure-template-main-title '.($this->mainYear ? 'structure-template-main-title-with-year' : '').'">';
				if($this->mainYear !== NULL) {
					$h .= '<div class="structure-template-main-year">'.$this->mainYear.'</div>';
				}
				$h .= '<div class="structure-template-main-content"><div>'.$this->mainTitle.'</div></div>';
			$h .= '</div>';

			return $h;

		} else {
			return '';
		}

	}

	protected function getNav(): string {

		$h = '<div class="structure-nav-wrapper">';
		
			$h .= '<div class="structure-nav-title">';
	
				if(OTF_DEMO) {
					$h .= '<a href="'.\structure\StructureUi::urlPlanning($this->data->eStructure).'">'.encode($this->data->eStructure['name']).'</a>';
					$h .= '&nbsp;&nbsp;<a href="'.Lime::getUrl().'" class="btn btn-primary">'.Asset::icon('escape').' '.s("Quitter la démo").'</a>';
				} else {
	
					$canUpdate = (
						$this->data->eStructure->canManage() or
						$this->data->eStructure->canPersonalData()
					);
	
					// Le nom ouvre la liste des structures du compte, recherche comprise.
					$switch = $this->hasSelector();

					if($switch) {
						$h .= '<a class="structure-nav-identity structure-nav-switch" '.$this->getSelectorTrigger().'>';
					} else {
						$h .= '<div class="structure-nav-identity">';
					}

						// Un logo réel ou rien : un carré vide décalerait le nom de l'axe de la colonne.
						if(
							$this->data->eStructure->hasMembershipAdvantages() and
							$this->data->eStructure['logo'] !== NULL
						) {
							$h .= '<div>'.\structure\StructureUi::getLogo($this->data->eStructure, '2rem').'</div>';
						}
	
						if($canUpdate) {
	
							$h .= '<div>';
								if($this->data->eStructure->isMembership()) {
									$h .= Asset::icon('star-fill').'  ';
								}
								$h .= '<span class="structure-nav-name">'.encode($this->data->eStructure['name']).'</span>';
								if($switch) {
									$h .= $this->getSelectorIcon();
								}
							$h .= '</div>';
	
						} else {
							$h .= '<span>';
								if($this->data->eStructure->isMembership()) {
									$h .= Asset::icon('star-fill').'  ';
								}
								$h .= '<span class="structure-nav-name">'.encode($this->data->eStructure['name']).'</span>';
								if($switch) {
									$h .= $this->getSelectorIcon();
								}
							$h .= '</span>';
						}

					if($switch) {
						$h .= '</a>';
					} else {
						$h .= '</div>';
					}

				}

			$h .= '</div>';

			// Hors du nom : dans la colonne, la marque prend une rangée à elle sous le nom et la cloche,
			// seule largeur où elle tienne sans couper le nom (`template-structure.css`).
			if(OTF_DEMO === FALSE and $canUpdate) {

				if($this->data->eStructure->isShop()) {
					$h .= '<div class="structure-nav-title-type"><span class="badge badge-outline">'.s("Groupement de producteurs").'</span></div>';
				}

				if($this->data->eStructure->producerType() === \structure\Structure::TRAINING) {
					$producerType = \structure\StructureUi::p('producerType');
					$h .= '<div class="structure-nav-title-type"><span class="badge badge-outline">'.$producerType->displayIcons[\structure\Structure::TRAINING].'  '.$producerType->displayValues[\structure\Structure::TRAINING].'</span></div>';
				}

			}

			$h .= '<div class="structure-nav-actions">';
				$h .= $this->getNavActions();
			$h .= '</div>';

		$h .= '</div>';

		$h .= $this->getBar();

		if($this->hasSelector()) {
			$h .= $this->getSelector();
		}

		return $h;

	}

	/**
	 * La barre du haut : la cloche et le compte. La colonne n'y garde que la cloche. Paramètres n'y est
	 * pas : la tablette le range au bout des univers (`getStructureSections()`), la colonne à son pied et
	 * le téléphone dans sa barre du bas.
	 *
	 * ⚠️ La cloche ne se rend qu'ici : elle porte un identifiant unique que les vues des notifications
	 * adressent au premier nœud trouvé, donc un second exemplaire garderait un compteur faux après
	 * lecture. Le compte n'en porte pas, et se rend aussi au pied de la colonne (`getNavFoot()`), chaque
	 * exemplaire masqué à la largeur où l'autre paraît : le pied vit dans `<header>` et la barre du haut
	 * dans `<nav>`, deux zones de la grille qu'un même nœud ne traverserait qu'en position fixe.
	 */
	protected function getNavActions(): string {

		$h = '';

		if(
			$this->data->isLogged and
			\notification\NotificationSetting::isEnabled($this->data->eStructure) and
			array_key_exists($this->data->eStructure['id'], $this->data->notificationsUnseen)
		) {
			$h .= new \notification\NotificationUi()->getBell($this->data->eStructure, $this->data->notificationsUnseen[$this->data->eStructure['id']]);
		}

		$h .= $this->getNavAccount('bottom');

		return $h;

	}

	/**
	 * Le pied de la colonne : Paramètres, puis le compte calé à droite.
	 */
	protected function getNavFoot(): string {

		$h = '<div class="structure-nav-foot">';
			$h .= $this->getNavSettings(\Asset::icon('chevron-up'));
			$h .= $this->getNavAccount('top');
		$h .= '</div>';

		return $h;

	}

	/**
	 * Paramètres, écrit à côté de son icône : sans jeton, il doit se lire seul. Le geste est celui d'un
	 * univers, qui ouvre ses onglets sans quitter l'écran. `$chevron` dit de quel côté : au pied de la
	 * colonne ils s'ouvrent au-dessus de lui, au bout de la rangée des univers le déroulant le dit déjà.
	 */
	protected function getNavSettings(string $chevron = ''): string {

		$sections = $this->getSections();

		if(array_key_exists('settings', $sections) === FALSE) {
			return '';
		}

		[$icon, $label] = $sections['settings'];

		$h = '<a '.$this->getSectionGestures().' data-section="settings" class="structure-nav-settings">';
			$h .= $icon;
			$h .= '<span>'.$label.'</span>';
			$h .= $chevron;
		$h .= '</a>';

		return $h;

	}

	/**
	 * Le compte et son menu, ou l'inscription et la connexion. `$dropdown` place le menu : sous la barre
	 * du haut, au-dessus du pied de la colonne.
	 */
	protected function getNavAccount(string $dropdown): string {

		if($this->data->isLogged) {

			$h = '<div class="nav-account structure-nav-account">';

				$h .= '<a class="nav-user nav-item" data-dropdown="'.$dropdown.'" data-dropdown-hover="true">';
					$h .= \user\UserUi::getVignette($this->data->eUserOnline, '1.75rem').'  ';
					$h .= \Asset::icon(match($dropdown) {
						'top' => 'chevron-up',
						'bottom' => 'chevron-down',
					});
				$h .= '</a>';

				$h .= $this->getUserMenu($this->data);

			$h .= '</div>';

		} else {

			$h = '<div class="structure-nav-account">';
				$h .= '<a href="'.Lime::getUrl().'/inscription" class="nav-item">'.s("Inscription").'</a>';
			$h .= '</div>';

			$h .= '<div class="structure-nav-account">';
				$h .= '<a href="/user/log:form" class="nav-item">'.s("Connexion").'</a>';
			$h .= '</div>';

		}

		return $h;

	}

	/**
	 * La barre d'univers du téléphone, en bas de l'écran : Bureau, les univers et Paramètres, dont
	 * un toucher ouvre le panneau en feuille. Ni le nom de la structure ni le compte : la barre du haut
	 * les porte déjà, et la cloche porte un identifiant qui ne se rend qu'une fois (`getNavActions()`).
	 * Paramètres y reste une icône parmi les univers, et la barre du haut le masque à cette largeur.
	 */
	protected function getBar(): string {

		$h = '<div class="structure-bar">';

			if($this->hasHome()) {
				$h .= $this->getHome(inBar: TRUE);
			}

			foreach($this->getSections() as $name => [$icon, $label]) {
				$h .= '<a '.$this->getSectionGestures().' data-section="'.$name.'" class="structure-nav-section structure-nav-section-'.$name.'">';
					$h .= '<span class="structure-nav-section-icon">'.$icon.'</span>';
					$h .= '<span class="structure-nav-section-label">'.$label.'</span>';
				$h .= '</a>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le jeton ouvre le groupe Bureau comme les autres univers (« Pilotage » jusqu'à L52, renommé par
	 * le 2026-10-17), et ne mène nulle part de lui-même : le tableau de bord se rejoint par son
	 * entrée dans le groupe, comme les notes et « Du nouveau » (« Supprime le clic direct sur le menu
	 * Bureau », 2026-10-19, qui revient sur L52).
	 */
	protected function getHome(bool $inBar = FALSE): string {

		$h = '<a '.$this->getSectionGestures().' data-section="home" class="structure-nav-section structure-nav-section-home">';
			$h .= '<span class="structure-nav-section-icon">'.\Asset::icon('cup-hot').'</span>';
			$h .= '<span class="structure-nav-section-label">'.s("Bureau").'</span>';

			if($inBar === FALSE) {
				$h .= '<span class="structure-nav-section-down">'.Asset::icon('chevron-down').'</span>';
				$h .= '<span class="structure-nav-section-up">'.Asset::icon('chevron-up').'</span>';
			}

		$h .= '</a>';

		return $h;

	}

	/**
	 * Le champ de saisie prend la place du « + » en tête du menu (`EntryUi::getMenu()`), pour les
	 * structures du drapeau `FEATURE_ENTRY` et les membres qui peuvent écrire au moins une de ses
	 * intentions, le droit de sa page (`entry.p.php`). Vide, il propose les mêmes gestes.
	 */
	protected function hasEntry(): bool {
		return (\entry\EntrySetting::isEnabled($this->data->eStructure) and \entry\EntryLib::getAllowed($this->data->eStructure) !== []);
	}

	protected function getStructureEntry(): string {

		if($this->hasEntry() === FALSE) {
			return '';
		}

		if($this->entryInPage) {
			return self::getEntryPlace();
		}

		return $this->getEntry('menu');

	}

	/**
	 * Le champ de saisie, pour l'écran qui le rend lui-même (`$entryInPage`) : ses gestes sont ceux du
	 * menu, que seul le gabarit connaît. Vide quand le membre n'a pas le champ.
	 */
	public function getEntryInPage(): string {

		if($this->hasEntry() === FALSE) {
			return '';
		}

		return $this->getEntry('dashboard');

	}

	protected function getEntry(string $origin): string {

		$labels = array_map(fn(array $section) => $section[1], $this->getSections());

		return new \entry\EntryUi()->getMenu($this->data->eStructure, $this->getGestures(), $labels, $origin);

	}

	protected static function getEntryPlace(): string {
		return '<div id="entry-menu-place"></div>';
	}

	protected function getTabsCreate(array $gestures): string {

		$h = '<a class="structure-tabs-create" data-dropdown="bottom-start" '.attr('title', s("Saisir")).'>'.\Asset::icon('plus-lg').'</a>';

		$h .= '<div class="dropdown-list dropdown-list-light">';
			$h .= $this->getCreateList($gestures);
		$h .= '</div>';

		return $h;

	}

	protected function getCreateList(array $gestures): string {

		$sections = $this->getSections();

		$h = '<div class="dropdown-title">'.s("Saisir").'</div>';

		foreach($gestures as $section => $entries) {

			if(count($gestures) > 1) {
				$h .= '<div class="dropdown-subtitle">'.$sections[$section][1].'</div>';
			}

			foreach($entries as [$icon, $label, $url]) {
				$h .= '<a href="'.$url.'" class="dropdown-item dropdown-item-icon">'.\Asset::icon($icon).'<span>'.$label.'</span></a>';
			}

		}

		return $h;

	}

	protected function getGestures(): array {

		if($this->gestures !== NULL) {
			return $this->gestures;
		}

		$eStructure = $this->data->eStructure;
		$sections = $this->getSections();

		$gestures = [];

		if(
			array_key_exists('production', $sections) and
			new \series\Task(['structure' => $eStructure])->assertCreate()
		) {

			$season = $eStructure->getView('viewSeason') ?? $eStructure->conf(\map\Configuration::class)['seasonLast'];

			// Le quatrième élément nomme l'intention du champ de saisie qui fait le geste : le champ la pose au
			// lieu d'ouvrir l'adresse (`EntryUi::getMenu()`), que le « + » garde.
			$gestures['production'] = [
				['calendar-plus', s("Une intervention"), '/series/task:createFromSeries?structure='.$eStructure['id'].'&season='.$season, 'intervention'],
			];

			if(\planning\HarvestWeekLib::canHarvest($eStructure)) {
				$gestures['production'][] = ['basket2', s("Une récolte"), \planning\HarvestWeekUi::urlCreate($eStructure, \Clock::week(), origin: 'menu'), 'harvest'];
			}

		}

		// L'univers de la commercialisation n'existe que sous `acceptCommercialisation()`, qui porte le
		// prédicat : ses gestes viennent d'un package fermé.
		if(array_key_exists('commercialisation', $sections)) {

			$gesturesSection = \structure\StructureUi::getObserverEntries('getCommercialisationGestures', $eStructure);

			if($gesturesSection !== []) {
				$gestures['commercialisation'] = $gesturesSection;
			}

		}

		// La comptabilité porte ses gestes, et `canManageAccounting()`, le droit d'écrire, le prédicat de
		// son univers.
		if(
			array_key_exists('accounting', $sections) and
			$eStructure->canManageAccounting()
		) {

			$gesturesSection = \structure\StructureUi::getObserverEntries('getAccountingGestures', $eStructure);

			if($gesturesSection !== []) {
				$gestures['accounting'] = $gesturesSection;
			}

		}

		$this->gestures = $gestures;

		return $gestures;

	}

	protected function hasSelector(): bool {
		return ($this->data->nStructureUser > 1);
	}

	/**
	 * Le nom de la structure dans la barre du haut ouvre la liste, chargée à l'ouverture dans une
	 * coquille rendue à part : `Lime.Dropdown` la retrouve par son identifiant, où qu'elle soit dans la
	 * page. Pas de `data-dropdown` : c'est `structure.js` qui ouvre,
	 * une fois la liste arrivée, parce que `Lime.Dropdown` la place d'après sa taille à l'ouverture.
	 */
	protected function getSelectorTrigger(): string {
		return attr('data-structure-selector', \structure\SelectorAiUi::url($this->data->eStructure)).' '.attr('data-structure-sections', implode(' ', \dashboard\DashboardSetting::SECTIONS)).' data-dropdown-id="structure-selector"';
	}

	protected function getSelectorIcon(): string {
		return '<span class="structure-nav-switch-icon">'.\Asset::icon('chevron-down').'</span>';
	}

	protected function getSelector(): string {
		return '<div class="dropdown-list dropdown-list-unstyled dropdown-list-light structure-selector" data-dropdown-id="structure-selector-list"></div>';
	}

	/**
	 * ⚠️ Les jetons du gabarit se posent ici et non dans une méthode de rendu : `RootTemplate::buildAjax()`
	 * lit `getTemplate()` avant d'appeler `getNav()`, quand `buildHtml()` fait l'inverse. Un jeton posé
	 * au rendu serait donc présent au rechargement et absent après une navigation ajax.
	 */
	protected function buildSections(): void {

		// Le champ du menu prend une hauteur dans l'en-tête fixe du téléphone (`--structure-entry`). Rendu
		// par l'écran, il est dans le flux et n'a rien à réserver.
		if($this->hasEntry() and $this->entryInPage === FALSE) {
			$this->template .= ' structure-entry ';
		}

		// Seul le jeton : `$this->section` choisit l'écran du sélecteur de structure, que l'accueil atteint
		// déjà sans section.
		if($this->homeNav !== NULL) {
			$this->template .= ' structure-home ';
			return;
		}

		switch($this->nav) {

			case 'planning' :
			case 'planning-v2' :
			case 'cultivation' :
			case 'intelligence-vegetable' :
			case 'toolbox' :
			case 'analyze-production' :
				$this->section = 'production';
				$this->template .= ' structure-production ';
				break;

			case 'selling' :
			case 'communications' :
			case 'analyze-commercialisation' :
				$this->section = 'commercialisation';
				$this->template .= ' structure-commercialisation ';
				break;

			case 'proof' :
			case 'bank' :
			case 'preaccounting' :
			case 'cash' :
			case 'invoicing' :
			case 'accounting' :
			case 'projection' :
			case 'loan' :
				$this->section = 'accounting';
				$this->template .= ' structure-accounting ';
				break;

			case 'structure' :
			case 'feature' :
			case 'data' :
			case 'team' :
			case 'subscription' :
				$this->section = 'settings';
				$this->template .= ' structure-settings ';
				break;

		}

	}

}
?>
