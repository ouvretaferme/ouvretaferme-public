<?php
namespace toolbox;

abstract class FertilizationSourceElement extends \Element {

	use \FilterElement;

	private static ?FertilizationSourceModel $model = NULL;

	public static function getSelection(): array {
		return FertilizationSource::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): FertilizationSourceModel {
		if(self::$model === NULL) {
			self::$model = new FertilizationSourceModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('FertilizationSource::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class FertilizationSourceModel extends \ModuleModel {

	protected string $module = 'toolbox\FertilizationSource';
	protected string $package = 'toolbox';
	protected string $table = 'toolboxFertilizationSource';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'name' => ['text8', 'min' => 1, 'max' => NULL, 'unique' => TRUE, 'cast' => 'string'],
			'url' => ['url', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'name', 'url'
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['name']
		]);

	}

	public function select(...$fields): FertilizationSourceModel {
		return parent::select(...$fields);
	}

	public function where(...$data): FertilizationSourceModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): FertilizationSourceModel {
		return $this->where('id', ...$data);
	}

	public function whereName(...$data): FertilizationSourceModel {
		return $this->where('name', ...$data);
	}

	public function whereUrl(...$data): FertilizationSourceModel {
		return $this->where('url', ...$data);
	}


}


abstract class FertilizationSourceCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): FertilizationSource {

		$e = new FertilizationSource();

		if(empty($id)) {
			FertilizationSource::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = FertilizationSource::getSelection();
		}

		if(FertilizationSource::model()
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
			$properties = FertilizationSource::getSelection();
		}

		if($sort !== NULL) {
			FertilizationSource::model()->sort($sort);
		}

		return FertilizationSource::model()
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

	public static function getNewElement(array $properties = []): FertilizationSource {

		return new FertilizationSource($properties);

	}

	public static function create(FertilizationSource $e): void {

		FertilizationSource::model()->insert($e);

	}

	public static function update(FertilizationSource $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = FertilizationSource::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, FertilizationSource $e, array $properties): void {

		self::$affected = FertilizationSource::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(FertilizationSource $e): void {

		$e->expects(['id']);

		self::$affected = FertilizationSource::model()->delete($e);

	}

}


class FertilizationSourcePage extends \ModulePage {

	protected string $module = 'toolbox\FertilizationSource';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? FertilizationSourceLib::getPropertiesCreate(),
		   $propertiesUpdate ?? FertilizationSourceLib::getPropertiesUpdate()
		);
	}

}
?>