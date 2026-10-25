<?php
namespace aggregate;

abstract class CultivationElement extends \Element {

	use \FilterElement;

	private static ?CultivationModel $model = NULL;

	const SOWING = 'sowing';
	const PLANTING = 'planting';

	const YOUNG_PLANT = 'young-plant';
	const YOUNG_PLANT_BOUGHT = 'young-plant-bought';

	const GREENHOUSE = 'greenhouse';
	const OPEN_FIELD = 'open-field';
	const MIX = 'mix';

	const BED = 'bed';
	const BLOCK = 'block';

	const WEEK = 'week';
	const MONTH = 'month';

	const KG = 'kg';
	const UNIT = 'unit';
	const BUNCH = 'bunch';

	public static function getSelection(): array {
		return Cultivation::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): CultivationModel {
		if(self::$model === NULL) {
			self::$model = new CultivationModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Cultivation::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class CultivationModel extends \ModuleModel {

	protected string $module = 'aggregate\Cultivation';
	protected string $package = 'aggregate';
	protected string $table = 'aggregateCultivation';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'plant' => ['element32', 'plant\Plant', 'cast' => 'element'],
			'seriesCultivation' => ['int32', 'min' => 1, 'max' => NULL, 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'season' => ['int16', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'startAction' => ['enum', [\aggregate\Cultivation::SOWING, \aggregate\Cultivation::PLANTING], 'cast' => 'enum'],
			'seedling' => ['enum', [\aggregate\Cultivation::SOWING, \aggregate\Cultivation::YOUNG_PLANT, \aggregate\Cultivation::YOUNG_PLANT_BOUGHT], 'null' => TRUE, 'cast' => 'enum'],
			'mode' => ['enum', [\aggregate\Cultivation::GREENHOUSE, \aggregate\Cultivation::OPEN_FIELD, \aggregate\Cultivation::MIX], 'cast' => 'enum'],
			'use' => ['enum', [\aggregate\Cultivation::BED, \aggregate\Cultivation::BLOCK], 'cast' => 'enum'],
			'bedWidth' => ['int16', 'min' => 1, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'alleyWidth' => ['int16', 'min' => 1, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'rows' => ['int8', 'min' => 1, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'rowSpacing' => ['decimal', 'digits' => 8, 'decimal' => 2, 'min' => 0.01, 'max' => 999999.99, 'null' => TRUE, 'cast' => 'float'],
			'plantSpacing' => ['decimal', 'digits' => 8, 'decimal' => 2, 'min' => 0.01, 'max' => 999999.99, 'null' => TRUE, 'cast' => 'float'],
			'density' => ['decimal', 'digits' => 10, 'decimal' => 2, 'min' => 0.01, 'max' => 99999999.99, 'null' => TRUE, 'cast' => 'float'],
			'startWeek' => ['int8', 'min' => 1, 'max' => 52, 'cast' => 'int'],
			'month' => ['int8', 'min' => 1, 'max' => 12, 'cast' => 'int'],
			'seedlingIndex' => ['int16', 'min' => -104, 'max' => 104, 'null' => TRUE, 'cast' => 'int'],
			'harvestPeriod' => ['enum', [\aggregate\Cultivation::WEEK, \aggregate\Cultivation::MONTH], 'cast' => 'enum'],
			'harvestFrom' => ['int16', 'min' => -104, 'max' => 208, 'null' => TRUE, 'cast' => 'int'],
			'harvestTo' => ['int16', 'min' => -104, 'max' => 208, 'null' => TRUE, 'cast' => 'int'],
			'harvestCount' => ['int16', 'min' => 0, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'harvestWeeks' => ['json', 'cast' => 'array'],
			'yieldExpected' => ['decimal', 'digits' => 8, 'decimal' => 2, 'min' => 0.0, 'max' => 999999.99, 'null' => TRUE, 'cast' => 'float'],
			'mainUnit' => ['enum', [\aggregate\Cultivation::KG, \aggregate\Cultivation::UNIT, \aggregate\Cultivation::BUNCH], 'cast' => 'enum'],
			'altitude' => ['int16', 'min' => 0, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'structureSeries' => ['int32', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'online' => ['bool', 'cast' => 'bool'],
			'createdAt' => ['date', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'plant', 'seriesCultivation', 'structure', 'season', 'startAction', 'seedling', 'mode', 'use', 'bedWidth', 'alleyWidth', 'rows', 'rowSpacing', 'plantSpacing', 'density', 'startWeek', 'month', 'seedlingIndex', 'harvestPeriod', 'harvestFrom', 'harvestTo', 'harvestCount', 'harvestWeeks', 'yieldExpected', 'mainUnit', 'altitude', 'structureSeries', 'online', 'createdAt'
		]);

		$this->propertiesToModule += [
			'plant' => 'plant\Plant',
			'structure' => 'structure\Structure',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['plant', 'online', 'startAction', 'seedling'],
			['online']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'harvestWeeks' :
				return [];

			case 'mainUnit' :
				return Cultivation::KG;

			case 'online' :
				return FALSE;

			case 'createdAt' :
				return new \Sql('CURDATE()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'startAction' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'seedling' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'mode' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'use' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'harvestPeriod' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'harvestWeeks' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			case 'mainUnit' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'harvestWeeks' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			default :
				return parent::decode($property, $value);

		}

	}

	public function select(...$fields): CultivationModel {
		return parent::select(...$fields);
	}

	public function where(...$data): CultivationModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): CultivationModel {
		return $this->where('id', ...$data);
	}

	public function wherePlant(...$data): CultivationModel {
		return $this->where('plant', ...$data);
	}

	public function whereSeriesCultivation(...$data): CultivationModel {
		return $this->where('seriesCultivation', ...$data);
	}

	public function whereStructure(...$data): CultivationModel {
		return $this->where('structure', ...$data);
	}

	public function whereSeason(...$data): CultivationModel {
		return $this->where('season', ...$data);
	}

	public function whereStartAction(...$data): CultivationModel {
		return $this->where('startAction', ...$data);
	}

	public function whereSeedling(...$data): CultivationModel {
		return $this->where('seedling', ...$data);
	}

	public function whereMode(...$data): CultivationModel {
		return $this->where('mode', ...$data);
	}

	public function whereUse(...$data): CultivationModel {
		return $this->where('use', ...$data);
	}

	public function whereBedWidth(...$data): CultivationModel {
		return $this->where('bedWidth', ...$data);
	}

	public function whereAlleyWidth(...$data): CultivationModel {
		return $this->where('alleyWidth', ...$data);
	}

	public function whereRows(...$data): CultivationModel {
		return $this->where('rows', ...$data);
	}

	public function whereRowSpacing(...$data): CultivationModel {
		return $this->where('rowSpacing', ...$data);
	}

	public function wherePlantSpacing(...$data): CultivationModel {
		return $this->where('plantSpacing', ...$data);
	}

	public function whereDensity(...$data): CultivationModel {
		return $this->where('density', ...$data);
	}

	public function whereStartWeek(...$data): CultivationModel {
		return $this->where('startWeek', ...$data);
	}

	public function whereMonth(...$data): CultivationModel {
		return $this->where('month', ...$data);
	}

	public function whereSeedlingIndex(...$data): CultivationModel {
		return $this->where('seedlingIndex', ...$data);
	}

	public function whereHarvestPeriod(...$data): CultivationModel {
		return $this->where('harvestPeriod', ...$data);
	}

	public function whereHarvestFrom(...$data): CultivationModel {
		return $this->where('harvestFrom', ...$data);
	}

	public function whereHarvestTo(...$data): CultivationModel {
		return $this->where('harvestTo', ...$data);
	}

	public function whereHarvestCount(...$data): CultivationModel {
		return $this->where('harvestCount', ...$data);
	}

	public function whereHarvestWeeks(...$data): CultivationModel {
		return $this->where('harvestWeeks', ...$data);
	}

	public function whereYieldExpected(...$data): CultivationModel {
		return $this->where('yieldExpected', ...$data);
	}

	public function whereMainUnit(...$data): CultivationModel {
		return $this->where('mainUnit', ...$data);
	}

	public function whereAltitude(...$data): CultivationModel {
		return $this->where('altitude', ...$data);
	}

	public function whereStructureSeries(...$data): CultivationModel {
		return $this->where('structureSeries', ...$data);
	}

	public function whereOnline(...$data): CultivationModel {
		return $this->where('online', ...$data);
	}

	public function whereCreatedAt(...$data): CultivationModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class CultivationCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Cultivation {

		$e = new Cultivation();

		if(empty($id)) {
			Cultivation::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Cultivation::getSelection();
		}

		if(Cultivation::model()
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
			$properties = Cultivation::getSelection();
		}

		if($sort !== NULL) {
			Cultivation::model()->sort($sort);
		}

		return Cultivation::model()
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

	public static function getNewElement(array $properties = []): Cultivation {

		return new Cultivation($properties);

	}

	public static function create(Cultivation $e): void {

		Cultivation::model()->insert($e);

	}

	public static function update(Cultivation $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Cultivation::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Cultivation $e, array $properties): void {

		self::$affected = Cultivation::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Cultivation $e): void {

		$e->expects(['id']);

		self::$affected = Cultivation::model()->delete($e);

	}

}


class CultivationPage extends \ModulePage {

	protected string $module = 'aggregate\Cultivation';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? CultivationAiLib::getPropertiesCreate(),
		   $propertiesUpdate ?? CultivationAiLib::getPropertiesUpdate()
		);
	}

}
?>