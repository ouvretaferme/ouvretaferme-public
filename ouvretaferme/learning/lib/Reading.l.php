<?php
namespace learning;

/**
 * Le journal de lecture d'un stagiaire (lot L8c) : une ligne par séance de lecture d'une leçon, son
 * ouverture (`startedAt`), le dernier signal reçu (`seenAt`) et le temps actif cumulé (`seconds`).
 *
 * Il ne vaut que pour une formation de groupe ou de réseau (`Course::acceptReading()`) et pour un
 * compte inscrit : l'équipe qui encadre le relit, et le stagiaire pour lui-même.
 *
 * La page d'une leçon ouvre ou prolonge la séance à l'affichage, puis la leçon signale toutes les
 * `PERIOD` secondes tant que l'onglet est visible et que le stagiaire y a agi depuis moins de
 * `IDLE` secondes (`LearningReading` dans `learning.js`). Deux signaux manqués (`GAP_MAX`) closent
 * la séance, et le signal suivant en ouvre une nouvelle.
 *
 * Le temps compté est celui que le serveur mesure entre deux signaux, jamais ce que le navigateur
 * annonce : un client qui signale plus souvent n'ajoute que l'écoulé réel, et un signal ne compte
 * jamais plus de `GAP_MAX` secondes.
 */
class ReadingLib extends ReadingCrud {

	/** L'intervalle entre deux signaux de la leçon ouverte, en secondes. */
	const PERIOD = 30;

	/** Au-delà de cet écart sans signal, en secondes, la séance est close. */
	const GAP_MAX = 60;

	/**
	 * Le temps sans interaction (défilement, clic, touche, vidéo en lecture) après lequel la leçon
	 * cesse de signaler, en secondes (porte du plan L8, question 1 a).
	 */
	const IDLE = 300;

	/** Le constat de l'équipe, seul événement du journal qui n'est pas un état de `ProgressLib::TRACK_*`. */
	const EVENT_CHECKED = 'checked';

	/**
	 * Ouvre une séance de lecture de la leçon, ou prolonge celle qui court. Rend `TRUE` quand le
	 * journal a été tenu, `FALSE` pour un compte sans inscription ou une formation hors journal : la
	 * leçon n'a alors pas de signal à émettre.
	 *
	 * Hors transaction : c'est un journal, et la lecture de la leçon ne doit pas tomber avec lui.
	 * Deux onglets sur la même leçon prolongent la même séance, l'écoulé se mesurant depuis le
	 * dernier signal, quel que soit l'onglet qui l'a émis.
	 */
	public static function see(Enrollment $eEnrollment, Lesson $eLesson): bool {

		if($eEnrollment->empty()) {
			return FALSE;
		}

		$eEnrollment->expects(['user', 'course']);

		if($eEnrollment['course']->acceptReading() === FALSE) {
			return FALSE;
		}

		$recent = 'seenAt >= NOW() - INTERVAL '.self::GAP_MAX.' SECOND';

		$eReading = Reading::model()
			->select(['id'])
			->whereCourse($eEnrollment['course'])
			->whereUser($eEnrollment['user'])
			->whereLesson($eLesson)
			->where($recent)
			->sort(['startedAt' => SORT_DESC])
			->get();

		// La séance trouvée se prolonge, quelle que soit la réponse de l'écriture : MySQL ne compte pas
		// une ligne dont aucune valeur ne change, et deux signaux dans la même seconde (deux onglets,
		// l'ouverture suivie du premier signal) rendent 0 ligne affectée sur une séance bien ouverte.
		if($eReading->notEmpty()) {

			// MySQL évalue les affectations d'un UPDATE sur une table de gauche à droite, chacune
			// lisant les valeurs déjà écrites par les précédentes : `seenAt` posé avant `seconds`
			// ferait ajouter zéro à chaque signal. L'écoulé est borné à `GAP_MAX` dans l'écriture même,
			// pour qu'une séance close entre la lecture et elle ne compte pas plus qu'un signal.
			// Des expressions SQL et non des valeurs de l'élément, donc un tableau.
			Reading::model()
				->whereId($eReading['id'])
				->update([
					'seconds' => new \Sql('seconds + LEAST(TIMESTAMPDIFF(SECOND, seenAt, NOW()), '.self::GAP_MAX.')'),
					'seenAt' => new \Sql('NOW()'),
				]);

			return TRUE;

		}

		self::create(new Reading([
			'user' => $eEnrollment['user'],
			'course' => $eEnrollment['course'],
			'lesson' => $eLesson,
		]));

		return TRUE;

	}

	/**
	 * Les séances du compte sur la formation, de la plus ancienne à la plus récente, par l'index
	 * (course, user, startedAt), qui sert aussi le tri.
	 */
	public static function getByUserAndCourse(\user\User $eUser, Course $eCourse): \Collection {

		return Reading::model()
			->select(['id', 'lesson', 'startedAt', 'seenAt', 'seconds'])
			->whereCourse($eCourse)
			->whereUser($eUser)
			->sort(['startedAt' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection();

	}

	/**
	 * Pose sur chaque stagiaire le dernier signal reçu sur la formation (`lastSeenAt`), `NULL` s'il
	 * n'a jamais ouvert une leçon depuis que le journal existe.
	 */
	public static function fillLastSeen(Course $eCourse, \Collection $cUser): void {

		$lastSeen = [];

		if($cUser->notEmpty()) {

			$cReading = Reading::model()
				->select([
					'user',
					'lastSeenAt' => new \Sql('MAX(seenAt)')
				])
				->whereCourse($eCourse)
				->whereUser('IN', $cUser)
				->group('user')
				->getCollection();

			foreach($cReading as $eReading) {
				$lastSeen[$eReading['user']['id']] = $eReading['lastSeenAt'];
			}

		}

		foreach($cUser as $eUser) {
			$eUser['lastSeenAt'] = $lastSeen[$eUser['id']] ?? NULL;
		}

	}

	/**
	 * Le temps mesuré de chaque compte, arrondi en minutes ici et non par l'écran : sur chaque leçon
	 * du programme (`measured`, indexé par leçon, NULL pour une leçon jamais ouverte), et sur le
	 * compte (`readings`, le nombre de séances, `minutesMeasuredAll`, toutes leçons du programme
	 * confondues, et `minutesMeasuredValidated`, sur les leçons validées). Chaque total s'arrondit
	 * une fois, sur la somme des secondes, et non comme la somme de minutes déjà arrondies. Les
	 * totaux et le nombre de séances ne comptent que les leçons du programme, celles que la colonne
	 * du relevé détaille.
	 *
	 * Les secondes sont posées à côté des minutes (`measuredSeconds`, `secondsMeasuredAll`,
	 * `secondsMeasuredValidated`) : le relevé dit « moins d'1 min » sous 60 s mesurées, comme
	 * l'annexe, et non d'après une minute déjà arrondie, où 45 s deviendraient « 1 min ». Elles
	 * valent NULL quand aucune séance n'entre dans le compte (leçon jamais ouverte, aucune leçon
	 * validée ouverte), à distinguer d'une séance ouverte sans un signal, qui en compte 0.
	 *
	 * `minutesMeasuredAll` est la seule définition du temps mesuré (porte L8, reprise 1) : le panneau,
	 * la figure du relevé, sa ligne « Toutes les leçons » et sa note la lisent toutes.
	 * `minutesMeasuredValidated` ne sert qu'à la ligne « Leçons validées » du relevé.
	 *
	 * Le temps par leçon se pose sur le compte et non sur la leçon : le programme est partagé par
	 * tous les comptes de l'export détaillé, où chaque ligne est un compte et une leçon.
	 *
	 * Une seule lecture pour tous les comptes, par l'index (course, user, startedAt). Chaque compte
	 * est passé par `ProgressLib::fillTrack()`, dont l'état de chaque leçon dit si elle est validée,
	 * avec la même règle que la durée retenue (`ProgressLib::fillSummary()`).
	 */
	public static function fillMeasured(Course $eCourse, \Collection $cChapter, \Collection $cUser): void {

		$seconds = [];
		$readings = [];

		if($cUser->notEmpty()) {

			$cReading = Reading::model()
				->select([
					'user', 'lesson',
					'readings' => new \Sql('COUNT(*)', 'int'),
					'total' => new \Sql('SUM(seconds)', 'int')
				])
				->whereCourse($eCourse)
				->whereUser('IN', $cUser)
				->group(['user', 'lesson'])
				->getCollection();

			foreach($cReading as $eReading) {

				$user = $eReading['user']['id'];

				$seconds[$user][$eReading['lesson']['id']] = $eReading['total'];
				$readings[$user][$eReading['lesson']['id']] = $eReading['readings'];

			}

		}

		foreach($cUser as $eUser) {

			$eUser->expects(['track']);

			$secondsByLesson = $seconds[$eUser['id']] ?? [];
			$readingsAll = 0;
			$secondsAll = NULL;
			$secondsValidated = NULL;
			$measured = [];
			$measuredSeconds = [];

			foreach($cChapter as $eChapter) {

				foreach($eChapter['cLesson'] as $eLesson) {

					// Une leçon jamais ouverte n'a pas de temps (NULL), à distinguer d'une leçon ouverte
					// moins d'une demi-minute, qui en a un de 0.
					if(isset($secondsByLesson[$eLesson['id']]) === FALSE) {
						$measured[$eLesson['id']] = NULL;
						$measuredSeconds[$eLesson['id']] = NULL;
						continue;
					}

					$measured[$eLesson['id']] = self::toMinutes($secondsByLesson[$eLesson['id']]);
					$measuredSeconds[$eLesson['id']] = $secondsByLesson[$eLesson['id']];
					$secondsAll = ($secondsAll ?? 0) + $secondsByLesson[$eLesson['id']];
					$readingsAll += $readings[$eUser['id']][$eLesson['id']];

					if(ProgressLib::isFollowed($eUser['track'][$eLesson['id']])) {
						$secondsValidated = ($secondsValidated ?? 0) + $secondsByLesson[$eLesson['id']];
					}

				}

			}

			$eUser['measured'] = $measured;
			$eUser['measuredSeconds'] = $measuredSeconds;
			$eUser['readings'] = $readingsAll;
			$eUser['minutesMeasuredAll'] = self::toMinutes($secondsAll ?? 0);
			$eUser['minutesMeasuredValidated'] = self::toMinutes($secondsValidated ?? 0);
			$eUser['secondsMeasuredAll'] = $secondsAll;
			$eUser['secondsMeasuredValidated'] = $secondsValidated;

		}

	}

	/**
	 * Le journal du compte sur la formation, par jour puis par heure : chaque séance de lecture, et
	 * sous elle ce qui s'est passé pendant qu'elle courait, la déclaration de la leçon et chaque
	 * tentative du quiz avec son score.
	 *
	 * Un événement va à la séance de la même leçon qui l'encadre, de son ouverture jusqu'à `GAP_MAX`
	 * après son dernier signal : un quiz envoyé juste après un signal manqué reste à sa lecture. Un
	 * événement qu'aucune séance n'encadre fait sa propre ligne sans durée : une déclaration par
	 * l'API, ou antérieure au journal. Le constat de l'équipe fait toujours la sienne, avec son
	 * auteur : c'est un geste de l'encadrant, qui ne dit rien de la lecture du stagiaire à la même
	 * heure (planche L8, bloc 4).
	 *
	 * Le compte est passé par `ProgressLib::fillTrack()` puis `fillDetail()`, qui disent l'état de
	 * chaque leçon, l'heure de sa validation et son constat.
	 *
	 * @return array<string, list<array{eLesson: Lesson, at: string, minutes: ?int, seconds: ?int, events: list<array>}>>
	 */
	public static function getJournal(Course $eCourse, \Collection $cChapter, \user\User $eUser): array {

		$eUser->expects(['track', 'detail']);

		$cLesson = new \Collection();

		foreach($cChapter as $eChapter) {
			foreach($eChapter['cLesson'] as $eLesson) {
				$cLesson[$eLesson['id']] = $eLesson;
			}
		}

		$rows = [];

		foreach(self::getByUserAndCourse($eUser, $eCourse) as $eReading) {

			// Une leçon retirée emporte ses séances (`deleteByLesson()`) : toute séance lue a la sienne.
			$rows[] = [
				'eLesson' => $cLesson[$eReading['lesson']['id']],
				'at' => $eReading['startedAt'],
				'until' => date('Y-m-d H:i:s', strtotime($eReading['seenAt']) + self::GAP_MAX),
				'minutes' => self::toMinutes($eReading['seconds']),
				'seconds' => $eReading['seconds'],
				'events' => [],
			];

		}

		$attempts = ResponseLib::getAttemptsByUserAndLessons($eUser, $cLesson);

		foreach($cLesson as $eLesson) {

			$track = $eUser['track'][$eLesson['id']];
			$detail = $eUser['detail'][$eLesson['id']];

			$events = [];

			foreach($attempts[$eLesson['id']] ?? [] as $attempt => $result) {

				// Une leçon réussie au quiz n'accepte plus de tentative (`ProgressLib::attempt()`) :
				// la réussite est la dernière, et toutes les précédentes ont échoué.
				$passed = (
					$track['state'] === ProgressLib::TRACK_PASSED and
					$attempt === $track['attempts']
				);

				$events[] = [
					'type' => $passed ? ProgressLib::TRACK_PASSED : ProgressLib::TRACK_FAILED,
					'at' => $result['at'],
					'right' => $result['right'],
					'questions' => $result['questions'],
				];

			}

			if($track['state'] === ProgressLib::TRACK_DECLARED) {
				$events[] = [
					'type' => ProgressLib::TRACK_DECLARED,
					'at' => $detail['doneAt'],
				];
			}

			foreach($events as $event) {
				self::attachEvent($rows, $eLesson, $event);
			}

			if($detail['checkedAt'] !== NULL) {
				$rows[] = [
					'eLesson' => $eLesson,
					'at' => $detail['checkedAt'],
					'until' => $detail['checkedAt'],
					'minutes' => NULL,
					'seconds' => NULL,
					'events' => [[
						'type' => self::EVENT_CHECKED,
						'at' => $detail['checkedAt'],
						'eUser' => $detail['eUserChecked'],
					]],
				];
			}

		}

		usort($rows, fn(array $row1, array $row2) => strcmp($row1['at'], $row2['at']));

		$journal = [];

		foreach($rows as $row) {

			usort($row['events'], fn(array $event1, array $event2) => strcmp($event1['at'], $event2['at']));

			unset($row['until']);

			$journal[substr($row['at'], 0, 10)][] = $row;

		}

		return $journal;

	}

	/**
	 * Pose l'événement sur la séance de la même leçon qui l'encadre, la plus récente s'il y en a
	 * plusieurs, ou sur une ligne à lui sans durée.
	 */
	private static function attachEvent(array &$rows, Lesson $eLesson, array $event): void {

		$found = NULL;

		foreach($rows as $key => $row) {

			if(
				$row['minutes'] !== NULL and
				$row['eLesson']['id'] === $eLesson['id'] and
				$row['at'] <= $event['at'] and
				$event['at'] <= $row['until']
			) {
				$found = $key;
			}

		}

		if($found !== NULL) {
			$rows[$found]['events'][] = $event;
			return;
		}

		$rows[] = [
			'eLesson' => $eLesson,
			'at' => $event['at'],
			'until' => $event['at'],
			'minutes' => NULL,
			'seconds' => NULL,
			'events' => [$event],
		];

	}

	/**
	 * Le temps médian passé sur chaque leçon par ses lecteurs, en secondes, indexé par leçon : les
	 * séances ouvertes entre deux jours compris, additionnées par lecteur, puis la médiane des
	 * lecteurs. Un lecteur dont les séances ne comptent aucune seconde (ouvertes sans un signal)
	 * n'a pas lu, il n'entre pas dans la médiane. Une leçon sans lecteur mesuré n'y figure pas.
	 */
	public static function getMedianByLesson(Course $eCourse, string $from, string $to): array {

		$cReading = Reading::model()
			->select([
				'lesson', 'user',
				'total' => new \Sql('SUM(seconds)', 'int')
			])
			->whereCourse($eCourse)
			->whereStartedAt('>=', $from.' 00:00:00')
			->whereStartedAt('<=', $to.' 23:59:59')
			->group(['lesson', 'user'])
			->getCollection();

		$totals = [];

		foreach($cReading as $eReading) {

			if($eReading['total'] > 0) {
				$totals[$eReading['lesson']['id']][] = $eReading['total'];
			}

		}

		$medians = [];

		foreach($totals as $lesson => $seconds) {

			sort($seconds);

			$middle = intdiv(count($seconds), 2);

			$medians[$lesson] = (count($seconds) % 2 === 1)
				? $seconds[$middle]
				: (int)round(($seconds[$middle - 1] + $seconds[$middle]) / 2);

		}

		return $medians;

	}

	/** Les séances d'une leçon retirée, lues par l'index `lesson`. */
	public static function deleteByLesson(Lesson $eLesson): void {

		$cReading = Reading::model()
			->select(['id'])
			->whereLesson($eLesson)
			->getCollection();

		foreach($cReading as $eReading) {
			self::delete($eReading);
		}

	}

	/**
	 * Les séances d'une inscription retirée, lues par l'index (course, user) : le journal suit le
	 * sort de la progression, dans la transaction d'`EnrollmentLib::delete()`.
	 */
	public static function deleteByEnrollment(Enrollment $eEnrollment): void {

		$eEnrollment->expects(['user', 'course']);

		$cReading = Reading::model()
			->select(['id'])
			->whereCourse($eEnrollment['course'])
			->whereUser($eEnrollment['user'])
			->getCollection();

		foreach($cReading as $eReading) {
			self::delete($eReading);
		}

	}

	private static function toMinutes(int $seconds): int {
		return (int)round($seconds / 60);
	}

}
?>
