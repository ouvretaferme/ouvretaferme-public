<?php
namespace dev;

/**
 * Lancement : dev/test package=dev file=Clock
 */
class ClockTest extends \Test {

	const DATETIME = '2026-06-15 10:00:00';

	public function testRealTimeByDefault(): void {

		$before = time();
		$timestamp = \Clock::timestamp();
		$now = \Clock::now()->getTimestamp();
		$after = time();

		$this->assertFalse(\Clock::isSet());
		$this->assertTrue($before <= $timestamp and $timestamp <= $after, 'sans réglage, l\'horloge rend l\'heure du serveur');
		$this->assertTrue($before <= $now and $now <= $after);
		$this->assertTrue(in_array(\Clock::year(), [(int)date('Y', $before), (int)date('Y', $after)], TRUE));

	}

	public function testReadersFollowClock(): void {

		$this->setClock(self::DATETIME);

		$this->assertTrue(\Clock::isSet());
		$this->assertEquals(strtotime(self::DATETIME), \Clock::timestamp());
		$this->assertEquals(self::DATETIME, \Clock::datetime());
		$this->assertEquals('2026-06-15', \Clock::date());
		$this->assertEquals('10:00:00', \Clock::timeOfDay());
		$this->assertEquals('2026-W25', \Clock::week());
		$this->assertEquals('2026-06', \Clock::month());
		$this->assertEquals(2026, \Clock::year());
		$this->assertEquals('15/06/2026 10h', \Clock::format('d/m/Y H\h'));
		$this->assertEquals(strtotime('2025-06-15 10:00:00'), \Clock::shift('-1 year'));
		$this->assertEquals(strtotime('2026-06-15'), \Clock::shift('today'));
		$this->assertEquals(strtotime('2026-06-16 10:00:00'), \Clock::shift('+1 day'));

		$now = \Clock::now();

		$this->assertEquals(self::DATETIME, $now->format('Y-m-d H:i:s'));
		$this->assertEquals(date_default_timezone_get(), $now->getTimezone()->getName(), 'l\'objet porte le fuseau du serveur, pas celui de UTC que rend un horodatage « @ »');

	}

	public function testShiftRefusesUnreadableModifier(): void {

		$this->setClock(self::DATETIME);

		try {
			\Clock::shift('n importe quoi');
			$this->assertFail('un modificateur illisible est refusé');
		} catch(\Exception $e) {
			$this->assertException($e, 'Invalid modifier \'n importe quoi\'');
		}

	}

	public function testIsoWeekStraddlesYear(): void {

		$this->setClock('2024-12-30 12:00:00');

		$this->assertEquals('2025-W01', \Clock::week(), 'la semaine ISO du 30 décembre 2024 est la première de 2025');
		$this->assertEquals(2024, \Clock::year(), 'l\'année est l\'année civile, pas celle de la semaine ISO');

	}

	public function testMachineIgnoresClock(): void {

		$this->setClock(self::DATETIME);

		$before = time();
		$machine = \Clock::machine();
		$after = time();

		$this->assertTrue($before <= $machine and $machine <= $after, 'l\'horloge de la machine rend l\'heure du serveur sous une horloge réglée');
		$this->assertEquals(strtotime(self::DATETIME), \Clock::timestamp(), 'et l\'heure métier reste réglée');

	}

	public function testDateWithoutTime(): void {

		$this->setClock('2027-01-05');

		$this->assertEquals('2027-01-05 00:00:00', \Clock::datetime());
		$this->assertEquals(2027, \Clock::year());

	}

	public function testClockIsSetAgain(): void {

		$this->setClock(self::DATETIME);
		$this->setClock('2026-06-15 10:00:01');

		$this->assertEquals('2026-06-15 10:00:01', \Clock::datetime());

	}

	public function testInvalidDatetimeIsRefused(): void {

		$datetimes = [
			'tomorrow', '2026-06-15T10:00:00', '15/06/2026', '2026-06-15 10:00', '', "2026-06-15\n",
			'2026-13-01', '2026-02-30', '2026-06-15 24:00:00', '2026-03-29 02:30:00', '1970-01-01 01:00:00', '1969-12-31'
		];

		foreach($datetimes as $datetime) {

			try {
				$this->setClock($datetime);
				$this->assertFail('« '.$datetime.' » est refusé');
			} catch(\Exception $e) {
				$this->assertException($e, 'Invalid datetime \''.$datetime.'\'');
			}

		}

		$this->assertFalse(\Clock::isSet(), 'un réglage refusé laisse l\'horloge à l\'heure du serveur');

	}

	public function testRefusedOutsideTestMethod(): void {

		$method = $this->getTestMethod();
		$this->setTestMethod('');

		try {
			$this->setClock(self::DATETIME);
			$refused = FALSE;
		} catch(\Exception $e) {
			$refused = ($e->getMessage() === 'Clock can only be set inside a test method');
		} finally {
			$this->setTestMethod($method);
		}

		$this->assertTrue($refused, 'init() et finalize() ne règlent pas l\'horloge');
		$this->assertFalse(\Clock::isSet());

	}

	public function testResetByScope(): void {

		foreach([TestLib::METHOD, TestLib::FILE] as $scope) {

			$this->setClock(self::DATETIME);

			TestLib::reset($scope);

			$this->assertFalse(\Clock::isSet(), 'la remise de portée '.$scope.' rend l\'horloge au serveur');

		}

	}

	public function testOpenConnectionFollowsClock(): void {

		$database = Error::model()->pdo();

		$this->assertTrue($database->selectUnique('SELECT NOW()') !== self::DATETIME);

		$this->setClock(self::DATETIME);

		$this->assertEquals(self::DATETIME, $database->selectUnique('SELECT NOW()'));
		$this->assertEquals('2026-06-15', $database->selectUnique('SELECT CURDATE()'));
		$this->assertEquals('10:00:00', $database->selectUnique('SELECT CURTIME()'));
		$this->assertEquals(strtotime(self::DATETIME), (int)$database->selectUnique('SELECT UNIX_TIMESTAMP()'));
		$this->assertEquals(self::DATETIME, Error::model()->now());

		\Clock::reset();

		$this->assertTrue($database->selectUnique('SELECT NOW()') !== self::DATETIME, 'la connexion revient à l\'heure du serveur');

	}

	public function testConnectionBornUnderClock(): void {

		$this->setClock(self::DATETIME);

		$database = new \Database('dev');

		$this->assertEquals(self::DATETIME, $database->selectUnique('SELECT NOW()'));

	}

	public function testLastMethodLeavesClockSet(): void {

		$this->setClock(self::DATETIME);

		$this->assertTrue(\Clock::isSet());

	}

	// Les remises de portée ne tournent qu'avant une méthode ou un fichier : après la dernière méthode, seule celle qui suit tearDown() a pu rendre l'horloge
	public function finalize(): void {

		$this->assertFalse(\Clock::isSet(), 'l\'horloge de la dernière méthode ne survit pas à son tearDown()');
		$this->assertEquals('', $this->getTestMethod());

		try {
			$this->setClock(self::DATETIME);
			$refused = FALSE;
		} catch(\Exception $e) {
			$refused = TRUE;
		}

		\Clock::reset();

		$this->assertTrue($refused, 'finalize() ne règle pas l\'horloge');

	}

}
?>
