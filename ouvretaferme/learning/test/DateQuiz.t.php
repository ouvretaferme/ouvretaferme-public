<?php
namespace learning;

require_once __DIR__.'/LearningDateFixture.t.php';

/**
 * Le quiz d'une date (lot L10d, O1, F1, N1, Q1, S3) : quand le réseau peut l'ouvrir, l'ouverture
 * unique et ses e-mails, le quiz fermé côté serveur avant l'ouverture et à qui n'est pas confirmé,
 * l'état que l'écran en montre concordant avec ce que l'envoi accepte, une personne inscrite à deux
 * dates, et le résultat dans la liste des inscrits et son export.
 *
 * Le décor est celui de `LearningDateFixtureTest`.
 *
 * Lancement : dev/test package=learning file=DateQuiz
 */
class DateQuizTest extends LearningDateFixtureTest {

	/**
	 * Le geste n'est proposé que sur une date planifiée commencée, pas encore ouverte, dont la
	 * formation a une leçon quiz et qui a au moins une personne confirmée.
	 */
	public function testAcceptOpenQuiz(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse();

		$eDate = $this->createDate($eCourse, 2);
		$this->enrollConfirmed($eDate, $this->createPerson());

		$this->assertFalse($this->prepareDate($eDate)->acceptOpenQuiz(), 'une date à venir ne s\'ouvre pas');

		$eDate = $this->moveDate($eDate, 0, '23:00:00');
		$this->assertTrue($this->prepareDate($eDate)->acceptOpenQuiz(), 'dès le premier jour, avant même l\'heure de début');

		$eDate = $this->moveDate($eDate, -5);
		$this->assertTrue($this->prepareDate($eDate)->acceptOpenQuiz(), 'une date finie s\'ouvre encore');

		$eDateEmpty = $this->createDate($eCourse, 0);
		$this->assertFalse($this->prepareDate($eDateEmpty)->acceptOpenQuiz(), 'sans personne confirmée, rien à ouvrir');

		$this->enrollRequested($eDateEmpty, $this->createPerson());
		$this->assertFalse($this->prepareDate($eDateEmpty)->acceptOpenQuiz(), 'une demande à confirmer ne compte pas');

		[$eCourseWithout] = $this->seedCourse(quiz: FALSE, first: FALSE);
		$eDateWithout = $this->createDate($eCourseWithout, 0);
		$this->enrollConfirmed($eDateWithout, $this->createPerson());
		$this->assertFalse($this->prepareDate($eDateWithout)->acceptOpenQuiz(), 'sans leçon quiz, rien à ouvrir');

		$eDateCanceled = $this->createDate($eCourse, 3);
		$this->enrollConfirmed($eDateCanceled, $this->createPerson());
		DateLib::cancel(DateLib::getById($eDateCanceled['id']));
		$eDateCanceled = $this->moveDate($eDateCanceled, -1);
		$this->assertFalse($this->prepareDate($eDateCanceled)->acceptOpenQuiz(), 'une date annulée ne s\'ouvre pas');

		$this->actAs($this->eUserAdmin);
		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/date:doOpenQuiz', ['id' => (string)$eDate['id']]), 'le réseau ouvre');
		$this->assertFalse($this->prepareDate($eDate)->acceptOpenQuiz(), 'une date ouverte ne s\'ouvre plus');

	}

	/**
	 * Une seule ouverture, par le réseau seulement, et un e-mail à chaque personne confirmée au
	 * moment de l'ouverture : ni aux demandes, ni aux attentes, ni aux annulées.
	 */
	public function testOpenOnce(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse();

		$eDate = $this->createDate($eCourse, 0);

		$eUserA = $this->createPerson();
		$this->enrollConfirmed($eDate, $eUserA);
		$this->enrollConfirmed($eDate, $this->createPerson());
		$this->enrollRequested($eDate, $this->createPerson());
		DateEnrollmentLib::cancel($this->enrollConfirmed($eDate, $this->createPerson()), DateEnrollment::USER);

		$this->actAs($eUserA);
		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('learning/date:openQuiz', ['id' => (string)$eDate['id']], 'GET'), 'une personne inscrite n\'ouvre pas le panneau');
		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('learning/date:doOpenQuiz', ['id' => (string)$eDate['id']]), 'ni le geste');

		$this->actAs($this->eUserAdmin);
		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('learning/date:openQuiz', ['id' => (string)$eDate['id']], 'GET'), 'le réseau ouvre le panneau');

		$eDatePanel = $this->prepareDate($eDate);
		$this->assertEquals(2, $eDatePanel['confirmed'], 'le panneau compte les confirmées');
		$this->assertTrue(new DateUi()->openQuiz($eDatePanel)->body !== '', 'le panneau se rend');

		$eDateOpened = $this->prepareDate($eDate);
		$this->assertEquals(2, DateLib::openQuiz($eDateOpened), 'deux e-mails, aux deux confirmées');
		$this->assertTrue(DateLib::getById($eDate['id'])->isQuizOpened(), 'la date est ouverte');

		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/date:doOpenQuiz', ['id' => (string)$eDate['id']]), 'le geste n\'est plus proposé, l\'état le refuse');

		// Le Lib refuse encore la seconde ouverture si deux gestes se croisent, sans e-mail.
		$fw = new \FailWatch();
		$this->assertEquals(0, DateLib::openQuiz($this->prepareDate($eDate)), 'une seconde ouverture n\'envoie rien');
		$this->assertEquals(['Date::quizAlreadyOpened'], array_column($fw->get(), 1), 'et lève son refus');

	}

	/**
	 * Le quiz d'une formation datée est fermé côté serveur : `doAnswer` et `retry` refusent avant
	 * l'ouverture et à qui n'est pas confirmé à une date ouverte, même après. Et l'état que l'écran
	 * montre (`fillQuizState()`) dit ouvert exactement quand `canAnswer()` l'accepte, pour
	 * l'équipe, la confirmée avant et après, le non-inscrit, et sur une formation sans date.
	 */
	public function testClosedServerSide(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eLesson] = $this->seedCourse();

		// Retenue au catalogue public, la formation se lit sans date confirmée : le non-inscrit la
		// lit, et c'est le quiz seul qui le refuse. L'ouverture aux membres du décor ne donne pas la
		// lecture.
		$eCourse['catalog'] = Course::ACCEPTED;
		CourseLib::updateCatalog($eCourse);

		$eDate = $this->createDate($eCourse, 0);

		$eUserConfirmed = $this->createPerson();
		$this->enrollConfirmed($eDate, $eUserConfirmed);
		$this->startCourse($eUserConfirmed, $eCourse);

		$eUserOther = $this->createPerson();
		$this->startCourse($eUserOther, $eCourse);

		$this->assertEquals([\NotAllowedAction::class, NULL], $this->postAnswer($eUserConfirmed, $eLesson), 'confirmée, avant l\'ouverture : refusé');
		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executeAs($eUserConfirmed, 'learning/response:retry', ['lesson' => (string)$eLesson['id']], 'GET'), 'le quiz vierge aussi');

		$this->assertState($this->eUserAdmin, $eLesson, ResponseLib::QUIZ_OPEN, 'l\'équipe qui écrit la formation');
		$this->assertState($eUserConfirmed, $eLesson, ResponseLib::QUIZ_NOT_OPENED, 'la confirmée avant l\'ouverture');
		$this->assertState($eUserOther, $eLesson, ResponseLib::QUIZ_RESERVED, 'le non-inscrit avant l\'ouverture');

		DateLib::openQuiz($this->prepareDate($eDate));

		$this->assertState($eUserConfirmed, $eLesson, ResponseLib::QUIZ_OPEN, 'la confirmée après l\'ouverture');
		$this->assertState($eUserOther, $eLesson, ResponseLib::QUIZ_RESERVED, 'le non-inscrit après l\'ouverture');

		$this->assertEquals([\ViewAction::class, NULL], $this->postAnswer($eUserConfirmed, $eLesson), 'confirmée, après l\'ouverture : accepté');
		$this->assertEquals(Progress::DONE, ProgressLib::getByUserAndLesson($eUserConfirmed, $eLesson)['status'], 'et réussi');
		$this->assertEquals([\NotAllowedAction::class, NULL], $this->postAnswer($eUserOther, $eLesson), 'le non-inscrit reste refusé');

		$eLessonRead = $this->getLesson($eLesson['id']);
		$this->actAs($eUserOther);
		ResponseLib::fillQuiz($eUserOther, $eLessonRead, FALSE);
		$this->assertFalse($eLessonRead->offsetExists('cQuestion'), 'le non-inscrit ne reçoit aucune question');
		$this->actAs($this->eUserAdmin);

		// Une formation sans date ne ferme rien (L12a).
		$eCourseOnline = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE);
		$eLessonOnline = $this->createLesson($this->createChapter($eCourseOnline, 'zzTest module'), 'zzTest quiz', 5);
		$eLessonOnline = $this->getLesson($eLessonOnline['id']);
		$eLessonOnline['validation'] = Lesson::QUIZ;
		$eLessonOnline['quizThreshold'] = 50;
		LessonLib::update($eLessonOnline, ['validation', 'quizThreshold']);

		$this->startCourse($eUserOther, $eCourseOnline);

		$this->assertState($eUserOther, $this->getLesson($eLessonOnline['id']), ResponseLib::QUIZ_OPEN, 'une formation sans date');

	}

	/**
	 * Une personne confirmée à deux dates passe dès que l'une ouvre, et la fiche le dit.
	 */
	public function testTwoDates(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eLesson] = $this->seedCourse();

		$eDateLater = $this->createDate($eCourse, 10);
		$eDateToday = $this->createDate($eCourse, 0);

		$eUser = $this->createPerson();
		$this->enrollConfirmed($eDateLater, $eUser);
		$this->enrollConfirmed($eDateToday, $eUser);
		$this->startCourse($eUser, $eCourse);

		$this->assertState($eUser, $eLesson, ResponseLib::QUIZ_NOT_OPENED, 'aucune date ouverte');

		$this->actAs($eUser);
		$eLessonRead = $this->getLesson($eLesson['id']);
		ResponseLib::fillQuizState($eUser, $eLessonRead);
		$this->assertEquals($eDateToday['id'], $eLessonRead['eDateConfirmed']['id'], 'la carte annonce la prochaine date, pas la plus lointaine');
		$this->actAs($this->eUserAdmin);

		DateLib::openQuiz($this->prepareDate($eDateToday));

		$this->assertState($eUser, $eLesson, ResponseLib::QUIZ_OPEN, 'une date ouverte suffit');
		$this->assertEquals([\ViewAction::class, NULL], $this->postAnswer($eUser, $eLesson), 'la réponse est acceptée');

	}

	/**
	 * La carte du quiz fermé annonce le premier jour de la date confirmée tant qu'il n'est pas atteint ;
	 * dès qu'il l'est, le premier jour, les suivants ou la date finie, elle dit que le réseau ne l'a
	 * pas encore ouvert.
	 */
	public function testClosedCard(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eLesson] = $this->seedCourse();

		$eDate = $this->createDate($eCourse, 10);

		$eUser = $this->createPerson();
		$this->enrollConfirmed($eDate, $eUser);
		$this->startCourse($eUser, $eCourse);

		$card = function() use($eUser, $eLesson): string {

			$this->actAs($eUser);
			$eLessonRead = $this->getLesson($eLesson['id']);
			ResponseLib::fillQuizState($eUser, $eLessonRead);
			$this->actAs($this->eUserAdmin);

			$this->assertEquals(ResponseLib::QUIZ_NOT_OPENED, $eLessonRead['quiz'], 'le quiz reste fermé');

			return new \ReflectionMethod(QuizUi::class, 'getClosed')->invoke(new QuizUi(), $eLessonRead);

		};

		$cases = [
			'la veille' => fn() => $this->moveDate($eDate, 1),
			'le premier jour' => fn() => $this->extendDate($this->moveDate($eDate, 0), 1),
			'le second jour d\'une date de deux jours' => fn() => $this->extendDate($this->moveDate($eDate, -1), 0),
			'une date passée' => fn() => $this->moveDate($eDate, -2),
		];

		foreach($cases as $case => $move) {

			$eDateMoved = $move();
			$closed = $card();

			$this->assertEquals($case === 'la veille', str_contains($closed, \util\DateUi::textual($eDateMoved['startAt'], \util\DateUi::DAY_MONTH)), $case.' : le premier jour annoncé seulement avant d\'être atteint : '.$closed);

		}

	}

	/**
	 * Inscrite sans être confirmée, en demande comme en liste d'attente : la carte réserve le quiz
	 * aux inscriptions confirmées, avant l'ouverture comme après, et non aux personnes inscrites.
	 * Une ligne annulée ne compte pas comme une inscription.
	 */
	public function testUnconfirmed(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eLesson] = $this->seedCourse();

		$eDate = $this->createDate($eCourse, 0);

		$eUserRequested = $this->createPerson();
		$this->enrollRequested($eDate, $eUserRequested);

		$eUserConfirmed = $this->createPerson();
		$this->enrollConfirmed($eDate, $eUserConfirmed);

		$eUserCanceled = $this->createPerson();
		DateEnrollmentLib::cancel($this->enrollRequested($eDate, $eUserCanceled), DateEnrollment::USER);

		$eDateFull = DateLib::getById($eDate['id']);
		$eDateFull['seats'] = 1;
		DateLib::update($eDateFull, ['seats']);

		$eUserWaiting = $this->createPerson();
		$eDateEnrollmentWaiting = DateEnrollmentLib::getCreateElement(DateLib::getById($eDate['id']), $eUserWaiting);
		$eDateEnrollmentWaiting['structure'] = new \structure\Structure();
		$eDateEnrollmentWaiting['trainee'] = DateEnrollment::FARMER;
		$eDateEnrollmentWaiting['committedAt'] = \Clock::datetime();
		DateEnrollmentLib::create($eDateEnrollmentWaiting);

		$this->assertEquals(DateEnrollment::WAITING, DateEnrollmentLib::getByDateAndUser($eDate, $eUserWaiting)['status'], 'la date est pleine : liste d\'attente');

		$card = function(\user\User $eUser) use($eLesson): string {

			$this->actAs($eUser);
			$eLessonRead = $this->getLesson($eLesson['id']);
			ResponseLib::fillQuizState($eUser, $eLessonRead);
			$this->actAs($this->eUserAdmin);

			return new \ReflectionMethod(QuizUi::class, 'getClosed')->invoke(new QuizUi(), $eLessonRead);

		};

		foreach(['avant l\'ouverture' => FALSE, 'après l\'ouverture' => TRUE] as $moment => $open) {

			if($open) {
				DateLib::openQuiz($this->prepareDate($eDate));
				$this->assertState($eUserConfirmed, $eLesson, ResponseLib::QUIZ_OPEN, 'la confirmée '.$moment);
			}

			foreach(['en demande' => $eUserRequested, 'en liste d\'attente' => $eUserWaiting] as $case => $eUser) {

				$this->assertState($eUser, $eLesson, ResponseLib::QUIZ_UNCONFIRMED, $case.' '.$moment);

				$closed = $card($eUser);
				$this->assertFalse(str_contains($closed, 'href='), $case.' '.$moment.' : la carte, sans le lien vers les dates du non-inscrit : '.$closed);

			}

			$this->assertState($eUserCanceled, $eLesson, ResponseLib::QUIZ_RESERVED, 'annulée '.$moment);

		}

	}

	/**
	 * L'état du quiz que le programme de la fiche lit : « pas encore confirmée » en demande comme en
	 * liste d'attente ; une confirmée à une autre date de la formation attend l'ouverture, sa demande
	 * ne comptant pas. Annulée ou non inscrite, la personne n'a pas de date.
	 */
	public function testProgram(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse();

		$eDate = $this->createDate($eCourse, 5);
		$eDateOther = $this->createDate($eCourse, 8);

		$eUserRequested = $this->createPerson();
		$this->enrollRequested($eDate, $eUserRequested);

		$eUserCanceled = $this->createPerson();
		DateEnrollmentLib::cancel($this->enrollRequested($eDate, $eUserCanceled), DateEnrollment::USER);

		$eUserConfirmedAndRequested = $this->createPerson();
		$this->enrollConfirmed($eDate, $eUserConfirmedAndRequested);
		$this->enrollRequested($eDateOther, $eUserConfirmedAndRequested);

		$eDateFull = DateLib::getById($eDateOther['id']);
		$eDateFull['seats'] = 1;
		DateLib::update($eDateFull, ['seats']);

		$eUserWaiting = $this->createPerson();
		$eDateEnrollmentWaiting = DateEnrollmentLib::getCreateElement(DateLib::getById($eDateOther['id']), $eUserWaiting);
		$eDateEnrollmentWaiting['structure'] = new \structure\Structure();
		$eDateEnrollmentWaiting['trainee'] = DateEnrollment::FARMER;
		$eDateEnrollmentWaiting['committedAt'] = \Clock::datetime();
		DateEnrollmentLib::create($eDateEnrollmentWaiting);

		$this->assertEquals(DateEnrollment::WAITING, DateEnrollmentLib::getByDateAndUser($eDateOther, $eUserWaiting)['status'], 'la date est pleine : liste d\'attente');

		$quiz = function(\user\User $eUser) use($eCourse): string {

			$this->actAs($eUser);

			$eCourseRead = $this->getCourse($eCourse['id']);
			$cChapter = ChapterLib::getByCourse($eCourseRead);
			ResponseLib::fillCourseQuiz($eUser, $eCourseRead, $cChapter);

			$this->actAs($this->eUserAdmin);

			return $eCourseRead['quiz'];

		};

		$cases = [
			'en demande' => [$eUserRequested, ResponseLib::QUIZ_UNCONFIRMED],
			'en liste d\'attente' => [$eUserWaiting, ResponseLib::QUIZ_UNCONFIRMED],
			'confirmée à une date et en demande à une autre' => [$eUserConfirmedAndRequested, ResponseLib::QUIZ_NOT_OPENED],
			'annulée' => [$eUserCanceled, ResponseLib::QUIZ_RESERVED],
			'non inscrite' => [$this->createPerson(), ResponseLib::QUIZ_RESERVED],
		];

		foreach($cases as $case => [$eUser, $expected]) {
			$this->assertEquals($expected, $quiz($eUser), $case);
		}

	}

	/**
	 * La liste des inscrits perd le geste dès l'ouverture, et sa colonne Quiz porte un tiret pour une
	 * ligne qui ne peut pas passer le quiz, non confirmée, même annulée après une réussite, ou sans
	 * compte ; rien pour une confirmée qui ne l'a pas encore passé, et une ligne sans compte rattachée
	 * à un compte le redevient ; le compteur « réussites sur » compte les confirmées du moment.
	 * L'export porte la même colonne.
	 */
	public function testList(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eLesson] = $this->seedCourse();

		$eDate = $this->createDate($eCourse, 0);

		[$eUserA, $eUserB, $eUserC] = [$this->createPerson(), $this->createPerson(), $this->createPerson()];
		$this->enrollConfirmed($eDate, $eUserA);
		$this->enrollConfirmed($eDate, $eUserB);
		$eDateEnrollmentC = $this->enrollConfirmed($eDate, $eUserC);
		$eDateEnrollmentPublic = $this->enrollPublicConfirmed($eDate, 'zztest.quiz-public@example.org');

		$ui = new DateEnrollmentUi();

		$eDateList = $this->prepareDate($eDate);
		$cDateEnrollment = DateEnrollmentLib::getByDate($eDate);
		DateEnrollmentLib::fillLosesLessons($eCourse, $cDateEnrollment);
		$list = $ui->getList($eDateList, $cDateEnrollment, new \Collection(), '');
		$this->assertTrue(str_contains($list, 'date:openQuiz?id='.$eDate['id']), 'avant l\'ouverture, le geste');
		$this->assertEquals(7, count($ui->getExport($eDateList, DateEnrollmentLib::getByDate($eDate))[0]), 'l\'export garde ses sept colonnes');

		DateLib::openQuiz($this->prepareDate($eDate));

		foreach([$eUserA, $eUserC] as $eUser) {
			$this->startCourse($eUser, $eCourse);
			$this->assertEquals([\ViewAction::class, NULL], $this->postAnswer($eUser, $eLesson), 'réussi');
		}

		DateEnrollmentLib::cancel($this->getLine($eDateEnrollmentC['id']), DateEnrollment::COMMUNITY);

		$eDateList = $this->prepareDate($eDate);
		$cDateEnrollment = DateEnrollmentLib::getByDate($eDate);
		ProgressLib::fillDateQuiz($eDateList, $cDateEnrollment);

		$this->assertEquals(1, $eDateList['quizPassed'], 'une réussite parmi les confirmées du moment, la réussite annulée ne compte plus');

		DateEnrollmentLib::fillLosesLessons($eCourse, $cDateEnrollment);
		$list = $ui->getList($eDateList, $cDateEnrollment, new \Collection(), '');
		$this->assertFalse(str_contains($list, 'date:openQuiz'), 'le geste est parti');
		$this->assertEquals([\Clock::date(), 3], [substr($eDateList['quizOpenedAt'], 0, 10), $eDateList['confirmed']], 'le sous-titre : ouvert ce jour, sur trois confirmées');

		$this->assertEquals('-', $this->getQuizCell($ui, $this->find($cDateEnrollment, $eDateEnrollmentC['id'])), 'annulée après réussite : le tiret');
		$this->assertEquals('-', $this->getQuizCell($ui, $this->find($cDateEnrollment, $eDateEnrollmentPublic['id'])), 'la ligne sans compte, qui ne peut pas passer le quiz : le tiret');
		$this->assertTrue($this->getQuizCell($ui, $this->find($cDateEnrollment, DateEnrollmentLib::getByDateAndUser($eDate, $eUserB)['id'])) !== '-', 'la confirmée qui n\'a pas encore passé le quiz : pas de tiret');
		$this->assertEquals(ProgressLib::TRACK_PASSED, $this->find($cDateEnrollment, DateEnrollmentLib::getByDateAndUser($eDate, $eUserA)['id'])['quizTrack']['state'], 'A a réussi');
		$this->assertEquals(ProgressLib::TRACK_NONE, $this->find($cDateEnrollment, DateEnrollmentLib::getByDateAndUser($eDate, $eUserB)['id'])['quizTrack']['state'], 'B n\'a pas encore passé le quiz');

		$lines = $ui->getExport($eDateList, $cDateEnrollment);
		$this->assertEquals(s("Quiz"), end($lines[0]), 'l\'export prend la colonne Quiz');

		$byEmail = [];
		foreach(array_slice($lines, 1) as $line) {
			$byEmail[$line[2]] = end($line);
		}

		$this->assertEquals('-', $byEmail[$eUserC['email']], 'annulée : le tiret dans l\'export');
		$this->assertEquals('-', $byEmail['zztest.quiz-public@example.org'], 'sans compte : le tiret dans l\'export');
		$this->assertEquals('', $byEmail[$eUserB['email']], 'confirmée, pas encore passé : vide dans l\'export');
		$this->assertTrue(!in_array($byEmail[$eUserA['email']], ['', '-'], TRUE), 'réussie : le résultat');

		// Rattachée à un compte à sa première connexion, la ligne redevient une confirmée ordinaire.
		$eUserAttached = $this->createUser('zztest.quiz-public@example.org');
		DateEnrollmentLib::attachByUser($eUserAttached);
		$this->join($eUserAttached, $this->eStructureFarm);

		$attached = function() use($eDate, $eDateEnrollmentPublic, $ui): array {

			$eDateList = $this->prepareDate($eDate);
			$cDateEnrollment = DateEnrollmentLib::getByDate($eDate);
			ProgressLib::fillDateQuiz($eDateList, $cDateEnrollment);

			$eDateEnrollment = $this->find($cDateEnrollment, $eDateEnrollmentPublic['id']);

			$export = [];
			foreach(array_slice($ui->getExport($eDateList, $cDateEnrollment), 1) as $line) {
				$export[$line[2]] = end($line);
			}

			return [$eDateEnrollment, $this->getQuizCell($ui, $eDateEnrollment), $export['zztest.quiz-public@example.org']];

		};

		[$eDateEnrollmentAttached, $cell, $export] = $attached();
		$this->assertEquals($eUserAttached['id'], $eDateEnrollmentAttached['user']['id'], 'la ligne porte le compte');
		$this->assertTrue($cell !== '-', 'rattachée : plus de tiret');
		$this->assertEquals(ProgressLib::TRACK_NONE, $eDateEnrollmentAttached['quizTrack']['state'], 'rattachée : pas encore passé');
		$this->assertEquals('', $export, 'rattachée : vide dans l\'export');

		$this->startCourse($eUserAttached, $eCourse);
		$this->assertEquals([\ViewAction::class, NULL], $this->postAnswer($eUserAttached, $eLesson), 'rattachée : elle passe le quiz');

		[$eDateEnrollmentAttached, $cell, $export] = $attached();
		$this->assertEquals(ProgressLib::TRACK_PASSED, $eDateEnrollmentAttached['quizTrack']['state'], 'rattachée : réussi');
		$this->assertTrue(str_starts_with($export, 'Réussie'), 'rattachée : le score dans l\'export');

		$this->assertEquals([\CsvAction::class, NULL], $this->executePage('learning/dateEnrollment:export', ['id' => (string)$eDate['id']], 'GET'), 'l\'export se rend');

	}

	/**
	 * L'e-mail d'ouverture et ses trois variantes : un compte va au quiz, une ligne sans compte dont
	 * l'adresse en a un y va aussi, une ligne sans compte à son adresse est invitée à en créer un.
	 */
	public function testMails(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eLesson] = $this->seedCourse();

		$eDate = $this->createDate($eCourse, 0);

		$eUser = $this->createPerson();
		$this->enrollConfirmed($eDate, $eUser);

		$this->createUser('zztest.quiz-compte@example.org');
		$eDateEnrollmentAccount = $this->enrollPublicConfirmed($eDate, 'zztest.quiz-compte@example.org');
		$eDateEnrollmentPublic = $this->enrollPublicConfirmed($eDate, 'zztest.quiz-sans-compte@example.org');

		$eDateOpened = $this->prepareDate($eDate);
		$this->assertEquals(3, DateLib::openQuiz($eDateOpened), 'trois e-mails');

		$quizUrl = \Lime::getUrl().QuizUi::url($eLesson);

		$mail = function(DateEnrollment $e) use(&$eDateOpened): string {
			$e['date'] = $eDateOpened;
			[$title, , $html] = DateEnrollmentLib::getMails($e, 'quizOpened')[0]['content'];
			return $title.$html;
		};

		$member = $mail(DateEnrollmentLib::getByDateAndUser($eDate, $eUser));
		$this->assertTrue(str_contains($member, 'Le quiz de la formation « '.$eCourse['name'].' » est ouvert'), 'le titre');
		$this->assertTrue(str_contains($member, ' du '.mb_strtolower(DateUi::getDays($eDate)).'.'), '« du » jour, et non « que vous avez suivie »');
		$this->assertTrue(str_contains($member, '2 questions') and str_contains($member, '1 bonne réponse'), 'le quiz et ce qu\'il faut pour valider');
		$this->assertTrue(str_contains($member, $quizUrl), 'le membre va au quiz');

		$account = $mail($this->getLine($eDateEnrollmentAccount['id']));
		$this->assertTrue(str_contains($account, $quizUrl), 'un compte à l\'adresse : au quiz');
		$this->assertTrue(str_contains($account, 'Connectez-vous avec l\'adresse zztest.quiz-compte@example.org'), 'en se connectant avec l\'adresse');

		$public = $mail($this->getLine($eDateEnrollmentPublic['id']));
		$this->assertFalse(str_contains($public, $quizUrl), 'sans compte : pas le quiz');
		$this->assertTrue(str_contains($public, '/inscription') and str_contains($public, 'Créer mon compte'), 'mais « Créer mon compte »');
		$this->assertTrue(str_contains($public, 'Le quiz se passe avec un compte Ouvretaferme.'), 'et la raison');

		// Sur plusieurs jours, `DateUi::getDays()` porte déjà son « du ».
		$eLessonQuiz = $eDateOpened['eLessonQuiz'];
		$eDateOpened = $this->prepareDate($this->extendDate($eDate, 2));
		$eDateOpened['eLessonQuiz'] = $eLessonQuiz;
		$days = mb_strtolower(DateUi::getDays($eDateOpened));

		$this->assertTrue(str_starts_with($days, 'du '), 'une date de plusieurs jours : '.$days);

		$member = $mail(DateEnrollmentLib::getByDateAndUser($eDate, $eUser));
		$this->assertTrue(str_contains($member, '» '.$days.'.'), 'plusieurs jours : « '.$days.' »');
		$this->assertFalse(str_contains($member, 'du du'), 'plusieurs jours : un seul « du »');

	}

	/**
	 * La confirmée qui a commencé la formation : `doAnswer` demande l'inscription à la formation.
	 */
	private function startCourse(\user\User $eUser, Course $eCourse): void {

		$eEnrollment = new Enrollment(['user' => $eUser, 'course' => $eCourse, 'structure' => new \structure\Structure()]);

		EnrollmentLib::create($eEnrollment);

	}

	/**
	 * Le chemin de `doAnswer`, toutes les réponses justes.
	 */
	private function postAnswer(\user\User $eUser, Lesson $eLesson): array {

		$answer = [];

		foreach(QuestionLib::getByLesson($eLesson) as $eQuestion) {
			foreach($eQuestion['cChoice'] as $eChoice) {
				if($eChoice['label'] === 'Choix juste') {
					$answer[$eQuestion['id']] = [(string)$eChoice['id']];
				}
			}
		}

		return $this->executeAs($eUser, 'learning/response:doAnswer', ['lesson' => (string)$eLesson['id'], 'answer' => $answer]);

	}

	private function executeAs(\user\User $eUser, string $page, array $input, string $method = 'POST'): array {

		$this->actAs($eUser);
		$result = $this->executePage($page, $input, $method);
		$this->actAs($this->eUserAdmin);

		return $result;

	}

	/**
	 * L'état que l'écran montre, et sa concordance avec le droit que l'envoi valide.
	 */
	private function assertState(\user\User $eUser, Lesson $eLesson, string $expected, string $case): void {

		$this->actAs($eUser);

		$eLessonRead = $this->getLesson($eLesson['id']);
		ResponseLib::fillQuizState($eUser, $eLessonRead);

		$this->assertEquals($expected, $eLessonRead['quiz'], $case.' : l\'état');
		$this->assertEquals($eLessonRead['quiz'] === ResponseLib::QUIZ_OPEN, $this->getLesson($eLesson['id'])->canAnswer(), $case.' : l\'écran et l\'envoi concordent');

		$this->actAs($this->eUserAdmin);

	}

	private function find(\Collection $cDateEnrollment, int $id): DateEnrollment {
		return $cDateEnrollment->find(fn(DateEnrollment $e) => $e['id'] === $id)->first();
	}

	private function getQuizCell(DateEnrollmentUi $ui, DateEnrollment $eDateEnrollment): string {
		return new \ReflectionMethod(DateEnrollmentUi::class, 'getQuiz')->invoke($ui, $eDateEnrollment);
	}

}
?>
