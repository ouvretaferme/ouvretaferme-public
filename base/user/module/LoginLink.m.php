<?php
namespace user;

abstract class LoginLinkElement extends \Element {

	use \FilterElement;

	private static ?LoginLinkModel $model = NULL;

	const WEB = 'web';
	const APP = 'app';
	const MOBILE_WEB = 'mobile-web';
	const TABLET_WEB = 'tablet-web';
	const CRAWLER = 'crawler';

	const LINK = 'link';
	const RESENT = 'resent';

	public static function getSelection(): array {
		return LoginLink::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): LoginLinkModel {
		if(self::$model === NULL) {
			self::$model = new LoginLinkModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('LoginLink::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class LoginLinkModel extends \ModuleModel {

	protected string $module = 'user\LoginLink';
	protected string $package = 'user';
	protected string $table = 'userLoginLink';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'user' => ['element32', 'user\User', 'cast' => 'element'],
			'token' => ['textFixed', 'min' => 64, 'max' => 64, 'charset' => 'ascii', 'unique' => TRUE, 'cast' => 'string'],
			'ip' => ['ipv4', 'cast' => 'string'],
			'device' => ['enum', [\user\LoginLink::WEB, \user\LoginLink::APP, \user\LoginLink::MOBILE_WEB, \user\LoginLink::TABLET_WEB, \user\LoginLink::CRAWLER], 'cast' => 'enum'],
			'createdAt' => ['datetime', 'cast' => 'string'],
			'expiresAt' => ['datetime', 'cast' => 'string'],
			'closedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'closedBy' => ['enum', [\user\LoginLink::LINK, \user\LoginLink::RESENT], 'null' => TRUE, 'cast' => 'enum'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'user', 'token', 'ip', 'device', 'createdAt', 'expiresAt', 'closedAt', 'closedBy'
		]);

		$this->propertiesToModule += [
			'user' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['user', 'createdAt']
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['token']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'ip' :
				return getIp();

			case 'createdAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'ip' :
				return $value === NULL ? NULL : (int)first(unpack('l', pack('l', ip2long($value))));

			case 'device' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'closedBy' :
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

	public function select(...$fields): LoginLinkModel {
		return parent::select(...$fields);
	}

	public function where(...$data): LoginLinkModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): LoginLinkModel {
		return $this->where('id', ...$data);
	}

	public function whereUser(...$data): LoginLinkModel {
		return $this->where('user', ...$data);
	}

	public function whereToken(...$data): LoginLinkModel {
		return $this->where('token', ...$data);
	}

	public function whereIp(...$data): LoginLinkModel {
		return $this->where('ip', ...$data);
	}

	public function whereDevice(...$data): LoginLinkModel {
		return $this->where('device', ...$data);
	}

	public function whereCreatedAt(...$data): LoginLinkModel {
		return $this->where('createdAt', ...$data);
	}

	public function whereExpiresAt(...$data): LoginLinkModel {
		return $this->where('expiresAt', ...$data);
	}

	public function whereClosedAt(...$data): LoginLinkModel {
		return $this->where('closedAt', ...$data);
	}

	public function whereClosedBy(...$data): LoginLinkModel {
		return $this->where('closedBy', ...$data);
	}


}


abstract class LoginLinkCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): LoginLink {

		$e = new LoginLink();

		if(empty($id)) {
			LoginLink::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = LoginLink::getSelection();
		}

		if(LoginLink::model()
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
			$properties = LoginLink::getSelection();
		}

		if($sort !== NULL) {
			LoginLink::model()->sort($sort);
		}

		return LoginLink::model()
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

	public static function getNewElement(array $properties = []): LoginLink {

		return new LoginLink($properties);

	}

	public static function create(LoginLink $e): void {

		LoginLink::model()->insert($e);

	}

	public static function update(LoginLink $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = LoginLink::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, LoginLink $e, array $properties): void {

		self::$affected = LoginLink::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(LoginLink $e): void {

		$e->expects(['id']);

		self::$affected = LoginLink::model()->delete($e);

	}

}


class LoginLinkPage extends \ModulePage {

	protected string $module = 'user\LoginLink';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? LoginLinkAiLib::getPropertiesCreate(),
		   $propertiesUpdate ?? LoginLinkAiLib::getPropertiesUpdate()
		);
	}

}
?>