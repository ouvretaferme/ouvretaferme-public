<?php
namespace api;

abstract class ClientScopeElement extends \Element {

	use \FilterElement;

	private static ?ClientScopeModel $model = NULL;

	public static function getSelection(): array {
		return ClientScope::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): ClientScopeModel {
		if(self::$model === NULL) {
			self::$model = new ClientScopeModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('ClientScope::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class ClientScopeModel extends \ModuleModel {

	protected string $module = 'api\ClientScope';
	protected string $package = 'api';
	protected string $table = 'apiClientScope';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'client' => ['element32', 'api\Client', 'cast' => 'element'],
			'scope' => ['element32', 'api\Scope', 'cast' => 'element'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'client', 'scope'
		]);

		$this->propertiesToModule += [
			'client' => 'api\Client',
			'scope' => 'api\Scope',
		];

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['client', 'scope']
		]);

	}

	public function select(...$fields): ClientScopeModel {
		return parent::select(...$fields);
	}

	public function where(...$data): ClientScopeModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): ClientScopeModel {
		return $this->where('id', ...$data);
	}

	public function whereClient(...$data): ClientScopeModel {
		return $this->where('client', ...$data);
	}

	public function whereScope(...$data): ClientScopeModel {
		return $this->where('scope', ...$data);
	}


}


abstract class ClientScopeCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): ClientScope {

		$e = new ClientScope();

		if(empty($id)) {
			ClientScope::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = ClientScope::getSelection();
		}

		if(ClientScope::model()
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
			$properties = ClientScope::getSelection();
		}

		if($sort !== NULL) {
			ClientScope::model()->sort($sort);
		}

		return ClientScope::model()
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

	public static function getNewElement(array $properties = []): ClientScope {

		return new ClientScope($properties);

	}

	public static function create(ClientScope $e): void {

		ClientScope::model()->insert($e);

	}

	public static function update(ClientScope $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = ClientScope::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, ClientScope $e, array $properties): void {

		self::$affected = ClientScope::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(ClientScope $e): void {

		$e->expects(['id']);

		self::$affected = ClientScope::model()->delete($e);

	}

}


class ClientScopePage extends \ModulePage {

	protected string $module = 'api\ClientScope';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? ClientScopeLib::getPropertiesCreate(),
		   $propertiesUpdate ?? ClientScopeLib::getPropertiesUpdate()
		);
	}

}
?>