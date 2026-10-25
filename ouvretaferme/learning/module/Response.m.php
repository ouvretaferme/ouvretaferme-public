<?php
namespace learning;

abstract class ResponseElement extends \Element {

	use \FilterElement;

	private static ?ResponseModel $model = NULL;

	public static function getSelection(): array {
		return Response::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): ResponseModel {
		if(self::$model === NULL) {
			self::$model = new ResponseModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Response::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class ResponseModel extends \ModuleModel {

	protected string $module = 'learning\Response';
	protected string $package = 'learning';
	protected string $table = 'learningResponse';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'question' => ['element32', 'learning\Question', 'cast' => 'element'],
			'user' => ['element32', 'user\User', 'cast' => 'element'],
			'attempt' => ['int16', 'min' => 1, 'max' => NULL, 'cast' => 'int'],
			'choice' => ['element32', 'learning\Choice', 'null' => TRUE, 'cast' => 'element'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'question', 'user', 'attempt', 'choice', 'createdAt'
		]);

		$this->propertiesToModule += [
			'question' => 'learning\Question',
			'user' => 'user\User',
			'choice' => 'learning\Choice',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['user', 'question'],
			['question']
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

	public function select(...$fields): ResponseModel {
		return parent::select(...$fields);
	}

	public function where(...$data): ResponseModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): ResponseModel {
		return $this->where('id', ...$data);
	}

	public function whereQuestion(...$data): ResponseModel {
		return $this->where('question', ...$data);
	}

	public function whereUser(...$data): ResponseModel {
		return $this->where('user', ...$data);
	}

	public function whereAttempt(...$data): ResponseModel {
		return $this->where('attempt', ...$data);
	}

	public function whereChoice(...$data): ResponseModel {
		return $this->where('choice', ...$data);
	}

	public function whereCreatedAt(...$data): ResponseModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class ResponseCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Response {

		$e = new Response();

		if(empty($id)) {
			Response::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Response::getSelection();
		}

		if(Response::model()
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
			$properties = Response::getSelection();
		}

		if($sort !== NULL) {
			Response::model()->sort($sort);
		}

		return Response::model()
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

	public static function getNewElement(array $properties = []): Response {

		return new Response($properties);

	}

	public static function create(Response $e): void {

		Response::model()->insert($e);

	}

	public static function update(Response $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Response::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Response $e, array $properties): void {

		self::$affected = Response::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Response $e): void {

		$e->expects(['id']);

		self::$affected = Response::model()->delete($e);

	}

}


class ResponsePage extends \ModulePage {

	protected string $module = 'learning\Response';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? ResponseLib::getPropertiesCreate(),
		   $propertiesUpdate ?? ResponseLib::getPropertiesUpdate()
		);
	}

}
?>