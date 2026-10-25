<?php
namespace learning;

require_once __DIR__.'/LearningDateFixture.t.php';

/**
 * Le lien d'une visio (lots lien-visio-differe et formulaires-visio) : un réseau publie ses dates
 * et ouvre les inscriptions avant d'avoir créé la réunion. La date s'écrit sans lien ; le lien posé
 * reste entre les mains du réseau, qui l'envoie aux personnes confirmées par un geste, une fois
 * (`DateLib::sendLink()`). Tant qu'il n'est pas envoyé, l'inscrit confirmé lit qu'il lui sera
 * envoyé, sur la fiche comme dans les e-mails ; ensuite il le lit, et toute confirmation le porte.
 * L'éditeur signale en orange la date qui attend un geste, lien à poser puis lien à envoyer ; un
 * lien changé après l'envoi redemande le geste ; le rappel parti sans lien le dit, et alerte le
 * réseau.
 *
 * Aucun e-mail n'est écrit : les personnes du décor sont en `example.org`, hors de
 * `MailSetting::$devSendOnly`, et l'adresse de contact du réseau du décor aussi. Les e-mails se lisent
 * par `DateEnrollmentLib::getMails()` et `getLinkMissingMails()`, les envois se comptent par ce que
 * rendent `DateLib::sendLink()` et `DateEnrollmentLib::remind()`, et le dernier identifiant de
 * `mailEmail` ne bouge pas.
 *
 * Lancement : dev/test package=learning file=DateLink
 */
class DateLinkTest extends LearningDateFixtureTest {

	const LINK = 'https://visio.example.org/zztest-lien';
	/** Le lien que `LearningDateFixtureTest::createDate()` pose à la création. */
	const CREATED = 'https://visio.example.org/zztest';
	const PENDING = 'le lien vous sera envoyé par e-mail avant la séance';

	/**
	 * Une date en visio se crée sans lien, et se modifie sans lien ; le lien se pose ensuite. Le
	 * lieu d'une session sur place reste exigé.
	 */
	public function testOptional(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourseRemote = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE, type: Course::REMOTE);
		$eCourseOnsite = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE, type: Course::ONSITE);

		$posted = [
			'link' => '',
			'seats' => '',
		] + $this->postSequences([
			[$this->day(10), '14:00', '16:30', DateSequence::REMOTE],
		]);

		$postedOnsite = [
			'link' => '',
			'seats' => '',
		] + $this->postSequences([
			[$this->day(10), '14:00', '16:30', DateSequence::ONSITE, ''],
		]);

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/date:doCreate', ['course' => (string)$eCourseRemote['id']] + $posted), 'une visio sans lien se crée');
		$this->assertEquals([\FailAction::class, 'DateSequence::place.required'], $this->executePage('learning/date:doCreate', ['course' => (string)$eCourseOnsite['id']] + $postedOnsite), 'une session sur place sans lieu reste refusée');

		$eDate = DateLib::getByCourse($eCourseRemote)->first();

		$this->assertEquals(NULL, $eDate['link'], 'sans lien');
		$this->assertTrue($eDate->isLinkMissing(), 'et le lien manque');

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/date:doUpdate', ['id' => (string)$eDate['id'], 'seats' => '12'] + $posted), 'elle se modifie encore sans lien');
		$this->assertEquals([\FailAction::class, 'Date::link.check'], $this->executePage('learning/date:doUpdate', ['id' => (string)$eDate['id'], 'link' => 'salle'] + $posted), 'un lien qui n\'est pas une adresse reste refusé');
		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/date:doUpdate', ['id' => (string)$eDate['id'], 'link' => self::LINK] + $posted), 'le lien se pose ensuite');

		$eDate = DateLib::getById($eDate['id']);

		$this->assertEquals(self::LINK, $eDate['link'], 'le lien posé');
		$this->assertFalse($eDate->isLinkMissing(), 'et il ne manque plus');

		// Le lien d'une visio est montré tant qu'une séance est en visio, masqué sinon, pour qu'une
		// séance passée en visio le fasse paraître.
		$fields = fn(Course $eCourse) => new DateUi()->getFields(new \util\FormUi(), new Date(['course' => $eCourse, 'startAt' => NULL, 'endAt' => NULL, 'link' => NULL, 'seats' => NULL, 'minutes' => NULL, 'cDateSequence' => new \Collection([$eCourse->getDefaultSequence()])]));

		$this->assertEquals(1, preg_match('/<div data-wrapper="link"[^>]*class="form-group\s*"/', $fields($eCourseRemote)), 'mais montré');
		$this->assertEquals(1, preg_match('/<div data-wrapper="link"[^>]*class="form-group[^"]*\bhide\b/', $fields($eCourseOnsite)), 'sur place, rendu masqué : '.$fields($eCourseOnsite));

	}

	/**
	 * Le manque ne se dit que d'une visio planifiée à venir : ni sur place, ni annulée, ni passée,
	 * ni avec son lien.
	 */
	public function testMissing(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seed(Course::REMOTE);
		$eCourseOnsite = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE, type: Course::ONSITE);

		$eDateMissing = $this->withoutLink($this->createDate($eCourse, 5));
		$eDateLinked = $this->createDate($eCourse, 6);
		$eDatePast = $this->withoutLink($this->createDate($eCourse, -2));
		$eDateCanceled = $this->withoutLink($this->createDate($eCourse, 7));
		DateLib::cancel($eDateCanceled);
		$eDateOnsite = $this->createDate($eCourseOnsite, 5);

		$this->assertTrue(DateLib::getById($eDateMissing['id'])->isLinkMissing(), 'visio à venir sans lien');
		$this->assertFalse(DateLib::getById($eDateLinked['id'])->isLinkMissing(), 'visio avec son lien');
		$this->assertFalse(DateLib::getById($eDatePast['id'])->isLinkMissing(), 'visio passée');
		$this->assertFalse(DateLib::getById($eDateCanceled['id'])->isLinkMissing(), 'visio annulée');
		$this->assertFalse(DateLib::getById($eDateOnsite['id'])->isLinkMissing(), 'sur place');

	}

	/**
	 * L'inscrit attend le lien d'une visio planifiée à venir tant que le réseau ne l'a pas envoyé,
	 * qu'il soit posé ou non. Le réseau peut l'envoyer quand il est posé, pas encore parti, que la
	 * date n'est ni finie ni annulée et qu'une personne confirmée l'attend.
	 */
	public function testAcceptSendLink(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$mail = $this->getLastMail();

		$eCourse = $this->seed(Course::REMOTE);
		$eCourseOnsite = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE, type: Course::ONSITE);

		$eDate = $this->createDate($eCourse, 5);

		$this->assertTrue($this->prepareDate($eDate)->isLinkAwaited(), 'un lien posé et pas envoyé : l\'inscrit l\'attend encore');
		$this->assertFalse($this->prepareDate($eDate)->acceptSendLink(), 'sans personne confirmée, rien à envoyer');

		$this->enrollRequested($eDate, $this->createPerson());
		$this->assertFalse($this->prepareDate($eDate)->acceptSendLink(), 'une demande à confirmer ne compte pas');

		// Un compte fermé par le chemin du produit garde sa place confirmée et n'a plus d'adresse :
		// une date qui n'a que lui ne propose pas un envoi sans destinataire.
		$eUserClosed = $this->createPerson();
		$this->enrollConfirmed($eDate, $eUserClosed);
		\user\DropLib::closeNow($eUserClosed);

		$eDateClosed = $this->prepareDate($eDate);
		$this->assertEquals([1, 0], [$eDateClosed['confirmed'], $eDateClosed['linkRecipients']], 'une confirmée au compte fermé : une place, aucun destinataire');
		$this->assertFalse($eDateClosed->acceptSendLink(), 'une confirmée qui ne recevrait rien ne compte pas');
		$this->assertFalse(str_contains($this->getDecks($eCourse)[$eDate['id']], 'date:sendLink'), 'et la carte ne propose pas l\'envoi');
		$this->assertTrue(str_contains($this->getDecks($eCourse)[$eDate['id']], 'href="'.self::CREATED.'"'), 'elle montre le lien posé');
		$this->actAs($this->eUserAdmin);
		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/date:sendLink', ['id' => (string)$eDate['id']], 'GET'), 'le panneau ne s\'ouvre pas');
		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/date:doSendLink', ['id' => (string)$eDate['id']]), 'ni le geste');

		$this->enrollConfirmed($eDate, $this->createPerson());
		$this->assertTrue($this->prepareDate($eDate)->acceptSendLink(), 'un lien posé, une personne confirmée : le lien peut partir');

		$this->withoutLink($eDate);
		$this->assertFalse($this->prepareDate($eDate)->acceptSendLink(), 'sans lien, rien à envoyer');
		$this->assertTrue($this->prepareDate($eDate)->isLinkAwaited(), 'et l\'inscrit l\'attend');
		$this->setLink($eDate, self::LINK);

		$eDateStarted = $this->createDate($eCourse, 0, '00:00:00');
		$this->enrollConfirmed($eDateStarted, $this->createPerson());
		$this->assertTrue($this->prepareDate($eDateStarted)->acceptSendLink(), 'une date commencée et pas finie l\'accepte encore');

		$eDatePast = $this->createDate($eCourse, 3);
		$this->enrollConfirmed($eDatePast, $this->createPerson());
		$eDatePast = $this->moveDate($eDatePast, -2);
		$this->assertFalse($this->prepareDate($eDatePast)->acceptSendLink(), 'une date finie n\'envoie plus rien');
		$this->assertFalse($this->prepareDate($eDatePast)->isLinkAwaited(), 'et plus personne n\'attend');

		$eDateCanceled = $this->createDate($eCourse, 4);
		$this->enrollConfirmed($eDateCanceled, $this->createPerson());
		DateLib::cancel(DateLib::getById($eDateCanceled['id']));
		$this->assertFalse($this->prepareDate($eDateCanceled)->acceptSendLink(), 'une date annulée non plus');
		$this->assertFalse($this->prepareDate($eDateCanceled)->isLinkAwaited(), 'annulée : personne n\'attend');

		$eDateOnsite = $this->createDate($eCourseOnsite, 5);
		$this->enrollConfirmed($eDateOnsite, $this->createPerson());
		$this->assertFalse($this->prepareDate($eDateOnsite)->acceptSendLink(), 'sur place, pas de lien à envoyer');
		$this->assertFalse($this->prepareDate($eDateOnsite)->isLinkAwaited(), 'sur place : personne n\'attend de lien');

		$this->actAs($this->eUserAdmin);
		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/date:doSendLink', ['id' => (string)$eDate['id']]), 'le réseau envoie');
		$this->assertFalse($this->prepareDate($eDate)->acceptSendLink(), 'un lien envoyé ne repart pas');
		$this->assertFalse($this->prepareDate($eDate)->isLinkAwaited(), 'et l\'inscrit ne l\'attend plus');
		$this->assertTrue($this->prepareDate($eDate)->isLinkMailed(), 'la date en garde la trace');

		$this->assertEquals($mail, $this->getLastMail(), 'aucun e-mail écrit');

	}

	/**
	 * Un seul envoi par lien, par le réseau seulement, et un e-mail à chaque personne confirmée au
	 * moment du geste : ni aux demandes, ni aux annulées, ni à un compte fermé.
	 */
	public function testSendOnce(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$mail = $this->getLastMail();

		$eCourse = $this->seed(Course::REMOTE);
		$eDate = $this->createDate($eCourse, 5);

		$eUserA = $this->createPerson();
		$this->enrollConfirmed($eDate, $eUserA);
		$this->enrollPublicConfirmed($eDate, 'zztest.lien-envoi@example.org');
		$this->enrollRequested($eDate, $this->createPerson());
		DateEnrollmentLib::cancel($this->enrollConfirmed($eDate, $this->createPerson()), DateEnrollment::USER);
		// Un compte fermé par le chemin du produit, qui vide son adresse : sa place reste confirmée,
		// aucun e-mail ne part pour elle et le panneau ne la compte pas.
		$eUserClosed = $this->createPerson();
		$eDateEnrollmentClosed = $this->enrollConfirmed($eDate, $eUserClosed);
		\user\DropLib::closeNow($eUserClosed);

		$this->assertEquals([], DateEnrollmentLib::getMails($this->getLine($eDateEnrollmentClosed['id']), 'linkMailed'), 'un compte fermé ne reçoit pas le lien');

		$this->actAs($eUserA);
		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('learning/date:sendLink', ['id' => (string)$eDate['id']], 'GET'), 'une personne inscrite n\'ouvre pas le panneau');
		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('learning/date:doSendLink', ['id' => (string)$eDate['id']]), 'ni le geste');

		$this->actAs($this->eUserAdmin);
		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('learning/date:sendLink', ['id' => (string)$eDate['id']], 'GET'), 'le réseau ouvre le panneau');

		$eDatePanel = $this->prepareDate($eDate);
		$this->assertEquals([3, 2], [$eDatePanel['confirmed'], $eDatePanel['linkRecipients']], 'trois confirmées, deux qui ont une adresse');

		$panel = new DateUi()->sendLink($eDatePanel);
		$this->assertTrue(str_contains($panel->body, '<a data-ajax="/learning/date:doSendLink" post-id="'.$eDate['id'].'"'), 'le geste dans le corps du panneau : '.$panel->body);

		$this->assertEquals($eDatePanel['linkRecipients'], DateLib::sendLink($this->prepareDate($eDate)), 'autant d\'e-mails que le panneau en annonce');
		$this->assertTrue(DateLib::getById($eDate['id'])->isLinkMailed(), 'la date garde la trace de l\'envoi');

		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/date:sendLink', ['id' => (string)$eDate['id']], 'GET'), 'le panneau ne s\'ouvre plus');
		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/date:doSendLink', ['id' => (string)$eDate['id']]), 'le geste n\'est plus proposé, l\'état le refuse');

		// Le Lib refuse encore le second envoi si deux gestes se croisent, sans e-mail. L'horloge
		// avance d'une minute : dans la même seconde, MySQL compte zéro ligne changée pour une date
		// réécrite à l'identique, et le refus passerait sans la garde de l'écriture.
		$this->setClock(date('Y-m-d H:i:s', \Clock::timestamp() + 60));

		$fw = new \FailWatch();
		$this->assertEquals(0, DateLib::sendLink($this->prepareDate($eDate)), 'un second envoi ne remet rien');
		$this->assertEquals(['Date::linkAlreadyMailed'], array_column($fw->get(), 1), 'et lève son refus');
		$this->assertTrue(\learning\AlertUi::getError('Date::linkAlreadyMailed') !== NULL, 'le refus a son message');
		$this->assertTrue(\learning\AlertUi::getSuccess('Date::linkMailed') !== NULL, 'et le succès le sien');

		$this->assertEquals($mail, $this->getLastMail(), 'aucun e-mail écrit');

	}

	/**
	 * Le lien effacé ou changé entre la lecture de la date et l'envoi, par un autre geste du réseau :
	 * l'élément lu porte encore l'ancien lien et l'état qui ouvre l'envoi. Rien ne part, aucune trace
	 * ne se pose, et le geste est refusé.
	 */
	public function testLinkGoneBeforeSend(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$mail = $this->getLastMail();

		$eCourse = $this->seed(Course::REMOTE);
		$eDate = $this->createDate($eCourse, 5);

		$this->enrollConfirmed($eDate, $this->createPerson());

		foreach([
			'effacé' => fn() => $this->withoutLink($eDate),
			'changé' => fn() => $this->setLink($eDate, self::LINK),
		] as $case => $change) {

			$this->setLink($eDate, self::CREATED);

			$eDateRead = $this->prepareDate($eDate);
			$this->assertTrue($eDateRead->acceptSendLink(), 'lien '.$case.' : la date lue ouvre l\'envoi');

			$change();

			$fw = new \FailWatch();
			$this->assertEquals(0, DateLib::sendLink($eDateRead), 'lien '.$case.' depuis la lecture : aucun e-mail');
			$this->assertEquals(['Date::linkAlreadyMailed'], array_column($fw->get(), 1), 'lien '.$case.' : le geste est refusé');
			$this->assertEquals(NULL, DateLib::getById($eDate['id'])['linkMailedAt'], 'lien '.$case.' : aucune trace d\'envoi');

		}

		$this->assertEquals(1, DateLib::sendLink($this->prepareDate($eDate)), 'la date relue envoie le lien qu\'elle porte');

		$this->assertEquals($mail, $this->getLastMail(), 'aucun e-mail écrit');

	}

	/**
	 * L'e-mail du lien : le jour dans l'objet, la séance dans les faits avec sa durée, le lien en
	 * clair et en bouton principal, l'annulation et le contact dans le texte. Un membre annule depuis
	 * la fiche, une ligne de la page publique depuis la page de son inscription.
	 */
	public function testLinkedMail(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$mail = $this->getLastMail();

		$eCourse = $this->seed(Course::REMOTE);
		$eDate = $this->setLink($this->createDate($eCourse, 5), self::LINK);

		$eDateEnrollmentMember = $this->enrollConfirmed($eDate, $this->createPerson());
		$eDateEnrollmentPublic = $this->enrollPublicConfirmed($eDate, 'zztest.lien-mail@example.org');

		$this->assertEquals(2, DateLib::sendLink($this->prepareDate($eDate)), 'le lien part aux deux confirmées');

		$eDate = DateLib::getById($eDate['id']);

		[$title, $text, $html] = DateEnrollmentLib::getMails($this->getLine($eDateEnrollmentMember['id']), 'linkMailed')[0]['content'];

		$this->assertEquals(s("Le lien de la visio du {value}", mb_strtolower(DateUi::getDays($eDate))), $title, 'l\'objet dit le jour');
		$this->assertTrue(str_contains($html, '>'.self::LINK.'</a>'), 'le lien écrit en clair dans les faits : '.$html);
		$this->assertTrue(str_contains($text, '- '.s("Lien de la visio").' : '.self::LINK), 'et dans le texte : '.$text);
		$this->assertTrue(str_contains($text, s("Rejoindre la visio")."\n".self::LINK), 'le bouton principal rejoint la visio : '.$text);
		$this->assertEquals(1, preg_match('#<a href="'.preg_quote(self::LINK, '#').'"[^>]*>\s*'.s("Rejoindre la visio").'\s*</a>#', $html), 'en bouton dans l\'e-mail : '.$html);
		$this->assertTrue(str_contains($text, '- '.s("Durée").' : 14 h'."\n"), 'la durée de la séance, sans entité dans le texte : '.$text);
		$this->assertFalse(str_contains($text, '&nbsp;'), 'aucune entité dans le texte : '.$text);
		$this->assertTrue(str_contains($text, 'la fiche de la formation : '.\Lime::getUrl().CourseUi::url($eCourse)), 'un membre annule depuis la fiche : '.$text);
		$this->assertTrue(str_contains($text, 'Pour en savoir plus, écrivez à '), 'et sait à qui écrire : '.$text);
		$this->assertFalse(str_contains($html, s("Au nom de")), 'l\'e-mail rappelle la séance, pas l\'inscription');

		[, $textPublic] = DateEnrollmentLib::getMails($this->getLine($eDateEnrollmentPublic['id']), 'linkMailed')[0]['content'];

		$this->assertTrue(str_contains($textPublic, 'la page de votre inscription : '), 'une ligne publique annule depuis la page de son inscription : '.$textPublic);
		$this->assertTrue(str_contains($textPublic, $this->getLine($eDateEnrollmentPublic['id'])['token']), 'par son jeton');
		$this->assertFalse(str_contains($textPublic, 'la fiche de la formation'), 'et non depuis une fiche qu\'elle peut ne pas lire');

		$this->assertEquals($mail, $this->getLastMail(), 'aucun e-mail écrit');

	}

	/**
	 * Dans l'éditeur, la carte d'une visio à venir porte le geste que son lien attend : l'envoi
	 * quand il est posé et qu'une personne confirmée l'attend, plus rien une fois envoyé. Dès qu'un
	 * lien est posé, la carte y mène (retour du 02/10). La date passée et l'annulée ne
	 * montrent rien, une date en présentiel non plus même si sa ligne porte un lien, et la fiche ne
	 * montre jamais d'adresse de visio.
	 */
	public function testEditionSignal(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$mail = $this->getLastMail();

		$eCourse = $this->seed(Course::REMOTE);

		$eDateMissing = $this->withoutLink($this->createDate($eCourse, 5));
		$eDatePosed = $this->createDate($eCourse, 6);
		$eDateToSend = $this->createDate($eCourse, 7);
		$eUserToSend = $this->createPerson();
		$this->enrollConfirmed($eDateToSend, $eUserToSend);
		$eDateMailed = $this->createDate($eCourse, 8);
		$this->enrollConfirmed($eDateMailed, $this->createPerson());
		DateLib::sendLink($this->prepareDate($eDateMailed));
		$eDatePast = $this->withoutLink($this->createDate($eCourse, -2));
		$eDatePastPosed = $this->createDate($eCourse, -3);
		$eDateCanceled = $this->withoutLink($this->createDate($eCourse, 9));
		DateLib::cancel($eDateCanceled);
		$eDateCanceledPosed = $this->createDate($eCourse, 10);
		DateLib::cancel($eDateCanceledPosed);

		$decks = $this->getDecks($eCourse);

		$missing = $decks[$eDateMissing['id']];

		$this->assertFalse(str_contains($missing, 'date:sendLink'), 'sans lien, rien à envoyer');
		$this->assertFalse(str_contains($missing, 'href="https://visio.example.org'), 'ni lien à montrer');

		$toSend = $decks[$eDateToSend['id']];

		$this->assertTrue(str_contains($toSend, '<a href="/learning/date:sendLink?id='.$eDateToSend['id'].'"'), 'la porte de l\'envoi : '.$toSend);
		$this->assertTrue(str_contains($toSend, 'href="'.self::CREATED.'"'), 'et le lien à envoyer');

		$posed = $decks[$eDatePosed['id']];

		$this->assertTrue(str_contains($posed, 'href="'.self::CREATED.'"'), 'un lien posé que personne n\'attend se montre : '.$posed);
		$this->assertFalse(str_contains($posed, 'date:sendLink'), 'sans porte d\'envoi');

		$mailed = $decks[$eDateMailed['id']];

		$this->assertTrue(str_contains($mailed, 'href="'.self::CREATED.'"'), 'un lien envoyé se montre : '.$mailed);
		$this->assertFalse(str_contains($mailed, 'date:sendLink'), 'le geste n\'est plus proposé');

		foreach([
			'passée' => $eDatePast,
			'passée avec son lien' => $eDatePastPosed,
			'annulée' => $eDateCanceled,
			'annulée avec son lien' => $eDateCanceledPosed,
		] as $case => $eDate) {
			$this->assertFalse(str_contains($decks[$eDate['id']], 'href="https://visio.example.org'), $case.' : sans lien à montrer');
		}

		// La fiche ne montre jamais le lien posé : il y reste à la personne confirmée qui l'a reçu.
		// Lue par la confirmée de la date « à envoyer », elle ne porte aucune adresse de visio.
		$cDateFiche = DateLib::getByCourse($eCourse);
		DateEnrollmentLib::fillForCourse($cDateFiche, $eUserToSend);
		DateEnrollmentLib::fillLosesLessons($eCourse, $cDateFiche->getColumnCollection('eDateEnrollment'));

		$fiche = new DateUi()->getFicheSection($cDateFiche);

		$this->assertFalse(str_contains($fiche, 'visio.example.org'), 'la fiche ne porte aucune adresse de visio');

		$eCourseOnsite = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE, type: Course::ONSITE);
		$eDateOnsite = $this->createDate($eCourseOnsite, 6);
		// Écrit hors de `build()` et du Lib, qui effacent le lien d'une date en présentiel : c'est la ligne qu'ils refusent que le cas éprouve.
		Date::model()->update($eDateOnsite, ['link' => self::LINK, 'linkMailedAt' => \Clock::datetime()]);

		$eDateOnsite = DateLib::getById($eDateOnsite['id']);
		$this->assertEquals([self::LINK, TRUE], [$eDateOnsite['link'], $eDateOnsite['linkMailedAt'] !== NULL], 'décor : un présentiel qui porte un lien et une trace d\'envoi');
		$this->assertFalse($eDateOnsite->isLinkPosed(), 'un présentiel ne porte pas de lien de visio, même si sa ligne en garde un');

		$onsite = $this->getDecks($eCourseOnsite)[$eDateOnsite['id']];

		$this->assertFalse(str_contains($onsite, 'href="'.self::LINK.'"'), 'présentiel : sans lien à montrer : '.$onsite);

		$this->assertEquals($mail, $this->getLastMail(), 'aucun e-mail écrit');

	}

	/**
	 * La roue d'une date propose l'annulation et la suppression : un inscrit ferme la suppression,
	 * une date passée ferme l'annulation.
	 */
	public function testGearDangerZone(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$mail = $this->getLastMail();

		$eCourse = $this->seed(Course::REMOTE);

		$eDateEmpty = $this->createDate($eCourse, 5);
		$eDateEnrolled = $this->createDate($eCourse, 6);
		$this->enrollConfirmed($eDateEnrolled, $this->createPerson());

		$decks = $this->getDecks($eCourse);

		$empty = $decks[$eDateEmpty['id']];

		$this->assertTrue(str_contains($empty, 'href="/learning/date:cancel?id='.$eDateEmpty['id'].'"'), 'une date à venir s\'annule : '.$empty);
		$this->assertTrue(str_contains($empty, 'data-ajax="/learning/date:doDelete" post-id="'.$eDateEmpty['id'].'"'), 'une date sans inscrit se supprime');

		$enrolled = $decks[$eDateEnrolled['id']];

		$this->assertFalse(str_contains($enrolled, 'learning/date:doDelete'), 'la suppression fermée par un inscrit : '.$enrolled);

		$eDatePast = $this->createDate($eCourse, -2);

		$past = $this->getDecks($eCourse)[$eDatePast['id']];

		$this->assertFalse(str_contains($past, 'learning/date:cancel?'), 'l\'annulation fermée sur une date passée : '.$past);

		$this->assertEquals($mail, $this->getLastMail(), 'aucun e-mail écrit');

	}

	/**
	 * À l'inscrit confirmé, la ligne de la date dit que le lien lui sera envoyé tant que le réseau
	 * ne l'a pas envoyé, même posé ; une fois envoyé, elle y mène. Rien de tel sans place confirmée.
	 */
	public function testLine(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$mail = $this->getLastMail();

		$eCourse = $this->seed(Course::REMOTE);

		$eDate = $this->setLink($this->createDate($eCourse, 5), self::LINK);

		$this->assertTrue(str_contains(DateUi::getLine($eDate, withLink: TRUE), self::PENDING), 'le lien posé et pas envoyé : encore la phrase');
		$this->assertFalse(str_contains(DateUi::getLine($eDate, withLink: TRUE), 'visio.example.org'), 'rien de lisible avant l\'envoi');

		$this->enrollConfirmed($eDate, $this->createPerson());
		DateLib::sendLink($this->prepareDate($eDate));

		$eDate = DateLib::getById($eDate['id']);

		$this->assertTrue(str_contains(DateUi::getLine($eDate, withLink: TRUE), 'href="'.self::LINK.'"'), 'le lien envoyé : la ligne y mène');
		$this->assertFalse(str_contains(DateUi::getLine($eDate, withLink: TRUE), self::PENDING), 'sans la phrase');
		$this->assertFalse(str_contains(DateUi::getLine($eDate, withLink: FALSE), 'visio.example.org'), 'toujours rien sans place confirmée');

		$this->assertEquals($mail, $this->getLastMail(), 'aucun e-mail écrit');

	}

	/**
	 * Les e-mails qui portent le lien disent qu'il suivra tant que le réseau ne l'a pas envoyé, sans
	 * lien comme avec un lien posé : la confirmation par le réseau, la confirmation directe, la place
	 * réservée de la page publique et sa confirmation, la place donnée et le rappel. Une fois le lien
	 * envoyé, les six le portent : une personne confirmée ensuite le reçoit avec sa confirmation,
	 * quel que soit le chemin qui la pose.
	 */
	public function testMailsBeforeAndAfter(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$mail = $this->getLastMail();

		$eCourse = $this->seed(Course::REMOTE);
		$eDate = $this->withoutLink($this->createDate($eCourse, 2));

		$eDateEnrollmentMember = $this->enrollConfirmed($eDate, $this->createPerson());
		$eDateEnrollmentPublic = $this->enrollPublicConfirmed($eDate, 'zztest.lien-public@example.org');

		$ui = new DateEnrollmentUi();

		$contents = fn(): array => [
			'confirmation par le réseau' => DateEnrollmentLib::getMails($this->getLine($eDateEnrollmentMember['id']), 'confirmed')[0]['content'],
			'confirmation directe' => DateEnrollmentLib::getMails($this->getLine($eDateEnrollmentMember['id']), 'created')[0]['content'],
			'page publique' => DateEnrollmentLib::getMails($this->getLine($eDateEnrollmentPublic['id']), 'created')[0]['content'],
			'confirmation d\'une inscription publique' => DateEnrollmentLib::getMails($this->getLine($eDateEnrollmentPublic['id']), 'confirmed')[0]['content'],
			'place donnée' => $ui->getSeatGivenMail($this->getLine($eDateEnrollmentMember['id'])),
			'rappel' => DateEnrollmentLib::getMails($this->getLine($eDateEnrollmentMember['id']), 'reminded')[0]['content'],
		];

		foreach(['sans lien' => NULL, 'le lien posé et pas envoyé' => self::LINK] as $state => $link) {

			if($link !== NULL) {
				$this->setLink($eDate, $link);
			}

			foreach($contents() as $case => [, $text, $html]) {
				$this->assertTrue(str_contains($html, s("Il vous sera envoyé par e-mail avant la séance.")), $state.', '.$case.' : le lien suivra');
				$this->assertTrue(str_contains($text, s("Il vous sera envoyé par e-mail avant la séance.")), $state.', '.$case.' : aussi dans le texte');
				$this->assertFalse(str_contains($html, 'visio.example.org'), $state.', '.$case.' : aucun lien');
			}

		}

		$this->assertEquals(2, DateLib::sendLink($this->prepareDate($eDate)), 'le réseau envoie le lien aux deux confirmées');

		foreach($contents() as $case => [, $text, $html]) {
			$this->assertTrue(str_contains($html, 'href="'.self::LINK.'"'), 'le lien envoyé, '.$case.' : le lien, cliquable');
			$this->assertTrue(str_contains($text, self::LINK), 'le lien envoyé, '.$case.' : aussi dans le texte');
			$this->assertFalse(str_contains($html, s("Il vous sera envoyé par e-mail avant la séance.")), 'le lien envoyé, '.$case.' : sans la phrase d\'attente');
		}

		$this->assertEquals($mail, $this->getLastMail(), 'aucun e-mail écrit');

	}

	/**
	 * Poser ou changer le lien par le panneau de la date n'envoie rien : la date n'en garde aucune
	 * trace d'envoi et l'inscrit attend toujours. Après un envoi, le même lien reposté garde la trace ;
	 * un lien changé ou effacé la remet à vide, la carte redemande le geste et l'inscrit relit qu'il
	 * lui sera envoyé.
	 */
	public function testUpdate(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$mail = $this->getLastMail();

		$eCourse = $this->seed(Course::REMOTE);
		$eDate = $this->withoutLink($this->createDate($eCourse, 5));

		$this->enrollConfirmed($eDate, $this->createPerson());

		$this->actAs($this->eUserAdmin);

		$post = fn(string $link): array => $this->executePage('learning/date:doUpdate', [
			'id' => (string)$eDate['id'],
			'link' => $link,
			'seats' => '',
		] + $this->postSequences([
			[substr($eDate['startAt'], 0, 10), substr($eDate['startAt'], 11, 5), substr($eDate['endAt'], 11, 5), DateSequence::REMOTE],
		]));

		$this->assertEquals([\ReloadAction::class, NULL], $post(self::LINK), 'le lien posé par le panneau');

		$this->assertEquals(self::LINK, DateLib::getById($eDate['id'])['link'], 'il est écrit');
		$this->assertFalse(DateLib::getById($eDate['id'])->isLinkMailed(), 'et rien n\'est parti : aucune trace d\'envoi');
		$this->assertTrue($this->prepareDate($eDate)->acceptSendLink(), 'le geste d\'envoi s\'ouvre');
		$this->assertTrue(str_contains($this->getDecks($eCourse)[$eDate['id']], '/learning/date:sendLink?id='.$eDate['id']), 'la carte le propose');

		$this->assertEquals(1, DateLib::sendLink($this->prepareDate($eDate)), 'le réseau l\'envoie');

		$this->assertEquals([\ReloadAction::class, NULL], $post(self::LINK), 'le même lien reposté');
		$this->assertTrue(DateLib::getById($eDate['id'])->isLinkMailed(), 'la trace reste : le lien reçu est toujours le bon');

		$this->assertEquals([\ReloadAction::class, NULL], $post('https://visio.example.org/zztest-autre'), 'un autre lien');

		$eDateChanged = $this->prepareDate($eDate);

		$this->assertEquals(NULL, $eDateChanged['linkMailedAt'], 'la trace de l\'envoi repart à vide');
		$this->assertTrue($eDateChanged->acceptSendLink(), 'le geste d\'envoi se rouvre');
		$this->assertTrue(str_contains($this->getDecks($eCourse)[$eDate['id']], '/learning/date:sendLink?id='.$eDate['id']), 'la carte le repropose');
		$this->assertTrue($eDateChanged->isLinkAwaited(), 'et l\'inscrit relit qu\'il lui sera envoyé');

		$this->assertEquals(1, DateLib::sendLink($eDateChanged), 'le nouveau lien part à son tour');

		$this->assertEquals([\ReloadAction::class, NULL], $post(''), 'le lien effacé');

		$eDateErased = $this->prepareDate($eDate);

		$this->assertEquals(NULL, $eDateErased['linkMailedAt'], 'la trace ne vit pas sans lien');
		$this->assertTrue($eDateErased->isLinkMissing(), 'la date attend de nouveau son lien');

		$this->assertEquals($mail, $this->getLastMail(), 'aucun e-mail écrit');

	}

	/**
	 * Le rappel d'une date dont le lien n'est pas envoyé part quand même, en disant que le lien
	 * suit, sans révéler un lien posé ; le passage alerte le réseau à son adresse de contact, une
	 * fois par date, « à poser » ou « à envoyer » selon ce qui reste à faire, et jamais pour une date
	 * dont le lien est envoyé. L'alerte compte toutes les personnes à qui le lien partira, y compris
	 * celle que ce passage ne rappelle pas encore (`testLinkMissingRecipients()` pour le compte fermé).
	 *
	 * `remind()` balaie toute la base : un premier passage la vide de ce qu'elle avait à rappeler,
	 * les confirmations du test n'y entrant qu'une fois reculées, puis le second ne compte que les
	 * e-mails du test. Un test concurrent qui recule ses propres confirmations entre les deux passages
	 * fausserait le compte.
	 *
	 * Ce premier passage rappelle aussi les places réelles de la base dont la date tombe le lendemain
	 * ou le surlendemain, et `SendLib` écrit celles d'une adresse de `MailSetting::$devSendOnly` : le
	 * dernier e-mail se relève donc deux fois, avant lui pour l'envoi du lien, après lui pour les
	 * passages du test.
	 */
	public function testRemindWithoutLink(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$mail = $this->getLastMail();

		$eCourse = $this->seed(Course::REMOTE);
		$eDate = $this->withoutLink($this->createDate($eCourse, 1));
		$eDatePosed = $this->setLink($this->createDate($eCourse, 1, '11:00:00'), self::LINK);
		$eDateMailed = $this->createDate($eCourse, 1, '14:00:00');

		$eDateEnrollment = $this->enrollConfirmed($eDate, $this->createPerson());
		$eDateEnrollmentSecond = $this->enrollConfirmed($eDate, $this->createPerson());
		$this->enrollConfirmed($eDate, $this->createPerson());
		$eDateEnrollmentPosed = $this->enrollConfirmed($eDatePosed, $this->createPerson());
		$eDateEnrollmentMailed = $this->enrollConfirmed($eDateMailed, $this->createPerson());

		$this->assertEquals(1, DateLib::sendLink($this->prepareDate($eDateMailed)), 'le lien de la troisième date est envoyé');
		$this->assertEquals($mail, $this->getLastMail(), 'sans écrire d\'e-mail');

		DateEnrollmentLib::remind();

		$mail = $this->getLastMail();

		$this->age($eDateEnrollment);
		$this->age($eDateEnrollmentSecond);
		$this->age($eDateEnrollmentPosed);
		$this->age($eDateEnrollmentMailed);

		$this->assertEquals(6, DateEnrollmentLib::remind(), 'quatre rappels, et une alerte pour chacune des deux dates dont le lien n\'est pas envoyé');
		$this->assertTrue($this->getLine($eDateEnrollment['id'])['remindedAt'] !== NULL, 'la ligne sans lien est rappelée');
		$this->assertEquals(0, DateEnrollmentLib::remind(), 'le passage suivant ne rappelle ni n\'alerte de nouveau');

		[, , $htmlPosed] = DateEnrollmentLib::getMails($this->getLine($eDateEnrollmentPosed['id']), 'reminded')[0]['content'];

		$this->assertTrue(str_contains($htmlPosed, s("Il vous sera envoyé par e-mail avant la séance.")), 'le rappel d\'un lien posé et pas envoyé dit qu\'il suivra');
		$this->assertFalse(str_contains($htmlPosed, 'visio.example.org'), 'sans le révéler');

		$eDate = DateLib::getById($eDate['id']);

		$mails = DateEnrollmentLib::getLinkMissingMails($eDate);

		$this->assertEquals([\structure\StructureLib::getById($this->eStructureNetwork['id'], ['legalEmail'])['legalEmail']], array_column($mails, 'to'), 'l\'alerte part à l\'adresse de contact du réseau');

		[$title, $text, $html] = $mails[0]['content'];

		$this->assertTrue(str_contains($title, s("Lien de la visio à poser")), 'le titre le dit : '.$title);
		$this->assertTrue(str_contains($title, encode($eCourse['name'])), 'avec la formation');
		$this->assertTrue(str_contains($html, DateUi::getDays($eDate)), 'et le jour');
		$this->assertTrue(str_contains($html, s("3 personnes confirmées")), 'les trois confirmées attendent le lien, pas les deux rappelées');
		$this->assertTrue(str_contains($text, s("3 personnes confirmées")), 'aussi dans le texte');
		$this->assertTrue(str_contains($text, 'puis cliquez sur « Envoyer le lien de la visio »'), 'poser le lien ne suffit plus, l\'alerte dit le second geste : '.$text);
		$this->assertFalse(str_contains($text, 'un e-mail le leur envoie'), 'et ne promet plus d\'envoi automatique');
		$this->assertTrue(str_contains($html, 'href="'.\Lime::getUrl().CourseUi::urlEdition($eCourse).'?tab=dates"'), 'le bouton mène à l\'onglet Organisation de l\'éditeur, où se pose le lien');
		$this->assertTrue(str_contains($text, s("Poser le lien de la visio")."\n".\Lime::getUrl().CourseUi::urlEdition($eCourse).'?tab=dates'), 'et porte le libellé de la porte de la date : '.$text);

		[$titlePosed, $textPosed] = DateEnrollmentLib::getLinkMissingMails(DateLib::getById($eDatePosed['id']))[0]['content'];

		$this->assertTrue(str_contains($titlePosed, s("Lien de la visio à envoyer")), 'un lien posé : l\'alerte dit « à envoyer » : '.$titlePosed);
		$this->assertTrue(str_contains($textPosed, 'Le lien est déjà posé sur la date'), 'et ne demande pas de le poser : '.$textPosed);
		$this->assertTrue(str_contains($textPosed, s("Envoyer le lien")."\n".\Lime::getUrl().CourseUi::urlEdition($eCourse).'?tab=dates'), 'le bouton mène à la date : '.$textPosed);

		$this->assertEquals($mail, $this->getLastMail(), 'aucun e-mail écrit');

	}

	/**
	 * L'alerte au réseau chiffre les personnes à qui le lien partira, pas toutes les confirmées : un
	 * compte fermé garde sa place et n'a plus d'adresse. Le nombre qu'elle annonce est celui que
	 * `DateLib::sendLink()` rend ensuite.
	 */
	public function testLinkMissingRecipients(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$mail = $this->getLastMail();

		$eCourse = $this->seed(Course::REMOTE);
		$eDate = $this->setLink($this->createDate($eCourse, 1), self::LINK);

		$this->enrollConfirmed($eDate, $this->createPerson());
		$this->enrollConfirmed($eDate, $this->createPerson());
		$eUserClosed = $this->createPerson();
		$this->enrollConfirmed($eDate, $eUserClosed);
		\user\DropLib::closeNow($eUserClosed);

		$eDatePanel = $this->prepareDate($eDate);
		$this->assertEquals([3, 2], [$eDatePanel['confirmed'], $eDatePanel['linkRecipients']], 'décor : trois confirmées, deux qui ont une adresse');

		[, $text, $html] = DateEnrollmentLib::getLinkMissingMails(DateLib::getById($eDate['id']))[0]['content'];

		$this->assertTrue(str_contains($text, s("2 personnes confirmées l'attendent")), 'l\'alerte ne compte pas le compte fermé : '.$text);
		$this->assertTrue(str_contains($html, s("2 personnes confirmées l'attendent")), 'ni dans le html');
		$this->assertFalse(str_contains($text, s("3 personnes confirmées")), 'les trois confirmées ne sont pas annoncées : '.$text);

		$this->assertEquals(2, DateLib::sendLink($this->prepareDate($eDate)), 'autant d\'e-mails que l\'alerte en annonce');

		$this->assertEquals($mail, $this->getLastMail(), 'aucun e-mail écrit');

	}

	/** Les cartes de la section des dates dans l'éditeur, indexées par l'id de leur date. */
	private function getDecks(Course $eCourse): array {

		$cDate = DateLib::getByCourse($eCourse);
		DateEnrollmentLib::fillCounts($cDate);
		DateEnrollmentLib::fillLinkRecipients($cDate);

		return $this->splitDecks(new DateUi()->getEditionSection($eCourse, $cDate));

	}

	/** Les decks de la section, indexés par l'id de leur date, lu sur la porte ou le lien de la roue. */
	private function splitDecks(string $section): array {

		$decks = [];

		foreach(preg_split('/(?=<div class="util-deck[ "])/', $section) as $deck) {
			if(preg_match('/(?:date:update\?id=|post-id=")(\d+)/', $deck, $match) === 1) {
				$decks[(int)$match[1]] = $deck;
			}
		}

		return $decks;

	}

	/** Le lien effacé par le Lib, comme le panneau posté sans lien. */
	private function withoutLink(Date $eDate): Date {

		$eDate = DateLib::getById($eDate['id']);
		$eDate['link'] = NULL;

		DateLib::update($eDate, ['link']);

		return DateLib::getById($eDate['id']);

	}

	private function setLink(Date $eDate, string $link): Date {

		$eDate = DateLib::getById($eDate['id']);
		$eDate['link'] = $link;

		DateLib::update($eDate, ['link']);

		return DateLib::getById($eDate['id']);

	}

	/**
	 * La confirmation reculée de deux jours, comme `DateEnrollmentRemind.t.php` : le rappel ne suit
	 * pas une confirmation de moins de 24 heures. Écrite hors du Lib, qui n'a pas de raison de dater
	 * une confirmation dans le passé.
	 */
	private function age(DateEnrollment $eDateEnrollment): DateEnrollment {

		DateEnrollment::model()
			->whereId($eDateEnrollment['id'])
			->update(['confirmedAt' => new \Sql('NOW() - INTERVAL 2 DAY')]);

		return $this->getLine($eDateEnrollment['id']);

	}

}
?>
