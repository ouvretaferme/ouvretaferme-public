<?php
namespace learning;

require_once __DIR__.'/LearningNetworkFixture.t.php';

/**
 * La partie B du lot « publier » (planche validée par V-Learning le 05/10) : la durée demandée sous
 * le titre à la création d'une leçon (D1), et « Avant de publier » qui sépare ce qui bloque de ce
 * qui est seulement recommandé (P1).
 *
 * Tout se joue sur des éléments en mémoire : aucune règle serveur ne change, seul l'écran et
 * l'inventaire des recommandations sont éprouvés.
 */
class PublishRecommendedTest extends LearningNetworkFixtureTest {

	/**
	 * La durée est facultative sous une formation en brouillon et exigée sous une formation publiée,
	 * comme `Lesson::minutes.classified` le refuse déjà.
	 */
	public function testLessonCreateMinutes(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		foreach([Course::DRAFT => FALSE, Course::PUBLISHED => TRUE] as $status => $required) {

			$eLesson = new Lesson([
				'chapter' => new Chapter(['id' => 1, 'name' => 'Module']),
				'course' => new Course(['id' => 1, 'status' => $status]),
			]);

			$html = new LessonUi()->create($eLesson)->body;

			preg_match('/data-wrapper="minutes".*?<\/label>/s', $html, $label);

			$this->assertEquals($required, str_contains($label[0], 'form-asterisk'), 'en '.$status.', la durée est '.($required ? 'exigée' : 'facultative'));

		}

	}

	/**
	 * Seule une fiche technique entièrement vide est recommandée. Le prix, recommandé jusqu'au lot
	 * glacons-parcours, est exigé pour publier (`Pricing.t.php`, `testPriced()`).
	 */
	public function testRecommended(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$properties = array_fill_keys(array_merge(CourseLib::getPropertiesDetails(), CourseLib::getPropertiesPrice()), NULL);

		$eCourseNetwork = new Course(['community' => $this->eStructureNetwork] + $properties);

		$this->assertEquals(['details'], CourseLib::getRecommendedForPublication($eCourseNetwork), 'fiche vide, le prix n\'est plus recommandé');

		$eCourseNetwork['audience'] = 'Maraîchers';

		$this->assertEquals([], CourseLib::getRecommendedForPublication($eCourseNetwork), 'une seule rubrique de la fiche suffit');

	}

	/**
	 * Le recommandé s'ajoute au panneau, que quelque chose bloque encore ou non, et disparaît quand
	 * il n'y a rien à recommander ; seul le panneau où rien ne bloque mène à la publication.
	 */
	public function testPanel(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = new Course(['id' => 1, 'community' => $this->eStructureNetwork, 'type' => Course::ONLINE, 'name' => 'Formation', 'dates' => 0, 'lessons' => 1, 'readers' => 0, 'shares' => 0, 'members' => 1]);

		$recommended = '/learning/course:updateDetails?id=1';

		$panelMissing = new CourseUi()->publish($eCourse, new \Collection(), ['summary'], new \Collection(), FALSE, ['details']);

		$this->assertTrue(str_contains($panelMissing->body, $recommended), 'le recommandé avec le bloquant');
		$this->assertFalse(str_contains($panelMissing->body, 'doUpdateStatus'), 'pas de publication tant que quelque chose bloque');

		$panelReady = new CourseUi()->publish($eCourse, new \Collection(), [], new \Collection(), FALSE, ['details']);

		$this->assertTrue(str_contains($panelReady->body, $recommended), 'le recommandé quand rien ne bloque');
		$this->assertTrue(str_contains($panelReady->body, 'doUpdateStatus'), 'rien ne bloque, le panneau mène à la publication');

		$panelPlain = new CourseUi()->publish($eCourse, new \Collection(), [], new \Collection(), FALSE, []);

		$this->assertFalse(str_contains($panelPlain->body, $recommended), 'rien de recommandé, pas d\'encadré');

	}

}
?>
