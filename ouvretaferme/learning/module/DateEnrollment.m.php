<?php
namespace learning;

abstract class DateEnrollmentElement extends \Element {

	use \FilterElement;

	private static ?DateEnrollmentModel $model = NULL;

	const FARMER = 'farmer';
	const SOLIDARITY = 'solidarity';
	const EMPLOYEE = 'employee';
	const PROJECT = 'project';

	const REQUESTED = 'requested';
	const CONFIRMED = 'confirmed';
	const WAITING = 'waiting';
	const CANCELED = 'canceled';

	const USER = 'user';
	const COMMUNITY = 'community';
	const DATE = 'date';

	public static function getSelection(): array {
		return DateEnrollment::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): DateEnrollmentModel {
		if(self::$model === NULL) {
			self::$model = new DateEnrollmentModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('DateEnrollment::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class DateEnrollmentModel extends \ModuleModel {

	protected string $module = 'learning\DateEnrollment';
	protected string $package = 'learning';
	protected string $table = 'learningDateEnrollment';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'date' => ['element32', 'learning\Date', 'cast' => 'element'],
			'user' => ['element32', 'user\User', 'null' => TRUE, 'cast' => 'element'],
			'structure' => ['element32', 'structure\Structure', 'null' => TRUE, 'cast' => 'element'],
			'structureName' => ['text8', 'min' => 1, 'max' => 100, 'null' => TRUE, 'cast' => 'string'],
			'firstName' => ['text8', 'min' => 1, 'max' => \user\UserSetting::NAME_SIZE_MAX, 'null' => TRUE, 'cast' => 'string'],
			'lastName' => ['text8', 'min' => 1, 'max' => \user\UserSetting::NAME_SIZE_MAX, 'null' => TRUE, 'cast' => 'string'],
			'email' => ['email', 'null' => TRUE, 'cast' => 'string'],
			'token' => ['textFixed', 'min' => 30, 'max' => 30, 'charset' => 'ascii', 'null' => TRUE, 'unique' => TRUE, 'cast' => 'string'],
			'trainee' => ['enum', [\learning\DateEnrollment::FARMER, \learning\DateEnrollment::SOLIDARITY, \learning\DateEnrollment::EMPLOYEE, \learning\DateEnrollment::PROJECT], 'cast' => 'enum'],
			'status' => ['enum', [\learning\DateEnrollment::REQUESTED, \learning\DateEnrollment::CONFIRMED, \learning\DateEnrollment::WAITING, \learning\DateEnrollment::CANCELED], 'cast' => 'enum'],
			'canceledBy' => ['enum', [\learning\DateEnrollment::USER, \learning\DateEnrollment::COMMUNITY, \learning\DateEnrollment::DATE], 'null' => TRUE, 'cast' => 'enum'],
			'requestedAt' => ['datetime', 'cast' => 'string'],
			'committedAt' => ['datetime', 'cast' => 'string'],
			'confirmedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'canceledAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'mailedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'remindedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'reviewMailedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'attendedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'attendedBy' => ['element32', 'user\User', 'null' => TRUE, 'cast' => 'element'],
			'attendedMinutes' => ['int16', 'min' => 30, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'attestationMailedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'date', 'user', 'structure', 'structureName', 'firstName', 'lastName', 'email', 'token', 'trainee', 'status', 'canceledBy', 'requestedAt', 'committedAt', 'confirmedAt', 'canceledAt', 'mailedAt', 'remindedAt', 'reviewMailedAt', 'attendedAt', 'attendedBy', 'attendedMinutes', 'attestationMailedAt', 'createdAt'
		]);

		$this->propertiesToModule += [
			'date' => 'learning\Date',
			'user' => 'user\User',
			'structure' => 'structure\Structure',
			'attendedBy' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['date', 'status', 'requestedAt']
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['user', 'date'],
			['email', 'date'],
			['token']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'requestedAt' :
				return new \Sql('NOW()');

			case 'createdAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'trainee' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'status' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'canceledBy' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): DateEnrollmentModel {
		return parent::select(...$fields);
	}

	public function where(...$data): DateEnrollmentModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): DateEnrollmentModel {
		return $this->where('id', ...$data);
	}

	public function whereDate(...$data): DateEnrollmentModel {
		return $this->where('date', ...$data);
	}

	public function whereUser(...$data): DateEnrollmentModel {
		return $this->where('user', ...$data);
	}

	public function whereStructure(...$data): DateEnrollmentModel {
		return $this->where('structure', ...$data);
	}

	public function whereStructureName(...$data): DateEnrollmentModel {
		return $this->where('structureName', ...$data);
	}

	public function whereFirstName(...$data): DateEnrollmentModel {
		return $this->where('firstName', ...$data);
	}

	public function whereLastName(...$data): DateEnrollmentModel {
		return $this->where('lastName', ...$data);
	}

	public function whereEmail(...$data): DateEnrollmentModel {
		return $this->where('email', ...$data);
	}

	public function whereToken(...$data): DateEnrollmentModel {
		return $this->where('token', ...$data);
	}

	public function whereTrainee(...$data): DateEnrollmentModel {
		return $this->where('trainee', ...$data);
	}

	public function whereStatus(...$data): DateEnrollmentModel {
		return $this->where('status', ...$data);
	}

	public function whereCanceledBy(...$data): DateEnrollmentModel {
		return $this->where('canceledBy', ...$data);
	}

	public function whereRequestedAt(...$data): DateEnrollmentModel {
		return $this->where('requestedAt', ...$data);
	}

	public function whereCommittedAt(...$data): DateEnrollmentModel {
		return $this->where('committedAt', ...$data);
	}

	public function whereConfirmedAt(...$data): DateEnrollmentModel {
		return $this->where('confirmedAt', ...$data);
	}

	public function whereCanceledAt(...$data): DateEnrollmentModel {
		return $this->where('canceledAt', ...$data);
	}

	public function whereMailedAt(...$data): DateEnrollmentModel {
		return $this->where('mailedAt', ...$data);
	}

	public function whereRemindedAt(...$data): DateEnrollmentModel {
		return $this->where('remindedAt', ...$data);
	}

	public function whereReviewMailedAt(...$data): DateEnrollmentModel {
		return $this->where('reviewMailedAt', ...$data);
	}

	public function whereAttendedAt(...$data): DateEnrollmentModel {
		return $this->where('attendedAt', ...$data);
	}

	public function whereAttendedBy(...$data): DateEnrollmentModel {
		return $this->where('attendedBy', ...$data);
	}

	public function whereAttendedMinutes(...$data): DateEnrollmentModel {
		return $this->where('attendedMinutes', ...$data);
	}

	public function whereAttestationMailedAt(...$data): DateEnrollmentModel {
		return $this->where('attestationMailedAt', ...$data);
	}

	public function whereCreatedAt(...$data): DateEnrollmentModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class DateEnrollmentCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): DateEnrollment {

		$e = new DateEnrollment();

		if(empty($id)) {
			DateEnrollment::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = DateEnrollment::getSelection();
		}

		if(DateEnrollment::model()
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
			$properties = DateEnrollment::getSelection();
		}

		if($sort !== NULL) {
			DateEnrollment::model()->sort($sort);
		}

		return DateEnrollment::model()
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

	public static function getNewElement(array $properties = []): DateEnrollment {

		return new DateEnrollment($properties);

	}

	public static function create(DateEnrollment $e): void {

		DateEnrollment::model()->insert($e);

	}

	public static function update(DateEnrollment $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = DateEnrollment::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, DateEnrollment $e, array $properties): void {

		self::$affected = DateEnrollment::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(DateEnrollment $e): void {

		$e->expects(['id']);

		self::$affected = DateEnrollment::model()->delete($e);

	}

}


class DateEnrollmentPage extends \ModulePage {

	protected string $module = 'learning\DateEnrollment';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? DateEnrollmentLib::getPropertiesCreate(),
		   $propertiesUpdate ?? DateEnrollmentLib::getPropertiesUpdate()
		);
	}

}
?>