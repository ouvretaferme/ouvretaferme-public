<?php
namespace learning;

require_once __DIR__.'/LearningDateFixture.t.php';

/**
 * Le bouton d'une date sur la fiche d'une formation datée du catalogue, lue sans compte (lot
 * visiteur-inscription) : il mène à la page publique de la date (L10c), où l'inscription se fait
 * sans compte. La fiche est rendue par la route
 * `/formation/{id}`, pour éprouver aussi le réseau que la page prépare.
 *
 * Le décor est celui de `LearningDateFixtureTest`, dans la transaction du test : une formation
 * publiée et retenue au catalogue, une date ouverte, une date complète, des personnes fictives en
 * `example.org`.
 *
 * Lancement : dev/test package=learning file=DateEnrollmentVisitor
 */
class DateEnrollmentVisitorTest extends LearningDateFixtureTest {

	const COURSE_URL = 'https://organisme.example.org/zztest-visiteur';

	/**
	 * Sans compte, une date ouverte et une date complète, que la route prépare sans place restante,
	 * mènent chacune à sa page publique et jamais au panneau du membre. Un compte garde le panneau.
	 */
	public function testVisitor(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eDateOpen, $eDateFull] = $this->seedCatalog();

		$eCommunity = \structure\CommunityLib::getByStructure($eCourse['community']);

		$this->actAs(new \user\User());

		$fiche = $this->renderFiche($eCourse);

		if($this->assertTrue(is_array($fiche), 'sans compte, la fiche se lit : '.(is_string($fiche) ? $fiche : '')) === FALSE) {
			return;
		}

		$open = $fiche[$eDateOpen['id']];
		$full = $fiche[$eDateFull['id']];

		$this->assertTrue(str_contains($open, '<a href="'.DatePublicUi::urlDate($eCommunity, $eDateOpen).'"'), 'date ouverte, le bouton mène à la page publique de la date : '.$open);
		$this->assertFalse(str_contains($open, 'dateEnrollment:create'), 'date ouverte, pas le panneau du membre');
		$this->assertFalse(str_contains($open, 'target='), 'date ouverte, dans le même onglet');

		$this->assertTrue(str_contains($full, '<a href="'.DatePublicUi::urlDate($eCommunity, $eDateFull).'"'), 'date complète, le bouton mène à la page publique de la date : '.$full);
		$this->assertFalse(str_contains($full, 'dateEnrollment:create'), 'date complète, pas le panneau du membre');

		$remaining = [];

		foreach($this->executeView('formation/'.$eCourse['id'], [])->cDate as $eDate) {
			$remaining[$eDate['id']] = $eDate['remaining'];
		}

		$this->assertTrue($remaining[$eDateOpen['id']] !== 0, 'la date ouverte garde des places');
		$this->assertEquals(0, $remaining[$eDateFull['id']], 'la date complète n\'en a plus, et le bouton passe en liste d\'attente');

		$this->actAs($this->eUserReader);

		$fiche = $this->renderFiche($eCourse);

		$this->assertTrue(str_contains($fiche[$eDateOpen['id']], '<a href="/learning/dateEnrollment:create?date='.$eDateOpen['id'].'"'), 'avec un compte, le panneau du membre, inchangé : '.$fiche[$eDateOpen['id']]);
		$this->assertFalse(str_contains($fiche[$eDateOpen['id']], DatePublicUi::urlList($eCommunity)), 'avec un compte, pas la page publique');

	}

	/**
	 * Une formation qui inscrit sur le site de l'organisme garde son lien sans compte, inchangé : la
	 * page publique de la date ne paraît pas.
	 */
	public function testVisitorExternal(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eDateOpen] = $this->seedCatalog();

		$eCommunity = \structure\CommunityLib::getByStructure($eCourse['community']);

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/course:doUpdateExternal', [
			'id' => (string)$eCourse['id'],
			'externalOnly' => '1',
			'externalUrl' => self::COURSE_URL,
		]), 'la formation se ferme à l\'inscription');

		$this->actAs(new \user\User());

		$fiche = $this->renderFiche($eCourse);

		if($this->assertTrue(is_array($fiche), 'sans compte, la fiche se lit : '.(is_string($fiche) ? $fiche : '')) === FALSE) {
			return;
		}

		$open = $fiche[$eDateOpen['id']];

		$this->assertTrue(str_contains($open, 'href="'.encode(self::COURSE_URL).'" target="_blank"'), 'le lien de l\'organisme, inchangé : '.$open);
		$this->assertFalse(str_contains($open, DatePublicUi::urlList($eCommunity)), 'pas la page publique');

	}

	/**
	 * La formation du catalogue, publiée et retenue (`Course::ACCEPTED`), une date ouverte dans dix
	 * jours et une date complète dans douze, d'une place prise par une personne confirmée.
	 *
	 * @return array{0: Course, 1: Date, 2: Date}
	 */
	private function seedCatalog(): array {

		$this->actAs($this->eUserAdmin);

		$this->join($this->eUserReader, $this->eStructureFarm);
		$this->affiliate($this->eStructureFarm);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::ACCEPTED, type: Course::ONSITE);

		$eDateOpen = $this->createDate($eCourse, 10);
		$eDateFull = $this->createDate($eCourse, 12);

		$this->enrollConfirmed($eDateFull, $this->createPerson());

		// Posé sans `build()`, qui aurait borné `seats` au schéma : une place, la valeur que le
		// formulaire accepte aussi, sans le parcours du panneau d'édition qu'aucun test ici n'éprouve.
		$eDateFull = DateLib::getById($eDateFull['id']);
		$eDateFull['seats'] = 1;
		DateLib::update($eDateFull, ['seats']);

		return [$this->getCourse($eCourse['id']), DateLib::getById($eDateOpen['id']), DateLib::getById($eDateFull['id'])];

	}

	/**
	 * Le bouton de chaque date de la fiche, rendu sur les dates que la route prépare, par id de
	 * date ; le nom de l'action levée si la fiche ne se lit pas.
	 *
	 * @return array<int, string>|string
	 */
	private function renderFiche(Course $eCourse): array|string {

		$data = $this->executeView('formation/'.$eCourse['id'], []);

		if(is_string($data)) {
			return $data;
		}

		$actions = [];

		foreach($data->cDate as $eDate) {
			$actions[$eDate['id']] = new DateEnrollmentUi()->getFicheAction($eDate);
		}

		return $actions;

	}

}
?>
