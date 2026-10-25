<?php
namespace toolbox;

abstract class MaintenanceDoneElement extends \Element {

	use \FilterElement;

	private static ?MaintenanceDoneModel $model = NULL;

	public static function getSelection(): array {
		return MaintenanceDone::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): MaintenanceDoneModel {
		if(self::$model === NULL) {
			self::$model = new MaintenanceDoneModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('MaintenanceDone::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class MaintenanceDoneModel extends \ModuleModel {

	protected string $module = 'toolbox\MaintenanceDone';
	protected string $package = 'toolbox';
	protected string $table = 'toolboxMaintenanceDone';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'equipment' => ['element32', 'toolbox\Equipment', 'cast' => 'element'],
			'maintenance' => ['element32', 'toolbox\Maintenance', 'null' => TRUE, 'cast' => 'element'],
			'name' => ['text8', 'min' => 1, 'max' => 60, 'collate' => 'general', 'null' => TRUE, 'cast' => 'string'],
			'date' => ['date', 'cast' => 'string'],
			'value' => ['int32', 'min' => 0, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'cost' => ['decimal', 'digits' => 8, 'decimal' => 2, 'min' => 0.0, 'max' => 999999.99, 'null' => TRUE, 'cast' => 'float'],
			'comment' => ['text16', 'null' => TRUE, 'cast' => 'string'],
			'photo' => ['textFixed', 'min' => 20, 'max' => 20, 'charset' => 'ascii', 'null' => TRUE, 'cast' => 'string'],
			'createdAt' => ['datetime', 'cast' => 'string'],
			'createdBy' => ['element32', 'user\User', 'cast' => 'element'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'equipment', 'maintenance', 'name', 'date', 'value', 'cost', 'comment', 'photo', 'createdAt', 'createdBy'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
			'equipment' => 'toolbox\Equipment',
			'maintenance' => 'toolbox\Maintenance',
			'createdBy' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['equipment'],
			['maintenance'],
			['structure']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'createdAt' :
				return new \Sql('NOW()');

			case 'createdBy' :
				return \user\ConnectionLib::getOnline();

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function select(...$fields): MaintenanceDoneModel {
		return parent::select(...$fields);
	}

	public function where(...$data): MaintenanceDoneModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): MaintenanceDoneModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): MaintenanceDoneModel {
		return $this->where('structure', ...$data);
	}

	public function whereEquipment(...$data): MaintenanceDoneModel {
		return $this->where('equipment', ...$data);
	}

	public function whereMaintenance(...$data): MaintenanceDoneModel {
		return $this->where('maintenance', ...$data);
	}

	public function whereName(...$data): MaintenanceDoneModel {
		return $this->where('name', ...$data);
	}

	public function whereDate(...$data): MaintenanceDoneModel {
		return $this->where('date', ...$data);
	}

	public function whereValue(...$data): MaintenanceDoneModel {
		return $this->where('value', ...$data);
	}

	public function whereCost(...$data): MaintenanceDoneModel {
		return $this->where('cost', ...$data);
	}

	public function whereComment(...$data): MaintenanceDoneModel {
		return $this->where('comment', ...$data);
	}

	public function wherePhoto(...$data): MaintenanceDoneModel {
		return $this->where('photo', ...$data);
	}

	public function whereCreatedAt(...$data): MaintenanceDoneModel {
		return $this->where('createdAt', ...$data);
	}

	public function whereCreatedBy(...$data): MaintenanceDoneModel {
		return $this->where('createdBy', ...$data);
	}


}


abstract class MaintenanceDoneCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): MaintenanceDone {

		$e = new MaintenanceDone();

		if(empty($id)) {
			MaintenanceDone::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = MaintenanceDone::getSelection();
		}

		if(MaintenanceDone::model()
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
			$properties = MaintenanceDone::getSelection();
		}

		if($sort !== NULL) {
			MaintenanceDone::model()->sort($sort);
		}

		return MaintenanceDone::model()
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

	public static function getNewElement(array $properties = []): MaintenanceDone {

		return new MaintenanceDone($properties);

	}

	public static function create(MaintenanceDone $e): void {

		MaintenanceDone::model()->insert($e);

	}

	public static function update(MaintenanceDone $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = MaintenanceDone::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, MaintenanceDone $e, array $properties): void {

		self::$affected = MaintenanceDone::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(MaintenanceDone $e): void {

		$e->expects(['id']);

		self::$affected = MaintenanceDone::model()->delete($e);

	}

}


class MaintenanceDonePage extends \ModulePage {

	protected string $module = 'toolbox\MaintenanceDone';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? MaintenanceDoneLib::getPropertiesCreate(),
		   $propertiesUpdate ?? MaintenanceDoneLib::getPropertiesUpdate()
		);
	}

}
?>