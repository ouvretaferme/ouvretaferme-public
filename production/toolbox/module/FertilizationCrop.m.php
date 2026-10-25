<?php
namespace toolbox;

abstract class FertilizationCropElement extends \Element {

	use \FilterElement;

	private static ?FertilizationCropModel $model = NULL;

	public static function getSelection(): array {
		return FertilizationCrop::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): FertilizationCropModel {
		if(self::$model === NULL) {
			self::$model = new FertilizationCropModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('FertilizationCrop::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class FertilizationCropModel extends \ModuleModel {

	protected string $module = 'toolbox\FertilizationCrop';
	protected string $package = 'toolbox';
	protected string $table = 'toolboxFertilizationCrop';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'plant' => ['element32', 'plant\Plant', 'unique' => TRUE, 'cast' => 'element'],
			'exportedNitrogen' => ['decimal', 'digits' => 5, 'decimal' => 2, 'min' => 0, 'max' => 999.99, 'null' => TRUE, 'cast' => 'float'],
			'exportedPhosphate' => ['decimal', 'digits' => 5, 'decimal' => 2, 'min' => 0, 'max' => 999.99, 'null' => TRUE, 'cast' => 'float'],
			'exportedPotash' => ['decimal', 'digits' => 5, 'decimal' => 2, 'min' => 0, 'max' => 999.99, 'null' => TRUE, 'cast' => 'float'],
			'exportedNitrogenSource' => ['element32', 'toolbox\FertilizationSource', 'null' => TRUE, 'cast' => 'element'],
			'exportedPhosphateSource' => ['element32', 'toolbox\FertilizationSource', 'null' => TRUE, 'cast' => 'element'],
			'exportedPotashSource' => ['element32', 'toolbox\FertilizationSource', 'null' => TRUE, 'cast' => 'element'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'plant', 'exportedNitrogen', 'exportedPhosphate', 'exportedPotash', 'exportedNitrogenSource', 'exportedPhosphateSource', 'exportedPotashSource'
		]);

		$this->propertiesToModule += [
			'plant' => 'plant\Plant',
			'exportedNitrogenSource' => 'toolbox\FertilizationSource',
			'exportedPhosphateSource' => 'toolbox\FertilizationSource',
			'exportedPotashSource' => 'toolbox\FertilizationSource',
		];

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['plant']
		]);

	}

	public function select(...$fields): FertilizationCropModel {
		return parent::select(...$fields);
	}

	public function where(...$data): FertilizationCropModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): FertilizationCropModel {
		return $this->where('id', ...$data);
	}

	public function wherePlant(...$data): FertilizationCropModel {
		return $this->where('plant', ...$data);
	}

	public function whereExportedNitrogen(...$data): FertilizationCropModel {
		return $this->where('exportedNitrogen', ...$data);
	}

	public function whereExportedPhosphate(...$data): FertilizationCropModel {
		return $this->where('exportedPhosphate', ...$data);
	}

	public function whereExportedPotash(...$data): FertilizationCropModel {
		return $this->where('exportedPotash', ...$data);
	}

	public function whereExportedNitrogenSource(...$data): FertilizationCropModel {
		return $this->where('exportedNitrogenSource', ...$data);
	}

	public function whereExportedPhosphateSource(...$data): FertilizationCropModel {
		return $this->where('exportedPhosphateSource', ...$data);
	}

	public function whereExportedPotashSource(...$data): FertilizationCropModel {
		return $this->where('exportedPotashSource', ...$data);
	}


}


abstract class FertilizationCropCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): FertilizationCrop {

		$e = new FertilizationCrop();

		if(empty($id)) {
			FertilizationCrop::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = FertilizationCrop::getSelection();
		}

		if(FertilizationCrop::model()
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
			$properties = FertilizationCrop::getSelection();
		}

		if($sort !== NULL) {
			FertilizationCrop::model()->sort($sort);
		}

		return FertilizationCrop::model()
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

	public static function getNewElement(array $properties = []): FertilizationCrop {

		return new FertilizationCrop($properties);

	}

	public static function create(FertilizationCrop $e): void {

		FertilizationCrop::model()->insert($e);

	}

	public static function update(FertilizationCrop $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = FertilizationCrop::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, FertilizationCrop $e, array $properties): void {

		self::$affected = FertilizationCrop::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(FertilizationCrop $e): void {

		$e->expects(['id']);

		self::$affected = FertilizationCrop::model()->delete($e);

	}

}


class FertilizationCropPage extends \ModulePage {

	protected string $module = 'toolbox\FertilizationCrop';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? FertilizationCropLib::getPropertiesCreate(),
		   $propertiesUpdate ?? FertilizationCropLib::getPropertiesUpdate()
		);
	}

}
?>