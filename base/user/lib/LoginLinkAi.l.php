<?php
namespace user;

/**
 * Le lien de connexion envoyé par e-mail : il ouvre la session sans le mot de passe, une seule
 * fois, dans les dix minutes, dans n'importe quel navigateur.
 *
 * Le lien part dans l'e-mail de mot de passe oublié, à côté du lien qui choisit un nouveau mot de
 * passe (`UserLib::sendForgottenPasswordLink()`). Ouvrir le lien ne consomme rien : la page
 * `/user/log:loginLink` montre un bouton qui poste, parce que les antivirus et les aperçus de
 * messagerie suivent les liens.
 *
 * Aucun lien n'est émis pour un compte qui ne pourrait pas entrer par lui : un administrateur, qui
 * répond à un défi après son mot de passe (`ChallengeAiLib`), un compte banni ou inactif. L'appelant
 * reçoit alors un élément vide et l'écran ne dit rien de différent, pour ne pas dire à qui demande
 * ce qu'est le compte.
 *
 * Le jeton ne s'écrit qu'haché, par SHA-256 comme celui du défi, et un compte n'a qu'un lien vivant :
 * un nouveau ferme les précédents, comme un nouveau mot de passe oublié écrase le précédent.
 *
 * Une connexion par lien lève le blocage de l'adresse (`ThrottleAiLib::succeedLogin()`) et clôt la
 * série de tentatives en `LoginAttempt::LINK`. Le blocage du poste reste : le lien prouve l'accès à
 * une boîte, pas l'innocence d'un poste qui essaie des comptes à la chaîne.
 */
class LoginLinkAiLib extends LoginLinkCrud {

	const LIFETIME = 600;
	const KEEP_DAYS = 90;

	/**
	 * Émet un lien pour ce compte, s'il peut entrer par lui. L'élément rendu porte le jeton en
	 * clair, `clearToken`, pour qui l'envoie : la base ne le connaît qu'haché.
	 */
	public static function open(User $eUser): LoginLink {

		$eUser->expects(['id']);

		if(self::acceptUser($eUser) === FALSE) {
			return new LoginLink();
		}

		// Écriture de masse : un tableau de valeurs, un élément réduirait la portée à sa seule ligne
		LoginLink::model()
			->whereUser($eUser)
			->whereClosedAt(NULL)
			->update([
				'closedAt' => new \Sql('NOW()'),
				'closedBy' => LoginLink::RESENT
			]);

		$token = bin2hex(random_bytes(ChallengeAiLib::TOKEN_BYTES));

		$e = new LoginLink([
			'user' => $eUser,
			'token' => ChallengeAiLib::hashToken($token),
			'ip' => getIp(),
			'device' => \util\DeviceLib::get(),
			'expiresAt' => new \Sql('NOW() + INTERVAL '.self::LIFETIME.' SECOND'),
		]);

		self::create($e);

		$e['clearToken'] = $token;

		return $e;

	}

	/**
	 * Le lien que ce jeton désigne, s'il peut encore connecter quelqu'un : ni fermé, ni expiré, et
	 * son compte peut toujours entrer par un lien (`acceptUser()`). Le jeton vient de l'URL. Un
	 * compte suspendu ou banni après l'envoi rend donc son lien mort, comme un lien déjà servi.
	 */
	public static function getByToken(?string $token): LoginLink {

		if(
			$token === NULL or
			strlen($token) !== ChallengeAiLib::TOKEN_BYTES * 2 or
			ctype_xdigit($token) === FALSE
		) {
			return new LoginLink();
		}

		$e = LoginLink::model()
			->select(LoginLink::getSelection() + [
				'user' => User::getSelection()
			])
			->whereToken(ChallengeAiLib::hashToken($token))
			->whereClosedAt(NULL)
			->where('expiresAt > NOW()')
			->get();

		if(
			$e->empty() or
			self::acceptUser($e['user']) === FALSE
		) {
			return new LoginLink();
		}

		return $e;

	}

	/**
	 * Ouvre la session du compte du lien, et ferme le lien.
	 *
	 * Le lien se ferme avant l'ouverture de la session, sous condition qu'il soit encore ouvert :
	 * deux envois simultanés du même lien, depuis deux navigateurs, ne ferment qu'une ligne, et seul
	 * celui qui l'a fermée entre. Le blocage et la série de tentatives ne se touchent qu'après une
	 * session ouverte, dans l'ordre de `ConnectionLib::logIn()`.
	 *
	 * Le compte repasse par `acceptUser()` bien que `getByToken()` l'ait déjà fait : la méthode
	 * reçoit un élément, pas un jeton, et ne peut pas savoir d'où il vient.
	 */
	public static function succeed(LoginLink $e): bool {

		$e->expects(['id', 'user']);

		$eUser = $e['user'];

		if(self::acceptUser($eUser) === FALSE) {
			return FALSE;
		}

		$affected = LoginLink::model()
			->whereId($e['id'])
			->whereClosedAt(NULL)
			->update([
				'closedAt' => new \Sql('NOW()'),
				'closedBy' => LoginLink::LINK
			]);

		if($affected === 0) {
			return FALSE;
		}

		if(ConnectionLib::logInUser($eUser) === FALSE) {
			return FALSE;
		}

		ThrottleAiLib::succeedLogin($eUser['email']);
		LoginAttemptAiLib::succeed($eUser['email'], $eUser, LoginAttempt::LINK);

		return TRUE;

	}

	/**
	 * Ce compte peut-il entrer par un lien ? Ni administrateur, ni banni depuis ce poste, et actif.
	 */
	public static function acceptUser(User $eUser): bool {

		$eUser->expects(['id', 'status']);

		return (
			$eUser['status'] === User::ACTIVE and
			$eUser->isAdmin() === FALSE and
			BanLib::getByUser($eUser, getIp())->empty()
		);

	}

	public static function clean(): void {

		LoginLink::model()
			->where('createdAt < NOW() - INTERVAL '.self::KEEP_DAYS.' DAY')
			->delete();

	}

}
?>
