<?php
namespace hr;

/**
 * Test bout-en-bout de l'API publique hr\Contract (base/hr/api/Contract_0_1beta.a.php), en lecture seule :
 * un contrat ne s'écrit qu'avec sa période.
 *
 * Lancement : dev/test package=hr file=ContractApiAi
 */
class ContractApiAiTest extends \dev\ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new ContractModel();
	}

	/**
	 * La RH allumée dans la transaction du test. Le rollback ne défait pas la mémoïsation de `conf()`, qui
	 * garde ce qu'elle a lu dans la transaction : elle s'oublie à l'entrée et à la sortie.
	 */
	public function setUp(): void {

		parent::setUp();

		\structure\FeatureLib::updateFeature($this->eStructure, 'hasHr', TRUE);
		\structure\FeatureLib::forget($this->eStructure);

	}

	public function tearDown(): void {

		parent::tearDown();

		\structure\FeatureLib::forget($this->eStructure);

	}

	protected function createContract(): Presence {

		$eUser = new \user\User([
			'firstName' => 'Contrat',
			'lastName' => 'Saisonnier',
			'email' => 'api-rh-contract-'.uniqid().'@example.org',
			'visibility' => \user\User::PRIVATE,
			'roleCustomer' => FALSE,
			'roleProducer' => TRUE,
			'roleConsultant' => FALSE,
			'roleTeacher' => FALSE
		]);

		// Hors build() : le compte, le coéquipier, la période et son contrat ne sont que le décor d'une lecture.
		// Leurs règles sont éprouvées par TeammateAi et PresenceApiAi.
		\user\UserLib::create($eUser);

		$ePresence = new Presence([
			'from' => '2002-04-01',
			'to' => '2002-09-30',
			'eContract' => new Contract([
				'type' => Contract::SEASONAL,
				'weeklyHours' => 35.0,
				'days' => new \Set(Contract::MONDAY | Contract::FRIDAY)
			])
		]);

		TeammateLib::create(new Teammate([
			'structure' => $this->eStructure,
			'user' => $eUser,
			'ePresence' => $ePresence
		]));

		return $ePresence;

	}

	public function testListAndRead(): void {

		$ePresence = $this->createContract();

		$list = $this->callApi('GET', 'contractList', get: ['presence' => $ePresence['id']]);

		$this->assertEquals(200, $list['status']);
		$this->assertEquals(1, count($list['body']['list']));

		$contract = $list['body']['list'][0];

		$this->assertEquals(['id', 'presence', 'type', 'weeklyHours', 'annualHours', 'hoursPeriod', 'days', 'hasVariableDays'], array_keys($contract));
		$this->assertFalse($contract['hasVariableDays']);
		$this->assertEquals($ePresence['id'], $contract['presence']);
		$this->assertEquals(Contract::SEASONAL, $contract['type']);

		$read = $this->callApi('GET', 'contract/{id}', get: ['id' => $contract['id']]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals($ePresence['eContract']['id'], $read['body']['id']);

	}

	/** Le contrat appartient à sa période : l'API ne le crée, ne le modifie ni ne le supprime. */
	public function testWritesAreNotExposed(): void {

		$ePresence = $this->createContract();
		$id = $ePresence['eContract']['id'];

		$this->assertEquals(404, $this->callApi('POST', 'contract', body: ['presence' => $ePresence['id'], 'type' => Contract::CDI])['status']);
		$this->assertEquals(405, $this->callApi('PUT', 'contract/{id}', body: ['properties' => ['type'], 'type' => Contract::CDI], get: ['id' => $id])['status']);
		$this->assertEquals(405, $this->callApi('DELETE', 'contract/{id}', get: ['id' => $id])['status']);

		$this->assertEquals(Contract::SEASONAL, ContractLib::getByPresence($ePresence)['type']);

	}

	/**
	 * RH éteinte, la liste et la lecture répondent le même refus, y compris sur un identifiant qui n'existe pas.
	 */
	public function testHrOffIsRefused(): void {

		$ePresence = $this->createContract();

		\structure\FeatureLib::updateFeature($this->eStructure, 'hasHr', FALSE);
		\structure\FeatureLib::forget($this->eStructure);

		$responses = [
			$this->callApi('GET', 'contractList', get: ['presence' => $ePresence['id']]),
			$this->callApi('GET', 'contract/{id}', get: ['id' => $ePresence['eContract']['id']]),
			$this->callApi('GET', 'contract/{id}', get: ['id' => 999999999])
		];

		foreach($responses as $response) {
			$this->assertEquals(412, $response['status']);
			$this->assertEquals('accept_hr', $response['body'][0]['code'] ?? NULL);
		}

	}

	public function testNotFound(): void {

		$this->assertEquals(404, $this->callApi('GET', 'contract/{id}', get: ['id' => 999999999])['status']);

	}

}
?>
