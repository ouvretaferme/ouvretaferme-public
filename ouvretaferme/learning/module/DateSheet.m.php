<?php
namespace learning;

abstract class DateSheetElement extends \Element {

	use \FilterElement;

	private static ?DateSheetModel $model = NULL;

	public static function getSelection(): array {
		return DateSheet::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): DateSheetModel {
		if(self::$model === NULL) {
			self::$model = new DateSheetModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('DateSheet::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class DateSheetModel extends \ModuleModel {

	protected string $module = 'learning\DateSheet';
	protected string $package = 'learning';
	protected string $table = 'learningDateSheet';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'date' => ['element32', 'learning\Date', 'cast' => 'element'],
			'course' => ['element32', 'learning\Course', 'cast' => 'element'],
			'name' => ['text8', 'min' => 1, 'max' => 100, 'cast' => 'string'],
			'hash' => ['textFixed', 'min' => 20, 'max' => 20, 'charset' => 'ascii', 'cast' => 'string'],
			'createdAt' => ['datetime', 'cast' => 'string'],
			'createdBy' => ['element32', 'user\User', 'cast' => 'element'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'date', 'course', 'name', 'hash', 'createdAt', 'createdBy'
		]);

		$this->propertiesToModule += [
			'date' => 'learning\Date',
			'course' => 'learning\Course',
			'createdBy' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['date'],
			['course']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'createdAt' :
				return new \Sql('NOW()');

			case 'createdBy' :
				return \user\ConnectionLib::getOnline();

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function select(...$fields): DateSheetModel {
		return parent::select(...$fields);
	}

	public function where(...$data): DateSheetModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): DateSheetModel {
		return $this->where('id', ...$data);
	}

	public function whereDate(...$data): DateSheetModel {
		return $this->where('date', ...$data);
	}

	public function whereCourse(...$data): DateSheetModel {
		return $this->where('course', ...$data);
	}

	public function whereName(...$data): DateSheetModel {
		return $this->where('name', ...$data);
	}

	public function whereHash(...$data): DateSheetModel {
		return $this->where('hash', ...$data);
	}

	public function whereCreatedAt(...$data): DateSheetModel {
		return $this->where('createdAt', ...$data);
	}

	public function whereCreatedBy(...$data): DateSheetModel {
		return $this->where('createdBy', ...$data);
	}


}


abstract class DateSheetCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): DateSheet {

		$e = new DateSheet();

		if(empty($id)) {
			DateSheet::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = DateSheet::getSelection();
		}

		if(DateSheet::model()
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
			$properties = DateSheet::getSelection();
		}

		if($sort !== NULL) {
			DateSheet::model()->sort($sort);
		}

		return DateSheet::model()
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

	public static function getNewElement(array $properties = []): DateSheet {

		return new DateSheet($properties);

	}

	public static function create(DateSheet $e): void {

		DateSheet::model()->insert($e);

	}

	public static function update(DateSheet $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = DateSheet::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, DateSheet $e, array $properties): void {

		self::$affected = DateSheet::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(DateSheet $e): void {

		$e->expects(['id']);

		self::$affected = DateSheet::model()->delete($e);

	}

}


class DateSheetPage extends \ModulePage {

	protected string $module = 'learning\DateSheet';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? DateSheetLib::getPropertiesCreate(),
		   $propertiesUpdate ?? DateSheetLib::getPropertiesUpdate()
		);
	}

}
?>