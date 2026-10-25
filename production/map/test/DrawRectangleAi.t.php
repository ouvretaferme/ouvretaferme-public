<?php
namespace map;

/**
 * La place que le navigateur poste pour chaque planche d'un rang (`MapDrawingLine.getBody()`, `getCanonical()`) recouvre la
 * planche que `MapCartography.getBeds()` dessine, au centimètre près, sous node : le serveur l'écrit telle quelle, donc une
 * place prise au mauvais bout déplacerait la planche de sa largeur. Un rang de biais garde le coin et le cap de la largeur, et
 * l'angle posté reste entre 0 et π, que `build()` exige.
 *
 * Aucune écriture, aucune base ouverte.
 *
 * Lancement : dev/test package=map file=DrawRectangleAi
 */
class DrawRectangleAiTest extends DrawingBenchAiTest {

	const array ORIGIN = [3.1, 45.6];

	public function testPostedPlacesCoverTheMap(): void {

		$beds = [
			['id' => 1, 'name' => '1', 'width' => 75, 'length' => 30],
			['id' => 2, 'name' => '2', 'width' => 120, 'length' => 30],
			['id' => 3, 'name' => '10', 'width' => 75, 'length' => 25],
			['id' => 4, 'name' => '3', 'width' => 100, 'length' => 30]
		];

		$cases = [
			'vers l\'est, longueur au sud' => [90, 180],
			'vers l\'est, longueur au nord' => [90, 0],
			'vers l\'ouest, longueur au nord' => [-90, 0],
			'vers l\'ouest, longueur au sud' => [-90, 180],
			'vers le sud-ouest, longueur au nord-ouest' => [-135, -45],
			'vers le nord-est, longueur au sud-est' => [45, 135],
			'vers le nord, longueur à l\'est' => [0, 90]
		];

		foreach($cases as $case => [$lineBearing, $directionBearing]) {
			$this->assertRowMatches($case, $beds, $this->getLine(9, $lineBearing, $directionBearing));
		}

		$this->assertRowMatches('une seule planche', [['id' => 1, 'name' => 'A', 'width' => 80, 'length' => 12]], $this->getLine(0.8, 30, 120));

	}

	/**
	 * Des planches à créer n'ont pas d'id : chacune part sous sa position dans le rang, dans le seul champ que le formulaire du
	 * panneau leur réserve, et c'est sous cette position que `map/bed:doCreate` la lit.
	 */
	public function testCreatedBedsArePostedByPosition(): void {

		$beds = [
			['id' => 0, 'name' => 'P1', 'width' => 110, 'length' => 20],
			['id' => 1, 'name' => 'P2', 'width' => 110, 'length' => 20],
			['id' => 2, 'name' => 'P3', 'width' => 110, 'length' => 20]
		];

		$coordinates = $this->getLine(5, 90, 0);

		$result = $this->run(1512, '
			const beds = '.json_encode($beds).';
			const rows = MapCartography.getBeds(beds, '.json_encode($coordinates).');
			result.polygons = rows.polygons.map(polygon => polygon.geometry.coordinates[0].slice(0, 4));
			result.body = MapDrawingLine.prototype.getBody.call({create: true, beds: beds, getRows: () => rows});
		');

		if($this->assertDecorPresent(isset($result['body']['draw']), 'le navigateur rend le champ du rang à créer') === FALSE) {
			return;
		}

		$this->assertEquals(['draw'], array_keys($result['body']), 'le rang à créer ne part que dans son champ');

		$input = json_decode($result['body']['draw'], TRUE);

		$this->assertEquals([0, 1, 2], array_keys($input['corner']), 'un coin par position');
		$this->assertEquals([0, 1, 2], array_keys($input['angle']), 'un angle par position');

		foreach($beds as $position => $bed) {

			// Un `point` part en JSON, comme le cadriciel le reçoit de tout formulaire
			$place = [
				'corner' => json_decode($input['corner'][$position], TRUE),
				'angle' => $input['angle'][$position]
			];

			$this->assertTrue($this->getGap($result['polygons'][$position], $this->getRectangle($place, $bed)) < 0.01, 'la place postée sous la position '.$position.' recouvre la planche '.$bed['name']);

		}

	}

	public function testSlantedRowKeepsItsWidth(): void {

		$beds = [
			['id' => 1, 'name' => '1', 'width' => 100, 'length' => 20],
			['id' => 2, 'name' => '2', 'width' => 100, 'length' => 20]
		];

		// La longueur part à 80° de la largeur au lieu de 90° : la carte dessine des parallélogrammes
		$coordinates = $this->getLine(3, 90, 170);

		[$polygons, $places] = $this->getPlaces($beds, $coordinates);

		foreach($beds as $index => $bed) {

			$polygon = $polygons[$index];
			$rectangle = $this->getRectangle($places[$index], $bed);

			$this->assertTrue($this->getGap([$polygon[0], $polygon[1]], [$rectangle[0], $rectangle[1]]) < 0.01, 'la largeur de la planche '.$bed['name'].' reste en place');
			$this->assertTrue(abs(MapLib::getDistance($rectangle[0], $rectangle[3]) - $bed['length']) < 0.01, 'sa longueur reste la sienne');
			$this->assertTrue(abs(fmod(MapLib::getBearing($rectangle[0], $rectangle[3]) - MapLib::getBearing($rectangle[0], $rectangle[1]) + 360, 360) - 90) < 0.01, 'et part d\'équerre');

		}

	}

	private function assertRowMatches(string $case, array $beds, array $coordinates): void {

		[$polygons, $places] = $this->getPlaces($beds, $coordinates);

		foreach($beds as $index => $bed) {

			$place = $places[$index] ?? NULL;

			if($this->assertDecorPresent($place !== NULL, $case.' : le navigateur rend la place de la planche '.$bed['name']) === FALSE) {
				return;
			}

			$this->assertTrue($place['angle'] >= 0 and $place['angle'] < M_PI, $case.' : l\'angle posté pour la planche '.$bed['name'].' est entre 0 et π');
			$this->assertTrue($this->getGap($polygons[$index], $this->getRectangle($place, $bed)) < 0.01, $case.' : la place postée pour la planche '.$bed['name'].' recouvre celle de la carte');

		}

	}

	/* Un trait de `$width` mètres partant de l'origine au cap `$lineBearing`, et son troisième point à 10 mètres au cap `$directionBearing` */
	private function getLine(float $width, float $lineBearing, float $directionBearing): array {

		$second = MapLib::getDestination(self::ORIGIN, $width, $lineBearing);

		return [self::ORIGIN, $second, MapLib::getDestination($second, 10, $directionBearing)];

	}

	/* Les quatre coins des planches que dessine la carte, et la place que le navigateur poste pour chacune, dans l'ordre des planches */
	private function getPlaces(array $beds, array $coordinates): array {

		$result = $this->run(1512, '
			const polygons = MapCartography.getBeds('.json_encode($beds).', '.json_encode($coordinates).').polygons;
			result.polygons = polygons.map(polygon => polygon.geometry.coordinates[0].slice(0, 4));
			result.places = polygons.map(polygon => MapDrawingLine.getCanonical(polygon));
		');

		return [$result['polygons'] ?? [], $result['places'] ?? []];

	}

	/* Les quatre coins d'un rectangle, depuis son coin : largeur à l'angle, longueur à l'angle + 90° */
	private function getRectangle(array $rectangle, array $bed): array {

		$angle = rad2deg($rectangle['angle']);
		$corner = $rectangle['corner'];
		$side = MapLib::getDestination($corner, $bed['width'] / 100, $angle);

		return [
			$corner,
			$side,
			MapLib::getDestination($side, $bed['length'], $angle + 90),
			MapLib::getDestination($corner, $bed['length'], $angle + 90)
		];

	}

	/* Le plus grand écart, en mètres, entre chaque coin d'une forme et le coin le plus proche de l'autre : deux formes de mêmes coins, dans n'importe quel ordre, sont à zéro */
	private function getGap(array $points, array $others): float {

		$gap = 0;

		foreach($points as $point) {
			$gap = max($gap, min(array_map(fn(array $other) => MapLib::getDistance($point, $other), $others)));
		}

		return $gap;

	}

}
?>
