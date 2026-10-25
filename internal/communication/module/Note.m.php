<?php
namespace communication;

abstract class NoteElement extends \Element {

	use \FilterElement;

	private static ?NoteModel $model = NULL;

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

	protected string $module = 'communication\Note';
	protected string $package = 'communication';
	protected string $table = 'communicationNote';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'title' => ['text8', 'min' => 1, 'max' => 100, 'cast' => 'string'],
			'content' => ['editor16', 'min' => 1, 'max' => 2048, 'cast' => 'string'],
			'buttonLabel' => ['text8', 'min' => 1, 'max' => 30, 'null' => TRUE, 'cast' => 'string'],
			'buttonPath' => ['text8', 'min' => 1, 'max' => 100, 'null' => TRUE, 'cast' => 'string'],
			'structureTypes' => ['json', 'cast' => 'array'],
			'features' => ['json', 'cast' => 'array'],
			'structures' => ['int32', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'users' => ['int32', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'views' => ['int32', 'min' => 0, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'reads' => ['int32', 'min' => 0, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'createdBy' => ['element32', 'user\User', 'cast' => 'element'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'title', 'content', 'buttonLabel', 'buttonPath', 'structureTypes', 'features', 'structures', 'users', 'views', 'reads', 'createdBy', 'createdAt'
		]);

		$this->propertiesToModule += [
			'createdBy' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['createdAt']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'structureTypes' :
				return [];

			case 'features' :
				return [];

			case 'createdAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'structureTypes' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			case 'features' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'structureTypes' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			case 'features' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			default :
				return parent::decode($property, $value);

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

	public function whereTitle(...$data): NoteModel {
		return $this->where('title', ...$data);
	}

	public function whereContent(...$data): NoteModel {
		return $this->where('content', ...$data);
	}

	public function whereButtonLabel(...$data): NoteModel {
		return $this->where('buttonLabel', ...$data);
	}

	public function whereButtonPath(...$data): NoteModel {
		return $this->where('buttonPath', ...$data);
	}

	public function whereStructureTypes(...$data): NoteModel {
		return $this->where('structureTypes', ...$data);
	}

	public function whereFeatures(...$data): NoteModel {
		return $this->where('features', ...$data);
	}

	public function whereStructures(...$data): NoteModel {
		return $this->where('structures', ...$data);
	}

	public function whereUsers(...$data): NoteModel {
		return $this->where('users', ...$data);
	}

	public function whereViews(...$data): NoteModel {
		return $this->where('views', ...$data);
	}

	public function whereReads(...$data): NoteModel {
		return $this->where('reads', ...$data);
	}

	public function whereCreatedBy(...$data): NoteModel {
		return $this->where('createdBy', ...$data);
	}

	public function whereCreatedAt(...$data): NoteModel {
		return $this->where('createdAt', ...$data);
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

	protected string $module = 'communication\Note';

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