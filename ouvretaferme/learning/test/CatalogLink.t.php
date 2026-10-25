<?php
namespace learning;

require_once __DIR__.'/LearningNetworkFixture.t.php';

/**
 * Le lien vers le catalogue public (`/formations`) ne paraît que là où le catalogue répond (remarque
 * 25 du 01/10/2026 : « Tout le catalogue » menait à une adresse inconnue depuis la fiche d'une
 * formation du réseau ouvert en production).
 *
 * Le catalogue ne s'ouvre qu'avec `LearningSetting::$active`, alors que la fiche d'une formation
 * d'une structure de `LearningSetting::STRUCTURE_BETA` et « Mes formations » se lisent sans lui. La
 * leçon ne mène plus au catalogue : sa colonne ne liste que les leçons de son module. Chaque méthode joue la page catalogue fermé puis ouvert, et rend l'écran avec ce
 * que la page a préparé, dans l'ordre où la vue le passe : le lien est absent fermé, présent ouvert.
 * La seconde moitié prouve aussi que la recherche du lien sait le trouver.
 *
 * `tearDown()` rend à l'interrupteur sa valeur de départ, que la méthode ait échoué ou non : le
 * lanceur l'appelle toujours.
 *
 * Lancement : dev/test package=learning file=CatalogLink
 */
class CatalogLinkTest extends LearningNetworkFixtureTest {

	private bool $activeInitial;

	public function setUp(): void {

		// Lu avant la transaction : si son ouverture lève, le lanceur appelle quand même `tearDown()`,
		// qui rend cette valeur.
		$this->activeInitial = LearningSetting::$active;

		parent::setUp();

	}

	public function tearDown(): void {

		LearningSetting::$active = $this->activeInitial;

		parent::tearDown();

	}

	/**
	 * Le cas constaté : la fiche d'une formation en brouillon du réseau listé, lue par son équipe.
	 * Catalogue fermé, la section « Formations » du menu ne garde que « Mes formations » ; pour un
	 * visiteur sans compte elle n'a plus d'entrée, et son titre part avec elle.
	 */
	public function testFiche(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);
		$this->createLesson($this->createChapter($eCourse, 'zzTest module'), 'zzTest leçon', 10);

		LearningSetting::$active = FALSE;

		$this->assertEquals(\NotExistsAction::class, $this->executeView('formations', []), 'catalogue fermé, son adresse est inconnue');

		$data = $this->getData('formation/'.$eCourse['id']);
		$menu = new CourseUi()->getMenu($data->eCourse, $data->cChapter, $data->cProgress, $data->isLogged, $data->hasCatalog);

		$this->assertFalse($this->hasCatalogLink($menu), 'catalogue fermé, le menu de la fiche ne mène pas au catalogue : '.$menu);
		$this->assertTrue(str_contains($menu, 'href="/mes-formations"'), 'la section garde « Mes formations » pour un compte connecté');

		$menuVisitor = new CourseUi()->getMenu($data->eCourse, $data->cChapter, $data->cProgress, FALSE, $data->hasCatalog);

		$this->assertFalse(str_contains($menuVisitor, 'learning-menu-section'), 'sans compte, la section n\'a plus d\'entrée et son titre ne paraît pas : '.$menuVisitor);

		LearningSetting::$active = TRUE;

		$this->assertTrue($this->executeView('formations', []) instanceof \stdClass, 'catalogue ouvert, son adresse répond');

		$data = $this->getData('formation/'.$eCourse['id']);
		$menu = new CourseUi()->getMenu($data->eCourse, $data->cChapter, $data->cProgress, $data->isLogged, $data->hasCatalog);

		$this->assertTrue($this->hasCatalogLink($menu), 'catalogue ouvert, le menu de la fiche mène au catalogue : '.$menu);

		$menuVisitor = new CourseUi()->getMenu($data->eCourse, $data->cChapter, $data->cProgress, FALSE, $data->hasCatalog);

		$this->assertTrue($this->hasCatalogLink($menuVisitor), 'sans compte aussi');

	}

	/**
	 * « Mes formations » s'ouvre dès qu'une structure est ouverte : son bouton vers le catalogue
	 * suit le catalogue.
	 */
	public function testMine(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->seedLessons();

		$this->actAs($this->eUserReader);

		LearningSetting::$active = FALSE;

		$data = $this->getData('mes-formations');
		$mine = new CatalogUi()->getMine($data->ccShareSection, $data->ccShareProposed, $data->cCourseRunning, $data->cCourseDone, $data->started, $data->hasCatalog);

		$this->assertFalse($this->hasCatalogLink($mine), 'catalogue fermé, « Mes formations » ne mène pas au catalogue');

		LearningSetting::$active = TRUE;

		$data = $this->getData('mes-formations');
		$mine = new CatalogUi()->getMine($data->ccShareSection, $data->ccShareProposed, $data->cCourseRunning, $data->cCourseDone, $data->started, $data->hasCatalog);

		$this->assertTrue($this->hasCatalogLink($mine), 'catalogue ouvert, « Mes formations » mène au catalogue');

	}

	/**
	 * L'état vide de « Mes formations », pour un compte auquel rien n'est ouvert et qui n'a rien
	 * commencé : sa phrase ne renvoie au catalogue que s'il répond.
	 */
	public function testMineEmpty(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserReader);

		$sentenceCatalog = s("Une formation se commence depuis sa fiche, dans le catalogue.");

		LearningSetting::$active = FALSE;

		$data = $this->getData('mes-formations');

		$this->assertEquals([TRUE, TRUE, TRUE, TRUE, FALSE], [$data->ccShareSection->empty(), $data->ccShareProposed->empty(), $data->cCourseRunning->empty(), $data->cCourseDone->empty(), $data->hasCatalog], 'catalogue fermé, rien d\'ouvert ni de commencé');

		$mine = new CatalogUi()->getMine($data->ccShareSection, $data->ccShareProposed, $data->cCourseRunning, $data->cCourseDone, $data->started, $data->hasCatalog);

		$this->assertFalse(str_contains($mine, $sentenceCatalog), 'catalogue fermé, l\'état vide ne renvoie pas au catalogue : '.$mine);

		LearningSetting::$active = TRUE;

		$data = $this->getData('mes-formations');

		$this->assertEquals([TRUE, TRUE, TRUE, TRUE, TRUE], [$data->ccShareSection->empty(), $data->ccShareProposed->empty(), $data->cCourseRunning->empty(), $data->cCourseDone->empty(), $data->hasCatalog], 'catalogue ouvert, rien d\'ouvert ni de commencé');

		$mine = new CatalogUi()->getMine($data->ccShareSection, $data->ccShareProposed, $data->cCourseRunning, $data->cCourseDone, $data->started, $data->hasCatalog);

		$this->assertTrue(str_contains($mine, $sentenceCatalog), 'il renvoie au catalogue : '.$mine);

	}

	/**
	 * Un lien vers le catalogue public. Les adresses d'un groupe (`/groupe/<id>/formations`) et d'un
	 * réseau (`/reseau/<fqn>/formations`) ne commencent pas par `/formations` et n'en sont pas.
	 */
	private function hasCatalogLink(string $html): bool {
		return (preg_match('#href="/formations["?/]#', $html) === 1);
	}

	/**
	 * Ce que la page prépare pour sa vue. Une page qui refuse est un échec du décor, pas un cas du
	 * test : l'exception le nomme.
	 */
	private function getData(string $path): \stdClass {

		$data = $this->executeView($path, []);

		if($data instanceof \stdClass) {
			return $data;
		}

		throw new \Exception('/'.$path.' ne s\'ouvre pas : '.$data);

	}

	/**
	 * Une formation en ligne publiée du réseau, où le lecteur est inscrit, avec une leçon à lire.
	 */
	private function seedLessons(): void {

		$eCourse = $this->seed();

		$this->actAs($this->eUserAdmin);

		$this->enrollReader($eCourse);

		$this->createLesson($this->createChapter($eCourse, 'zzTest module'), 'zzTest leçon', 10);

	}

}
?>
