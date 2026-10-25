<?php
namespace map;

/**
 * Les dimensions qu'un abri tracé tire de son contour (MapLib::getDimensions()) : un rectangle se mesure sur ses côtés, un autre
 * contour sur le plus petit rectangle qui l'englobe. Le navigateur fait le même calcul (MapDrawingPolygon.getDimensions()), dont
 * les valeurs ont été relevées dans Chrome sur les mêmes points.
 *
 * Lancement : dev/test package=map file=DimensionsAi
 */
class DimensionsAiTest extends \Test {

	const array RECTANGLE = [[3.1000, 45.6000], [3.1003, 45.6000], [3.1003, 45.6001], [3.1000, 45.6001]];
	const array TRIANGLE = [[3.128768, 45.664010], [3.128903, 45.663964], [3.129082, 45.663926]];
	const array L_SHAPE = [[3.1000, 45.6000], [3.1004, 45.6000], [3.1004, 45.6001], [3.1001, 45.6001], [3.1001, 45.6003], [3.1000, 45.6003]];
	const array PARALLELOGRAM = [[3.1000, 45.6000], [3.1003, 45.6000], [3.10035, 45.6001], [3.10005, 45.6001]];

	public function testRectangleIsMeasuredOnItsSides(): void {

		$this->assertEquals([23.3, 11.1], MapLib::getDimensions(self::RECTANGLE), 'la longueur d\'abord');
		$this->assertEquals([23.3, 11.1], MapLib::getDimensions([...self::RECTANGLE, self::RECTANGLE[0]]), 'la base referme l\'anneau');
		$this->assertEquals([23.3, 11.1], MapLib::getDimensions(array_reverse(self::RECTANGLE)), 'dans un sens comme dans l\'autre');

	}

	/** Un abri tracé de biais garde ses dimensions : le rectangle tourné reste un rectangle dans le plan local */
	public function testTurnedRectangleKeepsItsDimensions(): void {

		$turned = MapLib::getTransformed(self::RECTANGLE, MapLib::getOrigin(self::RECTANGLE), [0, 0], 30);

		$this->assertEquals([23.3, 11.1], MapLib::getDimensions($turned));

	}

	public function testOtherShapeIsMeasuredOnItsBoundingRectangle(): void {

		$this->assertEquals([26.2, 1.0], MapLib::getDimensions(self::TRIANGLE), 'un triangle se couche sur son plus long côté');
		$this->assertEquals([33.4, 31.2], MapLib::getDimensions(self::L_SHAPE), 'un L prend le rectangle de ses deux branches');
		$this->assertEquals([27.3, 11.1], MapLib::getDimensions(self::PARALLELOGRAM), 'quatre sommets sans angle droit ne font pas un rectangle');

	}

	/** Le panneau d'un abri tracé dans une parcelle non tracée n'a pas de carte, donc pas de contour à envoyer : il offre la saisie */
	public function testUndrawnZoneOffersTypedDimensions(): void {

		$this->assertEquals(MapLib::getDimensions(self::RECTANGLE), PlotLib::getDimensionsDrawn(new Plot([
			'coordinates' => self::RECTANGLE,
			'length' => NULL,
			'width' => NULL,
			'zone' => new Zone(['coordinates' => self::L_SHAPE])
		])), 'parcelle tracée');

		$this->assertEquals(NULL, PlotLib::getDimensionsDrawn(new Plot([
			'coordinates' => self::RECTANGLE,
			'length' => NULL,
			'width' => NULL,
			'zone' => new Zone(['coordinates' => NULL])
		])), 'parcelle non tracée');

	}

}
?>
