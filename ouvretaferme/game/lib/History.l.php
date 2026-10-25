<?php
namespace game;

class HistoryLib extends HistoryCrud {

	public static function getByPlayer(Player $ePlayer): \Collection {

		return History::model()
			->select(History::getSelection())
			->whereUser($ePlayer['user'])
			->sort([
				'createdAt' => SORT_DESC
			])
			->getCollection(0, 20);


	}

	public static function deleteByPlayer(Player $ePlayer): void {

		$cHistory = History::model()
			->select('id')
			->whereUser($ePlayer['user'])
			->getCollection();

		foreach($cHistory as $eHistory) {
			self::delete($eHistory);
		}

	}

}
?>
