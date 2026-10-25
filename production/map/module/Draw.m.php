<?php
namespace map;

abstract class DrawElement extends \Element {

	use \FilterElement;

	private static ?DrawModel $model = NULL;

	public static function getSelection(): array {
		return Draw::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): DrawModel {
		if(self::$model === NULL) {
			self::$model = new DrawModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Draw::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class DrawModel extends \ModuleModel {

	protected string $module = 'map\Draw';
	protected string $package = 'map';
	protected string $table = 'mapDraw';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'zone' => ['element32', 'map\Zone', 'cast' => 'element'],
			'plot' => ['element32', 'map\Plot', 'cast' => 'element'],
			'season' => ['int16', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'bed' => ['element32', 'map\Bed', 'null' => TRUE, 'cast' => 'element'],
			'corner' => ['point', 'null' => TRUE, 'cast' => 'json'],
			'angle' => ['float32', 'min' => 0, 'max' => 3.1416, 'null' => TRUE, 'cast' => 'float'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'zone', 'plot', 'season', 'bed', 'corner', 'angle'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
			'zone' => 'map\Zone',
			'plot' => 'map\Plot',
			'bed' => 'map\Bed',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['structure'],
			['plot', 'season']
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['bed', 'season']
		]);

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'corner' :
				return $value === NULL ? NULL : new \Sql($this->pdo()->api->encodePoint($value));

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'corner' :
				return $value === NULL ? NULL : $this->pdo()->api->decodePoint($value);

			default :
				return parent::decode($property, $value);

		}

	}

	public function select(...$fields): DrawModel {
		return parent::select(...$fields);
	}

	public function where(...$data): DrawModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): DrawModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): DrawModel {
		return $this->where('structure', ...$data);
	}

	public function whereZone(...$data): DrawModel {
		return $this->where('zone', ...$data);
	}

	public function wherePlot(...$data): DrawModel {
		return $this->where('plot', ...$data);
	}

	public function whereSeason(...$data): DrawModel {
		return $this->where('season', ...$data);
	}

	public function whereBed(...$data): DrawModel {
		return $this->where('bed', ...$data);
	}

	public function whereCorner(...$data): DrawModel {
		return $this->where('corner', ...$data);
	}

	public function whereAngle(...$data): DrawModel {
		return $this->where('angle', ...$data);
	}


}


abstract class DrawCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Draw {

		$e = new Draw();

		if(empty($id)) {
			Draw::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Draw::getSelection();
		}

		if(Draw::model()
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
			$properties = Draw::getSelection();
		}

		if($sort !== NULL) {
			Draw::model()->sort($sort);
		}

		return Draw::model()
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

	public static function getNewElement(array $properties = []): Draw {

		return new Draw($properties);

	}

	public static function create(Draw $e): void {

		Draw::model()->insert($e);

	}

	public static function update(Draw $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Draw::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Draw $e, array $properties): void {

		self::$affected = Draw::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Draw $e): void {

		$e->expects(['id']);

		self::$affected = Draw::model()->delete($e);

	}

}


class DrawPage extends \ModulePage {

	protected string $module = 'map\Draw';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? DrawLib::getPropertiesCreate(),
		   $propertiesUpdate ?? DrawLib::getPropertiesUpdate()
		);
	}

}
?>