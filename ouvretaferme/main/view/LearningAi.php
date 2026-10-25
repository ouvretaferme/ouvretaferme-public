<?php
/**
 * Le gabarit de la fiche d'une formation.
 *
 * Il reprend la mise en page de la documentation (`DocTemplate`), menu à gauche et contenu à
 * droite, mais le menu est celui de la formation lue et non celui de l'aide : il arrive tout rendu
 * de l'UI par `$menu`, le gabarit ne fait que le placer. `DocTemplate` écrit le sien en dur, d'où
 * ce gabarit voisin plutôt qu'une option de plus sur le sien.
 *
 * Il ne rend pas l'en-tête de l'aide (`h1` et sous-titre) : la fiche porte le sien, avec le visuel
 * de la thématique à côté du titre, et le pose dans le flux.
 */
class LearningAiTemplate extends BaseTemplate {

	public string $template = 'doc base ';
	public ?string $header = '';
	public ?string $footer = '';

	public bool $mainContainer = FALSE;

	public string $menu = '';

	/**
	 * Une formation de réseau porte le thème du réseau : le conteneur de la page, menu compris, devient
	 * la portée de ses jetons (`learning-doc`), et de son gabarit quand il en a choisi un. La portée
	 * monte jusqu'au conteneur parce que le menu précède la colonne de contenu : posée sur la colonne,
	 * elle ne l'atteindrait pas et le laisserait blanc à côté d'une page habillée. La barre de
	 * personnalisation reste dehors.
	 */
	public bool $custom = FALSE;
	public ?string $design = NULL;

	/**
	 * La barre de personnalisation du réseau, posée hors de la portée du thème : dedans, elle
	 * prendrait la police et les jetons du thème qu'elle règle, que la barre de la liste n'a pas.
	 */
	public string $customizeBar = '';

	protected function getMain(string $stream): string {

		Asset::css('main', 'template-doc.css');
		Asset::js('main', 'doc.js');

		$h = $this->customizeBar;

		if($this->custom) {
			$h .= '<div class="container learning-doc"'.($this->design === NULL ? '' : ' data-learning-design="'.encode($this->design).'"').'>';
		} else {
			$h .= '<div class="container">';
		}
			$h .= '<div id="doc-menu-open">';
				$h .= '<a class="btn btn-outline-primary" onclick="Doc.toggleMenu()">'.Asset::icon('list').' '.s("Menu").'</a>';
			$h .= '</div>';
			$h .= '<div class="doc-wrapper">';
				$h .= '<div class="doc-menu">';
					$h .= '<div id="doc-menu-list">';
						$h .= $this->menu;
					$h .= '</div>';
				$h .= '</div>';
				$h .= '<div class="doc-main">';
					$h .= '<div class="doc-content">';
						$h .= parent::getMain($stream);
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

}
?>
