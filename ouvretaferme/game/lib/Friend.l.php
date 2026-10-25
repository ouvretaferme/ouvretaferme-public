<?php
namespace game;

class FriendLib extends FriendCrud {

	public static function getByPlayer(Player $ePlayer): \Collection {

		$cFriend = Friend::model()
			->select(Friend::getSelection())
			->whereUser($ePlayer['user'])
			->getColumn('friend');

		if($cFriend->notEmpty()) {

			return Player::model()
				->select(Player::getSelection())
				->whereUser('IN', $cFriend)
				->sort([
					'points' => SORT_DESC,
					'id' => SORT_ASC
				])
				->getCollection();

		} else {
			return new \Collection();
		}


	}

	public static function exists(Player $ePlayer, Player $ePlayerFriend): bool {

		return Friend::model()
			->whereUser($ePlayer['user'])
			->whereFriend($ePlayerFriend['user'])
			->exists();

	}

	// Une amitié s'écrit dans les deux sens, et se défait de même.
	public static function deleteByPlayers(Player $ePlayer, Player $ePlayerFriend): void {

		Friend::model()->beginTransaction();

			foreach([[$ePlayer, $ePlayerFriend], [$ePlayerFriend, $ePlayer]] as [$ePlayerFrom, $ePlayerTo]) {

				$eFriend = Friend::model()
					->select('id')
					->whereUser($ePlayerFrom['user'])
					->whereFriend($ePlayerTo['user'])
					->get();

				if($eFriend->notEmpty()) {
					self::delete($eFriend);
				}

			}

		Friend::model()->commit();

	}

	public static function are(Player $ePlayer): bool {

		return Friend::model()
			->whereUser(\user\ConnectionLib::getOnline())
			->whereFriend($ePlayer['user'])
			->exists();


	}

}
?>
