<?php
namespace work;

abstract class PhytoUsageElement extends \Element {

	use \FilterElement;

	private static ?PhytoUsageModel $model = NULL;

	public static function getSelection(): array {
		return PhytoUsage::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): PhytoUsageModel {
		if(self::$model === NULL) {
			self::$model = new PhytoUsageModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('PhytoUsage::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class PhytoUsageModel extends \ModuleModel {

	protected string $module = 'work\PhytoUsage';
	protected string $package = 'work';
	protected string $table = 'workPhytoUsage';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'product' => ['element32', 'work\PhytoProduct', 'cast' => 'element'],
			'crop' => ['text8', 'min' => 1, 'max' => NULL, 'collate' => 'general', 'cast' => 'string'],
			'treatment' => ['text8', 'null' => TRUE, 'cast' => 'string'],
			'target' => ['text8', 'collate' => 'general', 'null' => TRUE, 'cast' => 'string'],
			'dose' => ['float32', 'min' => 0.0, 'max' => NULL, 'null' => TRUE, 'cast' => 'float'],
			'doseUnit' => ['text8', 'null' => TRUE, 'cast' => 'string'],
			'harvestDelay' => ['int16', 'min' => 0, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'plants' => ['json', 'cast' => 'array'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'product', 'crop', 'treatment', 'target', 'dose', 'doseUnit', 'harvestDelay', 'plants'
		]);

		$this->propertiesToModule += [
			'product' => 'work\PhytoProduct',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['product']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'plants' :
				return [];

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'plants' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'plants' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			default :
				return parent::decode($property, $value);

		}

	}

	public function select(...$fields): PhytoUsageModel {
		return parent::select(...$fields);
	}

	public function where(...$data): PhytoUsageModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): PhytoUsageModel {
		return $this->where('id', ...$data);
	}

	public function whereProduct(...$data): PhytoUsageModel {
		return $this->where('product', ...$data);
	}

	public function whereCrop(...$data): PhytoUsageModel {
		return $this->where('crop', ...$data);
	}

	public function whereTreatment(...$data): PhytoUsageModel {
		return $this->where('treatment', ...$data);
	}

	public function whereTarget(...$data): PhytoUsageModel {
		return $this->where('target', ...$data);
	}

	public function whereDose(...$data): PhytoUsageModel {
		return $this->where('dose', ...$data);
	}

	public function whereDoseUnit(...$data): PhytoUsageModel {
		return $this->where('doseUnit', ...$data);
	}

	public function whereHarvestDelay(...$data): PhytoUsageModel {
		return $this->where('harvestDelay', ...$data);
	}

	public function wherePlants(...$data): PhytoUsageModel {
		return $this->where('plants', ...$data);
	}


}


abstract class PhytoUsageCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): PhytoUsage {

		$e = new PhytoUsage();

		if(empty($id)) {
			PhytoUsage::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = PhytoUsage::getSelection();
		}

		if(PhytoUsage::model()
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
			$properties = PhytoUsage::getSelection();
		}

		if($sort !== NULL) {
			PhytoUsage::model()->sort($sort);
		}

		return PhytoUsage::model()
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

	public static function getNewElement(array $properties = []): PhytoUsage {

		return new PhytoUsage($properties);

	}

	public static function create(PhytoUsage $e): void {

		PhytoUsage::model()->insert($e);

	}

	public static function update(PhytoUsage $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = PhytoUsage::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, PhytoUsage $e, array $properties): void {

		self::$affected = PhytoUsage::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(PhytoUsage $e): void {

		$e->expects(['id']);

		self::$affected = PhytoUsage::model()->delete($e);

	}

}


class PhytoUsagePage extends \ModulePage {

	protected string $module = 'work\PhytoUsage';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? PhytoUsageLib::getPropertiesCreate(),
		   $propertiesUpdate ?? PhytoUsageLib::getPropertiesUpdate()
		);
	}

}
?>