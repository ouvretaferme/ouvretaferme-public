<?php
namespace entry;

/**
 * Quelle culture une espèce désigne (arbre, N10.2, go de l'architecte du 2026-09-28, et piste C de S2e) :
 * une seule candidate sans question, même hors phase ; à partir de deux, celles qui ont une récolte
 * désignent, puis, s'il n'y en a aucune, la phase seule ; sinon Q2.
 */
class CultivationResolveTest extends EntryFixtureTest {

	private function getPlantRead(): \plant\Plant {

		$cPlant = CultivationResolveLib::getPlants($this->eStructure, \Clock::date());

		if($cPlant->offsetExists($this->getPlant()['id']) === FALSE) {
			$this->assertFail('espèce du décor absente de getPlants()');
			return new \plant\Plant(['cCultivation' => new \Collection(), 'inPhase' => 0]);
		}

		return $cPlant[$this->getPlant()['id']];

	}

	public function testSingleOutOfPhase(): void {

		$eCultivation = $this->createCultivationInPhase(FALSE);

		$resolved = CultivationResolveLib::resolve($this->getPlantRead(), NULL, FALSE);

		$this->assertEquals($eCultivation['id'], $resolved['eCultivation']['id']);

	}

	public function testPhaseDesignates(): void {

		$this->createCultivationInPhase(FALSE, 'Série hors phase');
		$eCultivation = $this->createCultivationInPhase(TRUE, 'Série en phase');

		$resolved = CultivationResolveLib::resolve($this->getPlantRead(), NULL, FALSE);

		$this->assertEquals($eCultivation['id'], $resolved['eCultivation']['id']);

	}

	/**
	 * Deux hors phase : la question, sur les deux, et jamais une écriture silencieuse.
	 */
	public function testTwoOutOfPhase(): void {

		$this->createCultivationInPhase(FALSE, 'Série A');
		$this->createCultivationInPhase(FALSE, 'Série B');

		$resolved = CultivationResolveLib::resolve($this->getPlantRead(), NULL, FALSE);

		$this->assertTrue($resolved['eCultivation']->empty());
		$this->assertEquals(2, $resolved['cCultivation']->count());
		$this->assertEquals(0, $resolved['more']);

	}

	/**
	 * Deux en phase et une hors phase : la question porte sur les deux en phase, « Toutes les
	 * séries » ajoute la troisième, et la réponse est retenue.
	 */
	public function testQuestionAndAll(): void {

		$eCultivationA = $this->createCultivationInPhase(TRUE, 'Série A');
		$eCultivationB = $this->createCultivationInPhase(TRUE, 'Série B');
		$eCultivationC = $this->createCultivationInPhase(FALSE, 'Série C');

		$resolved = CultivationResolveLib::resolve($this->getPlantRead(), NULL, FALSE);

		$this->assertEquals([$eCultivationB['id'], $eCultivationA['id']], array_values(array_map('intval', $resolved['cCultivation']->getIds())), 'en phase, et à départ égal la plus récente d\'abord');
		$this->assertEquals(1, $resolved['more']);

		$resolved = CultivationResolveLib::resolve($this->getPlantRead(), NULL, TRUE);

		$this->assertEquals(3, $resolved['cCultivation']->count());
		$this->assertEquals(0, $resolved['more']);

		$resolved = CultivationResolveLib::resolve($this->getPlantRead(), $eCultivationC['id'], FALSE);

		$this->assertEquals($eCultivationC['id'], $resolved['eCultivation']['id']);

	}

	/**
	 * Une réponse qui ne désigne pas une candidate est ignorée : la question revient.
	 */
	public function testForeignAnswer(): void {

		$this->createCultivationInPhase(TRUE, 'Série A');
		$this->createCultivationInPhase(TRUE, 'Série B');

		$resolved = CultivationResolveLib::resolve($this->getPlantRead(), 1, FALSE);

		$this->assertTrue($resolved['eCultivation']->empty());

	}

	/**
	 * Sans semaine de départ, une culture n'est jamais en phase, mais elle reste candidate.
	 */
	public function testWithoutStartWeek(): void {

		$eCultivation = $this->createCultivation($this->createSeries(), $this->getPlant());

		$eCultivation['startWeek'] = NULL;
		$eCultivation['harvestWeeksExpected'] = NULL;

		\series\Cultivation::model()
			->select(['startWeek', 'harvestWeeksExpected'])
			->update($eCultivation);

		$ePlant = $this->getPlantRead();

		$this->assertEquals(0, $ePlant['inPhase']);
		$this->assertEquals($eCultivation['id'], CultivationResolveLib::resolve($ePlant, NULL, FALSE)['eCultivation']['id']);

	}

	/**
	 * Les semaines se comparent en chaînes : une récolte qui déborde sur l'année suivante garde la
	 * culture en phase en janvier.
	 */
	public function testPhaseAcrossYears(): void {

		$eCultivation = new \series\Cultivation([
			'season' => 2026,
			'startWeek' => 40,
			'harvestWeeks' => NULL,
			'harvestWeeksExpected' => ['2026-W50', '2027-W02'],
		]);

		$this->assertTrue(CultivationResolveLib::isInHarvest($eCultivation, '2027-W03'));
		$this->assertTrue(CultivationResolveLib::isInHarvest($eCultivation, '2026-W49'));
		$this->assertFalse(CultivationResolveLib::isInHarvest($eCultivation, '2027-W07'));
		$this->assertTrue(CultivationResolveLib::isInCultivation($eCultivation, '2026-W39'));
		$this->assertFalse(CultivationResolveLib::isInCultivation($eCultivation, '2026-W37'));

	}

	/**
	 * La fenêtre de récolte part de la première semaine de récolte, pas du départ : une carotte de la saison
	 * suivante semée en s41 est en terre dès son semis, mais ne se récolte qu'au printemps (20/10).
	 */
	public function testHarvestAfterSowing(): void {

		$eCultivation = new \series\Cultivation([
			'season' => 2027,
			'startWeek' => -59,
			'harvestWeeks' => NULL,
			'harvestWeeksExpected' => ['2027-W18', '2027-W19', '2027-W20', '2027-W21'],
		]);

		$this->assertFalse(CultivationResolveLib::isInHarvest($eCultivation, '2026-W43'), 'pas de récolte au semis');
		$this->assertTrue(CultivationResolveLib::isInCultivation($eCultivation, '2026-W43'), 'en terre au semis');
		$this->assertFalse(CultivationResolveLib::isInHarvest($eCultivation, '2027-W16'));
		$this->assertTrue(CultivationResolveLib::isInHarvest($eCultivation, '2027-W17'));
		$this->assertTrue(CultivationResolveLib::isInHarvest($eCultivation, '2027-W24'));
		$this->assertFalse(CultivationResolveLib::isInHarvest($eCultivation, '2027-W25'));

	}

	/**
	 * Le cas sur la 7 : en octobre, une carotte de la saison suivante semée deux semaines plus tôt
	 * et récoltée au printemps n'est pas candidate à côté de la carotte de la saison en récolte, qui désigne
	 * sans question (« pourquoi il propose la récolte sur une série prévue pour 6 mois plus tard ? »).
	 */
	public function testNextSeasonSownNotHarvested(): void {

		$this->setClock($this->getSeason().'-10-20 10:00:00');

		$eCultivation = $this->createCultivationInPhase(TRUE, 'Carotte en récolte');

		$eCultivationNext = $this->createCultivationOfSeason('Carotte de printemps', $this->getSeason() + 1);

		// Sans `build()` : un semis de l'année d'avant sa saison s'écrit moins cent
		// (`\series\Cultivation::getStartWeek()`), ce qu'un formulaire écrit par sa liste de semaines.
		$eCultivationNext['startWeek'] = week_number(\Clock::week()) - 2 - 100;
		$eCultivationNext['startAction'] = \series\Cultivation::SOWING;
		$eCultivationNext['harvestWeeksExpected'] = [($this->getSeason() + 1).'-W18', ($this->getSeason() + 1).'-W21'];

		\series\Cultivation::model()
			->select(['startWeek', 'startAction', 'harvestWeeksExpected'])
			->update($eCultivationNext);

		$ePlant = $this->getPlantRead();

		$this->assertFalse($ePlant['cCultivation']->offsetExists($eCultivationNext['id']), 'la carotte de printemps n\'est pas candidate');
		$this->assertEquals($eCultivation['id'], CultivationResolveLib::resolve($ePlant, NULL, FALSE)['eCultivation']['id']);

	}

	/**
	 * Une série close ne propose pas ses cultures.
	 */
	public function testClosedSeries(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$this->closeSeries($eCultivation['series']);

		$cPlant = CultivationResolveLib::getPlants($this->eStructure, \Clock::date());

		$this->assertFalse($cPlant->offsetExists($this->getPlant()['id']));

	}

	/**
	 * Piste C : de deux cultures en phase, celle qui a une récolte à faire dans la semaine est retenue
	 * sans question.
	 */
	public function testTargetDesignates(): void {

		$this->setClockInSeason();

		$this->createCultivationInPhase(TRUE, 'Série A');
		$eCultivation = $this->createCultivationInPhase(TRUE, 'Série B');
		$eTask = $this->createTaskTodo($eCultivation, '+0 days');

		$ePlant = $this->getPlantRead();
		$resolved = CultivationResolveLib::resolve($ePlant, NULL, FALSE);

		$this->assertEquals($eCultivation['id'], $resolved['eCultivation']['id']);
		$this->assertEquals($eTask['id'], $resolved['eCultivation']['eTaskTarget']['id']);
		$this->assertTrue($resolved['eCultivation']['eTaskDelayed']->empty(), 'une récolte de la semaine n\'est pas en retard');

	}

	/**
	 * Une récolte terminée dans la semaine garde sa culture parmi celles qui désignent (architecte S2e,
	 * point 5, la betterave de la 1786) : avec une autre en retard, la question porte sur les deux, et
	 * « Toutes les séries » ajoute la troisième, sans récolte.
	 */
	public function testTargetsAsk(): void {

		$this->setClockInSeason();

		$eCultivationDone = $this->createCultivationInPhase(TRUE, 'Série de conservation');
		$this->createTaskHarvest($eCultivationDone);

		$eCultivationDelayed = $this->createCultivationInPhase(FALSE, 'Série botte d\'été');
		$eTaskDelayed = $this->createTaskTodo($eCultivationDelayed, '-28 days');

		$this->createCultivationInPhase(TRUE, 'Série sans récolte');

		$resolved = CultivationResolveLib::resolve($this->getPlantRead(), NULL, FALSE);

		$this->assertTrue($resolved['eCultivation']->empty());
		$this->assertTrue($resolved['cCultivation']->offsetExists($eCultivationDone['id']) and $resolved['cCultivation']->offsetExists($eCultivationDelayed['id']), 'la question porte sur les deux récoltes');
		$this->assertEquals(2, $resolved['cCultivation']->count());
		$this->assertEquals(1, $resolved['more']);

		$this->assertTrue($resolved['cCultivation'][$eCultivationDone['id']]['eTaskDelayed']->empty(), 'la récolte faite n\'est pas en retard');
		$this->assertEquals($eTaskDelayed['id'], $resolved['cCultivation'][$eCultivationDelayed['id']]['eTaskDelayed']['id'], 'la récolte en retard l\'est');

		$this->assertEquals(3, CultivationResolveLib::resolve($this->getPlantRead(), NULL, TRUE)['cCultivation']->count());

	}

	/**
	 * Une culture de la saison d'avant, hors phase, est candidate quand elle a une récolte en retard,
	 * et désigne seule parmi les cultures en phase sans récolte : l'ail 2025 de la 662.
	 */
	public function testOtherSeasonTarget(): void {

		$this->setClockInSeason();

		$this->createCultivationInPhase(TRUE, 'Ail A');
		$this->createCultivationInPhase(TRUE, 'Ail B');
		$this->createCultivationInPhase(TRUE, 'Ail C');

		$eCultivation = $this->createCultivationPreviousSeason('Ail de la saison d\'avant');
		$eTask = $this->createTaskTodo($eCultivation, '-21 days');

		$resolved = CultivationResolveLib::resolve($this->getPlantRead(), NULL, FALSE);

		$this->assertEquals($eCultivation['id'], $resolved['eCultivation']['id']);
		$this->assertEquals($eTask['id'], $resolved['eCultivation']['eTaskDelayed']['id']);

	}

	/**
	 * Sans récolte, une culture de la saison d'avant et hors phase reste hors des candidates.
	 */
	public function testOtherSeasonWithoutTarget(): void {

		$this->setClockInSeason();

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$eCultivationPrevious = $this->createCultivationPreviousSeason('Série de la saison d\'avant');

		$ePlant = $this->getPlantRead();

		$this->assertFalse($ePlant['cCultivation']->offsetExists($eCultivationPrevious['id']));
		$this->assertEquals($eCultivation['id'], CultivationResolveLib::resolve($ePlant, NULL, FALSE)['eCultivation']['id']);

	}

	/**
	 * S2d : une culture dit sa saison quand sa série n'est pas de la saison de la date (26/09).
	 * Celle de la saison en cours ne la dit pas, celle de la saison d'avant la dit.
	 */
	public function testSeasonPrevious(): void {

		$this->setClockInSeason();

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$eCultivationPrevious = $this->createCultivationPreviousSeason('Série de la saison d\'avant');
		$this->createTaskTodo($eCultivationPrevious, '-21 days');

		$cCultivation = $this->getPlantRead()['cCultivation'];

		if($this->assertDecorPresent($cCultivation->offsetExists($eCultivationPrevious['id']), 'la culture de la saison d\'avant est candidate') === FALSE) {
			return;
		}

		$this->assertFalse($cCultivation[$eCultivation['id']]['nameWithSeason'], 'une culture de la saison de la date tait sa saison');
		$this->assertTrue($cCultivation[$eCultivationPrevious['id']]['nameWithSeason'], 'une culture de la saison d\'avant dit sa saison');

	}

	/**
	 * Une culture de la saison suivante en phase, semée l'automne d'avant comme la fève de la 178, dit
	 * sa saison.
	 */
	public function testSeasonNext(): void {

		$this->setClockInSeason();

		$this->createCultivationInPhase(TRUE);
		$eCultivationNext = $this->createCultivationNextSeasonInPhase('Série de la saison suivante');

		$cCultivation = $this->getPlantRead()['cCultivation'];

		if($this->assertDecorPresent($cCultivation->offsetExists($eCultivationNext['id']), 'la culture de la saison suivante est candidate') === FALSE) {
			return;
		}

		$this->assertTrue($cCultivation[$eCultivationNext['id']]['nameWithSeason'], 'une culture de la saison suivante dit sa saison');

	}

	/**
	 * La saison de référence est celle de la date dite, pas celle d'aujourd'hui : le lundi de la première
	 * semaine ISO d'une saison, « hier » est le dernier jour de la saison d'avant. La lecture se refait à la
	 * date dite (`EntryLib::read()`), et la propriété suit ce second appel.
	 */
	public function testSeasonFollowsDate(): void {

		$season = $this->getSeason();

		$this->setClock(date('Y-m-d', strtotime(($season + 1).'-W01-1')).' 10:00:00');

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$readingToday = $this->read($this->getText());
		$readingYesterday = $this->read($this->getText('hier'));

		if(
			$this->assertDecorPresent($readingToday['cultivation']->is($eCultivation), 'la culture est retenue aujourd\'hui') === FALSE or
			$this->assertDecorPresent($readingYesterday['cultivation']->is($eCultivation), 'la culture est retenue hier') === FALSE
		) {
			return;
		}

		$this->assertEquals($season, week_year(toWeek($readingYesterday['date'])));
		$this->assertTrue($readingToday['cultivation']['nameWithSeason'], 'aujourd\'hui, une culture de la saison d\'avant dit sa saison');
		$this->assertFalse($readingYesterday['cultivation']['nameWithSeason'], 'hier, une culture de la saison de la date tait sa saison');

	}

	/**
	 * Chaque ligne de la question porte la propriété, et la saisie la calcule comme Consigner sur la même
	 * semaine (`\planning\HarvestWeekLib::get()`, `prepareNames()`).
	 */
	public function testSeasonQuestionAndConsigner(): void {

		$this->setClockInSeason();

		$eCultivation = $this->createCultivationInPhase(TRUE, 'Série de la saison');
		$this->createTaskTodo($eCultivation, '+0 days');
		$eCultivationNext = $this->createCultivationNextSeasonInPhase('Série de la saison suivante');
		$this->createTaskTodo($eCultivationNext, '+0 days');

		$reading = $this->read($this->getText());

		if($this->assertDecorPresent($reading['question'] !== NULL, 'la question porte sur les deux cultures') === FALSE) {
			return;
		}

		$cCultivationQuestion = $reading['question']['cCultivation'];

		$this->assertFalse($cCultivationQuestion[$eCultivation['id']]['nameWithSeason']);
		$this->assertTrue($cCultivationQuestion[$eCultivationNext['id']]['nameWithSeason']);

		$cCultivationConsigner = \planning\HarvestWeekLib::get($this->eStructure, \Clock::week());

		foreach([$eCultivation, $eCultivationNext] as $eCultivationCompared) {

			if($this->assertDecorPresent($cCultivationConsigner->offsetExists($eCultivationCompared['id']), 'Consigner montre la culture '.$eCultivationCompared['id']) === FALSE) {
				return;
			}

			$this->assertEquals($cCultivationConsigner[$eCultivationCompared['id']]['nameWithSeason'], $cCultivationQuestion[$eCultivationCompared['id']]['nameWithSeason']);

		}

	}

	/**
	 * S2f : une saison dite borne les candidates à ses cultures, même hors phase et sans récolte
	 * (17/10). Sans elle, cette culture de la saison d'avant n'est pas candidate
	 * (`testOtherSeasonWithoutTarget()`).
	 */
	public function testSeasonSaid(): void {

		$this->setClockInSeason();

		$this->createCultivationInPhase(TRUE);
		$eCultivationPrevious = $this->createCultivationPreviousSeason('Série de la saison d\'avant');

		$reading = $this->read($this->getText(($this->getSeason() - 1).' 12 '));

		$this->assertTrue($reading['cultivation']->is($eCultivationPrevious), 'la saison dite retient sa culture');
		$this->assertEquals(12.0, $reading['complements']['quantity'], 'l\'année dite n\'est pas une quantité');
		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);

	}

	/**
	 * La saison dite borne la question à ses cultures : la culture de la saison d'avant, que sa récolte
	 * retiendrait seule sans saison dite, sort des candidates. Une réponse hors de la saison dite est
	 * ignorée, et la question revient.
	 */
	public function testSeasonSaidBoundsQuestion(): void {

		$this->setClockInSeason();

		$eCultivationA = $this->createCultivationInPhase(TRUE, 'Série A');
		$eCultivationB = $this->createCultivationInPhase(TRUE, 'Série B');
		$eCultivationPrevious = $this->createCultivationPreviousSeason('Série de la saison d\'avant');
		$this->createTaskTodo($eCultivationPrevious, '-21 days');

		$reading = $this->read($this->getText());

		if($this->assertDecorPresent($reading['cultivation']->is($eCultivationPrevious), 'sans saison dite, la récolte de la saison d\'avant désigne sa culture') === FALSE) {
			return;
		}

		$reading = $this->read($this->getText($this->getSeason().' '));

		if($this->assertDecorPresent($reading['question'] !== NULL, 'la saison dite pose la question') === FALSE) {
			return;
		}

		$ids = $reading['question']['cCultivation']->getIds();
		sort($ids);

		$this->assertEquals([$eCultivationA['id'], $eCultivationB['id']], $ids, 'la question ne porte que sur la saison dite');
		$this->assertEquals(0, $reading['question']['more']);

		$reading = $this->read($this->getText($this->getSeason().' '), ['cultivation' => $eCultivationPrevious['id']]);

		$this->assertTrue($reading['cultivation']->empty(), 'une réponse hors de la saison dite est ignorée');
		$this->assertTrue($reading['question'] !== NULL, 'la question revient');

	}

	/**
	 * Une saison dite où l'espèce n'a aucune culture est refusée, et rien ne s'écrit : écrire l'année en
	 * quantité est ce que la lecture de la saison corrige.
	 */
	public function testSeasonAbsent(): void {

		$this->setClockInSeason();

		$this->createCultivationInPhase(TRUE);

		$season = $this->getSeason() + 1;

		foreach([$season.' ', $season.' 12 '] as $rest) {

			$reading = $this->read($this->getText($rest));

			$this->assertEquals('season', $reading['refusal']['reason'], '« '.$rest.' » est refusé');
			$this->assertEquals($this->getPlant()['id'], $reading['refusal']['object']['id'], '« '.$rest.' » nomme l\'espèce');
			$this->assertEquals($season, $reading['refusal']['value'], '« '.$rest.' » dit la saison refusée');
			$this->assertTrue($reading['outcome'] !== EntryLib::WRITE, '« '.$rest.' » n\'écrit pas');

		}

	}

	/**
	 * Une année à deux saisons de celle du jour dit est comprise comme une saison, et refusée parce
	 * qu'aucune récolte ne s'y écrit, plutôt qu'écrite en quantité, « ail 2024 » en 2 024 pièces.
	 */
	public function testSeasonOutsideDate(): void {

		$this->setClockInSeason();

		$this->createCultivationInPhase(TRUE);

		foreach([$this->getSeason() - 2, $this->getSeason() + 2] as $season) {

			$reading = $this->read($this->getText($season.' '));

			$this->assertEquals(['reason' => 'seasonDate', 'value' => $season], $reading['refusal'], $season.' est refusée par le jour');
			$this->assertEquals(NULL, $reading['complements']['quantity'], $season.' n\'est pas une quantité');

		}

	}

	/**
	 * Le mot d'unité en cours de frappe se lit aussi sur une espèce que seule la saison dite fait
	 * proposer, sa seule culture étant hors phase et sans récolte : « ail 2025 3 k » se compose en
	 * « 3 kg », comme « courgette 3 k ».
	 */
	public function testSeasonSaidTypingUnit(): void {

		$this->setClockInSeason();

		$eCultivationPrevious = $this->createCultivationPreviousSeason('Série de la saison d\'avant');

		$reading = $this->read('récolte '.$this->getPlant()['name'].' '.($this->getSeason() - 1).' 3 k');

		$this->assertEquals(NULL, $reading['refusal'], 'le mot en frappe n\'est pas refusé');
		$this->assertTrue($reading['cultivation']->is($eCultivationPrevious));
		$this->assertEquals(\series\Task::KG, $reading['complements']['unit']);

	}

	/**
	 * Les saisons que la lecture comprend sont celles des cultures lues aujourd'hui. Le lundi de la
	 * première semaine d'une saison, « hier » est de la saison d'avant, dont la fenêtre s'arrête un an
	 * plus tôt : la saison qui suit aujourd'hui n'y prend pas de récolte (`\series\Task::planned.season`),
	 * et le refus le dit, au lieu de dire que l'espèce n'a pas de série cette saison-là.
	 */
	public function testSeasonSaidOutsideDate(): void {

		$season = $this->getSeason();

		$this->setClock(date('Y-m-d', strtotime(($season + 1).'-W01-1')).' 10:00:00');

		$this->createCultivationInPhase(TRUE);

		$seasonSaid = $season + 2;

		$eCultivation = $this->createCultivationOfSeason('Série de la saison d\'après', $seasonSaid);

		$reading = $this->read($this->getText('hier '.$seasonSaid.' 12 '));

		$this->assertEquals(['reason' => 'seasonDate', 'value' => $seasonSaid], $reading['refusal'], 'la saison dite hors de la fenêtre du jour dit est refusée par le jour');
		$this->assertTrue($reading['outcome'] !== EntryLib::WRITE, 'rien ne s\'écrit');

		$reading = $this->read($this->getText($seasonSaid.' 12 '));

		$this->assertTrue($reading['cultivation']->is($eCultivation), 'aujourd\'hui, la même saison dite retient sa culture');

	}

	/**
	 * Le mot en cours de frappe rendu aux compléments repasse par tout ce qu'un complément porte sur la
	 * lecture : « hi », début de « hier », ne laisse pas une écriture prête au jour d'aujourd'hui, comme
	 * sur une espèce proposée sans saison.
	 */
	public function testSeasonSaidTypingDate(): void {

		$this->setClockInSeason();

		$this->createCultivationPreviousSeason('Série de la saison d\'avant');

		$reading = $this->read('récolte '.$this->getPlant()['name'].' '.($this->getSeason() - 1).' 3 hi');

		$this->assertEquals(date('Y-m-d', \Clock::shift('-1 DAY')), $reading['date'], 'le jour lu sur « hi » est celui de la lecture');

	}

}
?>
