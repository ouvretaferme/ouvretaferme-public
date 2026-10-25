<?php
namespace toolbox;

/**
 * Le réglage du carnet d'entretien, ouvert depuis le menu du carnet. Deux champs : l'intervention
 * du planning que prend un entretien fait (planche production-finir-carnet-planning, Q4 C), que le
 * premier « Fait » demande aussi (`MaintenanceDoneUi::create()`), et le rythme du rappel des
 * compteurs à relever.
 */
class MaintenanceConfigurationUi {

	public static function urlCreate(\structure\Structure $eStructure): string {
		return '/toolbox/maintenanceConfiguration:create?structure='.$eStructure['id'];
	}

	public function create(MaintenanceConfiguration $eMaintenanceConfiguration): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/toolbox/maintenanceConfiguration:doCreate', ['id' => 'maintenance-configuration-create']);

			$h .= $form->hidden('structure', $eMaintenanceConfiguration['structure']);
			$h .= $form->dynamicGroup($eMaintenanceConfiguration, 'action*');
			$h .= $form->dynamicGroup($eMaintenanceConfiguration, 'readingEveryMonths*');

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-maintenance-configuration-create',
			title: s("Paramétrer le carnet d'entretien"),
			body: $h
		);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = MaintenanceConfiguration::model()->describer($property, [
			'action' => s("Intervention au planning"),
			'readingEveryMonths' => s("Fréquence de rappel pour relever les compteurs d'heures et de kilomètres"),
		]);

		switch($property) {

			case 'action' :
				$d->values = fn(MaintenanceConfiguration $e) => $e['cAction'] ?? $e->expects(['cAction']);
				$d->labelAfter = \util\FormUi::info(s("Celle que prend au planning un entretien noté fait."));
				break;

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
