<?php
namespace series;

/**
 * Test bout-en-bout de la ressource series\Task (production/series/api/Task_0_1beta.a.php), restée
 * sans filet jusqu'ici alors qu'elle accepte création, modification et suppression.
 *
 * La saisie d'une récolte y tient la place principale : c'est le seul chemin par lequel l'API écrit
 * une quantité récoltée, et il touche trois tables à la fois, le total de l'intervention, le
 * journal daté (series\Harvest) et les cumuls de la mise en culture.
 *
 * L'intervention de récolte est celle du décor (`SeriesFixtureAiTest`), terminée, avec une quantité
 * déjà saisie par le chemin de l'écran : les cas lisaient la récolte la plus récente de la
 * structure, dont le total et le journal changeaient avec chaque saisie et chaque rechargement.
 *
 * Lancement : dev/test package=series file=TaskApiAi
 */
class TaskApiAiTest extends SeriesFixtureAiTest {

	/** La quantité déjà récoltée sur l'intervention du décor, avant tout geste du cas. */
	const HARVEST_EXISTING = 10.0;

	/** Ce que tout cas exige de la structure : une équipe, et les deux actions des interventions du décor. */
	private function requireDecor(): bool {

		return ($this->requireMember() and $this->requireAction(ACTION_RECOLTE) and $this->requireAction(ACTION_PLANTATION));

	}

	/**
	 * L'intervention de récolte du décor, avec sa quantité, relue entière : c'est le total et
	 * l'unité tels que la base les porte que les cas comparent à la réponse de l'API.
	 */
	private function getTaskHarvestFilled(): Task {

		return $this->once('taskHarvestFilled', function() {

			$eTask = $this->getTaskHarvest();

			$this->createHarvest($eTask, self::HARVEST_EXISTING, toDate(strtotime('NOW - 7 DAY')));

			return Task::model()
				->select(Task::getSelection())
				->whereId($eTask)
				->get();

		});

	}

	public function testList(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eTask = $this->getTask();

		$response = $this->callApi('GET', 'taskList', get: ['start' => 1, 'limit' => 20, 'ids' => [$eTask['id']]]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue(array_key_exists('count', $response['body']));
		$this->assertTrue(array_key_exists('list', $response['body']));
		$this->assertEquals([$eTask['id']], array_column($response['body']['list'], 'id'), 'l\'intervention du décor est dans la liste');

	}

	public function testRead(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eTask = $this->getTaskHarvestFilled();

		$response = $this->callApi('GET', 'task/{id}', get: ['id' => $eTask['id']]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($eTask['id'], $response['body']['id'] ?? NULL);
		$this->assertEquals(self::HARVEST_EXISTING, $response['body']['harvest'] ?? NULL);
		$this->assertEquals($eTask['harvestUnit'], $response['body']['harvestUnit'] ?? NULL);
		$this->assertFalse(array_key_exists('createdBy', $response['body']));

	}

	/** Une intervention d'une autre structure ne se lit pas. */
	public function testReadIsScopedToStructure(): void {

		if($this->requireDecor() === FALSE or $this->requireStructureForeign() === FALSE) {
			return;
		}

		$eTaskForeign = $this->createTaskForeign();

		$response = $this->callApi('GET', 'task/{id}', get: ['id' => $eTaskForeign['id']]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

	/**
	 * La quantité s'ajoute au total de l'intervention, une ligne datée entre au journal, et les cumuls
	 * de la mise en culture suivent.
	 */
	public function testHarvest(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eTask = $this->getTaskHarvestFilled();

		$this->assertEquals(1, Harvest::model()->whereTask($eTask)->count(), 'le décor a mis une ligne au journal');

		$response = $this->callApi('PATCH', 'task/{id}/harvest', body: [
			'quantity' => 4.0,
			'date' => \Clock::date()
		], get: ['id' => $eTask['id']]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(round(self::HARVEST_EXISTING + 4.0, 1), $response['body']['harvest'] ?? NULL, 'la quantité s\'ajoute au total');
		$this->assertEquals($eTask['harvestUnit'], $response['body']['harvestUnit'] ?? NULL);

		$this->assertEquals(2, Harvest::model()->whereTask($eTask)->count(), 'une ligne est entrée au journal');

		$eHarvest = Harvest::model()
			->select(['quantity', 'date', 'week', 'cultivation'])
			->whereTask($eTask)
			->whereDate(\Clock::date())
			->get();

		$this->assertEquals(4.0, $eHarvest['quantity']);
		$this->assertEquals(\Clock::date(), $eHarvest['date']);
		$this->assertEquals(toWeek(\Clock::date()), $eHarvest['week']);
		$this->assertEquals($eTask['cultivation']['id'], $eHarvest['cultivation']['id'], 'la ligne porte la mise en culture de l\'intervention');

		$cumulated = Cultivation::model()->whereId($eTask['cultivation'])->getValue('harvested');

		$this->assertEquals(round(self::HARVEST_EXISTING + 4.0, 1), round($cumulated, 1), 'le cumul de la mise en culture suit, la récolte étant dans son unité');

	}

	/**
	 * Une quantité négative corrige une saisie de trop. Le journal en garde la trace comme d'un
	 * mouvement à part entière : il enregistre ce qui s'est passé, pas l'état du jour. Une correction
	 * qui effacerait la ligne de trop ferait disparaître les deux gestes au lieu d'en montrer deux.
	 */
	public function testHarvestAcceptsCorrection(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eTask = $this->getTaskHarvestFilled();

		$journalBefore = Harvest::model()->whereTask($eTask)->count();

		$this->callApi('PATCH', 'task/{id}/harvest', body: [
			'quantity' => 6.0,
			'date' => \Clock::date()
		], get: ['id' => $eTask['id']]);

		$response = $this->callApi('PATCH', 'task/{id}/harvest', body: [
			'quantity' => -2.0,
			'date' => \Clock::date()
		], get: ['id' => $eTask['id']]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(round(self::HARVEST_EXISTING + 4.0, 1), $response['body']['harvest'] ?? NULL, 'le total de l\'intervention est corrigé');
		$this->assertEquals(round(self::HARVEST_EXISTING + 4.0, 1), round(Task::model()->whereId($eTask)->getValue('harvest'), 1), 'et il l\'est en base');

		$this->assertEquals($journalBefore + 2, Harvest::model()->whereTask($eTask)->count(), 'les deux gestes sont au journal');

		$quantities = Harvest::model()
			->select(['quantity'])
			->whereTask($eTask)
			->whereDate(\Clock::date())
			->sort(['id' => SORT_DESC])
			->getCollection()
			->getColumn('quantity');

		$this->assertEquals([-2.0, 6.0], $quantities, 'la correction est une ligne négative, la saisie reste');

	}

	/**
	 * Une correction qui ramène la journée à zéro est le cas limite accepté : c'est ce qu'on fait pour
	 * annuler une saisie du jour. Un retrait de plus est refusé (cf testHarvestRejectsNegativeDay).
	 */
	public function testHarvestAcceptsCancellingTheDay(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eTask = $this->getTaskHarvestFilled();

		$this->callApi('PATCH', 'task/{id}/harvest', body: [
			'quantity' => 3.0,
			'date' => \Clock::date()
		], get: ['id' => $eTask['id']]);

		$response = $this->callApi('PATCH', 'task/{id}/harvest', body: [
			'quantity' => -3.0,
			'date' => \Clock::date()
		], get: ['id' => $eTask['id']]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(round(self::HARVEST_EXISTING, 1), $response['body']['harvest'] ?? NULL, 'le total revient à ce qu\'il était');

	}

	/**
	 * Un retrait massif est refusé.
	 *
	 * C'est la garde de la journée qui parle, pas celle du total : le total étant la somme des
	 * journées, il ne peut pas devenir négatif tant qu'aucune journée ne le devient. La seconde garde
	 * de buildHarvests() ne se déclenche donc que si Task.harvest et le journal ont divergé, ce que
	 * l'API n'a aucun moyen de provoquer : retirée, elle ne fait rougir aucun cas d'ici.
	 */
	public function testHarvestRejectsMassiveRemoval(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eTask = $this->getTaskHarvestFilled();

		$response = $this->callApi('PATCH', 'task/{id}/harvest', body: [
			'quantity' => -1000.0,
			'date' => \Clock::date()
		], get: ['id' => $eTask['id']]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('resource_error', $response['body'][0]['type'] ?? NULL);

	}

	/**
	 * Retirer plus que ce qui a été récolté ce jour-là est refusé, même quand le total de
	 * l'intervention resterait positif : les deux gardes de buildHarvests() sont distinctes, et un
	 * retrait de mille kilos les déclencherait toutes les deux sans dire laquelle a parlé. La
	 * quantité du décor est saisie une semaine avant le jour du cas, pour que le total l'absorbe.
	 */
	public function testHarvestRejectsNegativeDay(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eTask = $this->getTaskHarvestFilled();

		$this->assertTrue(self::HARVEST_EXISTING > 5.0, 'le total de l\'intervention absorbe le retrait');

		$this->callApi('PATCH', 'task/{id}/harvest', body: [
			'quantity' => 2.0,
			'date' => \Clock::date()
		], get: ['id' => $eTask['id']]);

		// La journée passerait à -3 alors que le total resterait au-dessus de zéro
		$response = $this->callApi('PATCH', 'task/{id}/harvest', body: [
			'quantity' => -5.0,
			'date' => \Clock::date()
		], get: ['id' => $eTask['id']]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Task::harvestDates.negative', $response['body'][0]['code'] ?? NULL);

		$this->assertEquals(
			round(self::HARVEST_EXISTING + 2.0, 1),
			round(Task::model()->whereId($eTask)->getValue('harvest'), 1),
			'le refus n\'a rien écrit'
		);

	}

	/**
	 * Une intervention qui n'est pas une récolte n'en reçoit pas, et le refus se lit.
	 *
	 * Sans ce contrôle, TaskLib::buildHarvests() refuserait quand même, mais par une NotExpectedAction
	 * que \ApiRoute::run() rend en 404 unexpected_data : un refus juste, qui ne dit pas ce qui cloche.
	 * C'est cet écart que le cas mesure.
	 */
	public function testHarvestRejectsOtherAction(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eTaskOther = $this->getTask();

		$response = $this->callApi('PATCH', 'task/{id}/harvest', body: [
			'quantity' => 1.0,
			'date' => \Clock::date()
		], get: ['id' => $eTaskOther['id']]);

		$this->assertEquals(412, $response['status']);
		$this->assertEquals('task_not_harvest', $response['body'][0]['code'] ?? NULL);

	}

	public function testHarvestRequiresQuantityAndDate(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eTask = $this->getTaskHarvestFilled();

		$missingQuantity = $this->callApi('PATCH', 'task/{id}/harvest', body: ['date' => \Clock::date()], get: ['id' => $eTask['id']]);
		$missingDate = $this->callApi('PATCH', 'task/{id}/harvest', body: ['quantity' => 1.0], get: ['id' => $eTask['id']]);

		$this->assertEquals('missing_quantity', $missingQuantity['body'][0]['code'] ?? NULL);
		$this->assertEquals('missing_date', $missingDate['body'][0]['code'] ?? NULL);

	}

	/**
	 * Une première récolte sans unité est refusée. Relevé en production le 2026-10-06 sur le panneau
	 * « Compléter la récolte » : harvestUnit est nullable pour les autres interventions, l'unité vide
	 * passait la construction, et la ligne du journal levait en base sur sa colonne unit.
	 */
	public function testHarvestRequiresUnit(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eTask = $this->createTaskHarvest();

		// Sans build() : une intervention de récolte sans unité est l'état que le cas éprouve,
		// celui d'une intervention créée sans culture ou avant qu'on ait choisi son unité.
		Task::model()->update($eTask, ['harvestUnit' => NULL]);

		$response = $this->callApi('PATCH', 'task/{id}/harvest', body: [
			'quantity' => 10.0,
			'date' => \Clock::date()
		], get: ['id' => $eTask['id']]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Task::harvestUnit.check', $response['body'][0]['code'] ?? NULL);
		$this->assertEquals(0, Harvest::model()->whereTask($eTask)->count(), 'le refus n\'a rien mis au journal');

	}

	/** La quantité récoltée ne se remplace pas par une modification ordinaire. */
	public function testHarvestIsNotWritableByUpdate(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eTask = $this->getTaskHarvestFilled();

		$response = $this->callApi('PUT', 'task/{id}', body: [
			'properties' => ['harvest'],
			'harvest' => 999.0
		], get: ['id' => $eTask['id']]);

		$this->assertDiffer(200, $response['status'], 'la propriété n\'est pas modifiable');
		$this->assertEquals(self::HARVEST_EXISTING, Task::model()->whereId($eTask)->getValue('harvest'), 'le total est intact');

	}

	/**
	 * Une réalisation envoyée à `null` est refusée comme une réalisation absente. Relevé en production
	 * le 2026-10-06 : l'étape `done` attendait un tableau, et le `null` d'un corps JSON la faisait lever
	 * au lieu de répondre une erreur de validation.
	 */
	public function testUpdateRejectsNullDone(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eTask = $this->getTaskHarvestFilled();

		$response = $this->callApi('PUT', 'task/{id}', body: [
			'properties' => ['done'],
			'done' => NULL
		], get: ['id' => $eTask['id']]);

		$this->assertEquals(400, $response['status'], 'la réalisation nulle est refusée');
		$this->assertEquals($eTask['doneWeek'], Task::model()->whereId($eTask)->getValue('doneWeek'), 'la semaine de réalisation est intacte');

	}

	/** Le corps d'une intervention à faire sur la mise en culture du décor, tel que l'API l'attend. */
	private function getCreateBody(Cultivation $eCultivation): array {

		return [
			'status' => Task::TODO,
			'category' => $this->getCategoryCulture()['id'],
			'series' => $eCultivation['series']['id'],
			'cultivation' => $eCultivation['id'],
			'action' => $this->getAction(ACTION_PLANTATION)['id'],
			'methods' => [],
			'tools' => [],
			'products' => [],
			'description' => 'Créée par l\'API',
			'harvestSize' => NULL,
			'harvestUnit' => NULL,
			'fertilizer' => NULL,
			'treatment' => NULL,
			'variety' => NULL,
			'planned' => ['week' => $this->getSeason().'-W20'],
			'timeExpected' => NULL
		];

	}

	/** L'écran tient le statut, la catégorie et la série de sa page : l'API les lit dans le corps. */
	public function testCreate(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eCultivation = $this->getCultivation();

		$response = $this->callApi('POST', 'task', body: $this->getCreateBody($eCultivation));

		$this->assertEquals(201, $response['status']);
		$this->assertEquals($eCultivation['series']['id'], $response['body']['series'] ?? NULL);
		$this->assertEquals($eCultivation['id'], $response['body']['cultivation'] ?? NULL);
		$this->assertEquals($this->getSeason().'-W20', $response['body']['plannedWeek'] ?? NULL);
		$this->assertEquals(Task::TODO, $response['body']['status'] ?? NULL);

	}

	/** Un corps vide se refuse en 400 et nomme ce qui manque, plutôt que de lever en 500. */
	public function testCreateRequiresStatusAndCategory(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$empty = $this->callApi('POST', 'task', body: []);
		$withoutCategory = $this->callApi('POST', 'task', body: ['status' => Task::TODO]);
		$unknownStatus = $this->callApi('POST', 'task', body: ['status' => 'sonde', 'category' => $this->getCategoryCulture()['id']]);

		$this->assertEquals(400, $empty['status']);
		$this->assertEquals('missing_status', $empty['body'][0]['code'] ?? NULL);
		$this->assertEquals('missing_category', $withoutCategory['body'][0]['code'] ?? NULL);
		$this->assertEquals('unknown_status', $unknownStatus['body'][0]['code'] ?? NULL);

	}

	/** Les références du corps sont bornées par la structure, et la mise en culture par la série. */
	public function testCreateIsScopedToStructure(): void {

		if($this->requireDecor() === FALSE or $this->requireStructureForeign() === FALSE) {
			return;
		}

		// Insertion directe, comme createTaskForeign() : createSeries() pose la structure du décor, et le
		// cas veut une série d'une autre structure, dont build() n'aurait aucune valeur à refuser.
		$eSeriesForeign = new Series([
			'structure' => $this->getStructureForeign(),
			'name' => 'Série étrangère',
			'cycle' => Series::ANNUAL,
			'use' => Series::BLOCK,
			'mode' => Series::OPEN_FIELD,
			'season' => $this->getSeason(),
			'plants' => 0
		]);

		Series::model()->insert($eSeriesForeign);

		$body = $this->getCreateBody($this->getCultivation());

		$foreignSeries = $this->callApi('POST', 'task', body: ['series' => $eSeriesForeign['id']] + $body);
		$foreignCultivation = $this->callApi('POST', 'task', body: ['cultivation' => $this->createCultivation($this->createSeries('Autre série'))['id']] + $body);

		$this->assertEquals(404, $foreignSeries['status']);
		$this->assertEquals('unknown_series', $foreignSeries['body'][0]['code'] ?? NULL);
		$this->assertEquals('unknown_cultivation', $foreignCultivation['body'][0]['code'] ?? NULL);

	}

	/** Comme à l'écran, un travail de culture sur une série d'une seule espèce porte sa mise en culture. */
	public function testCreateRequiresCultivationOnSingleSpeciesSeries(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eSeries = $this->createSeries('Série d\'une espèce');
		$eCultivation = $this->createCultivation($eSeries);

		// createSeries() pose déjà une espèce que CultivationLib::create() ajoute une seconde fois : le
		// compte se remet à ce que la série porte vraiment, une seule mise en culture.
		Series::model()->update($eSeries, ['plants' => 1]);

		$response = $this->callApi('POST', 'task', body: ['cultivation' => NULL] + $this->getCreateBody($eCultivation));

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Task::cultivation.check', $response['body'][0]['code'] ?? NULL);

	}

	/**
	 * Une récolte d'espèce seule, sans série, prend un calibre de son espèce (DB) : l'espèce se construit avant
	 * le calibre (`TaskLib::getPropertiesCreate()`). Le calibre d'une autre espèce reste refusé, et une espèce d'une
	 * autre structure se refuse seule, avec son calibre.
	 */
	public function testCreateHarvestPlantWithSize(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$ePlant = $this->createPlant();
		$eSize = $this->createSize($ePlant);
		$eSizeOther = $this->createSize($this->createPlant());

		$body = fn(\plant\Size $eSize) => [
			'series' => NULL,
			'cultivation' => NULL,
			'plant' => $ePlant['id'],
			'action' => $this->getAction(ACTION_RECOLTE)['id'],
			'description' => NULL,
			'harvestSize' => $eSize['id'],
		] + $this->getCreateBody($this->getCultivation());

		$response = $this->callApi('POST', 'task', body: $body($eSize));

		$this->assertEquals(201, $response['status'], 'la récolte d\'une espèce seule avec son calibre est refusée');
		$this->assertEquals([$ePlant['id'], $eSize['id']], [$response['body']['plant'] ?? NULL, $response['body']['harvestSize'] ?? NULL], 'la récolte ne porte pas son espèce et son calibre');

		$response = $this->callApi('POST', 'task', body: $body($eSizeOther));

		$this->assertEquals(400, $response['status'], 'le calibre d\'une autre espèce n\'est pas refusé');
		$this->assertEquals(['Task::harvestSize.action'], array_column($response['body'], 'code'), 'le refus du calibre d\'une autre espèce');

		// Une espèce d'une autre structure et son calibre : seule l'espèce se refuse, le calibre n'ajoute rien.
		$ePlantForeign = $this->createPlantForeign();

		$eSizeForeign = new \plant\Size([
			'name' => 'Calibre témoin '.uniqid(),
			'plant' => $ePlantForeign,
			'structure' => $this->getStructureForeign(),
			'comment' => NULL,
			'yield' => TRUE,
		]);

		// Sans build() : un calibre d'une autre structure, que le formulaire des calibres de cette structure ne
		// peut pas poser.
		\plant\SizeLib::create($eSizeForeign);

		$response = $this->callApi('POST', 'task', body: ['plant' => $ePlantForeign['id']] + $body($eSizeForeign));

		$this->assertEquals(400, $response['status'], 'l\'espèce d\'une autre structure et son calibre ne sont pas refusés');
		$this->assertEquals(['Task::plant.owned'], array_column($response['body'], 'code'), 'le refus d\'une espèce d\'une autre structure');
		$this->assertEquals(0, Task::model()->wherePlant($ePlantForeign)->count(), 'la récolte refusée a créé une intervention');

	}

	public function testNotFound(): void {

		$response = $this->callApi('GET', 'task/{id}', get: ['id' => 999999999]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

}
?>
