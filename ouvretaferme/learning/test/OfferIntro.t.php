<?php
namespace learning;

require_once __DIR__.'/LearningDateFixture.t.php';

/**
 * L'introduction du mode de diffusion (`CourseUi::getOfferIntro()`) dans l'onglet Diffusion, pour
 * une formation en ligne comme pour une formation datée.
 *
 * Sur une formation datée, la phrase ne se lit qu'avec des dates listées : ni la carte « Aucune
 * date à venir » ni celle de l'inscription sur le site de l'organisme ne diffusent quoi que ce soit.
 * Le test le vérifie dans les deux sens, la phrase apparaissant avec la première date et
 * disparaissant quand l'inscription passe sur le site de l'organisme.
 *
 * Tout se pose dans la transaction du test, sur le réseau G3 du décor.
 *
 * Lancement : dev/test package=learning file=OfferIntro
 */
class OfferIntroTest extends LearningDateFixtureTest {

	public function testCourse(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seed();

		$intro = CourseUi::getOfferIntro();

		$this->assertTrue($this->renderCourseOffer($eCourse) !== '', 'le panneau de la formation se rend');
		$this->assertTrue(str_contains($this->renderPage($eCourse), $intro), 'la page d\'une formation en ligne la porte');

	}

	public function testDates(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);

		$intro = CourseUi::getOfferIntro();

		$page = $this->renderPage($eCourse);

		$this->assertFalse(str_contains($page, $intro), 'sans date à venir, la page ne porte pas l\'introduction');

		$eDate = $this->createDate($eCourse, 10);

		$this->assertTrue(str_contains($this->renderPage($eCourse), $intro), 'avec une date listée, la page la porte');
		$this->assertTrue($this->renderDateOffer($eDate) !== '', 'le panneau de la date se rend');

		// Écrit hors de build() : seul l'état « inscription sur le site de l'organisme » compte ici,
		// et l'adresse que `Course::externalUrl.uncovered` exigerait ne change rien à la section.
		$eCourse = $this->getCourse($eCourse['id']);
		$eCourse['externalOnly'] = TRUE;
		CourseLib::update($eCourse, ['externalOnly']);

		$page = $this->renderPage($eCourse);

		$this->assertFalse(str_contains($page, $intro), 'sur le site de l\'organisme, la page ne porte plus l\'introduction');

	}

	private function renderCourseOffer(Course $eCourse): string {

		$data = $this->executeView('learning/course:updateOffer', ['id' => $eCourse['id']]);

		$this->assertTrue($data instanceof \stdClass, 'le panneau de la formation '.$eCourse['id'].' se prépare');

		return new CourseUi()->updateOffer($data->e)->body;

	}

	private function renderDateOffer(Date $eDate): string {

		$data = $this->executeView('learning/date:updateOffer', ['id' => $eDate['id']]);

		$this->assertTrue($data instanceof \stdClass, 'le panneau de la date '.$eDate['id'].' se prépare');

		return new DateUi()->updateOffer($data->e)->body;

	}

	private function renderPage(Course $eCourse): string {

		$data = $this->executeView('formation/'.$eCourse['id'].'/edition', []);

		$this->assertTrue($data instanceof \stdClass, 'l\'onglet Diffusion de la formation '.$eCourse['id'].' se prépare');

		return new CatalogUi()->getCourse($data->eCourse, $data->cDatePlanned);

	}

}
?>
