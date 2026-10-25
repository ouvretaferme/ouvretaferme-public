<?php
namespace learning;

abstract class DateElement extends \Element {

	use \FilterElement;

	private static ?DateModel $model = NULL;

	const PLANNED = 'planned';
	const CANCELED = 'canceled';

	const NETWORK = 'network';
	const TRAINEE = 'trainee';
	const NONE = 'none';

	const VIVEA = 1;
	const OCAPIAT = 2;
	const FRANCE_TRAVAIL = 4;
	const CPF = 8;
	const REGION = 16;
	const OTHER = 32;

	public static function getSelection(): array {
		return Date::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): DateModel {
		if(self::$model === NULL) {
			self::$model = new DateModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Date::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class DateModel extends \ModuleModel {

	protected string $module = 'learning\Date';
	protected string $package = 'learning';
	protected string $table = 'learningDate';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'course' => ['element32', 'learning\Course', 'cast' => 'element'],
			'startAt' => ['datetime', 'cast' => 'string'],
			'endAt' => ['datetime', 'cast' => 'string'],
			'place' => ['text8', 'min' => 1, 'max' => 250, 'null' => TRUE, 'cast' => 'string'],
			'link' => ['url', 'null' => TRUE, 'cast' => 'string'],
			'linkMailedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'seats' => ['int16', 'min' => 1, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'externalUrl' => ['url', 'null' => TRUE, 'cast' => 'string'],
			'enrollmentPaused' => ['bool', 'cast' => 'bool'],
			'status' => ['enum', [\learning\Date::PLANNED, \learning\Date::CANCELED], 'cast' => 'enum'],
			'offer' => ['enum', [\learning\Date::NETWORK, \learning\Date::TRAINEE, \learning\Date::NONE], 'cast' => 'enum'],
			'priceFunded' => ['decimal', 'digits' => 8, 'decimal' => 2, 'min' => 0.0, 'max' => 10000.0, 'null' => TRUE, 'cast' => 'float'],
			'price' => ['decimal', 'digits' => 8, 'decimal' => 2, 'min' => 0.0, 'max' => 10000.0, 'null' => TRUE, 'cast' => 'float'],
			'funding' => ['set', [\learning\Date::VIVEA, \learning\Date::OCAPIAT, \learning\Date::FRANCE_TRAVAIL, \learning\Date::CPF, \learning\Date::REGION, \learning\Date::OTHER], 'null' => TRUE, 'cast' => 'set'],
			'fundingNote' => ['text8', 'min' => 1, 'max' => 250, 'null' => TRUE, 'cast' => 'string'],
			'quizOpenedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'minutes' => ['int16', 'min' => 30, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'course', 'startAt', 'endAt', 'place', 'link', 'linkMailedAt', 'seats', 'externalUrl', 'enrollmentPaused', 'status', 'offer', 'priceFunded', 'price', 'funding', 'fundingNote', 'quizOpenedAt', 'minutes', 'createdAt'
		]);

		$this->propertiesToModule += [
			'course' => 'learning\Course',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['course', 'startAt'],
			['startAt']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'enrollmentPaused' :
				return FALSE;

			case 'status' :
				return Date::PLANNED;

			case 'offer' :
				return Date::NONE;

			case 'createdAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'status' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'offer' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): DateModel {
		return parent::select(...$fields);
	}

	public function where(...$data): DateModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): DateModel {
		return $this->where('id', ...$data);
	}

	public function whereCourse(...$data): DateModel {
		return $this->where('course', ...$data);
	}

	public function whereStartAt(...$data): DateModel {
		return $this->where('startAt', ...$data);
	}

	public function whereEndAt(...$data): DateModel {
		return $this->where('endAt', ...$data);
	}

	public function wherePlace(...$data): DateModel {
		return $this->where('place', ...$data);
	}

	public function whereLink(...$data): DateModel {
		return $this->where('link', ...$data);
	}

	public function whereLinkMailedAt(...$data): DateModel {
		return $this->where('linkMailedAt', ...$data);
	}

	public function whereSeats(...$data): DateModel {
		return $this->where('seats', ...$data);
	}

	public function whereExternalUrl(...$data): DateModel {
		return $this->where('externalUrl', ...$data);
	}

	public function whereEnrollmentPaused(...$data): DateModel {
		return $this->where('enrollmentPaused', ...$data);
	}

	public function whereStatus(...$data): DateModel {
		return $this->where('status', ...$data);
	}

	public function whereOffer(...$data): DateModel {
		return $this->where('offer', ...$data);
	}

	public function wherePriceFunded(...$data): DateModel {
		return $this->where('priceFunded', ...$data);
	}

	public function wherePrice(...$data): DateModel {
		return $this->where('price', ...$data);
	}

	public function whereFunding(...$data): DateModel {
		return $this->where('funding', ...$data);
	}

	public function whereFundingNote(...$data): DateModel {
		return $this->where('fundingNote', ...$data);
	}

	public function whereQuizOpenedAt(...$data): DateModel {
		return $this->where('quizOpenedAt', ...$data);
	}

	public function whereMinutes(...$data): DateModel {
		return $this->where('minutes', ...$data);
	}

	public function whereCreatedAt(...$data): DateModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class DateCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Date {

		$e = new Date();

		if(empty($id)) {
			Date::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Date::getSelection();
		}

		if(Date::model()
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
			$properties = Date::getSelection();
		}

		if($sort !== NULL) {
			Date::model()->sort($sort);
		}

		return Date::model()
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

	public static function getNewElement(array $properties = []): Date {

		return new Date($properties);

	}

	public static function create(Date $e): void {

		Date::model()->insert($e);

	}

	public static function update(Date $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Date::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Date $e, array $properties): void {

		self::$affected = Date::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Date $e): void {

		$e->expects(['id']);

		self::$affected = Date::model()->delete($e);

	}

}


class DatePage extends \ModulePage {

	protected string $module = 'learning\Date';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? DateLib::getPropertiesCreate(),
		   $propertiesUpdate ?? DateLib::getPropertiesUpdate()
		);
	}

}
?>