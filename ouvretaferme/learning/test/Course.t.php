<?php
namespace learning;

require_once __DIR__.'/LearningFixture.t.php';

/**
 * Qui lit et qui écrit une formation, ce que « Lecture sans compte » accepte, et ce que la
 * suppression emporte.
 */
class CourseTest extends LearningFixtureTest {

	/**
	 * La fiche d'une formation publiée de l'équipe se lit sans compte ; ses leçons demandent un
	 * compte, sauf en lecture libre. Un brouillon ne se lit que par l'équipe.
	 */
	public function testReadTeam(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourseOpen = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::ACCEPTED);
		$eCoursePrivate = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::PRIVATE);
		$eCourseDraft = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::ACCEPTED);

		$this->assertTrue($eCourseDraft->assertRead(), 'l\'équipe lit son brouillon');

		$this->actAs(new \user\User());

		$this->assertTrue($eCourseOpen->assertRead(), 'visiteur, fiche en lecture libre');
		$this->assertTrue($eCourseOpen->assertReadLessons(), 'visiteur, leçons en lecture libre');
		$this->assertTrue($eCoursePrivate->assertRead(), 'visiteur, fiche publique d\'une formation avec compte');
		$this->assertFalse($eCoursePrivate->assertReadLessons(), 'visiteur, leçons d\'une formation avec compte');
		$this->assertFalse($eCourseDraft->assertRead(), 'visiteur, brouillon');
		$this->assertFalse($eCourseDraft->assertReadLessons(), 'visiteur, leçons d\'un brouillon');
		$this->assertFalse($eCourseOpen->canWrite(), 'visiteur, écriture');

		$eLessonPrivate = $this->createLessonAs($eCoursePrivate);
		$this->assertFalse($eLessonPrivate->assertRead(), 'visiteur, une leçon d\'une formation avec compte');

		$this->actAs($this->eUserReader);

		$this->assertTrue($eCoursePrivate->assertReadLessons(), 'compte, leçons d\'une formation avec compte');
		$this->assertTrue($eLessonPrivate->assertRead(), 'compte, une leçon d\'une formation avec compte');
		$this->assertFalse($eCourseDraft->assertRead(), 'compte, brouillon');
		$this->assertFalse($eCourseDraft->assertReadLessons(), 'compte, leçons d\'un brouillon');
		$this->assertFalse($eCoursePrivate->canWrite(), 'compte, écriture');

	}

	/**
	 * Une formation de groupe ne se lit que par l'équipe du groupe en L1 : le partage vient plus tard.
	 */
	public function testReadCommunity(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructureCommunity = $this->getCommunity(self::COMMUNITY_EDUCATION);

		if($eStructureCommunity->empty() or $eStructureCommunity->isEducation() === FALSE) {
			$this->assertFail('le groupe de formation '.self::COMMUNITY_EDUCATION.' manque dans cette base');
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($eStructureCommunity, Course::PUBLISHED, Course::PRIVATE);

		$this->assertTrue($eCourse->canWrite(), 'équipe du groupe, écriture');
		$this->assertTrue($eCourse->assertRead(), 'équipe du groupe, lecture');

		$this->actAs($this->eUserReader);

		$this->assertFalse($eCourse->assertRead(), 'compte hors du groupe, lecture');
		$this->assertFalse($eCourse->assertReadLessons(), 'compte hors du groupe, leçons');
		$this->assertFalse($eCourse->canWrite(), 'compte hors du groupe, écriture');

		$this->actAs(new \user\User());

		$this->assertFalse($eCourse->assertRead(), 'visiteur, lecture');
		$this->assertFalse($eCourse->assertReadLessons(), 'visiteur, leçons');

	}

	/**
	 * Une ferme ne porte pas de formation : la règle est un état, lu par `acceptWrite()` sans aucun
	 * compte connecté, comme le fera l'API. Le module et la leçon suivent leur formation.
	 */
	public function testAcceptWrite(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructureFarm = $this->getFarm();

		if($eStructureFarm->empty()) {
			$this->assertFail('aucune ferme active dans cette base');
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourseFarm = $this->createCourse($eStructureFarm, Course::DRAFT, Course::PRIVATE);
		$eCourseTeam = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE);
		$eCourseCommunity = $this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), Course::DRAFT, Course::PRIVATE);

		$eChapterFarm = $this->createChapter($eCourseFarm, 'Module du test');
		$eLessonFarm = $this->createLesson($eChapterFarm, 'Leçon du test', 10);

		$this->actAs(new \user\User());

		$this->assertFalse($eCourseFarm->acceptWrite(), 'formation d\'une ferme, écriture');
		$this->assertFalse($eCourseFarm->acceptCreate(), 'formation d\'une ferme, création');
		$this->assertFalse(CourseLib::getCreateElement($eStructureFarm)->acceptCreate(), 'formation neuve d\'une ferme');
		$this->assertFalse($this->getChapter($eChapterFarm['id'])->acceptWrite(), 'module d\'une formation de ferme');
		$this->assertFalse($this->getLesson($eLessonFarm['id'])->acceptWrite(), 'leçon d\'une formation de ferme');

		$this->assertTrue($eCourseTeam->acceptWrite(), 'formation de l\'équipe');
		$this->assertTrue($eCourseCommunity->acceptWrite(), 'formation d\'un groupe de formation');

		$this->assertFalse($eCourseTeam->canWrite(), 'sans compte, aucun droit d\'écrire');

	}

	/**
	 * Par l'API, le parent arrive dans le corps de la requête : il doit appartenir à une formation de
	 * la structure (`cCourse`, `cChapter`, posés comme le fera `getNewElement()`). La leçon déduit
	 * sa formation de son module, sans en porter aucune avant la construction.
	 */
	public function testOwned(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructureCommunity = $this->getCommunity(self::COMMUNITY_EDUCATION);
		$eStructureFarm = $this->getFarm();

		$this->actAs($this->eUserAdmin);

		$eCourseOwn = $this->createCourse($eStructureCommunity, Course::DRAFT, Course::PRIVATE);
		$eCourseTeam = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE);
		$eCourseFarm = $this->createCourse($eStructureFarm, Course::DRAFT, Course::PRIVATE);

		$eChapterOwn = $this->createChapter($eCourseOwn, 'Module du groupe');
		$eChapterTeam = $this->createChapter($eCourseTeam, 'Module de l\'équipe');
		$eChapterFarm = $this->createChapter($eCourseFarm, 'Module de la ferme');

		$this->actAs(new \user\User());

		$cCourse = CourseLib::getByCommunity($eStructureCommunity);

		$eChapter = new Chapter(['cCourse' => $cCourse]);
		$fw = new \FailWatch();
		$eChapter->build(ChapterLib::getPropertiesCreate('api'), ['course' => $eCourseTeam['id'], 'name' => 'Module refusé'], new \PropertyList('create'));
		$this->assertEquals(['Chapter::course.owned'], array_column($fw->get(), 1), 'module posé dans une formation d\'ailleurs');

		$eChapter = new Chapter(['cCourse' => $cCourse]);
		$fw = new \FailWatch();
		$eChapter->build(ChapterLib::getPropertiesCreate('api'), ['course' => $eCourseOwn['id'], 'name' => 'Module admis'], new \PropertyList('create'));
		$this->assertEquals([], $fw->get(), 'module posé dans une formation de la structure');

		$cChapter = ChapterLib::getByCourse($eCourseOwn);

		$eLesson = new Lesson(['cChapter' => $cChapter, 'cBlock' => new \Collection()]);
		$fw = new \FailWatch();
		$eLesson->build(LessonLib::getPropertiesCreate('api'), ['chapter' => $eChapterTeam['id'], 'name' => 'Leçon refusée', 'minutes' => 5, 'validation' => Lesson::DECLARED, 'quizThreshold' => NULL], new \PropertyList('create'));
		$this->assertEquals(['Lesson::chapter.owned'], array_column($fw->get(), 1), 'leçon posée dans un module d\'ailleurs');

		// Une liste qui porterait le module d'une formation de ferme ne l'ouvre pas pour autant.
		$cChapterWithFarm = ChapterLib::getByCourse($eCourseOwn);
		$cChapterWithFarm[$eChapterFarm['id']] = $this->getChapter($eChapterFarm['id']);

		$eLesson = new Lesson(['cChapter' => $cChapterWithFarm, 'cBlock' => new \Collection()]);
		$fw = new \FailWatch();
		$eLesson->build(LessonLib::getPropertiesCreate('api'), ['chapter' => $eChapterFarm['id'], 'name' => 'Leçon refusée', 'minutes' => 5, 'validation' => Lesson::DECLARED, 'quizThreshold' => NULL], new \PropertyList('create'));
		$this->assertEquals(['Lesson::chapter.owned'], array_column($fw->get(), 1), 'leçon posée dans une formation de ferme');

		$eLesson = new Lesson(['cChapter' => $cChapter, 'cBlock' => new \Collection()]);
		$fw = new \FailWatch();
		$eLesson->build(LessonLib::getPropertiesCreate('api'), ['chapter' => $eChapterOwn['id'], 'name' => 'Leçon admise', 'minutes' => 5, 'content' => '<p>Contenu</p>', 'validation' => Lesson::DECLARED, 'quizThreshold' => NULL], new \PropertyList('create'));
		$this->assertEquals([], $fw->get(), 'leçon posée dans un module de la structure');
		$this->assertEquals($eCourseOwn['id'], $eLesson['course']['id'], 'la leçon tient sa formation de son module');

	}

	/**
	 * Une leçon écrite par l'équipe, relue dans le rôle du compte en cours.
	 */
	private function createLessonAs(Course $eCourse): Lesson {

		$eUser = \user\ConnectionLib::getOnline();

		$this->actAs($this->eUserAdmin);
		$eLesson = $this->createLessonWritten($this->createChapter($eCourse, 'Module du test'), 'Leçon du test', 10);

		$this->actAs($eUser);

		return $eLesson;

	}

	/**
	 * « Lecture sans compte » (`open`) n'existe que pour l'équipe : son formulaire seul porte le
	 * champ, et les leçons d'une formation ouverte se lisent sans compte. `catalog` n'est construit
	 * par personne.
	 */
	public function testOpen(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourseTeam = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::PRIVATE);

		$properties = CourseLib::getPropertiesUpdate()($eCourseTeam);

		$this->assertTrue(in_array('open', $properties, TRUE), 'le formulaire de l\'équipe porte le champ');
		$this->assertFalse(in_array('catalog', $properties, TRUE), 'le formulaire de l\'équipe ne construit pas la place au catalogue');

		foreach(['description', 'access'] as $property) {
			$this->assertFalse(in_array($property, $properties, TRUE), 'les réglages ne construisent pas '.$property.', qui est au détail');
			$this->assertTrue(in_array($property, CourseLib::getPropertiesDetails(), TRUE), 'le détail construit '.$property);
		}

		$fw = new \FailWatch();
		$eCourseTeam->build(['open'], ['open' => '1']);
		$this->assertFalse($fw->ko(), 'l\'équipe ouvre sa formation');
		$this->assertTrue($eCourseTeam['open']);

		CourseLib::update($eCourseTeam, ['open']);

		$this->actAs(new \user\User());
		$this->assertTrue($this->getCourse($eCourseTeam['id'])->canReadLessons(), 'visiteur, leçons d\'une formation ouverte');

		$this->actAs($this->eUserAdmin);

		$fw = new \FailWatch();
		$eCourseTeam->build(['open'], ['open' => '0']);
		$this->assertFalse($fw->ko(), 'l\'équipe referme sa formation');
		$this->assertFalse($eCourseTeam['open']);

		CourseLib::update($eCourseTeam, ['open']);

		$this->actAs(new \user\User());
		$this->assertFalse($this->getCourse($eCourseTeam['id'])->canReadLessons(), 'visiteur, leçons d\'une formation refermée');

		$this->actAs($this->eUserReader);
		$this->assertTrue($this->getCourse($eCourseTeam['id'])->canReadLessons(), 'compte, leçons d\'une formation refermée');

		$this->actAs($this->eUserAdmin);

		$eCourseCommunity = $this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), Course::DRAFT, Course::PRIVATE);

		$properties = CourseLib::getPropertiesUpdate()($eCourseCommunity);

		$this->assertFalse(in_array('open', $properties, TRUE), 'le formulaire d\'un groupe ne porte pas le champ');
		$this->assertFalse(in_array('catalog', $properties, TRUE), 'le formulaire d\'un groupe ne construit pas la place au catalogue');

	}

	/**
	 * La place au catalogue se décide à la création, d'après l'auteur : une formation de l'équipe y
	 * est d'office, une formation de groupe naît privée. Le catalogue liste ce qui est retenu et
	 * publié, d'où que ça vienne.
	 */
	public function testCatalog(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCommunity = $this->getCommunity(self::COMMUNITY_EDUCATION);

		foreach([[new \structure\Structure(), Course::ACCEPTED], [$eCommunity, Course::PRIVATE]] as [$eStructure, $catalog]) {

			$eCourse = CourseLib::getCreateElement($eStructure);

			$fw = new \FailWatch();
			$eCourse->build(CourseLib::getPropertiesCreate(), ['name' => 'zzTest formation'], new \PropertyList('create'));
			$this->assertFalse($fw->ko(), 'création');

			CourseLib::create($eCourse);

			$eCourse = $this->getCourse($eCourse['id']);

			$this->assertEquals($catalog, $eCourse['catalog'], ($eStructure->empty() ? 'équipe' : 'groupe').', place au catalogue à la création');
			$this->assertFalse($eCourse['open'], ($eStructure->empty() ? 'équipe' : 'groupe').', réservée aux comptes à la création');

		}

		$eCourseTeam = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::PRIVATE);
		$eCourseTeamDraft = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::ACCEPTED);
		$eCourseCommunityAccepted = $this->createCourse($eCommunity, Course::PUBLISHED, Course::ACCEPTED);
		$eCourseCommunityPrivate = $this->createCourse($eCommunity, Course::PUBLISHED, Course::PRIVATE);

		$ids = CourseLib::getCatalog()->getColumn('id');

		$this->assertTrue(in_array($eCourseTeam['id'], $ids, TRUE), 'publiée de l\'équipe, au catalogue même réservée aux comptes');
		$this->assertFalse(in_array($eCourseTeamDraft['id'], $ids, TRUE), 'brouillon de l\'équipe, hors catalogue');
		$this->assertTrue(in_array($eCourseCommunityAccepted['id'], $ids, TRUE), 'formation de groupe retenue, au catalogue');
		$this->assertFalse(in_array($eCourseCommunityPrivate['id'], $ids, TRUE), 'formation de groupe privée, hors catalogue');

		$eCourseListed = CourseLib::getCatalog()->find(fn(Course $eCourse) => $eCourse['id'] === $eCourseCommunityAccepted['id'])->first();

		$this->assertEquals(self::COMMUNITY_EDUCATION, $eCourseListed['community']['id'], 'la formation de groupe est listée avec son groupe');

	}

	/**
	 * Le panneau de réglages s'ouvre sur l'état qu'il a lu et ne le réécrit pas : une formation
	 * publiée entre-temps, depuis un autre onglet ou par un collègue, reste publiée quand
	 * « Enregistrer » renvoie un formulaire ouvert sur le brouillon. L'API, elle, écrit bien l'état :
	 * elle n'a qu'une route d'écriture, là où le produit a `doUpdateStatus`.
	 */
	public function testSettingsKeepStatus(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE);

		$properties = CourseLib::getPropertiesUpdate()($eCourse);

		$this->assertEquals(FALSE, in_array('status', $properties, TRUE), 'les réglages ne construisent pas l\'état');
		$this->assertEquals(TRUE, in_array('status', CourseLib::getPropertiesUpdate('api')($eCourse), TRUE), 'l\'API construit l\'état');

		$this->createLessonWritten($this->createChapter($eCourse, 'Module du test'), 'Leçon du test', 10);

		$eCoursePublished = $this->getCourse($eCourse['id']);

		$fw = new \FailWatch();
		$eCoursePublished->build(['status'], ['status' => Course::PUBLISHED]);
		$this->assertFalse($fw->ko(), 'la formation est publiée depuis un autre onglet');

		CourseLib::update($eCoursePublished, ['status']);

		// `$eCourse` est l'élément que le panneau a lu avant cette publication : il porte encore
		// DRAFT, et c'est lui que le formulaire renvoie.
		$fw = new \FailWatch();

		$eCourse->build($properties, [
			'name' => 'zzTest formation renommée',
			'summary' => 'Une présentation réécrite depuis les réglages.',
			'theme' => $this->eTheme['id'],
			'level' => $this->eLevel['id'],
			'open' => '0',
		], new \PropertyList('update'));

		$this->assertFalse($fw->ko(), 'les réglages sont acceptés');

		CourseLib::update($eCourse, $properties);

		$this->assertEquals(Course::PUBLISHED, $this->getCourse($eCourse['id'])['status'], 'la formation reste publiée');

	}

	/**
	 * La date de publication est celle de la première : repasser en brouillon puis republier ne la
	 * déplace pas.
	 */
	public function testPublishedAt(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::PRIVATE);
		$this->assertTrue($eCourse['publishedAt'] !== NULL, 'publiée, datée');

		Course::model()->update($eCourse, ['publishedAt' => '2020-01-01 00:00:00']);

		$eCourse['status'] = Course::DRAFT;
		CourseLib::update($eCourse, ['status']);

		$eCourse['status'] = Course::PUBLISHED;
		CourseLib::update($eCourse, ['status']);

		$this->assertEquals('2020-01-01 00:00:00', $this->getCourse($eCourse['id'])['publishedAt']);

	}

	/**
	 * Le haut de l'éditeur : en brouillon, il mène à la publication, et publiée, à la dépublication.
	 * Le titre de l'en-tête s'encode, et la ligne qui situe la formation compte ses lecteurs.
	 */
	public function testEditionHeader(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE);
		$this->createLessonWritten($this->createChapter($eCourse, 'Module du test'), 'Leçon du test', 10);

		$eCourse = $this->getEditionCourse($eCourse['id']);

		$this->assertEquals([1, 10, 0], [$eCourse['lessons'], $eCourse['minutes'], $eCourse['readers']], 'une leçon de 10 minutes, aucun lecteur');

		// Un nom qui s'encode, posé sur l'élément rendu : sans esperluette ni chevron, le titre de
		// l'en-tête se lirait pareil avec ou sans `encode()`.
		$eCourse['name'] = 'zzTest formation & <tête>';

		$head = new CourseUi()->getEditionHead($eCourse);

		$this->assertTrue(str_contains($head, encode($eCourse['name'])) and str_contains($head, '<tête>') === FALSE, 'le titre est encodé : '.$head);
		$this->assertTrue(str_contains($head, 'href="/learning/course:publish?id='.$eCourse['id'].'"'), 'brouillon, le geste mène à la publication');
		$this->assertFalse(str_contains($head, 'course:unpublish'), 'brouillon, rien à dépublier');

		$eCourse['status'] = Course::PUBLISHED;
		CourseLib::update($eCourse, ['status']);

		$eCourse = $this->getEditionCourse($eCourse['id']);

		$head = new CourseUi()->getEditionHead($eCourse);

		$this->assertFalse(str_contains($head, 'course:publish?'), 'publiée, plus de « Publier »');
		$this->assertTrue(str_contains($head, 'href="/learning/course:unpublish?id='.$eCourse['id'].'"'), 'publiée, la dépublication sous la roue');

	}

	/**
	 * La fiche offre le lien vers l'éditeur à qui peut modifier la formation, et à lui seul. Le
	 * bandeau d'un brouillon ne porte plus son propre lien, pour qu'il n'y en ait qu'un.
	 */
	public function testFicheEdit(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::ACCEPTED);
		$this->createLessonWritten($this->createChapter($eCourse, 'Module du test'), 'Leçon du test', 10);

		$url = CourseUi::urlEdition($eCourse);
		$fiche = $this->getFiche($eCourse['id']);

		$this->assertTrue(str_contains($fiche, 'href="'.$url.'"'), 'l\'auteur, le lien vers l\'éditeur');

		$this->actAs($this->eUserReader);

		$fiche = $this->getFiche($eCourse['id']);

		$this->assertFalse(str_contains($fiche, $url), 'un lecteur, aucun lien vers l\'éditeur');

		$this->actAs($this->eUserAdmin);

		$eCourse['status'] = Course::DRAFT;
		CourseLib::update($eCourse, ['status']);

		$fiche = $this->getFiche($eCourse['id']);

		$this->assertEquals(1, substr_count($fiche, 'href="'.$url.'"'), 'un seul lien vers l\'éditeur');

	}

	/** La fiche telle que la page `/formation/{id}` la prépare, pour le compte connecté. */
	private function getFiche(int $id): string {

		$eUser = \user\ConnectionLib::getOnline();
		$eCourse = $this->getCourse($id);

		$cChapter = ChapterLib::getByCourse($eCourse);
		$cStaff = StaffLib::getByCourse($eCourse);
		$eEnrollment = EnrollmentLib::getByUserAndCourse($eUser, $eCourse);
		$cProgress = ProgressLib::getDoneByUserAndCourse($eUser, $eCourse);

		ProgressLib::fillChapterDone($cChapter, $cProgress);
		LessonLib::fillChapterFormat($cChapter);

		$eCourse['lessons'] = ChapterLib::countLessons($cChapter);
		$eCourse['minutes'] = ChapterLib::sumMinutes($cChapter);
		$eCourse['done'] = $cProgress->count();
		$eCourse['canProgress'] = $eCourse->canProgress();
		$eCourse['canFollow'] = $eCourse->canFollow($eEnrollment);
		$eCourse['eLessonFirst'] = CourseLib::getFirstLinked($cChapter, $eCourse);
		$eCourse['eLessonResume'] = CourseLib::getResume($cChapter, $cProgress, $eCourse);
		$eCourse['acceptEnrollment'] = new Enrollment(['user' => $eUser, 'course' => $eCourse])->acceptCreate();
		$eCourse['isDateConfirmed'] = FALSE;
		$eCourse['eEnrollmentRequest'] = new Enrollment();
		$eCourse['hasEnrollmentLine'] = $eEnrollment->notEmpty();
		$eCourse['withEnrollPanel'] = FALSE;
		$eCourse['onlineConfirm'] = EnrollmentLib::getOnlineConfirm($eCourse);
		$eCourse['eShare'] = ShareLib::getWindowByUserAndCourse($eUser, $eCourse);
		$eCourse['eSupervisorContact'] = SupervisorLib::getContactByCourse($eCourse);
		$eCourse['canCustomize'] = FALSE;
		$eCourse['withEnroll'] = $eCourse->isEnrollFirst();

		ResponseLib::fillCourseQuiz($eUser, $eCourse, $cChapter);
		ReviewLib::fillCourse($eCourse, $eUser);

		return new CourseUi()->getFiche($eCourse, $cChapter, $cStaff, $eEnrollment, $cProgress, EnrollmentLib::getStructures(), TRUE);

	}

	/** La formation telle que la page de l'éditeur la prépare. */
	private function getEditionCourse(int $id): Course {

		$eCourse = $this->getCourse($id);
		$cChapter = ChapterLib::getByCourse($eCourse);

		$eCourse['lessons'] = ChapterLib::countLessons($cChapter);
		$eCourse['minutes'] = ChapterLib::sumMinutes($cChapter);
		$eCourse['sections'] = 0;
		$eCourse['shares'] = 0;

		EnrollmentLib::fillReaders(new \Collection([$eCourse['id'] => $eCourse]));
		EnrollmentLib::fillRequests(new \Collection([$eCourse['id'] => $eCourse]));
		DateSheetLib::fillCountByCourse(new \Collection([$eCourse]));

		return $eCourse;

	}

	/**
	 * Le visuel se dépose sous le droit d'écrire la formation. Le type de média se déduit du nom de la
	 * classe (`MediaLib::__construct()`) : c'est ce qui interdit le suffixe Ai à `LearningCourseLib`.
	 * Sans visuel, la boîte ne porte aucune image ; avec, elle lit le format de son emplacement.
	 */
	public function testVignette(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::ACCEPTED);

		$lib = \media\MediaLib::getInstance('learning-course');
		$this->assertEquals('media\LearningCourseLib', get_class($lib));

		$_POST['id'] = $eCourse['id'];

		try {

			$this->assertEquals($eCourse['id'], $lib->buildElement()['id'], 'l\'équipe dépose le visuel');

			$this->actAs($this->eUserReader);

			try {
				$lib->buildElement();
				$this->assertTrue(FALSE, 'un lecteur dépose le visuel');
			} catch(\NotAllowedAction) {
				$this->assertTrue(TRUE);
			}

		} finally {
			unset($_POST['id']);
		}

		$eCourse = $this->getCourse($eCourse['id']);

		$fallback = CourseUi::getCover($eCourse, 'learning-cover-lg');
		$this->assertFalse(str_contains($fallback, 'background-image'), 'aucune image sans visuel');

		$eCourse['vignette'] = str_repeat('a', 19).'j#FFFFFF001';

		$cover = CourseUi::getCover($eCourse, 'learning-cover-lg');
		$this->assertTrue(str_contains($cover, '/learning-course/1600x900/'), 'la fiche lit le grand format');
		$this->assertTrue(str_contains(CourseUi::getCover($eCourse), '/learning-course/1600x900/'), 'la carte lit le grand format');
		$this->assertTrue(str_contains(CourseUi::getCover($eCourse, 'learning-cover-xs'), '/learning-course/480x270/'), 'la liste lit le petit format');

	}

	/**
	 * Supprimer une formation emporte ses modules, ses leçons, ses encadrants, ses inscriptions et
	 * la progression de ses lecteurs ; la personne qui l'encadrait reste dans la liste de l'équipe,
	 * où d'autres formations peuvent la reprendre. Des lecteurs inscrits ne l'en empêchent pas : la
	 * suppression reste ouverte tant que l'écriture l'est (arbitrage du 2026-09-20,
	 * « Supprimable, comme aujourd'hui »).
	 */
	public function testDelete(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::PRIVATE);
		$eChapter = $this->createChapter($eCourse, 'Module du test');
		$eLesson = $this->createLesson($eChapter, 'Leçon du test', 10);

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/staff:doCreate', ['course' => (string)$eCourse['id'], 'name' => 'zzTest Encadrant', 'role' => 'Auteur', 'description' => '', 'email' => '']), 'encadrant ajouté');

		$eSupervisor = StaffLib::getByCourse($eCourse)->first()['supervisor'];

		$eEnrollment = new Enrollment(['user' => $this->eUserReader, 'course' => $eCourse, 'structure' => new \structure\Structure()]);
		EnrollmentLib::create($eEnrollment);
		ProgressLib::done($eEnrollment, $eLesson);

		$fw = new \FailWatch();

		CourseLib::delete($eCourse);

		$this->assertTrue($fw->ok(), 'suppression acceptée malgré un inscrit');

		$this->assertEquals([0, 0, 0, 0, 0, 0], [
			Course::model()->whereId($eCourse)->count(),
			Chapter::model()->whereCourse($eCourse)->count(),
			Lesson::model()->whereCourse($eCourse)->count(),
			Staff::model()->whereCourse($eCourse)->count(),
			Enrollment::model()->whereCourse($eCourse)->count(),
			Progress::model()->whereCourse($eCourse)->count(),
		], 'formation, modules, leçons, encadrants, inscriptions, progression');

		$this->assertEquals(1, Supervisor::model()->whereId($eSupervisor)->count(), 'la personne reste à l\'équipe');

	}

}
?>
