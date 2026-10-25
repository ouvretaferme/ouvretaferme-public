<?php
namespace api;

abstract class ClientElement extends \Element {

	use \FilterElement;

	private static ?ClientModel $model = NULL;

	const ACTIVE = 'active';
	const DISABLED = 'disabled';

	public static function getSelection(): array {
		return Client::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): ClientModel {
		if(self::$model === NULL) {
			self::$model = new ClientModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Client::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class ClientModel extends \ModuleModel {

	protected string $module = 'api\Client';
	protected string $package = 'api';
	protected string $table = 'apiClient';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'clientId' => ['text8', 'min' => 1, 'max' => NULL, 'charset' => 'ascii', 'unique' => TRUE, 'cast' => 'string'],
			'name' => ['text8', 'min' => 1, 'max' => NULL, 'cast' => 'string'],
			'secret' => ['text8', 'min' => 1, 'max' => NULL, 'charset' => 'ascii', 'null' => TRUE, 'cast' => 'string'],
			'redirectUris' => ['json', 'cast' => 'array'],
			'isConfidential' => ['bool', 'cast' => 'bool'],
			'isPersonal' => ['bool', 'cast' => 'bool'],
			'status' => ['enum', [\api\Client::ACTIVE, \api\Client::DISABLED], 'cast' => 'enum'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'clientId', 'name', 'secret', 'redirectUris', 'isConfidential', 'isPersonal', 'status', 'createdAt'
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['clientId']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'redirectUris' :
				return [];

			case 'isConfidential' :
				return FALSE;

			case 'isPersonal' :
				return FALSE;

			case 'status' :
				return Client::ACTIVE;

			case 'createdAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'redirectUris' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			case 'status' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'redirectUris' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			default :
				return parent::decode($property, $value);

		}

	}

	public function select(...$fields): ClientModel {
		return parent::select(...$fields);
	}

	public function where(...$data): ClientModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): ClientModel {
		return $this->where('id', ...$data);
	}

	public function whereClientId(...$data): ClientModel {
		return $this->where('clientId', ...$data);
	}

	public function whereName(...$data): ClientModel {
		return $this->where('name', ...$data);
	}

	public function whereSecret(...$data): ClientModel {
		return $this->where('secret', ...$data);
	}

	public function whereRedirectUris(...$data): ClientModel {
		return $this->where('redirectUris', ...$data);
	}

	public function whereIsConfidential(...$data): ClientModel {
		return $this->where('isConfidential', ...$data);
	}

	public function whereIsPersonal(...$data): ClientModel {
		return $this->where('isPersonal', ...$data);
	}

	public function whereStatus(...$data): ClientModel {
		return $this->where('status', ...$data);
	}

	public function whereCreatedAt(...$data): ClientModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class ClientCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Client {

		$e = new Client();

		if(empty($id)) {
			Client::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Client::getSelection();
		}

		if(Client::model()
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
			$properties = Client::getSelection();
		}

		if($sort !== NULL) {
			Client::model()->sort($sort);
		}

		return Client::model()
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

	public static function getNewElement(array $properties = []): Client {

		return new Client($properties);

	}

	public static function create(Client $e): void {

		Client::model()->insert($e);

	}

	public static function update(Client $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Client::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Client $e, array $properties): void {

		self::$affected = Client::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Client $e): void {

		$e->expects(['id']);

		self::$affected = Client::model()->delete($e);

	}

}


class ClientPage extends \ModulePage {

	protected string $module = 'api\Client';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? ClientLib::getPropertiesCreate(),
		   $propertiesUpdate ?? ClientLib::getPropertiesUpdate()
		);
	}

}
?>