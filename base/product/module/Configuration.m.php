<?php
namespace product;

abstract class ConfigurationElement extends \Element {

	use \FilterElement;

	private static ?ConfigurationModel $model = NULL;

	public static function getSelection(): array {
		return Configuration::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): ConfigurationModel {
		if(self::$model === NULL) {
			self::$model = new ConfigurationModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Configuration::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class ConfigurationModel extends \ModuleModel {

	protected string $module = 'product\Configuration';
	protected string $package = 'product';
	protected string $table = 'productConfiguration';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'unique' => TRUE, 'cast' => 'element'],
			'stockNotes' => ['text16', 'null' => TRUE, 'cast' => 'string'],
			'stockNotesUpdatedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'stockNotesUpdatedBy' => ['element32', 'user\User', 'null' => TRUE, 'cast' => 'element'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'stockNotes', 'stockNotesUpdatedAt', 'stockNotesUpdatedBy'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
			'stockNotesUpdatedBy' => 'user\User',
		];

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['structure']
		]);

	}

	public function select(...$fields): ConfigurationModel {
		return parent::select(...$fields);
	}

	public function where(...$data): ConfigurationModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): ConfigurationModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): ConfigurationModel {
		return $this->where('structure', ...$data);
	}

	public function whereStockNotes(...$data): ConfigurationModel {
		return $this->where('stockNotes', ...$data);
	}

	public function whereStockNotesUpdatedAt(...$data): ConfigurationModel {
		return $this->where('stockNotesUpdatedAt', ...$data);
	}

	public function whereStockNotesUpdatedBy(...$data): ConfigurationModel {
		return $this->where('stockNotesUpdatedBy', ...$data);
	}


}


abstract class ConfigurationCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Configuration {

		$e = new Configuration();

		if(empty($id)) {
			Configuration::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Configuration::getSelection();
		}

		if(Configuration::model()
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
			$properties = Configuration::getSelection();
		}

		if($sort !== NULL) {
			Configuration::model()->sort($sort);
		}

		return Configuration::model()
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

	public static function getNewElement(array $properties = []): Configuration {

		return new Configuration($properties);

	}

	public static function create(Configuration $e): void {

		Configuration::model()->insert($e);

	}

	public static function update(Configuration $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Configuration::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Configuration $e, array $properties): void {

		self::$affected = Configuration::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Configuration $e): void {

		$e->expects(['id']);

		self::$affected = Configuration::model()->delete($e);

	}

}


class ConfigurationPage extends \ModulePage {

	protected string $module = 'product\Configuration';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? ConfigurationLib::getPropertiesCreate(),
		   $propertiesUpdate ?? ConfigurationLib::getPropertiesUpdate()
		);
	}

}
?>