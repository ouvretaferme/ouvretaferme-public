<?php
namespace aid;

abstract class EditionElement extends \Element {

	use \FilterElement;

	private static ?EditionModel $model = NULL;

	const OPEN = 'open';
	const SUSPENDED = 'suspended';
	const EXHAUSTED = 'exhausted';
	const CLOSED = 'closed';

	public static function getSelection(): array {
		return Edition::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): EditionModel {
		if(self::$model === NULL) {
			self::$model = new EditionModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Edition::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class EditionModel extends \ModuleModel {

	protected string $module = 'aid\Edition';
	protected string $package = 'aid';
	protected string $table = 'aidEdition';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'aid' => ['element32', 'aid\Aid', 'cast' => 'element'],
			'name' => ['text8', 'min' => 1, 'max' => 100, 'collate' => 'general', 'null' => TRUE, 'cast' => 'string'],
			'startDate' => ['date', 'null' => TRUE, 'cast' => 'string'],
			'endDate' => ['date', 'null' => TRUE, 'cast' => 'string'],
			'state' => ['enum', [\aid\Edition::OPEN, \aid\Edition::SUSPENDED, \aid\Edition::EXHAUSTED, \aid\Edition::CLOSED], 'cast' => 'enum'],
			'rate' => ['text16', 'min' => 1, 'max' => 1000, 'collate' => 'general', 'null' => TRUE, 'cast' => 'string'],
			'ceiling' => ['decimal', 'digits' => 12, 'decimal' => 2, 'min' => 0.0, 'max' => 9999999999.99, 'null' => TRUE, 'cast' => 'float'],
			'url' => ['url', 'null' => TRUE, 'cast' => 'string'],
			'checkedAt' => ['date', 'null' => TRUE, 'cast' => 'string'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'aid', 'name', 'startDate', 'endDate', 'state', 'rate', 'ceiling', 'url', 'checkedAt', 'createdAt'
		]);

		$this->propertiesToModule += [
			'aid' => 'aid\Aid',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['aid']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'state' :
				return Edition::OPEN;

			case 'createdAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'state' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): EditionModel {
		return parent::select(...$fields);
	}

	public function where(...$data): EditionModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): EditionModel {
		return $this->where('id', ...$data);
	}

	public function whereAid(...$data): EditionModel {
		return $this->where('aid', ...$data);
	}

	public function whereName(...$data): EditionModel {
		return $this->where('name', ...$data);
	}

	public function whereStartDate(...$data): EditionModel {
		return $this->where('startDate', ...$data);
	}

	public function whereEndDate(...$data): EditionModel {
		return $this->where('endDate', ...$data);
	}

	public function whereState(...$data): EditionModel {
		return $this->where('state', ...$data);
	}

	public function whereRate(...$data): EditionModel {
		return $this->where('rate', ...$data);
	}

	public function whereCeiling(...$data): EditionModel {
		return $this->where('ceiling', ...$data);
	}

	public function whereUrl(...$data): EditionModel {
		return $this->where('url', ...$data);
	}

	public function whereCheckedAt(...$data): EditionModel {
		return $this->where('checkedAt', ...$data);
	}

	public function whereCreatedAt(...$data): EditionModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class EditionCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Edition {

		$e = new Edition();

		if(empty($id)) {
			Edition::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Edition::getSelection();
		}

		if(Edition::model()
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
			$properties = Edition::getSelection();
		}

		if($sort !== NULL) {
			Edition::model()->sort($sort);
		}

		return Edition::model()
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

	public static function getNewElement(array $properties = []): Edition {

		return new Edition($properties);

	}

	public static function create(Edition $e): void {

		Edition::model()->insert($e);

	}

	public static function update(Edition $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Edition::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Edition $e, array $properties): void {

		self::$affected = Edition::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Edition $e): void {

		$e->expects(['id']);

		self::$affected = Edition::model()->delete($e);

	}

}


class EditionPage extends \ModulePage {

	protected string $module = 'aid\Edition';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? EditionLib::getPropertiesCreate(),
		   $propertiesUpdate ?? EditionLib::getPropertiesUpdate()
		);
	}

}
?>