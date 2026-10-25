<?php
namespace learning;

require_once __DIR__.'/LearningDateFixture.t.php';

/**
 * Annuler une ligne confirmée d'une date retire l'accès aux leçons au compte qui n'en a pas d'autre
 * (lot L10e, option A) : `DateEnrollmentLib::fillLosesLessons()` le calcule pour le compte de la
 * ligne, et les trois e-mails d'annulation, le panneau de la personne, la confirmation du réseau et
 * le panneau d'annulation de la date le disent.
 *
 * Chaque cas est comparé au droit réel : le compte de la ligne connecté, `Course::canReadLessons()`
 * est lu avant et après l'annulation, écrite par le réseau dans la transaction du test.
 * `losesLessons` vaut « le droit était là et ne l'est plus », sauf sur une formation sans leçon, où
 * le droit se perd sans rien retirer à lire : la phrase n'y a pas de sens, et le cas le dit.
 *
 * Lot L10e-lien : `readsCourse` dit si le compte lira encore la fiche après l'annulation, quel que
 * soit l'état de la ligne, et se compare à `Course::canRead()` lu après l'annulation. « Voir les
 * autres dates » des e-mails d'annulation par la date et par le réseau le suit : la fiche pour qui la
 * lit encore, la page publique du réseau sinon, comme pour une ligne de la page publique.
 *
 * Les lignes appartiennent à des comptes fictifs en `example.org`, jamais au compte du décor : un
 * e-mail vers une adresse hors de `MailSetting::$devSendOnly` ne part pas. L'annulation par la
 * personne et avec la date ne s'écrivent pas : leurs e-mails se rendent sur la ligne annulée par le
 * réseau, l'auteur de l'annulation changé sur l'élément lu.
 *
 * Lancement : dev/test package=learning file=DateEnrollmentLessons
 */
class DateEnrollmentLessonsTest extends LearningDateFixtureTest {

	/**
	 * Le morceau commun aux trois e-mails et au panneau de la personne.
	 */
	const PHRASE = 'plus accès aux leçons';

	private int $accounts = 0;

	public function testLoses(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse();
		$eDate = $this->createDate($eCourse, 4);

		$e = $this->enrollConfirmed($eDate, $this->createOutsider());

		$this->check('hors du réseau, une seule date', $e, TRUE, reads: FALSE);

	}

	/**
	 * Lot L14 : une formation retenue au catalogue public se lit comme une formation de l'équipe, par
	 * tout compte connecté. La personne hors du réseau garde donc la fiche et les leçons après
	 * l'annulation, et aucun des textes ne lui annonce de perte.
	 */
	public function testCatalog(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse();
		$eDate = $this->createDate($eCourse, 4);

		$eCourse['catalog'] = Course::ACCEPTED;
		CourseLib::updateCatalog($eCourse);

		$e = $this->enrollConfirmed($eDate, $this->createOutsider());

		$this->check('hors du réseau, formation au catalogue', $e, FALSE, reads: TRUE);

	}

	/**
	 * L'inscription n'ouvre que la formation en ligne d'un réseau (`Course::canRead()`) : inscrit aux
	 * leçons d'une formation datée, le compte hors du réseau la perd avec sa date, et
	 * `fillReadsCourse()` n'a donc aucune branche à en recopier.
	 */
	public function testEnrolled(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse();
		$eDate = $this->createDate($eCourse, 4);

		$eUser = $this->createOutsider();
		$e = $this->enrollConfirmed($eDate, $eUser);

		// Hors build(), qui pèserait le droit du compte connecté : le décor veut la ligne d'un autre
		// compte, celle que « Commencer la formation » écrit une fois la date confirmée.
		EnrollmentLib::create(new Enrollment([
			'user' => $eUser,
			'course' => $eCourse,
			'structure' => new \structure\Structure()
		]));

		$this->assertTrue(EnrollmentLib::isEnrolled($eUser, $eCourse), 'le compte est inscrit aux leçons');

		$this->check('hors du réseau, inscrit aux leçons d\'une formation datée', $e, TRUE, reads: FALSE);

	}

	/**
	 * Lot L14 : l'équipe de Ouvretaferme lit une formation qui lui est proposée ou qu'elle a refusée,
	 * pour en juger (`Course::canDecide()`). Le compte de la ligne est poussé dans
	 * `UserSetting::$admin` le temps du cas, et la même formation, lue par un compte qui n'est pas de
	 * l'équipe, se perd : la condition porte bien sur le compte et non sur l'état seul.
	 */
	public function testCatalogProposed(): void {
		$this->checkDecider(Course::PROPOSED);
	}

	public function testCatalogRefused(): void {
		$this->checkDecider(Course::REFUSED);
	}

	private function checkDecider(string $catalog): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse();

		$eCourse['catalog'] = $catalog;
		CourseLib::updateCatalog($eCourse);

		$eUserDecider = $this->createOutsider();

		// Une date par compte : la liste du réseau d'une date ne doit porter que la ligne du cas.
		$eDateEnrollmentDecider = $this->enrollConfirmed($this->createDate($eCourse, 4), $eUserDecider);
		$eDateEnrollmentOther = $this->enrollConfirmed($this->createDate($eCourse, 8), $this->createOutsider());

		$adminInitial = \user\UserSetting::$admin;
		\user\UserSetting::$admin[] = $eUserDecider['id'];

		try {
			$this->check('équipe de Ouvretaferme, formation '.$catalog, $eDateEnrollmentDecider, FALSE, reads: TRUE);
			$this->check('hors du réseau et de l\'équipe, formation '.$catalog, $eDateEnrollmentOther, TRUE, reads: FALSE);
		} finally {
			\user\UserSetting::$admin = $adminInitial;
		}

	}

	/**
	 * « Perdent l'accès » (11/10) : l'ouverture aux membres ne donne pas la lecture. Le
	 * membre du réseau confirmé à une seule date la perd avec elle, comme une personne hors du
	 * réseau.
	 */
	public function testMembersOpen(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse();
		$eDate = $this->createDate($eCourse, 4);

		$eUser = $this->createPerson();
		$this->join($eUser, $this->eStructureNetwork, 'member');

		$e = $this->enrollConfirmed($eDate, $eUser);

		$this->check('membre du réseau, formation ouverte aux membres', $e, TRUE, reads: FALSE);

	}

	public function testTeam(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse();
		$eDate = $this->createDate($eCourse, 4);

		$eUser = $this->createOutsider();
		$this->join($eUser, $this->eStructureNetwork);

		$e = $this->enrollConfirmed($eDate, $eUser);

		$this->check('équipe du réseau', $e, FALSE, reads: TRUE);

	}

	public function testOtherDate(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse();
		$eDate = $this->createDate($eCourse, 4);
		$eDateOther = $this->createDate($eCourse, 8);

		$eUser = $this->createOutsider();

		$e = $this->enrollConfirmed($eDate, $eUser);
		$this->enrollConfirmed($eDateOther, $eUser);

		$this->check('seconde date confirmée', $e, FALSE, reads: TRUE);

	}

	public function testNoLesson(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->seedCourse();

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE, type: Course::ONSITE);
		$this->assertFalse(ChapterLib::hasLessons($eCourse), 'la formation ne porte aucune leçon');

		$eDate = $this->createDate($eCourse, 4);

		$e = $this->enrollConfirmed($eDate, $this->createOutsider());

		$this->check('formation sans leçon', $e, FALSE, reads: FALSE, lessons: FALSE);

	}

	public function testRequest(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse();
		$eDate = $this->createDate($eCourse, 4);

		$e = $this->enrollRequested($eDate, $this->createOutsider());

		$this->check('demande', $e, FALSE, reads: FALSE);

	}

	/**
	 * Reprise R3 : la personne a commencé la formation, et attend une place. Elle ne lit rien avant
	 * comme après, et une attente n'a jamais donné l'accès : un prédicat qui compterait l'attente la
	 * dirait perdante. La place est prise par l'équipe du réseau, qui garde l'accès : la liste de la
	 * date ne porte la phrase pour personne.
	 */
	public function testWaitingStartedByShare(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse();
		$eDate = $this->createDate($eCourse, 4);

		$this->enrollConfirmed($eDate, $this->createTeamMember());

		$eDateFull = DateLib::getById($eDate['id']);
		$eDateFull['seats'] = 1;
		DateLib::update($eDateFull, ['seats']);

		$eUser = $this->createPerson();

		EnrollmentLib::create(new Enrollment(['user' => $eUser, 'course' => $eCourse, 'structure' => new \structure\Structure()]));
		$this->assertTrue(EnrollmentLib::getByUserAndCourse($eUser, $eCourse)->notEmpty(), 'la formation est commencée');

		$e = DateEnrollmentLib::getCreateElement(DateLib::getById($eDate['id']), $eUser);
		$e['structure'] = new \structure\Structure();
		$e['trainee'] = DateEnrollment::FARMER;
		$e['committedAt'] = \Clock::datetime();
		DateEnrollmentLib::create($e);

		$e = DateEnrollmentLib::getByDateAndUser($eDate, $eUser);
		$this->assertEquals(DateEnrollment::WAITING, $e['status'], 'la date est pleine : liste d\'attente');

		$this->check('attente, formation commencée', $e, FALSE, reads: FALSE);

	}

	/**
	 * Une demande ne donne pas l'accès, et l'ouverture aux membres non plus : la demande d'un membre
	 * du réseau ne perd rien, ne lit pas la fiche, et ses e-mails mènent à la page publique du
	 * réseau.
	 */
	public function testRequestMember(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse();
		$eDate = $this->createDate($eCourse, 4);

		$eUser = $this->createPerson();
		$this->join($eUser, $this->eStructureNetwork, 'member');

		$e = $this->enrollRequested($eDate, $eUser);

		$this->check('demande d\'un membre, formation ouverte aux membres', $e, FALSE, reads: FALSE);

	}

	/**
	 * Le lien mène là où la personne lit encore, sur les vraies pages : qui perd l'accès trouve
	 * l'autre date de la formation sur la page publique du réseau, que la fiche lui refuse ; qui le
	 * garde la trouve sur la fiche.
	 */
	public function testOtherDatesLink(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse();
		$eDate = $this->createDate($eCourse, 4);
		$eDateOther = $this->createDate($eCourse, 8);

		$eCommunity = \structure\CommunityLib::getByStructure($eCourse['community']);

		$eUserLoses = $this->createOutsider();
		$eDateEnrollmentLoses = $this->enrollConfirmed($eDate, $eUserLoses);

		$eUserKeeps = $this->createTeamMember();
		$eDateEnrollmentKeeps = $this->enrollConfirmed($eDate, $eUserKeeps);

		DateEnrollmentLib::cancel($this->getLine($eDateEnrollmentLoses['id']), DateEnrollment::COMMUNITY);
		DateEnrollmentLib::cancel($this->getLine($eDateEnrollmentKeeps['id']), DateEnrollment::COMMUNITY);

		[['content' => [, $text]]] = DateEnrollmentLib::getMails($this->getLine($eDateEnrollmentLoses['id']), 'canceled');
		$this->assertTrue(str_contains($text, DatePublicUi::urlList($eCommunity)), 'qui perd l\'accès : le lien mène à la page publique du réseau');

		$this->actAs($eUserLoses);
		$fiche = $this->executeView('formation/'.$eCourse['id'], []);
		$public = $this->executeView('reseau/'.$eCommunity['fqn'].'/formations', []);
		$this->actAs($this->eUserAdmin);

		$this->assertTrue(is_string($fiche), 'la fiche lui est refusée');
		$this->assertTrue($public instanceof \stdClass, 'la page publique s\'ouvre');

		$cDatePublic = $public->cDate->find(fn(Date $eDatePublic) => $eDatePublic['id'] === $eDateOther['id']);

		$this->assertEquals(1, $cDatePublic->count(), 'et propose l\'autre date de la formation : '.implode(', ', $public->cDate->getIds()));
		$this->assertEquals(NULL, $cDatePublic->first()['eDateEnrollment']['status'], 'où il n\'a aucune ligne, donc où il peut s\'inscrire');

		[['content' => [, $text]]] = DateEnrollmentLib::getMails($this->getLine($eDateEnrollmentKeeps['id']), 'canceled');
		$this->assertTrue(str_contains($text, \Lime::getUrl().CourseUi::url($eCourse)), 'qui garde l\'accès : le lien mène à la fiche');

		$this->actAs($eUserKeeps);
		$fiche = $this->executeView('formation/'.$eCourse['id'], []);
		$this->actAs($this->eUserAdmin);

		$this->assertTrue($fiche instanceof \stdClass, 'qui s\'ouvre');
		$this->assertTrue(in_array($eDateOther['id'], $fiche->cDate->getIds(), TRUE), 'et propose l\'autre date');

	}

	/**
	 * Une demande annulée avec sa date, par `DateLib::cancel()` comme le réseau le fait : son e-mail
	 * lit `readsCourse`, que l'UI exige au lieu de retomber en silence sur la fiche. Rendu sans
	 * `fillCanceled()`, il lève en nommant la propriété ; rendu après, il est celui de `getMails()`.
	 */
	public function testDateCancelRequest(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse();
		$eDate = $this->createDate($eCourse, 4);

		$e = $this->enrollRequested($eDate, $this->createOutsider());

		DateLib::cancel(DateLib::getById($eDate['id']));

		$eDateEnrollment = $this->getLine($e['id']);
		$this->assertEquals([DateEnrollment::CANCELED, DateEnrollment::DATE], [$eDateEnrollment['status'], $eDateEnrollment['canceledBy']], 'demande annulée avec sa date');

		try {
			new DateEnrollmentUi()->getDateCanceledMail($eDateEnrollment);
			$message = 'aucune exception';
		} catch(\ElementException $exception) {
			$message = $exception->getMessage();
		}

		$this->assertTrue(str_contains($message, 'readsCourse'), 'sans readsCourse, l\'e-mail refuse de se rendre : '.$message);

		DateEnrollmentLib::fillCanceled($eDateEnrollment);

		$this->assertFalse($eDateEnrollment['readsCourse'], 'la demande ne lit pas la fiche');
		$this->assertFalse($eDateEnrollment['losesLessons'], 'et ne perd aucune leçon');

		$content = new DateEnrollmentUi()->getDateCanceledMail($eDateEnrollment);

		$this->assertEquals(DateEnrollmentLib::getMails($this->getLine($e['id']), 'canceled')[0]['content'], $content, 'le même e-mail que getMails()');
		$this->assertTrue(str_contains($content[1], DatePublicUi::urlList(\structure\CommunityLib::getByStructure($eCourse['community']))), 'qui mène à la page publique du réseau');

	}

	public function testPublicLine(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse();
		$eDate = $this->createDate($eCourse, 4);

		$e = $this->enrollPublicConfirmed($eDate, 'zztest.lecons-sans-compte@example.org');

		$this->check('ligne sans compte', $e, FALSE, reads: FALSE);

	}

	/**
	 * Le panneau d'annulation de la date : la ligne ne paraît que si l'une des inscriptions encore
	 * ouvertes perd l'accès.
	 */
	public function testDatePanel(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse();
		$eDate = $this->createDate($eCourse, 4);

		$this->enrollConfirmed($eDate, $this->createTeamMember());

		$this->assertFalse($this->getDatePanel($eDate)['losesLessons'], 'la seule inscrite, de l\'équipe du réseau, garde l\'accès : pas de ligne');

		$this->enrollConfirmed($eDate, $this->createOutsider());

		$this->assertTrue($this->getDatePanel($eDate)['losesLessons'], 'une inscrite hors du réseau le perd : la ligne paraît');

	}

	/**
	 * La personne annule elle-même depuis la fiche. Recharger la fiche échouerait sur `assertRead`
	 * pour qui vient d'y perdre l'accès : elle part vers les dates publiques du réseau, avec le
	 * message habituel. Qui garde l'accès voit la fiche se recharger, comme avant.
	 */
	public function testCancelByUser(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse();
		$eDate = $this->createDate($eCourse, 4);

		$eCommunity = \structure\CommunityLib::getByStructure($eCourse['community']);
		$expected = DatePublicUi::urlQuery(DatePublicUi::urlList($eCommunity), ['success' => 'learning\DateEnrollment::canceled'], FALSE);

		$eUserLoses = $this->createOutsider();
		$eDateEnrollmentLoses = $this->enrollConfirmed($eDate, $eUserLoses);

		$this->actAs($eUserLoses);
		$result = $this->executePage('learning/dateEnrollment:doCancel', ['id' => (string)$eDateEnrollmentLoses['id']], 'POST', $redirect);
		$fiche = $this->executeView('formation/'.$eCourse['id'], []);
		$this->actAs($this->eUserAdmin);

		$this->assertEquals([\RedirectAction::class, NULL], $result, 'qui perd l\'accès : une redirection, pas un rechargement');
		$this->assertEquals($expected, $redirect, 'vers les dates publiques du réseau, avec le message d\'annulation');
		$this->assertTrue(is_string($fiche), 'la fiche qu\'un rechargement rouvrirait la refuse bien');
		$this->assertEquals(DateEnrollment::CANCELED, $this->getLine($eDateEnrollmentLoses['id'])['status'], 'et la ligne est annulée');

		$eUserKeeps = $this->createTeamMember();
		$eDateEnrollmentKeeps = $this->enrollConfirmed($eDate, $eUserKeeps);

		$this->actAs($eUserKeeps);
		$result = $this->executePage('learning/dateEnrollment:doCancel', ['id' => (string)$eDateEnrollmentKeeps['id']], 'POST', $redirect);
		$fiche = $this->executeView('formation/'.$eCourse['id'], []);
		$this->actAs($this->eUserAdmin);

		$this->assertEquals([\ReloadAction::class, NULL], $result, 'qui garde l\'accès : la fiche se recharge');
		$this->assertTrue($fiche instanceof \stdClass, 'et elle s\'ouvre');
		$this->assertEquals(DateEnrollment::CANCELED, $this->getLine($eDateEnrollmentKeeps['id'])['status'], 'et la ligne est annulée');

	}

	/**
	 * Un compte fictif hors de la ferme rattachée : il ne lit la formation que par sa date.
	 */
	private function createOutsider(): \user\User {

		$this->accounts++;

		return $this->createUser('zztest.lecons-'.$this->accounts.'@example.org');

	}

	/**
	 * Un compte fictif de l'équipe du réseau : il lit la formation sans sa date, et garde l'accès
	 * quand elle s'annule.
	 */
	private function createTeamMember(): \user\User {

		$eUser = $this->createOutsider();
		$this->join($eUser, $this->eStructureNetwork);

		return $eUser;

	}

	/** La date telle que la page du panneau d'annulation la prépare. */
	private function getDatePanel(Date $eDate): Date {

		$eDatePanel = DateLib::getById($eDate['id']);

		DateEnrollmentLib::fillLosesLessonsByDate($eDatePanel);

		return $eDatePanel;

	}

	/**
	 * `$expected` est la réponse attendue, que chaque rendu doit dire ou taire. `$reads` dit si le
	 * compte lit encore la fiche après l'annulation, ce que suit le lien des autres dates. `$lessons`
	 * à faux pour la formation sans leçon, seule exception au droit perdu.
	 */
	private function check(string $case, DateEnrollment $e, bool $expected, bool $reads, bool $lessons = TRUE): void {

		$eDateEnrollment = $this->getLine($e['id']);

		// Avant : le panneau de la personne et la liste du réseau.
		$losesLessons = DateEnrollmentLib::losesLessons($eDateEnrollment);
		$this->assertEquals($expected, $losesLessons, $case.' : losesLessons avant l\'annulation');

		[, , , $confirm] = DateEnrollmentUi::getMineTexts($eDateEnrollment['status'], $eDateEnrollment['losesLessons']);
		$this->assertEquals($expected, str_contains($confirm, self::PHRASE), $case.' : confirmation du panneau de la personne : '.$confirm);

		$eDateList = $this->prepareDate($eDateEnrollment['date']);
		$cDateEnrollmentList = DateEnrollmentLib::getByDate($eDateList);
		DateEnrollmentLib::fillLosesLessons($eDateList['course'], $cDateEnrollmentList);

		$this->assertEquals($expected ? 1 : 0, $cDateEnrollmentList->find(fn(DateEnrollment $eDateEnrollmentList) => $eDateEnrollmentList['losesLessons'])->count(), $case.' : confirmation de la liste du réseau');

		$readBefore = $this->canReadLessonsAs($eDateEnrollment);

		$this->actAs($this->eUserAdmin);
		DateEnrollmentLib::cancel($this->getLine($e['id']), DateEnrollment::COMMUNITY);

		$readAfter = $this->canReadLessonsAs($eDateEnrollment);
		$readCourseAfter = $this->canReadAs($eDateEnrollment);
		$this->actAs($this->eUserAdmin);

		$eCommunity = \structure\CommunityLib::getByStructure($eDateEnrollment['date']['course']['community']);
		$urlPublic = DatePublicUi::urlList($eCommunity);
		$urlCourse = \Lime::getUrl().CourseUi::url($eDateEnrollment['date']['course']);
		$phrasePublic = s("Les autres dates restent ouvertes sur la page des formations de {value}.", encode($eDateEnrollment['date']['course']['community']['name']));
		$phraseCourse = s("Les autres dates de la formation restent ouvertes sur sa fiche.");

		// Après : les trois e-mails, sur la ligne annulée.
		foreach([DateEnrollment::COMMUNITY, DateEnrollment::DATE, DateEnrollment::USER] as $canceledBy) {

			$eDateEnrollmentCanceled = $this->getLine($e['id']);
			$this->assertEquals(DateEnrollment::CANCELED, $eDateEnrollmentCanceled['status'], $case.' : ligne annulée');

			$eDateEnrollmentCanceled['canceledBy'] = $canceledBy;

			[['content' => [, $text, $html]]] = DateEnrollmentLib::getMails($eDateEnrollmentCanceled, 'canceled');

			$this->assertEquals($expected, $eDateEnrollmentCanceled['losesLessons'], $case.' : losesLessons après l\'annulation ('.$canceledBy.')');
			$this->assertEquals($expected, str_contains($text, self::PHRASE), $case.' : e-mail '.$canceledBy.' en texte');
			$this->assertEquals($expected, str_contains($html, self::PHRASE), $case.' : e-mail '.$canceledBy.' en HTML');

			// L'e-mail au réseau nomme la personne dans son titre : la phrase sur les leçons ne la renomme pas.
			if($canceledBy === DateEnrollment::USER) {
				$this->assertEquals($expected, str_contains($text, s("Cette personne n'a plus accès aux leçons de la formation.")), $case.' : e-mail au réseau, sans répéter le nom');
				continue;
			}

			$this->assertEquals($reads, $eDateEnrollmentCanceled['readsCourse'], $case.' : readsCourse après l\'annulation ('.$canceledBy.')');
			$this->assertEquals($readCourseAfter, $eDateEnrollmentCanceled['readsCourse'], $case.' : readsCourse suit canRead() après l\'annulation ('.$canceledBy.')');

			// « Voir les autres dates » : la page publique pour une ligne qui en vient ou un compte qui ne lit plus la fiche.
			$public = ($eDateEnrollment->isPublic() or $reads === FALSE);

			foreach(['texte' => $text, 'HTML' => $html] as $format => $content) {
				$this->assertEquals($public, str_contains($content, $urlPublic), $case.' : e-mail '.$canceledBy.' en '.$format.', lien vers la page publique');
				$this->assertEquals($public, str_contains($content, $phrasePublic), $case.' : e-mail '.$canceledBy.' en '.$format.', phrase de la page publique');
				$this->assertEquals($public === FALSE, str_contains($content, $urlCourse), $case.' : e-mail '.$canceledBy.' en '.$format.', lien vers la fiche');
				$this->assertEquals($public === FALSE, str_contains($content, $phraseCourse), $case.' : e-mail '.$canceledBy.' en '.$format.', phrase de la fiche');
			}

		}

		// Concordance de la réponse calculée avec le droit réel du compte de la ligne.
		$this->assertEquals($readCourseAfter, $reads, $case.' : le cas attend ce que canRead() rend après l\'annulation');

		$lost = ($readBefore and $readAfter === FALSE);

		if($lessons) {
			$this->assertEquals($lost, $losesLessons, $case.' : losesLessons suit canReadLessons() (avant '.var_export($readBefore, TRUE).', après '.var_export($readAfter, TRUE).')');
		} else {
			$this->assertTrue($lost, $case.' : le droit se perd bien');
			$this->assertFalse($losesLessons, $case.' : mais sans leçon, rien à perdre ni à dire');
		}

	}

	private function canReadAs(DateEnrollment $eDateEnrollment): bool {

		$this->actAs($eDateEnrollment['user']);

		return $this->getCourse($eDateEnrollment['date']['course']['id'])->canRead();

	}

	private function canReadLessonsAs(DateEnrollment $eDateEnrollment): bool {

		$this->actAs($eDateEnrollment['user']);

		return $this->getCourse($eDateEnrollment['date']['course']['id'])->canReadLessons();

	}

}
?>
