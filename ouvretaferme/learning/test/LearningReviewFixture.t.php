<?php
namespace learning;

require_once __DIR__.'/LearningDateFixture.t.php';

/**
 * Le décor des tests des avis (lot L15c) : une formation du réseau au catalogue public, ouverte aux
 * membres du réseau, que des personnes de la ferme rattachée suivent, à une date passée où leur place
 * était confirmée, ou en ligne jusqu'à la dernière leçon.
 *
 * Les auteurs sont des comptes fictifs en `example.org` et des lignes sans compte de la page
 * publique : le compte 1 écrit la formation, il n'est jamais auteur d'un avis ni destinataire d'un
 * e-mail. Les tests qui traversent un envoi relèvent `getLastMail()` avant et après.
 */
abstract class LearningReviewFixtureTest extends LearningDateFixtureTest {

	/**
	 * La formation au catalogue public, avec une leçon, ou deux en ligne pour qu'une seule faite ne
	 * suffise pas. `$first` à faux pour une seconde formation dans le même test, le rattachement de la
	 * ferme ne se posant qu'une fois, comme dans `seedCourse()`.
	 *
	 * @return array{0: Course, 1: Lesson[]}
	 */
	protected function seedCatalog(string $type = Course::ONSITE, bool $first = TRUE): array {

		$eCourse = $first
			? $this->seed($type)
			: $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE, type: $type);

		// Posé sans passer par la demande et la décision de l'équipe, que `CatalogCircuit.t.php` teste.
		$eCourse['catalog'] = Course::ACCEPTED;
		CourseLib::update($eCourse, ['catalog']);

		$this->assertEquals([], $this->shareNetwork($eCourse), 'formation ouverte aux membres du réseau');

		$eChapter = $this->createChapter($eCourse, 'zzTest module');

		$lessons = [$this->createLesson($eChapter, 'zzTest leçon 1', 10)];

		if($type === Course::ONLINE) {
			$lessons[] = $this->createLesson($eChapter, 'zzTest leçon 2', 10);
		}

		return [$this->getCourse($eCourse['id']), $lessons];

	}

	/**
	 * La place confirmée de la personne à une date passée depuis `$offset` jours : la date se crée à
	 * venir, la place s'y confirme, puis la date recule, comme le temps le ferait.
	 */
	protected function followDate(Course $eCourse, \user\User $eUser, int $offset = -3): DateEnrollment {

		$this->actAs($this->eUserAdmin);

		$eDate = $this->createDate($eCourse, 10);
		$eDateEnrollment = $this->enrollConfirmed($eDate, $eUser);

		$this->moveDate($eDate, $offset);

		return $this->getLine($eDateEnrollment['id']);

	}

	/**
	 * La formation en ligne suivie jusqu'aux leçons données, par le chemin du bouton « Commencer »
	 * puis de « J'ai terminé ».
	 */
	protected function followOnline(Course $eCourse, \user\User $eUser, array $lessons): void {

		$eEnrollment = new Enrollment(['user' => $eUser, 'course' => $eCourse, 'structure' => $this->eStructureFarm]);
		EnrollmentLib::create($eEnrollment);

		$eEnrollment = EnrollmentLib::getByUserAndCourse($eUser, $eCourse);

		foreach($lessons as $eLesson) {
			ProgressLib::done($eEnrollment, $eLesson);
		}

	}

	/**
	 * L'avis du compte, posté sur `doCreate` comme le panneau le poste : rend l'action levée et le
	 * refus qu'elle porte, `ReloadAction` quand l'avis est écrit.
	 *
	 * @return array{0: string, 1: ?string}
	 */
	protected function writeReview(Course $eCourse, \user\User $eUser, int $rating, ?string $text = NULL): array {

		$this->actAs($eUser);

		$result = $this->executePage('learning/review:doCreate', [
			'course' => $eCourse['id'],
			'rating' => (string)$rating,
			'text' => (string)$text
		]);

		$this->actAs($this->eUserAdmin);

		return $result;

	}

	protected function getReview(Course $eCourse, \user\User $eUser): Review {
		return ReviewLib::getByCourseAndUser($this->getCourse($eCourse['id']), $eUser);
	}

	protected function getReviewById(int $id): Review {
		return ReviewLib::getById($id, Review::getSelection());
	}

	/**
	 * Le résumé que la carte et la fiche montrent.
	 *
	 * @return array{0: int, 1: ?float}
	 */
	protected function getSummary(Course $eCourse): array {

		$eCourse = $this->getCourse($eCourse['id']);

		ReviewLib::fillSummary(new \Collection([$eCourse['id'] => $eCourse]));

		return [$eCourse['reviews'], $eCourse['rating']];

	}

}
?>
