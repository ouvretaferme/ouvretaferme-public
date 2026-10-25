<?php
namespace structure;

class StructureAmapUi implements StructureCommonUi {

	public function getCreateForm(\util\FormUi $form, Structure $eStructure): string {

		$h = $form->asteriskInfo();

		$start = '<ul>';
			$start .= '<li>'.s("Gérez les adhérents et les contrats de l'AMAP").'</li>';
			$start .= '<li>'.s("Organisez la relation avec les producteurs").'</li>';
		$start .= '</ul>';

		$h .= new \main\DesignUi()->getStart(
			\Asset::icon('people-fill'),
			s("Votre AMAP sur {siteName}"),
			$start
		);
		
		$h .= $form->hidden('type', \structure\Structure::AMAP);
		$h .= $form->dynamicGroups($eStructure, ['name*', 'legalEmail*', 'legalCountry']);

		$h .= $form->group(
			content: $form->submit(s("Créer l'AMAP"), ['data-waiter' => s("Création en cours")])
		);

		return $h;

	}

	public function getCreateTitle(Structure $eStructure): string {

		return s("Créer une AMAP");

	}

	/**
	 * La rangée de l'AMAP en vue groupe, sur les menus que `MenuLib::getAmapGroupMenus()` ouvre au
	 * compte connecté. Un menu mène à sa première page ; s'il en a plusieurs, son onglet porte
	 * `dropdown` et en ouvre la liste (`StructureCommunityUi::getTabsRow()`).
	 *
	 * Un menu d'une seule page n'ouvre pas de liste, qui n'y choisirait rien : l'onglet y mène, et la
	 * page prend le nom du menu pour titre. Mon espace n'en ouvre pas non plus : sa première page porte
	 * les autres en onglets de tête.
	 *
	 * Hors du bureau, Mon espace est tout ce que le compte a : un onglet seul, déjà choisi, ne mènerait
	 * nulle part, et la rangée ne se rend que pour le référent, qui y trouve aussi « Mes producteurs ».
	 *
	 * @return array [clé => ['href', 'label', 'name' si le libellé est une icône, 'end', 'pages' => [page => ['href', 'label']], 'dropdown' si l'onglet ouvre ses pages]]
	 */
	public function getGroupTabs(Structure $eStructure, array $menus): array {

		if(
			$eStructure->canManage() === FALSE and
			array_key_exists('amap-referent-space', $menus) === FALSE
		) {
			return [];
		}

		$tabs = [];

		foreach($menus as $menu => $pages) {

			$tabsPage = [];

			foreach($pages as $page) {
				$tabsPage[$page] = [
					'href' => $this->getGroupPageUrl($eStructure, $menu, $page),
					'label' => $this->getGroupPageLabel($eStructure, $menu, $page)
				];
			}

			$tabs[$menu] = $this->getGroupMenuLabel($menu) + [
				'href' => ($tabsPage === []) ? $this->getGroupMenuUrl($eStructure, $menu) : $tabsPage[array_key_first($tabsPage)]['href'],
				'end' => FALSE,
				'pages' => $tabsPage
			];

			if(
				$menu !== 'amap-space' and
				count($tabsPage) > 1
			) {
				$tabs[$menu]['dropdown'] = TRUE;
			}

		}

		return $tabs;

	}

	/** Sous 768 px la rangée devient une liste, où une icône seule n'a pas de nom : la maison y porte le sien. */
	protected function getGroupMenuLabel(string $menu): array {

		return match($menu) {
			'amap-dashboard' => ['label' => \Asset::icon('house-fill'), 'name' => s("Tableau de bord")],
			'amap-distribution' => ['label' => s("Distributions")],
			'amap-producer' => ['label' => s("Producteurs")],
			'communications' => ['label' => s("Communication")],
			'amap-customer' => ['label' => s("Adhérents")],
			'amap-space' => ['label' => s("Mon espace")],
			'settings' => ['label' => s("Paramètres")],
			'amap-referent-space' => ['label' => s("Mes producteurs")],
		};

	}

	/**
	 * La vignette de la bande claire d'un écran de l'AMAP, l'icône du menu ouvert. Un écran qui
	 * n'ouvre aucun menu, comme les notifications ou le partage d'un contrat, n'a pas de vignette.
	 */
	public static function getGroupTabIcon(?string $nav): string {

		return match($nav) {
			NULL => '',
			'amap-dashboard' => 'house-fill',
			'amap-distribution' => 'calendar-week',
			'amap-producer', 'amap-referent-space' => 'producer-fill',
			'communications' => 'megaphone',
			'amap-customer' => 'people-fill',
			'amap-space' => 'person-fill',
			'settings' => 'gear-fill',
		};

	}

	protected function getGroupMenuUrl(Structure $eStructure, string $menu): string {

		return match($menu) {
			'amap-dashboard' => StructureUi::urlAmapDashboard($eStructure),
			'amap-referent-space' => StructureUi::urlAmapProducers($eStructure),
			'settings' => StructureUi::urlAmapSettings($eStructure),
		};

	}

	protected function getGroupPageUrl(Structure $eStructure, string $menu, string $page): string {

		return match($menu) {

			'amap-distribution' => match($page) {
				'distribution' => StructureUi::urlAmapDistributions($eStructure),
			},

			'amap-producer' => match($page) {
				'producer' => StructureUi::urlAmapProducers($eStructure),
				'wallet' => StructureUi::urlAmapWallets($eStructure),
				'referent' => StructureUi::urlAmapReferents($eStructure),
			},

			'amap-customer' => match($page) {
				'customer' => StructureUi::urlAmapCustomers($eStructure),
				'membershipRequest', 'membership', 'duty' => $this->getGroupObserverUrl($eStructure, $menu, $page),
			},

			'amap-space' => $this->getGroupObserverUrl($eStructure, $menu, $page),

			'communications' => new MenuUi()->getEntry($eStructure, $menu.'/'.$page)['url'],

		};

	}

	protected function getGroupPageLabel(Structure $eStructure, string $menu, string $page): string {

		return match($menu) {

			'amap-distribution' => match($page) {
				'distribution' => s("Distributions"),
			},

			'amap-producer' => match($page) {
				'producer' => s("Liste des producteurs"),
				'wallet' => s("Cagnottes"),
				'referent' => s("Référents"),
			},

			'amap-customer' => match($page) {
				'customer' => s("Liste des adhérents"),
				'membershipRequest' => s("Demandes d'adhésion"),
				'membership' => s("Cotisations des adhérents"),
				'duty' => s("Permanences"),
			},

			'amap-space' => match($page) {
				'home' => s("Accueil"),
				'duty' => s("Permanences"),
				'charter' => s("Charte"),
			},

			'communications' => new MenuUi()->getEntry($eStructure, $menu.'/'.$page)['label'],

		};

	}

	// Seul l'observateur connait l'adresse de ces pages, ce qui vaut garde : les menus qui les portent
	// ne s'ouvrent que sur une AMAP.
	protected function getGroupObserverUrl(Structure $eStructure, string $menu, string $page): string {
		return StructureUi::getObserverEntries('getCategoryUrls', $eStructure)[$menu][$page];
	}

	/**
	 * Le menu du membre dans le bandeau de l'AMAP : sa place, sans les gestes d'un groupe. Quitter le
	 * groupe et partager une ferme n'ont pas de sens pour une AMAP, où l'on n'entre et ne sort que par
	 * le bureau.
	 */
	public function getActionsMember(Structure $eStructure, Member $eMember): string {

		if($eMember->empty()) {
			return '';
		}

		$h = '<div>';
			$h .= '<a data-dropdown="bottom-end" class="dropdown-toggle btn btn-white">'.\Asset::icon('person-fill').'</a>';
			$h .= '<div class="dropdown-list">';
				$h .= '<div class="dropdown-text dropdown-info">'.s("Vous êtes {role} de {this}", ['role' => encode($eMember['role']['name']), 'this' => self::getText($eStructure, 'this')]).'</div>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Fermer l'AMAP : elle cesse de prendre ce qui est neuf, et sert ce qui court. Le panneau ne porte
	 * ni chiffre ni pastille « Définitif » : rien ne rouvre une AMAP, mais rien n'y est détruit.
	 */
	public function breakClose(Structure $eStructure): \Panel {

		return new \Panel(
			id: 'panel-break-close',
			title: static::getText($eStructure, 'delete'),
			subTitle: encode($eStructure['name']),
			body: \main\DesignUi::getWeigh(
				'danger',
				'door-closed',
				s("Fermer l'AMAP {name}", ['name' => encode($eStructure['name'])]),
				NULL,
				[
					['icon' => 'person-x', 'text' => s("<b>L'AMAP</b> ne prendra plus d'adhésion ni de souscription.")]
				],
				[
					s("Les souscriptions déjà signées sont servies jusqu'à la fin de leur saison."),
					s("Les adhérents, les souscriptions et les distributions sont conservés, et l'équipe garde l'accès à l'AMAP."),
					s("La boutique et le site restent en ligne.")
				],
				NULL
			),
			footer: '<a data-ajax="/structure/structure:doClose" post-id="'.$eStructure['id'].'" class="btn btn-danger">'.static::getText($eStructure, 'delete').'</a>'
		);

	}

	public static function getWelcomeMascot(): string {
		return 'escargot';
	}

	public static function getText(Structure $eStructure, string $text, mixed $value = 1): string {

		return match($text) {

			'of' => s("de l'AMAP"),
			'one' => s("une AMAP"),
			'the' => s("l'AMAP"),
			'this' => s("cette AMAP"),
			'your' => s("votre AMAP"),

			// « AMAP » ne prend pas de s : c'est un sigle, et la convention du produit le garde invariable.
			'my' => \p("Mon AMAP", "Mes AMAP", $value),

			'propertyLogo' => s("Logo de l'AMAP"),
			'propertyBanner' => s("Bandeau de l'AMAP"),
			'propertyName' => s("Nom de l'AMAP"),
			'propertyIsLegal' => throw new \UnsupportedException(),
			'propertyLegalEmail' => s("Adresse e-mail de l'AMAP"),
			'propertyPresentation' => s("Présentation de l'AMAP"),

			'updateLegal' => throw new \UnsupportedException(),

			'inviteAdmin' => '<p>'.s("Un administrateur tient l'AMAP comme vous, avec ses adhérents, ses adhésions, ses producteurs, ses distributions et ses permanences. Il peut aussi vous retirer de l'administration.").'</p><p>'.s("N'invitez que des personnes qui administrent l'AMAP.").'</p>',

			'settingsGeneral' => s("Profil"),

			'delete' => s("Fermer l'AMAP")

		};

	}

	public function getSettingsCustomers(Structure $eStructure): string {

		// Les entrées de l'écran viennent des observateurs : sans l'application, il n'a rien à montrer.
		if(StructureSetting::hasCommercialisationApp() === FALSE) {
			return '';
		}

		$entries = StructureUi::getObserverEntries('getAmapCustomersEntries', $eStructure);

		$h = '<div class="util-cards util-cards-2">';

			$h .= $entries['customers'];
			$h .= $entries['import'];

		$h .= '</div>';

		return $h;

	}

}
?>
