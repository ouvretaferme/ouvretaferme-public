<?php
namespace communication;

abstract class NoteRecipientElement extends \Element {

	use \FilterElement;

	private static ?NoteRecipientModel $model = NULL;

	public static function getSelection(): array {
		return NoteRecipient::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): NoteRecipientModel {
		if(self::$model === NULL) {
			self::$model = new NoteRecipientModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('NoteRecipient::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class NoteRecipientModel extends \ModuleModel {

	protected string $module = 'communication\NoteRecipient';
	protected string $package = 'communication';
	protected string $table = 'communicationNoteRecipient';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'note' => ['element32', 'communication\Note', 'cast' => 'element'],
			'user' => ['element32', 'user\User', 'cast' => 'element'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'seenAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'readAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'note', 'user', 'structure', 'seenAt', 'readAt', 'createdAt'
		]);

		$this->propertiesToModule += [
			'note' => 'communication\Note',
			'user' => 'user\User',
			'structure' => 'structure\Structure',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['user', 'structure', 'createdAt'],
			['createdAt']
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['note', 'user', 'structure']
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

	public function select(...$fields): NoteRecipientModel {
		return parent::select(...$fields);
	}

	public function where(...$data): NoteRecipientModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): NoteRecipientModel {
		return $this->where('id', ...$data);
	}

	public function whereNote(...$data): NoteRecipientModel {
		return $this->where('note', ...$data);
	}

	public function whereUser(...$data): NoteRecipientModel {
		return $this->where('user', ...$data);
	}

	public function whereStructure(...$data): NoteRecipientModel {
		return $this->where('structure', ...$data);
	}

	public function whereSeenAt(...$data): NoteRecipientModel {
		return $this->where('seenAt', ...$data);
	}

	public function whereReadAt(...$data): NoteRecipientModel {
		return $this->where('readAt', ...$data);
	}

	public function whereCreatedAt(...$data): NoteRecipientModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class NoteRecipientCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): NoteRecipient {

		$e = new NoteRecipient();

		if(empty($id)) {
			NoteRecipient::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = NoteRecipient::getSelection();
		}

		if(NoteRecipient::model()
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
			$properties = NoteRecipient::getSelection();
		}

		if($sort !== NULL) {
			NoteRecipient::model()->sort($sort);
		}

		return NoteRecipient::model()
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

	public static function getNewElement(array $properties = []): NoteRecipient {

		return new NoteRecipient($properties);

	}

	public static function create(NoteRecipient $e): void {

		NoteRecipient::model()->insert($e);

	}

	public static function update(NoteRecipient $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = NoteRecipient::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, NoteRecipient $e, array $properties): void {

		self::$affected = NoteRecipient::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(NoteRecipient $e): void {

		$e->expects(['id']);

		self::$affected = NoteRecipient::model()->delete($e);

	}

}


class NoteRecipientPage extends \ModulePage {

	protected string $module = 'communication\NoteRecipient';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? NoteRecipientLib::getPropertiesCreate(),
		   $propertiesUpdate ?? NoteRecipientLib::getPropertiesUpdate()
		);
	}

}
?>