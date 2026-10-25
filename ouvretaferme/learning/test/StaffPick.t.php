<?php
namespace learning;

require_once __DIR__.'/LearningNetworkFixture.t.php';

/**
 * La ligne d'encadrant d'une formation (lot C des encadrants du réseau) : elle rattache une personne
 * du réseau (`Supervisor`) à une formation, avec le rôle qu'elle y tient et la marque du contact de
 * la formation. L'action d'ajout (`learning/staff:doCreate`) a deux chemins, que `source` départage :
 * `pick` rattache une personne que le réseau connait déjà, tout autre envoi crée la personne dans le
 * réseau de la formation puis la rattache. Les règles de la personne elle-même sont dans
 * `SupervisorTest`, le contact unique d'une formation dans `StaffContactTest`.
 *
 * Ce que le fichier attend de la base, relu par `assertFixtures()` : le décor de
 * `LearningNetworkFixtureTest` (le réseau G3, dont l'utilisateur 1 est conseiller) et le groupe de
 * formation G2 (`COMMUNITY_EDUCATION`). Personnes, formations et lignes s'écrivent dans la
 * transaction de chaque méthode.
 *
 * Les groupes portent leurs propres personnes depuis la reprise du lot B : le fichier ne lit jamais
 * une liste entière, les siennes se nomment `zzTest …` et se lisent par ce préfixe.
 *
 * Lancement : dev/test package=learning file=StaffPick
 */
class StaffPickTest extends LearningNetworkFixtureTest {

	private \structure\Structure $eStructureEducation;

	protected function assertFixtures(): bool {

		if(parent::assertFixtures() === FALSE) {
			return FALSE;
		}

		$this->eStructureEducation = $this->getCommunity(self::COMMUNITY_EDUCATION);

		if($this->eStructureEducation->empty() or $this->eStructureEducation->isEducation() === FALSE) {
			$this->assertFail('le groupe de formation '.self::COMMUNITY_EDUCATION.' est absent de cette base, ou n\'est pas un groupe de formation');
			return FALSE;
		}

		return TRUE;

	}

	/**
	 * Rattacher une personne du réseau : la ligne porte la personne, le rôle saisi et le contact, à
	 * la suite des lignes de la formation. Aucune personne ne nait, et le rôle habituel de celle
	 * qu'on rattache ne change pas : le rôle saisi est celui de cette formation.
	 */
	public function testPick(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);

		$eSupervisorAda = $this->createSupervisor($this->eStructureNetwork, ['name' => 'zzTest Ada Martin', 'role' => 'conseillère', 'email' => 'ada@example.org', 'handicap' => '1']);
		$eSupervisorBob = $this->createSupervisor($this->eStructureNetwork, ['name' => 'zzTest Bob Petit']);

		$this->assertEquals([\ReloadAction::class, NULL], $this->pick($eCourse, $eSupervisorBob['id'], 'relecteur', FALSE), 'Bob rattaché');
		$this->assertEquals([\ReloadAction::class, NULL], $this->pick($eCourse, $eSupervisorAda['id'], 'formatrice', TRUE), 'Ada rattachée, et contact');

		$this->assertEquals(
			[
				[$eSupervisorBob['id'], 'zzTest Bob Petit', 'relecteur', FALSE, 0],
				[$eSupervisorAda['id'], 'zzTest Ada Martin', 'formatrice', TRUE, 1],
			],
			$this->describe($eCourse),
			'chaque ligne porte sa personne, le rôle saisi, le contact et son rang'
		);

		$this->assertEquals(2, $this->countSupervisors(), 'aucune personne de plus');
		$this->assertEquals(['conseillère', NULL], [SupervisorLib::getById($eSupervisorAda['id'])['role'], SupervisorLib::getById($eSupervisorBob['id'])['role']], 'le rôle habituel de chacune n\'a pas bougé');
		$this->assertTrue(StaffLib::getByCourse($eCourse)->last()['supervisor']['handicap'], 'la marque « Référent handicap » se lit sur la personne');

	}

	/**
	 * Une personne n'encadre une formation qu'une fois : la rattacher de nouveau est refusé, avec
	 * son message, sans que l'unique `(course, supervisor)` de la table ait à lever. Elle reste
	 * libre pour une autre formation du réseau.
	 */
	public function testDuplicate(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);
		$eCourseOther = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);

		$eSupervisorAda = $this->createSupervisor($this->eStructureNetwork, ['name' => 'zzTest Ada Martin']);

		$this->assertEquals([\ReloadAction::class, NULL], $this->pick($eCourse, $eSupervisorAda['id'], 'formatrice', FALSE), 'Ada rattachée');
		$this->assertEquals([\FailAction::class, 'Staff::supervisor.duplicate'], $this->pick($eCourse, $eSupervisorAda['id'], 'autrice', FALSE), 'une seconde fois, refusée');
		$this->assertTrue(AlertUi::getError('Staff::supervisor.duplicate') !== NULL, 'le refus a son message');
		$this->assertEquals(1, Staff::model()->whereCourse($eCourse)->count(), 'la formation garde une seule ligne');

		$this->assertEquals([\ReloadAction::class, NULL], $this->pick($eCourseOther, $eSupervisorAda['id'], 'autrice', FALSE), 'rattachée à une autre formation du réseau');
		$this->assertEquals([[$eSupervisorAda['id'], 'zzTest Ada Martin', 'autrice', FALSE, 0]], $this->describe($eCourseOther), 'avec le rôle qu\'elle y tient');
		$this->assertEquals([[$eSupervisorAda['id'], 'zzTest Ada Martin', 'formatrice', FALSE, 0]], $this->describe($eCourse), 'celui de la première n\'a pas bougé');

	}

	/**
	 * Une formation ne prend ses encadrants que dans son réseau, l'équipe Ouvretaferme comptant pour
	 * un réseau : la personne d'un autre groupe, celle de l'équipe et celle que personne ne connait
	 * sont refusées, avec un message. Aucun choix l'est aussi, par le schéma.
	 */
	public function testOwned(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);
		$eCourseTeam = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE);

		$eSupervisorNetwork = $this->createSupervisor($this->eStructureNetwork, ['name' => 'zzTest Ada Martin', 'email' => 'ada@example.org']);
		$eSupervisorEducation = $this->createSupervisor($this->eStructureEducation, ['name' => 'zzTest Claire Moulin', 'email' => 'claire@example.org']);
		$eSupervisorTeam = $this->createSupervisor(new \structure\Structure(), ['name' => 'zzTest Dora Roux', 'email' => 'dora@example.org']);

		$supervisorUnknownId = Supervisor::model()->getValue(new \Sql('MAX(id)', 'int')) + 1000;

		$this->assertEquals([\FailAction::class, 'Staff::supervisor.owned'], $this->pick($eCourse, $eSupervisorEducation['id'], 'formatrice', FALSE), 'la personne d\'un autre groupe est refusée');
		$this->assertEquals([\FailAction::class, 'Staff::supervisor.owned'], $this->pick($eCourse, $eSupervisorTeam['id'], 'formatrice', FALSE), 'celle de l\'équipe aussi');
		$this->assertEquals([\FailAction::class, 'Staff::supervisor.owned'], $this->pick($eCourseTeam, $eSupervisorNetwork['id'], 'formatrice', FALSE), 'et celle d\'un réseau, pour une formation de l\'équipe');
		$this->assertEquals([\FailAction::class, 'Staff::supervisor.owned'], $this->pick($eCourse, $supervisorUnknownId, 'formatrice', FALSE), 'une personne que personne ne connait est refusée de même');
		$this->assertEquals([\FailAction::class, 'Staff::supervisor.check'], $this->pick($eCourse, NULL, 'formatrice', FALSE), 'aucun choix est refusé par le schéma');

		// Le contact demandé sur une personne refusée ne lève pas un second refus : l'étape
		// `contact.noEmail` ne juge rien quand la personne n'a pas été retenue.
		$this->assertEquals([\FailAction::class, 'Staff::supervisor.owned'], $this->pick($eCourse, $eSupervisorEducation['id'], 'formatrice', TRUE), 'un seul refus quand le contact est demandé avec');

		foreach(['Staff::supervisor.owned', 'Staff::supervisor.check'] as $fail) {
			$this->assertTrue(AlertUi::getError($fail) !== NULL, $fail.' a son message');
		}

		$this->assertEquals([0, 0], [Staff::model()->whereCourse($eCourse)->count(), Staff::model()->whereCourse($eCourseTeam)->count()], 'aucune ligne n\'est écrite');

		$this->assertEquals([\ReloadAction::class, NULL], $this->pick($eCourseTeam, $eSupervisorTeam['id'], 'formatrice', TRUE), 'la personne de l\'équipe encadre une formation de l\'équipe');
		$this->assertEquals([\ReloadAction::class, NULL], $this->pick($eCourse, $eSupervisorNetwork['id'], 'formatrice', TRUE), 'celle du réseau une formation du réseau');

	}

	/**
	 * Le contact d'une formation donne son adresse : une personne sans adresse se rattache, mais
	 * pas comme contact. Le refus a son message, et rien n'est écrit.
	 */
	public function testContactWithoutEmail(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);

		$eSupervisorBob = $this->createSupervisor($this->eStructureNetwork, ['name' => 'zzTest Bob Petit']);

		$this->assertEquals([\FailAction::class, 'Staff::contact.noEmail'], $this->pick($eCourse, $eSupervisorBob['id'], 'formatrice', TRUE), 'contact sans adresse, refusé');
		$this->assertTrue(AlertUi::getError('Staff::contact.noEmail') !== NULL, 'le refus a son message');
		$this->assertEquals(0, Staff::model()->whereCourse($eCourse)->count(), 'aucune ligne n\'est écrite');

		$this->assertEquals([\ReloadAction::class, NULL], $this->pick($eCourse, $eSupervisorBob['id'], 'formatrice', FALSE), 'sans être contact, il se rattache');
		$this->assertTrue(SupervisorLib::getContactByCourse($eCourse)->empty(), 'la formation n\'a pas de contact');

	}

	/**
	 * L'encadrant que le réseau ne connait pas : la personne nait dans le réseau de la formation,
	 * avec pour rôle habituel le rôle saisi, puis sa ligne. Elle n'est jamais contact du réseau,
	 * même contact de la formation. Un envoi refusé ne laisse ni ligne ni personne.
	 */
	public function testNew(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);
		$eCourseTeam = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE);

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/staff:doCreate', [
			'course' => (string)$eCourse['id'],
			'name' => 'zzTest Ada Martin',
			'role' => 'formatrice',
			'description' => 'Maraichère depuis 2012.',
			'email' => 'ada@example.org',
			'handicap' => '1',
			'contact' => '1'
		]), 'Ada ajoutée, contact de la formation');

		$eStaff = StaffLib::getByCourse($eCourse)->first();
		$eSupervisor = Supervisor::model()->select(Supervisor::getSelection())->whereId($eStaff['supervisor'])->get();

		$this->assertEquals(['formatrice', TRUE, 0], [$eStaff['role'], $eStaff['contact'], $eStaff['position']], 'la ligne porte le rôle et le contact de la formation');
		$this->assertEquals(
			[self::COMMUNITY_NETWORK, 'zzTest Ada Martin', 'formatrice', 'Maraichère depuis 2012.', 'ada@example.org', TRUE, FALSE],
			[$eSupervisor['community']['id'], $eSupervisor['name'], $eSupervisor['role'], $eSupervisor['description'], $eSupervisor['email'], $eSupervisor['handicap'], $eSupervisor['contact']],
			'la personne nait dans le réseau de la formation, le rôle saisi est son rôle habituel, et elle n\'est pas contact du réseau'
		);

		$this->assertEquals([\FailAction::class, 'Staff::contact.noEmail'], $this->executePage('learning/staff:doCreate', [
			'course' => (string)$eCourse['id'],
			'name' => 'zzTest Bob Petit',
			'role' => 'relecteur',
			'description' => '',
			'email' => '',
			'contact' => '1'
		]), 'un contact sans adresse est refusé');
		$this->assertEquals([\FailAction::class, 'Staff::role.check'], $this->executePage('learning/staff:doCreate', [
			'course' => (string)$eCourse['id'],
			'name' => 'zzTest Bob Petit',
			'role' => '',
			'description' => '',
			'email' => ''
		]), 'un rôle vide aussi, la ligne en exigeant un que la personne n\'exige pas');
		$this->assertEquals([\FailAction::class, 'Supervisor::name.check'], $this->executePage('learning/staff:doCreate', [
			'course' => (string)$eCourse['id'],
			'name' => '',
			'role' => 'relecteur',
			'description' => '',
			'email' => ''
		]), 'un nom vide de même');

		foreach(['Staff::role.check', 'Supervisor::name.check', 'Supervisor::role.check', 'Supervisor::description.check', 'Supervisor::email.check'] as $fail) {
			$this->assertTrue(AlertUi::getError($fail) !== NULL, $fail.' a son message');
		}

		$this->assertEquals([1, 1], [Staff::model()->whereCourse($eCourse)->count(), $this->countSupervisors()], 'un envoi refusé ne laisse ni ligne ni personne');

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/staff:doCreate', [
			'course' => (string)$eCourseTeam['id'],
			'name' => 'zzTest Dora Roux',
			'role' => 'autrice',
			'description' => '',
			'email' => ''
		]), 'Dora ajoutée à une formation de l\'équipe');

		$this->assertTrue(StaffLib::getByCourse($eCourseTeam)->first()['supervisor']['community']->empty(), 'elle nait chez l\'équipe');

	}

	/**
	 * La liste que le panneau d'ajout proposera : les personnes du réseau de la formation qui ne
	 * l'encadrent pas encore, par nom et indexées par leur identifiant. Celles d'un autre groupe et
	 * de l'équipe n'y sont jamais.
	 */
	public function testForCourse(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);
		$eCourseOther = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);
		$eCourseTeam = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE);

		$eSupervisorBob = $this->createSupervisor($this->eStructureNetwork, ['name' => 'zzTest Bob Petit']);
		$eSupervisorAda = $this->createSupervisor($this->eStructureNetwork, ['name' => 'zzTest Ada Martin']);
		$this->createSupervisor($this->eStructureEducation, ['name' => 'zzTest Claire Moulin']);
		$eSupervisorDora = $this->createSupervisor(new \structure\Structure(), ['name' => 'zzTest Dora Roux']);

		$names = function(Course $eCourse): array {

			$cSupervisor = SupervisorLib::getForCourse($eCourse)->find(fn(Supervisor $eSupervisor) => str_starts_with($eSupervisor['name'], 'zzTest '));

			return array_combine($cSupervisor->getKeys(), $cSupervisor->getColumn('name'));

		};

		$this->assertEquals([$eSupervisorAda['id'] => 'zzTest Ada Martin', $eSupervisorBob['id'] => 'zzTest Bob Petit'], $names($eCourse), 'les personnes du réseau, par nom, indexées par leur identifiant');
		$this->assertTrue(array_keys($names($eCourse)) === [$eSupervisorAda['id'], $eSupervisorBob['id']], 'dans cet ordre');
		$this->assertEquals([$eSupervisorDora['id'] => 'zzTest Dora Roux'], $names($eCourseTeam), 'pour une formation de l\'équipe, celles de l\'équipe');

		$this->pick($eCourse, $eSupervisorAda['id'], 'formatrice', FALSE);

		$this->assertEquals([$eSupervisorBob['id'] => 'zzTest Bob Petit'], $names($eCourse), 'celle qui encadre déjà la formation n\'y est plus');
		$this->assertEquals([$eSupervisorAda['id'] => 'zzTest Ada Martin', $eSupervisorBob['id'] => 'zzTest Bob Petit'], $names($eCourseOther), 'elle reste proposée à une autre formation du réseau');

	}

	/**
	 * Les formations d'une personne, chacune avec le rôle qu'elle y tient, par titre.
	 */
	public function testBySupervisor(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eSupervisorAda = $this->createSupervisor($this->eStructureNetwork, ['name' => 'zzTest Ada Martin', 'email' => 'ada@example.org']);
		$eSupervisorBob = $this->createSupervisor($this->eStructureNetwork, ['name' => 'zzTest Bob Petit']);

		$this->assertTrue(StaffLib::getBySupervisor($eSupervisorAda)->empty(), 'aucune formation tant qu\'elle n\'en encadre pas');

		$eCourseVerger = $this->rename($this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE), 'zzTest Verger');
		$eCourseCompost = $this->rename($this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE), 'zzTest Compost');

		$this->pick($eCourseVerger, $eSupervisorAda['id'], 'formatrice', FALSE);
		$this->pick($eCourseVerger, $eSupervisorBob['id'], 'relecteur', FALSE);
		$this->pick($eCourseCompost, $eSupervisorAda['id'], 'autrice', TRUE);

		$list = [];

		foreach(StaffLib::getBySupervisor($eSupervisorAda) as $eStaff) {
			$list[] = [$eStaff['course']['id'], $eStaff['course']['name'], $eStaff['course']['status'], $eStaff['role'], $eStaff['contact']];
		}

		$this->assertEquals(
			[
				[$eCourseCompost['id'], 'zzTest Compost', Course::PUBLISHED, 'autrice', TRUE],
				[$eCourseVerger['id'], 'zzTest Verger', Course::DRAFT, 'formatrice', FALSE],
			],
			$list,
			'ses deux formations, par titre, chacune avec son rôle et son contact'
		);

	}

	/**
	 * La personne à qui s'adresser pour une formation : celle de la ligne contact, avec son nom et
	 * son adresse, sinon le contact de son réseau, qu'il l'encadre ou non, et un élément vide quand
	 * ni l'une ni l'autre n'en désigne.
	 */
	public function testContactByCourse(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);

		$eSupervisorAda = $this->createSupervisor($this->eStructureNetwork, ['name' => 'zzTest Ada Martin', 'email' => 'ada@example.org']);
		$eSupervisorBob = $this->createSupervisor($this->eStructureNetwork, ['name' => 'zzTest Bob Petit', 'email' => 'bob@example.org']);

		$this->pick($eCourse, $eSupervisorBob['id'], 'relecteur', FALSE);

		if(SupervisorLib::getContactByCommunity($this->eStructureNetwork)->notEmpty()) {
			$this->assertFail('le réseau '.self::COMMUNITY_NETWORK.' a un contact du réseau dans cette base, l\'élément vide ne peut pas se lire');
			return;
		}

		$this->assertTrue(SupervisorLib::getContactByCourse($eCourse)->empty(), 'sans contact, un élément vide');

		$eSupervisorDiane = $this->createSupervisor($this->eStructureNetwork, ['name' => 'zzTest Diane Faure', 'email' => 'diane@example.org', 'contact' => '1']);
		$eCourseForeign = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE);

		$eSupervisorContact = SupervisorLib::getContactByCourse($eCourse);

		$this->assertEquals([$eSupervisorDiane['id'], 'zzTest Diane Faure', 'diane@example.org'], [$eSupervisorContact['id'], $eSupervisorContact['name'], $eSupervisorContact['email']], 'sans contact désigné, le contact du réseau, qui n\'encadre pas la formation');
		$this->assertFalse(SupervisorLib::getContactByCourse($this->getCourse($eCourseForeign['id']))->is($eSupervisorDiane), 'qui ne vaut pas pour une formation d\'un autre auteur');

		$this->pick($eCourse, $eSupervisorAda['id'], 'formatrice', TRUE);

		$eSupervisorContact = SupervisorLib::getContactByCourse($eCourse);

		$this->assertEquals([$eSupervisorAda['id'], 'zzTest Ada Martin', 'ada@example.org'], [$eSupervisorContact['id'], $eSupervisorContact['name'], $eSupervisorContact['email']], 'la personne de la ligne contact, son nom et son adresse');

	}

	/**
	 * Aucune clé étrangère ne relie la ligne à sa personne : `StaffLib::create()` relit la personne
	 * sous verrou et n'écrit pas la ligne d'une personne disparue. Le refus a son message, et la
	 * transaction se referme : une écriture qui suit passe.
	 */
	public function testSupervisorGone(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);

		$eSupervisorAda = $this->createSupervisor($this->eStructureNetwork, ['name' => 'zzTest Ada Martin']);
		$eSupervisorBob = $this->createSupervisor($this->eStructureNetwork, ['name' => 'zzTest Bob Petit']);

		$eStaff = new Staff([
			'course' => $eCourse,
			'cStaff' => StaffLib::getByCourse($eCourse),
			'cSupervisor' => SupervisorLib::getForCourse($eCourse)
		]);

		$fw = new \FailWatch();

		$eStaff->build(StaffLib::getPropertiesCreate(), ['supervisor' => (string)$eSupervisorAda['id'], 'role' => 'formatrice', 'contact' => '0'], new \PropertyList('create'));

		$this->assertEquals([], $this->getFails($fw), 'la ligne est acceptée tant que la personne existe');

		// La personne part entre la validation et l'écriture, comme le ferait une suppression
		// concurrente depuis la liste du réseau.
		SupervisorLib::delete($eSupervisorAda);

		$states = \ModuleModel::getTransactionStates();

		StaffLib::create($eStaff);

		$this->assertEquals(['Staff::supervisor.check'], array_column($this->getFails($fw), 0), 'la ligne d\'une personne disparue est refusée');
		$this->assertEquals(0, Staff::model()->whereCourse($eCourse)->count(), 'et ne s\'écrit pas');
		$this->assertEquals($states, \ModuleModel::getTransactionStates(), 'la pile de transactions est rendue telle qu\'elle a été prise');

		$this->assertEquals([\ReloadAction::class, NULL], $this->pick($eCourse, $eSupervisorBob['id'], 'formatrice', FALSE), 'une écriture qui suit passe');
		$this->assertEquals(1, Staff::model()->whereCourse($eCourse)->count(), 'et sa ligne est là');

	}

	/**
	 * Une personne par le chemin que prendra son panneau : l'élément du Lib, `build()` sur sa
	 * liste, puis `SupervisorLib::create()`. Rendue relue.
	 */
	private function createSupervisor(\structure\Structure $eStructure, array $input): Supervisor {

		$eSupervisor = SupervisorLib::getCreateElement($eStructure);

		$fw = new \FailWatch();

		$eSupervisor->build(SupervisorLib::getPropertiesCreate(), $input + ['role' => '', 'description' => '', 'email' => ''], new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('personne '.$input['name'].' refusée : '.json_encode($this->getFails($fw))));

		SupervisorLib::create($eSupervisor);

		return SupervisorLib::getById($eSupervisor['id']);

	}

	/**
	 * L'envoi du chemin « un encadrant du réseau » : la personne choisie, son rôle dans la
	 * formation, et le contact, dont le oui / non poste toujours une valeur.
	 */
	private function pick(Course $eCourse, ?int $supervisor, string $role, bool $contact): array {

		return $this->executePage('learning/staff:doCreate', [
			'course' => (string)$eCourse['id'],
			'source' => 'pick',
			'supervisor' => (string)$supervisor,
			'role' => $role,
			'contact' => $contact ? '1' : '0'
		]);

	}

	/**
	 * @return array<int, array{0: int, 1: string, 2: string, 3: bool, 4: int}> les lignes d'une
	 * formation dans leur ordre : la personne, son nom, le rôle, le contact, le rang
	 */
	private function describe(Course $eCourse): array {

		$list = [];

		foreach(StaffLib::getByCourse($eCourse) as $eStaff) {
			$list[] = [$eStaff['supervisor']['id'], $eStaff['supervisor']['name'], $eStaff['role'], $eStaff['contact'], $eStaff['position']];
		}

		return $list;

	}

	private function countSupervisors(): int {

		return Supervisor::model()
			->whereName('LIKE', 'zzTest %')
			->count();

	}

	/**
	 * Le titre d'une formation du décor, par `build()` puis `CourseLib::update()` : `createCourse()`
	 * les nomme toutes pareil, et le tri par titre a besoin de deux titres.
	 */
	private function rename(Course $eCourse, string $name): Course {

		$fw = new \FailWatch();

		$eCourse->build(['name'], ['name' => $name]);

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('titre refusé : '.json_encode($this->getFails($fw))));

		CourseLib::update($eCourse, ['name']);

		return $eCourse;

	}

}
?>
