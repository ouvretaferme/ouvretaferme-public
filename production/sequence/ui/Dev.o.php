<?php
namespace sequence;

class DevObserverUi {

	public static function apiLabels(): array {

		return [
			'sequence' => [
				'title' => s('Itinéraires techniques'),
				'description' => s('Vos modèles de culture : les espèces menées ensemble, leur conduite, et les interventions à mener sur chacune. Une série du plan de culture se déroule à partir d\'un itinéraire.')
			],
			'sequence\Sequence' => [
				'title' => s('Itinéraires'),
				'plural' => s('itinéraires techniques'),
				'singular' => s('itinéraire technique'),
				'gender' => 'm',
				'description' => s('Un modèle de culture, annuel ou pérenne, dont on déroule des séries. Les espèces qu\'il réunit s\'envoient à sa création et ne se changent plus ensuite.')
			],
			'sequence\Crop' => [
				'title' => s('Espèces d\'un itinéraire'),
				'plural' => s('espèces d\'itinéraire'),
				'singular' => s('espèce d\'itinéraire'),
				'gender' => 'f',
				'description' => s('Une espèce dans un itinéraire technique, avec sa conduite (écartements ou densité, semis, rendement attendu) et la répartition des variétés qu\'on y met. Une même espèce n\'y figure qu\'une fois.')
			],
			'sequence\Flow' => [
				'title' => s('Interventions d\'un itinéraire'),
				'plural' => s('interventions d\'itinéraire'),
				'singular' => s('intervention d\'itinéraire'),
				'gender' => 'f',
				'description' => s('Une intervention modèle, datée en semaines relatives plutôt qu\'en dates : c\'est ce que le plan de culture déroule en interventions le jour où une série naît de l\'itinéraire. Elle porte sur une espèce, ou sur tout l\'itinéraire.')
			],
			'sequence\Slice' => [
				'title' => s('Variétés d\'une espèce d\'itinéraire'),
				'plural' => s('parts de variété'),
				'singular' => s('part de variété'),
				'gender' => 'f',
				'description' => s('La part d\'une variété dans une espèce d\'itinéraire, en pourcentage. Se pose et se retire avec l\'espèce qui la porte.')
			]
		];

	}

	/** Les collections embarquées, qu'aucune colonne du module ne porte. */
	public static function apiFields(): array {

		return [
			'sequence\Crop' => [
				'varieties' => [
					'type' => 'array',
					'description' => s("Répartition des variétés de l'espèce, une part par variété"),
					'items' => ['$ref' => '#/components/schemas/SequenceSlice'],
				],
			],
		];

	}

}
?>
