<?php
namespace dev;

/**
 * Une application autorisée par OAuth se voit et se révoque sur l'écran des clés de sa structure.
 *
 * league fait tourner les jetons à chaque renouvellement : une autorisation vivante est un jeton non
 * révoqué, encore valable ou accompagné d'un jeton de renouvellement valable. La liste regroupe par
 * application et par personne, et la révocation coupe toute la chaîne, puis s'inscrit au journal de
 * sécurité de la structure.
 *
 * Lancement : dev/test package=dev file=ApiApplicationAi
 */
class ApiApplicationAiTest extends ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new \api\AccessTokenModel();
	}

	public function testListKeepsLiveAuthorizationsOnly(): void {

		[$eUserFirst, $eUserSecond] = $this->getUsers();
		$eClient = $this->createClient();
		$reference = $this->eStructure['id'];

		$this->setClock('2026-10-08 10:00:00');

		$this->createAuthCode($eClient, $eUserFirst, '2026-10-01 09:00:00');

		$eAccessTokenLive = $this->createToken($eClient, $eUserFirst, '2026-10-08 10:30:00');
		$eAccessTokenRefreshed = $this->createToken($eClient, $eUserSecond, '2026-10-08 08:00:00');
		$this->createRefreshToken($eAccessTokenRefreshed, '2026-11-01 00:00:00');

		$eClientDead = $this->createClient();
		$this->createToken($eClientDead, $eUserFirst, '2026-10-08 08:00:00');
		$this->createToken($eClientDead, $eUserSecond, '2026-10-08 11:00:00', revoked: TRUE);

		$this->createLog($eAccessTokenLive, '2026-10-08 09:45:00');
		$this->createLog($eAccessTokenLive, '2026-10-08 09:50:00');

		$cAccessToken = \api\AccessTokenLib::getApplicationsByReference($reference)
			->find(fn($eAccessToken) => in_array($eAccessToken['client']['id'], [$eClient['id'], $eClientDead['id']], TRUE));

		$this->assertEquals(2, $cAccessToken->count(), 'une ligne par personne pour l\'application vivante, aucune pour l\'autre');

		$eAccessTokenFirst = $cAccessToken->find(fn($eAccessToken) => $eAccessToken['user']['id'] === $eUserFirst['id'])->first();
		$eAccessTokenSecond = $cAccessToken->find(fn($eAccessToken) => $eAccessToken['user']['id'] === $eUserSecond['id'])->first();

		$this->assertEquals($eAccessTokenLive['id'], $eAccessTokenFirst['id'], 'le jeton encore valable porte l\'autorisation');
		$this->assertEquals('2026-10-01 09:00:00', $eAccessTokenFirst['authorizedAt'], 'le consentement se date par le code d\'autorisation');
		$this->assertEquals('2026-10-08 09:50:00', $eAccessTokenFirst['lastCall'], 'le dernier appel est le plus récent du journal');

		$this->assertEquals($eAccessTokenRefreshed['id'], $eAccessTokenSecond['id'], 'un jeton expiré reste vivant par son jeton de renouvellement');
		$this->assertEquals(NULL, $eAccessTokenSecond['lastCall'], 'sans appel au journal, aucune date');

	}

	public function testRevokeCutsTheChainAndIsLogged(): void {

		[$eUserFirst, $eUserSecond] = $this->getUsers();
		$eClient = $this->createClient();
		$reference = $this->eStructure['id'];

		$eAccessToken = $this->createToken($eClient, $eUserFirst, '2030-01-01 00:00:00');
		$eRefreshToken = $this->createRefreshToken($eAccessToken, '2030-02-01 00:00:00');
		$eAccessTokenOther = $this->createToken($eClient, $eUserSecond, '2030-01-01 00:00:00');

		\api\AccessTokenLib::revokeApplication($eClient, $eUserFirst, $reference);

		$this->assertTrue(\api\AccessTokenLib::getById($eAccessToken['id'])['revoked'], 'le jeton est révoqué');
		$this->assertTrue(\api\RefreshToken::model()->select('revoked')->whereId($eRefreshToken['id'])->get()['revoked'], 'son jeton de renouvellement aussi');
		$this->assertFalse(\api\AccessTokenLib::getById($eAccessTokenOther['id'])['revoked'], 'l\'autorisation donnée par une autre personne reste');

		$eLog = \securing\Log::model()
			->select(['module', 'data'])
			->whereEvent(\securing\LogSetting::EVENT_API_APPLICATION_REVOKE)
			->sort(['id' => SORT_DESC])
			->get();

		$this->assertEquals(\securing\Log::SETTINGS, $eLog['module'], 'le geste entre au journal de sécurité, sous Paramètres');
		$this->assertEquals($eClient['id'], $eLog['data']['client']['id'], 'il nomme l\'application');
		$this->assertEquals($eUserFirst['id'], $eLog['data']['user']['id'], 'et la personne qui l\'avait autorisée');

	}

	/** Deux personnes, pour qu'une autorisation se regroupe par application et par personne. */
	private function getUsers(): array {

		$cUser = \user\User::model()
			->select(['id', 'firstName', 'lastName'])
			->sort(['id' => SORT_ASC])
			->getCollection(0, 2);

		if($cUser->count() < 2) {
			throw new \Exception('Deux comptes au moins sont nécessaires : recharge la base de dev');
		}

		return [$cUser->first(), $cUser->offsetGet(1)];

	}

	// Les lignes de décor s'insèrent directement : aucun formulaire ne poste un client, un jeton ou un
	// code, ils naissent de league, et le test veut exactement ces dates et ces états

	private function createClient(): \api\Client {

		$eClient = new \api\Client([
			'clientId' => 'zzTest-application-'.bin2hex(random_bytes(8)),
			'name' => 'zzTest application',
			'redirectUris' => ['https://example.org/callback'],
			'isConfidential' => FALSE,
			'isPersonal' => FALSE,
		]);

		\api\Client::model()->insert($eClient);

		return $eClient;

	}

	private function createToken(\api\Client $eClient, \user\User $eUser, string $expiryDateTime, bool $revoked = FALSE): \api\AccessToken {

		$eAccessToken = new \api\AccessToken([
			'tokenId' => str_pad(bin2hex(random_bytes(16)), 128, '0'),
			'client' => $eClient,
			'user' => $eUser,
			'reference' => $this->eStructure['id'],
			'scopes' => ['general:read'],
			'expiryDateTime' => $expiryDateTime,
			'revoked' => $revoked,
		]);

		\api\AccessToken::model()->insert($eAccessToken);

		return $eAccessToken;

	}

	private function createRefreshToken(\api\AccessToken $eAccessToken, string $expiryDateTime): \api\RefreshToken {

		$eRefreshToken = new \api\RefreshToken([
			'tokenId' => str_pad(bin2hex(random_bytes(16)), 128, '0'),
			'accessToken' => $eAccessToken,
			'expiryDateTime' => $expiryDateTime,
		]);

		\api\RefreshToken::model()->insert($eRefreshToken);

		return $eRefreshToken;

	}

	private function createAuthCode(\api\Client $eClient, \user\User $eUser, string $createdAt): void {

		$eAuthCode = new \api\AuthCode([
			'code' => str_pad(bin2hex(random_bytes(16)), 128, '0'),
			'client' => $eClient,
			'user' => $eUser,
			'reference' => $this->eStructure['id'],
			'scopes' => ['general:read'],
			'redirectUri' => 'https://example.org/callback',
			'expiryDateTime' => $createdAt,
			'revoked' => TRUE,
			'createdAt' => $createdAt,
		]);

		\api\AuthCode::model()->insert($eAuthCode);

	}

	private function createLog(\api\AccessToken $eAccessToken, string $createdAt): void {

		$eLog = new \api\Log([
			'token' => $eAccessToken,
			'reference' => $this->eStructure['id'],
			'requestMethod' => \api\Log::GET,
			'requestUrl' => '/0.1beta/me',
			'route' => 'me',
			'status' => \api\Log::SUCCESS,
			'responseStatusCode' => 200,
			'createdAt' => $createdAt,
		]);

		\api\Log::model()->insert($eLog);

	}

}
?>
