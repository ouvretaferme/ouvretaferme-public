<?php
namespace user;

/**
 * Le modèle 3D qu'un utilisateur téléverse à la place de son avatar composé. Une ligne par envoi, gardée après un
 * remplacement ou un retour à la composition pour la modération : seul le fichier part, et avec lui `hash` et
 * `bakedIndex`. `UserAvatar.upload` désigne l'envoi montré, NULL pour la composition, qui reste l'image de profil.
 */
class UserAvatarUploadLib extends UserAvatarUploadCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['commitment', 'file'];
	}

	public static function getPropertiesWithdraw(): array {
		return ['reason', 'reasonText'];
	}

	/**
	 * Le drapeau s'ouvre à l'utilisateur dont une structure est dans la liste, sur la collection de ses structures que
	 * `Page.l.php` charge déjà pour le menu.
	 */
	public static function isOpenByStructures(\Collection $ccStructure): bool {

		foreach($ccStructure as $cStructure) {
			foreach($cStructure as $eStructure) {
				if(UserSetting::isAvatarUploadEnabled($eStructure)) {
					return TRUE;
				}
			}
		}

		return FALSE;

	}

	/**
	 * Les modèles téléversés se montrent dans les scènes d'une structure qui a un plan (la condition de
	 * `\map\SceneLib::isOpenByStructures()`, prise pour elle seule), si le drapeau lui est ouvert.
	 */
	public static function isOpenByStructure(\structure\Structure $eStructure): bool {

		return (
			$eStructure->hasFeatureProduction() and
			UserSetting::isAvatarUploadEnabled($eStructure)
		);

	}

	/**
	 * Ceux de `$cUser` dont le modèle peut paraître dans les scènes de `$eStructure`, indexés par id : membres de l'équipe
	 * active, hors rôle fantôme, au compte actif et sans bannissement en cours. Le bannissement ne coupe que les sessions
	 * (`BanLib::createBan()`) : sans ce filtre, le modèle d'un banni resterait montré à ses co-membres.
	 *
	 * C'est la seule règle de l'auteur : la page qui pose l'adresse du modèle (`getByUsers()`) et la route qui le sert
	 * (`UserAvatarUpload::canReadByStructure()`) la lisent toutes deux.
	 */
	public static function getAuthorsByStructure(\structure\Structure $eStructure, \Collection $cUser): \Collection {

		if($cUser->empty()) {
			return new \Collection();
		}

		// Même borne de fin que `BanLib::getByUser()`, sans l'adresse IP : c'est le compte de l'auteur qui est en cause
		$mBan = new BanModel()
			->whereUser(new \Sql('m1.user'))
			->where('(s1.until IS NULL OR s1.until > '.Ban::model()->format(\Clock::datetime()).')');

		return \structure\Member::model()
			->select([
				'user' => ['status'],
				'role' => ['fqn']
			])
			->whereStructure($eStructure)
			->whereUser('IN', $cUser)
			->whereStatus(\structure\Member::IN)
			->whereStructureStatus(\structure\Member::ACTIVE)
			->whereRole('!=', NULL)
			->where('NOT '.$mBan->existsSql())
			->getCollection()
			->find(fn(\structure\Member $eMember) => (
				$eMember['role']->isGhost() === FALSE and
				$eMember['user']['status'] === User::ACTIVE
			))
			->getColumnCollection('user', 'user');

	}

	/**
	 * Les modèles que `$eStructure` montre dans ses scènes pour les personnes de `$cUser`, indexés par utilisateur : l'envoi
	 * que leur avatar désigne, montrable (`UserAvatarUpload::acceptRead()`), d'un auteur admis (`getAuthorsByStructure()`).
	 * Aucun si la structure n'a pas les modèles téléversés (`isOpenByStructure()`). Le hash reste dans le Lib : seuls
	 * l'id, l'utilisateur et l'index sortent, l'adresse du fichier se forme sur l'id (`UserAvatarUploadUi::url()`).
	 */
	public static function getByUsers(\structure\Structure $eStructure, \Collection $cUser): \Collection {

		if(self::isOpenByStructure($eStructure) === FALSE) {
			return new \Collection();
		}

		if($cUser->empty()) {
			return new \Collection();
		}

		$mUserAvatar = new UserAvatarModel()
			->select('upload')
			->whereUser('IN', $cUser)
			->whereUpload('!=', NULL);

		$cUserAvatarUpload = UserAvatarUpload::model()
			->select(['id', 'user', 'bakedIndex'])
			->whereId('IN', $mUserAvatar->getSql())
			->whereStatus('IN', [UserAvatarUpload::NEW, UserAvatarUpload::KEPT])
			->whereHash('!=', NULL)
			->getCollection(index: 'user');

		// Les modèles d'abord, les auteurs ensuite : une équipe sans modèle n'a pas à lire les adhésions, les comptes et les
		// rôles que demande la règle de l'auteur
		if($cUserAvatarUpload->empty()) {
			return $cUserAvatarUpload;
		}

		$cUserAuthor = self::getAuthorsByStructure($eStructure, $cUserAvatarUpload->getColumnCollection('user', 'user'));

		return $cUserAvatarUpload->find(fn(UserAvatarUpload $eUserAvatarUpload) => $cUserAuthor->offsetExists($eUserAvatarUpload['user']['id']));

	}

	/**
	 * L'envoi montré par l'avatar, avec son index, vide si l'avatar montre sa composition.
	 */
	public static function getByAvatar(UserAvatar $eUserAvatar): UserAvatarUpload {

		$eUserAvatar->expects(['upload']);

		if($eUserAvatar['upload']->empty()) {
			return new UserAvatarUpload();
		}

		return UserAvatarUpload::model()
			->select(UserAvatarUpload::getSelection())
			->whereId($eUserAvatar['upload'])
			->whereHash('!=', NULL)
			->get();

	}

	/**
	 * Le modèle converti remplace celui que l'avatar montrait, qui quitte le disque une fois la transaction validée. L'avatar
	 * se relit sous verrou : deux envois simultanés ne doivent pas laisser chacun l'ancien fichier de l'autre, et un avatar
	 * supprimé entre-temps n'a plus de composition où revenir.
	 *
	 * L'avatar prend le choix de remplacer la photo posté avec le modèle (`$eUserAvatar`), l'image étant celle du modèle que
	 * l'éditeur a rendue (`UserAvatarLib::placeImage()`), placée avant l'envoi du binaire : refusée, elle annule tout sans
	 * rien laisser au stockage. L'inverse ne tient pas : un fichier de la photo réécrit sur place ne s'annule pas avec la
	 * base, si bien qu'un binaire refusé par le stockage laisse l'image du modèle à la place de l'ancienne. Sans `$eUserAvatar`, pour une copie d'un modèle déjà converti, la photo et son image restent
	 * ce qu'elles étaient, aucune image du modèle n'étant rendue.
	 */
	public static function create(UserAvatarUpload $e, UserAvatar $eUserAvatar = new UserAvatar(), ?string $image = NULL): void {

		$e->expects(['user', 'rules', 'bakedIndex', 'bakedBuffer']);

		UserAvatarUpload::model()->beginTransaction();

			$eUserAvatarLocked = UserAvatarLib::getByUserForUpdate($e['user']);

			if($eUserAvatarLocked->empty()) {
				UserAvatarUpload::model()->rollBack();
				throw new \Exception('No composed avatar');
			}

			self::withdrawFiles($e['user']);

			$e['hash'] = NULL;

			parent::create($e);

			if($eUserAvatar->notEmpty()) {

				$replacedBefore = $eUserAvatarLocked['replacesPhoto'];
				$eUserAvatarLocked['replacesPhoto'] = $eUserAvatar['replacesPhoto'];

				UserAvatarLib::placeImage($eUserAvatarLocked, $replacedBefore, $image);

			}

			$hash = NULL;
			new \media\UserAvatarUploadLib()->send($e, $hash, $e['bakedBuffer'], 'bin');

			$eUserAvatarLocked['upload'] = $e;
			UserAvatarLib::update($eUserAvatarLocked, ['upload']);

		UserAvatarUpload::model()->commit();

		\media\MediaLib::deleteFiles();

	}

	/**
	 * L'avatar revient à sa composition, et le modèle qu'il montrait quitte le disque une fois la transaction validée.
	 * Quand l'avatar remplace la photo, `$image` est celle de la composition, que l'éditeur a rendue, et prend la place
	 * de celle du modèle.
	 */
	public static function clear(UserAvatar $eUserAvatar, ?string $image): void {

		$eUserAvatar->expects(['id', 'user']);

		UserAvatarUpload::model()->beginTransaction();

			$eUserAvatarLocked = UserAvatarLib::getByUserForUpdate($eUserAvatar['user']);

			// Supprimé entre-temps depuis un autre onglet : il n'y a plus de modèle à retirer
			if($eUserAvatarLocked->empty()) {
				UserAvatarUpload::model()->commit();
				throw new \FailAction('user\UserAvatarUpload::stale');
			}

			self::withdrawFiles($eUserAvatar['user']);

			$eUserAvatarLocked['upload'] = new UserAvatarUpload();
			UserAvatarLib::update($eUserAvatarLocked, ['upload']);

			if($eUserAvatarLocked['replacesPhoto']) {
				UserAvatarLib::placeImage($eUserAvatarLocked, TRUE, $image);
			}

		UserAvatarUpload::model()->commit();

		\media\MediaLib::deleteFiles();

	}

	/**
	 * Ce que l'administration lit d'un envoi : tout sauf le hash, qui ouvre le fichier à qui le connaît par l'adresse
	 * publique du stockage et ne quitte donc jamais le Lib. Le binaire se lit par sa route (`UserAvatarUploadUi::url()`).
	 */
	private static function getAdminSelection(): array {

		return [
			'id', 'bakedIndex', 'rules', 'status', 'reason', 'reasonText', 'moderatedAt', 'createdAt',
			'user' => User::getSelection(),
			'moderatedBy' => ['firstName', 'lastName']
		];

	}

	/**
	 * Les premiers modèles à examiner et leur nombre, le plus ancien en tête, et `$first` avant tous, l'envoi que nomme le
	 * lien de l'alerte Discord (`getMessage()`). Un envoi neuf remplacé ou abandonné par son auteur n'a plus rien à montrer
	 * ni à juger : il n'est pas dans la file. Elle se borne comme une page : chaque ligne montre un modèle, et ceux qui
	 * suivent arrivent à mesure que la tête est jugée.
	 */
	public static function getWaiting(int $first): array {

		$cUserAvatarUpload = UserAvatarUpload::model()
			->select(self::getAdminSelection())
			->whereStatus(UserAvatarUpload::NEW)
			->whereHash('!=', NULL)
			->sort(new \Sql('m1.id = '.$first.' DESC, m1.id ASC'))
			->option('count')
			->getCollection(0, UserSetting::MAX_BY_PAGE);

		return [$cUserAvatarUpload, UserAvatarUpload::model()->found()];

	}

	public static function countWaiting(): int {

		return UserAvatarUpload::model()
			->whereStatus(UserAvatarUpload::NEW)
			->whereHash('!=', NULL)
			->count();

	}

	/**
	 * Les modèles déjà examinés, du dernier jugé au premier, la page demandée et leur nombre : les retirés, et les gardés
	 * qui se montrent encore. Un gardé que son auteur a remplacé depuis n'a plus de modèle à revoir ni à retirer.
	 */
	public static function getExamined(int $page): array {

		$cUserAvatarUpload = UserAvatarUpload::model()
			->select(self::getAdminSelection())
			->or(
				fn() => $this->whereStatus(UserAvatarUpload::WITHDRAWN),
				fn() => $this->whereStatus(UserAvatarUpload::KEPT)->whereHash('!=', NULL)
			)
			->sort(['moderatedAt' => SORT_DESC, 'id' => SORT_DESC])
			->option('count')
			->getCollection($page * UserSetting::MAX_BY_PAGE, UserSetting::MAX_BY_PAGE);

		return [$cUserAvatarUpload, UserAvatarUpload::model()->found()];

	}

	/**
	 * L'état dans la condition de l'écriture, comme `\learning\ReviewLib::moderate()` : un modèle retiré ou remplacé entre
	 * l'affichage et le clic ne se garde pas. Rend le nombre de lignes écrites, 0 quand l'envoi n'était plus à examiner.
	 */
	public static function keep(UserAvatarUpload $e): int {

		$e['status'] = UserAvatarUpload::KEPT;
		$e['moderatedAt'] = \Clock::datetime();
		$e['moderatedBy'] = ConnectionLib::getOnline();

		return UserAvatarUpload::model()
			->select(['status', 'moderatedAt', 'moderatedBy'])
			->whereStatus(UserAvatarUpload::NEW)
			->whereHash('!=', NULL)
			->update($e);

	}

	/**
	 * Les modèles de la file que l'admin avait sous les yeux, gardés d'un geste : seuls les ids postés par la page comptent,
	 * de sorte qu'un envoi arrivé après l'affichage attend d'avoir été vu. Un envoi qui a quitté la file depuis (retiré,
	 * remplacé, gardé par un autre) est ignoré, puisque `keep()` porte l'état dans la condition de son écriture. Rend le
	 * nombre de modèles gardés, 0 aussi quand la liste passe la taille de la file affichée (`getWaiting()`), qu'aucun
	 * formulaire de la page ne peut poster.
	 */
	public static function keepAll(array $ids): int {

		if($ids === [] or count($ids) > UserSetting::MAX_BY_PAGE) {
			return 0;
		}

		$cUserAvatarUpload = UserAvatarUpload::model()
			->select('id')
			->whereId('IN', $ids)
			->whereStatus(UserAvatarUpload::NEW)
			->whereHash('!=', NULL)
			->getCollection();

		$kept = 0;

		foreach($cUserAvatarUpload as $eUserAvatarUpload) {
			$kept += self::keep($eUserAvatarUpload);
		}

		return $kept;

	}

	/**
	 * Le modèle quitte le disque une fois le retrait validé, l'avatar revient à sa composition s'il le montrait, avec
	 * l'image de celle-ci pour photo de profil quand le modèle la faisait (`UserAvatarLib::recompose()`), et l'auteur
	 * reçoit le motif. Rend FALSE sans rien écrire quand l'envoi ne se montrait plus au moment du verrou.
	 *
	 * Les verrous se prennent dans le même ordre que `create()`, `clear()` et `UserAvatarLib::delete()`, l'avatar puis
	 * les envois : un retrait qui croise un import, un retour à la composition ou la suppression de l'avatar par son
	 * auteur attend la fin de l'autre transaction au lieu de s'interbloquer avec elle. L'e-mail part après le COMMIT : envoyé avant, il annoncerait un
	 * retrait qu'une panne aurait défait.
	 */
	public static function withdraw(UserAvatarUpload $e): bool {

		$e->expects(['id', 'user', 'createdAt', 'reason', 'reasonText']);

		UserAvatarUpload::model()->beginTransaction();

			$eUserAvatar = UserAvatarLib::getByUserForUpdate($e['user']);

			$eUserAvatarUploadLocked = UserAvatarUpload::model()
				->select('id', 'hash', 'status')
				->whereId($e)
				->option('for-update')
				->get();

			if($eUserAvatarUploadLocked->acceptWithdraw() === FALSE) {
				// Rien n'est écrit : valider plutôt qu'annuler, un rollBack() emporterait la transaction d'un appelant
				UserAvatarUpload::model()->commit();
				return FALSE;
			}

			new \media\UserAvatarUploadLib()->deleteByHash($eUserAvatarUploadLocked['hash']);

			$e['hash'] = NULL;
			$e['bakedIndex'] = NULL;
			$e['status'] = UserAvatarUpload::WITHDRAWN;
			$e['moderatedAt'] = \Clock::datetime();
			$e['moderatedBy'] = ConnectionLib::getOnline();

			self::update($e, ['hash', 'bakedIndex', 'status', 'reason', 'reasonText', 'moderatedAt', 'moderatedBy']);

			// Un envoi qui se montre a son avatar : la suppression d'un avatar efface d'abord les fichiers de ses envois
			// (`UserAvatarLib::delete()`), sous le même verrou
			$shown = $eUserAvatar['upload']->is($e);

			if($shown) {
				$eUserAvatar['upload'] = new UserAvatarUpload();
				UserAvatarLib::update($eUserAvatar, ['upload']);
			}

		UserAvatarUpload::model()->commit();

		\media\MediaLib::deleteFiles();

		// L'image du modèle retiré quitte la photo de profil : Chrome rend la composition hors transaction, quelques
		// secondes qu'aucun verrou ne doit attendre, et la photo revient si le rendu échoue
		if($shown and $eUserAvatar['replacesPhoto']) {

			try {
				$image = UserAvatarLib::capture($eUserAvatar);
			} catch(\Exception $exception) {
				\dev\ErrorPhpLib::handleException($exception);
				$image = NULL;
			}

			// Le retrait est validé et l'auteur doit en recevoir le motif : une image refusée rend la photo plutôt que
			// d'arrêter le retrait avant les mails
			try {
				UserAvatarLib::recompose($e['user'], $image, $eUserAvatar['user']['vignette']);
			} catch(\FailAction) {
				UserAvatarLib::recompose($e['user'], NULL, $eUserAvatar['user']['vignette']);
			}

			if($image !== NULL) {
				unlink($image);
			}

		}

		foreach(self::getMails($e) as ['to' => $to, 'content' => $content, 'replyTo' => $replyTo]) {

			\main\MailLib::getInstance()
				->setTo($to)
				->setReplyTo($replyTo)
				->setContent(...$content)
				->send();

		}

		return TRUE;

	}

	/**
	 * L'e-mail d'un retrait, destinataire et contenu, sans rien envoyer : ce que les tests lisent est exactement ce qui
	 * part. Rien quand l'auteur n'a plus d'adresse, un compte fermé la perdant (`DropLib::closeNow()`). La réponse va à
	 * l'adresse de contestation, quand elle est posée (`UserSetting::$avatarUploadContest`).
	 *
	 * @return array<array{to: string, content: array, replyTo: ?string}>
	 */
	public static function getMails(UserAvatarUpload $e): array {

		$e->expects(['user' => ['email'], 'createdAt', 'reason', 'reasonText']);

		if($e['user']['email'] === NULL) {
			return [];
		}

		return [[
			'to' => $e['user']['email'],
			'content' => new UserAvatarUploadUi()->getWithdrawnMail($e, UserSetting::$avatarUploadContest),
			'replyTo' => UserSetting::$avatarUploadContest
		]];

	}

	/**
	 * L'alerte du salon de l'équipe, à chaque modèle accepté, en production seulement : le salon est ouvert à la dev
	 * (`\communication\DiscordWebhookLib::send()`), où chaque essai d'import alerterait l'équipe. Un Discord injoignable lève
	 * (`\util\CurlLib::exec()`) : l'erreur s'enregistre, et l'envoi, déjà validé, reste un succès pour son auteur.
	 */
	public static function notify(UserAvatarUpload $e): bool {

		if(LIME_ENV !== 'prod') {
			return FALSE;
		}

		try {
			return \communication\DiscordWebhookLib::send(self::getMessage($e, self::countWaiting()));
		} catch(\Exception $exception) {
			\dev\ErrorPhpLib::handleException($exception);
			return FALSE;
		}

	}

	/**
	 * Le message est écrit pour être lu dans Discord par l'équipe, en dehors de toute traduction. Il ne nomme ni l'auteur
	 * ni son adresse et ne montre pas le modèle, pour les raisons de `LoginAttemptAiLib::getMessage()` : un salon Discord
	 * ne purge rien, n'est déclaré destinataire nulle part et vit hors de l'Union. Le lien ouvre la file derrière
	 * l'authentification, l'envoi en tête.
	 */
	public static function getMessage(UserAvatarUpload $e, int $waiting): string {

		$e->expects(['id']);

		return '🧍 Nouveau modèle 3D d\'avatar, '.$waiting.' à examiner, celui-ci compris. '.\Lime::getUrl().'/user/admin/avatarUpload?id='.$e['id'];

	}

	/**
	 * Les fichiers des envois de l'utilisateur, à effacer au COMMIT de la transaction en cours. Les lignes restent, sans
	 * fichier ni index.
	 */
	public static function withdrawFiles(User $eUser): void {

		$cUserAvatarUpload = UserAvatarUpload::model()
			->select('id', 'hash')
			->whereUser($eUser)
			->whereHash('!=', NULL)
			->option('for-update')
			->getCollection();

		foreach($cUserAvatarUpload as $eUserAvatarUpload) {

			new \media\UserAvatarUploadLib()->deleteByHash($eUserAvatarUpload['hash']);

			$eUserAvatarUpload['hash'] = NULL;
			$eUserAvatarUpload['bakedIndex'] = NULL;

			self::update($eUserAvatarUpload, ['hash', 'bakedIndex']);

		}

	}

	/**
	 * Les envois montrables (`UserAvatarUpload::acceptRead()`) dont le binaire n'a pas encore les normales de la conversion
	 * d'aujourd'hui (`\scene\UploadLib::smooth()`), bornés à `$users` s'il est posé. À part, ceux dont le fichier manque au
	 * disque et ceux dont le binaire ne se reconvertit pas : ce sont des pannes, qu'aucune reprise ne règle, et un seul
	 * binaire cassé ne doit pas arrêter le relevé des autres. Rend `[$cUserAvatarUpload, $cUserAvatarUploadMissing,
	 * $cUserAvatarUploadBroken]`.
	 */
	public static function getUnsmoothed(array $users = []): array {

		$cUserAvatarUploadAll = UserAvatarUpload::model()
			->select('id', 'user', 'hash', 'bakedIndex', 'status')
			->whereUser('IN', $users, if: $users !== [])
			->whereStatus('IN', [UserAvatarUpload::NEW, UserAvatarUpload::KEPT])
			->whereHash('!=', NULL)
			->getCollection();

		$cUserAvatarUpload = new \Collection();
		$cUserAvatarUploadMissing = new \Collection();
		$cUserAvatarUploadBroken = new \Collection();

		foreach($cUserAvatarUploadAll as $eUserAvatarUpload) {

			$buffer = self::readFile($eUserAvatarUpload);

			if($buffer === NULL) {
				$cUserAvatarUploadMissing[$eUserAvatarUpload['id']] = $eUserAvatarUpload;
				continue;
			}

			try {
				$smoothed = \scene\UploadLib::smooth('avatar', $buffer, $eUserAvatarUpload['bakedIndex']);
			} catch(\Exception) {
				$cUserAvatarUploadBroken[$eUserAvatarUpload['id']] = $eUserAvatarUpload;
				continue;
			}

			if($smoothed !== $buffer) {
				$cUserAvatarUpload[$eUserAvatarUpload['id']] = $eUserAvatarUpload;
			}

		}

		return [$cUserAvatarUpload, $cUserAvatarUploadMissing, $cUserAvatarUploadBroken];

	}

	/**
	 * Refait les normales du binaire d'un envoi converti avant `sceneUploadSmooth()`. Le nouveau binaire prend un nouveau
	 * hash et l'ancien quitte le disque au COMMIT ; positions, index et règles ne changent pas. Rend FALSE sans rien écrire
	 * quand le binaire est déjà à jour, ou quand l'envoi a changé entre la lecture et le verrou.
	 *
	 * node tourne hors transaction, pour ne tenir aucun verrou le temps de la conversion : la ligne se relit ensuite sous
	 * verrou, puisqu'un envoi remplacé ou retiré entre-temps n'a plus ce fichier.
	 *
	 * La transaction se valide toujours, même interrompue par une exception, et jamais ne s'annule : un rollBack() emporterait
	 * celle d'un appelant. Ce qui le permet, c'est l'ordre des écritures : le nouveau fichier et son hash s'écrivent avant
	 * que l'ancien média ne soit marqué à effacer, si bien qu'à chaque étape l'envoi cite un fichier présent. Une exception
	 * laisse au pire un média neuf que rien ne cite, ou l'ancien fichier sur le disque, jamais un envoi sans fichier.
	 */
	public static function smooth(UserAvatarUpload $e): bool {

		$e->expects(['id', 'hash', 'bakedIndex']);

		$buffer = self::readFile($e);

		if($buffer === NULL) {
			throw new \Exception('File of UserAvatarUpload #'.$e['id'].' missing');
		}

		$smoothed = \scene\UploadLib::smooth('avatar', $buffer, $e['bakedIndex']);

		if($smoothed === $buffer) {
			return FALSE;
		}

		UserAvatarUpload::model()->beginTransaction();

		try {

			$eUserAvatarUploadLocked = UserAvatarUpload::model()
				->select('id', 'hash', 'status')
				->whereId($e)
				->option('for-update')
				->get();

			if(
				$eUserAvatarUploadLocked->empty() or
				$eUserAvatarUploadLocked['hash'] !== $e['hash'] or
				$eUserAvatarUploadLocked->acceptRead() === FALSE
			) {
				return FALSE;
			}

			$hash = NULL;
			new \media\UserAvatarUploadLib()->send($eUserAvatarUploadLocked, $hash, $smoothed, 'bin');

			new \media\UserAvatarUploadLib()->deleteByHash($e['hash']);

		} finally {
			UserAvatarUpload::model()->commit();
		}

		\media\MediaLib::deleteFiles();

		$e['hash'] = $eUserAvatarUploadLocked['hash'];

		return TRUE;

	}

	// Le binaire converti d'un envoi, NULL si son fichier manque au disque
	private static function readFile(UserAvatarUpload $e): ?string {

		$path = \storage\DriverLib::directory().'/'.new \media\UserAvatarUploadUi()->getBasenameByHash($e['hash']);

		return is_file($path) ? file_get_contents($path) : NULL;

	}

}
?>
