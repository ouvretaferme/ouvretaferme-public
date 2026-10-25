<?php
namespace entry;

/**
 * La parcelle que l'ancien planning poste avec une écriture (`#entry-context`) borne la relecture de
 * la semaine. Elle vient du navigateur : une parcelle d'une autre structure est refusée, comme le
 * refusent les autres lectures de parcelle du dépôt (`series/page/task.p.php`), avant toute écriture.
 *
 * Le jardin d'une autre structure est lu dans la base commune, sans y écrire.
 *
 * Lancement : dev/test package=entry file=PlotOfStructure
 */
class PlotOfStructureTest extends EntryFixtureTest {

	public function testForeignPlotRefusedBeforeWrite(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$ePlotForeign = $this->getForeignPlot();

		$this->assertTrue($ePlotForeign->notEmpty(), "la base de dev a une parcelle d'une autre structure");

		$harvests = $this->getHarvest($eCultivation)->count();

		$action = $this->executePageAction('entry/entry:doWrite', [
			'structure' => $this->eStructure['id'],
			'query' => $this->getText('12 kg'),
			'week' => \Clock::week(),
			'plot' => $ePlotForeign['id'],
		]);

		$this->assertTrue($action instanceof \NotExpectedAction, "la parcelle d'une autre structure est refusée");
		$this->assertEquals($harvests, $this->getHarvest($eCultivation)->count(), "et rien n'est écrit");

	}

	/** « Annuler » poste la même parcelle (`EntryField.cancel()`) : refusée avant de rien défaire. */
	public function testForeignPlotRefusedBeforeCancel(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$action = $this->executePageAction('entry/entry:doWrite', [
			'structure' => $this->eStructure['id'],
			'query' => $this->getText('12 kg'),
			'week' => \Clock::week(),
		]);

		$this->assertTrue($action instanceof \ViewAction, "la récolte à annuler est écrite");

		$undo = new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action)->reading['undo'];
		$harvested = $this->getHarvest($eCultivation)->getColumn('harvest');

		$action = $this->executePageAction('entry/entry:doCancel', [
			'structure' => $this->eStructure['id'],
			'intention' => $undo['intention'],
			'ticket' => $undo['ticket'],
			'week' => \Clock::week(),
			'plot' => $this->getForeignPlot()['id'],
		]);

		$this->assertTrue($action instanceof \NotExpectedAction, "la parcelle d'une autre structure est refusée");
		$this->assertEquals($harvested, $this->getHarvest($eCultivation)->getColumn('harvest'), "et la récolte reste");

		// Le témoin : la même annulation sans parcelle défait bien la récolte.
		$action = $this->executePageAction('entry/entry:doCancel', [
			'structure' => $this->eStructure['id'],
			'intention' => $undo['intention'],
			'ticket' => $undo['ticket'],
			'week' => \Clock::week(),
		]);

		$this->assertTrue($action instanceof \ViewAction, "sans parcelle, l'annulation passe");
		$this->assertTrue($harvested !== $this->getHarvest($eCultivation)->getColumn('harvest'), "et défait la récolte");

	}

	/** Le témoin : sans parcelle, la même phrase écrit. */
	public function testWithoutPlotWrites(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$harvests = $this->getHarvest($eCultivation)->count();

		$action = $this->executePageAction('entry/entry:doWrite', [
			'structure' => $this->eStructure['id'],
			'query' => $this->getText('12 kg'),
			'week' => \Clock::week(),
		]);

		$this->assertTrue($action instanceof \ViewAction, "sans parcelle, l'écriture passe");
		$this->assertEquals($harvests + 1, $this->getHarvest($eCultivation)->count(), "et la récolte est écrite");

	}

	/** Lue dans la base commune, sans y écrire. */
	private function getForeignPlot(): \map\Plot {

		return \map\Plot::model()
			->select('id')
			->whereStructure('!=', $this->eStructure)
			->get();

	}

}
?>
