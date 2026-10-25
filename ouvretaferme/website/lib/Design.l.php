<?php
namespace website;

class DesignLib extends DesignCrud {

	public static function getAll(): \Collection {

		return \Cache::redis()->query('website-design-all', function() {

			return Design::model()
				->select('id', 'fqn', 'name', 'free')
				->sort(['name' => SORT_ASC])
				->getCollection(index: 'id');

		}, 3600);

	}

}
?>
