<?php
namespace planning;

/**
 * L'écran sans aucune intervention (R-Retours-0410, point 18) : l'état vide porte « Nouvelle intervention ».
 * Ni « En retard » ni « Non planifié » ne le laissent vide à la Semaine ; « Masquer ce qui est fait » ne le
 * vide pas non plus. La rangée des outils et la recherche se regardent dans Chrome.
 *
 * Les réponses des gestes qui font apparaître ou disparaître la rangée se comparent au rechargement dans
 * `Fingerprint.t.php` (`testTaskEmpty`, `testDayEmpty`).
 *
 * Lancement : dev/test package=planning file=EmptyScreen
 */
class EmptyScreenTest extends HarvestFixtureTest {

	public function testIsEmpty(): void {

		$zone = fn(int $count) => ['count' => $count];

		$this->assertTrue(TaskWeekLib::isEmpty(new \Collection()), 'une semaine sans intervention ni zone n\'est pas vide');
		$this->assertTrue(TaskWeekLib::isEmpty(new \Collection(), [TaskLateLib::LATE => $zone(0), TaskLateLib::UNPLANNED => $zone(0)]), 'des zones sans intervention remplissent l\'écran');
		$this->assertFalse(TaskWeekLib::isEmpty(new \Collection([1 => new \series\Task(['id' => 1])])), 'une semaine qui porte une intervention est vide');
		$this->assertFalse(TaskWeekLib::isEmpty(new \Collection(), [TaskLateLib::LATE => $zone(1), TaskLateLib::UNPLANNED => $zone(0)]), '« En retard » ne remplit pas l\'écran');
		$this->assertFalse(TaskWeekLib::isEmpty(new \Collection(), [TaskLateLib::LATE => $zone(0), TaskLateLib::UNPLANNED => $zone(2)]), '« Non planifié » ne remplit pas l\'écran');

	}

	public function testWeekEmpty(): void {

		[$document, $data] = $this->renderTasks();

		$this->assertTrue($data->empty, 'l\'écran sans intervention n\'est pas jugé vide');

		$this->assertEquals([
			TaskCreateUi::url($this->eStructure, 'taskWeek', \Clock::week(), status: \series\Task::TODO, view: \structure\Member::CARDS),
			TaskCreateUi::url($this->eStructure, 'taskWeek', \Clock::week(), status: \series\Task::DONE, view: \structure\Member::CARDS),
		], $this->getHrefs($document, '#planning-week-board.util-block-empty > .util-buttons > a.btn.btn-primary'), 'les boutons de l\'état vide n\'ouvrent pas le panneau de la semaine en cartes, à faire puis fait');

	}

	/**
	 * « En retard » et « Non planifié » remplissent l'écran, même quand la semaine n'a rien.
	 */
	public function testWeekZones(): void {

		foreach([
			'en retard' => toWeek(\Clock::date().' - 7 DAYS'),
			'non planifié' => NULL,
		] as $case => $week) {

			$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);

			[, $data] = $this->renderTasks();

			$this->assertFalse($data->empty, 'un écran que remplit une intervention '.$case.' est jugé vide');

			\series\TaskLib::deleteCollection(new \Collection([$eTask['id'] => $eTask]));

		}

	}

	/**
	 * « Masquer ce qui est fait » vide les blocs d'une semaine dont tout est fait, sans vider l'écran.
	 */
	public function testWeekHidden(): void {

		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week());
		$this->setHideDone(TRUE);

		[, $data] = $this->renderTasks();

		$this->assertFalse($data->empty, 'l\'écran que le masque vide est jugé vide');
		$this->assertEquals(0, $data->ccTask->count(), 'le masque laisse un bloc à l\'écran, le cas ne se pose pas');

	}

	/**
	 * Le Jour n'a pas de zones : « Non planifié » ne le remplit pas, et « J'ai aussi fait » reste sur les jours
	 * passés (décision de V-Planning sur la passe ui).
	 */
	public function testDayEmpty(): void {

		$this->createTask($this->getCultivation(), ACTION_PLANTATION);

		[$document, $data] = $this->renderTaskDay();

		$this->assertTrue($data->empty, 'le Jour sans intervention de la semaine n\'est pas jugé vide');

		$this->assertEquals([
			TaskCreateUi::url($this->eStructure, 'taskDay', \Clock::week(), status: \series\Task::TODO),
			TaskCreateUi::url($this->eStructure, 'taskDay', \Clock::week(), status: \series\Task::DONE),
		], $this->getHrefs($document, '#planning-day > .util-block-empty > .util-buttons > a.btn.btn-primary'), 'les boutons de l\'état vide n\'ouvrent pas le panneau du Jour, à faire puis fait');

		$also = $document->querySelector('#planning-day-'.\Clock::date().' a[href="'.TaskCreateUi::url($this->eStructure, 'taskDay', \Clock::week(), date: \Clock::date(), status: \series\Task::DONE).'"]');
		$this->assertTrue($also !== NULL, 'aujourd\'hui, sur le Jour vide, n\'ouvre plus « J\'ai aussi fait » sur le jour, intervention faite');

	}

	public function testDayFull(): void {

		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

		[, $data] = $this->renderTaskDay();

		$this->assertFalse($data->empty, 'le Jour qui porte une intervention est jugé vide');

	}

	/**
	 * Les cibles des liens que `$selector` désigne, dans l'ordre du document.
	 */
	private function getHrefs(\Dom\HTMLDocument $document, string $selector): array {

		return array_map(fn(\Dom\Element $link) => $link->getAttribute('href'), iterator_to_array($document->querySelectorAll($selector)));

	}

}
?>
