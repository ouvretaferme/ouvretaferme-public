<?php
namespace learning;

abstract class ChapterElement extends \Element {

	use \FilterElement;

	private static ?ChapterModel $model = NULL;

	public static function getSelection(): array {
		return Chapter::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): ChapterModel {
		if(self::$model === NULL) {
			self::$model = new ChapterModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Chapter::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class ChapterModel extends \ModuleModel {

	protected string $module = 'learning\Chapter';
	protected string $package = 'learning';
	protected string $table = 'learningChapter';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'course' => ['element32', 'learning\Course', 'cast' => 'element'],
			'phase' => ['element32', 'learning\Phase', 'null' => TRUE, 'cast' => 'element'],
			'name' => ['text8', 'min' => 1, 'max' => 100, 'cast' => 'string'],
			'summary' => ['text8', 'min' => 1, 'max' => 250, 'null' => TRUE, 'cast' => 'string'],
			'position' => ['int8', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'course', 'phase', 'name', 'summary', 'position', 'createdAt'
		]);

		$this->propertiesToModule += [
			'course' => 'learning\Course',
			'phase' => 'learning\Phase',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['course'],
			['phase']
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

	public function select(...$fields): ChapterModel {
		return parent::select(...$fields);
	}

	public function where(...$data): ChapterModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): ChapterModel {
		return $this->where('id', ...$data);
	}

	public function whereCourse(...$data): ChapterModel {
		return $this->where('course', ...$data);
	}

	public function wherePhase(...$data): ChapterModel {
		return $this->where('phase', ...$data);
	}

	public function whereName(...$data): ChapterModel {
		return $this->where('name', ...$data);
	}

	public function whereSummary(...$data): ChapterModel {
		return $this->where('summary', ...$data);
	}

	public function wherePosition(...$data): ChapterModel {
		return $this->where('position', ...$data);
	}

	public function whereCreatedAt(...$data): ChapterModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class ChapterCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Chapter {

		$e = new Chapter();

		if(empty($id)) {
			Chapter::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Chapter::getSelection();
		}

		if(Chapter::model()
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
			$properties = Chapter::getSelection();
		}

		if($sort !== NULL) {
			Chapter::model()->sort($sort);
		}

		return Chapter::model()
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

	public static function getNewElement(array $properties = []): Chapter {

		return new Chapter($properties);

	}

	public static function create(Chapter $e): void {

		Chapter::model()->insert($e);

	}

	public static function update(Chapter $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Chapter::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Chapter $e, array $properties): void {

		self::$affected = Chapter::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Chapter $e): void {

		$e->expects(['id']);

		self::$affected = Chapter::model()->delete($e);

	}

}


class ChapterPage extends \ModulePage {

	protected string $module = 'learning\Chapter';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? ChapterLib::getPropertiesCreate(),
		   $propertiesUpdate ?? ChapterLib::getPropertiesUpdate()
		);
	}

}
?>