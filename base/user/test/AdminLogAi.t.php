<?php
namespace user;

/**
 * Le journal des pages de l'admin : ce qu'une requête y laisse, et ce que l'écran y retrouve.
 *
 * Les lignes du test portent un administrateur qui n'existe pas, pour ne compter que les siennes,
 * et tout se joue en transaction annulée par `tearDown()`, la dev étant partagée par plusieurs
 * sessions.
 *
 * Lancement : dev/test package=user file=AdminLogAi
 */
class AdminLogAiTest extends \Test {

	const ADMIN_ID = 999999901;
	const TARGET_ID = 999999902;

	public function setUp(): void {
		AdminLog::model()->beginTransaction();
	}

	public function tearDown(): void {
		AdminLog::model()->rollBack();
	}

	public function testPrepareEntryRoute(): void {

		$eUser = new User(['id' => self::ADMIN_ID]);

		$eAdminLog = AdminLogAiLib::prepareEntry($eUser, 'user/admin/log', 'index', 'GET', ['page' => '2'], []);

		$this->assertEquals('/user/admin/log', $eAdminLog['request']);
		$this->assertEquals(AdminLog::GET, $eAdminLog['method']);
		$this->assertEquals(['page' => '2'], $eAdminLog['parameters']);
		$this->assertEquals(self::ADMIN_ID, $eAdminLog['user']['id']);

		$eAdminLog = AdminLogAiLib::prepareEntry($eUser, 'dev/admin/index', 'doStatus', 'POST', [], ['id' => '146819']);

		$this->assertEquals('/dev/admin/:doStatus', $eAdminLog['request']);
		$this->assertEquals(AdminLog::POST, $eAdminLog['method']);
		$this->assertEquals(['id' => '146819'], $eAdminLog['parameters']);

	}

	public function testPrepareEntryHidesSecrets(): void {

		$eUser = new User(['id' => self::ADMIN_ID]);

		$eAdminLog = AdminLogAiLib::prepareEntry($eUser, 'user/admin/index', 'doUpdate', 'POST', ['token' => 'abc'], [
			'password' => 'secret',
			'email' => 'a@b.fr',
			'nested' => ['apiKey' => 'k', 'name' => 'n'],
			'long' => str_repeat('x', 500),
		]);

		$parameters = $eAdminLog['parameters'];

		$this->assertEquals(\dev\ErrorPhpLib::HIDDEN_VALUE, $parameters['token']);
		$this->assertEquals(\dev\ErrorPhpLib::HIDDEN_VALUE, $parameters['password']);
		$this->assertEquals('a@b.fr', $parameters['email']);
		$this->assertEquals(\dev\ErrorPhpLib::HIDDEN_VALUE, $parameters['nested']['apiKey']);
		$this->assertEquals('n', $parameters['nested']['name']);
		$this->assertEquals(AdminLogAiLib::VALUE_LENGTH, mb_strlen($parameters['long']));

	}

	public function testSearch(): void {

		$this->createEntries();

		[$cAdminLog, $nAdminLog] = AdminLogAiLib::getAll(new \Search(['user' => self::ADMIN_ID]), 0);

		$this->assertEquals(3, $nAdminLog);
		$this->assertEquals(3, $cAdminLog->count());

		[$cAdminLog, $nAdminLog] = AdminLogAiLib::getAll(new \Search(['user' => self::ADMIN_ID, 'type' => AdminLogAiLib::TYPE_READ]), 0);

		$this->assertEquals(1, $nAdminLog);
		$this->assertEquals('/user/admin/', $cAdminLog->first()['request']);

		[$cAdminLog, $nAdminLog] = AdminLogAiLib::getAll(new \Search(['user' => self::ADMIN_ID, 'type' => AdminLogAiLib::TYPE_WRITE]), 0);

		$this->assertEquals(1, $nAdminLog);
		$this->assertEquals('/user/admin/ban:do', $cAdminLog->first()['request']);

		[$cAdminLog, $nAdminLog] = AdminLogAiLib::getAll(new \Search(['user' => self::ADMIN_ID, 'type' => AdminLogAiLib::TYPE_LOGIN_EXTERNAL]), 0);

		$this->assertEquals(1, $nAdminLog);
		$this->assertEquals(AdminLogAiLib::REQUEST_LOGIN_EXTERNAL, $cAdminLog->first()['request']);

		[, $nAdminLog] = AdminLogAiLib::getAll(new \Search(['user' => self::ADMIN_ID, 'request' => 'ban']), 0);

		$this->assertEquals(1, $nAdminLog);

		[, $nAdminLog] = AdminLogAiLib::getAll(new \Search(['user' => self::ADMIN_ID, 'from' => date('Y-m-d'), 'to' => date('Y-m-d')]), 0);

		$this->assertEquals(3, $nAdminLog);

		[, $nAdminLog] = AdminLogAiLib::getAll(new \Search(['user' => self::ADMIN_ID, 'from' => date('Y-m-d', strtotime('+1 day'))]), 0);

		$this->assertEquals(0, $nAdminLog);

		[, $nAdminLog] = AdminLogAiLib::getAll(new \Search(['user' => self::ADMIN_ID, 'to' => date('Y-m-d', strtotime('-1 day'))]), 0);

		$this->assertEquals(0, $nAdminLog);

	}

	public function testTargets(): void {

		$eUser = new User(['id' => self::ADMIN_ID]);

		$eAdminLog = AdminLogAiLib::prepareEntry($eUser, 'structure/admin/index', 'index', 'GET', ['id' => '6436'], []);
		$this->assertEquals([AdminLogAiLib::TARGET_STRUCTURE, 6436], AdminLogAiLib::getTarget($eAdminLog));

		$eAdminLog = AdminLogAiLib::prepareEntry($eUser, 'dev/admin/index', 'doStatus', 'POST', [], ['id' => '146819']);
		$this->assertEquals([AdminLogAiLib::TARGET_ERROR, 146819], AdminLogAiLib::getTarget($eAdminLog));

		$eAdminLog = AdminLogAiLib::prepareEntry($eUser, 'user/admin/ban', 'form', 'GET', ['userToBan' => '12'], []);
		$this->assertEquals([AdminLogAiLib::TARGET_USER, 12], AdminLogAiLib::getTarget($eAdminLog));

		$eAdminLog = AdminLogAiLib::prepareEntry($eUser, 'payment/admin/index', 'index', 'GET', [], []);
		$this->assertEquals([], AdminLogAiLib::getTarget($eAdminLog));

		$eAdminLog = AdminLogAiLib::prepareEntry($eUser, 'user/admin/index', 'index', 'GET', ['id' => 'abc'], []);
		$this->assertEquals([], AdminLogAiLib::getTarget($eAdminLog));

		$eUserExisting = User::model()
			->select(['id'])
			->sort(['id' => SORT_ASC])
			->get();

		$this->createEntries($eUserExisting['id']);

		[$cAdminLog] = AdminLogAiLib::getAll(new \Search(['user' => self::ADMIN_ID, 'type' => AdminLogAiLib::TYPE_READ]), 0);

		$eAdminLog = $cAdminLog->first();

		$this->assertEquals($eUserExisting['id'], $eAdminLog['eUserTarget']['id']);
		$this->assertEquals(NULL, $eAdminLog['structureTarget']);
		$this->assertEquals(NULL, $eAdminLog['errorTarget']);

	}

	public function testClean(): void {

		$eUser = new User(['id' => self::ADMIN_ID]);

		$eAdminLogOld = AdminLogAiLib::prepareEntry($eUser, 'user/admin/index', 'index', 'GET', [], []);
		$eAdminLogOld['createdAt'] = date('Y-m-d H:i:s', strtotime('-'.(UserSetting::KEEP_LOGS + 1).' days'));
		AdminLogAiLib::create($eAdminLogOld);

		AdminLogAiLib::create(AdminLogAiLib::prepareEntry($eUser, 'user/admin/index', 'index', 'GET', [], []));

		AdminLogAiLib::clean();

		$this->assertEquals(1, AdminLog::model()->whereUser(self::ADMIN_ID)->count());

	}

	public function testRecordOnce(): void {

		$eUser = new User(['id' => self::ADMIN_ID]);

		AdminLogAiLib::record($eUser);
		AdminLogAiLib::record($eUser);

		$this->assertEquals(1, AdminLog::model()->whereUser(self::ADMIN_ID)->count());

	}

	public function testExternalEntry(): void {

		$eUser = new User(['id' => self::ADMIN_ID]);
		$eUserExternal = new User(['id' => self::TARGET_ID]);

		$eAdminLog = AdminLogAiLib::prepareExternalEntry($eUser, $eUserExternal, '/ferme/3994/ventes', 'GET', ['id' => '3994'], []);

		$this->assertEquals('/ferme/3994/ventes', $eAdminLog['request']);
		$this->assertEquals(AdminLog::GET, $eAdminLog['method']);
		$this->assertEquals(self::ADMIN_ID, $eAdminLog['user']['id']);
		$this->assertEquals(self::TARGET_ID, $eAdminLog['userExternal']['id']);
		$this->assertEquals([AdminLogAiLib::TARGET_STRUCTURE, 3994], AdminLogAiLib::getTarget($eAdminLog));

		$eAdminLog = AdminLogAiLib::prepareExternalEntry($eUser, $eUserExternal, '/selling/sale:doCreate', 'POST', [], ['structure' => '3994', 'password' => 'secret']);

		$this->assertEquals(AdminLog::POST, $eAdminLog['method']);
		$this->assertEquals(\dev\ErrorPhpLib::HIDDEN_VALUE, $eAdminLog['parameters']['password']);
		$this->assertEquals([AdminLogAiLib::TARGET_STRUCTURE, 3994], AdminLogAiLib::getTarget($eAdminLog));

		$eAdminLog = AdminLogAiLib::prepareExternalEntry($eUser, $eUserExternal, '/vente/12', 'GET', ['id' => '12'], []);

		$this->assertEquals([], AdminLogAiLib::getTarget($eAdminLog));

	}

	public function testAcceptExternal(): void {

		$this->assertTrue(AdminLogAiLib::acceptExternal('GET', '/ferme/17/outils', 'index'));
		$this->assertTrue(AdminLogAiLib::acceptExternal('GET', '/ferme/17/outils', 'query'));
		$this->assertTrue(AdminLogAiLib::acceptExternal('POST', '/selling/customer', 'doCreate'));
		$this->assertTrue(AdminLogAiLib::acceptExternal('POST', '/selling/customer', 'doUpdateProperties'));
		$this->assertTrue(AdminLogAiLib::acceptExternal('POST', '/series/series', 'addPlant'));

		$this->assertFalse(AdminLogAiLib::acceptExternal('POST', '/selling/customer', 'query'));
		$this->assertFalse(AdminLogAiLib::acceptExternal('POST', '/selling/customer', 'queryLabel'));
		$this->assertFalse(AdminLogAiLib::acceptExternal('POST', '/selling/customer', 'getFields'));
		$this->assertFalse(AdminLogAiLib::acceptExternal('POST', '/journal/operation', 'selectAccount'));

		$this->assertFalse(AdminLogAiLib::acceptExternal('GET', '/minify/77bab959/3f7bbcb5656d7459b1f8b65d0c0b9d52.css', 'index'), 'une feuille de style ne s\'enregistre pas');
		$this->assertFalse(AdminLogAiLib::acceptExternal('GET', '/minify/77bab959/b7bf54a7ff323a529593448bcec5c63f.js', 'index'), 'un script ne s\'enregistre pas');
		$this->assertTrue(AdminLogAiLib::acceptExternal('GET', '/ferme/17/minify', 'index'), 'seul le début de la route compte');

	}

	public function testLoginExternalCarriesAccount(): void {

		$eUser = new User(['id' => self::ADMIN_ID]);

		$eAdminLog = AdminLogAiLib::prepareEntry($eUser, 'user/log', 'doLoginExternal', 'POST', [], ['user' => (string)self::TARGET_ID]);
		$this->assertEquals(self::TARGET_ID, $eAdminLog['userExternal']['id']);

		$eAdminLog = AdminLogAiLib::prepareEntry($eUser, 'user/log', 'doLoginExternal', 'POST', [], ['user' => 'abc']);
		$this->assertTrue($eAdminLog['userExternal']->empty());

		$eAdminLog = AdminLogAiLib::prepareEntry($eUser, 'user/admin/ban', 'do', 'POST', [], ['user' => (string)self::TARGET_ID]);
		$this->assertTrue($eAdminLog['userExternal']->empty());

	}

	public function testSearchExternal(): void {

		$eUserExternal = User::model()
			->select(['id', 'firstName', 'lastName', 'email'])
			->whereFirstName('!=', NULL)
			->whereEmail('!=', NULL)
			->sort(['id' => SORT_ASC])
			->get();

		$eUser = new User(['id' => self::ADMIN_ID]);

		AdminLogAiLib::create(AdminLogAiLib::prepareEntry($eUser, 'user/admin/index', 'index', 'GET', [], []));
		AdminLogAiLib::create(AdminLogAiLib::prepareEntry($eUser, 'user/log', 'doLoginExternal', 'POST', [], ['user' => (string)$eUserExternal['id']]));
		AdminLogAiLib::create(AdminLogAiLib::prepareExternalEntry($eUser, $eUserExternal, '/ferme/3994/ventes', 'GET', [], []));
		AdminLogAiLib::create(AdminLogAiLib::prepareExternalEntry($eUser, $eUserExternal, '/selling/sale:doCreate', 'POST', [], ['structure' => '3994']));
		AdminLogAiLib::create(AdminLogAiLib::prepareExternalEntry($eUser, $eUserExternal, AdminLogAiLib::REQUEST_LOGOUT_EXTERNAL, 'POST', [], []));

		foreach([$eUserExternal['lastName'], $eUserExternal['email'], $eUserExternal['firstName'].' '.$eUserExternal['lastName']] as $external) {

			[, $nAdminLog] = AdminLogAiLib::getAll(new \Search(['user' => self::ADMIN_ID, 'external' => $external]), 0);
			$this->assertEquals(4, $nAdminLog, 'Compte emprunté cherché par « '.$external.' »');

		}

		[, $nAdminLog] = AdminLogAiLib::getAll(new \Search(['user' => self::ADMIN_ID, 'external' => 'aucun-compte-ne-porte-ce-nom']), 0);
		$this->assertEquals(0, $nAdminLog);

		[$cAdminLog, $nAdminLog] = AdminLogAiLib::getAll(new \Search(['user' => self::ADMIN_ID, 'external' => $eUserExternal['email'], 'type' => AdminLogAiLib::TYPE_READ]), 0);
		$this->assertEquals(1, $nAdminLog);
		$this->assertEquals('/ferme/3994/ventes', $cAdminLog->first()['request']);
		$this->assertEquals($eUserExternal['lastName'], $cAdminLog->first()['userExternal']['lastName']);
		$this->assertEquals(3994, $cAdminLog->first()['structureTarget']);

		[, $nAdminLog] = AdminLogAiLib::getAll(new \Search(['user' => self::ADMIN_ID, 'external' => $eUserExternal['email'], 'type' => AdminLogAiLib::TYPE_WRITE]), 0);
		$this->assertEquals(2, $nAdminLog);

		[, $nAdminLog] = AdminLogAiLib::getAll(new \Search(['user' => self::ADMIN_ID, 'type' => AdminLogAiLib::TYPE_READ]), 0);
		$this->assertEquals(2, $nAdminLog);

	}

	private function createEntries(int $targetId = self::TARGET_ID): void {

		$eUser = new User(['id' => self::ADMIN_ID]);

		AdminLogAiLib::create(AdminLogAiLib::prepareEntry($eUser, 'user/admin/index', 'index', 'GET', ['id' => (string)$targetId], []));
		AdminLogAiLib::create(AdminLogAiLib::prepareEntry($eUser, 'user/admin/ban', 'do', 'POST', [], ['userToBan' => (string)$targetId, 'reason' => 'test']));
		AdminLogAiLib::create(AdminLogAiLib::prepareEntry($eUser, 'user/log', 'doLoginExternal', 'POST', [], ['user' => (string)$targetId]));

	}

}
?>
