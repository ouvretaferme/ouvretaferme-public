<?php
namespace data;

abstract class EnableBankingHistoryElement extends \Element {

	use \FilterElement;

	private static ?EnableBankingHistoryModel $model = NULL;

	public static function getSelection(): array {
		return EnableBankingHistory::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): EnableBankingHistoryModel {
		if(self::$model === NULL) {
			self::$model = new EnableBankingHistoryModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('EnableBankingHistory::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class EnableBankingHistoryModel extends \ModuleModel {

	protected string $module = 'data\EnableBankingHistory';
	protected string $package = 'data';
	protected string $table = 'dataEnableBankingHistory';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'accountId' => ['text8', 'min' => 1, 'max' => NULL, 'cast' => 'string'],
			'connectedAt' => ['text8', 'min' => 7, 'max' => 7, 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'structure', 'accountId', 'connectedAt'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
		];

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['accountId', 'connectedAt']
		]);

	}

	public function select(...$fields): EnableBankingHistoryModel {
		return parent::select(...$fields);
	}

	public function where(...$data): EnableBankingHistoryModel {
		return parent::where(...$data);
	}

	public function whereStructure(...$data): EnableBankingHistoryModel {
		return $this->where('structure', ...$data);
	}

	public function whereAccountId(...$data): EnableBankingHistoryModel {
		return $this->where('accountId', ...$data);
	}

	public function whereConnectedAt(...$data): EnableBankingHistoryModel {
		return $this->where('connectedAt', ...$data);
	}


}


abstract class EnableBankingHistoryCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): EnableBankingHistory {

		$e = new EnableBankingHistory();

		if(empty($id)) {
			EnableBankingHistory::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = EnableBankingHistory::getSelection();
		}

		if(EnableBankingHistory::model()
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
			$properties = EnableBankingHistory::getSelection();
		}

		if($sort !== NULL) {
			EnableBankingHistory::model()->sort($sort);
		}

		return EnableBankingHistory::model()
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

	public static function getNewElement(array $properties = []): EnableBankingHistory {

		return new EnableBankingHistory($properties);

	}

	public static function create(EnableBankingHistory $e): void {

		EnableBankingHistory::model()->insert($e);

	}

	public static function update(EnableBankingHistory $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = EnableBankingHistory::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, EnableBankingHistory $e, array $properties): void {

		self::$affected = EnableBankingHistory::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(EnableBankingHistory $e): void {

		$e->expects(['id']);

		self::$affected = EnableBankingHistory::model()->delete($e);

	}

}


class EnableBankingHistoryPage extends \ModulePage {

	protected string $module = 'data\EnableBankingHistory';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? EnableBankingHistoryLib::getPropertiesCreate(),
		   $propertiesUpdate ?? EnableBankingHistoryLib::getPropertiesUpdate()
		);
	}

}
?>