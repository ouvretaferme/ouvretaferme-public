<?php
namespace toolbox;

abstract class RainElement extends \Element {

	use \FilterElement;

	private static ?RainModel $model = NULL;

	public static function getSelection(): array {
		return Rain::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): RainModel {
		if(self::$model === NULL) {
			self::$model = new RainModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Rain::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class RainModel extends \ModuleModel {

	protected string $module = 'toolbox\Rain';
	protected string $package = 'toolbox';
	protected string $table = 'toolboxRain';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'gauge' => ['element32', 'toolbox\Gauge', 'cast' => 'element'],
			'date' => ['date', 'cast' => 'string'],
			'rain' => ['decimal', 'digits' => 6, 'decimal' => 1, 'min' => 0.1, 'max' => 99999.9, 'cast' => 'float'],
			'createdAt' => ['datetime', 'cast' => 'string'],
			'createdBy' => ['element32', 'user\User', 'null' => TRUE, 'cast' => 'element'],
			'updatedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'gauge', 'date', 'rain', 'createdAt', 'createdBy', 'updatedAt'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
			'gauge' => 'toolbox\Gauge',
			'createdBy' => 'user\User',
		];

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['gauge', 'date']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'createdAt' :
				return new \Sql('NOW()');

			case 'createdBy' :
				return \user\ConnectionLib::getOnline();

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function select(...$fields): RainModel {
		return parent::select(...$fields);
	}

	public function where(...$data): RainModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): RainModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): RainModel {
		return $this->where('structure', ...$data);
	}

	public function whereGauge(...$data): RainModel {
		return $this->where('gauge', ...$data);
	}

	public function whereDate(...$data): RainModel {
		return $this->where('date', ...$data);
	}

	public function whereRain(...$data): RainModel {
		return $this->where('rain', ...$data);
	}

	public function whereCreatedAt(...$data): RainModel {
		return $this->where('createdAt', ...$data);
	}

	public function whereCreatedBy(...$data): RainModel {
		return $this->where('createdBy', ...$data);
	}

	public function whereUpdatedAt(...$data): RainModel {
		return $this->where('updatedAt', ...$data);
	}


}


abstract class RainCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Rain {

		$e = new Rain();

		if(empty($id)) {
			Rain::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Rain::getSelection();
		}

		if(Rain::model()
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
			$properties = Rain::getSelection();
		}

		if($sort !== NULL) {
			Rain::model()->sort($sort);
		}

		return Rain::model()
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

	public static function getNewElement(array $properties = []): Rain {

		return new Rain($properties);

	}

	public static function create(Rain $e): void {

		Rain::model()->insert($e);

	}

	public static function update(Rain $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Rain::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Rain $e, array $properties): void {

		self::$affected = Rain::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Rain $e): void {

		$e->expects(['id']);

		self::$affected = Rain::model()->delete($e);

	}

}


class RainPage extends \ModulePage {

	protected string $module = 'toolbox\Rain';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? RainLib::getPropertiesCreate(),
		   $propertiesUpdate ?? RainLib::getPropertiesUpdate()
		);
	}

}
?>