<?php
namespace learning;

require_once __DIR__.'/LearningNetworkFixture.t.php';

/**
 * Le Bilan d'une formation en ligne (`UsageLib::get()`), que l'onglet Bilan de l'éditeur et la route
 * `GET course/{id}/usage` lisent tous deux : les bornes de chaque période, les pas vides, chaque
 * compte à sa frontière, les leçons sur la période et la formation à dates, qui n'en a pas.
 *
 * L'horloge est figée (`setClock()`), ce qui fige aussi `NOW()` dans les écritures des Lib : chaque
 * geste du décor est daté en reculant l'horloge avant de le faire.
 *
 * Lancement : dev/test package=learning file=Usage
 */
class UsageTest extends LearningNetworkFixtureTest {

	const TODAY = '2026-10-23 12:00:00';

	public function testRange(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->setClock(self::TODAY);

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), Course::PUBLISHED, Course::PRIVATE);

		$this->assertEquals(['period' => '30d', 'from' => '2026-09-21', 'to' => '2026-10-23', 'step' => UsageLib::WEEK], UsageLib::getRange($eCourse, '30d', UsageLib::WEEK), '30 jours, aujourd\'hui compris, depuis le lundi qui précède le jeudi 24 septembre');
		$this->assertEquals(['period' => '90d', 'from' => '2026-07-20', 'to' => '2026-10-23', 'step' => UsageLib::MONTH], UsageLib::getRange($eCourse, '90d', UsageLib::MONTH), '90 jours depuis le lundi qui précède le dimanche 26 juillet, au pas mois aussi');
		$this->assertEquals(['period' => '12m', 'from' => '2025-11-03', 'to' => '2026-10-23', 'step' => UsageLib::WEEK], UsageLib::getRange($eCourse, '12m', UsageLib::WEEK), '12 mois, le dernier en cours, depuis le lundi qui suit le samedi 1er novembre');
		$this->assertEquals('2026-10-19', UsageLib::getRange($eCourse, 'all', UsageLib::MONTH)['from'], 'sans lecteur, depuis le lundi de la semaine de la publication');

		$this->assertEquals([[UsageLib::WEEK], UsageLib::STEPS], [UsageLib::getSteps('30d'), UsageLib::getSteps('90d')], 'un mois de pas sur 30 jours ne ferait qu\'une ou deux barres');

		try {
			UsageLib::getRange($eCourse, '30d', UsageLib::MONTH);
			$this->assertTrue(FALSE, 'le pas mensuel sur 30 jours est refusé');
		} catch(\Exception) {
			$this->assertTrue(TRUE, 'le pas mensuel sur 30 jours est refusé');
		}

		$this->setClock('2026-03-14 09:00:00');
		$this->enroll($eCourse, $this->createReader('zztest.bilan-mars'));
		$this->setClock(self::TODAY);

		$this->assertEquals('2026-03-09', UsageLib::getRange($eCourse, 'all', UsageLib::MONTH)['from'], 'depuis le lundi de la semaine de la première inscription, un samedi');

		// Le 24 septembre 2026 est un jeudi : la période recule au lundi 21.
		$weekThirty = UsageLib::getSeries(UsageLib::getRange($eCourse, '30d', UsageLib::WEEK), []);
		$this->assertEquals([5, '2026-09-21', '2026-10-19'], [count($weekThirty), $weekThirty[0]['date'], $weekThirty[4]['date']], 'cinq semaines sur 30 jours, semaines vides comprises');

		// Le 26 juillet 2026 est un dimanche : la période recule au lundi 20.
		$week = UsageLib::getSeries(UsageLib::getRange($eCourse, '90d', UsageLib::WEEK), ['enrolled' => ['2026-08-03' => 2]]);
		$this->assertEquals([14, '2026-07-20', '2026-10-19'], [count($week), $week[0]['date'], $week[13]['date']], 'un pas par semaine ISO, chacune entière');
		$this->assertEquals([0, 2], [$week[0]['enrolled'], $week[2]['enrolled']], 'un pas vide vaut 0');

		$this->assertEquals(4, count(UsageLib::getSeries(UsageLib::getRange($eCourse, '90d', UsageLib::MONTH), [])), 'juillet entamé, août, septembre, octobre en cours');
		$this->assertEquals(12, count(UsageLib::getSeries(UsageLib::getRange($eCourse, '12m', UsageLib::MONTH), [])), 'un pas par mois, novembre amputé de ses deux premiers jours');

		$this->setClock('2026-11-10 12:00:00');
		$this->assertEquals('2025-12-01', UsageLib::getRange($eCourse, '12m', UsageLib::MONTH)['from'], 'un premier du mois qui est un lundi reste le début');
		$this->setClock(self::TODAY);

		$this->assertEquals(['2026-10-19', '2026-10-01'], [UsageLib::getStepKey('2026-10-25 23:00:00', UsageLib::WEEK), UsageLib::getStepKey('2026-10-25', UsageLib::MONTH)], 'le jumeau PHP de getStepSql()');

	}

	/**
	 * Trois lecteurs sur une formation de trois leçons, la troisième validée par quiz. A a tout suivi
	 * en août, B a commencé le premier jour de la période, C a fini aujourd'hui. Chaque compte est
	 * posé de part et d'autre de sa frontière : le jour qui ouvre la période compte, la veille non.
	 */
	public function testGet(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->setClock(self::TODAY);

		$this->actAs($this->eUserAdmin);

		[$eCourse, $lessons] = $this->createDecor();
		[$eLessonFirst, $eLessonSecond, $eLessonQuiz] = $lessons;

		$eUserA = $this->createReader('zztest.bilan-a');
		$eUserB = $this->createReader('zztest.bilan-b');
		$eUserC = $this->createReader('zztest.bilan-c');

		$this->setClock('2026-08-01 10:00:00');
		$eEnrollmentA = $this->enroll($eCourse, $eUserA);
		ProgressLib::done($eEnrollmentA, $eLessonFirst);
		ProgressLib::done($eEnrollmentA, $eLessonSecond);
		$this->passQuiz($eEnrollmentA, $eLessonQuiz, 1);

		$this->setClock('2026-09-20 23:59:59');
		$this->createReview($eCourse, $eUserC, 4, Review::PUBLISHED);

		$this->setClock('2026-09-21 00:00:00');
		$eEnrollmentB = $this->enroll($eCourse, $eUserB);
		ProgressLib::done($eEnrollmentB, $eLessonFirst);

		$this->setClock('2026-10-01 10:00:00');
		$this->createReview($eCourse, $eUserA, 5, Review::PUBLISHED);
		$this->createReading($eCourse, $eUserB, $eLessonFirst, 120);

		$this->setClock('2026-10-02 10:00:00');
		$this->createReview($eCourse, $eUserB, 2, Review::HIDDEN);
		$this->createReading($eCourse, $eUserB, $eLessonFirst, 60);

		$this->setClock('2026-10-10 10:00:00');
		$this->actAs($eUserA);
		$eCommentAnswered = $this->createComment($eLessonFirst, new Comment());

		$this->setClock('2026-10-11 10:00:00');
		$this->actAs($this->eUserAdmin);
		$this->createComment($eLessonFirst, $eCommentAnswered);

		// Un message de l'équipe à tout le fil n'est ni un commentaire de lecteur ni une attente.
		$this->createComment($eLessonFirst, new Comment());

		$this->setClock('2026-10-12 10:00:00');
		$this->actAs($eUserB);
		$eCommentWaiting = $this->createComment($eLessonSecond, new Comment());

		// La réponse d'un lecteur n'est pas celle de l'équipe : le commentaire attend toujours.
		$this->actAs($eUserC);
		$this->createComment($eLessonSecond, $eCommentWaiting);
		$this->actAs($this->eUserAdmin);

		$this->setClock('2026-10-15 10:00:00');
		$this->passQuiz($eEnrollmentB, $eLessonQuiz, 1);

		$this->setClock('2026-10-17 10:00:00');
		$eEnrollmentC = $this->enroll($eCourse, $eUserC);
		$this->passQuiz($eEnrollmentC, $eLessonQuiz, 2);

		$this->setClock(self::TODAY);
		ProgressLib::done($eEnrollmentC, $eLessonFirst);
		ProgressLib::done($eEnrollmentC, $eLessonSecond);
		$this->createReading($eCourse, $eUserC, $eLessonFirst, 300);
		$this->createReading($eCourse, $eUserC, $eLessonSecond, 0);

		$this->setClock('2026-08-01 10:00:00');
		$this->createReading($eCourse, $eUserA, $eLessonFirst, 600);
		$this->setClock(self::TODAY);

		$eCourse['readers'] = 3;

		$usage = UsageLib::get($eCourse, ChapterLib::getByCourse($eCourse), '30d', UsageLib::WEEK);

		$this->assertEquals([3, 2, 67], [$usage['readers'], $usage['readersCompleted'], $usage['completionRate']], 'à ce jour, A et C ont tout suivi');
		$this->assertEquals(2, $usage['enrolled'], 'B le premier jour de la période et C, pas A');
		$this->assertEquals(2, $usage['readersActive'], 'B et C ont suivi une leçon sur la période, pas A');
		$this->assertEquals(1, $usage['completed'], 'C a fini sur la période, A en août');
		$this->assertEquals(['count' => 1, 'rating' => 5.0, 'distribution' => [5 => 1, 4 => 0, 3 => 0, 2 => 0, 1 => 0]], $usage['reviews'], 'l\'avis de la veille de la période et l\'avis masqué écartés');
		$this->assertEquals(['count' => 2, 'unanswered' => 1], $usage['comments'], 'le premier niveau des lecteurs seul ; le premier a la réponse de l\'équipe, le second celle d\'un lecteur, le message de l\'équipe écarté');

		$this->assertEquals(5, count($usage['series']), 'un pas par semaine');
		$this->assertEquals(
			['enrolled' => 1, 'readersActive' => 1, 'completed' => 0, 'reviews' => 0, 'comments' => 0],
			array_diff_key($usage['series'][0], ['date' => TRUE]),
			'la semaine qui ouvre la période, sans l\'avis de la veille'
		);
		$this->assertEquals(
			['enrolled' => 0, 'readersActive' => 1, 'completed' => 1, 'reviews' => 0, 'comments' => 0],
			array_diff_key($usage['series'][4], ['date' => TRUE]),
			'la semaine en cours'
		);

		$this->assertEquals([$eLessonFirst['id'], $eLessonSecond['id'], $eLessonQuiz['id']], array_keys($usage['lessons']), 'dans l\'ordre du programme');

		$this->assertEquals([
			'lesson' => $eLessonFirst['id'],
			'readersDone' => 2,
			'medianSeconds' => 240,
			'comments' => 1,
			'quizFirstAttemptRate' => NULL
		], $usage['lessons'][$eLessonFirst['id']], 'B en 180 secondes sur deux séances, C en 300, la séance de A en août écartée');

		$this->assertEquals([1, NULL, 1], [
			$usage['lessons'][$eLessonSecond['id']]['readersDone'],
			$usage['lessons'][$eLessonSecond['id']]['medianSeconds'],
			$usage['lessons'][$eLessonSecond['id']]['comments']
		], 'une séance de zéro seconde ne donne pas de temps médian');

		$this->assertEquals([2, 50], [
			$usage['lessons'][$eLessonQuiz['id']]['readersDone'],
			$usage['lessons'][$eLessonQuiz['id']]['quizFirstAttemptRate']
		], 'B réussit au premier essai, C au second, A hors de la période');

		$year = UsageLib::get($eCourse, ChapterLib::getByCourse($eCourse), '12m', UsageLib::MONTH);

		$this->assertEquals([3, 3, 2], [$year['enrolled'], $year['readersActive'], $year['completed']], 'sur 12 mois, A compte');
		$this->assertEquals([2, 4.5], [$year['reviews']['count'], $year['reviews']['rating']], 'sur 12 mois, l\'avis de la veille compte, le masqué jamais');
		$this->assertEquals(67, $year['lessons'][$eLessonQuiz['id']]['quizFirstAttemptRate'], 'A et B au premier essai, C au second');

	}

	/**
	 * La page prépare le Bilan sur la période, le pas et l'indicateur demandés, revient à leur défaut
	 * sur une valeur inconnue ou un pas que la période n'admet pas, et le refuse à une formation à
	 * dates, qui n'en a pas.
	 */
	public function testPage(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->setClock(self::TODAY);

		$this->actAs($this->eUserAdmin);

		[$eCourse] = $this->createDecor();

		$data = $this->executeView('formation/'.$eCourse['id'].'/bilan', []);

		$this->assertTrue($data instanceof \stdClass, 'la page se rend ('.(is_string($data) ? $data : '').')');
		$this->assertEquals(['90d', UsageLib::WEEK, 'readersActive', 0], [$data->usage['period'], $data->usage['step'], $data->indicator, $data->usage['readers']], 'par défaut, 90 jours par semaine, les lecteurs actifs');

		$data = $this->executeView('formation/'.$eCourse['id'].'/bilan', ['period' => '12m', 'step' => UsageLib::MONTH, 'indicator' => 'reviews']);
		$this->assertEquals(['12m', UsageLib::MONTH, 'reviews'], [$data->usage['period'], $data->usage['step'], $data->indicator], 'les choix demandés');

		$data = $this->executeView('formation/'.$eCourse['id'].'/bilan', ['period' => '7d', 'step' => 'day', 'indicator' => 'readers']);
		$this->assertEquals(['90d', UsageLib::WEEK, 'readersActive'], [$data->usage['period'], $data->usage['step'], $data->indicator], 'des valeurs inconnues reviennent au défaut');

		$data = $this->executeView('formation/'.$eCourse['id'].'/bilan', ['period' => '30d', 'step' => UsageLib::MONTH]);
		$this->assertEquals(['30d', UsageLib::WEEK], [$data->usage['period'], $data->usage['step']], 'le pas mensuel sur 30 jours revient à la semaine');

		$eCourseDated = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE, type: Course::REMOTE);

		$this->assertFalse($eCourseDated->acceptUsage(), 'une formation à dates n\'a pas de bilan');
		$this->assertTrue(is_string($this->executeView('formation/'.$eCourseDated['id'].'/bilan', [])), 'et sa page se refuse');

	}

	/**
	 * Une formation publiée de trois leçons en deux modules, la troisième validée par un quiz d'une
	 * question.
	 */
	private function createDecor(): array {

		$eCourse = $this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), Course::PUBLISHED, Course::PRIVATE);

		$eChapter = $this->createChapter($eCourse, 'Module du bilan');
		$eLessonFirst = $this->createLesson($eChapter, 'Première leçon', 10);
		$eLessonSecond = $this->createLesson($eChapter, 'Seconde leçon', 20);
		$eLessonQuiz = $this->createLesson($this->createChapter($eCourse, 'Module du quiz'), 'Leçon à quiz', 15);

		// Sans `build()` : la question n'a pas de choix, le Bilan ne lit que les essais qui y répondent.
		QuestionLib::create(new Question([
			'lesson' => $eLessonQuiz,
			'statement' => 'zzTest question',
			'type' => Question::SINGLE
		]));

		// Sans `build()` : le formulaire de la leçon vérifie qu'un quiz a des choix justes, ce qui
		// n'importe pas au Bilan, qui ne lit que le mode de validation.
		$eLessonQuiz['validation'] = Lesson::QUIZ;
		LessonLib::update($eLessonQuiz, ['validation']);

		return [$this->getCourse($eCourse['id']), [$eLessonFirst, $eLessonSecond, $eLessonQuiz]];

	}

	/**
	 * Un compte `example.org`, domaine réservé où aucun envoi n'est délivré, écrit dans la
	 * transaction du test.
	 */
	private function createReader(string $email): \user\User {

		$eUser = new \user\User([
			'firstName' => 'Lecteur',
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

	private function enroll(Course $eCourse, \user\User $eUser): Enrollment {

		$eEnrollment = new Enrollment(['user' => $eUser, 'course' => $eCourse, 'structure' => new \structure\Structure()]);
		EnrollmentLib::create($eEnrollment);

		return $eEnrollment;

	}

	/**
	 * La première réponse au quiz, puis la leçon suivie par le quiz au `$attempts`-ième essai.
	 */
	private function passQuiz(Enrollment $eEnrollment, Lesson $eLesson, int $attempts): void {

		$eQuestion = Question::model()
			->select('id')
			->whereLesson($eLesson)
			->get();

		// Sans `build()` : la réponse d'un essai est ce que le Bilan compte, pas le choix qu'elle porte.
		for($attempt = 1; $attempt <= $attempts; $attempt++) {
			ResponseLib::create(new Response([
				'question' => $eQuestion,
				'user' => $eEnrollment['user'],
				'attempt' => $attempt,
				'choice' => new Choice()
			]));
		}

		ProgressLib::done($eEnrollment, $eLesson, Progress::QUIZ);

		$eProgress = Progress::model()
			->select('id')
			->whereUser($eEnrollment['user'])
			->whereLesson($eLesson)
			->get();

		// Sans `build()` : `attempts` n'est écrit que par la correction du quiz, que le décor ne joue pas.
		$eProgress['attempts'] = $attempts;
		ProgressLib::update($eProgress, ['attempts']);

	}

	private function createReview(Course $eCourse, \user\User $eUser, int $rating, string $status): void {

		// Sans `build()` : un avis écrit à l'écran demande une formation suivie jusqu'au bout, que le
		// décor ne monte pas pour chaque auteur ; le Bilan ne lit que la note, le statut et la date.
		ReviewLib::create(new Review([
			'course' => $eCourse,
			'user' => $eUser,
			'dateEnrollment' => new DateEnrollment(),
			'rating' => $rating,
			'text' => NULL,
			'experiencedAt' => \Clock::date(),
			'status' => $status
		]));

	}

	/**
	 * Un commentaire du compte connecté, ou sa réponse à `$eCommentParent`. Écrit par l'équipe, il porte
	 * la marque d'une réponse de l'équipe (`CommentLib::create()`).
	 */
	private function createComment(Lesson $eLesson, Comment $eCommentParent): Comment {

		// Sans `build()` : le texte est la seule colonne que le formulaire vérifie, et le Bilan ne lit
		// que le fil, son auteur et sa date.
		$eComment = new Comment([
			'lesson' => $eLesson,
			'parent' => $eCommentParent,
			'text' => 'zzTest commentaire'
		]);

		CommentLib::create($eComment);

		return $eComment;

	}

	private function createReading(Course $eCourse, \user\User $eUser, Lesson $eLesson, int $seconds): void {

		// Sans `build()` : une séance n'a pas de formulaire, la page de lecture l'écrit d'un battement.
		ReadingLib::create(new Reading([
			'user' => $eUser,
			'course' => $eCourse,
			'lesson' => $eLesson,
			'seconds' => $seconds
		]));

	}

}
?>
