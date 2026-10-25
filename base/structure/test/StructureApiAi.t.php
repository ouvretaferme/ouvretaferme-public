<?php
namespace structure;

/**
 * Test bout-en-bout de la route /me (base/structure/api/Structure_0_1beta.a.php), qui rend la
 * structure rattachée au jeton.
 *
 * Lancement : dev/test package=structure file=StructureApiAi
 */
class StructureApiAiTest extends \dev\ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new StructureModel();
	}

	public function testMe(): void {

		$response = $this->callApi('GET', 'me');

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($this->eStructure['id'], $response['body']['id'] ?? NULL);
		$this->assertEquals($this->eStructure['name'], $response['body']['name'] ?? NULL);
		$this->assertEquals($this->eStructure['type'], $response['body']['type'] ?? NULL);

	}

	/**
	 * /me est la carte d'identité du jeton, pas un dump de la table : la plomberie interne
	 * (version, interstitiel, consentement) ne doit pas fuiter.
	 */
	public function testMeExposesNothingElse(): void {

		$response = $this->callApi('GET', 'me');

		$this->assertEquals([
			'id', 'name', 'type', 'communityType',
			'isLegal', 'legalName', 'legalEmail', 'legalCategory', 'siret',
			'legalStreet1', 'legalStreet2', 'legalPostcode', 'legalCity', 'legalCountry',
			'url', 'description', 'logo',
			'organic', 'qualities', 'startedAt',
			'status'
		], array_keys($response['body']));

	}

	/** Le logo sort en URL utilisable, pas en hash brut de média. */
	public function testLogoIsAnUrl(): void {

		$eStructure = StructureLib::getById($this->eStructure['id']);

		// Les deux écritures passent hors build() : le logo s'écrit par le téléversement
		// (\media\StructureLogoLib), jamais par un formulaire. Le hash a la forme que pose MediaLib,
		// 19 caractères, la lettre du format, puis la couleur et la version qui complètent les 30
		// caractères de la colonne.
		$eStructure['logo'] = NULL;
		StructureLib::update($eStructure, ['logo']);

		$this->assertEquals(NULL, $this->callApi('GET', 'me')['body']['logo'], 'sans logo, la route rend NULL');

		$eStructure['logo'] = \media\UtilLib::generateHash().'p'.'#000000'.'001';
		StructureLib::update($eStructure, ['logo']);

		$logo = $this->callApi('GET', 'me')['body']['logo'];

		$this->assertTrue(str_starts_with($logo, 'http'));
		$this->assertTrue(str_contains($logo, 'structure-logo/'));

	}

	/** Pas d'id dans la route : /me ne peut désigner qu'une seule structure. */
	public function testMeIsNotAddressableById(): void {

		$response = $this->callApi('GET', 'me/{id}', get: ['id' => $this->eStructure['id']]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('unknown_route', $response['body'][0]['code'] ?? NULL);

	}

	public function testWriteRoutesAreNotExposed(): void {

		foreach([['POST', 'me'], ['PUT', 'me'], ['DELETE', 'me']] as [$method, $route]) {

			$response = $this->callApi($method, $route);

			$this->assertEquals(405, $response['status']);
			$this->assertEquals('method_not_allowed', $response['body'][0]['code'] ?? NULL);

		}

	}

}
?>
