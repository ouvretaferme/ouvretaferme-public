<?php
namespace hr;

abstract class TeammateElement extends \Element {

	use \FilterElement;

	private static ?TeammateModel $model = NULL;

	public static function getSelection(): array {
		return Teammate::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): TeammateModel {
		if(self::$model === NULL) {
			self::$model = new TeammateModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Teammate::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class TeammateModel extends \ModuleModel {

	protected string $module = 'hr\Teammate';
	protected string $package = 'hr';
	protected string $table = 'hrTeammate';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'user' => ['element32', 'user\User', 'cast' => 'element'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'user', 'createdAt'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
			'user' => 'user\User',
		];

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['structure', 'user']
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

	public function select(...$fields): TeammateModel {
		return parent::select(...$fields);
	}

	public function where(...$data): TeammateModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): TeammateModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): TeammateModel {
		return $this->where('structure', ...$data);
	}

	public function whereUser(...$data): TeammateModel {
		return $this->where('user', ...$data);
	}

	public function whereCreatedAt(...$data): TeammateModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class TeammateCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Teammate {

		$e = new Teammate();

		if(empty($id)) {
			Teammate::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Teammate::getSelection();
		}

		if(Teammate::model()
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
			$properties = Teammate::getSelection();
		}

		if($sort !== NULL) {
			Teammate::model()->sort($sort);
		}

		return Teammate::model()
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

	public static function getNewElement(array $properties = []): Teammate {

		return new Teammate($properties);

	}

	public static function create(Teammate $e): void {

		Teammate::model()->insert($e);

	}

	public static function update(Teammate $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Teammate::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Teammate $e, array $properties): void {

		self::$affected = Teammate::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Teammate $e): void {

		$e->expects(['id']);

		self::$affected = Teammate::model()->delete($e);

	}

}


class TeammatePage extends \ModulePage {

	protected string $module = 'hr\Teammate';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? TeammateLib::getPropertiesCreate(),
		   $propertiesUpdate ?? TeammateLib::getPropertiesUpdate()
		);
	}

}
?>