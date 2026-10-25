<?php
namespace structure;

abstract class SystemElement extends \Element {

	use \FilterElement;

	private static ?SystemModel $model = NULL;

	const OCEANIC = 'oceanic';
	const CONTINENTAL = 'continental';
	const MEDITERRANEAN = 'mediterranean';
	const MOUNTAIN = 'mountain';

	const SABLEUX = 'sableux';
	const LIMONEUX = 'limoneux';
	const ARGILEUX = 'argileux';
	const SABLO_LIMONEUX = 'sablo-limoneux';
	const LIMONO_SABLEUX = 'limono-sableux';
	const SABLO_ARGILEUX = 'sablo-argileux';
	const SABLO_ARGILO_LIMONEUX = 'sablo-argilo-limoneux';
	const ARGILO_SABLEUX = 'argilo-sableux';
	const LIMONO_ARGILEUX = 'limono-argileux';
	const LIMONO_ARGILO_SABLEUX = 'limono-argilo-sableux';
	const ARGILO_LIMONEUX = 'argilo-limoneux';

	public static function getSelection(): array {
		return System::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): SystemModel {
		if(self::$model === NULL) {
			self::$model = new SystemModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('System::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class SystemModel extends \ModuleModel {

	protected string $module = 'structure\System';
	protected string $package = 'structure';
	protected string $table = 'structureSystem';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'unique' => TRUE, 'cast' => 'element'],
			'place' => ['text8', 'min' => 1, 'max' => NULL, 'null' => TRUE, 'cast' => 'string'],
			'placeLngLat' => ['point', 'null' => TRUE, 'cast' => 'json'],
			'placeCity' => ['textFixed', 'min' => 5, 'max' => 5, 'charset' => 'ascii', 'null' => TRUE, 'cast' => 'string'],
			'placeDepartment' => ['text8', 'min' => 2, 'max' => 3, 'charset' => 'ascii', 'null' => TRUE, 'cast' => 'string'],
			'placeEpci' => ['textFixed', 'min' => 9, 'max' => 9, 'charset' => 'ascii', 'null' => TRUE, 'cast' => 'string'],
			'totalArea' => ['float32', 'min' => 0.0, 'max' => 500.0, 'null' => TRUE, 'cast' => 'float'],
			'altitude' => ['int32', 'min' => 0, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'climate' => ['enum', [\structure\System::OCEANIC, \structure\System::CONTINENTAL, \structure\System::MEDITERRANEAN, \structure\System::MOUNTAIN], 'null' => TRUE, 'cast' => 'enum'],
			'soil' => ['enum', [\structure\System::SABLEUX, \structure\System::LIMONEUX, \structure\System::ARGILEUX, \structure\System::SABLO_LIMONEUX, \structure\System::LIMONO_SABLEUX, \structure\System::SABLO_ARGILEUX, \structure\System::SABLO_ARGILO_LIMONEUX, \structure\System::ARGILO_SABLEUX, \structure\System::LIMONO_ARGILEUX, \structure\System::LIMONO_ARGILO_SABLEUX, \structure\System::ARGILO_LIMONEUX], 'null' => TRUE, 'cast' => 'enum'],
			'ph' => ['decimal', 'digits' => 3, 'decimal' => 1, 'min' => 1.0, 'max' => 14.0, 'null' => TRUE, 'cast' => 'float'],
			'hrFarmer' => ['decimal', 'digits' => 3, 'decimal' => 1, 'min' => 0.0, 'max' => 99.9, 'null' => TRUE, 'cast' => 'float'],
			'hrEmployee' => ['decimal', 'digits' => 3, 'decimal' => 1, 'min' => 0.0, 'max' => 99.9, 'null' => TRUE, 'cast' => 'float'],
			'hrOther' => ['decimal', 'digits' => 3, 'decimal' => 1, 'min' => 0.0, 'max' => 99.9, 'null' => TRUE, 'cast' => 'float'],
			'sellingDirect' => ['int8', 'min' => 0, 'max' => 100, 'null' => TRUE, 'cast' => 'int'],
			'sellingSemi' => ['int8', 'min' => 0, 'max' => 100, 'null' => TRUE, 'cast' => 'int'],
			'sellingLong' => ['int8', 'min' => 0, 'max' => 100, 'null' => TRUE, 'cast' => 'int'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'place', 'placeLngLat', 'placeCity', 'placeDepartment', 'placeEpci', 'totalArea', 'altitude', 'climate', 'soil', 'ph', 'hrFarmer', 'hrEmployee', 'hrOther', 'sellingDirect', 'sellingSemi', 'sellingLong'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
		];

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['structure']
		]);

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'placeLngLat' :
				return $value === NULL ? NULL : new \Sql($this->pdo()->api->encodePoint($value));

			case 'climate' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'soil' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'placeLngLat' :
				return $value === NULL ? NULL : $this->pdo()->api->decodePoint($value);

			default :
				return parent::decode($property, $value);

		}

	}

	public function select(...$fields): SystemModel {
		return parent::select(...$fields);
	}

	public function where(...$data): SystemModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): SystemModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): SystemModel {
		return $this->where('structure', ...$data);
	}

	public function wherePlace(...$data): SystemModel {
		return $this->where('place', ...$data);
	}

	public function wherePlaceLngLat(...$data): SystemModel {
		return $this->where('placeLngLat', ...$data);
	}

	public function wherePlaceCity(...$data): SystemModel {
		return $this->where('placeCity', ...$data);
	}

	public function wherePlaceDepartment(...$data): SystemModel {
		return $this->where('placeDepartment', ...$data);
	}

	public function wherePlaceEpci(...$data): SystemModel {
		return $this->where('placeEpci', ...$data);
	}

	public function whereTotalArea(...$data): SystemModel {
		return $this->where('totalArea', ...$data);
	}

	public function whereAltitude(...$data): SystemModel {
		return $this->where('altitude', ...$data);
	}

	public function whereClimate(...$data): SystemModel {
		return $this->where('climate', ...$data);
	}

	public function whereSoil(...$data): SystemModel {
		return $this->where('soil', ...$data);
	}

	public function wherePh(...$data): SystemModel {
		return $this->where('ph', ...$data);
	}

	public function whereHrFarmer(...$data): SystemModel {
		return $this->where('hrFarmer', ...$data);
	}

	public function whereHrEmployee(...$data): SystemModel {
		return $this->where('hrEmployee', ...$data);
	}

	public function whereHrOther(...$data): SystemModel {
		return $this->where('hrOther', ...$data);
	}

	public function whereSellingDirect(...$data): SystemModel {
		return $this->where('sellingDirect', ...$data);
	}

	public function whereSellingSemi(...$data): SystemModel {
		return $this->where('sellingSemi', ...$data);
	}

	public function whereSellingLong(...$data): SystemModel {
		return $this->where('sellingLong', ...$data);
	}


}


abstract class SystemCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): System {

		$e = new System();

		if(empty($id)) {
			System::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = System::getSelection();
		}

		if(System::model()
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
			$properties = System::getSelection();
		}

		if($sort !== NULL) {
			System::model()->sort($sort);
		}

		return System::model()
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

	public static function getNewElement(array $properties = []): System {

		return new System($properties);

	}

	public static function create(System $e): void {

		System::model()->insert($e);

	}

	public static function update(System $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = System::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, System $e, array $properties): void {

		self::$affected = System::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(System $e): void {

		$e->expects(['id']);

		self::$affected = System::model()->delete($e);

	}

}


class SystemPage extends \ModulePage {

	protected string $module = 'structure\System';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? SystemLib::getPropertiesCreate(),
		   $propertiesUpdate ?? SystemLib::getPropertiesUpdate()
		);
	}

}
?>