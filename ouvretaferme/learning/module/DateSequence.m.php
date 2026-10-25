<?php
namespace learning;

abstract class DateSequenceElement extends \Element {

	use \FilterElement;

	private static ?DateSequenceModel $model = NULL;

	const ONSITE = 'onsite';
	const REMOTE = 'remote';

	public static function getSelection(): array {
		return DateSequence::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): DateSequenceModel {
		if(self::$model === NULL) {
			self::$model = new DateSequenceModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('DateSequence::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class DateSequenceModel extends \ModuleModel {

	protected string $module = 'learning\DateSequence';
	protected string $package = 'learning';
	protected string $table = 'learningDateSequence';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'date' => ['element32', 'learning\Date', 'cast' => 'element'],
			'startAt' => ['datetime', 'cast' => 'string'],
			'endAt' => ['datetime', 'cast' => 'string'],
			'mode' => ['enum', [\learning\DateSequence::ONSITE, \learning\DateSequence::REMOTE], 'cast' => 'enum'],
			'place' => ['text8', 'min' => 1, 'max' => 250, 'null' => TRUE, 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'date', 'startAt', 'endAt', 'mode', 'place'
		]);

		$this->propertiesToModule += [
			'date' => 'learning\Date',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['date', 'startAt']
		]);

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'mode' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): DateSequenceModel {
		return parent::select(...$fields);
	}

	public function where(...$data): DateSequenceModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): DateSequenceModel {
		return $this->where('id', ...$data);
	}

	public function whereDate(...$data): DateSequenceModel {
		return $this->where('date', ...$data);
	}

	public function whereStartAt(...$data): DateSequenceModel {
		return $this->where('startAt', ...$data);
	}

	public function whereEndAt(...$data): DateSequenceModel {
		return $this->where('endAt', ...$data);
	}

	public function whereMode(...$data): DateSequenceModel {
		return $this->where('mode', ...$data);
	}

	public function wherePlace(...$data): DateSequenceModel {
		return $this->where('place', ...$data);
	}


}


abstract class DateSequenceCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): DateSequence {

		$e = new DateSequence();

		if(empty($id)) {
			DateSequence::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = DateSequence::getSelection();
		}

		if(DateSequence::model()
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
			$properties = DateSequence::getSelection();
		}

		if($sort !== NULL) {
			DateSequence::model()->sort($sort);
		}

		return DateSequence::model()
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

	public static function getNewElement(array $properties = []): DateSequence {

		return new DateSequence($properties);

	}

	public static function create(DateSequence $e): void {

		DateSequence::model()->insert($e);

	}

	public static function update(DateSequence $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = DateSequence::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, DateSequence $e, array $properties): void {

		self::$affected = DateSequence::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(DateSequence $e): void {

		$e->expects(['id']);

		self::$affected = DateSequence::model()->delete($e);

	}

}


class DateSequencePage extends \ModulePage {

	protected string $module = 'learning\DateSequence';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? DateSequenceLib::getPropertiesCreate(),
		   $propertiesUpdate ?? DateSequenceLib::getPropertiesUpdate()
		);
	}

}
?>