<?php
namespace entry;

/**
 * Condition d'atomicité du go de l'architecte (2026-09-28) : une saisie refusée ne laisse ni intervention ni
 * récolte.
 *
 * Depuis que la saisie écrit par le chemin de Consigner (S2e), tout ce qui peut refuser se lève dans
 * `prepare()`, avant la transaction : `TaskLib::prepareHarvestForWeek()` construit la récolte sans rien
 * écrire, et `writePreparedHarvestForWeek()` ne refuse plus rien. L'échec est forcé par une quantité
 * négative posée dans la confirmation après la lecture, qui la refuse d'elle-même.
 *
 * Le refus ne doit donc rien défaire : la transaction du test reste ouverte, preuve qu'aucun
 * `rollBack()` n'a été joué (il remet la pile du serveur à 0 quel que soit l'étage, `framework/core/Module.php`).
 */
class EntryAtomicityTest extends EntryFixtureTest {

	public function testRefusedBeforeWriting(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$reading = $this->read($this->getText('12 kg '));

		$this->assertTrue($reading['confirmation']['created']);

		$reading['confirmation']['quantity'] = -5.0;

		try {
			EntryLib::write($this->eStructure, $reading);
			$this->assertFail('l\'écriture lève');
		} catch(\FailAction) {
		}

		$this->assertTrue(\series\Task::model()->inTransaction(), 'le refus n\'a défait aucune transaction');

		$this->assertEquals(0, $this->getHarvest($eCultivation)->count(), 'ni intervention');
		$this->assertTrue(\series\Harvest::model()->whereCultivation($eCultivation)->exists() === FALSE, 'ni récolte');

	}

}
?>
