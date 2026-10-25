<?php
namespace aid;

abstract class AreaElement extends \Element {

	use \FilterElement;

	private static ?AreaModel $model = NULL;

	public static function getSelection(): array {
		return Area::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): AreaModel {
		if(self::$model === NULL) {
			self::$model = new AreaModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Area::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class AreaModel extends \ModuleModel {

	protected string $module = 'aid\Area';
	protected string $package = 'aid';
	protected string $table = 'aidArea';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'aid' => ['element32', 'aid\Aid', 'cast' => 'element'],
			'department' => ['text8', 'min' => 2, 'max' => 3, 'charset' => 'ascii', 'cast' => 'string'],
			'epci' => ['text8', 'min' => 9, 'max' => 9, 'charset' => 'ascii', 'null' => TRUE, 'cast' => 'string'],
			'commune' => ['text8', 'min' => 5, 'max' => 5, 'charset' => 'ascii', 'null' => TRUE, 'cast' => 'string'],
			'note' => ['text8', 'min' => 1, 'max' => 250, 'null' => TRUE, 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'aid', 'department', 'epci', 'commune', 'note'
		]);

		$this->propertiesToModule += [
			'aid' => 'aid\Aid',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['department']
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['aid', 'department']
		]);

	}

	public function select(...$fields): AreaModel {
		return parent::select(...$fields);
	}

	public function where(...$data): AreaModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): AreaModel {
		return $this->where('id', ...$data);
	}

	public function whereAid(...$data): AreaModel {
		return $this->where('aid', ...$data);
	}

	public function whereDepartment(...$data): AreaModel {
		return $this->where('department', ...$data);
	}

	public function whereEpci(...$data): AreaModel {
		return $this->where('epci', ...$data);
	}

	public function whereCommune(...$data): AreaModel {
		return $this->where('commune', ...$data);
	}

	public function whereNote(...$data): AreaModel {
		return $this->where('note', ...$data);
	}


}


abstract class AreaCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Area {

		$e = new Area();

		if(empty($id)) {
			Area::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Area::getSelection();
		}

		if(Area::model()
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
			$properties = Area::getSelection();
		}

		if($sort !== NULL) {
			Area::model()->sort($sort);
		}

		return Area::model()
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

	public static function getNewElement(array $properties = []): Area {

		return new Area($properties);

	}

	public static function create(Area $e): void {

		Area::model()->insert($e);

	}

	public static function update(Area $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Area::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Area $e, array $properties): void {

		self::$affected = Area::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Area $e): void {

		$e->expects(['id']);

		self::$affected = Area::model()->delete($e);

	}

}


class AreaPage extends \ModulePage {

	protected string $module = 'aid\Area';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? AreaLib::getPropertiesCreate(),
		   $propertiesUpdate ?? AreaLib::getPropertiesUpdate()
		);
	}

}
?>