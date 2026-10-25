<?php
namespace structure;

/**
 * Ce que la fiche d'une ferme prépare et que ni un Lib de module ni une page ne portent : les
 * ateliers du rail des membres, et le voisinage.
 */
class ProfileAiLib {

	/**
	 * Charge les ateliers des fermes du rail des membres, en une requête.
	 *
	 * Le rail est indexé par utilisateur et une même ferme y figure sous chacun de ses associés :
	 * les ateliers se posent donc sur chaque occurrence, et non sur la première rencontrée.
	 *
	 * Le tri se fait ferme par ferme et non écran par écran : le périmètre vit sur le
	 * rattachement, donc un même groupe peut lire la production de l'une et pas de l'autre.
	 * Chaque ferme reçoit sa collection, vide pour celle qui n'a pas ouvert sa production —
	 * l'infobulle la lit sans avoir à savoir pourquoi elle est vide.
	 *
	 * ⚠️ Le groupe est demandé parce que le partage porte des publics : ce que le rail montre est
	 * ce que **ce lecteur-là** peut lire, et une ferme qui n'ouvre qu'à l'équipe ne remplit pas
	 * l'infobulle d'un producteur.
	 */
	public static function prepareRailProductions(\Collection $ccStructure, Structure $eStructureCommunity): void {

		$audience = CommunityRightAiLib::getAudience($eStructureCommunity->getMember()['role']);

		$cStructure = new \Collection();

		foreach($ccStructure as $cStructureUser) {
			foreach($cStructureUser as $eStructure) {

				$eStructure['cProduction'] = new \Collection();

				if(CommunityRightAiLib::grants($eStructureCommunity, 'shareProduction', $eStructure['granted']['shareProduction'], $audience, $eStructure['audiencesBringing'])) {
					$cStructure[$eStructure['id']] = $eStructure;
				}

			}
		}

		if($cStructure->empty()) {
			return;
		}

		$ccProduction = ProductionLib::getByStructures($cStructure);

		foreach($ccStructure as $cStructureUser) {
			foreach($cStructureUser as $eStructure) {

				if($ccProduction->offsetExists($eStructure['id'])) {
					$eStructure['cProduction'] = $ccProduction[$eStructure['id']];
				}

			}
		}

	}

	/**
	 * Les fermes proches, nommées par leur commune et jamais par une distance.
	 *
	 * Bornées à ce que ce lecteur voit **partagé**, et non à ce à quoi il a accès : une ferme
	 * dont aucun groupe ne lève `shareProduction` cache sa position sur sa propre fiche, elle
	 * n'a pas à la voir déduite depuis celle d'une autre.
	 *
	 * ⚠️ En PHP, `placeLngLat` rend bien **(longitude, latitude)**, comme son nom l'annonce — mais
	 * aucune des couches traversées ne le dit seule, et deux d'entre elles disent le contraire :
	 * la colonne stocke l'ordre interne du SRID 4326, soit (latitude, longitude) ;
	 * `ST_AsGeoJSON()` seul le rendrait en (longitude, latitude) ; le cadriciel le lit par
	 * `ST_AsGeoJSON(ST_PointFromText(ST_AsText(…)))` (`framework/core/Module.php:4911`), dont
	 * l'aller-retour repasse par l'ordre interne, puis `MySQLApi::decodePoint()`
	 * (`framework/core/Database/drivers/MySQL/MySQLApi.php:50-52`) l'inverse une dernière fois.
	 * Seule la distance rendue tranche : inverser donne 72,7 km entre Tallende et Beaulieu là où
	 * la bonne lecture donne 53,5 km, sans la moindre erreur. Le test le fixe.
	 */
	/**
	 * Les quatre valeurs du système d'une ferme : surface, altitude, climat, vente directe.
	 *
	 * ⚠️ **Une seule définition pour deux écrans** — la scène de la fiche et la carte de ferme
	 * école. Elles les calculaient chacune de leur côté et avaient déjà divergé : la carte
	 * rangeait le climat sous l'altitude et le détail des débouchés sur une ligne, là où la fiche
	 * en faisait une valeur à part et deux lignes. « Le même affichage » ne s'obtient pas en
	 * réalignant deux calculs, il s'obtient en n'en ayant qu'un.
	 *
	 * ⚠️ Ici et non dans une UI : la carte est rendue en boucle, et deux requêtes par ferme dans
	 * une méthode d'affichage se paient au nombre de fermes.
	 *
	 * Chaque entrée rend `[libellé, valeur, précision, la valeur est-elle un mot]`, et une valeur
	 * absente rend NULL — c'est l'appelant qui les écarte, pour que les présentes se rangent à
	 * gauche. Le vocabulaire vient du describer et ne s'invente pas : « Vente directe », et non
	 * « circuit court ».
	 */
	public static function getSystemFigures(\structure\System $eSystem, \Collection $cProduction): array {

		if($eSystem->empty()) {
			return [];
		}

		$productions = $cProduction->count();

		$outlets = [];

		foreach(['sellingSemi', 'sellingLong'] as $property) {

			if(($eSystem[$property] ?? 0) > 0) {
				$outlets[] = s("{value} % {label}", ['value' => $eSystem[$property], 'label' => mb_strtolower(\structure\SystemUi::p($property)->label)]);
			}

		}

		return [
			($eSystem['totalArea'] === NULL) ? NULL : [
				s("Surface"), $eSystem['totalArea'].' '.s("ha"),
				($productions === 0) ? '' : $productions.' '.p("atelier", "ateliers", $productions),
				FALSE
			],
			// Zéro est une non-saisie, pas une mesure : les 8 systèmes du dépôt qui portent 0
			// sont tous à l'intérieur des terres — Lathus, Sèvres-Anxaumont, Essay, Liège —, où
			// l'altitude nulle n'existe pas. Affichée, elle se lit comme un relevé.
			(($eSystem['altitude'] ?? 0) === 0) ? NULL : [
				s("Altitude"), $eSystem['altitude'].' '.s("m"), '', FALSE
			],
			($eSystem['climate'] === NULL) ? NULL : [
				s("Climat"), \structure\SystemUi::p('climate')->values[$eSystem['climate']], '', TRUE
			],
			(($eSystem['sellingDirect'] ?? 0) === 0) ? NULL : [
				\structure\SystemUi::p('sellingDirect')->label, s("{value} %", $eSystem['sellingDirect']),
				// Une ligne par débouché : les deux bout à bout formaient une phrase longue et
				// grise sous un chiffre, où l'on ne distinguait plus les deux valeurs.
				implode('<br/>', $outlets),
				FALSE
			]
		];

	}

	public static function getNeighbours(Structure $eStructure, System $eSystem, \user\User $eUser, int $limit = 6): \Collection {

		if($eSystem->empty() or $eSystem['placeLngLat'] === NULL) {
			return new \Collection();
		}

		// ⚠️ Une ferme école n'a pas de voisins, et ce n'est pas une question de goût : depuis
		// qu'elle porte le centre de son département, la recherche par distance trouvait **sa
		// propre source** à 10 km et la nommait sur sa fiche. Toute l'anonymisation tombe par ce
		// chemin — le nom, la commune et le logo de la ferme réelle, affichés sur la copie qui
		// existe pour les cacher.
		if($eStructure->isSchool()) {
			return new \Collection();
		}

		$cStructureShared = AffiliationAiLib::getSharedStructuresByUser($eUser);

		unset($cStructureShared[$eStructure['id']]);

		if($cStructureShared->empty()) {
			return new \Collection();
		}

		$cSystem = System::model()
			->select(['structure', 'place', 'placeLngLat'])
			->whereStructure('IN', $cStructureShared)
			->getCollection(index: 'structure');

		[$latitude, $longitude] = self::getCoordinates($eSystem);

		$cStructureNeighbour = new \Collection();

		foreach($cStructureShared as $eStructureNeighbour) {

			$eSystemNeighbour = $cSystem[$eStructureNeighbour['id']] ?? new System();

			if($eSystemNeighbour->empty() or $eSystemNeighbour['placeLngLat'] === NULL) {
				continue;
			}

			[$latitudeNeighbour, $longitudeNeighbour] = self::getCoordinates($eSystemNeighbour);

			$eStructureNeighbour['place'] = $eSystemNeighbour['place'];
			$eStructureNeighbour['distance'] = self::getDistance($latitude, $longitude, $latitudeNeighbour, $longitudeNeighbour);

			$cStructureNeighbour[$eStructureNeighbour['id']] = $eStructureNeighbour;

		}

		// Le nom départage les distances égales : ce sont des géocodages à la commune, deux
		// fermes du même village sont à zéro l'une de l'autre et l'ordre serait sinon celui de
		// la base, différent d'un rendu à l'autre.
		$cStructureNeighbour->sort([
			'distance' => SORT_ASC,
			'name' => SORT_ASC
		]);

		// Collection::slice() cherche aveuglément `length` positions et lève au-delà de la
		// dernière : la borner au nombre d'éléments est à la charge de l'appelant. Les clés se
		// préservent, la collection est indexée par ferme.
		if($cStructureNeighbour->count() > $limit) {
			$cStructureNeighbour = $cStructureNeighbour->slice(0, $limit, preserveKeys: TRUE);
		}

		return $cStructureNeighbour;

	}

	private static function getCoordinates(System $eSystem): array {

		[$longitude, $latitude] = $eSystem['placeLngLat'];

		return [(float)$latitude, (float)$longitude];

	}

	/** Haversine, en mètres. */
	private static function getDistance(float $latitude1, float $longitude1, float $latitude2, float $longitude2): float {

		$radius = 6371000;

		$deltaLatitude = deg2rad($latitude2 - $latitude1);
		$deltaLongitude = deg2rad($longitude2 - $longitude1);

		$a = sin($deltaLatitude / 2) ** 2
			+ cos(deg2rad($latitude1)) * cos(deg2rad($latitude2)) * sin($deltaLongitude / 2) ** 2;

		return $radius * 2 * atan2(sqrt($a), sqrt(1 - $a));

	}

}
?>
