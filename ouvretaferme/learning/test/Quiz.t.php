<?php
namespace learning;

require_once __DIR__.'/LearningFixture.t.php';

/**
 * Le quiz d'une leçon (lot L5) : ce que le panneau d'une question accepte, la notation tout ou
 * rien et le seuil, les tentatives, la légitimité de ce que le lecteur poste, les routes de la
 * déclaration fermées à une leçon à quiz, la dernière question d'un quiz publié, ce que les
 * suppressions et la copie emportent, et ce que le suivi et son export en disent.
 */
class QuizTest extends LearningFixtureTest {

	/**
	 * Le seuil se compte au supérieur, et une question n'est juste que si les choix cochés sont
	 * exactement ses choix justes.
	 */
	public function testScoring(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->assertEquals(4, ResponseLib::getNeeded(5, 80), '80 % de 5 questions');
		$this->assertEquals(2, ResponseLib::getNeeded(3, 50), '50 % de 3 questions, au supérieur');
		$this->assertEquals(3, ResponseLib::getNeeded(3, 100), 'tout juste');
		$this->assertEquals(0, ResponseLib::getNeeded(3, 0), 'seuil nul');

		[, , $eLesson] = $this->createQuiz(Course::DRAFT, 100);

		$this->createQuestion($eLesson, Question::MULTIPLE, ['A' => TRUE, 'B' => TRUE, 'C' => FALSE]);

		$eQuestion = QuestionLib::getByLesson($eLesson)->first();
		[$a, $b, $c] = $eQuestion['cChoice']->getIds();

		$this->assertTrue(ResponseLib::isRight($eQuestion, [$b, $a]), 'les deux justes, dans le désordre');
		$this->assertFalse(ResponseLib::isRight($eQuestion, [$a]), 'une seule des deux justes');
		$this->assertFalse(ResponseLib::isRight($eQuestion, [$a, $b, $c]), 'les deux justes et une fausse');
		$this->assertFalse(ResponseLib::isRight($eQuestion, []), 'rien de coché');

	}

	/**
	 * Une tentative sous le seuil laisse la leçon en attente avec son score, la suivante au seuil la
	 * passe en suivie par le quiz, et une leçon réussie refuse toute tentative de plus, sans rien
	 * écrire. Chaque tentative garde ses réponses sous son rang, et une question laissée vide y
	 * porte une ligne sans choix.
	 */
	public function testAttempts(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, , $eLesson] = $this->createQuiz(Course::PUBLISHED, 100);

		$this->createQuestion($eLesson, Question::SINGLE, ['Oui' => TRUE, 'Non' => FALSE]);
		$this->createQuestion($eLesson, Question::MULTIPLE, ['Un' => TRUE, 'Deux' => TRUE, 'Trois' => FALSE]);

		$cQuestion = QuestionLib::getByLesson($eLesson);
		[$eQuestionSingle, $eQuestionMultiple] = [$cQuestion->first(), $cQuestion->last()];
		[$yes, $no] = $eQuestionSingle['cChoice']->getIds();
		[$one, $two] = $eQuestionMultiple['cChoice']->getIds();

		$this->actAs($this->eUserReader);
		$eEnrollment = $this->enroll($this->eUserReader, $eCourse);

		$this->assertEquals([], $this->answer($eEnrollment, $eLesson, $cQuestion, [$eQuestionSingle['id'] => [(string)$yes]]), 'première tentative acceptée');

		$eProgress = ProgressLib::getByUserAndLesson($this->eUserReader, $eLesson);
		$this->assertEquals([Progress::PENDING, 50, 1], [$eProgress['status'], $eProgress['score'], $eProgress['attempts']], 'une juste sur deux au seuil de 100 %, en attente');

		$this->assertEquals(
			[[$eQuestionSingle['id'], 1, $yes], [$eQuestionMultiple['id'], 1, NULL]],
			$this->getResponses($this->eUserReader, $cQuestion),
			'une ligne par choix coché, une ligne sans choix pour la question laissée vide'
		);

		ResponseLib::fillQuiz($this->eUserReader, $eLesson);
		$this->assertEquals([TRUE, 1, 2, 1], [$eLesson['attempted'], $eLesson['right'], $eLesson['needed'], $eLesson['attempts']], 'la correction de la première tentative');
		$this->assertEquals([[$yes], []], [$eLesson['cQuestion'][$eQuestionSingle['id']]['picked'], $eLesson['cQuestion'][$eQuestionMultiple['id']]['picked']], 'les choix cochés');

		ResponseLib::fillQuiz($this->eUserReader, $eLesson, retry: TRUE);
		$this->assertFalse($eLesson['attempted'], 'une nouvelle tentative montre le quiz vierge');

		$this->assertEquals([], $this->answer($eEnrollment, $eLesson, $cQuestion, [$eQuestionSingle['id'] => [(string)$yes], $eQuestionMultiple['id'] => [(string)$two, (string)$one]]), 'seconde tentative acceptée');

		$eProgress = ProgressLib::getByUserAndLesson($this->eUserReader, $eLesson);
		$this->assertEquals([Progress::DONE, Progress::QUIZ, 100, 2], [$eProgress['status'], $eProgress['source'], $eProgress['score'], $eProgress['attempts']], 'réussie par le quiz à la deuxième tentative');
		$this->assertTrue(ProgressLib::getDoneByUserAndCourse($this->eUserReader, $eCourse)->offsetExists($eLesson['id']), 'comptée dans les leçons suivies');

		$responses = count($this->getResponses($this->eUserReader, $cQuestion));

		$this->assertEquals(['Response::lesson.passed'], $this->answer($eEnrollment, $eLesson, $cQuestion, [$eQuestionSingle['id'] => [(string)$no]]), 'une leçon réussie ne se retente pas');
		$this->assertEquals($responses, count($this->getResponses($this->eUserReader, $cQuestion)), 'aucune réponse écrite');

		$eProgress = ProgressLib::getByUserAndLesson($this->eUserReader, $eLesson);
		$this->assertEquals([Progress::DONE, 100, 2], [$eProgress['status'], $eProgress['score'], $eProgress['attempts']], 'ni le score ni le compte des tentatives ne bougent');

	}

	/**
	 * Le seuil décide, et non « tout juste » : au seuil de 50 %, une juste sur deux suffit.
	 */
	public function testThreshold(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, , $eLesson] = $this->createQuiz(Course::PUBLISHED, 50);

		$this->createQuestion($eLesson, Question::SINGLE, ['Oui' => TRUE, 'Non' => FALSE]);
		$this->createQuestion($eLesson, Question::SINGLE, ['Vrai' => FALSE, 'Faux' => TRUE]);

		$cQuestion = QuestionLib::getByLesson($eLesson);
		$eQuestion = $cQuestion->first();

		$this->actAs($this->eUserReader);
		$eEnrollment = $this->enroll($this->eUserReader, $eCourse);

		$this->answer($eEnrollment, $eLesson, $cQuestion, [$eQuestion['id'] => [(string)$eQuestion['cChoice']->first()['id']]]);

		$eProgress = ProgressLib::getByUserAndLesson($this->eUserReader, $eLesson);
		$this->assertEquals([Progress::DONE, Progress::QUIZ, 50], [$eProgress['status'], $eProgress['source'], $eProgress['score']], 'une juste sur deux au seuil de 50 %');

	}

	/**
	 * Une leçon déclarée poste le seuil qu'elle a en base, vide s'il n'a jamais été réglé : l'enregistrer
	 * ne doit pas le transformer en valeur par défaut, que seul le passage au quiz propose dans le
	 * champ. Sans quoi « jamais réglé » et « réglé à 80 » se confondraient.
	 */
	public function testSettingsKeepNullThreshold(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE);
		$eChapter = $this->createChapter($eCourse, 'Module déclaré');
		$eLesson = $this->createLesson($eChapter, 'Leçon déclarée', 10);

		$read = fn() => Lesson::model()->select(['validation', 'quizThreshold'])->whereId($eLesson)->get()->extracts(['validation', 'quizThreshold']);

		$this->assertEquals(['validation' => Lesson::DECLARED, 'quizThreshold' => NULL], $read(), 'une leçon neuve est déclarée, sans seuil');

		$settings = [
			'id' => (string)$eLesson['id'],
			'chapter' => (string)$eChapter['id'],
			'name' => 'Leçon déclarée',
			'minutes' => '10',
			'validation' => Lesson::DECLARED,
			'quizThreshold' => ''
		];

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/lesson:doUpdateSettings', $settings), 'réglages enregistrés');
		$this->assertEquals(['validation' => Lesson::DECLARED, 'quizThreshold' => NULL], $read(), 'le seuil jamais réglé reste vide');

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/lesson:doUpdateSettings', ['validation' => Lesson::QUIZ, 'quizThreshold' => '60'] + $settings), 'passage en quiz, en brouillon');
		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/lesson:doUpdateSettings', ['quizThreshold' => '60'] + $settings), 'retour à la déclaration');
		$this->assertEquals(['validation' => Lesson::DECLARED, 'quizThreshold' => 60], $read(), 'un seuil réglé se garde en déclaration');

	}

	/**
	 * Rien ne borne le nombre de tentatives : les colonnes qui le comptent doivent tenir au-delà de
	 * 255, où un `int8` levait « Out of range » à l'envoi (relecture L5, V2). La ligne de progression
	 * est posée directement à 255 : en rejouer autant d'envois ne prouverait rien de plus.
	 */
	public function testManyAttempts(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, , $eLesson] = $this->createQuiz(Course::PUBLISHED, 100);

		$this->createQuestion($eLesson, Question::SINGLE, ['Oui' => TRUE, 'Non' => FALSE]);

		$cQuestion = QuestionLib::getByLesson($eLesson);
		$eQuestion = $cQuestion->first();
		$wrong = (string)$eQuestion['cChoice']->last()['id'];

		$this->actAs($this->eUserReader);
		$eEnrollment = $this->enroll($this->eUserReader, $eCourse);

		$this->answer($eEnrollment, $eLesson, $cQuestion, [$eQuestion['id'] => [$wrong]]);

		Progress::model()
			->whereUser($this->eUserReader)
			->whereLesson($eLesson)
			->update(['attempts' => 255]);

		$this->answer($eEnrollment, $eLesson, $cQuestion, [$eQuestion['id'] => [$wrong]]);

		$eProgress = ProgressLib::getByUserAndLesson($this->eUserReader, $eLesson);
		$this->assertEquals([Progress::PENDING, 256], [$eProgress['status'], $eProgress['attempts']], 'la 256e tentative est comptée');

		$attempts = Response::model()
			->whereUser($this->eUserReader)
			->whereQuestion($eQuestion)
			->getColumn('attempt');

		$this->assertEquals([1, 256], $attempts, 'la 256e tentative écrit ses réponses sous son numéro');

	}

	/**
	 * Ce que le lecteur poste n'est cru que s'il désigne les questions de cette leçon et leurs
	 * choix : le formulaire ne produit pas le reste.
	 */
	public function testAnswersLegitimacy(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[, $eChapter, $eLesson] = $this->createQuiz(Course::DRAFT, 100);

		$this->createQuestion($eLesson, Question::SINGLE, ['Oui' => TRUE, 'Non' => FALSE]);

		$eLessonOther = $this->setQuiz($this->createLesson($eChapter, 'Autre leçon', 5), 100);
		$this->createQuestion($eLessonOther, Question::SINGLE, ['Oui' => TRUE, 'Non' => FALSE]);

		$cQuestion = QuestionLib::getByLesson($eLesson);
		$eQuestion = $cQuestion->first();
		$eQuestionOther = QuestionLib::getByLesson($eLessonOther)->first();

		$this->assertEquals([$eQuestion['id'] => []], ResponseLib::buildAnswers($cQuestion, []), 'rien de posté, la question reçoit la réponse vide');

		$cases = [
			'question d\'une autre leçon' => [$eQuestionOther['id'] => [(string)$eQuestionOther['cChoice']->first()['id']]],
			'choix d\'une autre question' => [$eQuestion['id'] => [(string)$eQuestionOther['cChoice']->first()['id']]],
			'deux choix à une question à une réponse' => [$eQuestion['id'] => array_map('strval', $eQuestion['cChoice']->getIds())],
			'réponse qui n\'est pas une liste' => [$eQuestion['id'] => (string)$eQuestion['cChoice']->first()['id']],
		];

		foreach($cases as $case => $input) {

			try {
				ResponseLib::buildAnswers($cQuestion, $input);
				$this->assertFail($case.' : accepté');
			} catch(\NotExpectedAction) {
				$this->assertTrue(TRUE, $case);
			}

		}

	}

	/**
	 * Une leçon à quiz ne se déclare ni ne se retire à la main, et une leçon déclarée ne reçoit pas
	 * de réponses : chaque route refuse l'autre mode. L'envoi et la nouvelle tentative passent par
	 * les routes du lecteur.
	 */
	public function testRoutes(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eChapter, $eLesson] = $this->createQuiz(Course::PUBLISHED, 100);

		$this->createQuestion($eLesson, Question::SINGLE, ['Oui' => TRUE, 'Non' => FALSE]);
		$eQuestion = QuestionLib::getByLesson($eLesson)->first();

		$eLessonDeclared = $this->createLesson($eChapter, 'Leçon déclarée', 5);

		$this->actAs($this->eUserReader);
		$this->enroll($this->eUserReader, $eCourse);

		$this->assertDiffer(\ReloadAction::class, $this->executePage('learning/lesson:doDone', ['id' => (string)$eLesson['id']])[0], 'une leçon à quiz ne se déclare pas');
		$this->assertDiffer(\ReloadAction::class, $this->executePage('learning/lesson:doUndo', ['id' => (string)$eLesson['id']])[0], 'ni ne se retire');
		$this->assertEquals(0, Progress::model()->whereUser($this->eUserReader)->whereLesson($eLesson)->count(), 'rien d\'écrit');

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/lesson:doDone', ['id' => (string)$eLessonDeclared['id']]), 'une leçon déclarée se déclare');

		$this->assertDiffer(\ViewAction::class, $this->executePage('learning/response:doAnswer', ['lesson' => (string)$eLessonDeclared['id'], 'answer' => []])[0], 'une leçon déclarée ne reçoit pas de réponses');

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('learning/response:retry', ['lesson' => (string)$eLesson['id']], 'GET'), 'nouvelle tentative');
		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('learning/response:doAnswer', ['lesson' => (string)$eLesson['id'], 'answer' => [(string)$eQuestion['id'] => [(string)$eQuestion['cChoice']->first()['id']]]]), 'envoi');
		$this->assertEquals([\FailAction::class, 'Response::lesson.passed'], $this->executePage('learning/response:doAnswer', ['lesson' => (string)$eLesson['id'], 'answer' => []]), 'une leçon réussie refuse l\'envoi, avec son message');

	}

	/**
	 * Une leçon déclarée suivie puis passée en quiz garde son acquis (V-Learning, C3) : l'état reste
	 * « Suivie le … » sans menu de retrait, le quiz se lit sans formulaire, aucune tentative n'est
	 * acceptée et le suivi continue de dire « Déclarée ». Les données sont celles que prépare la
	 * page `/formation/lecon/{id}`.
	 */
	public function testDeclaredThenQuiz(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::PRIVATE);
		$eChapter = $this->createChapter($eCourse, 'Module déclaré');
		$eLesson = $this->createLesson($eChapter, 'Leçon déclarée puis quiz', 10);

		$this->actAs($this->eUserReader);
		$eEnrollment = $this->enroll($this->eUserReader, $eCourse);

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/lesson:doDone', ['id' => (string)$eLesson['id']]), 'déclarée suivie');

		$this->actAs($this->eUserAdmin);
		$eLesson = $this->setQuiz($eLesson, 100);
		$this->createQuestion($eLesson, Question::SINGLE, ['Oui' => TRUE, 'Non' => FALSE]);
		$this->actAs($this->eUserReader);

		$cQuestion = QuestionLib::getByLesson($eLesson);
		$eQuestion = $cQuestion->first();

		$this->assertEquals(['Response::lesson.passed'], $this->answer($eEnrollment, $eLesson, $cQuestion, [$eQuestion['id'] => [(string)$eQuestion['cChoice']->first()['id']]]), 'aucune tentative sur un acquis déclaré');
		$this->assertEquals(0, Response::model()->whereUser($this->eUserReader)->whereQuestion($eQuestion)->count(), 'rien d\'écrit');

		$eLesson = LessonLib::getById($eLesson['id']);
		$eCourse = $eLesson['course'];
		$cChapter = ChapterLib::getByCourse($eCourse);
		ChapterLib::locate($cChapter, $eLesson);
		$eCourse['canProgress'] = $eCourse->canProgress();

		$cProgress = ProgressLib::getDoneByUserAndCourse($this->eUserReader, $eCourse);
		ResponseLib::fillQuiz($this->eUserReader, $eLesson);

		$this->assertEquals(Progress::DECLARED, $cProgress[$eLesson['id']]['source'], 'l\'acquis reste déclaré');

		$state = new ProgressUi()->getState($eLesson, $eCourse, $cProgress);
		$this->assertFalse(str_contains($state, 'doUndo'), 'en-tête figé, sans menu de retrait');

		$quiz = new QuizUi()->get($eLesson, EnrollmentLib::getByUserAndCourse($this->eUserReader, $eCourse), $cProgress, new \Collection(), TRUE);
		$this->assertTrue(str_contains($quiz, 'response:doAnswer') === FALSE and str_contains($quiz, '<input') === FALSE, 'quiz lu sans formulaire');

		$cUser = new \Collection([$this->eUserReader['id'] => $this->eUserReader]);
		ProgressLib::fillTrack($eCourse, $cChapter, $cUser);

		$this->assertEquals(ProgressLib::TRACK_DECLARED, $this->eUserReader['track'][$eLesson['id']]['state'], 'le suivi dit « Déclarée »');

	}

	/**
	 * Ce que le panneau d'une question accepte : de 2 à 6 choix, chacun avec un libellé ou une
	 * image, une seule bonne réponse pour une question à une réponse, au moins une sinon, et
	 * seulement des images déposées par le compte, sauf celle que la question porte déjà.
	 */
	public function testBuild(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[, $eChapter, $eLesson] = $this->createQuiz(Course::DRAFT, 100);

		$this->assertEquals(['Question::choices.count'], $this->postQuestion($eLesson, Question::SINGLE, [['A', TRUE]])[0], 'un seul choix');
		$this->assertEquals(['Question::choices.count'], $this->postQuestion($eLesson, Question::MULTIPLE, array_map(fn(int $i) => ['Choix '.$i, TRUE], range(1, 7)))[0], 'sept choix');
		$this->assertEquals([], $this->postQuestion($eLesson, Question::MULTIPLE, array_map(fn(int $i) => ['Choix '.$i, TRUE], range(1, 6)))[0], 'six choix');

		$this->assertEquals(['Choice::image.filled'], $this->postQuestion($eLesson, Question::SINGLE, [['A', TRUE], [NULL, FALSE]])[0], 'un choix sans libellé ni image');
		$this->assertEquals(['Question::choices.correct'], $this->postQuestion($eLesson, Question::SINGLE, [['A', TRUE], ['B', TRUE]])[0], 'deux justes à une question à une réponse');
		$this->assertEquals(['Question::choices.correct'], $this->postQuestion($eLesson, Question::MULTIPLE, [['A', FALSE], ['B', FALSE]])[0], 'aucune juste');
		$this->assertEquals(['Question::type.check'], $this->postQuestion($eLesson, 'other', [['A', TRUE], ['B', FALSE]])[0], 'type inconnu');

		$hashOwn = $this->createMedia($this->eUserAdmin);
		$hashForeign = $this->createMedia($this->eUserReader);

		$this->assertEquals([], $this->postQuestion($eLesson, Question::SINGLE, [['A', TRUE, $hashOwn], [NULL, FALSE, $hashOwn]], $hashOwn)[0], 'images déposées par le compte, un choix en image seule');
		$this->assertEquals(['Question::image.owned'], $this->postQuestion($eLesson, Question::SINGLE, [['A', TRUE], ['B', FALSE]], $hashForeign)[0], 'image de question d\'un autre compte');
		$this->assertEquals(['Choice::image.owned'], $this->postQuestion($eLesson, Question::SINGLE, [['A', TRUE, $hashForeign], ['B', FALSE]])[0], 'image de choix d\'un autre compte');

		// Une question dont l'image vient d'un autre compte (une copie, un autre membre de l'équipe)
		// se modifie sans la perdre : le contrôle ne tire que sur un hash qui change.
		[, $eQuestion] = $this->postQuestion($eLesson, Question::SINGLE, [['A', TRUE], ['B', FALSE]]);
		$eQuestion['image'] = $hashForeign;
		Question::model()->select('image')->update($eQuestion);

		$eQuestion = QuestionLib::getById($eQuestion['id']);
		$cChoice = ChoiceLib::getByQuestion($eQuestion);

		$this->assertEquals([], $this->postQuestion($eLesson, Question::SINGLE, [['A modifié', TRUE, NULL, $cChoice->first()['id']], ['B', FALSE, NULL, $cChoice->last()['id']]], $hashForeign, $eQuestion)[0], 'image d\'autrui gardée telle quelle');

		$eQuestion = QuestionLib::getById($eQuestion['id']);
		$this->assertEquals($hashForeign, $eQuestion['image'], 'image gardée');
		$this->assertEquals(['A modifié', 'B'], ChoiceLib::getByQuestion($eQuestion)->getColumn('label'), 'choix mis à jour, pas recréés');
		$this->assertEquals($cChoice->getIds(), ChoiceLib::getByQuestion($eQuestion)->getIds(), 'mêmes choix');

		// Le choix d'une autre question, désigné par son id, serait écrit par `update()`.
		$eLessonOther = $this->setQuiz($this->createLesson($eChapter, 'Autre leçon', 5), 100);
		[, $eQuestionOther] = $this->postQuestion($eLessonOther, Question::SINGLE, [['X', TRUE], ['Y', FALSE]]);
		$eChoiceOther = ChoiceLib::getByQuestion($eQuestionOther)->first();

		try {
			$this->postQuestion($eLesson, Question::SINGLE, [['A', TRUE, NULL, $eChoiceOther['id']], ['B', FALSE]], NULL, $eQuestion);
			$this->assertFail('choix d\'une autre question accepté');
		} catch(\NotExpectedAction) {
			$this->assertEquals('X', ChoiceLib::getByQuestion($eQuestionOther)->first()['label'], 'choix d\'une autre question refusé, intact');
		}

	}

	/**
	 * Un choix retiré du panneau part avec les réponses qui le cochaient, les autres restent.
	 */
	public function testChoiceRemoved(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, , $eLesson] = $this->createQuiz(Course::PUBLISHED, 100);

		[, $eQuestion] = $this->postQuestion($eLesson, Question::MULTIPLE, [['A', TRUE], ['B', TRUE], ['C', FALSE]]);

		$cQuestion = QuestionLib::getByLesson($eLesson);
		[$a, $b, $c] = $cQuestion->first()['cChoice']->getIds();

		$this->actAs($this->eUserReader);
		$eEnrollment = $this->enroll($this->eUserReader, $eCourse);
		$this->answer($eEnrollment, $eLesson, $cQuestion, [$eQuestion['id'] => [(string)$a, (string)$c]]);

		$this->actAs($this->eUserAdmin);

		$eQuestion = QuestionLib::getById($eQuestion['id']);
		$this->assertEquals([], $this->postQuestion($eLesson, Question::MULTIPLE, [['A', TRUE, NULL, $a], ['B', TRUE, NULL, $b]], NULL, $eQuestion)[0], 'choix C retiré');

		$this->assertEquals([$a, $b], ChoiceLib::getByQuestion($eQuestion)->getIds(), 'deux choix restent');
		$this->assertEquals([[$eQuestion['id'], 1, $a]], $this->getResponses($this->eUserReader, $cQuestion), 'la réponse qui cochait C est partie');

	}

	/**
	 * La règle de la leçon passée en quiz sans question (R12) : refusée sous une formation publiée,
	 * acceptée en brouillon ou dès qu'une question existe. Le seuil n'est exigé que du quiz.
	 */
	public function testValidation(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::PRIVATE);
		$eChapter = $this->createChapter($eCourse, 'Module');
		$eLesson = $this->getLesson($this->createLesson($eChapter, 'Leçon', 10)['id']);

		$this->assertEquals(['Lesson::validation.questioned'], $this->buildValidation($eLesson, Lesson::QUIZ, 80), 'quiz sans question sous une formation publiée');
		$this->assertEquals([], $this->buildValidation($eLesson, Lesson::DECLARED, NULL), 'déclarée sans seuil');
		$this->assertEquals(['Lesson::validation.check'], $this->buildValidation($eLesson, Lesson::PRESENCE, NULL), 'une validation qu\'aucun écran ne sait encore tenir');

		$this->createQuestion($eLesson, Question::SINGLE, ['Oui' => TRUE, 'Non' => FALSE]);
		$this->assertEquals([], $this->buildValidation($eLesson, Lesson::QUIZ, 80), 'quiz avec une question sous une formation publiée');
		$this->assertEquals(['Lesson::quizThreshold.required'], $this->buildValidation($eLesson, Lesson::QUIZ, NULL), 'quiz sans seuil');

		$eCourseDraft = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE);
		$eLessonDraft = $this->getLesson($this->createLesson($this->createChapter($eCourseDraft, 'Module'), 'Leçon', 10)['id']);
		$this->assertEquals([], $this->buildValidation($eLessonDraft, Lesson::QUIZ, 80), 'quiz sans question en brouillon');

		// Le chemin de l'API, où la leçon neuve ne connaît sa formation que par le module posté.
		$eLessonApi = new Lesson(['cChapter' => ChapterLib::getByCourse($eCourse), 'cBlock' => new \Collection()]);
		$fw = new \FailWatch();
		$eLessonApi->build(LessonLib::getPropertiesCreate('api'), ['chapter' => $eChapter['id'], 'name' => 'Leçon API', 'minutes' => 5, 'content' => NULL, 'validation' => Lesson::QUIZ, 'quizThreshold' => 80], new \PropertyList('create'));
		$this->assertEquals(['Lesson::validation.questioned'], array_column($fw->get(), 1), 'création d\'API d\'une leçon à quiz sous une formation publiée');

		// Le quiz d'une leçon qui n'existe pas encore est vide, même quand une question de parcours
		// (sans leçon) est en base.
		$eQuestionPath = new Question(['lesson' => new Lesson(), 'statement' => 'Question de parcours', 'image' => NULL, 'type' => Question::SINGLE, 'explanation' => NULL, 'position' => 0]);
		Question::model()->insert($eQuestionPath);
		$this->assertEquals(0, QuestionLib::countByLesson(new Lesson()), 'leçon neuve, aucune question');

	}

	/**
	 * La dernière question d'une leçon à quiz sous une formation publiée ne se supprime pas. Toutes
	 * les autres se suppriment, et les suivantes remontent d'un rang.
	 */
	public function testDeleteLastQuestion(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[, $eChapter, $eLesson] = $this->createQuiz(Course::PUBLISHED, 100);

		$this->createQuestion($eLesson, Question::SINGLE, ['Oui' => TRUE, 'Non' => FALSE]);
		$this->createQuestion($eLesson, Question::SINGLE, ['Vrai' => TRUE, 'Faux' => FALSE]);

		[$first, $second] = QuestionLib::getByLesson($eLesson)->getIds();

		$this->assertEquals([], $this->deleteQuestion($first), 'l\'avant-dernière se supprime');
		$this->assertEquals([0], QuestionLib::getByLesson($eLesson)->getColumn('position'), 'la suivante remonte');
		$this->assertEquals(0, Choice::model()->whereQuestion($first)->count(), 'ses choix sont partis');

		$this->assertEquals(['Question::deleteLastPublished'], $this->deleteQuestion($second), 'la dernière est refusée');
		$this->assertEquals(1, QuestionLib::countByLesson($eLesson), 'la dernière reste');

		$eLessonDeclared = $this->getLesson($this->createLesson($eChapter, 'Leçon déclarée', 5)['id']);
		$this->createQuestion($eLessonDeclared, Question::SINGLE, ['Oui' => TRUE, 'Non' => FALSE]);
		$this->assertEquals([], $this->deleteQuestion(QuestionLib::getByLesson($eLessonDeclared)->first()['id']), 'la dernière d\'une leçon déclarée se supprime');

		[, , $eLessonDraft] = $this->createQuiz(Course::DRAFT, 100);
		$this->createQuestion($eLessonDraft, Question::SINGLE, ['Oui' => TRUE, 'Non' => FALSE]);
		$this->assertEquals([], $this->deleteQuestion(QuestionLib::getByLesson($eLessonDraft)->first()['id']), 'la dernière d\'une formation en brouillon se supprime');

	}

	/**
	 * Supprimer une leçon à quiz ou une formation publiée emporte la dernière question, ses choix et
	 * les réponses des lecteurs : la règle de la dernière question ne vaut que pour la question seule.
	 */
	public function testCascade(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		foreach(['leçon' => FALSE, 'formation' => TRUE] as $case => $wholeCourse) {

			[$eCourse, $eChapter, $eLesson] = $this->createQuiz(Course::PUBLISHED, 100);
			$this->createLesson($eChapter, 'Leçon qui reste', 5);

			$this->createQuestion($eLesson, Question::SINGLE, ['Oui' => TRUE, 'Non' => FALSE]);

			$cQuestion = QuestionLib::getByLesson($eLesson);
			$eQuestion = $cQuestion->first();

			$this->actAs($this->eUserReader);
			$this->answer($this->enroll($this->eUserReader, $eCourse), $eLesson, $cQuestion, [$eQuestion['id'] => [(string)$eQuestion['cChoice']->first()['id']]]);
			$this->actAs($this->eUserAdmin);

			$fw = new \FailWatch();

			if($wholeCourse) {
				CourseLib::delete($this->getCourse($eCourse['id']));
			} else {
				LessonLib::delete($this->getLesson($eLesson['id']));
			}

			$this->assertEquals([], array_column($fw->get(), 1), $case.' supprimée');
			$this->assertEquals(
				[0, 0, 0],
				[
					Question::model()->whereLesson($eLesson)->count(),
					Choice::model()->whereQuestion($eQuestion)->count(),
					Response::model()->whereQuestion($eQuestion)->count(),
				],
				$case.' : ni question, ni choix, ni réponse'
			);

		}

	}

	/**
	 * La copie reprend les questions et leurs choix, images comprises sous le même hash, et laisse
	 * les réponses des lecteurs de l'original.
	 */
	public function testDuplicate(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, , $eLesson] = $this->createQuiz(Course::PUBLISHED, 60);

		$hash = $this->createMedia($this->eUserAdmin);

		$this->postQuestion($eLesson, Question::SINGLE, [['Oui', TRUE], [NULL, FALSE, $hash]], $hash, explanation: 'Parce que.');
		$this->createQuestion($eLesson, Question::MULTIPLE, ['Un' => TRUE, 'Deux' => TRUE, 'Trois' => FALSE]);

		$cQuestion = QuestionLib::getByLesson($eLesson);
		$eQuestion = $cQuestion->first();

		$this->actAs($this->eUserReader);
		$this->answer($this->enroll($this->eUserReader, $eCourse), $eLesson, $cQuestion, [$eQuestion['id'] => [(string)$eQuestion['cChoice']->first()['id']]]);
		$this->actAs($this->eUserAdmin);

		$eCourseNew = CourseLib::getCreateElement($this->getCommunity(self::COMMUNITY_EDUCATION));
		$fw = new \FailWatch();
		$eCourseNew->build(['name'], ['name' => 'zzTest copie du quiz'], new \PropertyList('create'));
		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('titre de la copie refusé : '.json_encode($fw->get())));

		$eCourseCopy = CourseLib::duplicate($this->getCourse($eCourse['id']), $eCourseNew);

		$eLessonCopy = Lesson::model()
			->select(Lesson::getSelection())
			->whereCourse($eCourseCopy)
			->get();

		$this->assertEquals([Lesson::QUIZ, 60], [$eLessonCopy['validation'], $eLessonCopy['quizThreshold']], 'la leçon copiée garde son quiz');

		$cQuestionCopy = QuestionLib::getByLesson($eLessonCopy);

		$describe = fn(\Collection $cQuestion) => $cQuestion->makeArray(fn(Question $eQuestion) => [
			$eQuestion['statement'], $eQuestion['image'], $eQuestion['type'], $eQuestion['explanation'], $eQuestion['position'],
			$eQuestion['cChoice']->makeArray(fn(Choice $eChoice) => [$eChoice['label'], $eChoice['image'], $eChoice['correct'], $eChoice['position']])
		]);

		$this->assertEquals($describe($cQuestion), $describe($cQuestionCopy), 'questions et choix copiés à l\'identique, image sous le même hash');
		$this->assertEquals([], array_intersect($cQuestion->getIds(), $cQuestionCopy->getIds()), 'de nouvelles questions');
		$this->assertEquals(0, Response::model()->whereQuestion('IN', $cQuestionCopy)->count(), 'aucune réponse copiée');

	}

	/**
	 * Le suivi et son export disent un quiz réussi ou à refaire avec son score, et le constat de
	 * l'équipe à la suite, par la même règle que pour une leçon déclarée.
	 */
	public function testTrack(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eChapter, $eLessonFirst] = $this->createQuiz(Course::PUBLISHED, 100);
		$eLessonSecond = $this->setQuiz($this->createLesson($eChapter, 'Seconde leçon', 5), 100);

		foreach([$eLessonFirst, $eLessonSecond] as $eLesson) {
			$this->createQuestion($eLesson, Question::SINGLE, ['Oui' => TRUE, 'Non' => FALSE]);
			$this->createQuestion($eLesson, Question::SINGLE, ['Vrai' => TRUE, 'Faux' => FALSE]);
		}

		// Le lecteur réussit la première, échoue une fois à la seconde ; l'administrateur réussit la
		// première et échoue deux fois à la seconde, et l'équipe constate ses deux leçons.
		$plays = [
			[$this->eUserReader, $eLessonFirst, [TRUE, TRUE]],
			[$this->eUserReader, $eLessonSecond, [TRUE, FALSE]],
			[$this->eUserAdmin, $eLessonFirst, [TRUE, TRUE]],
			[$this->eUserAdmin, $eLessonSecond, [FALSE, FALSE]],
			[$this->eUserAdmin, $eLessonSecond, [TRUE, FALSE]],
		];

		foreach($plays as [$eUser, $eLesson, $rights]) {

			$cQuestion = QuestionLib::getByLesson($eLesson);
			$answers = [];

			$position = 0;

			foreach($cQuestion as $eQuestion) {
				$answers[$eQuestion['id']] = [(string)($rights[$position++] ? $eQuestion['cChoice']->first() : $eQuestion['cChoice']->last())['id']];
			}

			$this->answer($this->enroll($eUser, $eCourse), $eLesson, $cQuestion, $answers);

		}

		ProgressLib::check($eCourse, $eLessonFirst, $this->eUserAdmin);
		ProgressLib::check($eCourse, $eLessonSecond, $this->eUserAdmin);

		$cChapter = ChapterLib::getByCourse($eCourse);
		$cUser = new \Collection([
			$this->eUserReader['id'] => $this->eUserReader,
			$this->eUserAdmin['id'] => $this->eUserAdmin,
		]);

		ProgressLib::fillTrack($eCourse, $cChapter, $cUser);

		$this->assertEquals(
			['state' => ProgressLib::TRACK_FAILED, 'checked' => TRUE, 'right' => 1, 'questions' => 2, 'attempts' => 2],
			$this->eUserAdmin['track'][$eLessonSecond['id']],
			'à refaire, constatée'
		);
		$this->assertEquals([1, 2], [$this->eUserReader['followed'], $this->eUserAdmin['followed']], 'un quiz à refaire ne compte que constaté');

		ReadingLib::fillLastSeen($eCourse, $cUser);

		$lines = new ProgressUi()->getExport($cChapter, $cUser);
		array_shift($lines);

		$this->assertEquals([s("Réussie, {score}", ['score' => s("{right} sur {questions}", ['right' => 2, 'questions' => 2])]), s("À refaire").', '.s("{right} sur {questions}", ['right' => 1, 'questions' => 2]).', '.p("{value} tentative", "{value} tentatives", 1)], array_slice($lines[0], 4), 'le lecteur');
		$this->assertEquals(['Réussie, 2 sur 2, constatée', 'À refaire, 1 sur 2, 2 tentatives, constatée'], array_slice($lines[1], 4), 'l\'administrateur, en toutes lettres');

	}

	/**
	 * « Poser ce quiz » passe une leçon déclarée en quiz par `build()` : avec le seuil qu'elle garde
	 * d'un quiz précédent, sinon le seuil par défaut, et jamais sans question sous une formation
	 * publiée. Le bouton paraît tant qu'il y a des questions à poser, et la réponse ne rend que la
	 * section du quiz.
	 */
	public function testPose(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::PRIVATE);
		$eChapter = $this->createChapter($eCourse, 'Module à poser');
		$eLesson = $this->createLesson($eChapter, 'Leçon à poser', 10);

		$this->assertFalse(str_contains($this->getEditor($eLesson), 'lesson:doPoseQuiz'), 'sans question, pas de bouton');
		$this->assertEquals([\FailAction::class, 'Lesson::validation.questioned'], $this->executePage('learning/lesson:doPoseQuiz', ['id' => (string)$eLesson['id']]), 'sans question, refusé par build()');
		$this->assertEquals(Lesson::DECLARED, $this->getLesson($eLesson['id'])['validation'], 'la leçon reste déclarée');

		$this->createQuestion($eLesson, Question::SINGLE, ['Oui' => TRUE, 'Non' => FALSE]);

		$editor = $this->getEditor($eLesson);
		$this->assertTrue(str_contains($editor, 'data-ajax="/learning/lesson:doPoseQuiz" post-id="'.$eLesson['id'].'"'), 'une question écrite : le bouton paraît');

		$data = $this->executeView('learning/lesson:doPoseQuiz', ['id' => (string)$eLesson['id']], 'POST');

		$this->assertTrue($data instanceof \stdClass, 'posé : une vue ('.(is_string($data) ? $data : 'vue').')');

		$eLessonPosed = $this->getLesson($eLesson['id']);

		$this->assertEquals(Lesson::QUIZ, $eLessonPosed['validation'], 'la leçon passe en quiz');
		$this->assertEquals(Lesson::QUIZ_THRESHOLD_DEFAULT, $eLessonPosed['quizThreshold'], 'au seuil par défaut');

		$editor = $this->getEditor($eLessonPosed);
		$this->assertFalse(str_contains($editor, 'lesson:doPoseQuiz'), 'posé : plus de bouton');

		$view = file_get_contents(\Package::getFileFromUri('learning/lesson', 'view'));
		$this->assertTrue(preg_match("/new JsonView\\('doPoseQuiz'.*?\\n\\}\\);/s", $view, $match) === 1, 'la vue de la réponse existe');
		$this->assertEquals(1, substr_count($match[0], '->outerHtml(') + substr_count($match[0], '->innerHtml('), 'elle ne rend qu\'un nœud');
		$this->assertTrue(str_contains($match[0], "qs('#lesson-quiz')"), 'celui de la section du quiz');
		$this->assertFalse(str_contains($match[0], 'ajaxReload'), 'sans recharger l\'écran');

		$eLessonKept = $this->setQuiz($this->createLesson($eChapter, 'Leçon au seuil gardé', 10), 60);
		$this->createQuestion($eLessonKept, Question::SINGLE, ['Oui' => TRUE, 'Non' => FALSE]);

		$eLessonKept['validation'] = Lesson::DECLARED;
		LessonLib::update($eLessonKept, ['validation']);

		$this->executeView('learning/lesson:doPoseQuiz', ['id' => (string)$eLessonKept['id']], 'POST');

		$this->assertEquals([Lesson::QUIZ, 60], [$this->getLesson($eLessonKept['id'])['validation'], $this->getLesson($eLessonKept['id'])['quizThreshold']], 'un seuil gardé d\'un quiz précédent resert');

	}

	/**
	 * Sous l'option du quiz, une leçon déclarée qui garde des questions le dit : la phrase ne vaut que
	 * pour la déclaration, et `LearningQuiz.toggleThreshold()` la cache dès que l'option est cochée
	 * (retour de V-Learning du 05/10). Une leçon déjà en quiz ne la porte pas.
	 */
	public function testSettingsKeptQuestions(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE);
		$eChapter = $this->createChapter($eCourse, 'Module gardé');
		$eLesson = $this->createLesson($eChapter, 'Leçon aux questions gardées', 10);

		$this->createQuestion($eLesson, Question::SINGLE, ['Oui' => TRUE, 'Non' => FALSE]);

		$settings = function(Lesson $eLesson): string {

			$data = $this->executeView('learning/lesson:updateSettings', ['id' => $eLesson['id']]);
			$this->assertTrue($data instanceof \stdClass, 'le panneau se prépare ('.(is_string($data) ? $data : 'vue').')');

			return new LessonUi()->updateSettings($data->e)->body;

		};

		$this->assertTrue(str_contains($settings($eLesson), 'id="lesson-quiz-kept"'), 'déclarée, la phrase sous l\'option, que le script retrouve par son identifiant');

		$script = file_get_contents(\Package::getPath('learning').'/static/js/learning.js');
		$this->assertTrue(preg_match('/static toggleThreshold\(form\) \{.*?\n\t\}\n/s', $script, $match) === 1, 'le script qui suit l\'option cochée existe');
		$this->assertTrue(str_contains($match[0], "form.qs('#lesson-quiz-kept', reason => reason.classList.toggle('hide', quiz));"), 'il cache la phrase quand le quiz est coché, et la rend avec la déclaration');

		$this->assertFalse(str_contains($settings($this->setQuiz($eLesson, 80)), 'lesson-quiz-kept'), 'en quiz, aucune phrase de questions gardées');

	}

	/**
	 * La section du quiz telle que l'écran de la leçon la rend.
	 */
	private function getEditor(Lesson $eLesson): string {

		$eLesson = $this->getLesson($eLesson['id']);

		QuestionLib::fillEditor($eLesson);

		return new QuestionUi()->getEditor($eLesson);

	}

	/**
	 * Une formation de l'équipe et une leçon passée en quiz, écrites par l'administrateur, au nom
	 * de qui le test continue.
	 */
	private function createQuiz(string $status, int $threshold): array {

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), $status, Course::PRIVATE);
		$eChapter = $this->createChapter($eCourse, 'Module du quiz');
		$eLesson = $this->setQuiz($this->createLesson($eChapter, 'Leçon à quiz', 10), $threshold);

		return [$eCourse, $eChapter, $eLesson];

	}

	/**
	 * Posé sans `build()`, donc sans l'étape `Lesson::validation.questioned` : le décor monte un
	 * quiz publié avant d'y écrire sa première question, ce que le produit fait dans l'autre ordre.
	 */
	private function setQuiz(Lesson $eLesson, int $threshold): Lesson {

		$eLesson = $this->getLesson($eLesson['id']);

		$eLesson['validation'] = Lesson::QUIZ;
		$eLesson['quizThreshold'] = $threshold;

		LessonLib::update($eLesson, ['validation', 'quizThreshold']);

		return $this->getLesson($eLesson['id']);

	}

	/**
	 * Une question écrite par le chemin du panneau, dont le décor exige qu'elle soit acceptée.
	 * `$choices` associe chaque libellé à sa justesse.
	 */
	private function createQuestion(Lesson $eLesson, string $type, array $choices): void {

		$posted = [];

		foreach($choices as $label => $correct) {
			$posted[] = [$label, $correct];
		}

		[$fails] = $this->postQuestion($eLesson, $type, $posted);

		if($fails !== []) {
			throw new \Exception('question refusée : '.implode(', ', $fails));
		}

	}

	/**
	 * Le chemin de `doCreate` et `doUpdate` (`page/question.p.php`), sous un même `FailWatch`, avec
	 * ce que le formulaire poste : des chaînes, un champ vide pour une image absente. Chaque choix
	 * est `[libellé, juste, image, id]`. Rend les refus et la question, écrite s'il n'y en a aucun.
	 */
	private function postQuestion(Lesson $eLesson, string $type, array $choices, ?string $image = NULL, Question $eQuestion = new Question(), ?string $explanation = NULL): array {

		if($eQuestion->empty()) {
			$eQuestion = new Question(['lesson' => $this->getLesson($eLesson['id']), 'type' => Question::SINGLE, 'image' => NULL]);
		}

		$input = ['label' => [], 'correct' => [], 'image' => [], 'id' => []];

		foreach($choices as $index => $choice) {
			$input['label'][$index] = $choice[0] ?? '';
			$input['correct'][$index] = $choice[1] ? '1' : '0';
			$input['image'][$index] = $choice[2] ?? '';
			$input['id'][$index] = isset($choice[3]) ? (string)$choice[3] : '';
		}

		$fw = new \FailWatch();

		$eQuestion->build(QuestionLib::getPropertiesCreate(), [
			'statement' => 'Une question ?',
			'image' => $image ?? '',
			'type' => $type,
			'explanation' => $explanation ?? '',
		], new \PropertyList($eQuestion->exists() ? 'update' : 'create'));

		$cChoice = ChoiceLib::buildForQuestion($eQuestion, $input);

		if($fw->ok()) {

			if($eQuestion->exists()) {
				QuestionLib::update($eQuestion, QuestionLib::getPropertiesUpdate(), $cChoice);
			} else {
				QuestionLib::create($eQuestion, $cChoice);
			}

		}

		return [array_column($fw->get(), 1), $eQuestion];

	}

	private function deleteQuestion(int $id): array {

		$fw = new \FailWatch();

		QuestionLib::delete(QuestionLib::getById($id));

		return array_column($fw->get(), 1);

	}

	/**
	 * Le chemin de `doUpdateSettings`, réduit aux deux propriétés du quiz.
	 */
	private function buildValidation(Lesson $eLesson, string $validation, ?int $threshold): array {

		$eLesson = $this->getLesson($eLesson['id']);

		$fw = new \FailWatch();

		$eLesson->build(['validation', 'quizThreshold'], ['validation' => $validation, 'quizThreshold' => $threshold === NULL ? '' : (string)$threshold], new \PropertyList('update'));

		return array_column($fw->get(), 1);

	}

	private function enroll(\user\User $eUser, Course $eCourse): Enrollment {

		$eEnrollment = new Enrollment(['user' => $eUser, 'course' => $eCourse, 'structure' => new \structure\Structure()]);

		EnrollmentLib::create($eEnrollment);

		return $eEnrollment;

	}

	/**
	 * Le chemin de `doAnswer`, depuis ce que le formulaire poste.
	 */
	private function answer(Enrollment $eEnrollment, Lesson $eLesson, \Collection $cQuestion, array $posted): array {

		$fw = new \FailWatch();

		ResponseLib::answer($eEnrollment, $this->getLesson($eLesson['id']), $cQuestion, ResponseLib::buildAnswers($cQuestion, $posted));

		return array_column($fw->get(), 1);

	}

	/**
	 * Les réponses du compte, `[question, tentative, choix]` dans l'ordre d'écriture.
	 */
	private function getResponses(\user\User $eUser, \Collection $cQuestion): array {

		$cResponse = Response::model()
			->select(['question', 'attempt', 'choice'])
			->whereUser($eUser)
			->whereQuestion('IN', $cQuestion)
			->sort(['id' => SORT_ASC])
			->getCollection();

		return $cResponse->makeArray(fn(Response $eResponse) => [
			$eResponse['question']['id'],
			$eResponse['attempt'],
			$eResponse['choice']->empty() ? NULL : $eResponse['choice']['id'],
		]);

	}

	/**
	 * Le média seul, sans fichier : le contrôle des images ne lit que sa ligne.
	 */
	private function createMedia(\user\User $eUser): string {

		$hash = \media\UtilLib::generateHash().'p';

		$eMedia = new \media\Media([
			'user' => $eUser,
			'type' => 'learning-quiz',
			'status' => \media\Media::ACTIVE,
			'updatedAt' => new \Sql('NOW()'),
			'hash' => $hash
		]);

		\media\Media::model()->insert($eMedia);

		return $hash;

	}

}
?>
