<?php
namespace learning;

require_once __DIR__.'/LearningFixture.t.php';

/**
 * La copie d'une formation dans son groupe (« Dupliquer ») : ce qu'elle emporte, ce qu'elle laisse,
 * qui la fait, et ce que devient un fichier que la copie partage avec l'original.
 *
 * La vignette se copie sur le disque du stockage, que le `rollBack()` de la fixture n'annule pas :
 * chaque test efface les fichiers qu'il a posés et ceux que la copie a écrits.
 */
class DuplicateTest extends LearningFixtureTest {

	private array $vignettesStored = [];

	public function tearDown(): void {

		$ui = new \media\LearningCourseUi();

		foreach($this->vignettesStored as $vignette) {

			foreach([NULL, 's', 'l'] as $format) {

				$basename = $ui->getBasenameByHash($vignette, $format);

				\media\MediaSetting::$mediaDriver->delete($basename);
				\media\MediaSetting::$mediaDriver->delete($basename.'.txt');

			}

		}

		$this->vignettesStored = [];

		parent::tearDown();

	}

	/**
	 * Une formation publiée de l'équipe dupliquée par l'équipe : titre posté, même auteur, brouillon,
	 * classement et présentation repris, programme dans l'ordre, encadrants, fichiers au même hash,
	 * vignette sous un hash neuf ; rien des lecteurs, et l'original intact.
	 */
	public function testDuplicate(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createSource();

		$eCourseCopy = $this->getCourse($this->duplicate($eCourse, 'zzTest copie')['id']);

		$this->assertEquals('zzTest copie', $eCourseCopy['name'], 'titre posté');
		$this->assertTrue($eCourseCopy->isTeam(), 'la copie reste à l\'équipe');
		$this->assertEquals(Course::DRAFT, $eCourseCopy['status'], 'brouillon');
		$this->assertEquals(Course::ACCEPTED, $eCourseCopy['catalog'], 'au catalogue d\'office, comme toute formation de l\'équipe');
		$this->assertFalse($eCourseCopy['open'], 'réservée aux comptes, l\'original étant en lecture libre');
		$this->assertEquals(NULL, $eCourseCopy['publishedAt'], 'jamais publiée');
		$this->assertEquals($eCourse['id'], $eCourseCopy['copiedFrom']['id'], 'original désigné');
		$this->assertEquals($this->eUserAdmin['id'], $eCourseCopy['createdBy']['id'], 'créée par le compte qui copie');

		foreach(['summary', 'audience', 'prerequisites', 'objectives'] as $property) {
			$this->assertEquals($eCourse[$property], $eCourseCopy[$property], $property.' repris');
		}

		$this->assertEquals($eCourse['theme']['id'], $eCourseCopy['theme']['id'], 'thématique reprise');
		$this->assertEquals($eCourse['level']['id'], $eCourseCopy['level']['id'], 'niveau repris');

		$cChapterCopy = ChapterLib::getByCourse($eCourseCopy);

		$this->assertEquals(['Module A', 'Module B'], array_values($cChapterCopy->getColumn('name')), 'modules dans l\'ordre');
		$this->assertEquals([0, 1], array_values($cChapterCopy->getColumn('position')), 'rangs des modules');
		$this->assertEquals(['Leçon A1', 'Leçon A2'], array_values($cChapterCopy->first()['cLesson']->getColumn('name')), 'leçons du premier module dans l\'ordre');
		$this->assertEquals([0, 1], array_values($cChapterCopy->first()['cLesson']->getColumn('position')), 'rangs des leçons');

		$cLessonCopy = LessonLib::getByCourse($eCourseCopy)->find(fn(Lesson $eLesson) => $eLesson['name'] === 'Leçon A2');

		$eLessonCopy = $cLessonCopy->first();
		$cBlockCopy = \block\BlockLib::getByOwnerAndReference(BlockOwnerLib::LESSON, $eLessonCopy['id']);

		$this->assertEquals([self::CONTENT], array_values($cBlockCopy->getColumn('content')), 'contenu de la leçon repris, par ses blocs');
		$this->assertEquals([0], array_values($cBlockCopy->getColumn('position')), 'rang du bloc copié');
		$this->assertTrue($eLessonCopy['written'], 'écrite comme l\'original, sans quiz, par les blocs qu\'elle a reçus');
		$this->assertEquals(['Leçon A1', 'Leçon B1'], array_values(LessonLib::getByCourse($eCourseCopy)->find(fn(Lesson $eLesson) => $eLesson['written'] === FALSE)->getColumn('name')), 'les leçons vides de l\'original le restent');

		$cStaffCopy = StaffLib::getByCourse($eCourseCopy);

		$cSupervisorCopy = $cStaffCopy->getColumnCollection('supervisor');

		$this->assertEquals(['Ada', 'Bob'], array_values($cSupervisorCopy->getColumn('name')), 'encadrants dans l\'ordre');
		$this->assertEquals([TRUE, FALSE], array_values($cSupervisorCopy->getColumn('handicap')), 'référent handicap repris');
		$this->assertEquals(['Formateur', 'Formateur'], array_values($cStaffCopy->getColumn('role')), 'rôle dans la formation repris');

		$cAttachment = AttachmentLib::getByCourse($eCourse);
		$cAttachmentCopy = AttachmentLib::getByCourse($eCourseCopy);

		$this->assertEquals(array_values($cAttachment->getColumn('hash')), array_values($cAttachmentCopy->getColumn('hash')), 'fichiers au même hash');
		$this->assertEquals(array_values($cAttachment->getColumn('name')), array_values($cAttachmentCopy->getColumn('name')), 'noms des fichiers');
		$this->assertEquals([$cLessonCopy->first()['id']], array_values(array_unique($cAttachmentCopy->getColumnCollection('lesson')->getIds())), 'fichiers rattachés à la leçon copiée');

		$this->assertEquals(0, Enrollment::model()->whereCourse($eCourseCopy)->count(), 'aucune inscription copiée');
		$this->assertEquals(0, Progress::model()->whereCourse($eCourseCopy)->count(), 'aucune progression copiée');
		$this->assertEquals(1, Enrollment::model()->whereCourse($eCourse)->count(), 'inscription de l\'original gardée');

		$this->assertEquals(2, Chapter::model()->whereCourse($eCourse)->count(), 'modules de l\'original gardés');
		$this->assertEquals(3, Lesson::model()->whereCourse($eCourse)->count(), 'leçons de l\'original gardées');
		$this->assertEquals(Course::PUBLISHED, $this->getCourse($eCourse['id'])['status'], 'original toujours publié');

		// La vignette
		$this->vignettesStored[] = $eCourseCopy['vignette'];

		$this->assertTrue(
			$eCourseCopy['vignette'] !== NULL and substr($eCourseCopy['vignette'], 0, 19) !== substr($eCourse['vignette'], 0, 19),
			'vignette sous un hash neuf'
		);
		$this->assertEquals(substr($eCourse['vignette'], 19, 8), substr($eCourseCopy['vignette'], 19, 8), 'format et couleur de la vignette gardés');

		$eMedia = \media\Media::model()
			->select('type', 'status')
			->whereHash(substr($eCourseCopy['vignette'], 0, 20))
			->get();

		$this->assertEquals(['learning-course', \media\Media::ACTIVE], $eMedia->empty() ? [] : [$eMedia['type'], $eMedia['status']], 'média actif de la vignette copiée');

		$ui = new \media\LearningCourseUi();

		foreach([NULL, 's', 'l'] as $format) {
			$this->assertTrue(is_file(\storage\DriverLib::getFileName($ui->getBasenameByHash($eCourseCopy['vignette'], $format))), 'fichier '.($format ?? 'original').' de la vignette copiée');
		}

		$this->assertEquals($eCourse['vignette'], $this->getCourse($eCourse['id'])['vignette'], 'vignette de l\'original inchangée');

	}

	/**
	 * `copiedFrom` désigne une formation de l'équipe, ou rien. Une formation de groupe copiée d'une
	 * formation de l'équipe, du temps où un groupe pouvait la reprendre, le dit, et sa copie dans le
	 * groupe aussi (C1 → C2), jusqu'à ce que l'original soit supprimé. Une formation née dans le
	 * groupe ne transmet rien à sa copie, et l'équipe qui duplique la sienne n'affiche aucune origine.
	 */
	public function testOrigin(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eStructure = $this->getCommunity(self::COMMUNITY_EDUCATION);

		$eCourseTeam = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::ACCEPTED);

		// Une copie vers un groupe ne se fait plus : le décor pose l'origine que le produit écrivait,
		// sans build(), `copiedFrom` n'étant dans aucune liste de propriétés construites.
		$eCourseCopy = $this->createCourse($eStructure, Course::DRAFT, Course::PRIVATE);
		$eCourseCopy['copiedFrom'] = $eCourseTeam;
		CourseLib::update($eCourseCopy, ['copiedFrom']);

		$this->assertEquals("D'après une formation de Ouvretaferme", CourseUi::getOrigin($this->getCourse($eCourseCopy['id'])), 'formation de groupe copiée d\'une formation de l\'équipe');

		$eCourseCopyCopy = $this->duplicate($this->getCourse($eCourseCopy['id']), 'zzTest copie de la copie');

		$this->assertEquals(self::COMMUNITY_EDUCATION, $this->getCourse($eCourseCopyCopy['id'])['community']['id'], 'dupliquée dans son groupe');
		$this->assertEquals($eCourseTeam['id'], $this->getCourse($eCourseCopyCopy['id'])['copiedFrom']['id'], 'copie de la copie (C1 → C2), l\'origine reste la formation de l\'équipe');
		$this->assertEquals("D'après une formation de Ouvretaferme", CourseUi::getOrigin($this->getCourse($eCourseCopyCopy['id'])), 'copie de la copie, toujours d\'après l\'équipe');

		$eCourseGroup = $this->createCourse($eStructure, Course::DRAFT, Course::PRIVATE);
		$eCourseGroupCopy = $this->duplicate($eCourseGroup, 'zzTest copie du groupe');

		$this->assertEquals(self::COMMUNITY_EDUCATION, $this->getCourse($eCourseGroupCopy['id'])['community']['id'], 'dupliquée dans son groupe');
		$this->assertTrue($this->getCourse($eCourseGroupCopy['id'])['copiedFrom']->empty(), 'copie d\'une formation née dans le groupe, aucune origine');
		$this->assertEquals(NULL, CourseUi::getOrigin($this->getCourse($eCourseGroupCopy['id'])), 'copie d\'une formation du groupe');

		$eCourseTeamCopy = $this->duplicate($eCourseTeam, 'zzTest copie de l\'équipe');

		$this->assertTrue($this->getCourse($eCourseTeamCopy['id'])->isTeam(), 'dupliquée dans l\'équipe');
		$this->assertEquals(Course::ACCEPTED, $this->getCourse($eCourseTeamCopy['id'])['catalog'], 'dupliquée dans l\'équipe, au catalogue d\'office');
		$this->assertFalse($this->getCourse($eCourseTeamCopy['id'])['open'], 'dupliquée dans l\'équipe, réservée aux comptes');
		$this->assertEquals($eCourseTeam['id'], $this->getCourse($eCourseTeamCopy['id'])['copiedFrom']['id'], 'dupliquée dans l\'équipe, l\'origine est une formation de l\'équipe');
		$this->assertEquals(NULL, CourseUi::getOrigin($this->getCourse($eCourseTeamCopy['id'])), 'copie faite par l\'équipe elle-même');

		CourseLib::delete($eCourseTeam);

		$eCourseOrphan = $this->getCourse($eCourseCopy['id']);

		$this->assertTrue($eCourseOrphan->notEmpty(), 'la copie survit à l\'original');
		$this->assertTrue($eCourseOrphan['copiedFrom']->empty(), 'original supprimé, la copie ne le désigne plus');
		$this->assertEquals(NULL, CourseUi::getOrigin($eCourseOrphan), 'original supprimé, plus rien ne le dit');
		$this->assertTrue($this->getCourse($eCourseCopyCopy['id'])['copiedFrom']->empty(), 'original supprimé, la copie de la copie ne le désigne plus');

	}

	/**
	 * Les encadrants de la copie (lot C des encadrants du réseau) : une personne appartient à un
	 * groupe, et la copie, qui reste dans le sien, garde les personnes de l'original, jumelles
	 * comprises, sans en créer aucune.
	 *
	 * Les lignes s'écrivent par le panneau (`learning/staff:doCreate`), et les personnes se lisent par
	 * leur préfixe `zzTest`, les groupes portant les leurs depuis la reprise.
	 */
	public function testStaffSupervisor(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourseTeam = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::ACCEPTED);

		foreach([
			['name' => 'zzTest Ada Martin', 'email' => 'ada@example.org', 'handicap' => '1'],
			['name' => 'zzTest Bob Petit', 'email' => ''],
			['name' => 'zzTest Bob Petit', 'email' => ''],
		] as $values) {

			$this->assertEquals(
				[\ReloadAction::class, NULL],
				$this->executePage('learning/staff:doCreate', $values + ['course' => (string)$eCourseTeam['id'], 'role' => 'formatrice', 'description' => '']),
				$values['name'].' nommé dans la formation de l\'équipe'
			);

		}

		$persons = fn(Course $eCourse) => Staff::model()
			->select(['id', 'supervisor' => ['name', 'email', 'role', 'handicap', 'community']])
			->whereCourse($eCourse)
			->sort(['position' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection()
			->getColumnCollection('supervisor');

		$describe = function(\Collection $cSupervisor): array {

			$list = [];

			foreach($cSupervisor as $eSupervisor) {

				$list[] = $eSupervisor->empty() ? NULL : [
					$eSupervisor['name'],
					$eSupervisor['email'],
					$eSupervisor['role'],
					$eSupervisor['handicap'],
					$eSupervisor['community']->empty() ? NULL : $eSupervisor['community']['id']
				];

			}

			return $list;

		};

		$count = fn() => Supervisor::model()->whereName('LIKE', 'zzTest %')->count();

		$cSupervisorTeam = $persons($eCourseTeam);

		$this->assertEquals(
			[
				['zzTest Ada Martin', 'ada@example.org', 'formatrice', TRUE, NULL],
				['zzTest Bob Petit', NULL, 'formatrice', FALSE, NULL],
				['zzTest Bob Petit', NULL, 'formatrice', FALSE, NULL],
			],
			$describe($cSupervisorTeam),
			'chaque ligne de l\'original porte sa personne, chez l\'équipe, et le rôle de la formation est devenu son rôle habituel'
		);
		$this->assertEquals(3, $count(), 'trois personnes, chaque ajout « Nouvel encadrant » créant la sienne');

		$eCourseTeamCopy = $this->duplicate($eCourseTeam, 'zzTest copie de l\'équipe');

		$this->assertEquals($cSupervisorTeam->getIds(), $persons($eCourseTeamCopy)->getIds(), 'copiée dans son groupe, chaque ligne garde la personne de l\'original, les jumelles comprises');
		$this->assertEquals(3, $count(), 'sans personne de plus');

		// La seconde des deux jumelles, seule dans une formation : copiée dans son groupe, la ligne
		// garde cette personne, et non la plus ancienne des deux.
		$eCourseTeamTwin = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::ACCEPTED);
		$supervisorTwinId = $cSupervisorTeam->getIds()[2];

		$this->assertEquals(
			[\ReloadAction::class, NULL],
			$this->executePage('learning/staff:doCreate', ['course' => (string)$eCourseTeamTwin['id'], 'source' => 'pick', 'supervisor' => (string)$supervisorTwinId, 'role' => 'formatrice', 'contact' => '0']),
			'la seconde jumelle rattachée seule à une autre formation de l\'équipe'
		);
		$this->assertEquals([$supervisorTwinId], $persons($this->duplicate($eCourseTeamTwin, 'zzTest copie de la jumelle'))->getIds(), 'copiée dans son groupe, la ligne garde sa personne et non sa jumelle plus ancienne');
		$this->assertEquals(3, $count(), 'toujours trois personnes');

	}

	/**
	 * La copie ne lit pas le statut d'une personne : seule la liste proposée à l'ajout le filtre. Dans
	 * son groupe, la copie garde une personne désactivée.
	 */
	public function testStaffSupervisorStatus(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourseTeam = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::ACCEPTED);

		$this->assertEquals(
			[\ReloadAction::class, NULL],
			$this->executePage('learning/staff:doCreate', ['course' => (string)$eCourseTeam['id'], 'name' => 'zzTest Ada Martin', 'email' => 'ada@example.org', 'role' => 'formatrice', 'description' => '']),
			'Ada nommée dans la formation de l\'équipe'
		);

		$person = fn(Course $eCourse) => Staff::model()
			->select(['id', 'supervisor' => ['status']])
			->whereCourse($eCourse)
			->get()['supervisor'];

		$setStatus = fn(Supervisor $eSupervisor, string $status) => $this->executePage('learning/supervisor:doUpdateStatus', ['id' => (string)$eSupervisor['id'], 'status' => $status]);

		$count = fn() => Supervisor::model()->whereName('zzTest Ada Martin')->count();

		$eSupervisorTeam = $person($eCourseTeam);

		$this->assertEquals([\ViewAction::class, NULL], $setStatus($eSupervisorTeam, Supervisor::INACTIVE), 'Ada désactivée chez l\'équipe');

		$eSupervisorTeamCopy = $person($this->duplicate($eCourseTeam, 'zzTest copie de l\'équipe'));

		$this->assertEquals([$eSupervisorTeam['id'], Supervisor::INACTIVE], [$eSupervisorTeamCopy['id'], $eSupervisorTeamCopy['status']], 'copiée dans son groupe, la formation garde la personne désactivée');
		$this->assertEquals(1, $count(), 'sans personne de plus');

	}

	/**
	 * Un fichier partagé : le retirer de la copie n'efface que sa ligne, ne décale rien dans
	 * l'original, et le nettoyage de nuit garde le média que l'original porte encore.
	 */
	public function testSharedAttachment(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createSource();
		$eCourseCopy = $this->duplicate($eCourse, 'zzTest copie');

		$this->vignettesStored[] = $this->getCourse($eCourseCopy['id'])['vignette'];

		$eAttachmentCopy = Attachment::model()
			->select(Attachment::getSelection())
			->whereCourse($eCourseCopy)
			->wherePosition(0)
			->get();

		AttachmentLib::delete($eAttachmentCopy);

		$cAttachment = AttachmentLib::getByCourse($eCourse);

		$this->assertEquals([0, 1], array_values($cAttachment->getColumn('position')), 'rangs de l\'original inchangés');
		$this->assertEquals([0], array_values(AttachmentLib::getByCourse($eCourseCopy)->getColumn('position')), 'rang resserré dans la copie');

		$hash = $eAttachmentCopy['hash'];

		\media\Media::model()
			->select('status')
			->whereHash($hash)
			->update(['status' => \media\Media::SEARCHING]);

		$this->assertTrue(new \media\LearningAttachmentAiLib()->clean(), 'le nettoyage aboutit');

		$this->assertEquals(\media\Media::ACTIVE, \media\Media::model()->whereHash($hash)->getValue('status'), 'média gardé par l\'original');

	}

	/**
	 * Qui copie : l'équipe de la formation la duplique, un lecteur non. Une ferme n'écrit pas de
	 * formation, donc n'en reçoit aucune copie.
	 */
	public function testRights(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCoursePublished = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::ACCEPTED);
		$eCourseGroup = $this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), Course::PUBLISHED, Course::PRIVATE);

		$this->assertFalse(CourseLib::getCreateElement($this->getFarm())->acceptCreate(), 'une ferme ne reçoit pas de copie');

		$this->assertTrue($eCourseGroup->canUpdate() and $eCourseGroup->acceptUpdate(), 'l\'équipe du groupe duplique sa formation');
		$this->assertTrue($eCoursePublished->canUpdate() and $eCoursePublished->acceptUpdate(), 'l\'équipe duplique la sienne');

		$this->actAs($this->eUserReader);

		$this->assertFalse($eCourseGroup->canUpdate(), 'un lecteur ne duplique pas');
		$this->assertFalse($eCoursePublished->canUpdate(), 'un lecteur ne duplique pas une formation de l\'équipe');

	}

	/**
	 * Une formation de l'équipe ne se copie plus dans un groupe (demande du 06/10) : la
	 * fiche ne le propose plus à l'administrateur d'un groupe, et un `community` posté à la main ne
	 * mène plus la copie dans ce groupe. « Dupliquer » reste, dans le groupe de l'original.
	 */
	public function testCopyClosed(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createSource();

		$fiche = $this->renderRoute('formation/'.$eCourse['id'], view: 'learning/course');

		$this->assertTrue(str_contains($fiche, encode($eCourse['name'])), 'la fiche est rendue');
		$this->assertFalse(str_contains($fiche, 'community='), 'la fiche ne mène à aucune copie vers un groupe');

		$data = $this->executeView('learning/course:duplicate', ['id' => (string)$eCourse['id'], 'community' => (string)self::COMMUNITY_EDUCATION]);

		$this->assertTrue($data instanceof \stdClass and $data->eCourseNew->isTeam(), 'le panneau ignore le groupe demandé et duplique dans l\'équipe');

		$this->assertEquals(\RedirectAction::class, $this->executePage('learning/course:doDuplicate', ['id' => (string)$eCourse['id'], 'community' => (string)self::COMMUNITY_EDUCATION, 'name' => 'zzTest copie fermée'])[0], 'la duplication aboutit');

		$cCourse = Course::model()
			->select(['id', 'community', 'vignette'])
			->whereName('zzTest copie fermée')
			->getCollection();

		$communities = [];

		foreach($cCourse as $eCourseCopy) {
			$this->vignettesStored[] = $eCourseCopy['vignette'];
			$communities[] = $eCourseCopy['community']->empty() ? NULL : $eCourseCopy['community']['id'];
		}

		$this->assertEquals([NULL], $communities, 'la copie postée avec un groupe naît dans l\'équipe, jamais dans le groupe');

	}

	const CONTENT = '<div class="editor-text"><p>Le contenu de la leçon A2.</p></div>';

	/**
	 * La formation d'origine : publiée par l'équipe, deux modules, trois leçons dont une avec
	 * contenu et deux fichiers, deux encadrants, une vignette sur le disque, un lecteur inscrit.
	 */
	private function createSource(): Course {

		$eCourse = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::ACCEPTED);

		$eChapterA = $this->createChapter($eCourse, 'Module A');
		$eChapterB = $this->createChapter($eCourse, 'Module B');

		$this->createLesson($eChapterA, 'Leçon A1', 10);
		$eLessonA2 = $this->createLesson($eChapterA, 'Leçon A2', 20);
		$this->createLesson($eChapterB, 'Leçon B1', 30);

		$eLessonA2['content'] = self::CONTENT;
		LessonLib::update($eLessonA2, ['content']);

		// Le média seul, sans fichier : la copie ne lit que le hash, le nettoyage que le média.
		foreach(['Support.pdf' => 'f', 'Planche.png' => 'p'] as $name => $letter) {

			$hash = \media\UtilLib::generateHash().$letter;

			$eMedia = new \media\Media([
				'user' => $this->eUserAdmin,
				'type' => 'learning-attachment',
				'status' => \media\Media::ACTIVE,
				'updatedAt' => new \Sql('NOW()'),
				'hash' => $hash
			]);

			\media\Media::model()->insert($eMedia);

			AttachmentLib::create(new Attachment([
				'lesson' => $eLessonA2,
				'course' => $eCourse,
				'name' => $name,
				'hash' => $hash
			]));

		}

		foreach(['Ada' => '1', 'Bob' => '0'] as $name => $handicap) {

			$eSupervisor = SupervisorLib::getCreateElement($eCourse['community']);
			$eStaff = new Staff(['course' => $eCourse, 'supervisor' => $eSupervisor]);

			$fw = new \FailWatch();

			$eSupervisor->build(SupervisorLib::getPropertiesCreate('course'), ['name' => $name, 'role' => 'Formateur', 'description' => '', 'email' => '', 'handicap' => $handicap], new \PropertyList('create'));
			$eStaff->build(StaffLib::getPropertiesCreate('supervisor'), ['role' => 'Formateur', 'contact' => '0'], new \PropertyList('create'));

			$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('encadrant '.$name.' refusé : '.json_encode($fw->get())));

			StaffLib::createWithSupervisor($eStaff, $eSupervisor);

		}

		EnrollmentLib::create(new Enrollment(['user' => $this->eUserReader, 'course' => $eCourse, 'structure' => new \structure\Structure()]));

		$eCourse['vignette'] = $this->storeVignette();
		CourseLib::update($eCourse, ['vignette']);

		return $this->getCourse($eCourse['id']);

	}

	/**
	 * Un média `learning-course` et ses trois fichiers, sans passer par l'envoi du navigateur : la
	 * copie ne lit que le hash et les fichiers.
	 */
	private function storeVignette(): string {

		$hash = \media\UtilLib::generateHash().'j';
		$vignette = $hash.'#A0B0C0001';

		$eMedia = new \media\Media([
			'user' => $this->eUserAdmin,
			'type' => 'learning-course',
			'status' => \media\Media::ACTIVE,
			'updatedAt' => new \Sql('NOW()'),
			'hash' => $hash
		]);

		\media\Media::model()->insert($eMedia);

		$image = new \Imagick();
		$image->newImage(16, 9, 'green');
		$image->setImageFormat('jpeg');

		$ui = new \media\LearningCourseUi();

		foreach([NULL, 's', 'l'] as $format) {
			\media\MediaSetting::$mediaDriver->sendBinary($image->getImageBlob(), $ui->getBasenameByHash($vignette, $format));
		}

		\media\MediaSetting::$mediaDriver->saveMetadata($ui->getBasenameByHash($vignette), []);

		$this->vignettesStored[] = $vignette;

		return $vignette;

	}

	/**
	 * Le chemin de `page/course.p.php` après le contrôle des droits : l'élément neuf du groupe de
	 * l'original, le titre construit, puis la copie.
	 */
	private function duplicate(Course $eCourse, string $name): Course {

		$eCourseNew = CourseLib::getCreateElement($eCourse['community']);

		$fw = new \FailWatch();

		$eCourseNew->build(['name'], ['name' => $name], new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('titre de la copie refusé : '.json_encode($fw->get())));

		return CourseLib::duplicate($eCourse, $eCourseNew);

	}

}
?>
