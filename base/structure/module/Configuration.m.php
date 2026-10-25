<?php
namespace structure;

abstract class ConfigurationElement extends \Element {

	use \FilterElement;

	private static ?ConfigurationModel $model = NULL;

	const CASH = 'cash';
	const DEBIT = 'debit';

	public static function getSelection(): array {
		return Configuration::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): ConfigurationModel {
		if(self::$model === NULL) {
			self::$model = new ConfigurationModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Configuration::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class ConfigurationModel extends \ModuleModel {

	protected string $module = 'structure\Configuration';
	protected string $package = 'structure';
	protected string $table = 'structureConfiguration';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'unique' => TRUE, 'cast' => 'element'],
			'hasVat' => ['bool', 'null' => TRUE, 'cast' => 'bool'],
			'vatChargeability' => ['enum', [\structure\Configuration::CASH, \structure\Configuration::DEBIT], 'cast' => 'enum'],
			'vatDebitsOption' => ['bool', 'cast' => 'bool'],
			'vatNumber' => ['text8', 'null' => TRUE, 'cast' => 'string'],
			'organicCertifier' => ['text8', 'null' => TRUE, 'cast' => 'string'],
			'emailBanner' => ['textFixed', 'min' => 30, 'max' => 30, 'charset' => 'ascii', 'null' => TRUE, 'cast' => 'string'],
			'emailFooter' => ['editor16', 'min' => 1, 'max' => 2048, 'null' => TRUE, 'cast' => 'string'],
			'time' => ['bool', 'cast' => 'bool'],
			'timeRange' => ['bool', 'cast' => 'bool'],
			'timeReferenceMonth' => ['int8', 'min' => 1, 'max' => 12, 'cast' => 'int'],
			'timePeriodDay' => ['int8', 'min' => 1, 'max' => 28, 'cast' => 'int'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'hasVat', 'vatChargeability', 'vatDebitsOption', 'vatNumber', 'organicCertifier', 'emailBanner', 'emailFooter', 'time', 'timeRange', 'timeReferenceMonth', 'timePeriodDay'
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

			case 'vatChargeability' :
				return Configuration::CASH;

			case 'vatDebitsOption' :
				return FALSE;

			case 'time' :
				return TRUE;

			case 'timeRange' :
				return FALSE;

			case 'timeReferenceMonth' :
				return 1;

			case 'timePeriodDay' :
				return 1;

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'vatChargeability' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): ConfigurationModel {
		return parent::select(...$fields);
	}

	public function where(...$data): ConfigurationModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): ConfigurationModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): ConfigurationModel {
		return $this->where('structure', ...$data);
	}

	public function whereHasVat(...$data): ConfigurationModel {
		return $this->where('hasVat', ...$data);
	}

	public function whereVatChargeability(...$data): ConfigurationModel {
		return $this->where('vatChargeability', ...$data);
	}

	public function whereVatDebitsOption(...$data): ConfigurationModel {
		return $this->where('vatDebitsOption', ...$data);
	}

	public function whereVatNumber(...$data): ConfigurationModel {
		return $this->where('vatNumber', ...$data);
	}

	public function whereOrganicCertifier(...$data): ConfigurationModel {
		return $this->where('organicCertifier', ...$data);
	}

	public function whereEmailBanner(...$data): ConfigurationModel {
		return $this->where('emailBanner', ...$data);
	}

	public function whereEmailFooter(...$data): ConfigurationModel {
		return $this->where('emailFooter', ...$data);
	}

	public function whereTime(...$data): ConfigurationModel {
		return $this->where('time', ...$data);
	}

	public function whereTimeRange(...$data): ConfigurationModel {
		return $this->where('timeRange', ...$data);
	}

	public function whereTimeReferenceMonth(...$data): ConfigurationModel {
		return $this->where('timeReferenceMonth', ...$data);
	}

	public function whereTimePeriodDay(...$data): ConfigurationModel {
		return $this->where('timePeriodDay', ...$data);
	}


}


abstract class ConfigurationCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Configuration {

		$e = new Configuration();

		if(empty($id)) {
			Configuration::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Configuration::getSelection();
		}

		if(Configuration::model()
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
			$properties = Configuration::getSelection();
		}

		if($sort !== NULL) {
			Configuration::model()->sort($sort);
		}

		return Configuration::model()
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

	public static function getNewElement(array $properties = []): Configuration {

		return new Configuration($properties);

	}

	public static function create(Configuration $e): void {

		Configuration::model()->insert($e);

	}

	public static function update(Configuration $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Configuration::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Configuration $e, array $properties): void {

		self::$affected = Configuration::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Configuration $e): void {

		$e->expects(['id']);

		self::$affected = Configuration::model()->delete($e);

	}

}


class ConfigurationPage extends \ModulePage {

	protected string $module = 'structure\Configuration';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? ConfigurationLib::getPropertiesCreate(),
		   $propertiesUpdate ?? ConfigurationLib::getPropertiesUpdate()
		);
	}

}
?>