<?php
namespace series;

/**
 * La fiche d'une série sur le plan d'assolement (`series/place:sheet`) et ses gestes (`doHarvest`, `doCreate`), passés
 * par les routes comme le navigateur les appelle : sa donnée, qui est celle du plan 3D pour cette série, les gestes
 * réservés à une série en place cette année, ce qui s'écrit, ce qui se refuse, et la réponse, égale à ce que la
 * fiche rechargée en montre.
 *
 * Le décor est celui des gestes du planning (`\planning\HarvestFixtureTest`), qui pose l'heure le jeudi de la
 * semaine 39 et sait exécuter une page puis sa vue. La planche est neuve à chaque cas : elle ne porte que les
 * séries que la méthode y place.
 *
 * Lancement : dev/test package=series file=PlaceSheet
 */
class PlaceSheetTest extends \planning\HarvestFixtureTest {

	/**
	 * Une série a des gestes si sa planche la porte aujourd'hui, sur l'année en cours ; ni une série à venir, ni une
	 * série finie ou clôturée, ni une autre saison n'en ont.
	 */
	public function testGesturesOnlyInPlace(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eBed = $this->createBed();

		$eSeriesRunning = $this->createSeriesOn($eBed, 'En place', 30, 45);
		$eSeriesUpcoming = $this->createSeriesOn($eBed, 'À venir', 45, 50);
		$eSeriesFinished = $this->createSeriesOn($eBed, 'Terminée', 10, 20);
		$eSeriesClosed = $this->createSeriesOn($eBed, 'Clôturée', 30, 45);

		$this->closeSeries($eSeriesClosed);

		$inPlace = [];

		foreach([$eSeriesRunning, $eSeriesUpcoming, $eSeriesFinished, $eSeriesClosed] as $eSeries) {

			[$data] = $this->callSheet($eSeries);

			if($this->assertDecorPresent($data !== NULL, 'la fiche de la série '.$eSeries['name'].' se lit') === FALSE) {
				return;
			}

			$inPlace[$eSeries['name']] = [$data->inPlace, $data->cCultivation->count()];

		}

		$this->assertEquals([
			'En place' => [TRUE, 1],
			'À venir' => [FALSE, 0],
			'Terminée' => [FALSE, 0],
			'Clôturée' => [FALSE, 0],
		], $inPlace, 'les gestes et les cultures de « Récolter » ne suivent pas la place de la série');

		// Le bloc d'une saison voisine ouvre la fiche de sa série, sur la saison de la série
		$eSeriesPast = $this->createSeriesOn($eBed, 'Saison passée', 30, 45);

		// Sans `build()`, qui garde la saison d'une série dont les interventions l'ancrent : le cas veut une série de la
		// saison passée, quelles que soient ses interventions. `oldSeason` est ce que la vérification de `build()` pose
		// pour que `SeriesLib::update()` déplace aussi les cultures, les interventions et les places
		$eSeriesPast['oldSeason'] = $eSeriesPast['season'];
		$eSeriesPast['season'] = $this->getSeason() - 1;
		SeriesLib::update($eSeriesPast, ['season']);

		[$data] = $this->callSheet($eSeriesPast);

		if($this->assertDecorPresent($data !== NULL, 'la fiche de la série de la saison passée se lit') === FALSE) {
			return;
		}

		$this->assertEquals([$this->getSeason() - 1, FALSE], [$data->sheet['season'], $data->inPlace], 'la fiche d\'une série de la saison passée n\'est pas sur sa saison, ou a des gestes');

	}

	/**
	 * La donnée de la fiche est celle que le plan 3D lit pour la série : la même série, ses places, toutes ses
	 * interventions et ses récoltes, et les planches qu'elle occupe.
	 */
	public function testSheetIsTheScene(): void {

		if($this->requireDecor() === FALSE or $this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$eBed = $this->createBed();
		$eBedOther = $this->createBed();

		$eSeries = $this->createSeriesOn($eBed, 'En place', 30, 45);
		$eSeriesNeighbour = $this->createSeriesOn($eBed, 'Voisine', 30, 45);
		$this->createPlace($eSeries, $eBedOther, 10);

		$eCultivation = $this->getCultivationOf($eSeries);

		$this->createTask($eCultivation, ACTION_PLANTATION, Task::DONE, $this->getSeason().'-W30');
		$this->createTask($eCultivation, ACTION_PLANTATION, Task::TODO, $this->getSeason().'-W40');
		$this->createTask($this->getCultivationOf($eSeriesNeighbour), ACTION_PLANTATION, Task::TODO, \Clock::week());

		[, $action] = $this->callGesture('doHarvest', $this->getHarvestInput($eCultivation));

		if($this->assertTrue($action instanceof \ViewAction, 'la récolte du décor est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$sheet = SeriesLib::getSheet($this->eStructure, $eSeries, $this->getSeason());
		$scene = \map\SceneLib::getByStructure($this->eStructure, $this->getSeason());

		$bySeries = fn(array $rows, array $columns) => array_values(array_filter($rows, fn(array $row) => $row[array_search('series', $columns)] === $eSeries['id']));

		$this->assertEquals(array_values(array_filter($scene['series'], fn(array $series) => $series['id'] === $eSeries['id'])), $sheet['series'], 'la série de la fiche diffère de celle du plan 3D');
		$this->assertEquals(array_values(array_filter($scene['places'], fn(array $place) => $place['series'] === $eSeries['id'])), $sheet['places'], 'les places de la fiche diffèrent de celles du plan 3D');
		$this->assertEquals($bySeries($scene['tasks'], $scene['columns']['tasks']), $sheet['tasks'], 'les interventions de la fiche diffèrent de celles du plan 3D');
		$this->assertEquals($bySeries($scene['harvests'], $scene['columns']['harvests']), $sheet['harvests'], 'les récoltes de la fiche diffèrent de celles du plan 3D');
		$this->assertEquals([$scene['columns']['tasks'], $scene['columns']['harvests']], [$sheet['columns']['tasks'], $sheet['columns']['harvests']], 'les colonnes de la fiche diffèrent de celles du plan 3D');

		$this->assertEquals(3, count($sheet['tasks']), 'la fiche ne porte pas toutes les interventions de la série, faite, à venir et récolte');
		$this->assertEquals([$eBed['id'], $eBedOther['id']], array_column($sheet['beds'], 'id'), 'la fiche ne porte pas les deux planches de la série');

		$actions = array_column($sheet['actions'], 'id');

		foreach($sheet['tasks'] as $task) {
			$this->assertTrue(in_array($task[array_search('action', $sheet['columns']['tasks'])], $actions, TRUE), 'une intervention de la fiche a une action qu\'elle ne nomme pas');
		}

	}

	/**
	 * « Récolter » poste sur la fiche : la récolte s'écrit sur la culture, le panneau se ferme et la fiche seule se
	 * redessine, comme la fiche rechargée la montre.
	 */
	public function testHarvestRedrawsTheSheet(): void {

		if($this->requireDecor() === FALSE or $this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$eBed = $this->createBed();
		$eSeries = $this->createSeriesOn($eBed, 'En place', 30, 45);
		$eCultivation = $this->getCultivationOf($eSeries);

		[$instructions, $action] = $this->callGesture('doHarvest', $this->getHarvestInput($eCultivation));

		if($this->assertTrue($action instanceof \ViewAction, 'la récolte est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$harvested = (float)Task::model()
			->whereCultivation($eCultivation)
			->whereAction($this->getAction(ACTION_RECOLTE))
			->whereDoneWeek(\Clock::week())
			->getValue(new \Sql('SUM(harvest)', 'float'));

		$this->assertEquals(3.0, $harvested, 'la récolte saisie n\'est pas écrite sur la culture');
		$this->assertTrue(in_array('#panel-planning-harvest-create', $this->getClosedPanels($instructions), TRUE), 'le panneau de récolte reste ouvert');
		$this->assertEquals(['#place-sheet'], $this->getSelectors($instructions), 'la récolte redessine autre chose que la fiche');
		$this->assertSameAsReloaded($eSeries, $instructions, 'après la récolte');

		[$data] = $this->callSheet($eSeries);

		$this->assertEquals(3.0, (float)array_sum(array_column($data->sheet['harvests'], array_search('quantity', $data->sheet['columns']['harvests']))), 'la fiche ne porte pas la récolte saisie');

	}

	/**
	 * « Intervention » ouvre le panneau du planning rempli des cultures de la série, qui poste sur la fiche : l'intervention
	 * s'écrit faite aujourd'hui sur la culture, le panneau se ferme et la fiche se redessine, comme la fiche rechargée la
	 * montre.
	 */
	public function testCreateRedrawsTheSheet(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eBed = $this->createBed();
		$eSeries = $this->createSeriesOn($eBed, 'En place', 30, 45);
		$eCultivation = $this->getCultivationOf($eSeries);

		$action = $this->executePage('planning/taskWeek:create', ['id' => $this->eStructure['id'], 'week' => \Clock::week(), 'origin' => 'assolement', 'series' => $eSeries['id']], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'le panneau ne s\'ouvre pas depuis la fiche') === FALSE) {
			return;
		}

		$data = new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);

		$this->assertEquals('placeSheet', $data->page, 'le panneau ouvert depuis la fiche ne poste pas sur elle');
		$this->assertEquals([$eCultivation['id']], $data->eTask['cCultivationChosen']->getIds(), 'le panneau ne part pas rempli des cultures de la série');
		$this->assertEquals($eSeries['id'], $data->post['series'], 'le panneau ne réémet pas la série de la fiche');

		// Le panneau se rend : la culture choisie d'office passe par la valeur par défaut d'« Espèce ou série »
		$this->runView('create', $action, 'taskDay', 'planning');

		[$instructions, $action] = $this->callGesture('doCreate', $this->getCreateInput($eSeries, $eCultivation));

		if($this->assertTrue($action instanceof \ViewAction, 'l\'intervention est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$created = Task::model()
			->whereCultivation($eCultivation)
			->whereAction($this->getAction(ACTION_PLANTATION))
			->whereStatus(Task::DONE)
			->whereDoneDate(\Clock::date())
			->count();

		$this->assertEquals(1, $created, 'l\'intervention n\'est pas écrite faite aujourd\'hui sur la culture');
		$this->assertTrue(in_array('#panel-planning-task-create', $this->getClosedPanels($instructions), TRUE), 'le panneau d\'intervention reste ouvert');
		$this->assertEquals(['#place-sheet'], $this->getSelectors($instructions), 'l\'intervention redessine autre chose que la fiche');
		$this->assertSameAsReloaded($eSeries, $instructions, 'après l\'intervention');

	}

	/** Une série ou une culture d'une autre structure ne se lit ni ne s'écrit par les routes de la structure. */
	public function testForeignStructureRefused(): void {

		if($this->requireDecor() === FALSE or $this->requireStructureForeign() === FALSE) {
			return;
		}

		// Une culture d'une autre structure, empruntée à la base et non posée : sa série, son itinéraire et sa
		// production ne se fabriquent qu'au nom de la structure connectée, et le cas ne lit que la structure de la ligne.
		$eCultivationForeign = Cultivation::model()
			->select(Cultivation::getSelection())
			->whereStructure('!=', static::STRUCTURE)
			->whereSeason($this->getSeason())
			->sort(['id' => SORT_ASC])
			->get();

		if($this->assertDecorPresent($eCultivationForeign->notEmpty(), 'une culture d\'une autre structure dans la saison') === FALSE) {
			return;
		}

		[, , $action] = $this->callSheet($eCultivationForeign['series']);

		$this->assertFalse($action instanceof \ViewAction, 'la fiche d\'une série d\'une autre structure se lit');

		$tasks = Task::model()->whereCultivation($eCultivationForeign)->count();

		[, $action] = $this->callGesture('doHarvest', $this->getHarvestInput($eCultivationForeign));

		$this->assertFalse($action instanceof \ViewAction, 'la récolte sur une culture d\'une autre structure est acceptée');
		$this->assertEquals($tasks, Task::model()->whereCultivation($eCultivationForeign)->count(), 'la culture d\'une autre structure a reçu une récolte');

		[, $action] = $this->callGesture('doCreate', $this->getCreateInput($eCultivationForeign['series'], $eCultivationForeign));

		$this->assertFalse($action instanceof \ViewAction, 'une intervention sur une série d\'une autre structure est acceptée');
		$this->assertEquals($tasks, Task::model()->whereCultivation($eCultivationForeign)->count(), 'la culture d\'une autre structure a reçu une intervention');

	}

	/** Le conseiller d'un groupe lit la fiche sans pouvoir ouvrir le planning : il n'y a aucun geste. */
	public function testGroupReaderReadsWithoutGestures(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eBed = $this->createBed();
		$eSeries = $this->createSeriesOn($eBed, 'En place', 30, 45);

		$eUserAdvisor = \user\User::model()
			->select(\user\User::getSelection())
			->whereEmail('decor.g3.conseiller@example.org')
			->get();

		if($this->assertDecorPresent($eUserAdvisor->notEmpty(), 'Fanny, conseillère du groupe G3') === FALSE) {
			return;
		}

		$this->reconnect($eUserAdvisor);

		$eStructure = \structure\StructureLib::getById($this->eStructure['id']);

		if($this->assertDecorPresent($eStructure->canRead() and $eStructure->canPlanningLimited() === FALSE, 'Fanny lit la structure '.static::STRUCTURE.' par son groupe, sans le planning') === FALSE) {
			return;
		}

		[$data] = $this->callSheet($eSeries);

		if($this->assertTrue($data !== NULL, 'le conseiller du groupe ne lit pas la fiche') === FALSE) {
			return;
		}

		$this->assertEquals([FALSE, FALSE], [$data->canHarvest, $data->canTask], 'le conseiller du groupe reçoit un geste');

	}

	/** Le planning limité lit la fiche sans le geste Intervention, et aucune de ses routes d'écriture ne lui écrit. */
	public function testPlanningLimitedRefused(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eBed = $this->createBed();
		$eSeries = $this->createSeriesOn($eBed, 'En place', 30, 45);
		$eCultivation = $this->getCultivationOf($eSeries);

		$this->reconnect($this->getMemberPlanning());

		$eStructure = \structure\StructureLib::getById($this->eStructure['id']);

		if($this->assertDecorPresent($eStructure->canPlanningLimited() and $eStructure->canPlanning() === FALSE, 'Bruno au planning limité sur la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		[$data] = $this->callSheet($eSeries);

		if($this->assertTrue($data !== NULL, 'le planning limité ne lit pas la fiche') === FALSE) {
			return;
		}

		$this->assertFalse($data->canTask, 'le planning limité reçoit le geste Intervention');

		$tasks = Task::model()->whereCultivation($eCultivation)->count();

		[, $action] = $this->callGesture('doHarvest', $this->getHarvestInput($eCultivation));
		$this->assertFalse($action instanceof \ViewAction, 'le planning limité saisit une récolte');

		[, $action] = $this->callGesture('doCreate', $this->getCreateInput($eSeries, $eCultivation));
		$this->assertFalse($action instanceof \ViewAction, 'le planning limité crée une intervention');

		$this->assertEquals($tasks, Task::model()->whereCultivation($eCultivation)->count(), 'le planning limité a écrit une intervention');

	}

	private function requireDecor(): bool {

		return ($this->requireMember() and $this->requireAction(ACTION_PLANTATION));

	}

	/**
	 * Une série en planches posée sur la planche, avec sa culture, et dont la place va de la semaine `$start` à la
	 * semaine `$stop` de la saison.
	 */
	private function createSeriesOn(\map\Bed $eBed, string $name, int $start, int $stop): Series {

		$eSeries = $this->createSeriesBed($name);

		$this->createCultivation($eSeries);
		$this->createPlace($eSeries, $eBed, 10);
		$this->setBedWeeks($eSeries, $start, $stop);

		return $eSeries;

	}

	private function setBedWeeks(Series $eSeries, int $start, int $stop): void {

		// Sans `build()`, qui refuse ces colonnes calculées : la place d'une série se déduit des dates de ses cultures,
		// et le cas fixe la place qu'il éprouve sans fabriquer les interventions qui y mènent.
		$eSeries['bedStartCalculated'] = $start;
		$eSeries['bedStopCalculated'] = $stop;

		SeriesLib::update($eSeries, ['bedStartCalculated', 'bedStopCalculated']);

	}

	private function getCultivationOf(Series $eSeries): Cultivation {

		return Cultivation::model()
			->select(Cultivation::getSelection())
			->whereSeries($eSeries)
			->get();

	}

	/** Ce que le panneau « Nouvelle récolte » poste depuis la fiche : la culture, le jour et la quantité ajoutée. */
	private function getHarvestInput(Cultivation $eCultivation): array {

		return [
			'week' => \Clock::week(),
			'origin' => 'assolement',
			'cultivation' => $eCultivation['id'],
			'date' => \Clock::date(),
			'mode' => 'add',
			'harvestMore' => '3',
			'harvestUnit' => Cultivation::KG,
		];

	}

	/** Ce que le panneau « Nouvelle intervention » poste depuis la fiche : une plantation faite aujourd'hui sur la culture. */
	private function getCreateInput(Series $eSeries, Cultivation $eCultivation): array {

		return [
			'week' => \Clock::week(),
			'series' => $eSeries['id'],
			'status' => Task::DONE,
			'day' => \Clock::date(),
			'action' => $this->getAction(ACTION_PLANTATION)['id'],
			'category' => $this->getCategoryCulture()['id'],
			'cultivation' => [$eCultivation['id']],
		];

	}

	/** La fiche de la série, lue par sa route puis rendue par sa vue : ses données, son HTML, ou l'action levée. */
	private function callSheet(Series $eSeries): array {

		$action = $this->executePage('series/place:sheet', ['id' => $this->eStructure['id'], 'series' => $eSeries['id']], 'GET');

		if(($action instanceof \ViewAction) === FALSE) {
			return [NULL, NULL, $action];
		}

		[$data, $template] = $this->runView('sheet', $action, 'place', 'series');

		return [$data, $this->getOuterHtml($template->formatInstructions(), '#place-sheet'), $action];

	}

	/** Un geste de la fiche posé sur la structure du décor, puis rendu par sa vue : ses instructions, et l'action levée. */
	private function callGesture(string $route, array $input): array {

		$action = $this->executePage('series/place:'.$route, $input + ['id' => $this->eStructure['id']], 'POST');

		if(($action instanceof \ViewAction) === FALSE) {
			return [[], $action];
		}

		[, $template] = $this->runView('doSheet', $action, 'place', 'series');

		return [$template->formatInstructions(), $action];

	}

	/**
	 * L'empreinte : la fiche redessinée par la réponse est celle que la fiche rechargée rend. Les deux rendus sont
	 * comparés en entier, puisque c'est leur égalité, et non leur contenu, que le cas éprouve.
	 */
	private function assertSameAsReloaded(Series $eSeries, array $instructions, string $case): void {

		$html = $this->getOuterHtml($instructions, '#place-sheet');

		[, $sheet] = $this->callSheet($eSeries);

		$this->assertTrue($html !== NULL and $html === $sheet, $case.', la fiche redessinée diffère de la fiche rechargée');

	}

	/** Les sélecteurs que les instructions de la réponse touchent, hors appels JavaScript. */
	private function getSelectors(array $instructions): array {

		return array_values(array_unique(array_column(array_filter($instructions, fn(array $instruction) => in_array($instruction[0], ['qs', 'qsa'], TRUE)), 1)));

	}

	private function getOuterHtml(array $instructions, string $selector): ?string {

		foreach($instructions as $instruction) {

			if($instruction[0] === 'qs' and $instruction[1] === $selector) {

				foreach($instruction[2] as [$method, $arguments]) {

					if($method === 'outerHtml') {
						return $arguments[0];
					}

				}

			}

		}

		return NULL;

	}

	private function getClosedPanels(array $instructions): array {

		$panels = [];

		foreach($instructions as $instruction) {

			if($instruction[0] !== 'js') {
				continue;
			}

			foreach($instruction[1] as [$method, $arguments]) {

				if($method === 'closePanel') {
					$panels[] = $arguments[0];
				}

			}

		}

		return $panels;

	}

	private function reconnect(\user\User $eUser): void {

		\user\ConnectionLib::setOnline($eUser);
		\structure\MemberLib::clearCache();
		$this->forgetAccountingYear();

	}

}
?>
