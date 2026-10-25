<?php
namespace toolbox;

abstract class FavoriteElement extends \Element {

	use \FilterElement;

	private static ?FavoriteModel $model = NULL;

	const RAIN = 'rain';
	const BASKET = 'basket';
	const TREATMENT = 'treatment';
	const DENSITY = 'density';
	const CATALOG = 'catalog';
	const MAINTENANCE = 'maintenance';
	const WATER = 'water';
	const FERTILIZATION = 'fertilization';
	const AID = 'aid';

	public static function getSelection(): array {
		return Favorite::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): FavoriteModel {
		if(self::$model === NULL) {
			self::$model = new FavoriteModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Favorite::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class FavoriteModel extends \ModuleModel {

	protected string $module = 'toolbox\Favorite';
	protected string $package = 'toolbox';
	protected string $table = 'toolboxFavorite';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'user' => ['element32', 'user\User', 'cast' => 'element'],
			'tool' => ['enum', [\toolbox\Favorite::RAIN, \toolbox\Favorite::BASKET, \toolbox\Favorite::TREATMENT, \toolbox\Favorite::DENSITY, \toolbox\Favorite::CATALOG, \toolbox\Favorite::MAINTENANCE, \toolbox\Favorite::WATER, \toolbox\Favorite::FERTILIZATION, \toolbox\Favorite::AID], 'cast' => 'enum'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'user', 'tool', 'createdAt'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
			'user' => 'user\User',
		];

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['structure', 'user', 'tool']
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

	public function encode(string $property, $value) {

		switch($property) {

			case 'tool' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): FavoriteModel {
		return parent::select(...$fields);
	}

	public function where(...$data): FavoriteModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): FavoriteModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): FavoriteModel {
		return $this->where('structure', ...$data);
	}

	public function whereUser(...$data): FavoriteModel {
		return $this->where('user', ...$data);
	}

	public function whereTool(...$data): FavoriteModel {
		return $this->where('tool', ...$data);
	}

	public function whereCreatedAt(...$data): FavoriteModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class FavoriteCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Favorite {

		$e = new Favorite();

		if(empty($id)) {
			Favorite::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Favorite::getSelection();
		}

		if(Favorite::model()
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
			$properties = Favorite::getSelection();
		}

		if($sort !== NULL) {
			Favorite::model()->sort($sort);
		}

		return Favorite::model()
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

	public static function getNewElement(array $properties = []): Favorite {

		return new Favorite($properties);

	}

	public static function create(Favorite $e): void {

		Favorite::model()->insert($e);

	}

	public static function update(Favorite $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Favorite::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Favorite $e, array $properties): void {

		self::$affected = Favorite::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Favorite $e): void {

		$e->expects(['id']);

		self::$affected = Favorite::model()->delete($e);

	}

}


class FavoritePage extends \ModulePage {

	protected string $module = 'toolbox\Favorite';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? FavoriteLib::getPropertiesCreate(),
		   $propertiesUpdate ?? FavoriteLib::getPropertiesUpdate()
		);
	}

}
?>