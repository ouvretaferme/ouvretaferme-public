<?php
namespace planning;

/**
 * Le socle du pense-bête de la semaine (R-PenseBete) : ce que `build()` accepte et refuse d'un rappel,
 * le cochage qui pose seul l'état et qui l'a fait, les droits par rôle, et ce que montre chaque semaine,
 * bornée à la structure.
 *
 * Chaque rappel est monté dans la transaction de la méthode, sur la structure du décor de `series`, à
 * l'heure figée par `HarvestFixtureTest` (jeudi de la semaine 39).
 *
 * Lancement : dev/test package=planning file=Reminder
 */
class ReminderTest extends HarvestFixtureTest {

	/**
	 * Un rappel construit comme le fera la page, par `build()` sur les propriétés de création : le jour
	 * dans la semaine et le jour absent passent, un jour d'une autre semaine est refusé par son étape.
	 */
	public function testBuildDate(): void {

		$week = \Clock::week();

		$this->assertEquals([], $this->buildsCreate(['week' => $week, 'date' => week_date_day($week, 1), 'text' => 'Lundi']), 'le lundi de la semaine est refusé');
		$this->assertEquals([], $this->buildsCreate(['week' => $week, 'date' => week_date_day($week, 7), 'text' => 'Dimanche']), 'le dimanche de la semaine est refusé');
		$this->assertEquals([], $this->buildsCreate(['week' => $week, 'date' => '', 'text' => 'Sans jour']), 'un rappel sans jour est refusé');

		$this->assertEquals(['Reminder::date.dateInWeek'], $this->buildsCreate(['week' => $week, 'date' => week_date_day($week, 8), 'text' => 'Lundi suivant']), 'le lundi de la semaine suivante est accepté');
		$this->assertEquals(['Reminder::date.dateInWeek'], $this->buildsCreate(['week' => $week, 'date' => date('Y-m-d', strtotime(week_date_starts($week).' -1 day')), 'text' => 'Dimanche précédent']), 'le dimanche de la semaine précédente est accepté');
		$this->assertEquals(['Reminder::date.check'], $this->buildsCreate(['week' => $week, 'date' => 'demain', 'text' => 'Date illisible']), 'une date illisible est acceptée');

		// La semaine refusée porte son message, et le jour ne le double pas d'un second refus.
		$this->assertEquals(['Reminder::week.check'], $this->buildsCreate(['week' => 'semaine 39', 'date' => week_date_day($week, 1), 'text' => 'Semaine illisible']), 'une semaine illisible ne rend pas son seul refus');

		// À la mise à jour, la semaine n'est pas postée : le jour se compare à celle du rappel en base.
		$eReminder = $this->createReminder($week, NULL, 'Mise à jour');

		$this->assertEquals([], $this->buildsUpdate($eReminder, ['date' => week_date_day($week, 3), 'text' => 'Mercredi']), 'le mercredi de la semaine du rappel est refusé à la mise à jour');
		$this->assertEquals(['Reminder::date.dateInWeek'], $this->buildsUpdate($eReminder, ['date' => week_date_day($week, 10), 'text' => 'Autre semaine']), 'un jour d\'une autre semaine est accepté à la mise à jour');

	}

	/**
	 * Une semaine passée ne prend plus de rappel, la semaine en cours et celles à venir oui (FX). Un rappel
	 * se corrige et s'efface tant qu'une semaine en cours ou à venir le montre : à faire, reporté, ou coché
	 * dans la semaine en cours ; plus une fois coché dans une semaine passée.
	 */
	public function testPastWeek(): void {

		$now = \Clock::datetime();
		$week = \Clock::week();
		$weekBefore = toWeek(week_date_starts($week).' -7 days');
		$weekNext = toWeek(week_date_starts($week).' +7 days');

		$this->assertEquals([], $this->buildsCreate(['week' => $week, 'text' => 'Cette semaine']), 'la semaine en cours ne prend pas de rappel');
		$this->assertEquals([], $this->buildsCreate(['week' => $weekNext, 'text' => 'Semaine prochaine']), 'la semaine prochaine ne prend pas de rappel');
		$this->assertEquals(['Reminder::week.notPast'], $this->buildsCreate(['week' => $weekBefore, 'date' => week_date_day($weekBefore, 1), 'text' => 'Semaine dernière']), 'une semaine passée prend un rappel, ou son jour double le refus');

		$eReminderTodo = $this->createReminder($weekBefore, NULL, 'Resté');
		$eReminderCheckedNow = $this->createReminder($weekBefore, NULL, 'Coché cette semaine');
		ReminderLib::check($eReminderCheckedNow);

		$eReminderCheckedBefore = $this->createReminder($weekBefore, NULL, 'Coché dans sa semaine');
		$this->setClock(week_date_day($weekBefore, 2).' 09:00:00');
		ReminderLib::check($eReminderCheckedBefore);
		$this->setClock($now);

		$this->assertTrue(ReminderLib::getById($eReminderTodo['id'])->acceptUpdate(), 'un resté à faire ne se corrige plus');
		$this->assertTrue(ReminderLib::getById($eReminderCheckedNow['id'])->acceptUpdate(), 'un rappel coché dans la semaine en cours ne se corrige plus');
		$this->assertFalse(ReminderLib::getById($eReminderCheckedBefore['id'])->acceptUpdate(), 'un rappel coché dans une semaine passée se corrige');

		$this->assertTrue(ReminderLib::getById($eReminderTodo['id'])->acceptDelete(), 'un resté à faire ne s\'efface plus');
		$this->assertTrue(ReminderLib::getById($eReminderCheckedNow['id'])->acceptDelete(), 'un rappel coché dans la semaine en cours ne s\'efface plus');
		$this->assertFalse(ReminderLib::getById($eReminderCheckedBefore['id'])->acceptDelete(), 'un rappel coché dans une semaine passée s\'efface');

	}

	/**
	 * Le texte va de 1 à 250 caractères, bornes du schéma comprises.
	 */
	public function testBuildText(): void {

		$week = \Clock::week();
		$max = Reminder::model()->getPropertyRange('text')[1];

		$this->assertEquals(250, $max, 'la borne du texte n\'est plus celle de la planche');
		$this->assertEquals([], $this->buildsCreate(['week' => $week, 'text' => str_repeat('é', $max)]), 'un texte de '.$max.' caractères est refusé');
		$this->assertEquals(['Reminder::text.check'], $this->buildsCreate(['week' => $week, 'text' => str_repeat('é', $max + 1)]), 'un texte de '.($max + 1).' caractères est accepté');
		$this->assertEquals(['Reminder::text.check'], $this->buildsCreate(['week' => $week, 'text' => '']), 'un texte vide est accepté');

	}

	/**
	 * L'état et qui a coché ne se postent pas : `build()` ne les lit pas, et un rappel créé avec eux dans
	 * l'entrée part à faire, sans personne qui l'ait coché.
	 */
	public function testStateNotPosted(): void {

		$eReminder = new Reminder(['structure' => $this->eStructure]);

		$fw = new \FailWatch();

		$eReminder->build(ReminderLib::getPropertiesCreate(), [
			'week' => \Clock::week(),
			'text' => 'Posté avec un état',
			'status' => Reminder::DONE,
			'doneAt' => \Clock::datetime(),
			'doneBy' => $this->getMember()['id'],
		], new \PropertyList('create'));

		if($this->assertTrue($fw->ok(), 'le rappel est refusé : '.implode(', ', array_column($fw->get(), 1))) === FALSE) {
			return;
		}

		ReminderLib::create($eReminder);

		$eReminderStored = ReminderLib::getById($eReminder['id']);

		$this->assertEquals(Reminder::TODO, $eReminderStored['status'], 'l\'état posté a été écrit');
		$this->assertEquals(NULL, $eReminderStored['doneAt'], 'la date de cochage postée a été écrite');
		$this->assertTrue($eReminderStored['doneBy']->empty(), 'la personne postée comme ayant coché a été écrite');
		$this->assertEquals($this->getMember()['id'], $eReminderStored['createdBy']['id'], 'l\'auteur n\'est pas la personne connectée');

	}

	/**
	 * Cocher pose l'état, l'heure et la personne connectée ensemble ; décocher les retire ensemble.
	 * Aucun des deux ne touche à `updatedAt`, qui date la dernière correction.
	 */
	public function testCheckUncheck(): void {

		$eReminder = ReminderLib::getById($this->createReminder(\Clock::week(), NULL, 'À cocher')['id']);

		$this->assertTrue($eReminder->acceptCheck(), 'un rappel à faire ne se coche pas');
		$this->assertFalse($eReminder->acceptUncheck(), 'un rappel à faire se décoche');

		ReminderLib::check($eReminder);

		$eReminderDone = ReminderLib::getById($eReminder['id']);

		$this->assertEquals(Reminder::DONE, $eReminderDone['status'], 'le rappel coché n\'est pas fait');
		$this->assertEquals(\Clock::datetime(), $eReminderDone['doneAt'], 'le rappel coché ne porte pas l\'heure de l\'horloge');
		$this->assertEquals($this->getMember()['id'], $eReminderDone['doneBy']['id'], 'le rappel coché ne porte pas la personne connectée');
		$this->assertEquals(NULL, $eReminderDone['updatedAt'], 'cocher a daté une correction');
		$this->assertFalse($eReminderDone->acceptCheck(), 'un rappel fait se coche encore');
		$this->assertTrue($eReminderDone->acceptUncheck(), 'un rappel fait ne se décoche pas');

		ReminderLib::uncheck($eReminderDone);

		$eReminderTodo = ReminderLib::getById($eReminder['id']);

		$this->assertEquals(Reminder::TODO, $eReminderTodo['status'], 'le rappel décoché n\'est pas à faire');
		$this->assertEquals(NULL, $eReminderTodo['doneAt'], 'le rappel décoché garde son heure');
		$this->assertTrue($eReminderTodo['doneBy']->empty(), 'le rappel décoché garde qui l\'avait coché');
		$this->assertEquals(NULL, $eReminderTodo['updatedAt'], 'décocher a daté une correction');

		ReminderLib::update($eReminderTodo, ['text']);

		$this->assertEquals(\Clock::datetime(), ReminderLib::getById($eReminder['id'])['updatedAt'], 'une correction n\'est pas datée');

	}

	/**
	 * Le planning entier lit, écrit et coche ; le planning limité lit seulement ; un rappel d'une
	 * structure dont on n'est pas membre ne se lit pas.
	 */
	public function testRights(): void {

		if($this->requireMember() === FALSE or $this->requireStructureForeign() === FALSE) {
			return;
		}

		$eReminder = ReminderLib::getById($this->createReminder(\Clock::week(), NULL, 'Droits')['id']);

		$this->connect($this->getMember());
		$eReminder['structure'] = \structure\StructureLib::getById($this->eStructure['id']);

		$this->assertTrue($eReminder->canRead(), 'le planning entier ne lit pas le rappel');
		$this->assertTrue($eReminder->canWrite(), 'le planning entier n\'écrit pas le rappel');
		$this->assertTrue($eReminder->assertCheck(), 'le planning entier ne coche pas le rappel');
		$this->assertFalse($eReminder->assertUncheck(), 'le planning entier décoche un rappel à faire');

		$this->connect($this->getMemberPlanning());
		$eReminder['structure'] = \structure\StructureLib::getById($this->eStructure['id']);

		if($this->assertDecorPresent($eReminder['structure']->canPlanningLimited() and $eReminder['structure']->canPlanning() === FALSE, 'Bruno au planning limité sur la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		$this->assertTrue($eReminder->canRead(), 'le planning limité ne lit pas le rappel');
		$this->assertFalse($eReminder->canWrite(), 'le planning limité écrit le rappel');
		$this->assertFalse($eReminder->assertCheck(), 'le planning limité coche le rappel');

		$this->connect($this->getMember());

		$eStructureForeign = \structure\StructureLib::getById($this->getStructureForeign()['id']);

		if($this->assertDecorPresent($eStructureForeign->getMember()->empty(), 'une autre structure dont le membre du décor n\'est pas membre') === FALSE) {
			return;
		}

		$eReminderForeign = new Reminder(['structure' => $eStructureForeign, 'status' => Reminder::TODO]);

		$this->assertFalse($eReminderForeign->canRead(), 'un rappel d\'une autre structure se lit');
		$this->assertFalse($eReminderForeign->assertCheck(), 'un rappel d\'une autre structure se coche');

	}

	/**
	 * La semaine rend ses rappels, faits ou non, datés dans l'ordre des jours puis sans jour ; les restés
	 * rendent les rappels à faire des semaines d'avant, la plus ancienne d'abord, et ceux cochés cette
	 * semaine, barrés. Aucune lecture ne prend une autre structure, ni une semaine à venir.
	 */
	public function testReads(): void {

		if($this->requireStructureForeign() === FALSE) {
			return;
		}

		$week = \Clock::week();
		$weekBefore = toWeek(week_date_starts($week).' -7 days');
		$weekLong = toWeek(week_date_starts($week).' -21 days');
		$weekAfter = toWeek(week_date_starts($week).' +7 days');

		$eReminderFriday = $this->createReminder($week, week_date_day($week, 5), 'Vendredi');
		$eReminderMonday = $this->createReminder($week, week_date_day($week, 1), 'Lundi');
		$eReminderNone = $this->createReminder($week, NULL, 'Sans jour');
		$eReminderDone = $this->createReminder($week, week_date_day($week, 2), 'Mardi, fait');
		ReminderLib::check($eReminderDone);

		$eReminderBefore = $this->createReminder($weekBefore, NULL, 'Resté de la semaine passée');
		$eReminderLong = $this->createReminder($weekLong, NULL, 'Resté depuis trois semaines');
		$eReminderBeforeDone = $this->createReminder($weekBefore, NULL, 'Resté, fait cette semaine');
		ReminderLib::check($eReminderBeforeDone);
		$this->createReminder($weekAfter, NULL, 'Semaine suivante');

		$this->createReminder($week, NULL, 'Autre structure', $this->getStructureForeign());
		$this->createReminder($weekBefore, NULL, 'Resté d\'une autre structure', $this->getStructureForeign());

		$reminders = ReminderLib::getForWeek($this->eStructure, $week);

		$this->assertEquals(
			[$eReminderMonday['id'], $eReminderDone['id'], $eReminderFriday['id'], $eReminderNone['id']],
			$reminders['cReminder']->getIds(),
			'les rappels de la semaine ne sont pas ceux posés, ou pas dans l\'ordre des jours, faits compris'
		);

		$this->assertEquals(
			[$eReminderLong['id'], $eReminderBefore['id'], $eReminderBeforeDone['id']],
			$reminders['cReminderBefore']->getIds(),
			'les rappels restés ne sont pas ceux à faire des semaines d\'avant et ceux cochés cette semaine, la plus ancienne d\'abord'
		);

		$this->assertEquals(5, $reminders['todo'], 'le compte ne dit pas les seuls rappels à faire, restés compris');
		$this->assertTrue($reminders['cReminder'][$eReminderDone['id']]['done'] and $reminders['cReminderBefore'][$eReminderBeforeDone['id']]['done'], 'un rappel coché cette semaine ne se lit pas barré');
		$this->assertEquals($this->getMember()['firstName'], $reminders['cReminder'][$eReminderDone['id']]['doneBy']['firstName'], 'la lecture de la semaine ne dit pas qui a coché');

	}

	/**
	 * Une semaine montre ce qui y était vrai (FX, décidé le 16/10). Un rappel écrit en S40 et
	 * coché en S42 se lit à faire en S40 et S41, barré en S42, et nulle part ensuite. Un rappel coché en S42
	 * pour S44 se lit barré en S44 seulement. Une semaine à venir ne montre que ses rappels. Décoché, un
	 * rappel revient à faire jusqu'à la semaine en cours. Une semaine passée se lit sans geste (`isPast`).
	 */
	public function testWeekShowsWhatWasTrue(): void {

		$week40 = \Clock::week();
		$week41 = toWeek(week_date_starts($week40).' +7 days');
		$week42 = toWeek(week_date_starts($week40).' +14 days');
		$week43 = toWeek(week_date_starts($week40).' +21 days');
		$week44 = toWeek(week_date_starts($week40).' +28 days');

		$eReminder = $this->createReminder($week40, week_date_day($week40, 3), 'Écrit en S40, coché en S42');
		$eReminderAhead = $this->createReminder($week44, NULL, 'Écrit pour S44, coché en S42');
		$eReminderTodo = $this->createReminder($week40, NULL, 'Jamais coché');

		$this->setClock(week_date_day($week42, 2).' 10:00:00');

		ReminderLib::check($eReminder);
		ReminderLib::check($eReminderAhead);

		$this->assertEquals([$eReminder['id'] => FALSE, $eReminderTodo['id'] => FALSE], $this->getShown($week40), 'S40 ne montre pas ses deux rappels à faire');
		$this->assertEquals([$eReminder['id'] => FALSE, $eReminderTodo['id'] => FALSE], $this->getShown($week41), 'S41 ne montre pas à faire le rappel coché plus tard');
		$this->assertEquals([$eReminder['id'] => TRUE, $eReminderTodo['id'] => FALSE], $this->getShown($week42), 'S42 ne montre pas barré le rappel coché dans la semaine');
		$this->assertEquals([], $this->getShown($week43), 'une semaine à venir montre des rappels reportés');
		$this->assertEquals([$eReminderAhead['id'] => TRUE], $this->getShown($week44), 'le rappel coché en avance ne se lit pas barré dans sa semaine');

		$this->assertTrue(ReminderLib::getForWeek($this->eStructure, $week41)['cReminderBefore'][$eReminder['id']]['isPast'], 'une semaine passée se lit avec ses gestes');
		$this->assertFalse(ReminderLib::getForWeek($this->eStructure, $week42)['cReminderBefore'][$eReminder['id']]['isPast'], 'la semaine en cours se lit sans geste');

		$this->setClock(week_date_day($week43, 1).' 08:00:00');

		$this->assertEquals([$eReminderTodo['id'] => FALSE], $this->getShown($week43), 'le rappel coché la semaine passée se lit encore');
		$this->assertEquals([$eReminder['id'] => TRUE, $eReminderTodo['id'] => FALSE], $this->getShown($week42), 'S42 passée ne garde pas son rappel barré');

		ReminderLib::uncheck(ReminderLib::getById($eReminder['id']));

		$this->assertEquals([$eReminder['id'] => FALSE, $eReminderTodo['id'] => FALSE], $this->getShown($week43), 'le rappel décoché ne revient pas à faire dans la semaine en cours');
		$this->assertEquals([$eReminder['id'] => FALSE, $eReminderTodo['id'] => FALSE], $this->getShown($week42), 'le rappel décoché reste barré dans la semaine de sa coche');

	}

	/**
	 * Chaque refus que `build()` ou une route peut lever sur un rappel a son message. Les gestes du
	 * pense-bête ne disent pas leur succès : la ligne touchée s'éclaire ou s'efface à sa place
	 * (`ReminderWeekTest`).
	 */
	public function testMessages(): void {

		foreach(['text.check', 'week.check', 'week.notPast', 'date.check', 'date.dateInWeek', 'updatePast', 'deletePast'] as $fail) {
			$this->assertTrue(AlertUi::getError('Reminder::'.$fail) !== NULL, 'le refus Reminder::'.$fail.' n\'a pas de message');
		}

	}

	/**
	 * Les refus de la construction d'un rappel neuf de la structure du décor.
	 */
	private function buildsCreate(array $input): array {

		$eReminder = new Reminder(['structure' => $this->eStructure]);

		$fw = new \FailWatch();
		$eReminder->build(ReminderLib::getPropertiesCreate(), $input, new \PropertyList('create'));

		return array_column($fw->get(), 1);

	}

	private function buildsUpdate(Reminder $eReminder, array $input): array {

		$eReminder = ReminderLib::getById($eReminder['id']);

		$fw = new \FailWatch();
		$eReminder->build(ReminderLib::getPropertiesUpdate(), $input, new \PropertyList('update'));

		return array_column($fw->get(), 1);

	}

	/**
	 * Les rappels que montre `$week`, ceux de la semaine puis les restés, avec l'état lu depuis elle.
	 *
	 * @return array<int, bool>
	 */
	private function getShown(string $week): array {

		$reminders = ReminderLib::getForWeek($this->eStructure, $week);
		$shown = [];

		foreach([$reminders['cReminder'], $reminders['cReminderBefore']] as $cReminder) {
			foreach($cReminder as $eReminder) {
				$shown[$eReminder['id']] = $eReminder['done'];
			}
		}

		return $shown;

	}

	private function createReminder(string $week, ?string $date, string $text, \structure\Structure $eStructure = new \structure\Structure()): Reminder {

		// Sans `build()` : il vérifierait le texte (1 à 250) et que le jour tombe dans la semaine, deux
		// choses que le décor pose lui-même, et que `testBuildDate()` et `testBuildText()` éprouvent.
		$eReminder = new Reminder([
			'structure' => $eStructure->empty() ? $this->eStructure : $eStructure,
			'week' => $week,
			'date' => $date,
			'text' => $text,
		]);

		ReminderLib::create($eReminder);

		return $eReminder;

	}

	private function connect(\user\User $eUser): void {

		\user\ConnectionLib::setOnline($eUser);
		\structure\MemberLib::clearCache();
		$this->forgetAccountingYear();

	}

}
?>
