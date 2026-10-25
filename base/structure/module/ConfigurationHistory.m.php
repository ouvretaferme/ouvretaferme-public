<?php
namespace structure;

abstract class ConfigurationHistoryElement extends \Element {

	use \FilterElement;

	private static ?ConfigurationHistoryModel $model = NULL;

	public static function getSelection(): array {
		return ConfigurationHistory::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): ConfigurationHistoryModel {
		if(self::$model === NULL) {
			self::$model = new ConfigurationHistoryModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('ConfigurationHistory::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class ConfigurationHistoryModel extends \ModuleModel {

	protected string $module = 'structure\ConfigurationHistory';
	protected string $package = 'structure';
	protected string $table = 'structureConfigurationHistory';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'field' => ['text8', 'cast' => 'string'],
			'value' => ['json', 'cast' => 'array'],
			'effectiveAt' => ['date', 'cast' => 'string'],
			'overwrittenBy' => ['date', 'null' => TRUE, 'cast' => 'string'],
			'createdAt' => ['datetime', 'cast' => 'string'],
			'createdBy' => ['element32', 'user\User', 'null' => TRUE, 'cast' => 'element'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'structure', 'field', 'value', 'effectiveAt', 'overwrittenBy', 'createdAt', 'createdBy'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
			'createdBy' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['structure', 'field']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'value' :
				return [];

			case 'createdAt' :
				return new \Sql('NOW()');

			case 'createdBy' :
				return \user\ConnectionLib::getOnline();

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'value' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'value' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			default :
				return parent::decode($property, $value);

		}

	}

	public function select(...$fields): ConfigurationHistoryModel {
		return parent::select(...$fields);
	}

	public function where(...$data): ConfigurationHistoryModel {
		return parent::where(...$data);
	}

	public function whereStructure(...$data): ConfigurationHistoryModel {
		return $this->where('structure', ...$data);
	}

	public function whereField(...$data): ConfigurationHistoryModel {
		return $this->where('field', ...$data);
	}

	public function whereValue(...$data): ConfigurationHistoryModel {
		return $this->where('value', ...$data);
	}

	public function whereEffectiveAt(...$data): ConfigurationHistoryModel {
		return $this->where('effectiveAt', ...$data);
	}

	public function whereOverwrittenBy(...$data): ConfigurationHistoryModel {
		return $this->where('overwrittenBy', ...$data);
	}

	public function whereCreatedAt(...$data): ConfigurationHistoryModel {
		return $this->where('createdAt', ...$data);
	}

	public function whereCreatedBy(...$data): ConfigurationHistoryModel {
		return $this->where('createdBy', ...$data);
	}


}


abstract class ConfigurationHistoryCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): ConfigurationHistory {

		$e = new ConfigurationHistory();

		if(empty($id)) {
			ConfigurationHistory::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = ConfigurationHistory::getSelection();
		}

		if(ConfigurationHistory::model()
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
			$properties = ConfigurationHistory::getSelection();
		}

		if($sort !== NULL) {
			ConfigurationHistory::model()->sort($sort);
		}

		return ConfigurationHistory::model()
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

	public static function getNewElement(array $properties = []): ConfigurationHistory {

		return new ConfigurationHistory($properties);

	}

	public static function create(ConfigurationHistory $e): void {

		ConfigurationHistory::model()->insert($e);

	}

	public static function update(ConfigurationHistory $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = ConfigurationHistory::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, ConfigurationHistory $e, array $properties): void {

		self::$affected = ConfigurationHistory::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(ConfigurationHistory $e): void {

		$e->expects(['id']);

		self::$affected = ConfigurationHistory::model()->delete($e);

	}

}


class ConfigurationHistoryPage extends \ModulePage {

	protected string $module = 'structure\ConfigurationHistory';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? ConfigurationHistoryLib::getPropertiesCreate(),
		   $propertiesUpdate ?? ConfigurationHistoryLib::getPropertiesUpdate()
		);
	}

}
?>