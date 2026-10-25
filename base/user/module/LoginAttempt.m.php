<?php
namespace user;

abstract class LoginAttemptElement extends \Element {

	use \FilterElement;

	private static ?LoginAttemptModel $model = NULL;

	const RUNNING = 'running';
	const THROTTLED = 'throttled';
	const SUCCEEDED = 'succeeded';
	const LINK = 'link';

	public static function getSelection(): array {
		return LoginAttempt::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): LoginAttemptModel {
		if(self::$model === NULL) {
			self::$model = new LoginAttemptModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('LoginAttempt::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class LoginAttemptModel extends \ModuleModel {

	protected string $module = 'user\LoginAttempt';
	protected string $package = 'user';
	protected string $table = 'userLoginAttempt';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'login' => ['text8', 'min' => 1, 'max' => 100, 'cast' => 'string'],
			'user' => ['element32', 'user\User', 'null' => TRUE, 'cast' => 'element'],
			'ip' => ['ipv4', 'cast' => 'string'],
			'failures' => ['int16', 'cast' => 'int'],
			'status' => ['enum', [\user\LoginAttempt::RUNNING, \user\LoginAttempt::THROTTLED, \user\LoginAttempt::SUCCEEDED, \user\LoginAttempt::LINK], 'cast' => 'enum'],
			'firstAt' => ['datetime', 'cast' => 'string'],
			'lastAt' => ['datetime', 'cast' => 'string'],
			'notifiedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'login', 'user', 'ip', 'failures', 'status', 'firstAt', 'lastAt', 'notifiedAt'
		]);

		$this->propertiesToModule += [
			'user' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['login', 'lastAt'],
			['ip', 'lastAt'],
			['lastAt']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'ip' :
				return getIp();

			case 'failures' :
				return 0;

			case 'status' :
				return LoginAttempt::RUNNING;

			case 'firstAt' :
				return new \Sql('NOW()');

			case 'lastAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'ip' :
				return $value === NULL ? NULL : (int)first(unpack('l', pack('l', ip2long($value))));

			case 'status' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'ip' :
				return $value === NULL ? NULL : long2ip($value);

			default :
				return parent::decode($property, $value);

		}

	}

	public function select(...$fields): LoginAttemptModel {
		return parent::select(...$fields);
	}

	public function where(...$data): LoginAttemptModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): LoginAttemptModel {
		return $this->where('id', ...$data);
	}

	public function whereLogin(...$data): LoginAttemptModel {
		return $this->where('login', ...$data);
	}

	public function whereUser(...$data): LoginAttemptModel {
		return $this->where('user', ...$data);
	}

	public function whereIp(...$data): LoginAttemptModel {
		return $this->where('ip', ...$data);
	}

	public function whereFailures(...$data): LoginAttemptModel {
		return $this->where('failures', ...$data);
	}

	public function whereStatus(...$data): LoginAttemptModel {
		return $this->where('status', ...$data);
	}

	public function whereFirstAt(...$data): LoginAttemptModel {
		return $this->where('firstAt', ...$data);
	}

	public function whereLastAt(...$data): LoginAttemptModel {
		return $this->where('lastAt', ...$data);
	}

	public function whereNotifiedAt(...$data): LoginAttemptModel {
		return $this->where('notifiedAt', ...$data);
	}


}


abstract class LoginAttemptCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): LoginAttempt {

		$e = new LoginAttempt();

		if(empty($id)) {
			LoginAttempt::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = LoginAttempt::getSelection();
		}

		if(LoginAttempt::model()
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
			$properties = LoginAttempt::getSelection();
		}

		if($sort !== NULL) {
			LoginAttempt::model()->sort($sort);
		}

		return LoginAttempt::model()
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

	public static function getNewElement(array $properties = []): LoginAttempt {

		return new LoginAttempt($properties);

	}

	public static function create(LoginAttempt $e): void {

		LoginAttempt::model()->insert($e);

	}

	public static function update(LoginAttempt $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = LoginAttempt::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, LoginAttempt $e, array $properties): void {

		self::$affected = LoginAttempt::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(LoginAttempt $e): void {

		$e->expects(['id']);

		self::$affected = LoginAttempt::model()->delete($e);

	}

}


class LoginAttemptPage extends \ModulePage {

	protected string $module = 'user\LoginAttempt';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? LoginAttemptAiLib::getPropertiesCreate(),
		   $propertiesUpdate ?? LoginAttemptAiLib::getPropertiesUpdate()
		);
	}

}
?>