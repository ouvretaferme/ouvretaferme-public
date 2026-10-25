<?php
namespace learning;

abstract class EnrollmentElement extends \Element {

	use \FilterElement;

	private static ?EnrollmentModel $model = NULL;

	const REQUESTED = 'requested';
	const CONFIRMED = 'confirmed';
	const CANCELED = 'canceled';

	public static function getSelection(): array {
		return Enrollment::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): EnrollmentModel {
		if(self::$model === NULL) {
			self::$model = new EnrollmentModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Enrollment::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class EnrollmentModel extends \ModuleModel {

	protected string $module = 'learning\Enrollment';
	protected string $package = 'learning';
	protected string $table = 'learningEnrollment';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'user' => ['element32', 'user\User', 'cast' => 'element'],
			'course' => ['element32', 'learning\Course', 'cast' => 'element'],
			'structure' => ['element32', 'structure\Structure', 'null' => TRUE, 'cast' => 'element'],
			'status' => ['enum', [\learning\Enrollment::REQUESTED, \learning\Enrollment::CONFIRMED, \learning\Enrollment::CANCELED], 'cast' => 'enum'],
			'fundingRequested' => ['bool', 'null' => TRUE, 'cast' => 'bool'],
			'confirmedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'user', 'course', 'structure', 'status', 'fundingRequested', 'confirmedAt', 'createdAt'
		]);

		$this->propertiesToModule += [
			'user' => 'user\User',
			'course' => 'learning\Course',
			'structure' => 'structure\Structure',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['course', 'confirmedAt'],
			['course', 'status']
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['user', 'course']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'status' :
				return Enrollment::CONFIRMED;

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

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): EnrollmentModel {
		return parent::select(...$fields);
	}

	public function where(...$data): EnrollmentModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): EnrollmentModel {
		return $this->where('id', ...$data);
	}

	public function whereUser(...$data): EnrollmentModel {
		return $this->where('user', ...$data);
	}

	public function whereCourse(...$data): EnrollmentModel {
		return $this->where('course', ...$data);
	}

	public function whereStructure(...$data): EnrollmentModel {
		return $this->where('structure', ...$data);
	}

	public function whereStatus(...$data): EnrollmentModel {
		return $this->where('status', ...$data);
	}

	public function whereFundingRequested(...$data): EnrollmentModel {
		return $this->where('fundingRequested', ...$data);
	}

	public function whereConfirmedAt(...$data): EnrollmentModel {
		return $this->where('confirmedAt', ...$data);
	}

	public function whereCreatedAt(...$data): EnrollmentModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class EnrollmentCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Enrollment {

		$e = new Enrollment();

		if(empty($id)) {
			Enrollment::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Enrollment::getSelection();
		}

		if(Enrollment::model()
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
			$properties = Enrollment::getSelection();
		}

		if($sort !== NULL) {
			Enrollment::model()->sort($sort);
		}

		return Enrollment::model()
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

	public static function getNewElement(array $properties = []): Enrollment {

		return new Enrollment($properties);

	}

	public static function create(Enrollment $e): void {

		Enrollment::model()->insert($e);

	}

	public static function update(Enrollment $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Enrollment::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Enrollment $e, array $properties): void {

		self::$affected = Enrollment::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Enrollment $e): void {

		$e->expects(['id']);

		self::$affected = Enrollment::model()->delete($e);

	}

}


class EnrollmentPage extends \ModulePage {

	protected string $module = 'learning\Enrollment';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? EnrollmentLib::getPropertiesCreate(),
		   $propertiesUpdate ?? EnrollmentLib::getPropertiesUpdate()
		);
	}

}
?>