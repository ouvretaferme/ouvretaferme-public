<?php
namespace dashboard;

abstract class PreferenceElement extends \Element {

	use \FilterElement;

	private static ?PreferenceModel $model = NULL;

	public static function getSelection(): array {
		return Preference::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): PreferenceModel {
		if(self::$model === NULL) {
			self::$model = new PreferenceModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Preference::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class PreferenceModel extends \ModuleModel {

	protected string $module = 'dashboard\Preference';
	protected string $package = 'dashboard';
	protected string $table = 'dashboardPreference';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'user' => ['element32', 'user\User', 'cast' => 'element'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'name' => ['text8', 'min' => 1, 'max' => 50, 'charset' => 'ascii', 'cast' => 'string'],
			'position' => ['int8', 'min' => 0, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'hidden' => ['bool', 'cast' => 'bool'],
			'updatedAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'user', 'structure', 'name', 'position', 'hidden', 'updatedAt'
		]);

		$this->propertiesToModule += [
			'user' => 'user\User',
			'structure' => 'structure\Structure',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['structure']
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['user', 'structure', 'name']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'hidden' :
				return FALSE;

			case 'updatedAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function select(...$fields): PreferenceModel {
		return parent::select(...$fields);
	}

	public function where(...$data): PreferenceModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): PreferenceModel {
		return $this->where('id', ...$data);
	}

	public function whereUser(...$data): PreferenceModel {
		return $this->where('user', ...$data);
	}

	public function whereStructure(...$data): PreferenceModel {
		return $this->where('structure', ...$data);
	}

	public function whereName(...$data): PreferenceModel {
		return $this->where('name', ...$data);
	}

	public function wherePosition(...$data): PreferenceModel {
		return $this->where('position', ...$data);
	}

	public function whereHidden(...$data): PreferenceModel {
		return $this->where('hidden', ...$data);
	}

	public function whereUpdatedAt(...$data): PreferenceModel {
		return $this->where('updatedAt', ...$data);
	}


}


abstract class PreferenceCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Preference {

		$e = new Preference();

		if(empty($id)) {
			Preference::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Preference::getSelection();
		}

		if(Preference::model()
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
			$properties = Preference::getSelection();
		}

		if($sort !== NULL) {
			Preference::model()->sort($sort);
		}

		return Preference::model()
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

	public static function getNewElement(array $properties = []): Preference {

		return new Preference($properties);

	}

	public static function create(Preference $e): void {

		Preference::model()->insert($e);

	}

	public static function update(Preference $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Preference::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Preference $e, array $properties): void {

		self::$affected = Preference::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Preference $e): void {

		$e->expects(['id']);

		self::$affected = Preference::model()->delete($e);

	}

}


class PreferencePage extends \ModulePage {

	protected string $module = 'dashboard\Preference';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? PreferenceLib::getPropertiesCreate(),
		   $propertiesUpdate ?? PreferenceLib::getPropertiesUpdate()
		);
	}

}
?>