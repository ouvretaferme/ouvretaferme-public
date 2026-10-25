<?php
namespace learning;

abstract class SubthemeElement extends \Element {

	use \FilterElement;

	private static ?SubthemeModel $model = NULL;

	public static function getSelection(): array {
		return Subtheme::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): SubthemeModel {
		if(self::$model === NULL) {
			self::$model = new SubthemeModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Subtheme::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class SubthemeModel extends \ModuleModel {

	protected string $module = 'learning\Subtheme';
	protected string $package = 'learning';
	protected string $table = 'learningSubtheme';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'theme' => ['element32', 'learning\Theme', 'cast' => 'element'],
			'name' => ['text8', 'min' => 1, 'max' => 50, 'cast' => 'string'],
			'position' => ['int8', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'theme', 'name', 'position', 'createdAt'
		]);

		$this->propertiesToModule += [
			'theme' => 'learning\Theme',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['theme']
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

	public function select(...$fields): SubthemeModel {
		return parent::select(...$fields);
	}

	public function where(...$data): SubthemeModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): SubthemeModel {
		return $this->where('id', ...$data);
	}

	public function whereTheme(...$data): SubthemeModel {
		return $this->where('theme', ...$data);
	}

	public function whereName(...$data): SubthemeModel {
		return $this->where('name', ...$data);
	}

	public function wherePosition(...$data): SubthemeModel {
		return $this->where('position', ...$data);
	}

	public function whereCreatedAt(...$data): SubthemeModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class SubthemeCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Subtheme {

		$e = new Subtheme();

		if(empty($id)) {
			Subtheme::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Subtheme::getSelection();
		}

		if(Subtheme::model()
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
			$properties = Subtheme::getSelection();
		}

		if($sort !== NULL) {
			Subtheme::model()->sort($sort);
		}

		return Subtheme::model()
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

	public static function getNewElement(array $properties = []): Subtheme {

		return new Subtheme($properties);

	}

	public static function create(Subtheme $e): void {

		Subtheme::model()->insert($e);

	}

	public static function update(Subtheme $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Subtheme::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Subtheme $e, array $properties): void {

		self::$affected = Subtheme::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Subtheme $e): void {

		$e->expects(['id']);

		self::$affected = Subtheme::model()->delete($e);

	}

}


class SubthemePage extends \ModulePage {

	protected string $module = 'learning\Subtheme';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? SubthemeLib::getPropertiesCreate(),
		   $propertiesUpdate ?? SubthemeLib::getPropertiesUpdate()
		);
	}

}
?>