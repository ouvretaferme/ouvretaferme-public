<?php
namespace planning;

class DevObserverUi {

	public static function apiLabels(): array {

		return [
			'planning' => [
				'title' => s('Planning'),
				'description' => s('Le planning de la semaine, et son pense-bête partagé par toute l\'équipe.')
			],
			'planning\Reminder' => [
				'title' => s('Pense-bête'),
				'plural' => s('rappels'),
				'singular' => s('rappel'),
				'gender' => 'm',
				'description' => s('Les rappels du pense-bête : une ligne de texte rattachée à une semaine, avec un jour en option, qu\'on coche une fois faite. Un rappel ne se crée pas dans une semaine passée. Un rappel coché dans une semaine passée ne se corrige plus et ne s\'efface plus. La liste rend chaque rappel dans sa semaine, sans le recopier dans les semaines où il reste à faire.')
			]
		];

	}

	/** Sans ces libellés, la référence affiche un résumé fabriqué depuis le nom de la route. */
	public static function apiRoutes(): array {

		return [
			'PATCH reminder/{id}/check' => [
				'label' => s("Cocher un rappel"),
				'body' => FALSE,
			],
			'PATCH reminder/{id}/uncheck' => [
				'label' => s("Décocher un rappel"),
				'body' => FALSE,
			],
		];

	}

}
?>
