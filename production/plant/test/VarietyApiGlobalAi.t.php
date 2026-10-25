<?php
namespace plant;

/**
 * Pendant de PlantApiGlobalAi pour les variétés : le référentiel partagé (structure NULL) se lit
 * mais ne s'écrit pas, getById() servant aussi à update()/delete().
 *
 * La variété commune est celle du décor (`PlantFixtureAiTest`), posée sans structure dans la
 * transaction de la méthode : le cas prenait avant cela la première du référentiel par
 * identifiant. Les deux variétés que le produit désigne par une constante restent lues, et se
 * déclarent.
 *
 * Lancement : dev/test package=plant file=VarietyApiGlobalAi
 */
class VarietyApiGlobalAiTest extends PlantFixtureAiTest {

	public function testListGlobal(): void {

		$eVariety = $this->getVarietyGlobal();

		$response = $this->callApi('GET', 'plantVarietyListGlobal', get: ['start' => 1, 'limit' => 20, 'ids' => [$eVariety['id']]]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals([$eVariety['id']], array_column($response['body']['list'], 'id'), 'la variété commune du décor est dans la liste du référentiel');

		foreach($response['body']['list'] as $variety) {

			// La branche globale de la closure element() : pas de fournisseurs ni de reference.
			$this->assertFalse(array_key_exists('reference', $variety));
			$this->assertFalse(array_key_exists('supplierSeed', $variety));
			$this->assertTrue(array_key_exists('tags', $variety));

		}

	}

	/** Une variété de la structure ne sort pas par la liste du référentiel. */
	public function testListGlobalExcludesStructureVariety(): void {

		$eVariety = $this->getVariety();

		$response = $this->callApi('GET', 'plantVarietyListGlobal', get: ['ids' => [$eVariety['id']]]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(0, $response['body']['count']);

	}

	/**
	 * VARIETY_MIX et VARIETY_UNKNOWN sont écartées de plantVarietyListGlobal, elles ne doivent pas
	 * revenir par la liste des variétés de la structure.
	 */
	public function testListExcludesSpecialVarieties(): void {

		$response = $this->callApi('GET', 'plantVarietyListGlobal', get: ['ids' => [PlantSetting::VARIETY_MIX, PlantSetting::VARIETY_UNKNOWN]]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(0, $response['body']['count']);
		$this->assertEquals([], $response['body']['list']);

	}

	public function testReadGlobal(): void {

		$eVariety = $this->getVarietyGlobal();

		$response = $this->callApi('GET', 'plantVariety/{id}', get: ['id' => $eVariety['id']]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($eVariety['name'], $response['body']['name'] ?? NULL);

	}

	/**
	 * Incohérence connue : plantVarietyListGlobal écarte VARIETY_MIX / VARIETY_UNKNOWN, mais
	 * getNewModel() élargit à structure NULL sur tout GET, donc la lecture par id les rend quand même.
	 */
	public function testReadSpecialVarieties(): void {

		if($this->requireVarietyUnknown() === FALSE) {
			return;
		}

		$response = $this->callApi('GET', 'plantVariety/{id}', get: ['id' => PlantSetting::VARIETY_UNKNOWN]);

		$this->assertEquals(200, $response['status']);

	}

	public function testUpdateGlobalIsRejected(): void {

		$eVariety = $this->getVarietyGlobal();

		$response = $this->callApi('PUT', 'plantVariety/{id}', body: [
			'properties' => ['name'],
			'name' => 'Variété piratée'
		], get: ['id' => $eVariety['id']]);

		$this->assertDiffer(200, $response['status']);

		$eReloaded = Variety::model()->select(['name'])->whereId($eVariety['id'])->get();

		$this->assertEquals($eVariety['name'], $eReloaded['name']);

	}

	public function testDeleteGlobalIsRejected(): void {

		$eVariety = $this->getVarietyGlobal();

		$response = $this->callApi('DELETE', 'plantVariety/{id}', get: ['id' => $eVariety['id']]);

		$this->assertDiffer(200, $response['status']);
		$this->assertTrue(Variety::model()->whereId($eVariety['id'])->exists());

	}

}
?>
