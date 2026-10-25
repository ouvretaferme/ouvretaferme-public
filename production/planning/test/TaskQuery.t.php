<?php
namespace planning;

/**
 * La recherche « Espèce ou série » de « Nouvelle intervention » (lot J, retour de
 * du 05/10) : les séries dont la récolte est à moins de `PlanningSetting::TASK_QUERY_MONTHS` mois de
 * la semaine, rangées par distance, la saison choisie dans la rangée de la liste qui lève la fenêtre
 * (J-193), et les espèces dès l'ouverture de la liste (retour 243), chaque section avec le compte de ce que
 * sa coupe laisse de côté.
 *
 * Le décor est posé sur la structure hôte, qui porte ses propres séries : chaque cas cherche par un mot
 * propre à son espèce (`createPlantNamed()`), que rien d'autre ne porte, et la plafonnée des séries ne
 * coupe ainsi que le décor.
 *
 * Lancement : dev/test package=planning file=TaskQuery
 */
class TaskQueryTest extends HarvestFixtureTest {

	/**
	 * La fenêtre se compte de part et d'autre de la semaine affichée, bornes comprises. Une culture sans
	 * aucune semaine n'y entre pas, et les semaines récoltées comptent comme les semaines prévues.
	 */
	public function testWindow(): void {

		$week = \Clock::week();
		[$ePlant, $word] = $this->createPlantNamed();

		$weekFrom = toWeek(week_date_starts($week).' - '.PlanningSetting::TASK_QUERY_MONTHS.' months');
		$weekTo = toWeek(week_date_starts($week).' + '.PlanningSetting::TASK_QUERY_MONTHS.' months');

		$eCultivationFrom = $this->createCultivationWeeks($ePlant, 'S1', [$this->shiftWeek($weekFrom, -1), $weekFrom]);
		$eCultivationTo = $this->createCultivationWeeks($ePlant, 'S2', [$weekTo, $this->shiftWeek($weekTo, 1)]);
		$eCultivationBefore = $this->createCultivationWeeks($ePlant, 'S3', [$this->shiftWeek($weekFrom, -2), $this->shiftWeek($weekFrom, -1)]);
		$eCultivationAfter = $this->createCultivationWeeks($ePlant, 'S4', [$this->shiftWeek($weekTo, 1), $this->shiftWeek($weekTo, 2)]);
		$eCultivationNone = $this->createCultivation($this->createSeries('S5'), $ePlant);
		$eCultivationHarvested = $this->createCultivationWeeks($ePlant, 'S6', [$this->shiftWeek($weekTo, 4)], [$week]);

		[$cCultivation] = HarvestWeekLib::getForTaskQuery($this->eStructure, $week, $word, NULL);

		$this->assertTrue($cCultivation->offsetExists($eCultivationFrom['id']), 'une culture dont la récolte touche le début de la fenêtre n\'est pas proposée');
		$this->assertTrue($cCultivation->offsetExists($eCultivationTo['id']), 'une culture dont la récolte touche la fin de la fenêtre n\'est pas proposée');
		$this->assertFalse($cCultivation->offsetExists($eCultivationBefore['id']), 'une culture récoltée plus de '.PlanningSetting::TASK_QUERY_MONTHS.' mois avant la semaine est proposée');
		$this->assertFalse($cCultivation->offsetExists($eCultivationAfter['id']), 'une culture récoltée plus de '.PlanningSetting::TASK_QUERY_MONTHS.' mois après la semaine est proposée');
		$this->assertFalse($cCultivation->offsetExists($eCultivationNone['id']), 'une culture sans période de récolte est proposée');
		$this->assertTrue($cCultivation->offsetExists($eCultivationHarvested['id']), 'une culture récoltée cette semaine, prévue loin, n\'est pas proposée');

	}

	/**
	 * L'ordre : l'écart entre la semaine et la plus proche des semaines de récolte, nul dans la période. À écart
	 * égal, la récolte qui commence passe avant celle qui finit, puis l'espèce et la série.
	 */
	public function testOrder(): void {

		$week = \Clock::week();
		[$ePlant, $word] = $this->createPlantNamed();

		$eCultivationAfterTwo = $this->createCultivationWeeks($ePlant, 'S1', [$this->shiftWeek($week, 2), $this->shiftWeek($week, 3)]);
		$eCultivationBeforeOne = $this->createCultivationWeeks($ePlant, 'S2', [$this->shiftWeek($week, -2), $this->shiftWeek($week, -1)]);
		$eCultivationInsideB = $this->createCultivationWeeks($ePlant, 'S4', [$this->shiftWeek($week, -1), $week, $this->shiftWeek($week, 1)]);
		$eCultivationInsideA = $this->createCultivationWeeks($ePlant, 'S3', [$week]);
		$eCultivationAfterOne = $this->createCultivationWeeks($ePlant, 'S5', [$this->shiftWeek($week, 1)]);

		[$cCultivation] = HarvestWeekLib::getForTaskQuery($this->eStructure, $week, $word, NULL);

		$this->assertEquals([
			$eCultivationInsideA['id'],
			$eCultivationInsideB['id'],
			$eCultivationAfterOne['id'],
			$eCultivationBeforeOne['id'],
			$eCultivationAfterTwo['id'],
		], $cCultivation->getIds(), 'la liste ne range pas les séries par écart à la semaine, puis par nom');

	}

	/**
	 * Au plus `PlanningSetting::TASK_QUERY_SERIES` séries, les plus proches, et le compte de celles que la
	 * coupe laisse de côté.
	 */
	public function testCap(): void {

		$week = \Clock::week();
		[$ePlant, $word] = $this->createPlantNamed();

		$cultivationsNear = [];

		for($position = 1; $position <= PlanningSetting::TASK_QUERY_SERIES; $position++) {
			$cultivationsNear[] = $this->createCultivationWeeks($ePlant, 'S'.str_pad($position, 2, '0', STR_PAD_LEFT), [$week])['id'];
		}

		$eCultivationFar = $this->createCultivationWeeks($ePlant, 'S00', [$this->shiftWeek($week, 1)]);

		[$cCultivation, $others] = HarvestWeekLib::getForTaskQuery($this->eStructure, $week, $word, NULL);

		$this->assertEquals($cultivationsNear, $cCultivation->getIds(), 'la coupe ne garde pas les '.PlanningSetting::TASK_QUERY_SERIES.' séries les plus proches');
		$this->assertFalse($cCultivation->offsetExists($eCultivationFar['id']), 'une série plus lointaine passe la coupe, devant une plus proche par son nom');
		$this->assertEquals(1, $others, 'la série coupée n\'est pas comptée');

		$eCultivationFurther = $this->createCultivationWeeks($ePlant, 'S99', [$this->shiftWeek($week, 2)]);

		$this->assertEquals(2, HarvestWeekLib::getForTaskQuery($this->eStructure, $week, $word, NULL)[1], 'deux séries coupées ne sont pas comptées deux');
		$this->assertEquals(1, HarvestWeekLib::getForTaskQuery($this->eStructure, $week, $word, NULL, [$eCultivationFurther['id']])[1], 'une série déjà choisie est comptée parmi celles que la coupe laisse');

	}

	/**
	 * « Voir les N autres » (retour 382) relance la même recherche sans la coupe de sa section : toutes les séries,
	 * dans l'ordre de la coupe, puis le reste, et plus rien à compter. L'autre section garde sa coupe, et une
	 * valeur qui ne nomme pas une section n'ouvre rien.
	 */
	public function testMore(): void {

		$week = \Clock::week();
		[$ePlant, $word] = $this->createPlantNamed();

		$cultivations = [];

		for($position = 1; $position <= PlanningSetting::TASK_QUERY_SERIES + 2; $position++) {
			$cultivations[] = $this->createCultivationWeeks($ePlant, 'S'.str_pad($position, 2, '0', STR_PAD_LEFT), [$this->shiftWeek($week, intdiv($position, 3))])['id'];
		}

		$data = $this->getQuery($word, ['cultivation']);

		$this->assertEquals([$cultivations, 0], [$data->cCultivation->getIds(), $data->cultivationOthers], 'les séries ouvertes ne sont pas toutes rendues dans l\'ordre, ou restent comptées');

		$data = $this->getQuery($word, ['plant', 'series', '1']);

		$this->assertEquals([array_slice($cultivations, 0, PlanningSetting::TASK_QUERY_SERIES), 2], [$data->cCultivation->getIds(), $data->cultivationOthers], 'les séries sont ouvertes par une autre section, ou par une valeur qui n\'en nomme aucune');

		$plantsActive = \plant\Plant::model()
			->whereStructure($this->eStructure)
			->whereStatus(\plant\Plant::ACTIVE)
			->count();

		if($this->assertDecorPresent($plantsActive > PlanningSetting::TASK_QUERY_PLANTS, 'plus de '.PlanningSetting::TASK_QUERY_PLANTS.' espèces actives sur la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		$data = $this->getQuery('', ['plant']);

		$this->assertEquals([$plantsActive, 0], [$data->cPlant->count(), $data->plantOthers], 'les espèces ouvertes ne sont pas toutes rendues, ou restent comptées');

		$data = $this->getQuery('', ['plant'], ['plant:'.$data->cPlant->first()['id']]);

		$this->assertEquals($plantsActive - 1, $data->cPlant->count(), 'une espèce déjà choisie revient dans les espèces ouvertes');

	}

	/**
	 * Une année tapée, seule ou avec un nom, ne choisit pas la saison, que pose la rangée de la liste : elle
	 * sort du nom cherché, qui sans elle ne trouverait rien.
	 */
	public function testName(): void {

		$season = $this->getSeason();

		$this->assertEquals('', TaskCreateLib::readName((string)$season), 'une année seule reste dans le nom cherché');
		$this->assertEquals('fraise', TaskCreateLib::readName('fraise '.$season), 'une année après un nom reste dans le nom cherché');
		$this->assertEquals('fraise gariguette', TaskCreateLib::readName(' fraise  '.$season.' gariguette'), 'une année entre deux mots reste dans le nom cherché');
		$this->assertEquals('S12345', TaskCreateLib::readName('S12345'), 'quatre chiffres dans un mot sortent du nom cherché');
		$this->assertEquals('123', TaskCreateLib::readName('123'), 'trois chiffres sortent du nom cherché');

	}

	/**
	 * Une saison donnée lève la fenêtre et montre toute la saison, sans période en dernier.
	 */
	public function testSeason(): void {

		$season = $this->getSeason();

		$week = \Clock::week();
		[$ePlant, $word] = $this->createPlantNamed();

		$weekTo = toWeek(week_date_starts($week).' + '.PlanningSetting::TASK_QUERY_MONTHS.' months');

		$eCultivationNear = $this->createCultivationWeeks($ePlant, 'S1', [$week]);
		$eCultivationFar = $this->createCultivationWeeks($ePlant, 'S2', [$this->shiftWeek($weekTo, 1)]);
		$eCultivationNone = $this->createCultivation($this->createSeries('S0'), $ePlant);

		$this->assertFalse(HarvestWeekLib::getForTaskQuery($this->eStructure, $week, $word, NULL)[0]->offsetExists($eCultivationFar['id']), 'le décor ne prouve rien : la série lointaine est déjà proposée sans saison');

		[$cCultivation] = HarvestWeekLib::getForTaskQuery($this->eStructure, $week, $word, $season);

		$this->assertEquals([
			$eCultivationNear['id'],
			$eCultivationFar['id'],
			$eCultivationNone['id'],
		], $cCultivation->getIds(), 'la saison donnée ne montre pas toute la saison, rangée par écart, sans période en dernier');

		$this->assertEquals([], HarvestWeekLib::getForTaskQuery($this->eStructure, $week, $word, $season + 1)[0]->getIds(), 'une autre saison montre les séries de la saison du décor');

		$seasons = array_unique(HarvestWeekLib::getForTaskQuery($this->eStructure, $week, '', $season - 1)[0]->getColumnCollection('series')->getColumn('season'));

		$this->assertTrue(array_diff($seasons, [$season - 1]) === [], 'une saison sans nom montre d\'autres saisons : '.implode(', ', $seasons));

	}

	/**
	 * Les espèces, dès l'ouverture de la liste (retour 243) : sans rien de tapé, les premières, et le compte de toutes les autres espèces actives de la structure. Une lettre suffit à chercher.
	 */
	public function testPlants(): void {

		[$ePlant, $word] = $this->createPlantNamed();

		$plantsActive = \plant\Plant::model()
			->whereStructure($this->eStructure)
			->whereStatus(\plant\Plant::ACTIVE)
			->count();

		if($this->assertDecorPresent($plantsActive > PlanningSetting::TASK_QUERY_PLANTS, 'plus de '.PlanningSetting::TASK_QUERY_PLANTS.' espèces actives sur la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		[$cPlant, $others] = TaskCreateLib::getPlantsForQuery($this->eStructure, '');

		$this->assertEquals(PlanningSetting::TASK_QUERY_PLANTS, $cPlant->count(), 'une recherche vide ne propose pas les '.PlanningSetting::TASK_QUERY_PLANTS.' premières espèces');
		$this->assertEquals($plantsActive - PlanningSetting::TASK_QUERY_PLANTS, $others, 'une recherche vide ne compte pas les autres espèces de la structure');

		[$cPlant] = TaskCreateLib::getPlantsForQuery($this->eStructure, mb_substr($word, 0, 1));

		$this->assertTrue($cPlant->notEmpty(), 'une lettre ne propose aucune espèce');

		[$cPlant, $others] = TaskCreateLib::getPlantsForQuery($this->eStructure, $word);

		$this->assertEquals([[$ePlant['id']], 0], [$cPlant->getIds(), $others], 'l\'espèce cherchée par son nom n\'est pas proposée seule');

		[$cPlant, $others] = TaskCreateLib::getPlantsForQuery($this->eStructure, '', [$cPlant->first()['id']]);

		$this->assertEquals($plantsActive - 1 - PlanningSetting::TASK_QUERY_PLANTS, $others, 'une espèce déjà choisie est comptée parmi celles que la coupe laisse');

	}

	/**
	 * La réponse de la route (retour 243) : « Dans les séries », la rangée des saisons sous lui, les séries, puis
	 * « Hors série » et les espèces. Les deux sections sont toujours là, une section vide le disant sur sa ligne
	 * (contre-recette, points 13 et 14) : la rangée ne change pas de place quand une frappe ne trouve plus rien.
	 */
	public function testResponse(): void {

		$week = \Clock::week();
		[$ePlant, $word] = $this->createPlantNamed();

		$eCultivation = $this->createCultivationWeeks($ePlant, 'S1', [$week]);

		$this->assertEquals([
			'title Dans les séries',
			'head',
			'value '.$eCultivation['id'],
			'title Hors série',
			'value plant:'.$ePlant['id'],
		], $this->getResults($word), 'la réponse ne porte pas ses deux sections dans l\'ordre');

		$this->assertEquals([
			'title Dans les séries',
			'head',
			'disabled Aucune série',
			'title Hors série',
			'value plant:'.$ePlant['id'],
		], $this->getResults($word, $this->getSeason() + 1), 'une saison sans série perd l\'intitulé des séries, sa rangée, ou ne dit pas qu\'elle n\'en a aucune');

		$this->assertEquals([
			'title Dans les séries',
			'head',
			'disabled Aucune série',
			'title Hors série',
			'disabled Aucune espèce',
		], $this->getResults('zz'.$word), 'une recherche qui ne trouve rien perd une section, ou ne dit pas qu\'elle est vide');

	}

	/**
	 * Une section coupée finit par la ligne qui l'ouvre (`TaskDayUi::getAutocompleteMore()`, retour 382) : les
	 * séries sous les séries, les espèces sous les espèces. Une section entière n'en a pas.
	 */
	public function testResponseOthers(): void {

		$week = \Clock::week();
		[$ePlant, $word] = $this->createPlantNamed();

		$cultivations = [];

		for($position = 1; $position <= PlanningSetting::TASK_QUERY_SERIES + 2; $position++) {
			$cultivations[] = 'value '.$this->createCultivationWeeks($ePlant, 'S'.str_pad($position, 2, '0', STR_PAD_LEFT), [$week])['id'];
		}

		$results = $this->getResults($word);
		$resultsMore = array_splice($results, 2 + PlanningSetting::TASK_QUERY_SERIES, 1);

		$this->assertEquals('disabled', explode(' ', $resultsMore[0] ?? '')[0], 'les séries coupées ne finissent pas par la ligne qui les ouvre');
		$this->assertEquals(array_merge(
			['title Dans les séries', 'head'],
			array_slice($cultivations, 0, PlanningSetting::TASK_QUERY_SERIES),
			['title Hors série', 'value plant:'.$ePlant['id']]
		), $results, 'les séries coupées ne sont pas suivies de « Hors série », ou les espèces entières sont coupées');

		$plantsActive = \plant\Plant::model()
			->whereStructure($this->eStructure)
			->whereStatus(\plant\Plant::ACTIVE)
			->count();

		if($this->assertDecorPresent($plantsActive > PlanningSetting::TASK_QUERY_PLANTS, 'plus de '.PlanningSetting::TASK_QUERY_PLANTS.' espèces actives sur la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		$results = $this->getResults('');

		$this->assertEquals('disabled', explode(' ', end($results))[0], 'les espèces coupées ne sont pas comptées en fin de liste : '.end($results));

	}

	/**
	 * La saison choisie dans la rangée en tête de la liste (J-193) borne la recherche. Une autre que celle de
	 * la semaine montre ses séries, même loin de la semaine, et deux séries du même nom y sont proposées
	 * toutes deux (les deux « Chou fleur · s36 » de 2027 relevés). Dans la saison de la semaine,
	 * les séries proches restent en tête et celles de la saison suivent. Une année tapée ne choisit plus rien.
	 */
	public function testSeasonChosen(): void {

		$week = \Clock::week();
		$season = $this->getSeason();
		[$ePlant, $word] = $this->createPlantNamed();

		$weekTo = toWeek(week_date_starts($week).' + '.PlanningSetting::TASK_QUERY_MONTHS.' months');

		$eCultivationNear = $this->createCultivationWeeks($ePlant, 'S1', [$week]);
		$eCultivationFar = $this->createCultivationWeeks($ePlant, 'S2', [$this->shiftWeek($weekTo, 1)]);
		$eCultivationNextA = $this->createCultivationWeeks($ePlant, 'S36', [($season + 1).'-W36'], season: $season + 1);
		$eCultivationNextB = $this->createCultivationWeeks($ePlant, 'S36', [($season + 1).'-W36'], season: $season + 1);

		// Deux séries du même nom, à la même distance, n'ont pas d'ordre entre elles : seule leur présence compte.
		$results = $this->getResults($word, $season + 1);
		$resultsNext = array_splice($results, 2, 2);
		sort($resultsNext);

		$this->assertEquals([
			'title Dans les séries',
			'head',
			'title Hors série',
			'value plant:'.$ePlant['id'],
		], $results, 'la saison suivante choisie ne montre pas ses séries seules, sous leur intitulé');
		$this->assertEquals([
			'value '.$eCultivationNextA['id'],
			'value '.$eCultivationNextB['id'],
		], $resultsNext, 'la saison suivante choisie ne montre pas ses deux séries du même nom');

		$this->assertEquals([
			'title Dans les séries',
			'head',
			'value '.$eCultivationNear['id'],
			'value '.$eCultivationFar['id'],
			'title Hors série',
			'value plant:'.$ePlant['id'],
		], $this->getResults($word, $season), 'la saison de la semaine ne range pas les séries proches en tête, puis le reste de la saison');

		$this->assertEquals(
			$this->getResults($word, $season),
			$this->getResults($word.' '.($season + 1), $season),
			'une année tapée choisit encore la saison'
		);

	}

	/**
	 * Rien de ce que « Nouvelle intervention » a déjà choisi, série ou espèce, ne revient dans la liste
	 * (lot L de R-Retours-0810) : la série écartée l'est avant la coupe des séries, la suivante remonte et la
	 * liste reste pleine. Une valeur qui ne désigne rien n'écarte rien.
	 */
	public function testExclude(): void {

		$week = \Clock::week();
		[$ePlant, $word] = $this->createPlantNamed();

		$cultivationsNear = [];

		for($position = 1; $position <= PlanningSetting::TASK_QUERY_SERIES; $position++) {
			$cultivationsNear[] = $this->createCultivationWeeks($ePlant, 'S'.str_pad($position, 2, '0', STR_PAD_LEFT), [$week])['id'];
		}

		$eCultivationFar = $this->createCultivationWeeks($ePlant, 'S00', [$this->shiftWeek($week, 1)]);

		$expected = array_merge(
			['title Dans les séries', 'head'],
			array_map(fn(int $id) => 'value '.$id, array_slice($cultivationsNear, 1)),
			['value '.$eCultivationFar['id'], 'title Hors série', 'disabled Aucune espèce']
		);

		$this->assertEquals($expected, $this->getResults($word, exclude: [(string)$cultivationsNear[0], 'plant:'.$ePlant['id']]), 'une série et une espèce déjà choisies reviennent, ou la série suivante ne remonte pas sous la coupe');

		$this->assertEquals(
			$this->getResults($word),
			$this->getResults($word, exclude: ['', '0', 'plant:', 'jq']),
			'une valeur qui ne désigne rien écarte quelque chose'
		);

	}

	/**
	 * La recherche ne sert que les deux panneaux qui créent une intervention, comme celle des actions : au
	 * planning limité, qui ne les ouvre pas, toutes deux sont refusées.
	 */
	public function testQueryLimited(): void {

		\user\ConnectionLib::setOnline($this->getMemberPlanning());

		if($this->assertDecorPresent(\structure\StructureLib::getById($this->eStructure['id'])->canPlanningLimited() and \structure\StructureLib::getById($this->eStructure['id'])->canPlanning() === FALSE, 'Bruno au planning limité sur la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		[, , $action] = $this->callDay('query', ['query' => '']);

		$this->assertTrue($action instanceof \NotAllowedAction, 'le planning limité cherche les espèces et les séries');

		[, , $action] = $this->callDay('queryAction', ['query' => '']);

		$this->assertTrue($action instanceof \NotAllowedAction, 'le planning limité cherche les actions');

	}

	/**
	 * Une espèce choisie hors série donne une intervention hors série sur cette espèce, à côté d'une série
	 * choisie dans le même champ. Une espèce n'est pas offerte à une intervention d'une autre catégorie.
	 */
	public function testCreateWithPlant(): void {

		$ePlant = $this->createPlant();
		$eCultivation = $this->createCultivation($this->createSeries('S1'), $this->createPlant());

		[$data, , $action] = $this->callDay('doCreate', [
			'category' => $this->getCategoryCulture()['id'],
			'action' => $this->getAction(ACTION_PLANTATION)['id'],
			'day' => '',
			'description' => '',
			'cultivation' => [$eCultivation['id'], 'plant:'.$ePlant['id']],
		]);

		if($this->assertTrue($action instanceof \ViewAction, 'une série et une espèce choisies ensemble sont refusées : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$created = $data->cTaskCreated->makeArray(fn(\series\Task $eTask) => [$eTask['series']['id'] ?? NULL, $eTask['plant']['id']]);

		$this->assertEquals([
			[$eCultivation['series']['id'], $eCultivation['plant']['id']],
			[NULL, $ePlant['id']],
		], $created, 'l\'espèce choisie ne donne pas une intervention hors série sur elle');

		$eCategoryOther = \work\CategoryLib::getByStructure($this->eStructure, fqn: CATEGORIE_ADMINISTRATION);

		if($this->assertTrue($eCategoryOther->notEmpty(), 'la structure '.static::STRUCTURE.' a une catégorie d\'administration') === FALSE) {
			return;
		}

		[, , $action] = $this->callDay('doCreate', [
			'category' => $eCategoryOther['id'],
			'action' => $this->getAction(ACTION_PLANTATION)['id'],
			'day' => '',
			'description' => 'Classer',
			'cultivation' => ['plant:'.$ePlant['id']],
		]);

		$this->assertEquals(['Task::plant.category'], $this->getFails($action), 'une espèce choisie pour une autre catégorie que la culture n\'est pas refusée');

	}

	/**
	 * Les résultats de la route, chacun en `<type> <valeur>`, `<type> <texte>` pour un intitulé ou une ligne grise,
	 * `head` pour la rangée des saisons, et la saison choisie dans la rangée s'il y en a une, et ce que le
	 * champ a déjà choisi.
	 */
	private function getResults(string $query, ?int $season = NULL, array $exclude = []): array {

		$data = $this->getQuery($query, [], $exclude, $season);

		if($data === NULL) {
			return [];
		}

		return array_map(fn(array $result) => match($result['type'] ?? 'value') {
			'title' => 'title '.$result['itemText'],
			'head' => 'head',
			'disabled' => 'disabled '.$result['itemText'],
			'value' => 'value '.$result['value'],
		}, TaskDayUi::getAutocompleteResults($data->cCultivation, $data->cultivationOthers, $data->cPlant, $data->plantOthers));

	}

	/**
	 * Ce que la route prépare pour une recherche, avec les sections ouvertes par « Voir les N autres » (`more`),
	 * ou NULL si elle la refuse.
	 */
	private function getQuery(string $query, array $more = [], array $exclude = [], ?int $season = NULL): ?\stdClass {

		$input = ['query' => $query];

		if($more !== []) {
			$input['more'] = $more;
		}

		if($season !== NULL) {
			$input['season'] = $season;
		}

		if($exclude !== []) {
			$input['exclude'] = $exclude;
		}

		[$data, , $action] = $this->callDay('query', $input);

		if($this->assertTrue($action instanceof \ViewAction, 'la recherche est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return NULL;
		}

		return $data;

	}

	/**
	 * Une espèce dont le nom porte un mot que rien d'autre ne porte, et ce mot.
	 */
	private function createPlantNamed(): array {

		$ePlant = $this->createPlant();

		$word = 'jq'.uniqid();

		// Sans build(), qui vérifierait un nom saisi : le décor nomme son espèce d'un mot introuvable ailleurs.
		$ePlant['name'] = 'Décor '.$word;
		\plant\PlantLib::update($ePlant, ['name']);

		return [$ePlant, $word];

	}

	/**
	 * Une culture et ses semaines prévues, ses semaines récoltées s'il en faut, et une autre saison que celle
	 * du décor s'il en faut une.
	 */
	private function createCultivationWeeks(\plant\Plant $ePlant, string $series, array $weeks, array $weeksHarvested = [], ?int $season = NULL): \series\Cultivation {

		$eCultivation = $this->createCultivation($this->createSeries($series, $season), $ePlant);

		$eCultivation['harvestPeriodExpected'] = \series\Cultivation::WEEK;
		$eCultivation->build(['harvestWeeksExpected'], ['harvestWeeksExpected' => $weeks]);

		$properties = ['harvestPeriodExpected', 'harvestMonthsExpected', 'harvestWeeksExpected'];

		if($weeksHarvested !== []) {

			// Sans build() ni récolte saisie : les semaines récoltées se calculent des récoltes, que le cas n'a
			// pas besoin de poser pour éprouver qu'elles comptent dans la fenêtre.
			$eCultivation['harvestWeeks'] = $weeksHarvested;
			$properties[] = 'harvestWeeks';

		}

		\series\CultivationLib::update($eCultivation, $properties);

		return $eCultivation;

	}

	private function shiftWeek(string $week, int $weeks): string {

		return toWeek(week_date_starts($week).' '.($weeks >= 0 ? '+' : '-').' '.abs($weeks).' weeks');

	}

}
?>
