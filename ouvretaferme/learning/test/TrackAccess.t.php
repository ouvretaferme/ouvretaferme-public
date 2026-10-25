<?php
namespace learning;

require_once __DIR__.'/LearningNetworkFixture.t.php';

/**
 * Les accès au suivi des stagiaires (lot L11a) : le nombre de lecteurs de la ligne sous le titre de
 * l'éditeur, et l'entrée de la roue. Une seule condition pour
 * les deux, `Course::assertTrack()` : qui peut ouvrir le suivi (`canUpdate` et `acceptTrack`, la
 * paire de la route), sur une formation ouverte à quelqu'un.
 *
 * Chaque écran est préparé par sa page (`executeView()`), puis rendu par la vue, pour que le test
 * lise ce que la page pose et non un élément monté à la main. Les deux sens sont éprouvés : le lien
 * pour l'équipe, aucun lien pour un stagiaire, aucun sur une formation ouverte à personne ni sur
 * une formation de l'équipe Ouvretaferme, qui n'a pas de suivi.
 */
class TrackAccessTest extends LearningNetworkFixtureTest {

	/**
	 * Groupe de formation : rien tant que la formation n'est ouverte à aucune promotion ; une fois
	 * partagée, la roue de la ligne gagne son entrée, et l'éditeur ouvre le suivi.
	 */
	public function testEducation(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCommunity = $this->getCommunity(self::COMMUNITY_EDUCATION);
		$eCourse = $this->createCourse($eCommunity, Course::PUBLISHED, Course::PRIVATE);
		$url = ProgressUi::urlTrack($eCourse);

		$row = $this->getListRow($eCommunity, $eCourse);
		$this->assertEquals(0, substr_count($row, $url), 'ouverte à personne, aucun accès dans la liste : '.$row);

		$head = $this->getEditionHead($eCourse);
		$this->assertTrue(str_contains($head, '<a href="/groupe/'.$eCommunity['id'].'/formations"'), 'le disque de retour ramène aux formations du groupe : '.$head);
		$this->assertEquals(0, substr_count($head, $url), 'ouverte à personne, aucun accès dans l\'éditeur');

		$this->share($eCourse, [
			$this->createSection($eCommunity, 'zzTest promotion du suivi', [$this->eUserReader])['id'] => [\Clock::date(), NULL]
		]);

		$this->assertEquals(0, $this->getListData($eCommunity)->cCourse[$eCourse['id']]['readers'], 'partagée, aucun lecteur encore');

		$row = $this->getListRow($eCommunity, $eCourse);
		$this->assertTrue(str_contains($row, '<a href="'.$url.'"'), 'la roue porte l\'accès au suivi : '.$row);

		$head = $this->getEditionHead($eCourse);
		$this->assertTrue(str_contains($head, '<a href="'.$url.'"'), 'partagée, l\'éditeur ouvre le suivi, même à zéro : '.$head);

		$this->assertNoAccessFor($this->eUserReader, $eCommunity, $eCourse, 'un stagiaire de la promotion');

	}

	/**
	 * Réseau : la roue d'une ligne n'avait pas d'entrée vers le suivi ; elle en gagne une, sous la
	 * même condition que le nombre de l'éditeur.
	 */
	public function testNetwork(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seed();
		$url = ProgressUi::urlTrack($eCourse);

		// Une formation de réseau a des inscrits sans partage : le suivi ne l'attend pas.
		$row = $this->getListRow($this->eStructureNetwork, $eCourse);
		$this->assertTrue(str_contains($row, '<a href="'.$url.'"'), 'ouverte à personne, la roue du réseau porte déjà le suivi : '.$row);

		$this->assertEquals([], $this->shareNetwork($eCourse), 'l\'ouverture aux membres');

		EnrollmentLib::create(new Enrollment(['user' => $this->eUserReader, 'course' => $eCourse, 'structure' => $this->eStructureFarm]));

		$this->assertEquals(1, $this->getListData($this->eStructureNetwork)->cCourse[$eCourse['id']]['readers'], 'partagée, un lecteur');

		$head = $this->getEditionHead($eCourse);
		$this->assertTrue(str_contains($head, '<a href="'.$url.'"'), 'l\'éditeur ouvre le suivi : '.$head);

		$this->assertNoAccessFor($this->eUserReader, $this->eStructureNetwork, $eCourse, 'un stagiaire d\'une ferme rattachée');

	}

	/**
	 * Une formation de l'équipe n'a pas de suivi (`acceptTrack()`) : l'éditeur, préparé par sa page,
	 * ne propose rien, et la roue du catalogue non plus.
	 */
	public function testTeam(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::ACCEPTED);
		$url = ProgressUi::urlTrack($eCourse);

		$head = $this->getEditionHead($eCourse);
		$this->assertEquals(0, substr_count($head, $url), 'l\'éditeur d\'une formation de l\'équipe');

		$data = $this->executeView('learning/admin/course', []);
		$this->assertTrue($data instanceof \stdClass, 'le catalogue de l\'équipe s\'ouvre : '.(is_string($data) ? $data : ''));

		$html = new CourseUi()->getTeamList($data->cThemeCourse, $data->cCourseUnclassified);
		$this->assertTrue(str_contains($html, 'href="'.CourseUi::urlEdition($eCourse).'"'), 'la formation est dans le catalogue');
		$this->assertEquals(0, substr_count($html, $url), 'sans accès au suivi');

	}

	/**
	 * La fiche que lit le stagiaire ne change pas : aucun lien vers le suivi de l'équipe.
	 */
	public function testFiche(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seed();
		$this->assertEquals([], $this->shareNetwork($eCourse), 'l\'ouverture aux membres');
		EnrollmentLib::create(new Enrollment(['user' => $this->eUserReader, 'course' => $eCourse, 'structure' => $this->eStructureFarm]));

		$this->actAs($this->eUserReader);

		$data = $this->executeView('formation/'.$eCourse['id'], []);
		$this->assertTrue($data instanceof \stdClass, 'la fiche s\'ouvre au stagiaire : '.(is_string($data) ? $data : ''));

		$html = new CourseUi()->getFiche($data->eCourse, $data->cChapter, $data->cStaff, $data->eEnrollment, $data->cProgress, $data->cStructure, $data->isLogged, $data->cDate);

		$this->assertTrue(str_contains($html, encode($eCourse['name'])), 'la fiche est rendue');
		$this->assertEquals(0, substr_count($html, ProgressUi::urlTrack($eCourse)), 'sans lien vers le suivi');

	}

	/**
	 * Les écrans que la page prépare pour l'équipe, rendus sous le compte `$eUser`, qui n'a pas le
	 * droit d'ouvrir le suivi : la page de la liste et celle de l'éditeur le refuseraient, et le
	 * test ne vise pas cette porte-là. Il vérifie que la condition des trois accès pèse le compte
	 * et pas seulement la formation.
	 */
	private function assertNoAccessFor(\user\User $eUser, \structure\Structure $eCommunity, Course $eCourse, string $who): void {

		$dataList = $this->getListData($eCommunity);
		$dataEdition = $this->getEditionData($eCourse);

		$this->actAs($eUser);

		$url = ProgressUi::urlTrack($eCourse);

		$this->assertFalse($dataEdition->eCourse->canUpdate(), $who.' ne modifie pas la formation');

		$row = $this->findRow($this->renderList($dataList), $eCourse);
		$this->assertEquals(0, substr_count($row, $url), $who.', aucun accès dans la liste : '.$row);

		$head = new CourseUi()->getEditionHead($dataEdition->eCourse);
		$this->assertEquals(0, substr_count($head, $url), $who.', aucun accès dans l\'éditeur');

		$this->actAs($this->eUserAdmin);

	}

	private function getListData(\structure\Structure $eCommunity): \stdClass {

		$data = $this->executeView('groupe/'.$eCommunity['id'].'/formations', []);

		if(is_string($data)) {
			throw new \Exception('la liste des formations du groupe '.$eCommunity['id'].' a levé '.$data);
		}

		return $data;

	}

	private function renderList(\stdClass $data): string {
		return new CourseUi()->getCommunityList($data->eStructure, $data->cCourse, $data->types, $data->type, $data->withRequested);
	}

	private function getListRow(\structure\Structure $eCommunity, Course $eCourse): string {
		return $this->findRow($this->renderList($this->getListData($eCommunity)), $eCourse);
	}

	/**
	 * La ligne de la formation dans le tableau, seule : le groupe en porte d'autres, dont les
	 * liens ne regardent pas le test.
	 */
	private function findRow(string $html, Course $eCourse): string {

		foreach(explode('<div class="util-table-row">', $html) as $row) {

			if(str_contains($row, 'href="'.CourseUi::urlEdition($eCourse).'"')) {
				return $row;
			}

		}

		throw new \Exception('la formation '.$eCourse['id'].' n\'est pas dans la liste');

	}

	private function getEditionData(Course $eCourse): \stdClass {

		$data = $this->executeView('formation/'.$eCourse['id'].'/edition', []);

		if(is_string($data)) {
			throw new \Exception('l\'éditeur de la formation '.$eCourse['id'].' a levé '.$data);
		}

		return $data;

	}

	private function getEditionHead(Course $eCourse): string {
		return new CourseUi()->getEditionHead($this->getEditionData($eCourse)->eCourse);
	}

}
?>
