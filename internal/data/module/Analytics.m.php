<?php
namespace data;

abstract class AnalyticsElement extends \Element {

	use \FilterElement;

	private static ?AnalyticsModel $model = NULL;

	public static function getSelection(): array {
		return Analytics::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): AnalyticsModel {
		if(self::$model === NULL) {
			self::$model = new AnalyticsModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Analytics::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class AnalyticsModel extends \ModuleModel {

	protected string $module = 'data\Analytics';
	protected string $package = 'data';
	protected string $table = 'dataAnalytics';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'data' => ['int32', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'value' => ['int16', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'structure', 'data', 'value', 'createdAt'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
		];

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['structure', 'data']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'createdAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function select(...$fields): AnalyticsModel {
		return parent::select(...$fields);
	}

	public function where(...$data): AnalyticsModel {
		return parent::where(...$data);
	}

	public function whereStructure(...$data): AnalyticsModel {
		return $this->where('structure', ...$data);
	}

	public function whereData(...$data): AnalyticsModel {
		return $this->where('data', ...$data);
	}

	public function whereValue(...$data): AnalyticsModel {
		return $this->where('value', ...$data);
	}

	public function whereCreatedAt(...$data): AnalyticsModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class AnalyticsCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Analytics {

		$e = new Analytics();

		if(empty($id)) {
			Analytics::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Analytics::getSelection();
		}

		if(Analytics::model()
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
			$properties = Analytics::getSelection();
		}

		if($sort !== NULL) {
			Analytics::model()->sort($sort);
		}

		return Analytics::model()
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

	public static function getNewElement(array $properties = []): Analytics {

		return new Analytics($properties);

	}

	public static function create(Analytics $e): void {

		Analytics::model()->insert($e);

	}

	public static function update(Analytics $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Analytics::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Analytics $e, array $properties): void {

		self::$affected = Analytics::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Analytics $e): void {

		$e->expects(['id']);

		self::$affected = Analytics::model()->delete($e);

	}

}


class AnalyticsPage extends \ModulePage {

	protected string $module = 'data\Analytics';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? AnalyticsLib::getPropertiesCreate(),
		   $propertiesUpdate ?? AnalyticsLib::getPropertiesUpdate()
		);
	}

}
?>