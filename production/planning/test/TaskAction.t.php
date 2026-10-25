<?php
namespace planning;

/**
 * Le champ « Intervention » de « Nouvelle intervention » (retour 192, lot J de
 * R-Retours-0810) : une autocomplétion du cadriciel, qui cherche par `taskDay:queryAction` parmi les
 * actions actives de la structure, rangées sous leurs catégories comme l'était le menu. Chaque action
 * porte la catégorie sous laquelle elle est choisie, que le formulaire poste avec elle, et se dit récolte
 * ou non. La récolte y est toujours proposée (retour 293).
 *
 * Lancement : dev/test package=planning file=TaskAction
 */
class TaskActionTest extends HarvestFixtureTest {

	/**
	 * La semaine où se fige l'horloge des cas de « Fréquentes », loin de toute intervention de la structure du
	 * décor : seules comptent celles que le cas pose.
	 */
	const string FREQUENT_WEEK = '2037-W20';

	/**
	 * Sans mot, toute la liste du menu : chaque catégorie en intitulé, puis ses actions, chacune avec sa
	 * catégorie, et la récolte marquée.
	 */
	public function testAll(): void {

		// Une semaine sans intervention, où « Fréquentes » ne passe pas devant le menu (`testFrequent()`).
		$this->setClock(week_date_day(self::FREQUENT_WEEK, 4).' 10:00:00');

		$results = $this->getResults('');

		$expected = [];

		foreach(TaskDayLib::getCategoriesForCreate($this->eStructure) as $eCategory) {

			$expected[] = 'title '.$eCategory['name'];

			foreach($eCategory['cAction'] as $eAction) {
				$expected[] = 'value '.$eAction['id'].' '.$eCategory['id'].' '.(($eAction['fqn'] === ACTION_RECOLTE) ? 'harvest' : '-');
			}

		}

		$this->assertTrue(count($expected) > 2, 'la structure du décor n\'a pas d\'action à proposer');
		$this->assertEquals($expected, $results, 'sans mot, la recherche ne rend pas le menu entier rangé par catégorie');

		$eActionHarvest = $this->getAction(ACTION_RECOLTE);

		$this->assertTrue(in_array('value '.$eActionHarvest['id'].' '.$this->getCategoryCulture()['id'].' harvest', $results, TRUE), '« Nouvelle intervention » ne propose pas la récolte, ou ne la marque pas');

	}

	/**
	 * Sans mot, « Fréquentes » passe en tête (retour 257) : les cinq actions qui comptent le plus
	 * d'interventions faites ou à faire sur les trois dernières semaines, la semaine en cours comprise, puis
	 * par nom à égalité. Une intervention plus ancienne, ou à faire sans semaine, ne compte pas. Une action de
	 * deux catégories y prend celle où elle compte le plus. Les catégories suivent, inchangées.
	 */
	public function testFrequent(): void {

		$decor = $this->createFrequent();

		if($decor === []) {
			return;
		}

		[
			'harvest' => $eActionHarvest,
			'first' => $eActionFirst,
			'alpha' => $eActionAlpha,
			'zeta' => $eActionZeta,
			'twice' => $eActionTwice,
			'last' => $eActionLast,
			'cut' => $eActionCut,
			'other' => $eCategoryOther,
		] = $decor;

		$culture = $this->getCategoryCulture()['id'];

		$results = $this->getResults('');

		$this->assertEquals([
			'title '.s("Fréquentes"),
			'value '.$eActionHarvest['id'].' '.$culture.' harvest',
			'value '.$eActionFirst['id'].' '.$culture.' -',
			'value '.$eActionAlpha['id'].' '.$culture.' -',
			'value '.$eActionZeta['id'].' '.$culture.' -',
			'value '.$eActionTwice['id'].' '.$eCategoryOther['id'].' -',
		], array_slice($results, 0, 6), '« Fréquentes » ne range pas en tête les cinq actions les plus comptées des trois dernières semaines');

		$this->assertTrue(str_starts_with($results[6] ?? '', 'title ') and $results[6] !== 'title '.s("Fréquentes"), '« Fréquentes » porte plus de cinq actions, ou les catégories ne la suivent pas');
		$this->assertEquals(2, count(array_keys($results, 'value '.$eActionFirst['id'].' '.$culture.' -', TRUE)), 'une action fréquente ne reste pas aussi à sa place dans sa catégorie');
		$this->assertTrue(in_array('value '.$eActionCut['id'].' '.$culture.' -', array_slice($results, 0, 6), TRUE) === FALSE, 'la sixième action passe dans « Fréquentes »');

	}

	/**
	 * Les pastilles sous « Intervention » (piste F de R-CreationRapide) : les six actions les plus comptées,
	 * dans l'ordre de « Fréquentes », chacune avec la catégorie où elle compte le plus. Aucune quand le « + »
	 * d'un bloc pose l'action.
	 */
	public function testShortcuts(): void {

		$decor = $this->createFrequent();

		if($decor === []) {
			return;
		}

		[$data, , $action] = $this->callDay('create', [], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'le panneau est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$culture = $this->getCategoryCulture()['id'];

		$this->assertEquals([
			$decor['harvest']['id'].' '.$culture,
			$decor['first']['id'].' '.$culture,
			$decor['alpha']['id'].' '.$culture,
			$decor['zeta']['id'].' '.$culture,
			$decor['twice']['id'].' '.$decor['other']['id'],
			$decor['last']['id'].' '.$culture,
		], array_values($data->cActionShortcut->makeArray(fn(\work\Action $eAction) => $eAction['id'].' '.$eAction['eCategory']['id'])), 'les pastilles ne sont pas les six actions les plus comptées, avec leur meilleure catégorie');

		[$data, , $action] = $this->callDay('create', ['action' => $decor['first']['id']], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'le panneau ouvert sur une action est refusé : '.implode(', ', $this->getFails($action)))) {
			$this->assertEquals(0, $data->cActionShortcut->count(), 'le panneau ouvert sur une action propose des pastilles');
		}

	}

	/**
	 * Dès qu'on tape, la recherche est celle d'avant, sans « Fréquentes ».
	 */
	public function testFrequentTyped(): void {

		$decor = $this->createFrequent();

		if($decor === []) {
			return;
		}

		$results = $this->getResults($decor['word']);

		$this->assertTrue(in_array('value '.$decor['first']['id'].' '.$this->getCategoryCulture()['id'].' -', $results, TRUE), 'la recherche ne trouve pas l\'action du décor');
		$this->assertTrue(in_array('title '.s("Fréquentes"), $results, TRUE) === FALSE, 'une recherche tapée garde « Fréquentes »');

	}

	/**
	 * Sans intervention sur les trois dernières semaines, pas de « Fréquentes » : celles de la semaine d'avant
	 * la fenêtre ne comptent plus.
	 */
	public function testFrequentNone(): void {

		$decor = $this->createFrequent();

		if($decor === []) {
			return;
		}

		$this->setClock(week_date_day(toWeek(strtotime(self::FREQUENT_WEEK.' + 3 WEEK')), 4).' 10:00:00');

		$results = $this->getResults('');

		$this->assertTrue(str_starts_with($results[0] ?? '', 'title '), 'la liste ne s\'ouvre pas sur une catégorie');
		$this->assertTrue(in_array('title '.s("Fréquentes"), $results, TRUE) === FALSE, '« Fréquentes » paraît sans intervention sur les trois dernières semaines');

	}

	/**
	 * L'année d'avant compte aussi, de la semaine en cours aux deux suivantes, 52 semaines plus tôt (Vincent,
	 * R-Retours-1210 V62) : une intervention faite deux semaines après celle d'il y a un an compte, une faite
	 * trois semaines après ne compte pas, et les deux années s'additionnent.
	 */
	public function testFrequentYearBefore(): void {

		$this->setClock(week_date_day(self::FREQUENT_WEEK, 4).' 10:00:00');

		$eCategoryCulture = $this->getCategoryCulture();
		$culture = $eCategoryCulture['id'];
		$word = 'fy'.uniqid();

		$eActionIn = $this->createAction('Aaa '.$word, [$eCategoryCulture]);
		$eActionOut = $this->createAction('Bbb '.$word, [$eCategoryCulture]);
		$eActionBoth = $this->createAction('Zzz '.$word, [$eCategoryCulture]);

		$weekYearBefore = toWeek(strtotime(self::FREQUENT_WEEK.' - 52 WEEK'));

		$this->createTaskFrequent($eActionIn, $eCategoryCulture, \series\Task::DONE, toWeek(strtotime($weekYearBefore.' + 2 WEEK')));
		$this->createTaskFrequent($eActionOut, $eCategoryCulture, \series\Task::DONE, toWeek(strtotime($weekYearBefore.' + 3 WEEK')));
		$this->createTaskFrequent($eActionBoth, $eCategoryCulture, \series\Task::TODO, $weekYearBefore);
		$this->createTaskFrequent($eActionBoth, $eCategoryCulture, \series\Task::DONE, self::FREQUENT_WEEK);

		$results = $this->getResults('');

		$this->assertEquals([
			'title '.s("Fréquentes"),
			'value '.$eActionBoth['id'].' '.$culture.' -',
			'value '.$eActionIn['id'].' '.$culture.' -',
		], array_slice($results, 0, 3), '« Fréquentes » ne compte pas l\'année d\'avant, de la semaine en cours aux deux suivantes, en l\'additionnant à l\'année en cours');

		$this->assertTrue(str_starts_with($results[3] ?? '', 'title '), 'une intervention faite trois semaines après la semaine d\'il y a un an compte dans « Fréquentes »');

	}

	/**
	 * Une semaine 53 n'a pas toujours de semaine 53 l'année d'avant : 52 semaines plus tôt, la période part de la
	 * semaine 1 de la même année ISO.
	 */
	public function testFrequentYearBeforeWeek53(): void {

		$this->setClock(week_date_day('2037-W53', 4).' 10:00:00');

		$eCategoryCulture = $this->getCategoryCulture();
		$eAction = $this->createAction('Aaa fz'.uniqid(), [$eCategoryCulture]);

		$this->createTaskFrequent($eAction, $eCategoryCulture, \series\Task::DONE, '2037-W03');

		$this->assertEquals([
			'title '.s("Fréquentes"),
			'value '.$eAction['id'].' '.$eCategoryCulture['id'].' -',
		], array_slice($this->getResults(''), 0, 2), 'en semaine 53, « Fréquentes » ne compte pas les semaines 1 à 3 de l\'année d\'il y a 52 semaines');

	}

	/**
	 * Le décor de « Fréquentes », l'horloge figée sur `FREQUENT_WEEK` : sur la semaine en cours et les deux
	 * d'avant, la récolte compte 5 interventions, puis 4, 3 et 3 (égalité que le nom départage, la première
	 * comptant une intervention d'une catégorie qui ne la propose pas), 2 hors culture pour une action de deux
	 * catégories, puis 1 et 1 (seconde égalité, dont la suivante est coupée). Une intervention de la semaine
	 * d'avant la fenêtre et une à faire sans semaine s'ajoutent sans compter.
	 */
	private function createFrequent(): array {

		$this->setClock(week_date_day(self::FREQUENT_WEEK, 4).' 10:00:00');

		$eCategoryCulture = $this->getCategoryCulture();
		$eCategoryOther = \work\CategoryLib::getByStructure($this->eStructure)->find(fn(\work\Category $eCategory) => $eCategory['fqn'] !== CATEGORIE_CULTURE)->first();

		if($this->assertDecorPresent($eCategoryOther !== NULL, 'une seconde catégorie que la culture dans la structure du décor') === FALSE) {
			return [];
		}

		$word = 'fq'.uniqid();

		$decor = [
			'word' => $word,
			'harvest' => $this->getAction(ACTION_RECOLTE),
			'first' => $this->createAction('Mmm '.$word, [$eCategoryCulture]),
			'alpha' => $this->createAction('Aaa '.$word, [$eCategoryCulture]),
			'zeta' => $this->createAction('Zzz '.$word, [$eCategoryCulture]),
			'twice' => $this->createAction('Ddd '.$word, [$eCategoryCulture, $eCategoryOther]),
			'last' => $this->createAction('Bbb '.$word, [$eCategoryCulture]),
			'cut' => $this->createAction('Ccc '.$word, [$eCategoryCulture]),
			'other' => $eCategoryOther,
		];

		$week = self::FREQUENT_WEEK;
		$weekBefore = toWeek(strtotime($week.' - 1 WEEK'));
		$weekFirst = toWeek(strtotime($week.' - 2 WEEK'));
		$weekOut = toWeek(strtotime($week.' - 3 WEEK'));

		foreach([
			['harvest', $eCategoryCulture, [\series\Task::TODO => [$week, $week, $weekFirst], \series\Task::DONE => [$weekBefore, $weekFirst]]],
			['first', $eCategoryCulture, [\series\Task::TODO => [$week, $weekBefore], \series\Task::DONE => [$week, $weekFirst]]],
			['alpha', $eCategoryCulture, [\series\Task::DONE => [$week, $weekBefore]]],
			['alpha', $eCategoryOther, [\series\Task::TODO => [$weekFirst]]],
			['zeta', $eCategoryCulture, [\series\Task::TODO => [$week, $weekBefore, $weekFirst, NULL], \series\Task::DONE => [$weekOut]]],
			['twice', $eCategoryOther, [\series\Task::TODO => [$week], \series\Task::DONE => [$weekBefore]]],
			['last', $eCategoryCulture, [\series\Task::TODO => [$weekFirst, $weekOut, $weekOut]]],
			['cut', $eCategoryCulture, [\series\Task::DONE => [$week]]],
		] as [$key, $eCategory, $weeks]) {

			foreach($weeks as $status => $list) {
				foreach($list as $taskWeek) {
					$this->createTaskFrequent($decor[$key], $eCategory, $status, $taskWeek);
				}
			}

		}

		return $decor;

	}

	/**
	 * Une intervention du décor de « Fréquentes », à faire dans sa semaine prévue, ou faite dans sa semaine.
	 */
	private function createTaskFrequent(\work\Action $eAction, \work\Category $eCategory, string $status, ?string $week): void {

		$isCulture = $eCategory->is($this->getCategoryCulture());

		$eTask = new \series\Task([
			'structure' => $this->eStructure,
			'series' => $isCulture ? $this->getCultivation()['series'] : new \series\Series(),
			'cultivation' => $isCulture ? $this->getCultivation() : new \series\Cultivation(),
			'plant' => $isCulture ? $this->getCultivation()['plant'] : new \plant\Plant(),
			'action' => $eAction,
			'category' => $eCategory,
			'description' => $isCulture ? NULL : 'Intervention du décor',
			'status' => $status,
			'plannedWeek' => ($status === \series\Task::TODO) ? $week : NULL,
			'doneWeek' => ($status === \series\Task::DONE) ? $week : NULL,
			'doneDate' => ($status === \series\Task::DONE) ? week_date_day($week, 1) : NULL,
			'repeatMaster' => new \series\Repeat(),
		]);

		// Sans `build()`, qui vérifierait l'action, le statut et la semaine saisis : le décor les pose en clair
		// et le test ne poste rien, comme `createTaskIn()`.
		\series\TaskLib::create($eTask);

	}

	/**
	 * Un mot garde les seules actions dont le nom le porte, sans tenir compte des accents ni de la casse,
	 * et une catégorie sans action trouvée disparaît. Une action de deux catégories reste sous les deux.
	 */
	public function testFiltered(): void {

		$word = 'jq'.uniqid();

		$eCategoryOther = \work\CategoryLib::getByStructure($this->eStructure)->find(fn(\work\Category $eCategory) => $eCategory['fqn'] !== CATEGORIE_CULTURE)->first();

		if($this->assertDecorPresent($eCategoryOther !== NULL, 'une seconde catégorie que la culture dans la structure du décor') === FALSE) {
			return;
		}

		$eAction = $this->createAction('Ébouillantage '.$word, [$this->getCategoryCulture(), $eCategoryOther]);

		$expected = [];

		foreach(\work\CategoryLib::getByStructure($this->eStructure) as $eCategory) {

			if(in_array($eCategory['id'], [$this->getCategoryCulture()['id'], $eCategoryOther['id']], TRUE)) {
				$expected[] = 'title '.$eCategory['name'];
				$expected[] = 'value '.$eAction['id'].' '.$eCategory['id'].' -';
			}

		}

		$this->assertEquals($expected, $this->getResults('ebouillantage '.$word), 'un mot sans accent ne trouve pas l\'action accentuée sous ses deux catégories');
		$this->assertEquals($expected, $this->getResults('EBOUILL', suffix: $word), 'un début de mot en capitales ne trouve pas l\'action');
		$this->assertEquals([], $this->getResults('zz'.$word), 'un mot que rien ne porte rend des actions');

	}

	/**
	 * Le panneau « Nouvelle intervention » ouvert sans action : le champ est l'autocomplétion du cadriciel,
	 * qui demande la récolte.
	 */
	public function testPanelCreate(): void {

		[$data, , $action] = $this->callDay('create', [], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'le panneau est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$panel = new TaskCreateUi()->create($data->eStructure, $data->page, $data->week, $data->when, $data->weeks, $data->eTask, $data->eCategoryAction, $data->cActionShortcut, $data->date, $data->status, $data->days);

		$this->assertField($panel->body, ['id' => $this->eStructure['id'], 'week' => \Clock::week()]);
		$this->assertSeason($panel->body, \Clock::week());

		$input = $this->parse($panel->body)->querySelector('input[data-autocomplete-field="cultivation[]"]');

		$this->assertTrue($input?->hasAttribute('data-planning-task-exclude') === TRUE, '« Espèce ou série » propose encore ce qui est déjà choisi');

	}

	/**
	 * Le jour se choisit par huit pastilles, « Non défini » en tête puis les sept jours, comme le jour d'un
	 * rappel (R-Retours-0810, 226) : le jour posé, ou rien sans jour, part dans un champ caché.
	 */
	public function testPanelCreateDays(): void {

		$wednesday = week_date_day(\Clock::week(), 3);

		foreach(['sans jour' => [NULL, ''], 'mercredi' => [$wednesday, $wednesday]] as $case => [$date, $selected]) {

			[$data, , $action] = $this->callDay('create', ['status' => \series\Task::TODO] + (($date === NULL) ? [] : ['date' => $date]), 'GET');

			if($this->assertTrue($action instanceof \ViewAction, $case.' : le panneau est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
				continue;
			}

			$panel = new TaskCreateUi()->create($data->eStructure, $data->page, $data->week, $data->when, $data->weeks, $data->eTask, $data->eCategoryAction, $data->cActionShortcut, $data->date, $data->status, $data->days);
			$document = $this->parse($panel->body);

			$this->assertEquals($selected, $document->querySelector('input[type="hidden"][name="day"]')?->getAttribute('value'), $case.' : le champ caché ne porte pas le jour posé');

			$days = array_map(fn(\Dom\Element $node) => $node->getAttribute('data-planning-task-create-day'), iterator_to_array($document->querySelectorAll('.util-week-picker-days [data-planning-task-create-day]')));
			$this->assertEquals(array_merge([''], week_dates(\Clock::week())), $days, $case.' : les pastilles ne sont pas « Non défini » puis les sept jours');

		}

	}

	/**
	 * Une action posée par le « + » d'un bloc part en champ caché.
	 */
	public function testPanelCreateWithAction(): void {

		$eAction = $this->getAction(ACTION_PLANTATION);

		[$data, , $action] = $this->callDay('create', ['action' => $eAction['id']], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'le panneau est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$panel = new TaskCreateUi()->create($data->eStructure, $data->page, $data->week, $data->when, $data->weeks, $data->eTask, $data->eCategoryAction, $data->cActionShortcut, $data->date, $data->status, $data->days);

		$document = $this->parse($panel->body);

		$this->assertEquals((string)$eAction['id'], $document->querySelector('input[type="hidden"][name="action"]')?->getAttribute('value'), 'l\'action posée ne part plus en champ caché');

		$this->assertSeason($panel->body, \Clock::week());

	}

	/**
	 * Échap, liste ouverte, ne ferme que la liste ; le suivant ferme le panneau (arbitré par V-Planning), dans
	 * « Intervention » comme dans « Espèce ou série » (J-193), qui portent tous deux `data-planning-task-escape`
	 * (`assertField()`, `assertSeason()`). `PlanningTaskAction.escape()` tourne sur un champ simulé,
	 * `AutocompleteField` réduit à sa liste.
	 */
	public function testEscape(): void {

		$source = file_get_contents(LIME_DIRECTORY.'/production/planning/static/js/planning.js');

		$this->assertTrue(str_contains($source, "document.delegateEventListener('keyup', 'input[data-planning-task-escape]'"), 'Échap n\'est pas écouté sur les champs qui le demandent');

		$start = strpos($source, "\nclass PlanningTaskAction {");
		$end = ($start === FALSE) ? FALSE : strpos($source, "\n}\n", $start);

		if($this->assertDecorPresent($start !== FALSE and $end !== FALSE, 'planning.js déclare la classe PlanningTaskAction') === FALSE) {
			return;
		}

		// [touche, liste ouverte] => [liste ouverte après, panneau épargné]
		$cases = [
			'Échap, liste ouverte' => [['Escape', TRUE], [FALSE, TRUE]],
			'Échap, liste fermée' => [['Escape', FALSE], [FALSE, FALSE]],
			'une autre touche, liste ouverte' => [['a', TRUE], [TRUE, FALSE]],
		];

		$script = '
			const vm = require("vm");
			const context = vm.createContext({});
			vm.runInContext(`
				class AutocompleteField {
					static hasDropdown(input) { return input.open; }
					static remove(input) { input.open = false; }
				}
			` + '.json_encode(substr($source, $start, $end - $start + 2)).' + ";globalThis.PlanningTaskAction = PlanningTaskAction;", context);
			const cases = '.json_encode(array_values(array_map(fn($case) => $case[0], $cases))).';
			const shown = cases.map(([key, open]) => {
				const input = {open: open};
				let stopped = false;
				const e = {key: key, stopPropagation() { stopped = true; }};
				context.PlanningTaskAction.escape(input, e);
				return [input.open, stopped];
			});
			process.stdout.write(JSON.stringify(shown));
		';

		$process = proc_open(['node', '-'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
		fwrite($pipes[0], $script);
		fclose($pipes[0]);
		$output = stream_get_contents($pipes[1]);
		$errors = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		proc_close($process);

		$shown = json_decode($output, TRUE);

		if($this->assertTrue(is_array($shown), 'node rend son résultat : '.$output.$errors) === FALSE) {
			return;
		}

		foreach(array_keys($cases) as $position => $case) {
			$this->assertEquals($cases[$case][1], $shown[$position], $case.', [liste ouverte, panneau épargné]');
		}

	}

	/**
	 * Ce que « Espèce ou série » a déjà choisi part avec la recherche (`exclude[]`), une valeur par pastille, sans
	 * rien retirer de son corps. `PlanningTaskAction.exclude()` tourne sur des pastilles simulées.
	 */
	public function testExclude(): void {

		$source = file_get_contents(LIME_DIRECTORY.'/production/planning/static/js/planning.js');

		$this->assertTrue(str_contains($source, "document.delegateEventListener('autocompleteBeforeQuery', 'input[data-planning-task-exclude]'"), 'la recherche n\'est pas écoutée sur le champ qui écarte ce qui est choisi');

		$start = strpos($source, "\nclass PlanningTaskAction {");
		$end = ($start === FALSE) ? FALSE : strpos($source, "\n}\n", $start);

		if($this->assertDecorPresent($start !== FALSE and $end !== FALSE, 'planning.js déclare la classe PlanningTaskAction') === FALSE) {
			return;
		}

		[$shown, $output] = $this->runNode('
			const vm = require("vm");
			const context = vm.createContext({});
			vm.runInContext(`
				const chosen = {
					\'#items-full input[type="hidden"]\': [{value: "12"}, {value: "plant:7"}],
					\'#items-empty input[type="hidden"]\': []
				};
				function qsa(selector, callback) { chosen[selector].forEach(callback); }
			` + '.json_encode(substr($source, $start, $end - $start + 2)).' + ";globalThis.PlanningTaskAction = PlanningTaskAction;", context);
			const shown = ["items-full", "items-empty"].map(items => {
				const body = new URLSearchParams({id: "1", query: "chou"});
				context.PlanningTaskAction.exclude({dataset: {autocompleteItems: items}}, body);
				return [...body.entries()];
			});
			process.stdout.write(JSON.stringify(shown));
		');

		if($this->assertTrue(is_array($shown), 'node rend son résultat : '.$output) === FALSE) {
			return;
		}

		$this->assertEquals([['id', '1'], ['query', 'chou'], ['exclude[]', '12'], ['exclude[]', 'plant:7']], $shown[0], 'une série et une espèce choisies ne partent pas avec la recherche');
		$this->assertEquals([['id', '1'], ['query', 'chou']], $shown[1], 'un champ sans choix poste quelque chose à écarter');

	}

	/**
	 * Le panneau s'ouvre sans curseur dans « Intervention » et sans liste (retour 260) : un clic dans le champ ouvre
	 * la liste entière, les Fréquentes en tête. L'`onrender` du champ est celui du panneau rendu, et `AutocompleteField`
	 * (form.js) et `PlanningAutocomplete` (planning.js) tournent tels quels sur un champ simulé, qui ne reçoit
	 * `focusin` qu'en prenant le curseur, comme dans le navigateur ; la recherche est réduite à un compteur et la
	 * liste à un booléen.
	 */
	public function testAutocompleteOpen(): void {

		[$data, , $action] = $this->callDay('create', ['date' => \Clock::date()], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'le panneau est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$panel = new TaskCreateUi()->create($data->eStructure, $data->page, $data->week, $data->when, $data->weeks, $data->eTask, $data->eCategoryAction, $data->cActionShortcut, $data->date, $data->status, $data->days);
		$onrender = $this->parse($panel->body)->querySelector('input[data-autocomplete-field="action"]')?->getAttribute('onrender');

		if($this->assertTrue($onrender !== NULL, 'le champ « Intervention » n\'a pas d\'onrender') === FALSE) {
			return;
		}

		[$shown, $output] = $this->runAutocomplete('
			const opened = field("opened");
			(function() { '.$onrender.' }).call(opened);
			shown.open = [queries.length, document.activeElement === opened];

			opened.click();
			shown.click = [queries.length, document.activeElement === opened, opened.hasAttribute("data-autocomplete"), "opened" in AutocompleteField.clickListener];

			opened.open = true;
			opened.click();
			shown.clickListed = queries.length;

			opened.open = false;
			delete AutocompleteField.clickListener.opened;
			opened.click();
			shown.clickClosed = [queries.length, "opened" in AutocompleteField.clickListener];

			AutocompleteField.focus(opened);
			shown.quiet = String(AutocompleteField.focusQuiet);

			const away = field("away");
			PlanningAutocomplete.start(away);
			away.click();
			shown.clickAway = queries.length;
		');

		if($this->assertTrue(is_array($shown), 'node rend son résultat : '.$output) === FALSE) {
			return;
		}

		$this->assertEquals([0, FALSE], $shown['open'], 'à l\'ouverture du panneau, [recherches, curseur dans le champ], le curseur ne se pose pas dans « Intervention » (retour 260)');
		$this->assertEquals([1, TRUE, TRUE, TRUE], $shown['click'], 'un clic dans le champ, [recherches, curseur dans le champ, champ initialisé, fermeture au clic extérieur armée], les Fréquentes s\'ouvrent');
		$this->assertEquals(1, $shown['clickListed'], 'un clic dans le champ, liste ouverte, la cherche de nouveau');
		$this->assertEquals([2, TRUE], $shown['clickClosed'], 'une liste fermée (Échap, choix), un clic dans le champ, [recherches, fermeture au clic extérieur armée]');
		$this->assertEquals('null', $shown['quiet'], 'un focus sur le champ qui a déjà le curseur, sans focusin, laisse la retenue posée et avale la recherche suivante');
		$this->assertEquals(3, $shown['clickAway'], 'un clic dans un champ inactif ne cherche pas une seule fois, la recherche du socle n\'étant pas rendue ou doublée');

	}

	/**
	 * Une réponse arrivée après la frappe, celle d'un texte qui n'est plus dans le champ, relance la recherche du
	 * texte tapé, que le socle avait refusée pendant qu'elle était en vol ; une réponse à jour, ou celle d'un champ
	 * quitté, ne relance rien. Le champ prend le curseur au clic, qui cherche les Fréquentes.
	 */
	public function testAutocompleteStale(): void {

		[$shown, $output] = $this->runAutocomplete('
			const stale = field("stale");
			PlanningAutocomplete.start(stale);
			stale.click();
			stale.value = "Bin";
			stale.dispatch("autocompleteSource", {detail: {query: ""}});
			shown.stale = queries.length;

			stale.dispatch("autocompleteSource", {detail: {query: "Bin"}});
			shown.current = queries.length;

			stale.blur();
			stale.value = "Bina";
			stale.dispatch("autocompleteSource", {detail: {query: "Bin"}});
			shown.left = queries.length;
		');

		if($this->assertTrue(is_array($shown), 'node rend son résultat : '.$output) === FALSE) {
			return;
		}

		$this->assertEquals(2, $shown['stale'], 'la réponse des Fréquentes, arrivée après « Bin », ne relance pas la recherche de « Bin »');
		$this->assertEquals(2, $shown['current'], 'la réponse de « Bin » relance une recherche');
		$this->assertEquals(2, $shown['left'], 'la réponse d\'un champ quitté relance une recherche');

	}

	/**
	 * Fait tourner `AutocompleteField` (form.js) et `PlanningAutocomplete` (planning.js) sur des champs simulés,
	 * puis `$script`, qui remplit `shown`.
	 */
	private function runAutocomplete(string $script): array {

		$sources = [];

		foreach([
			'AutocompleteField' => '/framework/util/static/js/form.js',
			'PlanningAutocomplete' => '/production/planning/static/js/planning.js',
		] as $class => $file) {

			$source = file_get_contents(LIME_DIRECTORY.$file);

			$start = strpos($source, "\nclass ".$class." {");
			$end = ($start === FALSE) ? FALSE : strpos($source, "\n}", $start + 1);

			if($this->assertDecorPresent($start !== FALSE and $end !== FALSE, $file.' déclare la classe '.$class) === FALSE) {
				return [NULL, ''];
			}

			$sources[] = substr($source, $start, $end - $start + 2).';';

		}

		return $this->runNode('
			const vm = require("vm");
			const context = vm.createContext({setTimeout: () => null});
			vm.runInContext(`
				const document = {
					activeElement: null,
					body: {matches: () => false},
					addEventListener() {}
				};
				function field(id) {
					const input = {id: id, value: "", dataset: {}, attributes: {}, listeners: {}, open: false};
					input.setAttribute = (name, value) => { input.attributes[name] = value; };
					input.hasAttribute = name => (name in input.attributes);
					input.setSelectionRange = () => null;
					input.addEventListener = (type, listener) => { (input.listeners[type] ??= []).push(listener); };
					input.dispatch = (type, event = {}) => (input.listeners[type] ?? []).forEach(listener => listener(event));
					input.focus = () => {
						if(document.activeElement !== input) {
							document.activeElement = input;
							input.dispatch("focusin");
						}
					};
					input.blur = () => {
						document.activeElement = null;
					};
					input.click = () => {
						input.dispatch("mousedown");
						input.focus();
					};
					return input;
				}
			` + '.json_encode(implode("\n", $sources)).' + `
				const queries = [];
				AutocompleteField.query = input => queries.push(input.id);
				AutocompleteField.hasDropdown = input => input.open;
				globalThis.context = {document, field, queries, AutocompleteField, PlanningAutocomplete};
			`, context);

			const {document, field, queries, AutocompleteField, PlanningAutocomplete} = context.context;
			const shown = {};
			'.$script.'
			process.stdout.write(JSON.stringify(shown));
		');

	}

	/**
	 * Le champ « Intervention » d'un panneau : l'autocomplétion de `taskDay:queryAction` et son corps, et le
	 * champ caché de la catégorie qu'elle remplit.
	 */
	private function assertField(string $body, array $expected): void {

		$document = $this->parse($body);

		$input = $document->querySelector('input[data-autocomplete-field="action"]');

		if($this->assertTrue($input !== NULL, 'le champ « Intervention » n\'est pas une autocomplétion') === FALSE) {
			return;
		}

		$this->assertEquals('/planning/taskDay:queryAction', $input->getAttribute('data-autocomplete-url'), 'l\'autocomplétion ne cherche pas les actions');
		$this->assertEquals($expected, json_decode($input->getAttribute('data-autocomplete-body'), TRUE), 'l\'autocomplétion ne poste pas son corps');
		$this->assertTrue($input->hasAttribute('data-planning-task-action'), 'l\'écouteur qui remplit la catégorie ne trouve pas le champ');
		$this->assertTrue($input->hasAttribute('data-planning-task-escape'), 'Échap ferme le panneau, liste ouverte, depuis « Intervention »');
		$this->assertEquals('PlanningAutocomplete.start(this);', $input->getAttribute('onrender'), 'le panneau pose le curseur dans « Intervention » à son ouverture (retour 260), ou ne démarre pas le champ');
		$this->assertTrue($document->querySelector('input[type="hidden"][name="category"]') !== NULL, 'la catégorie n\'a plus de champ caché');

	}

	/**
	 * Le champ « Espèce ou série » d'un panneau (J-193), multiple dans « Nouvelle intervention » : la rangée
	 * des saisons en tête de sa liste, la précédente, celle de la semaine, choisie d'office, et la suivante,
	 * et l'Échap en deux temps.
	 */
	private function assertSeason(string $body, string $week): void {

		$input = $this->parse($body)->querySelector('input[data-autocomplete-field="cultivation"], input[data-autocomplete-field="cultivation[]"]');

		if($this->assertTrue($input !== NULL, 'le champ « Espèce ou série » n\'est pas une autocomplétion') === FALSE) {
			return;
		}

		$season = week_year($week);

		$this->assertEquals([
			'name' => 'season',
			'label' => 'Saison',
			'values' => [
				[$season - 1, (string)($season - 1)],
				[$season, (string)$season],
				[$season + 1, (string)($season + 1)],
			],
		], json_decode((string)$input->getAttribute('data-autocomplete-head'), TRUE), 'la liste ne s\'ouvre pas sur les trois saisons autour de la semaine');
		$this->assertEquals((string)$season, $input->getAttribute('data-autocomplete-head-selected'), 'la saison de la semaine n\'est pas choisie d\'office');
		$this->assertTrue($input->hasAttribute('data-planning-task-escape'), 'Échap ferme le panneau, liste ouverte, depuis « Espèce ou série »');

	}

	/**
	 * Les résultats de la route, chacun en `title <catégorie>` ou `value <action> <catégorie> <harvest|->`.
	 */
	private function getResults(string $query, string $suffix = ''): array {

		[$data, , $action] = $this->callDay('queryAction', ['query' => $query]);

		if($this->assertTrue($action instanceof \ViewAction, 'la recherche est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return [];
		}

		$results = array_map(fn(array $result) => match($result['type'] ?? 'value') {
			'title' => 'title '.$result['itemText'],
			'value' => 'value '.$result['value'].' '.$result['category'].' '.($result['harvest'] ? 'harvest' : '-'),
		}, TaskCreateUi::getActionResults($data->cCategory, $data->cActionFrequent));

		if($suffix === '') {
			return $results;
		}

		// Un début de mot trouve aussi les actions de la structure : seules comptent celles du décor, et leur catégorie.
		$cActionDecor = \work\Action::model()
			->select('id')
			->whereStructure($this->eStructure)
			->whereName('LIKE', '%'.$suffix.'%')
			->getCollection(index: 'id');

		$kept = [];
		$title = NULL;

		foreach($results as $result) {

			if(str_starts_with($result, 'title ')) {
				$title = $result;
			} else if($cActionDecor->offsetExists((int)explode(' ', $result)[1])) {
				$kept[] = $title;
				$kept[] = $result;
			}

		}

		return $kept;

	}

	private function createAction(string $name, array $categories): \work\Action {

		$eAction = new \work\Action([
			'structure' => $this->eStructure,
		]);

		$fw = new \FailWatch();

		$eAction->build(\work\ActionLib::getPropertiesCreate(), [
			'name' => $name,
			'categories' => array_map(fn(\work\Category $eCategory) => $eCategory['id'], $categories),
			'color' => '#112233',
		]);

		$fw->validate();

		\work\ActionLib::create($eAction);

		return $eAction;

	}

	private function parse(string $html): \Dom\HTMLDocument {

		return \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NOERROR);

	}

}
?>
