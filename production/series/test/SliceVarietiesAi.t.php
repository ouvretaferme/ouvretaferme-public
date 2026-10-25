<?php
namespace series;

/**
 * Les variétés du champ Variété quand plusieurs séries sont choisies (`SliceLib::getVarietiesByCultivations()`,
 * lot EI du chantier planning) : le champ montre l'union des variétés de leurs tranches, et seules celles que
 * toutes les cultures cultivent restent choisissables, `Task::variety` refusant les autres sur la culture qui ne
 * les a pas. Le champ est celui de « Nouvelle intervention » du planning et de l'intervention sur plusieurs séries
 * de l'ancien planning.
 *
 * Avant le lot, une culture sans tranche n'entrait pas dans l'intersection : une courge butternut et une courge
 * tardive sans tranche choisies ensemble laissaient Butternut choisissable, refusée ensuite à l'envoi.
 *
 * Lancement : dev/test package=series file=SliceVarietiesAi
 */
class SliceVarietiesAiTest extends SeriesFixtureAiTest {

	public function testCultivationWithoutSlice(): void {

		$eCultivation = $this->getCultivation();
		$eVariety = $this->createVariety();
		$eVarietyOther = $this->createVariety();
		$this->createSlice($eCultivation, $eVariety, 50.0);
		$this->createSlice($eCultivation, $eVarietyOther, 50.0);

		$eCultivationEmpty = $this->createCultivation($this->createSeries('Série sans tranche'));

		$this->assertEquals(
			[[$eVariety['id'], $eVarietyOther['id']], []],
			$this->read(new \Collection([$eCultivation, $eCultivationEmpty])),
			'une culture sans tranche laisse choisir les variétés de l\'autre culture'
		);

		$this->assertEquals(
			[[], []],
			$this->read(new \Collection([$eCultivationEmpty])),
			'une culture sans tranche propose une variété'
		);

	}

	public function testIntersect(): void {

		$eCultivation = $this->getCultivation();
		$eVariety = $this->createVariety();
		$eVarietyOther = $this->createVariety();
		$this->createSlice($eCultivation, $eVariety, 50.0);
		$this->createSlice($eCultivation, $eVarietyOther, 50.0);

		$this->assertEquals(
			[[$eVariety['id'], $eVarietyOther['id']], [$eVariety['id'], $eVarietyOther['id']]],
			$this->read(new \Collection([$eCultivation])),
			'une culture seule ne laisse pas choisir toutes ses variétés'
		);

		$eCultivationNext = $this->createCultivation($this->createSeries('Série d\'une seule variété'));
		$this->createSlice($eCultivationNext, $eVariety);

		$this->assertEquals(
			[[$eVariety['id'], $eVarietyOther['id']], [$eVariety['id']]],
			$this->read(new \Collection([$eCultivation, $eCultivationNext])),
			'deux cultures ne laissent pas choisir leur seule variété commune parmi l\'union de leurs variétés'
		);

	}

	/**
	 * Les variétés proposées, puis celles qui restent choisissables, triées.
	 */
	private function read(\Collection $cCultivation): array {

		['cVariety' => $cVariety, 'varietiesIntersect' => $varietiesIntersect] = SliceLib::getVarietiesByCultivations($cCultivation);

		$varieties = $cVariety->getIds();

		sort($varieties);
		sort($varietiesIntersect);

		return [$varieties, $varietiesIntersect];

	}

}
?>
