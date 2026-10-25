<?php
namespace dev;

/**
 * Un corps JSON valide n'est pas forcément un objet : `5`, `"x"` ou `false` se décodent sans erreur
 * en scalaire, que l'affectation à la propriété typée `ApiInternalData::$body` refuse par une
 * TypeError, soit un 500 sans JSON.
 *
 * Lancement : dev/test package=dev file=ApiBodyShape
 */
class ApiBodyShapeTest extends ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new \api\LogModel();
	}

	public function testScalarBodyIsRefused(): void {

		foreach(['5', '"x"', 'false', 'true', '1.5'] as $body) {

			$response = $this->callApi('POST', 'gauge', $body);

			$this->assertEquals(400, $response['status'], $body);
			$this->assertEquals('body_not_object', $response['body'][0]['code'] ?? NULL, $body);

		}

	}

	/** Contrôle du contrôle : un objet passe la garde et va jusqu'aux propriétés. */
	public function testObjectBodyGoesThrough(): void {

		$response = $this->callApi('POST', 'gauge', '{}');

		$this->assertEquals(400, $response['status']);
		$this->assertDiffer('body_not_object', $response['body'][0]['code'] ?? NULL);

	}

}
?>
