<?php
namespace planning;

/**
 * Le temps d'une semaine (`TimeWeekLib`) et les composants qui le dessinent (`TimeWeekUi`), que la fiche de
 * temps lit : un jour, sa réglette, la semaine d'une personne ou de l'équipe, et qui la voit. Le bandeau du
 * temps de la Semaine et du Jour, que ces composants servaient d'abord (L6c), est retiré
 * (R-Retours-0410-soir, I1) : `testNoBand` garde qu'il ne revient ni dans ce que la page prépare, ni dans
 * une réponse, ni par la saisie d'une journée.
 *
 * Le jour du décor est le jeudi de la semaine 39 : trois jours passés, aujourd'hui, trois jours à venir.
 * Alice, connectée, a le planning complet et gère la ferme ; Bruno, au planning limité, devient présent
 * dans les cas qui ont besoin d'une seconde personne (`addBruno()`).
 *
 * Lancement : dev/test package=planning file=TimeWeek
 */
class TimeWeekTest extends HarvestFixtureTest {

	/**
	 * La part des interventions qui dépasse la journée se dessine au-delà de la poignée, en pointillés
	 * (R-Retours-0310, point 8) : de la journée jusqu'aux interventions, bornée au bout de l'échelle de 12 h.
	 * Une journée tenue, une journée à saisir et une journée qui remplit déjà l'échelle n'en portent pas.
	 */
	public function testRuleOverflow(): void {

		$date = \Clock::date();
		$uiTimeWeek = new TimeWeekUi();

		$this->assertTrue(str_contains($uiTimeWeek->getRule(TimeWeekLib::getDay($date, 1.0, 1.25), 'working-test', FALSE), 'class="planning-week-time-lane-overflow" style="left: 8.33%; width: 2.09%"'), 'le dépassement de 1h15 pour 1h00 n\'est pas dessiné de la poignée aux interventions');
		$this->assertFalse(str_contains($uiTimeWeek->getRule(TimeWeekLib::getDay($date, 5.0, 2.0), 'working-test', FALSE), 'planning-week-time-lane-overflow'), '2h00 d\'interventions pour 5h00 dessinent un dépassement');
		$this->assertTrue(str_contains($uiTimeWeek->getRule(TimeWeekLib::getDay($date, 11.0, 14.0), 'working-test', FALSE), 'class="planning-week-time-lane-overflow" style="left: 91.67%; width: 8.33%"'), 'le dépassement de 14h00 pour 11h00 ne s\'arrête pas au bout de l\'échelle');
		$this->assertFalse(str_contains($uiTimeWeek->getRule(TimeWeekLib::getDay($date, 12.0, 13.0), 'working-test', FALSE), 'planning-week-time-lane-overflow'), 'une journée de 12h00 qui remplit l\'échelle dessine un dépassement');
		$this->assertFalse(str_contains($uiTimeWeek->getRule(TimeWeekLib::getDay($date, NULL, 3.0), 'working-test', TRUE), 'planning-week-time-lane-overflow'), 'une journée à saisir dessine un dépassement');
		$this->assertFalse(str_contains($uiTimeWeek->getRule(TimeWeekLib::getDay($date, 3.0, 3.0), 'working-test', TRUE), 'planning-week-time-lane-overflow'), 'une journée tenue tout juste dessine un dépassement');

	}

	/**
	 * Une journée au-delà de l'échelle de 12 h se dit sur la réglette (R-Retours-0410-soir, T) : la classe se
	 * décide en PHP, posée à 15h45, absente à 12h00 tout juste et sur une journée à saisir. La réglette qu'on
	 * peut écrire porte la clé de sa tuile, celle qu'on lit n'en porte pas.
	 */
	public function testRuleBeyond(): void {

		$date = \Clock::date();
		$uiTimeWeek = new TimeWeekUi();

		$this->assertTrue(str_contains($uiTimeWeek->getRule(TimeWeekLib::getDay($date, 15.75, 0.0), 'working-test', TRUE), 'planning-time-rule-beyond'), 'une journée de 15h45 ne dit pas qu\'elle continue au-delà de 12h');
		$this->assertTrue(str_contains($uiTimeWeek->getRule(TimeWeekLib::getDay($date, 15.75, 0.0), 'working-test', FALSE), 'planning-time-rule-beyond'), 'une journée de 15h45 qu\'on lit seulement ne dit pas qu\'elle continue au-delà de 12h');
		$this->assertFalse(str_contains($uiTimeWeek->getRule(TimeWeekLib::getDay($date, 12.0, 0.0), 'working-test', TRUE), 'planning-time-rule-beyond'), 'une journée de 12h00 tout juste dit qu\'elle continue');
		$this->assertFalse(str_contains($uiTimeWeek->getRule(TimeWeekLib::getDay($date, NULL, 14.0), 'working-test', TRUE), 'planning-time-rule-beyond'), 'une journée à saisir dit qu\'elle continue');

		$this->assertTrue(str_contains($uiTimeWeek->getRule(TimeWeekLib::getDay($date, 3.0, 0.0), 'working-test', TRUE), 'data-working="working-test"'), 'la réglette qu\'on écrit ne porte pas la clé de sa tuile');
		$this->assertFalse(str_contains($uiTimeWeek->getRule(TimeWeekLib::getDay($date, 3.0, 0.0), 'working-test', FALSE), 'data-working'), 'la réglette qu\'on lit porte la clé d\'une tuile qu\'aucun geste n\'envoie');

	}

	/**
	 * La bulle de la poignée dit ce qu'elle pose, « Journée », au-dessus de la durée (R-Retours-0810, 179) :
	 * `PlanningTimeRule.showGhost()` lit le libellé sur la réglette qu'on écrit. Celle qu'on lit n'a pas de bulle.
	 */
	public function testRuleLabel(): void {

		$date = \Clock::date();
		$uiTimeWeek = new TimeWeekUi();

		$this->assertTrue(str_contains($uiTimeWeek->getRule(TimeWeekLib::getDay($date, 3.0, 0.0), 'working-test', TRUE), 'data-label="'.s("Journée").'"'), 'la réglette qu\'on écrit ne donne pas à sa bulle le libellé « Journée »');
		$this->assertTrue(str_contains($uiTimeWeek->getRule(TimeWeekLib::getDay($date, NULL, 0.0), 'working-test', TRUE), 'data-label="'.s("Journée").'"'), 'la réglette d\'une journée à saisir ne donne pas à sa bulle le libellé « Journée »');
		$this->assertFalse(str_contains($uiTimeWeek->getRule(TimeWeekLib::getDay($date, 3.0, 0.0), 'working-test', FALSE), 'data-label'), 'la réglette qu\'on lit porte le libellé d\'une bulle qu\'elle n\'ouvre pas');

	}

	/**
	 * La rangée des jours de la fiche (`util-days`, R-Retours-0410-soir/C4), un mercredi : lundi dépassé
	 * (1h15 d'interventions pour 1h00) remplit sa journée de ses interventions, sans la dépasser. La colonne d'un
	 * jour est celle d'une structure à horaires, la seule dont la fiche d'une personne garde la rangée des colonnes
	 * (`TimeDayUi::hasEntry()`).
	 */
	public function testDays(): void {

		$this->setClock('2026-09-30 10:00:00');

		$this->writeWorkingTime($this->getMember(), '2026-09-28', 1.0);
		$this->writeTime($this->getMember(), '2026-09-28', 1.25);

		[$document] = $this->renderTimeDayTimeRange();

		$days = $document->querySelector('#planning-time .util-days');

		if($this->assertTrue($days !== NULL, 'la fiche ne porte pas la rangée des jours') === FALSE) {
			return;
		}

		$this->assertEquals('height: 100%', $days->querySelector('#planning-time-day-2026-09-28 .util-day-done')?->getAttribute('style'), 'lundi, dépassé, ne remplit pas sa journée de ses interventions');

	}

	/**
	 * La colonne d'un jour à saisir qui porte des interventions dessine leur part à leur hauteur sur l'échelle
	 * de 12 h (R-Dettes-Ecran, n°6), et un jour saisi garde sa part rapportée à sa journée. Sous horaires, comme
	 * `testDays()`.
	 */
	public function testMissingDayColumn(): void {

		[$monday, , $wednesday] = array_slice(week_dates(\Clock::week()), 0, 3);

		$this->writeTime($this->getMember(), $monday, 1.75);
		$this->writeWorkingTime($this->getMember(), $wednesday, 5.0);
		$this->writeTime($this->getMember(), $wednesday, 2.0);

		[$document] = $this->renderTimeDayTimeRange();

		$column = $document->querySelector('#planning-time-day-'.$monday.' .util-day-column');

		if($this->assertTrue($column !== NULL, 'la colonne de lundi n\'est pas à saisir')) {
			$this->assertEquals('height: 14.58%', $column->querySelector('.util-day-done')?->getAttribute('style'), 'la part des 1h45 d\'interventions de lundi n\'est pas dessinée à sa hauteur');
		}

		$this->assertEquals('height: 41.67%', $document->querySelector('#planning-time-day-'.$wednesday.' .util-day-worked')?->getAttribute('style'), 'la journée de 5h00 de mercredi n\'est pas dessinée à sa hauteur');
		$this->assertEquals('height: 40%', $document->querySelector('#planning-time-day-'.$wednesday.' .util-day-worked > .util-day-done')?->getAttribute('style'), 'les 2h00 d\'interventions de mercredi ne sont plus rapportées à sa journée');

	}

	/**
	 * L'équipe dès deux personnes : chaque jour la somme des journées saisies, un jour où personne n'a saisi
	 * reste à saisir, et chaque personne garde ses propres jours. Sous deux personnes, l'équipe est refusée.
	 */
	public function testTeam(): void {

		$this->assertRefused(fn() => TimeWeekLib::read($this->eStructure, \Clock::week()), 'l\'équipe d\'une seule personne est acceptée');

		$eUserBruno = $this->addBruno();

		[$monday, $tuesday, $wednesday, , $friday] = array_slice(week_dates(\Clock::week()), 0, 5);

		$this->writeWorkingTime($this->getMember(), $monday, 7.0);
		$this->writeWorkingTime($eUserBruno, $monday, 5.0);
		$this->writeWorkingTime($eUserBruno, $tuesday, 4.0);
		$this->writeTime($eUserBruno, $monday, 1.5);
		$this->writeTime($eUserBruno, $wednesday, 0.75);

		$timeWeek = TimeWeekLib::read($this->eStructure, \Clock::week());
		$days = $timeWeek['days'];

		// Le jour de l'équipe porte aussi ses absents, une `Collection`, que `assertEquals()` ne compare pas par sa
		// valeur : elle se lit à part.
		$this->assertEquals(['holiday' => FALSE, 'unfilled' => FALSE, 'working' => 12.0, 'time' => 1.5, 'future' => FALSE, 'today' => FALSE, 'absent' => FALSE, 'absentPart' => NULL, 'overflow' => FALSE], array_diff_key($days[$monday], ['cUserAbsent' => TRUE]), 'lundi ne somme pas les journées et les interventions de l\'équipe');
		$this->assertEquals([], $days[$monday]['cUserAbsent']->getIds(), 'lundi de l\'équipe nomme un absent');
		$this->assertEquals(4.0, $days[$tuesday]['working'], 'mardi ne porte pas la journée de Bruno');
		$this->assertEquals(NULL, $days[$wednesday]['working'], 'un jour où personne n\'a saisi n\'est pas à saisir');
		$this->assertEquals(0.75, $days[$wednesday]['time'], 'mercredi, à saisir, perd ses interventions');
		$this->assertTrue($days[\Clock::date()]['today'], 'aujourd\'hui ne se marque pas');
		$this->assertTrue($days[$friday]['future'], 'vendredi n\'est pas à venir');

		$this->assertEquals([], array_diff([$this->getMember()['id'], $eUserBruno['id']], $timeWeek['cUserTime']->getIds()), 'l\'équipe ne porte pas ses deux personnes');
		$this->assertEquals(7.0, $timeWeek['cUserTime'][$this->getMember()['id']]['timeDays'][$monday]['working'], 'lundi d\'Alice porte la journée de l\'équipe');
		$this->assertEquals(NULL, $timeWeek['cUserTime'][$this->getMember()['id']]['timeDays'][$tuesday]['working'], 'mardi d\'Alice prend la journée de Bruno');
		$this->assertEquals(1.5, $timeWeek['cUserTime'][$eUserBruno['id']]['timeDays'][$monday]['time'], 'lundi de Bruno ne porte pas ses interventions');

	}

	/**
	 * Des interventions au-delà d'une journée saisie sont en défaut (R-Retours-1210, GJ), à la condition de
	 * l'ancien planning : au centième près, et jamais sur une journée à saisir. Trois interventions de 0h20
	 * additionnées en `float32`, comme MySQL les rend, ne dépassent pas une journée de 1h00.
	 */
	public function testOverflow(): void {

		$date = \Clock::date();
		$third = unpack('g', pack('g', 1 / 3))[1];

		$this->assertTrue(TimeWeekLib::getDay($date, 1.0, 1.25)['overflow'], '1h15 d\'interventions pour une journée de 1h00 ne sont pas en défaut');
		$this->assertFalse(TimeWeekLib::getDay($date, 1.0, 1.0)['overflow'], 'une journée tenue tout juste est en défaut');
		$this->assertFalse(TimeWeekLib::getDay($date, NULL, 3.0)['overflow'], 'une journée à saisir est en défaut');
		$this->assertTrue($third + $third + $third > 1.0, 'trois tiers en float32 ne dépassent plus 1.0, le cas suivant ne prouve rien');
		$this->assertFalse(TimeWeekLib::getDay($date, 1.0, $third + $third + $third)['overflow'], 'trois interventions de 0h20 dépassent une journée de 1h00');

	}

	/**
	 * Le jour de l'équipe est en défaut quand une personne l'est, et pas sur sa somme (R-Retours-1210, GJ) :
	 * mardi, Bruno dépasse sa journée sans que la somme de l'équipe dépasse la sienne ; mercredi, la somme
	 * dépasse par les interventions de Bruno, qui n'a pas de journée, sans que personne ne soit en défaut.
	 */
	public function testTeamOverflow(): void {

		$eUserBruno = $this->addBruno();

		[, $tuesday, $wednesday] = array_slice(week_dates(\Clock::week()), 0, 3);

		$this->writeWorkingTime($this->getMember(), $tuesday, 7.0);
		$this->writeWorkingTime($eUserBruno, $tuesday, 1.0);
		$this->writeTime($eUserBruno, $tuesday, 2.0);
		$this->writeWorkingTime($this->getMember(), $wednesday, 3.0);
		$this->writeTime($eUserBruno, $wednesday, 4.0);

		$timeWeek = TimeWeekLib::read($this->eStructure, \Clock::week());

		$this->assertTrue($timeWeek['cUserTime'][$eUserBruno['id']]['timeDays'][$tuesday]['overflow'], 'mardi, Bruno n\'est pas en défaut');
		$this->assertTrue($timeWeek['days'][$tuesday]['overflow'], 'mardi, l\'équipe n\'est pas en défaut quand Bruno l\'est');
		$this->assertFalse($timeWeek['days'][$wednesday]['overflow'], 'mercredi, l\'équipe est en défaut sur sa somme sans que personne le soit');

	}

	/**
	 * La somme de la semaine (R-Retours-1210, 294) additionne les sept jours, pour une personne comme pour
	 * l'équipe : les journées saisies seules, les interventions des jours à saisir comprises. Une semaine sans
	 * journée saisie reste à saisir, et les filtres de la fiche ne changent rien à la somme.
	 */
	public function testTotal(): void {

		$date = \Clock::date();

		$this->assertEquals(['working' => NULL, 'time' => 0.0], TimeWeekLib::getTotal([]), 'une semaine sans jour porte une somme');
		$this->assertEquals(['working' => NULL, 'time' => 2.5], TimeWeekLib::getTotal([TimeWeekLib::getDay($date, NULL, 1.75), TimeWeekLib::getDay($date, NULL, 0.75)]), 'une semaine sans journée saisie n\'est pas à saisir, ou perd ses interventions');
		$this->assertEquals(['working' => 0.0, 'time' => 0.0], TimeWeekLib::getTotal([TimeWeekLib::getDay($date, 0.0, 0.0), TimeWeekLib::getDay($date, NULL, 0.0)]), 'une journée saisie à zéro se lit comme une journée à saisir');

		$eUserBruno = $this->addBruno();

		[$monday, $tuesday, $wednesday] = array_slice(week_dates(\Clock::week()), 0, 3);

		$this->writeWorkingTime($this->getMember(), $monday, 7.0);
		$this->writeWorkingTime($this->getMember(), $wednesday, 5.5);
		$this->writeTime($this->getMember(), $monday, 1.25);
		$this->writeTime($this->getMember(), $tuesday, 2.0);
		$this->writeWorkingTime($eUserBruno, $monday, 5.0);
		$this->writeTime($eUserBruno, $wednesday, 0.75);

		$eStructure = $this->eStructure;
		$cTask = TaskWeekLib::get($eStructure, \Clock::week());

		$cUserSheet = TimeDayLib::getUsers($eStructure, \Clock::week());
		$eUserAlice = $cUserSheet[$this->getMember()['id']];

		$timeDay = TimeDayLib::get($eStructure, \Clock::week(), $cTask, $monday, $eUserAlice, FALSE, FALSE);

		$this->assertEquals(['working' => 12.5, 'time' => 3.25], $timeDay['total'], 'la semaine d\'Alice n\'additionne pas ses deux journées saisies et ses interventions, mardi à saisir compris');
		$this->assertEquals($timeDay['total'], TimeDayLib::get($eStructure, \Clock::week(), $cTask, $wednesday, $eUserAlice, TRUE, TRUE)['total'], 'le masque, le filtre ou le jour de la fiche changent la somme de la semaine');
		$this->assertEquals(['working' => 5.0, 'time' => 0.75], TimeDayLib::get($eStructure, \Clock::week(), $cTask, $monday, $cUserSheet[$eUserBruno['id']], FALSE, FALSE)['total'], 'la semaine de Bruno prend le temps d\'Alice');

		$this->assertEquals(['working' => 17.5, 'time' => 4.0], TimeWeekLib::read($eStructure, \Clock::week())['total'], 'la semaine de l\'équipe n\'additionne pas celles d\'Alice et de Bruno');

	}

	/**
	 * Bruno, au planning limité, ne voit que lui : l'équipe lui est refusée, Alice comprise.
	 */
	public function testLimited(): void {

		$eUserBruno = $this->addBruno();

		\user\ConnectionLib::setOnline($eUserBruno);

		$this->assertRefused(fn() => TimeWeekLib::read($this->eStructure, \Clock::week()), 'Bruno voit l\'équipe');

	}

	/**
	 * R-Retours-0410-soir, I1 : le bandeau du temps a quitté la Semaine et le Jour. La page ne le prépare
	 * plus, une journée saisie et une coche ne le relisent plus, ses routes n'existent plus, et une
	 * journée saisie depuis lui (`origin=week`) est refusée sans rien écrire.
	 */
	public function testNoBand(): void {

		$monday = week_date_day(\Clock::week(), 1);

		$this->writeWorkingTime($this->getMember(), $monday, 7.5);
		$this->writeTime($this->getMember(), $monday, 2.0);

		foreach(['la Semaine' => $this->renderTasks(...), 'le Jour' => $this->renderTaskDay(...)] as $screen => $render) {

			[, $data] = $render();

			$this->assertFalse(property_exists($data, 'timeWeek'), $screen.' lit encore le bandeau du temps');

		}

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), \Clock::date());

		foreach(['la Semaine' => fn() => $this->callTask('doDone', $eTask), 'le Jour' => fn() => $this->callDay('doTodo', ['task' => $eTask['id']])] as $screen => $call) {

			[, $instructions, $action] = $call();

			if($this->assertTrue($action instanceof \ViewAction, 'le geste sur '.$screen.' est refusé : '.implode(', ', $this->getFails($action)))) {
				$this->assertEquals([], $this->getInstructions($instructions), 'le geste sur '.$screen.' redessine encore le bandeau');
			}

		}

		foreach(['taskWeek', 'taskDay'] as $page) {
			$action = $this->executePage('planning/'.$page.':time', ['id' => $this->eStructure['id'], 'week' => \Clock::week()], 'GET');
			$this->assertFalse($action instanceof \ViewAction, 'la route du bandeau répond encore sur '.$page);
		}

		$action = $this->executePage('planning/timeDay:doWorkingTime', ['id' => $this->eStructure['id'], 'week' => \Clock::week(), 'date' => $monday, 'time' => '3', 'origin' => 'week'], 'POST');
		$this->assertTrue($action instanceof \NotExpectedAction, 'une journée saisie depuis le bandeau est acceptée');
		$this->assertEquals(7.5, $this->getWorkingTime($this->getMember(), $monday), 'une journée saisie depuis le bandeau écrit');

	}

	/**
	 * Les absents de l'équipe (R-Retours-1210, FH et FS) : portés par chaque jour de l'équipe où ils sont absents,
	 * et sortis du tableau de ce jour-là, sauf qui y a une journée ou du temps sur les interventions, que le jour
	 * de l'équipe additionne. La page lit les absents du jour de la fiche et pose les personnes de son tableau.
	 */
	public function testAbsents(): void {

		$eUserAlice = $this->getMember();
		$eUserBruno = $this->addBruno();

		[$monday, $tuesday, $wednesday] = array_slice(week_dates(\Clock::week()), 0, 3);

		$this->addAbsence($eUserBruno, $monday, $monday);
		$this->addAbsence($eUserAlice, $tuesday, $tuesday);
		$this->writeWorkingTime($eUserAlice, $tuesday, 4.0);
		$this->addAbsence($eUserBruno, $wednesday, $wednesday);
		$this->writeTime($eUserBruno, $wednesday, 0.5);

		$timeWeek = TimeWeekLib::read($this->eStructure, \Clock::week());

		$this->assertEquals([$eUserBruno['id']], $timeWeek['days'][$monday]['cUserAbsent']->getIds(), 'lundi ne nomme pas Bruno seul absent');
		$this->assertEquals([$eUserAlice['id']], $timeWeek['days'][$tuesday]['cUserAbsent']->getIds(), 'mardi ne nomme pas Alice seule absente');
		$this->assertEquals([], $timeWeek['days'][\Clock::date()]['cUserAbsent']->getIds(), 'aujourd\'hui nomme un absent');
		$this->assertTrue($timeWeek['cUserTime'][$eUserBruno['id']]['timeDays'][$monday]['absent'], 'le lundi de Bruno ne dit pas son absence');
		$this->assertFalse($timeWeek['days'][$monday]['absent'], 'le jour de l\'équipe se dit absent');

		$this->assertEquals([$eUserAlice['id']], TimeWeekLib::getPresents($timeWeek['cUserTime'], $monday)->getIds(), 'Bruno, absent lundi sans journée ni temps, reste au tableau');
		$this->assertEquals([], array_diff([$eUserAlice['id'], $eUserBruno['id']], TimeWeekLib::getPresents($timeWeek['cUserTime'], $tuesday)->getIds()), 'Alice, absente mardi avec une journée, quitte le tableau');
		$this->assertEquals([], array_diff([$eUserAlice['id'], $eUserBruno['id']], TimeWeekLib::getPresents($timeWeek['cUserTime'], $wednesday)->getIds()), 'Bruno, absent mercredi avec du temps, quitte le tableau');

		$data = $this->renderTimeDay(['user' => TimeWeekLib::TEAM, 'date' => $monday])[1];

		$this->assertEquals([$eUserBruno['id']], $data->timeWeek['days'][$monday]['cUserAbsent']->getIds(), 'la page ne pose pas Bruno seul absent lundi');
		$this->assertEquals([$eUserAlice['id']], $data->cUserPresent->getIds(), 'la page garde Bruno au tableau de lundi');

	}

	/**
	 * Le « + » d'un jour sans journée (R-Retours-1810) : un jour strictement passé, dans une période de
	 * présence, sans absence validée ni jour férié, week-end compris. Le jour férié se dit aussi (`holiday`). La semaine 46 porte le 11 novembre, un
	 * mercredi ; Bruno y est présent à partir du mardi. Le jour du cas est le samedi, puis le lundi suivant pour
	 * lire le week-end passé.
	 */
	public function testUnfilled(): void {

		$this->setClock('2026-11-14 10:00:00');

		[$monday, $tuesday, $wednesday, $thursday, $friday, $saturday, $sunday] = week_dates('2026-W46');

		$eUserBruno = $this->getMemberPlanning();
		$this->addPresence($eUserBruno, $tuesday);

		$this->addAbsence($eUserBruno, $tuesday, $tuesday);
		$this->writeWorkingTime($eUserBruno, $thursday, 7.0);

		$days = TimeWeekLib::read($this->eStructure, '2026-W46')['cUserTime'][$eUserBruno['id']]['timeDays'];

		$this->assertFalse($days[$monday]['unfilled'], 'le lundi de Bruno, avant sa présence, attend une journée');
		$this->assertFalse($days[$tuesday]['unfilled'], 'le mardi d\'absence de Bruno attend une journée');
		$this->assertFalse($days[$wednesday]['unfilled'], 'le 11 novembre de Bruno attend une journée');
		$this->assertTrue($days[$wednesday]['holiday'], 'le 11 novembre de Bruno n\'est pas férié');
		$this->assertFalse($days[$thursday]['holiday'], 'le 12 novembre de Bruno est férié');
		$this->assertFalse($days[$thursday]['unfilled'], 'le jeudi saisi de Bruno attend une journée');
		$this->assertTrue($days[$friday]['unfilled'], 'le vendredi passé de Bruno, sans journée, ne la réclame pas');
		$this->assertFalse($days[$saturday]['unfilled'], 'aujourd\'hui sans journée attend déjà une journée');
		$this->assertFalse($days[$sunday]['unfilled'], 'dimanche, à venir, attend une journée');

		$data = $this->renderTimeDay(['user' => $eUserBruno['id']], '2026-W46')[1];

		$this->assertTrue($data->timeDay['days'][$friday]['unfilled'], 'la fiche de Bruno ne réclame pas la journée de vendredi');
		$this->assertFalse($data->timeDay['days'][$saturday]['unfilled'], 'la fiche de Bruno réclame la journée d\'aujourd\'hui');

		$this->setClock('2026-11-16 10:00:00');

		$days = TimeWeekLib::read($this->eStructure, '2026-W46')['days'];

		$this->assertTrue($days[$saturday]['unfilled'], 'le samedi passé de l\'équipe, sans journée, ne la réclame pas');
		$this->assertTrue($days[$sunday]['unfilled'], 'le dimanche passé de l\'équipe, sans journée, ne la réclame pas');
		$this->assertFalse($days[$wednesday]['unfilled'], 'le 11 novembre de l\'équipe réclame une journée');

	}

	/**
	 * Un refus de `TimeWeekLib::read()`, qui lève l'action que la page rend.
	 */
	private function assertRefused(\Closure $read, string $message): void {

		try {
			$read();
			$this->assertFail($message);
		} catch(\NotExpectedAction) {
			$this->assertPass();
		}

	}

	/**
	 * Les sélecteurs qu'une réponse remplace sous l'identifiant du bandeau (`#planning-week-time…`), dans leur
	 * ordre (`[mode, sélecteur, [[méthode, arguments]…]]`, `Template::formatInstructions()`).
	 */
	private function getInstructions(array $instructions): array {

		$selectors = [];

		foreach($instructions as $instruction) {

			// Une instruction `js` n'a que deux membres, sans sélecteur.
			if($instruction[0] !== 'js' and str_starts_with($instruction[1], '#planning-week-time')) {
				$selectors[] = $instruction[1];
			}

		}

		return $selectors;

	}

	/**
	 * Bruno présent depuis le début de la saison, dans la transaction du décor.
	 */
	private function addBruno(): \user\User {

		$eUserBruno = $this->getMemberPlanning();

		$this->addPresence($eUserBruno);

		return $eUserBruno;

	}

	/**
	 * Une absence de `$from` à `$to` inclus, dans la transaction du décor.
	 */
	private function addAbsence(\user\User $eUser, string $from, string $to): void {

		$this->enableHr();

		// Sans `build()`, qui vérifierait les dates d'un formulaire : le décor veut exactement ces jours.
		\hr\AbsenceLib::create(new \hr\Absence([
			'structure' => $this->eStructure,
			'user' => $eUser,
			'from' => $from.' 00:00:00',
			'to' => $to.' 23:59:59',
			'duration' => 1.0,
			'type' => \hr\Absence::VACATION,
		]));

	}

	private function writeWorkingTime(\user\User $eUser, string $date, float $time): void {

		// Hors build() : le décor sème les jours de la semaine en cours, et `date.check` refuserait ceux qui suivent
		// aujourd'hui selon le jour où le test tourne ; le décor veut exactement ces journées.
		\hr\WorkingTimeLib::create(new \hr\WorkingTime([
			'structure' => $this->eStructure,
			'user' => $eUser,
			'date' => $date,
			'time' => $time
		]));

	}

	/**
	 * Du temps sur une intervention neuve, faite le jour donné.
	 */
	private function writeTime(\user\User $eUser, string $date, float $time): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), $date);

		// Sans `build()`, qui vérifierait la durée d'un formulaire : le décor veut exactement ce temps.
		\series\TimesheetLib::writeTimeForDay($eTask, new \series\Timesheet([
			'structure' => $this->eStructure,
			'user' => $eUser,
			'date' => $date,
			'time' => $time,
		]));

	}

	private function getWorkingTime(\user\User $eUser, string $date): ?float {

		$eWorkingTime = \hr\WorkingTime::model()
			->select(['time'])
			->whereStructure($this->eStructure)
			->whereUser($eUser)
			->whereDate($date)
			->get();

		return $eWorkingTime->empty() ? NULL : $eWorkingTime['time'];

	}

	/**
	 * La fiche sous horaires, posés sur la configuration chargée, que chaque rendu relit (`Structure::conf()`) :
	 * rien ne s'écrit en base, et la configuration reprend sa valeur même quand le rendu échoue.
	 */
	private function renderTimeDayTimeRange(): array {

		$eConfiguration = \structure\StructureLib::getById($this->eStructure['id'])->conf();
		$timeRange = $eConfiguration['timeRange'];

		$eConfiguration['timeRange'] = TRUE;

		try {
			return $this->renderTimeDay();
		} finally {
			$eConfiguration['timeRange'] = $timeRange;
		}

	}

}
?>
