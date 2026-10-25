<?php
namespace aid;

abstract class AidOrganizationElement extends \Element {

	use \FilterElement;

	private static ?AidOrganizationModel $model = NULL;

	const FUNDER = 'funder';
	const COFUNDER = 'cofunder';
	const INSTRUCTOR = 'instructor';
	const PAYER = 'payer';
	const DESK = 'desk';
	const RELAY = 'relay';

	public static function getSelection(): array {
		return AidOrganization::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): AidOrganizationModel {
		if(self::$model === NULL) {
			self::$model = new AidOrganizationModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('AidOrganization::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class AidOrganizationModel extends \ModuleModel {

	protected string $module = 'aid\AidOrganization';
	protected string $package = 'aid';
	protected string $table = 'aidAidOrganization';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'aid' => ['element32', 'aid\Aid', 'cast' => 'element'],
			'organization' => ['element32', 'aid\Organization', 'cast' => 'element'],
			'role' => ['enum', [\aid\AidOrganization::FUNDER, \aid\AidOrganization::COFUNDER, \aid\AidOrganization::INSTRUCTOR, \aid\AidOrganization::PAYER, \aid\AidOrganization::DESK, \aid\AidOrganization::RELAY], 'cast' => 'enum'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'aid', 'organization', 'role'
		]);

		$this->propertiesToModule += [
			'aid' => 'aid\Aid',
			'organization' => 'aid\Organization',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['organization']
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['aid', 'organization', 'role']
		]);

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'role' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): AidOrganizationModel {
		return parent::select(...$fields);
	}

	public function where(...$data): AidOrganizationModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): AidOrganizationModel {
		return $this->where('id', ...$data);
	}

	public function whereAid(...$data): AidOrganizationModel {
		return $this->where('aid', ...$data);
	}

	public function whereOrganization(...$data): AidOrganizationModel {
		return $this->where('organization', ...$data);
	}

	public function whereRole(...$data): AidOrganizationModel {
		return $this->where('role', ...$data);
	}


}


abstract class AidOrganizationCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): AidOrganization {

		$e = new AidOrganization();

		if(empty($id)) {
			AidOrganization::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = AidOrganization::getSelection();
		}

		if(AidOrganization::model()
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
			$properties = AidOrganization::getSelection();
		}

		if($sort !== NULL) {
			AidOrganization::model()->sort($sort);
		}

		return AidOrganization::model()
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

	public static function getNewElement(array $properties = []): AidOrganization {

		return new AidOrganization($properties);

	}

	public static function create(AidOrganization $e): void {

		AidOrganization::model()->insert($e);

	}

	public static function update(AidOrganization $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = AidOrganization::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, AidOrganization $e, array $properties): void {

		self::$affected = AidOrganization::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(AidOrganization $e): void {

		$e->expects(['id']);

		self::$affected = AidOrganization::model()->delete($e);

	}

}


class AidOrganizationPage extends \ModulePage {

	protected string $module = 'aid\AidOrganization';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? AidOrganizationLib::getPropertiesCreate(),
		   $propertiesUpdate ?? AidOrganizationLib::getPropertiesUpdate()
		);
	}

}
?>