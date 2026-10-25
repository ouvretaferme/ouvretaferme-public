<?php
namespace product;

class CategoryLib extends CategoryCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['name', 'icon'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['name', 'icon'];
	}

	public static function getByStructure(\structure\Structure $eStructure, mixed $id = NULL, ?string $index = NULL): \Collection|Category {

		$expects = 'collection';

		if($id !== NULL) {
			$expects = 'element';
			Category::model()->whereId($id);
		}

		Category::model()
			->select(Category::getSelection())
			->whereStructure($eStructure)
			->sort(['position' => SORT_ASC]);

		if($expects === 'element') {
			return Category::model()->get();
		} else {
			return Category::model()->getCollection(NULL, NULL, $index);
		}

	}

	public static function getByStructures(\Collection $cStructure): \Collection {

		return Category::model()
			->select(Category::getSelection())
			->whereStructure('IN', $cStructure)
			->sort([
				'position' => SORT_ASC,
				'id' => SORT_ASC
			])
			->getCollection();

	}

	public static function create(Category $e): void {

		Category::model()->beginTransaction();

		$e['position'] = Category::model()
			->whereStructure($e['structure'])
			->count() + 1;

		parent::create($e);

		Category::model()->commit();

	}

	public static function incrementPosition(Category $e, int $increment): void {

		$e->expects(['structure']);

		if($increment !== -1 and $increment !== 1) {
			return;
		}

		$eStructure = $e['structure'];

		Category::model()->beginTransaction();

		$position = Category::model()
			->whereId($e)
			->getValue('position');

		switch($increment) {

			case -1 :

				Category::model()
					->whereStructure($eStructure)
					->wherePosition($position - 1)
					->update([
						'position' => $position
					]);

				Category::model()->update($e, [
						'position' => $position - 1
					]);

				break;

			case 1 :

				Category::model()
					->whereStructure($eStructure)
					->wherePosition($position + 1)
					->update([
						'position' => $position
					]);

				Category::model()->update($e, [
						'position' => $position + 1
					]);

				break;

		}

		Category::model()->commit();

	}

	public static function delete(Category $e): void {

		$e->expects(['id', 'structure']);

		Category::model()->beginTransaction();

		Product::model()
			->whereCategory($e)
			->update([
				'category' => new Category()
			]);

		parent::delete($e);

		self::reorder($e['structure']);

		Category::model()->commit();

	}

	public static function reorder(\structure\Structure $eStructure): void {

		$cCategory = self::getByStructure($eStructure);

		$position = 1;

		foreach($cCategory as $eCategory) {

			Category::model()->update($eCategory, [
				'position' => $position++
			]);

		}

	}

}
?>
