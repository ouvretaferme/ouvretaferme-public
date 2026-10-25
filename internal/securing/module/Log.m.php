<?php
namespace securing;

abstract class LogElement extends \Element {

	use \FilterElement;

	private static ?LogModel $model = NULL;

	const PRODUCTION = 'production';
	const SELLING = 'selling';
	const ACCOUNTING = 'accounting';
	const SETTINGS = 'settings';

	const USER = 'user';
	const SYSTEM = 'system';
	const TECHNICAL = 'technical';

	const WEB = 'web';
	const API = 'api';
	const CRON = 'cron';

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

	protected string $module = 'securing\Log';
	protected string $package = 'securing';
	protected string $table = 'securingLog';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'module' => ['enum', [\securing\Log::PRODUCTION, \securing\Log::SELLING, \securing\Log::ACCOUNTING, \securing\Log::SETTINGS], 'cast' => 'enum'],
			'event' => ['text8', 'cast' => 'string'],
			'data' => ['json', 'null' => TRUE, 'cast' => 'array'],
			'comment' => ['text24', 'null' => TRUE, 'cast' => 'string'],
			'level' => ['enum', [\securing\Log::USER, \securing\Log::SYSTEM, \securing\Log::TECHNICAL], 'cast' => 'enum'],
			'channel' => ['enum', [\securing\Log::WEB, \securing\Log::API, \securing\Log::CRON], 'cast' => 'enum'],
			'apiLog' => ['int32', 'min' => 0, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'doneBy' => ['element32', 'user\User', 'null' => TRUE, 'cast' => 'element'],
			'doneAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'module', 'event', 'data', 'comment', 'level', 'channel', 'apiLog', 'doneBy', 'doneAt'
		]);

		$this->propertiesToModule += [
			'doneBy' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['level', 'doneAt'],
			['event', 'doneAt']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'doneBy' :
				return \user\ConnectionLib::getOnline();

			case 'doneAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'module' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'data' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			case 'level' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'channel' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'data' :
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

	public function whereModule(...$data): LogModel {
		return $this->where('module', ...$data);
	}

	public function whereEvent(...$data): LogModel {
		return $this->where('event', ...$data);
	}

	public function whereData(...$data): LogModel {
		return $this->where('data', ...$data);
	}

	public function whereComment(...$data): LogModel {
		return $this->where('comment', ...$data);
	}

	public function whereLevel(...$data): LogModel {
		return $this->where('level', ...$data);
	}

	public function whereChannel(...$data): LogModel {
		return $this->where('channel', ...$data);
	}

	public function whereApiLog(...$data): LogModel {
		return $this->where('apiLog', ...$data);
	}

	public function whereDoneBy(...$data): LogModel {
		return $this->where('doneBy', ...$data);
	}

	public function whereDoneAt(...$data): LogModel {
		return $this->where('doneAt', ...$data);
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

	protected string $module = 'securing\Log';

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