<?php
namespace entry;

/**
 * « récolte courgette 12 kg », de la lecture à l'écriture, sur le décor du package `series`.
 */
class HarvestIntentionTest extends EntryFixtureTest {

	private function write(string $text, array $answers = []): array {

		$reading = $this->read($text, $answers);

		$this->assertEquals(EntryLib::WRITE, $reading['outcome'], 'la phrase « '.$text.' » s\'écrit');

		EntryLib::write($this->eStructure, $reading);

		return $reading;

	}

	/**
	 * Sans intervention de récolte cette semaine, l'écriture la crée, faite aujourd'hui, dans
	 * l'unité dite, et y pose la quantité.
	 */
	public function testCreated(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$reading = $this->read($this->getText('12 kg '));

		$this->assertTrue($reading['confirmation']['created']);

		EntryLib::write($this->eStructure, $reading);

		$cTask = $this->getHarvest($eCultivation);

		$this->assertEquals(1, $cTask->count());

		$eTask = $cTask->first();

		$this->assertEquals(\series\Task::DONE, $eTask['status']);
		$this->assertEquals(\Clock::date(), $eTask['doneDate']);
		$this->assertEquals(12.0, (float)$eTask['harvest']);
		$this->assertEquals(\series\Task::KG, $eTask['harvestUnit']);

	}

	/**
	 * La seconde saisie du jour s'ajoute sur la même intervention.
	 */
	public function testSecondInput(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$this->write($this->getText('45 kg '));

		$reading = $this->read($this->getText('12 kg '));

		$this->assertFalse($reading['confirmation']['created']);
		$this->assertEquals(57.0, $reading['confirmation']['today']);

		EntryLib::write($this->eStructure, $reading);

		$cTask = $this->getHarvest($eCultivation);

		$this->assertEquals(1, $cTask->count());
		$this->assertEquals(57.0, (float)$cTask->first()['harvest']);

	}

	public function testExistingTask(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$eTask = $this->createTaskHarvest($eCultivation);

		$reading = $this->read($this->getText('12 kg '));

		$this->assertFalse($reading['confirmation']['created']);
		$this->assertEquals($eTask['id'], $reading['confirmation']['eTask']['id']);

	}

	/**
	 * Une unité autre que celle que tient l'intervention de la semaine désigne une autre intervention, comme
	 * dans Consigner (retour 319, 17/10, Q3) : sans intervention dans cette unité, la saisie en crée
	 * une, et celle de la semaine reste telle quelle.
	 */
	public function testUnitOther(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$eTaskKg = $this->createTaskHarvest($eCultivation);

		$reading = $this->read($this->getText('12 bottes '));

		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertEquals(\series\Task::BUNCH, $reading['complements']['unit']);
		$this->assertTrue($reading['confirmation']['created'], 'les bottes vont sur une intervention neuve');

		EntryLib::write($this->eStructure, $reading);

		$harvests = [];

		foreach($this->getHarvest($eCultivation) as $eTask) {
			$harvests[$eTask['id']] = [$eTask['harvestUnit'], $eTask['harvest']];
		}

		$this->assertEquals([$eTaskKg['id'] => [\series\Task::KG, NULL]], array_intersect_key($harvests, [$eTaskKg['id'] => TRUE]), 'l\'intervention en kilos ne reçoit rien');
		$this->assertEquals([[\series\Task::BUNCH, 12.0]], array_values(array_diff_key($harvests, [$eTaskKg['id'] => TRUE])), 'les bottes sont écrites à part');

	}

	/**
	 * Sur une intervention créée, l'unité dite n'est jamais refusée : c'est elle qui la fonde.
	 */
	public function testUnitOnCreated(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$this->write($this->getText('3 bottes '));

		$this->assertEquals(\series\Task::BUNCH, $this->getHarvest($eCultivation)->first()['harvestUnit']);

	}

	public function testUnitDefault(): void {

		$this->createCultivationInPhase(TRUE);

		$reading = $this->read($this->getText('12 '));

		$this->assertEquals(\series\Task::KG, $reading['complements']['unit']);
		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);

	}

	public function testQuantityMissing(): void {

		$this->createCultivationInPhase(TRUE);

		$reading = $this->read($this->getText());

		$this->assertEquals(EntryLib::ASK, $reading['outcome']);
		$this->assertEquals(NULL, $reading['question']);
		$this->assertTrue($reading['cultivation']->notEmpty());

	}

	/**
	 * Une récolte n'a pas de récapitulatif : `recap` posté ne la laisse pas s'écrire pendant qu'une date se tape,
	 * et la date en cours de frappe ne retient pas celle du jeton.
	 */
	public function testRecapIgnored(): void {

		$this->setClock('2026-10-16 10:00:00');

		$this->createCultivationInPhase(TRUE);

		foreach(['hier '.$this->getText('12 kg le'), 'hier '.$this->getText('12 kg le ')] as $text) {

			$reading = $this->read($text, ['recap' => TRUE]);

			$this->assertFalse($reading['recap'], '« '.$text.' »');
			$this->assertEquals(EntryLib::ASK, $reading['outcome'], '« '.$text.' » ne s\'écrit pas');
			$this->assertEquals('2026-10-16', $reading['date'], '« '.$text.' » se lit à la date du jour');

		}

	}

	public function testQuantityRefused(): void {

		$this->createCultivationInPhase(TRUE);

		$reading = $this->read($this->getText('0 kg '));

		$this->assertEquals(['reason' => 'quantity'], $reading['refusal']);

	}

	/**
	 * « -12 kg » retire du jour, et la confirmation dit ce qui reste. Le retrait ne termine rien.
	 */
	public function testRemove(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$this->write($this->getText('45 kg '));

		$reading = $this->read($this->getText('-12 kg '));

		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertEquals(-12.0, $reading['confirmation']['quantity']);
		$this->assertEquals(33.0, $reading['confirmation']['today']);

		EntryLib::write($this->eStructure, $reading);

		$cTask = $this->getHarvest($eCultivation);

		$this->assertEquals(1, $cTask->count());
		$this->assertEquals(33.0, (float)$cTask->first()['harvest']);

		// Jusqu'à zéro compris.
		$reading = $this->read($this->getText('-33 kg '));

		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertEquals(0.0, $reading['confirmation']['today']);

	}

	/**
	 * Un retrait qui passerait sous zéro se refuse dès la lecture, avec ce que le jour compte, et sans récolte rien ne
	 * se retire. Des caisses et un temps ne se retirent pas.
	 */
	public function testRemoveRefused(): void {

		$this->createCultivationInPhase(TRUE);

		$reading = $this->read($this->getText('-12 kg '));

		$this->assertEquals(['reason' => 'removeMore', 'today' => 0.0, 'unit' => \series\Task::KG], $reading['refusal']);

		$this->write($this->getText('5 kg '));

		$reading = $this->read($this->getText('-12 kg '));

		$this->assertEquals(['reason' => 'removeMore', 'today' => 5.0, 'unit' => \series\Task::KG], $reading['refusal']);

		$reading = $this->read($this->getText('-2 kg 30 min '));

		$this->assertEquals(['reason' => 'removeTime'], $reading['refusal']);

	}

	/**
	 * En pièces, « 9,5 » se refuse au lieu de s'arrondir, et le même nombre reste admis en kilos.
	 */
	public function testQuantityWhole(): void {

		$this->createCultivationInPhase(TRUE);

		$reading = $this->read($this->getText('9,5 pieces '));

		$this->assertEquals(['reason' => 'whole', 'unit' => \series\Task::UNIT], $reading['refusal']);
		$this->assertEquals(NULL, $reading['confirmation']);

		$reading = $this->read($this->getText('9 pieces '));

		$this->assertEquals(NULL, $reading['refusal']);

		$reading = $this->read($this->getText('9,5 kg '));

		$this->assertEquals(NULL, $reading['refusal']);

	}

	/**
	 * Deux cultures en phase : la question, puis la réponse qui mène à l'écriture.
	 */
	public function testQuestion(): void {

		$this->createCultivationInPhase(TRUE, 'Série A');
		$eCultivation = $this->createCultivationInPhase(TRUE, 'Série B');

		$reading = $this->read($this->getText('12 kg '));

		$this->assertEquals(EntryLib::ASK, $reading['outcome']);
		$this->assertEquals('cultivation', $reading['question']['name']);
		$this->assertEquals(2, $reading['question']['cCultivation']->count());

		$reading = $this->write($this->getText('12 kg '), ['cultivation' => $eCultivation['id']]);

		$this->assertEquals($eCultivation['id'], $reading['cultivation']['id']);
		$this->assertEquals(12.0, (float)$this->getHarvest($eCultivation)->first()['harvest']);

	}

	public function testUnknownObject(): void {

		$this->createCultivationInPhase(TRUE);

		$reading = $this->read('récolte zzzqqq 12 kg ');

		$this->assertEquals(EntryLib::REFUSE, $reading['outcome']);
		$this->assertEquals(['reason' => 'object'], $reading['refusal']);

	}

	/**
	 * Avant l'espèce, la première lettre tapée cherche une espèce et non une unité : « b » ne devient
	 * pas des bottes. Une fois l'espèce nommée, le même début pose l'unité.
	 */
	public function testTypingBeforeObject(): void {

		$this->createCultivationInPhase(TRUE);

		foreach(['b', 'k', 'p', 'u'] as $letter) {

			$reading = $this->read('récolte '.$letter);

			$this->assertTrue($reading['object']->empty(), 'aucune espèce pour « '.$letter.' »');
			$this->assertEquals(NULL, $reading['complements']['unit'], '« '.$letter.' » n\'est pas une unité avant l\'espèce');
			$this->assertEquals($letter, $reading['pending'], '« '.$letter.' » attend une espèce');

		}

		$reading = $this->read($this->getText('12 b'));

		$this->assertEquals(\series\Task::BUNCH, $reading['complements']['unit']);

	}

	/**
	 * Un mot que rien ne réclame empêche l'écriture, même si le reste est complet.
	 */
	public function testUnclaimed(): void {

		$this->createCultivationInPhase(TRUE);

		$reading = $this->read($this->getText('12 sacs '));

		$this->assertEquals(EntryLib::REFUSE, $reading['outcome']);
		$this->assertEquals('unclaimed', $reading['refusal']['reason']);

	}

	/**
	 * L'ordre des mots, le pluriel et les liaisons ne changent pas la lecture.
	 */
	public function testWordOrder(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$name = $this->getPlant()['name'];

		foreach([
			'récolte '.$name.' 12 kg ',
			'12 kg de '.$name.'s récolte ',
			'Récolte 12kg '.$name.' ',
		] as $text) {

			$reading = $this->read($text);

			$this->assertEquals(EntryLib::WRITE, $reading['outcome'], $text);
			$this->assertEquals($eCultivation['id'], $reading['cultivation']['id'], $text);
			$this->assertEquals(12.0, $reading['complements']['quantity'], $text);

		}

	}

	/**
	 * Une récolte datée de la semaine passée, sans intervention cette semaine-là : l'intervention est
	 * créée faite ce jour-là, et la récolte y est posée à cette date.
	 */
	public function testDatedCreated(): void {

		$this->setClockInSeason();

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$date = date('Y-m-d', \Clock::shift('-7 days'));

		$reading = $this->write($this->getText('12 kg '.DateComplementUi::getToken($date).' '));

		$this->assertEquals($date, $reading['date']);
		$this->assertTrue($reading['confirmation']['created']);

		$eTask = $this->getHarvest($eCultivation)->first();

		$this->assertEquals(\series\Task::DONE, $eTask['status']);
		$this->assertEquals($date, $eTask['doneDate']);
		$this->assertEquals(12.0, (float)$eTask['harvest']);

		$cHarvest = \series\Harvest::model()->select(['date', 'quantity'])->whereTask($eTask)->getCollection();

		$this->assertEquals([$date], $cHarvest->getColumn('date'));

	}

	/**
	 * Une récolte datée remplit l'intervention prévue la semaine du jour dit, et la termine comme Consigner
	 * (17/10, Q1) : son jour prévu passe au jour dit.
	 */
	public function testDatedPlanned(): void {

		$this->setClockInSeason();

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$date = date('Y-m-d', \Clock::shift('-7 days'));

		$eTask = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, toWeek($date));
		$this->createTaskHarvest($eCultivation);

		$reading = $this->write($this->getText('12 kg '.DateComplementUi::getToken($date).' '));

		$this->assertEquals($eTask['id'], $reading['confirmation']['eTask']['id']);

		$eTaskWritten = \series\Task::model()->select(['status', 'plannedDate', 'doneWeek', 'harvest'])->whereId($eTask)->get();

		$this->assertEquals([\series\Task::DONE, $date, toWeek($date)], [$eTaskWritten['status'], $eTaskWritten['plannedDate'], $eTaskWritten['doneWeek']]);
		$this->assertEquals(12.0, (float)$eTaskWritten['harvest']);

	}

	/**
	 * Le total de la confirmation est celui du jour dit, pas celui d'aujourd'hui.
	 */
	public function testDatedTotal(): void {

		$this->setClockInSeason();

		$this->createCultivationInPhase(TRUE);

		$day = DateComplementUi::getToken(date('Y-m-d', \Clock::shift('-1 DAY')));

		$this->write($this->getText('45 kg '.$day.' '));

		$this->assertEquals(57.0, $this->read($this->getText('12 kg '.$day.' '))['confirmation']['today']);
		$this->assertEquals(12.0, $this->read($this->getText('12 kg '))['confirmation']['today']);

	}

	/**
	 * Une récolte ne se saisit ni à l'avance ni au-delà de `EntrySetting::PAST_DAYS`, et un jour
	 * qui n'existe pas est refusé.
	 */
	public function testDatedBounds(): void {

		$this->setClockInSeason();

		$this->createCultivationInPhase(TRUE);

		$days = EntrySetting::PAST_DAYS;

		$this->assertEquals(['reason' => 'future'], $this->read($this->getText('12 kg demain '))['refusal']);
		$this->assertEquals(['reason' => 'future'], $this->read($this->getText('12 kg lundi prochain '))['refusal']);
		$this->assertEquals(['reason' => 'past', 'days' => $days], $this->read($this->getText('12 kg '.DateComplementUi::getToken(date('Y-m-d', \Clock::shift('-'.($days + 1).' DAYS'))).' '))['refusal']);
		$this->assertEquals(['reason' => 'date'], $this->read($this->getText('12 kg 31/02 '))['refusal']);

		$reading = $this->read($this->getText('12 kg '.DateComplementUi::getToken(date('Y-m-d', \Clock::shift('-'.$days.' DAYS'))).' '));

		$this->assertEquals(EntryLib::WRITE, $reading['outcome'], $days.' jours en arrière s\'écrivent');

	}

	/**
	 * Une date qui se tape encore n'écrit pas sur aujourd'hui.
	 */
	public function testDatedIncomplete(): void {

		$this->setClockInSeason();

		$this->createCultivationInPhase(TRUE);

		$reading = $this->read($this->getText('12 kg le'));

		$this->assertEquals(EntryLib::ASK, $reading['outcome']);
		$this->assertEquals(NULL, $reading['confirmation']);
		$this->assertEquals(NULL, $reading['refusal']);

		$this->assertEquals(EntryLib::WRITE, $this->read($this->getText('12 kg hie'))['outcome']);

	}

	/**
	 * La culture se résout à la semaine du jour dit : celle qui était en récolte ce jour-là, et non celle
	 * qui l'est aujourd'hui.
	 */
	public function testDatedPhase(): void {

		$this->setClockInSeason();

		$date = date('Y-m-d', \Clock::shift('-29 days'));

		$eCultivationNow = $this->createCultivation($this->createSeries('Série du moment'), $this->getPlant());
		$this->setHarvestWeek($eCultivationNow, \Clock::week());

		$eCultivationThen = $this->createCultivation($this->createSeries('Série de juin'), $this->getPlant());
		$this->setHarvestWeek($eCultivationThen, toWeek($date));

		$this->assertEquals($eCultivationNow['id'], $this->read($this->getText('12 kg '))['cultivation']['id']);

		$reading = $this->read($this->getText('12 kg '.DateComplementUi::getToken($date).' '));

		$this->assertEquals($eCultivationThen['id'], $reading['cultivation']['id']);
		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);

	}

	/**
	 * Une date dite dans la saison déjà lue ne relit que ce qui dépend du jour, le récolté de ce jour et
	 * les récoltes de sa semaine (l'action de récolte, puis celles faites ou à faire, puis en retard) : les
	 * cultures, leurs séries et leurs espèces sont reprises de la première lecture.
	 */
	public function testDatedReuse(): void {

		$this->setClockInSeason();

		$eCultivation = $this->createCultivation($this->createSeries(), $this->getPlant());
		$this->setHarvestWeek($eCultivation, \Clock::week());

		$plain = $this->countQueries(fn() => $this->read($this->getText('12 kg ')));
		$dated = $this->countQueries(fn() => $this->read($this->getText('12 kg hier ')));

		$this->assertEquals($plain + 4, $dated, 'quatre requêtes de plus, le récolté du jour dit et ses récoltes');

	}

	/**
	 * Une espèce nommée à la date du jour, sans série au jour dit, est refusée sans rien écrire.
	 * L'horloge est posée en janvier : le jour dit tombe dans la saison d'avant, où la série du décor
	 * n'est pas.
	 */
	public function testDatedObject(): void {

		$this->setClock($this->getSeason().'-01-10 10:00:00');

		$eCultivation = $this->createCultivation($this->createSeries(), $this->getPlant());
		$this->setHarvestWeek($eCultivation, \Clock::week());

		$date = date('Y-m-d', \Clock::shift('-21 days'));

		$this->assertEquals(EntryLib::WRITE, $this->read($this->getText('12 kg '))['outcome']);

		$reading = $this->read($this->getText('12 kg '.DateComplementUi::getToken($date).' '));

		$this->assertEquals('dated', $reading['refusal']['reason']);
		$this->assertEquals($this->getPlant()['id'], $reading['refusal']['object']['id']);

	}

	/**
	 * Le jour dit avant le Nouvel An, l'horloge en janvier : la récolte se pose sur la culture de la
	 * saison passée qui était en récolte ce jour-là, et l'intervention créée est faite ce jour-là.
	 */
	public function testDatedNewYear(): void {

		$this->setClock(($this->getSeason() + 1).'-01-08 10:00:00');

		$date = $this->getSeason().'-12-20';

		$eCultivation = $this->createCultivation($this->createSeries(), $this->getPlant());
		$this->setHarvestWeek($eCultivation, toWeek($date));

		$reading = $this->write($this->getText('12 kg '.DateComplementUi::getToken($date).' '));

		$this->assertEquals($date, $reading['date']);
		$this->assertEquals($eCultivation['id'], $reading['cultivation']['id']);
		$this->assertTrue($reading['confirmation']['created']);

		$eTask = $this->getHarvest($eCultivation)->first();

		$this->assertEquals(\series\Task::DONE, $eTask['status']);
		$this->assertEquals($date, $eTask['doneDate']);

	}

	/**
	 * La date se dit n'importe où dans la phrase ; « du » est une liaison, et le nombre qui la suit reste
	 * un second nombre, refusé plutôt qu'écrit à la place de la quantité.
	 */
	public function testDatedWordOrder(): void {

		$this->setClockInSeason();

		$this->createCultivationInPhase(TRUE);

		$name = $this->getPlant()['name'];
		$date = date('Y-m-d', \Clock::shift('-1 DAY'));

		foreach([
			'récolte '.$name.' 12 kg hier ',
			'récolte '.$name.' hier 12 kg ',
			'récolte hier '.$name.' 12 kg ',
		] as $text) {

			$reading = $this->read($text);

			$this->assertEquals(EntryLib::WRITE, $reading['outcome'], $text);
			$this->assertEquals($date, $reading['date'], $text);
			$this->assertEquals(12.0, $reading['complements']['quantity'], $text);

		}

		$this->assertEquals(EntryLib::REFUSE, $this->read('récolte '.$name.' 12 kg du 3 ')['outcome']);

	}

	/**
	 * Un nom d'espèce qui porte un mois se lit d'abord comme l'espèce.
	 */
	public function testDatedPlantName(): void {

		$ePlant = $this->createPlant('Baie de mai');

		$eCultivation = $this->createCultivation($this->createSeries(), $ePlant);
		$this->setPhase($eCultivation, TRUE);

		$reading = $this->read('récolte '.$ePlant['name'].' 3 kg ');

		$this->assertEquals($ePlant['id'], $reading['object']['id']);
		$this->assertEquals(\Clock::date(), $reading['date']);
		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);

	}

	/**
	 * Une culture en phase autour de la semaine `$week` : partie deux semaines plus tôt, récoltée
	 * cette semaine-là. Comme `setPhase()`, l'écriture passe par le modèle : le décor pose les semaines
	 * que la phase lit, sans le formulaire de la culture qui les tirerait de son itinéraire.
	 */
	private function setHarvestWeek(\series\Cultivation $eCultivation, string $week): void {

		$eCultivation['startWeek'] = max(1, week_number($week) - 2);
		$eCultivation['startAction'] = \series\Cultivation::PLANTING;
		$eCultivation['harvestWeeksExpected'] = [$week];

		// Sans `build()` ni `CultivationLib::update()` : ils vérifieraient les semaines saisies dans le
		// formulaire de la culture et les recalculeraient de son itinéraire, alors que le décor veut ces
		// semaines-là exactement, celles que la phase lit.
		\series\Cultivation::model()
			->select(['startWeek', 'startAction', 'harvestWeeksExpected'])
			->update($eCultivation);

	}

	/**
	 * La pastille pose son message en `innerHTML` : ce que la structure a nommé y arrive encodé.
	 */
	public function testToastEncoded(): void {

		$options = ['quantity' => 12.0, 'crates' => NULL, 'unit' => \series\Task::KG, 'minutes' => NULL, 'plant' => '<b>tomate</b>', 'date' => \Clock::date(), 'product' => '<i>vrac</i>'];

		foreach([HarvestIntentionUi::getSuccess($options), HarvestIntentionUi::getCancelled($options)] as $message) {
			$this->assertTrue(str_contains($message, '&lt;b&gt;tomate'), $message);
			$this->assertTrue(str_contains($message, '<b>') === FALSE, $message);
		}

		$this->assertTrue(str_contains(HarvestIntentionUi::getCancelled($options), '&lt;i&gt;vrac'));

	}

}
?>
