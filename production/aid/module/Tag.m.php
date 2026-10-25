<?php
namespace aid;

abstract class TagElement extends \Element {

	use \FilterElement;

	private static ?TagModel $model = NULL;

	const INVEST = 1;
	const INSTALL = 2;
	const ADVICE = 4;
	const CRISIS = 8;
	const IRRIGATION = 16;
	const ENERGY = 32;
	const ENVIRONMENT = 64;
	const ORGANIC = 128;
	const SELL = 256;
	const LAND = 512;
	const EMPLOYMENT = 1024;

	public static function getSelection(): array {
		return Tag::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): TagModel {
		if(self::$model === NULL) {
			self::$model = new TagModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Tag::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class TagModel extends \ModuleModel {

	protected string $module = 'aid\Tag';
	protected string $package = 'aid';
	protected string $table = 'aidTag';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'fqn' => ['fqn', 'unique' => TRUE, 'cast' => 'string'],
			'name' => ['text8', 'min' => 1, 'max' => 100, 'collate' => 'general', 'cast' => 'string'],
			'projects' => ['set', [\aid\Tag::INVEST, \aid\Tag::INSTALL, \aid\Tag::ADVICE, \aid\Tag::CRISIS, \aid\Tag::IRRIGATION, \aid\Tag::ENERGY, \aid\Tag::ENVIRONMENT, \aid\Tag::ORGANIC, \aid\Tag::SELL, \aid\Tag::LAND, \aid\Tag::EMPLOYMENT], 'cast' => 'set'],
			'position' => ['int8', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'fqn', 'name', 'projects', 'position', 'createdAt'
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['fqn']
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

	public function select(...$fields): TagModel {
		return parent::select(...$fields);
	}

	public function where(...$data): TagModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): TagModel {
		return $this->where('id', ...$data);
	}

	public function whereFqn(...$data): TagModel {
		return $this->where('fqn', ...$data);
	}

	public function whereName(...$data): TagModel {
		return $this->where('name', ...$data);
	}

	public function whereProjects(...$data): TagModel {
		return $this->where('projects', ...$data);
	}

	public function wherePosition(...$data): TagModel {
		return $this->where('position', ...$data);
	}

	public function whereCreatedAt(...$data): TagModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class TagCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Tag {

		$e = new Tag();

		if(empty($id)) {
			Tag::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Tag::getSelection();
		}

		if(Tag::model()
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
			$properties = Tag::getSelection();
		}

		if($sort !== NULL) {
			Tag::model()->sort($sort);
		}

		return Tag::model()
			->select($properties)
			->whereId('IN', $ids)
			->getCollection(NULL, NULL, $index);

	}

	public static function getByFqn(string $fqn, array $properties = []): Tag {

		$e = new Tag();

		if(empty($fqn)) {
			Tag::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Tag::getSelection();
		}

		if(Tag::model()
			->select($properties)
			->whereFqn($fqn)
			->get($e) === FALSE) {
				$e->setGhost($fqn);
		}

		return $e;

	}

	public static function getByFqns(array $fqns, array $properties = []): \Collection {

		if(empty($fqns)) {
			Tag::model()->reset();
			return new \Collection();
		}

		if($properties === []) {
			$properties = Tag::getSelection();
		}

		return Tag::model()
			->select($properties)
			->whereFqn('IN', $fqns)
			->getCollection(NULL, NULL, 'fqn');

	}

	public static function getCache(mixed $key, \Closure $callback): mixed {
		self::$cache[$key] ??= $callback();
		return self::$cache[$key];
	}

	public static function getAffected(): ?int {
		return self::$affected;
	}

	public static function getNewElement(array $properties = []): Tag {

		return new Tag($properties);

	}

	public static function create(Tag $e): void {

		Tag::model()->insert($e);

	}

	public static function update(Tag $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Tag::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Tag $e, array $properties): void {

		self::$affected = Tag::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Tag $e): void {

		$e->expects(['id']);

		self::$affected = Tag::model()->delete($e);

	}

}


class TagPage extends \ModulePage {

	protected string $module = 'aid\Tag';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? TagLib::getPropertiesCreate(),
		   $propertiesUpdate ?? TagLib::getPropertiesUpdate()
		);
	}

}
?>