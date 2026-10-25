<?php
namespace structure;

abstract class CommunityElement extends \Element {

	use \FilterElement;

	private static ?CommunityModel $model = NULL;

	const PRIVATE = 'private';
	const PUBLIC = 'public';

	const STAFF = 1;
	const MEMBER = 2;

	const IMMEDIATE = 'immediate';
	const FUNDING = 'funding';
	const ALWAYS = 'always';

	public static function getSelection(): array {
		return Community::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): CommunityModel {
		if(self::$model === NULL) {
			self::$model = new CommunityModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Community::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class CommunityModel extends \ModuleModel {

	protected string $module = 'structure\Community';
	protected string $package = 'structure';
	protected string $table = 'structureCommunity';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'unique' => TRUE, 'cast' => 'element'],
			'fqn' => ['fqn', 'unique' => TRUE, 'cast' => 'string'],
			'visibility' => ['enum', [\structure\Community::PRIVATE, \structure\Community::PUBLIC], 'cast' => 'enum'],
			'color' => ['color', 'null' => TRUE, 'cast' => 'string'],
			'customBackground' => ['color', 'null' => TRUE, 'cast' => 'string'],
			'customTitleFont' => ['text8', 'null' => TRUE, 'cast' => 'string'],
			'customFont' => ['text8', 'null' => TRUE, 'cast' => 'string'],
			'customDesign' => ['text8', 'charset' => 'ascii', 'null' => TRUE, 'cast' => 'string'],
			'shareProduction' => ['set', [\structure\Community::STAFF, \structure\Community::MEMBER], 'cast' => 'set'],
			'shareProductionExport' => ['set', [\structure\Community::STAFF, \structure\Community::MEMBER], 'cast' => 'set'],
			'shareProductionDuplicate' => ['set', [\structure\Community::STAFF, \structure\Community::MEMBER], 'cast' => 'set'],
			'shareSellingDuplicate' => ['set', [\structure\Community::STAFF, \structure\Community::MEMBER], 'cast' => 'set'],
			'contributesQuotation' => ['bool', 'cast' => 'bool'],
			'hasSellingDuplicate' => ['bool', 'cast' => 'bool'],
			'rules' => ['editor16', 'min' => 1, 'max' => 2048, 'null' => TRUE, 'cast' => 'string'],
			'quotationHelp' => ['editor16', 'min' => 1, 'max' => 2048, 'null' => TRUE, 'cast' => 'string'],
			'quotationResponseMinimum' => ['int8', 'min' => 1, 'max' => 50, 'cast' => 'int'],
			'quotationMinimum' => ['int8', 'min' => 5, 'max' => 50, 'cast' => 'int'],
			'quotationTrimLow' => ['int8', 'min' => 0, 'max' => 49, 'cast' => 'int'],
			'quotationTrimHigh' => ['int8', 'min' => 0, 'max' => 49, 'cast' => 'int'],
			'quotationRatioHigh' => ['decimal', 'digits' => 4, 'decimal' => 2, 'min' => 2.0, 'max' => 99.99, 'null' => TRUE, 'cast' => 'float'],
			'quotationRatioLow' => ['decimal', 'digits' => 4, 'decimal' => 2, 'min' => 2.0, 'max' => 99.99, 'null' => TRUE, 'cast' => 'float'],
			'quotationRegion' => ['text8', 'charset' => 'ascii', 'null' => TRUE, 'cast' => 'string'],
			'quotationOutside' => ['bool', 'cast' => 'bool'],
			'learningConfirm' => ['bool', 'cast' => 'bool'],
			'learningOnlineConfirm' => ['enum', [\structure\Community::IMMEDIATE, \structure\Community::FUNDING, \structure\Community::ALWAYS], 'cast' => 'enum'],
			'learningConditions' => ['editor16', 'min' => 1, 'max' => 2048, 'null' => TRUE, 'cast' => 'string'],
			'sections' => ['int16', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'fqn', 'visibility', 'color', 'customBackground', 'customTitleFont', 'customFont', 'customDesign', 'shareProduction', 'shareProductionExport', 'shareProductionDuplicate', 'shareSellingDuplicate', 'contributesQuotation', 'hasSellingDuplicate', 'rules', 'quotationHelp', 'quotationResponseMinimum', 'quotationMinimum', 'quotationTrimLow', 'quotationTrimHigh', 'quotationRatioHigh', 'quotationRatioLow', 'quotationRegion', 'quotationOutside', 'learningConfirm', 'learningOnlineConfirm', 'learningConditions', 'sections'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
		];

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['structure'],
			['fqn']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'visibility' :
				return Community::PRIVATE;

			case 'shareProduction' :
				return new \Set(0);

			case 'shareProductionExport' :
				return new \Set(0);

			case 'shareProductionDuplicate' :
				return new \Set(0);

			case 'shareSellingDuplicate' :
				return new \Set(0);

			case 'contributesQuotation' :
				return FALSE;

			case 'hasSellingDuplicate' :
				return FALSE;

			case 'quotationResponseMinimum' :
				return 1;

			case 'quotationMinimum' :
				return 5;

			case 'quotationTrimLow' :
				return 10;

			case 'quotationTrimHigh' :
				return 10;

			case 'quotationOutside' :
				return FALSE;

			case 'learningConfirm' :
				return TRUE;

			case 'learningOnlineConfirm' :
				return Community::FUNDING;

			case 'sections' :
				return 0;

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'visibility' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'learningOnlineConfirm' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): CommunityModel {
		return parent::select(...$fields);
	}

	public function where(...$data): CommunityModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): CommunityModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): CommunityModel {
		return $this->where('structure', ...$data);
	}

	public function whereFqn(...$data): CommunityModel {
		return $this->where('fqn', ...$data);
	}

	public function whereVisibility(...$data): CommunityModel {
		return $this->where('visibility', ...$data);
	}

	public function whereColor(...$data): CommunityModel {
		return $this->where('color', ...$data);
	}

	public function whereCustomBackground(...$data): CommunityModel {
		return $this->where('customBackground', ...$data);
	}

	public function whereCustomTitleFont(...$data): CommunityModel {
		return $this->where('customTitleFont', ...$data);
	}

	public function whereCustomFont(...$data): CommunityModel {
		return $this->where('customFont', ...$data);
	}

	public function whereCustomDesign(...$data): CommunityModel {
		return $this->where('customDesign', ...$data);
	}

	public function whereShareProduction(...$data): CommunityModel {
		return $this->where('shareProduction', ...$data);
	}

	public function whereShareProductionExport(...$data): CommunityModel {
		return $this->where('shareProductionExport', ...$data);
	}

	public function whereShareProductionDuplicate(...$data): CommunityModel {
		return $this->where('shareProductionDuplicate', ...$data);
	}

	public function whereShareSellingDuplicate(...$data): CommunityModel {
		return $this->where('shareSellingDuplicate', ...$data);
	}

	public function whereContributesQuotation(...$data): CommunityModel {
		return $this->where('contributesQuotation', ...$data);
	}

	public function whereHasSellingDuplicate(...$data): CommunityModel {
		return $this->where('hasSellingDuplicate', ...$data);
	}

	public function whereRules(...$data): CommunityModel {
		return $this->where('rules', ...$data);
	}

	public function whereQuotationHelp(...$data): CommunityModel {
		return $this->where('quotationHelp', ...$data);
	}

	public function whereQuotationResponseMinimum(...$data): CommunityModel {
		return $this->where('quotationResponseMinimum', ...$data);
	}

	public function whereQuotationMinimum(...$data): CommunityModel {
		return $this->where('quotationMinimum', ...$data);
	}

	public function whereQuotationTrimLow(...$data): CommunityModel {
		return $this->where('quotationTrimLow', ...$data);
	}

	public function whereQuotationTrimHigh(...$data): CommunityModel {
		return $this->where('quotationTrimHigh', ...$data);
	}

	public function whereQuotationRatioHigh(...$data): CommunityModel {
		return $this->where('quotationRatioHigh', ...$data);
	}

	public function whereQuotationRatioLow(...$data): CommunityModel {
		return $this->where('quotationRatioLow', ...$data);
	}

	public function whereQuotationRegion(...$data): CommunityModel {
		return $this->where('quotationRegion', ...$data);
	}

	public function whereQuotationOutside(...$data): CommunityModel {
		return $this->where('quotationOutside', ...$data);
	}

	public function whereLearningConfirm(...$data): CommunityModel {
		return $this->where('learningConfirm', ...$data);
	}

	public function whereLearningOnlineConfirm(...$data): CommunityModel {
		return $this->where('learningOnlineConfirm', ...$data);
	}

	public function whereLearningConditions(...$data): CommunityModel {
		return $this->where('learningConditions', ...$data);
	}

	public function whereSections(...$data): CommunityModel {
		return $this->where('sections', ...$data);
	}


}


abstract class CommunityCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Community {

		$e = new Community();

		if(empty($id)) {
			Community::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Community::getSelection();
		}

		if(Community::model()
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
			$properties = Community::getSelection();
		}

		if($sort !== NULL) {
			Community::model()->sort($sort);
		}

		return Community::model()
			->select($properties)
			->whereId('IN', $ids)
			->getCollection(NULL, NULL, $index);

	}

	public static function getByFqn(string $fqn, array $properties = []): Community {

		$e = new Community();

		if(empty($fqn)) {
			Community::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Community::getSelection();
		}

		if(Community::model()
			->select($properties)
			->whereFqn($fqn)
			->get($e) === FALSE) {
				$e->setGhost($fqn);
		}

		return $e;

	}

	public static function getByFqns(array $fqns, array $properties = []): \Collection {

		if(empty($fqns)) {
			Community::model()->reset();
			return new \Collection();
		}

		if($properties === []) {
			$properties = Community::getSelection();
		}

		return Community::model()
			->select($properties)
			->whereFqn('IN', $fqns)
			->getCollection(NULL, NULL, 'fqn');

	}

	public static function getCache(mixed $key, \Closure $callback): mixed {
		self::$cache[$key] ??= $callback();
		return self::$cache[$key];
	}

	public static function getAffected(): ?int {
		return self::$affected;
	}

	public static function getNewElement(array $properties = []): Community {

		return new Community($properties);

	}

	public static function create(Community $e): void {

		Community::model()->insert($e);

	}

	public static function update(Community $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Community::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Community $e, array $properties): void {

		self::$affected = Community::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Community $e): void {

		$e->expects(['id']);

		self::$affected = Community::model()->delete($e);

	}

}


class CommunityPage extends \ModulePage {

	protected string $module = 'structure\Community';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? CommunityLib::getPropertiesCreate(),
		   $propertiesUpdate ?? CommunityLib::getPropertiesUpdate()
		);
	}

}
?>