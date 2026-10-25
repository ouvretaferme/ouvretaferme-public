<?php
namespace series;

/**
 * Test bout-en-bout de la ressource series\Harvest (production/series/api/Harvest_0_1beta.a.php).
 *
 * La récolte lue est celle du décor (`SeriesFixtureAiTest`) : une quantité saisie par le chemin de
 * l'écran sur l'intervention de récolte du décor, dans la transaction de la méthode. Les cas
 * lisaient la récolte la plus récente de la structure, et la période de chaque appel se déduisait
 * de la ligne trouvée ; ici la période est celle du jour de la saisie.
 *
 * Lancement : dev/test package=series file=HarvestApiAi
 */
class HarvestApiAiTest extends SeriesFixtureAiTest {

	/** La quantité saisie sur l'intervention du décor. */
	const QUANTITY = 12.5;

	private function requireDecor(): bool {

		return ($this->requireMember() and $this->requireAction(ACTION_RECOLTE));

	}

	/** La récolte du décor, telle que le journal la porte : identifiant, unité et semaine y sont posés par l'écriture. */
	private function getHarvest(): Harvest {

		return $this->once('harvest', function() {

			$eHarvest = $this->createHarvest($this->getTaskHarvest(), self::QUANTITY);

			return Harvest::model()
				->select(['id', 'task', 'series', 'cultivation', 'quantity', 'unit', 'date', 'week'])
				->whereId($eHarvest)
				->get();

		});

	}

	public function testList(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eHarvest = $this->getHarvest();

		$response = $this->callApi('GET', 'harvestList', get: [
			'dateFrom' => $eHarvest['date'],
			'dateTo' => $eHarvest['date'],
			'limit' => 500
		]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue(array_key_exists('count', $response['body']));
		$this->assertTrue($response['body']['count'] > 0, 'la journée de la récolte du décor en rend au moins une');
		$this->assertTrue(in_array($eHarvest['id'], array_column($response['body']['list'], 'id')), 'la récolte du décor est dans la liste de son jour');

	}

	/** La période cadre la requête : sans elle, la liste balaierait la table. */
	public function testListRequiresPeriod(): void {

		$missingTo = $this->callApi('GET', 'harvestList', get: ['dateFrom' => \Clock::date()]);
		$missingFrom = $this->callApi('GET', 'harvestList', get: ['dateTo' => \Clock::date()]);

		foreach([$missingTo, $missingFrom] as $response) {
			$this->assertEquals(400, $response['status']);
		}

	}

	public function testListByTask(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eHarvest = $this->getHarvest();

		// Une récolte du même jour sur une autre intervention du décor ne doit pas passer le filtre
		$eHarvestOther = $this->createHarvest($this->createTaskHarvest($this->createCultivation($this->getSeries(), $this->createPlant())), 1.0);

		$response = $this->callApi('GET', 'harvestList', get: [
			'dateFrom' => $eHarvest['date'],
			'dateTo' => $eHarvest['date'],
			'task' => $eHarvest['task']['id']
		]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(1, $response['body']['count'], 'l\'intervention du décor n\'a qu\'une récolte');
		$this->assertEquals([$eHarvest['id']], array_column($response['body']['list'], 'id'), 'et c\'est celle du décor, pas celle de l\'autre intervention');
		$this->assertFalse(in_array($eHarvestOther['id'], array_column($response['body']['list'], 'id')));

		foreach($response['body']['list'] as $harvest) {
			$this->assertEquals($eHarvest['task']['id'], $harvest['task']);
		}

	}

	/** Une récolte d'une autre structure ne se lit pas, même par son identifiant. */
	public function testListIsScopedToStructure(): void {

		if($this->requireDecor() === FALSE or $this->requireStructureForeign() === FALSE) {
			return;
		}

		$eHarvestForeign = $this->createHarvestForeign();

		$response = $this->callApi('GET', 'harvest/{id}', get: ['id' => $eHarvestForeign['id']]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

	public function testRead(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eHarvest = $this->getHarvest();

		$response = $this->callApi('GET', 'harvest/{id}', get: ['id' => $eHarvest['id']]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($eHarvest['id'], $response['body']['id'] ?? NULL);
		$this->assertEquals(self::QUANTITY, $response['body']['quantity'] ?? NULL);
		$this->assertEquals($eHarvest['task']['id'], $response['body']['task'] ?? NULL);
		$this->assertEquals(toWeek(\Clock::date()), $response['body']['week'] ?? NULL);
		$this->assertEquals(Harvest::KG, $response['body']['unit'] ?? NULL, 'l\'unité sort sous sa valeur d\'enum');
		$this->assertFalse(array_key_exists('createdAt', $response['body']));
		$this->assertFalse(array_key_exists('createdBy', $response['body']));

	}

	/** Une récolte naît de sa tâche, jamais d'un appel direct : rien ne s'écrit ici. */
	public function testWriteRoutesAreNotExposed(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eHarvest = $this->getHarvest();

		$responses = [
			['unknown_route', $this->callApi('POST', 'harvest', body: [])],
			['method_not_allowed', $this->callApi('PUT', 'harvest/{id}', body: [], get: ['id' => $eHarvest['id']])],
			['method_not_allowed', $this->callApi('DELETE', 'harvest/{id}', get: ['id' => $eHarvest['id']])]
		];

		foreach($responses as [$code, $response]) {
			$this->assertEquals($code, $response['body'][0]['code'] ?? NULL);
		}

		$this->assertEquals(self::QUANTITY, Harvest::model()->whereId($eHarvest)->getValue('quantity'), 'la ligne est intacte');

	}

	public function testNotFound(): void {

		$response = $this->callApi('GET', 'harvest/{id}', get: ['id' => 999999999]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

}
?>
