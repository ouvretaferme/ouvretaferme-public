<?php
namespace structure;

/**
 * Ce qu'un groupe de formation perd par rapport aux autres groupes (décisions du
 * 26/09/2026) : ni règles, ni logo, ni bandeau, ni présentation, et plus de rôle enseignant, ses
 * enseignants passant administrateurs.
 *
 * Décor : celui de `CommunityFixtureAiTest`, dont le groupe partageant passe en formation le temps
 * d'une transaction. L'enseignant fictif est créé hors transaction, retrouvé par son adresse : une
 * adresse `example.org` ne reçoit rien.
 *
 * Lancement : dev/test package=structure file=EducationGroupAi
 */
class EducationGroupAiTest extends CommunityFixtureAiTest {

	const EMAIL_TEACHER = 'zztest.enseignant.fusion@example.org';

	/**
	 * Un identifiant qu'aucune structure ne porte : les rôles d'un groupe neuf s'y écrivent dans la
	 * transaction, sans créer la structure, dont `StructureLib::create()` validerait la transaction
	 * par son `CREATE DATABASE`.
	 */
	const STRUCTURE_UNUSED = 999999999;

	private \user\User $eUserTeacher;

	public function init(): void {

		parent::init();

		$this->eUserTeacher = $this->seedTeacher();

	}

	public function setUp(): void {

		Affiliation::model()->beginTransaction();

		MemberLib::clearCache();

	}

	/**
	 * ⚠️ Les rôles vivent aussi dans Redis (`RoleLib::getCacheKey()`), que le `rollBack()` n'atteint
	 * pas : sans la purge, le rôle enseignant créé puis retiré ici survivrait dans le cache.
	 */
	public function tearDown(): void {

		Affiliation::model()->rollBack();

		RoleLib::deleteCache(new Structure(['id' => $this->communitySharing]));
		RoleLib::deleteCache(new Structure(['id' => self::STRUCTURE_UNUSED]));

		MemberLib::clearCache();

	}

	public function testAccept(): void {

		$cases = [
			'formation' => [Structure::COMMUNITY, Structure::EDUCATION, FALSE, FALSE, FALSE, FALSE],
			'groupe de producteurs' => [Structure::COMMUNITY, Structure::PRODUCER, TRUE, TRUE, TRUE, TRUE],
			'réseau' => [Structure::COMMUNITY, Structure::NETWORK, TRUE, TRUE, TRUE, TRUE],
			'groupe comptable' => [Structure::COMMUNITY, Structure::ACCOUNTING, TRUE, TRUE, TRUE, TRUE],
			'ferme' => [Structure::PRODUCER, NULL, TRUE, TRUE, TRUE, FALSE],
		];

		foreach($cases as $label => [$type, $communityType, $presentation, $logo, $banner, $rules]) {

			$eStructure = new Structure(['type' => $type, 'communityType' => $communityType]);

			$this->assertEquals(
				[$presentation, $logo, $banner, $rules],
				[$eStructure->acceptPresentation(), $eStructure->acceptLogo(), $eStructure->acceptBanner(), $eStructure->acceptRules()],
				$label.' : présentation, logo, bandeau, règles'
			);

		}

	}

	public function testRolesOfNewGroup(): void {

		$eStructure = new Structure([
			'id' => self::STRUCTURE_UNUSED,
			'type' => Structure::COMMUNITY,
			'communityType' => Structure::EDUCATION
		]);

		RoleLib::createForStructure($eStructure);

		$fqns = Role::model()
			->whereStructure($eStructure)
			->getColumn('fqn');

		sort($fqns);

		$this->assertEquals(['admin', 'student'], $fqns, 'un groupe de formation naît sans rôle enseignant');

	}

	public function testStartAndCharter(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructureEducation = $this->placeGroup(Structure::EDUCATION);

		$this->assertEquals(0, CommunityStartAiUi::getStepNumber($eStructureEducation, 'description'), 'pas d\'étape de présentation en formation');

		$eStructureProducer = $this->placeGroup(Structure::PRODUCER);

		$this->assertEquals(1, CommunityStartAiUi::getStepNumber($eStructureProducer, 'description'), 'la présentation reste la première étape d\'un groupe de producteurs');

	}

	/**
	 * L'administrateur voit toutes les promotions, sans y être rangé.
	 */
	public function testAdminScope(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructureEducation = $this->placeGroup(Structure::EDUCATION);

		\user\ConnectionLib::setOnline(\user\UserLib::getById($this->userOther, \user\User::getSelection()));

		$this->assertTrue($eStructureEducation->getMember()->isAdmin(), 'le second lecteur administre le groupe');
		$this->assertEquals(NULL, SectionLib::getScope($eStructureEducation), 'il voit tout le groupe');

	}

	/**
	 * La reprise déplace les membres et les invitations du rôle enseignant vers l'administrateur,
	 * puis retire le rôle. Rejouée, elle ne fait rien.
	 */
	public function testMergeTeacherIntoAdmin(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructureEducation = $this->placeGroup(Structure::EDUCATION);

		$eRoleTeacher = new Role([
			'structure' => $eStructureEducation,
			'name' => 'Enseignant',
			'fqn' => 'teacher',
			'productionObserve' => TRUE
		]);

		RoleLib::create($eRoleTeacher);

		MemberLib::create(new Member([
			'user' => $this->eUserTeacher,
			'structure' => $eStructureEducation,
			'role' => $eRoleTeacher,
			'status' => Member::IN
		]));

		$eInvite = new Invite([
			'structure' => $eStructureEducation,
			'type' => Invite::MEMBER,
			'email' => NULL,
			'memberRole' => $eRoleTeacher,
			'notifyEmail' => FALSE
		]);

		InviteLib::create($eInvite);

		$this->assertEquals(['members' => 1, 'invites' => 1], RoleLib::mergeTeacherIntoAdmin($eStructureEducation), 'un membre et une invitation déplacés');

		$fqnMember = Member::model()
			->select(['role' => ['fqn']])
			->whereStructure($eStructureEducation)
			->whereUser($this->eUserTeacher)
			->get()['role']['fqn'];

		$fqnInvite = Invite::model()
			->select(['memberRole' => ['fqn']])
			->whereId($eInvite['id'])
			->get()['memberRole']['fqn'];

		$this->assertEquals(['admin', 'admin'], [$fqnMember, $fqnInvite], 'l\'enseignant et son invitation passent administrateurs');
		$this->assertFalse(Role::model()->whereStructure($eStructureEducation)->whereFqn('teacher')->exists(), 'le rôle enseignant est retiré');
		$this->assertEquals(['members' => 0, 'invites' => 0], RoleLib::mergeTeacherIntoAdmin($eStructureEducation), 'rejouée, la reprise ne fait rien');

	}

	/**
	 * L'administrateur d'un groupe de formation naît « Formateur », celui des autres groupes
	 * « Administrateur », et la reprise renomme ceux d'avant la décision. Rejouée, elle ne fait rien.
	 */
	public function testEducationAdminName(): void {

		$eStructure = new Structure([
			'id' => self::STRUCTURE_UNUSED,
			'type' => Structure::COMMUNITY,
			'communityType' => Structure::EDUCATION
		]);

		RoleLib::createForStructure($eStructure);

		$name = fn() => Role::model()
			->whereStructure($eStructure)
			->whereFqn('admin')
			->getValue('name');

		$this->assertEquals(RoleUi::getEducationAdmin(), $name(), 'un groupe de formation naît avec des formateurs');

		Role::model()
			->whereStructure($eStructure)
			->whereFqn('admin')
			->update(['name' => RoleUi::getFqn('admin')]);

		$this->assertTrue(RoleLib::nameEducationAdmin($eStructure), 'la reprise renomme l\'administrateur d\'avant la décision');
		$this->assertEquals(RoleUi::getEducationAdmin(), $name(), 'il s\'appelle désormais Formateur');
		$this->assertFalse(RoleLib::nameEducationAdmin($eStructure), 'rejouée, la reprise ne fait rien');

		Role::model()->whereStructure($eStructure)->delete();

		$eStructure['communityType'] = Structure::NETWORK;

		RoleLib::createForStructure($eStructure);

		$this->assertEquals(RoleUi::getFqn('admin'), $name(), 'un réseau garde ses administrateurs');

	}

	/**
	 * Le refus du serveur sur la première promotion se rattache à son champ, et une promotion de plus
	 * n'est pas obligatoire.
	 */
	public function testStartSectionsStep(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructureEducation = $this->placeGroup(Structure::EDUCATION);

		$html = $this->getStartSteps($eStructureEducation, new \Collection());

		$sections = $this->getStep($html, 'community-start-sections');

		$this->assertTrue(str_contains($sections, 'data-wrapper="name[1]"'), 'le refus du serveur se rattache au premier champ');

		$sections = $this->getStep($this->getStartSteps($eStructureEducation, new \Collection([1 => new Section(['id' => 1, 'name' => 'BPREA 2026-2027'])])), 'community-start-sections');

		$this->assertFalse(str_contains($sections, 'required'), 'un groupe qui a sa promotion en ajoute une autre sans obligation');

	}

	/**
	 * Un groupe de formation n'invite que ses formateurs dans son parcours : ni question du
	 * public, ni promotion, ni lien. Un réseau garde ses deux publics et son lien.
	 */
	public function testStartInvitesStep(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructureEducation = $this->placeGroup(Structure::EDUCATION);

		$invites = $this->getStep($this->getStartSteps($eStructureEducation, new \Collection([1 => new Section(['id' => 1, 'name' => 'BPREA 2026-2027'])])), 'community-start-invites');

		$this->assertTrue(str_contains($invites, 'name="audience" type="hidden" value="staff"'), 'les formateurs, sans question');
		$this->assertFalse(str_contains($invites, 'name="section"'), 'aucune promotion');
		$this->assertFalse(str_contains($invites, 'data-onboarding-links'), 'aucun lien, qui ferait entrer un étudiant');

		$this->assertEquals(['staff' => 'admin'], $eStructureEducation->getAudienceRoles(), 'et le serveur n\'accepte qu\'eux');

		$eStructureNetwork = $this->placeGroup(Structure::NETWORK);
		$eStructureNetwork['shareCounts'] = AffiliationAiLib::countSharesByCommunity($eStructureNetwork);

		$invites = $this->getStep($this->getStartSteps($eStructureNetwork, new \Collection()), 'community-start-invites');

		$this->assertTrue(str_contains($invites, 'name="audience" type="radio"'), 'un réseau choisit son public');
		$this->assertTrue(str_contains($invites, 'data-onboarding-links'), 'et garde son lien');

		$this->placeGroup(Structure::EDUCATION);

	}

	/**
	 * L'encadrement n'entre jamais dans un sous-groupe, les adhérents si (décision du
	 * 26/09/2026). La règle se déduit du rôle, jamais du type de groupe : chaque rôle du décor est
	 * éprouvé avec un sous-groupe, et seuls ceux qui n'encadrent pas passent.
	 */
	public function testInviteStaffNeverInSection(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructure = $this->getStructure($this->communitySharing);
		$cRole = RoleLib::getByStructure($eStructure);
		$cSection = new \Collection([1 => new Section(['id' => 1, 'name' => 'BPREA 2026-2027'])]);

		$staff = 0;

		foreach($cRole as $eRole) {

			$eInvite = new Invite([
				'structure' => $eStructure,
				'type' => Invite::MEMBER,
				'cRole' => $cRole,
				'cSection' => $cSection
			]);

			$fw = new \FailWatch();

			$eInvite->build(['memberRole', 'memberSection', 'email'], [
				'memberRole' => $eRole['id'],
				'memberSection' => 1,
				'email' => 'zztest.section@example.org'
			]);

			$fails = array_column($fw->format(), 0);

			if($eRole->isStaff()) {
				$staff++;
				$this->assertTrue(in_array('Invite::memberSection.staff', $fails, TRUE), $eRole['fqn'].' encadre, il n\'entre dans aucun sous-groupe');
			} else {
				$this->assertFalse(in_array('Invite::memberSection.staff', $fails, TRUE), $eRole['fqn'].' adhère, il peut être rangé');
			}

		}

		$this->assertTrue($staff > 0, 'le décor porte au moins un rôle d\'encadrement');

	}

	/**
	 * La même règle sur un membre déjà présent, quand on change son rôle et son rangement
	 * (décision du 27/09/2026) : un rôle d'encadrement ne se range dans aucun
	 * sous-groupe, et sans case cochée il passe.
	 */
	public function testMemberStaffNeverInSection(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructure = $this->getStructure($this->communitySharing);
		$cRole = RoleLib::getByStructure($eStructure);

		$staff = 0;

		foreach($cRole as $eRole) {

			foreach([[1], []] as $sections) {

				$eMember = new Member(['structure' => $eStructure, 'cRole' => $cRole]);

				$fw = new \FailWatch();

				$eMember->build(['role'], ['role' => $eRole['id'], 'sections' => $sections]);

				$messages = array_column($fw->format(), 1, 0);
				$refused = array_key_exists('Member::role.staff', $messages);

				if($eRole->isStaff() and $sections !== []) {
					$this->assertTrue($refused, $eRole['fqn'].' encadre, il ne se range dans aucun sous-groupe');
					// Le refus parle le mot du groupe : un message vide ne s'afficherait pas.
					$this->assertEquals(StructureUi::getText($eStructure, 'sectionStaff'), $messages['Member::role.staff'] ?? NULL);
					$this->assertDiffer('', $messages['Member::role.staff'] ?? '');
				} else {
					$this->assertFalse($refused, $eRole['fqn'].' passe avec '.count($sections).' sous-groupe(s)');
				}

			}

			if($eRole->isStaff()) {
				$staff++;
			}

		}

		$this->assertTrue($staff > 0, 'le décor porte au moins un rôle d\'encadrement');

	}

	/**
	 * Le conseiller d'un réseau d'accompagnement suit la même règle que le formateur (décision de
	 * du 27/09/2026) : il n'entre dans aucun groupe, ni par l'invitation ni par le
	 * rangement d'un membre, et le refus parle de groupe. Les rôles sont ceux que
	 * `RoleLib::createForStructure()` pose sur un réseau, le décor ayant été créé sous un autre type.
	 */
	public function testNetworkAdvisorNeverInSection(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructureNetwork = $this->placeGroup(Structure::NETWORK);

		$cRole = new \Collection([
			901 => new Role(['id' => 901, 'fqn' => 'member', 'name' => 'Membre', 'admin' => FALSE, 'sections' => Role::ALL]),
			902 => new Role(['id' => 902, 'fqn' => 'advisor', 'name' => 'Conseiller', 'admin' => FALSE, 'sections' => Role::ALL])
		]);
		$cSection = new \Collection([1 => new Section(['id' => 1, 'name' => 'Groupe maraîchage'])]);

		$message = StructureUi::getText($eStructureNetwork, 'sectionStaff');
		$this->assertTrue(str_contains($message, 'aucun groupe'), 'le refus parle de groupe dans un réseau');

		foreach($cRole as $eRole) {

			$eInvite = new Invite([
				'structure' => $eStructureNetwork,
				'type' => Invite::MEMBER,
				'cRole' => $cRole,
				'cSection' => $cSection
			]);

			$fw = new \FailWatch();
			$eInvite->build(['memberRole', 'memberSection', 'email'], [
				'memberRole' => $eRole['id'],
				'memberSection' => 1,
				'email' => 'zztest.section@example.org'
			]);
			$messagesInvite = array_column($fw->format(), 1, 0);

			$eMember = new Member(['structure' => $eStructureNetwork, 'cRole' => $cRole]);

			$fw = new \FailWatch();
			$eMember->build(['role'], ['role' => $eRole['id'], 'sections' => [1]]);
			$messagesMember = array_column($fw->format(), 1, 0);

			if($eRole['fqn'] === 'advisor') {
				$this->assertEquals($message, $messagesInvite['Invite::memberSection.staff'] ?? NULL, 'le conseiller ne s\'invite dans aucun groupe');
				$this->assertEquals($message, $messagesMember['Member::role.staff'] ?? NULL, 'le conseiller ne se range dans aucun groupe');
			} else {
				$this->assertEquals([], $messagesInvite, 'le membre s\'invite dans un groupe');
				$this->assertEquals([], $messagesMember, 'le membre se range dans un groupe');
			}

		}

		$this->placeGroup(Structure::EDUCATION);

	}

	/**
	 * L'étudiant crée sa ferme pour la formation : le bouton ouvre directement la ferme
	 * d'apprenant, là où un producteur choisit encore sa catégorie.
	 */
	public function testOnboardingFarm(): void {

		$eStructure = new Structure(['id' => 1, 'type' => Structure::COMMUNITY, 'communityType' => Structure::EDUCATION]);
		$onboarding = ['state' => StructureCommunityLib::FARM];

		$education = new StructureEducationUi()->getOnboarding($eStructure, $onboarding);

		$this->assertTrue(str_contains($education, '/demarrer/ferme?producerType='.Structure::LEARNER.'"'));

		// Le partage propose aussi de créer une ferme : le même geste mène au même endroit.
		$share = new StructureEducationUi()->getOnboarding($eStructure, ['state' => StructureCommunityLib::SHARE]);
		$this->assertTrue(str_contains($share, '/demarrer/ferme?producerType='.Structure::LEARNER.'"'));
		$this->assertTrue(str_contains(StructureEducationUi::getText($eStructure, 'sectionStaff'), 'promotion'));

		// Le bouton de la page des membres ouvre un formulaire qui propose aussi Formateur : il ne
		// nomme pas les seuls étudiants. L'encart du formateur ne parle pas d'administration.
		$this->assertEquals(s("Inviter"), StructureEducationUi::getText($eStructure, 'membersInviteAll'));
		$this->assertFalse(str_contains(StructureEducationUi::getText($eStructure, 'inviteAdmin'), "retirer l'administration"));

		$eStructure['communityType'] = Structure::NETWORK;
		$network = new StructureNetworkUi()->getOnboarding($eStructure, $onboarding);

		$this->assertTrue(str_contains($network, 'href="/demarrer/ferme"'));

	}

	/**
	 * La carte d'un membre de l'encadrement ne répète son rôle que si la section en réunit
	 * plusieurs : des formateurs seuls n'ont rien à distinguer, un administrateur et un conseiller
	 * de réseau si.
	 */
	public function testStaffRoleOnlyWhenMixed(): void {

		$eStructureEducation = new Structure(['type' => Structure::COMMUNITY, 'communityType' => Structure::EDUCATION]);
		$eStructureNetwork = new Structure(['type' => Structure::COMMUNITY, 'communityType' => Structure::NETWORK]);

		$cMember = new \Collection([new Member(['id' => 1])]);
		$cRole = new \Collection([2 => new Role(['id' => 2, 'fqn' => 'advisor', 'name' => 'Conseiller'])]);

		[[, , , $roleExpected]] = StructureCommunityUi::getMembersOrder($eStructureEducation, new \Collection(['' => $cMember]), new \Collection());

		$this->assertFalse($roleExpected, 'des formateurs seuls ne répètent pas leur rôle');

		[[, , , $roleExpected]] = StructureCommunityUi::getMembersOrder($eStructureNetwork, new \Collection(['' => $cMember, 2 => $cMember]), $cRole);

		$this->assertTrue($roleExpected, 'un administrateur et un conseiller se distinguent');

	}

	/**
	 * Chaque en-tête compte ses membres : l'encadrement et les rôles des membres s'accordent tous
	 * deux au nombre, sans quoi l'écran lisait « Formateurs » au-dessus de « Étudiant ».
	 */
	public function testMembersOrderLabelsAgree(): void {

		$eStructure = new Structure(['type' => Structure::COMMUNITY, 'communityType' => Structure::EDUCATION]);

		$cMemberOne = new \Collection([new Member(['id' => 1])]);
		$cMemberTwo = new \Collection([new Member(['id' => 2]), new Member(['id' => 3])]);
		$cRole = new \Collection([2 => new Role(['id' => 2, 'fqn' => 'student', 'name' => 'Étudiant', 'sections' => Role::ALL])]);

		[[$labelStaff], [$labelMember]] = StructureEducationUi::getMembersOrder($eStructure, new \Collection(['' => $cMemberTwo, 2 => $cMemberTwo]), $cRole);

		$this->assertEquals(s("Formateurs"), $labelStaff);
		$this->assertEquals(s("Étudiants"), $labelMember);

		[[$labelStaff], [$labelMember]] = StructureEducationUi::getMembersOrder($eStructure, new \Collection(['' => $cMemberOne, 2 => $cMemberOne]), $cRole);

		$this->assertEquals(s("Formateur"), $labelStaff);
		$this->assertEquals(s("Étudiant"), $labelMember);

	}

	private function getStartSteps(Structure $eStructure, \Collection $cSection): string {

		return CommunityStartAiUi::getStartSteps($eStructure, $cSection, ['invites' => 0, 'members' => 1, 'staff' => 0], new Invite(), 1, CommunityStartAiUi::ORIGIN_HOME);

	}

	/** Le formulaire d'une étape, de son ouverture à sa fermeture. */
	private function getStep(string $html, string $id): string {

		$start = strpos($html, 'id="'.$id.'"');

		return substr($html, $start, strpos($html, '</form>', $start) - $start);

	}

	private function placeGroup(string $communityType): Structure {

		$eStructureCommunity = $this->getStructure($this->communitySharing);
		$eStructureCommunity['communityType'] = $communityType;

		Structure::model()->select(['communityType'])->update($eStructureCommunity);

		return $this->getStructure($this->communitySharing);

	}

	private function seedTeacher(): \user\User {

		$eUser = \user\User::model()
			->select(\user\User::getSelection())
			->whereEmail(self::EMAIL_TEACHER)
			->get();

		if($eUser->notEmpty()) {
			return $eUser;
		}

		$eUser = new \user\User([
			'firstName' => 'zzTest',
			'lastName' => 'Enseignant',
			'email' => self::EMAIL_TEACHER,
			'visibility' => \user\User::PRIVATE,
			'roleCustomer' => FALSE,
			'roleProducer' => TRUE,
			'roleConsultant' => FALSE,
			'roleTeacher' => FALSE
		]);

		$fw = new \FailWatch();

		\user\UserLib::create($eUser);

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('création de l\'enseignant refusée : '.json_encode($fw->get())));

		return \user\UserLib::getById($eUser['id'], \user\User::getSelection());

	}

}
?>
