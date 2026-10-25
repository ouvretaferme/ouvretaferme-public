<?php
namespace aggregate;

abstract class SaleTotalElement extends \Element {

	use \FilterElement;

	private static ?SaleTotalModel $model = NULL;

	public static function getSelection(): array {
		return SaleTotal::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): SaleTotalModel {
		if(self::$model === NULL) {
			self::$model = new SaleTotalModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('SaleTotal::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class SaleTotalModel extends \ModuleModel {

	protected string $module = 'aggregate\SaleTotal';
	protected string $package = 'aggregate';
	protected string $table = 'aggregateSaleTotal';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'plant' => ['element32', 'plant\Plant', 'null' => TRUE, 'cast' => 'element'],
			'month' => ['int8', 'min' => 1, 'max' => 12, 'null' => TRUE, 'cast' => 'int'],
			'contributors' => ['int16', 'min' => 1, 'max' => NULL, 'cast' => 'int'],
			'amount' => ['decimal', 'digits' => 12, 'decimal' => 2, 'min' => -9999999999.99, 'max' => 9999999999.99, 'cast' => 'float'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'plant', 'month', 'contributors', 'amount'
		]);

		$this->propertiesToModule += [
			'plant' => 'plant\Plant',
		];

	}

	public function select(...$fields): SaleTotalModel {
		return parent::select(...$fields);
	}

	public function where(...$data): SaleTotalModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): SaleTotalModel {
		return $this->where('id', ...$data);
	}

	public function wherePlant(...$data): SaleTotalModel {
		return $this->where('plant', ...$data);
	}

	public function whereMonth(...$data): SaleTotalModel {
		return $this->where('month', ...$data);
	}

	public function whereContributors(...$data): SaleTotalModel {
		return $this->where('contributors', ...$data);
	}

	public function whereAmount(...$data): SaleTotalModel {
		return $this->where('amount', ...$data);
	}


}


abstract class SaleTotalCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): SaleTotal {

		$e = new SaleTotal();

		if(empty($id)) {
			SaleTotal::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = SaleTotal::getSelection();
		}

		if(SaleTotal::model()
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
			$properties = SaleTotal::getSelection();
		}

		if($sort !== NULL) {
			SaleTotal::model()->sort($sort);
		}

		return SaleTotal::model()
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

	public static function getNewElement(array $properties = []): SaleTotal {

		return new SaleTotal($properties);

	}

	public static function create(SaleTotal $e): void {

		SaleTotal::model()->insert($e);

	}

	public static function update(SaleTotal $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = SaleTotal::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, SaleTotal $e, array $properties): void {

		self::$affected = SaleTotal::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(SaleTotal $e): void {

		$e->expects(['id']);

		self::$affected = SaleTotal::model()->delete($e);

	}

}


class SaleTotalPage extends \ModulePage {

	protected string $module = 'aggregate\SaleTotal';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? SaleTotalAiLib::getPropertiesCreate(),
		   $propertiesUpdate ?? SaleTotalAiLib::getPropertiesUpdate()
		);
	}

}
?>