<?php
namespace communication;

abstract class WebinaireSubscriptionElement extends \Element {

	use \FilterElement;

	private static ?WebinaireSubscriptionModel $model = NULL;

	public static function getSelection(): array {
		return WebinaireSubscription::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): WebinaireSubscriptionModel {
		if(self::$model === NULL) {
			self::$model = new WebinaireSubscriptionModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('WebinaireSubscription::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class WebinaireSubscriptionModel extends \ModuleModel {

	protected string $module = 'communication\WebinaireSubscription';
	protected string $package = 'communication';
	protected string $table = 'communicationWebinaireSubscription';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'firstName' => ['text8', 'min' => 1, 'max' => \user\UserSetting::NAME_SIZE_MAX, 'collate' => 'general', 'cast' => 'string'],
			'lastName' => ['text8', 'min' => 1, 'max' => \user\UserSetting::NAME_SIZE_MAX, 'collate' => 'general', 'cast' => 'string'],
			'email' => ['email', 'unique' => TRUE, 'cast' => 'string'],
			'date' => ['int8', 'cast' => 'int'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'firstName', 'lastName', 'email', 'date', 'createdAt'
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['email']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'createdAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function select(...$fields): WebinaireSubscriptionModel {
		return parent::select(...$fields);
	}

	public function where(...$data): WebinaireSubscriptionModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): WebinaireSubscriptionModel {
		return $this->where('id', ...$data);
	}

	public function whereFirstName(...$data): WebinaireSubscriptionModel {
		return $this->where('firstName', ...$data);
	}

	public function whereLastName(...$data): WebinaireSubscriptionModel {
		return $this->where('lastName', ...$data);
	}

	public function whereEmail(...$data): WebinaireSubscriptionModel {
		return $this->where('email', ...$data);
	}

	public function whereDate(...$data): WebinaireSubscriptionModel {
		return $this->where('date', ...$data);
	}

	public function whereCreatedAt(...$data): WebinaireSubscriptionModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class WebinaireSubscriptionCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): WebinaireSubscription {

		$e = new WebinaireSubscription();

		if(empty($id)) {
			WebinaireSubscription::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = WebinaireSubscription::getSelection();
		}

		if(WebinaireSubscription::model()
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
			$properties = WebinaireSubscription::getSelection();
		}

		if($sort !== NULL) {
			WebinaireSubscription::model()->sort($sort);
		}

		return WebinaireSubscription::model()
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

	public static function getNewElement(array $properties = []): WebinaireSubscription {

		return new WebinaireSubscription($properties);

	}

	public static function create(WebinaireSubscription $e): void {

		WebinaireSubscription::model()->insert($e);

	}

	public static function update(WebinaireSubscription $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = WebinaireSubscription::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, WebinaireSubscription $e, array $properties): void {

		self::$affected = WebinaireSubscription::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(WebinaireSubscription $e): void {

		$e->expects(['id']);

		self::$affected = WebinaireSubscription::model()->delete($e);

	}

}


class WebinaireSubscriptionPage extends \ModulePage {

	protected string $module = 'communication\WebinaireSubscription';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? WebinaireSubscriptionLib::getPropertiesCreate(),
		   $propertiesUpdate ?? WebinaireSubscriptionLib::getPropertiesUpdate()
		);
	}

}
?>