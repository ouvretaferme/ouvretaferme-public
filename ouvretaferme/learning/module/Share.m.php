<?php
namespace learning;

abstract class ShareElement extends \Element {

	use \FilterElement;

	private static ?ShareModel $model = NULL;

	const SECTION = 'section';
	const MEMBERS = 'members';

	public static function getSelection(): array {
		return Share::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): ShareModel {
		if(self::$model === NULL) {
			self::$model = new ShareModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Share::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class ShareModel extends \ModuleModel {

	protected string $module = 'learning\Share';
	protected string $package = 'learning';
	protected string $table = 'learningShare';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'course' => ['element32', 'learning\Course', 'cast' => 'element'],
			'audience' => ['enum', [\learning\Share::SECTION, \learning\Share::MEMBERS], 'cast' => 'enum'],
			'section' => ['element32', 'structure\Section', 'null' => TRUE, 'cast' => 'element'],
			'opensAt' => ['date', 'cast' => 'string'],
			'dueAt' => ['date', 'null' => TRUE, 'cast' => 'string'],
			'announcedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'createdAt' => ['datetime', 'cast' => 'string'],
			'createdBy' => ['element32', 'user\User', 'cast' => 'element'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'course', 'audience', 'section', 'opensAt', 'dueAt', 'announcedAt', 'createdAt', 'createdBy'
		]);

		$this->propertiesToModule += [
			'course' => 'learning\Course',
			'section' => 'structure\Section',
			'createdBy' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['section'],
			['announcedAt', 'opensAt']
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['course', 'section']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'audience' :
				return Share::SECTION;

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

			case 'audience' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): ShareModel {
		return parent::select(...$fields);
	}

	public function where(...$data): ShareModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): ShareModel {
		return $this->where('id', ...$data);
	}

	public function whereCourse(...$data): ShareModel {
		return $this->where('course', ...$data);
	}

	public function whereAudience(...$data): ShareModel {
		return $this->where('audience', ...$data);
	}

	public function whereSection(...$data): ShareModel {
		return $this->where('section', ...$data);
	}

	public function whereOpensAt(...$data): ShareModel {
		return $this->where('opensAt', ...$data);
	}

	public function whereDueAt(...$data): ShareModel {
		return $this->where('dueAt', ...$data);
	}

	public function whereAnnouncedAt(...$data): ShareModel {
		return $this->where('announcedAt', ...$data);
	}

	public function whereCreatedAt(...$data): ShareModel {
		return $this->where('createdAt', ...$data);
	}

	public function whereCreatedBy(...$data): ShareModel {
		return $this->where('createdBy', ...$data);
	}


}


abstract class ShareCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Share {

		$e = new Share();

		if(empty($id)) {
			Share::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Share::getSelection();
		}

		if(Share::model()
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
			$properties = Share::getSelection();
		}

		if($sort !== NULL) {
			Share::model()->sort($sort);
		}

		return Share::model()
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

	public static function getNewElement(array $properties = []): Share {

		return new Share($properties);

	}

	public static function create(Share $e): void {

		Share::model()->insert($e);

	}

	public static function update(Share $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Share::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Share $e, array $properties): void {

		self::$affected = Share::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Share $e): void {

		$e->expects(['id']);

		self::$affected = Share::model()->delete($e);

	}

}


class SharePage extends \ModulePage {

	protected string $module = 'learning\Share';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? ShareLib::getPropertiesCreate(),
		   $propertiesUpdate ?? ShareLib::getPropertiesUpdate()
		);
	}

}
?>