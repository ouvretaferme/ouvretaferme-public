<?php
namespace user;

/**
 * Les connexions des administrateurs (`ConnectionLogAiLib`) : ce que l'écran liste, ce que les
 * filtres retiennent, et le marquage d'une reconnexion automatique depuis une adresse inconnue.
 *
 * Tout se joue en transaction annulée par `tearDown()`, la dev étant partagée par plusieurs
 * sessions. Deux comptes de test remplacent `UserSetting::$admin` le temps du cas, et les traces
 * sont posées directement dans `userLog` avec leur date, ce que `LogLib::add()` ne permet pas.
 *
 * Lancement : dev/test package=user file=ConnectionLogAi
 */
class ConnectionLogAiTest extends \Test {

	const IP_OFFICE = '203.0.113.10';
	const IP_HOME = '203.0.113.11';
	const IP_NEW = '203.0.113.12';
	const IP_PHONE = '203.0.113.13';

	private array $adminInitial;

	private User $eUserAdmin;
	private User $eUserAdminOther;
	private User $eUserMember;

	public function setUp(): void {

		Log::model()->beginTransaction();

		$this->eUserAdmin = $this->createUser('Claire');
		$this->eUserAdminOther = $this->createUser('Louis');
		$this->eUserMember = $this->createUser('Marie');

		$this->adminInitial = UserSetting::$admin;
		UserSetting::$admin = [$this->eUserAdmin['id'], $this->eUserAdminOther['id']];

	}

	public function tearDown(): void {

		UserSetting::$admin = $this->adminInitial;

		Log::model()->rollBack();

	}

	private function createUser(string $firstName): User {

		$eUser = new User([
			'firstName' => 'zzTest',
			'lastName' => $firstName,
			'email' => 'zztest.connexion.'.uniqid().'@example.org',
			'visibility' => User::PRIVATE,
			'roleCustomer' => TRUE,
			'roleProducer' => FALSE,
			'roleConsultant' => FALSE,
			'roleTeacher' => FALSE
		]);

		UserLib::create($eUser);

		return $eUser;

	}

	private function log(User $eUser, string $action, string $ip, string $device, string $createdAt, User $eUserAction = new User()): void {

		$eLog = new Log([
			'user' => $eUser,
			'action' => $action,
			'ip' => $ip,
			'sid' => 'zztest'.uniqid(),
			'device' => $device,
			'deviceVersion' => NULL,
			'createdAt' => $createdAt,
			'userAction' => $eUserAction,
		]);

		Log::model()->insert($eLog);

	}

	private function daysAgo(int $days, string $time = '10:00:00'): string {
		return date('Y-m-d', strtotime('-'.$days.' days')).' '.$time;
	}

	private function search(array $properties = []): array {
		return ConnectionLogAiLib::getAll(new \Search($properties), 0);
	}

	public function testOnlyAdministratorsAreListedAndLogoutsAreNot(): void {

		$this->log($this->eUserAdmin, Log::LOGIN, self::IP_OFFICE, Log::WEB, $this->daysAgo(2));
		$this->log($this->eUserAdmin, Log::LOGOUT, self::IP_OFFICE, Log::WEB, $this->daysAgo(2, '18:00:00'));
		$this->log($this->eUserAdminOther, Log::LOGIN_AUTO, self::IP_HOME, Log::MOBILE_WEB, $this->daysAgo(1));
		$this->log($this->eUserMember, Log::LOGIN, self::IP_NEW, Log::WEB, $this->daysAgo(1, '11:00:00'));

		[$cLog, $nLog] = $this->search();

		$this->assertEquals(2, $nLog, 'le membre et la déconnexion ne sont pas listés');
		$this->assertEquals(2, $cLog->count());

		$eLogFirst = $cLog->first();

		$this->assertEquals(Log::LOGIN_AUTO, $eLogFirst['action'], 'la plus récente en tête');
		$this->assertEquals($this->eUserAdminOther['id'], $eLogFirst['eUserAdministrator']['id']);
		$this->assertTrue($eLogFirst['eUserBorrowed']->empty());
		$this->assertEquals('zzTest Louis', $eLogFirst['eUserAdministrator']->getName(), 'le nom est chargé pour le lien');

	}

	public function testExternalConnectionIsReadByTheAdministratorWhoBorrows(): void {

		$this->log($this->eUserMember, Log::LOGIN_EXTERNAL, self::IP_OFFICE, Log::WEB, $this->daysAgo(1), $this->eUserAdmin);
		$this->log($this->eUserAdminOther, Log::LOGIN_EXTERNAL, self::IP_OFFICE, Log::WEB, $this->daysAgo(2), $this->eUserAdmin);

		[$cLog, $nLog] = $this->search();

		$this->assertEquals(2, $nLog, 'l\'entrée sur un compte tiers compte comme celle sur le compte de l\'autre administrateur');

		$eLogMember = $cLog->first();

		$this->assertEquals($this->eUserAdmin['id'], $eLogMember['eUserAdministrator']['id'], 'l\'administrateur est celui qui emprunte');
		$this->assertEquals($this->eUserMember['id'], $eLogMember['eUserBorrowed']['id'], 'le compte emprunté est en lien');
		$this->assertFalse($eLogMember['unknownAddress']);

		[$cLog, $nLog] = $this->search(['action' => ConnectionLogAiLib::FILTER_EXTERNAL]);
		$this->assertEquals(2, $nLog);

		[$cLog, $nLog] = $this->search(['user' => $this->eUserAdminOther['id']]);
		$this->assertEquals(0, $nLog, 'être emprunté n\'est pas se connecter');

		[$cLog, $nLog] = $this->search(['user' => $this->eUserAdmin['id']]);
		$this->assertEquals(2, $nLog);

	}

	public function testFilters(): void {

		$this->log($this->eUserAdmin, Log::LOGIN, self::IP_OFFICE, Log::WEB, $this->daysAgo(5));
		$this->log($this->eUserAdmin, Log::LOGIN_AUTO, self::IP_OFFICE, Log::WEB, $this->daysAgo(4));
		$this->log($this->eUserAdminOther, Log::LOGIN, self::IP_HOME, Log::MOBILE_WEB, $this->daysAgo(3));
		$this->log($this->eUserMember, Log::LOGIN_EXTERNAL, self::IP_HOME, Log::WEB, $this->daysAgo(2), $this->eUserAdminOther);

		[$cLog, $nLog] = $this->search(['action' => ConnectionLogAiLib::FILTER_PASSWORD]);
		$this->assertEquals(2, $nLog, 'mot de passe');

		[$cLog, $nLog] = $this->search(['action' => ConnectionLogAiLib::FILTER_AUTO]);
		$this->assertEquals(1, $nLog, 'reconnexion automatique');

		[$cLog, $nLog] = $this->search(['ip' => self::IP_HOME]);
		$this->assertEquals(2, $nLog, 'adresse, les deux colonnes confondues');

		[$cLog, $nLog] = $this->search(['user' => $this->eUserAdminOther['id']]);
		$this->assertEquals(2, $nLog, 'un administrateur, ses mots de passe et ses emprunts');

		[$cLog, $nLog] = $this->search(['from' => date('Y-m-d', strtotime('-3 days'))]);
		$this->assertEquals(2, $nLog, 'du');

		[$cLog, $nLog] = $this->search(['to' => date('Y-m-d', strtotime('-4 days'))]);
		$this->assertEquals(2, $nLog, 'au, jour inclus');

		[$cLog, $nLog] = $this->search(['from' => date('Y-m-d', strtotime('-4 days')), 'to' => date('Y-m-d', strtotime('-3 days'))]);
		$this->assertEquals(2, $nLog, 'du au');

	}

	public function testUnknownAddressIsMarkedOnComputersOnly(): void {

		$this->log($this->eUserAdmin, Log::LOGIN, self::IP_OFFICE, Log::WEB, $this->daysAgo(20));
		$this->log($this->eUserAdmin, Log::LOGIN_AUTO, self::IP_HOME, Log::WEB, $this->daysAgo(45));

		$this->log($this->eUserAdmin, Log::LOGIN_AUTO, self::IP_OFFICE, Log::WEB, $this->daysAgo(3, '08:00:00'));
		$this->log($this->eUserAdmin, Log::LOGIN_AUTO, self::IP_HOME, Log::WEB, $this->daysAgo(3, '09:00:00'));
		$this->log($this->eUserAdmin, Log::LOGIN_AUTO, self::IP_NEW, Log::WEB, $this->daysAgo(3, '10:00:00'));
		$this->log($this->eUserAdmin, Log::LOGIN_AUTO, self::IP_PHONE, Log::MOBILE_WEB, $this->daysAgo(3, '11:00:00'));
		$this->log($this->eUserAdmin, Log::LOGIN, self::IP_PHONE, Log::WEB, $this->daysAgo(3, '12:00:00'));
		$this->log($this->eUserAdmin, Log::LOGIN_AUTO, self::IP_NEW, Log::WEB, $this->daysAgo(2));
		$this->log($this->eUserAdminOther, Log::LOGIN_AUTO, self::IP_OFFICE, Log::WEB, $this->daysAgo(1));

		[$cLog, $nLog] = $this->search();

		$this->assertEquals(9, $nLog);

		$marks = [];

		foreach($cLog as $eLog) {
			$marks[$eLog['ip'].' '.$eLog['createdAt']] = $eLog['unknownAddress'];
		}

		$this->assertFalse($marks[self::IP_OFFICE.' '.$this->daysAgo(3, '08:00:00')], 'adresse vue par mot de passe 17 jours avant');
		$this->assertTrue($marks[self::IP_HOME.' '.$this->daysAgo(3, '09:00:00')], 'adresse vue 42 jours avant, hors fenêtre');
		$this->assertTrue($marks[self::IP_NEW.' '.$this->daysAgo(3, '10:00:00')], 'adresse jamais vue');
		$this->assertFalse($marks[self::IP_PHONE.' '.$this->daysAgo(3, '11:00:00')], 'une reconnexion mobile n\'est jamais marquée');
		$this->assertFalse($marks[self::IP_PHONE.' '.$this->daysAgo(3, '12:00:00')], 'un mot de passe n\'est jamais marqué');
		$this->assertFalse($marks[self::IP_NEW.' '.$this->daysAgo(2)], 'la même adresse la veille la rend connue');
		$this->assertTrue($marks[self::IP_OFFICE.' '.$this->daysAgo(1)], 'l\'adresse est connue du compte, pas de l\'autre administrateur');
		$this->assertFalse($marks[self::IP_OFFICE.' '.$this->daysAgo(20)], 'un mot de passe n\'est jamais marqué');
		$this->assertTrue($marks[self::IP_HOME.' '.$this->daysAgo(45)], 'la première trace connue');

		[$cLog, $nLog] = $this->search(['action' => ConnectionLogAiLib::FILTER_UNKNOWN]);

		$this->assertEquals(4, $nLog, 'le filtre retient exactement les lignes marquées');

		foreach($cLog as $eLog) {
			$this->assertTrue($eLog['unknownAddress'], 'le filtre et le marquage disent la même chose');
		}

	}

	public function testMarkStopsWhereTheHistoryStops(): void {

		$before = date('Y-m-d H:i:s', strtotime(ConnectionLogAiLib::getMarkableFrom()) - 3600);
		$after = date('Y-m-d H:i:s', strtotime(ConnectionLogAiLib::getMarkableFrom()) + 3600);

		$this->log($this->eUserAdmin, Log::LOGIN_AUTO, self::IP_NEW, Log::WEB, $before);
		$this->log($this->eUserAdmin, Log::LOGIN_AUTO, self::IP_OFFICE, Log::WEB, $after);

		[$cLog, $nLog] = $this->search();

		$marks = [];

		foreach($cLog as $eLog) {
			$marks[$eLog['ip']] = $eLog['unknownAddress'];
		}

		$this->assertFalse($marks[self::IP_NEW], 'plus d\'historique pour juger, pas de marque');
		$this->assertTrue($marks[self::IP_OFFICE]);

		[$cLog, $nLog] = $this->search(['action' => ConnectionLogAiLib::FILTER_UNKNOWN]);

		$this->assertEquals(1, $nLog);
		$this->assertEquals(self::IP_OFFICE, $cLog->first()['ip']);

	}

}
?>
