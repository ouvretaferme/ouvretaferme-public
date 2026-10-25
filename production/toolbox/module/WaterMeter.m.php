<?php
namespace toolbox;

abstract class WaterMeterElement extends \Element {

	use \FilterElement;

	private static ?WaterMeterModel $model = NULL;

	const ACTIVE = 'active';
	const INACTIVE = 'inactive';

	public static function getSelection(): array {
		return WaterMeter::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): WaterMeterModel {
		if(self::$model === NULL) {
			self::$model = new WaterMeterModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('WaterMeter::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class WaterMeterModel extends \ModuleModel {

	protected string $module = 'toolbox\WaterMeter';
	protected string $package = 'toolbox';
	protected string $table = 'toolboxWaterMeter';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'name' => ['text8', 'min' => 1, 'max' => 50, 'collate' => 'general', 'cast' => 'string'],
			'digits' => ['int8', 'min' => 4, 'max' => 9, 'cast' => 'int'],
			'readingSkippedDate' => ['date', 'null' => TRUE, 'cast' => 'string'],
			'readingPostponedDate' => ['date', 'null' => TRUE, 'cast' => 'string'],
			'status' => ['enum', [\toolbox\WaterMeter::ACTIVE, \toolbox\WaterMeter::INACTIVE], 'cast' => 'enum'],
			'createdAt' => ['datetime', 'cast' => 'string'],
			'createdBy' => ['element32', 'user\User', 'cast' => 'element'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'name', 'digits', 'readingSkippedDate', 'readingPostponedDate', 'status', 'createdAt', 'createdBy'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
			'createdBy' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['structure', 'status']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'digits' :
				return 5;

			case 'status' :
				return WaterMeter::ACTIVE;

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

	public function select(...$fields): WaterMeterModel {
		return parent::select(...$fields);
	}

	public function where(...$data): WaterMeterModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): WaterMeterModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): WaterMeterModel {
		return $this->where('structure', ...$data);
	}

	public function whereName(...$data): WaterMeterModel {
		return $this->where('name', ...$data);
	}

	public function whereDigits(...$data): WaterMeterModel {
		return $this->where('digits', ...$data);
	}

	public function whereReadingSkippedDate(...$data): WaterMeterModel {
		return $this->where('readingSkippedDate', ...$data);
	}

	public function whereReadingPostponedDate(...$data): WaterMeterModel {
		return $this->where('readingPostponedDate', ...$data);
	}

	public function whereStatus(...$data): WaterMeterModel {
		return $this->where('status', ...$data);
	}

	public function whereCreatedAt(...$data): WaterMeterModel {
		return $this->where('createdAt', ...$data);
	}

	public function whereCreatedBy(...$data): WaterMeterModel {
		return $this->where('createdBy', ...$data);
	}


}


abstract class WaterMeterCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): WaterMeter {

		$e = new WaterMeter();

		if(empty($id)) {
			WaterMeter::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = WaterMeter::getSelection();
		}

		if(WaterMeter::model()
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
			$properties = WaterMeter::getSelection();
		}

		if($sort !== NULL) {
			WaterMeter::model()->sort($sort);
		}

		return WaterMeter::model()
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

	public static function getNewElement(array $properties = []): WaterMeter {

		return new WaterMeter($properties);

	}

	public static function create(WaterMeter $e): void {

		WaterMeter::model()->insert($e);

	}

	public static function update(WaterMeter $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = WaterMeter::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, WaterMeter $e, array $properties): void {

		self::$affected = WaterMeter::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(WaterMeter $e): void {

		$e->expects(['id']);

		self::$affected = WaterMeter::model()->delete($e);

	}

}


class WaterMeterPage extends \ModulePage {

	protected string $module = 'toolbox\WaterMeter';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? WaterMeterLib::getPropertiesCreate(),
		   $propertiesUpdate ?? WaterMeterLib::getPropertiesUpdate()
		);
	}

}
?>