<?php
namespace structure;

abstract class AffiliationElement extends \Element {

	use \FilterElement;

	private static ?AffiliationModel $model = NULL;

	const STAFF = 1;
	const MEMBER = 2;

	public static function getSelection(): array {
		return Affiliation::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): AffiliationModel {
		if(self::$model === NULL) {
			self::$model = new AffiliationModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Affiliation::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class AffiliationModel extends \ModuleModel {

	protected string $module = 'structure\Affiliation';
	protected string $package = 'structure';
	protected string $table = 'structureAffiliation';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'user' => ['element32', 'user\User', 'cast' => 'element'],
			'structureProducer' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'structureCommunity' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'contributesQuotation' => ['bool', 'cast' => 'bool'],
			'shareProduction' => ['set', [\structure\Affiliation::STAFF, \structure\Affiliation::MEMBER], 'cast' => 'set'],
			'shareProductionExport' => ['set', [\structure\Affiliation::STAFF, \structure\Affiliation::MEMBER], 'cast' => 'set'],
			'shareProductionDuplicate' => ['set', [\structure\Affiliation::STAFF, \structure\Affiliation::MEMBER], 'cast' => 'set'],
			'shareSellingDuplicate' => ['set', [\structure\Affiliation::STAFF, \structure\Affiliation::MEMBER], 'cast' => 'set'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'user', 'structureProducer', 'structureCommunity', 'contributesQuotation', 'shareProduction', 'shareProductionExport', 'shareProductionDuplicate', 'shareSellingDuplicate'
		]);

		$this->propertiesToModule += [
			'user' => 'user\User',
			'structureProducer' => 'structure\Structure',
			'structureCommunity' => 'structure\Structure',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['user', 'structureProducer'],
			['structureProducer'],
			['structureCommunity']
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['structureCommunity', 'user', 'structureProducer']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'contributesQuotation' :
				return TRUE;

			case 'shareProduction' :
				return new \Set(0);

			case 'shareProductionExport' :
				return new \Set(0);

			case 'shareProductionDuplicate' :
				return new \Set(0);

			case 'shareSellingDuplicate' :
				return new \Set(0);

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function select(...$fields): AffiliationModel {
		return parent::select(...$fields);
	}

	public function where(...$data): AffiliationModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): AffiliationModel {
		return $this->where('id', ...$data);
	}

	public function whereUser(...$data): AffiliationModel {
		return $this->where('user', ...$data);
	}

	public function whereStructureProducer(...$data): AffiliationModel {
		return $this->where('structureProducer', ...$data);
	}

	public function whereStructureCommunity(...$data): AffiliationModel {
		return $this->where('structureCommunity', ...$data);
	}

	public function whereContributesQuotation(...$data): AffiliationModel {
		return $this->where('contributesQuotation', ...$data);
	}

	public function whereShareProduction(...$data): AffiliationModel {
		return $this->where('shareProduction', ...$data);
	}

	public function whereShareProductionExport(...$data): AffiliationModel {
		return $this->where('shareProductionExport', ...$data);
	}

	public function whereShareProductionDuplicate(...$data): AffiliationModel {
		return $this->where('shareProductionDuplicate', ...$data);
	}

	public function whereShareSellingDuplicate(...$data): AffiliationModel {
		return $this->where('shareSellingDuplicate', ...$data);
	}


}


abstract class AffiliationCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Affiliation {

		$e = new Affiliation();

		if(empty($id)) {
			Affiliation::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Affiliation::getSelection();
		}

		if(Affiliation::model()
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
			$properties = Affiliation::getSelection();
		}

		if($sort !== NULL) {
			Affiliation::model()->sort($sort);
		}

		return Affiliation::model()
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

	public static function getNewElement(array $properties = []): Affiliation {

		return new Affiliation($properties);

	}

	public static function create(Affiliation $e): void {

		Affiliation::model()->insert($e);

	}

	public static function update(Affiliation $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Affiliation::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Affiliation $e, array $properties): void {

		self::$affected = Affiliation::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Affiliation $e): void {

		$e->expects(['id']);

		self::$affected = Affiliation::model()->delete($e);

	}

}


class AffiliationPage extends \ModulePage {

	protected string $module = 'structure\Affiliation';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? AffiliationLib::getPropertiesCreate(),
		   $propertiesUpdate ?? AffiliationLib::getPropertiesUpdate()
		);
	}

}
?>