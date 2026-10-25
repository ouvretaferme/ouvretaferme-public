<?php
namespace main;

/**
 * Plomberie commune aux barres de personnalisation.
 *
 * La boutique et le site s'habillent de la même façon — on regarde la page et
 * l'on change les réglages dessus — mais n'offrent pas les mêmes réglages : ce
 * qui est ici est ce que les deux barres écrivent à l'identique, le reste
 * appartient à chaque paquet.
 */
abstract class CustomizeUi {

	// `adjust` sort en `font-size-adjust`, pour les polices dont la hauteur d'x est
	// nettement sous les ~0,50 du reste de la liste : à taille égale elles paraissent
	// plus petites. La propriété est héritée et porte sur la taille *utilisée*, elle
	// rattrape donc aussi les tailles posées en rem, qu'un font-size sur le conteneur
	// laisserait passer. Les cursives (Parisienne, Sail) restent volontairement de
	// côté : leur petite hauteur d'x tient au dessin, l'agrandir déforme la police.
	const CUSTOM_FONTS = [
		['label' => 'Cairo', 'value' => "'Cairo', sans-serif"],
		['label' => 'DM Sans', 'value' => "'DM Sans', sans-serif"],
		['label' => 'Figtree', 'value' => "'Figtree', serif", 'link' => "Figtree:wght@400;700"],
		['label' => 'Genos', 'value' => "'Genos', sans-serif"],
		['label' => 'Hina Mincho', 'value' => "'Hina Mincho', serif", 'adjust' => 0.42],
		['label' => 'Kodchasan', 'value' => "'Kodchasan', sans-serif", 'link' => "Kodchasan:wght@400;700"],
		['label' => 'Lato', 'value' => "'Lato', sans-serif"],
		['label' => 'Lora', 'value' => "'Lora', serif", 'link' => "Lora:wght@400;700"],
		['label' => 'Montserrat', 'value' => "'Montserrat', sans-serif"],
		['label' => 'Old Standard TT', 'value' => "'Old Standard TT', serif", 'adjust' => 0.5],
		['label' => 'Open Sans', 'value' => "'Open sans', sans-serif"],
		['label' => 'Parisienne', 'value' => "'Parisienne', cursive"],
		['label' => 'Poiret One', 'value' => "'Poiret One', cursive"],
		['label' => 'PT Serif', 'value' => "'PT Serif', serif"],
		['label' => 'Quattrocento Sans', 'value' => "'Quattrocento Sans', sans-serif", 'link' => "Quattrocento Sans:wght@400;700"],
		['label' => 'Roboto', 'value' => "'Roboto', sans-serif"],
		['label' => 'Sail', 'value' => "'Sail', cursive"],
		['label' => 'Source Sans Pro', 'value' => "'Source Sans Pro', sans-serif", 'link' => "Source Sans Pro:wght@400;700"],
		['label' => 'Titillium Web', 'value' => "'Titillium Web', sans-serif", 'link' => "Titillium Web:wght@400;700"],
	];

	const CUSTOM_TITLE_FONTS = [
		['label' => 'Acme', 'value' => "'Acme', sans-serif"],
		['label' => 'Anton', 'value' => "'Anton', sans-serif"],
		['label' => 'Barriecito', 'value' => "'Barriecito', serif"],
		['label' => 'Bricolage Grotesque', 'value' => "'Bricolage Grotesque', sans-serif"],
		['label' => 'Bungee Shade', 'value' => "'Bungee Shade', cursive"],
		['label' => 'Butterfly Kids', 'value' => "'Butterfly Kids', serif"],
		['label' => 'Cabin Sketch', 'value' => "'Cabin Sketch', cursive", 'link' => "Cabin Sketch:wght@400;700"],
		['label' => 'Chelsea Market', 'value' => "'Chelsea Market', serif"],
		['label' => 'Comfortaa', 'value' => "'Comfortaa', cursive", 'link' => "Comfortaa:wght@400;700"],
		['label' => 'Dancing Script', 'value' => "'Dancing Script', cursive"],
		['label' => 'Fredericka the Great', 'value' => "'Fredericka the Great', serif"],
		['label' => 'Frijole', 'value' => "'Frijole', serif"],
		['label' => 'Itim', 'value' => "'Itim', cursive"],
		['label' => 'Kurale', 'value' => "'Kurale', serif"],
		['label' => 'Josefin Slab', 'value' => "'Josefin Slab', serif", 'link' => "Josefin Slab:wght@400;700"],
		['label' => 'Lobster', 'value' => "'Lobster', cursive"],
		['label' => 'Londrina Sketch', 'value' => "'Londrina Sketch', serif"],
		['label' => 'Mea Culpa', 'value' => "'Mea Culpa', cursive"],
		['label' => 'Megrim', 'value' => "'Megrim', cursive"],
		['label' => 'Miniver', 'value' => "'Miniver', serif"],
		['label' => 'Montez', 'value' => "'Montez', serif"],
		['label' => 'Mouse Memoirs', 'value' => "'Mouse Memoirs', serif"],
		['label' => 'Mystery Quest', 'value' => "'Mystery Quest', serif"],
		['label' => 'Nova Mono', 'value' => "'Nova Mono', monospace"],
		['label' => 'Open Sans', 'value' => "'Open sans', sans-serif"],
		['label' => 'Pacifico', 'value' => "'Pacifico', cursive"],
		['label' => 'Patrick Hand', 'value' => "'Patrick Hand', serif"],
		['label' => 'PT Mono', 'value' => "'PT Mono', serif"],
		['label' => 'PT Serif', 'value' => "'PT Serif', serif"],
		['label' => 'Schoolbell', 'value' => "'Schoolbell', serif"],
		['label' => 'Shizuru', 'value' => "'Shizuru', cursive"],
	];

	const CUSTOM_BACKGROUNDS = [
		'Blanc' => ['#ffffff', '#d5d8db'],
		'Perle' => ['#e5e8eb', '#caced0'],
		'Lin' => ['#f3ede4', '#e3daca'],
		'Sable' => ['#f8edd8', '#efdebd'],
		'Paille' => ['#f3f1d2', '#e7e2b5'],
		'Prairie' => ['#e3f1dc', '#cce4c0'],
		'Menthe' => ['#dbefe9', '#bfe1d6'],
		'Azur' => ['#deeaf6', '#c2d9f0'],
		'Lilas' => ['#e8e1f6', '#d1c6ec'],
		'Pivoine' => ['#f6dee8', '#edc5d6'],
		'Abricot' => ['#f8e4d3', '#f2d0b2'],
	];

	public function __construct() {
		\Asset::css('main', 'customize.css');
		\Asset::js('main', 'customize.js');
	}

	/**
	 * Classe JS qui pilote la barre : ShopCustomizeAi, WebsiteCustomizeAi.
	 */
	abstract protected function getJs(): string;

	public static function getFont(string $label): ?array {

		$font = array_filter(
			self::CUSTOM_FONTS, fn($font) => $font['value'] === $label
		);

		return $font ? first($font) : NULL;

	}

	public static function getTitleFont(string $label): ?array {

		$font = array_filter(
			self::CUSTOM_TITLE_FONTS, fn($font) => $font['value'] === $label
		);

		return $font ? first($font) : NULL;

	}

	public static function isCustomFont(string $customFont, array $customFonts): bool {

		return count(array_filter(
			$customFonts, fn($font) => $font['value'] === $customFont
		)) > 0;

	}

	public static function isCustomBackground(string $background): bool {

		foreach(self::CUSTOM_BACKGROUNDS as $backgrounds) {

			if(in_array($background, $backgrounds, TRUE)) {
				return TRUE;
			}

		}

		return FALSE;

	}

	/**
	 * Les thèmes se choisissent à l'œil : une liste déroulante obligerait à les
	 * essayer un par un pour savoir de quoi elle parle.
	 *
	 * Sous 768px, la vingtaine de vignettes ne tient plus : la même liste est
	 * doublée en menu déroulant, et le CSS choisit laquelle des deux s'affiche.
	 */
	protected function getDesigns(string $label, \Collection $cDesign, int $current): string {
		return $this->getDesignList($label, $cDesign->getColumn('name', 'id'), (string)$current);
	}

	// PHP rend entière toute clé de tableau numérique : la comparaison se fait en chaîne.
	protected function getDesignList(string $label, array $designs, string $current): string {

		$h = '<div class="customize-group customize-group-wrap">';
			$h .= '<span class="customize-label">'.$label.'</span>';
			$h .= '<div class="customize-choices">';

				foreach($designs as $value => $name) {
					$h .= '<a class="customize-choice '.((string)$value === $current ? 'selected' : '').'" onclick="'.$this->getJs().'.set(\'customDesign\', \''.encode($value).'\')">'.encode($name).'</a>';
				}

			$h .= '</div>';

			$h .= '<select class="customize-select" onchange="'.$this->getJs().'.set(\'customDesign\', this.value)">';

				foreach($designs as $value => $name) {
					$h .= '<option value="'.encode($value).'" '.((string)$value === $current ? 'selected' : '').'>'.encode($name).'</option>';
				}

			$h .= '</select>';

		$h .= '</div>';

		return $h;

	}

	protected function getFonts(array $settings, array $defaults = ['title' => NULL, 'text' => NULL]): string {

		$h = '<div class="customize-group customize-fonts">';
			$h .= '<span class="customize-label">'.s("Polices").'</span>';
			$h .= '<div class="customize-choices">';

				foreach([
					'customTitleFont' => [s("Titres"), self::CUSTOM_TITLE_FONTS, ($defaults['title'] === NULL) ? NULL : self::getTitleFont($defaults['title'])],
					'customFont' => [s("Texte"), self::CUSTOM_FONTS, ($defaults['text'] === NULL) ? NULL : self::getFont($defaults['text'])]
				] as $property => [$label, $fonts, $fontDefault]) {

					$h .= '<label class="customize-field">';
						$h .= '<span>'.$label.'</span>';

						// La valeur stockée est la déclaration CSS de la police, pas son nom :
						// c'est sur elle que getFont() la retrouve.
						$h .= '<select class="customize-select" onchange="Customize.setFont(this, \''.$property.'\')">';
							if($fontDefault === NULL) {
								$h .= '<option value="">'.s("Par défaut").'</option>';
							} else {
								$h .= '<option value="" data-family="'.encode($fontDefault['link'] ?? $fontDefault['label']).'">'.s("{font} par défaut", ['font' => encode($fontDefault['label'])]).'</option>';
							}
							foreach($fonts as $font) {
								$h .= '<option value="'.encode($font['value']).'" data-family="'.encode($font['link'] ?? $font['label']).'" data-adjust="'.($font['adjust'] ?? 'none').'" '.($settings[$property] === $font['value'] ? 'selected' : '').'>'.encode($font['label']).'</option>';
							}
						$h .= '</select>';

					$h .= '</label>';

				}

			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	protected function getBackgrounds(array $settings): string {

		$h = '<div class="customize-backgrounds">';

			foreach([0, 1] as $intensity) {

				$h .= '<div class="customize-choices">';

					foreach(self::CUSTOM_BACKGROUNDS as $name => $backgrounds) {

						$background = $backgrounds[$intensity];

						$h .= '<a class="customize-background '.($settings['customBackground'] === $background ? 'selected' : '').'"';
							$h .= ' style="--customize-swatch: '.$background.'"';
							$h .= ' title="'.encode($name).'"';
							$h .= ' onclick="'.$this->getJs().'.set(\'customBackground\', \''.$background.'\')"></a>';

					}

				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	protected function getChoices(string $label, string $property, array $values, string $current): string {

		$h = '<div class="customize-group">';
			$h .= '<span class="customize-label">'.$label.'</span>';
			$h .= '<div class="customize-choices">';

				foreach($values as $value => $name) {
					$h .= '<a class="customize-choice '.((string)$value === $current ? 'selected' : '').'" onclick="'.$this->getJs().'.set(\''.$property.'\', \''.$value.'\')">'.encode($name).'</a>';
				}

			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

}
?>
