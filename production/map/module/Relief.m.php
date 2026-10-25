<?php
namespace map;

abstract class ReliefElement extends \Element {

	use \FilterElement;

	private static ?ReliefModel $model = NULL;

	public static function getSelection(): array {
		return Relief::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): ReliefModel {
		if(self::$model === NULL) {
			self::$model = new ReliefModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Relief::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class ReliefModel extends \ModuleModel {

	protected string $module = 'map\Relief';
	protected string $package = 'map';
	protected string $table = 'mapRelief';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'tileX' => ['int32', 'cast' => 'int'],
			'tileY' => ['int32', 'cast' => 'int'],
			'altitudes' => ['binary32', 'null' => TRUE, 'cast' => 'binary'],
			'failedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'uncoveredAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'tileX', 'tileY', 'altitudes', 'failedAt', 'uncoveredAt', 'createdAt'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
		];

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['structure', 'tileX', 'tileY']
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

	public function select(...$fields): ReliefModel {
		return parent::select(...$fields);
	}

	public function where(...$data): ReliefModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): ReliefModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): ReliefModel {
		return $this->where('structure', ...$data);
	}

	public function whereTileX(...$data): ReliefModel {
		return $this->where('tileX', ...$data);
	}

	public function whereTileY(...$data): ReliefModel {
		return $this->where('tileY', ...$data);
	}

	public function whereAltitudes(...$data): ReliefModel {
		return $this->where('altitudes', ...$data);
	}

	public function whereFailedAt(...$data): ReliefModel {
		return $this->where('failedAt', ...$data);
	}

	public function whereUncoveredAt(...$data): ReliefModel {
		return $this->where('uncoveredAt', ...$data);
	}

	public function whereCreatedAt(...$data): ReliefModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class ReliefCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Relief {

		$e = new Relief();

		if(empty($id)) {
			Relief::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Relief::getSelection();
		}

		if(Relief::model()
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
			$properties = Relief::getSelection();
		}

		if($sort !== NULL) {
			Relief::model()->sort($sort);
		}

		return Relief::model()
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

	public static function getNewElement(array $properties = []): Relief {

		return new Relief($properties);

	}

	public static function create(Relief $e): void {

		Relief::model()->insert($e);

	}

	public static function update(Relief $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Relief::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Relief $e, array $properties): void {

		self::$affected = Relief::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Relief $e): void {

		$e->expects(['id']);

		self::$affected = Relief::model()->delete($e);

	}

}


class ReliefPage extends \ModulePage {

	protected string $module = 'map\Relief';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? ReliefLib::getPropertiesCreate(),
		   $propertiesUpdate ?? ReliefLib::getPropertiesUpdate()
		);
	}

}
?>