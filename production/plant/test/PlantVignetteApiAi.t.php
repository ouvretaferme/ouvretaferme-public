<?php
namespace plant;

/**
 * Le dépôt d'une vignette de culture par l'API (PUT et DELETE plant/{id}/vignette).
 *
 * Le pipeline est celui vérifié en détail par product\ProductVignetteApiAiTest : ce test couvre ce
 * qui est propre à la ressource, c'est-à-dire son cadrage. Une culture du référentiel commun
 * (structure NULL) se lit mais ne se modifie pas, PlantModel n'ouvrant la structure NULL qu'en GET.
 *
 * L'espèce de la structure et l'espèce commune sont celles du décor (`PlantFixtureAiTest`), posées
 * dans la transaction de la méthode : le cas prenait avant cela la dernière espèce de la structure
 * et la première du référentiel.
 *
 * Lancement : dev/test package=plant file=PlantVignetteApiAi
 */
class PlantVignetteApiAiTest extends PlantFixtureAiTest {

	public function testPutAndDelete(): void {

		$ePlant = $this->getPlant();

		$put = $this->callApi('PUT', 'plant/{id}/vignette', get: ['id' => $ePlant['id']], body: ['content' => $this->getImage()]);

		$this->assertEquals(200, $put['status']);
		$this->assertTrue(str_starts_with($put['body']['vignette'] ?? '', 'http'), 'la vignette sort en adresse');

		$delete = $this->callApi('DELETE', 'plant/{id}/vignette', get: ['id' => $ePlant['id']]);

		$this->assertEquals(200, $delete['status']);
		$this->assertEquals(NULL, $delete['body']['vignette']);

		$read = $this->callApi('GET', 'plant/{id}', get: ['id' => $ePlant['id']]);

		$this->assertEquals(NULL, $read['body']['vignette'], 'la culture relue n\'a plus de vignette');

	}

	/** Le format est établi en décodant le fichier, pas en croyant l'appelant. */
	public function testPutRejectsNonImage(): void {

		$ePlant = $this->getPlant();

		$response = $this->callApi('PUT', 'plant/{id}/vignette', get: ['id' => $ePlant['id']], body: [
			'content' => base64_encode("%PDF-1.4\n1 0 obj\nendobj\n"),
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('media_invalid_format', $response['body'][0]['code'] ?? NULL);

	}

	/**
	 * Une culture du référentiel commun se lit par l'API mais n'appartient à personne : lui poser une
	 * vignette la changerait pour toutes les structures.
	 */
	public function testCommonPlantIsNotFound(): void {

		$ePlantCommon = $this->getPlantGlobal();

		// Contrôle du contrôle : la même culture se lit bien par la route de lecture
		$read = $this->callApi('GET', 'plant/{id}', get: ['id' => $ePlantCommon['id']]);

		$this->assertEquals(200, $read['status'], 'la culture commune est lisible');

		$put = $this->callApi('PUT', 'plant/{id}/vignette', get: ['id' => $ePlantCommon['id']], body: [
			'content' => $this->getImage(),
		]);

		$this->assertEquals(404, $put['status']);
		$this->assertEquals(NULL, Plant::model()->whereId($ePlantCommon)->getValue('vignette'), 'la culture commune n\'a pas reçu de vignette');

	}

}
?>
