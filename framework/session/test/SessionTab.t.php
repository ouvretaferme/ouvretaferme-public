<?php
namespace session;

/**
 * La session d'un onglet, sur un poste de dev qui l'a ouverte par son secret : ce que
 * `getTabSid()` tire de l'en-tête ou du corps, quand un chargement complet passe d'abord par le
 * shell, et l'origine qu'exige une session tirée du corps.
 *
 * Lancement : dev/test package=session file=SessionTab
 */
class SessionTabTest extends \Test {

	private array $serverInitial;
	private array $cookieInitial;
	private array $postInitial;
	private \ReflectionProperty $tab;
	private bool $tabInitial;
	private \ReflectionProperty $requestedWith;
	private ?string $requestedWithInitial;
	private \ReflectionProperty $requestMethod;
	private ?string $requestMethodInitial;
	private bool $tabEnabledInitial;

	public function setUp() {

		$this->serverInitial = $_SERVER;
		$this->cookieInitial = $_COOKIE;
		$this->postInitial = $_POST;

		$this->tab = new \ReflectionProperty(SessionLib::class, 'tab');
		$this->tabInitial = $this->tab->getValue();

		$this->requestedWith = new \ReflectionProperty(\Route::class, 'requestedWith');
		$this->requestedWithInitial = $this->requestedWith->getValue();

		$this->requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$this->requestMethodInitial = $this->requestMethod->getValue();

		$this->tabEnabledInitial = SessionLib::isTabEnabled();
		SessionLib::setTabEnabled(TRUE);

	}

	public function tearDown() {

		SessionLib::setTabEnabled($this->tabEnabledInitial);
		$this->tab->setValue(NULL, $this->tabInitial);
		$this->requestMethod->setValue(NULL, $this->requestMethodInitial);
		$this->requestedWith->setValue(NULL, $this->requestedWithInitial);

		$_POST = $this->postInitial;
		$_COOKIE = $this->cookieInitial;
		$_SERVER = $this->serverInitial;

	}

	public function testTabSid(): void {

		unset($_SERVER['HTTP_X_SESSION_TAB']);
		$this->assertEquals(NULL, SessionLib::getTabSid(), 'sans en-tête, la requête suit le cookie');

		$_SERVER['HTTP_X_SESSION_TAB'] = 'new';
		$this->assertEquals('', SessionLib::getTabSid(), 'new demande une session neuve');

		$_SERVER['HTTP_X_SESSION_TAB'] = SessionLib::TAB_COOKIE;
		$this->assertEquals(SessionLib::TAB_COOKIE, SessionLib::getTabSid(), 'un onglet sans session à lui suit le cookie, et le dit pour ne pas repasser par le shell');

		$_SERVER['HTTP_X_SESSION_TAB'] = '../../etc/passwd';
		$this->assertEquals('', SessionLib::getTabSid(), 'un identifiant mal formé vaut une demande de session neuve');

		$sid = SessionLib::createSid();

		$_SERVER['HTTP_X_SESSION_TAB'] = $sid;
		$this->assertEquals($sid, SessionLib::getTabSid(), 'un identifiant bien formé est rendu tel quel');

	}

	public function testTabSidPosted(): void {

		unset($_SERVER['HTTP_X_SESSION_TAB']);

		$sid = SessionLib::createSid();

		$_POST['x-session-tab'] = $sid;
		$this->assertEquals($sid, SessionLib::getTabSid(), 'sans en-tête, l\'identifiant vient du champ d\'un formulaire posté hors ajax');

		$_SERVER['HTTP_X_SESSION_TAB'] = SessionLib::TAB_COOKIE;
		$this->assertEquals(SessionLib::TAB_COOKIE, SessionLib::getTabSid(), 'l\'en-tête passe avant le champ');

		unset($_SERVER['HTTP_X_SESSION_TAB']);

		$_POST['x-session-tab'] = '../../etc/passwd';
		$this->assertEquals('', SessionLib::getTabSid(), 'un champ mal formé vaut une demande de session neuve, comme l\'en-tête');

		$_POST['x-session-tab'] = [$sid];
		$this->assertEquals('', SessionLib::getTabSid(), 'un champ en tableau vaut une demande de session neuve');

		$_POST['x-session-tab'] = '';
		$this->assertEquals(NULL, SessionLib::getTabSid(), 'un champ vide laisse la requête suivre le cookie');

	}

	public function testOriginRequiredForTabSidPosted(): void {

		$checkOrigin = new \ReflectionMethod(SessionLib::class, 'checkOrigin');

		$this->requestMethod->setValue(NULL, 'POST');
		$this->tab->setValue(NULL, TRUE);
		$_SERVER['HTTP_HOST'] = 'www.dev-ouvretaferme.org';
		unset($_SERVER['HTTP_X_SESSION_TAB'], $_SERVER['HTTP_ORIGIN']);

		$_POST['x-session-tab'] = SessionLib::createSid();

		$this->assertTrue($this->isRefused($checkOrigin), 'une session d\'onglet tirée du corps, sans origine, est refusée');

		$_SERVER['HTTP_ORIGIN'] = 'http://evil.dev-ouvretaferme.org';
		$this->assertTrue($this->isRefused($checkOrigin), 'une session d\'onglet tirée du corps, d\'une autre origine, est refusée');

		$_SERVER['HTTP_ORIGIN'] = \Lime::getProtocol().'://www.dev-ouvretaferme.org:8080';
		$this->assertTrue($this->isRefused($checkOrigin), 'une session d\'onglet tirée du corps, d\'un autre port du même hôte, est refusée');

		$_SERVER['HTTP_ORIGIN'] = 'null';
		$this->assertTrue($this->isRefused($checkOrigin), 'une session d\'onglet tirée du corps, d\'une origine opaque, est refusée');

		$_SERVER['HTTP_ORIGIN'] = \Lime::getProtocol().'://www.dev-ouvretaferme.org';
		$this->assertFalse($this->isRefused($checkOrigin), 'une session d\'onglet tirée du corps, de la même origine, passe');

		unset($_SERVER['HTTP_ORIGIN']);
		$_SERVER['HTTP_X_SESSION_TAB'] = SessionLib::createSid();
		$this->assertTrue($this->isRefused($checkOrigin), 'un corps qui porte le champ exige l\'origine, même quand l\'en-tête l\'emporte');

		unset($_POST['x-session-tab']);
		$this->assertFalse($this->isRefused($checkOrigin), 'une session d\'onglet tirée de l\'en-tête garde la règle commune : sans origine, elle passe');

		$this->requestMethod->setValue(NULL, 'GET');
		$_POST['x-session-tab'] = SessionLib::createSid();
		$this->assertFalse($this->isRefused($checkOrigin), 'une méthode qui ne change rien n\'est pas contrôlée');

	}

	private function isRefused(\ReflectionMethod $checkOrigin): bool {

		try {
			$checkOrigin->invoke(NULL);
			return FALSE;
		} catch(\NotAllowedAction) {
			return TRUE;
		}

	}

	public function testClosedWithoutActivation(): void {

		SessionLib::setTabEnabled(FALSE);

		$_SERVER['HTTP_X_SESSION_TAB'] = 'new';
		$this->assertEquals(NULL, SessionLib::getTabSid(), 'sans activation par le secret, l\'en-tête est ignoré');

		unset($_SERVER['HTTP_X_SESSION_TAB']);
		$_POST['x-session-tab'] = SessionLib::createSid();
		$this->assertEquals(NULL, SessionLib::getTabSid(), 'sans activation par le secret, le champ est ignoré');

		$this->requestedWith->setValue(NULL, 'http');
		$this->requestMethod->setValue(NULL, 'GET');
		$_SERVER['HTTP_UPGRADE_INSECURE_REQUESTS'] = '1';
		$_COOKIE['tabidentity'] = '1';

		$needsTabShell = new \ReflectionMethod(SessionLib::class, 'needsTabShell');
		$this->assertFalse($needsTabShell->invoke(NULL), 'sans activation par le secret, un navigateur marqué ne voit pas le shell');

	}

	public function testTabShell(): void {

		$needsTabShell = new \ReflectionMethod(SessionLib::class, 'needsTabShell');

		$this->requestedWith->setValue(NULL, 'http');
		$this->requestMethod->setValue(NULL, 'GET');
		$_SERVER['HTTP_UPGRADE_INSECURE_REQUESTS'] = '1';
		unset($_SERVER['HTTP_SEC_FETCH_DEST'], $_COOKIE['tabidentity']);

		$this->assertFalse($needsTabShell->invoke(NULL), 'un navigateur qui n\'a jamais pris d\'identité d\'onglet ne voit jamais le shell');

		$_COOKIE['tabidentity'] = '1';
		$this->assertTrue($needsTabShell->invoke(NULL), 'un chargement complet d\'un navigateur marqué passe par le shell, servi en http sans Sec-Fetch-Dest');

		// Chaque cas reproduit les en-têtes que Chrome envoie à une origine sûre, Sec-Fetch-Dest compris
		$_SERVER['HTTP_SEC_FETCH_DEST'] = 'iframe';
		$this->assertTrue($needsTabShell->invoke(NULL), 'un cadre passe par le shell, qui lui donne l\'identité de son onglet');

		unset($_SERVER['HTTP_UPGRADE_INSECURE_REQUESTS']);

		$_SERVER['HTTP_SEC_FETCH_DEST'] = 'image';
		$this->assertFalse($needsTabShell->invoke(NULL), 'une image reçoit son contenu et non le shell');

		$_SERVER['HTTP_SEC_FETCH_DEST'] = 'empty';
		$this->assertFalse($needsTabShell->invoke(NULL), 'un lien download reçoit son fichier et non le shell');

		unset($_SERVER['HTTP_SEC_FETCH_DEST']);
		$this->assertFalse($needsTabShell->invoke(NULL), 'une requête sans aucun de ces en-têtes, curl ou image servie en http, suit le cookie');

		$_SERVER['HTTP_UPGRADE_INSECURE_REQUESTS'] = '1';

		$this->requestedWith->setValue(NULL, 'ajax');
		$this->assertFalse($needsTabShell->invoke(NULL), 'une requête ajax porte déjà son en-tête');

		$this->requestedWith->setValue(NULL, 'http');
		$this->requestMethod->setValue(NULL, 'POST');
		$this->assertFalse($needsTabShell->invoke(NULL), 'un POST ne se rejoue pas');

	}

}
?>
