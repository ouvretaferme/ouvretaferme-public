<?php
namespace learning;

/**
 * Le tableau des formations d'un réseau (lot L10f) et les gestes de l'onglet, montés à droite de
 * son titre. Les formations et leur réseau sont construits en mémoire : aucune lecture ni écriture
 * en base.
 */
class CourseNetworkTableTest extends \Test {

	/**
	 * Les leçons et les lecteurs ne concernent qu'une formation en ligne : dans un réseau qui en a
	 * une, leurs deux colonnes restent ; dans un réseau qui n'a que des formations à dates, elles
	 * disparaissent, en tête comme en ligne.
	 */
	public function testLessonsAndReadersOnline(): void {

		$eStructureNetwork = $this->getNetworkInMemory();

		$eCourse = $this->getCourseInMemory($eStructureNetwork, Course::ONSITE, dateNext: '2026-10-02 09:00:00', dates: 1);

		$html = $this->getTable($eStructureNetwork, $eCourse, types: [Course::ONSITE => 1, Course::REMOTE => 0, Course::MIXED => 0, Course::ONLINE => 1]);
		$htmlDated = $this->getTable($eStructureNetwork, $eCourse);

		$this->assertEquals(count($this->getCellClasses($htmlDated, 'util-table-head')) + 2, count($this->getCellClasses($html, 'util-table-head')), 'un réseau qui a une formation en ligne garde les deux colonnes en tête');
		$this->assertEquals(count($this->getCellClasses($htmlDated, 'util-table-row')) + 2, count($this->getCellClasses($html, 'util-table-row')), 'et en ligne');

	}

	/**
	 * Sans « À confirmer », la colonne disparaît en tête comme en ligne.
	 */
	public function testWithoutRequested(): void {

		$eStructureNetwork = $this->getNetworkInMemory();

		$eCourse = $this->getCourseInMemory($eStructureNetwork, Course::ONSITE, dateNext: '2026-10-02 09:00:00', dates: 1);

		$html = $this->getTable($eStructureNetwork, $eCourse);
		$htmlWithout = $this->getTable($eStructureNetwork, $eCourse, withRequested: FALSE);

		$this->assertEquals(count($this->getCellClasses($html, 'util-table-head')) - 1, count($this->getCellClasses($htmlWithout, 'util-table-head')), 'la tête, sans la colonne');
		$this->assertEquals(count($this->getCellClasses($html, 'util-table-row')) - 1, count($this->getCellClasses($htmlWithout, 'util-table-row')), 'la ligne, sans la colonne');

	}

	/**
	 * La place au catalogue se lit sous le nom, par une marque qui mène à l'onglet du catalogue ; une
	 * formation privée ne se signale pas.
	 */
	public function testMarksUnderName(): void {

		$eStructureNetwork = $this->getNetworkInMemory();

		$eCourse = $this->getCourseInMemory($eStructureNetwork, Course::ONSITE, dateNext: '2026-10-02 09:00:00', dates: 1);

		$links = substr_count($this->getTable($eStructureNetwork, $eCourse), 'href="'.CatalogUi::urlCourse($eCourse).'"');

		$eCourse['status'] = Course::PUBLISHED;
		$eCourse['catalog'] = Course::ACCEPTED;
		$eCourse['catalogBadge'] = Course::ACCEPTED;
		$eCourse['cShare'] = $this->getSharesOpen();

		$html = $this->getTable($eStructureNetwork, $eCourse);

		$this->assertEquals($links + 1, substr_count($html, 'href="'.CatalogUi::urlCourse($eCourse).'"'), 'au catalogue, une marque de plus, qui mène à son onglet ; hors catalogue, aucune : '.$html);

	}

	/**
	 * L'ouverture aux membres, état attendu d'une formation publiée de réseau.
	 */
	private function getSharesOpen(): \Collection {

		return new \Collection([new Share(['id' => 1, 'audience' => Share::MEMBERS])]);

	}

	/**
	 * La colonne se décide sur les deux réglages du réseau, à une date et en ligne, et sur les demandes
	 * de la liste : un réglage ne vaut que pour les inscriptions suivantes, et un réseau passé sans confirmation garde les
	 * demandes déjà envoyées.
	 */
	public function testHasRequested(): void {

		$eStructureConfirm = $this->getNetworkInMemory(confirm: TRUE);
		$eStructureDirect = $this->getNetworkInMemory(confirm: FALSE);

		$cCourseNone = new \Collection([
			$this->getCourseInMemory($eStructureDirect, Course::ONSITE, requested: 0),
			$this->getCourseInMemory($eStructureDirect, Course::ONLINE),
		]);

		$cCourseOne = new \Collection([
			$this->getCourseInMemory($eStructureDirect, Course::ONSITE, requested: 0),
			$this->getCourseInMemory($eStructureDirect, Course::REMOTE, requested: 1),
		]);

		$this->assertFalse(DateEnrollmentLib::hasRequested($eStructureDirect, $cCourseNone), 'sans confirmation ni demande, la colonne est masquée');
		$this->assertTrue(DateEnrollmentLib::hasRequested($eStructureDirect, $cCourseOne), 'sans confirmation, une demande encore en attente la garde');
		$this->assertTrue(DateEnrollmentLib::hasRequested($eStructureConfirm, $cCourseNone), 'un réseau qui confirme la garde, même sans demande');
		$this->assertFalse(DateEnrollmentLib::hasRequested($eStructureDirect, new \Collection()), 'une liste vide, sans confirmation');

		$cCourseOnline = new \Collection([
			$this->getCourseInMemory($eStructureDirect, Course::ONSITE, requested: 0),
			$this->getCourseInMemory($eStructureDirect, Course::ONLINE, requested: 1),
		]);

		$this->assertTrue(DateEnrollmentLib::hasRequested($eStructureDirect, $cCourseOnline), 'sans confirmation, une demande en ligne encore en attente la garde');

		foreach([\structure\Community::FUNDING, \structure\Community::ALWAYS] as $onlineConfirm) {
			$this->assertTrue(DateEnrollmentLib::hasRequested($this->getNetworkInMemory(confirm: FALSE, onlineConfirm: $onlineConfirm), $cCourseNone), $onlineConfirm.' : un réseau qui confirme en ligne la garde, même sans demande ni confirmation des dates');
		}

	}

	/**
	 * Les gestes de la bande, pour les deux types de groupe : la création, et « Encadrants »
	 * pour toute l'équipe ; seul qui gère le réseau y a aussi les inscriptions (`CommunityFeature.t.php`).
	 */
	public function testActions(): void {

		$eStructureNetwork = $this->getNetworkInMemory();

		$html = CourseUi::getCommunityTitle($eStructureNetwork, 1, 0);

		$this->assertTrue(str_contains($html, 'href="/learning/course:create?community='.$eStructureNetwork['id'].'"'), 'la création, le type se choisissant dans le parcours');
		$this->assertTrue(str_contains($html, 'href="/groupe/'.$eStructureNetwork['id'].'/formations/encadrants"'), 'qui ne gère pas le réseau y trouve « Encadrants » : '.$html);
		$this->assertFalse(str_contains($html, 'updateEnrollment'), 'les inscriptions réservées à qui gère le réseau');

		$eStructureEducation = new \structure\Structure([
			'id' => 2,
			'type' => \structure\Structure::COMMUNITY,
			'communityType' => \structure\Structure::EDUCATION,
		]);
		// Les formations allumées, que la structure qui porte cet id en base n'a peut-être pas
		$eStructureEducation->conf(\structure\Feature::class)['hasCommunityLearning'] = TRUE;

		$html = CourseUi::getCommunityTitle($eStructureEducation, 1, 0);

		$this->assertTrue(str_contains($html, 'href="/learning/course:create?community=2"'), 'un groupe de formation a sa création');
		$this->assertTrue(str_contains($html, 'href="/groupe/2/formations/encadrants"'), 'et ses encadrants');

	}

	/**
	 * L'administration vide mène à la création. L'onglet vide d'un groupe n'a plus d'écran vide : il
	 * est le parcours de création (`CourseStart.t.php`).
	 */
	public function testEmpty(): void {

		$html = new CourseUi()->getTeamList(new \Collection(), new \Collection());

		$this->assertTrue(str_contains($html, 'href="/learning/course:create"'), 'l\'administration sans formation mène à la création');

	}

	/**
	 * Les classes des cellules d'une rangée, dans l'ordre.
	 */
	private function getCellClasses(string $html, string $row): array {

		// libxml ne connaît pas les balises SVG des icônes et le dirait en avertissement.
		$document = new \DOMDocument();
		$errors = libxml_use_internal_errors(TRUE);
		$document->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>');
		libxml_clear_errors();
		libxml_use_internal_errors($errors);

		$node = new \DOMXPath($document)->query('//div[@class="'.$row.'"]')->item(0);

		$classes = [];

		foreach($node->childNodes as $cell) {
			$classes[] = $cell->getAttribute('class');
		}

		return $classes;

	}

	/**
	 * Sans `$types`, le réseau n'a que la formation montrée : le compte par type, que la page pose sur
	 * toute la liste avant le filtre, se déduit d'elle.
	 */
	private function getTable(\structure\Structure $eStructure, Course $eCourse, bool $withRequested = TRUE, ?array $types = NULL): string {

		DateSheetLib::fillCountByCourse(new \Collection([$eCourse]));

		$types ??= [Course::ONSITE => 0, Course::REMOTE => 0, Course::MIXED => 0, Course::ONLINE => 0, $eCourse['type'] => 1];

		return new CourseUi()->getCommunityList($eStructure, new \Collection([$eCourse]), $types, NULL, $withRequested);

	}

	private function getNetworkInMemory(bool $confirm = TRUE, string $onlineConfirm = \structure\Community::IMMEDIATE): \structure\Structure {

		$eStructure = new \structure\Structure([
			'id' => 1,
			'name' => 'Réseau de test',
			'type' => \structure\Structure::COMMUNITY,
			'communityType' => \structure\Structure::NETWORK,
			'community' => new \structure\Community(['learningConfirm' => $confirm, 'learningOnlineConfirm' => $onlineConfirm]),
		]);

		// Les formations allumées, que la structure qui porte cet id en base n'a peut-être pas
		$eStructure->conf(\structure\Feature::class)['hasCommunityLearning'] = TRUE;

		return $eStructure;

	}

	private function getCourseInMemory(\structure\Structure $eStructure, string $type, ?string $dateNext = NULL, int $dates = 0, int $requested = 0): Course {

		$eCourse = new Course([
			'id' => 1,
			'name' => 'Réussir sa certification bio',
			'community' => $eStructure,
			'type' => $type,
			'status' => Course::DRAFT,
			'catalog' => Course::PRIVATE,
			'catalogBadge' => NULL,
			'level' => new Level(['name' => 'Porteur de projet']),
			'theme' => new Theme(['name' => 'Maraîchage', 'icon' => 'leaf', 'color' => Theme::COLORS['production']]),
			'subtheme' => new Subtheme(),
			'vignette' => NULL,
			'lessons' => 5,
			'readers' => 0,
			'shares' => 0,
			'sheets' => 0,
		]);

		if($eCourse->isDated()) {
			$eCourse['dateNext'] = $dateNext;
			$eCourse['dates'] = $dates;
		}

		$eCourse['requested'] = $requested;

		return $eCourse;

	}

}
?>
