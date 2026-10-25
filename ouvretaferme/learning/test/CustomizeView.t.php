<?php
namespace learning;

require_once __DIR__.'/LearningNetworkFixture.t.php';

/**
 * Le thème d'un réseau sur la fiche et la leçon d'une de ses formations (lot A2), lu sur la page
 * rendue : la portée que la vue fait poser, `learning-doc` sur le conteneur de la fiche, menu compris,
 * `learning-public` autour de la leçon, comme sur les pages publiques du réseau (T12b), son gabarit
 * (lot A3a-2), et les styles qu'elle y pose. Les réglages eux-mêmes sont éprouvés par `CustomizeTest`.
 *
 * Lancement : dev/test package=learning file=CustomizeView
 */
class CustomizeViewTest extends LearningNetworkFixtureTest {

	const GREEN = '#2f6b4f';
	const CREAM = '#f3ede4';
	// Lin mêlé de moitié de blanc (`CommunityLib::BACKGROUND_SOFTENING`) : le fond que la page pose.
	const CREAM_SOFTENED = '#f9f6f2';

	public function testCourseCarriesTheme(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seed();

		$this->assertTheme('la fiche', $this->renderRoute('formation/'.$eCourse['id'], view: 'learning/course'), '<div class="container learning-doc">', '.learning-doc');

	}

	public function testLessonCarriesTheme(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seed();
		$eLesson = $this->createLesson($this->createChapter($eCourse, 'Module'), 'Leçon', 5);

		$this->assertTheme('la leçon', $this->renderRoute('formation/lecon/'.$eLesson['id'], view: 'learning/lesson'), '<div class="learning-public util-brand-scope">', '.learning-public');

	}

	public function testLessonCarriesDesign(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = parent::seed(Course::ONLINE);
		$eLesson = $this->createLesson($this->createChapter($eCourse, 'Module'), 'Leçon', 5);

		$this->theme('kraft');

		$html = $this->renderRoute('formation/lecon/'.$eLesson['id'], view: 'learning/lesson');

		$this->assertTrue(str_contains($html, '<div class="learning-public util-brand-scope" data-learning-design="kraft">'), 'la leçon porte le gabarit sur sa portée');
		$this->assertTrue(str_contains($html, 'design-kraft.css'), 'et charge sa feuille');

	}

	/**
	 * Une formation de l'équipe garde le gabarit du catalogue : le jeton est bien celui du réseau, et
	 * non un ajout de toutes les fiches.
	 */
	public function testTeamCourseKeepsTemplate(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->theme();

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::ACCEPTED);

		$html = $this->renderRoute('formation/'.$eCourse['id'], view: 'learning/course');

		$this->assertEquals(1, preg_match('/<body[^>]* data-template="[^"]*"/', $html), 'la fiche de l\'équipe est rendue');
		$this->assertFalse(str_contains($html, 'learning-doc'), 'sans la portée du thème, ni ses styles');

	}

	private function assertTheme(string $page, string $html, string $scope, string $selector): void {

		$this->assertTrue(str_contains($html, $scope), $page.' porte la portée du thème');
		$this->assertTrue(str_contains($html, $selector.' {--primary: '.self::GREEN.';'), $page.' y pose la couleur du réseau');
		$this->assertTrue(str_contains($html, '--learning-background: '.self::CREAM_SOFTENED.';'), $page.' et son fond, adouci');

	}

	protected function seed(string $type = Course::ONLINE, string $catalog = Course::PRIVATE): Course {

		$this->theme();

		return parent::seed($type, $catalog);

	}

	private function theme(?string $design = NULL): void {

		$eCommunity = \structure\CommunityLib::getByStructure($this->eStructureNetwork);

		$values = [];

		foreach(CommunityLib::getPropertiesCustomize() as $property) {
			$values[$property] = $eCommunity[$property] ?? '';
		}

		$values['color'] = self::GREEN;
		$values['customBackground'] = self::CREAM;
		$values['customDesign'] = $design ?? '';

		$fw = new \FailWatch();

		CommunityLib::updateCustomize($this->eStructureNetwork, $values);

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('réglages refusés : '.json_encode($fw->get())));

		$this->forgetMemoization();

	}

}
?>
