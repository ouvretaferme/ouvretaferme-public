<?php
namespace user;

/**
 * Limitation des tentatives sur les entrées anonymes du site : connexion, mot de passe oublié,
 * inscription, les formulaires publics d'un site de structure, et les appels d'API refusés à
 * l'authentification.
 *
 * Un compteur par sujet (identifiant tenté, adresse e-mail, adresse IP) vit dans Redis avec une
 * fenêtre fixe : il naît à la première tentative, expire seul, et se refuse au-delà de sa limite.
 * L'API a sa propre limitation en base (`api\RateLimitLib`), sur un jeton connu ; ici le sujet est
 * anonyme, donc c'est ce qu'il tente ou d'où il vient qui le désigne. Un appel d'API sans jeton
 * valide est de ceux-là : il ne consomme aucun quota de structure, et coûte pourtant le chargement
 * de PHP et la vérification de la signature avant d'être refusé.
 *
 * La connexion compte les échecs seulement : un mot de passe juste remet le compteur de
 * l'identifiant à zéro, et la limite par adresse IP, dix fois plus haute, protège les identifiants
 * qu'un même poste essaierait à tour de rôle. Les autres entrées comptent chaque envoi, parce
 * qu'elles coûtent à chaque fois (un e-mail part, un compte se crée).
 *
 * L'envoi d'un modèle 3D d'avatar est la seule entrée comptée pour un utilisateur connecté : sa
 * conversion lance un processus node à chaque fois, que le modèle soit gardé ou refusé.
 *
 * Sans Redis (`\Cache::isDisabled()`), rien n'est compté et rien n'est refusé : la limitation est
 * une protection de production, pas une condition de fonctionnement.
 */
class ThrottleAiLib {

	const WINDOW_LOGIN = 900;
	const LIMIT_LOGIN = 10;
	const LIMIT_LOGIN_IP = 100;

	// À partir de ce nombre d'échecs, le refus propose de choisir un nouveau mot de passe
	const FORGOTTEN_LOGIN = 5;

	const WINDOW_FORGOTTEN = 3600;
	const LIMIT_FORGOTTEN = 3;
	const LIMIT_FORGOTTEN_IP = 20;

	const WINDOW_SIGN_UP = 3600;
	const LIMIT_SIGN_UP_IP = 10;

	const WINDOW_CONTACT = 3600;
	const LIMIT_CONTACT_IP = 5;

	const WINDOW_API = 900;
	const LIMIT_API_IP = 100;

	const WINDOW_AVATAR_UPLOAD = 86400;
	const LIMIT_AVATAR_UPLOAD = 50;

	const PREFIX = 'throttle-';

	/**
	 * La connexion est-elle encore ouverte à cet identifiant depuis ce poste ?
	 */
	public static function checkLogin(string $login): bool {

		if(
			self::isExceeded('login', self::subjectLogin($login), self::LIMIT_LOGIN) or
			self::isExceeded('login-ip', getIp(), self::LIMIT_LOGIN_IP)
		) {
			return User::fail('connectionThrottled', ['login' => $login]);
		}

		return TRUE;

	}

	public static function failLogin(string $login): void {

		self::hit('login', self::subjectLogin($login), self::WINDOW_LOGIN);
		self::hit('login-ip', getIp(), self::WINDOW_LOGIN);

	}

	/**
	 * Les échecs sont-ils assez nombreux pour que le refus propose un nouveau mot de passe ?
	 */
	public static function isForgottenLogin(string $login): bool {
		return self::count('login', self::subjectLogin($login)) >= self::FORGOTTEN_LOGIN;
	}

	public static function succeedLogin(string $login): void {

		self::clear('login', self::subjectLogin($login));

	}

	/**
	 * Le lien de mot de passe oublié peut-il encore partir vers cette adresse depuis ce poste ?
	 * Chaque demande compte, qu'elle aboutisse ou non : une adresse inconnue coûte une requête et
	 * renseigne sur l'existence du compte.
	 */
	public static function checkForgotten(string $email): bool {

		if(
			self::isExceeded('forgotten', self::subjectLogin($email), self::LIMIT_FORGOTTEN) or
			self::isExceeded('forgotten-ip', getIp(), self::LIMIT_FORGOTTEN_IP)
		) {
			return User::fail('forgottenThrottled');
		}

		self::hit('forgotten', self::subjectLogin($email), self::WINDOW_FORGOTTEN);
		self::hit('forgotten-ip', getIp(), self::WINDOW_FORGOTTEN);

		return TRUE;

	}

	/**
	 * Un compte de plus peut-il se créer depuis ce poste ?
	 */
	public static function checkSignUp(): bool {

		if(self::isExceeded('sign-up-ip', getIp(), self::LIMIT_SIGN_UP_IP)) {
			return User::fail('signUpThrottled');
		}

		self::hit('sign-up-ip', getIp(), self::WINDOW_SIGN_UP);

		return TRUE;

	}

	/**
	 * Un formulaire public d'un site de structure (message, lettre d'information) peut-il encore
	 * être envoyé depuis ce poste ? Le refus est à la charge de l'appelant, qui a son propre module.
	 */
	public static function acceptContact(): bool {

		if(self::isExceeded('contact-ip', getIp(), self::LIMIT_CONTACT_IP)) {
			return FALSE;
		}

		self::hit('contact-ip', getIp(), self::WINDOW_CONTACT);

		return TRUE;

	}

	/**
	 * Un appel d'API peut-il encore tenter de s'authentifier depuis ce poste ? Seuls les refus
	 * comptent, comme pour la connexion : une intégration dont le jeton est valide n'use rien, et un
	 * balayage sans jeton se coupe avant la vérification de la signature.
	 */
	public static function acceptApi(): bool {
		return (self::isExceeded('api-ip', getIp(), self::LIMIT_API_IP) === FALSE);
	}

	public static function failApi(): void {

		self::hit('api-ip', getIp(), self::WINDOW_API);

	}

	/**
	 * Un modèle 3D de plus peut-il être converti pour cet utilisateur ? L'envoi compte avant d'être
	 * jugé, refus compris, et se juge sur la valeur que rend l'incrément : deux envois simultanés qui
	 * liraient le compteur avant de l'incrémenter passeraient tous les deux sur la même valeur.
	 */
	public static function acceptAvatarUpload(User $eUser): bool {
		return (self::increment('avatar-upload', (string)$eUser['id'], self::WINDOW_AVATAR_UPLOAD) <= self::LIMIT_AVATAR_UPLOAD);
	}

	public static function isExceeded(string $scope, string $subject, int $limit): bool {

		if(\Cache::isDisabled()) {
			return FALSE;
		}

		return (int)\Cache::redis()->get(self::key($scope, $subject)) >= $limit;

	}

	public static function count(string $scope, string $subject): int {

		if(\Cache::isDisabled()) {
			return 0;
		}

		return (int)\Cache::redis()->get(self::key($scope, $subject));

	}

	/**
	 * Une tentative de plus. La fenêtre part de la première tentative et ne se prolonge pas aux
	 * suivantes, sinon un flux régulier maintiendrait le refus indéfiniment.
	 */
	public static function hit(string $scope, string $subject, int $window): void {

		self::increment($scope, $subject, $window);

	}

	/**
	 * Une tentative de plus, et le compte qu'elle atteint, 0 sans Redis.
	 */
	public static function increment(string $scope, string $subject, int $window): int {

		if(\Cache::isDisabled()) {
			return 0;
		}

		$key = self::key($scope, $subject);
		$count = \Cache::redis()->increment($key);

		if($count === 1) {
			\Cache::redis()->setTimeout($key, $window);
		}

		return $count;

	}

	public static function clear(string $scope, string $subject): void {

		if(\Cache::isDisabled()) {
			return;
		}

		\Cache::redis()->delete(self::key($scope, $subject));

	}

	private static function key(string $scope, string $subject): string {
		return self::PREFIX.$scope.'-'.$subject;
	}

	/**
	 * Un identifiant ou une adresse e-mail se compare sans la casse, et ne s'écrit pas en clair
	 * dans une clé de cache.
	 */
	private static function subjectLogin(string $login): string {
		return hash('sha256', mb_strtolower(trim($login)));
	}

}
?>
