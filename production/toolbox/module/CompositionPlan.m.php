<?php
namespace toolbox;

abstract class CompositionPlanElement extends \Element {

	use \FilterElement;

	private static ?CompositionPlanModel $model = NULL;

	const CULTIVATION = 'cultivation';
	const COMMUNITY = 'community';
	const CALENDAR = 'calendar';

	const OCEANIC = 'oceanic';
	const CONTINENTAL = 'continental';
	const MEDITERRANEAN = 'mediterranean';
	const MOUNTAIN = 'mountain';

	public static function getSelection(): array {
		return CompositionPlan::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): CompositionPlanModel {
		if(self::$model === NULL) {
			self::$model = new CompositionPlanModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('CompositionPlan::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class CompositionPlanModel extends \ModuleModel {

	protected string $module = 'toolbox\CompositionPlan';
	protected string $package = 'toolbox';
	protected string $table = 'toolboxCompositionPlan';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'unique' => TRUE, 'cast' => 'element'],
			'season' => ['int16', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'reference' => ['enum', [\toolbox\CompositionPlan::CULTIVATION, \toolbox\CompositionPlan::COMMUNITY, \toolbox\CompositionPlan::CALENDAR], 'cast' => 'enum'],
			'climate' => ['enum', [\toolbox\CompositionPlan::OCEANIC, \toolbox\CompositionPlan::CONTINENTAL, \toolbox\CompositionPlan::MEDITERRANEAN, \toolbox\CompositionPlan::MOUNTAIN], 'null' => TRUE, 'cast' => 'enum'],
			'weeks' => ['json', 'cast' => 'array'],
			'createdAt' => ['datetime', 'cast' => 'string'],
			'updatedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'season', 'reference', 'climate', 'weeks', 'createdAt', 'updatedAt'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
		];

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['structure']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'reference' :
				return CompositionPlan::COMMUNITY;

			case 'createdAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'reference' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'climate' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'weeks' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'weeks' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			default :
				return parent::decode($property, $value);

		}

	}

	public function select(...$fields): CompositionPlanModel {
		return parent::select(...$fields);
	}

	public function where(...$data): CompositionPlanModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): CompositionPlanModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): CompositionPlanModel {
		return $this->where('structure', ...$data);
	}

	public function whereSeason(...$data): CompositionPlanModel {
		return $this->where('season', ...$data);
	}

	public function whereReference(...$data): CompositionPlanModel {
		return $this->where('reference', ...$data);
	}

	public function whereClimate(...$data): CompositionPlanModel {
		return $this->where('climate', ...$data);
	}

	public function whereWeeks(...$data): CompositionPlanModel {
		return $this->where('weeks', ...$data);
	}

	public function whereCreatedAt(...$data): CompositionPlanModel {
		return $this->where('createdAt', ...$data);
	}

	public function whereUpdatedAt(...$data): CompositionPlanModel {
		return $this->where('updatedAt', ...$data);
	}


}


abstract class CompositionPlanCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): CompositionPlan {

		$e = new CompositionPlan();

		if(empty($id)) {
			CompositionPlan::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = CompositionPlan::getSelection();
		}

		if(CompositionPlan::model()
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
			$properties = CompositionPlan::getSelection();
		}

		if($sort !== NULL) {
			CompositionPlan::model()->sort($sort);
		}

		return CompositionPlan::model()
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

	public static function getNewElement(array $properties = []): CompositionPlan {

		return new CompositionPlan($properties);

	}

	public static function create(CompositionPlan $e): void {

		CompositionPlan::model()->insert($e);

	}

	public static function update(CompositionPlan $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = CompositionPlan::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, CompositionPlan $e, array $properties): void {

		self::$affected = CompositionPlan::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(CompositionPlan $e): void {

		$e->expects(['id']);

		self::$affected = CompositionPlan::model()->delete($e);

	}

}


class CompositionPlanPage extends \ModulePage {

	protected string $module = 'toolbox\CompositionPlan';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? CompositionPlanLib::getPropertiesCreate(),
		   $propertiesUpdate ?? CompositionPlanLib::getPropertiesUpdate()
		);
	}

}
?>