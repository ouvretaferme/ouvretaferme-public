<?php
namespace note;

abstract class TargetElement extends \Element {

	use \FilterElement;

	private static ?TargetModel $model = NULL;

	public static function getSelection(): array {
		return Target::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): TargetModel {
		if(self::$model === NULL) {
			self::$model = new TargetModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Target::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class TargetModel extends \ModuleModel {

	protected string $module = 'note\Target';
	protected string $package = 'note';
	protected string $table = 'noteTarget';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'note' => ['element32', 'note\Note', 'cast' => 'element'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'targetType' => ['text8', 'min' => 1, 'max' => 30, 'charset' => 'ascii', 'cast' => 'string'],
			'targetId' => ['int32', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'note', 'structure', 'targetType', 'targetId'
		]);

		$this->propertiesToModule += [
			'note' => 'note\Note',
			'structure' => 'structure\Structure',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['structure', 'targetType', 'targetId']
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['note', 'targetType', 'targetId']
		]);

	}

	public function select(...$fields): TargetModel {
		return parent::select(...$fields);
	}

	public function where(...$data): TargetModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): TargetModel {
		return $this->where('id', ...$data);
	}

	public function whereNote(...$data): TargetModel {
		return $this->where('note', ...$data);
	}

	public function whereStructure(...$data): TargetModel {
		return $this->where('structure', ...$data);
	}

	public function whereTargetType(...$data): TargetModel {
		return $this->where('targetType', ...$data);
	}

	public function whereTargetId(...$data): TargetModel {
		return $this->where('targetId', ...$data);
	}


}


abstract class TargetCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Target {

		$e = new Target();

		if(empty($id)) {
			Target::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Target::getSelection();
		}

		if(Target::model()
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
			$properties = Target::getSelection();
		}

		if($sort !== NULL) {
			Target::model()->sort($sort);
		}

		return Target::model()
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

	public static function getNewElement(array $properties = []): Target {

		return new Target($properties);

	}

	public static function create(Target $e): void {

		Target::model()->insert($e);

	}

	public static function update(Target $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Target::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Target $e, array $properties): void {

		self::$affected = Target::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Target $e): void {

		$e->expects(['id']);

		self::$affected = Target::model()->delete($e);

	}

}


class TargetPage extends \ModulePage {

	protected string $module = 'note\Target';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? TargetLib::getPropertiesCreate(),
		   $propertiesUpdate ?? TargetLib::getPropertiesUpdate()
		);
	}

}
?>