<?php
namespace learning;

require_once __DIR__.'/LearningNetworkFixture.t.php';

/**
 * Le suivi des stagiaires d'une formation de réseau (L4c) : qui y paraît, sous quelle ferme, ce que
 * la barre propose, ce que la case et l'export acceptent.
 *
 * Le décor est celui de `LearningNetworkFixture.t.php` : le réseau G3, la ferme de l'admin
 * rattachée dans la transaction, le lecteur fictif membre de cette ferme. Une seconde ferme du
 * réseau est prise parmi les rattachements du décor (`getOtherFarm()`), et un second compte fictif
 * y entre pour s'y inscrire.
 */
class TrackNetworkTest extends LearningNetworkFixtureTest {

	/**
	 * Le tableau ne liste que les comptes inscrits, jamais tous les membres des fermes ouvertes,
	 * et la ferme de chacun est celle de son inscription.
	 */
	public function testLearners(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eStructureOther, $eUserOther, $eUserMember] = $this->seedTrack();

		$cUser = EnrollmentLib::getLearners($eCourse);

		$this->assertEquals(
			$this->sorted([$this->eUserAdmin['id'], $this->eUserReader['id'], $eUserOther['id']]),
			$this->sorted($cUser->getIds()),
			'les trois inscrits, et pas le membre de la ferme qui ne l\'est pas'
		);
		$this->assertFalse($cUser->offsetExists($eUserMember['id']), 'un membre d\'une ferme ouverte non inscrit n\'y est pas');

		$this->assertEquals(
			[$this->eUserReader['id']],
			EnrollmentLib::getLearners($eCourse, $this->eStructureFarm)->getIds(),
			'sous la ferme du lecteur, lui seul'
		);
		$this->assertEquals(
			[$eUserOther['id']],
			EnrollmentLib::getLearners($eCourse, $eStructureOther)->getIds(),
			'sous l\'autre ferme, l\'autre compte'
		);

		$cChapter = ChapterLib::getByCourse($eCourse);
		ProgressLib::fillTrack($eCourse, $cChapter, $cUser);

		$this->assertEquals($this->eStructureFarm['name'], $cUser[$this->eUserReader['id']]['eEnrollment']['structure']['name'], 'la ferme de l\'inscription, en annotation');
		$this->assertTrue($cUser[$this->eUserAdmin['id']]['eEnrollment']['structure']->empty(), 'inscrit sans ferme : aucune annotation');

	}

	/**
	 * La barre ne propose que les fermes au nom desquelles des comptes sont inscrits, avec leur
	 * nombre ; une inscription sans ferme n'en fait pas une.
	 */
	public function testStructures(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eStructureOther] = $this->seedTrack();

		$cStructure = EnrollmentLib::getStructuresByCourse($eCourse);

		$this->assertEquals(
			$this->sorted([$this->eStructureFarm['id'], $eStructureOther['id']]),
			$this->sorted($cStructure->getKeys()),
			'les deux fermes qui ont un inscrit'
		);
		$this->assertEquals(1, $cStructure[$this->eStructureFarm['id']]['learners'], 'un inscrit par la ferme du lecteur');
		$this->assertEquals(1, $cStructure[$eStructureOther['id']]['learners'], 'un par l\'autre');

		$names = [];

		foreach($cStructure as $eEnrollment) {
			$names[] = $eEnrollment['structure']['name'];
		}

		$sorted = $names;
		sort($sorted, SORT_STRING);
		$this->assertEquals($sorted, $names, 'rangées par nom');

	}

	/**
	 * Un compte membre de deux fermes du réseau paraît une fois, sous la ferme de son inscription ;
	 * un compte qui a quitté sa ferme reste listé avec ce qu'il a suivi.
	 */
	public function testEnrollmentFarm(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eStructureOther] = $this->seedTrack();

		$this->actAs($this->eUserAdmin);
		$this->join($this->eUserReader, $eStructureOther);

		$this->assertEquals([$this->eUserReader['id']], EnrollmentLib::getLearners($eCourse, $this->eStructureFarm)->getIds(), 'membre de deux fermes : sous celle de son inscription');
		$this->assertFalse(EnrollmentLib::getLearners($eCourse, $eStructureOther)->offsetExists($this->eUserReader['id']), 'et pas sous l\'autre');
		$this->assertEquals(1, EnrollmentLib::getLearners($eCourse)->find(fn(\user\User $eUser) => $eUser['id'] === $this->eUserReader['id'])->count(), 'une seule ligne');

		$this->leave($this->eUserReader, $this->eStructureFarm);

		$this->assertTrue(EnrollmentLib::getLearners($eCourse, $this->eStructureFarm)->offsetExists($this->eUserReader['id']), 'parti de sa ferme, toujours listé sous elle');
		$this->assertTrue(EnrollmentLib::getStructuresByCourse($eCourse)->offsetExists($this->eStructureFarm['id']), 'et la ferme reste dans la barre');

	}

	/**
	 * Les partages d'un réseau comptent pour ouvrir le suivi, comme les promotions ; une promotion
	 * supprimée ne compte plus.
	 */
	public function testCountByCourse(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seed();

		$this->assertEquals(0, ShareLib::countByCourse($eCourse), 'ouverte à personne');

		$this->shareNetwork($eCourse);
		$this->assertEquals(1, ShareLib::countByCourse($eCourse), 'ouverte aux membres : une ligne');

		$this->shareNetwork($eCourse);
		$this->assertEquals(1, ShareLib::countByCourse($eCourse), 'rouverte : toujours une ligne');

		$this->shareNetwork($eCourse, FALSE);
		$this->assertEquals(0, ShareLib::countByCourse($eCourse), 'refermée : plus rien');

		$eCommunity = $this->getCommunity(self::COMMUNITY_EDUCATION);
		$eCourseEducation = $this->createCourse($eCommunity, Course::PUBLISHED, Course::PRIVATE);
		$eSection = $this->createSection($eCommunity, 'zzTest promotion à supprimer', []);
		$this->share($eCourseEducation, [$eSection['id'] => [\Clock::date(), NULL]]);

		$this->assertEquals(1, ShareLib::countByCourse($eCourseEducation), 'une promotion partagée');

		\structure\SectionLib::delete($eSection);

		$this->assertEquals(1, Share::model()->whereCourse($eCourseEducation)->count(), 'la ligne du partage survit à la promotion');
		$this->assertEquals(0, ShareLib::countByCourse($eCourseEducation), 'mais ne compte plus');

	}

	/**
	 * On s'inscrit à la formation en ligne d'un réseau sans qu'il l'ait ouverte à ses membres : le
	 * suivi montre ses inscrits dès le premier, l'ouverture ne décidant pas de qui y paraît.
	 */
	public function testTrackWithoutShare(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seed();
		$this->createLesson($this->createChapter($eCourse, 'Module'), 'Leçon', 5);
		$eCourse = $this->getCourse($eCourse['id']);

		$this->assertEquals(0, EnrollmentLib::getLearners($eCourse)->count(), 'ouverte à personne et sans inscrit');

		EnrollmentLib::create(new Enrollment(['user' => $this->eUserReader, 'course' => $eCourse, 'structure' => new \structure\Structure()]));

		$this->assertEquals(0, ShareLib::countByCourse($eCourse), 'la formation reste ouverte à personne');

		$this->assertEquals([$this->eUserReader['id']], EnrollmentLib::getLearners($eCourse)->getIds(), 'un inscrit, le suivi le compte et le nomme');

	}

	/**
	 * Les écrans du suivi d'un réseau : ouverte à personne, sans inscrit, la grille, et la barre par
	 * ferme avec ses adresses.
	 */
	public function testTrack(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seed();
		$eLesson = $this->createLesson($this->createChapter($eCourse, 'Module'), 'Leçon', 5);
		$eCourse = $this->getCourse($eCourse['id']);

		$html = $this->renderTrack($eCourse);
		$this->assertEquals(0, EnrollmentLib::getLearners($eCourse)->count(), 'ouverte à personne, aucun inscrit');
		$this->assertFalse(str_contains($html, 'course:share'), 'l\'ouverture aux membres ne se propose pas ici');

		$this->shareNetwork($eCourse);

		$this->assertEquals(0, EnrollmentLib::getLearners($eCourse)->count(), 'ouverte, sans inscrit');
		$this->assertFalse(str_contains($this->renderTrack($eCourse), 'progress:export'), 'rien à exporter');

		$eEnrollment = new Enrollment(['user' => $this->eUserReader, 'course' => $eCourse, 'structure' => $this->eStructureFarm]);
		EnrollmentLib::create($eEnrollment);
		ProgressLib::done($eEnrollment, $eLesson);

		$this->assertEquals(1, EnrollmentLib::getLearners($eCourse)->count(), 'un stagiaire');
		$this->assertFalse(str_contains($this->renderTrack($eCourse), 'progress:export'), 'conseiller du réseau : pas d\'export, la règle de L3e');

		$this->promote();

		$html = $this->renderTrack($eCourse);
		$this->assertTrue(str_contains($html, 'href="/learning/progress:export?id='.$eCourse['id'].'"'), 'administrateur : l\'export de tout');
		$this->assertTrue(str_contains($html, 'href="'.ProgressUi::urlTrackLessonStructure($eCourse, $eLesson, new \structure\Structure()).'"'), 'la vue d\'une leçon, toutes fermes');

		$html = $this->renderTrack($eCourse, $this->eStructureFarm);
		$this->assertTrue(str_contains($html, 'href="/learning/progress:export?id='.$eCourse['id'].'&structure='.$this->eStructureFarm['id'].'"'), 'l\'export sous la ferme');
		$this->assertTrue(str_contains($html, 'href="'.ProgressUi::urlTrack($eCourse).'/lecon/'.$eLesson['id'].'/ferme/'.$this->eStructureFarm['id'].'"'), 'la vue d\'une leçon sous la ferme');

		$eStructureNetwork = $this->getCommunity(self::COMMUNITY_NETWORK);
		$cStructure = EnrollmentLib::getStructuresByCourse($eCourse);

		$this->assertEquals('', new ProgressUi()->getStructureLine($eStructureNetwork, $eCourse, new Lesson(), $cStructure, new \structure\Structure()), 'une seule ferme : pas de barre');

		$eStructureOther = $this->getOtherFarm();
		$eUserOther = $this->createAccount('zztest.stagiaire-reseau');
		$this->join($eUserOther, $eStructureOther);
		EnrollmentLib::create(new Enrollment(['user' => $eUserOther, 'course' => $eCourse, 'structure' => $eStructureOther]));

		$cStructure = EnrollmentLib::getStructuresByCourse($eCourse);

		$this->assertEquals([1, 1], array_values($cStructure->getColumn('learners')), 'deux fermes, chacune avec son inscrit');

		$bar = new ProgressUi()->getStructureLine($eStructureNetwork, $eCourse, new Lesson(), $cStructure, new \structure\Structure());
		$this->assertTrue(str_contains($bar, 'href="'.ProgressUi::urlTrack($eCourse).'/ferme/'.$this->eStructureFarm['id'].'"'), 'deux fermes : la barre, avec l\'adresse par ferme');
		$this->assertTrue(str_contains($bar, 'href="'.ProgressUi::urlTrack($eCourse).'"'), 'et toutes');

		$bar = new ProgressUi()->getStructureLine($eStructureNetwork, $eCourse, $eLesson, $cStructure, $this->eStructureFarm);
		$this->assertTrue(str_contains($bar, 'href="'.ProgressUi::urlTrack($eCourse).'/lecon/'.$eLesson['id'].'/ferme/'.$eStructureOther['id'].'"'), 'sous la leçon, l\'adresse garde la leçon');
		$this->assertTrue(str_contains($bar, 'href="'.ProgressUi::urlTrack($eCourse).'/lecon/'.$eLesson['id'].'"'), 'et « toutes » aussi');

	}

	/**
	 * Les routes du suivi d'un réseau, la case et l'export : un inscrit se coche, un membre non
	 * inscrit non ; une ferme inconnue de la barre vaut toutes.
	 */
	public function testPages(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eStructureOther, $eUserOther, $eUserMember, $eLesson] = $this->seedTrack();

		$this->actAs($this->eUserAdmin);

		$this->assertEquals([\CommunityAction::class, NULL], $this->executePage('formation/'.$eCourse['id'].'/suivi', [], 'GET'), 'la grille');
		$this->assertEquals([\CommunityAction::class, NULL], $this->executePage('formation/'.$eCourse['id'].'/suivi/ferme/'.$this->eStructureFarm['id'], [], 'GET'), 'sous une ferme');
		$this->assertEquals([\CommunityAction::class, NULL], $this->executePage('formation/'.$eCourse['id'].'/suivi/ferme/999999999', [], 'GET'), 'une ferme hors de la barre vaut toutes');
		$this->assertEquals([\CommunityAction::class, NULL], $this->executePage('formation/'.$eCourse['id'].'/suivi/lecon/'.$eLesson['id'].'/ferme/'.$eStructureOther['id'], [], 'GET'), 'la vue d\'une leçon sous une ferme');

		$posted = fn(\user\User $eUser) => ['id' => (string)$eCourse['id'], 'lesson' => (string)$eLesson['id'], 'user' => (string)$eUser['id']];

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('learning/progress:doCheck', $posted($eUserOther)), 'un inscrit se coche');
		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('learning/progress:doUncheck', $posted($eUserOther)), 'et se décoche');
		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('learning/progress:doCheck', $posted($eUserMember)), 'un membre d\'une ferme ouverte non inscrit ne se coche pas');

		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('learning/progress:export', ['id' => (string)$eCourse['id']], 'GET'), 'conseiller du réseau : pas d\'export');
		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('learning/progress:exportDetail', ['id' => (string)$eCourse['id']], 'GET'), 'ni de détail');

		$this->promote();

		$this->assertEquals([\CsvAction::class, NULL], $this->executePage('learning/progress:export', ['id' => (string)$eCourse['id'], 'structure' => (string)$this->eStructureFarm['id']], 'GET'), 'administrateur : l\'export sous une ferme');

		$cChapter = ChapterLib::getByCourse($eCourse);
		$cUser = EnrollmentLib::getLearners($eCourse, $this->eStructureFarm);
		ProgressLib::fillTrack($eCourse, $cChapter, $cUser);
		ReadingLib::fillLastSeen($eCourse, $cUser);

		$lines = new ProgressUi()->getExport($cChapter, $cUser);
		$this->assertEquals(2, count($lines), 'l\'en-tête et le seul inscrit de la ferme');
		$this->assertEquals($this->eStructureFarm['name'], $lines[1][1], 'sa ferme en colonne');

		$this->assertEquals([\CsvAction::class, NULL], $this->executePage('learning/progress:exportDetail', ['id' => (string)$eCourse['id'], 'structure' => (string)$this->eStructureFarm['id']], 'GET'), 'administrateur : le détail sous une ferme');

		ProgressLib::fillDetail($eCourse, $cChapter, $cUser);
		ReadingLib::fillMeasured($eCourse, $cChapter, $cUser);

		$lines = new ProgressUi()->getExportDetail($cChapter, $cUser);
		$this->assertEquals(2, count($lines), 'l\'en-tête et la seule leçon du seul inscrit de la ferme');
		$this->assertEquals($this->eStructureFarm['name'], $lines[1][1], 'sa ferme en colonne, sous le même en-tête que le tableau');

		$this->actAs($this->eUserReader);

		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('formation/'.$eCourse['id'].'/suivi', [], 'GET'), 'un stagiaire ne voit pas le suivi');

	}

	/**
	 * Le décor du suivi : la formation partagée à toutes les fermes, une leçon, le lecteur inscrit
	 * par sa ferme, l'admin inscrit sans ferme, un second compte inscrit par une autre ferme du
	 * réseau, et un membre de la ferme du lecteur qui ne s'inscrit pas.
	 */
	private function seedTrack(): array {

		$eCourse = $this->seed();
		$eLesson = $this->createLesson($this->createChapter($eCourse, 'Module'), 'Leçon', 5);
		$eCourse = $this->getCourse($eCourse['id']);

		$this->shareNetwork($eCourse);

		$eStructureOther = $this->getOtherFarm();

		$eUserOther = $this->createAccount('zztest.stagiaire-reseau');
		$this->join($eUserOther, $eStructureOther);

		$eUserMember = $this->createAccount('zztest.membre-non-inscrit');
		$this->join($eUserMember, $this->eStructureFarm);

		EnrollmentLib::create(new Enrollment(['user' => $this->eUserReader, 'course' => $eCourse, 'structure' => $this->eStructureFarm]));
		EnrollmentLib::create(new Enrollment(['user' => $this->eUserAdmin, 'course' => $eCourse, 'structure' => new \structure\Structure()]));
		EnrollmentLib::create(new Enrollment(['user' => $eUserOther, 'course' => $eCourse, 'structure' => $eStructureOther]));

		return [$eCourse, $eStructureOther, $eUserOther, $eUserMember, $eLesson];

	}

	/**
	 * L'admin devient administrateur du réseau, où la base ne le fait que conseiller : l'export ne
	 * s'offre qu'à qui administre le groupe. Le rôle est relu par `actAs()`, qui vide les mémos.
	 */
	private function promote(): void {

		$eStructureNetwork = $this->getCommunity(self::COMMUNITY_NETWORK);
		$eMember = \structure\MemberLib::getByUserAndStructure($this->eUserAdmin, $eStructureNetwork);

		$eMember['role'] = \structure\RoleLib::getOne($eStructureNetwork, 'admin');
		\structure\MemberLib::update($eMember, ['role']);

		$this->actAs($this->eUserAdmin);

	}

	/** L'écran tel que la page le prépare pour un réseau, sous la ferme `$eStructure` ou toutes. */
	private function renderTrack(Course $eCourse, \structure\Structure $eStructure = new \structure\Structure()): string {

		$eStructureNetwork = $this->getCommunity(self::COMMUNITY_NETWORK);

		$cChapter = ChapterLib::getByCourse($eCourse);
		$eCourse['lessons'] = ChapterLib::countLessons($cChapter);
		$eCourse['shares'] = ShareLib::countByCourse($eCourse);

		$cUser = EnrollmentLib::getLearners($eCourse, $eStructure);
		ProgressLib::fillTrack($eCourse, $cChapter, $cUser);
		ReadingLib::fillLastSeen($eCourse, $cUser);

		return new ProgressUi()->getTrack($eStructureNetwork, $eCourse, $cChapter, new \Collection(), new \structure\Section(), $cUser, new Lesson(), $eStructure);

	}

	/** Un compte `example.org`, domaine réservé où aucun envoi n'est délivré, écrit dans la transaction. */
	private function createAccount(string $email): \user\User {

		$eUser = new \user\User([
			'firstName' => 'Émile',
			'lastName' => 'zzTest',
			'email' => $email.'-'.uniqid().'@example.org',
			'visibility' => \user\User::PRIVATE,
			'roleCustomer' => FALSE,
			'roleProducer' => TRUE,
			'roleConsultant' => FALSE,
			'roleTeacher' => FALSE
		]);

		$fw = new \FailWatch();

		\user\UserLib::create($eUser);

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('création du compte '.$email.' refusée : '.json_encode($fw->get())));

		return $this->getUser($eUser['id']);

	}

	private function sorted(array $values): array {
		sort($values);
		return $values;
	}

}
?>
