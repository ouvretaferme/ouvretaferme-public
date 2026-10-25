<?php
namespace learning;

require_once __DIR__.'/LearningNetworkFixture.t.php';

/**
 * Le décor des tests du rappel et du quiz d'une date (lot L10d, `DateQuiz.t.php` et
 * `DateEnrollmentRemind.t.php`) : une formation publiée du réseau, ouverte à ses fermes, avec une
 * leçon à lire et une leçon quiz de deux questions ; des dates posées à un jour donné ; des
 * personnes de la ferme rattachée, confirmées par le chemin du réseau ; des lignes sans compte de
 * la page publique.
 *
 * Tout s'écrit dans la transaction du test, par les Lib, avec des comptes fictifs en `example.org`.
 * Les e-mails ne partent pas : en dev, `SendLib` n'écrit rien pour une adresse hors de
 * `MailSetting::$devSendOnly`. Ils se comptent par ce que rendent `DateLib::openQuiz()` et
 * `DateEnrollmentLib::remind()`, et se lisent par `DateEnrollmentLib::getMails()`.
 */
abstract class LearningDateFixtureTest extends LearningNetworkFixtureTest {

	private int $people = 0;

	/**
	 * L'horloge est figée à midi du jour réel pour toute la méthode : une date « du jour » finit à 23:00
	 * (`moveDate()`), et lancée entre 23:00 et minuit elle serait déjà passée. Le lanceur rend l'heure
	 * réelle après chaque méthode, et un test qui fige sa propre heure la pose après celle-ci.
	 *
	 * La limite de la page publique par poste vit dans Redis, hors de la transaction du test : chaque
	 * ligne sans compte du décor la compte, et le fichier lancé deux fois la dépasserait.
	 */
	public function setUp(): void {

		$this->setClock(\Clock::date().' 12:00:00');

		parent::setUp();

		\user\ThrottleAiLib::clear('learning-enrollment-ip', getIp());

	}

	public function tearDown(): void {

		\user\ThrottleAiLib::clear('learning-enrollment-ip', getIp());

		parent::tearDown();

	}

	protected function createUser(string $email): \user\User {

		$eUser = new \user\User([
			'firstName' => 'zzTest',
			'lastName' => 'Stagiaire',
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
	 * Une personne de la ferme rattachée. L'ouverture aux membres ne lui donne pas la lecture : seule
	 * une date confirmée la lui ouvre.
	 */
	protected function createPerson(): \user\User {

		$this->people++;

		$eUser = $this->createUser('zztest.stagiaire-quiz-'.$this->people.'@example.org');

		$this->join($eUser, $this->eStructureFarm);

		return $eUser;

	}

	/**
	 * La formation du décor : publiée, sur place, ouverte aux membres du réseau, une leçon à lire
	 * puis, avec `$quiz`, une leçon quiz de deux questions au seuil de 50 %. `$first` à faux pour une
	 * seconde formation dans le même test.
	 *
	 * @return array{0: Course, 1: Lesson}
	 */
	protected function seedCourse(bool $quiz = TRUE, bool $first = TRUE): array {

		// Le rattachement de la ferme ne se pose qu'une fois par test : une seconde formation se crée seule.
		$eCourse = $first
			? $this->seed(Course::ONSITE)
			: $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE, type: Course::ONSITE);

		$this->assertEquals([], $this->shareNetwork($eCourse), 'formation ouverte aux membres du réseau');

		$eChapter = $this->createChapter($eCourse, 'zzTest module');
		$this->createLessonWritten($eChapter, 'zzTest leçon', 10);

		if($quiz === FALSE) {
			return [$this->getCourse($eCourse['id']), new Lesson()];
		}

		$eLesson = $this->getLesson($this->createLesson($eChapter, 'zzTest quiz', 5)['id']);

		// Posé sans `build()`, donc sans l'étape `validation.questioned` : le décor pose le quiz avant
		// d'y écrire ses questions, ce que le produit fait dans l'autre ordre.
		$eLesson['validation'] = Lesson::QUIZ;
		$eLesson['quizThreshold'] = 50;
		LessonLib::update($eLesson, ['validation', 'quizThreshold']);

		$eLesson = $this->getLesson($eLesson['id']);

		$this->createQuestion($eLesson);
		$this->createQuestion($eLesson);

		return [$this->getCourse($eCourse['id']), $this->getLesson($eLesson['id'])];

	}

	/**
	 * Une question à une réponse, « Choix juste » en premier.
	 */
	private function createQuestion(Lesson $eLesson): void {

		$eQuestion = new Question(['lesson' => $eLesson, 'type' => Question::SINGLE, 'image' => NULL]);

		$fw = new \FailWatch();

		$eQuestion->build(QuestionLib::getPropertiesCreate(), [
			'statement' => 'Une question ?',
			'image' => '',
			'type' => Question::SINGLE,
			'explanation' => '',
		], new \PropertyList('create'));

		$cChoice = ChoiceLib::buildForQuestion($eQuestion, [
			'label' => ['Choix juste', 'Choix faux'],
			'correct' => ['1', '0'],
			'image' => ['', ''],
			'id' => ['', ''],
		]);

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('question refusée : '.json_encode($fw->get())));

		QuestionLib::create($eQuestion, $cChoice);

	}

	/**
	 * Une date écrite par le chemin de `doCreate`, dans dix jours pour passer la validation, puis
	 * portée au jour `$offset` par `DateLib::update()` : une date du jour ou passée ne se crée pas.
	 * Sa séance unique suit le type de la formation, en présentiel pour une formation mixte
	 * (`Course::getDefaultSequence()`).
	 */
	protected function createDate(Course $eCourse, int $offset, string $time = '09:00:00'): Date {

		$eDate = new Date(['course' => $eCourse]);

		$fw = new \FailWatch();

		$eDate->build(DateLib::getPropertiesCreate(), [
			'sequences' => ['0'],
			'startAt' => ['0' => ['date' => $this->day(10), 'time' => '09:00']],
			'endAt' => ['0' => ['time' => '17:00']],
			'mode' => ['0' => $eCourse->getDefaultSequence()['mode']],
			'place' => ['0' => 'Salle des fêtes, Crest'],
			'link' => 'https://visio.example.org/zztest',
			'seats' => '',
		], new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('date refusée : '.json_encode($fw->get())));

		DateLib::create($eDate);

		return $this->moveDate($eDate, $offset, $time);

	}

	/**
	 * La date portée au jour `$offset` à l'heure `$time`, sur une journée, par `DateLib::update()`.
	 */
	protected function moveDate(Date $eDate, int $offset, string $time = '09:00:00'): Date {

		$eDate = DateLib::getById($eDate['id']);

		return $this->writeSequences($eDate, [
			[$this->day($offset).' '.$time, $this->day($offset).' 23:00:00'],
		]);

	}

	/**
	 * La date prolongée jusqu'au jour `$offset`, son jour et son heure de début gardés : une séance
	 * par jour, jusqu'à 17:00, dont `DateUi::getDays()` rend « Du … au … ».
	 */
	protected function extendDate(Date $eDate, int $offset): Date {

		$eDate = DateLib::getById($eDate['id']);

		$start = substr($eDate['startAt'], 0, 10);
		$time = substr($eDate['startAt'], 11);

		$sequences = [];

		for($day = $start; $day <= $this->day($offset); $day = date('Y-m-d', strtotime($day.' + 1 DAY'))) {
			$sequences[] = [$day.' '.$time, $day.' 17:00:00'];
		}

		return $this->writeSequences($eDate, $sequences);

	}

	/**
	 * La date telle que la liste des inscrits, le panneau d'ouverture du quiz et celui de l'envoi du
	 * lien la préparent.
	 */
	protected function prepareDate(Date $eDate): Date {

		$eDate = DateLib::getById($eDate['id']);

		DateEnrollmentLib::fillCounts(new \Collection([$eDate]));
		DateEnrollmentLib::fillLinkRecipients(new \Collection([$eDate]));
		$eDate['eLessonQuiz'] = LessonLib::getLastQuiz($eDate['course']);

		return $eDate;

	}

	/**
	 * La ligne du compte, par le chemin du panneau du membre, puis confirmée par le réseau.
	 */
	protected function enrollConfirmed(Date $eDate, \user\User $eUser): DateEnrollment {

		$e = $this->enrollRequested($eDate, $eUser);

		DateEnrollmentLib::confirm($e);

		return DateEnrollmentLib::getByDateAndUser($eDate, $eUser);

	}

	/**
	 * La ligne du compte laissée à confirmer, le réglage par défaut du réseau.
	 */
	protected function enrollRequested(Date $eDate, \user\User $eUser): DateEnrollment {

		$e = DateEnrollmentLib::getCreateElement($eDate, $eUser);
		$e['structure'] = new \structure\Structure();
		$e['trainee'] = DateEnrollment::FARMER;
		$e['committedAt'] = \Clock::datetime();

		DateEnrollmentLib::create($e);

		$e = DateEnrollmentLib::getByDateAndUser($eDate, $eUser);

		$this->assertEquals(DateEnrollment::REQUESTED, $e['status'], 'demande à confirmer');

		return $e;

	}

	/**
	 * Une ligne sans compte de la page publique, écrite par le Lib comme `doCreate` la prépare, puis
	 * confirmée par le réseau.
	 */
	protected function enrollPublicConfirmed(Date $eDate, string $email): DateEnrollment {

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

		$e = DateEnrollmentLib::getById($e['id'], DateEnrollment::getSelection());

		if($e['status'] === DateEnrollment::REQUESTED) {
			DateEnrollmentLib::confirm($e);
		}

		return DateEnrollmentLib::getById($e['id'], DateEnrollment::getSelection());

	}

	protected function getLine(int $id): DateEnrollment {
		return DateEnrollmentLib::getById($id, DateEnrollment::getSelection());
	}

}
?>
