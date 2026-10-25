<?php
namespace user;

abstract class UserAvatarUploadElement extends \Element {

	use \FilterElement;

	private static ?UserAvatarUploadModel $model = NULL;

	const NEW = 'new';
	const KEPT = 'kept';
	const WITHDRAWN = 'withdrawn';

	const PERSON = 'person';
	const BRAND = 'brand';
	const OFFENSIVE = 'offensive';
	const OTHER = 'other';

	public static function getSelection(): array {
		return UserAvatarUpload::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): UserAvatarUploadModel {
		if(self::$model === NULL) {
			self::$model = new UserAvatarUploadModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('UserAvatarUpload::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class UserAvatarUploadModel extends \ModuleModel {

	protected string $module = 'user\UserAvatarUpload';
	protected string $package = 'user';
	protected string $table = 'userUserAvatarUpload';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'user' => ['element32', 'user\User', 'cast' => 'element'],
			'hash' => ['textFixed', 'min' => 20, 'max' => 20, 'charset' => 'ascii', 'null' => TRUE, 'cast' => 'string'],
			'bakedIndex' => ['json', 'null' => TRUE, 'cast' => 'array'],
			'rules' => ['int16', 'min' => 1, 'max' => NULL, 'cast' => 'int'],
			'status' => ['enum', [\user\UserAvatarUpload::NEW, \user\UserAvatarUpload::KEPT, \user\UserAvatarUpload::WITHDRAWN], 'cast' => 'enum'],
			'reason' => ['enum', [\user\UserAvatarUpload::PERSON, \user\UserAvatarUpload::BRAND, \user\UserAvatarUpload::OFFENSIVE, \user\UserAvatarUpload::OTHER], 'null' => TRUE, 'cast' => 'enum'],
			'reasonText' => ['text16', 'min' => 1, 'max' => 500, 'null' => TRUE, 'cast' => 'string'],
			'moderatedBy' => ['element32', 'user\User', 'null' => TRUE, 'cast' => 'element'],
			'moderatedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'user', 'hash', 'bakedIndex', 'rules', 'status', 'reason', 'reasonText', 'moderatedBy', 'moderatedAt', 'createdAt'
		]);

		$this->propertiesToModule += [
			'user' => 'user\User',
			'moderatedBy' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['user'],
			['status']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'status' :
				return UserAvatarUpload::NEW;

			case 'createdAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'bakedIndex' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			case 'status' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'reason' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'bakedIndex' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			default :
				return parent::decode($property, $value);

		}

	}

	public function select(...$fields): UserAvatarUploadModel {
		return parent::select(...$fields);
	}

	public function where(...$data): UserAvatarUploadModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): UserAvatarUploadModel {
		return $this->where('id', ...$data);
	}

	public function whereUser(...$data): UserAvatarUploadModel {
		return $this->where('user', ...$data);
	}

	public function whereHash(...$data): UserAvatarUploadModel {
		return $this->where('hash', ...$data);
	}

	public function whereBakedIndex(...$data): UserAvatarUploadModel {
		return $this->where('bakedIndex', ...$data);
	}

	public function whereRules(...$data): UserAvatarUploadModel {
		return $this->where('rules', ...$data);
	}

	public function whereStatus(...$data): UserAvatarUploadModel {
		return $this->where('status', ...$data);
	}

	public function whereReason(...$data): UserAvatarUploadModel {
		return $this->where('reason', ...$data);
	}

	public function whereReasonText(...$data): UserAvatarUploadModel {
		return $this->where('reasonText', ...$data);
	}

	public function whereModeratedBy(...$data): UserAvatarUploadModel {
		return $this->where('moderatedBy', ...$data);
	}

	public function whereModeratedAt(...$data): UserAvatarUploadModel {
		return $this->where('moderatedAt', ...$data);
	}

	public function whereCreatedAt(...$data): UserAvatarUploadModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class UserAvatarUploadCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): UserAvatarUpload {

		$e = new UserAvatarUpload();

		if(empty($id)) {
			UserAvatarUpload::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = UserAvatarUpload::getSelection();
		}

		if(UserAvatarUpload::model()
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
			$properties = UserAvatarUpload::getSelection();
		}

		if($sort !== NULL) {
			UserAvatarUpload::model()->sort($sort);
		}

		return UserAvatarUpload::model()
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

	public static function getNewElement(array $properties = []): UserAvatarUpload {

		return new UserAvatarUpload($properties);

	}

	public static function create(UserAvatarUpload $e): void {

		UserAvatarUpload::model()->insert($e);

	}

	public static function update(UserAvatarUpload $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = UserAvatarUpload::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, UserAvatarUpload $e, array $properties): void {

		self::$affected = UserAvatarUpload::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(UserAvatarUpload $e): void {

		$e->expects(['id']);

		self::$affected = UserAvatarUpload::model()->delete($e);

	}

}


class UserAvatarUploadPage extends \ModulePage {

	protected string $module = 'user\UserAvatarUpload';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? UserAvatarUploadLib::getPropertiesCreate(),
		   $propertiesUpdate ?? UserAvatarUploadLib::getPropertiesUpdate()
		);
	}

}
?>