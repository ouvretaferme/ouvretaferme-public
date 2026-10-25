<?php
namespace security;

abstract class AccessEventElement extends \Element {

	use \FilterElement;

	private static ?AccessEventModel $model = NULL;

	const SCAN = 'scan';
	const FLOOD = 'flood';
	const ERROR = 'error';

	public static function getSelection(): array {
		return AccessEvent::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): AccessEventModel {
		if(self::$model === NULL) {
			self::$model = new AccessEventModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('AccessEvent::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class AccessEventModel extends \ModuleModel {

	protected string $module = 'security\AccessEvent';
	protected string $package = 'security';
	protected string $table = 'securityAccessEvent';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'type' => ['enum', [\security\AccessEvent::SCAN, \security\AccessEvent::FLOOD, \security\AccessEvent::ERROR], 'cast' => 'enum'],
			'ip' => ['ipv4', 'null' => TRUE, 'cast' => 'string'],
			'host' => ['text8', 'min' => 1, 'max' => 100, 'null' => TRUE, 'cast' => 'string'],
			'path' => ['text8', 'min' => 1, 'max' => 255, 'null' => TRUE, 'cast' => 'string'],
			'status' => ['int16', 'cast' => 'int'],
			'hour' => ['datetime', 'cast' => 'string'],
			'count' => ['int32', 'min' => 1, 'max' => NULL, 'cast' => 'int'],
			'hosts' => ['int16', 'min' => 1, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'paths' => ['int32', 'min' => 1, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'notifiedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'type', 'ip', 'host', 'path', 'status', 'hour', 'count', 'hosts', 'paths', 'notifiedAt'
		]);

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['hour'],
			['ip', 'hour'],
			['type', 'hour']
		]);

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'type' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'ip' :
				return $value === NULL ? NULL : (int)first(unpack('l', pack('l', ip2long($value))));

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

	public function select(...$fields): AccessEventModel {
		return parent::select(...$fields);
	}

	public function where(...$data): AccessEventModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): AccessEventModel {
		return $this->where('id', ...$data);
	}

	public function whereType(...$data): AccessEventModel {
		return $this->where('type', ...$data);
	}

	public function whereIp(...$data): AccessEventModel {
		return $this->where('ip', ...$data);
	}

	public function whereHost(...$data): AccessEventModel {
		return $this->where('host', ...$data);
	}

	public function wherePath(...$data): AccessEventModel {
		return $this->where('path', ...$data);
	}

	public function whereStatus(...$data): AccessEventModel {
		return $this->where('status', ...$data);
	}

	public function whereHour(...$data): AccessEventModel {
		return $this->where('hour', ...$data);
	}

	public function whereCount(...$data): AccessEventModel {
		return $this->where('count', ...$data);
	}

	public function whereHosts(...$data): AccessEventModel {
		return $this->where('hosts', ...$data);
	}

	public function wherePaths(...$data): AccessEventModel {
		return $this->where('paths', ...$data);
	}

	public function whereNotifiedAt(...$data): AccessEventModel {
		return $this->where('notifiedAt', ...$data);
	}


}


abstract class AccessEventCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): AccessEvent {

		$e = new AccessEvent();

		if(empty($id)) {
			AccessEvent::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = AccessEvent::getSelection();
		}

		if(AccessEvent::model()
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
			$properties = AccessEvent::getSelection();
		}

		if($sort !== NULL) {
			AccessEvent::model()->sort($sort);
		}

		return AccessEvent::model()
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

	public static function getNewElement(array $properties = []): AccessEvent {

		return new AccessEvent($properties);

	}

	public static function create(AccessEvent $e): void {

		AccessEvent::model()->insert($e);

	}

	public static function update(AccessEvent $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = AccessEvent::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, AccessEvent $e, array $properties): void {

		self::$affected = AccessEvent::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(AccessEvent $e): void {

		$e->expects(['id']);

		self::$affected = AccessEvent::model()->delete($e);

	}

}


class AccessEventPage extends \ModulePage {

	protected string $module = 'security\AccessEvent';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? AccessEventLib::getPropertiesCreate(),
		   $propertiesUpdate ?? AccessEventLib::getPropertiesUpdate()
		);
	}

}
?>