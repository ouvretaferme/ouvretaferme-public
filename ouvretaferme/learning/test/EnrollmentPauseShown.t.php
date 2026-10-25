<?php
namespace learning;

require_once __DIR__.'/LearningDateFixture.t.php';

/**
 * Ce que la fiche dit de la pause des inscriptions (lot T13b, arbitré par V-Learning le 19/10) :
 * - la pause se dit sous la condition de son interrupteur (`Course::isEnrollmentPauseShown()`) : une
 *   formation datée dont toutes les dates sont passées ne la montre plus, ni sur la fiche ni sur la
 *   carte, et la montre de nouveau avec une date à venir ;
 * - qui a déjà sa ligne, inscription en ligne ou place à une date, ne la lit pas en tête de la fiche
 *   (`hasEnrollmentLine`), comme sur une date ;
 * - le lien vers le site de l'organisme d'une formation fermée suit la même condition : la pause
 *   d'une formation dont les dates sont passées ne coupe plus la seule porte de la fiche ;
 * - de même pour la première leçon que la fiche propose au visiteur.
 *
 * Chaque écran est préparé par sa page (`executeView()`) ; le test juge la donnée qu'elle prépare.
 *
 * Lancement : dev/test package=learning file=EnrollmentPauseShown
 */
class EnrollmentPauseShownTest extends LearningDateFixtureTest {

	const EXTERNAL_URL = 'https://formations.example.org/zztest-pause';

	public function testDated(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);
		$eDate = $this->createDate($eCourse, 10);

		// La place se prend avant la pause, qui refuserait la demande.
		$eUser = $this->createPerson();
		$this->enrollConfirmed($eDate, $eUser);

		$this->assertEquals([\ViewAction::class, NULL], $this->pause($eCourse, TRUE), 'le réseau met la formation en pause');

		$data = $this->getFiche($eCourse);

		$this->assertTrue($data->eCourse->isEnrollmentPauseShown(), 'une date à venir, la fiche dit la pause');
		$this->assertFalse($data->eCourse['hasEnrollmentLine'], 'sans ligne, la tête la dit');
		$this->assertTrue($this->getCard($eCourse)->isEnrollmentPauseShown(), 'une date à venir, la carte dit la pause');

		$this->actAs($eUser);
		$this->assertTrue($this->getFiche($eCourse)->eCourse['hasEnrollmentLine'], 'une place confirmée, la tête ne la dit pas');
		$this->actAs($this->eUserAdmin);

		$this->moveDate($eDate, -5);

		$this->assertTrue($this->getCourse($eCourse['id'])['enrollmentPaused'], 'la pause reste en base');
		$this->assertFalse($this->getFiche($eCourse)->eCourse->isEnrollmentPauseShown(), 'toutes les dates passées, la fiche ne la dit plus');
		$this->assertFalse($this->getCard($eCourse)->isEnrollmentPauseShown(), 'toutes les dates passées, la carte non plus');

		$this->createDate($this->getCourse($eCourse['id']), 20);

		$this->assertTrue($this->getFiche($eCourse)->eCourse->isEnrollmentPauseShown(), 'la prochaine date la ramène');

	}

	/**
	 * Le rendu s'éprouve ici sur la cible du lien, l'adresse de l'organisme, qui est la porte de la
	 * fiche : sans elle, une formation fermée n'a plus aucun moyen de s'inscrire.
	 */
	public function testExternal(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);
		$eDate = $this->createDate($eCourse, 10);

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/course:doUpdateExternal', [
			'id' => (string)$eCourse['id'],
			'externalOnly' => '1',
			'externalUrl' => self::EXTERNAL_URL,
		]), 'la formation se ferme à l\'inscription');

		$this->assertEquals([\ViewAction::class, NULL], $this->pause($eCourse, TRUE), 'le réseau met la formation en pause');

		$this->assertFalse(str_contains($this->getFicheHtml($eCourse), 'href="'.self::EXTERNAL_URL.'"'), 'une date à venir en pause, la fiche ne porte aucun lien vers l\'organisme');

		$this->moveDate($eDate, -5);

		$this->assertTrue(str_contains($this->getFicheHtml($eCourse), 'href="'.self::EXTERNAL_URL.'"'), 'toutes les dates passées, la pause ne se dit plus et le lien revient');

	}

	/**
	 * Le rendu s'éprouve ici sur la cible du bouton, la première leçon ou la création du compte qui y
	 * mène : c'est le seul geste que la fiche offre au visiteur.
	 */
	public function testVisitorFirstLesson(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);
		$eDate = $this->createDate($eCourse, 10);

		// Retenue au catalogue public, comme l'équipe le fait (`admin/catalog:doAccept`) : le visiteur
		// ne lit une fiche datée que là.
		$fw = new \FailWatch();
		$eCourse->build(['catalog'], ['catalog' => Course::ACCEPTED]);
		$this->assertTrue($fw->ok(), 'la formation est retenue au catalogue public');
		CourseLib::updateCatalog($eCourse);

		$this->assertEquals([\ViewAction::class, NULL], $this->pause($eCourse, TRUE), 'le réseau met la formation en pause');

		$this->moveDate($eDate, -5);

		$this->actAs(new \user\User());

		$data = $this->getFiche($eCourse);

		$this->assertFalse($data->eCourse['open'], 'sans lecture libre');
		$this->assertTrue($data->eCourse['eLessonFirst']->notEmpty(), 'une première leçon écrite à proposer');
		$this->assertFalse($data->eCourse->isEnrollmentPauseShown(), 'toutes les dates passées, la pause ne se dit pas');

		$url = $data->eCourse['withAccountCreate']
			? '/learning/account:create?course='.$eCourse['id']
			: LessonUi::url($data->eCourse['eLessonFirst'], $data->eCourse['customizeQuery'] ?? []);

		$html = new CourseUi()->getFiche($data->eCourse, $data->cChapter, $data->cStaff, $data->eEnrollment, $data->cProgress, $data->cStructure, $data->isLogged, $data->cDate);

		$this->assertTrue(str_contains($html, 'href="'.$url.'"'), 'la pause qui ne se dit pas laisse au visiteur sa première leçon');

		$this->actAs($this->eUserAdmin);

	}

	public function testOnline(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seed();
		$this->setOnlineConfirm(\structure\Community::IMMEDIATE);
		$this->createLesson($this->createChapter($eCourse, 'zzTest module'), 'zzTest leçon', 10);

		$this->actAs($this->eUserReader);
		$this->assertEquals(\RedirectAction::class, $this->executePage('learning/enrollment:doCreate', ['course' => (string)$eCourse['id'], 'structure' => ''])[0], 'le compte s\'inscrit avant la pause');
		$this->actAs($this->eUserAdmin);

		$this->assertEquals([\ViewAction::class, NULL], $this->pause($eCourse, TRUE), 'le réseau met la formation en pause');

		$data = $this->getFiche($eCourse);

		$this->assertTrue($data->eCourse->isEnrollmentPauseShown(), 'en ligne, sans date, la pause se dit');
		$this->assertFalse($data->eCourse['hasEnrollmentLine'], 'sans ligne, la tête la dit');

		$this->actAs($this->eUserReader);
		$this->assertTrue($this->getFiche($eCourse)->eCourse['hasEnrollmentLine'], 'inscrit, la tête ne la dit pas');
		$this->actAs($this->eUserAdmin);

	}

	private function pause(Course $eCourse, bool $paused): array {

		return $this->executePage('learning/course:doUpdateEnrollmentPaused', [
			'id' => (string)$eCourse['id'],
			'enrollmentPaused' => $paused ? '1' : '0',
		]);

	}

	private function getFiche(Course $eCourse): \stdClass {

		$data = $this->executeView('formation/'.$eCourse['id'], []);

		if($data instanceof \stdClass) {
			return $data;
		}

		throw new \Exception('la fiche ne s\'ouvre pas : '.$data);

	}

	/** La fiche rendue comme la vue la rend, sur la donnée que la page prépare. */
	private function getFicheHtml(Course $eCourse): string {

		$data = $this->getFiche($eCourse);

		return new CourseUi()->getFiche($data->eCourse, $data->cChapter, $data->cStaff, $data->eEnrollment, $data->cProgress, $data->cStructure, $data->isLogged, $data->cDate);

	}

	/**
	 * La formation telle qu'une liste de cartes la prépare (`CourseLib::fillCounts()`).
	 */
	private function getCard(Course $eCourse): Course {

		$eCourse = $this->getCourse($eCourse['id']);
		CourseLib::fillCounts(new \Collection([$eCourse]));

		return $eCourse;

	}

}
?>
