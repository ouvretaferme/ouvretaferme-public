<?php
namespace work;

/**
 * Le dépôt d'une vignette d'outil par l'API (PUT et DELETE tool/{id}/vignette).
 *
 * Le pipeline est celui vérifié en détail par product\ProductVignetteApiAiTest : ce test couvre ce
 * qui est propre à la ressource, c'est-à-dire son cadrage par la structure du jeton.
 *
 * Lancement : dev/test package=work file=ToolVignetteApiAi
 */
class ToolVignetteApiAiTest extends \dev\ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new ToolModel();
	}

	private function getImage(): string {

		$image = new \Imagick();
		$image->newImage(600, 600, new \ImagickPixel('#4a7c2f'));
		$image->setImageFormat('jpeg');

		$content = base64_encode($image->getImageBlob());

		$image->clear();

		return $content;

	}

	/** Un outil de la structure du jeton, posé par l'API dans la transaction de la méthode. */
	private function createTool(): Tool {

		$create = $this->callApi('POST', 'tool', body: [
			'name' => 'Test API Tool vignette',
			'action' => NULL,
			'stock' => NULL,
			'comment' => NULL,
			'routineName' => NULL,
			'routineValue' => NULL
		]);

		$this->assertEquals(201, $create['status'], 'l\'outil du cas se crée');

		return new Tool(['id' => $create['body']['id']]);

	}

	public function testPutAndDelete(): void {

		$eTool = $this->createTool();

		$put = $this->callApi('PUT', 'tool/{id}/vignette', get: ['id' => $eTool['id']], body: ['content' => $this->getImage()]);

		$this->assertEquals(200, $put['status']);
		$this->assertTrue(str_starts_with($put['body']['vignette'] ?? '', 'http'), 'la vignette sort en adresse');

		$delete = $this->callApi('DELETE', 'tool/{id}/vignette', get: ['id' => $eTool['id']]);

		$this->assertEquals(200, $delete['status']);
		$this->assertEquals(NULL, $delete['body']['vignette']);

	}

	/** Le format est établi en décodant le fichier, pas en croyant l'appelant. */
	public function testPutRejectsNonImage(): void {

		$eTool = $this->createTool();

		$response = $this->callApi('PUT', 'tool/{id}/vignette', get: ['id' => $eTool['id']], body: [
			'content' => base64_encode("%PDF-1.4\n1 0 obj\nendobj\n"),
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('media_invalid_format', $response['body'][0]['code'] ?? NULL);

	}

	/** Le cadrage vient de getNewModel() : l'outil d'une autre structure est introuvable. */
	public function testOtherStructureIsNotFound(): void {

		$eToolOther = Tool::model()
			->select(['id'])
			->whereStructure('!=', $this->eStructure)
			->get();

		$this->assertTrue($eToolOther->notEmpty(), 'la base de dev porte un outil d\'une autre structure');

		$response = $this->callApi('PUT', 'tool/{id}/vignette', get: ['id' => $eToolOther['id']], body: [
			'content' => $this->getImage(),
		]);

		$this->assertEquals(404, $response['status']);

	}

}
?>
