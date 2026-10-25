<?php
namespace map;

abstract class DecorElement extends \Element {

	use \FilterElement;

	private static ?DecorModel $model = NULL;

	const SHEEP = 'sheep';
	const PALING = 'paling';
	const PANEL = 'panel';
	const ELECTRIC = 'electric';

	const SURFACE = 'surface';
	const BURIED = 'buried';

	public static function getSelection(): array {
		return Decor::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): DecorModel {
		if(self::$model === NULL) {
			self::$model = new DecorModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Decor::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class DecorModel extends \ModuleModel {

	protected string $module = 'map\Decor';
	protected string $package = 'map';
	protected string $table = 'mapDecor';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'layer' => ['element32', 'map\DecorLayer', 'null' => TRUE, 'cast' => 'element'],
			'kit' => ['text8', 'min' => 1, 'max' => 40, 'cast' => 'string'],
			'building' => ['element32', 'map\DecorBuilding', 'null' => TRUE, 'cast' => 'element'],
			'coordinates' => ['point', 'cast' => 'json'],
			'angle' => ['float32', 'min' => 0, 'max' => 6.2832, 'cast' => 'float'],
			'size' => ['float32', 'min' => 0.1, 'max' => 10, 'cast' => 'float'],
			'seed' => ['int32', 'min' => 0, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'width' => ['int8', 'min' => 1, 'max' => 3, 'null' => TRUE, 'cast' => 'int'],
			'model' => ['enum', [\map\Decor::SHEEP, \map\Decor::PALING, \map\Decor::PANEL, \map\Decor::ELECTRIC], 'null' => TRUE, 'cast' => 'enum'],
			'trace' => ['json', 'null' => TRUE, 'cast' => 'array'],
			'diameter' => ['int16', 'min' => 10, 'max' => 400, 'null' => TRUE, 'cast' => 'int'],
			'laying' => ['enum', [\map\Decor::SURFACE, \map\Decor::BURIED], 'null' => TRUE, 'cast' => 'enum'],
			'zone' => ['element32', 'map\Zone', 'null' => TRUE, 'cast' => 'element'],
			'plot' => ['element32', 'map\Plot', 'null' => TRUE, 'cast' => 'element'],
			'hedge' => ['bool', 'cast' => 'bool'],
			'gate' => ['float32', 'min' => 0, 'max' => 1, 'null' => TRUE, 'cast' => 'float'],
			'createdAt' => ['datetime', 'cast' => 'string'],
			'createdBy' => ['element32', 'user\User', 'cast' => 'element'],
			'updatedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'layer', 'kit', 'building', 'coordinates', 'angle', 'size', 'seed', 'width', 'model', 'trace', 'diameter', 'laying', 'zone', 'plot', 'hedge', 'gate', 'createdAt', 'createdBy', 'updatedAt'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
			'layer' => 'map\DecorLayer',
			'building' => 'map\DecorBuilding',
			'zone' => 'map\Zone',
			'plot' => 'map\Plot',
			'createdBy' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['structure'],
			['layer'],
			['building']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'angle' :
				return 0;

			case 'size' :
				return 1;

			case 'hedge' :
				return FALSE;

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

			case 'coordinates' :
				return $value === NULL ? NULL : new \Sql($this->pdo()->api->encodePoint($value));

			case 'model' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'trace' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			case 'laying' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'coordinates' :
				return $value === NULL ? NULL : $this->pdo()->api->decodePoint($value);

			case 'trace' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			default :
				return parent::decode($property, $value);

		}

	}

	public function select(...$fields): DecorModel {
		return parent::select(...$fields);
	}

	public function where(...$data): DecorModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): DecorModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): DecorModel {
		return $this->where('structure', ...$data);
	}

	public function whereLayer(...$data): DecorModel {
		return $this->where('layer', ...$data);
	}

	public function whereKit(...$data): DecorModel {
		return $this->where('kit', ...$data);
	}

	public function whereBuilding(...$data): DecorModel {
		return $this->where('building', ...$data);
	}

	public function whereCoordinates(...$data): DecorModel {
		return $this->where('coordinates', ...$data);
	}

	public function whereAngle(...$data): DecorModel {
		return $this->where('angle', ...$data);
	}

	public function whereSize(...$data): DecorModel {
		return $this->where('size', ...$data);
	}

	public function whereSeed(...$data): DecorModel {
		return $this->where('seed', ...$data);
	}

	public function whereWidth(...$data): DecorModel {
		return $this->where('width', ...$data);
	}

	public function whereModel(...$data): DecorModel {
		return $this->where('model', ...$data);
	}

	public function whereTrace(...$data): DecorModel {
		return $this->where('trace', ...$data);
	}

	public function whereDiameter(...$data): DecorModel {
		return $this->where('diameter', ...$data);
	}

	public function whereLaying(...$data): DecorModel {
		return $this->where('laying', ...$data);
	}

	public function whereZone(...$data): DecorModel {
		return $this->where('zone', ...$data);
	}

	public function wherePlot(...$data): DecorModel {
		return $this->where('plot', ...$data);
	}

	public function whereHedge(...$data): DecorModel {
		return $this->where('hedge', ...$data);
	}

	public function whereGate(...$data): DecorModel {
		return $this->where('gate', ...$data);
	}

	public function whereCreatedAt(...$data): DecorModel {
		return $this->where('createdAt', ...$data);
	}

	public function whereCreatedBy(...$data): DecorModel {
		return $this->where('createdBy', ...$data);
	}

	public function whereUpdatedAt(...$data): DecorModel {
		return $this->where('updatedAt', ...$data);
	}


}


abstract class DecorCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Decor {

		$e = new Decor();

		if(empty($id)) {
			Decor::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Decor::getSelection();
		}

		if(Decor::model()
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
			$properties = Decor::getSelection();
		}

		if($sort !== NULL) {
			Decor::model()->sort($sort);
		}

		return Decor::model()
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

	public static function getNewElement(array $properties = []): Decor {

		return new Decor($properties);

	}

	public static function create(Decor $e): void {

		Decor::model()->insert($e);

	}

	public static function update(Decor $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Decor::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Decor $e, array $properties): void {

		self::$affected = Decor::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Decor $e): void {

		$e->expects(['id']);

		self::$affected = Decor::model()->delete($e);

	}

}


class DecorPage extends \ModulePage {

	protected string $module = 'map\Decor';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? DecorLib::getPropertiesCreate(),
		   $propertiesUpdate ?? DecorLib::getPropertiesUpdate()
		);
	}

}
?>