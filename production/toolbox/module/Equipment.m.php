<?php
namespace toolbox;

abstract class EquipmentElement extends \Element {

	use \FilterElement;

	private static ?EquipmentModel $model = NULL;

	const HOURS = 'hours';
	const KILOMETERS = 'kilometers';

	const ACTIVE = 'active';
	const INACTIVE = 'inactive';

	public static function getSelection(): array {
		return Equipment::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): EquipmentModel {
		if(self::$model === NULL) {
			self::$model = new EquipmentModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Equipment::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class EquipmentModel extends \ModuleModel {

	protected string $module = 'toolbox\Equipment';
	protected string $package = 'toolbox';
	protected string $table = 'toolboxEquipment';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'tool' => ['element32', 'work\Tool', 'unique' => TRUE, 'cast' => 'element'],
			'meter' => ['enum', [\toolbox\Equipment::HOURS, \toolbox\Equipment::KILOMETERS], 'null' => TRUE, 'cast' => 'enum'],
			'lastMeter' => ['enum', [\toolbox\Equipment::HOURS, \toolbox\Equipment::KILOMETERS], 'null' => TRUE, 'cast' => 'enum'],
			'registration' => ['text8', 'min' => 1, 'max' => 20, 'collate' => 'general', 'null' => TRUE, 'cast' => 'string'],
			'readingSkippedDate' => ['date', 'null' => TRUE, 'cast' => 'string'],
			'readingPostponedDate' => ['date', 'null' => TRUE, 'cast' => 'string'],
			'status' => ['enum', [\toolbox\Equipment::ACTIVE, \toolbox\Equipment::INACTIVE], 'cast' => 'enum'],
			'createdAt' => ['datetime', 'cast' => 'string'],
			'createdBy' => ['element32', 'user\User', 'cast' => 'element'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'tool', 'meter', 'lastMeter', 'registration', 'readingSkippedDate', 'readingPostponedDate', 'status', 'createdAt', 'createdBy'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
			'tool' => 'work\Tool',
			'createdBy' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['structure', 'status']
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['tool']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'status' :
				return Equipment::ACTIVE;

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

			case 'lastMeter' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'status' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): EquipmentModel {
		return parent::select(...$fields);
	}

	public function where(...$data): EquipmentModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): EquipmentModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): EquipmentModel {
		return $this->where('structure', ...$data);
	}

	public function whereTool(...$data): EquipmentModel {
		return $this->where('tool', ...$data);
	}

	public function whereMeter(...$data): EquipmentModel {
		return $this->where('meter', ...$data);
	}

	public function whereLastMeter(...$data): EquipmentModel {
		return $this->where('lastMeter', ...$data);
	}

	public function whereRegistration(...$data): EquipmentModel {
		return $this->where('registration', ...$data);
	}

	public function whereReadingSkippedDate(...$data): EquipmentModel {
		return $this->where('readingSkippedDate', ...$data);
	}

	public function whereReadingPostponedDate(...$data): EquipmentModel {
		return $this->where('readingPostponedDate', ...$data);
	}

	public function whereStatus(...$data): EquipmentModel {
		return $this->where('status', ...$data);
	}

	public function whereCreatedAt(...$data): EquipmentModel {
		return $this->where('createdAt', ...$data);
	}

	public function whereCreatedBy(...$data): EquipmentModel {
		return $this->where('createdBy', ...$data);
	}


}


abstract class EquipmentCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Equipment {

		$e = new Equipment();

		if(empty($id)) {
			Equipment::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Equipment::getSelection();
		}

		if(Equipment::model()
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
			$properties = Equipment::getSelection();
		}

		if($sort !== NULL) {
			Equipment::model()->sort($sort);
		}

		return Equipment::model()
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

	public static function getNewElement(array $properties = []): Equipment {

		return new Equipment($properties);

	}

	public static function create(Equipment $e): void {

		Equipment::model()->insert($e);

	}

	public static function update(Equipment $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Equipment::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Equipment $e, array $properties): void {

		self::$affected = Equipment::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Equipment $e): void {

		$e->expects(['id']);

		self::$affected = Equipment::model()->delete($e);

	}

}


class EquipmentPage extends \ModulePage {

	protected string $module = 'toolbox\Equipment';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? EquipmentLib::getPropertiesCreate(),
		   $propertiesUpdate ?? EquipmentLib::getPropertiesUpdate()
		);
	}

}
?>