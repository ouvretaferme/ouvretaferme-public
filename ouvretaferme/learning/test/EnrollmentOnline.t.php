<?php
namespace learning;

require_once __DIR__.'/LearningNetworkFixture.t.php';

/**
 * S'inscrire soi-même à la formation en ligne d'un réseau (chantier « ouvrir aux personnes », lot A) :
 * publiée, elle s'offre à l'inscription de tous sans que le réseau l'ait ouverte à personne, sa
 * fiche se lit sans compte, et c'est l'inscription qui ouvre les leçons (`Course::canRead()`).
 *
 * Le décor est celui de `LearningNetworkFixture.t.php` : le lecteur fictif n'est membre d'aucun
 * réseau, l'utilisateur 1 est conseiller du réseau G3, et la formation de `seed()` n'a aucun
 * partage.
 *
 * Lancement : dev/test package=learning file=EnrollmentOnline
 */
class EnrollmentOnlineTest extends LearningNetworkFixtureTest {

	private bool $activeInitial;

	public function setUp(): void {

		parent::setUp();

		$this->activeInitial = LearningSetting::$active;

	}

	public function tearDown(): void {

		LearningSetting::$active = $this->activeInitial;

		parent::tearDown();

	}

	/**
	 * Le visiteur lit la fiche, pas les leçons : le programme les nomme sans y mener, et le geste est
	 * « S'inscrire », qui crée le compte sur place ou, cette page fermée, passe par la connexion et
	 * revient à la fiche.
	 */
	public function testVisitor(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eLesson] = $this->seedOnline();

		$this->actAs(new \user\User());

		$eCourseRead = $this->getCourse($eCourse['id']);

		$this->assertFalse($eCourseRead->canRead(), 'le visiteur ne lit pas la formation');
		$this->assertFalse($eCourseRead->canReadLessons(), 'ni ses leçons');
		$this->assertTrue($eCourseRead->acceptPublic(), 'qui s\'offre à l\'inscription');
		$this->assertTrue($eCourseRead->isEnrollFirst(), 'et où l\'on s\'inscrit avant de lire');
		$this->assertFalse(new Enrollment(['user' => new \user\User(), 'course' => $eCourseRead])->canCreate(), 'sans compte, pas d\'inscription');

		LearningSetting::$active = TRUE;

		$fiche = $this->renderFiche($eCourse);

		$this->assertTrue(str_contains($fiche, 'href="/learning/account:create?course='.$eCourse['id'].'"'), 'la fiche propose de s\'inscrire, le compte se crée sur place : '.$fiche);
		$this->assertFalse(str_contains($fiche, LessonUi::url($eLesson)), 'sans mener aux leçons');

		LearningSetting::$active = FALSE;

		$fiche = $this->renderFiche($eCourse);

		$this->assertTrue(str_contains($fiche, 'href="'.\Lime::getUrl().'/user/log:form?redirect='.urlencode(\Lime::getUrl().CourseUi::url($eCourse)).'"'), 'formations fermées, « S\'inscrire » passe par la connexion et revient à la fiche : '.$fiche);

		LearningSetting::$active = TRUE;

		$this->assertTrue(is_string($this->executeView(ltrim(LessonUi::url($eLesson), '/'), [])), 'la leçon ne s\'ouvre pas au visiteur');

		$this->actAs($this->eUserAdmin);

	}

	/**
	 * Le compte hors du réseau s'inscrit d'un clic, revient à la fiche, et lit dès lors les leçons
	 * sans qu'aucun partage existe. Le réseau l'a formé : il signe son attestation.
	 */
	public function testEnroll(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eLesson] = $this->seedOnline();

		$this->assertEquals(0, Share::model()->whereCourse($eCourse)->count(), 'la formation n\'est ouverte à personne');
		$this->assertTrue(CourseLib::getIssuer($eCourse, $this->eUserReader)->empty(), 'avant l\'inscription, rien ne lie le compte au réseau');

		$this->actAs($this->eUserReader);

		$eCourseRead = $this->getCourse($eCourse['id']);

		$this->assertFalse($eCourseRead->canRead(), 'hors du réseau et sans inscription, le compte ne lit pas');
		$this->assertTrue($eCourseRead->isEnrollFirst(), 'il s\'inscrit d\'abord');
		$this->assertTrue(new Enrollment(['user' => $this->eUserReader, 'course' => $eCourseRead])->canCreate(), 'et il le peut');

		$fiche = $this->renderFiche($eCourse);

		$this->assertTrue(str_contains($fiche, 'data-ajax="/learning/enrollment:doCreate" post-course="'.$eCourse['id'].'" post-structure="'.$this->eStructureFarm['id'].'"'), 'la fiche propose de s\'inscrire d\'un clic : '.$fiche);
		$this->assertFalse(str_contains($fiche, LessonUi::url($eLesson)), 'le programme ne mène pas aux leçons');

		$this->assertEquals([\RedirectAction::class, NULL], $this->executePage('learning/enrollment:doCreate', ['course' => (string)$eCourse['id'], 'structure' => ''], redirect: $redirect), 'l\'inscription s\'écrit');
		$this->assertEquals(CourseUi::url($eCourse), $redirect, 'et ramène à la fiche, pas à une leçon');

		$eCourseRead = $this->getCourse($eCourse['id']);

		$this->assertTrue($eCourseRead->canRead(), 'inscrit, le compte lit la formation');
		$this->assertTrue($eCourseRead->canReadLessons(), 'et ses leçons');
		$this->assertTrue($eCourseRead->canProgress(), 'qu\'il coche sans échéance');
		$this->assertFalse($eCourseRead->isEnrollFirst(), 'la question de s\'inscrire ne se pose plus');

		$fiche = $this->renderFiche($eCourse);

		$this->assertTrue(str_contains($fiche, 'href="'.LessonUi::url($eLesson).'"'), 'la fiche mène à la première leçon : '.$fiche);
		$this->assertFalse(str_contains($fiche, 'data-ajax="/learning/enrollment:doCreate"'), 'sans plus proposer de s\'inscrire');

		$this->assertTrue($this->executeView(ltrim(LessonUi::url($eLesson), '/'), []) instanceof \stdClass, 'la leçon s\'ouvre');

		$this->actAs($this->eUserAdmin);

		$this->assertEquals($this->eStructureNetwork['id'], CourseLib::getIssuer($eCourse, $this->eUserReader)['id'], 'le réseau délivre l\'attestation de son inscrit');
		$this->assertTrue($this->getCourse($eCourse['id'])->assertTrack(), 'et suit ses inscrits sans partage');

		// Dépubliée, la formation se retire à tous, inscrits compris.
		$eCourse['status'] = Course::DRAFT;
		CourseLib::update($eCourse, ['status']);

		$this->actAs($this->eUserReader);

		$this->assertTrue(is_string($this->executeView('formation/'.$eCourse['id'], [])), 'en brouillon, la fiche se ferme à l\'inscrit');

		$this->actAs($this->eUserAdmin);

	}

	/**
	 * Le compte qui a une ferme s'inscrit en son nom : la fiche poste la ferme, la route la garde sur
	 * l'inscription. Celle d'un autre, qu'un POST forgé nommerait, est refusée par `structure.owned`
	 * et rien ne s'écrit.
	 */
	public function testEnrollStructure(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedOnline();

		$eStructureForeign = $this->getCommunity(\structure\FixtureAiLib::F4);

		$this->join($this->eUserReader, $this->eStructureFarm);
		$this->forgetMemoization();

		$this->actAs($this->eUserReader);

		$this->assertEquals([$this->eStructureFarm['id']], EnrollmentLib::getStructures()->getIds(), 'le compte n\'a que la ferme du décor');

		$result = $this->executePage('learning/enrollment:doCreate', ['course' => (string)$eCourse['id'], 'structure' => (string)$eStructureForeign['id']]);

		$this->assertEquals(\FailAction::class, $result[0], 'la ferme d\'un autre est refusée : '.json_encode($result));
		$this->assertTrue(str_contains((string)$result[1], 'Enrollment::structure.owned'), 'par l\'étape qui pèse l\'appartenance : '.json_encode($result));
		$this->assertFalse(EnrollmentLib::isEnrolled($this->eUserReader, $eCourse), 'aucune inscription ne s\'écrit, ni à ce nom ni à titre personnel');
		$this->assertFalse($this->getCourse($eCourse['id'])->canRead(), 'et la lecture reste fermée');

		$this->assertEquals([\RedirectAction::class, NULL], $this->executePage('learning/enrollment:doCreate', ['course' => (string)$eCourse['id'], 'structure' => (string)$this->eStructureFarm['id']], redirect: $redirect), 'au nom de sa ferme, l\'inscription s\'écrit');
		$this->assertEquals(CourseUi::url($eCourse), $redirect, 'et ramène à la fiche');

		$eEnrollment = EnrollmentLib::getByUserAndCourse($this->eUserReader, $eCourse);

		$this->assertTrue($eEnrollment->notEmpty(), 'l\'inscription existe');
		$this->assertEquals($this->eStructureFarm['id'], $eEnrollment['structure']['id'], 'et porte la ferme postée');

		$eCourseRead = $this->getCourse($eCourse['id']);

		$this->assertTrue($eCourseRead->canRead(), 'inscrit, le compte lit la formation');
		$this->assertTrue($eCourseRead->canReadLessons(), 'et ses leçons');

		$this->actAs($this->eUserAdmin);

	}

	/**
	 * Un membre du réseau n'a pas plus de droit qu'un autre tant qu'il n'est pas inscrit : le réseau
	 * n'a rien ouvert, et c'est l'inscription qui ouvre.
	 */
	public function testMember(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedOnline();

		$this->join($this->eUserReader, $this->eStructureNetwork, 'member');
		$this->forgetMemoization();

		$this->actAs($this->eUserReader);

		$eCourseRead = $this->getCourse($eCourse['id']);

		$this->assertFalse($eCourseRead->canWrite(), 'le membre n\'est pas de l\'équipe');
		$this->assertFalse($eCourseRead->canRead(), 'membre et non inscrit, il ne lit pas');
		$this->assertTrue($eCourseRead->isEnrollFirst(), 'il s\'inscrit d\'abord, comme tout le monde');

		$this->assertEquals([\RedirectAction::class, NULL], $this->executePage('learning/enrollment:doCreate', ['course' => (string)$eCourse['id'], 'structure' => ''], redirect: $redirect), 'l\'inscription s\'écrit');
		$this->assertEquals(CourseUi::url($eCourse), $redirect, 'et ramène à la fiche');
		$this->assertTrue($this->getCourse($eCourse['id'])->canRead(), 'inscrit, il lit');

		$this->actAs($this->eUserAdmin);

		$eCourseRead = $this->getCourse($eCourse['id']);

		$this->assertTrue($eCourseRead->canRead(), 'l\'équipe du réseau lit sans s\'inscrire');
		$this->assertFalse($eCourseRead->isEnrollFirst(), 'et garde « Commencer la formation »');

	}

	/**
	 * La règle est bornée à la formation en ligne d'un réseau : l'inscription à celle d'un groupe de
	 * formation n'ouvre rien sans promotion, et celle-ci ne s'offre pas à tous.
	 */
	public function testGroup(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->seed();

		$eCourse = $this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), Course::PUBLISHED, Course::PRIVATE);

		// Hors build(), qui pèserait le droit du compte connecté et refuserait cette ligne : le décor
		// veut l'état d'une promotion retirée après l'inscription.
		EnrollmentLib::create(new Enrollment([
			'user' => $this->eUserReader,
			'course' => $eCourse,
			'structure' => new \structure\Structure()
		]));

		$this->assertTrue(EnrollmentLib::isEnrolled($this->eUserReader, $eCourse), 'le compte est inscrit');

		$this->actAs($this->eUserReader);

		$eCourseRead = $this->getCourse($eCourse['id']);

		$this->assertFalse($eCourseRead->acceptPublic(), 'la formation d\'un groupe ne s\'offre pas à tous');
		$this->assertFalse($eCourseRead->canRead(), 'et son inscrit ne la lit pas sans promotion');
		$this->assertTrue(is_string($this->executeView('formation/'.$eCourse['id'], [])), 'sa fiche lui reste fermée');

		$this->actAs($this->eUserAdmin);

		$this->assertTrue(CourseLib::getIssuer($eCourse, $this->eUserReader)->empty(), 'et le groupe ne signe pas son attestation');

	}

	/**
	 * La formation en ligne du décor, publiée et ouverte à personne, avec une leçon.
	 *
	 * @return array{0: Course, 1: Lesson}
	 */
	private function seedOnline(): array {

		$eCourse = $this->seed();

		// Le parcours d'un clic : le réseau inscrit d'office. La question de la prise en charge et la
		// demande sont éprouvées par `EnrollmentRequest.t.php`.
		$this->setOnlineConfirm(\structure\Community::IMMEDIATE);

		$eLesson = $this->createLesson($this->createChapter($eCourse, 'zzTest module'), 'zzTest leçon du programme', 10);

		return [$this->getCourse($eCourse['id']), $this->getLesson($eLesson['id'])];

	}

	private function renderFiche(Course $eCourse): string {

		$data = $this->executeView('formation/'.$eCourse['id'], []);

		$this->assertTrue($data instanceof \stdClass, 'la fiche '.$eCourse['id'].' se prépare : '.(is_string($data) ? $data : 'vue'));

		if(($data instanceof \stdClass) === FALSE) {
			return '';
		}

		return new CourseUi()->getFiche($data->eCourse, $data->cChapter, $data->cStaff, $data->eEnrollment, $data->cProgress, $data->cStructure, $data->isLogged, $data->cDate);

	}

}
?>
