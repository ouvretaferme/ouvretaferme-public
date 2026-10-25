<?php
namespace aid;

abstract class SourceElement extends \Element {

	use \FilterElement;

	private static ?SourceModel $model = NULL;

	const PRIMARY = 'primary';
	const RELAY = 'relay';
	const AGGREGATOR = 'aggregator';
	const PIECES = 'pieces';

	const ALIVE = 'alive';
	const MOVED = 'moved';
	const DEAD = 'dead';

	const FORBIDDEN = 'forbidden';
	const LIMITED = 'limited';
	const SERVER = 'server';
	const TIMEOUT = 'timeout';
	const CHALLENGE = 'challenge';
	const ROBOTS = 'robots';

	public static function getSelection(): array {
		return Source::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): SourceModel {
		if(self::$model === NULL) {
			self::$model = new SourceModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Source::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class SourceModel extends \ModuleModel {

	protected string $module = 'aid\Source';
	protected string $package = 'aid';
	protected string $table = 'aidSource';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'aid' => ['element32', 'aid\Aid', 'cast' => 'element'],
			'url' => ['url', 'cast' => 'string'],
			'kind' => ['enum', [\aid\Source::PRIMARY, \aid\Source::RELAY, \aid\Source::AGGREGATOR, \aid\Source::PIECES], 'cast' => 'enum'],
			'status' => ['enum', [\aid\Source::ALIVE, \aid\Source::MOVED, \aid\Source::DEAD], 'cast' => 'enum'],
			'statusAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'movedUrl' => ['url', 'null' => TRUE, 'cast' => 'string'],
			'fingerprint' => ['text8', 'min' => 1, 'max' => 64, 'charset' => 'ascii', 'null' => TRUE, 'cast' => 'string'],
			'failures' => ['int8', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'failedAt' => ['date', 'null' => TRUE, 'cast' => 'string'],
			'unreadSince' => ['date', 'null' => TRUE, 'cast' => 'string'],
			'unreadReason' => ['enum', [\aid\Source::FORBIDDEN, \aid\Source::LIMITED, \aid\Source::SERVER, \aid\Source::TIMEOUT, \aid\Source::CHALLENGE, \aid\Source::ROBOTS], 'null' => TRUE, 'cast' => 'enum'],
			'checkedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'changedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'aid', 'url', 'kind', 'status', 'statusAt', 'movedUrl', 'fingerprint', 'failures', 'failedAt', 'unreadSince', 'unreadReason', 'checkedAt', 'changedAt'
		]);

		$this->propertiesToModule += [
			'aid' => 'aid\Aid',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['aid']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'status' :
				return Source::ALIVE;

			case 'failures' :
				return 0;

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'kind' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'status' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'unreadReason' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): SourceModel {
		return parent::select(...$fields);
	}

	public function where(...$data): SourceModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): SourceModel {
		return $this->where('id', ...$data);
	}

	public function whereAid(...$data): SourceModel {
		return $this->where('aid', ...$data);
	}

	public function whereUrl(...$data): SourceModel {
		return $this->where('url', ...$data);
	}

	public function whereKind(...$data): SourceModel {
		return $this->where('kind', ...$data);
	}

	public function whereStatus(...$data): SourceModel {
		return $this->where('status', ...$data);
	}

	public function whereStatusAt(...$data): SourceModel {
		return $this->where('statusAt', ...$data);
	}

	public function whereMovedUrl(...$data): SourceModel {
		return $this->where('movedUrl', ...$data);
	}

	public function whereFingerprint(...$data): SourceModel {
		return $this->where('fingerprint', ...$data);
	}

	public function whereFailures(...$data): SourceModel {
		return $this->where('failures', ...$data);
	}

	public function whereFailedAt(...$data): SourceModel {
		return $this->where('failedAt', ...$data);
	}

	public function whereUnreadSince(...$data): SourceModel {
		return $this->where('unreadSince', ...$data);
	}

	public function whereUnreadReason(...$data): SourceModel {
		return $this->where('unreadReason', ...$data);
	}

	public function whereCheckedAt(...$data): SourceModel {
		return $this->where('checkedAt', ...$data);
	}

	public function whereChangedAt(...$data): SourceModel {
		return $this->where('changedAt', ...$data);
	}


}


abstract class SourceCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Source {

		$e = new Source();

		if(empty($id)) {
			Source::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Source::getSelection();
		}

		if(Source::model()
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
			$properties = Source::getSelection();
		}

		if($sort !== NULL) {
			Source::model()->sort($sort);
		}

		return Source::model()
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

	public static function getNewElement(array $properties = []): Source {

		return new Source($properties);

	}

	public static function create(Source $e): void {

		Source::model()->insert($e);

	}

	public static function update(Source $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Source::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Source $e, array $properties): void {

		self::$affected = Source::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Source $e): void {

		$e->expects(['id']);

		self::$affected = Source::model()->delete($e);

	}

}


class SourcePage extends \ModulePage {

	protected string $module = 'aid\Source';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? SourceLib::getPropertiesCreate(),
		   $propertiesUpdate ?? SourceLib::getPropertiesUpdate()
		);
	}

}
?>