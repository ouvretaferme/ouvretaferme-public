<?php
namespace learning;

abstract class ProgressElement extends \Element {

	use \FilterElement;

	private static ?ProgressModel $model = NULL;

	const PENDING = 'pending';
	const DONE = 'done';

	const DECLARED = 'declared';
	const QUIZ = 'quiz';
	const DELIVERABLE = 'deliverable';
	const PRESENCE = 'presence';

	public static function getSelection(): array {
		return Progress::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): ProgressModel {
		if(self::$model === NULL) {
			self::$model = new ProgressModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Progress::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class ProgressModel extends \ModuleModel {

	protected string $module = 'learning\Progress';
	protected string $package = 'learning';
	protected string $table = 'learningProgress';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'user' => ['element32', 'user\User', 'cast' => 'element'],
			'course' => ['element32', 'learning\Course', 'cast' => 'element'],
			'lesson' => ['element32', 'learning\Lesson', 'cast' => 'element'],
			'status' => ['enum', [\learning\Progress::PENDING, \learning\Progress::DONE], 'cast' => 'enum'],
			'source' => ['enum', [\learning\Progress::DECLARED, \learning\Progress::QUIZ, \learning\Progress::DELIVERABLE, \learning\Progress::PRESENCE], 'null' => TRUE, 'cast' => 'enum'],
			'score' => ['int8', 'min' => 0, 'max' => 100, 'null' => TRUE, 'cast' => 'int'],
			'attempts' => ['int16', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'doneAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'checkedBy' => ['element32', 'user\User', 'null' => TRUE, 'cast' => 'element'],
			'checkedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'createdAt' => ['datetime', 'cast' => 'string'],
			'updatedAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'user', 'course', 'lesson', 'status', 'source', 'score', 'attempts', 'doneAt', 'checkedBy', 'checkedAt', 'createdAt', 'updatedAt'
		]);

		$this->propertiesToModule += [
			'user' => 'user\User',
			'course' => 'learning\Course',
			'lesson' => 'learning\Lesson',
			'checkedBy' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['user', 'course'],
			['course']
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['user', 'lesson']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'status' :
				return Progress::PENDING;

			case 'attempts' :
				return 0;

			case 'createdAt' :
				return new \Sql('NOW()');

			case 'updatedAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'status' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'source' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): ProgressModel {
		return parent::select(...$fields);
	}

	public function where(...$data): ProgressModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): ProgressModel {
		return $this->where('id', ...$data);
	}

	public function whereUser(...$data): ProgressModel {
		return $this->where('user', ...$data);
	}

	public function whereCourse(...$data): ProgressModel {
		return $this->where('course', ...$data);
	}

	public function whereLesson(...$data): ProgressModel {
		return $this->where('lesson', ...$data);
	}

	public function whereStatus(...$data): ProgressModel {
		return $this->where('status', ...$data);
	}

	public function whereSource(...$data): ProgressModel {
		return $this->where('source', ...$data);
	}

	public function whereScore(...$data): ProgressModel {
		return $this->where('score', ...$data);
	}

	public function whereAttempts(...$data): ProgressModel {
		return $this->where('attempts', ...$data);
	}

	public function whereDoneAt(...$data): ProgressModel {
		return $this->where('doneAt', ...$data);
	}

	public function whereCheckedBy(...$data): ProgressModel {
		return $this->where('checkedBy', ...$data);
	}

	public function whereCheckedAt(...$data): ProgressModel {
		return $this->where('checkedAt', ...$data);
	}

	public function whereCreatedAt(...$data): ProgressModel {
		return $this->where('createdAt', ...$data);
	}

	public function whereUpdatedAt(...$data): ProgressModel {
		return $this->where('updatedAt', ...$data);
	}


}


abstract class ProgressCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Progress {

		$e = new Progress();

		if(empty($id)) {
			Progress::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Progress::getSelection();
		}

		if(Progress::model()
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
			$properties = Progress::getSelection();
		}

		if($sort !== NULL) {
			Progress::model()->sort($sort);
		}

		return Progress::model()
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

	public static function getNewElement(array $properties = []): Progress {

		return new Progress($properties);

	}

	public static function create(Progress $e): void {

		Progress::model()->insert($e);

	}

	public static function update(Progress $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Progress::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Progress $e, array $properties): void {

		self::$affected = Progress::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Progress $e): void {

		$e->expects(['id']);

		self::$affected = Progress::model()->delete($e);

	}

}


class ProgressPage extends \ModulePage {

	protected string $module = 'learning\Progress';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? ProgressLib::getPropertiesCreate(),
		   $propertiesUpdate ?? ProgressLib::getPropertiesUpdate()
		);
	}

}
?>