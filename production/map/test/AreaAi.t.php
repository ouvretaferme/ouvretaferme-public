<?php
namespace map;

/**
 * La surface que le serveur tire d'un contour (MapLib::getArea()) est celle que le navigateur affiche pendant le tracé. Les
 * valeurs attendues sont celles de `turf.area()` 7.2.0 (`static/lib/turf-7.2.0.min.js`) sur les mêmes points, mesurées dans
 * Chrome le 2026-10-17, arrondies comme le fait `MapDrawingPolygon.getArea()`.
 *
 * Lancement : dev/test package=map file=AreaAi
 */
class AreaAiTest extends \Test {

	const array SQUARE = [[3.0990, 45.5990], [3.1010, 45.5990], [3.1010, 45.6010], [3.0990, 45.6010]];
	const array RECTANGLE = [[3.1000, 45.6000], [3.1003, 45.6000], [3.1003, 45.6001], [3.1000, 45.6001]];
	const array TRIANGLE = [[3.128768, 45.664010], [3.128903, 45.663964], [3.129082, 45.663926]];
	const array PARIS = [[2.30, 48.80], [2.40, 48.80], [2.40, 48.90], [2.30, 48.90]];

	private function getPolygon(): array {

		return array_map(fn(int $i) => [
			3 + cos(2 * M_PI * $i / MapSetting::SHAPE_POINTS_MAX) / 1000,
			45 + sin(2 * M_PI * $i / MapSetting::SHAPE_POINTS_MAX) / 1000
		], range(1, MapSetting::SHAPE_POINTS_MAX));

	}

	public function testAreaIsTurfArea(): void {

		$this->assertEquals(34604, MapLib::getArea(self::SQUARE), 'un carré de parcelle');
		$this->assertEquals(260, MapLib::getArea(self::RECTANGLE), 'un rectangle de jardin');
		$this->assertEquals(13, MapLib::getArea(self::TRIANGLE), 'un triangle de quelques mètres');
		$this->assertEquals(81361417, MapLib::getArea(self::PARIS), 'un contour de 8 136 ha, où les écarts d\'arrondi s\'additionneraient');
		$this->assertEquals(27466, MapLib::getArea($this->getPolygon()), 'autant de sommets que le plafond');

	}

	/** La base referme l'anneau et le navigateur poste ses points ouverts : les deux formes ont la même surface. */
	public function testClosedRingHasTheSameArea(): void {

		$this->assertEquals(MapLib::getArea(self::SQUARE), MapLib::getArea([...self::SQUARE, self::SQUARE[0]]));
		$this->assertEquals(MapLib::getArea(self::TRIANGLE), MapLib::getArea([...self::TRIANGLE, self::TRIANGLE[0]]));

	}

	/** Le sens du tracé ne compte pas. */
	public function testDirectionDoesNotMatter(): void {

		$this->assertEquals(MapLib::getArea(self::SQUARE), MapLib::getArea(array_reverse(self::SQUARE)));

	}

}
?>
