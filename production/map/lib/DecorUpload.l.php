<?php
namespace map;

/**
 * Le modèle 3D qu'une structure téléverse pour en faire un bâtiment neuf de sa rangée, ou un élément neuf de ses rangées Eau,
 * Nature et Matériel : un envoi porte `building` ou `object`, l'un ou l'autre, posé ici et jamais construit.
 * `DecorBuilding.upload` et `DecorObject.upload` désignent l'envoi montré, et un bâtiment qui en a un ne se modifie pas
 * (`DecorBuilding::acceptUpdate()`) : on le supprime et on en importe un autre. L'envoi et son fichier partent avec ce qui
 * le montre (`deleteByBuilding()`, `deleteByObject()`).
 */
class DecorUploadLib extends DecorUploadCrud {

	// Le profil des règles de conversion (`base/scene/upload/decor.json`) et la famille d'un bâtiment dans ce profil
	const string PROFILE = 'decor';
	const string FAMILY_BUILDING = 'building';

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['commitment', 'shared', 'file'];
	}

	/**
	 * Ce que l'éditeur d'un bâtiment neuf montre de l'envoi (`DecorUploadUi::getTab()`) : le drapeau, le poids que le dépôt
	 * annonce et juge avant l'envoi, et les triangles qu'il annonce.
	 */
	public static function fillEditor(DecorBuilding $eDecorBuilding): void {

		$rules = \scene\UploadLib::getModelRules(self::PROFILE, self::FAMILY_BUILDING);

		$eDecorBuilding['uploadEnabled'] = MapSetting::isDecorUploadEnabled($eDecorBuilding['structure']);
		$eDecorBuilding['uploadRules'] = [
			'maxBytes' => $rules['maxBytes'],
			'maxTriangles' => $rules['maxTriangles'],
		];

	}

	/**
	 * Le modèle que la scène monte à la place de la composition, null s'il n'y en a pas ou si la route du fichier le refuserait
	 * (`decorUpload:model`) : la scène ne demande pas un binaire qu'on ne lui servirait pas. L'adresse est celle de la route, jamais celle
	 * du stockage, publique pour qui connait le hash.
	 */
	public static function getScene(DecorUpload $e): ?array {

		if($e->empty() or $e->assertRead() === FALSE) {
			return NULL;
		}

		return [
			'url' => DecorUploadUi::url($e),
			'index' => $e['bakedIndex']
		];

	}

	/**
	 * Le bâtiment neuf, construit par l'appelant, se crée dans la même transaction que son envoi par
	 * `DecorBuildingLib::create()`, sous la composition qu'il garde sans la montrer : un refus de sa création
	 * (`structure.limit`, `decorChanged`) n'écrit aucun envoi.
	 */
	public static function create(DecorUpload $e): void {

		$e->expects(['structure', 'building', 'shared', 'rules', 'bakedIndex', 'bakedBuffer']);

		if($e['building']->exists()) {
			throw new \Exception('An upload only creates a new building');
		}

		$e['object'] = new DecorObject();
		$e['sharedAt'] = $e['shared'] ? \Clock::datetime() : NULL;

		DecorUpload::model()->beginTransaction();

			DecorBuildingLib::create($e['building']);

			if($e['building']->exists() === FALSE) {
				DecorUpload::model()->commit();
				return;
			}

			$e['hash'] = NULL;

			parent::create($e);

			$hash = NULL;
			new \media\MapDecorUploadLib()->send($e, $hash, $e['bakedBuffer'], 'bin');

			$e['building']['upload'] = $e;
			DecorBuildingLib::update($e['building'], ['upload']);

		DecorUpload::model()->commit();

	}

	/**
	 * L'élément neuf, construit par l'appelant, se crée dans la même transaction que son envoi par `DecorObjectLib::create()` :
	 * un refus de sa création (`structure.limit`) n'écrit aucun envoi.
	 */
	public static function createByObject(DecorUpload $e): void {

		$e->expects(['structure', 'object', 'shared', 'rules', 'bakedIndex', 'bakedBuffer']);

		if($e['object']->exists()) {
			throw new \Exception('An upload only creates a new object');
		}

		$e['building'] = new DecorBuilding();
		$e['sharedAt'] = $e['shared'] ? \Clock::datetime() : NULL;

		DecorUpload::model()->beginTransaction();

			DecorObjectLib::create($e['object']);

			if($e['object']->exists() === FALSE) {
				DecorUpload::model()->commit();
				return;
			}

			$e['hash'] = NULL;

			parent::create($e);

			$hash = NULL;
			new \media\MapDecorUploadLib()->send($e, $hash, $e['bakedBuffer'], 'bin');

			$e['object']['upload'] = $e;
			DecorObjectLib::update($e['object'], ['upload']);

		DecorUpload::model()->commit();

	}

	/**
	 * Les envois d'un bâtiment qui quitte la rangée, lignes comprises : un envoi qui n'a plus rien à montrer n'a plus rien
	 * à modérer. Dans la transaction de `DecorBuildingLib::delete()`, qui a verrouillé le bâtiment puis supprimé ses poses ;
	 * les fichiers quittent le disque à son COMMIT.
	 */
	public static function deleteByBuilding(DecorBuilding $eDecorBuilding): void {

		self::deleteCollection(
			DecorUpload::model()
				->select('id', 'hash')
				->whereBuilding($eDecorBuilding)
				->option('for-update')
				->getCollection()
		);

	}

	/**
	 * Les envois d'un élément qui quitte sa rangée, lignes comprises, dans la transaction de `DecorObjectLib::delete()`, qui a
	 * verrouillé l'élément puis supprimé ses poses ; les fichiers quittent le disque à son COMMIT.
	 */
	public static function deleteByObject(DecorObject $eDecorObject): void {

		self::deleteCollection(
			DecorUpload::model()
				->select('id', 'hash')
				->whereObject($eDecorObject)
				->option('for-update')
				->getCollection()
		);

	}

	private static function deleteCollection(\Collection $cDecorUpload): void {

		foreach($cDecorUpload as $eDecorUpload) {

			if($eDecorUpload['hash'] !== NULL) {
				new \media\MapDecorUploadLib()->deleteByHash($eDecorUpload['hash']);
			}

			self::delete($eDecorUpload);

		}

	}


	public static function getPropertiesWithdraw(): array {
		return ['reason', 'reasonText'];
	}

	/**
	 * Ce que les listes de l'administration lisent d'un envoi : tout sauf le hash, qui ouvre le fichier à qui le connaît par
	 * l'adresse publique du stockage et ne paraît dans aucun rendu. Le binaire se lit par sa route (`DecorUploadUi::url()`).
	 */
	private static function getAdminSelection(): array {

		return [
			'id', 'bakedIndex', 'rules', 'sharedAt', 'chosenAt', 'status', 'reason', 'reasonText', 'moderatedAt', 'createdAt',
			'structure' => ['name'],
			'building' => ['name'],
			'object' => ['name'],
			'createdBy' => \user\User::getSelection(),
			'moderatedBy' => ['firstName', 'lastName'],
			'chosenBy' => ['firstName', 'lastName']
		];

	}

	/**
	 * Les premiers modèles à examiner et leur nombre, le plus ancien en tête, et `$first` avant tous, l'envoi que nomme le
	 * lien de l'alerte Discord (`getMessage()`). Un envoi qui n'a plus de fichier n'a plus rien à montrer ni à juger : il
	 * n'est pas dans la file. `$shared` ne garde que les modèles dont l'auteur a accepté le partage.
	 */
	public static function getWaiting(int $first, bool $shared): array {

		$cDecorUpload = DecorUpload::model()
			->select(self::getAdminSelection())
			->whereStatus(DecorUpload::NEW)
			->whereHash('!=', NULL)
			->whereSharedAt('!=', NULL, if: $shared)
			->sort(new \Sql('m1.id = '.$first.' DESC, m1.id ASC'))
			->option('count')
			->getCollection(0, MapSetting::MAX_BY_PAGE);

		return [$cDecorUpload, DecorUpload::model()->found()];

	}

	public static function countWaiting(): int {

		return DecorUpload::model()
			->whereStatus(DecorUpload::NEW)
			->whereHash('!=', NULL)
			->count();

	}

	/**
	 * Les modèles déjà examinés, du dernier geste au premier, la page demandée et leur nombre : les retirés, et les gardés
	 * qui se montrent encore. Une sélection compte pour un geste, sans réécrire la date du premier examen, et un retrait
	 * après la sélection pour le suivant, d'où le plus récent des deux.
	 */
	public static function getExamined(int $page, bool $shared): array {

		$cDecorUpload = DecorUpload::model()
			->select(self::getAdminSelection())
			->or(
				fn() => $this->whereStatus(DecorUpload::WITHDRAWN),
				fn() => $this->whereStatus(DecorUpload::KEPT)->whereHash('!=', NULL)
			)
			->whereSharedAt('!=', NULL, if: $shared)
			->sort(new \Sql('GREATEST(m1.moderatedAt, COALESCE(m1.chosenAt, m1.moderatedAt)) DESC, m1.id DESC'))
			->option('count')
			->getCollection($page * MapSetting::MAX_BY_PAGE, MapSetting::MAX_BY_PAGE);

		return [$cDecorUpload, DecorUpload::model()->found()];

	}

	/**
	 * L'état dans la condition de l'écriture : un modèle retiré ou effacé entre l'affichage et le clic ne se garde pas. Rend
	 * le nombre de lignes écrites, 0 quand l'envoi n'était plus à examiner.
	 */
	public static function keep(DecorUpload $e): int {

		$e['status'] = DecorUpload::KEPT;
		$e['moderatedAt'] = \Clock::datetime();
		$e['moderatedBy'] = \user\ConnectionLib::getOnline();

		return DecorUpload::model()
			->select(['status', 'moderatedAt', 'moderatedBy'])
			->whereStatus(DecorUpload::NEW)
			->whereHash('!=', NULL)
			->update($e);

	}

	/**
	 * Les modèles de la file que l'admin avait sous les yeux, gardés d'un geste : seuls les ids postés par la page comptent,
	 * de sorte qu'un envoi arrivé après l'affichage attend d'avoir été vu, et un envoi qui a quitté la file depuis est
	 * ignoré, puisque `keep()` porte l'état dans la condition de son écriture. Rend le nombre de modèles gardés, 0 aussi
	 * quand la liste passe la taille de la file affichée, qu'aucun formulaire de la page ne peut poster.
	 */
	public static function keepAll(array $ids): int {

		if($ids === [] or count($ids) > MapSetting::MAX_BY_PAGE) {
			return 0;
		}

		$cDecorUpload = DecorUpload::model()
			->select('id')
			->whereId('IN', $ids)
			->whereStatus(DecorUpload::NEW)
			->whereHash('!=', NULL)
			->getCollection();

		$kept = 0;

		foreach($cDecorUpload as $eDecorUpload) {
			$kept += self::keep($eDecorUpload);
		}

		return $kept;

	}

	/**
	 * Le modèle quitte le disque une fois le retrait validé, et ce qui le montrait ne le cite plus (`upload`) : un bâtiment reprend
	 * sa composition et redevient modifiable (`DecorBuilding::acceptUpdate()`), un élément se dessine sous sa forme neutre.
	 * L'auteur reçoit le motif. Rend FALSE sans rien écrire quand l'envoi ne se montrait plus au moment du verrou.
	 *
	 * Les verrous se prennent dans l'ordre de `DecorBuildingLib::delete()` et `DecorObjectLib::delete()`, ce qui montre le
	 * modèle puis ses envois : un retrait qui croise la suppression du bâtiment attend la fin de l'autre transaction, puis
	 * ne trouve plus l'envoi. L'e-mail part après le COMMIT : envoyé avant, il annoncerait un retrait qu'une panne aurait
	 * défait.
	 */
	public static function withdraw(DecorUpload $e): bool {

		$e->expects(['id', 'structure', 'building', 'object', 'createdBy', 'createdAt', 'reason', 'reasonText']);

		DecorUpload::model()->beginTransaction();

			if($e['building']->notEmpty()) {

				$eDecorBuilding = DecorBuilding::model()
					->select('id', 'structure', 'upload')
					->whereId($e['building'])
					->whereStructure($e['structure'])
					->option('for-update')
					->get();

				$eDecorObject = new DecorObject();

			} else {

				$eDecorObject = DecorObject::model()
					->select('id', 'structure', 'upload')
					->whereId($e['object'])
					->whereStructure($e['structure'])
					->option('for-update')
					->get();

				$eDecorBuilding = new DecorBuilding();

			}

			$eDecorUploadLocked = DecorUpload::model()
				->select('id', 'hash', 'status')
				->whereId($e)
				->option('for-update')
				->get();

			if($eDecorUploadLocked->empty() or $eDecorUploadLocked->acceptWithdraw() === FALSE) {
				// Rien n'est écrit : valider plutôt qu'annuler, un rollBack() emporterait la transaction d'un appelant
				DecorUpload::model()->commit();
				return FALSE;
			}

			new \media\MapDecorUploadLib()->deleteByHash($eDecorUploadLocked['hash']);

			$e['hash'] = NULL;
			$e['bakedIndex'] = NULL;
			$e['status'] = DecorUpload::WITHDRAWN;
			$e['moderatedAt'] = \Clock::datetime();
			$e['moderatedBy'] = \user\ConnectionLib::getOnline();

			self::update($e, ['hash', 'bakedIndex', 'status', 'reason', 'reasonText', 'moderatedAt', 'moderatedBy']);

			// Ce qui montre le modèle ne le cite que s'il désigne cet envoi : un autre envoi qu'il montrerait reste en place
			if($eDecorBuilding->notEmpty() and $eDecorBuilding['upload']->is($e)) {
				$eDecorBuilding['upload'] = new DecorUpload();
				DecorBuildingLib::update($eDecorBuilding, ['upload']);
			}

			if($eDecorObject->notEmpty() and $eDecorObject['upload']->is($e)) {
				$eDecorObject['upload'] = new DecorUpload();
				DecorObjectLib::update($eDecorObject, ['upload']);
			}

		DecorUpload::model()->commit();

		\media\MediaLib::deleteFiles();

		self::sendMails(self::getWithdrawnMails($e));

		return TRUE;

	}

	/**
	 * L'e-mail d'un retrait, destinataire et contenu, sans rien envoyer : ce que les tests lisent est exactement ce qui
	 * part. Rien quand l'auteur n'a plus d'adresse, un compte fermé la perdant. La réponse va à l'adresse de contestation
	 * des modèles 3D, celle des avatars, quand elle est posée.
	 *
	 * @return array<array{to: string, content: array, replyTo: ?string}>
	 */
	public static function getWithdrawnMails(DecorUpload $e): array {

		$e->expects(['createdBy' => ['email'], 'createdAt', 'reason', 'reasonText']);

		if($e['createdBy']['email'] === NULL) {
			return [];
		}

		return [[
			'to' => $e['createdBy']['email'],
			'content' => new DecorUploadUi()->getWithdrawnMail($e, \user\UserSetting::$avatarUploadContest),
			'replyTo' => \user\UserSetting::$avatarUploadContest
		]];

	}

	/**
	 * L'admin sélectionne un modèle partagé pour le proposer aux autres utilisateurs, et son auteur reçoit un remerciement.
	 * Deux écritures : la sélection, dont la condition porte l'état et ne laisse passer qu'un geste, puis `keep()`, qui garde
	 * un modèle encore à examiner et ne touche pas à un modèle déjà gardé, dont le premier examen reste écrit. Le
	 * remerciement ne part que si la sélection s'est écrite : deux clics, deux admins ou un retrait croisé n'en envoient
	 * jamais deux. Rend le nombre de lignes sélectionnées, 0 quand le modèle ne l'était plus.
	 */
	public static function choose(DecorUpload $e): int {

		$e->expects(['id', 'createdBy', 'createdAt', 'building', 'object']);

		$e['chosenAt'] = \Clock::datetime();
		$e['chosenBy'] = \user\ConnectionLib::getOnline();

		// Les deux écritures dans une transaction : la ligne reste verrouillée jusqu'au COMMIT, et un retrait croisé attend
		// pour la trouver sélectionnée et gardée
		DecorUpload::model()->beginTransaction();

			$chosen = DecorUpload::model()
				->select(['chosenAt', 'chosenBy'])
				->whereChosenAt(NULL)
				->whereSharedAt('!=', NULL)
				->whereStatus('IN', [DecorUpload::NEW, DecorUpload::KEPT])
				->whereHash('!=', NULL)
				->update($e);

			// Sur un élément à part : un modèle déjà gardé ne s'écrit pas, et `$e` garderait sinon un statut et une date qui
			// ne sont pas ceux de la base
			if($chosen > 0) {
				self::keep(new DecorUpload(['id' => $e['id']]));
			}

		DecorUpload::model()->commit();

		if($chosen === 0) {
			return 0;
		}

		self::sendMails(self::getChosenMails($e));

		return $chosen;

	}

	/**
	 * Le remerciement d'une sélection, destinataire et contenu, sans rien envoyer. Rien quand l'auteur n'a plus d'adresse :
	 * la sélection s'écrit quand même, et la confirmation l'a dit à l'admin.
	 *
	 * @return array<array{to: string, content: array, replyTo: ?string}>
	 */
	public static function getChosenMails(DecorUpload $e): array {

		$e->expects(['createdBy' => ['email'], 'createdAt']);

		if($e['createdBy']['email'] === NULL) {
			return [];
		}

		return [[
			'to' => $e['createdBy']['email'],
			'content' => new DecorUploadUi()->getChosenMail($e),
			'replyTo' => NULL
		]];

	}

	private static function sendMails(array $mails): void {

		foreach($mails as ['to' => $to, 'content' => $content, 'replyTo' => $replyTo]) {

			\main\MailLib::getInstance()
				->setTo($to)
				->setReplyTo($replyTo)
				->setContent(...$content)
				->send();

		}

	}

	/**
	 * L'alerte du salon de l'équipe, à chaque modèle importé, en production seulement : le salon est ouvert à la dev
	 * (`\communication\DiscordWebhookLib::send()`), où chaque essai d'import alerterait l'équipe. Un Discord injoignable lève
	 * (`\util\CurlLib::exec()`) : l'erreur s'enregistre, et l'envoi, déjà validé, reste un succès pour son auteur.
	 */
	public static function notify(DecorUpload $e): bool {

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
	 * Le message est écrit pour être lu dans Discord par l'équipe, en dehors de toute traduction. Il ne nomme ni l'auteur,
	 * ni la structure, ni le bâtiment, et ne montre pas le modèle, pour les raisons de `\user\UserAvatarUploadLib::getMessage()`.
	 * Le lien ouvre la file derrière l'authentification, l'envoi en tête.
	 */
	public static function getMessage(DecorUpload $e, int $waiting): string {

		$e->expects(['id']);

		return '🏠 Nouveau modèle 3D de décor, '.$waiting.' à examiner, celui-ci compris. '.\Lime::getUrl().'/map/admin/decorUpload?id='.$e['id'];

	}

}
?>
