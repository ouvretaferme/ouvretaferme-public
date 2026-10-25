<?php
namespace structure;

abstract class SurveyElement extends \Element {

	use \FilterElement;

	private static ?SurveyModel $model = NULL;

	const DAILY = 'daily';
	const WEEKLY = 'weekly';
	const OCCASIONAL = 'occasional';
	const STOPPED = 'stopped';
	const NEVER = 'never';

	public static function getSelection(): array {
		return Survey::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): SurveyModel {
		if(self::$model === NULL) {
			self::$model = new SurveyModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Survey::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class SurveyModel extends \ModuleModel {

	protected string $module = 'structure\Survey';
	protected string $package = 'structure';
	protected string $table = 'structureSurvey';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'unique' => TRUE, 'cast' => 'element'],
			'production' => ['enum', [\structure\Survey::DAILY, \structure\Survey::WEEKLY, \structure\Survey::OCCASIONAL, \structure\Survey::STOPPED, \structure\Survey::NEVER], 'cast' => 'enum'],
			'productionMissing' => ['text16', 'min' => 0, 'max' => 5000, 'null' => TRUE, 'cast' => 'string'],
			'commercialisation' => ['enum', [\structure\Survey::DAILY, \structure\Survey::WEEKLY, \structure\Survey::OCCASIONAL, \structure\Survey::STOPPED, \structure\Survey::NEVER], 'cast' => 'enum'],
			'commercialisationMissing' => ['text16', 'min' => 0, 'max' => 5000, 'null' => TRUE, 'cast' => 'string'],
			'accounting' => ['enum', [\structure\Survey::DAILY, \structure\Survey::WEEKLY, \structure\Survey::OCCASIONAL, \structure\Survey::STOPPED, \structure\Survey::NEVER], 'cast' => 'enum'],
			'accountingMissing' => ['text16', 'min' => 0, 'max' => 5000, 'null' => TRUE, 'cast' => 'string'],
			'useful' => ['text16', 'min' => 0, 'max' => 5000, 'null' => TRUE, 'cast' => 'string'],
			'missing' => ['json', 'cast' => 'array'],
			'otherSoftware' => ['text16', 'min' => 0, 'max' => 5000, 'null' => TRUE, 'cast' => 'string'],
			'comment' => ['text16', 'min' => 0, 'max' => 5000, 'null' => TRUE, 'cast' => 'string'],
			'createdAt' => ['date', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'production', 'productionMissing', 'commercialisation', 'commercialisationMissing', 'accounting', 'accountingMissing', 'useful', 'missing', 'otherSoftware', 'comment', 'createdAt'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
		];

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['structure']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'createdAt' :
				return new \Sql('CURDATE()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'production' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'commercialisation' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'accounting' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'missing' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'missing' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			default :
				return parent::decode($property, $value);

		}

	}

	public function select(...$fields): SurveyModel {
		return parent::select(...$fields);
	}

	public function where(...$data): SurveyModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): SurveyModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): SurveyModel {
		return $this->where('structure', ...$data);
	}

	public function whereProduction(...$data): SurveyModel {
		return $this->where('production', ...$data);
	}

	public function whereProductionMissing(...$data): SurveyModel {
		return $this->where('productionMissing', ...$data);
	}

	public function whereCommercialisation(...$data): SurveyModel {
		return $this->where('commercialisation', ...$data);
	}

	public function whereCommercialisationMissing(...$data): SurveyModel {
		return $this->where('commercialisationMissing', ...$data);
	}

	public function whereAccounting(...$data): SurveyModel {
		return $this->where('accounting', ...$data);
	}

	public function whereAccountingMissing(...$data): SurveyModel {
		return $this->where('accountingMissing', ...$data);
	}

	public function whereUseful(...$data): SurveyModel {
		return $this->where('useful', ...$data);
	}

	public function whereMissing(...$data): SurveyModel {
		return $this->where('missing', ...$data);
	}

	public function whereOtherSoftware(...$data): SurveyModel {
		return $this->where('otherSoftware', ...$data);
	}

	public function whereComment(...$data): SurveyModel {
		return $this->where('comment', ...$data);
	}

	public function whereCreatedAt(...$data): SurveyModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class SurveyCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Survey {

		$e = new Survey();

		if(empty($id)) {
			Survey::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Survey::getSelection();
		}

		if(Survey::model()
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
			$properties = Survey::getSelection();
		}

		if($sort !== NULL) {
			Survey::model()->sort($sort);
		}

		return Survey::model()
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

	public static function getNewElement(array $properties = []): Survey {

		return new Survey($properties);

	}

	public static function create(Survey $e): void {

		Survey::model()->insert($e);

	}

	public static function update(Survey $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Survey::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Survey $e, array $properties): void {

		self::$affected = Survey::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Survey $e): void {

		$e->expects(['id']);

		self::$affected = Survey::model()->delete($e);

	}

}


class SurveyPage extends \ModulePage {

	protected string $module = 'structure\Survey';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? SurveyLib::getPropertiesCreate(),
		   $propertiesUpdate ?? SurveyLib::getPropertiesUpdate()
		);
	}

}
?>