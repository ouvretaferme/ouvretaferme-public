<?php
namespace dashboard;

/**
 * Le groupe Bureau du panneau (« Pilotage » jusqu'à L52) : le tableau de bord, les notes (L47), le
 * raccourci vers l'équipe, la section Ouvretaferme et son « Du nouveau » quand le blog a parlé (L46,
 * L91), puis la météo (L52).
 *
 * Le gabarit rend le panneau sur chaque page de structure, et la navigation ajax ne le réécrit pas
 * (`StructureTemplate::buildAjax()`) : le groupe garde son état jusqu'au rechargement, et seule
 * l'entrée choisie suit l'écran. Hors des écrans qui les lisent, la météo n'est qu'un emplacement et
 * « Du nouveau » une entrée qui porte son adresse, que `pilotage.js` confirme ou remplit au premier
 * passage sur l'univers : lire le blog et la prévision à chaque page le ferait payer à tous les écrans. La météo a sa feuille, `pilotage.css`, que
 * `getHome()` charge : celle de l'accueil reste à l'accueil, et les entrées se composent du mobilier
 * des onglets.
 */
class PilotageUi {

	public function __construct() {
		\Asset::js('dashboard', 'pilotage.js');
	}

	public static function url(\structure\Structure $eStructure): string {
		return DashboardUi::url($eStructure).'/pilotage';
	}

	public static function urlHome(\structure\Structure $eStructure): string {
		return DashboardUi::url($eStructure).'/menu';
	}

	public static function getNewsId(): string {
		return 'structure-pilotage-news';
	}

	public static function getHomeId(): string {
		return 'structure-pilotage-home';
	}

	/** La place, sous la date de l'accueil, où la tablette montre la météo. */
	public static function getHomePlaceId(): string {
		return 'dashboard-home';
	}

	/**
	 * `$news` dit si « Du nouveau » paraît, `NULL` quand l'écran n'a pas lu les nouvelles : le groupe
	 * porte alors l'emplacement qui les demandera. `$home` est la météo (`DashboardLib::loadHome()`), au
	 * même régime.
	 *
	 * `$homeNav` choisit l'entrée de l'écran ouvert sous Bureau : `dashboard` pour l'accueil et son
	 * réglage, `news` pour « Du nouveau » (L50), `note` pour les notes (L47). Chaque entrée qui ouvre un
	 * écran du groupe porte son nom dans `data-home-nav`, où la navigation ajax déplace la sélection.
	 */
	public function get(\structure\Structure $eStructure, ?bool $news, ?array $home, ?string $homeNav = NULL): string {

		$h = '<div id="structure-pilotage">';

			$h .= '<div class="structure-tab-wrapper">';
				$h .= '<a href="'.DashboardUi::url($eStructure).'" class="structure-tab '.($homeNav === 'dashboard' ? 'selected' : '').'" data-home-nav="dashboard">';
					$h .= '<span class="structure-tab-icon">'.\Asset::icon('grid-1x2').'</span>';
					$h .= '<span class="structure-tab-label">'.s("Tableau de bord").'</span>';
					$h .= '<span class="structure-tab-chevron">'.\Asset::icon('chevron-right').'</span>';
				$h .= '</a>';
			$h .= '</div>';

			// L'école et les groupes qui observent la production voient l'accueil, mais les notes ne se
			// lisent qu'entre membres.
			if($eStructure->isMember()) {

				$h .= '<div class="structure-tab-wrapper" id="structure-pilotage-notes">';
					$h .= '<a href="'.\note\NoteUi::urlStructure($eStructure).'" class="structure-tab '.($homeNav === 'note' ? 'selected' : '').'" data-home-nav="note">';
						$h .= '<span class="structure-tab-icon">'.\Asset::icon('journal-text').'</span>';
						$h .= '<span class="structure-tab-label">'.s("Notes").'</span>';
					$h .= '</a>';
				$h .= '</div>';

			}

			// Un raccourci vers l'écran de l'équipe, qui reste rangé sous les paramètres (`nav = 'team'`) : il
			// n'a donc pas de `data-home-nav`, qui ouvrirait Bureau sur un écran des paramètres. Il paraît aux
			// membres qui ont l'entrée des paramètres (`MenuLib::getSettingsMenus()`).
			if(in_array('team', $eStructure['menus?']()['settings'])) {

				$h .= '<div class="structure-tab-wrapper" id="structure-pilotage-team">';
					$h .= '<a href="'.\structure\StructureUi::urlStructureTeam($eStructure).'" class="structure-tab">';
						$h .= '<span class="structure-tab-icon">'.\Asset::icon('people-fill').'</span>';
						$h .= '<span class="structure-tab-label">'.\structure\HeaderUi::getTeamCategories($eStructure)[\structure\Member::TEAM].'</span>';
					$h .= '</a>';
				$h .= '</div>';

			}

			// L'emplacement porte déjà la section, que la réponse remplace par la même ou retire : le blog garde
			// ses dernières nouvelles, quel que soit leur âge, donc elle paraît presque toujours, et un
			// emplacement vide la ferait arriver après coup en décalant ses voisines (L77).
			if($news === NULL) {
				$h .= $this->getNews($eStructure, FALSE, self::url($eStructure));
			} else if($news) {
				$h .= $this->getNews($eStructure, $homeNav === 'news');
			}

			if($home === NULL) {
				$h .= '<div id="'.self::getHomeId().'" data-url="'.self::urlHome($eStructure).'" onrender="DashboardPilotage.watch()"></div>';
			} else {
				$h .= $this->getHome($eStructure, $home);
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La météo, au pied du groupe dans la colonne (`template-structure.css`).
	 * Le nœud remplace son emplacement et en garde l'identifiant, sans adresse : rien ne le redemande.
	 */
	public function getHome(\structure\Structure $eStructure, array $home): string {

		\Asset::css('dashboard', 'pilotage.css');

		$dashboardUi = new DashboardUi();

		// Un emplacement sans occupant est absent de ce que `loadContributions()` rend, et non une clé
		// vide : c'est son contrat, que l'accueil lit de la même façon.
		$h = '<div id="'.self::getHomeId().'" onrender="DashboardPilotage.place()">';
			$h .= $dashboardUi->getHomeWeather($eStructure, $home[DashboardSetting::WEATHER] ?? []);
		$h .= '</div>';

		return $h;

	}

	/**
	 * La section Ouvretaferme et son entrée « Du nouveau », au pied du groupe sous un filet, comme la
	 * comptabilité dans son univers (L91) : ce qu'elle annonce vient du logiciel, pas de la structure.
	 * Elle sert aussi d'emplacement : avec `$url`, `pilotage.js` demande les nouvelles, et la réponse la
	 * remplace par la section sans adresse, que rien ne redemande, ou la retire avec son titre.
	 */
	public function getNews(\structure\Structure $eStructure, bool $selected, ?string $url = NULL): string {

		$attributes = ($url === NULL) ? '' : ' data-url="'.$url.'" onrender="DashboardPilotage.watch()"';

		$h = '<div class="structure-tab-wrapper structure-tab-subnav structure-nav-ouvretaferme" id="'.self::getNewsId().'"'.$attributes.'>';
			$h .= '<div class="structure-tab structure-tab-subnav">';
				$h .= '<span class="structure-tab-icon"><span class="asset-icon">'.\Asset::svg('main', 'svg/logo-otf.svg').'</span></span>';
				$h .= '<span class="structure-tab-label">'.s("Ouvretaferme").'</span>';
			$h .= '</div>';
			$h .= '<div class="structure-subnav-wrapper">';
				$h .= '<a href="'.DashboardUi::urlNews($eStructure).'" class="structure-subnav-item '.($selected ? 'selected' : '').'" data-home-nav="news">';
					$h .= '<span class="structure-subnav-prefix"></span> ';
					$h .= '<span>'.s("Du nouveau").'</span>';
				$h .= '</a>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

}
?>
