<?php
namespace work;

class SupplierLib extends SupplierCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['name'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return self::getPropertiesCreate();
	}

	public static function getFromQuery(string $query, \structure\Structure $eStructure, ?array $properties = []): \Collection {

		if($query !== '') {
			Supplier::model()->whereName('LIKE', '%'.$query.'%');
		}

		return Supplier::model()
			->select($properties ?: Supplier::getSelection())
			->whereStructure($eStructure)
			->getCollection()
			->sort('name', natural: TRUE);

	}

	public static function getByStructure(\structure\Structure $eStructure, \Search $search = new \Search()): \Collection {

		if($search->get('name')) {
			Supplier::model()->whereName('LIKE', '%'.$search->get('name').'%');
		}

		return Supplier::model()
			->select(Supplier::getSelection())
			->whereStructure($eStructure)
			->getCollection()
			->sort('name', natural: TRUE);

	}

	public static function delete(Supplier $e): void {

		$e->expects(['id', 'structure']);

		if(\plant\Variety::model()
				->whereSupplierSeed($e)
				->exists() or \plant\Variety::model()
				->whereSupplierPlant($e)
				->exists()) {
			Supplier::fail('deleteUsed');
			return;
		}

		Supplier::model()->beginTransaction();

		parent::delete($e);

		Supplier::model()->commit();

	}

}
?>
