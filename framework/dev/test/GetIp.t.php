<?php
namespace dev;

/**
 * L'adresse IP que le cadriciel prête à une requête, et sur laquelle reposent les bannissements
 * (`user\BanLib`), la limitation des tentatives (`user\ThrottleAiLib`) et le journal des
 * connexions (`user\Log.ip`).
 *
 * `getIp()` lisait `HTTP_CLIENT_IP` puis `HTTP_X_FORWARDED_FOR` avant `REMOTE_ADDR`. Ces deux
 * en-têtes sont écrits par le client lui-même, et rien ne se tient devant l'application pour les
 * réécrire : un poste banni ou limité choisissait donc l'adresse sous laquelle il se présentait,
 * et le webhook Brevo, qui vérifiait une plage d'adresses, s'ouvrait à qui posait l'en-tête.
 * Seule `REMOTE_ADDR` vient de la connexion TCP.
 *
 * Lancement : dev/test package=dev file=GetIp
 */
class GetIpTest extends \Test {

	const REMOTE = '203.0.113.42';
	const FORGED = '198.51.100.7';

	private array $serverInitial;

	public function setUp(): void {

		$this->serverInitial = $_SERVER;

		unset($_SERVER['HTTP_CLIENT_IP'], $_SERVER['HTTP_X_FORWARDED_FOR']);
		$_SERVER['REMOTE_ADDR'] = self::REMOTE;

	}

	public function tearDown(): void {
		$_SERVER = $this->serverInitial;
	}

	public function testTheConnectionAddressIsTheOne(): void {
		$this->assertEquals(self::REMOTE, getIp());
	}

	public function testAForwardedForHeaderIsIgnored(): void {

		$_SERVER['HTTP_X_FORWARDED_FOR'] = self::FORGED;

		$this->assertEquals(self::REMOTE, getIp(), 'un X-Forwarded-For posé par le client ne change pas l\'adresse');

	}

	public function testAClientIpHeaderIsIgnored(): void {

		$_SERVER['HTTP_CLIENT_IP'] = self::FORGED;

		$this->assertEquals(self::REMOTE, getIp(), 'un Client-IP posé par le client ne change pas l\'adresse');

	}

	public function testAForwardedListIsIgnored(): void {

		$_SERVER['HTTP_X_FORWARDED_FOR'] = self::FORGED.', 10.0.0.1';

		$this->assertEquals(self::REMOTE, getIp(), 'une liste d\'adresses relayées non plus');

	}

	/** Le cas de la ligne de commande, qui n'a pas de connexion. */
	public function testWithoutConnectionTheLoopbackIsReturned(): void {

		unset($_SERVER['REMOTE_ADDR']);

		$this->assertEquals('127.0.0.1', getIp());

	}

}
?>
