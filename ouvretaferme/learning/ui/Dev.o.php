<?php
namespace learning;

/**
 * Ce que la référence OpenAPI dit des formations, que le code ne peut pas dire : la section qui les
 * range et qui y a accès, les libellés des ressources et le résumé des routes qui ne sont ni une lecture ni une écriture de la ressource.
 */
class DevObserverUi {

	public static function apiScopes(): array {

		return [
			'learning' => [
				'title' => s("Formations et FAQ"),
				'description' => s("Ces routes ne répondent qu'à la clé d'un réseau d'accompagnement ou d'un groupe de formation dont les formations sont activées, avec le scope learning:read pour lire et learning:write pour écrire.")
			]
		];

	}

	public static function apiLabels(): array {

		return [
			// Le groupe des ressources qui n'en déclarent pas d'autre, la FAQ seule : un groupe d'une seule
			// ressource remonte sous sa section, et son titre ne s'affiche pas.
			'learning' => [
				'title' => s("Questions fréquentes")
			],
			// Des titres distincts de ceux des ressources et de la section : le même nom déclare deux
			// fois le tag, et GitBook n'affiche alors plus aucune catégorie de la référence.
			'learning:course' => [
				'title' => s("Contenu des formations"),
				'description' => s("Les formations écrites par le groupe, avec leurs phases, leurs modules, leurs leçons, les blocs qui composent une leçon et le nombre de lecteurs qui suivent une formation.")
			],
			'learning:date' => [
				'title' => s("Dates et inscriptions"),
				'description' => s("Les dates des formations et leurs inscriptions, en lecture seule.")
			],
			'learning\Course' => [
				'title' => s("Formations"),
				'group' => 'learning:course',
				'plural' => s("formations"),
				'singular' => s("formation"),
				'gender' => 'f',
				'description' => s("Les formations de votre groupe. Les objectifs s'écrivent en HTML et se relisent enveloppés dans une balise main, format de stockage de l'éditeur.")
			],
			'learning\Chapter' => [
				'title' => s("Modules"),
				'group' => 'learning:course',
				'plural' => s("modules"),
				'singular' => s("module"),
				'gender' => 'm',
				'description' => s("Les modules d'une formation, qui regroupent ses leçons. Un module supprimé emporte ses leçons ; supprimer celui qui porte les dernières leçons d'une formation publiée qu'un inscrit a suivie en entier est refusé en 412.")
			],
			'learning\Lesson' => [
				'title' => s("Leçons"),
				'group' => 'learning:course',
				'plural' => s("leçons"),
				'singular' => s("leçon"),
				'gender' => 'f',
				'description' => s("Les leçons d'un module. Une leçon change de module à l'intérieur de sa formation, jamais d'une formation à une autre. Supprimer la dernière leçon d'une formation publiée qu'un inscrit a suivie en entier est refusé en 412. Le contenu s'écrit en HTML et se relit enveloppé dans une balise main, format de stockage de l'éditeur. Une leçon se compose de blocs, et son contenu se lit comme les textes de ces blocs mis bout à bout. Il ne s'écrit que sur une leçon sans bloc ou d'un seul bloc de texte, et l'écriture sur une leçon composée est refusée avec l'erreur Lesson::content.blocks. La forme (format) se lit et ne s'écrit pas : video quand la leçon porte une vidéo, sinon steps quand elle porte des étapes numérotées, sinon text quand elle porte un texte ou un quiz, sinon empty.")
			],
			'learning\Block' => [
				'title' => s("Blocs des leçons"),
				'group' => 'learning:course',
				'plural' => s("blocs"),
				'singular' => s("bloc"),
				'gender' => 'm',
				'description' => s("Les blocs qui composent une leçon, un par un. Un bloc d'étapes, de volets, d'onglets, de chiffres clés ou de réponses se crée avec son premier enfant, décrit par title et content, et chaque enfant suivant désigne son parent par parent. Le retrait du dernier enfant emporte son parent. Le type, la leçon et le parent ne changent jamais. Seul un enfant de type block-item porte une image, qui se pose et se retire par sa propre route."),
			],
			'learning\Faq' => [
				'title' => s("FAQ"),
				'plural' => s("questions"),
				'singular' => s("question"),
				'gender' => 'f',
				'description' => s("Les questions de la FAQ de votre groupe, avec leur réponse, l'écran qu'elles concernent quand elles en visent un, et un verdict qui dit si ce qui est demandé est possible, possible en partie, voulu ou pas possible aujourd'hui. Une question reste en brouillon tant qu'elle n'est pas publiée. Le module auquel une question peut renvoyer est toujours un module d'une formation de votre groupe.")
			],
			'learning\Phase' => [
				'title' => s("Phases"),
				'group' => 'learning:course',
				'plural' => s("phases"),
				'singular' => s("phase"),
				'gender' => 'f',
				'description' => s("Les phases d'une formation de votre groupe, qui rangent ses modules. Les phases se suivent dans l'ordre de leur création. Les phases d'une formation se listent par la route de la formation. Une phase supprimée n'emporte pas ses modules, qui restent dans la formation sans phase.")
			],
			'learning\Date' => [
				'title' => s("Dates"),
				'group' => 'learning:date',
				'plural' => s("dates"),
				'singular' => s("date"),
				'gender' => 'f',
				'description' => s("Les dates des formations sur place ou à distance de votre réseau, en lecture seule. Le lien de visioconférence est rendu à la clé du réseau, mais la page publique du réseau ne le montre pas.")
			],
			'learning\DateEnrollment' => [
				'title' => s("Inscriptions aux dates"),
				'group' => 'learning:date',
				'plural' => s("inscriptions"),
				'singular' => s("inscription"),
				'gender' => 'f',
				'description' => s("Les inscriptions aux dates de votre réseau, en lecture seule, avec les champs de l'export que le réseau télécharge à l'écran. Une inscription faite avec un compte rend l'identité de ce compte, une inscription faite sans compte l'identité saisie.")
			]
		];

	}

	/**
	 * Une entrée remplace le schéma que la colonne donnerait : type et bornes y sont redits.
	 */
	public static function apiFields(): array {

		return [
			'learning\Block' => [
				'lesson' => [
					'type' => 'integer',
					'description' => s("Identifiant de la leçon qui porte le bloc"),
				],
				'media' => [
					'type' => 'string',
					'format' => 'uri',
					'nullable' => TRUE,
					'description' => s("Adresse de l'image d'un enfant de type block-item, ou null s'il n'en a pas. Elle ne s'écrit que par la route block/{id}/media"),
				],
			],
			'learning\Chapter' => [
				'name' => [
					'type' => 'string',
					'minLength' => 1,
					'maxLength' => 100,
					'description' => s("Nom du module, sans numéro, que l'écran ajoute devant le nom pour une formation en ligne"),
				],
			],
			// `subthemes` est rendu par `themeList`, sur l'élément `learning\Theme`, qui n'est pas une
			// ressource exposée : sa route vit dans le fichier d'API des formations, et les corrélations
			// se rattachent à la ressource du fichier qui les déclare (ApiDocFieldTypeSentinel). Le
			// schéma de la formation ne le publie pas, n'ayant pas ce champ dans sa liste.
			'learning\Course' => [
				'subthemes' => [
					'type' => 'array',
					'description' => s("Les sous-thématiques de la thématique, dans leur ordre"),
					'items' => [
						'type' => 'object',
						'properties' => [
							'id' => ['type' => 'integer', 'description' => s("Identifiant de la sous-thématique")],
							'name' => ['type' => 'string', 'description' => s("Nom de la sous-thématique")],
							'position' => ['type' => 'integer', 'description' => s("Place dans la thématique, de zéro")],
						],
					],
				],
			],
			'learning\Date' => [
				'sequences' => [
					'type' => 'array',
					'description' => s("Les séances de la date, dans l'ordre où elles se tiennent, chacune sur un seul jour. Une date peut mêler des séances en présentiel et en visio, et ses séances en présentiel peuvent se tenir en plusieurs lieux"),
					'items' => [
						'type' => 'object',
						'properties' => [
							'startAt' => ['type' => 'string', 'format' => 'date-time', 'description' => s("Début de la séance")],
							'endAt' => ['type' => 'string', 'format' => 'date-time', 'description' => s("Fin de la séance, le même jour")],
							'mode' => ['type' => 'string', 'enum' => [DateSequence::ONSITE, DateSequence::REMOTE], 'description' => s("En présentiel ou en visio")],
							'place' => ['type' => 'string', 'nullable' => TRUE, 'description' => s("Lieu de la séance en présentiel, vide en visio")],
						],
					],
				],
			],
			'learning\DateEnrollment' => [
				'firstName' => [
					'type' => 'string',
					'nullable' => TRUE,
					'description' => s("Prénom, lu sur le compte de la personne quand elle s'est inscrite avec un compte"),
				],
				'lastName' => [
					'type' => 'string',
					'description' => s("Nom, lu sur le compte de la personne quand elle s'est inscrite avec un compte"),
				],
				'email' => [
					'type' => 'string',
					'format' => 'email',
					'description' => s("Adresse e-mail, lue sur le compte de la personne quand elle s'est inscrite avec un compte"),
				],
				'structureName' => [
					'type' => 'string',
					'nullable' => TRUE,
					'description' => s("La structure au nom de laquelle la personne s'est inscrite, ou celle qu'elle a saisie sans compte. Vide pour une inscription à titre personnel"),
				],
				// Le describer la nomme en heures, l'unité de sa saisie à l'écran : la colonne et la valeur rendue sont en minutes
				'attendedMinutes' => [
					'type' => 'integer',
					'nullable' => TRUE,
					'description' => s("Durée de présence en minutes, quand elle diffère de celle de la date, et vide quand elle lui est égale"),
				],
			],
			'learning\Lesson' => [
				'content' => [
					'type' => 'string',
					'nullable' => TRUE,
					'description' => s("Contenu de la leçon en HTML, lu dans les textes de ses blocs mis bout à bout"),
				],
				'format' => [
					'type' => 'string',
					'enum' => [Lesson::VIDEO, Lesson::STEPS, Lesson::TEXT, Lesson::EMPTY],
					'description' => s("Forme de la leçon, calculée sur son contenu. Elle vaut video quand la leçon porte une vidéo, sinon steps quand elle porte des étapes numérotées, sinon text quand elle porte un texte ou un quiz, et empty quand elle n'a rien à lire"),
				],
			],
		];

	}

	public static function apiRoutes(): array {

		$integer = ['type' => 'integer'];

		$list = fn(array $properties) => [
			'type' => 'object',
			'properties' => [
				'list' => [
					'type' => 'array',
					'items' => ['type' => 'object', 'properties' => $properties]
				]
			]
		];

		return [
			'GET lesson/{id}/blocksList' => ['resource' => 'learning\Block', 'label' => s("Lister les blocs d'une leçon")],
			'PUT block/{id}/media' => [
				'label' => s("Déposer l'image d'un bloc"),
				'body' => [
					'type' => 'object',
					'required' => ['content'],
					'properties' => [
						'content' => [
							'type' => 'string',
							'format' => 'byte',
							'description' => s("Le fichier encodé en base64, 10 Mo au plus. Le format est établi en décodant le contenu, et jpeg, png, gif, bmp, webp et svg sont acceptés en entrée."),
						],
					],
				],
			],
			'DELETE block/{id}/media' => [
				'label' => s("Retirer l'image d'un bloc"),
				'body' => FALSE,
			],
			'GET course/{id}/chapterList' => ['resource' => 'learning\Chapter', 'label' => s("Lister les modules d'une formation")],
			'GET course/{id}/phasesList' => ['resource' => 'learning\Phase', 'label' => s("Lister les phases d'une formation")],
			'GET chapter/{id}/lessonList' => ['resource' => 'learning\Lesson', 'label' => s("Lister les leçons d'un module")],
			'GET courseDate/{id}/enrollmentList' => ['resource' => 'learning\DateEnrollment', 'label' => s("Lister les inscriptions à une date")],
			'GET themeList' => [
				'label' => s("Lister les thématiques"),
				'response' => [
					'description' => s("Les thématiques qu'une formation peut porter, dans leur ordre."),
					'schema' => $list([
						'id' => $integer,
						'name' => ['type' => 'string'],
						'icon' => ['type' => 'string'],
						'color' => ['type' => 'string'],
						'position' => $integer
					])
				]
			],
			'GET levelList' => [
				'label' => s("Lister les niveaux"),
				'response' => [
					'description' => s("Les niveaux qu'une formation peut porter, dans leur ordre."),
					'schema' => $list([
						'id' => $integer,
						'name' => ['type' => 'string'],
						'position' => $integer
					])
				]
			],
			'GET course/{id}/usage' => [
				'label' => s("Lire le bilan d'une formation"),
				'response' => [
					'description' => s("Ce que les lecteurs font de la formation sur une période, en nombres et sans aucun lecteur nommé. La période va d'un lundi jusqu'à aujourd'hui, pour que chaque semaine soit entière, et le paramètre period choisit ce lundi. Avec 30d et 90d (par défaut), c'est le lundi qui précède le premier des 30 ou 90 derniers jours, aujourd'hui compris, ou ce premier jour s'il tombe un lundi. Avec 12m, c'est le lundi qui suit le premier jour du mois d'il y a onze mois, ou ce jour s'il tombe un lundi, ce qui donne toujours douze pas avec step=month. Avec all, c'est le lundi qui précède la première inscription confirmée, ou à défaut la publication de la formation, ou sa création si elle n'est pas publiée, ou ce jour s'il tombe un lundi. Les comptes sur la période partent de ce lundi. Avec step=month, la première barre de la série porte le premier jour de son mois mais ne compte qu'à partir de from. Le paramètre step donne le pas de la série, week (par semaine, par défaut) ou month (par mois), et month est refusé sur 30d. Une formation à dates n'a pas de bilan et répond 412."),
					'schema' => [
						'type' => 'object',
						'properties' => [
							'course' => $integer,
							'period' => ['type' => 'string', 'enum' => UsageLib::PERIODS],
							'from' => ['type' => 'string', 'format' => 'date', 'description' => s("Premier jour de la période, toujours un lundi")],
							'to' => ['type' => 'string', 'format' => 'date', 'description' => s("Dernier jour de la période, aujourd'hui")],
							'step' => ['type' => 'string', 'enum' => UsageLib::STEPS, 'description' => s("Pas de la série")],
							'readers' => ['type' => 'integer', 'description' => s("Lecteurs inscrits à ce jour")],
							'readersCompleted' => ['type' => 'integer', 'description' => s("Lecteurs qui ont suivi toutes les leçons, à ce jour")],
							'completionRate' => ['type' => 'integer', 'nullable' => TRUE, 'description' => s("Part des lecteurs inscrits qui ont suivi toutes les leçons, en pour cent, nulle sans lecteur")],
							'enrolled' => ['type' => 'integer', 'description' => s("Inscriptions confirmées sur la période")],
							'readersActive' => ['type' => 'integer', 'description' => s("Lecteurs qui ont suivi au moins une leçon sur la période")],
							'completed' => ['type' => 'integer', 'description' => s("Lecteurs dont la dernière leçon suivie tombe dans la période")],
							'reviews' => [
								'type' => 'object',
								'description' => s("Avis affichés déposés sur la période"),
								'properties' => [
									'count' => $integer,
									'rating' => ['type' => 'number', 'nullable' => TRUE, 'description' => s("Note moyenne sur 5, nulle sans avis")],
									'distribution' => ['type' => 'object', 'description' => s("Nombre d'avis par note, de 5 à 1")]
								]
							],
							'comments' => [
								'type' => 'object',
								'description' => s("Commentaires de premier niveau déposés sur la période"),
								'properties' => [
									'count' => $integer,
									'unanswered' => ['type' => 'integer', 'description' => s("Dont sans réponse de l'équipe")]
								]
							],
							'series' => [
								'type' => 'array',
								'description' => s("Un élément par pas de la période, pas vides compris"),
								'items' => ['type' => 'object', 'properties' => [
									'date' => ['type' => 'string', 'format' => 'date', 'description' => s("Premier jour du pas")],
									'enrolled' => $integer,
									'readersActive' => $integer,
									'completed' => $integer,
									'reviews' => $integer,
									'comments' => $integer
								]]
							],
							'lessons' => [
								'type' => 'array',
								'description' => s("Chaque leçon dans l'ordre du programme, sur la période"),
								'items' => ['type' => 'object', 'properties' => [
									'lesson' => $integer,
									'readersDone' => ['type' => 'integer', 'description' => s("Lecteurs qui l'ont suivie")],
									'medianSeconds' => ['type' => 'integer', 'nullable' => TRUE, 'description' => s("Temps médian passé par ses lecteurs, en secondes")],
									'comments' => $integer,
									'quizFirstAttemptRate' => ['type' => 'integer', 'nullable' => TRUE, 'description' => s("Pour une leçon validée par quiz, part des premiers essais réussis, en pour cent")]
								]]
							]
						]
					]
				]
			]
		];

	}

}
?>
