<?php
namespace toolbox;

abstract class WaterConfigurationElement extends \Element {

	use \FilterElement;

	private static ?WaterConfigurationModel $model = NULL;

	public static function getSelection(): array {
		return WaterConfiguration::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): WaterConfigurationModel {
		if(self::$model === NULL) {
			self::$model = new WaterConfigurationModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('WaterConfiguration::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class WaterConfigurationModel extends \ModuleModel {

	protected string $module = 'toolbox\WaterConfiguration';
	protected string $package = 'toolbox';
	protected string $table = 'toolboxWaterConfiguration';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'unique' => TRUE, 'cast' => 'element'],
			'readingEveryMonths' => ['int8', 'min' => 1, 'max' => 6, 'cast' => 'int'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'readingEveryMonths'
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

			case 'readingEveryMonths' :
				return 3;

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function select(...$fields): WaterConfigurationModel {
		return parent::select(...$fields);
	}

	public function where(...$data): WaterConfigurationModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): WaterConfigurationModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): WaterConfigurationModel {
		return $this->where('structure', ...$data);
	}

	public function whereReadingEveryMonths(...$data): WaterConfigurationModel {
		return $this->where('readingEveryMonths', ...$data);
	}


}


abstract class WaterConfigurationCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): WaterConfiguration {

		$e = new WaterConfiguration();

		if(empty($id)) {
			WaterConfiguration::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = WaterConfiguration::getSelection();
		}

		if(WaterConfiguration::model()
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
			$properties = WaterConfiguration::getSelection();
		}

		if($sort !== NULL) {
			WaterConfiguration::model()->sort($sort);
		}

		return WaterConfiguration::model()
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

	public static function getNewElement(array $properties = []): WaterConfiguration {

		return new WaterConfiguration($properties);

	}

	public static function create(WaterConfiguration $e): void {

		WaterConfiguration::model()->insert($e);

	}

	public static function update(WaterConfiguration $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = WaterConfiguration::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, WaterConfiguration $e, array $properties): void {

		self::$affected = WaterConfiguration::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(WaterConfiguration $e): void {

		$e->expects(['id']);

		self::$affected = WaterConfiguration::model()->delete($e);

	}

}


class WaterConfigurationPage extends \ModulePage {

	protected string $module = 'toolbox\WaterConfiguration';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? WaterConfigurationLib::getPropertiesCreate(),
		   $propertiesUpdate ?? WaterConfigurationLib::getPropertiesUpdate()
		);
	}

}
?>