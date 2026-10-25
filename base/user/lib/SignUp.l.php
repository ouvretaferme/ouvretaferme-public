<?php
namespace user;

/**
 * Handle user signup and profile edition
 */
class SignUpLib {

	use \Notifiable;

	/**
	 * Check if terms of service have been accepted
	 */
	public static function checkTos(array $input) {

		$tos = (bool)($input['tos'] ?? FALSE);

		if(UserSetting::$checkTos and $tos === FALSE) {
			User::fail('tos.accepted');
		}

	}

	public static function createBasic(User $eUser, array $input): void {

		$fw = new \FailWatch();

		self::match(UserAuth::BASIC, $eUser, $input);
		self::matchBasicPassword('create', $eUser, $input);
		self::checkTos($input);

		$fw->validate();

		ThrottleAiLib::checkSignUp();

		$fw->validate();

		self::create($eUser, FALSE);

		$fw->validate();

		TosAiLib::acceptOnSignUp($eUser);

	}

	/**
	 * Check if a user can be created with basic authentication using the given input
	 * - email: user[email] *
	 */
	public static function match(string $auth, User $eUser, array $input, array $additionalProperties = []): bool {

		$eUser->add([
			'visibility' => User::PUBLIC,
			'auth' => new UserAuth()
		]);

		$fw = new \FailWatch;

		$eUser->build(['type'], $input);

		if($fw->ko()) {
			return FALSE;
		}

		$properties = [
			'email', 'firstName', 'lastName', 'legalName',
			'deliveryCountry', 'deliveryStreet1', 'deliveryStreet2', 'deliveryPostcode', 'deliveryCity'
		];

		if($eUser['type'] === User::PRO) {
			$properties = array_merge($properties, [
				'invoiceCountry', 'invoiceStreet1', 'invoiceStreet2', 'invoicePostcode', 'invoiceCity',
				'siret'
			]);
		}

		$properties = array_merge($properties, $additionalProperties);

		$eUser->build($properties, $input);

		if($eUser->offsetExists('id') === FALSE) {

			self::notify('signUpCheck', $auth, $eUser, $input);

		}

		if($fw->ok()) {

			$eUser['auth']['type'] = $auth;
			$eUser['auth']['login'] = $eUser['email'];

			if($eUser['type'] === User::PRIVATE) {
				$eUser['invoiceCountry'] = $eUser['deliveryCountry'];
			}

			return TRUE;

		} else {
			return FALSE;
		}

	}

	/**
	 * Check if a password is valid using the given input
	 * - password/passwordBis : userAuth[password] *
	 */
	public static function matchBasicPassword(string $action, User $eUser, array $input) {

		if($action === 'update') {

			$eUser['auth'] = self::getBasicAuth($eUser);

			// The user might come from social connection
			if($eUser['auth']->empty()) {

				self::match('update', $eUser, $input);

				if(UserAuth::model()->whereLogin($eUser['email'])->whereType(UserAuth::BASIC)->exists()) {
					User::fail('email.duplicate');
					return;
				}

				$eUser['auth'] = new UserAuth([
					'user' => $eUser,
					'type' => UserAuth::BASIC,
					'login' => $eUser['email']
				]);

			} else {

				self::checkCurrentPassword($eUser['auth'], $input['passwordOld'] ?? NULL);

			}

		}

		$l = new \PropertyList();

		$l->property('password')
			->addAfterCheck('length', function($password) {
				return (
					$password !== NULL and
					strlen($password) >= UserSetting::PASSWORD_SIZE_MIN
				);
			})
			->addAfterCheck('hash', function(&$password) {

				// Valid password, we hash it
				$password = password_hash($password, PASSWORD_DEFAULT);

				return TRUE;

			})
			->addAfterCheck('match', function($password, \PropertyList $l) {

				if(array_key_exists('passwordBis', $l->input)) {
					return password_verify($l->input['passwordBis'], $password);
				} else {
					// Sometimes we don't ask for a bis password
					return TRUE;
				}

			});


		$eUser['auth']->build(['password'], $input, $l);

	}

	public static function getBasicAuth(User $eUser): UserAuth {

		return UserAuth::model()
			->select('id', 'password')
			->whereType(UserAuth::BASIC)
			->whereUser($eUser)
			->get();

	}

	/**
	 * A user proves they know their current password before a sensitive change (password, e-mail)
	 */
	public static function checkCurrentPassword(UserAuth $eUserAuth, ?string $password): bool {

		$eUserAuth->expects(['password']);

		if(
			$password === NULL or
			password_verify($password, $eUserAuth['password']) === FALSE
		) {
			return UserAuth::fail('passwordOld.invalid');
		}

		return TRUE;

	}

	/**
	 * Create a new user in the database.
	 */
	public static function create(\Element $eUser, bool $verifiedByDefault = TRUE) {

		$eUser->expects(['auth', 'visibility', 'deliveryCountry']);

		// Required by doLogIn() for log in just after create
		$eUser['status'] = User::ACTIVE;
		$eUser['deletedAt'] = NULL;
		$eUser['seniority'] = 1;
		$eUser['verified'] = $verifiedByDefault;
		$eUser['onlineToday'] = FALSE;

		// L'e-mail de bienvenue part par l'observateur de `signUpCreate`. Une porte qui envoie le
		// sien après la création pose FALSE avant d'appeler.
		$eUser->add([
			'sendMail' => TRUE
		]);

		// We check if this IP is banned before creating the account
		$eBan = BanLib::getByIp(getIp());

		if($eBan->empty() === FALSE) {

			// In case of specific registration methods we store data to generate the good error message after redirection.
			if($eUser['auth']['type'] !== UserAuth::BASIC) {
				\session\SessionLib::set('activeBanForUser', $eBan['id']);
			}

			return User::fail('signUpBanned', ['eBan' => $eBan]);
		}

		try {

			User::model()->beginTransaction();

			// Add user
			User::model()->insert($eUser);

			try {

				// Add user authentication
				$eUser['auth']['user'] = $eUser;
				UserAuth::model()->insert($eUser['auth']);

				$fw = new \FailWatch;

				self::notify('signUpCreate', $eUser);

				if($fw->ko()) {

					User::model()->rollBack();

					self::notify('dropDelete', $eUser);

					return FALSE;

				} else {
					User::model()->commit();
				}

			}
			catch(\DuplicateException $e) { // UserAuth.login

				User::model()->rollBack();

				return User::fail('email.duplicate');

			}

		}
		catch(\DuplicateException $e) { // User.email

			User::model()->rollBack();

			$eUserCheck = User::model()
				->select('id', 'email')
				->whereEmail($eUser['email'])
				->get();

			if($eUserCheck->empty() === FALSE) {

				$cUserAuth = UserAuth::model()
					->select('user', 'type')
					->whereUser($eUserCheck)
					->getCollection(NULL, NULL, 'type');

				if($cUserAuth->offsetExists(UserAuth::BASIC)) {
					User::fail('email.signUpDuplicate');
					return FALSE;
				}

			}

			User::fail('email.duplicate');
			return FALSE;

		}

		return TRUE;

	}

	/**
	 * Checks if a user can update its email/password
	 */
	public static function canUpdate(User $eUser): array {

		if($eUser->empty()) {

			return [
				'email' => FALSE,
				'drop' => FALSE,
				'password' => FALSE,
				'hasPassword' => FALSE,
				'cUserAuth' => new \Collection()
			];

		}

		$cUserAuth = UserAuth::model()
			->select('type')
			->whereUser($eUser)
			->getCollection(NULL, NULL, 'type');

		$values = [
			'drop' => TRUE,
			'email' => TRUE,
			'password' => TRUE,
			'hasPassword' => $cUserAuth->offsetExists(UserAuth::BASIC),
			'cUserAuth' => $cUserAuth
		];

		self::notify('canUpdate', $eUser, $cUserAuth, $values);

		return $values;

	}

	/**
	 * Update email of an existing user.
	 */
	public static function updateEmail(User $eUser, bool $verified = FALSE): bool {

		$eUser->expects(['id', 'email']);

		try {

			User::model()->beginTransaction();

			// Update email
			if(User::model()
				->select('email')
				->update($eUser) > 0) {

				// In case email has change we re init the verified flag
				$eUser['verified'] = $verified;
				$eUser['bounce'] = FALSE;

				User::model()
					->select('verified', 'bounce')
					->update($eUser);

				// Update basic auth
				UserAuth::model()
					->whereUser($eUser)
					->whereType(UserAuth::BASIC)
					->update(['login' => $eUser['email']]);

				$eUserOnline = ConnectionLib::getOnline();

				if(
					$eUserOnline->notEmpty() and
					$eUserOnline['id'] === $eUser['id']
				) {

					\session\SessionLib::set('user', $eUser['id']);

				}

				if($eUser['verified'] === FALSE) {
					$change = TRUE;
					self::notify('sendVerifyEmail', $eUser, $change);
				}

				$properties = ['email'];
				self::notify('update', $eUser, $properties);

			}

			User::model()->commit();

		} catch(\DuplicateException $e) { // User.email

			User::model()->rollBack();

			return User::fail('email.duplicate');

		}

		return TRUE;

	}

	/**
	 * Save a new password, and close every other session opened on the account
	 */
	public static function updatePassword(User $eUser): bool {

		$eUser->expects(['auth' => ['password']]);

		if(UserAuth::model()
				->whereUser($eUser)
				->whereType(UserAuth::BASIC)
				->exists() === FALSE) {

			UserAuth::model()->insert($eUser['auth']);
			self::updateEmail($eUser);

		} else {

			UserAuth::model()
				->select('password')
				->whereType(UserAuth::BASIC)
				->update($eUser['auth']);

		}

		UserAuto::model()
			->whereUser($eUser)
			->delete();

		ChallengeAiLib::revoke($eUser);

		\session\SessionLib::killByUser($eUser, session_id());

		return TRUE;

	}

	/**
	 * Create a password for a user
	 */
	public static function createPassword(User $eUser): bool {

		$eUser->expects(['auth' => ['password']]);

		$eUserAuth = $eUser['auth'];
		$eUserAuth['user'] = $eUser;
		$eUserAuth['type'] = UserAuth::BASIC;

		try {
			UserAuth::model()->insert($eUserAuth);
		} catch(\DuplicateException) {
			self::updatePassword($eUser);
		}

		return TRUE;

	}

}
?>
