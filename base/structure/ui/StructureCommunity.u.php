<?php
namespace structure;

class StructureCommunityUi implements StructureCommonUi {

	public function getCreateForm(\util\FormUi $form, Structure $eStructure): string {

		$h = $form->asteriskInfo();
		$h .= $this->getStart($eStructure);

		$h .= $form->hidden('type', \structure\Structure::COMMUNITY);
		$h .= $form->hidden('communityType', $eStructure['communityType']);
		$h .= $form->dynamicGroups($eStructure, ['name*', 'legalEmail*']);

		$h .= $form->group(
			content: $form->submit(static::getText($eStructure, 'createButton'), ['data-waiter' => s("Création en cours")])
		);

		return $h;

	}

	protected function getStart(Structure $eStructure): string {

		switch($eStructure['communityType']) {

			case Structure::EDUCATION :

				$start = '<p>'.s("Créez un groupe de formation pour sensibiliser vos étudiants à la création d'un plan de culture et aux choix organisationnels d'une ferme.").'</p>';
				$start .= '<ul>';
					$start .= '<li>'.s("Permettez à vos étudiants de créer des fermes de simulation").'</li>';
					$start .= '<li>'.s("Accéder à du matériel pédagogique basé sur des données réelles de fermes").'</li>';
				$start .= '</ul>';

				return new \main\DesignUi()->getStart(
					\Asset::icon('info'),
					s("Pourquoi créer un groupe de formation ?"),
					$start
				);

			case Structure::PRODUCER :

				$start = '<p>'.s("Créez une communauté de producteurs pour partager des données de productions.").'</p>';
				$start .= '<ul>';
					$start .= '<li>'.s("Partagez les plans de culture").'</li>';
					$start .= '<li>'.s("Comparez les choix variétaux").'</li>';
				$start .= '</ul>';

				return new \main\DesignUi()->getStart(
					\Asset::icon('info'),
					s("Pourquoi créer une communauté de producteurs ?"),
					$start
				);

		}

		return '';

	}

	/**
	 * Le nom de l'onglet des fermes, que le parcours de mise en route reprend pour y ramener : un
	 * lien de retour qui nommerait l'écran autrement que sa rangée ne se lirait pas comme un retour.
	 *
	 * Un groupe de formation nomme les fermes de ses étudiants selon qui regarde (`getTabs()` dit
	 * pourquoi les autres types gardent le nom générique).
	 */
	public static function getFarmsLabel(Structure $eStructure): string {

		if($eStructure->acceptSchool()) {
			return StructureUi::getText($eStructure, $eStructure->getMember()->isStaff() ? 'farms' : 'farmsMember');
		}

		return s("Fermes");

	}

	public function getTabs(Structure $eStructure, int $members, bool $hasStructures, bool $hasLearningShared, string $selected, Section $eSection = new Section()): string {

		$tabs = [];

		// Le repli qui nommait cet onglet « Relevés de prix » faute de fermes ne masquait pas un
		// libellé, il masquait un trou : un groupe producteur *avec* des fermes n'avait aucun
		// chemin vers ses relevés, et l'absence ne se voyait que là où elle n'existait pas. Les
		// relevés ont maintenant leur onglet, celui-ci nomme ce qu'il montre.
		// La maison est un onglet à part entière et non un remplacement : sur l'accueil elle fait
		// doublon avec « Fermes », mais c'est le seul écran où elle en fait un. Sur les pages
		// filles — membres, paramétrage, administratif — elle est le repère de retour, et c'est
		// là qu'elle sert. Juger la rangée sur le seul écran où elle est redondante revient à
		// raisonner sur la navigation en oubliant le repérage.
		$tabs['home'] = [
			'href' => StructureUi::urlCommunity($eStructure, $eSection),
			'label' => \Asset::icon('house-fill'),
			// ⚠️ Sous 768 px la rangée devient une liste, et une icône seule n'y a pas de nom :
			// la maison s'y écrit « Tableau de bord ». Le libellé de la rangée reste l'icône.
			'name' => s("Tableau de bord"),
			'end' => FALSE
		];

		$tabs['members'] = [
			'href' => StructureUi::urlCommunityMembers($eStructure, $eSection),
			'label' => s("Membres"),
			'end' => FALSE
		];

		// ⚠️ Un groupe de formation porte deux natures de fermes qui n'ont rien à voir : celles
		// que ses étudiants apportent, et les fermes école, copies anonymisées communes à tout le
		// site dont il n'est ni propriétaire ni destinataire. Elles montaient dans la rangée
		// principale le 2026-09-05 : rangées en sous-onglets, elles se lisaient comme deux vues
		// d'un même ensemble, et la seconde ne se découvrait qu'en cliquant.
		//
		// Les autres types n'ont pas de ferme école : leur onglet reste unique et garde son nom
		// générique, la rangée n'ayant rien à distinguer.
		if($eStructure->acceptSchool()) {

			// ⚠️ Les fermes des étudiants n'existent qu'une fois une promotion créée : le rôle
			// d'étudiant est borné, donc personne ne peut entrer dans le groupe avant, et l'écran
			// ne pourrait montrer que du vide. La question porte sur la **fonctionnalité** et non
			// sur le compteur : un organisme de formation ouvre avec ses promotions allumées et
			// aucune créée, et c'est `getOnboarding()` qui l'accueille alors sur « créez votre
			// première promotion ».
			if($eStructure->acceptCommunityFeature('hasCommunitySections')) {

				$tabs['structures'] = [
					'href' => StructureUi::urlCommunityFarms($eStructure, $eSection),
					'label' => self::getFarmsLabel($eStructure),
					'end' => FALSE
				];

			}

			$tabs['schools'] = [
				'href' => StructureUi::urlCommunitySchools($eStructure),
				'label' => s("Fermes école"),
				'end' => FALSE
			];

		} else {

			$tabs['structures'] = [
				'href' => StructureUi::urlCommunityFarms($eStructure, $eSection),
				'label' => self::getFarmsLabel($eStructure),
				'end' => FALSE
			];

		}

		// ⚠️ Comparer suppose des fermes à mettre en regard, et une fonctionnalité allumée. Les
		// fermes sont structurantes — tout groupe en héberge —, le maraîchage ne l'est pas : il
		// avait vécu jusqu'ici sans porte, en suivant les fermes.
		if($eStructure->acceptCommunityFeature('hasCommunityVegetable')) {

			// ⚠️ Le chapeau ne prend pas de route à lui : il n'a rien à montrer par lui-même et
			// pointe la route de son premier écran. Une route intermédiaire qui redirige serait un
			// écran de plus à maintenir pour ne rien afficher, et il faudrait la défaire au
			// deuxième sous-onglet. La clé nomme le chapeau et non l'écran, c'est elle qui reste
			// active sur toutes ses routes.
			$tabs['production'] = [
				'href' => $eStructure->getMember()->isStaff() ? StructureUi::urlCommunityVegetable($eStructure, $eSection) : StructureUi::urlCommunityCompare($eStructure, $eSection),
				'label' => s("Maraîchage"),
				'end' => FALSE
			];

		}

		if(
			(
				$eStructure->acceptLearning() and
				($eStructure->isEducation() or $eStructure->isNetwork()) and
				$eStructure->getMember()->isStaff()
			) or
			$hasLearningShared
		) {
			$tabs['learning'] = ['href' => StructureUi::urlCommunityLearning($eStructure), 'label' => s("Formations"), 'end' => FALSE];
		}

		if($eStructure->acceptGroupQuotation()) {
			$tabs['quotation'] = ['href' => StructureUi::getObserverEntries('getCategoryUrls', $eStructure)['quotation']['community'], 'label' => s("Relevés de prix"), 'end' => FALSE];
		}

		// ⚠️ **Un onglet et non un menu.** Le paramétrage a porté un bouton déroulant posé au bout
		// de la rangée : il y ressemblait à un onglet sans en être un, ce qui est le pire des deux
		// états — l'écran annonce une navigation et n'en donne pas. C'est un écran du groupe comme
		// les autres, il prend sa place dans la rangée et en suit le repli sous 768 px.
		//
		// Il vient en dernier, après les écrans qu'on ouvre tous les jours : on paramètre un groupe
		// une fois, on lit ses fermes tout le temps.
		if($eStructure->canManage()) {
			$tabs['settings'] = [
				'href' => '/structure/structure:update?id='.$eStructure['id'],
				'label' => s("Administrer"),
				'end' => FALSE
			];
		}

		return self::getTabsRow($tabs, $selected).self::getTabsMenu($tabs, $selected);

	}

	/**
	 * La rangée d'onglets du bandeau, sur un tableau `[clé => ['href', 'label', 'name' si le libellé est
	 * une icône, 'end', 'class' facultative, 'pages' et 'dropdown' facultatifs]]`. Un groupe la remplit
	 * par `getTabs()`, une AMAP par ses menus. Un onglet qui porte `dropdown` ouvre la liste de ses
	 * `pages`, `$page` y étant la page ouverte.
	 */
	public static function getTabsRow(array $tabs, string $selected, string $page = ''): string {

		$h = '<div class="community-tabs-row">';

			$h .= '<div class="tabs tabs-h tabs-lg">';

				// tabs-item-sticked est la classe du produit (component.css, employée par quatre
				// écrans) : elle annule la marge basse qui sépare la rangée du contenu quand celle-ci
				// vit dans le corps de page. Une règle maison perdrait au-dessus de 992 px, la règle
				// du cadriciel y pesant (0,2,2).
				$h .= '<div class="tabs-item tabs-item-sticked">';

					$end = ' tab-item-end';

					foreach($tabs as $name => $tab) {

						$classes = ($name === $selected) ? ' selected' : '';
						$classes .= isset($tab['class']) ? ' '.$tab['class'] : '';

						if($tab['end']) {
							$classes .= $end;
							$end = '';
						}

						if(isset($tab['dropdown'])) {

							// Un onglet et non un bouton : `dropdown-toggle` porterait la flèche, mais son
							// `::after` effacerait le soulignement de l'onglet choisi (`component.css`).
							// La liste s'ouvre au survol comme le menu du planning d'une ferme, et à
							// l'appui sur un écran tactile, où le cadriciel ignore le survol.
							$h .= '<a data-dropdown="bottom-start" data-dropdown-hover="true" class="tab-item'.$classes.'">'.$tab['label'].' <span class="tab-item-caret">'.\Asset::icon('caret-down-fill').'</span></a>';
							$h .= '<div class="dropdown-list dropdown-list-unstyled dropdown-list-light">';
								foreach($tab['pages'] as $namePage => $tabPage) {
									$h .= '<a href="'.$tabPage['href'].'" class="dropdown-item'.(($name === $selected and $namePage === $page) ? ' selected' : '').'">'.$tabPage['label'].'</a>';
								}
							$h .= '</div>';

						} else {
							$h .= '<a href="'.$tab['href'].'" class="tab-item'.$classes.'">'.$tab['label'].'</a>';
						}

					}

				$h .= '</div>';
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La même rangée, repliée en un menu sous 768 px.
	 *
	 * ⚠️ Le bouton porte **les trois barres et le nom de l'écran ouvert**, jamais un burger nu :
	 * la rangée disait deux choses — où l'on peut aller, et où l'on est —, et le burger seul ne
	 * garde que la première, au moment précis où l'en-tête est le plus réduit.
	 *
	 * ⚠️ Rendue toujours, montrée par la seule feuille : les deux formes portent les mêmes liens,
	 * et les faire dépendre d'une mesure de largeur côté serveur ferait dépendre le HTML d'une
	 * chose que le serveur ne connaît pas.
	 *
	 * Un onglet qui ouvre ses pages (`dropdown`) les range ici sous son nom.
	 */
	public static function getTabsMenu(array $tabs, string $selected, string $page = ''): string {

		$current = $tabs[$selected]['name'] ?? ($tabs[$selected]['label'] ?? '');

		$h = '<div class="community-tabs-menu">';

			$h .= '<a class="btn btn-white dropdown-toggle" data-dropdown="bottom-start">';
				$h .= \Asset::icon('list').' <span>'.$current.'</span>';
			$h .= '</a>';

			$h .= '<div class="dropdown-list dropdown-list-unstyled dropdown-list-light">';

				// Un onglet seul qui suit des pages rangées sous leur nom se lirait comme la dernière d'entre
				// elles : un filet referme le groupe avant lui.
				$afterPages = FALSE;

				foreach($tabs as $name => $tab) {

					if(isset($tab['dropdown'])) {

						$h .= '<div class="dropdown-subtitle">'.($tab['name'] ?? $tab['label']).'</div>';

						foreach($tab['pages'] as $namePage => $tabPage) {
							$h .= '<a href="'.$tabPage['href'].'" class="dropdown-item'.(($name === $selected and $namePage === $page) ? ' selected' : '').'">'.$tabPage['label'].'</a>';
						}

						$afterPages = TRUE;

					} else {

						if($afterPages) {
							$h .= '<div class="dropdown-divider"></div>';
							$afterPages = FALSE;
						}

						$h .= '<a href="'.$tab['href'].'" class="dropdown-item'.(($name === $selected) ? ' selected' : '').'">'.($tab['name'] ?? $tab['label']).'</a>';

					}

				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les écrans de production du groupe, sous le chapeau qui les réunit, en onglets de la bande de
	 * l'écran (`CommunityTemplate::$mainTabs`). Seule l'équipe du groupe a deux écrans ; un membre
	 * n'en a qu'un, qui ne fait pas une rangée.
	 *
	 * ⚠️ Des liens et non des panneaux : chaque écran porte ses propres paramètres et son propre
	 * coût, et `Lime.Tab.restore` les chargerait tous d'avance.
	 */
	public function getProductionTabs(Structure $eStructure, string $selected, Section $eSection = new Section()): string {

		if($eStructure->getMember()->isStaff() === FALSE) {
			return '';
		}

		$tabs = [
			'compare' => [StructureUi::urlCommunityCompare($eStructure, $eSection), s("Cultures")],
			'vegetable' => [StructureUi::urlCommunityVegetable($eStructure, $eSection), s("Statistiques")]
		];

		$h = '';

		foreach($tabs as $name => [$href, $label]) {
			$h .= '<a href="'.$href.'" class="tab-item'.(($name === $selected) ? ' selected' : '').'">'.$label.'</a>';
		}

		return $h;

	}

	public function getMap(\Collection $cStructureLocated, int $total): string {

		\map\MapboxUi::load();
		\Asset::js('structure', 'communityMapAi.js');

		$points = [];

		foreach($cStructureLocated as $eStructure) {

			$popup = '<div><b>'.encode($eStructure['name']).'</b></div>';
			$popup .= '<div class="color-muted font-sm">'.encode($eStructure['eSystem']['place']).'</div>';

			if($eStructure['url'] !== '') {
				$popup .= '<div class="mt-1"><a href="'.$eStructure['url'].'" class="btn btn-sm btn-outline-primary">'.s("Ouvrir la ferme").'</a></div>';
			}

			$points[] = [
				'lngLat' => $eStructure['eSystem']['placeLngLat'],
				'popup' => $popup
			];

		}

		$located = $cStructureLocated->count();

		$h = '<div class="util-card mb-2">';
			$h .= '<div class="util-card-bleed">';

				$locale = [
					'ScrollZoomBlocker.CtrlMessage' => s("Utilisez Ctrl + molette pour zoomer sur la carte"),
					'ScrollZoomBlocker.CmdMessage' => s("Utilisez ⌘ + molette pour zoomer sur la carte"),
					'TouchPanBlocker.Message' => s("Utilisez deux doigts pour déplacer la carte"),
					'NavigationControl.ZoomIn' => s("Zoomer"),
					'NavigationControl.ZoomOut' => s("Dézoomer")
				];

				$h .= '<div id="community-map" class="community-map" data-points="'.encode(json_encode($points)).'" data-locale="'.encode(json_encode($locale)).'" onrender="StructureCommunityMap.start(this)"></div>';

				if($located < $total) {
					$h .= '<div class="util-card-count font-sm">'.p("{value} ferme sur {total} située", "{value} fermes sur {total} situées", $located, ['total' => $total]).'</div>';
				}

			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La barre de portée, qui prolonge le bandeau du groupe.
	 *
	 * `$apply` dit si **cet écran-là** est filtré : là où il ne l'est pas — « mes fermes », les
	 * relevés —, la barre ne se rend pas, une commande sans effet mentant sur ce qu'elle fait.
	 *
	 * Ici se décide ce qu'on montre : le nom en surtitre, et les entrées de la liste, « tout le
	 * groupe » d'abord, puis les sous-groupes ouverts, puis les clos, qui restent sélectionnables.
	 * Le rendu est celui de `getScopeLine()`, que partagent les écrans filtrés sur autre chose
	 * qu'un sous-groupe.
	 */
	public function getSectionLine(Structure $eStructure, \Collection $cSection, \Collection $cSectionClosed, Section $eSection, bool $choice = TRUE, bool $apply = TRUE): string {

		// ⚠️ Deux sous-groupes pour proposer un choix, **mais un seul suffit quand il borne**. Un
		// rôle borné à une promotion close ne voit plus qu'elle depuis qu'un clos ne se propose plus
		// à un membre : sans cette seconde porte, l'étudiant regardait un écran filtré sans
		// étiquette, sans rien qui dise à quoi. Clore ne retire aucun accès — le `.yml` le dit —,
		// donc le filtre agit toujours et doit toujours se nommer.
		$isBounding = ($choice === FALSE and $cSection->count() === 1);

		if(
			$apply === FALSE or
			(
				$isBounding === FALSE and
				($cSection->count() + $cSectionClosed->count()) < 2
			)
		) {
			return '';
		}

		// Borné à un seul sous-groupe, c'est lui qu'on regarde quoi que dise l'adresse : la portée
		// filtre l'écran avant tout choix, et il n'y a pas de choix à faire. Un libellé nu posé là
		// où les autres ont un bouton donnait deux formes à la même information : le jeton disparaît
		// avec le choix.
		if($choice === FALSE) {
			return $this->getScopeLine($eStructure, encode($cSection->first()['name']), []);
		}

		$overtitle = $eSection->notEmpty()
			? encode($eSection['name'])
			: static::getText($eStructure, 'sectionAll');

		// ⚠️ Les entrées se construisent sur l'adresse **sans sa portée**, et non par ajout
		// d'un paramètre : la portée est un segment, et l'ajouter à la fin d'une adresse
		// qui en porte déjà un en poserait deux. On la retire d'abord, on repose ensuite.
		$base = self::getSectionBase($eStructure, $eSection);

		$entries = [[
			'url' => $base,
			'label' => static::getText($eStructure, 'sectionAll'),
			'count' => NULL,
			'selected' => $eSection->empty(),
			'closed' => FALSE
		]];

		foreach($cSection as $eSectionItem) {
			$entries[] = [
				'url' => self::getSectionUrl($eStructure, $base, $eSectionItem),
				'label' => encode($eSectionItem['name']),
				'count' => $eSectionItem['countMember'],
				'selected' => ($eSection->notEmpty() and $eSection['id'] === $eSectionItem['id']),
				'closed' => FALSE
			];
		}

		foreach($cSectionClosed as $eSectionItem) {
			$entries[] = [
				'url' => self::getSectionUrl($eStructure, $base, $eSectionItem),
				'label' => encode($eSectionItem['name']),
				'count' => $eSectionItem['countMember'],
				'selected' => ($eSection->notEmpty() and $eSection['id'] === $eSectionItem['id']),
				'closed' => TRUE
			];
		}

		return $this->getScopeLine($eStructure, $overtitle, $entries);

	}

	/**
	 * La ligne de la barre de portée : le surtitre, qui dit ce qu'on regarde, et le jeton « Changer »
	 * avec la liste qu'il ouvre, quand `$entries` en porte. Elle est neutre : elle rend ce qu'on lui
	 * donne, et ne rien rendre se décide chez l'appelant (`getSectionLine()` sous deux sous-groupes,
	 * le suivi d'une formation de réseau sous deux fermes). Une entrée porte `url`, `label` (déjà
	 * encodé), `count` (`NULL` pour n'en montrer aucun), `selected` et `closed` ; un filet suit la
	 * première entrée, qui est le tout, un second précède la première close.
	 *
	 * ⚠️ Le surtitre porte toujours un nom, y compris pour « tout le groupe » : sans lui la ligne ne
	 * portait que le jeton, qui se lisait alors comme un fil d'Ariane et non comme une commande de
	 * portée. Le jeton, lui, ne porte pas le nom, passé en surtitre à sa gauche, et se nomme toujours
	 * du même mot : une commande qui affiche l'état qu'elle change se lit comme une information et
	 * se clique par erreur, une commande qui change de nom selon l'état se cherche deux fois.
	 */
	public function getScopeLine(Structure $eStructure, string $overtitle, array $entries): string {

		$h = '<div class="util-scope-line">';

			$h .= '<div class="util-scope-line-title">'.$overtitle.'</div>';

			if($entries !== []) {

				$h .= '<div class="util-scope-line-change">';

					$h .= '<div>';

						// util-chip sans dropdown-toggle : le jeton porte déjà son chevron en
						// background-image et lui réserve sa marge droite, quand dropdown-toggle en pose un
						// second en :after. C'est la forme de la page design system (Design.u.php), et
						// data-dropdown suffit à Lime.Dropdown, qui écoute l'attribut et non la classe.
						// ⚠️ `is-inverted` seulement sur une photo : sans image, la barre est blanche et le
						// jeton y reprend sa forme ordinaire. C'est le seul cas où le jeton a un fond
						// sombre sous lui.
						//
						// ⚠️ Et **jamais `is-active`**, quelle que soit l'entrée choisie : le composant
						// dit ce que cette classe signifie — la bordure « est le seul signe qui dit ce
						// jeton porte une valeur ». Celui-ci n'en porte aucune, il dit « Changer », et
						// c'est le surtitre à sa gauche qui porte la valeur. La marquer ici serait faux,
						// et pas seulement redondant.
						$h .= '<a class="util-chip'.($eStructure['banner'] !== NULL ? ' is-inverted' : '').'" data-dropdown="bottom-end">'.s("Changer").'</a>';

						$h .= '<div class="dropdown-list">';

							$closedStarted = FALSE;

							foreach($entries as $position => $entry) {

								// Deux filets seulement, et chacun sépare deux natures : le tout n'est pas
								// une entrée comme les autres, les closes sont une autre catégorie. Un filet
								// entre chaque entrée dirait qu'il n'y a aucun groupement, et les closes ne
								// se distingueraient plus que par leur opacité.
								if($position === 1) {
									$h .= '<div class="dropdown-divider"></div>';
								}

								if($entry['closed'] and $closedStarted === FALSE) {
									$h .= '<div class="dropdown-divider"></div>';
									$closedStarted = TRUE;
								}

								$classes = 'dropdown-item util-scope-line-item';

								if($entry['selected']) {
									$classes .= ' selected';
								}

								if($entry['closed']) {
									$classes .= ' util-scope-line-item-closed';
								}

								$h .= '<a href="'.$entry['url'].'" class="'.$classes.'">';
									$h .= '<span>'.$entry['label'].'</span>';

									if($entry['count'] !== NULL) {
										$h .= '<span class="util-counter">'.$entry['count'].'</span>';
									}

								$h .= '</a>';

							}

						$h .= '</div>';

					$h .= '</div>';

				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * L'adresse de l'écran courant débarrassée de sa portée.
	 *
	 * ⚠️ On ne peut pas se contenter de couper le dernier segment numérique : `/groupe/5984` en
	 * est un, et le tableau de bord d'un groupe se serait retrouvé sans son identifiant. C'est le
	 * sous-groupe **qu'on regarde** qui dit quel suffixe retirer, et il est déjà connu.
	 */
	protected static function getSectionBase(Structure $eStructure, Section $eSection): string {

		$base = LIME_REQUEST_PATH;

		if($eSection->empty()) {
			return $base;
		}

		// Le tableau de bord n'a pas de suffixe à lui : sa portée s'écrit `/section/{id}`.
		foreach(['/section/'.$eSection['id'], '/'.$eSection['id']] as $suffix) {
			if(str_ends_with($base, $suffix)) {
				return substr($base, 0, -strlen($suffix));
			}
		}

		return $base;

	}

	/** La même adresse, portant cette fois le sous-groupe choisi. */
	protected static function getSectionUrl(Structure $eStructure, string $base, Section $eSection): string {

		$dashboard = ($base === StructureUi::urlCommunity($eStructure));

		return $base.($dashboard ? '/section/' : '/').$eSection['id'];

	}

	public function getActionsMember(Structure $eStructure, Member $eMember, \Collection $cStructureMine, \Collection $cSection): string {

		$blocks = [];

		if($eMember->notEmpty()) {
			$blocks[] = '<div class="dropdown-text dropdown-info">'.s("Vous êtes {role} de {this}", ['role' => encode($eMember['role']['name']), 'this' => static::getText($eStructure, 'this')]).'</div>';
		}

		// ⚠️ **L'administration vit dans ce menu et non dans un bouton à part.** Elle avait
		// le sien, posé au bout de la rangée d'onglets : au-dessus de 992 px il y ressemble
		// à un onglet sans en être un, ce qui est le pire des deux états, et il faisait
		// deux menus voisins pour un seul lecteur. Ses entrées gardent leur titre, donc
		// les deux sujets restent distincts dans la même liste.
		// ⚠️ **Ce menu ne garde que ce qui n'a pas d'onglet.** Le paramétrage en a un depuis
		// qu'il est dans la rangée ; les sous-groupes n'en ont pas, et ce menu reste leur
		// seul chemin. La porte des sous-groupes est leur **fonctionnalité** et non
		// l'accueil des fermes : éteinte, un interrupteur qui laisse ouvert le geste qu'il
		// ferme n'éteint rien.
		if($eStructure->canManage() and $eStructure->acceptCommunityFeature('hasCommunitySections')) {
			$blocks[] = '<div class="dropdown-title">'.s("Administration de groupe").'</div>'.
				'<a href="'.StructureUi::urlCommunitySections($eStructure).'" class="dropdown-item">'.static::getText($eStructure, 'sectionManage').'</a>';
		}

		if($eMember->notEmpty()) {

			$blocks[] = self::getActionsMemberStructures($eStructure, $cStructureMine);

			if($cSection->notEmpty()) {
				$blocks[] = self::getActionsMemberSections($eStructure, $cSection);
			}

			// ⚠️ Un panneau et non un `data-confirm` : celui-ci passe par le `confirm()` du
			// navigateur, qui ne rend que du texte brut. Une rupture a trois natures à dire —
			// ce qui reste, ce qui part, ce qui ne se défait pas — et les aplatir en une
			// phrase revient à n'en dire qu'une.
			$blocks[] = '<a href="/structure/community:breakLeave?id='.$eStructure['id'].'" class="dropdown-item">'.s("Quitter le groupe").'</a>';

		}

		$blocks = array_filter($blocks, fn(string $block) => $block !== '');

		if($blocks === []) {
			return '';
		}

		$h = '<div>';
			$h .= '<a data-dropdown="bottom-end" class="dropdown-toggle btn btn-white">'.\Asset::icon('person-fill').'</a>';
			$h .= '<div class="dropdown-list">';
				$h .= implode('<div class="dropdown-divider"></div>', $blocks);
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	protected static function getActionsMemberStructures(Structure $eStructure, \Collection $cStructureMine): string {

		if($cStructureMine->empty()) {

			// La même porte que la route, qui valide `acceptGroupStructure()` : un groupe sans
			// type n'héberge pas de ferme, et le lien y menait à un refus.
			if($eStructure->acceptGroupStructure() === FALSE) {
				return '';
			}

			return '<div class="dropdown-title">'.s("Mes fermes").'</div>'.
				'<a href="'.StructureUi::urlCommunityFarmsMine($eStructure).'" class="dropdown-item">'.s("Partager une ferme").'</a>';

		}

		$h = '<div class="dropdown-title">'.s("Mes fermes").'</div>';

		$shown = ($cStructureMine->count() > 4) ? 3 : $cStructureMine->count();
		$position = 0;

		foreach($cStructureMine as $eStructureProducer) {

			if($position >= $shown) {
				break;
			}

			$position++;

			$h .= '<a href="'.StructureUi::urlCommunityFarmsMine($eStructure).'" class="dropdown-item">'.encode($eStructureProducer['name']).'</a>';

		}

		if($cStructureMine->count() > $shown) {
			$h .= '<a href="'.StructureUi::urlCommunityFarmsMine($eStructure).'" class="dropdown-item">'.s("Les {value} autres", $cStructureMine->count() - $shown).'</a>';
		}

		return $h;

	}

	protected static function getActionsMemberSections(Structure $eStructure, \Collection $cSection): string {

		$h = '<div class="dropdown-title">'.s("Mes {sections}", ['sections' => mb_strtolower(static::getText($eStructure, 'section', 2))]).'</div>';

		foreach($cSection as $eSection) {

			$h .= '<a href="'.StructureUi::urlCommunity($eStructure, $eSection).'" class="dropdown-item">'.encode($eSection['name']).($eSection->isClosed() ? ' '.self::getSectionClosed() : '').'</a>';

		}

		return $h;

	}


	/**
	 * Les membres du groupe, rangés par rôle.
	 *
	 * Le groupement existait déjà dans la donnée — `ccMember` est indexée par rôle — mais la grille
	 * unique ne le montrait pas, et le rôle se répétait donc sur chaque carte : seize fois le même
	 * mot là où il ne varie pas. Rendu visible en titre de section, il libère la carte, où la ferme
	 * prend sa place.
	 *
	 * ⚠️ « Aucune ferme partagée » se dit, en atténué : c'est la seule chose qu'un administrateur
	 * vient chercher ici, et un blanc ne se distingue pas d'une donnée qui n'a pas chargé.
	 */
	public function getMembers(Structure $eStructure, \Collection $ccMember, \Collection $cRole): string {

		$h = '';

		foreach(static::getMembersOrder($eStructure, $ccMember, $cRole) as [$label, $cMember, $farmExpected, $roleExpected]) {

			$h .= '<div class="community-members-section">';

				$h .= '<h3>';
					$h .= $label;
					$h .= ' <span class="util-counter">'.$cMember->count().'</span>';
				$h .= '</h3>';

				$h .= '<div class="util-cards util-cards-3">';

					foreach($cMember as $eMember) {
						$h .= $this->getMemberCard($eMember, $farmExpected, $roleExpected);
					}

				$h .= '</div>';

			$h .= '</div>';

		}

		return $h;

	}

	/**
	 * Les sections de l'écran des membres et du rail, dans l'ordre, chacune avec les deux drapeaux
	 * qui disent ce que la carte porte sous le nom.
	 *
	 * ⚠️ **L'encadrement tient dans une seule section**, nommée d'après le type de groupe, et non
	 * une section par rôle. Un organisme de formation lisait « Administrateur 1 · Enseignant 1 ·
	 * Étudiant 10 » : trois en-têtes pour deux personnes et une promotion. Que le producteur et la
	 * comptabilité ne bougent pas est le contrôle que la règle est juste — chez eux la section
	 * n'avait déjà qu'un rôle dedans.
	 *
	 * ⚠️ Le rôle revient alors sur la carte, en atténué, **dans cette section seulement, et quand
	 * elle en réunit plusieurs** : fondre l'administrateur et le conseiller d'un réseau perdrait qui
	 * fait quoi. Sous un seul rôle, comme les formateurs d'un organisme de formation, il se
	 * répéterait sur chaque carte sans rien distinguer, ce qui est la raison pour laquelle il avait
	 * quitté la carte. La place est libre parce qu'on n'attend aucune ferme de l'encadrement.
	 *
	 * ⚠️ Le drapeau de la ferme est exactement la condition de l'accueil : on n'attend une ferme
	 * que de ceux à qui l'écran en propose une. Là où un rôle est borné, ce sont les bornés seuls.
	 * Sous un rôle dont on n'attend rien, « aucune ferme partagée » annoncerait un manque qui n'en
	 * est pas un.
	 *
	 * ⚠️ Une seule règle pour deux écrans : le rail du gabarit lit cette liste, au lieu de refaire
	 * son propre nommage par rôle. Les deux se contredisaient dès qu'on touchait à l'un.
	 */
	public static function getMembersOrder(Structure $eStructure, \Collection $ccMember, \Collection $cRole): array {

		$cMemberStaff = new \Collection();
		$staffRoles = 0;

		$all = [];
		$own = [];

		foreach($ccMember as $role => $cMember) {

			// La clé vide est celle du rôle d'administration, que la sélection ne rend pas dans
			// `cRole` : c'est déjà ainsi que le rail la reconnaissait.
			if($role === '' or $cRole[$role]->isStaff()) {

				$staffRoles++;

				foreach($cMember as $eMember) {
					$cMemberStaff[] = $eMember;
				}

				continue;

			}

			// L'en-tête porte le nombre de ses membres, comme celui de l'encadrement : il
			// s'accorde donc au nombre, ce que le nom stocké du rôle ne sait pas faire.
			$entry = [RoleUi::getFqn($cRole[$role]['fqn'], $cMember->count()), $cMember, TRUE, FALSE];

			if($cRole[$role]['sections'] === Role::ALL) {
				$all[] = $entry;
			} else {
				$own[] = $entry;
			}

		}

		// Un rôle borné existe : lui seul apporte une ferme, et les non bornés n'en apportent plus.
		if($own !== []) {

			foreach($all as $index => [$label, $cMember, $farmExpected, $roleExpected]) {
				$all[$index] = [$label, $cMember, FALSE, $roleExpected];
			}

		}

		$staff = [];

		if($cMemberStaff->notEmpty()) {
			$staff[] = [
				static::getText($eStructure, 'staffTitle', $cMemberStaff->count()),
				$cMemberStaff,
				FALSE,
				$staffRoles > 1
			];
		}

		return array_merge($staff, $all, $own);

	}

	protected function getMemberCard(Member $eMember, bool $farmExpected, bool $roleExpected = FALSE): string {

		$eUser = $eMember['user'];

		$h = '<div class="util-card">';
			$h .= '<div class="community-member">';
				$h .= \user\UserUi::getVignette($eUser, '2.5rem');
				$h .= '<div class="community-member-name">';

					$h .= '<b>'.$eUser->getName().'</b>';

					// Le nom du rôle tel qu'il est en base, et non le libellé de son `fqn` : un
					// organisme de formation nomme son administrateur « Formateur ». Même règle que
					// le badge du bandeau.
					//
					// ⚠️ Sa propre classe et non celle des fermes : l'encadrement en porte, mesuré
					// sur un réseau de dev où l'administrateur en partage quatre, et deux lignes
					// atténuées de même graisse se seraient lues comme une liste de cinq. Deux
					// natures, deux formes — la règle du bandeau du groupe, un cran plus bas.
					if($roleExpected) {
						$h .= '<div class="community-member-role color-muted font-sm">'.encode($eMember['role']['name']).'</div>';
					}

					if($eMember['cStructure']->notEmpty() or $farmExpected) {

						$h .= '<div class="community-member-structures color-muted font-sm">';

							if($eMember['cStructure']->empty()) {
								$h .= '<div>'.s("Aucune ferme partagée").'</div>';
							} else {

								foreach($eMember['cStructure'] as $eStructureProducer) {
									$h .= '<div>'.encode($eStructureProducer['name']).'</div>';
								}

							}

						$h .= '</div>';

					}

				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	public function getStructures(Structure $eStructure, \structure\Community $eCommunity, \Collection $cStructureMine, \Collection $cStructureProject, \Collection $cStructureMember, bool $canManage, bool $hasShareable, bool $hasProducer, bool $hasDuplicate, Section $eSection = new Section(), bool $onboarding = FALSE, string $membersHead = ''): string {

		// La grille des cartes vit dans la feuille du package, que seul le constructeur
		// de StructureUi déclarait : sans elle, la carte perd sa mise en page.
		\Asset::css('structure', 'structure.css');

		$shares = $eStructure->getCommunityAudienceRights();

		$h = '';

		// ⚠️ **Aucune phrase globale sur ce qui est partagé.** Un bloc disait ici que le groupe ne
		// demande rien et renvoyait le régler ailleurs : c'était le diagnostic d'un trou du
		// parcours, pas une information. Le groupe n'avait jamais eu l'occasion de le dire, alors
		// un écran qui n'est pas celui du réglage le lui rappelait, au-dessus d'une liste de
		// fermes, pour une décision qui appartient au groupe. L'étape « Partage » pose la question
		// au bon moment, et le bandeau d'arrivée propose le geste à qui l'a passée — une alerte
		// dit qu'un état est anormal, or ne rien demander n'a rien d'anormal. Chaque carte porte
		// par ailleurs ce que le groupe lit de cette ferme-là.

		$filtered = $eSection->notEmpty();

		// ⚠️ Sous filtre, un écran sans ferme ne dit pas la même chose : le groupe en a, c'est le
		// sous-groupe choisi qui n'en a pas. Les trois états ci-dessous parlent du groupe et
		// mèneraient à créer ou partager une ferme, ce qui ne répare rien.
		if($filtered === FALSE and $cStructureMine->empty() and $cStructureProject->empty() and $cStructureMember->empty()) {

			// ⚠️ Deux gestes identiques ne se posent pas à deux cents pixels l'un de l'autre.
			// L'appel à l'action du groupe, rendu au-dessus par le gabarit, propose déjà de
			// partager, d'inviter ou de créer une ferme, et il sait à quelle étape on en est : les
			// deux états qui reposaient ces boutons lui laissent la main.
			//
			// Celui du milieu reste : il ne propose rien et dit ce que l'appel à l'action ne dit
			// jamais — que le partage passe par l'administrateur de la ferme, pas par le lecteur.
			if($hasShareable) {

				return $onboarding ? $h : $h.self::getEmpty(
					// Le geste d'abord, le texte qui l'explique ensuite : l'écran n'a rien d'autre à
					// porter, et le partage a lieu d'être depuis que tous les types de groupes
					// hébergent des fermes.
					s("Aucune ferme dans ce groupe"),
					s("Partagez la vôtre, ou invitez les personnes qui apporteront les leurs."),
					$this->getEmptyActions($eStructure, $canManage, TRUE)
				);

			}

			if($hasProducer) {
				return $h.self::getEmpty(
					s("Aucune ferme dans ce groupe"),
					s("Seul un administrateur peut partager une ferme avec un groupe. Demandez à un administrateur de la vôtre de vous nommer administrateur, ou de la partager lui-même.")
				);
			}

			return $onboarding ? $h : $h.self::getEmpty(
				s("Aucune ferme dans ce groupe"),
				s("Les membres verront vos données de production le jour où vous aurez une ferme et où vous la partagerez."),
				'<div class="util-buttons"><a href="/demarrer/ferme" class="btn btn-primary">'.s("Créer une ferme").'</a></div>'
			);

		}

		// ⚠️ Le retrait quitte la carte pour la page de gestion. Il y était le seul geste
		// destructeur d'un écran de consultation, répété sur chaque carte et bordé de rouge : la
		// grille se lisait comme une liste de choses à retirer. Une carte mène à une ferme, elle
		// ne la défait pas.
		//
		// Le titre de l'onglet n'est plus ici : il monte dans le bandeau avec sa roue crantée,
		// comme celui des relevés de prix (`quotation/view/community.v.php:10`). Deux onglets d'un
		// même groupe ne se présentent pas différemment, l'un par un titre de page et l'autre par
		// un intertitre.
		$getAffiliated = fn(Structure $eStructureAffiliated) => self::getGroupPanel(
			$eStructureAffiliated,
			self::getStructureMembers($eStructureAffiliated),
			url: $eStructureAffiliated['url'],
			badges: self::getShareBadges($eStructureAffiliated, $shares),
			aside: self::getBatchCheckbox($eStructureAffiliated)
		);

		$members = '';

		foreach($cStructureMine as $eStructureMine) {
			$members .= $getAffiliated($eStructureMine);
		}

		foreach($cStructureMember as $eStructureMember) {
			$members .= $getAffiliated($eStructureMember);
		}

		// ⚠️ Trois natures, trois onglets, et le mélange les confondait : une ferme projet
		// appartient au groupe et ne lui partage rien, une ferme école n'est la ferme de personne,
		// une ferme de membre lui ouvre un périmètre. Rangées dans la même grille, seul le
		// sous-titre les distinguait — un mot au milieu de vingt cartes.
		// ⚠️ Les fermes école ne sont plus ici : elles ont leur propre entrée dans la rangée
		// principale, et un groupe de formation n'a donc plus qu'un panneau — donc plus de
		// sous-onglets du tout. C'est ce que le repli à un seul panneau produit tout seul.
		$panels = [
			'members' => [static::getText($eStructure, 'farms'), $members],
			'group' => [s("Fermes projet"), self::getProjectPanels($cStructureProject)]
		];

		// ⚠️ Un filtre change ce qu'une vue contient, jamais la navigation entre les vues. Le
		// panneau des fermes de membres reste donc dans la rangée même vidé par un sous-groupe :
		// sans cette exception, l'écran perdait le chemin vers les fermes projet en même temps que
		// son contenu, et une carte restée seule sous le titre se lisait « ce groupe a une ferme ».
		$panels = array_filter(
			$panels,
			fn(array $panel, string $name) => ($panel[1] !== '' or ($name === 'members' and $filtered)),
			ARRAY_FILTER_USE_BOTH
		);

		$getPanel = function(string $name, string $content) use($eStructure, $eSection, $membersHead): string {

			$h = ($name === 'members') ? $membersHead : '';

			if($content === '') {
				$h .= $this->getSectionEmpty($eStructure, $eSection);
			} else {
				$h .= '<div class="member-structures util-cards util-cards-2" data-batch="#batch-farm">'.$content.'</div>';
			}

			return $h;

		};

		// Un seul onglet ne s'annonce pas : la rangée dirait qu'il y a un choix là où il n'y en a
		// pas. C'est le cas de la plupart des groupes, qui n'ont ni ferme projet ni ferme école.
		if(count($panels) === 1) {

			$name = array_key_first($panels);
			$content = $panels[$name][1];

			return $h.$getPanel($name, $content).($content === '' ? '' : self::getBatch($eStructure, $cStructureMine, $cStructureMember, $hasDuplicate, $eSection));

		}

		$h .= '<div class="tabs tabs-h" id="community-structures" onrender="'.encode('Lime.Tab.restore(this, "members")').'">';

			$h .= '<div class="tabs-item">';

				$first = TRUE;

				foreach($panels as $name => [$label, $content]) {
					$h .= '<a class="tab-item'.($first ? ' selected' : '').'" data-tab="'.$name.'" onclick="Lime.Tab.select(this)">'.$label.'</a>';
					$first = FALSE;
				}

			$h .= '</div>';

			$first = TRUE;

			foreach($panels as $name => [$label, $content]) {

				$h .= '<div class="tab-panel'.($first ? ' selected' : '').'" data-tab="'.$name.'">';
					$h .= $getPanel($name, $content);
				$h .= '</div>';

				$first = FALSE;

			}

		$h .= '</div>';

		$h .= self::getBatch($eStructure, $cStructureMine, $cStructureMember, $hasDuplicate, $eSection);

		return $h;

	}

	protected static function getBatchCheckbox(Structure $eStructure): string {

		$batch = ['accept-system'];

		if($eStructure['sharing']['shareProduction']) {
			$batch[] = 'accept-cultures';
		}

		if($eStructure['duplicate']) {
			$batch[] = 'accept-duplicate';
		}

		$h = '<div class="util-deck-aside">';
			$h .= '<label class="util-checkbox">';
				$h .= new \util\FormUi()->inputCheckbox('batch[]', $eStructure, [
					'oninput' => 'StructureCommunityFarmsAi.changeSelection()',
					'data-batch' => implode(' ', $batch),
					'data-name' => $eStructure['name']
				]);
			$h .= '</label>';
		$h .= '</div>';

		return $h;

	}

	protected static function getBatch(Structure $eStructure, \Collection $cStructureMine, \Collection $cStructureMember, bool $hasDuplicate, Section $eSection): string {

		if($cStructureMine->empty() and $cStructureMember->empty()) {
			return '';
		}

		\Asset::js('structure', 'communityFarmsAi.js');

		$url = StructureUi::urlCommunityCompare($eStructure, $eSection);

		$menu = '<a data-dropdown="top-start" class="batch-item" data-compare>'.\Asset::icon('arrow-left-right').'<span>'.s("Comparer").'</span></a>';
		$menu .= '<div class="dropdown-list bg-secondary">';

			$menu .= '<div class="dropdown-title" data-compare-names data-and="'.s("et").'"></div>';

			$targets = [
				1 => [s("Face au réseau"), s("Cette ferme ne partage pas son plan de culture avec vous")],
				2 => [s("Ferme à ferme"), s("Une des deux fermes ne partage pas son plan de culture avec vous")]
			];

			foreach($targets as $count => [$label, $refused]) {

				$menu .= '<div class="dropdown-subtitle" data-compare-target="'.$count.'">'.$label.'</div>';
				$menu .= '<a data-url="'.$url.'" data-compare-target="'.$count.'" data-batch-test="accept-cultures" data-batch-not-only="hide" class="dropdown-item">'.s("Cultures").'</a>';
				$menu .= '<a data-compare-target="'.$count.'" data-batch-test="accept-cultures" data-batch-only="hide" class="dropdown-item disabled">';
					$menu .= '<div class="util-item-label">'.s("Cultures").'</div>';
					$menu .= '<div class="util-item-more">'.$refused.'</div>';
				$menu .= '</a>';

			}

		$menu .= '</div>';

		if($hasDuplicate) {
			$menu .= '<a data-url="'.StructureDuplicateAiUi::urlBase($eStructure).'" class="batch-item" data-batch-test="accept-duplicate" data-duplicate>'.\Asset::icon('copy').'<span>'.s("Dupliquer la ferme").'</span></a>';
		}

		return \util\BatchUi::group('batch-farm', $menu, title: s("Pour les fermes sélectionnées"), hide: 'StructureCommunityFarmsAi.hideSelection()');

	}

	/**
	 * Ce qu'un filtre qui n'a rien laissé doit dire.
	 *
	 * Il nomme le sous-groupe, et c'est tout ce qui sépare « le filtre a tout écarté » de « il n'y
	 * a rien » : sans le nom, le lecteur retourne lire le jeton pour comprendre ce qu'il regarde.
	 * Le chemin de retour va avec, un écran vide ne se quitte pas par la mémoire de l'URL.
	 */
	protected function getSectionEmpty(Structure $eStructure, Section $eSection): string {

		$text = s("Aucun membre de {value} n'a partagé de ferme avec ce groupe.", '<b>'.encode($eSection['name']).'</b>');

		return self::getEmpty(
			s("Aucune ferme"),
			$text,
			'<div class="util-buttons"><a href="'.StructureUi::urlCommunityFarms($eStructure).'" class="btn btn-primary">'.s("Voir toutes les fermes").'</a></div>'
		);

	}

	/** Qui, dans ce groupe, répond de cette ferme. */
	protected static function getStructureMembers(Structure $eStructure): string {

		$names = [];

		foreach($eStructure['cUser'] as $eUser) {
			$names[] = $eUser->getName();
		}

		return implode(', ', $names);

	}

	/**
	 * Une ferme du groupe, en `util-deck`.
	 *
	 * ⚠️ **Le bloc n'est pas le lien, et c'est le cas « aucune nature » du composant.** Le jeton du
	 * plan de culture est lui-même une porte vers l'espace de production (`getShareBadges()`), et
	 * deux éléments cliquables imbriqués n'ont pas de comportement défini : le clic sur le jeton
	 * suivrait le lien du bloc. C'est donc le nom qui porte le lien, et le deck ne prend ni chevron
	 * ni bloc cliquable, comme la liste des autorisations.
	 */
	protected static function getGroupPanel(Structure $eStructure, string $owner, string $url = '', string $badges = '', string $aside = ''): string {

		$h = '<div class="util-deck">';

			$h .= '<div class="util-deck-head">';

				$h .= ($eStructure['logo'] !== NULL)
					? StructureUi::getLogo($eStructure, '2.5rem', 'util-icon-round')
					: '<div class="util-icon util-icon-round util-icon-production">'.\Asset::icon('house').'</div>';

				$h .= '<div class="util-deck-body">';

					$name = encode($eStructure['name']);

					$h .= '<div class="util-deck-title">'.($url !== '' ? '<a href="'.$url.'">'.$name.'</a>' : $name).'</div>';

					$infos = [];

					if($eStructure['legalCity']) {
						$infos[] = \Asset::icon('geo-fill').' '.encode($eStructure['legalCity']);
					}

					if($owner !== '') {
						$infos[] = \Asset::icon('producer-fill').' '.$owner;
					}

					if($infos !== []) {
						$h .= '<p>'.implode(' · ', $infos).'</p>';
					}

				$h .= '</div>';

				$h .= $aside;

			$h .= '</div>';

			if($badges !== '') {
				$h .= '<div class="util-deck-band">'.$badges.'</div>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La gestion des fermes du groupe : toutes en un tableau, le geste dans la roue crantée.
	 *
	 * Même forme que la page Équipe, et pour la même raison : une liste dense se parcourt, une
	 * grille de cartes se regarde. On vient ici pour agir sur une ferme précise, pas pour voir
	 * lesquelles il y a — c'est l'onglet qui montre.
	 *
	 * ⚠️ Les fermes projet et les fermes école n'y figurent pas : il n'y a rien à en retirer.
	 * Une ferme projet appartient au groupe par `Structure.parent` et s'en séparer serait la
	 * supprimer ; une ferme école n'a aucun rattachement à défaire.
	 */
	public function getManageStructures(Structure $eStructureCommunity, \Collection $cStructure, array $shares): string {

		if($cStructure->empty()) {
			return self::getEmpty(
				s("Aucune ferme dans ce groupe"),
				s("Il n'y a rien à gérer tant qu'aucune ferme n'a rejoint le groupe.")
			);
		}

		$h = '<div class="util-overflow-md">';
			$h .= '<table class="tr-bordered">';

				$h .= '<thead>';
					$h .= '<tr>';
						$h .= '<th colspan="2">'.s("Ferme").'</th>';
						$h .= '<th>'.s("Apportée par").'</th>';
						$h .= '<th>'.s("Ce que le groupe lit").'</th>';
						$h .= '<th></th>';
					$h .= '</tr>';
				$h .= '</thead>';

				$h .= '<tbody>';

					foreach($cStructure as $eStructure) {

						$h .= '<tr>';

							$h .= '<td class="td-min-content">'.StructureUi::getLogo($eStructure, '2rem').'</td>';

							$h .= '<td>';
								$h .= '<b>'.encode($eStructure['name']).'</b>';

								if($eStructure['legalCity'] !== NULL) {
									$h .= '<div class="color-muted font-sm">'.encode($eStructure['legalCity']).'</div>';
								}

							$h .= '</td>';

							$h .= '<td>'.self::getStructureMembers($eStructure).'</td>';

							$h .= '<td>';

								$badges = self::getShareBadges($eStructure, $shares);

								$h .= ($badges === '') ? '<span class="color-muted">'.s("Rien").'</span>' : $badges;

							$h .= '</td>';

							$h .= '<td class="text-end">';
								$h .= '<a class="btn btn-color-primary dropdown-toggle" data-dropdown="bottom-end">'.\Asset::icon('gear-fill').'</a>';
								$h .= '<div class="dropdown-list">';
									$h .= '<div class="dropdown-title">'.encode($eStructure['name']).'</div>';

									if($eStructure['duplicate'] !== NULL) {
										$h .= StructureDuplicateAiUi::getItem($eStructureCommunity, $eStructure);
										$h .= '<div class="dropdown-divider"></div>';
									}

									$h .= self::getExcludeStructureItem($eStructureCommunity, $eStructure, $shares);
								$h .= '</div>';
							$h .= '</td>';

						$h .= '</tr>';

					}

				$h .= '</tbody>';

			$h .= '</table>';
		$h .= '</div>';

		return $h;

	}

	/** Le retrait, en entrée de menu plutôt qu'en bouton bordé de rouge. */
	protected static function getExcludeStructureItem(Structure $eStructureCommunity, Structure $eStructure, array $shares): string {

		$confirm = self::getExcludeConfirm($eStructureCommunity, $eStructure, $shares);

		return '<a data-ajax="/structure/community:doExcludeStructure" post-id="'.$eStructureCommunity['id'].'" post-structure-producer="'.$eStructure['id'].'" data-confirm="'.encode($confirm).'" class="dropdown-item">'.s("Retirer du groupe").'</a>';

	}

	/**
	 * Les fermes que le groupe possède par `Structure.parent`.
	 *
	 * Ni périmètre ni retrait : elle ne lui ouvre rien puisqu'elle est à lui, et s'en séparer
	 * serait la supprimer.
	 */
	protected static function getProjectPanels(\Collection $cStructureProject): string {

		$h = '';

		foreach($cStructureProject as $eStructureProject) {
			$h .= self::getGroupPanel($eStructureProject, s("Ferme projet"), url: $eStructureProject['url']);
		}

		return $h;

	}

	protected static function isShared(Structure $eStructure, string $property): bool {

		return $eStructure['sharing'][$property];

	}

	/**
	 * Ce que le retrait annonce, et il annonce ce que l'écran montre.
	 *
	 * ⚠️ Le périmètre est filtré par `$shares`, comme `getShareBadges()` : sans ce filtre, la
	 * confirmation nomme des modules que la ligne n'affiche pas. Une ligne portant une valeur
	 * qu'aucun écran ne montre plus annonçait « Production » et « les données production,
	 * gestion » à deux centimètres l'une de l'autre — deux réponses à la même question.
	 */
	protected static function getExcludeConfirm(Structure $eStructureCommunity, Structure $eStructure, array $shares): string {

		$labels = [];

		foreach(self::getShareLabels(CommunityUi::POINT_COMMUNITY) as $property => [$color, $label]) {

			if(
				in_array($property, $shares) and
				self::isShared($eStructure, $property)
			) {
				$labels[] = mb_strtolower($label);
			}

		}

		$confirm = ($labels === [])
			? s("{name} ne fera plus partie de ce groupe.", ['name' => $eStructure['name']])
			: s("Ce groupe cessera de lire les données {modules} de {name}.", [
				'modules' => implode(', ', $labels),
				'name' => $eStructure['name']
			]);

		return $confirm.' '.s("Ce qu'il en a déjà lu lui reste.");

	}

	/**
	 * Retirer un membre du groupe : ce que le groupe cesse de lire, ce qui lui reste.
	 */
	public function breakMember(Structure $eStructure, \structure\Member $eMember, \Collection $cStructureShared, \Collection $cSection): \Panel {

		$name = encode($eMember['user']->getName());

		// ⚠️ Une perte à zéro n'est pas une perte : une ligne rouge qui annonce « 0 structure » occupe
		// la place d'un avertissement pour dire qu'il ne se passe rien. Chaque ligne se tait quand
		// son compte est nul, comme le compteur des périmètres se tait sur un groupe vide.
		$consequences = [];

		if($cStructureShared->count() === 1) {
			$consequences[] = ['icon' => 'house-dash', 'text' => s("<b>{structure}</b> quittera également le groupe.", ['structure' => encode($cStructureShared->first()['name'])])];
		} else if($cStructureShared->count() > 1) {
			$consequences[] = ['icon' => 'house-dash', 'text' => s("<b>Les {value} structures</b> apportées par {name} quitteront également le groupe.", ['value' => $cStructureShared->count(), 'name' => $name])];
		}

		$consequences[] = ['icon' => 'eye-slash', 'text' => s("<b>{name}</b> ne verra plus ce que le groupe partage.", ['name' => $name])];

		if($cSection->notEmpty()) {
			$consequences[] = ['icon' => 'diagram-3', 'text' => p("<b>{name}</b> quittera le sous-groupe {sections}.", "<b>{name}</b> quittera les sous-groupes {sections}.", $cSection->count(), ['name' => $name, 'sections' => $this->getSectionNames($cSection)])];
		}

		return new \Panel(
			id: 'panel-break-member',
			title: s("Retirer du groupe"),
			subTitle: $name,
			body: \main\DesignUi::getWeigh(
				'danger',
				'person-dash',
				s("Retirer {name} du groupe", ['name' => $name]),
				s("Il faudra une nouvelle invitation pour que cette personne revienne."),
				$consequences,
				[
					s("{name} garde ses structures et leurs données.", ['name' => $name]),
				],
				FALSE
			),
			footer: '<a data-ajax="/structure/member:doDelete" post-id="'.$eMember['id'].'" class="btn btn-danger">'.s("Retirer du groupe").'</a>'
		);

	}

	protected function getSectionNames(\Collection $cSection): string {

		$names = $cSection->makeArray(fn(Section $eSection) => encode($eSection['name']));

		if(count($names) === 1) {
			return $names[0];
		}

		$last = array_pop($names);

		return s("{list} et {last}", ['list' => implode(', ', $names), 'last' => $last]);

	}

	/**
	 * Quitter le groupe soi-même.
	 */
	public function breakLeave(Structure $eStructure, \structure\Member $eMember, int $farms, int $mine, bool $lastAdmin): \Panel {

		// ⚠️ Dit avant le geste : `MemberLib::leave()` refuse le dernier administrateur, et
		// découvrir la règle sur un échec après avoir cliqué la fait porter à l'utilisateur.
		if($lastAdmin) {
			$body = '<div class="util-block-info"><p>'.s("Vous êtes le dernier administrateur de ce groupe. Nommez-en un autre avant de partir, sans quoi personne ne pourrait plus l'administrer.").'</p></div>';
			$footer = NULL;
		} else {

			$consequences = [];

			// ⚠️ `$farms` est ce que **ce lecteur** voit et non ce que le groupe partage : borné à ses
			// sous-groupes, il perd sa promotion et non l'établissement. Et une perte à zéro ne se dit
			// pas : un membre qui n'a apporté aucune structure n'en perd aucune.
			if($farms > 0) {
				$consequences[] = ['icon' => 'eye-slash', 'text' => p("Vous ne verrez plus <b>la structure</b> partagée par le groupe.", "Vous ne verrez plus <b>les {value} structures</b> partagées par le groupe.", $farms)];
			}

			if($mine > 0) {
				$consequences[] = ['icon' => 'house-dash', 'text' => p("<b>{value} de vos structures</b> quittera également le groupe.", "<b>{value} de vos structures</b> quitteront également le groupe.", $mine)];
			}

			if($consequences === []) {
				$consequences[] = ['icon' => 'door-open', 'text' => s("Vous perdrez l'accès au groupe.")];
			}

			$body = \main\DesignUi::getWeigh(
				'danger',
				'box-arrow-right',
				s("Quitter le groupe {name}", ['name' => encode($eStructure['name'])]),
				s("Il faudra une nouvelle invitation pour revenir."),
				$consequences,
				[
					s("Vos structures et vos données restent les vôtres."),
					s("Les plans de culture déjà copiés restent là où ils sont.")
				],
				FALSE
			);

			$footer = '<a data-ajax="/structure/member:doLeave" post-id="'.$eMember['id'].'" class="btn btn-danger">'.s("Quitter le groupe").'</a>';

		}

		return new \Panel(
			id: 'panel-break-leave',
			title: s("Quitter le groupe"),
			subTitle: encode($eStructure['name']),
			body: $body,
			footer: $footer
		);

	}

	/**
	 * Supprimer le groupe.
	 */
	public function breakClose(Structure $eStructure, int $farms, int $members): \Panel {

		$consequences = [];

		if($farms > 0) {
			$consequences[] = ['icon' => 'house-x', 'text' => p("<b>La structure</b> sera détachée du groupe.", "<b>Les {value} structures</b> seront détachées du groupe.", $farms)];
		}

		if($members > 0) {
			$consequences[] = ['icon' => 'person-x', 'text' => p("<b>Le membre</b> perdra l'accès à ce que le groupe partageait.", "<b>Les {value} membres</b> perdront l'accès à ce que le groupe partageait.", $members)];
		}

		$consequences[] = ['icon' => 'graph-down', 'text' => s("<b>Les relevés de prix et les comparaisons</b> du groupe seront supprimés.")];

		return new \Panel(
			id: 'panel-break-close',
			title: static::getText($eStructure, 'delete'),
			subTitle: encode($eStructure['name']),
			body: \main\DesignUi::getWeigh(
				'danger',
				'trash',
				s("Supprimer le groupe {name}", ['name' => encode($eStructure['name'])]),
				s("Il n'y a pas de corbeille, et rien ne se restaure."),
				$consequences,
				[
					s("Chaque structure garde ses données, qui n'ont jamais cessé d'être les siennes."),
					s("Les plans de culture déjà copiés restent là où ils sont.")
				],
				FALSE
			),
			footer: '<a data-ajax="/structure/structure:doClose" post-id="'.$eStructure['id'].'" class="btn btn-danger">'.static::getText($eStructure, 'delete').'</a>'
		);

	}

	/**
	 * L'assistant de partage, et lui seul : la liste des fermes est un écran.
	 *
	 * Le panneau ne s'ouvre donc que sur une ferme choisie. Sans elle, la page qui porte la liste
	 * répond à sa place (`/groupe/{id}/mes-fermes`), et c'est la route qui redirige.
	 */
	public function shareStructure(Structure $eStructure, \structure\Community $eCommunity, int $members, Structure $eStructureProducer, System $eSystem): \Panel {

		return new \Panel(
			id: 'panel-community-share',
			title: s("Partager une ferme avec {name}", ['name' => encode($eStructure['name'])]),
			body: self::getShareSteps($eStructure, $eCommunity, $members, $eStructureProducer, $eSystem)
		);

	}

	/**
	 * Les deux ensembles : ce que cette personne partage déjà avec ce groupe, et ce qu'elle peut y
	 * ajouter. Le choix d'une ferme ouvre l'assistant, qui est un panneau.
	 */
	public function getShareChoice(Structure $eStructure, \Collection $cStructure, \Collection $cStructureShared, bool $hasProducer): string {

		// La branche des étapes déclare ses quatre feuilles, celle-ci n'en déclarait aucune :
		// ouverte en ajax depuis un écran de groupe elle trouvait shareAi.css déjà chargée, et
		// rendait sa liste en texte brut dès qu'on ouvrait son adresse. Une vue qui dépend d'un
		// écran pour être lisible n'est pas une vue.
		\Asset::css('structure', 'shareAi.css');

		$h = '';

		if($cStructureShared->notEmpty()) {

			$h .= '<h3>'.s("Partagées avec ce groupe").'</h3>';

			$h .= '<div class="util-cards util-cards-1 mb-2">';

				foreach($cStructureShared as $eStructureShared) {

					// Une div et non un lien : cette ferme est déjà partagée, le deck ne mène nulle
					// part. Pas de chevron non plus, qui promettrait un ailleurs.
					$h .= '<div class="util-deck">';

						$h .= '<div class="util-deck-head">'.self::getShareChoiceCard($eStructureShared).'</div>';

						$h .= '<div class="util-deck-content">'.self::getRightsTable($eStructure, $eStructureShared).'</div>';

						// ⚠️ Un seul geste, et c'est le retrait : le périmètre se règle droit par droit,
						// et « Modifier » n'avait plus qu'à ouvrir une seconde liste disant la même chose.
						$h .= '<div class="util-deck-band util-deck-band-doors">';
							$h .= '<a data-ajax="/structure/community:doRemoveStructure" post-id="'.$eStructure['id'].'" post-structure-producer="'.$eStructureShared['id'].'" data-confirm="'.s("Ce groupe cessera de lire les données de cette ferme. Ce qu'il en a déjà lu lui reste.").'" class="btn btn-danger-light">'.s("Retirer la ferme du groupe").'</a>';
						$h .= '</div>';

					$h .= '</div>';

				}

			$h .= '</div>';

		}

		if($cStructure->empty()) {

			// Le menu ouvre ce panneau à tout membre du groupe, y compris à qui n'a aucune ferme :
			// l'ancien bouton, lui, était gardé par les partageables et ne pouvait pas rendre vide.
			if($cStructureShared->empty()) {
				$h .= self::getEmpty(
					s("Aucune ferme à partager"),
					$hasProducer
						? s("Seul un administrateur peut partager une ferme avec un groupe. Demandez à un administrateur de la vôtre de vous nommer administrateur, ou de la partager lui-même.")
						: s("Vous partagerez vos données de production avec ce groupe le jour où vous aurez une ferme.").' <a href="/demarrer/ferme">'.s("Créer une ferme").'</a>'
				);
			}

			return $h;

		}

		$h .= '<h3>'.($cStructureShared->empty() ? s("Quelle ferme partagez-vous avec ce groupe ?") : s("Autres fermes à partager")).'</h3>';

		$h .= '<div class="util-cards util-cards-1">';

			foreach($cStructure as $eStructureShareable) {

				$h .= '<a href="/structure/community:shareStructure?id='.$eStructure['id'].'&structureProducer='.$eStructureShareable['id'].'" class="util-deck">';
					$h .= '<div class="util-deck-head">';
						$h .= self::getShareChoiceCard($eStructureShareable);
						$h .= '<div class="util-deck-arrow">'.\Asset::icon('chevron-right').'</div>';
					$h .= '</div>';
				$h .= '</a>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Ce que cette ferme accorde à ce groupe, droit par droit et public par public.
	 *
	 * ⚠️ **La position dit ce que la couleur disait mal.** La carte portait un jeton vert pour un
	 * droit accordé et le même jeton gris bordé pour un droit refusé : c'était la seule différence,
	 * et elle ne se voyait pas. Une grille met chaque public à une place fixe, et la place se lit
	 * sans comparer deux gris.
	 *
	 * ⚠️ **Un interrupteur et non une coche à relire ailleurs.** La carte rendait l'état et
	 * renvoyait le modifier dans un panneau : le lecteur voyait sa décision ici et la prenait
	 * là-bas, sur un second tableau qui disait la même chose. Chaque cellule écrit maintenant son
	 * réglage à l'instant où on la bascule — la forme du tableau que le groupe emploie pour
	 * demander. Toutes les fermes de cette liste sont administrées par le lecteur, `getSharedByUser()`
	 * ne rend que celles-là, donc aucune cellule n'est un interrupteur qu'il n'a pas le droit
	 * d'actionner.
	 */
	protected static function getRightsTable(Structure $eStructure, Structure $eStructureShared): string {

		$rights = self::getAskedRights($eStructure);

		if($rights === []) {
			return '<p class="color-muted font-sm mb-0">'.s("Ce groupe ne demande accès à aucune donnée de production de votre ferme.").'</p>';
		}

		// Les publics que le groupe demande, et eux seuls : la ferme n'a rien à accorder à un
		// public à qui le groupe ne demande rien, et l'action le refuserait.
		$audiences = fn(string $right) => $eStructure->askedCommunityAudiences($right);

		$control = function(string $right, int $audience, string $label) use ($eStructure, $eStructureShared) {

			$on = in_array($audience, self::getGrantedAudiences($eStructure, $eStructureShared, $right), TRUE);

			$required = $eStructure->requiresCommunityRight($right);

			$locked = (
				$required !== NULL and
				$on === FALSE and
				in_array($audience, self::getGrantedAudiences($eStructure, $eStructureShared, $required), TRUE) === FALSE
			);

			// ⚠️ L'analyseur HTML abaisse les noms d'attributs : la route reçoit `right`, `audience`
			// et `value`, jamais un nom de colonne en camel-case, qui ne serait pas posté du tout.
			$switch = \util\TextUi::switch([
				'data-ajax' => '/structure/community:doUpdateStructureRight',
				'post-id' => $eStructure['id'],
				'post-structure-producer' => $eStructureShared['id'],
				'post-right' => $right,
				'post-audience' => $audience,
				'post-value' => $on ? 0 : 1,
				'disabled' => $locked,
				'title' => $locked ? s("Autorisez d'abord l'accès à votre plan de culture") : NULL,
			], on: $on);

			return '<div class="community-rights-choice">'.$switch.'<span>'.$label.'</span></div>';

		};

		return self::getRightsList($eStructure, $rights, CommunityUi::POINT_PRODUCER, $audiences, $control, $eStructureShared['audiencesBringing']);

	}

	/**
	 * Le nombre de droits **au choix** à partir duquel la grille redevient plus lisible que la
	 * liste : trois droits sur deux publics font six cases et six libellés répétés, et c'est là que
	 * la matrice rend ce qu'elle coûte. Seul l'écran du groupe le lit.
	 */
	protected const RIGHTS_GRID = 3;

	/**
	 * Les droits que ce groupe demande vraiment, pour les écrans de la ferme.
	 *
	 * ⚠️ `getCommunityAudienceRights()` rend ce que le **type** permet de demander, ce qui est la
	 * bonne liste pour le groupe qui règle sa demande, et la mauvaise pour la ferme : un droit
	 * qu'aucun public ne demande y paraissait avec son nom, sa description et aucun geste dessous.
	 */
	protected static function getAskedRights(Structure $eStructure): array {

		return array_values(array_filter(
			$eStructure->getCommunityAudienceRights(),
			fn(string $right) => \structure\CommunityRightAiLib::isAsked($eStructure, $right)
		));

	}

	/**
	 * Les autorisations en liste : un droit, sa description, puis ce qui se décide.
	 *
	 * ⚠️ **Une grille ne vaut que pour une matrice, et il n'y en a pas.** Mesuré le 13/09/2026 sur
	 * les 14 groupes du parc : 6 ne demandent rien, 4 imposent leur seul droit, 3 en laissent un au
	 * choix, 1 en laisse deux, avant les droits de duplication. Ceux-là n'ont que l'équipe pour
	 * public : ils ajoutent des lignes à une case, pas de colonne. Pour une ligne et deux colonnes,
	 * l'en-tête coûtait deux phrases de quarante caractères sur trois rangs.
	 *
	 * ⚠️ **Un droit imposé n'est pas une case grisée.** Une case qui ne se coche pas demande une
	 * décision qu'on refuse ensuite, ce qui est le pire des deux états ; la phrase dit la même chose
	 * sans promettre un geste. C'est ce que l'écran faisait avec ses deux « toujours ».
	 *
	 * ⚠️ **Chaque case porte son libellé en entier**, et c'est ce qui retire l'en-tête de colonne :
	 * plus de capitales, plus de rangs, plus de tableau. Ce que la liste perd est assumé — on ne lit
	 * plus une colonne pour voir d'un coup tout ce qu'un public obtient ; à deux droits cette lecture
	 * ne sert à personne, et au-delà du seuil c'est la grille qui revient.
	 *
	 * `$audiences` dit les publics à proposer pour un droit : ceux que le groupe demande côté ferme,
	 * tous ceux que le type permet côté paramétrage, où le groupe décide justement lesquels demander.
	 */
	protected static function getRightsList(Structure $eStructure, array $rights, string $point, \Closure $audiences, \Closure $control, array $audiencesBringing): string {

		// ⚠️ La feuille se charge ici bien que cette méthode n'y pose plus aucune classe : les
		// closures des appelants rendent `community-rights-choice`, et ni `getRightsTable()` ni la
		// page d'invitation ne chargent `shareAi.css` par ailleurs.
		\Asset::css('structure', 'shareAi.css');

		$labels = CommunityUi::getAudienceLabels($eStructure, allow: TRUE, point: $point);
		$texts = CommunityUi::getRightLabels($point);

		$h = '<div class="util-cards util-cards-1">';

			foreach($rights as $right) {

				[$name, $text] = $texts[$right];

				$imposed = $eStructure->imposesCommunityRight($right);

				$h .= '<div class="util-deck'.($imposed ? ' util-deck-accent-success' : '').'">';

					$h .= '<div class="util-deck-head">';
						$h .= '<div class="util-deck-body">';
							$h .= '<div class="util-deck-title">'.$name.'</div>';
							$h .= '<p>'.$text.'</p>';
						$h .= '</div>';
					$h .= '</div>';

					if($imposed and $eStructure->restrictsCommunityReach($right)) {

						if($audiencesBringing === []) {
							$text = 'shareImposedRule';
						} else if($eStructure->reachesCommunityAudience($right, \structure\Community::MEMBER, $audiencesBringing)) {
							$text = 'shareImposedAll';
						} else {
							$text = 'shareImposedStaff';
						}

						$h .= '<div class="util-deck-band util-deck-band-state">'.StructureUi::getClass($eStructure)::getText($eStructure, $text).'</div>';

					} else if($imposed) {

						if($audiencesBringing === []) {
							$text = 'shareStaffImposedRule';
						} else {
							$text = 'shareStaffImposed';
						}

						$h .= '<div class="util-deck-band util-deck-band-state">'.StructureUi::getClass($eStructure)::getText($eStructure, $text).'</div>';

					} else {

						$h .= '<div class="util-deck-band">';

							foreach($audiences($right) as $audience) {
								$h .= $control($right, $audience, $labels[$audience]);
							}

						$h .= '</div>';

					}

				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les publics auxquels cette ferme a accordé ce droit dans ce groupe.
	 */
	protected static function getGrantedAudiences(Structure $eStructure, Structure $eStructureShared, string $right): array {

		return $eStructure->getGrantedCommunityAudiences($right, $eStructureShared[$right]);

	}

	/**
	 * La pastille et le corps d'un deck de ferme, communs aux deux listes.
	 *
	 * ⚠️ Le chevron n'est pas ici : il dit que le deck mène quelque part, et seules les fermes
	 * qu'il reste à partager mènent à un écran.
	 */
	protected static function getShareChoiceCard(Structure $eStructure): string {

		// Le logo de la ferme quand elle en a un : c'est ce qui l'identifie d'un coup d'oeil dans
		// une liste de vingt. Les autres gardent la pastille, qui tient exactement la même place.
		$h = ($eStructure['logo'] !== NULL)
			? StructureUi::getLogo($eStructure, '2.5rem', 'util-icon-round')
			: '<div class="util-icon util-icon-round util-icon-production">'.\Asset::icon('house').'</div>';

		$h .= '<div class="util-deck-body">';
			$h .= '<div class="util-deck-title">'.encode($eStructure['name']).'</div>';

			if($eStructure['legalCity']) {
				$h .= '<p>'.encode($eStructure['legalCity']).'</p>';
			}

		$h .= '</div>';

		return $h;

	}

	protected static function getShareSteps(Structure $eStructure, \structure\Community $eCommunity, int $members, Structure $eStructureProducer, System $eSystem): string {

		// Déclarés ici et non dans un constructeur : les six écrans de groupe passent par cette
		// classe, et une feuille posée une fois reste chargée toute la navigation ajax.
		\Asset::css('main', 'onboarding.css');
		\Asset::js('main', 'onboarding.js');
		\Asset::css('structure', 'shareAi.css');
		\Asset::js('structure', 'shareAi.js');

		$h = '<div class="onboarding community-share" data-step="1" onrender="StructureShareOnboarding.start(this)">';

			$h .= '<section class="onboarding-step" data-step="1" data-label="'.s("Votre système").'">';

				// ⚠️ `util-welcome` porte 2 rem de marque de fin et `onboarding-step` une gouttière
				// de 1,25 rem, qui s'ajoutent : c'est la gouttière qui cède, dans `onboarding.css`.
				$h .= \main\DesignUi::getWelcome(
					s("{name} rejoint {the}", ['name' => encode($eStructureProducer['name']), 'the' => static::getText($eStructure, 'the')]),
					'<p>'.s("Les autres membres verront vos ateliers, vos surfaces et votre système de culture. Relisez ce que vous avez déjà saisi et complétez ce qui manque.").'</p>',
					// L'abeille et non la poule : celle-ci accueille déjà les groupes de formation,
					// et deux accueils du même produit ne partagent pas leur image. Ce sont les
					// deux seules qui tiennent sur un fond coloré.
					\Asset::svg('main', 'friends/abeille.svg')
				);

				$h .= new SystemUi()->updateShare(
					$eSystem,
					'<a data-action="community-share-skip" class="btn btn-outline-primary">'.s("Passer cette étape").'</a>'
				);

			$h .= '</section>';

			$h .= '<section class="onboarding-step" data-step="2" data-label="'.s("Le partage").'">';
				$h .= self::getShareCharter($eStructure, $eCommunity, $members, $eStructureProducer);
			$h .= '</section>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * L'entrée dans un groupe : ce qu'on donne, puis ce qu'on accepte.
	 *
	 * Deux blocs de même rang, l'un décidé et l'autre lu. Le second ne se rend que si le groupe a
	 * écrit des règles : une case « J'accepte ces règles » sans règles demande d'accepter le vide,
	 * et le consentement est alors porté par les cases — cocher ce qu'on donne **est** l'accord.
	 */
	protected static function getShareCharter(Structure $eStructure, \structure\Community $eCommunity, int $members, Structure $eStructureProducer): string {

		// ⚠️ Une colonne : hors de la question de catégorie que pose le seul groupe de formation,
		// ce formulaire n'a aucun champ à libellé — deux `hidden`, la liste des autorisations, la
		// case des règles et le bouton. À deux colonnes, un groupe sans libellé pose son contenu
		// dans celle de droite, qui commence au milieu : le bouton flottait donc au centre d'un
		// écran aligné à gauche. Rien ne le montrait tant que l'étape portait d'autres champs, qui
		// donnaient le repère.
		$form = new \util\FormUi(['columns' => 1]);

		$rules = self::getCharterRules($eStructure, $eCommunity);

		$h = $form->openAjax('/structure/community:doShareStructure', ['id' => 'community-share']);

			$h .= $form->hidden('id', $eStructure);
			$h .= $form->hidden('structureProducer', $eStructureProducer);

			$h .= static::getProducerTypeChoice($form, new \Collection([$eStructureProducer['id'] => $eStructureProducer]));

			$h .= '<h3 class="mt-2">'.s("Les autorisations d'accès aux données de votre ferme").'</h3>';

			$h .= self::getRightsChoice($form, $eStructure, $eStructure->getCommunityAudience());

			if($rules !== '') {

				$h .= $rules;

				$h .= $form->group(
					content: $form->checkbox('charter', 1, ['callbackLabel' => fn($input) => $input.' <b>'.s("J'accepte ces règles").'</b>'])
				);

			}

			$h .= $form->group(
				content: $form->submit(s("Partager cette ferme")),
				attributes: ['class' => 'mt-2']
			);

		$h .= $form->close();

		return $h;

	}

	/**
	 * Le tableau des autorisations dans un formulaire, cases comprises.
	 *
	 * ⚠️ Partagé par le panneau de la ferme et par l'écran d'entrée, et c'est le sujet : les deux
	 * posent la même question au même objet, à deux moments. Deux rendus divergeraient au premier
	 * changement de libellé — précisément ce qui vient d'arriver aux deux phrases corrigées
	 * en recette, qui ne se sont corrigées qu'une fois parce qu'elles vivent au même endroit.
	 *
	 * ⚠️ **Ce que le groupe ne demande pas ne se propose pas** : une case pour un public hors de la
	 * demande laisserait accorder un droit qu'aucun écran ne montrerait ensuite, donc que personne
	 * ne pourrait retirer. La borne est la même dans `setRights()`, parce qu'un POST se forge.
	 *
	 * ⚠️ Un mot et non une case verrouillée sur un droit imposé : une case cochée qu'on ne peut pas
	 * décocher invite à cliquer, et le refus qui suit ne s'explique pas.
	 *
	 * ⚠️ Une valeur vide postée d'office, sinon on ne peut pas tout décocher : un groupe de cases
	 * toutes décochées ne poste rien, et `build()` lit « champ non posté » là où la ferme a voulu
	 * dire « plus rien ».
	 *
	 * `$checked` est ce que l'écran propose : ce que la ferme a déjà accordé sur le panneau, ce que
	 * le groupe demande à l'entrée.
	 */
	protected static function getRightsFields(\util\FormUi $form, Structure $eStructure, array $rights, \Closure $checked, array $audiencesBringing): string {

		$h = '';

		foreach($rights as $right) {
			$h .= $form->hidden($right.'[]', '');
		}

		$audiences = fn(string $right) => $eStructure->askedCommunityAudiences($right);

		$control = fn(string $right, int $audience, string $label) => '<div class="community-rights-choice">'.$form->checkbox($right.'[]', $audience, [
			'checked' => in_array($audience, $checked($right), TRUE),
			'callbackLabel' => fn(string $input) => $input.'<span>'.$label.'</span>'
		]).'</div>';

		return $h.self::getRightsList($eStructure, $rights, CommunityUi::POINT_PRODUCER, $audiences, $control, $audiencesBringing);

	}

	/**
	 * Ce que la ferme donne en entrant, décidé et non plus lu.
	 *
	 * ⚠️ **Le tableau remplace la prose.** `getCharter()` énumérait ce que le groupe lirait sans
	 * qu'on puisse y toucher : la ferme lisait son propre consentement au lieu de le donner. Le
	 * tableau dit la même chose et la rend décidable, avec les cases précochées sur ce que le
	 * groupe demande — décocher est permis, et la ferme entre quand même. Refuser un accès n'est
	 * pas refuser le groupe.
	 *
	 * ⚠️ Les relevés de prix n'y sont pas, et ce n'est pas un oubli : contribuer demande de saisir
	 * ses prix, un geste qu'on fait ou qu'on ne fait pas. Une case cochée d'avance promettrait une
	 * contribution que personne n'a décidée. Le droit se règle sur l'écran des relevés et sur la
	 * carte de la ferme.
	 *
	 * ⚠️ Un droit imposé n'a pas de case et se dit en une phrase : une case cochée qu'on ne peut
	 * pas décocher invite à cliquer.
	 */
	public static function getRightsChoice(\util\FormUi $form, Structure $eStructure, int $audienceBringing): string {

		$rights = self::getAskedRights($eStructure);

		if($rights === []) {
			return '<div class="util-block-info"><p class="mb-0">'.s("Ce groupe ne demande accès à aucune donnée de production de votre ferme. Rien n'en sera visible tant que son administrateur n'aura pas choisi ce qu'il demande.").'</p></div>';
		}

		// Précoché sur ce que le groupe demande : c'est la proposition, pas la décision.
		return self::getRightsFields($form, $eStructure, $rights, fn(string $right) => $eStructure->askedCommunityAudiences($right), [$audienceBringing]);

	}

	/**
	 * La catégorie des fermes qui entrent dans le groupe, confirmée au moment où elles entrent.
	 *
	 * Vide pour tous les types de groupe sauf la formation, qui la surcharge. Une couture et non
	 * un `isEducation()` posé sur chacun des deux écrans qui entrent : une conjonction répétée est
	 * exactement ce que le troisième écran oubliera.
	 *
	 * `$cStructureProducer` porte ce que l'écran propose, et non ce qui est coché : à plusieurs
	 * fermes, c'est le JS qui suit la sélection.
	 */
	public static function getProducerTypeChoice(\util\FormUi $form, \Collection $cStructureProducer): string {
		return '';
	}

	/**
	 * Les règles que le groupe a écrites, **sous** l'encadré factuel et jamais dedans.
	 *
	 * ⚠️ Deux blocs et non un, et c'est le fond du sujet : l'encadré bleu est engendré par le
	 * produit et dit un fait vérifiable — ce que le groupe lit, qui peut le consulter. Les règles
	 * sont un texte dont le groupe répond. Les mettre dans le même cadre ferait passer une promesse
	 * pour un fait, et le lecteur ne saurait plus laquelle des deux le produit garantit.
	 *
	 * La case « J'accepte ces règles » couvre les deux, et son libellé devient enfin exact —
	 * jusqu'ici elle demandait d'accepter des règles que personne n'avait écrites.
	 *
	 * Rien si le groupe n'a rien écrit : aucun écran ne se remplit d'un titre sans contenu.
	 */
	public static function getCharterRules(Structure $eStructure, \structure\Community $eCommunity): string {

		// L'élément vide est un vrai cas et non une sélection incomplète : `prepareShare()` le pose
		// tel quel pour une invitation qui n'est pas d'un membre, ou pour un lecteur anonyme.
		if(
			$eStructure->acceptRules() === FALSE or
			$eCommunity->empty() or
			$eCommunity['rules'] === NULL
		) {
			return '';
		}

		// `mt-2` : les titres du dépôt n'ont pas de marge haute, et celui-ci suit le bloc des
		// autorisations dont il doit se détacher — l'idiome de 24 des 40 `h3` classés.
		$h = '<h3 class="mt-2">'.s("Les règles de {name}", ['name' => encode($eStructure['name'])]).'</h3>';
		$h .= '<div class="community-share-rules">'.new \editor\EditorUi()->value($eCommunity['rules']).'</div>';

		return $h;

	}

	/**
	 * Ce que le groupe apprend quand une ferme se retire d'elle-même.
	 *
	 * Envoyé au groupe et non à qui vient de cliquer : celui-là sait ce qu'il a fait. Le groupe,
	 * lui, ne peut pas l'apprendre de son écran — une absence ne se lit pas, personne ne remarque
	 * une carte qui a disparu d'une liste de trente.
	 */
	public static function getRemovedByStructureMail(Structure $eStructureCommunity, Structure $eStructureProducer): array {

		$content = '<p>'.s("Bonjour,").'</p>';
		$content .= '<p>'.s("<b>{producer}</b> ne fait plus partie de <b>{community}</b>. Le groupe ne lit plus ses données.", [
			'producer' => encode($eStructureProducer['name']),
			'community' => encode($eStructureCommunity['name'])
		]).'</p>';

		return \main\MailSoftwareAiUi::format(
			s("{name} a quitté votre groupe", ['name' => $eStructureProducer['name']]),
			$content,
			facts: self::getRemovedFacts($eStructureProducer),
			footer: FALSE
		);

	}

	/**
	 * Le courriel envoyé quand le groupe se met à demander un périmètre qu'il ne demandait pas.
	 *
	 * ⚠️ C'est une **demande d'autoriser**, pas une notification de changement. Le gel fait qu'un
	 * réglage du groupe ne change rien aux fermes déjà rattachées : leur annoncer que « le groupe
	 * a modifié son périmètre » les informerait d'un fait sans conséquence pour elles, et le
	 * message serait faux par omission. Ce qui les concerne, c'est qu'il y a désormais quelque
	 * chose qu'elles peuvent donner, et qu'elles seules peuvent donner.
	 *
	 * N'est envoyé qu'à celles qui ne l'ont pas déjà accordé : les autres n'ont rien à faire.
	 */
	public static function getShareAskedMail(Structure $eStructureCommunity, array $names, array $labels): array {

		$content = '<p>'.s("Bonjour,").'</p>';

		$content .= '<p>'.p(
			"<b>{community}</b> demande désormais un accès supplémentaire aux fermes de ses membres :",
			"<b>{community}</b> demande désormais des accès supplémentaires aux fermes de ses membres :",
			count($labels),
			['community' => encode($eStructureCommunity['name'])]
		).'</p>';

		$content .= '<ul>';

			foreach($labels as $label) {
				$content .= '<li>'.$label.'</li>';
			}

		$content .= '</ul>';

		$content .= '<p>'.p(
			"<b>{names}</b> ne l'a pas accordé, et le groupe ne le lira pas tant que vous ne l'aurez pas fait. Vous seul pouvez le donner, depuis vos fermes dans ce groupe.",
			"<b>{names}</b> ne l'ont pas accordé, et le groupe ne le lira pas tant que vous ne l'aurez pas fait. Vous seul pouvez le donner, depuis vos fermes dans ce groupe.",
			count($names),
			['names' => encode(implode(', ', $names))]
		).'</p>';

		return \main\MailSoftwareAiUi::format(
			s("{community} vous demande un accès", ['community' => $eStructureCommunity['name']]),
			$content,
			buttonLabel: s("Voir ce que le groupe demande"),
			buttonUrl: \Lime::getUrl().StructureUi::urlCommunityFarmsMine($eStructureCommunity),
			footer: FALSE
		);

	}

	/**
	 * Ce que la ferme apprend quand le groupe la retire.
	 *
	 * Ne dit pas la même chose que l'autre : celui qui part choisit, celle qu'on retire subit et
	 * doit comprendre ce qui change pour elle. D'où la phrase de l'écran, reprise ici — sans
	 * elle, la ferme croira ses données effacées.
	 */
	public static function getRemovedByCommunityMail(Structure $eStructureCommunity, Structure $eStructureProducer): array {

		$content = '<p>'.s("Bonjour,").'</p>';
		$content .= '<p>'.s("<b>{community}</b> ne lit plus les données de <b>{producer}</b>.", [
			'community' => encode($eStructureCommunity['name']),
			'producer' => encode($eStructureProducer['name'])
		]).'</p>';
		$content .= '<p>'.s("Le retrait arrête la lecture, il ne défait pas ce qui a été lu : ce que le groupe a déjà agrégé lui reste, et rien de vos données n'a été effacé.").'</p>';

		return \main\MailSoftwareAiUi::format(
			s("{name} ne lit plus les données de votre ferme", ['name' => $eStructureCommunity['name']]),
			$content,
			facts: self::getRemovedFacts($eStructureProducer),
			footer: FALSE
		);

	}

	private static function getRemovedFacts(Structure $eStructureProducer): array {

		$eStructureProducer->expects(['name', 'legalCity']);

		$facts = [s("Ferme") => encode($eStructureProducer['name'])];

		if($eStructureProducer['legalCity'] !== NULL) {
			$facts[s("Commune")] = encode($eStructureProducer['legalCity']);
		}

		return $facts;

	}


	/**
	 * Les deux périmètres qu'un jeton peut porter : leur couleur, et leur nom.
	 *
	 * ⚠️ **Le nom ne s'écrit pas ici**, il vient de `CommunityUi::getRightLabels()` comme sur les
	 * quatre tableaux et dans le courriel. C'était une troisième table qui répétait les mêmes deux
	 * noms, et elle a divergé au premier renommage : le 10/09, « Copie du plan » est devenu
	 * « Copie du plan de culture » aux deux endroits et « Plan de culture et plan d'assolement »
	 * n'a été corrigé qu'à un seul, l'autre gardant « Plant ». Cette méthode ne décide donc plus
	 * que de la couleur.
	 *
	 * ⚠️ Publique parce que le courriel les nomme aussi : un message qui appellerait un périmètre
	 * autrement que l'écran ferait douter qu'il s'agit de la même chose.
	 */
	public static function getShareLabels(string $point): array {

		$labels = CommunityUi::getRightLabels($point);

		return [
			'shareProduction' => ['color-production', $labels['shareProduction'][0]],
			'shareProductionExport' => ['color-production', $labels['shareProductionExport'][0]],
			'shareProductionDuplicate' => ['color-production', $labels['shareProductionDuplicate'][0]],
			'shareSellingDuplicate' => ['color-commercialisation', $labels['shareSellingDuplicate'][0]],
		];

	}

	/**
	 * Ce que le groupe partage effectivement, pour l'écrire à l'écran.
	 *
	 * Passe par `hasCommunityShare()` et non par la colonne : le type peut rendre un partage
	 * inhérent sans qu'il soit enregistré.
	 */
	protected static function getShares(Structure $eStructure): array {

		$shares = [];

		foreach(self::getShareLabels(CommunityUi::POINT_COMMUNITY) as $property => $share) {
			if($eStructure->hasCommunityShare($property)) {
				$shares[] = $share;
			}
		}

		return $shares;

	}

	/**
	 * Ce que ce groupe lit de cette ferme, tel qu'elle l'a accepté en le rejoignant.
	 *
	 * Se lit sur la ferme et non sur le groupe : les deux se ressemblent tant que personne n'a
	 * élargi le périmètre du groupe depuis, et divergent ensuite.
	 */
	protected static function getShareBadges(Structure $eStructure, array $shares): string {

		// Un type qui ne permet aucun partage n'a pas de périmètre à montrer : sur les fermes des
		// étudiants d'un groupe de formation, « ce groupe ne lit rien » se répéterait sur
		// chaque carte pour dire ce qui vaut pour toutes. La section le dit une fois.
		if($shares === []) {
			return '';
		}

		$badges = [];

		foreach(self::getShareLabels(CommunityUi::POINT_COMMUNITY) as $property => [$color, $label]) {

			if(in_array($property, $shares) === FALSE) {
				continue;
			}

			// ⚠️ Une copie ou une duplication ne se dit pas sur une carte : elle y annoncerait un
			// geste à un endroit où l'on n'en fait aucun. Elle se dit au point du geste, dans le
			// menu, où elle est actionnable ou porte la raison de ne pas l'être.
			if(in_array($property, ['shareProductionExport', 'shareProductionDuplicate', 'shareSellingDuplicate'], TRUE)) {
				continue;
			}

			// ⚠️ Demandé et non accordé : le jeton se rend quand même, atténué. Le sauter faisait
			// disparaître de l'écran un droit que le groupe demande, et la ferme ne pouvait pas
			// savoir qu'elle avait quelque chose à décider — c'est ce qui rendait
			// `shareProductionExport` invisible partout depuis sa création.
			//
			if(self::isShared($eStructure, $property) === FALSE) {

				$badges[] = '<span class="badge badge-outline color-muted">'.$label.'</span>';

				continue;

			}

			// La production se lit : le partage ouvre l'espace de production de cette ferme en
			// lecture seule (`Structure::canObserveProduction()`), donc le jeton mène là plutôt
			// que d'énoncer un périmètre sans porte. Même bouton que la carte « Mes fermes »,
			// parce qu'il fait la même chose — `Structure.u.php:1804`.
			//
			// ⚠️ Les autres partages restent des jetons : la gestion n'a pas d'espace en lecture
			// seule, et un bouton qui ne mène nulle part est le défaut qu'on corrige ici.
			if($property === 'shareProduction') {
				$badges[] = '<a href="'.$eStructure->getProductionUrl($eStructure['seasonSeries']).'" class="btn btn-production-light">'.\Asset::icon('leaf').' '.$label.'</a>';
			} else {
				$badges[] = '<span class="badge badge-outline '.$color.'">'.$label.'</span>';
			}

		}

		// Quand tous les partages du groupe sont sautés ci-dessus, la boucle rend une liste vide,
		// et le conteneur se posait quand même sur chaque carte.
		if($badges === []) {
			return '';
		}

		return '<div class="util-chips">'.implode('', $badges).'</div>';

	}

	/**
	 * L'écran des membres tel que le voit qui administre : les invitations, la table, les gestes.
	 *
	 * ⚠️ **Une duplication assumée de `MemberUi::getManage()`, et non une méthode partagée.** Ce
	 * qu'un groupe retire à cette table — le bloc des utilisateurs fantômes, réservé aux fermes
	 * par `acceptGhost()`, les colonnes de présence, que `assertProduction()` interdit, les modes
	 * d'affichage du bandeau — en fait une autre table, et la partager forcerait une garde dans
	 * chaque cellule. Ce qu'un groupe y ajoute, ses sous-groupes, n'existe que chez lui.
	 *
	 * ⚠️ Le rangement est celui de l'annuaire, `getMembersOrder()` : c'est la même page vue par
	 * deux lecteurs, et deux ordres différents pour les mêmes personnes se contrediraient dès
	 * qu'un administrateur décrirait une ligne à un membre.
	 */
	public function getMembersManage(Structure $eStructure, \Collection $ccMember, \Collection $cRole, \Collection $cInvite, \Collection $cInviteFinished, \Collection $cSection): string {

		$h = '';

		// ⚠️ La colonne des sous-groupes ne se rend que là où il y en a : vide sur toutes les
		// lignes, elle annoncerait un rangement qui n'existe pas dans ce groupe.
		$hasSection = $cSection->notEmpty();

		if($cInvite->notEmpty()) {
			$h .= '<h3>'.s("Les invitations en cours").'</h3>';
			$h .= new InviteUi()->getManage($cInvite, $hasSection ? static::getText($eStructure, 'section') : NULL);
		}

		// Les membres se titrent dès qu'une liste d'invitations partage l'écran avec eux, en cours
		// au-dessus ou terminées en dessous : sans titre, leur table se lirait comme une suite de la
		// liste voisine.
		if($cInvite->notEmpty() or $cInviteFinished->notEmpty()) {
			$h .= '<h3>'.s("Les membres {ofStructure}", ['ofStructure' => StructureUi::getText($eStructure, 'of')]).'</h3>';
		}

		// ⚠️ Même règle pour les fermes, mais la condition est le **type** et non le contenu : un
		// groupe qui héberge des fermes garde la colonne même vide, parce que c'est sa raison
		// d'être et qu'un membre sans ferme est une information. Un groupe qui n'en héberge pas
		// n'a rien à réserver — et c'est ce même drapeau qui décide de charger la donnée, donc la
		// colonne et son contenu ne peuvent pas diverger.
		$hasStructure = $eStructure->acceptGroupStructure();

		$h .= '<div class="util-overflow-md">';

			$h .= '<table class="tr-even">';

				$h .= '<thead>';
					$h .= '<tr>';
						$h .= '<th colspan="2">'.s("Membre").'</th>';
						if($hasStructure) {
							$h .= '<th>'.s("Ferme").'</th>';
						}
						if($hasSection) {
							$h .= '<th>'.static::getText($eStructure, 'section').'</th>';
						}
						$h .= '<th>'.s("Ajouté").'</th>';
						$h .= '<th></th>';
					$h .= '</tr>';
				$h .= '</thead>';

				foreach(static::getMembersOrder($eStructure, $ccMember, $cRole) as [$label, $cMember]) {

					$h .= '<tbody>';
						$h .= '<tr class="tr-title">';
							$h .= '<td colspan="10">'.$label.'</td>';
						$h .= '</tr>';
					$h .= '</tbody>';

					$h .= '<tbody>';

						foreach($cMember as $eMember) {

							$h .= '<tr>';

								$h .= '<td class="td-min-content">';
									$h .= \user\UserUi::getVignette($eMember['user'], '2rem');
								$h .= '</td>';

								$h .= '<td>';
									$h .= '<a href="/structure/member:show?id='.$eMember['id'].'" class="util-button">'.encode($eMember['user']->getName()).'</a>';
								$h .= '</td>';

								if($hasStructure) {

									$h .= '<td>';

										$cStructureMember = $eMember['cStructure'];

										if($cStructureMember->empty()) {

											// Le tiret de la valeur absente, comme la colonne
											// voisine : une cellule vide se lit comme une colonne
											// qui n'a pas chargé, un tiret dit que ce membre n'a
											// rattaché aucune ferme.
											$h .= '<span class="color-muted">—</span>';

										} else {

											// ⚠️ Une ferme par ligne et non une liste en ligne : un
											// producteur peut en rattacher plusieurs, et des noms
											// séparés par des virgules se lisent comme un seul nom
											// composé.
											foreach($cStructureMember as $eStructureMember) {

												$name = encode($eStructureMember['name']);
												$url = $eStructureMember['url'];

												$h .= '<div>'.($url !== '' ? '<a href="'.$url.'">'.$name.'</a>' : $name).'</div>';

											}

										}

									$h .= '</td>';

								}

								if($hasSection) {

									$h .= '<td>';

										$cSectionMember = $eMember['cSection'];

										if($cSectionMember->empty()) {

											// Le tiret de la valeur absente, forme du dépôt : une
											// cellule vide se lit comme une colonne qui n'a pas
											// chargé, un tiret dit que la personne n'est rangée
											// nulle part.
											$h .= '<span class="color-muted">—</span>';

										} else {

											// `badge badge-outline` et non `util-chip` : le jeton porte
											// un chevron en fond, qui annonce un menu. C'est déjà la
											// forme que la table des invitations, juste au-dessus,
											// donne à la même information.
											foreach($cSectionMember as $eSection) {
												$h .= '<span class="badge badge-outline">'.encode($eSection['name']).'</span> ';
											}

										}

									$h .= '</td>';

								}

								$h .= '<td>';
									$h .= '<span class="color-muted font-sm">'.\util\DateUi::numeric($eMember['createdAt'], \util\DateUi::DATE).'</span>';
								$h .= '</td>';

								$h .= '<td class="text-end">';
									$h .= self::getMembersManageActions($eStructure, $eMember, $cSection);
								$h .= '</td>';

							$h .= '</tr>';

						}

					$h .= '</tbody>';

				}

			$h .= '</table>';

		$h .= '</div>';

		if($cInviteFinished->notEmpty()) {
			$h .= '<h3 class="mt-2">'.s("Les invitations terminées").'</h3>';
			$h .= new InviteUi()->getManage($cInviteFinished, $hasSection ? static::getText($eStructure, 'section') : NULL);
		}

		return $h;

	}

	/**
	 * Ce qu'une ligne de membre propose dans un groupe.
	 *
	 * ⚠️ Sur sa **propre** ligne, un seul geste et c'est le sien : partir. Le retrait y est refusé
	 * par `MemberLib::delete()` (`Member::deleteItself`), et un menu qui n'aurait alors qu'une
	 * entrée se lirait comme une panne — le lecteur ouvre les autres lignes pour comprendre ce qui
	 * manque. Le départ passe par le panneau `community:breakLeave`, déjà employé par le menu du
	 * bandeau : une rupture a trois choses à dire, ce qui reste, ce qui part, ce qui ne se défait
	 * pas, et le `confirm()` du navigateur les aplatirait en une phrase.
	 */
	protected static function getMembersManageActions(Structure $eStructure, Member $eMember, \Collection $cSection): string {

		$h = '<a class="btn btn-color-primary dropdown-toggle" data-dropdown="bottom-end">'.\Asset::icon('gear-fill').'</a>';

		$h .= '<div class="dropdown-list">';

			$h .= '<div class="dropdown-title">'.encode($eMember['user']->getName()).'</div>';

			$disabled = fn(string $label, string $reason) => '<a class="dropdown-item disabled"><div class="util-item-label">'.$label.'</div><div class="util-item-more">'.$reason.'</div></a>';

			$isSelf = $eMember->canSelf();

			// ⚠️ L'entrée garde sa place même refusée : un menu amputé se lit comme une panne. Le
			// retrait fait exception — sur sa propre ligne, il dirait « pas vous » là où « quitter
			// le groupe » existe vraiment.
			$label = $cSection->empty()
				? s("Changer le rôle")
				: s("Rôle et {sections}", ['sections' => mb_strtolower(static::getText($eStructure, 'section', 2))]);

			// ⚠️ Sur sa propre ligne, la raison dit **qui peut le faire à sa place**, et non ce qui
			// débloquerait le geste : personne ne change jamais son propre rôle ici, quel que soit
			// le nombre d'administrateurs — `Member::canUpdateRole()` refuse sur le seul fait que
			// c'est soi. Un second administrateur ne rend donc pas le geste possible, il rend
			// possible que quelqu'un d'autre le fasse. Même formulation que l'écran des fermes,
			// `MemberUi::getManageActions()` : deux exemplaires de cette phrase existent, et ils
			// divergent en silence dès que l'un des deux est réécrit seul.
			if($eMember->canUpdateRole()) {
				$h .= '<a href="/structure/member:update?id='.$eMember['id'].'" class="dropdown-item">'.$label.'</a>';
			} else if($isSelf === FALSE) {
				$h .= $disabled($label, s("Ce rôle ne peut pas être changé ici"));
			} else if($eStructure['admins'] <= 1) {
				$h .= $disabled($label, s("Vous êtes le seul administrateur : invitez-en un autre, lui pourra changer votre rôle"));
			} else {
				$h .= $disabled($label, s("Un autre administrateur peut le changer"));
			}

			$h .= '<div class="dropdown-divider"></div>';

			if($isSelf) {

				$h .= '<a href="/structure/community:breakLeave?id='.$eStructure['id'].'" class="dropdown-item">'.s("Quitter le groupe").'</a>';

			} else {

				// Sortir d'un groupe détache des fermes et vide des sous-groupes : le panneau les
				// chiffre avant de faire le geste.
				$h .= '<a href="/structure/community:breakMember?id='.$eStructure['id'].'&member='.$eMember['id'].'" class="dropdown-item">'.s("Retirer du groupe").'</a>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le cadenas d'un sous-groupe clos.
	 *
	 * ⚠️ Le `title` dit ce que l'état **fait** et jamais ce qu'il est : l'adjectif devait s'accorder
	 * avec un nom qui change selon le type de groupe, et aucune forme n'était juste sur les trois.
	 * Mesuré, la clôture n'a qu'un effet — `getByStructure(onlyOpen: TRUE)`, lu par `invite.p.php`.
	 */
	public static function getSectionClosed(): string {

		return '<span class="color-muted" title="'.s("Ne reçoit plus d'invitation").'">'.\Asset::icon('lock-fill').'</span>';

	}

	/**
	 * L'accueil d'un onglet dont rien n'est encore allumé.
	 *
	 * ⚠️ **Sans bouton** : le geste est juste dessous, une carte par fonctionnalité avec son
	 * interrupteur. Un bouton en tête renverrait à ce qu'on a déjà sous les yeux.
	 *
	 * ⚠️ **Aucun état stocké, la condition est l'état lui-même** : rien d'allumé parmi ce que le
	 * type permet. Il revient donc si l'administrateur éteint tout, et c'est juste — « votre groupe
	 * attend ce qu'il doit faire » redevient vrai. Mémoriser qu'il a déjà été vu demanderait une
	 * colonne et une migration pour ne pas réafficher une phrase exacte.
	 *
	 * ⚠️ Le compte se fait sur ce que le **type** permet, jamais sur la table entière : un groupe
	 * de comptabilité n'a que deux cartes, et mesurer sur les quatre le laisserait accueilli à vie.
	 */
	protected static function getFeaturesWelcome(Structure $eStructure): string {

		foreach(array_keys(self::getFeatureLabels($eStructure)) as $feature) {

			if($eStructure->acceptCommunityFeatureSetting($feature) and $eStructure->conf(Feature::class)[$feature]) {
				return '';
			}

		}

		// ⚠️ Un autre titre que celui du tableau de bord, qui porte le même sujet et le même
		// bouton vers ici : cliquer pour retomber sur le même titre donne l'impression de
		// n'avoir pas bougé. Les deux blocs ne font pas le même travail. Celui du tableau de
		// bord **appelle** vers un ailleurs et a le droit d'être engageant, celui-ci **accueille**
		// quelqu'un qui est arrivé et n'a qu'à dire où l'on est et ce qui manque, la suite
		// étant sous ses yeux.
		return \main\DesignUi::getWelcome(
			s("Ce groupe n'a encore aucune fonctionnalité"),
			'<p>'.s("Rattacher des fermes, comparer des cultures, tenir des relevés de prix, proposer des formations : chaque fonctionnalité s'allume quand vous en avez besoin, et se referme si elle ne sert pas.").'</p>',
			// Pas l'abeille, qui accueille déjà l'entrée d'une ferme : deux accueils du même produit
			// ne partagent pas leur image.
			//
			// ⚠️ Et surtout pas l'oiseau, qui était ici : son corps vaut `#eef0ff` et le fond de
			// `util-welcome` va de `#E1F7E8` à `#E6FBF7` (trois quasi-blancs, dont il ne restait à
			// l'écran que l'œil noir et le bec rouge). La règle « seules l'abeille et l'oiseau
			// tiennent » a été mesurée sur le fond **de marque**, sombre, et elle s'inverse sur un
			// fond clair : là, c'est l'oiseau qui disparaît et les silhouettes à contour `#1D4B53`
			// qui tiennent. Vu dans Chrome par la session AMAP sur ce même composant.
			\Asset::svg('main', 'friends/coccinelle-rouge.svg'),
			class: 'mb-2'
		);

	}

	/**
	 * Les fonctionnalités du groupe, une carte par interrupteur.
	 *
	 * ⚠️ **Une fonctionnalité que le type ne permet pas n'apparaît pas du tout.** Ce n'est pas une
	 * entrée désactivée portant sa raison : cette forme-là vaut pour ce qu'on n'a pas le droit de
	 * faire, pas pour ce qui n'existe pas. Montrer « Relevés de prix » barré à un groupe de
	 * comptabilité reviendrait à lui proposer de changer de nature.
	 *
	 * ⚠️ Les membres, les fermes et le partage des données n'y sont pas : ils sont structurants.
	 * Un groupe existe pour rassembler des fermes, et éteindre les fermes le viderait de son objet.
	 */
	public function getFeatures(Structure $eStructure): string {

		$h = self::getFeaturesWelcome($eStructure);

		$h .= '<div class="util-cards">';

			foreach(self::getFeatureLabels($eStructure) as $feature => [$icon, $title, $text]) {

				if($eStructure->acceptCommunityFeatureSetting($feature) === FALSE) {
					continue;
				}

				$active = $eStructure->conf(Feature::class)[$feature];

				$h .= '<div class="util-deck">';
					$h .= '<div class="util-deck-head">';

						// ⚠️ Jamais `util-icon-muted` : le gris dit « indisponible », or ce qui est éteint
						// reste disponible — et ce qui ne l'est pas ne s'affiche pas du tout.
						$h .= '<div class="util-icon util-icon-round '.($active ? 'util-icon-active' : 'util-icon-inactive').'">'.\Asset::icon($icon).'</div>';

						$h .= '<div class="util-deck-body">';
							$h .= '<div class="util-deck-title">'.$title.'</div>';
							$h .= '<p>'.$text.'</p>';

						$h .= '</div>';

						// ⚠️ **Un interrupteur et non deux boutons.** Le réglage est binaire et s'applique
						// aussitôt, sans bouton d'enregistrement : c'est ce que `TextUi::switch()` rend,
						// et c'est déjà la forme du même geste ailleurs dans le produit. Deux libellés
						// qui alternent obligent à lire le bouton pour connaître l'état, quand la
						// position du curseur le dit sans le lire.
						//
						// ⚠️ Éteindre n'efface rien, et la carte le dit au moment où l'on éteint plutôt
						// qu'en légende permanente : un groupe qui referme ses relevés garde ses
						// cotations et les retrouve entières en rallumant.
						$attributes = [
							'data-ajax' => '/structure/community:doUpdateFeature',
							'post-id' => $eStructure['id'],
							'post-feature' => $feature,
							'post-value' => $active ? 0 : 1,
						];

						if($active) {
							$attributes['data-confirm'] = s("Les données déjà saisies sont conservées et reviendront si vous la rallumez. Continuer ?");
						}

						$h .= '<div class="util-deck-aside">'.\util\TextUi::switch($attributes, $active, textOn: s("Oui"), textOff: s("Non")).'</div>';

					$h .= '</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * L'icône, le nom et ce que chaque fonctionnalité ouvre.
	 *
	 * Les libellés vivent ici et non dans la déclaration : `CommunityFeatureAiLib` dit qui a droit
	 * à quoi et se lit depuis un Lib comme depuis une page, où `s()` n'a pas cours.
	 *
	 * ⚠️ Publique parce que la mise en route les reprend : l'étape des fonctionnalités montre les
	 * mêmes cartes que l'onglet, et deux tables de libellés divergeraient au premier mot changé.
	 */
	public static function getFeatureLabels(Structure $eStructure): array {

		return [
			'hasCommunityVegetable' => ['flower3', s("Maraîchage"), s("Comparer les plans de culture des fermes {of}, accéder aux données régionalisées des itinéraires techniques et des variétés.", ['of' => StructureUi::getText($eStructure, 'of')])],
			'hasQuotation' => ['tag', s("Relevés de prix"), s("Tenir des relevés de prix entre les fermes {of} et lire leurs médianes.", ['of' => StructureUi::getText($eStructure, 'of')])],
			'hasCommunitySections' => ['diagram-3', StructureUi::getText($eStructure, 'section', 2), StructureUi::getText($eStructure, 'sectionsFeature')],
			'hasCommunitySchools' => ['mortarboard', s("Fermes école"), s("Donner accès aux copies anonymisées de fermes réelles, pour apprendre sur des données vraies.")],
			'hasCommunityLearning' => ['book', s("Formations"), $eStructure->isNetwork() ?
				s("Écrire des formations, programmer leurs dates et recevoir les inscriptions.") :
				s("Écrire des formations et les ouvrir aux promotions du groupe.")],
		];

	}

	/**
	 * Ce que le groupe demande, droit par droit et public par public.
	 *
	 * ⚠️ **Une ligne imposée se rend en lecture, avec sa raison.** Un groupe de formation partage
	 * la production parce qu'il est un groupe de formation : la case n'existe pas, mais la taire
	 * ferait croire qu'il ne partage rien. C'est le régime `IMPOSED`, et c'est ce qui a fait sortir
	 * la déclaration de `CommunityFeatureAiLib`.
	 *
	 * ⚠️ **Le nombre de colonnes vient de l'appelant.** Le paramétrage est à deux comme tous ses
	 * formulaires ; le parcours est à une, comme ses autres étapes. À deux dans un panneau de
	 * 38 rem, le libellé prenait la colonne de gauche et il ne restait qu'une quinzaine de rem à
	 * un tableau qui réclame `1fr + 2 × 11rem` : chaque mot d'un nom de droit tombait sur sa
	 * propre ligne.
	 *
	 * `$rights` borne le tableau aux droits que la mise en route pose (`CommunityStartAiUi`) ; le
	 * paramétrage n'en passe pas et montre tous les droits à public du groupe.
	 *
	 * ⚠️ `$title` à FAUX quand l'appelant a déjà titré le tableau : l'étape du partage pose sa
	 * question en `h2` et dit dans son aide que la ferme reste libre de refuser, et le titre d'ici
	 * le redisait juste dessous. Le paramétrage n'a pas d'autre titre et garde celui-ci.
	 */
	public function getShareForm(Structure $eStructure, Community $eCommunity, array $counts = [], int $columns = 2, ?string $skip = NULL, ?string $origin = NULL, ?array $rights = NULL, bool $title = TRUE): string {

		$rights ??= $eStructure->getCommunityAudienceRights();

		$optional = array_values(array_filter(
			$rights,
			fn(string $right) => $eStructure->imposesCommunityRight($right) === FALSE
		));

		if($rights === []) {
			return '';
		}

		// La ligne d'un périmètre se dimensionne par cette feuille, et l'écran du paramétrage ne
		// la chargeait pas : elle n'était posée que par l'assistant de partage.
		\Asset::css('structure', 'shareAi.css');

		// ⚠️ **Plus de `<form>` ici.** Chaque interrupteur porte sa propre action et poste seul :
		// une balise de formulaire sans champ ni bouton n'aurait plus rien à soumettre, et son
		// `data-ajax-form` désignerait une route que plus personne n'appelle.
		$h = '';

			// ⚠️ Le titre est le libellé du groupe de champs et non un `h3` au-dessus : ce tableau
			// n'ayant pas de libellé à gauche, `form-columns` laisserait sa colonne vide et le
			// titre s'alignerait seul à 320 px de ce qu'il nomme.
			if($optional === []) {

				if($title) {
					$h .= '<h4>'.s("Les données partagées dans le groupe").'</h4>';
				}

			} else {

				if($title) {
					$h .= '<h4>'.s("Les données que vous demandez aux producteurs de partager (et qu'ils sont libres de refuser de vous partager)").'</h4>';
				}

				// Le rappel se lit **entre** le titre et le tableau : il dit ce que les interrupteurs
				// ne peuvent pas dire — demander n'est pas obtenir — et il le dit avant qu'on bascule,
				// pas après. Au-dessus du titre, il parlait avant qu'on sache de quoi ; sous le
				// tableau, il se lisait comme le commentaire d'un bouton qui n'existe plus.
				$h .= self::getShareCounts($eStructure, $counts);

			}

			$h .= self::getShareTable($eStructure, $rights, $origin);

			// ⚠️ **Plus de bouton.** Chaque interrupteur écrit son réglage à l'instant où on le
			// bascule : un « Enregistrer » n'aurait plus rien à enregistrer, et un bouton qui
			// n'écrit rien ment sur ce qu'il fait. Le parcours pose alors son propre pied, dont le
			// « Continuer » ne fait qu'avancer — la forme de l'étape des fonctionnalités.
			$h .= $skip ?? '';

		return $h;

	}

	/**
	 * Le tableau droit × public, en `util-table`.
	 *
	 * ⚠️ **`util-table` et non un `<table>`.** Un tableau met ses cellules d'en-tête en petites
	 * capitales — bloc `table th, .util-table-header, .util-table-head > *`, `reboot.css:1354` —,
	 * ce qui est le dessin d'une étiquette de colonne et non celui
	 * du sujet d'une ligne : le nom d'un droit en `<th>` obligeait à contre-braquer la casse, la
	 * graisse, la couleur et les marges dans la feuille du package, c'est-à-dire à réécrire le
	 * châssis d'un composant chez soi. La grille n'a pas de `<th>` dans son corps, donc plus rien
	 * à défaire.
	 *
	 * ⚠️ **Un en-tête de colonne dit le geste et non le seul nom du public.** Au-dessus d'une case,
	 * « l'équipe du réseau » ne dit pas ce que cocher fait ; c'est pourquoi les libellés se prennent
	 * en `allow`, comme dans la liste.
	 *
	 * ⚠️ **La cellule est une closure parce que la grille et la liste doivent dire la même chose.**
	 * Un même écran rend l'une ou l'autre selon `RIGHTS_GRID`, sur les mêmes droits et les mêmes
	 * publics : deux rendus écrits séparément divergeraient au premier changement de colonne, ce
	 * qui vient d'arriver aux deux phrases corrigées.
	 */
	protected static function getRightsGrid(Structure $eStructure, array $rights, string $point, \Closure $cell): string {

		// Les colonnes du tableau vivent dans cette feuille : elle se déclare ici et non chez ses
		// appelants, sinon celui qui l'oublie rend un tableau dont toutes les cellules s'empilent —
		// vu sur le panneau de la ferme, atteint par une adresse directe.
		\Asset::css('structure', 'shareAi.css');

		$labels = CommunityUi::getRightLabels($point);
		$audiences = CommunityUi::getAudienceLabels($eStructure, allow: TRUE, point: $point);

		// Les colonnes vivent dans `shareAi.css`, où une media query peut les resserrer : c'est ce
		// que `util-table` offre de plus qu'un `<table>`. Seul leur **nombre** vient d'ici, parce
		// que la feuille ne connaît pas la liste des publics.
		$h = '<div class="util-table community-rights" style="--community-rights-audiences: '.count($audiences).'">';

			$h .= '<div class="util-table-head">';
				$h .= '<div></div>';
				foreach($audiences as $label) {
					$h .= '<div>'.$label.'</div>';
				}
			$h .= '</div>';

			foreach($rights as $right) {

				[$name, $text] = $labels[$right];

				$h .= '<div class="util-table-row">';

					$h .= '<div>';
						$h .= '<div class="util-action">';
							$h .= '<span>'.$name.'</span>';
						$h .= '</div>';
						$h .= '<div class="color-muted font-sm">'.$text.'</div>';
					$h .= '</div>';

					foreach(array_keys($audiences) as $audience) {
						$h .= '<div class="text-center">'.$cell($right, $audience).'</div>';
					}

				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Ce que le groupe demande, droit par droit et public par public.
	 *
	 * ⚠️ Un mot et non une case verrouillée sur un droit imposé : une case cochée qu'on ne peut pas
	 * décocher invite à cliquer, et le refus qui suit ne s'explique pas.
	 *
	 * ⚠️ Une valeur vide postée d'office, sinon on ne peut pas tout décocher : un groupe de cases
	 * toutes décochées ne poste rien, et `build()` lit « champ non posté » là où l'administrateur a
	 * voulu dire « plus rien ».
	 */
	protected static function getShareTable(Structure $eStructure, array $rights, ?string $origin): string {

		// ⚠️ **Tous les publics du droit, et non ceux que le groupe demande déjà** : c'est ici qu'il
		// décide lesquels demander. Côté ferme, la liste s'arrête à ce qui est demandé — une ferme
		// n'a rien à accorder à un public dont le groupe n'a pas voulu.
		$audiences = fn(string $right) => \structure\CommunityRightAiLib::getAudiences($right);

		// ⚠️ **Un interrupteur qui s'applique tout de suite, et non une case à enregistrer.** Chaque
		// public est un réglage indépendant : demander l'accès au plan de culture n'a rien à voir
		// avec l'accès à sa copie, et un bouton unique en bas faisait dépendre quatre décisions du
		// même geste. C'est déjà la forme des fonctionnalités du groupe, et `util\TextUi::switch()`
		// est ce que le dépôt appelle « un réglage binaire appliqué tout de suite ».
		//
		// ⚠️ L'analyseur HTML abaisse les noms d'attributs : la route reçoit `right`, `audience` et
		// `value`, jamais un nom de colonne en camel-case, qui ne serait pas posté du tout.
		$switch = function(string $right, int $audience) use ($eStructure, $origin) {

			$on = in_array($audience, $eStructure->askedCommunityAudiences($right), TRUE);

			return \util\TextUi::switch([
				'data-ajax' => '/structure/community:doUpdateShare',
				'post-id' => $eStructure['id'],
				'post-right' => $right,
				'post-audience' => $audience,
				'post-value' => $on ? 0 : 1,
				'post-origin' => $origin,
			], on: $on);

		};

		$control = fn(string $right, int $audience, string $label) => '<div class="community-rights-choice">'.$switch($right, $audience).'<span>'.$label.'</span></div>';

		$optional = array_filter($rights, fn(string $right) => $eStructure->imposesCommunityRight($right) === FALSE);

		// ⚠️ **Le seuil se lit sur les droits au choix du groupe**, que la ferme ne voit pas en grille :
		// un réseau en porte trois depuis la duplication et rend la grille ici seulement. Une case
		// sans objet rend un tiret, jamais un interrupteur que l'action refuserait.
		if(count($optional) >= self::RIGHTS_GRID) {

			$cell = function(string $right, int $audience) use ($eStructure, $audiences, $switch) {

				if($eStructure->imposesCommunityRight($right)) {
					return '<span class="color-success">'.s("toujours").'</span>';
				} else if(in_array($audience, $audiences($right), TRUE)) {
					return $switch($right, $audience);
				} else {
					return '-';
				}

			};

			return self::getRightsGrid($eStructure, $rights, CommunityUi::POINT_COMMUNITY, $cell);

		}

		return self::getRightsList($eStructure, $rights, CommunityUi::POINT_COMMUNITY, $audiences, $control, []);

	}

	/**
	 * Les règles que le groupe écrit et dont il répond.
	 *
	 * ⚠️ Un second bloc et non un champ de plus dans le premier : les deux forment un engagement,
	 * mais l'un est un périmètre technique et l'autre une promesse. Deux formulaires indépendants,
	 * qui postent sur deux routes, donc deux blocs et deux boutons — mis bout à bout, l'onglet se
	 * lirait comme un seul formulaire avec un « Enregistrer » tombé au milieu.
	 */
	public function getRulesForm(Structure $eStructure, Community $eCommunity): string {

		$form = new \util\FormUi();

		$h = $form->openAjax('/structure/community:doUpdateRules');

			$h .= $form->hidden('id', $eStructure['id']);

			$h .= $form->dynamicGroup($eCommunity, 'rules');

			$h .= $form->group(content: $form->submit(s("Enregistrer")));

		$h .= $form->close();

		return $h;

	}

	/**
	 * La règle que l'écran ne peut pas montrer, et rien d'autre.
	 *
	 * ⚠️ Les compteurs ont quitté ce bloc pour la ligne de chaque périmètre : ils y répétaient les
	 * deux mêmes noms à trente pixels d'écart, et le lecteur devait apparier « Production » du haut
	 * avec « Production » du bas. Ce qui reste est une règle métier — demander n'est pas obtenir —,
	 * que le compte rend vérifiable mais n'énonce pas.
	 *
	 * ⚠️ Il disparaît avec eux quand le groupe n'a aucune ferme : sa phrase parle de fermes qui
	 * n'existent pas encore, et elle dirait où agir sur un écran où il n'y a rien à faire.
	 */
	protected static function getShareCounts(Structure $eStructure, array $counts): string {

		if(($counts['total'] ?? 0) === 0) {
			return '';
		}

		return '<div class="util-block-info"><p class="mb-0">'
			.s("Demander un accès ne l'accorde pas, les données des producteurs n'appartiennent qu'à eux. Chaque ferme accorde explicitement l'accès à ses données depuis {link}.", ['link' => '<a href="'.StructureUi::urlCommunityFarmsMine($eStructure).'">'.s("ses propres fermes").'</a>'])
			.'</p></div>';

	}

	/**
	 * Ce que porte l'accueil d'un groupe qui n'a rien à montrer.
	 *
	 * L'invitation vient en premier : c'est le seul geste qui fasse venir des fermes qu'on n'a
	 * pas soi-même. Le partage vient ensuite, pour celui qui en a une — et seulement là où le
	 * groupe accepte d'en héberger.
	 */
	public function getEmptyActions(Structure $eStructure, bool $canManage, bool $hasShareable): string {

		$boutons = [];

		// Partager passe devant quand c'est possible : le geste est immédiat et son effet visible,
		// là où une invitation attend une réponse. L'invitation reste le seul recours pour faire
		// venir les fermes qu'on n'a pas.
		if($hasShareable) {
			$boutons[] = '<a href="'.StructureUi::urlCommunityFarmsMine($eStructure).'" class="btn btn-primary">'.s("Partager une ferme").'</a>';
		}

		if($canManage) {
			$boutons[] = '<a href="/structure/invite:createMember?structure='.$eStructure['id'].'" class="btn '.($hasShareable ? 'btn-outline-primary' : 'btn-primary').'">'.\Asset::icon('person-plus-fill').' '.s("Inviter des membres").'</a>';
		}

		return ($boutons === []) ? '' : '<div class="util-buttons">'.implode('', $boutons).'</div>';

	}

	/**
	 * Le rendu de l'accueil, un composant par état.
	 *
	 * ⚠️ Aucune condition de produit ici : l'état arrive calculé par `StructureCommunityLib` ou
	 * par le Lib du type, et cette méthode ne fait que le traduire. C'est ce qui permet à un
	 * groupe qui enchaîne autrement de ne rien réécrire de ce rendu.
	 */
	public function getOnboarding(Structure $eStructure, array $onboarding): string {

		$ui = new \main\DesignUi();

		return match($onboarding['state']) {

			StructureCommunityLib::SECTION => $ui->getStart(
				\Asset::icon('diagram-3'),
				static::getText($eStructure, 'sectionStartTitle'),
				static::getText($eStructure, 'sectionStartText'),
				buttons: '<a href="'.StructureUi::urlCommunitySections($eStructure).'" class="btn btn-primary">'.static::getText($eStructure, 'sectionStartButton').'</a>'
			),

			StructureCommunityLib::INVITE => $ui->getStart(
				\Asset::icon('person-plus'),
				static::getText($eStructure, 'membersInvite'),
				static::getText($eStructure, 'membersInviteText'),
				buttons: $this->getOnboardingInvite($eStructure, $onboarding)
			),

			StructureCommunityLib::SECTION_CREATED => $this->getOnboardingCreated($eStructure, $onboarding),

			StructureCommunityLib::SHARE => $ui->getStart(
				\Asset::icon('share'),
				s("Partagez votre ferme avec {value}", static::getText($eStructure, 'the')),
				$this->getOnboardingShareText($eStructure),
				buttons: '<a href="'.StructureUi::urlCommunityFarmsMine($eStructure).'" class="btn btn-primary">'.s("Partager ma ferme").'</a> <a href="'.$this->getOnboardingFarmUrl().'" class="btn btn-outline-primary">'.s("Créer une ferme").'</a>'
			),

			StructureCommunityLib::FARM => $ui->getStart(
				\Asset::icon('house-add'),
				s("Créez votre ferme"),
				$this->getOnboardingFarmText($eStructure),
				buttons: '<a href="'.$this->getOnboardingFarmUrl().'" class="btn btn-primary">'.s("Créer ma ferme").'</a>'
			),

			default => ''

		};

	}

	/**
	 * Le bandeau d'arrivée : ce qui vient d'être créé, la suite, une croix.
	 *
	 * Il ne revient pas — non parce qu'on le mémorise, mais parce que le premier membre invité
	 * éteint l'état qui le fait paraître.
	 */
	protected function getOnboardingCreated(Structure $eStructure, array $onboarding): string {

		$h = '<div class="util-block-info">';
			$h .= \Asset::icon('check-lg', ['class' => 'util-block-icon']);
			$h .= '<div class="util-block-info-text">';
				$h .= '<div class="util-block-info-title">'.static::getText($eStructure, 'sectionCreated', '<b>'.encode($onboarding['eSection']['name']).'</b>').'</div>';
				$h .= '<div class="util-block-info-help">'.static::getText($eStructure, 'membersInviteText').'</div>';
			$h .= '</div>';
			$h .= '<div class="util-block-info-actions">';
				$h .= $this->getOnboardingInvite($eStructure, $onboarding);
				$h .= '<a href="'.StructureUi::urlCommunitySections($eStructure).'" class="btn btn-outline-primary">'.static::getText($eStructure, 'sectionOther').'</a>';
				$h .= '<a onclick="this.closest(\'.util-block-info\').remove()" class="util-block-info-close" title="'.s("Fermer").'">'.\Asset::icon('x-lg').'</a>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le rôle et le sous-groupe voyagent jusqu'au formulaire, qui les pose : le bandeau nomme la
	 * promotion qu'on vient de créer, et c'est celle-là qu'on veut peupler.
	 */
	protected function getOnboardingInvite(Structure $eStructure, array $onboarding): string {

		$role = $onboarding['eRole']->notEmpty() ? '&memberRole='.$onboarding['eRole']['id'] : '';

		return '<a href="/structure/invite:createMember?structure='.$eStructure['id'].$role.$onboarding['eSection']->getInviteArgument().'" class="btn btn-secondary">'.static::getText($eStructure, 'membersInvite').'</a>';

	}

	protected function getOnboardingFarmText(Structure $eStructure): string {
		return s("C'est elle qui portera vos cultures, vos ventes et vos observations. Vous la partagerez ensuite avec {value}, quand vous le voudrez.", static::getText($eStructure, 'the'));
	}

	protected function getOnboardingFarmUrl(): string {
		return '/demarrer/ferme';
	}

	/**
	 * ⚠️ Le texte suit la portée du lecteur : borné, ce ne sont pas « les autres membres » qui
	 * verront sa ferme mais son sous-groupe et les non bornés. Promettre plus large que la réalité
	 * est le mauvais sens de l'erreur sur un écran de consentement.
	 */
	protected function getOnboardingShareText(Structure $eStructure): string {

		if(self::getAskedRights($eStructure) === []) {
			return s("Pour l'instant, {value} ne demande accès à aucune donnée de production de votre ferme.", static::getText($eStructure, 'the'));
		}

		return (SectionLib::getScope($eStructure) === NULL)
			? s("Les autres membres verront vos données de production. Vous choisissez ce que vous ouvrez, et vous pouvez le refermer à tout moment.")
			: static::getText($eStructure, 'sectionShareText');

	}

	/**
	 * Le tableau de bord d'un groupe : ce que chaque fonctionnalité a d'ouvert. Ses deux comptes
	 * sont dans sa bande (`getDashboardFigures()`).
	 *
	 * ⚠️ **Plus de bloc d'accueil des fonctionnalités ici.** Il disait « Votre groupe est prêt, il
	 * attend ce qu'il doit faire » et renvoyait les choisir : c'était juste tant que rien ne posait
	 * la question, et le parcours la pose désormais à son étape 2. Un rappel qui double une étape
	 * la répète avec d'autres mots, sur un écran qui n'est pas celui du réglage ; le bandeau
	 * d'arrivée propose déjà les fonctionnalités restées éteintes, à l'endroit d'où l'on agit.
	 *
	 * C'est le troisième de la même famille retiré le 12/09/2026, après l'alerte du partage et les
	 * boutons « Allumer » du bandeau. Le contrôle à faire en livrant une étape de parcours : **ce
	 * qu'elle rend caduc ailleurs.**
	 */
	public function getDashboard(string $collects, string $enrollments): string {

		$h = '';

		// Ce que chaque fonctionnalité a d'ouvert, dans l'ordre des onglets, rendu par le composant
		// de son propre écran. Un bloc vide n'est pas écrit : l'écran reste alors les deux chiffres
		// de sa bande (`getDashboardFigures()`).
		foreach([$collects, $enrollments] as $block) {

			if($block !== '') {
				$h .= '<div class="mb-2">'.$block.'</div>';
			}

		}

		return $h;

	}

	/**
	 * Les deux comptes du tableau de bord, que sa bande porte à droite du titre.
	 *
	 * Ils résument les deux écrans voisins et se lisent avec les mêmes lectures qu'eux : un
	 * chiffre calculé autrement finirait par diverger de la liste qu'il annonce.
	 */
	public function getDashboardFigures(Structure $eStructure, int $members, int $farms): string {

		$h = '<div class="util-figures-line util-figures-line-card">';

			// Chaque chiffre mène à la liste qu'il compte : il la résume, il ne la remplace pas, et
			// le tableau de bord n'a rien d'autre à offrir que ces deux chemins.
			foreach([
				[$members, p("membre", "membres", $members), StructureUi::urlCommunityMembers($eStructure)],
				[$farms, p("ferme", "fermes", $farms), StructureUi::urlCommunityFarms($eStructure)]
			] as [$value, $label, $url]) {

				$h .= '<a href="'.$url.'" class="util-figures-line-item">';
					$h .= '<div class="util-figure-value">'.$value.'</div>';
					$h .= '<div class="util-figure-label">'.$label.'</div>';
				$h .= '</a>';

			}

		$h .= '</div>';

		return $h;

	}

	/** L'accueil d'un groupe qui n'héberge pas de fermes et n'a pas encore de relevés. */
	public function getEmptyStart(Structure $eStructure, bool $canManage, bool $onboarding = FALSE): string {

		// Même raison que les états vides de la grille : l'appel à l'action du groupe porte déjà le
		// geste. Sans droit d'administration ce bloc n'a pas de bouton à répéter, et son texte est
		// alors la seule chose que l'écran dise.
		if($onboarding and $canManage) {
			return '';
		}

		// L'écran est celui des fermes, l'absence à nommer est celle des fermes. « Pas encore de
		// membres » se lisait comme « personne » à trois centimètres d'un rail qui en affiche un.
		return self::getEmpty(
			s("Ce groupe n'a pas encore de fermes"),
			s("Invitez les personnes qui le composeront, ce sont elles qui y apporteront leurs fermes."),
			$this->getEmptyActions($eStructure, $canManage, FALSE)
		);

	}

	protected static function getEmpty(string $title, string $text, string $actions = ''): string {

		$h = '<div class="util-block util-block-empty mb-3">';
			$h .= '<h3>'.$title.'</h3>';
			$h .= '<p>'.$text.'</p>';
			$h .= $actions;
		$h .= '</div>';

		return $h;

	}

	public function getCreateTitle(Structure $eStructure): string {

		return static::getText($eStructure, 'create');

	}

	/**
	 * Le titre de l'écran des fermes, selon ce que son lecteur peut voir.
	 *
	 * ⚠️ Un texte écrit du point de vue du groupe se révèle faux du point de vue de qui le lit :
	 * « les fermes de ma promotion » ment à qui en voit deux, et redit en moins précis ce que le
	 * jeton nomme déjà à qui en a choisi une. C'est le troisième texte de ce chantier à se
	 * conditionner ainsi, avec le consentement du partage et « aucune ferme partagée ».
	 *
	 * Le possessif dit pourquoi il n'y en a qu'une : l'article défini laisserait croire que le
	 * groupe lui-même n'en a qu'une seule.
	 */
	public static function getFarmsTitle(Structure $eStructure, bool $scoped): string {
		return static::getText($eStructure, $scoped ? 'farmsTitleScoped' : 'farmsTitle');
	}

	public static function getWelcomeMascot(): string {
		return 'poule';
	}

	/**
	 * Les mots d'un groupe.
	 *
	 * Cette classe porte ceux d'une communauté de producteurs, qui sont aussi le défaut : « le
	 * groupe », mot que le produit emploie depuis toujours. Chaque autre type les redéfinit dans
	 * sa propre sous-classe — `StructureEducationUi`, `StructureNetworkUi`,
	 * `StructureAccountingUi` — et `StructureUi::getClass()` choisit laquelle répond.
	 *
	 * ⚠️ Le type ne se teste donc **nulle part** : il se résout une fois, à l'instanciation. Un
	 * cinquième type est une classe de plus et pas une branche de plus, et une sous-classe qui
	 * n'aurait rien de particulier à dire n'écrit rien du tout.
	 *
	 * Les noms s'accordent sur le describer de `communityType` (`Structure.u.php`) plutôt que
	 * d'inventer un cinquième vocabulaire : « Communauté de producteurs », « Organisme de
	 * formation », « Réseau d'accompagnement », « Structure comptable ».
	 *
	 * Chaque forme est un littéral, jamais une concaténation : `s()` extrait les chaînes du code,
	 * un mot assemblé à l'exécution n'est jamais traduit.
	 *
	 * ⚠️ `sectionInvite` porte la phrase entière et non le seul substantif : l'article s'accorde
	 * au genre du mot du type — une promotion, un groupe, une antenne —, et aucun accord ne se
	 * calcule à l'exécution.
	 *
	 * ⚠️ `farms` nomme l'onglet, `farmsTitle` nomme la page, et ce ne sont pas le même mot : un
	 * groupe de formation met « Fermes des étudiants » dans sa rangée et « Les fermes des
	 * étudiants » en titre, mais un réseau met « Fermes » dans la sienne et « Les fermes du
	 * réseau » en titre — son onglet ne peut pas dire « des membres », il y a aussi les fermes
	 * projet. Un seul libellé pour les deux emplois obligeait l'un des deux à mentir.
	 */
	public static function getText(Structure $eStructure, string $text, mixed $value = 1): string {

		return match($text) {
			'of' => s("du groupe"),
			'one' => s("un groupe"),
			'the' => s("le groupe"),
			'this' => s("ce groupe"),
			'your' => s("votre groupe"),
			'my' => p("Mon groupe", "Mes groupes", $value),
			'propertyLogo' => s("Image du groupe"),
			'propertyBanner' => s("Bandeau du groupe"),
			'propertyName' => s("Nom du groupe"),
			'propertyIsLegal' => throw new \UnsupportedException(),
			'propertyLegalEmail' => s("Adresse e-mail du groupe"),
			'propertyPresentation' => s("Présentation du groupe"),
			'farms' => s("Fermes des membres"),
			'farmsTitle' => s("Les fermes du groupe"),
			'farmsTitleScoped' => s("Les fermes de mon sous-groupe"),
			'section' => p("Sous-groupe", "Sous-groupes", $value),
			'sectionsTitle' => s("Les sous-groupes"),
			'sectionsFeature' => s("Faire des sous-groupes de fermes dans votre groupe."),
			'sectionClosed' => p("{value} sous-groupe clos", "{value} sous-groupes clos", $value),
			// L'encadrement d'un groupe de producteurs, ce sont ses administrateurs et rien
			// d'autre : la section garde donc exactement le nom qu'elle avait.
			'staffTitle' => p("Administrateur", "Administrateurs", $value),
			// Les deux publics d'un droit de partage. Nommés par ce qu'ils sont dans le groupe et
			// jamais par le rôle qui les définit : personne ne se sait « non-staff ».
			'audienceStaff' => s("l'équipe du groupe"),
			'audienceMember' => s("les autres producteurs"),
			// ⚠️ Le geste en entier, préposition comprise, et non « Permettre l'accès à » suivi du
			// nom du public : « à » devant l'équipe, « aux » devant les producteurs. Assembler les
			// deux moitiés rendrait « à les producteurs » au premier type où l'article change.
			'audienceStaffAllow' => s("Permettre l'accès à l'équipe du groupe"),
			'audienceMemberAllow' => s("Permettre l'accès aux producteurs du groupe"),
			// ⚠️ Le même geste dit par celui qui l'accorde, et non par celui qui le demande : sur
			// l'écran de la ferme, « Permettre l'accès » ferait dire au groupe qu'il s'autorise
			// lui-même. Deux jeux et non un, parce que demander et accorder sont deux actes.
			'audienceStaffGrant' => s("J'autorise l'accès à l'équipe du groupe"),
			'audienceMemberGrant' => s("J'autorise l'accès aux producteurs du groupe"),
			// ⚠️ Deux titres et non un : chacun a son tableau de bord, et celui qui le lit doit
			// voir de qui il parle. Le partage se fait sur `Role::isStaff()` et non sur la portée
			// — c'est déjà le critère de `getMembersOrder()` et de `filterMembers()`, et
			// `advisor` comme `member` portent tous deux `sections = ALL`.
			'dashboardStaff' => s("Tableau de bord des administrateurs"),
			'dashboardMember' => s("Tableau de bord des producteurs"),
			'sectionAll' => s("Tout le groupe"),
			'sectionManage' => s("Gérer les sous-groupes"),
			'sectionNew' => s("Nom du sous-groupe"),
			'sectionCreate' => s("Nouveau sous-groupe"),
			'sectionThe' => s("le sous-groupe"),
			'sectionThis' => s("ce sous-groupe"),
			'sectionInvite' => s("Sous-groupe dans lequel l'invitation fait entrer"),
			'sectionStaff' => s("Ce rôle encadre tout le groupe et n'entre dans aucun sous-groupe."),
			'inviteAdminEmail' => s("Lorsque vous créez un lien pour inviter un administrateur, vous devez obligatoirement indiquer une adresse e-mail pour des raisons de sécurité."),
			'inviteAdminEmailRequired' => s("Veuillez réserver l'invitation d'un nouvel administrateur à une adresse e-mail"),
			'inviteAdmin' => '<p>'.s("Un administrateur administre le groupe comme vous : il invite et retire des membres, change leur rôle, gère les sous-groupes, retire des fermes du groupe, modifie son profil et son paramétrage, et peut supprimer le groupe. Il peut aussi vous retirer l'administration ou vous retirer du groupe, et il faudra une nouvelle invitation pour y revenir.").'</p>'.'<p>'.s("Il ne prend en revanche aucune main sur les fermes : le groupe ne fait que lire ce que chaque ferme lui partage, et seul un administrateur d'une ferme décide de ce qu'elle partage.").'</p>'.'<p><b>'.s("N'invitez comme administrateurs que les personnes qui animent réellement le groupe.").'</b></p>',
			'sectionStartTitle' => s("Créez votre premier sous-groupe"),
			'sectionEmpty' => s("Vous n'avez pas encore créé de sous-groupe."),
			'sectionStartText' => s("Un sous-groupe range les membres du groupe. C'est lui qui décide de ce que chacun voit quand son rôle l'y borne."),
			'sectionStartButton' => s("Créer un sous-groupe"),
			'sectionCreated' => s("{value} créé", $value),
			'sectionOther' => s("Créer un autre sous-groupe"),
			'sectionShareText' => s("Votre sous-groupe verra vos données de production. Vous choisissez ce que vous ouvrez, et vous pouvez le refermer à tout moment."),
			'membersInvite' => s("Inviter des membres"),
			'membersInviteAll' => s("Inviter des membres"),
			'membersInviteText' => s("Invitez vos collègues à rejoindre votre groupe."),
			'sectionDeleteWarning' => p("{value} personne ne sera plus rangée dans aucun sous-groupe.", "{value} personnes ne seront plus rangées dans aucun sous-groupe.", $value),
			'create' => s("Créer un groupe"),
			'createButton' => s("Créer le groupe"),
			'created' => s("Votre groupe est créé !"),
			'ready' => s("Votre groupe est prêt"),
			'delete' => s("Supprimer le groupe")
		};

	}

}
?>
