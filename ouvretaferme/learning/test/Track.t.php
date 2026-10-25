<?php
namespace learning;

require_once __DIR__.'/LearningFixture.t.php';

/**
 * Le suivi des stagiaires d'une formation de groupe (D2), la case « Constatée » (F23) et l'export
 * (F28) : qui paraît dans le tableau, qui peut le voir et cocher, et ce que la case écrit.
 *
 * Le décor est écrit dans la transaction du test : deux promotions du groupe de formation de
 * l'utilisateur 1, trois stagiaires membres du groupe, un compte rangé dans une promotion sans être
 * membre. Les stagiaires sont des comptes `example.org` créés puis annulés avec le reste.
 */
class TrackTest extends LearningFixtureTest {

	/**
	 * Le tableau ne propose que les promotions auxquelles la formation est ouverte : une promotion
	 * du groupe non partagée n'y figure pas, une promotion supprimée non plus.
	 */
	public function testSections(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCommunity = $this->getCommunity(self::COMMUNITY_EDUCATION);
		$eCourse = $this->createCourse($eCommunity, Course::PUBLISHED, Course::PRIVATE);

		$this->assertTrue(ShareLib::getSectionsForTrack($eCourse, NULL)->empty(), 'aucune promotion partagée');

		$html = $this->renderTrack($eCommunity, $eCourse, new \Collection());
		$this->assertTrue(str_contains($html, 'href="/learning/course:share?id='.$eCourse['id'].'"'), 'aucune promotion, l\'écran propose d\'ouvrir à une promotion');

		$eSectionShared = $this->createSection($eCommunity, 'zzTest promotion partagée', []);
		$eSectionDeleted = $this->createSection($eCommunity, 'zzTest promotion supprimée', []);
		$this->createSection($eCommunity, 'zzTest promotion non partagée', []);

		$this->share($eCourse, [
			$eSectionShared['id'] => [\Clock::date(), NULL],
			$eSectionDeleted['id'] => [\Clock::date(), NULL]
		]);

		\structure\SectionLib::delete($eSectionDeleted);

		$this->assertEquals([$eSectionShared['id']], ShareLib::getSectionsForTrack($eCourse, NULL)->getIds(), 'la promotion partagée, seule');
		$this->assertEquals([], ShareLib::getSectionsForTrack($eCourse, [$eSectionDeleted['id']])->getIds(), 'une portée ne rouvre pas une promotion supprimée');

	}

	/**
	 * Les stagiaires sont les membres du groupe rangés dans les promotions partagées. Un compte
	 * rangé dans une promotion sans être membre n'y est pas, un membre parti non plus, bien que
	 * `Section.users` le garde.
	 */
	public function testLearners(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		[$eCommunity, $eCourse, $eSectionA, $eSectionB, $learners] = $this->createDecor();

		$cSection = ShareLib::getSectionsForTrack($eCourse, NULL);

		$this->assertEquals(
			$this->sorted([$learners['a1']['id'], $learners['a2']['id'], $learners['b']['id']]),
			$this->sorted(ShareLib::getLearners($eCourse, $cSection)->getIds()),
			'les membres des deux promotions, sans le compte qui n\'est pas membre'
		);

		$this->assertEquals(
			[$learners['b']['id']],
			ShareLib::getLearners($eCourse, $cSection, $cSection[$eSectionB['id']])->getIds(),
			'la promotion choisie'
		);

		$eMember = \structure\MemberLib::getByUserAndStructure($learners['a2'], $eCommunity);
		\structure\MemberLib::leave($eMember);

		$this->assertTrue(
			\structure\Section::model()->select(['users'])->whereId($eSectionA)->get()['users'] !== [],
			'la promotion garde le compte parti'
		);

		$this->assertEquals(
			[$learners['a1']['id']],
			ShareLib::getLearners($eCourse, $cSection, $cSection[$eSectionA['id']])->getIds(),
			'le membre parti n\'est plus un stagiaire'
		);

	}

	/**
	 * Cocher écrit le constat et lui seul : le statut déclaré, sa source et tous les compteurs de
	 * lecture restent ce qu'ils étaient. Une leçon jamais touchée reçoit une ligne en attente.
	 * Cocher deux fois revient à cocher une fois ; le stagiaire qui retire sa déclaration laisse le
	 * constat.
	 */
	public function testCheck(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		[$eCommunity, $eCourse, , , $learners, $lessons] = $this->createDecor();

		[$eLessonFirst, $eLessonSecond] = $lessons;

		$eUser = $learners['a1'];

		$eEnrollment = new Enrollment(['user' => $eUser, 'course' => $eCourse, 'structure' => new \structure\Structure()]);
		EnrollmentLib::create($eEnrollment);
		ProgressLib::done($eEnrollment, $eLessonFirst);

		$counters = $this->getCounters($eCourse, $eUser);

		ProgressLib::check($eCourse, $eLessonFirst, $eUser);
		ProgressLib::check($eCourse, $eLessonFirst, $eUser);

		$eProgress = $this->getProgress($eUser, $eLessonFirst);

		$this->assertEquals(1, Progress::model()->whereUser($eUser)->whereLesson($eLessonFirst)->count(), 'cochée deux fois, une ligne');
		$this->assertEquals([Progress::DONE, Progress::DECLARED, $this->eUserAdmin['id']], [$eProgress['status'], $eProgress['source'], $eProgress['checkedBy']['id']], 'le constat s\'ajoute à la déclaration');
		$this->assertTrue($eProgress['checkedAt'] !== NULL, 'avec sa date');

		ProgressLib::check($eCourse, $eLessonSecond, $eUser);

		$eProgress = $this->getProgress($eUser, $eLessonSecond);

		$this->assertEquals([Progress::PENDING, NULL], [$eProgress['status'], $eProgress['source']], 'une leçon jamais touchée reste en attente');
		$this->assertTrue($eProgress['checkedAt'] !== NULL, 'mais constatée');
		$this->assertEquals($counters, $this->getCounters($eCourse, $eUser), 'aucun compteur de lecture ne voit le constat');

		ProgressLib::undo($eEnrollment, $eLessonFirst);

		$eProgress = $this->getProgress($eUser, $eLessonFirst);

		$this->assertEquals(Progress::PENDING, $eProgress['status'], 'le stagiaire retire sa déclaration');
		$this->assertTrue($eProgress['checkedAt'] !== NULL, 'le constat reste');

		ProgressLib::uncheck($eLessonFirst, $eUser);
		ProgressLib::uncheck($eLessonFirst, $eUser);

		$eProgress = $this->getProgress($eUser, $eLessonFirst);

		$this->assertEquals([TRUE, NULL], [$eProgress['checkedBy']->empty(), $eProgress['checkedAt']], 'décochée, le constat part');
		$this->assertEquals(1, Progress::model()->whereUser($eUser)->whereLesson($eLessonFirst)->count(), 'la ligne reste');

		$cChapter = ChapterLib::getByCourse($eCourse);
		$eCourse['lessons'] = ChapterLib::countLessons($cChapter);

		ReadingLib::fillLastSeen($eCourse, new \Collection([$eUser['id'] => $eUser]));
		ProgressLib::fillTrack($eCourse, $cChapter, new \Collection([$eUser['id'] => $eUser]));

		$row = new ProgressUi()->getTrackRow($eCourse, $cChapter, $eUser);

		$this->assertEquals(1, $eUser['followed'], 'une leçon constatée sans être déclarée compte dans le total');
		$this->assertEquals(0, substr_count($row, '<input'), 'la grille se lit, elle ne porte aucune case');
		$this->assertEquals([FALSE, TRUE], [$eUser['track'][$eLessonFirst['id']]['checked'], $eUser['track'][$eLessonSecond['id']]['checked']], 'le constat sur la seule leçon constatée');
		$this->assertEquals([ProgressLib::TRACK_NONE, ProgressLib::TRACK_NONE], [$eUser['track'][$eLessonFirst['id']]['state'], $eUser['track'][$eLessonSecond['id']]['state']], 'aucune leçon déclarée ni tentée');

		ProgressLib::done($eEnrollment, $eLessonFirst);
		ProgressLib::fillTrack($eCourse, $cChapter, new \Collection([$eUser['id'] => $eUser]));

		$this->assertEquals(2, $eUser['followed'], 'la déclaration s\'ajoute au constat');
		$this->assertEquals(ProgressLib::TRACK_DECLARED, $eUser['track'][$eLessonFirst['id']]['state'], 'la leçon déclarée');

	}

	/**
	 * La vue d'une leçon, où l'équipe constate : une ligne par stagiaire, son état, la case. Le score
	 * et les tentatives ne s'ajoutent qu'à une leçon qui a un quiz. Une leçon d'une autre formation
	 * n'a pas de vue.
	 */
	public function testLessonView(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		[$eCommunity, $eCourse, , , $learners, $lessons] = $this->createDecor();

		[$eLessonFirst] = $lessons;

		$eEnrollment = new Enrollment(['user' => $learners['a1'], 'course' => $eCourse, 'structure' => new \structure\Structure()]);
		EnrollmentLib::create($eEnrollment);
		ProgressLib::done($eEnrollment, $eLessonFirst);
		ProgressLib::check($eCourse, $eLessonFirst, $learners['a1']);
		ProgressLib::check($eCourse, $eLessonFirst, $learners['b']);

		$cSection = ShareLib::getSectionsForTrack($eCourse, NULL);

		$html = $this->renderTrack($eCommunity, $eCourse, $cSection, lesson: $eLessonFirst);

		$this->assertTrue(str_contains($html, 'oninput="LearningTrack.check(this)"'), 'la vue d\'une leçon porte la case');

		$cChapter = ChapterLib::getByCourse($eCourse);
		$cUser = ShareLib::getLearners($eCourse, $cSection);
		ProgressLib::fillTrack($eCourse, $cChapter, $cUser);

		$this->assertEquals(2, $cUser->find(fn(\user\User $eUser) => $eUser['track'][$eLessonFirst['id']]['checked'])->count(), 'cochée pour les deux constatés');
		$this->assertEquals(1, $cUser->find(fn(\user\User $eUser) => $eUser['track'][$eLessonFirst['id']]['state'] === ProgressLib::TRACK_DECLARED)->count(), 'un seul déclaré');

		$html = $this->renderTrack($eCommunity, $eCourse, $cSection);

		$this->assertEquals(0, substr_count($html, '<input'), 'la grille ne porte aucune case');
		$this->assertTrue(str_contains($html, 'href="'.ProgressUi::urlTrackLesson($eCourse, $eLessonFirst, new \structure\Section()).'"'), 'le menu ouvre la vue d\'une leçon');

		$eCourseOther = $this->createCourse($eCommunity, Course::PUBLISHED, Course::PRIVATE);
		$eLessonOther = $this->createLesson($this->createChapter($eCourseOther, 'Autre module'), 'Autre leçon', 5);

		$this->assertEquals(
			[\CommunityAction::class, NULL],
			$this->executePage('formation/'.$eCourse['id'].'/suivi/lecon/'.$eLessonFirst['id'], [], 'GET'),
			'la vue d\'une leçon de la formation'
		);

		$this->assertEquals(
			[\NotExistsAction::class, NULL],
			$this->executePage('formation/'.$eCourse['id'].'/suivi/lecon/'.$eLessonOther['id'], [], 'GET'),
			'une leçon d\'une autre formation n\'a pas de vue'
		);

		$cChapter = ChapterLib::getByCourse($eCourse);
		ProgressLib::fillTrack($eCourse, $cChapter, new \Collection([$learners['b']['id'] => $learners['b']]));

		$eLessonView = $cChapter->first()['cLesson']->first();

		$row = new ProgressUi()->getTrackLessonRow($eCourse, $eLessonView, $learners['b']);

		$this->assertTrue(str_contains($row, 'id="learning-track-'.$learners['b']['id'].'"'), 'la case redessine la ligne du stagiaire dans la vue');
		$this->assertTrue(str_contains($row, 'data-lesson="'.$eLessonFirst['id'].'"'), 'pour la leçon regardée');

		$eLessonView['questions'] = 1;

		$this->assertEquals(substr_count($row, '<div') + 2, substr_count(new ProgressUi()->getTrackLessonRow($eCourse, $eLessonView, $learners['b']), '<div'), 'une leçon à quiz ajoute ses deux colonnes');

	}

	/**
	 * La case se coche par la page, qui vérifie la leçon et le stagiaire postés : un stagiaire jamais
	 * inscrit se coche, un compte hors des promotions partagées ou une leçon d'une autre formation
	 * sont refusés.
	 */
	public function testCheckPage(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		[$eCommunity, $eCourse, , , $learners, $lessons] = $this->createDecor();

		$eLesson = $lessons[0];

		$eCourseOther = $this->createCourse($eCommunity, Course::PUBLISHED, Course::PRIVATE);
		$eLessonOther = $this->createLesson($this->createChapter($eCourseOther, 'Autre module'), 'Autre leçon', 5);

		$this->actAs($this->eUserAdmin);

		$this->assertEquals(
			[\ViewAction::class, NULL],
			$this->executePage('learning/progress:doCheck', $this->getPosted($eCourse, $eLesson, $learners['b'])),
			'un stagiaire jamais inscrit se coche'
		);

		$this->assertTrue(EnrollmentLib::getByUserAndCourse($learners['b'], $eCourse)->empty(), 'sans être inscrit pour autant');
		$this->assertTrue($this->getProgress($learners['b'], $eLesson)['checkedAt'] !== NULL, 'le constat est écrit');

		$this->assertEquals(
			[\ViewAction::class, NULL],
			$this->executePage('learning/progress:doUncheck', $this->getPosted($eCourse, $eLesson, $learners['b'])),
			'et se décoche'
		);

		$this->assertEquals(NULL, $this->getProgress($learners['b'], $eLesson)['checkedAt'], 'le constat est retiré');

		$this->assertEquals(
			[\NotAllowedAction::class, NULL],
			$this->executePage('learning/progress:doCheck', $this->getPosted($eCourse, $eLesson, $learners['outsider'])),
			'un compte rangé dans une promotion sans être membre du groupe'
		);

		$this->assertEquals(
			[\NotAllowedAction::class, NULL],
			$this->executePage('learning/progress:doCheck', $this->getPosted($eCourse, $eLessonOther, $learners['a1'])),
			'une leçon d\'une autre formation'
		);

		$this->assertEquals(
			[\NotAllowedAction::class, NULL],
			$this->executePage('learning/progress:doCheck', $this->getPosted($eCourse, $eLesson, $this->eUserReader)),
			'un compte hors des promotions'
		);

		$this->actAs($learners['a1']);

		$this->assertEquals(
			[\NotAllowedAction::class, NULL],
			$this->executePage('learning/progress:doCheck', $this->getPosted($eCourse, $eLesson, $learners['a1'])),
			'un stagiaire ne se constate pas lui-même'
		);

	}

	/**
	 * Une formation de l'équipe n'a pas de promotions, donc pas de suivi.
	 */
	public function testTeam(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourseTeam = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::PRIVATE);

		$this->assertFalse($eCourseTeam->acceptTrack(), 'formation de l\'équipe');
		$this->assertTrue($this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), Course::DRAFT, Course::PRIVATE)->acceptTrack(), 'un brouillon du groupe se suit, ce qui y a été déclaré reste');

	}

	/**
	 * L'export rend ce que dit l'écran, une ligne par stagiaire : l'administrateur du groupe l'obtient.
	 */
	public function testExport(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		[$eCommunity, $eCourse, , $eSectionB, $learners, $lessons] = $this->createDecor();

		[$eLessonFirst, $eLessonSecond] = $lessons;

		$eStructureFarm = $this->getFarm();

		$eEnrollment = new Enrollment(['user' => $learners['a1'], 'course' => $eCourse, 'structure' => $eStructureFarm]);
		EnrollmentLib::create($eEnrollment);
		ProgressLib::done($eEnrollment, $eLessonFirst);
		ProgressLib::check($eCourse, $eLessonFirst, $learners['a1']);
		ProgressLib::check($eCourse, $eLessonSecond, $learners['b']);

		$this->assertEquals(
			[\CsvAction::class, NULL],
			$this->executePage('learning/progress:export', ['id' => (string)$eCourse['id']], 'GET'),
			'l\'administrateur exporte'
		);

		$cSection = ShareLib::getSectionsForTrack($eCourse, NULL);
		$cChapter = ChapterLib::getByCourse($eCourse);
		$cUser = ShareLib::getLearners($eCourse, $cSection);
		ProgressLib::fillTrack($eCourse, $cChapter, $cUser);
		ReadingLib::fillLastSeen($eCourse, $cUser);

		$lines = new ProgressUi()->getExport($cChapter, $cUser);

		$this->assertEquals([s("Stagiaire"), s("Ferme"), s("Leçons suivies"), s("Dernière activité"), '1. Première leçon', '2. Seconde leçon'], array_shift($lines), 'l\'en-tête');
		$this->assertEquals(3, count($lines), 'trois stagiaires');

		$byName = [];

		foreach($lines as $line) {
			$byName[$line[0]] = array_slice($line, 1);
		}

		$this->assertEquals([$eStructureFarm['name'], 1, '', s("Déclarée, constatée"), ''], $byName[decode(\user\UserUi::name($learners['a1']))], 'déclarée et constatée, au nom de sa ferme, sans lecture');
		$this->assertEquals(['', 1, '', '', s("Constatée")], $byName['Chloé d\'Aubigny zzTest'], 'constatée sans avoir été déclarée, le nom en texte et non en HTML');
		$this->assertEquals(['', 0, '', '', ''], $byName[decode(\user\UserUi::name($learners['a2']))], 'rien de suivi');

		$cUserB = ShareLib::getLearners($eCourse, $cSection, $cSection[$eSectionB['id']]);
		ProgressLib::fillTrack($eCourse, $cChapter, $cUserB);
		ReadingLib::fillLastSeen($eCourse, $cUserB);
		$this->assertEquals(2, count(new ProgressUi()->getExport($cChapter, $cUserB)), 'filtré par la promotion, sous l\'en-tête');

		$html = $this->renderTrack($eCommunity, $eCourse, $cSection);
		$this->assertEquals(1, preg_match('/href="\/learning\/progress:export\?id='.$eCourse['id'].'"[^>]* data-ajax-download/', $html), 'l\'administrateur exporte le tableau');
		$this->assertEquals(1, preg_match('/href="\/learning\/progress:exportDetail\?id='.$eCourse['id'].'"[^>]* data-ajax-download/', $html), 'et le détail par leçon');

	}

	/**
	 * L'export détaillé, une ligne par stagiaire et par leçon, avec ses dates : une leçon déclarée
	 * puis constatée par l'équipe, un quiz réussi à la seconde tentative, un quiz en échec, une
	 * leçon constatée sans être déclarée, une leçon jamais touchée, qui garde sa ligne à cases
	 * vides. Les heures se lisent à la minute, date et heure dans une seule colonne.
	 */
	public function testExportDetail(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		[, $eCourse, , , $learners, $lessons] = $this->createDecor();

		[$eLessonFirst, $eLessonSecond] = $lessons;

		$this->createQuestion($eLessonSecond);

		$eLessonQuiz = $this->getLesson($eLessonSecond['id']);
		$eLessonQuiz['quizThreshold'] = 100;

		$cQuestion = QuestionLib::getByLesson($eLessonQuiz);
		$eQuestion = $cQuestion->first();
		$choiceRight = $eQuestion['cChoice']->find(fn(Choice $eChoice) => $eChoice['correct'])->first()['id'];
		$choiceWrong = $eQuestion['cChoice']->find(fn(Choice $eChoice) => $eChoice['correct'] === FALSE)->first()['id'];

		$enroll = function(\user\User $eUser) use($eCourse): Enrollment {
			$eEnrollment = new Enrollment(['user' => $eUser, 'course' => $eCourse, 'structure' => new \structure\Structure()]);
			EnrollmentLib::create($eEnrollment);
			return $eEnrollment;
		};

		$eEnrollmentA1 = $enroll($learners['a1']);
		ProgressLib::done($eEnrollmentA1, $eLessonFirst);
		ProgressLib::check($eCourse, $eLessonFirst, $learners['a1']);

		$eEnrollmentA2 = $enroll($learners['a2']);
		ResponseLib::answer($eEnrollmentA2, $eLessonQuiz, $cQuestion, [$eQuestion['id'] => [$choiceWrong]]);
		ResponseLib::answer($eEnrollmentA2, $eLessonQuiz, $cQuestion, [$eQuestion['id'] => [$choiceRight]]);

		ProgressLib::check($eCourse, $eLessonFirst, $learners['b']);
		ResponseLib::answer(new Enrollment(['user' => $learners['b'], 'course' => $eCourse]), $eLessonQuiz, $cQuestion, [$eQuestion['id'] => [$choiceWrong]]);

		$this->assertEquals(
			[\CsvAction::class, NULL],
			$this->executePage('learning/progress:exportDetail', ['id' => (string)$eCourse['id']], 'GET'),
			'l\'administrateur exporte le détail'
		);

		$cSection = ShareLib::getSectionsForTrack($eCourse, NULL);
		$cChapter = ChapterLib::getByCourse($eCourse);
		$cUser = ShareLib::getLearners($eCourse, $cSection);
		ProgressLib::fillTrack($eCourse, $cChapter, $cUser);
		ProgressLib::fillDetail($eCourse, $cChapter, $cUser);
		ReadingLib::fillMeasured($eCourse, $cChapter, $cUser);

		$lines = new ProgressUi()->getExportDetail($cChapter, $cUser);

		$this->assertEquals([
			s("Stagiaire"), s("Ferme"), s("Inscription"), s("Module"), s("Leçon"), s("Durée estimée (min)"), s("Temps mesuré (min)"), s("État"), s("Suivie le"),
			s("Bonnes réponses"), s("Questions"), s("Score (%)"), s("Tentatives"), s("Dernière tentative"), s("Constatée le"), s("Constatée par")
		], array_shift($lines), 'l\'en-tête');

		$this->assertEquals(6, count($lines), 'trois stagiaires, deux leçons chacun, une ligne par leçon même jamais touchée');

		$byLesson = [];

		foreach($lines as $line) {
			$byLesson[$line[0]][$line[4]] = $line;
		}

		$first = '1. Première leçon';
		$second = '2. Seconde leçon';
		$now = '/^'.preg_quote(\util\DateUi::numeric(\Clock::date(), \util\DateUi::DATE), '/').' \d\d:\d\d$/';
		$admin = decode($this->getUser($this->eUserAdmin['id'])->getName());

		$a1 = $byLesson[decode(\user\UserUi::name($learners['a1']))];
		$a2 = $byLesson[decode(\user\UserUi::name($learners['a2']))];
		$b = $byLesson['Chloé d\'Aubigny zzTest'];

		$this->assertTrue(preg_match($now, $a1[$first][2]) === 1, 'l\'inscription, date et heure dans une seule colonne : '.$a1[$first][2]);
		$this->assertEquals(['1. Module du suivi', $first, 10, '', s("Déclarée")], array_slice($a1[$first], 3, 5), 'le module, la leçon, sa durée, son temps mesuré vide sans journal et son état');
		$this->assertTrue(preg_match($now, $a1[$first][8]) === 1, 'déclarée, suivie le : '.$a1[$first][8]);
		$this->assertEquals(['', '', '', '', ''], array_slice($a1[$first], 9, 5), 'une leçon sans quiz n\'a ni score ni tentative');
		$this->assertTrue(preg_match($now, $a1[$first][14]) === 1, 'constatée le : '.$a1[$first][14]);
		$this->assertEquals($admin, $a1[$first][15], 'constatée par l\'administrateur, en texte');

		$this->assertEquals(s("Réussie"), $a2[$second][7], 'réussie à la seconde tentative');
		$this->assertTrue(preg_match($now, $a2[$second][8]) === 1, 'réussie, suivie le : '.$a2[$second][8]);
		$this->assertEquals([1, 1, 100, 2], array_slice($a2[$second], 9, 4), 'les bonnes réponses, les questions, le score et les tentatives de la dernière');
		$this->assertTrue(preg_match($now, $a2[$second][13]) === 1, 'l\'heure de la dernière tentative : '.$a2[$second][13]);
		$this->assertEquals(['', ''], array_slice($a2[$second], 14, 2), 'réussie sans constat');

		$this->assertEquals(array_merge([10], array_fill(0, 10, '')), array_slice($a2[$first], 5), 'une leçon jamais touchée, à cases vides');

		$this->assertEquals('', $b[$first][2], 'jamais inscrite');
		$this->assertEquals(['', ''], array_slice($b[$first], 7, 2), 'constatée sans être déclarée : ni état ni suivie le');
		$this->assertTrue(preg_match($now, $b[$first][14]) === 1, 'mais constatée le : '.$b[$first][14]);
		$this->assertEquals([s("À refaire"), '', 0, 1, 0, 1], array_slice($b[$second], 7, 6), 'le quiz en échec, sans date de suivi');
		$this->assertTrue(preg_match($now, $b[$second][13]) === 1, 'et l\'heure de sa tentative : '.$b[$second][13]);

	}

	/**
	 * Les états particuliers de l'écran : une formation sans leçon, une promotion sans stagiaire.
	 */
	public function testEmptyStates(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCommunity = $this->getCommunity(self::COMMUNITY_EDUCATION);
		$eCourse = $this->createCourse($eCommunity, Course::PUBLISHED, Course::PRIVATE);
		$eSection = $this->createSection($eCommunity, 'zzTest promotion vide', []);

		$this->share($eCourse, [$eSection['id'] => [\Clock::date(), NULL]]);

		$cSection = ShareLib::getSectionsForTrack($eCourse, NULL);

		$html = $this->renderTrack($eCommunity, $eCourse, $cSection);
		$this->assertTrue(str_contains($html, 'href="'.CourseUi::urlEdition($eCourse).'"'), 'formation sans leçon, vers son écriture');
		$this->assertFalse(str_contains($html, 'progress:export'), 'sans tableau');

		$this->createLesson($this->createChapter($eCourse, 'Module'), 'Leçon', 5);

		$html = $this->renderTrack($eCommunity, $eCourse, $cSection, $cSection[$eSection['id']]);
		$this->assertTrue(ShareLib::getLearners($eCourse, $cSection, $cSection[$eSection['id']])->empty(), 'promotion sans stagiaire');
		$this->assertFalse(str_contains($html, 'progress:export'), 'rien à exporter');

		$this->assertTrue(ShareLib::getLearners($eCourse, $cSection)->empty(), 'toutes les promotions');

	}

	/**
	 * Le décor commun : une formation publiée du groupe à deux leçons, partagée à deux promotions.
	 * A range deux stagiaires et un compte qui n'est pas membre du groupe, B un stagiaire.
	 *
	 * @return array{0: \structure\Structure, 1: Course, 2: \structure\Section, 3: \structure\Section, 4: array<string, \user\User>, 5: Lesson[]}
	 */
	private function createDecor(): array {

		$eCommunity = $this->getCommunity(self::COMMUNITY_EDUCATION);

		$eCourse = $this->createCourse($eCommunity, Course::PUBLISHED, Course::PRIVATE);
		$eChapter = $this->createChapter($eCourse, 'Module du suivi');
		$eLessonFirst = $this->createLesson($eChapter, 'Première leçon', 10);
		$eLessonSecond = $this->createLesson($eChapter, 'Seconde leçon', 20);

		$learners = [
			'a1' => $this->createMember($eCommunity, 'zztest.stagiaire-a1', 'Aline', 'student'),
			'a2' => $this->createMember($eCommunity, 'zztest.stagiaire-a2', 'Bruno', 'student'),
			'b' => $this->createMember($eCommunity, 'zztest.stagiaire-b', 'Chloé d\'Aubigny', 'student'),
			'outsider' => $this->createAccount('zztest.hors-groupe', 'Émile'),
		];

		$eSectionA = $this->createSection($eCommunity, 'zzTest promotion A', [$learners['a1'], $learners['a2'], $learners['outsider']]);
		$eSectionB = $this->createSection($eCommunity, 'zzTest promotion B', [$learners['b']]);

		$this->share($eCourse, [
			$eSectionA['id'] => [\Clock::date(), NULL],
			$eSectionB['id'] => [\Clock::date(), NULL]
		]);

		return [
			$eCommunity,
			$eCourse,
			$eSectionA,
			$eSectionB,
			$learners,
			[$eLessonFirst, $eLessonSecond]
		];

	}

	/**
	 * Un compte `example.org`, domaine réservé où aucun envoi n'est délivré, écrit dans la
	 * transaction du test.
	 */
	private function createAccount(string $email, string $firstName): \user\User {

		$eUser = new \user\User([
			'firstName' => $firstName,
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

	private function createMember(\structure\Structure $eCommunity, string $email, string $firstName, string $role): \user\User {

		$eUser = $this->createAccount($email, $firstName);

		\structure\MemberLib::create(new \structure\Member([
			'user' => $eUser,
			'structure' => $eCommunity,
			'role' => \structure\RoleLib::getOne($eCommunity, $role),
			'status' => \structure\Member::IN
		]));

		return $eUser;

	}

	/** Ce que la case poste, en chaînes comme le navigateur. */
	private function getPosted(Course $eCourse, Lesson $eLesson, \user\User $eUser): array {

		return [
			'id' => (string)$eCourse['id'],
			'lesson' => (string)$eLesson['id'],
			'user' => (string)$eUser['id']
		];

	}

	private function getProgress(\user\User $eUser, Lesson $eLesson): Progress {

		return Progress::model()
			->select(['status', 'source', 'checkedBy', 'checkedAt'])
			->whereUser($eUser)
			->whereLesson($eLesson)
			->get();

	}

	/** Tout ce que la lecture du stagiaire et le catalogue comptent. */
	private function getCounters(Course $eCourse, \user\User $eUser): array {

		$cCourse = new \Collection([$eCourse['id'] => new Course(['id' => $eCourse['id']])]);
		ProgressLib::fillDone($eUser, $cCourse);

		return [
			$cCourse[$eCourse['id']]['done'],
			$cCourse[$eCourse['id']]['doneMinutes'],
			ProgressLib::countCompleted($eCourse),
			ProgressLib::countByLesson($eCourse),
			ProgressLib::getDoneByUserAndCourse($eUser, $eCourse)->getKeys()
		];

	}

	/** L'écran tel que la page le prépare : la grille, ou la vue de la leçon `$lesson`. */
	private function renderTrack(\structure\Structure $eCommunity, Course $eCourse, \Collection $cSection, \structure\Section $eSection = new \structure\Section(), Lesson $lesson = new Lesson()): string {

		$cChapter = ChapterLib::getByCourse($eCourse);
		$eCourse['lessons'] = ChapterLib::countLessons($cChapter);

		$eCourse['shares'] = ShareLib::countByCourse($eCourse);

		$eLesson = $lesson->empty() ? new Lesson() : ChapterLib::findLesson($cChapter, $lesson['id']);

		$cUser = ShareLib::getLearners($eCourse, $cSection, $eSection);
		ProgressLib::fillTrack($eCourse, $cChapter, $cUser);
		ReadingLib::fillLastSeen($eCourse, $cUser);

		return new ProgressUi()->getTrack($eCommunity, $eCourse, $cChapter, $cSection, $eSection, $cUser, $eLesson);

	}

	/** Une question à choix unique, écrite par le chemin du panneau. */
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

	private function sorted(array $values): array {
		sort($values);
		return $values;
	}

}
?>
