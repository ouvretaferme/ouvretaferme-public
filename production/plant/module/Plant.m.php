<?php
namespace plant;

abstract class PlantElement extends \Element {

	use \FilterElement;

	private static ?PlantModel $model = NULL;

	const ANNUAL = 'annual';
	const PERENNIAL = 'perennial';

	const LEAF = 'leaf';
	const ROOT = 'root';
	const FRUIT = 'fruit';

	const ACTIVE = 'active';
	const INACTIVE = 'inactive';

	public static function getSelection(): array {
		return Plant::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): PlantModel {
		if(self::$model === NULL) {
			self::$model = new PlantModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Plant::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class PlantModel extends \ModuleModel {

	protected string $module = 'plant\Plant';
	protected string $package = 'plant';
	protected string $table = 'plant';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'name' => ['text8', 'min' => 1, 'max' => NULL, 'collate' => 'general', 'cast' => 'string'],
			'fqn' => ['text8', 'null' => TRUE, 'cast' => 'string'],
			'reference' => ['element32', 'plant\Plant', 'null' => TRUE, 'cast' => 'element'],
			'aliases' => ['text8', 'min' => 0, 'max' => NULL, 'null' => TRUE, 'cast' => 'string'],
			'tagsAccepted' => ['json', 'cast' => 'array'],
			'color' => ['color', 'cast' => 'string'],
			'structure' => ['element32', 'structure\Structure', 'null' => TRUE, 'cast' => 'element'],
			'family' => ['element32', 'plant\Family', 'null' => TRUE, 'cast' => 'element'],
			'vignette' => ['text8', 'min' => 30, 'max' => 30, 'null' => TRUE, 'cast' => 'string'],
			'plantsSafetyMargin' => ['int8', 'min' => 1, 'max' => 100, 'null' => TRUE, 'cast' => 'int'],
			'seedsSafetyMargin' => ['int8', 'min' => 1, 'max' => 100, 'null' => TRUE, 'cast' => 'int'],
			'storage' => ['int8', 'min' => 0, 'max' => 52, 'null' => TRUE, 'cast' => 'int'],
			'crateWeight' => ['decimal', 'digits' => 5, 'decimal' => 2, 'min' => 0.1, 'max' => 100, 'null' => TRUE, 'cast' => 'float'],
			'cycle' => ['enum', [\plant\Plant::ANNUAL, \plant\Plant::PERENNIAL], 'cast' => 'enum'],
			'organ' => ['enum', [\plant\Plant::LEAF, \plant\Plant::ROOT, \plant\Plant::FRUIT], 'null' => TRUE, 'cast' => 'enum'],
			'usageVegetable' => ['bool', 'cast' => 'bool'],
			'usageFruit' => ['bool', 'cast' => 'bool'],
			'usageSmallFruit' => ['bool', 'cast' => 'bool'],
			'usageFlower' => ['bool', 'cast' => 'bool'],
			'usageAromatic' => ['bool', 'cast' => 'bool'],
			'usageMushroom' => ['bool', 'cast' => 'bool'],
			'usageField' => ['bool', 'cast' => 'bool'],
			'status' => ['enum', [\plant\Plant::ACTIVE, \plant\Plant::INACTIVE], 'cast' => 'enum'],
			'criteria' => ['json', 'cast' => 'array'],
			'createdAt' => ['date', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'name', 'fqn', 'reference', 'aliases', 'tagsAccepted', 'color', 'structure', 'family', 'vignette', 'plantsSafetyMargin', 'seedsSafetyMargin', 'storage', 'crateWeight', 'cycle', 'organ', 'usageVegetable', 'usageFruit', 'usageSmallFruit', 'usageFlower', 'usageAromatic', 'usageMushroom', 'usageField', 'status', 'criteria', 'createdAt'
		]);

		$this->propertiesToModule += [
			'reference' => 'plant\Plant',
			'structure' => 'structure\Structure',
			'family' => 'plant\Family',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['fqn'],
			['family']
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['structure', 'name']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'tagsAccepted' :
				return [];

			case 'color' :
				return '#373737';

			case 'usageVegetable' :
				return FALSE;

			case 'usageFruit' :
				return FALSE;

			case 'usageSmallFruit' :
				return FALSE;

			case 'usageFlower' :
				return FALSE;

			case 'usageAromatic' :
				return FALSE;

			case 'usageMushroom' :
				return FALSE;

			case 'usageField' :
				return FALSE;

			case 'status' :
				return Plant::ACTIVE;

			case 'criteria' :
				return [];

			case 'createdAt' :
				return new \Sql('CURDATE()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'tagsAccepted' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			case 'cycle' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'organ' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'status' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'criteria' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'tagsAccepted' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			case 'criteria' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			default :
				return parent::decode($property, $value);

		}

	}

	public function select(...$fields): PlantModel {
		return parent::select(...$fields);
	}

	public function where(...$data): PlantModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): PlantModel {
		return $this->where('id', ...$data);
	}

	public function whereName(...$data): PlantModel {
		return $this->where('name', ...$data);
	}

	public function whereFqn(...$data): PlantModel {
		return $this->where('fqn', ...$data);
	}

	public function whereReference(...$data): PlantModel {
		return $this->where('reference', ...$data);
	}

	public function whereAliases(...$data): PlantModel {
		return $this->where('aliases', ...$data);
	}

	public function whereTagsAccepted(...$data): PlantModel {
		return $this->where('tagsAccepted', ...$data);
	}

	public function whereColor(...$data): PlantModel {
		return $this->where('color', ...$data);
	}

	public function whereStructure(...$data): PlantModel {
		return $this->where('structure', ...$data);
	}

	public function whereFamily(...$data): PlantModel {
		return $this->where('family', ...$data);
	}

	public function whereVignette(...$data): PlantModel {
		return $this->where('vignette', ...$data);
	}

	public function wherePlantsSafetyMargin(...$data): PlantModel {
		return $this->where('plantsSafetyMargin', ...$data);
	}

	public function whereSeedsSafetyMargin(...$data): PlantModel {
		return $this->where('seedsSafetyMargin', ...$data);
	}

	public function whereStorage(...$data): PlantModel {
		return $this->where('storage', ...$data);
	}

	public function whereCrateWeight(...$data): PlantModel {
		return $this->where('crateWeight', ...$data);
	}

	public function whereCycle(...$data): PlantModel {
		return $this->where('cycle', ...$data);
	}

	public function whereOrgan(...$data): PlantModel {
		return $this->where('organ', ...$data);
	}

	public function whereUsageVegetable(...$data): PlantModel {
		return $this->where('usageVegetable', ...$data);
	}

	public function whereUsageFruit(...$data): PlantModel {
		return $this->where('usageFruit', ...$data);
	}

	public function whereUsageSmallFruit(...$data): PlantModel {
		return $this->where('usageSmallFruit', ...$data);
	}

	public function whereUsageFlower(...$data): PlantModel {
		return $this->where('usageFlower', ...$data);
	}

	public function whereUsageAromatic(...$data): PlantModel {
		return $this->where('usageAromatic', ...$data);
	}

	public function whereUsageMushroom(...$data): PlantModel {
		return $this->where('usageMushroom', ...$data);
	}

	public function whereUsageField(...$data): PlantModel {
		return $this->where('usageField', ...$data);
	}

	public function whereStatus(...$data): PlantModel {
		return $this->where('status', ...$data);
	}

	public function whereCriteria(...$data): PlantModel {
		return $this->where('criteria', ...$data);
	}

	public function whereCreatedAt(...$data): PlantModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class PlantCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Plant {

		$e = new Plant();

		if(empty($id)) {
			Plant::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Plant::getSelection();
		}

		if(Plant::model()
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
			$properties = Plant::getSelection();
		}

		if($sort !== NULL) {
			Plant::model()->sort($sort);
		}

		return Plant::model()
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

	public static function getNewElement(array $properties = []): Plant {

		return new Plant($properties);

	}

	public static function create(Plant $e): void {

		Plant::model()->insert($e);

	}

	public static function update(Plant $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Plant::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Plant $e, array $properties): void {

		self::$affected = Plant::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Plant $e): void {

		$e->expects(['id']);

		self::$affected = Plant::model()->delete($e);

	}

}


class PlantPage extends \ModulePage {

	protected string $module = 'plant\Plant';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? PlantLib::getPropertiesCreate(),
		   $propertiesUpdate ?? PlantLib::getPropertiesUpdate()
		);
	}

}
?>