<?php
namespace aggregate;

abstract class CalendarElement extends \Element {

	use \FilterElement;

	private static ?CalendarModel $model = NULL;

	const OCEANIC = 'oceanic';
	const CONTINENTAL = 'continental';
	const MEDITERRANEAN = 'mediterranean';
	const MOUNTAIN = 'mountain';

	public static function getSelection(): array {
		return Calendar::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): CalendarModel {
		if(self::$model === NULL) {
			self::$model = new CalendarModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Calendar::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class CalendarModel extends \ModuleModel {

	protected string $module = 'aggregate\Calendar';
	protected string $package = 'aggregate';
	protected string $table = 'aggregateCalendar';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'climate' => ['enum', [\aggregate\Calendar::OCEANIC, \aggregate\Calendar::CONTINENTAL, \aggregate\Calendar::MEDITERRANEAN, \aggregate\Calendar::MOUNTAIN], 'cast' => 'enum'],
			'plant' => ['element32', 'plant\Plant', 'cast' => 'element'],
			'harvestWeeks' => ['json', 'cast' => 'array'],
			'weeks' => ['json', 'cast' => 'array'],
			'structures' => ['int16', 'min' => 1, 'max' => NULL, 'cast' => 'int'],
			'fallback' => ['bool', 'cast' => 'bool'],
			'online' => ['bool', 'cast' => 'bool'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'climate', 'plant', 'harvestWeeks', 'weeks', 'structures', 'fallback', 'online'
		]);

		$this->propertiesToModule += [
			'plant' => 'plant\Plant',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['online']
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['climate', 'plant']
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

	public function encode(string $property, $value) {

		switch($property) {

			case 'climate' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'harvestWeeks' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			case 'weeks' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'harvestWeeks' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			case 'weeks' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			default :
				return parent::decode($property, $value);

		}

	}

	public function select(...$fields): CalendarModel {
		return parent::select(...$fields);
	}

	public function where(...$data): CalendarModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): CalendarModel {
		return $this->where('id', ...$data);
	}

	public function whereClimate(...$data): CalendarModel {
		return $this->where('climate', ...$data);
	}

	public function wherePlant(...$data): CalendarModel {
		return $this->where('plant', ...$data);
	}

	public function whereHarvestWeeks(...$data): CalendarModel {
		return $this->where('harvestWeeks', ...$data);
	}

	public function whereWeeks(...$data): CalendarModel {
		return $this->where('weeks', ...$data);
	}

	public function whereStructures(...$data): CalendarModel {
		return $this->where('structures', ...$data);
	}

	public function whereFallback(...$data): CalendarModel {
		return $this->where('fallback', ...$data);
	}

	public function whereOnline(...$data): CalendarModel {
		return $this->where('online', ...$data);
	}


}


abstract class CalendarCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Calendar {

		$e = new Calendar();

		if(empty($id)) {
			Calendar::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Calendar::getSelection();
		}

		if(Calendar::model()
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
			$properties = Calendar::getSelection();
		}

		if($sort !== NULL) {
			Calendar::model()->sort($sort);
		}

		return Calendar::model()
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

	public static function getNewElement(array $properties = []): Calendar {

		return new Calendar($properties);

	}

	public static function create(Calendar $e): void {

		Calendar::model()->insert($e);

	}

	public static function update(Calendar $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Calendar::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Calendar $e, array $properties): void {

		self::$affected = Calendar::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Calendar $e): void {

		$e->expects(['id']);

		self::$affected = Calendar::model()->delete($e);

	}

}


class CalendarPage extends \ModulePage {

	protected string $module = 'aggregate\Calendar';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? CalendarAiLib::getPropertiesCreate(),
		   $propertiesUpdate ?? CalendarAiLib::getPropertiesUpdate()
		);
	}

}
?>