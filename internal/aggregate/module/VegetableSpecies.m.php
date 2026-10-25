<?php
namespace aggregate;

abstract class VegetableSpeciesElement extends \Element {

	use \FilterElement;

	private static ?VegetableSpeciesModel $model = NULL;

	public static function getSelection(): array {
		return VegetableSpecies::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): VegetableSpeciesModel {
		if(self::$model === NULL) {
			self::$model = new VegetableSpeciesModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('VegetableSpecies::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class VegetableSpeciesModel extends \ModuleModel {

	protected string $module = 'aggregate\VegetableSpecies';
	protected string $package = 'aggregate';
	protected string $table = 'aggregateVegetableSpecies';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'season' => ['int16', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'plant' => ['element32', 'plant\Plant', 'cast' => 'element'],
			'cultivations' => ['int32', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'months' => ['json', 'cast' => 'array'],
			'online' => ['bool', 'cast' => 'bool'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'season', 'plant', 'cultivations', 'months', 'online'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
			'plant' => 'plant\Plant',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['structure', 'season'],
			['online']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'months' :
				return [];

			case 'online' :
				return FALSE;

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'months' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'months' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			default :
				return parent::decode($property, $value);

		}

	}

	public function select(...$fields): VegetableSpeciesModel {
		return parent::select(...$fields);
	}

	public function where(...$data): VegetableSpeciesModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): VegetableSpeciesModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): VegetableSpeciesModel {
		return $this->where('structure', ...$data);
	}

	public function whereSeason(...$data): VegetableSpeciesModel {
		return $this->where('season', ...$data);
	}

	public function wherePlant(...$data): VegetableSpeciesModel {
		return $this->where('plant', ...$data);
	}

	public function whereCultivations(...$data): VegetableSpeciesModel {
		return $this->where('cultivations', ...$data);
	}

	public function whereMonths(...$data): VegetableSpeciesModel {
		return $this->where('months', ...$data);
	}

	public function whereOnline(...$data): VegetableSpeciesModel {
		return $this->where('online', ...$data);
	}


}


abstract class VegetableSpeciesCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): VegetableSpecies {

		$e = new VegetableSpecies();

		if(empty($id)) {
			VegetableSpecies::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = VegetableSpecies::getSelection();
		}

		if(VegetableSpecies::model()
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
			$properties = VegetableSpecies::getSelection();
		}

		if($sort !== NULL) {
			VegetableSpecies::model()->sort($sort);
		}

		return VegetableSpecies::model()
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

	public static function getNewElement(array $properties = []): VegetableSpecies {

		return new VegetableSpecies($properties);

	}

	public static function create(VegetableSpecies $e): void {

		VegetableSpecies::model()->insert($e);

	}

	public static function update(VegetableSpecies $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = VegetableSpecies::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, VegetableSpecies $e, array $properties): void {

		self::$affected = VegetableSpecies::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(VegetableSpecies $e): void {

		$e->expects(['id']);

		self::$affected = VegetableSpecies::model()->delete($e);

	}

}


class VegetableSpeciesPage extends \ModulePage {

	protected string $module = 'aggregate\VegetableSpecies';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? VegetableSpeciesAiLib::getPropertiesCreate(),
		   $propertiesUpdate ?? VegetableSpeciesAiLib::getPropertiesUpdate()
		);
	}

}
?>