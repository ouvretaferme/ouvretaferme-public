<?php
namespace user;

abstract class UserAvatarRequestElement extends \Element {

	use \FilterElement;

	private static ?UserAvatarRequestModel $model = NULL;

	const WAITING = 'waiting';
	const DONE = 'done';
	const REFUSED = 'refused';

	public static function getSelection(): array {
		return UserAvatarRequest::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): UserAvatarRequestModel {
		if(self::$model === NULL) {
			self::$model = new UserAvatarRequestModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('UserAvatarRequest::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class UserAvatarRequestModel extends \ModuleModel {

	protected string $module = 'user\UserAvatarRequest';
	protected string $package = 'user';
	protected string $table = 'userUserAvatarRequest';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'user' => ['element32', 'user\User', 'cast' => 'element'],
			'photo' => ['textFixed', 'min' => 30, 'max' => 30, 'charset' => 'ascii', 'null' => TRUE, 'cast' => 'string'],
			'status' => ['enum', [\user\UserAvatarRequest::WAITING, \user\UserAvatarRequest::DONE, \user\UserAvatarRequest::REFUSED], 'cast' => 'enum'],
			'reason' => ['text8', 'min' => 1, 'max' => 200, 'null' => TRUE, 'cast' => 'string'],
			'createdAt' => ['datetime', 'cast' => 'string'],
			'doneAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'user', 'photo', 'status', 'reason', 'createdAt', 'doneAt'
		]);

		$this->propertiesToModule += [
			'user' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['user'],
			['status']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'status' :
				return UserAvatarRequest::WAITING;

			case 'createdAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'status' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): UserAvatarRequestModel {
		return parent::select(...$fields);
	}

	public function where(...$data): UserAvatarRequestModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): UserAvatarRequestModel {
		return $this->where('id', ...$data);
	}

	public function whereUser(...$data): UserAvatarRequestModel {
		return $this->where('user', ...$data);
	}

	public function wherePhoto(...$data): UserAvatarRequestModel {
		return $this->where('photo', ...$data);
	}

	public function whereStatus(...$data): UserAvatarRequestModel {
		return $this->where('status', ...$data);
	}

	public function whereReason(...$data): UserAvatarRequestModel {
		return $this->where('reason', ...$data);
	}

	public function whereCreatedAt(...$data): UserAvatarRequestModel {
		return $this->where('createdAt', ...$data);
	}

	public function whereDoneAt(...$data): UserAvatarRequestModel {
		return $this->where('doneAt', ...$data);
	}


}


abstract class UserAvatarRequestCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): UserAvatarRequest {

		$e = new UserAvatarRequest();

		if(empty($id)) {
			UserAvatarRequest::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = UserAvatarRequest::getSelection();
		}

		if(UserAvatarRequest::model()
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
			$properties = UserAvatarRequest::getSelection();
		}

		if($sort !== NULL) {
			UserAvatarRequest::model()->sort($sort);
		}

		return UserAvatarRequest::model()
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

	public static function getNewElement(array $properties = []): UserAvatarRequest {

		return new UserAvatarRequest($properties);

	}

	public static function create(UserAvatarRequest $e): void {

		UserAvatarRequest::model()->insert($e);

	}

	public static function update(UserAvatarRequest $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = UserAvatarRequest::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, UserAvatarRequest $e, array $properties): void {

		self::$affected = UserAvatarRequest::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(UserAvatarRequest $e): void {

		$e->expects(['id']);

		self::$affected = UserAvatarRequest::model()->delete($e);

	}

}


class UserAvatarRequestPage extends \ModulePage {

	protected string $module = 'user\UserAvatarRequest';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? UserAvatarRequestLib::getPropertiesCreate(),
		   $propertiesUpdate ?? UserAvatarRequestLib::getPropertiesUpdate()
		);
	}

}
?>