<?php
namespace plant;

abstract class TagElement extends \Element {

	use \FilterElement;

	private static ?TagModel $model = NULL;

	const VEGETABLE = 'vegetable';
	const FRUIT = 'fruit';
	const SMALL_FRUIT = 'small-fruit';
	const FLOWER = 'flower';
	const AROMATIC = 'aromatic';
	const MUSHROOM = 'mushroom';
	const FIELD = 'field';

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

	protected string $module = 'plant\Tag';
	protected string $package = 'plant';
	protected string $table = 'plantTag';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'fqn' => ['fqn', 'unique' => TRUE, 'cast' => 'string'],
			'usage' => ['enum', [\plant\Tag::VEGETABLE, \plant\Tag::FRUIT, \plant\Tag::SMALL_FRUIT, \plant\Tag::FLOWER, \plant\Tag::AROMATIC, \plant\Tag::MUSHROOM, \plant\Tag::FIELD], 'cast' => 'enum'],
			'name' => ['text8', 'min' => 1, 'max' => NULL, 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'fqn', 'usage', 'name'
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['fqn']
		]);

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'usage' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

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

	public function whereUsage(...$data): TagModel {
		return $this->where('usage', ...$data);
	}

	public function whereName(...$data): TagModel {
		return $this->where('name', ...$data);
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

	protected string $module = 'plant\Tag';

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