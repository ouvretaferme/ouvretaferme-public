<?php
namespace map;

abstract class DecorObjectElement extends \Element {

	use \FilterElement;

	private static ?DecorObjectModel $model = NULL;

	const WATER = 'water';
	const TREE = 'tree';
	const OTHER = 'other';

	public static function getSelection(): array {
		return DecorObject::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): DecorObjectModel {
		if(self::$model === NULL) {
			self::$model = new DecorObjectModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('DecorObject::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class DecorObjectModel extends \ModuleModel {

	protected string $module = 'map\DecorObject';
	protected string $package = 'map';
	protected string $table = 'mapDecorObject';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'family' => ['enum', [\map\DecorObject::WATER, \map\DecorObject::TREE, \map\DecorObject::OTHER], 'cast' => 'enum'],
			'name' => ['text8', 'min' => 1, 'max' => 40, 'collate' => 'general', 'cast' => 'string'],
			'upload' => ['element32', 'map\DecorUpload', 'null' => TRUE, 'cast' => 'element'],
			'createdAt' => ['datetime', 'cast' => 'string'],
			'createdBy' => ['element32', 'user\User', 'cast' => 'element'],
			'updatedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'family', 'name', 'upload', 'createdAt', 'createdBy', 'updatedAt'
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

			case 'family' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): DecorObjectModel {
		return parent::select(...$fields);
	}

	public function where(...$data): DecorObjectModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): DecorObjectModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): DecorObjectModel {
		return $this->where('structure', ...$data);
	}

	public function whereFamily(...$data): DecorObjectModel {
		return $this->where('family', ...$data);
	}

	public function whereName(...$data): DecorObjectModel {
		return $this->where('name', ...$data);
	}

	public function whereUpload(...$data): DecorObjectModel {
		return $this->where('upload', ...$data);
	}

	public function whereCreatedAt(...$data): DecorObjectModel {
		return $this->where('createdAt', ...$data);
	}

	public function whereCreatedBy(...$data): DecorObjectModel {
		return $this->where('createdBy', ...$data);
	}

	public function whereUpdatedAt(...$data): DecorObjectModel {
		return $this->where('updatedAt', ...$data);
	}


}


abstract class DecorObjectCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): DecorObject {

		$e = new DecorObject();

		if(empty($id)) {
			DecorObject::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = DecorObject::getSelection();
		}

		if(DecorObject::model()
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
			$properties = DecorObject::getSelection();
		}

		if($sort !== NULL) {
			DecorObject::model()->sort($sort);
		}

		return DecorObject::model()
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

	public static function getNewElement(array $properties = []): DecorObject {

		return new DecorObject($properties);

	}

	public static function create(DecorObject $e): void {

		DecorObject::model()->insert($e);

	}

	public static function update(DecorObject $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = DecorObject::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, DecorObject $e, array $properties): void {

		self::$affected = DecorObject::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(DecorObject $e): void {

		$e->expects(['id']);

		self::$affected = DecorObject::model()->delete($e);

	}

}


class DecorObjectPage extends \ModulePage {

	protected string $module = 'map\DecorObject';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? DecorObjectLib::getPropertiesCreate(),
		   $propertiesUpdate ?? DecorObjectLib::getPropertiesUpdate()
		);
	}

}
?>