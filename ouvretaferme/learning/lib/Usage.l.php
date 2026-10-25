<?php
namespace learning;

/**
 * Le Bilan d'une formation en ligne : ce que ses lecteurs en font sur une période, en nombres et
 * sans aucun lecteur nommé. L'onglet Bilan de l'éditeur (`/formation/{id}/bilan`) et la route
 * `GET course/{id}/usage` de l'API lisent la même préparation, `get()`, pour que l'écran et l'API
 * rendent les mêmes chiffres avec les mêmes définitions.
 *
 * Chaque compte vit dans le Lib du module qu'il lit ; ce Lib ne fait que fixer la période et
 * assembler.
 */
class UsageLib {

	/**
	 * Les périodes du Bilan : 30 jours, 90 jours, 12 mois, et toute la durée de la formation. Le Bilan
	 * s'ouvre sur 90 jours, première période dont la série par semaine dessine une tendance (
	 * 23/10).
	 */
	const PERIODS = ['30d', '90d', '12m', 'all'];
	const PERIOD_DEFAULT = '90d';

	const WEEK = 'week';
	const MONTH = 'month';

	/**
	 * Les pas d'une série, la semaine par défaut et le mois au choix (23/10).
	 */
	const STEPS = [self::WEEK, self::MONTH];
	const STEP_DEFAULT = self::WEEK;

	/**
	 * Les pas qu'une période admet. Trente jours n'ont que la semaine : par mois, ils feraient deux
	 * barres, la première de quelques jours, qui ne se comparent pas.
	 */
	public static function getSteps(string $period): array {

		return match($period) {
			'30d' => [self::WEEK],
			'90d', '12m', 'all' => self::STEPS,
		};

	}

	/**
	 * Les jours compris de la période (`from`, `to`) jusqu'à aujourd'hui, et le pas choisi (`step`).
	 * Une période en mois part du premier du mois ; « Depuis l'ouverture » part de la première inscription confirmée,
	 * ou de la publication d'une formation que personne n'a encore suivie.
	 *
	 * Le début se cale ensuite sur un lundi, ou reste s'il en est un (24/10) : chaque semaine
	 * est alors entière, et les barres couvrent exactement la plage des chiffres clés. Le calage vaut
	 * pour les deux pas, pour qu'un chiffre clé ne change pas quand on passe de la semaine au mois.
	 * Trente jours, quatre-vingt-dix jours et « Depuis l'ouverture » reculent au lundi qui précède, pour ne rien
	 * perdre de la période demandée. Douze mois avancent au lundi qui suit le premier du mois : reculer
	 * ajouterait au pas mois une treizième barre de un à six jours, qui se lirait comme une chute
	 * d'activité, quand avancer n'ampute que la première des douze.
	 */
	public static function getRange(Course $eCourse, string $period, string $step): array {

		if(in_array($step, self::getSteps($period), TRUE) === FALSE) {
			throw new \Exception('Step '.$step.' is not available on period '.$period);
		}

		$eCourse->expects(['publishedAt', 'createdAt']);

		$today = new \DateTimeImmutable(\Clock::date());

		$start = match($period) {
			'30d' => $today->modify('-29 days'),
			'90d' => $today->modify('-89 days'),
			'12m' => $today->modify('first day of -11 months'),
			'all' => new \DateTimeImmutable(EnrollmentLib::getFirstConfirmedAt($eCourse) ?? substr($eCourse['publishedAt'] ?? $eCourse['createdAt'], 0, 10)),
		};

		$from = match($period) {
			'30d', '90d', 'all' => $start->modify('monday this week'),
			'12m' => $start->modify('-1 day')->modify('next monday'),
		};

		return [
			'period' => $period,
			'from' => $from->format('Y-m-d'),
			'to' => $today->format('Y-m-d'),
			'step' => $step
		];

	}

	/**
	 * Le premier jour du pas d'une date, en SQL, que les comptes par pas groupent et rendent : le lundi
	 * de sa semaine ISO ou le premier du mois. `getStepKey()` en est le jumeau
	 * PHP, pour une date déjà lue.
	 */
	public static function getStepSql(string $column, string $step): \Sql {

		return match($step) {
			self::WEEK => new \Sql('DATE('.$column.' - INTERVAL WEEKDAY('.$column.') DAY)'),
			self::MONTH => new \Sql('DATE_FORMAT('.$column.', \'%Y-%m-01\')'),
		};

	}

	public static function getStepKey(string $date, string $step): string {

		$day = new \DateTimeImmutable(substr($date, 0, 10));

		return match($step) {
			self::WEEK => $day->modify('monday this week')->format('Y-m-d'),
			self::MONTH => $day->format('Y-m-01'),
		};

	}

	/**
	 * Une ligne par pas de la période, du plus ancien au plus récent, pas vides compris : `date`, le
	 * premier jour du pas, puis chaque compte de `$counts`, indexé par le premier jour du pas, à 0
	 * quand le pas n'en a pas. La période part d'un lundi (`getRange()`) : au pas mois, le premier
	 * pas commence avant elle quand ce lundi n'est pas un premier du mois, et ne compte que ses jours
	 * dans la période.
	 */
	public static function getSeries(array $range, array $counts): array {

		$step = match($range['step']) {
			self::WEEK => '+1 week',
			self::MONTH => '+1 month',
		};

		$series = [];

		$date = new \DateTimeImmutable(self::getStepKey($range['from'], $range['step']));
		$to = new \DateTimeImmutable($range['to']);

		while($date <= $to) {

			$key = $date->format('Y-m-d');
			$line = ['date' => $key];

			foreach($counts as $name => $values) {
				$line[$name] = $values[$key] ?? 0;
			}

			$series[] = $line;

			$date = $date->modify($step);

		}

		return $series;

	}

	/**
	 * Le Bilan de la formation sur la période, par pas, pour l'écran comme pour l'API.
	 *
	 * À ce jour, quelle que soit la période : `readers` (inscrits confirmés), `readersCompleted` (ceux
	 * qui ont suivi toutes les leçons) et `completionRate`, en pour cent des inscrits. Sur la période :
	 * `enrolled` (inscriptions confirmées), `readersActive` (lecteurs qui ont suivi au moins une leçon),
	 * `completed` (lecteurs dont la dernière leçon tombe dans la période), `reviews` (avis affichés,
	 * leur note moyenne et leur répartition de 5 à 1), `comments` (commentaires de premier niveau,
	 * dont sans réponse), la série de ces cinq comptes par pas, et chaque leçon dans l'ordre du
	 * programme, indexée par leçon : les lecteurs qui l'ont suivie, leur temps médian en secondes, ses
	 * commentaires et, pour une leçon validée par quiz, la part réussie au premier essai.
	 *
	 * Le temps médian et le taux d'un quiz se rendent dès le premier lecteur, sans plancher (
	 * 23/10, « Pas de minimum ») : aucun nom ne sort, et l'équipe d'un groupe ou d'un réseau lit
	 * déjà chaque lecteur nommé dans son suivi.
	 */
	public static function get(Course $eCourse, \Collection $cChapter, string $period, string $step = self::STEP_DEFAULT): array {

		$eCourse->expects(['readers']);

		$range = self::getRange($eCourse, $period, $step);
		[$from, $to, $step] = [$range['from'], $range['to'], $range['step']];

		$completedAt = ProgressLib::getCompletedAt($eCourse);

		$completedBySteps = [];

		foreach($completedAt as $date) {

			if($date >= $from and $date <= $to) {
				$key = self::getStepKey($date, $step);
				$completedBySteps[$key] = ($completedBySteps[$key] ?? 0) + 1;
			}

		}

		$distribution = ReviewLib::getDistribution($eCourse, $from, $to);
		$reviews = array_sum($distribution);

		$rating = 0;

		foreach($distribution as $value => $count) {
			$rating += $value * $count;
		}

		$comments = CommentLib::countOnPeriod($eCourse, $from, $to);

		$series = self::getSeries($range, [
			'enrolled' => EnrollmentLib::countBySteps($eCourse, $from, $to, $step),
			'readersActive' => ProgressLib::countActiveBySteps($eCourse, $from, $to, $step),
			'completed' => $completedBySteps,
			'reviews' => ReviewLib::countBySteps($eCourse, $from, $to, $step),
			'comments' => CommentLib::countBySteps($eCourse, $from, $to, $step),
		]);

		return [
			'course' => $eCourse['id'],
			'period' => $range['period'],
			'from' => $from,
			'to' => $to,
			'step' => $step,
			'readers' => $eCourse['readers'],
			'readersCompleted' => count($completedAt),
			'completionRate' => ($eCourse['readers'] > 0) ? (int)round(count($completedAt) * 100 / $eCourse['readers']) : NULL,
			'enrolled' => array_sum(array_column($series, 'enrolled')),
			'readersActive' => ProgressLib::countActive($eCourse, $from, $to),
			'completed' => array_sum($completedBySteps),
			'reviews' => [
				'count' => $reviews,
				'rating' => ($reviews > 0) ? round($rating / $reviews, 1) : NULL,
				'distribution' => $distribution
			],
			'comments' => [
				'count' => $comments['count'],
				'unanswered' => $comments['unanswered']
			],
			'series' => $series,
			'lessons' => self::getLessons($eCourse, $cChapter, $from, $to, $comments['lessons'])
		];

	}

	/**
	 * Chaque leçon sur la période, dans l'ordre du programme et indexée par leçon. Le taux du quiz se
	 * rapporte aux lecteurs qui l'ont tenté pour la première fois dans la période, et vaut NULL quand
	 * aucun ne l'a fait, comme le temps médian d'une leçon que personne n'a ouverte.
	 */
	private static function getLessons(Course $eCourse, \Collection $cChapter, string $from, string $to, array $comments): array {

		$cLessonQuiz = new \Collection();

		foreach($cChapter as $eChapter) {
			foreach($eChapter['cLesson'] as $eLesson) {

				if($eLesson['validation'] === Lesson::QUIZ) {
					$cLessonQuiz[$eLesson['id']] = $eLesson;
				}

			}
		}

		$readersDone = ProgressLib::countByLesson($eCourse, $from, $to);
		$medians = ReadingLib::getMedianByLesson($eCourse, $from, $to);

		$attempts = ResponseLib::getFirstAttempts($cLessonQuiz, $from, $to);
		$passed = $cLessonQuiz->empty() ? [] : ProgressLib::getPassedFirstAttempt($eCourse);

		$lessons = [];

		foreach($cChapter as $eChapter) {
			foreach($eChapter['cLesson'] as $eLesson) {

				$lesson = $eLesson['id'];

				if($eLesson['validation'] === Lesson::QUIZ and ($attempts[$lesson] ?? []) !== []) {
					$quiz = (int)round(count(array_intersect_key($attempts[$lesson], $passed[$lesson] ?? [])) * 100 / count($attempts[$lesson]));
				} else {
					$quiz = NULL;
				}

				$lessons[$lesson] = [
					'lesson' => $lesson,
					'readersDone' => $readersDone[$lesson] ?? 0,
					'medianSeconds' => $medians[$lesson] ?? NULL,
					'comments' => $comments[$lesson] ?? 0,
					'quizFirstAttemptRate' => $quiz
				];

			}
		}

		return $lessons;

	}

}
?>
