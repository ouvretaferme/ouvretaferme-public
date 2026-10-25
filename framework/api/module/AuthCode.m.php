<?php
namespace api;

abstract class AuthCodeElement extends \Element {

	use \FilterElement;

	private static ?AuthCodeModel $model = NULL;

	public static function getSelection(): array {
		return AuthCode::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): AuthCodeModel {
		if(self::$model === NULL) {
			self::$model = new AuthCodeModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('AuthCode::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class AuthCodeModel extends \ModuleModel {

	protected string $module = 'api\AuthCode';
	protected string $package = 'api';
	protected string $table = 'apiAuthCode';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'code' => ['text8', 'min' => 128, 'max' => 128, 'charset' => 'ascii', 'unique' => TRUE, 'cast' => 'string'],
			'client' => ['element32', 'api\Client', 'cast' => 'element'],
			'user' => ['element32', 'user\User', 'cast' => 'element'],
			'reference' => ['int32', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'scopes' => ['json', 'cast' => 'array'],
			'redirectUri' => ['text8', 'min' => 1, 'max' => NULL, 'cast' => 'string'],
			'expiryDateTime' => ['datetime', 'cast' => 'string'],
			'revoked' => ['bool', 'cast' => 'bool'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'code', 'client', 'user', 'reference', 'scopes', 'redirectUri', 'expiryDateTime', 'revoked', 'createdAt'
		]);

		$this->propertiesToModule += [
			'client' => 'api\Client',
			'user' => 'user\User',
		];

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['code']
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

	public function select(...$fields): AuthCodeModel {
		return parent::select(...$fields);
	}

	public function where(...$data): AuthCodeModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): AuthCodeModel {
		return $this->where('id', ...$data);
	}

	public function whereCode(...$data): AuthCodeModel {
		return $this->where('code', ...$data);
	}

	public function whereClient(...$data): AuthCodeModel {
		return $this->where('client', ...$data);
	}

	public function whereUser(...$data): AuthCodeModel {
		return $this->where('user', ...$data);
	}

	public function whereReference(...$data): AuthCodeModel {
		return $this->where('reference', ...$data);
	}

	public function whereScopes(...$data): AuthCodeModel {
		return $this->where('scopes', ...$data);
	}

	public function whereRedirectUri(...$data): AuthCodeModel {
		return $this->where('redirectUri', ...$data);
	}

	public function whereExpiryDateTime(...$data): AuthCodeModel {
		return $this->where('expiryDateTime', ...$data);
	}

	public function whereRevoked(...$data): AuthCodeModel {
		return $this->where('revoked', ...$data);
	}

	public function whereCreatedAt(...$data): AuthCodeModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class AuthCodeCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): AuthCode {

		$e = new AuthCode();

		if(empty($id)) {
			AuthCode::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = AuthCode::getSelection();
		}

		if(AuthCode::model()
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
			$properties = AuthCode::getSelection();
		}

		if($sort !== NULL) {
			AuthCode::model()->sort($sort);
		}

		return AuthCode::model()
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

	public static function getNewElement(array $properties = []): AuthCode {

		return new AuthCode($properties);

	}

	public static function create(AuthCode $e): void {

		AuthCode::model()->insert($e);

	}

	public static function update(AuthCode $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = AuthCode::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, AuthCode $e, array $properties): void {

		self::$affected = AuthCode::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(AuthCode $e): void {

		$e->expects(['id']);

		self::$affected = AuthCode::model()->delete($e);

	}

}


class AuthCodePage extends \ModulePage {

	protected string $module = 'api\AuthCode';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? AuthCodeLib::getPropertiesCreate(),
		   $propertiesUpdate ?? AuthCodeLib::getPropertiesUpdate()
		);
	}

}
?>