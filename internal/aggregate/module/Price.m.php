<?php
namespace aggregate;

abstract class PriceElement extends \Element {

	use \FilterElement;

	private static ?PriceModel $model = NULL;

	public static function getSelection(): array {
		return Price::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): PriceModel {
		if(self::$model === NULL) {
			self::$model = new PriceModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Price::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class PriceModel extends \ModuleModel {

	protected string $module = 'aggregate\Price';
	protected string $package = 'aggregate';
	protected string $table = 'aggregatePrice';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'week' => ['int8', 'min' => 1, 'max' => 52, 'cast' => 'int'],
			'plant' => ['element32', 'plant\Plant', 'cast' => 'element'],
			'unit' => ['element32', 'product\Unit', 'cast' => 'element'],
			'contributors' => ['int16', 'min' => 1, 'max' => NULL, 'cast' => 'int'],
			'median' => ['decimal', 'digits' => 10, 'decimal' => 4, 'min' => -999999.9999, 'max' => 999999.9999, 'cast' => 'float'],
			'low' => ['decimal', 'digits' => 10, 'decimal' => 4, 'min' => -999999.9999, 'max' => 999999.9999, 'cast' => 'float'],
			'high' => ['decimal', 'digits' => 10, 'decimal' => 4, 'min' => -999999.9999, 'max' => 999999.9999, 'cast' => 'float'],
			'quantityMedian' => ['decimal', 'digits' => 8, 'decimal' => 2, 'min' => -999999.99, 'max' => 999999.99, 'null' => TRUE, 'cast' => 'float'],
			'online' => ['bool', 'cast' => 'bool'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'week', 'plant', 'unit', 'contributors', 'median', 'low', 'high', 'quantityMedian', 'online'
		]);

		$this->propertiesToModule += [
			'plant' => 'plant\Plant',
			'unit' => 'product\Unit',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['week', 'online'],
			['plant', 'online'],
			['online']
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['week', 'plant', 'unit']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'online' :
				return FALSE;

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function select(...$fields): PriceModel {
		return parent::select(...$fields);
	}

	public function where(...$data): PriceModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): PriceModel {
		return $this->where('id', ...$data);
	}

	public function whereWeek(...$data): PriceModel {
		return $this->where('week', ...$data);
	}

	public function wherePlant(...$data): PriceModel {
		return $this->where('plant', ...$data);
	}

	public function whereUnit(...$data): PriceModel {
		return $this->where('unit', ...$data);
	}

	public function whereContributors(...$data): PriceModel {
		return $this->where('contributors', ...$data);
	}

	public function whereMedian(...$data): PriceModel {
		return $this->where('median', ...$data);
	}

	public function whereLow(...$data): PriceModel {
		return $this->where('low', ...$data);
	}

	public function whereHigh(...$data): PriceModel {
		return $this->where('high', ...$data);
	}

	public function whereQuantityMedian(...$data): PriceModel {
		return $this->where('quantityMedian', ...$data);
	}

	public function whereOnline(...$data): PriceModel {
		return $this->where('online', ...$data);
	}


}


abstract class PriceCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Price {

		$e = new Price();

		if(empty($id)) {
			Price::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Price::getSelection();
		}

		if(Price::model()
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
			$properties = Price::getSelection();
		}

		if($sort !== NULL) {
			Price::model()->sort($sort);
		}

		return Price::model()
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

	public static function getNewElement(array $properties = []): Price {

		return new Price($properties);

	}

	public static function create(Price $e): void {

		Price::model()->insert($e);

	}

	public static function update(Price $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Price::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Price $e, array $properties): void {

		self::$affected = Price::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Price $e): void {

		$e->expects(['id']);

		self::$affected = Price::model()->delete($e);

	}

}


class PricePage extends \ModulePage {

	protected string $module = 'aggregate\Price';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? PriceAiLib::getPropertiesCreate(),
		   $propertiesUpdate ?? PriceAiLib::getPropertiesUpdate()
		);
	}

}
?>