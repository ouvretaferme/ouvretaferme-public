<?php
namespace learning;

/**
 * Ce que le lecteur a suivi, leçon par leçon. Une ligne par couple (compte, leçon) : suivre à
 * nouveau une leçon la remet à jour, retirer une leçon de celles qu'on a suivies la repasse en
 * attente sans effacer la ligne.
 */
class ProgressLib extends ProgressCrud {

	/**
	 * L'état d'une cellule du suivi, tel que le stagiaire l'a laissé : rien, une leçon déclarée, un
	 * quiz réussi, un quiz tenté sans atteindre le seuil. Le constat de l'équipe se lit à côté
	 * (`checked`), et s'ajoute à n'importe lequel des quatre.
	 */
	const TRACK_NONE = 'none';
	const TRACK_DECLARED = 'declared';
	const TRACK_PASSED = 'passed';
	const TRACK_FAILED = 'failed';

	/**
	 * Les leçons suivies d'une formation par le compte, indexées par leçon. `source` distingue une
	 * leçon réussie au quiz d'une leçon déclarée suivie.
	 */
	public static function getDoneByUserAndCourse(\user\User $eUser, Course $eCourse): \Collection {

		if($eUser->empty()) {
			return new \Collection();
		}

		return Progress::model()
			->select(['id', 'lesson', 'source', 'doneAt'])
			->whereUser($eUser)
			->whereCourse($eCourse)
			->whereStatus(Progress::DONE)
			->getCollection(index: 'lesson');

	}

	/**
	 * Pose sur chaque formation le nombre de leçons que le compte a suivies (`done`) et la durée cumulée
	 * des leçons écrites parmi elles (`doneMinutes`), qui donne ce qu'il lui reste à lire : une leçon
	 * vide n'entre pas dans la durée de la formation (`CourseLib::fillCounts()`).
	 */
	public static function fillDone(\user\User $eUser, \Collection $cCourse): void {

		if($cCourse->empty()) {
			return;
		}

		if($eUser->empty()) {

			foreach($cCourse as $eCourse) {
				$eCourse['done'] = 0;
				$eCourse['doneMinutes'] = 0;
			}

			return;

		}

		$cProgressCount = Progress::model()
			->join(Lesson::model(), 'm1.lesson = m2.id')
			->select([
				'course' => new \Sql('m1.course', 'int'),
				'done' => new \Sql('COUNT(*)', 'int'),
				'doneMinutes' => new \Sql('SUM(IF(m2.written, m2.minutes, NULL))', 'int')
			])
			->where('m1.user', $eUser)
			->where('m1.course', 'IN', $cCourse)
			->where('m1.status', Progress::DONE)
			->group(new \Sql('m1.course'))
			->getCollection(index: 'course');

		foreach($cCourse as $eCourse) {
			$eCourse['done'] = $cProgressCount[$eCourse['id']]['done'] ?? 0;
			$eCourse['doneMinutes'] = $cProgressCount[$eCourse['id']]['doneMinutes'] ?? 0;
		}

	}

	/**
	 * Pose sur chaque module s'il est suivi en entier (`isDone`) : un module sans leçon ne l'est pas.
	 */
	public static function fillChapterDone(\Collection $cChapter, \Collection $cProgress): void {

		foreach($cChapter as $eChapter) {

			$eChapter['isDone'] = (
				$eChapter['cLesson']->notEmpty() and
				$eChapter['cLesson']->find(fn(Lesson $eLesson) => $cProgress->offsetExists($eLesson['id']) === FALSE)->empty()
			);

		}

	}

	/**
	 * Les lecteurs actifs d'une formation : ceux qui ont suivi au moins une leçon entre deux jours
	 * compris. Un lecteur compte une fois, quel que soit le nombre de leçons suivies.
	 */
	public static function countActive(Course $eCourse, string $from, string $to): int {

		return Progress::model()
			->select([
				'readers' => new \Sql('COUNT(DISTINCT user)', 'int')
			])
			->whereCourse($eCourse)
			->whereStatus(Progress::DONE)
			->whereDoneAt('>=', $from.' 00:00:00')
			->whereDoneAt('<=', $to.' 23:59:59')
			->get()['readers'];

	}

	/**
	 * Les lecteurs actifs de chaque pas (`UsageLib::getStepSql()`), indexés par le premier jour du pas.
	 * Un lecteur compte une fois par pas, donc la somme des pas n'est pas le compte de la période.
	 */
	public static function countActiveBySteps(Course $eCourse, string $from, string $to, string $step): array {

		$cProgress = Progress::model()
			->select([
				'step' => UsageLib::getStepSql('doneAt', $step),
				'readers' => new \Sql('COUNT(DISTINCT user)', 'int')
			])
			->whereCourse($eCourse)
			->whereStatus(Progress::DONE)
			->whereDoneAt('>=', $from.' 00:00:00')
			->whereDoneAt('<=', $to.' 23:59:59')
			->group(UsageLib::getStepSql('doneAt', $step))
			->getCollection();

		$steps = [];

		foreach($cProgress as $eProgress) {
			$steps[$eProgress['step']] = $eProgress['readers'];
		}

		return $steps;

	}

	/**
	 * Les lecteurs qui ont suivi toutes les leçons de la formation, avec le jour de la dernière, qui
	 * date leur complétion : indexés par lecteur. Même définition que `countCompleted()`.
	 */
	public static function getCompletedAt(Course $eCourse): array {

		$lessons = Lesson::model()
			->whereCourse($eCourse)
			->count();

		if($lessons === 0) {
			return [];
		}

		$cProgress = Progress::model()
			->select([
				'user',
				'completedAt' => new \Sql('DATE(MAX(doneAt))')
			])
			->whereCourse($eCourse)
			->whereStatus(Progress::DONE)
			->group('user')
			->having(new \Sql('COUNT(*) = '.$lessons))
			->getCollection();

		$completed = [];

		foreach($cProgress as $eProgress) {
			$completed[$eProgress['user']['id']] = $eProgress['completedAt'];
		}

		return $completed;

	}

	/**
	 * Les lecteurs qui ont suivi toutes les leçons de la formation. La progression d'une leçon
	 * retirée part avec elle (`deleteByLesson()`) : compter les leçons suivies suffit à dire qu'il
	 * n'en manque aucune.
	 *
	 * Le compte se fait en PHP sur une ligne par lecteur complet (`group` et `having` passent par
	 * une table temporaire) : à l'échelle des lecteurs d'une formation, c'est sans conséquence.
	 */
	public static function countCompleted(Course $eCourse): int {

		$lessons = Lesson::model()
			->whereCourse($eCourse)
			->count();

		if($lessons === 0) {
			return 0;
		}

		return Progress::model()
			->select(['user'])
			->whereCourse($eCourse)
			->whereStatus(Progress::DONE)
			->group('user')
			->having(new \Sql('COUNT(*) = '.$lessons))
			->getCollection()
			->count();

	}

	/**
	 * Le nombre de lecteurs qui ont suivi chaque leçon de la formation, indexé par leçon, depuis
	 * l'ouverture ou entre deux jours compris (le Bilan sur sa période). Une leçon que personne n'a
	 * suivie n'y figure pas.
	 */
	public static function countByLesson(Course $eCourse, string $from = '', string $to = ''): array {

		$cProgress = Progress::model()
			->select([
				'lesson',
				'readers' => new \Sql('COUNT(*)', 'int')
			])
			->whereCourse($eCourse)
			->whereStatus(Progress::DONE)
			->whereDoneAt('>=', $from.' 00:00:00', if: $from !== '')
			->whereDoneAt('<=', $to.' 23:59:59', if: $to !== '')
			->group('lesson')
			->getCollection();

		$lessons = [];

		foreach($cProgress as $eProgress) {
			$lessons[$eProgress['lesson']['id']] = $eProgress['readers'];
		}

		return $lessons;

	}

	/**
	 * Les lecteurs qui ont réussi le quiz d'une leçon du premier coup, rangés par leçon et indexés par
	 * lecteur : la leçon suivie par son quiz (`Progress::QUIZ`) en une seule tentative. Le jour de
	 * cette tentative se lit dans les réponses (`ResponseLib::getFirstAttempts()`).
	 */
	public static function getPassedFirstAttempt(Course $eCourse): array {

		$cProgress = Progress::model()
			->select(['lesson', 'user'])
			->whereCourse($eCourse)
			->whereStatus(Progress::DONE)
			->whereSource(Progress::QUIZ)
			->whereAttempts(1)
			->getCollection();

		$passed = [];

		foreach($cProgress as $eProgress) {
			$passed[$eProgress['lesson']['id']][$eProgress['user']['id']] = TRUE;
		}

		return $passed;

	}

	public static function getByEnrollment(Enrollment $eEnrollment): \Collection {

		return Progress::model()
			->select(['id'])
			->whereUser($eEnrollment['user'])
			->whereCourse($eEnrollment['course'])
			->getCollection();

	}

	/**
	 * La leçon est suivie : le lecteur la déclare (`Progress::DECLARED`), ou il en réussit le quiz
	 * (`Progress::QUIZ`, par `ResponseLib::answer()`). La déclaration reste la valeur par défaut,
	 * c'est le seul geste du lecteur sur une leçon sans quiz.
	 *
	 * Le passage en suivie se lit sur l'écriture elle-même, jamais sur une relecture qui laisserait
	 * une course entre deux envois : la ligne qui se crée passe en suivie, et l'écriture d'une ligne
	 * existante est bornée à une ligne en attente, comme celle d'`attempt()`, donc son retour dit si
	 * la leçon vient d'être suivie (1) ou l'était déjà (0). Une leçon déjà suivie garde ainsi le
	 * `doneAt` et la `source` de son premier passage.
	 *
	 * C'est ce passage qui peut terminer la formation : la dernière leçon obligatoire suivie
	 * l'annonce aux formateurs (`NotificationLib::completed()`), une leçon facultative jamais.
	 */
	public static function done(Enrollment $eEnrollment, Lesson $eLesson, string $source = Progress::DECLARED): void {

		$eEnrollment->expects(['id', 'user', 'course']);

		$eProgress = new Progress([
			'user' => $eEnrollment['user'],
			'course' => $eEnrollment['course'],
			'lesson' => $eLesson,
			'status' => Progress::DONE,
			'source' => $source,
			'doneAt' => new \Sql('NOW()'),
			'updatedAt' => new \Sql('NOW()'),
		]);

		try {

			self::create($eProgress);

			$done = 1;

		} catch(\DuplicateException) {

			$done = Progress::model()
				->whereUser($eEnrollment['user'])
				->whereLesson($eLesson)
				->whereStatus(Progress::PENDING)
				->update($eProgress->extracts(['status', 'source', 'doneAt', 'updatedAt']));

		}

		if($done === 1) {

			// La leçon arrive souvent par son seul id (l'API, le quiz) : `mandatory` se relit ici, sur
			// le seul passage en suivie, plutôt que d'être exigé de chaque appelant pour une notification.
			$mandatory = Lesson::model()
				->select(['mandatory'])
				->whereId($eLesson)
				->get()['mandatory'];

			if($mandatory and self::countMandatoryLeft($eEnrollment) === 0) {
				NotificationLib::completed($eEnrollment);
			}

		}

	}

	/**
	 * Le nombre de leçons obligatoires de la formation que le compte n'a pas encore suivies. Zéro dit
	 * que la formation est terminée au sens de la notification de fin ; « Suivie en entier »
	 * (`Course::isDone()`) compte aussi les leçons facultatives.
	 *
	 * Un anti-join et non un `NOT IN`, qui grossirait avec les leçons suivies : les leçons de la
	 * formation à gauche, la ligne du compte à droite, ses conditions dans le `ON` pour que la ligne
	 * absente reste une ligne. La clé unique (user, lesson) sert la jointure.
	 */
	public static function countMandatoryLeft(Enrollment $eEnrollment): int {

		$eEnrollment->expects(['user', 'course']);

		return Lesson::model()
			->join(Progress::model(), 'm1.id = m2.lesson AND m2.user = '.$eEnrollment['user']['id'].' AND m2.status = '.Progress::model()->format(Progress::DONE), 'LEFT')
			->where('m2.id IS NULL')
			->where('m1.course', $eEnrollment['course'])
			->where('m1.mandatory', TRUE)
			->count();

	}

	/**
	 * Une tentative du quiz de la leçon : `attempts` compte une tentative de plus et `score` prend
	 * celui de la dernière, sur la ligne du lecteur, créée à la première. Ni le statut ni le constat
	 * de l'équipe ne changent ici : c'est `done()` qui passe la leçon en suivie, au seuil.
	 *
	 * Rend le nombre de lignes écrites. Zéro veut dire que la leçon est déjà suivie : l'écriture est
	 * bornée à une ligne en attente, et `attempts + 1` change toujours une ligne qu'elle atteint. La
	 * garde vit dans l'écriture et non dans une lecture préalable, pour que deux envois simultanés
	 * se sérialisent sur le verrou de la ligne : le second voit la leçon suivie par le premier.
	 */
	public static function attempt(Enrollment $eEnrollment, Lesson $eLesson, int $score): int {

		$eEnrollment->expects(['user', 'course']);

		$eProgress = new Progress([
			'user' => $eEnrollment['user'],
			'course' => $eEnrollment['course'],
			'lesson' => $eLesson,
			'status' => Progress::PENDING,
			'score' => $score,
			'attempts' => 1,
			'updatedAt' => new \Sql('NOW()'),
		]);

		try {

			self::create($eProgress);

			return 1;

		} catch(\DuplicateException) {

			// Une écriture de masse bornée par la clé unique (user, lesson) : un tableau de valeurs, un
			// élément porteur d'un id réduirait la portée à la ligne de cet id.
			return Progress::model()
				->whereUser($eEnrollment['user'])
				->whereLesson($eLesson)
				->whereStatus(Progress::PENDING)
				->update([
					'score' => $score,
					'attempts' => new \Sql('attempts + 1'),
					'updatedAt' => new \Sql('NOW()'),
				]);

		}

	}

	/**
	 * La ligne du lecteur pour la leçon, vide s'il ne l'a jamais touchée.
	 */
	public static function getByUserAndLesson(\user\User $eUser, Lesson $eLesson): Progress {

		$eProgress = new Progress();

		if($eUser->empty()) {
			return $eProgress;
		}

		Progress::model()
			->select(['id', 'status', 'source', 'score', 'attempts', 'doneAt'])
			->whereUser($eUser)
			->whereLesson($eLesson)
			->get($eProgress);

		return $eProgress;

	}

	/**
	 * Ce que les stagiaires ont fait de la formation, indexé par compte puis par leçon. Une ligne
	 * n'existe que pour une leçon déclarée, retirée ou constatée.
	 */
	public static function getByCourseAndUsers(Course $eCourse, \Collection $cUser): \Collection {

		if($cUser->empty()) {
			return new \Collection();
		}

		return Progress::model()
			->select(['id', 'user', 'lesson', 'status', 'source', 'score', 'attempts', 'checkedAt'])
			->whereCourse($eCourse)
			->whereUser('IN', $cUser)
			->getCollection(index: ['user', 'lesson']);

	}

	/**
	 * Pose sur chaque stagiaire ce que le suivi montre de lui, pour l'écran comme pour l'export :
	 * l'état de chaque leçon (`track`, indexé par leçon, voir `getTrack()`), le nombre de leçons
	 * déclarées ou constatées (`followed`) et son inscription (`eEnrollment`, vide s'il ne s'est
	 * jamais inscrit).
	 *
	 * Le nombre de questions de chaque leçon se lit une fois pour toute la formation : c'est lui qui
	 * traduit le score d'un quiz en bonnes réponses. Il est posé aussi sur chaque leçon du programme
	 * (`questions`), où la vue d'une leçon lit si elle a un quiz, même sans stagiaire à montrer.
	 */
	public static function fillTrack(Course $eCourse, \Collection $cChapter, \Collection $cUser): void {

		$ccProgress = self::getByCourseAndUsers($eCourse, $cUser);
		$cEnrollment = EnrollmentLib::getByCourseAndUsers($eCourse, $cUser);

		$lessons = [];

		foreach($cChapter as $eChapter) {
			foreach($eChapter['cLesson'] as $eLesson) {
				$lessons[] = $eLesson['id'];
			}
		}

		$questions = QuestionLib::countByLessons($lessons);

		foreach($cChapter as $eChapter) {
			foreach($eChapter['cLesson'] as $eLesson) {
				$eLesson['questions'] = $questions[$eLesson['id']];
			}
		}

		foreach($cUser as $eUser) {

			$cProgress = $ccProgress->offsetExists($eUser['id']) ? $ccProgress[$eUser['id']] : new \Collection();

			$track = [];
			$followed = 0;

			foreach($cChapter as $eChapter) {

				foreach($eChapter['cLesson'] as $eLesson) {

					$track[$eLesson['id']] = self::getTrack(
						$cProgress->offsetExists($eLesson['id']) ? $cProgress[$eLesson['id']] : new Progress(),
						$questions[$eLesson['id']]
					);

					if(self::isFollowed($track[$eLesson['id']])) {
						$followed++;
					}

				}

			}

			$eUser['track'] = $track;
			$eUser['followed'] = $followed;
			$eUser['eEnrollment'] = $cEnrollment->offsetExists($eUser['id']) ? $cEnrollment[$eUser['id']] : new Enrollment();

		}

	}

	/**
	 * Pose sur chaque stagiaire, en plus de `fillTrack()`, ce que seul l'export détaillé montre de
	 * chaque leçon (`detail`, indexé par leçon) : quand il l'a suivie (`doneAt`, la première
	 * déclaration ou la première réussite, `done()` n'écrivant qu'une ligne en attente), son score
	 * en pour cent (`score`, celui de la dernière tentative), l'heure de cette dernière tentative
	 * (`answeredAt`), et le constat de l'équipe (`checkedAt`, `checkedBy`). Lu à part, par la seule
	 * route de l'export détaillé : l'écran du suivi n'en montre rien et n'a pas à le payer.
	 */
	public static function fillDetail(Course $eCourse, \Collection $cChapter, \Collection $cUser): void {

		if($cUser->empty()) {
			return;
		}

		$ccProgress = Progress::model()
			->select([
				'id', 'user', 'lesson', 'score', 'doneAt', 'checkedAt',
				'checkedBy' => ['firstName', 'lastName']
			])
			->whereCourse($eCourse)
			->whereUser('IN', $cUser)
			->getCollection(index: ['user', 'lesson']);

		$answered = ResponseLib::getLastAnsweredAt($eCourse, $cUser);

		foreach($cUser as $eUser) {

			$cProgress = $ccProgress->offsetExists($eUser['id']) ? $ccProgress[$eUser['id']] : new \Collection();

			$detail = [];

			foreach($cChapter as $eChapter) {

				foreach($eChapter['cLesson'] as $eLesson) {

					$eProgress = $cProgress->offsetExists($eLesson['id']) ? $cProgress[$eLesson['id']] : new Progress([
						'score' => NULL,
						'doneAt' => NULL,
						'checkedAt' => NULL,
						'checkedBy' => new \user\User(),
					]);

					$detail[$eLesson['id']] = [
						'doneAt' => $eProgress['doneAt'],
						'score' => $eProgress['score'],
						'answeredAt' => $answered[$eUser['id']][$eLesson['id']] ?? NULL,
						'checkedAt' => $eProgress['checkedAt'],
						'eUserChecked' => $eProgress['checkedBy'],
					];

				}

			}

			$eUser['detail'] = $detail;

		}

	}

	/**
	 * Pose sur chaque stagiaire, après `fillTrack()` et `fillDetail()`, ce que l'attestation de suivi
	 * retient de lui, et que le relevé de L8d reprendra : la définition est ici une fois.
	 * - `validated` : les leçons suivies, au sens du suivi (`isFollowed()`), constat compris ;
	 * - `minutesValidated` : leur durée estimée, une leçon sans durée ou vide comptant pour zéro, comme
	 *   dans `ChapterLib::getByCourse()` ;
	 * - `minutesAll` : la durée estimée de toute la formation, sur la même règle ;
	 * - `firstDoneAt`, `lastDoneAt` : la première et la dernière leçon suivie, `NULL` sans aucune ;
	 * - `checked` : le nombre de leçons constatées par l'équipe ;
	 * - `validatedAt` : pour chaque leçon, le moment où elle est devenue suivie, la déclaration, la
	 *   réussite au quiz ou le constat, le premier venu ; `NULL` pour une leçon qui ne l'est pas.
	 *
	 * Les dates sont celles du stagiaire (`doneAt`) : une leçon seulement constatée n'en a pas et ne
	 * déplace pas la période.
	 */
	public static function fillSummary(\Collection $cChapter, \Collection $cUser): void {

		foreach($cUser as $eUser) {

			$eUser->expects(['track', 'detail']);

			$validated = 0;
			$minutesValidated = 0;
			$minutesAll = 0;
			$firstDoneAt = NULL;
			$lastDoneAt = NULL;
			$checked = 0;
			$validatedAt = [];

			foreach($cChapter as $eChapter) {

				foreach($eChapter['cLesson'] as $eLesson) {

					if($eLesson['written']) {
						$minutes = $eLesson['minutes'] ?? 0;
					} else {
						$minutes = 0;
					}

					$track = $eUser['track'][$eLesson['id']];
					$detail = $eUser['detail'][$eLesson['id']];

					$minutesAll += $minutes;
					$validatedAt[$eLesson['id']] = NULL;

					if(self::isFollowed($track)) {

						$validated++;
						$minutesValidated += $minutes;

						// La déclaration ou la réussite au quiz porte sa date (`doneAt`), le constat la
						// sienne : la leçon est validée par la première venue.
						$validatedAt[$eLesson['id']] = min(array_filter([$detail['doneAt'], $track['checked'] ? $detail['checkedAt'] : NULL]));

					}

					if($track['checked']) {
						$checked++;
					}

					if($detail['doneAt'] !== NULL) {
						$firstDoneAt = ($firstDoneAt === NULL) ? $detail['doneAt'] : min($firstDoneAt, $detail['doneAt']);
						$lastDoneAt = ($lastDoneAt === NULL) ? $detail['doneAt'] : max($lastDoneAt, $detail['doneAt']);
					}

				}

			}

			$eUser['validated'] = $validated;
			$eUser['minutesValidated'] = $minutesValidated;
			$eUser['minutesAll'] = $minutesAll;
			$eUser['firstDoneAt'] = $firstDoneAt;
			$eUser['lastDoneAt'] = $lastDoneAt;
			$eUser['checked'] = $checked;
			$eUser['validatedAt'] = $validatedAt;

		}

	}

	/**
	 * Une leçon compte comme suivie quand le stagiaire l'a déclarée, quand il en a réussi le quiz, ou
	 * quand l'équipe l'a constatée. Un quiz tenté sans atteindre le seuil n'en fait pas une leçon
	 * suivie. Le suivi (`followed`) et l'attestation (`validated`) comptent par cette seule règle.
	 */
	public static function isFollowed(array $track): bool {

		return (
			in_array($track['state'], [self::TRACK_DECLARED, self::TRACK_PASSED], TRUE) or
			$track['checked']
		);

	}

	/**
	 * Le résultat au quiz de la date, pour la liste de ses inscrits et son export : sur chaque ligne
	 * la cellule du suivi de la leçon quiz (`quizTrack`, voir `getTrack()`), vide pour une ligne sans
	 * compte, qui n'a pas pu le passer ; sur la date le nombre de confirmées qui l'ont réussi
	 * (`quizPassed`), les confirmées du moment, qu'une annulation après réussite ne compte plus.
	 */
	public static function fillDateQuiz(Date $eDate, \Collection $cDateEnrollment): void {

		$eDate->expects(['eLessonQuiz']);

		$eLesson = $eDate['eLessonQuiz'];

		$cUser = $cDateEnrollment
			->find(fn(DateEnrollment $e) => $e['user']->notEmpty())
			->getColumnCollection('user');

		$cProgress = $cUser->empty()
			? new \Collection()
			: Progress::model()
				->select(['id', 'user', 'status', 'source', 'score', 'attempts', 'checkedAt'])
				->whereLesson($eLesson)
				->whereUser('IN', $cUser)
				->getCollection(index: 'user');

		$questions = QuestionLib::countByLesson($eLesson);

		$eDate['quizPassed'] = 0;

		foreach($cDateEnrollment as $eDateEnrollment) {

			$eProgress = ($eDateEnrollment['user']->notEmpty() and $cProgress->offsetExists($eDateEnrollment['user']['id']))
				? $cProgress[$eDateEnrollment['user']['id']]
				: new Progress();

			$eDateEnrollment['quizTrack'] = self::getTrack($eProgress, $questions);

			if(
				$eDateEnrollment['status'] === DateEnrollment::CONFIRMED and
				$eDateEnrollment['quizTrack']['state'] === self::TRACK_PASSED
			) {
				$eDate['quizPassed']++;
			}

		}

	}

	/**
	 * Une cellule du suivi : `state` (l'une des constantes `TRACK_*`), `checked` (l'équipe a constaté
	 * la leçon), et pour un quiz tenté `right` (bonnes réponses), `questions` et `attempts`.
	 *
	 * `score` est un pourcentage, `right` s'en déduit sur le nombre de questions **actuel** : exact
	 * tant que le quiz n'a pas changé depuis la tentative (écart consigné dans le plan de L5). Une
	 * ligne n'existe pas pour une leçon que personne n'a touchée.
	 */
	private static function getTrack(Progress $eProgress, int $questions): array {

		$track = [
			'state' => self::TRACK_NONE,
			'checked' => FALSE,
			'right' => NULL,
			'questions' => NULL,
			'attempts' => 0,
		];

		if($eProgress->empty()) {
			return $track;
		}

		$track['checked'] = ($eProgress['checkedAt'] !== NULL);

		if($eProgress['score'] !== NULL) {
			$track['right'] = (int)round($eProgress['score'] * $questions / 100);
			$track['questions'] = $questions;
			$track['attempts'] = $eProgress['attempts'];
		}

		if($eProgress['status'] === Progress::DONE) {
			$track['state'] = match($eProgress['source']) {
				Progress::DECLARED => self::TRACK_DECLARED,
				Progress::QUIZ => self::TRACK_PASSED,
			};
		} else if($eProgress['score'] !== NULL) {
			$track['state'] = self::TRACK_FAILED;
		}

		return $track;

	}

	/**
	 * L'équipe constate que le stagiaire a suivi la leçon (F23). Seuls `checkedBy` et `checkedAt`
	 * s'écrivent : le statut reste celui que le stagiaire a déclaré (Q-D), et une leçon constatée
	 * sans avoir été déclarée reste en attente, ce qu'aucun compteur de lecture ne voit.
	 *
	 * Même forme que `done()` : la ligne se crée, et existe déjà si le stagiaire a touché la leçon.
	 */
	public static function check(Course $eCourse, Lesson $eLesson, \user\User $eUser): void {

		$eProgress = new Progress([
			'user' => $eUser,
			'course' => $eCourse,
			'lesson' => $eLesson,
			'status' => Progress::PENDING,
			'checkedBy' => \user\ConnectionLib::getOnline(),
			'checkedAt' => new \Sql('NOW()'),
			'updatedAt' => new \Sql('NOW()'),
		]);

		try {

			self::create($eProgress);

		} catch(\DuplicateException) {

			Progress::model()
				->whereUser($eUser)
				->whereLesson($eLesson)
				->update($eProgress->extracts(['checkedBy', 'checkedAt', 'updatedAt']));

		}

	}

	/**
	 * Le constat retiré. La ligne reste : elle porte peut-être ce que le stagiaire a déclaré.
	 */
	public static function uncheck(Lesson $eLesson, \user\User $eUser): void {

		Progress::model()
			->whereUser($eUser)
			->whereLesson($eLesson)
			->update([
				'checkedBy' => NULL,
				'checkedAt' => NULL,
				'updatedAt' => new \Sql('NOW()'),
			]);

	}

	public static function undo(Enrollment $eEnrollment, Lesson $eLesson): void {

		Progress::model()
			->whereUser($eEnrollment['user'])
			->whereLesson($eLesson)
			->update([
				'status' => Progress::PENDING,
				'source' => NULL,
				'doneAt' => NULL,
				'updatedAt' => new \Sql('NOW()'),
			]);

	}

	/**
	 * Aucun index ne commence par `lesson` : la formation borne la lecture par son index, la leçon
	 * trie ensuite.
	 */
	public static function deleteByLesson(Lesson $eLesson): void {

		$eLesson->expects(['course']);

		$cProgress = Progress::model()
			->select(['id'])
			->whereCourse($eLesson['course'])
			->whereLesson($eLesson)
			->getCollection();

		foreach($cProgress as $eProgress) {
			self::delete($eProgress);
		}

	}

}
?>
