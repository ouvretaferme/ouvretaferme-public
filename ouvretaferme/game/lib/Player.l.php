<?php
namespace game;

class PlayerLib extends PlayerCrud {

	use \Notifiable;

	// Indexé par utilisateur : un processus peut changer d'utilisateur connecté (cron, tests).
	private static array $ePlayerOnline = [];

	// La version publique, sans association, ne reçoit aucun don.
	public static function hasDonate(\user\User $eUser): bool {

		if(\structure\StructureSetting::hasAssociationPackage() === FALSE) {
			return FALSE;
		}

		return self::notify('hasDonate', $eUser)[0];

	}

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['name'];
	}

	public static function create(Player $e): void {

		try {

			Player::model()->beginTransaction();

				$e['code'] = self::generateCode();

				parent::create($e);

				TileLib::createByPlayer($e);
				FoodLib::createByPlayer($e);

			Player::model()->commit();

		} catch(\DuplicateException) {

			Player::model()->rollBack();
			Player::fail('name.duplicate');
		}

	}

	/**
	 * Un code ami tiré au hasard, qu'aucun joueur ne porte encore. L'unicité n'est pas tenue par un index :
	 * à 2^40 codes pour quelques centaines de joueurs, un second tirage est déjà rare.
	 */
	public static function generateCode(): string {

		$alphabet = GameSetting::CODE_ALPHABET;

		do {

			$code = '';

			for($position = 0; $position < GameSetting::CODE_LENGTH; $position++) {
				$code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
			}

		} while(Player::model()->whereCode($code)->exists());

		return $code;

	}

	// Un code de la forme que tire `generateCode()`
	public static function isCode(?string $code): bool {

		return (
			$code !== NULL and
			strlen($code) === GameSetting::CODE_LENGTH and
			strspn($code, GameSetting::CODE_ALPHABET) === GameSetting::CODE_LENGTH
		);

	}

	public static function updateCode(Player $e): void {

		$e['code'] = self::generateCode();

		self::update($e, ['code']);

	}

	public static function getByUser(\user\User $eUser): Player {

		return Player::model()
			->select(Player::getSelection())
			->whereUser($eUser)
			->get();

	}

	public static function getOnline(): Player {

		$eUser = \user\ConnectionLib::getOnline();

		if($eUser->empty()) {
			return new Player();
		}

		if(array_key_exists($eUser['id'], self::$ePlayerOnline)) {
			return self::$ePlayerOnline[$eUser['id']];
		}

		$ePlayer = Player::model()
			->select(Player::getSelection())
			->whereUser($eUser)
			->get();

		if($ePlayer->notEmpty()) {

			$ePlayer['user'] = $eUser;

			if($ePlayer['timeUpdatedAt'] !== \Clock::date()) {

				$ePlayer['time'] = 0;
				$ePlayer['timeUpdatedAt'] = \Clock::date();

				self::update($ePlayer, ['time', 'timeUpdatedAt']);

			}

		}

		self::$ePlayerOnline[$eUser['id']] = $ePlayer;

		return $ePlayer;

	}

	public static function getForUpdate(Player $ePlayer): Player {

		$ePlayerLocked = Player::model()
			->select(Player::getSelection())
			->whereId($ePlayer)
			->getForUpdate()
			->validate();

		// L'utilisateur de l'appelant est complet, quand la relecture n'en porte que l'id : premium et
		// journée du joueur se calculent sur lui.
		$ePlayerLocked['user'] = $ePlayer['user'];

		return $ePlayerLocked;

	}

	// Écriture de masse sur tous les joueurs du jour précédent, d'où le tableau de valeurs.
	public static function resetTime(): void {

		Player::model()
			->whereTimeUpdatedAt('!=', \Clock::date())
			->update([
				'time' => 0,
				'timeUpdatedAt' => \Clock::date()
			]);

	}

	// La condition sur le temps restant est posée dans la mise à jour elle-même : la dépense et sa
	// vérification sont atomiques, et une dépense refusée n'écrit rien.
	public static function changeTime(Player $e, float $value): bool {

		$affected = Player::model()
			->where(new \Sql('time - '.$value.' <= '.$e->getDailyTime()), if: $value < 0)
			->update($e, [
				'time' => new \Sql('time - '.$value)
			]);

		return ($affected > 0);

	}

	public static function updatePoints(Player $e): void {

		Player::model()->update($e, [
			'points' => self::calculatePoints($e['user'])
		]);

	}

	public static function calculatePoints(\user\User $eUser): int {

		return Food::model()
			->whereUser($eUser)
			->getValue(new \Sql('SUM(IF(growing IS NULL, current * 10, current))', 'int'));

	}

	public static function getTotalPoints(): int {

		return Player::model()->getValue(new \Sql('SUM(points)', 'int'));

	}

	public static function getPointsRanking(Player $ePlayerOnline): \Collection {

		$position = 1;

		$cPlayer = Player::model()
			->select([
				'position' => function() use (&$position) {
					return $position++;
				},
				'id',
				'name',
				'points',
				'user'
			])
			->whereName('NOT IN', GameSetting::ADMIN)
			->wherePoints('>', 0)
			->sort([
				'points' => SORT_DESC,
				$ePlayerOnline->empty() ? new \Sql('id ASC'): new \Sql('id = '.$ePlayerOnline['id'].' DESC, id ASC')
			])
			->getCollection(0, 20);

		if(
			$ePlayerOnline->notEmpty() and
			$cPlayer->contains(fn($ePlayer) => $ePlayerOnline->is($ePlayer)) === FALSE and
			$ePlayerOnline['points'] > 0
		) {

			$cPlayer[] = (clone $ePlayerOnline)->merge([
				'position' => self::getPosition($ePlayerOnline)
			]);

		}

		return $cPlayer;

	}

	/**
	 * Le rang du joueur au classement des rennes, NULL tant qu'il n'y figure pas. Le classement place un joueur en tête
	 * de ceux qui ont autant de rennes que lui, d'où le compte des seuls joueurs qui en ont plus.
	 */
	public static function getPosition(Player $ePlayer): ?int {

		if($ePlayer['points'] === 0) {
			return NULL;
		}

		return Player::model()
			->whereName('NOT IN', GameSetting::ADMIN)
			->wherePoints('>', $ePlayer['points'])
			->count() + 1;

	}

	public static function restart(Player $ePlayer): void {

		Player::model()->beginTransaction();

			// Le joueur d'abord, comme chaque geste d'ActionLib : un geste en cours ne peut ni
			// s'interbloquer avec la remise à zéro ni écrire sur un joueur qu'elle efface.
			$ePlayerLocked = Player::model()
				->whereId($ePlayer)
				->getForUpdate();

			// Deux remises à zéro envoyées ensemble : la seconde trouve le joueur déjà effacé.
			if($ePlayerLocked->empty()) {
				Player::model()->commit();
				return;
			}

			FoodLib::deleteByPlayer($ePlayer);
			HistoryLib::deleteByPlayer($ePlayer);
			TileLib::deleteByPlayer($ePlayer);

			self::delete($ePlayer);

		Player::model()->commit();

	}

	/**
	 * Chaque envoi compte, trouvé ou non : compter les seuls échecs laisserait deviner un code au rythme
	 * des succès.
	 */
	public static function addFriend(Player $ePlayer, string $code): void {

		if(\user\ThrottleAiLib::increment('game-friend', (string)$ePlayer['user']['id'], GameSetting::WINDOW_FRIEND) > GameSetting::LIMIT_FRIEND) {
			Friend::fail('code.throttled');
			return;
		}

		$ePlayerFriend = Player::model()
			->select(Player::getSelection())
			->whereCode($code)
			->get();

		if(
			$ePlayerFriend->empty() or
			$ePlayerFriend->is($ePlayer)
		) {
			Friend::fail('code.unknown');
			return;
		}

		if(FriendLib::exists($ePlayer, $ePlayerFriend)) {
			return;
		}

		try {

			Friend::model()->beginTransaction();

				FriendLib::create(new Friend([
					'user' => $ePlayerFriend['user'],
					'friend' => $ePlayer['user']
				]));

				FriendLib::create(new Friend([
					'user' => $ePlayer['user'],
					'friend' => $ePlayerFriend['user']
				]));

			Friend::model()->commit();

		} catch(\DuplicateException) {
			// Deux ajouts simultanés de la même amitié : le premier a écrit les deux lignes.
			Friend::model()->rollBack();
		}

	}

	public static function motivate(Player $ePlayer, Player $ePlayerFriend): void {

		Player::model()->beginTransaction();

			// Les deux joueurs se verrouillent d'une seule lecture, dans l'ordre de l'index : deux
			// amis qui se motivent l'un l'autre au même instant ne s'attendent pas mutuellement.
			$cPlayer = Player::model()
				->select(Player::getSelection())
				->whereId('IN', [$ePlayer['id'], $ePlayerFriend['id']])
				->option('for-update')
				->getCollection(index: 'id');

			$ePlayerLocked = $cPlayer[$ePlayer['id']];
			$ePlayerFriendLocked = $cPlayer[$ePlayerFriend['id']];

			if(
				FriendLib::exists($ePlayerLocked, $ePlayerFriendLocked) === FALSE or
				$ePlayerLocked->acceptMotivate($ePlayerFriendLocked) === FALSE
			) {
				Player::model()->commit();
				Player::fail('giftSentAt.motivate');
				return;
			}

			self::changeTime($ePlayerFriendLocked, GameSetting::BONUS_MOTIVATION);

			$ePlayerLocked['giftSentAt'] = \Clock::date();
			self::update($ePlayerLocked, ['giftSentAt']);

			$ePlayerFriendLocked['giftReceivedAt'] = \Clock::date();
			self::update($ePlayerFriendLocked, ['giftReceivedAt']);

		Player::model()->commit();

	}

	public static function removeFriend(Player $ePlayer, Player $ePlayerFriend): void {

		FriendLib::deleteByPlayers($ePlayer, $ePlayerFriend);

	}

}
?>
