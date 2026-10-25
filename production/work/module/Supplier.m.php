<?php
namespace work;

abstract class SupplierElement extends \Element {

	use \FilterElement;

	private static ?SupplierModel $model = NULL;

	public static function getSelection(): array {
		return Supplier::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): SupplierModel {
		if(self::$model === NULL) {
			self::$model = new SupplierModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Supplier::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class SupplierModel extends \ModuleModel {

	protected string $module = 'work\Supplier';
	protected string $package = 'work';
	protected string $table = 'workSupplier';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'name' => ['text8', 'min' => 1, 'max' => 50, 'collate' => 'general', 'cast' => 'string'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'createdAt' => ['date', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'name', 'structure', 'createdAt'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
		];

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'createdAt' :
				return new \Sql('CURDATE()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function select(...$fields): SupplierModel {
		return parent::select(...$fields);
	}

	public function where(...$data): SupplierModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): SupplierModel {
		return $this->where('id', ...$data);
	}

	public function whereName(...$data): SupplierModel {
		return $this->where('name', ...$data);
	}

	public function whereStructure(...$data): SupplierModel {
		return $this->where('structure', ...$data);
	}

	public function whereCreatedAt(...$data): SupplierModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class SupplierCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Supplier {

		$e = new Supplier();

		if(empty($id)) {
			Supplier::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Supplier::getSelection();
		}

		if(Supplier::model()
			->select($properties)
			->whereId($id)
			->get($e) === FALSE) {
				$e->setGhost($id);
		}

		return $e;

	}

	public static function getByIds(mixed $ids, array $properties = [], mixed $sort = NULL, mixed $index = NULL): \Collection {

		if(empty($ids)) {
			return new \Collection();
		}

		if($properties === []) {
			$properties = Supplier::getSelection();
		}

		if($sort !== NULL) {
			Supplier::model()->sort($sort);
		}

		return Supplier::model()
			->select($properties)
			->whereId('IN', $ids)
			->getCollection(NULL, NULL, $index);

	}

	public static function getCache(mixed $key, \Closure $callback): mixed {
		self::$cache[$key] ??= $callback();
		return self::$cache[$key];
	}

	public static function getAffected(): ?int {
		return self::$affected;
	}

	public static function getNewElement(array $properties = []): Supplier {

		return new Supplier($properties);

	}

	public static function create(Supplier $e): void {

		Supplier::model()->insert($e);

	}

	public static function update(Supplier $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Supplier::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Supplier $e, array $properties): void {

		self::$affected = Supplier::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Supplier $e): void {

		$e->expects(['id']);

		self::$affected = Supplier::model()->delete($e);

	}

}


class SupplierPage extends \ModulePage {

	protected string $module = 'work\Supplier';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? SupplierLib::getPropertiesCreate(),
		   $propertiesUpdate ?? SupplierLib::getPropertiesUpdate()
		);
	}

}
?>