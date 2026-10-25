<?php
namespace aggregate;

abstract class VegetableHoursElement extends \Element {

	use \FilterElement;

	private static ?VegetableHoursModel $model = NULL;

	public static function getSelection(): array {
		return VegetableHours::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): VegetableHoursModel {
		if(self::$model === NULL) {
			self::$model = new VegetableHoursModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('VegetableHours::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class VegetableHoursModel extends \ModuleModel {

	protected string $module = 'aggregate\VegetableHours';
	protected string $package = 'aggregate';
	protected string $table = 'aggregateVegetableHours';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'year' => ['int16', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'fqn' => ['text8', 'charset' => 'ascii', 'null' => TRUE, 'cast' => 'string'],
			'hours' => ['float32', 'min' => 0.0, 'max' => NULL, 'cast' => 'float'],
			'online' => ['bool', 'cast' => 'bool'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'year', 'fqn', 'hours', 'online'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['structure', 'year'],
			['online']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'online' :
				return FALSE;

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function select(...$fields): VegetableHoursModel {
		return parent::select(...$fields);
	}

	public function where(...$data): VegetableHoursModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): VegetableHoursModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): VegetableHoursModel {
		return $this->where('structure', ...$data);
	}

	public function whereYear(...$data): VegetableHoursModel {
		return $this->where('year', ...$data);
	}

	public function whereFqn(...$data): VegetableHoursModel {
		return $this->where('fqn', ...$data);
	}

	public function whereHours(...$data): VegetableHoursModel {
		return $this->where('hours', ...$data);
	}

	public function whereOnline(...$data): VegetableHoursModel {
		return $this->where('online', ...$data);
	}


}


abstract class VegetableHoursCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): VegetableHours {

		$e = new VegetableHours();

		if(empty($id)) {
			VegetableHours::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = VegetableHours::getSelection();
		}

		if(VegetableHours::model()
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
			$properties = VegetableHours::getSelection();
		}

		if($sort !== NULL) {
			VegetableHours::model()->sort($sort);
		}

		return VegetableHours::model()
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

	public static function getNewElement(array $properties = []): VegetableHours {

		return new VegetableHours($properties);

	}

	public static function create(VegetableHours $e): void {

		VegetableHours::model()->insert($e);

	}

	public static function update(VegetableHours $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = VegetableHours::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, VegetableHours $e, array $properties): void {

		self::$affected = VegetableHours::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(VegetableHours $e): void {

		$e->expects(['id']);

		self::$affected = VegetableHours::model()->delete($e);

	}

}


class VegetableHoursPage extends \ModulePage {

	protected string $module = 'aggregate\VegetableHours';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? VegetableHoursAiLib::getPropertiesCreate(),
		   $propertiesUpdate ?? VegetableHoursAiLib::getPropertiesUpdate()
		);
	}

}
?>