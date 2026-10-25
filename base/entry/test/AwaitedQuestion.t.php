<?php
namespace entry;

/**
 * La question attendue en tête de liste, les précisions dessous (HN-B8) : une fois l'objet résolu, la liste
 * redit en ligne muette ce que la phrase attend, au-dessus des puces qui le précisent, les unités d'une récolte
 * et les jours d'un temps. Une réponse choisie pose en jetons ce que la phrase a déjà dit : la lecture rend le
 * verbe tapé et l'espèce lue après l'action, chacun avec les mots qui l'ont dit, et leurs jetons se relisent
 * comme la phrase tapée.
 *
 * L'horloge est un jeudi de la saison du décor : un temps y propose lundi, mardi, mercredi et jeudi.
 */
class AwaitedQuestionTest extends EntryFixtureTest {

	private function setClockThursday(): string {

		$thursday = date('Y-m-d', strtotime('thursday this week', strtotime($this->getSeason().'-07-15')));

		$this->setClock($thursday.' 10:00:00');

		return $thursday;

	}

	// L'objet résolu, la liste ne propose plus d'objets : aucun à lui passer.
	private function getResults(array $reading): array {
		return EntryUi::getResults($this->eStructure, $reading, new \Collection(), EntryLib::findIntentions($this->eStructure, $reading['pending']));
	}

	private function getHead(string $group, string $label): array {
		return ['group' => $group, 'label' => $label, 'notice' => 'muted'];
	}

	/**
	 * Une intervention de fertilisation à faire cette semaine, sur une culture en phase.
	 */
	private function createTaskFertilisation(): \series\Task {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$eTask = new \series\Task([
			'structure' => $this->eStructure,
			'series' => $eCultivation['series'],
			'cultivation' => $eCultivation,
			'plant' => $eCultivation['plant'],
			'action' => $this->getAction(ACTION_FERTILISATION),
			'category' => $this->getCategoryCulture(),
			'description' => NULL,
			'status' => \series\Task::TODO,
			'plannedWeek' => \Clock::week(),
			'plannedDate' => \Clock::date(),
			'variety' => new \plant\Variety(),
			'harvestSize' => new \plant\Size(),
			'repeatMaster' => new \series\Repeat(),
		]);

		// Sans `build()` : le décor fixe telles quelles la semaine et le jour prévus, le statut et la catégorie,
		// que `build()` ne prend que d'un formulaire posté (`planned`, comme `TimeIntentionLib::buildTask()`).
		\series\TaskLib::create($eTask);

		return $eTask;

	}

	/**
	 * La série retenue, la liste pose la question de la quantité dans l'unité qui s'écrira, puis les unités en
	 * puces avant le nombre. Une puce choisie change l'unité, et la question avec elle.
	 */
	public function testHarvest(): void {

		$this->createCultivationInPhase(TRUE);

		$reading = $this->read($this->getText());

		$this->assertEquals('quantity', EntryUi::getStep($reading));

		$results = $this->getResults($reading);

		$this->assertEquals($this->getHead(HarvestIntentionUi::getLabel(), QuantityComplementUi::getPlaceholder(\series\Task::KG)), $results[0], 'la question en tête');
		$this->assertEquals(['unit:kg', 'unit:piece', 'unit:botte'], array_column(array_slice($results, 1), 'value'), 'les unités dessous');
		$this->assertEquals([TRUE, FALSE, FALSE], array_column(array_slice($results, 1), 'selected'));

		$reading = $this->read(UnitComplementLib::getWord(\series\Task::UNIT).' '.$this->getText());

		$this->assertEquals(QuantityComplementUi::getPlaceholder(\series\Task::UNIT), $this->getResults($reading)[0]['label'], 'la question suit l\'unité choisie');
		$this->assertEquals([FALSE, TRUE, FALSE], array_column($reading['units'], 'selected'));

	}

	/**
	 * La quantité dite, la ligne qui écrit prend la tête : la phrase n'attend plus rien. Une question de série
	 * n'a pas non plus de ligne muette, ses réponses sont la question.
	 */
	public function testHarvestWithoutHead(): void {

		$this->createCultivationInPhase(TRUE, 'Série A');

		$reading = $this->read($this->getText());

		$this->assertEquals('quantity', EntryUi::getStep($reading));

		$this->createCultivationInPhase(TRUE, 'Série B');

		$reading = $this->read($this->getText());

		$this->assertEquals('question', EntryUi::getStep($reading));
		$this->assertEquals([], array_filter($this->getResults($reading), fn(array $result) => isset($result['notice'])), 'la question de série');

		$reading = $this->read($this->getText('12 '), ['cultivation' => $reading['question']['cCultivation']->first()['id']]);

		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertEquals('write', $this->getResults($reading)[0]['value'], 'la ligne qui écrit');

	}

	/**
	 * L'intervention retenue, la liste pose la question de la durée, puis les jours de la semaine, qu'on choisit
	 * avant de la taper. Les jours retenus par les puces sont ceux que le champ garde en jetons.
	 */
	public function testTime(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$thursday = $this->setClockThursday();
		$this->createTaskFertilisation();

		[$monday] = week_dates(toWeek($thursday));

		// Sans durée ni verbe, la phrase se lirait en coche (`EntryLib::readImplicit()`).
		$text = 'temps '.$this->getAction(ACTION_FERTILISATION)['name'].' '.$this->getPlant()['name'].' ';

		$reading = $this->read($text);

		$this->assertEquals('duration', EntryUi::getStep($reading));

		$results = $this->getResults($reading);

		$this->assertEquals($this->getHead(TimeIntentionUi::getLabel(), TimeIntentionUi::getDurationPlaceholder()), $results[0], 'la question en tête');
		$this->assertEquals(4, count(array_slice($results, 1)), 'les jours dessous');
		$this->assertEquals([FALSE, FALSE, FALSE, TRUE], array_column(array_slice($results, 1), 'selected'), 'le jour dit choisi');

		$reading = $this->read($text, ['days' => [$monday]]);

		$this->assertEquals([$monday], EntryUi::getEntry($reading)['days'], 'le jour choisi avant la durée reste en jeton, à la place d\'aujourd\'hui');

		$reading = $this->read($text.'1 h ', ['days' => [$monday]]);

		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertEquals([$monday], $reading['confirmation']['dates'], 'et s\'écrit avec la durée');
		$this->assertEquals('write', $this->getResults($reading)[0]['value']);

	}

	/**
	 * Le verbe tapé est rendu avec les mots qui l'ont dit et le libellé de son jeton, qui se relit comme lui :
	 * « Temps » et non « Temps de travail », dont « travail » serait un mot que personne ne lit. Un verbe
	 * deviné n'a pas été tapé, la réponse ne le pose pas.
	 */
	public function testVerb(): void {

		$this->createCultivationInPhase(TRUE);

		$entry = EntryUi::getEntry($this->read($this->getText()));

		$this->assertEquals(['value' => 'harvest', 'label' => HarvestIntentionUi::getToken(), 'named' => 'recolte'], $entry['intention']);
		$this->assertEquals('harvest', $this->read($entry['intention']['label'].' '.$this->getPlant()['name'].' ')['intention'], 'le jeton se relit');

		$this->assertEquals(NULL, EntryUi::getEntry($this->read($this->getPlant()['name'].' '))['intention'], 'le verbe deviné');

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$this->createTaskFertilisation();

		$text = $this->getAction(ACTION_FERTILISATION)['name'].' '.$this->getPlant()['name'].' ';

		$entry = EntryUi::getEntry($this->read('temps '.$text));

		$this->assertEquals(['value' => 'time', 'label' => TimeIntentionUi::getToken(), 'named' => 'temps'], $entry['intention']);

		$reading = $this->read($entry['intention']['label'].' '.$text);

		$this->assertEquals('time', $reading['intention']);
		$this->assertEquals(NULL, $reading['pending'], 'aucun mot du jeton en trop');

	}

	/**
	 * Le jeton de chaque verbe se relit comme lui, qu'il vienne d'un geste, de la liste des verbes ou d'une réponse :
	 * la coche ne s'appelle pas « Intervention faite » dans la phrase, ni le temps « Temps de travail », ni la
	 * création « Nouvelle intervention ».
	 */
	public function testTokens(): void {

		foreach(['harvest', 'time', 'done', 'intervention'] as $intention) {

			$reading = $this->read(EntryUi::getIntentions()[$intention]::getToken().' ');

			$this->assertEquals($intention, $reading['intention'], $intention);
			$this->assertEquals(NULL, $reading['pending'], $intention);

		}

	}

	/**
	 * L'espèce lue après l'action est rendue avec les mots qui l'ont nommée, son début compris : le jeton qui les
	 * remplace porte son nom entier, et la phrase relue par les jetons pose la même question.
	 */
	public function testPlantAfterAction(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$this->createTaskFertilisation();

		$ePlant = $this->createPlant('Zzautre');
		$action = $this->getAction(ACTION_FERTILISATION)['name'];

		$typed = mb_substr($ePlant['name'], 0, -3);

		$reading = $this->read('temps '.$action.' '.$typed);

		$this->assertEquals('series', $reading['question']['name']);

		$entry = EntryUi::getEntry($reading);

		$this->assertEquals(['value' => $ePlant['id'], 'label' => $ePlant['name'], 'icon' => NULL, 'named' => implode(' ', EntryLib::split($typed))], $entry['plant']);

		$readingTokens = $this->read(implode(' ', [$entry['intention']['label'], $entry['object']['label'], $entry['plant']['label']]).' ');

		$this->assertEquals('series', $readingTokens['question']['name']);
		$this->assertEquals($ePlant['id'], $readingTokens['question']['ePlant']['id'], 'les jetons relisent la même phrase');

		$reading = $this->read('temps '.$action.' '.$ePlant['name'].' ', ['addNoSeries' => $ePlant['id']]);

		$this->assertEquals('duration', EntryUi::getStep($reading), 'la réponse choisie, la durée est attendue');
		$this->assertEquals(TimeIntentionUi::getDurationPlaceholder(), $this->getResults($reading)[0]['label']);

		$this->assertTrue(EntryUi::getEntry($this->read($this->getAction(ACTION_FERTILISATION)['name'].' '.$this->getPlant()['name'].' '))['plant'] === NULL, 'une intervention nommée avec son espèce n\'en lit pas après elle');

	}

}
?>
