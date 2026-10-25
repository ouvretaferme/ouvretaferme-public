<?php
namespace plant;

class DevObserverUi {

	public static function apiLabels(): array {

		return [
			'plant' => [
				'title' => s('Espèces et variétés'),
				'description' => s('Ce que vous cultivez : vos espèces et leurs variétés, les calibres et les tags qui les décrivent, et les récoltes attendues saison par saison.')
			],
			'plant\Plant' => [
				'title' => s('Espèces'),
				'plural' => s('espèces'),
				'singular' => s('espèce'),
				'gender' => 'f',
				'description' => s('Les espèces cultivées par votre structure.')
			],
			'plant\Family' => [
				'title' => s("Familles botaniques"),
				'plural' => s("familles botaniques"),
				'singular' => s("famille botanique"),
				'gender' => 'f',
				'description' => s("Le référentiel des familles botaniques, commun à toutes les structures et consultable uniquement. Les ids servent à renseigner la famille d'une espèce.")
			],
			'plant\Forecast' => [
				'title' => s('Prévisionnels de récolte'),
				'plural' => s('prévisionnels de récolte'),
				'singular' => s('prévisionnel de récolte'),
				'gender' => 'm',
				'description' => s('Les prévisionnels de récolte par espèce et par saison (quantité attendue, répartition professionnel / autoconsommation).')
			],
			'plant\Size' => [
				'title' => s('Calibres'),
				'plural' => s('calibres'),
				'singular' => s('calibre'),
				'gender' => 'm',
				'description' => s('Les calibres définis pour vos espèces (ex. petit / moyen / gros), utilisés notamment pour distinguer les rendements par calibre.')
			],
			'plant\Tag' => [
				'title' => s("Tags de variétés"),
				'plural' => s("tags de variétés"),
				'singular' => s("tag de variété"),
				'gender' => 'm',
				'description' => s("Le vocabulaire commun par lequel on décrit une variété (couleur, forme, usage…), partagé par toutes les structures et consultable uniquement. Le filtre plant donne les tags qu'une espèce accepte.")
			],
			'plant\Variety' => [
				'title' => s('Variétés'),
				'plural' => s('variétés'),
				'singular' => s('variété'),
				'gender' => 'f',
				'description' => s('Les variétés cultivées pour chacune de vos espèces, avec leurs fournisseurs de semences et de plants.')
			]
		];

	}

	/** La vignette sort en adresse, pas sous la forme stockée en base. */
	/** Sans ces libellés, la référence affiche un résumé fabriqué depuis le nom de la route. */
	public static function apiRoutes(): array {

		return [
			'PUT plant/{id}/vignette' => [
				'label' => s("Déposer la vignette d'une culture"),
				'body' => [
					'type' => 'object',
					'required' => ['content'],
					'properties' => [
						'content' => [
							'type' => 'string',
							'format' => 'byte',
							'description' => s("Le fichier encodé en base64, 10 Mo au plus. Le format est établi en décodant le contenu : jpeg, png, gif, bmp, webp et svg sont acceptés en entrée."),
						],
					],
				],
			],
			'DELETE plant/{id}/vignette' => [
				'label' => s("Retirer la vignette d'une culture"),
				'body' => FALSE,
			],
		];

	}

	public static function apiFields(): array {

		return [
			'plant\Plant' => [
				'vignette' => [
					'type' => 'string',
					'format' => 'uri',
					'nullable' => TRUE,
					'description' => s("Adresse de la vignette, ou null si la culture n'en a pas"),
				],
			],
		];

	}

}
?>
