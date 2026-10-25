<?php
namespace work;

class CategoryLib extends CategoryCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['name'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['name'];
	}

	public static function duplicateForStructure(\structure\Structure $eStructure): \Collection {

		$cCategory = Category::model()
			->select(Category::getSelection())
			->whereStructure(NULL)
			->getCollection(index: 'id');

		$cCategory->map(function(Category $eCategory) use($eStructure) {
			$eCategory['id'] = NULL;
			$eCategory['structure'] = $eStructure;
		});

		foreach($cCategory as $eCategory) {
			Category::model()->insert($eCategory);
		}

		return $cCategory;

	}

	public static function getByStructure(\structure\Structure $eStructure, mixed $id = NULL, array|string|null $fqn = NULL, ?string $index = NULL): \Collection|Category {

		$expects = 'collection';

		if($id !== NULL) {
			$expects = 'element';
			Category::model()->whereId($id);
		}

		if($fqn !== NULL) {
			if(is_string($fqn)) {
				$expects = 'element';
				Category::model()->whereFqn($fqn);
			} else {
				Category::model()->where('fqn', 'IN', $fqn);
			}
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

	public static function create(Category $e): void {

		Category::model()->beginTransaction();

		$categories = Category::model()
			->whereStructure($e['structure'])
			->count();

		if($categories === WorkSetting::CATEGORIES_LIMIT) {

			Category::fail('limitReached');

			Category::model()->rollBack();

			return;

		}

		$e['position'] = $categories + 1;

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

		$e->expects(['id', 'structure', 'fqn']);

		if(in_array($e['fqn'], WorkSetting::CATEGORIES_STRUCTURELLES, TRUE)) {
			Category::fail('deleteMandatory');
			return;
		}

		if(
			Action::model()
				->whereStructure($e['structure'])
				->where('JSON_CONTAINS(categories, \''.$e['id'].'\')')
				->exists() or
			\series\Task::model()
				->whereStructure($e['structure'])
				->whereCategory($e)
				->exists()
		) {
			Category::fail('deleteUsed');
			return;
		}

		Category::model()->beginTransaction();

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
