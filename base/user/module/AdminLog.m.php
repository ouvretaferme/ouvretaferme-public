<?php
namespace user;

abstract class AdminLogElement extends \Element {

	use \FilterElement;

	private static ?AdminLogModel $model = NULL;

	const GET = 'get';
	const POST = 'post';

	public static function getSelection(): array {
		return AdminLog::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): AdminLogModel {
		if(self::$model === NULL) {
			self::$model = new AdminLogModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('AdminLog::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class AdminLogModel extends \ModuleModel {

	protected string $module = 'user\AdminLog';
	protected string $package = 'user';
	protected string $table = 'userAdminLog';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'user' => ['element32', 'user\User', 'cast' => 'element'],
			'userExternal' => ['element32', 'user\User', 'null' => TRUE, 'cast' => 'element'],
			'request' => ['text8', 'min' => 1, 'max' => 200, 'cast' => 'string'],
			'method' => ['enum', [\user\AdminLog::GET, \user\AdminLog::POST], 'cast' => 'enum'],
			'parameters' => ['json', 'null' => TRUE, 'cast' => 'array'],
			'ip' => ['ipv4', 'cast' => 'string'],
			'sid' => ['sid', 'cast' => 'string'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'user', 'userExternal', 'request', 'method', 'parameters', 'ip', 'sid', 'createdAt'
		]);

		$this->propertiesToModule += [
			'user' => 'user\User',
			'userExternal' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['user', 'createdAt'],
			['request', 'createdAt'],
			['createdAt'],
			['userExternal', 'createdAt']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'ip' :
				return getIp();

			case 'sid' :
				return session_id();

			case 'createdAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'method' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'parameters' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			case 'ip' :
				return $value === NULL ? NULL : (int)first(unpack('l', pack('l', ip2long($value))));

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'parameters' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			case 'ip' :
				return $value === NULL ? NULL : long2ip($value);

			default :
				return parent::decode($property, $value);

		}

	}

	public function select(...$fields): AdminLogModel {
		return parent::select(...$fields);
	}

	public function where(...$data): AdminLogModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): AdminLogModel {
		return $this->where('id', ...$data);
	}

	public function whereUser(...$data): AdminLogModel {
		return $this->where('user', ...$data);
	}

	public function whereUserExternal(...$data): AdminLogModel {
		return $this->where('userExternal', ...$data);
	}

	public function whereRequest(...$data): AdminLogModel {
		return $this->where('request', ...$data);
	}

	public function whereMethod(...$data): AdminLogModel {
		return $this->where('method', ...$data);
	}

	public function whereParameters(...$data): AdminLogModel {
		return $this->where('parameters', ...$data);
	}

	public function whereIp(...$data): AdminLogModel {
		return $this->where('ip', ...$data);
	}

	public function whereSid(...$data): AdminLogModel {
		return $this->where('sid', ...$data);
	}

	public function whereCreatedAt(...$data): AdminLogModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class AdminLogCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): AdminLog {

		$e = new AdminLog();

		if(empty($id)) {
			AdminLog::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = AdminLog::getSelection();
		}

		if(AdminLog::model()
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
			$properties = AdminLog::getSelection();
		}

		if($sort !== NULL) {
			AdminLog::model()->sort($sort);
		}

		return AdminLog::model()
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

	public static function getNewElement(array $properties = []): AdminLog {

		return new AdminLog($properties);

	}

	public static function create(AdminLog $e): void {

		AdminLog::model()->insert($e);

	}

	public static function update(AdminLog $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = AdminLog::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, AdminLog $e, array $properties): void {

		self::$affected = AdminLog::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(AdminLog $e): void {

		$e->expects(['id']);

		self::$affected = AdminLog::model()->delete($e);

	}

}


class AdminLogPage extends \ModulePage {

	protected string $module = 'user\AdminLog';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? AdminLogAiLib::getPropertiesCreate(),
		   $propertiesUpdate ?? AdminLogAiLib::getPropertiesUpdate()
		);
	}

}
?>