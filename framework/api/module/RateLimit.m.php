<?php
namespace api;

abstract class RateLimitElement extends \Element {

	use \FilterElement;

	private static ?RateLimitModel $model = NULL;

	public static function getSelection(): array {
		return RateLimit::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): RateLimitModel {
		if(self::$model === NULL) {
			self::$model = new RateLimitModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('RateLimit::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class RateLimitModel extends \ModuleModel {

	protected string $module = 'api\RateLimit';
	protected string $package = 'api';
	protected string $table = 'apiRateLimit';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'reference' => ['int32', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'calls' => ['int32', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'timestamp' => ['int64', 'cast' => 'int'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'reference', 'calls', 'timestamp'
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['reference', 'timestamp']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'calls' :
				return 1;

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function select(...$fields): RateLimitModel {
		return parent::select(...$fields);
	}

	public function where(...$data): RateLimitModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): RateLimitModel {
		return $this->where('id', ...$data);
	}

	public function whereReference(...$data): RateLimitModel {
		return $this->where('reference', ...$data);
	}

	public function whereCalls(...$data): RateLimitModel {
		return $this->where('calls', ...$data);
	}

	public function whereTimestamp(...$data): RateLimitModel {
		return $this->where('timestamp', ...$data);
	}


}


abstract class RateLimitCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): RateLimit {

		$e = new RateLimit();

		if(empty($id)) {
			RateLimit::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = RateLimit::getSelection();
		}

		if(RateLimit::model()
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
			$properties = RateLimit::getSelection();
		}

		if($sort !== NULL) {
			RateLimit::model()->sort($sort);
		}

		return RateLimit::model()
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

	public static function getNewElement(array $properties = []): RateLimit {

		return new RateLimit($properties);

	}

	public static function create(RateLimit $e): void {

		RateLimit::model()->insert($e);

	}

	public static function update(RateLimit $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = RateLimit::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, RateLimit $e, array $properties): void {

		self::$affected = RateLimit::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(RateLimit $e): void {

		$e->expects(['id']);

		self::$affected = RateLimit::model()->delete($e);

	}

}


class RateLimitPage extends \ModulePage {

	protected string $module = 'api\RateLimit';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? RateLimitLib::getPropertiesCreate(),
		   $propertiesUpdate ?? RateLimitLib::getPropertiesUpdate()
		);
	}

}
?>