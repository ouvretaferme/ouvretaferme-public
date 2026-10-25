<?php
namespace api;

abstract class ScopeElement extends \Element {

	use \FilterElement;

	private static ?ScopeModel $model = NULL;

	public static function getSelection(): array {
		return Scope::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): ScopeModel {
		if(self::$model === NULL) {
			self::$model = new ScopeModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Scope::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class ScopeModel extends \ModuleModel {

	protected string $module = 'api\Scope';
	protected string $package = 'api';
	protected string $table = 'apiScope';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'name' => ['text8', 'min' => 1, 'max' => NULL, 'charset' => 'ascii', 'unique' => TRUE, 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'name'
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['name']
		]);

	}

	public function select(...$fields): ScopeModel {
		return parent::select(...$fields);
	}

	public function where(...$data): ScopeModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): ScopeModel {
		return $this->where('id', ...$data);
	}

	public function whereName(...$data): ScopeModel {
		return $this->where('name', ...$data);
	}


}


abstract class ScopeCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Scope {

		$e = new Scope();

		if(empty($id)) {
			Scope::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Scope::getSelection();
		}

		if(Scope::model()
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
			$properties = Scope::getSelection();
		}

		if($sort !== NULL) {
			Scope::model()->sort($sort);
		}

		return Scope::model()
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

	public static function getNewElement(array $properties = []): Scope {

		return new Scope($properties);

	}

	public static function create(Scope $e): void {

		Scope::model()->insert($e);

	}

	public static function update(Scope $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Scope::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Scope $e, array $properties): void {

		self::$affected = Scope::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Scope $e): void {

		$e->expects(['id']);

		self::$affected = Scope::model()->delete($e);

	}

}


class ScopePage extends \ModulePage {

	protected string $module = 'api\Scope';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? ScopeLib::getPropertiesCreate(),
		   $propertiesUpdate ?? ScopeLib::getPropertiesUpdate()
		);
	}

}
?>