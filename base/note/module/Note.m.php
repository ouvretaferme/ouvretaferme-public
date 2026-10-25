<?php
namespace note;

abstract class NoteElement extends \Element {

	use \FilterElement;

	private static ?NoteModel $model = NULL;

	const ADMIN = 'admin';
	const TEAM = 'team';

	public static function getSelection(): array {
		return Note::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): NoteModel {
		if(self::$model === NULL) {
			self::$model = new NoteModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Note::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class NoteModel extends \ModuleModel {

	protected string $module = 'note\Note';
	protected string $package = 'note';
	protected string $table = 'note';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'notebook' => ['element32', 'note\Notebook', 'null' => TRUE, 'cast' => 'element'],
			'title' => ['text8', 'min' => 1, 'max' => 100, 'cast' => 'string'],
			'content' => ['editor24', 'null' => TRUE, 'cast' => 'string'],
			'readers' => ['enum', [\note\Note::ADMIN, \note\Note::TEAM], 'cast' => 'enum'],
			'pinned' => ['bool', 'cast' => 'bool'],
			'createdAt' => ['datetime', 'cast' => 'string'],
			'createdBy' => ['element32', 'user\User', 'cast' => 'element'],
			'updatedAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'notebook', 'title', 'content', 'readers', 'pinned', 'createdAt', 'createdBy', 'updatedAt'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
			'notebook' => 'note\Notebook',
			'createdBy' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['structure', 'notebook']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'readers' :
				return Note::ADMIN;

			case 'pinned' :
				return FALSE;

			case 'createdAt' :
				return new \Sql('NOW()');

			case 'createdBy' :
				return \user\ConnectionLib::getOnline();

			case 'updatedAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'readers' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): NoteModel {
		return parent::select(...$fields);
	}

	public function where(...$data): NoteModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): NoteModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): NoteModel {
		return $this->where('structure', ...$data);
	}

	public function whereNotebook(...$data): NoteModel {
		return $this->where('notebook', ...$data);
	}

	public function whereTitle(...$data): NoteModel {
		return $this->where('title', ...$data);
	}

	public function whereContent(...$data): NoteModel {
		return $this->where('content', ...$data);
	}

	public function whereReaders(...$data): NoteModel {
		return $this->where('readers', ...$data);
	}

	public function wherePinned(...$data): NoteModel {
		return $this->where('pinned', ...$data);
	}

	public function whereCreatedAt(...$data): NoteModel {
		return $this->where('createdAt', ...$data);
	}

	public function whereCreatedBy(...$data): NoteModel {
		return $this->where('createdBy', ...$data);
	}

	public function whereUpdatedAt(...$data): NoteModel {
		return $this->where('updatedAt', ...$data);
	}


}


abstract class NoteCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Note {

		$e = new Note();

		if(empty($id)) {
			Note::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Note::getSelection();
		}

		if(Note::model()
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
			$properties = Note::getSelection();
		}

		if($sort !== NULL) {
			Note::model()->sort($sort);
		}

		return Note::model()
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

	public static function getNewElement(array $properties = []): Note {

		return new Note($properties);

	}

	public static function create(Note $e): void {

		Note::model()->insert($e);

	}

	public static function update(Note $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Note::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Note $e, array $properties): void {

		self::$affected = Note::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Note $e): void {

		$e->expects(['id']);

		self::$affected = Note::model()->delete($e);

	}

}


class NotePage extends \ModulePage {

	protected string $module = 'note\Note';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? NoteLib::getPropertiesCreate(),
		   $propertiesUpdate ?? NoteLib::getPropertiesUpdate()
		);
	}

}
?>