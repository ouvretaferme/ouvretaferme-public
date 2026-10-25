<?php
namespace design;

/**
 * Le passage d'une page du design system à l'autre.
 *
 * Les pages n'étaient reliées par rien : on y arrivait en tapant l'URL, et
 * rien n'annonçait que les autres existaient. Le menu vit ici plutôt que dans
 * `DesignUi` parce qu'il est une navigation et non un composant de la charte : ce
 * qu'il liste ne s'illustre pas, ça se visite.
 *
 * Le titre de la page devient l'ouverture du menu : sur un site de quelques écrans,
 * un libellé « Menu » à côté du titre dirait deux fois la même chose.
 *
 * Trois onglets par-dessus ce menu : Ui, qui couvre les cinq pages ci-dessous, Dev, qui dit
 * comment on travaille avec les agents, et Tests, qui décrit le décor des tests. Le menu n'aurait
 * pas pu les porter, une méthode de travail ou un décor n'étant pas une page de charte : les
 * onglets séparent les sujets, le menu circule à l'intérieur du premier.
 */
class NavigationUi {

	const DEV = '/?dev';
	const TESTS = '/?tests';

	/**
	 * L'ajout d'une page se fait ici et nulle part ailleurs.
	 */
	private static function getPages(): array {

		return [
			'/' => s("Composants"),
			'/plants' => s("Espèces"),
			'/categories' => s("Icônes de catégorie"),
			'/friends' => s("Illustrations"),
			'/pdf' => s("Documents PDF"),
		];

	}

	public static function getHeader(string $title, string $current, ?bool $forAgents = NULL): string {

		\Asset::css('design', 'designAi.css');

		// Le menu ne circule qu'entre les pages de Ui : sur Dev et Tests, un chevron ouvrirait une liste
		// qui ne contient pas la page où l'on est, et le titre redevient donc un titre.
		if(in_array($current, [self::DEV, self::TESTS])) {

			$h = '<h1>'.encode($title).'</h1>';

		} else {

			$h = '<a data-dropdown="bottom-start" class="design-navigation">';
				$h .= '<h1>'.encode($title).'</h1>';
				$h .= \Asset::icon('chevron-down');
			$h .= '</a>';

			// `Lime.Dropdown` prend le frère immédiat du bouton pour liste (`lime.js`,
			// `getListFromButton()`) : la bascule ci-dessous ne peut pas s'intercaler.
			$h .= '<div class="dropdown-list">';

				$h .= '<div class="dropdown-title">'.s("Design system").'</div>';

				foreach(self::getPages() as $url => $label) {
					$h .= '<a href="'.self::url($url).'" class="dropdown-item '.($url === $current ? 'selected' : '').'">'.encode($label).'</a>';
				}

			$h .= '</div>';

		}

		if($forAgents !== NULL) {
			$h .= $forAgents
				? '<a href="'.self::url($current).'" class="btn btn-outline-primary btn-sm">'.s("Vue de lecture").'</a>'
				: '<a href="'.self::url(self::getAgentsUrl($current)).'" class="btn btn-outline-primary btn-sm">'.s("Vue pour les agents").'</a>';
		}

		$h .= self::getTabs($current);

		return $h;

	}

	/**
	 * Les chemins de cette classe sont ceux du sous-domaine du design system, dont nginx réécrit la
	 * racine en `/design/` (`main/conf/rewrite/dev.cfg` et `prod.cfg`). Servie par l'hôte du site, la page vit sous
	 * `/design/`, et un chemin écrit pour la racine y mènerait hors du design system. Le test d'hôte
	 * est celui de `main\SiteAiUi::url()`.
	 */
	private static function url(string $path): string {

		if(LIME_HOST === \Lime::getHost()) {
			return '/design'.$path;
		} else {
			return $path;
		}

	}

	private static function getAgentsUrl(string $current): string {

		return match($current) {
			'/' => '/?agents',
			self::TESTS => self::TESTS.'&agents',
		};

	}

	private static function getTabs(string $current): string {

		$tab = match($current) {
			self::DEV => self::DEV,
			self::TESTS => self::TESTS,
			default => '/',
		};

		$h = '<div class="tabs tabs-h">';
			$h .= '<div class="tabs-item">';
				$h .= '<a href="'.self::url('/').'" class="tab-item '.($tab === '/' ? 'selected' : '').'">'.s("Ui").'</a>';
				$h .= '<a href="'.self::url(self::DEV).'" class="tab-item '.($tab === self::DEV ? 'selected' : '').'">'.s("Dev").'</a>';
				$h .= '<a href="'.self::url(self::TESTS).'" class="tab-item '.($tab === self::TESTS ? 'selected' : '').'">'.s("Tests").'</a>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

}
?>
