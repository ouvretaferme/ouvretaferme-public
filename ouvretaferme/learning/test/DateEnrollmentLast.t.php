<?php
namespace learning;

require_once __DIR__.'/LearningDateFixture.t.php';

/**
 * Les dernières inscriptions du tableau de bord d'un réseau (`DateEnrollmentLib::getLastByCommunity()`
 * et `DateEnrollmentUi::getLast()`) : cinq au plus, la plus récente en tête, sur les seules dates à
 * venir du réseau, sans les lignes annulées ni la liste d'attente, avec les places prises de la date.
 *
 * Le réseau du décor peut porter d'autres inscriptions dans une copie de la production : le test
 * pose les siennes à l'heure figée du décor, donc après elles, et juge ses propres lignes.
 *
 * Lancement : dev/test package=learning file=DateEnrollmentLast
 */
class DateEnrollmentLastTest extends LearningDateFixtureTest {

	public function testLastAndOrder(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);
		$eDate = $this->createDate($eCourse, 5);

		$ids = [];

		for($person = 0; $person < DateEnrollmentLib::LAST_MAX + 2; $person++) {
			$ids[] = $this->enrollRequested($eDate, $this->createPerson())['id'];
		}

		$cDateEnrollment = DateEnrollmentLib::getLastByCommunity($this->eStructureNetwork);

		$this->assertEquals(
			array_slice(array_reverse($ids), 0, DateEnrollmentLib::LAST_MAX),
			$cDateEnrollment->getIds(),
			'les cinq dernières lignes, la plus récente en tête'
		);

		$this->assertEquals(count($ids), $cDateEnrollment->first()['date']['taken'], 'les places prises de la date comptent toutes ses lignes, pas les cinq montrées');

		$this->assertEquals(
			[],
			array_intersect($ids, DateEnrollmentLib::getLastByCommunity($this->eStructureFarm)->getIds()),
			'une structure qui ne porte pas la formation ne lit aucune de ces lignes'
		);

	}

	/**
	 * Ce qui n'entre pas : une ligne annulée, une ligne en liste d'attente, une date passée et une
	 * date annulée. Chaque cas part d'une ligne que la lecture rend, pour que son absence dise
	 * quelque chose.
	 */
	public function testExcluded(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);

		$read = fn() => DateEnrollmentLib::getLastByCommunity($this->eStructureNetwork)->getIds();

		$eDate = $this->createDate($eCourse, 5);

		// Posé sans `build()`, qui borne `seats` à un entier d'au moins 1 : la valeur est dans la borne,
		// et le décor veut une date pleine à la première ligne.
		$eDate['seats'] = 1;
		DateLib::update($eDate, ['seats']);

		$eDateEnrollmentKept = $this->enrollConfirmed($eDate, $this->createPerson());

		$eUserWaiting = $this->createPerson();
		$eDateEnrollmentWaiting = DateEnrollmentLib::getCreateElement($eDate, $eUserWaiting);
		$eDateEnrollmentWaiting['structure'] = new \structure\Structure();
		$eDateEnrollmentWaiting['trainee'] = DateEnrollment::FARMER;
		$eDateEnrollmentWaiting['committedAt'] = \Clock::datetime();
		DateEnrollmentLib::create($eDateEnrollmentWaiting);
		$eDateEnrollmentWaiting = DateEnrollmentLib::getByDateAndUser($eDate, $eUserWaiting);

		if($this->assertDecorPresent($eDateEnrollmentWaiting['status'] === DateEnrollment::WAITING, 'une seconde ligne sur une date d\'une place part en liste d\'attente') === FALSE) {
			return;
		}

		$ids = $read();

		$this->assertTrue(in_array($eDateEnrollmentKept['id'], $ids), 'la ligne confirmée est rendue');
		$this->assertFalse(in_array($eDateEnrollmentWaiting['id'], $ids), 'la liste d\'attente ne l\'est pas');

		$eDateOther = $this->createDate($eCourse, 6);
		$eDateEnrollmentCanceled = $this->enrollRequested($eDateOther, $this->createPerson());
		$eDateEnrollmentPast = $this->enrollRequested($eDateOther, $this->createPerson());

		$this->assertTrue(in_array($eDateEnrollmentCanceled['id'], $read()), 'la demande est rendue avant son annulation');

		DateEnrollmentLib::cancel($eDateEnrollmentCanceled, DateEnrollment::USER);

		$ids = $read();

		$this->assertFalse(in_array($eDateEnrollmentCanceled['id'], $ids), 'une ligne annulée ne l\'est plus');
		$this->assertTrue(in_array($eDateEnrollmentPast['id'], $ids), 'sa voisine de la même date reste');

		$this->moveDate($eDateOther, -1);

		$this->assertFalse(in_array($eDateEnrollmentPast['id'], $read()), 'une date passée sort ses lignes');

		DateLib::cancel(DateLib::getById($eDate['id']));

		$this->assertFalse(in_array($eDateEnrollmentKept['id'], $read()), 'une date annulée sort les siennes');

	}

	public function testRender(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);

		$eDate = $this->createDate($eCourse, 5);
		$this->enrollConfirmed($eDate, $this->createPerson());
		$this->enrollRequested($eDate, $this->createPerson());

		$cDateEnrollment = DateEnrollmentLib::getLastByCommunity($this->eStructureNetwork)->find(fn(DateEnrollment $e) => $e['date']['id'] === $eDate['id']);
		$html = new DateEnrollmentUi()->getLast($cDateEnrollment);

		$this->assertEquals([2, 2], [$cDateEnrollment->count(), $cDateEnrollment->first()['date']['taken']], 'deux lignes, deux places prises, demande comprise');
		$this->assertTrue(str_contains($html, 'href="'.DateEnrollmentUi::urlList($eDate).'"'), 'la formation mène à la liste des inscrits de la date');

		// Posé sans `build()` pour la même raison que dans `testExcluded()`.
		$eDate['seats'] = 12;
		DateLib::update($eDate, ['seats']);

		$htmlSeats = new DateEnrollmentUi()->getLast(DateEnrollmentLib::getLastByCommunity($this->eStructureNetwork)->find(fn(DateEnrollment $e) => $e['date']['id'] === $eDate['id']));

		$this->assertTrue($htmlSeats !== $html, 'avec un plafond, les places prises se lisent sur le plafond');

	}

}
?>
