<?php
namespace map;

/**
 * Une planche se pose dans le contour où son rang se dessine (`Draw`, étape `inside` de `angle`) : celui du jardin, ou celui de la
 * parcelle quand le jardin la remplit. Le contour se juge comme le navigateur le juge (`MapDrawingLine.isInside()`), chaque coin
 * rentré d'un cinquantième vers le milieu, pour qu'une planche posée contre un bord reste dedans.
 *
 * Aucune écriture, aucune base ouverte.
 *
 * Lancement : dev/test package=map file=DrawInsideAi
 */
class DrawInsideAiTest extends \Test {

	const array ZONE = [[3.0990, 45.5990], [3.1010, 45.5990], [3.1010, 45.6010], [3.0990, 45.6010], [3.0990, 45.5990]];

	const array GARDEN = [[3.0995, 45.5995], [3.1005, 45.5995], [3.1005, 45.6005], [3.0995, 45.6005], [3.0995, 45.5995]];

	// Un jardin en L : le quart nord-est lui manque
	const array GARDEN_L = [[3.0995, 45.5995], [3.1005, 45.5995], [3.1005, 45.6000], [3.1000, 45.6000], [3.1000, 45.6005], [3.0995, 45.6005], [3.0995, 45.5995]];

	// Le travers vers l'est, la longueur vers le sud
	const float EAST = M_PI / 2;

	private function getPlot(array $coordinates, bool $zoneFill = FALSE): Plot {

		return new Plot([
			'zoneFill' => $zoneFill,
			'coordinates' => $zoneFill ? NULL : $coordinates,
			'zone' => new Zone(['coordinates' => self::ZONE])
		]);

	}

	/* La planche de 20 m sur 80 cm dont le coin est posté, refusée ou non par son contour */
	private function getFails(Plot $ePlot, array $corner, int $length = 20): array {

		$fw = new \FailWatch();

		$eDraw = new Draw([
			'bed' => new Bed(['id' => 1, 'length' => $length, 'width' => 80]),
			'plot' => $ePlot
		]);

		$eDraw->build(['corner', 'angle'], [
			'corner' => json_encode($corner),
			'angle' => (string)self::EAST
		]);

		return array_map(fn(array $fail) => $fail[1], $fw->get());

	}

	/* La latitude à `$meters` au nord du bord sud du jardin */
	private function getNorth(float $meters): float {
		return MapLib::getDestination([3.0996, 45.5995], $meters, 0)[1];
	}

	public function testBedInsideItsGardenIsAccepted(): void {

		$this->assertEquals([], $this->getFails($this->getPlot(self::GARDEN), [3.0996, 45.6004]), 'une planche posée au milieu du jardin');
		$this->assertEquals([], $this->getFails($this->getPlot(self::GARDEN), [3.0996, $this->getNorth(21)]), 'une planche qui finit à 1 m du bord');

	}

	public function testBedOutOfItsGardenIsRefused(): void {

		$this->assertEquals(['Draw::angle.inside'], $this->getFails($this->getPlot(self::GARDEN), [3.0996, $this->getNorth(19)]), 'une planche qui dépasse le bord sud de 1 m');
		$this->assertEquals(['Draw::angle.inside'], $this->getFails($this->getPlot(self::GARDEN), [3.0980, 45.6004]), 'une planche posée hors du jardin');

	}

	public function testBedAgainstTheBorderIsAccepted(): void {

		$this->assertEquals([], $this->getFails($this->getPlot(self::GARDEN), [3.0995, 45.6005]), 'une planche dans le coin nord-ouest, deux côtés sur le contour');
		$this->assertEquals([], $this->getFails($this->getPlot(self::GARDEN), [3.0996, $this->getNorth(20)]), 'une planche qui finit sur le bord sud');

	}

	public function testBedOnTheZoneIsJudgedByTheZone(): void {

		$this->assertEquals([], $this->getFails($this->getPlot(self::GARDEN, zoneFill: TRUE), [3.0991, 45.6008]), 'hors du jardin mais dans la parcelle que le jardin remplit');
		$this->assertEquals(['Draw::angle.inside'], $this->getFails($this->getPlot(self::GARDEN, zoneFill: TRUE), [3.0985, 45.6008]), 'hors de la parcelle');

	}

	public function testBedInTheNotchOfAGardenIsRefused(): void {

		$this->assertEquals([], $this->getFails($this->getPlot(self::GARDEN_L), [3.0996, 45.6004]), 'dans la branche nord du L');
		$this->assertEquals(['Draw::angle.inside'], $this->getFails($this->getPlot(self::GARDEN_L), [3.1002, 45.6004]), 'dans le quart qui manque au L');

	}

	public function testBedWithoutSizeIsLeftToItsOwnRefusal(): void {

		$fw = new \FailWatch();

		$eDraw = new Draw([
			'bed' => new Bed(['id' => 1, 'length' => NULL, 'width' => 80]),
			'plot' => $this->getPlot(self::GARDEN)
		]);

		$eDraw->build(['corner', 'angle'], ['corner' => json_encode([3.0980, 45.6004]), 'angle' => (string)self::EAST]);

		$this->assertEquals([], $fw->get(), 'une planche sans longueur n\'a pas de rectangle, Draw::bed.sized la refuse à l\'écriture');

	}

}
?>
