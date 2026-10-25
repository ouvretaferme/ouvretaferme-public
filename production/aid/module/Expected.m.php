<?php
namespace aid;

abstract class ExpectedElement extends \Element {

	use \FilterElement;

	private static ?ExpectedModel $model = NULL;

	const NATIONAL = 'national';
	const FEADER = 'feader';
	const DEPARTMENT = 'department';
	const GAL = 'gal';
	const PARK = 'park';
	const EPCI = 'epci';

	public static function getSelection(): array {
		return Expected::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): ExpectedModel {
		if(self::$model === NULL) {
			self::$model = new ExpectedModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Expected::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class ExpectedModel extends \ModuleModel {

	protected string $module = 'aid\Expected';
	protected string $package = 'aid';
	protected string $table = 'aidExpected';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'code' => ['text8', 'min' => 1, 'max' => 60, 'charset' => 'ascii', 'unique' => TRUE, 'cast' => 'string'],
			'family' => ['enum', [\aid\Expected::NATIONAL, \aid\Expected::FEADER, \aid\Expected::DEPARTMENT, \aid\Expected::GAL, \aid\Expected::PARK, \aid\Expected::EPCI], 'cast' => 'enum'],
			'region' => ['text8', 'min' => 2, 'max' => 2, 'charset' => 'ascii', 'null' => TRUE, 'cast' => 'string'],
			'department' => ['text8', 'min' => 2, 'max' => 3, 'charset' => 'ascii', 'null' => TRUE, 'cast' => 'string'],
			'number' => ['text8', 'min' => 1, 'max' => 20, 'charset' => 'ascii', 'null' => TRUE, 'cast' => 'string'],
			'name' => ['text8', 'min' => 1, 'max' => 250, 'collate' => 'general', 'cast' => 'string'],
			'aid' => ['element32', 'aid\Aid', 'null' => TRUE, 'cast' => 'element'],
			'organization' => ['element32', 'aid\Organization', 'null' => TRUE, 'cast' => 'element'],
			'justification' => ['text8', 'min' => 1, 'max' => 250, 'collate' => 'general', 'null' => TRUE, 'cast' => 'string'],
			'justificationUrl' => ['url', 'null' => TRUE, 'cast' => 'string'],
			'listUrl' => ['url', 'null' => TRUE, 'cast' => 'string'],
			'createdAt' => ['datetime', 'cast' => 'string'],
			'updatedAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'code', 'family', 'region', 'department', 'number', 'name', 'aid', 'organization', 'justification', 'justificationUrl', 'listUrl', 'createdAt', 'updatedAt'
		]);

		$this->propertiesToModule += [
			'aid' => 'aid\Aid',
			'organization' => 'aid\Organization',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['aid'],
			['organization'],
			['region']
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['code']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'createdAt' :
				return new \Sql('NOW()');

			case 'updatedAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'family' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): ExpectedModel {
		return parent::select(...$fields);
	}

	public function where(...$data): ExpectedModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): ExpectedModel {
		return $this->where('id', ...$data);
	}

	public function whereCode(...$data): ExpectedModel {
		return $this->where('code', ...$data);
	}

	public function whereFamily(...$data): ExpectedModel {
		return $this->where('family', ...$data);
	}

	public function whereRegion(...$data): ExpectedModel {
		return $this->where('region', ...$data);
	}

	public function whereDepartment(...$data): ExpectedModel {
		return $this->where('department', ...$data);
	}

	public function whereNumber(...$data): ExpectedModel {
		return $this->where('number', ...$data);
	}

	public function whereName(...$data): ExpectedModel {
		return $this->where('name', ...$data);
	}

	public function whereAid(...$data): ExpectedModel {
		return $this->where('aid', ...$data);
	}

	public function whereOrganization(...$data): ExpectedModel {
		return $this->where('organization', ...$data);
	}

	public function whereJustification(...$data): ExpectedModel {
		return $this->where('justification', ...$data);
	}

	public function whereJustificationUrl(...$data): ExpectedModel {
		return $this->where('justificationUrl', ...$data);
	}

	public function whereListUrl(...$data): ExpectedModel {
		return $this->where('listUrl', ...$data);
	}

	public function whereCreatedAt(...$data): ExpectedModel {
		return $this->where('createdAt', ...$data);
	}

	public function whereUpdatedAt(...$data): ExpectedModel {
		return $this->where('updatedAt', ...$data);
	}


}


abstract class ExpectedCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Expected {

		$e = new Expected();

		if(empty($id)) {
			Expected::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Expected::getSelection();
		}

		if(Expected::model()
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
			$properties = Expected::getSelection();
		}

		if($sort !== NULL) {
			Expected::model()->sort($sort);
		}

		return Expected::model()
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

	public static function getNewElement(array $properties = []): Expected {

		return new Expected($properties);

	}

	public static function create(Expected $e): void {

		Expected::model()->insert($e);

	}

	public static function update(Expected $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Expected::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Expected $e, array $properties): void {

		self::$affected = Expected::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Expected $e): void {

		$e->expects(['id']);

		self::$affected = Expected::model()->delete($e);

	}

}


class ExpectedPage extends \ModulePage {

	protected string $module = 'aid\Expected';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? ExpectedLib::getPropertiesCreate(),
		   $propertiesUpdate ?? ExpectedLib::getPropertiesUpdate()
		);
	}

}
?>