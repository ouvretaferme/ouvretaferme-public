<?php
namespace toolbox;

/**
 * La soudure (lot r22, point 4) : les semaines où le plan de culture ne donne plus assez d'espèces
 * pour atteindre le prix (`CompositionLib::getLeanWeeks()`), leurs périodes (`getLeanPeriods()`), les
 * espèces conservées dont une garde plus longue ouvrirait une période (`getStretch()`), et ce que
 * l'écran en montre : le bloc de chaque période, la bande et les portes de chaque carte, l'intitulé
 * du rail, et « Retirer la semaine ».
 *
 * La règle s'éprouve sur des stocks construits à la main, comme `CompositionDerivedTest::testStock()` :
 * un plan de culture réel ne garantit ni une semaine servie de toutes ses candidates sous le prix, ni
 * un lot que la semaine d'avant vide. L'écran s'éprouve sur un plan écrit par le départ sur la saison
 * 2099, qu'aucune structure ne cultive : sous « Mes semaines de récolte », toutes ses semaines sont
 * donc de soudure, sans espèce.
 *
 * Chaque cas a son pendant : la semaine qui garde une candidate à côté de celle qui les sert toutes,
 * le lot qu'une ligne plus petite laisse à la semaine suivante, la structure sans droit d'écrire qui
 * ne voit pas les portes, la communauté où aucun geste ne touche à la soudure.
 *
 * Isolation : la transaction de `TransactionTest`, annulée après chaque méthode.
 *
 * Lancement : dev/test package=toolbox file=CompositionLean
 */
class CompositionLeanTest extends \dev\TransactionTest {

	const SEASON = 2026;

	const PRICE = 10.0;

	private \user\User $eUserInitial;

	protected function getTransactionModel(): \ModuleModel {
		return CompositionPlan::model();
	}

	public function setUp(): void {

		parent::setUp();

		$this->eUserInitial = \user\ConnectionLib::getOnline();

	}

	public function tearDown(): void {

		\user\ConnectionLib::setOnline($this->eUserInitial);

		parent::tearDown();

	}

	/**
	 * Une semaine est de soudure quand elle est sous le prix, au-delà de l'écart, et que son panier
	 * sert déjà toutes les espèces que le stock lui laisse ; une semaine sans panier l'est toujours, et
	 * rend ses candidates. Le stock filtre les candidates : l'espèce 2, récoltée la seule semaine 20
	 * et sans garde, n'est plus candidate ensuite.
	 */
	public function testWeeks(): void {

		$weeks = [20, 21, 22, 23, 24];
		$source = $this->getSource([1 => 0.9, 2 => 0.85], $weeks, [1 => 0, 2 => 0]);

		$stock = $this->getStock([
			1 => [20 => 100.0, 21 => 100.0, 22 => 100.0, 23 => 100.0],
			2 => [20 => 100.0],
		]);

		$cCompositionWeek = new \Collection([
			$this->getWeek(20, TRUE, 2.0, [1 => 1.0, 2 => 1.0]),
			$this->getWeek(21, TRUE, 0.0, []),
			$this->getWeek(22, FALSE, 9.8, [1 => 1.0]),
			$this->getWeek(23, TRUE, 12.0, [1 => 1.0]),
		]);

		$lean = CompositionLib::getLeanWeeks($weeks, self::SEASON, self::PRICE, $cCompositionWeek, NULL, $source, [], $stock);

		$this->assertEquals([20 => [1, 2], 24 => []], $lean, 'la semaine 20 sert ses deux candidates sous le prix, la semaine 24 n\'a pas de panier ; 21 laisse une candidate, 22 est dans l\'écart, 23 au-dessus du prix');

		$this->assertEquals([], CompositionLib::getLeanWeeks($weeks, self::SEASON, self::PRICE, $cCompositionWeek, NULL, $source, [], []), 'sans stock, sous la communauté, pas de soudure');

		$this->assertEquals([20 => [2], 21 => [], 24 => []], CompositionLib::getLeanWeeks($weeks, self::SEASON, self::PRICE, $cCompositionWeek, [2 => $weeks], $source, [], $stock), 'une espèce retirée, sans saison, n\'est pas candidate : la semaine 21 n\'en laisse plus aucune');

	}

	/**
	 * Ce qu'une semaine prend au stock n'est plus là la semaine suivante : 10 kilos récoltés en
	 * semaine 20 et gardés une semaine, qu'un kilo par panier pour dix paniers vide, laissent la
	 * semaine 21 sans candidate. Le pendant : un demi-kilo en laisse assez pour que la semaine 21, qui
	 * ne le sert pas, ne soit pas de soudure.
	 */
	public function testWeeksTakeStock(): void {

		$weeks = [20, 21];
		$source = $this->getSource([1 => 0.9], $weeks, [1 => 1]);

		$stock = $this->getStock([1 => [20 => 10.0]]);

		$lean = fn(float $quantity) => CompositionLib::getLeanWeeks($weeks, self::SEASON, self::PRICE, new \Collection([
			$this->getWeek(20, FALSE, 9.8, [1 => $quantity]),
			$this->getWeek(21, TRUE, 0.0, []),
		]), NULL, $source, [], $stock);

		$this->assertEquals([21 => []], $lean(1.0), 'la semaine 20 vide le lot');
		$this->assertEquals([], $lean(0.5), 'la semaine 20 en laisse la moitié');

	}

	/**
	 * Une déclinaison (r71, décision 1 du coordinateur sous mandat) puise dans le stock de sa
	 * référence, au poids de sa propre ligne : 10 kilos de l'espèce 1, qu'un demi-kilo de l'espèce 1 et
	 * un demi-kilo de sa déclinaison 3 par panier vident en semaine 20, ne laissent rien ni à l'une ni à
	 * l'autre en semaine 21. Le pendant : l'espèce 1 seule en laisse la moitié, aux deux.
	 */
	public function testWeeksTakeStockDeclension(): void {

		$weeks = [20, 21];
		$source = $this->getSource([1 => 0.9, 3 => 0.9], $weeks, [1 => 1, 3 => 1]);

		$stock = $this->getStock([1 => [20 => 10.0]]);
		$stock['kilos'][3] = 1.0;
		$stock['shared'] = [3 => 1];

		$lean = fn(array $lines) => CompositionLib::getLeanWeeks($weeks, self::SEASON, self::PRICE, new \Collection([
			$this->getWeek(20, FALSE, 9.8, $lines),
			$this->getWeek(21, TRUE, 0.0, []),
		]), NULL, $source, [], $stock);

		$this->assertEquals([21 => []], $lean([1 => 0.5, 3 => 0.5]), 'les deux lignes vident le même lot');
		$this->assertEquals([], $lean([1 => 0.5]), 'la référence seule en laisse la moitié');

	}

	/**
	 * Une période est une suite de semaines de soudure parmi les semaines livrées : une semaine livrée
	 * hors soudure la coupe, une semaine non livrée ne la coupe pas.
	 */
	public function testPeriods(): void {

		$this->assertEquals([[3, 4], [6], [9]], CompositionLib::getLeanPeriods([3 => [], 4 => [], 6 => [], 9 => []], [9, 3, 4, 5, 6, 8]), 'coupées par 5 et 8');
		$this->assertEquals([[3, 5]], CompositionLib::getLeanPeriods([3 => [], 5 => []], [3, 5]), 'la semaine 4 n\'est pas livrée');
		$this->assertEquals([], CompositionLib::getLeanPeriods([], [3, 5]), 'aucune soudure');

	}

	/**
	 * Les espèces qu'une garde plus longue ferait durer jusqu'à la dernière semaine d'une période,
	 * de la plus proche à la plus lointaine, cinq au plus. Ni une espèce retirée, ni une espèce sans
	 * garde ou sans fiche dans la structure, ni une espèce que sa garde couvre déjà, ni une espèce
	 * récoltée seulement après.
	 */
	public function testStretch(): void {

		$copy = fn(int $id) => new \plant\Plant(['id' => $id]);

		$storage = [
			1 => ['weeks' => 4, 'plant' => $copy(101)],
			2 => ['weeks' => 2, 'plant' => $copy(102)],
			3 => ['weeks' => 0, 'plant' => $copy(103)],
			4 => ['weeks' => 2, 'plant' => new \plant\Plant()],
			5 => ['weeks' => 8, 'plant' => $copy(105)],
			6 => ['weeks' => 2, 'plant' => $copy(106)],
			7 => ['weeks' => 2, 'plant' => $copy(107)],
		];

		$harvestWeeks = [
			1 => [1, 4],
			2 => [5, 20],
			3 => [1],
			4 => [1],
			5 => [4],
			6 => [11],
			8 => [1],
		];

		// Toutes les espèces portent une saison : une espèce retirée est celle qui n'en a pas.
		$windows = array_fill_keys(range(1, 8), [1]);

		$this->assertEquals([
			['species' => 1, 'weeks' => 4, 'needed' => 8],
			['species' => 2, 'weeks' => 2, 'needed' => 7],
		], CompositionLib::getStretch($harvestWeeks, $storage, $windows, 12), 'quatre semaines de plus avant cinq, la récolte de la semaine 20 ne compte pas ; la 5 et la 6 sont déjà couvertes, la 3 sans garde, la 4 sans fiche, la 7 sans récolte, la 8 sans garde');

		$this->assertEquals([
			['species' => 2, 'weeks' => 2, 'needed' => 7],
		], CompositionLib::getStretch($harvestWeeks, $storage, array_diff_key($windows, [1 => TRUE]), 12), 'une espèce retirée n\'y est pas');

		$this->assertEquals([
			['species' => 6, 'weeks' => 2, 'needed' => 3],
			['species' => 5, 'weeks' => 8, 'needed' => 10],
			['species' => 1, 'weeks' => 4, 'needed' => 10],
			['species' => 2, 'weeks' => 2, 'needed' => 9],
		], CompositionLib::getStretch($harvestWeeks, $storage, $windows, 14), 'jusqu\'à la semaine 14, la 6 et la 5 entrent, les plus proches');

		$many = [];
		$harvestMany = [];

		foreach(range(1, 7) as $speciesId) {
			$many[$speciesId] = ['weeks' => 1, 'plant' => $copy(100 + $speciesId)];
			$harvestMany[$speciesId] = [$speciesId];
		}

		$this->assertEquals([7, 6, 5, 4, 3], array_column(CompositionLib::getStretch($harvestMany, $many, $windows, 10), 'species'), 'cinq au plus, les plus proches');
		$this->assertEquals(CompositionLib::STRETCH_MAX, 5, 'la limite du menu');

	}

	/**
	 * Un plan de la saison 2099, sous la communauté : pas de soudure, et `getLeanChanges()` ne rend rien
	 * de plus que ce qu'on lui donne. Aucune culture ne date d'espèce en 2099 : la synchronisation est
	 * refusée, son menu ne propose rien, et le plan reste sans soudure. Puis toutes les semaines
	 * livrées en soudure, en une seule période, sans espèce à conserver plus : l'écran en montre le
	 * bloc, la bande et les portes de chaque carte, comme au rendu complet.
	 */
	public function testScreen(): void {

		$eStructure = $this->createPlan();

		if($eStructure->empty()) {
			return;
		}

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);
		$eCompositionFormula = $eCompositionPlan['eCompositionFormula'];

		$this->assertEquals(['weeks' => [], 'periods' => []], $eCompositionFormula['lean'], 'sous la communauté, pas de soudure');

		$changes = ['outer' => ['#a' => 'b'], 'attributes' => []];
		$this->assertEquals($changes, new CompositionUi()->getLeanChanges($eCompositionPlan, $changes), 'sous la communauté, aucun geste ne touche à la soudure');

		// Le plan de culture 2099 ne date aucune espèce : le deck n'en liste aucune, un envoi forgé est
		// refusé et le plan reste sous la communauté (arbitrage r27 du 28/09, r51b).
		$eCompositionPlanReference = CompositionPlanLib::getByStructure($eStructure);
		$eCompositionPlanReference['structure'] = $eStructure;

		$fw = new \FailWatch();

		CompositionPlanLib::synchronize($eCompositionPlanReference, [CompositionSpeciesLib::getByPlan($eCompositionPlanReference)->first()['id']]);

		$this->assertEquals(['CompositionPlan::synchronizeListed'], array_column($fw->get(), 1), 'une espèce que le plan de culture ne date pas ne s\'envoie pas');
		$this->assertEquals(CompositionPlan::COMMUNITY, CompositionPlanLib::getByStructure($eStructure)['reference'], 'le plan reste sous la communauté');

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);
		$eCompositionFormula = $eCompositionPlan['eCompositionFormula'];

		$synchronization = CompositionPlanLib::getSynchronization($eCompositionPlanReference, CompositionSpeciesLib::getByPlan($eCompositionPlanReference));

		$this->assertEquals([0, 0], [$synchronization['plants'], $synchronization['custom']], 'aucune espèce à cocher, ni aucune réglée à la main');
		$this->assertTrue($synchronization['cCompositionSpecies']->empty(), 'le deck ne liste rien');

		$this->assertEquals(['weeks' => [], 'periods' => []], $eCompositionFormula['lean'], 'toujours pas de soudure');

		$weeks = $eCompositionPlan['weeks'];
		sort($weeks);

		// Toutes les semaines livrées en soudure, sans espèce ni rien à conserver plus, comme
		// `getLean()` les rendrait sur un plan synchronisé dont aucune espèce datée ne donne rien.
		$eCompositionPlan['reference'] = CompositionPlan::CULTIVATION;
		$eCompositionPlan['climate'] = NULL;
		$eCompositionFormula['lean'] = [
			'weeks' => array_fill_keys($weeks, ['cCompositionSpecies' => new \Collection(), 'period' => 0]),
			'periods' => [['weeks' => $weeks, 'stretch' => []]],
			'baskets' => $eCompositionFormula['cCompositionWeek']->count(),
		];

		$changes = new CompositionUi()->getLeanChanges($eCompositionPlan, ['outer' => [], 'attributes' => []]);

		// La réponse ciblée rend ce que le rendu complet de chaque carte porte : même bande, même classe.
		foreach($weeks as $week) {

			$card = new CompositionUi()->getWeekCard($eCompositionPlan, $eCompositionFormula, $week);

			$this->assertTrue(str_contains($card, $changes['outer']['#toolbox-composition-card-lean-'.$week]), 'la bande de la semaine '.$week.' comme au rendu complet');
			$this->assertTrue(str_starts_with($card, '<div class="'.$changes['attributes']['#toolbox-composition-card-'.$week]['class'].'" id="toolbox-composition-card-'.$week.'">'), 'la classe de la semaine '.$week.' comme au rendu complet');

		}

		$block = $changes['outer']['#toolbox-composition-lean'];
		$from = min($weeks);
		$to = max($weeks);

		$this->assertTrue(str_contains($block, 'post-from="'.$from.'" post-to="'.$to.'"'), 'la période postée de sa première à sa dernière semaine');

		$card = $changes['outer']['#toolbox-composition-card-lean-'.$from];

		$this->assertTrue(str_contains($card, 'post-from="'.$from.'" post-to="'.$from.'"'), 'la semaine seule postée');


		// Une période de deux semaines, une espèce à conserver plus, et une seule espèce que le plan
		// donne encore : le titre, le menu et la cause de chaque forme.
		$eCompositionFormula['lean'] = [
			'weeks' => [
				$weeks[0] => ['cCompositionSpecies' => new \Collection([1 => new CompositionSpecies(['id' => 1, 'name' => NULL, 'plant' => new \plant\Plant(['id' => 1, 'name' => 'Oignon'])])]), 'period' => 0],
				$weeks[1] => ['cCompositionSpecies' => new \Collection(), 'period' => 0],
			],
			'periods' => [
				['weeks' => [$weeks[0], $weeks[1]], 'stretch' => [['species' => new CompositionSpecies(['id' => 2, 'name' => NULL, 'plant' => new \plant\Plant(['id' => 2, 'name' => 'Betterave'])]), 'copy' => new \plant\Plant(['id' => 102]), 'weeks' => 12, 'needed' => 17]]],
			],
		];

		$changes = new CompositionUi()->getLeanChanges($eCompositionPlan, ['outer' => [], 'attributes' => []]);

		$block = $changes['outer']['#toolbox-composition-lean'];

		$this->assertTrue(str_contains($block, 'href="/plant/plant:update?id=102&origin=composition"'), 'la fiche de la copie');
		$this->assertEquals('<div class="toolbox-composition-card-lean" id="toolbox-composition-card-lean-'.$weeks[2].'"></div>', $changes['outer']['#toolbox-composition-card-lean-'.$weeks[2]], 'hors soudure, la bande vide');

	}

	/**
	 * « Retirer la semaine », comme `composition.p.php` (`doRemoveWeeks`) : les semaines de `from` à
	 * `to` sortent du plan et de toutes ses tailles, les autres restent. Retirer toutes les semaines
	 * est refusé par le contrôle du plan, comme dans le panneau des semaines livrées.
	 */
	public function testRemoveWeeks(): void {

		$eStructure = $this->createPlan();

		if($eStructure->empty()) {
			return;
		}

		$eCompositionPlan = CompositionPlanLib::getByStructure($eStructure);
		$weeks = $eCompositionPlan['weeks'];
		sort($weeks);

		$from = $weeks[2];
		$to = $weeks[4];

		$this->assertFalse($this->removeWeeks($eStructure, $from, $to)->ko(), 'aucun refus');

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);

		$this->assertEquals(array_values(array_diff($weeks, [$weeks[2], $weeks[3], $weeks[4]])), $eCompositionPlan['weeks'], 'trois semaines de moins');

		foreach($eCompositionPlan['cCompositionFormula'] as $eCompositionFormula) {
			$this->assertEquals([], array_intersect([$weeks[2], $weeks[3], $weeks[4]], CompositionWeekLib::getByFormulaWithLines($eCompositionFormula, CompositionSpeciesLib::getByPlan($eCompositionPlan))->getColumn('week')), 'plus de panier ces semaines-là, '.$eCompositionFormula['name']);
		}

		$this->assertTrue($this->removeWeeks($eStructure, 1, 53)->has('CompositionPlan::weeks.check'), 'un plan sans semaine est refusé');
		$this->assertEquals(array_values(array_diff($weeks, [$weeks[2], $weeks[3], $weeks[4]])), CompositionPlanLib::getByStructure($eStructure)['weeks'], 'et rien n\'a changé');

	}

	/**
	 * Le corps de `doRemoveWeeks`, sans la vue : rend le `FailWatch` du contrôle, le plan n'étant
	 * écrit que s'il n'a rien refusé.
	 */
	private function removeWeeks(\structure\Structure $eStructure, int $from, int $to): \FailWatch {

		$eCompositionPlan = CompositionPlanLib::getByStructure($eStructure);
		$eCompositionPlan['structure'] = $eStructure;

		$fw = new \FailWatch();

		$eCompositionPlan->build(['weeks'], [
			'weeks' => array_values(array_filter($eCompositionPlan['weeks'], fn(int $week) => ($week < $from or $week > $to))),
		]);

		if($fw->ko() === FALSE) {
			CompositionPlanLib::update($eCompositionPlan, ['weeks']);
		}

		return $fw;

	}

	/**
	 * Un plan de deux tailles sur la saison 2099, pour une structure qui n'en a pas et dont
	 * l'administrateur est connecté, sous la communauté.
	 */
	private function createPlan(): \structure\Structure {

		$eMember = \structure\Member::model()
			->select(['user' => new \Sql('m1.user', 'element'), 'structure' => new \Sql('m1.structure', 'element')])
			->join(\structure\Role::model(), 'm1.role = m2.id')
			->join(CompositionPlan::model(), 'm1.structure = m3.structure', 'LEFT')
			->where('m2.admin', TRUE)
			->where('m1.status', \structure\Member::IN)
			->where('m1.structureStatus', \structure\Member::ACTIVE)
			->where('m3.id IS NULL')
			->sort(['m1.structure' => SORT_ASC])
			->get();

		if($eMember->empty()) {
			$this->assertFail('aucune structure sans plan n\'a d\'administrateur : le contrôle ne verrait rien');
			return new \structure\Structure();
		}

		\user\ConnectionLib::setOnline($eMember['user']);

		$eStructure = \structure\StructureLib::getById($eMember['structure']['id']);

		$eCompositionPlan = CompositionPlanLib::getForStart($eStructure, 2099);
		$eCompositionPlan['weeks'] = range(20, 27);
		$eCompositionPlan['reference'] = CompositionPlan::COMMUNITY;
		$eCompositionPlan['climate'] = NULL;

		$eCompositionPlan['cCompositionSpecies']->map(fn(CompositionSpecies $e) => $e['status'] = CompositionSpecies::NATURAL);

		$eCompositionPlan['cCompositionFormula'] = new \Collection([
			new CompositionFormula(['plan' => $eCompositionPlan, 'name' => 'Le panier', 'price' => 16.0, 'baskets' => 10, 'gap' => 5, 'version' => 0]),
			new CompositionFormula(['plan' => $eCompositionPlan, 'name' => 'Le grand panier', 'price' => 24.0, 'baskets' => 10, 'gap' => 5, 'version' => 0]),
		]);

		CompositionPlanLib::create($eCompositionPlan);

		return $eStructure;

	}

	/**
	 * Une semaine écrite, construite à la main : ses lignes `[id d'espèce => quantité]`, dans
	 * l'unité 1 de `getSource()`.
	 */
	private function getWeek(int $week, bool $gap, float $value, array $lines): CompositionWeek {

		$cCompositionLine = new \Collection();

		foreach($lines as $speciesId => $quantity) {
			$cCompositionLine[] = new CompositionLine([
				'species' => new CompositionSpecies(['id' => $speciesId]),
				'unit' => new \product\Unit(['id' => 1]),
				'quantity' => $quantity,
			]);
		}

		return new CompositionWeek([
			'week' => $week,
			'gap' => $gap,
			'value' => $value,
			'cCompositionLine' => $cCompositionLine,
		]);

	}

	/**
	 * Le stock d'une taille de dix paniers, dont les autres tailles ne prennent rien, pour les
	 * récoltes `[id d'espèce => [semaine => kilos]]`, un kilo par unité.
	 */
	private function getStock(array $harvests): array {

		$planned = [];
		$kilos = [];

		foreach($harvests as $plantId => $harvest) {
			$planned[$plantId] = ['cultivations' => 1, 'incomplete' => 0, 'harvest' => $harvest, 'weeks' => array_keys($harvest), 'weeksIncomplete' => [], 'carried' => [], 'carriedIncomplete' => []];
			$kilos[$plantId] = 1.0;
		}

		return [
			'planned' => $planned,
			'shared' => [],
			'needs' => ['needs' => [], 'unknown' => []],
			'kilos' => $kilos,
			'baskets' => 10,
		];

	}

	/**
	 * Un relevé construit à la main, comme `CompositionDerivedTest::getSource()` : une unité par
	 * espèce, 1 kilo de référence au pas de 0,1, à 1 €, et la garde `$storage` de chaque espèce.
	 */
	private function getSource(array $shares, array $weeks, array $storage): array {

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
			'storage' => [],
		];

		foreach($shares as $plantId => $share) {

			$source['units'][$plantId] = ['unit' => 1, 'quantity' => 1.0, 'minimum' => NULL, 'step' => 0.1, 'community' => NULL];
			$source['frequency']['byYear'][$plantId] = $share;
			$source['organs'][$plantId] = NULL;
			$source['storage'][$plantId] = ['weeks' => $storage[$plantId]];

			foreach($weeks as $week) {

				$source['availability'][$week][$plantId] = 10;
				$source['prices'][$week][$plantId][1] = [
					'contributors' => 10,
					'median' => 1.0,
					'low' => 1.0,
					'high' => 1.0,
				];

			}

		}

		return $source;

	}

}
?>
