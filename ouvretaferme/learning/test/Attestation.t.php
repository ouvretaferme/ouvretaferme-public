<?php
namespace learning;

require_once __DIR__.'/LearningDateFixture.t.php';

/**
 * L'attestation de suivi (F69, lot L8b) : qui l'obtient, ce qu'elle retient du stagiaire, et qui la
 * délivre.
 *
 * Le décor est celui des dates (`LearningDateFixture.t.php`) : le réseau G3 du décor commun,
 * une ferme rattachée où entre le lecteur fictif, des comptes fictifs en `example.org`.
 * Tout s'écrit dans la transaction de chaque test. Le PDF lui-même ne se rend pas ici : il passe par
 * le navigateur sans tête, que la recette éprouve.
 *
 * Lancement : dev/test package=learning file=Attestation
 */
class AttestationTest extends LearningDateFixtureTest {

	/**
	 * Le programme de la planche L4g : deux modules, cinq leçons, une heure.
	 */
	const PROGRAM = [
		'Prendre ses repères' => [['Situer les étapes', 8], ['Choisir son organisme', 13], ['Constituer son dossier', 18]],
		'Aller plus loin' => [['Préparer la visite', 8], ['Tenir ses enregistrements', 13]],
	];

	/**
	 * Les jours où le lecteur suit chaque leçon, dans l'ordre du programme.
	 */
	const DAYS = ['2026-09-12 09:30:00', '2026-09-12 10:10:00', '2026-09-15 14:00:00', '2026-09-17 08:45:00', '2026-09-18 17:20:00'];

	/**
	 * La route refuse un compte qui n'est pas inscrit, puis un inscrit qui n'a pas tout suivi : c'est
	 * elle qui juge, l'adresse signée du PDF ne regardant plus rien.
	 */
	public function testRoute(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seed();
		$this->assertEquals([], $this->shareNetwork($eCourse), 'formation ouverte aux membres du réseau');

		$eChapter = $this->createChapter($eCourse, 'zzTest module');
		$eLesson = $this->createLessonWritten($eChapter, 'zzTest leçon 1', 10);
		$this->createLessonWritten($eChapter, 'zzTest leçon 2', 10);

		$this->actAs($this->eUserReader);

		$this->assertEquals(\NotExpectedAction::class, $this->executeView('formation/'.$eCourse['id'].'/attestation', []), 'le lecteur n\'est pas inscrit, et l\'ouverture aux membres ne lui donne pas la lecture');

		$eEnrollment = $this->enroll($eCourse);
		ProgressLib::done($eEnrollment, $eLesson);

		$this->assertEquals(\NotAllowedAction::class, $this->executeView('formation/'.$eCourse['id'].'/attestation', []), 'une leçon sur deux, la formation n\'est pas finie');

		// La condition que partagent la route et le bouton de la fiche : toutes les leçons, et une inscription.
		$eCourse['lessons'] = 2;
		$eCourse['done'] = 2;

		$this->assertTrue($eCourse->acceptAttestation($eEnrollment), 'inscrit, deux leçons sur deux');
		$this->assertFalse($eCourse->acceptAttestation(new Enrollment()), 'deux leçons sur deux, sans inscription');

	}

	/**
	 * Le cas de la planche : cinq leçons, une heure, du 12 au 18 septembre, trois constatées par
	 * l'équipe du réseau. Avant la dernière leçon, la durée retenue est celle des leçons suivies et
	 * non celle de la formation.
	 */
	public function testSummary(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $lessons] = $this->seedProgram();

		$this->actAs($this->eUserReader);
		$eEnrollment = $this->enroll($eCourse);

		foreach(array_slice($lessons, 0, 4) as $position => $eLesson) {
			$this->setClock(self::DAYS[$position]);
			ProgressLib::done($eEnrollment, $eLesson);
		}

		\Clock::reset();

		$this->actAs($this->eUserAdmin);

		foreach(array_slice($lessons, 0, 3) as $eLesson) {
			ProgressLib::check($eCourse, $eLesson, $this->eUserReader);
		}

		$this->assertEquals(
			['validated' => 4, 'minutesValidated' => 47, 'minutesAll' => 60, 'firstDoneAt' => self::DAYS[0], 'lastDoneAt' => self::DAYS[3], 'checked' => 3],
			$this->getSummary($eCourse),
			'quatre leçons sur cinq, 47 minutes sur 60'
		);

		$this->setClock(self::DAYS[4]);
		ProgressLib::done($eEnrollment, $lessons[4]);
		\Clock::reset();

		$this->assertEquals(
			['validated' => 5, 'minutesValidated' => 60, 'minutesAll' => 60, 'firstDoneAt' => self::DAYS[0], 'lastDoneAt' => self::DAYS[4], 'checked' => 3],
			$this->getSummary($eCourse),
			'cinq leçons, une heure, du 12 au 18 septembre, trois constatées'
		);

	}

	/**
	 * Les cas de la question 6 de la porte L8 : l'inscription à la formation en ligne lie le réseau à
	 * la personne, une inscription confirmée à une date aussi, même hors réseau ; l'ouverture aux
	 * membres ne lie rien. Sans lien, et pour une formation de l'équipe, Ouvretaferme délivre seule.
	 */
	public function testIssuer(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourseShared = $this->seed();
		$this->assertEquals([], $this->shareNetwork($eCourseShared), 'formation ouverte aux membres du réseau');

		$this->join($this->eUserReader, $this->eStructureNetwork, 'member');

		$this->assertEquals(NULL, $this->getIssuerId($eCourseShared, $this->eUserReader), 'ouverte aux membres, sans inscription : forme B');

		$this->enrollReader($eCourseShared);

		$this->assertEquals($this->eStructureNetwork['id'], $this->getIssuerId($eCourseShared, $this->eUserReader), 'inscrit : forme A, au nom du réseau');

		$eUserOutside = $this->createUser('zztest.stagiaire-attestation@example.org');

		$this->assertEquals(NULL, $this->getIssuerId($eCourseShared, $eUserOutside), 'ni date, ni inscription : forme B');

		$eCourseDated = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE, type: Course::ONSITE);
		$eChapter = $this->createChapter($eCourseDated, 'zzTest module');
		$this->createLessonWritten($eChapter, 'zzTest leçon', 10);

		$eDate = $this->createDate($eCourseDated, 10);

		$this->assertEquals(NULL, $this->getIssuerId($eCourseDated, $eUserOutside), 'formation datée, aucune inscription : forme B');

		$this->enrollConfirmed($eDate, $eUserOutside);

		$this->assertEquals($this->eStructureNetwork['id'], $this->getIssuerId($eCourseDated, $eUserOutside), 'inscription confirmée à une date, hors réseau : forme A');

		// Seule la confirmation lie : une demande, une ligne annulée et une place en liste d'attente
		// ne prouvent pas que le réseau a formé la personne.
		$eUserRequested = $this->createUser('zztest.stagiaire-attestation-demande@example.org');
		$this->enrollRequested($eDate, $eUserRequested);

		$eUserCanceled = $this->createUser('zztest.stagiaire-attestation-annulee@example.org');
		DateEnrollmentLib::cancel($this->enrollConfirmed($eDate, $eUserCanceled), DateEnrollment::USER);
		$this->assertEquals(DateEnrollment::CANCELED, DateEnrollmentLib::getByDateAndUser($eDate, $eUserCanceled)['status'], 'confirmée puis annulée');

		// Les deux lignes ouvertes, la demande et la confirmée, occupent les deux places.
		$eDateFull = DateLib::getById($eDate['id']);
		$eDateFull['seats'] = 2;
		DateLib::update($eDateFull, ['seats']);

		$eUserWaiting = $this->createUser('zztest.stagiaire-attestation-attente@example.org');
		$eDateEnrollmentWaiting = DateEnrollmentLib::getCreateElement(DateLib::getById($eDate['id']), $eUserWaiting);
		$eDateEnrollmentWaiting['structure'] = new \structure\Structure();
		$eDateEnrollmentWaiting['trainee'] = DateEnrollment::FARMER;
		$eDateEnrollmentWaiting['committedAt'] = \Clock::datetime();
		DateEnrollmentLib::create($eDateEnrollmentWaiting);
		$this->assertEquals(DateEnrollment::WAITING, DateEnrollmentLib::getByDateAndUser($eDate, $eUserWaiting)['status'], 'la date est pleine : liste d\'attente');

		$this->assertEquals(NULL, $this->getIssuerId($eCourseDated, $eUserRequested), 'inscription en demande : forme B');
		$this->assertEquals(NULL, $this->getIssuerId($eCourseDated, $eUserCanceled), 'inscription annulée : forme B');
		$this->assertEquals(NULL, $this->getIssuerId($eCourseDated, $eUserWaiting), 'inscription en liste d\'attente : forme B');

		$eCourseTeam = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::PRIVATE);

		$this->assertEquals(NULL, $this->getIssuerId($eCourseTeam, $this->eUserReader), 'formation de l\'équipe : forme B');

	}

	/**
	 * L'inscription à la formation en ligne d'un réseau lie la personne au réseau, qui délivre son
	 * attestation : sans partage, et aussi quand la formation est retenue au catalogue public
	 * (V-Learning, 10/10). L'attestation ne se délivrant qu'à un inscrit, ces formations n'ont plus
	 * la forme B. Celles d'un groupe de formation et de l'équipe la gardent.
	 */
	public function testIssuerOnline(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seed();
		$eCourseCatalog = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::ACCEPTED);
		$eCourseEducation = $this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), Course::PUBLISHED, Course::PRIVATE);
		$eCourseTeam = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::PRIVATE);

		$eUserOutside = $this->createUser('zztest.stagiaire-attestation-en-ligne@example.org');

		foreach(['sans partage' => $eCourse, 'au catalogue public' => $eCourseCatalog] as $label => $eCourseNetwork) {

			$this->assertEquals(0, ShareLib::countByCourse($eCourseNetwork), $label.' : la formation n\'est ouverte à personne');
			$this->assertEquals(NULL, $this->getIssuerId($eCourseNetwork, $eUserOutside), $label.', non inscrit : rien ne lie le compte au réseau');

			$this->enrollOutside($eCourseNetwork, $eUserOutside);

			$this->assertEquals($this->eStructureNetwork['id'], $this->getIssuerId($eCourseNetwork, $eUserOutside), $label.', inscrit : forme A, au nom du réseau');

		}

		foreach(['d\'un groupe de formation' => $eCourseEducation, 'de l\'équipe' => $eCourseTeam] as $label => $eCourseOther) {

			$this->enrollOutside($eCourseOther, $eUserOutside);

			$this->assertEquals(NULL, $this->getIssuerId($eCourseOther, $eUserOutside), 'formation '.$label.', inscrit sans partage : forme B');

		}

	}

	/**
	 * Les deux formes rendues sur le cas de la planche : la date de délivrance par le réseau pour A,
	 * aucune pour B. Le texte nomme la structure du lecteur,
	 * « ferme » quand le réseau délivre et « structure » quand Ouvretaferme délivre seule, et le
	 * constat de l'équipe.
	 */
	public function testRender(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $lessons] = $this->seedProgram();

		$this->actAs($this->eUserReader);
		$eEnrollment = $this->enroll($eCourse);

		foreach($lessons as $eLesson) {
			ProgressLib::done($eEnrollment, $eLesson);
		}

		$this->actAs($this->eUserAdmin);
		ProgressLib::check($eCourse, $lessons[0], $this->eUserReader);

		$eUser = $this->getReaderSummary($eCourse);
		$cChapter = ChapterLib::getByCourse($eCourse);
		LessonLib::fillChapterFormat($cChapter);

		$eStructureIssuer = CourseLib::getIssuer($eCourse, $this->eUserReader);

		$a = new AttestationUi()->get($eCourse, $cChapter, $eUser, $this->eStructureFarm, $eStructureIssuer, \Clock::date());
		$b = new AttestationUi()->get($eCourse, $cChapter, $eUser, $this->eStructureFarm, new \structure\Structure(), \Clock::date());

		$this->assertTrue(str_contains($a, 'Délivrée le'), 'forme A : la date de délivrance');
		$this->assertFalse(str_contains($b, 'Délivrée le'), 'forme B : aucune structure ne la délivre');

		$this->assertTrue(str_contains($a, 'de la ferme '.encode($this->eStructureFarm['name'])), 'forme A : le réseau délivre, la structure du lecteur est une ferme');
		$this->assertTrue(str_contains($b, 'de la structure '.encode($this->eStructureFarm['name'])), 'forme B : Ouvretaferme délivre, la structure du lecteur est une structure');
		$this->assertFalse(str_contains($b, 'de la ferme'), 'forme B : jamais « ferme »');
		$this->assertTrue(str_contains($a, 'dont 1 constatée par l\'équipe du réseau'), 'le constat de l\'équipe');
		$this->assertEquals(5, substr_count($a, '<tr class="pdf-tr-ml-1">'), 'une ligne par leçon');
		$this->assertEquals(2, substr_count($a, '<tr class="pdf-tr-title">'), 'un titre par module');

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

			preg_match_all('#<tr class="pdf-tr-title"><td colspan="4">(.*?)</td></tr>#', new AttestationUi()->get($eCourseTyped, $cChapter, $eUser, $this->eStructureFarm, $eStructureIssuer, \Clock::date()), $titles);

			$this->assertEquals($expected, $titles[1], $type.', le titre de chaque module de l\'attestation');

		}

	}

	/**
	 * Une leçon vide garde sa ligne dans l'attestation, qui en tait la forme et la durée : elle ne
	 * compte pas dans la durée suivie, et la somme des lignes fait le total que la phrase annonce.
	 */
	public function testRenderEmpty(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $lessons] = $this->seedProgram();

		$this->actAs($this->eUserAdmin);

		$eLessonEmpty = $this->createLesson(ChapterLib::getByCourse($eCourse)->first(), 'zzTest leçon vide', 30);

		$this->actAs($this->eUserReader);

		$eEnrollment = $this->enroll($eCourse);
		ProgressLib::done($eEnrollment, $lessons[0]);
		ProgressLib::done($eEnrollment, $eLessonEmpty);

		$eUser = $this->getReaderSummary($eCourse);
		$cChapter = ChapterLib::getByCourse($eCourse);
		LessonLib::fillChapterFormat($cChapter);

		$attestation = new AttestationUi()->get($eCourse, $cChapter, $eUser, $this->eStructureFarm, CourseLib::getIssuer($eCourse, $this->eUserReader), \Clock::date());

		preg_match_all('#<tr class="pdf-tr-ml-1"><td>[0-9]+\. (.*?)</td><td>(.*?)</td><td class="text-end">(.*?)</td>#', $attestation, $rows, PREG_SET_ORDER);

		$cells = array_column(array_map(fn(array $row) => [$row[1], [$row[2], $row[3]]], $rows), 1, 0);

		$this->assertEquals(['<span class="util-annotation">-</span>', '<span class="util-annotation">-</span>'], $cells['zzTest leçon vide'] ?? NULL, 'la leçon vide tait sa forme et sa durée');
		$this->assertEquals([LessonUi::p('format')->values[Lesson::STEPS], CourseUi::getDuration(8)], $cells['Situer les étapes'] ?? NULL, 'une leçon écrite garde les siennes');
		$this->assertEquals([2, 8, 60], [$eUser['validated'], $eUser['minutesValidated'], $eUser['minutesAll']], 'la leçon vide suivie compte parmi les leçons, pas dans les durées');

	}

	/**
	 * La formation de la planche, publiée par le réseau et ouverte à ses fermes.
	 *
	 * @return array{0: Course, 1: Lesson[]} les leçons dans l'ordre du programme
	 */
	private function seedProgram(): array {

		$eCourse = $this->seed();
		$this->assertEquals([], $this->shareNetwork($eCourse), 'formation ouverte aux membres du réseau');

		$lessons = [];

		foreach(self::PROGRAM as $chapter => $program) {

			$eChapter = $this->createChapter($eCourse, $chapter);

			foreach($program as [$name, $minutes]) {
				$lessons[] = $this->createLessonWritten($eChapter, $name, $minutes);
			}

		}

		return [$this->getCourse($eCourse['id']), $lessons];

	}

	/**
	 * L'inscription du lecteur au nom de sa ferme, comme le bouton « Commencer » la pose.
	 */
	private function enroll(Course $eCourse): Enrollment {

		$eEnrollment = new Enrollment(['user' => $this->eUserReader, 'course' => $eCourse, 'structure' => $this->eStructureFarm]);
		EnrollmentLib::create($eEnrollment);

		return EnrollmentLib::getByUserAndCourse($this->eUserReader, $eCourse);

	}

	/**
	 * L'inscription à titre personnel d'un compte hors du réseau. Hors build(), qui pèserait le droit
	 * du compte connecté quand le décor veut la ligne d'un autre compte, y compris sur la formation
	 * d'un groupe où aucune promotion ne la lui ouvrirait.
	 */
	private function enrollOutside(Course $eCourse, \user\User $eUser): void {

		EnrollmentLib::create(new Enrollment(['user' => $eUser, 'course' => $eCourse, 'structure' => new \structure\Structure()]));

		$this->assertTrue(EnrollmentLib::isEnrolled($eUser, $eCourse), 'le compte '.$eUser['id'].' est inscrit à la formation '.$eCourse['id']);

	}

	/**
	 * Le lecteur préparé comme la route du PDF le prépare.
	 */
	private function getReaderSummary(Course $eCourse): \user\User {

		$eUser = $this->getUser($this->eUserReader['id']);
		$cUser = new \Collection([$eUser['id'] => $eUser]);
		$cChapter = ChapterLib::getByCourse($eCourse);

		ProgressLib::fillTrack($eCourse, $cChapter, $cUser);
		ProgressLib::fillDetail($eCourse, $cChapter, $cUser);
		ProgressLib::fillSummary($cChapter, $cUser);
		DateEnrollmentLib::fillAttended($eCourse, $cUser);

		return $eUser;

	}

	private function getSummary(Course $eCourse): array {

		$eUser = $this->getReaderSummary($eCourse);

		return $eUser->extracts(['validated', 'minutesValidated', 'minutesAll', 'firstDoneAt', 'lastDoneAt', 'checked']);

	}

	private function getIssuerId(Course $eCourse, \user\User $eUser): ?int {

		$eStructure = CourseLib::getIssuer($eCourse, $eUser);

		return $eStructure->empty() ? NULL : $eStructure['id'];

	}

}
?>
