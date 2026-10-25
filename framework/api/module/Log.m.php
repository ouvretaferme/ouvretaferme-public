<?php
namespace api;

abstract class LogElement extends \Element {

	use \FilterElement;

	private static ?LogModel $model = NULL;

	const GET = 'get';
	const POST = 'post';
	const PUT = 'put';
	const DELETE = 'delete';
	const PATCH = 'patch';

	const CREATED = 'created';
	const SUCCESS = 'success';
	const ERROR = 'error';
	const RATE_LIMIT = 'rate-limit';

	public static function getSelection(): array {
		return Log::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): LogModel {
		if(self::$model === NULL) {
			self::$model = new LogModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Log::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class LogModel extends \ModuleModel {

	protected string $module = 'api\Log';
	protected string $package = 'api';
	protected string $table = 'apiLog';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'token' => ['element32', 'api\AccessToken', 'null' => TRUE, 'cast' => 'element'],
			'reference' => ['int32', 'min' => 0, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'requestMethod' => ['enum', [\api\Log::GET, \api\Log::POST, \api\Log::PUT, \api\Log::DELETE, \api\Log::PATCH], 'cast' => 'enum'],
			'requestUrl' => ['text8', 'cast' => 'string'],
			'requestHeaders' => ['json', 'null' => TRUE, 'cast' => 'array'],
			'route' => ['text8', 'cast' => 'string'],
			'responseBody' => ['json', 'null' => TRUE, 'cast' => 'array'],
			'responseStatusCode' => ['int16', 'min' => 0, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'time' => ['float32', 'null' => TRUE, 'cast' => 'float'],
			'createdAt' => ['datetime', 'cast' => 'string'],
			'status' => ['enum', [\api\Log::CREATED, \api\Log::SUCCESS, \api\Log::ERROR, \api\Log::RATE_LIMIT], 'cast' => 'enum'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'token', 'reference', 'requestMethod', 'requestUrl', 'requestHeaders', 'route', 'responseBody', 'responseStatusCode', 'time', 'createdAt', 'status'
		]);

		$this->propertiesToModule += [
			'token' => 'api\AccessToken',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['token'],
			['reference']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'createdAt' :
				return new \Sql('NOW()');

			case 'status' :
				return Log::CREATED;

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'requestMethod' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'requestHeaders' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			case 'responseBody' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			case 'status' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'requestHeaders' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			case 'responseBody' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			default :
				return parent::decode($property, $value);

		}

	}

	public function select(...$fields): LogModel {
		return parent::select(...$fields);
	}

	public function where(...$data): LogModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): LogModel {
		return $this->where('id', ...$data);
	}

	public function whereToken(...$data): LogModel {
		return $this->where('token', ...$data);
	}

	public function whereReference(...$data): LogModel {
		return $this->where('reference', ...$data);
	}

	public function whereRequestMethod(...$data): LogModel {
		return $this->where('requestMethod', ...$data);
	}

	public function whereRequestUrl(...$data): LogModel {
		return $this->where('requestUrl', ...$data);
	}

	public function whereRequestHeaders(...$data): LogModel {
		return $this->where('requestHeaders', ...$data);
	}

	public function whereRoute(...$data): LogModel {
		return $this->where('route', ...$data);
	}

	public function whereResponseBody(...$data): LogModel {
		return $this->where('responseBody', ...$data);
	}

	public function whereResponseStatusCode(...$data): LogModel {
		return $this->where('responseStatusCode', ...$data);
	}

	public function whereTime(...$data): LogModel {
		return $this->where('time', ...$data);
	}

	public function whereCreatedAt(...$data): LogModel {
		return $this->where('createdAt', ...$data);
	}

	public function whereStatus(...$data): LogModel {
		return $this->where('status', ...$data);
	}


}


abstract class LogCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Log {

		$e = new Log();

		if(empty($id)) {
			Log::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Log::getSelection();
		}

		if(Log::model()
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
			$properties = Log::getSelection();
		}

		if($sort !== NULL) {
			Log::model()->sort($sort);
		}

		return Log::model()
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

	public static function getNewElement(array $properties = []): Log {

		return new Log($properties);

	}

	public static function create(Log $e): void {

		Log::model()->insert($e);

	}

	public static function update(Log $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Log::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Log $e, array $properties): void {

		self::$affected = Log::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Log $e): void {

		$e->expects(['id']);

		self::$affected = Log::model()->delete($e);

	}

}


class LogPage extends \ModulePage {

	protected string $module = 'api\Log';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? LogLib::getPropertiesCreate(),
		   $propertiesUpdate ?? LogLib::getPropertiesUpdate()
		);
	}

}
?>