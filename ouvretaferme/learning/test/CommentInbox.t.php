<?php
namespace learning;

require_once __DIR__.'/LearningFixture.t.php';

/**
 * Les commentaires d'un groupe pour ses formateurs (L3g-1) : ce qui est lu d'emblée, ce qui se
 * marque lu, un par un, par la réponse ou tous ensemble, ce que la page liste et ce qu'elle compte.
 *
 * L'équipe est celle du groupe au sens de `Member::isStaff()` : les gestes se jouent sous un compte
 * enseignant, écrit dans la transaction du test, administrateur du groupe comme tout enseignant
 * depuis le 26/09/2026, mais pas de Ouvretaferme. L'utilisateur 1 n'agit que pour écrire le décor.
 */
class CommentInboxTest extends LearningFixtureTest {

	private \user\User $eUserTeacher;

	/**
	 * Le message de l'équipe est lu dès sa création, par son auteur ; celui d'un stagiaire reste nouveau.
	 */
	public function testBornRead(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eLesson] = $this->createDecor();

		$this->assertFalse($this->eUserTeacher->isAdmin(), 'l\'enseignant n\'est pas administrateur de Ouvretaferme');

		$this->actAs($this->eUserTeacher);
		$eCommentTeacher = $this->write($eLesson, 'Réponse de l\'enseignant');

		$this->assertTrue($eCommentTeacher['readAt'] !== NULL, 'le message de l\'équipe est lu dès sa création');
		$this->assertEquals($this->eUserTeacher['id'], $eCommentTeacher['readBy']['id'], 'par son auteur');

		$this->actAs($this->eUserReader);
		$eCommentReader = $this->write($eLesson, 'Question du stagiaire');

		$this->assertEquals(NULL, $eCommentReader['readAt'], 'le message du stagiaire reste nouveau');
		$this->assertTrue($eCommentReader['readBy']->empty(), 'sans lecteur');

	}

	/**
	 * Un message de premier niveau de l'équipe marque lus les nouveaux messages de sa leçon, et d'elle
	 * seule : il s'adresse à tout le fil.
	 */
	public function testReplyReadsLesson(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eLesson, $eLessonOther] = $this->createDecor();

		$this->actAs($this->eUserReader);
		$eCommentFirst = $this->write($eLesson, 'Première question');
		$eCommentSecond = $this->write($eLesson, 'Seconde question');
		$eCommentOther = $this->write($eLessonOther, 'Question sur l\'autre leçon');

		$this->actAs($this->eUserTeacher);
		$this->write($eLesson, 'Réponse aux deux');

		foreach([$eCommentFirst, $eCommentSecond] as $eComment) {
			$this->assertEquals($this->eUserTeacher['id'], $this->reload($eComment)['readBy']['id'], 'la réponse marque lu « '.$eComment['text'].' »');
		}

		$this->assertEquals(NULL, $this->reload($eCommentOther)['readAt'], 'le message de l\'autre leçon reste nouveau');

		$this->actAs($this->eUserReader);
		$this->write($eLessonOther, 'Relance du stagiaire');

		$this->assertEquals(NULL, $this->reload($eCommentOther)['readAt'], 'un stagiaire qui écrit ne marque rien');

	}

	/**
	 * Marquer lu garde le premier lecteur : un second geste, d'un autre membre de l'équipe, ne le
	 * réécrit pas.
	 */
	public function testRead(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eLesson] = $this->createDecor();

		$this->actAs($this->eUserReader);
		$eComment = $this->write($eLesson, 'Question du stagiaire');

		$this->actAs($this->eUserTeacher);
		CommentLib::read($this->reload($eComment));

		$eCommentRead = $this->reload($eComment);

		$this->assertTrue($eCommentRead['readAt'] !== NULL, 'lu');
		$this->assertEquals($this->eUserTeacher['id'], $eCommentRead['readBy']['id'], 'par l\'enseignant');

		$this->actAs($this->eUserAdmin);
		CommentLib::read($this->reload($eComment));

		$this->assertEquals($this->eUserTeacher['id'], $this->reload($eComment)['readBy']['id'], 'le second geste garde le premier lecteur');

	}

	/**
	 * « Tout marquer comme lu » ne touche que les formations du groupe.
	 */
	public function testReadAllByStructure(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eLesson, $eLessonOther, $eCommunity] = $this->createDecor();

		$this->actAs($this->eUserAdmin);
		$eLessonTeam = $this->getLesson($this->createLesson($this->createChapter($this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::ACCEPTED), 'Module'), 'Leçon de l\'équipe', 5)['id']);

		$this->actAs($this->eUserReader);
		$this->write($eLesson, 'Un');
		$this->write($eLessonOther, 'Deux');
		$eCommentTeam = $this->write($eLessonTeam, 'Sur une formation de l\'équipe');

		$this->actAs($this->eUserTeacher);

		$this->assertEquals(2, CommentLib::countNewByStructure($eCommunity), 'deux nouveaux dans le groupe');

		CommentLib::readAllByStructure($eCommunity);

		$this->assertEquals(0, CommentLib::countNewByStructure($eCommunity), 'tous lus');
		$this->assertEquals(0, Comment::model()->whereCourse($eLesson['course'])->whereReadBy('!=', $this->eUserTeacher)->count(), 'par l\'enseignant');
		$this->assertEquals(NULL, $this->reload($eCommentTeam)['readAt'], 'le message d\'une formation hors du groupe reste nouveau');

	}

	/**
	 * La page liste les nouveaux du plus récent au plus ancien, puis les lus par pages, et compte les
	 * nouveaux. Le compte et les listes lisent le même périmètre.
	 */
	public function testLists(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eLesson, $eLessonOther, $eCommunity] = $this->createDecor();

		$this->actAs($this->eUserTeacher);

		$read = [];

		for($i = 0; $i < CommentLib::READ_PER_PAGE + 2; $i++) {
			$read[] = $this->write($eLesson, 'Message lu '.$i)['id'];
		}

		$this->actAs($this->eUserReader);
		$eCommentOld = $this->write($eLesson, 'Nouveau, le plus ancien');
		$eCommentRecent = $this->write($eLessonOther, 'Nouveau, le plus récent');

		$this->actAs($this->eUserTeacher);

		$cCommentNew = CommentLib::getNewByStructure($eCommunity);

		$this->assertEquals([$eCommentRecent['id'], $eCommentOld['id']], $cCommentNew->getIds(), 'les nouveaux, le plus récent d\'abord');
		$this->assertEquals(2, CommentLib::countNewByStructure($eCommunity), 'et leur compte');

		$cCommentRead = CommentLib::getReadByStructure($eCommunity, NULL, CommentLib::READ_PER_PAGE + 1);

		$this->assertTrue($cCommentRead->cut(CommentLib::READ_PER_PAGE), 'une page pleine en annonce d\'autres');
		$this->assertEquals(array_slice(array_reverse($read), 0, CommentLib::READ_PER_PAGE), $cCommentRead->getIds(), 'les lus, le plus récent d\'abord');

		$cCommentOlder = CommentLib::getReadByStructure($eCommunity, $cCommentRead->last()['id'], CommentLib::READ_PER_PAGE + 1);

		$this->assertFalse($cCommentOlder->cut(CommentLib::READ_PER_PAGE), 'la page suivante est la dernière');
		$this->assertEquals([$read[1], $read[0]], $cCommentOlder->getIds(), 'et reprend sous le dernier montré');

	}

	/**
	 * La marque va à l'équipe du groupe, un enseignant compris.
	 */
	public function testFillTeam(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eLesson, , , $eCourse] = $this->createDecor();

		$this->actAs($this->eUserTeacher);
		$this->write($eLesson, 'Réponse de l\'enseignant');

		$this->actAs($this->eUserReader);
		$this->write($eLesson, 'Question du stagiaire');

		$cComment = CommentLib::getByLesson($eLesson);
		CommentLib::fillTeam($eCourse, $cComment);

		$this->assertEquals([TRUE, FALSE], array_values($cComment->getColumn('isTeam')), 'l\'enseignant, puis le stagiaire');

	}

	/**
	 * Les routes : un stagiaire ne marque rien, l'enseignant marque un message, y compris sur une
	 * formation revenue au brouillon, puis tout le groupe d'un geste.
	 */
	public function testPage(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eLesson, $eLessonOther, $eCommunity, $eCourse] = $this->createDecor();

		$this->actAs($this->eUserReader);
		$eComment = $this->write($eLesson, 'Question du stagiaire');
		$this->write($eLessonOther, 'Autre question');

		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('learning/comment:doRead', ['id' => (string)$eComment['id']]), 'le stagiaire ne marque pas lu');
		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('learning/community:doReadAll', ['id' => (string)$eCommunity['id']]), 'ni tout le groupe');
		$this->assertEquals(2, CommentLib::countNewByStructure($eCommunity), 'rien n\'est écrit');

		$this->actAs($this->eUserAdmin);
		$eCourse['status'] = Course::DRAFT;
		CourseLib::update($eCourse, ['status']);

		$this->actAs($this->eUserTeacher);

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('learning/comment:doRead', ['id' => (string)$eComment['id']]), 'l\'enseignant marque lu, formation au brouillon comprise');
		$this->assertEquals($this->eUserTeacher['id'], $this->reload($eComment)['readBy']['id'], 'le message est lu');

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/community:doReadAll', ['id' => (string)$eCommunity['id']]), 'puis tout le groupe');
		$this->assertEquals(0, CommentLib::countNewByStructure($eCommunity), 'plus rien de nouveau');

	}

	/**
	 * La page : le compteur et « Tout marquer comme lu » n'existent que s'il y a du nouveau, chaque
	 * nouveau porte son geste, et l'entrée ne pose jamais de compteur à zéro.
	 */
	public function testInbox(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eLesson, $eLessonOther, $eCommunity] = $this->createDecor();

		$this->actAs($this->eUserReader);
		$this->write($eLesson, 'Question du stagiaire');

		$this->actAs($this->eUserTeacher);
		$this->write($eLesson, 'Réponse de l\'enseignant');
		$this->write($eLessonOther, 'Consigne sur la seconde leçon');

		$this->actAs($this->eUserReader);
		$eCommentNew = $this->write($eLesson, 'Nouvelle question');

		$this->actAs($this->eUserTeacher);

		$cCommentNew = CommentLib::getNewByStructure($eCommunity);
		$cCommentRead = CommentLib::getReadByStructure($eCommunity, NULL, CommentLib::READ_PER_PAGE);

		CommentLib::fillTeamByStructure($eCommunity, $cCommentNew);
		CommentLib::fillTeamByStructure($eCommunity, $cCommentRead);
		CommentLib::fillLessonNumber($cCommentNew, $cCommentRead);
		CommentLib::fillTeamAnswers($eCommunity, $cCommentNew, $cCommentRead);

		$title = new CommentUi()->getInboxTitle($eCommunity, 1);
		$inbox = new CommentUi()->getInbox($eCommunity, $cCommentNew, $cCommentRead, FALSE);

		$this->assertEquals(2, count(array_filter([...$cCommentNew->getColumn('isTeam'), ...$cCommentRead->getColumn('isTeam')])), 'les deux messages du formateur portent sa marque');

		$numbers = [...$cCommentNew->makeArray(fn(Comment $eComment) => $eComment['lesson']['number']), ...$cCommentRead->makeArray(fn(Comment $eComment) => $eComment['lesson']['number'])];

		$this->assertEquals([1 => 3, 2 => 1], array_count_values($numbers), 'chaque leçon porte son numéro dans le programme, comme au suivi et dans l\'export');

		$this->assertTrue(str_contains($title, '>1<'), 'le compteur des nouveaux');
		$this->assertTrue(str_contains($title, 'community:doReadAll'), 'et « Tout marquer comme lu »');
		// `doRead` et `doCreate` ne redessinent que ces deux nœuds : sans leur id, la boite ne se met plus à jour.
		$this->assertTrue(str_contains($title, 'id="learning-inbox-counter"'), 'le compteur se désigne');
		$this->assertTrue(str_contains($title, 'id="learning-inbox-read-all"'), 'le geste se désigne');
		$this->assertEquals(1, substr_count($inbox, 'comment:doRead"'), 'un seul geste, sur le seul nouveau');
		$this->assertTrue(str_contains($inbox, 'id="learning-comment-'.$eCommentNew['id'].'"'), 'la ligne du nouveau se désigne');

		$titleNone = new CommentUi()->getInboxTitle($eCommunity, 0);

		$this->assertFalse(str_contains($titleNone, '>0<'), 'sans nouveau, pas de compteur');
		$this->assertFalse(str_contains($titleNone, 'doReadAll'), 'ni de geste');

		$this->assertTrue(str_contains(CommentUi::getInboxButton($eCommunity, 3), '>3<'), 'l\'entrée compte les nouveaux');
		$this->assertFalse(str_contains(CommentUi::getInboxButton($eCommunity, 0), '>0<'), 'et ne pose pas de zéro');

	}

	/**
	 * Deux commentaires non lus sous une même leçon : l'équipe répond à l'un, depuis la boite puis
	 * depuis la leçon, et l'autre reste à lire. La réponse ne vaut lecture que de son commentaire.
	 */
	public function testAnswerReadsParent(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eLesson, , $eCommunity] = $this->createDecor();

		foreach([
			'depuis la boite' => ['inbox', \ViewAction::class],
			'depuis la leçon' => [NULL, \ReloadAction::class],
		] as $case => [$origin, $action]) {

			$this->actAs($this->eUserReader);
			$eCommentAnswered = $this->write($eLesson, 'Question à laquelle l\'équipe répond');
			$eCommentWaiting = $this->write($eLesson, 'Question qui attend');

			$this->actAs($this->eUserTeacher);

			$this->assertEquals(2, CommentLib::countNewByStructure($eCommunity), $case.' : deux à lire');

			$input = ['lesson' => (string)$eLesson['id'], 'parent' => (string)$eCommentAnswered['id'], 'text' => 'Réponse de l\'enseignant'];

			if($origin !== NULL) {
				$input['origin'] = $origin;
			}

			$this->assertEquals([$action, NULL], $this->executePage('learning/comment:doCreate', $input, data: $data), $case.' : la réponse part');

			$this->assertEquals($this->eUserTeacher['id'], $this->reload($eCommentAnswered)['readBy']['id'], $case.' : le commentaire répondu est lu par qui répond');
			$this->assertEquals(NULL, $this->reload($eCommentWaiting)['readAt'], $case.' : l\'autre reste à lire');
			$this->assertEquals(1, CommentLib::countNewByStructure($eCommunity), $case.' : le compteur dit un');

			if($origin === 'inbox') {

				$this->assertEquals($eCommentAnswered['id'], $data->eComment['id'], 'la boite reçoit la ligne du commentaire répondu');
				$this->assertEquals(1, $data->commentsNew, 'et le compte des nouveaux');
				$this->assertTrue($data->eComment['readAt'] !== NULL, 'la ligne redessinée est lue');
				$this->assertEquals(1, $data->eComment['cCommentAnswer']->count(), 'la réponse en filet dessous');

			}

			// La scène suivante repart d'une boite vide.
			CommentLib::readAllByStructure($eCommunity);

		}

	}

	/**
	 * La boite range la réponse de l'équipe en filet sous son commentaire, sans en faire une ligne ni
	 * la compter, et montre la réponse d'un stagiaire comme un message à lire qui dit à qui il répond.
	 * « Répondre » ne se pose que sur un commentaire de premier niveau à lire.
	 */
	public function testInboxAnswers(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eLesson, $eLessonOther, $eCommunity] = $this->createDecor();

		$this->actAs($this->eUserReader);
		$eCommentAnswered = $this->write($eLesson, 'Question répondue');
		$eCommentWaiting = $this->write($eLessonOther, 'Question qui attend');

		$this->actAs($this->eUserTeacher);
		$eCommentTeam = $this->write($eLesson, 'Réponse de l\'enseignant', $eCommentAnswered);

		$this->assertTrue($eCommentTeam['readAt'] !== NULL, 'la réponse de l\'équipe est lue dès sa création');

		$this->actAs($this->eUserReader);
		$eCommentTrainee = $this->write($eLesson, 'Merci, et au printemps ?', $eCommentAnswered);

		$this->assertEquals(NULL, $eCommentTrainee['readAt'], 'celle d\'un stagiaire reste à lire');

		$this->actAs($this->eUserTeacher);

		$cCommentNew = CommentLib::getNewByStructure($eCommunity);
		$cCommentRead = CommentLib::getReadByStructure($eCommunity, NULL, CommentLib::READ_PER_PAGE);

		$this->assertEquals([$eCommentTrainee['id'], $eCommentWaiting['id']], $cCommentNew->getIds(), 'à lire : la réponse du stagiaire et le commentaire qui attend');
		$this->assertEquals([$eCommentAnswered['id']], $cCommentRead->getIds(), 'lus : le commentaire répondu, sans la réponse de l\'équipe');
		$this->assertEquals(2, CommentLib::countNewByStructure($eCommunity), 'le compte suit les lignes à lire');

		CommentLib::fillTeamByStructure($eCommunity, $cCommentNew);
		CommentLib::fillTeamByStructure($eCommunity, $cCommentRead);
		CommentLib::fillLessonNumber($cCommentNew, $cCommentRead);
		CommentLib::fillTeamAnswers($eCommunity, $cCommentNew, $cCommentRead);

		$ui = new CommentUi();

		$rowTrainee = $ui->getInboxRow($cCommentNew[0]);
		$rowWaiting = $ui->getInboxRow($cCommentNew[1]);
		$rowAnswered = $ui->getInboxRow($cCommentRead[0]);

		$this->assertEquals($eCommentAnswered['id'], $cCommentNew[0]['parent']['id'], 'la réponse du stagiaire dit à qui elle répond');
		$this->assertFalse(str_contains($rowTrainee, 'comment:create'), 'et n\'a pas « Répondre »');
		$this->assertTrue(str_contains($rowTrainee, 'comment:doRead"'), 'mais se marque lue');

		$this->assertEquals(1, substr_count($rowWaiting, '/learning/comment:create?lesson='.$eLessonOther['id'].'&parent='.$eCommentWaiting['id'].'&origin=inbox"'), '« Répondre » sur le commentaire à lire, qui dit d\'où il vient');
		$this->assertTrue($cCommentNew[1]['parent']->empty(), 'un premier niveau ne répond à personne');

		$this->assertEquals([$eCommentTeam['id']], $cCommentRead[0]['cCommentAnswer']->getIds(), 'la réponse de l\'équipe en filet, celle du stagiaire a sa ligne');
		$this->assertFalse(str_contains($rowAnswered, 'comment:create'), 'une ligne lue n\'a pas « Répondre »');

	}

	/**
	 * La réponse d'un stagiaire lue plus tard par son propre auteur, entré depuis dans l'équipe, garde
	 * sa ligne : seule une réponse écrite par l'équipe se range en filet.
	 */
	public function testAnswerReadByItsAuthor(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eLesson, , $eCommunity] = $this->createDecor();

		$this->actAs($this->eUserReader);
		$eCommentQuestion = $this->write($eLesson, 'Question du stagiaire');
		$eCommentTrainee = $this->write($eLesson, 'Précision du stagiaire', $eCommentQuestion);

		$this->actAs($this->eUserTeacher);
		$eCommentTeam = $this->write($eLesson, 'Réponse de l\'enseignant', $eCommentQuestion);

		// La lecture vient après l'écriture, d'un autre jour ici. Le Lib ne pèse pas le droit de lire,
		// que la page garde : l'auteur de la précision la marque lue comme le ferait un stagiaire entré
		// dans l'équipe.
		$this->setClock(date('Y-m-d H:i:s', strtotime('+1 day')));

		$this->actAs($this->eUserReader);
		CommentLib::read($this->reload($eCommentTrainee));

		$eCommentTraineeRead = $this->reload($eCommentTrainee);

		$this->assertEquals($eCommentTraineeRead['createdBy']['id'], $eCommentTraineeRead['readBy']['id'], 'la précision est lue par son auteur');

		$this->actAs($this->eUserTeacher);

		$cCommentRead = CommentLib::getReadByStructure($eCommunity, NULL, CommentLib::READ_PER_PAGE);

		$this->assertEquals([$eCommentTrainee['id'], $eCommentQuestion['id']], $cCommentRead->getIds(), 'elle garde sa ligne parmi les lus, la réponse de l\'enseignant n\'en a pas');

		CommentLib::fillTeamAnswers($eCommunity, $cCommentRead);

		$this->assertEquals([$eCommentTeam['id']], $cCommentRead[1]['cCommentAnswer']->getIds(), 'et seule la réponse de l\'enseignant se lit en filet');

	}

	/**
	 * L'état vide nomme le groupe selon ce qu'il est.
	 */
	public function testInboxEmpty(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCommunity = $this->getCommunity(self::COMMUNITY_EDUCATION);

		$this->assertEquals(\structure\Structure::EDUCATION, $eCommunity['communityType'], 'le décor est un groupe de formation');

		$empty = new CommentUi()->getInbox($eCommunity, new \Collection(), new \Collection(), FALSE);

		$this->assertTrue($empty !== '', 'un groupe de formation');

		// Élément monté à la main : `getInbox()` ne lit du groupe que son type quand il n'a rien à lister.
		$emptyNetwork = new CommentUi()->getInbox(new \structure\Structure(['id' => $eCommunity['id'], 'communityType' => \structure\Structure::NETWORK]), new \Collection(), new \Collection(), FALSE);

		$this->assertTrue($emptyNetwork !== $empty, 'un réseau, nommé autrement');

	}

	/**
	 * Une formation publiée du groupe à deux leçons, partagée à une promotion où le stagiaire est
	 * rangé ; un enseignant membre du groupe, rangé nulle part.
	 *
	 * @return array{0: Lesson, 1: Lesson, 2: \structure\Structure, 3: Course}
	 */
	private function createDecor(): array {

		$this->actAs($this->eUserAdmin);

		$eCommunity = $this->getCommunity(self::COMMUNITY_EDUCATION);

		$eCourse = $this->createCourse($eCommunity, Course::PUBLISHED, Course::PRIVATE);
		$eChapter = $this->createChapter($eCourse, 'Module');
		$eLesson = $this->createLesson($eChapter, 'Lire un sachet de graines', 10);
		$eLessonOther = $this->createLesson($eChapter, 'Choisir sa densité', 10);

		$eSection = $this->createSection($eCommunity, 'zzTest promotion des commentaires', [$this->eUserReader]);
		$this->share($eCourse, [$eSection['id'] => [$this->day(0), NULL]]);

		$this->eUserTeacher = $this->createTeacher($eCommunity);

		return [$this->getLesson($eLesson['id']), $this->getLesson($eLessonOther['id']), $eCommunity, $this->getCourse($eCourse['id'])];

	}

	/**
	 * Un compte `example.org`, domaine réservé où aucun envoi n'est délivré, administrateur du groupe,
	 * rôle des formateurs en formation.
	 */
	private function createTeacher(\structure\Structure $eCommunity): \user\User {

		$eUser = new \user\User([
			'firstName' => 'Denis',
			'lastName' => 'zzTest',
			'email' => 'zztest.enseignant-'.uniqid().'@example.org',
			'visibility' => \user\User::PRIVATE,
			'roleCustomer' => FALSE,
			'roleProducer' => TRUE,
			'roleConsultant' => FALSE,
			'roleTeacher' => FALSE
		]);

		$fw = new \FailWatch();

		\user\UserLib::create($eUser);

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('création de l\'enseignant refusée : '.json_encode($fw->get())));

		\structure\MemberLib::create(new \structure\Member([
			'user' => $eUser,
			'structure' => $eCommunity,
			'role' => \structure\RoleLib::getOne($eCommunity, 'admin'),
			'status' => \structure\Member::IN
		]));

		return $this->getUser($eUser['id']);

	}

	/**
	 * Un message écrit comme la page l'écrit : elle pose le parent lu dans la leçon, `build()` pèse
	 * celui qui est posté.
	 */
	private function write(Lesson $eLesson, string $text, Comment $eCommentParent = new Comment()): Comment {

		$parent = $eCommentParent->empty() ? NULL : $eCommentParent['id'];

		$eComment = new Comment([
			'lesson' => $eLesson,
			'eCommentParent' => CommentLib::getParentByLesson($eLesson, $parent)
		]);

		$fw = new \FailWatch();

		$eComment->build(CommentLib::getPropertiesCreate(), ['parent' => (string)$parent, 'text' => $text], new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('message refusé : '.json_encode($this->getFails($fw))));

		CommentLib::create($eComment);

		return $this->reload($eComment);

	}

	private function reload(Comment $eComment): Comment {
		return CommentLib::getById($eComment['id']);
	}

}
?>
