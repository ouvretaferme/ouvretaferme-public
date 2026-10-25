<?php
namespace learning;

require_once __DIR__.'/LearningNetworkFixture.t.php';

/**
 * « M'ajouter comme encadrant » (lot D des commentaires, verdict du 08/10, question 4 B) :
 * tant qu'une formation n'a aucun encadrant, son onglet invite l'autrice à s'ajouter. Le bouton ouvre
 * le panneau « Ajouter un encadrant » (`learning/staff:create`, `origin=self`) avec ce que le compte
 * sait d'elle, et rien ne s'écrit avant l'envoi : le rôle reste à saisir. Quand le réseau de la
 * formation a déjà sa fiche active, reconnue par l'adresse du compte, le panneau s'ouvre sur elle,
 * et quand cette fiche est désactivée il s'ouvre vide, pour ne pas pousser à en créer une seconde.
 *
 * Ce que le fichier attend de la base, relu par `assertFixtures()` : le décor de
 * `LearningNetworkFixtureTest` (le réseau G3, dont l'utilisateur 1 est conseiller) et le groupe de
 * formation G2. Formations, fiches et lignes s'écrivent dans la transaction de chaque méthode. Les
 * comptes des cas de reconnaissance sont montés à la main, en `example.org` : `prepareSelf()` ne lit
 * d'eux que le nom et l'adresse.
 *
 * Lancement : dev/test package=learning file=StaffSelf
 */
class StaffSelfTest extends LearningNetworkFixtureTest {

	const EMAIL = 'zztest.marion-lefevre@example.org';

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
	 * L'onglet d'une formation sans encadrant porte le geste qui ouvre le panneau d'ajout sur soi, à
	 * côté de « Ajouter un encadrant ». Dès qu'un encadrant est là, le geste s'en va.
	 */
	public function testButton(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);

		$section = $this->getSection($eCourse);

		$this->assertTrue(str_contains($section, 'href="/learning/staff:create?course='.$eCourse['id'].'&origin=self"'), 'sans encadrant, le geste pour s\'ajouter : '.$section);
		$this->assertTrue(str_contains($section, 'href="/learning/staff:create?course='.$eCourse['id'].'"'), '« Ajouter un encadrant » reste, sans origine');

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/staff:doCreate', [
			'course' => (string)$eCourse['id'],
			'name' => 'zzTest Ada Martin',
			'role' => 'formatrice',
			'description' => '',
			'email' => '',
			'handicap' => '0',
			'contact' => '0'
		]), 'Ada ajoutée');

		$this->assertEquals(1, $this->executeView('formation/'.$eCourse['id'].'/edition', [])->cStaff->count(), 'Ada encadre la formation');
		$this->assertFalse(str_contains($this->getSection($eCourse), 'origin=self'), 'avec un encadrant, plus de geste pour s\'ajouter');

	}

	/**
	 * Le réseau ne connait pas le compte : le nom et l'adresse du compte sont posés sur la personne à
	 * saisir, le rôle reste vide. Un compte sans prénom donne son nom seul, un compte sans adresse
	 * son nom seul aussi, sans jamais prendre la fiche d'une personne qui n'a pas d'adresse.
	 */
	public function testPrepareNew(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);

		// Une fiche sans adresse, qu'une recherche sur une adresse vide ramasserait.
		$this->createSupervisor($this->eStructureNetwork, ['name' => 'zzTest Bob Petit', 'role' => 'relecteur']);

		[$eStaff, $eSupervisor] = $this->prepare($eCourse, new \user\User(['firstName' => 'Marion', 'lastName' => 'Lefèvre', 'email' => self::EMAIL]));

		$this->assertEquals(['Marion Lefèvre', self::EMAIL], [$eSupervisor['name'], $eSupervisor['email']], 'le nom et l\'adresse du compte sont posés');
		$this->assertTrue($eStaff['supervisor']->empty(), 'aucune fiche n\'est choisie');
		$this->assertFalse($eStaff->offsetExists('role'), 'le rôle reste à écrire');

		[$eStaff, $eSupervisor] = $this->prepare($eCourse, new \user\User(['firstName' => NULL, 'lastName' => 'Lefèvre', 'email' => self::EMAIL]));

		$this->assertEquals('Lefèvre', $eSupervisor['name'], 'sans prénom, le nom seul');

		[$eStaff, $eSupervisor] = $this->prepare($eCourse, new \user\User(['firstName' => 'Marion', 'lastName' => 'Lefèvre', 'email' => NULL]));

		$this->assertEquals('Marion Lefèvre', $eSupervisor['name'], 'sans adresse, le nom est posé');
		$this->assertFalse($eSupervisor->offsetExists('email'), 'et aucune adresse');
		$this->assertTrue($eStaff['supervisor']->empty(), 'la fiche sans adresse du réseau n\'est pas prise pour la sienne');

	}

	/**
	 * Le réseau a déjà la fiche active du compte, reconnue par son adresse : la ligne s'ouvre sur
	 * elle et la personne à saisir reste vide. Son rôle habituel ne suit que cette fiche : il remplit
	 * le rôle du chemin du choix comme un rôle proposé (`data-proposed`, que
	 * `LearningStaff.proposeRole()` remplace quand on choisit quelqu'un d'autre), jamais celui d'une
	 * personne nouvelle, et ne se pose pas sur la ligne. La reconnaissance ne sort pas du réseau de la
	 * formation. Une fiche désactivée, ou qui encadre déjà la formation, n'est pas choisie et ferme
	 * aussi la saisie du compte en personne nouvelle, qui ferait un doublon à la même adresse.
	 */
	public function testPrepareKnown(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eUser = new \user\User(['firstName' => 'Marion', 'lastName' => 'Lefèvre', 'email' => self::EMAIL]);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);
		$eCourseTeam = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE);

		// La même adresse dans un autre groupe et chez l'équipe : le réseau, lui, ne la connait pas.
		$this->createSupervisor($this->eStructureEducation, ['name' => 'zzTest Marion Ailleurs', 'role' => 'autrice', 'email' => self::EMAIL]);
		$eSupervisorTeam = $this->createSupervisor(new \structure\Structure(), ['name' => 'zzTest Marion Équipe', 'role' => 'animatrice', 'email' => self::EMAIL]);

		[$eStaff, $eSupervisor] = $this->prepare($eCourse, $eUser);

		$this->assertTrue($eStaff['supervisor']->empty(), 'la fiche d\'un autre groupe n\'est pas reconnue');
		$this->assertEquals('Marion Lefèvre', $eSupervisor['name'], 'le compte se saisit alors comme une personne nouvelle');

		[$eStaff, $eSupervisor] = $this->prepare($eCourseTeam, $eUser);

		$this->assertEquals([$eSupervisorTeam['id'], 'animatrice'], [$eStaff['supervisor']['id'] ?? NULL, $eStaff['supervisor']['role'] ?? NULL], 'une formation de l\'équipe reconnait la fiche de l\'équipe');

		$eSupervisorSelf = $this->createSupervisor($this->eStructureNetwork, ['name' => 'zzTest Marion Lefèvre', 'role' => 'formatrice en maraichage', 'email' => self::EMAIL]);

		[$eStaff, $eSupervisor] = $this->prepare($eCourse, $eUser);

		$this->assertEquals([$eSupervisorSelf['id'], 'formatrice en maraichage'], [$eStaff['supervisor']['id'] ?? NULL, $eStaff['supervisor']['role'] ?? NULL], 'la fiche du réseau est choisie, avec son rôle habituel');
		$this->assertFalse($eStaff->offsetExists('role'), 'ce rôle ne se pose pas sur la ligne, que les deux chemins lisent');
		$this->assertEquals([FALSE, FALSE], [$eSupervisor->offsetExists('name'), $eSupervisor->offsetExists('email')], 'et la personne à saisir reste vide');

		$panel = new StaffUi()->create($eStaff, $eSupervisor)->body;

		preg_match_all('#<input[^>]*name="role"[^>]*>#', $panel, $matches);
		[$rolePick, $roleNew] = $matches[0] + [NULL, NULL];

		$this->assertTrue(str_contains((string)$rolePick, ' data-proposed="formatrice en maraichage"'), 'le rôle habituel remplit le chemin du choix comme un rôle proposé, que le choix d\'une autre personne remplace : '.$rolePick);
		$this->assertFalse(str_contains((string)$roleNew, ' value=') or str_contains((string)$roleNew, 'data-proposed'), 'le rôle d\'une personne nouvelle reste vide : '.$roleNew);
		$this->assertTrue(str_contains($panel, 'class="tab-panel selected" data-tab="pick"'), 'le chemin du choix est ouvert');

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('learning/supervisor:doUpdateStatus', ['id' => (string)$eSupervisorSelf['id'], 'status' => Supervisor::INACTIVE]), 'la fiche désactivée');

		[$eStaff, $eSupervisor] = $this->prepare($eCourse, $eUser);

		$this->assertTrue($eStaff['supervisor']->empty(), 'désactivée, elle n\'est pas choisie');
		$this->assertEquals([FALSE, FALSE], [$eSupervisor->offsetExists('name'), $eSupervisor->offsetExists('email')], 'et le compte ne se saisit pas en personne nouvelle, qui la doublerait');
		$this->assertFalse(str_contains(new StaffUi()->create($eStaff, $eSupervisor)->body, 'data-proposed'), 'le panneau ne propose aucun rôle');

		// Une seconde fiche, active, à la même adresse : c'est elle qui est choisie, la désactivée ne la masque pas.
		$eSupervisorSecond = $this->createSupervisor($this->eStructureNetwork, ['name' => 'zzTest Marion Seconde', 'role' => 'relectrice', 'email' => self::EMAIL]);

		[$eStaff, $eSupervisor] = $this->prepare($eCourse, $eUser);

		$this->assertEquals($eSupervisorSecond['id'], $eStaff['supervisor']['id'] ?? NULL, 'une fiche active plus récente à la même adresse est choisie');

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('learning/supervisor:doUpdateStatus', ['id' => (string)$eSupervisorSecond['id'], 'status' => Supervisor::INACTIVE]), 'la seconde désactivée à son tour');

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('learning/supervisor:doUpdateStatus', ['id' => (string)$eSupervisorSelf['id'], 'status' => Supervisor::ACTIVE]), 'la fiche réactivée');
		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/staff:doCreate', [
			'course' => (string)$eCourse['id'],
			'source' => 'pick',
			'supervisor' => (string)$eSupervisorSelf['id'],
			'role' => 'formatrice',
			'contact' => '0'
		]), 'elle encadre la formation');

		[$eStaff, $eSupervisor] = $this->prepare($eCourse, $eUser);

		$this->assertTrue($eStaff['supervisor']->empty(), 'déjà sur la formation, elle n\'est pas proposée de nouveau');
		$this->assertFalse($eSupervisor->offsetExists('name'), 'et le compte ne se saisit pas une seconde fois');

	}

	/**
	 * Ce que le panneau laissé vide n'empêche pas : la fiche du compte étant désactivée, la personne
	 * qui saisit quand même sa propre adresse est acceptée, et le réseau a deux fiches à la même
	 * adresse. Aucune règle ne borne l'adresse d'une fiche à son réseau, et l'envoi est celui de
	 * « Ajouter un encadrant » : le lot D ne l'a pas ouvert, il cesse seulement d'y pousser. Le jour
	 * où une étape `Supervisor::email` refuse ce doublon, ce test dit où en reprendre l'attendu.
	 */
	public function testInactiveDuplicate(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);

		$eSupervisorSelf = $this->createSupervisor($this->eStructureNetwork, ['name' => 'zzTest Marion Lefèvre', 'role' => 'formatrice', 'email' => self::EMAIL]);

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('learning/supervisor:doUpdateStatus', ['id' => (string)$eSupervisorSelf['id'], 'status' => Supervisor::INACTIVE]), 'la fiche désactivée');

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/staff:doCreate', [
			'course' => (string)$eCourse['id'],
			'source' => 'new',
			'name' => 'zzTest Marion Lefèvre',
			'role' => 'formatrice',
			'description' => '',
			'email' => self::EMAIL,
			'handicap' => '0',
			'contact' => '0'
		]), 'la même adresse saisie à la main est acceptée');

		$cSupervisor = SupervisorLib::getByEmail($this->eStructureNetwork, self::EMAIL);

		$this->assertEquals(2, $cSupervisor->count(), 'le réseau a alors deux fiches à cette adresse');
		$this->assertEquals([Supervisor::INACTIVE, Supervisor::ACTIVE], $cSupervisor->getColumn('status'), 'l\'ancienne désactivée, la nouvelle active');
		$this->assertEquals(1, Staff::model()->whereCourse($eCourse)->count(), 'et la nouvelle encadre la formation');

	}

	/**
	 * Le panneau tel que la page le prépare pour le compte connecté. Sans origine, il s'ouvre vide,
	 * comme depuis « Ajouter un encadrant ». Avec `origin=self`, il porte le nom et l'adresse du
	 * compte et un rôle vide ; ce qu'il poste est ce que `doCreate` reçoit de n'importe quel ajout.
	 * Les valeurs du compte se comparent sans s'imprimer : l'utilisateur 1 est un compte réel.
	 */
	public function testPanel(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		if($this->assertDecorPresent(
			$this->eUserAdmin['email'] !== NULL and SupervisorLib::getByEmail($this->eStructureNetwork, $this->eUserAdmin['email'])->empty(),
			'l\'utilisateur '.self::USER_ADMIN.' a une adresse, et le réseau '.self::COMMUNITY_NETWORK.' n\'a pas de fiche active à cette adresse'
		) === FALSE) {
			return;
		}

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);

		$name = ($this->eUserAdmin['firstName'] === NULL) ? $this->eUserAdmin['lastName'] : $this->eUserAdmin['firstName'].' '.$this->eUserAdmin['lastName'];

		$data = $this->executeView('learning/staff:create', ['course' => (string)$eCourse['id']]);

		$this->assertEquals([FALSE, FALSE], [$data->eSupervisor->offsetExists('name'), $data->eSupervisor->offsetExists('email')], 'sans origine, le nom et l\'adresse sont vides');

		$data = $this->executeView('learning/staff:create', ['course' => (string)$eCourse['id'], 'origin' => 'self']);
		$panel = new StaffUi()->create($data->e, $data->eSupervisor)->body;

		$this->assertTrue($data->eSupervisor['name'] === $name, 'le nom du compte est posé');
		$this->assertTrue($data->eSupervisor['email'] === $this->eUserAdmin['email'], 'son adresse aussi');
		$this->assertFalse($data->e->offsetExists('role'), 'le rôle reste à écrire');
		$this->assertTrue($data->e['supervisor']->empty(), 'aucune fiche n\'est choisie');
		$this->assertTrue(str_contains($panel, 'data-ajax-form="/learning/staff:doCreate"'), 'le panneau poste vers l\'ajout de toujours');
		$this->assertFalse(str_contains($panel, 'name="origin"'), 'sans rien lui dire de son origine');

		$this->assertEquals(0, Staff::model()->whereCourse($eCourse)->count(), 'ouvrir le panneau n\'écrit aucune ligne');

	}

	/**
	 * La ligne et la personne du panneau d'ajout, telles que la page les monte, puis préparées pour
	 * un compte.
	 *
	 * @return array{0: Staff, 1: Supervisor}
	 */
	private function prepare(Course $eCourse, \user\User $eUser): array {

		$eCourse = $this->getCourse($eCourse['id']);

		$eStaff = new Staff([
			'course' => $eCourse,
			'supervisor' => new Supervisor(),
			'cSupervisor' => SupervisorLib::getForCourse($eCourse)
		]);

		$eSupervisor = SupervisorLib::getCreateElement($eCourse['community']);

		StaffLib::prepareSelf($eStaff, $eSupervisor, $eUser);

		return [$eStaff, $eSupervisor];

	}

	/** L'onglet des encadrants tel que l'éditeur de la formation le prépare. */
	private function getSection(Course $eCourse): string {

		$data = $this->executeView('formation/'.$eCourse['id'].'/edition', []);

		if($data instanceof \stdClass) {
			return new StaffUi()->getEditionSection($data->eCourse, $data->cStaff);
		}

		$this->assertFail('l\'éditeur de la formation '.$eCourse['id'].' ne se lit pas : '.$data);

		return '';

	}

	/**
	 * Une personne par le chemin de son panneau : l'élément du Lib, `build()` sur sa liste, puis
	 * `SupervisorLib::create()`. Rendue relue.
	 */
	private function createSupervisor(\structure\Structure $eStructure, array $input): Supervisor {

		$eSupervisor = SupervisorLib::getCreateElement($eStructure);

		$fw = new \FailWatch();

		$eSupervisor->build(SupervisorLib::getPropertiesCreate(), $input + ['role' => '', 'description' => '', 'email' => ''], new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('personne '.$input['name'].' refusée : '.json_encode($this->getFails($fw))));

		SupervisorLib::create($eSupervisor);

		return SupervisorLib::getById($eSupervisor['id']);

	}

}
?>
