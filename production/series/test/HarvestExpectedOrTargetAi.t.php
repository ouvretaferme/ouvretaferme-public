<?php
namespace series;

/**
 * La récolte prévue d'une culture, `Cultivation::getHarvestExpectedOrTarget()` : rendement prévu × surface
 * de la série, dans l'unité principale de la culture. La surface réelle passe avant la surface
 * visée, et une donnée manquante rend NULL et non 0 : un 0 se confondrait avec une récolte prévue
 * nulle, et les paniers (`toolbox`) ne sauraient plus dire « à vérifier ».
 *
 * Le Prévisionnel (`plant\ForecastLib::getByStructure()`) et les paniers de l'année la lisent tous
 * deux.
 *
 * Lancement : dev/test package=series file=HarvestExpectedAi
 */
class HarvestExpectedOrTargetAiTest extends \Test {

	private function getCultivation(?float $yieldExpected, ?int $area, ?int $areaTarget): Cultivation {

		return new Cultivation([
			'yieldExpected' => $yieldExpected,
			'series' => new Series([
				'area' => $area,
				'areaTarget' => $areaTarget,
			]),
		]);

	}

	public function testArea(): void {

		$this->assertEquals(300.0, $this->getCultivation(2.5, 120, 80)->getHarvestExpectedOrTarget(), 'la surface réelle ne passe pas avant la surface visée');

	}

	public function testAreaTarget(): void {

		$this->assertEquals(200.0, $this->getCultivation(2.5, NULL, 80)->getHarvestExpectedOrTarget(), 'sans surface réelle, la surface visée n\'est pas lue');

	}

	public function testMissing(): void {

		$this->assertEquals(NULL, $this->getCultivation(NULL, 120, 80)->getHarvestExpectedOrTarget(), 'une culture sans rendement prévu rend une récolte');
		$this->assertEquals(NULL, $this->getCultivation(2.5, NULL, NULL)->getHarvestExpectedOrTarget(), 'une série sans surface rend une récolte');

	}

	/**
	 * Un rendement prévu à zéro est une prévision, pas une donnée manquante.
	 */
	public function testZero(): void {

		$this->assertEquals(0.0, $this->getCultivation(0.0, 120, 80)->getHarvestExpectedOrTarget(), 'un rendement prévu nul ne rend pas une récolte nulle');

	}

}
