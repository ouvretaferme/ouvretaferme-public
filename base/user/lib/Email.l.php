<?php

namespace user;

/**
 * Handle features relative to user and mail sending
 */
class EmailLib {

	/**
	 * Return true if the user has verified his last specified mail address and false otherwise.
	 */
	public static function isVerified(User $eUser): bool {

		$eUser->expects(['email', 'verified']);

		return (
			$eUser['email'] !== NULL and
			$eUser['verified'] === TRUE
		);

	}

	/**
	 * Sends the account closing confirmation mail
	 */
	public static function sendClose(User $eUser) {

		$eUser->expects(['id', 'email']);

		if($eUser['email'] === NULL) {
			return FALSE;
		}

		$content = UserUi::getCloseMail();

		\main\MailLib::getInstance()
			->setTo($eUser['email'])
			->setContent(...$content)
			->send();
	}

	/**
	 * Send the hash to the user's by email.
	 */
	public static function sendSignUp(User $eUser) {

		$eUser->expects(['id', 'email']);

		if($eUser['email'] === NULL) {
			return FALSE;
		}

		$content = UserUi::getSignUpMail($eUser);

		\main\MailLib::getInstance()
			->setTo($eUser['email'])
			->setContent(...$content)
			->send();

	}

	/**
	 * Send the hash to the user's by email to verify the address.
	 */
	public static function sendVerify(
		User $eUser,
		bool $change
	) {

		$eUser->expects(['id', 'email']);

		$hash = self::computeHash($eUser);

		$content = UserUi::getVerifyMail($eUser, $hash, $change);

		\main\MailLib::getInstance()
			->setTo($eUser['email'])
			->setContent(...$content)
			->send();

	}

	/**
	 * Verify that the $actualHash match this specific $eUser.
	 */
	public static function validate(string $actualHash) {

		$eUser = self::getByHash($actualHash);

		if($eUser->empty()) {
			return User::fail('invalidHash');
		}

		if(ConnectionLib::isLogged()) {

			$eUserLogged = ConnectionLib::getOnline();

			if($eUser['id'] !== $eUserLogged['id']) {
				return User::fail('hashConnectedWrongAccount');
			}

		}


		// Swith the user as verified
		$eUser['verified'] = TRUE;

		\user\User::model()
			->select('verified')
			->update($eUser);

	}

	/**
	 * Le lien de vérification d'une adresse, signé et borné à l'adresse de l'envoi.
	 *
	 * Signé et non chiffré : `CryptLib::encrypt()` est un AES-CBC sans authentification dont l'IV voyage
	 * en clair, donc le premier bloc d'un lien se réécrit sans connaître le secret. L'adresse n'entre pas
	 * dans le lien mais sous la forme d'un condensé, un lien de vérification voyageant en clair dans une
	 * chaîne de requête. Le préfixe le borne à cet usage, le secret `user` servant aussi à la référence
	 * d'une commande de boutique.
	 */
	public static function computeHash(User $eUser): string {

		$eUser->expects(['id', 'email']); // Mail can be null

		return \main\CryptLib::sign('userEmail:'.$eUser['id'].':'.\main\CryptLib::digest((string)$eUser['email'], 'user'), 'user');

	}

	/**
	 * L'utilisateur que ce lien désigne, vide si l'adresse a changé depuis l'envoi.
	 */
	private static function getByHash(string $hash): User {

		$data = \main\CryptLib::verify($hash, 'user');

		if($data !== NULL) {

			$parts = explode(':', $data);

			if(
				count($parts) !== 3 or
				$parts[0] !== 'userEmail' or
				ctype_digit($parts[1]) === FALSE
			) {
				return new User();
			}

			$eUser = UserLib::getById((int)$parts[1], ['id', 'email']);

			if(
				$eUser->empty() or
				hash_equals(\main\CryptLib::digest((string)$eUser['email'], 'user'), $parts[2]) === FALSE
			) {
				return new User();
			}

			return $eUser;

		}

		// TRANSITION jusqu'au 2026-12-31 : les liens chiffrés avant la signature vivent dans les boîtes de
		// réception, et rien ne les rappelle. Passée cette date, ce repli s'enlève, et avec lui la moitié
		// falsifiable du lien. Échéance tenue par main\LegacyTokenSentinelAiTest.
		$decrypted = \main\CryptLib::decrypt($hash, 'user');

		if($decrypted === NULL or str_contains($decrypted, '/') === FALSE) {
			return new User();
		}

		[$userId, $userEmail] = explode('/', $decrypted, 2);

		if(ctype_digit($userId) === FALSE) {
			return new User();
		}

		$eUser = UserLib::getById((int)$userId, ['id', 'email']);

		if(
			$eUser->empty() or
			$eUser['email'] !== $userEmail
		) {
			return new User();
		}

		return $eUser;

	}

}
?>
