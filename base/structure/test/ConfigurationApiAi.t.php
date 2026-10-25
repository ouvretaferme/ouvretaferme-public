<?php
namespace structure;

/**
 * Test bout-en-bout de l'API publique structure\Configuration
 * (base/structure/api/Configuration_0_1beta.a.php).
 *
 * Lancement : dev/test package=structure file=ConfigurationApiAi
 */
class ConfigurationApiAiTest extends \dev\ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new ConfigurationModel();
	}

	/** Exposés quel que soit le type de structure. */
	protected const COMMON = [
		'id',
		'hasVat', 'vatNumber', 'vatChargeability', 'vatDebitsOption',
		'stockNotes', 'stockNotesUpdatedAt', 'stockNotesUpdatedBy'
	];

	/** Réservés à une structure qui produit. */
	protected const PRODUCER_ONLY = [
		'seasonFirst', 'seasonLast',
		'defaultBedLength', 'defaultBedWidth', 'defaultAlleyWidth',
		'organicCertifier',
	];

	protected function getConfiguration(): Configuration {
		return ConfigurationLib::readByStructure($this->eStructure);
	}

	/**
	 * Bascule le type de la structure de référence, la transaction du test étant annulée derrière.
	 * \ApiData::authorized() relit la structure à chaque appel, le changement est donc pris en compte.
	 */
	protected function setStructureType(string $type): void {

		Structure::model()
			->whereId($this->eStructure)
			->update(['type' => $type]);

	}

	public function testRead(): void {

		$this->assertEquals(Structure::PRODUCER, $this->eStructure['type']);

		$response = $this->callApi('GET', 'configuration');

		$eConfiguration = $this->getConfiguration();

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($eConfiguration['id'], $response['body']['id']);
		$this->assertEquals($eConfiguration['hasVat'], $response['body']['hasVat']);
		$this->assertEquals(\map\ConfigurationLib::readByStructure($this->eStructure)['seasonLast'], $response['body']['seasonLast'], 'la saison se lit dans map\Configuration');
		$this->assertEquals(\product\ConfigurationLib::readByStructure($this->eStructure)['stockNotes'], $response['body']['stockNotes'], 'les notes se lisent dans product\Configuration');

	}

	/**
	 * Rien d'autre ne sort que les colonnes déclarées. Les réglages des ventes (taux de TVA par
	 * défaut, adresse électronique, caisse) ont leur ressource, `sellingConfiguration`.
	 */
	public function testExposesNothingElse(): void {

		$response = $this->callApi('GET', 'configuration');

		$this->assertEquals(
			array_merge(self::COMMON, self::PRODUCER_ONLY),
			array_keys($response['body'])
		);

	}

	/** Une boutique ou un groupe n'a ni plan de culture ni certification bio. */
	public function testNonProducerHidesCropAndMarket(): void {

		foreach([Structure::SHOP, Structure::COMMUNITY] as $type) {

			$this->setStructureType($type);

			$response = $this->callApi('GET', 'configuration');

			$this->assertEquals(200, $response['status']);
			$this->assertEquals(self::COMMON, array_keys($response['body']));

		}

	}

	/**
	 * Une AMAP n'a pas d'API (décision 7B du lot E2) : `ApiData::authorized()` la refuse avant toute
	 * route, jeton valide ou non, et la même structure redevenue productrice répond de nouveau.
	 */
	public function testAmapIsRefused(): void {

		$this->setStructureType(Structure::AMAP);

		$response = $this->callApi('GET', 'configuration');

		$this->assertEquals(403, $response['status']);
		$this->assertEquals('structure_not_allowed', $response['body'][0]['code'] ?? NULL);

		$this->setStructureType(Structure::PRODUCER);

		$this->assertEquals(200, $this->callApi('GET', 'configuration')['status']);

	}

	/** Le régime de TVA reste lisible sur une structure qui ne produit pas. */
	public function testVatIsExposedToNonProducer(): void {

		$this->setStructureType(Structure::SHOP);

		$response = $this->callApi('GET', 'configuration');

		$eConfiguration = $this->getConfiguration();

		$this->assertEquals($eConfiguration['hasVat'], $response['body']['hasVat']);
		$this->assertEquals($eConfiguration['vatChargeability'], $response['body']['vatChargeability']);

	}

	public function testWriteRoutesAreNotExposed(): void {

		foreach([['POST', 'configuration'], ['PUT', 'configuration'], ['DELETE', 'configuration']] as [$method, $route]) {

			$response = $this->callApi($method, $route);

			$this->assertEquals(405, $response['status']);
			$this->assertEquals('method_not_allowed', $response['body'][0]['code'] ?? NULL);

		}

	}

	/** Pas d'id dans la route : la configuration d'une autre structure n'est pas adressable. */
	public function testIsNotAddressableById(): void {

		$response = $this->callApi('GET', 'configuration/{id}', get: ['id' => $this->getConfiguration()['id']]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('unknown_route', $response['body'][0]['code'] ?? NULL);

	}

}
?>
