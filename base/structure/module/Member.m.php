<?php
namespace structure;

abstract class MemberElement extends \Element {

	use \FilterElement;

	private static ?MemberModel $model = NULL;

	const ACTIVE = 'active';
	const CLOSED = 'closed';

	const IN = 'in';
	const OUT = 'out';

	const DAILY = 'daily';
	const WEEKLY = 'weekly';
	const YEARLY = 'yearly';

	const CARDS = 'cards';
	const GRID = 'grid';
	const COLUMNS = 'columns';

	const TIME = 'time';
	const TEAM = 'team';
	const PACE = 'pace';
	const PERIOD = 'period';

	const TOTAL = 'total';

	const VARIETY = 'variety';
	const SOIL = 'soil';
	const SERIES = 'series';

	const AREA = 'area';
	const LENGTH = 'length';

	const DENSE = 'dense';
	const NORMAL = 'normal';
	const SPACIOUS = 'spacious';

	const PLANT = 'plant';
	const FAMILY = 'family';

	const HARVESTING = 'harvesting';
	const WORKING_TIME = 'working-time';

	const PLAN = 'plan';
	const MAP = 'map';

	const SATELLITE = 'satellite';
	const DRAWING = 'drawing';

	const ROTATION = 'rotation';
	const GARDEN = 'garden';

	const WHITE = 'white';

	const SALE = 'sale';
	const SALE_PRIVATE = 'sale-private';
	const SALE_PRO = 'sale-pro';
	const LABEL = 'label';
	const PURCHASE = 'purchase';
	const ARCHIVES = 'archives';

	const PRODUCT = 'product';
	const CATEGORY = 'category';

	const TURNOVER = 'turnover';
	const ACCOUNT = 'account';

	const CUSTOMER = 'customer';
	const CUSTOMER_PRIVATE = 'customer-private';
	const CUSTOMER_PRO = 'customer-pro';
	const WALLET = 'wallet';
	const GROUP = 'group';

	const PRIVATE = 'private';
	const PRO = 'pro';

	const ITEM = 'item';
	const SHOP = 'shop';

	const CAMPAIGN = 'campaign';
	const CONTACT = 'contact';

	const POINT = 'point';

	const ME = 'me';
	const ALL = 'all';

	const COUNTRY = 'country';
	const REGION = 'region';

	const QUANTITY = 'quantity';

	const COMPOSITION = 'composition';
	const INGREDIENT = 'ingredient';

	const ROLE = 'role';

	const ICON = 'icon';
	const METEOFRANCE = 'meteofrance';
	const UKMO = 'ukmo';
	const ECMWF = 'ecmwf';

	public static function getSelection(): array {
		return Member::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): MemberModel {
		if(self::$model === NULL) {
			self::$model = new MemberModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Member::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class MemberModel extends \ModuleModel {

	protected string $module = 'structure\Member';
	protected string $package = 'structure';
	protected string $table = 'structureMember';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'user' => ['element32', 'user\User', 'cast' => 'element'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'structureStatus' => ['enum', [\structure\Member::ACTIVE, \structure\Member::CLOSED], 'cast' => 'enum'],
			'status' => ['enum', [\structure\Member::IN, \structure\Member::OUT], 'cast' => 'enum'],
			'inviteKey' => ['text8', 'null' => TRUE, 'cast' => 'string'],
			'role' => ['element32', 'structure\Role', 'null' => TRUE, 'cast' => 'element'],
			'visitedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'viewPlanning' => ['enum', [\structure\Member::DAILY, \structure\Member::WEEKLY, \structure\Member::YEARLY], 'cast' => 'enum'],
			'viewPlanningWeek' => ['enum', [\structure\Member::CARDS, \structure\Member::GRID, \structure\Member::COLUMNS], 'cast' => 'enum'],
			'viewPlanningAction' => ['element32', 'work\Action', 'null' => TRUE, 'cast' => 'element'],
			'viewPlanningPlant' => ['element32', 'plant\Plant', 'null' => TRUE, 'cast' => 'element'],
			'viewPlanningUser' => ['element32', 'user\User', 'null' => TRUE, 'cast' => 'element'],
			'viewPlanningCategory' => ['enum', [\structure\Member::TIME, \structure\Member::TEAM, \structure\Member::PACE, \structure\Member::PERIOD], 'cast' => 'enum'],
			'viewPlanningYear' => ['int16', 'min' => 0, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'viewPlanningHarvestExpected' => ['enum', [\structure\Member::TOTAL, \structure\Member::WEEKLY], 'cast' => 'enum'],
			'viewPlanningHarvestQuick' => ['bool', 'cast' => 'bool'],
			'viewPlanningField' => ['enum', [\structure\Member::VARIETY, \structure\Member::SOIL, \structure\Member::SERIES], 'cast' => 'enum'],
			'viewPlanningArea' => ['enum', [\structure\Member::AREA, \structure\Member::LENGTH, \structure\Member::TOTAL, \structure\Member::WEEKLY], 'cast' => 'enum'],
			'viewPlanningUserDaily' => ['element32', 'user\User', 'null' => TRUE, 'cast' => 'element'],
			'viewPlanningHideDone' => ['bool', 'cast' => 'bool'],
			'viewPlanningMine' => ['bool', 'cast' => 'bool'],
			'viewPlanningOrder' => ['json', 'null' => TRUE, 'cast' => 'array'],
			'viewPlanningDensity' => ['enum', [\structure\Member::DENSE, \structure\Member::NORMAL, \structure\Member::SPACIOUS], 'cast' => 'enum'],
			'viewPlanningColor' => ['bool', 'cast' => 'bool'],
			'viewPlanningSales' => ['bool', 'cast' => 'bool'],
			'viewPlanningSalesDays' => ['int8', 'min' => 0, 'max' => 14, 'cast' => 'int'],
			'viewPlanningReminders' => ['bool', 'cast' => 'bool'],
			'viewCultivationCategory' => ['enum', [\structure\Member::AREA, \structure\Member::PLANT, \structure\Member::FAMILY], 'cast' => 'enum'],
			'viewSeries' => ['enum', [\structure\Member::AREA, \structure\Member::HARVESTING, \structure\Member::WORKING_TIME], 'cast' => 'enum'],
			'viewMap' => ['enum', [\structure\Member::PLAN, \structure\Member::MAP], 'cast' => 'enum'],
			'viewMapBasemap' => ['enum', [\structure\Member::SATELLITE, \structure\Member::DRAWING], 'cast' => 'enum'],
			'viewSoil' => ['enum', [\structure\Member::PLAN, \structure\Member::ROTATION, \structure\Member::GARDEN], 'cast' => 'enum'],
			'viewSoilColor' => ['enum', [\structure\Member::WHITE, \structure\Member::PLANT, \structure\Member::FAMILY], 'cast' => 'enum'],
			'viewSoilOverlay' => ['bool', 'cast' => 'bool'],
			'viewSoilTasks' => ['bool', 'cast' => 'bool'],
			'viewSellingSales' => ['enum', [\structure\Member::SALE, \structure\Member::SALE_PRIVATE, \structure\Member::SALE_PRO, \structure\Member::LABEL, \structure\Member::PURCHASE, \structure\Member::ARCHIVES], 'cast' => 'enum'],
			'viewSellingProducts' => ['enum', [\structure\Member::PRODUCT, \structure\Member::CATEGORY], 'cast' => 'enum'],
			'viewSellingProductsColumn' => ['enum', [\structure\Member::TURNOVER, \structure\Member::ACCOUNT], 'cast' => 'enum'],
			'viewSellingCustomers' => ['enum', [\structure\Member::CUSTOMER, \structure\Member::CUSTOMER_PRIVATE, \structure\Member::CUSTOMER_PRO, \structure\Member::WALLET, \structure\Member::GROUP], 'cast' => 'enum'],
			'viewSellingType' => ['enum', [\structure\Member::PRIVATE, \structure\Member::PRO], 'null' => TRUE, 'cast' => 'enum'],
			'viewSellingTransaction' => ['enum', [\structure\Member::SALE, \structure\Member::PURCHASE], 'null' => TRUE, 'cast' => 'enum'],
			'viewSellingCategory' => ['enum', [\structure\Member::ITEM, \structure\Member::CUSTOMER, \structure\Member::SHOP, \structure\Member::PERIOD], 'cast' => 'enum'],
			'viewSellingPreparing' => ['json', 'null' => TRUE, 'cast' => 'array'],
			'viewMailingCategory' => ['enum', [\structure\Member::CAMPAIGN, \structure\Member::CONTACT], 'cast' => 'enum'],
			'viewShopSort' => ['enum', [\structure\Member::CUSTOMER, \structure\Member::POINT], 'cast' => 'enum'],
			'viewQuotation' => ['enum', [\structure\Member::ME, \structure\Member::ALL], 'cast' => 'enum'],
			'viewQuotationLevel' => ['enum', [\structure\Member::COUNTRY, \structure\Member::REGION], 'cast' => 'enum'],
			'viewQuotationRegion' => ['text8', 'null' => TRUE, 'cast' => 'string'],
			'viewAnalyzeChart' => ['enum', [\structure\Member::TURNOVER, \structure\Member::QUANTITY], 'cast' => 'enum'],
			'viewAnalyzeComposition' => ['enum', [\structure\Member::COMPOSITION, \structure\Member::INGREDIENT], 'cast' => 'enum'],
			'viewAnalyzeYear' => ['int16', 'min' => 0, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'viewTeamCategory' => ['enum', [\structure\Member::TEAM, \structure\Member::ROLE], 'cast' => 'enum'],
			'viewSeason' => ['int16', 'min' => 0, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'viewWeatherModel' => ['enum', [\structure\Member::ICON, \structure\Member::METEOFRANCE, \structure\Member::UKMO, \structure\Member::ECMWF], 'cast' => 'enum'],
			'viewDashboardActions' => ['json', 'null' => TRUE, 'cast' => 'array'],
			'viewNoteLinked' => ['bool', 'cast' => 'bool'],
			'helpers' => ['json', 'cast' => 'array'],
			'notificationsUnseen' => ['int32', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'notifications' => ['json', 'cast' => 'array'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'user', 'structure', 'structureStatus', 'status', 'inviteKey', 'role', 'visitedAt', 'viewPlanning', 'viewPlanningWeek', 'viewPlanningAction', 'viewPlanningPlant', 'viewPlanningUser', 'viewPlanningCategory', 'viewPlanningYear', 'viewPlanningHarvestExpected', 'viewPlanningHarvestQuick', 'viewPlanningField', 'viewPlanningArea', 'viewPlanningUserDaily', 'viewPlanningHideDone', 'viewPlanningMine', 'viewPlanningOrder', 'viewPlanningDensity', 'viewPlanningColor', 'viewPlanningSales', 'viewPlanningSalesDays', 'viewPlanningReminders', 'viewCultivationCategory', 'viewSeries', 'viewMap', 'viewMapBasemap', 'viewSoil', 'viewSoilColor', 'viewSoilOverlay', 'viewSoilTasks', 'viewSellingSales', 'viewSellingProducts', 'viewSellingProductsColumn', 'viewSellingCustomers', 'viewSellingType', 'viewSellingTransaction', 'viewSellingCategory', 'viewSellingPreparing', 'viewMailingCategory', 'viewShopSort', 'viewQuotation', 'viewQuotationLevel', 'viewQuotationRegion', 'viewAnalyzeChart', 'viewAnalyzeComposition', 'viewAnalyzeYear', 'viewTeamCategory', 'viewSeason', 'viewWeatherModel', 'viewDashboardActions', 'viewNoteLinked', 'helpers', 'notificationsUnseen', 'notifications', 'createdAt'
		]);

		$this->propertiesToModule += [
			'user' => 'user\User',
			'structure' => 'structure\Structure',
			'role' => 'structure\Role',
			'viewPlanningAction' => 'work\Action',
			'viewPlanningPlant' => 'plant\Plant',
			'viewPlanningUser' => 'user\User',
			'viewPlanningUserDaily' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['structure']
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['user', 'structure']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'structureStatus' :
				return Member::ACTIVE;

			case 'status' :
				return Member::IN;

			case 'viewPlanning' :
				return Member::WEEKLY;

			case 'viewPlanningWeek' :
				return Member::COLUMNS;

			case 'viewPlanningCategory' :
				return Member::TIME;

			case 'viewPlanningHarvestExpected' :
				return Member::TOTAL;

			case 'viewPlanningHarvestQuick' :
				return FALSE;

			case 'viewPlanningField' :
				return Member::VARIETY;

			case 'viewPlanningArea' :
				return Member::LENGTH;

			case 'viewPlanningHideDone' :
				return FALSE;

			case 'viewPlanningMine' :
				return FALSE;

			case 'viewPlanningDensity' :
				return Member::NORMAL;

			case 'viewPlanningColor' :
				return FALSE;

			case 'viewPlanningSales' :
				return FALSE;

			case 'viewPlanningSalesDays' :
				return 7;

			case 'viewPlanningReminders' :
				return FALSE;

			case 'viewCultivationCategory' :
				return Member::AREA;

			case 'viewSeries' :
				return Member::AREA;

			case 'viewMap' :
				return Member::PLAN;

			case 'viewMapBasemap' :
				return Member::SATELLITE;

			case 'viewSoil' :
				return Member::GARDEN;

			case 'viewSoilColor' :
				return Member::WHITE;

			case 'viewSoilOverlay' :
				return TRUE;

			case 'viewSoilTasks' :
				return FALSE;

			case 'viewSellingSales' :
				return Member::SALE;

			case 'viewSellingProducts' :
				return Member::PRODUCT;

			case 'viewSellingProductsColumn' :
				return Member::TURNOVER;

			case 'viewSellingCustomers' :
				return Member::CUSTOMER;

			case 'viewSellingCategory' :
				return Member::ITEM;

			case 'viewMailingCategory' :
				return Member::CAMPAIGN;

			case 'viewShopSort' :
				return Member::CUSTOMER;

			case 'viewQuotation' :
				return Member::ME;

			case 'viewQuotationLevel' :
				return Member::COUNTRY;

			case 'viewAnalyzeChart' :
				return Member::TURNOVER;

			case 'viewAnalyzeComposition' :
				return Member::COMPOSITION;

			case 'viewTeamCategory' :
				return Member::TEAM;

			case 'viewWeatherModel' :
				return Member::ICON;

			case 'viewNoteLinked' :
				return TRUE;

			case 'helpers' :
				return [];

			case 'notificationsUnseen' :
				return 0;

			case 'notifications' :
				return [];

			case 'createdAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'structureStatus' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'status' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'viewPlanning' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'viewPlanningWeek' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'viewPlanningCategory' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'viewPlanningHarvestExpected' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'viewPlanningField' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'viewPlanningArea' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'viewPlanningOrder' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			case 'viewPlanningDensity' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'viewCultivationCategory' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'viewSeries' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'viewMap' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'viewMapBasemap' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'viewSoil' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'viewSoilColor' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'viewSellingSales' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'viewSellingProducts' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'viewSellingProductsColumn' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'viewSellingCustomers' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'viewSellingType' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'viewSellingTransaction' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'viewSellingCategory' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'viewSellingPreparing' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			case 'viewMailingCategory' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'viewShopSort' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'viewQuotation' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'viewQuotationLevel' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'viewAnalyzeChart' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'viewAnalyzeComposition' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'viewTeamCategory' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'viewWeatherModel' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'viewDashboardActions' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			case 'helpers' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			case 'notifications' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'viewPlanningOrder' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			case 'viewSellingPreparing' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			case 'viewDashboardActions' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			case 'helpers' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			case 'notifications' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			default :
				return parent::decode($property, $value);

		}

	}

	public function select(...$fields): MemberModel {
		return parent::select(...$fields);
	}

	public function where(...$data): MemberModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): MemberModel {
		return $this->where('id', ...$data);
	}

	public function whereUser(...$data): MemberModel {
		return $this->where('user', ...$data);
	}

	public function whereStructure(...$data): MemberModel {
		return $this->where('structure', ...$data);
	}

	public function whereStructureStatus(...$data): MemberModel {
		return $this->where('structureStatus', ...$data);
	}

	public function whereStatus(...$data): MemberModel {
		return $this->where('status', ...$data);
	}

	public function whereInviteKey(...$data): MemberModel {
		return $this->where('inviteKey', ...$data);
	}

	public function whereRole(...$data): MemberModel {
		return $this->where('role', ...$data);
	}

	public function whereVisitedAt(...$data): MemberModel {
		return $this->where('visitedAt', ...$data);
	}

	public function whereViewPlanning(...$data): MemberModel {
		return $this->where('viewPlanning', ...$data);
	}

	public function whereViewPlanningWeek(...$data): MemberModel {
		return $this->where('viewPlanningWeek', ...$data);
	}

	public function whereViewPlanningAction(...$data): MemberModel {
		return $this->where('viewPlanningAction', ...$data);
	}

	public function whereViewPlanningPlant(...$data): MemberModel {
		return $this->where('viewPlanningPlant', ...$data);
	}

	public function whereViewPlanningUser(...$data): MemberModel {
		return $this->where('viewPlanningUser', ...$data);
	}

	public function whereViewPlanningCategory(...$data): MemberModel {
		return $this->where('viewPlanningCategory', ...$data);
	}

	public function whereViewPlanningYear(...$data): MemberModel {
		return $this->where('viewPlanningYear', ...$data);
	}

	public function whereViewPlanningHarvestExpected(...$data): MemberModel {
		return $this->where('viewPlanningHarvestExpected', ...$data);
	}

	public function whereViewPlanningHarvestQuick(...$data): MemberModel {
		return $this->where('viewPlanningHarvestQuick', ...$data);
	}

	public function whereViewPlanningField(...$data): MemberModel {
		return $this->where('viewPlanningField', ...$data);
	}

	public function whereViewPlanningArea(...$data): MemberModel {
		return $this->where('viewPlanningArea', ...$data);
	}

	public function whereViewPlanningUserDaily(...$data): MemberModel {
		return $this->where('viewPlanningUserDaily', ...$data);
	}

	public function whereViewPlanningHideDone(...$data): MemberModel {
		return $this->where('viewPlanningHideDone', ...$data);
	}

	public function whereViewPlanningMine(...$data): MemberModel {
		return $this->where('viewPlanningMine', ...$data);
	}

	public function whereViewPlanningOrder(...$data): MemberModel {
		return $this->where('viewPlanningOrder', ...$data);
	}

	public function whereViewPlanningDensity(...$data): MemberModel {
		return $this->where('viewPlanningDensity', ...$data);
	}

	public function whereViewPlanningColor(...$data): MemberModel {
		return $this->where('viewPlanningColor', ...$data);
	}

	public function whereViewPlanningSales(...$data): MemberModel {
		return $this->where('viewPlanningSales', ...$data);
	}

	public function whereViewPlanningSalesDays(...$data): MemberModel {
		return $this->where('viewPlanningSalesDays', ...$data);
	}

	public function whereViewPlanningReminders(...$data): MemberModel {
		return $this->where('viewPlanningReminders', ...$data);
	}

	public function whereViewCultivationCategory(...$data): MemberModel {
		return $this->where('viewCultivationCategory', ...$data);
	}

	public function whereViewSeries(...$data): MemberModel {
		return $this->where('viewSeries', ...$data);
	}

	public function whereViewMap(...$data): MemberModel {
		return $this->where('viewMap', ...$data);
	}

	public function whereViewMapBasemap(...$data): MemberModel {
		return $this->where('viewMapBasemap', ...$data);
	}

	public function whereViewSoil(...$data): MemberModel {
		return $this->where('viewSoil', ...$data);
	}

	public function whereViewSoilColor(...$data): MemberModel {
		return $this->where('viewSoilColor', ...$data);
	}

	public function whereViewSoilOverlay(...$data): MemberModel {
		return $this->where('viewSoilOverlay', ...$data);
	}

	public function whereViewSoilTasks(...$data): MemberModel {
		return $this->where('viewSoilTasks', ...$data);
	}

	public function whereViewSellingSales(...$data): MemberModel {
		return $this->where('viewSellingSales', ...$data);
	}

	public function whereViewSellingProducts(...$data): MemberModel {
		return $this->where('viewSellingProducts', ...$data);
	}

	public function whereViewSellingProductsColumn(...$data): MemberModel {
		return $this->where('viewSellingProductsColumn', ...$data);
	}

	public function whereViewSellingCustomers(...$data): MemberModel {
		return $this->where('viewSellingCustomers', ...$data);
	}

	public function whereViewSellingType(...$data): MemberModel {
		return $this->where('viewSellingType', ...$data);
	}

	public function whereViewSellingTransaction(...$data): MemberModel {
		return $this->where('viewSellingTransaction', ...$data);
	}

	public function whereViewSellingCategory(...$data): MemberModel {
		return $this->where('viewSellingCategory', ...$data);
	}

	public function whereViewSellingPreparing(...$data): MemberModel {
		return $this->where('viewSellingPreparing', ...$data);
	}

	public function whereViewMailingCategory(...$data): MemberModel {
		return $this->where('viewMailingCategory', ...$data);
	}

	public function whereViewShopSort(...$data): MemberModel {
		return $this->where('viewShopSort', ...$data);
	}

	public function whereViewQuotation(...$data): MemberModel {
		return $this->where('viewQuotation', ...$data);
	}

	public function whereViewQuotationLevel(...$data): MemberModel {
		return $this->where('viewQuotationLevel', ...$data);
	}

	public function whereViewQuotationRegion(...$data): MemberModel {
		return $this->where('viewQuotationRegion', ...$data);
	}

	public function whereViewAnalyzeChart(...$data): MemberModel {
		return $this->where('viewAnalyzeChart', ...$data);
	}

	public function whereViewAnalyzeComposition(...$data): MemberModel {
		return $this->where('viewAnalyzeComposition', ...$data);
	}

	public function whereViewAnalyzeYear(...$data): MemberModel {
		return $this->where('viewAnalyzeYear', ...$data);
	}

	public function whereViewTeamCategory(...$data): MemberModel {
		return $this->where('viewTeamCategory', ...$data);
	}

	public function whereViewSeason(...$data): MemberModel {
		return $this->where('viewSeason', ...$data);
	}

	public function whereViewWeatherModel(...$data): MemberModel {
		return $this->where('viewWeatherModel', ...$data);
	}

	public function whereViewDashboardActions(...$data): MemberModel {
		return $this->where('viewDashboardActions', ...$data);
	}

	public function whereViewNoteLinked(...$data): MemberModel {
		return $this->where('viewNoteLinked', ...$data);
	}

	public function whereHelpers(...$data): MemberModel {
		return $this->where('helpers', ...$data);
	}

	public function whereNotificationsUnseen(...$data): MemberModel {
		return $this->where('notificationsUnseen', ...$data);
	}

	public function whereNotifications(...$data): MemberModel {
		return $this->where('notifications', ...$data);
	}

	public function whereCreatedAt(...$data): MemberModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class MemberCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Member {

		$e = new Member();

		if(empty($id)) {
			Member::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Member::getSelection();
		}

		if(Member::model()
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
			$properties = Member::getSelection();
		}

		if($sort !== NULL) {
			Member::model()->sort($sort);
		}

		return Member::model()
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

	public static function getNewElement(array $properties = []): Member {

		return new Member($properties);

	}

	public static function create(Member $e): void {

		Member::model()->insert($e);

	}

	public static function update(Member $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Member::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Member $e, array $properties): void {

		self::$affected = Member::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Member $e): void {

		$e->expects(['id']);

		self::$affected = Member::model()->delete($e);

	}

}


class MemberPage extends \ModulePage {

	protected string $module = 'structure\Member';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? MemberLib::getPropertiesCreate(),
		   $propertiesUpdate ?? MemberLib::getPropertiesUpdate()
		);
	}

}
?>