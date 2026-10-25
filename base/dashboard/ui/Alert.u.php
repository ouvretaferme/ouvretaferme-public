<?php
namespace dashboard;

class AlertUi {

	public static function getError(string $fqn, array $options = []): mixed {

		return match($fqn) {

			'Preference::name.check' => s("Ce réglage n'existe pas."),
			'Preference::position.check' => s("L'ordre n'a pas pu être enregistré, merci de recharger la page."),
			'Preference::sectionHidden' => s("Un univers s'éteint par son propre interrupteur."),
			'Preference::positionsIncomplete' => s("L'ordre n'a pas pu être enregistré, merci de recharger la page."),

			default => NULL

		};

	}

}
?>
