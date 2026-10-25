<?php
namespace series;

class DevObserverUi {

	public static function apiLabels(): array {

		return [
			'series' => [
				'title' => s('Plan de culture'),
				'description' => s('Votre plan de culture pour la saison : les séries, ce qui y est mis en culture, et les interventions planifiées avec leurs commentaires.')
			],
			'series\Comment' => [
				'title' => s('Commentaires d\'interventions'),
				'plural' => s('commentaires d\'interventions'),
				'singular' => s('commentaire d\'intervention'),
				'gender' => 'm',
				'description' => s('Les commentaires laissés sur une intervention du planning. Chaque commentaire est rattaché à une intervention, et hérite d\'elle sa série et sa mise en culture.')
			],
			'series\Cultivation' => [
				'title' => s('Mises en culture'),
				'plural' => s('mises en culture'),
				'singular' => s('mise en culture'),
				'gender' => 'f',
				'description' => s('La culture d\'une variété au sein d\'une série, sur une saison donnée (surface, densité, dates, itinéraire technique).')
			],
			'series\Series' => [
				'title' => s('Séries'),
				'plural' => s('séries'),
				'singular' => s('série'),
				'gender' => 'f',
				'description' => s('Les séries de votre plan de culture : un regroupement de cultures menées ensemble, sur les mêmes emplacements (planches), pour une saison donnée.')
			],
			'series\Place' => [
				'title' => s('Emplacements'),
				'plural' => s('emplacements'),
				'singular' => s('emplacement'),
				'gender' => 'm',
				'description' => s('L\'occupation des planches : une ligne par planche et par série, avec le linéaire et la surface qu\'elle y occupe. Une intervention posée hors série y figure aussi, sans dimensions. L\'assolement se modifie par les actions de la ressource série.')
			],
			'series\Slice' => [
				'title' => s('Répartitions de culture'),
				'plural' => s('répartitions de culture'),
				'singular' => s('répartition de culture'),
				'gender' => 'f',
				'description' => s('La part (en %, m², mL, nombre de plants ou de plateaux) qu\'une variété occupe au sein d\'une mise en culture, sur les différentes planches d\'une série.')
			],
			'series\Task' => [
				'title' => s('Interventions'),
				'plural' => s('interventions'),
				'singular' => s('intervention'),
				'gender' => 'f',
				'description' => s('Les interventions planifiées dans le planning. Chaque intervention est une occurrence d\'une action (semis, désherbage, récolte...), liée à une culture et éventuellement à une série.')
			],
			'series\Harvest' => [
				'title' => s('Récoltes'),
				'plural' => s('récoltes'),
				'singular' => s('récolte'),
				'gender' => 'f',
				'description' => s('Ce qui a été récolté, jour par jour : une ligne par intervention de récolte et par date, avec sa quantité et son unité. Les récoltes se saisissent sur l\'intervention, qui met à jour les cumuls de la mise en culture : elles sont ici en lecture seule. La période est obligatoire.')
			],
			'series\Timesheet' => [
				'title' => s('Temps de travail'),
				'plural' => s('temps de travail'),
				'singular' => s('temps de travail'),
				'gender' => 'm',
				'description' => s('Le temps passé sur une intervention : une ligne par intervention, par personne et par date. L\'intervention se désigne à la création et donne à la ligne sa série, sa mise en culture et son espèce. Seule la durée se modifie ensuite. La période est obligatoire.')
			]
		];

	}

	/** Sans ces libellés, la référence affiche un résumé fabriqué depuis le nom de la route. */
	public static function apiRoutes(): array {

		$beds = ['beds' => [
			'type' => 'array',
			'items' => ['type' => 'integer', 'format' => 'int64'],
			'description' => s("Références des planches"),
		]];

		// La taille se lit selon l'utilisation de la série : en mètres linéaires pour une série en
		// planches, en m² pour une série en bloc (PlaceLib::buildFromBeds()).
		$size = ['size' => [
			'type' => 'integer',
			'description' => s("Linéaire en mètres pour une série en planches, surface en m² pour une série en bloc"),
		]];

		return [
			'PATCH series/{id}/addBeds' => [
				'label' => s("Assoler une série sur des planches"),
				'body' => [
					'type' => 'object',
					'required' => ['beds'],
					'properties' => $beds + ['size' => $size['size'] + ['description' => s("Taille posée sur chaque planche ajoutée, ramenée à la longueur de la planche si elle la dépasse. Sans elle, la série prend ce que la planche a de libre.")]],
				],
			],
			'PATCH series/{id}/removeBeds' => [
				'label' => s("Retirer des planches d'une série"),
				'body' => [
					'type' => 'object',
					'required' => ['beds'],
					'properties' => $beds,
				],
			],
			'PATCH series/{id}/bedSize' => [
				'label' => s("Changer la taille occupée sur une planche"),
				'body' => [
					'type' => 'object',
					'required' => ['bed', 'size'],
					'properties' => ['bed' => ['type' => 'integer', 'format' => 'int64', 'description' => s("Référence d'une planche déjà occupée par la série")]] + $size,
				],
			],
			'PATCH series/{id}/clearBeds' => [
				'label' => s("Désassoler entièrement une série"),
				'body' => FALSE,
			],
			'PATCH series/{id}/close' => [
				'label' => s("Clôturer une série"),
				'body' => [
					'type' => 'object',
					'properties' => [
						'outcome' => [
							'type' => 'string',
							'nullable' => TRUE,
							'enum' => [Series::SUCCESS, Series::FAILURE, Series::CANCELED, Series::MIXED],
							'description' => s("Dénouement de la série. Absent, il reste tel quel."),
						],
						'comment' => ['type' => 'string', 'nullable' => TRUE, 'description' => s("Commentaire de la série, en HTML. Absent, il reste tel quel.")],
					],
				],
			],
			'PATCH series/{id}/open' => [
				'label' => s("Réouvrir une série clôturée"),
				'body' => FALSE,
			],
			'POST timesheet/distribution' => [
				'label' => s("Répartir un temps sur plusieurs interventions"),
				'body' => [
					'type' => 'object',
					'required' => ['tasks', 'user', 'date', 'time'],
					'properties' => [
						'tasks' => [
							'type' => 'array',
							'items' => ['type' => 'integer', 'format' => 'int64'],
							'description' => s("Références des interventions entre lesquelles répartir le temps"),
						],
						'user' => ['type' => 'integer', 'format' => 'int64', 'description' => s("Membre de l'équipe qui a travaillé")],
						'date' => ['type' => 'string', 'format' => 'date', 'description' => s("Jour travaillé, qui ne peut pas être dans le futur")],
						'time' => ['type' => 'number', 'description' => s("Temps à répartir, en heures")],
						'distribution' => [
							'type' => 'string',
							'enum' => \series\Timesheet_0_1betaApi::DISTRIBUTIONS,
							'description' => s("Clé de répartition : parts égales par défaut, sinon au prorata de la surface de la série, de ses plants, ou de ce qui a été récolté ce jour-là"),
						],
					],
				],
			],
			'PATCH task/{id}/harvest' => [
				'label' => s("Saisir une récolte sur une intervention"),
				'body' => [
					'type' => 'object',
					'required' => ['quantity', 'date'],
					'properties' => [
						'quantity' => [
							'type' => 'number',
							'description' => s("Quantité récoltée, ajoutée au total de l'intervention. Négative pour corriger une saisie de trop, tant que ni le total ni la journée ne deviennent négatifs."),
						],
						'date' => [
							'type' => 'string',
							'format' => 'date',
							'description' => s("Jour de la récolte, qui date la ligne du journal des récoltes"),
						],
						'unit' => [
							'type' => 'string',
							'enum' => [Task::KG, Task::UNIT, Task::BUNCH],
							'description' => s("Unité de récolte, posée une seule fois : une intervention qui en a déjà une garde la sienne"),
						],
					],
				],
			],
		];

	}

	/**
	 * Les collections embarquées, qu'aucune colonne du module ne porte, et les champs qu'une création
	 * lit dans le corps sans les construire (Task_0_1betaApi::load(), Timesheet_0_1betaApi::load()).
	 */
	public static function apiFields(): array {

		$reference = fn(string $description, bool $nullable = FALSE) => [
			'type' => 'integer',
			'format' => 'int64',
			'nullable' => $nullable,
			'gestures' => ['create'],
			'required' => ($nullable === FALSE),
			'description' => $description,
		];

		return [
			'series\Task' => [
				'status' => [
					'type' => 'string',
					'enum' => [Task::TODO, Task::DONE],
					'gestures' => ['create'],
					'required' => TRUE,
					'description' => s("Statut de l'intervention, à faire ou faite. Il ne se choisit qu'à la création et décide de la date attendue, planned pour une intervention à faire, done pour une intervention faite."),
				],
				'planned' => [
					'type' => 'object',
					'nullable' => TRUE,
					'writeOnly' => TRUE,
					'description' => s("Moment où l'intervention est prévue, pour le statut todo et ignoré pour une intervention faite. Il se donne au jour près par date, ou à la semaine près par week quand le jour n'est pas encore fixé, et date l'emporte si les deux sont envoyés. Null ou un objet vide laisse l'intervention sans date prévue. Il se relit dans plannedDate et plannedWeek, plannedDate restant null quand seule la semaine est donnée. La semaine doit tomber à un an au plus de la saison de la série, ou à moins de cinq ans d'aujourd'hui pour une intervention hors série."),
					'properties' => [
						'date' => [
							'type' => 'string',
							'format' => 'date',
							'example' => '2026-04-15',
							'description' => s("Jour où l'intervention est prévue, au format AAAA-MM-JJ"),
						],
						'week' => [
							'type' => 'string',
							'example' => '2026-W16',
							'description' => s("Semaine ISO où l'intervention est prévue, au format AAAA-Wss, de W01 à W53"),
						],
					],
				],
				'done' => [
					'type' => 'object',
					'writeOnly' => TRUE,
					'description' => s("Moment où l'intervention a été faite, à envoyer avec le statut done et ignoré pour une intervention à faire. Il se donne au jour près par date, ou à la semaine près par week quand le jour n'est pas connu, et date l'emporte si les deux sont envoyés. Il se relit dans doneDate et doneWeek, doneDate restant null quand seule la semaine est donnée. La semaine doit tomber à un an au plus de la saison de la série, ou à moins de cinq ans d'aujourd'hui pour une intervention hors série."),
					'properties' => [
						'date' => [
							'type' => 'string',
							'format' => 'date',
							'example' => '2026-04-15',
							'description' => s("Jour où l'intervention a été faite, au format AAAA-MM-JJ"),
						],
						'week' => [
							'type' => 'string',
							'example' => '2026-W16',
							'description' => s("Semaine ISO où l'intervention a été faite, au format AAAA-Wss, de W01 à W53"),
						],
					],
				],
				'category' => $reference(s("Référence de la catégorie de travail, qui borne les interventions possibles")),
				'series' => $reference(s("Référence de la série, ou null pour une intervention hors série"), TRUE),
				'cultivation' => $reference(s("Référence de la mise en culture dans la série, ou null pour une intervention qui vaut pour toute la série. Obligatoire pour un travail de culture sur une série d'une seule espèce."), TRUE),
				// Deux champs construits que les sondes du générateur n'atteignent pas : seule une intervention de
				// culture les lit, et getPropertiesCreate() décide sur la catégorie que porte l'élément.
				'plant' => ['required' => FALSE] + $reference(s("Référence de l'espèce, pour une intervention de culture hors série. Dans une série, elle vient de la mise en culture."), TRUE),
				'variety' => ['required' => FALSE] + $reference(s("Référence de la variété cultivée, pour une intervention de culture"), TRUE),
			],
			'series\Timesheet' => [
				'task' => $reference(s("Référence de l'intervention sur laquelle le temps a été passé. Elle donne à la ligne sa série, sa mise en culture et son espèce.")),
			],
			'series\Series' => [
				'cultivations' => [
					'type' => 'array',
					'description' => s("Cultures de la série, demandées par withCultivations"),
					'items' => ['$ref' => '#/components/schemas/Cultivation'],
				],
			],
			'series\Cultivation' => [
				'varieties' => [
					'type' => 'array',
					'description' => s("Répartition des variétés de la culture, une part par variété"),
					// Deux modules portent le nom Slice depuis l'exposition de l'itinéraire technique :
					// le générateur les préfixe de leur paquet (ApiDocLib::buildUniqueNames()).
					'items' => ['$ref' => '#/components/schemas/SeriesSlice'],
				],
			],
		];

	}

}
?>
