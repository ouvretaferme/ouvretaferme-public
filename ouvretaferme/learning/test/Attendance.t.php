<?php
namespace learning;

require_once __DIR__.'/LearningDateFixture.t.php';

/**
 * Le pointage des présences d'une date (lot L8e-2) : sur quelle date et quelle ligne il se propose,
 * pointer et dépointer, les heures corrigées d'une personne, la durée de la date corrigée après
 * coup, et la liste des inscrits qui n'en montre les colonnes que sur une date passée.
 *
 * Le décor est celui de `LearningDateFixtureTest`.
 *
 * Lancement : dev/test package=learning file=Attendance
 */
class AttendanceTest extends LearningDateFixtureTest {

	/**
	 * Le pointage se propose sur une date planifiée passée, à une personne confirmée seulement.
	 */
	public function testAccept(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);

		$eDate = $this->createDate($eCourse, 2);
		$eDateEnrollment = $this->enrollConfirmed($eDate, $this->createPerson());

		$this->assertFalse($this->prepareDate($eDate)->acceptAttendance(), 'une date à venir ne se pointe pas');
		$this->assertFalse($this->getLine($eDateEnrollment['id'])->acceptAttend(), 'ni ses personnes');

		$eDate = $this->moveDate($eDate, 0, '09:00:00');
		$this->assertFalse($this->prepareDate($eDate)->acceptAttendance(), 'une date du jour qui n\'est pas finie ne se pointe pas');

		$eDate = $this->moveDate($eDate, -3);
		$this->assertTrue($this->prepareDate($eDate)->acceptAttendance(), 'une date passée se pointe');
		$this->assertTrue($this->getLine($eDateEnrollment['id'])->acceptAttend(), 'une personne confirmée se pointe');
		$this->assertFalse($this->prepareDate($eDate)->acceptUpdate(), 'la date passée ne se modifie plus pour autant');

		$eDateEnrollmentRequested = $this->enrollRequested($this->createDate($eCourse, 4), $this->createPerson());
		$this->moveDate($eDateEnrollmentRequested['date'], -3);
		$this->assertFalse($this->getLine($eDateEnrollmentRequested['id'])->acceptAttend(), 'une demande à confirmer ne se pointe pas');

		$eDateCanceled = $this->createDate($eCourse, 5);
		$eDateEnrollmentCanceled = $this->enrollConfirmed($eDateCanceled, $this->createPerson());
		DateLib::cancel(DateLib::getById($eDateCanceled['id']));
		$eDateCanceled = $this->moveDate($eDateCanceled, -3);
		$this->assertFalse($this->prepareDate($eDateCanceled)->acceptAttendance(), 'une date annulée ne se pointe pas');
		$this->assertFalse($this->getLine($eDateEnrollmentCanceled['id'])->acceptAttend(), 'ni ses personnes');

	}

	/**
	 * Pointer écrit qui et quand, dépointer efface les trois colonnes ; les deux routes refusent une
	 * date à venir et une personne non confirmée, que l'écran ne propose pas mais qu'un POST atteint.
	 */
	public function testAttend(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);

		$eDate = $this->createDate($eCourse, 2);
		$eDateEnrollment = $this->enrollConfirmed($eDate, $this->createPerson());
		$this->enrollConfirmed($eDate, $this->createPerson());

		$this->actAs($this->eUserAdmin);

		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/dateEnrollment:doAttend', ['id' => (string)$eDateEnrollment['id']]), 'refusé sur une date à venir');
		$this->assertEquals(NULL, $this->getLine($eDateEnrollment['id'])['attendedAt'], 'rien d\'écrit');

		$eDate = $this->moveDate($eDate, -2);

		$this->setClock(\Clock::date().' 14:30:00');

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('learning/dateEnrollment:doAttend', ['id' => (string)$eDateEnrollment['id']]), 'pointée');

		$eDateEnrollment = $this->getLine($eDateEnrollment['id']);
		$this->assertEquals(\Clock::date().' 14:30:00', $eDateEnrollment['attendedAt'], 'l\'heure du pointage');
		$this->assertEquals($this->eUserAdmin['id'], $eDateEnrollment['attendedBy']['id'], 'qui a pointé');

		$eDate = $this->prepareDate($eDate);
		DateEnrollmentLib::fillAttendedByDate($eDate);
		$this->assertEquals(1, $eDate['attended'], 'une présente sur deux');

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('@module/learning/DateEnrollment/doQuick', ['id' => (string)$eDateEnrollment['id'], 'property' => 'attendedMinutes', 'attendedMinutes' => '2']), 'heures corrigées');
		$this->assertEquals(120, $this->getLine($eDateEnrollment['id'])['attendedMinutes'], 'deux heures');

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('learning/dateEnrollment:doUnattend', ['id' => (string)$eDateEnrollment['id']]), 'dépointée');

		$eDateEnrollment = $this->getLine($eDateEnrollment['id']);
		$this->assertEquals(NULL, $eDateEnrollment['attendedAt'], 'plus d\'heure de pointage');
		$this->assertTrue($eDateEnrollment['attendedBy']->empty(), 'plus personne');
		$this->assertEquals(NULL, $eDateEnrollment['attendedMinutes'], 'les heures corrigées partent avec le pointage');

		$eDateRequested = $this->createDate($eCourse, 3);
		$eDateEnrollmentRequested = $this->enrollRequested($eDateRequested, $this->createPerson());
		$this->moveDate($eDateRequested, -2);

		$this->actAs($this->eUserAdmin);
		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/dateEnrollment:doAttend', ['id' => (string)$eDateEnrollmentRequested['id']]), 'refusé sur une demande à confirmer');
		$this->assertEquals(NULL, $this->getLine($eDateEnrollmentRequested['id'])['attendedAt'], 'rien d\'écrit sur la demande');

	}

	/**
	 * Les heures d'une personne : pas avant son pointage, en heures et en virgule, pas plus que la
	 * durée de la date, et vides elles suivent la durée de la date, même changée après coup.
	 */
	public function testAttendedMinutes(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);

		$eDate = $this->createDate($eCourse, 2);
		$eDateEnrollment = $this->enrollConfirmed($eDate, $this->createPerson());
		$eDate = $this->moveDate($eDate, -2, '10:00:00');

		$this->actAs($this->eUserAdmin);

		$quick = fn(string $value) => $this->executePage('@module/learning/DateEnrollment/doQuick', ['id' => (string)$eDateEnrollment['id'], 'property' => 'attendedMinutes', 'attendedMinutes' => $value]);

		$this->assertEquals([\NotExpectedAction::class, NULL], $quick('2'), 'pas d\'heures sans pointage');
		$this->assertEquals(NULL, $this->getLine($eDateEnrollment['id'])['attendedMinutes'], 'rien d\'écrit avant le pointage');

		DateEnrollmentLib::attend($this->getLine($eDateEnrollment['id']));

		$this->assertEquals(13 * 60, $this->getLine($eDateEnrollment['id'])->getAttendedMinutes(), 'sans correction, la durée des horaires, de 10:00 à 23:00');

		$this->assertEquals([\ViewAction::class, NULL], $quick('6,5'), 'heures à la virgule');
		$this->assertEquals(390, $this->getLine($eDateEnrollment['id'])['attendedMinutes'], 'six heures et demie');

		$this->assertEquals([\FailAction::class, 'DateEnrollment::attendedMinutes.tooHigh'], $quick('13,5'), 'plus que la durée de la date');
		$this->assertEquals([\FailAction::class, 'DateEnrollment::attendedMinutes.check'], $quick('0'), 'zéro heure');
		$this->assertEquals([\FailAction::class, 'DateEnrollment::attendedMinutes.hours'], $quick('deux'), 'pas un nombre');
		$this->assertEquals([\FailAction::class, 'DateEnrollment::attendedMinutes.hours'], $quick('45 min'), 'un nombre suivi d\'une unité n\'est pas lu comme des minutes');
		$this->assertEquals(390, $this->getLine($eDateEnrollment['id'])['attendedMinutes'], 'les refus n\'écrivent rien');

		$this->assertEquals([\ViewAction::class, NULL], $quick('13'), 'toute la durée');

		$this->assertEquals([\ViewAction::class, NULL], $quick(''), 'vidées');
		$this->assertEquals(NULL, $this->getLine($eDateEnrollment['id'])['attendedMinutes'], 'vide en base');

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('@module/learning/Date/doQuick', ['id' => (string)$eDate['id'], 'property' => 'minutes', 'minutes' => '7']), 'durée de la date corrigée');
		$this->assertEquals(420, $this->getLine($eDateEnrollment['id'])->getAttendedMinutes(), 'les heures vides suivent la durée corrigée');

		$this->assertEquals([\FailAction::class, 'DateEnrollment::attendedMinutes.tooHigh'], $quick('8'), 'le plafond suit la durée corrigée');

	}

	/**
	 * La durée d'une date : celle des horaires par jour, sinon celle saisie ; corrigée sur une date
	 * passée alors que la date ne se modifie plus, jamais sur une date à venir par cette route.
	 */
	public function testDateMinutes(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);

		$eDate = $this->createDate($eCourse, 2);
		$this->assertEquals(14 * 60, $this->prepareDate($eDate)->getMinutes(), 'une journée de 09:00 à 23:00');

		$eDate = $this->extendDate($eDate, 4);
		$this->assertEquals(3 * 8 * 60, $this->prepareDate($eDate)->getMinutes(), 'trois journées de 09:00 à 17:00');

		$this->actAs($this->eUserAdmin);

		$quick = fn(string $value) => $this->executePage('@module/learning/Date/doQuick', ['id' => (string)$eDate['id'], 'property' => 'minutes', 'minutes' => $value]);

		$this->assertEquals([\NotExpectedAction::class, NULL], $quick('3'), 'pas par l\'édition rapide sur une date à venir');

		$eDate = $this->moveDate($eDate, -3);

		$this->assertEquals([\ViewAction::class, NULL], $quick('3,5'), 'corrigée après la date');
		$this->assertEquals(210, $this->prepareDate($eDate)['minutes'], 'trois heures et demie');
		$this->assertEquals(210, $this->prepareDate($eDate)->getMinutes(), 'la durée saisie l\'emporte sur les horaires');

		$this->assertEquals([\FailAction::class, 'Date::minutes.check'], $quick('0,25'), 'moins d\'une demi-heure');
		$this->assertEquals([\FailAction::class, 'Date::minutes.hours'], $quick('3 h'), 'pas un nombre');

		$this->assertEquals([\ViewAction::class, NULL], $quick(''), 'vidée');
		$this->assertEquals(14 * 60, $this->prepareDate($eDate)->getMinutes(), 'retour aux horaires');

	}

	/**
	 * La durée d'une date ne descend pas sous les heures saisies les plus longues d'une personne ; les
	 * présences sans heures saisies suivent la durée et ne retiennent rien.
	 */
	public function testDateMinutesUnderAttendance(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);

		$eDate = $this->createDate($eCourse, 2);
		$eDateEnrollmentLong = $this->enrollConfirmed($eDate, $this->createPerson());
		$eDateEnrollmentShort = $this->enrollConfirmed($eDate, $this->createPerson());
		$eDateEnrollmentFollowing = $this->enrollConfirmed($eDate, $this->createPerson());
		$eDate = $this->moveDate($eDate, -2);

		$this->actAs($this->eUserAdmin);

		foreach([$eDateEnrollmentLong, $eDateEnrollmentShort, $eDateEnrollmentFollowing] as $eDateEnrollment) {
			DateEnrollmentLib::attend($this->getLine($eDateEnrollment['id']));
		}

		$attendedMinutes = fn(DateEnrollment $eDateEnrollment, string $value) => $this->executePage('@module/learning/DateEnrollment/doQuick', ['id' => (string)$eDateEnrollment['id'], 'property' => 'attendedMinutes', 'attendedMinutes' => $value]);
		$this->assertEquals([\ViewAction::class, NULL], $attendedMinutes($eDateEnrollmentLong, '6,5'), 'six heures et demie');
		$this->assertEquals([\ViewAction::class, NULL], $attendedMinutes($eDateEnrollmentShort, '4'), 'quatre heures');

		$quick = fn(string $value) => $this->executePage('@module/learning/Date/doQuick', ['id' => (string)$eDate['id'], 'property' => 'minutes', 'minutes' => $value]);

		$this->assertEquals([\FailAction::class, 'Date::minutes.attended'], $quick('6'), 'sous les heures les plus longues');
		$this->assertEquals(NULL, $this->prepareDate($eDate)['minutes'], 'le refus n\'écrit rien');

		$message = new AlertUi()->getError('Date::minutes.attended', ['eDateEnrollment' => DateEnrollmentLib::getLongestAttendanceByDate($eDate)]);
		$this->assertTrue(str_contains($message, $this->getLine($eDateEnrollmentLong['id'])->getName()), 'le message nomme la personne');
		$this->assertTrue(str_contains($message, '6&nbsp;h&nbsp;30'), 'le message dit ses heures');

		$this->assertEquals([\ViewAction::class, NULL], $quick('6,5'), 'à la limite');
		$this->assertEquals(390, $this->prepareDate($eDate)['minutes'], 'six heures et demie, que suit la présence sans heures saisies');

		$this->assertEquals([\ViewAction::class, NULL], $quick(''), 'vidée, la durée des horaires couvre les heures saisies');

	}

	/**
	 * Les colonnes Présence et Heures ne sortent que sur une date passée, pour les seules confirmées ;
	 * la date passée n'a plus de roue, et le compte des présentes s'affiche sous le titre.
	 */
	public function testList(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);

		$eDate = $this->createDate($eCourse, 2);
		$eDateEnrollment = $this->enrollConfirmed($eDate, $this->createPerson());
		$this->enrollConfirmed($eDate, $this->createPerson());

		$this->actAs($this->eUserAdmin);

		$html = $this->renderList($eDate);
		$this->assertFalse(str_contains($html, 'LearningDate.attend(this)'), 'pas de colonnes sur une date à venir');
		$this->assertFalse(str_contains($html, 'learning-date-attendance'), 'pas de compte des présentes');
		$this->assertTrue(str_contains($html, 'dateEnrollment:doCancelByCommunity'), 'la roue d\'une date à venir');

		$eDate = $this->moveDate($eDate, -2);
		DateEnrollmentLib::attend($this->getLine($eDateEnrollment['id']));

		$html = $this->renderList($eDate);
		$this->assertTrue(str_contains($html, 'LearningDate.attend(this)'), 'la case');
		$this->assertTrue(str_contains($html, 'id="learning-date-attendance"'), 'le compte des présentes');
		$this->assertFalse(str_contains($html, 'dateEnrollment:doCancelByCommunity'), 'plus de roue sur une date passée');
		$this->assertTrue(str_contains($html, 'id="learning-date-enrollment-'.$eDateEnrollment['id'].'"'), 'la ligne se cible');

	}

	private function renderList(Date $eDate): string {

		$eDate = $this->prepareDate($eDate);
		DateEnrollmentLib::fillAttendedByDate($eDate);

		$cDateSheet = DateSheetLib::getByDate($eDate);
		$eDate['sheets'] = $cDateSheet->count();

		$cDateEnrollment = DateEnrollmentLib::getByDate($eDate);
		DateEnrollmentLib::fillLosesLessons($eDate['course'], $cDateEnrollment);
		DateEnrollmentLib::fillAttestationRecipients($eDate, $cDateEnrollment);

		return new DateEnrollmentUi()->getList($eDate, $cDateEnrollment, $cDateSheet, '');

	}

}
?>
