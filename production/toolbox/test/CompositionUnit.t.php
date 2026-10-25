<?php
namespace toolbox;

/**
 * L'unité au panier qu'une structure choisit pour une espèce (lot r14, planche r14 tranchée le 27/09,
 * toutes les options A) : le kilo, la pièce ou la botte, l'unité de la communauté restant le choix
 * vide (`CompositionSpecies.unit`, NULL).
 *
 * - la source du plan prend l'unité choisie : sa quantité est la médiane de la communauté dans cette
 *   unité, à défaut celle de l'unité de la communauté convertie par le poids, et une espèce que rien
 *   ne convertit n'entre plus au panier (`CompositionLib::applyUnits()`, `isSold()`) ;
 * - le prix de la structure passe devant la médiane quelle que soit l'unité où elle l'a posé, converti
 *   par le poids, et la médiane nationale de l'unité de la communauté est convertie en dernier recours
 *   (`getPrices()`, `getUnitPrice()`, `getPriceReference()`, `PRICE_CONVERTED`) ;
 * - le contrôle de l'unité et du poids, par `build()` : une unité proposée, jamais celle de la
 *   communauté, et un poids exigé quand ni les cultures ni le poids courant n'en donnent un ;
 * - le geste efface le prix, le pas, le minimum et le poids tapés, recompose les semaines non
 *   corrigées de toutes les tailles, et laisse les semaines corrigées dans l'unité d'avant ;
 * - « Recommencer » reporte l'unité ;
 * - le panneau « Régler une espèce » dit l'unité et le prix converti, dans ses tuiles et ses champs.
 *
 * Le geste s'éprouve sur un plan écrit par le départ sur la saison 2099, qu'aucune structure
 * n'emploie : aucune culture de la saison ne pèse ses pièces ni ses bottes, et le poids est donc le
 * poids tapé ou le poids courant (`ToolboxSetting::COMPOSITION_WEIGHTS`). Les espèces se choisissent
 * dans le relevé de la nuit, par ce qu'il en dit, et l'absence d'une espèce qui convienne est un échec
 * qui le dit.
 *
 * Isolation : une transaction par méthode, annulée en tearDown().
 *
 * Lancement : dev/test package=toolbox file=CompositionUnit
 */
class CompositionUnitTest extends \Test {

	const SEASON = 2099;

	const PRICE = 16.0;

	private \user\User $eUserInitial;

	public function setUp(): void {

		CompositionPlan::model()->beginTransaction();

		$this->eUserInitial = \user\ConnectionLib::getOnline();

	}

	public function tearDown(): void {

		\user\ConnectionLib::setOnline($this->eUserInitial);

		CompositionPlan::model()->rollBack();

	}

	/**
	 * L'écran lit les espèces du plan une fois (`CompositionSpeciesLib::getByPlan()`) et en dérive ce
	 * que chaque lecture relisait seule (chantier Performances, P7) : la source du plan qu'il en tire
	 * (unités choisies, prix tapés) est celle que `getSourceByPlan()` relit, et les fenêtres, les pas et
	 * les unités sont ceux des lectures propres. Le plan porte une espèce passée à la pièce, une espèce
	 * au prix et au pas tapés, et des espèces sans rien de tout cela, que les dérivations doivent
	 * écarter comme les lectures le font en SQL.
	 */
	public function testSourceFromSpeciesRead(): void {

		$eCompositionPlan = $this->createPlan();
		$plants = $this->getPlants($eCompositionPlan);

		if($eCompositionPlan->empty() or $plants === []) {
			return;
		}

		['converted' => $speciesConverted, 'median' => $speciesMedian, 'one' => $oneId] = $plants;

		$this->assertEquals([], $this->setUnit($this->getSpecies($eCompositionPlan, $speciesConverted), (string)$oneId), 'passée à la pièce');
		$this->assertEquals([], $this->setSpecies($this->getSpecies($eCompositionPlan, $speciesMedian), ['price' => '7.25', 'step' => '0.5']), 'prix et pas tapés');

		$cCompositionSpecies = CompositionSpeciesLib::getByPlan($eCompositionPlan);

		$this->assertTrue($cCompositionSpecies->find(fn(CompositionSpecies $e) => ($e['price'] === NULL and $e['unit']->empty() and $e['step'] === NULL))->count() > 0, 'des espèces sans unité, sans prix ni pas, que les dérivations écartent');

		$windows = CompositionSpeciesLib::getWindows($cCompositionSpecies);

		$this->assertEquals(CompositionSpeciesLib::getWindowsByPlan($eCompositionPlan), $windows, 'les fenêtres');
		$this->assertEquals(CompositionSpeciesLib::getStepsByPlan($eCompositionPlan), CompositionSpeciesLib::getSteps($cCompositionSpecies), 'les pas');
		$this->assertEquals(CompositionSpeciesLib::getUnitsByPlan($eCompositionPlan), CompositionSpeciesLib::getUnits($cCompositionSpecies), 'les unités');

		$source = CompositionLib::getSourceByPlan($eCompositionPlan);

		$this->assertEquals(CompositionLib::PRICE_TYPED, $source['pricesPlan'][$speciesMedian]['from'], 'le prix tapé est dans la cascade');
		$this->assertEquals($oneId, $source['units'][$speciesConverted]['unit'], 'l\'unité choisie est dans la source');

		// La conservation porte la copie de l'espèce, un objet que chaque lecture instancie à nouveau,
		// et assertEquals() compare les objets par identité : la copie se compare par son identifiant.
		$flatten = fn(array $source) => array_merge($source, [
			'storage' => array_map(fn(array $storage) => array_merge($storage, ['plant' => $storage['plant']->notEmpty() ? $storage['plant']['id'] : NULL]), $source['storage']),
		]);

		$this->assertEquals($flatten($source), $flatten(CompositionLib::getSourceByPlan($eCompositionPlan, $windows, $cCompositionSpecies)), 'la source tirée des espèces lues est celle que la source relit');

	}

	/**
	 * Une unité choisie remplace celle de la communauté sur la source du plan, et seulement là :
	 *
	 * - l'ail passé à la pièce, que la communauté ne sert qu'au kilo, prend la quantité au kilo
	 *   divisée par le poids d'une pièce ;
	 * - une espèce passée à la botte, que la communauté sert aussi à la botte, prend la médiane à la
	 *   botte ;
	 * - une espèce sans poids courant passée à la pièce avec un poids tapé se convertit par lui, et
	 *   sans poids du tout n'a plus de quantité, ni de place dans les paniers.
	 *
	 * La source partagée par toutes les structures (`getSource()`) n'en voit rien.
	 */
	public function testSource(): void {

		$eCompositionPlan = $this->createPlan();
		$plants = $this->getPlants($eCompositionPlan);

		if($eCompositionPlan->empty() or $plants === []) {
			return;
		}

		['converted' => $speciesConverted, 'median' => $speciesMedian, 'unweighed' => $speciesUnweighed, 'one' => $oneId, 'bunch' => $bunchId, 'kg' => $kgId] = $plants;
		['converted' => $plantConverted, 'median' => $plantMedian, 'unweighed' => $plantUnweighed] = $plants['plants'];

		$sourceShared = CompositionLib::getSource();

		$this->assertEquals([], $this->setUnit($this->getSpecies($eCompositionPlan, $speciesConverted), (string)$oneId), 'pièce sans poids tapé, le poids courant suffit');
		$this->assertEquals([], $this->setUnit($this->getSpecies($eCompositionPlan, $speciesMedian), (string)$bunchId), 'botte sans poids tapé');
		$this->assertEquals([], $this->setUnit($this->getSpecies($eCompositionPlan, $speciesUnweighed), (string)$oneId, '0.25'), 'pièce avec un poids tapé');

		$source = CompositionLib::getSourceByPlan($eCompositionPlan);

		$weight = $sourceShared['weights'][$plantConverted]['one'];

		$this->assertEquals([
			'unit' => $oneId,
			'quantity' => $sourceShared['units'][$plantConverted]['quantity'] / $weight,
			'minimum' => NULL,
			'fqn' => 'one',
			'step' => 1.0,
			'kilos' => $weight,
			'community' => ['unit' => $kgId, 'fqn' => 'kg', 'kilos' => 1.0, 'ratio' => $weight],
		], $source['units'][$speciesConverted], 'convertie par le poids courant d\'une pièce');

		$this->assertEquals($sourceShared['quantities'][$plantMedian][$bunchId]['quantity'], $source['units'][$speciesMedian]['quantity'], 'la médiane de la communauté à la botte');

		// Le minimum de la communauté se dit dans son unité, et ne suit pas l'unité choisie (lot r25) :
		// l'espèce retombe sur un pas, même quand la communauté a un minimum dans l'unité choisie.
		$this->assertTrue($sourceShared['units'][$plantConverted]['minimum'] > 0, 'la communauté donne un minimum à l\'espèce convertie, dans son unité');
		$this->assertEquals(['step' => 1.0, 'minimum' => 1.0], CompositionLib::getStep($speciesConverted, $source, []), 'passée à la pièce, un minimum d\'une pièce');
		$this->assertTrue($sourceShared['quantities'][$plantMedian][$bunchId]['minimum'] > 0, 'la communauté a un minimum à la botte');
		$this->assertEquals(['step' => 1.0, 'minimum' => 1.0], CompositionLib::getStep($speciesMedian, $source, []), 'passée à la botte, un minimum d\'une botte');

		// Le sens où l'écart se voit : une espèce que la communauté sert à la botte ou à la pièce, au
		// minimum d'une unité, passée au kilo, prend un minimum d'un pas du kilo et non d'un kilo.
		$speciesBare = CompositionLib::getSourceByPlan($eCompositionPlan)['speciesBare'];
		$speciesWhole = NULL;

		foreach($sourceShared['units'] as $plantId => $unit) {
			if($speciesWhole === NULL and $unit['fqn'] !== 'kg' and $unit['minimum'] !== NULL and $unit['minimum'] >= 1.0 and array_key_exists($plantId, $speciesBare)) {
				$speciesWhole = $speciesBare[$plantId];
			}
		}

		if($speciesWhole === NULL) {
			$this->assertFail('aucune espèce du plan à la botte ou à la pièce avec un minimum : le contrôle ne verrait rien');
		} else {

			$this->assertEquals([], $this->setUnit($this->getSpecies($eCompositionPlan, $speciesWhole), (string)$kgId), 'passée au kilo');

			$sourceKilo = CompositionLib::getSourceByPlan($eCompositionPlan);
			$stepKilo = CompositionLib::getStep($speciesWhole, $sourceKilo, []);

			$this->assertTrue($stepKilo['step'] < 1.0, 'au pas du kilo');
			$this->assertEquals($stepKilo['step'], $stepKilo['minimum'], 'passée au kilo, un minimum d\'un pas et non celui de la communauté');

		}
		$this->assertEquals($bunchId, $source['units'][$speciesMedian]['unit'], 'dans l\'unité choisie');

		$this->assertEquals($sourceShared['units'][$plantUnweighed]['quantity'] / 0.25, $source['units'][$speciesUnweighed]['quantity'], 'convertie par le poids tapé');
		$this->assertEquals(0.25, $source['units'][$speciesUnweighed]['community']['ratio'], 'le poids tapé fait le rapport');

		foreach([$plantConverted => $speciesConverted, $plantMedian => $speciesMedian, $plantUnweighed => $speciesUnweighed] as $plantId => $speciesId) {
			$this->assertEquals($kgId, $sourceShared['units'][$plantId]['unit'], 'la source partagée garde l\'unité de la communauté : '.$plantId);
			$this->assertTrue(CompositionLib::isSold($speciesId, $source), 'proposable : '.$speciesId);
		}

		// Un poids effacé après coup, sur une unité déjà choisie, laisse l'espèce sans quantité : elle
		// sort des paniers des semaines non corrigées, comme une espèce sans prix.
		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesUnweighed);
		$eCompositionSpecies['weight'] = NULL;
		CompositionSpeciesLib::update($eCompositionSpecies, ['weight']);

		$source = CompositionLib::getSourceByPlan($eCompositionPlan);

		$this->assertEquals(NULL, $source['units'][$speciesUnweighed]['quantity'], 'sans poids, pas de quantité');
		$this->assertFalse(CompositionLib::isSold($speciesUnweighed, $source), 'ni de place au panier');

		$served = [];

		foreach($this->getLines($eCompositionPlan) as $line) {

			if($line['species'] === $speciesUnweighed) {
				$served[] = $line['formula'].'/'.$line['week'];
			}

		}

		$this->assertEquals([], $served, 'aucune semaine ne la sert : '.implode(', ', $served));

	}

	/**
	 * Le prix d'une espèce passée à la pièce : le prévisionnel au kilo de la structure, converti par le
	 * poids d'une pièce, passe devant la médiane nationale, et le formulaire du prix retrouve le prix
	 * d'origine (`community`, `converted`). Un prix tapé à la pièce passe devant les deux. Sans aucun
	 * prix de la structure, la médiane nationale au kilo est convertie, faute de médiane à la pièce.
	 */
	public function testPrices(): void {

		$eCompositionPlan = $this->createPlan();
		$plants = $this->getPlants($eCompositionPlan);

		if($eCompositionPlan->empty() or $plants === []) {
			return;
		}

		['converted' => $speciesConverted, 'unweighed' => $speciesUnweighed, 'one' => $oneId] = $plants;
		$plantConverted = $plants['plants']['converted'];

		$ePlantStructure = \plant\Plant::model()
			->select(['id', 'reference'])
			->whereStructure($eCompositionPlan['structure'])
			->whereReference($plantConverted)
			->get();

		if($ePlantStructure->empty()) {
			$this->assertFail('la structure du plan n\'a pas de copie de l\'espèce : le prévisionnel ne se pose pas');
			return;
		}

		\plant\ForecastLib::create(new \plant\Forecast([
			'structure' => $eCompositionPlan['structure'],
			'season' => self::SEASON,
			'plant' => $ePlantStructure,
			'unit' => \plant\Forecast::KG,
			'privatePrice' => 12.0,
		]));

		$pricesKg = CompositionLib::getPrices($eCompositionPlan, CompositionLib::getSourceByPlan($eCompositionPlan));

		$this->assertEquals(CompositionLib::PRICE_FORECAST, $pricesKg[$speciesConverted]['from'], 'au kilo, le prévisionnel');

		$this->setUnit($this->getSpecies($eCompositionPlan, $speciesConverted), (string)$oneId);

		$source = CompositionLib::getSourceByPlan($eCompositionPlan);
		$prices = CompositionLib::getPrices($eCompositionPlan, $source);

		$ratio = $source['units'][$speciesConverted]['community']['ratio'];

		$this->assertEquals(CompositionLib::PRICE_CONVERTED, $prices[$speciesConverted]['from'], 'à la pièce, converti');
		$this->assertEquals($pricesKg[$speciesConverted], $prices[$speciesConverted]['community'], 'le prix au kilo reste à côté');
		$this->assertEquals([round(12.0 * $ratio, 2)], array_values(array_unique($prices[$speciesConverted]['weeks'])), '12 € le kilo, par le poids d\'une pièce');

		// La cascade dans l'unité choisie et celle dans l'unité de la communauté lisent les mêmes
		// sources de la structure, une fois pour les deux (chantier Performances, P17).
		$forecast = array_filter($this->getQueries(fn() => CompositionLib::getPrices($eCompositionPlan, $source)), fn(string $sql) => str_contains($sql, '`plantForecast`'));
		$this->assertEquals(1, count($forecast), 'les deux cascades lisent le prévisionnel '.count($forecast).' fois, et non une');

		$week = CompositionLib::getSeasonWeeks()[0];
		$reference = CompositionLib::getPriceReference($week, $speciesConverted, $source, $prices);

		$this->assertEquals([CompositionLib::PRICE_CONVERTED, round(12.0 * $ratio, 2), ['unitPrice' => 12.0, 'from' => CompositionLib::PRICE_FORECAST]], [$reference['from'], $reference['unitPrice'], $reference['converted']], 'le panneau reçoit le prix converti et son origine');

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesConverted);
		$eCompositionSpecies['price'] = 0.7;
		CompositionSpeciesLib::update($eCompositionSpecies, ['price']);

		$prices = CompositionLib::getPrices($eCompositionPlan, $source);

		$this->assertEquals(CompositionLib::PRICE_TYPED, $prices[$speciesConverted]['from'], 'un prix tapé à la pièce passe devant');

		// La médiane nationale au kilo, convertie : une espèce que la structure ne chiffre dans aucune
		// unité, une semaine où la médiane au kilo est employable.
		$this->setUnit($this->getSpecies($eCompositionPlan, $speciesUnweighed), (string)$oneId, '0.5');

		$source = CompositionLib::getSourceByPlan($eCompositionPlan);
		$prices = CompositionLib::getPrices($eCompositionPlan, $source);

		if(array_key_exists($speciesUnweighed, $prices)) {
			$this->assertFail('la structure du plan chiffre déjà l\'espèce : la médiane nationale ne serait pas lue');
			return;
		}

		$weeksChecked = 0;

		foreach(CompositionLib::getSeasonWeeks() as $week) {

			$median = $sourceMedian = $source['prices'][$week][$speciesUnweighed][$source['units'][$speciesUnweighed]['community']['unit']]['median'] ?? NULL;
			$medianChosen = $source['prices'][$week][$speciesUnweighed][$oneId]['median'] ?? NULL;

			if($sourceMedian === NULL or $medianChosen !== NULL) {
				continue;
			}

			$reference = CompositionLib::getPriceReference($week, $speciesUnweighed, $source, $prices);

			if($reference['converted'] === NULL) {
				continue;
			}

			$this->assertEquals(round($median * 0.5, 2), CompositionLib::getUnitPrice($week, $speciesUnweighed, $source, $prices), 'semaine '.$week.', la médiane au kilo convertie');
			$this->assertEquals([CompositionLib::PRICE_CONVERTED, CompositionLib::PRICE_NATIONAL], [$reference['from'], $reference['converted']['from']], 'semaine '.$week.', d\'où vient le prix');

			$weeksChecked++;

		}

		$this->assertTrue($weeksChecked > 0, 'au moins une semaine chiffrée par la médiane au kilo');

	}

	/**
	 * Le premier nombre de lignes d'une semaine (`getLinesStart()`, privée) quand une espèce convertie
	 * est candidate : elle l'est dès que la communauté la relève dans son unité, sans relevé dans
	 * l'unité choisie, et sa valeur au panier est alors la médiane de l'unité de la communauté,
	 * convertie par le poids. Une valeur lue dans l'unité choisie serait nulle, et le nombre de lignes
	 * tomberait sur la borne haute de la formule.
	 *
	 * Le prix visé vaut sept fois la valeur de l'espèce, seule de l'échantillon : sept lignes.
	 */
	public function testLinesStart(): void {

		$eCompositionPlan = $this->createPlan();
		$plants = $this->getPlants($eCompositionPlan);

		if($eCompositionPlan->empty() or $plants === []) {
			return;
		}

		['converted' => $speciesConverted, 'one' => $oneId] = $plants;

		$this->setUnit($this->getSpecies($eCompositionPlan, $speciesConverted), (string)$oneId);

		$source = CompositionLib::getSourceByPlan($eCompositionPlan);
		$unit = $source['units'][$speciesConverted];

		$getLinesStart = new \ReflectionMethod(CompositionLib::class, 'getLinesStart');

		$weeksChecked = 0;

		foreach(CompositionLib::getSeasonWeeks() as $week) {

			$prices = $source['prices'][$week][$speciesConverted] ?? [];

			if(array_key_exists($oneId, $prices) or array_key_exists($unit['community']['unit'], $prices) === FALSE) {
				continue;
			}

			$value = $unit['quantity'] * $prices[$unit['community']['unit']]['median'] * $unit['community']['ratio'];

			$this->assertEquals(7, $getLinesStart->invoke(NULL, $value * 7, 1, 1000, $week, [$speciesConverted], $source, []), 'semaine '.$week.', la médiane au kilo convertie fait la valeur');

			$weeksChecked++;

		}

		$this->assertTrue($weeksChecked > 0, 'au moins une semaine où l\'espèce n\'est relevée qu\'au kilo');

	}

	/**
	 * Une candidate sans relevé à sa semaine (lot r27 : sa saison écrite peut dépasser la récolte de
	 * la communauté) se chiffre, pour le premier nombre de lignes, au relevé le plus proche
	 * (`getWeekSurveyed()`), comme sa ligne. Lue à sa propre semaine, sa valeur serait nulle et le
	 * nombre de lignes tomberait sur la borne haute de la formule.
	 *
	 * Sur une source posée à la main : une espèce au kilo relevée en semaine 20 à 4 € le kilo, une
	 * part de 0,5 kg, soit 2 € au panier ; un panier de 14 € en fait sept.
	 */
	public function testLinesStartUnsurveyed(): void {

		$source = [
			'units' => [
				1 => ['unit' => 1, 'quantity' => 0.5, 'community' => NULL],
			],
			'prices' => [
				20 => [1 => [1 => ['median' => 4.0]]],
			],
		];

		$getLinesStart = new \ReflectionMethod(CompositionLib::class, 'getLinesStart');

		$this->assertEquals(7, $getLinesStart->invoke(NULL, 14.0, 1, 1000, 20, [1], $source, []), 'à la semaine du relevé');
		$this->assertEquals(7, $getLinesStart->invoke(NULL, 14.0, 1, 1000, 30, [1], $source, []), 'dix semaines plus loin, au relevé le plus proche');

	}

	/**
	 * Le contrôle du geste, par `build()` : une unité proposée, jamais celle de la communauté, qui est
	 * le choix vide ; un poids quand ni les cultures ni le poids courant n'en disent un ; un pas entier
	 * et un poids qui se disent dans l'unité choisie. Chaque refus a son message.
	 */
	public function testBuild(): void {

		$eCompositionPlan = $this->createPlan();
		$plants = $this->getPlants($eCompositionPlan);

		if($eCompositionPlan->empty() or $plants === []) {
			return;
		}

		['converted' => $speciesConverted, 'unweighed' => $speciesUnweighed, 'one' => $oneId, 'kg' => $kgId] = $plants;

		$build = function(int $plantId, array $properties, array $values) use($eCompositionPlan): array {

			$fw = new \FailWatch();

			$this->getSpecies($eCompositionPlan, $plantId)->build($properties, $values);

			return array_column($fw->get(), 1);

		};

		$eUnitOther = \product\Unit::model()
			->select(['id'])
			->whereStructure(NULL)
			->whereFqn('NOT IN', ['kg', 'one', 'bunch'])
			->get();

		$this->assertEquals([], $build($speciesConverted, ['unit', 'weight'], ['unit' => '', 'weight' => '']), 'l\'unité de la communauté, par le choix vide');
		$this->assertEquals(['CompositionSpecies::unit.offered'], $build($speciesConverted, ['unit', 'weight'], ['unit' => (string)$kgId, 'weight' => '']), 'l\'unité de la communauté ne s\'écrit pas');
		$this->assertEquals(['CompositionSpecies::unit.offered'], $build($speciesConverted, ['unit', 'weight'], ['unit' => (string)$eUnitOther['id'], 'weight' => '']), 'une unité que le poids ne convertit pas');

		$this->assertEquals([], $build($speciesConverted, ['unit', 'weight'], ['unit' => (string)$oneId, 'weight' => '']), 'le poids courant d\'une pièce suffit');
		$this->assertEquals(['CompositionSpecies::weight.missing'], $build($speciesUnweighed, ['unit', 'weight'], ['unit' => (string)$oneId, 'weight' => '']), 'sans poids courant, le poids est exigé');
		$this->assertEquals([], $build($speciesUnweighed, ['unit', 'weight'], ['unit' => (string)$oneId, 'weight' => '0.3']), 'avec un poids tapé');

		$this->assertEquals(['CompositionSpecies::weight.weighed'], $build($speciesConverted, ['unit', 'weight'], ['unit' => '', 'weight' => '0.3']), 'pas de poids au kilo');

		// Le pas et le poids se contrôlent dans l'unité choisie, une fois écrite.
		$this->assertEquals([], $build($speciesConverted, ['step'], ['step' => '0.5']), 'un pas décimal au kilo');
		$this->assertEquals(['CompositionSpecies::weight.weighed'], $build($speciesConverted, ['weight'], ['weight' => '0.3']), 'et pas de poids');

		$this->setUnit($this->getSpecies($eCompositionPlan, $speciesConverted), (string)$oneId);

		$this->assertEquals(['CompositionSpecies::step.whole'], $build($speciesConverted, ['step'], ['step' => '0.5']), 'une pièce ne se coupe pas');
		$this->assertEquals([], $build($speciesConverted, ['weight'], ['weight' => '0.3']), 'le poids d\'une pièce se règle');
		$this->assertEquals([], $build($speciesConverted, ['weight'], ['weight' => '']), 'et s\'efface hors du geste de l\'unité');

		foreach(['CompositionSpecies::unit.offered', 'CompositionSpecies::unit.check', 'CompositionSpecies::weight.missing'] as $fqn) {
			$this->assertTrue(AlertUi::getError($fqn) !== NULL, 'message : '.$fqn);
		}

	}

	/**
	 * Le geste efface ce qui se disait dans l'unité d'avant et recompose : les lignes de l'espèce dans
	 * les semaines non corrigées passent dans l'unité choisie, celle d'une semaine corrigée reste dans
	 * l'unité d'avant. Une unité reposée à l'identique n'efface rien ; passer à une autre unité efface
	 * le poids d'une pièce, qui n'a plus d'unité où se dire, même quand le geste ne construit pas le
	 * poids.
	 */
	public function testUpdate(): void {

		$eCompositionPlan = $this->createPlan();
		$plants = $this->getPlants($eCompositionPlan);

		if($eCompositionPlan->empty() or $plants === []) {
			return;
		}

		['converted' => $speciesConverted, 'one' => $oneId, 'bunch' => $bunchId, 'kg' => $kgId] = $plants;

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesConverted);
		$eCompositionSpecies['price'] = 14.0;
		$eCompositionSpecies['step'] = 0.2;
		$eCompositionSpecies['minimum'] = 0.4;
		CompositionSpeciesLib::update($eCompositionSpecies, ['price', 'step', 'minimum']);

		$countUnits = fn() => array_count_values(array_map(fn(array $line) => $line['unit'], array_filter($this->getLines($eCompositionPlan), fn(array $line) => $line['species'] === $speciesConverted)));

		$this->assertEquals([$kgId], array_keys($countUnits()), 'au kilo avant le geste');

		// Une semaine corrigée qui sert l'espèce : elle doit garder sa ligne au kilo.
		$amended = NULL;

		foreach($this->getLines($eCompositionPlan) as $line) {

			if($line['species'] === $speciesConverted) {
				$amended = $line;
				break;
			}

		}

		if($amended === NULL) {
			$this->assertFail('aucune semaine ne sert l\'espèce : le contrôle ne verrait rien');
			return;
		}

		$eCompositionWeek = CompositionWeekLib::getById($amended['weekId']);
		$eCompositionWeek['amended'] = TRUE;
		CompositionWeekLib::update($eCompositionWeek, ['amended']);

		$this->assertEquals([], $this->setUnit($this->getSpecies($eCompositionPlan, $speciesConverted), (string)$oneId), 'pièce');

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesConverted);

		$this->assertEquals([$oneId, NULL, NULL, NULL, NULL], [$eCompositionSpecies['unit']['id'], $eCompositionSpecies['price'], $eCompositionSpecies['step'], $eCompositionSpecies['minimum'], $eCompositionSpecies['weight']], 'le prix, le pas et le minimum au kilo effacés');

		$units = $countUnits();

		$this->assertEquals(1, $units[$kgId] ?? 0, 'la semaine corrigée garde sa ligne au kilo');
		$this->assertTrue(($units[$oneId] ?? 0) > 0, 'les autres semaines passent à la pièce');

		foreach($this->getLines($eCompositionPlan) as $line) {

			if($line['species'] === $speciesConverted and $line['unit'] === $oneId) {
				$this->assertTrue(CompositionLib::isStepMultiple($line['quantity'], 1.0), 'une pièce ne se coupe pas, semaine '.$line['week']);
			}

		}

		// Reposée à l'identique, avec un poids : rien ne s'efface.
		$eCompositionSpecies['price'] = 0.7;
		CompositionSpeciesLib::update($eCompositionSpecies, ['price']);

		$this->assertEquals([], $this->setUnit($this->getSpecies($eCompositionPlan, $speciesConverted), (string)$oneId, '0.06'), 'pièce, de nouveau');

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesConverted);

		$this->assertEquals([0.7, 0.06], [$eCompositionSpecies['price'], $eCompositionSpecies['weight']], 'le prix tapé à la pièce reste, le poids s\'écrit');
		$this->assertEquals(0.06, CompositionLib::getSourceByPlan($eCompositionPlan)['units'][$speciesConverted]['community']['ratio'], 'et convertit');

		// Le Lib appelé sur la seule unité, sans le formulaire rapide, qui construit toujours le poids
		// avec elle : le poids d'une pièce ne se dit pas à la botte, il s'efface aussi.
		$eCompositionSpecies['unit'] = new \product\Unit(['id' => $bunchId]);
		CompositionSpeciesLib::update($eCompositionSpecies, ['unit']);

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesConverted);

		$this->assertEquals([$bunchId, NULL, NULL], [$eCompositionSpecies['unit']['id'], $eCompositionSpecies['price'], $eCompositionSpecies['weight']], 'à la botte, le prix et le poids d\'une pièce effacés');

		$this->assertEquals([], $this->setUnit($eCompositionSpecies, ''), 'retour à la communauté');

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesConverted);

		$this->assertEquals([TRUE, NULL], [$eCompositionSpecies['unit']->empty(), $eCompositionSpecies['weight']], 'l\'unité de la communauté, sans poids');
		$this->assertTrue(($countUnits()[$oneId] ?? 0) === 0, 'plus aucune ligne à la pièce');

	}

	/**
	 * « Recommencer » reporte l'unité choisie avec le poids tapé, et la proposition du nouveau plan
	 * sert l'espèce dans cette unité.
	 */
	public function testRestart(): void {

		$eCompositionPlan = $this->createPlan();
		$plants = $this->getPlants($eCompositionPlan);

		if($eCompositionPlan->empty() or $plants === []) {
			return;
		}

		['converted' => $speciesConverted, 'one' => $oneId] = $plants;

		$this->setUnit($this->getSpecies($eCompositionPlan, $speciesConverted), (string)$oneId, '0.06');

		$eCompositionPlanStart = CompositionPlanLib::getForStart($eCompositionPlan['structure'], self::SEASON);
		$eCompositionPlanStart['weeks'] = $eCompositionPlanStart['seasonWeeks'];
		$eCompositionPlanStart['cCompositionSpecies']->map(fn(CompositionSpecies $e) => $e['status'] = CompositionSpecies::NATURAL);
		$eCompositionPlanStart['cCompositionFormula'] = new \Collection([
			new CompositionFormula(['plan' => $eCompositionPlanStart, 'name' => 'Le panier', 'price' => self::PRICE, 'baskets' => 10, 'gap' => 5, 'version' => 0]),
		]);

		CompositionPlanLib::restart($eCompositionPlanStart);

		$eCompositionPlanNew = CompositionPlanLib::getByStructure($eCompositionPlan['structure']);

		$this->assertTrue($eCompositionPlanNew['id'] !== $eCompositionPlan['id'], 'un nouveau plan');

		// Le nouveau plan recopie ses espèces : l'espèce reportée y a un autre identifiant, celui de
		// l'espèce nue de la même fiche.
		$speciesConvertedNew = CompositionLib::getSourceByPlan($eCompositionPlanNew)['speciesBare'][$plants['plants']['converted']];

		$this->assertEquals([$speciesConvertedNew => ['unit' => $oneId, 'weight' => 0.06, 'quantityBasket' => NULL]], CompositionSpeciesLib::getUnitsByPlan($eCompositionPlanNew), 'l\'unité et le poids reportés');

		$units = array_unique(array_map(fn(array $line) => $line['unit'], array_filter($this->getLines($eCompositionPlanNew), fn(array $line) => $line['species'] === $speciesConvertedNew)));

		$this->assertEquals([$oneId], array_values($units), 'la proposition sert l\'espèce à la pièce');

	}

	/**
	 * La quantité par défaut d'une espèce (r30, #92, retour du 30/09, « choisir une valeur
	 * par défaut qui est sélectionnée quand on ajoute le produit dans une semaine donnée ») :
	 * - `build()` la refuse sous le minimum et hors des pas, et l'accepte vide ;
	 * - vide, le champ du panneau propose le minimum réglé, et le menu d'une case préremplit le minimum ;
	 * - posée, le menu d'une case la préremplit, et une ligne ajoutée sans quantité la prend ;
	 * - changer d'unité l'efface, et le choix de l'unité le dit avant l'envoi.
	 */
	public function testQuantityDefault(): void {

		$eCompositionPlan = $this->createPlan();
		$plants = $this->getPlants($eCompositionPlan);

		if($eCompositionPlan->empty() or $plants === []) {
			return;
		}

		['converted' => $speciesConverted, 'one' => $oneId] = $plants;

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesConverted);
		$eCompositionSpecies['step'] = 0.2;
		$eCompositionSpecies['minimum'] = 0.4;
		CompositionSpeciesLib::update($eCompositionSpecies, ['step', 'minimum']);

		$build = function(string $quantity) use($eCompositionPlan, $speciesConverted): array {

			$fw = new \FailWatch();

			$this->getSpecies($eCompositionPlan, $speciesConverted)->build(['quantity'], ['quantity' => $quantity]);

			return array_column($fw->get(), 1);

		};

		$this->assertEquals(['CompositionSpecies::quantity.minimum'], $build('0.2'), 'sous le minimum');
		$this->assertEquals(['CompositionSpecies::quantity.multiple'], $build('0.5'), 'entre deux pas');
		$this->assertEquals([], $build('0.4'), 'au minimum');
		$this->assertEquals([], $build(''), 'vide');

		foreach(['CompositionSpecies::quantity.check', 'CompositionSpecies::quantity.minimum', 'CompositionSpecies::quantity.multiple'] as $fqn) {
			$this->assertTrue(AlertUi::getError($fqn) !== NULL, 'message : '.$fqn);
		}

		// Une semaine que la première taille ne sert pas à l'espèce : sa case s'ouvre sur un ajout.
		$eCompositionFormula = CompositionFormulaLib::getByPlan($eCompositionPlan)->first();
		$weeksServed = array_column(array_filter($this->getLines($eCompositionPlan), fn(array $line) => $line['formula'] === $eCompositionFormula['id'] and $line['species'] === $speciesConverted), 'week');
		$weeks = array_values(array_diff($eCompositionPlan['weeks'], $weeksServed));

		if($weeks === []) {
			$this->assertFail('la première taille sert l\'espèce toutes les semaines : aucune case ne s\'ouvrirait sur un ajout');
			return;
		}

		$week = $weeks[0];

		$addable = function() use($eCompositionPlan, $eCompositionFormula, $speciesConverted, $week): CompositionLine {

			$eCompositionPlanScreen = CompositionPlanLib::getForScreen($eCompositionPlan['structure'], $eCompositionFormula['id'], $week);

			foreach($eCompositionPlanScreen['eCompositionFormula']['grid'] as $group) {
				foreach($group['species'] as $row) {
					if($row['species']['id'] === $speciesConverted) {
						return $row['eCompositionLineAddable'];
					}
				}
			}

			return new CompositionLine();

		};

		$this->assertEquals(0.4, $addable()['quantity'], 'vide, le menu de la case préremplit le minimum');

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesConverted);
		$eCompositionSpecies['quantity'] = 0.8;
		CompositionSpeciesLib::update($eCompositionSpecies, ['quantity']);

		$this->assertEquals(0.8, $addable()['quantity'], 'posée, le menu de la case la préremplit');

		// Le plan entier, que la ligne lit pour sa source et ses pas.
		$eCompositionFormula['plan'] = $eCompositionPlan;

		$eCompositionLine = new CompositionLine([
			'week' => CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, $week),
			'species' => new CompositionSpecies(['id' => $speciesConverted]),
			'origin' => CompositionLine::USER,
		]);
		CompositionLineLib::create($eCompositionLine);

		$this->assertEquals(0.8, CompositionLine::model()->select(['quantity'])->whereId($eCompositionLine['id'])->get()['quantity'], 'une ligne ajoutée sans quantité la prend aussi');

		$this->assertEquals([], $this->setUnit($this->getSpecies($eCompositionPlan, $speciesConverted), (string)$oneId), 'pièce');
		$this->assertEquals(NULL, $this->getSpecies($eCompositionPlan, $speciesConverted)['quantity'], 'changer d\'unité efface la quantité par défaut');
		$this->assertEquals(NULL, CompositionSpeciesLib::getStepsByPlan($eCompositionPlan)[$speciesConverted]['quantity'] ?? NULL, 'et l\'ajout à la main retombe sur le minimum');

	}

	/**
	 * Ce que l'écran en dit, dans le panneau « Régler une espèce » (lot r22e) : la première tuile,
	 * « Au kilo, unité de la communauté » ; le champ de l'unité, celle de la communauté en choix vide,
	 * un groupe de poids par unité pesée dont seul celui de l'unité cochée est visible et actif, et ce
	 * que chaque choix efface ; le champ du prix, le calcul du prix converti et d'où vient ce prix,
	 * sans médiane nationale (verdict sur la planche r22).
	 */
	public function testScreen(): void {

		$eCompositionPlan = $this->createPlan();
		$plants = $this->getPlants($eCompositionPlan);

		if($eCompositionPlan->empty() or $plants === []) {
			return;
		}

		['converted' => $speciesConverted, 'one' => $oneId, 'bunch' => $bunchId] = $plants;

		$xpath = $this->getPanel($eCompositionPlan, $speciesConverted);

		$this->assertEquals('', $xpath->query('//input[@name="unit"][@checked]')->item(0)->getAttribute('value'), 'la communauté, cochée');

		$groups = [];

		foreach($xpath->query('//div[@data-wrapper="weight"]') as $node) {
			$groups[$node->getAttribute('data-unit')] = $xpath->query('.//input[@name="weight"]', $node)->item(0)->hasAttribute('disabled');
		}

		$this->assertTrue($groups[(string)$oneId], 'le poids d\'une pièce, inactif');
		$this->assertTrue(array_key_exists((string)$bunchId, $groups), 'le poids d\'une botte aussi');

		$this->setUnit($this->getSpecies($eCompositionPlan, $speciesConverted), (string)$oneId);

		$xpath = $this->getPanel($eCompositionPlan, $speciesConverted);

		$this->assertEquals((string)$oneId, $xpath->query('//input[@name="unit"][@checked]')->item(0)->getAttribute('value'), 'la pièce, cochée');
		$this->assertFalse($this->getWeightGroup($xpath, (string)$oneId), 'son poids actif');

	}

	/**
	 * La colonne des sommes (lot r13b, « mettre la qte dans l'unité de ce qui est vendu ») : une espèce
	 * passée à la pièce dit son année en pièces, dans sa case comme dans celles des semaines, et le
	 * pied garde les kilos, où l'espèce compte pour ses pièces converties par le poids d'une pièce.
	 * Des pièces et des kilos ne s'additionneraient pas.
	 *
	 * Une semaine corrigée avant le geste garde sa ligne au kilo : elle entre dans la somme en pièces,
	 * convertie par le poids d'une pièce.
	 */
	public function testGridSum(): void {

		$eCompositionPlan = $this->createPlan();
		$plants = $this->getPlants($eCompositionPlan);

		if($eCompositionPlan->empty() or $plants === []) {
			return;
		}

		['converted' => $speciesConverted, 'one' => $oneId, 'kg' => $kgId] = $plants;

		$eCompositionFormula = CompositionFormulaLib::getByPlan($eCompositionPlan)->first();
		$weekIdAmended = NULL;

		foreach(CompositionWeekLib::getByFormulaWithLines($eCompositionFormula, CompositionSpeciesLib::getByPlan($eCompositionPlan)) as $eCompositionWeek) {

			if($eCompositionWeek['cCompositionLine']->find(fn(CompositionLine $e) => $e['species']['id'] === $speciesConverted)->notEmpty()) {
				$weekIdAmended = $eCompositionWeek['id'];
				break;
			}

		}

		if($weekIdAmended === NULL) {
			$this->assertFail('la première taille ne sert pas l\'espèce : la ligne restée au kilo ne se verrait pas');
			return;
		}

		$eCompositionWeek = CompositionWeekLib::getById($weekIdAmended);
		$eCompositionWeek['amended'] = TRUE;
		CompositionWeekLib::update($eCompositionWeek, ['amended']);

		$this->setUnit($this->getSpecies($eCompositionPlan, $speciesConverted), (string)$oneId);

		$eCompositionPlanScreen = CompositionPlanLib::getForScreen($eCompositionPlan['structure'], NULL);
		$eCompositionFormula = $eCompositionPlanScreen['eCompositionFormula'];

		$row = NULL;
		$kilosRows = 0.0;

		foreach($eCompositionFormula['grid'] as $group) {

			foreach($group['species'] as $rowGroup) {

				$kilosRows += $rowGroup['sum']['kilos'] ?? 0.0;

				if($rowGroup['species']['id'] === $speciesConverted) {
					$row = $rowGroup;
				}

			}

		}

		if($row === NULL or $row['lines'] === []) {
			$this->assertFail('la taille ne sert pas l\'espèce passée à la pièce : sa somme ne se verrait pas');
			return;
		}

		$weight = CompositionLib::getSource()['weights'][$plants['plants']['converted']]['one'];

		$quantity = 0.0;
		$units = [];

		foreach($row['lines'] as $eCompositionLine) {

			$units[] = $eCompositionLine['unit']['id'];

			$quantity += match($eCompositionLine['unit']['id']) {
				$oneId => $eCompositionLine['quantity'],
				$kgId => $eCompositionLine['quantity'] / $weight,
			} * $eCompositionFormula['baskets'];

		}

		$this->assertEquals([$kgId => 1, $oneId => count($row['lines']) - 1], array_count_values($units) + [$kgId => 0], 'une ligne restée au kilo, les autres à la pièce');

		$this->assertEquals([$oneId, 'one'], [$row['sum']['eUnit']['id'], $row['sum']['eUnit']['fqn']], 'la somme dans l\'unité choisie');
		$this->assertTrue(abs($row['sum']['quantity'] - $quantity) < 0.001, 'le nombre de pièces de l\'année');
		$this->assertTrue(abs($row['sum']['kilos'] - $quantity * $weight) < 0.001, 'et les kilos qu\'elles pèsent, pour le groupe');
		$this->assertTrue(abs($eCompositionFormula['gridSum']['kilos'] - $kilosRows) < 0.001, 'le pied reste en kilos, somme des espèces pesées');

	}

	/**
	 * Le panneau « Régler une espèce » (lot r22e), chargé comme la page le charge
	 * (`compositionSpecies.p.php`).
	 */
	private function getPanel(CompositionPlan $eCompositionPlan, int $speciesId): \DOMXPath {

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesId);

		CompositionSpeciesLib::loadPriced($eCompositionSpecies);
		CompositionSpeciesLib::loadUnits($eCompositionSpecies);
		CompositionSpeciesLib::loadProducts($eCompositionSpecies);
		CompositionSpeciesLib::loadSeasonAxis($eCompositionSpecies['plan']);
		CompositionSpeciesLib::loadFrequency($eCompositionSpecies, NULL);

		$panel = new CompositionSpeciesUi()->update($eCompositionSpecies, NULL, NULL);

		return new \DOMXPath($this->getDocument($panel->body.$panel->footer));

	}

	private function getWeightGroup(\DOMXPath $xpath, string $unit): bool {

		$node = $xpath->query('//div[@data-wrapper="weight"][@data-unit="'.$unit.'"]')->item(0);

		return $xpath->query('.//input[@name="weight"]', $node)->item(0)->hasAttribute('disabled');

	}

	/**
	 * Le geste du formulaire rapide : `build()` de l'unité et du poids, puis le Lib avec ce qui s'est
	 * construit, comme `ModulePage::quick()`. Rend les refus.
	 */
	private function setUnit(CompositionSpecies $eCompositionSpecies, string $unit, string $weight = ''): array {

		$fw = new \FailWatch();

		$properties = new \PropertyList('update');
		$eCompositionSpecies->build(['unit', 'weight'], ['unit' => $unit, 'weight' => $weight], $properties);

		$errors = array_column($fw->get(), 1);

		if($errors === []) {
			CompositionSpeciesLib::update($eCompositionSpecies, $properties->getBuilt());
		}

		return $errors;

	}

	/**
	 * Écrit `$values` sur l'espèce par `build()` et le Lib, comme le panneau, et rend les erreurs.
	 */
	private function setSpecies(CompositionSpecies $eCompositionSpecies, array $values): array {

		$fw = new \FailWatch();

		$properties = new \PropertyList('update');
		$eCompositionSpecies->build(array_keys($values), $values, $properties);

		$errors = array_column($fw->get(), 1);

		if($errors === []) {
			CompositionSpeciesLib::update($eCompositionSpecies, $properties->getBuilt());
		}

		return $errors;

	}

	private function getSpecies(CompositionPlan $eCompositionPlan, int $speciesId): CompositionSpecies {

		return CompositionSpecies::model()
			->select(CompositionSpecies::getSelection() + [
				'plan' => CompositionPlan::getSelection(),
			])
			->wherePlan($eCompositionPlan)
			->whereId($speciesId)
			->get();

	}

	/**
	 * Les lignes de toutes les tailles du plan, une par ligne : `['formula', 'week', 'weekId', 'species',
	 * 'unit', 'quantity']`.
	 */
	private function getLines(CompositionPlan $eCompositionPlan): array {

		$lines = [];

		foreach(CompositionFormulaLib::getByPlan($eCompositionPlan) as $eCompositionFormula) {

			foreach(CompositionWeekLib::getByFormulaWithLines($eCompositionFormula, CompositionSpeciesLib::getByPlan($eCompositionPlan)) as $eCompositionWeek) {

				foreach($eCompositionWeek['cCompositionLine'] as $eCompositionLine) {

					$lines[] = [
						'formula' => $eCompositionFormula['id'],
						'week' => $eCompositionWeek['week'],
						'weekId' => $eCompositionWeek['id'],
						'species' => $eCompositionLine['species']['id'],
						'unit' => $eCompositionLine['unit']['id'],
						'quantity' => $eCompositionLine['quantity'],
					];

				}

			}

		}

		return $lines;

	}

	/**
	 * Les espèces du relevé de la nuit qui servent les cas du lot, toutes servies au kilo par la
	 * communauté : `converted`, pesée à la pièce par le poids courant et sans médiane à la pièce ;
	 * `median`, qui a une médiane à la botte ; `unweighed`, sans aucun poids courant. Avec les
	 * identifiants du kilo, de la pièce et de la botte. Vide, avec un échec qui le dit, quand le relevé
	 * n'en a pas.
	 */
	private function getPlants(CompositionPlan $eCompositionPlan): array {

		$source = CompositionLib::getSource();

		$units = array_column($source['offered'], 'unit', 'fqn');

		$plants = ['converted' => NULL, 'median' => NULL, 'unweighed' => NULL];

		foreach($source['units'] as $plantId => $unit) {

			if($unit['fqn'] !== 'kg') {
				continue;
			}

			$weights = $source['weights'][$plantId] ?? [];

			if(array_key_exists('one', $weights) and array_key_exists($units['one'], $source['quantities'][$plantId]) === FALSE) {
				$plants['converted'] ??= $plantId;
			} else if(array_key_exists($units['bunch'], $source['quantities'][$plantId])) {
				$plants['median'] ??= $plantId;
			} else if($weights === []) {
				$plants['unweighed'] ??= $plantId;
			}

		}

		if(in_array(NULL, $plants, TRUE)) {
			$this->assertFail('le relevé n\'a pas d\'espèce pour chaque cas : '.json_encode($plants));
			return [];
		}

		$speciesBare = CompositionLib::getSourceByPlan($eCompositionPlan)['speciesBare'];

		return array_map(fn(int $plantId) => $speciesBare[$plantId], $plants) + ['plants' => $plants] + $units;

	}

	/**
	 * Un plan écrit par le départ sur une structure qui n'en a pas, en 2099, toute l'année, toutes
	 * les espèces proposables cochées, deux tailles. La structure est celle d'un administrateur, que le
	 * test connecte : le panneau de l'espèce ne se rend qu'à qui gère la structure (`assertUpdate`).
	 */
	private function createPlan(): CompositionPlan {

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
			return new CompositionPlan();
		}

		\user\ConnectionLib::setOnline($eMember['user']);

		$eStructure = \structure\StructureLib::getById($eMember['structure']['id']);

		$eCompositionPlan = CompositionPlanLib::getForStart($eStructure, self::SEASON);
		$eCompositionPlan['weeks'] = $eCompositionPlan['seasonWeeks'];
		$eCompositionPlan['reference'] = CompositionPlan::COMMUNITY;
		$eCompositionPlan['climate'] = NULL;

		$eCompositionPlan['cCompositionSpecies']->map(fn(CompositionSpecies $e) => $e['status'] = CompositionSpecies::NATURAL);

		$eCompositionPlan['cCompositionFormula'] = new \Collection([
			new CompositionFormula(['plan' => $eCompositionPlan, 'name' => 'Le panier', 'price' => self::PRICE, 'baskets' => 10, 'gap' => 5, 'version' => 0]),
			new CompositionFormula(['plan' => $eCompositionPlan, 'name' => 'Le grand panier', 'price' => self::PRICE * 1.5, 'baskets' => 10, 'gap' => 5, 'version' => 0]),
		]);

		CompositionPlanLib::create($eCompositionPlan);

		$eCompositionPlan = CompositionPlanLib::getByStructure($eStructure);
		$eCompositionPlan['structure'] = $eStructure;

		return $eCompositionPlan;

	}

	/**
	 * Les requêtes SQL que `$run` fait, dans l'ordre, relevées par le mode debug de `Database`, qui
	 * les écrit sur la sortie en ligne de commande (même relevé que `CompositionPanelTest`).
	 */
	private function getQueries(\Closure $run): array {

		$debug = \Database::getDebug();

		\Database::setDebug(TRUE);
		ob_start();

		try {
			$run();
		} finally {
			$output = ob_get_clean();
			\Database::setDebug($debug);
		}

		preg_match_all('/ : (.*) \(\S*?[\d.]+ s\.\S*\)$/m', $output, $matches);

		return $matches[1];

	}

	private function getDocument(string $html): \DOMDocument {

		// L'analyseur HTML de libxml ne connaît pas les balises SVG des vignettes, et le dit en
		// avertissements, que le cadriciel lève.
		$libxmlErrors = libxml_use_internal_errors(TRUE);

		$document = new \DOMDocument();
		$document->loadHTML('<?xml encoding="utf-8"?>'.$html);

		libxml_clear_errors();
		libxml_use_internal_errors($libxmlErrors);

		return $document;

	}

}
?>
