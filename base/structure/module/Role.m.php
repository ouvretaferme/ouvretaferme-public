<?php
namespace structure;

abstract class RoleElement extends \Element {

	use \FilterElement;

	private static ?RoleModel $model = NULL;

	const PUBLIC = 'public';
	const PRIVATE = 'private';

	const LIMITED = 'limited';
	const FULL = 'full';
	const DENIED = 'denied';

	const ALL = 'all';
	const OWN = 'own';

	public static function getSelection(): array {
		return Role::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): RoleModel {
		if(self::$model === NULL) {
			self::$model = new RoleModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Role::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class RoleModel extends \ModuleModel {

	protected string $module = 'structure\Role';
	protected string $package = 'structure';
	protected string $table = 'structureRole';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'name' => ['text8', 'min' => 1, 'max' => 20, 'cast' => 'string'],
			'fqn' => ['fqn', 'null' => TRUE, 'cast' => 'string'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'visibility' => ['enum', [\structure\Role::PUBLIC, \structure\Role::PRIVATE], 'cast' => 'enum'],
			'admin' => ['bool', 'cast' => 'bool'],
			'planning' => ['enum', [\structure\Role::LIMITED, \structure\Role::FULL, \structure\Role::DENIED], 'cast' => 'enum'],
			'selling' => ['bool', 'cast' => 'bool'],
			'productionAnalyze' => ['bool', 'cast' => 'bool'],
			'productionObserve' => ['bool', 'cast' => 'bool'],
			'productionExport' => ['bool', 'cast' => 'bool'],
			'commercialisationAnalyze' => ['bool', 'cast' => 'bool'],
			'accounting' => ['enum', [\structure\Role::LIMITED, \structure\Role::FULL, \structure\Role::DENIED], 'cast' => 'enum'],
			'accountingAnalyze' => ['bool', 'cast' => 'bool'],
			'sections' => ['enum', [\structure\Role::ALL, \structure\Role::OWN], 'cast' => 'enum'],
			'locked' => ['bool', 'cast' => 'bool'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'name', 'fqn', 'structure', 'visibility', 'admin', 'planning', 'selling', 'productionAnalyze', 'productionObserve', 'productionExport', 'commercialisationAnalyze', 'accounting', 'accountingAnalyze', 'sections', 'locked', 'createdAt'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
		];

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['structure', 'name']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'visibility' :
				return Role::PUBLIC;

			case 'admin' :
				return FALSE;

			case 'planning' :
				return Role::DENIED;

			case 'selling' :
				return FALSE;

			case 'productionAnalyze' :
				return FALSE;

			case 'productionObserve' :
				return FALSE;

			case 'productionExport' :
				return FALSE;

			case 'commercialisationAnalyze' :
				return FALSE;

			case 'accounting' :
				return Role::DENIED;

			case 'accountingAnalyze' :
				return FALSE;

			case 'sections' :
				return Role::ALL;

			case 'locked' :
				return FALSE;

			case 'createdAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'visibility' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'planning' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'accounting' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'sections' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): RoleModel {
		return parent::select(...$fields);
	}

	public function where(...$data): RoleModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): RoleModel {
		return $this->where('id', ...$data);
	}

	public function whereName(...$data): RoleModel {
		return $this->where('name', ...$data);
	}

	public function whereFqn(...$data): RoleModel {
		return $this->where('fqn', ...$data);
	}

	public function whereStructure(...$data): RoleModel {
		return $this->where('structure', ...$data);
	}

	public function whereVisibility(...$data): RoleModel {
		return $this->where('visibility', ...$data);
	}

	public function whereAdmin(...$data): RoleModel {
		return $this->where('admin', ...$data);
	}

	public function wherePlanning(...$data): RoleModel {
		return $this->where('planning', ...$data);
	}

	public function whereSelling(...$data): RoleModel {
		return $this->where('selling', ...$data);
	}

	public function whereProductionAnalyze(...$data): RoleModel {
		return $this->where('productionAnalyze', ...$data);
	}

	public function whereProductionObserve(...$data): RoleModel {
		return $this->where('productionObserve', ...$data);
	}

	public function whereProductionExport(...$data): RoleModel {
		return $this->where('productionExport', ...$data);
	}

	public function whereCommercialisationAnalyze(...$data): RoleModel {
		return $this->where('commercialisationAnalyze', ...$data);
	}

	public function whereAccounting(...$data): RoleModel {
		return $this->where('accounting', ...$data);
	}

	public function whereAccountingAnalyze(...$data): RoleModel {
		return $this->where('accountingAnalyze', ...$data);
	}

	public function whereSections(...$data): RoleModel {
		return $this->where('sections', ...$data);
	}

	public function whereLocked(...$data): RoleModel {
		return $this->where('locked', ...$data);
	}

	public function whereCreatedAt(...$data): RoleModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class RoleCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Role {

		$e = new Role();

		if(empty($id)) {
			Role::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Role::getSelection();
		}

		if(Role::model()
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
			$properties = Role::getSelection();
		}

		if($sort !== NULL) {
			Role::model()->sort($sort);
		}

		return Role::model()
			->select($properties)
			->whereId('IN', $ids)
			->getCollection(NULL, NULL, $index);

	}

	public static function getByFqn(string $fqn, array $properties = []): Role {

		$e = new Role();

		if(empty($fqn)) {
			Role::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Role::getSelection();
		}

		if(Role::model()
			->select($properties)
			->whereFqn($fqn)
			->get($e) === FALSE) {
				$e->setGhost($fqn);
		}

		return $e;

	}

	public static function getByFqns(array $fqns, array $properties = []): \Collection {

		if(empty($fqns)) {
			Role::model()->reset();
			return new \Collection();
		}

		if($properties === []) {
			$properties = Role::getSelection();
		}

		return Role::model()
			->select($properties)
			->whereFqn('IN', $fqns)
			->getCollection(NULL, NULL, 'fqn');

	}

	public static function getCache(mixed $key, \Closure $callback): mixed {
		self::$cache[$key] ??= $callback();
		return self::$cache[$key];
	}

	public static function getAffected(): ?int {
		return self::$affected;
	}

	public static function getNewElement(array $properties = []): Role {

		return new Role($properties);

	}

	public static function create(Role $e): void {

		Role::model()->insert($e);

	}

	public static function update(Role $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Role::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Role $e, array $properties): void {

		self::$affected = Role::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Role $e): void {

		$e->expects(['id']);

		self::$affected = Role::model()->delete($e);

	}

}


class RolePage extends \ModulePage {

	protected string $module = 'structure\Role';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? RoleLib::getPropertiesCreate(),
		   $propertiesUpdate ?? RoleLib::getPropertiesUpdate()
		);
	}

}
?>