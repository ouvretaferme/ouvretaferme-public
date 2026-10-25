<?php
namespace learning;

require_once __DIR__.'/LearningNetworkFixture.t.php';

/**
 * Le circuit du catalogue public d'une formation de réseau (lot L14) : le réseau demande, retire sa
 * demande ou sa formation, et redemande après un refus ; l'équipe de Ouvretaferme accepte, refuse
 * avec un motif, ou retire du catalogue avec un motif.
 *
 * Chaque geste est joué par sa route (`executePage()`), pour que le droit (`canUpdate` pour le
 * réseau, `validateAdmin()` et `canDecide` pour l'équipe) et l'état (`acceptCatalog*`) soient ceux
 * du produit. Les deux rôles sont éprouvés sur chaque geste : l'utilisateur 1, conseiller du réseau
 * et administrateur de Ouvretaferme, et le lecteur fictif, qu'un test fait entrer dans l'équipe du
 * réseau pour avoir un membre du réseau qui n'est pas de l'équipe de Ouvretaferme.
 *
 * Lancement : dev/test package=learning file=CatalogCircuit
 */
class CatalogCircuitTest extends LearningNetworkFixtureTest {

	const REASON = 'zzTest L14 : le public visé et les prérequis manquent.';

	/**
	 * Une formation de réseau ne se propose au catalogue qu'avec une offre (lot L15a,
	 * `Course::catalog.offer`, éprouvée par `Offer.t.php`). Ce fichier éprouve le circuit : chaque
	 * formation de réseau en ligne naît donc payée par le réseau, posée par le Lib sans les verrous
	 * du panneau, qui ne sont pas son sujet.
	 */
	protected function createCourse(\structure\Structure $eStructureCommunity, string $status, string $catalog, bool $classified = TRUE, string $type = Course::ONLINE): Course {

		$eCourse = parent::createCourse($eStructureCommunity, $status, $catalog, $classified, $type);

		if(
			$eCourse->isNetwork() and
			$eCourse->isDated() === FALSE
		) {
			$eCourse['offer'] = Course::NETWORK;
			CourseLib::update($eCourse, ['offer']);
		}

		return $eCourse;

	}

	/**
	 * Demander puis retirer la demande, par l'équipe du réseau ; la demande est refusée sur un
	 * brouillon, avec son message, et sur une formation déjà proposée.
	 */
	public function testRequest(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourseDraft = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);
		$this->assertEquals([\FailAction::class, 'Course::catalog.published'], $this->executePage('learning/course:doCatalogRequest', ['id' => $eCourseDraft['id']]), 'un brouillon ne se propose pas');
		$this->assertEquals(Course::PRIVATE, $this->getCourse($eCourseDraft['id'])['catalog'], 'le brouillon reste privé');

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE);

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/course:doCatalogRequest', ['id' => $eCourse['id']]), 'la demande part');

		$eCourse = $this->getCourse($eCourse['id']);
		$this->assertEquals(Course::PROPOSED, $eCourse['catalog'], 'la formation est proposée');
		$this->assertTrue($eCourse['catalogAt'] !== NULL, 'la date de la demande est posée');
		$this->assertEquals(NULL, $eCourse['catalogReason'], 'aucun motif');

		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/course:doCatalogRequest', ['id' => $eCourse['id']]), 'une demande en cours ne se redemande pas');

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/course:doCatalogWithdraw', ['id' => $eCourse['id']]), 'la demande se retire');
		$this->assertEquals(Course::PRIVATE, $this->getCourse($eCourse['id'])['catalog'], 'la formation redevient privée');

		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/course:doCatalogWithdraw', ['id' => $eCourse['id']]), 'une formation privée n\'a rien à retirer');

	}

	/**
	 * Le circuit est réservé aux formations de réseau : ni un groupe de formation, ni l'équipe de
	 * Ouvretaferme, dont les formations sont au catalogue d'office.
	 */
	public function testNetworkOnly(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourseEducation = $this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), Course::PUBLISHED, Course::PRIVATE);
		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/course:doCatalogRequest', ['id' => $eCourseEducation['id']]), 'un groupe de formation ne demande pas le catalogue');
		$this->assertEquals(\NotExpectedAction::class, $this->executeView('formation/'.$eCourseEducation['id'].'/catalogue', []), 'ni n\'a d\'onglet Diffusion à rejoindre');

		$eCourseTeam = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::ACCEPTED);
		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/course:doCatalogWithdraw', ['id' => $eCourseTeam['id']]), 'une formation de l\'équipe ne sort pas du catalogue par le circuit');
		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/admin/catalog:doRemove', ['id' => $eCourseTeam['id'], 'catalogReason' => self::REASON]), 'ni par le retrait de l\'équipe');

	}

	/**
	 * La roue d'une ligne de la liste mène à l'onglet Diffusion, sur une formation de réseau seulement.
	 */
	public function testListActions(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE);
		$this->assertTrue(str_contains($this->getList($eCourse), 'href="'.CatalogUi::urlCourse($eCourse).'"'), 'la roue de la ligne d\'une formation de réseau mène au catalogue public');

		$eCourseEducation = $this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), Course::PUBLISHED, Course::PRIVATE);
		$list = $this->getList($eCourseEducation);

		$this->assertTrue(str_contains($list, '/formation/'.$eCourseEducation['id'].'/edition'), 'la liste du groupe de formation porte bien sa ligne');
		$this->assertFalse(str_contains($list, CatalogUi::urlCourse($eCourseEducation)), 'mais pas d\'entrée Catalogue public');

	}

	/**
	 * La diffusion est un onglet de l'éditeur (retour de Vincent du 03/10, point 5), sur une
	 * formation de réseau de chaque type qu'on crée, une formation mixte ne se créant pas ainsi mais
	 * par ses séances, et l'ancienne adresse y mène. Une formation de groupe n'a pas de diffusion.
	 */
	public function testDiffusionTab(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		foreach([Course::ONSITE, Course::REMOTE, Course::ONLINE] as $type) {

			$eCourse = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE, type: $type);
			$this->assertTrue(str_contains($this->renderEdition($eCourse), 'data-tab="diffusion"'), $type.', l\'onglet de la diffusion');

			$this->assertEquals(\PermanentRedirectAction::class, $this->executeView('formation/'.$eCourse['id'].'/catalogue', []), $type.', l\'ancienne adresse redirige');

		}

		$eCourseEducation = $this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), Course::PUBLISHED, Course::PRIVATE);

		$this->assertFalse(str_contains($this->renderEdition($eCourseEducation), 'data-tab="diffusion"'), 'une formation de groupe n\'a pas de diffusion');

		// La carte de l'aperçu, lue comme un visiteur : diffusée au réseau, elle dit déjà comment payer.
		$eCourseCard = $this->executeView('formation/'.$eCourse['id'].'/edition', [])->eCourse;

		$this->assertTrue(CourseUi::getCardAccess($eCourseCard) !== '', 'la ligne d\'accès dit comment payer');

		$eCourseCard['offer'] = Course::NONE;

		$this->assertEquals('', CourseUi::getCardAccess($eCourseCard), 'hors catalogue public, aucune ligne d\'accès');

		// Une formation à des dates dit sur sa carte sa prochaine date (planche carte-hierarchie,
		// piste B). La date passe par build(), dans dix jours pour être acceptée.
		$eCourseDated = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE, type: Course::ONSITE);
		$this->createLesson($this->createChapter($eCourseDated, 'zzTest module de la carte datée'), 'zzTest leçon de la carte datée', 15);

		$eDate = new Date(['course' => $eCourseDated]);

		$fw = new \FailWatch();

		$eDate->build(DateLib::getPropertiesCreate(), [
			'sequences' => ['0'],
			'startAt' => ['0' => ['date' => $this->day(10), 'time' => '09:00']],
			'endAt' => ['0' => ['time' => '17:00']],
			'mode' => ['0' => $eCourseDated->getDefaultSequence()['mode']],
			'place' => ['0' => 'Salle des fêtes, Crest'],
			'seats' => '',
		], new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('date refusée : '.json_encode($fw->get())));

		DateLib::create($eDate);

		$eCourseDatedCard = $this->executeView('formation/'.$eCourseDated['id'].'/edition', [])->eCourse;

		$this->assertEquals($this->day(10).' 09:00:00', $eCourseDatedCard['dateNext'], 'la prochaine date en tête de carte');

	}

	private function renderEdition(Course $eCourse): string {

		$data = $this->executeView('formation/'.$eCourse['id'].'/edition', []);

		if(is_string($data)) {
			throw new \Exception('l\'éditeur de la formation '.$eCourse['id'].' a levé '.$data);
		}

		return new CourseUi()->getEdition($data->eCourse, $data->cChapter, $data->cDate, $data->cStaff, $data->tab, $data->withDiffusion, $data->cDatePlanned);

	}

	/**
	 * Accepter, refuser et retirer sont à l'équipe de Ouvretaferme : un membre de l'équipe du réseau
	 * qui n'en est pas est refusé sur les trois, et l'équipe du réseau demande à sa place.
	 */
	public function testDecideRole(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);
		$this->join($this->eUserReader, $this->eStructureNetwork);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE);

		$this->actAs($this->eUserReader);

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/course:doCatalogRequest', ['id' => $eCourse['id']]), 'un membre de l\'équipe du réseau demande');
		$this->assertAdminOnly(fn() => $this->executePage('learning/admin/catalog:doAccept', ['id' => $eCourse['id']]), 'mais n\'accepte pas');
		$this->assertAdminOnly(fn() => $this->executePage('learning/admin/catalog:doRefuse', ['id' => $eCourse['id'], 'catalogReason' => self::REASON]), 'ni ne refuse');
		$this->assertAdminOnly(fn() => $this->executeView('learning/admin/catalogue', []), 'ni n\'ouvre l\'onglet de l\'administration');
		$this->assertEquals(Course::PROPOSED, $this->getCourse($eCourse['id'])['catalog'], 'la demande attend');

		$this->actAs($this->eUserAdmin);
		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/admin/catalog:doAccept', ['id' => $eCourse['id']]), 'l\'équipe de Ouvretaferme accepte');
		$this->assertEquals(Course::ACCEPTED, $this->getCourse($eCourse['id'])['catalog'], 'la formation est au catalogue');

		$this->actAs($this->eUserReader);
		$this->assertAdminOnly(fn() => $this->executePage('learning/admin/catalog:doRemove', ['id' => $eCourse['id'], 'catalogReason' => self::REASON]), 'le réseau ne retire pas par la route de l\'équipe');
		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/course:doCatalogWithdraw', ['id' => $eCourse['id']]), 'il retire sa formation par la sienne');
		$this->assertEquals(Course::PRIVATE, $this->getCourse($eCourse['id'])['catalog'], 'la formation est sortie du catalogue');

	}

	/**
	 * Une demande survit à la dépublication de sa formation : l'équipe peut encore la refuser, mais
	 * pas faire entrer le brouillon au catalogue. Republiée, la formation s'accepte.
	 */
	public function testDraftAccept(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE);
		$this->executePage('learning/course:doCatalogRequest', ['id' => $eCourse['id']]);

		$eCourse = $this->getCourse($eCourse['id']);
		$eCourse['status'] = Course::DRAFT;
		CourseLib::update($eCourse, ['status']);

		$this->assertEquals([\FailAction::class, 'Course::catalog.draft'], $this->executePage('learning/admin/catalog:doAccept', ['id' => $eCourse['id']]), 'un brouillon n\'entre pas au catalogue');
		$this->assertEquals(Course::PROPOSED, $this->getCourse($eCourse['id'])['catalog'], 'la demande attend toujours');

		$eCourse['status'] = Course::PUBLISHED;
		CourseLib::update($eCourse, ['status']);

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/admin/catalog:doAccept', ['id' => $eCourse['id']]), 'republiée, elle s\'accepte');
		$this->assertEquals(Course::ACCEPTED, $this->getCourse($eCourse['id'])['catalog'], 'la formation est au catalogue');

	}

	/**
	 * Une formation au catalogue repassée en brouillon perd sa marque dans la liste du réseau : sa
	 * ligne ne dit rien du catalogue (arbitrage du 01/10, Q2). Sa place au catalogue ne bouge pas, et
	 * la marque revient à la publication.
	 */
	public function testDraftBadge(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE);
		$this->executePage('learning/course:doCatalogRequest', ['id' => $eCourse['id']]);
		$this->executePage('learning/admin/catalog:doAccept', ['id' => $eCourse['id']]);

		$eCourse = $this->getCourse($eCourse['id']);
		$eCourse['status'] = Course::DRAFT;
		CourseLib::update($eCourse, ['status']);

		$links = substr_count($this->getListRow($this->getList($eCourse), $eCourse), 'href="'.CatalogUi::urlCourse($eCourse).'"');

		$this->assertEquals(Course::ACCEPTED, $this->getCourse($eCourse['id'])['catalog'], 'la formation garde sa place');

		$eCourse['status'] = Course::PUBLISHED;
		CourseLib::update($eCourse, ['status']);

		$row = $this->getListRow($this->getList($eCourse), $eCourse);

		$this->assertEquals($links + 1, substr_count($row, 'href="'.CatalogUi::urlCourse($eCourse).'"'), 'publiée, la marque revient sous le nom, qu\'en brouillon elle n\'a pas : '.$row);

	}

	/**
	 * Le refus exige un motif, que le réseau lit ; la demande suivante l'efface. Une décision ne se
	 * prend que sur une demande en cours.
	 */
	public function testRefuse(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE);

		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/admin/catalog:doAccept', ['id' => $eCourse['id']]), 'rien à accepter sans demande');
		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/admin/catalog:doRefuse', ['id' => $eCourse['id'], 'catalogReason' => self::REASON]), 'rien à refuser sans demande');

		$this->executePage('learning/course:doCatalogRequest', ['id' => $eCourse['id']]);

		$this->assertEquals([\FailAction::class, 'Course::catalogReason.empty'], $this->executePage('learning/admin/catalog:doRefuse', ['id' => $eCourse['id']]), 'un refus sans motif est refusé');
		$this->assertEquals([\FailAction::class, 'Course::catalogReason.empty'], $this->executePage('learning/admin/catalog:doRefuse', ['id' => $eCourse['id'], 'catalogReason' => '   ']), 'un motif fait d\'espaces aussi');
		$this->assertEquals(Course::PROPOSED, $this->getCourse($eCourse['id'])['catalog'], 'la demande attend toujours');

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/admin/catalog:doRefuse', ['id' => $eCourse['id'], 'catalogReason' => self::REASON]), 'le refus avec motif passe');

		$eCourse = $this->getCourse($eCourse['id']);
		$this->assertEquals(Course::REFUSED, $eCourse['catalog'], 'la demande est refusée');
		$this->assertEquals(self::REASON, $eCourse['catalogReason'], 'le motif est enregistré');

		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/admin/catalog:doAccept', ['id' => $eCourse['id']]), 'un refus ne s\'accepte pas sans nouvelle demande');
		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/course:doCatalogWithdraw', ['id' => $eCourse['id']]), 'un refus ne se retire pas');

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/course:doCatalogRequest', ['id' => $eCourse['id']]), 'le réseau redemande après un refus');

		$eCourse = $this->getCourse($eCourse['id']);
		$this->assertEquals(Course::PROPOSED, $eCourse['catalog'], 'la formation est de nouveau proposée');
		$this->assertEquals(NULL, $eCourse['catalogReason'], 'le motif du refus est effacé');

	}

	/**
	 * L'équipe retire une formation retenue, avec un motif ; le réseau peut la redemander ensuite.
	 */
	public function testRemove(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE);

		$this->executePage('learning/course:doCatalogRequest', ['id' => $eCourse['id']]);

		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/admin/catalog:doRemove', ['id' => $eCourse['id'], 'catalogReason' => self::REASON]), 'une demande ne se retire pas du catalogue');

		$this->executePage('learning/admin/catalog:doAccept', ['id' => $eCourse['id']]);

		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/admin/catalog:doRefuse', ['id' => $eCourse['id'], 'catalogReason' => self::REASON]), 'une formation retenue ne se refuse plus');
		$this->assertEquals([\FailAction::class, 'Course::catalogReason.empty'], $this->executePage('learning/admin/catalog:doRemove', ['id' => $eCourse['id'], 'catalogReason' => '']), 'un retrait sans motif est refusé');

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/admin/catalog:doRemove', ['id' => $eCourse['id'], 'catalogReason' => self::REASON]), 'le retrait avec motif passe');

		$eCourse = $this->getCourse($eCourse['id']);
		$this->assertEquals(Course::PRIVATE, $eCourse['catalog'], 'la formation est privée');
		$this->assertEquals(self::REASON, $eCourse['catalogReason'], 'avec le motif du retrait');

		$this->assertTrue(str_contains($this->renderPage($eCourse), 'learning/course:doCatalogRequest'), 'la page du réseau offre de redemander');

		// La décision de l'équipe se voit depuis la liste du réseau, jusqu'à la demande suivante.
		$this->assertEquals(Course::PRIVATE, $this->getListCatalogBadge($eCourse), 'la liste du réseau marque le retrait');

		$this->executePage('learning/course:doCatalogRequest', ['id' => $eCourse['id']]);

		$this->assertEquals(Course::PROPOSED, $this->getListCatalogBadge($eCourse), 'la demande suivante fait tomber la marque du retrait, au profit de celle de la demande');

	}

	/**
	 * Le catalogue public ne change pas : il montre les formations retenues et publiées, et elles
	 * seules. Une formation de réseau retenue s'y lit comme une formation de l'équipe.
	 */
	public function testCatalog(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$cCourse = [];

		foreach([Course::PROPOSED, Course::REFUSED, Course::ACCEPTED] as $catalog) {
			$cCourse[$catalog] = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, $catalog);
		}

		$eCourseDraft = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::ACCEPTED);

		$catalog = CourseLib::getCatalog()->getIds();

		$this->assertFalse(in_array($cCourse[Course::PROPOSED]['id'], $catalog), 'une demande n\'y est pas');
		$this->assertFalse(in_array($cCourse[Course::REFUSED]['id'], $catalog), 'un refus n\'y est pas');
		$this->assertTrue(in_array($cCourse[Course::ACCEPTED]['id'], $catalog), 'une formation retenue y est');
		$this->assertFalse(in_array($eCourseDraft['id'], $catalog), 'retenue mais dépubliée, elle n\'y paraît pas');

		$this->actAs($this->eUserReader);

		$this->assertTrue($this->getCourse($cCourse[Course::ACCEPTED]['id'])->assertRead(), 'un compte hors du réseau lit la formation retenue');
		$this->assertFalse($this->getCourse($cCourse[Course::PROPOSED]['id'])->assertRead(), 'pas une demande');
		$this->assertFalse($this->getCourse($cCourse[Course::REFUSED]['id'])->assertRead(), 'ni un refus');

		$data = $this->executeView('formations', []);
		$this->assertTrue(new CatalogUi()->getFormations($data->cCourseFeatured, $data->cThemeCatalog, $data->courses, $data->isLogged) !== '', 'le catalogue se rend');

		$this->actAs(new \user\User());
		$this->assertFalse($this->getCourse($cCourse[Course::ACCEPTED]['id'])->canReadLessons(), 'le catalogue n\'ouvre pas les leçons sans compte');

	}

	/**
	 * Les trois sections de « Mes formations » : « À suivre », la section du réseau et « Suivies en
	 * entier ». Le compte, membre du réseau, s'en voit proposer une formation, s'inscrit à deux
	 * autres, retenues au catalogue pour qu'il puisse les lire, et lit l'unique leçon de la seconde.
	 */
	public function testMineGrids(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourseShared = $this->seed();
		$this->join($this->eUserReader, $this->eStructureNetwork, 'member');
		$this->assertEquals([], $this->shareNetwork($eCourseShared), 'formation ouverte aux membres du réseau');

		$eCourseRunning = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::ACCEPTED);
		$this->createLesson($this->createChapter($eCourseRunning, 'zzTest grilles · à suivre'), 'zzTest leçon à lire', 10);

		$eCourseDone = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::ACCEPTED);
		$eLessonDone = $this->createLesson($this->createChapter($eCourseDone, 'zzTest grilles · suivie'), 'zzTest leçon lue', 10);

		$this->actAs($this->eUserReader);

		EnrollmentLib::create(new Enrollment(['user' => $this->eUserReader, 'course' => $eCourseRunning, 'structure' => $this->eStructureFarm]));

		$eEnrollmentDone = new Enrollment(['user' => $this->eUserReader, 'course' => $eCourseDone, 'structure' => $this->eStructureFarm]);
		EnrollmentLib::create($eEnrollmentDone);
		ProgressLib::done($eEnrollmentDone, $eLessonDone);

		$data = $this->executeView('mes-formations', []);

		$this->assertTrue($data->cCourseRunning->offsetExists($eCourseRunning['id']), '« À suivre » porte la formation commencée');
		$this->assertTrue($data->ccShareProposed->offsetExists('community-'.$this->eStructureNetwork['id']), 'la section du réseau porte la formation proposée');
		$this->assertTrue($data->cCourseDone->offsetExists($eCourseDone['id']), '« Suivies en entier » porte la formation lue');

	}

	/**
	 * L'onglet Diffusion et l'onglet de l'administration : l'équipe du réseau ouvre l'onglet de
	 * sa formation, un membre d'un autre réseau non ; l'onglet range chaque formation sous son état.
	 */
	public function testScreens(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE);

		$page = $this->renderPage($eCourse);
		$this->assertTrue(str_contains($page, 'data-ajax="/learning/course:doCatalogRequest" post-id="'.$eCourse['id'].'"'), 'hors catalogue, avec la demande : '.$page);

		$this->assertEquals(NULL, $this->getListCatalogBadge($eCourse), 'jamais demandée, aucune marque dans la liste du réseau');

		$eCourseOther = $this->createCourse($this->getNetworkOther(), Course::PUBLISHED, Course::PRIVATE);
		$this->join($this->eUserReader, $eCourseOther['community']);

		$this->actAs($this->eUserReader);
		$this->assertEquals(\NotAllowedAction::class, $this->executeView('formation/'.$eCourse['id'].'/catalogue', []), 'un membre d\'un autre réseau n\'ouvre pas la page');
		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('learning/course:doCatalogRequest', ['id' => $eCourse['id']]), 'ni ne demande pour ce réseau');
		$this->assertEquals(Course::PRIVATE, $this->getCourse($eCourse['id'])['catalog'], 'la formation reste privée');

		$this->actAs($this->eUserAdmin);
		$this->executePage('learning/course:doCatalogRequest', ['id' => $eCourse['id']]);

		$page = $this->renderPage($this->getCourse($eCourse['id']));
		$this->assertTrue(str_contains($page, 'data-ajax="/learning/course:doCatalogWithdraw"'), 'la demande, avec son retrait : '.$page);

		$this->assertEquals(Course::PROPOSED, $this->getListCatalogBadge($eCourse), 'la liste du réseau marque la demande');

		$data = $this->executeView('learning/admin/catalogue', []);
		$this->assertTrue($data->cCourseProposed->offsetExists($eCourse['id']), 'l\'onglet la range sous les demandes');
		$this->assertFalse($data->cCourseAccepted->offsetExists($eCourse['id']) or $data->cCourseRefused->offsetExists($eCourse['id']), 'et nulle part ailleurs');

		$admin = new CatalogUi()->getAdmin($data->cCourseProposed, $data->cCourseAccepted, $data->cCourseFeatured, $data->cCourseRefused);
		$this->assertTrue(str_contains($admin, 'data-ajax="/learning/admin/catalog:doAccept" post-id="'.$eCourse['id'].'"'), 'avec « Accepter »');
		$this->assertTrue(str_contains($admin, 'href="/learning/admin/catalog:refuse?id='.$eCourse['id'].'"'), 'et « Refuser »');

		$panel = $this->executeView('learning/admin/catalog:refuse', ['id' => $eCourse['id']]);
		$this->assertTrue(str_contains(new CatalogUi()->decide($panel->e, 'refuse')->body, 'name="catalogReason"'), 'le panneau du refus demande le motif');

		$this->assertTrue(str_contains($this->getEditionHead($eCourse), 'href="'.CatalogUi::urlCourse($eCourse).'"'), 'la roue de l\'éditeur mène à la page');

	}

	/**
	 * Réglages ne perd rien (porte L14, question 1) : une formation de groupe, qui n'a pas de page
	 * Catalogue public, y garde sa thématique et son niveau. Le niveau posté s'enregistre ; la roue
	 * n'offre pas la page.
	 */
	public function testSettingsEducation(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), Course::DRAFT, Course::PRIVATE);

		$data = $this->executeView('learning/course:updateSettings', ['id' => $eCourse['id']]);
		$settings = new CourseUi()->updateSettings($data->e)->body;

		$this->assertTrue(str_contains($settings, 'data-wrapper="theme"'), 'la thématique reste');
		$this->assertTrue(str_contains($settings, 'data-wrapper="level"'), 'le niveau reste');

		$eLevelOther = LevelLib::getAll()->find(fn(Level $eLevel) => $eLevel['id'] !== $this->eLevel['id'])->first();

		$input = [
			'id' => (string)$eCourse['id'],
			'name' => $eCourse['name'],
			'summary' => 'Une formation du décor de test.',
			'theme' => (string)$this->eTheme['id'],
			'level' => (string)$eLevelOther['id'],
			'type' => $eCourse['type'],
		];

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/course:doUpdateSettings', $input), 'les réglages s\'enregistrent');
		$this->assertEquals($eLevelOther['id'], $this->getCourse($eCourse['id'])['level']['id'], 'le niveau posté est enregistré');

		$this->assertFalse(str_contains($this->getEditionHead($eCourse), CatalogUi::urlCourse($eCourse)), 'la roue d\'un groupe n\'offre pas la page');

	}

	/**
	 * La liste de l'administration lit l'index `(catalog, status)`, jamais la table entière. La
	 * requête citée est celle de `CourseLib::getByCatalog()`, recopiée ici pour l'`EXPLAIN`.
	 */
	public function testExplain(): void {

		$sql = new CourseModel()
			->select(['id'])
			->whereCatalog(Course::PROPOSED)
			->whereCommunity('!=', NULL)
			->getSql();

		$rows = Course::model()->pdo()->query('EXPLAIN '.$sql)->fetchAll(\PDO::FETCH_ASSOC);

		// Le générateur nomme les index par leur rang : celui de `(catalog, status)` se lit dans la base.
		$index = Course::model()->pdo()->query('SHOW INDEX FROM '.Course::model()->getDatabase().'.'.Course::model()->getTable().' WHERE Column_name = \'catalog\' AND Seq_in_index = 1')->fetch(\PDO::FETCH_ASSOC)['Key_name'];

		$this->assertEquals('ref', $rows[0]['type'], 'accès par index : '.json_encode($rows));
		$this->assertEquals($index, $rows[0]['key'], 'l\'index (catalog, status) : '.json_encode($rows));

	}

	/**
	 * Hors de l'équipe de Ouvretaferme, l'administration refuse par `validateAdmin()`, qui lève une
	 * page désactivée et non une action : le lanceur de pages ne l'attrape pas.
	 */
	private function assertAdminOnly(\Closure $execute, string $message): void {

		try {
			$execute();
			$this->assertFail($message.' : la route a répondu');
		} catch(\DisabledPage) {
			$this->assertTrue(TRUE, $message);
		}

	}

	private function renderPage(Course $eCourse): string {

		$data = $this->executeView('formation/'.$eCourse['id'].'/edition', []);

		if(is_string($data)) {
			throw new \Exception('l\'onglet Diffusion de la formation '.$eCourse['id'].' a levé '.$data);
		}

		return new CatalogUi()->getCourse($data->eCourse, $data->cDatePlanned);

	}

	/** La marque du catalogue que la liste des formations du réseau pose sur la formation. */
	private function getListCatalogBadge(Course $eCourse): ?string {

		$data = $this->executeView('groupe/'.$this->eStructureNetwork['id'].'/formations', []);

		if(is_string($data)) {
			throw new \Exception('la liste des formations du réseau a levé '.$data);
		}

		return $data->cCourse[$eCourse['id']]['catalogBadge'];

	}

	/** La liste des formations du groupe de la formation, rendue comme la page la rend. */
	private function getList(Course $eCourse): string {

		$data = $this->executeView('groupe/'.$eCourse['community']['id'].'/formations', []);

		if(is_string($data)) {
			throw new \Exception('la liste des formations du groupe '.$eCourse['community']['id'].' a levé '.$data);
		}

		return new CourseUi()->getCommunityList($data->eStructure, $data->cCourse, $data->types, $data->type, $data->withRequested);

	}

	/** La ligne d'une formation dans le tableau rendu, repérée par le lien de son nom. */
	private function getListRow(string $list, Course $eCourse): string {

		foreach(explode('<div class="util-table-row">', $list) as $row) {
			if(str_contains($row, '<a href="'.CourseUi::urlEdition($eCourse).'"><b>')) {
				return $row;
			}
		}

		throw new \Exception('la formation '.$eCourse['id'].' n\'a pas de ligne dans la liste');

	}

	private function getEditionHead(Course $eCourse): string {

		$data = $this->executeView('formation/'.$eCourse['id'].'/edition', []);

		if(is_string($data)) {
			throw new \Exception('l\'éditeur de la formation '.$eCourse['id'].' a levé '.$data);
		}

		return new CourseUi()->getEditionHead($data->eCourse);

	}

	private function getNetworkOther(): \structure\Structure {

		$eStructure = \structure\Structure::model()
			->select(\structure\Structure::getSelection())
			->whereType(\structure\Structure::COMMUNITY)
			->whereCommunityType(\structure\Structure::NETWORK)
			->whereId('!=', self::COMMUNITY_NETWORK)
			->sort(['id' => SORT_ASC])
			->get();

		if($eStructure->empty()) {
			throw new \Exception('aucun autre réseau dans cette base');
		}

		return $eStructure;

	}

}
?>
