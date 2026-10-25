<?php
namespace learning;

class LessonLib extends LessonCrud {

	use \Notifiable;

	/**
	 * Ce que le rendu des blocs d'une leçon demande à la comptabilité (`LessonUi::getContent()`), lu
	 * ici et non au rendu, qui ne requête pas. `learning` ne nomme pas le package qui répond : il émet
	 * l'événement, et seule l'application comptable l'écoute. `$eEnrollment` est vide pour un visiteur
	 * et pour l'aperçu de l'auteur, qui lit donc les formes neutres de ce que lira un visiteur.
	 * `$cStructure` porte les structures dont le lecteur est membre (`EnrollmentLib::getStructures()`),
	 * que la page lit pour elle-même.
	 */
	public static function fillContent(Lesson $eLesson, Enrollment $eEnrollment, \Collection $cStructure): void {

		$eLesson['eEnrollment'] = $eEnrollment;
		$eLesson['cStructure'] = $cStructure;

		if(\structure\StructureSetting::hasAccountingApp()) {
			self::notify('fillContent', $eLesson);
		}

	}

	/**
	 * Les blocs que la lecture de la leçon montre (`cBlock`, `BlockLib::getReadable()`), et ce que le
	 * lecteur en tire (`fillReader()`) : la page de la leçon et son aperçu dans l'éditeur lisent les
	 * mêmes.
	 */
	public static function fillReadable(Lesson $eLesson): void {

		$eLesson['cBlock'] = \block\BlockLib::getReadable(BlockOwnerLib::LESSON, $eLesson['id']);

		self::fillReader($eLesson);

	}

	/**
	 * La forme d'une leçon, que donne ce qu'elle contient et non ce que son auteur déclare : une vidéo,
	 * sinon des étapes numérotées, sinon un texte ou un quiz, sinon rien. Seule écriture de la règle,
	 * que lisent le programme, l'éditeur, l'attestation et l'API. Un quiz seul se lit comme un texte
	 * (arbitrage Q1 de V-Learning, 19/10).
	 */
	public static function getFormat(Lesson $eLesson, \Collection $cBlockReadable): string {

		$eLesson->expects(['written']);

		if($cBlockReadable->contains(fn(\block\Block $eBlock) => $eBlock['type'] === \block\BlockTypeLib::VIDEO)) {
			return Lesson::VIDEO;
		} else if(self::countSteps($cBlockReadable) > 0) {
			return Lesson::STEPS;
		} else if($eLesson['written']) {
			return Lesson::TEXT;
		} else {
			return Lesson::EMPTY;
		}

	}

	/**
	 * Pose `steps` et `format` sur chaque leçon, sur leurs blocs lisibles chargés en une requête
	 * (`BlockLib::getReadableReferences()`), quel que soit le nombre de formations que la liste réunit.
	 */
	public static function fillFormat(\Collection $cLesson): void {

		$readable = \block\BlockLib::getReadableReferences(BlockOwnerLib::LESSON, $cLesson->getIds());

		foreach($cLesson as $eLesson) {
			$eLesson['steps'] = self::countSteps($readable[$eLesson['id']]);
			$eLesson['format'] = self::getFormat($eLesson, $readable[$eLesson['id']]);
		}

	}

	/**
	 * `fillFormat()` sur toutes les leçons des modules : la page d'une leçon, la fiche, l'éditeur, le
	 * sommaire des tutoriels et l'attestation lisent la même.
	 */
	public static function fillChapterFormat(\Collection $cChapter): void {

		$cLesson = new \Collection();

		foreach($cChapter as $eChapter) {
			foreach($eChapter['cLesson'] as $eLesson) {
				$cLesson[$eLesson['id']] = $eLesson;
			}
		}

		self::fillFormat($cLesson);

	}

	/**
	 * Recalcule `written` sur les blocs que la lecture garde, et ne l'écrit que s'il change. Appelée à
	 * chaque écriture de bloc (`BlockOwnerLib::touch()`) et de validation (`update()`), les deux seules
	 * causes qui le font changer.
	 */
	public static function synchronizeWritten(Lesson $e): void {

		$e->expects(['validation', 'written']);

		$written = $e->isWritten(\block\BlockLib::getReadable(BlockOwnerLib::LESSON, $e['id']));

		if($written !== $e['written']) {

			$e['written'] = $written;

			Lesson::model()
				->select('written')
				->update($e);

		}

	}

	/**
	 * Ce que la page d'une leçon tire de son programme : la paire du bas de page, qui mène aux voisines
	 * écrites de `$eLesson` sur toute la formation (`eLessonPreviousWritten`, `eLessonNextWritten`),
	 * vides aux deux bouts. La progression (`done`) compte toutes les leçons suivies, vides comprises :
	 * c'est le compte de `Course::isDone()`, qui décide de la fin de la formation.
	 */
	public static function fillWritten(Course $eCourse, \Collection $cChapter, \Collection $cProgress, Lesson $eLesson): void {

		$eCourse['done'] = $cProgress->count();

		$eLesson['eLessonPreviousWritten'] = new Lesson();
		$eLesson['eLessonNextWritten'] = new Lesson();

		$passed = FALSE;

		foreach($cChapter as $eChapter) {

			foreach($eChapter['cLesson'] as $eLessonChapter) {

				if($eLessonChapter['id'] === $eLesson['id']) {
					$passed = TRUE;
					continue;
				}

				if($eLessonChapter['written'] === FALSE) {
					continue;
				}

				if($passed === FALSE) {
					$eLesson['eLessonPreviousWritten'] = $eLessonChapter;
				} else if($eLesson['eLessonNextWritten']->empty()) {
					$eLesson['eLessonNextWritten'] = $eLessonChapter;
				}

			}

		}

	}

	/**
	 * Ce que la page de la leçon tire de ses blocs (`LessonUi::getContent()`), sur ceux que la lecture
	 * garde (`cBlock`, `BlockLib::getReadable()`).
	 *
	 * `eBlockVideo` est la vidéo de la leçon, qui n'en porte qu'une (`\block\Block::url.video`).
	 * `steps` compte les étapes numérotées (`countSteps()`), que la ligne sous son titre annonce. La
	 * capture d'une liste de critères cochés (`BlockTypeLib::CHECK`) n'ouvre pas le lecteur.
	 *
	 * `eBlockCapture` est la première étape numérotée qui a sa capture. Avec elle, la leçon se lit au
	 * lecteur (`reader`) : les étapes d'un côté, la capture de l'étape choisie de l'autre, vidéo ou pas.
	 * `videoCard` met la vidéo d'une leçon à étapes dans une carte en tête de la leçon, hors du fil.
	 * Une vidéo sans étape reste dans le fil, où elle est tout le contenu.
	 *
	 * `cBlockIntro` est ce qui précède la première liste d'étapes numérotées, au-dessus du lecteur, et
	 * `cBlockSteps` le reste, que le lecteur prend en colonne ; la vidéo n'est dans aucune des deux. Une
	 * liste ouverte par une section se coupe avant la section : la section se rend autour de ses blocs
	 * (`BlockUi::render()`), et son titre ne se sépare pas de ses étapes.
	 */
	public static function fillReader(Lesson $eLesson): void {

		$eLesson->expects(['cBlock']);

		$eLesson['eBlockVideo'] = $eLesson['cBlock']->find(fn(\block\Block $eBlock) => $eBlock['type'] === \block\BlockTypeLib::VIDEO, limit: 1, default: new \block\Block());
		$eLesson['eBlockCapture'] = new \block\Block();
		$eLesson['cBlockIntro'] = new \Collection();
		$eLesson['cBlockSteps'] = new \Collection();
		$eLesson['steps'] = self::countSteps($eLesson['cBlock']);

		$section = NULL;
		$cut = NULL;

		foreach($eLesson['cBlock'] as $id => $eBlock) {

			if($eBlock['type'] === \block\BlockTypeLib::SECTION) {
				$section = $id;
			}

			if(self::isSteps($eBlock)) {

				$cut ??= $section ?? $id;

				if($eLesson['eBlockCapture']->empty()) {
					$eLesson['eBlockCapture'] = $eBlock['cBlock']->find(fn(\block\Block $eBlockChild) => $eBlockChild['media'] !== NULL, limit: 1, default: new \block\Block());
				}

			}

		}

		foreach($eLesson['cBlock'] as $id => $eBlock) {

			if($eBlock['type'] === \block\BlockTypeLib::VIDEO) {
				continue;
			}

			if($id === $cut or $eLesson['cBlockSteps']->notEmpty()) {
				$eLesson['cBlockSteps'][$id] = $eBlock;
			} else {
				$eLesson['cBlockIntro'][$id] = $eBlock;
			}

		}

		$eLesson['reader'] = $eLesson['eBlockCapture']->notEmpty();

		$eLesson['videoCard'] = (
			$eLesson['steps'] > 0 and
			$eLesson['eBlockVideo']->notEmpty()
		);

	}

	/**
	 * Les étapes numérotées des blocs lisibles d'une leçon, toutes listes confondues. Une liste de
	 * critères cochés (`BlockTypeLib::CHECK`) n'est pas une suite d'étapes.
	 */
	private static function countSteps(\Collection $cBlockReadable): int {

		$steps = 0;

		foreach($cBlockReadable as $eBlock) {

			if(self::isSteps($eBlock)) {
				$steps += $eBlock['cBlock']->count();
			}

		}

		return $steps;

	}

	private static function isSteps(\block\Block $eBlock): bool {

		return (
			$eBlock['type'] === \block\BlockTypeLib::STEPS and
			$eBlock['variant'] === \block\BlockTypeLib::NUMBERS
		);

	}

	public static function getPropertiesCreate(?string $origin = NULL): array {

		// L'écran pose le module depuis l'éditeur et écrit le contenu à l'étape suivante ; en API, le
		// corps de la requête porte l'un et l'autre.
		if($origin === 'api') {
			return ['chapter', 'name', 'minutes', 'content', 'validation', 'quizThreshold'];
		}

		return ['name', 'minutes'];

	}

	/**
	 * `chapter` en tête, comme dans la liste de création d'API : `build()` construit dans l'ordre
	 * de la liste (`framework/core/Data.php`), et c'est `chapter.owned` qui pose la formation que
	 * `Lesson::minutes.classified` et `Lesson::validation.questioned` jugent ensuite. `validation`
	 * passe avant `quizThreshold`, que `Lesson::quizThreshold.required` juge sur elle.
	 *
	 * `objective` s'écrit à l'écran seulement : l'API le rend, mais dans la liste `api` il
	 * deviendrait exigible à chaque PUT d'une leçon (`Lesson::objective.missing`,
	 * `framework/core/Api.php`), que les clients existants n'envoient pas.
	 */
	public static function getPropertiesUpdate(?string $origin = NULL): array {

		if($origin === 'api') {
			return ['chapter', 'name', 'minutes', 'content', 'validation', 'quizThreshold'];
		}

		return ['chapter', 'name', 'objective', 'minutes', 'validation', 'quizThreshold'];

	}

	/**
	 * Les leçons entières d'une formation, par rang : recréées dans cet ordre,
	 * chacune reprend dans son module le rang que `create()` compte.
	 */
	public static function getByCourse(Course $eCourse): \Collection {

		return Lesson::model()
			->select(Lesson::getSelectionWithoutCourse())
			->whereCourse($eCourse)
			->sort(['position' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection()
			->setColumn('course', $eCourse);

	}

	/**
	 * La leçon quiz qui clôt le programme, dans l'ordre des modules puis des leçons : c'est elle
	 * qu'une date ouvre (`DateLib::openQuiz()`), et dont l'e-mail et la liste des inscrits disent le
	 * résultat. Vide pour une formation sans quiz. Le rang d'une leçon ne vaut que dans son module,
	 * d'où le tri sur les deux, fait ici sur les seules leçons quiz.
	 */
	public static function getLastQuiz(Course $eCourse): Lesson {

		$cLesson = Lesson::model()
			->select(Lesson::getSelectionWithoutCourse())
			->whereCourse($eCourse)
			->whereValidation(Lesson::QUIZ)
			->getCollection()
			->setColumn('course', $eCourse);

		$eLessonLast = new Lesson();

		foreach($cLesson as $eLesson) {

			if(
				$eLessonLast->empty() or
				[$eLesson['chapter']['position'], $eLesson['position']] > [$eLessonLast['chapter']['position'], $eLessonLast['position']]
			) {
				$eLessonLast = $eLesson;
			}

		}

		return $eLessonLast;

	}

	/**
	 * La formation d'une leçon est celle de son module : le formulaire ne la pose pas.
	 *
	 * Un contenu posé sur l'élément (seule l'API en construit un) devient le bloc texte de la leçon
	 * neuve : la leçon n'a pas de colonne pour le porter.
	 *
	 * Une leçon naît sans bloc : `written` ne tient qu'à sa validation, et le bloc du contenu le
	 * recalcule à son écriture (`BlockOwnerLib::touch()`).
	 */
	public static function create(Lesson $e): void {

		$e->expects(['chapter', 'course']);
		$e->add([
			'content' => NULL,
			'validation' => Lesson::model()->getDefaultValue('validation'),
		]);

		$content = self::extractContent($e);

		$e['written'] = $e->isWritten(new \Collection());

		Lesson::model()->beginTransaction();

			$e['position'] = Lesson::model()
				->whereChapter($e['chapter'])
				->count();

			parent::create($e);

			if($content !== NULL) {

				\block\BlockLib::create(new \block\Block([
					'owner' => BlockOwnerLib::LESSON,
					'reference' => $e['id'],
					'type' => \block\BlockTypeLib::TEXT,
					'content' => $content,
				]));

			}

			CourseLib::touch($e['course']);

		Lesson::model()->commit();

	}

	/**
	 * Changée de module, une leçon se range en dernier dans celui qui la reçoit, et les leçons qui
	 * la suivaient dans celui qu'elle quitte remontent d'un rang : les deux listes restent sans trou.
	 *
	 * Un contenu écrit (par l'API) va dans le bloc texte de la leçon, jamais dans la colonne : il le
	 * crée s'il n'y en a pas, le remplace s'il y en a un, et le retire s'il est vidé. Une leçon
	 * composée a été refusée avant, par `Lesson::content.blocks`. Les blocs sont relus ici plutôt que
	 * pris sur l'élément : la décision doit porter sur ce que la base contient au moment d'écrire.
	 *
	 * Un quiz suffit à écrire une leçon (`Lesson::isWritten()`) : `written` se recalcule quand la
	 * validation s'écrit, après le contenu, dont les blocs l'ont déjà recalculé.
	 */
	public static function update(Lesson $e, array $properties): void {

		$e->expects(['course']);

		$writeContent = in_array('content', $properties);

		if($writeContent) {
			$properties = array_values(array_diff($properties, ['content']));
			$content = self::extractContent($e);
		}

		$eLessonLeft = new Lesson();

		if(in_array('chapter', $properties)) {

			$eLessonBefore = Lesson::model()
				->select(['chapter', 'position'])
				->whereId($e)
				->get();

			if($eLessonBefore['chapter']['id'] !== $e['chapter']['id']) {

				$eLessonLeft = $eLessonBefore;

				$e['position'] = Lesson::model()
					->whereChapter($e['chapter'])
					->count();

				$properties[] = 'position';

			}

		}

		$e['updatedAt'] = new \Sql('NOW()');
		$properties[] = 'updatedAt';

		Lesson::model()->beginTransaction();

			parent::update($e, $properties);

			if($eLessonLeft->notEmpty()) {
				self::closeUp($eLessonLeft['chapter'], $eLessonLeft['position']);
			}

			if($writeContent) {
				self::writeContent($e, $content);
			}

			if(in_array('validation', $properties)) {
				self::synchronizeWritten($e);
			}

			CourseLib::touch($e['course']);

		Lesson::model()->commit();

	}

	/**
	 * Retire `content` de l'élément et le rend : la leçon n'a pas de colonne pour le porter, et
	 * l'écriture de la ligne le refuserait.
	 */
	private static function extractContent(Lesson $e): ?string {

		$content = $e['content'];
		unset($e['content']);

		return $content;

	}

	/**
	 * Écrit par `BlockLib` sans passer par `Block::build()` : le type est posé ici, et le contenu sort
	 * déjà du même cast que celui d'un bloc texte (`Lesson::content`). Le `touch()` de chaque écriture
	 * de bloc date la leçon, que la leçon date déjà : c'est une écriture de plus dans la même
	 * transaction, sans effet visible.
	 */
	private static function writeContent(Lesson $e, ?string $content): void {

		$cBlock = \block\BlockLib::getByOwnerAndReference(BlockOwnerLib::LESSON, $e['id']);

		if($cBlock->empty()) {

			if($content !== NULL) {

				\block\BlockLib::create(new \block\Block([
					'owner' => BlockOwnerLib::LESSON,
					'reference' => $e['id'],
					'type' => \block\BlockTypeLib::TEXT,
					'content' => $content,
				]));

			}

		} else {

			$eBlock = $cBlock->first();

			if($content === NULL) {
				\block\BlockLib::delete($eBlock);
			} else {
				$eBlock['content'] = $content;
				\block\BlockLib::update($eBlock, ['content']);
			}

		}

	}

	public static function updatePositions(Chapter $eChapter, array $ids): void {

		$cLesson = Lesson::model()
			->select('id')
			->whereChapter($eChapter)
			->getCollection();

		$positions = array_flip(array_values($ids));

		if(
			count($ids) !== $cLesson->count() or
			count($positions) !== count($ids) or
			array_diff($ids, $cLesson->getIds()) !== []
		) {
			Lesson::fail('positionsIncomplete');
			return;
		}

		Lesson::model()->beginTransaction();

			foreach($cLesson as $eLesson) {

				$eLesson['position'] = $positions[$eLesson['id']];

				Lesson::model()
					->select('position')
					->update($eLesson);

			}

		Lesson::model()->commit();

	}

	/**
	 * Le refus d'une formation publiée qu'on viderait de sa dernière leçon, en ligne toujours, en
	 * présentiel si un inscrit l'a suivie en entier, est celui de `Lesson::acceptDelete()`, que la
	 * route ne valide pas :
	 * `ModulePage::doDelete()` valide avant d'ouvrir son `FailWatch`, et le refus sortirait muet.
	 * La règle se rejuge donc ici, dans la transaction, et sort par `commit()` puis le `fail` : ne
	 * rien faire laisserait le compteur de transactions à 1 et le COMMIT suivant ne partirait jamais.
	 *
	 * Le cadriciel n'a pas de `SELECT … FOR UPDATE` : c'est `CourseLib::touch()`, première écriture
	 * de la transaction, qui verrouille la ligne de la formation jusqu'au `commit()`, comme dans
	 * `QuestionLib::delete()`. Deux suppressions des deux dernières leçons s'attendent alors, et la
	 * seconde recompte après la première : sans ce verrou, chacune verrait encore l'autre leçon et
	 * la formation se viderait. Le statut et le type se relisent sous ce verrou, pas depuis l'élément
	 * chargé avant, et un refus garde la date que ce `touch()` a avancée.
	 *
	 * `deleteWithCourse()` est le chemin par où `CourseLib::delete()` emporte le programme : ni ce
	 * refus ni le resserrement des rangs n'y sont, il n'y a plus rien à garder quand tout part.
	 */
	public static function delete(Lesson $e): void {

		$e->expects(['course', 'chapter', 'position']);

		Lesson::model()->beginTransaction();

			CourseLib::touch($e['course']);

			$eCourse = Course::model()
				->select(['id', 'status', 'type'])
				->whereId($e['course'])
				->get();

			if(
				$eCourse['status'] === Course::PUBLISHED and
				Lesson::model()->whereCourse($eCourse)->whereId('!=', $e)->exists() === FALSE
			) {

				if($eCourse['type'] === Course::ONLINE) {
					Lesson::model()->commit();
					Lesson::fail('deleteLastPublished');
					return;
				}

				if(ProgressLib::countCompleted($eCourse) > 0) {
					Lesson::model()->commit();
					Lesson::fail('deleteCompleted');
					return;
				}

			}

			self::deleteWithCourse($e);

			self::closeUp($e['chapter'], $e['position']);

		Lesson::model()->commit();

	}

	/**
	 * Les leçons d'un module rangées après la place qui vient de se libérer remontent d'un rang,
	 * pour que la liste reste sans trou, comme celle des modules (`ChapterLib`). L'écriture de
	 * masse garde un tableau de valeurs : un élément porteur d'un id la bornerait à une ligne. Sans
	 * transaction propre : ses deux appelants en tiennent une.
	 */
	private static function closeUp(Chapter $eChapter, int $position): void {

		Lesson::model()
			->whereChapter($eChapter)
			->wherePosition('>', $position)
			->update([
				'position' => new \Sql('position - 1')
			]);

	}

	/**
	 * La leçon, sa progression, ses fichiers, son fil, son quiz et ses blocs, sans dater la formation : c'est sa suppression qui
	 * l'emporte, et la dater ligne à ligne écrirait sur une formation qui disparaît. Elle n'ouvre pas
	 * de transaction : ses deux appelants, `delete()` et `ChapterLib::deleteWithCourse()` sous
	 * `CourseLib::delete()`, en tiennent une.
	 */
	public static function deleteWithCourse(Lesson $e): void {

		ProgressLib::deleteByLesson($e);
		ReadingLib::deleteByLesson($e);
		AttachmentLib::deleteByLesson($e);
		CommentLib::deleteByLesson($e);
		QuestionLib::deleteByLesson($e);
		\block\BlockLib::deleteByOwnerAndReference(BlockOwnerLib::LESSON, $e['id']);

		parent::delete($e);

	}

}
?>
