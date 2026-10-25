<?php
namespace planning;

/**
 * Ce qui entre dans l'écran des récoltes d'une semaine, et comment ses états particuliers se rendent.
 *
 * Le décor est celui de `series` (`SeriesFixtureAiTest`), posé dans la transaction de la méthode sur
 * la structure du décor. Un cas ne compte jamais la liste entière, il cherche ses cultures par leur
 * identifiant, présentes ou absentes. Les plans des requêtes se mesurent sur un volume réel, dans
 * `HarvestExplainAi`.
 *
 * La règle de la série close (point 7 du plan de L2) est une hypothèse de V-Planning sous mandat,
 * réversible avec le geste « Dernière récolte » de L3 : si elle change, c'est
 * `testClosedSeriesLeavesUnlessHarvested` qui le dit.
 *
 * Lancement : dev/test package=planning file=HarvestWeek
 */
class HarvestWeekTest extends \series\SeriesFixtureAiTest {

	/**
	 * L'état vide propose « Nouvelle récolte » seulement quand la semaine accepte une saisie (`$canCreate`),
	 * jamais sur une semaine à venir.
	 */
	public function testEmpty(): void {

		$week = $this->getSeason().'-W39';

		$h = new HarvestWeekUi()->get($this->eStructure, $week, HarvestWeekLib::getDays($week), new \Collection(), new \structure\Member(), canHarvest: TRUE, canCreate: TRUE);
		$this->assertTrue(str_contains($h, 'harvestWeek:create'), 'l\'état vide d\'une semaine qui accepte la saisie n\'a pas « Nouvelle récolte »');

		$h = new HarvestWeekUi()->get($this->eStructure, $week, HarvestWeekLib::getDays($week), new \Collection(), new \structure\Member(), canHarvest: TRUE, canCreate: FALSE);
		$this->assertFalse(str_contains($h, 'harvestWeek:create'), 'l\'état vide d\'une semaine à venir porte « Nouvelle récolte »');

	}

	/**
	 * Le rang des gestes d'une liste non vide : la semaine courante porte « Nouvelle récolte », une semaine
	 * à venir non, aucune de ses cases n'étant éditable (la saisie appartient à la semaine affichée, arbitrage V-Planning du 2026-09-30). Une
	 * culture prévue les deux semaines garantit la liste, que les cultures réelles de la structure ne
	 * garantissent pas.
	 */
	public function testFutureWeekTools(): void {

		$season = $this->getSeason();
		$this->setClock(week_date_day($season.'-W39', 4).' 10:00:00');

		$eCultivation = $this->createCultivation();
		$this->setExpected($eCultivation, \series\Cultivation::WEEK, [$season.'-W39', $season.'-W40']);

		foreach([$season.'-W39' => TRUE, $season.'-W40' => FALSE] as $week => $expected) {

			$days = HarvestWeekLib::getDays($week);
			$cCultivation = HarvestWeekLib::get($this->eStructure, $week);

			$this->assertTrue($cCultivation->offsetExists($eCultivation['id']), 'la culture prévue n\'est pas listée en '.$week.', le rang des gestes d\'une liste n\'est pas éprouvé');
			$this->assertEquals($expected, HarvestWeekLib::getCreateDate($days) !== NULL, 'la semaine '.$week.' ne se classe pas comme attendu');

			$h = new HarvestWeekUi()->get($this->eStructure, $week, $days, $cCultivation, new \structure\Member(), canHarvest: TRUE, canCreate: HarvestWeekLib::getCreateDate($days) !== NULL);

			$this->assertEquals($expected, str_contains($h, 'harvestWeek:create'), '« Nouvelle récolte » en '.$week.' ne suit pas la règle de semaine');

		}

	}

	/**
	 * Une récolte prévue à la semaine sort ses semaines, la suivante (règle R2, R-Retours-0710), et
	 * aucune autre.
	 */
	public function testWeekPeriod(): void {

		$season = $this->getSeason();

		$eCultivation = $this->createCultivation();
		$this->setExpected($eCultivation, \series\Cultivation::WEEK, [$season.'-W39', $season.'-W40']);

		$this->assertTrue($this->isListed($eCultivation, $season.'-W39'), 'la culture prévue en '.$season.'-W39 n\'y est pas');
		$this->assertTrue($this->isListed($eCultivation, $season.'-W40'), 'la culture prévue en '.$season.'-W40 n\'y est pas');
		$this->assertTrue($this->isListed($eCultivation, $season.'-W41'), 'la culture ne sort pas la semaine qui suit sa période');
		$this->assertFalse($this->isListed($eCultivation, $season.'-W38'), 'la culture sort une semaine qui n\'est pas prévue, avant sa période');
		$this->assertFalse($this->isListed($eCultivation, $season.'-W42'), 'la culture sort une semaine qui n\'est pas prévue, après sa période');

	}

	/**
	 * Une récolte prévue au mois (le défaut) : la semaine à cheval sur deux mois va au mois qui en
	 * porte le plus de jours, celui de son jeudi (`\util\DateLib::convertMonthsToWeeks()`, que
	 * `build()` applique). La règle du lundi, qu'emploie encore `updateHarvestPeriodExpected()`,
	 * rangerait la semaine dans l'autre mois : le cas le prouve dans les deux sens. Le mois du lundi se
	 * contrôle sur sa période et non sur la liste, où la règle R2 le garde la semaine qui suit.
	 */
	public function testMonthPeriodAstrideWeek(): void {

		$season = $this->getSeason();
		$week = toWeek($season.'-10-01');

		$monday = date('Y-m', strtotime($week));
		$thursday = date('Y-m', strtotime($week.' + 3 DAYS'));

		// Sans quoi le cas ne prouve rien : c'est la divergence des deux règles qui le rend intéressant.
		if($this->assertTrue($monday !== $thursday, $week.' n\'est pas à cheval sur deux mois, le cas est à changer') === FALSE) {
			return;
		}

		$eCultivationMonday = $this->createCultivation($this->createSeries('Série du mois du lundi'));
		$this->setExpected($eCultivationMonday, \series\Cultivation::MONTH, [$monday]);

		$eCultivationThursday = $this->createCultivation($this->createSeries('Série du mois du jeudi'));
		$this->setExpected($eCultivationThursday, \series\Cultivation::MONTH, [$thursday]);

		$this->assertTrue($this->isListed($eCultivationThursday, $week), 'la culture de '.$thursday.' ne sort pas en '.$week.', dont le jeudi est en '.$thursday);
		$this->assertFalse(in_array($week, $eCultivationMonday['harvestWeeksExpected'], TRUE), 'la culture de '.$monday.' est prévue en '.$week.', rangée par son lundi');

	}

	/**
	 * La semaine 1 d'une saison lit aussi la saison précédente, dont une culture d'hiver peut se
	 * récolter en janvier.
	 */
	public function testPreviousSeasonOnFirstWeek(): void {

		$season = $this->getSeason();
		$week = $season.'-W01';

		$eSeries = $this->createSeries('Série de l\'hiver', $season - 1);

		// CultivationLib::create() donne à la culture la saison de sa série.
		$eCultivation = $this->createCultivation($eSeries);
		$this->setExpected($eCultivation, \series\Cultivation::WEEK, [($season - 1).'-W52', $week]);

		$cCultivation = HarvestWeekLib::get($this->eStructure, $week);

		$this->assertTrue($cCultivation->offsetExists($eCultivation['id']), 'la culture de la saison '.($season - 1).' prévue en '.$week.' n\'y est pas');

	}

	/**
	 * La recherche de « Nouvelle récolte » range ses « Autres séries », celles sans récolte prévue proche, par
	 * écart de saison avant de garder les dix premiers : la saison de la semaine d'abord, puis la précédente
	 * avant la suivante, une culture d'hivernage étant plus probable en semaine de récolte qu'une culture de
	 * l'an prochain. Une culture de la saison précédente ne doit pas faire lever l'écart, que `season`, non
	 * signée, rendait négatif en SQL (`BIGINT UNSIGNED value is out of range`).
	 *
	 * La suivante est créée en dernier : un rangement laissé à l'identifiant la mettrait en tête des deux
	 * saisons voisines, c'est donc bien la saison la plus ancienne qui les départage.
	 */
	public function testQueryPreviousSeason(): void {

		$season = $this->getSeason();
		$word = 'rang'.uniqid();

		$eCultivationPrevious = $this->createCultivation($this->createSeries('Série '.$word, $season - 1));
		$eCultivationCurrent = $this->createCultivation($this->createSeries('Série '.$word));
		$eCultivationNext = $this->createCultivation($this->createSeries('Série '.$word, $season + 1));

		$cCultivation = HarvestWeekLib::getForQuery($this->eStructure, $season.'-W10', $word);

		$this->assertEquals(
			[$eCultivationCurrent['id'], $eCultivationPrevious['id'], $eCultivationNext['id']],
			$cCultivation->getIds(),
			'la recherche ne range pas la saison de la semaine, puis la précédente, puis la suivante'
		);

	}

	/**
	 * Une série close sort de la liste, sauf si elle a une récolte cette semaine : la dernière
	 * récolte d'une série se fait souvent le jour où on la clôt.
	 */
	public function testClosedSeriesLeavesUnlessHarvested(): void {

		$season = $this->getSeason();
		$week = $season.'-W39';
		$date = week_date_day($week, 2);

		$eSeriesIdle = $this->createSeries('Série close sans récolte');
		$eCultivationIdle = $this->createCultivation($eSeriesIdle);
		$this->setExpected($eCultivationIdle, \series\Cultivation::WEEK, [$week]);

		$eSeriesHarvested = $this->createSeries('Série close récoltée');
		$eCultivationHarvested = $this->createCultivation($eSeriesHarvested);
		$this->setExpected($eCultivationHarvested, \series\Cultivation::WEEK, [$week]);
		$this->createHarvestInUnit($eCultivationHarvested, \series\Task::KG, 4.5, $date);

		$this->closeSeries($eSeriesIdle, \series\Series::SUCCESS);
		$this->closeSeries($eSeriesHarvested, \series\Series::SUCCESS);

		$this->assertFalse($this->isListed($eCultivationIdle, $week), 'une série close sans récolte cette semaine reste dans la liste');
		$this->assertTrue($this->isListed($eCultivationHarvested, $week), 'une série close récoltée cette semaine sort de la liste');

	}

	/**
	 * Une culture récoltée cette semaine hors de sa période prévue entre dans la liste, avec son
	 * récolté.
	 */
	public function testHarvestedOutsideExpected(): void {

		$season = $this->getSeason();
		$week = $season.'-W39';
		$date = week_date_day($week, 1);

		$eCultivation = $this->createCultivation();
		$this->createHarvestInUnit($eCultivation, \series\Task::KG, 3.0, $date);

		$cCultivation = HarvestWeekLib::get($this->eStructure, $week);

		if($this->assertTrue($cCultivation->offsetExists($eCultivation['id']), 'une culture récoltée cette semaine hors période n\'y est pas') === FALSE) {
			return;
		}

		$eCultivationListed = $cCultivation[$eCultivation['id']];

		$this->assertEquals([$date => [\series\Task::KG => 3.0]], $eCultivationListed['harvestedDays'], 'le récolté de la semaine n\'est pas celui posé');
		$this->assertEquals(NULL, $eCultivationListed['expectedFirst'], 'une culture sans période prévue en porte une');

	}

	/**
	 * L'entrée dans Récoltes se décide en semaines, jamais en jours (R-Retours-0610) : une série y
	 * entre si sa récolte est prévue dans la semaine, ou si une récolte est datée de la semaine, quelles que
	 * soient la semaine de son intervention et sa période prévue. Trois récoltes datées de la semaine 39, hors
	 * de la période prévue (semaines 30 et 31) : sur une intervention à faire planifiée en semaine 37, sur une
	 * intervention faite en semaine 37, et sur une intervention faite en semaine 41, au dimanche, dernier jour
	 * de la semaine. Chacune apparaît avec son jour.
	 */
	public function testHarvestedOnTaskOfOtherWeek(): void {

		$season = $this->getSeason();
		$week = $season.'-W39';

		$cases = [
			'intervention à faire planifiée en semaine 37' => [\series\Task::TODO, $season.'-W37', week_date_day($week, 3)],
			'intervention faite en semaine 37' => [\series\Task::DONE, $season.'-W37', week_date_day($week, 1)],
			'intervention faite en semaine 41' => [\series\Task::DONE, $season.'-W41', week_date_day($week, 7)],
		];

		$expected = [];

		foreach($cases as $case => [$status, $weekTask, $date]) {

			$eCultivation = $this->createCultivation($this->createSeries($case));
			$this->setExpected($eCultivation, \series\Cultivation::WEEK, [$season.'-W30', $season.'-W31']);

			if($status === \series\Task::DONE) {
				$eTask = $this->createTaskHarvest($eCultivation, $weekTask, week_date_day($weekTask, 2));
			} else {

				$eTask = $this->createTask($eCultivation, ACTION_RECOLTE, $status, $weekTask, week_date_day($weekTask, 2));

				// Sans build(), qui vérifierait l'unité postée depuis le formulaire d'intervention : le décor
				// veut une intervention à faire déjà dans l'unité de la culture, ce que `createTask()` ne pose pas.
				$eTask['harvestUnit'] = $eCultivation['mainUnit'];
				\series\TaskLib::update($eTask, ['harvestUnit']);

			}

			$this->createHarvest($eTask, 4.0, $date);

			$expected[$case] = [$eCultivation, $date];

		}

		$cCultivation = HarvestWeekLib::get($this->eStructure, $week);

		foreach($expected as $case => [$eCultivation, $date]) {

			if($this->assertTrue($cCultivation->offsetExists($eCultivation['id']), $case.', la série récoltée dans la semaine n\'est pas dans Récoltes') === FALSE) {
				continue;
			}

			$this->assertEquals([$date => [$eCultivation['mainUnit'] => 4.0]], $cCultivation[$eCultivation['id']]['harvestedDays'], $case.', la récolte n\'est pas au jour où elle est datée');

		}

	}

	/**
	 * La semaine précédente, que la vue Semaine montre seule (R-Retours-1210, 304), somme le récolté de la
	 * semaine d'avant, unité par unité, culture par culture : ni celui de la semaine affichée ni celui de deux
	 * semaines plus tôt n'y entrent. Une culture sans récolte la semaine d'avant n'a rien. Le Jour ne
	 * la lit pas.
	 */
	public function testPreviousWeek(): void {

		$season = $this->getSeason();
		$week = $season.'-W39';
		$previous = $season.'-W38';

		$eCultivation = $this->createCultivation();
		$this->setExpected($eCultivation, \series\Cultivation::WEEK, [$previous, $week]);

		$eCultivationOther = $this->createCultivation($this->createSeries('Série sans semaine précédente'));
		$this->setExpected($eCultivationOther, \series\Cultivation::WEEK, [$week]);

		$this->createHarvestInUnit($eCultivation, \series\Task::KG, 2.0, week_date_day($previous, 1));
		$this->createHarvestInUnit($eCultivation, \series\Task::KG, 1.5, week_date_day($previous, 7));
		$this->createHarvestInUnit($eCultivation, \series\Task::BUNCH, 3.0, week_date_day($previous, 3));
		$this->createHarvestInUnit($eCultivation, \series\Task::KG, 9.0, week_date_day($season.'-W37', 7));
		$this->createHarvestInUnit($eCultivation, \series\Task::KG, 1.0, week_date_day($week, 1));

		$cCultivation = HarvestWeekLib::get($this->eStructure, $week, previous: TRUE);

		if($this->assertTrue($cCultivation->offsetExists($eCultivation['id']) and $cCultivation->offsetExists($eCultivationOther['id']), 'les deux cultures prévues en '.$week.' ne sont pas listées') === FALSE) {
			return;
		}

		$this->assertEquals([\series\Task::KG => 3.5, \series\Task::BUNCH => 3.0], $cCultivation[$eCultivation['id']]['harvestedWeekPrevious'], 'la semaine précédente ne somme pas la seule semaine d\'avant, unité par unité');
		$this->assertEquals([], $cCultivation[$eCultivationOther['id']]['harvestedWeekPrevious'], 'une culture sans récolte la semaine d\'avant prend celle d\'une autre');

		$this->assertEquals([], HarvestWeekLib::get($this->eStructure, $week)[$eCultivation['id']]['harvestedWeekPrevious'], 'sans `previous`, comme au tableau de bord, la liste lit la semaine précédente');

	}

	/**
	 * Chaque part porte sa semaine précédente (`weekPrevious`), puisque la ligne d'une culture ventilée ne sert
	 * qu'à saisir. Une part récoltée la semaine d'avant seulement garde sa ligne, vide sur la semaine, où ce
	 * récolté se lit (M-Planning, 21/10).
	 */
	public function testPreviousWeekParts(): void {

		$season = $this->getSeason();
		$week = $season.'-W39';
		$previous = $season.'-W38';

		$eCultivation = $this->createCultivation();
		$this->setExpected($eCultivation, \series\Cultivation::WEEK, [$previous, $week]);

		$eVarietyBoth = $this->createVariety($eCultivation['plant']);
		$this->createSlice($eCultivation, $eVarietyBoth, 50.0);

		$eVarietyCurrent = $this->createVariety($eCultivation['plant']);
		$this->createSlice($eCultivation, $eVarietyCurrent, 50.0);

		$this->createHarvestOf($eCultivation, ['variety' => $eVarietyBoth], 2.0, week_date_day($previous, 2));
		$this->createHarvestOf($eCultivation, [], 0.5, week_date_day($previous, 4));
		$this->createHarvestOf($eCultivation, ['variety' => $eVarietyBoth], 1.0, week_date_day($week, 1));
		$this->createHarvestOf($eCultivation, ['variety' => $eVarietyCurrent], 3.0, week_date_day($week, 1));

		$eCultivationWeek = HarvestWeekLib::get($this->eStructure, $week, previous: TRUE)[$eCultivation['id']];

		$keyBoth = \series\CultivationLib::getHarvestPartKey($eVarietyBoth, new \plant\Size());
		$keyCurrent = \series\CultivationLib::getHarvestPartKey($eVarietyCurrent, new \plant\Size());
		$keyUnspecified = \series\CultivationLib::getHarvestPartKey(new \plant\Variety(), new \plant\Size());

		$this->assertEquals($this->sortKeys([$keyBoth, $keyCurrent, $keyUnspecified]), $this->getPartKeys($eCultivationWeek), 'la part récoltée la semaine d\'avant seulement n\'a pas sa ligne');

		if($this->assertDecorPresent($eCultivationWeek['harvestedParts'] !== [], 'les parts de la culture en '.$week) === FALSE) {
			return;
		}

		$this->assertEquals([\series\Task::KG => 2.0], $eCultivationWeek['harvestedParts'][$keyBoth]['weekPrevious'], 'la variété récoltée les deux semaines n\'a pas sa semaine précédente');
		$this->assertEquals([], $eCultivationWeek['harvestedParts'][$keyCurrent]['weekPrevious'], 'la variété sans récolte la semaine d\'avant prend celle d\'une autre part');
		$this->assertEquals([\series\Task::KG => 0.5], $eCultivationWeek['harvestedParts'][$keyUnspecified]['weekPrevious'], '« Non spécifiée » ne porte pas sa semaine précédente');
		$this->assertEquals([], $eCultivationWeek['harvestedParts'][$keyUnspecified]['week'], '« Non spécifiée » porte une semaine où rien n\'a été récolté');
		$this->assertEquals([\series\Task::KG => 2.5], $eCultivationWeek['harvestedWeekPrevious'], 'la culture ne somme plus toutes ses parts la semaine d\'avant');

	}

	/**
	 * Règle R1 (R-Retours-0710) : une série dont la récolte a commencé reste dans Récoltes jusqu'à
	 * la fin de sa période attendue, même commencée avant elle. Sa voisine, prévue pareil mais jamais
	 * récoltée, n'entre qu'avec sa période. Le commencement se lit dans `harvestWeeks`, les semaines des
	 * interventions de récolte faites.
	 */
	public function testStartedStaysUntilExpectedEnd(): void {

		$season = $this->getSeason();

		$eCultivationStarted = $this->createCultivation($this->createSeries('Série commencée avant sa période'));
		$this->setExpected($eCultivationStarted, \series\Cultivation::WEEK, [$season.'-W40', $season.'-W41', $season.'-W42']);
		$this->createHarvestInUnit($eCultivationStarted, \series\Task::KG, 2.0, week_date_day($season.'-W37', 3));

		$eCultivationIdle = $this->createCultivation($this->createSeries('Série prévue jamais récoltée'));
		$this->setExpected($eCultivationIdle, \series\Cultivation::WEEK, [$season.'-W40', $season.'-W41', $season.'-W42']);

		$this->assertTrue($this->isListed($eCultivationStarted, $season.'-W38'), 'une série commencée en W37 et prévue jusqu\'en W42 n\'est pas listée en W38');
		$this->assertTrue($this->isListed($eCultivationStarted, $season.'-W42'), 'une série commencée n\'est pas listée la dernière semaine de sa période');
		$this->assertFalse($this->isListed($eCultivationIdle, $season.'-W38'), 'une série jamais récoltée est listée avant sa période');
		$this->assertFalse($this->isListed($eCultivationStarted, $season.'-W44'), 'une série commencée reste listée deux semaines après la fin de sa période');

	}

	/**
	 * Règle R2, premier volet : une série reste dans Récoltes la semaine qui suit la fin de sa période attendue,
	 * pas celle d'après, et pas si elle est close.
	 */
	public function testOneWeekAfterExpectedEnd(): void {

		$season = $this->getSeason();

		$eCultivation = $this->createCultivation($this->createSeries('Série prévue jusqu\'en W38'));
		$this->setExpected($eCultivation, \series\Cultivation::WEEK, [$season.'-W37', $season.'-W38']);

		$eSeriesClosed = $this->createSeries('Série close prévue jusqu\'en W38');
		$eCultivationClosed = $this->createCultivation($eSeriesClosed);
		$this->setExpected($eCultivationClosed, \series\Cultivation::WEEK, [$season.'-W37', $season.'-W38']);
		$this->closeSeries($eSeriesClosed, \series\Series::SUCCESS);

		$this->assertTrue($this->isListed($eCultivation, $season.'-W39'), 'une série n\'est pas listée la semaine qui suit la fin de sa période');
		$this->assertFalse($this->isListed($eCultivation, $season.'-W40'), 'une série est listée deux semaines après la fin de sa période');
		$this->assertFalse($this->isListed($eCultivationClosed, $season.'-W39'), 'une série close est listée la semaine qui suit la fin de sa période');

	}

	/**
	 * Règle R2, second volet : une série récoltée la semaine précédente est listée, sauf si elle est close. Le
	 * récolté se lit dans la table des récoltes.
	 */
	public function testHarvestedPreviousWeek(): void {

		$season = $this->getSeason();
		$week = $season.'-W39';

		$eCultivation = $this->createCultivation($this->createSeries('Série récoltée la semaine d\'avant'));
		$this->createHarvestInUnit($eCultivation, \series\Task::KG, 1.5, week_date_day($season.'-W38', 5));

		$eSeriesClosed = $this->createSeries('Série close récoltée la semaine d\'avant');
		$eCultivationClosed = $this->createCultivation($eSeriesClosed);
		$this->createHarvestInUnit($eCultivationClosed, \series\Task::KG, 1.5, week_date_day($season.'-W38', 5));
		$this->closeSeries($eSeriesClosed, \series\Series::SUCCESS);

		$this->assertTrue($this->isListed($eCultivation, $week), 'une série récoltée la semaine précédente n\'est pas listée');
		$this->assertFalse($this->isListed($eCultivation, $season.'-W40'), 'une série récoltée deux semaines plus tôt est listée');
		$this->assertFalse($this->isListed($eCultivationClosed, $week), 'une série close récoltée la semaine précédente est listée');

	}

	/**
	 * Un jour à venir demandé par l'adresse ne s'ouvre pas : la semaine s'ouvre sur aujourd'hui.
	 */
	public function testFutureDayNotOpen(): void {

		$week = \Clock::week();

		// Un jeudi : trois jours passés, aujourd'hui, trois jours à venir.
		$this->setClock(week_date_day($week, 4).' 10:00:00');

		$this->assertEquals(week_date_day($week, 4), array_find_key(HarvestWeekLib::getDays($week, week_date_day($week, 5)), fn(array $day) => $day['open']), 'un jour à venir demandé par l\'adresse s\'ouvre');

	}

	/**
	 * La frise compte les semaines par leurs dates, à cheval sur deux années comme dans une seule : une
	 * période de quatre semaines qui passe le Nouvel An est quatre fois plus large que sa première semaine
	 * seule, celle d'une culture finie mais récoltée cette semaine. Une culture sans période prévue n'a pas de
	 * frise.
	 */
	public function testFriezeAcrossYear(): void {

		$season = $this->getSeason();
		$week = toWeek($season.'-12-28');
		$weeks = array_map(fn(int $offset) => toWeek(week_date_starts($week).' '.$offset.' weeks'), [-2, -1, 0, 1]);

		$eCultivation = $this->createCultivation();
		$this->setExpected($eCultivation, \series\Cultivation::WEEK, $weeks);

		$eCultivationLate = $this->createCultivation($this->createSeries('Série finie'));
		$this->setExpected($eCultivationLate, \series\Cultivation::WEEK, [$weeks[0]]);
		$this->createHarvestInUnit($eCultivationLate, \series\Task::KG, 1.0, week_date_starts($week));

		$eCultivationOut = $this->createCultivation($this->createSeries('Série hors période'));
		$this->createHarvestInUnit($eCultivationOut, \series\Task::KG, 1.0, week_date_starts($week));

		$cCultivation = HarvestWeekLib::get($this->eStructure, $week);

		if($this->assertTrue($cCultivation->offsetExists($eCultivation['id']) and $cCultivation->offsetExists($eCultivationLate['id']) and $cCultivation->offsetExists($eCultivationOut['id']), 'les trois cultures ne sont pas listées en '.$week) === FALSE) {
			return;
		}

		$this->assertEquals(1, week_number($weeks[3]), 'la période ne passe pas le Nouvel An, le cas ne mesure rien');

		$frieze = $cCultivation[$eCultivation['id']]['expectedFrieze'];
		$friezeLate = $cCultivation[$eCultivationLate['id']]['expectedFrieze'];

		$this->assertEquals('now', $frieze['state'], 'la période en cours ne se dit pas en cours');
		$this->assertEquals('late', $friezeLate['state'], 'la période finie ne se dit pas finie');
		$this->assertEquals($friezeLate['left'], $frieze['left'], 'deux périodes qui commencent la même semaine ne partent pas du même point');
		$this->assertTrue(abs($frieze['width'] - 4 * $friezeLate['width']) < 0.05, 'quatre semaines à cheval sur l\'année ne font pas quatre fois une semaine : '.$frieze['width'].' contre '.$friezeLate['width']);
		$this->assertTrue(abs($frieze['mark'] - ($frieze['left'] + 2.5 * $friezeLate['width'])) < 0.05, 'le trait n\'est pas au milieu de la troisième semaine de la période');

		$this->assertEquals([], $cCultivation[$eCultivationOut['id']]['expectedFrieze'], 'une culture sans période prévue a une frise');

	}

	/**
	 * L'échelle de la frise va de `PlanningSetting::FRIEZE_WEEKS` semaines avant la semaine affichée à
	 * autant après, quelles que soient les périodes de la liste. Une période plus longue s'arrête aux deux
	 * bords sans arrondi, une période entièrement passée n'a plus de barre mais garde son trait et ses
	 * semaines, et une période qui tient dedans n'est pas coupée.
	 */
	public function testFriezeCut(): void {

		$season = $this->getSeason();
		$week = $season.'-W26';
		$around = PlanningSetting::FRIEZE_WEEKS;
		$weeksAt = fn(int ...$offsets) => array_map(fn(int $offset) => toWeek(week_date_starts($week).' '.$offset.' weeks'), $offsets);

		$eCultivationLong = $this->createCultivation();
		$this->setExpected($eCultivationLong, \series\Cultivation::WEEK, $weeksAt(-$around - 5, $around + 5));
		$this->createHarvestInUnit($eCultivationLong, \series\Task::KG, 1.0, week_date_starts($week));

		$eCultivationGone = $this->createCultivation($this->createSeries('Série finie depuis longtemps'));
		$this->setExpected($eCultivationGone, \series\Cultivation::WEEK, $weeksAt(-$around - 4, -$around - 2));
		$this->createHarvestInUnit($eCultivationGone, \series\Task::KG, 1.0, week_date_starts($week));

		$eCultivationInside = $this->createCultivation($this->createSeries('Série dans la frise'));
		$this->setExpected($eCultivationInside, \series\Cultivation::WEEK, $weeksAt(-$around, $around));
		$this->createHarvestInUnit($eCultivationInside, \series\Task::KG, 1.0, week_date_starts($week));

		$cCultivation = HarvestWeekLib::get($this->eStructure, $week);

		if($this->assertTrue($cCultivation->offsetExists($eCultivationLong['id']) and $cCultivation->offsetExists($eCultivationGone['id']) and $cCultivation->offsetExists($eCultivationInside['id']), 'les trois cultures ne sont pas listées en '.$week) === FALSE) {
			return;
		}

		$friezeLong = $cCultivation[$eCultivationLong['id']]['expectedFrieze'];
		$friezeGone = $cCultivation[$eCultivationGone['id']]['expectedFrieze'];
		$friezeInside = $cCultivation[$eCultivationInside['id']]['expectedFrieze'];

		$this->assertEquals([0.0, 100.0, TRUE, TRUE], [$friezeLong['left'], $friezeLong['width'], $friezeLong['cutStart'], $friezeLong['cutEnd']], 'une période plus longue que l\'échelle n\'est pas coupée aux deux bords');
		$this->assertEquals([0.0, 100.0, FALSE, FALSE], [$friezeInside['left'], $friezeInside['width'], $friezeInside['cutStart'], $friezeInside['cutEnd']], 'une période qui tient dans l\'échelle, bornes comprises, est coupée');
		$this->assertEquals([0.0, 'late', 50.0], [$friezeGone['width'], $friezeGone['state'], $friezeGone['mark']], 'une période passée hors de l\'échelle garde une barre, ou le trait n\'est pas au milieu');
		$this->assertEquals(50.0, $friezeLong['mark'], 'le trait de la semaine affichée n\'est pas au milieu de l\'échelle');

	}

	/**
	 * Deux unités un même jour restent deux quantités : on n'additionne
	 * pas des kilos et des bottes. La colonne Semaine les somme unité par unité.
	 */
	public function testTwoUnitsSameDay(): void {

		$season = $this->getSeason();
		$week = $season.'-W39';
		$monday = week_date_day($week, 1);
		$tuesday = week_date_day($week, 2);

		$eCultivation = $this->createCultivation();
		$this->setExpected($eCultivation, \series\Cultivation::WEEK, [$week]);

		$this->createHarvestInUnit($eCultivation, \series\Task::KG, 2.0, $monday);
		$this->createHarvestInUnit($eCultivation, \series\Task::BUNCH, 5.0, $monday);
		$this->createHarvestInUnit($eCultivation, \series\Task::KG, 1.5, $tuesday);

		$eCultivationListed = HarvestWeekLib::get($this->eStructure, $week)[$eCultivation['id']];

		$this->assertEquals([\series\Task::KG => 2.0, \series\Task::BUNCH => 5.0], $eCultivationListed['harvestedDays'][$monday], 'les deux unités du lundi ne sont pas gardées à part');
		$this->assertEquals([\series\Task::KG => 3.5, \series\Task::BUNCH => 5.0], $eCultivationListed['harvestedWeek'], 'la semaine ne somme pas unité par unité');

	}

	/**
	 * Les parts d'une culture ventilée (L3c) : une par couple variété · calibre, les variétés dans l'ordre
	 * des parts variétales, puis les calibres, puis « Non spécifiée ». La somme des parts d'un jour vaut la
	 * tête ; une part qui s'annule (+2 kg puis -2 kg le mercredi) sort des parts, et son jour de la tête.
	 * Une culture dont il ne reste que du non ventilé n'a pas de parts.
	 */
	public function testParts(): void {

		$week = $this->getSeason().'-W39';
		$monday = week_date_day($week, 1);
		$tuesday = week_date_day($week, 2);
		$wednesday = week_date_day($week, 3);

		$eCultivation = $this->createCultivation();
		$this->setExpected($eCultivation, \series\Cultivation::WEEK, [$week]);

		$eVariety1 = $this->createVariety($eCultivation['plant']);
		$eVariety2 = $this->createVariety($eCultivation['plant']);
		$this->createSlice($eCultivation, $eVariety1, 50.0);
		$this->createSlice($eCultivation, $eVariety2, 50.0);
		$eSize = $this->createSize($eCultivation['plant']);

		$this->createHarvestOf($eCultivation, [], 5.0, $monday);
		$this->createHarvestOf($eCultivation, ['variety' => $eVariety2], 2.0, $monday);
		$this->createHarvestOf($eCultivation, ['harvestSize' => $eSize], 1.0, $monday);
		$this->createHarvestOf($eCultivation, ['variety' => $eVariety1], 3.0, $tuesday);
		$eTaskCancelled = $this->createHarvestOf($eCultivation, ['variety' => $eVariety1, 'harvestSize' => $eSize], 2.0, $wednesday);
		$this->createHarvest($eTaskCancelled, -2.0, $wednesday);

		$eCultivationListed = HarvestWeekLib::get($this->eStructure, $week)[$eCultivation['id']];
		$parts = $eCultivationListed['harvestedParts'];

		$this->assertEquals([$monday => ['kg' => 8.0], $tuesday => ['kg' => 3.0]], $eCultivationListed['harvestedDays'], 'la tête ne vaut pas la somme des parts, ou garde un jour qui s\'annule');

		$varieties = $eCultivationListed['cVarietyChoice']->getIds();
		$expected = [
			\series\CultivationLib::getHarvestPartKey($eVariety1, new \plant\Size()) => array_search($eVariety1['id'], $varieties),
			\series\CultivationLib::getHarvestPartKey($eVariety2, new \plant\Size()) => array_search($eVariety2['id'], $varieties),
		];
		asort($expected);

		$this->assertEquals(
			array_merge(array_keys($expected), [
				\series\CultivationLib::getHarvestPartKey(new \plant\Variety(), $eSize),
				\series\CultivationLib::getHarvestPartKey(new \plant\Variety(), new \plant\Size()),
			]),
			array_keys($parts),
			'les parts ne sont pas rangées variétés, calibres, « Non spécifiée », ou une part qui s\'annule reste'
		);

		$sum = 0.0;

		foreach($parts as $part) {
			$sum += $part['days'][$monday]['kg'] ?? 0.0;
		}

		$this->assertEquals(8.0, $sum, 'les parts du lundi ne somment pas à la tête');
		$this->assertTrue(array_all($parts, fn($part) => $part['writable']), 'une part de la culture ne se saisit pas');

		$eCultivationPlain = $this->createCultivation($this->createSeries('Série sans parts'));
		$this->setExpected($eCultivationPlain, \series\Cultivation::WEEK, [$week]);

		$this->createHarvestOf($eCultivationPlain, [], 4.0, $monday);
		$eTaskPlainCancelled = $this->createHarvestOf($eCultivationPlain, ['harvestSize' => $eSize], 1.0, $monday);
		$this->createHarvest($eTaskPlainCancelled, -1.0, $monday);

		$eCultivationPlainListed = HarvestWeekLib::get($this->eStructure, $week)[$eCultivationPlain['id']];

		$this->assertEquals([], $eCultivationPlainListed['harvestedParts'], 'une culture dont la part ventilée s\'annule garde des parts');
		$this->assertEquals([$monday => ['kg' => 4.0]], $eCultivationPlainListed['harvestedDays'], 'la tête de la culture non ventilée change');

	}

	/**
	 * Une variété qui ne porte que du temps garde sa ligne, même sans aucune récolte de la semaine. Une saisie
	 * ne disparaît jamais de l'écran qui l'a reçue (M-Planning, 21/10). Le temps qui compte est celui que l'écran somme : celui d'une intervention terminée la semaine d'avant ne
	 * fait pas paraître « Non spécifiée », qui n'aurait alors rien à montrer.
	 */
	public function testPartTimeOnly(): void {

		$week = $this->getSeason().'-W39';
		$monday = week_date_day($week, 1);
		$tuesday = week_date_day($week, 2);

		$eCultivation = $this->createCultivation();
		$this->setExpected($eCultivation, \series\Cultivation::WEEK, [$week]);

		$eVarietyHarvested = $this->createVariety($eCultivation['plant']);
		$this->createSlice($eCultivation, $eVarietyHarvested, 50.0);

		$eVarietyTimed = $this->createVariety($eCultivation['plant']);
		$this->createSlice($eCultivation, $eVarietyTimed, 50.0);

		$eTaskHarvested = $this->createHarvestOf($eCultivation, ['variety' => $eVarietyHarvested], 2.0, $monday);

		$eTaskTimed = $this->createTaskHarvest($eCultivation, $week, $tuesday);
		$this->ventilate($eTaskTimed, ['variety' => $eVarietyTimed]);

		$this->createTimesheetOn($eTaskTimed, 0.75, $tuesday);

		$eTaskBefore = $this->createHarvestOf($eCultivation, [], 1.0, week_date_day($this->getSeason().'-W38', 5));
		$this->createTimesheetOn($eTaskBefore, 0.25, $tuesday);

		$keyHarvested = \series\CultivationLib::getHarvestPartKey($eVarietyHarvested, new \plant\Size());
		$keyTimed = \series\CultivationLib::getHarvestPartKey($eVarietyTimed, new \plant\Size());

		$eCultivationWeek = HarvestWeekLib::get($this->eStructure, $week)[$eCultivation['id']];

		$this->assertEquals($this->sortKeys([$keyHarvested, $keyTimed]), $this->getPartKeys($eCultivationWeek), 'la semaine perd la variété qui ne porte que du temps, ou montre « Non spécifiée » sur le temps d\'une intervention qu\'elle ne somme pas');
		$this->assertEquals([[], [$tuesday => 0.75]], [$eCultivationWeek['harvestedParts'][$keyTimed]['week'], $eCultivationWeek['harvestedParts'][$keyTimed]['timeDays']], 'la variété qui ne porte que du temps n\'a pas son temps, ou porte une quantité');

		// Le temps seul suffit à faire paraître la part sans aucune récolte de la semaine.
		\series\HarvestLib::delete(\series\Harvest::model()
			->select(\series\Harvest::getSelection())
			->whereTask($eTaskHarvested)
			->get());

		$this->assertEquals([$keyTimed], array_keys(HarvestWeekLib::get($this->eStructure, $week)[$eCultivation['id']]['harvestedParts']), 'sans récolte de la semaine, la variété qui porte du temps n\'a pas sa ligne');

	}

	/**
	 * Le choix du couple ne paraît que s'il y a à choisir : la variété dès deux parts variétales, le
	 * calibre quand l'espèce en déclare, rien pour une variété seule sans calibre (planche, section 5).
	 */
	public function testChoice(): void {

		$week = $this->getSeason().'-W39';
		$ePlant = $this->createPlant();

		$cases = [];

		$eCultivationOne = $this->createCultivation($this->createSeries('Série à une variété'), $ePlant);
		$this->createSlice($eCultivationOne, $this->createVariety($ePlant));
		$cases['une variété sans calibre'] = [$eCultivationOne, FALSE, FALSE];

		$eCultivationTwo = $this->createCultivation($this->createSeries('Série à deux variétés'), $ePlant);
		$this->createSlice($eCultivationTwo, $this->createVariety($ePlant), 50.0);
		$this->createSlice($eCultivationTwo, $this->createVariety($ePlant), 50.0);
		$cases['deux variétés sans calibre'] = [$eCultivationTwo, TRUE, FALSE];

		$eCultivationSize = $this->createCultivation($this->createSeries('Série à calibre'));
		$this->createSlice($eCultivationSize, $this->createVariety($eCultivationSize['plant']));
		$this->createSize($eCultivationSize['plant']);
		$cases['une variété et un calibre'] = [$eCultivationSize, FALSE, TRUE];

		foreach($cases as $case => [$eCultivation, $variety, $size]) {

			$this->setExpected($eCultivation, \series\Cultivation::WEEK, [$week]);

			$eCultivationListed = HarvestWeekLib::get($this->eStructure, $week)[$eCultivation['id']];

			// Le tiroir reçoit la culture telle que `open` la prépare : la caisse fait partie de cette préparation.
			HarvestWeekLib::prepareCrate($eCultivationListed['plant']);

			$h = new HarvestEntryUi()->getDrawer($this->eStructure, $week, $eCultivationListed, week_date_day($week, 1), HarvestWeekLib::getDays($week), 'add', [], [], HarvestWeekLib::getTimeUsers($this->eStructure, $eCultivationListed, $week, HarvestWeekLib::getDays($week), 'add'));

			$this->assertEquals($variety, str_contains($h, 'name="variety"'), $case.' : le choix de la variété');
			$this->assertEquals($size, str_contains($h, 'name="harvestSize"'), $case.' : le choix du calibre');

		}

	}

	/**
	 * Le titre de la quantité dit le geste du tiroir, ajout ou remplacement, devant aujourd'hui.
	 */
	public function testDrawerDay(): void {

		$week = $this->getSeason().'-W39';
		$eCultivation = $this->createCultivation($this->createSeries('Série du tiroir'));

		$this->setExpected($eCultivation, \series\Cultivation::WEEK, [$week]);

		$eCultivationListed = HarvestWeekLib::get($this->eStructure, $week)[$eCultivation['id']];
		HarvestWeekLib::prepareCrate($eCultivationListed['plant']);

		$monday = week_date_day($week, 1);

		// Aujourd'hui se dit dans les deux gestes, en minuscules derrière le verbe (retour 299).
		$daysToday = HarvestWeekLib::getDays($week);
		$daysToday[$monday]['today'] = TRUE;

		foreach(['add' => 'Ajouter aujourd\'hui', 'replace' => 'Remplacer aujourd\'hui'] as $mode => $title) {

			$h = new HarvestEntryUi()->getDrawer($this->eStructure, $week, $eCultivationListed, $monday, $daysToday, $mode, [], [], HarvestWeekLib::getTimeUsers($this->eStructure, $eCultivationListed, $week, $daysToday, $mode));

			$this->assertEquals($title, $this->getDrawerTitles($h)[0] ?? '', $mode.' : le titre de la quantité ne dit pas le geste devant aujourd\'hui');

		}

	}

	/**
	 * Une récolte sans culture (une intervention hors série) n'entre pas, et la récolte d'une autre
	 * structure ne se lit pas, même quand elle désigne une culture de la structure.
	 */
	public function testHarvestsNotRead(): void {

		if($this->requireStructureForeign() === FALSE) {
			return;
		}

		$season = $this->getSeason();
		$week = $season.'-W39';
		$date = week_date_day($week, 3);

		$eCultivation = $this->createCultivation();

		// L'intervention de la culture du cas, et non getTaskHarvest() : sa culture reprendrait la série et
		// l'espèce du décor, que l'index unique (series, plant) refuse, et create() fermerait alors la
		// transaction du test, laissant en base tout ce qui s'écrit ensuite.
		$eTask = $this->createTaskHarvest($eCultivation, $week, $date);

		$before = HarvestWeekLib::get($this->eStructure, $week)->getIds();

		// Deux lignes que ni l'écran ni l'API ne savent écrire, et c'est ce qu'elles éprouvent : une
		// récolte sans culture, une récolte d'une autre structure qui désigne une culture d'ici.
		// build() refuserait la seconde, dont la culture n'appartient pas à la structure.
		foreach([
			[$this->eStructure, new \series\Series(), new \series\Cultivation(), $eTask],
			[$this->getStructureForeign(), $eCultivation['series'], $eCultivation, $this->createTaskForeign()],
		] as [$eStructure, $eSeries, $eCultivationHarvest, $eTaskHarvest]) {

			$eHarvest = new \series\Harvest([
				'structure' => $eStructure,
				'series' => $eSeries,
				'cultivation' => $eCultivationHarvest,
				'task' => $eTaskHarvest,
				'quantity' => 7.0,
				'unit' => \series\Task::KG,
				'date' => $date,
				'week' => $week,
				'createdBy' => $this->getMember(),
			]);

			\series\Harvest::model()->insert($eHarvest);

		}

		$after = HarvestWeekLib::get($this->eStructure, $week)->getIds();

		$this->assertFalse(in_array($eCultivation['id'], $after), 'la récolte d\'une autre structure fait entrer une culture d\'ici');
		$this->assertEquals($before, $after, 'une récolte sans culture ou d\'une autre structure change la liste');

	}

	/**
	 * La règle de nom : trois séries de même nom, sur la même espèce et sans planche. Deux partent
	 * en s10, la troisième en s20. L'espèce comptant plusieurs cultures, chaque ligne dit sa semaine
	 * de départ ; les deux lignes de s10 restent identiques malgré tout, et elles seules disent leurs
	 * variétés.
	 */
	public function testNames(): void {

		$season = $this->getSeason();
		$week = $season.'-W39';

		$ePlant = $this->createPlant();

		$eVarietyFirst = $this->createVariety($ePlant);
		$eVarietySecond = $this->createVariety($ePlant);

		$eCultivationFirst = $this->createCultivationStarting($ePlant, 10, $week);
		$this->createSlice($eCultivationFirst, $eVarietyFirst);

		$eCultivationSecond = $this->createCultivationStarting($ePlant, 10, $week);
		$this->createSlice($eCultivationSecond, $eVarietySecond);

		$eCultivationLater = $this->createCultivationStarting($ePlant, 20, $week);

		$cCultivation = HarvestWeekLib::get($this->eStructure, $week);

		foreach([$eCultivationFirst, $eCultivationSecond, $eCultivationLater] as $eCultivation) {

			if($this->assertTrue($cCultivation->offsetExists($eCultivation['id']), 'la culture '.$eCultivation['id'].' prévue en '.$week.' n\'y est pas') === FALSE) {
				return;
			}

		}

		$eCultivationFirstListed = $cCultivation[$eCultivationFirst['id']];
		$eCultivationLaterListed = $cCultivation[$eCultivationLater['id']];

		$this->assertTrue($eCultivationLaterListed['nameWithStartWeek'], 'une espèce à plusieurs cultures ne dit pas la semaine de départ');
		$this->assertTrue($eCultivationFirstListed['nameWithVarieties'], 'deux lignes identiques ne disent pas leurs variétés');
		$this->assertTrue($cCultivation[$eCultivationSecond['id']]['nameWithVarieties'], 'deux lignes identiques ne disent pas leurs variétés');
		$this->assertFalse($eCultivationLaterListed['nameWithVarieties'], 'une ligne que sa semaine de départ distingue dit encore ses variétés');
		$this->assertFalse($eCultivationLaterListed['nameWithSeason'], 'une série de la saison de la semaine dit sa saison');

	}

	/**
	 * Les colonnes vont de lundi à dimanche, dans toute semaine. Le jour ouvert est aujourd'hui en
	 * arrivant, le lundi d'une semaine passée, aucun dans une semaine à venir ; un jour ouvert à l'écran
	 * (`$open`) le remplace s'il est de la semaine et pas à venir, sinon on revient au jour d'arrivée. Sans
	 * jour par défaut, une semaine passée n'ouvre rien et propose son dimanche à la ligne.
	 */
	public function testDays(): void {

		$this->setClock('2026-09-24 10:00:00');

		$days = HarvestWeekLib::getDays('2026-W39');

		$this->assertEquals(['2026-09-21', '2026-09-22', '2026-09-23', '2026-09-24', '2026-09-25', '2026-09-26', '2026-09-27'], array_keys($days), 'les jours ne vont pas de lundi à dimanche');
		$this->assertTrue($days['2026-09-24']['today'], 'jeudi n\'est pas aujourd\'hui');
		$this->assertEquals(4, $days['2026-09-24']['day'], 'jeudi n\'est pas le quatrième jour');
		$this->assertEquals(24, $days['2026-09-24']['number'], 'jeudi n\'est pas le 24');
		$this->assertTrue($days['2026-09-25']['future'], 'vendredi n\'est pas à venir');
		$this->assertFalse($days['2026-09-23']['future'], 'mercredi est à venir');
		$this->assertEquals(['2026-09-24'], array_keys(array_filter($days, fn($day) => $day['open'])), 'aujourd\'hui n\'est pas le seul jour ouvert en arrivant');

		$opened = [
			'2026-09-22' => '2026-09-22',
			'2026-09-26' => '2026-09-24',
			'2026-09-28' => '2026-09-24',
		];

		foreach($opened as $open => $expected) {
			$days = HarvestWeekLib::getDays('2026-W39', $open);
			$this->assertEquals([$expected], array_keys(array_filter($days, fn($day) => $day['open'])), 'le jour ouvert demandé '.$open.' ne rend pas '.$expected);
		}

		$days = HarvestWeekLib::getDays('2026-W38');

		$this->assertEquals('2026-09-14', array_key_first($days), 'une semaine passée ne commence pas par lundi');
		$this->assertEquals([], array_filter($days, fn($day) => $day['today'] or $day['future']), 'une semaine passée porte un jour à venir');
		$this->assertEquals(['2026-09-14'], array_keys(array_filter($days, fn($day) => $day['open'])), 'une semaine passée ne s\'ouvre pas sur lundi');
		$this->assertEquals(['2026-09-14'], array_keys(array_filter($days, fn($day) => $day['proposed'])), 'une semaine passée ne propose pas son jour ouvert');

		// La Semaine des récoltes n'ouvre pas de jour par défaut sur une semaine passée (R-Retours-1210, HD).
		$days = HarvestWeekLib::getDays('2026-W38', defaultOpen: FALSE);

		$this->assertEquals([], array_filter($days, fn($day) => $day['open']), 'une semaine passée sans jour par défaut en ouvre un');
		$this->assertEquals(['2026-09-20'], array_keys(array_filter($days, fn($day) => $day['proposed'])), 'une semaine passée sans jour ouvert ne propose pas son dimanche');

		$days = HarvestWeekLib::getDays('2026-W38', '2026-09-16', FALSE);

		$this->assertEquals(['2026-09-16'], array_keys(array_filter($days, fn($day) => $day['open'])), 'le jour demandé d\'une semaine passée ne s\'ouvre pas sans jour par défaut');
		$this->assertEquals(['2026-09-16'], array_keys(array_filter($days, fn($day) => $day['proposed'])), 'le jour ouvert n\'est pas le jour proposé');

		$days = HarvestWeekLib::getDays('2026-W39', defaultOpen: FALSE);

		$this->assertEquals(['2026-09-24'], array_keys(array_filter($days, fn($day) => $day['open'])), 'la semaine courante ne s\'ouvre pas sur aujourd\'hui sans jour par défaut');

		$days = HarvestWeekLib::getDays('2026-W40', '2026-09-30');

		$this->assertEquals([], array_filter($days, fn($day) => $day['open']), 'une semaine à venir a un jour ouvert');
		$this->assertEquals([], array_filter($days, fn($day) => $day['proposed']), 'une semaine à venir propose un jour');

	}

	/**
	 * Les voisins d'un jour, qu'ouvrent les chevrons de son titre : le jour d'à côté dans la semaine, sauf au
	 * bout de la semaine et devant un jour à venir. Une semaine passée ouverte sans jour les porte aussi, pour
	 * le premier pli qu'on y ouvre.
	 */
	public function testDayNeighbours(): void {

		$this->setClock('2026-09-24 10:00:00');

		$days = HarvestWeekLib::getDays('2026-W39');

		$this->assertEquals([NULL, '2026-09-22'], [$days['2026-09-21']['previous'], $days['2026-09-21']['next']], 'lundi n\'a pas pour seul voisin mardi');
		$this->assertEquals(['2026-09-22', '2026-09-24'], [$days['2026-09-23']['previous'], $days['2026-09-23']['next']], 'mercredi n\'a pas mardi et jeudi pour voisins');
		$this->assertEquals(['2026-09-23', NULL], [$days['2026-09-24']['previous'], $days['2026-09-24']['next']], 'aujourd\'hui ouvre demain, qui est à venir');

		foreach([HarvestWeekLib::getDays('2026-W38'), HarvestWeekLib::getDays('2026-W38', defaultOpen: FALSE)] as $days) {
			$this->assertEquals(['2026-09-14', '2026-09-16'], [$days['2026-09-15']['previous'], $days['2026-09-15']['next']], 'mardi d\'une semaine passée n\'a pas lundi et mercredi pour voisins');
			$this->assertEquals(['2026-09-19', NULL], [$days['2026-09-20']['previous'], $days['2026-09-20']['next']], 'dimanche d\'une semaine passée ouvre la semaine suivante');
		}

	}

	/**
	 * La semaine en plis de `util-bellows` : la ligne saisit le jour ouvert, que porte son
	 * `data-planning-harvest-open`.
	 */
	public function testBellows(): void {

		$season = $this->getSeason();
		$week = $season.'-W39';
		$monday = week_date_day($week, 1);

		$this->setClock(week_date_day($week, 4).' 10:00:00');

		$eCultivation = $this->createCultivation();
		$this->setExpected($eCultivation, \series\Cultivation::WEEK, [$week, $season.'-W40']);
		$this->createHarvestInUnit($eCultivation, \series\Task::KG, 2.0, $monday);

		$cCultivation = HarvestWeekLib::get($this->eStructure, $week);

		if($this->assertTrue($cCultivation->offsetExists($eCultivation['id']), 'la culture prévue n\'est pas listée en '.$week) === FALSE) {
			return;
		}


		// Le lien « remplacer » de la cellule du lundi porte aussi `date=<lundi>` : seul l'attribut de la ligne dit le jour saisi.
		$rowOpen = fn(string $open) => preg_match('/^<div class="util-table-row"[^>]* data-planning-harvest-open="([^"]*)"/', new HarvestWeekUi()->getRow($this->eStructure, $week, HarvestWeekLib::getDays($week, $open), $cCultivation[$eCultivation['id']], canHarvest: TRUE), $match) ? html_entity_decode($match[1]) : '';

		$this->assertTrue(str_contains($rowOpen($monday), 'date='.$monday), 'la ligne rendue sur lundi ouvert ne saisit pas lundi');
		$this->assertTrue(str_contains($rowOpen(week_date_day($week, 4)), 'date='.week_date_day($week, 4)), 'la ligne rendue sur jeudi ouvert ne saisit pas jeudi');
		$this->assertFalse(str_contains($rowOpen(week_date_day($week, 4)), 'date='.$monday), 'la ligne rendue sur jeudi ouvert saisit encore lundi');

	}

	/**
	 * Retour 410 : chaque culture listée compte ses récoltes à faire jour par jour sur les jours à venir de la
	 * semaine (`plannedDays`), ceux de la semaine courante après aujourd'hui comme ceux d'une semaine à venir.
	 * Une récolte sans jour précis, une récolte faite et une récolte prévue aujourd'hui ou avant, qui est en
	 * retard, n'y entrent pas. Une récolte prévue ne fait pas entrer sa culture dans la liste.
	 */
	public function testPlannedDays(): void {

		$season = $this->getSeason();
		$week = $season.'-W39';
		$weekNext = $season.'-W40';

		$this->setClock(week_date_day($week, 4).' 10:00:00');

		$create = function(string $name, array $weeks): \series\Cultivation {
			$eCultivation = $this->createCultivation($this->createSeries($name));
			$this->setExpected($eCultivation, \series\Cultivation::WEEK, $weeks);
			return $eCultivation;
		};

		$eCultivationTwoDays = $create('deux jours', [$week, $weekNext]);
		$this->createTask($eCultivationTwoDays, ACTION_RECOLTE, \series\Task::TODO, $weekNext, week_date_day($weekNext, 2));
		$this->createTask($eCultivationTwoDays, ACTION_RECOLTE, \series\Task::TODO, $weekNext, week_date_day($weekNext, 4));

		$eCultivationSameDay = $create('deux récoltes le même jour', [$week, $weekNext]);
		$this->createTask($eCultivationSameDay, ACTION_RECOLTE, \series\Task::TODO, $weekNext, week_date_day($weekNext, 3));
		$this->createTask($eCultivationSameDay, ACTION_RECOLTE, \series\Task::TODO, $weekNext, week_date_day($weekNext, 3));

		$eCultivationNoDate = $create('sans jour précis', [$week, $weekNext]);
		$this->createTask($eCultivationNoDate, ACTION_RECOLTE, \series\Task::TODO, $weekNext);

		$eCultivationDone = $create('récolte faite', [$week, $weekNext]);
		$this->createTaskHarvest($eCultivationDone, $week, week_date_day($week, 6));

		$eCultivationLate = $create('en retard et à venir', [$week, $weekNext]);
		$this->createTask($eCultivationLate, ACTION_RECOLTE, \series\Task::TODO, $week, week_date_day($week, 2));
		$this->createTask($eCultivationLate, ACTION_RECOLTE, \series\Task::TODO, $week, week_date_day($week, 4));
		$this->createTask($eCultivationLate, ACTION_RECOLTE, \series\Task::TODO, $week, week_date_day($week, 6));

		$eCultivationOutside = $create('hors liste', [$season.'-W20']);
		$this->createTask($eCultivationOutside, ACTION_RECOLTE, \series\Task::TODO, $weekNext, week_date_day($weekNext, 3));

		$cases = [
			$week => [
				'deux jours' => [$eCultivationTwoDays, []],
				'récolte faite' => [$eCultivationDone, []],
				'en retard et à venir' => [$eCultivationLate, [week_date_day($week, 6) => 1]],
			],
			$weekNext => [
				'deux jours' => [$eCultivationTwoDays, [week_date_day($weekNext, 2) => 1, week_date_day($weekNext, 4) => 1]],
				'deux récoltes le même jour' => [$eCultivationSameDay, [week_date_day($weekNext, 3) => 2]],
				'sans jour précis' => [$eCultivationNoDate, []],
			],
		];

		foreach($cases as $weekCase => $cultivations) {

			$cCultivation = HarvestWeekLib::get($this->eStructure, $weekCase);

			$this->assertFalse($cCultivation->offsetExists($eCultivationOutside['id']), 'une récolte prévue fait entrer sa culture dans la liste de '.$weekCase);

			foreach($cultivations as $case => [$eCultivation, $expected]) {

				if($this->assertTrue($cCultivation->offsetExists($eCultivation['id']), $case.', la culture n\'est pas listée en '.$weekCase) === FALSE) {
					continue;
				}

				$this->assertEquals($expected, $cCultivation[$eCultivation['id']]['plannedDays'], $case.', les récoltes prévues de '.$weekCase.' ne sont pas celles des jours à venir');

			}

		}

		$this->assertEquals([], \series\TaskLib::getHarvestPlannedForWeek($this->eStructure, new \Collection([$eCultivationLate]), $season.'-W38'), 'une semaine passée a des récoltes prévues à venir');

	}

	/**
	 * Retours 238 et 239 : l'intertitre d'une espèce porte les sept plis de la semaine juste après son nom,
	 * dans l'ordre de la tête, que `MainBellows.open()` suit par rang d'enfant.
	 */
	public function testSectionColumns(): void {

		$season = $this->getSeason();
		$week = $season.'-W39';

		$this->setClock(week_date_day($week, 4).' 10:00:00');

		$section = $this->getDrawerDocument(new HarvestWeekUi()->getSection($this->getPlant(), HarvestWeekLib::getDays($week)))->querySelector('.planning-harvest-section');
		$classes = array_map(fn(\Dom\Element $node) => $node->getAttribute('class'), iterator_to_array($section?->parentNode->querySelectorAll('.planning-harvest-section > *') ?? []));
		$days = array_slice($classes, 1, 7);

		$this->assertEquals(7, count(array_filter($days, fn(string $class) => str_contains($class, 'planning-harvest-day util-bellows-item'))), 'l\'intertitre ne pose pas ses sept plis juste après son nom, dans l\'ordre de la tête : '.implode(' | ', $classes));

	}

	/**
	 * Une culture seule de son espèce ne dit pas sa semaine de départ dans son nom. Une culture pérenne dit
	 * son année (`CultivationUi::getPerennialSeason()`), une annuelle non.
	 */
	public function testStartWeek(): void {

		$season = $this->getSeason();
		$week = $season.'-W39';

		$eCultivationAnnual = $this->createCultivationStarting($this->createPlant(), 10, $week);

		$eCultivationPerennial = $this->createCultivation($this->createSeriesPerennial(), $this->createPlant());
		$this->setExpected($eCultivationPerennial, \series\Cultivation::WEEK, [$week]);

		// Sans build(), pour la même raison que `createCultivationStarting()` : la semaine et la façon de
		// départ viennent des interventions de plantation, que le décor ne pose pas.
		$eCultivationPerennial['startWeek'] = 14;
		$eCultivationPerennial['startAction'] = \series\Cultivation::PLANTING;
		\series\CultivationLib::update($eCultivationPerennial, ['startWeek', 'startAction']);

		$cCultivation = HarvestWeekLib::get($this->eStructure, $week);

		foreach([$eCultivationAnnual, $eCultivationPerennial] as $eCultivation) {

			if($this->assertDecorPresent($cCultivation->offsetExists($eCultivation['id']), 'la culture '.$eCultivation['id'].' prévue en '.$week.' est listée') === FALSE) {
				return;
			}

		}

		$this->assertFalse($cCultivation[$eCultivationAnnual['id']]['nameWithStartWeek'], 'la culture seule de son espèce dit sa semaine dans son nom, le cas éprouvé n\'est pas celui de « Betterave 3 »');

		$crop = fn(\series\Cultivation $eCultivation) => $this->getDrawerDocument(new HarvestWeekUi()->getRow($this->eStructure, $week, HarvestWeekLib::getDays($week), $cCultivation[$eCultivation['id']]))->querySelector('.planning-harvest-crop');

		$cropAnnual = $crop($eCultivationAnnual);
		$cropPerennial = $crop($eCultivationPerennial);

		$this->assertFalse(str_contains($cropAnnual?->textContent ?? s("Année {value}", 1), s("Année {value}", 1)), 'une culture annuelle dit une année');
		$this->assertTrue(str_contains($cropPerennial?->textContent ?? '', s("Année {value}", 1)), 'la ligne d\'une culture pérenne ne dit pas son année');

	}

	/**
	 * Retour 103 : l'assolement s'arrête après `TaskCardUi::PLACES` planches. `PLACES` planches s'affichent
	 * entières, une de plus s'arrête à `PLACES`.
	 */
	public function testPlacesLimit(): void {

		$ePlot = $this->createPlot();

		$cBed = new \Collection();

		for($position = 0; $position < TaskCardUi::PLACES + 1; $position++) {
			$eBed = $this->createBed($ePlot);
			$cBed[$eBed['id']] = $eBed;
		}

		$names = array_values($cBed->makeArray(fn($eBed) => $eBed['name']));
		$nameLast = $names[TaskCardUi::PLACES];

		$eSeriesOver = $this->createSeriesOnBeds($cBed);
		$eSeriesAt = $this->createSeriesOnBeds($cBed->find(fn($eBed) => $eBed['name'] !== $nameLast));

		$h = new TaskCardUi()->getPlaces($eSeriesOver['use'], $eSeriesOver['cccPlace']);

		$this->assertEquals(TaskCardUi::PLACES, count(array_filter($names, fn($name) => str_contains($h, $name))), 'une planche de trop ne s\'arrête pas à '.TaskCardUi::PLACES.' noms : '.strip_tags($h));

		$h = new TaskCardUi()->getPlaces($eSeriesAt['use'], $eSeriesAt['cccPlace']);

		$this->assertEquals(TaskCardUi::PLACES, count(array_filter($names, fn($name) => str_contains($h, $name))), 'la limite atteinte sans la dépasser ne s\'affiche pas entière : '.strip_tags($h));

	}

	/**
	 * GF : une saisie du champ du menu repose entières les espèces voisines de la culture saisie, chacune à sa
	 * place dans la semaine entière, et ne touche à aucune autre. La première ligne d'une espèce porte son
	 * intertitre, la suivante s'ancre sur elle.
	 */
	public function testChangePlants(): void {

		$week = $this->getSeason().'-W42';

		$ePlant = $this->createPlant();
		$ePlantOther = $this->createPlant();

		$eCultivationFirst = $this->createCultivation($this->createSeries('GF première '.uniqid()), $ePlant);
		$eCultivationSecond = $this->createCultivation($this->createSeries('GF seconde '.uniqid()), $ePlant);
		$eCultivationOther = $this->createCultivation($this->createSeries('GF autre '.uniqid()), $ePlantOther);

		foreach([$eCultivationFirst, $eCultivationSecond, $eCultivationOther] as $eCultivation) {
			$this->setExpected($eCultivation, \series\Cultivation::WEEK, [$week]);
		}

		$cPlant = HarvestWeekLib::getPlantsAround($this->eStructure, [$eCultivationFirst['id']]);

		$this->assertEquals([$ePlant['id']], $cPlant->getIds(), 'les espèces voisines ne sont pas la seule espèce de la culture');

		$change = HarvestWeekLib::getChangePlants($this->eStructure, $week, $cPlant);

		$this->assertFalse($change['list'], 'une semaine qui garde une autre espèce se rend entière');
		$this->assertEquals([], $change['removed'], 'des lignes partent par leur identifiant');
		$this->assertEquals([], $change['updated'], 'des lignes se redessinent en place');
		$this->assertEquals([], $change['sections']['updated'], 'des intertitres se redessinent en place');
		$this->assertEquals([$ePlant['id']], $change['sections']['removed'], 'l\'intertitre de l\'espèce voisine ne part pas');

		$this->assertFalse(array_key_exists($eCultivationOther['id'], $change['inserted']), 'une culture d\'une autre espèce est reposée');

		// L'ordre de l'écran : la ligne qui précède chacune dans la semaine entière.
		$ids = HarvestWeekLib::get($this->eStructure, $week)->getIds();
		$previous = fn(int $id) => $ids[array_search($id, $ids) - 1] ?? NULL;

		$cultivations = array_keys($change['inserted']);
		$this->assertEquals(2, count($cultivations), 'les deux cultures de l\'espèce ne sont pas reposées');

		[$first, $second] = $cultivations;

		$this->assertEquals(['after' => $previous($first), 'section' => TRUE], $change['inserted'][$first], 'la première ligne de l\'espèce ne revient pas avec son intertitre après la ligne qui la précède');
		$this->assertEquals(['after' => $first, 'section' => FALSE], $change['inserted'][$second], 'la seconde ligne de l\'espèce ne s\'ancre pas sur la première');

	}

	/**
	 * GF : une semaine qui ne compte que les espèces voisines se rend entière, seul cas où l'écran a pu passer
	 * du vide au plein. Une semaine lointaine de la saison, que le décor laisse vide.
	 */
	public function testChangePlantsWholeList(): void {

		$week = $this->getSeason().'-W03';

		if($this->assertDecorPresent(HarvestWeekLib::get($this->eStructure, $week)->empty(), 'la semaine '.$week.' sans aucune culture en récolte') === FALSE) {
			return;
		}

		$eCultivation = $this->createCultivation($this->createSeries('GF seule '.uniqid()), $this->createPlant());
		$this->setExpected($eCultivation, \series\Cultivation::WEEK, [$week]);

		$cPlant = HarvestWeekLib::getPlantsAround($this->eStructure, [$eCultivation['id']]);

		$this->assertTrue(HarvestWeekLib::getChangePlants($this->eStructure, $week, $cPlant)['list'], 'une semaine qui ne compte que l\'espèce voisine ne se rend pas entière');

		// La culture sortie de la semaine la laisse vide : la liste se rend entière aussi.
		$this->setExpected($eCultivation, \series\Cultivation::WEEK, [$this->getSeason().'-W04']);

		$this->assertTrue(HarvestWeekLib::getChangePlants($this->eStructure, $week, $cPlant)['list'], 'une semaine vidée par la saisie ne se rend pas entière');

	}

	/** La culture sort-elle dans la liste de cette semaine ? */
	private function isListed(\series\Cultivation $eCultivation, string $week): bool {

		return HarvestWeekLib::get($this->eStructure, $week)->offsetExists($eCultivation['id']);

	}

	/**
	 * La liste de la semaine dit quelles espèces se récoltent en caisses sans requête de plus (lot GN) : le
	 * poids de caisse vient de la sélection de l'espèce. Deux espèces de plus, l'une pesée et l'autre non, ne
	 * changent pas le compte.
	 */
	public function testCrateWithoutQuery(): void {

		$week = \Clock::week();

		$createCultivation = function(?float $crateWeight) use ($week): \series\Cultivation {

			$ePlant = $this->createPlant();

			if($crateWeight !== NULL) {
				// Écrit sans build() : le décor veut ce poids, que l'étape `decimal` de `crateWeight` accepterait.
				$ePlant['crateWeight'] = $crateWeight;
				\plant\PlantLib::update($ePlant, ['crateWeight']);
			}

			$eCultivation = $this->createCultivation(new \series\Series(), $ePlant);
			$this->setExpected($eCultivation, \series\Cultivation::WEEK, [$week]);

			return $eCultivation;

		};

		$eCultivationWeighed = $createCultivation(6.0);

		// La première lecture charge une fois les réglages de la structure (conf()), qui restent en mémoire ensuite : la mesure part de la seconde.
		HarvestWeekLib::get($this->eStructure, $week);

		$queries =$this->countQueries(fn() => HarvestWeekLib::get($this->eStructure, $week));

		$eCultivationLight = $createCultivation(NULL);
		$createCultivation(4.5);

		$cCultivation = new \Collection();
		$queriesMore = $this->countQueries(function() use (&$cCultivation, $week) {
			$cCultivation = HarvestWeekLib::get($this->eStructure, $week);
		});

		if($this->assertDecorPresent(($cCultivation->offsetExists($eCultivationWeighed['id']) and $cCultivation->offsetExists($eCultivationLight['id'])), 'les cultures prévues cette semaine sont dans la liste') === FALSE) {
			return;
		}

		$this->assertEquals($queries, $queriesMore, 'deux espèces de plus coûtent des requêtes à la liste de la semaine');
		$this->assertEquals([TRUE, FALSE], [$cCultivation[$eCultivationWeighed['id']]['plant']['crate'], $cCultivation[$eCultivationLight['id']]['plant']['crate']], 'les caisses ne suivent pas le poids de caisse de l\'espèce');

	}

	/**
	 * La récolte attendue, posée par le build() de l'écran : au mois, il recopie les mois en semaines,
	 * ce que le cas du mois éprouve.
	 */
	private function setExpected(\series\Cultivation $eCultivation, string $period, array $values): void {

		$eCultivation['harvestPeriodExpected'] = $period;

		$property = match($period) {
			\series\Cultivation::WEEK => 'harvestWeeksExpected',
			\series\Cultivation::MONTH => 'harvestMonthsExpected',
		};

		$eCultivation->build([$property], [$property => $values]);

		\series\CultivationLib::update($eCultivation, ['harvestPeriodExpected', 'harvestMonthsExpected', 'harvestWeeksExpected']);

	}

	/**
	 * Une récolte dans une unité donnée : l'intervention de récolte du décor prend l'unité de la
	 * culture, et deux unités un même jour demandent deux interventions.
	 */
	private function createHarvestInUnit(\series\Cultivation $eCultivation, string $unit, float $quantity, string $date): \series\Harvest {

		$eTask = new \series\Task([
			'structure' => $this->eStructure,
			'series' => $eCultivation['series'],
			'cultivation' => $eCultivation,
			'plant' => $eCultivation['plant'],
			'action' => $this->getAction(ACTION_RECOLTE),
			'category' => $this->getCategoryCulture(),
			'status' => \series\Task::DONE,
			'doneWeek' => toWeek($date),
			'doneDate' => $date,
			'harvest' => NULL,
			'harvestUnit' => $unit,
			'repeatMaster' => new \series\Repeat(),
		]);

		// Sans build(), qui vérifierait l'action, la catégorie, la semaine et l'unité postées depuis le
		// formulaire d'intervention : le décor veut une intervention faite dans une unité choisie, que
		// la saisie ne laisse pas poser directement, et le test n'éprouve que la lecture de l'écran.
		\series\TaskLib::create($eTask);

		return $this->createHarvest($eTask, $quantity, $date);

	}

	/**
	 * Les parts d'une culture, quel que soit leur rang. Deux variétés du décor se rangent par le tri naturel de
	 * leur nom, que `createVariety()` termine par un `uniqid()` hexadécimal : il ne suit pas l'ordre de
	 * création (`b99e` vient après `b9a0`), et un test qui ne juge pas le rang n'a pas à le connaître.
	 */
	private function getPartKeys(\series\Cultivation $eCultivation): array {
		return $this->sortKeys(array_keys($eCultivation['harvestedParts']));
	}

	private function sortKeys(array $keys): array {
		sort($keys);
		return $keys;
	}

	/**
	 * Un temps d'une personne nommée, au jour dit, sur l'intervention donnée.
	 */
	private function createTimesheetOn(\series\Task $eTask, float $time, string $date): void {

		$eTimesheet = new \series\Timesheet([
			'structure' => $this->eStructure,
			'user' => $this->getMember(),
			'series' => $eTask['series'],
			'cultivation' => $eTask['cultivation'],
			'plant' => $eTask['plant'],
			'task' => $eTask,
			'time' => $time,
			'date' => $date,
			'week' => toWeek($date),
		]);

		// Sans `build()`, qui borne le jour saisi à ce que l'horloge permet : le décor pose un temps dans une
		// semaine de saison choisie, et c'est sa lecture par l'écran qu'éprouve le cas.
		\series\TimesheetLib::create($eTimesheet);

	}

	/**
	 * Une récolte sur une intervention ventilée par `$values` (variété, calibre), une intervention par
	 * récolte : le couple se lit sur l'intervention, pas sur la récolte. Une quantité retirée se pose
	 * ensuite sur l'intervention rendue, qui ne peut pas descendre sous zéro.
	 */
	private function createHarvestOf(\series\Cultivation $eCultivation, array $values, float $quantity, string $date): \series\Task {

		$eTask = $this->createTaskHarvest($eCultivation, toWeek($date), $date);

		if($values !== []) {
			$this->ventilate($eTask, $values);
		}

		$this->createHarvest($eTask, $quantity, $date);

		return $eTask;

	}

	/**
	 * Une culture de l'espèce donnée, dans une série de nom commun à toutes, partie à la semaine
	 * donnée et prévue en récolte la semaine cherchée.
	 */
	private function createCultivationStarting(\plant\Plant $ePlant, int $startWeek, string $week): \series\Cultivation {

		$eCultivation = $this->createCultivation($this->createSeries('Série jumelle'), $ePlant);

		$this->setExpected($eCultivation, \series\Cultivation::WEEK, [$week]);

		// Après la récolte prévue, que `SeriesLib::recalculate()` suit en recalculant la semaine de
		// départ depuis les interventions. Sans build(), qui n'a rien à vérifier : la semaine et la façon
		// de départ ne sont pas une saisie, la culture les reçoit ensemble de ses interventions de semis
		// ou de plantation, que le décor ne pose pas.
		$eCultivation['startWeek'] = $startWeek;
		$eCultivation['startAction'] = \series\Cultivation::SOWING;
		\series\CultivationLib::update($eCultivation, ['startWeek', 'startAction']);

		return $eCultivation;

	}

	/**
	 * Une série en planches posée sur les planches données, relue avec l'assolement que lit le planning.
	 */
	private function createSeriesOnBeds(\Collection $cBed): \series\Series {

		$eSeries = $this->createSeriesBed('R103 '.uniqid());

		foreach($cBed as $eBed) {
			$this->createPlace($eSeries, $eBed, 10);
		}

		return \series\Series::model()
			->select(['id', 'name', 'season', 'use', 'cccPlace' => \series\PlaceLib::delegateBySeries()])
			->whereId($eSeries['id'])
			->get();

	}

	private function getDrawerDocument(string $html): \Dom\HTMLDocument {
		return \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NOERROR);
	}

	private function getDrawerTitles(string $html): array {

		$titles = [];

		foreach($this->getDrawerDocument($html)->querySelectorAll('.planning-harvest-drawer-form .planning-harvest-entry-titled > .util-label') as $node) {
			$titles[] = $node->textContent;
		}

		return $titles;

	}

}
?>
