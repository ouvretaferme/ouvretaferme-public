<?php
namespace toolbox;

abstract class GaugeElement extends \Element {

	use \FilterElement;

	private static ?GaugeModel $model = NULL;

	const ACTIVE = 'active';
	const INACTIVE = 'inactive';

	public static function getSelection(): array {
		return Gauge::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): GaugeModel {
		if(self::$model === NULL) {
			self::$model = new GaugeModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Gauge::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class GaugeModel extends \ModuleModel {

	protected string $module = 'toolbox\Gauge';
	protected string $package = 'toolbox';
	protected string $table = 'toolboxGauge';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'zone' => ['element32', 'map\Zone', 'null' => TRUE, 'cast' => 'element'],
			'status' => ['enum', [\toolbox\Gauge::ACTIVE, \toolbox\Gauge::INACTIVE], 'cast' => 'enum'],
			'createdAt' => ['datetime', 'cast' => 'string'],
			'createdBy' => ['element32', 'user\User', 'null' => TRUE, 'cast' => 'element'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'zone', 'status', 'createdAt', 'createdBy'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
			'zone' => 'map\Zone',
			'createdBy' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['structure', 'status']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'status' :
				return Gauge::ACTIVE;

			case 'createdAt' :
				return new \Sql('NOW()');

			case 'createdBy' :
				return \user\ConnectionLib::getOnline();

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'status' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): GaugeModel {
		return parent::select(...$fields);
	}

	public function where(...$data): GaugeModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): GaugeModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): GaugeModel {
		return $this->where('structure', ...$data);
	}

	public function whereZone(...$data): GaugeModel {
		return $this->where('zone', ...$data);
	}

	public function whereStatus(...$data): GaugeModel {
		return $this->where('status', ...$data);
	}

	public function whereCreatedAt(...$data): GaugeModel {
		return $this->where('createdAt', ...$data);
	}

	public function whereCreatedBy(...$data): GaugeModel {
		return $this->where('createdBy', ...$data);
	}


}


abstract class GaugeCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Gauge {

		$e = new Gauge();

		if(empty($id)) {
			Gauge::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Gauge::getSelection();
		}

		if(Gauge::model()
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
			$properties = Gauge::getSelection();
		}

		if($sort !== NULL) {
			Gauge::model()->sort($sort);
		}

		return Gauge::model()
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

	public static function getNewElement(array $properties = []): Gauge {

		return new Gauge($properties);

	}

	public static function create(Gauge $e): void {

		Gauge::model()->insert($e);

	}

	public static function update(Gauge $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Gauge::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Gauge $e, array $properties): void {

		self::$affected = Gauge::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Gauge $e): void {

		$e->expects(['id']);

		self::$affected = Gauge::model()->delete($e);

	}

}


class GaugePage extends \ModulePage {

	protected string $module = 'toolbox\Gauge';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? GaugeLib::getPropertiesCreate(),
		   $propertiesUpdate ?? GaugeLib::getPropertiesUpdate()
		);
	}

}
?>