<?php
namespace planning;

/**
 * Le temps de travail des récoltes sous la quantité du jour, avec et sans la saisie rapide (lot CN-b, retours 302 et 303) :
 * la même ligne dans les deux modes, les personnes qui ont passé du temps sur la récolte du jour en vignettes,
 * puis le temps de toutes. La ligne se lit et ne s'écrit pas : le temps s'écrit par personne dans le tiroir du
 * jour, que rouvre la quantité.
 *
 * Lancement : dev/test package=planning file=HarvestTimeGrid
 */
class HarvestTimeGridTest extends HarvestFixtureTest {

	/**
	 * Sans la saisie rapide, la quantité du jour rouvre le tiroir en remplacement et le « + » à côté l'ouvre en ajout.
	 */
	public function testLineInBothModes(): void {

		$eUserOnline = $this->getMember();
		$eUserBruno = $this->getMemberPlanning();

		[$eCultivation, $eTask] = $this->createHarvested(2.0);

		$this->writeTime($eTask, $eUserOnline, \Clock::date(), 0.5);
		$this->writeTime($eTask, $eUserBruno, \Clock::date(), 0.25);

		[$document] = $this->render();

		$day = $this->getDay($document, $eCultivation, \Clock::date());

		$this->assertTrue(str_contains($day?->querySelector('a.util-quick')?->getAttribute('data-planning-harvest-open') ?? '', 'mode=replace'), 'sans la saisie rapide, la quantité et son temps n\'ouvrent pas le tiroir en remplacement');
		$this->assertTrue(str_contains($day?->querySelector('.planning-harvest-button')?->getAttribute('data-planning-harvest-open') ?? '', 'mode=add'), 'sans la saisie rapide, le « + » ne reste pas à côté de la quantité');

	}

	/**
	 * Un temps posé avant la pesée rouvre le tiroir en remplacement sans la saisie rapide.
	 */
	public function testTimeAlone(): void {

		$eUserOnline = $this->getMember();

		[$eCultivation, $eTask] = $this->createHarvested(2.0);
		$tuesday = week_date_day(\Clock::week(), 2);

		$this->writeTime($eTask, $eUserOnline, $tuesday, 1.0);

		[$document] = $this->render();

		$day = $this->getDay($document, $eCultivation, $tuesday);

		$this->assertTrue(str_contains($day?->querySelector('a.util-quick')?->getAttribute('data-planning-harvest-open') ?? '', 'mode=replace'), 'sans la saisie rapide, le temps seul n\'ouvre pas le tiroir en remplacement');

	}

	/**
	 * Un jour récolté sur plusieurs interventions somme le temps de toutes : le cas de zzN1 Caisses, lundi 12/10 sur la structure de recette, quatre
	 * interventions, 44,5 kg et 6 bottes en 3 h 06, que l'écran disait « en 0h21 », le temps d'une seule (verdict
	 * du relecteur, 13/10).
	 *
	 * Le tiroir qu'ouvre le jour ne montre que le temps de l'intervention qu'il écrit, celle de la semaine :
	 * il garde le reste du jour, et la somme des deux est le temps de la case.
	 */
	public function testSumsInterventions(): void {

		$eUserOnline = $this->getMember();
		$eUserBruno = $this->getMemberPlanning();

		$this->addPresence($eUserBruno);

		[$eCultivation, $eTask] = $this->createHarvested(20.0);

		$eTaskSecond = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->createHarvest($eTaskSecond, 20.0, \Clock::date());

		$eTaskThird = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->createHarvest($eTaskThird, 4.5, \Clock::date());

		$eCultivationBunch = clone $eCultivation;
		$eCultivationBunch['mainUnit'] = \series\Task::BUNCH;

		$eTaskBunch = $this->createTaskHarvest($eCultivationBunch, \Clock::week(), \Clock::date());
		$this->createHarvest($eTaskBunch, 6.0, \Clock::date());

		$this->writeTime($eTask, $eUserOnline, \Clock::date(), 1.0);
		$this->writeTime($eTaskSecond, $eUserBruno, \Clock::date(), 1.0);
		$this->writeTime($eTaskThird, $eUserOnline, \Clock::date(), 0.75);
		$this->writeTime($eTaskBunch, $eUserOnline, \Clock::date(), 0.35);

		[, $data] = $this->render();

		$this->assertEquals(3.1, $data->cCultivation[$eCultivation['id']]['harvestTimeDays'][\Clock::date()] ?? 0.0, 'le temps du jour ne somme pas celui des quatre interventions récoltées');

		[$data] = $this->call('open', ['cultivation' => $eCultivation['id'], 'date' => \Clock::date(), 'mode' => 'replace'], 'GET');

		if($this->assertTrue($data !== NULL, 'le tiroir du jour ne s\'ouvre pas en remplacement')) {

			$timeDrawer = array_sum(array_map(fn(array $spent) => $spent[\Clock::date()], $data->cUserTime->getColumn('spent')));

			// La récolte de la semaine est la dernière en kilos, l'unité de la culture : celle en bottes, faite
			// après elle, ne la remplace pas (retour 319).
			$this->assertEquals(0.75, round($timeDrawer, 2), 'le tiroir ne porte pas le temps de la récolte de la semaine');
			$this->assertEquals(2.35, $data->timeKept, 'le tiroir ne compte pas le temps du jour qu\'il ne remplace pas, celui des trois autres interventions');

		}

		[$data] = $this->call('open', ['cultivation' => $eCultivation['id'], 'date' => \Clock::date(), 'mode' => 'add'], 'GET');

		$this->assertTrue($data !== NULL, 'le tiroir du jour ne s\'ouvre pas en ajout');

	}

	/**
	 * Une personne partie de la structure garde son temps sur l'intervention : il compte dans la durée, sans
	 * vignette (`cUserTimeDays` de `HarvestWeekLib::get()`), et la ligne ne porte que la durée quand elle seule
	 * a récolté.
	 */
	public function testUserGone(): void {

		$eUserOnline = $this->getMember();

		$eUserGone = \user\User::model()
			->select(\user\User::getSelection())
			->whereEmail('decor.sans.structure@example.org')
			->get();

		if($this->assertDecorPresent($eUserGone->notEmpty() and \structure\MemberLib::getUsersByStructure($this->eStructure)->offsetExists($eUserGone['id']) === FALSE, 'decor.sans.structure@example.org, hors de la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		[$eCultivation, $eTask] = $this->createHarvested(2.0);

		$this->writeTime($eTask, $eUserGone, \Clock::date(), 0.4);

		$this->assertEquals([0.4, []], $this->readTimeDay($eCultivation, \Clock::date()), 'le temps d\'une personne partie ne se lit pas seul, sans vignette');

		$this->writeTime($eTask, $eUserOnline, \Clock::date(), 0.5);

		$this->assertEquals([0.9, [$eUserOnline['id']]], $this->readTimeDay($eCultivation, \Clock::date()), 'le temps d\'une personne partie ne compte pas à côté de celui d\'un membre, ou elle a une vignette');

	}

	/**
	 * Un salarié lit la même ligne que qui gère la structure, ses collègues en vignettes et le temps de tous :
	 * elle ne dit que ce que le pied d'une carte de la Semaine lui montre déjà. Son tiroir, lui, ne lui rend que
	 * son propre temps (`testEntryJoinsDayTime()`).
	 */
	public function testEmployee(): void {

		$eUserOnline = $this->getMember();
		$eUserBruno = $this->getMemberPlanning();

		[$eCultivation, $eTask] = $this->createHarvested(2.0);

		$this->writeTime($eTask, $eUserOnline, \Clock::date(), 0.5);
		$this->writeTime($eTask, $eUserBruno, \Clock::date(), 0.25);

		$line = [$this->getMemberNames([$eUserOnline, $eUserBruno]), '0h45'];

		$this->withRoleEmployee($eUserBruno, function() use($eCultivation, $line) {

			foreach(['sans la saisie rapide' => FALSE, 'en saisie rapide' => TRUE] as $case => $quick) {

				[$document] = $this->render($quick);

				$this->assertEquals($line, $this->readLine($this->getDay($document, $eCultivation, \Clock::date())), $case.', un salarié ne lit pas les personnes du jour et le temps de toutes');

			}

		});

	}

	/**
	 * Le temps de deux personnes écrit par le tiroir sur un jour dont la quantité ne change pas : la réponse
	 * éclaire la case dont le temps et les personnes viennent de changer, avec ou sans la saisie rapide. Le
	 * jour étant récolté, la saisie rapide le rend aussi par sa quantité (V52). La même saisie rejouée
	 * n'éclaire plus rien.
	 */
	public function testWriteLightsCase(): void {

		$eUserOnline = $this->getMember();
		$eUserBruno = $this->getMemberPlanning();

		$this->addPresence($eUserBruno);

		foreach(['sans la saisie rapide' => FALSE, 'en saisie rapide' => TRUE] as $case => $quick) {

			$this->setQuick($quick);

			[$eCultivation] = $this->createHarvested(2.0);

			$input = [
				'cultivation' => $eCultivation['id'],
				'date' => \Clock::date(),
				'mode' => 'replace',
				'harvestDay' => '2',
				'time' => [$eUserOnline['id'] => '0.5', $eUserBruno['id'] => '0.25'],
			];

			[, $instructions, $action] = $this->call('doHarvest', $input, 'POST');

			if($this->assertTrue($action instanceof \ViewAction, $case.', le temps de deux personnes est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
				continue;
			}

			$nodes = $this->getHighlighted($instructions);

			if($this->assertEquals(1, count($nodes), $case.', la réponse n\'éclaire pas une case, et une seule, quand seul le temps change')) {

				$this->assertEquals(
					[TRUE, 'planning-harvest-'.$eCultivation['id'], \Clock::date()],
					[$nodes[0]->classList->contains('planning-harvest-day-quantities'), $nodes[0]->closest('[id^="planning-harvest-"]')?->getAttribute('id'), $nodes[0]->getAttribute('data-date')],
					$case.', la case éclairée n\'est pas celle du jour écrit, dans son mode'
				);

			}

			[, $instructions, $action] = $this->call('doHarvest', $input, 'POST');

			$this->assertTrue($action instanceof \ViewAction, $case.', la même saisie rejouée est refusée : '.implode(', ', $this->getFails($action)));
			$this->assertEquals([], $this->getJs($instructions, 'highlight'), $case.', la réponse éclaire une case qui montre ce qu\'elle montrait');

		}

	}

	/**
	 * Le tiroir et la feuille ne montrent que le temps des personnes qu'ils rendent, sur l'intervention qu'ils
	 * écrivent, et gardent le reste du jour : leur somme est le temps de la case, quel que soit ce que la
	 * personne connectée a le droit de voir. Trois cas le font varier : qui gère la structure voit toute
	 * l'équipe, un salarié ne reçoit que lui-même (`TimeTeamLib::getUsersForWeek()`), et une personne partie
	 * garde son temps sur l'intervention sans plus être rendue à personne.
	 */
	public function testEntryJoinsDayTime(): void {

		$eUserOnline = $this->getMember();
		$eUserBruno = $this->getMemberPlanning();

		$this->addPresence($eUserBruno);

		[$eCultivation, $eTask] = $this->createHarvested(20.0);

		$eTaskSecond = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->createHarvest($eTaskSecond, 5.0, \Clock::date());

		$this->writeTime($eTask, $eUserOnline, \Clock::date(), 1.0);
		$this->writeTime($eTask, $eUserBruno, \Clock::date(), 0.5);
		$this->writeTime($eTaskSecond, $eUserOnline, \Clock::date(), 0.75);
		$this->writeTime($eTaskSecond, $eUserBruno, \Clock::date(), 0.25);

		// Qui gère la structure : les deux personnes de l'intervention, le reste sur l'autre intervention.
		$this->checkEntryJoinsDayTime($eCultivation, 2.5, [$eUserBruno['id'], $eUserOnline['id']], 'qui gère la structure');

		$eUserGone = \user\User::model()
			->select(\user\User::getSelection())
			->whereEmail('decor.sans.structure@example.org')
			->get();

		if($this->assertDecorPresent($eUserGone->notEmpty() and \structure\MemberLib::getUsersByStructure($this->eStructure)->offsetExists($eUserGone['id']) === FALSE, 'decor.sans.structure@example.org, hors de la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		$this->writeTime($eTask, $eUserGone, \Clock::date(), 0.4);
		$this->writeTime($eTaskSecond, $eUserGone, \Clock::date(), 0.4);

		// Une personne partie : son temps compte dans la case, sur l'intervention du tiroir aussi, sans qu'elle
		// soit rendue.
		$this->checkEntryJoinsDayTime($eCultivation, 3.3, [$eUserBruno['id'], $eUserOnline['id']], 'une personne partie');

		// Un salarié : lui seul, le temps de ses collègues sur la même intervention passe dans le reste.
		$this->withRoleEmployee($eUserBruno, fn() => $this->checkEntryJoinsDayTime($eCultivation, 3.3, [$eUserBruno['id']], 'un salarié'));

	}

	/**
	 * Depuis une carte de la Semaine, la saisie prend l'intervention de la carte, que la case du jour ne compte
	 * pas toujours : la seconde de deux récoltes à faire n'est ni récoltée ce jour-là ni celle que la liste
	 * désigne, et du temps a pu y être noté avant la pesée. Ce temps ne se retranche alors pas de celui de la
	 * case : le reste dit est tout le temps de la case, que la carte porte moins de temps qu'elle ou davantage.
	 */
	public function testEntryFromCardOutOfDayTime(): void {

		$eUserOnline = $this->getMember();

		[$eCultivation, $eTaskHarvested] = $this->createHarvested(20.0);

		$eTaskFirst = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week(), \Clock::date());
		$eTaskCard = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week(), \Clock::date());

		$this->writeTime($eTaskHarvested, $eUserOnline, \Clock::date(), 1.0);
		$this->writeTime($eTaskFirst, $eUserOnline, \Clock::date(), 0.25);

		foreach(['une carte au temps plus court que la case' => 0.5, 'une carte au temps plus long que la case' => 2.0] as $case => $timeCard) {

			$this->writeTime($eTaskCard, $eUserOnline, \Clock::date(), $timeCard);

			[, $data] = $this->render();

			$timeDay = $data->cCultivation[$eCultivation['id']]['harvestTimeDays'][\Clock::date()] ?? 0.0;

			if($this->assertDecorPresent($timeDay === 1.25, $case.' : une case qui compte la récolte du jour et la première à faire, 1,25 h, sans l\'intervention '.$eTaskCard['id'].' de la carte') === FALSE) {
				return;
			}

			foreach(['le tiroir' => [], 'la feuille' => ['sheet' => 1]] as $entry => $input) {

				[$data] = $this->call('open', ['origin' => 'semaine', 'task' => $eTaskCard['id'], 'cultivation' => $eCultivation['id'], 'unplanned' => '', 'folded' => '', 'mode' => 'replace'] + $input, 'GET');

				if($this->assertTrue($data !== NULL, $case.' : '.$entry.' ne s\'ouvre pas depuis la carte') === FALSE) {
					continue;
				}

				$timeShown = array_sum(array_map(fn(array $spent) => $spent[$data->date], $data->cUserTime->getColumn('spent')));

				$this->assertEquals([\Clock::date(), $timeCard], [$data->date, round($timeShown, 2)], $case.' : '.$entry.' ne montre pas le temps de la carte au jour qu\'elle propose');
				$this->assertEquals($timeDay, $data->timeKept, $case.' : le reste que dit '.$entry.' n\'est pas le temps de la case, qui ne compte pas l\'intervention de la carte');

			}

		}

	}

	/**
	 * Un temps hors du centième d'heure, comme les dix minutes de l'ancien planning (0,166667 h) : la case
	 * l'arrondit personne par personne, et le reste se calcule sur les mêmes arrondis. Sans cela, deux
	 * personnes à dix minutes feraient dire « compte aussi 0h01 » à une saisie qui montre tout le temps du jour.
	 */
	public function testEntryTimeOutOfHundredth(): void {

		$eUserOnline = $this->getMember();
		$eUserBruno = $this->getMemberPlanning();

		$this->addPresence($eUserBruno);

		[$eCultivation, $eTask] = $this->createHarvested(20.0);

		foreach([$eUserOnline, $eUserBruno] as $eUser) {

			// Sans `build()` ni `writeTimeForDay()`, qui arrondit l'écart au centième : le décor veut le sixième
			// d'heure que l'ancien planning écrit par `writeByTasks()`.
			\series\TimesheetLib::writeByTasks(new \Collection([$eTask]), new \series\Timesheet([
				'structure' => $this->eStructure,
				'user' => $eUser,
				'date' => \Clock::date(),
			]), 1 / 6, 'fair');

		}

		foreach(['le tiroir' => [], 'la feuille' => ['sheet' => 1]] as $entry => $input) {

			[$data] = $this->call('open', ['cultivation' => $eCultivation['id'], 'date' => \Clock::date(), 'mode' => 'replace'] + $input, 'GET');

			if($this->assertTrue($data !== NULL, $entry.' ne s\'ouvre pas en remplacement') === FALSE) {
				continue;
			}

			$timeShown = array_sum(array_map(fn(array $spent) => $spent[\Clock::date()], $data->cUserTime->getColumn('spent')));

			if($this->assertDecorPresent(round($timeShown, 4) === 0.3333, $entry.' : deux personnes à un sixième d\'heure sur la récolte du jour') === FALSE) {
				continue;
			}

			$this->assertEquals(0.34, $data->cCultivation[$eCultivation['id']]['harvestTimeDays'][\Clock::date()] ?? 0.0, $entry.' : la case ne somme pas les deux temps arrondis au centième');
			$this->assertEquals(0.0, $data->timeKept, $entry.' garde un reste alors qu\'il montre tout le temps du jour');

		}

	}

	/**
	 * Une part récoltée un même jour sur deux interventions de son couple somme leur temps, comme la culture,
	 * sans celui d'un autre couple, et le tiroir de la part rejoint sa case.
	 */
	public function testPartSumsInterventions(): void {

		$eUserOnline = $this->getMember();

		$eCultivation = $this->createCultivation($this->createSeries('Série ventilée'));
		$eVariety = $this->createVariety($eCultivation['plant']);
		$eVarietyOther = $this->createVariety($eCultivation['plant']);
		$this->createSlice($eCultivation, $eVariety, 50.0);
		$this->createSlice($eCultivation, $eVarietyOther, 50.0);

		$tasks = [];

		foreach([[$eVariety, 2.0, 0.5], [$eVariety, 3.0, 1.25], [$eVarietyOther, 4.0, 2.0]] as [$eVarietyTask, $quantity, $time]) {

			$eTask = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());

			// Sans `build()`, qui vérifierait que la variété est une de celles de la culture : elle vient d'y
			// être posée par `createSlice()`.
			$eTask['variety'] = $eVarietyTask;
			\series\TaskLib::update($eTask, ['variety']);

			$this->createHarvest($eTask, $quantity, \Clock::date());
			$this->writeTime($eTask, $eUserOnline, \Clock::date(), $time);

			$tasks[] = $eTask;

		}

		$key = \series\CultivationLib::getHarvestPartKey($eVariety, new \plant\Size());
		$keyOther = \series\CultivationLib::getHarvestPartKey($eVarietyOther, new \plant\Size());

		[, $data] = $this->render();

		$parts = $data->cCultivation[$eCultivation['id']]['harvestedParts'];

		if($this->assertDecorPresent(array_key_exists($key, $parts) and array_key_exists($keyOther, $parts), 'une culture à deux parts récoltées, les variétés '.$eVariety['id'].' et '.$eVarietyOther['id']) === FALSE) {
			return;
		}

		$this->assertEquals(1.75, $parts[$key]['timeDays'][\Clock::date()] ?? 0.0, 'la part ne somme pas le temps de ses deux interventions');
		$this->assertEquals(2.0, $parts[$keyOther]['timeDays'][\Clock::date()] ?? 0.0, 'la part compte le temps d\'un autre couple');
		$this->assertEquals(3.75, $data->cCultivation[$eCultivation['id']]['harvestTimeDays'][\Clock::date()] ?? 0.0, 'la culture ne somme pas le temps de ses trois interventions');

		$this->checkEntryJoinsDayTime($eCultivation, 1.75, [$eUserOnline['id']], 'une part', ['variety' => $eVariety['id']]);

	}

	/**
	 * Ouvre le tiroir puis la feuille du jour en remplacement, pour la personne connectée, et vérifie que le
	 * temps qu'ils montrent et le reste qu'ils disent font `$time`, le temps de la case.
	 */
	private function checkEntryJoinsDayTime(\series\Cultivation $eCultivation, float $time, array $users, string $case, array $couple = []): void {

		foreach(['le tiroir' => [], 'la feuille' => ['sheet' => 1]] as $entry => $input) {

			[$data] = $this->call('open', ['cultivation' => $eCultivation['id'], 'date' => \Clock::date(), 'mode' => 'replace'] + $couple + $input, 'GET');

			if($this->assertTrue($data !== NULL, $case.' : '.$entry.' ne s\'ouvre pas en remplacement') === FALSE) {
				continue;
			}

			$ids = $data->cUserTime->getIds();
			sort($ids);
			sort($users);

			$this->assertEquals($users, $ids, $case.' : '.$entry.' ne rend pas les personnes attendues');

			$timeShown = array_sum(array_map(fn(array $spent) => $spent[\Clock::date()], $data->cUserTime->getColumn('spent')));

			$this->assertTrue($data->timeKept > 0.0, $case.' : '.$entry.' ne garde aucun temps hors de la saisie');
			$this->assertEquals($time, round($timeShown + $data->timeKept, 2), $case.' : le temps de '.$entry.' et le reste qu\'il dit ne font pas le temps de la case');

		}

	}

	/** Une culture récoltée aujourd'hui sur une récolte faite de la semaine, et cette récolte. */
	private function createHarvested(float $quantity): array {

		// Une série par culture : `UNIQUE (series, plant)` refuserait la seconde de l'espèce du décor.
		$eCultivation = $this->createCultivation($this->createSeries('Série récoltée'));
		$eTask = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->createHarvest($eTask, $quantity, \Clock::date());

		return [$eCultivation, $eTask];

	}

	/**
	 * Le temps d'une personne sur une récolte, un jour.
	 */
	private function writeTime(\series\Task $eTask, \user\User $eUser, string $date, float $time): void {

		// Sans `build()`, qui vérifierait la personne et le jour d'un formulaire : le décor veut un temps déjà
		// saisi, que le cas lit ensuite à l'écran.
		\series\TimesheetLib::writeTimeForDay($eTask, new \series\Timesheet([
			'structure' => $this->eStructure,
			'user' => $eUser,
			'date' => $date,
			'time' => $time,
		]));

	}

	/**
	 * Le temps d'un jour d'une culture et les personnes qu'il montre en vignettes, tels que les prépare
	 * `HarvestWeekLib::get()` pour la semaine du jour.
	 */
	private function readTimeDay(\series\Cultivation $eCultivation, string $date): array {

		$cCultivation = HarvestWeekLib::get($this->eStructure, \Clock::week());

		if($this->assertTrue($cCultivation->offsetExists($eCultivation['id']), 'la culture récoltée n\'est pas listée cette semaine') === FALSE) {
			return [];
		}

		$eCultivationListed = $cCultivation[$eCultivation['id']];

		return [
			round($eCultivationListed['harvestTimeDays'][$date] ?? 0.0, 2),
			array_key_exists($date, $eCultivationListed['cUserTimeDays']) ? array_values($eCultivationListed['cUserTimeDays'][$date]->getIds()) : []
		];

	}

	/**
	 * Les sept jours d'une ligne, ou d'une part, sans ceux de ses parts, qu'elle porte en sous-lignes : le
	 * DOM de PHP ne connaît pas `:scope`, d'où le parcours des enfants directs.
	 */
	private function getDays(\Dom\HTMLDocument $document, \series\Cultivation $eCultivation, ?string $key = NULL): array {

		$row = $document->getElementById('planning-harvest-'.$eCultivation['id'].(($key === NULL) ? '' : '-'.$key));

		if($row === NULL) {
			return [];
		}

		$days = [];

		foreach($row->childNodes as $node) {
			if($node instanceof \Dom\Element and $node->classList->contains('planning-harvest-day')) {
				$days[] = $node;
			}
		}

		return $days;

	}

	/**
	 * La case d'un jour sur une ligne, ou sur une de ses parts. Au Jour, le tableau ne montre que le jour choisi
	 * (retour 315), dans sa colonne « Récolté » : un autre jour n'y a pas de case.
	 */
	private function getDay(\Dom\HTMLDocument $document, \series\Cultivation $eCultivation, string $date, ?string $key = NULL): ?\Dom\Element {

		$head = $document->querySelector('.util-table-head > .planning-harvest-done');

		if($head === NULL) {
			return $this->getDays($document, $eCultivation, $key)[(int)date('N', strtotime($date)) - 1] ?? NULL;
		}

		if($head->getAttribute('data-date') !== $date) {
			return NULL;
		}

		$row = $document->getElementById('planning-harvest-'.$eCultivation['id'].(($key === NULL) ? '' : '-'.$key));

		foreach($row?->childNodes ?? [] as $node) {
			if($node instanceof \Dom\Element and $node->classList->contains('planning-harvest-done')) {
				return $node;
			}
		}

		return NULL;

	}

	/**
	 * Ce que dit la ligne du temps d'un jour : les personnes de ses vignettes, dans leur ordre, puis sa durée.
	 * Vide sans ligne. Une vignette sans photo écrit des initiales : la durée est le dernier texte de la ligne.
	 */
	private function readLine(?\Dom\Element $day): array {

		$line = $day?->querySelector('.planning-harvest-day-time');

		if($line === NULL) {
			return [];
		}

		$names = [];

		foreach($line->querySelectorAll('.planning-task-users > *') as $vignette) {
			$names[] = $vignette->getAttribute('title');
		}

		return [$names, trim($line->lastChild->textContent)];

	}

	/** Les noms de `$users` tels qu'une vignette les porte, dans l'ordre des membres de la structure. */
	private function getMemberNames(array $users): array {

		$ids = array_map(fn(\user\User $eUser) => $eUser['id'], $users);

		return array_values(
			\structure\MemberLib::getUsersByStructure($this->eStructure)
				->find(fn(\user\User $eUser) => in_array($eUser['id'], $ids, TRUE))
				->makeArray(fn(\user\User $eUser) => html_entity_decode(\user\UserUi::name($eUser)))
		);

	}

	/** Les arguments de chaque appel d'une méthode `js` de la réponse. */
	private function getJs(array $instructions, string $method): array {

		$calls = [];

		foreach($instructions as $instruction) {

			if($instruction[0] !== 'js') {
				continue;
			}

			foreach($instruction[1] as [$methodCalled, $argumentsCalled]) {

				if($methodCalled === $method) {
					$calls[] = $argumentsCalled[0];
				}

			}

		}

		return $calls;

	}

	/** Tout le HTML qu'une réponse pose, en un document où chercher ce qu'elle rend. */
	private function getDocument(array $instructions): \Dom\HTMLDocument {

		$html = '';

		foreach($instructions as $instruction) {

			if($instruction[0] === 'js') {
				continue;
			}

			foreach($instruction[2] as $call) {
				$html .= implode('', array_filter($call[1] ?? [], 'is_string'));
			}

		}

		return \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NOERROR);

	}

	/**
	 * Les nœuds que la réponse éclaire (`highlight`), cherchés dans les lignes qu'elle rend. Chaque sélecteur
	 * part de la liste de la semaine, que ces lignes n'ont pas autour d'elles : il se lit ici sans elle.
	 */
	private function getHighlighted(array $instructions): array {

		$document = $this->getDocument($instructions);
		$nodes = [];

		$list = '#planning-harvest-list[data-week="'.\Clock::week().'"]';

		foreach($this->getJs($instructions, 'highlight') as $selectors) {
			foreach($selectors as $selector) {

				if($this->assertTrue(str_starts_with($selector, $list), 'la réponse éclaire hors de la liste de sa semaine : '.$selector) === FALSE) {
					continue;
				}

				array_push($nodes, ...iterator_to_array($document->querySelectorAll(substr($selector, strpos($selector, ' ') + 1))));

			}
		}

		return $nodes;

	}


	/**
	 * `$callback` joué par `$eUser` connecté, avec le rôle de salarié de F1 (le planning entier, sans
	 * administration), puis son rôle et la personne connectée rendus, même sur un échec.
	 */
	private function withRoleEmployee(\user\User $eUser, \Closure $callback): void {

		$eRole = \structure\Role::model()
			->select('id')
			->whereStructure($this->eStructure)
			->whereAdmin(FALSE)
			->wherePlanning(\structure\Role::FULL)
			->whereFqn(NULL)
			->get();

		$eMember = \structure\Member::model()
			->select(['id', 'role'])
			->whereStructure($this->eStructure)
			->whereUser($eUser)
			->get();

		if(
			$this->assertDecorPresent($eRole->notEmpty(), 'un rôle de salarié sur F1') === FALSE or
			$this->assertDecorPresent($eMember->notEmpty(), 'l\'adhésion de '.$eUser['email'].' à F1') === FALSE
		) {
			return;
		}

		$eRoleBefore = $eMember['role'];

		$eMember['role'] = $eRole;
		\structure\MemberLib::update($eMember, ['role']);

		$this->connect($eUser);

		try {
			$callback();
		} finally {

			$eMember['role'] = $eRoleBefore;
			\structure\MemberLib::update($eMember, ['role']);

			$this->connect($this->getMember());

		}

	}

	/** La personne en ligne, comme la pose une connexion : le membre en cache est celui de la précédente. */
	private function connect(\user\User $eUser): void {

		\user\ConnectionLib::setOnline($eUser);
		\structure\MemberLib::clearCache();
		$this->forgetAccountingYear();

	}

}
?>
