<?php
namespace learning;

abstract class AnnouncementElement extends \Element {

	use \FilterElement;

	private static ?AnnouncementModel $model = NULL;

	public static function getSelection(): array {
		return Announcement::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): AnnouncementModel {
		if(self::$model === NULL) {
			self::$model = new AnnouncementModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Announcement::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class AnnouncementModel extends \ModuleModel {

	protected string $module = 'learning\Announcement';
	protected string $package = 'learning';
	protected string $table = 'learningAnnouncement';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'course' => ['element32', 'learning\Course', 'cast' => 'element'],
			'user' => ['element32', 'user\User', 'cast' => 'element'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'course', 'user', 'createdAt'
		]);

		$this->propertiesToModule += [
			'course' => 'learning\Course',
			'user' => 'user\User',
		];

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['course', 'user']
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

	public function select(...$fields): AnnouncementModel {
		return parent::select(...$fields);
	}

	public function where(...$data): AnnouncementModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): AnnouncementModel {
		return $this->where('id', ...$data);
	}

	public function whereCourse(...$data): AnnouncementModel {
		return $this->where('course', ...$data);
	}

	public function whereUser(...$data): AnnouncementModel {
		return $this->where('user', ...$data);
	}

	public function whereCreatedAt(...$data): AnnouncementModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class AnnouncementCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Announcement {

		$e = new Announcement();

		if(empty($id)) {
			Announcement::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Announcement::getSelection();
		}

		if(Announcement::model()
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
			$properties = Announcement::getSelection();
		}

		if($sort !== NULL) {
			Announcement::model()->sort($sort);
		}

		return Announcement::model()
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

	public static function getNewElement(array $properties = []): Announcement {

		return new Announcement($properties);

	}

	public static function create(Announcement $e): void {

		Announcement::model()->insert($e);

	}

	public static function update(Announcement $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Announcement::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Announcement $e, array $properties): void {

		self::$affected = Announcement::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Announcement $e): void {

		$e->expects(['id']);

		self::$affected = Announcement::model()->delete($e);

	}

}


class AnnouncementPage extends \ModulePage {

	protected string $module = 'learning\Announcement';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? AnnouncementLib::getPropertiesCreate(),
		   $propertiesUpdate ?? AnnouncementLib::getPropertiesUpdate()
		);
	}

}
?>