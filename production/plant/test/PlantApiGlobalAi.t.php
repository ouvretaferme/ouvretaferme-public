<?php
namespace plant;

/**
 * Vérifie que le référentiel partagé (plantes à structure NULL) reste en lecture seule via l'API :
 * getNewModel() l'inclut pour permettre plant/{id}, mais getById() sert aussi à update()/delete().
 *
 * L'espèce commune est celle du décor (`PlantFixtureAiTest`), posée sans structure dans la
 * transaction de la méthode : le cas prenait avant cela la première espèce du référentiel par
 * identifiant, et une espèce sans variété quand il en trouvait une.
 *
 * Lancement : dev/test package=plant file=PlantApiGlobalAi
 */
class PlantApiGlobalAiTest extends PlantFixtureAiTest {

	/** L'espèce commune du décor, avec une variété commune : c'est sur les variétés que PlantLib::delete() s'arrête. */
	private function getPlantGlobalWithVariety(): Plant {

		return $this->once('plantGlobalWithVariety', function() {

			$ePlant = $this->createPlantGlobal();

			$this->createVarietyGlobal($ePlant);

			return $ePlant;

		});

	}

	public function testReadGlobal(): void {

		$ePlant = $this->getPlantGlobal();

		$response = $this->callApi('GET', 'plant/{id}', get: ['id' => $ePlant['id']]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($ePlant['name'], $response['body']['name'] ?? NULL);

	}

	public function testUpdateGlobalIsRejected(): void {

		$ePlant = $this->getPlantGlobal();

		$response = $this->callApi('PUT', 'plant/{id}', body: [
			'name' => 'Culture piratée',
			'color' => '#ff0000',
			'plantsSafetyMargin' => 99,
			'seedsSafetyMargin' => 99
		], get: ['id' => $ePlant['id']]);

		$this->assertDiffer(200, $response['status']);

		$eReloaded = Plant::model()->select(['name'])->whereId($ePlant['id'])->get();

		$this->assertEquals($ePlant['name'], $eReloaded['name']);

	}

	public function testDeleteGlobalIsRejected(): void {

		$ePlant = $this->getPlantGlobalWithVariety();

		$response = $this->callApi('DELETE', 'plant/{id}', get: ['id' => $ePlant['id']]);

		$this->assertDiffer(200, $response['status']);
		$this->assertTrue(Plant::model()->whereId($ePlant['id'])->exists());

	}

	/**
	 * PlantLib::delete() ne protège le référentiel qu'indirectement : ses garde-fous d'usage sont
	 * filtrés sur $e['structure'] (NULL ici, donc toujours vides), seul le test sur les variétés
	 * mord. Une plante globale sans variété serait donc supprimable.
	 */
	public function testDeleteGlobalWithoutVarietyIsRejected(): void {

		$ePlant = $this->getPlantGlobal();

		$this->assertFalse(Variety::model()->wherePlant($ePlant)->exists(), 'l\'espèce commune du décor n\'a pas de variété');

		$response = $this->callApi('DELETE', 'plant/{id}', get: ['id' => $ePlant['id']]);

		$this->assertDiffer(200, $response['status']);
		$this->assertTrue(Plant::model()->whereId($ePlant['id'])->exists());

	}

}
?>
