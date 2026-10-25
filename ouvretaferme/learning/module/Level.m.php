<?php
namespace learning;

abstract class LevelElement extends \Element {

	use \FilterElement;

	private static ?LevelModel $model = NULL;

	public static function getSelection(): array {
		return Level::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): LevelModel {
		if(self::$model === NULL) {
			self::$model = new LevelModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Level::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class LevelModel extends \ModuleModel {

	protected string $module = 'learning\Level';
	protected string $package = 'learning';
	protected string $table = 'learningLevel';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'name' => ['text8', 'min' => 1, 'max' => 50, 'cast' => 'string'],
			'position' => ['int8', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'name', 'position'
		]);

	}

	public function select(...$fields): LevelModel {
		return parent::select(...$fields);
	}

	public function where(...$data): LevelModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): LevelModel {
		return $this->where('id', ...$data);
	}

	public function whereName(...$data): LevelModel {
		return $this->where('name', ...$data);
	}

	public function wherePosition(...$data): LevelModel {
		return $this->where('position', ...$data);
	}


}


abstract class LevelCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Level {

		$e = new Level();

		if(empty($id)) {
			Level::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Level::getSelection();
		}

		if(Level::model()
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
			$properties = Level::getSelection();
		}

		if($sort !== NULL) {
			Level::model()->sort($sort);
		}

		return Level::model()
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

	public static function getNewElement(array $properties = []): Level {

		return new Level($properties);

	}

	public static function create(Level $e): void {

		Level::model()->insert($e);

	}

	public static function update(Level $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Level::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Level $e, array $properties): void {

		self::$affected = Level::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Level $e): void {

		$e->expects(['id']);

		self::$affected = Level::model()->delete($e);

	}

}


class LevelPage extends \ModulePage {

	protected string $module = 'learning\Level';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? LevelLib::getPropertiesCreate(),
		   $propertiesUpdate ?? LevelLib::getPropertiesUpdate()
		);
	}

}
?>