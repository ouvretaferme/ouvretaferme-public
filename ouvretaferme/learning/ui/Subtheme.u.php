<?php
namespace learning;

/**
 * Le describer des sous-thématiques. Leur panneau et leurs formulaires vivent dans `ThemeUi`, sur
 * l'écran des thématiques, comme ceux des niveaux.
 */
class SubthemeUi {

	public static function p(string $property): \PropertyDescriber {

		return Subtheme::model()->describer($property, [
			'name' => s("Nom"),
		]);

	}

}
?>
