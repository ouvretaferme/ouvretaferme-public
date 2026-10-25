<?php
namespace toolbox;

/**
 * Ce que l'écran « Paniers de l'année » lit d'un plan : les semaines et leurs lignes
 * valorisées, le résumé d'une formule, la grille espèces × semaines, les périodes de la frise et le compte des
 * espèces cultivées.
 *
 * L'écran ne calcule rien (`CompositionUi` ne fait que placer), donc tout ce qu'il affiche de
 * chiffré se joue ici : une valeur de semaine fausse, un écart mal compté ou une espèce rangée sous
 * le mauvais organe se verraient à l'écran sans qu'aucune erreur ne le signale.
 *
 * Les lignes sont écrites telles que `CompositionLib::compose()` les écrit, par les Lib et sans
 * `build()` : c'est l'état que la proposition produit, et ce sont ses lectures qu'on éprouve ici.
 * Les constructions refusées sont l'affaire de `CompositionTest`.
 *
 * Isolation : une transaction par méthode, annulée en tearDown(). Les ventes que le départ lit
 * (`CompositionLib::getSales()`) sont semées dans leur cache sous la clé du jour, et ces clés
 * effacées au même moment, le cache n'étant pas dans la transaction.
 *
 * Lancement : dev/test package=toolbox file=CompositionReading
 */
class CompositionReadingTest extends \Test {

	private \structure\Structure $eStructure;
	private \product\Unit $eUnit;

	private array $structuresSeeded = [];

	public function setUp(): void {

		CompositionPlan::model()->beginTransaction();

		$this->eStructure = \structure\Structure::model()
			->select(['id'])
			->whereStatus(\structure\Structure::ACTIVE)
			->sort(['id' => SORT_ASC])
			->get();

		$this->eUnit = \product\Unit::model()
			->select(['id', 'singular'])
			->whereStructure(NULL)
			->sort(['id' => SORT_ASC])
			->get();

	}

	public function tearDown(): void {

		CompositionPlan::model()->rollBack();

		foreach($this->structuresSeeded as $structureId) {
			\Cache::redis()->delete($this->getSalesKey(new \structure\Structure(['id' => $structureId])));
		}

		$this->structuresSeeded = [];

		$this->resetSales();

	}

	/**
	 * Les semaines se regroupent en périodes continues, quel que soit l'ordre reçu, et une semaine
	 * isolée fait une période à elle seule.
	 */
	public function testGetBands(): void {

		$this->assertEquals([[1, 3], [7, 7], [9, 10]], CompositionLib::getBands([9, 2, 7, 1, 10, 3]), 'trois périodes');
		$this->assertEquals([[1, 52]], CompositionLib::getBands(range(1, 52)), 'l\'année entière');
		$this->assertEquals([], CompositionLib::getBands([]), 'aucune semaine');

	}

	/**
	 * Une saison bouche ses trous de quatre semaines au plus (r61, `SEASON_GAP_FILLED`), et garde
	 * en deux barres ce qui s'écarte davantage, une vraie seconde saison. Un morceau de moins de trois
	 * semaines se retire une fois les trous bouchés (r72, `CalendarProjectionAiLib::PIECE_MIN`), sauf
	 * quand la saison n'a que des morceaux courts.
	 */
	public function testGetWindowWeeksFilled(): void {

		$this->assertEquals(4, CompositionLib::SEASON_GAP_FILLED, 'la borne que les cas suivants encadrent');
		$this->assertEquals([[10, 21]], CompositionLib::getWindowWeeksFilled([10, 11, 12, 17, 18, 21]), 'un trou de 4 semaines, puis de 2, se bouchent');
		$this->assertEquals([[10, 12], [18, 20]], CompositionLib::getWindowWeeksFilled([19, 10, 11, 12, 18, 20, 12]), 'un trou de 5 semaines se garde, quel que soit l\'ordre reçu');
		$this->assertEquals([[1, 9], [22, 30]], CompositionLib::getWindowWeeksFilled(array_merge(range(1, 9), range(22, 30))), 'une seconde saison à 12 semaines de la première reste à part');
		$this->assertEquals([[14, 40]], CompositionLib::getWindowWeeksFilled(array_merge([1], range(14, 40))), 'une semaine isolée devant la saison se retire, le cas du Chou pé-tsaï de la 7');
		$this->assertEquals([[10, 12]], CompositionLib::getWindowWeeksFilled([10, 11, 12, 18, 19]), 'deux semaines à part se retirent, trois restent');
		$this->assertEquals([[5, 5]], CompositionLib::getWindowWeeksFilled([5]), 'une semaine seule, toute la saison, se garde');
		$this->assertEquals([[5, 6], [20, 20]], CompositionLib::getWindowWeeksFilled([5, 6, 20]), 'des morceaux tous courts se gardent');
		$this->assertEquals(NULL, CompositionLib::getWindowWeeksFilled([]), 'aucune semaine, aucune saison');
		$this->assertEquals([[1, 2], [40, 52]], CompositionLib::getWindowWeeksFilled(array_merge([1, 2], range(40, 52))), 'une saison qui passe le nouvel an est d\'un seul tenant, ses deux semaines de janvier se gardent');
		$this->assertEquals([[1, 12], [52, 52]], CompositionLib::getWindowWeeksFilled(array_merge(range(1, 12), [52])), 'comme la dernière semaine de décembre devant une saison qui part de la semaine 1');
		$this->assertEquals([[14, 51]], CompositionLib::getWindowWeeksFilled(array_merge([1], range(14, 51))), 'une saison qui s\'arrête avant la dernière semaine ne prolonge pas la semaine 1');

	}

	/**
	 * La valeur d'une semaine somme ses lignes valorisées, et une ligne sans prix compte pour zéro
	 * sans faire tomber la somme. L'écart se juge sur le prix et l'écart maximum de la formule : à
	 * 3 € visés, 3 € ne s'écarte pas ; à 18 €, si ; à 3,10 €, 10 centimes passent à 5 % (15,5
	 * centimes tolérés) et pas à 2 % (6 centimes).
	 */
	public function testWeekValue(): void {

		$cPlant = $this->getPlants(\plant\Plant::LEAF, 2);

		$eCompositionPlan = $this->createPlan([14]);
		$eCompositionFormula = $this->createFormula($eCompositionPlan, 3.0);
		$eCompositionWeek = $this->createWeek($eCompositionFormula, 14);

		[$eCompositionSpeciesFirst, $eCompositionSpeciesLast] = $this->createSpecies($eCompositionPlan, $cPlant);

		$this->createLine($eCompositionWeek, $eCompositionSpeciesFirst, 2.0, 1.5);
		$this->createLine($eCompositionWeek, $eCompositionSpeciesLast, 1.0, NULL);

		$cCompositionWeek = CompositionWeekLib::getByFormulaWithLines($eCompositionFormula, CompositionSpeciesLib::getByPlan($eCompositionPlan));

		$this->assertEquals(1, $cCompositionWeek->count(), 'une semaine');

		$eCompositionWeekRead = $cCompositionWeek->first();

		$this->assertEquals(2, $eCompositionWeekRead['cCompositionLine']->count(), 'deux lignes');
		$this->assertEquals(3.0, $eCompositionWeekRead['value'], 'valeur, la ligne sans prix comptant pour zéro');
		$this->assertEquals(FALSE, $eCompositionWeekRead['gap'], 'pas d\'écart à 3 € visés');

		$values = [];
		foreach($eCompositionWeekRead['cCompositionLine'] as $eCompositionLine) {
			$values[] = $eCompositionLine['value'];
		}
		sort($values);
		$this->assertEquals([NULL, 3.0], $values, 'valeur par ligne, nulle sans prix');

		$eCompositionLine = $eCompositionWeekRead['cCompositionLine']->first();
		$this->assertTrue($eCompositionLine['species']['plant']['name'] !== NULL, 'nom de l\'espèce chargé');
		$this->assertEquals($this->eUnit['singular'], $eCompositionLine['unit']['singular'], 'unité chargée');

		$eCompositionFormula['price'] = 18.0;
		$this->assertEquals(TRUE, CompositionWeekLib::getByFormulaWithLines($eCompositionFormula, CompositionSpeciesLib::getByPlan($eCompositionPlan))->first()['gap'], 'écart à 18 € visés');

		$eCompositionFormula['price'] = 3.1;
		$this->assertEquals(FALSE, CompositionWeekLib::getByFormulaWithLines($eCompositionFormula, CompositionSpeciesLib::getByPlan($eCompositionPlan))->first()['gap'], 'pas d\'écart à 3,10 € et 5 %');

		$eCompositionFormula['gap'] = 2;
		$this->assertEquals(TRUE, CompositionWeekLib::getByFormulaWithLines($eCompositionFormula, CompositionSpeciesLib::getByPlan($eCompositionPlan))->first()['gap'], 'écart à 3,10 € et 2 %');

	}

	/**
	 * Le résumé d'une formule : le nombre de semaines, les bornes du nombre de lignes, et les semaines
	 * où la règle a relâché le plancher d'organes, une semaine qui le relâche pour deux organes ne
	 * comptant qu'une fois.
	 */
	public function testSummary(): void {

		$cPlant = $this->getPlants(\plant\Plant::ROOT, 3);

		$eCompositionPlan = $this->createPlan([14, 15, 16]);
		$eCompositionFormula = $this->createFormula($eCompositionPlan, 10.0);

		$species = $this->createSpecies($eCompositionPlan, $cPlant);

		$eCompositionWeek14 = $this->createWeek($eCompositionFormula, 14);
		$this->createLine($eCompositionWeek14, $species[0], 1.0, 10.0);

		$eCompositionWeek15 = $this->createWeek($eCompositionFormula, 15, relaxedFloor: [CompositionWeek::LEAF, CompositionWeek::FRUIT]);
		$this->createLine($eCompositionWeek15, $species[0], 1.0, 4.0);
		$this->createLine($eCompositionWeek15, $species[2], 1.0, 4.0);

		$eCompositionWeek16 = $this->createWeek($eCompositionFormula, 16, relaxedFloor: [CompositionWeek::LEAF]);
		foreach($species as $eCompositionSpecies) {
			$this->createLine($eCompositionWeek16, $eCompositionSpecies, 1.0, 4.0);
		}

		$summary = CompositionLib::getSummary(CompositionWeekLib::getByFormulaWithLines($eCompositionFormula, CompositionSpeciesLib::getByPlan($eCompositionPlan)));

		$this->assertEquals([
			'weeks' => 3,
			'linesMin' => 1,
			'linesMax' => 3,
			'weeksShort' => 2,
		], $summary, 'résumé');

		$this->assertEquals([
			'weeks' => 0,
			'linesMin' => NULL,
			'linesMax' => NULL,
			'weeksShort' => 0,
		], CompositionLib::getSummary(new \Collection()), 'formule sans semaine');

	}

	/**
	 * La grille range les espèces sous leur organe, feuille avant racine, l'espèce à la plus longue
	 * saison en tête de son groupe (r72), et compte les mises au panier du groupe. Un organe sans
	 * espèce servie ne paraît pas.
	 */
	public function testGrid(): void {

		$cPlantLeaf = $this->getPlants(\plant\Plant::LEAF, 2);
		$cPlantRoot = $this->getPlants(\plant\Plant::ROOT, 1);

		$eCompositionPlan = $this->createPlan([14, 15]);
		$eCompositionFormula = $this->createFormula($eCompositionPlan, 10.0);

		[$eCompositionSpeciesLeafOnce, $eCompositionSpeciesLeafTwice] = $this->createSpecies($eCompositionPlan, $cPlantLeaf);
		[$eCompositionSpeciesRoot] = $this->createSpecies($eCompositionPlan, $cPlantRoot);

		$eCompositionWeek14 = $this->createWeek($eCompositionFormula, 14);
		$this->createLine($eCompositionWeek14, $eCompositionSpeciesRoot, 1.0, 2.0);
		$this->createLine($eCompositionWeek14, $eCompositionSpeciesLeafOnce, 1.0, 2.0);
		$this->createLine($eCompositionWeek14, $eCompositionSpeciesLeafTwice, 1.0, 2.0);

		$eCompositionWeek15 = $this->createWeek($eCompositionFormula, 15);
		$this->createLine($eCompositionWeek15, $eCompositionSpeciesLeafTwice, 1.0, 2.0);

		// La feuille servie une fois a la saison la plus longue : elle passe devant celle que la
		// proposition sert deux fois, dont la saison ne couvre qu'une semaine.
		$windows = [
			$eCompositionSpeciesLeafOnce['id'] => [14, 15],
			$eCompositionSpeciesLeafTwice['id'] => [15],
			$eCompositionSpeciesRoot['id'] => [14],
		];

		$cCompositionSpecies = CompositionSpeciesLib::getByPlan($eCompositionPlan);
		$grid = CompositionLib::getGrid(CompositionWeekLib::getByFormulaWithLines($eCompositionFormula, $cCompositionSpecies), 2099, $windows, $this->getSource([], []), $cCompositionSpecies);

		$this->assertEquals([\plant\Plant::LEAF, \plant\Plant::ROOT], array_column($grid, 'organ'), 'organes dans l\'ordre, sans le fruit');
		$this->assertEquals([3, 1], array_column($grid, 'served'), 'mises au panier par groupe');

		$this->assertEquals(
			[$eCompositionSpeciesLeafOnce['id'], $eCompositionSpeciesLeafTwice['id']],
			array_map(fn(array $row) => $row['species']['id'], $grid[0]['species']),
			'la plus longue saison en tête, pas la plus servie'
		);

		$this->assertEquals([14], array_keys($grid[0]['species'][0]['lines']), 'semaine de la feuille servie une fois');
		$this->assertEquals([14, 15], array_keys($grid[0]['species'][1]['lines']), 'semaines de la feuille servie deux fois');
		$this->assertEquals([14], array_keys($grid[1]['species'][0]['lines']), 'semaine de la racine');

		// À saison égale, le nom départage, quel que soit le nombre de lignes servies.
		$windows[$eCompositionSpeciesLeafTwice['id']] = [14, 15];

		$cCompositionSpecies = CompositionSpeciesLib::getByPlan($eCompositionPlan);
		$grid = CompositionLib::getGrid(CompositionWeekLib::getByFormulaWithLines($eCompositionFormula, $cCompositionSpecies), 2099, $windows, $this->getSource([], []), $cCompositionSpecies);

		$names = array_map(fn(array $row) => $row['species']->getLabel(), $grid[0]['species']);
		$namesSorted = $names;
		usort($namesSorted, fn(string $name1, string $name2) => \L::getCollator()->compare($name1, $name2));

		$this->assertEquals($namesSorted, $names, 'à saison égale, l\'ordre du nom');

	}

	/**
	 * La grille montre aussi la saison de chaque espèce : un rang par espèce candidate au moins une
	 * semaine, servie ou non, avec sa part de chaque semaine où elle est candidate, telle que la
	 * dette la cumule, et les semaines de sa saison, que la grille passe au vert. Une espèce décochée
	 * n'a pas de rang, et une semaine où l'espèce n'est pas candidate n'a pas de part.
	 */
	public function testGridWindow(): void {

		$cPlant = $this->getPlants(\plant\Plant::LEAF, 3);

		$eCompositionPlan = $this->createPlan([14, 15, 16]);
		$eCompositionFormula = $this->createFormula($eCompositionPlan, 10.0);

		[$eCompositionSpeciesServed, $eCompositionSpeciesSeason, $eCompositionSpeciesExcluded] = $this->createSpecies($eCompositionPlan, $cPlant);

		$eCompositionWeek14 = $this->createWeek($eCompositionFormula, 14);
		$this->createLine($eCompositionWeek14, $eCompositionSpeciesServed, 1.0, 2.0);

		$eCompositionWeek15 = $this->createWeek($eCompositionFormula, 15);
		$this->createLine($eCompositionWeek15, $eCompositionSpeciesServed, 1.0, 2.0);
		$this->createLine($eCompositionWeek15, $eCompositionSpeciesSeason, 1.0, 2.0);

		// La semaine 16 est livrée sans panier : aucune candidate, donc aucune fenêtre.
		$source = $this->getSource([
			$eCompositionSpeciesServed['id'] => 0.6,
			$eCompositionSpeciesSeason['id'] => 0.2,
			$eCompositionSpeciesExcluded['id'] => 0.9,
		], [14, 15]);

		// La seconde n'a que la semaine 14 dans sa saison, la décochée n'en a pas.
		$windows = [
			$eCompositionSpeciesServed['id'] => [14, 15, 16],
			$eCompositionSpeciesSeason['id'] => [14],
		];

		$cCompositionSpecies = CompositionSpeciesLib::getByPlan($eCompositionPlan);
		$grid = CompositionLib::getGrid(CompositionWeekLib::getByFormulaWithLines($eCompositionFormula, $cCompositionSpecies), 2099, $windows, $source, $cCompositionSpecies);

		$this->assertEquals([\plant\Plant::LEAF], array_column($grid, 'organ'), 'un seul organe');

		$rows = [];

		foreach($grid[0]['species'] as $row) {
			$rows[$row['species']['id']] = $row;
		}

		$this->assertEquals([$eCompositionSpeciesServed['id'], $eCompositionSpeciesSeason['id']], array_keys($rows), 'la plus servie en tête, la décochée absente');
		$this->assertEquals($eCompositionSpeciesSeason['plant']['name'], $rows[$eCompositionSpeciesSeason['id']]['species']->getLabel(), 'une espèce servie seulement là où elle n\'est pas candidate garde son nom');

		// Semaine 14, un panier d'une ligne, deux candidates à 0,6 et 0,2 : 0,75 et 0,25.
		// Semaine 15, un panier de deux lignes, une seule candidate : sa part plafonne à 1.
		$this->assertEquals([14 => 0.75, 15 => 1.0], array_map(fn(float $share) => round($share, 6), $rows[$eCompositionSpeciesServed['id']]['shares']), 'part de la servie');
		$this->assertEquals([14 => 0.25], array_map(fn(float $share) => round($share, 6), $rows[$eCompositionSpeciesSeason['id']]['shares']), 'part de la seconde, hors de sa fenêtre en 15');

		// Le vert ne suit que la saison, sur les semaines livrées qui ont un panier (r29, #87) : la
		// semaine 16 de la servie n'a pas de case, la 15 de la seconde, servie, reste hors saison.
		$this->assertEquals([14 => 14, 15 => 15], $rows[$eCompositionSpeciesServed['id']]['weeksSeason'], 'saison de la servie');
		$this->assertEquals([14 => 14], $rows[$eCompositionSpeciesSeason['id']]['weeksSeason'], 'saison de la seconde');
		$this->assertEquals([14 => 14, 15 => 15], $rows[$eCompositionSpeciesServed['id']]['open'], 'cases ouvertes de la servie');
		// Toute semaine livrée s'ouvre, de saison ou non (r29, #83) : la 15 de la seconde aussi.
		$this->assertEquals([14 => 14, 15 => 15], $rows[$eCompositionSpeciesSeason['id']]['open'], 'cases ouvertes de la seconde');

		// Sans unité au panier, l'espèce ne se vend pas (`isSold()`) : ses cases restent vertes, mais
		// aucune ne s'ouvre.
		unset($source['units'][$eCompositionSpeciesServed['id']]);

		$cCompositionSpecies = CompositionSpeciesLib::getByPlan($eCompositionPlan);
		$grid = CompositionLib::getGrid(CompositionWeekLib::getByFormulaWithLines($eCompositionFormula, $cCompositionSpecies), 2099, $windows, $source, $cCompositionSpecies);
		$rowUnsold = array_values(array_filter($grid[0]['species'], fn(array $row) => $row['species']['id'] === $eCompositionSpeciesServed['id']))[0];

		$this->assertEquals([14 => 14, 15 => 15], $rowUnsold['weeksSeason'], 'sans unité, toujours de saison');
		$this->assertEquals([], $rowUnsold['open'], 'mais fermée');

		$this->assertEquals([14, 15], array_keys($rows[$eCompositionSpeciesServed['id']]['lines']), 'semaines servies');
		$this->assertEquals([15], array_keys($rows[$eCompositionSpeciesSeason['id']]['lines']), 'servie hors de sa fenêtre, par une ligne gardée');

	}

	/**
	 * Une espèce qui n'est jamais servie a son rang quand même, et son nom et son organe sont lus à
	 * part, puisqu'aucune ligne ne les porte.
	 */
	public function testGridNeverServed(): void {

		$cPlant = $this->getPlants(\plant\Plant::ROOT, 2);

		$eCompositionPlan = $this->createPlan([14]);
		$eCompositionFormula = $this->createFormula($eCompositionPlan, 10.0);

		[$eCompositionSpeciesServed, $eCompositionSpeciesNever] = $this->createSpecies($eCompositionPlan, $cPlant);

		$eCompositionWeek14 = $this->createWeek($eCompositionFormula, 14);
		$this->createLine($eCompositionWeek14, $eCompositionSpeciesServed, 1.0, 2.0);

		$source = $this->getSource([$eCompositionSpeciesServed['id'] => 0.5, $eCompositionSpeciesNever['id'] => 0.5], [14]);

		$cCompositionSpecies = CompositionSpeciesLib::getByPlan($eCompositionPlan);
		$grid = CompositionLib::getGrid(CompositionWeekLib::getByFormulaWithLines($eCompositionFormula, $cCompositionSpecies), 2099, NULL, $source, $cCompositionSpecies);

		$this->assertEquals([\plant\Plant::ROOT], array_column($grid, 'organ'), 'rangée sous son organe');
		$this->assertEquals(1, $grid[0]['served'], 'une seule mise au panier');

		$row = $grid[0]['species'][1];

		$this->assertEquals($eCompositionSpeciesNever['id'], $row['species']['id'], 'la jamais servie après la servie');
		$this->assertEquals($eCompositionSpeciesNever['plant']['name'], $row['species']->getLabel(), 'son nom');
		$this->assertEquals([], array_keys($row['lines']), 'aucune semaine servie');
		$this->assertEquals([14 => 14], $row['weeksSeason'], 'sans saison réglée, sa semaine de candidate est de saison');

	}

	/**
	 * Le départ s'ouvre sans rien écrire : une seule taille, vide, et aucune semaine cochée. Le prix
	 * part vide lui aussi : c'est celui de la structure, qu'aucune médiane ne devine (revue v12).
	 */
	public function testStartWritesNothing(): void {

		$eStructure = $this->getStructureWithoutPlan();
		$counts = $this->countRows();

		$eCompositionPlan = CompositionPlanLib::getForStart($eStructure, 2026);

		$this->assertFalse($eCompositionPlan['restart'], 'une saison sans plan');
		$this->assertEquals([], $eCompositionPlan['weeks'], 'aucune semaine cochée');
		$this->assertEquals(1, $eCompositionPlan['cCompositionFormula']->count(), 'une taille');

		$eCompositionFormula = $eCompositionPlan['cCompositionFormula']->first();

		$this->assertEquals([NULL, NULL, NULL], [$eCompositionFormula['name'], $eCompositionFormula['price'], $eCompositionFormula['baskets']], 'ni nom, ni prix, ni nombre de paniers');
		$this->assertEquals(count(CompositionLib::getProposable(CompositionLib::getSeasonWeeks(), 2026, CompositionLib::getSource())), $eCompositionPlan['cCompositionSpecies']->count(), 'une espèce par espèce proposable');
		$this->assertEquals($counts, $this->countRows(), 'aucune ligne écrite par la lecture');

		$html = new CompositionStartUi()->getStart($eCompositionPlan);

		$libxmlErrors = libxml_use_internal_errors(TRUE);
		$document = new \DOMDocument();
		$document->loadHTML('<?xml encoding="utf-8"?>'.$html);
		libxml_clear_errors();
		libxml_use_internal_errors($libxmlErrors);
		$xpath = new \DOMXPath($document);

		// Au premier départ, le menu des saisons ne fait que recocher la liste : rouvrir le départ sur
		// une autre saison perdrait ce qui est tapé, et rien n'est encore écrit.
		$this->assertEquals(0, $xpath->query('//select[@id="toolbox-composition-start-cultivation"]/option[@data-url]')->length, 'aucune saison ne rouvre le départ');
		$this->assertEquals('1', $xpath->query('//*[@id="toolbox-composition-start"]')->item(0)->getAttribute('data-step'), 'ouvert sur la première étape');

	}

	/**
	 * Les cultures du départ : cochées depuis le plan de culture de la saison, toutes quand il est
	 * vide. Les deux cas se mesurent, pour que le contrôle ne passe pas sur une base où l'un manque.
	 * Le menu du départ propose les autres saisons cultivées, chacune avec les espèces qu'elle recoche.
	 *
	 * La structure ne vend rien ici : ce que ses ventes cochent est l'affaire de testStartSold().
	 */
	public function testStartCultivated(): void {

		$eStructure = $this->getStructureCultivating(2026);

		$this->setSales($eStructure, []);

		$cultivatedSeasons = CompositionLib::getCultivatedBySeason($eStructure);

		if(array_key_exists(2026, $cultivatedSeasons) === FALSE) {
			$this->assertFail('aucune structure ne cultive 2026 sans plan de paniers : le contrôle ne verrait rien');
			return;
		}

		$eCompositionPlan = CompositionPlanLib::getForStart($eStructure, 2098);

		$this->assertEquals(NULL, $eCompositionPlan['cultivated'], 'aucune culture en 2098');
		$this->assertEquals(0, $eCompositionPlan['cCompositionSpecies']->find(fn(CompositionSpecies $e) => $e['status'] === CompositionSpecies::EXCLUDED)->count(), 'toutes cochées sans plan de culture');

		$eCompositionPlan = CompositionPlanLib::getForStart($eStructure, 2026);

		$natural = $eCompositionPlan['cCompositionSpecies']
			->find(fn(CompositionSpecies $e) => $e['status'] === CompositionSpecies::NATURAL)
			->getKeys();

		$expected = array_values(array_intersect(array_keys($eCompositionPlan['cCompositionSpecies']->getArrayCopy()), $cultivatedSeasons[2026]));

		$this->assertEquals($expected, $natural, 'les cultures de la saison, et elles seules');
		$this->assertEquals(count($natural), $eCompositionPlan['cultivated'], 'le nombre annoncé');
		$this->assertTrue(count($natural) < $eCompositionPlan['cCompositionSpecies']->count(), 'des espèces décochées');

		// Le menu du départ : chaque saison cultivée, la plus récente d'abord, avec ses seules espèces
		// proposables, celles que le choix recochera.
		$seasons = array_keys($eCompositionPlan['cultivatedSeasons']);
		$proposable = array_keys($eCompositionPlan['cCompositionSpecies']->getArrayCopy());

		$descending = $seasons;
		rsort($descending);

		$this->assertEquals($descending, $seasons, 'la plus récente d\'abord');
		$this->assertEquals(array_keys($cultivatedSeasons), $seasons, 'une entrée par saison cultivée');
		$default = $eCompositionPlan['cultivatedSeasons'][2026];
		sort($default);
		sort($natural);

		$this->assertEquals($natural, $default, 'la saison du départ, cochée par défaut');

		foreach($eCompositionPlan['cultivatedSeasons'] as $season => $plantIds) {
			$this->assertEquals(array_values(array_intersect($cultivatedSeasons[$season], $proposable)), $plantIds, 'saison '.$season.' : ses espèces proposables');
		}

		$eCompositionPlan = CompositionPlanLib::getForStart($eStructure, 2098);

		$this->assertEquals([], $eCompositionPlan['cultivatedSeasons'][2098], 'la saison du départ figure au menu, même sans culture');
		$this->assertEquals(array_keys($cultivatedSeasons), array_slice(array_keys($eCompositionPlan['cultivatedSeasons']), 1), 'suivie des saisons cultivées');

	}

	/**
	 * Le départ sans plan de culture pour la saison coche les espèces que la structure a vendues,
	 * dès qu'elles sont au moins `CompositionLib::LINES_MIN` parmi les proposables (lot L8b). En
	 * deçà, ou avec un plan de culture, ou sur un plan qui suit déjà la saison, les ventes ne jouent
	 * pas. Le seuil se compte sur les seules proposables : deux ventes d'espèces qu'aucun panier ne
	 * propose ne comptent pas.
	 */
	public function testStartSold(): void {

		$eStructure = $this->getStructureCultivating(2026);

		$this->setSales($eStructure, []);

		$proposable = array_keys(CompositionPlanLib::getForStart($eStructure, 2098)['cCompositionSpecies']->getArrayCopy());
		$others = array_slice(array_diff(\plant\Plant::model()->whereStructure(NULL)->whereReference(NULL)->getColumn('id'), $proposable), 0, 2);

		$this->assertTrue(count($proposable) > CompositionLib::LINES_MIN and count($others) === 2, 'des espèces proposables et d\'autres qui ne le sont pas : le contrôle verrait le seuil');

		$sold = array_slice($proposable, 1, CompositionLib::LINES_MIN);
		$soldUnder = array_slice($sold, 0, CompositionLib::LINES_MIN - 1);

		$this->setSales($eStructure, array_fill_keys(array_merge($soldUnder, $others), []));

		$eCompositionPlan = CompositionPlanLib::getForStart($eStructure, 2098);

		$this->assertEquals([], $eCompositionPlan['sold'], 'trois vendues proposables et deux qui ne le sont pas : sous le seuil');
		$this->assertEquals(0, $this->countExcluded($eCompositionPlan), 'toutes cochées sous le seuil');

		$this->setSales($eStructure, array_fill_keys(array_merge($others, $sold), ['kg' => 1.0]));

		$eCompositionPlan = CompositionPlanLib::getForStart($eStructure, 2098);

		$natural = $this->getNatural($eCompositionPlan);
		$soldSorted = $sold;
		sort($soldSorted);

		$this->assertEquals($soldSorted, $natural, 'les espèces vendues, et elles seules');
		$this->assertEquals($soldSorted, $this->getSorted($eCompositionPlan['sold']), 'annoncées au menu des saisons');
		$this->assertEquals(NULL, $eCompositionPlan['cultivated'], 'aucune depuis le plan de culture');

		$select = new \ReflectionMethod(CompositionStartUi::class, 'getCultivatedSeasons')->invoke(new CompositionStartUi(), new \util\FormUi(), $eCompositionPlan);
		$this->assertTrue(str_contains($select, 'data-sold="'.implode(' ', $eCompositionPlan['sold']).'"'), 'le menu des saisons porte les espèces vendues');

		$eCompositionPlan = CompositionPlanLib::getForStart($eStructure, 2026);

		$this->assertEquals([], $eCompositionPlan['sold'], 'un plan de culture ignore les ventes');
		$this->assertEquals($this->getSorted($eCompositionPlan['cultivatedSeasons'][2026]), $this->getNatural($eCompositionPlan), 'les cultures de la saison, comme sans ventes');

		$this->setSales($eStructure, []);
		$eCompositionPlanStarted = $this->createStarted($eStructure, 2098);
		$this->setSales($eStructure, array_fill_keys($sold, ['kg' => 1.0]));

		$eCompositionPlan = CompositionPlanLib::getForStart($eStructure, 2098);

		$this->assertTrue($eCompositionPlan['followed'], 'le plan suit la saison 2098');
		$this->assertEquals([], $eCompositionPlan['sold'], 'un plan qui suit la saison ignore les ventes');
		$this->assertEquals(0, $this->countExcluded($eCompositionPlan), 'ses espèces, toutes cultivées');

	}

	private function getNatural(CompositionPlan $eCompositionPlan): array {

		return $this->getSorted($eCompositionPlan['cCompositionSpecies']
			->find(fn(CompositionSpecies $e) => $e['status'] === CompositionSpecies::NATURAL)
			->getKeys());

	}

	private function countExcluded(CompositionPlan $eCompositionPlan): int {
		return $eCompositionPlan['cCompositionSpecies']->find(fn(CompositionSpecies $e) => $e['status'] === CompositionSpecies::EXCLUDED)->count();
	}

	private function getSorted(array $values): array {

		sort($values);

		return $values;

	}

	private function getSalesKey(\structure\Structure $eStructure): string {
		return 'toolbox-composition-sales-'.$eStructure['id'].'-'.\Clock::date();
	}

	/**
	 * Pose les ventes d'une structure dans la clé du jour, et vide ce que l'exécution a gardé, que
	 * `getSales()` lirait avant la clé.
	 */
	private function setSales(\structure\Structure $eStructure, array $sales): void {

		\Cache::redis()->set($this->getSalesKey($eStructure), $sales, 86400);
		$this->structuresSeeded[] = $eStructure['id'];

		$this->resetSales();

	}

	private function resetSales(): void {
		new \ReflectionProperty(CompositionLib::class, 'sales')->setValue(NULL, []);
	}

	/**
	 * Un réglage du prix recompose la taille : ses semaines sont remplacées et non ajoutées.
	 */
	public function testUpdatePriceRecomposes(): void {

		$eStructure = $this->getStructureWithoutPlan();

		$eCompositionPlan = $this->createStarted($eStructure, 2026);
		$eCompositionFormula = CompositionFormulaLib::getByPlan($eCompositionPlan)->first();
		$eCompositionFormula['plan'] = $eCompositionPlan;

		$weeks = CompositionWeek::model()->whereFormula($eCompositionFormula)->count();

		$eCompositionFormula['price'] = 25.0;
		CompositionFormulaLib::update($eCompositionFormula, ['price']);

		$this->assertEquals(25.0, (float)CompositionFormulaLib::getById($eCompositionFormula['id'])['price'], 'prix enregistré');

		$eCompositionFormula['price'] = 12.0;
		CompositionFormulaLib::update($eCompositionFormula, ['price']);

		$this->assertEquals($weeks, CompositionWeek::model()->whereFormula($eCompositionFormula)->count(), 'semaines remplacées, pas ajoutées');
		$this->assertEquals(1, CompositionPlan::model()->whereStructure($eStructure)->whereSeason(2026)->count(), 'un seul plan');

	}

	/**
	 * Le point d'entrée des réglages de semaines et d'espèces recompose chaque taille du plan, hors
	 * des semaines corrigées (`CompositionPanelTest::testUpdateWeeksSparesAmended()`), et chacune
	 * garde une semaine par semaine livrée.
	 */
	public function testRecomposePlan(): void {

		$eStructure = $this->getStructureWithoutPlan();

		$eCompositionPlan = $this->createStarted($eStructure, 2026);

		// La source d'un plan sert ses espèces sous l'id de leur `CompositionSpecies` (r76) : celle de
		// la communauté les servirait sous l'id du référentiel, qu'aucune ligne ne pointe.
		$source = CompositionLib::getSourceByPlan($eCompositionPlan);

		$eCompositionFormula = new CompositionFormula([
			'plan' => $eCompositionPlan,
			'name' => 'Le grand panier',
			'price' => 30.0,
			'baskets' => 10,
			'position' => 1,
		]);

		CompositionFormulaLib::create($eCompositionFormula);
		$prices = $source['pricesPlan'];

		CompositionLib::compose($eCompositionPlan, $eCompositionFormula, $source, $prices);

		CompositionLib::recomposePlan($eCompositionPlan, $source, $prices);

		foreach(CompositionFormulaLib::getByPlan($eCompositionPlan) as $eCompositionFormulaRead) {
			$this->assertEquals(count($eCompositionPlan['weeks']), CompositionWeek::model()->whereFormula($eCompositionFormulaRead)->count(), 'une semaine par semaine livrée : '.$eCompositionFormulaRead['name']);
		}

	}

	/**
	 * Les paniers sont ouverts à toute structure qui a la production, déclarée en légumes ou non, et
	 * à elles seules (lots r42 et r52), sur tous les environnements : la garde ne lit que l'état de
	 * la structure.
	 *
	 * Le comportement s'éprouve sur une structure de chaque sorte, lue en base. Le test tourne en dev,
	 * et `LIME_ENV` est posé pour tout le processus : une garde qui fermerait l'outil ailleurs qu'en
	 * dev y passerait inaperçue. La forme le tient donc aussi, le corps de la méthode ne portant que
	 * `acceptProduction()`, sans environnement ni liste de structures.
	 */
	public function testAcceptComposition(): void {

		$eStructureProducer = $this->getProducerWithoutVegetable();

		$eStructureOther = \structure\Structure::model()
			->select(\structure\Structure::getSelection())
			->whereType('!=', \structure\Structure::PRODUCER)
			->whereStatus(\structure\Structure::ACTIVE)
			->sort(['id' => SORT_ASC])
			->get();

		$this->assertEquals([TRUE, FALSE], [$eStructureProducer->acceptProduction(), $eStructureProducer->acceptVegetable()], 'le cas est monté : un producteur sans maraichage ni légume');
		$this->assertFalse($eStructureOther->acceptProduction(), 'le cas est monté : une structure qui n\'est pas productrice');

		$this->assertTrue($eStructureProducer->acceptComposition(), 'un producteur sans maraichage ni légume ouvre les paniers');
		$this->assertFalse($eStructureOther->acceptComposition(), 'une structure qui n\'est pas productrice ne les ouvre pas');

		$method = new \ReflectionMethod(\structure\Structure::class, 'acceptComposition');
		$body = array_values(array_filter(array_map('trim', array_slice(file($method->getFileName()), $method->getStartLine(), $method->getEndLine() - $method->getStartLine() - 1))));

		$this->assertEquals(['return $this->acceptProduction();'], $body, 'la garde ne porte que la production, sans environnement ni liste de structures : '.implode(' ', $body));

	}

	/**
	 * Un producteur qui n'a déclaré ni légumes ni aucune culture ouvre l'outil comme un autre (lot
	 * r42) : la page le mène à l'accueil, et le départ lui propose toutes les espèces, aucun plan de
	 * culture ne lui en cochant une partie. Rien ne s'écrit.
	 */
	public function testStartWithoutVegetable(): void {

		$eStructure = $this->getProducerWithoutVegetable();

		$this->assertEquals([], CompositionLib::getCultivatedBySeason($eStructure), 'le cas est monté : aucun plan de culture, quelle que soit la saison');

		$counts = $this->countRows();

		$this->assertTrue(CompositionPlanLib::getForScreen($eStructure, NULL)->empty(), 'sans plan, la page ouvre l\'accueil');
		$this->assertTrue(str_contains(new CompositionUi()->getStart($eStructure), 'href="'.CompositionUi::urlStart($eStructure).'"'), 'l\'accueil mène au départ');

		// La saison est celle que la page lit quand l'adresse n'en porte pas.
		$eCompositionPlan = CompositionPlanLib::getForStart($eStructure);

		$this->assertEquals(NULL, $eCompositionPlan['cultivated'], 'aucune espèce cochée depuis un plan de culture');
		$this->assertEquals([$eCompositionPlan['season']], array_keys($eCompositionPlan['cultivatedSeasons']), 'le menu des saisons ne porte que celle du départ');
		$this->assertTrue($eCompositionPlan['cCompositionSpecies']->count() > 0, 'les espèces proposables sont là');

		$html = new CompositionStartUi()->getStart($eCompositionPlan);

		$this->assertTrue(str_contains($html, 'id="toolbox-composition-start"'), 'le départ se rend');
		$this->assertEquals($counts, $this->countRows(), 'aucune ligne écrite par la lecture');

	}

	/**
	 * La première ferme active sans plan de paniers qui n'a déclaré ni le maraichage ni les légumes
	 * parmi ses productions, et qui n'a aucune culture.
	 */
	private function getProducerWithoutVegetable(): \structure\Structure {

		$eStructure = \structure\Structure::model()
			->select(['id' => new \Sql('m1.id')])
			->join(\structure\Production::model(), 'm1.id = m2.structure AND m2.fqn IN (\'maraichage\', \'legume\')', 'LEFT')
			->join(\series\Cultivation::model(), 'm1.id = m3.structure', 'LEFT')
			->join(CompositionPlan::model(), 'm1.id = m4.structure', 'LEFT')
			->where('m1.type', \structure\Structure::PRODUCER)
			->where('m1.status', \structure\Structure::ACTIVE)
			->where('m2.id IS NULL')
			->where('m3.id IS NULL')
			->where('m4.id IS NULL')
			->sort(['m1.id' => SORT_ASC])
			->get();

		return \structure\StructureLib::getById($eStructure['id']);

	}

	/**
	 * Les paniers ne se mettent au menu que là où la Boîte les montre : dans une structure qui les
	 * accepte, et pour un gestionnaire. Le test tourne sans personne connectée, donc sans gestionnaire :
	 * l'étoile des paniers y est refusée, celle de la pluviométrie non.
	 */
	public function testBasketFavoriteClosed(): void {

		$eStructure = \structure\StructureLib::getById($this->eStructure['id']);

		$this->assertFalse($eStructure->canManage(), 'personne n\'est connecté');

		$eUser = \user\User::model()
			->select('id')
			->sort(['id' => SORT_ASC])
			->get();

		$builds = function(string $tool) use($eStructure, $eUser): array {

			$eFavorite = new Favorite(['structure' => $eStructure, 'user' => $eUser]);

			$fw = new \FailWatch();
			$eFavorite->build(['tool'], ['tool' => $tool]);

			return array_map(fn($fail) => $fail[1], $fw->get());

		};

		$this->assertEquals(['Favorite::tool.closed'], $builds(Favorite::BASKET), 'les paniers sont refusés');
		$this->assertEquals([], $builds(Favorite::RAIN), 'la pluviométrie passe');

	}

	/**
	 * Toute saison compte 52 semaines, y compris 2026, année ISO longue qui commence un jeudi.
	 */
	public function testSeasonWeeks(): void {

		$this->assertEquals(range(1, 52), CompositionLib::getSeasonWeeks(), 'cinquante-deux semaines');

	}

	/**
	 * La semaine 53 de 2026 n'est jamais proposée : ni au départ, ni en base une fois le plan
	 * composé sur toute la saison. La 52 l'est, avec un panier.
	 */
	public function testWeek53(): void {

		$eStructure = $this->getStructureWithoutPlan();

		$eCompositionPlanStart = CompositionPlanLib::getForStart($eStructure, 2026);

		$this->assertEquals(range(1, 52), $eCompositionPlanStart['seasonWeeks'], 'semaines du départ');

		$eCompositionPlan = $this->createStarted($eStructure, 2026);
		$eCompositionFormula = CompositionFormulaLib::getByPlan($eCompositionPlan)->first();

		$this->assertEquals(0, CompositionWeek::model()->whereFormula($eCompositionFormula)->whereWeek(53)->count(), 'aucune semaine 53 enregistrée');
		$this->assertEquals(52, CompositionWeek::model()->whereFormula($eCompositionFormula)->count(), 'cinquante-deux semaines enregistrées');

		$eCompositionWeek = CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, 52);

		$this->assertTrue(CompositionLineLib::getByWeek($eCompositionWeek)->notEmpty(), 'semaine 52 garnie');

	}

	/**
	 * Le départ envoyé sur toute la saison, une taille à 18 €, les espèces cochées comme le départ
	 * les propose.
	 */
	private function createStarted(\structure\Structure $eStructure, int $season): CompositionPlan {

		$eCompositionPlan = CompositionPlanLib::getForStart($eStructure, $season);
		$eCompositionPlan['weeks'] = $eCompositionPlan['seasonWeeks'];
		$eCompositionPlan['reference'] = CompositionPlan::COMMUNITY;
		$eCompositionPlan['climate'] = NULL;
		$eCompositionPlan['cCompositionFormula']->first()->merge(['name' => 'Le panier', 'price' => 18.0, 'baskets' => 10]);

		CompositionPlanLib::create($eCompositionPlan);

		$eCompositionPlanWritten = CompositionPlanLib::getByStructure($eStructure);
		$eCompositionPlanWritten['structure'] = $eStructure;

		return $eCompositionPlanWritten;

	}

	/**
	 * Une structure active qui cultive la saison et n'a pas encore de plan de paniers : le départ y
	 * coche des espèces, et n'en reprend aucune d'un plan existant.
	 */
	private function getStructureCultivating(int $season): \structure\Structure {

		return \structure\Structure::model()
			->select(['id' => new \Sql('m1.id')])
			->join(CompositionPlan::model(), 'm1.id = m2.structure', 'LEFT')
			->join(\series\Cultivation::model(), 'm1.id = m3.structure AND m3.season = '.$season)
			->where('m2.id IS NULL')
			->where('m1.status', \structure\Structure::ACTIVE)
			->group(new \Sql('m1.id'))
			->sort(['m1.id' => SORT_ASC])
			->get();

	}

	/**
	 * Une structure active sans plan : `CompositionPlanLib::create()` annule toute la pile de
	 * transactions sur un doublon, ce qui emporterait celle du test.
	 */
	private function getStructureWithoutPlan(): \structure\Structure {

		return \structure\Structure::model()
			->select(['id' => new \Sql('m1.id')])
			->join(CompositionPlan::model(), 'm1.id = m2.structure', 'LEFT')
			->where('m2.id IS NULL')
			->where('m1.status', \structure\Structure::ACTIVE)
			->sort(['m1.id' => SORT_ASC])
			->get();

	}

	private function countRows(): array {

		return [
			CompositionPlan::model()->count(),
			CompositionFormula::model()->count(),
			CompositionWeek::model()->count(),
			CompositionLine::model()->count(),
			CompositionSpecies::model()->count(),
		];

	}

	/**
	 * Des espèces du référentiel partagé dont l'organe est qualifié : la grille les range par là.
	 */
	private function getPlants(string $organ, int $number): \Collection {

		$cPlant = \plant\Plant::model()
			->select(['id', 'name', 'organ'])
			->whereStructure(NULL)
			->whereOrgan($organ)
			->sort(['id' => SORT_ASC])
			->getCollection(0, $number);

		if($cPlant->count() < $number) {
			throw new \Exception('le référentiel ne porte pas '.$number.' espèces d\'organe '.$organ);
		}

		return $cPlant;

	}

	/**
	 * Un relevé construit, tel que la source d'un plan le sert (`CompositionLib::getSourceByPlan()`) :
	 * chaque espèce du plan de `$shares` (`[id d'espèce du plan => part]`) disponible sur les semaines
	 * données, de saison, dans l'unité de test, avec un prix, et classée par sa part à l'année. Comme
	 * dans la source d'un plan, la disponibilité reste rangée par l'espèce du référentiel que l'espèce
	 * du plan lit (`plants`), et tout le reste par l'espèce du plan.
	 */
	private function getSource(array $shares, array $weeks): array {

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

		$cCompositionSpecies = ($shares === []) ?
			new \Collection() :
			CompositionSpecies::model()
				->select(['id', 'plant'])
				->whereId('IN', array_keys($shares))
				->getCollection(index: 'id');

		foreach($shares as $speciesId => $share) {

			$plantId = $cCompositionSpecies[$speciesId]['plant']['id'];

			$source['plants'][$speciesId] = $plantId;
			$source['speciesBare'][$plantId] = $speciesId;
			$source['units'][$speciesId] = ['unit' => $this->eUnit['id'], 'quantity' => 1.0, 'minimum' => NULL, 'step' => 0.1];
			$source['frequency']['byYear'][$speciesId] = $share;

			foreach($weeks as $week) {
				$source['availability'][$week][$plantId] = 10;
				$source['prices'][$week][$speciesId][$this->eUnit['id']] = ['contributors' => 10, 'median' => 2.0, 'low' => 2.0, 'high' => 2.0];
			}

		}

		return $source;

	}

	private function createPlan(array $weeks): CompositionPlan {

		$eCompositionPlan = new CompositionPlan([
			'structure' => $this->eStructure,
			'season' => 2099,
			'reference' => CompositionPlan::COMMUNITY,
			'weeks' => $weeks,
			'cCompositionFormula' => new \Collection(),
			'cCompositionSpecies' => new \Collection(),
		]);

		CompositionPlanLib::create($eCompositionPlan);

		return $eCompositionPlan;

	}

	private function createFormula(CompositionPlan $eCompositionPlan, float $price): CompositionFormula {

		$eCompositionFormula = new CompositionFormula([
			'plan' => $eCompositionPlan,
			'name' => 'Panier de test',
			'price' => $price,
			'baskets' => 10,
			'position' => 0,
		]);

		CompositionFormulaLib::create($eCompositionFormula);

		return $eCompositionFormula;

	}

	private function createWeek(CompositionFormula $eCompositionFormula, int $week, array $relaxedFloor = []): CompositionWeek {

		$eCompositionWeek = new CompositionWeek([
			'formula' => $eCompositionFormula,
			'week' => $week,
			'relaxedFloor' => new \Set($relaxedFloor),
		]);

		CompositionWeekLib::create($eCompositionWeek);

		return $eCompositionWeek;

	}

	/**
	 * Une espèce du plan par espèce de `$cPlant`, dans l'ordre, chacune nue et sans saison réglée,
	 * avec la fiche qu'elle lit (`plant`, nom et organe compris) : la grille range les lignes par
	 * l'espèce du plan qu'elles servent (r76).
	 */
	private function createSpecies(CompositionPlan $eCompositionPlan, \Collection $cPlant): array {

		$species = [];

		foreach($cPlant as $ePlant) {

			$eCompositionSpecies = new CompositionSpecies([
				'plan' => $eCompositionPlan,
				'plant' => $ePlant,
				'status' => CompositionSpecies::CUSTOM,
				'windowWeeks' => [],
			]);

			// Sans build() : c'est le décor du test. `CUSTOM` sur une saison vide est la forme que
			// `CompositionSpecies::properties()` admet, et la grille reçoit la saison par `$windows`.
			CompositionSpeciesLib::create($eCompositionSpecies);

			$species[] = $eCompositionSpecies;

		}

		return $species;

	}

	private function createLine(CompositionWeek $eCompositionWeek, CompositionSpecies $eCompositionSpecies, float $quantity, ?float $unitPrice): void {

		CompositionLineLib::create(new CompositionLine([
			'week' => $eCompositionWeek,
			'species' => $eCompositionSpecies,
			'unit' => $this->eUnit,
			'quantity' => $quantity,
			'quantityProposed' => $quantity,
			'unitPrice' => $unitPrice,
			'origin' => CompositionLine::PROPOSED,
		]));

	}

}
?>
