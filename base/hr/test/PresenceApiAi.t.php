<?php
namespace hr;

/**
 * Test bout-en-bout de l'API publique hr\Presence (base/hr/api/Presence_0_1beta.a.php).
 *
 * Lancement : dev/test package=hr file=PresenceApiAi
 */
class PresenceApiAiTest extends \dev\ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new PresenceModel();
	}

	/** L'adresse est en `example.org`, domaine réservé où aucun envoi n'est délivré. */
	protected function createUser(string $name): \user\User {

		$eUser = new \user\User([
			'firstName' => 'Présence',
			'lastName' => $name,
			'email' => 'api-sl6-presence-'.uniqid().'@example.org',
			'visibility' => \user\User::PRIVATE,
			'roleCustomer' => FALSE,
			'roleProducer' => TRUE,
			'roleConsultant' => FALSE,
			'roleTeacher' => FALSE
		]);

		// Hors build() : le compte n'est que le porteur d'une présence. build() aurait vérifié le format
		// et l'unicité de l'adresse, qu'assurent ici le domaine réservé et uniqid().
		\user\UserLib::create($eUser);

		return $eUser;

	}

	/**
	 * Un membre neuf de l'équipe, sans présence : l'administrateur du décor en a une ouverte, sur
	 * laquelle aucune période ne serait libre. Il a un accès et il est dans l'équipe, les deux n'allant
	 * plus ensemble.
	 */
	protected function getUserMember(): \user\User {

		$eUser = $this->createUser('Membre');

		$eRole = \structure\RoleLib::getByStructure($this->eStructure, withAdmin: FALSE)->first();

		$this->assertTrue($eRole !== NULL, 'la structure porte un rôle non administrateur');

		// Hors build(), comme le pose InviteLib::accept() : un membre entre par une invitation, aucun
		// formulaire ne le construit.
		\structure\MemberLib::create(new \structure\Member([
			'user' => $eUser,
			'structure' => $this->eStructure,
			'role' => $eRole,
			'status' => \structure\Member::IN
		]));

		// Hors build() : l'étape `member` de `Teammate::user` est éprouvée par TeammateAi, ce test ne veut que
		// quelqu'un de l'équipe.
		TeammateLib::create(new Teammate([
			'structure' => $this->eStructure,
			'user' => $eUser
		]));

		return $eUser;

	}

	/** Un utilisateur qui existe mais n'appartient pas à l'équipe. */
	protected function getUserOutsider(): \user\User {

		$eUser = $this->createUser('Hors équipe');

		$this->assertFalse(\structure\MemberLib::isMember($eUser, $this->eStructure, NULL), 'le compte n\'est pas de l\'équipe');

		return $eUser;

	}

	/** Une période libre : to.present refuse tout chevauchement avec une présence existante. */
	protected function getFreeDates(\user\User $eUser): array {

		$from = Presence::model()
			->select(['from' => new \Sql('MAX(m1.from)')])
			->whereStructure($this->eStructure)
			->whereUser($eUser)
			->get()['from'];

		$start = $from === NULL ? '2000-01-01' : date('Y-m-d', strtotime($from.' +1 year'));

		return [$start, date('Y-m-d', strtotime($start.' +30 day'))];

	}

	protected function createPresence(array $body = []): array {

		$eUser = $this->getUserMember();
		[$from, $to] = $this->getFreeDates($eUser);

		return $this->callApi('POST', 'presence', body: $body + [
			'user' => $eUser['id'],
			'from' => $from,
			'to' => $to
		]);

	}

	public function testList(): void {

		$create = $this->createPresence();

		$this->assertEquals(201, $create['status']);

		$response = $this->callApi('GET', 'presenceList', get: ['start' => 1, 'limit' => 100]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue($response['body']['count'] > 0);
		$this->assertTrue(in_array($create['body']['id'], array_column($response['body']['list'], 'id')), 'la liste rend la présence créée');

	}

	public function testListFilteredByUser(): void {

		// On crée d'abord la présence : le membre est neuf, et un filtre qui ne rend rien ne prouverait
		// pas qu'il filtre.
		$create = $this->createPresence();

		$this->assertEquals(201, $create['status']);

		$response = $this->callApi('GET', 'presenceList', get: ['user' => $create['body']['user'], 'limit' => 500]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(TRUE, in_array($create['body']['id'], array_column($response['body']['list'], 'id')));
		$this->assertEquals([$create['body']['user']], array_unique(array_column($response['body']['list'], 'user')));

	}

	public function testCreateReadUpdateDelete(): void {

		$create = $this->createPresence();

		$this->assertEquals(201, $create['status']);

		$id = $create['body']['id'];

		$read = $this->callApi('GET', 'presence/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals($create['body']['from'], $read['body']['from']);

		$update = $this->callApi('PUT', 'presence/{id}', body: [
			'properties' => ['to'],
			'to' => NULL
		], get: ['id' => $id]);

		$this->assertEquals(200, $update['status']);
		$this->assertEquals(NULL, $update['body']['to']);

		$delete = $this->callApi('DELETE', 'presence/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $delete['status']);
		$this->assertEquals(404, $this->callApi('GET', 'presence/{id}', get: ['id' => $id])['status']);

	}

	public function testCreateExposesNothingElse(): void {

		$create = $this->createPresence();

		$this->assertEquals(201, $create['status']);
		$this->assertEquals(['id', 'user', 'from', 'to'], array_keys($create['body']));

	}

	/** Une présence n'a de sens que pour quelqu'un de l'équipe. */
	public function testOutsiderIsRejected(): void {

		$response = $this->createPresence(['user' => $this->getUserOutsider()['id']]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Presence::user.teammate', $response['body'][0]['code'] ?? NULL);

	}

	/** Un accès ne fait pas entrer dans l'équipe : la présence de quelqu'un qui n'y est pas est refusée. */
	public function testMemberOutsideTeamIsRejected(): void {

		$eUser = $this->createUser('Accès seul');

		// Hors build(), comme le pose InviteLib::accept().
		\structure\MemberLib::create(new \structure\Member([
			'user' => $eUser,
			'structure' => $this->eStructure,
			'role' => \structure\RoleLib::getByStructure($this->eStructure, withAdmin: FALSE)->first(),
			'status' => \structure\Member::IN
		]));

		$response = $this->createPresence(['user' => $eUser['id']]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Presence::user.teammate', $response['body'][0]['code'] ?? NULL);

	}

	/** Deux périodes qui se chevauchent rendraient la présence ambiguë. */
	public function testOverlapIsRejected(): void {

		$create = $this->createPresence();

		$this->assertEquals(201, $create['status']);

		$response = $this->callApi('POST', 'presence', body: [
			'user' => $create['body']['user'],
			'from' => $create['body']['from'],
			'to' => $create['body']['to']
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Presence::to.present', $response['body'][0]['code'] ?? NULL);

	}

	public function testEndBeforeStartIsRejected(): void {

		$eUser = $this->getUserMember();
		[$from] = $this->getFreeDates($eUser);

		$response = $this->callApi('POST', 'presence', body: [
			'user' => $eUser['id'],
			'from' => $from,
			'to' => date('Y-m-d', strtotime($from.' -1 day'))
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Presence::to.consistency', $response['body'][0]['code'] ?? NULL);

	}

	/** Le jour de départ seul se contrôle contre l'arrivée enregistrée. */
	public function testUpdateEndOverlapIsRejected(): void {

		$create = $this->createPresence();
		$createNext = $this->createPresence([
			'user' => $create['body']['user'],
			'from' => date('Y-m-d', strtotime($create['body']['to'].' +30 day')),
			'to' => NULL
		]);

		$this->assertEquals(201, $create['status']);
		$this->assertEquals(201, $createNext['status']);

		$response = $this->callApi('PUT', 'presence/{id}', body: [
			'properties' => ['to'],
			'to' => date('Y-m-d', strtotime($createNext['body']['from'].' +1 day'))
		], get: ['id' => $create['body']['id']]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Presence::to.present', $response['body'][0]['code'] ?? NULL);

	}

	public function testUpdateEndBeforeStartIsRejected(): void {

		$create = $this->createPresence();

		$this->assertEquals(201, $create['status']);

		$response = $this->callApi('PUT', 'presence/{id}', body: [
			'properties' => ['to'],
			'to' => date('Y-m-d', strtotime($create['body']['from'].' -1 day'))
		], get: ['id' => $create['body']['id']]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Presence::to.consistency', $response['body'][0]['code'] ?? NULL);

	}

	public function testNotFound(): void {

		$response = $this->callApi('GET', 'presence/{id}', get: ['id' => 999999999]);

		$this->assertEquals(404, $response['status']);

	}

}
?>
