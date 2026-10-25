<?php
namespace toolbox;

class DevObserverUi {

	public static function apiLabels(): array {

		return [
			'toolbox' => [
				'title' => s('Boite à outils'),
				'description' => s('Les outils de la Boite à outils qui portent des données propres à votre structure. La pluviométrie, avec vos pluviomètres et leurs relevés, le carnet d\'entretien du matériel, avec les matériels suivis, les relevés de leur compteur, leurs entretiens planifiés et leur historique, et la consommation d\'eau, avec vos compteurs d\'eau et leurs relevés. On y trouve aussi le catalogue E-Phy des produits phytopharmaceutiques sur lequel repose le carnet de traitement, qui n\'est la donnée de personne en particulier.')
			],
			'toolbox\Equipment' => [
				'title' => s('Matériels suivis'),
				'plural' => s('matériels suivis'),
				'singular' => s('matériel suivi'),
				'gender' => 'm',
				'description' => s('Les matériels que suit le carnet d\'entretien, avec un compteur d\'heures, de kilomètres, ou sans compteur. Suivre un matériel demande qu\'il existe déjà dans la ressource tool, et il ne se suit qu\'une fois. Il n\'y a pas de suppression. Ne plus suivre un matériel le passe au statut inactive et lui garde ses relevés et son historique. Une modification nomme ce qu\'elle change dans properties. Un corps sans properties est lu comme une modification complète, qui attend toutes les propriétés, status compris, et la seule valeur que status accepte est inactive. Un matériel inactif refuse ensuite toute modification, comme ses relevés, avec un code 412. POST equipment sur le même matériel le reprend avec son identifiant au lieu d\'en créer un second, et répond alors 200 au lieu de 201. Un matériel qui porte des valeurs de compteur ne change plus d\'unité. L\'immatriculation est facultative, tient en 20 caractères et se relit en capitales.')
			],
			'toolbox\EquipmentReading' => [
				'title' => s('Relevés de compteur'),
				'plural' => s('relevés de compteur'),
				'singular' => s('relevé de compteur'),
				'gender' => 'm',
				'description' => s('Ce que le compteur d\'un matériel suivi affiche un jour donné, dans l\'unité du matériel. Il n\'y a qu\'un relevé par matériel et par jour : créer un relevé remplace celui du jour, et répond 200 avec le relevé du jour. Le premier relevé d\'un matériel fait repartir de sa valeur les entretiens réguliers au compteur dont plannedValue lui est inférieur ou égal, qui deviennent dus à cette valeur augmentée de everyValue, pour qu\'ils ne soient pas en retard dès la première saisie. Les relevés suivants ne changent aucune échéance. Un compteur ne recule pas. Un relevé inférieur à un relevé plus ancien, ou supérieur à un relevé plus récent, est refusé. Pour corriger une erreur, le même appel renvoyé avec "confirmed": true dans le corps est accepté. Une modification ne change que la valeur. Les relevés d\'un matériel inactif ne se créent, ne se modifient et ne se suppriment plus, ce que dit un code 412.')
			],
			'toolbox\Maintenance' => [
				'title' => s('Entretiens planifiés'),
				'plural' => s('entretiens planifiés'),
				'singular' => s('entretien planifié'),
				'gender' => 'm',
				'description' => s('Les entretiens à faire sur un matériel suivi, dus à une date, à une valeur du compteur, ou au premier des deux. Un entretien revient régulièrement quand il porte une fréquence, au compteur ou en mois, et se fait une seule fois quand les deux fréquences sont à null. Les valeurs au compteur demandent un matériel à compteur. Il n\'y a pas de suppression, retirer un entretien le passe au statut deleted. Une modification nomme ce qu\'elle change dans properties, les deux fréquences ensemble et les deux échéances ensemble. Un corps sans properties est lu comme une modification complète, qui attend toutes les propriétés, status compris, et la seule valeur que status accepte est deleted. La liste écarte les entretiens retirés, que ?status=deleted retrouve.')
			],
			'toolbox\MaintenanceDone' => [
				'title' => s('Entretiens faits'),
				'plural' => s('entretiens faits'),
				'singular' => s('entretien fait'),
				'gender' => 'm',
				'description' => s('L\'historique d\'un matériel suivi. Une ligne qui nomme un entretien planifié dit qu\'il a été fait ce jour-là. Elle fait repartir son échéance, ou le clôt s\'il se fait une seule fois, et name reste à null. Avec maintenance à null, la ligne est une intervention, qui n\'entre qu\'à l\'historique sous le libellé donné dans name. L\'entretien nommé est un entretien encore actif du matériel donné. À la création, value à null prend le compteur dans les relevés du matériel. Quand le matériel n\'a aucun relevé, la ligne d\'un entretien dû au compteur seul doit donner value, et elle est refusée sans lui. Une valeur donnée devient aussi le relevé de ce jour et suit la règle d\'un relevé. Un compteur ne recule pas, et le même appel renvoyé avec "confirmed": true dans le corps est accepté. Une modification ne change ni le matériel, ni l\'entretien, et value ne s\'y donne pas. Quand la date d\'une ligne change, value est relu dans les relevés du matériel à la nouvelle date, et le relevé de l\'ancien jour reste. Sur un matériel dont le compteur a été retiré, la ligne déplacée garde value et l\'échéance au compteur de l\'entretien reste celle qu\'il portait. Un entretien fait pendant que le compteur est retiré ne fait pas repartir son échéance au compteur. Supprimer la ligne de la dernière fois où l\'entretien a été fait fait repartir son échéance de la fois d\'avant. Quand la ligne supprimée était la seule, l\'échéance d\'un entretien régulier reste celle qui avait été calculée, et se corrige sur l\'entretien par plannedDate et plannedValue, donnés ensemble. Un jour à venir est refusé. Une ligne créée, déplacée ou supprimée avant la dernière fois où l\'entretien a été fait n\'entre qu\'à l\'historique et ne change pas son échéance. Quand l\'échéance repart d\'une ligne, un retard ignoré avant la date de cette ligne est oublié, et ignoredDate revient à null. Un compteur donné sur un matériel sans compteur est refusé, et value est rendu à null quand aucun relevé ne précède la date. Le relevé posé par une ligne reste quand la ligne est supprimée ou change de date. Un entretien à faire une seule fois redevient à faire quand sa ligne est supprimée. Deux créations identiques font deux lignes. Sur la ligne d\'un entretien planifié, name ne se modifie pas et reste à null. Les lignes d\'un matériel inactif ne se modifient et ne se suppriment plus, ce que dit un code 412. La photo d\'un entretien ne passe pas par l\'API.')
			],
			'toolbox\Gauge' => [
				'title' => s('Pluviomètres'),
				'plural' => s('pluviomètres'),
				'singular' => s('pluviomètre'),
				'gender' => 'm',
				'description' => s('Les emplacements où la pluie est relevée : une zone du parcellaire, ou la ferme entière quand la pluie n\'est pas relevée zone par zone. Un lieu ne reçoit qu\'un pluviomètre actif à la fois. Retirer un pluviomètre le passe inactif et lui garde ses relevés, ce qui se fait en modifiant son statut : il n\'y a pas de suppression.')
			],
			'toolbox\Rain' => [
				'title' => s('Relevés de pluie'),
				'plural' => s('relevés de pluie'),
				'singular' => s('relevé de pluie'),
				'gender' => 'm',
				'description' => s('Le total de pluie d\'un jour sur un pluviomètre, en mm. Il n\'y a qu\'un relevé par pluviomètre et par jour. Créer un relevé ajoute au jour au lieu de le remplacer, comme une seconde saisie dans l\'écran : envoyer deux fois 12 mm sur la même date laisse 24 mm. Pour fixer un total, modifiez le relevé du jour. Une quantité négative retire des mm à un jour qui en porte déjà autant, et le jour ramené à zéro perd son relevé. La création rend le jour tel qu\'il est après l\'ajout, avec son total, et non la seule quantité envoyée : elle répond 200, et un jour vidé rend un total de 0 sans identifiant.')
			],
			'toolbox\WaterMeter' => [
				'title' => s('Compteurs d\'eau'),
				'plural' => s('compteurs d\'eau'),
				'singular' => s('compteur d\'eau'),
				'gender' => 'm',
				'description' => s('Les compteurs d\'eau de la structure, chacun avec un nom et le nombre de chiffres qu\'il affiche pour les m³ entiers. Ce nombre de chiffres borne les relevés du compteur, et dit ce qu\'il a compté quand il repasse par zéro. Il ne descend pas sous le plus haut relevé du compteur en place, et un compteur remplacé dans l\'écran garde pour ses anciens relevés le nombre de chiffres qu\'il avait. Deux compteurs actifs ne portent pas le même nom. Il n\'y a pas de suppression. Supprimer un compteur le passe au statut inactive et lui garde ses relevés. Une modification nomme ce qu\'elle change dans properties. Un corps sans properties est lu comme une modification complète, qui attend toutes les propriétés, status compris, et la seule valeur que status accepte est inactive. Un compteur inactif refuse ensuite toute modification, comme ses relevés, avec un code 412.')
			],
			'toolbox\WaterReading' => [
				'title' => s('Relevés de compteur d\'eau'),
				'plural' => s('relevés de compteur d\'eau'),
				'singular' => s('relevé de compteur d\'eau'),
				'gender' => 'm',
				'description' => s('Ce qu\'un compteur d\'eau affiche un jour donné, en m³, au dixième. Un relevé n\'est pas une consommation. La consommation est l\'écart entre deux relevés qui se suivent. Il n\'y a qu\'un relevé par compteur et par jour : créer un relevé remplace celui du jour, et répond 200 avec le relevé du jour. Un relevé plus bas que le précédent est accepté. Il dit que le compteur a fait le tour, et la consommation se compte alors en passant par zéro, avec le nombre de chiffres du compteur. Un relevé reste sous ce que ce nombre de chiffres permet d\'afficher. Un jour à venir est refusé. Quand le compteur a été remplacé, le relevé du jour du changement porte changed, sa valeur est ce que le nouveau compteur affichait à sa pose, d\'où les relevés suivants se comptent, et changedValue ce que l\'ancien affichait à sa dépose, vide s\'il n\'a pas été relevé. La consommation de ce jour-là est l\'écart entre le relevé précédent et changedValue, et vaut 0 sans lui. Ces deux champs se lisent et ne s\'écrivent pas, un changement de compteur se déclare dans l\'écran. Une modification ne change que la valeur. Les relevés d\'un compteur inactif ne se créent, ne se modifient et ne se suppriment plus, ce que dit un code 412.')
			],
		];

	}

	/**
	 * Le lieu d'un pluviomètre, dont une corrélation change la valeur en sortie comme en entrée,
	 * l'échéance d'un entretien, que le carnet calcule (`MaintenanceLib::fill()`), et la confirmation
	 * d'un relevé ou du compteur d'un entretien fait, qui n'est pas une colonne et ne se lit donc que
	 * dans le corps.
	 */
	public static function apiFields(): array {

		return [
			'toolbox\Gauge' => [
				'zone' => [
					'type' => 'integer',
					'format' => 'int64',
					'nullable' => TRUE,
					'description' => s("Référence de la zone du parcellaire où la pluie est relevée, ou null pour le pluviomètre de la ferme entière."),
				],
			],
			'toolbox\EquipmentReading' => [
				'confirmed' => [
					'type' => 'boolean',
					'writeOnly' => TRUE,
					// Le corps d'exemple reprend l'exemple de chaque champ : à true, il confirmerait d'office.
					'example' => FALSE,
					'description' => s("À true, accepte un relevé inférieur à un relevé plus ancien ou supérieur à un relevé plus récent, pour corriger une erreur. Ne se relit pas."),
				],
			],
			'toolbox\MaintenanceDone' => [
				'confirmed' => [
					'type' => 'boolean',
					'writeOnly' => TRUE,
					// Le corps d'exemple reprend l'exemple de chaque champ : à true, il confirmerait d'office.
					'example' => FALSE,
					'gestures' => ['create'],
					'description' => s("À true, accepte un compteur inférieur à un relevé plus ancien ou supérieur à un relevé plus récent, pour corriger une erreur. Ne se relit pas."),
				],
			],
			'toolbox\Maintenance' => [
				'due' => [
					'type' => 'string',
					'enum' => [MaintenanceLib::OVERDUE, MaintenanceLib::SOON],
					'nullable' => TRUE,
					'description' => s("État de l'échéance, tel que le carnet le montre : overdue pour un entretien en retard, soon à 60 jours ou moins, 50 heures ou moins, ou 1 000 kilomètres ou moins de l'échéance, null sinon. Toujours null sur un entretien qui n'est plus actif ou dont le matériel n'est plus suivi."),
				],
				'remainingDays' => [
					'type' => 'integer',
					'nullable' => TRUE,
					'description' => s("Nombre de jours avant plannedDate, négatif une fois la date dépassée. null sans plannedDate, et sur un entretien qui n'est plus actif ou dont le matériel n'est plus suivi."),
				],
				'remainingValue' => [
					'type' => 'integer',
					'nullable' => TRUE,
					'description' => s("Ce qui reste au compteur avant plannedValue, dans l'unité du matériel, négatif une fois la valeur dépassée. null sans plannedValue, sur un matériel sans compteur, tant que le matériel n'a aucun relevé, et sur un entretien qui n'est plus actif ou dont le matériel n'est plus suivi."),
				],
			],
		];

	}

}
?>
