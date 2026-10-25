<?php
namespace planning;

/**
 * La saisie d'une récolte sur une structure qui ne suit pas le temps de travail (`Structure::hasTime()`,
 * retour de Guillaume du 12/10, lot AZ) : ni le tiroir, ni la feuille, ni « Nouvelle récolte » n'ont de bloc
 * Temps, et un temps posté quand même est refusé par `build()` (`Timesheet::time.untracked`), depuis l'écran
 * des récoltes comme depuis une carte de la Semaine. Avec le suivi, la même saisie écrit son temps.
 *
 * Lancement : dev/test package=planning file=HarvestTimeUntracked
 */
class HarvestTimeUntrackedTest extends HarvestFixtureTest {

	private bool $timeBefore;

	public function setUp(): void {

		parent::setUp();

		$this->timeBefore = $this->getConfiguration()['time'];

	}

	public function tearDown(): void {

		$this->getConfiguration()['time'] = $this->timeBefore;

		parent::tearDown();

	}

	/**
	 * La configuration que lit `Structure::hasTime()`, mémorisée par structure (`Structure::conf()`) : la page
	 * exécutée la relit par l'identifiant de la structure, et prend donc celle-ci.
	 */
	private function getConfiguration(): \structure\Configuration {

		return \structure\StructureLib::getById($this->eStructure['id'])->conf();

	}

	/**
	 * Sans `ConfigurationLib` : le réglage se pose sur la configuration mémorisée seulement, que `tearDown()`
	 * remet, et la ligne de la structure n'est pas touchée, aucun autre test ne la voyant changer.
	 */
	private function setTime(bool $time): void {

		$this->getConfiguration()['time'] = $time;

	}

	public function testHarvestWeekRefusesTime(): void {

		$this->setTime(FALSE);

		$eCultivation = $this->createCultivation();

		[, , $action] = $this->call('doHarvest', [
			'cultivation' => $eCultivation['id'],
			'date' => \Clock::date(),
			'mode' => 'add',
			'harvestMore' => '3',
			'time' => $this->getTimeInput('0.5'),
		], 'POST');

		$this->assertTrue(in_array('Timesheet::time.untracked', $this->getFails($action)), 'un temps posté sans suivi du temps n\'est pas refusé : '.implode(', ', $this->getFails($action)));
		$this->assertEquals(0, $this->countTime($eCultivation), 'un temps posté sans suivi du temps est écrit');

		[, , $action] = $this->call('doHarvest', [
			'cultivation' => $eCultivation['id'],
			'date' => \Clock::date(),
			'mode' => 'add',
			'harvestMore' => '3',
		], 'POST');

		$this->assertTrue($action instanceof \ViewAction, 'une récolte sans temps est refusée sans suivi du temps : '.implode(', ', $this->getFails($action)));
		$this->assertEquals(0, $this->countTime($eCultivation), 'une récolte sans suivi du temps écrit un temps');

	}

	public function testTaskWeekRefusesTime(): void {

		$this->setTime(FALSE);

		$eCultivation = $this->createCultivation();
		$eTask = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		[, , $action] = $this->callTask('doHarvest', $eTask, [
			'cultivation' => $eCultivation['id'],
			'date' => \Clock::date(),
			'mode' => 'add',
			'harvestMore' => '3',
			'panel' => 1,
			'time' => $this->getTimeInput('0.5'),
		]);

		$this->assertTrue(in_array('Timesheet::time.untracked', $this->getFails($action)), 'un temps posté depuis une carte sans suivi du temps n\'est pas refusé : '.implode(', ', $this->getFails($action)));
		$this->assertEquals(0, $this->countTime($eCultivation), 'un temps posté depuis une carte sans suivi du temps est écrit');

	}

	public function testTrackedWritesTime(): void {

		$this->setTime(TRUE);

		$eCultivation = $this->createCultivation();

		[, , $action] = $this->call('doHarvest', [
			'cultivation' => $eCultivation['id'],
			'date' => \Clock::date(),
			'mode' => 'add',
			'harvestMore' => '3',
			'time' => $this->getTimeInput('0.5'),
		], 'POST');

		$this->assertTrue($action instanceof \ViewAction, 'la saisie avec temps est refusée avec le suivi : '.implode(', ', $this->getFails($action)));
		$this->assertEquals(1, $this->countTime($eCultivation), 'le temps n\'est pas écrit avec le suivi');

	}

	/**
	 * Le tiroir, la feuille et « Nouvelle récolte » montrent le bloc Temps avec le suivi, et aucun sans lui.
	 */
	public function testEntryScreens(): void {

		$eCultivation = $this->createCultivation();
		$this->createHarvest($this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date()), 2.0, \Clock::date());

		foreach([TRUE, FALSE] as $time) {

			$this->setTime($time);

			$case = $time ? 'avec suivi' : 'sans suivi';

			[, $instructions, $action] = $this->call('open', ['cultivation' => $eCultivation['id'], 'date' => \Clock::date(), 'mode' => 'add'], 'GET');

			if($this->assertTrue($action instanceof \ViewAction, $case.' : le tiroir ne s\'ouvre pas') === FALSE) {
				continue;
			}

			$drawer = $this->getDocument($this->getInserted($instructions, '#planning-harvest-'.$eCultivation['id']));

			$this->assertTrue($drawer->querySelector('.planning-harvest-drawer') !== NULL, $case.' : la réponse ne pose pas le tiroir');
			$this->assertEquals($time, $drawer->querySelector('.planning-harvest-entry-time') !== NULL, $case.' : le tiroir '.($time ? 'ne montre pas' : 'montre').' le bloc Temps');
			$this->assertEquals($time, $drawer->querySelector('[name^="time["]') !== NULL, $case.' : le tiroir '.($time ? 'ne poste pas' : 'poste').' un temps');

			[, $instructions, $action] = $this->call('open', ['cultivation' => $eCultivation['id'], 'date' => \Clock::date(), 'mode' => 'add', 'sheet' => 1], 'GET');

			if($this->assertTrue($action instanceof \ViewAction, $case.' : la feuille ne s\'ouvre pas') === FALSE) {
				continue;
			}

			$sheet = $this->getDocument($this->getInserted($instructions, 'body'))->getElementById('planning-harvest-sheet');

			if($this->assertTrue($sheet !== NULL, $case.' : la réponse ne pose pas la feuille') === FALSE) {
				continue;
			}

			$this->assertEquals($time, $sheet->querySelector('[data-planning-sheet-pane="time"]') !== NULL, $case.' : la feuille '.($time ? 'ne montre pas' : 'montre').' le temps des personnes');
			$this->assertEquals($time, $sheet->querySelector('[data-planning-sheet-time]') !== NULL, $case.' : le bouton de la feuille '.($time ? 'ne dit pas' : 'dit').' le temps');
			$this->assertEquals($time, $sheet->querySelector('[name^="time["]') !== NULL, $case.' : la feuille '.($time ? 'ne poste pas' : 'poste').' un temps');

			[$data, , $action] = $this->call('create', ['cultivation' => $eCultivation['id']], 'GET');

			if($this->assertTrue($action instanceof \ViewAction, $case.' : « Nouvelle récolte » ne s\'ouvre pas') === FALSE) {
				continue;
			}

			$panel = new HarvestEntryUi()->create($data->eStructure, $data->week, $data->eCultivation, $data->unit, $data->units, $data->date, $data->dateMin, $data->dateMax, $data->days, $data->eUserOnline, $data->open, $data->taskWeek, $data->tasksByUnit);
			$document = $this->getDocument($panel->body);

			$this->assertEquals($time, $document->querySelector('[data-planning-harvest-time-labels]') !== NULL, $case.' : « Nouvelle récolte » '.($time ? 'ne montre pas' : 'montre').' le bloc Temps');
			$this->assertEquals($time, $document->querySelector('[name^="time["]') !== NULL, $case.' : « Nouvelle récolte » '.($time ? 'ne poste pas' : 'poste').' un temps');

		}

	}

	private function countTime(\series\Cultivation $eCultivation): int {

		return \series\Timesheet::model()
			->whereCultivation($eCultivation)
			->count();

	}

	private function getDocument(string $html): \Dom\HTMLDocument {

		return \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NOERROR);

	}

	/** Le HTML que la réponse insère dans le nœud nommé. */
	private function getInserted(array $instructions, string $selector): string {

		$html = '';

		foreach($instructions as $instruction) {

			if($instruction[0] === 'js' or $instruction[1] !== $selector) {
				continue;
			}

			foreach($instruction[2] as $call) {

				[$method, $arguments] = $call + [1 => []];

				if($method === 'insertAdjacentHtml') {
					$html .= $arguments[1];
				}

			}

		}

		return $html;

	}

}
?>
