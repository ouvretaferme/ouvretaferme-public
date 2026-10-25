<?php
namespace api;

abstract class RefreshTokenElement extends \Element {

	use \FilterElement;

	private static ?RefreshTokenModel $model = NULL;

	public static function getSelection(): array {
		return RefreshToken::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): RefreshTokenModel {
		if(self::$model === NULL) {
			self::$model = new RefreshTokenModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('RefreshToken::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class RefreshTokenModel extends \ModuleModel {

	protected string $module = 'api\RefreshToken';
	protected string $package = 'api';
	protected string $table = 'apiRefreshToken';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'tokenId' => ['text8', 'min' => 128, 'max' => 128, 'charset' => 'ascii', 'unique' => TRUE, 'cast' => 'string'],
			'accessToken' => ['element32', 'api\AccessToken', 'cast' => 'element'],
			'expiryDateTime' => ['datetime', 'cast' => 'string'],
			'revoked' => ['bool', 'cast' => 'bool'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'tokenId', 'accessToken', 'expiryDateTime', 'revoked', 'createdAt'
		]);

		$this->propertiesToModule += [
			'accessToken' => 'api\AccessToken',
		];

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['tokenId']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'revoked' :
				return FALSE;

			case 'createdAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function select(...$fields): RefreshTokenModel {
		return parent::select(...$fields);
	}

	public function where(...$data): RefreshTokenModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): RefreshTokenModel {
		return $this->where('id', ...$data);
	}

	public function whereTokenId(...$data): RefreshTokenModel {
		return $this->where('tokenId', ...$data);
	}

	public function whereAccessToken(...$data): RefreshTokenModel {
		return $this->where('accessToken', ...$data);
	}

	public function whereExpiryDateTime(...$data): RefreshTokenModel {
		return $this->where('expiryDateTime', ...$data);
	}

	public function whereRevoked(...$data): RefreshTokenModel {
		return $this->where('revoked', ...$data);
	}

	public function whereCreatedAt(...$data): RefreshTokenModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class RefreshTokenCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): RefreshToken {

		$e = new RefreshToken();

		if(empty($id)) {
			RefreshToken::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = RefreshToken::getSelection();
		}

		if(RefreshToken::model()
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
			$properties = RefreshToken::getSelection();
		}

		if($sort !== NULL) {
			RefreshToken::model()->sort($sort);
		}

		return RefreshToken::model()
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

	public static function getNewElement(array $properties = []): RefreshToken {

		return new RefreshToken($properties);

	}

	public static function create(RefreshToken $e): void {

		RefreshToken::model()->insert($e);

	}

	public static function update(RefreshToken $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = RefreshToken::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, RefreshToken $e, array $properties): void {

		self::$affected = RefreshToken::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(RefreshToken $e): void {

		$e->expects(['id']);

		self::$affected = RefreshToken::model()->delete($e);

	}

}


class RefreshTokenPage extends \ModulePage {

	protected string $module = 'api\RefreshToken';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? RefreshTokenLib::getPropertiesCreate(),
		   $propertiesUpdate ?? RefreshTokenLib::getPropertiesUpdate()
		);
	}

}
?>