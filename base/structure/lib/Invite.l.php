<?php
namespace structure;

class InviteLib extends InviteCrud {

	use \Notifiable;

	public static function getPropertiesCreate(?string $origin = NULL): \Closure {

		// Le sous-groupe n'est pas exposé à l'API : c'est une notion du produit INRAE, derrière un
		// drapeau, et l'endpoint des invitations garde une surface étroite — il n'y donne pas
		// davantage les droits d'administrateur. L'écarter ici plutôt que de rendre facultatif le
		// contrôle qui le borne : memberSection.check a besoin de la liste des sous-groupes
		// ouverts, que seul l'écran prépare.
		$properties = ($origin === 'api')
			? ['memberRole', 'email', 'limit', 'expiresAt']
			// `member` avant `email`, dont l'étape `required` le lit. Une personne sans compte se désigne
			// depuis sa ligne d'équipe seule, comme le sous-groupe : l'API ne la propose pas.
			: ['memberRole', 'memberSection', 'member', 'email', 'limit', 'expiresAt'];

		return function(Invite $eInvite) use ($properties) {

			return match($eInvite['type']) {
				Invite::MEMBER => $properties,
				Invite::CUSTOMER => ['email', 'limit'],
				Invite::PRODUCER => ['email', 'limit', 'expiresAt']
			};

		};

	}

	/**
	 * Le lien d'invitation d'un groupe entier : une invitation sans adresse, sans limite et sans
	 * sous-groupe, qui vaut pour qui l'ouvre et autant de fois qu'on veut.
	 */
	public static function getLinkByStructure(Structure $eStructure): Invite {

		return Invite::model()
			->select(['id', 'key'])
			->whereStructure($eStructure)
			->whereType(Invite::MEMBER)
			->whereStatus(Invite::PENDING)
			->whereEmail(NULL)
			->whereLimit(NULL)
			->whereMemberSection(NULL)
			->get();

	}

	public static function createLinkByStructure(Structure $eStructure): Invite {

		$roles = $eStructure->getAudienceRoles();

		if(array_key_exists('member', $roles) === FALSE) {
			throw new \NotExpectedAction('Ce type de groupe ne propose pas de lien');
		}

		$cRole = RoleLib::getByStructure($eStructure);
		$eRole = $cRole->find(fn(Role $eRole) => $eRole['fqn'] === $roles['member'])->first() ?? new Role();

		if($eRole->empty()) {
			throw new \NotExpectedAction('Rôle '.$roles['member'].' absent de ce groupe');
		}

		Invite::model()->beginTransaction();

		Structure::model()
			->whereId($eStructure)
			->getForUpdate();

		$eInvite = self::getLinkByStructure($eStructure);

		if($eInvite->empty()) {

			$fw = new \FailWatch();

			$eInvite = new Invite([
				'structure' => $eStructure,
				'type' => Invite::MEMBER,
				'cRole' => $cRole,
				'cSection' => SectionLib::getByStructure($eStructure, onlyOpen: TRUE)
			]);

			$eInvite->build(['memberRole', 'memberSection', 'email'], [
				'memberRole' => $eRole['id'],
				'memberSection' => '',
				'email' => ''
			]);

			if($fw->ok()) {
				self::create($eInvite);
			}

		}

		Invite::model()->commit();

		return $eInvite;

	}

	public static function countMembersByRoleFqn(Structure $eStructure, string $fqn): int {

		return Invite::model()
			->join(Role::model(), 'm1.memberRole = m2.id')
			->where('m1.structure', $eStructure)
			->where('m1.type', Invite::MEMBER)
			->where('m1.status', Invite::PENDING)
			->or(
				fn() => $this->where('m1.limit', NULL),
				fn() => $this->where('m1.limit', '>', new \Sql('m1.used')),
			)
			->where('m2.fqn', $fqn)
			->count();

	}

	/**
	 * Les invitations encore valables qui attendent ce compte. Une invitation par adresse se retrouve
	 * par celle du compte ; une invitation par lien n'en porte aucune, et seule sa clé, que la personne
	 * a eue en main, la retrouve.
	 */
	public static function getValidByUser(\user\User $eUser, ?string $key = NULL): \Collection {

		$cInvite = Invite::model()
			->select(Invite::getSelection())
			->whereEmail($eUser['email'], if: $key === NULL)
			->or(
				fn() => $this->whereEmail($eUser['email']),
				fn() => $this->whereKey($key)->whereEmail(NULL),
				if: $key !== NULL
			)
			->whereStatus(Invite::PENDING)
			->or(
				fn() => $this->whereExpiresAt(NULL),
				fn() => $this->whereExpiresAt('>=', new \Sql('CURDATE()')),
			)
			->or(
				fn() => $this->whereLimit(NULL),
				fn() => $this->whereLimit('>', new \Sql(Invite::model()->field('used'))),
			)
			->getCollection();

		self::fillShop($cInvite);

		return $cInvite;

	}

	public static function getMembersByStructure(Structure $eStructure): \Collection {

		return Invite::model()
			->select(Invite::getSelection())
			->whereType(Invite::MEMBER)
			->whereStatus(Invite::PENDING)
			->whereStructure($eStructure)
			->sort([
				'createdAt' => SORT_ASC
			])
			->getCollection();

	}

	/**
	 * L'invitation en cours à l'adresse du compte d'une personne à qui l'on a retiré tout rôle : elle ne désigne
	 * pas son adhésion gardée, que l'acceptation retrouve seule (`acceptMember()`).
	 */
	public static function getPendingByEmail(Structure $eStructure, string $email): Invite {

		return Invite::model()
			->select(Invite::getSelection())
			->whereType(Invite::MEMBER)
			->whereStructure($eStructure)
			->whereEmail($email)
			->whereStatus(Invite::PENDING)
			->or(
				fn() => $this->whereExpiresAt(NULL),
				fn() => $this->whereExpiresAt('>=', \Clock::date())
			)
			->get();

	}

	/**
	 * Les invitations en cours, puis les terminées. Une invitation épuisée reste lisible jusqu'à son
	 * nettoyage (`clean()`), sous son propre intitulé : sous « Les invitations en cours », elle ferait
	 * mentir le titre.
	 */
	public static function splitFinished(\Collection $cInvite): array {

		return [
			$cInvite->find(fn(Invite $eInvite) => $eInvite->acceptLimit()),
			$cInvite->find(fn(Invite $eInvite) => $eInvite->acceptLimit() === FALSE)
		];

	}

	public static function countAdminsByStructure(Structure $eStructure): int {

		return Invite::model()
			->join(Role::model(), 'm1.memberRole = m2.id')
			->where('m1.structure', $eStructure)
			->where('m1.type', Invite::MEMBER)
			->where('m1.status', Invite::PENDING)
			->or(
				fn() => $this->where('m1.limit', NULL),
				fn() => $this->where('m1.limit', '>', new \Sql('m1.used')),
			)
			->where('m2.admin', TRUE)
			->count();

	}

	public static function getByKey(string $key, bool $onlyPending = TRUE): Invite {

		$eInvite = Invite::model()
			->select(Invite::getSelection())
			->whereKey($key)
			->whereStatus(Invite::PENDING, if: $onlyPending)
			->get();

		self::fillShop(new \Collection([$eInvite]));

		return $eInvite;

	}

	/**
	 * La boutique où une invitation de producteur fait entrer, que ses écrans et ses e-mails nomment :
	 * elle vit dans la commercialisation (`eShop`).
	 */
	public static function fillShop(\Collection $cInvite): void {

		if(
			StructureSetting::hasCommercialisationApp() and
			$cInvite->contains(fn(Invite $eInvite) => $eInvite->notEmpty() and $eInvite['type'] === Invite::PRODUCER)
		) {
			self::notify('fillInviteShop', $cInvite);
		}

	}

	/**
	 * La structure que l'adresse de l'accueil désigne participe-t-elle à la boutique de l'invitation.
	 */
	public static function matchProducer(Invite $eInvite, Structure $eStructureProducer): bool {

		if(StructureSetting::hasCommercialisationApp() === FALSE) {
			return FALSE;
		}

		return self::notify('matchInviteProducer', $eInvite, $eStructureProducer)[0];

	}

	/**
	 * L'invitation dont la clé a été collée telle qu'elle a été reçue : le lien entier, qui la porte
	 * dans `?key=`, ou la clé seule.
	 */
	public static function getByKeyPasted(string $pasted): Invite {

		$key = (preg_match('/[?&]key=([^&#]+)/', $pasted, $match) === 1) ? urldecode($match[1]) : $pasted;

		return self::getByKey($key);

	}

	public static function create(Invite $e): void {

		$e->expects([
			'type',
			'structure' => ['name', 'type']
		]);

		$e->add([
			'sendMail' => TRUE
		]);

		Invite::model()->beginTransaction();

		// La fiche d'un client invité vit dans la commercialisation : c'est elle qui dit si la fiche
		// s'invite encore, et qui efface les invitations qu'elle avait
		if(
			$e['type'] === Invite::CUSTOMER and
			(
				StructureSetting::hasCommercialisationApp() === FALSE or
				self::notify('prepareInviteCustomer', $e)[0] === FALSE
			)
		) {
			Invite::model()->commit();
			return;
		}

		try {

			Invite::model()->insert($e);

			match($e['type']) {
				Invite::CUSTOMER => self::notify('createInviteCustomer', $e),
				Invite::PRODUCER => self::notify('createInviteProducer', $e),
				Invite::MEMBER => NULL
			};

			if(
				$e['email'] !== NULL and
				$e['sendMail']
			) {
				self::send($e);
			}

		}
		catch(\DuplicateException) {

			Invite::fail('duplicateKey');

		}

		Invite::model()->commit();



	}
	
	public static function validateJoin(Invite $eInvite, ?string $wrapper = NULL): void {

		if(
			$eInvite->empty() or
			$eInvite['status'] !== Invite::PENDING
		) {
			Invite::fail('invalidKey', wrapper: $wrapper);
			return;
		}

		if($eInvite->acceptExpired() === FALSE) {
			Invite::fail('invalidExpired', wrapper: $wrapper);
			return;
		}

		if($eInvite->acceptAmapActive() === FALSE) {
			Invite::fail('amapClosed', wrapper: $wrapper);
			return;
		}

		if(
			$eInvite->acceptLimit() === FALSE
		) {
			Invite::fail('invalidLimit', wrapper: $wrapper);
		}

		$eUserOnline = \user\ConnectionLib::getOnline();

		if($eInvite->acceptEmail($eUserOnline['email']) === FALSE) {
			Invite::fail('invalidEmail', wrapper: $wrapper);
		}

		// Une invitation Producteur rattache une structure et non une personne : être déjà membre du
		// collectif n'est pas une raison de la refuser, un producteur pouvant en livrer plusieurs.
		if(
			$eInvite['type'] === Invite::MEMBER and
			$eInvite->isAmapProducer() === FALSE and
			$eInvite['structure']->isMember()
		) {
			Invite::fail('alreadyJoined', [$eInvite['structure']], $wrapper);
		}
		
	}

	public static function resend(Invite $e): void {

		$e['createdAt'] = new \Sql('NOW()');

		Invite::model()
			->select(['createdAt'])
			->update($e);

		self::send($e);

	}

	public static function send(Invite $e): void {

		// Relue en base : `build()` ne pose d'une propriété déléguée que l'identifiant posté, et le
		// message d'un producteur d'AMAP se choisit sur le rôle entier.
		$eInvite = Invite::model()
			->select(Invite::getSelection())
			->whereId($e['id'])
			->get();

		self::fillShop(new \Collection([$eInvite]));

		$content = InviteUi::getInviteMail($eInvite);

		\main\MailLib::getInstance()
			->setStructure($eInvite['structure'])
			->setTo($eInvite['email'])
			->setContent(...$content)
			->send();

	}

	/**
	 * Quelles fermes à partager n'ont pas de lieu, et le système sur lequel le formulaire d'entrée le
	 * demande : l'écran et `preparePlace()` lisent la même réponse.
	 */
	public static function fillPlaced(Invite $eInvite): void {

		$eInvite['eSystemPlace'] = new System();

		foreach($eInvite['cStructureShareable'] as $eStructureShareable) {

			$eStructureShareable['isPlaced'] = ($eStructureShareable['eSystem']['placeLngLat'] !== NULL);

			if($eStructureShareable['isPlaced'] === FALSE and $eInvite['eSystemPlace']->empty()) {
				$eInvite['eSystemPlace'] = $eStructureShareable['eSystem'];
			}

		}

	}

	public static function preparePlace(Structure $eStructureProducer, array $input): System {

		// ⚠️ Le champ du lieu est posté même masqué, quand la ferme cochée est déjà située :
		// c'est le lieu en base qui décide, jamais la présence du champ.
		if(
			$eStructureProducer['isPlaced'] or
			($input['place'] ?? '') === ''
		) {
			return new System();
		}

		$eSystem = $eStructureProducer['eSystem'];
		$eSystem->build(['place', 'placeLngLat'], $input);

		return $eSystem;

	}

	/**
	 * La structure qu'un producteur invité dans une AMAP rattache, lue dans le formulaire d'entrée
	 * par l'AMAP, qui refuse le formulaire s'il est invalide.
	 */
	public static function buildInviteAmapProducer(Invite $eInvite, array $input): Structure {
		return self::notify('buildInviteAmapProducer', $eInvite, $input)[0];
	}

	/**
	 * Ce que l'accueil qui suit l'acceptation montre de l'invitation : `$eStructureProducer` vient de
	 * l'adresse, et seul ce qui le relie à l'invitation est retenu.
	 */
	/**
	 * Un compte qui a déjà sa fiche client dans la structure n'en reçoit pas une seconde par invitation.
	 */
	public static function existsCustomerUser(Structure $eStructure, \user\User $eUser): bool {

		if(StructureSetting::hasCommercialisationApp() === FALSE) {
			return FALSE;
		}

		return self::notify('existsInviteCustomerUser', $eStructure, $eUser)[0];

	}

	/**
	 * L'inscription par une invitation de client prend le type de la fiche qu'elle rattache.
	 */
	public static function prepareSignUp(Invite $eInvite, \user\User $eUser): void {

		if(StructureSetting::hasCommercialisationApp()) {
			self::notify('prepareInviteSignUp', $eInvite, $eUser);
		}

	}

	public static function prepareWelcome(Invite $eInvite, Structure $eStructureProducer, \user\User $eUser): void {

		if(StructureSetting::hasCommercialisationApp()) {
			self::notify('prepareInviteWelcome', $eInvite, $eStructureProducer, $eUser);
		}

	}

	public static function prepareTos(Invite $eInvite, \user\User $eUser): void {

		if($eInvite->empty()) {
			return;
		}

		$eInvite['tosRequired'] = (
			$eInvite['type'] === Invite::MEMBER and
			$eUser->notEmpty() and
			\user\TosAiLib::hasAccepted($eUser) === FALSE
		);

	}

	public static function prepareShare(Invite $eInvite): void {

		if($eInvite->empty()) {
			return;
		}

		$eInvite['cStructureShareable'] = new \Collection();
		$eInvite['eCommunity'] = new Community();
		$eInvite['members'] = 0;
		$eInvite['hasProducer'] = FALSE;
		$eInvite['eSystemPlace'] = new System();
		$eInvite['eUser'] = new \user\User();
		$eInvite['cStructureJoinable'] = new \Collection();
		$eInvite['audienceBringing'] = Community::MEMBER;

		// Le rattachement d'un producteur invité dans une AMAP se prépare chez elle
		if(StructureSetting::hasCommercialisationApp()) {
			self::notify('prepareInviteShare', $eInvite);
		}

		if($eInvite['type'] === Invite::PRODUCER) {

			$eUser = \user\ConnectionLib::getOnline();
			$eInvite['eUser'] = $eUser;

			if($eUser->empty()) {
				return;
			}

			$eInvite['cStructureJoinable'] = self::notify('getInviteProducerJoinable', $eInvite, $eUser)[0];
			$eInvite['hasProducer'] = StructureLib::hasProducerByUser($eUser);

			return;

		}

		if($eInvite['type'] !== Invite::MEMBER) {
			return;
		}

		$eStructure = $eInvite['structure'];

		$eUser = \user\ConnectionLib::getOnline();

		if($eUser->empty()) {
			return;
		}

		if($eInvite->isAmapProducer()) {
			return;
		}

		if($eStructure->acceptGroupStructure() === FALSE) {
			return;
		}

		$eInvite['cStructureShareable'] = StructureLib::getShareableByUser($eUser, $eStructure);

		$cSystem = SystemLib::getByStructures($eInvite['cStructureShareable']);

		foreach($eInvite['cStructureShareable'] as $eStructureShareable) {
			$eStructureShareable['eSystem'] = $cSystem[$eStructureShareable['id']];
		}

		self::fillPlaced($eInvite);

		$eInvite['hasProducer'] = StructureLib::hasProducerByUser($eUser);
		$eInvite['eCommunity'] = CommunityLib::getByStructure($eStructure);

		if($eInvite['memberRole']->notEmpty()) {
			$eInvite['audienceBringing'] = CommunityRightAiLib::getAudience(RoleLib::getById($eInvite['memberRole']['id'], ['fqn']));
		}

		$eInvite['members'] = MemberLib::countByStructure($eStructure);

	}

	public static function getNotifyEmails(Invite $e, \user\User $eUserJoining): array {

		$emails = [];

		foreach(MemberLib::getAdminsByStructure($e['structure']) as $eMember) {

			if(
				$eMember['user']->is($eUserJoining) === FALSE and
				$eMember['user']['email'] !== NULL
			) {
				$emails[] = $eMember['user']['email'];
			}

		}

		return $emails;

	}

	public static function accept(Invite $e, \user\User $eUser, Structure $eStructureProducer = new Structure(), ?array $rights = NULL): bool {

		$e->expects([
			'type', 'status', 'member',
			'structure' => ['name', 'type']
		]);

		if($eUser->empty()) {
			return FALSE;
		}

		$eUser->expects(['email']);

		// Refusé avant d'ouvrir, rien n'étant écrit : rollBack() viderait toute la pile, transaction de l'appelant comprise
		if($eStructureProducer->empty()) {

			if($e['type'] === Invite::PRODUCER) {
				Invite::fail('structureProducer.check');
				return FALSE;
			}

			if(
				$e['type'] === Invite::MEMBER and
				$e->isAmapProducer()
			) {
				self::notify('failInviteAmapProducer', $e);
				return FALSE;
			}

		}

		// Ici et non dans les seules pages : l'inscription accepte l'invitation sans passer par `validateJoin()`
		if($e->acceptAmapActive() === FALSE) {
			Invite::fail('amapClosed');
			return FALSE;
		}

		Invite::model()->beginTransaction();

		$affected = Invite::model()
			->whereId($e['id'])
			->whereStatus(Invite::PENDING)
			->or(
				fn() => $this->whereEmail(NULL),
				fn() => $this->whereEmail($eUser['email']),
			)
			->or(
				fn() => $this->whereExpiresAt(NULL),
				fn() => $this->whereExpiresAt('>=', new \Sql('CURDATE()')),
			)
			->or(
				fn() => $this->whereLimit(NULL),
				fn() => $this->whereLimit('>', new \Sql(Invite::model()->field('used'))),
			)
			->update([
				'used' => new \Sql(Invite::model()->field('used').' + 1'),
				'lastUsedAt' => new \Sql('CURRENT_DATE')
			]);

		if($affected) {

			$fw = new \FailWatch();

			match($e['type']) {
				Invite::CUSTOMER => self::notify('acceptInviteCustomer', $e, $eUser),
				Invite::MEMBER => self::acceptMember($e, $eUser, $eStructureProducer, $rights),
				Invite::PRODUCER => self::notify('acceptInviteProducer', $e, $eStructureProducer)
			};

			if($fw->ok()) {

				switch($e['type']) {

					case Invite::CUSTOMER :
						$content = InviteUi::getAcceptCustomerMail($e, $eUser);

						\main\MailLib::getInstance()
							->setTo($e['email'])
							->setContent(...$content)
							->send();
						break;

					case Invite::MEMBER :
						if($e['notifyEmail']) {

							$content = InviteUi::getNewMemberMail($e, $eUser);

							foreach(self::getNotifyEmails($e, $eUser) as $email) {

								\main\MailLib::getInstance()
									->setTo($email)
									->setContent(...$content)
									->send();

							}

						}
						break;

					case Invite::PRODUCER :
						if($e['notifyEmail']) {

							$content = InviteUi::getNewProducerMail($e, $eStructureProducer);

							foreach(self::getNotifyEmails($e, $eUser) as $email) {

								\main\MailLib::getInstance()
									->setStructure($e['structure'])
									->setTo($email)
									->setContent(...$content)
									->send();

							}

						}
						break;

				}

				if($e['email'] !== NULL) {

					$e['status'] = Invite::INVALID;
					self::update($e, ['status']);
				}

			} else {
				Invite::model()->rollBack();
				return FALSE;
			}

		}

		Invite::model()->commit();

		return ($affected > 0);

	}

	/**
	 * ⚠️ `$rights` traverse jusqu'ici et n'a pas de défaut implicite : ce que la ferme a **décoché**
	 * sur l'écran d'entrée est une décision, et `NULL` n'en est pas une — `getSharesForCouple()`
	 * l'entend comme « prends tout ce que le groupe demande ». La variable manquait à cette
	 * signature tout en étant employée dans le corps : le POST était lu, puis jeté, et chaque ferme
	 * entrait avec le périmètre entier du groupe. Warning en dev, silence en production.
	 */
	protected static function acceptMember(Invite $e, \user\User $eUser, Structure $eStructureProducer = new Structure(), ?array $rights = NULL): void {

		// Une seconde structure ne vaut pas une seconde adhésion : le rattachement s'écrit seul.
		if(
			$e->isAmapProducer() and
			MemberLib::isMember($eUser, $e['structure'])
		) {

			$e['memberCreated'] = FALSE;

			self::notify('createInviteAmapProducer', $e);
			return;

		}

		// Une personne de l'équipe à qui l'on avait retiré tout rôle a gardé son adhésion
		// (`MemberLib::deleteInternal()`) : l'invitation la fait entrer de nouveau, au lieu d'en insérer une
		// seconde que `UNIQUE (user, structure)` refuserait. Elle vaut une adhésion créée, et ce qui suit
		// s'applique à elle comme à une nouvelle.
		$eMember = MemberLib::getRevokedForUpdate($e['structure'], $eUser);

		if($eMember->notEmpty()) {

			$eMember['role'] = $e['memberRole'];
			$eMember['status'] = Member::IN;
			$eMember['inviteKey'] = $e['key'];

			MemberLib::update($eMember, ['role', 'status', 'inviteKey']);

		} else {

			$eMember = new Member([
				'user' => $eUser,
				'structure' => $e['structure'],
				'role' => $e['memberRole'],
				'status' => Member::IN,
				'inviteKey' => $e['key']
			]);

			MemberLib::create($eMember);

			// ⚠️ **Rien ne suit une adhésion qui n'a pas eu lieu.** Sur un double envoi, l'insertion
			// lève `DuplicateException` et `MemberLib::create()` répond par un `rollBack()` — qui remet
			// la pile de transactions à zéro (`Module.php:1910`) et défait donc **celle que `accept()`
			// vient d'ouvrir**. Sans ce retour, tout ce qui suit s'exécuterait en autocommit, hors de
			// la transaction que le `rollBack()` final ne peut plus reprendre : le rattachement de la
			// ferme et le rangement en sous-groupe s'écriraient au nom de quelqu'un qui n'est pas
			// entré, et resteraient.
			if($eMember->exists() === FALSE) {
				return;
			}

		}

		$e['memberCreated'] = TRUE;

		// La personne sans compte que l'invitation désigne devient ce compte : son passé le suit, et elle
		// quitte l'équipe. Un refus passe par `Member::fail()`, que le `$fw` d'`accept()` voit et défait.
		if(
			$e['member']->notEmpty() and
			MemberLib::transferGhost(new Member([
				'id' => $e['member']['id'],
				'structure' => $e['structure']
			]), $eUser) === FALSE
		) {
			return;
		}

		\user\TosAiLib::acceptOnStructure($eUser);

		NotificationLib::memberJoined($eMember);

		// Le sous-groupe se pose à l'invitation et s'applique ici, une personne étant ce qu'un
		// sous-groupe range. Il peut avoir été clos entre-temps : l'invitation reste valable, et
		// clore n'a jamais retiré personne.
		if($e['memberSection']->notEmpty()) {
			SectionLib::associateUser($e['memberSection'], $eUser);
		}

		if($eStructureProducer->notEmpty()) {

			if($e->isAmapProducer()) {
				self::notify('createInviteAmapProducer', $e);
				return;
			}

			AffiliationAiLib::create(new Affiliation([
				'user' => $eUser,
				'structureProducer' => $eStructureProducer,
				'structureCommunity' => $e['structure'],
			]), $rights);

		}

	}

	/**
	 * L'invitation qui peut encore rattacher la personne sans compte. Une invitation expirée reste en
	 * attente jusqu'à son nettoyage (`clean()`), mais ne s'accepte plus : elle n'empêche pas d'en
	 * envoyer une autre.
	 */
	public static function getPendingByMember(Member $eMember): Invite {

		return Invite::model()
			->select(Invite::getSelection())
			->whereStructure($eMember['structure'])
			->whereMember($eMember)
			->whereStatus(Invite::PENDING)
			->or(
				fn() => $this->whereExpiresAt(NULL),
				fn() => $this->whereExpiresAt('>=', \Clock::date())
			)
			->get();

	}

	/**
	 * Les invitations qui désignent une personne sans compte ne servent plus quand elle disparait :
	 * acceptées, elles ne rattacheraient plus rien.
	 */
	public static function invalidateByMember(Member $eMember): void {

		// Écriture de masse : une seule invitation attend en principe, mais toutes changent d'état.
		Invite::model()
			->whereMember($eMember)
			->whereStatus(Invite::PENDING)
			->update([
				'status' => Invite::INVALID
			]);

	}

	public static function deleteByRole(Role $eRole): void {

		$eRole->expects(['id', 'structure']);

		Invite::model()
			->whereStructure($eRole['structure'])
			->whereMemberRole($eRole)
			->delete();

	}

	public static function delete(Invite $e): void {

		Invite::model()->beginTransaction();

			if(StructureSetting::hasCommercialisationApp()) {
				self::notify('deleteInvite', $e);
			}

			parent::delete($e);

		Invite::model()->commit();

	}

	public static function clean() {

		Invite::model()
			->whereExpiresAt('<', new \Sql('NOW() - INTERVAL 7 DAY'))
			->update([
				'status' => Invite::INVALID
			]);

		Invite::model()
			->where(Invite::model()->field('used').' = '.Invite::model()->field('limit'))
			->whereLastUsedAt('<', new \Sql('NOW() - INTERVAL 7 DAY'))
			->update([
				'status' => Invite::INVALID
			]);

	}

}
?>
