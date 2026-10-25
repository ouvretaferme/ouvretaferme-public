<?php
namespace learning;

require_once __DIR__.'/LearningDateFixture.t.php';

/**
 * La feuille d'émargement d'une date en présentiel (lot L8e-1) : quand elle s'édite, quelles
 * colonnes elle porte, et qui elle nomme.
 *
 * Le décor est celui des dates (`LearningDateFixture.t.php`), écrit dans la transaction de chaque
 * test. Le PDF lui-même ne se rend pas ici : il passe par le navigateur sans tête, que la recette
 * éprouve. Le test lit le HTML que la page signée donne à ce navigateur.
 *
 * Lancement : dev/test package=learning file=Sheet
 */
class SheetTest extends LearningDateFixtureTest {

	/**
	 * Une colonne par demi-journée, les mêmes chaque jour : le matin quand la date commence avant
	 * midi, l'après-midi quand elle finit après 13:00.
	 */
	public function testHalfDays(): void {

		$halfDays = fn(string $startAt, string $endAt) => DateLib::getHalfDays($this->getOnsite($startAt, $endAt));

		$this->assertEquals([
			'2026-11-07' => [DateLib::MORNING, DateLib::AFTERNOON],
			'2026-11-08' => [DateLib::MORNING, DateLib::AFTERNOON],
		], $halfDays('2026-11-07 09:30:00', '2026-11-08 17:00:00'), 'deux journées pleines, quatre colonnes');

		$this->assertEquals(['2026-11-17' => [DateLib::AFTERNOON]], $halfDays('2026-11-17 14:00:00', '2026-11-17 16:30:00'), 'un après-midi seul');
		$this->assertEquals(['2026-11-17' => [DateLib::MORNING]], $halfDays('2026-11-17 08:00:00', '2026-11-17 11:30:00'), 'un matin seul');
		$this->assertEquals(['2026-11-17' => [DateLib::MORNING]], $halfDays('2026-11-17 09:00:00', '2026-11-17 13:00:00'), 'finir à 13:00, c\'est finir le matin');

		// Une date sur l'heure du déjeuner n'est ni un matin ni un après-midi : elle garde une colonne.
		$this->assertEquals(['2026-11-17' => [DateLib::AFTERNOON]], $halfDays('2026-11-17 12:15:00', '2026-11-17 12:45:00'), 'une date entre midi et 13:00');

		$this->assertEquals(
			['2026-10-24', '2026-10-25', '2026-10-26'],
			array_keys($halfDays('2026-10-24 09:00:00', '2026-10-26 17:00:00')),
			'trois jours comptés sur le calendrier, passage à l\'heure d\'hiver compris'
		);

		// Lot date-mixte : les colonnes ne suivent que les séances en présentiel, qu'on signe ; un
		// matin et un après-midi le même jour en font deux.
		$eDateMixed = new Date(['cDateSequence' => new \Collection([
			new DateSequence(['startAt' => '2026-11-17 09:30:00', 'endAt' => '2026-11-17 12:30:00', 'mode' => DateSequence::ONSITE, 'place' => 'Crest']),
			new DateSequence(['startAt' => '2026-11-17 14:00:00', 'endAt' => '2026-11-17 17:00:00', 'mode' => DateSequence::ONSITE, 'place' => 'Die']),
			new DateSequence(['startAt' => '2026-11-18 09:30:00', 'endAt' => '2026-11-18 12:30:00', 'mode' => DateSequence::REMOTE, 'place' => NULL]),
		])]);

		$this->assertEquals(['2026-11-17' => [DateLib::MORNING, DateLib::AFTERNOON]], DateLib::getHalfDays($eDateMixed), 'la visio du lendemain n\'a pas de colonne');

	}

	/**
	 * La feuille s'édite pour une date en présentiel planifiée qui a au moins une personne confirmée,
	 * avant comme après la date : le réseau l'imprime la veille, et la réédite après coup.
	 */
	public function testAccept(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);

		$eDate = $this->createDate($eCourse, 10);
		$this->assertFalse($this->prepareDate($eDate)->acceptSheet(), 'sans personne confirmée, rien à signer');

		$this->enrollRequested($eDate, $this->createPerson());
		$this->assertFalse($this->prepareDate($eDate)->acceptSheet(), 'une demande à confirmer ne compte pas');

		$this->enrollConfirmed($eDate, $this->createPerson());
		$this->assertTrue($this->prepareDate($eDate)->acceptSheet(), 'une date à venir, une personne confirmée');

		$eDate = $this->moveDate($eDate, -5);
		$this->assertTrue($this->prepareDate($eDate)->acceptSheet(), 'une date passée se réédite');

		$eDateRemote = $this->prepareDate($eDate);
		foreach($eDateRemote['cDateSequence'] as $eDateSequence) {
			$eDateSequence['mode'] = DateSequence::REMOTE;
		}
		$this->assertFalse($eDateRemote->acceptSheet(), 'une visio ne s\'émarge pas');

		$eDateCanceled = $this->createDate($eCourse, 3);
		$this->enrollConfirmed($eDateCanceled, $this->createPerson());
		DateLib::cancel(DateLib::getById($eDateCanceled['id']));

		$eDateCanceled = $this->prepareDate($eDateCanceled);
		$this->assertEquals(Date::CANCELED, $eDateCanceled['status'], 'la date est annulée');
		$this->assertFalse($eDateCanceled->acceptSheet(), 'une date annulée ne s\'émarge pas');

		// L'annulation de la date annule ses lignes : l'état seul se juge sur une date qui compte encore une confirmée.
		$eDateCanceled = $this->prepareDate($eDate);
		$eDateCanceled['status'] = Date::CANCELED;
		$this->assertFalse($eDateCanceled->acceptSheet(), 'annulée, même avec une personne confirmée');

	}

	/**
	 * La route juge le droit puis l'état, avant de signer l'adresse du PDF : une personne inscrite
	 * n'y accède pas, le réseau n'obtient rien sur une date sans personne confirmée.
	 */
	public function testRoute(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);

		$eDate = $this->createDate($eCourse, 10);
		$eUser = $this->createPerson();
		$this->enrollRequested($eDate, $eUser);

		$this->actAs($this->eUserAdmin);
		$this->assertEquals(\NotAllowedAction::class, $this->executeView('formation/date/'.$eDate['id'].'/emargement', []), 'une demande à confirmer, pas de feuille');

		$this->actAs($eUser);
		$this->assertEquals(\NotAllowedAction::class, $this->executeView('formation/date/'.$eDate['id'].'/emargement', []), 'la personne inscrite n\'édite pas la feuille');

	}

	/**
	 * Les seules personnes confirmées, rangées par nom puis par prénom, la ligne sans compte nommée
	 * par sa saisie ; trois lignes vides pour qui vient sans être inscrit ; les encadrants à la fin.
	 */
	public function testRender(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);

		$eDate = $this->createDate($eCourse, 10);
		$eDate = $this->extendDate($eDate, 11);

		$this->enrollConfirmed($eDate, $this->createNamed('Zoé', 'Blanc'));
		$this->enrollConfirmed($eDate, $this->createNamed('Anne', 'Martin'));
		$this->enrollConfirmed($eDate, $this->createNamed('Élise', 'Blanc'));
		$this->enrollPublicConfirmed($eDate, 'zztest.emargement-public@example.org');

		$this->enrollRequested($eDate, $this->createNamed('Demande', 'Aconfirmer'));
		DateEnrollmentLib::cancel($this->enrollConfirmed($eDate, $this->createNamed('Annule', 'Parlui')), DateEnrollment::USER);

		$cDateEnrollment = DateEnrollmentLib::getConfirmedByDate($eDate);

		$this->assertEquals(
			['Élise Blanc', 'Zoé Blanc', 'Anne Martin', 'Zztest Public'],
			$cDateEnrollment->makeArray(fn(DateEnrollment $e) => $e->getFirstName().' '.$e->getLastName()),
			'les confirmées seules, par nom puis par prénom'
		);

		$this->createStaff($eCourse, 'Julie Fabre', 'formatrice');
		$this->createStaff($eCourse, 'Claire Moulin', 'conseillère bio');

		$eDate = DateLib::getById($eDate['id']);
		$cStaff = StaffLib::getByCourse($eCourse);
		$eStructureCommunity = \structure\StructureLib::getById($eCourse['community']);

		$sheet = new SheetUi()->get($eDate, $cDateEnrollment, $cStaff, $eStructureCommunity, DateLib::getHalfDays($eDate), '2026-11-06');

		$this->assertEquals(4, substr_count($sheet, '<col class="pdf-sheet-sign">'), 'deux jours de 09:00 à 17:00, quatre demi-journées');
		$this->assertEquals(2, substr_count($sheet, '>'.s("Matin").'</th>'), 'un matin par jour');

		foreach(['Élise Blanc', 'Zoé Blanc', 'Anne Martin', 'Zztest Public'] as $name) {
			$this->assertTrue(str_contains($sheet, '<b>'.encode($name).'</b>'), $name.' est sur la feuille');
		}

		$this->assertTrue(strpos($sheet, 'Élise Blanc') < strpos($sheet, 'Zoé Blanc') and strpos($sheet, 'Zoé Blanc') < strpos($sheet, 'Anne Martin'), 'dans l\'ordre de la liste');
		$this->assertFalse(str_contains($sheet, 'Aconfirmer'), 'pas de demande à confirmer');
		$this->assertFalse(str_contains($sheet, 'Parlui'), 'pas de ligne annulée');

		$this->assertTrue(str_contains($sheet, s("À titre personnel")), 'une personne inscrite à titre personnel');
		$this->assertEquals(3, substr_count($sheet, '<tr><td></td>'), 'trois lignes vides pour les non inscrits');

		$group = fn(string $title) => '<tr class="pdf-sheet-group"><td colspan="5">'.$title.'</td></tr>';

		$this->assertTrue(str_contains($sheet, '<th rowspan="2">'.s("Nom").'</th>'), 'la colonne des noms vaut pour les trois groupes');

		// Chaque titre d'abord, sinon un `strpos()` à FALSE passerait pour la première position ci-dessous.
		foreach([s("Stagiaires"), s("Non inscrits"), s("Encadrants")] as $title) {
			$this->assertEquals(1, substr_count($sheet, $group($title)), $title.' titre son groupe');
		}

		$this->assertTrue(
			strpos($sheet, $group(s("Stagiaires"))) < strpos($sheet, 'Élise Blanc')
			and strpos($sheet, 'Anne Martin') < strpos($sheet, $group(s("Non inscrits")))
			and strpos($sheet, $group(s("Non inscrits"))) < strpos($sheet, '<tr><td></td>')
			and strpos($sheet, $group(s("Encadrants"))) < strpos($sheet, 'Claire Moulin</b>'),
			'trois groupes titrés de la même façon, chacun devant ses lignes (recette du 01/10)'
		);

		$this->assertTrue(str_contains($sheet, 'Encadrée par Julie Fabre, formatrice et Claire Moulin, conseillère bio.'), 'les encadrants en tête');
		$this->assertTrue(str_contains($sheet, '<b>Claire Moulin</b><div class="util-annotation">Conseillère bio</div>'), 'et leur ligne à signer');

		$sheetAlone = new SheetUi()->get($eDate, $cDateEnrollment, new \Collection(), $eStructureCommunity, DateLib::getHalfDays($eDate), '2026-11-06');

		$this->assertFalse(str_contains($sheetAlone, 'pdf-lede'), 'sans encadrant, pas de phrase');
		$this->assertFalse(str_contains($sheetAlone, $group(s("Encadrants"))), 'ni de groupe');
		$this->assertTrue(str_contains($sheetAlone, $group(s("Non inscrits"))), 'les non inscrits restent');

		// La figure des dates sans le nom des jours, que portent les colonnes : elle tient sur une ligne.
		$figure = fn(string $startAt, string $endAt) => new SheetUi()->get($this->getOnsite($startAt, $endAt, $eDate['course']), $cDateEnrollment, new \Collection(), $eStructureCommunity, [], '2026-11-06');

		$this->assertTrue(str_contains($figure('2026-11-07 09:30:00', '2026-11-08 17:00:00'), '>7 et 8 novembre 2026<'), 'deux jours du même mois');
		$this->assertTrue(str_contains($figure('2026-10-31 09:30:00', '2026-11-02 17:00:00'), '>Du 31 octobre au 2 novembre 2026<'), 'trois jours sur deux mois');
		$this->assertTrue(str_contains($figure('2026-11-17 14:00:00', '2026-11-17 16:30:00'), '>17 novembre 2026<'), 'un seul jour');

		// Une séance reprise d'une date écrite avant que le lieu soit exigé n'en porte pas : la figure du lieu disparaît, plutôt que de rester vide.
		$eDateWithoutPlace = $this->getOnsite('2026-11-17 14:00:00', '2026-11-17 16:30:00', $eDate['course']);
		$eDateWithoutPlace['cDateSequence']->first()['place'] = NULL;
		$sheetWithoutPlace = new SheetUi()->get($eDateWithoutPlace, $cDateEnrollment, new \Collection(), $eStructureCommunity, [], '2026-11-06');

		$this->assertTrue(str_contains($figure('2026-11-17 14:00:00', '2026-11-17 16:30:00'), '>'.s("Lieu").'<'), 'une séance avec lieu a sa figure');
		$this->assertFalse(str_contains($sheetWithoutPlace, '>'.s("Lieu").'<'), 'une séance sans lieu n\'en a pas');

	}

	/**
	 * Une date en présentiel à la Salle des fêtes de Crest, construite sans être écrite : une séance
	 * par jour, de l'heure de `$startAt` à celle de `$endAt`.
	 */
	private function getOnsite(string $startAt, string $endAt, Course $eCourse = new Course()): Date {

		$cDateSequence = new \Collection();

		for($day = substr($startAt, 0, 10); $day <= substr($endAt, 0, 10); $day = date('Y-m-d', strtotime($day.' + 1 DAY'))) {
			$cDateSequence[] = new DateSequence([
				'startAt' => $day.' '.substr($startAt, 11),
				'endAt' => $day.' '.substr($endAt, 11),
				'mode' => DateSequence::ONSITE,
				'place' => 'Salle des fêtes, Crest',
			]);
		}

		return new Date(['course' => $eCourse, 'startAt' => $startAt, 'endAt' => $endAt, 'cDateSequence' => $cDateSequence]);

	}

	/**
	 * Une personne de la ferme rattachée, nommée pour que l'ordre de la feuille se lise.
	 */
	private function createNamed(string $firstName, string $lastName): \user\User {

		$eUser = $this->createPerson();
		$eUser['firstName'] = $firstName;
		$eUser['lastName'] = $lastName;

		// Sans `build()` : il ne vérifierait que la longueur des noms et la présence du prénom d'un compte
		// public, que des noms écrits en dur respectent, et le décor veut exactement ces valeurs.
		\user\UserLib::update($eUser, ['firstName', 'lastName']);

		return $eUser;

	}

	private function createStaff(Course $eCourse, string $name, string $role): void {

		$this->actAs($this->eUserAdmin);

		$result = $this->executePage('learning/staff:doCreate', [
			'course' => (string)$eCourse['id'],
			'name' => $name,
			'role' => $role,
			'description' => '',
			'email' => ''
		]);

		$this->assertEquals([\ReloadAction::class, NULL], $result, 'encadrant créé');

	}

}
?>
