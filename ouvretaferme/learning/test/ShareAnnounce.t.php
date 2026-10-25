<?php
namespace learning;

require_once __DIR__.'/LearningDateFixture.t.php';

/**
 * L'e-mail d'annonce d'une formation partagée (F35, lot L4d) : à qui il part, une seule fois par
 * compte et par formation, et ce qu'il dit.
 *
 * Aucun test n'envoie : `ShareLib::claimAnnounce()` réclame les partages et pose les annonces comme
 * le produit, puis rend les e-mails au lieu de les remettre à `SendLib`. Le compte 1 du décor est
 * administrateur de la ferme rattachée, et son adresse est de celles que la dev envoie
 * réellement : `ShareLib::announce()` et `announceOpened()`, qui envoient, ne se lancent jamais ici.
 * Chaque test relève le dernier e-mail écrit en base avant et après, qui ne doit pas bouger.
 *
 * Le décor est celui de `LearningDateFixture` : un réseau réel, dont le lecteur fictif devient
 * membre hors de l'équipe (`seedMember()`), l'horloge figée à midi. Les ouvertures datées (ouverture
 * à venir, échéance) n'existent plus que pour une promotion, et s'éprouvent sur le groupe du décor.
 *
 * Lancement : dev/test package=learning file=ShareAnnounce
 */
class ShareAnnounceTest extends LearningDateFixtureTest {

	private int $lastMail;

	public function setUp(): void {

		parent::setUp();

		$this->lastMail = $this->getLastMail();

	}

	public function tearDown(): void {

		$this->assertEquals($this->lastMail, $this->getLastMail(), 'aucun e-mail écrit en base');

		parent::tearDown();

	}

	/**
	 * Ouverte aux membres : un e-mail par membre du réseau hors de l'équipe, qu'il ait rattaché une
	 * structure ou non, aucun pour l'équipe, un compte fermé, suspendu ou sorti du réseau. Le
	 * partage est réclamé, et un second passage n'envoie plus rien.
	 */
	public function testMembers(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seedMember();

		$eChapter = $this->createChapter($eCourse, 'zzTest module');
		$this->createLesson($eChapter, 'zzTest leçon 1', 30);
		$this->createLesson($eChapter, 'zzTest leçon 2', 30);

		$eUserAlone = $this->createUser('zztest.membre-seul@example.org');
		$this->join($eUserAlone, $this->eStructureNetwork, 'member');

		$eUserClosed = $this->createMember();
		\user\DropLib::closeNow($eUserClosed);

		$eUserSuspended = $this->createMember();
		// Aucun Lib ne suspend un compte : c'est un geste d'administration de la base. Le décor pose
		// l'état tel quel, `build()` n'ayant rien à juger sur une colonne qu'aucun formulaire n'écrit.
		\user\User::model()->update($eUserSuspended, ['status' => \user\User::SUSPENDED]);

		$eUserOut = $this->createMember();
		$this->leave($eUserOut, $this->eStructureNetwork);

		$eUserTeam = $this->createPerson();
		$this->join($eUserTeam, $this->eStructureNetwork);

		$this->assertEquals([], $this->shareNetwork($eCourse), 'décor : formation ouverte aux membres du réseau');

		$mails = $this->claim($eCourse);

		$this->assertTrue(isset($mails[self::EMAIL_READER]), 'le lecteur, membre du réseau, est prévenu');
		$this->assertTrue(isset($mails[$eUserAlone['email']]), 'un membre sans structure rattachée est prévenu');
		$this->assertFalse(isset($mails[$eUserTeam['email']]), 'l\'équipe du réseau n\'est pas prévenue');
		$this->assertFalse(isset($mails[$eUserSuspended['email']]), 'un compte suspendu n\'est pas prévenu');
		$this->assertFalse(isset($mails[$eUserOut['email']]), 'un compte sorti du réseau n\'est pas prévenu');

		foreach(array_keys($mails) as $email) {
			$this->assertTrue($email !== '', 'chaque e-mail a une adresse, celle du compte fermé étant vide');
		}

		[$title, , $html] = $mails[self::EMAIL_READER];

		$this->assertEquals(s("{value} vous propose une formation", $this->eStructureNetwork['name']), $title, 'sujet');
		$this->assertTrue(str_contains($html, encode($this->eStructureNetwork['name'])), 'le réseau est nommé');
		$this->assertFalse(str_contains($html, encode($this->eStructureFarm['name'])), 'la ferme du lecteur ne l\'est pas');
		$this->assertTrue(str_contains($html, s("Voir la formation et s'inscrire")), 'le bouton');
		$this->assertTrue(str_contains($html, '/formation/'.$eCourse['id']), 'le bouton mène à la formation');
		$this->assertTrue(str_contains($html, '2 leçons'), 'la durée compte les leçons');
		$this->assertFalse(str_contains($html, s("Échéance")), 'pas d\'échéance pour un réseau');
		$this->assertTrue(str_contains($html, encode($this->eLevel['name'])), 'le niveau');

		$this->assertEquals(0, $this->countToAnnounce($eCourse), 'le partage est réclamé');
		$this->assertEquals([], $this->claim($eCourse), 'un second passage n\'envoie rien');

	}

	/**
	 * Promotion ouverte dans trois jours : rien à l'enregistrement, ni dans la liste du cron ; le jour
	 * venu, la formation y entre et l'annonce part, une fois.
	 */
	public function testFuture(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eSection] = $this->seedSection();

		$this->share($eCourse, [$eSection['id'] => [$this->day(3), NULL]]);

		$this->assertEquals([], $this->claim($eCourse), 'rien avant l\'ouverture');
		$this->assertEquals(1, $this->countToAnnounce($eCourse), 'le partage attend son jour');
		$this->assertFalse(ShareLib::getCoursesToAnnounce()->offsetExists($eCourse['id']), 'le cron ne la prend pas avant son jour');

		$this->setClock($this->day(3).' 08:00:00');

		$this->assertTrue(ShareLib::getCoursesToAnnounce()->offsetExists($eCourse['id']), 'le cron la prend le jour de l\'ouverture');
		$this->assertTrue(isset($this->claim($eCourse)[self::EMAIL_READER]), 'le stagiaire est prévenu le jour de l\'ouverture');
		$this->assertFalse(ShareLib::getCoursesToAnnounce()->offsetExists($eCourse['id']), 'le cron ne la reprend plus');
		$this->assertEquals([], $this->claim($eCourse), 'un second passage n\'envoie rien');

	}

	/**
	 * Une échéance déjà passée quand le partage s'ouvre (un cron manqué, une saisie à rebours) : la
	 * formation ne se coche plus, l'annonce ne part pas, et le partage est marqué pour ne plus être
	 * relu.
	 */
	public function testDuePast(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eSection] = $this->seedSection();

		$this->share($eCourse, [$eSection['id'] => [$this->day(-10), $this->day(-1)]]);

		$this->assertEquals([], $this->claim($eCourse), 'aucun e-mail après l\'échéance');
		$this->assertEquals(0, $this->countToAnnounce($eCourse), 'le partage est marqué annoncé');

	}

	/**
	 * Avec une échéance à venir, la date se lit en clair.
	 */
	public function testDue(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eSection] = $this->seedSection();

		$this->share($eCourse, [$eSection['id'] => [\Clock::date(), $this->day(30)]]);

		$html = $this->claim($eCourse)[self::EMAIL_READER][2];

		$this->assertTrue(str_contains($html, \util\DateUi::textual($this->day(30), \util\DateUi::DATE)), 'l\'échéance');

	}

	/**
	 * Une formation datée : la prochaine date, sa durée et son prix, lu sur la date qui reprend celui
	 * de sa formation.
	 */
	public function testDated(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->join($this->eUserReader, $this->eStructureNetwork, 'member');

		[$eCourse] = $this->seedCourse(quiz: FALSE);

		$eCourse['price'] = 120.0;
		CourseLib::update($eCourse, ['price']);

		$eDate = $this->createDate($eCourse, 5);

		ShareLib::deleteByCourse($eCourse);
		$this->assertEquals([], $this->shareNetwork($eCourse), 'décor : partage reposé après la date');

		$html = $this->claim($this->getCourse($eCourse['id']))[self::EMAIL_READER][2];

		$this->assertTrue(str_contains($html, 'Prochaine date'), 'la prochaine date');
		$this->assertTrue(str_contains($html, DateUi::getDays(DateLib::getById($eDate['id']))), 'le jour de la prochaine date');
		$this->assertTrue(str_contains($html, 'pour les autres publics'), 'le prix repris de la formation');
		$this->assertTrue(str_contains($html, 'Durée'), 'la durée de la date');

	}

	/**
	 * Une formation datée sans date à venir : ni prochaine date ni prix.
	 */
	public function testDatedWithoutDate(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->join($this->eUserReader, $this->eStructureNetwork, 'member');

		[$eCourse] = $this->seedCourse(quiz: FALSE);

		$html = $this->claim($eCourse)[self::EMAIL_READER][2];

		$this->assertFalse(str_contains($html, 'Prochaine date'), 'pas de prochaine date');
		$this->assertFalse(str_contains($html, 'Prix'), 'pas de prix sans date');

	}

	/**
	 * Un membre déjà inscrit à la formation la connaît, de même que celui dont la demande attend : ni
	 * l'un ni l'autre n'est prévenu.
	 */
	public function testEnrolled(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seedMember();

		EnrollmentLib::create(new Enrollment([
			'user' => $this->eUserReader,
			'course' => $eCourse,
			'structure' => new \structure\Structure()
		]));

		$eUserRequested = $this->createMember();

		// Une demande écrite telle que la pose l'inscription d'un réseau qui confirme : `build()` n'y
		// jugerait que la structure du compte, que le test laisse vide, et `EnrollmentLib::create()`
		// n'écrit qu'une inscription confirmée.
		$eEnrollmentRequested = new Enrollment([
			'user' => $eUserRequested,
			'course' => $eCourse,
			'structure' => new \structure\Structure(),
			'status' => Enrollment::REQUESTED,
			'fundingRequested' => TRUE,
			'confirmedAt' => NULL
		]);

		Enrollment::model()->insert($eEnrollmentRequested);

		$this->assertEquals([], $this->shareNetwork($eCourse), 'décor : formation ouverte aux membres du réseau');

		$mails = $this->claim($eCourse);

		$this->assertFalse(isset($mails[self::EMAIL_READER]), 'un inscrit n\'est pas prévenu');
		$this->assertFalse(isset($mails[$eUserRequested['email']]), 'une demande en attente non plus');

	}

	/**
	 * Fermer puis rouvrir aux membres crée une ligne neuve, que le cron réclame, mais le compte déjà
	 * prévenu ne l'est pas deux fois.
	 */
	public function testOnce(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seedMember();

		$this->assertEquals([], $this->shareNetwork($eCourse), 'décor : ouverte aux membres');
		$this->assertTrue(isset($this->claim($eCourse)[self::EMAIL_READER]), 'première annonce');

		$this->assertEquals([], $this->shareNetwork($eCourse, FALSE), 'décor : ouverte à personne');
		$this->assertEquals([], $this->shareNetwork($eCourse), 'décor : rouverte aux membres');

		$this->assertEquals(1, $this->countToAnnounce($eCourse), 'décor : la ligne rouverte est neuve');
		$this->assertFalse(isset($this->claim($eCourse)[self::EMAIL_READER]), 'rouvrir ne renvoie pas l\'annonce');
		$this->assertEquals(0, $this->countToAnnounce($eCourse), 'la ligne rouverte est réclamée');

	}

	/**
	 * Une formation dépubliée garde ses partages : le cron ne la prend pas, et rien n'est réclamé.
	 */
	public function testDraft(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seedMember();

		$this->assertEquals([], $this->shareNetwork($eCourse), 'décor : formation ouverte aux membres du réseau');

		// Posé sans `build()`, comme `createCourse()` : la dépublication par l'écran passe par un
		// panneau qui n'a rien à juger ici.
		$eCourse['status'] = Course::DRAFT;
		CourseLib::update($eCourse, ['status']);

		$this->assertFalse(ShareLib::getCoursesToAnnounce()->offsetExists($eCourse['id']), 'le cron ne prend pas un brouillon');
		$this->assertEquals([], $this->claim($this->getCourse($eCourse['id'])), 'rien pour un brouillon');
		$this->assertEquals(1, $this->countToAnnounce($eCourse), 'le partage attend la publication');

	}

	/**
	 * Une promotion d'un groupe de formation : ses stagiaires sont prévenus, sous le nom du groupe.
	 */
	public function testSection(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eSection] = $this->seedSection();

		$this->share($eCourse, [$eSection['id'] => [\Clock::date(), NULL]]);

		$mails = $this->claim($eCourse);

		$this->assertTrue(isset($mails[self::EMAIL_READER]), 'le stagiaire de la promotion est prévenu');

		[$title, , $html] = $mails[self::EMAIL_READER];

		$this->assertEquals('Une nouvelle formation pour votre promotion', $title, 'sujet');
		$this->assertTrue(str_contains($html, 'Groupe'), 'le groupe et non le réseau');
		$this->assertTrue(str_contains($html, 'votre promotion «&nbsp;zzTest promotion&nbsp;»'), 'la promotion est nommée entre guillemets');
		$this->assertTrue(str_contains($html, 'Aucune, la formation reste ouverte'), 'sans échéance, la ligne le dit');

	}

	/**
	 * Les annonces partent avec leur formation.
	 */
	public function testDelete(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seedMember();

		$this->assertEquals([], $this->shareNetwork($eCourse), 'décor : formation ouverte aux membres du réseau');
		$this->assertTrue(isset($this->claim($eCourse)[self::EMAIL_READER]), 'décor : annonce partie');

		CourseLib::delete($eCourse);

		$this->assertEquals(0, Announcement::model()->whereCourse($eCourse)->count(), 'plus aucune annonce de la formation');

	}

	/** Les e-mails réclamés, indexés par adresse. */
	private function claim(Course $eCourse): array {
		return $this->claimed($this->claimList($eCourse));
	}

	private function claimList(Course $eCourse): array {

		$this->forgetMemoization();

		return ShareLib::claimAnnounce($eCourse);

	}

	private function claimed(array $mails): array {

		$claimed = [];

		foreach($mails as ['to' => $to, 'content' => $content]) {
			$claimed[$to] = $content;
		}

		return $claimed;

	}

	private function countToAnnounce(Course $eCourse): int {

		return Share::model()
			->whereCourse($eCourse)
			->whereAnnouncedAt(NULL)
			->count();

	}

	/**
	 * La formation en ligne du décor, le lecteur devenu membre du réseau hors de son équipe.
	 */
	private function seedMember(): Course {

		$this->join($this->eUserReader, $this->eStructureNetwork, 'member');

		return $this->seed();

	}

	/** Un compte fictif membre du réseau hors de son équipe. */
	private function createMember(): \user\User {

		$eUser = $this->createPerson();
		$this->join($eUser, $this->eStructureNetwork, 'member');

		return $eUser;

	}

	/**
	 * Une formation du groupe de formation du décor et une promotion où le lecteur est stagiaire.
	 *
	 * @return array{0: Course, 1: \structure\Section}
	 */
	private function seedSection(): array {

		$this->actAs($this->eUserAdmin);

		$eStructureEducation = $this->getCommunity(self::COMMUNITY_EDUCATION);

		// Un stagiaire est un membre du groupe qui n'encadre pas : `join()` le ferait administrateur.
		$eMember = \structure\MemberLib::getByUserAndStructure($this->eUserReader, $eStructureEducation);

		if($eMember->empty()) {
			\structure\MemberLib::create(new \structure\Member([
				'user' => $this->eUserReader,
				'structure' => $eStructureEducation,
				'role' => \structure\RoleLib::getOne($eStructureEducation, 'student'),
				'status' => \structure\Member::IN
			]));
		}

		$eSection = $this->createSection($eStructureEducation, 'zzTest promotion', [$this->eUserReader]);

		$eCourse = $this->createCourse($eStructureEducation, Course::PUBLISHED, Course::PRIVATE);

		return [$eCourse, $eSection];

	}

}
?>
