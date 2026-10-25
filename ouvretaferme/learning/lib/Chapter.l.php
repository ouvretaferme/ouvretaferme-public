<?php
namespace learning;

/**
 * Les modules d'une formation, et leurs leçons.
 *
 * Les modules sont rangés par `position` à partir de 0, sans trou, comme les blocs
 * (`BlockLib`) : un module créé prend le nombre de modules pour position, ce qui le range en
 * dernier, et une suppression fait remonter les suivants d'un rang. Le préfixe « Module N · » que
 * le panneau de création annonce est donc celui que le module prendra.
 *
 * Les leçons sont numérotées sur toute la formation et non par module : « Leçon 3 » se retrouve
 * dans le sommaire sans savoir dans quel module elle vit. Le numéro se pose ici, en une passe sur
 * le programme, et ne se stocke pas.
 */
class ChapterLib extends ChapterCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {

		// L'écran pose la formation sur l'élément depuis l'éditeur ; en API il n'y a que le corps de
		// la requête pour la désigner.
		if($origin === 'api') {
			return array_merge(['course'], self::getPropertiesUpdate($origin));
		}

		return self::getPropertiesUpdate($origin);

	}

	/**
	 * Seule l'API pose la phase : le panneau du module ne la poste pas, et une propriété de sa liste
	 * qu'il ne poste pas serait vidée à chaque enregistrement. Elle vient après `course`, dont l'étape
	 * `Chapter::phase.consistent` lit la valeur construite.
	 */
	public static function getPropertiesUpdate(?string $origin = NULL): array {

		if($origin === 'api') {
			return ['name', 'summary', 'phase'];
		}

		return ['name', 'summary'];

	}

	/**
	 * Le programme d'une formation : ses modules dans l'ordre, chacun avec ses leçons (`cLesson`),
	 * son numéro (`number`) et la durée de ses leçons écrites (`minutes`), chaque leçon avec son
	 * numéro dans la formation (`number`) et dans son module (`numberInChapter`, le « 2 » de « 1.2 »),
	 * avec le numéro de son module (`chapterNumber`, le « 1 »). Le contenu des leçons n'est pas lu.
	 *
	 * Les modules se rangent par leur position, comme l'éditeur les ordonne. Avec `$byPhase`, ils se
	 * rangent phase par phase, dans l'ordre des phases puis par leur propre position, les modules
	 * sans phase après : c'est l'ordre du sommaire des tutoriels, et il se pose avant la numérotation
	 * pour que le sommaire numérote dans l'ordre où il montre.
	 */
	public static function getByCourse(Course $eCourse, bool $byPhase = FALSE): \Collection {

		$properties = Chapter::getSelectionWithoutCourse();
		$properties['cLesson'] = Lesson::model()
			->select(['id', 'chapter', 'name', 'objective', 'minutes', 'validation', 'written', 'position'])
			->sort(['position' => SORT_ASC, 'id' => SORT_ASC])
			->delegateCollection('chapter');

		if($byPhase) {
			$properties['phase'] = ['position'];
		}

		$cChapter = Chapter::model()
			->select($properties)
			->whereCourse($eCourse)
			->sort(['position' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection(index: 'id');

		if($byPhase) {
			$cChapter->sort(fn(Chapter $eChapter1, Chapter $eChapter2) => self::getRankByPhase($eChapter1) <=> self::getRankByPhase($eChapter2));
		}

		$chapterNumber = 0;
		$lessonNumber = 0;

		// La formation reçue s'accroche au module et à chacune de ses leçons, sans relecture :
		// `Chapter::canRead()`, `Lesson::canWrite()` et `acceptWrite()` la lisent, et c'est la seule
		// que le programme montre.
		foreach($cChapter as $eChapter) {

			$eChapter['course'] = $eCourse;
			$eChapter['number'] = ++$chapterNumber;

			$minutes = NULL;
			$lessonNumberInChapter = 0;

			foreach($eChapter['cLesson'] as $eLesson) {

				$eLesson['number'] = ++$lessonNumber;
				$eLesson['numberInChapter'] = ++$lessonNumberInChapter;
				$eLesson['chapterNumber'] = $eChapter['number'];
				$eLesson['course'] = $eCourse;

				if(
					$eLesson['written'] and
					$eLesson['minutes'] !== NULL
				) {
					$minutes ??= 0;
					$minutes += $eLesson['minutes'];
				}

			}

			// Une leçon vide ne se suit pas encore : sa durée ne compte pas (arbitrage H1 de
			// V-Learning, 19/10), comme dans `CourseLib::fillCounts()` et `ProgressLib::fillDone()`.
			// `NULL` et non zéro quand aucune leçon du module n'est estimée : la durée non estimée est
			// une valeur, que `CourseUi::getDuration()` rend par un tiret. Zéro dirait « ce module ne
			// dure rien ».
			$eChapter['minutes'] = $minutes;

		}

		return $cChapter;

	}

	/**
	 * Le rang d'un module dans le sommaire, comparé terme à terme : sans phase après les phases,
	 * puis la position de sa phase, sa position dans la formation, et son identifiant pour départager
	 * deux modules créés à la même position.
	 */
	private static function getRankByPhase(Chapter $eChapter): array {

		return [
			$eChapter['phase']->empty() ? 1 : 0,
			$eChapter['phase']->empty() ? 0 : $eChapter['phase']['position'],
			$eChapter['position'],
			$eChapter['id']
		];

	}

	/**
	 * Les modules de toutes les formations d'un groupe, chacun avec sa formation entière : c'est la
	 * liste où l'étape `Lesson::chapter.owned` cherche le module posté par l'API.
	 */
	public static function getByCommunity(\structure\Structure $eStructure): \Collection {

		return Chapter::model()
			->select(Chapter::getSelection())
			->whereCourse('IN', new CourseModel()->select('id')->whereCommunity($eStructure)->getSql())
			->getCollection(index: 'id');

	}

	/**
	 * La durée d'une formation, ou `NULL` quand aucune de ses leçons n'est estimée. `array_sum()` ne
	 * distingue pas ce cas de la formation de zéro minute : il rend zéro pour les deux.
	 */
	public static function sumMinutes(\Collection $cChapter): ?int {

		$minutes = NULL;

		foreach($cChapter as $eChapter) {

			if($eChapter['minutes'] !== NULL) {
				$minutes ??= 0;
				$minutes += $eChapter['minutes'];
			}

		}

		return $minutes;

	}

	public static function countLessons(\Collection $cChapter): int {

		$lessons = 0;

		foreach($cChapter as $eChapter) {
			$lessons += $eChapter['cLesson']->count();
		}

		return $lessons;

	}

	/**
	 * Pose sur chaque module et chaque leçon du programme ce qui retient sa suppression, quand elle
	 * retirerait la dernière leçon d'une formation publiée. `holdsProgram` : la formation est en
	 * ligne, et ne se publie pas sans leçon (`Course::status.program`, décision du 05/10).
	 * `holdsCompleted` : en présentiel, un inscrit l'a suivie en entier (décision du 04/10).
	 * `Lesson::acceptDelete()` et `Chapter::acceptDelete()` les lisent au lieu de compter eux-mêmes :
	 * l'écran du programme appelle `assertDelete()` une fois par ligne, et une requête dans la règle y
	 * ferait un N+1. Les lecteurs complets ne se comptent qu'en présentiel, et seulement si une ligne
	 * au moins porterait tout le programme.
	 */
	public static function fillHolds(Course $eCourse, \Collection $cChapter): void {

		$eCourse->expects(['status', 'type']);

		$lessons = self::countLessons($cChapter);

		$holdsAll = fn(int $count): bool => (
			$lessons > 0 and
			$count === $lessons
		);

		$candidate = (
			$eCourse['status'] === Course::PUBLISHED and
			$cChapter->find(fn(Chapter $eChapter) => $holdsAll($eChapter['cLesson']->count()))->notEmpty()
		);

		$online = ($eCourse['type'] === Course::ONLINE);

		$program = ($candidate and $online);
		$completed = ($candidate and $online === FALSE and ProgressLib::countCompleted($eCourse) > 0);

		foreach($cChapter as $eChapter) {

			$eChapter['holdsProgram'] = ($program and $holdsAll($eChapter['cLesson']->count()));
			$eChapter['holdsCompleted'] = ($completed and $holdsAll($eChapter['cLesson']->count()));

			foreach($eChapter['cLesson'] as $eLesson) {
				$eLesson['holdsProgram'] = ($program and $holdsAll(1));
				$eLesson['holdsCompleted'] = ($completed and $holdsAll(1));
			}

		}

	}

	/**
	 * Situe une leçon dans le programme : son numéro, et les leçons qui l'encadrent (`eLessonPrevious`,
	 * `eLessonNext`, vides aux deux bouts).
	 */
	public static function locate(\Collection $cChapter, Lesson $eLesson): void {

		$lessons = [];

		foreach($cChapter as $eChapter) {
			foreach($eChapter['cLesson'] as $eLessonChapter) {
				$lessons[] = $eLessonChapter;
			}
		}

		foreach($lessons as $index => $eLessonProgram) {

			if($eLessonProgram['id'] === $eLesson['id']) {

				$eLesson['number'] = $eLessonProgram['number'];
				$eLesson['numberInChapter'] = $eLessonProgram['numberInChapter'];
				$eLesson['chapterNumber'] = $eLessonProgram['chapterNumber'];
				$eLesson['eLessonPrevious'] = $lessons[$index - 1] ?? new Lesson();
				$eLesson['eLessonNext'] = $lessons[$index + 1] ?? new Lesson();

			}

		}

	}

	/**
	 * Où la leçon se tient, « Leçon n sur N » (`positionNumber`, `positionTotal`), sur une leçon
	 * située (`locate()`), dans une formation qui a compté ses leçons (`countLessons()`). En ligne, la
	 * leçon se situe dans son module, dont le surtitre de la bande porte le numéro ; sur place ou en
	 * visio, les leçons se suivent sur toute la formation.
	 */
	public static function fillPosition(Course $eCourse, \Collection $cChapter, Lesson $eLesson): void {

		$eCourse->expects(['type', 'lessons']);
		$eLesson->expects(['chapter', 'number', 'numberInChapter']);

		[$eLesson['positionNumber'], $eLesson['positionTotal']] = match($eCourse['type']) {
			Course::ONLINE => [$eLesson['numberInChapter'], $cChapter[$eLesson['chapter']['id']]['cLesson']->count()],
			Course::REMOTE, Course::ONSITE, Course::MIXED => [$eLesson['number'], $eCourse['lessons']],
		};

	}

	/**
	 * La leçon `$lesson` telle que le programme la porte, avec son numéro, ou une leçon vide si elle
	 * n'est pas de cette formation : chercher dans le programme borne l'identifiant posté à la
	 * formation sans requête de plus.
	 */
	public static function findLesson(\Collection $cChapter, int $lesson): Lesson {

		foreach($cChapter as $eChapter) {
			foreach($eChapter['cLesson'] as $eLesson) {
				if($eLesson['id'] === $lesson) {
					return $eLesson;
				}
			}
		}

		return new Lesson();

	}

	/**
	 * La leçon où l'inscription amène le lecteur : celle d'où il a commencé la formation, si elle
	 * est bien de cette formation, sinon la première. L'origine vient d'un POST, qui se forge :
	 * chercher dans le programme la borne à la formation sans requête de plus.
	 */
	public static function getLanding(\Collection $cChapter, Lesson $eLessonOrigin): Lesson {

		if($eLessonOrigin->notEmpty()) {

			$eLesson = self::findLesson($cChapter, $eLessonOrigin['id']);

			if($eLesson->notEmpty()) {
				return $eLesson;
			}

		}

		return CourseLib::getFirst($cChapter);

	}

	/**
	 * La formation a-t-elle au moins une leçon ? Une formation datée peut n'en avoir aucune, son
	 * contenu étant la journée elle-même, et on ne commence pas une formation vide.
	 */
	public static function hasLessons(Course $eCourse): bool {

		return Lesson::model()
			->whereCourse($eCourse)
			->exists();

	}

	public static function create(Chapter $e): void {

		$e->expects(['course']);

		Chapter::model()->beginTransaction();

			$e['position'] = Chapter::model()
				->whereCourse($e['course'])
				->count();

			parent::create($e);

			CourseLib::touch($e['course']);

		Chapter::model()->commit();

	}

	/**
	 * Le premier module et ses leçons, posés ensemble par le parcours de création : tout ou rien, un
	 * module sans les leçons qu'on vient de lui donner n'étant pas ce qui a été demandé. Chaque
	 * écriture passe par son Lib, qui range et date.
	 */
	public static function createWithLessons(Chapter $e, \Collection $cLesson): void {

		Chapter::model()->beginTransaction();

			self::create($e);

			foreach($cLesson as $eLesson) {
				LessonLib::create($eLesson);
			}

		Chapter::model()->commit();

	}

	public static function update(Chapter $e, array $properties): void {

		$e->expects(['course']);

		Chapter::model()->beginTransaction();

			parent::update($e, $properties);

			CourseLib::touch($e['course']);

		Chapter::model()->commit();

	}

	public static function updatePositions(Course $eCourse, array $ids): void {

		$cChapter = Chapter::model()
			->select('id')
			->whereCourse($eCourse)
			->getCollection();

		$positions = array_flip(array_values($ids));

		if(
			count($ids) !== $cChapter->count() or
			count($positions) !== count($ids) or
			array_diff($ids, $cChapter->getIds()) !== []
		) {
			Chapter::fail('positionsIncomplete');
			return;
		}

		Chapter::model()->beginTransaction();

			foreach($cChapter as $eChapter) {

				$eChapter['position'] = $positions[$eChapter['id']];

				Chapter::model()
					->select('position')
					->update($eChapter);

			}

		Chapter::model()->commit();

	}

	/**
	 * Le refus d'une formation publiée qu'on viderait de ses dernières leçons, en ligne toujours, en
	 * présentiel si un inscrit l'a suivie en entier, est celui de `Chapter::acceptDelete()`, rejugé
	 * ici au même montage que `LessonLib::delete()`, verrou de la formation compris.
	 *
	 * `deleteWithCourse()` est le chemin par où `CourseLib::delete()` emporte le programme : ni ce
	 * refus ni le resserrement des rangs n'y sont, il n'y a plus rien à garder quand tout part.
	 *
	 * Les modules suivants remontent d'un rang, pour que la liste reste sans trou. L'écriture de
	 * masse garde un tableau de valeurs : un élément porteur d'un id la bornerait à une ligne.
	 */
	public static function delete(Chapter $e): void {

		$e->expects(['course', 'position']);

		Chapter::model()->beginTransaction();

			CourseLib::touch($e['course']);

			$eCourse = Course::model()
				->select(['id', 'status', 'type'])
				->whereId($e['course'])
				->get();

			if(
				$eCourse['status'] === Course::PUBLISHED and
				Lesson::model()->whereChapter($e)->exists() and
				Lesson::model()->whereCourse($eCourse)->whereChapter('!=', $e)->exists() === FALSE
			) {

				if($eCourse['type'] === Course::ONLINE) {
					Chapter::model()->commit();
					Chapter::fail('deleteLastPublished');
					return;
				}

				if(ProgressLib::countCompleted($eCourse) > 0) {
					Chapter::model()->commit();
					Chapter::fail('deleteCompleted');
					return;
				}

			}

			self::deleteWithCourse($e);

			Chapter::model()
				->whereCourse($e['course'])
				->wherePosition('>', $e['position'])
				->update([
					'position' => new \Sql('position - 1')
				]);

		Chapter::model()->commit();

	}

	/**
	 * Les modules d'une phase qu'on retire restent dans leur formation, sans phase. La formation borne
	 * l'écriture en plus de la phase, que ses modules désignent toujours dans la même formation
	 * (`Chapter::phase.consistent`). L'écriture de masse garde un tableau de valeurs : un élément
	 * porteur d'un id la bornerait à une ligne.
	 */
	public static function removePhase(Phase $ePhase): void {

		$ePhase->expects(['course']);

		Chapter::model()
			->whereCourse($ePhase['course'])
			->wherePhase($ePhase)
			->update([
				'phase' => NULL
			]);

	}

	/**
	 * Le module et ses leçons, sans dater la formation, pour la même raison que
	 * `LessonLib::deleteWithCourse()`. La leçon n'est lue que pour ce que sa suppression écrit.
	 *
	 * Les questions de FAQ qui renvoyaient au module le perdent ici, chemin commun au retrait d'un
	 * module et à celui de sa formation.
	 */
	public static function deleteWithCourse(Chapter $e): void {

		$cLesson = Lesson::model()
			->select(['id', 'course', 'chapter'])
			->whereChapter($e)
			->getCollection();

		foreach($cLesson as $eLesson) {
			LessonLib::deleteWithCourse($eLesson);
		}

		FaqLib::removeChapter($e);

		parent::delete($e);

	}

}
?>
