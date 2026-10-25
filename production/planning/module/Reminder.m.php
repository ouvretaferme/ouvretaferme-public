<?php
namespace planning;

abstract class ReminderElement extends \Element {

	use \FilterElement;

	private static ?ReminderModel $model = NULL;

	const TODO = 'todo';
	const DONE = 'done';

	public static function getSelection(): array {
		return Reminder::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): ReminderModel {
		if(self::$model === NULL) {
			self::$model = new ReminderModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Reminder::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class ReminderModel extends \ModuleModel {

	protected string $module = 'planning\Reminder';
	protected string $package = 'planning';
	protected string $table = 'planningReminder';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'week' => ['week', 'cast' => 'string'],
			'date' => ['date', 'null' => TRUE, 'cast' => 'string'],
			'text' => ['text8', 'min' => 1, 'max' => 250, 'cast' => 'string'],
			'status' => ['enum', [\planning\Reminder::TODO, \planning\Reminder::DONE], 'cast' => 'enum'],
			'doneAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'doneBy' => ['element32', 'user\User', 'null' => TRUE, 'cast' => 'element'],
			'createdAt' => ['datetime', 'cast' => 'string'],
			'createdBy' => ['element32', 'user\User', 'cast' => 'element'],
			'updatedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'week', 'date', 'text', 'status', 'doneAt', 'doneBy', 'createdAt', 'createdBy', 'updatedAt'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
			'doneBy' => 'user\User',
			'createdBy' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['structure', 'status', 'week'],
			['structure', 'status', 'doneAt']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'status' :
				return Reminder::TODO;

			case 'createdAt' :
				return new \Sql('NOW()');

			case 'createdBy' :
				return \user\ConnectionLib::getOnline();

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'status' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): ReminderModel {
		return parent::select(...$fields);
	}

	public function where(...$data): ReminderModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): ReminderModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): ReminderModel {
		return $this->where('structure', ...$data);
	}

	public function whereWeek(...$data): ReminderModel {
		return $this->where('week', ...$data);
	}

	public function whereDate(...$data): ReminderModel {
		return $this->where('date', ...$data);
	}

	public function whereText(...$data): ReminderModel {
		return $this->where('text', ...$data);
	}

	public function whereStatus(...$data): ReminderModel {
		return $this->where('status', ...$data);
	}

	public function whereDoneAt(...$data): ReminderModel {
		return $this->where('doneAt', ...$data);
	}

	public function whereDoneBy(...$data): ReminderModel {
		return $this->where('doneBy', ...$data);
	}

	public function whereCreatedAt(...$data): ReminderModel {
		return $this->where('createdAt', ...$data);
	}

	public function whereCreatedBy(...$data): ReminderModel {
		return $this->where('createdBy', ...$data);
	}

	public function whereUpdatedAt(...$data): ReminderModel {
		return $this->where('updatedAt', ...$data);
	}


}


abstract class ReminderCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Reminder {

		$e = new Reminder();

		if(empty($id)) {
			Reminder::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Reminder::getSelection();
		}

		if(Reminder::model()
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
			$properties = Reminder::getSelection();
		}

		if($sort !== NULL) {
			Reminder::model()->sort($sort);
		}

		return Reminder::model()
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

	public static function getNewElement(array $properties = []): Reminder {

		return new Reminder($properties);

	}

	public static function create(Reminder $e): void {

		Reminder::model()->insert($e);

	}

	public static function update(Reminder $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Reminder::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Reminder $e, array $properties): void {

		self::$affected = Reminder::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Reminder $e): void {

		$e->expects(['id']);

		self::$affected = Reminder::model()->delete($e);

	}

}


class ReminderPage extends \ModulePage {

	protected string $module = 'planning\Reminder';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? ReminderLib::getPropertiesCreate(),
		   $propertiesUpdate ?? ReminderLib::getPropertiesUpdate()
		);
	}

}
?>