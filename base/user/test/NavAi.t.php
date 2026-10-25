<?php
namespace user;

/**
 * Le menu sombre (`User::nav`) : la valeur d'un compte neuf, ce que l'action `doUpdateNav` de
 * `settings.p.php` écrit, et ce que `build()` refuse.
 *
 * L'action rejoue ici ce que `doUpdateProperties()` fait sur l'élément de `getElement()`,
 * `ConnectionLib::getOnline()` : `build()` sur la liste `['nav']`, puis `UserLib::update()` sur les
 * seules propriétés construites. Tout se joue dans la transaction de `TransactionTest`.
 *
 * Lancement : dev/test package=user file=NavAi
 */
class NavAiTest extends \dev\TransactionTest {

	private User $eUser;
	private User $eUserOther;

	protected function getTransactionModel(): \ModuleModel {
		return new UserModel();
	}

	public function setUp(): void {

		parent::setUp();

		$this->eUser = $this->createUser();
		$this->eUserOther = $this->createUser();

		ConnectionLib::setOnline($this->eUser);

	}

	public function tearDown(): void {

		ConnectionLib::setOnline(new User());

		parent::tearDown();

	}

	private function createUser(): User {

		$eUser = new User([
			'firstName' => 'zzTest',
			'lastName' => 'Menu',
			'email' => 'zztest.nav.'.uniqid().'@example.org',
			'visibility' => User::PRIVATE,
			'roleCustomer' => TRUE,
			'roleProducer' => FALSE,
			'roleConsultant' => FALSE,
			'roleTeacher' => FALSE
		]);

		UserLib::create($eUser);

		return $eUser;

	}

	private function updateNav(array $input): \FailWatch {

		$fw = new \FailWatch();

		$eUser = ConnectionLib::getOnline();

		$l = new \PropertyList('update');
		$eUser->build(['nav'], $input, $l);

		if($fw->ok()) {
			UserLib::update($eUser, $l->getBuilt());
		}

		return $fw;

	}

	private function getNav(User $eUser): string {

		return User::model()
			->select('nav')
			->whereId($eUser['id'])
			->getValue('nav');

	}

	public function testNewAccountKeepsTheLightMenu(): void {

		$this->assertEquals(User::LIGHT, $this->getNav($this->eUser));

	}

	public function testActionWritesTheConnectedUserOnly(): void {

		$fw = $this->updateNav(['nav' => User::DARK, 'id' => $this->eUserOther['id']]);

		$this->assertTrue($fw->ok());
		$this->assertEquals(User::DARK, $this->getNav($this->eUser), 'le connecté passe au menu sombre');
		$this->assertEquals(User::LIGHT, $this->getNav($this->eUserOther), 'l\'id posté ne désigne personne d\'autre');

		$this->updateNav(['nav' => User::LIGHT]);
		$this->assertEquals(User::LIGHT, $this->getNav($this->eUser), 'et revient au menu clair');

	}

	public function testUnknownValueIsRefused(): void {

		$fw = $this->updateNav(['nav' => 'blue']);

		$this->assertTrue($fw->has('User::nav.check'));
		$this->assertEquals(User::LIGHT, $this->getNav($this->eUser));

	}

}
?>
