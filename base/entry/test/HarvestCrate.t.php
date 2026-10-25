<?php
namespace entry;

/**
 * « courgette 3 caisses » : une récolte comptée en caisses s'écrit en kilos, au poids de caisse de l'espèce
 * (`plant.crateWeight`), par le calcul et l'arrondi du panneau Consigner (`\series\TaskLib::prepareHarvestForWeek()`,
 * `\planning\HarvestWeekLib::getCrateQuantity()`). Comme dans le panneau, les caisses ne valent qu'en kilos, et
 * une espèce sans poids ne se récolte pas en caisses.
 */
class HarvestCrateTest extends EntryFixtureTest {

	/**
	 * Le poids d'une caisse de l'espèce du décor.
	 */
	private function setCrateWeight(?float $crateWeight): \plant\Plant {

		$ePlant = $this->getPlant();
		$ePlant['crateWeight'] = $crateWeight;

		// Sans `build()` : il lit le poids posté par le panneau et le refuse à qui ne peut pas écrire l'espèce,
		// alors que le décor fixe lui-même le poids, absent compris, que seule une écriture directe remet à NULL.
		\plant\PlantLib::update($ePlant, ['crateWeight']);

		return $ePlant;

	}

	public function testWords(): void {

		$this->createCultivationInPhase(TRUE);
		$this->setCrateWeight(8.0);

		foreach(['3 caisses ', '3 caisse ', '3 cs ', '3 Caisses', '3 c', '3 cai'] as $rest) {

			$reading = $this->read($this->getText($rest));

			$this->assertEquals(UnitComplementLib::CRATE, $reading['complements']['unit'], '« '.$rest.' » se lit en caisses');
			$this->assertEquals(EntryLib::WRITE, $reading['outcome'], '« '.$rest.' » s\'écrit');
			$this->assertEquals(24.0, $reading['confirmation']['quantity'], '« '.$rest.' » écrit 3 caisses de 8 kg');

		}

	}

	/**
	 * Trois caisses de 8 kg écrivent 24 kg sur la récolte de la semaine, et la confirmation garde le nombre de
	 * caisses que la phrase a dit.
	 */
	public function testWrite(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$this->setCrateWeight(8.0);

		$reading = $this->read($this->getText('3 caisses '));

		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertEquals(24.0, $reading['confirmation']['quantity']);
		$this->assertEquals(3.0, $reading['confirmation']['crates']);
		$this->assertEquals(\series\Task::KG, $reading['confirmation']['unit']);

		EntryLib::write($this->eStructure, $reading);

		$cTask = $this->getHarvest($eCultivation);

		$this->assertEquals(1, $cTask->count());
		$this->assertEquals(24.0, (float)$cTask->first()['harvest'], '3 caisses de 8 kg écrivent 24 kg');
		$this->assertEquals(\series\Task::KG, $cTask->first()['harvestUnit']);

		// Le poids se règle dans le panneau Consigner, jamais par la saisie, qui ne fait que le lire.
		$this->assertEquals(8.0, (float)\plant\PlantLib::getById($this->getPlant()['id'])['crateWeight'], 'la saisie ne touche pas au poids');

		// Le total du jour compte en kilos : la saisie suivante en kilos s'y ajoute.
		$reading = $this->read($this->getText('6 kg '));

		$this->assertEquals(30.0, $reading['confirmation']['today']);
		$this->assertEquals(NULL, $reading['confirmation']['crates']);

	}

	/**
	 * Le même arrondi au dixième de kilo que le panneau : 3 caisses de 2,25 kg écrivent 6,8 kg.
	 */
	public function testRounding(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$ePlant = $this->setCrateWeight(2.25);

		$reading = $this->read($this->getText('3 caisses '));

		$this->assertEquals(\planning\HarvestWeekLib::getCrateQuantity($ePlant, 3.0), $reading['confirmation']['quantity'], 'la confirmation convertit comme le panneau');
		$this->assertEquals(6.8, $reading['confirmation']['quantity']);

		EntryLib::write($this->eStructure, $reading);

		$this->assertEquals(6.8, (float)$this->getHarvest($eCultivation)->first()['harvest'], 'l\'écriture convertit comme la confirmation');

	}

	/**
	 * Les caisses se comptent, comme les pièces et les bottes : la lecture leur refuse la virgule.
	 */
	public function testWhole(): void {

		$this->createCultivationInPhase(TRUE);
		$this->setCrateWeight(8.0);

		$reading = $this->read($this->getText('2,5 caisses '));

		$this->assertEquals(['reason' => 'whole', 'unit' => UnitComplementLib::CRATE], $reading['refusal']);
		$this->assertEquals(NULL, $reading['confirmation']);

	}

	/**
	 * Une espèce sans poids de caisse ne se récolte pas en caisses : le refus le dit dès le mot, avant la
	 * quantité.
	 */
	public function testNoWeight(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$ePlant = $this->setCrateWeight(NULL);

		foreach(['caisses ', '3 caisses '] as $rest) {

			$reading = $this->read($this->getText($rest));

			$this->assertEquals(EntryLib::REFUSE, $reading['outcome'], '« '.$rest.' » est refusé');
			$this->assertEquals('crateWeight', $reading['refusal']['reason'], '« '.$rest.' » est refusé faute de poids');
			$this->assertEquals($ePlant['id'], $reading['refusal']['object']['id']);

		}

		$this->assertEquals(0, $this->getHarvest($eCultivation)->count());

	}

	/**
	 * Le moteur ne lit que les intentions que l'utilisateur en ligne peut écrire (`EntryLib::getAllowed()`) :
	 * sans droit sur le planning, « 3 caisses » ne se lit pas en récolte.
	 */
	public function testNotReadWithoutPlanning(): void {

		$this->createCultivationInPhase(TRUE);
		$this->setCrateWeight(NULL);

		\user\ConnectionLib::setOnline(new \user\User());

		try {

			$reading = $this->read($this->getText('3 caisses '));

			$this->assertEquals([NULL, EntryLib::ASK, NULL], [$reading['intention'], $reading['outcome'], $reading['refusal']], 'sans membre en ligne, « 3 caisses » ne se lit pas en récolte');

		} finally {
			\user\ConnectionLib::setOnline($this->getMember());
		}

	}

	/**
	 * Une culture récoltée en bottes ne prend pas de caisses, qui se convertissent en kilos, et les caisses ne
	 * vont pas sur une autre intervention en kilos comme « 3 kg » : le panneau ne les offre qu'à l'unité de la
	 * récolte. Avec ou sans récolte de la semaine.
	 */
	public function testUnitNotKg(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$this->setCrateWeight(8.0);

		// Sans `build()` : l'unité principale se choisit dans le formulaire de la culture, que le décor de `series`
		// ne poste pas.
		$eCultivation['mainUnit'] = \series\Cultivation::BUNCH;

		\series\Cultivation::model()
			->select(['mainUnit'])
			->update($eCultivation);

		$reading = $this->read($this->getText('3 caisses '));

		$this->assertEquals(['reason' => 'crateUnit', 'unit' => \series\Task::BUNCH], $reading['refusal'], 'sans récolte de la semaine');

		$this->createTaskTodo($eCultivation, '+0 day');

		$reading = $this->read($this->getText('3 caisses '));

		$this->assertEquals(['reason' => 'crateUnit', 'unit' => \series\Task::BUNCH], $reading['refusal'], 'avec une récolte de la semaine en bottes');
		$this->assertEquals(NULL, $reading['confirmation']);

	}

	/**
	 * Avec le temps, « courgette 3 caisses 30 min » : la récolte en kilos et le temps sur la même intervention.
	 */
	public function testTime(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$this->setCrateWeight(8.0);

		if($this->assertDecorPresent($this->eStructure->hasTime(), 'la structure du décor suit le temps de travail') === FALSE) {
			return;
		}

		$reading = $this->read($this->getText('3 caisses 30 min '));

		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertEquals(24.0, $reading['confirmation']['quantity']);
		$this->assertEquals(30, $reading['confirmation']['minutes']);

		EntryLib::write($this->eStructure, $reading);

		$this->assertEquals(24.0, (float)$this->getHarvest($eCultivation)->first()['harvest']);

	}

	/**
	 * Annuler retire les kilos écrits, et l'intervention que la saisie avait créée.
	 */
	public function testCancel(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$this->setCrateWeight(8.0);

		$reading = $this->read($this->getText('3 caisses '));

		EntryLib::write($this->eStructure, $reading);

		$eHarvest = $reading['confirmation']['eTask']['eHarvest'];

		$this->assertEquals(24.0, (float)$eHarvest['quantity']);

		$this->cancelUndo($reading['undo']);

		$this->assertEquals(0, $this->getHarvest($eCultivation)->count(), 'l\'intervention créée part avec ses kilos');

	}

	/**
	 * Le mot seul, sans quantité, attend le nombre de caisses.
	 */
	public function testQuantityMissing(): void {

		$this->createCultivationInPhase(TRUE);
		$this->setCrateWeight(8.0);

		$reading = $this->read($this->getText('caisses '));

		$this->assertEquals(EntryLib::ASK, $reading['outcome']);
		$this->assertEquals(NULL, $reading['refusal']);
		$this->assertEquals(UnitComplementLib::CRATE, $reading['complements']['unit']);

	}

}
?>
