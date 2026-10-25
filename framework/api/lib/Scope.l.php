<?php
namespace api;

class ScopeLib extends ScopeCrud {

	public static function getAll(): \Collection {

		return Scope::model()
			->select(Scope::getSelection())
			->sort(['name' => SORT_ASC])
			->getCollection();

	}

	public static function getByNames(array $names): \Collection {

		return Scope::model()
			->select(Scope::getSelection())
			->whereName('IN', $names)
			->sort(['name' => SORT_ASC])
			->getCollection();

	}

}
?>
