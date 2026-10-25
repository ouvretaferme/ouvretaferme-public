<?php
namespace communication;

/**
 * Gestion des comptes Discord selon l'offre Communauté des structures
 *
 * On rejoint le serveur par le lien de sa structure (DiscordInvitation). Qui peut s'en servir et qui
 * perd l'accès se décide dans le paquet subscription, qui porte les offres.
 *
 * Le serveur est celui de Ouvretaferme, qui a aussi des salons publics : l'offre Communauté ne donne
 * pas le serveur mais un rôle, posé à l'entrée par le lien et retiré à la fin de l'offre. Personne n'est
 * exclu du serveur.
 *
 * Avant d'activer CommunicationSetting::DISCORD_INVITATION :
 * - Ajouter dans secret.c.php :
 *      - CommunicationSetting::$crypt (clé 'discord')
 *      - les credentials CommunicationSetting::$discord, dont guildId et roleId
 * - Préparer le serveur :
 *      - créer le rôle de la Communauté, et rendre ses salons invisibles à @everyone et visibles pour ce rôle
 *      - inviter le bot avec les permissions « Créer une invitation » et « Gérer les rôles » :
 *      https://discord.com/oauth2/authorize?client_id=1529542391248191651&permissions=268435457&integration_type=0&scope=bot
 *      - placer le rôle du bot au-dessus du rôle de la Communauté, sans quoi Discord refuse de le poser
 *      Note : Le clientId/clientSecret/botToken et les redirect URIs restent valables tels quels (pas liés à un serveur)
 *
 */
class DiscordAccessLib extends DiscordAccessCrud {

	const API_URL = 'https://discord.com/api/v10/';
	const OAUTH_AUTHORIZE_URL = 'https://discord.com/api/oauth2/authorize';
	const OAUTH_TOKEN_URL = 'https://discord.com/api/v10/oauth2/token';
	const SCOPE = 'identify guilds.join';

	const UNKNOWN_MEMBER = 10007;

	public static function getByUser(\user\User $eUser): DiscordAccess {

		return DiscordAccess::model()
			->select(DiscordAccess::getSelection())
			->whereUser($eUser)
			->get();

	}

	public static function getJoined(): \Collection {

		return DiscordAccess::model()
			->select(DiscordAccess::getSelection())
			->whereStatus(DiscordAccess::JOINED)
			->getCollection();

	}

	public static function getAuthorizeUrl(DiscordInvitation $eDiscordInvitation, \user\User $eUser): string {

		$params = [
			'client_id' => CommunicationSetting::$discord['clientId'],
			'redirect_uri' => self::getRedirectionUri(),
			'response_type' => 'code',
			'scope' => self::SCOPE,
			'state' => self::getState($eDiscordInvitation, $eUser),
			'prompt' => 'consent',
		];

		return self::OAUTH_AUTHORIZE_URL.'?'.http_build_query($params);

	}

	/**
	 * Le `state` de l'aller, signé (`CryptLib::sign()`) et valable `CommunicationSetting::DISCORD_STATE_LIFETIME`.
	 *
	 * Discord renvoie sur le domaine principal avec le `code` de qui vient d'autoriser : un `state`
	 * falsifiable laissait rejoindre le serveur par le lien d'une autre structure, ou sous le nom d'un
	 * autre. Le droit, lui, est déjà pesé à l'aller, qui n'émet ce `state` qu'au membre connecté d'une
	 * structure qui a la Communauté.
	 */
	public static function getState(DiscordInvitation $eDiscordInvitation, \user\User $eUser, ?int $time = NULL): string {

		return \main\CryptLib::sign('discord:'.$eDiscordInvitation['id'].':'.$eUser['id'].':'.($time ?? \Clock::timestamp()), 'discord');

	}

	/**
	 * @return array{DiscordInvitation, \user\User} deux éléments vides si le `state` est refusé
	 */
	public static function getByState(string $state): array {

		$refused = [new DiscordInvitation(), new \user\User()];

		$data = \main\CryptLib::verify($state, 'discord');

		if($data === NULL) {
			return $refused;
		}

		$parts = explode(':', $data);

		if(
			count($parts) !== 4 or
			$parts[0] !== 'discord' or
			ctype_digit($parts[1]) === FALSE or
			ctype_digit($parts[2]) === FALSE or
			ctype_digit($parts[3]) === FALSE or
			(int)$parts[3] < \Clock::timestamp() - CommunicationSetting::DISCORD_STATE_LIFETIME
		) {
			return $refused;
		}

		$eDiscordInvitation = DiscordInvitationLib::getById((int)$parts[1]);
		$eUser = \user\UserLib::getById((int)$parts[2]);

		if($eDiscordInvitation->empty() or $eUser->empty()) {
			return $refused;
		}

		return [$eDiscordInvitation, $eUser];

	}

	private static function getRedirectionUri(): string {

		if(LIME_ENV === 'dev') {
			return 'http://www.dev-ouvretaferme.org/discord';
		}

		return 'https://www.ouvretaferme.org/discord';

	}

	/**
	 * Callback OAuth2 : échange le code contre un token, identifie la personne, l'ajoute au serveur si elle n'y est pas
	 * et lui pose le rôle de la Communauté
	 */
	public static function handleCallback(string $code, string $state): DiscordAccess {

		[$eDiscordInvitation, $eUser] = self::getByState($state);

		if($eDiscordInvitation->empty()) {
			\Fail::log('DiscordAccess::invalidState');
			return new DiscordAccess();
		}

		$token = self::exchangeCode($code);

		if($token === NULL) {
			\Fail::log('DiscordAccess::exchangeFailed');
			return new DiscordAccess();
		}

		$discordUser = self::getDiscordUser($token['access_token']);

		if($discordUser === NULL) {
			\Fail::log('DiscordAccess::identifyFailed');
			return new DiscordAccess();
		}

		$eDiscordAccess = self::getByUser($eUser);

		// Le retrait quotidien ne connaît que le compte de la ligne : un compte remplacé garderait le rôle.
		if(
			$eDiscordAccess->acceptRevoke() and
			$eDiscordAccess['discordId'] !== $discordUser['id'] and
			self::removeRole($eDiscordAccess['discordId']) === FALSE
		) {
			\Fail::log('DiscordAccess::previousRoleFailed');
			return new DiscordAccess();
		}

		if(
			self::joinGuild($token['access_token'], $discordUser['id']) === FALSE or
			self::addRole($discordUser['id']) === FALSE
		) {
			\Fail::log('DiscordAccess::joinFailed');
			return new DiscordAccess();
		}

		if($eDiscordAccess->empty()) {

			$eDiscordAccess = new DiscordAccess([
				'user' => $eUser,
				'structure' => $eDiscordInvitation['structure'],
				'discordId' => $discordUser['id'],
				'joinedAt' => new \Sql('NOW()'),
			]);

			self::create($eDiscordAccess);

		} else {

			$eDiscordAccess['structure'] = $eDiscordInvitation['structure'];
			$eDiscordAccess['discordId'] = $discordUser['id'];
			// Un jeton déjà écrit ouvrirait encore n'importe quel serveur du bot au nom de la personne.
			$eDiscordAccess['refreshToken'] = NULL;
			$eDiscordAccess['status'] = DiscordAccess::JOINED;
			$eDiscordAccess['joinedAt'] = new \Sql('NOW()');
			$eDiscordAccess['revokedAt'] = NULL;

			self::update($eDiscordAccess, ['structure', 'discordId', 'refreshToken', 'status', 'joinedAt', 'revokedAt']);

		}

		return $eDiscordAccess;

	}

	/**
	 * Coupe l'accès : retire le rôle de la Communauté, la personne reste sur le serveur. À appeler quand elle n'est
	 * plus éligible (cf. vérification côté appelant de tous les Member de l'utilisateur avant d'invoquer cette méthode)
	 */
	public static function revoke(DiscordAccess $eDiscordAccess): void {

		if($eDiscordAccess->acceptRevoke() === FALSE) {
			return;
		}

		if(self::removeRole($eDiscordAccess['discordId']) === FALSE) {
			return;
		}

		$eDiscordAccess['status'] = DiscordAccess::REVOKED;
		$eDiscordAccess['revokedAt'] = new \Sql('NOW()');

		self::update($eDiscordAccess, ['status', 'revokedAt']);

	}

	private static function exchangeCode(string $code): ?array {

		return self::requestToken([
			'grant_type' => 'authorization_code',
			'code' => $code,
			'redirect_uri' => self::getRedirectionUri(),
			'client_id' => CommunicationSetting::$discord['clientId'],
			'client_secret' => CommunicationSetting::$discord['clientSecret'],
		]);

	}

	private static function requestToken(array $body): ?array {

		$options = [
			CURLOPT_HTTPHEADER => [
				'Content-Type: application/x-www-form-urlencoded',
			],
			CURLOPT_RETURNTRANSFER => TRUE,
		];

		$curl = new \util\CurlLib();

		try {
			$result = $curl->exec(self::OAUTH_TOKEN_URL, http_build_query($body), 'POST', $options);
		} catch(\Exception $e) {
			trigger_error('Unable to reach Discord token endpoint. Message: '.$e->getMessage());
			return NULL;
		}

		$data = json_decode($result, TRUE);

		if(isset($data['access_token']) === FALSE or isset($data['refresh_token']) === FALSE) {
			return NULL;
		}

		return $data;

	}

	private static function getDiscordUser(string $accessToken): ?array {

		$options = [
			CURLOPT_HTTPHEADER => [
				'Authorization: Bearer '.$accessToken,
			],
			CURLOPT_RETURNTRANSFER => TRUE,
		];

		$curl = new \util\CurlLib();

		try {

			$result = $curl->exec(self::API_URL.'users/@me', [], 'GET', $options);

		} catch(\Exception $e) {

			trigger_error('Unable to reach Discord users/@me. Message: '.$e->getMessage());
			return NULL;

		}

		$data = json_decode($result, TRUE);

		return isset($data['id']) ? $data : NULL;

	}

	// PUT avec un corps JSON : CurlLib ne gère nativement que POST/DELETE, d'où le mode 'POST' + CUSTOMREQUEST en override
	private static function joinGuild(string $userAccessToken, string $discordUserId): bool {

		$options = [
			CURLOPT_HTTPHEADER => [
				'Authorization: Bot '.CommunicationSetting::$discord['botToken'],
				'Content-Type: application/json',
			],
			CURLOPT_CUSTOMREQUEST => 'PUT',
			CURLOPT_RETURNTRANSFER => TRUE,
		];

		$url = self::API_URL.'guilds/'.CommunicationSetting::$discord['guildId'].'/members/'.$discordUserId;

		$curl = new \util\CurlLib();

		try {

			$curl->exec($url, json_encode(['access_token' => $userAccessToken]), 'POST', $options);

		} catch(\Exception $e) {

			trigger_error('Unable to join Discord guild. Message: '.$e->getMessage());
			return FALSE;

		}

		$httpCode = $curl->getLastInfos()['httpCode'] ?? 0;

		// 201 = ajouté au serveur, 204 = déjà membre
		return in_array($httpCode, [201, 204]);

	}

	/**
	 * Discord rend 404 pour un membre qui a quitté le serveur (le retrait est alors acquis), mais aussi pour un rôle
	 * ou un serveur inconnus : seul le code d'erreur les distingue.
	 */
	public static function isRoleRemoved(int $httpCode, string $content): bool {

		if($httpCode === 204) {
			return TRUE;
		}

		if($httpCode !== 404) {
			return FALSE;
		}

		$data = json_decode($content, TRUE);

		return (is_array($data) and ($data['code'] ?? NULL) === self::UNKNOWN_MEMBER);

	}

	private static function getRoleUrl(string $discordUserId): string {
		return self::API_URL.'guilds/'.CommunicationSetting::$discord['guildId'].'/members/'.$discordUserId.'/roles/'.CommunicationSetting::$discord['roleId'];
	}

	// PUT sans corps : le mode 'POST' pose un Content-Length à 0, sans lequel Discord répond 411
	private static function addRole(string $discordUserId): bool {

		$options = [
			CURLOPT_HTTPHEADER => [
				'Authorization: Bot '.CommunicationSetting::$discord['botToken'],
			],
			CURLOPT_CUSTOMREQUEST => 'PUT',
			CURLOPT_RETURNTRANSFER => TRUE,
		];

		$curl = new \util\CurlLib();

		try {

			$curl->exec(self::getRoleUrl($discordUserId), '', 'POST', $options);

		} catch(\Exception $e) {

			trigger_error('Unable to add Discord role. Message: '.$e->getMessage());
			return FALSE;

		}

		$httpCode = $curl->getLastInfos()['httpCode'] ?? 0;

		if($httpCode !== 204) {
			trigger_error('Unable to add Discord role to member #'.$discordUserId.'. HTTP code: '.$httpCode);
			return FALSE;
		}

		return TRUE;

	}

	private static function removeRole(string $discordUserId): bool {

		$options = [
			CURLOPT_HTTPHEADER => [
				'Authorization: Bot '.CommunicationSetting::$discord['botToken'],
			],
			CURLOPT_RETURNTRANSFER => TRUE,
		];

		$curl = new \util\CurlLib();

		try {

			$content = $curl->exec(self::getRoleUrl($discordUserId), [], 'DELETE', $options);

		} catch(\Exception $e) {

			trigger_error('Unable to remove Discord role. Message: '.$e->getMessage());
			return FALSE;

		}

		$httpCode = $curl->getLastInfos()['httpCode'] ?? 0;

		if(self::isRoleRemoved($httpCode, $content) === FALSE) {
			trigger_error('Unable to remove Discord role from member #'.$discordUserId.'. HTTP code: '.$httpCode);
			return FALSE;
		}

		return TRUE;

	}

}
