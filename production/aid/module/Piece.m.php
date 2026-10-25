<?php
namespace aid;

abstract class PieceElement extends \Element {

	use \FilterElement;

	private static ?PieceModel $model = NULL;

	public static function getSelection(): array {
		return Piece::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): PieceModel {
		if(self::$model === NULL) {
			self::$model = new PieceModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Piece::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class PieceModel extends \ModuleModel {

	protected string $module = 'aid\Piece';
	protected string $package = 'aid';
	protected string $table = 'aidPiece';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'fqn' => ['fqn', 'unique' => TRUE, 'cast' => 'string'],
			'name' => ['text8', 'min' => 1, 'max' => 100, 'collate' => 'general', 'cast' => 'string'],
			'position' => ['int8', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'fqn', 'name', 'position', 'createdAt'
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['fqn']
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

	public function select(...$fields): PieceModel {
		return parent::select(...$fields);
	}

	public function where(...$data): PieceModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): PieceModel {
		return $this->where('id', ...$data);
	}

	public function whereFqn(...$data): PieceModel {
		return $this->where('fqn', ...$data);
	}

	public function whereName(...$data): PieceModel {
		return $this->where('name', ...$data);
	}

	public function wherePosition(...$data): PieceModel {
		return $this->where('position', ...$data);
	}

	public function whereCreatedAt(...$data): PieceModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class PieceCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Piece {

		$e = new Piece();

		if(empty($id)) {
			Piece::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Piece::getSelection();
		}

		if(Piece::model()
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
			$properties = Piece::getSelection();
		}

		if($sort !== NULL) {
			Piece::model()->sort($sort);
		}

		return Piece::model()
			->select($properties)
			->whereId('IN', $ids)
			->getCollection(NULL, NULL, $index);

	}

	public static function getByFqn(string $fqn, array $properties = []): Piece {

		$e = new Piece();

		if(empty($fqn)) {
			Piece::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Piece::getSelection();
		}

		if(Piece::model()
			->select($properties)
			->whereFqn($fqn)
			->get($e) === FALSE) {
				$e->setGhost($fqn);
		}

		return $e;

	}

	public static function getByFqns(array $fqns, array $properties = []): \Collection {

		if(empty($fqns)) {
			Piece::model()->reset();
			return new \Collection();
		}

		if($properties === []) {
			$properties = Piece::getSelection();
		}

		return Piece::model()
			->select($properties)
			->whereFqn('IN', $fqns)
			->getCollection(NULL, NULL, 'fqn');

	}

	public static function getCache(mixed $key, \Closure $callback): mixed {
		self::$cache[$key] ??= $callback();
		return self::$cache[$key];
	}

	public static function getAffected(): ?int {
		return self::$affected;
	}

	public static function getNewElement(array $properties = []): Piece {

		return new Piece($properties);

	}

	public static function create(Piece $e): void {

		Piece::model()->insert($e);

	}

	public static function update(Piece $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Piece::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Piece $e, array $properties): void {

		self::$affected = Piece::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Piece $e): void {

		$e->expects(['id']);

		self::$affected = Piece::model()->delete($e);

	}

}


class PiecePage extends \ModulePage {

	protected string $module = 'aid\Piece';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? PieceLib::getPropertiesCreate(),
		   $propertiesUpdate ?? PieceLib::getPropertiesUpdate()
		);
	}

}
?>