<?php
namespace learning;

require_once __DIR__.'/LearningFixture.t.php';

/**
 * Le contact d'une formation (lot contact-encadrant) : un encadrant porte une adresse e-mail et peut
 * être désigné contact. La ligne Contact de la fiche nomme alors cet encadrant et donne son adresse,
 * et l'adresse légale du réseau ne sert plus que de repli. Une formation a au plus un contact : en
 * désigner un le retire à l'ancien, sans toucher aux autres formations. Le contact se règle sur la
 * ligne de la formation, l'adresse est celle de la personne (`learning\Supervisor`).
 *
 * Ce que le fichier attend de la base, relu par `assertFixtures()` : le réseau G3 du décor commun
 * (`COMMUNITY_NETWORK`), dont l'utilisateur 1 (`USER_ADMIN`), fait conseiller par `setUp()`, écrit
 * les formations, et qui porte une adresse légale, la même attente que `CourseMentionTest`, et n'a
 * pas désigné de contact du réseau, qui passerait devant cette adresse. Les formations et encadrants s'écrivent par le
 * chemin des panneaux, dans la transaction.
 */
class StaffContactTest extends LearningFixtureTest {

	const COMMUNITY_NETWORK = \structure\FixtureAiLib::G3;

	private \structure\Structure $eStructureNetwork;

	protected function getMemberships(): array {
		return parent::getMemberships() + [
			self::COMMUNITY_NETWORK => 'advisor',
		];
	}

	protected function assertFixtures(): bool {

		if(parent::assertFixtures() === FALSE) {
			return FALSE;
		}

		$this->eStructureNetwork = $this->getCommunity(self::COMMUNITY_NETWORK);

		if($this->eStructureNetwork->empty() or $this->eStructureNetwork->isNetwork() === FALSE) {
			$this->assertFail('le réseau '.self::COMMUNITY_NETWORK.' est absent de cette base, ou n\'est pas un réseau');
			return FALSE;
		}

		if($this->eStructureNetwork['legalEmail'] === '') {
			$this->assertFail('le réseau '.self::COMMUNITY_NETWORK.' n\'a pas d\'adresse légale dans cette base, le repli de la ligne Contact ne peut pas se lire');
			return FALSE;
		}

		if(SupervisorLib::getContactByCommunity($this->eStructureNetwork)->notEmpty()) {
			$this->assertFail('le réseau '.self::COMMUNITY_NETWORK.' a un contact du réseau dans cette base, le repli sur l\'adresse légale ne peut pas se lire');
			return FALSE;
		}

		return TRUE;

	}

	/**
	 * Cocher un encadrant le fait contact et le retire à celui qui l'était, à la création comme à la
	 * modification ; le décocher laisse la formation sans contact. Le contact d'une autre formation
	 * ne bouge jamais.
	 */
	public function testContactUnique(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);
		$eCourseOther = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);

		$this->assertEquals([\ReloadAction::class, NULL], $this->createStaff($eCourseOther, 'Claire Moulin', 'claire@example.org', TRUE), 'contact d\'une autre formation');
		$this->assertEquals([\ReloadAction::class, NULL], $this->createStaff($eCourse, 'Ada Martin', 'ada@example.org', TRUE), 'Ada nommée contact');

		$this->assertEquals(['Ada Martin' => TRUE], $this->getContacts($eCourse), 'Ada est le contact');
		$this->assertEquals('ada@example.org', $this->getStaff($eCourse, 'Ada Martin')['supervisor']['email'], 'son adresse est écrite, sur la personne');

		$this->assertEquals([\ReloadAction::class, NULL], $this->createStaff($eCourse, 'Bob Petit', 'bob@example.org', TRUE), 'Bob nommé contact');
		$this->assertEquals(['Ada Martin' => FALSE, 'Bob Petit' => TRUE], $this->getContacts($eCourse), 'Bob le devient, Ada ne l\'est plus');

		$eStaffAda = $this->getStaff($eCourse, 'Ada Martin');

		$this->assertEquals([\ReloadAction::class, NULL], $this->updateStaff($eStaffAda, TRUE), 'Ada recochée');
		$this->assertEquals(['Ada Martin' => TRUE, 'Bob Petit' => FALSE], $this->getContacts($eCourse), 'Ada le redevient, Bob ne l\'est plus');

		$this->assertEquals([\ReloadAction::class, NULL], $this->updateStaff($eStaffAda, FALSE), 'Ada décochée, la case absente du formulaire');
		$this->assertEquals(['Ada Martin' => FALSE, 'Bob Petit' => FALSE], $this->getContacts($eCourse), 'la formation n\'a plus de contact');

		$this->assertEquals(['Claire Moulin' => TRUE], $this->getContacts($eCourseOther), 'le contact de l\'autre formation n\'a pas bougé');

	}

	/**
	 * Un contact sans adresse n'a rien à donner sur la fiche : il est refusé, à l'ajout comme au
	 * réglage d'un encadrant que la formation a déjà. Une adresse invalide l'est aussi. Chaque refus
	 * a son message.
	 */
	public function testContactRefused(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);

		$this->assertEquals([\FailAction::class, 'Staff::contact.noEmail'], $this->createStaff($eCourse, 'Ada Martin', '', TRUE), 'un contact sans adresse est refusé');
		$this->assertTrue(AlertUi::getError('Staff::contact.noEmail') !== NULL, 'le refus a son message');
		$this->assertEquals(0, Staff::model()->whereCourse($eCourse)->count(), 'rien n\'est écrit');

		$this->assertEquals([\FailAction::class, 'Supervisor::email.check'], $this->createStaff($eCourse, 'Ada Martin', 'pas-une-adresse', FALSE), 'une adresse invalide est refusée');
		$this->assertTrue(AlertUi::getError('Supervisor::email.check') !== NULL, 'le refus a son message');
		$this->assertEquals([0, 0], [Staff::model()->whereCourse($eCourse)->count(), Supervisor::model()->whereCommunity($this->eStructureNetwork)->whereName('Ada Martin')->count()], 'rien n\'est écrit, ni la ligne ni la personne');

		$this->assertEquals([\ReloadAction::class, NULL], $this->createStaff($eCourse, 'Ada Martin', 'ada@example.org', FALSE), 'une adresse sans contact s\'écrit');

		$this->assertEquals([\ReloadAction::class, NULL], $this->createStaff($eCourse, 'Bob Petit', '', FALSE), 'un encadrant sans adresse s\'écrit, tant qu\'il n\'est pas contact');
		$this->assertEquals(NULL, $this->getStaff($eCourse, 'Bob Petit')['supervisor']['email'], 'une adresse laissée vide est NULL');

		$this->assertEquals([\FailAction::class, 'Staff::contact.noEmail'], $this->updateStaff($this->getStaff($eCourse, 'Bob Petit'), TRUE), 'le désigner contact ensuite est refusé');
		$this->assertEquals(['Ada Martin' => FALSE, 'Bob Petit' => FALSE], $this->getContacts($eCourse), 'la formation reste sans contact');

	}

	/**
	 * La ligne Contact de la fiche et du panneau d'inscription nomme l'encadrant contact et donne son
	 * adresse ; sans contact, l'adresse légale du réseau revient. Entre les deux passe le contact du
	 * réseau, qu'il encadre ou non la formation : il remplace l'adresse légale, et cède au contact que
	 * la formation désigne. Une formation de l'équipe n'a de ligne Contact que si un encadrant l'est.
	 */
	public function testFiche(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$legal = 'mailto:'.encode($this->eStructureNetwork['legalEmail']);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);
		$eChapter = $this->createChapter($eCourse, 'Prendre ses repères');
		$this->createLesson($eChapter, 'Constituer son dossier', 18);

		$this->createStaff($eCourse, 'Ada Martin', 'ada@example.org', TRUE);
		$this->createStaff($eCourse, 'Bob Petit', 'bob@example.org', FALSE);

		$data = $this->executeView('formation/'.$eCourse['id'], []);

		if($this->assertTrue($data instanceof \stdClass, 'la fiche se lit') === FALSE) {
			return;
		}

		foreach([
			'fiche' => new CourseUi()->getDetails($data->eCourse, $data->cStaff),
			'panneau d\'inscription' => new CourseUi()->getAudience($data->eCourse, $data->cStaff)
		] as $where => $html) {

			$this->assertTrue(str_contains($html, 'href="mailto:ada@example.org"'), $where.' : le contact est Ada, avec son adresse');
			$this->assertFalse(str_contains($html, 'mailto:bob@example.org'), $where.' : l\'adresse de Bob, qui n\'est pas contact, ne paraît pas');
			$this->assertFalse(str_contains($html, $legal), $where.' : l\'adresse légale du réseau cède la place');

		}

		$this->updateStaff($this->getStaff($eCourse, 'Ada Martin'), FALSE);

		$data = $this->executeView('formation/'.$eCourse['id'], []);

		$details = new CourseUi()->getDetails($data->eCourse, $data->cStaff);

		$this->assertTrue(str_contains($details, $legal), 'sans contact, l\'adresse légale du réseau revient');
		$this->assertFalse(str_contains($details, 'mailto:ada@example.org'), 'et l\'adresse d\'Ada ne paraît plus');

		$this->assertEquals([\ReloadAction::class, NULL], $this->createNetworkContact('Diane Faure', 'diane@example.org'), 'le réseau désigne son contact, qui n\'encadre pas la formation');

		$data = $this->executeView('formation/'.$eCourse['id'], []);

		foreach([
			'fiche' => new CourseUi()->getDetails($data->eCourse, $data->cStaff),
			'panneau d\'inscription' => new CourseUi()->getAudience($data->eCourse, $data->cStaff)
		] as $where => $html) {

			$this->assertTrue(str_contains($html, 'href="mailto:diane@example.org"'), $where.' : sans contact désigné, le contact du réseau');
			$this->assertFalse(str_contains($html, $legal), $where.' : qui passe devant l\'adresse légale');

		}

		$this->updateStaff($this->getStaff($eCourse, 'Ada Martin'), TRUE);

		$data = $this->executeView('formation/'.$eCourse['id'], []);

		$details = new CourseUi()->getDetails($data->eCourse, $data->cStaff);

		$this->assertTrue(str_contains($details, 'href="mailto:ada@example.org"'), 'le contact que la formation désigne passe devant celui du réseau');
		$this->assertFalse(str_contains($details, 'mailto:diane@example.org'), 'qui ne paraît plus');

		$eCourseTeam = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE);
		$this->createStaff($eCourseTeam, 'Claire Moulin', 'claire@example.org', TRUE);

		$eCourseTeam = $this->getCourse($eCourseTeam['id']);
		$eCourseTeam['eSupervisorContact'] = SupervisorLib::getContactByCourse($eCourseTeam);

		$details = new CourseUi()->getDetails($eCourseTeam, StaffLib::getByCourse($eCourseTeam));

		$this->assertTrue(str_contains($details, 'href="mailto:claire@example.org"'), 'une formation de l\'équipe montre son encadrant contact');

	}

	/**
	 * L'onglet des encadrants, dans l'éditeur : sous les cartes, la ligne « Contact » est celle de la
	 * fiche, et donne quand la formation n'a désigné personne le contact du réseau, sinon son adresse
	 * légale. Une personne désactivée reste sur la formation.
	 */
	public function testDeck(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);

		$this->createStaff($eCourse, 'Ada Martin', 'ada@example.org', TRUE);
		$this->createStaff($eCourse, 'Bob Petit', '', FALSE);

		$deck = $this->getSection($eCourse);

		$this->assertTrue(str_contains($deck, 'href="mailto:ada@example.org"'), 'la ligne Contact donne le contact désigné : '.$deck);

		$this->updateStaff($this->getStaff($eCourse, 'Ada Martin'), FALSE);

		$deck = $this->getSection($eCourse);

		$this->assertTrue(str_contains($deck, 'href="mailto:'.encode($this->eStructureNetwork['legalEmail']).'"'), 'la ligne donne l\'adresse légale du réseau : '.$deck);

		$this->createNetworkContact('Diane Faure', 'diane@example.org');

		$deck = $this->getSection($eCourse);

		$this->assertTrue(str_contains($deck, 'href="mailto:diane@example.org"'), 'la ligne donne le contact du réseau : '.$deck);

		$eSupervisorBob = $this->getStaff($eCourse, 'Bob Petit')['supervisor'];

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('learning/supervisor:doUpdateStatus', ['id' => (string)$eSupervisorBob['id'], 'status' => Supervisor::INACTIVE]), 'Bob désactivé depuis la liste du réseau');

		$this->assertEquals(2, StaffLib::getByCourse($eCourse)->count(), 'Bob reste sur la formation');
		$this->assertEquals(Supervisor::INACTIVE, $this->getStaff($eCourse, 'Bob Petit')['supervisor']['status'], 'désactivé');

		$eCourseTeam = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE);

		// La formation de l'équipe n'a de ligne que si l'équipe a un contact, que cette base peut porter.
		if(SupervisorLib::getContactByCommunity(new \structure\Structure())->empty()) {
			$this->assertFalse(str_contains($this->getSection($eCourseTeam), 'mailto:'), 'une formation de l\'équipe sans contact n\'a pas de ligne Contact');
		}

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
	 * Le contact du réseau, par le panneau de sa liste d'encadrants.
	 */
	private function createNetworkContact(string $name, string $email): array {

		return $this->executePage('learning/supervisor:doCreate', [
			'community' => (string)$this->eStructureNetwork['id'],
			'name' => $name,
			'role' => '',
			'description' => '',
			'email' => $email,
			'handicap' => '0',
			'contact' => '1'
		]);

	}

	/**
	 * Le panneau d'un encadrant pose Contact et Référent handicap en oui / non (lot
	 * formulaires-visio, retours du 01/10, points 20 et 21). À l'ajout les deux partent de
	 * « non », le défaut du schéma, sans quoi le navigateur refuserait l'envoi d'un choix exigé et
	 * resté vide ; à la modification, le panneau d'une formation ne règle plus que le contact, qui
	 * reprend ce que la ligne porte : la marque « Référent handicap » est celle de la personne.
	 *
	 * L'ajout a deux chemins (lot des encadrants du réseau) : choisir une personne du réseau, proposée
	 * avec son rôle habituel, ou en saisir une nouvelle. Chacun tient dans un `fieldset`, celui du
	 * chemin non retenu étant désactivé ; le contact de la formation se lit une fois, sous les deux.
	 * Sans personne à choisir, le panneau n'a pas d'onglets. Une personne désactivée n'est pas
	 * proposée. Au réglage, « Modifier la fiche » ouvre le panneau de la personne.
	 */
	public function testPanel(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);

		$eSupervisorAda = $this->createSupervisor('zzTest Ada Martin', 'formatrice');
		$eSupervisorBob = $this->createSupervisor('zzTest Bob Petit', '');
		$eSupervisorOff = $this->createSupervisor('zzTest Carla Roy', 'autrice');

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('learning/supervisor:doUpdateStatus', ['id' => (string)$eSupervisorOff['id'], 'status' => Supervisor::INACTIVE]), 'Carla désactivée');

		$data = $this->executeView('learning/staff:create', ['course' => (string)$eCourse['id']]);
		$panel = new StaffUi()->create($data->e, $data->eSupervisor)->body;

		$this->assertFalse(str_contains($panel, 'type="checkbox"'), 'à l\'ajout, plus aucune case à cocher');
		$this->assertEquals(['contact' => '0', 'handicap' => '0'], $this->getChoices($panel), 'à l\'ajout, les deux partent de « non »');

		$this->assertTrue(str_contains($panel, '<div class="tabs tabs-h tabs-exclusive"><div class="tabs-item tabs-form">'), 'deux chemins exclusifs, en onglets de formulaire');
		$this->assertEquals(['new', 'pick'], $this->getSources($panel), 'chaque chemin poste sa source');
		$this->assertTrue(str_contains($panel, '<div class="tab-panel selected" data-tab="pick"><fieldset>'), 'le choix ouvert, son fieldset actif et enfant direct du panneau');
		$this->assertTrue(str_contains($panel, '<div class="tab-panel" data-tab="new"><fieldset disabled>'), 'la saisie fermée, son fieldset désactivé');
		$this->assertEquals(2, substr_count($panel, 'name="role"'), 'un « Rôle dans la formation » par chemin');
		$this->assertEquals(2, substr_count($panel, 'name="contact"'), 'le contact de la formation une seule fois, son oui et son non');
		$this->assertTrue(strpos($panel, 'name="contact"') > strrpos($panel, '</fieldset>'), 'sous les deux chemins');

		$options = $this->getOptions($panel);

		$this->assertEquals('formatrice', $options[$eSupervisorAda['id']] ?? 'absente', 'Ada proposée avec son rôle habituel');
		$this->assertEquals(NULL, array_key_exists($eSupervisorBob['id'], $options) ? $options[$eSupervisorBob['id']] : 'absent', 'Bob proposé, sans rôle à reporter');
		$this->assertFalse(array_key_exists($eSupervisorOff['id'], $options), 'Carla, désactivée, n\'est pas proposée');
		$this->assertTrue(str_contains($panel, 'onchange="LearningStaff.proposeRole(this)"'), 'le choix reporte le rôle habituel');

		$data->e['cSupervisor'] = new \Collection();
		$panelAlone = new StaffUi()->create($data->e, $data->eSupervisor)->body;

		$this->assertFalse(str_contains($panelAlone, '<fieldset'), 'sans personne à choisir, pas de fieldset');
		$this->assertEquals(['new'], $this->getSources($panelAlone), 'la saisie seule');
		$this->assertEquals(1, substr_count($panelAlone, 'name="role"'), 'et un seul rôle');

		$this->createStaff($eCourse, 'Ada Martin', 'ada@example.org', TRUE);

		$eStaff = StaffLib::getByCourse($eCourse)->first();

		$data = $this->executeView('learning/staff:update', ['id' => (string)$eStaff['id']]);
		$panel = new StaffUi()->update($data->e)->body;

		$this->assertEquals(['contact' => '1', 'handicap' => NULL], $this->getChoices($panel), 'à la modification, le contact seul, qui se lit sur « oui »');
		$this->assertTrue(str_contains($panel, '<a href="/learning/supervisor:update?id='.$eStaff['supervisor']['id'].'"'), '« Modifier la fiche » ouvre le panneau de la personne : '.$panel);

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/staff:doUpdate', [
			'id' => (string)$eStaff['id'],
			'role' => 'formatrice',
			'contact' => '0',
		]), 'le panneau poste « non » en toutes lettres');

		$this->assertFalse(Staff::model()->select('contact')->whereId($eStaff['id'])->get()['contact'], '« non » retire le contact');

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/staff:doCreate', [
			'course' => (string)$eCourse['id'],
			'name' => 'Bob Petit',
			'role' => 'formateur',
			'description' => '',
			'email' => '',
			'contact' => '0',
			'handicap' => '1',
		]), 'à l\'ajout, le panneau poste « non » et « oui » en toutes lettres');

		$eStaffBob = $this->getStaff($eCourse, 'Bob Petit');

		$this->assertEquals([FALSE, TRUE], [$eStaffBob['contact'], $eStaffBob['supervisor']['handicap']], '« non » ne désigne pas de contact, « oui » pose le référent handicap sur la personne');

	}

	/**
	 * Une personne du réseau, par le panneau de sa liste. Rendue relue.
	 */
	private function createSupervisor(string $name, string $role): Supervisor {

		$this->executePage('learning/supervisor:doCreate', [
			'community' => (string)$this->eStructureNetwork['id'],
			'name' => $name,
			'role' => $role,
			'description' => '',
			'email' => '',
			'handicap' => '0',
			'contact' => '0'
		]);

		return Supervisor::model()
			->select(Supervisor::getSelection())
			->whereCommunity($this->eStructureNetwork)
			->whereName($name)
			->get();

	}

	/**
	 * @return list<string> la source que poste chaque chemin du panneau, triées
	 */
	private function getSources(string $panel): array {

		preg_match_all('#<input[^>]*name="source"[^>]*value="([a-z]+)"#', $panel, $matches);
		sort($matches[1]);

		return $matches[1];

	}

	/**
	 * @return array<int, ?string> le rôle habituel que porte chaque personne proposée, par identifiant
	 */
	private function getOptions(string $panel): array {

		preg_match_all('#<option value="([0-9]+)"( data-role="([^"]*)")?#', $panel, $matches, PREG_SET_ORDER);

		$options = [];

		foreach($matches as $match) {
			$options[(int)$match[1]] = $match[3] ?? NULL;
		}

		return $options;

	}

	/**
	 * @return array<string, ?string> la valeur cochée de chaque oui / non du panneau
	 */
	private function getChoices(string $panel): array {

		$choices = [];

		foreach(['contact', 'handicap'] as $property) {
			$choices[$property] = (preg_match('#<input id="'.$property.'[01]" checked="checked" name="'.$property.'" type="radio" value="([01])"/>#', $panel, $match) === 1) ? $match[1] : NULL;
		}

		return $choices;

	}

	/**
	 * La copie d'une formation dans son réseau reprend les personnes de l'original et le contact.
	 */
	public function testDuplicate(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE);

		$this->createStaff($eCourse, 'Ada Martin', 'ada@example.org', FALSE);
		$this->createStaff($eCourse, 'Bob Petit', 'bob@example.org', TRUE);

		$eCourseNew = CourseLib::getCreateElement($this->eStructureNetwork);

		$fw = new \FailWatch();
		$eCourseNew->build(['name'], ['name' => 'zzTest copie'], new \PropertyList('create'));
		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('titre de la copie refusé : '.json_encode($fw->get())));

		$eCourseCopy = CourseLib::duplicate($this->getCourse($eCourse['id']), $eCourseNew);

		$cStaffCopy = StaffLib::getByCourse($eCourseCopy);

		$this->assertEquals(StaffLib::getByCourse($eCourse)->getColumnCollection('supervisor')->getIds(), $cStaffCopy->getColumnCollection('supervisor')->getIds(), 'les mêmes personnes, donc leurs adresses');
		$this->assertEquals(['Ada Martin' => FALSE, 'Bob Petit' => TRUE], $this->getContacts($eCourseCopy), 'contact repris');
		$this->assertEquals(['Ada Martin' => FALSE, 'Bob Petit' => TRUE], $this->getContacts($eCourse), 'l\'original garde le sien');

	}

	private function createStaff(Course $eCourse, string $name, string $email, bool $contact): array {

		return $this->executePage('learning/staff:doCreate', [
			'course' => (string)$eCourse['id'],
			'name' => $name,
			'role' => 'formatrice',
			'description' => '',
			'email' => $email
		] + ($contact ? ['contact' => '1'] : []));

	}

	private function updateStaff(Staff $eStaff, bool $contact): array {

		return $this->executePage('learning/staff:doUpdate', [
			'id' => (string)$eStaff['id'],
			'role' => 'formatrice'
		] + ($contact ? ['contact' => '1'] : []));

	}

	/**
	 * La ligne d'une formation dont la personne porte ce nom, ou une ligne vide.
	 */
	private function getStaff(Course $eCourse, string $name): Staff {

		return StaffLib::getByCourse($eCourse)->find(fn(Staff $eStaff) => $eStaff['supervisor']['name'] === $name, limit: 1, default: new Staff());

	}

	/**
	 * @return array<string, bool> le contact de chaque encadrant, par nom
	 */
	private function getContacts(Course $eCourse): array {

		$contacts = [];

		foreach(StaffLib::getByCourse($eCourse) as $eStaff) {
			$contacts[$eStaff['supervisor']['name']] = $eStaff['contact'];
		}

		return $contacts;

	}

}
?>
