<?php
namespace api;

require_once __DIR__.'/../vendor/autoload.php';

class AccessTokenLib extends AccessTokenCrud {

	use \Notifiable;

	public static function getByUserAndReference(\user\User $eUser, int $reference): \Collection {

		return AccessToken::model()
			->select(AccessToken::getSelection())
			->whereUser($eUser)
			->whereReference($reference)
			->whereRevoked(FALSE)
			->sort(['createdAt' => SORT_DESC])
			->getCollection();

	}

	/**
	 * Toutes les clés personnelles émises pour une référence, révoquées comprises, quel que soit leur
	 * porteur.
	 *
	 * Une clé révoquée n'est jamais supprimée, et le journal des appels impute ses lignes à elle : la
	 * borner au porteur connecté et aux clés vives laissait l'écran sans rien pour expliquer la
	 * quasi-totalité de ce journal.
	 *
	 * La borne sur le client réservé n'est pas cosmétique : la table porte aussi les jetons des
	 * applications tierces, qui vivent une heure, portent la même référence, naissent à chaque
	 * rafraîchissement et ne sont pas révoqués en expirant. Sans elle, une intégration tierce
	 * remplirait cet écran d'une vingtaine de clés sans nom par jour.
	 */
	public static function getByReference(int $reference): \Collection {

		$properties = AccessToken::getSelection();
		$properties['user'] = ['firstName', 'lastName'];

		return AccessToken::model()
			->select($properties)
			->whereReference($reference)
			->whereClient('IN', Client::model()->select('id')->whereIsPersonal(TRUE)->getSql())
			->sort(['createdAt' => SORT_DESC])
			->getCollection();

	}

	public static function revoke(AccessToken $e): void {

		$e->expects(['id', 'name', 'reference']);

		AccessToken::model()->whereId($e['id'])->update(self::getRevokeValues());

		self::notify('keyRevoke', $e);

	}

	/**
	 * Les applications qu'une référence a autorisées et qui peuvent encore appeler l'API : un élément
	 * par application et par personne qui a donné son consentement, le jeton le plus récent.
	 *
	 * league fait tourner les jetons à chaque renouvellement et révoque le précédent : une
	 * autorisation vivante n'a donc qu'un jeton non révoqué, valable une heure, et elle survit à son
	 * expiration tant que le jeton de renouvellement qui l'accompagne est valable.
	 *
	 * Le consentement se date par le code d'autorisation, que rien ne purge : le premier jeton de la
	 * chaîne est révoqué depuis longtemps et ne se distingue pas des autres.
	 */
	public static function getApplicationsByReference(int $reference): \Collection {

		$now = AccessToken::model()->format(\Clock::datetime());

		$mRefreshToken = new RefreshTokenModel()
			->whereAccessToken(new \Sql('m1.id'))
			->whereRevoked(FALSE)
			->where('s1.expiryDateTime > '.$now);

		$properties = AccessToken::getSelection();
		$properties['client'] = ['name'];
		$properties['user'] = ['firstName', 'lastName'];

		$cAccessToken = AccessToken::model()
			->select($properties)
			->whereReference($reference)
			->whereClient('IN', Client::model()->select('id')->whereIsPersonal(FALSE)->getSql())
			->whereRevoked(FALSE)
			->where('(m1.expiryDateTime > '.$now.' OR '.$mRefreshToken->existsSql().')')
			->sort(['createdAt' => SORT_DESC, 'id' => SORT_DESC])
			->getCollection();

		$cAccessTokenApplication = new \Collection();

		foreach($cAccessToken as $eAccessToken) {

			$key = self::getApplicationKey($eAccessToken['client'], $eAccessToken['user']);

			if($cAccessTokenApplication->offsetExists($key) === FALSE) {
				$eAccessToken['authorizedAt'] = NULL;
				$eAccessToken['lastCall'] = NULL;
				$cAccessTokenApplication[$key] = $eAccessToken;
			}

		}

		if($cAccessTokenApplication->empty()) {
			return $cAccessTokenApplication;
		}

		$cAuthCode = AuthCode::model()
			->select([
				'client', 'user',
				'authorizedAt' => new \Sql('MAX(m1.createdAt)')
			])
			->whereReference($reference)
			->group(['client', 'user'])
			->getCollection();

		foreach($cAuthCode as $eAuthCode) {

			$key = self::getApplicationKey($eAuthCode['client'], $eAuthCode['user']);

			if($cAccessTokenApplication->offsetExists($key)) {
				$cAccessTokenApplication[$key]['authorizedAt'] = $eAuthCode['authorizedAt'];
			}

		}

		// Le journal ne garde que ApiSetting::LOG_RETENTION_DAYS jours : un appel par jeton suffit à
		// borner la lecture, et chaque jeton se rattache ensuite à son application
		$cLog = Log::model()
			->select([
				'token' => ['client', 'user'],
				'lastCall' => new \Sql('MAX(m1.createdAt)')
			])
			->whereReference($reference)
			->where('m1.token IS NOT NULL')
			->group(['token'])
			->getCollection();

		foreach($cLog as $eLog) {

			$key = self::getApplicationKey($eLog['token']['client'], $eLog['token']['user']);

			if(
				$cAccessTokenApplication->offsetExists($key) and
				$cAccessTokenApplication[$key]['lastCall'] < $eLog['lastCall']
			) {
				$cAccessTokenApplication[$key]['lastCall'] = $eLog['lastCall'];
			}

		}

		return $cAccessTokenApplication;

	}

	private static function getApplicationKey(Client $eClient, \user\User $eUser): string {
		return $eClient['id'].'-'.$eUser['id'];
	}

	/**
	 * Retire à une application l'accès qu'une personne lui a donné sur une référence : ses jetons,
	 * leurs jetons de renouvellement, et un code d'autorisation qui n'aurait pas encore été échangé.
	 * L'application devra redemander le consentement.
	 */
	public static function revokeApplication(Client $eClient, \user\User $eUser, int $reference): void {

		$eClient->expects(['id', 'name']);

		self::revokeCollection(
			AccessToken::model()
				->select('id')
				->whereClient($eClient)
				->whereUser($eUser)
				->whereReference($reference)
				->whereRevoked(FALSE)
				->getCollection()
		);

		AuthCode::model()
			->whereClient($eClient)
			->whereUser($eUser)
			->whereReference($reference)
			->whereRevoked(FALSE)
			->update(['revoked' => TRUE]);

		self::notify('applicationRevoke', $eClient, $eUser, $reference);

	}

	public static function revokeByClient(Client $eClient): void {

		self::revokeCollection(
			AccessToken::model()
				->select('id')
				->whereClient($eClient)
				->whereRevoked(FALSE)
				->getCollection()
		);

		AuthCode::model()
			->whereClient($eClient)
			->whereRevoked(FALSE)
			->update(['revoked' => TRUE]);

	}

	/**
	 * Ce qu'une révocation écrit, quel que soit le chemin qui la décide : le geste de l'écran, le
	 * départ d'un membre, et la bibliothèque OAuth par AccessTokenRepositoryLib. La date se perd dès
	 * qu'un de ces chemins écrit `revoked` seul, et rien ne le signalerait, la colonne étant nullable
	 * pour les clés révoquées avant son ajout.
	 */
	public static function getRevokeValues(): array {

		return ['revoked' => TRUE, 'revokedAt' => \Clock::datetime()];

	}

	public static function revokeByReference(int $reference): void {

		self::revokeCollection(
			AccessToken::model()
				->select('id')
				->whereReference($reference)
				->whereRevoked(FALSE)
				->getCollection()
		);

	}

	/**
	 * Les clés qu'un utilisateur a émises pour une référence, quand il cesse d'y avoir droit.
	 *
	 * Le droit se relit aussi à chaque appel, la révocation ne le remplace pas : elle retire la clé
	 * des écrans et la rend définitivement morte, là où le contrôle à l'usage la rouvrirait si son
	 * porteur revenait.
	 */
	public static function revokeByUserAndReference(\user\User $eUser, int $reference): void {

		if($eUser->empty()) {
			return;
		}

		self::revokeCollection(
			AccessToken::model()
				->select('id')
				->whereUser($eUser)
				->whereReference($reference)
				->whereRevoked(FALSE)
				->getCollection()
		);

	}

	private static function revokeCollection(\Collection $cAccessToken): void {

		// Un IN vide rend (0), donc aucune ligne : sans ce retour, les deux écritures tourneraient
		// pour rien à chaque départ d'un membre qui n'a jamais créé de clé
		if($cAccessToken->empty()) {
			return;
		}

		AccessToken::model()
			->whereId('IN', $cAccessToken)
			->update(self::getRevokeValues());

		RefreshToken::model()
			->whereAccessToken('IN', $cAccessToken)
			->update(['revoked' => TRUE]);

	}

	public static function deleteAuthCodeByReference(int $reference): void {

		AuthCode::model()
			->whereReference($reference)
			->delete();

	}

	/**
	 * La clé que porte une requête dont le jeton est signé par le serveur, vide sinon.
	 *
	 * L'authentification refuse sans distinguer un jeton expiré d'un jeton forgé, et un refus ne
	 * peut s'imputer qu'à ce que le serveur a lui-même émis. La signature est le seul élément du
	 * jeton qui l'établisse, et elle se revérifie ici seule, hors des contraintes de validité que le
	 * refus vient précisément de violer. C'est aussi elle qui borne ce que cette méthode ouvre :
	 * sans la clé privée du serveur, un appelant ne peut faire reconnaître aucun jeton.
	 */
	public static function getFromSignedRequest(\Psr\Http\Message\ServerRequestInterface $request): AccessToken {

		$jwt = trim(preg_replace('/^\s*Bearer\s/i', '', $request->getHeaderLine('authorization')) ?? '');

		if($jwt === '') {
			return new AccessToken();
		}

		try {

			$token = new \Lcobucci\JWT\Token\Parser(new \Lcobucci\JWT\Encoding\JoseEncoder())->parse($jwt);

			new \Lcobucci\JWT\Validation\Validator()->assert(
				$token,
				new \Lcobucci\JWT\Validation\Constraint\SignedWith(
					new \Lcobucci\JWT\Signer\Rsa\Sha256(),
					\Lcobucci\JWT\Signer\Key\InMemory::file(ApiSetting::getPublicKeyPath())
				)
			);

		} catch(\Exception) {

			return new AccessToken();

		}

		// SignedWith a déjà écarté ce cas : le garde est là pour que claims() soit typé
		if($token instanceof \Lcobucci\JWT\UnencryptedToken === FALSE) {
			return new AccessToken();
		}

		return self::getByTokenId((string)$token->claims()->get('jti'));

	}

	public static function getByTokenId(string $tokenId): AccessToken {

		return \api\AccessToken::model()
			->select(AccessToken::getSelection())
			->whereTokenId($tokenId)
			->get();
	}

}
?>
