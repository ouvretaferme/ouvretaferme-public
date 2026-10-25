<?php
namespace map;

abstract class DecorLayerElement extends \Element {

	use \FilterElement;

	private static ?DecorLayerModel $model = NULL;

	const IRRIGATION = 'irrigation';

	public static function getSelection(): array {
		return DecorLayer::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): DecorLayerModel {
		if(self::$model === NULL) {
			self::$model = new DecorLayerModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('DecorLayer::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class DecorLayerModel extends \ModuleModel {

	protected string $module = 'map\DecorLayer';
	protected string $package = 'map';
	protected string $table = 'mapDecorLayer';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'name' => ['text8', 'min' => 1, 'max' => 60, 'collate' => 'general', 'cast' => 'string'],
			'type' => ['enum', [\map\DecorLayer::IRRIGATION], 'cast' => 'enum'],
			'position' => ['int8', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'createdAt' => ['datetime', 'cast' => 'string'],
			'createdBy' => ['element32', 'user\User', 'cast' => 'element'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'name', 'type', 'position', 'createdAt', 'createdBy'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
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

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): DecorLayerModel {
		return parent::select(...$fields);
	}

	public function where(...$data): DecorLayerModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): DecorLayerModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): DecorLayerModel {
		return $this->where('structure', ...$data);
	}

	public function whereName(...$data): DecorLayerModel {
		return $this->where('name', ...$data);
	}

	public function whereType(...$data): DecorLayerModel {
		return $this->where('type', ...$data);
	}

	public function wherePosition(...$data): DecorLayerModel {
		return $this->where('position', ...$data);
	}

	public function whereCreatedAt(...$data): DecorLayerModel {
		return $this->where('createdAt', ...$data);
	}

	public function whereCreatedBy(...$data): DecorLayerModel {
		return $this->where('createdBy', ...$data);
	}


}


abstract class DecorLayerCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): DecorLayer {

		$e = new DecorLayer();

		if(empty($id)) {
			DecorLayer::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = DecorLayer::getSelection();
		}

		if(DecorLayer::model()
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
			$properties = DecorLayer::getSelection();
		}

		if($sort !== NULL) {
			DecorLayer::model()->sort($sort);
		}

		return DecorLayer::model()
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

	public static function getNewElement(array $properties = []): DecorLayer {

		return new DecorLayer($properties);

	}

	public static function create(DecorLayer $e): void {

		DecorLayer::model()->insert($e);

	}

	public static function update(DecorLayer $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = DecorLayer::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, DecorLayer $e, array $properties): void {

		self::$affected = DecorLayer::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(DecorLayer $e): void {

		$e->expects(['id']);

		self::$affected = DecorLayer::model()->delete($e);

	}

}


class DecorLayerPage extends \ModulePage {

	protected string $module = 'map\DecorLayer';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? DecorLayerLib::getPropertiesCreate(),
		   $propertiesUpdate ?? DecorLayerLib::getPropertiesUpdate()
		);
	}

}
?>