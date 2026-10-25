<?php
namespace notification;

abstract class NotificationElement extends \Element {

	use \FilterElement;

	private static ?NotificationModel $model = NULL;

	public static function getSelection(): array {
		return Notification::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): NotificationModel {
		if(self::$model === NULL) {
			self::$model = new NotificationModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Notification::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class NotificationModel extends \ModuleModel {

	protected string $module = 'notification\Notification';
	protected string $package = 'notification';
	protected string $table = 'notification';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'user' => ['element32', 'user\User', 'cast' => 'element'],
			'type' => ['text8', 'min' => 1, 'max' => 50, 'charset' => 'ascii', 'cast' => 'string'],
			'reference' => ['int32', 'min' => 0, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'group' => ['int32', 'min' => 0, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'content' => ['json', 'cast' => 'array'],
			'seenAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'readAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'user', 'type', 'reference', 'group', 'content', 'seenAt', 'readAt', 'createdAt'
		]);

		$this->propertiesToModule += [
			'user' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['user', 'createdAt'],
			['user', 'seenAt'],
			['createdAt']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'content' :
				return [];

			case 'createdAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'content' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'content' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			default :
				return parent::decode($property, $value);

		}

	}

	public function select(...$fields): NotificationModel {
		return parent::select(...$fields);
	}

	public function where(...$data): NotificationModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): NotificationModel {
		return $this->where('id', ...$data);
	}

	public function whereUser(...$data): NotificationModel {
		return $this->where('user', ...$data);
	}

	public function whereType(...$data): NotificationModel {
		return $this->where('type', ...$data);
	}

	public function whereReference(...$data): NotificationModel {
		return $this->where('reference', ...$data);
	}

	public function whereGroup(...$data): NotificationModel {
		return $this->where('group', ...$data);
	}

	public function whereContent(...$data): NotificationModel {
		return $this->where('content', ...$data);
	}

	public function whereSeenAt(...$data): NotificationModel {
		return $this->where('seenAt', ...$data);
	}

	public function whereReadAt(...$data): NotificationModel {
		return $this->where('readAt', ...$data);
	}

	public function whereCreatedAt(...$data): NotificationModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class NotificationCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Notification {

		$e = new Notification();

		if(empty($id)) {
			Notification::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Notification::getSelection();
		}

		if(Notification::model()
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
			$properties = Notification::getSelection();
		}

		if($sort !== NULL) {
			Notification::model()->sort($sort);
		}

		return Notification::model()
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

	public static function getNewElement(array $properties = []): Notification {

		return new Notification($properties);

	}

	public static function create(Notification $e): void {

		Notification::model()->insert($e);

	}

	public static function update(Notification $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Notification::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Notification $e, array $properties): void {

		self::$affected = Notification::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Notification $e): void {

		$e->expects(['id']);

		self::$affected = Notification::model()->delete($e);

	}

}


class NotificationPage extends \ModulePage {

	protected string $module = 'notification\Notification';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? NotificationLib::getPropertiesCreate(),
		   $propertiesUpdate ?? NotificationLib::getPropertiesUpdate()
		);
	}

}
?>