<?php
namespace hr;

abstract class WorkingTimeElement extends \Element {

	use \FilterElement;

	private static ?WorkingTimeModel $model = NULL;

	public static function getSelection(): array {
		return WorkingTime::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): WorkingTimeModel {
		if(self::$model === NULL) {
			self::$model = new WorkingTimeModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('WorkingTime::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class WorkingTimeModel extends \ModuleModel {

	protected string $module = 'hr\WorkingTime';
	protected string $package = 'hr';
	protected string $table = 'hrWorkingTime';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'user' => ['element32', 'user\User', 'cast' => 'element'],
			'date' => ['date', 'min' => NULL, 'max' => \Clock::date(), 'cast' => 'string'],
			'time' => ['float32', 'min' => 0.0, 'max' => NULL, 'cast' => 'float'],
			'start' => ['time', 'null' => TRUE, 'cast' => 'string'],
			'end' => ['time', 'null' => TRUE, 'cast' => 'string'],
			'pause' => ['float32', 'min' => 0.0, 'max' => 24.0, 'null' => TRUE, 'cast' => 'float'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'user', 'date', 'time', 'start', 'end', 'pause', 'createdAt'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
			'user' => 'user\User',
		];

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['structure', 'user', 'date']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'createdAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function select(...$fields): WorkingTimeModel {
		return parent::select(...$fields);
	}

	public function where(...$data): WorkingTimeModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): WorkingTimeModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): WorkingTimeModel {
		return $this->where('structure', ...$data);
	}

	public function whereUser(...$data): WorkingTimeModel {
		return $this->where('user', ...$data);
	}

	public function whereDate(...$data): WorkingTimeModel {
		return $this->where('date', ...$data);
	}

	public function whereTime(...$data): WorkingTimeModel {
		return $this->where('time', ...$data);
	}

	public function whereStart(...$data): WorkingTimeModel {
		return $this->where('start', ...$data);
	}

	public function whereEnd(...$data): WorkingTimeModel {
		return $this->where('end', ...$data);
	}

	public function wherePause(...$data): WorkingTimeModel {
		return $this->where('pause', ...$data);
	}

	public function whereCreatedAt(...$data): WorkingTimeModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class WorkingTimeCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): WorkingTime {

		$e = new WorkingTime();

		if(empty($id)) {
			WorkingTime::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = WorkingTime::getSelection();
		}

		if(WorkingTime::model()
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
			$properties = WorkingTime::getSelection();
		}

		if($sort !== NULL) {
			WorkingTime::model()->sort($sort);
		}

		return WorkingTime::model()
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

	public static function getNewElement(array $properties = []): WorkingTime {

		return new WorkingTime($properties);

	}

	public static function create(WorkingTime $e): void {

		WorkingTime::model()->insert($e);

	}

	public static function update(WorkingTime $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = WorkingTime::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, WorkingTime $e, array $properties): void {

		self::$affected = WorkingTime::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(WorkingTime $e): void {

		$e->expects(['id']);

		self::$affected = WorkingTime::model()->delete($e);

	}

}


class WorkingTimePage extends \ModulePage {

	protected string $module = 'hr\WorkingTime';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? WorkingTimeLib::getPropertiesCreate(),
		   $propertiesUpdate ?? WorkingTimeLib::getPropertiesUpdate()
		);
	}

}
?>