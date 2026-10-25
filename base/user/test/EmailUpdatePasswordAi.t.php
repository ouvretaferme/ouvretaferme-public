<?php
namespace user;

/**
 * Le changement d'adresse e-mail demande le mot de passe actuel.
 *
 * `/user/update:doEmail` ne demandait que d'être connecté : une session volée changeait
 * l'adresse du compte, puis demandait un lien de mot de passe oublié sur la nouvelle, et le
 * compte était pris pour de bon. La route exige désormais `passwordOld` de tout compte qui a un
 * mot de passe, par `SignUpLib::checkCurrentPassword()`, la même vérification que le changement
 * de mot de passe. Un compte sans mot de passe (connexion sociale seule) n'a rien à prouver, et
 * garde le geste.
 *
 * Les deux sens sont tenus : le bon mot de passe ouvre le changement, un autre ou aucun le refuse.
 *
 * Lancement : dev/test package=user file=EmailUpdatePasswordAi
 */
class EmailUpdatePasswordAiTest extends \Test {

	const PASSWORD = 'zzTestMotDePasseActuel42';

	private User $eUser;

	public function setUp(): void {

		User::model()->beginTransaction();

		$this->eUser = $this->createUser();

	}

	public function tearDown(): void {
		User::model()->rollBack();
	}

	private function createUser(): User {

		$eUser = new User([
			'firstName' => 'zzTest',
			'lastName' => 'Adresse',
			'email' => 'zztest.email.'.uniqid().'@example.org',
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

	/** Ce que la route fait avant de construire l'adresse. */
	private function check(?string $passwordOld): \FailWatch {

		$fw = new \FailWatch();

		SignUpLib::checkCurrentPassword(SignUpLib::getBasicAuth($this->eUser), $passwordOld);

		return $fw;

	}

	public function testTheAccountHasAPasswordToProve(): void {
		$this->assertTrue(SignUpLib::canUpdate($this->eUser)['hasPassword']);
	}

	public function testTheCurrentPasswordOpensTheChange(): void {

		$fw = $this->check(self::PASSWORD);

		$this->assertTrue($fw->ok(), 'le mot de passe actuel ouvre le changement : '.$fw);

	}

	public function testAWrongPasswordIsRefused(): void {

		$fw = $this->check('zzTestPasCeluiLa42');

		$this->assertTrue($fw->has('UserAuth::passwordOld.invalid'));

	}

	/** Le POST forgé qui ne poste pas le champ, celui par lequel passait la prise de compte. */
	public function testAMissingPasswordIsRefused(): void {

		$fw = $this->check(NULL);

		$this->assertTrue($fw->has('UserAuth::passwordOld.invalid'));

	}

	public function testTheCheckIsTheSameAsThePasswordChange(): void {

		$fw = new \FailWatch();

		SignUpLib::matchBasicPassword('update', $this->eUser, [
			'password' => 'zzTestNouveau42',
			'passwordBis' => 'zzTestNouveau42'
		]);

		$this->assertTrue($fw->has('UserAuth::passwordOld.invalid'), 'le changement de mot de passe refuse par le même nom');

	}

}
?>
