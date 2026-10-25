<?php
namespace toolbox;

/**
 * La règle de composition (`CompositionLib::propose()`) et le geste « Recommencer »
 * (`CompositionPlanLib::restart()`, qui écrit le plan que le départ envoie).
 *
 * La règle s'éprouve sur des relevés construits à la main plutôt que sur l'agrégé de dev : un
 * relevé réel ne garantit ni une semaine sans fruit, ni deux espèces à rang égal, ni un facteur
 * qui sort de ses bornes, et un test qui dépend de la nuit précédente ne dit rien de la règle.
 * Chaque cas a son pendant, pour qu'un contrôle qui ne trouve rien ne passe pas pour réussi :
 * le plancher relâché sans fruit et tenu avec un fruit, le bruit qui départage deux dettes
 * voisines et n'inverse pas deux dettes éloignées, le facteur qui ajoute une ligne et celui qui
 * en retire une.
 *
 * L'ordre du tirage suit la dette plus un bruit qui peut atteindre un panier : entre deux espèces
 * dont les parts attendues s'écartent de moins de 1, rien ne dit laquelle passe devant. Un cas
 * qui a besoin d'un ordre sûr met donc en tête des espèces à part 1 et derrière des espèces à
 * part 0 ; les autres vérifient ce que la règle garantit quel que soit le bruit (un nombre de
 * lignes, un organe, un facteur), et non la liste exacte des espèces.
 *
 * Trois cas lisent le relevé réel de dev : le même prix qui rend le même panier, l'absence de
 * période, et `restart()`, sur la saison 2099 qu'aucune structure n'emploie.
 *
 * Isolation : une transaction par méthode, annulée en tearDown().
 *
 * Lancement : dev/test package=toolbox file=CompositionRule
 */
class CompositionRuleTest extends \Test {

	const SEASON = 2026;

	/**
	 * Les bornes du nombre de lignes d'avant r11, 4 et 10, que ce fichier passe à la règle : ses
	 * décomptes épinglés ont été mesurés avec elles. Les bornes réglées par formule ont leurs propres
	 * tests (`CompositionRuleTest::testLinesBounds()`).
	 */
	const LINES_MIN = 4;
	const LINES_MAX = 10;

	const EPSILON = 0.000001;

	/**
	 * L'écart admis entre le recouvrement mesuré à un décalage et le plancher de deux tirages
	 * indépendants à parts respectées, et les prix où il se mesure (critère 1 du lot, 21/09).
	 */
	const PERIOD_GAP = 0.05;

	/**
	 * La marge admise au-dessus du décalage de référence avant de parler de pic. Moyennés sur dix
	 * saisons, les décalages de la règle de la dette flottaient de +0,003 à +0,005 autour du premier à
	 * 12 € (bruit de mesure, une saison déplaçant un décalage de deux ou trois centièmes) ; la
	 * première règle montait à +0,55 au décalage 3. Tranché le 21/09 avec la relance du nombre de
	 * lignes. La référence est le décalage 3 depuis l'écart minimal des espèces faibles et de
	 * diversification (r10) : voir `testNoPeriod()`.
	 */
	const PERIOD_PEAK = 0.01;
	const PERIOD_PRICES = [12.0, 16.0, 20.0];
	const PERIOD_SEASONS = [2022, 2023, 2024, 2025, 2026, 2027, 2028, 2029, 2030, 2031];

	public function setUp(): void {
		CompositionPlan::model()->beginTransaction();
	}

	public function tearDown(): void {
		CompositionPlan::model()->rollBack();
	}

	/**
	 * Une semaine d'hiver : trois feuilles, trois racines, aucun fruit. Le plancher ne peut pas
	 * être tenu pour le fruit, et la semaine le dit ; il l'est pour les deux autres organes. Dès
	 * qu'un fruit est disponible, même classé dernier, il entre et le plancher n'est plus relâché.
	 */
	public function testFloorRelaxedWithoutFruit(): void {

		$species = [
			1 => ['organ' => \plant\Plant::LEAF, 'share' => 0.9],
			2 => ['organ' => \plant\Plant::LEAF, 'share' => 0.8],
			3 => ['organ' => \plant\Plant::LEAF, 'share' => 0.7],
			4 => ['organ' => \plant\Plant::ROOT, 'share' => 0.6],
			5 => ['organ' => \plant\Plant::ROOT, 'share' => 0.5],
			6 => ['organ' => \plant\Plant::ROOT, 'share' => 0.4],
		];

		$proposal = CompositionLib::propose(6.0, 5, self::LINES_MIN, self::LINES_MAX, [20], NULL, self::SEASON, $this->getSource($species, [20]), [], []);

		$this->assertEquals([\plant\Plant::FRUIT], $proposal[20]['relaxedFloor'], 'semaine sans fruit');
		$this->assertEquals(6, count($proposal[20]['lines']), 'lignes sans fruit');

		$species[7] = ['organ' => \plant\Plant::FRUIT, 'share' => 0.1];

		$proposal = CompositionLib::propose(6.0, 5, self::LINES_MIN, self::LINES_MAX, [20], NULL, self::SEASON, $this->getSource($species, [20]), [], []);

		$this->assertEquals([], $proposal[20]['relaxedFloor'], 'semaine avec un fruit');
		$this->assertTrue(in_array(7, array_column($proposal[20]['lines'], 'species'), TRUE), 'le fruit classé dernier entre au panier');

	}

	/**
	 * Un seul chou par panier (`CompositionLib::CABBAGE`) : trois choux en tête de l'ordre n'en
	 * donnent qu'un par semaine, sur dix semaines. Les mêmes espèces sans la marque du chou en
	 * serviraient plusieurs, ce qui prouve que le contrôle sait trouver un second chou.
	 */
	public function testOneCabbagePerWeek(): void {

		$species = [];

		foreach(range(1, 3) as $plantId) {
			$species[$plantId] = ['organ' => NULL, 'share' => 1.0, 'cabbage' => TRUE];
		}

		foreach(range(4, 12) as $plantId) {
			$species[$plantId] = ['organ' => NULL, 'share' => 0.2];
		}

		$weeks = range(20, 29);

		$cabbages = function(array $proposal): array {

			$counts = [];

			foreach($proposal as $week => $basket) {
				$counts[$week] = count(array_intersect(array_column($basket['lines'], 'species'), [1, 2, 3]));
			}

			return $counts;

		};

		$counts = $cabbages(CompositionLib::propose(6.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $this->getSource($species, $weeks), [], []));

		$this->assertEquals(array_fill_keys($weeks, 1), $counts, 'un chou par semaine');

		foreach(range(1, 3) as $plantId) {
			unset($species[$plantId]['cabbage']);
		}

		$counts = $cabbages(CompositionLib::propose(6.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $this->getSource($species, $weeks), [], []));

		$this->assertTrue(max($counts) > 1, 'sans la règle, plusieurs choux');

	}

	/**
	 * Le plancher ne fait pas entrer un second chou : la seule racine est un chou, le panier sert déjà
	 * un chou-feuille, et le plancher des racines est relâché plutôt que tenu par un second chou.
	 */
	public function testFloorKeepsOneCabbage(): void {

		$species = [
			1 => ['organ' => \plant\Plant::LEAF, 'share' => 0.9, 'cabbage' => TRUE],
			2 => ['organ' => \plant\Plant::LEAF, 'share' => 0.8],
			3 => ['organ' => \plant\Plant::LEAF, 'share' => 0.7],
			5 => ['organ' => \plant\Plant::FRUIT, 'share' => 0.5],
			6 => ['organ' => \plant\Plant::FRUIT, 'share' => 0.4],
			8 => ['organ' => \plant\Plant::FRUIT, 'share' => 0.3],
			7 => ['organ' => \plant\Plant::ROOT, 'share' => 0.1, 'cabbage' => TRUE],
		];

		$proposal = CompositionLib::propose(6.0, 5, self::LINES_MIN, self::LINES_MAX, [20], NULL, self::SEASON, $this->getSource($species, [20]), [], []);
		$plantIds = array_column($proposal[20]['lines'], 'species');

		$this->assertEquals([\plant\Plant::ROOT], $proposal[20]['relaxedFloor'], 'plancher des racines relâché');
		$this->assertFalse(in_array(7, $plantIds, TRUE), 'le chou-racine n\'entre pas');
		$this->assertTrue(in_array(1, $plantIds, TRUE), 'le chou-feuille reste');

		unset($species[1]['cabbage']);

		$proposal = CompositionLib::propose(6.0, 5, self::LINES_MIN, self::LINES_MAX, [20], NULL, self::SEASON, $this->getSource($species, [20]), [], []);

		$this->assertEquals([], $proposal[20]['relaxedFloor'], 'sans second chou, le plancher est tenu');
		$this->assertTrue(in_array(7, array_column($proposal[20]['lines'], 'species'), TRUE), 'par la racine');

	}

	/**
	 * Le pas et le minimum d'une espèce (revue v12) : quatre espèces à 1 € pour un panier de 2,80 €
	 * donnent un facteur borné à 0,7. Au pas de l'unité, 0,1, la quantité proposée vaut 0,7 ; au pas
	 * de 0,5 elle s'arrondit à 0,5, qui est aussi son minimum ; un minimum de 1 la relève à 1. Les
	 * autres espèces gardent le pas de leur unité.
	 *
	 * L'écart est à 20 % : les paniers à 2,60 € et à 3,10 € y restent, et l'ajustement au pas (r23,
	 * `testPriceSteps()`) ne vient pas reprendre l'arrondi que ce cas éprouve.
	 */
	public function testStepAndMinimum(): void {

		$species = [];

		foreach(range(1, 4) as $plantId) {
			$species[$plantId] = ['organ' => NULL, 'share' => 1.0];
		}

		$source = $this->getSource($species, [20]);

		$quantities = function(array $steps) use($source): array {

			$proposal = CompositionLib::propose(2.8, 20, self::LINES_MIN, self::LINES_MAX, [20], NULL, self::SEASON, $source, [], $steps);

			$quantities = array_column($proposal[20]['lines'], 'quantity', 'species');
			ksort($quantities);

			return $quantities;

		};

		$this->assertEquals([1 => 0.7, 2 => 0.7, 3 => 0.7, 4 => 0.7], $quantities([]), 'au pas de l\'unité');
		$this->assertEquals([1 => 0.5, 2 => 0.7, 3 => 0.7, 4 => 0.7], $quantities([1 => ['step' => 0.5, 'minimum' => NULL]]), 'au pas de 0,5');
		$this->assertEquals([1 => 1.0, 2 => 0.7, 3 => 0.7, 4 => 0.7], $quantities([1 => ['step' => 0.5, 'minimum' => 1.0]]), 'jamais sous le minimum');
		$this->assertEquals([1 => 1.0, 2 => 0.7, 3 => 0.7, 4 => 0.7], $quantities([1 => ['step' => NULL, 'minimum' => 1.0]]), 'un minimum sans pas garde le pas de l\'unité');

	}

	/**
	 * Le plafond : aucun organe au-delà de la moitié des lignes. Six feuilles à part 1 en tête et
	 * six racines à part 0 derrière donnent un panier de six lignes à trois feuilles et trois
	 * racines, et non les six feuilles du haut de l'ordre.
	 */
	public function testCeilingDuringDraw(): void {

		$species = [];

		foreach(range(1, 6) as $plantId) {
			$species[$plantId] = ['organ' => \plant\Plant::LEAF, 'share' => 1.0];
		}

		foreach(range(7, 12) as $plantId) {
			$species[$plantId] = ['organ' => \plant\Plant::ROOT, 'share' => 0.0];
		}

		$proposal = CompositionLib::propose(6.0, 5, self::LINES_MIN, self::LINES_MAX, [20], NULL, self::SEASON, $this->getSource($species, [20]), [], []);

		$organs = array_count_values(array_map(fn(int $plantId) => $species[$plantId]['organ'], array_column($proposal[20]['lines'], 'species')));

		$this->assertEquals([\plant\Plant::LEAF => 3, \plant\Plant::ROOT => 3], $organs, 'plafond de moitié');

	}

	/**
	 * Le plancher de lignes passe avant le plafond par organe (r70), sur le cas de la structure qui l'a
	 * signalé : deux feuilles et une racine en tête de l'ordre, une troisième feuille derrière, quatre
	 * à cinq lignes et 10 € visés. Le plafond de deux feuilles écartait la troisième, et la semaine
	 * restait à trois lignes sous le plancher. Elle en a quatre, la troisième feuille comprise, et
	 * relâche seulement le fruit, qu'aucune candidate ne porte. Sans autre candidate que les trois
	 * premières, elle reste à trois lignes.
	 */
	public function testLinesMinBeforeCeiling(): void {

		$species = [
			1 => ['organ' => \plant\Plant::LEAF, 'share' => 0.9],
			2 => ['organ' => \plant\Plant::LEAF, 'share' => 0.8],
			3 => ['organ' => \plant\Plant::ROOT, 'share' => 0.7],
			4 => ['organ' => \plant\Plant::LEAF, 'share' => 0.1],
		];

		$proposal = CompositionLib::propose(10.0, 5, 4, 5, [20], NULL, self::SEASON, $this->getSource($species, [20]), [], []);

		$plants = array_column($proposal[20]['lines'], 'species');
		sort($plants);

		$this->assertEquals([1, 2, 3, 4], $plants, 'la troisième feuille complète le panier jusqu\'au plancher');
		$this->assertEquals([\plant\Plant::FRUIT], $proposal[20]['relaxedFloor'], 'seul le fruit est relâché');

		unset($species[4]);

		$proposal = CompositionLib::propose(10.0, 5, 4, 5, [20], NULL, self::SEASON, $this->getSource($species, [20]), [], []);

		$this->assertEquals(3, count($proposal[20]['lines']), 'sans autre candidate, la semaine reste sous le plancher');

	}

	/**
	 * Sur quarante semaines, chaque espèce entre au panier à sa part attendue, à un panier près :
	 * dix espèces dont les parts somment aux quatre lignes du panier, de 0,9 à 0,05. Une espèce
	 * rare revient, et une espèce fréquente ne prend pas la place des autres.
	 */
	public function testDebtFollowsFrequency(): void {

		$weeks = range(5, 44);
		$shares = [1 => 0.9, 2 => 0.8, 3 => 0.6, 4 => 0.5, 5 => 0.4, 6 => 0.3, 7 => 0.2, 8 => 0.15, 9 => 0.1, 10 => 0.05];

		$species = [];

		foreach($shares as $plantId => $share) {
			$species[$plantId] = ['organ' => NULL, 'share' => $share];
		}

		$proposal = CompositionLib::propose(4.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $this->getSource($species, $weeks), [], []);

		$served = array_fill_keys(array_keys($shares), 0);

		foreach($proposal as $basket) {

			$this->assertEquals(4, count($basket['lines']), 'quatre lignes');

			foreach(array_column($basket['lines'], 'species') as $plantId) {
				$served[$plantId]++;
			}

		}

		$this->assertEquals(count($weeks), count($proposal), 'un panier par semaine');

		$far = [];

		foreach($shares as $plantId => $share) {

			if(abs($served[$plantId] - $share * count($weeks)) > 1.0 + self::EPSILON) {
				$far[] = $plantId.' servie '.$served[$plantId].' fois pour '.($share * count($weeks));
			}

		}

		$this->assertEquals([], $far, 'espèces à plus d\'un panier de leur part : '.implode(', ', $far));

	}

	/**
	 * Une espèce faible ne revient jamais la semaine qui suit son panier (r10, retour du
	 * 27/09). Le relevé de `testDebtFollowsFrequency()` : ses parts somment aux quatre lignes, et la
	 * part de chaque espèce dans le panier est donc sa part brute. Les 5 et 6 (0,4 et 0,3) sont
	 * faibles par leur part. Une semaine manque à la saison, la 20 : l'écart se compte en numéros de
	 * semaine, et la 19 et la 21 ne se suivent pas. La dette court pendant l'écart : chacune reste à
	 * deux paniers de sa part.
	 *
	 * La 3 (0,6, moyenne par sa part) l'est par la fréquence choisie, dans une seconde proposition :
	 * ce que son rythme laisse se répartit sur les autres, et la 5 y monte en moyenne.
	 */
	public function testGapLow(): void {

		[$weeks, $shares, $species] = $this->getGapSpecies();
		$source = $this->getSource($species, $weeks);

		$served = $this->getServedWeeks(CompositionLib::propose(4.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $source, [], []));

		foreach([5, 6] as $plantId) {
			$this->assertTrue(count($served[$plantId]) > 1, $plantId.', le contrôle voit plusieurs paniers');
			$this->assertEquals([], $this->getGapsUnder($served[$plantId], 2), $plantId.', semaines servies à moins de deux semaines d\'écart');
			$this->assertTrue(abs(count($served[$plantId]) - $shares[$plantId] * count($weeks)) <= 2.0, $plantId.' servie '.count($served[$plantId]).' fois pour '.($shares[$plantId] * count($weeks)));
		}

		$served = $this->getServedWeeks(CompositionLib::propose(4.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $source, [], [], 0, [3 => CompositionSpecies::LOW]));

		$this->assertTrue(count($served[3]) > 1, '3, le contrôle voit plusieurs paniers');
		$this->assertEquals([], $this->getGapsUnder($served[3], 2), '3 faible par son choix, semaines servies à moins de deux semaines d\'écart');

	}

	/**
	 * Une espèce de diversification laisse au moins deux semaines entre deux paniers (r10, « écart
	 * minimal de 3 semaines »). Les 7 à 10 (0,2 à 0,05) le sont par leur part, la 4 (0,5, moyenne
	 * par sa part) par la fréquence choisie, dans une seconde proposition comme la 3 de `testGapLow()`.
	 */
	public function testGapDiversification(): void {

		[$weeks, $shares, $species] = $this->getGapSpecies();
		$source = $this->getSource($species, $weeks);

		$served = $this->getServedWeeks(CompositionLib::propose(4.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $source, [], []));

		foreach([7, 8, 9, 10] as $plantId) {
			$this->assertTrue(count($served[$plantId]) > 1, $plantId.', le contrôle voit plusieurs paniers');
			$this->assertEquals([], $this->getGapsUnder($served[$plantId], 3), $plantId.', semaines servies à moins de trois semaines d\'écart');
			$this->assertTrue(abs(count($served[$plantId]) - $shares[$plantId] * count($weeks)) <= 2.0, $plantId.' servie '.count($served[$plantId]).' fois pour '.($shares[$plantId] * count($weeks)));
		}

		$served = $this->getServedWeeks(CompositionLib::propose(4.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $source, [], [], 0, [4 => CompositionSpecies::DIVERSIFICATION]));

		$this->assertTrue(count($served[4]) > 1, '4, le contrôle voit plusieurs paniers');
		$this->assertEquals([], $this->getGapsUnder($served[4], 3), '4 en diversification par son choix, semaines servies à moins de trois semaines d\'écart');

	}

	/**
	 * La base et la moyenne n'ont pas d'écart : les 1 et 2 (base par leur part) et les 3 et 4
	 * (moyenne) reviennent des semaines qui se suivent, comme avant r10, et une espèce faible par sa
	 * part qui choisit la base (la 5) le peut aussi.
	 */
	public function testGapBaseAndMedium(): void {

		[$weeks, , $species] = $this->getGapSpecies();

		$proposal = CompositionLib::propose(4.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $this->getSource($species, $weeks), [], [], 0, [5 => CompositionSpecies::BASE]);
		$served = $this->getServedWeeks($proposal);

		foreach([1, 2, 3, 4, 5] as $plantId) {
			$this->assertTrue(count($this->getGapsUnder($served[$plantId], 2)) > 0, $plantId.', servie deux semaines de suite au moins une fois');
		}

	}

	private function getGapSpecies(): array {

		$weeks = array_values(array_diff(range(5, 44), [20]));
		$shares = [1 => 0.9, 2 => 0.8, 3 => 0.6, 4 => 0.5, 5 => 0.4, 6 => 0.3, 7 => 0.2, 8 => 0.15, 9 => 0.1, 10 => 0.05];

		$species = [];

		foreach($shares as $plantId => $share) {
			$species[$plantId] = ['organ' => NULL, 'share' => $share];
		}

		return [$weeks, $shares, $species];

	}

	private function getServedWeeks(array $proposal): array {

		$served = [];

		foreach($proposal as $week => $basket) {

			foreach(array_column($basket['lines'], 'species') as $plantId) {
				$served[$plantId][] = $week;
			}

		}

		return $served;

	}

	/**
	 * Les paires de paniers d'une espèce écartés de moins de `$gap` numéros de semaine, écrites
	 * `semaine → semaine`.
	 */
	private function getGapsUnder(array $weeks, int $gap): array {

		$under = [];

		for($index = 1; $index < count($weeks); $index++) {

			if($weeks[$index] - $weeks[$index - 1] < $gap) {
				$under[] = $weeks[$index - 1].' → '.$weeks[$index];
			}

		}

		return $under;

	}

	/**
	 * Le bruit départage deux dettes voisines dans les deux sens selon la semaine, et n'inverse
	 * jamais deux dettes qui s'écartent de plus d'un panier. Onze saisons de 52 semaines, deux
	 * espèces à même part : à un demi-panier d'écart, la moins endettée passe parfois devant ; à
	 * un panier et un centième, jamais. Au tirage 0 comme à un tirage rejoué : le rejeu change le
	 * bruit, pas sa borne.
	 */
	public function testNoiseBound(): void {

		$getOrderByDebt = new \ReflectionMethod(CompositionLib::class, 'getOrderByDebt');
		$shares = [1 => 0.5, 2 => 0.5];

		$inverted = ['near' => 0, 'far' => 0];

		foreach([0, 1, 7] as $version) {

			foreach(range(2020, 2030) as $season) {

				foreach(range(1, 52) as $week) {

					if($getOrderByDebt->invoke(NULL, $season, $week, [1, 2], $shares, [1 => 0.5, 2 => 0.0], $version, ['plants' => []]) !== [1, 2]) {
						$inverted['near']++;
					}

					if($getOrderByDebt->invoke(NULL, $season, $week, [1, 2], $shares, [1 => 1.01, 2 => 0.0], $version, ['plants' => []]) !== [1, 2]) {
						$inverted['far']++;
					}

				}

			}

		}

		$this->assertTrue($inverted['near'] > 0, 'un demi-panier d\'écart s\'inverse parfois');
		$this->assertEquals(0, $inverted['far'], 'plus d\'un panier d\'écart ne s\'inverse jamais');

	}

	/**
	 * Le facteur sort des bornes et la règle ajoute des lignes plutôt que de l'appliquer : quatre
	 * espèces à 1 € et à part 1 en tête, huit à 3 € et à part 0 derrière. La mise au panier
	 * médiane des douze vaut 3 €, d'où 4 lignes pour 12 € ; ce sont les quatre à 1 €, qui valent
	 * 4 €, `f = 3`. La règle ajoute une ligne à 3 € (7 €, `f = 1,71`), puis une autre (10 €), et
	 * s'arrête à 6 lignes, `f = 1,2`.
	 */
	public function testFactorAddsLines(): void {

		$species = [];

		foreach(range(1, 12) as $plantId) {
			$species[$plantId] = ($plantId <= 4) ?
				['organ' => NULL, 'share' => 1.0, 'price' => 1.0] :
				['organ' => NULL, 'share' => 0.0, 'price' => 3.0];
		}

		$proposal = CompositionLib::propose(12.0, 5, self::LINES_MIN, self::LINES_MAX, [20], NULL, self::SEASON, $this->getSource($species, [20]), [], []);

		$plants = array_column($proposal[20]['lines'], 'species');
		$plantsFirst = array_slice($plants, 0, 4);

		sort($plantsFirst);

		$this->assertEquals(6, count($plants), 'lignes ajoutées');
		$this->assertEquals([1, 2, 3, 4], $plantsFirst, 'les quatre à part 1 d\'abord');
		$this->assertEquals([1.2], array_values(array_unique(array_column($proposal[20]['lines'], 'quantity'))), 'quantité à 12 / 10');

	}

	/**
	 * Le pendant : quatre espèces à 3 € et six à 1 €, toutes prises. La mise au panier médiane
	 * vaut 1 €, d'où 12 lignes ramenées à 10 ; elles valent 18 €, `f = 0,67`, et la règle retire
	 * une ligne. Laquelle dépend du bruit, pas ce qui suit : 9 lignes, à 15 ou 17 €, et un facteur
	 * rentré dans ses bornes qui s'applique tel quel.
	 */
	public function testFactorRemovesLines(): void {

		$species = [];

		foreach(range(1, 10) as $plantId) {
			$species[$plantId] = ['organ' => NULL, 'share' => 1.0, 'price' => ($plantId <= 4) ? 3.0 : 1.0];
		}

		$proposal = CompositionLib::propose(12.0, 5, self::LINES_MIN, self::LINES_MAX, [20], NULL, self::SEASON, $this->getSource($species, [20]), [], []);

		$value = array_sum(array_column($proposal[20]['lines'], 'unitPrice'));

		$this->assertEquals(9, count($proposal[20]['lines']), 'ligne retirée');
		$this->assertTrue(in_array($value, [15.0, 17.0], TRUE), 'valeur après retrait');
		$this->assertEquals([round(12 / $value, 1)], array_values(array_unique(array_column($proposal[20]['lines'], 'quantity'))), 'quantité à 12 / valeur, arrondie au dixième');

	}

	/**
	 * La relance de l'étape 8 : le facteur est dans ses bornes mais le panier arrondi manque le prix
	 * de plus de 5 %, et une ligne de moins le rapproche. Cinq espèces à 3 € et à part 1 en tête,
	 * sept à 2,60 € et à part 0 derrière, toutes à l'unité entière. La mise au panier médiane vaut
	 * 2,60 €, d'où 5 lignes pour 13 € : les cinq à 3 €, 15 €, `f = 0,87` dans ses bornes, et les
	 * quantités arrondies à 1 laissent le panier à 15 € (+15 %). Quatre lignes valent 12 € (−8 %),
	 * six en valent 17,60 € (+35 %) : le panier en garde quatre.
	 */
	public function testRelaunchLinesOnRoundingGap(): void {

		$species = [];

		foreach(range(1, 12) as $plantId) {
			$species[$plantId] = ($plantId <= 5) ?
				['organ' => NULL, 'share' => 1.0, 'price' => 3.0, 'step' => 1.0] :
				['organ' => NULL, 'share' => 0.0, 'price' => 2.6, 'step' => 1.0];
		}

		$proposal = CompositionLib::propose(13.0, 5, self::LINES_MIN, self::LINES_MAX, [20], NULL, self::SEASON, $this->getSource($species, [20]), [], []);

		$lines = $proposal[20]['lines'];

		$this->assertEquals(4, count($lines), 'une ligne de moins');
		$this->assertEquals([3.0], array_values(array_unique(array_column($lines, 'unitPrice'))), 'les espèces à part 1');
		$this->assertEquals([1.0], array_values(array_unique(array_column($lines, 'quantity'))), 'facteur 13 / 12 arrondi à l\'unité');

	}

	/**
	 * Le pendant : la relance ne garde un autre nombre de lignes que s'il tombe plus près. Dix
	 * espèces à 2,20 € à l'unité entière pour 10 € : 5 lignes, 11 € (+10 %) ; quatre en valent
	 * 8,80 € (−12 %), six 13,20 € (+32 %). Le panier reste à cinq lignes, à 10 % du prix, et
	 * l'écran le dira.
	 */
	public function testRelaunchKeepsClosest(): void {

		$species = [];

		foreach(range(1, 10) as $plantId) {
			$species[$plantId] = ['organ' => NULL, 'share' => 0.5, 'price' => 2.2, 'step' => 1.0];
		}

		$proposal = CompositionLib::propose(10.0, 5, self::LINES_MIN, self::LINES_MAX, [20], NULL, self::SEASON, $this->getSource($species, [20]), [], []);

		$this->assertEquals(5, count($proposal[20]['lines']), 'cinq lignes gardées');
		$this->assertEquals([1.0], array_values(array_unique(array_column($proposal[20]['lines'], 'quantity'))), 'quantités à l\'unité');

	}

	/**
	 * L'écart se juge sur les valeurs affichées, au centime, par la règle comme par l'écran : 16,80 €
	 * pour 16 € visés est à 5 % exactement, donc pas au-delà, alors qu'en flottants
	 * `abs(16.8 - 16) / 16` rend 0,05000000000000004. Même bord, au centime, aux deux bornes de
	 * l'écart d'une formule, 2 et 20 %, et à 10 %.
	 */
	public function testGapOnDisplayedValues(): void {

		foreach([
			[5, 16.80, 16.81, 15.20, 15.19],
			[2, 16.32, 16.33, 15.68, 15.67],
			[10, 17.60, 17.61, 14.40, 14.39],
			[20, 19.20, 19.21, 12.80, 12.79],
		] as [$gap, $above, $beyond, $below, $under]) {

			$this->assertEquals(FALSE, CompositionLib::isGap(16.0, $above, $gap), $gap.' % exactement au-dessus');
			$this->assertEquals(TRUE, CompositionLib::isGap(16.0, $beyond, $gap), $gap.' %, un centime au-delà');
			$this->assertEquals(FALSE, CompositionLib::isGap(16.0, $below, $gap), $gap.' % exactement au-dessous');
			$this->assertEquals(TRUE, CompositionLib::isGap(16.0, $under, $gap), $gap.' %, un centime en deçà');

		}

		$this->assertEquals(0.8, CompositionLib::getGapAmount(16.0, 5), '5 % de 16 €');
		$this->assertEquals(0.33, CompositionLib::getGapAmount(16.5, 2), '2 % de 16,50 €, au centime');

		$this->assertEquals(1.33, CompositionLib::getLineValue(0.4, 3.3333), 'une ligne au centime');
		$this->assertEquals(NULL, CompositionLib::getLineValue(0.4, NULL), 'une ligne sans prix');
		$this->assertEquals(16.80, CompositionLib::getWeekValue([1.33, NULL, 15.47]), 'la semaine, la ligne sans prix comptant pour zéro');

	}

	/**
	 * Le décompte des semaines au-delà de l'écart sur le relevé réel de 2026, à 12, 16 et 20 €. Avant
	 * l'ajustement au pas (r23), il rendait à 5 % 5, 4 et 5 semaines ; l'ajustement les ramène toutes
	 * dans l'écart, à 2, 5 et 10 % : le relevé de la communauté a assez d'espèces au pas fin pour
	 * qu'une ligne trouve toujours le pas qui manque.
	 *
	 * Le pendant prouve que le décompte sait trouver et que l'ajustement vise l'écart de la formule,
	 * pas un écart plus serré : composées à 5 %, des semaines restent au-delà de 2 % ; composées à
	 * 10 %, des semaines restent au-delà de 5 %.
	 *
	 * La fréquence est celle des paniers partagés seuls : la saison des ventes, que le cron republie
	 * chaque nuit, recompose la saison et déplacerait ces décomptes sans que l'écart y soit pour rien.
	 */
	public function testGapCountOnSeason(): void {

		$source = CompositionLib::getSource();
		$source['frequency'] = \aggregate\CompositionSourceAiLib::getCompositionFrequency();
		$weeks = CompositionLib::getSeasonWeeks();

		foreach([12.0, 16.0, 20.0] as $price) {

			$proposals = [];

			foreach([2, 5, 10] as $gap) {
				$proposals[$gap] = CompositionLib::propose($price, $gap, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $source, [], []);
			}

			if($proposals[5] === []) {
				$this->assertFail('le relevé de dev ne compose aucun panier : le contrôle ne verrait rien');
				return;
			}

			foreach($proposals as $gap => $proposal) {
				$this->assertEquals(0, $this->countOver($proposal, $price, $gap), $price.' € à '.$gap.' %, toutes les semaines dans l\'écart');
			}

			$this->assertTrue($this->countOver($proposals[5], $price, 2) > 0, $price.' € : composée à 5 %, des semaines au-delà de 2 %');
			$this->assertTrue($this->countOver($proposals[10], $price, 5) > 0, $price.' € : composée à 10 %, des semaines au-delà de 5 %');

		}

	}

	private function countOver(array $proposal, float $price, int $gap): int {
		return count(array_filter($proposal, fn(array $basket) => CompositionLib::isGap($price, $this->getValue($basket), $gap)));
	}

	/**
	 * Les quantités d'un panier proposé, par espèce, dans l'ordre des ids.
	 */
	private function getQuantities(array $basket): array {

		$quantities = array_column($basket['lines'], 'quantity', 'species');
		ksort($quantities);

		return $quantities;

	}

	/**
	 * La valeur d'un panier proposé, comptée comme l'écran la compte.
	 */
	private function getValue(array $basket): float {
		return CompositionLib::getWeekValue(array_map(fn(array $line) => CompositionLib::getLineValue($line['quantity'], $line['unitPrice']), $basket['lines']));
	}

	/**
	 * Les bornes réglées sur la formule (r11) remplacent les bornes fixes de la règle, 4 et 10. Seize
	 * espèces à 1 € et à part 1 : la mise au panier médiane vaut 1 €.
	 *
	 * - À 16 €, le nombre de lignes de départ serait 16 : il s'arrête au plafond, et le facteur hors
	 *   bornes ne le relance pas au-delà. Le plafond par défaut, 8, est la demande du 27/09.
	 * - À 2 €, il serait 2 : il remonte au plancher, et le facteur hors bornes ne le fait pas
	 *   redescendre en dessous.
	 *
	 * Les mêmes semaines sous les anciennes bornes (4 et 10) prouvent que ce sont bien celles passées
	 * qui s'appliquent, et non une constante restée dans la règle.
	 */
	public function testLinesBounds(): void {

		$species = [];

		foreach(range(1, 16) as $plantId) {
			$species[$plantId] = ['organ' => NULL, 'share' => 1.0];
		}

		$source = $this->getSource($species, [20]);

		$count = fn(array $source, float $price, int $linesMin, int $linesMax) => count(CompositionLib::propose($price, 5, $linesMin, $linesMax, [20], NULL, self::SEASON, $source, [], [])[20]['lines']);

		$this->assertEquals(8, $count($source, 16.0, 4, 8), 'plafond par défaut');
		$this->assertEquals(10, $count($source, 16.0, self::LINES_MIN, self::LINES_MAX), 'plafond d\'avant r11');
		$this->assertEquals(12, $count($source, 16.0, 4, 12), 'plafond le plus haut');
		$this->assertEquals(5, $count($source, 16.0, 1, 5), 'plafond bas');

		$this->assertEquals(4, $count($source, 2.0, 4, 8), 'plancher par défaut');
		$this->assertEquals(6, $count($source, 2.0, 6, 8), 'plancher relevé');
		$this->assertEquals(2, $count($source, 2.0, 1, 8), 'plancher abaissé, le départ tel quel');

		$this->assertEquals(7, $count($source, 16.0, 7, 7), 'bornes confondues');
		$this->assertEquals(7, $count($source, 2.0, 7, 7), 'bornes confondues, par le bas');

		// Une médiane nulle ne dit aucun ordre de grandeur : le départ prend le plafond de la formule.
		// À 3 €, un départ tiré de la médiane de 1 € en aurait pris 3.
		$speciesFree = array_map(fn(array $properties) => ['price' => 0.0] + $properties, $species);
		$sourceFree = $this->getSource($speciesFree, [20]);

		$this->assertEquals(3, $count($source, 3.0, 1, 9), 'médiane de 1 €, trois lignes');
		$this->assertEquals(6, $count($sourceFree, 3.0, 1, 6), 'médiane nulle, plafond de la formule');
		$this->assertEquals(9, $count($sourceFree, 3.0, 1, 9), 'médiane nulle, autre plafond');

		// La relance sur l'écart arrondi reste aussi dans les bornes : le cas de
		// `testRelaunchLinesOnRoundingGap()`, qui garde quatre lignes sur cinq, les garde toutes
		// sous un plancher à 5.
		$speciesRounding = [];

		foreach(range(1, 12) as $plantId) {
			$speciesRounding[$plantId] = ($plantId <= 5) ?
				['organ' => NULL, 'share' => 1.0, 'price' => 3.0, 'step' => 1.0] :
				['organ' => NULL, 'share' => 0.0, 'price' => 2.6, 'step' => 1.0];
		}

		$sourceRounding = $this->getSource($speciesRounding, [20]);

		$this->assertEquals(4, $count($sourceRounding, 13.0, 4, 8), 'relance vers une ligne de moins');
		$this->assertEquals(5, $count($sourceRounding, 13.0, 5, 8), 'relance retenue par le plancher');

		// La part que reçoit une fréquence choisie se calcule sur le panier de départ, lui aussi borné
		// par la formule : une espèce en moyenne (0,5) parmi quinze autres à part 1, sur huit puis
		// douze lignes.
		foreach([8, 12] as $linesMax) {

			$sourceFrequency = CompositionLib::applyFrequencies(16.0, 4, $linesMax, [20], self::SEASON, NULL, $source, [], [1 => CompositionSpecies::MEDIUM]);

			$this->assertTrue(abs($sourceFrequency['frequency']['byWeek'][20][1] - 0.5 * 15 / ($linesMax - 0.5)) < self::EPSILON, 'part de la fréquence choisie sur '.$linesMax.' lignes');

		}

	}

	/**
	 * La formule refuse un minimum au-dessus du maximum, par l'une ou l'autre borne, en création
	 * comme en modification, et accepte des bornes confondues. Le schéma refuse ce qui sort de 1 à 12.
	 */
	public function testLinesBoundsBuild(): void {

		$build = function(array $input, CompositionFormula $e = new CompositionFormula()): array {

			$fw = new \FailWatch();

			$e->build(array_keys($input), $input, new \PropertyList($e->empty() ? 'create' : 'update'));

			$errors = array_map(fn($fail) => $fail[1], $fw->get());

			return $errors;

		};

		$this->assertEquals([], $build(['linesMin' => 4, 'linesMax' => 8]), 'défauts');
		$this->assertEquals([], $build(['linesMin' => 6, 'linesMax' => 6]), 'bornes confondues');
		$this->assertEquals(['CompositionFormula::linesMax.belowMin'], $build(['linesMin' => 9, 'linesMax' => 8]), 'minimum au-dessus du maximum, refusé sur le maximum construit après lui');
		$this->assertEquals(['CompositionFormula::linesMin.check'], $build(['linesMin' => 0, 'linesMax' => 8]), 'minimum hors bornes, le maximum sans rien à comparer');
		$this->assertEquals(['CompositionFormula::linesMin.check', 'CompositionFormula::linesMax.check'], $build(['linesMin' => 0, 'linesMax' => 13]), 'hors de 1 à 12');

		// En modification, une seule borne est postée : l'autre se lit sur la formule. Un build réussi
		// pose sa valeur sur la formule, d'où une formule neuve à chaque cas.
		$eCompositionFormula = fn() => new CompositionFormula(['id' => 1, 'base' => new CompositionFormula(), 'linesMin' => 4, 'linesMax' => 8]);

		$this->assertEquals([], $build(['linesMax' => 5], $eCompositionFormula()), 'maximum abaissé au-dessus du minimum');
		$this->assertEquals([], $build(['linesMax' => 4], $eCompositionFormula()), 'maximum abaissé au minimum');
		$this->assertEquals(['CompositionFormula::linesMax.belowMin'], $build(['linesMax' => 3], $eCompositionFormula()), 'maximum sous le minimum');
		$this->assertEquals(['CompositionFormula::linesMin.aboveMax'], $build(['linesMin' => 9], $eCompositionFormula()), 'minimum au-dessus du maximum');
		$this->assertEquals([], $build(['linesMin' => 8], $eCompositionFormula()), 'minimum relevé au maximum');

		foreach(['linesMin.check', 'linesMin.aboveMax', 'linesMax.check', 'linesMax.belowMin'] as $fail) {
			$this->assertDiffer(NULL, AlertUi::getError('CompositionFormula::'.$fail), 'le refus '.$fail.' a son message');
		}

	}

	/**
	 * Quand ajouter une ligne ne change plus rien (quatre candidates seulement, toutes prises), le
	 * facteur reste hors bornes : il est appliqué borné à 1,4. L'ajustement au pas (r23) monte ensuite
	 * chaque ligne jusqu'à son plafond, 2 fois sa quantité de référence, et pas au-delà : le panier
	 * vaut 8 € pour 40 € visés, et l'écart au prix subsiste.
	 */
	public function testFactorStaysBounded(): void {

		$species = [];

		foreach(range(1, 4) as $plantId) {
			$species[$plantId] = ['organ' => NULL, 'share' => 1 - $plantId / 100];
		}

		$proposal = CompositionLib::propose(40.0, 5, self::LINES_MIN, self::LINES_MAX, [20], NULL, self::SEASON, $this->getSource($species, [20]), [], []);

		$this->assertEquals(4, count($proposal[20]['lines']), 'lignes');
		$this->assertEquals([2.0], array_values(array_unique(array_column($proposal[20]['lines'], 'quantity'))), 'facteur borné à 1,4, puis chaque ligne à son plafond');
		$this->assertEquals(TRUE, CompositionLib::isGap(40.0, $this->getValue($proposal[20]), 5), 'l\'écart au prix subsiste');

	}

	/**
	 * Un panier dont aucune ligne n'a de prix employable garde `f = 1` : aucune espèce n'est
	 * ajoutée, et les quantités restent celles de référence. Douze candidates au prix trop
	 * dispersé, un prix visé de 5 € pour 1 € de médiane : cinq lignes, pas une de plus.
	 */
	public function testFactorWithoutPrice(): void {

		$species = [];

		foreach(range(1, 12) as $plantId) {
			$species[$plantId] = ['organ' => NULL, 'share' => 1 - $plantId / 100, 'low' => 0.5, 'high' => 1.5];
		}

		$proposal = CompositionLib::propose(5.0, 5, self::LINES_MIN, self::LINES_MAX, [20], NULL, self::SEASON, $this->getSource($species, [20]), [], []);

		$this->assertEquals(5, count($proposal[20]['lines']), 'aucune espèce ajoutée');
		$this->assertEquals([NULL], array_values(array_unique(array_column($proposal[20]['lines'], 'unitPrice'))), 'aucun prix employé');
		$this->assertEquals([1.0], array_values(array_unique(array_column($proposal[20]['lines'], 'quantity'))), 'quantités de référence');

	}

	/**
	 * L'arrondi au pas de l'unité laisse une unité entière de quantité 1 à 1 pour `f = 1,3`, et le
	 * panier à 4 € pour 5,20 € visés. L'ajustement au pas (r23) monte une ligne d'une unité : 5 €, dans
	 * l'écart de 5 %, et il s'arrête là sans viser le prix exact.
	 */
	public function testRoundingAdjusted(): void {

		$species = [];

		foreach(range(1, 4) as $plantId) {
			$species[$plantId] = ['organ' => NULL, 'share' => 1 - $plantId / 100, 'step' => 1.0];
		}

		$proposal = CompositionLib::propose(5.2, 5, self::LINES_MIN, self::LINES_MAX, [20], NULL, self::SEASON, $this->getSource($species, [20]), [], []);

		$quantities = array_column($proposal[20]['lines'], 'quantity');
		sort($quantities);

		$this->assertEquals([1.0, 1.0, 1.0, 2.0], $quantities, 'arrondi à l\'unité, puis une ligne d\'un pas');
		$this->assertEquals(5.0, $this->getValue($proposal[20]), 'dans l\'écart, sans viser le prix exact');

	}

	/**
	 * L'ajustement au pas (r23) prend le pas qui rapproche le plus, pas le premier qui rapproche.
	 * Trois espèces à 1 € et une à 3 €, toutes à l'unité entière, pour 7,90 € : le facteur, 1,32,
	 * laisse chaque ligne à 1, soit 6 €. Le tirage met la ligne à 3 € en tête du panier, et une unité
	 * de plus sur elle rapproche déjà (9 €, à 1,10 €) ; une unité de plus sur une ligne à 1 € rapproche
	 * davantage (7 €, à 0,90 €), puis une seconde fait entrer le panier dans l'écart, à 8 €. Prendre le
	 * premier pas qui rapproche laisserait le panier à 9 €, la ligne à 3 € à 2, sans pas qui rapproche
	 * encore.
	 *
	 * Le pendant, le minimum : pour 2 €, les mêmes lignes à 1 valent 6 € et aucune ne peut descendre
	 * sous son minimum d'une unité. Le panier reste à 6 €, au-delà de l'écart.
	 */
	public function testPriceSteps(): void {

		$species = [];

		foreach(range(1, 4) as $plantId) {
			$species[$plantId] = ($plantId <= 3) ?
				['organ' => NULL, 'share' => 1.0, 'price' => 1.0, 'step' => 1.0] :
				['organ' => NULL, 'share' => 0.0, 'price' => 3.0, 'step' => 1.0];
		}

		$source = $this->getSource($species, [20]);

		$proposal = CompositionLib::propose(7.9, 5, self::LINES_MIN, self::LINES_MAX, [20], NULL, self::SEASON, $source, [], []);

		$this->assertEquals(4, $proposal[20]['lines'][0]['species'], 'la ligne à 3 € en tête du panier');

		$quantities = array_column($proposal[20]['lines'], 'quantity', 'species');
		$quantitiesCheap = [$quantities[1], $quantities[2], $quantities[3]];
		sort($quantitiesCheap);

		$this->assertEquals(1.0, $quantities[4], 'la ligne à 3 € ne bouge pas');
		$this->assertEquals([1.0, 2.0, 2.0], $quantitiesCheap, 'deux lignes à 1 € montent d\'une unité');
		$this->assertEquals(8.0, $this->getValue($proposal[20]), 'dans l\'écart');

		$proposal = CompositionLib::propose(2.0, 5, self::LINES_MIN, self::LINES_MAX, [20], NULL, self::SEASON, $source, [], []);

		$this->assertEquals([1.0], array_values(array_unique(array_column($proposal[20]['lines'], 'quantity'))), 'aucune ligne sous son minimum');
		$this->assertEquals(6.0, $this->getValue($proposal[20]), 'le panier reste au-delà de l\'écart');

	}

	/**
	 * Le cas que l'architecte a mesuré sur un plan réel (r23), réduit à ses deux sortes de semaines,
	 * à deux prix.
	 *
	 * - Les semaines 10 à 13 sont une soudure : le poireau, 3 € le kilo au pas de 0,2 kg, y est seul.
	 *   Le facteur borné le laisse à 1,4 kg, 4,20 €, et l'ajustement ne le monte qu'à 2 fois sa
	 *   référence, 2 kg, 6 € : la semaine reste hors de l'écart, et c'est au plan de culture d'y
	 *   répondre.
	 * - Les semaines 20 à 23 ont cinq espèces à 3 €, toutes au panier : un kilo de carottes au pas de
	 *   0,2 kg, et quatre bottes qui ne se coupent pas. Le facteur, 0,93 à 14 € et 1,07 à 16 €, laisse
	 *   chaque ligne à 1 et le panier à 15 €, hors des 5 % des deux côtés. Un pas de carottes, 0,8 ou
	 *   1,2 kg, le ramène à 14,40 € et 15,60 €.
	 *
	 * Sur le plan de l'architecte, les mêmes causes faisaient 7 semaines rattrapées sur 10 à 18 € et 8
	 * sur 9 à 12 €, sans nouvelle semaine hors cible (`.claude/state/paniers/r23-cible/architecte.md`).
	 */
	public function testPriceStepsOnSeason(): void {

		$species = [
			1 => ['organ' => NULL, 'share' => 1.0, 'price' => 3.0, 'step' => 0.2],
			2 => ['organ' => NULL, 'share' => 1.0, 'price' => 3.0, 'step' => 0.2],
		];

		foreach(range(3, 6) as $plantId) {
			$species[$plantId] = ['organ' => NULL, 'share' => 0.0, 'price' => 3.0, 'step' => 1.0];
		}

		$weeksWinter = [10, 11, 12, 13];
		$weeksSummer = [20, 21, 22, 23];
		$weeks = array_merge($weeksWinter, $weeksSummer);

		$source = $this->getSource($species, $weeks);

		foreach($weeksWinter as $week) {
			$source['availability'][$week] = [1 => 10];
		}

		foreach($weeksSummer as $week) {
			unset($source['availability'][$week][1]);
		}

		foreach([14.0 => 0.8, 16.0 => 1.2] as $price => $carrots) {

			$price = (float)$price;

			$proposal = CompositionLib::propose($price, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $source, [], []);

			$this->assertEquals($weeks, array_keys($proposal), $price.' €, toutes les semaines ont un panier');
			$this->assertEquals($weeksWinter, array_keys(array_filter($proposal, fn(array $basket) => CompositionLib::isGap($price, $this->getValue($basket), 5))), $price.' €, seule la soudure reste hors de l\'écart');

			foreach($weeksWinter as $week) {
				$this->assertEquals([1 => 2.0], array_column($proposal[$week]['lines'], 'quantity', 'species'), $price.' €, semaine '.$week.', le poireau à 2 fois sa référence');
			}

			foreach($weeksSummer as $week) {
				$this->assertEquals([2 => $carrots, 3 => 1.0, 4 => 1.0, 5 => 1.0, 6 => 1.0], $this->getQuantities($proposal[$week]), $price.' €, semaine '.$week.', un pas de carottes');
			}

		}

	}

	/**
	 * Le stock du plan de culture passe devant la cible (lot r23, tranché le 27/09) :
	 * une ligne ne dépasse pas ce qu'il donne à chaque panier, arrondi au pas inférieur, et une
	 * espèce dont il ne donne pas le minimum n'est pas candidate. Dix paniers, un kilo l'unité.
	 *
	 * - 1, en kilos au pas de 0,2 : 7 kg récoltés en semaine 20 et gardés une semaine de plus, donc
	 *   0,6 kg au panier en 20 (0,7 au pas inférieur), et le kilo restant ne fait plus le minimum
	 *   en 21 ;
	 * - 2 : une autre taille prend toute sa récolte de la semaine 20, et sert en 21 une ligne qui ne
	 *   se pèse pas, ce qui lève le plafond d'une semaine où un kilo ne ferait pas le minimum ;
	 * - 3 : 15 pièces récoltées en 20 et gardées quatre semaines, une au panier en 20, et les 5
	 *   restantes ne font plus une pièce au panier ;
	 * - 4 : de quoi servir toute la saison, jusqu'à 2 fois sa référence ;
	 * - 5 : rien de récolté, sauf en 22, une récolte qui ne se chiffre pas et ne plafonne donc rien ;
	 * - 6 : récoltée seulement en semaine 30, jamais servie avant. Absente du plan de culture, ou
	 *   cultivée sans semaine de récolte, elle ne se plafonne pas et se sert de nouveau (arbitrage
	 *   r27 du 28/09).
	 *
	 * Les semaines tombent sous les 4 lignes et loin de 14 € : le stock passe devant la cible.
	 */
	public function testStockCaps(): void {

		$species = [
			1 => ['organ' => NULL, 'share' => 1.0, 'price' => 3.0, 'step' => 0.2],
		];

		foreach(range(2, 6) as $plantId) {
			$species[$plantId] = ['organ' => NULL, 'share' => 0.0, 'price' => 3.0, 'step' => 1.0];
		}

		$weeks = [20, 21, 22, 23];

		$source = $this->getSource($species, $weeks);

		foreach(array_keys($species) as $plantId) {
			$source['storage'][$plantId] = ['weeks' => 0];
		}

		$source['storage'][1]['weeks'] = 1;
		$source['storage'][3]['weeks'] = 4;

		$plenty = array_fill_keys($weeks, 1000.0);

		$stock = [
			'planned' => [
				1 => $this->getPlan([20 => 7.0]),
				2 => $this->getPlan([21 => 1.0] + $plenty),
				3 => $this->getPlan([20 => 15.0]),
				4 => $this->getPlan($plenty),
				5 => $this->getPlan([], [22]),
				6 => $this->getPlan([30 => 100.0]),
			],
			'shared' => [],
			'needs' => [
				'needs' => [2 => [20 => 1000.0]],
				'unknown' => [2 => [21 => TRUE]],
			],
			'kilos' => array_fill_keys(range(1, 6), 1.0),
			'baskets' => 10,
		];

		$proposalFree = CompositionLib::propose(14.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $source, [], []);
		$proposal = CompositionLib::propose(14.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $source, [], [], stock: $stock);

		$this->assertEquals([20 => 0.8, 21 => 0.8, 22 => 0.8, 23 => 0.8], array_map(fn(array $basket) => $this->getQuantities($basket)[1], $proposalFree), 'sans stock, 1 servie toutes les semaines');
		$this->assertEquals([20, 22, 23], array_keys(array_filter($proposalFree, fn(array $basket) => isset($this->getQuantities($basket)[6]))), 'sans stock, 6 servie');

		$this->assertEquals([
			20 => [1 => 0.6, 3 => 1.0, 4 => 2.0],
			21 => [2 => 2.0, 4 => 2.0],
			22 => [2 => 1.0, 4 => 2.0, 5 => 2.0],
			23 => [2 => 2.0, 4 => 2.0],
		], array_map(fn(array $basket) => $this->getQuantities($basket), $proposal), 'le stock plafonne et écarte');

		$served = fn(array $stock) => array_keys(array_filter(CompositionLib::propose(14.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $source, [], [], stock: $stock), fn(array $basket) => isset($this->getQuantities($basket)[6])));

		$stockUndated = $stock;
		$stockUndated['planned'][6] = $this->getPlan([]);

		$this->assertTrue($served($stockUndated) !== [], 'cultivée sans semaine de récolte, 6 servie');

		$stockUncultivated = $stock;
		unset($stockUncultivated['planned'][6], $stockUncultivated['kilos'][6]);

		$this->assertTrue($served($stockUncultivated) !== [], 'absente du plan de culture, 6 servie');

	}

	/**
	 * Le minimum de la communauté borne aussi le stock (lot r25) : un reste de poireau qui ne donne
	 * que 0,1 kg à chaque panier le servait au minimum d'un pas, et ne le sert plus sous un minimum de
	 * 0,5 kg. Le minimum réglé par la structure passe devant celui de la communauté, et le reste se
	 * sert de nouveau à 0,1 kg. Dix paniers, un kilo de poireau récolté la semaine 20, rien de gardé.
	 */
	public function testStockCapsMinimum(): void {

		$weeks = [20];

		$species = [
			1 => ['organ' => NULL, 'share' => 1.0, 'price' => 3.0, 'step' => 0.1],
		];

		foreach(range(2, 5) as $plantId) {
			$species[$plantId] = ['organ' => NULL, 'share' => 0.5, 'price' => 3.0, 'step' => 1.0];
		}

		// Les autres espèces ont de quoi servir : sans plan de culture, une espèce n'a aucun stock.
		$stock = [
			'planned' => [
				1 => $this->getPlan([20 => 1.0]),
			] + array_fill_keys(range(2, 5), $this->getPlan([20 => 1000.0])),
			'shared' => [],
			'needs' => ['needs' => [], 'unknown' => []],
			'kilos' => array_fill_keys(range(1, 6), 1.0),
			'baskets' => 10,
		];

		$leek = function(?float $minimum, array $steps) use($species, $weeks, $stock): ?float {

			$species[1]['minimum'] = $minimum;

			$source = $this->getSource($species, $weeks);
			$source['storage'] = array_fill_keys(array_keys($species), ['weeks' => 0]);

			$proposal = CompositionLib::propose(14.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $source, [], $steps, stock: $stock);

			return $this->getQuantities($proposal[20])[1] ?? NULL;

		};

		$this->assertEquals(0.1, $leek(NULL, []), 'sans minimum de la communauté, le reste servi à un pas');
		$this->assertEquals(NULL, $leek(0.5, []), 'sous le minimum de la communauté, le reste n\'est pas servi');
		$this->assertEquals(0.1, $leek(0.5, [1 => ['step' => NULL, 'minimum' => 0.1]]), 'le minimum réglé par la structure passe devant');

	}

	/**
	 * Le minimum qui s'applique à une espèce que la structure n'a pas réglée (lot r25) : celui de la
	 * communauté arrondi au pas effectif, au plus proche et jamais sous un pas, et un pas quand la
	 * communauté n'en donne pas, sous le seuil de contributeurs ou dans une autre unité.
	 */
	public function testMinimumCommunity(): void {

		$this->assertEquals(0.5, CompositionLib::getMinimumCommunity(0.5, 0.1), 'déjà au pas');
		$this->assertEquals(0.6, CompositionLib::getMinimumCommunity(0.5, 0.2), 'au pas du dessus quand il est au milieu');
		$this->assertEquals(0.4, CompositionLib::getMinimumCommunity(0.35, 0.2), '0,35 au pas de 0,2, arrondi à 0,4');
		$this->assertEquals(0.4, CompositionLib::getMinimumCommunity(0.3, 0.2), '0,3 au pas de 0,2, au pas du dessus malgré le reliquat binaire de 0,3 / 0,2');
		$this->assertEquals(0.3, CompositionLib::getMinimumCommunity(0.315, 0.1), '0,315 au pas de 0,1');
		$this->assertEquals(0.1, CompositionLib::getMinimumCommunity(0.05, 0.1), 'jamais sous un pas');
		$this->assertEquals(0.2, CompositionLib::getMinimumCommunity(0.05, 0.2), 'jamais sous un pas, même arrondi à zéro');
		$this->assertEquals(0.2, CompositionLib::getMinimumCommunity(NULL, 0.2), 'sans minimum de la communauté, un pas');

		$source = $this->getSource([
			1 => ['organ' => NULL, 'share' => 1.0, 'step' => 0.2, 'minimum' => 0.5],
			2 => ['organ' => NULL, 'share' => 1.0, 'step' => 0.1],
		], [20]);

		$this->assertEquals(['step' => 0.2, 'minimum' => 0.6], CompositionLib::getStep(1, $source, []), 'le minimum de la communauté au pas de l\'unité');
		$this->assertEquals(['step' => 0.5, 'minimum' => 0.5], CompositionLib::getStep(1, $source, [1 => ['step' => 0.5, 'minimum' => NULL]]), 'au pas réglé par la structure');
		$this->assertEquals(['step' => 0.2, 'minimum' => 1.2], CompositionLib::getStep(1, $source, [1 => ['step' => NULL, 'minimum' => 1.2]]), 'le minimum réglé passe devant');
		$this->assertEquals(['step' => 0.1, 'minimum' => 0.1], CompositionLib::getStep(2, $source, []), 'sous le seuil, un pas');

	}

	/**
	 * Douze espèces de même part sur dix semaines : ce qui les départage est la dette et le bruit,
	 * jamais l'ordre de lecture. Le même relevé lu à l'envers rend la même proposition.
	 */
	public function testReadingOrderIgnored(): void {

		$weeks = range(20, 29);

		$species = [];

		foreach(range(1, 12) as $plantId) {
			$species[$plantId] = ['organ' => NULL, 'share' => 0.5];
		}

		$proposal = CompositionLib::propose(4.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $this->getSource($species, $weeks), [], []);

		$sourceReversed = $this->reverse($this->getSource($species, $weeks));

		$this->assertEquals([12, 11, 10], array_slice(array_keys($sourceReversed['availability'][20]), 0, 3), 'relevé lu à l\'envers');
		$this->assertEquals($proposal, CompositionLib::propose(4.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $sourceReversed, [], []), 'même proposition');

	}

	/**
	 * Le §6.5 sur le relevé réel : revenir de 16 à 14 puis à 16 € rend le même panier, et le
	 * rend encore quand tout le relevé, disponibilités, fréquences, prix, unités et organes, est
	 * lu dans l'ordre inverse.
	 */
	public function testSamePriceSameBasket(): void {

		$source = CompositionLib::getSource();
		$weeks = CompositionLib::getSeasonWeeks();

		$proposal16 = CompositionLib::propose(16.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $source, [], []);
		$proposal14 = CompositionLib::propose(14.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $source, [], []);

		if($proposal16 === []) {
			$this->assertFail('le relevé de dev ne compose aucun panier : le contrôle ne verrait rien');
			return;
		}

		$this->assertDiffer($proposal16, $proposal14, 'deux prix, deux propositions');
		$this->assertEquals($proposal16, CompositionLib::propose(16.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $source, [], []), 'retour à 16 €');
		$this->assertEquals($proposal16, CompositionLib::propose(16.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $this->reverse($source), [], []), 'relevé lu à l\'envers');

	}

	/**
	 * Aucune période, sur le relevé réel à 12, 16 et 20 € : à aucun décalage de 4 à 12 semaines
	 * deux paniers ne se ressemblent plus qu'à trois semaines d'écart (à `PERIOD_PEAK` près), et à aucun décalage le
	 * recouvrement (Jaccard) ne dépasse de plus de `PERIOD_GAP` le plancher, celui de deux paniers
	 * tirés indépendamment avec les parts attendues. C'est le défaut de la première règle, qui
	 * revenait aux mêmes espèces toutes les trois semaines : 0,59 au décalage 3 contre 0,12 au
	 * décalage 1, à 16 € sur dix saisons, pour un plancher à 0,39. Elle échoue ici aux trois prix.
	 *
	 * L'écart ne se borne que vers le haut. Sous le plancher, deux paniers se ressemblent moins
	 * que deux tirages au hasard, et c'est l'effet voulu de la dette : une espèce qui vient d'être
	 * servie recule, donc le décalage 1 est le plus bas relativement au plancher (0,26 contre
	 * 0,31 à 12 €, 0,37 contre 0,41 à 16 €). Le borner des deux côtés reviendrait à exiger que la
	 * règle ressemble au hasard, ce qu'elle n'a pas pour but.
	 *
	 * La référence des pics est le décalage 3, le plus long écart minimal (`CompositionLib::FREQUENCY_GAPS`,
	 * r10) : une espèce faible ne revient pas à une semaine, une espèce de diversification ni à une
	 * ni à deux, et les décalages 1 et 2 sont donc bas par construction. Comparés au décalage 1, les
	 * décalages de 2 à 8 passaient au-dessus à 12 € (0,145 au décalage 1, de 0,173 à 0,26 ensuite)
	 * sans qu'aucun ne s'écarte du plancher de plus de `PERIOD_GAP`. Une période de trois semaines,
	 * celle de la première règle, reste attrapée par le plancher.
	 *
	 * La mesure est moyennée sur dix saisons : sur une seule, le bruit déplace un décalage de deux
	 * ou trois centièmes d'un côté ou de l'autre, alors qu'une période se retrouve à chaque saison.
	 */
	public function testNoPeriod(): void {

		$source = CompositionLib::getSource();
		$reference = max(CompositionLib::FREQUENCY_GAPS);

		foreach(self::PERIOD_PRICES as $price) {

			$lags = array_fill(1, 12, 0.0);
			$floors = array_fill(1, 12, 0.0);

			foreach(self::PERIOD_SEASONS as $season) {

				$proposal = CompositionLib::propose($price, 5, self::LINES_MIN, self::LINES_MAX, CompositionLib::getSeasonWeeks(), NULL, $season, $source, [], []);

				if($proposal === []) {
					$this->assertFail('le relevé de dev ne compose aucun panier : le contrôle ne verrait rien');
					return;
				}

				foreach($this->getOverlaps($proposal, $season, $source) as $lag => [$overlap, $floor]) {
					$lags[$lag] += $overlap / count(self::PERIOD_SEASONS);
					$floors[$lag] += $floor / count(self::PERIOD_SEASONS);
				}

			}

			$peaks = [];
			$far = [];

			foreach($lags as $lag => $overlap) {

				if($lag > $reference and $overlap > $lags[$reference] + self::PERIOD_PEAK) {
					$peaks[] = $lag.' ('.round($overlap, 3).' contre '.round($lags[$reference], 3).')';
				}

				if($overlap - $floors[$lag] > self::PERIOD_GAP + self::EPSILON) {
					$far[] = $lag.' ('.round($overlap, 3).' pour un plancher à '.round($floors[$lag], 3).')';
				}

			}

			$this->assertEquals([], $peaks, $price.' €, décalages au-dessus du premier : '.implode(', ', $peaks));
			$this->assertEquals([], $far, $price.' €, décalages trop au-dessus du plancher : '.implode(', ', $far));

		}

	}

	/**
	 * Étapes 2 et 3, sans plan : une espèce hors saison ce mois-là ne se propose pas. La semaine 30
	 * tombe en juillet, la 3 en janvier. Le panier de 5 € a de quoi prendre cinq lignes : il prend
	 * donc toutes les candidates, et ce qui manque est ce que les étapes ont écarté.
	 */
	public function testSeasonMonths(): void {

		$species = [];

		foreach(range(1, 5) as $plantId) {
			$species[$plantId] = ['organ' => NULL, 'share' => 0.5];
		}

		$source = $this->getSource($species, [3, 30]);
		$source['seasons'][1] = [1, 2, 12];

		$proposal = CompositionLib::propose(5.0, 5, self::LINES_MIN, self::LINES_MAX, [3, 30], NULL, self::SEASON, $source, [], []);

		$plants30 = array_column($proposal[30]['lines'], 'species');
		$plants3 = array_column($proposal[3]['lines'], 'species');

		sort($plants30);
		sort($plants3);

		$this->assertEquals(7, CompositionLib::getMonth(self::SEASON, 30), 'mois de la semaine 30');
		$this->assertEquals([2, 3, 4, 5], $plants30, 'semaine 30, l\'espèce 1 hors saison');
		$this->assertEquals([1, 2, 3, 4, 5], $plants3, 'semaine 3, de saison');

	}

	/**
	 * Avec un plan, la saison écrite sur chaque espèce est la porte (lot r27) : une espèce retirée, qui
	 * n'en a pas, ne se propose nulle part, une semaine hors de sa saison la ferme, et les mois de la
	 * mercuriale ne ferment plus rien. L'espèce 1, hors saison en juillet pour la mercuriale, entre au
	 * panier de la semaine 30 parce que sa saison la porte, et sort de celui de la semaine 3 parce
	 * qu'elle ne la porte pas.
	 */
	public function testSeasonWritten(): void {

		$species = [];

		foreach(range(1, 6) as $plantId) {
			$species[$plantId] = ['organ' => NULL, 'share' => 0.5];
		}

		$source = $this->getSource($species, [3, 30]);
		$source['seasons'][1] = [1, 2, 12];

		$windows = [
			1 => [30],
			3 => [3, 30],
			4 => [3, 30],
			5 => [3, 30],
			6 => [3, 30],
		];

		$proposal = CompositionLib::propose(5.0, 5, self::LINES_MIN, self::LINES_MAX, [3, 30], $windows, self::SEASON, $source, [], []);

		$plants30 = array_column($proposal[30]['lines'], 'species');
		$plants3 = array_column($proposal[3]['lines'], 'species');

		sort($plants30);
		sort($plants3);

		$this->assertEquals([1, 3, 4, 5, 6], $plants30, 'semaine 30, dans la saison de l\'espèce 1, sans l\'espèce retirée');
		$this->assertEquals([3, 4, 5, 6], $plants3, 'semaine 3, hors de la saison de l\'espèce 1');

	}

	/**
	 * Une espèce dont le prix est trop dispersé reste proposée, sans prix figé, et compte pour
	 * zéro dans la valeur : le facteur se calcule sur les autres.
	 */
	public function testDispersedPriceCountsZero(): void {

		$species = [];

		foreach(range(1, 4) as $plantId) {
			$species[$plantId] = ['organ' => NULL, 'share' => 1 - $plantId / 100];
		}

		$species[1]['low'] = 0.5;
		$species[1]['high'] = 1.5;

		$proposal = CompositionLib::propose(3.6, 5, self::LINES_MIN, self::LINES_MAX, [20], NULL, self::SEASON, $this->getSource($species, [20]), [], []);

		$lines = array_column($proposal[20]['lines'], NULL, 'species');

		$this->assertEquals(NULL, $lines[1]['unitPrice'], 'prix dispersé');
		$this->assertEquals(1.0, $lines[2]['unitPrice'], 'prix employé');
		$this->assertEquals(1.2, $lines[2]['quantity'], 'facteur 3,6 / 3 sur les trois lignes valorisées');

	}

	/**
	 * Le tirage 0 garde le bruit d'avant le rejeu, sans suffixe dans le hash : les formules que
	 * personne n'a rejouées ne bougent pas. Le pendant : un autre tirage ne rend pas le même bruit.
	 */
	public function testReplayVersionZero(): void {

		$getNoise = new \ReflectionMethod(CompositionLib::class, 'getNoise');

		$same = 0;
		$differ = 0;

		foreach(range(1, 52) as $week) {

			$noise = (1 - cos(M_PI * crc32(self::SEASON.'-'.$week.'-'.$week * 7) / 0xFFFFFFFF)) / 2 * CompositionLib::NOISE;

			if(abs($getNoise->invoke(NULL, self::SEASON, $week, $week * 7, 0) - $noise) < self::EPSILON) {
				$same++;
			}

			if(abs($getNoise->invoke(NULL, self::SEASON, $week, $week * 7, 1) - $noise) > self::EPSILON) {
				$differ++;
			}

		}

		$this->assertEquals(52, $same, 'le tirage 0 rend le bruit d\'avant le rejeu');
		$this->assertEquals(52, $differ, 'le tirage 1 en rend un autre');

	}

	/**
	 * Sur le relevé réel de 2026, à 16 € : le tirage suivant change le panier d'au moins 90 % des
	 * semaines (95 à 98 % mesurés dans le cadrage du rejeu), et le même tirage rejoué rend la même
	 * proposition. Sur 20 tirages, une espèce reçoit en moyenne moins d'un panier de plus ou de
	 * moins qu'au tirage 0 sur la saison (0,31 mesuré) : c'est ce qui échouera si quelqu'un monte
	 * `NOISE`, qui double cet écart sans rien renouveler de plus.
	 */
	public function testReplayOnSeason(): void {

		$source = CompositionLib::getSource();
		$weeks = CompositionLib::getSeasonWeeks();

		$proposal0 = CompositionLib::propose(16.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $source, [], []);

		if($proposal0 === []) {
			$this->assertFail('le relevé de dev ne compose aucun panier : le contrôle ne verrait rien');
			return;
		}

		$proposal1 = CompositionLib::propose(16.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $source, [], [], 1);

		$this->assertEquals($proposal1, CompositionLib::propose(16.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $source, [], [], 1), 'le même tirage rend la même proposition');
		$this->assertEquals($proposal0, CompositionLib::propose(16.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $source, [], []), 'le tirage 0 est la proposition par défaut');

		$changed = 0;

		foreach($proposal0 as $week => $basket) {

			$plants0 = array_column($basket['lines'], 'species');
			$plants1 = array_column($proposal1[$week]['lines'] ?? [], 'species');
			sort($plants0);
			sort($plants1);

			if($plants0 !== $plants1) {
				$changed++;
			}

		}

		$this->assertTrue($changed >= 0.9 * count($proposal0), 'le tirage 1 change '.$changed.' paniers sur '.count($proposal0));

		$served0 = $this->countServed($proposal0);
		$deviations = [];

		foreach(range(1, 20) as $version) {

			$served = $this->countServed(CompositionLib::propose(16.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $source, [], [], $version));

			$plants = array_unique(array_merge(array_keys($served0), array_keys($served)));

			$deviations[] = array_sum(array_map(fn(int $plantId) => abs(($served[$plantId] ?? 0) - ($served0[$plantId] ?? 0)), $plants)) / count($plants);

		}

		$deviation = array_sum($deviations) / count($deviations);

		$this->assertTrue($deviation > 0.0, 'les tirages servent les espèces autrement');
		$this->assertTrue($deviation < 1.0, 'écart moyen du nombre de paniers par espèce : '.round($deviation, 2));

	}

	/**
	 * La dette tient chaque espèce à sa part attendue quel que soit le tirage : sur 20 tirages de
	 * quarante semaines, les dix espèces de `testDebtFollowsFrequency()` restent chacune à moins
	 * de trois paniers de leur part (la dette finale la plus forte mesurée sur le relevé réel va de
	 * 1,4 à 2,7 selon le prix).
	 */
	public function testReplayKeepsDebt(): void {

		$weeks = range(5, 44);
		$shares = [1 => 0.9, 2 => 0.8, 3 => 0.6, 4 => 0.5, 5 => 0.4, 6 => 0.3, 7 => 0.2, 8 => 0.15, 9 => 0.1, 10 => 0.05];

		$species = [];

		foreach($shares as $plantId => $share) {
			$species[$plantId] = ['organ' => NULL, 'share' => $share];
		}

		$source = $this->getSource($species, $weeks);

		$far = [];
		$debtMax = 0.0;

		foreach(range(1, 20) as $version) {

			$served = $this->countServed(CompositionLib::propose(4.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $source, [], [], $version));

			foreach($shares as $plantId => $share) {

				$debt = abs(($served[$plantId] ?? 0) - $share * count($weeks));
				$debtMax = max($debtMax, $debt);

				if($debt >= 3.0) {
					$far[] = 'tirage '.$version.', espèce '.$plantId.' servie '.($served[$plantId] ?? 0).' fois pour '.($share * count($weeks));
				}

			}

		}

		$this->assertTrue($debtMax > 0.0, 'la dette finale se mesure');
		$this->assertEquals([], $far, 'espèces à trois paniers ou plus de leur part : '.implode(', ', $far));

	}

	private function countServed(array $proposal): array {

		$served = [];

		foreach($proposal as $basket) {
			foreach(array_column($basket['lines'], 'species') as $plantId) {
				$served[$plantId] = ($served[$plantId] ?? 0) + 1;
			}
		}

		return $served;

	}

	/**
	 * « Recommencer » : le départ envoyé par une structure qui a déjà un plan le supprime entièrement
	 * et écrit celui du départ : sa taille au prix médian, une espèce par espèce proposable, des
	 * lignes proposées intactes, et plus rien de l'ancien plan.
	 */
	public function testRestart(): void {

		$eStructure = \structure\Structure::model()
			->select(['id'])
			->whereStatus(\structure\Structure::ACTIVE)
			->sort(['id' => SORT_ASC])
			->get();

		$price = \aggregate\CompositionSourceAiLib::getBasketPriceMedian();

		if($price === NULL) {
			$this->assertFail('la base de dev n\'a aucun panier partagé en ligne : le contrôle ne verrait rien');
			return;
		}

		CompositionPlanLib::create($this->getStart($eStructure, $price));

		$eCompositionPlanFirst = CompositionPlanLib::getByStructure($eStructure);

		$cCompositionFormulaFirst = CompositionFormulaLib::getByPlan($eCompositionPlanFirst);
		$cCompositionWeekFirst = CompositionWeekLib::getByFormula($cCompositionFormulaFirst->first());

		// 2099 est une année ISO longue (le 1er janvier tombe un jeudi), et compte 52 semaines comme les autres.
		$this->assertEquals(range(1, 52), $eCompositionPlanFirst['weeks'], 'semaines envoyées');
		$this->assertEquals(1, $cCompositionFormulaFirst->count(), 'une seule taille');
		$this->assertEquals((float)$price, (float)$cCompositionFormulaFirst->first()['price'], 'prix médian');
		$this->assertEquals(
			count(CompositionLib::getProposable($eCompositionPlanFirst['weeks'], 2099, CompositionLib::getSource())),
			CompositionSpeciesLib::getByPlan($eCompositionPlanFirst)->count(),
			'une ligne par espèce proposable'
		);

		$cCompositionLine = CompositionLineLib::getByWeeks($cCompositionWeekFirst);

		if($cCompositionLine->empty()) {
			$this->assertFail('la proposition de dev est vide : le contrôle ne verrait rien');
			return;
		}

		foreach($cCompositionLine as $eCompositionLine) {

			$this->assertEquals($eCompositionLine['quantityProposed'], $eCompositionLine['quantity'], 'quantité proposée');
			$this->assertEquals(CompositionLine::PROPOSED, $eCompositionLine['origin'], 'origine');

		}

		CompositionPlanLib::restart($this->getStart($eStructure, $price));

		$eCompositionPlan = CompositionPlanLib::getByStructure($eStructure);

		$this->assertDiffer($eCompositionPlanFirst['id'], $eCompositionPlan['id'], 'plan recréé');
		$this->assertEquals(1, CompositionPlan::model()->whereStructure($eStructure)->whereSeason(2099)->count(), 'un seul plan pour la saison');
		$this->assertEquals(0, CompositionFormula::model()->wherePlan($eCompositionPlanFirst)->count(), 'tailles de l\'ancien plan');
		$this->assertEquals(0, CompositionSpecies::model()->wherePlan($eCompositionPlanFirst)->count(), 'espèces de l\'ancien plan');
		$this->assertEquals(0, CompositionWeek::model()->whereFormula('IN', $cCompositionFormulaFirst)->count(), 'semaines de l\'ancien plan');
		$this->assertEquals(0, CompositionLine::model()->whereWeek('IN', $cCompositionWeekFirst)->count(), 'lignes de l\'ancien plan');
		$this->assertEquals(
			$cCompositionLine->count(),
			CompositionLineLib::getByWeeks(CompositionWeekLib::getByFormula(CompositionFormulaLib::getByPlan($eCompositionPlan)->first()))->count(),
			'même proposition après recommencer'
		);

	}

	/**
	 * Le départ tel que la page le construit à l'envoi : une taille au prix donné, toute l'année, et
	 * les espèces proposables toutes cochées. Les espèces se reprennent d'un départ neuf : celui
	 * d'une saison qui a un plan coche les espèces de ce plan, qui sont ici les mêmes.
	 */
	private function getStart(\structure\Structure $eStructure, float $price): CompositionPlan {

		$eCompositionPlan = CompositionPlanLib::getForStart($eStructure, 2099);
		$eCompositionPlan['weeks'] = $eCompositionPlan['seasonWeeks'];
		$eCompositionPlan['reference'] = CompositionPlan::COMMUNITY;
		$eCompositionPlan['climate'] = NULL;

		$eCompositionPlan['cCompositionSpecies']->map(fn(CompositionSpecies $e) => $e['status'] = CompositionSpecies::NATURAL);

		$eCompositionPlan['cCompositionFormula'] = new \Collection([
			new CompositionFormula(['plan' => $eCompositionPlan, 'name' => 'Le panier', 'price' => $price, 'baskets' => 10, 'gap' => 5, 'version' => 0]),
		]);

		return $eCompositionPlan;

	}

	/**
	 * Le recouvrement moyen d'une proposition à chaque décalage de 1 à 12 semaines, et le plancher
	 * attendu au même décalage : `[décalage => [recouvrement, plancher]]`.
	 *
	 * Le plancher est celui de deux paniers tirés indépendamment, chaque espèce avec sa part
	 * attendue `p` à la semaine : ils ont en commun `Σ p·q` espèces en moyenne, d'où un Jaccard de
	 * `Σ p·q / (L + L' − Σ p·q)`. Les parts sont calculées ici et non lues dans `CompositionLib`,
	 * pour que le contrôle mesure aussi une règle qui ne les calcule pas, comme la première.
	 */
	private function getOverlaps(array $proposal, int $season, array $source): array {

		$getOrder = new \ReflectionMethod(CompositionLib::class, 'getOrder');

		$plants = [];
		$expected = [];

		foreach($proposal as $week => $basket) {

			$plants[$week] = array_column($basket['lines'], 'species');

			$shares = [];

			foreach($getOrder->invoke(NULL, $week, $season, NULL, $source) as $plantId) {
				$shares[$plantId] = $source['frequency']['byWeek'][$week][$plantId] ?? $source['frequency']['byYear'][$plantId] ?? 0.0;
			}

			$expected[$week] = $this->getCapped($shares, count($plants[$week]));

		}

		$overlaps = [];

		foreach(range(1, 12) as $lag) {

			$overlap = 0.0;
			$floor = 0.0;
			$pairs = 0;

			foreach($plants as $week => $plantsWeek) {

				if(array_key_exists($week - $lag, $plants) === FALSE) {
					continue;
				}

				$plantsBefore = $plants[$week - $lag];
				$overlap += count(array_intersect($plantsWeek, $plantsBefore)) / count(array_unique(array_merge($plantsWeek, $plantsBefore)));

				$common = 0.0;

				foreach($expected[$week] as $plantId => $share) {
					$common += $share * ($expected[$week - $lag][$plantId] ?? 0.0);
				}

				$floor += $common / (count($plantsWeek) + count($plantsBefore) - $common);
				$pairs++;

			}

			$overlaps[$lag] = [$overlap / $pairs, $floor / $pairs];

		}

		return $overlaps;

	}

	/**
	 * Des parts brutes rapportées à `$lines` lignes, aucune au-delà de 1 : une espèce est au
	 * panier ou n'y est pas, et ce qu'elle ne peut pas prendre se répartit sur les autres.
	 */
	private function getCapped(array $shares, int $lines): array {

		$capped = [];

		do {

			$free = array_diff_key($shares, $capped);
			$sum = array_sum($free);
			$left = $lines - count($capped);
			$overflow = FALSE;

			foreach($free as $plantId => $share) {

				if((($sum > 0) ? $share * $left / $sum : $left / count($free)) > 1.0) {
					$capped[$plantId] = TRUE;
					$overflow = TRUE;
				}

			}

		} while($overflow and count($capped) < count($shares));

		$free = array_diff_key($shares, $capped);
		$sum = array_sum($free);
		$left = $lines - count($capped);

		$result = [];

		foreach($shares as $plantId => $share) {

			if(array_key_exists($plantId, $capped)) {
				$result[$plantId] = 1.0;
			} else {
				$result[$plantId] = ($sum > 0) ? $share * $left / $sum : $left / count($free);
			}

		}

		return $result;

	}

	/**
	 * Le relevé lu à l'envers à tous les niveaux où un ordre de lecture pourrait compter.
	 */
	private function reverse(array $source): array {

		foreach(['availability', 'units', 'prices', 'frequency', 'organs', 'cabbages'] as $key) {
			$source[$key] = $this->reverseDeep($source[$key]);
		}

		return $source;

	}

	private function reverseDeep(array $values): array {

		$values = array_reverse($values, TRUE);

		foreach($values as $key => $value) {

			if(is_array($value)) {
				$values[$key] = $this->reverseDeep($value);
			}

		}

		return $values;

	}

	/**
	 * Le plan de culture d'une espèce (`CompositionLib::getPlanned()`), sans récolte de l'année d'avant.
	 */
	private function getPlan(array $harvest, array $weeksIncomplete = []): array {
		return ['cultivations' => 1, 'incomplete' => 0, 'harvest' => $harvest, 'weeks' => array_keys($harvest), 'weeksIncomplete' => $weeksIncomplete, 'carried' => [], 'carriedIncomplete' => []];
	}

	/**
	 * Un relevé construit : chaque espèce disponible sur les semaines données, récoltée par dix
	 * structures, dans l'unité 1 à la quantité 1 et au prix médian donné (1 € par défaut), classée
	 * par sa part à l'année, sans minimum de la communauté sauf s'il est donné. Aucune saison, donc
	 * toutes de saison.
	 */
	private function getSource(array $species, array $weeks): array {

		$source = [
			'availability' => [],
			'seasons' => [],
			'units' => [],
			'prices' => [],
			'frequency' => ['byWeek' => [], 'byYear' => []],
			'organs' => [],
			'cabbages' => [],
			'plants' => [],
			'speciesBare' => [],
		];

		foreach($species as $plantId => $properties) {

			$price = $properties['price'] ?? 1.0;

			$source['units'][$plantId] = ['unit' => 1, 'quantity' => 1.0, 'minimum' => $properties['minimum'] ?? NULL, 'step' => $properties['step'] ?? 0.1, 'community' => NULL];
			$source['frequency']['byYear'][$plantId] = $properties['share'];
			$source['organs'][$plantId] = $properties['organ'];

			if($properties['cabbage'] ?? FALSE) {
				$source['cabbages'][$plantId] = TRUE;
			}

			foreach($weeks as $week) {

				$source['availability'][$week][$plantId] = 10;
				$source['prices'][$week][$plantId][1] = [
					'contributors' => 10,
					'median' => $price,
					'low' => $properties['low'] ?? $price,
					'high' => $properties['high'] ?? $price,
				];

			}

		}

		return $source;

	}

}
?>
