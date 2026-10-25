<?php
namespace toolbox;

abstract class WaterReadingElement extends \Element {

	use \FilterElement;

	private static ?WaterReadingModel $model = NULL;

	public static function getSelection(): array {
		return WaterReading::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): WaterReadingModel {
		if(self::$model === NULL) {
			self::$model = new WaterReadingModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('WaterReading::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class WaterReadingModel extends \ModuleModel {

	protected string $module = 'toolbox\WaterReading';
	protected string $package = 'toolbox';
	protected string $table = 'toolboxWaterReading';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'meter' => ['element32', 'toolbox\WaterMeter', 'cast' => 'element'],
			'date' => ['date', 'cast' => 'string'],
			'value' => ['decimal', 'digits' => 10, 'decimal' => 1, 'min' => 0.0, 'max' => 999999999.9, 'cast' => 'float'],
			'changed' => ['bool', 'cast' => 'bool'],
			'changedValue' => ['decimal', 'digits' => 10, 'decimal' => 1, 'min' => 0.0, 'max' => 999999999.9, 'null' => TRUE, 'cast' => 'float'],
			'changedDigits' => ['int8', 'min' => 4, 'max' => 9, 'null' => TRUE, 'cast' => 'int'],
			'createdAt' => ['datetime', 'cast' => 'string'],
			'createdBy' => ['element32', 'user\User', 'cast' => 'element'],
			'updatedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'meter', 'date', 'value', 'changed', 'changedValue', 'changedDigits', 'createdAt', 'createdBy', 'updatedAt'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
			'meter' => 'toolbox\WaterMeter',
			'createdBy' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['structure']
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['meter', 'date']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'changed' :
				return FALSE;

			case 'createdAt' :
				return new \Sql('NOW()');

			case 'createdBy' :
				return \user\ConnectionLib::getOnline();

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function select(...$fields): WaterReadingModel {
		return parent::select(...$fields);
	}

	public function where(...$data): WaterReadingModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): WaterReadingModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): WaterReadingModel {
		return $this->where('structure', ...$data);
	}

	public function whereMeter(...$data): WaterReadingModel {
		return $this->where('meter', ...$data);
	}

	public function whereDate(...$data): WaterReadingModel {
		return $this->where('date', ...$data);
	}

	public function whereValue(...$data): WaterReadingModel {
		return $this->where('value', ...$data);
	}

	public function whereChanged(...$data): WaterReadingModel {
		return $this->where('changed', ...$data);
	}

	public function whereChangedValue(...$data): WaterReadingModel {
		return $this->where('changedValue', ...$data);
	}

	public function whereChangedDigits(...$data): WaterReadingModel {
		return $this->where('changedDigits', ...$data);
	}

	public function whereCreatedAt(...$data): WaterReadingModel {
		return $this->where('createdAt', ...$data);
	}

	public function whereCreatedBy(...$data): WaterReadingModel {
		return $this->where('createdBy', ...$data);
	}

	public function whereUpdatedAt(...$data): WaterReadingModel {
		return $this->where('updatedAt', ...$data);
	}


}


abstract class WaterReadingCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): WaterReading {

		$e = new WaterReading();

		if(empty($id)) {
			WaterReading::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = WaterReading::getSelection();
		}

		if(WaterReading::model()
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
			$properties = WaterReading::getSelection();
		}

		if($sort !== NULL) {
			WaterReading::model()->sort($sort);
		}

		return WaterReading::model()
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

	public static function getNewElement(array $properties = []): WaterReading {

		return new WaterReading($properties);

	}

	public static function create(WaterReading $e): void {

		WaterReading::model()->insert($e);

	}

	public static function update(WaterReading $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = WaterReading::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, WaterReading $e, array $properties): void {

		self::$affected = WaterReading::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(WaterReading $e): void {

		$e->expects(['id']);

		self::$affected = WaterReading::model()->delete($e);

	}

}


class WaterReadingPage extends \ModulePage {

	protected string $module = 'toolbox\WaterReading';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? WaterReadingLib::getPropertiesCreate(),
		   $propertiesUpdate ?? WaterReadingLib::getPropertiesUpdate()
		);
	}

}
?>