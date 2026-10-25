<?php
namespace structure;

class MemberLib extends MemberCrud {

	/**
	 * L'élément que le membre regardait la dernière fois dans une liste (catégorie, catalogue, compte
	 * bancaire, exercice). Il vit en redis sans échéance, et sa perte au redémarrage est acceptée : le
	 * prochain clic le repose. Seul son identifiant est gardé, chaque lecteur le cherche dans ce qu'il
	 * a chargé, puisque rien ne garantit qu'il désigne encore une ligne qui existe.
	 */
	const VIEWS_CACHED = ['viewSellingCategoryCurrent', 'viewShopCatalogCurrent', 'viewAccountingBankAccount', 'viewAccountingYear'];

	private static array $cMemberOnline = [];
	private static array $viewsCached = [];


	public static function getPropertiesUpdate(?string $origin = NULL): \Closure|array {

		// L'API agit pour la structure et non pour un membre connecté : canUpdateRole(), qui interdit
		// de modifier son propre rôle, y renverrait toujours FALSE.
		if($origin === 'api') {
			return ['role'];
		}

		return function(Member $e) {

			if($e->assertUpdateRole() === FALSE) {
				return [];
			} else {
				return ['role'];
			}

		};

	}

	// Indexé par utilisateur : un même processus peut en connecter plusieurs (tests, scripts CLI),
	// et un cache à une seule entrée rendrait alors les structures du premier connecté.
	public static function getOnline(): \Collection {

		$eUser = \user\ConnectionLib::getOnline();
		$user = $eUser->empty() ? 0 : $eUser['id'];

		return self::$cMemberOnline[$user] ??= self::getByUser($eUser);

	}

	public static function getRecent(Structure $eStructureCurrent, \Collection $ccStructure, int $number): \Collection {

		$cMember = self::getOnline();

		$cStructureRecent = new \Collection();
		$cStructureVisited = new \Collection();

		foreach($ccStructure as $cStructure) {

			foreach($cStructure as $eStructure) {

				if($eStructure['id'] === $eStructureCurrent['id']) {
					$cStructureRecent[$eStructure['id']] = $eStructure;
				} else if(
					$cMember->offsetExists($eStructure['id']) and
					$cMember[$eStructure['id']]['visitedAt'] !== NULL
				) {
					$cStructureVisited[$eStructure['id']] = $eStructure;
				}

			}

		}

		$cStructureVisited->sort(fn(Structure $e1, Structure $e2) => strcmp($cMember[$e2['id']]['visitedAt'], $cMember[$e1['id']]['visitedAt']));

		foreach($cStructureVisited as $eStructure) {

			if($cStructureRecent->count() === $number) {
				break;
			}

			$cStructureRecent[$eStructure['id']] = $eStructure;

		}

		return $cStructureRecent;

	}

	public static function clearCache(): void {
		self::$cMemberOnline = [];
		self::$viewsCached = [];
	}

	public static function visit(Structure $eStructure): void {

		// Une session d'administration ouvre la structure sans être le membre : sa visite écraserait
		// celle du membre, qui n'est pas venu.
		if(\user\ConnectionLib::isLoggedExternal()) {
			return;
		}

		$eMember = $eStructure->getMember();

		if($eMember->empty()) {
			return;
		}

		if(
			$eMember['visitedAt'] !== NULL and
			substr($eMember['visitedAt'], 0, 10) === \Clock::date()
		) {
			return;
		}

		$eMember['visitedAt'] = \Clock::datetime();

		// Pas de clearCache() ici : `getMember()` rend l'élément de la collection mémoïsée, qui porte donc
		// déjà la date.
		Member::model()
			->select(['visitedAt'])
			->update($eMember);

	}

	/**
	 * Le dernier passage d'un membre, quel qu'il soit, sur chacune des structures, indexé par leur
	 * id : `visit()` le date une fois par jour. Une structure où personne n'est jamais venu n'y
	 * figure pas.
	 *
	 * @return array<int, string>
	 */
	public static function getVisitedAtByStructures(\Collection $cStructure): array {

		if($cStructure->empty()) {
			return [];
		}

		$cMember = Member::model()
			->select([
				'structure',
				'visitedAt' => new \Sql('MAX(visitedAt)')
			])
			->whereStructure('IN', $cStructure)
			->whereVisitedAt('!=', NULL)
			->group('structure')
			->getCollection();

		$visitedAt = [];

		foreach($cMember as $eMember) {
			$visitedAt[$eMember['structure']['id']] = $eMember['visitedAt'];
		}

		return $visitedAt;

	}

	public static function getUnseenByStructure(): array {

		$unseen = [];

		foreach(self::getOnline() as $eMember) {
			$unseen[$eMember['structure']['id']] = $eMember['notificationsUnseen'];
		}

		return $unseen;

	}

	/**
	 * Les réglages de notification de ce membre dans cette structure. Ils vivent sur l'adhésion, que
	 * la barre et l'émission lisent déjà : les régler ne coûte aucune requête de plus.
	 */
	public static function updateNotifications(Member $e): void {

		Member::model()
			->select(['notifications'])
			->update($e);

	}

	public static function addNotificationsUnseen(\Collection $cMember): void {

		// Écriture de masse, donc un tableau de valeurs : `update($e)` sur un élément porteur d'un id
		// ajouterait `AND id = <id>` et réduirait l'écriture à une seule ligne.
		Member::model()
			->whereId('IN', $cMember)
			->update([
				'notificationsUnseen' => new \Sql('notificationsUnseen + 1')
			]);

	}

	public static function synchronizeNotificationsUnseen(Structure $eStructure, array $unseen): void {

		// Un tableau de valeurs : `UNIQUE (user, structure)` borne l'écriture à une ligne.
		foreach($unseen as $user => $number) {

			Member::model()
				->whereStructure($eStructure)
				->whereUser($user)
				->update([
					'notificationsUnseen' => $number
				]);

		}

	}

	public static function create(Member $e): void {

		$e->expects(['user', 'structure', 'role', 'status']);

		try {

			Member::model()->insert($e);


		} catch(\DuplicateException $e) {

			Member::model()->rollBack();
			Member::fail('duplicate');

		}

	}

	public static function update(Member $e, array $properties): void {

		parent::update($e, $properties);

		// Une clé d'API s'obtient sur le droit d'administrer la structure : qui le perd, en changeant
		// de rôle ou en passant hors du collectif, ne doit pas garder de clé vivante. Le droit se relit
		// en base après l'écriture, la propriété écrite ne portant que l'identifiant du nouveau rôle.
		if(array_intersect($properties, ['role', 'status']) !== []) {
			self::revokeApiKeys($e);
		}

	}

	/**
	 * Les clés d'API du membre, quand il n'administre plus la structure.
	 *
	 * Le membre se relit : ses appelants le chargent avec des sélections différentes, et l'une d'elles
	 * ne porte que ce qu'elle écrit.
	 */
	private static function revokeApiKeys(Member $e): void {

		$e->expects(['id']);

		$eMember = Member::model()
			->select(['user', 'structure'])
			->whereId($e['id'])
			->get();

		if($eMember->empty()) {
			return;
		}

		if(self::isAdmin($eMember['user'], $eMember['structure']) === FALSE) {
			\api\AccessTokenLib::revokeByUserAndReference($eMember['user'], $eMember['structure']['id']);
		}

	}

	public static function countByStructure(Structure $eStructure): int {

		return Member::model()
			->whereStructure($eStructure)
			->whereStatus(Member::IN)
			->whereStructureStatus(Member::ACTIVE)
			->count();

	}

	/**
	 * Les membres de cette structure qui tiennent ce rôle, par son nom de code.
	 *
	 * ⚠️ La jointure porte sur `fqn` et non sur le nom : celui-ci se renomme depuis l'écran des
	 * rôles, le code non — c'est déjà ce que fait `isAdmin()` juste en dessous.
	 */
	public static function countByRoleFqn(Structure $eStructure, string $fqn): int {

		return Member::model()
			->join(Role::model(), 'm1.role = m2.id')
			->where('m1.structure', $eStructure)
			->where('m1.status', Member::IN)
			->where('m2.fqn', $fqn)
			->count();

	}

	public static function isAdmin(\user\User $eUser, Structure $eStructure): bool {

		return Member::model()
			->join(Role::model(), 'm1.role = m2.id')
			->where('m1.user', $eUser)
			->where('m1.structure', $eStructure)
			->where('m1.status', Member::IN)
			->where('m1.structureStatus', Member::ACTIVE)
			->where('m2.admin', TRUE)
			->exists();

	}

	public static function isMember(\user\User $eUser, Structure $eStructure, ?string $status = Member::IN): bool {

		if($status !== NULL) {
			Member::model()->whereStatus($status);
		}

		return Member::model()
			->whereUser($eUser)
			->whereStructure($eStructure)
			->whereStructureStatus(Member::ACTIVE)
			->exists();

	}

	public static function getByUserAndStructure(\user\User $eUser, Structure $eStructure): Member {

		return Member::model()
			->select(Member::getSelection())
			->whereUser($eUser)
			->whereStructure($eStructure)
			->get();

	}

	public static function getByUser(\user\User $eUser): \Collection {

		if($eUser->exists() === FALSE) {
			return new \Collection();
		}

		return Member::model()
			->select(Member::getSelection())
			->whereUser($eUser)
			->whereStructureStatus(Member::ACTIVE)
			->whereStatus(Member::IN)
			->getCollection(NULL, NULL, 'structure');

	}

	/**
	 * Les adhésions de ces personnes à la structure, tout statut, rangées par personne.
	 */
	public static function getByUsers(Structure $eStructure, \Collection $cUser): \Collection {

		return Member::model()
			->select(Member::getSelection())
			->whereStructure($eStructure)
			->whereUser('IN', $cUser)
			->getCollection(index: 'user');

	}

	public static function getGhostByStructure(Structure $eStructure): \Collection {

		$eRole = RoleLib::getOne($eStructure, 'ghost');

		return Member::model()
			->select(Member::getSelection())
			->whereRole($eRole)
			->whereStructure($eStructure)
			->getCollection()
			->sort([
				'user' => ['lastName' => SORT_ASC]
			]);

	}

	/**
	 * Les adhésions que la page Équipe montre, par la règle que l'API emprunte (`applyTeam()`), rangées par nom.
	 * Une liste groupée (`['role', NULL]`) se range dans chacun de ses groupes.
	 */
	public static function getTeamByStructure(Structure $eStructure, array|string $index): \Collection {

		return self::applyTeam(
				Member::model()
					->select(Member::getSelection())
					->whereStructure($eStructure),
				$eStructure
			)
			->getCollection(index: $index)
			->sort([
				'user' => ['lastName' => SORT_ASC]
			], depth: count((array)$index));

	}

	public static function getByStructure(Structure $eStructure, ?array $index = ['role', NULL], Role $eRole = new Role()): \Collection {

		return Member::model()
			->select(Member::getSelection())
			->join(\user\User::model(), 'm1.user = m2.id')
			->whereStructure($eStructure)
			->where('m1.status', Member::IN)
			->where('m1.role', $eRole, if: $eRole->notEmpty())
			->getCollection(index: $index)
			->sort([
				'role',
				'user' => ['lastName' => SORT_ASC]
			]);

	}

	public static function getUsersByStructure(Structure $eStructure): \Collection {

		return self::getByStructure($eStructure, index: NULL)
			->getColumnCollection('user', 'user')
			->sort([
				'firstName' => SORT_ASC,
				'lastName' => SORT_ASC
			]);

	}

	public static function getUsersByStructureForTasks(Structure $eStructure, \Collection $cTask, bool $withPresenceAbsence = FALSE): \Collection {

		$cUser = self::getUsersByStructureForPeriod(
			$eStructure,
			week_date_starts(\Clock::week()),
			week_date_ends(\Clock::week())
		);

		$weeks = array_merge($cTask->getColumn('plannedWeek'), $cTask->getColumn('doneWeek'));

		if($weeks) {

			$cUser->appendCollection(

				self::getUsersByStructureForPeriod(
					$eStructure,
					week_date_starts(min($weeks)),
					week_date_ends(max($weeks))
				)

			);

		}

		$cUser->appendCollection(

			\series\Timesheet::model()
				->select([
					'user' => \user\User::getSelection()
				])
				->whereStructure($eStructure)
				->whereTask('IN', $cTask)
				->getCollection()
				->getColumnCollection('user', 'user')
		);

		$cUser->sort([
			'firstName' => SORT_ASC,
			'lastName' => SORT_ASC
		]);

		if($withPresenceAbsence) {
			\hr\PresenceLib::fillUsers($eStructure, $cUser);
			\hr\AbsenceLib::fillUsers($eStructure, $cUser);
		}

		return $cUser;

	}

	public static function getUsersByStructureForPeriod(Structure $eStructure, string $start, string $stop, bool $withPresenceAbsence = FALSE): \Collection {

		$cUser = \hr\TeammateLib::getForPeriod($eStructure, $start, $stop)
			->sort([
				'firstName' => SORT_ASC,
				'lastName' => SORT_ASC
			]);

		if($withPresenceAbsence) {
			\hr\PresenceLib::fillUsers($eStructure, $cUser);
			\hr\AbsenceLib::fillUsers($eStructure, $cUser, $start, $stop);
		}

		return $cUser;

	}

	/**
	 * Une personne sans compte, avant que `build()` ne lui donne son nom : ni adresse ni mot de passe, elle ne
	 * se connecte jamais.
	 */
	public static function getNewGhostUser(): \user\User {

		return \user\UserLib::getNew()->merge([
			'invoiceCountry' => new \user\Country(),
			'email' => NULL,
			'type' => \user\User::PRIVATE,
			'visibility' => \user\User::PRIVATE,
			'roleProducer' => TRUE
		]);

	}

	public static function createGhostUser(Structure $eStructure, \user\User $eUser) {

		Member::model()->beginTransaction();

		$fw = new \FailWatch();

		\user\UserLib::create($eUser);

		if($fw->ok()) {

			$eMember = new Member([
				'id' =>  NULL,
				'structure' => $eStructure,
				'role' => RoleLib::getOne($eStructure, 'ghost'),
				'user' => $eUser,
				'status' => Member::OUT
			]);

			self::create($eMember);

		}

		Member::model()->commit();

	}

	public static function deleteGhostUser(Structure $eStructure, \user\User $eUser) {

		if(\hr\TeammateLib::isTeammate($eStructure, $eUser)) {
			Member::fail('deleteGhostTeammate');
			return;
		}

		Member::model()->beginTransaction();

		$eRole = RoleLib::getOne($eStructure, 'ghost');

		$eMember = Member::model()
			->select('id')
			->whereRole($eRole)
			->whereStructure($eStructure)
			->whereUser($eUser)
			->get();

		$affected = Member::model()
			->whereRole($eRole)
			->whereStructure($eStructure)
			->whereUser($eUser)
			->delete();

		if($affected > 0) {

			// Une invitation qui la désignait ne rattacherait plus rien : elle sort de la liste d'Équipe.
			InviteLib::invalidateByMember($eMember);

			AffiliationAiLib::deleteByMember($eUser, $eStructure);

			\hr\Absence::model()
				->whereStructure($eStructure)
				->whereUser($eUser)
				->delete();

			\user\DropLib::closeNow($eUser);

		}

		Member::model()->commit();

	}

	/**
	 * La personne sans compte de cette structure que désigne cet identifiant, ou une adhésion vide : un
	 * identifiant posté ne nomme jamais une personne d'une autre structure, ni un compte.
	 */
	public static function getGhostById(Structure $eStructure, ?int $id): Member {

		if($id === NULL) {
			return new Member();
		}

		return Member::model()
			->select(Member::getSelection())
			->whereId($id)
			->whereStructure($eStructure)
			->whereRole(RoleLib::getOne($eStructure, 'ghost'))
			->get();

	}

	/**
	 * Le passé d'une personne sans compte passe au compte `$eUser`, membre de la même structure, puis la
	 * personne sans compte disparait : l'équipe ne compte plus deux fois la même personne. Appelé à
	 * l'acceptation d'une invitation qui la désigne.
	 *
	 * Les auteurs (`createdBy` des interventions et des temps) restent à la personne sans compte : ce sont
	 * des traces de qui a saisi, et le compte fermé garde son nom.
	 *
	 * FALSE quand le transfert est refusé, par un `Member::fail()` qui en dit la raison.
	 */
	public static function transferGhost(Member $eMemberGhost, \user\User $eUser): bool {

		$eMemberGhost->expects(['id', 'structure']);

		$eStructure = $eMemberGhost['structure'];

		Member::model()->beginTransaction();

		// Relue sous verrou : une autre acceptation, ou une suppression, peut l'avoir fait disparaitre
		// depuis la lecture qui a ouvert l'écran.
		$eMemberGhost = Member::model()
			->select(Member::getSelection())
			->whereId($eMemberGhost)
			->whereStructure($eStructure)
			->whereRole(RoleLib::getOne($eStructure, 'ghost'))
			->option('for-update')
			->get();

		if($eMemberGhost->empty()) {
			Member::model()->commit();
			Member::fail('ghostGone');
			return FALSE;
		}

		// L'équipe ne porte qu'une fiche, et le temps de travail qu'une journée, par personne : un compte
		// qui en a déjà dans la structure ne peut pas recevoir ceux d'une autre.
		if(\hr\TeammateLib::hasHistory($eStructure, $eUser)) {
			Member::model()->commit();
			Member::fail('ghostConflict');
			return FALSE;
		}

		$eUserGhost = $eMemberGhost['user'];

		\hr\TeammateLib::transferUser($eStructure, $eUserGhost, $eUser);
		\hr\PresenceLib::transferUser($eStructure, $eUserGhost, $eUser);
		\hr\AbsenceLib::transferUser($eStructure, $eUserGhost, $eUser);
		\hr\WorkingTimeLib::transferUser($eStructure, $eUserGhost, $eUser);

		\series\TimesheetLib::transferUser($eStructure, $eUserGhost, $eUser);
		\series\HistoryLib::transferUser($eStructure, $eUserGhost, $eUser);
		\series\TaskLib::transferUser($eStructure, $eUserGhost, $eUser);

		// Écriture de masse : ce sont les vues des autres membres qui affichaient la personne sans compte.
		Member::model()
			->whereStructure($eStructure)
			->whereViewPlanningUserDaily($eUserGhost)
			->update([
				'viewPlanningUserDaily' => $eUser
			]);

		self::deleteGhostUser($eStructure, $eUserGhost);

		Member::model()->commit();

		return TRUE;

	}

	/**
	 * Combien d'administrateurs porte cette structure.
	 *
	 * ⚠️ La même lecture que celle de `leave()` juste en dessous, et c'est voulu : l'écran qui
	 * annonce le refus et l'action qui refuse doivent compter pareil, sinon l'un promet ce que
	 * l'autre interdit.
	 */
	public static function countAdminsByStructure(Structure $eStructure): int {

		return Member::model()
			->join(Role::model(), 'm1.role = m2.id')
			->where('m1.structure', $eStructure)
			->where('m1.status', Member::IN)
			->where('m2.admin', TRUE)
			->count();

	}

	/**
	 * Les membres qui administrent la structure aujourd'hui, avec leur utilisateur.
	 */
	public static function getAdminsByStructure(Structure $eStructure): \Collection {

		return Member::model()
			->join(Role::model(), 'm1.role = m2.id')
			->select(['id', 'user' => ['email', 'firstName', 'lastName', 'vignette']])
			->where('m1.structure', $eStructure)
			->where('m1.status', Member::IN)
			->where('m1.structureStatus', Member::ACTIVE)
			->where('m2.admin', TRUE)
			->sort(['m1.id' => SORT_ASC])
			->getCollection();

	}

	public static function getAccountingManagersByStructure(Structure $eStructure): \Collection {

		return Member::model()
			->join(Role::model(), 'm1.role = m2.id')
			->join(\user\User::model(), 'm1.user = m3.id')
			->select(['id', 'user' => ['firstName', 'lastName', 'email']])
			->where('m1.structure', $eStructure)
			->where('m1.status', Member::IN)
			->where('m1.structureStatus', Member::ACTIVE)
			->or(
				fn() => $this->where('m2.admin', TRUE),
				fn() => $this->where('m2.accounting', Role::FULL)
			)
			->where('m3.email IS NOT NULL')
			->sort(['m1.id' => SORT_ASC])
			->getCollection();

	}

	/**
	 * Les membres qui tiennent la comptabilité sans administrer la structure : un administrateur la lit par l'adresse de
	 * la structure, à laquelle les e-mails comptables partent déjà.
	 */
	public static function getAccountingFullByStructure(Structure $eStructure): \Collection {

		return Member::model()
			->join(Role::model(), 'm1.role = m2.id')
			->join(\user\User::model(), 'm1.user = m3.id')
			->select(['id', 'user' => ['email']])
			->where('m1.structure', $eStructure)
			->where('m1.status', Member::IN)
			->where('m1.structureStatus', Member::ACTIVE)
			->where('m2.admin', FALSE)
			->where('m2.accounting', Role::FULL)
			->where('m3.email IS NOT NULL')
			->sort(['m1.id' => SORT_ASC])
			->getCollection();

	}

	public static function leave(Member $e): void {

		$e->expects(['user', 'structure', 'role']);

		$eStructure = StructureLib::getById($e['structure']);

		if($eStructure['type'] !== Structure::COMMUNITY) {
			Member::fail('leaveNotCommunity');
			return;
		}

		if(
			$e['role']['admin'] and
			self::countAdminsByStructure($eStructure) <= 1
		) {
			Member::fail('leaveLastAdmin');
			return;
		}

		Member::model()->beginTransaction();

			AffiliationAiLib::deleteByMember($e['user'], $e['structure']);

			\api\AccessTokenLib::revokeByUserAndReference($e['user'], $e['structure']['id']);

			parent::delete($e);

		Member::model()->commit();

		NotificationLib::memberLeft($e);

	}

	public static function delete(Member $e): void {

		$e->expects(['role']);

		if($e['role']->isGhost()) {
			Member::fail('deleteGhost');
			return;
		}

		if(
			$e['user']->notEmpty() and
			$e['user']['id'] === \user\ConnectionLib::getOnline()['id']
		) {
			Member::fail('deleteItself');
			return;
		}

		self::deleteInternal($e);

	}

	/**
	 * Retirer tout rôle. Une personne de l'équipe d'une structure de production garde son adhésion, sortie et
	 * sans rôle, tant qu'elle est dans l'équipe : son nom mène à sa fiche, et l'accès se redonne par une
	 * invitation qui rend cette même ligne (décidé le 22/10). Ses périodes et ses absences ne
	 * dépendent pas de son accès, elles ne bougent pas : c'est la sortie du planning qui les borne
	 * (`hr\TeammateLib::leave()`). Tous les retraits passent ici, l'invariant ne dépend donc pas de l'appelant.
	 */
	public static function deleteInternal(Member $e): void {

		$e->expects(['user', 'structure']);

		$eStructure = StructureLib::getById($e['structure'], ['id', 'type']);

		Member::model()->beginTransaction();

			// La ligne d'équipe se relit sous verrou : une sortie du planning peut la retirer dans le même temps,
			// et l'adhésion gardée n'aurait plus d'équipe pour la montrer.
			$revoked = (
				$eStructure->acceptProduction() and
				\hr\TeammateLib::isTeammate($eStructure, $e['user'], lock: TRUE)
			);

			if($revoked) {

				$e['status'] = Member::OUT;
				$e['role'] = new Role();

				self::update($e, ['status', 'role']);

			} else {

				parent::delete($e);

				// Ce qu'un membre a réglé de son accueil ne vaut que pour lui : un membre réinvité repart des
				// défauts, comme un nouveau. Une adhésion gardée garde ses réglages avec elle, comme ses vues.
				\dashboard\PreferenceLib::deleteByUserAndStructure($e['user'], $e['structure']);

			}

			// Quitter un groupe ou quitter une ferme rend les affiliations caduques
			// dans les deux sens.
			AffiliationAiLib::deleteByMember($e['user'], $e['structure']);

			\api\AccessTokenLib::revokeByUserAndReference($e['user'], $e['structure']['id']);

			// Un id rangé dans une colonne json n'est pas une clé étrangère : rien dans le modèle
			// ne le retirera des sous-groupes qui le portent.
			SectionLib::dissociateUser($e['user'], $e['structure']);

		Member::model()->commit();

	}

	/**
	 * L'adhésion gardée d'une personne qui sort de l'équipe (`hr\TeammateLib::delete()`) : sans équipe, plus
	 * aucun écran ne la montrerait. Une personne sans compte garde la sienne, qui porte son rôle.
	 */
	public static function deleteRevoked(Structure $eStructure, \user\User $eUser): void {

		$eMember = self::getRevokedForUpdate($eStructure, $eUser);

		if($eMember->notEmpty()) {
			parent::delete($eMember);
		}

	}

	/**
	 * L'adhésion gardée de ce compte dans cette structure, relue sous verrou par l'invitation qui la rend
	 * (`InviteLib::acceptMember()`) ou la sortie de l'équipe qui l'efface.
	 */
	public static function getRevokedForUpdate(Structure $eStructure, \user\User $eUser): Member {

		return Member::model()
			->select(Member::getSelection())
			->whereStructure($eStructure)
			->whereUser($eUser)
			->whereStatus(Member::OUT)
			->whereRole(NULL)
			->option('for-update')
			->get();

	}

	/**
	 * L'adhésion gardée que désigne cet identifiant, ou une adhésion vide : un identifiant reçu ne nomme jamais
	 * une adhésion d'une autre structure, ni une adhésion qui a un rôle.
	 */
	public static function getRevokedById(Structure $eStructure, ?int $id): Member {

		if($id === NULL) {
			return new Member();
		}

		return Member::model()
			->select(Member::getSelection())
			->whereId($id)
			->whereStructure($eStructure)
			->whereStatus(Member::OUT)
			->whereRole(NULL)
			->get();

	}

	/**
	 * Get season from $season or from Member::$viewSeason
	 */
	public static function getDynamicSeason(Structure $eStructure, int $season): int {

		$eStructure->expects(['id']);

		if($season) {

			if($eStructure->conf(\map\Configuration::class)->checkSeason($season) === FALSE) {
				$season = $eStructure->conf(\map\Configuration::class)['seasonLast'];
			}

			self::setView('viewSeason', $eStructure, $season);

			return $season;

		} else {
			return $eStructure->getView('viewSeason') ?? $eStructure->conf(\map\Configuration::class)['seasonLast'];
		}

	}

	public static function getSoilView(Structure $eStructure, mixed $view): string {

		if(in_array($view, [Member::PLAN, Member::GARDEN], TRUE)) {
			return $view;
		}

		return ($eStructure->getView('viewSoil') === Member::GARDEN) ? Member::GARDEN : Member::PLAN;

	}

	public static function hasHelper(Structure $eStructure, string $helper): bool {

		return in_array($helper, $eStructure->getView('helpers'), TRUE);

	}

	public static function addHelper(Structure $eStructure, string $helper): void {

		$helpers = $eStructure->getView('helpers');

		if(in_array($helper, $helpers, TRUE)) {
			return;
		}

		$helpers[] = $helper;

		self::setView('helpers', $eStructure, $helpers);

	}

	public static function setView(string $field, Structure $eStructure, mixed $newView): mixed {

		$eMember = $eStructure->getMember();

		if($eMember->empty()) {
			return $newView;
		}

		if(
			$eMember[$field] instanceof \Element and
			$newView instanceof \Element
		) {

			if($newView->is($eMember[$field])) {
				return $eMember[$field];
			}

		} else {

			if($newView === $eMember[$field]) {
				return $eMember[$field];
			}

		}

		if(Member::model()->check($field, $newView)) {

			$eMember[$field] = $newView;

			Member::model()
				->select($field)
				->update($eMember);

		}

		return $eMember[$field];

	}

	public static function getViewCached(Structure $eStructure, string $name): ?int {

		self::checkViewCached($name);

		$eMember = $eStructure->getMember();

		if($eMember->empty()) {
			return NULL;
		}

		return self::readViewsCached($eStructure['id'], \user\ConnectionLib::getOnline()['id'])[$name] ?? NULL;

	}

	public static function setViewCached(string $name, Structure $eStructure, \Element $eElement): void {

		self::checkViewCached($name);

		$eMember = $eStructure->getMember();

		if($eMember->empty()) {
			return;
		}

		$views = self::readViewsCached($eStructure['id'], \user\ConnectionLib::getOnline()['id']);

		if($eElement->empty()) {
			unset($views[$name]);
		} else {
			$views[$name] = $eElement['id'];
		}

		self::writeViewsCached($eStructure['id'], \user\ConnectionLib::getOnline()['id'], $views);

	}

	/**
	 * Pour tous les membres de la structure : l'élément regardé a disparu, ou son identifiant va
	 * désigner une autre ligne.
	 */
	public static function deleteViewCached(Structure $eStructure, string $name): void {

		self::checkViewCached($name);

		$users = Member::model()
			->select('user')
			->whereStructure($eStructure)
			->whereUser('!=', NULL)
			->getColumn('user')
			->getIds();

		foreach($users as $user) {

			$views = self::readViewsCached($eStructure['id'], $user);

			if(array_key_exists($name, $views)) {
				unset($views[$name]);
				self::writeViewsCached($eStructure['id'], $user, $views);
			}

		}

	}

	private static function checkViewCached(string $name): void {

		if(in_array($name, self::VIEWS_CACHED, TRUE) === FALSE) {
			throw new \Exception('View \''.$name.'\' is not cached');
		}

	}

	private static function getViewsCachedKey(int $structure, int $user): string {
		return 'structure-member-view-'.$structure.'-'.$user;
	}

	private static function readViewsCached(int $structure, int $user): array {

		$key = self::getViewsCachedKey($structure, $user);

		if(array_key_exists($key, self::$viewsCached) === FALSE) {
			// FALSE quand la clé manque, jamais vide sinon : writeViewsCached() efface plutôt que d'écrire un tableau vide.
			self::$viewsCached[$key] = \Cache::redis()->get($key) ?: [];
		}

		return self::$viewsCached[$key];

	}

	private static function writeViewsCached(int $structure, int $user, array $views): void {

		$key = self::getViewsCachedKey($structure, $user);

		if($views === []) {
			\Cache::redis()->delete($key);
		} else {
			\Cache::redis()->set($key, $views);
		}

		self::$viewsCached[$key] = $views;

	}

	/**
	 * Les adhésions que la page Équipe montre à qui gère la structure, en condition sur une requête : la page
	 * la lit par `getTeamByStructure()`, l'API par `Member_0_1beta::getNewModel()`.
	 *
	 * - Une structure de production montre son équipe, adhésion de tout statut comprise, puis ceux qui ont un
	 *   accès ou sont sans compte hors de l'équipe. L'équipe se lit en `EXISTS` et non en jointure : la liste
	 *   de l'API filtre et trie sur `id` sans préfixe de table.
	 * - Une AMAP ne montre que ses administrateurs : producteurs et référents relèvent de l'AMAP, pas de son
	 *   équipe.
	 * - Une autre structure, groupe compris, montre ceux qui ont un accès.
	 */
	public static function applyTeam(MemberModel $mMember, Structure $eStructure): MemberModel {

		if($eStructure->acceptProduction()) {

			$eRoleGhost = RoleLib::getOne($eStructure, 'ghost');

			$mTeammate = new \hr\TeammateModel()
				->whereStructure($eStructure)
				->whereUser(new \Sql('m1.user'));

			return $mMember->or(
				fn() => $this->whereStatus(Member::IN),
				fn() => $this->whereRole($eRoleGhost),
				fn() => $this->where($mTeammate->existsSql())
			);

		}

		$mMember->whereStatus(Member::IN);

		if($eStructure->isAmap()) {
			$mMember->whereRole(RoleLib::getOne($eStructure, 'admin'));
		}

		return $mMember;

	}

}
