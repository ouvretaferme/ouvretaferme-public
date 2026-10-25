<?php
namespace learning;

abstract class ThemeElement extends \Element {

	use \FilterElement;

	private static ?ThemeModel $model = NULL;

	public static function getSelection(): array {
		return Theme::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): ThemeModel {
		if(self::$model === NULL) {
			self::$model = new ThemeModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Theme::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class ThemeModel extends \ModuleModel {

	protected string $module = 'learning\Theme';
	protected string $package = 'learning';
	protected string $table = 'learningTheme';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'name' => ['text8', 'min' => 1, 'max' => 50, 'cast' => 'string'],
			'icon' => ['text8', 'min' => 1, 'max' => 50, 'charset' => 'ascii', 'cast' => 'string'],
			'color' => ['color', 'cast' => 'string'],
			'position' => ['int8', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'name', 'icon', 'color', 'position', 'createdAt'
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

	public function select(...$fields): ThemeModel {
		return parent::select(...$fields);
	}

	public function where(...$data): ThemeModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): ThemeModel {
		return $this->where('id', ...$data);
	}

	public function whereName(...$data): ThemeModel {
		return $this->where('name', ...$data);
	}

	public function whereIcon(...$data): ThemeModel {
		return $this->where('icon', ...$data);
	}

	public function whereColor(...$data): ThemeModel {
		return $this->where('color', ...$data);
	}

	public function wherePosition(...$data): ThemeModel {
		return $this->where('position', ...$data);
	}

	public function whereCreatedAt(...$data): ThemeModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class ThemeCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Theme {

		$e = new Theme();

		if(empty($id)) {
			Theme::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Theme::getSelection();
		}

		if(Theme::model()
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
			$properties = Theme::getSelection();
		}

		if($sort !== NULL) {
			Theme::model()->sort($sort);
		}

		return Theme::model()
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

	public static function getNewElement(array $properties = []): Theme {

		return new Theme($properties);

	}

	public static function create(Theme $e): void {

		Theme::model()->insert($e);

	}

	public static function update(Theme $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Theme::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Theme $e, array $properties): void {

		self::$affected = Theme::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Theme $e): void {

		$e->expects(['id']);

		self::$affected = Theme::model()->delete($e);

	}

}


class ThemePage extends \ModulePage {

	protected string $module = 'learning\Theme';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? ThemeLib::getPropertiesCreate(),
		   $propertiesUpdate ?? ThemeLib::getPropertiesUpdate()
		);
	}

}
?>