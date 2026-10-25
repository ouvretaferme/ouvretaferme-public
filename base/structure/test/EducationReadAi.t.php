<?php
namespace structure;

/**
 * Dans un groupe de formation, les étudiants ne se lisent pas entre eux.
 *
 * Décision du 25/09/2026 (« retire le droit, les étudiants ne se voient pas entre
 * eux ») : la lecture reste imposée par le type, mais une ferme apportée par un étudiant n'atteint
 * que les formateurs (`CommunityRightAiLib::RIGHTS['shareProduction']['reach']`). C'est le
 * droit qui refuse, donc le contrôle porte sur `canObserveProduction()`, que l'adresse directe du
 * plan de culture interroge, et non sur les cartes seules.
 *
 * Décor : celui de `CommunityFixtureAiTest`, dont le groupe partageant passe en formation le temps
 * d'une transaction. Les deux lecteurs du décor l'administrent, donc ce qu'ils apportent (F6, F7,
 * la ferme d'un autre associé) est apporté par l'équipe. Deux étudiants fictifs s'y ajoutent :
 * l'étudiant B apporte la ferme sans partage, dont il devient administrateur, l'étudiant A n'est
 * membre d'aucune ferme et ne lit donc que par le groupe.
 *
 * Les deux étudiants se créent hors transaction, retrouvés par leur adresse : une adresse
 * `example.org` ne reçoit rien. Tout le reste s'écrit dans la transaction de chaque méthode.
 *
 * Lancement : dev/test package=structure file=EducationReadAi
 */
class EducationReadAiTest extends CommunityFixtureAiTest {

	const EMAIL_STUDENT_A = 'zztest.etudiant.a@example.org';
	const EMAIL_STUDENT_B = 'zztest.etudiant.b@example.org';
	const EMAIL_STUDENT_JOIN = 'zztest.etudiant.entrant@example.org';

	private \user\User $eUserStudentA;
	private \user\User $eUserStudentB;
	private \user\User $eUserStudentJoin;

	public function init(): void {

		parent::init();

		$this->eUserStudentA = $this->seedStudent(self::EMAIL_STUDENT_A, 'Étudiant A');
		$this->eUserStudentB = $this->seedStudent(self::EMAIL_STUDENT_B, 'Étudiant B');
		$this->eUserStudentJoin = $this->seedStudent(self::EMAIL_STUDENT_JOIN, 'Étudiant entrant');

	}

	public function setUp(): void {

		Affiliation::model()->beginTransaction();

		$this->forget();

	}

	/**
	 * ⚠️ Les rôles du groupe vivent aussi dans Redis (`RoleLib::getCacheKey()`), que le `rollBack()`
	 * n'atteint pas : sans la purge, le rôle étudiant créé ici survivrait dans le cache à la ligne
	 * annulée.
	 */
	public function tearDown(): void {

		Affiliation::model()->rollBack();

		RoleLib::deleteCache(new Structure(['id' => $this->communitySharing]));

		$this->forget();

	}

	public function testEducation(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->placeGroup(Structure::EDUCATION);

		$cases = [
			'l\'équipe lit la ferme d\'un étudiant' => [$this->getUserOther(), $this->structureSharedWithoutProduction, TRUE],
			'un étudiant lit une ferme de l\'équipe' => [$this->eUserStudentA, self::STRUCTURE_SHARED, TRUE],
			'un étudiant lit la ferme de l\'équipe apportée par un autre membre de l\'équipe' => [$this->eUserStudentA, $this->structureOtherAssociate, TRUE],
			'un étudiant ne lit pas la ferme d\'un autre étudiant' => [$this->eUserStudentA, $this->structureSharedWithoutProduction, FALSE],
			'l\'étudiant lit sa propre ferme' => [$this->eUserStudentB, $this->structureSharedWithoutProduction, TRUE],
			'l\'étudiant qui apporte une ferme lit celles de l\'équipe' => [$this->eUserStudentB, self::STRUCTURE_SHARED, TRUE],
		];

		foreach($cases as $label => [$eUser, $structure, $expected]) {
			$this->assertEquals($expected, $this->canObserveProduction($eUser, $structure), $label);
		}

	}

	/**
	 * Les cartes de « Fermes des étudiants » suivent le même droit : la ferme d'un autre étudiant
	 * n'y figure plus, même atténuée.
	 */
	public function testEducationCards(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->placeGroup(Structure::EDUCATION);

		\user\ConnectionLib::setOnline($this->eUserStudentA);

		$eStructureCommunity = $this->getStructure($this->communitySharing);

		$cStructure = AffiliationAiLib::getStructuresByCommunity($eStructureCommunity, $this->eUserStudentA);

		$this->assertTrue($cStructure->offsetExists($this->structureSharedWithoutProduction), 'le groupe porte bien la ferme de l\'autre étudiant');

		$cStructureVisible = AffiliationAiLib::findVisible($eStructureCommunity, $cStructure);

		$this->assertTrue($cStructureVisible->offsetExists(self::STRUCTURE_SHARED), 'la ferme de l\'équipe reste en carte');
		$this->assertFalse($cStructureVisible->offsetExists($this->structureSharedWithoutProduction), 'la ferme de l\'autre étudiant n\'a plus de carte');

	}

	/**
	 * La duplication de la production est imposée en formation (décision du 25/09/2026) :
	 * la ferme d'un étudiant l'accorde à l'équipe avec une colonne à zéro, ce qu'écrit désormais un
	 * rattachement dont le formulaire ne poste plus la case. Dans un groupe de producteurs le droit
	 * n'existe pas, et la même ferme ne l'accorde pas même colonne remplie.
	 */
	public function testEducationDuplication(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$cases = [
			Structure::EDUCATION => [\Set::fromArray([]), TRUE, 'la ferme d\'un étudiant se duplique par l\'équipe, colonne à zéro'],
			Structure::PRODUCER => [\Set::fromArray([Community::STAFF]), FALSE, 'un groupe de producteurs ne duplique pas, colonne remplie'],
		];

		foreach($cases as $communityType => [$granted, $expected, $label]) {

			$this->placeGroup($communityType);

			Affiliation::model()
				->whereStructureProducer($this->structureSharedWithoutProduction)
				->whereStructureCommunity($this->communitySharing)
				->update(['shareProductionDuplicate' => $granted]);

			$this->forget();

			$eUserOther = $this->getUserOther();

			\user\ConnectionLib::setOnline($eUserOther);

			$eStructureCommunity = $this->getStructure($this->communitySharing);

			$cStructure = AffiliationAiLib::getStructuresByCommunity($eStructureCommunity, $eUserOther);

			$this->assertTrue($cStructure->offsetExists($this->structureSharedWithoutProduction), $communityType.' : le groupe porte bien la ferme');
			$this->assertEquals($expected, DuplicateAiLib::getGranted($eStructureCommunity, $cStructure[$this->structureSharedWithoutProduction])['production'], $label);

			Affiliation::model()->rollBack();
			RoleLib::deleteCache(new Structure(['id' => $this->communitySharing]));
			Affiliation::model()->beginTransaction();

		}

	}

	/**
	 * En formation, un étudiant entre sans apporter de ferme : l'invitation acceptée sans ferme crée
	 * son adhésion et aucun rattachement.
	 */
	public function testJoinWithoutFarm(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->placeGroup(Structure::EDUCATION);

		$eStructureCommunity = $this->getStructure($this->communitySharing);

		$eInvite = new Invite([
			'structure' => $eStructureCommunity,
			'type' => Invite::MEMBER,
			'email' => NULL,
			'memberRole' => RoleLib::getOne($eStructureCommunity, 'student'),
			'notifyEmail' => FALSE
		]);

		InviteLib::create($eInvite);

		$eInvite = InviteLib::getByKey($eInvite['key']);

		$this->assertTrue(InviteLib::accept($eInvite, $this->eUserStudentJoin), 'l\'invitation est acceptée sans ferme');

		$eMember = Member::model()
			->select(['status', 'role' => ['fqn']])
			->whereStructure($eStructureCommunity)
			->whereUser($this->eUserStudentJoin)
			->get();

		$this->assertEquals([Member::IN, 'student'], [$eMember['status'], $eMember['role']['fqn']], 'l\'étudiant est membre du groupe');
		$this->assertEquals(0, Affiliation::model()->whereStructureCommunity($eStructureCommunity)->whereUser($this->eUserStudentJoin)->count(), 'aucune ferme rattachée');

	}

	/**
	 * Un groupe comptable ne porte aucun des partages : chacun vaut FALSE sur la carte, et la clé
	 * existe quand même, les écrans de « Mes fermes » la lisant sur tout groupe.
	 */
	public function testAccountingSharingHasEveryKey(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->placeGroup(Structure::ACCOUNTING);

		$eUserOther = $this->getUserOther();

		\user\ConnectionLib::setOnline($eUserOther);

		$eStructureCommunity = $this->getStructure($this->communitySharing);

		$cStructure = AffiliationAiLib::getStructuresByCommunity($eStructureCommunity, $eUserOther);

		AffiliationAiLib::fillSharing($eStructureCommunity, $cStructure);

		$this->assertTrue($cStructure->offsetExists($this->structureSharedWithoutProduction), 'le groupe porte bien la ferme');
		$this->assertEquals(array_fill_keys(AffiliationAiLib::SHARES, FALSE), $cStructure[$this->structureSharedWithoutProduction]['sharing'], 'aucun partage, chaque clé posée');

	}

	/**
	 * Hors formation, rien ne change : la même ferme, apportée par le même membre ordinaire, se lit
	 * par les autres membres dès qu'elle leur est ouverte. C'est ce qui prouve que le refus du cas
	 * formation vient de la portée du type, et non du décor.
	 */
	public function testProducerUnchanged(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->placeGroup(Structure::PRODUCER);

		Affiliation::model()
			->whereStructureProducer($this->structureSharedWithoutProduction)
			->whereStructureCommunity($this->communitySharing)
			->update(['shareProduction' => \Set::fromArray([Community::STAFF, Community::MEMBER])]);

		$this->assertTrue($this->canObserveProduction($this->eUserStudentA, $this->structureSharedWithoutProduction), 'un membre lit la ferme d\'un autre membre qui la lui ouvre');

	}

	/**
	 * Pose le type du groupe, ses rôles d'enseignement et les deux étudiants, l'étudiant B apportant
	 * la ferme sans partage. C'est un état de test que le produit ne fabrique pas en un geste,
	 * d'où les écritures directes sur le type du groupe et sur l'apporteur.
	 */
	private function placeGroup(string $communityType): void {

		$eStructureCommunity = $this->getStructure($this->communitySharing);
		$eStructureCommunity['communityType'] = $communityType;

		Structure::model()->select(['communityType'])->update($eStructureCommunity);

		$eRoleStudent = new Role([
			'structure' => $eStructureCommunity,
			'name' => RoleUi::getFqn('student'),
			'fqn' => 'student',
			'productionObserve' => TRUE,
			'sections' => Role::OWN,
		]);

		RoleLib::create($eRoleStudent);

		foreach([$this->eUserStudentA, $this->eUserStudentB] as $eUser) {

			MemberLib::create(new Member([
				'user' => $eUser,
				'structure' => $eStructureCommunity,
				'role' => $eRoleStudent,
				'status' => Member::IN
			]));

		}

		$eStructureStudent = $this->getStructure($this->structureSharedWithoutProduction);

		MemberLib::create(new Member([
			'user' => $this->eUserStudentB,
			'structure' => $eStructureStudent,
			'role' => RoleLib::getOne($eStructureStudent, 'admin'),
			'status' => Member::IN
		]));

		Affiliation::model()
			->whereStructureProducer($eStructureStudent)
			->whereStructureCommunity($eStructureCommunity)
			->update(['user' => $this->eUserStudentB]);

		$this->forget();

	}

	private function canObserveProduction(\user\User $eUser, int $structure): bool {

		$this->forget();

		\user\ConnectionLib::setOnline($eUser);

		return $this->getStructure($structure)->canObserveProduction();

	}

	private function getUserOther(): \user\User {
		return \user\UserLib::getById($this->userOther, \user\User::getSelection());
	}

	/**
	 * Chaque cas change de lecteur ou de décor, et ces classes mémoïsent par processus ce qu'elles
	 * ont lu : l'adhésion du lecteur, les groupes d'une ferme, le verdict de partage.
	 */
	private function forget(): void {

		foreach([Structure::class, ConfigurationLib::class, FeatureLib::class, MemberLib::class, AffiliationAiLib::class] as $class) {

			foreach(new \ReflectionClass($class)->getProperties(\ReflectionProperty::IS_STATIC) as $property) {

				if($property->getDeclaringClass()->getName() === $class and $property->hasDefaultValue()) {
					$property->setValue(NULL, $property->getDefaultValue());
				}

			}

		}

	}

	private function seedStudent(string $email, string $lastName): \user\User {

		$eUser = \user\User::model()
			->select(\user\User::getSelection())
			->whereEmail($email)
			->get();

		if($eUser->notEmpty()) {
			return $eUser;
		}

		$eUser = new \user\User([
			'firstName' => 'zzTest',
			'lastName' => $lastName,
			'email' => $email,
			'visibility' => \user\User::PRIVATE,
			'roleCustomer' => FALSE,
			'roleProducer' => TRUE,
			'roleConsultant' => FALSE,
			'roleTeacher' => FALSE
		]);

		$fw = new \FailWatch();

		\user\UserLib::create($eUser);

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('création de « '.$lastName.' » refusée : '.json_encode($fw->get())));

		return \user\UserLib::getById($eUser['id'], \user\User::getSelection());

	}

}
?>
