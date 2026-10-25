<?php
namespace planning;

abstract class DueElement extends \Element {

	use \FilterElement;

	private static ?DueModel $model = NULL;

	public static function getSelection(): array {
		return Due::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): DueModel {
		if(self::$model === NULL) {
			self::$model = new DueModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Due::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class DueModel extends \ModuleModel {

	protected string $module = 'planning\Due';
	protected string $package = 'planning';
	protected string $table = 'planningDue';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'source' => ['text8', 'min' => 1, 'max' => 30, 'charset' => 'ascii', 'cast' => 'string'],
			'type' => ['text8', 'min' => 1, 'max' => 50, 'charset' => 'ascii', 'cast' => 'string'],
			'reference' => ['int32', 'min' => 0, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'dueDate' => ['date', 'null' => TRUE, 'cast' => 'string'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'source', 'type', 'reference', 'dueDate', 'createdAt'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['structure', 'dueDate']
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

	public function select(...$fields): DueModel {
		return parent::select(...$fields);
	}

	public function where(...$data): DueModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): DueModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): DueModel {
		return $this->where('structure', ...$data);
	}

	public function whereSource(...$data): DueModel {
		return $this->where('source', ...$data);
	}

	public function whereType(...$data): DueModel {
		return $this->where('type', ...$data);
	}

	public function whereReference(...$data): DueModel {
		return $this->where('reference', ...$data);
	}

	public function whereDueDate(...$data): DueModel {
		return $this->where('dueDate', ...$data);
	}

	public function whereCreatedAt(...$data): DueModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class DueCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Due {

		$e = new Due();

		if(empty($id)) {
			Due::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Due::getSelection();
		}

		if(Due::model()
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
			$properties = Due::getSelection();
		}

		if($sort !== NULL) {
			Due::model()->sort($sort);
		}

		return Due::model()
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

	public static function getNewElement(array $properties = []): Due {

		return new Due($properties);

	}

	public static function create(Due $e): void {

		Due::model()->insert($e);

	}

	public static function update(Due $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Due::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Due $e, array $properties): void {

		self::$affected = Due::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Due $e): void {

		$e->expects(['id']);

		self::$affected = Due::model()->delete($e);

	}

}


class DuePage extends \ModulePage {

	protected string $module = 'planning\Due';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? DueLib::getPropertiesCreate(),
		   $propertiesUpdate ?? DueLib::getPropertiesUpdate()
		);
	}

}
?>