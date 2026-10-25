<?php
namespace data;

abstract class PdpHistoryElement extends \Element {

	use \FilterElement;

	private static ?PdpHistoryModel $model = NULL;

	const INVOICE = 'invoice';
	const INVOICES = 'invoices';
	const INVOICE_DETAILS = 'invoice-details';
	const INVOICE_SEND = 'invoice-send';
	const INVOICE_DOWNLOAD = 'invoice-download';
	const EVENT = 'event';
	const EVENT_SEND = 'event-send';
	const ADDRESS = 'address';
	const ADDRESS_CREATE = 'address-create';
	const ADDRESS_DELETE = 'address-delete';
	const COMPANY_RETRIEVE = 'company-retrieve';
	const COMPANY = 'company';
	const SESSION = 'session';
	const TOKEN_REFRESH = 'token-refresh';
	const VALIDATION_REPORT = 'validation-report';

	public static function getSelection(): array {
		return PdpHistory::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): PdpHistoryModel {
		if(self::$model === NULL) {
			self::$model = new PdpHistoryModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('PdpHistory::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class PdpHistoryModel extends \ModuleModel {

	protected string $module = 'data\PdpHistory';
	protected string $package = 'data';
	protected string $table = 'dataPdpHistory';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'partner' => ['element32', 'pdp\Partner', 'null' => TRUE, 'cast' => 'element'],
			'action' => ['enum', [\data\PdpHistory::INVOICE, \data\PdpHistory::INVOICES, \data\PdpHistory::INVOICE_DETAILS, \data\PdpHistory::INVOICE_SEND, \data\PdpHistory::INVOICE_DOWNLOAD, \data\PdpHistory::EVENT, \data\PdpHistory::EVENT_SEND, \data\PdpHistory::ADDRESS, \data\PdpHistory::ADDRESS_CREATE, \data\PdpHistory::ADDRESS_DELETE, \data\PdpHistory::COMPANY_RETRIEVE, \data\PdpHistory::COMPANY, \data\PdpHistory::SESSION, \data\PdpHistory::TOKEN_REFRESH, \data\PdpHistory::VALIDATION_REPORT], 'cast' => 'enum'],
			'invoicing' => ['element32', 'invoicing\Invoicing', 'null' => TRUE, 'cast' => 'element'],
			'arg' => ['json', 'null' => TRUE, 'cast' => 'array'],
			'request' => ['text8', 'cast' => 'string'],
			'result' => ['json', 'null' => TRUE, 'cast' => 'array'],
			'date' => ['datetime', 'cast' => 'string'],
			'doneBy' => ['element32', 'user\User', 'null' => TRUE, 'cast' => 'element'],
			'relaunchedDate' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'relaunchedBy' => ['element32', 'user\User', 'null' => TRUE, 'cast' => 'element'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'partner', 'action', 'invoicing', 'arg', 'request', 'result', 'date', 'doneBy', 'relaunchedDate', 'relaunchedBy'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
			'partner' => 'pdp\Partner',
			'invoicing' => 'invoicing\Invoicing',
			'doneBy' => 'user\User',
			'relaunchedBy' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['action', 'date']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'arg' :
				return [];

			case 'result' :
				return [];

			case 'date' :
				return new \Sql('NOW()');

			case 'doneBy' :
				return \user\ConnectionLib::getOnline();

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'action' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'arg' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			case 'result' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'arg' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			case 'result' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			default :
				return parent::decode($property, $value);

		}

	}

	public function select(...$fields): PdpHistoryModel {
		return parent::select(...$fields);
	}

	public function where(...$data): PdpHistoryModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): PdpHistoryModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): PdpHistoryModel {
		return $this->where('structure', ...$data);
	}

	public function wherePartner(...$data): PdpHistoryModel {
		return $this->where('partner', ...$data);
	}

	public function whereAction(...$data): PdpHistoryModel {
		return $this->where('action', ...$data);
	}

	public function whereInvoicing(...$data): PdpHistoryModel {
		return $this->where('invoicing', ...$data);
	}

	public function whereArg(...$data): PdpHistoryModel {
		return $this->where('arg', ...$data);
	}

	public function whereRequest(...$data): PdpHistoryModel {
		return $this->where('request', ...$data);
	}

	public function whereResult(...$data): PdpHistoryModel {
		return $this->where('result', ...$data);
	}

	public function whereDate(...$data): PdpHistoryModel {
		return $this->where('date', ...$data);
	}

	public function whereDoneBy(...$data): PdpHistoryModel {
		return $this->where('doneBy', ...$data);
	}

	public function whereRelaunchedDate(...$data): PdpHistoryModel {
		return $this->where('relaunchedDate', ...$data);
	}

	public function whereRelaunchedBy(...$data): PdpHistoryModel {
		return $this->where('relaunchedBy', ...$data);
	}


}


abstract class PdpHistoryCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): PdpHistory {

		$e = new PdpHistory();

		if(empty($id)) {
			PdpHistory::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = PdpHistory::getSelection();
		}

		if(PdpHistory::model()
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
			$properties = PdpHistory::getSelection();
		}

		if($sort !== NULL) {
			PdpHistory::model()->sort($sort);
		}

		return PdpHistory::model()
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

	public static function getNewElement(array $properties = []): PdpHistory {

		return new PdpHistory($properties);

	}

	public static function create(PdpHistory $e): void {

		PdpHistory::model()->insert($e);

	}

	public static function update(PdpHistory $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = PdpHistory::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, PdpHistory $e, array $properties): void {

		self::$affected = PdpHistory::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(PdpHistory $e): void {

		$e->expects(['id']);

		self::$affected = PdpHistory::model()->delete($e);

	}

}


class PdpHistoryPage extends \ModulePage {

	protected string $module = 'data\PdpHistory';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? PdpHistoryLib::getPropertiesCreate(),
		   $propertiesUpdate ?? PdpHistoryLib::getPropertiesUpdate()
		);
	}

}
?>