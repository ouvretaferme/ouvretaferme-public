<?php
namespace plant;

/**
 * La route DELETE plant/{id} refuse ce que l'écran ne propose pas : la copie du référentiel que
 * porte la structure du jeton (`fqn` posé). L'écran n'offre « Supprimer l'espèce » que sous
 * `Plant::assertDelete()`, et `ModuleApi::delete()` n'interroge que `Plant::acceptDelete()`.
 *
 * La copie est neuve et inutilisée : `PlantLib::delete()` refuse déjà une espèce utilisée
 * (`Plant::deleteUsed`), et sur une copie utilisée la ligne resterait même sans la garde.
 *
 * Lancement : dev/test package=plant file=PlantDeleteApiAi
 */
class PlantDeleteApiAiTest extends PlantFixtureAiTest {

	public function testDeleteCopyIsRejected(): void {

		// Sans build() : `fqn` n'est dans aucune liste d'écriture, aucun formulaire ne le poste. Le test
		// pose ce que la copie du référentiel porte à la création de la structure.
		$ePlant = new Plant([
			'name' => 'Copie du décor '.uniqid(),
			'fqn' => 'copie-decor-'.uniqid(),
			'structure' => $this->eStructure,
			'cycle' => Plant::ANNUAL,
		]);

		PlantLib::create($ePlant);

		$response = $this->callApi('DELETE', 'plant/{id}', get: ['id' => $ePlant['id']]);

		$this->assertEquals(412, $response['status']);
		$this->assertEquals('accept_delete', $response['body'][0]['code'] ?? NULL);
		$this->assertTrue(Plant::model()->whereId($ePlant['id'])->exists(), 'la copie du référentiel est toujours là');

	}

	public function testDeleteOwnIsAccepted(): void {

		$ePlant = $this->createPlant();

		$response = $this->callApi('DELETE', 'plant/{id}', get: ['id' => $ePlant['id']]);

		$this->assertEquals(200, $response['status']);
		$this->assertFalse(Plant::model()->whereId($ePlant['id'])->exists(), 'l\'espèce propre est supprimée');

	}

}
?>
