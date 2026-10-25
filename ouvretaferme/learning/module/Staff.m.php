<?php
namespace learning;

abstract class StaffElement extends \Element {

	use \FilterElement;

	private static ?StaffModel $model = NULL;

	public static function getSelection(): array {
		return Staff::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): StaffModel {
		if(self::$model === NULL) {
			self::$model = new StaffModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Staff::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class StaffModel extends \ModuleModel {

	protected string $module = 'learning\Staff';
	protected string $package = 'learning';
	protected string $table = 'learningStaff';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'course' => ['element32', 'learning\Course', 'cast' => 'element'],
			'supervisor' => ['element32', 'learning\Supervisor', 'cast' => 'element'],
			'role' => ['text8', 'min' => 1, 'max' => 100, 'cast' => 'string'],
			'contact' => ['bool', 'cast' => 'bool'],
			'position' => ['int8', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'course', 'supervisor', 'role', 'contact', 'position'
		]);

		$this->propertiesToModule += [
			'course' => 'learning\Course',
			'supervisor' => 'learning\Supervisor',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['supervisor']
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['course', 'supervisor']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'contact' :
				return FALSE;

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function select(...$fields): StaffModel {
		return parent::select(...$fields);
	}

	public function where(...$data): StaffModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): StaffModel {
		return $this->where('id', ...$data);
	}

	public function whereCourse(...$data): StaffModel {
		return $this->where('course', ...$data);
	}

	public function whereSupervisor(...$data): StaffModel {
		return $this->where('supervisor', ...$data);
	}

	public function whereRole(...$data): StaffModel {
		return $this->where('role', ...$data);
	}

	public function whereContact(...$data): StaffModel {
		return $this->where('contact', ...$data);
	}

	public function wherePosition(...$data): StaffModel {
		return $this->where('position', ...$data);
	}


}


abstract class StaffCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Staff {

		$e = new Staff();

		if(empty($id)) {
			Staff::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Staff::getSelection();
		}

		if(Staff::model()
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
			$properties = Staff::getSelection();
		}

		if($sort !== NULL) {
			Staff::model()->sort($sort);
		}

		return Staff::model()
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

	public static function getNewElement(array $properties = []): Staff {

		return new Staff($properties);

	}

	public static function create(Staff $e): void {

		Staff::model()->insert($e);

	}

	public static function update(Staff $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Staff::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Staff $e, array $properties): void {

		self::$affected = Staff::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Staff $e): void {

		$e->expects(['id']);

		self::$affected = Staff::model()->delete($e);

	}

}


class StaffPage extends \ModulePage {

	protected string $module = 'learning\Staff';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? StaffLib::getPropertiesCreate(),
		   $propertiesUpdate ?? StaffLib::getPropertiesUpdate()
		);
	}

}
?>