<?php
namespace dev;

/**
 * Une application tierce se crée depuis l'admin, avec ses adresses de retour et ses accès, et se
 * désactive en coupant tout ce que les structures lui avaient donné.
 *
 * Lancement : dev/test package=dev file=ApiClientAi
 */
class ApiClientAiTest extends ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new \api\ClientModel();
	}

	public function testRedirectUrisAreHttpsOrLoopback(): void {

		$eClient = $this->build("https://pluviose.example/callback\r\n\r\nhttp://localhost:3001/callback\nhttps://pluviose.example/callback", ['production:read']);

		$this->assertEquals(['https://pluviose.example/callback', 'http://localhost:3001/callback'], $eClient['redirectUris'], 'une adresse par ligne, sans ligne vide ni doublon');

		foreach([
			'http://pluviose.example/callback' => 'http hors de la boucle locale',
			'https://pluviose.example/callback#fragment' => 'un fragment',
			'pluviose.example/callback' => 'une adresse relative',
			'' => 'aucune adresse',
		] as $redirectUris => $reason) {

			$fw = new \FailWatch();
			$this->build($redirectUris, ['production:read']);
			$this->assertTrue($fw->ko(), 'refusé : '.$reason);

		}

	}

	public function testScopesAreKnownAndWriteGivesRead(): void {

		$eClient = $this->build('https://pluviose.example/callback', ['production:write']);
		$this->assertEquals(['production:read', 'production:write'], $eClient['cScope']->getColumn('name'), 'l\'écriture donne aussi la lecture');

		$fw = new \FailWatch();
		$this->build('https://pluviose.example/callback', ['production:read', 'inconnu:read']);
		$this->assertTrue($fw->ko(), 'un accès inconnu est refusé');

		$fw = new \FailWatch();
		$this->build('https://pluviose.example/callback', []);
		$this->assertTrue($fw->ko(), 'aucun accès est refusé');

	}

	public function testCreateDrawsIdentifierAndScopes(): void {

		$eClient = $this->build('https://pluviose.example/callback', ['production:read']);
		\api\ClientLib::create($eClient);

		$this->assertTrue(preg_match('/^[0-9a-f]{32}$/', $eClient['clientId']) === 1, 'l\'identifiant est tiré au hasard');
		$this->assertEquals(['production:read'], \api\ClientScopeLib::getScopesByClient($eClient)->getColumn('name'), 'les accès sont rattachés au client');
		$this->assertTrue(new \api\ClientRepositoryLib()->getClientEntity($eClient['clientId']) !== NULL, 'league trouve le client');

	}

	public function testDisableCutsEverything(): void {

		$eClient = $this->build('https://pluviose.example/callback', ['production:read']);
		\api\ClientLib::create($eClient);

		$eUser = \user\User::model()->select('id')->sort(['id' => SORT_ASC])->get();

		$eAccessToken = new \api\AccessToken([
			'tokenId' => str_pad(bin2hex(random_bytes(16)), 128, '0'),
			'client' => $eClient,
			'user' => $eUser,
			'reference' => $this->eStructure['id'],
			'scopes' => ['production:read'],
			'expiryDateTime' => '2030-01-01 00:00:00',
		]);

		// Le jeton naît de league, aucun formulaire ne le poste
		\api\AccessToken::model()->insert($eAccessToken);

		$eClient = \api\ClientLib::getById($eClient['id']);
		$this->assertTrue($eClient->acceptDisable(), 'une application active se désactive');

		\api\ClientLib::disable($eClient);

		$this->assertTrue(\api\AccessTokenLib::getById($eAccessToken['id'])['revoked'], 'ses jetons sont révoqués');
		$this->assertTrue(new \api\ClientRepositoryLib()->getClientEntity($eClient['clientId']) === NULL, 'league ne la trouve plus');

		$eClient = \api\ClientLib::getById($eClient['id']);
		$this->assertTrue($eClient->acceptEnable(), 'une application désactivée se réactive');

		\api\ClientLib::enable($eClient);

		$this->assertTrue(new \api\ClientRepositoryLib()->getClientEntity($eClient['clientId']) !== NULL, 'réactivée, league la retrouve');
		$this->assertTrue(\api\AccessTokenLib::getById($eAccessToken['id'])['revoked'], 'ses anciens jetons restent révoqués');

	}

	public function testPersonalClientStaysOut(): void {

		$eClient = \api\ClientLib::getPersonalClient();

		$this->assertFalse($eClient->acceptDisable(), 'le client des clés personnelles ne se désactive pas');
		$this->assertFalse($eClient->acceptEnable(), 'ni ne se réactive');

	}

	private function build(string $redirectUris, array $scopes): \api\Client {

		$eClient = new \api\Client();
		$eClient->build(\api\ClientLib::getPropertiesCreate(), [
			'name' => 'zzTest Pluviose',
			'redirectUris' => $redirectUris,
			'cScope' => $scopes,
		]);

		return $eClient;

	}

}
?>
