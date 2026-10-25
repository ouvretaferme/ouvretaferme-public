<?php
namespace dev;

abstract class QueryStatementElement extends \Element {

	use \FilterElement;

	private static ?QueryStatementModel $model = NULL;

	public static function getSelection(): array {
		return QueryStatement::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): QueryStatementModel {
		if(self::$model === NULL) {
			self::$model = new QueryStatementModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('QueryStatement::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class QueryStatementModel extends \ModuleModel {

	protected string $module = 'dev\QueryStatement';
	protected string $package = 'dev';
	protected string $table = 'devQueryStatement';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'query' => ['element32', 'dev\Query', 'cast' => 'element'],
			'hash' => ['text8', 'charset' => 'ascii', 'cast' => 'string'],
			'statement' => ['text16', 'min' => 1, 'max' => 2000, 'cast' => 'string'],
			'executions' => ['int32', 'min' => 1, 'max' => NULL, 'cast' => 'int'],
			'time' => ['float32', 'min' => 0.0, 'max' => NULL, 'cast' => 'float'],
			'timeMax' => ['float32', 'min' => 0.0, 'max' => NULL, 'cast' => 'float'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'query', 'hash', 'statement', 'executions', 'time', 'timeMax'
		]);

		$this->propertiesToModule += [
			'query' => 'dev\Query',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['query'],
			['hash']
		]);

	}

	public function select(...$fields): QueryStatementModel {
		return parent::select(...$fields);
	}

	public function where(...$data): QueryStatementModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): QueryStatementModel {
		return $this->where('id', ...$data);
	}

	public function whereQuery(...$data): QueryStatementModel {
		return $this->where('query', ...$data);
	}

	public function whereHash(...$data): QueryStatementModel {
		return $this->where('hash', ...$data);
	}

	public function whereStatement(...$data): QueryStatementModel {
		return $this->where('statement', ...$data);
	}

	public function whereExecutions(...$data): QueryStatementModel {
		return $this->where('executions', ...$data);
	}

	public function whereTime(...$data): QueryStatementModel {
		return $this->where('time', ...$data);
	}

	public function whereTimeMax(...$data): QueryStatementModel {
		return $this->where('timeMax', ...$data);
	}


}


abstract class QueryStatementCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): QueryStatement {

		$e = new QueryStatement();

		if(empty($id)) {
			QueryStatement::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = QueryStatement::getSelection();
		}

		if(QueryStatement::model()
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
			$properties = QueryStatement::getSelection();
		}

		if($sort !== NULL) {
			QueryStatement::model()->sort($sort);
		}

		return QueryStatement::model()
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

	public static function getNewElement(array $properties = []): QueryStatement {

		return new QueryStatement($properties);

	}

	public static function create(QueryStatement $e): void {

		QueryStatement::model()->insert($e);

	}

	public static function update(QueryStatement $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = QueryStatement::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, QueryStatement $e, array $properties): void {

		self::$affected = QueryStatement::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(QueryStatement $e): void {

		$e->expects(['id']);

		self::$affected = QueryStatement::model()->delete($e);

	}

}


class QueryStatementPage extends \ModulePage {

	protected string $module = 'dev\QueryStatement';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? QueryStatementLib::getPropertiesCreate(),
		   $propertiesUpdate ?? QueryStatementLib::getPropertiesUpdate()
		);
	}

}
?>