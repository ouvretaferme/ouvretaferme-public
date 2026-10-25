<?php
namespace toolbox;

/**
 * Le réglage de la consommation d'eau, ouvert depuis la roue de l'outil : le rythme du rappel des
 * compteurs d'eau à relever, avec les mots et le champ du réglage du carnet d'entretien
 * (`MaintenanceConfigurationUi`).
 */
class WaterConfigurationUi {

	public static function urlCreate(\structure\Structure $eStructure): string {
		return '/toolbox/waterConfiguration:create?structure='.$eStructure['id'];
	}

	public function create(WaterConfiguration $eWaterConfiguration): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/toolbox/waterConfiguration:doCreate', ['id' => 'water-configuration-create']);

			$h .= $form->hidden('structure', $eWaterConfiguration['structure']);
			$h .= $form->dynamicGroup($eWaterConfiguration, 'readingEveryMonths*');

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-water-configuration-create',
			title: s("Paramétrer la consommation d'eau"),
			body: $h
		);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = WaterConfiguration::model()->describer($property, [
			'readingEveryMonths' => s("Fréquence de rappel pour relever les compteurs d'eau"),
		]);

		switch($property) {

			case 'readingEveryMonths' :
				$d->field = 'select';
				$d->required = TRUE;
				$d->labelAfter = \util\FormUi::info(s("Envoyé le 5 du mois, en comptant à partir de janvier."));
				$d->values = [
					1 => s("Tous les mois"),
					2 => s("Tous les 2 mois"),
					3 => s("Tous les 3 mois"),
					6 => s("Tous les 6 mois"),
				];
				break;

		}

		return $d;

	}

}
?>
