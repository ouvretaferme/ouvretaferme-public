<?php
namespace planning;

/**
 * La saisie d'une récolte en caisses (lot L4b) : `doHarvest` reçoit un nombre de caisses (`crate=1`),
 * `\series\TaskLib::writeHarvestForWeek()` le convertit en kilos avant `buildHarvests()`, et le poids
 * d'une caisse s'écrit sur l'espèce (`plant.crateWeight`) après une récolte acceptée seulement.
 *
 * La virgule se lit aussi : le cast par défaut d'un nombre passe la valeur au `(float)` de PHP, qui lit
 * « 6,5 » comme 6 sans échec. La lecture tient dans le cast de `harvestMore` et `harvestDay`
 * (`series/module/Task.e.php`) et dans l'étape `decimal` de `crateWeight` (`plant/module/Plant.e.php`) :
 * `testComma` échoue sans elles, 12 kg écrits au lieu de 16,3.
 *
 * Chaque cas pose sa propre espèce, sans poids de caisse, dans la transaction du décor.
 *
 * Lancement : dev/test package=planning file=HarvestCrate
 */
class HarvestCrateTest extends HarvestFixtureTest {

	/**
	 * Le premier poids se règle avec la première récolte en caisses : 3 caisses de 6 kg écrivent 18 kg,
	 * et l'espèce garde le poids. Un remplacement par 2 caisses ramène le jour à 12 kg.
	 */
	public function testAddThenReplace(): void {

		[$ePlant, $eCultivation] = $this->createCultivationCrate();

		$action = $this->post($eCultivation, ['mode' => 'add', 'crate' => 1, 'harvestMore' => '3', 'crateWeight' => '6']);
		$this->assertTrue($action instanceof \ViewAction, 'la saisie en caisses est refusée : '.implode(', ', $this->getFails($action)));

		$this->assertEquals([\Clock::date() => ['kg' => 18.0]], $this->getHarvested($eCultivation), '3 caisses de 6 kg n\'écrivent pas 18 kg');
		$this->assertEquals(6.0, $this->getCrateWeight($ePlant), 'le poids de la caisse n\'est pas posé sur l\'espèce');

		$action = $this->post($eCultivation, ['mode' => 'replace', 'crate' => 1, 'harvestDay' => '2']);
		$this->assertTrue($action instanceof \ViewAction, 'le remplacement en caisses est refusé : '.implode(', ', $this->getFails($action)));

		$this->assertEquals([\Clock::date() => ['kg' => 12.0]], $this->getHarvested($eCultivation), 'remplacer par 2 caisses de 6 kg ne laisse pas 12 kg');

	}

	/**
	 * « 2,5 » caisses de « 6,5 » kg écrivent 16,3 kg, au dixième de kilo de toute récolte (`buildHarvests()`
	 * répartit au dixième), et le poids posé est 6,5 ; en kilos, « 6,5 » vaut
	 * 6,5 kg. Sans la lecture de la virgule, la première saisie écrirait 12 kg (2 × 6) et la seconde 6 kg.
	 */
	public function testComma(): void {

		[$ePlant, $eCultivation] = $this->createCultivationCrate();

		$action = $this->post($eCultivation, ['mode' => 'add', 'crate' => 1, 'harvestMore' => '2,5', 'crateWeight' => '6,5']);
		$this->assertTrue($action instanceof \ViewAction, 'une saisie avec virgule est refusée : '.implode(', ', $this->getFails($action)));

		$this->assertEquals([\Clock::date() => ['kg' => 16.3]], $this->getHarvested($eCultivation), 'la virgule n\'est pas lue : « 2,5 » caisses de « 6,5 » kg doivent écrire 16,3 kg');
		$this->assertEquals(6.5, $this->getCrateWeight($ePlant), 'la virgule du poids n\'est pas lue');

		$action = $this->post($eCultivation, ['mode' => 'replace', 'harvestDay' => '6,5']);
		$this->assertTrue($action instanceof \ViewAction, 'un remplacement en kilos avec virgule est refusé : '.implode(', ', $this->getFails($action)));

		$this->assertEquals([\Clock::date() => ['kg' => 6.5]], $this->getHarvested($eCultivation), 'la virgule n\'est pas lue en kilos : « 6,5 » doit écrire 6,5 kg');

	}

	/**
	 * Ce qui ne se lit pas comme un nombre est refusé, au lieu d'être lu à moitié : « 1 000 » valait 1,
	 * « 1e3 » 1000, et « 1e400 » INF, qui partait jusqu'à la base en erreur SQL.
	 */
	public function testNotNumber(): void {

		[$ePlant, $eCultivation] = $this->createCultivationCrate();

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '1 000']);
		$this->assertEquals(['Task::harvestMore.cast'], $this->getFails($action), 'une quantité illisible n\'est pas refusée par son cast');

		$action = $this->post($eCultivation, ['mode' => 'replace', 'harvestDay' => '1 000']);
		$this->assertEquals(['Task::harvestDay.cast'], $this->getFails($action), 'un remplacement illisible n\'est pas refusé par son cast');

		foreach(['1e3', '1e400', ' 6', '6,5,1', str_repeat('9', 400)] as $harvest) {
			$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => $harvest]);
			$this->assertEquals(['Task::harvestMore.cast'], $this->getFails($action), json_encode($harvest).' n\'est pas refusé par le cast');
		}

		foreach(['6 kg', '1e1'] as $weight) {
			$action = $this->post($eCultivation, ['mode' => 'add', 'crate' => 1, 'harvestMore' => '3', 'crateWeight' => $weight]);
			$this->assertEquals(['Plant::crateWeight.decimal'], $this->getFails($action), json_encode($weight).' n\'est pas refusé par l\'étape decimal');
		}

		$this->assertEquals([], $this->getHarvested($eCultivation), 'un refus a écrit une récolte');
		$this->assertEquals(NULL, $this->getCrateWeight($ePlant), 'un refus a écrit un poids');

	}

	/**
	 * Une caisse se convertit en kilos : la saisie en caisses qui nomme une autre unité (`harvestUnit`, retour
	 * 319) est refusée par `writeHarvestForWeek()`, que la semaine porte déjà une intervention dans cette unité
	 * ou qu'elle soit à créer, et le poids posté avec elle ne s'écrit pas.
	 */
	public function testCrateOutsideKg(): void {

		[$ePlant, $eCultivation] = $this->createCultivationCrate();

		$this->createTaskHarvestInUnit($eCultivation, \series\Task::UNIT, \Clock::date());

		foreach([
			'intervention en pièces de la semaine' => \series\Task::UNIT,
			'intervention en bottes à créer' => \series\Task::BUNCH,
		] as $case => $harvestUnit) {

			$action = $this->post($eCultivation, ['mode' => 'add', 'crate' => 1, 'harvestMore' => '3', 'crateWeight' => '6', 'harvestUnit' => $harvestUnit]);

			$this->assertEquals(['Task::harvestUnit.crate'], $this->getFails($action), $case.' : la caisse n\'est pas refusée');

		}

		$this->assertEquals([], $this->getHarvested($eCultivation), 'la saisie refusée a écrit une récolte');
		$this->assertEquals(NULL, $this->getCrateWeight($ePlant), 'la saisie refusée a écrit le poids de la caisse');

	}

	/**
	 * Sans poids, ni posté ni réglé, la saisie en caisses est refusée par `PlantLib::prepareCrateWeight()`.
	 */
	public function testMissingWeight(): void {

		[, $eCultivation] = $this->createCultivationCrate();

		$action = $this->post($eCultivation, ['mode' => 'add', 'crate' => 1, 'harvestMore' => '3', 'crateWeight' => '']);

		$this->assertEquals(['Plant::crateWeight.missing'], $this->getFails($action), 'une saisie en caisses sans poids n\'est pas refusée');
		$this->assertEquals([], $this->getHarvested($eCultivation), 'la saisie refusée a écrit une récolte');

	}

	/**
	 * Des caisses négatives retirent du jour saisi comme des kilos (retour 213) : converties au poids de la
	 * caisse posté, elles passent le même plancher, la récolte du jour. Sur 3 caisses de 6 kg, -1 caisse de
	 * 7 kg laisse 11 kg, et -5 caisses de 6 kg (30 kg) se refusent par `harvestMore.day` sans rien écrire. Le
	 * poids ne s'écrit sur l'espèce que sur une quantité positive : l'espèce garde 6 kg.
	 */
	public function testNegativeCrates(): void {

		[$ePlant, $eCultivation] = $this->createCultivationCrate();

		$action = $this->post($eCultivation, ['mode' => 'add', 'crate' => 1, 'harvestMore' => '3', 'crateWeight' => '6']);

		if($this->assertDecorPresent($action instanceof \ViewAction, '3 caisses de 6 kg récoltées aujourd\'hui') === FALSE) {
			return;
		}

		$action = $this->post($eCultivation, ['mode' => 'add', 'crate' => 1, 'harvestMore' => '-1', 'crateWeight' => '7']);
		$this->assertTrue($action instanceof \ViewAction, '-1 caisse sur un jour de 3 est refusée : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([\Clock::date() => ['kg' => 11.0]], $this->getHarvested($eCultivation), '-1 caisse de 7 kg sur 18 kg ne laisse pas 11 kg');
		$this->assertEquals(6.0, $this->getCrateWeight($ePlant), 'une quantité négative a écrit le poids de la caisse');

		$action = $this->post($eCultivation, ['mode' => 'add', 'crate' => 1, 'harvestMore' => '-5']);
		$this->assertEquals(['Task::harvestMore.day'], $this->getFails($action), '-5 caisses sur un jour de 11 kg ne sont pas refusées par le plancher du jour');
		$this->assertEquals([\Clock::date() => ['kg' => 11.0]], $this->getHarvested($eCultivation), 'la saisie refusée a changé la récolte');

	}

	/**
	 * Des caisses à 0 avec un temps seul ne se servent pas du poids : il ne s'écrit pas.
	 */
	public function testZeroCrates(): void {

		[$ePlant, $eCultivation] = $this->createCultivationCrate();

		$action = $this->post($eCultivation, ['mode' => 'add', 'crate' => 1, 'harvestMore' => '0', 'crateWeight' => '6', 'time' => $this->getTimeInput('0.5')]);
		$this->assertTrue($action instanceof \ViewAction, 'un temps seul en caisses est refusé : '.implode(', ', $this->getFails($action)));

		$this->assertEquals(NULL, $this->getCrateWeight($ePlant), 'des caisses à 0 ont écrit le poids');

	}

	/**
	 * Un poids posté identique au poids réglé ne réécrit pas l'espèce : deux saisies identiques, l'une
	 * avec le poids, l'autre sans, font autant d'`UPDATE` (précédent du comptage :
	 * `accounting/cash/test/WaitingReconciliableAi.t.php`). Un poids changé, lui, s'écrit.
	 */
	public function testWeightWrittenOnlyWhenChanged(): void {

		[$ePlant, $eCultivation] = $this->createCultivationCrate();

		$this->post($eCultivation, ['mode' => 'add', 'crate' => 1, 'harvestMore' => '1', 'crateWeight' => '6']);

		$before = $this->countUpdates();
		$this->post($eCultivation, ['mode' => 'add', 'crate' => 1, 'harvestMore' => '1', 'crateWeight' => '6']);
		$withWeight = $this->countUpdates() - $before;

		$before = $this->countUpdates();
		$this->post($eCultivation, ['mode' => 'add', 'crate' => 1, 'harvestMore' => '1']);
		$withoutWeight = $this->countUpdates() - $before;

		$this->assertEquals($withoutWeight, $withWeight, 'un poids inchangé réécrit l\'espèce');

		$action = $this->post($eCultivation, ['mode' => 'add', 'crate' => 1, 'harvestMore' => '1', 'crateWeight' => '7']);
		$this->assertTrue($action instanceof \ViewAction, 'la saisie avec un nouveau poids est refusée : '.implode(', ', $this->getFails($action)));

		$this->assertEquals(7.0, $this->getCrateWeight($ePlant), 'un poids changé ne s\'écrit pas');
		$this->assertEquals([\Clock::date() => ['kg' => 25.0]], $this->getHarvested($eCultivation), 'trois caisses de 6 kg puis une de 7 kg n\'écrivent pas 25 kg');

	}

	/**
	 * Une espèce neuve, sans poids de caisse, et sa culture en kilos.
	 */
	private function createCultivationCrate(): array {

		$ePlant = $this->createPlant();
		$eCultivation = $this->createCultivation(ePlant: $ePlant);

		return [$ePlant, $eCultivation];

	}

	private function getCrateWeight(\plant\Plant $ePlant): ?float {

		return \plant\PlantLib::getById($ePlant['id'])['crateWeight'];

	}

	private function countUpdates(): int {

		return (int)\plant\Plant::model()->pdo()->query('SHOW SESSION STATUS LIKE "Com_update"')->fetch()['Value'];

	}

	/**
	 * `doHarvest` sur la culture, aujourd'hui.
	 */
	private function post(\series\Cultivation $eCultivation, array $input): ?\Action {

		[, , $action] = $this->call('doHarvest', $input + [
			'cultivation' => $eCultivation['id'],
			'date' => \Clock::date(),
		], 'POST');

		return $action;

	}

	/** Le récolté de la culture, par jour puis par unité, sans les jours revenus à zéro. */
	private function getHarvested(\series\Cultivation $eCultivation): array {

		$cHarvest = \series\Harvest::model()
			->select(['date', 'unit', 'quantity'])
			->whereCultivation($eCultivation)
			->getCollection();

		$harvested = [];

		foreach($cHarvest as $eHarvest) {
			$harvested[$eHarvest['date']][$eHarvest['unit']] = round(($harvested[$eHarvest['date']][$eHarvest['unit']] ?? 0.0) + $eHarvest['quantity'], 3);
		}

		return array_map(fn($units) => array_filter($units, fn($quantity) => $quantity !== 0.0), array_filter($harvested));

	}

	/**
	 * Une intervention de récolte faite aujourd'hui en unités, sans quantité.
	 */
	private function createTaskHarvestInUnit(\series\Cultivation $eCultivation, string $unit, string $date): \series\Task {

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
		// formulaire d'intervention : le décor veut une intervention en unités sans quantité sur une culture
		// en kilos, que la saisie de l'écran refuse de créer (`Task::harvestUnit.quantity`).
		\series\TaskLib::create($eTask);

		return $eTask;

	}

}
?>
