<?php
namespace learning;

require_once __DIR__.'/LearningFixture.t.php';

/**
 * La formation en détail (chantier edition-formation, R1) : les enjeux, les objectifs, le public, les
 * prérequis et les trois mentions de l'indicateur 1 de Qualiopi s'écrivent par leur propre panneau,
 * `updateDetails`, et plus par les réglages de la roue. Chacun des deux gestes n'écrit que ce qu'il
 * montre : enregistrer l'un ne vide jamais ce que l'autre a écrit.
 *
 * Ce que le fichier attend de la base, relu par `assertFixtures()` avant chaque test : le groupe de
 * formation G2 (`COMMUNITY_EDUCATION`), que l'utilisateur 1 (`USER_ADMIN`) administre. Les
 * formations s'écrivent par le chemin des panneaux, dans la transaction.
 */
class CourseDetailsTest extends LearningFixtureTest {

	const DETAILS = [
		'description' => '<p>Le contrôle annuel se prépare toute l\'année.</p>',
		'objectives' => '<p>Réunir les pièces demandées au contrôle.</p>',
		'audience' => 'Maraîchers qui démarrent.',
		'prerequisites' => 'Avoir notifié son activité.',
		'access' => 'Inscription en ligne à tout moment.',
		'accessibility' => "Les leçons se lisent sur téléphone.\nLes vidéos sont sous-titrées.",
		'recognition' => 'Attestation de suivi, sans certification.',
	];

	/**
	 * Le panneau écrit les sept champs, puis les vide : un champ vidé repasse à NULL, ce que l'onglet
	 * lit pour dire « Non renseigné ».
	 */
	public function testWrite(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), Course::DRAFT, Course::PRIVATE);

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/course:doUpdateDetails', $this->details($eCourse, self::DETAILS)), 'le détail s\'enregistre');

		$eCourseWritten = $this->getCourse($eCourse['id']);

		foreach(self::DETAILS as $property => $value) {

			if(in_array($property, ['description', 'objectives'], TRUE)) {
				$this->assertTrue(str_contains((string)$eCourseWritten[$property], strip_tags($value)), $property.' est écrit par l\'éditeur : '.$eCourseWritten[$property]);
			} else {
				$this->assertEquals($value, $eCourseWritten[$property], $property.' est écrit');
			}

		}

		$this->assertEquals(s("La fiche technique a bien été enregistrée."), AlertUi::getSuccess('Course::detailsUpdated'), 'le succès a son message');

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/course:doUpdateDetails', $this->details($eCourse, array_fill_keys(array_keys(self::DETAILS), ''))), 'le détail vidé s\'enregistre');

		$this->assertEquals(array_fill_keys(array_keys(self::DETAILS), NULL), $this->getCourse($eCourse['id'])->extracts(array_keys(self::DETAILS)), 'chaque champ vidé repasse à NULL');

	}

	/**
	 * Un champ borné au-delà de sa borne est refusé avec son message, et rien n'est écrit.
	 */
	public function testBounds(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), Course::DRAFT, Course::PRIVATE);

		foreach(['audience', 'prerequisites', 'access', 'accessibility', 'recognition'] as $property) {

			[, $max] = Course::model()->getPropertyRange($property);

			$this->assertEquals([\FailAction::class, 'Course::'.$property.'.check'], $this->executePage('learning/course:doUpdateDetails', $this->details($eCourse, [$property => str_repeat('a', $max + 1)])), $property.' au-delà de '.$max.' caractères est refusé');
			$this->assertTrue(AlertUi::getError('Course::'.$property.'.check') !== NULL, 'le refus de '.$property.' a son message');

		}

		$this->assertEquals(array_fill_keys(array_keys(self::DETAILS), NULL), $this->getCourse($eCourse['id'])->extracts(array_keys(self::DETAILS)), 'rien d\'écrit par les refus');

	}

	/**
	 * Les réglages de la roue ne postent plus le détail, et ne le construisent plus : l'enregistrer
	 * le laisse intact. Avant le lot, `build()` lisait chaque champ absent comme NULL et vidait tout.
	 */
	public function testSettingsKeepDetails(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), Course::DRAFT, Course::PRIVATE);

		$this->executePage('learning/course:doUpdateDetails', $this->details($eCourse, self::DETAILS));

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/course:doUpdateSettings', [
			'id' => (string)$eCourse['id'],
			'name' => 'zzTest formation renommée',
			'summary' => 'Une formation du décor de test.',
			'theme' => (string)$this->eTheme['id'],
			'level' => (string)$this->eLevel['id'],
		]), 'les réglages s\'enregistrent');

		$eCourseWritten = $this->getCourse($eCourse['id']);

		$this->assertEquals('zzTest formation renommée', $eCourseWritten['name'], 'le nom change');

		foreach(['audience', 'prerequisites', 'access', 'accessibility', 'recognition'] as $property) {
			$this->assertEquals(self::DETAILS[$property], $eCourseWritten[$property], $property.' reste');
		}

		$this->assertTrue($eCourseWritten['description'] !== NULL and $eCourseWritten['objectives'] !== NULL, 'les enjeux et les objectifs restent');

	}

	/**
	 * Le panneau et son action demandent le droit de modifier la formation.
	 */
	public function testRefused(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), Course::DRAFT, Course::PRIVATE);

		$this->actAs($this->eUserReader);

		$this->assertFalse($this->getCourse($eCourse['id'])->canUpdate(), 'le lecteur ne modifie pas la formation');
		$this->assertEquals(\NotAllowedAction::class, $this->executeView('learning/course:updateDetails', ['id' => $eCourse['id']]), 'le panneau lui est refusé');
		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('learning/course:doUpdateDetails', $this->details($eCourse, self::DETAILS)), 'l\'action aussi');

		$this->actAs($this->eUserAdmin);

		$this->assertEquals(NULL, $this->getCourse($eCourse['id'])['audience'], 'rien d\'écrit');

	}

	/**
	 * La roue n'a plus que le titre, la présentation, le type et le classement ; le détail a son
	 * panneau.
	 */
	public function testPanels(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), Course::DRAFT, Course::PRIVATE);

		$data = $this->executeView('learning/course:updateSettings', ['id' => $eCourse['id']]);
		$settings = new CourseUi()->updateSettings($data->e)->body;

		foreach(array_keys(self::DETAILS) as $property) {
			$this->assertFalse(str_contains($settings, 'data-wrapper="'.$property.'"'), 'la roue ne porte plus '.$property);
		}

		$this->assertTrue(str_contains($settings, 'data-wrapper="summary"'), 'la roue garde la présentation');

		$data = $this->executeView('learning/course:updateDetails', ['id' => $eCourse['id']]);

		if($this->assertTrue($data instanceof \stdClass, 'le panneau du détail s\'ouvre ('.(is_string($data) ? $data : 'vue').')') === FALSE) {
			return;
		}

		$panel = new CourseUi()->updateDetails($data->e);

		$this->assertEquals('panel-learning-course-details', $panel->id, 'son identifiant');
		$this->assertTrue(str_contains($panel->body, '/learning/course:doUpdateDetails'), 'il poste à son action');

		foreach(array_keys(self::DETAILS) as $property) {
			$this->assertTrue(str_contains($panel->body, 'data-wrapper="'.$property.'"'), 'le panneau porte '.$property);
		}

	}

	/**
	 * Le retour d'une leçon à sa formation mène à l'onglet des leçons, avec le message et l'ancre de
	 * la leçon : un seul `?`, l'ancre en dernier.
	 */
	public function testLeave(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), Course::DRAFT, Course::PRIVATE);
		$eLesson = $this->createLesson($this->createChapter($eCourse, 'Module du test'), 'Leçon du test', 10);

		[$action] = $this->executePage('learning/lesson:leave', ['id' => (string)$eLesson['id'], 'destination' => 'course'], 'GET', $url);

		$this->assertEquals(\RedirectAction::class, $action, 'le retour redirige');
		$this->assertEquals(CourseUi::urlEdition($eCourse).'?tab=lessons&success=learning\\Lesson::updated#lesson-'.$eLesson['id'], $url, 'vers l\'onglet des leçons, l\'ancre en dernier');
		$this->assertEquals(1, substr_count((string)$url, '?'), 'un seul ?');

	}

	private function details(Course $eCourse, array $input): array {
		return $input + ['id' => (string)$eCourse['id']];
	}

}
?>
