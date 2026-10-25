<?php
namespace dev;

/**
 * Une clé révoquée reste sur l'écran de sa structure, et dit quand elle a été révoquée.
 *
 * L'écran ne montrait que les clés vives de l'utilisateur connecté, alors qu'une clé révoquée n'est
 * jamais supprimée et que le journal des appels continue de lui imputer ses lignes : mesuré le
 * 2026-10-02 en dev, 1 134 des 1 359 lignes du journal étaient imputées à une clé qu'aucun écran ne
 * montrait plus. `AccessTokenLib::getByReference()` ouvre donc la liste à toute la structure et aux
 * clés mortes, ce qui expose deux contrats que rien d'autre ne tient :
 *
 * - la table porte aussi les jetons OAuth des applications tierces, qui vivent une heure, portent la
 *   même référence, naissent à chaque rafraîchissement et ne sont pas révoqués en expirant. Sans la
 *   borne sur le client réservé, une intégration tierce remplirait l'écran de clés sans nom.
 * - la date de révocation se perd dès qu'un des trois chemins de révocation écrit `revoked` seul, et
 *   la colonne étant nullable pour les clés révoquées avant son ajout, rien ne le signalerait.
 *
 * Lancement : dev/test package=dev file=ApiKeyRevoked
 */
class ApiKeyRevokedTest extends ApiTest {

	/**
	 * La référence des clés est la structure du décor, parce qu'une révocation s'inscrit au journal
	 * de sécurité de sa structure. L'autre référence n'existe nulle part : `apiAccessToken` ne porte
	 * la structure qu'en `reference: int32` brut, et la liste ne doit pas l'atteindre.
	 */
	const OTHER_REFERENCE = 999000002;

	protected function getTransactionModel(): \ModuleModel {
		return new \api\AccessTokenModel();
	}

	public function testListCarriesRevokedKeysOfEveryHolder(): void {

		[$eUserFirst, $eUserSecond] = $this->getUsers();

		$eAccessTokenLive = $this->createKey($eUserFirst, $this->eStructure['id']);
		$eAccessTokenRevoked = $this->createKey($eUserSecond, $this->eStructure['id'], revoked: TRUE);
		$eAccessTokenElsewhere = $this->createKey($eUserFirst, self::OTHER_REFERENCE);

		$ids = \api\AccessTokenLib::getByReference($this->eStructure['id'])->getIds();

		$this->assertTrue(in_array($eAccessTokenLive['id'], $ids), 'la clé vive du premier porteur est listée');
		$this->assertTrue(in_array($eAccessTokenRevoked['id'], $ids), 'la clé révoquée d\'un autre porteur est listée');
		$this->assertFalse(in_array($eAccessTokenElsewhere['id'], $ids), 'la clé du même porteur sur une autre référence ne l\'est pas');

	}

	public function testListLeavesOutThirdPartyTokens(): void {

		[$eUserFirst] = $this->getUsers();

		$eAccessTokenPersonal = $this->createKey($eUserFirst, $this->eStructure['id']);
		$eAccessTokenThirdParty = $this->createKey($eUserFirst, $this->eStructure['id'], eClient: $this->createThirdPartyClient());

		$ids = \api\AccessTokenLib::getByReference($this->eStructure['id'])->getIds();

		// Le cas accepté d'abord : une liste vide passerait l'assertion suivante sans rien prouver
		$this->assertTrue(in_array($eAccessTokenPersonal['id'], $ids), 'la clé personnelle est listée');
		$this->assertFalse(in_array($eAccessTokenThirdParty['id'], $ids), 'le jeton d\'une application tierce ne l\'est pas');

	}

	public function testEveryRevocationPathStampsTheDate(): void {

		[$eUserFirst] = $this->getUsers();

		$this->setClock('2026-06-15 10:00:00');

		$eAccessTokenByKey = $this->createKey($eUserFirst, $this->eStructure['id']);
		$eAccessTokenByUser = $this->createKey($eUserFirst, $this->eStructure['id']);
		$eAccessTokenByToken = $this->createKey($eUserFirst, $this->eStructure['id']);

		\api\AccessTokenLib::revoke($eAccessTokenByKey);
		\api\AccessTokenLib::revokeByUserAndReference($eUserFirst, $this->eStructure['id']);
		new \api\AccessTokenRepositoryLib()->revokeAccessToken($eAccessTokenByToken['tokenId']);

		$this->assertEquals('2026-06-15 10:00:00', $this->getRevokedAt($eAccessTokenByKey), 'le geste de l\'écran date la révocation');
		$this->assertEquals('2026-06-15 10:00:00', $this->getRevokedAt($eAccessTokenByUser), 'le départ d\'un membre la date aussi');
		$this->assertEquals('2026-06-15 10:00:00', $this->getRevokedAt($eAccessTokenByToken), 'la bibliothèque OAuth la date aussi');

	}

	public function testRevokedKeyRefusesASecondRevocation(): void {

		[$eUserFirst] = $this->getUsers();

		$eAccessToken = $this->createKey($eUserFirst, $this->eStructure['id']);

		$this->assertTrue(\api\AccessTokenLib::getById($eAccessToken['id'])->acceptRevoke(), 'une clé vive accepte d\'être révoquée');

		\api\AccessTokenLib::revoke($eAccessToken);

		// Les ids des clés révoquées sont désormais dans la page : sans cette garde, un POST rejoué
		// réécrirait revokedAt et effacerait la date de la vraie révocation
		$this->assertFalse(\api\AccessTokenLib::getById($eAccessToken['id'])->acceptRevoke(), 'une clé déjà révoquée la refuse');

	}

	private function getRevokedAt(\api\AccessToken $eAccessToken): ?string {

		return \api\AccessToken::model()
			->select(['revoked', 'revokedAt'])
			->whereId($eAccessToken['id'])
			->get()['revokedAt'];

	}

	/** Deux porteurs distincts, pour que « toutes les clés de la structure » ait un sens à mesurer. */
	private function getUsers(): array {

		$cUser = \user\User::model()
			->select(['id', 'firstName', 'lastName'])
			->sort(['id' => SORT_ASC])
			->getCollection(0, 2);

		if($cUser->count() < 2) {
			throw new \Exception('Deux comptes au moins sont nécessaires : recharge la base de dev');
		}

		$users = [];

		foreach($cUser as $eUser) {
			$users[] = $eUser;
		}

		return $users;

	}

	private function createThirdPartyClient(): \api\Client {

		$eClient = new \api\Client([
			'clientId' => 'zzTest-third-party-'.bin2hex(random_bytes(8)),
			'name' => 'zzTest application tierce',
			'secret' => bin2hex(random_bytes(16)),
			'redirectUris' => ['https://example.org/callback'],
			'isConfidential' => TRUE,
			'isPersonal' => FALSE,
		]);

		// Insertion directe : build() refuserait un clientId qu'aucun formulaire ne poste, et le décor
		// veut exactement ces valeurs
		\api\Client::model()->insert($eClient);

		return $eClient;

	}

	/**
	 * La clé s'insère directement : `PersonalAccessTokenLib::issue()` signe un JWT avec la paire RSA,
	 * qui n'est pas versionnée, et rien de ce que le test mesure ne dépend de la signature. Pour la
	 * même raison, `build()` n'a ici aucun `$_POST` à valider.
	 */
	private function createKey(\user\User $eUser, int $reference, bool $revoked = FALSE, \api\Client $eClient = new \api\Client()): \api\AccessToken {

		if($eClient->empty()) {
			$eClient = \api\ClientLib::getPersonalClient();
		}

		$eAccessToken = new \api\AccessToken([
			'tokenId' => str_pad(bin2hex(random_bytes(16)), 128, '0'),
			'client' => $eClient,
			'user' => $eUser,
			'reference' => $reference,
			'name' => 'zzTest clé',
			'scopes' => \api\ApiSetting::PERSONAL_SCOPE_NAMES,
			'expiryDateTime' => new \Sql('NOW() + INTERVAL 1 YEAR'),
			'revoked' => $revoked,
		]);

		\api\AccessToken::model()->insert($eAccessToken);

		return $eAccessToken;

	}

}
?>
