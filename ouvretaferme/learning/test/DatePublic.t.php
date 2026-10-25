<?php
namespace learning;

require_once __DIR__.'/LearningNetworkFixture.t.php';

/**
 * La page publique des formations d'un réseau (lot L10c) : la liste des dates qu'elle propose et
 * celles qu'elle tait, la racine du réseau, l'inscription sans compte et son jeton, le doublon et
 * la réouverture, le plafond par adresse, le débit par poste, l'annulation par le jeton, les
 * e-mails d'une ligne sans compte, le rattachement au compte qui se connecte (arbitrages 1 a et 4),
 * la même inscription avec et sans session, la lecture ouverte à une personne confirmée
 * (arbitrage 3 a), et le verrou de la date sur deux connexions.
 *
 * Le décor est celui de `LearningNetworkFixtureTest`, réseau G3 du décor commun. Les routes se jouent par leur adresse, comme le navigateur les demande :
 * une route du domaine réseau n'a pas d'autre nom. Les personnes sont fictives, en `example.org`.
 *
 * Chaque envoi de la page compte dans le débit du poste (`learning-enrollment-ip`, 20 par heure),
 * que le lanceur partage avec les autres tests : le compteur est vidé avant et après chaque test.
 *
 * Lancement : dev/test package=learning file=DatePublic
 */
class DatePublicTest extends LearningNetworkFixtureTest {

	const THROTTLE = 'learning-enrollment-ip';

	private int $people = 0;

	/**
	 * Les données de la dernière `ViewAction` rendue par `call()`, pour lire l'élément que la page
	 * prépare à la vue.
	 */
	private ?\stdClass $viewData = NULL;

	/**
	 * La dernière `ViewAction` levée par `call()`, pour la rendre : son chemin seul ne dit pas si le
	 * cadriciel y trouve une vue, un nom sans « : » en tête étant lu comme une adresse de fichier.
	 */
	private ?\ViewAction $viewAction = NULL;

	public function setUp(): void {

		parent::setUp();

		\user\ThrottleAiLib::clear(self::THROTTLE, getIp());

	}

	public function tearDown(): void {

		\user\ThrottleAiLib::clear(self::THROTTLE, getIp());

		parent::tearDown();

	}

	/**
	 * Les dates que la liste propose, et le refus des autres à l'étape 1 : brouillon, passée,
	 * annulée, d'un autre réseau. Une formation en ligne n'a pas de date, `Date::acceptPublic()`
	 * l'écarte par `isDated()` et la liste par son type.
	 */
	public function testList(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eDate = $this->seedDate(NULL);

		$eCourseDraft = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE, type: Course::ONSITE);
		$eDateDraft = $this->createDate($eCourseDraft, NULL);

		$eDatePast = $this->createDate($eDate['course'], NULL);
		$eDatePast = $this->writeSequences($eDatePast, [[$this->day(-10).' 09:00:00', $this->day(-10).' 17:00:00']]);

		$eDateCanceled = $this->createDate($eDate['course'], NULL);
		DateLib::cancel($eDateCanceled);

		$eStructureOther = $this->getNetworkOther();
		$eCourseOther = $this->createCourse($eStructureOther, Course::PUBLISHED, Course::PRIVATE, type: Course::ONSITE);
		$eDateOther = $this->createDate($eCourseOther, NULL);

		$this->actAs(new \user\User());

		$data = $this->executeView($this->getPath().'/formations', []);

		$this->assertTrue($data instanceof \stdClass, 'la liste se lit sans compte');

		$dates = $data->cDate->getIds();

		$this->assertTrue(in_array($eDate['id'], $dates), 'la date planifiée à venir est proposée');

		foreach(['brouillon' => $eDateDraft, 'passée' => $eDatePast, 'annulée' => $eDateCanceled, 'd\'un autre réseau' => $eDateOther] as $label => $eDateHidden) {

			$this->assertFalse(in_array($eDateHidden['id'], $dates), 'la date '.$label.' est tue');
			$this->assertEquals([\ViewAction::class, '/error:404'], $this->call('GET', $this->getPath().'/formations/'.$eDateHidden['id']), 'la date '.$label.' ne s\'ouvre pas');

		}

		$this->assertEquals([\ViewAction::class, NULL], $this->call('GET', $this->getPath().'/formations/'.$eDate['id']), 'l\'étape 1 de la date proposée s\'ouvre');
		$this->assertEquals([\ViewAction::class, '/error:404'], $this->call('GET', 'reseau/'.\structure\CommunityLib::getByStructure($eStructureOther)['fqn'].'/formations/'.$eDate['id']), 'et pas sous l\'adresse d\'un autre réseau');

	}

	/**
	 * Les formations en ligne du réseau paraissent sous ses dates dès qu'elles sont publiées, sans
	 * qu'il les ait ouvertes à ses membres (09/10). Leur carte mène à la fiche, sur le
	 * domaine du produit, et sort de l'iframe quand la page est intégrée.
	 */
	public function testListOnline(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seed();
		$eCourseDraft = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);
		$eCourseDated = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE, type: Course::ONSITE);
		$eCourseOther = $this->createCourse($this->getNetworkOther(), Course::PUBLISHED, Course::PRIVATE);

		$this->assertEquals(0, Share::model()->whereCourse($eCourse)->count(), 'la formation publiée n\'est ouverte à personne');

		$this->actAs(new \user\User());

		$data = $this->executeView($this->getPath().'/formations', []);

		$this->assertTrue($data instanceof \stdClass, 'la liste se lit sans compte');

		$courses = $data->cCourse->getIds();

		$this->assertTrue(in_array($eCourse['id'], $courses), 'la formation en ligne publiée est proposée');

		foreach(['brouillon' => $eCourseDraft, 'datée' => $eCourseDated, 'd\'un autre réseau' => $eCourseOther] as $label => $eCourseHidden) {
			$this->assertFalse(in_array($eCourseHidden['id'], $courses), 'la formation '.$label.' n\'est pas parmi les formations en ligne');
		}

		$cCourse = $data->cCourse->find(fn(Course $eCourseListed) => $eCourseListed['id'] === $eCourse['id']);
		$link = '<a href="'.\Lime::getUrl().CourseUi::url($eCourse).'"';

		$h = $this->getUi()->getList(new \Collection(), $cCourse);

		$this->assertEquals(1, substr_count($h, $link), 'la carte mène à la fiche, sur le domaine du produit : '.$h);
		$this->assertFalse(str_contains($h, 'target="_blank"'), 'dans le même onglet hors intégration');

		$eStructureCommunity = \structure\StructureLib::getById($this->eStructureNetwork['id']);
		$h = new DatePublicUi($eStructureCommunity, \structure\CommunityLib::getByStructure($eStructureCommunity), TRUE)->getList(new \Collection(), $cCourse);

		$this->assertEquals(1, preg_match('/'.preg_quote($link, '/').'[^>]* target="_blank"/', $h), 'intégrée, la carte sort de l\'iframe : '.$h);

		$this->actAs($this->eUserAdmin);

	}

	/**
	 * La racine du réseau : la liste quand une date ou une formation en ligne est proposée, sinon les
	 * relevés s'il en a, sinon la 404. `/formations` sans date rend l'état vide, pas une erreur
	 * (arbitrage 6).
	 */
	public function testRoot(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->seedDate(NULL);

		$this->actAs(new \user\User());

		$this->assertEquals([\ViewAction::class, NULL], $this->call('GET', $this->getPath()), 'un réseau qui propose une date montre sa liste à sa racine');

		[$eStructureQuotation, $eStructureNothing] = [$this->getNetworkWithout(TRUE), $this->getNetworkWithout(FALSE)];

		$pathQuotation = 'reseau/'.\structure\CommunityLib::getByStructure($eStructureQuotation)['fqn'];
		$pathNothing = 'reseau/'.\structure\CommunityLib::getByStructure($eStructureNothing)['fqn'];

		$this->assertEquals(\TemporaryRedirectAction::class, $this->call('GET', $pathQuotation)[0], 'sans date, la racine d\'un réseau qui relève des prix mène aux relevés');
		$this->assertEquals([\ViewAction::class, '/error:404'], $this->call('GET', $pathNothing), 'sans date ni relevés, la racine est une 404');

		$data = $this->executeView($pathNothing.'/formations', []);

		$this->assertTrue($data instanceof \stdClass, 'la liste d\'un réseau sans date se sert');
		$this->assertEquals(0, $data->cDate->count(), 'vide');

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($eStructureNothing, Course::PUBLISHED, Course::PRIVATE);

		$this->actAs(new \user\User());

		$this->assertEquals([\ViewAction::class, NULL], $this->call('GET', $pathNothing), 'sans date, une formation en ligne publiée suffit à la racine');
		$this->assertEquals(0, $this->viewData->cDate->count(), 'aucune date');
		$this->assertEquals([$eCourse['id']], $this->viewData->cCourse->getIds(), 'et sa formation en ligne');

		$view = $this->renderView();

		$this->assertTrue(str_contains($view, '<a href="'.\Lime::getUrl().CourseUi::url($eCourse).'"'), 'la vue se rend avec la carte de la formation : '.$view);

	}

	/**
	 * Le jeton est tiré par le serveur : celui qu'un formulaire forgé poste est ignoré. L'adresse est
	 * rangée en minuscules. L'envoi répond par l'écran « envoyé », qui ne porte ni le jeton ni le
	 * geste d'annulation : seul l'e-mail les porte (remède a de la relecture).
	 */
	public function testToken(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eDate = $this->seedDate(NULL);

		$this->actAs(new \user\User());

		$forged = str_repeat('a', 30);

		$result = $this->call('POST', $this->getPath().'/formations/'.$eDate['id'].'/:doCreate', $this->getInput('Zztest.Jeton@Example.org') + ['token' => $forged]);

		$eDateEnrollment = $this->getLineByEmail($eDate, 'zztest.jeton@example.org');

		$this->assertTrue($eDateEnrollment->notEmpty(), 'la ligne est écrite à l\'adresse en minuscules');
		$this->assertTrue($eDateEnrollment['user']->empty(), 'sans compte');
		$this->assertTrue(preg_match('/^[0-9a-f]{30}$/', $eDateEnrollment['token']) === 1, 'le jeton a trente caractères hexadécimaux');
		$this->assertTrue($eDateEnrollment['token'] !== $forged, 'le jeton posté est ignoré');
		$this->assertEquals([\ViewAction::class, NULL], $result, 'l\'écran « envoyé » répond à l\'envoi, sans redirection');
		$this->assertEquals(DateEnrollment::REQUESTED, $eDateEnrollment['status'], 'le réseau confirme : demande');

		DateEnrollmentLib::fillLessons(new \Collection([$eDateEnrollment]));

		$sent = $this->getUi()->getSent($eDateEnrollment);

		$this->assertTrue(str_contains($sent, 'Zztest'), 'l\'écran remercie par le prénom saisi');
		$this->assertFalse(str_contains($sent, $eDateEnrollment['token']), 'il ne porte pas le jeton');
		$this->assertFalse(str_contains($sent, ':doCancel'), 'ni le geste d\'annulation');
		$this->assertTrue($eDateEnrollment['mailedAt'] !== NULL, 'le jeton part dans l\'accusé de réception');

		$this->assertEquals([\ViewAction::class, NULL], $this->call('GET', $this->getPath().'/inscription/'.$eDateEnrollment['token']), 'la page du jeton se lit sans compte');
		$this->assertEquals([\ViewAction::class, '/error:404'], $this->call('GET', $this->getPath().'/inscription/'.$forged), 'un jeton inconnu rend la 404');
		$this->assertEquals([\ViewAction::class, '/error:404'], $this->call('GET', 'reseau/'.\structure\CommunityLib::getByStructure($this->getNetworkOther())['fqn'].'/inscription/'.$eDateEnrollment['token']), 'comme le jeton lu sous l\'adresse d\'un autre réseau');

	}

	/**
	 * L'invitation à créer un compte promet les leçons de la formation : l'écran « envoyé »,
	 * l'accusé de réception et le rappel ne la portent que si la formation en a. Un présentiel sans
	 * leçon se suit sans compte, l'attestation de présence passant par le jeton (lot L8f).
	 */
	public function testAccountCardFollowsLessons(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$card = 'se suivent avec un compte';

		$eDate = $this->seedDate(NULL);

		$this->actAs(new \user\User());

		$this->assertEquals([\ViewAction::class, NULL], $this->call('POST', $this->getPath().'/formations/'.$eDate['id'].'/:doCreate', $this->getInput('zztest.sans-lecon@example.org')), 'l\'envoi sur une formation sans leçon');
		$this->assertFalse(str_contains(stripslashes($this->renderView()), 'href="'.\Lime::getUrl().'/inscription"'), 'l\'écran « envoyé » ne promet pas de leçon');

		$eDateEnrollment = $this->getLineByEmail($eDate, 'zztest.sans-lecon@example.org');

		$this->assertFalse(str_contains(implode(' ', DateEnrollmentLib::getMails($eDateEnrollment, 'created')[0]['content']), $card), 'l\'accusé de réception non plus');
		$this->assertFalse(str_contains(implode(' ', DateEnrollmentLib::getMails($eDateEnrollment, 'reminded')[0]['content']), $card), 'ni le rappel');

		$this->actAs($this->eUserAdmin);

		$this->createLesson($this->createChapter($eDate['course'], 'zzTest module'), 'zzTest leçon', 30);

		$this->actAs(new \user\User());

		$this->assertEquals([\ViewAction::class, NULL], $this->call('POST', $this->getPath().'/formations/'.$eDate['id'].'/:doCreate', $this->getInput('zztest.avec-lecon@example.org')), 'l\'envoi sur la même formation, qui a maintenant une leçon');
		$this->assertTrue(str_contains(stripslashes($this->renderView()), 'href="'.\Lime::getUrl().'/inscription"'), 'l\'écran « envoyé » invite à créer le compte');

		$eDateEnrollment = $this->getLineByEmail($eDate, 'zztest.avec-lecon@example.org');

		$this->assertTrue(str_contains(implode(' ', DateEnrollmentLib::getMails($eDateEnrollment, 'created')[0]['content']), $card), 'l\'accusé de réception aussi');
		$this->assertTrue(str_contains(implode(' ', DateEnrollmentLib::getMails($eDateEnrollment, 'reminded')[0]['content']), $card), 'et le rappel');

	}

	/**
	 * Une adresse n'a qu'une ligne ouverte par date. Renvoyée, elle reçoit la même réponse qu'une
	 * adresse neuve, rien ne s'écrit, et l'accusé repart avec son lien, une fois par heure au plus.
	 * Annulée, sa ligne se rouvre sous le même jeton. L'annulation se fait par un POST, un GET sur son
	 * adresse n'écrit rien.
	 */
	public function testDuplicateCancelReopen(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eDate = $this->seedDate(NULL);
		$email = 'zztest.doublon@example.org';

		$this->actAs(new \user\User());

		$this->call('POST', $this->getPath().'/formations/'.$eDate['id'].'/:doCreate', $this->getInput($email));

		$eDateEnrollment = $this->getLineByEmail($eDate, $email);
		$token = $eDateEnrollment['token'];

		$mailedAt = $this->getMailedAt($eDate, $email);

		$this->assertTrue($mailedAt !== NULL, 'un accusé à l\'inscription');

		$input = ['firstName' => 'Zzautre'] + $this->getInput($email);

		// L'accusé est daté de trente minutes plus tôt : `NOW()` compte en secondes, et un renvoi
		// dans la même seconde laisserait la date inchangée même sans la borne.
		DateEnrollment::model()->update($eDateEnrollment, ['mailedAt' => new \Sql('NOW() - INTERVAL 30 MINUTE')]);

		$mailedAt = $this->getMailedAt($eDate, $email);

		$this->assertEquals([\ViewAction::class, NULL], $this->call('POST', $this->getPath().'/formations/'.$eDate['id'].'/:doCreate', $input), 'la même adresse reçoit la même réponse');
		$this->assertEquals('Zztest', $this->getLineByEmail($eDate, $email)['firstName'], 'sans rien écrire');
		$this->assertEquals($mailedAt, $this->getMailedAt($eDate, $email), 'ni renvoyer l\'accusé dans l\'heure');

		DateEnrollment::model()->update($eDateEnrollment, ['mailedAt' => new \Sql('NOW() - INTERVAL 2 HOUR')]);

		$mailedAt = $this->getMailedAt($eDate, $email);

		$this->call('POST', $this->getPath().'/formations/'.$eDate['id'].'/:doCreate', $input);

		$this->assertTrue($this->getMailedAt($eDate, $email) > $mailedAt, 'une heure plus tard, l\'accusé repart');
		$this->assertTrue(str_contains(implode(' ', DateEnrollmentLib::getMails($this->getLineByEmail($eDate, $email), 'created')[0]['content']), '/inscription/'.$token), 'avec le lien de la ligne');

		DateEnrollment::model()->update($eDateEnrollment, ['mailedAt' => new \Sql('NOW() - INTERVAL 59 MINUTE')]);

		$mailedAt = $this->getMailedAt($eDate, $email);

		$this->call('POST', $this->getPath().'/formations/'.$eDate['id'].'/:doCreate', $input);

		$this->assertEquals($mailedAt, $this->getMailedAt($eDate, $email), 'et pas davantage dans l\'heure qui suit');
		$pathCancel = $this->getPath().'/inscription/'.$token.'/:doCancel';

		$this->assertEquals(['aucune route', NULL], $this->call('GET', $pathCancel), 'l\'annulation n\'a pas de route en GET');
		$this->assertEquals(DateEnrollment::REQUESTED, $this->getLineByEmail($eDate, $email)['status'], 'et n\'écrit rien');

		$this->assertEquals(\RedirectAction::class, $this->call('POST', $pathCancel)[0], 'le POST annule');

		$eDateEnrollmentCanceled = $this->getLineByEmail($eDate, $email);

		$this->assertEquals(DateEnrollment::CANCELED, $eDateEnrollmentCanceled['status'], 'la ligne est annulée');
		$this->assertEquals(DateEnrollment::USER, $eDateEnrollmentCanceled['canceledBy'], 'à la demande de la personne');
		$this->assertEquals([\NotExpectedAction::class, NULL], $this->call('POST', $pathCancel), 'une ligne annulée ne s\'annule pas deux fois');

		$this->call('POST', $this->getPath().'/formations/'.$eDate['id'].'/:doCreate', $this->getInput($email));

		$eDateEnrollmentReopened = $this->getLineByEmail($eDate, $email);

		$this->assertEquals($eDateEnrollment['id'], $eDateEnrollmentReopened['id'], 'la ligne annulée se rouvre');
		$this->assertEquals($token, $eDateEnrollmentReopened['token'], 'sous le même jeton');
		$this->assertEquals(DateEnrollment::REQUESTED, $eDateEnrollmentReopened['status'], 'en demande');

	}

	/**
	 * Cinq lignes ouvertes par adresse sur les dates à venir du réseau. Une ligne annulée, une date
	 * passée ou une ligne d'un autre réseau ne comptent pas.
	 */
	public function testOpenMax(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eDate = $this->seedDate(NULL);
		$email = 'zztest.plafond@example.org';

		$cDate = new \Collection([$eDate]);

		for($i = 1; $i <= DateEnrollmentLib::PUBLIC_OPEN_MAX; $i++) {
			$cDate[] = $this->createDate($eDate['course'], NULL);
		}

		$this->actAs(new \user\User());

		$dates = $cDate->getIds();

		foreach(array_slice($dates, 0, DateEnrollmentLib::PUBLIC_OPEN_MAX) as $date) {
			$this->assertEquals(\ViewAction::class, $this->call('POST', $this->getPath().'/formations/'.$date.'/:doCreate', $this->getInput($email))[0], 'inscription à la date '.$date);
		}

		$dateLast = $dates[DateEnrollmentLib::PUBLIC_OPEN_MAX];

		$this->assertSilent(DateLib::getById($dateLast), $email, 'la sixième ligne ouverte');

		// Une ligne d'un autre réseau à la même adresse : ni elle ne compte, ni elle n'est refusée.
		$this->actAs($this->eUserAdmin);

		$eStructureOther = $this->getNetworkOther();
		$eDateOther = $this->createDate($this->createCourse($eStructureOther, Course::PUBLISHED, Course::PRIVATE, type: Course::ONSITE), NULL);

		$this->actAs(new \user\User());

		$this->call('POST', 'reseau/'.\structure\CommunityLib::getByStructure($eStructureOther)['fqn'].'/formations/'.$eDateOther['id'].'/:doCreate', $this->getInput($email));

		$this->assertTrue($this->getLineByEmail($eDateOther, $email)->notEmpty(), 'l\'autre réseau a son propre plafond');
		$this->assertSilent(DateLib::getById($dateLast), $email, 'et ne libère rien ici');

		// Une date devenue passée ne compte plus.
		$eDatePast = DateLib::getById($dates[0]);
		$eDatePast = $this->writeSequences($eDatePast, [[$this->day(-10).' 09:00:00', $this->day(-10).' 17:00:00']]);

		$this->call('POST', $this->getPath().'/formations/'.$dateLast.'/:doCreate', $this->getInput($email));

		$this->assertTrue($this->getLineByEmail(DateLib::getById($dateLast), $email)->notEmpty(), 'une date passée libère une ligne');

		// Une ligne annulée ne compte plus.
		$this->actAs($this->eUserAdmin);

		$eDateNext = $this->createDate($eDate['course'], NULL);

		$this->actAs(new \user\User());

		$this->assertSilent($eDateNext, $email, 'de nouveau cinq');

		$this->call('POST', $this->getPath().'/inscription/'.$this->getLineByEmail(DateLib::getById($dates[1]), $email)['token'].'/:doCancel');

		$this->call('POST', $this->getPath().'/formations/'.$eDateNext['id'].'/:doCreate', $this->getInput($email));

		$this->assertTrue($this->getLineByEmail($eDateNext, $email)->notEmpty(), 'une ligne annulée libère une ligne');

	}

	/**
	 * Vingt envois par poste et par heure : le vingt et unième est refusé, quelle que soit l'adresse.
	 */
	public function testThrottle(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		if(\Cache::isDisabled()) {
			$this->assertFail('Redis est coupé : le débit ne compte rien, il ne s\'éprouve pas');
			return;
		}

		$eDate = $this->seedDate(NULL);

		$this->actAs(new \user\User());

		for($i = 1; $i < DateEnrollmentLib::PUBLIC_POST_MAX; $i++) {
			\user\ThrottleAiLib::hit(self::THROTTLE, getIp(), DateEnrollmentLib::PUBLIC_POST_WINDOW);
		}

		$this->assertEquals(\ViewAction::class, $this->call('POST', $this->getPath().'/formations/'.$eDate['id'].'/:doCreate', $this->getInput('zztest.debit-1@example.org'))[0], 'le vingtième envoi passe');
		$this->assertEquals([\FailAction::class, 'DateEnrollment::throttled'], $this->call('POST', $this->getPath().'/formations/'.$eDate['id'].'/:doCreate', $this->getInput('zztest.debit-2@example.org')), 'le vingt et unième est refusé, à une autre adresse');
		$this->assertTrue($this->getLineByEmail($eDate, 'zztest.debit-2@example.org')->empty(), 'sans rien écrire');

	}

	/**
	 * Les e-mails d'une ligne sans compte : un accusé de réception pour chacun des trois états, à
	 * l'adresse saisie, avec le lien à jeton ; la place obtenue part à la même adresse. Aucun ne porte
	 * le pied du gabarit, dont la documentation et la foire aux questions s'adressent aux comptes.
	 */
	public function testMails(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eDate = $this->seedDate(1);
		$urlCommunity = \structure\CommunityUi::url(\structure\CommunityLib::getByStructure($this->eStructureNetwork));

		$this->setConfirm(FALSE);

		$eDateEnrollmentConfirmed = $this->createLinePublic($eDate, 'zztest.courriel-confirme@example.org');
		$eDateEnrollmentWaiting = $this->createLinePublic($eDate, 'zztest.courriel-attente@example.org');

		$this->setConfirm(TRUE);

		$eDateRequested = $this->createDate($eDate['course'], NULL);
		$eDateEnrollmentRequested = $this->createLinePublic($eDateRequested, 'zztest.courriel-demande@example.org');

		$expected = [
			DateEnrollment::CONFIRMED => $eDateEnrollmentConfirmed,
			DateEnrollment::WAITING => $eDateEnrollmentWaiting,
			DateEnrollment::REQUESTED => $eDateEnrollmentRequested,
		];

		$subjects = [];

		foreach($expected as $status => $eDateEnrollment) {

			$this->assertEquals($status, $eDateEnrollment['status'], 'état de la ligne '.$eDateEnrollment['email']);

			$mails = DateEnrollmentLib::getMails($eDateEnrollment, 'created');

			$this->assertEquals([$eDateEnrollment['email']], array_column($mails, 'to'), 'un accusé de réception, à l\'adresse saisie ('.$status.')');
			$this->assertTrue(str_contains(implode(' ', $mails[0]['content']), $urlCommunity.'/inscription/'.$eDateEnrollment['token']), 'qui porte le lien à jeton ('.$status.')');
			$this->assertFalse(str_contains(implode(' ', $mails[0]['content']), \Lime::getUrl().\main\MailSoftwareAiUi::URL_DOC), 'sans le pied du gabarit ('.$status.')');

			$subjects[] = $mails[0]['content'][0];

		}

		$this->assertEquals(3, count(array_unique($subjects)), 'trois accusés différents');

		DateEnrollmentLib::cancel($this->getLineByEmail($eDate, 'zztest.courriel-confirme@example.org'), DateEnrollment::USER);
		DateEnrollmentLib::giveSeat($this->getLineByEmail($eDate, 'zztest.courriel-attente@example.org'));

		$eDateEnrollmentSeat = $this->getLineByEmail($eDate, 'zztest.courriel-attente@example.org');

		$this->assertEquals(DateEnrollment::CONFIRMED, $eDateEnrollmentSeat['status'], 'la place libérée est donnée');

		$mails = DateEnrollmentLib::getMails($eDateEnrollmentSeat, 'seatGiven');

		$this->assertEquals(['zztest.courriel-attente@example.org'], array_column($mails, 'to'), 'la place obtenue part à l\'adresse saisie');
		$this->assertTrue(str_contains(implode(' ', $mails[0]['content']), $urlCommunity.'/inscription/'.$eDateEnrollmentSeat['token']), 'avec le lien à jeton');
		$this->assertFalse(str_contains(implode(' ', $mails[0]['content']), \Lime::getUrl().\main\MailSoftwareAiUi::URL_DOC), 'sans le pied du gabarit');

		DateLib::cancel($eDate);

		$mails = DateEnrollmentLib::getMails($this->getLineByEmail($eDate, 'zztest.courriel-attente@example.org'), 'canceled');

		$this->assertFalse(str_contains(implode(' ', $mails[0]['content']), \Lime::getUrl().\main\MailSoftwareAiUi::URL_DOC), 'l\'annulation de la date non plus');

		$eDateEnrollmentAttached = $this->getLineByEmail($eDate, 'zztest.courriel-attente@example.org');
		$eDateEnrollmentAttached['user'] = $this->createUser('zztest.courriel-compte@example.org');

		$this->assertTrue(str_contains(implode(' ', DateEnrollmentLib::getMails($eDateEnrollmentAttached, 'canceled')[0]['content']), \Lime::getUrl().\main\MailSoftwareAiUi::URL_DOC), 'rattachée à un compte, la ligne retrouve le pied');

	}

	/**
	 * Une demande s'appelle une demande jusqu'à ce qu'une place soit donnée, pour la ligne comme dans
	 * les e-mails, ceux de la personne comme celui du réseau, y compris une fois annulée : l'annulation
	 * garde `confirmedAt`, qui dit ce qu'elle était. Une ligne rouverte après une place perdue
	 * redevient une demande, parce que la réouverture repose `confirmedAt`.
	 */
	public function testRequestWording(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->setConfirm(FALSE);

		$eDateConfirmed = $this->seedDate(NULL);
		$this->createLinePublic($eDateConfirmed, 'zztest.mot-inscription@example.org');

		$this->setConfirm(TRUE);

		$eDateRequested = $this->createDate($eDateConfirmed['course'], NULL);
		$this->createLinePublic($eDateRequested, 'zztest.mot-demande@example.org');
		$this->createLinePublic($eDateRequested, 'zztest.mot-demande-reseau@example.org');

		$eDateEnrollmentRequested = $this->getLineByEmail($eDateRequested, 'zztest.mot-demande@example.org');
		$eDateEnrollmentConfirmed = $this->getLineByEmail($eDateConfirmed, 'zztest.mot-inscription@example.org');

		$this->assertEquals([DateEnrollment::REQUESTED, DateEnrollment::CONFIRMED], [$eDateEnrollmentRequested['status'], $eDateEnrollmentConfirmed['status']], 'une demande et une inscription');

		$received = implode(' ', DateEnrollmentLib::getMails($eDateEnrollmentRequested, 'created')[0]['content']);

		$this->assertTrue(str_contains($received, 'Annulez votre demande') and str_contains($received, 'Voir ma demande'), 'l\'accusé d\'une demande parle de demande');
		$this->assertFalse(str_contains($received, 'votre inscription'), 'et jamais d\'inscription');

		$received = implode(' ', DateEnrollmentLib::getMails($eDateEnrollmentConfirmed, 'created')[0]['content']);

		$this->assertTrue(str_contains($received, 'Annulez votre inscription') and str_contains($received, 'Voir mon inscription'), 'celui d\'une inscription parle d\'inscription');

		DateEnrollmentLib::cancel($eDateEnrollmentRequested, DateEnrollment::USER);
		DateEnrollmentLib::cancel($eDateEnrollmentConfirmed, DateEnrollment::USER);
		DateEnrollmentLib::cancel($this->getLineByEmail($eDateRequested, 'zztest.mot-demande-reseau@example.org'), DateEnrollment::COMMUNITY);

		$this->assertTrue($this->getLineByEmail($eDateRequested, 'zztest.mot-demande@example.org')->isRequest(), 'une demande annulée reste une demande');
		$this->assertFalse($this->getLineByEmail($eDateConfirmed, 'zztest.mot-inscription@example.org')->isRequest(), 'une inscription annulée reste une inscription');

		$eDateEnrollmentCommunity = $this->getLineByEmail($eDateRequested, 'zztest.mot-demande-reseau@example.org');

		[$subject] = DateEnrollmentLib::getMails($eDateEnrollmentCommunity, 'canceled')[0]['content'];

		$this->assertTrue(str_contains($subject, 'Votre demande du'), 'le réseau annule une demande : '.$subject);
		$this->assertTrue($eDateEnrollmentCommunity->isRequest(), 'et la ligne le reste');

		// L'e-mail que reçoit le réseau quand la personne annule suit le même mot que le sien.
		$eStructureNetwork = \structure\StructureLib::getById($this->eStructureNetwork['id']);

		[$mailRequested] = DateEnrollmentLib::getMails($this->getLineByEmail($eDateRequested, 'zztest.mot-demande@example.org'), 'canceled');
		[$mailConfirmed] = DateEnrollmentLib::getMails($this->getLineByEmail($eDateConfirmed, 'zztest.mot-inscription@example.org'), 'canceled');

		$this->assertEquals([$eStructureNetwork['legalEmail'], $eStructureNetwork['legalEmail']], [$mailRequested['to'], $mailConfirmed['to']], 'les deux partent au réseau');
		$this->assertTrue(str_contains($mailRequested['content'][0], 'annule sa demande du') and str_contains(implode(' ', $mailRequested['content']), 'Demande annulée'), 'une demande annulée par la personne, côté réseau : '.$mailRequested['content'][0]);
		$this->assertFalse(str_contains(implode(' ', $mailRequested['content']), 'inscription'), 'et jamais d\'inscription');
		$this->assertTrue(str_contains($mailConfirmed['content'][0], 'annule son inscription du') and str_contains(implode(' ', $mailConfirmed['content']), 'Inscription annulée'), 'une inscription annulée par la personne, côté réseau : '.$mailConfirmed['content'][0]);

		// L'adresse d'envoi ne reçoit pas de réponse : l'annulation par le réseau donne la sienne.
		$contact = 'écrivez à '.encode($eStructureNetwork['name']).' à l\'adresse '.\main\MailSoftwareAiUi::linkTag('mailto:'.$eStructureNetwork['legalEmail']).encode($eStructureNetwork['legalEmail']).'</a>';

		$this->assertTrue(str_contains(implode(' ', DateEnrollmentLib::getMails($eDateEnrollmentCommunity, 'canceled')[0]['content']), $contact), 'l\'annulation par le réseau donne son adresse de contact');

		// Place obtenue, perdue, puis redemandée : le mot suit l'état de la ligne à chaque pas.
		$eDateRoundTrip = $this->createDate($eDateConfirmed['course'], NULL);
		$this->createLinePublic($eDateRoundTrip, 'zztest.mot-aller-retour@example.org');

		$eDateEnrollment = $this->getLineByEmail($eDateRoundTrip, 'zztest.mot-aller-retour@example.org');
		$this->assertTrue($eDateEnrollment->isRequest(), 'envoyée, c\'est une demande');

		DateEnrollmentLib::confirm($eDateEnrollment);

		$eDateEnrollment = $this->getLineByEmail($eDateRoundTrip, 'zztest.mot-aller-retour@example.org');
		$this->assertEquals(DateEnrollment::CONFIRMED, $eDateEnrollment['status'], 'place obtenue');
		$this->assertFalse($eDateEnrollment->isRequest(), 'confirmée, c\'est une inscription');

		DateEnrollmentLib::cancel($eDateEnrollment, DateEnrollment::COMMUNITY);

		$eDateEnrollment = $this->getLineByEmail($eDateRoundTrip, 'zztest.mot-aller-retour@example.org');
		[$subject] = DateEnrollmentLib::getMails($eDateEnrollment, 'canceled')[0]['content'];

		$this->assertTrue(str_contains($subject, 'Votre inscription du'), 'place perdue, l\'e-mail parle d\'inscription : '.$subject);
		$this->assertFalse($eDateEnrollment->isRequest(), 'et la ligne aussi');

		$this->createLinePublic($eDateRoundTrip, 'zztest.mot-aller-retour@example.org');

		$eDateEnrollment = $this->getLineByEmail($eDateRoundTrip, 'zztest.mot-aller-retour@example.org');
		$this->assertEquals([DateEnrollment::REQUESTED, NULL], [$eDateEnrollment['status'], $eDateEnrollment['confirmedAt']], 'redemandée, la ligne rouverte attend de nouveau une place');

		$received = implode(' ', DateEnrollmentLib::getMails($eDateEnrollment, 'created')[0]['content']);
		$this->assertTrue(str_contains($received, 'Voir ma demande') and str_contains($received, 'Annulez votre demande'), 'l\'accusé repart en demande');

		DateEnrollmentLib::cancel($eDateEnrollment, DateEnrollment::USER);

		$eDateEnrollment = $this->getLineByEmail($eDateRoundTrip, 'zztest.mot-aller-retour@example.org');

		$this->assertTrue(str_contains(DateEnrollmentLib::getMails($eDateEnrollment, 'canceled')[0]['content'][0], 'annule sa demande du'), 'annulée par la personne, le réseau lit une demande');
		$this->assertTrue($eDateEnrollment->isRequest(), 'et la ligne aussi');

	}

	/**
	 * Le rattachement au compte qui se connecte (arbitrage 4) : une ligne sans compte prend le
	 * compte ; si le compte a déjà sa ligne sur la date, reste l'ouverte, puis celle qui vaut le
	 * plus, à égalité celle du compte, et celle du compte si les deux sont annulées.
	 */
	public function testAttach(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eDate = $this->seedDate(1);
		$eCourse = $eDate['course'];

		$eUser = $this->createPerson();

		// Chaque cas sur sa date, la ligne sans compte et celle du compte dans les états voulus.
		$cases = [
			'seule' => [NULL, DateEnrollment::REQUESTED, 'public'],
			'confirmée sans compte contre compte en attente' => [DateEnrollment::WAITING, DateEnrollment::CONFIRMED, 'public'],
			'deux demandes' => [DateEnrollment::REQUESTED, DateEnrollment::REQUESTED, 'account'],
			'deux annulées' => [DateEnrollment::CANCELED, DateEnrollment::CANCELED, 'account'],
			'annulée sans compte' => [DateEnrollment::REQUESTED, DateEnrollment::CANCELED, 'account'],
			'annulée du compte' => [DateEnrollment::CANCELED, DateEnrollment::REQUESTED, 'public'],
		];

		$lines = [];

		foreach($cases as $label => [$statusAccount, $statusPublic, $kept]) {

			$eDateCase = ($label === 'confirmée sans compte contre compte en attente') ? $eDate : $this->createDate($eCourse, NULL);

			$this->setConfirm($statusPublic !== DateEnrollment::CONFIRMED);

			$eDateEnrollmentPublic = $this->createLinePublic($eDateCase, $eUser['email']);

			$eDateEnrollmentAccount = ($statusAccount === NULL) ? new DateEnrollment() : $this->createLineAccount($eDateCase, $eUser);

			if($statusPublic === DateEnrollment::CANCELED) {
				DateEnrollmentLib::cancel($eDateEnrollmentPublic, DateEnrollment::USER);
			}

			if($statusAccount === DateEnrollment::CANCELED) {
				DateEnrollmentLib::cancel($eDateEnrollmentAccount, DateEnrollment::USER);
			}

			$this->assertEquals($statusPublic, DateEnrollmentLib::getById($eDateEnrollmentPublic['id'])['status'], $label.' : ligne sans compte dans l\'état voulu');

			if($statusAccount !== NULL) {
				$this->assertEquals($statusAccount, DateEnrollmentLib::getById($eDateEnrollmentAccount['id'])['status'], $label.' : ligne du compte dans l\'état voulu');
			}

			$lines[$label] = [$eDateCase, $eDateEnrollmentPublic, $eDateEnrollmentAccount, $kept];

		}

		$this->setConfirm(TRUE);

		UserObserverLib::logIn($eUser);

		foreach($lines as $label => [$eDateCase, $eDateEnrollmentPublic, $eDateEnrollmentAccount, $kept]) {

			$cDateEnrollment = DateEnrollment::model()
				->select(['id', 'user', 'email'])
				->whereDate($eDateCase)
				->where('user = '.$eUser['id'].' OR email = '.DateEnrollment::model()->format($eUser['email']))
				->getCollection();

			$this->assertEquals(1, $cDateEnrollment->count(), $label.' : une seule ligne reste');

			$eDateEnrollmentKept = $cDateEnrollment->first();

			$this->assertEquals($eUser['id'], $eDateEnrollmentKept['user']['id'], $label.' : elle porte le compte');
			$this->assertEquals(($kept === 'public') ? $eDateEnrollmentPublic['id'] : $eDateEnrollmentAccount['id'], $eDateEnrollmentKept['id'], $label.' : c\'est celle attendue ('.$kept.')');

		}

		$this->assertEquals(0, DateEnrollment::model()->whereEmail($eUser['email'])->whereUser(NULL)->count(), 'plus aucune ligne sans compte à cette adresse');

	}

	/**
	 * La même inscription postée avec et sans session : sans compte, la ligne prend la saisie ; avec
	 * un compte qui ne lit pas la formation, elle prend le compte et un jeton, et la saisie de
	 * l'identité est ignorée (arbitrage 2). Un compte qui lit la formation est renvoyé à sa fiche.
	 */
	public function testWithAndWithoutSession(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eDate = $this->seedDate(NULL);
		$input = $this->getInput('zztest.session@example.org');
		$path = $this->getPath().'/formations/'.$eDate['id'].'/:doCreate';

		$this->actAs(new \user\User());

		$this->assertEquals(\ViewAction::class, $this->call('POST', $path, $input)[0], 'sans session');

		$eDateEnrollmentGuest = $this->getLineByEmail($eDate, 'zztest.session@example.org');

		$this->assertTrue($eDateEnrollmentGuest['user']->empty(), 'la ligne n\'a pas de compte');
		$this->assertEquals('Zztest', $eDateEnrollmentGuest['firstName'], 'elle porte la saisie');

		$eUserOutside = $this->createUser('zztest.hors-reseau@example.org');

		$this->actAs($eUserOutside);

		$this->assertFalse(CourseLib::getById($eDate['course']['id'])->canRead(), 'le compte ne lit pas la formation');
		$this->assertEquals([\ViewAction::class, NULL], $this->call('GET', $this->getPath().'/formations/'.$eDate['id'], ['step' => 3, 'commitment' => ['presence', 'cancel', 'conditions']]), 'il suit les étapes de la page publique');
		$this->assertEquals(\ViewAction::class, $this->call('POST', $path, $input)[0], 'avec session');

		$eDateEnrollmentOnline = DateEnrollmentLib::getByDateAndUser($eDate, $eUserOutside);

		$this->assertTrue($eDateEnrollmentOnline->notEmpty(), 'la ligne prend le compte');
		$this->assertTrue($eDateEnrollmentOnline['token'] !== NULL, 'et un jeton');
		$this->assertEquals(NULL, $eDateEnrollmentOnline['email'], 'l\'adresse postée est ignorée');
		$this->assertEquals($eUserOutside['email'], $eDateEnrollmentOnline->getEmail(), 'c\'est celle du compte qui sert');

		$this->assertEquals([\ViewAction::class, ':/reseau/{fqn}/inscription/{token}'], $this->call('GET', $this->getPath().'/formations/'.$eDate['id']), 'inscrit, il lit sa ligne à la place des étapes');

		$eUserMember = $this->createPerson();
		$this->join($eUserMember, $this->eStructureNetwork, 'member');

		$this->actAs($eUserMember);

		$this->assertEquals([\ViewAction::class, NULL], $this->call('GET', $this->getPath().'/formations/'.$eDate['id']), 'un membre du réseau ne lit pas la formation ouverte aux membres : il suit les étapes de la page publique');

		$eUserTeam = $this->createPerson();
		$this->join($eUserTeam, $this->eStructureNetwork);

		$this->actAs($eUserTeam);

		$result = $this->call('GET', $this->getPath().'/formations/'.$eDate['id']);

		$this->assertEquals(\RedirectAction::class, $result[0], 'un compte qui lit la formation est renvoyé');
		$this->assertTrue(str_ends_with($result[1], CourseUi::url($eDate['course'])), 'à sa fiche');

	}

	/**
	 * L'étape 3 en connecté hors du réseau (mineur 8 de l'ui, tranché par V-Learning) : le compte ne
	 * ressaisit pas son identité, et le statut ne vient que d'une inscription précédente de la
	 * même personne. Sans elle, aucun défaut, comme pour l'anonyme, qui saisit son identité.
	 */
	public function testStepThreeAccount(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eDate = $this->seedDate(NULL);
		$eDateNext = $this->createDate($eDate['course'], NULL);
		$input = ['step' => 3, 'commitment' => ['presence', 'cancel', 'conditions']];

		$eUserOutside = $this->createUser('zztest.etape-trois@example.org');

		$this->actAs($eUserOutside);

		$this->assertEquals([\ViewAction::class, NULL], $this->call('GET', $this->getPath().'/formations/'.$eDate['id'], $input), 'le compte suit les étapes');
		$this->assertEquals(NULL, $this->viewData->e['trainee'], 'sans inscription précédente, aucun statut d\'office');
		$this->assertFalse(str_contains($this->getStepsHtml(), 'name="email"'), 'le compte ne ressaisit pas son adresse');
		$this->assertEquals(0, preg_match('/data-waiter="1"/', $this->getStepsHtml()), 'le bouton d\'envoi garde son libellé pendant l\'attente, un « 1 » le remplacerait');

		$this->createLineAccount($eDate, $eUserOutside);

		$this->actAs($eUserOutside);

		$this->assertEquals([\ViewAction::class, NULL], $this->call('GET', $this->getPath().'/formations/'.$eDateNext['id'], $input), 'une autre date');
		$this->assertEquals(DateEnrollment::FARMER, $this->viewData->e['trainee'], 'le statut de l\'inscription précédente est repris');

		$this->actAs(new \user\User());

		$this->assertEquals([\ViewAction::class, NULL], $this->call('GET', $this->getPath().'/formations/'.$eDate['id'], $input), 'l\'anonyme suit les étapes');
		$this->assertEquals(NULL, $this->viewData->e['trainee'], 'sans statut d\'office');
		$this->assertTrue(str_contains($this->getStepsHtml(), 'name="email"'), 'mais saisit son adresse');

	}

	/**
	 * Sans session, la page publique ne relit ni ne réécrit une ligne qui porte un compte : une
	 * ligne rattachée garde l'adresse saisie, et un inconnu qui la tape ne doit ni la rouvrir, ni y
	 * poser son nom, ni recevoir quoi que ce soit (bloquant 1 de la relecture). L'adresse d'un compte
	 * sans ligne sur la date s'inscrit, mais aucun accusé n'y part : la ligne attend la connexion.
	 */
	public function testAccountLineUntouched(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eDate = $this->seedDate(NULL);
		$email = 'zztest.titulaire@example.org';
		$path = $this->getPath().'/formations/'.$eDate['id'].'/:doCreate';

		$this->actAs(new \user\User());

		$this->call('POST', $path, $this->getInput($email));

		$eUser = $this->createUser($email);

		DateEnrollmentLib::attachByUser($eUser);
		DateEnrollmentLib::cancel($this->getLineByEmail($eDate, $email), DateEnrollment::USER);

		$mailedAt = $this->getMailedAt($eDate, $email);

		$this->actAs(new \user\User());

		$this->assertEquals([\ViewAction::class, NULL], $this->call('POST', $path, ['firstName' => 'Zzintrus'] + $this->getInput($email)), 'l\'inconnu reçoit la réponse de tous');

		$eDateEnrollment = $this->getLineByEmail($eDate, $email);

		$this->assertEquals($eUser['id'], $eDateEnrollment['user']['id'], 'la ligne garde son compte');
		$this->assertEquals(DateEnrollment::CANCELED, $eDateEnrollment['status'], 'elle n\'est pas rouverte');
		$this->assertEquals('Zztest', $eDateEnrollment['firstName'], 'le nom saisi par l\'inconnu n\'y est pas');
		$this->assertEquals($mailedAt, $eDateEnrollment['mailedAt'], 'rien n\'est parti à l\'adresse');
		$this->assertEquals(1, DateEnrollment::model()->whereDate($eDate)->whereEmail($email)->count(), 'aucune seconde ligne');

		$emailAccount = 'zztest.compte-existant@example.org';
		$eUserAccount = $this->createUser($emailAccount);

		$this->actAs(new \user\User());

		$this->assertEquals([\ViewAction::class, NULL], $this->call('POST', $path, $this->getInput($emailAccount)), 'l\'adresse d\'un compte reçoit la même réponse');

		$eDateEnrollmentAccount = $this->getLineByEmail($eDate, $emailAccount);

		$this->assertTrue($eDateEnrollmentAccount->notEmpty() and $eDateEnrollmentAccount['user']->empty(), 'la ligne s\'écrit sans compte');
		$this->assertEquals(NULL, $eDateEnrollmentAccount['mailedAt'], 'sans accusé à l\'adresse du compte');

		DateEnrollmentLib::attachByUser($eUserAccount);

		$this->assertEquals($eUserAccount['id'], $this->getLineByEmail($eDate, $emailAccount)['user']['id'], 'elle prend le compte à la connexion');

	}

	/**
	 * Un compte hors du réseau qui tient une ligne ouverte sans jeton, venue de la fiche du temps où
	 * il était du réseau, lit l'état de sa ligne sur la page publique, jamais une page d'erreur
	 * (remarque 10 de la relecture). Sans jeton, pas de geste d'annulation ici.
	 */
	public function testAccountLineWithoutToken(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eDate = $this->seedDate(NULL);
		$eUser = $this->createUser('zztest.ancien-membre@example.org');

		$eDateEnrollment = $this->createLineAccount($eDate, $eUser);

		$this->assertEquals(NULL, $eDateEnrollment['token'], 'la ligne du membre n\'a pas de jeton');

		$this->actAs($eUser);

		$this->assertFalse(CourseLib::getById($eDate['course']['id'])->canRead(), 'le compte ne lit plus la formation');
		$this->assertEquals([\ViewAction::class, ':/reseau/{fqn}/inscription/{token}'], $this->call('GET', $this->getPath().'/formations/'.$eDate['id']), 'il lit sa ligne à la place des étapes');

		$view = $this->renderView();

		$this->assertTrue(str_contains($view, 'id="learning-public-enrollment"'), 'la vue de sa ligne se rend : '.substr(strip_tags($view), 0, 200));

		// Depuis une ancienne adresse du réseau, la même redirection que la page d'une inscription.
		\structure\CommunityRedirectLib::create(new \structure\CommunityRedirect([
			'fqn' => 'zztest-ancienne-adresse-compte',
			'structure' => $this->eStructureNetwork,
		]));

		$eCommunity = \structure\CommunityLib::getByStructure($this->eStructureNetwork);

		$this->assertEquals([\RedirectAction::class, DatePublicUi::urlDate($eCommunity, $eDate)], $this->call('GET', 'reseau/zztest-ancienne-adresse-compte/formations/'.$eDate['id']), 'depuis une ancienne adresse, vers l\'adresse actuelle');

		// Posé comme la page le pose sur la ligne qu'elle rend.
		$eDateEnrollment['losesLessons'] = DateEnrollmentLib::losesLessons($eDateEnrollment);

		$h = $this->getEnrollmentHtml($eDateEnrollment);

		$this->assertTrue(str_contains($h, DatePublicUi::urlDate($eCommunity, $eDate).'/:doCancel'), 'le geste d\'annulation passe par la date, sans jeton');
		$this->assertTrue(str_contains($h, 'data-confirm="'), 'et se confirme');

	}

	/**
	 * La personne connectée annule sa propre ligne sans jeton, par les règles de l'annulation par le
	 * jeton (point 7 de la contre-recette) : `DateEnrollmentLib::cancel()` et `acceptCancel()`, le
	 * droit étant celui du compte (`canCancel()`) et non d'un jeton. Personne d'autre ne l'atteint :
	 * ni sans session, ni sous un autre compte, qui n'a pas de ligne sur la date, ni le compte lui-même
	 * sous l'adresse d'un autre réseau, que la date ne concerne pas.
	 */
	public function testAccountLineCancel(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eDate = $this->seedDate(NULL);
		$eUser = $this->createUser('zztest.annule-compte@example.org');

		$eDateEnrollment = $this->createLineAccount($eDate, $eUser);
		$path = $this->getPath().'/formations/'.$eDate['id'].'/:doCancel';

		$this->actAs(new \user\User());

		$this->assertEquals([\ViewAction::class, '/error:404'], $this->call('POST', $path), 'sans session, la date n\'a pas de ligne à annuler');

		$this->actAs($this->createUser('zztest.annule-autre@example.org'));

		$this->assertEquals([\ViewAction::class, '/error:404'], $this->call('POST', $path), 'un autre compte non plus');
		$this->actAs($eUser);

		$pathOther = 'reseau/'.\structure\CommunityLib::getByStructure($this->getNetworkOther())['fqn'].'/formations/'.$eDate['id'].'/:doCancel';

		$this->assertEquals([\ViewAction::class, '/error:404'], $this->call('POST', $pathOther), 'sous l\'adresse d\'un autre réseau, la date n\'existe pas');
		$this->assertEquals(DateEnrollment::REQUESTED, DateEnrollmentLib::getById($eDateEnrollment['id'])['status'], 'la ligne est intacte');

		$this->assertEquals([\ViewAction::class, NULL], $this->call('POST', $path), 'le compte annule sa ligne');

		// La réponse est du JSON d'instructions ajax, dont les accents sont échappés.
		$view = $this->renderView();
		$instructions = json_encode(json_decode($view, TRUE), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		$this->assertTrue(str_contains($instructions, '#learning-public-enrollment'), 'la vue repeint la ligne dans son nouvel état : '.substr($view, 0, 300));

		$eDateEnrollmentCanceled = DateEnrollmentLib::getById($eDateEnrollment['id']);

		$this->assertEquals(DateEnrollment::CANCELED, $eDateEnrollmentCanceled['status'], 'elle est annulée');
		$this->assertEquals(DateEnrollment::USER, $eDateEnrollmentCanceled['canceledBy'], 'à sa demande');

		$this->actAs($eUser);

		$this->assertEquals([\NotExpectedAction::class, NULL], $this->call('POST', $path), 'une ligne annulée ne s\'annule pas deux fois');

	}

	/**
	 * Connecté, la liste dit l'état de la ligne ouverte du compte sur une date et mène à la page de
	 * cette ligne, au lieu de proposer de s'y inscrire de nouveau (point 3 de la contre-recette), sur
	 * `/formations` comme à la racine du réseau. Sans session, la même date propose l'inscription.
	 */
	public function testListOwnLine(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eDate = $this->seedDate(NULL);
		$eUser = $this->createUser('zztest.liste-compte@example.org');

		$this->createLineAccount($eDate, $eUser);

		$eCommunity = \structure\CommunityLib::getByStructure($this->eStructureNetwork);
		$link = 'href="'.DatePublicUi::urlDate($eCommunity, $eDate).'"';

		foreach(['/formations' => 'la liste', '' => 'la racine'] as $suffix => $label) {

			$this->actAs($eUser);

			$this->assertEquals([\ViewAction::class, NULL], $this->call('GET', $this->getPath().$suffix), $label.' se sert au compte');

			$cDate = $this->viewData->cDate->find(fn(Date $eDateListed) => $eDateListed['id'] === $eDate['id']);

			$this->assertEquals(DateEnrollment::REQUESTED, $cDate->first()['eDateEnrollment']['status'], $label.' dit l\'état de sa demande');
			$this->assertTrue(str_contains($this->getUi()->getList($cDate), $link), $label.' mène à la page de sa demande');

		}

		$this->actAs(new \user\User());

		$this->call('GET', $this->getPath().'/formations');

		$cDate = $this->viewData->cDate->find(fn(Date $eDateListed) => $eDateListed['id'] === $eDate['id']);

		$this->assertTrue(str_contains($this->getUi()->getList($cDate), $link), 'sans session, la date propose l\'inscription');
		$this->assertFalse($cDate->first()['eDateEnrollment']->isOpen(), 'sans état de demande');

	}

	/**
	 * Une ancienne adresse du réseau mène à la même page sous l'adresse actuelle, à la racine comme
	 * sur la liste et la page d'une inscription (remarque 8 de la relecture).
	 */
	public function testOldAddress(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eDate = $this->seedDate(NULL);

		$eCommunityRedirect = new \structure\CommunityRedirect([
			'fqn' => 'zztest-ancienne-adresse',
			'structure' => $this->eStructureNetwork,
		]);

		\structure\CommunityRedirect::model()->insert($eCommunityRedirect);

		$this->actAs(new \user\User());

		$eCommunity = \structure\CommunityLib::getByStructure($this->eStructureNetwork);

		$this->assertEquals([\TemporaryRedirectAction::class, \structure\CommunityUi::url($eCommunity)], $this->call('GET', 'reseau/zztest-ancienne-adresse'), 'la racine');
		$this->assertEquals([\RedirectAction::class, DatePublicUi::urlList($eCommunity)], $this->call('GET', 'reseau/zztest-ancienne-adresse/formations'), 'la liste');
		$this->assertEquals([\RedirectAction::class, DatePublicUi::urlDate($eCommunity, $eDate).'?embed=1'], $this->call('GET', 'reseau/zztest-ancienne-adresse/formations/'.$eDate['id'], ['embed' => 1]), 'une date, intégrée ou non');

		$this->call('POST', $this->getPath().'/formations/'.$eDate['id'].'/:doCreate', $this->getInput('zztest.ancienne@example.org'));

		$token = $this->getLineByEmail($eDate, 'zztest.ancienne@example.org')['token'];

		$this->assertEquals([\RedirectAction::class, DatePublicUi::urlEnrollment($eCommunity, $token)], $this->call('GET', 'reseau/zztest-ancienne-adresse/inscription/'.$token), 'la page d\'une inscription');
		$this->assertEquals([\ViewAction::class, NULL], $this->call('GET', $this->getPath().'/formations'), 'l\'adresse actuelle se sert sans détour');

	}

	/**
	 * Une inscription confirmée ouvre la fiche d'une formation datée à qui ne la lirait pas
	 * autrement (arbitrage 3 a) ; une demande ne l'ouvre pas.
	 */
	public function testCanReadConfirmed(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eDate = $this->seedDate(NULL);
		$eUser = $this->createUser('zztest.confirme-lecture@example.org');

		$this->actAs($eUser);

		$this->assertFalse(CourseLib::getById($eDate['course']['id'])->canRead(), 'le compte hors du réseau ne lit pas la formation');

		$this->call('POST', $this->getPath().'/formations/'.$eDate['id'].'/:doCreate', $this->getInput('zztest.ignoree@example.org'));

		$eDateEnrollment = DateEnrollmentLib::getByDateAndUser($eDate, $eUser);

		$this->assertEquals(DateEnrollment::REQUESTED, $eDateEnrollment['status'], 'sa demande est écrite');
		$this->assertFalse(CourseLib::getById($eDate['course']['id'])->canRead(), 'une demande n\'ouvre pas la fiche');

		DateEnrollmentLib::confirm($eDateEnrollment);

		$this->actAs($eUser);

		$this->assertTrue(CourseLib::getById($eDate['course']['id'])->canRead(), 'une inscription confirmée l\'ouvre');

	}

	/**
	 * Le verrou de la date, sur deux connexions réelles, pour deux adresses sans compte sur une date
	 * à une place : A écrit et garde sa transaction trois secondes, B attend le verrou, puis voit A et
	 * part en attente. Données écrites hors de la transaction du test et effacées à la fin.
	 */
	public function testLockTwoConnections(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		Course::model()->rollBack();

		$eCourse = new Course();

		try {

			$this->actAs($this->eUserAdmin);

			$eCourse = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE, type: Course::ONSITE);
			$eDate = $this->createDate($eCourse, 1);

			$load = '$eStructure = \\structure\\StructureLib::getById('.$this->eStructureNetwork['id'].');'
				.'$eStructure["community"] = \\structure\\CommunityLib::getByStructure($eStructure);'
				.'$e = \\learning\\DateEnrollmentLib::getPublicCreateElement(\\learning\\DateLib::getById('.$eDate['id'].'), $eStructure, new \\user\\User());'
				.'$e["firstName"] = "Zztest"; $e["lastName"] = "Verrou"; $e["email"] = "%s"; $e["trainee"] = \\learning\\DateEnrollment::FARMER; $e["committedAt"] = \\Clock::datetime();';

			$processA = $this->spawn(sprintf($load, 'zztest.verrou-a@example.org')
				.'\\learning\\DateEnrollment::model()->beginTransaction();'
				.'\\learning\\DateEnrollmentLib::createPublic($e);'
				.'sleep(3);'
				.'\\learning\\DateEnrollment::model()->commit();');

			sleep(1);

			$processB = $this->spawn(sprintf($load, 'zztest.verrou-b@example.org')
				.'$t = microtime(TRUE);'
				.'\\learning\\DateEnrollmentLib::createPublic($e);'
				.'echo "WAIT:", microtime(TRUE) - $t;');

			$outputB = $this->finish($processB);
			$this->finish($processA);

			$this->assertTrue(preg_match('/WAIT:([0-9.]+)/', $outputB, $match) === 1, 'le second processus a rendu son attente : '.$outputB);
			$this->assertTrue((float)($match[1] ?? 0) > 1.0, 'B a attendu le COMMIT de A ('.round((float)($match[1] ?? 0), 2).' s)');

			$this->assertEquals(DateEnrollment::REQUESTED, $this->getLineByEmail($eDate, 'zztest.verrou-a@example.org')['status'], 'A a pris la place');
			$this->assertEquals(DateEnrollment::WAITING, $this->getLineByEmail($eDate, 'zztest.verrou-b@example.org')['status'], 'B, qui a attendu le verrou, voit A et part en attente');

		} finally {

			if($eCourse->notEmpty()) {
				CourseLib::delete(CourseLib::getById($eCourse['id']));
			}

			Course::model()->beginTransaction();

		}

	}

	/**
	 * Le chemin du formulaire de l'étape 3, identité comprise, en chaînes comme le navigateur les
	 * poste.
	 */
	private function getInput(string $email): array {

		return [
			'firstName' => 'Zztest',
			'lastName' => 'Public',
			'email' => $email,
			'structureName' => 'zzTest structure',
			'trainee' => DateEnrollment::FARMER,
			'commitment' => ['presence', 'cancel', 'conditions'],
		];

	}

	/**
	 * Un envoi que la page tait : la même réponse que pour une adresse neuve, sans ligne, donc sans
	 * accusé, qui ne part que sur une ligne (`sendReceived()`).
	 */
	private function assertSilent(Date $eDate, string $email, string $case): void {

		$this->assertEquals([\ViewAction::class, NULL], $this->call('POST', $this->getPath().'/formations/'.$eDate['id'].'/:doCreate', $this->getInput($email)), $case.' reçoit la réponse de tous');
		$this->assertTrue($this->getLineByEmail($eDate, $email)->empty(), $case.' n\'est pas écrite');

	}

	/**
	 * Le dernier accusé de réception parti pour la ligne de l'adresse sur la date. Il se lit sur la
	 * ligne et non dans la file des e-mails : en dev, `SendLib` n'écrit rien pour une adresse hors de
	 * `MailSetting::$devSendOnly`, et l'y ajouter ferait partir un vrai envoi chez le prestataire.
	 */
	private function getMailedAt(Date $eDate, string $email): ?string {
		return $this->getLineByEmail($eDate, $email)['mailedAt'];
	}

	private function getUi(): DatePublicUi {

		$eStructureCommunity = \structure\StructureLib::getById($this->eStructureNetwork['id']);

		return new DatePublicUi($eStructureCommunity, \structure\CommunityLib::getByStructure($eStructureCommunity), FALSE);

	}

	/**
	 * La page du jeton telle que la route la rend : `course.lessons` posé, qui dit si la place délivre
	 * une attestation de présence (lot L8f).
	 */
	private function getEnrollmentHtml(DateEnrollment $eDateEnrollment): string {

		DateEnrollmentLib::fillLessons(new \Collection([$eDateEnrollment]));

		return $this->getUi()->getEnrollment($eDateEnrollment);

	}

	/**
	 * La dernière vue levée, rendue par le cadriciel comme il la servirait, ou le message qui l'en
	 * empêche.
	 */
	private function renderView(): string {

		// `ViewAction::run()` ouvre son propre tampon et ne le referme pas.
		$level = ob_get_level();
		ob_start();

		try {
			$this->viewAction->run();
			$error = NULL;
		} catch(\Exception $e) {
			$error = $e->getMessage();
		}

		$rendered = '';

		while(ob_get_level() > $level) {
			$rendered = ob_get_clean().$rendered;
		}

		return $error ?? $rendered;

	}

	/**
	 * Les étapes telles que la vue les rend, sur les données de la dernière page jouée.
	 */
	private function getStepsHtml(): string {

		$data = $this->viewData;

		return $this->getUi()->getSteps($data->e, $data->step, $data->status, $data->commitments, $data->cChapter, $data->cStaff, $data->online);

	}

	private function getPath(): string {
		return 'reseau/'.\structure\CommunityLib::getByStructure($this->eStructureNetwork)['fqn'];
	}

	/**
	 * Une formation publiée et datée du réseau, ouverte à ses fermes, et une date dans dix jours.
	 */
	private function seedDate(?int $seats): Date {

		$eCourse = $this->seed(Course::ONSITE);

		$this->assertEquals([], $this->shareNetwork($eCourse), 'formation ouverte aux membres du réseau');

		return $this->createDate($eCourse, $seats);

	}

	private function createDate(Course $eCourse, ?int $seats): Date {

		$eDate = new Date(['course' => $eCourse]);

		$fw = new \FailWatch();

		$eDate->build(DateLib::getPropertiesCreate(), $this->postSequences([
			[$this->day(10), '09:00', '17:00', $eCourse->getDefaultSequence()['mode'], 'Salle des fêtes, Crest'],
		]) + [
			'link' => 'https://visio.example.org/zztest',
			'seats' => ($seats === NULL) ? '' : (string)$seats,
		], new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('date refusée : '.json_encode($fw->get())));

		DateLib::create($eDate);

		return DateLib::getById($eDate['id']);

	}

	private function createUser(string $email): \user\User {

		$eUser = new \user\User([
			'firstName' => 'zzTest',
			'lastName' => 'Public',
			'email' => $email,
			'visibility' => \user\User::PRIVATE,
			'roleCustomer' => FALSE,
			'roleProducer' => TRUE,
			'roleConsultant' => FALSE,
			'roleTeacher' => FALSE
		]);

		$fw = new \FailWatch();

		\user\UserLib::create($eUser);

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('création du compte refusée : '.json_encode($fw->get())));

		return $this->getUser($eUser['id']);

	}

	/**
	 * Une personne de la ferme rattachée, qui lit donc une formation ouverte aux fermes du réseau.
	 */
	private function createPerson(): \user\User {

		$this->people++;

		$eUser = $this->createUser('zztest.public-membre-'.$this->people.'@example.org');

		$this->join($eUser, $this->eStructureFarm);

		return $eUser;

	}

	/**
	 * Une ligne sans compte écrite par le Lib, comme `doCreate` la prépare.
	 */
	private function createLinePublic(Date $eDate, string $email): DateEnrollment {

		$eStructureCommunity = \structure\StructureLib::getById($this->eStructureNetwork['id']);
		$eStructureCommunity['community'] = \structure\CommunityLib::getByStructure($eStructureCommunity);

		$e = DateEnrollmentLib::getPublicCreateElement($eDate, $eStructureCommunity, new \user\User());
		$e['firstName'] = 'Zztest';
		$e['lastName'] = 'Public';
		$e['email'] = $email;
		$e['trainee'] = DateEnrollment::FARMER;
		$e['committedAt'] = \Clock::datetime();

		$fw = new \FailWatch();

		DateEnrollmentLib::createPublic($e);

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('inscription publique refusée : '.json_encode($this->getFails($fw))));

		return DateEnrollmentLib::getById($e['id'], DateEnrollment::getSelection());

	}

	/**
	 * La ligne du compte, écrite par le chemin du panneau du membre.
	 */
	private function createLineAccount(Date $eDate, \user\User $eUser): DateEnrollment {

		$e = DateEnrollmentLib::getCreateElement($eDate, $eUser);
		$e['structure'] = new \structure\Structure();
		$e['trainee'] = DateEnrollment::FARMER;
		$e['committedAt'] = \Clock::datetime();

		DateEnrollmentLib::create($e);

		return DateEnrollmentLib::getByDateAndUser($eDate, $eUser);

	}

	private function getLineByEmail(Date $eDate, string $email): DateEnrollment {

		return DateEnrollment::model()
			->select(DateEnrollment::getSelection())
			->whereDate($eDate)
			->whereEmail($email)
			->get();

	}

	private function setConfirm(bool $confirm): void {

		$fw = new \FailWatch();

		CommunityLib::updateEnrollment($this->eStructureNetwork, ['learningConfirm' => $confirm ? '1' : '0', 'learningOnlineConfirm' => \structure\Community::FUNDING]);

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('réglage refusé : '.json_encode($this->getFails($fw))));

	}

	/**
	 * Un autre réseau de la base qui a une adresse, pour les dates et les jetons qu'on lit sous la
	 * mauvaise adresse.
	 */
	private function getNetworkOther(): \structure\Structure {

		foreach($this->getNetworks() as $eStructure) {
			if($eStructure['id'] !== $this->eStructureNetwork['id']) {
				return $eStructure;
			}
		}

		throw new \Exception('aucun autre réseau avec une adresse dans cette base');

	}

	/**
	 * Un réseau sans date ni formation en ligne proposée, qui relève des prix (`$withQuotation`) ou
	 * non : les deux autres issues de la racine. Ouvert aux formations, pour que sa racine ne doive
	 * ces issues qu'à ce qu'il ne propose pas.
	 */
	private function getNetworkWithout(bool $withQuotation): \structure\Structure {

		foreach($this->getNetworks() as $eStructure) {

			if(
				$eStructure->acceptLearning() and
				DateLib::existsPublicByCommunity($eStructure) === FALSE and
				CourseLib::getPublicOnlineByCommunity($eStructure)->empty() and
				($eStructure->acceptGroupQuotationSetting() and $eStructure->acceptGroupQuotation()) === $withQuotation
			) {
				return $eStructure;
			}

		}

		throw new \Exception('aucun réseau sans date ni formation en ligne '.($withQuotation ? 'qui relève des prix' : 'ni relevés').' dans cette base');

	}

	private function getNetworks(): \Collection {

		$structures = \structure\Community::model()
			->select('structure')
			->getCollection()
			->getColumnCollection('structure')
			->getIds();

		return \structure\Structure::model()
			->select(\structure\Structure::getSelection())
			->whereId('IN', $structures)
			->whereType(\structure\Structure::COMMUNITY)
			->whereCommunityType(\structure\Structure::NETWORK)
			->sort(['id' => SORT_ASC])
			->getCollection();

	}

	/**
	 * La route jouée par son adresse, comme `CommunityAddressAi::callRoute()` : la classe de l'action,
	 * et la vue d'une `ViewAction`, l'adresse d'une redirection ou le refus d'une `FailAction`.
	 *
	 * @return array{0: string, 1: ?string}
	 */
	private function call(string $method, string $path, array $input = []): array {

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, $method);

		[$getInitial, $postInitial, $requestInitial] = [$_GET, $_POST, $_REQUEST];
		[$_GET, $_POST, $_REQUEST] = match($method) {
			'GET' => [$input, [], $input],
			'POST' => [[], $input, $input],
		};

		try {

			$pages = \Route::getCompatiblePages($path);

			// Aucune route sous cette méthode : le cadriciel servirait la 404.
			if($pages === []) {
				return ['aucune route', NULL];
			}

			// Ce que le démarrage de l'application pose avant toute page (`main\PageLib::common()`), que
			// `Page::execute()` ne rejoue pas et dont le gabarit a besoin pour rendre une vue. `isLogged`
			// reste à faux même pour un compte connecté : la navigation du compte lirait le reste de
			// `common()`, et aucun de ces tests ne porte sur elle.
			$data = new \stdClass();
			$data->eUserOnline = \user\ConnectionLib::getOnline();
			$data->isLogged = FALSE;
			$data->browserObsolete = FALSE;
			$data->logInExternal = NULL;

			\Page::execute($pages, $data);
			return ['aucune action', NULL];

		} catch(\ViewAction $action) {
			$this->viewData = new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);
			$this->viewAction = $action;
			return [\ViewAction::class, new \ReflectionProperty(\ViewAction::class, 'path')->getValue($action)];
		} catch(\RedirectAction | \PermanentRedirectAction $action) {
			return [get_class($action), new \ReflectionProperty($action, 'url')->getValue($action)];
		} catch(\FailAction $action) {
			$fail = new \ReflectionProperty(\FailAction::class, 'fail')->getValue($action);
			return [\FailAction::class, is_string($fail) ? $fail : implode(', ', array_column($fail->format(), 0))];
		} catch(\Action $action) {
			return [get_class($action), NULL];
		} finally {
			[$_GET, $_POST, $_REQUEST] = [$getInitial, $postInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
			$this->actAs(\user\ConnectionLib::getOnline());
		}

	}

	/**
	 * Le code du processus voyage par l'environnement, comme dans `DateEnrollment.t.php` : la commande
	 * ne se compose que de littéraux (`ShellCommandSentinel`).
	 */
	private function spawn(string $code): array {

		$process = proc_open(
			['php', 'framework/lime.php', '-a', 'ouvretaferme', '-e', 'dev', '-r', 'eval(getenv(\'DATE_PUBLIC_RACE\'));'],
			[1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
			$pipes,
			LIME_DIRECTORY,
			['DATE_PUBLIC_RACE' => $code] + getenv()
		);

		return [$process, $pipes];

	}

	private function finish(array $spawned): string {

		[$process, $pipes] = $spawned;

		$output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);

		fclose($pipes[1]);
		fclose($pipes[2]);
		proc_close($process);

		return $output;

	}

}
?>
