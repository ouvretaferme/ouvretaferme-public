<?php
namespace learning;

require_once __DIR__.'/LearningFixture.t.php';

/**
 * Le type d'une formation (lot L10t) : présentiel, visio ou en ligne. Il se choisit à la création,
 * n'est ouvert qu'aux réseaux, se change tant que la formation n'a aucune date, annulées comprises,
 * et range la liste des formations du réseau.
 *
 * Ce que le fichier attend de la base, relu par `assertFixtures()` avant chaque test : le réseau
 * G3 du décor commun (`COMMUNITY_NETWORK`), de type `network`, dont `setUp()` fait de
 * l'utilisateur 1 (`USER_ADMIN`) un conseiller IN, et l'école G2 (`COMMUNITY_EDUCATION`).
 * Les formations s'écrivent par le chemin de `doCreate` et de `doUpdateSettings`.
 */
class CourseTypeTest extends LearningFixtureTest {

	const COMMUNITY_NETWORK = \structure\FixtureAiLib::G3;

	private \structure\Structure $eStructureNetwork;

	protected function getMemberships(): array {
		return parent::getMemberships() + [
			self::COMMUNITY_NETWORK => 'advisor',
		];
	}

	protected function assertFixtures(): bool {

		if(parent::assertFixtures() === FALSE) {
			return FALSE;
		}

		$this->eStructureNetwork = $this->getCommunity(self::COMMUNITY_NETWORK);

		if($this->eStructureNetwork->empty() or $this->eStructureNetwork->isNetwork() === FALSE) {
			$this->assertFail('le réseau '.self::COMMUNITY_NETWORK.' est absent de cette base, ou n\'est pas un réseau');
			return FALSE;
		}

		$eMember = \structure\MemberLib::getByUserAndStructure($this->eUserAdmin, $this->eStructureNetwork);

		if(
			$eMember->empty() or
			$eMember['status'] !== \structure\Member::IN or
			$eMember['role']['fqn'] !== 'advisor'
		) {
			$this->assertFail('l\'utilisateur '.self::USER_ADMIN.' n\'est pas conseiller IN du réseau '.self::COMMUNITY_NETWORK.' dans cette base');
			return FALSE;
		}

		return TRUE;

	}

	/**
	 * Un réseau crée les trois types ; une école ne crée qu'en ligne, et un type absent vaut en ligne.
	 */
	public function testCreate(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		foreach([Course::ONSITE, Course::REMOTE, Course::ONLINE] as $type) {

			$name = 'zzTest type '.$type;

			$this->assertEquals([\RedirectAction::class, NULL], $this->executePage('learning/course:doCreate', ['community' => (string)self::COMMUNITY_NETWORK, 'name' => $name, 'type' => $type]), 'le réseau crée une formation '.$type);
			$this->assertEquals($type, $this->getLastCourse(self::COMMUNITY_NETWORK, $name)['type'], 'et son type est écrit');

		}

		$this->assertEquals([\RedirectAction::class, NULL], $this->executePage('learning/course:doCreate', ['community' => (string)self::COMMUNITY_NETWORK, 'name' => 'zzTest type absent']), 'sans type posté');
		$this->assertEquals(Course::ONLINE, $this->getLastCourse(self::COMMUNITY_NETWORK, 'zzTest type absent')['type'], 'la formation est en ligne');

		$this->assertEquals([\FailAction::class, 'Course::type.check'], $this->executePage('learning/course:doCreate', ['community' => (string)self::COMMUNITY_NETWORK, 'name' => 'zzTest type inconnu', 'type' => 'hybrid']), 'un type inconnu est refusé');

		$this->assertEquals([\FailAction::class, 'Course::type.network'], $this->executePage('learning/course:doCreate', ['community' => (string)self::COMMUNITY_EDUCATION, 'name' => 'zzTest école', 'type' => Course::ONSITE]), 'une école ne crée pas en présentiel');
		$this->assertEquals([\FailAction::class, 'Course::type.network'], $this->executePage('learning/course:doCreate', ['community' => (string)self::COMMUNITY_EDUCATION, 'name' => 'zzTest école', 'type' => Course::REMOTE]), 'ni en visio');
		$this->assertTrue($this->getLastCourse(self::COMMUNITY_EDUCATION, 'zzTest école')->empty(), 'rien d\'écrit');

		$this->assertEquals([\RedirectAction::class, NULL], $this->executePage('learning/course:doCreate', ['community' => (string)self::COMMUNITY_EDUCATION, 'name' => 'zzTest école']), 'mais crée en ligne sans poster de type');
		$this->assertEquals(Course::ONLINE, $this->getLastCourse(self::COMMUNITY_EDUCATION, 'zzTest école')['type'], 'en ligne');

	}

	/**
	 * Sans date, le type se change dans les réglages (edition-formation, R2). Une date, même annulée,
	 * le fige : les réglages
	 * s'enregistrent encore sans le poster, le type reste celui de la base, et un autre type posté
	 * par un panneau ouvert avant la première date est refusé avec son message.
	 */
	public function testUpdate(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);

		$this->assertTrue(in_array('type', (CourseLib::getPropertiesUpdate())($eCourse)), 'sans date, les réglages construisent le type');
		$this->assertFalse(in_array('type', (CourseLib::getPropertiesUpdate('api'))($eCourse)), 'l\'API ne le modifie pas');

		$data = $this->executeView('learning/course:updateSettings', ['id' => $eCourse['id']]);
		$settings = new CourseUi()->updateSettings($data->e)->body;

		$this->assertEquals(3, preg_match_all('/<input(?=[^>]*type="radio")(?=[^>]*name="type")/', $settings), 'trois boutons radio, un par type');

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/course:doUpdateSettings', $this->settings($eCourse, ['type' => Course::REMOTE])), 'type changé sans date');
		$this->assertEquals(Course::REMOTE, $this->getCourse($eCourse['id'])['type'], 'la formation passe en visio');

		$eDate = $this->createDate($this->getCourse($eCourse['id']));

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/date:doCancel', ['id' => (string)$eDate['id']]), 'la seule date est annulée');

		$eCourse = $this->getCourse($eCourse['id']);

		$this->assertTrue(DateLib::existsByCourse($eCourse), 'une date annulée compte');

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/course:doUpdateSettings', $this->settings($eCourse, ['name' => 'zzTest datée renommée'])), 'la formation datée enregistre ses réglages sans poster de type');
		$this->assertEquals(['zzTest datée renommée', Course::REMOTE], [$this->getCourse($eCourse['id'])['name'], $this->getCourse($eCourse['id'])['type']], 'le nom change, le type non');

		$this->assertEquals([\FailAction::class, 'Course::type.dated'], $this->executePage('learning/course:doUpdateSettings', $this->settings($eCourse, ['type' => Course::ONSITE])), 'un autre type posté est refusé');
		$this->assertEquals(Course::REMOTE, $this->getCourse($eCourse['id'])['type'], 'le type reste en visio');

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/course:doUpdateSettings', $this->settings($eCourse, ['type' => Course::REMOTE])), 'le même type reposté passe');

	}

	/**
	 * Publier et dépublier une formation de réseau parle du réseau, en ligne comme à des dates : le
	 * texte suit `isNetwork()`, pas `isDated()`, qui écarte les formations en ligne.
	 */
	public function testPublishText(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$texts = function(string $type): array {

			$eCourse = new Course(['id' => 1, 'community' => $this->eStructureNetwork, 'type' => $type, 'dates' => 0, 'lessons' => 1, 'readers' => 0, 'shares' => 0, 'members' => 2, 'sections' => 1]);

			return [
				new CourseUi()->publish($eCourse, new \Collection(), [], new \Collection(), FALSE, [])->body,
				new CourseUi()->unpublish($eCourse, new \Collection())->body
			];

		};

		[$publishOnline, $unpublishOnline] = $texts(Course::ONLINE);

		$this->assertTrue($unpublishOnline !== '', 'la dépublication se rend');

		foreach([Course::ONSITE, Course::REMOTE] as $type) {
			$this->assertEquals([$publishOnline, $unpublishOnline], $texts($type), 'une formation de réseau '.$type.' se publie et se dépublie comme une formation en ligne');
		}

	}

	/**
	 * Le panneau de publication porte le geste qui publie quand rien ne manque, et ne l'offre pas tant
	 * qu'il manque quelque chose : chaque manque se remplit par son lien.
	 */
	public function testPanelButtons(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = new Course(['id' => 1, 'community' => $this->eStructureNetwork, 'type' => Course::ONLINE, 'name' => 'Formation', 'dates' => 0, 'lessons' => 1, 'readers' => 0, 'shares' => 0, 'members' => 1]);

		$panelReady = new CourseUi()->publish($eCourse, new \Collection(), [], new \Collection(), FALSE, []);

		$this->assertTrue(str_contains($panelReady->body, 'data-ajax="/learning/course:doUpdateStatus"'), 'prête à publier, le geste qui publie');

		$panelMissing = new CourseUi()->publish($eCourse, new \Collection(), ['summary'], new \Collection(), FALSE, []);

		$this->assertFalse(str_contains($panelMissing->body, 'course:doUpdateStatus'), 'avant de publier, pas de geste qui publie');

	}

	/**
	 * Le filtre de la liste du réseau compte les formations par type, sur la liste entière ; un type
	 * sans formation choisi rend l'appel à en créer une.
	 */
	public function testFilter(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$cCourse = new \Collection([
			$this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE, type: Course::REMOTE),
			$this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE),
			$this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE),
		]);

		$types = CourseLib::countByType($cCourse);

		$this->assertEquals([Course::ONSITE => 0, Course::REMOTE => 1, Course::MIXED => 0, Course::ONLINE => 2], $types, 'les quatre types sont comptés, zéro compris');
		$this->assertEquals([Course::ONSITE => 0, Course::REMOTE => 0, Course::MIXED => 0, Course::ONLINE => 0], CourseLib::countByType(new \Collection()), 'une liste vide rend quatre zéros');

		$url = '/groupe/'.$this->eStructureNetwork['id'].'/formations?type=';

		$html = CourseUi::getCommunityTypeTabs($this->eStructureNetwork, $types, NULL);

		$this->assertTrue(str_contains($html, 'href="'.$url.Course::REMOTE.'"'), 'un type qui a des formations a son onglet');
		$this->assertFalse(str_contains($html, 'href="'.$url.Course::ONSITE.'"'), 'un type sans formation n\'a pas d\'onglet');

		$html = CourseUi::getCommunityTypeTabs($this->eStructureNetwork, $types, Course::ONSITE);

		$this->assertTrue(str_contains($html, 'href="'.$url.Course::ONSITE.'"'), 'sauf s\'il est retenu par l\'adresse');

		$html = CourseUi::getTypeFilter($types, Course::REMOTE, fn(?string $option) => 'href="#'.$option.'"');

		$this->assertFalse(str_contains($html, 'href="#'.Course::ONSITE.'"'), 'chez le membre, le type sans formation disparaît');

		$html = CourseUi::getTypeFilter($types, Course::ONSITE, fn(?string $option) => 'href="#'.$option.'"');

		$this->assertTrue(str_contains($html, 'href="#'.Course::ONSITE.'"'), 'sauf s\'il est choisi');

		// La liste chargée comme par la page : une formation en ligne n'a ni prochaine date ni demande,
		// et sa ligne ne les lit pas. Un avertissement PHP ne fait échouer aucun test, d'où le relevé.
		$cCourseList = CourseLib::getByCommunity($this->eStructureNetwork)->find(fn(Course $eCourse) => in_array($eCourse['id'], $cCourse->getIds()));

		CourseLib::fillCounts($cCourseList);
		\learning\EnrollmentLib::fillReaders($cCourseList);
		DateEnrollmentLib::fillRequested($cCourseList);
		EnrollmentLib::fillRequests($cCourseList);
		CourseLib::fillCatalogBadge($cCourseList);
		ShareLib::fillShares($cCourseList);
		DateSheetLib::fillCountByCourse($cCourseList);

		$warnings = [];
		set_error_handler(function(int $number, string $message) use(&$warnings): bool {
			$warnings[] = $message;
			return TRUE;
		});

		$html = new CourseUi()->getCommunityList($this->eStructureNetwork, $cCourseList, $types, NULL, TRUE);

		restore_error_handler();

		$this->assertEquals([], $warnings, 'les lignes des trois formations se rendent sans avertissement');

		$html = new CourseUi()->getCommunityList($this->eStructureNetwork, new \Collection(), $types, Course::ONSITE, TRUE);

		$this->assertTrue(str_contains($html, CourseUi::urlCreate($this->eStructureNetwork, Course::ONSITE)), 'un type choisi sans formation appelle à en créer, du type choisi');

	}

	private function getLastCourse(int $community, string $name): Course {

		return Course::model()
			->select(Course::getSelection())
			->whereCommunity($community)
			->whereName($name)
			->sort(['id' => SORT_DESC])
			->get();

	}

	/** Les réglages tels que le panneau les poste, `type` seulement si `$input` le porte. */
	private function settings(Course $eCourse, array $input): array {

		return $input + [
			'id' => (string)$eCourse['id'],
			'name' => $eCourse['name'],
			'summary' => 'Une formation du décor de test.',
			'theme' => (string)$this->eTheme['id'],
			'level' => (string)$this->eLevel['id'],
		];

	}

	private function createDate(Course $eCourse): Date {

		$eDate = new Date(['course' => $eCourse]);

		$fw = new \FailWatch();

		$eDate->build(DateLib::getPropertiesCreate(), $this->postSequences([
			[$this->day(10), '14:00', '16:30', $eCourse->getDefaultSequence()['mode'], 'Salle des fêtes, Crest'],
		]) + [
			'link' => 'https://visio.example.org/salle',
		], new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('date refusée : '.json_encode($fw->get())));

		DateLib::create($eDate);

		return $eDate;

	}

}
?>
