<?php
namespace entry;

/**
 * Une action d'une autre catégorie que Culture, « RH 💡 » en Administration, s'ajoute au planning depuis le champ
 * comme une action de culture, sans espèce ni série, qu'aucune autre catégorie ne porte (HN-B14). « rh » ne la
 * trouvait pas : seules les actions de culture se proposaient à l'ajout, ni la longueur du nom ni son emoji n'y
 * étaient pour rien. Elle ne se lit que derrière son verbe, « temps rh » ou « intervention rh » : son nom croise
 * ceux d'espèces, de clients ou de produits (« RH » et Rhubarbe, une action et un client « Marché » en dev).
 */
class ActionOutsideCultureTest extends EntryFixtureTest {

	private function getTaskRead(\series\Task $eTask): \series\Task {

		return \series\Task::model()
			->select(['status', 'action', 'category', 'series', 'cultivation', 'plant', 'plannedWeek', 'doneDate', 'timeExpected'])
			->whereId($eTask['id'])
			->get();

	}

	/**
	 * « temps r » propose l'action au temps, comme les premières lettres d'une action de culture, et « temps rh »
	 * la nomme. Sans verbe, « rh » ne la devine pas.
	 */
	public function testTyped(): void {

		$this->setClockInSeason();

		$eAction = $this->createActionAdministration();

		if($eAction->empty()) {
			return;
		}

		$cElement = new \Collection();
		$reading = EntryLib::read($this->eStructure, 'temps r', cElement: $cElement);

		$this->assertEquals('time', $reading['intention']);

		$cElementShown = TimeIntentionLib::getShown($this->eStructure, $reading, EntryLib::findObjects($cElement, $reading['pending']));

		$this->assertTrue($cElementShown->offsetExists(-$eAction['id']), '« temps r » propose l\'action');

		// L'emoji n'est pas un mot du nom : « rh » le dit entier.
		$reading = $this->read('temps rh');

		$this->assertEquals($eAction['id'], $reading['object']['action']['id'], '« temps rh » nomme l\'action');

		$this->assertNotImplicit('rh');

		// Ni proposée dans la liste : choisie, elle poserait un jeton sans verbe que rien ne relirait.
		$cElement = new \Collection();
		EntryLib::read($this->eStructure, 'r', cElement: $cElement);

		$this->assertFalse($cElement->offsetExists(-$eAction['id']), '« r » ne propose pas l\'action');

		$cTaskObject = InterventionIntentionLib::getObjects($this->eStructure, EntryLib::getEmptyReading());

		$this->assertTrue($cTaskObject->offsetExists($eAction['id']), 'la nouvelle intervention la propose aussi');
		$this->assertEquals($eAction['category']['id'], $cTaskObject[$eAction['id']]['category']['id']);

	}

	/**
	 * Un emoji et ses modificateurs ne sont pas des mots, qu'il soit seul ou collé : un nom fait d'eux seuls n'en a
	 * aucun.
	 */
	public function testSplitSymbols(): void {

		$this->assertEquals(['rh'], EntryLib::split('RH 💡'));
		$this->assertEquals(['rh'], EntryLib::split('RH💡'));
		$this->assertEquals(['tri', 'bio'], EntryLib::split('Tri 👍🏽 bio'));
		$this->assertEquals([], EntryLib::split('📌'));
		$this->assertEquals(['b2', 'b'], EntryLib::split('B2B'), 'un nom court garde son découpage');

	}

	/**
	 * « temps rh 1 h » ajoute au planning une intervention d'Administration, sans espèce ni série, et y écrit le
	 * temps. Planifiée cette semaine, elle ne se devine pas davantage sans verbe.
	 */
	public function testTime(): void {

		$this->setClockInSeason();

		$eAction = $this->createActionAdministration();

		if($eAction->empty() or $this->requireMember() === FALSE) {
			return;
		}

		$reading = $this->read('temps rh 1 h ');

		$this->assertEquals('time', $reading['intention']);
		$this->assertEquals(NULL, $reading['question'], 'ni espèce ni série à demander');
		$this->assertEquals(NULL, $reading['refusal']);
		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);

		$entry = EntryUi::getEntry($reading);

		$this->assertEquals([NULL, NULL], [$entry['addCultivation'], $entry['addNoSeries']], 'ni culture ni espèce à répondre');

		EntryLib::write($this->eStructure, $reading);

		$eTask = $this->getTaskRead($reading['confirmation']['eTask']);

		$this->assertEquals($eAction['id'], $eTask['action']['id']);
		$this->assertEquals($eAction['category']['id'], $eTask['category']['id']);
		$this->assertTrue($eTask['series']->empty() and $eTask['cultivation']->empty() and $eTask['plant']->empty(), 'ni série, ni culture, ni espèce');
		$this->assertEquals(1.0, (float)\series\Timesheet::model()->select(['time'])->whereTask($reading['confirmation']['eTask'])->getCollection()->sum('time'));

		$this->assertNotImplicit('rh 30 min ');

	}

	/**
	 * Une action hors de Culture qui porte le nom d'une espèce en phase ne dispute pas la phrase sans verbe à la
	 * récolte : sans la règle, les deux lectures se valent et le champ ne devine rien.
	 */
	public function testCollision(): void {

		$this->setClockInSeason();

		$this->createCultivationInPhase(TRUE);
		$ePlant = $this->getPlant();

		$eAction = $this->createActionAdministration($ePlant['name']);

		if($eAction->empty()) {
			return;
		}

		$reading = $this->read(mb_strtolower($ePlant['name']));

		$this->assertEquals('harvest', $reading['intention']);
		$this->assertEquals($ePlant['id'], $reading['object']['id']);

		$reading = $this->read('temps '.mb_strtolower($ePlant['name']));

		$this->assertEquals('time', $reading['intention']);
		$this->assertEquals($eAction['id'], $reading['object']['action']['id'], 'derrière son verbe, l\'action se lit');

	}

	/**
	 * Une action hors de Culture rendue inactive garde ses interventions planifiées, que le temps propose encore :
	 * la catégorie de l'intervention dit alors qu'elle ne se devine pas sans verbe.
	 */
	public function testInactive(): void {

		$this->setClockInSeason();

		$eAction = $this->createActionAdministration('Zzinact'.substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 10));

		if($eAction->empty() or $this->requireMember() === FALSE) {
			return;
		}

		$eTask = new \series\Task([
			'structure' => $this->eStructure,
			'series' => new \series\Series(),
			'cultivation' => new \series\Cultivation(),
			'plant' => new \plant\Plant(),
			'action' => $eAction,
			'category' => $eAction['category'],
			'description' => NULL,
			'status' => \series\Task::TODO,
			'plannedWeek' => \Clock::week(),
			'plannedDate' => NULL,
			'variety' => new \plant\Variety(),
			'harvestSize' => new \plant\Size(),
			'repeatMaster' => new \series\Repeat(),
		]);

		// Sans `build()` : le décor fixe telles quelles la semaine prévue, le statut et la catégorie, que `build()` ne
		// prend que d'un formulaire posté (`planned`).
		\series\TaskLib::create($eTask);

		$eAction['status'] = \work\Action::INACTIVE;

		// Sans `build()` : le décor rend l'action inactive, comme le ferait son bouton, qui ne poste aucun champ.
		\work\ActionLib::update($eAction, ['status']);

		$name = mb_strtolower($eAction['name']);

		$this->assertNotImplicit($name.' 30 min ');

		$reading = $this->read('temps '.$name.' 30 min ');

		$this->assertEquals('time', $reading['intention']);
		$this->assertEquals($eAction['id'], $reading['object']['action']['id'], 'derrière son verbe, l\'intervention se lit');

	}

	/**
	 * Une action de Culture dont le nom commence par celui d'une action hors de Culture se trouve sans verbe dès ses
	 * premières lettres : la lecture de Culture ne change pas.
	 */
	public function testCulturePrefix(): void {

		$this->setClockInSeason();

		$eAction = $this->createActionAdministration('Zzr'.substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 10));

		if($eAction->empty()) {
			return;
		}

		$eActionCulture = new \work\Action([
			'name' => $eAction['name'].' semis',
			'structure' => $this->eStructure,
			'categories' => [$this->getCategoryCulture()['id']],
		]);

		// Sans `build()` : le décor fixe le nom et la catégorie, que le formulaire d'action vérifierait.
		\work\ActionLib::create($eActionCulture);

		$name = mb_strtolower($eAction['name']);

		foreach([$name, $name.' se'] as $text) {

			$cElement = new \Collection();
			$reading = EntryLib::read($this->eStructure, $text, cElement: $cElement);

			$this->assertTrue(in_array($reading['intention'], ['time', 'done'], TRUE), '« '.$text.' » se lit sans verbe');
			$this->assertTrue($cElement->offsetExists(-$eActionCulture['id']), '« '.$text.' » propose l\'action de Culture');
			$this->assertFalse($cElement->offsetExists(-$eAction['id']), '« '.$text.' » ne propose pas l\'autre');

		}

	}

	private function getQuestionName(array $reading): ?string {
		return ($reading['question'] === NULL) ? NULL : $reading['question']['name'];
	}

	/**
	 * Ni le temps ni la coche ne lisent la phrase sans verbe.
	 */
	private function assertNotImplicit(string $text): void {

		$reading = $this->read($text);

		$this->assertTrue(in_array($reading['intention'], ['time', 'done'], TRUE) === FALSE, '« '.$text.' » ne se lit pas en '.$reading['intention']);

	}

	/**
	 * « intervention rh » mène droit au récapitulatif, faite aujourd'hui, et « semaine prochaine 30 min » la
	 * planifie avec son temps prévu.
	 */
	public function testIntervention(): void {

		$this->setClockInSeason();

		$eAction = $this->createActionAdministration();

		if($eAction->empty()) {
			return;
		}

		$reading = $this->read('intervention rh ');

		$this->assertEquals('intervention', $reading['intention']);
		$this->assertEquals(NULL, $reading['question'], 'l\'espèce ne se demande pas');
		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertEquals(\series\Task::DONE, $reading['confirmation']['status']);
		$this->assertEquals(NULL, EntryUi::getEntry($reading)['addNoSeries']);

		$reading = $this->read('intervention rh semaine prochaine 30 min ');

		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);

		EntryLib::write($this->eStructure, $reading);

		$eTask = $this->getTaskRead($reading['confirmation']['eTask']);

		$this->assertEquals(\series\Task::TODO, $eTask['status']);
		$this->assertEquals($eAction['category']['id'], $eTask['category']['id']);
		$this->assertTrue($eTask['series']->empty() and $eTask['plant']->empty(), 'ni série ni espèce');
		$this->assertEquals(toWeek(date('Y-m-d', \Clock::shift('+7 DAYS'))), $eTask['plannedWeek']);
		$this->assertEquals(0.5, $eTask['timeExpected']);

	}

	/**
	 * Une action de culture garde sa question de l'espèce : la catégorie qu'elle donne est Culture, même quand
	 * elle en a d'autres.
	 */
	public function testCultureFirst(): void {

		$eCategoryCulture = $this->getCategoryCulture();
		$eCategory = \work\CategoryLib::getByStructure($this->eStructure, fqn: CATEGORIE_ADMINISTRATION);

		$cCategory = \work\CategoryLib::getByStructure($this->eStructure);

		$eAction = new \work\Action(['categories' => [$eCategory['id'], $eCategoryCulture['id']]]);

		$this->assertEquals($eCategoryCulture['id'], TimeIntentionLib::getCategory($cCategory, $eAction)['id']);
		$this->assertTrue(TimeIntentionLib::getCategory($cCategory, new \work\Action(['categories' => []]))->empty(), 'sans catégorie, rien ne s\'ajoute');

	}

	/**
	 * « rh fait » sans rien de prévu passe à l'intervention, faite d'abord, que la puce d'état met à faire, et que
	 * le verbe tapé reste « fait ». Sans verbe, rien ne se lit.
	 */
	public function testDoneCreates(): void {

		$this->setClockInSeason();

		$eAction = $this->createActionAdministration();

		if($eAction->empty() or $this->requireMember() === FALSE) {
			return;
		}

		$reading = $this->read('rh fait ');

		$this->assertEquals('intervention', $reading['intention']);
		$this->assertEquals(['fait'], $reading['verb'], 'le jeton du verbe remplace le mot tapé');
		$this->assertEquals(NULL, $reading['refusal']);
		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertEquals(\series\Task::DONE, $reading['confirmation']['status']);

		$this->assertEquals(\series\Task::TODO, $this->read('rh fait ', ['status' => \series\Task::TODO])['confirmation']['status']);

		$reading = $this->read('rh fait hier ');

		$this->assertEquals('intervention', $reading['intention']);
		$this->assertEquals(date('Y-m-d', \Clock::shift('-1 DAY')), $reading['date'], 'le jour dit reste');

		// Le verbe en frappe ne reste pas en frappe : l'action est nommée.
		$this->assertEquals('intervention', $this->read('rh fait')['intention']);
		$this->assertEquals($eAction['id'], $this->read('rh fait')['object']['action']['id']);

		$reading = $this->read('rh fait ');

		EntryLib::write($this->eStructure, $reading);

		$eTask = $this->getTaskRead($reading['confirmation']['eTask']);

		$this->assertEquals(\series\Task::DONE, $eTask['status']);
		$this->assertEquals($eAction['category']['id'], $eTask['category']['id']);
		$this->assertTrue($eTask['series']->empty() and $eTask['plant']->empty(), 'ni série ni espèce');

		$this->assertNotImplicit('rh ');

	}

	/**
	 * Une intervention prévue de l'action se coche comme une intervention de culture, sans en créer une autre. Faite,
	 * elle n'attend plus rien.
	 */
	public function testDonePlanned(): void {

		$this->setClockInSeason();

		$eAction = $this->createActionAdministration();

		if($eAction->empty() or $this->requireMember() === FALSE) {
			return;
		}

		$reading = $this->read('intervention rh semaine prochaine ');

		EntryLib::write($this->eStructure, $reading);

		$eTaskPlanned = $reading['confirmation']['eTask'];

		$reading = $this->read('rh fait ');

		$this->assertEquals('done', $reading['intention']);
		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertEquals([$eTaskPlanned['id']], $reading['confirmation']['cTask']->getIds());
		$this->assertFalse($reading['confirmation']['created']);

		EntryLib::write($this->eStructure, $reading);

		$this->assertEquals(\series\Task::DONE, $this->getTaskRead($eTaskPlanned)['status']);

		// La pastille de la coche se rend sur une intervention sans espèce.
		$this->assertEquals(1, DoneIntentionUi::getWriteToast($reading)['options']['count']);

		$reading = $this->read('rh fait ');

		$this->assertEquals('done', $reading['intention'], 'faite aujourd\'hui, elle ne se recrée pas');
		$this->assertEquals($eTaskPlanned['id'], $reading['task']['id']);
		$this->assertEquals(NULL, $reading['confirmation']);

	}

	/**
	 * Une action de plusieurs catégories hors de Culture fait choisir la sienne, que l'intervention, le temps et la
	 * coche qui passe à l'intervention reçoivent. Une catégorie qui n'est pas la sienne ne répond pas.
	 */
	public function testCategoryAsked(): void {

		$this->setClockInSeason();

		$eAction = $this->createActionAdministration('Compta', [CATEGORIE_COMMERCIALISATION, CATEGORIE_ADMINISTRATION]);

		if($eAction->empty() or $this->requireMember() === FALSE) {
			return;
		}

		$cCategory = $eAction['cCategory'];
		$eCategoryCulture = $this->getCategoryCulture();

		foreach(['intervention compta ', 'temps compta 1 h ', 'compta fait '] as $text) {

			$reading = $this->read($text);

			if($this->assertEquals('category', $this->getQuestionName($reading), '« '.$text.' » demande la catégorie')) {
				$this->assertEquals($cCategory->getIds(), $reading['question']['cCategory']->getIds());
			}

			$this->assertEquals('category', $this->getQuestionName($this->read($text, ['category' => $eCategoryCulture['id']])), '« '.$text.' » ne prend pas une catégorie qui n\'est pas la sienne');

		}

		$eCategory = $cCategory->last();

		$reading = $this->read('intervention compta semaine prochaine ', ['category' => $eCategory['id']]);

		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);

		EntryLib::write($this->eStructure, $reading);

		$eTaskPlanned = $reading['confirmation']['eTask'];
		$eTaskRead = $this->getTaskRead($eTaskPlanned);

		$this->assertEquals($eCategory['id'], $eTaskRead['category']['id']);
		$this->assertEquals(\series\Task::TODO, $eTaskRead['status']);

		$reading = $this->read('temps compta 1 h ', ['category' => $eCategory['id']]);

		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertEquals($eCategory['id'], $reading['confirmation']['eTask']['category']['id']);

		// Prévue dans l'une de ses catégories, l'intervention se coche sans question.
		$reading = $this->read('compta fait ');

		$this->assertEquals('done', $reading['intention']);
		$this->assertEquals(NULL, $reading['question']);
		$this->assertEquals([$eTaskPlanned['id']], $reading['confirmation']['cTask']->getIds());
		$this->assertFalse($reading['confirmation']['created']);

		// Prévue aussi dans l'autre catégorie, la coche demande laquelle, chacune portant le nom de sa catégorie, qui
		// seul les départage.
		$reading = $this->read('intervention compta semaine prochaine ', ['category' => $cCategory->first()['id']]);

		EntryLib::write($this->eStructure, $reading);

		$reading = $this->read('compta fait ');

		if($this->assertEquals('task', $this->getQuestionName($reading))) {
			$expected = $cCategory->getIds();
			$categories = $reading['question']['cTask']->getColumnCollection('category')->getIds();
			sort($expected);
			sort($categories);

			$this->assertEquals($expected, $categories);
			$this->assertTrue($reading['question']['cTask']->first()['category']->offsetExists('name'), 'le nom de la catégorie est lu');
		}

	}

}
?>
