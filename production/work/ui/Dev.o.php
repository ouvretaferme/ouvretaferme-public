<?php
namespace work;

class DevObserverUi {

	public static function apiLabels(): array {

		return [
			'work' => [
				'title' => s('Actions et matériel'),
				'description' => s('De quoi décrire le travail aux champs : les actions réalisables sur vos cultures, leurs catégories et leurs méthodes, le matériel qui les outille et vos fournisseurs de semences et plants.')
			],
			'work\Action' => [
				'title' => s('Actions'),
				'plural' => s('actions'),
				'singular' => s('action'),
				'gender' => 'f',
				'description' => s('Les actions réalisables sur vos cultures (semis, désherbage, récolte...). Chaque intervention du planning est une occurrence d\'une action.')
			],
			'work\Category' => [
				'title' => s('Catégories d\'action'),
				'plural' => s('catégories d\'action'),
				'singular' => s('catégorie d\'action'),
				'gender' => 'f',
				'description' => s('Les catégories utilisées pour regrouper vos actions.')
			],
			'work\Method' => [
				'title' => s('Méthodes de travail'),
				'plural' => s('méthodes de travail'),
				'singular' => s('méthode de travail'),
				'gender' => 'f',
				'description' => s('Les méthodes de travail associées à une action (ex. techniques ou outils spécifiques utilisés pour la réaliser).')
			],
			'work\Supplier' => [
				'title' => s('Fournisseurs de semences et plants'),
				'plural' => s('fournisseurs de semences et plants'),
				'singular' => s('fournisseur de semences et plants'),
				'gender' => 'm',
				'description' => s('Les fournisseurs de semences et de plants utilisés pour vos variétés.')
			],
			// Le catalogue E-Phy vit dans work, mais la doc le range avec le carnet de traitement qu'il sert.
			'work\PhytoProduct' => [
				'group' => 'toolbox',
				'title' => s('Catalogue E-Phy'),
				'plural' => s('produits du catalogue E-Phy'),
				'singular' => s('produit du catalogue E-Phy'),
				'gender' => 'm',
				'description' => s('Les produits phytopharmaceutiques du catalogue E-Phy de l\'ANSES, rechargé chaque semaine : les produits autorisés, et les produits retirés tant qu\'un de leurs usages reste utilisable. Ce catalogue est le même pour tout le monde et ne se modifie pas. Le produit que votre structure note est un matériel à routine phyto, qui n\'en garde que le numéro d\'AMM.')
			],
			'work\PhytoUsage' => [
				'group' => 'toolbox',
				'title' => s('Usages du catalogue E-Phy'),
				'plural' => s('usages du catalogue E-Phy'),
				'singular' => s('usage du catalogue E-Phy'),
				'gender' => 'm',
				'description' => s('Ce qu\'un produit du catalogue E-Phy est autorisé à traiter : la culture, la cible, la dose et le délai avant récolte. Le catalogue publie une ligne par cible, par mode de traitement et par délai, si bien qu\'un même produit sur une même culture en porte souvent plusieurs.')
			],
			'work\Tool' => [
				'title' => s('Matériel'),
				'plural' => s('matériel'),
				'singular' => s('matériel'),
				'gender' => 'm',
				'description' => s('Le matériel utilisé pour réaliser vos interventions (outils, équipements...). La liste écarte les matériels supprimés, que ?status=deleted retrouve. Supprimer un matériel que cite une intervention, un itinéraire ou le carnet d\'entretien ne l\'efface pas, il passe au statut deleted et le carnet arrête de le suivre.')
			]
		];

	}

	/** Les modes d'exécution rattachés à une action, rendus par leurs seuls identifiants. */
	/** Sans ces libellés, la référence affiche un résumé fabriqué depuis le nom de la route. */
	public static function apiRoutes(): array {

		return [
			'PUT tool/{id}/vignette' => [
				'label' => s("Déposer la vignette d'un matériel"),
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
			'DELETE tool/{id}/vignette' => [
				'label' => s("Retirer la vignette d'un matériel"),
				'body' => FALSE,
			],
		];

	}

	public static function apiFields(): array {

		return [
			'work\Tool' => [
				'vignette' => [
					'type' => 'string',
					'format' => 'uri',
					'nullable' => TRUE,
					'description' => s("Adresse de la vignette, ou null si l'outil n'en a pas"),
				],
			],
			'work\PhytoProduct' => [
				'otherNames' => [
					'type' => 'array',
					'description' => s("Les seconds noms commerciaux sous lesquels le catalogue connaît ce produit"),
					'items' => ['type' => 'string'],
				],
				'substances' => [
					'type' => 'array',
					'description' => s("Les substances actives du produit, chacune sous son nom international et le nom français le plus court que le catalogue lui donne"),
					'items' => [
						'type' => 'object',
						'properties' => [
							'international' => ['type' => 'string'],
							'name' => ['type' => 'string'],
						],
					],
				],
			],
			'work\PhytoUsage' => [
				'plants' => [
					'type' => 'array',
					'description' => s("Références des espèces du référentiel partagé que désigne la culture de cet usage, vide tant que le catalogue ne rattache pas cette culture à des espèces"),
					'items' => ['type' => 'integer', 'format' => 'int64'],
				],
			],
			'work\Action' => [
				'methods' => [
					'type' => 'array',
					'description' => s("Références des modes d'exécution proposés pour cette action"),
					'items' => ['type' => 'integer', 'format' => 'int64'],
				],
			],
		];

	}

}
?>
