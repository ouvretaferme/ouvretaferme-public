<?php
namespace entry;

/**
 * L'annulation d'une récolte saisie, depuis le lien « Annuler » de sa pastille : ce qu'elle défait,
 * et ce qu'elle refuse.
 *
 * Aucun refus éprouvé ici ne passe par un `rollBack()` : la transaction du test l'emporterait avec
 * lui (`EntryAtomicity.t.php`). Une seconde annulation est donc éprouvée par la voie séquentielle,
 * où la récolte n'est déjà plus lue avant la transaction.
 */
class CancelTest extends EntryFixtureTest {

	/**
	 * Le jeton du ticket de chaque récolte écrite, par id de récolte.
	 */
	private array $tickets = [];

	/**
	 * Écrit une phrase et rend la récolte que l'écriture a posée.
	 */
	private function write(string $quantity): \series\Harvest {

		$reading = $this->read($this->getText($quantity.' '));

		$this->assertEquals(EntryLib::WRITE, $reading['outcome'], 'la phrase « '.$quantity.' » s\'écrit');

		EntryLib::write($this->eStructure, $reading);

		$eHarvest = $reading['confirmation']['eTask']['eHarvest'];

		$this->tickets[$eHarvest['id']] = $reading['undo']['ticket'];

		return $eHarvest;

	}

	private function cancel(\series\Harvest $eHarvest): array {

		return $this->cancelUndo(['intention' => 'harvest', 'ticket' => $this->tickets[$eHarvest['id']]]);

	}

	private function countHarvest(\series\Task $eTask): int {

		return \series\Harvest::model()
			->whereTask($eTask)
			->count();

	}

	/**
	 * La saisie avait créé l'intervention : elle part avec sa récolte.
	 */
	public function testCreated(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$eHarvest = $this->write('12 kg');

		$cancellation = $this->cancel($eHarvest);

		$this->assertTrue($cancellation['created'], 'l\'intervention est reconnue comme créée par la saisie');
		$this->assertEquals(0, $this->getHarvest($eCultivation)->count(), 'l\'intervention créée est supprimée');
		$this->assertEquals(0, $this->countHarvest($eHarvest['task']), 'la ligne de récolte est supprimée');

	}

	/**
	 * Une récolte datée de la semaine passée s'annule comme celle du jour : l'intervention que la saisie
	 * a créée cette semaine-là part avec elle.
	 */
	public function testCreatedDated(): void {

		$this->setClockInSeason();

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$date = date('Y-m-d', \Clock::shift('-7 days'));

		$eHarvest = $this->write('12 kg '.DateComplementUi::getToken($date));

		$this->assertEquals($date, $eHarvest['date']);

		$cancellation = $this->cancel($eHarvest);

		$this->assertTrue($cancellation['created'], 'l\'intervention est reconnue comme créée par la saisie');
		$this->assertEquals(0, $this->getHarvest($eCultivation)->count(), 'l\'intervention créée est supprimée');

	}

	/**
	 * Sur une intervention qui portait déjà une récolte, seule la seconde s'annule : la quantité revient
	 * à celle d'avant, par jour comme au total.
	 */
	public function testExisting(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$this->write('45 kg');
		$eHarvest = $this->write('12 kg');

		$cancellation = $this->cancel($eHarvest);

		$this->assertFalse($cancellation['created'], 'une intervention qui porte une autre récolte n\'est pas créée par la saisie');

		$cTask = $this->getHarvest($eCultivation);

		$this->assertEquals(1, $cTask->count(), 'l\'intervention reste');
		$this->assertEquals(45.0, (float)$cTask->first()['harvest']);
		$this->assertEquals(1, $this->countHarvest($cTask->first()), 'la première récolte reste');

		$eTask = \series\Task::model()
			->select(\series\Task::getSelection())
			->whereId($cTask->first())
			->get();

		\series\TaskLib::fillHarvestDates(new \Collection([$eTask]));

		$this->assertEquals(45.0, (float)$eTask['harvestDates'][\Clock::date()]);

	}

	/**
	 * Un retrait s'annule comme un ajout : sa ligne négative part, et le jour retrouve ce qu'il comptait.
	 */
	public function testRemoved(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$this->write('45 kg');
		$eHarvest = $this->write('-12 kg');

		$this->assertEquals(-12.0, (float)$eHarvest['quantity']);

		$cancellation = $this->cancel($eHarvest);

		$this->assertFalse($cancellation['created']);

		$cTask = $this->getHarvest($eCultivation);

		$this->assertEquals(45.0, (float)$cTask->first()['harvest']);
		$this->assertEquals(1, $this->countHarvest($cTask->first()), 'la ligne du retrait est supprimée');

	}

	/**
	 * Une intervention que la saisie avait créée, puis commentée : la récolte s'annule, l'intervention
	 * et son commentaire restent.
	 */
	public function testCreatedThenCommented(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$eHarvest = $this->write('12 kg');

		$eTask = \series\Task::model()
			->select(['id', 'structure', 'series', 'cultivation'])
			->whereId($eHarvest['task'])
			->get();

		\series\CommentLib::create(new \series\Comment([
			'task' => $eTask,
			'text' => 'Commentaire du décor',
		]));

		$cancellation = $this->cancel($eHarvest);

		$this->assertFalse($cancellation['created'], 'une intervention commentée n\'est plus celle de la saisie');

		$cTask = $this->getHarvest($eCultivation);

		$this->assertEquals(1, $cTask->count(), 'l\'intervention commentée reste');
		$this->assertEquals(0.0, (float)$cTask->first()['harvest']);
		$this->assertEquals(0, $this->countHarvest($eTask));

	}

	/**
	 * Une intervention que la saisie avait créée, puis retouchée par un autre geste dans le délai
	 * d'annulation : la récolte s'annule, l'intervention et sa retouche restent.
	 */
	public function testCreatedThenUpdated(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$eHarvest = $this->write('12 kg');

		$eTask = \series\Task::model()
			->select(\series\Task::getSelection())
			->whereId($eHarvest['task'])
			->get();

		$eTask['description'] = 'Retouche du décor';
		\series\TaskLib::update($eTask, ['description']);

		$cancellation = $this->cancel($eHarvest);

		$this->assertFalse($cancellation['created'], 'une intervention retouchée n\'est plus celle de la saisie');

		$cTask = $this->getHarvest($eCultivation);

		$this->assertEquals(1, $cTask->count(), 'l\'intervention retouchée reste');
		$this->assertEquals(0.0, (float)$cTask->first()['harvest']);

	}

	/**
	 * Deux saisies sur l'intervention que la première a créée, annulées l'une après l'autre : la seconde
	 * ne retouche pas l'intervention, qui part avec la dernière récolte.
	 */
	public function testCreatedThenEntered(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$eHarvestFirst = $this->write('3 kg');

		$eHarvestSecond = $this->write('2 kg');

		$this->assertTrue($eHarvestSecond['task']->is($eHarvestFirst['task']), 'la seconde saisie va sur l\'intervention créée par la première');

		$this->assertFalse($this->cancel($eHarvestSecond)['created'], 'la seconde annulation laisse la première récolte');

		// La seconde saisie et son annulation viennent au moins une seconde après la première, et déplacent
		// la date de modification : le test, qui les enchaîne dans la même seconde, la repose plus tard.
		\series\Task::model()->update(new \series\Task(['id' => $eHarvestFirst['task']['id']]), ['updatedAt' => new \Sql('NOW() + INTERVAL 2 SECOND')]);

		$this->assertTrue($this->cancel($eHarvestFirst)['created'], 'l\'intervention reste celle de la saisie');
		$this->assertEquals(0, $this->getHarvest($eCultivation)->count(), 'l\'intervention créée est supprimée');

	}

	/**
	 * Une seconde annulation ne trouve plus la récolte, que la saisie ait créé l'intervention ou non.
	 */
	public function testTwiceCreated(): void {

		$this->createCultivationInPhase(TRUE);

		$this->checkTwice($this->write('12 kg'));

	}

	public function testTwiceExisting(): void {

		$this->createCultivationInPhase(TRUE);

		$this->write('45 kg');

		$this->checkTwice($this->write('12 kg'));

	}

	private function checkTwice(\series\Harvest $eHarvest): void {

		$this->cancel($eHarvest);

		$this->assertTrue(HarvestIntentionLib::getCancellable($this->eStructure, $this->tickets[$eHarvest['id']])->empty(), 'la récolte annulée n\'est plus annulable');

		$action = $this->executePageAction('entry/entry:doCancel', [
			'structure' => $this->eStructure['id'],
			'intention' => 'harvest',
			'ticket' => $this->tickets[$eHarvest['id']],
		]);

		$this->assertTrue($action instanceof \FailAction, 'la seconde annulation est refusée');

	}

	/**
	 * Seul l'auteur de la saisie l'annule, et seulement dans sa structure.
	 */
	public function testForeign(): void {

		$this->createCultivationInPhase(TRUE);

		$eHarvest = $this->write('12 kg');

		$this->assertTrue(HarvestIntentionLib::getCancellable($this->eStructure, $this->tickets[$eHarvest['id']])->notEmpty(), 'l\'auteur trouve sa récolte');

		if($this->requireStructureForeign()) {
			$this->assertTrue(HarvestIntentionLib::getCancellable($this->getStructureForeign(), $this->tickets[$eHarvest['id']])->empty(), 'une autre structure ne la trouve pas');
		}

		$eUserOther = new \user\User();

		foreach(\structure\MemberLib::getByStructure($this->eStructure, index: NULL) as $eMember) {

			if($eMember['user']->notEmpty() and $eMember['user']->is($this->getMember()) === FALSE) {
				$eUserOther = $eMember['user'];
				break;
			}

		}

		if($this->assertTrue($eUserOther->notEmpty(), 'la structure '.static::STRUCTURE.' a un second membre') === FALSE) {
			return;
		}

		\user\ConnectionLib::setOnline($eUserOther);

		try {
			$this->assertTrue(HarvestIntentionLib::getCancellable($this->eStructure, $this->tickets[$eHarvest['id']])->empty(), 'un autre membre ne la trouve pas');
		} finally {
			\user\ConnectionLib::setOnline($this->getMember());
		}

		$this->assertEquals(1, $this->countHarvest($eHarvest['task']), 'rien n\'a été annulé');

	}

	/**
	 * La saisie a terminé l'intervention à faire de la semaine (Q1) : Annuler la remet à faire, à sa semaine,
	 * sans le jour, la récolte ni l'unité que la saisie lui avait donnés (Q4), et l'historique le dit.
	 */
	public function testTodoRestored(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$eTask = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		$eHarvest = $this->write('12 kg');

		$this->assertEquals($eTask['id'], $eHarvest['task']['id'], 'la saisie remplit l\'intervention à faire de la semaine');
		$this->assertEquals([\series\Task::DONE, \Clock::date()], array_values($this->getTaskState($eTask, ['status', 'plannedDate'])), 'la saisie la termine au jour récolté');

		$cancellation = $this->cancel($eHarvest);

		$this->assertFalse($cancellation['created'], 'une intervention à faire n\'est pas créée par la saisie');
		$this->assertEquals([
			'status' => \series\Task::TODO,
			'plannedWeek' => \Clock::week(),
			'plannedDate' => NULL,
			'doneWeek' => NULL,
			'doneDate' => NULL,
			'harvest' => NULL,
			'harvestUnit' => NULL,
		], $this->getTaskState($eTask, ['status', 'plannedWeek', 'plannedDate', 'doneWeek', 'doneDate', 'harvest', 'harvestUnit']), 'l\'intervention revient à faire comme avant');

		$this->assertTrue(\series\History::model()->whereTask($eTask)->whereEvent(\series\History::TODO)->exists(), 'l\'historique dit la remise à faire');

	}

	/**
	 * Une intervention en retard (cas A) que la saisie a terminée et replanifiée au jour récolté revient à
	 * son jour prévu d'avant.
	 */
	public function testDelayedRestored(): void {

		$this->setClockInSeason();

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$planned = date('Y-m-d', \Clock::shift('-14 days'));
		$eTask = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, toWeek($planned), $planned);

		$eHarvest = $this->write('12 kg');

		$this->assertEquals($eTask['id'], $eHarvest['task']['id'], 'la saisie remplit l\'intervention en retard');

		$this->cancel($eHarvest);

		$this->assertEquals([\series\Task::TODO, toWeek($planned), $planned], array_values($this->getTaskState($eTask, ['status', 'plannedWeek', 'plannedDate'])), 'l\'intervention en retard revient à son jour prévu');

	}

	/**
	 * Consigner a écrit sur l'intervention après la saisie : elle n'est plus dans l'état que la saisie a
	 * laissé, Annuler ne retire que la récolte de la saisie, et l'intervention reste faite.
	 */
	public function testTodoKeptAfterConsigner(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$eTask = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		$eHarvest = $this->write('12 kg');

		\planning\HarvestWeekLib::writeEntry($this->eStructure, \series\CultivationLib::getById($eCultivation['id']), \planning\HarvestWeekLib::getDays(\Clock::week()), [
			'date' => \Clock::date(),
			'mode' => 'add',
			'harvestMore' => '3',
		], stock: FALSE);

		$this->cancel($eHarvest);

		$this->assertEquals([\series\Task::DONE, 3.0], array_values($this->getTaskState($eTask, ['status', 'harvest'])), 'l\'intervention reste faite avec la récolte de Consigner');

	}

	/**
	 * Le ticket expiré, l'annulation est refusée entière : rien n'est défait à moitié.
	 */
	public function testTicketExpired(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$eTask = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		$eHarvest = $this->write('12 kg');

		\Cache::redis()->delete(HarvestIntentionLib::getCancelKey($this->eStructure, $this->tickets[$eHarvest['id']]));

		$this->assertTrue(HarvestIntentionLib::getCancellable($this->eStructure, $this->tickets[$eHarvest['id']])->empty(), 'sans ticket, la récolte n\'est plus annulable');

		$action = $this->executePageAction('entry/entry:doCancel', [
			'structure' => $this->eStructure['id'],
			'intention' => 'harvest',
			'ticket' => $this->tickets[$eHarvest['id']],
		]);

		$this->assertTrue($action instanceof \FailAction, 'l\'annulation sans ticket est refusée');
		$this->assertEquals([\series\Task::DONE, 12.0], array_values($this->getTaskState($eTask, ['status', 'harvest'])), 'rien n\'est défait');

	}

	private function getTaskState(\series\Task $eTask, array $properties): array {

		$eTaskRead = \series\Task::model()
			->select($properties)
			->whereId($eTask)
			->get();

		$state = $eTaskRead->extracts($properties);

		// Un flottant simple en base, relu en double : arrondi pour se comparer à la quantité saisie.
		if(in_array('harvest', $properties) and $state['harvest'] !== NULL) {
			$state['harvest'] = round((float)$state['harvest'], 2);
		}

		return $state;

	}

	/**
	 * Le stock que la saisie avait rempli se vide de la même quantité.
	 */
	public function testStock(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$eProduct = $this->createProductStock();

		$eHarvest = $this->write('12 kg');

		$this->assertEquals(22.0, $this->getStock($eProduct), 'la saisie remplit le stock');

		$cancellation = $this->cancel($eHarvest);

		$this->assertTrue($cancellation['eStock']->notEmpty(), 'la ligne de stock de la saisie est retrouvée');
		$this->assertEquals(10.0, $this->getStock($eProduct), 'l\'annulation le vide d\'autant');
		$this->assertEquals(0, $this->getHarvest($eCultivation)->count());

	}

	/**
	 * Un retrait baisse le stock jusqu'à zéro, et son annulation rend ce qu'il a retiré, pas plus. Le produit est
	 * créé après la récolte, pour que la ligne de stock du retrait soit la seule de la saisie à reconnaître.
	 */
	public function testStockRemoved(): void {

		$this->createCultivationInPhase(TRUE);

		$this->write('30 kg');

		$eProduct = $this->createProductStock();

		$eHarvest = $this->write('-12 kg');

		$this->assertEquals(0.0, $this->getStock($eProduct), 'le retrait vide le stock sans le passer sous zéro');

		$cancellation = $this->cancel($eHarvest);

		$this->assertTrue($cancellation['eStock']->notEmpty(), 'la ligne de stock du retrait est retrouvée');
		$this->assertEquals(10.0, $this->getStock($eProduct), 'l\'annulation rend ce que le retrait a ôté');

	}

	/**
	 * La ligne d'historique de la saisie modifiée entre-temps n'est plus reconnue : le stock n'est pas
	 * touché, et le message le dit.
	 */
	public function testStockNotFound(): void {

		$this->createCultivationInPhase(TRUE);

		$eProduct = $this->createProductStock();

		$eHarvest = $this->write('12 kg');

		\product\Stock::model()
			->whereProduct($eProduct)
			->where('comment IS NOT NULL')
			->update(['comment' => 'Commentaire retouché']);

		$cancellation = $this->cancel($eHarvest);

		$this->assertTrue($cancellation['eProduct']->notEmpty());
		$this->assertTrue($cancellation['eStock']->empty(), 'la ligne retouchée n\'est pas retrouvée');
		$this->assertEquals(22.0, $this->getStock($eProduct), 'le stock n\'est pas touché');

		$message = HarvestIntentionUi::getCancelled([
			'quantity' => 12.0,
			'unit' => \series\Task::KG,
			'minutes' => NULL,
			'plant' => $this->getPlant()['name'],
			'date' => \Clock::date(),
			'product' => $eProduct['name'],
		]);

		$this->assertTrue(str_contains($message, $eProduct['name']), 'le message nomme le produit dont le stock n\'a pas bougé');

	}

	/**
	 * Un produit suivi en stock (10 kg), que l'écran de récolte présélectionne pour l'espèce du décor
	 * au kilo.
	 */
	private function createProductStock(): \product\Product {

		$vat = array_search(5.5, \product\ProductSetting::getVatRates($this->eStructure));

		$eProduct = new \product\Product([
			'structure' => $this->eStructure,
			'status' => \product\Product::ACTIVE,
			'profile' => \product\Product::PLANT,
			'distribution' => \product\Product::PRODUCTION,
			'name' => 'Produit '.$this->getPlant()['name'],
			'unit' => \product\UnitLib::getOneByStructure($this->eStructure, 'kg'),
			'plant' => $this->getPlant(),
			'variety' => NULL,
			'vat' => (int)$vat,
			'organic' => \product\Product::NO,
			'qualities' => new \Set(),
			'private' => TRUE,
			'privatePrice' => 3.0,
			'privateStep' => NULL,
			'pro' => TRUE,
			'proPrice' => 2.0,
			'proPackaging' => NULL,
		]);

		\product\ProductLib::create($eProduct);

		$eProduct = \product\ProductLib::getById($eProduct['id']);

		\product\StockLib::enable($eProduct);
		\product\StockLib::set($eProduct, 10.0);

		\product\StockLib::remember(new \series\Task([
			'structure' => $this->eStructure,
			'plant' => $this->getPlant(),
			'harvestUnit' => \series\Task::KG,
			'variety' => new \plant\Variety(),
			'harvestSize' => new \plant\Size(),
		]), $eProduct);

		return $eProduct;

	}

	private function getStock(\product\Product $eProduct): float {

		return (float)\product\Product::model()
			->select('stock')
			->whereId($eProduct)
			->get()['stock'];

	}

}
?>
