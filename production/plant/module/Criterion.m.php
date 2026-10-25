<?php
namespace plant;

abstract class CriterionElement extends \Element {

	use \FilterElement;

	private static ?CriterionModel $model = NULL;

	const PRODUCTION = 'production';
	const RESISTANCE = 'resistance';
	const WORK = 'work';
	const QUALITY = 'quality';

	const ACTIVE = 'active';
	const INACTIVE = 'inactive';

	public static function getSelection(): array {
		return Criterion::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): CriterionModel {
		if(self::$model === NULL) {
			self::$model = new CriterionModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Criterion::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class CriterionModel extends \ModuleModel {

	protected string $module = 'plant\Criterion';
	protected string $package = 'plant';
	protected string $table = 'plantCriterion';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'name' => ['text8', 'min' => 1, 'max' => 50, 'collate' => 'general', 'unique' => TRUE, 'cast' => 'string'],
			'type' => ['enum', [\plant\Criterion::PRODUCTION, \plant\Criterion::RESISTANCE, \plant\Criterion::WORK, \plant\Criterion::QUALITY], 'cast' => 'enum'],
			'status' => ['enum', [\plant\Criterion::ACTIVE, \plant\Criterion::INACTIVE], 'cast' => 'enum'],
			'createdAt' => ['date', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'name', 'type', 'status', 'createdAt'
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['name']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'status' :
				return Criterion::ACTIVE;

			case 'createdAt' :
				return new \Sql('CURDATE()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'type' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'status' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): CriterionModel {
		return parent::select(...$fields);
	}

	public function where(...$data): CriterionModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): CriterionModel {
		return $this->where('id', ...$data);
	}

	public function whereName(...$data): CriterionModel {
		return $this->where('name', ...$data);
	}

	public function whereType(...$data): CriterionModel {
		return $this->where('type', ...$data);
	}

	public function whereStatus(...$data): CriterionModel {
		return $this->where('status', ...$data);
	}

	public function whereCreatedAt(...$data): CriterionModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class CriterionCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Criterion {

		$e = new Criterion();

		if(empty($id)) {
			Criterion::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Criterion::getSelection();
		}

		if(Criterion::model()
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
			$properties = Criterion::getSelection();
		}

		if($sort !== NULL) {
			Criterion::model()->sort($sort);
		}

		return Criterion::model()
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

	public static function getNewElement(array $properties = []): Criterion {

		return new Criterion($properties);

	}

	public static function create(Criterion $e): void {

		Criterion::model()->insert($e);

	}

	public static function update(Criterion $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Criterion::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Criterion $e, array $properties): void {

		self::$affected = Criterion::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Criterion $e): void {

		$e->expects(['id']);

		self::$affected = Criterion::model()->delete($e);

	}

}


class CriterionPage extends \ModulePage {

	protected string $module = 'plant\Criterion';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? CriterionLib::getPropertiesCreate(),
		   $propertiesUpdate ?? CriterionLib::getPropertiesUpdate()
		);
	}

}
?>