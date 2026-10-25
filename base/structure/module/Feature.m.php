<?php
namespace structure;

abstract class FeatureElement extends \Element {

	use \FilterElement;

	private static ?FeatureModel $model = NULL;

	public static function getSelection(): array {
		return Feature::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): FeatureModel {
		if(self::$model === NULL) {
			self::$model = new FeatureModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Feature::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class FeatureModel extends \ModuleModel {

	protected string $module = 'structure\Feature';
	protected string $package = 'structure';
	protected string $table = 'structureFeature';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'unique' => TRUE, 'cast' => 'element'],
			'production' => ['bool', 'cast' => 'bool'],
			'hasHr' => ['bool', 'cast' => 'bool'],
			'hasShops' => ['bool', 'cast' => 'bool'],
			'hasSales' => ['bool', 'cast' => 'bool'],
			'hasMarket' => ['bool', 'cast' => 'bool'],
			'hasInvoices' => ['bool', 'cast' => 'bool'],
			'hasPurchases' => ['bool', 'cast' => 'bool'],
			'hasCultivations' => ['bool', 'cast' => 'bool'],
			'hasAccounting' => ['bool', 'cast' => 'bool'],
			'hasFinancialYears' => ['bool', 'cast' => 'bool'],
			'hasPdp' => ['bool', 'cast' => 'bool'],
			'hasEnableBanking' => ['bool', 'cast' => 'bool'],
			'hasWallet' => ['bool', 'cast' => 'bool'],
			'hasStock' => ['bool', 'cast' => 'bool'],
			'hasQuotation' => ['bool', 'cast' => 'bool'],
			'hasCommunityVegetable' => ['bool', 'cast' => 'bool'],
			'hasCommunitySections' => ['bool', 'cast' => 'bool'],
			'hasCommunitySchools' => ['bool', 'cast' => 'bool'],
			'hasCommunityLearning' => ['bool', 'cast' => 'bool'],
			'hasStart' => ['bool', 'cast' => 'bool'],
			'hasAmap' => ['bool', 'cast' => 'bool'],
			'hasToolbox' => ['json', 'cast' => 'array'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'production', 'hasHr', 'hasShops', 'hasSales', 'hasMarket', 'hasInvoices', 'hasPurchases', 'hasCultivations', 'hasAccounting', 'hasFinancialYears', 'hasPdp', 'hasEnableBanking', 'hasWallet', 'hasStock', 'hasQuotation', 'hasCommunityVegetable', 'hasCommunitySections', 'hasCommunitySchools', 'hasCommunityLearning', 'hasStart', 'hasAmap', 'hasToolbox'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
		];

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['structure']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'production' :
				return TRUE;

			case 'hasHr' :
				return FALSE;

			case 'hasShops' :
				return FALSE;

			case 'hasSales' :
				return FALSE;

			case 'hasMarket' :
				return FALSE;

			case 'hasInvoices' :
				return FALSE;

			case 'hasPurchases' :
				return FALSE;

			case 'hasCultivations' :
				return FALSE;

			case 'hasAccounting' :
				return FALSE;

			case 'hasFinancialYears' :
				return FALSE;

			case 'hasPdp' :
				return FALSE;

			case 'hasEnableBanking' :
				return FALSE;

			case 'hasWallet' :
				return FALSE;

			case 'hasStock' :
				return FALSE;

			case 'hasQuotation' :
				return FALSE;

			case 'hasCommunityVegetable' :
				return FALSE;

			case 'hasCommunitySections' :
				return FALSE;

			case 'hasCommunitySchools' :
				return FALSE;

			case 'hasCommunityLearning' :
				return FALSE;

			case 'hasStart' :
				return FALSE;

			case 'hasAmap' :
				return FALSE;

			case 'hasToolbox' :
				return [];

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'hasToolbox' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'hasToolbox' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			default :
				return parent::decode($property, $value);

		}

	}

	public function select(...$fields): FeatureModel {
		return parent::select(...$fields);
	}

	public function where(...$data): FeatureModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): FeatureModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): FeatureModel {
		return $this->where('structure', ...$data);
	}

	public function whereProduction(...$data): FeatureModel {
		return $this->where('production', ...$data);
	}

	public function whereHasHr(...$data): FeatureModel {
		return $this->where('hasHr', ...$data);
	}

	public function whereHasShops(...$data): FeatureModel {
		return $this->where('hasShops', ...$data);
	}

	public function whereHasSales(...$data): FeatureModel {
		return $this->where('hasSales', ...$data);
	}

	public function whereHasMarket(...$data): FeatureModel {
		return $this->where('hasMarket', ...$data);
	}

	public function whereHasInvoices(...$data): FeatureModel {
		return $this->where('hasInvoices', ...$data);
	}

	public function whereHasPurchases(...$data): FeatureModel {
		return $this->where('hasPurchases', ...$data);
	}

	public function whereHasCultivations(...$data): FeatureModel {
		return $this->where('hasCultivations', ...$data);
	}

	public function whereHasAccounting(...$data): FeatureModel {
		return $this->where('hasAccounting', ...$data);
	}

	public function whereHasFinancialYears(...$data): FeatureModel {
		return $this->where('hasFinancialYears', ...$data);
	}

	public function whereHasPdp(...$data): FeatureModel {
		return $this->where('hasPdp', ...$data);
	}

	public function whereHasEnableBanking(...$data): FeatureModel {
		return $this->where('hasEnableBanking', ...$data);
	}

	public function whereHasWallet(...$data): FeatureModel {
		return $this->where('hasWallet', ...$data);
	}

	public function whereHasStock(...$data): FeatureModel {
		return $this->where('hasStock', ...$data);
	}

	public function whereHasQuotation(...$data): FeatureModel {
		return $this->where('hasQuotation', ...$data);
	}

	public function whereHasCommunityVegetable(...$data): FeatureModel {
		return $this->where('hasCommunityVegetable', ...$data);
	}

	public function whereHasCommunitySections(...$data): FeatureModel {
		return $this->where('hasCommunitySections', ...$data);
	}

	public function whereHasCommunitySchools(...$data): FeatureModel {
		return $this->where('hasCommunitySchools', ...$data);
	}

	public function whereHasCommunityLearning(...$data): FeatureModel {
		return $this->where('hasCommunityLearning', ...$data);
	}

	public function whereHasStart(...$data): FeatureModel {
		return $this->where('hasStart', ...$data);
	}

	public function whereHasAmap(...$data): FeatureModel {
		return $this->where('hasAmap', ...$data);
	}

	public function whereHasToolbox(...$data): FeatureModel {
		return $this->where('hasToolbox', ...$data);
	}


}


abstract class FeatureCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Feature {

		$e = new Feature();

		if(empty($id)) {
			Feature::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Feature::getSelection();
		}

		if(Feature::model()
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
			$properties = Feature::getSelection();
		}

		if($sort !== NULL) {
			Feature::model()->sort($sort);
		}

		return Feature::model()
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

	public static function getNewElement(array $properties = []): Feature {

		return new Feature($properties);

	}

	public static function create(Feature $e): void {

		Feature::model()->insert($e);

	}

	public static function update(Feature $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Feature::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Feature $e, array $properties): void {

		self::$affected = Feature::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Feature $e): void {

		$e->expects(['id']);

		self::$affected = Feature::model()->delete($e);

	}

}


class FeaturePage extends \ModulePage {

	protected string $module = 'structure\Feature';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? FeatureLib::getPropertiesCreate(),
		   $propertiesUpdate ?? FeatureLib::getPropertiesUpdate()
		);
	}

}
?>