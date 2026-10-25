<?php
namespace user;

/**
 * La fermeture d'un compte est refusée tant qu'il est au planning d'une structure (décidé le 22/10) : la
 * demande, pas son annulation, et le compte en attente que le planning a repris n'est pas clôturé à l'échéance.
 *
 * Tout se joue dans la transaction de `TransactionTest`, les comptes semés en `example.org`.
 *
 * Lancement : dev/test package=user file=DropCloseAi
 */
class DropCloseAiTest extends \dev\TransactionTest {

	protected \structure\Structure $eStructure;

	protected function getTransactionModel(): \ModuleModel {
		return User::model();
	}

	public function init(): void {

		$this->eStructure = \structure\StructureLib::getById(\structure\FixtureAiLib::F1);

	}

	public function tearDown(): void {

		\session\SessionLib::clean();

		parent::tearDown();

	}

	/**
	 * Un compte membre de la structure, avec une période de `$from` à `$to`.
	 */
	protected function createUser(string $name, string $from, ?string $to): User {

		$eUser = new User([
			'firstName' => 'zzTest',
			'lastName' => $name,
			'email' => 'zztest.dropclose.'.uniqid().'@example.org',
			'visibility' => User::PUBLIC,
			'roleCustomer' => FALSE,
			'roleProducer' => TRUE,
			'roleConsultant' => FALSE,
			'roleTeacher' => FALSE
		]);

		// Hors build() : le domaine réservé et uniqid() assurent ce que build() vérifierait de l'adresse.
		UserLib::create($eUser);

		// Hors build(), comme le pose InviteLib::accept().
		\structure\MemberLib::create(new \structure\Member([
			'user' => $eUser,
			'structure' => $this->eStructure,
			'role' => \structure\RoleLib::getByStructure($this->eStructure, withAdmin: FALSE)->first(),
			'status' => \structure\Member::IN
		]));

		// Hors build() : le décor veut ces dates exactes, passées comprises, que `to.present` refuserait.
		\hr\PresenceLib::create(new \hr\Presence([
			'structure' => $this->eStructure,
			'user' => $eUser,
			'from' => $from,
			'to' => $to
		]));

		return $this->getUser($eUser);

	}

	protected function getUser(User $eUser): User {

		return User::model()
			->select(['id', 'email', 'visibility', 'status', 'deletedAt'])
			->whereId($eUser)
			->get();

	}

	public function testRefusedWithPresence(): void {

		$eUser = $this->createUser('Au planning', '2020-01-01', NULL);

		$fw = new \FailWatch();

		$this->assertFalse(DropLib::changeClose($eUser), 'la demande est refusée');
		$this->assertTrue($fw->has('User::closePresence'), 'avec le message qui dit pourquoi');
		$this->assertEquals(NULL, $this->getUser($eUser)['deletedAt'], 'et rien n\'est écrit');

	}

	public function testAcceptedWithoutPresence(): void {

		$eUser = $this->createUser('Sorti du planning', '2020-01-01', '2020-12-31');

		$fw = new \FailWatch();

		$this->assertTrue(DropLib::changeClose($eUser), 'une période finie ne retient pas le compte');
		$this->assertTrue($fw->ok());
		$this->assertTrue($this->getUser($eUser)['deletedAt'] !== NULL, 'la fermeture est demandée');

	}

	/** Remis au planning pendant le délai : l'annulation reste permise, l'échéance ne clôt pas le compte. */
	public function testPendingWithPresence(): void {

		$eUser = $this->createUser('Repris', '2020-01-01', NULL);

		// Hors Lib : la demande posée avant la période, que `changeClose()` refuserait aujourd'hui, et son échéance passée.
		User::model()->update($eUser, ['deletedAt' => '2020-01-01 00:00:00']);

		DropLib::closeExpired();

		$this->assertEquals(User::ACTIVE, $this->getUser($eUser)['status'], 'le compte au planning n\'est pas clôturé');

		$fw = new \FailWatch();

		$this->assertTrue(DropLib::changeClose($this->getUser($eUser)), 'l\'annulation reste permise');
		$this->assertTrue($fw->ok());
		$this->assertEquals(NULL, $this->getUser($eUser)['deletedAt'], 'la demande est annulée');

	}

	public function testClosedWithoutPresence(): void {

		$eUser = $this->createUser('Parti', '2020-01-01', '2020-12-31');

		// Hors Lib : une demande dont l'échéance est passée, que seul le délai de `changeClose()` pose autrement.
		User::model()->update($eUser, ['deletedAt' => '2020-01-01 00:00:00']);

		DropLib::closeExpired();

		$this->assertEquals(User::CLOSED, $this->getUser($eUser)['status'], 'le compte sans période ouverte est clôturé');

	}

}
?>
