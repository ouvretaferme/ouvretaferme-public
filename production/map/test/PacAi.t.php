<?php
namespace map;

/**
 * La lecture d'une réponse du Registre parcellaire graphique (PacLib::parse()) : une parcelle sort en un seul anneau, celui de son
 * plus grand morceau, sans trou ni sommet doublé ni point de fermeture, et seulement des nombres. Les réponses sont écrites ici sur
 * le modèle de celles du WFS de la Géoplateforme : le test ne l'appelle pas. Les libellés des cultures se lisent par
 * PacLib::parseCrops().
 *
 * Aucune écriture, aucune base ouverte.
 *
 * Lancement : dev/test package=map file=PacAi
 */
class PacAiTest extends \Test {

	// Un carré d'environ 78 m sur 111 m, fermé comme le registre ferme ses anneaux
	const array SQUARE = [[3.099, 45.599], [3.1, 45.599], [3.1, 45.6], [3.099, 45.6], [3.099, 45.599]];

	public function testAParcelKeepsItsOuterRingWithoutHole(): void {

		$hole = [[3.0994, 45.5994], [3.0996, 45.5994], [3.0996, 45.5996], [3.0994, 45.5996], [3.0994, 45.5994]];

		$parcels = PacLib::parse($this->getResponse([
			$this->getFeature('7163339', 'Polygon', [self::SQUARE, $hole])
		]));

		$this->assertEquals([['id' => 7163339, 'crop' => 'BTH', 'coordinates' => array_slice(self::SQUARE, 0, 4)]], $parcels, 'l\'anneau extérieur seul, sans le point qui le referme');

	}

	public function testAMultiPolygonKeepsItsLargestPiece(): void {

		$small = [[3.101, 45.599], [3.1012, 45.599], [3.1012, 45.5992], [3.101, 45.5992], [3.101, 45.599]];

		$parcels = PacLib::parse($this->getResponse([
			$this->getFeature('12', 'MultiPolygon', [[$small], [self::SQUARE]])
		]));

		$this->assertEquals([array_slice(self::SQUARE, 0, 4)], array_column($parcels, 'coordinates'), 'le plus grand morceau, quel que soit son rang');

	}

	public function testCloseVerticesMergeIntoOne(): void {

		// Un sommet doublé à 11 cm du coin sud-est, et un dernier sommet à 11 cm du premier, avant le point de fermeture
		$ring = [[3.099, 45.599], [3.1, 45.599], [3.1, 45.599001], [3.1, 45.6], [3.099, 45.6], [3.099, 45.599001], [3.099, 45.599]];

		$parcels = PacLib::parse($this->getResponse([
			$this->getFeature('12', 'Polygon', [$ring])
		]));

		$this->assertEquals([array_slice(self::SQUARE, 0, 4)], array_column($parcels, 'coordinates'), 'les sommets à moins d\'un mètre se fondent dans le sommet gardé avant eux');

	}

	public function testOnlyNumbersComeOut(): void {

		$broken = [[3.099, 45.599], ['<b>', 45.599], [3.1, 45.6], [3.099, 45.6], [3.099, 45.599]];

		$parcels = PacLib::parse($this->getResponse([
			$this->getFeature('12', 'Polygon', [$broken]),
			$this->getFeature('13', 'Point', [3.099, 45.599]),
			$this->getFeature('14', 'Polygon', [array_slice(self::SQUARE, 0, 2)]),
			$this->getFeature('15', 'Polygon', [self::SQUARE])
		]));

		$this->assertEquals([15], array_column($parcels, 'id'), 'un anneau qui porte autre chose qu\'un nombre, une géométrie qui n\'est pas un polygone et un anneau de moins de trois sommets sont écartés');
		$this->assertTrue(is_float($parcels[0]['coordinates'][0][0] ?? NULL), 'les coordonnées sortent en nombres');

	}

	public function testAnUnreadableResponseHasNoParcel(): void {

		$this->assertEquals([], PacLib::parse('<html>Service indisponible</html>'), 'une page d\'erreur ne rend aucune parcelle');
		$this->assertEquals([], PacLib::parse('{"type": "FeatureCollection"}'), 'une réponse sans parcelle ne rend aucune parcelle');

	}

	public function testAParcelKeepsOnlyAVerifiedCropCode(): void {

		$parcels = PacLib::parse($this->getResponse([
			$this->getFeature('1', 'Polygon', [self::SQUARE], ['code_cultu' => 'PPH']),
			$this->getFeature('2', 'Polygon', [self::SQUARE], ['code_cultu' => 'Prairie <b>']),
			$this->getFeature('3', 'Polygon', [self::SQUARE], ['code_cultu' => 12]),
			$this->getFeature('4', 'Polygon', [self::SQUARE], [])
		]));

		$this->assertEquals(['PPH', NULL, NULL, NULL], array_column($parcels, 'crop'), 'le survol ne reçoit de la parcelle qu\'un code de trois lettres ou chiffres, que getAround() change en libellé');

	}

	public function testCropLabelsAreShortened(): void {

		$json = $this->getResponse([
			['type' => 'Feature', 'properties' => ['code_culture' => 'MDI', 'libelle_culture' => 'Maraîchage diversifié (Plusieurs légumes)']],
			['type' => 'Feature', 'properties' => ['code_culture' => 'CAR', 'libelle_culture' => 'Carotte']],
			['type' => 'Feature', 'properties' => ['code_culture' => 12]]
		]);

		$this->assertEquals(['MDI' => 'Maraichage diversifié', 'CAR' => 'Carotte'], PacLib::parseCrops($json), 'le libellé se coupe avant sa précision et s\'écrit sans î, une ligne sans libellé est écartée');
		$this->assertEquals([], PacLib::parseCrops('<html>Service indisponible</html>'), 'une page d\'erreur ne rend aucun libellé');

	}

	private function getFeature(string $number, string $type, array $coordinates, array $properties = ['code_cultu' => 'BTH']): array {

		return [
			'type' => 'Feature',
			'geometry' => ['type' => $type, 'coordinates' => $coordinates],
			'properties' => ['num_parcel' => $number] + $properties
		];

	}

	private function getResponse(array $features): string {
		return json_encode(['type' => 'FeatureCollection', 'features' => $features]);
	}

}
?>
