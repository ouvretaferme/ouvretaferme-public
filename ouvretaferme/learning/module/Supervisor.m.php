<?php
namespace learning;

abstract class SupervisorElement extends \Element {

	use \FilterElement;

	private static ?SupervisorModel $model = NULL;

	const ACTIVE = 'active';
	const INACTIVE = 'inactive';

	public static function getSelection(): array {
		return Supervisor::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): SupervisorModel {
		if(self::$model === NULL) {
			self::$model = new SupervisorModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Supervisor::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class SupervisorModel extends \ModuleModel {

	protected string $module = 'learning\Supervisor';
	protected string $package = 'learning';
	protected string $table = 'learningSupervisor';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'community' => ['element32', 'structure\Structure', 'null' => TRUE, 'cast' => 'element'],
			'name' => ['text8', 'min' => 1, 'max' => 100, 'cast' => 'string'],
			'role' => ['text8', 'min' => 1, 'max' => 100, 'null' => TRUE, 'cast' => 'string'],
			'description' => ['text8', 'min' => 1, 'max' => 250, 'null' => TRUE, 'cast' => 'string'],
			'email' => ['email', 'null' => TRUE, 'cast' => 'string'],
			'handicap' => ['bool', 'cast' => 'bool'],
			'contact' => ['bool', 'cast' => 'bool'],
			'status' => ['enum', [\learning\Supervisor::ACTIVE, \learning\Supervisor::INACTIVE], 'cast' => 'enum'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'community', 'name', 'role', 'description', 'email', 'handicap', 'contact', 'status'
		]);

		$this->propertiesToModule += [
			'community' => 'structure\Structure',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['community', 'status']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'handicap' :
				return FALSE;

			case 'contact' :
				return FALSE;

			case 'status' :
				return Supervisor::ACTIVE;

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

	public function select(...$fields): SupervisorModel {
		return parent::select(...$fields);
	}

	public function where(...$data): SupervisorModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): SupervisorModel {
		return $this->where('id', ...$data);
	}

	public function whereCommunity(...$data): SupervisorModel {
		return $this->where('community', ...$data);
	}

	public function whereName(...$data): SupervisorModel {
		return $this->where('name', ...$data);
	}

	public function whereRole(...$data): SupervisorModel {
		return $this->where('role', ...$data);
	}

	public function whereDescription(...$data): SupervisorModel {
		return $this->where('description', ...$data);
	}

	public function whereEmail(...$data): SupervisorModel {
		return $this->where('email', ...$data);
	}

	public function whereHandicap(...$data): SupervisorModel {
		return $this->where('handicap', ...$data);
	}

	public function whereContact(...$data): SupervisorModel {
		return $this->where('contact', ...$data);
	}

	public function whereStatus(...$data): SupervisorModel {
		return $this->where('status', ...$data);
	}


}


abstract class SupervisorCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Supervisor {

		$e = new Supervisor();

		if(empty($id)) {
			Supervisor::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Supervisor::getSelection();
		}

		if(Supervisor::model()
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
			$properties = Supervisor::getSelection();
		}

		if($sort !== NULL) {
			Supervisor::model()->sort($sort);
		}

		return Supervisor::model()
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

	public static function getNewElement(array $properties = []): Supervisor {

		return new Supervisor($properties);

	}

	public static function create(Supervisor $e): void {

		Supervisor::model()->insert($e);

	}

	public static function update(Supervisor $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Supervisor::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Supervisor $e, array $properties): void {

		self::$affected = Supervisor::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Supervisor $e): void {

		$e->expects(['id']);

		self::$affected = Supervisor::model()->delete($e);

	}

}


class SupervisorPage extends \ModulePage {

	protected string $module = 'learning\Supervisor';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? SupervisorLib::getPropertiesCreate(),
		   $propertiesUpdate ?? SupervisorLib::getPropertiesUpdate()
		);
	}

}
?>