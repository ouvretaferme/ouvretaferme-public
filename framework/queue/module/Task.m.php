<?php
namespace queue;

abstract class TaskElement extends \Element {

	use \FilterElement;

	private static ?TaskModel $model = NULL;

	const PENDING = 'pending';
	const RUNNING = 'running';
	const FAILED = 'failed';

	public static function getSelection(): array {
		return Task::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): TaskModel {
		if(self::$model === NULL) {
			self::$model = new TaskModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Task::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class TaskModel extends \ModuleModel {

	protected string $module = 'queue\Task';
	protected string $package = 'queue';
	protected string $table = 'queueTask';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'reference' => ['int32', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'action' => ['text8', 'min' => 1, 'max' => 50, 'charset' => 'ascii', 'cast' => 'string'],
			'element' => ['int32', 'min' => 0, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'payload' => ['json', 'cast' => 'array'],
			'status' => ['enum', [\queue\Task::PENDING, \queue\Task::RUNNING, \queue\Task::FAILED], 'cast' => 'enum'],
			'tries' => ['int8', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'runAt' => ['datetime', 'cast' => 'string'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'reference', 'action', 'element', 'payload', 'status', 'tries', 'runAt', 'createdAt'
		]);

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['action', 'status', 'runAt'],
			['status', 'runAt']
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['reference', 'action', 'element']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'payload' :
				return [];

			case 'status' :
				return Task::PENDING;

			case 'tries' :
				return 0;

			case 'runAt' :
				return new \Sql('NOW()');

			case 'createdAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'payload' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			case 'status' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'payload' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			default :
				return parent::decode($property, $value);

		}

	}

	public function select(...$fields): TaskModel {
		return parent::select(...$fields);
	}

	public function where(...$data): TaskModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): TaskModel {
		return $this->where('id', ...$data);
	}

	public function whereReference(...$data): TaskModel {
		return $this->where('reference', ...$data);
	}

	public function whereAction(...$data): TaskModel {
		return $this->where('action', ...$data);
	}

	public function whereElement(...$data): TaskModel {
		return $this->where('element', ...$data);
	}

	public function wherePayload(...$data): TaskModel {
		return $this->where('payload', ...$data);
	}

	public function whereStatus(...$data): TaskModel {
		return $this->where('status', ...$data);
	}

	public function whereTries(...$data): TaskModel {
		return $this->where('tries', ...$data);
	}

	public function whereRunAt(...$data): TaskModel {
		return $this->where('runAt', ...$data);
	}

	public function whereCreatedAt(...$data): TaskModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class TaskCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Task {

		$e = new Task();

		if(empty($id)) {
			Task::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Task::getSelection();
		}

		if(Task::model()
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
			$properties = Task::getSelection();
		}

		if($sort !== NULL) {
			Task::model()->sort($sort);
		}

		return Task::model()
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

	public static function getNewElement(array $properties = []): Task {

		return new Task($properties);

	}

	public static function create(Task $e): void {

		Task::model()->insert($e);

	}

	public static function update(Task $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Task::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Task $e, array $properties): void {

		self::$affected = Task::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Task $e): void {

		$e->expects(['id']);

		self::$affected = Task::model()->delete($e);

	}

}


class TaskPage extends \ModulePage {

	protected string $module = 'queue\Task';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? TaskLib::getPropertiesCreate(),
		   $propertiesUpdate ?? TaskLib::getPropertiesUpdate()
		);
	}

}
?>