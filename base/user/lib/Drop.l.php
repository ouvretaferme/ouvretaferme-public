<?php
namespace user;

/**
 * Drop a user
 */
class DropLib {

	use \Notifiable;


	/**
	 * Checks if user can close its account in the current session
	 *
	 * @return bool
	 */
	public static function canClose(): bool {

		$loggedSince = \util\DateLib::interval(
			\Clock::timestamp(),
			\session\SessionLib::get('userLoggedAt')
		);

		return (
			$loggedSince < UserSetting::CLOSE_TIME_LIMIT * 60
		);

	}

	/**
	 * Close user account
	 *
	 */
	public static function changeClose(User $eUser): bool {

		$eUser->expects(['id', 'deletedAt']);

		if($eUser['deletedAt']) {
			$eUser['deletedAt'] = NULL;
		} else {

			// Un compte au planning d'une structure ne se ferme pas : la personne y garde des périodes que la
			// structure doit d'abord terminer (décidé le 22/10). L'annulation reste toujours permise.
			$cStructure = \hr\PresenceLib::getStructuresOpenByUser($eUser);

			if($cStructure->notEmpty()) {
				User::fail('closePresence', [$cStructure]);
				return FALSE;
			}

			$eUser['deletedAt'] = User::model()->now('datetime', UserSetting::CLOSE_TIMEOUT.' DAY');

		}

		$affected = User::model()
			->select('deletedAt')
			->update($eUser);

		if($affected) {
			\session\SessionLib::set('userDeletedAt', $eUser['deletedAt']);
		}

		if($eUser['deletedAt'] and $eUser['email'] !== NULL) {
			self::notify('close', $eUser);
		}

		return TRUE;

	}

	/**
	 * Close accounts
	 */
	public static function closeExpired() {

		$cUser = User::model()
			->select(['id', 'visibility'])
			->where('deletedAt IS NOT NULL AND deletedAt < NOW()')
			->whereStatus('!=', User::CLOSED)
			->recordset()
			->getCollection();

		foreach($cUser as $eUser) {

			// Mis au planning pendant le délai : le compte attend que la structure l'en sorte, et l'écran de
			// fermeture le lui dit (`close.p.php`).
			if(\hr\PresenceLib::getStructuresOpenByUser($eUser)->notEmpty()) {
				continue;
			}

			self::closeNow($eUser);

		}

	}

	/**
	 * Close now a user
	 * For admin purpose only
	 *
	 */
	public static function closeNow(User $eUser) {

		$eUser->expects(['id', 'visibility']);

		self::notify('dropClose', $eUser);

		User::model()->beginTransaction();

		User::model()
			->update($eUser, [
				'email' => NULL,
				'deletedAt' => new \Sql('NOW()'),
				'status' => User::CLOSED
			]);

		if($eUser['visibility'] === User::PUBLIC) {

			UserAuth::model()
				->whereUser($eUser)
				->update([
					'user' => NULL,
					'userArchive' => $eUser,
					'loginArchive' => new \Sql('login'),
					'login' => NULL,
				]);

			\session\SessionLib::killByUser($eUser);

		}

		User::model()->commit();

	}

	/**
	 * Delete a user from the database
	 *
	 */
	public static function delete(User $eUser) {

		self::notify('dropDelete', $eUser);

		TosAiLib::deleteByUser($eUser);

		UserAuth::model()
			->whereUser($eUser)
			->delete();

		User::model()->delete($eUser);

	}

}
?>
