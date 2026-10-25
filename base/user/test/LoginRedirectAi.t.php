<?php
namespace user;

/**
 * La page où la connexion dépose l'utilisateur reste sur le site.
 *
 * `ConnectionLib::getRedirectLogin()` acceptait toute URL en http ou https venue du paramètre
 * redirect : un lien vers la page de connexion, construit par un tiers, emmenait le compte tout
 * juste connecté sur le site du tiers. Le refus renvoie NULL, et la page de connexion tombe alors
 * sur l'accueil.
 *
 * Le compte est semé dans la transaction et mis en ligne le temps du cas, parce que la méthode lit
 * en base le compte connecté (rebond de son adresse).
 *
 * Lancement : dev/test package=user file=LoginRedirectAi
 */
class LoginRedirectAiTest extends \Test {

	private array $requestInitial;

	public function setUp(): void {

		$this->requestInitial = $_REQUEST;

		User::model()->beginTransaction();

		$eUser = new User([
			'firstName' => 'zzTest',
			'lastName' => 'Redirection',
			'email' => 'zztest.redirect.'.uniqid().'@example.org',
			'visibility' => User::PRIVATE,
			'roleCustomer' => TRUE,
			'roleProducer' => FALSE,
			'roleConsultant' => FALSE,
			'roleTeacher' => FALSE
		]);

		UserLib::create($eUser);

		ConnectionLib::setOnline($eUser);

	}

	public function tearDown(): void {

		\session\SessionLib::clean();
		ConnectionLib::setOnline(new User());

		User::model()->rollBack();

		$_REQUEST = $this->requestInitial;

	}

	private function getRedirectLogin(?string $redirect): ?string {

		if($redirect === NULL) {
			unset($_REQUEST['redirect']);
		} else {
			$_REQUEST['redirect'] = $redirect;
		}

		return ConnectionLib::getRedirectLogin();

	}

	public function testAPathIsCompletedWithTheSiteUrl(): void {

		$redirect = $this->getRedirectLogin('/structure/7/vente?success=user\\User::welcome');

		$this->assertTrue(str_starts_with((string)$redirect, \Lime::getUrl().'/structure/7/vente?'), 'le chemin demandé, sur le site, sans le succès qu\'il portait : '.$redirect);

	}

	public function testTheSiteUrlIsKept(): void {

		$redirect = $this->getRedirectLogin(\Lime::getUrl().'/user/update:email');

		$this->assertTrue(str_starts_with((string)$redirect, \Lime::getUrl().'/user/update:email?'), 'une URL du site est gardée : '.$redirect);

	}

	public function testAnotherHostIsDropped(): void {

		$this->assertEquals(NULL, $this->getRedirectLogin('https://evil.example/'), 'un autre hôte');
		$this->assertEquals(NULL, $this->getRedirectLogin('//evil.example/'), 'une URL sans schéma');

	}

	public function testWithoutParameterTheHomeIsUsed(): void {

		$redirect = $this->getRedirectLogin(NULL);

		$this->assertTrue(str_starts_with((string)$redirect, '/?'), 'l\'accueil : '.$redirect);

	}

}
?>
