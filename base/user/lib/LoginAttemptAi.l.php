<?php
namespace user;

/**
 * Les tentatives de connexion échouées : une ligne par série, c'est-à-dire par identifiant tenté
 * et adresse sur la fenêtre de la limitation (`ThrottleAiLib::WINDOW_LOGIN`).
 *
 * `ThrottleAiLib` compte dans Redis et refuse au-delà de la limite, puis oublie : personne ne
 * savait qu'un compte avait été essayé. Ici la série reste en base, avec ses échecs, le compte
 * visé s'il existe, le refus quand la limitation l'a prononcé, et la réussite quand une connexion
 * aboutit après les échecs. `ConnectionLib::logIn()` écrit aux trois endroits où elle sait ce qui
 * s'est passé, la limitation ne change pas.
 *
 * L'identifiant tenté s'écrit en clair, normalisé comme la limitation le compare, sur décision
 * du 23/09/2026 en recette : c'est lui que l'écran affiche et que la recherche retrouve
 * par un morceau. Le mot de passe essayé ne s'écrit nulle part. Le compte visé, lui, se lit sur
 * `user` quand il existe.
 *
 * Discord ne reçoit un message que pour un compte administrateur : dès le premier échec de la
 * série, et de nouveau si une connexion finit par réussir après au moins `THRESHOLD` échecs. Sur
 * un compte ordinaire, une réussite après des échecs est un mot de passe retrouvé et n'alerte
 * personne (décidé le 02/10/2026 : 17 des 17 séries ainsi notifiées en production
 * visaient un compte ordinaire). L'écran, lui, montre toutes les séries.
 */
class LoginAttemptAiLib extends LoginAttemptCrud {

	const WINDOW = ThrottleAiLib::WINDOW_LOGIN;
	const THRESHOLD = 5;
	const KEEP_DAYS = 90;

	const FILTER_THRESHOLD = 'threshold';
	const FILTER_THROTTLED = 'throttled';
	const FILTER_SUCCEEDED = 'succeeded';

	const STATUS_SUCCEEDED = [LoginAttempt::SUCCEEDED, LoginAttempt::LINK];

	const NOTIFY_ADMIN = 'admin';
	const NOTIFY_SUCCEEDED = 'succeeded';

	const LOGIN_LENGTH = 100;

	/**
	 * Un échec de plus sur cet identifiant depuis ce poste ; `$throttled` quand c'est la
	 * limitation qui a refusé la tentative sans regarder le mot de passe.
	 */
	public static function fail(string $login, bool $throttled): void {

		$login = self::normalize($login);

		if($login === '') {
			return;
		}

		$e = self::getOpen($login, getIp());

		if($e->empty()) {

			$e = new LoginAttempt([
				'login' => $login,
				'user' => self::getUserByLogin($login),
				'ip' => getIp(),
				'failures' => 1,
				'status' => $throttled ? LoginAttempt::THROTTLED : LoginAttempt::RUNNING,
				'notifiedAt' => NULL,
			]);

			self::create($e);

		} else {

			$e['failures'] = new \Sql('failures + 1');
			$e['lastAt'] = new \Sql('NOW()');

			if($throttled) {
				$e['status'] = LoginAttempt::THROTTLED;
			}

			self::update($e, ['failures', 'lastAt', 'status']);

		}

		if(
			$e['user']->isAdmin() and
			$e['notifiedAt'] === NULL
		) {
			self::notify($e, self::NOTIFY_ADMIN);
		}

	}

	/**
	 * Une connexion a réussi sur cet identifiant : la série ouverte, quel que soit le poste, se
	 * clôt en réussite, par le mot de passe (`SUCCEEDED`) ou par un lien reçu par e-mail (`LINK`).
	 * Sans série ouverte, rien ne s'écrit, `userLog` porte déjà les connexions.
	 */
	public static function succeed(string $login, User $eUser, string $status = LoginAttempt::SUCCEEDED): void {

		$login = self::normalize($login);

		if($login === '') {
			return;
		}

		$e = self::getOpen($login);

		if($e->empty()) {
			return;
		}

		$e['status'] = $status;
		$e['user'] = $eUser;
		$e['lastAt'] = new \Sql('NOW()');

		self::update($e, ['status', 'user', 'lastAt']);

		if(self::isNotifiableSuccess($e)) {
			self::notify($e, self::NOTIFY_SUCCEEDED);
		}

	}

	/**
	 * Une réussite n'alerte que si elle clôt une série d'au moins `THRESHOLD` échecs sur un compte
	 * administrateur. Sur un compte ordinaire, c'est un mot de passe retrouvé, et le seuil ne
	 * suffit pas à en faire un événement : la série reste visible sur l'écran.
	 *
	 * Un identifiant qui ne désigne aucun compte donne un élément vide, dont `isAdmin()` rend
	 * `FALSE` sans lire d'identifiant.
	 */
	public static function isNotifiableSuccess(LoginAttempt $e): bool {

		$e->expects(['failures', 'user']);

		return (
			$e['failures'] >= self::THRESHOLD and
			$e['user']->isAdmin()
		);

	}

	/**
	 * La série encore ouverte pour cet identifiant, bornée au poste quand l'adresse est donnée :
	 * ni réussie, ni plus vieille que la fenêtre.
	 */
	public static function getOpen(string $login, ?string $ip = NULL): LoginAttempt {

		return LoginAttempt::model()
			->select(LoginAttempt::getSelection())
			->whereLogin($login)
			->whereIp($ip, if: $ip !== NULL)
			->whereStatus('NOT IN', self::STATUS_SUCCEEDED)
			->where('lastAt >= NOW() - INTERVAL '.self::WINDOW.' SECOND')
			->sort(['lastAt' => SORT_DESC])
			->get();

	}

	public static function notify(LoginAttempt $e, string $reason): bool {

		$sent = \communication\DiscordWebhookLib::send(self::getMessage($e, $reason), [], \communication\DiscordWebhookLib::SECURITY);

		if($sent) {

			$e['notifiedAt'] = new \Sql('NOW()');

			self::update($e, ['notifiedAt']);

		}

		return $sent;

	}

	/**
	 * Le message est écrit pour être lu dans Discord par l'équipe, en dehors de toute traduction :
	 * la langue du site n'y a pas de sens.
	 *
	 * Ni le compte ni l'adresse ne s'y nomment. Un salon Discord ne purge rien, là où la série
	 * disparaît d'ici au bout de KEEP_DAYS : ce qui part là-bas survit à la ligne qui le porte, et à
	 * l'effacement que le compte peut demander. Discord n'est par ailleurs déclaré destinataire dans
	 * aucun des deux documents que l'article 13 du RGPD oblige à tenir, et son salon est hébergé hors
	 * de l'Union. Le lien ne porte donc que l'identifiant de la série, que `getIpById()` résout
	 * derrière l'authentification pour rouvrir l'écran filtré sur le poste : qui est visé et d'où se
	 * lisent là, qui est le seul endroit qui doive le dire.
	 */
	public static function getMessage(LoginAttempt $e, string $reason): string {

		$e->expects(['id', 'failures', 'status']);

		$url = \Lime::getUrl().'/user/admin/loginAttemptAi?attempt='.$e['id'];

		return match($reason) {
			self::NOTIFY_ADMIN => '🔐 Échec de connexion sur un compte administrateur. '.$url,
			self::NOTIFY_SUCCEEDED => match($e['status']) {
				LoginAttempt::SUCCEEDED => '🔑 Connexion réussie par mot de passe',
				LoginAttempt::LINK => '📧 Connexion réussie par un lien reçu par e-mail',
			}.' après '.$e['failures'].' échecs. '.$url,
		};

	}

	/**
	 * Les séries qui répondent à la recherche, la page demandée et leur nombre total.
	 */
	public static function getAll(\Search $search, int $page): array {

		self::applySearch($search);

		$cLoginAttempt = LoginAttempt::model()
			->select(LoginAttempt::getSelection() + ['user' => User::getSelection()])
			->sort(['lastAt' => SORT_DESC, 'id' => SORT_DESC])
			->option('count')
			->getCollection($page * UserSetting::MAX_BY_PAGE, UserSetting::MAX_BY_PAGE);

		$nLoginAttempt = LoginAttempt::model()->found();

		foreach($cLoginAttempt as $eLoginAttempt) {
			$eLoginAttempt['isOpen'] = self::isOpen($eLoginAttempt);
		}

		return [$cLoginAttempt, $nLoginAttempt];

	}

	/**
	 * Une série en cours n'est pas réussie et sa dernière tentative tient dans la fenêtre : un échec
	 * de plus la rejoindrait encore.
	 */
	public static function isOpen(LoginAttempt $e): bool {

		$e->expects(['status', 'lastAt']);

		return (
			in_array($e['status'], self::STATUS_SUCCEEDED, TRUE) === FALSE and
			strtotime($e['lastAt']) >= \Clock::timestamp() - self::WINDOW
		);

	}

	/**
	 * L'adresse que porte une série, pour un lien qui n'a pu transporter que son identifiant
	 * (cf. `getMessage()`). Sans adresse, l'écran appelant s'ouvre sans filtre.
	 */
	public static function getIpById(?int $id): ?string {

		if($id === NULL) {
			return NULL;
		}

		$eLoginAttempt = LoginAttempt::model()
			->select('ip')
			->whereId($id)
			->get();

		return $eLoginAttempt->notEmpty() ? $eLoginAttempt['ip'] : NULL;

	}

	public static function applySearch(\Search $search): void {

		LoginAttempt::model()
			->whereIp($search->get('ip'), if: $search->get('ip'))
			->where('m1.lastAt', '>=', $search->get('from'), if: $search->get('from'))
			->where('m1.lastAt', '<', new \Sql(LoginAttempt::model()->format($search->get('to')).' + INTERVAL 1 DAY'), if: $search->get('to'));

		if($search->get('login')) {
			LoginAttempt::model()->where('m1.login', 'LIKE', '%'.self::normalize($search->get('login')).'%');
		}

		switch($search->get('status')) {

			case self::FILTER_THRESHOLD :
				LoginAttempt::model()->whereFailures('>=', self::THRESHOLD);
				break;

			case self::FILTER_THROTTLED :
				LoginAttempt::model()->whereStatus(LoginAttempt::THROTTLED);
				break;

			case self::FILTER_SUCCEEDED :
				LoginAttempt::model()->whereStatus('IN', self::STATUS_SUCCEEDED);
				break;

		}

	}

	public static function clean(): void {

		LoginAttempt::model()
			->where('lastAt < NOW() - INTERVAL '.self::KEEP_DAYS.' DAY')
			->delete();

	}

	/**
	 * Le compte dont c'est l'identifiant, s'il existe ; `logInBasic()` ne distingue pas, à dessein,
	 * un identifiant inconnu d'un mauvais mot de passe, ce journal a besoin de le savoir.
	 */
	public static function getUserByLogin(string $login): User {

		$eUserAuth = UserAuth::model()
			->select(['user' => User::getSelection()])
			->whereType(UserAuth::BASIC)
			->whereLogin($login)
			->get();

		if($eUserAuth->empty() or $eUserAuth['user']->empty()) {
			return new User();
		}

		return $eUserAuth['user'];

	}

	/**
	 * Même normalisation que `ThrottleAiLib::subjectLogin()`, pour que la série et le compteur
	 * désignent la même chose.
	 */
	public static function normalize(string $login): string {
		return mb_substr(mb_strtolower(trim($login)), 0, self::LOGIN_LENGTH);
	}

}
?>
