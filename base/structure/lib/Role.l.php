<?php
namespace structure;

class RoleLib extends RoleCrud {

	use \ModuleDeferred;

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return self::getPropertiesUpdate();
	}

	public static function getPropertiesUpdate(?string $origin = NULL): \Closure|array {

		return [
			'name',
			'planning', 'productionObserve', 'productionAnalyze',
			'selling', 'commercialisationAnalyze',
			'accounting', 'accountingAnalyze',
		];

	}

	public static function create(Role $e): void {

		try {
			parent::create($e);
		} catch(\DuplicateException) {
			Role::fail('name.duplicate');
			return;
		}

		self::deleteCache($e['structure']);

	}

	public static function update(Role $e, array $properties): void {

		try {
			parent::update($e, $properties);
		} catch(\DuplicateException) {
			Role::fail('name.duplicate');
			return;
		}

		self::deleteCache($e['structure']);

	}

	public static function delete(Role $e): void {

		Role::model()->beginTransaction();

			if(
				Member::model()
					->whereStructure($e['structure'])
					->whereRole($e)
					->exists()
			) {
				\Fail::log('structure\Role::deleteUsed');
				Role::model()->commit();
				return;
			}

			parent::delete($e);

			InviteLib::deleteByRole($e);

		Role::model()->commit();

		self::deleteCache($e['structure']);

	}

	/**
	 * Un groupe de formation n'a plus d'enseignants : ils deviennent administrateurs (décision de
	 * du 26/09/2026), puis le rôle disparaît.
	 *
	 * ⚠️ Tous les membres du rôle, sortis compris, et toutes ses invitations, acceptées comprises :
	 * `delete()` refuse un rôle que porte encore un membre quel que soit son statut, et efface les
	 * invitations du rôle, auxquelles `Member.inviteKey` renvoie.
	 */
	public static function mergeTeacherIntoAdmin(Structure $eStructure): array {

		$eRoleTeacher = Role::model()
			->select(Role::getSelection())
			->whereStructure($eStructure)
			->whereFqn('teacher')
			->get();

		if($eRoleTeacher->empty()) {
			return ['members' => 0, 'invites' => 0];
		}

		$eRoleAdmin = Role::model()
			->select(Role::getSelection())
			->whereStructure($eStructure)
			->whereFqn('admin')
			->get();

		if($eRoleAdmin->empty()) {
			throw new \Exception('Le groupe #'.$eStructure['id'].' n\'a pas de rôle administrateur');
		}

		$cMember = Member::model()
			->select(['id', 'role'])
			->whereStructure($eStructure)
			->whereRole($eRoleTeacher)
			->getCollection();

		$cInvite = Invite::model()
			->select(['id', 'memberRole'])
			->whereStructure($eStructure)
			->whereMemberRole($eRoleTeacher)
			->getCollection();

		Role::model()->beginTransaction();

			foreach($cMember as $eMember) {
				$eMember['role'] = $eRoleAdmin;
				MemberLib::update($eMember, ['role']);
			}

			foreach($cInvite as $eInvite) {
				$eInvite['memberRole'] = $eRoleAdmin;
				InviteLib::update($eInvite, ['memberRole']);
			}

			self::delete($eRoleTeacher);

		Role::model()->commit();

		return ['members' => $cMember->count(), 'invites' => $cInvite->count()];

	}

	/**
	 * L'administrateur d'un groupe de formation s'appelle « Formateur » (décision du
	 * 26/09/2026). Le nom est une donnée, lue telle quelle par les écrans des membres : les
	 * groupes créés avant la décision portent encore « Administrateur », que ceci renomme.
	 */
	public static function nameEducationAdmin(Structure $eStructure): bool {

		$eRole = Role::model()
			->select(['id', 'structure', 'name'])
			->whereStructure($eStructure)
			->whereFqn('admin')
			->get();

		$name = RoleUi::getEducationAdmin();

		if($eRole->empty() or $eRole['name'] === $name) {
			return FALSE;
		}

		$eRole['name'] = $name;

		self::update($eRole, ['name']);

		return TRUE;

	}

	public static function deferred(\structure\Structure $eStructure): \Collection {

		return \Cache::redis()->query(self::getCacheKey($eStructure), function() use ($eStructure) {

			return Role::model()
				->select(RoleElement::getSelection())
				->whereStructure($eStructure)
				->getCollection(index: 'id');

		}, 86400);

	}

	public static function getOne(Structure $eStructure, string $fqn): Role {

		return self::ask(fn(Role $e) => $e['fqn'] === $fqn, $eStructure);

	}

	public static function getByStructure(Structure $eStructure, bool $withAdmin = TRUE): \Collection {

		return self::askCollection(fn(Role $e) => (
			$e['visibility'] === Role::PUBLIC and
			(
				$withAdmin === TRUE or
				($withAdmin === FALSE and $e['admin'] === FALSE)
			)
		), $eStructure)->sort(['name' => SORT_ASC]);

	}

	public static function createForStructure(\structure\Structure $eStructure): void {

		$c = new \Collection();

		$c[] = new Role([
			'name' => $eStructure->isEducation() ? RoleUi::getEducationAdmin() : RoleUi::getFqn('admin'),
			'fqn' => 'admin',
			'admin' => TRUE,
		  	'productionObserve' => TRUE,
			'productionExport' => TRUE,
		]);

		switch($eStructure['type']) {

			case Structure::PRODUCER :

					$c[] = new Role([
						'name' => RoleUi::getDefaultTemporary(),
						'planning' => Role::LIMITED,
						'selling' => FALSE,
						'productionAnalyze' => FALSE,
						'productionObserve' => TRUE,
						'commercialisationAnalyze' => FALSE,
						'accounting' => Role::DENIED,
					]);
					$c[] = new Role([
						'name' => RoleUi::getDefaultPermanent(),
						'planning' => Role::FULL,
						'selling' => TRUE,
						'productionAnalyze' => FALSE,
						'productionObserve' => TRUE,
						'commercialisationAnalyze' => FALSE,
						'accounting' => Role::DENIED,
					]);
					$c[] = new Role([
						'name' => RoleUi::getDefaultObserverProduction(),
						'planning' => Role::DENIED,
						'selling' => FALSE,
						'productionAnalyze' => TRUE,
						'productionObserve' => TRUE,
						'commercialisationAnalyze' => FALSE,
						'accounting' => Role::DENIED,
					]);
					$c[] = new Role([
						'name' => RoleUi::getDefaultAccountant(),
						'accounting' => Role::FULL,
					]);
					$c[] = new Role([
						'name' => RoleUi::getFqn('ghost'),
						'fqn' => 'ghost',
						'visibility' => Role::PRIVATE,
						'planning' => Role::FULL
					]);

				break;

			case Structure::SHOP :
				break;

			case Structure::COMMUNITY :

				if(in_array($eStructure['communityType'], [Structure::PRODUCER, Structure::NETWORK, Structure::ACCOUNTING])) {

					$c[] = new Role([
						'name' => RoleUi::getFqn('member'),
						'fqn' => 'member',
						'productionObserve' => TRUE,
					]);

				}

				// Le conseiller accompagne sans être un membre ordinaire : il voit ce qu'un membre
				// voit et peut copier un plan de culture vers une autre ferme, ce qui est le geste
				// de l'accompagnement. Propre au réseau : c'est le seul type qui porte ce métier.
				if(in_array($eStructure['communityType'], [Structure::NETWORK])) {

					$c[] = new Role([
						'name' => RoleUi::getFqn('advisor'),
						'fqn' => 'advisor',
						'productionObserve' => TRUE,
						'productionExport' => TRUE,
					]);

				}

				if(in_array($eStructure['communityType'], [Structure::EDUCATION])) {

					$c[] = new Role([
						'name' => RoleUi::getFqn('student'),
						'fqn' => 'student',
						'productionObserve' => TRUE,
						'sections' => Role::OWN,
					]);

				}
				break;

			case Structure::AMAP :

				$c[] = new Role([
					'name' => RoleUi::getFqn('producer'),
					'fqn' => 'producer',
				]);

				$c[] = new Role([
					'name' => RoleUi::getFqn('referent'),
					'fqn' => 'referent',
					'visibility' => Role::PRIVATE,
				]);
				break;

		}

		foreach($c as $e) {

			$e['structure'] = $eStructure;

			self::create($e);

		}

	}

	public static function deleteCache(Structure $eStructure): void {
		\Cache::redis()->delete(self::getCacheKey($eStructure));
	}

	private static function getCacheKey(Structure $eStructure): string {
		return 'roles-'.$eStructure['id'];
	}

}
