<?php
namespace aid;

abstract class AidTagElement extends \Element {

	use \FilterElement;

	private static ?AidTagModel $model = NULL;

	public static function getSelection(): array {
		return AidTag::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): AidTagModel {
		if(self::$model === NULL) {
			self::$model = new AidTagModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('AidTag::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class AidTagModel extends \ModuleModel {

	protected string $module = 'aid\AidTag';
	protected string $package = 'aid';
	protected string $table = 'aidAidTag';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'aid' => ['element32', 'aid\Aid', 'cast' => 'element'],
			'tag' => ['element32', 'aid\Tag', 'cast' => 'element'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'aid', 'tag'
		]);

		$this->propertiesToModule += [
			'aid' => 'aid\Aid',
			'tag' => 'aid\Tag',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['tag']
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['aid', 'tag']
		]);

	}

	public function select(...$fields): AidTagModel {
		return parent::select(...$fields);
	}

	public function where(...$data): AidTagModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): AidTagModel {
		return $this->where('id', ...$data);
	}

	public function whereAid(...$data): AidTagModel {
		return $this->where('aid', ...$data);
	}

	public function whereTag(...$data): AidTagModel {
		return $this->where('tag', ...$data);
	}


}


abstract class AidTagCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): AidTag {

		$e = new AidTag();

		if(empty($id)) {
			AidTag::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = AidTag::getSelection();
		}

		if(AidTag::model()
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
			$properties = AidTag::getSelection();
		}

		if($sort !== NULL) {
			AidTag::model()->sort($sort);
		}

		return AidTag::model()
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

	public static function getNewElement(array $properties = []): AidTag {

		return new AidTag($properties);

	}

	public static function create(AidTag $e): void {

		AidTag::model()->insert($e);

	}

	public static function update(AidTag $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = AidTag::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, AidTag $e, array $properties): void {

		self::$affected = AidTag::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(AidTag $e): void {

		$e->expects(['id']);

		self::$affected = AidTag::model()->delete($e);

	}

}


class AidTagPage extends \ModulePage {

	protected string $module = 'aid\AidTag';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? AidTagLib::getPropertiesCreate(),
		   $propertiesUpdate ?? AidTagLib::getPropertiesUpdate()
		);
	}

}
?>