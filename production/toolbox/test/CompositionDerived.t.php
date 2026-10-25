<?php
namespace toolbox;

/**
 * Le panier dérivé (lot r17) : une taille qui s'appuie sur une autre, moins chère, en reprend les
 * lignes de chaque semaine, quantités augmentées, et y ajoute des espèces jusqu'à son prix.
 *
 * Deux moitiés, comme `CompositionRuleTest`. La règle (`CompositionLib::propose()` avec `$base`)
 * s'éprouve sur des relevés construits à la main, parce qu'un relevé réel ne garantit ni un organe
 * au plafond, ni une espèce de diversification servie la semaine d'avant. Le lien entre deux
 * tailles écrites (refus, propagation d'une correction, détachement, « Recommencer ») s'éprouve sur
 * un plan 2099, qu'aucune structure n'emploie, écrit par les Lib comme l'écrit la page.
 *
 * Chaque cas a son pendant, pour qu'un contrôle qui ne trouve rien ne passe pas pour réussi : la
 * semaine dérivée corrigée qui ne bouge pas à côté de celle qui suit la base, l'espèce ajoutée
 * quand l'héritage ne l'a pas servie la semaine d'avant, le lien qui tombe quand la base n'est plus
 * la moins chère.
 *
 * Isolation : une transaction par méthode, annulée en tearDown().
 *
 * Lancement : dev/test package=toolbox file=CompositionDerived
 */
class CompositionDerivedTest extends \Test {

	const SEASON = 2026;
	const WEEK = 20;

	/**
	 * Les semaines du plan 2099 : huit suffisent à voir une semaine suivre sa base et une autre
	 * rester telle qu'elle est, et une saison entière coûterait une composition de 52 semaines par
	 * taille et par geste.
	 */
	const WEEKS = [20, 21, 22, 23, 24, 25, 26, 27];

	/**
	 * Le prix de la petite taille du plan 2099, celui d'un panier courant. Il est écrit ici plutôt que
	 * lu dans l'agrégé : le prix médian des paniers partagés passe par les seuils d'anonymat, que
	 * `AnonymitySentinelAiTest` demande à chaque lecteur de justifier, et un test n'en a pas besoin.
	 */
	const PRICE = 16.0;

	public function setUp(): void {
		CompositionPlan::model()->beginTransaction();
	}

	public function tearDown(): void {
		CompositionPlan::model()->rollBack();
	}

	/**
	 * Le plancher de lignes passe avant le plafond par organe dans une taille dérivée aussi (r70) :
	 * une base de deux feuilles et une racine, une troisième feuille seule candidate, quatre à cinq
	 * lignes. Le plafond de deux feuilles la refusait, et la semaine restait à trois lignes héritées
	 * sous un plancher de quatre. Elle entre ; sans elle, la semaine reste à trois lignes.
	 */
	public function testLinesMinBeforeCeiling(): void {

		$species = [
			1 => ['organ' => \plant\Plant::LEAF, 'share' => 0.9],
			2 => ['organ' => \plant\Plant::LEAF, 'share' => 0.8],
			3 => ['organ' => \plant\Plant::ROOT, 'share' => 0.7],
			4 => ['organ' => \plant\Plant::LEAF, 'share' => 0.1],
		];

		$base = [self::WEEK => $this->getBaseLines([1, 2, 3], $species)];

		$proposal = CompositionLib::propose(10.0, 5, 4, 5, [self::WEEK], NULL, self::SEASON, $this->getSource($species, [self::WEEK]), [], [], 0, [], $base);

		$this->assertEquals([1, 2, 3, 4], array_column($proposal[self::WEEK]['lines'], 'species'), 'la troisième feuille complète la taille dérivée jusqu\'au plancher');

		unset($species[4]);

		$proposal = CompositionLib::propose(10.0, 5, 4, 5, [self::WEEK], NULL, self::SEASON, $this->getSource($species, [self::WEEK]), [], [], 0, [], $base);

		$this->assertEquals([1, 2, 3], array_column($proposal[self::WEEK]['lines'], 'species'), 'sans autre candidate, la semaine garde sa base');

	}

	/**
	 * Chaque espèce héritée reste au panier, dans l'ordre de la base, à une quantité au moins égale
	 * à la sienne ; les espèces ajoutées sont d'autres espèces. À 6 € pour cinq lignes d'un euro, le
	 * facteur vaut 1,2 et le kilo hérité monte à 1,2 kg : le grand panier augmente les quantités.
	 */
	public function testInherited(): void {

		$species = $this->getSpecies(8);
		$base = [self::WEEK => $this->getBaseLines([1, 2, 3])];

		$proposal = CompositionLib::propose(6.0, 5, 4, 5, [self::WEEK], NULL, self::SEASON, $this->getSource($species, [self::WEEK]), [], [], 0, [], $base);
		$lines = $proposal[self::WEEK]['lines'];

		$this->assertEquals([1, 2, 3], array_column(array_slice($lines, 0, 3), 'species'), 'lignes héritées en tête, dans l\'ordre de la base');
		$this->assertEquals([], array_intersect([1, 2, 3], array_column(array_slice($lines, 3), 'species')), 'une espèce ajoutée n\'est pas une espèce héritée');
		$this->assertEquals(5, count($lines), 'lignes');

		foreach(array_slice($lines, 0, 3) as $line) {
			$this->assertEquals(1.2, $line['quantity'], $line['species'].', quantité héritée multipliée par le facteur');
		}

	}

	/**
	 * Une ligne à la pièce ne se coupe pas : 2 pièces à 1,4 font 3 pièces, pas 2,8. Et la ligne
	 * héritée garde son unité et son prix figé (lot r14), même quand l'espèce entre désormais au
	 * panier dans une autre unité, à un autre prix.
	 *
	 * L'écart est à 100 % : l'ajustement au pas (r23, `testPriceSteps()`) ne vient pas reprendre
	 * l'arrondi que ce cas éprouve.
	 */
	public function testInheritedWholeUnit(): void {

		$species = $this->getSpecies(6);

		$base = [self::WEEK => [
			['species' => 1, 'unit' => 99, 'quantity' => 2.0, 'unitPrice' => 2.5, 'organ' => NULL, 'step' => ['step' => 1.0, 'minimum' => 1.0]],
		]];

		$proposal = CompositionLib::propose(40.0, 100, 1, 2, [self::WEEK], NULL, self::SEASON, $this->getSource($species, [self::WEEK]), [], [], 0, [], $base);
		$line = $proposal[self::WEEK]['lines'][0];

		$this->assertEquals(1, $line['species'], 'espèce héritée');
		$this->assertEquals(3.0, $line['quantity'], 'pièces arrondies au pas entier');
		$this->assertEquals(99, $line['unit'], 'unité de la base');
		$this->assertEquals(2.5, $line['unitPrice'], 'prix figé de la base');

	}

	/**
	 * L'ajustement au pas (r23) vaut aussi pour une semaine dérivée, sur le surplus au-dessus de la
	 * base. Un kilo hérité à 3 €, au pas de 0,2 kg, seul au panier (un plafond d'une ligne ne laisse
	 * rien ajouter) :
	 *
	 * - pour 4,90 €, le facteur borné à 1,4 le monte à 1,4 kg, 4,20 €, au-delà de 5 % ; l'ajustement
	 *   le porte à 1,6 kg, 4,80 €, au-delà de ce que le facteur donnait ;
	 * - pour 2 €, le facteur tenu à 1 le laisse à 1 kg, 3 € ; descendre d'un pas rapprocherait du prix,
	 *   mais le grand panier ne contiendrait plus le petit, et la ligne reste à sa quantité de base ;
	 * - pour 12 €, l'ajustement s'arrête à 2 fois la quantité de base, 2 kg.
	 */
	public function testPriceSteps(): void {

		$source = $this->getSource($this->getSpecies(1), [self::WEEK]);

		$base = [self::WEEK => [
			['species' => 1, 'unit' => 1, 'quantity' => 1.0, 'unitPrice' => 3.0, 'organ' => NULL, 'step' => ['step' => 0.2, 'minimum' => 0.2]],
		]];

		$quantity = fn(float $price) => CompositionLib::propose($price, 5, 1, 1, [self::WEEK], NULL, self::SEASON, $source, [], [], 0, [], $base)[self::WEEK]['lines'][0]['quantity'];

		$this->assertEquals(1.6, $quantity(4.9), 'le surplus au-delà du facteur, dans l\'écart');
		$this->assertEquals(1.0, $quantity(2.0), 'jamais sous la quantité de base');
		$this->assertEquals(2.0, $quantity(12.0), '2 fois la quantité de base au plus');

	}

	/**
	 * Le stock du plan de culture (lot r23) dans une semaine dérivée. Dix paniers, un kilo l'unité,
	 * une récolte gardée une semaine de plus :
	 *
	 * - 1, héritée en semaine 20 : 11 kg, donc 1,1 kg au panier, que son surplus ne dépasse pas. Elle
	 *   prend sa part au stock, et rien ne reste pour 21, où la base ne la porte pas ;
	 * - 3, héritée en semaine 20 et récoltée seulement en semaine 30 : elle reste à sa quantité de
	 *   base, le grand panier contenant le petit, mais n'est jamais ajoutée ;
	 * - 2 : 3 kg en semaine 20, ajoutée à 0,3 kg au plus, et absente en 21 ;
	 * - 4 : de quoi servir, jusqu'à 2 fois sa référence.
	 *
	 * Une espèce que le plan de culture ne date pas, cultivée sans semaine de récolte ou pas cultivée
	 * du tout, ne se plafonne pas (arbitrage r27 du 28/09) : 3 est alors servie comme sans stock, au
	 * plafond de 2 fois sa référence que 4 lui laisse.
	 */
	public function testStock(): void {

		$weeks = [20, 21];
		$species = $this->getSpecies(4);

		$source = $this->getSource($species, $weeks);

		foreach(array_keys($species) as $plantId) {
			$source['storage'][$plantId] = ['weeks' => 1];
		}

		$plan = fn(array $harvest) => ['cultivations' => 1, 'incomplete' => 0, 'harvest' => $harvest, 'weeks' => array_keys($harvest), 'weeksIncomplete' => [], 'carried' => [], 'carriedIncomplete' => []];

		$stock = [
			'planned' => [
				1 => $plan([20 => 11.0]),
				2 => $plan([20 => 3.0]),
				3 => $plan([30 => 5.0]),
				4 => $plan([20 => 100.0, 21 => 100.0]),
			],
			'shared' => [],
			'needs' => ['needs' => [], 'unknown' => []],
			'kilos' => [1 => 1.0, 2 => 1.0, 3 => 1.0, 4 => 1.0],
			'baskets' => 10,
		];

		$base = [20 => $this->getBaseLines([1, 3]), 21 => $this->getBaseLines([4])];

		$proposalFree = CompositionLib::propose(6.0, 5, 3, 4, $weeks, NULL, self::SEASON, $source, [], [], 0, [], $base);
		$proposal = CompositionLib::propose(6.0, 5, 3, 4, $weeks, NULL, self::SEASON, $source, [], [], 0, [], $base, $stock);

		$quantities = fn(array $proposal) => array_map(fn(array $basket) => array_column($basket['lines'], 'quantity', 'species'), $proposal);

		$this->assertEquals([
			20 => [1 => 1.5, 3 => 1.4, 4 => 1.4, 2 => 1.4],
			21 => [4 => 1.5, 1 => 1.4, 3 => 1.4, 2 => 1.4],
		], $quantities($proposalFree), 'sans stock, toutes servies chaque semaine, au-dessus de la base');

		$this->assertEquals([
			20 => [1 => 1.1, 3 => 1.0, 4 => 2.0, 2 => 0.3],
			21 => [4 => 2.0],
		], $quantities($proposal), 'le stock borne le surplus et les ajouts');

		$undated = [
			20 => [1 => 1.1, 3 => 2.0, 4 => 2.0, 2 => 0.3],
			21 => [4 => 2.0, 3 => 2.0],
		];

		$stockUncultivated = $stock;
		unset($stockUncultivated['planned'][3], $stockUncultivated['kilos'][3]);

		$this->assertEquals($undated, $quantities(CompositionLib::propose(6.0, 5, 3, 4, $weeks, NULL, self::SEASON, $source, [], [], 0, [], $base, $stockUncultivated)), 'une espèce absente du plan de culture ne se plafonne pas');

		$stockUndated = $stock;
		$stockUndated['planned'][3] = $plan([]);

		$this->assertEquals($undated, $quantities(CompositionLib::propose(6.0, 5, 3, 4, $weeks, NULL, self::SEASON, $source, [], [], 0, [], $base, $stockUndated)), 'une espèce cultivée sans semaine de récolte ne se plafonne pas');

	}

	/**
	 * Le stock qu'une taille écrite lit (`CompositionLib::getStock()`, lot r23) : aucun sous
	 * `CompositionPlan::COMMUNITY`, et sous `CompositionPlan::CULTIVATION`, ce que servent les
	 * autres tailles du plan, jamais la sienne, par ses paniers. Les autres tailles se lisent à part
	 * depuis P6 (`getFormulasOtherWithLines()`), l'écran passant celles qu'il a déjà.
	 */
	public function testGetStock(): void {

		[$eCompositionPlan, $small, $large, $largest] = $this->createPlan();

		$source = CompositionLib::getSourceByPlan($eCompositionPlan);
		$cCompositionSpecies = CompositionSpeciesLib::getByPlan($eCompositionPlan);
		$cCompositionSpeciesPriced = CompositionSpeciesLib::getWithPrices($eCompositionPlan, $source, $cCompositionSpecies);

		$this->assertEquals([], CompositionLib::getStock($eCompositionPlan, $small, $source, $cCompositionSpeciesPriced, CompositionLib::getFormulasOtherWithLines($eCompositionPlan, $small, $cCompositionSpecies)), 'aucun stock sous la communauté');

		$eCompositionPlan['reference'] = CompositionPlan::CULTIVATION;
		$eCompositionPlan['climate'] = NULL;

		$cCompositionFormulaRead = CompositionLib::getFormulasOtherWithLines($eCompositionPlan, $small, $cCompositionSpecies);
		$formulaIds = $cCompositionFormulaRead->getIds();
		sort($formulaIds);

		$this->assertEquals([$large['id'], $largest['id']], $formulaIds, 'les autres tailles du plan, jamais la sienne');

		$stock = CompositionLib::getStock($eCompositionPlan, $small, $source, $cCompositionSpeciesPriced, $cCompositionFormulaRead);

		$cCompositionFormulaOther = new \Collection([$large, $largest]);

		foreach($cCompositionFormulaOther as $eCompositionFormula) {
			$eCompositionFormula['cCompositionWeek'] = CompositionWeekLib::getByFormulaWithLines($eCompositionFormula, $cCompositionSpecies);
		}

		$needs = CompositionLib::getNeeds($cCompositionFormulaOther, $source, $cCompositionSpeciesPriced);

		$this->assertTrue($needs['needs'] !== [], 'les autres tailles servent des espèces');
		$this->assertEquals($needs, $stock['needs'], 'le besoin des deux autres tailles');
		$this->assertDiffer($needs, CompositionLib::getStock($eCompositionPlan, $large, $source, $cCompositionSpeciesPriced, CompositionLib::getFormulasOtherWithLines($eCompositionPlan, $large, $cCompositionSpecies))['needs'], 'la grande lit un autre besoin');
		$this->assertEquals(10, $stock['baskets'], 'paniers de la petite');
		$this->assertEquals(array_keys($stock['planned']), array_keys($stock['kilos']), 'un poids par espèce du plan de culture');

	}

	/**
	 * Les espèces ajoutées ne dépassent jamais la place que laisse l'héritage sous le plafond : trois
	 * lignes héritées sous un plafond de 5 en laissent deux, même à un prix qui en demanderait dix.
	 * Une base qui atteint déjà le plafond est héritée entière, sans rien ajouter.
	 */
	public function testAddedBounded(): void {

		$species = $this->getSpecies(12);
		$source = $this->getSource($species, [self::WEEK]);

		$proposal = CompositionLib::propose(40.0, 5, 4, 5, [self::WEEK], NULL, self::SEASON, $source, [], [], 0, [], [self::WEEK => $this->getBaseLines([1, 2, 3])]);

		$this->assertEquals(5, count($proposal[self::WEEK]['lines']), 'trois héritées et deux ajoutées au plafond');

		$proposal = CompositionLib::propose(40.0, 5, 4, 5, [self::WEEK], NULL, self::SEASON, $source, [], [], 0, [], [self::WEEK => $this->getBaseLines([1, 2, 3, 4, 5])]);

		$this->assertEquals([1, 2, 3, 4, 5], array_column($proposal[self::WEEK]['lines'], 'species'), 'base au plafond héritée seule');

	}

	/**
	 * Le même appel rend le même panier : rien dans la dérivation ne tire au hasard.
	 */
	public function testDeterministic(): void {

		$weeks = [20, 21, 22, 23];
		$source = $this->getSource($this->getSpecies(12), $weeks);

		$base = [];

		foreach($weeks as $week) {
			$base[$week] = $this->getBaseLines([1, 2, 3]);
		}

		$first = CompositionLib::propose(9.0, 5, 4, 8, $weeks, NULL, self::SEASON, $source, [], [], 0, [], $base);
		$second = CompositionLib::propose(9.0, 5, 4, 8, $weeks, NULL, self::SEASON, $source, [], [], 0, [], $base);

		$this->assertEquals($first, $second, 'même proposition');

	}

	/**
	 * L'écart minimal d'une espèce de diversification (r10) compte les semaines où l'héritage l'a
	 * servie. La 3 est héritée en semaine 20 : en 21, elle attend, et la seule place libre reste vide,
	 * les deux autres candidates étant des racines déjà au plafond de l'organe. Quand la semaine 20 de
	 * la base sert la 5 à sa place, la 3 entre en 21.
	 */
	public function testGapCountsInherited(): void {

		$species = [
			1 => ['organ' => \plant\Plant::ROOT, 'share' => 1.0],
			2 => ['organ' => \plant\Plant::ROOT, 'share' => 1.0],
			3 => ['organ' => NULL, 'share' => 0.1],
			4 => ['organ' => \plant\Plant::ROOT, 'share' => 0.4],
			5 => ['organ' => \plant\Plant::ROOT, 'share' => 0.4],
		];

		$source = $this->getSource($species, [20, 21]);

		$propose = fn(array $base) => CompositionLib::propose(3.0, 5, 2, 3, [20, 21], NULL, self::SEASON, $source, [], [], 0, [], $base);

		$proposal = $propose([
			20 => $this->getBaseLines([1, 2, 3], $species),
			21 => $this->getBaseLines([1, 2], $species),
		]);

		$this->assertEquals([1, 2, 3], array_column($proposal[20]['lines'], 'species'), 'semaine 20, la 3 héritée');
		$this->assertEquals([1, 2], array_column($proposal[21]['lines'], 'species'), 'semaine 21, la 3 attend son écart');

		$proposal = $propose([
			20 => $this->getBaseLines([1, 2, 5], $species),
			21 => $this->getBaseLines([1, 2], $species),
		]);

		$this->assertEquals([1, 2, 3], array_column($proposal[21]['lines'], 'species'), 'semaine 21, la 3 entre quand la base ne l\'a pas servie');

	}

	/**
	 * Une semaine que la base ne sert pas se compose seule, exactement comme sans base.
	 */
	public function testWeekWithoutBase(): void {

		$source = $this->getSource($this->getSpecies(12), [21]);

		$this->assertEquals(
			CompositionLib::propose(9.0, 5, 4, 8, [21], NULL, self::SEASON, $source, [], []),
			CompositionLib::propose(9.0, 5, 4, 8, [21], NULL, self::SEASON, $source, [], [], 0, [], [20 => $this->getBaseLines([1, 2, 3])]),
			'semaine 21 sans panier de base'
		);

	}

	/**
	 * Chaque refus du lien a sa raison et son message, et un réglage qui respecte l'ordre des tailles
	 * passe. Trois tailles, dans l'ordre des prix : la petite, la grande, qui s'appuie sur la petite
	 * une fois le lien écrit, et la très grande, sans base.
	 */
	public function testRefusals(): void {

		[$eCompositionPlan, $small, $large, $extra] = $this->createPlan();

		$build = function(array $input, CompositionFormula $e): array {

			$fw = new \FailWatch();

			$e->build(array_keys($input), $input, new \PropertyList('update'));

			return array_map(fn($fail) => $fail[1], $fw->get());

		};

		$this->assertEquals(['CompositionFormula::base.plan'], $build(['base' => $small['id'] + 100000000], $this->getFormula($eCompositionPlan, $large)), 'base d\'un autre plan');
		$this->assertEquals(['CompositionFormula::base.self'], $build(['base' => $large['id']], $this->getFormula($eCompositionPlan, $large)), 'base sur elle-même');
		$this->assertEquals(['CompositionFormula::base.cheaper'], $build(['base' => $large['id']], $this->getFormula($eCompositionPlan, $small)), 'base plus chère');

		$eCompositionFormulaNarrow = $this->getFormula($eCompositionPlan, $large);
		$eCompositionFormulaNarrow['linesMax'] = 5;

		$this->assertEquals(['CompositionFormula::base.lines'], $build(['base' => $small['id']], $eCompositionFormulaNarrow), 'base au plafond plus haut');

		$this->link($eCompositionPlan, $large, $small);

		$this->assertEquals(['CompositionFormula::base.chain'], $build(['base' => $large['id']], $this->getFormula($eCompositionPlan, $extra)), 'base qui a déjà une base');
		$this->assertEquals(['CompositionFormula::base.chain'], $build(['base' => $extra['id']], $this->getFormula($eCompositionPlan, $small)), 'base qui prend une base');

		$this->assertEquals(['CompositionFormula::linesMax.belowBase'], $build(['linesMax' => 5], $this->getFormula($eCompositionPlan, $large)), 'plafond de la dérivée sous celui de la base');
		$this->assertEquals(['CompositionFormula::linesMax.belowBase'], $build(['linesMax' => 11], $this->getFormula($eCompositionPlan, $small)), 'plafond de la base au-dessus de celui de la dérivée');
		$this->assertEquals(['CompositionFormula::price.order'], $build(['price' => $small['price']], $this->getFormula($eCompositionPlan, $large)), 'dérivée au prix de la base');
		$this->assertEquals(['CompositionFormula::price.order'], $build(['price' => $large['price']], $this->getFormula($eCompositionPlan, $small)), 'base au prix de la dérivée');

		$this->assertEquals([], $build(['linesMax' => 6, 'price' => $small['price'] + 1], $this->getFormula($eCompositionPlan, $large)), 'dérivée réglée dans l\'ordre');
		$this->assertEquals([], $build(['linesMax' => 10, 'price' => $large['price'] - 1], $this->getFormula($eCompositionPlan, $small)), 'base réglée dans l\'ordre');
		$this->assertEquals([], $build(['base' => ''], $this->getFormula($eCompositionPlan, $large)), 'lien retiré');

		foreach(['base.check', 'base.plan', 'base.self', 'base.chain', 'base.cheaper', 'base.lines', 'linesMax.belowBase', 'price.order'] as $fail) {
			$this->assertDiffer(NULL, AlertUi::getError('CompositionFormula::'.$fail), 'le refus '.$fail.' a son message');
		}

	}

	/**
	 * Le lien écrit, chaque semaine de la grande reprend toutes les lignes de la petite, à une
	 * quantité au moins égale. Une correction de la petite en semaine 21 passe à la grande en
	 * semaine 21 ; la même correction en semaine 22, où la grande est corrigée, n'y change rien, et
	 * les autres semaines de la grande ne bougent pas.
	 */
	public function testPropagation(): void {

		[$eCompositionPlan, $small, $large] = $this->createPlan();

		$this->link($eCompositionPlan, $large, $small);
		$this->assertContains($small, $large, 'lien écrit');

		$this->raise($large, 22);

		$linesBefore = $this->getLines($large);

		$this->raise($small, 21);
		$this->raise($small, 22);

		$this->assertContains($small, $large, 'correction de la base', weeksSkipped: [22]);

		$linesAfter = $this->getLines($large);

		$this->assertDiffer($linesBefore[21], $linesAfter[21], 'semaine 21 de la grande refaite');
		$this->assertEquals($linesBefore[22], $linesAfter[22], 'semaine 22 de la grande corrigée, épargnée');

		foreach(array_diff(array_keys($linesBefore), [21, 22]) as $week) {
			$this->assertEquals($linesBefore[$week], $linesAfter[$week], 'semaine '.$week.' de la grande, hors du geste');
		}

	}

	/**
	 * Un prix changé refait la petite, et la grande la suit : ses semaines reprennent les nouvelles
	 * lignes. Le contrôle voit bien la petite changer.
	 */
	public function testBasePriceChange(): void {

		[$eCompositionPlan, $small, $large] = $this->createPlan();

		$this->link($eCompositionPlan, $large, $small);

		$linesBefore = $this->getLines($small);

		$eCompositionFormula = $this->getFormula($eCompositionPlan, $small);
		$eCompositionFormula['price'] = round($small['price'] * 1.3, 2);

		CompositionFormulaLib::update($eCompositionFormula, ['price']);

		$this->assertDiffer($linesBefore, $this->getLines($small), 'la petite refaite à son nouveau prix');
		$this->assertContains($small, $large, 'la grande suit la petite');

	}

	/**
	 * La base supprimée, la grande se détache et garde ses semaines telles qu'elles sont.
	 */
	public function testBaseDeleted(): void {

		[$eCompositionPlan, $small, $large] = $this->createPlan();

		$this->link($eCompositionPlan, $large, $small);

		$linesBefore = $this->getLines($large);

		CompositionFormulaLib::delete($this->getFormula($eCompositionPlan, $small));

		$this->assertTrue($this->getFormula($eCompositionPlan, $large)['base']->empty(), 'grande détachée');
		$this->assertEquals($linesBefore, $this->getLines($large), 'semaines de la grande gardées');

	}

	/**
	 * « Recommencer » garde le lien par rang tant que la base reste la moins chère ; un départ qui
	 * rend la base plus chère que sa dérivée le fait tomber.
	 */
	public function testRestart(): void {

		[$eCompositionPlan, $small, $large] = $this->createPlan();

		$this->link($eCompositionPlan, $large, $small);

		CompositionPlanLib::restart($this->getStart($eCompositionPlan['structure'], [$small['price'], $large['price'], $large['price'] * 1.5]));

		$formulas = $this->getFormulas(CompositionPlanLib::getByStructure($eCompositionPlan['structure']));

		$this->assertEquals($formulas[0]['id'], $formulas[1]['base']['id'], 'la grande s\'appuie sur la nouvelle petite');
		$this->assertTrue($formulas[0]['base']->empty() and $formulas[2]['base']->empty(), 'les autres sans base');

		$this->assertContains($formulas[0], $formulas[1], 'la grande composée sur la petite');

		CompositionPlanLib::restart($this->getStart($eCompositionPlan['structure'], [$large['price'] * 2, $large['price'], $large['price'] * 3]));

		$cCompositionFormula = CompositionFormulaLib::getByPlan(CompositionPlanLib::getByStructure($eCompositionPlan['structure']));

		$this->assertEquals(0, $cCompositionFormula->find(fn(CompositionFormula $e) => $e['base']->notEmpty())->count(), 'lien tombé, la base devenue plus chère');

	}

	/**
	 * Toutes les semaines de `$derived` reprennent les lignes de `$base` à une quantité au moins
	 * égale, hors `$weeksSkipped`. Le contrôle exige une semaine de base avec des lignes, sans quoi
	 * il ne verrait rien.
	 */
	private function assertContains(CompositionFormula $base, CompositionFormula $derived, string $label, array $weeksSkipped = []): void {

		$linesBase = $this->getLines($base);
		$linesDerived = $this->getLines($derived);

		$this->assertTrue($linesBase !== [], $label.', la base a des semaines');

		foreach($linesBase as $week => $lines) {

			if(in_array($week, $weeksSkipped, TRUE)) {
				continue;
			}

			foreach($lines as $plantId => $quantity) {
				$this->assertTrue(($linesDerived[$week][$plantId] ?? 0.0) >= $quantity, $label.', semaine '.$week.', espèce '.$plantId.' à '.($linesDerived[$week][$plantId] ?? 'rien').' pour '.$quantity);
			}

		}

	}

	/**
	 * Les lignes écrites d'une formule, `[semaine => [espèce => quantité]]`.
	 */
	private function getLines(CompositionFormula $e): array {

		$lines = [];

		foreach(CompositionLineLib::getByFormula($e) as $eCompositionLine) {
			$lines[$eCompositionLine['week']['week']][$eCompositionLine['species']['id']] = (float)$eCompositionLine['quantity'];
		}

		ksort($lines);

		return $lines;

	}

	/**
	 * Une correction à la main : la première ligne de la semaine `$week` prend un pas de plus, par
	 * le geste de la grille (`CompositionLineLib::update()`).
	 */
	private function raise(CompositionFormula $e, int $week): void {

		$eCompositionWeek = CompositionWeekLib::getByFormulaAndWeek($e, $week);
		$eCompositionLine = CompositionLineLib::getByWeek($eCompositionWeek)->first();

		$eCompositionLine['quantity'] = $eCompositionLine['quantity'] + 1;
		$eCompositionLine['week'] = $eCompositionWeek;

		CompositionLineLib::update($eCompositionLine, ['quantity']);

	}

	private function link(CompositionPlan $eCompositionPlan, CompositionFormula $derived, CompositionFormula $base): void {

		$eCompositionFormula = $this->getFormula($eCompositionPlan, $derived);
		$eCompositionFormula['base'] = $this->getFormula($eCompositionPlan, $base);

		CompositionFormulaLib::update($eCompositionFormula, ['base']);

	}

	/**
	 * La formule telle que la page la lit avant un geste, relue à chaque fois : un `build()` réussi
	 * pose sa valeur sur l'élément.
	 */
	private function getFormula(CompositionPlan $eCompositionPlan, CompositionFormula $e): CompositionFormula {
		return CompositionFormulaLib::getByPlanAndId($eCompositionPlan, $e['id']);
	}

	/**
	 * Un plan 2099 sur une structure qui n'a pas de paniers, trois tailles sans base : la petite à
	 * `PRICE` et au plafond de 6 légumes, la grande à 1,6 fois et 10
	 * légumes, la très grande à 2 fois. Rend `[plan, petite, grande, très grande]`.
	 */
	private function createPlan(): array {

		$eStructure = \structure\Structure::model()
			->select(['id'])
			->whereStatus(\structure\Structure::ACTIVE)
			->whereId('NOT IN', CompositionPlan::model()->select(['structure'])->getColumn('structure'))
			->sort(['id' => SORT_ASC])
			->get();

		CompositionPlanLib::create($this->getStart($eStructure, [self::PRICE, self::PRICE * 1.6, self::PRICE * 2]));

		$eCompositionPlan = CompositionPlanLib::getByStructure($eStructure);

		return [$eCompositionPlan, ...$this->getFormulas($eCompositionPlan)];

	}

	/**
	 * Les tailles du plan dans l'ordre des positions, en liste : le rang est ce que « Recommencer »
	 * garde.
	 */
	private function getFormulas(CompositionPlan $eCompositionPlan): array {

		$formulas = [];

		foreach(CompositionFormulaLib::getByPlan($eCompositionPlan) as $eCompositionFormula) {
			$formulas[] = $eCompositionFormula;
		}

		return $formulas;

	}

	/**
	 * Le départ tel que la page le construit à l'envoi, une taille par prix de `$prices`, sur les
	 * semaines `WEEKS`. Les plafonds de 6, 10 et 12 légumes laissent la grande hériter de la petite.
	 */
	private function getStart(\structure\Structure $eStructure, array $prices): CompositionPlan {

		$eCompositionPlan = CompositionPlanLib::getForStart($eStructure, 2099);
		$eCompositionPlan['weeks'] = self::WEEKS;
		$eCompositionPlan['reference'] = CompositionPlan::COMMUNITY;
		$eCompositionPlan['climate'] = NULL;

		$eCompositionPlan['cCompositionSpecies']->map(fn(CompositionSpecies $e) => $e['status'] = CompositionSpecies::NATURAL);

		$formulas = [];

		foreach(array_values($prices) as $index => $price) {

			$formulas[] = new CompositionFormula([
				'plan' => $eCompositionPlan,
				'name' => 'Taille '.$index,
				'price' => $price,
				'baskets' => 10,
				'gap' => 5,
				'linesMin' => 4,
				'linesMax' => [6, 10, 12][$index],
				'version' => 0,
			]);

		}

		$eCompositionPlan['cCompositionFormula'] = new \Collection($formulas);

		return $eCompositionPlan;

	}

	/**
	 * `$count` espèces sans organe, aux parts décroissantes de 0,9 à 0,9 − 0,05 × (`$count` − 1).
	 */
	private function getSpecies(int $count): array {

		$species = [];

		foreach(range(1, $count) as $plantId) {
			$species[$plantId] = ['organ' => NULL, 'share' => 0.95 - $plantId * 0.05];
		}

		return $species;

	}

	/**
	 * Les lignes d'une semaine de la base, un kilo à un euro par espèce, au pas de 0,1 : ce que
	 * `CompositionLib::getBaseLines()` rend. L'organe est celui de `$species`, que le plafond et le
	 * plancher du tirage comptent sur les lignes héritées.
	 */
	private function getBaseLines(array $speciesIds, array $species = []): array {

		return array_map(fn(int $speciesId) => [
			'species' => $speciesId,
			'unit' => 1,
			'quantity' => 1.0,
			'unitPrice' => 1.0,
			'organ' => $species[$speciesId]['organ'] ?? NULL,
			'step' => ['step' => 0.1, 'minimum' => 0.1],
		], $speciesIds);

	}

	/**
	 * Un relevé construit à la main, comme `CompositionRuleTest::getSource()` : une unité par
	 * espèce, 1 kilo de référence au pas de 0,1, à 1 € par défaut.
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

			$source['units'][$plantId] = ['unit' => 1, 'quantity' => 1.0, 'minimum' => NULL, 'step' => 0.1, 'community' => NULL];
			$source['frequency']['byYear'][$plantId] = $properties['share'];
			$source['organs'][$plantId] = $properties['organ'];

			foreach($weeks as $week) {

				$source['availability'][$week][$plantId] = 10;
				$source['prices'][$week][$plantId][1] = [
					'contributors' => 10,
					'median' => $price,
					'low' => $price,
					'high' => $price,
				];

			}

		}

		return $source;

	}

}
?>
