<?php
namespace map;

abstract class ConfigurationElement extends \Element {

	use \FilterElement;

	private static ?ConfigurationModel $model = NULL;

	const TENDER = 'tender';
	const PALE = 'pale';
	const CLEAR = 'clear';
	const DRY = 'dry';
	const LUSH = 'lush';
	const GRAZED = 'grazed';

	const LOAM = 'loam';
	const CLAY = 'clay';
	const SAND = 'sand';
	const HUMUS = 'humus';
	const CHALK = 'chalk';
	const OCHRE = 'ochre';
	const SANDSTONE = 'sandstone';
	const SCHIST = 'schist';
	const BROWN = 'brown';

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

	protected string $module = 'map\Configuration';
	protected string $package = 'map';
	protected string $table = 'mapConfiguration';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'unique' => TRUE, 'cast' => 'element'],
			'seasonFirst' => ['int16', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'seasonLast' => ['int16', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'rotationYears' => ['int8', 'min' => 2, 'max' => 5, 'cast' => 'int'],
			'rotationExclude' => ['json', 'cast' => 'array'],
			'defaultBedLength' => ['int16', 'min' => 1, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'defaultBedWidth' => ['int16', 'min' => 1, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'defaultAlleyWidth' => ['int16', 'min' => 1, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'calendarMonthStart' => ['int8', 'min' => 7, 'max' => 12, 'null' => TRUE, 'cast' => 'int'],
			'calendarMonthStop' => ['int8', 'min' => 1, 'max' => 6, 'null' => TRUE, 'cast' => 'int'],
			'planningDelayedMax' => ['int8', 'min' => 1, 'max' => 6, 'null' => TRUE, 'cast' => 'int'],
			'decorGrass' => ['enum', [\map\Configuration::TENDER, \map\Configuration::PALE, \map\Configuration::CLEAR, \map\Configuration::DRY, \map\Configuration::LUSH, \map\Configuration::GRAZED], 'cast' => 'enum'],
			'decorSoil' => ['enum', [\map\Configuration::LOAM, \map\Configuration::CLAY, \map\Configuration::SAND, \map\Configuration::HUMUS, \map\Configuration::CHALK, \map\Configuration::OCHRE, \map\Configuration::SANDSTONE, \map\Configuration::SCHIST, \map\Configuration::BROWN], 'cast' => 'enum'],
			'decorAdopted' => ['bool', 'cast' => 'bool'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'seasonFirst', 'seasonLast', 'rotationYears', 'rotationExclude', 'defaultBedLength', 'defaultBedWidth', 'defaultAlleyWidth', 'calendarMonthStart', 'calendarMonthStop', 'planningDelayedMax', 'decorGrass', 'decorSoil', 'decorAdopted'
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

			case 'rotationYears' :
				return 4;

			case 'rotationExclude' :
				return [];

			case 'calendarMonthStart' :
				return 10;

			case 'calendarMonthStop' :
				return 3;

			case 'planningDelayedMax' :
				return 2;

			case 'decorGrass' :
				return Configuration::TENDER;

			case 'decorSoil' :
				return Configuration::LOAM;

			case 'decorAdopted' :
				return FALSE;

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'rotationExclude' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			case 'decorGrass' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'decorSoil' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'rotationExclude' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			default :
				return parent::decode($property, $value);

		}

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

	public function whereSeasonFirst(...$data): ConfigurationModel {
		return $this->where('seasonFirst', ...$data);
	}

	public function whereSeasonLast(...$data): ConfigurationModel {
		return $this->where('seasonLast', ...$data);
	}

	public function whereRotationYears(...$data): ConfigurationModel {
		return $this->where('rotationYears', ...$data);
	}

	public function whereRotationExclude(...$data): ConfigurationModel {
		return $this->where('rotationExclude', ...$data);
	}

	public function whereDefaultBedLength(...$data): ConfigurationModel {
		return $this->where('defaultBedLength', ...$data);
	}

	public function whereDefaultBedWidth(...$data): ConfigurationModel {
		return $this->where('defaultBedWidth', ...$data);
	}

	public function whereDefaultAlleyWidth(...$data): ConfigurationModel {
		return $this->where('defaultAlleyWidth', ...$data);
	}

	public function whereCalendarMonthStart(...$data): ConfigurationModel {
		return $this->where('calendarMonthStart', ...$data);
	}

	public function whereCalendarMonthStop(...$data): ConfigurationModel {
		return $this->where('calendarMonthStop', ...$data);
	}

	public function wherePlanningDelayedMax(...$data): ConfigurationModel {
		return $this->where('planningDelayedMax', ...$data);
	}

	public function whereDecorGrass(...$data): ConfigurationModel {
		return $this->where('decorGrass', ...$data);
	}

	public function whereDecorSoil(...$data): ConfigurationModel {
		return $this->where('decorSoil', ...$data);
	}

	public function whereDecorAdopted(...$data): ConfigurationModel {
		return $this->where('decorAdopted', ...$data);
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

	protected string $module = 'map\Configuration';

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