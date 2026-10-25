<?php
namespace dev;

abstract class QueryElement extends \Element {

	use \FilterElement;

	private static ?QueryModel $model = NULL;

	public static function getSelection(): array {
		return Query::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): QueryModel {
		if(self::$model === NULL) {
			self::$model = new QueryModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Query::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class QueryModel extends \ModuleModel {

	protected string $module = 'dev\Query';
	protected string $package = 'dev';
	protected string $table = 'devQuery';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'method' => ['text8', 'charset' => 'ascii', 'null' => TRUE, 'cast' => 'string'],
			'request' => ['text16', 'cast' => 'string'],
			'page' => ['text8', 'null' => TRUE, 'cast' => 'string'],
			'name' => ['text8', 'null' => TRUE, 'cast' => 'string'],
			'mode' => ['text8', 'charset' => 'ascii', 'null' => TRUE, 'cast' => 'string'],
			'user' => ['element32', 'user\User', 'null' => TRUE, 'cast' => 'element'],
			'time' => ['float32', 'min' => 0.0, 'max' => NULL, 'cast' => 'float'],
			'queries' => ['int32', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'duplicates' => ['int32', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'duration' => ['float32', 'min' => 0.0, 'max' => NULL, 'null' => TRUE, 'cast' => 'float'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'method', 'request', 'page', 'name', 'mode', 'user', 'time', 'queries', 'duplicates', 'duration', 'createdAt'
		]);

		$this->propertiesToModule += [
			'user' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['createdAt']
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

	public function select(...$fields): QueryModel {
		return parent::select(...$fields);
	}

	public function where(...$data): QueryModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): QueryModel {
		return $this->where('id', ...$data);
	}

	public function whereMethod(...$data): QueryModel {
		return $this->where('method', ...$data);
	}

	public function whereRequest(...$data): QueryModel {
		return $this->where('request', ...$data);
	}

	public function wherePage(...$data): QueryModel {
		return $this->where('page', ...$data);
	}

	public function whereName(...$data): QueryModel {
		return $this->where('name', ...$data);
	}

	public function whereMode(...$data): QueryModel {
		return $this->where('mode', ...$data);
	}

	public function whereUser(...$data): QueryModel {
		return $this->where('user', ...$data);
	}

	public function whereTime(...$data): QueryModel {
		return $this->where('time', ...$data);
	}

	public function whereQueries(...$data): QueryModel {
		return $this->where('queries', ...$data);
	}

	public function whereDuplicates(...$data): QueryModel {
		return $this->where('duplicates', ...$data);
	}

	public function whereDuration(...$data): QueryModel {
		return $this->where('duration', ...$data);
	}

	public function whereCreatedAt(...$data): QueryModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class QueryCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Query {

		$e = new Query();

		if(empty($id)) {
			Query::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Query::getSelection();
		}

		if(Query::model()
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
			$properties = Query::getSelection();
		}

		if($sort !== NULL) {
			Query::model()->sort($sort);
		}

		return Query::model()
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

	public static function getNewElement(array $properties = []): Query {

		return new Query($properties);

	}

	public static function create(Query $e): void {

		Query::model()->insert($e);

	}

	public static function update(Query $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Query::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Query $e, array $properties): void {

		self::$affected = Query::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Query $e): void {

		$e->expects(['id']);

		self::$affected = Query::model()->delete($e);

	}

}


class QueryPage extends \ModulePage {

	protected string $module = 'dev\Query';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? QueryLib::getPropertiesCreate(),
		   $propertiesUpdate ?? QueryLib::getPropertiesUpdate()
		);
	}

}
?>