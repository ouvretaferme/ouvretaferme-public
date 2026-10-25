<?php
namespace structure;

class StructureShopUi implements StructureCommonUi {

	public function getCreateForm(\util\FormUi $form, Structure $eStructure): string {

		$h = $form->asteriskInfo();

		$start = '<ul>';
			$start .= '<li>'.s("Vendre à plusieurs producteurs sur une même boutique en ligne").'</li>';
			$start .= '<li>'.s("Gérer la logistique relatives aux commandes réalisées auprès du groupement").'</li>';
		$start .= '</ul>';

		$h .= new \main\DesignUi()->getStart(
			\Asset::icon('info'),
			s("À quoi sert un groupement de producteurs ?"),
			$start
		);
		
		$h .= $form->hidden('type', \structure\Structure::SHOP);
		$h .= $form->dynamicGroups($eStructure, ['name*', 'legalEmail*', 'isLegal', 'legalCountry']);

		$h .= $form->group(
			content: $form->submit(s("Créer le groupement de producteurs"), ['data-waiter' => s("Création en cours")])
		);

		return $h;

	}

	public function getCreateTitle(Structure $eStructure): string {

		return s("Créer un groupement de producteurs");

	}

	public static function getWelcomeMascot(): string {
		return 'papillon-jaune';
	}

	public static function getText(Structure $eStructure, string $text, mixed $value = 1): string {

		return match($text) {

			'of' => s("du groupement de producteurs"),
			'one' => s("un groupement de producteurs"),
			'the' => s("le groupement de producteurs"),
			'this' => s("ce groupement de producteurs"),
			'your' => s("votre groupement de producteurs"),

			'my' => p("Mon groupement de producteurs", "Mes groupements de producteurs", $value),

			'propertyLogo' => s("Logo du groupement"),
			'propertyBanner' => s("Bandeau du groupement"),
			'propertyName' => s("Nom du groupement"),
			'propertyIsLegal' => s("Votre groupement est-il porté par une structure juridique ?"),
			'propertyLegalEmail' => s("Adresse e-mail du groupement"),
			'propertyPresentation' => s("Présentation du groupement"),

			'updateLegal' => s("Votre groupement de producteur est désormais porté par une structure juridique ?"),

			'settingsGeneral' => s("Administratif"),

			'delete' => s("Supprimer le groupement")

		};

	}

}
?>
