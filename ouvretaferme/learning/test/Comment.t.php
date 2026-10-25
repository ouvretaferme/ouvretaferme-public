<?php
namespace learning;

require_once __DIR__.'/LearningFixture.t.php';

/**
 * Le fil d'une leçon : qui le lit, qui écrit, modifie et retire un message, la marque de l'équipe
 * pédagogique, ce que le schéma refuse, ce que les suppressions emportent, et le compte par leçon
 * du programme.
 *
 * Le stagiaire est le lecteur fictif de la fixture, rangé dans une promotion du groupe à qui la
 * formation est ouverte depuis aujourd'hui. L'utilisateur 1 administre le groupe et Ouvretaferme.
 */
class CommentTest extends LearningFixtureTest {

	/** Réseau du décor commun, dont l'utilisateur 1 est conseiller le temps du test */
	const COMMUNITY_NETWORK = \structure\FixtureAiLib::G3;

	protected function getMemberships(): array {
		return parent::getMemberships() + [
			self::COMMUNITY_NETWORK => 'advisor',
		];
	}

	/**
	 * Le stagiaire lit le fil et écrit ; il modifie et retire les siens seulement. L'équipe du groupe
	 * retire tout, mais ne réécrit pas le message d'un autre. Un visiteur ne lit rien, pas même sur
	 * une formation qui se lit sans compte, et un compte hors des promotions non plus.
	 */
	public function testRights(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eLesson] = $this->createSharedLesson(Course::PUBLISHED);

		$this->actAs($this->eUserAdmin);
		$eCommentAdmin = $this->write($eLesson, 'Réponse de l\'équipe');

		$this->actAs($this->eUserReader);
		$eCommentReader = $this->write($eLesson, 'Question du stagiaire');

		$this->assertTrue($this->newComment($eLesson)->assertCreate(), 'stagiaire, écriture');
		$this->assertTrue($eCommentAdmin->assertRead(), 'stagiaire, lecture du message de l\'équipe');
		$this->assertTrue($eCommentReader->assertUpdate(), 'stagiaire, modification du sien');
		$this->assertTrue($eCommentReader->assertDelete(), 'stagiaire, suppression du sien');
		$this->assertFalse($eCommentAdmin->assertUpdate(), 'stagiaire, modification d\'un autre');
		$this->assertFalse($eCommentAdmin->assertDelete(), 'stagiaire, suppression d\'un autre');

		$this->actAs($this->eUserAdmin);

		$this->assertFalse($eCommentReader->assertUpdate(), 'équipe, modification du message d\'un stagiaire');
		$this->assertTrue($eCommentReader->assertDelete(), 'équipe, suppression du message d\'un stagiaire');
		$this->assertTrue($eCommentAdmin->assertUpdate(), 'équipe, modification du sien');

		$this->actAs(new \user\User());

		$this->assertFalse($eCommentAdmin->assertRead(), 'visiteur, lecture');
		$this->assertFalse($this->newComment($eLesson)->assertCreate(), 'visiteur, écriture');

		$this->actAs($this->eUserAdmin);

		$eLessonOpen = $this->getLesson($this->createLesson($this->createChapter($this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::ACCEPTED), 'Module'), 'Leçon en lecture libre', 5)['id']);

		$this->actAs(new \user\User());

		$this->assertTrue($eLessonOpen->assertRead(), 'visiteur, la leçon en lecture libre se lit');
		$this->assertFalse($this->newComment($eLessonOpen)->assertRead(), 'visiteur, son fil ne se lit pas');

		$this->actAs($this->eUserAdmin);

		$eLessonClosed = $this->getLesson($this->createLesson($this->createChapter($this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), Course::PUBLISHED, Course::PRIVATE), 'Module'), 'Leçon sans partage', 5)['id']);

		$this->actAs($this->eUserReader);

		$this->assertFalse($this->newComment($eLessonClosed)->assertRead(), 'compte hors des promotions, lecture');
		$this->assertFalse($this->newComment($eLessonClosed)->assertCreate(), 'compte hors des promotions, écriture');

	}

	/**
	 * Un brouillon n'a pas de fil : ni son auteur, qui le relit, ni personne n'y écrit.
	 */
	public function testDraft(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eLesson] = $this->createSharedLesson(Course::DRAFT);

		$this->actAs($this->eUserAdmin);

		$this->assertTrue($eLesson->assertRead(), 'l\'équipe relit la leçon du brouillon');
		$this->assertFalse($this->newComment($eLesson)->assertRead(), 'mais pas de fil');
		$this->assertFalse($this->newComment($eLesson)->assertCreate(), 'ni d\'écriture');

		$this->assertEquals(
			[\NotExpectedAction::class, NULL],
			$this->executePage('learning/comment:doCreate', ['lesson' => (string)$eLesson['id'], 'text' => 'Sur un brouillon']),
			'le POST forgé est refusé'
		);

		$this->assertEquals(0, Comment::model()->whereLesson($eLesson)->count(), 'et n\'écrit rien');

	}

	/**
	 * La marque va aux administrateurs du groupe qui porte la formation, et pour une formation de
	 * l'équipe, aux administrateurs de Ouvretaferme.
	 */
	public function testFillTeam(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eLesson, $eCourse] = $this->createSharedLesson(Course::PUBLISHED);

		$this->actAs($this->eUserAdmin);
		$this->write($eLesson, 'Réponse de l\'équipe');

		$this->actAs($this->eUserReader);
		$this->write($eLesson, 'Question du stagiaire');

		$cComment = CommentLib::getByLesson($eLesson);
		CommentLib::fillTeam($eCourse, $cComment);

		$this->assertEquals([TRUE, FALSE], array_values($cComment->getColumn('isTeam')), 'groupe : l\'administrateur, puis le stagiaire, dans l\'ordre du fil');

		$this->actAs($this->eUserAdmin);

		$eCourseTeam = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::PRIVATE);
		$eLessonTeam = $this->getLesson($this->createLesson($this->createChapter($eCourseTeam, 'Module'), 'Leçon de l\'équipe', 5)['id']);

		$this->write($eLessonTeam, 'Réponse de Ouvretaferme');

		$this->actAs($this->eUserReader);
		$this->write($eLessonTeam, 'Question d\'un lecteur');

		$cComment = CommentLib::getByLesson($eLessonTeam);
		CommentLib::fillTeam($eCourseTeam, $cComment);

		$this->assertEquals([TRUE, FALSE], array_values($cComment->getColumn('isTeam')), 'équipe : l\'administrateur de Ouvretaferme, puis le lecteur');

	}

	/**
	 * Le schéma borne le texte de 1 à 2 000 caractères, et le refus porte le nom que l'alerte traduit.
	 */
	public function testBuild(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eLesson] = $this->createSharedLesson(Course::PUBLISHED);

		$this->actAs($this->eUserReader);

		[, $max] = Comment::model()->getPropertyRange('text');

		foreach(['' => FALSE, str_repeat('a', $max) => TRUE, str_repeat('a', $max + 1) => FALSE] as $text => $accepted) {

			$fw = new \FailWatch();

			$this->newComment($eLesson)->build(['text'], ['text' => $text], new \PropertyList('create'));

			$this->assertEquals($accepted ? [] : [['Comment::text.check', 'text']], $this->getFails($fw), strlen($text).' caractères');
			$this->assertTrue(AlertUi::getError('Comment::text.check') !== NULL, 'le refus a son message');

		}

	}

	/**
	 * Les routes du fil, jouées comme le panneau et la corbeille les postent.
	 */
	public function testPage(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eLesson] = $this->createSharedLesson(Course::PUBLISHED);

		$this->actAs($this->eUserReader);

		$this->assertEquals(
			[\ReloadAction::class, NULL],
			$this->executePage('learning/comment:doCreate', ['lesson' => (string)$eLesson['id'], 'text' => 'Question du stagiaire']),
			'le stagiaire publie'
		);

		$eComment = CommentLib::getByLesson($eLesson)->first();

		$this->assertEquals($eLesson['course']['id'], Comment::model()->select('course')->whereId($eComment)->get()['course']['id'], 'la formation est posée depuis la leçon');

		$this->assertEquals(
			[\ReloadAction::class, NULL],
			$this->executePage('learning/comment:doUpdate', ['id' => (string)$eComment['id'], 'text' => 'Question reformulée']),
			'et modifie'
		);

		$eCommentUpdated = CommentLib::getByLesson($eLesson)->first();

		$this->assertEquals('Question reformulée', $eCommentUpdated['text'], 'le texte est modifié');
		$this->assertTrue($eCommentUpdated['updatedAt'] !== NULL, 'et daté');

		$this->actAs($this->eUserAdmin);

		$this->assertEquals(
			[\NotAllowedAction::class, NULL],
			$this->executePage('learning/comment:doUpdate', ['id' => (string)$eComment['id'], 'text' => 'Réécrit par l\'équipe']),
			'l\'équipe ne réécrit pas le message d\'un stagiaire'
		);

		$this->assertEquals(
			[\ReloadAction::class, NULL],
			$this->executePage('learning/comment:doDelete', ['id' => (string)$eComment['id']]),
			'mais le retire'
		);

		$this->assertEquals(0, Comment::model()->whereLesson($eLesson)->count(), 'le message est retiré');

	}

	/**
	 * Une réponse se range sous son commentaire, du plus ancien au plus récent, et le fil compte
	 * commentaires et réponses. « Répondre » ne se pose que sur un commentaire de premier niveau, et
	 * une réponse n'a ni vignette ni roue.
	 */
	public function testAnswer(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eLesson, $eCourse] = $this->createSharedLesson(Course::PUBLISHED);

		$this->actAs($this->eUserReader);
		$eCommentQuestion = $this->write($eLesson, 'Question du stagiaire');
		$eCommentOther = $this->write($eLesson, 'Autre question');

		$this->actAs($this->eUserAdmin);
		$eCommentAnswer = $this->write($eLesson, 'Réponse de l\'équipe', $eCommentQuestion);

		$this->actAs($this->eUserReader);
		$eCommentThanks = $this->write($eLesson, 'Merci', $eCommentQuestion);

		$this->assertEquals($eCommentQuestion['id'], $eCommentAnswer['parent']['id'], 'la réponse porte son commentaire');
		$this->assertTrue($eCommentQuestion['parent']->empty(), 'un commentaire de premier niveau n\'en porte pas');

		$this->assertTrue($eCommentQuestion->assertAnswer(), 'on répond à un commentaire de premier niveau');
		$this->assertFalse($eCommentAnswer->assertAnswer(), 'pas à une réponse');

		$cComment = CommentLib::getByLesson($eLesson);
		CommentLib::fillTeam($eCourse, $cComment);
		CommentLib::fillAnswers($cComment);

		$cCommentFirst = $cComment->find(fn(Comment $eComment) => $eComment['parent']->empty());

		$this->assertEquals([$eCommentQuestion['id'], $eCommentOther['id']], $cCommentFirst->getIds(), 'le fil ne garde que les premiers niveaux');
		$this->assertEquals([$eCommentAnswer['id'], $eCommentThanks['id']], $cCommentFirst->first()['cCommentAnswer']->getIds(), 'les réponses sous leur commentaire, la plus ancienne d\'abord');
		$this->assertTrue($cCommentFirst->last()['cCommentAnswer']->empty(), 'un commentaire sans réponse n\'en porte aucune');

		$this->assertEquals(4, $cComment->count(), 'le fil compte commentaires et réponses');

		$eLesson['eCommentNew'] = $this->newComment($eLesson);
		$eLesson['cComment'] = $cCommentFirst;
		$eLesson['comments'] = $cComment->count();

		$thread = new CommentUi()->getThread($eLesson);

		$this->assertEquals(1, substr_count($thread, '/learning/comment:create?lesson='.$eLesson['id'].'&parent='.$eCommentQuestion['id'].'"'), '« Répondre » sur le premier commentaire');
		$this->assertEquals(2, substr_count($thread, '&parent='), 'et sur le second, pas sur les réponses');
		$this->assertEquals(2, substr_count($thread, 'comment:doDelete'), 'une roue par commentaire du stagiaire, aucune sur les réponses');

	}

	/**
	 * Le fil n'a qu'un niveau, et un parent se lit dans la leçon : une réponse à une réponse, à un
	 * commentaire d'une autre leçon ou à un commentaire retiré entre-temps est refusée, sous le nom
	 * que l'alerte traduit.
	 */
	public function testAnswerRefused(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eLesson] = $this->createSharedLesson(Course::PUBLISHED);

		$this->actAs($this->eUserAdmin);
		$eLessonOther = $this->getLesson($this->createLesson($this->getChapter($eLesson['chapter']['id']), 'Leçon voisine', 10)['id']);

		$this->actAs($this->eUserReader);
		$eCommentQuestion = $this->write($eLesson, 'Question du stagiaire');
		$eCommentAnswer = $this->write($eLesson, 'Précision', $eCommentQuestion);
		$eCommentOther = $this->write($eLessonOther, 'Question sur la leçon voisine');
		$eCommentDeleted = $this->write($eLesson, 'Question retirée');

		CommentLib::delete($eCommentDeleted);

		$this->assertTrue(AlertUi::getError('Comment::parent.thread') !== NULL, 'le refus a son message');

		foreach([
			'une réponse à une réponse' => $eCommentAnswer,
			'un commentaire d\'une autre leçon' => $eCommentOther,
			'un commentaire retiré' => $eCommentDeleted,
		] as $case => $eCommentParent) {

			$this->assertEquals(
				[\FailAction::class, 'Comment::parent.thread'],
				$this->executePage('learning/comment:doCreate', ['lesson' => (string)$eLesson['id'], 'parent' => (string)$eCommentParent['id'], 'text' => 'Réponse forgée']),
				$case.' : le POST est refusé'
			);

			$this->assertEquals(
				[\NotExistsAction::class, NULL],
				$this->executePage('learning/comment:create', ['lesson' => (string)$eLesson['id'], 'parent' => (string)$eCommentParent['id']], 'GET'),
				$case.' : le panneau ne s\'ouvre pas'
			);

		}

		$this->assertTrue(AlertUi::getError('Comment::parent.check') !== NULL, 'le refus du schéma a son message');

		$this->assertEquals(
			[\FailAction::class, 'Comment::parent.check'],
			$this->executePage('learning/comment:doCreate', ['lesson' => (string)$eLesson['id'], 'parent' => 'abc', 'text' => 'Réponse forgée']),
			'un parent qui n\'est pas un identifiant est refusé par le schéma'
		);

		$this->assertEquals(2, Comment::model()->whereLesson($eLesson)->count(), 'rien n\'est écrit');

		$this->assertEquals(
			[\ReloadAction::class, NULL],
			$this->executePage('learning/comment:doCreate', ['lesson' => (string)$eLesson['id'], 'parent' => (string)$eCommentQuestion['id'], 'text' => 'Réponse légitime']),
			'la réponse à un commentaire de la leçon passe'
		);

		$this->assertEquals(2, Comment::model()->whereParent($eCommentQuestion)->count(), 'et se range sous lui');

	}

	/**
	 * Le panneau nomme avant l'envoi l'équipe qui lira : celle du réseau qui porte la formation, ou
	 * celle de Ouvretaferme pour une formation de l'équipe. En réponse, il cite le
	 * commentaire et poste son identifiant, avec le lieu d'où il s'ouvre.
	 */
	public function testPanel(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCommunity = $this->getCommunity(self::COMMUNITY_NETWORK);

		$this->assertEquals(\structure\Structure::NETWORK, $eCommunity['communityType'], 'le décor est un réseau');

		$eLesson = $this->getLesson($this->createLesson($this->createChapter($this->createCourse($eCommunity, Course::PUBLISHED, Course::PRIVATE), 'Module'), 'Tailler la vigne', 5)['id']);
		$eCommentQuestion = $this->write($eLesson, "Question <b>du réseau</b>\nsur deux lignes");

		$data = $this->executeView('learning/comment:create', ['lesson' => (string)$eLesson['id'], 'parent' => (string)$eCommentQuestion['id'], 'origin' => 'inbox']);
		$panel = new CommentUi()->create($data->e, $data->origin);

		$this->assertEquals('panel-learning-comment-answer', $panel->id, 'réponse : son panneau');
		$this->assertTrue(str_contains($panel->body, 'Question &lt;b&gt;du réseau&lt;/b&gt;<br />'."\n".'sur deux lignes'), 'le commentaire est cité, encodé, avec ses retours à la ligne');
		$this->assertTrue(str_contains($panel->body, encode($eCommunity['name'])), 'l\'encart nomme l\'équipe du réseau');
		$this->assertEquals(1, preg_match('/<input[^>]*name="parent"[^>]*value="'.$eCommentQuestion['id'].'"/', $panel->body), 'le champ caché porte le commentaire');
		$this->assertEquals(1, preg_match('/<input[^>]*name="origin"[^>]*value="inbox"/', $panel->body), 'et le lieu d\'où le panneau s\'ouvre');
		$this->assertTrue(str_contains($panel->subTitle, '1. Tailler la vigne'), 'depuis la boite, la leçon se nomme avec son numéro');

		$data = $this->executeView('learning/comment:create', ['lesson' => (string)$eLesson['id']]);
		$panel = new CommentUi()->create($data->e, $data->origin);

		$this->assertEquals('panel-learning-comment-create', $panel->id, 'commentaire : son panneau');
		$this->assertFalse(str_contains($panel->body, 'Question &lt;b&gt;du réseau&lt;/b&gt;'), 'sans citation');
		$this->assertEquals(1, preg_match('/<input[^>]*name="parent" type="hidden"\/>/', $panel->body), 'le champ caché du parent ne porte aucune valeur');
		$this->assertEquals(1, preg_match('/<input[^>]*name="origin"[^>]*value=""/', $panel->body), 'comme celui du lieu');

		$eLessonTeam = $this->getLesson($this->createLesson($this->createChapter($this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::ACCEPTED), 'Module'), 'Leçon de l\'équipe', 5)['id']);

		$data = $this->executeView('learning/comment:create', ['lesson' => (string)$eLessonTeam['id']]);
		$panel = new CommentUi()->create($data->e, $data->origin);

		$this->assertTrue(str_contains($panel->body, 'Ouvretaferme') and str_contains($panel->body, encode($eCommunity['name'])) === FALSE, 'une formation de l\'équipe nomme celle de Ouvretaferme');

	}

	/**
	 * Un commentaire emporte ses réponses, quel qu'en soit l'auteur, et laisse celles des autres.
	 */
	public function testAnswerCascade(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eLesson] = $this->createSharedLesson(Course::PUBLISHED);

		$this->actAs($this->eUserReader);
		$eCommentQuestion = $this->write($eLesson, 'Question du stagiaire');
		$eCommentKept = $this->write($eLesson, 'Question qui reste');
		$this->write($eLesson, 'Précision', $eCommentQuestion);
		$eCommentKeptAnswer = $this->write($eLesson, 'Précision qui reste', $eCommentKept);

		$this->actAs($this->eUserAdmin);
		$this->write($eLesson, 'Réponse de l\'équipe', $eCommentQuestion);

		$this->actAs($this->eUserReader);

		$this->assertEquals(
			[\ReloadAction::class, NULL],
			$this->executePage('learning/comment:doDelete', ['id' => (string)$eCommentQuestion['id']]),
			'le stagiaire retire son commentaire'
		);

		$this->assertEquals(
			[$eCommentKept['id'], $eCommentKeptAnswer['id']],
			Comment::model()->select('id')->whereLesson($eLesson)->sort('id')->getCollection()->getIds(),
			'ses réponses partent avec lui, celle de l\'équipe comprise, et l\'autre commentaire garde la sienne'
		);

		$this->actAs($this->eUserAdmin);

		// Une formation publiée garde une leçon : la voisine laisse supprimer celle du fil.
		$this->createLesson($this->getChapter($eLesson['chapter']['id']), 'Leçon qui reste', 10);

		$fw = new \FailWatch();
		LessonLib::delete($this->getLesson($eLesson['id']));
		$this->assertEquals([], $this->getFails($fw), 'leçon supprimée');

		$this->assertEquals(0, Comment::model()->whereLesson($eLesson)->count(), 'la leçon emporte commentaires et réponses');

	}

	/**
	 * La leçon emporte son fil, la formation ceux de ses leçons, et le programme compte les
	 * messages de chaque leçon.
	 */
	public function testDeleteAndCount(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eLesson, $eCourse] = $this->createSharedLesson(Course::PUBLISHED);

		$this->actAs($this->eUserAdmin);

		$eLessonOther = $this->getLesson($this->createLesson($this->getChapter($eLesson['chapter']['id']), 'Leçon qui reste', 10)['id']);

		$this->write($eLesson, 'Un');
		$this->write($eLesson, 'Deux');
		$this->write($eLessonOther, 'Trois');

		$cChapter = ChapterLib::getByCourse($eCourse);
		CommentLib::fillCountByCourse($eCourse, $cChapter);

		$this->assertEquals(3, $eCourse['comments'], 'la formation compte trois messages');
		$cLesson = $cChapter->first()['cLesson'];

		$this->assertEquals([$eLesson['id'] => 2, $eLessonOther['id'] => 1], array_combine($cLesson->getIds(), $cLesson->getColumn('comments')), 'deux sur la première leçon, un sur la seconde');

		$fw = new \FailWatch();
		LessonLib::delete($this->getLesson($eLesson['id']));
		$this->assertFalse($fw->ko(), 'leçon supprimée');

		$this->assertEquals(0, Comment::model()->whereLesson($eLesson)->count(), 'la leçon emporte son fil');
		$this->assertEquals(1, Comment::model()->whereCourse($eCourse)->count(), 'et laisse celui de l\'autre leçon');

		CourseLib::delete($this->getCourse($eCourse['id']));

		$this->assertEquals(0, Comment::model()->whereCourse($eCourse)->count(), 'la formation emporte le fil de ses leçons');

	}

	/**
	 * Une formation du groupe, une leçon, et une promotion où le stagiaire est rangé, ouverte depuis
	 * aujourd'hui.
	 *
	 * @return array{0: Lesson, 1: Course}
	 */
	private function createSharedLesson(string $status): array {

		$this->actAs($this->eUserAdmin);

		$eCommunity = $this->getCommunity(self::COMMUNITY_EDUCATION);

		$eCourse = $this->createCourse($eCommunity, Course::PUBLISHED, Course::PRIVATE);
		$eLesson = $this->createLesson($this->createChapter($eCourse, 'Module'), 'Construire son assolement', 13);

		$eSection = $this->createSection($eCommunity, 'zzTest promotion du fil', [$this->eUserReader]);
		$this->share($eCourse, [$eSection['id'] => [$this->day(0), NULL]]);

		if($status !== Course::PUBLISHED) {
			$eCourse['status'] = $status;
			CourseLib::update($eCourse, ['status']);
		}

		return [$this->getLesson($eLesson['id']), $this->getCourse($eCourse['id'])];

	}

	private function newComment(Lesson $eLesson): Comment {
		return new Comment(['lesson' => $eLesson]);
	}

	/**
	 * Un message écrit comme la page l'écrit : elle pose le parent lu dans la leçon, `build()` pèse
	 * celui qui est posté.
	 */
	private function write(Lesson $eLesson, string $text, Comment $eCommentParent = new Comment()): Comment {

		$parent = $eCommentParent->empty() ? NULL : $eCommentParent['id'];

		$eComment = $this->newComment($eLesson);
		$eComment['eCommentParent'] = CommentLib::getParentByLesson($eLesson, $parent);

		$fw = new \FailWatch();

		$eComment->build(CommentLib::getPropertiesCreate(), ['parent' => (string)$parent, 'text' => $text], new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('message refusé : '.json_encode($this->getFails($fw))));

		CommentLib::create($eComment);

		return CommentLib::getById($eComment['id']);

	}

}
?>
