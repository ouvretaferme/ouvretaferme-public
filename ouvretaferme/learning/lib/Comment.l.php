<?php
namespace learning;

/**
 * Le fil d'une leçon, commun à tous ses lecteurs, quelle que soit leur promotion : un message
 * s'écrit, se modifie par son auteur et se retire. Un message de stagiaire prévient les
 * formateurs par la cloche du groupe (`NotificationLib::commented()`) ; les e-mails de formation
 * (F46, L6) restent à venir.
 *
 * Pour les formateurs, un message est nouveau tant qu'aucun formateur ne l'a lu : la
 * lecture vaut pour tous, et garde le premier lecteur.
 *
 * Le fil a un niveau : une réponse porte son commentaire en `parent`, se range dessous à la lecture
 * et part avec lui.
 */
class CommentLib extends CommentCrud {

	/** Les messages lus que la page des commentaires montre à la fois */
	const READ_PER_PAGE = 20;

	/**
	 * Une réponse écrite par l'équipe : lue par son auteur à l'instant de sa création (`create()`).
	 * L'instant compte autant que le lecteur, une réponse de stagiaire que son auteur marque lue une
	 * fois entré dans l'équipe l'est après coup et garde sa ligne.
	 */
	private const TEAM_ANSWER = 'readBy = createdBy AND readAt = createdAt';

	// Un commentaire de lecteur pas encore lu n'a pas de lecture, et `TEAM_ANSWER` y vaut NULL
	private const READER_COMMENT = 'readAt IS NULL OR NOT ('.self::TEAM_ANSWER.')';

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['parent', 'text'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['text'];
	}

	/**
	 * Du plus ancien au plus récent : un fil se lit de haut en bas, la réponse sous la question.
	 */
	public static function getByLesson(Lesson $eLesson): \Collection {

		return Comment::model()
			->select(Comment::getSelection())
			->whereLesson($eLesson)
			->sort(['createdAt' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection();

	}

	/**
	 * Le commentaire auquel on répond, lu dans la leçon et parmi ceux de premier niveau : un id d'une
	 * autre leçon, d'une réponse ou d'un commentaire retiré rend un élément vide.
	 */
	public static function getParentByLesson(Lesson $eLesson, ?int $id): Comment {

		if($id === NULL) {
			return new Comment();
		}

		$eLesson->expects(['course']);

		return Comment::model()
			->select(Comment::getSelection())
			->whereId($id)
			->whereLesson($eLesson)
			->whereCourse($eLesson['course'])
			->whereParent(NULL)
			->get();

	}

	/**
	 * Pose `cCommentAnswer` sur chaque commentaire de premier niveau d'un fil lu en entier : ses
	 * réponses, dans l'ordre du fil.
	 */
	public static function fillAnswers(\Collection $cComment): void {

		$answers = [];

		foreach($cComment as $eComment) {
			if($eComment['parent']->notEmpty()) {
				$answers[$eComment['parent']['id']][] = $eComment;
			}
		}

		foreach($cComment as $eComment) {
			if($eComment['parent']->empty()) {
				$eComment['cCommentAnswer'] = new \Collection($answers[$eComment['id']] ?? []);
			}
		}

	}

	/**
	 * Pose `cCommentAnswer` sur chaque ligne de la boite : les réponses de l'équipe à ce commentaire,
	 * qui n'y ont pas de ligne à elles (`getReadByStructure()`), de la plus ancienne à la plus
	 * récente. Une seule lecture pour toutes les listes de l'écran.
	 */
	public static function fillTeamAnswers(\structure\Structure $eStructure, \Collection ...$cCommentLists): void {

		$parents = [];

		foreach($cCommentLists as $cComment) {
			foreach($cComment as $eComment) {
				$eComment['cCommentAnswer'] = new \Collection();
				$parents[$eComment['id']] = $eComment;
			}
		}

		if($parents === []) {
			return;
		}

		$cCommentAnswer = self::whereStructure($eStructure)
			->select(Comment::getAnswerSelection())
			->whereParent('IN', array_keys($parents))
			->where(self::TEAM_ANSWER)
			->sort(['createdAt' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection();

		self::fillTeamByStructure($eStructure, $cCommentAnswer);

		foreach($cCommentAnswer as $eCommentAnswer) {
			$parents[$eCommentAnswer['parent']['id']]['cCommentAnswer'][] = $eCommentAnswer;
		}

	}

	/**
	 * Pose `comments` sur chaque leçon du programme, et leur somme sur la formation : commentaires et
	 * réponses.
	 */
	public static function fillCountByCourse(Course $eCourse, \Collection $cChapter): void {

		$cComment = Comment::model()
			->select([
				'lesson',
				'count' => new \Sql('COUNT(*)', 'int')
			])
			->whereCourse($eCourse)
			->group('lesson')
			->getCollection();

		$counts = [];

		foreach($cComment as $eComment) {
			$counts[$eComment['lesson']['id']] = $eComment['count'];
		}

		foreach($cChapter as $eChapter) {
			foreach($eChapter['cLesson'] as $eLesson) {
				$eLesson['comments'] = $counts[$eLesson['id']] ?? 0;
			}
		}

		$eCourse['comments'] = array_sum($counts);

	}

	/**
	 * Les commentaires de premier niveau écrits par les lecteurs entre deux jours compris, ce qu'ils
	 * ont demandé ou signalé, sans les réponses du fil ni les messages que l'équipe adresse à tout le
	 * fil : leur nombre (`count`), ceux qui attendent encore une réponse de l'équipe (`unanswered`)
	 * et leur nombre par leçon (`lessons`). L'équipe se reconnait à `TEAM_ANSWER`, la seule trace en
	 * base de qui en était au moment d'écrire ; une réponse compte donc quelle que soit sa date.
	 */
	public static function countOnPeriod(Course $eCourse, string $from, string $to): array {

		$mCommentAnswer = new CommentModel()
			->where('s1.parent', new \Sql('m1.id'))
			->where(self::TEAM_ANSWER);

		$cComment = Comment::model()
			->select([
				'lesson',
				'count' => new \Sql('COUNT(*)', 'int'),
				'unanswered' => new \Sql('SUM(NOT '.$mCommentAnswer->existsSql().')', 'int')
			])
			->whereCourse($eCourse)
			->whereParent(NULL)
			->where(self::READER_COMMENT)
			->whereCreatedAt('>=', $from.' 00:00:00')
			->whereCreatedAt('<=', $to.' 23:59:59')
			->group('lesson')
			->getCollection();

		$comments = [
			'count' => 0,
			'unanswered' => 0,
			'lessons' => []
		];

		foreach($cComment as $eComment) {
			$comments['count'] += $eComment['count'];
			$comments['unanswered'] += $eComment['unanswered'];
			$comments['lessons'][$eComment['lesson']['id']] = $eComment['count'];
		}

		return $comments;

	}

	/**
	 * Les commentaires de premier niveau des lecteurs entre deux jours compris, indexés par le premier jour de leur
	 * pas (`UsageLib::getStepSql()`). Un pas sans commentaire n'y figure pas.
	 */
	public static function countBySteps(Course $eCourse, string $from, string $to, string $step): array {

		$cComment = Comment::model()
			->select([
				'step' => UsageLib::getStepSql('createdAt', $step),
				'count' => new \Sql('COUNT(*)', 'int')
			])
			->whereCourse($eCourse)
			->whereParent(NULL)
			->where(self::READER_COMMENT)
			->whereCreatedAt('>=', $from.' 00:00:00')
			->whereCreatedAt('<=', $to.' 23:59:59')
			->group(UsageLib::getStepSql('createdAt', $step))
			->getCollection();

		$steps = [];

		foreach($cComment as $eComment) {
			$steps[$eComment['step']] = $eComment['count'];
		}

		return $steps;

	}

	/**
	 * Les nouveaux messages des formations du groupe, le plus récent d'abord.
	 */
	public static function getNewByStructure(\structure\Structure $eStructure): \Collection {

		return self::whereStructure($eStructure)
			->select(Comment::getInboxSelection())
			->whereReadAt(NULL)
			->sort(['id' => SORT_DESC])
			->getCollection();

	}

	/**
	 * Les messages lus des formations du groupe, le plus récent d'abord, à partir de celui qui précède
	 * `$before` : la page suivante reprend sous le dernier montré, et un message lu entre-temps ne
	 * décale pas la suite.
	 *
	 * Une réponse de l'équipe n'y a pas de ligne : elle se lit en filet sous son commentaire
	 * (`fillTeamAnswers()`). Elle se reconnait à `TEAM_ANSWER`, seule trace en base de qui était de
	 * l'équipe au moment d'écrire.
	 */
	public static function getReadByStructure(\structure\Structure $eStructure, ?int $before, int $number): \Collection {

		return self::whereStructure($eStructure)
			->select(Comment::getInboxSelection())
			->whereReadAt('!=', NULL)
			->where('parent IS NULL OR NOT ('.self::TEAM_ANSWER.')')
			->whereId('<', $before, if: $before !== NULL)
			->sort(['id' => SORT_DESC])
			->getCollection(0, $number);

	}

	public static function countNewByStructure(\structure\Structure $eStructure): int {

		return self::whereStructure($eStructure)
			->whereReadAt(NULL)
			->count();

	}

	/**
	 * Les messages des formations du groupe, formations et messages dans la base commune.
	 */
	private static function whereStructure(\structure\Structure $eStructure): CommentModel {

		return Comment::model()->whereCourse('IN', Course::model()
			->select('id')
			->whereCommunity($eStructure)
			->getSql());

	}

	/**
	 * Pose `isTeam` sur chaque message : écrit par l'équipe du groupe qui porte la formation, ou par
	 * un administrateur de Ouvretaferme pour une formation de l'équipe.
	 */
	public static function fillTeam(Course $eCourse, \Collection $cComment): void {

		if($eCourse->isTeam()) {

			foreach($cComment as $eComment) {
				$eComment['isTeam'] = $eComment['createdBy']->isAdmin();
			}

			return;

		}

		self::fillTeamByStructure($eCourse['community'], $cComment);

	}

	/**
	 * L'équipe est celle de `Member::isStaff()`, qui écrit les formations du groupe. Les membres
	 * viennent de la liste du groupe, qui borne déjà le statut du membre et celui du groupe.
	 */
	public static function fillTeamByStructure(\structure\Structure $eStructure, \Collection $cComment): void {

		if($cComment->empty()) {
			return;
		}

		$staff = \structure\MemberLib::getByStructure($eStructure, index: NULL)
			->find(fn(\structure\Member $eMember) => $eMember->isStaff())
			->getColumnCollection('user', 'user')
			->getIds();

		foreach($cComment as $eComment) {
			$eComment['isTeam'] = in_array($eComment['createdBy']['id'], $staff);
		}

	}

	/**
	 * Le numéro de la leçon de chaque commentaire, celui que le programme de sa formation lui donne
	 * (`ChapterLib::getByCourse()`), pour la nommer comme partout ailleurs : « 1. Lire un sachet de
	 * graines ». Le numéro dépend de la place de la leçon dans tout le programme, donc un programme
	 * est lu par formation présente, pas par commentaire, et une fois pour toutes les listes de
	 * l'écran.
	 */
	public static function fillLessonNumber(\Collection ...$cCommentLists): void {

		$courses = [];

		foreach($cCommentLists as $cComment) {
			foreach($cComment as $eComment) {
				$courses[$eComment['lesson']['course']['id']] = $eComment['lesson']['course'];
			}
		}

		$programs = array_map(fn(Course $eCourse) => ChapterLib::getByCourse($eCourse), $courses);

		foreach($cCommentLists as $cComment) {
			foreach($cComment as $eComment) {
				ChapterLib::locate($programs[$eComment['lesson']['course']['id']], $eComment['lesson']);
			}
		}

	}

	/**
	 * Le message de l'équipe est lu dès sa création, d'une seule heure pour les deux dates, ce que lit
	 * `TEAM_ANSWER`. Au premier niveau, il s'adresse à tout le fil et vaut lecture des
	 * nouveaux messages de sa leçon ; en réponse, il ne vaut lecture que de son commentaire, les autres
	 * restent à lire. Il n'y a pas d'API des commentaires, l'auteur est donc le compte connecté que
	 * pèse `canModerate()`.
	 */
	public static function create(Comment $e): void {

		$e->expects(['lesson' => ['course']]);

		// Un message construit sans passer par `build()` est de premier niveau.
		$e['parent'] ??= new Comment();

		$e['course'] = $e['lesson']['course'];

		if($e->canModerate() === FALSE) {
			parent::create($e);
			NotificationLib::commented($e);
			return;
		}

		$e['createdAt'] = \Clock::datetime();
		$e['readAt'] = $e['createdAt'];
		$e['readBy'] = \user\ConnectionLib::getOnline();

		Comment::model()->beginTransaction();

			if($e['parent']->empty()) {
				self::readByLesson($e['lesson']);
			} else {
				self::read($e['parent']);
			}

			parent::create($e);

		Comment::model()->commit();

	}

	/**
	 * `readAt IS NULL` garde le premier lecteur : un second geste ne le réécrit pas.
	 */
	public static function read(Comment $e): void {

		$e['readAt'] = \Clock::datetime();
		$e['readBy'] = \user\ConnectionLib::getOnline();

		Comment::model()
			->select(['readAt', 'readBy'])
			->whereReadAt(NULL)
			->update($e);

	}

	/**
	 * La ligne d'un commentaire telle que la boite la dessine, relue après un geste qui ne redessine
	 * qu'elle.
	 */
	public static function getForInbox(\structure\Structure $eStructure, Comment $e): Comment {

		$eComment = self::getById($e['id'], Comment::getInboxSelection());

		$cComment = new \Collection([$eComment]);

		self::fillTeamByStructure($eStructure, $cComment);
		self::fillLessonNumber($cComment);
		self::fillTeamAnswers($eStructure, $cComment);

		return $eComment;

	}

	public static function readAllByStructure(\structure\Structure $eStructure): void {

		// Écriture de masse, sur le modèle de `NotificationLib::readAll()` : marquer lu n'a aucune
		// règle par message à rejouer, et un groupe peut en compter des centaines. Un tableau de
		// valeurs, parce qu'un élément porteur d'un id réduirait l'écriture à sa seule ligne
		// (`Module::getUpdateCondition()`).
		self::whereStructure($eStructure)
			->whereReadAt(NULL)
			->update([
				'readAt' => \Clock::datetime(),
				'readBy' => \user\ConnectionLib::getOnline()
			]);

	}

	private static function readByLesson(Lesson $eLesson): void {

		// Écriture de masse en tableau, pour la même raison que `readAllByStructure()`.
		Comment::model()
			->whereLesson($eLesson)
			->whereReadAt(NULL)
			->update([
				'readAt' => \Clock::datetime(),
				'readBy' => \user\ConnectionLib::getOnline()
			]);

	}

	public static function update(Comment $e, array $properties = []): void {

		$e['updatedAt'] = new \Sql('NOW()');
		$properties[] = 'updatedAt';

		parent::update($e, $properties);

	}

	/**
	 * Un commentaire emporte ses réponses, lues dans la transaction qui les supprime.
	 */
	public static function delete(Comment $e): void {

		Comment::model()->beginTransaction();

			$cCommentAnswer = Comment::model()
				->select('id')
				->whereParent($e)
				->getCollection();

			foreach($cCommentAnswer as $eCommentAnswer) {
				self::delete($eCommentAnswer);
			}

			parent::delete($e);

		Comment::model()->commit();

	}

	/**
	 * Les messages d'une leçon qu'on supprime, dans la transaction que tient
	 * `LessonLib::deleteWithCourse()`. Les commentaires de premier niveau suffisent, chacun emporte
	 * ses réponses.
	 */
	public static function deleteByLesson(Lesson $eLesson): void {

		$cComment = Comment::model()
			->select('id')
			->whereLesson($eLesson)
			->whereParent(NULL)
			->getCollection();

		foreach($cComment as $eComment) {
			self::delete($eComment);
		}

	}

}
?>
