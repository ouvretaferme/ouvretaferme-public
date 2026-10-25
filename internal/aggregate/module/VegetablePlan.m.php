<?php
namespace aggregate;

abstract class VegetablePlanElement extends \Element {

	use \FilterElement;

	private static ?VegetablePlanModel $model = NULL;

	public static function getSelection(): array {
		return VegetablePlan::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): VegetablePlanModel {
		if(self::$model === NULL) {
			self::$model = new VegetablePlanModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('VegetablePlan::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class VegetablePlanModel extends \ModuleModel {

	protected string $module = 'aggregate\VegetablePlan';
	protected string $package = 'aggregate';
	protected string $table = 'aggregateVegetablePlan';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'season' => ['int16', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'series' => ['int32', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'cultivations' => ['int32', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'harvested' => ['int32', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'species' => ['int32', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'area' => ['float32', 'min' => 0.0, 'max' => NULL, 'cast' => 'float'],
			'greenhouse' => ['float32', 'min' => 0.0, 'max' => NULL, 'cast' => 'float'],
			'online' => ['bool', 'cast' => 'bool'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'season', 'series', 'cultivations', 'harvested', 'species', 'area', 'greenhouse', 'online', 'createdAt'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['structure', 'season'],
			['online']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'online' :
				return FALSE;

			case 'createdAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function select(...$fields): VegetablePlanModel {
		return parent::select(...$fields);
	}

	public function where(...$data): VegetablePlanModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): VegetablePlanModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): VegetablePlanModel {
		return $this->where('structure', ...$data);
	}

	public function whereSeason(...$data): VegetablePlanModel {
		return $this->where('season', ...$data);
	}

	public function whereSeries(...$data): VegetablePlanModel {
		return $this->where('series', ...$data);
	}

	public function whereCultivations(...$data): VegetablePlanModel {
		return $this->where('cultivations', ...$data);
	}

	public function whereHarvested(...$data): VegetablePlanModel {
		return $this->where('harvested', ...$data);
	}

	public function whereSpecies(...$data): VegetablePlanModel {
		return $this->where('species', ...$data);
	}

	public function whereArea(...$data): VegetablePlanModel {
		return $this->where('area', ...$data);
	}

	public function whereGreenhouse(...$data): VegetablePlanModel {
		return $this->where('greenhouse', ...$data);
	}

	public function whereOnline(...$data): VegetablePlanModel {
		return $this->where('online', ...$data);
	}

	public function whereCreatedAt(...$data): VegetablePlanModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class VegetablePlanCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): VegetablePlan {

		$e = new VegetablePlan();

		if(empty($id)) {
			VegetablePlan::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = VegetablePlan::getSelection();
		}

		if(VegetablePlan::model()
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
			$properties = VegetablePlan::getSelection();
		}

		if($sort !== NULL) {
			VegetablePlan::model()->sort($sort);
		}

		return VegetablePlan::model()
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

	public static function getNewElement(array $properties = []): VegetablePlan {

		return new VegetablePlan($properties);

	}

	public static function create(VegetablePlan $e): void {

		VegetablePlan::model()->insert($e);

	}

	public static function update(VegetablePlan $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = VegetablePlan::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, VegetablePlan $e, array $properties): void {

		self::$affected = VegetablePlan::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(VegetablePlan $e): void {

		$e->expects(['id']);

		self::$affected = VegetablePlan::model()->delete($e);

	}

}


class VegetablePlanPage extends \ModulePage {

	protected string $module = 'aggregate\VegetablePlan';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? VegetablePlanAiLib::getPropertiesCreate(),
		   $propertiesUpdate ?? VegetablePlanAiLib::getPropertiesUpdate()
		);
	}

}
?>