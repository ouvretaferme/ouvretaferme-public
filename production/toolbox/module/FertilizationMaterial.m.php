<?php
namespace toolbox;

abstract class FertilizationMaterialElement extends \Element {

	use \FilterElement;

	private static ?FertilizationMaterialModel $model = NULL;

	public static function getSelection(): array {
		return FertilizationMaterial::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): FertilizationMaterialModel {
		if(self::$model === NULL) {
			self::$model = new FertilizationMaterialModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('FertilizationMaterial::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class FertilizationMaterialModel extends \ModuleModel {

	protected string $module = 'toolbox\FertilizationMaterial';
	protected string $package = 'toolbox';
	protected string $table = 'toolboxFertilizationMaterial';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'name' => ['text8', 'min' => 1, 'max' => NULL, 'cast' => 'string'],
			'nitrogen' => ['decimal', 'digits' => 6, 'decimal' => 2, 'min' => 0, 'max' => 9999.99, 'null' => TRUE, 'cast' => 'float'],
			'phosphate' => ['decimal', 'digits' => 6, 'decimal' => 2, 'min' => 0, 'max' => 9999.99, 'null' => TRUE, 'cast' => 'float'],
			'potash' => ['decimal', 'digits' => 6, 'decimal' => 2, 'min' => 0, 'max' => 9999.99, 'null' => TRUE, 'cast' => 'float'],
			'availableNitrogen' => ['decimal', 'digits' => 4, 'decimal' => 3, 'min' => 0, 'max' => 1, 'null' => TRUE, 'cast' => 'float'],
			'availablePhosphate' => ['decimal', 'digits' => 3, 'decimal' => 2, 'min' => 0, 'max' => 1, 'null' => TRUE, 'cast' => 'float'],
			'source' => ['element32', 'toolbox\FertilizationSource', 'cast' => 'element'],
			'position' => ['int8', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'name', 'nitrogen', 'phosphate', 'potash', 'availableNitrogen', 'availablePhosphate', 'source', 'position'
		]);

		$this->propertiesToModule += [
			'source' => 'toolbox\FertilizationSource',
		];

	}

	public function select(...$fields): FertilizationMaterialModel {
		return parent::select(...$fields);
	}

	public function where(...$data): FertilizationMaterialModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): FertilizationMaterialModel {
		return $this->where('id', ...$data);
	}

	public function whereName(...$data): FertilizationMaterialModel {
		return $this->where('name', ...$data);
	}

	public function whereNitrogen(...$data): FertilizationMaterialModel {
		return $this->where('nitrogen', ...$data);
	}

	public function wherePhosphate(...$data): FertilizationMaterialModel {
		return $this->where('phosphate', ...$data);
	}

	public function wherePotash(...$data): FertilizationMaterialModel {
		return $this->where('potash', ...$data);
	}

	public function whereAvailableNitrogen(...$data): FertilizationMaterialModel {
		return $this->where('availableNitrogen', ...$data);
	}

	public function whereAvailablePhosphate(...$data): FertilizationMaterialModel {
		return $this->where('availablePhosphate', ...$data);
	}

	public function whereSource(...$data): FertilizationMaterialModel {
		return $this->where('source', ...$data);
	}

	public function wherePosition(...$data): FertilizationMaterialModel {
		return $this->where('position', ...$data);
	}


}


abstract class FertilizationMaterialCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): FertilizationMaterial {

		$e = new FertilizationMaterial();

		if(empty($id)) {
			FertilizationMaterial::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = FertilizationMaterial::getSelection();
		}

		if(FertilizationMaterial::model()
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
			$properties = FertilizationMaterial::getSelection();
		}

		if($sort !== NULL) {
			FertilizationMaterial::model()->sort($sort);
		}

		return FertilizationMaterial::model()
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

	public static function getNewElement(array $properties = []): FertilizationMaterial {

		return new FertilizationMaterial($properties);

	}

	public static function create(FertilizationMaterial $e): void {

		FertilizationMaterial::model()->insert($e);

	}

	public static function update(FertilizationMaterial $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = FertilizationMaterial::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, FertilizationMaterial $e, array $properties): void {

		self::$affected = FertilizationMaterial::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(FertilizationMaterial $e): void {

		$e->expects(['id']);

		self::$affected = FertilizationMaterial::model()->delete($e);

	}

}


class FertilizationMaterialPage extends \ModulePage {

	protected string $module = 'toolbox\FertilizationMaterial';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? FertilizationMaterialLib::getPropertiesCreate(),
		   $propertiesUpdate ?? FertilizationMaterialLib::getPropertiesUpdate()
		);
	}

}
?>