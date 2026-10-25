<?php
namespace map;

/**
 * Le rang que « Recommencer le dessin » repose (DrawLib::getRespaced()) : la première planche par nom garde son coin et son angle,
 * les suivantes se posent à la suite, séparées par leur passe-pied ou, sans lui, par l'écart médian d'avant le geste. Le geste
 * lui-même (route, saison, planches non dessinées) est éprouvé par DrawConsole.
 *
 * Lancement : dev/test package=map file=DrawRespaceAi
 */
class DrawRespaceAiTest extends \Test {

	const array CORNER = [3.1000, 45.6000];

	// Les planches font 80 cm de large ; la première est posée plein nord, sa largeur partant vers le nord
	private function createDraws(array $beds): \Collection {

		$cDraw = new \Collection();
		$id = 1;

		foreach($beds as $name => [$offset, $alleyWidth, $angle]) {

			$cDraw[$id] = new Draw([
				'bed' => new Bed(['id' => $id, 'name' => $name, 'length' => 20, 'width' => 80, 'alleyWidth' => $alleyWidth]),
				'corner' => MapLib::getDestination(self::CORNER, abs($offset), ($offset >= 0) ? 0 : 180),
				'angle' => $angle
			]);

			$id++;

		}

		return $cDraw;

	}

	// Les planches cochées sont tout le jardin
	private function respace(\Collection $cDraw): \Collection {
		return DrawLib::getRespaced($cDraw, clone $cDraw);
	}

	// La place d'un coin le long de la largeur de la première planche, en mètres vers le nord
	private function getOffsets(\Collection $cDraw): array {

		$offsets = [];

		foreach($cDraw as $eDraw) {
			$distance = MapLib::getDistance(self::CORNER, $eDraw['corner']);
			$north = ($eDraw['corner'][1] >= self::CORNER[1]) ? 1 : -1;
			$offsets[$eDraw['bed']['name']] = round($north * $distance, 2);
		}

		return $offsets;

	}

	public function testBedsAreSpacedByTheirAlley(): void {

		$cDraw = $this->respace($this->createDraws([
			'P3' => [10, 40, 0.0],
			'P1' => [0, 40, 0.0],
			'P2' => [3, 40, 0.3]
		]));

		$this->assertEquals(['P1' => 0.0, 'P2' => 1.2, 'P3' => 2.4], $this->getOffsets($cDraw), 'chaque planche suit la précédente à 80 cm et 40 cm de passe-pied');
		$this->assertEquals([0.0, 0.0, 0.0], array_values($cDraw->makeArray(fn(Draw $eDraw) => $eDraw['angle'])), 'toutes prennent l\'angle de la première');

	}

	public function testOrderIsTheOneOfTheMap(): void {

		$cDraw = $this->respace($this->createDraws([
			'P1' => [0, 20, 0.0],
			'P10' => [2, 20, 0.0],
			'P2' => [4, 20, 0.0]
		]));

		$this->assertEquals(['P1' => 0.0, 'P2' => 1.0, 'P10' => 2.0], $this->getOffsets($cDraw), 'P10 vient après P2');

		// Une comparaison d'octets rangerait « Est 1 » en tête et « École 1 » en dernier, « Serre 10 » devant « serre 2 »
		$cDraw = $this->respace($this->createDraws([
			'Serre 10' => [3, 20, 0.0],
			'École 1' => [0, 20, 0.0],
			'serre 2' => [2, 20, 0.0],
			'Est 1' => [1, 20, 0.0]
		]));

		$this->assertEquals(['École 1' => 0.0, 'Est 1' => 1.0, 'serre 2' => 2.0, 'Serre 10' => 3.0], $this->getOffsets($cDraw), 'ni la casse ni l\'accent');

	}

	public function testBedWithoutAlleyTakesTheMedianGap(): void {

		// Écarts devant les planches sans passe-pied : 0,5 m, 1,5 m puis 0,2 m, dont la médiane est 0,5 m. P5 a le sien, son écart
		// n'y entre pas
		$cDraw = $this->respace($this->createDraws([
			'P1' => [0, NULL, 0.0],
			'P2' => [1.3, NULL, 0.0],
			'P3' => [3.6, NULL, 0.0],
			'P4' => [4.6, NULL, 0.0],
			'P5' => [9, 30, 0.0]
		]));

		$expected = ['P1' => 0.0, 'P2' => 1.3, 'P3' => 2.6, 'P4' => 3.9, 'P5' => 5.0];

		$this->assertEquals($expected, $this->getOffsets($cDraw));
		$this->assertEquals($expected, $this->getOffsets($this->respace($cDraw)), 'un second geste ne bouge plus rien');

	}

	public function testMedianIsTheOneOfTheGarden(): void {

		// Sept planches au pas de 1,5 m, dont les deux dernières dérangées : P6 à 0,9 m de P5, P7 à 1,9 m de P6
		$cDrawPlot = $this->createDraws([
			'P1' => [0, NULL, 0.0],
			'P2' => [1.5, NULL, 0.0],
			'P3' => [3.0, NULL, 0.0],
			'P4' => [4.5, NULL, 0.0],
			'P5' => [6.0, NULL, 0.0],
			'P6' => [7.7, NULL, 0.0],
			'P7' => [10.4, NULL, 0.0]
		]);

		$cDraw = $cDrawPlot->find(fn(Draw $eDraw) => in_array($eDraw['bed']['name'], ['P5', 'P6', 'P7']));

		$this->assertEquals(['P5' => 6.0, 'P6' => 7.5, 'P7' => 9.0], $this->getOffsets(DrawLib::getRespaced($cDraw, $cDrawPlot)), 'les planches non cochées gardent l\'écart d\'origine');

		// Deux écarts seulement, 0,9 m et 1,9 m : la médiane est entre les deux
		$this->assertEquals(['P5' => 6.0, 'P6' => 8.2, 'P7' => 10.4], $this->getOffsets($this->respace($cDraw)), 'ni le plus grand ni le plus petit');

	}

	public function testOverlapIsUndone(): void {

		$cDraw = $this->respace($this->createDraws([
			'P1' => [0, NULL, 0.0],
			'P2' => [0.5, NULL, 0.0]
		]));

		$this->assertEquals(['P1' => 0.0, 'P2' => 0.8], $this->getOffsets($cDraw), 'les planches se touchent');

	}

	public function testRowGoesTheWayTheSecondBedWas(): void {

		$cDraw = $this->respace($this->createDraws([
			'P1' => [0, 40, 0.0],
			'P2' => [-5, 40, 0.0],
			'P3' => [-9, 40, 0.0]
		]));

		// Vers le sud, une planche finit au coin de sa largeur : son coin est 80 cm plus au sud
		$this->assertEquals(['P1' => 0.0, 'P2' => -1.2, 'P3' => -2.4], $this->getOffsets($cDraw));

	}

}
?>
