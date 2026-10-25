<?php
namespace toolbox;

abstract class CompositionLineElement extends \Element {

	use \FilterElement;

	private static ?CompositionLineModel $model = NULL;

	const PROPOSED = 'proposed';
	const USER = 'user';

	public static function getSelection(): array {
		return CompositionLine::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): CompositionLineModel {
		if(self::$model === NULL) {
			self::$model = new CompositionLineModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('CompositionLine::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class CompositionLineModel extends \ModuleModel {

	protected string $module = 'toolbox\CompositionLine';
	protected string $package = 'toolbox';
	protected string $table = 'toolboxCompositionLine';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'week' => ['element32', 'toolbox\CompositionWeek', 'cast' => 'element'],
			'species' => ['element32', 'toolbox\CompositionSpecies', 'cast' => 'element'],
			'unit' => ['element32', 'product\Unit', 'cast' => 'element'],
			'quantity' => ['decimal', 'digits' => 8, 'decimal' => 2, 'min' => 0.01, 'max' => 999999.99, 'cast' => 'float'],
			'quantityProposed' => ['decimal', 'digits' => 8, 'decimal' => 2, 'min' => -999999.99, 'max' => 999999.99, 'null' => TRUE, 'cast' => 'float'],
			'unitPrice' => ['decimal', 'digits' => 10, 'decimal' => 4, 'min' => -999999.9999, 'max' => 999999.9999, 'null' => TRUE, 'cast' => 'float'],
			'origin' => ['enum', [\toolbox\CompositionLine::PROPOSED, \toolbox\CompositionLine::USER], 'cast' => 'enum'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'week', 'species', 'unit', 'quantity', 'quantityProposed', 'unitPrice', 'origin'
		]);

		$this->propertiesToModule += [
			'week' => 'toolbox\CompositionWeek',
			'species' => 'toolbox\CompositionSpecies',
			'unit' => 'product\Unit',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['species']
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['week', 'species']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'origin' :
				return CompositionLine::PROPOSED;

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'origin' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): CompositionLineModel {
		return parent::select(...$fields);
	}

	public function where(...$data): CompositionLineModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): CompositionLineModel {
		return $this->where('id', ...$data);
	}

	public function whereWeek(...$data): CompositionLineModel {
		return $this->where('week', ...$data);
	}

	public function whereSpecies(...$data): CompositionLineModel {
		return $this->where('species', ...$data);
	}

	public function whereUnit(...$data): CompositionLineModel {
		return $this->where('unit', ...$data);
	}

	public function whereQuantity(...$data): CompositionLineModel {
		return $this->where('quantity', ...$data);
	}

	public function whereQuantityProposed(...$data): CompositionLineModel {
		return $this->where('quantityProposed', ...$data);
	}

	public function whereUnitPrice(...$data): CompositionLineModel {
		return $this->where('unitPrice', ...$data);
	}

	public function whereOrigin(...$data): CompositionLineModel {
		return $this->where('origin', ...$data);
	}


}


abstract class CompositionLineCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): CompositionLine {

		$e = new CompositionLine();

		if(empty($id)) {
			CompositionLine::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = CompositionLine::getSelection();
		}

		if(CompositionLine::model()
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
			$properties = CompositionLine::getSelection();
		}

		if($sort !== NULL) {
			CompositionLine::model()->sort($sort);
		}

		return CompositionLine::model()
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

	public static function getNewElement(array $properties = []): CompositionLine {

		return new CompositionLine($properties);

	}

	public static function create(CompositionLine $e): void {

		CompositionLine::model()->insert($e);

	}

	public static function update(CompositionLine $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = CompositionLine::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, CompositionLine $e, array $properties): void {

		self::$affected = CompositionLine::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(CompositionLine $e): void {

		$e->expects(['id']);

		self::$affected = CompositionLine::model()->delete($e);

	}

}


class CompositionLinePage extends \ModulePage {

	protected string $module = 'toolbox\CompositionLine';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? CompositionLineLib::getPropertiesCreate(),
		   $propertiesUpdate ?? CompositionLineLib::getPropertiesUpdate()
		);
	}

}
?>