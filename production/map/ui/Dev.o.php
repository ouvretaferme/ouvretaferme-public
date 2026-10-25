<?php
namespace map;

class DevObserverUi {

	public static function apiLabels(): array {

		return [
			'map' => [
				'title' => s('Plan de la ferme'),
				'description' => s('Le plan de votre ferme, du plus large au plus fin : les parcelles, les jardins qui les découpent et les planches de culture.')
			],
			'map\Zone' => [
				'title' => s('Parcelles'),
				'plural' => s('parcelles'),
				'singular' => s('parcelle'),
				'gender' => 'f',
				'description' => s('Les parcelles qui composent le plan, chacune délimitée par un polygone et découpée en jardins. Une parcelle dessinée a la surface de son polygone, qui se modifie donc avec lui.')
			],
			'map\Plot' => [
				'title' => s('Jardins'),
				'plural' => s('jardins'),
				'singular' => s('jardin'),
				'gender' => 'm',
				'description' => s('Les jardins d\'une parcelle, en plein champ ou couverts par un abri. Un jardin couvert porte les dimensions de son abri, dont se déduit sa surface. Dessiné dans une parcelle elle-même dessinée, il tire ses dimensions de son polygone, qui se modifie seul et les entraîne, et celles qu\'on lui envoie sont remplacées. Effacer ce polygone demande d\'envoyer aussi les dimensions de l\'abri. Un nouveau polygone trop étroit pour un abri est refusé. Un jardin en plein champ dessiné a la surface de son polygone, qui se modifie donc avec lui. Le jardin qui porte les planches posées à même la parcelle se lit, mais ne se modifie ni ne se supprime : il suit sa parcelle. Un jardin ne change pas de parcelle : celle-ci se pose à la création. Couvrir ou découvrir un jardin ne se fait pas par l\'API.')
			],
			'map\Bed' => [
				'title' => s('Planches'),
				'plural' => s('planches'),
				'singular' => s('planche'),
				'gender' => 'f',
				'description' => s('Les planches de culture d\'un jardin, avec leurs dimensions. Les planches de remplissage, créées par le produit pour porter une culture posée sur un jardin entier, n\'apparaissent pas ici.')
			]
		];

	}

	/**
	 * Le contour d'un jardin ou d'une parcelle. La colonne est un polygon, que le framework rend
	 * décodé : sans cette déclaration le schéma annoncerait la chaîne stockée et non la liste de
	 * points effectivement échangée, dans un sens comme dans l'autre.
	 */
	public static function apiFields(): array {

		$coordinates = [
			'type' => 'array',
			'nullable' => TRUE,
			'description' => s("Contour, en points [longitude, latitude]"),
			'items' => [
				'type' => 'array',
				'minItems' => 2,
				'maxItems' => 2,
				'items' => ['type' => 'number'],
			],
		];

		return [
			'map\Zone' => ['coordinates' => $coordinates],
			'map\Plot' => ['coordinates' => $coordinates],
		];

	}

}
?>
