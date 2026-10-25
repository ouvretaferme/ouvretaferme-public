<?php
namespace securing;

abstract class SignatureTailElement extends \Element {

	use \FilterElement;

	private static ?SignatureTailModel $model = NULL;

	const SALE = 'sale';
	const CASH = 'cash';
	const MARKET_DEVICE = 'market-device';
	const MARKET_DEVICE_SET_ASIDE = 'market-device-set-aside';
	const CASH_ARCHIVE = 'cash-archive';
	const SELLING_ARCHIVE = 'selling-archive';

	public static function getSelection(): array {
		return SignatureTail::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): SignatureTailModel {
		if(self::$model === NULL) {
			self::$model = new SignatureTailModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('SignatureTail::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class SignatureTailModel extends \ModuleModel {

	protected string $module = 'securing\SignatureTail';
	protected string $package = 'securing';
	protected string $table = 'securingSignatureTail';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'source' => ['enum', [\securing\SignatureTail::SALE, \securing\SignatureTail::CASH, \securing\SignatureTail::MARKET_DEVICE, \securing\SignatureTail::MARKET_DEVICE_SET_ASIDE, \securing\SignatureTail::CASH_ARCHIVE, \securing\SignatureTail::SELLING_ARCHIVE], 'unique' => TRUE, 'cast' => 'enum'],
			'key' => ['int16', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'hmac' => ['text8', 'min' => 64, 'max' => 64, 'charset' => 'ascii', 'cast' => 'string'],
			'updatedAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'source', 'key', 'hmac', 'updatedAt'
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['source']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'updatedAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'source' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): SignatureTailModel {
		return parent::select(...$fields);
	}

	public function where(...$data): SignatureTailModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): SignatureTailModel {
		return $this->where('id', ...$data);
	}

	public function whereSource(...$data): SignatureTailModel {
		return $this->where('source', ...$data);
	}

	public function whereKey(...$data): SignatureTailModel {
		return $this->where('key', ...$data);
	}

	public function whereHmac(...$data): SignatureTailModel {
		return $this->where('hmac', ...$data);
	}

	public function whereUpdatedAt(...$data): SignatureTailModel {
		return $this->where('updatedAt', ...$data);
	}


}


abstract class SignatureTailCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): SignatureTail {

		$e = new SignatureTail();

		if(empty($id)) {
			SignatureTail::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = SignatureTail::getSelection();
		}

		if(SignatureTail::model()
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
			$properties = SignatureTail::getSelection();
		}

		if($sort !== NULL) {
			SignatureTail::model()->sort($sort);
		}

		return SignatureTail::model()
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

	public static function getNewElement(array $properties = []): SignatureTail {

		return new SignatureTail($properties);

	}

	public static function create(SignatureTail $e): void {

		SignatureTail::model()->insert($e);

	}

	public static function update(SignatureTail $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = SignatureTail::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, SignatureTail $e, array $properties): void {

		self::$affected = SignatureTail::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(SignatureTail $e): void {

		$e->expects(['id']);

		self::$affected = SignatureTail::model()->delete($e);

	}

}


class SignatureTailPage extends \ModulePage {

	protected string $module = 'securing\SignatureTail';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? SignatureTailLib::getPropertiesCreate(),
		   $propertiesUpdate ?? SignatureTailLib::getPropertiesUpdate()
		);
	}

}
?>