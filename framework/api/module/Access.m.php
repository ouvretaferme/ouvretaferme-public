<?php
namespace api;

abstract class AccessElement extends \Element {

	use \FilterElement;

	private static ?AccessModel $model = NULL;

	public static function getSelection(): array {
		return Access::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): AccessModel {
		if(self::$model === NULL) {
			self::$model = new AccessModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Access::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class AccessModel extends \ModuleModel {

	protected string $module = 'api\Access';
	protected string $package = 'api';
	protected string $table = 'apiAccess';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'reference' => ['int32', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'counter' => ['int32', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'date' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'reference', 'counter', 'date'
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['reference', 'date']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'counter' :
				return 0;

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function select(...$fields): AccessModel {
		return parent::select(...$fields);
	}

	public function where(...$data): AccessModel {
		return parent::where(...$data);
	}

	public function whereReference(...$data): AccessModel {
		return $this->where('reference', ...$data);
	}

	public function whereCounter(...$data): AccessModel {
		return $this->where('counter', ...$data);
	}

	public function whereDate(...$data): AccessModel {
		return $this->where('date', ...$data);
	}


}


abstract class AccessCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Access {

		$e = new Access();

		if(empty($id)) {
			Access::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Access::getSelection();
		}

		if(Access::model()
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
			$properties = Access::getSelection();
		}

		if($sort !== NULL) {
			Access::model()->sort($sort);
		}

		return Access::model()
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

	public static function getNewElement(array $properties = []): Access {

		return new Access($properties);

	}

	public static function create(Access $e): void {

		Access::model()->insert($e);

	}

	public static function update(Access $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Access::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Access $e, array $properties): void {

		self::$affected = Access::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Access $e): void {

		$e->expects(['id']);

		self::$affected = Access::model()->delete($e);

	}

}


class AccessPage extends \ModulePage {

	protected string $module = 'api\Access';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? AccessLib::getPropertiesCreate(),
		   $propertiesUpdate ?? AccessLib::getPropertiesUpdate()
		);
	}

}
?>