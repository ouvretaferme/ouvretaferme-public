<?php
namespace learning;

require_once __DIR__.'/LearningNetworkFixture.t.php';

/**
 * Le journal de lecture (lot L8c) : la séance qu'ouvre et prolonge `ReadingLib::see()`, le signal
 * de la leçon et qui peut l'envoyer, le journal relu par l'équipe et par le stagiaire, la dernière
 * activité du suivi, et ce que les suppressions emportent. Le relevé de suivi en PDF (lot L8d), sa
 * colonne de l'export détaillé et qui peut le tirer.
 *
 * Le décor est celui de `LearningNetworkFixture.t.php` : le réseau G3, la ferme de l'admin
 * rattachée dans la transaction, le lecteur fictif membre de cette ferme et inscrit à une formation
 * du réseau ouverte à toutes les fermes. L'heure est figée par `setClock()`, que MySQL suit aussi :
 * les écarts entre deux signaux se lisent à la seconde, sans tolérance.
 */
class ReadingTest extends LearningNetworkFixtureTest {

	/**
	 * L'ouverture crée la séance, un signal 40 s plus tard ajoute 40 s, un autre 20 s après en
	 * ajoute 20 ; 70 s sans signal closent la séance, et le suivant en ouvre une seconde sans
	 * toucher à la première.
	 */
	public function testSee(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eLesson, $eEnrollment] = $this->seedReading();

		$this->setClock('2026-09-12 09:14:00');
		$this->assertTrue(ReadingLib::see($eEnrollment, $eLesson), 'l\'ouverture tient le journal');

		$this->assertEquals([['2026-09-12 09:14:00', '2026-09-12 09:14:00', 0]], $this->getReadings($eCourse), 'une séance à zéro seconde');

		$this->setClock('2026-09-12 09:14:40');
		ReadingLib::see($eEnrollment, $eLesson);

		$this->assertEquals([['2026-09-12 09:14:00', '2026-09-12 09:14:40', 40]], $this->getReadings($eCourse), 'un signal 40 s plus tard ajoute 40 s à la même séance');

		$this->setClock('2026-09-12 09:15:00');
		ReadingLib::see($eEnrollment, $eLesson);

		$this->assertEquals([['2026-09-12 09:14:00', '2026-09-12 09:15:00', 60]], $this->getReadings($eCourse), 'deux signaux rapprochés n\'ajoutent que l\'écoulé');

		$this->setClock('2026-09-12 09:16:10');
		ReadingLib::see($eEnrollment, $eLesson);

		$this->assertEquals(
			[
				['2026-09-12 09:14:00', '2026-09-12 09:15:00', 60],
				['2026-09-12 09:16:10', '2026-09-12 09:16:10', 0]
			],
			$this->getReadings($eCourse),
			'70 s sans signal : une seconde séance, la première intacte'
		);

	}

	/**
	 * Deux onglets, ou l'ouverture suivie de son premier signal, peuvent tomber dans la même seconde :
	 * MySQL rend alors 0 ligne affectée, la séance ne changeant pas, et ce n'est pas une séance close.
	 */
	public function testSeeSameSecond(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eLesson, $eEnrollment] = $this->seedReading();

		$this->setClock('2026-09-12 09:14:00');
		ReadingLib::see($eEnrollment, $eLesson);
		ReadingLib::see($eEnrollment, $eLesson);

		$this->assertEquals([['2026-09-12 09:14:00', '2026-09-12 09:14:00', 0]], $this->getReadings($eCourse), 'deux signaux dans la même seconde, une séance');

		ReadingLib::see($eEnrollment, $eLesson);

		$this->assertEquals([['2026-09-12 09:14:00', '2026-09-12 09:14:00', 0]], $this->getReadings($eCourse), 'trois signaux dans la même seconde, une séance');

		$this->setClock('2026-09-12 09:14:30');
		ReadingLib::see($eEnrollment, $eLesson);
		ReadingLib::see($eEnrollment, $eLesson);

		$this->assertEquals([['2026-09-12 09:14:00', '2026-09-12 09:14:30', 30]], $this->getReadings($eCourse), 'deux onglets 30 s plus tard ajoutent 30 s une seule fois');

	}

	/**
	 * Ni une formation de l'équipe, ni un compte sans inscription ne tiennent de journal.
	 */
	public function testSeeOutOfScope(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eLesson] = $this->seedReading();

		$this->assertFalse(ReadingLib::see(new Enrollment(), $eLesson), 'sans inscription, rien');
		$this->assertEquals([], $this->getReadings($eCourse), 'aucune séance sans inscription');

		$eCourseTeam = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::PRIVATE);
		$eLessonTeam = $this->createLessonWritten($this->createChapter($eCourseTeam, 'Module'), 'Leçon', 5);

		$eEnrollmentTeam = new Enrollment(['user' => $this->eUserReader, 'course' => $eCourseTeam, 'structure' => new \structure\Structure()]);
		EnrollmentLib::create($eEnrollmentTeam);

		$this->assertFalse(ReadingLib::see(EnrollmentLib::getByUserAndCourse($this->eUserReader, $eCourseTeam), $eLessonTeam), 'une formation de l\'équipe ne tient pas de journal');
		$this->assertEquals([], $this->getReadings($eCourseTeam), 'aucune séance sur la formation de l\'équipe');

	}

	/**
	 * Le nœud du signal porte le plafond d'une vidéo lancée : la durée estimée de la leçon en
	 * secondes, et le délai d'inactivité pour une leçon sans durée. Ce que le script en fait n'est pas
	 * éprouvé ici, aucun test n'exécutant `learning.js`.
	 */
	public function testVideoLimit(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[, $eLesson] = $this->seedReading();

		$this->actAs($this->eUserReader);

		$getNode = function() use($eLesson): array {

			preg_match('/data-lesson="'.$eLesson['id'].'" data-period="([0-9]+)" data-idle="([0-9]+)" data-video-limit="([0-9]+)" /', $this->renderRoute('formation/lecon/'.$eLesson['id'], view: 'learning/lesson'), $match);

			return array_slice($match, 1);

		};

		$this->assertEquals([(string)ReadingLib::PERIOD, (string)ReadingLib::IDLE, '480'], $getNode(), 'une leçon de 8 minutes plafonne une vidéo lancée à 480 secondes');

		// Sans `build()` : `Lesson::minutes.classified` refuse une leçon sans durée dans une formation
		// publiée, et c'est le cas éprouvé, celui d'une leçon écrite avant que la formation le soit.
		$eLesson['minutes'] = NULL;
		LessonLib::update($eLesson, ['minutes']);

		$this->assertEquals([(string)ReadingLib::PERIOD, (string)ReadingLib::IDLE, (string)ReadingLib::IDLE], $getNode(), 'une leçon sans durée garde le délai d\'inactivité');

	}

	/**
	 * Le signal de la leçon, tel que `LearningReading` le poste : accepté pour l'inscrit, refusé
	 * pour un membre de la même ferme qui lit la leçon sans s'être inscrit.
	 */
	public function testDoSee(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eLesson] = $this->seedReading();

		$eUserMember = $this->createAccount('zztest.lecture-non-inscrit');
		$this->join($eUserMember, $this->eStructureFarm);

		$this->actAs($eUserMember);

		[$action] = $this->executePage('learning/reading:doSee', ['lesson' => (string)$eLesson['id']]);
		$this->assertEquals(\NotExpectedAction::class, $action, 'un membre non inscrit ne signale rien : il ne lit pas la formation, que l\'inscription seule lui ouvre');
		$this->assertEquals([], $this->getReadings($eCourse), 'et n\'écrit rien');

		$this->actAs($this->eUserReader);

		$this->assertEquals([\VoidAction::class, NULL], $this->executePage('learning/reading:doSee', ['lesson' => (string)$eLesson['id']]), 'l\'inscrit signale');
		$this->assertEquals(1, count($this->getReadings($eCourse)), 'une séance ouverte par le signal');

		$eCourseTeam = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::PRIVATE);
		$eLessonTeam = $this->createLessonWritten($this->createChapter($eCourseTeam, 'Module'), 'Leçon', 5);
		EnrollmentLib::create(new Enrollment(['user' => $this->eUserReader, 'course' => $eCourseTeam, 'structure' => new \structure\Structure()]));

		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/reading:doSee', ['lesson' => (string)$eLessonTeam['id']]), 'l\'inscrit d\'une formation de l\'équipe ne signale rien');
		$this->assertEquals([], $this->getReadings($eCourseTeam), 'et n\'écrit rien');

	}

	/**
	 * Le journal d'un stagiaire ne s'ouvre à l'encadrant que pour un compte qu'il suit, et « Mon
	 * suivi » qu'à un inscrit.
	 */
	public function testJournalScope(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedReading();

		$eUserMember = $this->createAccount('zztest.journal-non-inscrit');
		$this->join($eUserMember, $this->eStructureFarm);

		$this->actAs($this->eUserAdmin);

		$this->assertEquals(\NotAllowedAction::class, $this->executeView('learning/progress:journal', ['id' => (string)$eCourse['id'], 'user' => (string)$eUserMember['id']]), 'un compte que l\'encadrant ne suit pas');

		$data = $this->executeView('learning/progress:journal', ['id' => (string)$eCourse['id'], 'user' => (string)$this->eUserReader['id']]);
		$this->assertTrue($data instanceof \stdClass, 'le stagiaire inscrit');
		$this->assertEquals($this->eUserReader['id'], $data->eUser['id'], 'son journal');

		$this->actAs($eUserMember);

		$this->assertTrue(is_string($this->executeView('learning/progress:mine', ['id' => (string)$eCourse['id']])), 'sans inscription, pas de « Mon suivi »');
		$this->assertTrue(is_string($this->executeView('learning/progress:journal', ['id' => (string)$eCourse['id'], 'user' => (string)$this->eUserReader['id']])), 'ni le journal d\'un autre');

		$this->actAs($this->eUserReader);

		$data = $this->executeView('learning/progress:mine', ['id' => (string)$eCourse['id']]);
		$this->assertTrue($data instanceof \stdClass, 'l\'inscrit relit son suivi');
		$this->assertEquals($this->eUserReader['id'], $data->eUser['id'], 'le sien');

	}

	/**
	 * Le journal range chaque séance à son jour, y rattache la déclaration et les deux tentatives
	 * du quiz qu'elle encadre, et met le constat de l'équipe, avec son auteur, sur une ligne à lui.
	 * Le résumé compte le temps de toutes les lectures, validées ou non.
	 */
	public function testJournal(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eLessonFirst, $eEnrollment, $eChapter] = $this->seedReading();

		$eLessonQuiz = $this->createLessonWritten($eChapter, 'Leçon à quiz', 10);
		$eLessonOpen = $this->createLessonWritten($eChapter, 'Leçon ouverte', 5);

		$this->createQuestion($eLessonQuiz);
		$this->createQuestion($eLessonQuiz);

		$eLessonQuiz = $this->getLesson($eLessonQuiz['id']);
		$eLessonQuiz['quizThreshold'] = 100;

		// La première question se rate à la première tentative, la seconde est toujours juste. Son id
		// se lit avant la boucle : `first()` dans un parcours de la même collection la relancerait.
		$cQuestion = QuestionLib::getByLesson($eLessonQuiz);
		$questionMissed = $cQuestion->first()['id'];

		$answers = fn(bool $allRight) => array_combine(
			$cQuestion->getIds(),
			$cQuestion->makeArray(fn(Question $eQuestion) => [$eQuestion['cChoice']->find(fn(Choice $eChoice) => $eChoice['correct'] === ($allRight or $eQuestion['id'] !== $questionMissed))->first()['id']])
		);

		// Samedi : la première leçon lue 11 min puis déclarée.
		$this->read($eEnrollment, $eLessonFirst, '2026-09-12 09:14:00', 11);
		$this->setClock('2026-09-12 09:25:10');
		ProgressLib::done($eEnrollment, $eLessonFirst);

		// Mardi : le quiz, raté puis réussi dans la même séance.
		$this->read($eEnrollment, $eLessonQuiz, '2026-09-15 20:05:00', 24);
		$this->setClock('2026-09-15 20:26:00');
		ResponseLib::answer($eEnrollment, $eLessonQuiz, $cQuestion, $answers(FALSE));
		$this->setClock('2026-09-15 20:29:00');
		ResponseLib::answer($eEnrollment, $eLessonQuiz, $cQuestion, $answers(TRUE));

		// Vendredi : une leçon ouverte 3 min, jamais validée.
		$this->read($eEnrollment, $eLessonOpen, '2026-09-18 12:10:00', 3);

		// Lundi : l'encadrant constate la première leçon.
		$this->actAs($this->eUserAdmin);
		$this->setClock('2026-09-21 10:02:00');
		ProgressLib::check($eCourse, $eLessonFirst, $this->eUserReader);

		$cChapter = ChapterLib::getByCourse($eCourse);
		$eCourse['lessons'] = ChapterLib::countLessons($cChapter);

		$eUser = $this->getUser($this->eUserReader['id']);
		$cUser = new \Collection([$eUser['id'] => $eUser]);

		ProgressLib::fillTrack($eCourse, $cChapter, $cUser);
		ProgressLib::fillDetail($eCourse, $cChapter, $cUser);
		ProgressLib::fillSummary($cChapter, $cUser);
		DateEnrollmentLib::fillAttended($eCourse, $cUser);
		ReadingLib::fillMeasured($eCourse, $cChapter, $cUser);

		$this->assertEquals(
			[2, 18, 3, 38, 35],
			[$eUser['validated'], $eUser['minutesValidated'], $eUser['readings'], $eUser['minutesMeasuredAll'], $eUser['minutesMeasuredValidated']],
			'deux leçons validées pour 18 min estimées, 38 min mesurées en trois lectures dont 35 sur les leçons validées'
		);

		$journal = ReadingLib::getJournal($eCourse, $cChapter, $eUser);

		$this->assertEquals(['2026-09-12', '2026-09-15', '2026-09-18', '2026-09-21'], array_keys($journal), 'quatre jours, du plus ancien au plus récent');

		$summary = [];

		foreach($journal as $rows) {
			foreach($rows as $row) {
				$summary[] = [
					$row['at'],
					$row['eLesson']['id'],
					$row['minutes'],
					array_map(fn(array $event) => [$event['type'], $event['at'], $event['right'] ?? NULL], $row['events'])
				];
			}
		}

		$this->assertEquals([
			['2026-09-12 09:14:00', $eLessonFirst['id'], 11, [[ProgressLib::TRACK_DECLARED, '2026-09-12 09:25:10', NULL]]],
			['2026-09-15 20:05:00', $eLessonQuiz['id'], 24, [[ProgressLib::TRACK_FAILED, '2026-09-15 20:26:00', 1], [ProgressLib::TRACK_PASSED, '2026-09-15 20:29:00', 2]]],
			['2026-09-18 12:10:00', $eLessonOpen['id'], 3, []],
			['2026-09-21 10:02:00', $eLessonFirst['id'], NULL, [[ReadingLib::EVENT_CHECKED, '2026-09-21 10:02:00', NULL]]],
		], $summary, 'chaque événement à sa séance, le constat sur sa ligne sans durée');

		$this->assertEquals($this->eUserAdmin['id'], $journal['2026-09-21'][0]['events'][0]['eUser']['id'], 'le constat nomme son auteur');

		$this->assertTrue(new ReadingUi()->getPanel($eCourse, $cChapter, $eUser, $journal, TRUE)->body !== '', 'le panneau se rend');

	}

	/**
	 * Une séance de moins d'une minute se dit « moins d'1 min » dans la colonne Lecture, et non
	 * « 0 min » ni « 1 min » par l'arrondi : une ouverture sans signal, une séance de 45 s, puis une
	 * séance d'une minute pile, qui garde sa durée.
	 */
	public function testJournalShortReading(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eLesson, $eEnrollment] = $this->seedReading();

		$this->setClock('2026-09-12 09:00:00');
		ReadingLib::see($eEnrollment, $eLesson);

		$this->setClock('2026-09-13 10:00:00');
		ReadingLib::see($eEnrollment, $eLesson);
		$this->setClock('2026-09-13 10:00:45');
		ReadingLib::see($eEnrollment, $eLesson);

		$this->setClock('2026-09-14 10:30:00');
		ReadingLib::see($eEnrollment, $eLesson);
		$this->setClock('2026-09-14 10:31:00');
		ReadingLib::see($eEnrollment, $eLesson);

		$cChapter = ChapterLib::getByCourse($eCourse);
		$eCourse['lessons'] = ChapterLib::countLessons($cChapter);

		$eUser = $this->getUser($this->eUserReader['id']);
		$cUser = new \Collection([$eUser['id'] => $eUser]);

		ProgressLib::fillTrack($eCourse, $cChapter, $cUser);
		ProgressLib::fillDetail($eCourse, $cChapter, $cUser);

		$rows = array_merge(...array_values(ReadingLib::getJournal($eCourse, $cChapter, $eUser)));

		$this->assertEquals(
			[s("moins d'1&nbsp;min"), s("moins d'1&nbsp;min"), CourseUi::getDuration(1)],
			array_map(ReadingUi::getLength(...), $rows),
			'0 s et 45 s se disent « moins d\'1 min », 60 s « 1 min »'
		);

	}

	/**
	 * La dernière activité de chaque stagiaire, vide pour qui n'a rien ouvert, passe dans la grille
	 * et dans l'export.
	 */
	public function testLastSeen(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eLesson, $eEnrollment] = $this->seedReading();

		EnrollmentLib::create(new Enrollment(['user' => $this->eUserAdmin, 'course' => $eCourse, 'structure' => new \structure\Structure()]));

		$this->read($eEnrollment, $eLesson, '2026-09-12 09:14:00', 11);
		$this->read($eEnrollment, $eLesson, '2026-09-17 18:40:00', 6);

		$cChapter = ChapterLib::getByCourse($eCourse);
		$eCourse['lessons'] = ChapterLib::countLessons($cChapter);
		$eCourse['shares'] = ShareLib::countByCourse($eCourse);

		$cUser = EnrollmentLib::getLearners($eCourse);
		ProgressLib::fillTrack($eCourse, $cChapter, $cUser);
		ReadingLib::fillLastSeen($eCourse, $cUser);

		$this->assertEquals('2026-09-17 18:46:00', $cUser[$this->eUserReader['id']]['lastSeenAt'], 'le dernier signal du lecteur');
		$this->assertEquals(NULL, $cUser[$this->eUserAdmin['id']]['lastSeenAt'], 'rien pour qui n\'a rien ouvert');

		$html = new ProgressUi()->getTrack($this->eStructureNetwork, $eCourse, $cChapter, new \Collection(), new \structure\Section(), $cUser);

		$this->assertTrue(str_contains($html, '<a href="'.ReadingUi::urlJournal($eCourse, $this->eUserReader).'">'), 'le nom ouvre le journal');

		$lines = new ProgressUi()->getExport($cChapter, $cUser);
		$header = array_shift($lines);

		$this->assertEquals(s("Dernière activité"), $header[3], 'la colonne de l\'export');

		$byName = [];

		foreach($lines as $line) {
			$byName[$line[0]] = $line[3];
		}

		$this->assertEquals('17/09/2026', $byName[decode(\user\UserUi::name($this->eUserReader))], 'datée en entier');
		$this->assertEquals('', $byName[decode(\user\UserUi::name($this->eUserAdmin))], 'vide sans lecture');

	}

	/**
	 * Le relevé de suivi (lot L8d) sur le cas de la planche L8 : Manon Petit, cinq leçons estimées
	 * 8, 13, 18, 8 et 13 min, lues 11, 16, 24, 6 et 3 min, plus une séance rouverte sans signal sur
	 * la quatrième. Quatre leçons validées : la première déclarée puis constatée, la deuxième et la
	 * quatrième déclarées, la troisième réussie au quiz à la seconde tentative. La cinquième, ouverte
	 * sans être validée, compte dans le temps mesuré et pas dans la durée retenue.
	 *
	 * Une seule définition du temps mesuré (porte L8, reprise 1) : la figure « Temps mesuré » et la
	 * ligne « Toutes les leçons » disent la même valeur, celle du panneau du journal, et la ligne
	 * « Leçons validées » est la seule à ne sommer que les leçons validées.
	 */
	public function testReport(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $cChapter, $eUser, $journal] = $this->seedReport();

		$this->assertEquals(
			[4, 47, 60, 6, 60, 57],
			[$eUser['validated'], $eUser['minutesValidated'], $eUser['minutesAll'], $eUser['readings'], $eUser['minutesMeasuredAll'], $eUser['minutesMeasuredValidated']],
			'quatre leçons validées sur cinq, 47 min retenues sur 60, 60 min mesurées en six lectures dont 57 sur les leçons validées'
		);

		$this->assertEquals([11, 16, 24, 6, 3], array_values($eUser['measured']), 'le temps de chaque leçon, les deux séances de la quatrième sommées');

		$html = new ReportUi()->get($eCourse, $cChapter, $eUser, \structure\StructureLib::getById($eCourse['community']), $journal, '2026-09-27');

		preg_match('/<div class="pdf-figure-value">([^<]*)<\/div><div class="pdf-figure-label">'.preg_quote(encode(s("Temps mesuré")), '/').'<\/div>/', $html, $figure);
		$totals = $this->getReportTotals($html);

		$this->assertEquals(
			[
				s("Leçons validées") => [CourseUi::getDuration(47), CourseUi::getDuration(57)],
				s("Toutes les leçons") => [CourseUi::getDuration(60), CourseUi::getDuration(60)],
			],
			$totals,
			'les deux lignes de total, durée estimée puis temps mesuré'
		);

		$this->assertEquals($totals[s("Toutes les leçons")][1], $figure[1] ?? NULL, 'la figure « Temps mesuré » est la ligne « Toutes les leçons »');

		$this->assertTrue(str_contains($html, '<div class="pdf-figure-value">'.s("{validated} sur {lessons}", ['validated' => 4, 'lessons' => 5]).'</div>'), 'quatre leçons validées sur cinq');
		$this->assertTrue(str_contains($html, '<div class="pdf-figure-value">'.CourseUi::getDuration(47).'</div>'), 'la durée retenue');

		$this->assertEquals(
			[
				[s("{state}, constatée", ['state' => s("Déclarée")]), '12/09/2026', CourseUi::getDuration(11)],
				[s("Déclarée"), '12/09/2026', CourseUi::getDuration(16)],
				[s("Réussie au quiz, {value}", s("{right} sur {questions}", ['right' => 4, 'questions' => 5])), '15/09/2026', CourseUi::getDuration(24)],
				[s("Déclarée"), '17/09/2026', CourseUi::getDuration(6)],
				['', '', CourseUi::getDuration(3)],
			],
			$this->getReportLessons($html),
			'la validation, sa date et le temps mesuré de chaque leçon'
		);

		$this->assertTrue(str_contains($html, s("Relevé établi le {date} à partir du journal de lecture de la formation.", ['date' => '27/09/2026'])), 'la date du relevé en pied');
		$this->assertTrue(str_contains($html, s("Une leçon déclarée l'est par le stagiaire, une leçon constatée l'est par l'équipe du réseau.")), 'la note d\'une formation de réseau');

		// L'annexe : un titre par jour, les événements en toutes lettres.
		$this->assertTrue(str_contains($html, '<div class="util-print-break-after">'), 'l\'annexe commence sur sa page');
		$this->assertTrue(str_contains($html, '<td colspan="4"><b>'.ReadingUi::getDay('2026-09-12').'</b></td>'), 'le premier jour');
		$this->assertTrue(str_contains($html, '<div>'.s("Quiz à refaire, {score} à {time}", ['score' => s("{right} sur {questions}", ['right' => 2, 'questions' => 5]), 'time' => '20:26']).'</div><div>'.s("Réussie au quiz, {score} à {time}", ['score' => s("{right} sur {questions}", ['right' => 4, 'questions' => 5]), 'time' => '20:29']).'</div>'), 'les deux tentatives dans la même séance, une par ligne');
		$this->assertTrue(str_contains($html, s("Constatée par {value}", \user\UserUi::name($this->eUserAdmin))), 'le constat nomme son auteur');
		$this->assertEquals(7, substr_count($html, '<tr><td>'), 'six séances et le constat');

		// Comme le panneau : le statut a sa colonne, la cellule Leçon ne porte que la leçon.
		$this->assertTrue(str_contains($html, '<th>'.s("Leçon").'</th><th>'.s("Statut").'</th>'), 'la colonne Statut de l\'annexe, à droite de la leçon');

		preg_match_all('/<tr><td>(\d\d:\d\d)<\/td><td>(.*?)<\/td><td>(.*?)<\/td><td class="text-end">.*?<\/td><\/tr>/', $html, $cells, PREG_SET_ORDER);

		$expected = [];

		foreach($journal as $rows) {
			foreach($rows as $row) {
				$expected[] = [encode($row['eLesson']['number'].'. '.$row['eLesson']['name']), count($row['events'])];
			}
		}

		$this->assertEquals(
			$expected,
			array_map(fn(array $cell) => [$cell[2], substr_count($cell[3], '<div>')], $cells),
			'quatre colonnes par ligne de l\'annexe : la leçon seule, puis un statut par événement'
		);

		$this->assertEquals(
			$cChapter->count() + count($journal),
			substr_count($html, '<tbody class="util-print-break-inside-avoid"><tr class="tr-title">'),
			'un bloc insécable par module et par jour, ouvert par son titre : une ligne ne commence pas une page sans lui'
		);
		$this->assertEquals($cChapter->count() + count($journal) + 1, substr_count($html, '<tbody'), 'et un dernier bloc pour les totaux');

		// Le titre d'un module suit le type de la formation (`CourseUi::getChapterName()`). Le type est
		// posé sur une copie de l'élément, sans `build()` ni écriture : `build()` jugerait un changement
		// de type, quand seul le rendu du même programme sous chaque type est éprouvé ici.
		$names = array_map(encode(...), array_values($cChapter->getColumn('name')));

		foreach([
			Course::ONLINE => array_map(fn(int $position, string $name) => s("Module {number}&nbsp;· {name}", ['number' => $position + 1, 'name' => $name]), array_keys($names), $names),
			Course::REMOTE => $names,
			Course::ONSITE => $names,
		] as $type => $expected) {

			$eCourseTyped = clone $eCourse;
			$eCourseTyped['type'] = $type;

			preg_match_all('#<tr class="tr-title"><td colspan="5"><b>(.*?)</b></td></tr>#', new ReportUi()->get($eCourseTyped, $cChapter, $eUser, \structure\StructureLib::getById($eCourse['community']), $journal, '2026-09-27'), $titles);

			$this->assertEquals($expected, $titles[1], $type.', le titre de chaque module du relevé');

		}

	}

	/**
	 * Un stagiaire qui n'a rien ouvert a un relevé d'une page, sans annexe ni saut de page, et des
	 * temps mesurés en tiret : une leçon jamais ouverte n'a pas de temps.
	 */
	public function testReportEmpty(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedReading();

		$cChapter = ChapterLib::getByCourse($eCourse);
		$eCourse['lessons'] = ChapterLib::countLessons($cChapter);

		$eUser = $this->getUser($this->eUserReader['id']);
		$cUser = new \Collection([$eUser['id'] => $eUser]);

		ProgressLib::fillTrack($eCourse, $cChapter, $cUser);
		ProgressLib::fillDetail($eCourse, $cChapter, $cUser);
		ProgressLib::fillSummary($cChapter, $cUser);
		DateEnrollmentLib::fillAttended($eCourse, $cUser);
		ReadingLib::fillMeasured($eCourse, $cChapter, $cUser);

		$journal = ReadingLib::getJournal($eCourse, $cChapter, $eUser);

		$this->assertEquals([], $journal, 'aucun journal');
		$this->assertEquals([NULL], array_values($eUser['measured']), 'pas de temps pour une leçon jamais ouverte');

		$html = new ReportUi()->get($eCourse, $cChapter, $eUser, \structure\StructureLib::getById($eCourse['community']), $journal, '2026-09-27');

		$this->assertTrue(str_contains($html, '<div class="pdf-figure-value">'.s("Aucune lecture").'</div><div class="pdf-figure-label">'.encode(s("Temps mesuré")).'</div>'), 'la figure dit « Aucune lecture », comme le panneau');
		$this->assertEquals(
			[
				s("Leçons validées") => [CourseUi::getDuration(0), CourseUi::getDuration(NULL)],
				s("Toutes les leçons") => [CourseUi::getDuration(8), CourseUi::getDuration(NULL)],
			],
			$this->getReportTotals($html),
			'aucun temps mesuré dans les totaux, pas « 0 min »'
		);
		$this->assertFalse(str_contains($html, 'util-print-break-after'), 'pas de saut de page');
		$this->assertFalse(str_contains($html, s("Journal des lectures")), 'pas d\'annexe');
		$this->assertEquals([['', '', CourseUi::getDuration(NULL)]], $this->getReportLessons($html), 'le temps de la leçon en tiret');

	}

	/**
	 * Le seuil « moins d'1 min » se lit sur les secondes mesurées, dans le programme comme dans les
	 * totaux, et non sur des minutes déjà arrondies : trois leçons lues 0 s (ouverte sans un signal),
	 * 45 s et 60 s, les deux premières déclarées. 45 s ferait « 1 min » par l'arrondi, alors que
	 * l'annexe dit « moins d'1 min » pour la même séance ; 45 s validées aussi ; 105 s en tout font
	 * 2 min.
	 */
	public function testReportUnderOneMinute(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eLesson1, $eEnrollment, $eChapter] = $this->seedReading();

		$eLesson2 = $this->createLessonWritten($eChapter, 'Leçon de 45 s', 5);
		$eLesson3 = $this->createLessonWritten($eChapter, 'Leçon de 60 s', 5);

		$this->setClock('2026-09-12 09:00:00');
		ReadingLib::see($eEnrollment, $eLesson1);
		ProgressLib::done($eEnrollment, $eLesson1);

		$this->setClock('2026-09-12 10:00:00');
		ReadingLib::see($eEnrollment, $eLesson2);
		$this->setClock('2026-09-12 10:00:45');
		ReadingLib::see($eEnrollment, $eLesson2);
		ProgressLib::done($eEnrollment, $eLesson2);

		$this->setClock('2026-09-12 11:00:00');
		ReadingLib::see($eEnrollment, $eLesson3);
		$this->setClock('2026-09-12 11:00:30');
		ReadingLib::see($eEnrollment, $eLesson3);
		$this->setClock('2026-09-12 11:01:00');
		ReadingLib::see($eEnrollment, $eLesson3);

		$cChapter = ChapterLib::getByCourse($eCourse);
		$eCourse['lessons'] = ChapterLib::countLessons($cChapter);

		$eUser = $this->getUser($this->eUserReader['id']);
		$cUser = new \Collection([$eUser['id'] => $eUser]);

		ProgressLib::fillTrack($eCourse, $cChapter, $cUser);
		ProgressLib::fillDetail($eCourse, $cChapter, $cUser);
		ProgressLib::fillSummary($cChapter, $cUser);
		DateEnrollmentLib::fillAttended($eCourse, $cUser);
		ReadingLib::fillMeasured($eCourse, $cChapter, $cUser);

		$this->assertEquals([0, 45, 60], array_values($eUser['measuredSeconds']), 'les secondes de chaque leçon');

		$html = new ReportUi()->get($eCourse, $cChapter, $eUser, \structure\StructureLib::getById($eCourse['community']), ReadingLib::getJournal($eCourse, $cChapter, $eUser), '2026-09-27');

		$under = s("moins d'1&nbsp;min");

		$this->assertEquals(
			[$under, $under, CourseUi::getDuration(1)],
			array_column($this->getReportLessons($html), 2),
			'0 s et 45 s font « moins d\'1 min », 60 s « 1 min »'
		);

		$this->assertEquals(
			[
				s("Leçons validées") => [CourseUi::getDuration(13), $under],
				s("Toutes les leçons") => [CourseUi::getDuration(18), CourseUi::getDuration(2)],
			],
			$this->getReportTotals($html),
			'45 s validées font « moins d\'1 min », 105 s en tout 2 min'
		);

		$this->assertTrue(str_contains($html, '<div class="pdf-figure-value">'.CourseUi::getDuration(2).'</div><div class="pdf-figure-label">'.encode(s("Temps mesuré")).'</div>'), 'la figure est la ligne « Toutes les leçons »');

	}

	/**
	 * Le panneau dit le temps mesuré comme le relevé : une seule lecture de 45 s fait « moins d'1 min »,
	 * et non « 1 min » par l'arrondi des minutes, qui ferait dire au panneau autre chose que le PDF.
	 */
	public function testPanelUnderOneMinute(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eLesson, $eEnrollment] = $this->seedReading();

		$this->setClock('2026-09-12 10:00:00');
		ReadingLib::see($eEnrollment, $eLesson);
		$this->setClock('2026-09-12 10:00:45');
		ReadingLib::see($eEnrollment, $eLesson);

		$cChapter = ChapterLib::getByCourse($eCourse);
		$eCourse['lessons'] = ChapterLib::countLessons($cChapter);

		$eUser = $this->getUser($this->eUserReader['id']);
		$cUser = new \Collection([$eUser['id'] => $eUser]);

		ProgressLib::fillTrack($eCourse, $cChapter, $cUser);
		ProgressLib::fillDetail($eCourse, $cChapter, $cUser);
		ProgressLib::fillSummary($cChapter, $cUser);
		DateEnrollmentLib::fillAttended($eCourse, $cUser);
		ReadingLib::fillMeasured($eCourse, $cChapter, $cUser);

		$this->assertEquals([45, 1], [$eUser['secondsMeasuredAll'], $eUser['minutesMeasuredAll']], '45 s mesurées, que l\'arrondi porte à 1 min');

		$this->assertEquals(s("moins d'1&nbsp;min"), ReadingUi::getMeasured($eUser['secondsMeasuredAll'], $eUser['minutesMeasuredAll']), '45 s font « moins d\'1 min » dans le résumé du panneau');

	}

	/**
	 * Le relevé de l'équipe ne se tire que pour un stagiaire que l'encadrant suit, et celui du
	 * stagiaire que pour un inscrit. La réussite n'est pas jouée : elle rend le PDF par le navigateur
	 * sans tête, sur une base que la transaction du test n'a pas validée.
	 */
	public function testReportScope(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedReading();

		$eUserMember = $this->createAccount('zztest.releve-non-inscrit');
		$this->join($eUserMember, $this->eStructureFarm);

		$this->actAs($this->eUserAdmin);

		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('learning/progress:report', ['id' => (string)$eCourse['id'], 'user' => (string)$eUserMember['id']], 'GET'), 'un compte que l\'encadrant ne suit pas');

		$this->actAs($eUserMember);

		$this->assertEquals([\NotExistsAction::class, NULL], $this->executePage('learning/progress:reportMine', ['id' => (string)$eCourse['id']], 'GET'), 'sans inscription, pas de relevé');
		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('learning/progress:report', ['id' => (string)$eCourse['id'], 'user' => (string)$this->eUserReader['id']], 'GET'), 'ni celui d\'un autre');

	}

	/**
	 * L'export détaillé porte le temps mesuré de chaque leçon, celui du relevé, vide pour une leçon
	 * jamais ouverte. Un second inscrit, qui n'a rien lu, garde ses cases vides : le temps se pose
	 * sur le compte, pas sur la leçon que les deux partagent.
	 */
	public function testExportDetailMeasured(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedReport();

		EnrollmentLib::create(new Enrollment(['user' => $this->eUserAdmin, 'course' => $eCourse, 'structure' => new \structure\Structure()]));

		$cChapter = ChapterLib::getByCourse($eCourse);
		$cUser = EnrollmentLib::getLearners($eCourse);

		ProgressLib::fillTrack($eCourse, $cChapter, $cUser);
		ProgressLib::fillDetail($eCourse, $cChapter, $cUser);
		ReadingLib::fillMeasured($eCourse, $cChapter, $cUser);

		$lines = new ProgressUi()->getExportDetail($cChapter, $cUser);
		$header = array_shift($lines);

		$column = array_search(s("Temps mesuré (min)"), $header, TRUE);

		$this->assertEquals(array_search(s("Durée estimée (min)"), $header, TRUE) + 1, $column, 'la colonne suit la durée estimée');

		$byName = [];

		foreach($lines as $line) {
			$byName[$line[0]][] = $line[$column];
		}

		$this->assertEquals([11, 16, 24, 6, 3], $byName[decode(\user\UserUi::name($this->eUserReader))], 'le temps de chaque leçon du lecteur');
		$this->assertEquals(['', '', '', '', ''], $byName[decode(\user\UserUi::name($this->eUserAdmin))], 'vide pour qui n\'a rien ouvert');

	}

	/**
	 * Une leçon retirée emporte ses séances, une inscription retirée celles du compte.
	 */
	public function testDelete(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eLesson, $eEnrollment, $eChapter] = $this->seedReading();

		$eLessonOther = $this->createLessonWritten($eChapter, 'Autre leçon', 5);

		$this->read($eEnrollment, $eLesson, '2026-09-12 09:14:00', 2);
		$this->read($eEnrollment, $eLessonOther, '2026-09-12 10:00:00', 2);

		LessonLib::deleteWithCourse($this->getLesson($eLessonOther['id']));

		$this->assertEquals([['2026-09-12 09:14:00', '2026-09-12 09:16:00', 120]], $this->getReadings($eCourse), 'la séance de la leçon retirée part avec elle');

		EnrollmentLib::delete(EnrollmentLib::getByUserAndCourse($this->eUserReader, $eCourse));

		$this->assertEquals([], $this->getReadings($eCourse), 'l\'inscription retirée emporte le reste');

	}

	/**
	 * Une formation du réseau ouverte à toutes les fermes, un module, une leçon, et le lecteur
	 * inscrit au nom de sa ferme.
	 */
	private function seedReading(): array {

		$eCourse = $this->seed();
		$eChapter = $this->createChapter($eCourse, 'Module');
		$eLesson = $this->createLessonWritten($eChapter, 'Leçon', 8);
		$eCourse = $this->getCourse($eCourse['id']);

		$this->shareNetwork($eCourse);

		EnrollmentLib::create(new Enrollment(['user' => $this->eUserReader, 'course' => $eCourse, 'structure' => $this->eStructureFarm]));

		return [$eCourse, $eLesson, EnrollmentLib::getByUserAndCourse($this->eUserReader, $eCourse), $eChapter];

	}

	/**
	 * Le cas de la planche L8 (bloc 6 A), sur deux modules, relu comme le relevé le lit. Rend la
	 * formation, son programme, le lecteur et son journal.
	 */
	private function seedReport(): array {

		[$eCourse, $eLesson1, $eEnrollment, $eChapter] = $this->seedReading();

		$eLesson2 = $this->createLessonWritten($eChapter, 'Le cahier des charges', 13);
		$eLesson3 = $this->createLessonWritten($eChapter, 'Le contrôle', 18);

		$eChapterSecond = $this->createChapter($eCourse, 'Après la certification');
		$eLesson4 = $this->createLessonWritten($eChapterSecond, 'La mention', 8);
		$eLesson5 = $this->createLessonWritten($eChapterSecond, 'Le renouvellement', 13);

		foreach(range(1, 5) as $question) {
			$this->createQuestion($eLesson3);
		}

		$eLesson3 = $this->getLesson($eLesson3['id']);
		$eLesson3['quizThreshold'] = 80;

		// Les `$right` premières questions justes, les suivantes fausses.
		$cQuestion = QuestionLib::getByLesson($eLesson3);
		$questions = $cQuestion->getIds();

		$answers = fn(int $right) => array_combine(
			$questions,
			$cQuestion->makeArray(fn(Question $eQuestion) => [$eQuestion['cChoice']->find(fn(Choice $eChoice) => $eChoice['correct'] === (array_search($eQuestion['id'], $questions, TRUE) < $right))->first()['id']])
		);

		$this->read($eEnrollment, $eLesson1, '2026-09-12 09:14:00', 11);
		$this->setClock('2026-09-12 09:25:10');
		ProgressLib::done($eEnrollment, $eLesson1);

		$this->read($eEnrollment, $eLesson2, '2026-09-12 09:27:00', 16);
		$this->setClock('2026-09-12 09:43:10');
		ProgressLib::done($eEnrollment, $eLesson2);

		$this->read($eEnrollment, $eLesson3, '2026-09-15 20:05:00', 24);
		$this->setClock('2026-09-15 20:26:00');
		ResponseLib::answer($eEnrollment, $eLesson3, $cQuestion, $answers(2));
		$this->setClock('2026-09-15 20:29:00');
		ResponseLib::answer($eEnrollment, $eLesson3, $cQuestion, $answers(4));

		$this->read($eEnrollment, $eLesson4, '2026-09-17 18:40:00', 6);
		$this->setClock('2026-09-17 18:46:10');
		ProgressLib::done($eEnrollment, $eLesson4);

		$this->read($eEnrollment, $eLesson5, '2026-09-18 12:10:00', 3);

		// La quatrième rouverte et quittée sans un signal : une séance de 0 s.
		$this->read($eEnrollment, $eLesson4, '2026-09-19 07:30:00', 0);

		$this->actAs($this->eUserAdmin);
		$this->setClock('2026-09-21 10:02:00');
		ProgressLib::check($eCourse, $eLesson1, $this->eUserReader);

		$cChapter = ChapterLib::getByCourse($eCourse);
		$eCourse['lessons'] = ChapterLib::countLessons($cChapter);

		$eUser = $this->getUser($this->eUserReader['id']);
		$cUser = new \Collection([$eUser['id'] => $eUser]);

		ProgressLib::fillTrack($eCourse, $cChapter, $cUser);
		ProgressLib::fillDetail($eCourse, $cChapter, $cUser);
		ProgressLib::fillSummary($cChapter, $cUser);
		DateEnrollmentLib::fillAttended($eCourse, $cUser);
		ReadingLib::fillMeasured($eCourse, $cChapter, $cUser);

		return [$eCourse, $cChapter, $eUser, ReadingLib::getJournal($eCourse, $cChapter, $eUser)];

	}

	/** Les deux lignes de total du relevé : libellé => [durée estimée, temps mesuré]. */
	private function getReportTotals(string $html): array {

		preg_match_all('/<tr class="tr-title"><td colspan="3"><b>(.*?)<\/b><\/td><td class="text-end"><b>(.*?)<\/b><\/td><td class="text-end"><b>(.*?)<\/b><\/td><\/tr>/', $html, $matches, PREG_SET_ORDER);

		return array_combine(array_column($matches, 1), array_map(fn(array $match) => [$match[2], $match[3]], $matches));

	}

	/** Chaque leçon du programme du relevé : [validation, validée le, temps mesuré]. */
	private function getReportLessons(string $html): array {

		preg_match_all('/<tr class="pdf-tr-ml-1"><td>.*?<\/td><td>(.*?)<\/td><td class="text-end">(.*?)<\/td><td class="text-end">.*?<\/td><td class="text-end">(.*?)<\/td><\/tr>/', $html, $matches, PREG_SET_ORDER);

		return array_map(fn(array $match) => [$match[1], $match[2], $match[3]], $matches);

	}

	/**
	 * Une séance comme la leçon la tient : l'ouverture, puis un signal toutes les 30 s pendant
	 * `$minutes`.
	 */
	private function read(Enrollment $eEnrollment, Lesson $eLesson, string $startedAt, int $minutes): void {

		$timestamp = strtotime($startedAt);

		for($elapsed = 0; $elapsed <= $minutes * 60; $elapsed += ReadingLib::PERIOD) {
			$this->setClock(date('Y-m-d H:i:s', $timestamp + $elapsed));
			ReadingLib::see($eEnrollment, $eLesson);
		}

	}

	/** Les séances du lecteur sur la formation : ouverture, dernier signal, secondes. */
	private function getReadings(Course $eCourse): array {

		return ReadingLib::getByUserAndCourse($this->eUserReader, $eCourse)
			->makeArray(fn(Reading $eReading) => [$eReading['startedAt'], $eReading['seenAt'], $eReading['seconds']]);

	}

	/** Une question à choix unique, deux choix dont le premier juste, écrite par le chemin du panneau. */
	private function createQuestion(Lesson $eLesson): void {

		$eQuestion = new Question(['lesson' => $this->getLesson($eLesson['id']), 'type' => Question::SINGLE, 'image' => NULL]);

		$fw = new \FailWatch();

		$eQuestion->build(QuestionLib::getPropertiesCreate(), [
			'statement' => 'Une question ?',
			'image' => '',
			'type' => Question::SINGLE,
			'explanation' => '',
		], new \PropertyList('create'));

		$cChoice = ChoiceLib::buildForQuestion($eQuestion, [
			'label' => ['Juste', 'Faux'],
			'correct' => ['1', '0'],
			'image' => ['', ''],
			'id' => ['', '']
		]);

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('question refusée : '.json_encode($fw->get())));

		QuestionLib::create($eQuestion, $cChoice);

	}

	/** Un compte `example.org`, domaine réservé où aucun envoi n'est délivré, écrit dans la transaction. */
	private function createAccount(string $email): \user\User {

		$eUser = new \user\User([
			'firstName' => 'Émile',
			'lastName' => 'zzTest',
			'email' => $email.'-'.uniqid().'@example.org',
			'visibility' => \user\User::PRIVATE,
			'roleCustomer' => FALSE,
			'roleProducer' => TRUE,
			'roleConsultant' => FALSE,
			'roleTeacher' => FALSE
		]);

		$fw = new \FailWatch();

		\user\UserLib::create($eUser);

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('création du compte '.$email.' refusée : '.json_encode($fw->get())));

		return $this->getUser($eUser['id']);

	}

}
?>
