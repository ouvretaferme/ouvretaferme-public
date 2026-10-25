<?php
namespace learning;

require_once __DIR__.'/LearningFixture.t.php';

/**
 * Les mentions de l'indicateur 1 de Qualiopi (lot L8a) : les modalités et délais d'accès,
 * l'accessibilité et la reconnaissance d'une formation, le référent handicap parmi ses encadrants,
 * l'objectif de chaque leçon. Tout s'écrit à l'écran, rien n'est exigé à la publication, et l'API
 * les rend sans les écrire (`ApiPropertySentinel`).
 *
 * Ce que le fichier attend de la base, relu par `assertFixtures()` avant chaque test : le réseau
 * G3 du décor commun (`COMMUNITY_NETWORK`), de type `network`, dont `setUp()` fait de
 * l'utilisateur 1 (`USER_ADMIN`) un conseiller IN, et qui porte une adresse légale. Les
 * formations, encadrants et leçons s'écrivent par le chemin des panneaux, dans la transaction.
 */
class CourseMentionTest extends LearningFixtureTest {

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

		if($this->eStructureNetwork['legalEmail'] === '') {
			$this->assertFail('le réseau '.self::COMMUNITY_NETWORK.' n\'a pas d\'adresse légale dans cette base, la ligne Contact ne peut pas se lire');
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
	 * Le panneau du détail écrit les trois mentions, et un champ vidé les remet à NULL : c'est ce que la
	 * fiche lit pour ne pas afficher la ligne. Une valeur hors des bornes du schéma est refusée avec
	 * un message, et chaque refus possible a le sien.
	 */
	public function testCourseMentions(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);

		$mentions = [
			'access' => 'Inscription en ligne à tout moment.',
			'accessibility' => "Les leçons se lisent sur téléphone.\nLes vidéos sont sous-titrées.",
			'recognition' => 'Attestation de suivi, sans certification.',
		];

		$read = fn() => Course::model()->select(array_keys($mentions))->whereId($eCourse)->get()->extracts(array_keys($mentions));

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/course:doUpdateDetails', $this->details($eCourse, $mentions)), 'le détail enregistré avec les mentions');
		$this->assertEquals($mentions, $read(), 'les trois mentions sont écrites');

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/course:doUpdateDetails', $this->details($eCourse, ['access' => '', 'accessibility' => '', 'recognition' => ''])), 'le détail enregistré avec les mentions vidées');
		$this->assertEquals(['access' => NULL, 'accessibility' => NULL, 'recognition' => NULL], $read(), 'une mention vidée repasse à NULL');

		foreach(['access', 'accessibility', 'recognition'] as $property) {

			[, $max] = Course::model()->getPropertyRange($property);

			$this->assertEquals([\FailAction::class, 'Course::'.$property.'.check'], $this->executePage('learning/course:doUpdateDetails', $this->details($eCourse, [$property => str_repeat('a', $max + 1)])), $property.' au-delà de '.$max.' caractères est refusé');
			$this->assertTrue(AlertUi::getError('Course::'.$property.'.check') !== NULL, 'le refus de '.$property.' a son message');

		}

		$this->assertEquals(['access' => NULL, 'accessibility' => NULL, 'recognition' => NULL], $read(), 'rien d\'écrit par les refus');

		$this->assertEquals([], array_values(array_intersect(['access', 'accessibility', 'recognition'], CourseLib::getPropertiesUpdate('api')($eCourse))), 'l\'API ne les écrit pas');

	}

	/**
	 * La case cochée fait l'encadrant référent handicap, sur sa personne ; une case non cochée n'est
	 * pas postée, et c'est `build()` qui doit lire cette absence comme un « non ». Le panneau d'une
	 * formation ne retire plus la marque : elle se règle sur la personne.
	 */
	public function testStaffHandicap(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/staff:doCreate', ['course' => (string)$eCourse['id'], 'name' => 'Claire Moulin', 'role' => 'autrice', 'description' => '', 'handicap' => '1']), 'encadrant nommé, case cochée');

		$eStaff = StaffLib::getByCourse($eCourse)->first();

		$this->assertTrue($eStaff['supervisor']['handicap'], 'il est référent handicap');

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/staff:doUpdate', ['id' => (string)$eStaff['id'], 'role' => 'autrice']), 'encadrant modifié dans la formation, sans rien poster de la marque');
		$this->assertTrue(Supervisor::model()->select('handicap')->whereId($eStaff['supervisor'])->get()['handicap'], 'il l\'est toujours');

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/staff:doCreate', ['course' => (string)$eCourse['id'], 'name' => 'Julien Roux', 'role' => 'relecteur', 'description' => '']), 'encadrant nommé sans cocher');
		$this->assertFalse(StaffLib::getByCourse($eCourse)->find(fn(Staff $eStaff) => $eStaff['supervisor']['name'] === 'Julien Roux', limit: 1)['supervisor']['handicap'], 'il n\'est pas référent handicap');

	}

	/**
	 * L'objectif s'écrit par les réglages de la leçon, se vide à NULL, et reste hors de l'écriture
	 * par l'API.
	 */
	public function testLessonObjective(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);
		$eChapter = $this->createChapter($eCourse, 'Prendre ses repères');
		$eLesson = $this->createLesson($eChapter, 'Constituer son dossier', 18);

		$settings = [
			'id' => (string)$eLesson['id'],
			'chapter' => (string)$eChapter['id'],
			'name' => 'Constituer son dossier',
			'minutes' => '18',
			'validation' => Lesson::DECLARED,
			'quizThreshold' => ''
		];

		$read = fn() => Lesson::model()->select('objective')->whereId($eLesson)->get()['objective'];

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/lesson:doUpdateSettings', ['objective' => 'Constituer son dossier de notification'] + $settings), 'réglages enregistrés avec un objectif');
		$this->assertEquals('Constituer son dossier de notification', $read(), 'l\'objectif est écrit');

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/lesson:doUpdateSettings', ['objective' => ''] + $settings), 'réglages enregistrés avec l\'objectif vidé');
		$this->assertEquals(NULL, $read(), 'un objectif vidé repasse à NULL');

		[, $max] = Lesson::model()->getPropertyRange('objective');

		$this->assertEquals([\FailAction::class, 'Lesson::objective.check'], $this->executePage('learning/lesson:doUpdateSettings', ['objective' => str_repeat('a', $max + 1)] + $settings), 'un objectif trop long est refusé');
		$this->assertTrue(AlertUi::getError('Lesson::objective.check') !== NULL, 'le refus a son message');

		$this->assertFalse(in_array('objective', LessonLib::getPropertiesUpdate('api'), TRUE), 'l\'API ne l\'écrit pas');

	}

	/**
	 * La fiche lue par la route de la formation : les mentions saisies, « Sans prérequis » quand le
	 * champ est vide, le référent handicap après le rôle, le contact du réseau, l'objectif de chaque
	 * leçon au programme. Le panneau d'inscription reprend les mêmes mentions sans la date de mise à
	 * jour.
	 */
	public function testFiche(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourseEmpty = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);
		$eChapter = $this->createChapter($eCourseEmpty, 'Prendre ses repères');
		$this->createLesson($eChapter, 'Constituer son dossier', 18);

		$contact = encode($this->eStructureNetwork['legalEmail']);

		$data = $this->executeView('formation/'.$eCourseEmpty['id'], []);

		if($this->assertTrue($data instanceof \stdClass, 'la fiche vide se lit') === FALSE) {
			return;
		}

		$details = new CourseUi()->getDetails($data->eCourse, $data->cStaff);

		$this->assertTrue(str_contains($details, 'Sans prérequis'), 'la fiche vide dit « Sans prérequis »');
		$this->assertTrue(str_contains($details, 'mailto:'.$contact), 'et le contact du réseau');

		$eCourseFull = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);
		$eChapter = $this->createChapter($eCourseFull, 'Prendre ses repères');
		$eLesson = $this->createLesson($eChapter, 'Constituer son dossier', 18);

		$this->executePage('learning/course:doUpdateDetails', $this->details($eCourseFull, [
			'audience' => 'Producteurs en conversion.',
			'prerequisites' => 'Avoir notifié son activité.',
			'access' => 'Inscription en ligne à tout moment.',
			'accessibility' => "Les leçons se lisent sur téléphone.\nLes vidéos sont sous-titrées.",
			'recognition' => 'Attestation de suivi, sans certification.',
		]));
		$this->executePage('learning/staff:doCreate', ['course' => (string)$eCourseFull['id'], 'name' => 'Claire Moulin', 'role' => 'autrice', 'description' => '', 'handicap' => '1']);
		$this->executePage('learning/staff:doCreate', ['course' => (string)$eCourseFull['id'], 'name' => 'Julien Roux', 'role' => 'relecteur', 'description' => '']);
		$this->executePage('learning/lesson:doUpdateSettings', [
			'id' => (string)$eLesson['id'],
			'chapter' => (string)$eChapter['id'],
			'name' => 'Constituer son dossier',
			'objective' => 'Réunir les pièces du dossier de notification',
			'minutes' => '18',
			'validation' => Lesson::DECLARED,
			'quizThreshold' => ''
		]);

		$data = $this->executeView('formation/'.$eCourseFull['id'], []);

		if($this->assertTrue($data instanceof \stdClass, 'la fiche remplie se lit') === FALSE) {
			return;
		}

		$details = new CourseUi()->getDetails($data->eCourse, $data->cStaff);

		foreach(['Avoir notifié son activité.', 'Inscription en ligne à tout moment.', 'Les leçons se lisent sur téléphone.', 'Attestation de suivi, sans certification.', 'autrice, référent handicap', 'relecteur', 'mailto:'.$contact, 'Dernière mise à jour'] as $expected) {
			$this->assertTrue(str_contains($details, $expected), 'la fiche remplie porte « '.$expected.' »');
		}

		$this->assertFalse(str_contains($details, 'Sans prérequis'), 'des prérequis écrits remplacent « Sans prérequis »');
		$this->assertFalse(str_contains($details, 'relecteur, référent'), 'le second encadrant n\'est pas référent');

		$this->assertTrue(str_contains(new CourseUi()->getProgram($data->eCourse, $data->cChapter, $data->cProgress), 'Réunir les pièces du dossier de notification'), 'le programme porte l\'objectif de la leçon');

		$audience = new CourseUi()->getAudience($data->eCourse, $data->cStaff);

		foreach(['Producteurs en conversion.', 'Inscription en ligne à tout moment.', 'Attestation de suivi, sans certification.', 'référent handicap', 'mailto:'.$contact] as $expected) {
			$this->assertTrue(str_contains($audience, $expected), 'le panneau d\'inscription porte « '.$expected.' »');
		}

		$this->assertFalse(str_contains($audience, 'Dernière mise à jour'), 'mais pas la date de mise à jour');

		$eCourseTeam = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE);

		$eCourseTeam = $this->getCourse($eCourseTeam['id']);
		$eCourseTeam['eSupervisorContact'] = SupervisorLib::getContactByCourse($eCourseTeam);

		$this->assertFalse(str_contains(new CourseUi()->getDetails($eCourseTeam, new \Collection()), 'mailto:'), 'une formation de l\'équipe n\'a pas de contact');

	}

	/**
	 * Le panneau du détail ne poste que ses propres champs : l'identifiant suffit à côté.
	 */
	private function details(Course $eCourse, array $input): array {
		return $input + ['id' => (string)$eCourse['id']];
	}

}
?>
