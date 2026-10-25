<?php
namespace work;

abstract class MethodElement extends \Element {

	use \FilterElement;

	private static ?MethodModel $model = NULL;

	public static function getSelection(): array {
		return Method::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): MethodModel {
		if(self::$model === NULL) {
			self::$model = new MethodModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Method::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class MethodModel extends \ModuleModel {

	protected string $module = 'work\Method';
	protected string $package = 'work';
	protected string $table = 'workMethod';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'name' => ['text8', 'min' => 1, 'max' => NULL, 'collate' => 'general', 'cast' => 'string'],
			'action' => ['element32', 'work\Action', 'cast' => 'element'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'name', 'action', 'structure'
		]);

		$this->propertiesToModule += [
			'action' => 'work\Action',
			'structure' => 'structure\Structure',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['structure', 'action'],
			['action']
		]);

	}

	public function select(...$fields): MethodModel {
		return parent::select(...$fields);
	}

	public function where(...$data): MethodModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): MethodModel {
		return $this->where('id', ...$data);
	}

	public function whereName(...$data): MethodModel {
		return $this->where('name', ...$data);
	}

	public function whereAction(...$data): MethodModel {
		return $this->where('action', ...$data);
	}

	public function whereStructure(...$data): MethodModel {
		return $this->where('structure', ...$data);
	}


}


abstract class MethodCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Method {

		$e = new Method();

		if(empty($id)) {
			Method::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Method::getSelection();
		}

		if(Method::model()
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
			$properties = Method::getSelection();
		}

		if($sort !== NULL) {
			Method::model()->sort($sort);
		}

		return Method::model()
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

	public static function getNewElement(array $properties = []): Method {

		return new Method($properties);

	}

	public static function create(Method $e): void {

		Method::model()->insert($e);

	}

	public static function update(Method $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Method::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Method $e, array $properties): void {

		self::$affected = Method::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Method $e): void {

		$e->expects(['id']);

		self::$affected = Method::model()->delete($e);

	}

}


class MethodPage extends \ModulePage {

	protected string $module = 'work\Method';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? MethodLib::getPropertiesCreate(),
		   $propertiesUpdate ?? MethodLib::getPropertiesUpdate()
		);
	}

}
?>