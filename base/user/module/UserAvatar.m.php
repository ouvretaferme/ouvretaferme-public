<?php
namespace user;

abstract class UserAvatarElement extends \Element {

	use \FilterElement;

	private static ?UserAvatarModel $model = NULL;

	const EDITOR = 'editor';
	const PHOTO = 'photo';

	public static function getSelection(): array {
		return UserAvatar::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): UserAvatarModel {
		if(self::$model === NULL) {
			self::$model = new UserAvatarModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('UserAvatar::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class UserAvatarModel extends \ModuleModel {

	protected string $module = 'user\UserAvatar';
	protected string $package = 'user';
	protected string $table = 'userUserAvatar';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'user' => ['element32', 'user\User', 'unique' => TRUE, 'cast' => 'element'],
			'source' => ['enum', [\user\UserAvatar::EDITOR, \user\UserAvatar::PHOTO], 'cast' => 'enum'],
			'composition' => ['json', 'null' => TRUE, 'cast' => 'array'],
			'accessory' => ['text8', 'min' => 1, 'max' => 40, 'null' => TRUE, 'cast' => 'string'],
			'companion' => ['text8', 'min' => 1, 'max' => 40, 'null' => TRUE, 'cast' => 'string'],
			'replacesPhoto' => ['bool', 'cast' => 'bool'],
			'photo' => ['textFixed', 'min' => 30, 'max' => 30, 'charset' => 'ascii', 'null' => TRUE, 'cast' => 'string'],
			'upload' => ['element32', 'user\UserAvatarUpload', 'null' => TRUE, 'cast' => 'element'],
			'createdAt' => ['datetime', 'cast' => 'string'],
			'updatedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'user', 'source', 'composition', 'accessory', 'companion', 'replacesPhoto', 'photo', 'upload', 'createdAt', 'updatedAt'
		]);

		$this->propertiesToModule += [
			'user' => 'user\User',
			'upload' => 'user\UserAvatarUpload',
		];

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['user']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'source' :
				return UserAvatar::EDITOR;

			case 'replacesPhoto' :
				return TRUE;

			case 'createdAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'source' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'composition' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'composition' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			default :
				return parent::decode($property, $value);

		}

	}

	public function select(...$fields): UserAvatarModel {
		return parent::select(...$fields);
	}

	public function where(...$data): UserAvatarModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): UserAvatarModel {
		return $this->where('id', ...$data);
	}

	public function whereUser(...$data): UserAvatarModel {
		return $this->where('user', ...$data);
	}

	public function whereSource(...$data): UserAvatarModel {
		return $this->where('source', ...$data);
	}

	public function whereComposition(...$data): UserAvatarModel {
		return $this->where('composition', ...$data);
	}

	public function whereAccessory(...$data): UserAvatarModel {
		return $this->where('accessory', ...$data);
	}

	public function whereCompanion(...$data): UserAvatarModel {
		return $this->where('companion', ...$data);
	}

	public function whereReplacesPhoto(...$data): UserAvatarModel {
		return $this->where('replacesPhoto', ...$data);
	}

	public function wherePhoto(...$data): UserAvatarModel {
		return $this->where('photo', ...$data);
	}

	public function whereUpload(...$data): UserAvatarModel {
		return $this->where('upload', ...$data);
	}

	public function whereCreatedAt(...$data): UserAvatarModel {
		return $this->where('createdAt', ...$data);
	}

	public function whereUpdatedAt(...$data): UserAvatarModel {
		return $this->where('updatedAt', ...$data);
	}


}


abstract class UserAvatarCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): UserAvatar {

		$e = new UserAvatar();

		if(empty($id)) {
			UserAvatar::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = UserAvatar::getSelection();
		}

		if(UserAvatar::model()
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
			$properties = UserAvatar::getSelection();
		}

		if($sort !== NULL) {
			UserAvatar::model()->sort($sort);
		}

		return UserAvatar::model()
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

	public static function getNewElement(array $properties = []): UserAvatar {

		return new UserAvatar($properties);

	}

	public static function create(UserAvatar $e): void {

		UserAvatar::model()->insert($e);

	}

	public static function update(UserAvatar $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = UserAvatar::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, UserAvatar $e, array $properties): void {

		self::$affected = UserAvatar::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(UserAvatar $e): void {

		$e->expects(['id']);

		self::$affected = UserAvatar::model()->delete($e);

	}

}


class UserAvatarPage extends \ModulePage {

	protected string $module = 'user\UserAvatar';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? UserAvatarLib::getPropertiesCreate(),
		   $propertiesUpdate ?? UserAvatarLib::getPropertiesUpdate()
		);
	}

}
?>