<?php
namespace entry;

/**
 * La saisie et Consigner choisissent la même intervention de récolte (S2e) : chaque cas pose les mêmes
 * interventions, puis compare celle de la confirmation (`EntryLib::read()`) à celle que Consigner écrirait
 * (`HarvestWeekLib::getTaskForEntry()`). Deux interventions à créer se comparent par leur unité, leur couple
 * et leur statut, faute d'identifiant.
 */
class HarvestTaskChoiceTest extends EntryFixtureTest {

	public function setUp(): void {

		parent::setUp();

		$this->setClockInSeason();

	}

	/**
	 * Cas A : rien dans la semaine, une récolte à faire en retard la remplit.
	 */
	public function testDelayed(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$eTask = $this->createTaskTodo($eCultivation, '-14 days');

		$this->assertEquals($eTask['id'], $this->check($eCultivation, '12 kg', NULL, 'une récolte en retard')['id']);

	}

	/**
	 * Deux récoltes en retard : la plus ancienne gagne.
	 */
	public function testDelayedOldest(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$this->createTaskTodo($eCultivation, '-7 days');
		$eTaskOldest = $this->createTaskTodo($eCultivation, '-21 days');

		$this->assertEquals($eTaskOldest['id'], $this->check($eCultivation, '12 kg', NULL, 'deux récoltes en retard')['id']);

	}

	/**
	 * Une récolte en retard au-delà de `planningDelayedMax` n'est plus en retard : les deux voies créent.
	 */
	public function testDelayedBeyond(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$this->createTaskTodo($eCultivation, '-'.($this->eStructure->conf(\map\Configuration::class)['planningDelayedMax'] + 1).' months');

		$this->assertTrue($this->check($eCultivation, '12 kg', NULL, 'une récolte trop ancienne')->exists() === FALSE);

	}

	/**
	 * Cas B : la seule récolte de la semaine porte une variété, ou un calibre. La phrase n'en dit aucun,
	 * donc les deux voies créent une récolte sur la culture entière. Une culture par cas, deux cultures en
	 * phase faisant poser la question de la culture.
	 */
	public function testVariety(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$this->createTaskTodo($eCultivation, '+0 days', ['variety' => $this->createVariety()]);

		$this->assertTrue($this->check($eCultivation, '12 kg', NULL, 'une récolte à variété')->exists() === FALSE);

	}

	public function testSize(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$this->createTaskTodo($eCultivation, '+0 days', ['harvestSize' => $this->createSize($this->getPlant())]);

		$this->assertTrue($this->check($eCultivation, '12 kg', NULL, 'une récolte à calibre')->exists() === FALSE);

	}

	/**
	 * Cas C : la semaine porte une récolte en bottes sur une culture au kilo. « 2 » et « 2 kg » vont au kilo,
	 * « 2 bottes » écrit sur la récolte en bottes.
	 */
	public function testUnit(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$eTaskBunch = $this->createTaskTodo($eCultivation, '+0 days', ['harvestUnit' => \series\Task::BUNCH]);

		$this->assertTrue($this->check($eCultivation, '2', NULL, 'sans unité')->exists() === FALSE);
		$this->assertTrue($this->check($eCultivation, '2 kg', NULL, 'au kilo')->exists() === FALSE);
		$this->assertEquals($eTaskBunch['id'], $this->check($eCultivation, '2 bottes', \series\Task::BUNCH, 'en bottes')['id']);

	}

	/**
	 * Deux récoltes à faire et une faite dans la semaine : la première à faire.
	 */
	public function testOrderTodo(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$eTaskFirst = $this->createTaskTodo($eCultivation, '+0 days');
		$this->createTaskTodo($eCultivation, '+0 days');
		$this->createTaskHarvest($eCultivation);

		$this->assertEquals($eTaskFirst['id'], $this->check($eCultivation, '12 kg', NULL, 'deux à faire et une faite')['id']);

	}

	/**
	 * Deux récoltes faites dans la semaine : la dernière.
	 */
	public function testOrderDone(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$this->createTaskHarvest($eCultivation);
		$eTaskLast = $this->createTaskHarvest($eCultivation);

		$this->assertEquals($eTaskLast['id'], $this->check($eCultivation, '12 kg', NULL, 'deux faites')['id']);

	}

	/**
	 * Cas A écrit : la saisie sur une récolte en retard la laisse dans l'état où Consigner laisse sa jumelle.
	 */
	public function testWriteDelayed(): void {

		$this->checkWrite('-14 days', 'en retard');

	}

	/**
	 * Cas D écrit : de même sur une récolte à faire de la semaine.
	 */
	public function testWriteWeek(): void {

		$this->checkWrite('+0 days', 'de la semaine');

	}

	/**
	 * La saisie écrit d'abord, seule culture à voir ; la jumelle, posée après, reçoit la même récolte par
	 * Consigner, qui ne lit pas la phase.
	 */
	private function checkWrite(string $shift, string $label): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$eTask = $this->createTaskTodo($eCultivation, $shift);

		$reading = $this->read($this->getText('12 kg '));

		if($this->assertEquals(EntryLib::WRITE, $reading['outcome'], 'récolte '.$label.' : la phrase s\'écrit') === FALSE) {
			return;
		}

		EntryLib::write($this->eStructure, $reading);

		$eCultivationTwin = $this->createCultivationInPhase(FALSE, 'Série jumelle');
		$eTaskTwin = $this->createTaskTodo($eCultivationTwin, $shift);

		\planning\HarvestWeekLib::writeEntry($this->eStructure, \series\CultivationLib::getById($eCultivationTwin['id']), \planning\HarvestWeekLib::getDays(\Clock::week()), [
			'date' => \Clock::date(),
			'mode' => 'add',
			'harvestMore' => '12',
		], stock: FALSE);

		$properties = ['status', 'plannedWeek', 'plannedDate', 'doneWeek', 'doneDate', 'harvest', 'harvestUnit'];

		$this->assertEquals(
			\series\Task::model()->select($properties)->whereId($eTaskTwin)->get()->extracts($properties),
			\series\Task::model()->select($properties)->whereId($eTask)->get()->extracts($properties),
			'récolte '.$label.' : même état que par Consigner'
		);

	}

	/**
	 * Compare l'intervention de la saisie à celle de Consigner et rend la seconde.
	 */
	private function check(\series\Cultivation $eCultivation, string $quantity, ?string $unitOther, string $label): \series\Task {

		$reading = $this->read($this->getText($quantity.' '));

		if($this->assertEquals(EntryLib::WRITE, $reading['outcome'], $label.' : la phrase s\'écrit') === FALSE) {
			return new \series\Task();
		}

		$eTaskEntry = $reading['confirmation']['eTask'];
		$eTaskConsigner = \planning\HarvestWeekLib::getTaskForEntry($this->eStructure, \series\CultivationLib::getById($eCultivation['id']), \Clock::date(), $unitOther);

		if($eTaskConsigner->exists()) {
			$this->assertEquals($eTaskConsigner['id'], $eTaskEntry->exists() ? $eTaskEntry['id'] : NULL, $label.' : la même intervention');
		} else {
			$this->assertTrue($reading['confirmation']['created'], $label.' : la saisie crée aussi');
			$this->assertEquals(
				$eTaskConsigner->extracts(['harvestUnit', 'status']) + ['variety' => $eTaskConsigner['variety']->empty(), 'harvestSize' => $eTaskConsigner['harvestSize']->empty()],
				$eTaskEntry->extracts(['harvestUnit', 'status']) + ['variety' => $eTaskEntry['variety']->empty(), 'harvestSize' => $eTaskEntry['harvestSize']->empty()],
				$label.' : une intervention neuve de même unité, couple et statut'
			);
		}

		return $eTaskConsigner;

	}

	/**
	 * L'ail 2025 de la 662 : trois cultures en phase sans récolte et une de la saison d'avant, hors phase,
	 * qui a sa récolte à faire en retard. La saisie écrit sur celle-ci, comme Consigner, et le détail dit
	 * la semaine où elle était prévue.
	 */
	public function testOtherSeasonDelayed(): void {

		$this->createCultivationInPhase(TRUE, 'Ail A');
		$this->createCultivationInPhase(TRUE, 'Ail B');
		$this->createCultivationInPhase(TRUE, 'Ail C');

		$eCultivation = $this->createCultivationPreviousSeason('Ail de la saison d\'avant');
		$eTask = $this->createTaskTodo($eCultivation, '-21 days');

		$this->assertEquals($eTask['id'], $this->check($eCultivation, '12 kg', NULL, 'l\'ail de la saison d\'avant')['id']);

		$this->assertEquals($eTask['id'], $this->read($this->getText('12 kg '))['confirmation']['eTaskDelayed']['id'], 'la confirmation dit le retard');

	}

	/**
	 * La betterave de la 1786 : une culture en phase sans récolte, et la botte d'été, hors phase, en retard
	 * de quatre semaines. La saisie écrit sur la récolte en retard, comme Consigner, et non plus sur une
	 * intervention neuve de la culture en phase.
	 */
	public function testDelayedOutOfPhase(): void {

		$this->createCultivationInPhase(TRUE, 'Série de conservation');

		$eCultivation = $this->createCultivationInPhase(FALSE, 'Série botte d\'été');
		$eTask = $this->createTaskTodo($eCultivation, '-28 days');

		$this->assertEquals($eTask['id'], $this->check($eCultivation, '12 kg', NULL, 'la botte d\'été en retard')['id']);

	}

	/**
	 * Chaque ligne d'une question annonce la récolte que Consigner compléterait sur sa culture, et seule
	 * la récolte en retard dit sa semaine.
	 */
	public function testQuestionTargets(): void {

		$eCultivationDone = $this->createCultivationInPhase(TRUE, 'Série de conservation');
		$this->createTaskHarvest($eCultivationDone);

		$eCultivationDelayed = $this->createCultivationInPhase(FALSE, 'Série botte d\'été');
		$eTaskDelayed = $this->createTaskTodo($eCultivationDelayed, '-28 days');

		$reading = $this->read($this->getText('12 kg '));

		if($this->assertTrue($reading['question'] !== NULL, 'deux cultures qui ont une récolte : la question') === FALSE) {
			return;
		}

		$cCultivation = $reading['question']['cCultivation'];

		$this->assertEquals(2, $cCultivation->count());

		foreach($cCultivation as $eCultivation) {

			$eTaskConsigner = \planning\HarvestWeekLib::getTaskForEntry($this->eStructure, \series\CultivationLib::getById($eCultivation['id']), \Clock::date(), NULL);

			$this->assertEquals($eTaskConsigner['id'], $eCultivation['eTaskTarget']['id'], $eCultivation['series']['name'].' : la récolte de Consigner');

		}

		$this->assertTrue($cCultivation[$eCultivationDone['id']]['eTaskDelayed']->empty(), 'la récolte faite ne dit pas de semaine');
		$this->assertEquals($eTaskDelayed['id'], $cCultivation[$eCultivationDelayed['id']]['eTaskDelayed']['id'], 'la récolte en retard dit sa semaine');

	}

}
?>
