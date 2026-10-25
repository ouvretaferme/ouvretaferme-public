<?php
namespace toolbox;

abstract class FertilizationYieldElement extends \Element {

	use \FilterElement;

	private static ?FertilizationYieldModel $model = NULL;

	public static function getSelection(): array {
		return FertilizationYield::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): FertilizationYieldModel {
		if(self::$model === NULL) {
			self::$model = new FertilizationYieldModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('FertilizationYield::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class FertilizationYieldModel extends \ModuleModel {

	protected string $module = 'toolbox\FertilizationYield';
	protected string $package = 'toolbox';
	protected string $table = 'toolboxFertilizationYield';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'crop' => ['element32', 'toolbox\FertilizationCrop', 'cast' => 'element'],
			'name' => ['text8', 'null' => TRUE, 'cast' => 'string'],
			'yield' => ['decimal', 'digits' => 5, 'decimal' => 1, 'min' => 0, 'max' => 9999.9, 'null' => TRUE, 'cast' => 'float'],
			'nitrogen' => ['decimal', 'digits' => 5, 'decimal' => 1, 'min' => 0, 'max' => 9999.9, 'cast' => 'float'],
			'phosphate' => ['decimal', 'digits' => 5, 'decimal' => 1, 'min' => 0, 'max' => 9999.9, 'null' => TRUE, 'cast' => 'float'],
			'potash' => ['decimal', 'digits' => 5, 'decimal' => 1, 'min' => 0, 'max' => 9999.9, 'null' => TRUE, 'cast' => 'float'],
			'source' => ['element32', 'toolbox\FertilizationSource', 'cast' => 'element'],
			'position' => ['int8', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'crop', 'name', 'yield', 'nitrogen', 'phosphate', 'potash', 'source', 'position'
		]);

		$this->propertiesToModule += [
			'crop' => 'toolbox\FertilizationCrop',
			'source' => 'toolbox\FertilizationSource',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['crop']
		]);

	}

	public function select(...$fields): FertilizationYieldModel {
		return parent::select(...$fields);
	}

	public function where(...$data): FertilizationYieldModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): FertilizationYieldModel {
		return $this->where('id', ...$data);
	}

	public function whereCrop(...$data): FertilizationYieldModel {
		return $this->where('crop', ...$data);
	}

	public function whereName(...$data): FertilizationYieldModel {
		return $this->where('name', ...$data);
	}

	public function whereYield(...$data): FertilizationYieldModel {
		return $this->where('yield', ...$data);
	}

	public function whereNitrogen(...$data): FertilizationYieldModel {
		return $this->where('nitrogen', ...$data);
	}

	public function wherePhosphate(...$data): FertilizationYieldModel {
		return $this->where('phosphate', ...$data);
	}

	public function wherePotash(...$data): FertilizationYieldModel {
		return $this->where('potash', ...$data);
	}

	public function whereSource(...$data): FertilizationYieldModel {
		return $this->where('source', ...$data);
	}

	public function wherePosition(...$data): FertilizationYieldModel {
		return $this->where('position', ...$data);
	}


}


abstract class FertilizationYieldCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): FertilizationYield {

		$e = new FertilizationYield();

		if(empty($id)) {
			FertilizationYield::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = FertilizationYield::getSelection();
		}

		if(FertilizationYield::model()
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
			$properties = FertilizationYield::getSelection();
		}

		if($sort !== NULL) {
			FertilizationYield::model()->sort($sort);
		}

		return FertilizationYield::model()
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

	public static function getNewElement(array $properties = []): FertilizationYield {

		return new FertilizationYield($properties);

	}

	public static function create(FertilizationYield $e): void {

		FertilizationYield::model()->insert($e);

	}

	public static function update(FertilizationYield $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = FertilizationYield::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, FertilizationYield $e, array $properties): void {

		self::$affected = FertilizationYield::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(FertilizationYield $e): void {

		$e->expects(['id']);

		self::$affected = FertilizationYield::model()->delete($e);

	}

}


class FertilizationYieldPage extends \ModulePage {

	protected string $module = 'toolbox\FertilizationYield';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? FertilizationYieldLib::getPropertiesCreate(),
		   $propertiesUpdate ?? FertilizationYieldLib::getPropertiesUpdate()
		);
	}

}
?>