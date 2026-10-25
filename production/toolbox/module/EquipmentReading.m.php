<?php
namespace toolbox;

abstract class EquipmentReadingElement extends \Element {

	use \FilterElement;

	private static ?EquipmentReadingModel $model = NULL;

	const HOURS = 'hours';
	const KILOMETERS = 'kilometers';

	public static function getSelection(): array {
		return EquipmentReading::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): EquipmentReadingModel {
		if(self::$model === NULL) {
			self::$model = new EquipmentReadingModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('EquipmentReading::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class EquipmentReadingModel extends \ModuleModel {

	protected string $module = 'toolbox\EquipmentReading';
	protected string $package = 'toolbox';
	protected string $table = 'toolboxEquipmentReading';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'equipment' => ['element32', 'toolbox\Equipment', 'cast' => 'element'],
			'meter' => ['enum', [\toolbox\EquipmentReading::HOURS, \toolbox\EquipmentReading::KILOMETERS], 'cast' => 'enum'],
			'date' => ['date', 'cast' => 'string'],
			'value' => ['int32', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'createdAt' => ['datetime', 'cast' => 'string'],
			'createdBy' => ['element32', 'user\User', 'cast' => 'element'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'equipment', 'meter', 'date', 'value', 'createdAt', 'createdBy'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
			'equipment' => 'toolbox\Equipment',
			'createdBy' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['structure']
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['equipment', 'date']
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

	public function encode(string $property, $value) {

		switch($property) {

			case 'meter' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): EquipmentReadingModel {
		return parent::select(...$fields);
	}

	public function where(...$data): EquipmentReadingModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): EquipmentReadingModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): EquipmentReadingModel {
		return $this->where('structure', ...$data);
	}

	public function whereEquipment(...$data): EquipmentReadingModel {
		return $this->where('equipment', ...$data);
	}

	public function whereMeter(...$data): EquipmentReadingModel {
		return $this->where('meter', ...$data);
	}

	public function whereDate(...$data): EquipmentReadingModel {
		return $this->where('date', ...$data);
	}

	public function whereValue(...$data): EquipmentReadingModel {
		return $this->where('value', ...$data);
	}

	public function whereCreatedAt(...$data): EquipmentReadingModel {
		return $this->where('createdAt', ...$data);
	}

	public function whereCreatedBy(...$data): EquipmentReadingModel {
		return $this->where('createdBy', ...$data);
	}


}


abstract class EquipmentReadingCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): EquipmentReading {

		$e = new EquipmentReading();

		if(empty($id)) {
			EquipmentReading::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = EquipmentReading::getSelection();
		}

		if(EquipmentReading::model()
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
			$properties = EquipmentReading::getSelection();
		}

		if($sort !== NULL) {
			EquipmentReading::model()->sort($sort);
		}

		return EquipmentReading::model()
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

	public static function getNewElement(array $properties = []): EquipmentReading {

		return new EquipmentReading($properties);

	}

	public static function create(EquipmentReading $e): void {

		EquipmentReading::model()->insert($e);

	}

	public static function update(EquipmentReading $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = EquipmentReading::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, EquipmentReading $e, array $properties): void {

		self::$affected = EquipmentReading::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(EquipmentReading $e): void {

		$e->expects(['id']);

		self::$affected = EquipmentReading::model()->delete($e);

	}

}


class EquipmentReadingPage extends \ModulePage {

	protected string $module = 'toolbox\EquipmentReading';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? EquipmentReadingLib::getPropertiesCreate(),
		   $propertiesUpdate ?? EquipmentReadingLib::getPropertiesUpdate()
		);
	}

}
?>