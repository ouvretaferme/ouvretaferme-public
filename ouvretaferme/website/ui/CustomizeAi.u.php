<?php
namespace website;

class CustomizeAiUi extends \main\CustomizeUi {

	public function __construct() {

		parent::__construct();

		\Asset::css('website', 'customizeAi.css');
		\Asset::js('website', 'customizeAi.js');

	}

	protected function getJs(): string {
		return 'WebsiteCustomizeAi';
	}

	public function get(Website $eWebsite, \Collection $cDesign, array $settings): string {

		$exit = \Lime::getUrl().'/website/manage?id='.$eWebsite['structure']['id'];

		$h = '<div id="website-customize-bar" class="customize-bar customize-bar-bottom" data-url="'.encode(WebsiteUi::url($eWebsite, '/customize', internalDomain: TRUE)).'" data-exit="'.encode($exit).'" data-settings="'.encode(json_encode($settings)).'">';

			// Repliée, la barre ne montre plus que cette poignée : sans elle, il ne
			// resterait rien à cliquer pour la rouvrir.
			$h .= '<a class="customize-unfold" onclick="WebsiteCustomizeAi.toggle()" title="'.s("Personnaliser le site").'">'.\Asset::icon('sliders').'</a>';

			$h .= '<div class="customize-panel">';

				$h .= '<div class="customize-options">';

					$h .= $this->getDesigns(s("Template"), $cDesign, $settings['customDesign']);
					$h .= $this->getColors($settings);
					$h .= $this->getFonts($settings);
					$h .= $this->getText($settings['customText']);
					$h .= $this->getPosition($settings['customDesignPosition']);
					$h .= $this->getWidth($settings['customWidth']);

				$h .= '</div>';

				$h .= '<div class="customize-actions">';
					$h .= '<a class="btn btn-lg btn-success customize-save" onclick="WebsiteCustomizeAi.save()">'.s("Enregistrer").'</a>';
					$h .= '<a class="btn btn-sm customize-cancel" onclick="WebsiteCustomizeAi.toggle()">'.s("Replier").'</a>';
				$h .= '</div>';

				$h .= '<a class="customize-close" onclick="WebsiteCustomizeAi.cancel()" title="'.s("Quitter").'">'.\Asset::icon('x-circle').'</a>';

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Sous 768px, les trois sélecteurs tiennent une bonne part de la barre : ils
	 * passent derrière un bouton qui rappelle les couleurs en cours, et le CSS
	 * décide lequel des deux affichages s'applique.
	 */
	protected function getColors(array $settings): string {

		$h = '<div class="customize-colors">';

		$h .= '<a class="customize-colors-toggle" onclick="Customize.toggleColors(this)">';
			$h .= '<span class="customize-colors-preview">';
				$h .= '<span style="background-color: '.encode($settings['customColor'] ?? '').'" '.($settings['customColor'] === NULL ? 'data-customize-default="--secondary"' : '').'></span>';
				$h .= '<span style="background-color: '.encode($settings['customBackground'] ?? '').'" '.($settings['customBackground'] === NULL ? 'data-customize-default="--background"' : '').'></span>';
				$h .= '<span style="background-color: '.encode($settings['customLinkColor'] ?? '').'" '.($settings['customLinkColor'] === NULL ? 'data-customize-default="--link"' : '').'></span>';
			$h .= '</span>';
			$h .= s("Couleurs");
			$h .= \Asset::icon('chevron-up');
		$h .= '</a>';

		$h .= '<div class="customize-popover">';

		$h .= '<div class="customize-group">';
			$h .= '<span class="customize-label">'.s("Couleurs").'</span>';
			$h .= '<div class="customize-choices">';

				$h .= '<label class="customize-color" title="'.s("Couleur contrastante").'">';
					$h .= '<input type="color" value="'.encode($settings['customColor'] ?? '#000000').'" '.($settings['customColor'] === NULL ? 'data-customize-default="--secondary"' : '').' onchange="WebsiteCustomizeAi.set(\'customColor\', this.value)"/>';
					$h .= '<span>'.s("contraste").'</span>';
				$h .= '</label>';

				$h .= '<label class="customize-color" title="'.s("Couleur d'arrière plan").'">';
					$h .= '<input type="color" value="'.encode($settings['customBackground'] ?? '#ffffff').'" '.($settings['customBackground'] === NULL ? 'data-customize-default="--background"' : '').' onchange="WebsiteCustomizeAi.set(\'customBackground\', this.value)"/>';
					$h .= '<span>'.s("fond").'</span>';
				$h .= '</label>';

				$h .= '<label class="customize-color '.($settings['customLinkColor'] === NULL ? 'customize-color-unset' : '').'" title="'.s("Couleur des liens").'">';
					$h .= '<input type="color" value="'.encode($settings['customLinkColor'] ?? '#000000').'" '.($settings['customLinkColor'] === NULL ? 'data-customize-default="--link"' : '').' onchange="WebsiteCustomizeAi.set(\'customLinkColor\', this.value)"/>';
					$h .= '<span>'.s("liens").'</span>';
				$h .= '</label>';

				if($settings['customLinkColor'] !== NULL) {
					$h .= '<a class="customize-reset" onclick="WebsiteCustomizeAi.set(\'customLinkColor\', \'\')" title="'.s("Rendre aux liens la couleur du texte").'">'.\Asset::icon('arrow-counterclockwise').'</a>';
				}

				$h .= '<a class="customize-wand" onclick="WebsiteCustomizeAi.magic()" title="'.s("Tirer un jeu de couleurs au hasard, accordé à la couleur du texte").'">'.\Asset::icon('magic').'</a>';

			$h .= '</div>';
		$h .= '</div>';

		$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	protected function getText(string $current): string {

		return $this->getChoices(s("Texte"), 'customText', [
			Website::BLACK => s("Noir"),
			Website::WHITE => s("Blanc")
		], $current);

	}

	protected function getPosition(string $current): string {

		return $this->getChoices(s("Menu"), 'customDesignPosition', [
			Website::TOP => s("En haut"),
			Website::LEFT => s("À gauche")
		], $current);

	}

	protected function getWidth(int $current): string {

		return $this->getChoices(s("Largeur"), 'customWidth', [
			800 => s("Étroit"),
			1000 => s("Moyen"),
			1200 => s("Large")
		], (string)$current);

	}

}
?>
