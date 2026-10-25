<?php
namespace learning;

abstract class PhaseElement extends \Element {

	use \FilterElement;

	private static ?PhaseModel $model = NULL;

	public static function getSelection(): array {
		return Phase::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): PhaseModel {
		if(self::$model === NULL) {
			self::$model = new PhaseModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Phase::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class PhaseModel extends \ModuleModel {

	protected string $module = 'learning\Phase';
	protected string $package = 'learning';
	protected string $table = 'learningPhase';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'course' => ['element32', 'learning\Course', 'cast' => 'element'],
			'name' => ['text8', 'min' => 1, 'max' => 100, 'cast' => 'string'],
			'summary' => ['text8', 'min' => 1, 'max' => 250, 'null' => TRUE, 'cast' => 'string'],
			'position' => ['int8', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'course', 'name', 'summary', 'position', 'createdAt'
		]);

		$this->propertiesToModule += [
			'course' => 'learning\Course',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['course']
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

	public function select(...$fields): PhaseModel {
		return parent::select(...$fields);
	}

	public function where(...$data): PhaseModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): PhaseModel {
		return $this->where('id', ...$data);
	}

	public function whereCourse(...$data): PhaseModel {
		return $this->where('course', ...$data);
	}

	public function whereName(...$data): PhaseModel {
		return $this->where('name', ...$data);
	}

	public function whereSummary(...$data): PhaseModel {
		return $this->where('summary', ...$data);
	}

	public function wherePosition(...$data): PhaseModel {
		return $this->where('position', ...$data);
	}

	public function whereCreatedAt(...$data): PhaseModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class PhaseCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Phase {

		$e = new Phase();

		if(empty($id)) {
			Phase::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Phase::getSelection();
		}

		if(Phase::model()
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
			$properties = Phase::getSelection();
		}

		if($sort !== NULL) {
			Phase::model()->sort($sort);
		}

		return Phase::model()
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

	public static function getNewElement(array $properties = []): Phase {

		return new Phase($properties);

	}

	public static function create(Phase $e): void {

		Phase::model()->insert($e);

	}

	public static function update(Phase $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Phase::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Phase $e, array $properties): void {

		self::$affected = Phase::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Phase $e): void {

		$e->expects(['id']);

		self::$affected = Phase::model()->delete($e);

	}

}


class PhasePage extends \ModulePage {

	protected string $module = 'learning\Phase';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? PhaseLib::getPropertiesCreate(),
		   $propertiesUpdate ?? PhaseLib::getPropertiesUpdate()
		);
	}

}
?>