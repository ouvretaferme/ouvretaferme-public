<?php
namespace structure;

abstract class SectionElement extends \Element {

	use \FilterElement;

	private static ?SectionModel $model = NULL;

	const OPEN = 'open';
	const CLOSED = 'closed';

	public static function getSelection(): array {
		return Section::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): SectionModel {
		if(self::$model === NULL) {
			self::$model = new SectionModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Section::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class SectionModel extends \ModuleModel {

	protected string $module = 'structure\Section';
	protected string $package = 'structure';
	protected string $table = 'structureSection';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'name' => ['text8', 'min' => 1, 'max' => NULL, 'cast' => 'string'],
			'users' => ['json', 'cast' => 'array'],
			'status' => ['enum', [\structure\Section::OPEN, \structure\Section::CLOSED], 'cast' => 'enum'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'name', 'users', 'status'
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

			case 'users' :
				return [];

			case 'status' :
				return Section::OPEN;

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'users' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			case 'status' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'users' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			default :
				return parent::decode($property, $value);

		}

	}

	public function select(...$fields): SectionModel {
		return parent::select(...$fields);
	}

	public function where(...$data): SectionModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): SectionModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): SectionModel {
		return $this->where('structure', ...$data);
	}

	public function whereName(...$data): SectionModel {
		return $this->where('name', ...$data);
	}

	public function whereUsers(...$data): SectionModel {
		return $this->where('users', ...$data);
	}

	public function whereStatus(...$data): SectionModel {
		return $this->where('status', ...$data);
	}


}


abstract class SectionCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Section {

		$e = new Section();

		if(empty($id)) {
			Section::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Section::getSelection();
		}

		if(Section::model()
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
			$properties = Section::getSelection();
		}

		if($sort !== NULL) {
			Section::model()->sort($sort);
		}

		return Section::model()
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

	public static function getNewElement(array $properties = []): Section {

		return new Section($properties);

	}

	public static function create(Section $e): void {

		Section::model()->insert($e);

	}

	public static function update(Section $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Section::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Section $e, array $properties): void {

		self::$affected = Section::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Section $e): void {

		$e->expects(['id']);

		self::$affected = Section::model()->delete($e);

	}

}


class SectionPage extends \ModulePage {

	protected string $module = 'structure\Section';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? SectionLib::getPropertiesCreate(),
		   $propertiesUpdate ?? SectionLib::getPropertiesUpdate()
		);
	}

}
?>