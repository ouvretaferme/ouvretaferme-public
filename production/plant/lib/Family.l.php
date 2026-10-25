<?php
namespace plant;

/**
 * Family basic functions
 */
class FamilyLib extends FamilyCrud {

	public static function getList(): \Collection {

		return Family::model()
			->select(Family::getSelection())
			->sort('name')
			->getCollection();

	}

	public static function delete(Family $e): void {

		throw new \UnsupportedException();

	}

}
?>
