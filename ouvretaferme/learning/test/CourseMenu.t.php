<?php
namespace learning;

require_once __DIR__.'/LearningNetworkFixture.t.php';

/**
 * La roue d'une formation (lot menus-formation, retour de Vincent du 29/09 sur la formation 4 du
 * réseau 6659 : « le terme est modifier non ? Et puis les menus sont différents »). Elle était
 * écrite trois fois, avec trois ordres, deux libellés pour le même geste et deux classes de bouton.
 * Elle n'a plus qu'une source, `CourseUi::getActions()`, et ce fichier le vérifie par le rendu : la
 * roue lue dans chaque tableau qui liste des formations (l'équipe, un réseau, un groupe) est
 * identique, octet pour octet, à celle de l'éditeur de la même formation. Une roue réécrite à la
 * main dans une liste divergera tôt ou tard, et c'est ici qu'elle échouera.
 *
 * Chaque écran est préparé par sa page (`executeView()`) puis rendu par l'UI, pour lire ce que la
 * page pose et non un élément monté à la main.
 *
 * Lancement : dev/test package=learning file=CourseMenu
 */
class CourseMenuTest extends LearningNetworkFixtureTest {

	/**
	 * Formation de réseau publiée et partagée : toutes les entrées conditionnelles sont présentes,
	 * et la roue du tableau du réseau est celle de l'éditeur.
	 */
	public function testNetwork(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seed();
		$this->assertEquals([], $this->shareNetwork($eCourse), 'l\'ouverture aux membres');

		$edition = $this->getEditionMenu($eCourse);

		$this->assertEquals([TRUE, TRUE, TRUE], $this->getConditionalItems($edition, $eCourse), 'le catalogue, le suivi et la dépublication : '.$edition);
		$this->assertTrue(str_contains($edition, '<a href="/learning/course:updateSettings?id='.$eCourse['id'].'"'), '« Modifier la formation » ouvre le panneau de la formation');

		$this->assertEquals($edition, $this->getListMenu($this->eStructureNetwork, $eCourse), 'le tableau du réseau porte la roue de l\'éditeur');

	}

	/**
	 * Formation de groupe en brouillon, ouverte à personne : ni catalogue, ni suivi, ni dépublication.
	 * Sa ligne mène à la fiche par le visuel et par la roue.
	 */
	public function testEducation(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCommunity = $this->getCommunity(self::COMMUNITY_EDUCATION);
		$eCourse = $this->createCourse($eCommunity, Course::DRAFT, Course::PRIVATE);

		$edition = $this->getEditionMenu($eCourse);

		$this->assertEquals([FALSE, FALSE, FALSE], $this->getConditionalItems($edition, $eCourse), 'ni catalogue, ni suivi, ni dépublication : '.$edition);

		$this->assertEquals($edition, $this->getListMenu($eCommunity, $eCourse), 'la liste du groupe porte la roue de l\'éditeur');

		$row = $this->getListRow($eCommunity, $eCourse);
		$this->assertEquals(2, substr_count($row, 'href="'.CourseUi::url($eCourse).'"'), 'la fiche, par le visuel et par la roue : '.$row);
		$this->assertTrue(CourseUi::getCoverPlay($eCourse) !== '', 'le visuel de la ligne se rend');

		$fiche = $this->executeView('formation/'.$eCourse['id'], []);
		$this->assertTrue($fiche instanceof \stdClass, 'le bouton mène à une fiche qui s\'ouvre, brouillon compris : '.(is_string($fiche) ? $fiche : ''));

	}

	/**
	 * Formation de l'équipe Ouvretaferme, dans son catalogue d'administration.
	 */
	public function testTeam(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::ACCEPTED);

		$edition = $this->getEditionMenu($eCourse);

		$this->assertEquals([FALSE, FALSE, TRUE], $this->getConditionalItems($edition, $eCourse), 'la dépublication seule : '.$edition);

		$data = $this->executeView('learning/admin/course', []);
		$this->assertTrue($data instanceof \stdClass, 'le catalogue de l\'équipe s\'ouvre : '.(is_string($data) ? $data : ''));

		$list = new CourseUi()->getTeamList($data->cThemeCourse, $data->cCourseUnclassified);

		$this->assertEquals($edition, $this->findMenu($this->findRow($list, $eCourse), $eCourse), 'le catalogue de l\'équipe porte la roue de l\'éditeur');

		// Le visuel mène à la fiche, publiée comme en brouillon : l'équipe relit ce qu'elle écrit.
		$eCourseDraft = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE);

		$data = $this->executeView('learning/admin/course', []);
		$list = new CourseUi()->getTeamList($data->cThemeCourse, $data->cCourseUnclassified);

		foreach(['publiée' => $eCourse, 'en brouillon' => $eCourseDraft] as $case => $eCourseListed) {

			$row = $this->findRow($list, $eCourseListed);

			$this->assertTrue(str_contains($row, 'href="'.CourseUi::url($eCourseListed).'"'), 'formation de l\'équipe '.$case.' : la ligne mène à la fiche : '.$row);

			$fiche = $this->executeView('formation/'.$eCourseListed['id'], []);
			$this->assertTrue($fiche instanceof \stdClass, 'formation de l\'équipe '.$case.' : la fiche s\'ouvre : '.(is_string($fiche) ? $fiche : ''));

		}

	}

	/**
	 * Les panneaux de la formation et de la leçon se préparent, la roue de la leçon ouvre son panneau
	 * sans menu, et le module se modifie, nom et résumé.
	 */
	public function testWording(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), Course::DRAFT, Course::PRIVATE);
		$eLesson = $this->createLesson($this->createChapter($eCourse, 'zzTest module du menu'), 'zzTest leçon du menu', 10);

		$data = $this->executeView('learning/course:updateSettings', ['id' => $eCourse['id']]);
		$this->assertTrue($data instanceof \stdClass, 'le panneau de la formation se prépare : '.(is_string($data) ? $data : ''));

		$data = $this->executeView('learning/lesson:updateSettings', ['id' => $eLesson['id']]);
		$this->assertTrue($data instanceof \stdClass, 'le panneau de la leçon se prépare : '.(is_string($data) ? $data : ''));

		$this->assertTrue(str_contains(new LessonUi()->getEditionActions($eLesson), 'href="/learning/lesson:updateSettings?id='.$eLesson['id'].'"'), 'la roue de la leçon ouvre son panneau');

		$this->assertEquals(s("Le module a bien été modifié."), AlertUi::getSuccess('Chapter::updated'), 'le module se modifie, nom et résumé, il ne se renomme plus seulement');

	}

	/**
	 * La roue d'une ligne de leçon, dans le programme de l'éditeur (second tour du lot) : « Modifier »
	 * y ouvrait l'éditeur de la leçon, la même adresse que le nom de la ligne, quand toutes les
	 * autres roues du package ouvrent un panneau. Elle ouvre maintenant le panneau de la leçon, et le
	 * nom de la ligne reste le chemin vers l'éditeur.
	 */
	public function testLessonRow(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), Course::DRAFT, Course::PRIVATE);
		$eLesson = $this->createLesson($this->createChapter($eCourse, 'zzTest module de la roue'), 'zzTest leçon de la roue', 10);

		$data = $this->executeView('formation/'.$eCourse['id'].'/edition', []);
		$this->assertTrue($data instanceof \stdClass, 'l\'éditeur de la formation s\'ouvre : '.(is_string($data) ? $data : ''));

		$edition = new CourseUi()->getEdition($data->eCourse, $data->cChapter, $data->cDate);

		$this->assertTrue(str_contains($edition, 'href="/learning/lesson:updateSettings?id='.$eLesson['id'].'"'), '« Modifier la leçon » ouvre le panneau de la leçon : '.$edition);
		$this->assertTrue(str_contains($edition, 'href="'.LessonUi::urlEdition($eLesson).'"'), 'le nom de la ligne mène toujours à l\'éditeur de la leçon');

	}

	/** La roue de l'éditeur, seule, sans « Publier » ni « Partager » posés devant elle. */
	private function getEditionMenu(Course $eCourse): string {

		$data = $this->executeView('formation/'.$eCourse['id'].'/edition', []);

		if(is_string($data)) {
			throw new \Exception('l\'éditeur de la formation '.$eCourse['id'].' a levé '.$data);
		}

		return $this->findMenu(new CourseUi()->getEditionHead($data->eCourse), $eCourse);

	}

	private function getListMenu(\structure\Structure $eCommunity, Course $eCourse): string {
		return $this->findMenu($this->getListRow($eCommunity, $eCourse), $eCourse);
	}

	private function getListRow(\structure\Structure $eCommunity, Course $eCourse): string {

		$data = $this->executeView('groupe/'.$eCommunity['id'].'/formations', []);

		if(is_string($data)) {
			throw new \Exception('la liste des formations du groupe '.$eCommunity['id'].' a levé '.$data);
		}

		$list = new CourseUi()->getCommunityList($data->eStructure, $data->cCourse, $data->types, $data->type, $data->withRequested);

		return $this->findRow($list, $eCourse);

	}

	/** La ligne de la formation dans le tableau, repérée par le lien de son nom vers l'éditeur. */
	private function findRow(string $html, Course $eCourse): string {

		foreach(explode('<div class="util-table-row">', $html) as $row) {

			if(str_contains($row, 'href="'.CourseUi::urlEdition($eCourse).'"><b>')) {
				return $row;
			}

		}

		throw new \Exception('la formation '.$eCourse['id'].' n\'est pas dans la liste');

	}

	/**
	 * La roue de la formation dans un fragment : du bouton, quels que soient sa classe et l'ordre de
	 * ses attributs, jusqu'à la fin de sa liste, que ferme toujours « Supprimer ». Rien n'est
	 * normalisé, pour qu'une classe, un libellé ou un attribut qui diverge fasse échouer la
	 * comparaison.
	 */
	private function findMenu(string $html, Course $eCourse): string {

		$pattern = '/<a [^>]*class="[^"]*dropdown-toggle"[^>]*>'.preg_quote(\Asset::icon('gear-fill'), '/').'<\/a><div class="dropdown-list"><div class="dropdown-title">'.preg_quote(encode($eCourse['name']), '/').'<\/div>.*?data-ajax="\/learning\/course:doDelete" post-id="'.$eCourse['id'].'"[^>]*>[^<]*<\/a><\/div>/s';

		if(preg_match($pattern, $html, $match) !== 1) {
			throw new \Exception('aucune roue de la formation '.$eCourse['id'].' dans '.$html);
		}

		return $match[0];

	}

	/**
	 * Les trois entrées conditionnelles de la roue, présentes ou non : le catalogue, le suivi des
	 * stagiaires et la dépublication.
	 *
	 * @return bool[]
	 */
	private function getConditionalItems(string $menu, Course $eCourse): array {

		return [
			str_contains($menu, 'href="'.CatalogUi::urlCourse($eCourse).'"'),
			str_contains($menu, 'href="'.ProgressUi::urlTrack($eCourse).'"'),
			str_contains($menu, 'href="/learning/course:unpublish?id='.$eCourse['id'].'"'),
		];

	}

}
?>
