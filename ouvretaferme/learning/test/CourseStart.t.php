<?php
namespace learning;

require_once __DIR__.'/LearningNetworkFixture.t.php';

/**
 * Le parcours de création d'une formation (L13), étape par étape, par les routes que postent ses
 * formulaires : le type (réseau seulement), la formation, que `doCreate` écrit en brouillon, puis la
 * première date d'une formation datée ou le premier module d'une formation en ligne.
 *
 * Le décor est celui de `LearningNetworkFixture.t.php` : le réseau G3 dont l'utilisateur 1 est
 * conseiller. S'y ajoute un groupe de formation neuf, que le test sème lui-même et retrouve par son
 * nom (`COMMUNITY_NEW_NAME`), l'utilisateur 1 pour propriétaire.
 */
class CourseStartTest extends LearningNetworkFixtureTest {

	const COMMUNITY_NEW_NAME = 'zzTest L13 groupe neuf';

	private \structure\Structure $eStructureNew;

	/** La raison du refus, quand le groupe neuf n'a pas pu se semer */
	private ?string $seedNewFailure = NULL;

	/**
	 * Le groupe neuf se sème hors transaction, comme dans `CommunityFixtureAi` : `StructureLib::create()`
	 * émet un `CREATE DATABASE`, qui validerait la transaction ouverte par `setUp()`.
	 */
	public function init(): void {

		parent::init();

		try {
			$this->eStructureNew = $this->seedNewCommunity();
		} catch(\Throwable $e) {
			$this->eStructureNew = new \structure\Structure();
			$this->seedNewFailure = $e->getMessage().' ('.$e->getFile().':'.$e->getLine().')';
		}

	}

	/**
	 * Réseau, formation en présentiel : l'étape formation écrit les cinq champs en brouillon et mène
	 * au parcours ; le type se change ensuite sans rien perdre, la présentation se reprend sans vider
	 * ce que l'étape ne montre pas, et la première date termine dans l'éditeur.
	 */
	public function testNetworkDated(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		[$action, $fail] = $this->executePage('learning/course:doCreate', [
			'community' => (string)self::COMMUNITY_NETWORK,
			'type' => Course::ONSITE,
			'name' => 'zzTest parcours présentiel',
			'summary' => 'Préparer le contrôle annuel.',
			'theme' => (string)$this->eTheme['id'],
			'level' => (string)$this->eLevel['id'],
		], redirect: $url);

		$eCourse = $this->getCourseByName('zzTest parcours présentiel');

		$this->assertEquals([\RedirectAction::class, NULL], [$action, $fail], 'la formation est écrite');
		$this->assertTrue($eCourse->notEmpty(), 'et se relit en base');
		$this->assertEquals('/formation/'.$eCourse['id'].'/demarrer', $url, 'le parcours reprend à l\'étape suivante');
		$this->assertEquals(
			[Course::DRAFT, Course::ONSITE, self::COMMUNITY_NETWORK, 'Préparer le contrôle annuel.', $this->eTheme['id'], $this->eLevel['id']],
			[$eCourse['status'], $eCourse['type'], $eCourse['community']['id'], $eCourse['summary'], $eCourse['theme']['id'], $eCourse['level']['id']],
			'en brouillon, avec son type, son groupe et ses quatre champs'
		);

		$data = $this->executeView('formation/'.$eCourse['id'].'/demarrer', []);
		$this->assertEquals('date', $data->step ?? $data, 'une formation datée s\'ouvre sur sa première date');

		$data = $this->executeView('formation/'.$eCourse['id'].'/demarrer', ['etape' => 'type']);
		$this->assertEquals('type', $data->step ?? $data, 'le nom de l\'étape type y ramène');

		[$action, $fail] = $this->executePage('learning/course:doUpdateStartType', ['id' => (string)$eCourse['id'], 'type' => Course::REMOTE], redirect: $url);

		$this->assertEquals([\RedirectAction::class, NULL], [$action, $fail], 'le type revu');
		$this->assertEquals('/formation/'.$eCourse['id'].'/demarrer?etape=formation', $url, 'mène à la présentation');
		$this->assertEquals(Course::REMOTE, $this->getCourse($eCourse['id'])['type'], 'la visio est écrite');

		$eCourse = $this->getCourse($eCourse['id']);
		$eCourse['objectives'] = 'Des objectifs écrits depuis l\'éditeur';
		CourseLib::update($eCourse, ['objectives']);

		[$action, $fail] = $this->executePage('learning/course:doUpdateStart', [
			'id' => (string)$eCourse['id'],
			'name' => 'zzTest parcours visio',
			'summary' => 'Préparer le contrôle annuel, en visio.',
			'theme' => (string)$this->eTheme['id'],
			'level' => (string)$this->eLevel['id'],
		], redirect: $url);

		$eCourse = $this->getCourse($eCourse['id']);

		$this->assertEquals([\RedirectAction::class, NULL, '/formation/'.$eCourse['id'].'/demarrer'], [$action, $fail, $url], 'la présentation reprise mène à l\'étape suivante');
		$this->assertEquals(['zzTest parcours visio', 'Préparer le contrôle annuel, en visio.'], [$eCourse['name'], $eCourse['summary']], 'le titre et le résumé écrits');
		$this->assertEquals('Des objectifs écrits depuis l\'éditeur', $eCourse['objectives'], 'ce que l\'étape ne montre pas reste');

		[$action, $fail] = $this->executePage('learning/date:doCreate', [
			'course' => (string)$eCourse['id'],
			'origin' => 'start',
			'link' => 'https://visio.example.org/salle',
			'seats' => '12',
		] + $this->postSequences([
			[$this->day(10), '14:00', '16:30', DateSequence::REMOTE],
		]), redirect: $url);

		$this->assertEquals([\RedirectAction::class, NULL], [$action, $fail], 'la première date posée depuis le parcours');
		$this->assertEquals(CourseUi::urlEdition($eCourse).'?tab=dates&success=learning\\Course::created', $url, 'termine dans l\'éditeur, sur l\'organisation');

		$cDate = Date::model()->select(['link', 'seats'])->whereCourse($eCourse)->getCollection();

		$this->assertEquals(1, $cDate->count(), 'la date est en base');
		$this->assertEquals(['https://visio.example.org/salle', 12], [$cDate->first()['link'], $cDate->first()['seats']], 'avec son lien et ses places');

		[$action, $fail] = $this->executePage('learning/date:doCreate', [
			'course' => (string)$eCourse['id'],
			'link' => 'https://visio.example.org/salle',
			'seats' => '',
		] + $this->postSequences([
			[$this->day(11), '14:00', '16:30', DateSequence::REMOTE],
		]));

		$this->assertEquals([\ReloadAction::class, NULL], [$action, $fail], 'sans origine, une date se pose comme depuis l\'éditeur');

		$this->assertEquals(
			[\FailAction::class, 'Course::type.dated'],
			$this->executePage('learning/course:doUpdateStartType', ['id' => (string)$eCourse['id'], 'type' => Course::ONSITE]),
			'une formation datée ne change plus de type par le parcours'
		);

	}

	/**
	 * Réseau, formation en ligne : le premier module et ses leçons d'un seul envoi. Les lignes vides
	 * sont écartées, les leçons prennent leurs positions dans l'ordre, sans durée, au format texte ;
	 * un envoi refusé n'écrit rien.
	 */
	public function testNetworkProgram(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE, type: Course::ONLINE);

		$data = $this->executeView('formation/'.$eCourse['id'].'/demarrer', []);
		$this->assertEquals('module', $data->step ?? $data, 'une formation en ligne s\'ouvre sur son premier module');

		$this->assertEquals(
			[\FailAction::class, 'Course::lessons.tooMany'],
			$this->executePage('learning/course:doCreateProgram', [
				'id' => (string)$eCourse['id'],
				'name' => 'Module trop long',
				'lessonName' => array_map(fn(int $i) => 'Leçon '.$i, range(1, Chapter::LESSONS_START_MAX + 1)),
			]),
			'une leçon de plus que la borne est refusée'
		);

		$this->assertEquals(
			[\FailAction::class, 'Chapter::name.check'],
			$this->executePage('learning/course:doCreateProgram', [
				'id' => (string)$eCourse['id'],
				'name' => '',
				'lessonName' => ['Une leçon'],
			]),
			'un module sans nom est refusé'
		);

		$this->assertEquals(
			[\FailAction::class, 'Lesson::name.check'],
			$this->executePage('learning/course:doCreateProgram', [
				'id' => (string)$eCourse['id'],
				'name' => 'Module',
				'lessonName' => [str_repeat('a', Lesson::model()->getPropertyRange('name')[1] + 1)],
			]),
			'un titre de leçon trop long est refusé'
		);

		$this->assertEquals(0, Chapter::model()->whereCourse($eCourse)->count(), 'aucun envoi refusé n\'a écrit de module');
		$this->assertEquals(0, Lesson::model()->whereCourse($eCourse)->count(), 'ni de leçon');

		[$action, $fail] = $this->executePage('learning/course:doCreateProgram', [
			'id' => (string)$eCourse['id'],
			'name' => 'Tenir son registre',
			'lessonName' => [0 => 'Pourquoi', 1 => '', 2 => '   ', 4 => 'Comment', 5 => ['forgée'], 6 => 'Quand'],
		], redirect: $url);

		$this->assertEquals([\RedirectAction::class, NULL], [$action, $fail], 'le module et ses leçons');
		$this->assertEquals(CourseUi::urlEdition($eCourse).'?tab=lessons&success=learning\\Course::created', $url, 'terminent dans l\'éditeur, sur les leçons');

		$cChapter = ChapterLib::getByCourse($this->getCourse($eCourse['id']));

		$this->assertEquals(1, $cChapter->count(), 'un module');
		$this->assertEquals('Tenir son registre', $cChapter->first()['name'], 'avec son nom');

		$cLesson = LessonLib::getByCourse($eCourse);

		$this->assertEquals(['Pourquoi', 'Comment', 'Quand'], array_values($cLesson->getColumn('name')), 'trois leçons, les lignes vide, faite d\'espaces ou forgée écartées, dans l\'ordre');
		$this->assertEquals([0, 1, 2], array_values($cLesson->getColumn('position')), 'positions sans trou');
		$this->assertEquals([NULL, NULL, NULL], $cLesson->makeArray(fn(Lesson $eLesson) => $eLesson['minutes']), 'sans durée');
		$this->assertEquals([FALSE, FALSE, FALSE], array_values($cLesson->getColumn('written')), 'vides, le parcours ne donnant que des titres');
		$this->assertEquals([$cChapter->first()['id']], array_values(array_unique(array_map(fn(Chapter $eChapter) => $eChapter['id'], $cLesson->getColumn('chapter')))), 'toutes dans le module');

		[$action, $fail] = $this->executePage('learning/course:doCreateProgram', [
			'id' => (string)$eCourse['id'],
			'name' => 'Sans leçon',
		]);

		$this->assertEquals([\RedirectAction::class, NULL], [$action, $fail], 'un module sans leçon, toutes les lignes retirées');
		$this->assertEquals(2, Chapter::model()->whereCourse($eCourse)->count(), 'est écrit seul');

		[$action, $fail] = $this->executePage('learning/course:doCreateProgram', [
			'id' => (string)$eCourse['id'],
			'name' => 'Leçons forgées',
			'lessonName' => 'Une chaîne au lieu d\'une liste',
		]);

		// `POST(…, 'array')` fait d'une chaîne une liste d'un élément.
		$this->assertEquals([\RedirectAction::class, NULL], [$action, $fail], 'une liste forgée en chaîne ne lève pas');
		$this->assertEquals(4, LessonLib::getByCourse($eCourse)->count(), 'et compte pour une ligne');

	}

	/**
	 * Une formation publiée atteint aussi l'adresse du premier module : ses leçons y naîtraient sans
	 * durée devant les lecteurs, ce que `Lesson::minutes.classified` refuse partout ailleurs.
	 */
	public function testPublishedProgram(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE, type: Course::ONLINE);

		$this->assertEquals(
			[\FailAction::class, 'Lesson::minutes.classified'],
			$this->executePage('learning/course:doCreateProgram', [
				'id' => (string)$eCourse['id'],
				'name' => 'Module',
				'lessonName' => ['Une leçon'],
			]),
			'une leçon sans durée sous une formation publiée'
		);

		$this->assertEquals(0, Chapter::model()->whereCourse($eCourse)->count(), 'rien n\'est écrit');

		[$action, $fail] = $this->executePage('learning/course:doCreateProgram', [
			'id' => (string)$eCourse['id'],
			'name' => 'Module sans leçon',
		]);

		$this->assertEquals([\RedirectAction::class, NULL], [$action, $fail], 'un module sans leçon reste possible');

	}

	/**
	 * L'étape type n'existe que dans un réseau, avant l'écriture comme après ; le type choisi mène à
	 * la formation, et le nom de l'étape y ramène en gardant la présélection.
	 */
	public function testSteps(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$data = $this->executeView('learning/course:create', ['community' => (string)self::COMMUNITY_NETWORK]);
		$this->assertEquals(['type', NULL], [$data->step ?? $data, (($data instanceof \stdClass) ? $data->e['type'] : $data)], 'un réseau commence par le type, aucun choisi');

		$this->assertEquals(
			[\FailAction::class, 'Course::type.missing'],
			$this->executePage('learning/course:create', ['community' => (string)self::COMMUNITY_NETWORK, 'etape' => 'formation'], 'GET'),
			'l\'étape envoyée sans type est refusée'
		);

		$data = $this->executeView('learning/course:create', ['community' => (string)self::COMMUNITY_NETWORK, 'etape' => 'formation', 'type' => Course::REMOTE]);
		$this->assertEquals(['formation', Course::REMOTE], [$data->step ?? $data, (($data instanceof \stdClass) ? $data->e['type'] : $data)], 'l\'étape envoyée avec son type mène à la formation');

		$data = $this->executeView('learning/course:create', ['community' => (string)self::COMMUNITY_NETWORK, 'type' => Course::REMOTE]);
		$this->assertEquals(['formation', Course::REMOTE], [$data->step ?? $data, (($data instanceof \stdClass) ? $data->e['type'] : $data)], 'le type choisi mène à la formation');

		$data = $this->executeView('learning/course:create', ['community' => (string)self::COMMUNITY_NETWORK, 'type' => Course::MIXED]);
		$this->assertEquals(['type', NULL], [$data->step ?? $data, (($data instanceof \stdClass) ? $data->e['type'] : $data)], 'mixte se calcule par les dates, il ne se choisit pas : le parcours reste sur le type');

		$data = $this->executeView('learning/course:create', ['community' => (string)self::COMMUNITY_NETWORK, 'type' => Course::REMOTE, 'etape' => 'type']);
		$this->assertEquals(['type', Course::REMOTE], [$data->step ?? $data, (($data instanceof \stdClass) ? $data->e['type'] : $data)], 'le retour au type garde la présélection');

		$data = $this->executeView('learning/course:create', ['community' => (string)self::COMMUNITY_EDUCATION, 'etape' => 'type']);
		$this->assertEquals(['formation', Course::ONLINE], [$data->step ?? $data, (($data instanceof \stdClass) ? $data->e['type'] : $data)], 'un groupe de formation n\'a pas d\'étape type');

		$data = $this->executeView('learning/course:create', []);
		$this->assertEquals('formation', $data->step ?? $data, 'l\'équipe crée sans groupe, sur la formation');
		$this->assertTrue($data->e->isTeam(), 'une formation de l\'équipe');

		$eCourse = $this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), Course::DRAFT, Course::PRIVATE);

		$data = $this->executeView('formation/'.$eCourse['id'].'/demarrer', ['etape' => 'type']);
		$this->assertEquals('module', $data->step ?? $data, 'hors réseau, l\'étape type demandée n\'existe pas');

		$this->assertEquals(['type', 'formation', 'date'], CourseStartUi::getSteps(CourseLib::getCreateElement($this->eStructureNetwork, Course::ONSITE)), 'réseau, présentiel');
		$this->assertEquals(['type', 'formation', 'module'], CourseStartUi::getSteps(CourseLib::getCreateElement($this->eStructureNetwork, Course::ONLINE)), 'réseau, en ligne');
		$this->assertEquals(['formation', 'module'], CourseStartUi::getSteps($eCourse), 'groupe de formation');

	}

	/**
	 * Ce que l'écran montre et que le serveur décide : les étapes faites se rouvrent par leur nom,
	 * le module prend le numéro qui suit, et le retour aux formations n'existe qu'avec une formation.
	 */
	public function testRender(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourseNew = CourseLib::getCreateElement($this->eStructureNetwork);
		$eCourseNew['type'] = NULL;
		$eCourseNew['cTheme'] = ThemeLib::getAll();
		$eCourseNew['cLevel'] = LevelLib::getAll();

		$html = new CourseStartUi()->getStart($eCourseNew, 'type', FALSE);

		$this->assertEquals(0, substr_count($html, 'data-get='), 'à la première étape, rien à rouvrir');
		$this->assertFalse(str_contains($html, 'href="'.CourseUi::urlList($eCourseNew).'"'), 'pas de retour aux formations sans formation');
		preg_match_all('/<input [^>]*name="etape"[^>]*>/', $html, $inputs);
		$this->assertEquals(['formation'], array_map(fn(string $input) => preg_match('/value="([^"]*)"/', $input, $value) ? $value[1] : NULL, $inputs[0]), 'l\'étape type dit qu\'elle a été soumise, un type non coché n\'étant pas envoyé');

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE, type: Course::ONLINE);
		$this->createChapter($eCourse, 'Un module déjà là');

		// Une formation écrite et classée rouvre l'étape formation avec les sous-thématiques de sa
		// thématique, que la page lit en base et non plus vides comme à la création.
		if($this->assertDecorPresent(SubthemeLib::getByTheme($this->eTheme)->notEmpty(), 'une thématique avec ses sous-thématiques, learning/configure/theme à jouer') === FALSE) {
			return;
		}

		$html = $this->renderRoute('formation/'.$eCourse['id'].'/demarrer', ['etape' => 'formation']);

		$this->assertEquals(1, preg_match_all('/<select [^>]*name="subtheme"/', $html), 'la formation classée propose les sous-thématiques de sa thématique');

		$eCourse = $this->getCourse($eCourse['id']);
		$eCourse['cTheme'] = ThemeLib::getAll();
		$eCourse['cLevel'] = LevelLib::getAll();
		CourseLib::fillCounts(new \Collection([$eCourse]));

		$html = new CourseStartUi()->getStart($eCourse, 'module', TRUE);

		$this->assertEquals(1, $eCourse['chapters'], 'un module déjà là : le suivant prend le numéro 2');
		$this->assertEquals(2, substr_count($html, 'data-get='), 'les deux étapes faites se rouvrent');
		$this->assertTrue(str_contains($html, '/formation/'.$eCourse['id'].'/demarrer?etape=type'), 'le type par son nom');
		$this->assertTrue(str_contains($html, 'href="'.CourseUi::urlList($eCourse).'"'), 'le retour aux formations');
		$this->assertFalse(str_contains($html, 'data-wrapper="lessons"'), 'le plafond des leçons ne met aucune ligne en faute : aucun wrapper ne l\'attend, il tombe dans le bandeau');
		$this->assertEquals(1, substr_count($html, 'href="/formation/'.$eCourse['id'].'/edition?tab=lessons&success=learning\\Course::created" data-ajax-navigation="never"'), 'le module passé charge l\'éditeur en entier, qui seul rend le message de succès');

		$html = new CourseStartUi()->getStart($eCourse, 'type', TRUE);

		$this->assertEquals(1, substr_count($html, 'data-get="/formation/'.$eCourse['id'].'/demarrer?etape=formation"'), 'revenu au type, la formation écrite reste faite et se rouvre');
		$this->assertFalse(str_contains($html, 'data-get="/formation/'.$eCourse['id'].'/demarrer?etape=type"'), 'le type est l\'étape courante, sans lien');

		// Une formation devient mixte dès sa première date en présentiel et en visio, et le parcours
		// y ramène encore : l'étape de la date lui demande un lieu, sa séance par défaut étant en présentiel.
		$eCourse['type'] = Course::MIXED;
		$html = new CourseStartUi()->getStart($eCourse, 'date', TRUE);

		$this->assertTrue(str_contains($html, 'data-ajax-form="/learning/date:doCreate"'), 'une formation mixte rouvre l\'étape de la date');

	}

	/**
	 * La troisième étape se passe par « Passer cette étape », un lien qui mène à la fin du parcours
	 * sans rien poster, et la suivre ne change rien en base : la formation reste le brouillon écrit à
	 * l'étape précédente, sans date ni module.
	 */
	public function testSkip(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$steps = [
			'date' => [Course::ONSITE, '/learning/date:doCreate'],
			'module' => [Course::ONLINE, '/learning/course:doCreateProgram'],
		];

		foreach($steps as $step => [$type, $action]) {

			$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE, type: $type);
			$eCourse['cTheme'] = ThemeLib::getAll();
			$eCourse['cSubtheme'] = SubthemeLib::getByTheme($eCourse['theme']);
			$eCourse['cLevel'] = LevelLib::getAll();
			CourseLib::fillCounts(new \Collection([$eCourse]));

			$html = new CourseStartUi()->getStart($eCourse, $step, TRUE);

			$this->assertTrue(str_contains($html, 'data-ajax-form="'.$action.'"'), $step.' : l\'étape est rendue');
			$this->assertTrue(str_contains($html, 'href="'.CourseUi::urlEdition($eCourse).'?tab=lessons&success=learning\\Course::created" data-ajax-navigation="never"'), $step.' : « Passer cette étape », un lien vers la fin du parcours qui ne poste rien');

			$before = [
				$this->getCourse($eCourse['id'])->extracts(['status', 'type', 'name', 'updatedAt']),
				Date::model()->whereCourse($eCourse)->count(),
				Chapter::model()->whereCourse($eCourse)->count(),
			];

			$data = $this->executeView('formation/'.$eCourse['id'].'/edition', ['success' => 'learning\\Course::created']);

			$this->assertTrue($data instanceof \stdClass, $step.' : le lien ouvre l\'éditeur ('.(is_string($data) ? $data : 'vue').')');
			$this->assertEquals(
				$before,
				[
					$this->getCourse($eCourse['id'])->extracts(['status', 'type', 'name', 'updatedAt']),
					Date::model()->whereCourse($eCourse)->count(),
					Chapter::model()->whereCourse($eCourse)->count(),
				],
				$step.' : passer l\'étape n\'écrit rien, la formation reste le brouillon sans date ni module'
			);

		}

	}

	/**
	 * Un groupe sans formation ouvre son onglet sur le parcours, à l'étape formation. Rien n'est
	 * écrit tant que l'étape n'est pas envoyée, et la formation qu'elle écrit est en ligne. La mise
	 * en route du groupe, qui reste à faire, ne prend pas l'onglet : elle n'y laisse qu'un rappel.
	 */
	public function testNewCommunity(): void {

		if($this->assertFixtures() === FALSE or $this->prepareNewCommunity() === FALSE) {
			return;
		}

		$community = $this->eStructureNew['id'];

		$this->actAs($this->eUserAdmin);

		$data = $this->executeView('groupe/'.$community.'/formations', []);

		$this->assertEquals('formation', $data->step ?? $data, 'l\'onglet s\'ouvre sur la formation');
		$this->assertTrue($data->eCourseStart->notEmpty(), 'sur une formation préparée');
		$this->assertTrue($data->eCourseStart->exists() === FALSE, 'et pas encore écrite');
		$this->assertEquals(0, CourseLib::countByCommunity($this->eStructureNew), 'l\'onglet n\'écrit rien');

		// L'onglet se rend en entier, et pas seulement sa donnée : l'étape formation lit chaque liste de
		// ses champs, sous-thématique comprise, et une liste oubliée par la page ne se voit qu'au rendu.
		$html = $this->renderRoute('groupe/'.$community.'/formations');

		$this->assertTrue(str_contains($html, 'data-ajax-form="/learning/course:doCreate"'), 'l\'onglet rend l\'étape formation');
		$this->assertEquals(1, substr_count($html, 'id="learning-course-subtheme-field"'), 'avec la place de la sous-thématique, vide tant qu\'aucune thématique n\'est choisie');

		[$action, $fail] = $this->executePage('learning/course:doCreate', [
			'community' => (string)$community,
			'name' => 'zzTest première formation',
			'summary' => '',
			'theme' => '',
			'level' => '',
		]);

		$this->assertEquals([\RedirectAction::class, NULL], [$action, $fail], 'la première formation, titre seul');
		$this->assertEquals(Course::ONLINE, $this->getCourseByName('zzTest première formation', $community)['type'], 'en ligne');

		$data = $this->executeView('groupe/'.$community.'/formations', []);
		$this->assertTrue($data->eCourseStart->empty(), 'avec une formation, l\'onglet revient à la liste');

		$html = $this->renderRoute('groupe/'.$community.'/formations');

		$this->assertFalse(str_contains($html, 'StructureCommunityStartAi.start'), 'la mise en route du groupe ne tient que l\'accueil, elle laisse l\'onglet à son parcours');
		$this->assertFalse(str_contains($html, 'href="'.\structure\StructureUi::urlCommunity($this->eStructureNew).'?demarrage='), 'ni aucun lien vers une de ses étapes');

		// Les deux états du réseau se posent dans la transaction, pour que chaque branche se joue
		// quel que soit ce que le réseau contient dans cette base.
		$this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE);

		$data = $this->executeView('groupe/'.self::COMMUNITY_NETWORK.'/formations', []);
		$this->assertTrue($data->eCourseStart->empty(), 'un réseau qui a des formations montre sa liste');

		foreach(CourseLib::getByCommunity($this->eStructureNetwork) as $eCourse) {
			CourseLib::delete($eCourse);
		}

		$data = $this->executeView('groupe/'.self::COMMUNITY_NETWORK.'/formations', []);
		$this->assertEquals('type', $data->step ?? $data, 'un réseau sans formation s\'ouvre sur le type');

		$html = $this->renderRoute('groupe/'.self::COMMUNITY_NETWORK.'/formations');

		$this->assertTrue(str_contains($html, 'data-ajax-form="/learning/course:create"'), 'l\'onglet du réseau rend l\'étape du type');

	}

	/**
	 * Qui ne peut pas créer n'ouvre ni n'envoie aucune étape.
	 */
	public function testRefused(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE, type: Course::ONSITE);
		$eCourseOnline = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE, type: Course::ONLINE);

		$this->assertEquals(
			[\FailAction::class, 'Course::type.network'],
			$this->executePage('learning/course:doCreate', ['community' => (string)self::COMMUNITY_EDUCATION, 'name' => 'zzTest visio d\'école', 'type' => Course::REMOTE]),
			'la visio hors réseau'
		);

		$this->assertEquals(
			\NotExpectedAction::class,
			$this->executePage('learning/date:doCreate', [
				'course' => (string)$eCourseOnline['id'],
				'origin' => 'start',
				'link' => 'https://visio.example.org/salle',
			] + $this->postSequences([
				[$this->day(10), '14:00', '16:30', DateSequence::REMOTE],
			]))[0],
			'une date sur une formation en ligne'
		);

		$this->assertTrue(
			in_array($this->executePage('learning/course:doCreate', ['community' => (string)$this->eStructureFarm['id'], 'name' => 'zzTest ferme'])[0], [\NotAllowedAction::class, \NotExpectedAction::class], TRUE),
			'une ferme ne crée pas de formation'
		);

		$this->actAs($this->eUserReader);

		$this->assertEquals(\NotAllowedAction::class, $this->executePage('learning/course:create', ['community' => (string)self::COMMUNITY_NETWORK], 'GET')[0], 'hors de l\'équipe, le parcours ne s\'ouvre pas');
		$this->assertEquals(\NotAllowedAction::class, $this->executePage('learning/course:doCreate', ['community' => (string)self::COMMUNITY_NETWORK, 'name' => 'zzTest intrus', 'type' => Course::ONSITE])[0], 'ni ne crée');
		$this->assertEquals(\NotAllowedAction::class, $this->executePage('formation/'.$eCourse['id'].'/demarrer', [], 'GET')[0], 'ni ne reprend une formation');
		$this->assertEquals(\NotAllowedAction::class, $this->executePage('learning/course:doUpdateStartType', ['id' => (string)$eCourse['id'], 'type' => Course::REMOTE])[0], 'ni n\'en change le type');
		$this->assertEquals(\NotAllowedAction::class, $this->executePage('learning/course:doUpdateStart', ['id' => (string)$eCourse['id'], 'name' => 'zzTest renommée'])[0], 'ni la présentation');
		$this->assertEquals(\NotAllowedAction::class, $this->executePage('learning/course:doCreateProgram', ['id' => (string)$eCourseOnline['id'], 'name' => 'Intrus'])[0], 'ni n\'y écrit de module');
		$this->assertEquals(\NotAllowedAction::class, $this->executePage('groupe/'.self::COMMUNITY_NETWORK.'/formations', [], 'GET')[0], 'ni n\'ouvre l\'onglet');

		$this->assertEquals(Course::ONSITE, $this->getCourse($eCourse['id'])['type'], 'rien n\'a changé');
		$this->assertEquals(0, Chapter::model()->whereCourse($eCourseOnline)->count(), 'rien n\'a été écrit');

	}

	/**
	 * Retrouvé par son nom, créé s'il manque : son id change à chaque base neuve.
	 */
	private function seedNewCommunity(): \structure\Structure {

		$eStructure = \structure\Structure::model()
			->select('id')
			->whereName(self::COMMUNITY_NEW_NAME)
			->get();

		if($eStructure->notEmpty()) {
			return $this->getCommunity($eStructure['id']);
		}

		$eStructure = new \structure\Structure(['type' => \structure\Structure::COMMUNITY, 'owner' => $this->eUserAdmin]);

		$fw = new \FailWatch();

		$eStructure->build(\structure\StructureLib::getPropertiesCreate()($eStructure), [
			'communityType' => \structure\Structure::EDUCATION,
			'name' => self::COMMUNITY_NEW_NAME,
			'isLegal' => 0,
			'legalEmail' => 'zztest+l13@ouvretaferme.org',
			// L'identifiant du pays et non son code : la colonne est un `?user\Country`.
			'legalCountry' => 75,
			'startedAt' => date('Y')
		], new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('création de « '.self::COMMUNITY_NEW_NAME.' » refusée : '.json_encode($fw->get())));

		\structure\StructureLib::create($eStructure);

		return $this->getCommunity($eStructure['id']);

	}

	/**
	 * Pose, dans la transaction du test, l'état que le cas du groupe neuf suppose : aucune formation
	 * et la mise en route ouverte. Un lancement interrompu, ou un essai à la main dans le groupe, a
	 * pu laisser l'une ou fermer l'autre. L'utilisateur 1 en est membre par la création même.
	 */
	private function prepareNewCommunity(): bool {

		if($this->seedNewFailure !== NULL) {
			$this->assertFail('semis du groupe neuf refusé : '.$this->seedNewFailure);
			return FALSE;
		}

		foreach(CourseLib::getByCommunity($this->eStructureNew) as $eCourse) {
			CourseLib::delete($eCourse);
		}

		$eFeature = \structure\FeatureLib::readByStructure($this->eStructureNew);
		$eFeature['hasStart'] = TRUE;

		\structure\FeatureLib::update($eFeature, ['hasStart']);

		$eMember = \structure\MemberLib::getByUserAndStructure($this->eUserAdmin, $this->eStructureNew);

		if($eMember->empty() or $eMember['status'] !== \structure\Member::IN) {
			$this->assertFail('l\'utilisateur '.self::USER_ADMIN.' n\'est pas membre IN du groupe '.$this->eStructureNew['id']);
			return FALSE;
		}

		return TRUE;

	}

	private function getCourseByName(string $name, int $community = self::COMMUNITY_NETWORK): Course {

		$eCourse = Course::model()
			->select('id')
			->whereCommunity($community)
			->whereName($name)
			->get();

		return $eCourse->empty() ? new Course() : $this->getCourse($eCourse['id']);

	}

}
?>
