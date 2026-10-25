<?php
namespace learning;

require_once __DIR__.'/LearningNetworkFixture.t.php';

/**
 * L'onglet « Formations » d'un membre du réseau (choix 1 A du lot glacons-parcours) : il y lit les
 * formations que le réseau ouvre à ses membres, les cartes de « Mes formations ». L'équipe garde
 * son tableau, et le reste de l'onglet (commentaires, avis, encadrants)
 * lui reste réservé.
 *
 * Le décor est celui de `LearningNetworkFixture.t.php` : le lecteur fictif, membre de la ferme
 * rattachée, entre aussi dans le réseau sous le rôle `member`, qui n'est pas de l'équipe.
 */
class CommunityMemberTest extends LearningDateFixtureTest {

	/**
	 * Le drapeau de l'onglet ne se lève qu'avec une formation du réseau partagée au membre.
	 */
	public function testHasShared(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seedMember();

		$this->assertFalse(ShareLib::hasNetworkByUser($this->eUserReader, $this->eStructureNetwork), 'rien de partagé, pas d\'onglet');

		$this->assertEquals([], $this->shareNetwork($eCourse), 'ouverte aux membres du réseau');

		$this->assertTrue(ShareLib::hasNetworkByUser($this->eUserReader, $this->eStructureNetwork), 'partagée, l\'onglet paraît');
		$this->assertFalse(ShareLib::hasNetworkByUser($this->eUserReader, $this->getCommunity(self::COMMUNITY_EDUCATION)), 'pas dans un autre groupe');

	}

	/**
	 * L'onglet du membre suit le drapeau que pose `Lime.s.php` par `ShareLib::hasNetworkByUser()`.
	 */
	public function testTab(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->seedMember();

		$this->actAs($this->eUserReader);

		$eStructure = \structure\StructureLib::getById($this->eStructureNetwork['id']);
		$href = 'href="/groupe/'.$eStructure['id'].'/formations"';

		$this->assertFalse(str_contains(new \structure\StructureCommunityUi()->getTabs($eStructure, 0, TRUE, FALSE, 'home'), $href), 'rien de partagé, pas d\'onglet');
		$this->assertTrue(str_contains(new \structure\StructureCommunityUi()->getTabs($eStructure, 0, TRUE, TRUE, 'home'), $href), 'une formation partagée, l\'onglet');

		$this->actAs($this->eUserAdmin);

	}

	/**
	 * Le membre lit les cartes de ses formations du réseau, filtrables par type ; sans formation
	 * partagée, l'onglet n'existe pas pour lui.
	 */
	public function testPage(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seedMember();

		$this->actAs($this->eUserReader);

		$this->assertEquals(\NotAllowedAction::class, $this->executeView('groupe/'.$this->eStructureNetwork['id'].'/formations', []), 'rien de partagé, refusé');

		$this->actAs($this->eUserAdmin);
		$this->shareNetwork($eCourse);
		$this->actAs($this->eUserReader);

		$data = $this->executeView('groupe/'.$this->eStructureNetwork['id'].'/formations', []);

		$this->assertTrue($data instanceof \stdClass, 'le membre entre : '.(is_string($data) ? $data : 'vue'));
		$this->assertEquals([$eCourse['id']], $data->cShareShown->getColumnCollection('course')->getIds(), 'sa formation partagée');
		$this->assertTrue($data->cCourse->empty(), 'et non le tableau de l\'équipe');

		$html = new CatalogUi()->getCommunityMember($data->eStructure, $data->cShareShown, $data->types, $data->type);

		$this->assertTrue($html !== '', 'les cartes de Mes formations se rendent');

		$data = $this->executeView('groupe/'.$this->eStructureNetwork['id'].'/formations', ['type' => Course::ONSITE]);

		$this->assertTrue($data->cShareShown->empty(), 'le filtre par type réduit les cartes');

		foreach(['groupe/'.$this->eStructureNetwork['id'].'/formations/commentaires', 'groupe/'.$this->eStructureNetwork['id'].'/formations/encadrants', 'groupe/'.$this->eStructureNetwork['id'].'/formations/avis'] as $path) {
			$this->assertEquals(\NotAllowedAction::class, $this->executeView($path, []), $path.' reste à l\'équipe');
		}

	}

	/**
	 * Une carte par formation ouverte aux membres : celle où il est inscrit porte son avancement,
	 * les autres l'inscription, ou la demande quand elle attend ; une formation que le réseau
	 * n'ouvre pas n'y paraît pas.
	 */
	public function testCards(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourseEnrolled = $this->seedMember();
		$eLesson = $this->createLesson($this->createChapter($eCourseEnrolled, 'zzTest module'), 'zzTest leçon', 10);
		$this->createLesson($this->createChapter($eCourseEnrolled, 'zzTest module 2'), 'zzTest leçon 2', 10);

		$eCourseProposed = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE);
		$eCourseRequested = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE);
		// Publiée et jamais ouverte aux membres : elle ne doit pas paraître.
		$this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE);

		foreach([$eCourseEnrolled, $eCourseProposed, $eCourseRequested] as $eCourse) {
			$this->assertEquals([], $this->shareNetwork($eCourse), 'formation '.$eCourse['id'].' ouverte aux membres');
		}

		$eEnrollment = $this->enrollReader($eCourseEnrolled);
		ProgressLib::done($eEnrollment, $eLesson);

		// Une demande écrite telle que la pose l'inscription d'un réseau qui confirme : `build()` n'y
		// jugerait que la structure du compte, que le test laisse vide, et `EnrollmentLib::create()`
		// n'écrit qu'une inscription confirmée.
		$eEnrollmentRequested = new Enrollment([
			'user' => $this->eUserReader,
			'course' => $eCourseRequested,
			'structure' => new \structure\Structure(),
			'status' => Enrollment::REQUESTED,
			'fundingRequested' => TRUE,
			'confirmedAt' => NULL
		]);

		Enrollment::model()->insert($eEnrollmentRequested);

		$this->actAs($this->eUserReader);

		$data = $this->executeView('groupe/'.$this->eStructureNetwork['id'].'/formations', []);

		$this->assertTrue($data instanceof \stdClass, 'le membre entre : '.(is_string($data) ? $data : 'vue'));

		$courses = $data->cShareShown->getColumnCollection('course')->getIds();
		sort($courses);

		$this->assertEquals([$eCourseEnrolled['id'], $eCourseProposed['id'], $eCourseRequested['id']], $courses, 'les trois formations ouvertes, pas la quatrième');

		$cards = [];
		$cCourse = new \Collection();

		foreach($data->cShareShown as $eShare) {
			$cards[$eShare['course']['id']] = new CourseUi()->getCard($eShare['course'], TRUE, FALSE, withAuthor: FALSE, proposed: $eShare['course']['proposed']);
			$cCourse[$eShare['course']['id']] = $eShare['course'];
		}

		$this->assertFalse($cCourse[$eCourseEnrolled['id']]['proposed'], 'inscrit, son avancement plutôt que l\'inscription');
		$this->assertTrue($cCourse[$eCourseProposed['id']]['proposed'] and $cCourse[$eCourseProposed['id']]['eEnrollmentRequest']->empty(), 'non inscrit, l\'inscription');
		$this->assertTrue($cCourse[$eCourseRequested['id']]['proposed'] and $cCourse[$eCourseRequested['id']]['eEnrollmentRequest']->notEmpty(), 'demande en attente, la demande plutôt que l\'inscription');
		$this->assertTrue(str_contains($cards[$eCourseProposed['id']], 'href="'.CourseUi::url($eCourseProposed, ['enroll' => 1]).'"'), 'non inscrit, la carte ouvre le panneau d\'inscription sur la fiche');
		$this->assertTrue(str_contains($cards[$eCourseRequested['id']], 'href="'.CourseUi::url($eCourseRequested).'"'), 'demande en attente, la fiche sans panneau');

	}

	/**
	 * Une formation datée se dit par la place du membre à l'une de ses dates : confirmée, la carte
	 * porte son avancement comme une inscription ; demandée, la demande.
	 */
	public function testCardsDated(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourseConfirmed] = $this->seedCourse(quiz: FALSE);
		[$eCourseRequested] = $this->seedCourse(quiz: FALSE, first: FALSE);

		$this->join($this->eUserReader, $this->eStructureNetwork, 'member');

		$this->enrollConfirmed($this->createDate($eCourseConfirmed, 5), $this->eUserReader);
		$this->enrollRequested($this->createDate($eCourseRequested, 5), $this->eUserReader);

		$this->actAs($this->eUserReader);

		$data = $this->executeView('groupe/'.$this->eStructureNetwork['id'].'/formations', []);

		$this->assertTrue($data instanceof \stdClass, 'le membre entre : '.(is_string($data) ? $data : 'vue'));

		$cCourse = new \Collection();

		foreach($data->cShareShown as $eShare) {
			$cCourse[$eShare['course']['id']] = $eShare['course'];
		}

		$this->assertEquals([$eCourseConfirmed['id'], $eCourseRequested['id']], $cCourse->getIds(), 'les deux formations datées');

		$this->assertFalse($cCourse[$eCourseConfirmed['id']]['proposed'], 'place confirmée, son avancement plutôt que l\'inscription');
		$this->assertTrue($cCourse[$eCourseRequested['id']]['proposed'] and $cCourse[$eCourseRequested['id']]['eDateEnrollmentRequest']->notEmpty(), 'place demandée, la demande plutôt que l\'inscription');

	}

	/**
	 * L'équipe garde son tableau.
	 */
	public function testStaff(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seedMember();
		$this->shareNetwork($eCourse);

		$data = $this->executeView('groupe/'.$this->eStructureNetwork['id'].'/formations', []);

		$this->assertTrue($data instanceof \stdClass, 'l\'équipe entre');
		$this->assertTrue($data->cCourse->offsetExists($eCourse['id']), 'et lit le tableau des formations du réseau');
		$this->assertTrue($data->cShare->empty(), 'sans les cartes du membre');

	}

	private function seedMember(): Course {

		$eCourse = $this->seed();

		$this->join($this->eUserReader, $this->eStructureNetwork, 'member');

		return $eCourse;

	}

}
?>
