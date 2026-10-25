<?php
namespace structure;

/**
 * memberList et member/{id} rendent sur une structure de production ce que la page Équipe montre
 * (`MemberLib::applyTeam()`) : son équipe, adhésion sortie comprise, et les personnes sans compte.
 *
 * Les deux cas passent par `ids` et par `member/{id}`, où le filtre de la liste et la lecture par id
 * portent sur `id` sans préfixe de table : une équipe lue par jointure les rendrait ambigus.
 *
 * Lancement : dev/test package=structure file=MemberTeamApiAi
 */
class MemberTeamApiAiTest extends \dev\ApiTest {

	/** Un compte du décor qui n'appartient à aucune structure (`FixtureAiLib::USERS`). */
	const EMAIL_OUTSIDE = 'decor.sans.structure@example.org';

	protected function getTransactionModel(): \ModuleModel {
		return new MemberModel();
	}

	/**
	 * Une personne de l'équipe à qui l'on a retiré tout rôle, par le geste de l'écran : elle entre dans
	 * l'équipe avec un accès, puis l'accès se retire et l'adhésion reste, sortie et sans rôle.
	 */
	private function createMemberRevoked(\user\User $eUser, Role $eRole): Member {

		\hr\TeammateLib::create(new \hr\Teammate([
			'structure' => $this->eStructure,
			'user' => $eUser
		]));

		$eMember = new Member([
			'structure' => $this->eStructure,
			'user' => $eUser,
			'role' => $eRole,
			'status' => Member::IN
		]);

		MemberLib::create($eMember);
		// deleteInternal() et non delete() : celui-ci compare au compte connecté, qu'une ligne de commande n'a pas.
		MemberLib::deleteInternal(MemberLib::getById($eMember['id']));

		$eMember = MemberLib::getById($eMember['id']);

		$this->assertTrue($eMember->notEmpty() and $eMember->isRevoked(), 'l\'adhésion reste, sortie et sans rôle');

		return $eMember;

	}

	/** Une personne sans compte, posée comme la pose l'écran de l'équipe. */
	private function createMemberGhost(): Member {

		$eUser = MemberLib::getNewGhostUser();

		$fw = new \FailWatch();

		$eUser->build(['firstName', 'lastName'], ['firstName' => 'Fantôme', 'lastName' => 'Équipe API '.uniqid()]);

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('fantôme refusé : '.json_encode($fw->get())));

		MemberLib::createGhostUser($this->eStructure, $eUser);

		return Member::model()
			->select(Member::getSelection())
			->whereStructure($this->eStructure)
			->whereUser($eUser)
			->get();

	}

	public function testRevokedTeammateIsListed(): void {

		$eUser = \user\UserLib::getByEmail(self::EMAIL_OUTSIDE);

		$eRole = Role::model()
			->select(Role::getSelection())
			->whereStructure($this->eStructure)
			->whereAdmin(FALSE)
			->whereVisibility(Role::PUBLIC)
			->get();

		if($this->assertDecorPresent($eUser->notEmpty() and $eRole->notEmpty(), 'le compte '.self::EMAIL_OUTSIDE.' et un rôle public non administrateur de la structure') === FALSE) {
			return;
		}

		$eMember = $this->createMemberRevoked($eUser, $eRole);

		$list = $this->callApi('GET', 'memberList', get: ['ids' => [$eMember['id']]]);

		$this->assertEquals(200, $list['status']);
		$this->assertEquals(1, $list['body']['count'] ?? NULL);

		$read = $this->callApi('GET', 'member/{id}', get: ['id' => $eMember['id']]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals(Member::OUT, $read['body']['status'] ?? NULL);
		$this->assertTrue(array_key_exists('role', $read['body']) and $read['body']['role'] === NULL);

		$update = $this->callApi('PUT', 'member/{id}', body: [
			'properties' => ['role'],
			'role' => RoleLib::getOne($this->eStructure, 'admin')['id']
		], get: ['id' => $eMember['id']]);

		$this->assertEquals(412, $update['status']);

		$delete = $this->callApi('DELETE', 'member/{id}', get: ['id' => $eMember['id']]);

		$this->assertEquals(412, $delete['status']);

	}

	public function testGhostIsListed(): void {

		$eMember = $this->createMemberGhost();

		$this->assertTrue($eMember->notEmpty() and $eMember['role']->isGhost(), 'le cas pose une personne sans compte');

		$list = $this->callApi('GET', 'memberList', get: ['ids' => [$eMember['id']]]);

		$this->assertEquals(200, $list['status']);
		$this->assertEquals(1, $list['body']['count'] ?? NULL);

		$read = $this->callApi('GET', 'member/{id}', get: ['id' => $eMember['id']]);

		$this->assertEquals(200, $read['status']);

	}

	/**
	 * Une adhésion sortie sans rôle hors de l'équipe, que l'écran ne montre pas, ne sort pas non plus de l'API.
	 * Le produit ne la fabrique pas (`MemberLib::deleteInternal()` supprime l'adhésion d'une personne hors de
	 * l'équipe) : elle est posée telle quelle pour éprouver que memberList et member/{id} portent bien la
	 * règle de l'écran, et non le seul cadrage par structure.
	 */
	public function testOutsideRevokedIsHidden(): void {

		$eUser = \user\UserLib::getByEmail(self::EMAIL_OUTSIDE);

		if($this->assertDecorPresent($eUser->notEmpty(), 'le compte '.self::EMAIL_OUTSIDE) === FALSE) {
			return;
		}

		$eMember = new Member([
			'structure' => $this->eStructure,
			'user' => $eUser,
			'role' => new Role(),
			'status' => Member::OUT
		]);

		MemberLib::create($eMember);

		$list = $this->callApi('GET', 'memberList', get: ['ids' => [$eMember['id']]]);

		$this->assertEquals(200, $list['status']);
		$this->assertEquals(0, $list['body']['count'] ?? NULL);

		$this->assertEquals(404, $this->callApi('GET', 'member/{id}', get: ['id' => $eMember['id']])['status']);

	}

}
?>
