<?php
namespace aggregate;

abstract class SaleElement extends \Element {

	use \FilterElement;

	private static ?SaleModel $model = NULL;

	public static function getSelection(): array {
		return Sale::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): SaleModel {
		if(self::$model === NULL) {
			self::$model = new SaleModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Sale::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class SaleModel extends \ModuleModel {

	protected string $module = 'aggregate\Sale';
	protected string $package = 'aggregate';
	protected string $table = 'aggregateSale';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'plant' => ['element32', 'plant\Plant', 'cast' => 'element'],
			'month' => ['int8', 'min' => 1, 'max' => 12, 'cast' => 'int'],
			'contributors' => ['int16', 'min' => 1, 'max' => NULL, 'cast' => 'int'],
			'amount' => ['decimal', 'digits' => 12, 'decimal' => 2, 'min' => -9999999999.99, 'max' => 9999999999.99, 'cast' => 'float'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'plant', 'month', 'contributors', 'amount'
		]);

		$this->propertiesToModule += [
			'plant' => 'plant\Plant',
		];

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['plant', 'month']
		]);

	}

	public function select(...$fields): SaleModel {
		return parent::select(...$fields);
	}

	public function where(...$data): SaleModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): SaleModel {
		return $this->where('id', ...$data);
	}

	public function wherePlant(...$data): SaleModel {
		return $this->where('plant', ...$data);
	}

	public function whereMonth(...$data): SaleModel {
		return $this->where('month', ...$data);
	}

	public function whereContributors(...$data): SaleModel {
		return $this->where('contributors', ...$data);
	}

	public function whereAmount(...$data): SaleModel {
		return $this->where('amount', ...$data);
	}


}


abstract class SaleCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Sale {

		$e = new Sale();

		if(empty($id)) {
			Sale::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Sale::getSelection();
		}

		if(Sale::model()
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
			$properties = Sale::getSelection();
		}

		if($sort !== NULL) {
			Sale::model()->sort($sort);
		}

		return Sale::model()
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

	public static function getNewElement(array $properties = []): Sale {

		return new Sale($properties);

	}

	public static function create(Sale $e): void {

		Sale::model()->insert($e);

	}

	public static function update(Sale $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Sale::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Sale $e, array $properties): void {

		self::$affected = Sale::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Sale $e): void {

		$e->expects(['id']);

		self::$affected = Sale::model()->delete($e);

	}

}


class SalePage extends \ModulePage {

	protected string $module = 'aggregate\Sale';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? SaleAiLib::getPropertiesCreate(),
		   $propertiesUpdate ?? SaleAiLib::getPropertiesUpdate()
		);
	}

}
?>