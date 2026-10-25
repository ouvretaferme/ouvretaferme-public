<?php
namespace learning;

/**
 * La tête de la fiche et de l'éditeur, redessinée d'ensemble au lot L10t-tête, après le retour de
 * Vincent sur les « empilements successifs ». Ce qui se vérifie ici est ce que chaque lot précédent
 * avait posé à la suite, et qui a désormais une seule place : le domaine une seule fois, l'auteur
 * sur la couverture, l'échéance dans la bande de progression.
 *
 * Les formations et leur réseau sont construits en mémoire : aucune lecture ni écriture en base.
 */
class CourseHeadTest extends \Test {

	/**
	 * La ligne sous le titre de la fiche : le domaine sous une vignette seulement, ni le type ni
	 * l'auteur, qui sont sur la couverture, et rien quand elle n'a rien à dire.
	 */
	public function testFicheContext(): void {

		$eStructureNetwork = $this->getNetworkInMemory();
		$network = encode($eStructureNetwork['name']);

		$context = CourseUi::getFicheContext($this->getCourseInMemory($eStructureNetwork, Course::ONSITE));

		$this->assertFalse(str_contains($context, 'Maraîchage'), 'sans le domaine que la couverture écrit');
		$this->assertFalse(str_contains($context, $network), 'ni l\'auteur qu\'elle porte');

		$context = CourseUi::getFicheContext($this->getCourseInMemory($eStructureNetwork, Course::ONSITE, vignette: 'vignette'));

		$this->assertTrue(str_contains($context, 'Maraîchage'), 'sous une vignette, le domaine revient');

		$context = CourseUi::getFicheContext($this->getCourseInMemory($eStructureNetwork, Course::REMOTE, copied: TRUE));

		$this->assertFalse(str_contains($context, $network), 'une copie, sans l\'auteur, posé sur la couverture');

		$context = CourseUi::getFicheContext($this->getCourseInMemory(new \structure\Structure(), Course::ONLINE, level: FALSE, lessons: 0));

		$this->assertEquals('', $context, 'rien à dire, aucune ligne vide');

	}

	/**
	 * Le type et l'auteur sur la couverture de la carte et de la fiche (retour de Vincent du 03/10) :
	 * le type, que seul un réseau distingue, puis l'auteur.
	 */
	public function testCardBadges(): void {

		$eStructureNetwork = $this->getNetworkInMemory();
		$eCourseNetwork = $this->getCourseInMemory($eStructureNetwork, Course::ONSITE);

		$badges = CourseUi::getCardBadges($eCourseNetwork);
		$typeMark = CourseUi::getTypeMark(Course::ONSITE, solid: TRUE);

		$this->assertTrue(str_contains($badges, $typeMark) and str_contains($badges, encode($eStructureNetwork['name'])), 'le type et le réseau qui la propose');

		$this->assertFalse(str_contains(CourseUi::getCardBadges($this->getCourseInMemory(new \structure\Structure(), Course::ONLINE)), CourseUi::getTypeMark(Course::ONLINE, solid: TRUE)), 'une formation de l\'équipe, sans type');
		$this->assertFalse(str_contains(CourseUi::getCardBadges($eCourseNetwork, withAuthor: FALSE), encode($eStructureNetwork['name'])), 'sans auteur, le type seul');
		$this->assertEquals('', CourseUi::getCardBadges($this->getCourseInMemory(new \structure\Structure(), Course::ONLINE), withAuthor: FALSE), 'ni type ni auteur, rien');

		$this->assertTrue(CourseUi::getCover($eCourseNetwork, badges: $badges) !== '', 'la couverture, avec ses marques');

	}

	/**
	 * La progression et l'échéance dans une seule bande. Suivie en entier, la formation n'a plus
	 * d'échéance à tenir.
	 */
	public function testBandDue(): void {

		$eCourse = $this->getCourseInMemory(new \structure\Structure(), Course::ONLINE);
		$eCourse['done'] = 2;

		$band = new ProgressUi()->getBand($eCourse, 'À suivre avant le 12/10/2026');

		$this->assertTrue(str_contains($band, 'À suivre avant le 12/10/2026'), 'l\'échéance à la suite de la progression');

		$eCourse['done'] = 0;

		$this->assertTrue(str_contains(new ProgressUi()->getBand($eCourse, 'À suivre avant le 12/10/2026'), 'À suivre avant le 12/10/2026'), 'pas encore commencée, l\'échéance se lit déjà');

		$eCourse['done'] = 5;

		$band = new ProgressUi()->getBand($eCourse, 'Échéance passée le 01/09/2026');

		$this->assertFalse(str_contains($band, 'Échéance'), 'suivie en entier, sans échéance');

	}

	/**
	 * Une formation publiée sans leçon (décision du 04/10) : la carte d'un compte n'y dit ni « Pas
	 * commencée » ni de barre, et garde l'échéance de sa promotion quand elle en a une.
	 */
	public function testBandEmpty(): void {

		$eCourse = $this->getCourseInMemory(new \structure\Structure(), Course::ONLINE);
		$eCourse['lessons'] = 0;
		$eCourse['done'] = 0;

		$this->assertEquals('', new ProgressUi()->getBand($eCourse), 'sans échéance, pas de bande');

		$band = new ProgressUi()->getBand($eCourse, 'Échéance passée le 01/09/2026', duePast: TRUE);

		$this->assertEquals('Échéance passée le 01/09/2026', strip_tags($band), 'l\'échéance seule, sans progression');

	}

	/**
	 * La bande de la fiche selon l'inscription et la promotion. Figée sans avoir été commencée, elle
	 * ne montre plus de progression, seulement l'échéance passée.
	 */
	public function testFicheBand(): void {

		$eEnrollment = new Enrollment(['id' => 1]);

		$eCourse = $this->getCourseInMemory(new \structure\Structure(), Course::ONLINE);
		$eCourse['canProgress'] = TRUE;
		$eCourse['eShare'] = new Share();

		$this->assertEquals('', CourseUi::getFicheBand($eCourse, new Enrollment()), 'ni inscription ni promotion, aucune bande');
		$this->assertTrue(CourseUi::getFicheBand($eCourse, $eEnrollment) !== '', 'inscrit sans promotion, la progression seule');

		$eCourse['eShare'] = new Share(['id' => 1, 'opensAt' => date('Y-m-d', strtotime('-10 days')), 'dueAt' => date('Y-m-d', strtotime('+20 days'))]);

		$band = CourseUi::getFicheBand($eCourse, new Enrollment());

		$this->assertTrue(str_contains($band, ShareUi::getDue($eCourse['eShare'])), 'membre de la promotion, l\'échéance à venir : '.$band);

		$eCourse['canProgress'] = FALSE;
		$eCourse['eShare']['dueAt'] = date('Y-m-d', strtotime('-1 day'));

		$band = CourseUi::getFicheBand($eCourse, new Enrollment());

		$this->assertEquals(ShareUi::getDuePast($eCourse['eShare']), strip_tags($band), 'figée sans avoir été commencée, l\'échéance passée seule : '.$band);

		$eCourse['done'] = 2;

		$band = CourseUi::getFicheBand($eCourse, $eEnrollment);

		$this->assertTrue(str_contains($band, ShareUi::getDuePast($eCourse['eShare'])) and strip_tags($band) !== ShareUi::getDuePast($eCourse['eShare']), 'figée en cours, la progression reste lisible : '.$band);

	}

	/**
	 * À qui la formation est ouverte, sous le titre de l'éditeur (B11) : le nom de la seule
	 * promotion, le nombre au-delà, et la période seulement quand tous les partages ont la même ;
	 * « Ouverte à tous les membres » pour un réseau, sans période, et qui ouvre le panneau sous la
	 * condition du bouton. « Pas encore ouverte » là où le bouton d'ouverture est offert, et ouvrant
	 * le même panneau, rien sur un brouillon ni sur une
	 * formation de l'équipe.
	 */
	public function testEditionShared(): void {

		$eStructureEducation = new \structure\Structure([
			'id' => 2,
			'type' => \structure\Structure::COMMUNITY,
			'communityType' => \structure\Structure::EDUCATION,
		]);
		// Les formations allumées, que la structure qui porte cet id en base n'a peut-être pas
		$eStructureEducation->conf(\structure\Feature::class)['hasCommunityLearning'] = TRUE;

		$today = \Clock::date();
		$past = date('Y-m-d', strtotime('-10 days'));
		$yesterday = date('Y-m-d', strtotime('-1 day'));
		$soon = date('Y-m-d', strtotime('+5 days'));
		$later = date('Y-m-d', strtotime('+20 days'));

		$date = fn(string $value) => \util\DateUi::numeric($value, \util\DateUi::DATE);

		$section = fn(int $id, string $name, string $opensAt, ?string $dueAt) => new Share([
			'id' => $id,
			'audience' => Share::SECTION,
			'section' => new \structure\Section(['id' => $id, 'name' => $name]),
			'opensAt' => $opensAt,
			'dueAt' => $dueAt,
		]);

		$summary = function(\structure\Structure $eStructure, array $shares, string $status = Course::PUBLISHED, int $sections = 2, int $members = 2): string {

			$eCourse = $this->getCourseInMemory($eStructure, Course::ONLINE);
			$eCourse['name'] = 'Formation de test';
			$eCourse['status'] = $status;

			if($eStructure->isNetwork()) {
				$eCourse['members'] = $members;
			} else {
				$eCourse['sections'] = $sections;
			}

			$eCourse['cShare'] = new \Collection($shares);
			$eCourse['shares'] = count($shares);
			$eCourse['readers'] = 0;
			$eCourse['requested'] = 0;

			return new CourseUi()->getEditionContext($eCourse);

		};

		$context = $summary($eStructureEducation, [$section(1, 'Promotion A', $past, $later)]);
		$this->assertTrue(str_contains($context, 'Promotion A') and str_contains($context, $date($later)), 'une promotion, son nom et son échéance');
		$this->assertTrue(str_contains($summary($eStructureEducation, [$section(1, 'Promotion A', $past, $later), $section(2, 'Promotion B', $past, $later)]), $date($later)), 'deux promotions à la même période');
		$context = $summary($eStructureEducation, [$section(1, 'Promotion A', $past, $later), $section(2, 'Promotion B', $past, $soon)]);
		$this->assertFalse(str_contains($context, $date($later)) or str_contains($context, $date($soon)), 'deux promotions à des échéances différentes, sans période');
		$this->assertFalse(str_contains($summary($eStructureEducation, [$section(1, 'Promotion A', $today, NULL)]), $date($today)), 'ouverte sans échéance, sans période');
		$this->assertTrue(str_contains($summary($eStructureEducation, [$section(1, 'Promotion A', $soon, NULL)]), $date($soon)), 'une ouverture à venir');
		$context = $summary($eStructureEducation, [$section(1, 'Promotion A', $soon, $later)]);
		$this->assertTrue(str_contains($context, $date($soon)) and str_contains($context, $date($later)), 'une ouverture à venir, avec son échéance');
		$this->assertTrue(str_contains($summary($eStructureEducation, [$section(1, 'Promotion A', $past, $yesterday)]), $date($yesterday)), 'une échéance passée');
		$this->assertTrue(str_contains($summary($eStructureEducation, [$section(1, 'Promo <b>', $past, NULL)]), 'Promo &lt;b&gt;'), 'le nom de la promotion encodé');

		$this->assertTrue(str_contains($summary($eStructureEducation, []), '<a href="/learning/course:share?id=1">'), 'publiée, partageable et sans partage : un lien vers le panneau du partage');
		$this->assertFalse(str_contains($summary($eStructureEducation, [], sections: 0), 'course:share'), 'un groupe sans promotion ne peut pas partager : rien');
		$this->assertFalse(str_contains($summary($eStructureEducation, [$section(1, 'Promotion A', $past, NULL)], Course::DRAFT), 'Promotion A'), 'un brouillon garde ses partages, mais n\'est ouvert à personne');
		$this->assertFalse(str_contains($summary($eStructureEducation, [], Course::DRAFT), 'course:share'), 'un brouillon sans partage');

		$eStructureNetwork = $this->getNetworkInMemory();

		$eShareMembers = new Share(['id' => 1, 'audience' => Share::MEMBERS, 'opensAt' => $past, 'dueAt' => NULL]);

		$this->assertTrue(str_contains($summary($eStructureNetwork, [$eShareMembers]), '<a href="/learning/course:share?id=1">'), 'un réseau ouvert à ses membres : la mention mène au panneau, où l\'on referme');
		$this->assertFalse(str_contains($summary($eStructureNetwork, [$eShareMembers], members: 0), 'course:share'), 'ouverte, sans plus de membre hors de l\'équipe : l\'état, sans lien');
		$this->assertTrue(str_contains($summary($eStructureNetwork, []), '<a href="/learning/course:share?id=1">'), 'un réseau qui a des membres, sans ouverture : le lien vers le panneau');
		$this->assertFalse(str_contains($summary($eStructureNetwork, [], members: 0), 'course:share'), 'un réseau dont l\'équipe est seule membre : l\'état, sans lien, le panneau n\'ayant personne à proposer');

		$eCourseTeam = $this->getCourseInMemory(new \structure\Structure(), Course::ONLINE);
		$eCourseTeam['name'] = 'Formation de l\'équipe';
		$eCourseTeam['status'] = Course::PUBLISHED;
		$eCourseTeam['readers'] = 0;
		$eCourseTeam['requested'] = 0;

		$this->assertFalse(str_contains(new CourseUi()->getEditionContext($eCourseTeam), 'course:share'), 'une formation de l\'équipe, sans `cShare` : rien');

	}

	/**
	 * Le nombre de lecteurs, sous le titre de l'éditeur, compte ceux qui suivent la formation à leur
	 * rythme : une formation en présentiel ou en visio se tient à des dates et ne le dit pas, même
	 * quand le compte n'est pas nul.
	 */
	public function testEditionReadersDated(): void {

		$eStructureNetwork = $this->getNetworkInMemory();

		$context = function(string $type, int $readers) use($eStructureNetwork): string {

			$eCourse = $this->getCourseInMemory($eStructureNetwork, $type);
			$eCourse['name'] = 'Formation de test';
			$eCourse['status'] = Course::DRAFT;
			$eCourse['readers'] = $readers;
			$eCourse['requested'] = 0;
			$eCourse['shares'] = 0;

			return new CourseUi()->getEditionContext($eCourse);

		};

		$this->assertTrue($context(Course::ONLINE, 0) !== $context(Course::ONLINE, 3), 'en ligne, le nombre de lecteurs');

		foreach([Course::ONSITE, Course::REMOTE, Course::MIXED] as $type) {
			$this->assertEquals($context($type, 0), $context($type, 3), $type.', aucun nombre de lecteurs, même quand des comptes s\'y sont inscrits');
		}

	}

	/**
	 * L'éditeur d'une formation sans module (P3) : l'écran vide porte le geste « Ajouter un module ».
	 * Il se lit dans l'onglet « Leçons » : l'onglet « Encadrants », un autre panneau, a le sien tant
	 * que la formation n'a pas d'encadrant (`StaffSelfTest`).
	 */
	public function testEditionEmpty(): void {

		$eCourse = $this->getCourseInMemory(new \structure\Structure(), Course::ONLINE, lessons: 0);
		$eCourse['comments'] = 0;

		$edition = $this->getLessonsPanel(new CourseUi()->getEdition($eCourse, new \Collection()));

		$this->assertTrue(str_contains($edition, '<a href="/learning/chapter:create?course=1"'), 'le geste « Ajouter un module »');

	}

	/**
	 * Le panneau de l'onglet « Leçons », jusqu'au panneau suivant. `data-tab="…">` ne se lit que sur
	 * un panneau : l'onglet porte son `onclick` après l'attribut.
	 */
	private function getLessonsPanel(string $html): string {

		$panel = substr($html, strpos($html, 'data-tab="lessons">'));
		$next = strpos($panel, '<div class="tab-panel');

		return ($next === FALSE) ? $panel : substr($panel, 0, $next);

	}

	private function getNetworkInMemory(): \structure\Structure {

		$eStructure = new \structure\Structure([
			'id' => 1,
			'name' => 'Réseau de test',
			'type' => \structure\Structure::COMMUNITY,
			'communityType' => \structure\Structure::NETWORK,
		]);

		// Les formations allumées, que la structure qui porte cet id en base n'a peut-être pas
		$eStructure->conf(\structure\Feature::class)['hasCommunityLearning'] = TRUE;

		return $eStructure;

	}

	private function getCourseInMemory(\structure\Structure $eStructure, string $type, ?string $vignette = NULL, bool $copied = FALSE, bool $level = TRUE, int $lessons = 5, bool $theme = TRUE): Course {

		return new Course([
			'id' => 1,
			'community' => $eStructure,
			'type' => $type,
			'level' => $level ? new Level(['name' => 'Porteur de projet']) : new Level(),
			'theme' => $theme ? new Theme(['name' => 'Maraîchage', 'icon' => 'leaf', 'color' => Theme::COLORS['production']]) : new Theme(),
			'subtheme' => new Subtheme(),
			'vignette' => $vignette,
			'copiedFrom' => $copied ? new Course(['id' => 2]) : new Course(),
			'lessons' => $lessons,
			'minutes' => 60,
			'done' => 0,
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
