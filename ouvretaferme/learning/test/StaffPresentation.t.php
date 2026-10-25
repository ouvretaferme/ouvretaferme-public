<?php
namespace learning;

require_once __DIR__.'/LearningFixture.t.php';

/**
 * La présentation d'un encadrant (`Supervisor.description`, lot contact-mails) se lit sur la fiche de la
 * formation et dans le panneau d'inscription, sous son nom et son rôle : dès qu'un encadrant en a
 * une, chacun prend son bloc. Sans aucune présentation, les encadrants gardent leur ligne unique
 * séparée par « · ».
 *
 * Ce que le fichier attend de la base, relu par `assertFixtures()` : le réseau G3 du décor commun
 * (`COMMUNITY_NETWORK`), dont l'utilisateur 1 (`USER_ADMIN`), fait conseiller par `setUp()`, écrit
 * les formations, la même attente que `StaffContactTest`. Formations et encadrants s'écrivent par le chemin des panneaux,
 * dans la transaction ; la présentation d'une personne se corrige par `SupervisorLib::update()`,
 * après `build()` sur sa liste, le panneau d'une formation ne réglant que le rôle et le contact.
 *
 * Lancement : dev/test package=learning file=StaffPresentation
 */
class StaffPresentationTest extends LearningFixtureTest {

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

		return TRUE;

	}

	public function testFiche(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);
		$eChapter = $this->createChapter($eCourse, 'Prendre ses repères');
		$this->createLesson($eChapter, 'Constituer son dossier', 18);

		$this->createStaff($eCourse, 'Ada Martin', '');
		$this->createStaff($eCourse, 'Bob Petit', '');

		foreach($this->getFiches($eCourse) as $where => $html) {
			$this->assertTrue(str_contains($html, ' · <b>Bob Petit</b>'), $where.' : sans présentation, les encadrants restent sur une ligne');
		}

		$this->updateSupervisor($eCourse, 'Ada Martin', 'Maraîchère depuis 2012 & formatrice <en> gestion.');

		foreach($this->getFiches($eCourse) as $where => $html) {

			$this->assertFalse(str_contains($html, ' · <b>Bob Petit</b>'), $where.' : avec une présentation, les encadrants ne sont plus sur une ligne');
			$this->assertTrue(str_contains($html, 'Maraîchère depuis 2012 &amp; formatrice &lt;en&gt; gestion.'), $where.' : la présentation d\'Ada, encodée');

		}

	}

	/**
	 * Le référent handicap est retenu sur chaque encadrant, et une présentation au plafond du schéma
	 * (250 caractères) paraît entière.
	 */
	public function testBlocks(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);
		$eChapter = $this->createChapter($eCourse, 'Prendre ses repères');
		$this->createLesson($eChapter, 'Constituer son dossier', 18);

		$description = 'Maraîcher en agriculture biologique dans la Drôme depuis 2009, il accompagne les installations en arboriculture, anime les groupes d’échange sur les vergers et forme aux méthodes de protection sans produits de synthèse, du diagnostic au suivi annuel.';

		$this->assertEquals(250, mb_strlen($description), 'la présentation est au plafond du schéma');

		$this->createStaff($eCourse, 'Ada Martin', $description, handicap: TRUE);
		$this->createStaff($eCourse, 'Bob Petit', '', handicap: TRUE);

		$this->assertEquals([TRUE, TRUE], StaffLib::getByCourse($eCourse)->makeArray(fn(Staff $eStaff) => $eStaff['supervisor']['handicap']), 'Ada et Bob, référents handicap');

		foreach($this->getFiches($eCourse) as $where => $html) {
			$this->assertTrue(str_contains($html, encode($description)), $where.' : la présentation d\'Ada paraît entière');
		}

	}

	/**
	 * @return array<string, string> la fiche et le panneau d'inscription, qui partagent leurs lignes
	 */
	private function getFiches(Course $eCourse): array {

		$data = $this->executeView('formation/'.$eCourse['id'], []);

		if($this->assertTrue($data instanceof \stdClass, 'la fiche se lit') === FALSE) {
			return [];
		}

		return [
			'fiche' => new CourseUi()->getDetails($data->eCourse, $data->cStaff),
			'panneau d\'inscription' => new CourseUi()->getAudience($data->eCourse, $data->cStaff)
		];

	}

	private function createStaff(Course $eCourse, string $name, string $description, bool $handicap = FALSE): void {

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/staff:doCreate', [
			'course' => (string)$eCourse['id'],
			'name' => $name,
			'role' => 'formatrice',
			'description' => $description,
			'email' => ''
		] + ($handicap ? ['handicap' => '1'] : [])), $name.' nommé');

	}

	/**
	 * La présentation de la personne qui encadre la formation sous ce nom, corrigée par le chemin
	 * que prendra son panneau : ce que ses formations disent d'elle posé par `fillCourses()`,
	 * `build()` sur la liste du Lib, puis `update()`.
	 */
	private function updateSupervisor(Course $eCourse, string $name, string $description): void {

		$eSupervisor = StaffLib::getByCourse($eCourse)->find(fn(Staff $eStaff) => $eStaff['supervisor']['name'] === $name, limit: 1)['supervisor'];

		SupervisorLib::fillCourses(new \Collection([$eSupervisor]));

		$fw = new \FailWatch();

		$eSupervisor->build(SupervisorLib::getPropertiesUpdate(), [
			'name' => $name,
			'role' => 'formatrice',
			'description' => $description,
			'email' => ''
		], new \PropertyList('update'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('présentation de '.$name.' refusée : '.json_encode($this->getFails($fw))));

		SupervisorLib::update($eSupervisor, SupervisorLib::getPropertiesUpdate());

	}

}
?>
