<?php
namespace toolbox;

/**
 * Ce que le plan de composition refuse, et ce qu'il emporte quand il disparaît.
 *
 * Les cinq entités n'ont pas encore d'écran : ce qui les tient est entièrement dans `build()` et
 * dans les Lib, c'est-à-dire là où l'API passera aussi. Trois contrats s'y éprouvent, chacun avec
 * son pendant admis pour qu'un refus qui ne trouve rien ne passe pas pour un contrôle réussi :
 *
 * 1. les deux colonnes `json` ne portent que des semaines de 1 à 52, distinctes, non vides, et les
 *    bornes sont lues dans le schéma plutôt que recopiées ;
 * 2. `CompositionSpecies.windowWeeks` et `status` se répondent : une fenêtre n'existe que sur
 *    `CUSTOM`, et `CUSTOM` sans fenêtre est refusé ;
 * 3. `CompositionLine.unit` ne désigne qu'une unité du référentiel partagé : une unité propre à une
 *    structure vaut un identifiant différent d'une ferme à l'autre et rendrait la ligne illisible
 *    hors de chez elle ;
 * 4. la suppression d'un plan descend la chaîne entière — formules, semaines, lignes — et emporte
 *    aussi les statuts d'espèce, qui pendent au plan et non à la formule.
 *
 * ⚠️ Ce que ce découpage ne voit pas : l'unicité `(structure, season)` et `(formula, week)`, tenue
 * par la base et non par `build()`. Une seconde création lève une erreur PDO, pas un fail.
 *
 * Isolation : une transaction par méthode, annulée en tearDown().
 *
 * Lancement : dev/test package=toolbox file=Composition
 */
class CompositionTest extends \Test {

	/**
	 * Les bornes du nombre de lignes d'avant r11, 4 et 10, que ce fichier passe à la règle : ses
	 * décomptes épinglés ont été mesurés avec elles. Les bornes réglées par formule ont leurs propres
	 * tests (`CompositionRuleTest::testLinesBounds()`).
	 */
	const LINES_MIN = 4;
	const LINES_MAX = 10;

	private \structure\Structure $eStructure;
	private \plant\Plant $ePlant;
	private \plant\Plant $ePlantOutOfWindow;
	private \plant\Plant $ePlantUnsold;
	private \product\Unit $eUnitReference;
	private \product\Unit $eUnitStructure;

	public function setUp(): void {

		CompositionPlan::model()->beginTransaction();

		$this->eStructure = \structure\Structure::model()
			->select(['id'])
			->whereStatus(\structure\Structure::ACTIVE)
			->sort(['id' => SORT_ASC])
			->get();

		// L'espèce des tests est la première que la règle admet en semaine 14, où les plans de ce
		// fichier posent leurs semaines. Depuis la colonne ouverte (lot r8), une ligne se construit
		// aussi hors des semaines de récolte, sur toute espèce qui a une unité au panier
		// (`species.unsold`) : l'espèce hors de sa fenêtre est la première du référentiel que la règle
		// n'admet pas en semaine 14 et qui en a une, l'espèce refusée la première qui n'en a pas.
		// Ce sont des espèces du référentiel : une ligne de panier sert l'espèce du plan qui les lit
		// (r76), que chaque test écrit dans son plan (`createSpeciesOf()`, `getSpecies()`).
		$source = CompositionLib::getSource();
		$natural = CompositionLib::getNaturalWeeks([14], 2099, $source);

		$this->ePlant = new \plant\Plant(['id' => min(array_keys($natural))]);

		$this->ePlantOutOfWindow = \plant\Plant::model()
			->select(['id'])
			->whereStructure(NULL)
			->whereId('NOT IN', array_keys($natural))
			->whereId('IN', array_keys($source['units']))
			->sort(['id' => SORT_ASC])
			->get();

		$this->ePlantUnsold = \plant\Plant::model()
			->select(['id'])
			->whereStructure(NULL)
			->whereId('NOT IN', array_keys($source['units']))
			->sort(['id' => SORT_ASC])
			->get();

		$this->eUnitReference = \product\Unit::model()
			->select(['id'])
			->whereStructure(NULL)
			->sort(['id' => SORT_ASC])
			->get();

		$this->eUnitStructure = \product\Unit::model()
			->select(['id'])
			->whereStructure('!=', NULL)
			->sort(['id' => SORT_ASC])
			->get();

	}

	public function tearDown(): void {
		CompositionPlan::model()->rollBack();
	}

	/**
	 * Les bornes que `checkWeeks()` refuse, une par une, puis la liste qu'il admet.
	 *
	 * La borne haute est celle du schéma : la lire ici depuis `getPropertyRange()` ferait passer le
	 * test pour vert si la règle la lisait ailleurs. Elle est donc écrite en clair, et une semaine
	 * 53 comme une semaine 0 doivent tomber.
	 */
	public function testCheckWeeks(): void {

		$refused = [
			'vide' => [],
			'semaine 0' => [0],
			'semaine 53' => [53],
			'semaine négative' => [-1],
			'doublon' => [10, 12, 10],
			'chaîne' => ['12'],
			'flottant' => [12.5],
		];

		foreach($refused as $case => $weeks) {
			$this->assertFalse(CompositionLib::checkWeeks($weeks), 'semaines admises à tort, cas « '.$case.' »');
		}

		$this->assertTrue(CompositionLib::checkWeeks([1]), 'semaine 1 refusée');
		$this->assertTrue(CompositionLib::checkWeeks([52]), 'semaine 52 refusée');
		$this->assertTrue(CompositionLib::checkWeeks([14, 15, 16, 40]), 'liste ordinaire refusée');

	}

	/**
	 * Une fenêtre est un couple de semaines dans l'ordre. Ce qui n'en est pas un tombe.
	 */
	public function testCheckWindowWeeks(): void {

		$refused = [
			'nulle' => NULL,
			'vide' => [],
			'fenêtre qui n\'est pas un couple' => [[14]],
			'fenêtre de trois' => [[14, 20, 30]],
			'fenêtre à l\'envers' => [[20, 14]],
			'borne hors du calendrier' => [[14, 53]],
			'fenêtre qui n\'est pas un tableau' => [14],
		];

		foreach($refused as $case => $windowWeeks) {
			$this->assertFalse(CompositionLib::checkWindowWeeks($windowWeeks), 'fenêtre admise à tort, cas « '.$case.' »');
		}

		$this->assertTrue(CompositionLib::checkWindowWeeks([[14, 14]]), 'fenêtre d\'une semaine refusée');
		$this->assertTrue(CompositionLib::checkWindowWeeks([[14, 20], [35, 42]]), 'deux fenêtres refusées');

	}

	/**
	 * Le refus des semaines et de la saison du plan remonte bien en fail, sous le nom que
	 * `Alert.u.php` connaît. La saison est celle d'un plan de culture, bornée par la configuration de
	 * la structure (2021 à 2026 sur celle du test).
	 */
	public function testBuildPlan(): void {

		$eCompositionPlan = new CompositionPlan();

		$fw = new \FailWatch();

		$eCompositionPlan->build(CompositionPlanLib::getPropertiesCreate(), [
			'structure' => $this->eStructure['id'],
			'season' => 2026,
			'weeks' => [0, 14],
			'reference' => CompositionPlan::CULTIVATION,
		], new \PropertyList('create'));

		$this->assertEquals(['CompositionPlan::weeks.check'], array_column($fw->get(), 1), 'semaines fautives');

		$eCompositionPlan = new CompositionPlan();

		$fw = new \FailWatch();

		$eCompositionPlan->build(CompositionPlanLib::getPropertiesCreate(), [
			'structure' => $this->eStructure['id'],
			'season' => $this->eStructure->conf(\map\Configuration::class)['seasonLast'] + 1,
			'weeks' => [14, 15, 16],
			'reference' => CompositionPlan::CULTIVATION,
		], new \PropertyList('create'));

		$this->assertEquals(['CompositionPlan::season.configured'], array_column($fw->get(), 1), 'saison que la structure n\'a pas');

		$eCompositionPlan = new CompositionPlan();

		$fw = new \FailWatch();

		$eCompositionPlan->build(CompositionPlanLib::getPropertiesCreate(), [
			'structure' => $this->eStructure['id'],
			'season' => 2026,
			'weeks' => [14, 15, 16],
			'reference' => CompositionPlan::CULTIVATION,
		], new \PropertyList('create'));

		$this->assertEquals([], array_column($fw->get(), 1), 'semaines et saison valides');

	}

	/**
	 * D'où partent les saisons (r48b) : le départ poste le calendrier type ou le plan de culture,
	 * jamais la communauté, et le climat ne s'écrit que sous le calendrier, où il est exigé. Chaque
	 * refus a son message.
	 */
	public function testBuildReference(): void {

		$build = function(array $input): array {

			$eCompositionPlan = new CompositionPlan();

			$fw = new \FailWatch();

			$eCompositionPlan->build(['reference', 'climate'], $input, new \PropertyList('create'));

			return [array_column($fw->get(), 1), $eCompositionPlan];

		};

		[$fails, $eCompositionPlan] = $build(['reference' => CompositionPlan::CALENDAR, 'climate' => CompositionPlan::CONTINENTAL]);

		$this->assertEquals([], $fails, 'le calendrier et son climat');
		$this->assertEquals([CompositionPlan::CALENDAR, CompositionPlan::CONTINENTAL], [$eCompositionPlan['reference'], $eCompositionPlan['climate']], 'écrits tels quels');

		[$fails, $eCompositionPlan] = $build(['reference' => CompositionPlan::CULTIVATION, 'climate' => CompositionPlan::CONTINENTAL]);

		$this->assertEquals([], $fails, 'le plan de culture, avec le climat que le champ caché poste');
		$this->assertEquals([CompositionPlan::CULTIVATION, NULL], [$eCompositionPlan['reference'], $eCompositionPlan['climate']], 'le climat ne s\'écrit pas sous le plan de culture');

		[$fails] = $build(['reference' => CompositionPlan::CALENDAR, 'climate' => '']);

		$this->assertEquals(['CompositionPlan::climate.required'], $fails, 'le calendrier sans climat');

		[$fails] = $build(['reference' => CompositionPlan::CALENDAR]);

		$this->assertEquals(['CompositionPlan::climate.required'], $fails, 'le calendrier sans champ de climat');

		[$fails] = $build(['reference' => CompositionPlan::CALENDAR, 'climate' => 'tropical']);

		$this->assertEquals(['CompositionPlan::climate.check'], $fails, 'un climat que le calendrier n\'a pas');

		$eCompositionPlan = new CompositionPlan();

		$fw = new \FailWatch();

		$eCompositionPlan->build(['reference'], ['reference' => CompositionPlan::COMMUNITY], new \PropertyList('create'));

		$this->assertEquals(['CompositionPlan::reference.start'], array_column($fw->get(), 1), 'la communauté ne se choisit plus au départ');

		$eCompositionPlan = new CompositionPlan();

		$fw = new \FailWatch();

		$eCompositionPlan->build(['reference'], [], new \PropertyList('create'));

		$this->assertEquals(['CompositionPlan::reference.check'], array_column($fw->get(), 1), 'aucun choix posté');

		foreach(['reference.check', 'reference.start', 'climate.check', 'climate.required'] as $fqn) {
			$this->assertTrue(AlertUi::getError('CompositionPlan::'.$fqn) !== NULL, $fqn.' a son message');
		}

	}

	/**
	 * `status` et `windowWeeks` se répondent : le contrôle de la fenêtre lit l'état que le même
	 * `build()` vient de poser, ce qui n'est vrai que parce que `status` précède `windowWeeks` dans
	 * `getPropertiesUpdate()`.
	 */
	public function testBuildSpecies(): void {

		$eCompositionPlan = $this->createPlan();

		$cases = [
			'naturelle avec saison' => [
				['status' => CompositionSpecies::NATURAL, 'windowWeeks' => [[14, 20]]],
				[],
			],
			'du plan de culture avec saison' => [
				['status' => CompositionSpecies::CULTIVATION, 'windowWeeks' => [[14, 20]]],
				[],
			],
			'du plan de culture sans saison' => [
				['status' => CompositionSpecies::CULTIVATION, 'windowWeeks' => NULL],
				['CompositionSpecies::windowWeeks.check'],
			],
			'exclue avec fenêtre' => [
				['status' => CompositionSpecies::EXCLUDED, 'windowWeeks' => [[14, 20]]],
				['CompositionSpecies::windowWeeks.check'],
			],
			'sur mesure sans fenêtre' => [
				['status' => CompositionSpecies::CUSTOM, 'windowWeeks' => NULL],
				['CompositionSpecies::windowWeeks.check'],
			],
			'naturelle sans saison' => [
				['status' => CompositionSpecies::NATURAL, 'windowWeeks' => NULL],
				['CompositionSpecies::windowWeeks.check'],
			],
			'sur mesure avec fenêtre' => [
				['status' => CompositionSpecies::CUSTOM, 'windowWeeks' => [[14, 20]]],
				[],
			],
		];

		foreach($cases as $case => [$post, $expected]) {

			// Une espèce nue porte son nom, NULL, avant de se construire, comme sur la page et dans le
			// Lib : `plant.duplicate` le compare (r76).
			$eCompositionSpecies = new CompositionSpecies(['name' => NULL, 'plan' => $eCompositionPlan]);

			$fw = new \FailWatch();

			$eCompositionSpecies->build(CompositionSpeciesLib::getPropertiesCreate(), [
				'plant' => $this->ePlant['id'],
			] + $post, new \PropertyList('create'));

			$this->assertEquals($expected, array_column($fw->get(), 1), 'espèce, cas « '.$case.' »');

		}

	}

	/**
	 * Une saison postée se normalise avant son contrôle (lot r27) : les chaînes d'un formulaire
	 * deviennent des semaines, et les intervalles qu'un geste sur la grille fait se chevaucher ou se
	 * toucher se fusionnent, dans l'ordre. Un couple mal formé revient tel quel, et le contrôle le
	 * refuse.
	 */
	public function testBuildSpeciesMerge(): void {

		$eCompositionPlan = $this->createPlan();

		$build = function(array $windowWeeks) use($eCompositionPlan): array {

			$eCompositionSpecies = new CompositionSpecies(['name' => NULL, 'plan' => $eCompositionPlan]);

			$fw = new \FailWatch();

			$eCompositionSpecies->build(CompositionSpeciesLib::getPropertiesCreate(), [
				'plant' => $this->ePlant['id'],
				'status' => CompositionSpecies::CUSTOM,
				'windowWeeks' => $windowWeeks,
			], new \PropertyList('create'));

			return [$eCompositionSpecies['windowWeeks'] ?? NULL, array_column($fw->get(), 1)];

		};

		$this->assertEquals([[[14, 28], [38, 44]], []], $build([['38', '44'], ['14', '20'], ['21', '28']]), 'triée, fusionnée là où elle se touche');
		$this->assertEquals([[[10, 30]], []], $build([[10, 20], [15, 30], [12, 14]]), 'fusionnée là où elle se chevauche');
		$this->assertEquals([[[10, 12], [14, 16]], []], $build([[14, 16], [10, 12]]), 'une semaine d\'écart ne se comble pas');
		$this->assertEquals(['CompositionSpecies::windowWeeks.check'], $build([[20, 14], [10, 12]])[1], 'un couple à l\'envers est refusé');
		$this->assertEquals(['CompositionSpecies::windowWeeks.check'], $build([['12a', '14']])[1], 'une semaine qui n\'est pas un nombre est refusée');

	}

	/**
	 * Une saison compte 52 semaines, même une année ISO longue : la semaine 53 de 2026 est refusée
	 * par le contrôle du schéma, pour un plan comme pour une fenêtre de commercialisation.
	 */
	public function testBuildWeek53(): void {

		$eCompositionPlan = new CompositionPlan();

		$fw = new \FailWatch();

		$eCompositionPlan->build(CompositionPlanLib::getPropertiesCreate(), [
			'structure' => $this->eStructure['id'],
			'season' => 2026,
			'weeks' => [52, 53],
			'reference' => CompositionPlan::CULTIVATION,
		], new \PropertyList('create'));

		$this->assertEquals(['CompositionPlan::weeks.check'], array_column($fw->get(), 1), 'plan, semaine 53 en 2026');

		$eCompositionPlan = new CompositionPlan([
			'structure' => $this->eStructure,
			'season' => 2026,
			'reference' => CompositionPlan::COMMUNITY,
			'weeks' => [14],
			'cCompositionFormula' => new \Collection(),
			'cCompositionSpecies' => new \Collection(),
		]);

		CompositionPlanLib::create($eCompositionPlan);

		$eCompositionSpecies = new CompositionSpecies(['name' => NULL, 'plan' => $eCompositionPlan]);

		$fw = new \FailWatch();

		$eCompositionSpecies->build(CompositionSpeciesLib::getPropertiesCreate(), [
			'plant' => $this->ePlant['id'],
			'status' => CompositionSpecies::CUSTOM,
			'windowWeeks' => [[40, 53]],
		], new \PropertyList('create'));

		$this->assertEquals(['CompositionSpecies::windowWeeks.check'], array_column($fw->get(), 1), 'fenêtre, semaine 53 en 2026');

	}

	/**
	 * Une unité propre à une structure est refusée, une unité du référentiel passe.
	 */
	public function testBuildLineUnit(): void {

		if($this->eUnitStructure->empty()) {
			$this->assertFail('la base de dev n\'a aucune unité propre à une structure : le contrôle ne verrait rien');
			return;
		}

		$eCompositionPlan = $this->createPlan();
		$eCompositionWeek = $this->createWeek($this->createFormula($eCompositionPlan));

		$post = [
			'week' => $eCompositionWeek['id'],
			'species' => $this->createSpecies($eCompositionPlan)['id'],
			'quantity' => 1.6,
			'origin' => CompositionLine::PROPOSED,
		];

		$fw = new \FailWatch();

		new CompositionLine()->build(CompositionLineLib::getPropertiesCreate(), $post + ['unit' => $this->eUnitStructure['id']], new \PropertyList('create'));

		$this->assertEquals(['CompositionLine::unit.reference'], array_column($fw->get(), 1), 'unité de structure');

		$fw = new \FailWatch();

		new CompositionLine()->build(CompositionLineLib::getPropertiesCreate(), $post + ['unit' => $this->eUnitReference['id']], new \PropertyList('create'));

		$this->assertEquals([], array_column($fw->get(), 1), 'unité du référentiel');

	}

	/**
	 * L'écart maximum d'une formule se construit dans les bornes du schéma, 2 et 20 %, bornes
	 * comprises ; hors bornes ou vide, il est refusé sous `gap.check`, que `Alert.u.php` affiche.
	 */
	public function testBuildFormulaGap(): void {

		$eCompositionFormula = $this->createFormula($this->createPlan());

		$this->assertEquals(5, CompositionFormula::model()->select('gap')->whereId($eCompositionFormula['id'])->get()['gap'], 'le défaut du schéma à la création');

		foreach([
			1 => ['CompositionFormula::gap.check'],
			2 => [],
			20 => [],
			21 => ['CompositionFormula::gap.check'],
			'' => ['CompositionFormula::gap.check'],
		] as $gap => $expected) {

			$fw = new \FailWatch();

			$eCompositionFormula->build(['gap'], ['gap' => $gap], new \PropertyList('update'));

			$this->assertEquals($expected, array_column($fw->get(), 1), 'écart '.var_export($gap, TRUE));

		}

		$this->assertDiffer(NULL, AlertUi::getError('CompositionFormula::gap.check'), 'le refus a son message');

	}

	/**
	 * Supprimer le plan vide toute la chaîne, y compris la branche des espèces qui ne passe pas par
	 * les formules.
	 */
	public function testDeleteCascade(): void {

		$eCompositionPlan = $this->createPlan();
		$eCompositionFormula = $this->createFormula($eCompositionPlan);
		$eCompositionWeek = $this->createWeek($eCompositionFormula);
		$eCompositionSpecies = $this->createSpecies($eCompositionPlan);
		$eCompositionLine = $this->createLine($eCompositionWeek, $eCompositionSpecies);

		$this->assertEquals(1, CompositionLineLib::getByWeek($eCompositionWeek)->count(), 'ligne avant');
		$this->assertEquals(1, CompositionSpeciesLib::getByPlan($eCompositionPlan)->count(), 'espèce avant');

		CompositionPlanLib::delete($eCompositionPlan);

		$this->assertTrue(CompositionPlan::model()->select(['id'])->whereId($eCompositionPlan)->get()->empty(), 'plan');
		$this->assertTrue(CompositionFormula::model()->select(['id'])->whereId($eCompositionFormula)->get()->empty(), 'formule');
		$this->assertTrue(CompositionWeek::model()->select(['id'])->whereId($eCompositionWeek)->get()->empty(), 'semaine');
		$this->assertTrue(CompositionLine::model()->select(['id'])->whereId($eCompositionLine)->get()->empty(), 'ligne');
		$this->assertTrue(CompositionSpecies::model()->select(['id'])->whereId($eCompositionSpecies)->get()->empty(), 'espèce');

	}

	/**
	 * L'accesseur rend un élément vide plutôt que NULL quand la saison n'a pas de plan.
	 */
	public function testGetByStructure(): void {

		$this->assertTrue(CompositionPlanLib::getByStructure($this->eStructure)->empty(), 'saison sans plan');

		$eCompositionPlan = $this->createPlan();

		$this->assertEquals(
			$eCompositionPlan['id'],
			CompositionPlanLib::getByStructure($this->eStructure)['id'],
			'saison avec plan'
		);

	}

	/**
	 * Une ligne ne se construit que sur une espèce de son plan (r76), qui a une unité au panier, hors
	 * de sa fenêtre comprise (lot r8, le plus de la colonne ouverte), et une seule fois par semaine :
	 * les trois refus remontent sous un nom que `Alert.u.php` affiche. L'espèce d'un autre plan est
	 * celle d'un envoi forgé, que la grille ne poste jamais : sans l'étape `owned`, elle passerait les
	 * suivantes en lisant une source qui n'est pas la sienne.
	 */
	public function testBuildLineSpecies(): void {

		if($this->ePlantOutOfWindow->empty() or $this->ePlantUnsold->empty()) {
			$this->assertFail('le référentiel n\'a pas d\'espèce hors de la semaine 14, ou pas d\'espèce sans unité : le contrôle ne verrait rien');
			return;
		}

		$eCompositionPlan = $this->createPlan();
		$eCompositionWeek = $this->createWeek($this->createFormula($eCompositionPlan));

		$build = function(CompositionSpecies $eCompositionSpecies) use($eCompositionWeek): array {

			$fw = new \FailWatch();

			new CompositionLine(['week' => $eCompositionWeek])->build(['species'], ['species' => $eCompositionSpecies['id']], new \PropertyList('create'));

			return array_column($fw->get(), 1);

		};

		$eCompositionSpecies = $this->createSpecies($eCompositionPlan);

		// Une espèce du plan sans unité au panier est celle d'un relevé qui a cessé de la compter :
		// aucun formulaire ne l'écrit, `build()` exigeant d'une espèce que la communauté ne compte pas
		// son unité, sa quantité et son prix (r48c). Elle s'écrit donc hors de `build()`.
		$eCompositionSpeciesUnsold = new CompositionSpecies([
			'plan' => $eCompositionPlan,
			'plant' => $this->ePlantUnsold,
			'status' => CompositionSpecies::EXCLUDED,
			'windowWeeks' => NULL,
		]);

		CompositionSpeciesLib::create($eCompositionSpeciesUnsold);

		$this->assertEquals(['CompositionLine::species.unsold'], $build($eCompositionSpeciesUnsold), 'espèce sans unité au panier');
		$this->assertEquals([], $build($this->createSpeciesOf($eCompositionPlan, $this->ePlantOutOfWindow)), 'espèce hors de sa fenêtre, qui a une unité');
		$this->assertEquals([], $build($eCompositionSpecies), 'espèce candidate');

		// Une structure ne porte qu'un plan : l'autre plan est celui de la structure F1 du décor, sur une
		// saison que sa configuration n'ouvre pas. Il s'écrit hors de `build()`, qui refuserait la
		// saison (`testBuildPlan()`), et seule son espèce compte ici.
		$eCompositionPlanOther = new CompositionPlan([
			'structure' => new \structure\Structure(['id' => \structure\FixtureAiLib::F1]),
			'season' => 2099,
			'reference' => CompositionPlan::COMMUNITY,
			'weeks' => [14],
			'cCompositionFormula' => new \Collection(),
			'cCompositionSpecies' => new \Collection(),
		]);

		CompositionPlanLib::create($eCompositionPlanOther);

		$this->assertEquals(['CompositionLine::species.owned'], $build($this->createSpecies($eCompositionPlanOther)), 'la même espèce, dans un autre plan');

		$this->createLine($eCompositionWeek, $eCompositionSpecies);

		$this->assertEquals(['CompositionLine::species.duplicate'], $build($eCompositionSpecies), 'espèce déjà servie');

		foreach(['CompositionLine::species.check', 'CompositionLine::species.owned', 'CompositionLine::species.unsold', 'CompositionLine::species.duplicate'] as $fqn) {
			$this->assertDiffer(NULL, AlertUi::getError($fqn), $fqn.' a son message');
		}

	}

	/**
	 * La règle d'un seul chou par panier (`CompositionLib::CABBAGE`) ne tient que la proposition : à la
	 * main, une semaine qui sert un chou en accepte un second (r34d, retour du 30/09, « le
	 * user fait ce qu'il veut »), comme toute autre espèce. Les choux sont les candidates de la
	 * semaine 14 dont le nom contient « chou ».
	 */
	public function testBuildLineCabbage(): void {

		$source = CompositionLib::getSource();

		$candidates = CompositionLib::getCandidates(14, 2099, NULL, $source);
		$cabbages = array_values(array_filter($candidates, fn(int $plantId) => CompositionLib::isCabbage($plantId, $source)));
		$others = array_values(array_filter($candidates, fn(int $plantId) => CompositionLib::isCabbage($plantId, $source) === FALSE));

		if(count($cabbages) < 2 or $others === []) {
			$this->assertFail('la semaine 14 n\'a pas deux choux et une autre espèce candidats : le contrôle ne verrait rien');
			return;
		}

		$eCompositionPlan = $this->createPlan();
		$eCompositionFormula = $this->createFormula($eCompositionPlan);

		$eCompositionWeek = $this->createWeek($eCompositionFormula);
		$eCompositionWeek['formula'] = $eCompositionFormula;

		// Les choux et l'autre espèce sont des candidates de la semaine 14 : le plan les porte, chacune
		// sous l'espèce nue qui la lit (r76).
		$this->createSeasons($eCompositionPlan, $source);

		$speciesBare = CompositionLib::getSourceByPlan($eCompositionPlan)['speciesBare'];

		$build = function(int $plantId) use($eCompositionWeek, $speciesBare): array {

			$fw = new \FailWatch();

			new CompositionLine(['week' => $eCompositionWeek])->build(['species'], ['species' => $speciesBare[$plantId]], new \PropertyList('create'));

			return array_column($fw->get(), 1);

		};

		$this->assertEquals([], $build($cabbages[1]), 'un premier chou');

		// Comme la case de la grille (`compositionLine:doCreate`) : l'espèce seule, le reste vient de la règle.
		CompositionLineLib::create(new CompositionLine([
			'week' => $eCompositionWeek,
			'species' => new CompositionSpecies(['id' => $speciesBare[$cabbages[0]]]),
			'origin' => CompositionLine::USER,
		]));

		$this->assertEquals([], $build($cabbages[1]), 'un second chou, à la main');
		$this->assertEquals([], $build($others[0]), 'une espèce qui n\'est pas un chou');

	}

	/**
	 * Le pas et le minimum d'une espèce (revue v12) : le pas d'une unité entière est entier, et le
	 * minimum se compte en pas, celui qu'on règle ou à défaut celui de l'unité. Chaque refus remonte
	 * sous un nom que `Alert.u.php` affiche.
	 */
	public function testBuildSpeciesStep(): void {

		$source = CompositionLib::getSource();

		$whole = NULL;
		$decimal = NULL;

		foreach($source['units'] as $plantId => $unit) {

			if($unit['step'] === 1.0) {
				$whole ??= $plantId;
			} else {
				$decimal ??= $plantId;
			}

		}

		if($whole === NULL or $decimal === NULL) {
			$this->assertFail('le relevé n\'a pas d\'espèce à l\'unité entière et à l\'unité décimale : le contrôle ne verrait rien');
			return;
		}

		$build = function(int $plantId, array $values): array {

			$fw = new \FailWatch();

			new CompositionSpecies(['plant' => new \plant\Plant(['id' => $plantId]), 'unit' => new \product\Unit()])->build(['step', 'minimum'], $values);

			return array_column($fw->get(), 1);

		};

		$this->assertEquals([], $build($decimal, ['step' => '0.25', 'minimum' => '0.5']), 'un pas et un minimum sur une unité décimale');
		$this->assertEquals([], $build($whole, ['step' => '2', 'minimum' => '4']), 'un pas entier sur une unité entière');
		$this->assertEquals([], $build($whole, ['step' => '', 'minimum' => '']), 'rien de réglé');
		$this->assertEquals(['CompositionSpecies::step.whole'], $build($whole, ['step' => '0.5', 'minimum' => '']), 'une demi-botte');
		$this->assertEquals(['CompositionSpecies::minimum.multiple'], $build($decimal, ['step' => '0.25', 'minimum' => '0.3']), 'un minimum entre deux pas');
		$this->assertEquals(['CompositionSpecies::minimum.multiple'], $build($whole, ['step' => '', 'minimum' => '1.5']), 'un minimum entre deux pas de l\'unité');
		$this->assertEquals(['CompositionSpecies::step.check'], $build($decimal, ['step' => '0', 'minimum' => '']), 'un pas nul');

		foreach(['CompositionSpecies::step.check', 'CompositionSpecies::step.whole', 'CompositionSpecies::minimum.check', 'CompositionSpecies::minimum.multiple'] as $fqn) {
			$this->assertDiffer(NULL, AlertUi::getError($fqn), $fqn.' a son message');
		}

	}

	/**
	 * Une quantité saisie à la main tient les bornes de la quantité proposée (revue v12) : jamais sous
	 * le minimum de l'espèce, et en pas entiers. Éprouvé au pas de l'unité et au minimum de la
	 * communauté (lot r25), puis au pas et au minimum réglés sur l'espèce du plan, qui passent devant.
	 */
	public function testBuildLineQuantity(): void {

		$source = CompositionLib::getSource();

		$eCompositionPlan = $this->createPlan();
		$eCompositionFormula = $this->createFormula($eCompositionPlan);

		$eCompositionWeek = $this->createWeek($eCompositionFormula);
		$eCompositionWeek['formula'] = $eCompositionFormula;

		$eCompositionSpecies = $this->createSpecies($eCompositionPlan);

		CompositionLineLib::create(new CompositionLine([
			'week' => $eCompositionWeek,
			'species' => $eCompositionSpecies,
			'origin' => CompositionLine::USER,
		]));

		$eCompositionLine = CompositionLineLib::getByWeekAndSpecies($eCompositionWeek, $eCompositionSpecies);
		$eCompositionLine['week'] = $eCompositionWeek;

		$build = function(float $quantity) use($eCompositionLine): array {

			$fw = new \FailWatch();

			$eCompositionLine->build(['quantity'], ['quantity' => (string)$quantity]);

			return array_column($fw->get(), 1);

		};

		// Le pas par défaut est celui du plan : au kilo, il dépend du prix de l'espèce (lot r18). Le
		// minimum par défaut est celui de la communauté à ce pas (lot r25).
		$default = CompositionLib::getStep($eCompositionSpecies['id'], CompositionLib::getSourceByPlan($eCompositionPlan), []);
		$unit = $default['step'];

		$this->assertTrue($default['minimum'] > $unit, 'la communauté donne à l\'espèce des tests un minimum de plus d\'un pas');
		$this->assertEquals(['CompositionLine::quantity.minimum'], $build($default['minimum'] - $unit), 'sous le minimum de la communauté');
		$this->assertEquals([], $build($default['minimum']), 'au minimum de la communauté');
		$this->assertEquals([], $build($default['minimum'] + 3 * $unit), 'trois pas de l\'unité au-dessus');
		$this->assertEquals(['CompositionLine::quantity.step'], $build($default['minimum'] + 2.5 * $unit), 'entre deux pas de l\'unité');

		$eCompositionSpecies['step'] = 2 * $unit;
		$eCompositionSpecies['minimum'] = 4 * $unit;

		CompositionSpeciesLib::update($eCompositionSpecies, ['step', 'minimum']);

		$this->assertEquals(['CompositionLine::quantity.minimum'], $build(2 * $unit), 'sous le minimum réglé');
		$this->assertEquals(['CompositionLine::quantity.step'], $build(5 * $unit), 'entre deux pas réglés');
		$this->assertEquals([], $build(6 * $unit), 'trois pas réglés');

		foreach(['CompositionLine::quantity.minimum', 'CompositionLine::quantity.step'] as $fqn) {
			$this->assertDiffer(NULL, AlertUi::getError($fqn), $fqn.' a son message');
		}

	}

	/**
	 * Le pas par défaut d'une espèce au kilo suit son prix (lot r18, demandé le 27/09) :
	 * 0,2 kg à 3 € le kilo, et à 10 € tout rond, la borne étant stricte ; 0,1 kg à 13 € le kilo. Une
	 * espèce à la pièce garde le pas d'une pièce quel que soit son prix, et un pas réglé par la
	 * structure passe devant celui du prix. Le minimum par défaut, celui de la communauté (lot r25),
	 * s'arrondit au pas du prix, et une ligne se construit au pas du prix.
	 */
	public function testKiloSteps(): void {

		$source = CompositionLib::getSource();

		$plantIdKilo = $this->ePlant['id'];
		$plantIdWhole = current(array_keys(array_filter($source['units'], fn(array $unit) => $unit['fqn'] === 'one')));

		$this->assertEquals('kg', $source['units'][$plantIdKilo]['fqn'], 'l\'espèce des tests est au kilo');

		if($plantIdWhole === FALSE) {
			$this->assertFail('aucune espèce à la pièce dans la source : le contrôle ne verrait rien');
			return;
		}

		$eCompositionPlan = $this->createPlan();
		$eCompositionFormula = $this->createFormula($eCompositionPlan);
		$eCompositionWeek = $this->createWeek($eCompositionFormula);
		$eCompositionWeek['formula'] = $eCompositionFormula;

		$eCompositionSpeciesKilo = $this->createSpecies($eCompositionPlan);
		$eCompositionSpeciesWhole = $this->createSpeciesOf($eCompositionPlan, new \plant\Plant(['id' => $plantIdWhole]), 0.2);

		$step = function(float $price) use($eCompositionPlan, $eCompositionSpeciesKilo, $eCompositionSpeciesWhole): array {

			foreach([$eCompositionSpeciesKilo, $eCompositionSpeciesWhole] as $eCompositionSpecies) {
				$eCompositionSpecies['price'] = $price;
				CompositionSpeciesLib::update($eCompositionSpecies, ['price']);
			}

			$source = CompositionLib::getSourceByPlan($eCompositionPlan);
			$steps = CompositionSpeciesLib::getStepsByPlan($eCompositionPlan);

			return [
				CompositionLib::getStep($eCompositionSpeciesKilo['id'], $source, $steps),
				CompositionLib::getStep($eCompositionSpeciesWhole['id'], $source, $steps),
			];

		};

		$minimumCommunity = $source['units'][$plantIdKilo]['minimum'];

		[$kilo, $whole] = $step(3.0);

		$this->assertEquals(['step' => CompositionLib::KILO_STEP, 'minimum' => CompositionLib::getMinimumCommunity($minimumCommunity, CompositionLib::KILO_STEP)], $kilo, 'à 3 € le kilo, 0,2 kg, et le minimum de la communauté à ce pas');
		$this->assertEquals(['step' => 1.0, 'minimum' => 1.0], $whole, 'à la pièce, une pièce');

		[$kilo] = $step(CompositionLib::KILO_PRICE_FINE);

		$this->assertEquals(CompositionLib::KILO_STEP, $kilo['step'], 'à 10 € le kilo tout rond, toujours 0,2 kg');

		[$kilo, $whole] = $step(13.0);

		$this->assertEquals(['step' => CompositionLib::KILO_STEP_FINE, 'minimum' => CompositionLib::getMinimumCommunity($minimumCommunity, CompositionLib::KILO_STEP_FINE)], $kilo, 'à 13 € le kilo, 0,1 kg');
		$this->assertEquals(['step' => 1.0, 'minimum' => 1.0], $whole, 'à la pièce, toujours une pièce');

		// Une ligne se construit au pas du prix : un nombre impair de dixièmes passe à 13 €, pas à 3 €.
		// Il est pris au-dessus du minimum aux deux pas : un multiple de 0,2 plus 0,1.
		$minimumCoarse = CompositionLib::getMinimumCommunity($minimumCommunity, CompositionLib::KILO_STEP);
		$odd = round($minimumCoarse + 0.1, 2);
		$even = round($minimumCoarse + 0.2, 2);

		CompositionLineLib::create(new CompositionLine([
			'week' => $eCompositionWeek,
			'species' => $eCompositionSpeciesKilo,
			'origin' => CompositionLine::USER,
		]));

		$eCompositionLine = CompositionLineLib::getByWeekAndSpecies($eCompositionWeek, $eCompositionSpeciesKilo);
		$eCompositionLine['week'] = $eCompositionWeek;

		$build = function(float $quantity) use($eCompositionLine): array {

			$fw = new \FailWatch();

			$eCompositionLine->build(['quantity'], ['quantity' => (string)$quantity]);

			return array_column($fw->get(), 1);

		};

		$this->assertEquals([], $build($odd), $odd.' kg à 13 € le kilo');

		$step(3.0);

		$this->assertEquals(['CompositionLine::quantity.step'], $build($odd), $odd.' kg refusé à 3 € le kilo');
		$this->assertEquals([], $build($even), $even.' kg admis');

		// Le minimum de l'espèce se compte au même pas quand elle n'en règle aucun.
		$fw = new \FailWatch();
		$eCompositionSpeciesKilo->build(['minimum'], ['minimum' => '0.3'], new \PropertyList('update'));
		$this->assertEquals(['CompositionSpecies::minimum.multiple'], array_column($fw->get(), 1), 'un minimum de 0,3 kg refusé à 3 € le kilo');

		$step(13.0);

		$fw = new \FailWatch();
		$eCompositionSpeciesKilo->build(['minimum'], ['minimum' => '0.3'], new \PropertyList('update'));
		$this->assertEquals([], array_column($fw->get(), 1), 'admis à 13 € le kilo');

		// Un pas réglé par la structure passe devant celui du prix.
		$eCompositionSpeciesKilo['step'] = 0.5;
		$eCompositionSpeciesKilo['minimum'] = NULL;
		CompositionSpeciesLib::update($eCompositionSpeciesKilo, ['step', 'minimum']);

		[$kilo] = $step(13.0);

		$this->assertEquals(['step' => 0.5, 'minimum' => 0.5], $kilo, 'le pas de la structure, à tout prix');

	}

	/**
	 * Une case allumée écrit une ligne `USER` à l'unité, la quantité et le prix que la règle donne à
	 * l'espèce, sans quantité proposée, et marque sa semaine seule comme corrigée.
	 */
	public function testCreateLineUser(): void {

		[$eCompositionPlan, $eCompositionFormula] = $this->createComposed();

		$eCompositionWeek = CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, 14);
		$eCompositionSpecies = $this->getUnserved($eCompositionPlan, $eCompositionWeek);

		if($eCompositionSpecies->empty()) {
			$this->assertFail('la semaine 14 sert déjà toutes ses candidates : le contrôle ne verrait rien');
			return;
		}

		$eCompositionLine = new CompositionLine(['week' => $eCompositionWeek, 'origin' => CompositionLine::USER]);

		$fw = new \FailWatch();

		$eCompositionLine->build(['species'], ['species' => $eCompositionSpecies['id']], new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('ligne refusée : '.json_encode($fw->get())));

		CompositionLineLib::create($eCompositionLine);

		$source = CompositionLib::getSourceByPlan($eCompositionPlan);

		$reference = CompositionLib::getLineReference(14, $eCompositionSpecies['id'], $source, $source['pricesPlan'], []);

		$eCompositionLineWritten = CompositionLineLib::getByWeekAndSpecies($eCompositionWeek, $eCompositionSpecies);

		$this->assertEquals(CompositionLine::USER, $eCompositionLineWritten['origin'], 'origine');
		$this->assertEquals($reference['unit'], $eCompositionLineWritten['unit']['id'], 'unité de la règle');
		$this->assertEquals((float)$reference['quantity'], (float)$eCompositionLineWritten['quantity'], 'quantité de la règle');
		$this->assertEquals(NULL, $eCompositionLineWritten['quantityProposed'], 'aucune quantité proposée');
		$this->assertEquals($reference['unitPrice'] === NULL ? NULL : (float)$reference['unitPrice'], $eCompositionLineWritten['unitPrice'] === NULL ? NULL : (float)$eCompositionLineWritten['unitPrice'], 'prix de la règle');

		$this->assertEquals([14], $this->getAmended($eCompositionFormula), 'seule la semaine 14 est corrigée');

	}

	/**
	 * Une case servie éteinte retire sa ligne et elle seule, et marque la semaine comme corrigée. Une
	 * semaine qui part avec ses lignes, ou que la règle recompose, ne marque rien.
	 */
	public function testDeleteLine(): void {

		[$eCompositionPlan, $eCompositionFormula] = $this->createComposed();

		$eCompositionWeek = CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, 15);
		$cCompositionLine = CompositionLineLib::getByWeek($eCompositionWeek);

		if($cCompositionLine->count() < 2) {
			$this->assertFail('la semaine 15 a moins de deux lignes : le contrôle ne verrait rien');
			return;
		}

		$eCompositionLine = $cCompositionLine->first();
		$eCompositionLine['week'] = $eCompositionWeek;

		CompositionLineLib::delete($eCompositionLine);

		$this->assertEquals(
			array_values(array_diff($cCompositionLine->getColumn('id'), [$eCompositionLine['id']])),
			array_values(CompositionLineLib::getByWeek($eCompositionWeek)->getColumn('id')),
			'la ligne seule est partie'
		);

		$this->assertEquals([15], $this->getAmended($eCompositionFormula), 'seule la semaine 15 est corrigée');

		CompositionWeekLib::delete(CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, 16));

		$this->assertEquals([15], $this->getAmended($eCompositionFormula), 'une semaine supprimée ne marque rien');

	}

	/**
	 * « Revenir à la proposition » rend à la semaine les lignes que la règle lui propose et lève sa
	 * marque ; une autre semaine corrigée garde sa correction.
	 */
	public function testRecomposeWeek(): void {

		[$eCompositionPlan, $eCompositionFormula] = $this->createComposed();

		$speciesProposed = $this->getSpeciesServed($eCompositionFormula, 14);

		$eCompositionWeek = CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, 14);
		$eCompositionSpecies = $this->getUnserved($eCompositionPlan, $eCompositionWeek);

		if($eCompositionSpecies->empty()) {
			$this->assertFail('la semaine 14 sert déjà toutes ses candidates : le contrôle ne verrait rien');
			return;
		}

		CompositionLineLib::create(new CompositionLine(['week' => $eCompositionWeek, 'species' => $eCompositionSpecies, 'origin' => CompositionLine::USER]));

		$eCompositionWeekOther = CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, 15);
		$eCompositionLineOther = CompositionLineLib::getByWeek($eCompositionWeekOther)->first();
		$eCompositionLineOther['week'] = $eCompositionWeekOther;

		CompositionLineLib::delete($eCompositionLineOther);

		$speciesOther = $this->getSpeciesServed($eCompositionFormula, 15);

		$this->assertEquals([14, 15], $this->getAmended($eCompositionFormula), 'deux semaines corrigées');

		$source = CompositionLib::getSourceByPlan($eCompositionPlan);

		CompositionLib::recomposeWeek($eCompositionPlan, $eCompositionFormula, CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, 14), $source, $source['pricesPlan']);

		$this->assertEquals($speciesProposed, $this->getSpeciesServed($eCompositionFormula, 14), 'la semaine reprend sa proposition');
		$this->assertEquals(0, CompositionLine::model()->whereWeek(CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, 14))->whereOrigin(CompositionLine::USER)->count(), 'plus aucune ligne à la main');
		$this->assertEquals([15], $this->getAmended($eCompositionFormula), 'l\'autre semaine reste corrigée');
		$this->assertEquals($speciesOther, $this->getSpeciesServed($eCompositionFormula, 15), 'l\'autre semaine garde sa correction');

	}

	/**
	 * Le prix et l'écart recomposent toute la formule : les corrections partent, ce que « Régler les
	 * paniers » annonce (`CompositionFormulaLib::applyRecompose()`, `CompositionFormulaUi::getRecomposeLoss()`),
	 * et les autres formules du plan y sont nommées.
	 */
	public function testRecomposeFormula(): void {

		[$eCompositionPlan, $eCompositionFormula] = $this->createComposed();

		$eCompositionWeek = CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, 16);
		$eCompositionLine = CompositionLineLib::getByWeek($eCompositionWeek)->first();
		$eCompositionLine['week'] = $eCompositionWeek;

		CompositionLineLib::delete($eCompositionLine);

		$eCompositionFormulaOther = $this->createFormula($eCompositionPlan, 'Autre panier de test');

		$eCompositionFormula['plan'] = CompositionPlanLib::getByStructure($this->eStructure);
		$eCompositionFormula['plan']['structure'] = $this->eStructure;

		CompositionFormulaLib::applyRecompose($eCompositionFormula, CompositionFormulaLib::getByPlan($eCompositionPlan), CompositionWeekLib::getAmendedByFormula($eCompositionFormula));

		$this->assertEquals([16], $eCompositionFormula['weeksAmended'], 'semaines annoncées');
		$this->assertEquals([$eCompositionFormulaOther['name']], array_values($eCompositionFormula['formulasOther']), 'autres formules annoncées');

		$eCompositionFormula['price'] = 22.0;

		CompositionFormulaLib::update($eCompositionFormula, ['price']);

		$this->assertEquals([], $this->getAmended($eCompositionFormula), 'plus aucune semaine corrigée');

		CompositionFormulaLib::applyRecompose($eCompositionFormula, CompositionFormulaLib::getByPlan($eCompositionPlan), CompositionWeekLib::getAmendedByFormula($eCompositionFormula));

		$this->assertEquals(NULL, CompositionFormulaUi::getRecomposeLoss($eCompositionFormula), 'plus rien à confirmer');

	}

	/**
	 * Une quantité réglée sur la carte marque la semaine comme corrigée, comme une case allumée ou
	 * éteinte : « Revenir à la proposition » doit pouvoir la rendre. Un autre réglage de la ligne
	 * ne marque rien, pour que le pendant prouve que la marque vient bien de la quantité.
	 */
	public function testUpdateLineQuantity(): void {

		[$eCompositionPlan, $eCompositionFormula] = $this->createComposed();

		$eCompositionWeek = CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, 15);

		$eCompositionLine = CompositionLineLib::getByWeek($eCompositionWeek)->first();
		$eCompositionLine['week'] = $eCompositionWeek;

		CompositionLineLib::update($eCompositionLine, ['origin']);

		$this->assertEquals([], $this->getAmended($eCompositionFormula), 'un autre réglage ne marque rien');

		$eCompositionLine['quantity'] = (float)$eCompositionLine['quantity'] + 1;

		CompositionLineLib::update($eCompositionLine, ['quantity']);

		$this->assertEquals((float)$eCompositionLine['quantity'], (float)CompositionLineLib::getByWeekAndSpecies($eCompositionWeek, $eCompositionLine['species'])['quantity'], 'la quantité est écrite');
		$this->assertEquals([15], $this->getAmended($eCompositionFormula), 'seule la semaine 15 est corrigée');

	}

	/**
	 * Une fenêtre réglée à la main (`CUSTOM`) resserre ce que la composition sert : l'espèce n'est
	 * proposée que sur ses semaines, et la grille ne teinte pas sa case ailleurs. Elle s'y ajoute
	 * pourtant à la main, depuis la colonne ouverte (lot r8), comme toute espèce hors de ses
	 * semaines de récolte qui a une unité au panier.
	 *
	 * L'espèce éprouvée est une espèce que la règle sert en semaine 16 sur la saison de la
	 * communauté : sans cette condition, la voir absente de la semaine 16 ne prouverait rien. Les
	 * autres espèces portent la leur, pour que la semaine 16 ait un panier.
	 */
	public function testCustomWindow(): void {

		$source = CompositionLib::getSource();

		$eCompositionPlan = $this->createPlan();
		$prices = CompositionLib::getPrices($eCompositionPlan, $source);

		$proposal = CompositionLib::propose(18.0, 5, self::LINES_MIN, self::LINES_MAX, [14, 15, 16], NULL, 2099, $source, $prices, []);

		if($proposal[16]['lines'] === []) {
			$this->assertFail('la règle ne sert rien en semaine 16 : le contrôle ne verrait rien');
			return;
		}

		// La source de la communauté sert chaque espèce sous son propre id : c'est l'espèce du
		// référentiel, que le plan lit ensuite sous l'id de son espèce (r76).
		$plantId = $proposal[16]['lines'][0]['species'];

		$this->createSeasons($eCompositionPlan, $source, [$plantId]);

		CompositionSpeciesLib::create(new CompositionSpecies([
			'plan' => $eCompositionPlan,
			'plant' => new \plant\Plant(['id' => $plantId]),
			'status' => CompositionSpecies::CUSTOM,
			'windowWeeks' => [[14, 15]],
		]));

		$sourcePlan = CompositionLib::getSourceByPlan($eCompositionPlan);
		$speciesId = $sourcePlan['speciesBare'][$plantId];

		$windows = CompositionSpeciesLib::getWindowsByPlan($eCompositionPlan);

		$this->assertEquals([14, 15], $windows[$speciesId], 'la saison dépliée');

		$eCompositionFormula = $this->createFormula($eCompositionPlan);

		CompositionLib::compose($eCompositionPlan, $eCompositionFormula, $sourcePlan, $sourcePlan['pricesPlan']);

		$this->assertFalse(in_array($speciesId, $this->getSpeciesServed($eCompositionFormula, 16)), 'la composition ne sert plus l\'espèce hors de sa fenêtre');

		$cCompositionSpecies = CompositionSpeciesLib::getByPlan($eCompositionPlan);
		$grid = CompositionLib::getGrid(CompositionWeekLib::getByFormulaWithLines($eCompositionFormula, $cCompositionSpecies), 2099, $windows, $sourcePlan, $cCompositionSpecies);
		$weeksSeason = [];

		foreach($grid as $group) {
			foreach($group['species'] as $row) {
				if($row['species']['id'] === $speciesId) {
					$weeksSeason = array_keys($row['weeksSeason']);
				}
			}
		}

		$this->assertFalse(in_array(16, $weeksSeason), 'la grille ne passe pas au vert la case hors de la fenêtre');

		$this->assertFalse(CompositionLib::isCandidate(16, $speciesId, 2099, $windows, $sourcePlan), 'hors de la fenêtre, l\'espèce n\'est pas candidate');

		$fw = new \FailWatch();

		new CompositionLine(['week' => CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, 16)])->build(['species'], ['species' => $speciesId], new \PropertyList('create'));

		$this->assertEquals([], array_column($fw->get(), 1), 'une espèce s\'ajoute hors de sa fenêtre (lot r8)');

	}

	/**
	 * Ce que « Recommencer les paniers » chiffre, chaque perte comptée à part, pour que le message dise
	 * ce que le prochain départ remplacera et seulement cela. Les tailles et leurs semaines corrigées
	 * sont celles que « Modifier les paniers » lit à son ouverture (`CompositionPlanLib::loadSettings()`).
	 */
	public function testLosses(): void {

		[$eCompositionPlan, $eCompositionFormula] = $this->createComposed();

		$eCompositionPlan['structure'] = $this->eStructure;

		CompositionPlanLib::loadSettings($eCompositionPlan);

		$this->assertEquals([
			'amended' => 0,
		], CompositionPlanLib::getLosses($eCompositionPlan), 'un panier, aucune correction');

		$eCompositionWeek = CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, 15);
		$eCompositionLine = CompositionLineLib::getByWeek($eCompositionWeek)->first();
		$eCompositionLine['week'] = $eCompositionWeek;

		CompositionLineLib::delete($eCompositionLine);

		$this->createFormula($eCompositionPlan, 'Autre panier de test');

		CompositionPlanLib::loadSettings($eCompositionPlan);

		$this->assertEquals([
			'amended' => 1,
		], CompositionPlanLib::getLosses($eCompositionPlan), 'deux paniers, une semaine corrigée');

	}

	/**
	 * « Rejouer les paniers » passe la formule au tirage suivant et refait sa proposition : ses
	 * corrections partent avec ses semaines, l'autre formule du plan ne bouge pas, et le tirage
	 * écrit est celui que la règle rend pour cette version.
	 */
	public function testReplay(): void {

		[$eCompositionPlan, $eCompositionFormula] = $this->createComposed();

		$eCompositionFormulaOther = $this->createFormula($eCompositionPlan, 'Autre panier de test');
		$eCompositionFormulaOther['version'] = 0;
		$source = CompositionLib::getSourceByPlan($eCompositionPlan);

		CompositionLib::compose($eCompositionPlan, $eCompositionFormulaOther, $source, $source['pricesPlan']);

		$eCompositionWeekOther = CompositionWeekLib::getByFormulaAndWeek($eCompositionFormulaOther, 15);
		$eCompositionLineOther = CompositionLineLib::getByWeek($eCompositionWeekOther)->first();
		$eCompositionLineOther['week'] = $eCompositionWeekOther;

		CompositionLineLib::delete($eCompositionLineOther);

		$linesOther = CompositionLine::model()->whereWeek('IN', CompositionWeekLib::getByFormula($eCompositionFormulaOther))->getColumn('id');

		$eCompositionWeek = CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, 14);
		$eCompositionSpecies = $this->getUnserved($eCompositionPlan, $eCompositionWeek);

		if($eCompositionSpecies->empty()) {
			$this->assertFail('la semaine 14 sert déjà toutes ses candidates : le contrôle ne verrait rien');
			return;
		}

		CompositionLineLib::create(new CompositionLine(['week' => $eCompositionWeek, 'species' => $eCompositionSpecies, 'origin' => CompositionLine::USER]));

		$this->assertEquals([14], $this->getAmended($eCompositionFormula), 'une semaine corrigée avant le rejeu');

		$eCompositionFormula = CompositionFormulaLib::getById($eCompositionFormula['id'], CompositionFormula::getSelection() + [
			'plan' => CompositionPlan::getSelection(),
		]);

		$this->assertEquals(0, $eCompositionFormula['version'], 'tirage 0 avant le rejeu');

		CompositionFormulaLib::replay($eCompositionFormula);

		$this->assertEquals(1, CompositionFormulaLib::getById($eCompositionFormula['id'])['version'], 'tirage 1 écrit');
		$this->assertEquals([], $this->getAmended($eCompositionFormula), 'plus aucune semaine corrigée');
		$this->assertEquals(0, CompositionLine::model()->whereWeek('IN', CompositionWeekLib::getByFormula($eCompositionFormula))->whereOrigin(CompositionLine::USER)->count(), 'plus aucune ligne à la main');

		// La source d'un plan sert ses espèces sous leur id (r76) : la règle les lit dans ses saisons,
		// que tout plan passe à `propose()` (`CompositionLib::proposeFormula()`).
		$proposal = CompositionLib::propose(18.0, 5, self::LINES_MIN, self::LINES_MAX, [14, 15, 16], CompositionSpeciesLib::getWindowsByPlan($eCompositionPlan), 2099, $source, $source['pricesPlan'], [], 1);

		foreach([14, 15, 16] as $week) {

			$speciesIds = array_column($proposal[$week]['lines'], 'species');
			sort($speciesIds);

			$this->assertEquals($speciesIds, $this->getSpeciesServed($eCompositionFormula, $week), 'semaine '.$week.' au tirage 1');

		}

		$this->assertEquals(0, CompositionFormulaLib::getById($eCompositionFormulaOther['id'])['version'], 'l\'autre formule garde son tirage');
		$this->assertEquals([15], $this->getAmended($eCompositionFormulaOther), 'et sa correction');
		$this->assertEquals($linesOther, CompositionLine::model()->whereWeek('IN', CompositionWeekLib::getByFormula($eCompositionFormulaOther))->getColumn('id'), 'et ses lignes');

		CompositionFormulaLib::replay($eCompositionFormula);

		$this->assertEquals(2, CompositionFormulaLib::getById($eCompositionFormula['id'])['version'], 'le rejeu suivant avance encore');

	}

	/**
	 * Recommencer les paniers rend une taille au tirage 0, même après un rejeu.
	 */
	public function testRestartResetsVersion(): void {

		CompositionPlanLib::create($this->getStart());

		$eCompositionPlan = CompositionPlanLib::getByStructure($this->eStructure);
		$eCompositionPlan['structure'] = $this->eStructure;

		$eCompositionFormula = CompositionFormulaLib::getByPlan($eCompositionPlan)->first();
		$eCompositionFormula['plan'] = $eCompositionPlan;

		CompositionFormulaLib::replay($eCompositionFormula);

		$this->assertEquals(1, CompositionFormulaLib::getByPlan($eCompositionPlan)->first()['version'], 'rejouée');

		CompositionPlanLib::restart($this->getStart());

		$this->assertEquals([0], array_values(CompositionFormulaLib::getByPlan(CompositionPlanLib::getByStructure($this->eStructure))->getColumn('version')), 'une taille au tirage 0');

	}

	/**
	 * L'envoi du départ (`composition:doStart`) : deux tailles avec leur nombre de paniers, une
	 * période, trois espèces décochées. Le plan porte les deux tailles et les semaines envoyées, les
	 * trois espèces sont retirées, et aucune ligne proposée ne sert une espèce retirée.
	 */
	public function testStart(): void {

		$eCompositionPlan = $this->getStart([
			['name' => 'Le petit panier', 'price' => 16.0, 'baskets' => 20],
			['name' => 'Le grand panier', 'price' => 28.0, 'baskets' => 15],
		]);

		$excluded = array_slice(array_keys($eCompositionPlan['cCompositionSpecies']->getArrayCopy()), 0, 3);

		// Sans plan de culture 2099, le départ coche les espèces que la structure vend : l'envoi
		// qu'on éprouve ici part d'une liste entièrement cochée, dont il décoche trois espèces.
		foreach($eCompositionPlan['cCompositionSpecies'] as $eCompositionSpecies) {
			$eCompositionSpecies['status'] = CompositionSpecies::NATURAL;
		}

		foreach($excluded as $plantId) {
			$eCompositionPlan['cCompositionSpecies'][$plantId]['status'] = CompositionSpecies::EXCLUDED;
		}

		CompositionPlanLib::create($eCompositionPlan);

		$eCompositionPlanWritten = CompositionPlanLib::getByStructure($this->eStructure);

		$this->assertEquals([14, 15, 16, 17, 18, 19, 20], $eCompositionPlanWritten['weeks'], 'les semaines envoyées');

		$cCompositionFormula = CompositionFormulaLib::getByPlan($eCompositionPlanWritten);

		$this->assertEquals(['Le petit panier' => 20, 'Le grand panier' => 15], array_combine($cCompositionFormula->getColumn('name'), $cCompositionFormula->getColumn('baskets')), 'deux tailles et leur nombre de paniers');

		$excludedWritten = CompositionSpeciesLib::getExcludedByPlan($eCompositionPlanWritten);

		sort($excluded);
		sort($excludedWritten);

		$this->assertEquals($excluded, $excludedWritten, 'trois espèces retirées');

		// Les espèces retirées sont des espèces du référentiel, et une ligne sert une espèce du plan
		// (r76) : c'est l'espèce qu'elle lit qui se compare.
		$cCompositionLine = CompositionLine::model()
			->select(['species' => ['plant']])
			->whereWeek('IN', CompositionWeek::model()->select('id')->whereFormula('IN', $cCompositionFormula)->getCollection())
			->getCollection();

		$served = [];

		foreach($cCompositionLine as $eCompositionLine) {
			$served[] = $eCompositionLine['species']['plant']['id'];
		}

		$this->assertTrue(count($served) > 0, 'des lignes proposées : le contrôle suivant verrait quelque chose');
		$this->assertEquals([], array_values(array_intersect($excluded, $served)), 'aucune ligne sur une espèce retirée');

	}

	/**
	 * Ce que la page refuse avant d'écrire : aucune semaine, aucun panier, un prix vide. Chaque refus
	 * porte le nom du champ de sa rangée (`buildIndex()`), qui le rattache à son étape.
	 */
	public function testStartRefused(): void {

		$eCompositionPlan = new CompositionPlan(['structure' => $this->eStructure, 'season' => 2099]);

		$fw = new \FailWatch();

		$eCompositionPlan->build(['weeks'], ['weeks' => []]);

		$eCompositionFormula = new CompositionFormula(['plan' => $eCompositionPlan]);
		$eCompositionFormula->buildIndex(['name', 'price', 'baskets'], ['name' => [3 => 'Le panier'], 'price' => [3 => ''], 'baskets' => [3 => '0']], 3, new \PropertyList('create'));

		$this->assertEquals([
			'CompositionPlan::weeks.check' => 'weeks',
			'CompositionFormula::price.check' => 'price[3]',
			'CompositionFormula::baskets.check' => 'baskets[3]',
		], array_combine(array_column($fw->get(), 1), array_column($fw->get(), 3)), 'trois refus, chacun sur son champ');

		foreach(array_column($fw->get(), 1) as $fqn) {
			$this->assertTrue(AlertUi::getError($fqn) !== NULL, $fqn.' a son message');
		}

	}

	/**
	 * Recommencer puis composer : la structure garde un seul plan, et les lignes de l'ancien sont
	 * supprimées. Ouvrir le départ sans l'envoyer ne touche à rien.
	 */
	public function testRestart(): void {

		CompositionPlanLib::create($this->getStart());

		$eCompositionPlanOld = CompositionPlanLib::getByStructure($this->eStructure);
		$lines = $this->getLines($eCompositionPlanOld);

		$this->assertTrue($lines !== [], 'l\'ancien plan porte des lignes');

		$eCompositionPlanStart = CompositionPlanLib::getForStart($this->eStructure, 2099);

		$this->assertTrue($eCompositionPlanStart['restart'], 'le départ sait qu\'il recommence');
		$this->assertEquals([14, 15, 16, 17, 18, 19, 20], $eCompositionPlanStart['weeks'], 'avec les semaines du plan');
		$this->assertEquals(['Le panier'], $eCompositionPlanStart['cCompositionFormula']->getColumn('name'), 'et ses tailles');
		$this->assertEquals($eCompositionPlanOld['id'], CompositionPlanLib::getByStructure($this->eStructure)['id'], 'un départ ouvert et abandonné laisse le plan');
		$this->assertEquals($lines, $this->getLines($eCompositionPlanOld), 'et ses lignes');

		CompositionPlanLib::restart($this->getStart([['name' => 'Le nouveau panier', 'price' => 22.0, 'baskets' => 8]]));

		$this->assertEquals(1, CompositionPlan::model()->whereStructure($this->eStructure)->whereSeason(2099)->count(), 'un seul plan pour la saison');
		$this->assertEquals(0, CompositionLine::model()->whereId('IN', $lines)->count(), 'les lignes de l\'ancien plan sont supprimées');
		$this->assertEquals(['Le nouveau panier'], CompositionFormulaLib::getByPlan(CompositionPlanLib::getByStructure($this->eStructure))->getColumn('name'), 'le plan envoyé');

	}

	/**
	 * Le départ tel que la page le construit à l'envoi : les tailles données, les semaines 14 à 20,
	 * les espèces proposables cochées comme le départ les propose.
	 */
	private function getStart(array $formulas = [['name' => 'Le panier', 'price' => 18.0, 'baskets' => 10]]): CompositionPlan {

		$eCompositionPlan = CompositionPlanLib::getForStart($this->eStructure, 2099);
		$eCompositionPlan['weeks'] = [14, 15, 16, 17, 18, 19, 20];
		$eCompositionPlan['reference'] = CompositionPlan::COMMUNITY;
		$eCompositionPlan['climate'] = NULL;

		$cCompositionFormula = new \Collection();

		foreach($formulas as $formula) {
			$cCompositionFormula[] = new CompositionFormula($formula + ['plan' => $eCompositionPlan, 'gap' => 5, 'version' => 0]);
		}

		$eCompositionPlan['cCompositionFormula'] = $cCompositionFormula;

		return $eCompositionPlan;

	}

	private function getLines(CompositionPlan $eCompositionPlan): array {

		return CompositionLine::model()
			->whereWeek('IN', CompositionWeek::model()->select('id')->whereFormula('IN', CompositionFormulaLib::getByPlan($eCompositionPlan))->getCollection())
			->getColumn('id');

	}

	/**
	 * Les espèces que la communauté vend, chacune sur la saison que le départ lui écrirait
	 * (`CompositionSpeciesLib::getSeasonsDefault()`), sauf `$plantIdsExcept` : la saison est la porte
	 * des candidates (lot r27), et un plan sans espèce n'en aurait aucune.
	 */
	private function createSeasons(CompositionPlan $eCompositionPlan, array $source, array $plantIdsExcept = []): void {

		$plantIds = array_diff(array_keys(CompositionLib::getNaturalWindows(CompositionLib::getSeasonWeeks(), $eCompositionPlan['season'], $source)), $plantIdsExcept);

		// `getSeasonsDefault()` prend les espèces sous la forme `[id servi => id lu]` (r76) : la source
		// de la communauté sert chaque espèce sous son propre id.
		foreach(CompositionSpeciesLib::getSeasonsDefault($eCompositionPlan, array_combine($plantIds, $plantIds), $source) as $plantId => $season) {

			CompositionSpeciesLib::create(new CompositionSpecies([
				'plan' => $eCompositionPlan,
				'plant' => new \plant\Plant(['id' => $plantId]),
				'status' => $season['status'],
				'windowWeeks' => $season['windowWeeks'],
			]));

		}

	}

	/**
	 * Un plan de trois semaines composé par la règle, comme à son ouverture.
	 */
	private function createComposed(): array {

		$eCompositionPlan = $this->createPlan();
		$eCompositionFormula = $this->createFormula($eCompositionPlan);

		$this->createSeasons($eCompositionPlan, CompositionLib::getSource());

		// Comme `CompositionFormulaLib::create()` : la règle compose sur la source du plan, qui sert les
		// espèces que le plan vient d'écrire sous leur id (r76).
		$source = CompositionLib::getSourceByPlan($eCompositionPlan);

		CompositionLib::compose($eCompositionPlan, $eCompositionFormula, $source, $source['pricesPlan']);

		return [$eCompositionPlan, $eCompositionFormula];

	}

	/**
	 * L'espèce du plan de plus petit id que la semaine admet et ne sert pas encore : une candidate de
	 * la semaine, sur les saisons du plan, comme la case teintée que la grille allume.
	 */
	private function getUnserved(CompositionPlan $eCompositionPlan, CompositionWeek $eCompositionWeek): CompositionSpecies {

		$source = CompositionLib::getSourceByPlan($eCompositionPlan);
		$served = CompositionLineLib::getByWeek($eCompositionWeek)->getColumnCollection('species')->getIds();

		$candidates = CompositionLib::getCandidates($eCompositionWeek['week'], $eCompositionPlan['season'], CompositionSpeciesLib::getWindowsByPlan($eCompositionPlan), $source);
		sort($candidates);

		foreach($candidates as $speciesId) {

			if(in_array($speciesId, $served) === FALSE) {
				return new CompositionSpecies(['id' => $speciesId]);
			}

		}

		return new CompositionSpecies();

	}

	/**
	 * Les espèces du plan que sert la semaine `$week` de la formule, par id croissant.
	 */
	private function getSpeciesServed(CompositionFormula $eCompositionFormula, int $week): array {

		$speciesIds = CompositionLineLib::getByWeek(CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, $week))->getColumnCollection('species')->getIds();
		sort($speciesIds);

		return $speciesIds;

	}

	private function getAmended(CompositionFormula $eCompositionFormula): array {
		return array_values(CompositionWeekLib::getAmendedByFormula($eCompositionFormula)->getColumn('week'));
	}

	private function createPlan(): CompositionPlan {

		// 2099 ne croise aucune donnée réelle de la structure, et sa configuration ne l'ouvre donc pas :
		// la saison se pose hors de `build()`, qui la refuserait (`testBuildPlan()`).
		$eCompositionPlan = new CompositionPlan([
			'season' => 2099,
			'reference' => CompositionPlan::COMMUNITY,
		]);

		$fw = new \FailWatch();

		$eCompositionPlan->build(['structure', 'weeks'], [
			'structure' => $this->eStructure['id'],
			'weeks' => [14, 15, 16],
		], new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('plan refusé : '.json_encode($fw->get())));

		// Un plan sans taille ni espèce : chaque test pose ensuite ce qu'il éprouve.
		$eCompositionPlan['cCompositionFormula'] = new \Collection();
		$eCompositionPlan['cCompositionSpecies'] = new \Collection();

		CompositionPlanLib::create($eCompositionPlan);

		return $eCompositionPlan;

	}

	/**
	 * Une seconde taille du même plan prend un autre nom : un plan ne porte pas deux tailles du même
	 * nom (`CompositionFormula::name.duplicate`, lot r40).
	 */
	private function createFormula(CompositionPlan $eCompositionPlan, string $name = 'Panier de test'): CompositionFormula {

		$eCompositionFormula = new CompositionFormula(['plan' => $eCompositionPlan]);

		$fw = new \FailWatch();

		$eCompositionFormula->build(CompositionFormulaLib::getPropertiesCreate(), [
			'name' => $name,
			'price' => 18.0,
			'baskets' => 10,
			'gap' => 5,
			'linesMin' => 4,
			'linesMax' => 8,
			'position' => 0,
		], new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('formule refusée : '.json_encode($fw->get())));

		CompositionFormulaLib::create($eCompositionFormula);

		return $eCompositionFormula;

	}

	private function createWeek(CompositionFormula $eCompositionFormula): CompositionWeek {

		$eCompositionWeek = new CompositionWeek();

		$fw = new \FailWatch();

		$eCompositionWeek->build(CompositionWeekLib::getPropertiesCreate(), [
			'formula' => $eCompositionFormula['id'],
			'week' => 14,
		], new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('semaine refusée : '.json_encode($fw->get())));

		CompositionWeekLib::create($eCompositionWeek);

		return $eCompositionWeek;

	}

	private function createLine(CompositionWeek $eCompositionWeek, CompositionSpecies $eCompositionSpecies): CompositionLine {

		$eCompositionLine = new CompositionLine();

		$fw = new \FailWatch();

		$eCompositionLine->build(CompositionLineLib::getPropertiesCreate(), [
			'week' => $eCompositionWeek['id'],
			'species' => $eCompositionSpecies['id'],
			'unit' => $this->eUnitReference['id'],
			'quantity' => 1.6,
			'origin' => CompositionLine::PROPOSED,
		], new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('ligne refusée : '.json_encode($fw->get())));

		CompositionLineLib::create($eCompositionLine);

		return $eCompositionLine;

	}

	private function createSpecies(CompositionPlan $eCompositionPlan): CompositionSpecies {
		return $this->createSpeciesOf($eCompositionPlan, $this->ePlant);
	}

	/**
	 * `$weight` est le poids d'une pièce ou d'une botte : sans lui, une espèce qui entre au panier dans
	 * l'une de ces unités en chercherait un dans les cultures de la structure (`weight.missing`).
	 */
	private function createSpeciesOf(CompositionPlan $eCompositionPlan, \plant\Plant $ePlant, ?float $weight = NULL): CompositionSpecies {

		$eCompositionSpecies = new CompositionSpecies(['name' => NULL, 'plan' => $eCompositionPlan]);

		$fw = new \FailWatch();

		$eCompositionSpecies->build(CompositionSpeciesLib::getPropertiesCreate(), [
			'plant' => $ePlant['id'],
			'weight' => ($weight === NULL) ? '' : (string)$weight,
			'status' => CompositionSpecies::EXCLUDED,
			'windowWeeks' => NULL,
		], new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('espèce refusée : '.json_encode($fw->get())));

		CompositionSpeciesLib::create($eCompositionSpecies);

		return $eCompositionSpecies;

	}

}
?>
