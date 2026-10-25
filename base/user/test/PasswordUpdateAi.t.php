<?php
namespace user;

/**
 * Le changement de mot de passe demande-t-il vraiment de connaître l'actuel ?
 *
 * `SignUpLib::matchBasicPassword()` relit l'authentification BASIC de l'utilisateur et compare le
 * `passwordOld` posté au condensat en base. Le test était **inversé** : `if(password_verify(...))`
 * levait `passwordOld.invalid` quand l'ancien mot de passe était le bon, et laissait passer quand il
 * était faux ou absent. La route `/user/update:doPassword` ne demandant rien d'autre que d'être
 * connecté (`SignUpLib::canUpdate()` rend `password => TRUE` pour tout utilisateur non vide), une
 * session volée suffisait à prendre le compte définitivement.
 *
 * Les quatre cas tiennent les deux sens, parce qu'un refus seul ne prouverait rien : la forme fautive
 * refusait elle aussi, simplement les mauvais. Le cas du mot de passe juste et celui du mot de passe
 * faux doivent donc diverger, et c'est leur écart qui est le contrôle.
 *
 * Le décor se sème par les Libs du produit : `UserLib::create()` pose le compte,
 * `SignUpLib::updatePassword()` pose l'authentification, qu'il insère quand elle manque. La
 * transaction de `setUp()` défait tout.
 *
 * Lancement : dev/test package=user file=PasswordUpdateAi
 */
class PasswordUpdateAiTest extends \Test {

	const PASSWORD = 'zzTestMotDePasseActuel42';
	const PASSWORD_NEW = 'zzTestMotDePasseNouveau42';

	private User $eUser;

	public function setUp(): void {

		User::model()->beginTransaction();

		$this->eUser = $this->createUser();

	}

	public function tearDown(): void {
		User::model()->rollBack();
	}

	/** Un compte avec une authentification BASIC dont le mot de passe est connu du test. */
	private function createUser(): User {

		$eUser = new User([
			'firstName' => 'zzTest',
			'lastName' => 'Mot de passe',
			'email' => 'zztest.password.'.uniqid().'@example.org',
			'visibility' => User::PRIVATE,
			'roleCustomer' => TRUE,
			'roleProducer' => FALSE,
			'roleConsultant' => FALSE,
			'roleTeacher' => FALSE
		]);

		UserLib::create($eUser);

		$eUser['auth'] = new UserAuth([
			'user' => $eUser,
			'type' => UserAuth::BASIC,
			'login' => $eUser['email'],
			'password' => password_hash(self::PASSWORD, PASSWORD_DEFAULT)
		]);

		SignUpLib::updatePassword($eUser);

		return $eUser;

	}

	/** Le condensat réellement en base, que seule une écriture acceptée fait changer. */
	private function getPasswordHash(): string {

		return UserAuth::model()
			->select('password')
			->whereUser($this->eUser)
			->whereType(UserAuth::BASIC)
			->get()['password'];

	}

	/** Le POST du formulaire de changement de mot de passe, rendu par son `FailWatch`. */
	private function submitPassword(array $input): \FailWatch {

		$eUser = UserLib::getById($this->eUser['id']);

		$fw = new \FailWatch();

		SignUpLib::matchBasicPassword('update', $eUser, $input);

		return $fw;

	}

	public function testTheCurrentPasswordOpensTheChange(): void {

		$fw = $this->submitPassword([
			'passwordOld' => self::PASSWORD,
			'password' => self::PASSWORD_NEW,
			'passwordBis' => self::PASSWORD_NEW
		]);

		$this->assertTrue($fw->ok(), 'le mot de passe actuel ouvre le changement : '.$fw);

	}

	public function testAWrongCurrentPasswordIsRefused(): void {

		$fw = $this->submitPassword([
			'passwordOld' => 'zzTestPasCeluiLa42',
			'password' => self::PASSWORD_NEW,
			'passwordBis' => self::PASSWORD_NEW
		]);

		$this->assertTrue($fw->ko(), 'un autre mot de passe ne l\'ouvre pas');
		$this->assertTrue($fw->has('UserAuth::passwordOld.invalid'), 'et le refus est bien celui du mot de passe actuel');

	}

	/**
	 * Le champ absent, c'est-à-dire le POST forgé qui n'essaie même pas de deviner : c'est par lui que
	 * passait la prise de compte, `password_verify()` ne rendant jamais vrai sur une chaîne vide.
	 */
	public function testAMissingCurrentPasswordIsRefused(): void {

		$fw = $this->submitPassword([
			'password' => self::PASSWORD_NEW,
			'passwordBis' => self::PASSWORD_NEW
		]);

		$this->assertTrue($fw->ko(), 'un mot de passe actuel non posté ne l\'ouvre pas');
		$this->assertTrue($fw->has('UserAuth::passwordOld.invalid'), 'et le refus est bien celui du mot de passe actuel');

	}

	/**
	 * Ce que le refus protège vraiment.
	 *
	 * `doPassword` (`base/user/page/update.p.php`) valide son `FailWatch` entre `matchBasicPassword()`
	 * et `updatePassword()`, donc un refus arrête la chaîne avant l'écriture. Le cas le joue en entier
	 * plutôt que de le déduire : il écrit ce que la page écrirait, et relit le condensat.
	 */
	public function testTheAccountIsNotTakenOverByARefusedChange(): void {

		$hashBefore = $this->getPasswordHash();

		$eUser = UserLib::getById($this->eUser['id']);

		$fw = new \FailWatch();

		SignUpLib::matchBasicPassword('update', $eUser, [
			'password' => self::PASSWORD_NEW,
			'passwordBis' => self::PASSWORD_NEW
		]);

		if($fw->ok()) {
			SignUpLib::updatePassword($eUser);
		}

		$hashAfter = $this->getPasswordHash();

		$this->assertEquals($hashBefore, $hashAfter, 'le condensat en base n\'a pas bougé');
		$this->assertTrue(password_verify(self::PASSWORD, $hashAfter), 'le mot de passe actuel ouvre toujours le compte');
		$this->assertFalse(password_verify(self::PASSWORD_NEW, $hashAfter), 'et celui que le POST forgé proposait ne l\'ouvre pas');

	}

}
?>
