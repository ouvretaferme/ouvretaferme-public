<?php
namespace entry;

/**
 * L'action d'abord, puis l'espèce (HN-B11) : les premières lettres d'une action de la structure la proposent
 * et la complètent, même sans intervention de la semaine. Choisie seule, elle demande l'espèce parmi celles de
 * la saison, celles qu'une intervention de l'action vise déjà en tête, et l'espèce mène aux séries où
 * l'ajouter au planning.
 */
class ActionTypedTest extends EntryFixtureTest {

	/**
	 * Une action de la catégorie donnée, au nom de lettres seules qui commence par `$prefix`, qu'aucune
	 * intervention de la structure ne porte encore.
	 */
	private function createAction(\work\Category $eCategory, string $prefix = 'Zzsem'): \work\Action {

		$eAction = new \work\Action([
			'name' => $prefix.substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 10),
			'structure' => $this->eStructure,
			'categories' => [$eCategory['id']],
		]);

		// Sans `build()` : le décor fixe le nom et la catégorie, que le formulaire d'action vérifierait.
		\work\ActionLib::create($eAction);

		return $eAction;

	}

	/**
	 * Une intervention de l'action sur la culture, à faire au jour donné.
	 */
	private function createTaskOfAction(\work\Action $eAction, \series\Cultivation $eCultivation, string $date): \series\Task {

		$eTask = new \series\Task([
			'structure' => $this->eStructure,
			'series' => $eCultivation['series'],
			'cultivation' => $eCultivation,
			'plant' => $eCultivation['plant'],
			'action' => $eAction,
			'category' => $this->getCategoryCulture(),
			'description' => NULL,
			'status' => \series\Task::TODO,
			'plannedWeek' => toWeek($date),
			'plannedDate' => $date,
			'variety' => new \plant\Variety(),
			'harvestSize' => new \plant\Size(),
			'repeatMaster' => new \series\Repeat(),
		]);

		// Sans `build()` : le décor fixe telles quelles la semaine et le jour prévus, le statut et la catégorie,
		// que `build()` ne prend que d'un formulaire posté (`planned`).
		\series\TaskLib::create($eTask);

		return $eTask;

	}

	/**
	 * La lecture et les objets que la liste montre, comme la page les prépare (`entry.p.php`).
	 */
	private function readShown(string $text): array {

		$cElement = new \Collection();
		$reading = EntryLib::read($this->eStructure, $text, cElement: $cElement);

		$cElementFound = EntryLib::findObjects($cElement, $reading['pending']);
		$completion = EntryLib::getCompletion($text, $reading, $cElementFound);

		$cElementShown = ($reading['intention'] === NULL) ?
			new \Collection() :
			EntryLib::getIntentions()[$reading['intention']]::getShown($this->eStructure, $reading, $cElementFound);

		return [$reading, $cElementShown, $completion];

	}

	/**
	 * Les premières lettres d'une action de culture sans intervention de la semaine la proposent en temps, et
	 * se complètent jusqu'à son nom. Le champ ouvert sans frappe ne la propose pas.
	 */
	public function testTyped(): void {

		$eAction = $this->createAction($this->getCategoryCulture());

		$text = mb_strtolower(mb_substr($eAction['name'], 0, 6));

		[$reading, $cElementShown, $completion] = $this->readShown($text);

		$this->assertEquals('time', $reading['intention'], 'le début du nom d\'une action se lit en temps');
		$this->assertTrue($reading['object']->empty(), 'l\'action n\'est pas encore nommée');
		$this->assertEquals([-$eAction['id']], $cElementShown->find(fn(\series\Task $eTaskObject) => $eTaskObject['action']['id'] === $eAction['id'])->getIds(), 'la liste propose l\'action seule');
		$this->assertEquals(['word' => $eAction['name'], 'suffix' => mb_strtolower(mb_substr($eAction['name'], 6))], $completion, 'la fin du nom se complète');

		[, $cElementShown] = $this->readShown('temps ');

		$this->assertFalse($cElementShown->offsetExists(-$eAction['id']), 'sans frappe, l\'action n\'est pas proposée');

	}

	/**
	 * Un début de nom qui s'écrit comme une liaison (« des », de Désherbage) propose l'action comme un autre.
	 */
	public function testTypedLink(): void {

		$eAction = $this->createAction($this->getCategoryCulture(), 'Deszz');

		[$reading, $cElementShown] = $this->readShown('des');

		$this->assertEquals('des', $reading['pending'], 'le mot en frappe n\'est pas lu comme une liaison');
		$this->assertTrue($cElementShown->offsetExists(-$eAction['id']), 'la liste propose l\'action');

	}

	/**
	 * Une action hors de la catégorie Culture, sans intervention de la semaine, ne mène nulle part : la frappe
	 * ne la propose pas.
	 */
	public function testTypedOutsideCulture(): void {

		$eCategory = \work\CategoryLib::getByStructure($this->eStructure, fqn: CATEGORIE_COMMERCIALISATION);

		if($this->assertTrue($eCategory->notEmpty(), 'la structure a une catégorie Commercialisation') === FALSE) {
			return;
		}

		$eAction = $this->createAction($eCategory);

		[$reading, $cElementShown] = $this->readShown('temps '.mb_strtolower(mb_substr($eAction['name'], 0, 6)));

		$this->assertFalse($cElementShown->offsetExists(-$eAction['id']));
		$this->assertEquals(['reason' => 'none', 'name' => $eAction['name']], $this->read($eAction['name'])['refusal'], 'nommée en entier, elle garde le refus');

	}

	/**
	 * L'action seule demande l'espèce parmi celles de la saison, rangées par nom, en temps comme en coche. Celles qu'une intervention de
	 * l'action vise dans la saison sont marquées, et la liste les range sous leur propre groupe, en tête.
	 */
	public function testPlantQuestion(): void {

		$eAction = $this->createAction($this->getCategoryCulture());

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$ePlantOther = $this->createPlant('Zzautre');
		$this->createCultivation($this->createSeries('Série B'), $ePlantOther);

		$reading = $this->read($eAction['name']);

		$this->assertEquals('time', $reading['intention']);
		$this->assertEquals('plant', $reading['question']['name'] ?? NULL, 'l\'action seule demande l\'espèce');

		$cPlant = $reading['question']['cPlant'];

		$this->assertTrue($cPlant->offsetExists($eCultivation['plant']['id']) and $cPlant->offsetExists($ePlantOther['id']), 'les espèces des cultures de la saison');
		$this->assertFalse($cPlant[$eCultivation['plant']['id']]['acted']);
		$this->assertEquals('plant', $this->read('fait '.$eAction['name'])['question']['name'] ?? NULL, 'la coche demande l\'espèce de même');
		$this->assertEquals(1, count(array_unique(array_column(TimeIntentionUi::getPlantResults($eAction, $cPlant), 'group'))), 'sans intervention de l\'action dans la saison, un seul groupe');

		// Six semaines plus tôt : dans la saison, au-delà des interventions en retard que le temps propose (HN-B7).
		$this->createTaskOfAction($eAction, $eCultivation, date('Y-m-d', \Clock::shift('-42 DAY')));

		$cPlant = $this->read($eAction['name'])['question']['cPlant'];

		$this->assertTrue($cPlant[$eCultivation['plant']['id']]['acted'], 'l\'espèce qu\'une intervention de l\'action vise');
		$this->assertFalse($cPlant[$ePlantOther['id']]['acted']);

		$results = TimeIntentionUi::getPlantResults($eAction, $cPlant);

		$this->assertEquals('plant:'.$eCultivation['plant']['id'], $results[0]['value'], 'elle vient en tête');
		$this->assertEquals(2, count(array_unique(array_column($results, 'group'))), 'deux groupes');

	}

	/**
	 * Le début de plusieurs noms tapé après l'action garde la question de l'espèce, bornée aux espèces de la
	 * saison qu'il lit, pour le temps comme pour la coche.
	 */
	public function testPlantPrefix(): void {

		$eAction = $this->createAction($this->getCategoryCulture());

		$ePlantFirst = $this->createPlant('Zzpréfixe un');
		$ePlantSecond = $this->createPlant('Zzpréfixe deux');
		$ePlantOther = $this->createPlant('Zzautre');

		$this->createCultivation($this->createSeries('Série A'), $ePlantFirst);
		$this->createCultivation($this->createSeries('Série B'), $ePlantSecond);
		$this->createCultivation($this->createSeries('Série C'), $ePlantOther);

		$expected = [$ePlantSecond['id'], $ePlantFirst['id']];

		foreach(['', 'fait '] as $verb) {

			$reading = $this->read($verb.$eAction['name'].' zzpréf');

			$this->assertEquals('plant', $reading['question']['name'] ?? NULL, $verb.'la question de l\'espèce reste posée');
			$this->assertEquals($expected, $reading['question']['cPlant']->getIds(), $verb.'bornée aux espèces que le début lit');
			$this->assertEquals('zzpref', $reading['pending'], $verb.'le début reste en attente, que l\'espèce choisie remplace');
			$this->assertEquals(NULL, $reading['refusal'], $verb.'sans refus');

		}

		// Une intervention de l'action cette semaine, sur une autre espèce, ne retire pas la question : une autre
		// espèce s'ajoute toujours au planning.
		$this->createTaskOfAction($eAction, $this->createCultivation($this->createSeries('Série D'), $ePlantOther), \Clock::date());

		foreach(['', 'fait '] as $verb) {
			$this->assertEquals($expected, $this->read($verb.$eAction['name'].' zzpréf')['question']['cPlant']->getIds(), $verb.'avec une intervention de la semaine');
		}

		$this->assertEquals(['zzprefixe', 'u'], $this->read('fait '.$eAction['name'].' zzpréfixe u')['plantNamed'], 'la coche nomme l\'espèce par ses mots, que le choix d\'une série retire du champ');

	}

	/**
	 * L'espèce choisie, que son jeton redit après l'action, mène aux séries où ajouter l'intervention.
	 */
	public function testPlantAnswered(): void {

		$eAction = $this->createAction($this->getCategoryCulture());
		$eCultivation = $this->createCultivationInPhase(TRUE);

		$reading = $this->read($eAction['name'].' '.$eCultivation['plant']['name']);

		$this->assertEquals('series', $reading['question']['name'] ?? NULL);
		$this->assertEquals($eCultivation['plant']['id'], $reading['question']['ePlant']['id']);
		$this->assertTrue($reading['question']['cCultivation']->offsetExists($eCultivation['id']));

	}

}
?>
