<?php
namespace user;

/**
 * Le bannissement depuis l'écran des tentatives : l'adresse à bannir est celle de la série, et non
 * la dernière adresse de connexion du compte visé, qui est le plus souvent celle du titulaire.
 *
 * En transaction annulée ; le compte est créé sans jamais se connecter, donc sans adresse connue.
 *
 * Lancement : dev/test package=user file=BanIpAi
 */
class BanIpAiTest extends \Test {

	const IP_SERIES = '203.0.113.90';

	private User $eUser;
	private User $eUserAdmin;

	public function setUp(): void {

		Ban::model()->beginTransaction();

		$this->eUser = $this->createUser();
		$this->eUserAdmin = $this->createUser();

	}

	public function tearDown(): void {

		Ban::model()->rollBack();

	}

	private function createUser(): User {

		$eUser = new User([
			'firstName' => 'zzTest',
			'lastName' => 'Bannissement',
			'email' => 'zztest.ban.'.uniqid().'@example.org',
			'visibility' => User::PRIVATE,
			'roleCustomer' => TRUE,
			'roleProducer' => FALSE,
			'roleConsultant' => FALSE,
			'roleTeacher' => FALSE
		]);

		UserLib::create($eUser);

		return $eUser;

	}

	public function testBanGivenIpWithUser(): void {

		$fw = new \FailWatch();

		BanLib::createBan($this->eUser, $this->eUserAdmin, ['ip'], 'recette', 1, $fw, self::IP_SERIES);

		$this->assertTrue($fw->ok());

		$eBan = Ban::model()
			->select(['ip', 'user'])
			->whereAdmin($this->eUserAdmin)
			->get();

		$this->assertTrue($eBan->notEmpty(), 'un bannissement est écrit');
		$this->assertEquals(self::IP_SERIES, $eBan['ip'], 'l\'adresse bannie est celle donnée, pas la dernière connue du compte');
		$this->assertTrue($eBan['user']->empty(), 'le compte n\'est pas banni quand seule l\'adresse est cochée');

	}

	public function testBanUserAndGivenIp(): void {

		$fw = new \FailWatch();

		BanLib::createBan($this->eUser, $this->eUserAdmin, ['user', 'ip'], 'recette', 1, $fw, self::IP_SERIES);

		$this->assertTrue($fw->ok());

		$eBan = Ban::model()
			->select(['ip', 'user'])
			->whereAdmin($this->eUserAdmin)
			->get();

		$this->assertEquals(self::IP_SERIES, $eBan['ip']);
		$this->assertEquals($this->eUser['id'], $eBan['user']['id']);

	}

	public function testBanIpWithoutUser(): void {

		$fw = new \FailWatch();

		BanLib::createBan(new User(), $this->eUserAdmin, ['ip'], 'recette', 1, $fw, self::IP_SERIES);

		$this->assertTrue($fw->ok());

		$eBan = Ban::model()
			->select(['ip', 'user'])
			->whereAdmin($this->eUserAdmin)
			->get();

		$this->assertEquals(self::IP_SERIES, $eBan['ip']);
		$this->assertTrue($eBan['user']->empty());

	}

	public function testUserWithoutKnownIpFallsBack(): void {

		$fw = new \FailWatch();

		try {
			BanLib::createBan($this->eUser, $this->eUserAdmin, ['ip'], 'recette', 1, $fw);
			$this->assertTrue(FALSE, 'sans adresse donnée, un compte jamais connecté ne se bannit pas par adresse');
		} catch(\NotExpectedAction $e) {
			$this->assertException($e);
		}

	}

}
?>
