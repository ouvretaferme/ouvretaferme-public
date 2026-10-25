<?php
namespace series;

abstract class HistoryElement extends \Element {

	use \FilterElement;

	private static ?HistoryModel $model = NULL;

	const DONE = 'done';
	const TODO = 'todo';
	const TIME = 'time';
	const HARVEST = 'harvest';

	const KG = 'kg';
	const UNIT = 'unit';
	const BUNCH = 'bunch';

	public static function getSelection(): array {
		return History::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): HistoryModel {
		if(self::$model === NULL) {
			self::$model = new HistoryModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('History::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class HistoryModel extends \ModuleModel {

	protected string $module = 'series\History';
	protected string $package = 'series';
	protected string $table = 'seriesHistory';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'task' => ['element32', 'series\Task', 'cast' => 'element'],
			'event' => ['enum', [\series\History::DONE, \series\History::TODO, \series\History::TIME, \series\History::HARVEST], 'cast' => 'enum'],
			'worker' => ['element32', 'user\User', 'null' => TRUE, 'cast' => 'element'],
			'day' => ['date', 'null' => TRUE, 'cast' => 'string'],
			'time' => ['float32', 'null' => TRUE, 'cast' => 'float'],
			'harvest' => ['float32', 'null' => TRUE, 'cast' => 'float'],
			'harvestUnit' => ['enum', [\series\History::KG, \series\History::UNIT, \series\History::BUNCH], 'null' => TRUE, 'cast' => 'enum'],
			'date' => ['datetime', 'cast' => 'string'],
			'user' => ['element32', 'user\User', 'null' => TRUE, 'cast' => 'element'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'task', 'event', 'worker', 'day', 'time', 'harvest', 'harvestUnit', 'date', 'user'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
			'task' => 'series\Task',
			'worker' => 'user\User',
			'user' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['task']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'date' :
				return new \Sql('NOW()');

			case 'user' :
				return \user\ConnectionLib::getOnline();

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'event' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'harvestUnit' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): HistoryModel {
		return parent::select(...$fields);
	}

	public function where(...$data): HistoryModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): HistoryModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): HistoryModel {
		return $this->where('structure', ...$data);
	}

	public function whereTask(...$data): HistoryModel {
		return $this->where('task', ...$data);
	}

	public function whereEvent(...$data): HistoryModel {
		return $this->where('event', ...$data);
	}

	public function whereWorker(...$data): HistoryModel {
		return $this->where('worker', ...$data);
	}

	public function whereDay(...$data): HistoryModel {
		return $this->where('day', ...$data);
	}

	public function whereTime(...$data): HistoryModel {
		return $this->where('time', ...$data);
	}

	public function whereHarvest(...$data): HistoryModel {
		return $this->where('harvest', ...$data);
	}

	public function whereHarvestUnit(...$data): HistoryModel {
		return $this->where('harvestUnit', ...$data);
	}

	public function whereDate(...$data): HistoryModel {
		return $this->where('date', ...$data);
	}

	public function whereUser(...$data): HistoryModel {
		return $this->where('user', ...$data);
	}


}


abstract class HistoryCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): History {

		$e = new History();

		if(empty($id)) {
			History::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = History::getSelection();
		}

		if(History::model()
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
			$properties = History::getSelection();
		}

		if($sort !== NULL) {
			History::model()->sort($sort);
		}

		return History::model()
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

	public static function getNewElement(array $properties = []): History {

		return new History($properties);

	}

	public static function create(History $e): void {

		History::model()->insert($e);

	}

	public static function update(History $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = History::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, History $e, array $properties): void {

		self::$affected = History::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(History $e): void {

		$e->expects(['id']);

		self::$affected = History::model()->delete($e);

	}

}


class HistoryPage extends \ModulePage {

	protected string $module = 'series\History';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? HistoryLib::getPropertiesCreate(),
		   $propertiesUpdate ?? HistoryLib::getPropertiesUpdate()
		);
	}

}
?>