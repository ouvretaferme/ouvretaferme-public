<?php
namespace learning;

abstract class ReadingElement extends \Element {

	use \FilterElement;

	private static ?ReadingModel $model = NULL;

	public static function getSelection(): array {
		return Reading::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): ReadingModel {
		if(self::$model === NULL) {
			self::$model = new ReadingModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Reading::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class ReadingModel extends \ModuleModel {

	protected string $module = 'learning\Reading';
	protected string $package = 'learning';
	protected string $table = 'learningReading';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'user' => ['element32', 'user\User', 'cast' => 'element'],
			'course' => ['element32', 'learning\Course', 'cast' => 'element'],
			'lesson' => ['element32', 'learning\Lesson', 'cast' => 'element'],
			'startedAt' => ['datetime', 'cast' => 'string'],
			'seenAt' => ['datetime', 'cast' => 'string'],
			'seconds' => ['int32', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'user', 'course', 'lesson', 'startedAt', 'seenAt', 'seconds'
		]);

		$this->propertiesToModule += [
			'user' => 'user\User',
			'course' => 'learning\Course',
			'lesson' => 'learning\Lesson',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['course', 'user', 'startedAt'],
			['lesson']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'startedAt' :
				return new \Sql('NOW()');

			case 'seenAt' :
				return new \Sql('NOW()');

			case 'seconds' :
				return 0;

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function select(...$fields): ReadingModel {
		return parent::select(...$fields);
	}

	public function where(...$data): ReadingModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): ReadingModel {
		return $this->where('id', ...$data);
	}

	public function whereUser(...$data): ReadingModel {
		return $this->where('user', ...$data);
	}

	public function whereCourse(...$data): ReadingModel {
		return $this->where('course', ...$data);
	}

	public function whereLesson(...$data): ReadingModel {
		return $this->where('lesson', ...$data);
	}

	public function whereStartedAt(...$data): ReadingModel {
		return $this->where('startedAt', ...$data);
	}

	public function whereSeenAt(...$data): ReadingModel {
		return $this->where('seenAt', ...$data);
	}

	public function whereSeconds(...$data): ReadingModel {
		return $this->where('seconds', ...$data);
	}


}


abstract class ReadingCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Reading {

		$e = new Reading();

		if(empty($id)) {
			Reading::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Reading::getSelection();
		}

		if(Reading::model()
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
			$properties = Reading::getSelection();
		}

		if($sort !== NULL) {
			Reading::model()->sort($sort);
		}

		return Reading::model()
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

	public static function getNewElement(array $properties = []): Reading {

		return new Reading($properties);

	}

	public static function create(Reading $e): void {

		Reading::model()->insert($e);

	}

	public static function update(Reading $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Reading::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Reading $e, array $properties): void {

		self::$affected = Reading::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Reading $e): void {

		$e->expects(['id']);

		self::$affected = Reading::model()->delete($e);

	}

}


class ReadingPage extends \ModulePage {

	protected string $module = 'learning\Reading';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? ReadingLib::getPropertiesCreate(),
		   $propertiesUpdate ?? ReadingLib::getPropertiesUpdate()
		);
	}

}
?>