<?php
namespace structure;

abstract class ProductionElement extends \Element {

	use \FilterElement;

	private static ?ProductionModel $model = NULL;

	const MANUAL = 'manual';
	const WALKING_TRACTOR = 'walking-tractor';
	const TRACTOR = 'tractor';

	const NO = 'no';
	const YES = 'yes';
	const CONVERSION = 'conversion';

	const ACTIVE = 'active';
	const INACTIVE = 'inactive';

	public static function getSelection(): array {
		return Production::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): ProductionModel {
		if(self::$model === NULL) {
			self::$model = new ProductionModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Production::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class ProductionModel extends \ModuleModel {

	protected string $module = 'structure\Production';
	protected string $package = 'structure';
	protected string $table = 'structureProduction';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'fqn' => ['text8', 'cast' => 'string'],
			'area' => ['float32', 'min' => 0, 'max' => 500, 'null' => TRUE, 'cast' => 'float'],
			'areaGreenhouse' => ['int32', 'min' => 50, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'mechanisation' => ['enum', [\structure\Production::MANUAL, \structure\Production::WALKING_TRACTOR, \structure\Production::TRACTOR], 'null' => TRUE, 'cast' => 'enum'],
			'organic' => ['enum', [\structure\Production::NO, \structure\Production::YES, \structure\Production::CONVERSION], 'cast' => 'enum'],
			'startedAt' => ['int16', 'min' => \Clock::year() - 100, 'max' => \Clock::year() + 10, 'null' => TRUE, 'cast' => 'int'],
			'description' => ['text8', 'min' => 1, 'max' => NULL, 'null' => TRUE, 'cast' => 'string'],
			'status' => ['enum', [\structure\Production::ACTIVE, \structure\Production::INACTIVE], 'cast' => 'enum'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'fqn', 'area', 'areaGreenhouse', 'mechanisation', 'organic', 'startedAt', 'description', 'status'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
		];

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['structure', 'fqn']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'status' :
				return Production::ACTIVE;

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'mechanisation' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'organic' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'status' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): ProductionModel {
		return parent::select(...$fields);
	}

	public function where(...$data): ProductionModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): ProductionModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): ProductionModel {
		return $this->where('structure', ...$data);
	}

	public function whereFqn(...$data): ProductionModel {
		return $this->where('fqn', ...$data);
	}

	public function whereArea(...$data): ProductionModel {
		return $this->where('area', ...$data);
	}

	public function whereAreaGreenhouse(...$data): ProductionModel {
		return $this->where('areaGreenhouse', ...$data);
	}

	public function whereMechanisation(...$data): ProductionModel {
		return $this->where('mechanisation', ...$data);
	}

	public function whereOrganic(...$data): ProductionModel {
		return $this->where('organic', ...$data);
	}

	public function whereStartedAt(...$data): ProductionModel {
		return $this->where('startedAt', ...$data);
	}

	public function whereDescription(...$data): ProductionModel {
		return $this->where('description', ...$data);
	}

	public function whereStatus(...$data): ProductionModel {
		return $this->where('status', ...$data);
	}


}


abstract class ProductionCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Production {

		$e = new Production();

		if(empty($id)) {
			Production::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Production::getSelection();
		}

		if(Production::model()
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
			$properties = Production::getSelection();
		}

		if($sort !== NULL) {
			Production::model()->sort($sort);
		}

		return Production::model()
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

	public static function getNewElement(array $properties = []): Production {

		return new Production($properties);

	}

	public static function create(Production $e): void {

		Production::model()->insert($e);

	}

	public static function update(Production $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Production::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Production $e, array $properties): void {

		self::$affected = Production::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Production $e): void {

		$e->expects(['id']);

		self::$affected = Production::model()->delete($e);

	}

}


class ProductionPage extends \ModulePage {

	protected string $module = 'structure\Production';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? ProductionLib::getPropertiesCreate(),
		   $propertiesUpdate ?? ProductionLib::getPropertiesUpdate()
		);
	}

}
?>