<?php
namespace learning;

/**
 * L'éditeur d'une formation en onglets (chantier edition-formation, R1) : l'organisation (les dates),
 * les leçons, la fiche technique et les encadrants (retours de Vincent du 30/09, points 5 et 6).
 * L'organisation n'existe que pour une formation à dates, qui s'ouvre dessus ; les autres s'ouvrent
 * sur les leçons. La fiche technique se lit ici, champ par champ, et se modifie par son propre
 * panneau ; chaque encadrant ouvre le sien.
 *
 * Les formations et leur groupe sont construits en mémoire : aucune lecture ni écriture en base.
 */
class CourseEditionTabsTest extends \Test {

	/**
	 * Une formation de réseau à dates a quatre onglets, l'organisation sélectionnée par défaut, et le
	 * défaut comme l'onglet demandé passent à `Lime.Tab.restore()`.
	 */
	public function testDated(): void {

		$eCourse = $this->getCourseInMemory($this->getNetworkInMemory(), Course::ONSITE);

		$edition = new CourseUi()->getEdition($eCourse, new \Collection(), new \Collection());

		$this->assertEquals(['dates', 'details', 'lessons', 'staff'], $this->getTabs($edition), 'quatre onglets');
		$this->assertTrue(str_contains($edition, 'onrender="'.encode('Lime.Tab.restore(this, "dates", "")').'"'), 'l\'organisation par défaut, aucun onglet demandé : '.$edition);
		$this->assertTrue(str_contains($edition, '<a class="tab-item selected" data-tab="dates"'), 'l\'organisation sélectionnée côté serveur');
		$this->assertTrue(str_contains($edition, '<div class="tab-panel selected" data-tab="dates">'), 'et son panneau');

		$edition = new CourseUi()->getEdition($eCourse, new \Collection(), new \Collection(), new \Collection(), 'staff');

		$this->assertTrue(str_contains($edition, 'onrender="'.encode('Lime.Tab.restore(this, "dates", "staff")').'"'), 'l\'onglet demandé en troisième argument');

	}

	/**
	 * Une formation en ligne, de réseau, de groupe ou de l'équipe, n'a pas d'organisation : trois
	 * onglets, les leçons par défaut.
	 */
	public function testOnline(): void {

		$courses = [
			'réseau' => $this->getCourseInMemory($this->getNetworkInMemory(), Course::ONLINE),
			'groupe' => $this->getCourseInMemory($this->getEducationInMemory(), Course::ONLINE),
			'équipe' => $this->getCourseInMemory(new \structure\Structure(), Course::ONLINE),
		];

		foreach($courses as $who => $eCourse) {

			$edition = new CourseUi()->getEdition($eCourse, new \Collection());

			$this->assertEquals(['details', 'lessons', 'staff'], $this->getTabs($edition), $who.', trois onglets');
			$this->assertTrue(str_contains($edition, 'onrender="'.encode('Lime.Tab.restore(this, "lessons", "")').'"'), $who.', les leçons par défaut');
			$this->assertTrue(str_contains($edition, '<a class="tab-item selected" data-tab="lessons"'), $who.', sélectionnées côté serveur');
			$this->assertFalse(str_contains($edition, 'data-tab="dates"'), $who.', aucune organisation');

		}

	}

	/**
	 * L'onglet quitté se retient par formation (retour de Vincent du 01/10, remarque 23) :
	 * `Lime.Tab` range son choix sous l'identifiant de la rangée, qui porte donc celui de la formation.
	 */
	public function testTabsKeptByCourse(): void {

		$eCourseOnline = $this->getCourseInMemory($this->getNetworkInMemory(), Course::ONLINE);
		$eCourseOnline['id'] = 41;

		$eCourseDated = $this->getCourseInMemory($this->getNetworkInMemory(), Course::ONSITE);
		$eCourseDated['id'] = 42;

		foreach([$eCourseOnline, $eCourseDated] as $eCourse) {

			$edition = new CourseUi()->getEdition($eCourse, new \Collection(), new \Collection());

			$found = preg_match_all('/<div class="tabs [^"]*" id="([^"]*)"/', $edition, $matches);

			$this->assertEquals(1, $found, 'formation '.$eCourse['id'].' : la rangée d\'onglets de l\'éditeur porte un identifiant, une seule fois : '.$edition);

			if($found === 1) {
				$this->assertEquals('learning-course-tabs-'.$eCourse['id'], $matches[1][0], 'et il nomme la formation');
			}

		}

	}

	/**
	 * L'onglet « Fiche technique » : un seul geste pour la modifier, qui ouvre son panneau. Une
	 * formation en ligne de réseau y ajoute son prix et son financement, avec leur propre geste.
	 */
	public function testDetailsEmpty(): void {

		$eCourse = $this->getCourseInMemory($this->getNetworkInMemory(), Course::ONLINE);

		$details = $this->getPanel(new CourseUi()->getEdition($eCourse, new \Collection()), 'details');

		$this->assertEquals(1, substr_count($details, 'course:updatePrice?id=1'), 'le prix avec son geste : '.$details);
		$this->assertEquals(1, substr_count($details, 'href="/learning/course:updateDetails?id=1"'), 'un seul geste');

	}

	/**
	 * Un champ rempli se lit comme sur la fiche : l'éditeur rend les enjeux et les objectifs, le reste
	 * est du texte encodé.
	 */
	public function testDetailsFilled(): void {

		$eCourse = $this->getCourseInMemory($this->getNetworkInMemory(), Course::ONLINE);
		$eCourse['audience'] = 'Maraîchers <b>installés</b>';

		$details = $this->getPanel(new CourseUi()->getEdition($eCourse, new \Collection()), 'details');

		$this->assertTrue(str_contains($details, 'Maraîchers &lt;b&gt;installés&lt;/b&gt;'), 'le texte encodé');

	}

	/**
	 * L'onglet des encadrants, sans encadrant : l'ajout, et un état vide qui appelle un geste,
	 * « M'ajouter comme encadrant » (`StaffSelfTest`).
	 */
	public function testStaffEmpty(): void {

		$eCourse = $this->getCourseInMemory($this->getNetworkInMemory(), Course::ONSITE);

		$staff = $this->getPanel(new CourseUi()->getEdition($eCourse, new \Collection(), new \Collection(), new \Collection()), 'staff');

		$this->assertTrue(str_contains($staff, 'href="/learning/staff:create?course=1"'), 'l\'ajout : '.$staff);
		$this->assertTrue(str_contains($staff, 'href="/learning/staff:create?course=1&origin=self"'), 'l\'état vide, et son geste');

	}

	/**
	 * Avec des encadrants : un lien par encadrant vers son panneau (modifier et retirer), et son nom
	 * encodé.
	 */
	public function testStaff(): void {

		$eCourse = $this->getCourseInMemory($this->getNetworkInMemory(), Course::ONLINE);

		$cStaff = new \Collection([
			11 => new Staff(['id' => 11, 'supervisor' => new Supervisor(['id' => 21, 'name' => 'Ada <b>Martin</b>', 'description' => NULL, 'handicap' => FALSE, 'status' => Supervisor::ACTIVE]), 'role' => 'formatrice', 'contact' => TRUE]),
			12 => new Staff(['id' => 12, 'supervisor' => new Supervisor(['id' => 22, 'name' => 'Bruno Lefèvre', 'description' => 'Maraîcher dans la Drôme.', 'handicap' => TRUE, 'status' => Supervisor::INACTIVE]), 'role' => 'maraîcher formateur', 'contact' => FALSE]),
		]);

		$staff = $this->getPanel(new CourseUi()->getEdition($eCourse, new \Collection(), new \Collection(), $cStaff), 'staff');

		$this->assertTrue(str_contains($staff, 'href="/learning/staff:update?id=11"'), 'le panneau du premier : '.$staff);
		$this->assertTrue(str_contains($staff, 'href="/learning/staff:update?id=12"'), 'et celui du second');
		$this->assertTrue(str_contains($staff, 'Ada &lt;b&gt;Martin&lt;/b&gt;'), 'le nom encodé');

	}

	/**
	 * Les onglets d'un éditeur : leurs `data-tab`, par ordre alphabétique.
	 */
	private function getTabs(string $html): array {

		preg_match_all('/<a class="tab-item[^"]*" data-tab="([a-z]+)"/', $html, $matches);

		sort($matches[1]);

		return $matches[1];

	}

	/**
	 * Le contenu d'un panneau d'onglet, jusqu'au panneau suivant. `data-tab="…">` ne se lit que sur
	 * un panneau : l'onglet porte son `onclick` après l'attribut.
	 */
	private function getPanel(string $html, string $tab): string {

		$panel = substr($html, strpos($html, 'data-tab="'.$tab.'">'));
		$next = strpos($panel, '<div class="tab-panel');

		return ($next === FALSE) ? $panel : substr($panel, 0, $next);

	}

	private function getNetworkInMemory(): \structure\Structure {

		return new \structure\Structure([
			'id' => 1,
			'name' => 'Réseau de test',
			'type' => \structure\Structure::COMMUNITY,
			'communityType' => \structure\Structure::NETWORK,
			'legalEmail' => 'contact@reseau.example.org',
		]);

	}

	private function getEducationInMemory(): \structure\Structure {

		return new \structure\Structure([
			'id' => 2,
			'name' => 'Groupe de test',
			'type' => \structure\Structure::COMMUNITY,
			'communityType' => \structure\Structure::EDUCATION,
			'legalEmail' => 'contact@groupe.example.org',
		]);

	}

	private function getCourseInMemory(\structure\Structure $eStructure, string $type): Course {

		return new Course([
			'id' => 1,
			'community' => $eStructure,
			'type' => $type,
			'theme' => new Theme(),
			'lessons' => 0,
			'minutes' => NULL,
			'externalOnly' => FALSE,
			'externalUrl' => NULL,
			'priceFunded' => NULL,
			'price' => NULL,
			'funding' => NULL,
			'fundingNote' => NULL,
			'description' => NULL,
			'objectives' => NULL,
			'audience' => NULL,
			'prerequisites' => NULL,
			'access' => NULL,
			'accessibility' => NULL,
			'recognition' => NULL,
			'eSupervisorContact' => new Supervisor(),
		]);

	}

}
?>
