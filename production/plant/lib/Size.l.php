<?php
namespace plant;

/**
 * Size basic functions
 */
class SizeLib extends SizeCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['plant', 'name', 'comment', 'yield'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['name', 'comment', 'yield'];
	}

	public static function getByStructureAndPlant(\structure\Structure $eStructure, Plant $ePlant): \Collection {

		return Size::model()
			->select(Size::getSelection())
			->whereStructure($eStructure)
			->wherePlant($ePlant)
			->sort('name')
			->getCollection();

	}

	public static function getForYield(\structure\Structure $eStructure, Plant $ePlant): \Collection {

		return Size::model()
			->select('id')
			->whereStructure($eStructure)
			->wherePlant($ePlant)
			->whereYield(TRUE)
			->getCollection();

	}

	public static function create(Size $e): void {

		$e->expects(['structure', 'plant']);

		try {
			parent::create($e);
		} catch(\DuplicateException) {
			Size::fail('name.duplicate');
		}

	}

	public static function update(Size $e, array $properties): void {

		try {
			parent::update($e, $properties);
		} catch(\DuplicateException) {
			Size::fail('name.duplicate');
		}

	}

	public static function delete(Size $e): void {

		$e->expects(['id']);

		if(\series\Task::model()
				->whereHarvestSize($e)
				->exists()) {
			Size::fail('deleteUsed');
			return;
		}

		Size::model()->delete($e);

	}

}
?>
