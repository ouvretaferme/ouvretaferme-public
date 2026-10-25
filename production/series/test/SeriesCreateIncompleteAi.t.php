<?php
namespace series;

/**
 * Une création de série dont le POST ne porte que l'espèce, sans aucun champ de sa culture.
 *
 * Relevé en production le 2026-10-06 (series:doCreate, structure 6491) : le corps posté portait
 * `plant[0]` et les champs de la série, mais aucun champ de la culture. Des étapes de
 * Cultivation::properties() lisent `distance`, `seedling` ou `harvestPeriodExpected` pour savoir
 * si elles s'appliquent, et la culture de référence de SeriesLib::prepareCreate() ne les posait
 * pas : la lecture levait
 * « Property distance of Element series\Cultivation is not set » au lieu de construire. La culture
 * part désormais avec les défauts que CultivationLib pose sur une culture neuve, et le POST
 * incomplet est refusé par un message de formulaire, comme celui qui laisse un champ vide.
 *
 * Lancement : dev/test package=series file=SeriesCreateIncompleteAi
 */
class SeriesCreateIncompleteAiTest extends SeriesFixtureAiTest {

	private function getInput(): array {

		return [
			'structure' => (string)$this->eStructure['id'],
			'season' => (string)$this->getSeason(),
			'name' => 'Fenouil',
			'cycle' => Series::ANNUAL,
			'mode' => Series::GREENHOUSE,
			'use' => Series::BED,
			'plant' => [0 => (string)$this->getPlant()['id']],
			'bedWidth' => '125',
			'alleyWidth' => '45',
			'lengthTarget' => '25',
			'areaTarget' => '30'
		];

	}

	public function testWithoutCultivationFields(): void {

		$fw = new \FailWatch();

		$prepare = SeriesLib::prepareCreate($this->getInput(), $this->eStructure);

		$this->assertEquals([], $prepare, 'la création est refusée sans lever');
		$this->assertTrue($fw->has('Cultivation::varietiesUnit.check'), 'le refus passe par un message de formulaire : '.$fw);
		$this->assertTrue(AlertUi::getError('Cultivation::varietiesUnit.check') !== NULL, 'le refus a un message à afficher');

	}

}
