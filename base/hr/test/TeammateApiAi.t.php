<?php
namespace hr;

/**
 * Test bout-en-bout de l'API publique hr\Teammate (base/hr/api/Teammate_0_1beta.a.php).
 *
 * Lancement : dev/test package=hr file=TeammateApiAi
 */
class TeammateApiAiTest extends \dev\ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new TeammateModel();
	}

	/** L'adresse est en `example.org`, domaine réservé où aucun envoi n'est délivré. */
	protected function createUser(string $name): \user\User {

		$eUser = new \user\User([
			'firstName' => 'Équipe',
			'lastName' => $name,
			'email' => 'api-rh-teammate-'.uniqid().'@example.org',
			'visibility' => \user\User::PRIVATE,
			'roleCustomer' => FALSE,
			'roleProducer' => TRUE,
			'roleConsultant' => FALSE,
			'roleTeacher' => FALSE
		]);

		// Hors build() : le compte n'est que le porteur d'un coéquipier. build() aurait vérifié le format et
		// l'unicité de l'adresse, qu'assurent ici le domaine réservé et uniqid().
		\user\UserLib::create($eUser);

		return $eUser;

	}

	/** Quelqu'un qui a un accès à la structure et n'est pas dans son équipe. */
	protected function getUserMember(string $status = \structure\Member::IN): \user\User {

		$eUser = $this->createUser('Accès');

		// Hors build(), comme le pose InviteLib::accept() : un membre entre par une invitation, aucun
		// formulaire ne le construit.
		\structure\MemberLib::create(new \structure\Member([
			'user' => $eUser,
			'structure' => $this->eStructure,
			'role' => \structure\RoleLib::getByStructure($this->eStructure, withAdmin: FALSE)->first(),
			'status' => $status
		]));

		return $eUser;

	}

	public function testCreateReadList(): void {

		$eUser = $this->getUserMember();

		$create = $this->callApi('POST', 'teammate', body: ['user' => $eUser['id']]);

		$this->assertEquals(201, $create['status']);
		$this->assertEquals(['id', 'user'], array_keys($create['body']));
		$this->assertEquals($eUser['id'], $create['body']['user']);

		$id = $create['body']['id'];

		$read = $this->callApi('GET', 'teammate/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $read['status']);

		$list = $this->callApi('GET', 'teammateList', get: ['user' => $eUser['id']]);

		$this->assertEquals(200, $list['status']);
		$this->assertEquals([$id], array_column($list['body']['list'], 'id'));

	}

	/**
	 * L'écran sort une personne du planning (hr/teammate:doLeave) et garde ses périodes commencées, celles
	 * qui courent encore finissant la veille. Il ne la retire de l'équipe que si aucune de ses périodes n'a
	 * commencé (TeammateLib::leave()). La suppression, qui emportait toutes ses présences et leurs contrats,
	 * ne se fait donc pas par l'API. La lecture restant déclarée sous la même adresse, le cadriciel répond
	 * 405 et non 404.
	 */
	public function testDeleteIsNotExposed(): void {

		$create = $this->callApi('POST', 'teammate', body: ['user' => $this->getUserMember()['id']]);

		$this->assertEquals(201, $create['status']);

		$id = $create['body']['id'];

		$response = $this->callApi('DELETE', 'teammate/{id}', get: ['id' => $id]);

		$this->assertEquals(405, $response['status']);
		$this->assertEquals('method_not_allowed', $response['body'][0]['code'] ?? NULL);

		$this->assertTrue(TeammateLib::getById($id)->notEmpty());

	}

	/** Un ancien membre peut revenir travailler sans avoir retrouvé d'accès. */
	public function testFormerMemberIsAccepted(): void {

		$eUser = $this->getUserMember(\structure\Member::OUT);

		$create = $this->callApi('POST', 'teammate', body: ['user' => $eUser['id']]);

		$this->assertEquals(201, $create['status']);

	}

	public function testOutsiderIsRejected(): void {

		$response = $this->callApi('POST', 'teammate', body: ['user' => $this->createUser('Hors structure')['id']]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Teammate::user.member', $response['body'][0]['code'] ?? NULL);

	}

	public function testDuplicateIsRejected(): void {

		$eUser = $this->getUserMember();

		$this->assertEquals(201, $this->callApi('POST', 'teammate', body: ['user' => $eUser['id']])['status']);

		$response = $this->callApi('POST', 'teammate', body: ['user' => $eUser['id']]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Teammate::user.duplicate', $response['body'][0]['code'] ?? NULL);

	}

	public function testNotFound(): void {

		$this->assertEquals(404, $this->callApi('GET', 'teammate/{id}', get: ['id' => 999999999])['status']);

	}

}
?>
