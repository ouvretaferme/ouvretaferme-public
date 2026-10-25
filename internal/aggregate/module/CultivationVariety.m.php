<?php
namespace aggregate;

abstract class CultivationVarietyElement extends \Element {

	use \FilterElement;

	private static ?CultivationVarietyModel $model = NULL;

	public static function getSelection(): array {
		return CultivationVariety::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): CultivationVarietyModel {
		if(self::$model === NULL) {
			self::$model = new CultivationVarietyModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('CultivationVariety::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class CultivationVarietyModel extends \ModuleModel {

	protected string $module = 'aggregate\CultivationVariety';
	protected string $package = 'aggregate';
	protected string $table = 'aggregateCultivationVariety';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'cultivation' => ['element32', 'aggregate\Cultivation', 'cast' => 'element'],
			'plant' => ['element32', 'plant\Plant', 'cast' => 'element'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'variety' => ['element32', 'plant\Variety', 'cast' => 'element'],
			'name' => ['text8', 'min' => 1, 'max' => 50, 'collate' => 'general', 'cast' => 'string'],
			'searchKey' => ['text8', 'min' => 1, 'max' => 50, 'cast' => 'string'],
			'online' => ['bool', 'cast' => 'bool'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'cultivation', 'plant', 'structure', 'variety', 'name', 'searchKey', 'online'
		]);

		$this->propertiesToModule += [
			'cultivation' => 'aggregate\Cultivation',
			'plant' => 'plant\Plant',
			'structure' => 'structure\Structure',
			'variety' => 'plant\Variety',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['plant', 'online'],
			['cultivation'],
			['online']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'online' :
				return FALSE;

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function select(...$fields): CultivationVarietyModel {
		return parent::select(...$fields);
	}

	public function where(...$data): CultivationVarietyModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): CultivationVarietyModel {
		return $this->where('id', ...$data);
	}

	public function whereCultivation(...$data): CultivationVarietyModel {
		return $this->where('cultivation', ...$data);
	}

	public function wherePlant(...$data): CultivationVarietyModel {
		return $this->where('plant', ...$data);
	}

	public function whereStructure(...$data): CultivationVarietyModel {
		return $this->where('structure', ...$data);
	}

	public function whereVariety(...$data): CultivationVarietyModel {
		return $this->where('variety', ...$data);
	}

	public function whereName(...$data): CultivationVarietyModel {
		return $this->where('name', ...$data);
	}

	public function whereSearchKey(...$data): CultivationVarietyModel {
		return $this->where('searchKey', ...$data);
	}

	public function whereOnline(...$data): CultivationVarietyModel {
		return $this->where('online', ...$data);
	}


}


abstract class CultivationVarietyCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): CultivationVariety {

		$e = new CultivationVariety();

		if(empty($id)) {
			CultivationVariety::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = CultivationVariety::getSelection();
		}

		if(CultivationVariety::model()
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
			$properties = CultivationVariety::getSelection();
		}

		if($sort !== NULL) {
			CultivationVariety::model()->sort($sort);
		}

		return CultivationVariety::model()
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

	public static function getNewElement(array $properties = []): CultivationVariety {

		return new CultivationVariety($properties);

	}

	public static function create(CultivationVariety $e): void {

		CultivationVariety::model()->insert($e);

	}

	public static function update(CultivationVariety $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = CultivationVariety::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, CultivationVariety $e, array $properties): void {

		self::$affected = CultivationVariety::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(CultivationVariety $e): void {

		$e->expects(['id']);

		self::$affected = CultivationVariety::model()->delete($e);

	}

}


class CultivationVarietyPage extends \ModulePage {

	protected string $module = 'aggregate\CultivationVariety';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? CultivationVarietyAiLib::getPropertiesCreate(),
		   $propertiesUpdate ?? CultivationVarietyAiLib::getPropertiesUpdate()
		);
	}

}
?>