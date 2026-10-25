<?php
namespace user;

/**
 * Le défi posé à un administrateur du produit après son mot de passe : un code à six chiffres et un
 * lien, envoyés dans le même e-mail, dont le premier utilisé ferme l'autre.
 *
 * Le mot de passe seul n'ouvre plus la session d'un administrateur : `ConnectionLib::logIn()` ouvre
 * un défi à sa place quand le navigateur ne porte pas le cookie `trusted` d'un défi réussi dans les
 * trente jours. Le code se saisit sur `/user/log:code`, le lien s'ouvre sur `/user/log:link` et ne
 * connecte que dans le navigateur qui a saisi le mot de passe, par un bouton qui poste : un GET ne
 * consomme jamais rien, les antivirus et les aperçus de messagerie suivent les liens.
 *
 * Les deux secrets ne s'écrivent qu'hachés : le code par `password_hash()`, cinq essais avant que le
 * défi se ferme, le jeton du lien par SHA-256, 32 octets d'aléa. Le cookie `trusted` désigne la ligne
 * du défi réussi, signé par le secret `user` : le retirer se fait en base, en vidant `trustedUntil`,
 * ce que `SignUpLib::updatePassword()` fait avec les reconnexions automatiques.
 *
 * La table est le journal : chaque défi garde comment il s'est fermé (`closedBy`), et le cron
 * `user/cron/log` signale dans Discord `security` un défi expiré sans avoir servi, ce qui veut dire
 * que quelqu'un a le mot de passe mais pas la boîte, et ferme ce qu'il signale pour ne le dire
 * qu'une fois. Les compteurs d'essais et d'envois vivent dans Redis, comme la limitation de la
 * connexion (`ThrottleAiLib`).
 *
 * Cadrage et planche : `.claude/state/securite/recap.html`, section S4, lot 4.
 */
class ChallengeAiLib extends ChallengeCrud {

	const LIFETIME = 600;
	const TRUST_DAYS = 30;
	const CODE_LENGTH = 6;
	const TOKEN_BYTES = 32;
	const MAX_FAILURES = 5;
	const KEEP_DAYS = 90;

	// Un nouveau code se redemande depuis un défi expiré tant que le mot de passe a été saisi
	// depuis moins d'une heure ; au-delà, il se ressaisit.
	const RESEND_WINDOW = 3600;

	const WINDOW_MAIL = 3600;
	const LIMIT_MAIL = 5;

	const COOKIE = 'trusted';
	const SESSION = 'challenge';
	const SECRET = 'user';

	const NOTIFY_FAILURES = 'failures';
	const NOTIFY_EXPIRED = 'expired';

	/**
	 * Ce compte doit-il répondre à un défi avant que sa session s'ouvre ?
	 */
	public static function isRequired(User $eUser): bool {

		return (
			UserSetting::$challenge and
			$eUser->isAdmin() and
			self::isTrusted($eUser) === FALSE
		);

	}

	/**
	 * Le navigateur porte-t-il le cookie d'un défi réussi par ce compte, encore valable en base ?
	 */
	public static function isTrusted(User $eUser): bool {

		$eUser->expects(['id']);

		$signed = COOKIE(self::COOKIE, '?string');

		if($signed === NULL) {
			return FALSE;
		}

		$data = \main\CryptLib::verify($signed, self::SECRET);

		if(
			$data === NULL or
			preg_match('/^trusted:([0-9]+):([0-9]+)$/', $data, $match) === 0 or
			(int)$match[2] !== $eUser['id']
		) {
			return FALSE;
		}

		return Challenge::model()
			->whereId((int)$match[1])
			->whereUser($eUser)
			->where('trustedUntil > NOW()')
			->exists();

	}

	/**
	 * Ouvre un défi pour ce compte et lui envoie le code et le lien. Le défi s'accroche à la session
	 * avec ce que la connexion devra reprendre une fois le défi réussi.
	 *
	 * L'élément rendu porte les deux secrets en clair, `clearCode` et `clearToken`, pour qui vient de
	 * les faire naître : la base ne les connaît que hachés.
	 */
	public static function open(User $eUser, bool $remember = FALSE, ?string $redirect = NULL): Challenge {

		$eUser->expects(['id', 'email']);

		if(ThrottleAiLib::isExceeded('challenge-mail', (string)$eUser['id'], self::LIMIT_MAIL)) {
			Challenge::fail('mailThrottled');
			return new Challenge();
		}

		ThrottleAiLib::hit('challenge-mail', (string)$eUser['id'], self::WINDOW_MAIL);

		$code = str_pad((string)random_int(0, 10 ** self::CODE_LENGTH - 1), self::CODE_LENGTH, '0', STR_PAD_LEFT);
		$token = bin2hex(random_bytes(self::TOKEN_BYTES));

		$e = new Challenge([
			'user' => $eUser,
			'code' => password_hash($code, PASSWORD_DEFAULT),
			'token' => self::hashToken($token),
			'ip' => getIp(),
			'device' => \util\DeviceLib::get(),
			'expiresAt' => new \Sql('NOW() + INTERVAL '.self::LIFETIME.' SECOND'),
		]);

		self::create($e);

		Challenge::model()
			->select(['createdAt', 'expiresAt'])
			->get($e);

		\session\SessionLib::set(self::SESSION, [
			'id' => $e['id'],
			'remember' => $remember,
			'redirect' => $redirect,
		]);

		[$subject, $text, $html] = ChallengeAiUi::getMail($e, $code, $token);

		\main\MailLib::getInstance()
			->setTo($eUser['email'])
			->setContent($subject, $text, $html)
			->send();

		$e['clearCode'] = $code;
		$e['clearToken'] = $token;

		return $e;

	}

	/**
	 * Le défi que la session attend, s'il court encore : ni fermé, ni expiré.
	 */
	public static function getPending(): Challenge {

		$e = self::getFromSession();

		if(
			$e->empty() or
			$e['closedAt'] !== NULL or
			strtotime($e['expiresAt']) <= \Clock::timestamp()
		) {
			return new Challenge();
		}

		return $e;

	}

	/**
	 * Le défi accroché à la session, quel que soit son état, avec ce que la session a gardé pour la
	 * connexion : `remember` et `redirect`.
	 */
	public static function getFromSession(): Challenge {

		if(\session\SessionLib::exists(self::SESSION) === FALSE) {
			return new Challenge();
		}

		$session = \session\SessionLib::get(self::SESSION);

		$e = Challenge::model()
			->select(self::getSelectionWithUser())
			->whereId($session['id'])
			->get();

		if($e->empty()) {
			return new Challenge();
		}

		$e['remember'] = $session['remember'];
		$e['redirect'] = $session['redirect'];

		return $e;

	}

	/**
	 * Le défi que ce jeton désigne, s'il court encore ; le jeton vient de l'URL du lien.
	 */
	public static function getByToken(?string $token): Challenge {

		if(
			$token === NULL or
			strlen($token) !== self::TOKEN_BYTES * 2 or
			ctype_xdigit($token) === FALSE
		) {
			return new Challenge();
		}

		$e = Challenge::model()
			->select(self::getSelectionWithUser())
			->whereToken(self::hashToken($token))
			->where('closedAt IS NULL')
			->where('expiresAt > NOW()')
			->get();

		if($e->empty()) {
			return new Challenge();
		}

		$e['isSameBrowser'] = self::isSameBrowser($e);

		if($e['isSameBrowser']) {
			$session = \session\SessionLib::get(self::SESSION);
			$e['remember'] = $session['remember'];
			$e['redirect'] = $session['redirect'];
		} else {
			$e['remember'] = FALSE;
			$e['redirect'] = NULL;
		}

		return $e;

	}

	/**
	 * Le lien s'ouvre-t-il dans le navigateur qui a saisi le mot de passe ? C'est la session qui le
	 * dit : elle a reçu le défi à son ouverture.
	 */
	public static function isSameBrowser(Challenge $e): bool {

		$e->expects(['id']);

		return (
			\session\SessionLib::exists(self::SESSION) and
			\session\SessionLib::get(self::SESSION)['id'] === $e['id']
		);

	}

	/**
	 * Le code saisi est-il celui du défi ? Un refus de plus compte ; au cinquième, le défi se ferme,
	 * Discord le sait, et la connexion repart du mot de passe.
	 */
	public static function verifyCode(Challenge $e, ?string $code): bool {

		$e->expects(['id', 'code', 'user']);

		$subject = (string)$e['id'];

		if(password_verify(self::normalizeCode($code), $e['code'])) {

			ThrottleAiLib::clear('challenge-code', $subject);

			self::succeed($e, Challenge::CODE);

			return TRUE;

		}

		ThrottleAiLib::hit('challenge-code', $subject, self::LIFETIME);

		$failures = ThrottleAiLib::count('challenge-code', $subject);

		if($failures >= self::MAX_FAILURES) {

			ThrottleAiLib::clear('challenge-code', $subject);

			self::close($e, Challenge::FAILURES);
			self::notify($e, self::NOTIFY_FAILURES);

			\session\SessionLib::delete(self::SESSION);

			return Challenge::fail('exceeded');

		}

		return Challenge::fail('code.invalid', ['remaining' => self::MAX_FAILURES - $failures]);

	}

	/**
	 * Le défi est réussi, par le code ou par le lien : il se ferme, le navigateur devient de
	 * confiance pour trente jours, et la session n'attend plus rien.
	 */
	public static function succeed(Challenge $e, string $by): void {

		$e->expects(['id', 'user']);

		$e['closedAt'] = new \Sql('NOW()');
		$e['closedBy'] = $by;
		$e['trustedUntil'] = new \Sql('NOW() + INTERVAL '.self::TRUST_DAYS.' DAY');

		self::update($e, ['closedAt', 'closedBy', 'trustedUntil']);

		self::trust($e);

		\session\SessionLib::delete(self::SESSION);

	}

	/**
	 * Un défi qui n'a pas abouti se ferme en disant pourquoi.
	 */
	public static function close(Challenge $e, string $by): void {

		$e->expects(['id']);

		$e['closedAt'] = new \Sql('NOW()');
		$e['closedBy'] = $by;

		self::update($e, ['closedAt', 'closedBy']);

	}

	/**
	 * Un nouveau code peut-il remplacer celui-ci ? Oui tant que le défi n'a pas abouti ni été fermé
	 * sur cinq refus, et que le mot de passe a été saisi il y a moins d'une heure.
	 */
	public static function acceptResend(Challenge $e): bool {

		if($e->empty()) {
			return FALSE;
		}

		$e->expects(['closedBy', 'createdAt']);

		return (
			in_array($e['closedBy'], [NULL, Challenge::EXPIRED], TRUE) and
			strtotime($e['createdAt']) >= \Clock::timestamp() - self::RESEND_WINDOW
		);

	}

	/**
	 * Remplace le défi par un nouveau, pour le même compte et la même connexion.
	 */
	public static function resend(Challenge $e): Challenge {

		$e->expects(['user', 'remember', 'redirect']);

		if(self::acceptResend($e) === FALSE) {
			Challenge::fail('expired');
			return new Challenge();
		}

		if($e['closedAt'] === NULL) {
			self::close($e, Challenge::RESENT);
		}

		return self::open($e['user'], $e['remember'], $e['redirect']);

	}

	/**
	 * Retire la confiance accordée aux navigateurs de ce compte : ils repasseront par un défi.
	 */
	public static function revoke(User $eUser): void {

		$eUser->expects(['id']);

		Challenge::model()
			->whereUser($eUser)
			->where('trustedUntil IS NOT NULL')
			->update([
				'trustedUntil' => NULL
			]);

	}

	/**
	 * Les défis expirés sans avoir servi : Discord les apprend, et ils se ferment pour n'être dits
	 * qu'une fois. Un défi ouvert sans suite dit que le mot de passe est connu de quelqu'un qui n'a
	 * pas la boîte.
	 */
	public static function notifyExpired(): int {

		$cChallenge = Challenge::model()
			->select(self::getSelectionWithUser())
			->where('closedAt IS NULL')
			->where('expiresAt < NOW()')
			->getCollection();

		foreach($cChallenge as $eChallenge) {

			self::close($eChallenge, Challenge::EXPIRED);
			self::notify($eChallenge, self::NOTIFY_EXPIRED);

		}

		return $cChallenge->count();

	}

	public static function clean(): void {

		Challenge::model()
			->where('createdAt < NOW() - INTERVAL '.self::KEEP_DAYS.' DAY')
			->delete();

	}

	/**
	 * L'adresse que porte un challenge, pour un lien qui n'a pu transporter que son identifiant
	 * (cf. `getMessage()`). Sans adresse, l'écran appelant s'ouvre sans filtre.
	 */
	public static function getIpById(?int $id): ?string {

		if($id === NULL) {
			return NULL;
		}

		$eChallenge = Challenge::model()
			->select('ip')
			->whereId($id)
			->get();

		return $eChallenge->notEmpty() ? $eChallenge['ip'] : NULL;

	}

	public static function notify(Challenge $e, string $reason): bool {

		return \communication\DiscordWebhookLib::send(self::getMessage($e, $reason), [], \communication\DiscordWebhookLib::SECURITY);

	}

	/**
	 * Le message est écrit pour l'équipe, dans Discord, hors de toute traduction.
	 *
	 * Ni le compte ni l'adresse ne s'y nomment, pour la raison écrite sur
	 * LoginAttemptAiLib::getMessage(). Le type d'appareil reste : c'est un enum fermé, il ne désigne
	 * personne, et il dit s'il faut regarder. Le lien porte l'identifiant du challenge, que
	 * `getIpById()` résout derrière l'authentification pour ouvrir l'écran des connexions filtré sur
	 * l'adresse.
	 */
	public static function getMessage(Challenge $e, string $reason): string {

		$e->expects(['id', 'device', 'createdAt']);

		$url = \Lime::getUrl().'/user/admin/connectionAi?challenge='.$e['id'];

		return match($reason) {
			self::NOTIFY_FAILURES => '🔢 Cinq codes refusés sur un compte administrateur depuis '.$e['device'].' : le mot de passe a été saisi, le code envoyé par e-mail n\'a pas été trouvé. '.$url,
			self::NOTIFY_EXPIRED => '⌛ Le mot de passe d\'un compte administrateur a été saisi depuis '.$e['device'].' le '.$e['createdAt'].', et le code envoyé par e-mail n\'a jamais servi. '.$url,
		};

	}

	/**
	 * Pose le cookie de confiance : il désigne le défi réussi, et c'est la ligne du défi qui dit
	 * jusqu'à quand il vaut.
	 */
	protected static function trust(Challenge $e): void {

		$value = \main\CryptLib::sign('trusted:'.$e['id'].':'.$e['user']['id'], self::SECRET);

		// En CLI il n'y a pas de réponse HTTP à qui donner le cookie, et setcookie() lève un
		// avertissement dès qu'une sortie a commencé, ce que le lanceur de tests fait avant le
		// premier cas. Le tableau, lui, sert dans les deux cas : c'est lui que lit isTrusted().
		if(\Route::getRequestedWith() !== 'cli') {

			setcookie(self::COOKIE, $value, [
				'expires' => \Clock::machine() + 3600 * 24 * self::TRUST_DAYS,
				'path' => '/',
				'domain' => '.'.\Lime::getDomain(),
				'secure' => (\Lime::getProtocol() === 'https'),
				'httponly' => TRUE,
				'samesite' => \session\SessionLib::COOKIE_SAMESITE,
			]);

		}

		$_COOKIE[self::COOKIE] = $value;

	}

	public static function getSelectionWithUser(): array {

		return Challenge::getSelection() + [
			'user' => User::getSelection()
		];

	}

	public static function hashToken(string $token): string {
		return hash('sha256', $token);
	}

	/**
	 * Le code se saisit comme le mail le montre, en deux groupes de trois chiffres, ou d'un bloc.
	 */
	public static function normalizeCode(?string $code): string {
		return preg_replace('/[^0-9]/', '', (string)$code);
	}

}
?>
