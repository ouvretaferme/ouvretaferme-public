<?php
namespace aid;

abstract class AidPieceElement extends \Element {

	use \FilterElement;

	private static ?AidPieceModel $model = NULL;

	const APPLICATION = 'application';
	const PAYMENT = 'payment';

	public static function getSelection(): array {
		return AidPiece::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): AidPieceModel {
		if(self::$model === NULL) {
			self::$model = new AidPieceModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('AidPiece::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class AidPieceModel extends \ModuleModel {

	protected string $module = 'aid\AidPiece';
	protected string $package = 'aid';
	protected string $table = 'aidAidPiece';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'aid' => ['element32', 'aid\Aid', 'cast' => 'element'],
			'moment' => ['enum', [\aid\AidPiece::APPLICATION, \aid\AidPiece::PAYMENT], 'cast' => 'enum'],
			'piece' => ['element32', 'aid\Piece', 'null' => TRUE, 'cast' => 'element'],
			'name' => ['text8', 'min' => 1, 'max' => 250, 'null' => TRUE, 'cast' => 'string'],
			'note' => ['text8', 'min' => 1, 'max' => 250, 'null' => TRUE, 'cast' => 'string'],
			'url' => ['url', 'null' => TRUE, 'cast' => 'string'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'aid', 'moment', 'piece', 'name', 'note', 'url', 'createdAt'
		]);

		$this->propertiesToModule += [
			'aid' => 'aid\Aid',
			'piece' => 'aid\Piece',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['aid'],
			['piece']
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

			case 'moment' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): AidPieceModel {
		return parent::select(...$fields);
	}

	public function where(...$data): AidPieceModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): AidPieceModel {
		return $this->where('id', ...$data);
	}

	public function whereAid(...$data): AidPieceModel {
		return $this->where('aid', ...$data);
	}

	public function whereMoment(...$data): AidPieceModel {
		return $this->where('moment', ...$data);
	}

	public function wherePiece(...$data): AidPieceModel {
		return $this->where('piece', ...$data);
	}

	public function whereName(...$data): AidPieceModel {
		return $this->where('name', ...$data);
	}

	public function whereNote(...$data): AidPieceModel {
		return $this->where('note', ...$data);
	}

	public function whereUrl(...$data): AidPieceModel {
		return $this->where('url', ...$data);
	}

	public function whereCreatedAt(...$data): AidPieceModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class AidPieceCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): AidPiece {

		$e = new AidPiece();

		if(empty($id)) {
			AidPiece::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = AidPiece::getSelection();
		}

		if(AidPiece::model()
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
			$properties = AidPiece::getSelection();
		}

		if($sort !== NULL) {
			AidPiece::model()->sort($sort);
		}

		return AidPiece::model()
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

	public static function getNewElement(array $properties = []): AidPiece {

		return new AidPiece($properties);

	}

	public static function create(AidPiece $e): void {

		AidPiece::model()->insert($e);

	}

	public static function update(AidPiece $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = AidPiece::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, AidPiece $e, array $properties): void {

		self::$affected = AidPiece::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(AidPiece $e): void {

		$e->expects(['id']);

		self::$affected = AidPiece::model()->delete($e);

	}

}


class AidPiecePage extends \ModulePage {

	protected string $module = 'aid\AidPiece';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? AidPieceLib::getPropertiesCreate(),
		   $propertiesUpdate ?? AidPieceLib::getPropertiesUpdate()
		);
	}

}
?>