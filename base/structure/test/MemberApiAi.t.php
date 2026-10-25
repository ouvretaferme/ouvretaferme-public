<?php
namespace structure;

/**
 * Test bout-en-bout de l'API publique structure\Member (base/structure/api/Member_0_1beta.a.php).
 *
 * Lancement : dev/test package=structure file=MemberApiAi
 */
class MemberApiAiTest extends \dev\ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new MemberModel();
	}

	protected function getMembers(?\Closure $filter = NULL): \Collection {

		$cMember = Member::model()
			->select(Member::getSelection())
			->whereStructure($this->eStructure)
			->getCollection();

		return $filter === NULL ? $cMember : $cMember->find($filter);

	}

	/** Un membre ordinaire : ni admin, ni utilisateur fantôme. */
	protected function getMemberBasic(): Member {

		$eMember = $this->getMembers(fn(Member $e) => (
			$e['role']['admin'] === FALSE and
			$e['role']->isGhost() === FALSE
		))->first();

		if($eMember === NULL) {
			throw new \Exception('La structure de référence n\'a aucun membre non admin');
		}

		return $eMember;

	}

	/** Un utilisateur fantôme, posé comme le pose l'écran de l'équipe (member.p.php, doCreateGhost). */
	protected function createMemberGhost(): Member {

		$eUser = \user\UserLib::getNew()->merge([
			'invoiceCountry' => new \user\Country(),
			'email' => NULL,
			'type' => \user\User::PRIVATE,
			'visibility' => \user\User::PRIVATE,
			'roleProducer' => TRUE
		]);

		$fw = new \FailWatch();

		$eUser->build(['firstName', 'lastName'], ['firstName' => 'Fantôme', 'lastName' => 'Fantôme API '.uniqid()]);

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('fantôme refusé : '.json_encode($fw->get())));

		MemberLib::createGhostUser($this->eStructure, $eUser);

		$eMember = $this->getMembers(fn(Member $e) => $e['user']['id'] === $eUser['id'])->first();

		$this->assertTrue($eMember !== NULL and $eMember['role']->isGhost(), 'le cas pose un utilisateur fantôme');

		return $eMember;

	}

	protected function getMemberAdmin(): Member {

		$eMember = $this->getMembers(fn(Member $e) => $e['role']['admin'])->first();

		if($eMember === NULL) {
			throw new \Exception('La structure de référence n\'a aucun membre admin');
		}

		return $eMember;

	}

	protected function getRoleAdmin(): Role {

		$eRole = RoleLib::getOne($this->eStructure, 'admin');

		if($eRole->empty()) {
			throw new \Exception('La structure de référence n\'a pas de rôle admin');
		}

		return $eRole;

	}

	protected function getRoleOther(Member $eMember): Role {

		$eRole = RoleLib::getByStructure($this->eStructure, withAdmin: FALSE)
			->find(fn(Role $e) => $e['id'] !== $eMember['role']['id'])
			->first();

		if($eRole === NULL) {
			throw new \Exception('La structure de référence n\'a pas deux rôles publics non admin');
		}

		return $eRole;

	}

	public function testList(): void {

		$response = $this->callApi('GET', 'memberList', get: ['start' => 1, 'limit' => 100]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue($response['body']['count'] > 0);
		$this->assertTrue(count($response['body']['list']) > 0);

	}

	/**
	 * Un utilisateur fantôme participe au planning et porte ses présences : il fait partie de
	 * l'équipe, même sans compte pour se connecter.
	 */
	public function testGhostIsReadable(): void {

		$eMember = $this->createMemberGhost();

		$list = $this->callApi('GET', 'memberList', get: ['start' => 1, 'limit' => 500]);

		$this->assertEquals(200, $list['status']);
		$this->assertEquals(TRUE, in_array($eMember['id'], array_column($list['body']['list'], 'id')));

		$read = $this->callApi('GET', 'member/{id}', get: ['id' => $eMember['id']]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals($eMember['status'], $read['body']['status']);
		// Un fantôme n'a pas de compte : c'est le seul signal qui le distingue dans la réponse.
		$this->assertEquals(NULL, $read['body']['user']['email']);

	}

	/** Son rôle est privé donc non attribuable, et MemberLib::delete() refuse de le supprimer. */
	public function testGhostCanNotBeWritten(): void {

		$eMember = $this->createMemberGhost();

		$update = $this->callApi('PUT', 'member/{id}', body: [
			'role' => $this->getRoleOther($eMember)['id']
		], get: ['id' => $eMember['id']]);

		$this->assertEquals(412, $update['status']);
		$this->assertEquals('ghost_not_writable', $update['body'][0]['code'] ?? NULL);

		$delete = $this->callApi('DELETE', 'member/{id}', get: ['id' => $eMember['id']]);

		$this->assertEquals(412, $delete['status']);
		$this->assertEquals('ghost_not_writable', $delete['body'][0]['code'] ?? NULL);

	}

	/** Un membre porte 44 colonnes, dont les préférences d'affichage de chaque écran du web. */
	public function testReadExposesNothingElse(): void {

		$eMember = $this->getMemberBasic();

		$response = $this->callApi('GET', 'member/{id}', get: ['id' => $eMember['id']]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(['id', 'user', 'role', 'status'], array_keys($response['body']));
		$this->assertEquals(['id', 'firstName', 'lastName', 'email', 'vignette'], array_keys($response['body']['user']));
		$this->assertEquals($eMember['role']['id'], $response['body']['role']);
		$this->assertEquals(Member::IN, $response['body']['status']);

	}

	/** user\User n'a pas d'API : l'identité du membre doit être lisible sans second appel. */
	public function testUserIsResolved(): void {

		$eMember = $this->getMemberBasic();

		$eUser = \user\UserLib::getById($eMember['user']['id']);

		// Hors build() : la photo s'écrit par le téléversement (\media\UserVignetteLib), jamais par un
		// formulaire. Le hash a la forme que pose MediaLib, 19 caractères, la lettre du format, puis la
		// couleur et la version qui complètent les 30 caractères de la colonne.
		$eUser['vignette'] = \media\UtilLib::generateHash().'j'.'#000000'.'001';
		\user\UserLib::update($eUser, ['vignette']);

		$response = $this->callApi('GET', 'member/{id}', get: ['id' => $eMember['id']]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($eUser['id'], $response['body']['user']['id']);
		$this->assertTrue(str_contains($response['body']['user']['vignette'], 'user-vignette/'));

	}

	/**
	 * L'adresse d'un membre suit ce que l'écran de l'équipe en montre (MemberUi::getUser()) : en clair
	 * pour un compte public, jamais pour un compte privé. Le même membre passe d'un état à l'autre, pour
	 * que seul le choix du compte départage les deux réponses.
	 */
	public function testEmailFollowsVisibility(): void {

		$eMember = $this->getMemberBasic();

		$eUser = \user\UserLib::getById($eMember['user']['id']);

		if($this->assertDecorPresent($eUser['email'] !== NULL, 'un membre ordinaire avec une adresse e-mail') === FALSE) {
			return;
		}

		// Hors build() : la visibilité se choisit sur le compte de la personne, que le test ne peut pas
		// emprunter, et le cas éprouve sa lecture par l'API, pas sa saisie.
		$eUser['visibility'] = \user\User::PUBLIC;
		\user\UserLib::update($eUser, ['visibility']);

		$response = $this->callApi('GET', 'member/{id}', get: ['id' => $eMember['id']]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($eUser['email'], $response['body']['user']['email'], 'compte public');

		$eUser['visibility'] = \user\User::PRIVATE;
		\user\UserLib::update($eUser, ['visibility']);

		$response = $this->callApi('GET', 'member/{id}', get: ['id' => $eMember['id']]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue(array_key_exists('email', $response['body']['user']), 'le champ reste dans le contrat');
		$this->assertEquals(NULL, $response['body']['user']['email'], 'compte privé, lecture');

		$response = $this->callApi('GET', 'memberList', get: ['ids' => [$eMember['id']]]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals([$eMember['id']], array_column($response['body']['list'], 'id'), 'memberList rend le membre demandé');
		$this->assertEquals(NULL, $response['body']['list'][0]['user']['email'], 'compte privé, liste');

	}

	public function testUpdateRole(): void {

		$eMember = $this->getMemberBasic();
		$eRole = $this->getRoleOther($eMember);

		$response = $this->callApi('PUT', 'member/{id}', body: [
			'role' => $eRole['id']
		], get: ['id' => $eMember['id']]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($eRole['id'], $response['body']['role']);

	}

	/** Le rôle fantôme est privé : l'attribuer sortirait le membre de l'équipe visible. */
	public function testUpdateToGhostRoleIsRejected(): void {

		$eMember = $this->getMemberBasic();
		$eRoleGhost = RoleLib::getOne($this->eStructure, 'ghost');

		$response = $this->callApi('PUT', 'member/{id}', body: [
			'role' => $eRoleGhost['id']
		], get: ['id' => $eMember['id']]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Member::role.known', $response['body'][0]['code'] ?? NULL);

	}

	/** Seul le rôle est modifiable : le reste des colonnes est de la préférence d'affichage. */
	public function testOtherPropertiesAreNotWritable(): void {

		$eMember = $this->getMemberBasic();

		foreach(['status' => Member::OUT, 'user' => 1] as $property => $value) {

			$response = $this->callApi('PUT', 'member/{id}', body: [
				'properties' => [$property],
				$property => $value
			], get: ['id' => $eMember['id']]);

			$this->assertEquals(400, $response['status']);
			$this->assertEquals('property_unknown', $response['body'][0]['code'] ?? NULL);

		}

	}

	/** On ne rejoint pas une structure par l'API : il faut passer par une invitation. */
	public function testCreateIsNotExposed(): void {

		$response = $this->callApi('POST', 'member', body: ['role' => 1]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('unknown_route', $response['body'][0]['code'] ?? NULL);

	}

	public function testDelete(): void {

		$eMember = $this->getMemberBasic();

		$delete = $this->callApi('DELETE', 'member/{id}', get: ['id' => $eMember['id']]);

		$this->assertEquals(200, $delete['status']);

		$read = $this->callApi('GET', 'member/{id}', get: ['id' => $eMember['id']]);

		$this->assertEquals(404, $read['status']);

	}

	/**
	 * Un jeton d'API n'agit au nom d'aucun membre : le laisser toucher aux administrateurs lui
	 * permettrait de vider une structure de tous ceux qui peuvent la gérer.
	 */
	public function testAdminCanNotBeWritten(): void {

		$eMember = $this->getMemberAdmin();

		$update = $this->callApi('PUT', 'member/{id}', body: [
			'role' => $this->getRoleOther($eMember)['id']
		], get: ['id' => $eMember['id']]);

		$this->assertEquals(412, $update['status']);
		$this->assertEquals('admin_not_writable', $update['body'][0]['code'] ?? NULL);

		$delete = $this->callApi('DELETE', 'member/{id}', get: ['id' => $eMember['id']]);

		$this->assertEquals(412, $delete['status']);
		$this->assertEquals('admin_not_writable', $delete['body'][0]['code'] ?? NULL);

	}

	/** Un administrateur reste visible dans l'équipe, seule son écriture est fermée. */
	public function testAdminIsReadable(): void {

		$eMember = $this->getMemberAdmin();

		$read = $this->callApi('GET', 'member/{id}', get: ['id' => $eMember['id']]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals($eMember['role']['id'], $read['body']['role']);

		$list = $this->callApi('GET', 'memberList', get: ['start' => 1, 'limit' => 500]);

		$this->assertEquals(TRUE, in_array($eMember['id'], array_column($list['body']['list'], 'id')));

	}

	/** Sinon l'interdiction d'inviter un admin se contournerait en promouvant un membre ordinaire. */
	public function testPromotionToAdminIsRejected(): void {

		$eMember = $this->getMemberBasic();

		$response = $this->callApi('PUT', 'member/{id}', body: [
			'role' => $this->getRoleAdmin()['id']
		], get: ['id' => $eMember['id']]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Member::role.known', $response['body'][0]['code'] ?? NULL);

	}

	public function testNotFound(): void {

		$response = $this->callApi('GET', 'member/{id}', get: ['id' => 999999999]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

}
?>
