<?php
namespace learning;

/**
 * Les réponses d'un lecteur au quiz d'une leçon, tentative par tentative. Une tentative porte une
 * ligne par choix coché, et une ligne sans choix pour une question laissée sans réponse : la
 * correction de la dernière tentative se relit donc question par question, même vide.
 *
 * La notation est tout ou rien : une question est juste quand les choix cochés sont exactement ses
 * choix justes (planche L5, hors planche consigné à la porte).
 */
class ResponseLib extends ResponseCrud {

	/**
	 * Ce que le quiz d'une leçon est pour le lecteur connecté (`quiz`, posé par `fillQuizState()`) :
	 * ouvert, pas encore ouvert pour sa date confirmée, réservé aux inscriptions confirmées quand sa
	 * demande attend ou qu'il est en liste d'attente, ou réservé aux personnes inscrites à une date.
	 * Les trois derniers n'existent que sur une formation datée.
	 */
	const QUIZ_OPEN = 'open';
	const QUIZ_NOT_OPENED = 'notOpened';
	const QUIZ_UNCONFIRMED = 'unconfirmed';
	const QUIZ_RESERVED = 'reserved';

	/**
	 * Le nombre de bonnes réponses qui valide un quiz de `$questions` questions au seuil
	 * `$threshold` %, arrondi au supérieur : 80 % de 5 questions, ce sont 4 bonnes réponses.
	 */
	public static function getNeeded(int $questions, int $threshold): int {
		return (int)ceil($questions * $threshold / 100);
	}

	/**
	 * Lit les choix postés par le lecteur, `answer[<question>][]`, et les rend indexés par question.
	 * Une question qui n'est pas de la leçon, un choix qui n'est pas de sa question, ou plusieurs
	 * choix sur une question à une réponse sont des appels forgés : le formulaire ne les produit pas.
	 */
	public static function buildAnswers(\Collection $cQuestion, array $input): array {

		$answers = [];

		foreach($cQuestion as $eQuestion) {
			$answers[$eQuestion['id']] = [];
		}

		foreach($input as $question => $choices) {

			if(
				$cQuestion->offsetExists((int)$question) === FALSE or
				is_array($choices) === FALSE
			) {
				throw new \NotExpectedAction('Question');
			}

			$eQuestion = $cQuestion[(int)$question];

			foreach($choices as $choice) {

				if($eQuestion['cChoice']->offsetExists((int)$choice) === FALSE) {
					throw new \NotExpectedAction('Choice');
				}

				$answers[$eQuestion['id']][] = (int)$choice;

			}

			$answers[$eQuestion['id']] = array_values(array_unique($answers[$eQuestion['id']]));

			if(
				$eQuestion['type'] === Question::SINGLE and
				count($answers[$eQuestion['id']]) > 1
			) {
				throw new \NotExpectedAction('Single choice');
			}

		}

		return $answers;

	}

	public static function isRight(Question $eQuestion, array $choices): bool {

		$corrects = $eQuestion['cChoice']
			->find(fn(Choice $eChoice) => $eChoice['correct'])
			->getIds();

		sort($corrects);
		sort($choices);

		return ($corrects === $choices);

	}

	/**
	 * Note une tentative et l'enregistre : le score sur `Progress`, les choix sur `Response`, et la
	 * leçon suivie si le seuil est atteint. Les réponses sont passées par `buildAnswers()`.
	 *
	 * Une leçon déjà suivie refuse la tentative (`Response::lesson.passed`) : la garde est celle de
	 * `ProgressLib::attempt()`, dans la même transaction, avant toute réponse écrite. La sortie
	 * reprend celle de `LessonLib::delete()` : `commit()`, rien n'étant encore écrit, puis le `fail`.
	 */
	public static function answer(Enrollment $eEnrollment, Lesson $eLesson, \Collection $cQuestion, array $answers): void {

		$eEnrollment->expects(['user', 'course']);
		$eLesson->expects(['quizThreshold']);

		$questions = $cQuestion->count();
		$right = 0;

		foreach($cQuestion as $eQuestion) {
			if(self::isRight($eQuestion, $answers[$eQuestion['id']])) {
				$right++;
			}
		}

		$score = (int)round($right * 100 / $questions);

		Response::model()->beginTransaction();

			if(ProgressLib::attempt($eEnrollment, $eLesson, $score) === 0) {
				Response::model()->commit();
				Response::fail('lesson.passed');
				return;
			}

			$attempt = ProgressLib::getByUserAndLesson($eEnrollment['user'], $eLesson)['attempts'];

			foreach($cQuestion as $eQuestion) {

				$choices = $answers[$eQuestion['id']];

				if($choices === []) {
					$choices = [NULL];
				}

				foreach($choices as $choice) {

					self::create(new Response([
						'question' => $eQuestion,
						'user' => $eEnrollment['user'],
						'attempt' => $attempt,
						'choice' => ($choice === NULL) ? new Choice() : new Choice(['id' => $choice]),
					]));

				}

			}

			if($right >= self::getNeeded($questions, $eLesson['quizThreshold'])) {
				ProgressLib::done($eEnrollment, $eLesson, Progress::QUIZ);
			}

		Response::model()->commit();

	}

	/**
	 * Pose sur la leçon ce que son quiz montre au lecteur : ses questions (`cQuestion`), et s'il a
	 * déjà tenté le quiz (`attempted`), sa dernière tentative corrigée, les choix cochés de chaque
	 * question (`picked`) et si elle est juste (`isRight`), le nombre de bonnes réponses (`right`) et
	 * le rang de cette tentative (`attempts`). `needed` est le nombre de bonnes réponses que le seuil
	 * demande.
	 *
	 * `$retry` montre le quiz à nouveau vierge, sans rien effacer : la tentative suivante s'écrira à
	 * l'envoi.
	 *
	 * Un quiz fermé au lecteur (`fillQuizState()`) ne charge aucune question : il ne les lit pas.
	 */
	public static function fillQuiz(\user\User $eUser, Lesson $eLesson, bool $retry = FALSE): void {

		$eLesson->expects(['quizThreshold']);

		self::fillQuizState($eUser, $eLesson);

		if($eLesson['quiz'] !== self::QUIZ_OPEN) {
			return;
		}

		$cQuestion = QuestionLib::getByLesson($eLesson);

		$answers = $retry ? [] : self::getLastAttempt($eUser, $cQuestion);

		$eLesson['attempted'] = ($answers !== []);
		$right = 0;

		foreach($cQuestion as $eQuestion) {

			$eQuestion['picked'] = $eLesson['attempted'] ? $answers[$eQuestion['id']] : [];
			$eQuestion['isRight'] = ($eLesson['attempted'] and self::isRight($eQuestion, $eQuestion['picked']));

			if($eQuestion['isRight']) {
				$right++;
			}

		}

		$eProgress = ProgressLib::getByUserAndLesson($eUser, $eLesson);

		$eLesson['cQuestion'] = $cQuestion;
		$eLesson['right'] = $right;
		$eLesson['attempts'] = $eProgress->empty() ? 0 : $eProgress['attempts'];
		$eLesson['needed'] = self::getNeeded($cQuestion->count(), $eLesson['quizThreshold']);

	}

	/**
	 * Pose sur la leçon l'état de son quiz pour le lecteur (`quiz`, l'une des constantes `QUIZ_*`) et,
	 * quand il n'est pas encore ouvert, la date confirmée qui l'ouvrira (`eDateConfirmed`). « Ouvert »
	 * ne vient que de `Lesson::canAnswer()`, le droit que les deux gestes valident : l'écran ne peut
	 * pas montrer un formulaire que l'envoi refuserait. La date confirmée, puis une inscription qui
	 * attend sa confirmation, ne servent qu'à départager les états fermés.
	 */
	public static function fillQuizState(\user\User $eUser, Lesson $eLesson): void {

		if($eLesson->canAnswer()) {
			$eLesson['quiz'] = self::QUIZ_OPEN;
			$eLesson['eDateConfirmed'] = new Date();
			return;
		}

		$eLesson['eDateConfirmed'] = DateLib::getConfirmedByUser($eUser, $eLesson['course']);

		if($eLesson['eDateConfirmed']->notEmpty()) {
			$eLesson['quiz'] = self::QUIZ_NOT_OPENED;
		} else if(DateEnrollmentLib::hasUnconfirmed($eUser, $eLesson['course'])) {
			$eLesson['quiz'] = self::QUIZ_UNCONFIRMED;
		} else {
			$eLesson['quiz'] = self::QUIZ_RESERVED;
		}

	}

	/**
	 * Ce que la fiche dit du quiz : son état pour le lecteur (`quiz`), celui de la première leçon
	 * quiz du programme, toutes s'ouvrant ensemble, et ouvert sans leçon quiz, rien n'étant alors
	 * fermé ; et si le programme n'a que des leçons quiz (`quizOnly`), la formation d'une journée
	 * qui n'a rien à lire avant l'ouverture.
	 */
	public static function fillCourseQuiz(\user\User $eUser, Course $eCourse, \Collection $cChapter): void {

		$eLessonQuiz = new Lesson();
		$quizOnly = TRUE;

		foreach($cChapter as $eChapter) {

			foreach($eChapter['cLesson'] as $eLesson) {

				if($eLesson['validation'] !== Lesson::QUIZ) {
					$quizOnly = FALSE;
				} else if($eLessonQuiz->empty()) {
					$eLessonQuiz = $eLesson;
				}

			}

		}

		if($eLessonQuiz->empty()) {
			$eCourse['quiz'] = self::QUIZ_OPEN;
			$eCourse['quizOnly'] = FALSE;
			return;
		}

		// Les leçons du programme ne portent pas leur formation, que le droit de répondre juge.
		$eLessonQuiz['course'] = $eCourse;

		self::fillQuizState($eUser, $eLessonQuiz);

		$eCourse['quiz'] = $eLessonQuiz['quiz'];
		$eCourse['quizOnly'] = $quizOnly;

	}

	/**
	 * Les choix cochés à la dernière tentative du lecteur, indexés par question : une liste vide
	 * pour une question laissée sans réponse, et rien du tout s'il n'a jamais tenté le quiz. La
	 * lecture passe par l'index (user, question).
	 */
	public static function getLastAttempt(\user\User $eUser, \Collection $cQuestion): array {

		if(
			$eUser->empty() or
			$cQuestion->empty()
		) {
			return [];
		}

		// Citation du même module : `new ResponseModel()`, le singleton se viderait sous la requête qui le cite.
		$mResponseLast = new ResponseModel()
			->select(['last' => new \Sql('MAX(attempt)')])
			->whereUser($eUser)
			->whereQuestion('IN', $cQuestion);

		$cResponse = Response::model()
			->select(['question', 'choice'])
			->whereUser($eUser)
			->whereQuestion('IN', $cQuestion)
			->whereAttempt('=', $mResponseLast->getSql())
			->getCollection();

		if($cResponse->empty()) {
			return [];
		}

		$answers = [];

		foreach($cQuestion as $eQuestion) {
			$answers[$eQuestion['id']] = [];
		}

		foreach($cResponse as $eResponse) {

			if($eResponse['choice']->notEmpty()) {
				$answers[$eResponse['question']['id']][] = $eResponse['choice']['id'];
			}

		}

		return $answers;

	}

	/**
	 * Les lecteurs qui ont tenté pour la première fois le quiz de chaque leçon entre deux jours
	 * compris, rangés par leçon et indexés par lecteur : la base du taux de réussite au premier essai
	 * du Bilan (`UsageLib::get()`), dont la réussite se lit dans la progression
	 * (`ProgressLib::getPassedFirstAttempt()`). Les réponses n'ont pas de formation : la question
	 * donne la leçon.
	 */
	public static function getFirstAttempts(\Collection $cLesson, string $from, string $to): array {

		if($cLesson->empty()) {
			return [];
		}

		$cResponse = Response::model()
			->join(Question::model(), 'm1.question = m2.id')
			->select([
				'lesson' => new \Sql('m2.lesson', 'int'),
				'reader' => new \Sql('m1.user', 'int')
			])
			->where('m2.lesson', 'IN', $cLesson)
			->where('m1.attempt', 1)
			->where('m1.createdAt', '>=', $from.' 00:00:00')
			->where('m1.createdAt', '<=', $to.' 23:59:59')
			->group(new \Sql('m2.lesson, m1.user'))
			->getCollection();

		$attempts = [];

		foreach($cResponse as $eResponse) {
			$attempts[$eResponse['lesson']][$eResponse['reader']] = TRUE;
		}

		return $attempts;

	}

	/**
	 * L'heure de la dernière tentative de chaque stagiaire à chaque quiz de la formation, indexée
	 * par compte puis par leçon, et absente pour un quiz jamais tenté : celle de sa dernière réponse
	 * écrite. `Progress.updatedAt` ne la donne pas, le constat de l'équipe et le retrait d'une
	 * déclaration l'écrivant aussi. Les trois tables vivent dans la base commune, où la jointure
	 * est permise.
	 */
	public static function getLastAnsweredAt(Course $eCourse, \Collection $cUser): array {

		if($cUser->empty()) {
			return [];
		}

		$cResponse = Response::model()
			->select([
				'user' => new \Sql('m1.user', 'int'),
				'lesson' => new \Sql('m2.lesson', 'int'),
				'answeredAt' => new \Sql('MAX(m1.createdAt)')
			])
			->join(Question::model(), 'm1.question = m2.id')
			->join(Lesson::model(), 'm2.lesson = m3.id')
			->where('m3.course', $eCourse)
			->where('m1.user', 'IN', $cUser)
			->group(new \Sql('m1.user, m2.lesson'))
			->getCollection();

		$answered = [];

		foreach($cResponse as $eResponse) {
			$answered[$eResponse['user']][$eResponse['lesson']] = $eResponse['answeredAt'];
		}

		return $answered;

	}

	/**
	 * Chaque tentative du compte aux quiz des leçons, indexée par leçon puis par rang de tentative :
	 * son heure (`at`, celle de sa dernière réponse écrite), ses bonnes réponses recalculées par
	 * `isRight()` (`right`) et le nombre de questions de la leçon (`questions`). Rien pour une leçon
	 * dont le quiz n'a jamais été tenté.
	 *
	 * Deux requêtes et aucune jointure : les questions des leçons, puis les réponses du compte à ces
	 * questions par l'index (user, question). La jointure à trois tables de `getLastAnsweredAt()`
	 * parcourt l'index de `learningQuestion` en entier.
	 */
	public static function getAttemptsByUserAndLessons(\user\User $eUser, \Collection $cLesson): array {

		$cQuestion = QuestionLib::getByLessons($cLesson);

		if($cQuestion->empty()) {
			return [];
		}

		$cResponse = Response::model()
			->select(['question', 'choice', 'attempt', 'createdAt'])
			->whereUser($eUser)
			->whereQuestion('IN', $cQuestion)
			->getCollection();

		$questions = [];

		foreach($cQuestion as $eQuestion) {
			$questions[$eQuestion['lesson']['id']] = ($questions[$eQuestion['lesson']['id']] ?? 0) + 1;
		}

		// Les choix cochés de chaque tentative, question par question : une question laissée sans
		// réponse porte une ligne sans choix, et reste une liste vide.
		$picked = [];
		$at = [];

		foreach($cResponse as $eResponse) {

			$question = $eResponse['question']['id'];
			$lesson = $cQuestion[$question]['lesson']['id'];
			$attempt = $eResponse['attempt'];

			$picked[$lesson][$attempt][$question] ??= [];

			if($eResponse['choice']->notEmpty()) {
				$picked[$lesson][$attempt][$question][] = $eResponse['choice']['id'];
			}

			$at[$lesson][$attempt] = max($at[$lesson][$attempt] ?? $eResponse['createdAt'], $eResponse['createdAt']);

		}

		$attempts = [];

		foreach($picked as $lesson => $pickedByAttempt) {

			ksort($pickedByAttempt);

			foreach($pickedByAttempt as $attempt => $pickedByQuestion) {

				$right = 0;

				foreach($pickedByQuestion as $question => $choices) {
					if(self::isRight($cQuestion[$question], $choices)) {
						$right++;
					}
				}

				$attempts[$lesson][$attempt] = [
					'at' => $at[$lesson][$attempt],
					'right' => $right,
					'questions' => $questions[$lesson],
				];

			}

		}

		return $attempts;

	}

	/**
	 * Dans la transaction de `QuestionLib::deleteWithLesson()`.
	 */
	public static function deleteByQuestion(Question $eQuestion): void {

		$cResponse = Response::model()
			->select('id')
			->whereQuestion($eQuestion)
			->getCollection();

		foreach($cResponse as $eResponse) {
			self::delete($eResponse);
		}

	}

	/**
	 * Aucun index ne commence par `choice` : la question borne la lecture par le sien, le choix trie
	 * ensuite.
	 */
	public static function deleteByChoice(Choice $eChoice): void {

		$eChoice->expects(['question']);

		$cResponse = Response::model()
			->select('id')
			->whereQuestion($eChoice['question'])
			->whereChoice($eChoice)
			->getCollection();

		foreach($cResponse as $eResponse) {
			self::delete($eResponse);
		}

	}

}
?>
