<?php
namespace learning;

require_once __DIR__.'/LearningNetworkFixture.t.php';

/**
 * Les encadrants du réseau : une personne se saisit une fois pour son réseau, son groupe de
 * formation ou l'équipe Ouvretaferme, et une ligne de `Staff` la rattache à chacune de ses
 * formations par `supervisor`. Les règles de la personne se jouent ici par le chemin de ses
 * panneaux, `SupervisorLib::getCreateElement()`, `build()` sur les listes du Lib, puis
 * `SupervisorLib`. Celles de la ligne sont dans `StaffPickTest`.
 *
 * Ce que le fichier attend de la base, relu par `assertFixtures()` : le décor de
 * `LearningNetworkFixtureTest` (le réseau G3, dont l'utilisateur 1 est conseiller, et une ferme
 * qu'il administre), plus le groupe de formation G2 (`COMMUNITY_EDUCATION`) et un lecteur fictif
 * qui n'est membre d'aucun des deux. Encadrants, adhésions, formations et liaisons s'écrivent dans
 * la transaction de chaque méthode.
 *
 * Les deux groupes portent leurs propres encadrants dès la reprise du lot B : le fichier ne lit donc
 * jamais une liste entière. Les siens se nomment `zzTest …` et se lisent par ce préfixe, et les
 * méthodes qui comptent posent d'abord des encadrants étrangers (`createForeign()`), pour que
 * l'indépendance se prouve à chaque lancement et non le jour où la table se remplit.
 *
 * Lancement : dev/test package=learning file=Supervisor
 */
class SupervisorTest extends LearningNetworkFixtureTest {

	/**
	 * Les encadrants que les groupes portent déjà quand le test arrive, comme la reprise du lot B en
	 * posera : sans le préfixe du test, rangés avant et après les siens, le premier contact du réseau.
	 */
	const FOREIGN_NETWORK = ['Aaron Avant', 'zzzoé Après'];
	const FOREIGN_EDUCATION = 'Aaron Ailleurs';

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

		if(\structure\MemberLib::getByUserAndStructure($this->eUserReader, $this->eStructureNetwork)->notEmpty()) {
			$this->assertFail('le lecteur fictif est membre du réseau '.self::COMMUNITY_NETWORK.' dans cette base, le refus d\'un membre d\'un autre réseau ne peut pas se lire');
			return FALSE;
		}

		return TRUE;

	}

	/**
	 * Les encadrants d'un réseau se lisent et s'écrivent par son équipe seule : un compte sans
	 * groupe est refusé, un simple adhérent du réseau aussi, et l'équipe d'un autre groupe de même,
	 * tout en écrivant les siens. Ceux de l'équipe Ouvretaferme sont réservés à ses administrateurs.
	 */
	public function testRights(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eSupervisorNetwork = SupervisorLib::getCreateElement($this->eStructureNetwork);
		$eSupervisorEducation = SupervisorLib::getCreateElement($this->eStructureEducation);
		$eSupervisorTeam = SupervisorLib::getCreateElement();

		$this->actAs($this->eUserAdmin);

		$this->assertEquals([TRUE, TRUE, TRUE, TRUE], [$eSupervisorNetwork->canRead(), $eSupervisorNetwork->canCreate(), $eSupervisorNetwork->canUpdate(), $eSupervisorNetwork->canDelete()], 'un membre de l\'équipe du réseau lit et écrit ses encadrants');
		$this->assertEquals([TRUE, TRUE], [$eSupervisorTeam->canRead(), $eSupervisorTeam->canWrite()], 'un administrateur de Ouvretaferme lit et écrit les encadrants de l\'équipe');

		$this->actAs($this->eUserReader);

		$this->assertEquals([FALSE, FALSE, FALSE, FALSE], [$eSupervisorNetwork->canRead(), $eSupervisorNetwork->canCreate(), $eSupervisorNetwork->canUpdate(), $eSupervisorNetwork->canDelete()], 'un compte sans groupe ne lit ni n\'écrit les encadrants du réseau');
		$this->assertEquals([FALSE, FALSE], [$eSupervisorTeam->canRead(), $eSupervisorTeam->canWrite()], 'un compte qui n\'est pas administrateur ne lit ni n\'écrit les encadrants de l\'équipe');

		$this->joinAsMember($this->eUserReader, $this->eStructureNetwork);
		$this->actAs($this->eUserReader);

		$this->assertTrue($this->eStructureNetwork->getMember()->notEmpty() and $this->eStructureNetwork->getMember()->isStaff() === FALSE, 'le compte est entré au réseau en simple adhérent');
		$this->assertEquals([FALSE, FALSE, FALSE, FALSE], [$eSupervisorNetwork->canRead(), $eSupervisorNetwork->canCreate(), $eSupervisorNetwork->canUpdate(), $eSupervisorNetwork->canDelete()], 'un simple adhérent du réseau, qui n\'est pas de son équipe, ne lit ni n\'écrit ses encadrants');

		$this->join($this->eUserReader, $this->eStructureEducation);
		$this->actAs($this->eUserReader);

		$this->assertEquals([TRUE, TRUE], [$eSupervisorEducation->canRead(), $eSupervisorEducation->canWrite()], 'entré dans l\'équipe d\'un groupe, il écrit les encadrants de ce groupe');
		$this->assertEquals([FALSE, FALSE, FALSE, FALSE], [$eSupervisorNetwork->canRead(), $eSupervisorNetwork->canCreate(), $eSupervisorNetwork->canUpdate(), $eSupervisorNetwork->canDelete()], 'membre de l\'équipe d\'un autre groupe et simple adhérent du réseau, il reste refusé chez le réseau');
		$this->assertEquals([FALSE, FALSE], [$eSupervisorTeam->canRead(), $eSupervisorTeam->canWrite()], 'et chez l\'équipe Ouvretaferme');

		$this->actAs(new \user\User());

		$this->assertEquals([FALSE, FALSE, FALSE], [$eSupervisorNetwork->canRead(), $eSupervisorEducation->canRead(), $eSupervisorTeam->canRead()], 'un visiteur sans compte ne lit aucun encadrant');

	}

	/**
	 * Un encadrant ne s'écrit que là où une formation s'écrit : jamais chez une ferme, et chez un
	 * groupe ou l'équipe seulement tant qu'ils sont ouverts aux formations. La production simulée
	 * (`LearningSetting::$active` à faux) ferme l'équipe et le groupe de formation, et laisse le
	 * réseau de `STRUCTURE_BETA`.
	 */
	public function testAcceptWrite(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$accepts = fn() => [
			'réseau' => SupervisorLib::getCreateElement($this->eStructureNetwork)->acceptWrite(),
			'groupe de formation' => SupervisorLib::getCreateElement($this->eStructureEducation)->acceptWrite(),
			'équipe' => SupervisorLib::getCreateElement()->acceptWrite(),
			'ferme' => SupervisorLib::getCreateElement($this->eStructureFarm)->acceptWrite(),
		];

		$active = LearningSetting::$active;

		try {

			LearningSetting::$active = TRUE;
			$this->forgetMemoization();

			$this->assertEquals(['réseau' => TRUE, 'groupe de formation' => TRUE, 'équipe' => TRUE, 'ferme' => FALSE], $accepts(), 'formations ouvertes à tous : tout auteur de formations écrit ses encadrants, jamais une ferme');

			// Les formations allumées à la main sur un type qui ne les porte pas : la règle éprouvée est
			// celle du type de structure, que l'interrupteur seul ne dit pas.
			$this->eStructureFarm->conf(\structure\Feature::class)['hasCommunityLearning'] = TRUE;

			$acceptsType = fn(string $type, ?string $communityType) => SupervisorLib::getCreateElement(
				new \structure\Structure(['id' => $this->eStructureFarm['id'], 'type' => $type, 'communityType' => $communityType])
			)->acceptWrite();

			$this->assertEquals(
				[FALSE, FALSE, TRUE, TRUE],
				[
					$acceptsType(\structure\Structure::PRODUCER, NULL),
					$acceptsType(\structure\Structure::COMMUNITY, \structure\Structure::ACCOUNTING),
					$acceptsType(\structure\Structure::COMMUNITY, \structure\Structure::EDUCATION),
					$acceptsType(\structure\Structure::COMMUNITY, \structure\Structure::NETWORK)
				],
				'l\'interrupteur allumé ne suffit pas : ni une ferme ni un groupe comptable n\'écrivent d\'encadrants, un groupe de formation et un réseau si'
			);

			\structure\FeatureLib::forget($this->eStructureFarm);

			LearningSetting::$active = FALSE;
			$this->forgetMemoization();

			$this->assertEquals(['réseau' => TRUE, 'groupe de formation' => FALSE, 'équipe' => FALSE, 'ferme' => FALSE], $accepts(), 'production simulée : seul le réseau ouvert aux formations écrit ses encadrants');

		} finally {
			LearningSetting::$active = $active;
			$this->forgetMemoization();
		}

	}

	/**
	 * Un réseau a au plus un contact : en désigner un le retire à celui qui l'était, à la création
	 * comme à la modification, et le décocher laisse le réseau sans contact. Le contact d'un autre
	 * groupe et celui de l'équipe ne bougent jamais, et l'équipe a le sien de la même façon.
	 */
	public function testContactUnique(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$this->createForeign();

		$this->assertEquals([], $this->createSupervisor($this->eStructureEducation, ['name' => 'zzTest Claire Moulin', 'email' => 'claire@example.org', 'contact' => '1']), 'contact d\'un autre groupe');
		$this->assertEquals([], $this->createSupervisor(new \structure\Structure(), ['name' => 'zzTest Dora Roux', 'email' => 'dora@example.org', 'contact' => '1']), 'contact de l\'équipe');

		$this->assertEquals([], $this->createSupervisor($this->eStructureNetwork, ['name' => 'zzTest Ada Martin', 'email' => 'ada@example.org', 'contact' => '1'], $eSupervisorAda), 'Ada désignée contact');
		$this->assertEquals(['zzTest Ada Martin' => TRUE], $this->getContacts($this->eStructureNetwork), 'Ada est le contact du réseau');
		$this->assertEquals([], $this->getContactsForeign($this->eStructureNetwork), 'et celui que le réseau avait déjà ne l\'est plus');

		$this->assertEquals([], $this->createSupervisor($this->eStructureNetwork, ['name' => 'zzTest Bob Petit', 'email' => 'bob@example.org', 'contact' => '1']), 'Bob désigné contact');
		$this->assertEquals(['zzTest Ada Martin' => FALSE, 'zzTest Bob Petit' => TRUE], $this->getContacts($this->eStructureNetwork), 'Bob le devient, Ada ne l\'est plus');

		$this->assertEquals([], $this->updateSupervisor($eSupervisorAda, ['name' => 'zzTest Ada Martin', 'email' => 'ada@example.org', 'contact' => '1']), 'Ada recochée');
		$this->assertEquals(['zzTest Ada Martin' => TRUE, 'zzTest Bob Petit' => FALSE], $this->getContacts($this->eStructureNetwork), 'Ada le redevient, Bob ne l\'est plus');

		$this->assertEquals([], $this->updateSupervisor($eSupervisorAda, ['name' => 'zzTest Ada Martin', 'email' => 'ada@example.org']), 'Ada décochée, la case absente du formulaire');
		$this->assertEquals(['zzTest Ada Martin' => FALSE, 'zzTest Bob Petit' => FALSE], $this->getContacts($this->eStructureNetwork), 'aucun des deux n\'est plus contact');
		$this->assertEquals(0, Supervisor::model()->whereCommunity($this->eStructureNetwork)->whereContact(TRUE)->count(), 'le réseau n\'a plus de contact');

		$this->assertEquals(['zzTest Claire Moulin' => TRUE], $this->getContacts($this->eStructureEducation), 'le contact de l\'autre groupe n\'a pas bougé');
		$this->assertEquals(TRUE, $this->getContacts(new \structure\Structure())['zzTest Dora Roux'], 'celui de l\'équipe non plus');

		$this->assertEquals([], $this->createSupervisor(new \structure\Structure(), ['name' => 'zzTest Eve Blanc', 'email' => 'eve@example.org', 'contact' => '1']), 'un second contact de l\'équipe');

		$contactsTeam = $this->getContacts(new \structure\Structure());

		$this->assertEquals([FALSE, TRUE], [$contactsTeam['zzTest Dora Roux'], $contactsTeam['zzTest Eve Blanc']], 'l\'équipe n\'a qu\'un contact, le dernier désigné');
		$this->assertEquals(['zzTest Claire Moulin' => TRUE], $this->getContacts($this->eStructureEducation), 'le contact du groupe de formation n\'a toujours pas bougé');

	}

	/**
	 * Un contact sans adresse n'a rien à donner sur la fiche : il est refusé à la création, et vider
	 * l'adresse de celui qui l'est déjà l'est aussi. L'adresse vidée et le drapeau retiré dans le même
	 * envoi passent. Chaque refus a son message.
	 */
	public function testContactWithoutEmail(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$this->assertEquals([['Supervisor::contact.noEmail', 'contact']], $this->createSupervisor($this->eStructureNetwork, ['name' => 'zzTest Ada Martin', 'email' => '', 'contact' => '1']), 'un contact sans adresse est refusé');
		$this->assertTrue(AlertUi::getError('Supervisor::contact.noEmail') !== NULL, 'le refus a son message');
		$this->assertEquals(0, Supervisor::model()->whereCommunity($this->eStructureNetwork)->whereName('zzTest Ada Martin')->count(), 'rien n\'est écrit');

		$this->assertEquals([['Supervisor::email.check', 'email']], $this->createSupervisor($this->eStructureNetwork, ['name' => 'zzTest Ada Martin', 'email' => 'pas-une-adresse', 'contact' => '1']), 'une adresse invalide ne lève que son propre refus');

		$this->assertEquals([], $this->createSupervisor($this->eStructureNetwork, ['name' => 'zzTest Ada Martin', 'email' => 'ada@example.org', 'contact' => '1'], $eSupervisor), 'un contact avec adresse s\'écrit');

		$this->assertEquals([['Supervisor::contact.noEmail', 'contact']], $this->updateSupervisor($eSupervisor, ['name' => 'zzTest Ada Martin', 'email' => '', 'contact' => '1']), 'vider l\'adresse du contact du réseau est refusé');
		$this->assertEquals(['ada@example.org', TRUE], array_values(Supervisor::model()->select(['email', 'contact'])->whereId($eSupervisor)->get()->extracts(['email', 'contact'])), 'rien n\'a changé en base');

		$this->assertEquals([], $this->updateSupervisor($eSupervisor, ['name' => 'zzTest Ada Martin', 'email' => '']), 'l\'adresse vidée et le drapeau retiré passent ensemble');
		$this->assertEquals([NULL, FALSE], array_values(Supervisor::model()->select(['email', 'contact'])->whereId($eSupervisor)->get()->extracts(['email', 'contact'])), 'une adresse vidée repasse à NULL, la personne n\'est plus contact');

	}

	/**
	 * Le contact d'une formation est porté par la liaison, pas par la personne : tant qu'une de ses
	 * formations la désigne contact, son adresse ne se vide pas. Rattachée sans être contact, ou
	 * retirée de la formation, elle la vide librement.
	 */
	public function testEmailOfCourseContact(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);
		$eCourseOther = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);

		$this->createSupervisor($this->eStructureNetwork, ['name' => 'zzTest Ada Martin', 'email' => 'ada@example.org'], $eSupervisorAda);
		$this->createSupervisor($this->eStructureNetwork, ['name' => 'zzTest Bob Petit', 'email' => 'bob@example.org'], $eSupervisorBob);

		$eStaffAda = $this->link($eCourse, $eSupervisorAda, TRUE);
		$this->link($eCourseOther, $eSupervisorAda, FALSE);
		$this->link($eCourse, $eSupervisorBob, FALSE);

		$this->assertEquals([['Supervisor::email.contact', 'email']], $this->updateSupervisor($eSupervisorAda, ['name' => 'zzTest Ada Martin', 'email' => '']), 'vider l\'adresse du contact d\'une formation est refusé');
		$this->assertTrue(AlertUi::getError('Supervisor::email.contact') !== NULL, 'le refus a son message');
		$this->assertEquals('ada@example.org', Supervisor::model()->select('email')->whereId($eSupervisorAda)->get()['email'], 'son adresse est restée en base');

		$this->assertEquals([], $this->updateSupervisor($eSupervisorAda, ['name' => 'zzTest Ada Martin', 'email' => 'ada.martin@example.org']), 'la changer reste permis');
		$this->assertEquals([], $this->updateSupervisor($eSupervisorBob, ['name' => 'zzTest Bob Petit', 'email' => '']), 'rattaché sans être contact, Bob vide la sienne');
		$this->assertEquals(NULL, Supervisor::model()->select('email')->whereId($eSupervisorBob)->get()['email'], 'elle repasse à NULL');

		StaffLib::delete($eStaffAda);

		$this->assertEquals([], $this->updateSupervisor($eSupervisorAda, ['name' => 'zzTest Ada Martin', 'email' => '']), 'retirée de la formation dont elle était le contact, Ada vide la sienne');

	}

	/**
	 * Un encadrant rattaché à une formation ne se supprime pas, avec un message ; retiré de toutes
	 * ses formations, il se supprime.
	 */
	public function testDeleteLinked(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);

		$this->createSupervisor($this->eStructureNetwork, ['name' => 'zzTest Ada Martin'], $eSupervisorAda);
		$this->createSupervisor($this->eStructureNetwork, ['name' => 'zzTest Bob Petit'], $eSupervisorBob);

		$eStaff = $this->link($eCourse, $eSupervisorAda, FALSE);

		$this->assertEquals([['Supervisor::deleteLinked', NULL]], $this->deleteSupervisor($eSupervisorAda), 'un encadrant rattaché à une formation ne se supprime pas');
		$this->assertTrue(AlertUi::getError('Supervisor::deleteLinked') !== NULL, 'le refus a son message');
		$this->assertTrue(Supervisor::model()->whereId($eSupervisorAda)->exists(), 'il est toujours en base');

		$this->assertEquals([], $this->deleteSupervisor($eSupervisorBob), 'un encadrant sans formation se supprime');
		$this->assertTrue(Supervisor::model()->whereId($eSupervisorBob)->exists() === FALSE, 'il n\'est plus en base');

		StaffLib::delete($eStaff);

		$this->assertTrue(Supervisor::model()->whereId($eSupervisorAda)->exists(), 'retiré de sa formation, il reste au réseau');
		$this->assertEquals([], $this->deleteSupervisor($eSupervisorAda), 'et il se supprime alors');
		$this->assertTrue(Supervisor::model()->whereId($eSupervisorAda)->exists() === FALSE, 'il n\'est plus en base');

	}

	/**
	 * La liste d'un réseau ne rend que ses encadrants, par nom, chacun avec le nombre de formations
	 * qu'il encadre et de celles dont il est le contact. Créé depuis une formation (`course`), un
	 * encadrant n'est jamais contact du réseau, quoi que le formulaire poste. Le rôle habituel est
	 * celui de la personne : le rôle qu'elle tient dans une formation ne le remplit pas.
	 */
	public function testByCommunity(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);
		$eCourseOther = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);

		$this->createForeign();

		$this->createSupervisor($this->eStructureNetwork, ['name' => 'zzTest Bob Petit', 'role' => 'formateur'], $eSupervisorBob);
		$this->createSupervisor($this->eStructureNetwork, ['name' => 'zzTest Ada Martin', 'email' => 'ada@example.org', 'handicap' => '1'], $eSupervisorAda);
		$this->createSupervisor($this->eStructureEducation, ['name' => 'zzTest Claire Moulin']);
		$this->createSupervisor(new \structure\Structure(), ['name' => 'zzTest Dora Roux']);

		$this->assertEquals([], $this->createSupervisor($this->eStructureNetwork, ['name' => 'zzTest Fanny Leroy', 'email' => 'fanny@example.org', 'contact' => '1'], origin: 'course'), 'un encadrant créé depuis une formation');

		$this->link($eCourse, $eSupervisorAda, TRUE);
		$this->link($eCourseOther, $eSupervisorAda, FALSE);
		$this->link($eCourse, $eSupervisorBob, FALSE);

		$list = [];

		$cSupervisor = SupervisorLib::getByCommunity($this->eStructureNetwork);

		$names = $cSupervisor->getColumn('name');
		$namesSorted = $names;
		sort($namesSorted, SORT_STRING);

		$this->assertEquals($namesSorted, $names, 'la liste entière du réseau est rendue par nom, les encadrants déjà là compris');
		$this->assertEquals([], array_diff(self::FOREIGN_NETWORK, $names), 'où ils paraissent');

		foreach($cSupervisor as $eSupervisor) {

			if(str_starts_with($eSupervisor['name'], 'zzTest ') === FALSE) {
				continue;
			}

			$list[$eSupervisor['name']] = [$eSupervisor['role'], $eSupervisor['handicap'], $eSupervisor['contact'], $eSupervisor['courses'], $eSupervisor['coursesContact']];
		}

		$this->assertEquals([
			'zzTest Ada Martin' => [NULL, TRUE, FALSE, 2, 1],
			'zzTest Bob Petit' => ['formateur', FALSE, FALSE, 1, 0],
			'zzTest Fanny Leroy' => [NULL, FALSE, FALSE, 0, 0],
		], $list, 'les encadrants posés ici, par nom, avec leurs formations ; celui d\'une formation n\'est pas contact du réseau');

		$namesEducation = SupervisorLib::getByCommunity($this->eStructureEducation)->getColumn('name');

		$this->assertTrue(in_array('zzTest Claire Moulin', $namesEducation, TRUE), 'ceux du groupe de formation');
		$this->assertEquals([], array_intersect(['zzTest Ada Martin', 'zzTest Bob Petit', 'zzTest Fanny Leroy', 'zzTest Dora Roux', ...self::FOREIGN_NETWORK], $namesEducation), 'où ceux du réseau et de l\'équipe ne paraissent pas');
		$this->assertEquals([], array_intersect(['zzTest Claire Moulin', 'zzTest Dora Roux', self::FOREIGN_EDUCATION], $names), 'ni ceux du groupe de formation et de l\'équipe chez le réseau');
		$this->assertTrue(in_array('zzTest Dora Roux', SupervisorLib::getByCommunity(new \structure\Structure())->getColumn('name'), TRUE), 'ceux de l\'équipe, pour un groupe vide');
		$this->assertEquals([], array_intersect(['zzTest Ada Martin', 'zzTest Bob Petit', 'zzTest Claire Moulin', 'zzTest Fanny Leroy', ...self::FOREIGN_NETWORK, self::FOREIGN_EDUCATION], SupervisorLib::getByCommunity(new \structure\Structure())->getColumn('name')), 'où ceux des groupes ne paraissent pas');

	}

	/**
	 * Le chemin que prendra le panneau de création : l'élément du Lib, `build()` sur sa liste, puis
	 * `SupervisorLib::create()`. Rend les refus, et l'encadrant relu dans `$eSupervisor`.
	 */
	private function createSupervisor(\structure\Structure $eStructure, array $input, ?Supervisor &$eSupervisor = NULL, ?string $origin = NULL): array {

		$eSupervisor = SupervisorLib::getCreateElement($eStructure);

		$fw = new \FailWatch();

		$eSupervisor->build(SupervisorLib::getPropertiesCreate($origin), $input + ['role' => '', 'description' => '', 'email' => ''], new \PropertyList('create'));

		if($fw->ok()) {
			SupervisorLib::create($eSupervisor);
		}

		return $this->getFails($fw);

	}

	/**
	 * Le chemin que prendra le panneau de modification : l'encadrant relu, ce que ses formations
	 * disent de lui posé par `fillCourses()`, `build()` sur la liste du Lib, puis `update()`.
	 */
	private function updateSupervisor(Supervisor $eSupervisor, array $input): array {

		$eSupervisor = SupervisorLib::getById($eSupervisor['id']);

		SupervisorLib::fillCourses(new \Collection([$eSupervisor]));

		$fw = new \FailWatch();

		$eSupervisor->build(SupervisorLib::getPropertiesUpdate(), $input + ['role' => '', 'description' => '', 'email' => ''], new \PropertyList('update'));

		if($fw->ok()) {
			SupervisorLib::update($eSupervisor, SupervisorLib::getPropertiesUpdate());
		}

		return $this->getFails($fw);

	}

	private function deleteSupervisor(Supervisor $eSupervisor): array {

		$fw = new \FailWatch();

		SupervisorLib::delete($eSupervisor);

		return $this->getFails($fw);

	}

	/**
	 * La ligne d'encadrant d'une formation, rattachée à la personne par le chemin du produit :
	 * `build()` sur la liste de `StaffLib`, la page ayant posé les encadrants de la formation et les
	 * personnes de son réseau qui ne l'encadrent pas encore. Une ligne a toujours un rôle, que la
	 * personne n'a pas forcément : sans rôle habituel, la ligne prend `formatrice`.
	 */
	private function link(Course $eCourse, Supervisor $eSupervisor, bool $contact): Staff {

		$eStaff = new Staff([
			'course' => $eCourse,
			'cStaff' => StaffLib::getByCourse($eCourse),
			'cSupervisor' => SupervisorLib::getForCourse($eCourse)
		]);

		$fw = new \FailWatch();

		$eStaff->build(StaffLib::getPropertiesCreate(), [
			'supervisor' => (string)$eSupervisor['id'],
			'role' => Supervisor::model()->select('role')->whereId($eSupervisor)->get()['role'] ?? 'formatrice',
			'contact' => $contact ? '1' : '0'
		], new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('liaison de '.$eSupervisor['name'].' refusée : '.json_encode($this->getFails($fw))));

		StaffLib::create($eStaff);

		return $eStaff;

	}

	/**
	 * Un simple adhérent : membre du groupe sans être de son équipe (`Role::isStaff()`).
	 */
	private function joinAsMember(\user\User $eUser, \structure\Structure $eStructure): void {

		$fw = new \FailWatch();

		\structure\MemberLib::create(new \structure\Member([
			'user' => $eUser,
			'structure' => $eStructure,
			'role' => \structure\RoleLib::getOne($eStructure, 'member'),
			'status' => \structure\Member::IN
		]));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('adhésion du compte '.$eUser['id'].' à la structure '.$eStructure['id'].' refusée : '.json_encode($this->getFails($fw))));

	}

	/**
	 * Pose les encadrants étrangers au test (`FOREIGN_NETWORK`, `FOREIGN_EDUCATION`), dont le contact
	 * du réseau et celui du groupe de formation.
	 */
	private function createForeign(): void {

		$this->assertEquals(
			[[], [], []],
			[
				$this->createSupervisor($this->eStructureNetwork, ['name' => self::FOREIGN_NETWORK[0], 'email' => 'aaron@example.org', 'contact' => '1']),
				$this->createSupervisor($this->eStructureNetwork, ['name' => self::FOREIGN_NETWORK[1]]),
				$this->createSupervisor($this->eStructureEducation, ['name' => self::FOREIGN_EDUCATION, 'email' => 'ailleurs@example.org', 'contact' => '1'])
			],
			'les encadrants que les groupes portent déjà'
		);

	}

	/**
	 * @return array<string, bool> le contact de chaque encadrant que le test a posé dans le groupe
	 * (`zzTest …`), par nom ; ceux que le groupe porte par ailleurs ne sont pas lus
	 */
	private function getContacts(\structure\Structure $eStructure): array {

		$contacts = [];

		foreach(SupervisorLib::getByCommunity($eStructure) as $eSupervisor) {

			if(str_starts_with($eSupervisor['name'], 'zzTest ')) {
				$contacts[$eSupervisor['name']] = $eSupervisor['contact'];
			}

		}

		return $contacts;

	}

	/**
	 * @return string[] les encadrants étrangers au test qui sont contact du groupe
	 */
	private function getContactsForeign(\structure\Structure $eStructure): array {

		return Supervisor::model()
			->whereCommunity($eStructure)
			->whereName('IN', [...self::FOREIGN_NETWORK, self::FOREIGN_EDUCATION])
			->whereContact(TRUE)
			->getColumn('name');

	}

}
?>
