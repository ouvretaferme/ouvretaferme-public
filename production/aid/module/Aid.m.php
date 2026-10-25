<?php
namespace aid;

abstract class AidElement extends \Element {

	use \FilterElement;

	private static ?AidModel $model = NULL;

	const NATIONAL = 'national';
	const PAC = 'pac';
	const REGION = 'region';
	const DEPARTMENT = 'department';
	const INTERCOMMUNAL = 'intercommunal';
	const OTHER = 'other';

	const GRANT = 'grant';
	const LOAN = 'loan';
	const ADVANCE = 'advance';
	const GUARANTEE = 'guarantee';
	const TAX = 'tax';
	const SOCIAL = 'social';
	const SERVICE = 'service';
	const LAND = 'land';

	const PERMANENT = 'permanent';
	const CALL = 'call';
	const CAMPAIGN = 'campaign';
	const AUTOMATIC = 'automatic';

	const EURO = 'euro';
	const PER_HECTARE = 'per-hectare';
	const PER_UGB = 'per-ugb';
	const PER_HEAD = 'per-head';
	const PER_METER = 'per-meter';
	const PER_LITER = 'per-liter';

	const EXPENSE = 'expense';
	const AID = 'aid';

	const YEAR = 'year';

	const AB = 1;
	const AOP = 2;
	const AOC = 4;
	const IGP = 8;
	const LABEL_ROUGE = 16;
	const STG = 32;
	const HVE = 64;

	const FARM = 1;
	const YOUNG_FARMER = 2;
	const NEW_FARMER = 4;
	const CUMA = 8;
	const GROUP = 16;
	const COMMUNITY = 32;
	const ASSOCIATION = 64;
	const LANDOWNER = 128;

	const DRAFT = 'draft';
	const PUBLISHED = 'published';
	const ARCHIVED = 'archived';

	const PRIMARY = 'primary';
	const RELAY = 'relay';
	const TO_CHECK = 'to-check';
	const CONFLICT = 'conflict';

	public static function getSelection(): array {
		return Aid::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): AidModel {
		if(self::$model === NULL) {
			self::$model = new AidModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Aid::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class AidModel extends \ModuleModel {

	protected string $module = 'aid\Aid';
	protected string $package = 'aid';
	protected string $table = 'aid';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'name' => ['text8', 'min' => 1, 'max' => 250, 'collate' => 'general', 'cast' => 'string'],
			'summary' => ['text8', 'min' => 1, 'max' => 250, 'collate' => 'general', 'null' => TRUE, 'cast' => 'string'],
			'conditions' => ['editor16', 'null' => TRUE, 'cast' => 'string'],
			'organization' => ['element32', 'aid\Organization', 'cast' => 'element'],
			'level' => ['enum', [\aid\Aid::NATIONAL, \aid\Aid::PAC, \aid\Aid::REGION, \aid\Aid::DEPARTMENT, \aid\Aid::INTERCOMMUNAL, \aid\Aid::OTHER], 'cast' => 'enum'],
			'type' => ['enum', [\aid\Aid::GRANT, \aid\Aid::LOAN, \aid\Aid::ADVANCE, \aid\Aid::GUARANTEE, \aid\Aid::TAX, \aid\Aid::SOCIAL, \aid\Aid::SERVICE, \aid\Aid::LAND], 'cast' => 'enum'],
			'calendar' => ['enum', [\aid\Aid::PERMANENT, \aid\Aid::CALL, \aid\Aid::CAMPAIGN, \aid\Aid::AUTOMATIC], 'cast' => 'enum'],
			'rate' => ['text16', 'min' => 1, 'max' => 1000, 'collate' => 'general', 'null' => TRUE, 'cast' => 'string'],
			'rateUnpublished' => ['bool', 'cast' => 'bool'],
			'ratePercentMax' => ['decimal', 'digits' => 5, 'decimal' => 2, 'min' => 0.0, 'max' => 100.0, 'null' => TRUE, 'cast' => 'float'],
			'amountMax' => ['decimal', 'digits' => 12, 'decimal' => 2, 'min' => 0.0, 'max' => 9999999999.99, 'null' => TRUE, 'cast' => 'float'],
			'amountUnit' => ['enum', [\aid\Aid::EURO, \aid\Aid::PER_HECTARE, \aid\Aid::PER_UGB, \aid\Aid::PER_HEAD, \aid\Aid::PER_METER, \aid\Aid::PER_LITER], 'null' => TRUE, 'cast' => 'enum'],
			'floor' => ['decimal', 'digits' => 12, 'decimal' => 2, 'min' => 0.0, 'max' => 9999999999.99, 'null' => TRUE, 'cast' => 'float'],
			'ceiling' => ['decimal', 'digits' => 12, 'decimal' => 2, 'min' => 0.0, 'max' => 9999999999.99, 'null' => TRUE, 'cast' => 'float'],
			'boundBase' => ['enum', [\aid\Aid::EXPENSE, \aid\Aid::AID], 'null' => TRUE, 'cast' => 'enum'],
			'boundPeriod' => ['enum', [\aid\Aid::YEAR], 'null' => TRUE, 'cast' => 'enum'],
			'productions' => ['json', 'cast' => 'array'],
			'labels' => ['set', [\aid\Aid::AB, \aid\Aid::AOP, \aid\Aid::AOC, \aid\Aid::IGP, \aid\Aid::LABEL_ROUGE, \aid\Aid::STG, \aid\Aid::HVE], 'null' => TRUE, 'cast' => 'set'],
			'labelsBonus' => ['set', [\aid\Aid::AB, \aid\Aid::AOP, \aid\Aid::AOC, \aid\Aid::IGP, \aid\Aid::LABEL_ROUGE, \aid\Aid::STG, \aid\Aid::HVE], 'null' => TRUE, 'cast' => 'set'],
			'beneficiaries' => ['set', [\aid\Aid::FARM, \aid\Aid::YOUNG_FARMER, \aid\Aid::NEW_FARMER, \aid\Aid::CUMA, \aid\Aid::GROUP, \aid\Aid::COMMUNITY, \aid\Aid::ASSOCIATION, \aid\Aid::LANDOWNER], 'null' => TRUE, 'cast' => 'set'],
			'direct' => ['bool', 'cast' => 'bool'],
			'depositUrl' => ['url', 'null' => TRUE, 'cast' => 'string'],
			'commitmentRule' => ['text16', 'min' => 1, 'max' => 1000, 'null' => TRUE, 'cast' => 'string'],
			'piecesUnpublished' => ['bool', 'cast' => 'bool'],
			'parent' => ['element32', 'aid\Aid', 'null' => TRUE, 'cast' => 'element'],
			'keywords' => ['text8', 'min' => 1, 'max' => 250, 'collate' => 'general', 'null' => TRUE, 'cast' => 'string'],
			'status' => ['enum', [\aid\Aid::DRAFT, \aid\Aid::PUBLISHED, \aid\Aid::ARCHIVED], 'cast' => 'enum'],
			'verification' => ['enum', [\aid\Aid::PRIMARY, \aid\Aid::RELAY, \aid\Aid::TO_CHECK, \aid\Aid::CONFLICT], 'cast' => 'enum'],
			'checkedAt' => ['date', 'null' => TRUE, 'cast' => 'string'],
			'checkedBy' => ['element32', 'user\User', 'null' => TRUE, 'cast' => 'element'],
			'piecesCheckedAt' => ['date', 'null' => TRUE, 'cast' => 'string'],
			'publishedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'notes' => ['text16', 'min' => 1, 'max' => NULL, 'null' => TRUE, 'cast' => 'string'],
			'seedDifferences' => ['json', 'null' => TRUE, 'cast' => 'array'],
			'seedKept' => ['text8', 'min' => 64, 'max' => 64, 'charset' => 'ascii', 'null' => TRUE, 'cast' => 'string'],
			'aliases' => ['json', 'cast' => 'array'],
			'createdAt' => ['datetime', 'cast' => 'string'],
			'updatedAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'name', 'summary', 'conditions', 'organization', 'level', 'type', 'calendar', 'rate', 'rateUnpublished', 'ratePercentMax', 'amountMax', 'amountUnit', 'floor', 'ceiling', 'boundBase', 'boundPeriod', 'productions', 'labels', 'labelsBonus', 'beneficiaries', 'direct', 'depositUrl', 'commitmentRule', 'piecesUnpublished', 'parent', 'keywords', 'status', 'verification', 'checkedAt', 'checkedBy', 'piecesCheckedAt', 'publishedAt', 'notes', 'seedDifferences', 'seedKept', 'aliases', 'createdAt', 'updatedAt'
		]);

		$this->propertiesToModule += [
			'organization' => 'aid\Organization',
			'parent' => 'aid\Aid',
			'checkedBy' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['organization'],
			['parent']
		]);

		$this->searchConstraints = array_merge($this->searchConstraints, [
			['name', 'summary', 'keywords']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'rateUnpublished' :
				return FALSE;

			case 'productions' :
				return [];

			case 'direct' :
				return TRUE;

			case 'piecesUnpublished' :
				return FALSE;

			case 'status' :
				return Aid::DRAFT;

			case 'verification' :
				return Aid::TO_CHECK;

			case 'aliases' :
				return [];

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

			case 'level' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'type' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'calendar' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'amountUnit' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'boundBase' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'boundPeriod' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'productions' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			case 'status' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'verification' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'seedDifferences' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			case 'aliases' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'productions' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			case 'seedDifferences' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			case 'aliases' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			default :
				return parent::decode($property, $value);

		}

	}

	public function select(...$fields): AidModel {
		return parent::select(...$fields);
	}

	public function where(...$data): AidModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): AidModel {
		return $this->where('id', ...$data);
	}

	public function whereName(...$data): AidModel {
		return $this->where('name', ...$data);
	}

	public function whereSummary(...$data): AidModel {
		return $this->where('summary', ...$data);
	}

	public function whereConditions(...$data): AidModel {
		return $this->where('conditions', ...$data);
	}

	public function whereOrganization(...$data): AidModel {
		return $this->where('organization', ...$data);
	}

	public function whereLevel(...$data): AidModel {
		return $this->where('level', ...$data);
	}

	public function whereType(...$data): AidModel {
		return $this->where('type', ...$data);
	}

	public function whereCalendar(...$data): AidModel {
		return $this->where('calendar', ...$data);
	}

	public function whereRate(...$data): AidModel {
		return $this->where('rate', ...$data);
	}

	public function whereRateUnpublished(...$data): AidModel {
		return $this->where('rateUnpublished', ...$data);
	}

	public function whereRatePercentMax(...$data): AidModel {
		return $this->where('ratePercentMax', ...$data);
	}

	public function whereAmountMax(...$data): AidModel {
		return $this->where('amountMax', ...$data);
	}

	public function whereAmountUnit(...$data): AidModel {
		return $this->where('amountUnit', ...$data);
	}

	public function whereFloor(...$data): AidModel {
		return $this->where('floor', ...$data);
	}

	public function whereCeiling(...$data): AidModel {
		return $this->where('ceiling', ...$data);
	}

	public function whereBoundBase(...$data): AidModel {
		return $this->where('boundBase', ...$data);
	}

	public function whereBoundPeriod(...$data): AidModel {
		return $this->where('boundPeriod', ...$data);
	}

	public function whereProductions(...$data): AidModel {
		return $this->where('productions', ...$data);
	}

	public function whereLabels(...$data): AidModel {
		return $this->where('labels', ...$data);
	}

	public function whereLabelsBonus(...$data): AidModel {
		return $this->where('labelsBonus', ...$data);
	}

	public function whereBeneficiaries(...$data): AidModel {
		return $this->where('beneficiaries', ...$data);
	}

	public function whereDirect(...$data): AidModel {
		return $this->where('direct', ...$data);
	}

	public function whereDepositUrl(...$data): AidModel {
		return $this->where('depositUrl', ...$data);
	}

	public function whereCommitmentRule(...$data): AidModel {
		return $this->where('commitmentRule', ...$data);
	}

	public function wherePiecesUnpublished(...$data): AidModel {
		return $this->where('piecesUnpublished', ...$data);
	}

	public function whereParent(...$data): AidModel {
		return $this->where('parent', ...$data);
	}

	public function whereKeywords(...$data): AidModel {
		return $this->where('keywords', ...$data);
	}

	public function whereStatus(...$data): AidModel {
		return $this->where('status', ...$data);
	}

	public function whereVerification(...$data): AidModel {
		return $this->where('verification', ...$data);
	}

	public function whereCheckedAt(...$data): AidModel {
		return $this->where('checkedAt', ...$data);
	}

	public function whereCheckedBy(...$data): AidModel {
		return $this->where('checkedBy', ...$data);
	}

	public function wherePiecesCheckedAt(...$data): AidModel {
		return $this->where('piecesCheckedAt', ...$data);
	}

	public function wherePublishedAt(...$data): AidModel {
		return $this->where('publishedAt', ...$data);
	}

	public function whereNotes(...$data): AidModel {
		return $this->where('notes', ...$data);
	}

	public function whereSeedDifferences(...$data): AidModel {
		return $this->where('seedDifferences', ...$data);
	}

	public function whereSeedKept(...$data): AidModel {
		return $this->where('seedKept', ...$data);
	}

	public function whereAliases(...$data): AidModel {
		return $this->where('aliases', ...$data);
	}

	public function whereCreatedAt(...$data): AidModel {
		return $this->where('createdAt', ...$data);
	}

	public function whereUpdatedAt(...$data): AidModel {
		return $this->where('updatedAt', ...$data);
	}


}


abstract class AidCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Aid {

		$e = new Aid();

		if(empty($id)) {
			Aid::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Aid::getSelection();
		}

		if(Aid::model()
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
			$properties = Aid::getSelection();
		}

		if($sort !== NULL) {
			Aid::model()->sort($sort);
		}

		return Aid::model()
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

	public static function getNewElement(array $properties = []): Aid {

		return new Aid($properties);

	}

	public static function create(Aid $e): void {

		Aid::model()->insert($e);

	}

	public static function update(Aid $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Aid::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Aid $e, array $properties): void {

		self::$affected = Aid::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Aid $e): void {

		$e->expects(['id']);

		self::$affected = Aid::model()->delete($e);

	}

}


class AidPage extends \ModulePage {

	protected string $module = 'aid\Aid';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? AidLib::getPropertiesCreate(),
		   $propertiesUpdate ?? AidLib::getPropertiesUpdate()
		);
	}

}
?>