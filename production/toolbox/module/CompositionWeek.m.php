<?php
namespace toolbox;

abstract class CompositionWeekElement extends \Element {

	use \FilterElement;

	private static ?CompositionWeekModel $model = NULL;

	const LEAF = 1;
	const ROOT = 2;
	const FRUIT = 4;

	public static function getSelection(): array {
		return CompositionWeek::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): CompositionWeekModel {
		if(self::$model === NULL) {
			self::$model = new CompositionWeekModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('CompositionWeek::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class CompositionWeekModel extends \ModuleModel {

	protected string $module = 'toolbox\CompositionWeek';
	protected string $package = 'toolbox';
	protected string $table = 'toolboxCompositionWeek';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'formula' => ['element32', 'toolbox\CompositionFormula', 'cast' => 'element'],
			'week' => ['int8', 'min' => 1, 'max' => 52, 'cast' => 'int'],
			'amended' => ['bool', 'cast' => 'bool'],
			'relaxedFloor' => ['set', [\toolbox\CompositionWeek::LEAF, \toolbox\CompositionWeek::ROOT, \toolbox\CompositionWeek::FRUIT], 'cast' => 'set'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'formula', 'week', 'amended', 'relaxedFloor'
		]);

		$this->propertiesToModule += [
			'formula' => 'toolbox\CompositionFormula',
		];

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['formula', 'week']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'amended' :
				return FALSE;

			case 'relaxedFloor' :
				return new \Set(0);

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function select(...$fields): CompositionWeekModel {
		return parent::select(...$fields);
	}

	public function where(...$data): CompositionWeekModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): CompositionWeekModel {
		return $this->where('id', ...$data);
	}

	public function whereFormula(...$data): CompositionWeekModel {
		return $this->where('formula', ...$data);
	}

	public function whereWeek(...$data): CompositionWeekModel {
		return $this->where('week', ...$data);
	}

	public function whereAmended(...$data): CompositionWeekModel {
		return $this->where('amended', ...$data);
	}

	public function whereRelaxedFloor(...$data): CompositionWeekModel {
		return $this->where('relaxedFloor', ...$data);
	}


}


abstract class CompositionWeekCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): CompositionWeek {

		$e = new CompositionWeek();

		if(empty($id)) {
			CompositionWeek::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = CompositionWeek::getSelection();
		}

		if(CompositionWeek::model()
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
			$properties = CompositionWeek::getSelection();
		}

		if($sort !== NULL) {
			CompositionWeek::model()->sort($sort);
		}

		return CompositionWeek::model()
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

	public static function getNewElement(array $properties = []): CompositionWeek {

		return new CompositionWeek($properties);

	}

	public static function create(CompositionWeek $e): void {

		CompositionWeek::model()->insert($e);

	}

	public static function update(CompositionWeek $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = CompositionWeek::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, CompositionWeek $e, array $properties): void {

		self::$affected = CompositionWeek::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(CompositionWeek $e): void {

		$e->expects(['id']);

		self::$affected = CompositionWeek::model()->delete($e);

	}

}


class CompositionWeekPage extends \ModulePage {

	protected string $module = 'toolbox\CompositionWeek';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? CompositionWeekLib::getPropertiesCreate(),
		   $propertiesUpdate ?? CompositionWeekLib::getPropertiesUpdate()
		);
	}

}
?>