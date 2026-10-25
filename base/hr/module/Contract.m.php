<?php
namespace hr;

abstract class ContractElement extends \Element {

	use \FilterElement;

	private static ?ContractModel $model = NULL;

	const CDI = 'cdi';
	const CDD = 'cdd';
	const SEASONAL = 'seasonal';
	const APPRENTICESHIP = 'apprenticeship';
	const INTERNSHIP = 'internship';
	const OTHER = 'other';
	const PROFESSIONALIZATION = 'professionalization';
	const TEMPORARY = 'temporary';
	const EMPLOYER_GROUP = 'employer-group';

	const WEEK = 'week';
	const YEAR = 'year';

	const MONDAY = 1;
	const TUESDAY = 2;
	const WEDNESDAY = 4;
	const THURSDAY = 8;
	const FRIDAY = 16;
	const SATURDAY = 32;
	const SUNDAY = 64;

	public static function getSelection(): array {
		return Contract::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): ContractModel {
		if(self::$model === NULL) {
			self::$model = new ContractModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Contract::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class ContractModel extends \ModuleModel {

	protected string $module = 'hr\Contract';
	protected string $package = 'hr';
	protected string $table = 'hrContract';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'presence' => ['element32', 'hr\Presence', 'unique' => TRUE, 'cast' => 'element'],
			'type' => ['enum', [\hr\Contract::CDI, \hr\Contract::CDD, \hr\Contract::SEASONAL, \hr\Contract::APPRENTICESHIP, \hr\Contract::INTERNSHIP, \hr\Contract::OTHER, \hr\Contract::PROFESSIONALIZATION, \hr\Contract::TEMPORARY, \hr\Contract::EMPLOYER_GROUP], 'cast' => 'enum'],
			'weeklyHours' => ['float32', 'min' => 0.0, 'max' => 48.0, 'null' => TRUE, 'cast' => 'float'],
			'annualHours' => ['float32', 'min' => 0.0, 'max' => 2300.0, 'null' => TRUE, 'cast' => 'float'],
			'hoursPeriod' => ['enum', [\hr\Contract::WEEK, \hr\Contract::YEAR], 'cast' => 'enum'],
			'days' => ['set', [\hr\Contract::MONDAY, \hr\Contract::TUESDAY, \hr\Contract::WEDNESDAY, \hr\Contract::THURSDAY, \hr\Contract::FRIDAY, \hr\Contract::SATURDAY, \hr\Contract::SUNDAY], 'null' => TRUE, 'cast' => 'set'],
			'hasVariableDays' => ['bool', 'cast' => 'bool'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'presence', 'type', 'weeklyHours', 'annualHours', 'hoursPeriod', 'days', 'hasVariableDays'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
			'presence' => 'hr\Presence',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['structure']
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['presence']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'hoursPeriod' :
				return Contract::WEEK;

			case 'hasVariableDays' :
				return FALSE;

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'type' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'hoursPeriod' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): ContractModel {
		return parent::select(...$fields);
	}

	public function where(...$data): ContractModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): ContractModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): ContractModel {
		return $this->where('structure', ...$data);
	}

	public function wherePresence(...$data): ContractModel {
		return $this->where('presence', ...$data);
	}

	public function whereType(...$data): ContractModel {
		return $this->where('type', ...$data);
	}

	public function whereWeeklyHours(...$data): ContractModel {
		return $this->where('weeklyHours', ...$data);
	}

	public function whereAnnualHours(...$data): ContractModel {
		return $this->where('annualHours', ...$data);
	}

	public function whereHoursPeriod(...$data): ContractModel {
		return $this->where('hoursPeriod', ...$data);
	}

	public function whereDays(...$data): ContractModel {
		return $this->where('days', ...$data);
	}

	public function whereHasVariableDays(...$data): ContractModel {
		return $this->where('hasVariableDays', ...$data);
	}


}


abstract class ContractCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Contract {

		$e = new Contract();

		if(empty($id)) {
			Contract::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Contract::getSelection();
		}

		if(Contract::model()
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
			$properties = Contract::getSelection();
		}

		if($sort !== NULL) {
			Contract::model()->sort($sort);
		}

		return Contract::model()
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

	public static function getNewElement(array $properties = []): Contract {

		return new Contract($properties);

	}

	public static function create(Contract $e): void {

		Contract::model()->insert($e);

	}

	public static function update(Contract $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Contract::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Contract $e, array $properties): void {

		self::$affected = Contract::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Contract $e): void {

		$e->expects(['id']);

		self::$affected = Contract::model()->delete($e);

	}

}


class ContractPage extends \ModulePage {

	protected string $module = 'hr\Contract';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? ContractLib::getPropertiesCreate(),
		   $propertiesUpdate ?? ContractLib::getPropertiesUpdate()
		);
	}

}
?>