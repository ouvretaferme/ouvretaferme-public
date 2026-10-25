<?php
namespace toolbox;

/**
 * Les doublons que la composition refuse (lot r40) : une semaine ne sert pas deux fois le même
 * légume, un plan ne porte pas deux tailles du même nom.
 *
 * Chaque refus a deux étages, et ce fichier les éprouve séparément :
 *
 * 1. le contrôle avant l'écriture, une étape nommée de `properties()` (`CompositionLine::species.duplicate`,
 *    `CompositionFormula::name.duplicate`), que tout envoi ordinaire rencontre ;
 * 2. le filet, l'unicité du schéma (`(week, species)`, `(plan, name)`), que seuls deux envois simultanés
 *    atteignent : tous deux passent le contrôle, et la base refuse le second. Le Lib rend alors le même
 *    refus, n'écrit rien et referme la transaction qu'il a ouverte (`testLineSafetyNet()`,
 *    `testFormulaAddSafetyNet()`, `testFormulaUpdateSafetyNet()`). Deux envois simultanés se montent ici
 *    en construisant les deux éléments avant d'écrire le premier.
 *
 * Les filets qui annulent toute la pile (`CompositionFormulaLib::updateByPlan()`,
 * `CompositionPlanLib::create()`) sont dans `CompositionDuplicateRollBackTest`, qui ferme la
 * transaction du test.
 *
 * Chaque refus a son pendant admis : le légume que la semaine ne sert pas, le nom libre, la taille qui
 * garde son nom, le plan pas encore écrit.
 *
 * Isolation : la transaction de `TransactionTest`, annulée après chaque méthode. Le décor est celui de
 * `CompositionDuplicateFixtureTest`.
 *
 * Lancement : dev/test package=toolbox file=CompositionDuplicate
 */
class CompositionDuplicateTest extends CompositionDuplicateFixtureTest {

	/**
	 * Le contrôle avant l'écriture : un légume que la semaine sert déjà est refusé sous son champ,
	 * qu'il y soit par la règle ou par un ajout à la main, et celui qu'elle ne sert pas est admis.
	 */
	public function testLineCheck(): void {

		[, $eCompositionWeek, $eCompositionSpeciesFree] = $this->createWeekWithFreeSpecies();

		$eCompositionSpeciesProposed = CompositionLineLib::getByWeek($eCompositionWeek)->first()['species'];

		$this->assertEquals(['CompositionLine::species.duplicate species'], $this->buildLine($eCompositionWeek, $eCompositionSpeciesProposed)[1], 'un légume que la règle sert déjà cette semaine');

		[$eCompositionLine, $fails] = $this->buildLine($eCompositionWeek, $eCompositionSpeciesFree);

		$this->assertEquals([], $fails, 'le pendant : un légume que la semaine ne sert pas');

		CompositionLineLib::create($eCompositionLine);

		$this->assertEquals(['CompositionLine::species.duplicate species'], $this->buildLine($eCompositionWeek, $eCompositionSpeciesFree)[1], 'le même légume une seconde fois');
		$this->assertEquals(1, $this->countLines($eCompositionWeek, $eCompositionSpeciesFree), 'une seule ligne');
		$this->assertEquals('Ce légume est déjà dans le panier de cette semaine.', AlertUi::getError('CompositionLine::species.duplicate'), 'le message du refus');

	}

	/**
	 * Le filet : deux envois de la même case construits avant qu'aucun ne s'écrive passent tous deux
	 * le contrôle. Le second ne laisse pas de seconde ligne, rend le refus du contrôle, et la pile de
	 * transactions revient où elle était.
	 */
	public function testLineSafetyNet(): void {

		[, $eCompositionWeek, $eCompositionSpeciesFree] = $this->createWeekWithFreeSpecies();

		[$eCompositionLineFirst, $failsFirst] = $this->buildLine($eCompositionWeek, $eCompositionSpeciesFree);
		[$eCompositionLineSecond, $failsSecond] = $this->buildLine($eCompositionWeek, $eCompositionSpeciesFree);

		$this->assertEquals([[], []], [$failsFirst, $failsSecond], 'les deux envois passent le contrôle, rien n\'étant encore écrit');

		CompositionLineLib::create($eCompositionLineFirst);

		$states = \ModuleModel::getTransactionStates();

		$fw = new \FailWatch();

		CompositionLineLib::create($eCompositionLineSecond);

		$this->assertEquals(['CompositionLine::species.duplicate'], array_column($fw->get(), 1), 'le second envoi rend le refus du contrôle');
		$this->assertEquals(1, $this->countLines($eCompositionWeek, $eCompositionSpeciesFree), 'une seule ligne');
		$this->assertEquals($states, \ModuleModel::getTransactionStates(), 'la transaction ouverte par le Lib est refermée, celle de l\'appelant intacte');

	}

	/**
	 * Un plan porte une espèce sous un nom une seule fois (r76) : l'espèce nue une fois, chaque
	 * variante une fois. Le schéma ne le tient que pour les variantes, `UNIQUE (plan, plant, name)`
	 * laissant passer deux noms NULL : c'est l'étape `CompositionSpecies::plant.duplicate` qui refuse
	 * une seconde espèce nue, et elle seule. Le nom se compare comme la colonne le classe, sans la
	 * casse. L'espèce elle-même, reconstruite, ne se compare pas à elle-même.
	 */
	public function testSpeciesCheck(): void {

		$eCompositionPlan = $this->createStarted();

		$eCompositionSpeciesBare = CompositionSpeciesLib::getByPlan($eCompositionPlan)->find(fn(CompositionSpecies $e) => $e['name'] === NULL)->first();
		$eCompositionSpeciesBare['plan'] = $eCompositionPlan;

		$ePlant = $eCompositionSpeciesBare['plant'];

		$build = function(CompositionSpecies $eCompositionSpecies) use($ePlant): array {

			$fw = new \FailWatch();

			$eCompositionSpecies->build(['plant'], ['plant' => $ePlant['id']], new \PropertyList('create'));

			return array_map(fn(array $fail) => $fail[1].' '.$fail[3], $fw->get());

		};

		$this->assertEquals(['CompositionSpecies::plant.duplicate plant'], $build(new CompositionSpecies(['plan' => $eCompositionPlan, 'name' => NULL])), 'une seconde espèce nue, que l\'index unique laisserait passer');
		$this->assertEquals([], $build(new CompositionSpecies(['plan' => $eCompositionPlan, 'name' => 'Variante du test'])), 'le pendant : une variante de la même espèce');
		$this->assertEquals([], $build($eCompositionSpeciesBare), 'l\'espèce nue reconstruite ne se compare pas à elle-même');

		$eCompositionSpeciesBare['name'] = 'Variante du test';
		CompositionSpeciesLib::update($eCompositionSpeciesBare, ['name']);

		$this->assertEquals(['CompositionSpecies::plant.duplicate plant'], $build(new CompositionSpecies(['plan' => $eCompositionPlan, 'name' => 'Variante du test'])), 'une seconde variante du même nom');
		$this->assertEquals(['CompositionSpecies::plant.duplicate plant'], $build(new CompositionSpecies(['plan' => $eCompositionPlan, 'name' => 'variante du test'])), 'la casse ne distingue pas deux noms, comme dans la base');
		$this->assertEquals([], $build(new CompositionSpecies(['plan' => $eCompositionPlan, 'name' => NULL])), 'l\'espèce nue, que le plan ne porte plus');

		$this->assertEquals('Cette espèce est déjà dans vos paniers sous ce nom.', AlertUi::getError('CompositionSpecies::plant.duplicate'), 'le message du refus');

	}

	/**
	 * Le contrôle avant l'écriture, à la création et au renommage : un nom déjà porté par une autre
	 * taille du plan est refusé, dans le classement de la colonne (« le panier » après « Le panier »).
	 * Une taille qui garde son nom, un nom libre et les tailles d'un plan pas encore écrit sont admis.
	 */
	public function testFormulaCheck(): void {

		$eCompositionPlan = $this->createStarted();

		$eCompositionFormulaSmall = CompositionFormulaLib::getByPlan($eCompositionPlan)->first();
		$eCompositionFormulaLarge = $this->getFormula($eCompositionPlan, 'Le grand panier', 30.0);

		CompositionFormulaLib::add($eCompositionFormulaLarge);

		// La création, comme `compositionFormula:doCreate`.
		$create = function(string $name) use($eCompositionPlan): array {

			$fw = new \FailWatch();

			new CompositionFormula(['id' => NULL, 'plan' => $eCompositionPlan, 'base' => new CompositionFormula()])
				->build(['name', 'price', 'baskets', 'base'], ['name' => $name, 'price' => '25', 'baskets' => '5', 'base' => ''], new \PropertyList('create'));

			return array_map(fn(array $fail) => $fail[1].' '.$fail[3], $fw->get());

		};

		$this->assertEquals(['CompositionFormula::name.duplicate name'], $create('Le panier'), 'une taille neuve au nom d\'une autre');
		$this->assertEquals(['CompositionFormula::name.duplicate name'], $create('le panier'), 'la casse ne distingue pas deux noms, comme dans la base');
		$this->assertEquals([], $create('Le panier moyen'), 'le pendant : un nom libre');

		// Le renommage, comme `composition:doSettings`, qui poste toutes les tailles.
		$rename = function(CompositionFormula $e, string $name) use($eCompositionPlan): array {

			$eCompositionFormula = CompositionFormulaLib::getByPlanAndId($eCompositionPlan, $e['id']);

			$fw = new \FailWatch();

			$eCompositionFormula->buildIndex(['name'], ['name' => [$e['id'] => $name]], $e['id']);

			return array_map(fn(array $fail) => $fail[1].' '.$fail[3], $fw->get());

		};

		$this->assertEquals(['CompositionFormula::name.duplicate name['.$eCompositionFormulaLarge['id'].']'], $rename($eCompositionFormulaLarge, 'Le panier'), 'un renommage vers le nom d\'une autre, sous son champ');
		$this->assertEquals([], $rename($eCompositionFormulaLarge, 'Le grand panier'), 'une taille qui garde son nom');
		$this->assertEquals([], $rename($eCompositionFormulaSmall, 'Le panier'), 'l\'autre aussi');
		$this->assertEquals([], $rename($eCompositionFormulaLarge, 'Le très grand panier'), 'un renommage vers un nom libre');

		// Le départ : le plan n'est pas écrit, il n'y a rien en base à quoi comparer.
		$fw = new \FailWatch();

		new CompositionFormula(['plan' => new CompositionPlan(['structure' => $eCompositionPlan['structure'], 'season' => 2026]), 'base' => new CompositionFormula()])
			->buildIndex(['name', 'price', 'baskets'], ['name' => [0 => 'Le panier'], 'price' => [0 => '18'], 'baskets' => [0 => '10']], 0, new \PropertyList('create'));

		$this->assertEquals([], array_column($fw->get(), 1), 'une taille d\'un plan pas encore écrit ne se compare à aucune');

		$this->assertEquals('Un autre panier porte déjà ce nom.', AlertUi::getError('CompositionFormula::name.duplicate'), 'le message du refus');

	}

	/**
	 * Le filet à l'ajout : deux envois du panneau « Ajouter une taille » construits avant qu'aucun ne
	 * s'écrive. Le second ne laisse ni taille ni semaine, rend le refus du contrôle, et la pile de
	 * transactions revient où elle était.
	 */
	public function testFormulaAddSafetyNet(): void {

		$eCompositionPlan = $this->createStarted();

		$eCompositionFormulaFirst = $this->getFormula($eCompositionPlan, 'Le grand panier', 30.0);
		$eCompositionFormulaSecond = $this->getFormula($eCompositionPlan, 'Le grand panier', 32.0);

		CompositionFormulaLib::add($eCompositionFormulaFirst);

		$weeks = CompositionWeek::model()->count();
		$states = \ModuleModel::getTransactionStates();

		$fw = new \FailWatch();

		CompositionFormulaLib::add($eCompositionFormulaSecond);

		$this->assertEquals(['CompositionFormula::name.duplicate'], array_column($fw->get(), 1), 'le second envoi rend le refus du contrôle');
		$this->assertEquals([30.0], array_map(floatval(...), CompositionFormula::model()->wherePlan($eCompositionPlan)->whereName('Le grand panier')->getColumn('price')), 'une seule taille de ce nom, la première');
		$this->assertEquals($weeks, CompositionWeek::model()->count(), 'aucune semaine composée pour la taille refusée');
		$this->assertEquals($states, \ModuleModel::getTransactionStates(), 'la transaction ouverte par le Lib est refermée, celle de l\'appelant intacte');

	}

	/**
	 * Le filet au renommage d'une taille seule (`CompositionFormulaLib::update()`) : un nom pris entre
	 * le contrôle et l'écriture ne s'écrit pas, et la pile de transactions revient où elle était.
	 */
	public function testFormulaUpdateSafetyNet(): void {

		$eCompositionPlan = $this->createStarted();

		$eCompositionFormulaLarge = $this->getFormula($eCompositionPlan, 'Le grand panier', 30.0);

		CompositionFormulaLib::add($eCompositionFormulaLarge);

		$eCompositionFormula = CompositionFormulaLib::getByPlanAndId($eCompositionPlan, $eCompositionFormulaLarge['id']);

		// Le nom se pose sans `build()` : son étape `duplicate` le refuserait, et c'est l'écriture d'un
		// nom que le contrôle a laissé passer qu'on éprouve.
		$eCompositionFormula['name'] = 'Le panier';

		$states = \ModuleModel::getTransactionStates();

		$fw = new \FailWatch();

		CompositionFormulaLib::update($eCompositionFormula, ['name']);

		$this->assertEquals(['CompositionFormula::name.duplicate'], array_column($fw->get(), 1), 'l\'écriture rend le refus du contrôle');
		$this->assertEquals('Le grand panier', CompositionFormulaLib::getByPlanAndId($eCompositionPlan, $eCompositionFormulaLarge['id'])['name'], 'la taille garde son nom');
		$this->assertEquals($states, \ModuleModel::getTransactionStates(), 'la transaction ouverte par le Lib est refermée, celle de l\'appelant intacte');

	}

	/**
	 * Ce que la règle propose ne bute jamais sur l'unicité `(week, species)` : aucune semaine d'aucune
	 * taille, base ou dérivée, ne sert deux fois un légume, à la composition comme après un rejeu.
	 */
	public function testProposalNeverServesTwice(): void {

		$eCompositionPlan = $this->createStarted();

		$eCompositionFormulaSmall = CompositionFormulaLib::getByPlanAndId($eCompositionPlan, CompositionFormulaLib::getByPlan($eCompositionPlan)->first()['id']);

		$eCompositionFormulaDerived = $this->getFormula($eCompositionPlan, 'Le grand panier', 30.0);
		$eCompositionFormulaDerived['base'] = $eCompositionFormulaSmall;

		CompositionFormulaLib::add($eCompositionFormulaDerived);

		$this->assertTrue($this->countPlanLines($eCompositionPlan) > 0, 'des lignes proposées : le contrôle suivant verrait quelque chose');
		$this->assertEquals([], $this->getLinesTwice($eCompositionPlan), 'aucun légume servi deux fois à la composition');

		CompositionFormulaLib::replay($eCompositionFormulaSmall);

		$this->assertEquals([], $this->getLinesTwice($eCompositionPlan), 'ni après un rejeu de la base, qui refait sa dérivée');

	}

	/**
	 * Les couples (semaine, légume) que le plan porte plus d'une fois, `['<id de semaine>/<id de légume>', …]`.
	 */
	private function getLinesTwice(CompositionPlan $eCompositionPlan): array {

		$counts = [];

		foreach($this->getPlanLines($eCompositionPlan) as $eCompositionLine) {

			$key = $eCompositionLine['week']['id'].'/'.$eCompositionLine['species']['id'];

			$counts[$key] = ($counts[$key] ?? 0) + 1;

		}

		return array_keys(array_filter($counts, fn(int $count) => $count > 1));

	}

	private function countPlanLines(CompositionPlan $eCompositionPlan): int {
		return $this->getPlanLines($eCompositionPlan)->count();
	}

	private function getPlanLines(CompositionPlan $eCompositionPlan): \Collection {

		return CompositionLine::model()
			->select(['id', 'week', 'species'])
			->whereWeek('IN', CompositionWeek::model()
				->select('id')
				->whereFormula('IN', CompositionFormulaLib::getByPlan($eCompositionPlan))
				->getSql())
			->getCollection();

	}

	private function countLines(CompositionWeek $eCompositionWeek, CompositionSpecies $eCompositionSpecies): int {

		return CompositionLine::model()
			->whereWeek($eCompositionWeek)
			->whereSpecies($eCompositionSpecies)
			->count();

	}

	/**
	 * Une ligne ajoutée à la main, construite comme `compositionLine:doCreate` la construit, et les
	 * refus de sa construction, `['<fail> <champ>', …]`.
	 */
	private function buildLine(CompositionWeek $eCompositionWeek, CompositionSpecies $eCompositionSpecies): array {

		$eCompositionLine = new CompositionLine(['week' => $eCompositionWeek, 'origin' => CompositionLine::USER]);

		$fw = new \FailWatch();

		$eCompositionLine->build(['species'], ['species' => $eCompositionSpecies['id']], new \PropertyList('create'));

		return [$eCompositionLine, array_map(fn(array $fail) => $fail[1].' '.$fail[3], $fw->get())];

	}

	/**
	 * Le plan, sa première semaine écrite qui ne sert pas toutes ses candidates, et une espèce du plan
	 * candidate qu'elle ne sert pas, ni sous un autre nom (`CompositionLine::species.twin`).
	 */
	private function createWeekWithFreeSpecies(): array {

		$eCompositionPlan = $this->createStarted();

		$eCompositionFormula = CompositionFormulaLib::getByPlanAndId($eCompositionPlan, CompositionFormulaLib::getByPlan($eCompositionPlan)->first()['id']);

		$source = CompositionLib::getSourceByPlan($eCompositionPlan);

		foreach($eCompositionPlan['weeks'] as $week) {

			$eCompositionWeek = CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, $week);

			if($eCompositionWeek->empty()) {
				continue;
			}

			$served = CompositionLineLib::getByWeek($eCompositionWeek)->getColumnCollection('species')->getIds();

			foreach(array_keys(CompositionLib::getNaturalWeeks([$week], $eCompositionPlan['season'], $source)) as $speciesId) {

				if(
					in_array($speciesId, $served, TRUE) === FALSE and
					CompositionLib::isSold($speciesId, $source) and
					CompositionLib::isTwinRefused($speciesId, $served, $source) === FALSE
				) {
					return [$eCompositionPlan, $eCompositionWeek, new CompositionSpecies(['id' => $speciesId])];
				}

			}

		}

		throw new \Exception('aucune semaine du plan ne laisse un légume candidat hors de son panier : le cas ne peut pas se monter');

	}

}
?>
