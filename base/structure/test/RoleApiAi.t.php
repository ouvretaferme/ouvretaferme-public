<?php
namespace structure;

/**
 * Test bout-en-bout de l'API publique structure\Role (base/structure/api/Role_0_1beta.a.php).
 *
 * Lancement : dev/test package=structure file=RoleApiAi
 */
class RoleApiAiTest extends \dev\ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new RoleModel();
	}

	/**
	 * La transaction est annulée à chaque test, mais pas le cache Redis : un test qui relit la liste
	 * après une écriture y laisserait sinon un rôle qui n'existe plus en base.
	 */
	public function tearDown(): void {

		parent::tearDown();

		RoleLib::deleteCache($this->eStructure);

	}

	/** Un nom libre, la clé (structure, name) étant unique. */
	protected function getFreeName(): string {

		$used = Role::model()
			->select(['name'])
			->whereStructure($this->eStructure)
			->getCollection()
			->getColumn('name');

		for($i = 1; $i <= 100; $i++) {

			$name = 'Rôle API '.$i;

			if(in_array($name, $used) === FALSE) {
				return $name;
			}

		}

		throw new \Exception('Aucun nom de rôle libre dans la structure de référence');

	}

	/** Un rôle modifiable par l'API : ni le rôle admin, ni un rôle privé. */
	protected function getWritableRole(): Role {

		$eRole = Role::model()
			->select(Role::getSelection())
			->whereStructure($this->eStructure)
			->whereAdmin(FALSE)
			->whereVisibility(Role::PUBLIC)
			->get();

		if($eRole->empty()) {
			throw new \Exception('La structure de référence n\'a aucun rôle modifiable');
		}

		return $eRole;

	}

	/** Un rôle modifiable et effectivement porté par un membre. */
	protected function getUsedRole(): Role {

		$roleIds = Member::model()
			->select(['role'])
			->whereStructure($this->eStructure)
			->getCollection()
			->getColumnCollection('role')
			->getIds();

		$eRole = Role::model()
			->select(Role::getSelection())
			->whereStructure($this->eStructure)
			->whereAdmin(FALSE)
			->whereVisibility(Role::PUBLIC)
			->whereId('IN', $roleIds)
			->get();

		if($eRole->empty()) {
			throw new \Exception('Aucun rôle modifiable n\'est attribué dans la structure de référence : le test ne vérifierait rien');
		}

		return $eRole;

	}

	protected function createRole(array $body = []): array {

		return $this->callApi('POST', 'role', body: $body + [
			'name' => $this->getFreeName(),
			'planning' => Role::DENIED,
			'selling' => FALSE,
			'productionObserve' => FALSE,
			'productionAnalyze' => FALSE,
			'commercialisationAnalyze' => FALSE,
			'accounting' => Role::DENIED,
			'accountingAnalyze' => FALSE
		]);

	}

	public function testList(): void {

		$response = $this->callApi('GET', 'roleList', get: ['start' => 1, 'limit' => 20]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue(array_key_exists('count', $response['body']));
		$this->assertTrue(array_key_exists('list', $response['body']));
		$this->assertTrue($response['body']['count'] > 0);

	}

	public function testCreateReadUpdateDelete(): void {

		$name = $this->getFreeName();

		$create = $this->createRole([
			'name' => $name,
			'planning' => Role::FULL,
			'selling' => TRUE,
			'productionObserve' => TRUE
		]);

		$this->assertEquals(201, $create['status']);
		$this->assertEquals($name, $create['body']['name'] ?? NULL);
		$this->assertEquals(Role::FULL, $create['body']['planning'] ?? NULL);
		$this->assertEquals(TRUE, $create['body']['selling'] ?? NULL);

		$id = $create['body']['id'];

		$read = $this->callApi('GET', 'role/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals($name, $read['body']['name'] ?? NULL);

		$update = $this->callApi('PUT', 'role/{id}', body: [
			'properties' => ['planning', 'accounting'],
			'planning' => Role::LIMITED,
			'accounting' => Role::FULL
		], get: ['id' => $id]);

		$this->assertEquals(200, $update['status']);
		$this->assertEquals(Role::LIMITED, $update['body']['planning'] ?? NULL);
		$this->assertEquals(Role::FULL, $update['body']['accounting'] ?? NULL);
		$this->assertEquals(TRUE, $update['body']['selling'] ?? NULL);

		$delete = $this->callApi('DELETE', 'role/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $delete['status']);

		$readAfterDelete = $this->callApi('GET', 'role/{id}', get: ['id' => $id]);

		$this->assertEquals(404, $readAfterDelete['status']);

	}

	/** Un rôle nouvellement créé prend les valeurs par défaut du schéma, pas celles du rôle admin. */
	public function testCreateExposesNothingElse(): void {

		$create = $this->createRole();

		$this->assertEquals(201, $create['status']);
		$this->assertEquals([
			'id', 'name', 'fqn',
			'visibility', 'admin',
			'planning', 'selling',
			'productionObserve', 'productionAnalyze',
			'commercialisationAnalyze', 'accounting', 'accountingAnalyze'
		], array_keys($create['body']));

		$this->assertEquals(NULL, $create['body']['fqn']);
		$this->assertEquals(Role::PUBLIC, $create['body']['visibility']);
		$this->assertEquals(FALSE, $create['body']['admin']);

	}

	/** UNIQUE (structure, name) : le doublon doit rendre une erreur métier, pas une 500. */
	public function testDuplicateNameIsRejected(): void {

		$name = $this->getFreeName();

		$this->assertEquals(201, $this->createRole(['name' => $name])['status']);

		$response = $this->createRole(['name' => $name]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Role::name.duplicate', $response['body'][0]['code'] ?? NULL);

	}

	/** Même contrainte au renommage : elle passe par update(), pas par create(). */
	public function testRenameToExistingNameIsRejected(): void {

		$eRole = $this->getWritableRole();

		$create = $this->createRole();

		$this->assertEquals(201, $create['status']);

		$response = $this->callApi('PUT', 'role/{id}', body: [
			'properties' => ['name'],
			'name' => $eRole['name']
		], get: ['id' => $create['body']['id']]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Role::name.duplicate', $response['body'][0]['code'] ?? NULL);

	}

	/** Le rôle admin porte les droits complets : l'API ne doit pas pouvoir les rogner. */
	public function testAdminRoleIsNotWritable(): void {

		$eRole = RoleLib::getOne($this->eStructure, 'admin');

		$this->assertTrue($eRole->notEmpty());

		$update = $this->callApi('PUT', 'role/{id}', body: [
			'properties' => ['planning'],
			'planning' => Role::DENIED
		], get: ['id' => $eRole['id']]);

		$this->assertEquals(412, $update['status']);
		$this->assertEquals('accept_update', $update['body'][0]['code'] ?? NULL);

		$delete = $this->callApi('DELETE', 'role/{id}', get: ['id' => $eRole['id']]);

		$this->assertEquals(412, $delete['status']);
		$this->assertEquals('accept_delete', $delete['body'][0]['code'] ?? NULL);

	}

	/**
	 * Le rôle fantôme est privé : l'écran des rôles ne le montre pas (RoleLib::getByStructure()),
	 * l'API ne le liste pas et ne le trouve pas par son id, en lecture comme en écriture.
	 */
	public function testPrivateRoleIsHidden(): void {

		$eRole = RoleLib::getOne($this->eStructure, 'ghost');

		if($this->assertDecorPresent($eRole->notEmpty(), 'la structure de référence porte le rôle fantôme') === FALSE) {
			return;
		}

		$list = $this->callApi('GET', 'roleList', get: ['start' => 1, 'limit' => 500]);

		$this->assertEquals(200, $list['status']);
		$this->assertEquals([Role::PUBLIC], array_values(array_unique(array_column($list['body']['list'], 'visibility'))));

		$listById = $this->callApi('GET', 'roleList', get: ['ids' => [$eRole['id']]]);

		$this->assertEquals(200, $listById['status']);
		$this->assertEquals(0, $listById['body']['count']);

		$read = $this->callApi('GET', 'role/{id}', get: ['id' => $eRole['id']]);

		$this->assertEquals(404, $read['status']);

		$update = $this->callApi('PUT', 'role/{id}', body: [
			'properties' => ['planning'],
			'planning' => Role::DENIED
		], get: ['id' => $eRole['id']]);

		$this->assertEquals(404, $update['status']);

		$delete = $this->callApi('DELETE', 'role/{id}', get: ['id' => $eRole['id']]);

		$this->assertEquals(404, $delete['status']);

	}

	/** Supprimer un rôle encore attribué laisserait des membres sans droits. */
	public function testDeleteUsedRoleIsRejected(): void {

		$eRole = $this->getUsedRole();

		$response = $this->callApi('DELETE', 'role/{id}', get: ['id' => $eRole['id']]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Role::deleteUsed', $response['body'][0]['code'] ?? NULL);

	}

	/**
	 * fqn, admin et visibility décident de ce qu'un rôle peut faire et de qui peut le modifier :
	 * ils sortent en lecture, mais ne sont pas modifiables.
	 */
	public function testReadOnlyPropertiesAreNotWritable(): void {

		$create = $this->createRole();

		$this->assertEquals(201, $create['status']);

		foreach(['fqn' => 'admin', 'admin' => TRUE, 'visibility' => Role::PRIVATE] as $property => $value) {

			$response = $this->callApi('PUT', 'role/{id}', body: [
				'properties' => [$property],
				$property => $value
			], get: ['id' => $create['body']['id']]);

			$this->assertEquals(400, $response['status']);
			$this->assertEquals('property_unknown', $response['body'][0]['code'] ?? NULL);

		}

	}

	/**
	 * RoleLib met en cache la liste par structure pendant 24 h : une écriture par l'API doit
	 * l'invalider, sinon la modification reste invisible côté web.
	 */
	public function testWriteInvalidatesCache(): void {

		RoleLib::getByStructure($this->eStructure);

		$create = $this->createRole();

		$this->assertEquals(201, $create['status']);

		$cRole = RoleLib::getByStructure($this->eStructure);

		$this->assertTrue($cRole->offsetExists($create['body']['id']));

	}

	public function testNotFound(): void {

		$response = $this->callApi('GET', 'role/{id}', get: ['id' => 999999999]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

}
?>
