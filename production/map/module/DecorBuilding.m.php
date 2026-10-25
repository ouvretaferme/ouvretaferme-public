<?php
namespace map;

abstract class DecorBuildingElement extends \Element {

	use \FilterElement;

	private static ?DecorBuildingModel $model = NULL;

	const BUILDING = 'building';
	const HANGAR = 'hangar';
	const HOUSE = 'house';

	public static function getSelection(): array {
		return DecorBuilding::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): DecorBuildingModel {
		if(self::$model === NULL) {
			self::$model = new DecorBuildingModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('DecorBuilding::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class DecorBuildingModel extends \ModuleModel {

	protected string $module = 'map\DecorBuilding';
	protected string $package = 'map';
	protected string $table = 'mapDecorBuilding';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'type' => ['enum', [\map\DecorBuilding::BUILDING, \map\DecorBuilding::HANGAR, \map\DecorBuilding::HOUSE], 'cast' => 'enum'],
			'name' => ['text8', 'min' => 1, 'max' => 40, 'collate' => 'general', 'cast' => 'string'],
			'length' => ['float32', 'min' => 3, 'max' => 30, 'cast' => 'float'],
			'width' => ['float32', 'min' => 3, 'max' => 16, 'null' => TRUE, 'cast' => 'float'],
			'height' => ['float32', 'min' => 2.5, 'max' => 7, 'null' => TRUE, 'cast' => 'float'],
			'settings' => ['json', 'cast' => 'array'],
			'upload' => ['element32', 'map\DecorUpload', 'null' => TRUE, 'cast' => 'element'],
			'createdAt' => ['datetime', 'cast' => 'string'],
			'createdBy' => ['element32', 'user\User', 'cast' => 'element'],
			'updatedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'type', 'name', 'length', 'width', 'height', 'settings', 'upload', 'createdAt', 'createdBy', 'updatedAt'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
			'upload' => 'map\DecorUpload',
			'createdBy' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['structure']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'createdAt' :
				return new \Sql('NOW()');

			case 'createdBy' :
				return \user\ConnectionLib::getOnline();

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'type' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'settings' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'settings' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			default :
				return parent::decode($property, $value);

		}

	}

	public function select(...$fields): DecorBuildingModel {
		return parent::select(...$fields);
	}

	public function where(...$data): DecorBuildingModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): DecorBuildingModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): DecorBuildingModel {
		return $this->where('structure', ...$data);
	}

	public function whereType(...$data): DecorBuildingModel {
		return $this->where('type', ...$data);
	}

	public function whereName(...$data): DecorBuildingModel {
		return $this->where('name', ...$data);
	}

	public function whereLength(...$data): DecorBuildingModel {
		return $this->where('length', ...$data);
	}

	public function whereWidth(...$data): DecorBuildingModel {
		return $this->where('width', ...$data);
	}

	public function whereHeight(...$data): DecorBuildingModel {
		return $this->where('height', ...$data);
	}

	public function whereSettings(...$data): DecorBuildingModel {
		return $this->where('settings', ...$data);
	}

	public function whereUpload(...$data): DecorBuildingModel {
		return $this->where('upload', ...$data);
	}

	public function whereCreatedAt(...$data): DecorBuildingModel {
		return $this->where('createdAt', ...$data);
	}

	public function whereCreatedBy(...$data): DecorBuildingModel {
		return $this->where('createdBy', ...$data);
	}

	public function whereUpdatedAt(...$data): DecorBuildingModel {
		return $this->where('updatedAt', ...$data);
	}


}


abstract class DecorBuildingCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): DecorBuilding {

		$e = new DecorBuilding();

		if(empty($id)) {
			DecorBuilding::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = DecorBuilding::getSelection();
		}

		if(DecorBuilding::model()
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
			$properties = DecorBuilding::getSelection();
		}

		if($sort !== NULL) {
			DecorBuilding::model()->sort($sort);
		}

		return DecorBuilding::model()
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

	public static function getNewElement(array $properties = []): DecorBuilding {

		return new DecorBuilding($properties);

	}

	public static function create(DecorBuilding $e): void {

		DecorBuilding::model()->insert($e);

	}

	public static function update(DecorBuilding $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = DecorBuilding::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, DecorBuilding $e, array $properties): void {

		self::$affected = DecorBuilding::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(DecorBuilding $e): void {

		$e->expects(['id']);

		self::$affected = DecorBuilding::model()->delete($e);

	}

}


class DecorBuildingPage extends \ModulePage {

	protected string $module = 'map\DecorBuilding';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? DecorBuildingLib::getPropertiesCreate(),
		   $propertiesUpdate ?? DecorBuildingLib::getPropertiesUpdate()
		);
	}

}
?>