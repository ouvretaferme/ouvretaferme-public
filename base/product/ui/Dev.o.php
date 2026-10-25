<?php
namespace product;

class DevObserverUi {

	public static function apiLabels(): array {

		return [
			'product' => [
				'title' => s('Catalogue produits'),
				'menu' => s('Produits'),
				'description' => s('Ce que vous vendez : vos produits, et les unités et les catégories qui les décrivent.')
			],
			'product\Unit' => [
				'title' => s('Unités'),
				'plural' => s('unités'),
				'singular' => s('unité'),
				'gender' => 'f',
				'description' => s("Les unités de mesure utilisées pour vendre vos produits (kg, botte, barquette...). Seules les unités propres à votre structure sont rendues, pas le catalogue d'unités proposé par défaut à la création d'une structure.")
			],
			'product\Product' => [
				'title' => s('Produits'),
				'plural' => s('produits'),
				'singular' => s('produit'),
				'gender' => 'm',
				'description' => s('Les produits proposés à la vente par votre structure.')
			],
			'product\Category' => [
				'title' => s('Catégories de produits'),
				'menu' => s('Catégories'),
				'plural' => s('catégories de produits'),
				'singular' => s('catégorie de produits'),
				'gender' => 'f',
				'description' => s('Les catégories utilisées pour regrouper vos produits sur votre page d\'administration et vos boutiques en ligne.')
			],
			'product\Stock' => [
				'title' => s('Mouvements de stock'),
				'plural' => s('mouvements de stock'),
				'singular' => s('mouvement de stock'),
				'gender' => 'm',
				'description' => s("L'historique du stock d'un produit : ce qui a été posé ou ajouté, et le solde qui en résulte. Le produit est un filtre obligatoire, la table n'ayant d'index que sur lui. Les mouvements s'écrivent par les gestes de la ressource produit, qui recalculent le solde dans le même passage ; le dernier d'entre eux accompagne le produit sous stockLast.")
			]
		];

	}

	/** La vignette sort en adresse, pas sous la forme stockée en base. */
	/** Sans ces libellés, la référence affiche un résumé fabriqué depuis le nom de la route. */
	public   static function apiRoutes(): array {

		return [
			'PATCH product/{id}/stock' => [
				'label' => s("Poser ou faire varier le stock d'un produit"),
				'body' => [
					'type' => 'object',
					'required' => ['quantity'],
					'properties' => [
						'quantity' => [
							'type' => 'number',
							'nullable' => TRUE,
							'description' => s("Sans signe, la quantité devient le solde ; avec un signe, elle s'y ajoute ou s'en retire. Nulle et sans signe, elle efface le solde sans fermer le suivi."),
						],
						'sign' => [
							'type' => 'string',
							'enum' => ['+', '-'],
							'description' => s("Absent pour poser un solde, présent pour le faire varier"),
						],
						'comment' => [
							'type' => 'string',
							'nullable' => TRUE,
							'description' => s("Ce que le mouvement note, libre et gardé au journal"),
						],
					],
				],
			],
			'PATCH product/{id}/enableStock' => [
				'label' => s("Suivre le stock d'un produit"),
				'body' => FALSE,
			],
			'PATCH product/{id}/disableStock' => [
				'label' => s("Cesser de suivre le stock d'un produit"),
				'body' => FALSE,
			],
			'PUT product/{id}/vignette' => [
				'label' => s("Déposer la vignette d'un produit"),
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
			'DELETE product/{id}/vignette' => [
				'label' => s("Retirer la vignette d'un produit"),
				'body' => FALSE,
			],
		];

	}

	public static function apiFields(): array {

		return [
			'product\Product' => [
				// La corrélation rend le mouvement entier, pas la référence que porte la colonne
				'stockLast' => [
					'nullable' => TRUE,
					'description' => s("Dernier mouvement de stock, ou null si le produit n'en a pas"),
					'allOf' => [['$ref' => '#/components/schemas/Stock']],
				],
				'vignette' => [
					'type' => 'string',
					'format' => 'uri',
					'nullable' => TRUE,
					'description' => s("Adresse de la vignette, ou null si le produit n'en a pas"),
				],
			],
		];

	}

}
?>
