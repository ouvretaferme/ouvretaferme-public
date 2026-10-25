<?php
namespace api;

abstract class AccessTokenElement extends \Element {

	use \FilterElement;

	private static ?AccessTokenModel $model = NULL;

	public static function getSelection(): array {
		return AccessToken::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): AccessTokenModel {
		if(self::$model === NULL) {
			self::$model = new AccessTokenModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('AccessToken::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class AccessTokenModel extends \ModuleModel {

	protected string $module = 'api\AccessToken';
	protected string $package = 'api';
	protected string $table = 'apiAccessToken';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'tokenId' => ['text8', 'min' => 128, 'max' => 128, 'charset' => 'ascii', 'unique' => TRUE, 'cast' => 'string'],
			'client' => ['element32', 'api\Client', 'cast' => 'element'],
			'user' => ['element32', 'user\User', 'cast' => 'element'],
			'reference' => ['int32', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'name' => ['text8', 'min' => 1, 'max' => NULL, 'null' => TRUE, 'cast' => 'string'],
			'scopes' => ['json', 'cast' => 'array'],
			'expiryDateTime' => ['datetime', 'cast' => 'string'],
			'revoked' => ['bool', 'cast' => 'bool'],
			'revokedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'tokenId', 'client', 'user', 'reference', 'name', 'scopes', 'expiryDateTime', 'revoked', 'revokedAt', 'createdAt'
		]);

		$this->propertiesToModule += [
			'client' => 'api\Client',
			'user' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['user'],
			['reference'],
			['client']
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['tokenId']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'scopes' :
				return [];

			case 'revoked' :
				return FALSE;

			case 'createdAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'scopes' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'scopes' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			default :
				return parent::decode($property, $value);

		}

	}

	public function select(...$fields): AccessTokenModel {
		return parent::select(...$fields);
	}

	public function where(...$data): AccessTokenModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): AccessTokenModel {
		return $this->where('id', ...$data);
	}

	public function whereTokenId(...$data): AccessTokenModel {
		return $this->where('tokenId', ...$data);
	}

	public function whereClient(...$data): AccessTokenModel {
		return $this->where('client', ...$data);
	}

	public function whereUser(...$data): AccessTokenModel {
		return $this->where('user', ...$data);
	}

	public function whereReference(...$data): AccessTokenModel {
		return $this->where('reference', ...$data);
	}

	public function whereName(...$data): AccessTokenModel {
		return $this->where('name', ...$data);
	}

	public function whereScopes(...$data): AccessTokenModel {
		return $this->where('scopes', ...$data);
	}

	public function whereExpiryDateTime(...$data): AccessTokenModel {
		return $this->where('expiryDateTime', ...$data);
	}

	public function whereRevoked(...$data): AccessTokenModel {
		return $this->where('revoked', ...$data);
	}

	public function whereRevokedAt(...$data): AccessTokenModel {
		return $this->where('revokedAt', ...$data);
	}

	public function whereCreatedAt(...$data): AccessTokenModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class AccessTokenCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): AccessToken {

		$e = new AccessToken();

		if(empty($id)) {
			AccessToken::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = AccessToken::getSelection();
		}

		if(AccessToken::model()
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
			$properties = AccessToken::getSelection();
		}

		if($sort !== NULL) {
			AccessToken::model()->sort($sort);
		}

		return AccessToken::model()
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

	public static function getNewElement(array $properties = []): AccessToken {

		return new AccessToken($properties);

	}

	public static function create(AccessToken $e): void {

		AccessToken::model()->insert($e);

	}

	public static function update(AccessToken $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = AccessToken::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, AccessToken $e, array $properties): void {

		self::$affected = AccessToken::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(AccessToken $e): void {

		$e->expects(['id']);

		self::$affected = AccessToken::model()->delete($e);

	}

}


class AccessTokenPage extends \ModulePage {

	protected string $module = 'api\AccessToken';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? AccessTokenLib::getPropertiesCreate(),
		   $propertiesUpdate ?? AccessTokenLib::getPropertiesUpdate()
		);
	}

}
?>