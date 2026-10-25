<?php
namespace aid;

abstract class OrganizationElement extends \Element {

	use \FilterElement;

	private static ?OrganizationModel $model = NULL;

	const STATE = 'state';
	const OPERATOR = 'operator';
	const REGION = 'region';
	const DEPARTMENT = 'department';
	const EPCI = 'epci';
	const CHAMBER = 'chamber';
	const WATER_AGENCY = 'water-agency';
	const PARK = 'park';
	const GAL = 'gal';
	const ASSOCIATION = 'association';
	const EUROPE = 'europe';
	const OTHER = 'other';

	const NATIONAL = 'national';
	const INTERCOMMUNAL = 'intercommunal';
	const LOCAL = 'local';

	public static function getSelection(): array {
		return Organization::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): OrganizationModel {
		if(self::$model === NULL) {
			self::$model = new OrganizationModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Organization::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class OrganizationModel extends \ModuleModel {

	protected string $module = 'aid\Organization';
	protected string $package = 'aid';
	protected string $table = 'aidOrganization';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'name' => ['text8', 'min' => 1, 'max' => 250, 'collate' => 'general', 'unique' => TRUE, 'cast' => 'string'],
			'type' => ['enum', [\aid\Organization::STATE, \aid\Organization::OPERATOR, \aid\Organization::REGION, \aid\Organization::DEPARTMENT, \aid\Organization::EPCI, \aid\Organization::CHAMBER, \aid\Organization::WATER_AGENCY, \aid\Organization::PARK, \aid\Organization::GAL, \aid\Organization::ASSOCIATION, \aid\Organization::EUROPE, \aid\Organization::OTHER], 'cast' => 'enum'],
			'level' => ['enum', [\aid\Organization::EUROPE, \aid\Organization::NATIONAL, \aid\Organization::REGION, \aid\Organization::DEPARTMENT, \aid\Organization::INTERCOMMUNAL, \aid\Organization::LOCAL], 'cast' => 'enum'],
			'url' => ['url', 'null' => TRUE, 'cast' => 'string'],
			'grants' => ['bool', 'cast' => 'bool'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'name', 'type', 'level', 'url', 'grants', 'createdAt'
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['name']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'grants' :
				return TRUE;

			case 'createdAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'type' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'level' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): OrganizationModel {
		return parent::select(...$fields);
	}

	public function where(...$data): OrganizationModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): OrganizationModel {
		return $this->where('id', ...$data);
	}

	public function whereName(...$data): OrganizationModel {
		return $this->where('name', ...$data);
	}

	public function whereType(...$data): OrganizationModel {
		return $this->where('type', ...$data);
	}

	public function whereLevel(...$data): OrganizationModel {
		return $this->where('level', ...$data);
	}

	public function whereUrl(...$data): OrganizationModel {
		return $this->where('url', ...$data);
	}

	public function whereGrants(...$data): OrganizationModel {
		return $this->where('grants', ...$data);
	}

	public function whereCreatedAt(...$data): OrganizationModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class OrganizationCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Organization {

		$e = new Organization();

		if(empty($id)) {
			Organization::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Organization::getSelection();
		}

		if(Organization::model()
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
			$properties = Organization::getSelection();
		}

		if($sort !== NULL) {
			Organization::model()->sort($sort);
		}

		return Organization::model()
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

	public static function getNewElement(array $properties = []): Organization {

		return new Organization($properties);

	}

	public static function create(Organization $e): void {

		Organization::model()->insert($e);

	}

	public static function update(Organization $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Organization::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Organization $e, array $properties): void {

		self::$affected = Organization::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Organization $e): void {

		$e->expects(['id']);

		self::$affected = Organization::model()->delete($e);

	}

}


class OrganizationPage extends \ModulePage {

	protected string $module = 'aid\Organization';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? OrganizationLib::getPropertiesCreate(),
		   $propertiesUpdate ?? OrganizationLib::getPropertiesUpdate()
		);
	}

}
?>