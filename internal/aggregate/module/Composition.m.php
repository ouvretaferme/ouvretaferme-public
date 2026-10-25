<?php
namespace aggregate;

abstract class CompositionElement extends \Element {

	use \FilterElement;

	private static ?CompositionModel $model = NULL;

	public static function getSelection(): array {
		return Composition::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): CompositionModel {
		if(self::$model === NULL) {
			self::$model = new CompositionModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Composition::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class CompositionModel extends \ModuleModel {

	protected string $module = 'aggregate\Composition';
	protected string $package = 'aggregate';
	protected string $table = 'aggregateComposition';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'contributor' => ['int32', 'min' => 1, 'max' => NULL, 'cast' => 'int'],
			'composedProduct' => ['int32', 'min' => 1, 'max' => NULL, 'cast' => 'int'],
			'year' => ['int16', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'week' => ['int8', 'min' => 1, 'max' => 52, 'cast' => 'int'],
			'altitude' => ['int16', 'min' => 0, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'value' => ['decimal', 'digits' => 8, 'decimal' => 2, 'min' => -999999.99, 'max' => 999999.99, 'null' => TRUE, 'cast' => 'float'],
			'lines' => ['int8', 'min' => 1, 'max' => NULL, 'cast' => 'int'],
			'online' => ['bool', 'cast' => 'bool'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'contributor', 'composedProduct', 'year', 'week', 'altitude', 'value', 'lines', 'online'
		]);

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['online']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'online' :
				return FALSE;

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function select(...$fields): CompositionModel {
		return parent::select(...$fields);
	}

	public function where(...$data): CompositionModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): CompositionModel {
		return $this->where('id', ...$data);
	}

	public function whereContributor(...$data): CompositionModel {
		return $this->where('contributor', ...$data);
	}

	public function whereComposedProduct(...$data): CompositionModel {
		return $this->where('composedProduct', ...$data);
	}

	public function whereYear(...$data): CompositionModel {
		return $this->where('year', ...$data);
	}

	public function whereWeek(...$data): CompositionModel {
		return $this->where('week', ...$data);
	}

	public function whereAltitude(...$data): CompositionModel {
		return $this->where('altitude', ...$data);
	}

	public function whereValue(...$data): CompositionModel {
		return $this->where('value', ...$data);
	}

	public function whereLines(...$data): CompositionModel {
		return $this->where('lines', ...$data);
	}

	public function whereOnline(...$data): CompositionModel {
		return $this->where('online', ...$data);
	}


}


abstract class CompositionCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Composition {

		$e = new Composition();

		if(empty($id)) {
			Composition::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Composition::getSelection();
		}

		if(Composition::model()
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
			$properties = Composition::getSelection();
		}

		if($sort !== NULL) {
			Composition::model()->sort($sort);
		}

		return Composition::model()
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

	public static function getNewElement(array $properties = []): Composition {

		return new Composition($properties);

	}

	public static function create(Composition $e): void {

		Composition::model()->insert($e);

	}

	public static function update(Composition $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Composition::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Composition $e, array $properties): void {

		self::$affected = Composition::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Composition $e): void {

		$e->expects(['id']);

		self::$affected = Composition::model()->delete($e);

	}

}


class CompositionPage extends \ModulePage {

	protected string $module = 'aggregate\Composition';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? CompositionAiLib::getPropertiesCreate(),
		   $propertiesUpdate ?? CompositionAiLib::getPropertiesUpdate()
		);
	}

}
?>