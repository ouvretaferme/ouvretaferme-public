<?php
namespace learning;

require_once __DIR__.'/LearningNetworkFixture.t.php';

/**
 * La liste des encadrants d'un réseau (`/groupe/{id}/formations/encadrants`), celle de l'équipe
 * Ouvretaferme dans son administration, et le panneau d'une personne (lot des encadrants du réseau) :
 * qui y entre, ce que porte une ligne, et les gestes d'ajout, de correction et de suppression par
 * leurs routes. Le statut et son interrupteur sont dans `SupervisorStatus.t.php`, la roue qui mène à
 * la liste dans `CourseNetworkTable.t.php` et `CommunityFeature.t.php`.
 *
 * Le réseau G3 du décor (`LearningNetworkFixtureTest`) : l'utilisateur 1 y est conseiller, donc de
 * l'équipe sans gérer le réseau ; le lecteur fictif n'en est pas. Tout s'écrit par les routes, dans
 * la transaction.
 *
 * Lancement : dev/test package=learning file=SupervisorList
 */
class SupervisorListTest extends LearningNetworkFixtureTest {

	/**
	 * Toute l'équipe du réseau entre dans la liste et ses panneaux, personne d'autre ; la liste de
	 * l'équipe Ouvretaferme est réservée à ses administrateurs.
	 */
	public function testAccess(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$id = (string)$this->eStructureNetwork['id'];

		$eSupervisor = $this->createSupervisor($this->eStructureNetwork, 'zzTest Ada Martin', 'ada@example.org');

		$data = $this->executeView('groupe/'.$id.'/formations/encadrants', []);

		if($this->assertTrue($data instanceof \stdClass, 'un conseiller du réseau ouvre la liste') === FALSE) {
			return;
		}

		$this->assertTrue($data->cSupervisor->contains(fn(Supervisor $e) => $e['id'] === $eSupervisor['id']), 'qui porte la personne du réseau');
		$this->assertTrue($this->executeView('learning/supervisor:create', ['community' => $id]) instanceof \stdClass, 'il ouvre le panneau d\'ajout');
		$this->assertTrue($this->executeView('learning/supervisor:update', ['id' => (string)$eSupervisor['id']]) instanceof \stdClass, 'et celui de la personne');
		$this->assertEquals(\NotExistsAction::class, $this->executeView('learning/supervisor:create', ['community' => '999999999']), 'un groupe inconnu ne se lit pas comme l\'équipe');

		$data = $this->executeView('learning/admin/encadrants', []);

		if($this->assertTrue($data instanceof \stdClass, 'un administrateur ouvre la liste de l\'équipe') === FALSE) {
			return;
		}

		$this->assertFalse($data->cSupervisor->contains(fn(Supervisor $e) => $e['community']->notEmpty()), 'qui ne porte aucune personne d\'un groupe');
		$this->assertTrue(str_contains(CourseUi::getAdminTabs('supervisors'), '<a href="/learning/admin/encadrants"'), 'son onglet dans l\'administration des formations');

		$this->actAs($this->eUserReader);

		$this->assertEquals(\NotAllowedAction::class, $this->executeView('groupe/'.$id.'/formations/encadrants', []), 'qui n\'est pas du réseau n\'ouvre pas sa liste');
		$this->assertEquals(\NotAllowedAction::class, $this->executeView('learning/supervisor:create', ['community' => $id]), 'ni le panneau d\'ajout');
		$this->assertEquals(\NotAllowedAction::class, $this->executeView('learning/supervisor:update', ['id' => (string)$eSupervisor['id']]), 'ni celui d\'une personne');
		$this->assertEquals(\NotAllowedAction::class, $this->executeView('learning/supervisor:create', []), 'ni l\'ajout d\'une personne de l\'équipe');
		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('learning/supervisor:doDelete', ['id' => (string)$eSupervisor['id']]), 'et ne supprime personne');

		// `validateAdmin()` lève `DisabledPage`, qui n'est pas une action : `executeView()` ne l'attrape pas.
		try {
			$this->executeView('learning/admin/encadrants', []);
			$this->assertFail('la liste de l\'équipe s\'ouvre à un compte qui n\'administre pas Ouvretaferme');
		} catch(\DisabledPage) {
			$this->assertTrue(TRUE, 'la liste de l\'équipe lui est fermée');
		}

	}

	/**
	 * Une ligne dit la personne et le nombre de ses formations. Son interrupteur poste le statut
	 * inverse. La suppression n'est ouverte qu'à une personne sans formation.
	 */
	public function testRow(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);

		$eSupervisorAda = $this->createSupervisor($this->eStructureNetwork, 'zzTest Ada <b>Martin</b>', 'ada.martin@reseau-exemple.org', role: 'formatrice', description: 'Maraîchère dans la Drôme.', handicap: TRUE, contact: TRUE);
		$eSupervisorBob = $this->createSupervisor($this->eStructureNetwork, 'zzTest Bob Petit', '');

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/staff:doCreate', [
			'course' => (string)$eCourse['id'],
			'source' => 'pick',
			'supervisor' => (string)$eSupervisorAda['id'],
			'role' => 'formatrice',
			'contact' => '0'
		]), 'Ada encadre une formation');

		$cSupervisor = SupervisorLib::getByCommunity($this->eStructureNetwork);

		$ui = new SupervisorUi();

		$rowAda = $ui->getRow($cSupervisor->find(fn(Supervisor $e) => $e['id'] === $eSupervisorAda['id'], limit: 1));
		$rowBob = $ui->getRow($cSupervisor->find(fn(Supervisor $e) => $e['id'] === $eSupervisorBob['id'], limit: 1));

		$this->assertTrue(str_contains($rowAda, 'id="supervisor-row-'.$eSupervisorAda['id'].'"') and str_contains($rowAda, 'zzTest Ada &lt;b&gt;Martin&lt;/b&gt;'), 'la ligne se cible, le nom encodé : '.$rowAda);

		$courses = $cSupervisor->find(fn(Supervisor $e) => in_array($e['id'], [$eSupervisorAda['id'], $eSupervisorBob['id']]))->getColumn('courses', 'id');
		$this->assertEquals([$eSupervisorAda['id'] => 1, $eSupervisorBob['id'] => 0], $courses, 'le nombre de formations de chacun');

		$this->assertTrue(str_contains($rowAda, 'data-ajax="/learning/supervisor:doUpdateStatus" post-id="'.$eSupervisorAda['id'].'" post-status="'.Supervisor::INACTIVE.'"'), 'l\'interrupteur allumé, qui poste le statut inverse');

		$this->assertTrue(str_contains($rowAda, '<a href="/learning/supervisor:update?id='.$eSupervisorAda['id'].'"'), 'la roue ouvre le panneau de la personne');

		$this->assertFalse(str_contains($rowAda, 'supervisor:doDelete'), 'la suppression fermée ne poste rien');

		$this->assertTrue(str_contains($rowBob, '<a data-ajax="/learning/supervisor:doDelete" post-id="'.$eSupervisorBob['id'].'"') and str_contains($rowBob, 'data-confirm="'), 'sans formation, la suppression est ouverte et se confirme');

		$this->executePage('learning/supervisor:doUpdateStatus', ['id' => (string)$eSupervisorBob['id'], 'status' => Supervisor::INACTIVE]);

		$cSupervisor = SupervisorLib::getByCommunity($this->eStructureNetwork);
		$eSupervisorBob = $cSupervisor->find(fn(Supervisor $e) => $e['id'] === $eSupervisorBob['id'], limit: 1, default: new Supervisor());

		if($this->assertTrue($eSupervisorBob->notEmpty(), 'la liste garde une personne désactivée') === FALSE) {
			return;
		}

		$rowBob = $ui->getRow($eSupervisorBob);

		$this->assertTrue(str_contains($rowBob, 'post-status="'.Supervisor::ACTIVE.'"'), 'son interrupteur éteint, qui la réactive : '.$rowBob);

		$this->assertTrue($ui->getList($cSupervisor) !== '', 'la liste se rend');

		$title = $ui->getCommunityTitle($this->eStructureNetwork, $cSupervisor);

		$this->assertTrue(str_contains($title, '<a href="/groupe/'.$this->eStructureNetwork['id'].'/formations"'), 'le titre revient aux formations');
		$this->assertTrue(str_contains($title, '<a href="/learning/supervisor:create?community='.$this->eStructureNetwork['id'].'"'), 'et ouvre l\'ajout dans ce réseau');

	}

	/**
	 * Le panneau de la personne : ce qui vaut dans toutes ses formations, puis la liste de celles
	 * qu'elle encadre, chacune menant à son éditeur. Il ne porte pas le statut. Celui de l'ajout
	 * garde le groupe où la personne nait.
	 */
	public function testPanel(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$id = (string)$this->eStructureNetwork['id'];

		$data = $this->executeView('learning/supervisor:create', ['community' => $id]);
		$panel = new SupervisorUi()->create($data->e)->body;

		$this->assertTrue((bool)preg_match('#<input[^>]*name="community"[^>]*value="'.$id.'"#', $panel), 'l\'ajout garde le réseau : '.$panel);
		$this->assertEquals(['contact', 'description', 'email', 'handicap', 'name', 'role'], $this->getWrappers($panel), 'les champs de la personne');
		$this->assertFalse(str_contains($panel, 'name="status"'), 'pas de statut');

		$dataTeam = $this->executeView('learning/supervisor:create', []);

		$this->assertFalse(str_contains(new SupervisorUi()->create($dataTeam->e)->body, 'name="community"'), 'une personne de l\'équipe nait sans groupe');

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);
		$eSupervisor = $this->createSupervisor($this->eStructureNetwork, 'zzTest Ada Martin', 'ada@example.org');

		$data = $this->executeView('learning/supervisor:update', ['id' => (string)$eSupervisor['id']]);
		$panel = new SupervisorUi()->update($data->e, $data->e['cStaff'])->body;

		$this->assertEquals(['contact', 'description', 'email', 'handicap', 'name', 'role'], $this->getWrappers($panel), 'sans formation, les seuls champs de la personne');
		$this->assertFalse(str_contains($panel, CourseUi::urlEdition($eCourse)), 'et pas de liste de formations');

		$this->executePage('learning/staff:doCreate', [
			'course' => (string)$eCourse['id'],
			'source' => 'pick',
			'supervisor' => (string)$eSupervisor['id'],
			'role' => 'formatrice',
			'contact' => '0'
		]);

		$data = $this->executeView('learning/supervisor:update', ['id' => (string)$eSupervisor['id']]);
		$panel = new SupervisorUi()->update($data->e, $data->e['cStaff'])->body;

		$this->assertTrue(str_contains($panel, '<a href="'.CourseUi::urlEdition($eCourse).'"'), 'la formation qu\'elle encadre, qui mène à son éditeur : '.$panel);
		$this->assertFalse(str_contains($panel, 'name="status"') or str_contains($panel, 'supervisor:doUpdateStatus'), 'le statut ne se change pas ici');

	}

	/**
	 * Les gestes par leurs routes : l'ajout, la correction, puis la suppression, refusée avec son
	 * message tant que la personne encadre une formation.
	 */
	public function testWrite(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);
		$eSupervisor = $this->createSupervisor($this->eStructureNetwork, 'zzTest Ada Martin', 'ada@example.org');

		$this->assertEquals([$this->eStructureNetwork['id'], Supervisor::ACTIVE, FALSE], [$eSupervisor['community']['id'], $eSupervisor['status'], $eSupervisor['contact']], 'la personne nait dans le réseau, active, sans être son contact');
		$this->assertTrue(AlertUi::getSuccess('Supervisor::created') !== NULL and AlertUi::getSuccess('Supervisor::updated') !== NULL and AlertUi::getSuccess('Supervisor::deleted') !== NULL, 'chaque geste a son message');

		$this->assertEquals([\FailAction::class, 'Supervisor::contact.noEmail'], $this->executePage('learning/supervisor:doUpdate', [
			'id' => (string)$eSupervisor['id'],
			'name' => 'zzTest Ada Martin',
			'role' => '',
			'description' => '',
			'email' => '',
			'handicap' => '0',
			'contact' => '1'
		]), 'un contact du réseau sans adresse est refusé');

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/supervisor:doUpdate', [
			'id' => (string)$eSupervisor['id'],
			'name' => 'zzTest Ada Dupont',
			'role' => 'autrice',
			'description' => 'Maraîchère.',
			'email' => 'ada@example.org',
			'handicap' => '1',
			'contact' => '1'
		]), 'la fiche se corrige');

		$eSupervisor = SupervisorLib::getById($eSupervisor['id']);

		$this->assertEquals(['zzTest Ada Dupont', 'autrice', 'Maraîchère.', TRUE, TRUE], [$eSupervisor['name'], $eSupervisor['role'], $eSupervisor['description'], $eSupervisor['handicap'], $eSupervisor['contact']], 'chaque champ est écrit');

		$this->executePage('learning/staff:doCreate', [
			'course' => (string)$eCourse['id'],
			'source' => 'pick',
			'supervisor' => (string)$eSupervisor['id'],
			'role' => 'formatrice',
			'contact' => '0'
		]);

		$this->assertEquals([\FailAction::class, 'Supervisor::deleteLinked'], $this->executePage('learning/supervisor:doDelete', ['id' => (string)$eSupervisor['id']]), 'une personne qui encadre une formation ne se supprime pas, même par un envoi forgé');
		$this->assertTrue(AlertUi::getError('Supervisor::deleteLinked') !== NULL, 'le refus a son message');

		$eStaff = StaffLib::getByCourse($eCourse)->first();

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/staff:doDelete', ['id' => (string)$eStaff['id']]), 'retirée de sa formation');
		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/supervisor:doDelete', ['id' => (string)$eSupervisor['id']]), 'elle se supprime');
		$this->assertTrue(SupervisorLib::getById($eSupervisor['id'])->empty(), 'et n\'est plus en base');

	}

	/**
	 * Un cas par type de groupe, le réseau, le groupe de formation et l'équipe Ouvretaferme : le
	 * panneau de la personne et celui de l'ajout d'une formation s'ouvrent, et la ligne « Contact »
	 * porte le contact du groupe que la formation n'a pas désigné. Une formation de l'équipe sans
	 * contact n'a pas de ligne, donc pas d'adresse.
	 */
	public function testCommunityTexts(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eStructureEducation = $this->getCommunity(self::COMMUNITY_EDUCATION);

		if($this->assertTrue($eStructureEducation->isEducation() and $this->eStructureNetwork->isNetwork(), 'le décor porte un groupe de formation et un réseau') === FALSE) {
			return;
		}

		foreach([
			'un réseau' => $this->eStructureNetwork,
			'un groupe de formation' => $eStructureEducation,
			'l\'équipe' => new \structure\Structure(),
		] as $case => $eStructure) {

			$data = $this->executeView('learning/supervisor:create', $eStructure->empty() ? [] : ['community' => (string)$eStructure['id']]);

			if($this->assertTrue($data instanceof \stdClass, $case.' : le panneau de la personne s\'ouvre') === FALSE) {
				continue;
			}

			$eCourse = $this->createCourse($eStructure, Course::DRAFT, Course::PRIVATE);
			$eSupervisor = $this->createSupervisor($eStructure, 'zzTest Ada Martin', 'ada@example.org', contact: TRUE);

			$data = $this->executeView('learning/staff:create', ['course' => (string)$eCourse['id']]);

			if($this->assertTrue($data instanceof \stdClass, $case.' : le panneau d\'ajout d\'une formation s\'ouvre') === FALSE) {
				continue;
			}

			$eCourseRead = $this->getCourse($eCourse['id']);
			$eCourseRead['eSupervisorContact'] = SupervisorLib::getContactByCourse($eCourseRead);

			$this->assertTrue(str_contains(StaffUi::getContact($eCourseRead, new \Collection(), TRUE), '<a href="mailto:ada@example.org">'), $case.' : la ligne Contact porte le contact que la formation n\'a pas désigné');
			$this->assertTrue($eCourseRead['eSupervisorContact']->is($eSupervisor), $case.' : et c\'est la personne posée');

			// Sans contact, en mémoire : la base de dev peut en porter un pour ce groupe.
			$eCourseRead['eSupervisorContact'] = new Supervisor();

			$address = StaffUi::getContact($eCourseRead, new \Collection(), TRUE);

			if($eStructure->empty()) {
				$this->assertEquals('', $address, $case.' : sans contact, pas de ligne');
			} else {
				$this->assertTrue($address !== '', $case.' : sans contact, l\'adresse légale du groupe');
			}

		}

	}

	/**
	 * Une personne par le panneau d'ajout de la liste, chez l'équipe quand le groupe est vide. Rendue
	 * relue.
	 */
	private function createSupervisor(\structure\Structure $eStructure, string $name, string $email, string $role = '', string $description = '', bool $handicap = FALSE, bool $contact = FALSE): Supervisor {

		$result = $this->executePage('learning/supervisor:doCreate', ($eStructure->empty() ? [] : ['community' => (string)$eStructure['id']]) + [
			'name' => $name,
			'role' => $role,
			'description' => $description,
			'email' => $email,
			'handicap' => $handicap ? '1' : '0',
			'contact' => $contact ? '1' : '0'
		]);

		$this->assertEquals([\ReloadAction::class, NULL], $result, $name.' ajoutée au réseau');

		return Supervisor::model()
			->select(Supervisor::getSelection())
			->whereCommunity($eStructure->empty() ? NULL : $eStructure)
			->whereName($name)
			->get();

	}

	/**
	 * @return list<string> les champs d'un panneau, triés
	 */
	private function getWrappers(string $panel): array {

		preg_match_all('#data-wrapper="([a-zA-Z]+)"#', $panel, $matches);
		sort($matches[1]);

		return $matches[1];

	}

}
?>
