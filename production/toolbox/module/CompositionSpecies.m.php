<?php
namespace toolbox;

abstract class CompositionSpeciesElement extends \Element {

	use \FilterElement;

	private static ?CompositionSpeciesModel $model = NULL;

	const NATURAL = 'natural';
	const CUSTOM = 'custom';
	const EXCLUDED = 'excluded';
	const CULTIVATION = 'cultivation';
	const CALENDAR = 'calendar';

	const BASE = 'base';
	const MEDIUM = 'medium';
	const LOW = 'low';
	const DIVERSIFICATION = 'diversification';

	public static function getSelection(): array {
		return CompositionSpecies::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): CompositionSpeciesModel {
		if(self::$model === NULL) {
			self::$model = new CompositionSpeciesModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('CompositionSpecies::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class CompositionSpeciesModel extends \ModuleModel {

	protected string $module = 'toolbox\CompositionSpecies';
	protected string $package = 'toolbox';
	protected string $table = 'toolboxCompositionSpecies';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'plan' => ['element32', 'toolbox\CompositionPlan', 'cast' => 'element'],
			'plant' => ['element32', 'plant\Plant', 'cast' => 'element'],
			'name' => ['text8', 'min' => 1, 'max' => NULL, 'collate' => 'general', 'null' => TRUE, 'cast' => 'string'],
			'status' => ['enum', [\toolbox\CompositionSpecies::NATURAL, \toolbox\CompositionSpecies::CUSTOM, \toolbox\CompositionSpecies::EXCLUDED, \toolbox\CompositionSpecies::CULTIVATION, \toolbox\CompositionSpecies::CALENDAR], 'cast' => 'enum'],
			'windowWeeks' => ['json', 'null' => TRUE, 'cast' => 'array'],
			'unit' => ['element32', 'product\Unit', 'null' => TRUE, 'cast' => 'element'],
			'price' => ['decimal', 'digits' => 8, 'decimal' => 2, 'min' => 0.01, 'max' => 999999.99, 'null' => TRUE, 'cast' => 'float'],
			'step' => ['decimal', 'digits' => 8, 'decimal' => 2, 'min' => 0.01, 'max' => 999999.99, 'null' => TRUE, 'cast' => 'float'],
			'minimum' => ['decimal', 'digits' => 8, 'decimal' => 2, 'min' => 0.01, 'max' => 999999.99, 'null' => TRUE, 'cast' => 'float'],
			'quantity' => ['decimal', 'digits' => 8, 'decimal' => 2, 'min' => 0.01, 'max' => 999999.99, 'null' => TRUE, 'cast' => 'float'],
			'quantityBasket' => ['decimal', 'digits' => 8, 'decimal' => 2, 'min' => 0.01, 'max' => 999999.99, 'null' => TRUE, 'cast' => 'float'],
			'weight' => ['decimal', 'digits' => 8, 'decimal' => 3, 'min' => 0.001, 'max' => 99999.999, 'null' => TRUE, 'cast' => 'float'],
			'frequency' => ['enum', [\toolbox\CompositionSpecies::BASE, \toolbox\CompositionSpecies::MEDIUM, \toolbox\CompositionSpecies::LOW, \toolbox\CompositionSpecies::DIVERSIFICATION], 'null' => TRUE, 'cast' => 'enum'],
			'product' => ['element32', 'product\Product', 'null' => TRUE, 'cast' => 'element'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'plan', 'plant', 'name', 'status', 'windowWeeks', 'unit', 'price', 'step', 'minimum', 'quantity', 'quantityBasket', 'weight', 'frequency', 'product'
		]);

		$this->propertiesToModule += [
			'plan' => 'toolbox\CompositionPlan',
			'plant' => 'plant\Plant',
			'unit' => 'product\Unit',
			'product' => 'product\Product',
		];

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['plan', 'plant', 'name']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'status' :
				return CompositionSpecies::NATURAL;

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'status' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'windowWeeks' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			case 'frequency' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'windowWeeks' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			default :
				return parent::decode($property, $value);

		}

	}

	public function select(...$fields): CompositionSpeciesModel {
		return parent::select(...$fields);
	}

	public function where(...$data): CompositionSpeciesModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): CompositionSpeciesModel {
		return $this->where('id', ...$data);
	}

	public function wherePlan(...$data): CompositionSpeciesModel {
		return $this->where('plan', ...$data);
	}

	public function wherePlant(...$data): CompositionSpeciesModel {
		return $this->where('plant', ...$data);
	}

	public function whereName(...$data): CompositionSpeciesModel {
		return $this->where('name', ...$data);
	}

	public function whereStatus(...$data): CompositionSpeciesModel {
		return $this->where('status', ...$data);
	}

	public function whereWindowWeeks(...$data): CompositionSpeciesModel {
		return $this->where('windowWeeks', ...$data);
	}

	public function whereUnit(...$data): CompositionSpeciesModel {
		return $this->where('unit', ...$data);
	}

	public function wherePrice(...$data): CompositionSpeciesModel {
		return $this->where('price', ...$data);
	}

	public function whereStep(...$data): CompositionSpeciesModel {
		return $this->where('step', ...$data);
	}

	public function whereMinimum(...$data): CompositionSpeciesModel {
		return $this->where('minimum', ...$data);
	}

	public function whereQuantity(...$data): CompositionSpeciesModel {
		return $this->where('quantity', ...$data);
	}

	public function whereQuantityBasket(...$data): CompositionSpeciesModel {
		return $this->where('quantityBasket', ...$data);
	}

	public function whereWeight(...$data): CompositionSpeciesModel {
		return $this->where('weight', ...$data);
	}

	public function whereFrequency(...$data): CompositionSpeciesModel {
		return $this->where('frequency', ...$data);
	}

	public function whereProduct(...$data): CompositionSpeciesModel {
		return $this->where('product', ...$data);
	}


}


abstract class CompositionSpeciesCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): CompositionSpecies {

		$e = new CompositionSpecies();

		if(empty($id)) {
			CompositionSpecies::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = CompositionSpecies::getSelection();
		}

		if(CompositionSpecies::model()
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
			$properties = CompositionSpecies::getSelection();
		}

		if($sort !== NULL) {
			CompositionSpecies::model()->sort($sort);
		}

		return CompositionSpecies::model()
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

	public static function getNewElement(array $properties = []): CompositionSpecies {

		return new CompositionSpecies($properties);

	}

	public static function create(CompositionSpecies $e): void {

		CompositionSpecies::model()->insert($e);

	}

	public static function update(CompositionSpecies $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = CompositionSpecies::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, CompositionSpecies $e, array $properties): void {

		self::$affected = CompositionSpecies::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(CompositionSpecies $e): void {

		$e->expects(['id']);

		self::$affected = CompositionSpecies::model()->delete($e);

	}

}


class CompositionSpeciesPage extends \ModulePage {

	protected string $module = 'toolbox\CompositionSpecies';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? CompositionSpeciesLib::getPropertiesCreate(),
		   $propertiesUpdate ?? CompositionSpeciesLib::getPropertiesUpdate()
		);
	}

}
?>