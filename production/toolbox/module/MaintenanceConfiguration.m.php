<?php
namespace toolbox;

abstract class MaintenanceConfigurationElement extends \Element {

	use \FilterElement;

	private static ?MaintenanceConfigurationModel $model = NULL;

	public static function getSelection(): array {
		return MaintenanceConfiguration::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): MaintenanceConfigurationModel {
		if(self::$model === NULL) {
			self::$model = new MaintenanceConfigurationModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('MaintenanceConfiguration::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class MaintenanceConfigurationModel extends \ModuleModel {

	protected string $module = 'toolbox\MaintenanceConfiguration';
	protected string $package = 'toolbox';
	protected string $table = 'toolboxMaintenanceConfiguration';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'unique' => TRUE, 'cast' => 'element'],
			'action' => ['element32', 'work\Action', 'cast' => 'element'],
			'readingEveryMonths' => ['int8', 'min' => 1, 'max' => 6, 'cast' => 'int'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'action', 'readingEveryMonths'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
			'action' => 'work\Action',
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

	public function select(...$fields): MaintenanceConfigurationModel {
		return parent::select(...$fields);
	}

	public function where(...$data): MaintenanceConfigurationModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): MaintenanceConfigurationModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): MaintenanceConfigurationModel {
		return $this->where('structure', ...$data);
	}

	public function whereAction(...$data): MaintenanceConfigurationModel {
		return $this->where('action', ...$data);
	}

	public function whereReadingEveryMonths(...$data): MaintenanceConfigurationModel {
		return $this->where('readingEveryMonths', ...$data);
	}


}


abstract class MaintenanceConfigurationCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): MaintenanceConfiguration {

		$e = new MaintenanceConfiguration();

		if(empty($id)) {
			MaintenanceConfiguration::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = MaintenanceConfiguration::getSelection();
		}

		if(MaintenanceConfiguration::model()
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
			$properties = MaintenanceConfiguration::getSelection();
		}

		if($sort !== NULL) {
			MaintenanceConfiguration::model()->sort($sort);
		}

		return MaintenanceConfiguration::model()
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

	public static function getNewElement(array $properties = []): MaintenanceConfiguration {

		return new MaintenanceConfiguration($properties);

	}

	public static function create(MaintenanceConfiguration $e): void {

		MaintenanceConfiguration::model()->insert($e);

	}

	public static function update(MaintenanceConfiguration $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = MaintenanceConfiguration::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, MaintenanceConfiguration $e, array $properties): void {

		self::$affected = MaintenanceConfiguration::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(MaintenanceConfiguration $e): void {

		$e->expects(['id']);

		self::$affected = MaintenanceConfiguration::model()->delete($e);

	}

}


class MaintenanceConfigurationPage extends \ModulePage {

	protected string $module = 'toolbox\MaintenanceConfiguration';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? MaintenanceConfigurationLib::getPropertiesCreate(),
		   $propertiesUpdate ?? MaintenanceConfigurationLib::getPropertiesUpdate()
		);
	}

}
?>