<?php
namespace user;

/**
 * Handle authentication
 */
class ConnectionLib {

	use \Notifiable;

	private static $eUserOnline = NULL;

	/**
	 * Checks if a user is currently connected
	 *
	 * @throws Action A redirect action to /user/log:form if user is not connected
	 */
	public static function isLogged(): bool {

		return (self::getOnline()->empty() === FALSE);

	}

	/**
	 * Checks if a user is currently connected
	 *
	 * @throws Action A redirect action to /user/log:form if user is not connected
	 */
	public static function checkLogged(string $redirect = LIME_URL): void {

		if(self::isLogged() === FALSE) {
			throw new \RedirectAction(\Lime::getUrl().'/user/log:form?redirect='.urlencode($redirect));
		}

	}

	public static function loadSignUp(\StdClass $data): void {

		if(get_exists('invite')) {

			$data->eInvite = \structure\InviteLib::getByKey(GET('invite'));

			if($data->eInvite->isValid()) {

				$data->eUser['email'] = $data->eInvite['email'];

				if($data->eInvite['type'] === \structure\Invite::CUSTOMER) {
					$data->eUser['roleCustomer'] = TRUE;
				}

				\structure\InviteLib::prepareSignUp($data->eInvite, $data->eUser);

			} else {
				throw new \RedirectAction('/presentation/invitation');
			}

		} else {
			$data->eInvite = new \structure\Invite();
		}

	}

	/**
	 * Checks if a user is currently anonymous
	 *
	 * @throws Action A redirect action to / if user is not connected
	 */
	public static function isAnonymous(): bool {

		return self::getOnline()->empty();

	}

	/**
	 * Checks if a user is currently anonymous
	 *
	 * @throws Action A redirect action to / if user is not connected
	 */
	public static function checkAnonymous(): void {

		if(self::isAnonymous() === FALSE) {
			throw new \RedirectAction(\Lime::getRedirect(GET('redirect', '?string')));
		}

	}

	public static function setOnline(User $eUser): void {
		self::$eUserOnline = $eUser;
	}

	/**
	 * Returns the user currently connected
	 *
	 * @return array
	 */
	public static function getOnline(): User {

		if(self::$eUserOnline !== NULL) {
			return self::$eUserOnline;
		}

		try {

			self::$eUserOnline = self::getFromSession();

		} catch(\Exception $e) {

			if(self::autoLogIn()) {
				self::$eUserOnline = self::getFromSession();
			} else {
				self::$eUserOnline = new User();
			}

		}

		if(
			self::$eUserOnline->empty() === FALSE and
			\session\SessionLib::isRegenerated() and
			self::isLoggedExternal() === FALSE
		) {

			User::model()->update(self::$eUserOnline, [
				'ping' => new \Sql('NOW()')
			]);

		}

		\main\MainSetting::$onlineUser = self::$eUserOnline;

		return self::$eUserOnline;

	}

	public static function isOnline(User $eUser): bool {

		$eUser->expects(['id']);

		$eUserOnline = self::getOnline();

		return (
			$eUserOnline->notEmpty() and
			$eUserOnline['id'] === $eUser['id']
		);

	}

	protected static function getFromSession(): User {

		$eUser = new User(['id' => \session\SessionLib::get('user')]);

		User::model()
			->select(User::getSelection())
			->get($eUser);

		return $eUser;

	}

	/**
	 * Autologs the user if he asked before and the cookie is valid
	 *
	 */
	public static function autoLogIn(): bool {

		// Le cookie est celui du navigateur : un onglet sans compte prendrait celui des autres onglets
		if(
			\session\SessionLib::isInitialized() === FALSE or
			\session\SessionLib::isTab()
		) {
			return FALSE;
		}

		$cookie = COOKIE('autologin');

		if(strpos($cookie, '.') === FALSE) {
			return FALSE;
		}

		list($key, $id) = explode('.', $cookie);

		$eUserAuto = UserAuto::model()
			->select([
				'user' => User::getSelection(),
				'id'
			])
			->whereKey($key)
			->whereId($id)
			->whereStatus(UserAuto::ACTIVE)
			->get();

		if($eUserAuto->empty() === FALSE) {

			// Deletes the old cookie from the DB if there is an old one
			self::deleteAutoLoginCookie($eUserAuto['id'], $key);

			if(ChallengeAiLib::isRequired($eUserAuto['user'])) {
				return FALSE;
			}

			// Check if the user or his IP is currently banned
			if(
				BanLib::isBanned($eUserAuto['user'], getIp())
			) {

				return FALSE;

			} else {

				// Log user in
				if(self::doLogIn($eUserAuto['user'], Log::LOGIN_AUTO)) {

					UserAuth::model()
						->whereUser($eUserAuto['user'])
						->update([
							'loggedAt' => new \Sql('NOW()'),
						]);

					self::setAutoLoginCookie($eUserAuto['user'], $eUserAuto['id']);

					return TRUE;

				}

			}

		}

		return FALSE;


	}

	/**
	 * Sets the autologin cookie according to the user
	 * Generates a new key
	 *
	 */
	protected static function setAutoLoginCookie(User $eUser) {

		// Un cookie vaut pour tous les onglets du navigateur, pas pour celui qui se connecte
		if(\session\SessionLib::isTab()) {
			return;
		}

		$eUser->expects(['id']);

		// Generates a new key
		$eUserAuto = new UserAuto([
			'user' => $eUser,
			'key' => hash('sha256', random_bytes(1024)),
			'expiresAt' => new \Sql('NOW() + INTERVAL 30 DAY')
		]);

		$lifetime = 30;

		UserAuto::model()->insert($eUserAuto);

		// Add this new key to the user's autologin cookie
		$key = $eUserAuto['key'].'.'.$eUserAuto['id'];
		setcookie('autologin', $key, [
			'expires' => \Clock::machine() + 3600 * 24 * $lifetime,
			'path' => '/',
			'domain' => '.'.\Lime::getDomain(),
			'secure' => (\Lime::getProtocol() === 'https'),
			'httponly' => TRUE,
			'samesite' => \session\SessionLib::COOKIE_SAMESITE,
		]);

	}

	/**
	 * Deletes the autologin cookie for the user who asked for disconnection
	 *
	 *
	 */
	protected static function deleteAutoLoginCookie(int $idUserAuto, string $key) {

		$eUserAuto = new UserAuto([
			'id' => $idUserAuto,
			'status' => UserAuto::DELETED
		]);

		UserAuto::model()
			->select('status')
			->whereId($eUserAuto['id'])
			->whereKey($key)
			->update($eUserAuto);

		// Unsets the login auto if the user had one
		// Mêmes attributs qu'à la pose : un cookie que le navigateur ne reconnaît pas n'est pas remplacé
		setcookie('autologin', '', [
			'expires' => \Clock::machine() - 3600 * 24,
			'path' => '/',
			'domain' => '.'.\Lime::getDomain(),
			'secure' => (\Lime::getProtocol() === 'https'),
			'httponly' => TRUE,
			'samesite' => \session\SessionLib::COOKIE_SAMESITE,
		]);
		unset($_COOKIE['autologin']);

	}

	/**
	 * Get redirect after login
	 */
	public static function getRedirectLogin(): ?string {

		$eUser = ConnectionLib::getOnline();

		if(
			User::model()
				->select(['email', 'verified', 'deletedAt', 'bounce'])
				->get($eUser) and
			$eUser['bounce'] === TRUE
		) {
			return \Lime::getUrl().'/user/update:email';
		}

		foreach(self::notify('logInRedirect') as $result) {

			if($result) {
				return $result;
			}

		}

		$defaultRedirect = self::getDefaultRedirect();

		if($defaultRedirect !== NULL) {

			if(str_contains($defaultRedirect, '?')) {
				$redirect = $defaultRedirect.'&';
			} else {
				$redirect = $defaultRedirect.'?';
			}
			$redirect .= self::getCustomRedirect($redirect);

		} else {

			$redirect = NULL;

		}

		return $redirect;

	}

	public static function getCustomRedirect(string $redirect): string {

		$redirect = self::notify('loginCustom', $redirect);

		if(empty($redirect)) {
			return '';
		}

		return first($redirect);

	}

	/**
	 * Get default redirection
	 */
	private static function getDefaultRedirect(): ?string {

		if(request_exists('redirect')) {

			$request = REQUEST('redirect');
			$request = \util\HttpUi::removeArgument($request, 'success');

			if(\Lime::isSameSite($request) === FALSE) {
				return NULL;
			}

			if(str_starts_with($request, '/')) {
				return \Lime::getUrl().$request;
			}

			return $request;

		} else {
			return '/';
		}

	}

	/**
	 * Try to log a user in
	 * This method tries available auths
	 *
	 */
	public static function logIn(?string $login = NULL, ?string $password = NULL, array $options = []): bool {

		$fw = new \FailWatch();

		if(
			$login !== NULL and
			ThrottleAiLib::checkLogin($login) === FALSE
		) {
			LoginAttemptAiLib::fail($login, throttled: TRUE);
			return FALSE;
		}

		foreach(UserSetting::AUTH as $auth => $params) {

			if(is_string($params)) {
				$auth = $params;
				$params = [];
			}

			if($auth === UserAuth::BASIC and $login !== NULL and $password !== NULL) {
				$eUserAuth = self::logInBasic($login, $password);
			} else {
				$eUserAuth = new UserAuth();
			}

			if($eUserAuth->empty() === FALSE and $eUserAuth['user']->empty() === FALSE) {

				// Check user ban
				$eBan = BanLib::getByUser($eUserAuth['user'], getIp());

				if($eBan->empty() === FALSE) {

					// In case of specific login methods we store data to generate the good error message after redirection.
					if($auth !== UserAuth::BASIC) {
						\session\SessionLib::set('activeBanForUser', $eBan['id']);
					}

					return User::fail('connectionBanned', ['eBan' => $eBan]);

				}

				if(ChallengeAiLib::isRequired($eUserAuth['user'])) {

					if($login !== NULL) {
						ThrottleAiLib::succeedLogin($login);
						LoginAttemptAiLib::succeed($login, $eUserAuth['user']);
					}

					ChallengeAiLib::open(
						$eUserAuth['user'],
						($options['remember'] ?? FALSE) === TRUE,
						$options['redirect'] ?? NULL
					);

					return FALSE;

				}

				// Log user in
				if(self::doLogIn($eUserAuth['user'])) {

					// Update last log in date
					$eUserAuth['loggedAt'] = new \Sql('NOW()');

					UserAuth::model()
						->select('loggedAt')
						->update($eUserAuth);

					if(($options['remember'] ?? FALSE) === TRUE) {

						self::setAutoLoginCookie($eUserAuth['user']);

					}

					if($login !== NULL) {
						ThrottleAiLib::succeedLogin($login);
						LoginAttemptAiLib::succeed($login, $eUserAuth['user']);
					}

					return TRUE;

				}

			}

		}

		if($fw->ok()) {

			if($login !== NULL) {
				ThrottleAiLib::failLogin($login);
				LoginAttemptAiLib::fail($login, throttled: FALSE);
			}

			User::fail('connectionInvalid', [
				'login' => $login,
				'forgotten' => ($login !== NULL and ThrottleAiLib::isForgottenLogin($login)),
			]);

		}

		return FALSE;

	}

	/**
	 * Log user in for basic authentication
	 */
	protected static function logInBasic(string $login, string $password): UserAuth {

		$eUserAuth = UserAuth::model()
			->select([
				'id',
				'password',
				'user' => User::getSelection()
			])
			->whereType(UserAuth::BASIC)
			->whereLogin($login)
			->get();

		if(
			$eUserAuth->empty() or
			$eUserAuth['user']->empty() or // Account deleted
			password_verify($password, $eUserAuth['password']) === FALSE // Check user password
		) {
			return new UserAuth();
		}

		return $eUserAuth;

	}

	/**
	 * Log a user in without any check
	 */
	public static function logInUser(User $eUser, bool $remember = FALSE): bool {

		$eUser->expects(['id']);

		if(
			\Route::getRequestedWith() !== 'cli' and
			ChallengeAiLib::isRequired($eUser)
		) {
			return User::fail('connectionChallenge');
		}

		if(

			User::model()
				->select(User::getSelection())
				->whereStatus(User::ACTIVE)
				->get($eUser) === FALSE or

			self::doLogIn($eUser) === FALSE

		) {
			User::fail('connectionInvalid');
			return FALSE;
		} else {

			if($remember) {
				self::setAutoLoginCookie($eUser);
			}
			return TRUE;
		}

	}

	/**
	 * Database and session processes for user login
	 *
	 */
	protected static function doLogIn(User $eUser, string $type = Log::LOGIN): bool {

		$eUser->expects(['id']);
		$eUser->expects(['email', 'status', 'deletedAt', 'onlineToday']); // These properties must be initialized in SignUpLib::create()

		$loggedAt = User::model()->now();
		$isRegularLogin = ($type === Log::LOGIN or $type === Log::LOGIN_AUTO);

		if($isRegularLogin) {

			$affected = User::model()
				->whereStatus(User::ACTIVE)
				->update($eUser, [
					'loggedAt' => $loggedAt,
					'seen' => new \Sql('seen + 1'),
					'onlineToday' => TRUE,
					'ping' => $loggedAt
				]);

			// User is not valid
			if($affected === 0) {
				return FALSE;
			}

			// Log out connected user first
			try {

				$eUserOnline = new User(['id' => \session\SessionLib::get('user')]);

				self::logOut($eUserOnline);

			} catch(\Exception $e) {

			}

		}

		// L'identifiant de session change avec le niveau de privilège : celui qui a servi avant la
		// connexion a pu être choisi par un tiers, et le garder lui donnerait la session
		// authentifiée. Avant la trace, qui rend `Log.sid` par `SPECIAL(sid)` : journalisée avant,
		// elle nommerait l'identifiant que la régénération vient de détruire.
		\session\SessionLib::regenerate();

		LogLib::add($eUser, $type);

		\session\SessionLib::set('userLoggedAt', $loggedAt);
		\session\SessionLib::set('userDeletedAt', $eUser['deletedAt']);

		\session\SessionLib::set('user', $eUser['id']);

		if($isRegularLogin) {

			$firstTimeToday = ($eUser['onlineToday'] === FALSE);

			$firstTime = (
				$eUser['seniority'] === 1 and
				$firstTimeToday
			);

			self::notify('logIn', $eUser, $firstTime, $firstTimeToday);

		}

		self::$eUserOnline = NULL;

		return TRUE;

	}

	/**
	 * Logs $eUserAction into $eUser account
	 *
	 */
	public static function logInExternal(User $eUser, User $eUserAction): bool {

		$eUserOld = new User(['id' => \session\SessionLib::get('user')]);

		if(self::isLoggedExternal()) {
			return FALSE;
		}

		// First : logOut $eUserAction
		self::logOut($eUserAction, forgetDevice: FALSE);

		// Second : logIn $eUserAction as if it was $eUser
		self::doLogIn($eUser, Log::LOGIN_EXTERNAL);

		\session\SessionLib::set('userOld', $eUserOld['id']);

		return TRUE;

	}

	/**
	 * Logs out from the user's account and relogs in the good account
	 *
	 * @param bool
	 */
	public static function logOutExternal(): bool {

		try {
			$eUserOld = new User(['id' => \session\SessionLib::get('userOld')]);
		} catch(\Exception $e) {
			return FALSE;
		}

		$eUser = self::getOnline();

		// logs out from the previous account
		self::logOut($eUser, forgetDevice: FALSE);

		if(User::model()
			->select(User::getSelection())
			->get($eUserOld)) {

			// Second : logIn as the good user
			self::doLogIn($eUserOld, Log::LOGIN);

		}

		return TRUE;

	}

	/**
	 * Log a user out
	 *
	 */
	public static function logOut(User $eUser, bool $forgetDevice = TRUE): bool {

		$eUser->expects(['id']);

		LogLib::add($eUser, Log::LOGOUT);

		\session\SessionLib::clean();

		// Vider le contenu ne suffit pas : sans nouvel identifiant, celui qui vient de servir reste
		// valable jusqu'à `SessionLib::LIFETIME`, et se déconnecter ne reprendrait rien à qui l'a volé
		\session\SessionLib::regenerate();

		// Changer de compte dans un onglet effacerait la connexion automatique du navigateur entier
		if(
			$forgetDevice and
			\session\SessionLib::isTab() === FALSE
		) {

			$cookie = COOKIE('autologin');

			if(strpos($cookie, '.') !== FALSE) {

				list($key, $idUserAuto) = explode('.', $cookie);
				self::deleteAutoLoginCookie($idUserAuto, $key);
			}

		}

		self::notify('logOut', $eUser);

		return TRUE;

	}

	/**
	 * Is the connected user someone logged onto another account?
	 */
	public static function isLoggedExternal(): bool {
		return \session\SessionLib::exists('userOld');
	}

	/**
	 * Checks if the user is connected onto another account
	 */
	public static function checkLoginExternal(): ?array {

		if(self::isLoggedExternal() === FALSE) {
			return NULL;
		}

		$eUserAction = new User(['id' => \session\SessionLib::get('userOld')]);
		$eUser = self::getOnline();

		return [$eUser, $eUserAction];

	}

}
?>
