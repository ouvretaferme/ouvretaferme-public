<?php
namespace user;

/**
 * L'acceptation des CGU de Ouvretaferme, une ligne par version acceptée.
 *
 * Les versions et leur date d'entrée en vigueur sont dans `UserSetting::$tos`. Une version se déclare
 * dès sa mise en ligne, avec la date du préavis écoulé, et la fenêtre l'annonce dès que
 * `MainSetting::CGU_POPUP` est ouvert, c'est-à-dire dès que `cgu.pdf` porte son texte. Aucun e-mail ne
 * part. Elle ne s'applique qu'à cette date, et la précédente reste en vigueur jusque-là.
 *
 * Les façons d'accepter, que la colonne `mode` distingue :
 * - `SIGNUP` : la case cochée à l'inscription, ou l'entrée dans une première structure, par sa
 *   création ou par une invitation, d'un compte qui n'avait encore rien accepté (un client de boutique
 *   qui devient producteur ou membre d'une équipe) ;
 * - `EXPLICIT` : « J'accepte » dans la fenêtre qui présente une version annoncée, pas encore en
 *   vigueur, y compris quand le clic part d'une fenêtre restée ouverte après l'entrée en vigueur.
 *   « Plus tard » la ferme jusqu'à la connexion suivante ;
 * - `HISTORIC` : la reprise des comptes existants au déploiement, datée de leur création ;
 * - `TACIT` : le premier écran chargé après l'entrée en vigueur, par un compte qui avait accepté une
 *   version précédente : continuer à utiliser le service vaut acceptation (CGU, article 12). La
 *   fenêtre présente ensuite une fois ce qui a changé, sans rien demander. Le lien de refus de la
 *   fenêtre d'une version annoncée n'en pose pas : la session qui l'a suivi va fermer le compte ou
 *   exporter ses données, et la règle ne reprend qu'à la connexion suivante. Celui de la fenêtre
 *   d'une version en vigueur arrive une fois la tacite enregistrée, et mène à la fermeture du compte.
 *
 * Seul un compte qui a déjà accepté une version se voit présenter la suivante. Un compte créé depuis
 * une boutique n'a rien accepté : ses conditions sont celles du commerçant, et se connecter ne
 * l'engage pas envers Ouvretaferme. Une connexion en tant qu'un autre compte n'engage que
 * l'administrateur, et l'API n'est ouverte que par un compte qui a déjà accepté. L'API ne charge aucun
 * écran : un compte qui ne passe que par elle après l'entrée en vigueur n'enregistre pas de `TACIT`.
 *
 * Les lignes sont une preuve : aucune purge, elles ne partent qu'avec la suppression physique du
 * compte (`DropLib::delete()`).
 */
class TosAiLib extends TosCrud {

	// La dernière version, annoncée ou déjà en vigueur : la première se présente dans la fenêtre pour être acceptée, la seconde s'accepte en continuant puis s'y présente une fois
	const ANNOUNCED = 'announced';
	const EFFECTIVE = 'effective';

	// La version reportée par « Plus tard » ou refusée par le lien de refus, jusqu'à la fin de la session
	const SESSION_LATER = 'tosLater';
	const SESSION_REFUSED = 'tosRefused';

	// La version acceptée tacitement dans cette session et pas encore présentée dans la fenêtre
	const SESSION_TACIT = 'tosTacit';

	// Lu une fois ici pour qu'un test puisse ouvrir ou fermer la fenêtre sans toucher à la constante
	public static bool $popup = \main\MainSetting::CGU_POPUP;

	public static function getCurrentVersion(): int {

		return self::getVersionAt(\Clock::date());

	}

	/**
	 * Une date antérieure à la première version rend celle-ci : un compte entré avant que les CGU
	 * existent n'a jamais connu d'autre texte.
	 */
	public static function getVersionAt(string $date): int {

		$current = array_key_first(UserSetting::$tos);

		foreach(UserSetting::$tos as $version => $effectiveAt) {

			if($effectiveAt <= $date) {
				$current = $version;
			}

		}

		return $current;

	}

	public static function getLatestVersion(): int {

		return array_key_last(UserSetting::$tos);

	}

	public static function getLatestEffectiveAt(): string {

		return UserSetting::$tos[self::getLatestVersion()];

	}

	/**
	 * La version que présente `cgu.pdf` : la dernière déclarée une fois la fenêtre ouverte, la version en
	 * vigueur tant que le nouveau texte n'est pas en ligne.
	 */
	public static function getPublishedVersion(): int {

		if(self::$popup) {
			return self::getLatestVersion();
		} else {
			return self::getCurrentVersion();
		}

	}

	/**
	 * Ce que ce compte a à accepter : `ANNOUNCED` pour une version pas encore en vigueur, `EFFECTIVE` pour
	 * une version en vigueur, NULL s'il n'a rien à accepter. « Plus tard » n'y change rien : il écarte la
	 * fenêtre, pas l'acceptation, et « J'accepte » cliqué après lui s'enregistre encore.
	 */
	public static function getPending(User $eUser): ?string {

		if(
			self::$popup === FALSE or
			$eUser->empty() or
			ConnectionLib::isLoggedExternal()
		) {
			return NULL;
		}

		$versions = Tos::model()
			->whereUser($eUser)
			->getColumn('version');

		if(
			$versions === [] or
			in_array(self::getLatestVersion(), $versions, TRUE)
		) {
			return NULL;
		}

		if(self::getLatestVersion() === self::getCurrentVersion()) {
			return self::EFFECTIVE;
		} else {
			return self::ANNOUNCED;
		}

	}

	/**
	 * Appelée à chaque écran : une version en vigueur s'accepte tacitement, puis se présente une fois
	 * dans la fenêtre, et une version annoncée se présente tant que « Plus tard » ne l'a pas écartée pour
	 * la session. Rend ce que la fenêtre doit présenter, `ANNOUNCED`, `EFFECTIVE` ou NULL.
	 *
	 * L'écran qui enregistre la tacite ne sait pas toujours ouvrir la fenêtre (une action ajax, une
	 * boutique, la caisse) : `EFFECTIVE` reste donc rendu tant que la fenêtre ne l'a pas consommé
	 * (`consumeTacit()`), et elle s'ouvre au premier écran qui porte son déclencheur.
	 */
	public static function acceptOnUse(User $eUser): ?string {

		if(self::isInSession(self::SESSION_REFUSED)) {
			return NULL;
		}

		if(self::isInSession(self::SESSION_TACIT)) {
			return self::EFFECTIVE;
		}

		$pending = self::getPending($eUser);

		if($pending === self::EFFECTIVE) {
			self::accept($eUser, self::getLatestVersion(), Tos::TACIT);
			\session\SessionLib::set(self::SESSION_TACIT, self::getLatestVersion());
			return self::EFFECTIVE;
		}

		// Seule une version annoncée se reporte : après son entrée en vigueur, une session ouverte avant doit l'accepter en continuant
		if(self::isInSession(self::SESSION_LATER)) {
			return NULL;
		}

		return $pending;

	}

	public static function postpone(): void {

		\session\SessionLib::set(self::SESSION_LATER, self::getLatestVersion());

	}

	public static function refuse(): void {

		\session\SessionLib::set(self::SESSION_REFUSED, self::getLatestVersion());

	}

	/**
	 * La fenêtre qui présente une version acceptée tacitement ne s'ouvre qu'une fois : rend vrai et retire
	 * la clé si elle porte la dernière version.
	 */
	public static function consumeTacit(): bool {

		if(self::isInSession(self::SESSION_TACIT) === FALSE) {
			return FALSE;
		}

		\session\SessionLib::delete(self::SESSION_TACIT);

		return TRUE;

	}

	private static function isInSession(string $key): bool {

		return (
			\session\SessionLib::exists($key) and
			\session\SessionLib::get($key) === self::getLatestVersion()
		);

	}

	/**
	 * Une version quelconque, en vigueur ou non : c'est ce qui sépare un compte engagé envers
	 * Ouvretaferme d'un compte qui n'a encore rien accepté.
	 */
	public static function hasAccepted(User $eUser): bool {

		return Tos::model()
			->whereUser($eUser)
			->exists();

	}

	/**
	 * La version publiée, en vigueur ou non : c'est son texte que la case de l'inscription présente, et un
	 * compte neuf n'a pas à lire ce qui change par rapport à une version qu'il n'a pas vue.
	 */
	public static function acceptOnSignUp(User $eUser): void {

		self::accept($eUser, self::getPublishedVersion(), Tos::SIGNUP);

	}

	/**
	 * Un compte qui entre dans une structure, qu'il la crée ou qu'il y soit invité, utilise l'outil :
	 * s'il n'a jamais rien accepté, il accepte ici la version publiée, comme à l'inscription, que le
	 * parcours ait montré la case ou non.
	 */
	public static function acceptOnStructure(User $eUser): void {

		$eUser->expects(['id']);

		if(self::hasAccepted($eUser) === FALSE) {
			self::accept($eUser, self::getPublishedVersion(), Tos::SIGNUP);
		}

	}

	/**
	 * La même règle, appliquée après coup à un compte entré dans une structure avant qu'elle n'existe :
	 * la version en vigueur à son entrée, datée de son entrée. Seule la reprise
	 * de `migration/2026-10-01-cgu-membres-invites.md` l'appelle, son script se récupère par git.
	 */
	public static function acceptOnStructureAt(User $eUser, string $joinedAt): void {

		$eUser->expects(['id']);

		if(self::hasAccepted($eUser) === FALSE) {
			self::accept($eUser, self::getVersionAt(substr($joinedAt, 0, 10)), Tos::SIGNUP, $joinedAt);
		}

	}

	/**
	 * La version acceptée est la dernière déclarée, en vigueur ou non : c'est celle que la fenêtre présente.
	 */
	public static function acceptLatest(User $eUser): void {

		self::accept($eUser, self::getLatestVersion(), Tos::EXPLICIT);

	}

	private static function accept(User $eUser, int $version, string $mode, ?string $acceptedAt = NULL): void {

		$eUser->expects(['id']);

		$eTos = new Tos([
			'user' => $eUser,
			'version' => $version,
			'mode' => $mode
		]);

		if($acceptedAt !== NULL) {
			$eTos['acceptedAt'] = $acceptedAt;
		}

		// Deux onglets peuvent accepter la même version : le second n'a rien à ajouter
		Tos::model()
			->option('add-ignore')
			->insert($eTos);

	}

	public static function deleteByUser(User $eUser): void {

		Tos::model()
			->whereUser($eUser)
			->delete();

	}

}
?>
