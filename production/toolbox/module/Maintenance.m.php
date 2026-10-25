<?php
namespace toolbox;

abstract class MaintenanceElement extends \Element {

	use \FilterElement;

	private static ?MaintenanceModel $model = NULL;

	const ACTIVE = 'active';
	const DONE = 'done';
	const DELETED = 'deleted';

	public static function getSelection(): array {
		return Maintenance::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): MaintenanceModel {
		if(self::$model === NULL) {
			self::$model = new MaintenanceModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Maintenance::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class MaintenanceModel extends \ModuleModel {

	protected string $module = 'toolbox\Maintenance';
	protected string $package = 'toolbox';
	protected string $table = 'toolboxMaintenance';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'equipment' => ['element32', 'toolbox\Equipment', 'cast' => 'element'],
			'name' => ['text8', 'min' => 1, 'max' => 60, 'collate' => 'general', 'cast' => 'string'],
			'everyValue' => ['int32', 'min' => 1, 'max' => 1000000, 'null' => TRUE, 'cast' => 'int'],
			'everyMonths' => ['int16', 'min' => 1, 'max' => 240, 'null' => TRUE, 'cast' => 'int'],
			'plannedDate' => ['date', 'null' => TRUE, 'cast' => 'string'],
			'plannedValue' => ['int32', 'min' => 0, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'ignoredDate' => ['date', 'null' => TRUE, 'cast' => 'string'],
			'postponedDate' => ['date', 'null' => TRUE, 'cast' => 'string'],
			'status' => ['enum', [\toolbox\Maintenance::ACTIVE, \toolbox\Maintenance::DONE, \toolbox\Maintenance::DELETED], 'cast' => 'enum'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'equipment', 'name', 'everyValue', 'everyMonths', 'plannedDate', 'plannedValue', 'ignoredDate', 'postponedDate', 'status', 'createdAt'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
			'equipment' => 'toolbox\Equipment',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['equipment'],
			['structure']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'status' :
				return Maintenance::ACTIVE;

			case 'createdAt' :
				return new \Sql('NOW()');

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

	public function select(...$fields): MaintenanceModel {
		return parent::select(...$fields);
	}

	public function where(...$data): MaintenanceModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): MaintenanceModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): MaintenanceModel {
		return $this->where('structure', ...$data);
	}

	public function whereEquipment(...$data): MaintenanceModel {
		return $this->where('equipment', ...$data);
	}

	public function whereName(...$data): MaintenanceModel {
		return $this->where('name', ...$data);
	}

	public function whereEveryValue(...$data): MaintenanceModel {
		return $this->where('everyValue', ...$data);
	}

	public function whereEveryMonths(...$data): MaintenanceModel {
		return $this->where('everyMonths', ...$data);
	}

	public function wherePlannedDate(...$data): MaintenanceModel {
		return $this->where('plannedDate', ...$data);
	}

	public function wherePlannedValue(...$data): MaintenanceModel {
		return $this->where('plannedValue', ...$data);
	}

	public function whereIgnoredDate(...$data): MaintenanceModel {
		return $this->where('ignoredDate', ...$data);
	}

	public function wherePostponedDate(...$data): MaintenanceModel {
		return $this->where('postponedDate', ...$data);
	}

	public function whereStatus(...$data): MaintenanceModel {
		return $this->where('status', ...$data);
	}

	public function whereCreatedAt(...$data): MaintenanceModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class MaintenanceCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Maintenance {

		$e = new Maintenance();

		if(empty($id)) {
			Maintenance::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Maintenance::getSelection();
		}

		if(Maintenance::model()
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
			$properties = Maintenance::getSelection();
		}

		if($sort !== NULL) {
			Maintenance::model()->sort($sort);
		}

		return Maintenance::model()
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

	public static function getNewElement(array $properties = []): Maintenance {

		return new Maintenance($properties);

	}

	public static function create(Maintenance $e): void {

		Maintenance::model()->insert($e);

	}

	public static function update(Maintenance $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Maintenance::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Maintenance $e, array $properties): void {

		self::$affected = Maintenance::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Maintenance $e): void {

		$e->expects(['id']);

		self::$affected = Maintenance::model()->delete($e);

	}

}


class MaintenancePage extends \ModulePage {

	protected string $module = 'toolbox\Maintenance';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? MaintenanceLib::getPropertiesCreate(),
		   $propertiesUpdate ?? MaintenanceLib::getPropertiesUpdate()
		);
	}

}
?>