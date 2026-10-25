<?php
namespace note;

abstract class NotebookElement extends \Element {

	use \FilterElement;

	private static ?NotebookModel $model = NULL;

	public static function getSelection(): array {
		return Notebook::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): NotebookModel {
		if(self::$model === NULL) {
			self::$model = new NotebookModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Notebook::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class NotebookModel extends \ModuleModel {

	protected string $module = 'note\Notebook';
	protected string $package = 'note';
	protected string $table = 'noteNotebook';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'name' => ['text8', 'min' => 1, 'max' => 50, 'cast' => 'string'],
			'position' => ['int8', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'name', 'position', 'createdAt'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
		];

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['structure', 'name']
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

	public function select(...$fields): NotebookModel {
		return parent::select(...$fields);
	}

	public function where(...$data): NotebookModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): NotebookModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): NotebookModel {
		return $this->where('structure', ...$data);
	}

	public function whereName(...$data): NotebookModel {
		return $this->where('name', ...$data);
	}

	public function wherePosition(...$data): NotebookModel {
		return $this->where('position', ...$data);
	}

	public function whereCreatedAt(...$data): NotebookModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class NotebookCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Notebook {

		$e = new Notebook();

		if(empty($id)) {
			Notebook::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Notebook::getSelection();
		}

		if(Notebook::model()
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
			$properties = Notebook::getSelection();
		}

		if($sort !== NULL) {
			Notebook::model()->sort($sort);
		}

		return Notebook::model()
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

	public static function getNewElement(array $properties = []): Notebook {

		return new Notebook($properties);

	}

	public static function create(Notebook $e): void {

		Notebook::model()->insert($e);

	}

	public static function update(Notebook $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Notebook::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Notebook $e, array $properties): void {

		self::$affected = Notebook::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Notebook $e): void {

		$e->expects(['id']);

		self::$affected = Notebook::model()->delete($e);

	}

}


class NotebookPage extends \ModulePage {

	protected string $module = 'note\Notebook';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? NotebookLib::getPropertiesCreate(),
		   $propertiesUpdate ?? NotebookLib::getPropertiesUpdate()
		);
	}

}
?>