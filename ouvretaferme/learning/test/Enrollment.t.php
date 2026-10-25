<?php
namespace learning;

require_once __DIR__.'/LearningFixture.t.php';

/**
 * La ferme d'une inscription : ce qu'un compte peut choisir, ce qui lui est proposé d'office, et
 * l'inscription répétée.
 */
class EnrollmentTest extends LearningFixtureTest {

	/**
	 * Un compte sans ferme n'a rien à choisir : son inscription se construit sans ferme, et une ferme
	 * postée à la main est refusée par l'étape `owned`.
	 */
	public function testWithoutStructure(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserReader);

		$this->assertEquals(0, EnrollmentLib::getStructures()->count(), 'le lecteur du décor n\'a aucune ferme');

		$eCourse = new Course(['id' => 1, 'community' => new \structure\Structure(), 'onlineConfirm' => NULL]);

		$eEnrollment = new Enrollment(['user' => $this->eUserReader, 'course' => $eCourse]);

		$fw = new \FailWatch();
		$eEnrollment->build(EnrollmentLib::getPropertiesCreate()($eEnrollment), ['structure' => ''], new \PropertyList('create'));
		$this->assertFalse($fw->ko(), 'aucune ferme, accepté');
		$this->assertTrue($eEnrollment['structure']->empty(), 'aucune ferme, élément vide');

		$eStructureForeign = $this->getForeignStructure(new \Collection());

		$fw = new \FailWatch();
		$eEnrollment->build(EnrollmentLib::getPropertiesCreate()($eEnrollment), ['structure' => $eStructureForeign['id']], new \PropertyList('create'));
		$this->assertEquals(['Enrollment::structure.owned'], array_column($fw->get(), 1), 'ferme d\'un autre compte');

	}

	/**
	 * Un compte à plusieurs fermes choisit l'une des siennes, jamais celle d'un autre.
	 */
	public function testSeveralStructures(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$cStructure = EnrollmentLib::getStructures();

		if($cStructure->count() < 2) {
			$this->assertFail('l\'utilisateur '.self::USER_ADMIN.' n\'a plus plusieurs fermes dans cette base');
			return;
		}

		$eEnrollment = new Enrollment(['user' => $this->eUserAdmin, 'course' => new Course(['id' => 1, 'community' => new \structure\Structure(), 'onlineConfirm' => NULL])]);

		$fw = new \FailWatch();
		$eEnrollment->build(EnrollmentLib::getPropertiesCreate()($eEnrollment), ['structure' => $cStructure->first()['id']], new \PropertyList('create'));
		$this->assertFalse($fw->ko(), 'une de ses fermes');
		$this->assertEquals($cStructure->first()['id'], $eEnrollment['structure']['id']);

		$fw = new \FailWatch();
		$eEnrollment->build(EnrollmentLib::getPropertiesCreate()($eEnrollment), ['structure' => $this->getForeignStructure($cStructure)['id']], new \PropertyList('create'));
		$this->assertEquals(['Enrollment::structure.owned'], array_column($fw->get(), 1), 'la ferme d\'un autre compte');

		$fw = new \FailWatch();
		$eEnrollment->build(EnrollmentLib::getPropertiesCreate()($eEnrollment), [], new \PropertyList('create'));
		$this->assertEquals(['Enrollment::structure.chosen'], array_column($fw->get(), 1), 'rien de coché');

		$fw = new \FailWatch();
		$eEnrollment->build(EnrollmentLib::getPropertiesCreate()($eEnrollment), ['structure' => ''], new \PropertyList('create'));
		$this->assertFalse($fw->ko(), '« Aucune, à titre personnel », choisi');

	}

	/**
	 * Une ferme école ou une ferme projet n'est pas une ferme au nom de laquelle on s'inscrit : elle
	 * sort de la liste, et la choisir à la main est refusé par l'étape `owned`.
	 */
	public function testCollectiveStructure(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$cStructure = EnrollmentLib::getStructures();

		if($cStructure->empty()) {
			$this->assertFail('l\'utilisateur '.self::USER_ADMIN.' n\'a plus de ferme dans cette base');
			return;
		}

		$eStructure = $cStructure->first();
		$eStructure['producerType'] = \structure\Structure::SCHOOL;

		\structure\StructureLib::update($eStructure, ['producerType']);

		$cStructureAfter = EnrollmentLib::getStructures();

		$this->assertEquals($cStructure->count() - 1, $cStructureAfter->count(), 'la liste raccourcit d\'une ferme');
		$this->assertFalse($cStructureAfter->offsetExists($eStructure['id']), 'la ferme école n\'est plus proposée');

		$eEnrollment = new Enrollment(['user' => $this->eUserAdmin, 'course' => new Course(['id' => 1, 'community' => new \structure\Structure(), 'onlineConfirm' => NULL])]);

		$fw = new \FailWatch();
		$eEnrollment->build(EnrollmentLib::getPropertiesCreate()($eEnrollment), ['structure' => $eStructure['id']], new \PropertyList('create'));
		$this->assertEquals(['Enrollment::structure.owned'], array_column($fw->get(), 1), 'la ferme école, postée à la main');

	}

	/**
	 * Une formation de groupe propose la ferme du compte rattachée à ce groupe ; une formation de
	 * l'équipe n'en propose aucune.
	 */
	public function testPreselected(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$cStructure = EnrollmentLib::getStructures();

		$eAffiliation = \structure\Affiliation::model()
			->select(['structureProducer', 'structureCommunity'])
			->whereStructureProducer('IN', $cStructure)
			->get();

		if($eAffiliation->empty()) {
			$this->assertFail('aucune ferme de l\'utilisateur '.self::USER_ADMIN.' n\'est rattachée à un groupe dans cette base');
			return;
		}

		$eCourseTeam = new Course(['community' => new \structure\Structure()]);
		$this->assertTrue(EnrollmentLib::getPreselected($eCourseTeam, $cStructure)->empty(), 'formation de l\'équipe');

		$eCourseCommunity = new Course(['community' => $eAffiliation['structureCommunity']]);
		$eStructurePreselected = EnrollmentLib::getPreselected($eCourseCommunity, $cStructure);

		$this->assertTrue($eStructurePreselected->notEmpty(), 'formation d\'un groupe auquel une ferme est rattachée');

		$this->assertTrue(\structure\Affiliation::model()
			->whereStructureCommunity($eAffiliation['structureCommunity'])
			->whereStructureProducer($eStructurePreselected)
			->exists(), 'la ferme proposée est rattachée au groupe');

		$this->assertTrue(EnrollmentLib::getPreselected($eCourseCommunity, new \Collection())->empty(), 'compte sans ferme');

	}

	/**
	 * Le second clic, d'un autre onglet, ne crée pas une seconde inscription et ne lève rien.
	 */
	public function testCreateTwice(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::PRIVATE);

		$this->actAs($this->eUserReader);

		$this->assertTrue(new Enrollment(['user' => $this->eUserReader, 'course' => $eCourse])->canCreate(), 'un compte s\'inscrit à une formation qu\'il lit');

		EnrollmentLib::create(new Enrollment(['user' => $this->eUserReader, 'course' => $eCourse, 'structure' => new \structure\Structure()]));
		EnrollmentLib::create(new Enrollment(['user' => $this->eUserReader, 'course' => $eCourse, 'structure' => new \structure\Structure()]));

		$this->assertEquals(1, Enrollment::model()->whereUser($this->eUserReader)->whereCourse($eCourse)->count());

		$this->actAs(new \user\User());

		$this->assertFalse(new Enrollment(['user' => new \user\User(), 'course' => $eCourse])->canCreate(), 'un visiteur ne s\'inscrit pas');

	}

	/**
	 * Commencée depuis une leçon, la formation y ramène. Une origine d'une autre formation, forgée
	 * dans le POST, ou absente, ramène à la première leçon.
	 */
	public function testLanding(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::PRIVATE);
		$eChapter = $this->createChapter($eCourse, 'Module du test');
		$eLessonFirst = $this->createLesson($eChapter, 'Première leçon', 10);
		$this->createLesson($eChapter, 'Deuxième leçon', 10);
		$eLessonThird = $this->createLesson($eChapter, 'Troisième leçon', 10);

		$eCourseOther = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::PRIVATE);
		$eLessonOther = $this->createLesson($this->createChapter($eCourseOther, 'Autre module'), 'Leçon d\'une autre formation', 10);

		$cChapter = ChapterLib::getByCourse($eCourse);

		$this->assertEquals($eLessonThird['id'], ChapterLib::getLanding($cChapter, $this->getLesson($eLessonThird['id']))['id'], 'la leçon d\'où l\'on vient');
		$this->assertEquals($eLessonFirst['id'], ChapterLib::getLanding($cChapter, $this->getLesson($eLessonOther['id']))['id'], 'une leçon d\'une autre formation ramène à la première');
		$this->assertEquals($eLessonFirst['id'], ChapterLib::getLanding($cChapter, new Lesson())['id'], 'sans origine, la première');

		$this->actAs($this->eUserReader);

		$this->assertEquals(LessonUi::url($eLessonThird), $this->startFrom($eCourse, (string)$eLessonThird['id']), 'le bouton sous la leçon 3 poste son origine, et l\'inscription y ramène');

		EnrollmentLib::delete(EnrollmentLib::getByUserAndCourse($this->eUserReader, $eCourse));

		$this->assertEquals(LessonUi::url($eLessonFirst), $this->startFrom($eCourse, (string)$eLessonOther['id']), 'une origine forgée, d\'une autre formation');

	}

	/**
	 * Le chemin de `doCreate`, comme le poste le bouton d'un compte sans ferme : l'adresse où
	 * l'inscription envoie le lecteur. `executePage()` ne rend que la classe de l'action.
	 */
	private function startFrom(Course $eCourse, string $origin): string {

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, 'POST');

		[$getInitial, $postInitial, $requestInitial] = [$_GET, $_POST, $_REQUEST];
		$_GET = [];
		$_POST = $_REQUEST = ['course' => (string)$eCourse['id'], 'structure' => '', 'origin' => $origin];

		try {
			\Page::execute(new \ReflectionMethod(\Page::class, 'getPages')->invoke(NULL, 'learning/enrollment:doCreate'), new \stdClass());
			return 'aucune action';
		} catch(\RedirectAction $action) {
			return new \ReflectionProperty(\RedirectAction::class, 'url')->getValue($action);
		} catch(\Action $action) {
			return get_class($action);
		} finally {
			[$_GET, $_POST, $_REQUEST] = [$getInitial, $postInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

	}

	/**
	 * Une ferme qui n'est pas parmi celles du compte. `whereId('NOT IN', …)` sur une liste vide rend
	 * la condition `(0)` et ne trouve plus rien (`framework/core/Module.php`, `whereBuild()`) :
	 * la condition n'est posée que s'il y a des fermes à écarter.
	 */
	private function getForeignStructure(\Collection $cStructureOwned): \structure\Structure {

		return \structure\Structure::model()
			->select('id')
			->whereType(\structure\Structure::PRODUCER)
			->whereId('NOT IN', $cStructureOwned, if: $cStructureOwned->notEmpty())
			->whereId('NOT IN', \structure\StructureSetting::FIXTURE_STRUCTURES)
			->get();

	}

}
?>
