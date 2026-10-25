<?php
namespace plant;

abstract class VarietyNameElement extends \Element {

	use \FilterElement;

	private static ?VarietyNameModel $model = NULL;

	public static function getSelection(): array {
		return VarietyName::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): VarietyNameModel {
		if(self::$model === NULL) {
			self::$model = new VarietyNameModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('VarietyName::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class VarietyNameModel extends \ModuleModel {

	protected string $module = 'plant\VarietyName';
	protected string $package = 'plant';
	protected string $table = 'plantVarietyName';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'variety' => ['element32', 'plant\Variety', 'cast' => 'element'],
			'plant' => ['element32', 'plant\Plant', 'cast' => 'element'],
			'name' => ['text8', 'min' => 1, 'max' => 50, 'collate' => 'general', 'cast' => 'string'],
			'searchKey' => ['text8', 'min' => 1, 'max' => 50, 'cast' => 'string'],
			'sortKey' => ['text8', 'min' => 1, 'max' => 50, 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'variety', 'plant', 'name', 'searchKey', 'sortKey'
		]);

		$this->propertiesToModule += [
			'variety' => 'plant\Variety',
			'plant' => 'plant\Plant',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['variety'],
			['plant', 'searchKey'],
			['plant', 'sortKey']
		]);

	}

	public function select(...$fields): VarietyNameModel {
		return parent::select(...$fields);
	}

	public function where(...$data): VarietyNameModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): VarietyNameModel {
		return $this->where('id', ...$data);
	}

	public function whereVariety(...$data): VarietyNameModel {
		return $this->where('variety', ...$data);
	}

	public function wherePlant(...$data): VarietyNameModel {
		return $this->where('plant', ...$data);
	}

	public function whereName(...$data): VarietyNameModel {
		return $this->where('name', ...$data);
	}

	public function whereSearchKey(...$data): VarietyNameModel {
		return $this->where('searchKey', ...$data);
	}

	public function whereSortKey(...$data): VarietyNameModel {
		return $this->where('sortKey', ...$data);
	}


}


abstract class VarietyNameCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): VarietyName {

		$e = new VarietyName();

		if(empty($id)) {
			VarietyName::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = VarietyName::getSelection();
		}

		if(VarietyName::model()
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
			$properties = VarietyName::getSelection();
		}

		if($sort !== NULL) {
			VarietyName::model()->sort($sort);
		}

		return VarietyName::model()
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

	public static function getNewElement(array $properties = []): VarietyName {

		return new VarietyName($properties);

	}

	public static function create(VarietyName $e): void {

		VarietyName::model()->insert($e);

	}

	public static function update(VarietyName $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = VarietyName::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, VarietyName $e, array $properties): void {

		self::$affected = VarietyName::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(VarietyName $e): void {

		$e->expects(['id']);

		self::$affected = VarietyName::model()->delete($e);

	}

}


class VarietyNamePage extends \ModulePage {

	protected string $module = 'plant\VarietyName';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? VarietyNameAiLib::getPropertiesCreate(),
		   $propertiesUpdate ?? VarietyNameAiLib::getPropertiesUpdate()
		);
	}

}
?>