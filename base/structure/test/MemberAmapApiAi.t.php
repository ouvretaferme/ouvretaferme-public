<?php
namespace structure;

/**
 * La page Équipe d'une AMAP ne montre que ses administrateurs (`page/member.p.php`) : ses producteurs et
 * ses référents relèvent de l'AMAP, pas de son équipe. Une AMAP n'ayant pas d'API, c'est le refus de la
 * route qui protège ses membres, et `MemberLib::applyTeam()` qui porte la règle de l'écran.
 *
 * Le décor A1 n'a pas d'administrateur : le cas en pose un dans la transaction, qui porterait le jeton.
 *
 * Lancement : dev/test package=structure file=MemberAmapApiAi
 */
class MemberAmapApiAiTest extends \dev\ApiTest {

	const STRUCTURE = FixtureAiLib::A1;

	/** Deux comptes du décor sans lien avec l'AMAP (`FixtureAiLib::USERS`). */
	const EMAIL_ADMIN = 'decor.f1.admin@example.org';
	const EMAIL_PRODUCER = 'decor.sans.structure@example.org';

	private Member $eMemberAdmin;
	private Member $eMemberProducer;

	protected function getTransactionModel(): \ModuleModel {
		return new MemberModel();
	}

	public function setUp(): void {

		parent::setUp();

		$this->eMemberAdmin = $this->createMember(self::EMAIL_ADMIN, 'admin');
		$this->eMemberProducer = $this->createMember(self::EMAIL_PRODUCER, 'producer');

	}

	private function createMember(string $email, string $fqn): Member {

		$eUser = \user\UserLib::getByEmail($email);
		$eRole = RoleLib::getOne($this->eStructure, $fqn);

		if($eUser->empty() or $eRole->empty()) {
			return new Member();
		}

		$eMember = new Member([
			'structure' => $this->eStructure,
			'user' => $eUser,
			'role' => $eRole,
			'status' => Member::IN
		]);

		MemberLib::create($eMember);

		return $eMember;

	}

	/** Une AMAP n'a pas d'API (`Structure::acceptApiAccess()`) : memberList et member/{id} se refusent avant tout cadrage. */
	public function testApiIsRefused(): void {

		if($this->assertDecorPresent($this->eMemberProducer->notEmpty(), 'le compte '.self::EMAIL_PRODUCER.' et le rôle producer de l\'AMAP') === FALSE) {
			return;
		}

		$list = $this->callApi('GET', 'memberList');

		$this->assertEquals(403, $list['status']);
		$this->assertEquals('structure_not_allowed', $list['body'][0]['code'] ?? NULL);

		$delete = $this->callApi('DELETE', 'member/{id}', get: ['id' => $this->eMemberProducer['id']]);

		$this->assertEquals(403, $delete['status']);
		$this->assertTrue(MemberLib::getById($this->eMemberProducer['id'])->notEmpty(), 'le producteur est toujours membre');

	}

	/**
	 * La règle de l'écran vaut pour l'AMAP comme pour les autres structures, même sans route pour la lire :
	 * `applyTeam()` ne garde que l'administrateur, et ni le producteur ni la référente du décor.
	 */
	public function testOnlyAdminsAreInTeam(): void {

		if($this->assertDecorPresent($this->eMemberAdmin->notEmpty() and $this->eMemberProducer->notEmpty(), 'les comptes '.self::EMAIL_ADMIN.' et '.self::EMAIL_PRODUCER.', et les rôles admin et producer de l\'AMAP') === FALSE) {
			return;
		}

		$cMember = MemberLib::applyTeam(new MemberModel()->whereStructure($this->eStructure), $this->eStructure)
			->select('id')
			->getCollection(index: 'id');

		$this->assertEquals([$this->eMemberAdmin['id']], $cMember->getIds());

	}

}
?>
