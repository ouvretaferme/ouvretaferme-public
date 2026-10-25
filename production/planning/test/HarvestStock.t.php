<?php
namespace planning;

/**
 * « Ajouter à un stock » sur toute saisie de récolte du nouveau planning (DG-4, `HarvestStockLib`) : le tiroir et
 * « Nouvelle récolte » des Récoltes (`harvestWeek:doHarvest`), « Supprimer » (`doHarvestDelete`), la fiche
 * d'une intervention (`taskDetail:doHarvest`) et « Nouvelle intervention » (`taskWeek:doCreate`). Le stock
 * choisi reçoit la quantité écrite, signée, et l'étoile le retient pour les saisies sans champ. Un stock que le
 * champ ne propose pas est refusé avant toute écriture.
 *
 * Lancement : dev/test package=planning file=HarvestStock
 */
class HarvestStockTest extends HarvestFixtureTest {

	// Une saisie refusée ne laisse aucune transaction ouverte (`\dev\TransactionTest`) : `writeEntry()` ouvre la
	// sienne après le dernier refus (`\series\TaskLib::prepareHarvestForWeek()`), et l'écart se lit en échec.
	const STRICT = TRUE;

	/**
	 * Au tiroir, un ajout de 4 kg monte le stock choisi de 4, un retrait de 1 kg le baisse de 1, un remplacement
	 * de 3 kg par 2 le baisse de l'écart, et « Pas de mise en stock » ne le touche pas.
	 */
	public function testDrawer(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$ePlant = $this->createPlant();
		$eCultivation = $this->createCultivation(ePlant: $ePlant);
		$eProduct = $this->createProduct('Stock du tiroir', $this->getUnit(\series\Task::KG), 10.0, ePlant: $ePlant);

		$cases = [
			'ajout' => [['mode' => 'add', 'harvestMore' => '4'], 14.0],
			'ajout négatif' => [['mode' => 'add', 'harvestMore' => '-1'], 13.0],
			'remplacement à la baisse' => [['mode' => 'replace', 'harvestDay' => '2'], 12.0],
			'pas de mise en stock' => [['mode' => 'add', 'harvestMore' => '5', 'stock' => ''], 12.0],
		];

		foreach($cases as $case => [$input, $stock]) {

			$action = $this->postEntry($eCultivation, $input + ['stock' => $eProduct['id']]);

			if($this->assertTrue($action instanceof \ViewAction, $case.' : la saisie est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
				return;
			}

			$this->assertEquals($stock, $this->getStock($eProduct), $case.' : le stock ne suit pas la quantité écrite');

		}

		$this->assertEquals([\Clock::date() => 7.0], $this->getHarvested($eCultivation), 'les saisies n\'ont pas écrit la récolte du jour');

	}

	/**
	 * Un produit d'une autre espèce, de la même espèce dans une autre unité, ou de la même espèce et de la même
	 * unité dans une autre structure, n'est pas de ceux que le champ propose : la saisie est refusée, ni la
	 * récolte ni aucun stock ne bougent.
	 */
	public function testRefused(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$ePlant = $this->createPlant();
		$eCultivation = $this->createCultivation(ePlant: $ePlant);

		$cases = [
			'autre espèce' => $this->createProduct('Stock d\'une autre espèce', $this->getUnit(\series\Task::KG), 10.0, ePlant: $this->createPlant()),
			'autre unité' => $this->createProduct('Stock en bottes', $this->getUnit(\series\Task::BUNCH), 10.0, ePlant: $ePlant),
			'autre structure' => $this->createProductOther($ePlant),
		];

		foreach($cases as $case => $eProduct) {

			$action = $this->postEntry($eCultivation, ['mode' => 'add', 'harvestMore' => '4', 'stock' => $eProduct['id']]);

			$this->assertEquals(['Task::stock.check'], $this->getFails($action), $case.' : le stock n\'est pas refusé');
			$this->assertEquals(10.0, $this->getStock($eProduct), $case.' : le stock refusé a bougé');

		}

		$this->assertEquals([], $this->getHarvested($eCultivation), 'une saisie refusée pour son stock a écrit une récolte');

	}

	/**
	 * Un retrait plus grand que le jour est refusé par `writeHarvestForWeek()` (`harvestMore.day`), après la
	 * construction du stock : ni la récolte ni le stock ne bougent, et la classe, stricte, vérifie qu'aucune
	 * transaction ne reste ouverte.
	 */
	public function testRefusedDay(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$ePlant = $this->createPlant();
		$eCultivation = $this->createCultivation(ePlant: $ePlant);
		$eProduct = $this->createProduct('Stock du retrait refusé', $this->getUnit(\series\Task::KG), 10.0, ePlant: $ePlant);

		$this->postEntry($eCultivation, ['mode' => 'add', 'harvestMore' => '2', 'stock' => $eProduct['id']]);

		$action = $this->postEntry($eCultivation, ['mode' => 'add', 'harvestMore' => '-5', 'stock' => $eProduct['id']]);

		$this->assertEquals(['Task::harvestMore.day'], $this->getFails($action), 'un retrait plus grand que le jour n\'est pas refusé');
		$this->assertEquals(12.0, $this->getStock($eProduct), 'le retrait refusé a touché le stock');
		$this->assertEquals([\Clock::date() => 2.0], $this->getHarvested($eCultivation), 'le retrait refusé a touché la récolte');

	}

	/**
	 * L'étoile posée retient le stock dans l'unité de la saisie, alors que la récolte n'en avait aucune : deux
	 * saisies sans champ de suite, comme la case de la grille, l'alimentent toutes les deux sans retirer
	 * l'étoile. Retirée, aucune saisie sans champ ne touche plus le stock.
	 */
	public function testBookmark(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$ePlant = $this->createPlant();
		$eCultivation = $this->createCultivation(ePlant: $ePlant);
		$eProduct = $this->createProduct('Stock étoilé', $this->getUnit(\series\Task::KG), 10.0, ePlant: $ePlant);

		$eTaskReference = HarvestStockLib::getReference(HarvestWeekLib::getCoupleProbe($this->eStructure, $eCultivation), \series\Task::KG);

		if($this->assertDecorPresent(HarvestStockLib::getBookmarked($eTaskReference)->empty(), 'une espèce neuve sans stock mémorisé') === FALSE) {
			return;
		}

		$this->postEntry($eCultivation, ['mode' => 'add', 'harvestMore' => '1', 'stock' => $eProduct['id'], 'stockRemember' => '1']);

		$this->assertEquals($eProduct['id'], HarvestStockLib::getBookmarked($eTaskReference)['id'] ?? NULL, 'l\'étoile ne retient pas le stock dans l\'unité de la saisie');

		foreach(['première', 'seconde'] as $case) {
			$action = $this->postEntry($eCultivation, ['mode' => 'add', 'harvestMore' => '2']);
			$this->assertTrue($action instanceof \ViewAction, $case.' saisie sans champ refusée : '.implode(', ', $this->getFails($action)));
		}

		$this->assertEquals(15.0, $this->getStock($eProduct), 'les saisies sans champ n\'alimentent pas le stock étoilé');
		$this->assertEquals($eProduct['id'], HarvestStockLib::getBookmarked($eTaskReference)['id'] ?? NULL, 'une saisie sans champ retire l\'étoile');

		$this->postEntry($eCultivation, ['mode' => 'add', 'harvestMore' => '1', 'stock' => '', 'stockRemember' => '0']);

		$this->assertTrue(HarvestStockLib::getBookmarked($eTaskReference)->empty(), 'l\'étoile retirée retient encore le stock');

		$this->postEntry($eCultivation, ['mode' => 'add', 'harvestMore' => '3']);

		$this->assertEquals(15.0, $this->getStock($eProduct), 'sans étoile, une saisie sans champ touche le stock');

	}

	/**
	 * Sans stock compatible, le champ est vide et rien ne se poste : la récolte s'écrit, sans mouvement de stock.
	 */
	public function testNoCompatible(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$ePlant = $this->createPlant();
		$eCultivation = $this->createCultivation(ePlant: $ePlant);
		$eProductOther = $this->createProduct('Stock d\'une autre espèce', $this->getUnit(\series\Task::KG), 10.0, ePlant: $this->createPlant());

		$eTaskReference = HarvestStockLib::fill(HarvestStockLib::getReference(HarvestWeekLib::getCoupleProbe($this->eStructure, $eCultivation), \series\Task::KG));

		$this->assertTrue($eTaskReference['cProductStock']->empty(), 'le champ propose un stock d\'une autre espèce');

		$action = $this->postEntry($eCultivation, ['mode' => 'add', 'harvestMore' => '4']);

		if($this->assertTrue($action instanceof \ViewAction, 'la saisie sans stock compatible est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals([\Clock::date() => 4.0], $this->getHarvested($eCultivation), 'la récolte sans stock compatible ne s\'écrit pas');
		$this->assertEquals(10.0, $this->getStock($eProductOther), 'un stock d\'une autre espèce a bougé');

	}

	/**
	 * « Supprimer » rend au stock choisi la somme des quantités parties du jour, en une fois.
	 */
	public function testDelete(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$ePlant = $this->createPlant();
		$eCultivation = $this->createCultivation(ePlant: $ePlant);
		$eProduct = $this->createProduct('Stock de la suppression', $this->getUnit(\series\Task::KG), 10.0, ePlant: $ePlant);

		$this->postEntry($eCultivation, ['mode' => 'add', 'harvestMore' => '5', 'stock' => $eProduct['id']]);
		$this->postEntry($eCultivation, ['mode' => 'add', 'harvestMore' => '2', 'stock' => $eProduct['id']]);

		if($this->assertDecorPresent($this->getStock($eProduct) === 17.0, '7 kg récoltés au stock avant la suppression') === FALSE) {
			return;
		}

		$action = $this->executePage('planning/harvestWeek:doHarvestDelete', [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'cultivation' => $eCultivation['id'],
			'date' => \Clock::date(),
			'stock' => $eProduct['id'],
			'stockRemember' => '0',
		], 'POST');

		if($this->assertTrue($action instanceof \ViewAction, '« Supprimer » est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals([], $this->getHarvested($eCultivation), '« Supprimer » laisse une récolte du jour');
		$this->assertEquals(10.0, $this->getStock($eProduct), '« Supprimer » ne rend pas au stock les 7 kg partis');

	}

	/**
	 * Un jour récolté en kilos et en pièces : « Supprimer » vide les deux unités (HN-B2c), et ne rend au stock,
	 * qui est dans l'unité de la saisie, que les kilos.
	 */
	public function testDeleteOthers(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$ePlant = $this->createPlant();
		$eCultivation = $this->createCultivation(ePlant: $ePlant);
		$eProduct = $this->createProduct('Stock de la suppression en deux unités', $this->getUnit(\series\Task::KG), 10.0, ePlant: $ePlant);

		$this->postEntry($eCultivation, ['mode' => 'add', 'harvestMore' => '5', 'stock' => $eProduct['id']]);
		$this->postEntry($eCultivation, ['mode' => 'add', 'harvestMore' => '3', 'harvestUnit' => \series\Task::UNIT, 'stock' => '']);

		if($this->assertDecorPresent($this->getStock($eProduct) === 15.0 and $this->sumByDate(\series\Harvest::model()->whereCultivation($eCultivation)->whereUnit(\series\Task::UNIT)) === [\Clock::date() => 3.0], '5 kg au stock et 3 pièces récoltées avant la suppression') === FALSE) {
			return;
		}

		$action = $this->executePage('planning/harvestWeek:doHarvestDelete', [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'cultivation' => $eCultivation['id'],
			'date' => \Clock::date(),
			'stock' => $eProduct['id'],
			'stockRemember' => '0',
		], 'POST');

		if($this->assertTrue($action instanceof \ViewAction, '« Supprimer » est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals([], $this->sumByDate(\series\Harvest::model()->whereCultivation($eCultivation)->whereUnit(\series\Task::UNIT)), '« Supprimer » laisse les pièces du jour');
		$this->assertEquals([], $this->getHarvested($eCultivation), '« Supprimer » laisse une récolte du jour');
		$this->assertEquals(10.0, $this->getStock($eProduct), '« Supprimer » rend au stock en kilos autre chose que les 5 kg partis');

	}

	/**
	 * Sur la fiche, un ajout de 3 kg monte le stock de 3, puis le remplacement du jour par 1 kg le baisse de
	 * l'écart. Un stock refusé n'écrit aucune récolte, et la fiche le propose dans l'unité de l'intervention.
	 */
	public function testDetail(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$ePlant = $this->createPlant();
		$eTask = $this->createTaskHarvest($this->createCultivation(ePlant: $ePlant), \Clock::week(), \Clock::date());
		$eProduct = $this->createProduct('Stock de la fiche', $this->getUnit(\series\Task::KG), 10.0, ePlant: $ePlant);
		$eProductOther = $this->createProduct('Stock d\'une autre espèce', $this->getUnit(\series\Task::KG), 10.0, ePlant: $this->createPlant());

		$action = $this->postDetail($eTask, ['harvestMore' => '3', 'harvestUnit' => \series\Task::KG, 'stock' => $eProductOther['id']]);

		$this->assertEquals(['Task::stock.check'], $this->getFails($action), 'un stock d\'une autre espèce n\'est pas refusé');
		$this->assertEquals([], $this->getHarvestedByTask($eTask), 'le stock refusé a laissé écrire la récolte');

		$cases = [
			'ajout' => [['harvestMore' => '3', 'harvestUnit' => \series\Task::KG], 13.0, 3.0],
			'remplacement à la baisse' => [['mode' => 'replace', 'harvestDay' => '1'], 11.0, 1.0],
		];

		foreach($cases as $case => [$input, $stock, $harvested]) {

			$action = $this->postDetail($eTask, $input + ['stock' => $eProduct['id']]);

			if($this->assertTrue($action instanceof \ViewAction, $case.' : la saisie est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
				return;
			}

			$this->assertEquals([\Clock::date() => $harvested], $this->getHarvestedByTask($eTask), $case.' : la récolte du jour');
			$this->assertEquals($stock, $this->getStock($eProduct), $case.' : le stock ne suit pas l\'écart du jour');

		}

		$data = new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);

		$this->assertEquals([$eProduct['id']], $data->eTask['eTaskStock']['cProductStock']->getIds(), 'la fiche ne propose pas le stock de l\'espèce en kilos');

	}

	/**
	 * « Nouvelle intervention » faite avec sa quantité verse au stock choisi, sur une série comme sur une espèce
	 * seule, qui se saisit en kilos.
	 */
	public function testCreate(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$cases = [
			'série' => fn(\plant\Plant $ePlant) => (string)$this->createCultivation(ePlant: $ePlant)['id'],
			'espèce seule' => fn(\plant\Plant $ePlant) => 'plant:'.$ePlant['id'],
		];

		foreach($cases as $case => $choice) {

			$ePlant = $this->createPlant();
			$eProduct = $this->createProduct('Stock de la nouvelle intervention', $this->getUnit(\series\Task::KG), 10.0, ePlant: $ePlant);

			$action = $this->executePage('planning/taskWeek:doCreate', [
				'id' => $this->eStructure['id'],
				'week' => \Clock::week(),
				'when' => \Clock::week(),
				'category' => $this->getCategoryCulture()['id'],
				'action' => $this->getAction(ACTION_RECOLTE)['id'],
				'status' => \series\Task::DONE,
				'day' => \Clock::date(),
				'description' => '',
				'cultivation' => [$choice($ePlant)],
				'harvestMore' => '4',
				'stock' => $eProduct['id'],
			], 'POST');

			if($this->assertTrue($action instanceof \ViewAction, $case.' : la récolte est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
				continue;
			}

			$this->assertEquals(14.0, $this->getStock($eProduct), $case.' : le stock ne reçoit pas les 4 kg');

		}

	}

	/**
	 * Une saisie du tiroir, aujourd'hui, sur la culture.
	 */
	private function postEntry(\series\Cultivation $eCultivation, array $input): ?\Action {

		return $this->executePage('planning/harvestWeek:doHarvest', $input + [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'cultivation' => $eCultivation['id'],
			'date' => \Clock::date(),
		], 'POST');

	}

	/**
	 * Une saisie de la fiche de l'intervention, aujourd'hui.
	 */
	private function postDetail(\series\Task $eTask, array $input): ?\Action {

		return $this->executePage('planning/taskDetail:doHarvest', $input + [
			'id' => $this->eStructure['id'],
			'task' => $eTask['id'],
			'harvestDate' => \Clock::date(),
		], 'POST');

	}

	/**
	 * Le récolté de la culture par jour, toutes interventions et unités confondues, sans les jours revenus à zéro.
	 */
	private function getHarvested(\series\Cultivation $eCultivation): array {

		return $this->sumByDate(\series\Harvest::model()->whereCultivation($eCultivation));

	}

	private function getHarvestedByTask(\series\Task $eTask): array {

		return $this->sumByDate(\series\Harvest::model()->whereTask($eTask));

	}

	private function sumByDate(\series\HarvestModel $mHarvest): array {

		$harvested = [];

		foreach($mHarvest->select(['date', 'quantity'])->getCollection() as $eHarvest) {
			$harvested[$eHarvest['date']] = round(($harvested[$eHarvest['date']] ?? 0.0) + $eHarvest['quantity'], 3);
		}

		return array_filter($harvested);

	}

	private function getStock(\product\Product $eProduct): ?float {

		return \product\ProductLib::getById($eProduct['id'])['stock'];

	}

	/**
	 * Un stock de l'espèce, dans l'unité de la saisie, rattaché à une autre structure : `getCompatibleProducts()`
	 * lit les stocks par l'espèce et l'unité, et seul `HarvestStockLib::build()` le refuse par sa structure.
	 */
	protected function createProductOther(\plant\Plant $ePlant): \product\Product {

		$eProduct = $this->createProduct('Stock d\'une autre structure', $this->getUnit(\series\Task::KG), 10.0, ePlant: $ePlant);

		// Écrit en direct : `build()` refuserait l'espèce d'une structure dont le produit n'est pas, et c'est
		// justement ce produit-là que le cas éprouve.
		\product\Product::model()->update($eProduct, ['structure' => \structure\FixtureAiLib::F2]);

		return \product\ProductLib::getById($eProduct['id']);

	}

}
?>
