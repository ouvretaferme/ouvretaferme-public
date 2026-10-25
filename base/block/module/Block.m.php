<?php
namespace block;

abstract class BlockElement extends \Element {

	use \FilterElement;

	private static ?BlockModel $model = NULL;

	public static function getSelection(): array {
		return Block::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): BlockModel {
		if(self::$model === NULL) {
			self::$model = new BlockModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Block::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class BlockModel extends \ModuleModel {

	protected string $module = 'block\Block';
	protected string $package = 'block';
	protected string $table = 'block';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'owner' => ['text8', 'min' => 1, 'max' => 50, 'charset' => 'ascii', 'cast' => 'string'],
			'reference' => ['int32', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'parent' => ['element32', 'block\Block', 'null' => TRUE, 'cast' => 'element'],
			'type' => ['text8', 'min' => 1, 'max' => 50, 'charset' => 'ascii', 'cast' => 'string'],
			'variant' => ['text8', 'min' => 1, 'max' => 30, 'charset' => 'ascii', 'null' => TRUE, 'cast' => 'string'],
			'title' => ['text8', 'min' => 1, 'max' => 250, 'null' => TRUE, 'cast' => 'string'],
			'content' => ['editor24', 'null' => TRUE, 'cast' => 'string'],
			'media' => ['textFixed', 'min' => 30, 'max' => 30, 'charset' => 'ascii', 'null' => TRUE, 'cast' => 'string'],
			'url' => ['url', 'null' => TRUE, 'cast' => 'string'],
			'settings' => ['json', 'cast' => 'array'],
			'position' => ['int16', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'createdAt' => ['datetime', 'cast' => 'string'],
			'updatedAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'owner', 'reference', 'parent', 'type', 'variant', 'title', 'content', 'media', 'url', 'settings', 'position', 'createdAt', 'updatedAt'
		]);

		$this->propertiesToModule += [
			'parent' => 'block\Block',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['reference', 'owner']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'settings' :
				return [];

			case 'createdAt' :
				return new \Sql('NOW()');

			case 'updatedAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'settings' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'settings' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			default :
				return parent::decode($property, $value);

		}

	}

	public function select(...$fields): BlockModel {
		return parent::select(...$fields);
	}

	public function where(...$data): BlockModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): BlockModel {
		return $this->where('id', ...$data);
	}

	public function whereOwner(...$data): BlockModel {
		return $this->where('owner', ...$data);
	}

	public function whereReference(...$data): BlockModel {
		return $this->where('reference', ...$data);
	}

	public function whereParent(...$data): BlockModel {
		return $this->where('parent', ...$data);
	}

	public function whereType(...$data): BlockModel {
		return $this->where('type', ...$data);
	}

	public function whereVariant(...$data): BlockModel {
		return $this->where('variant', ...$data);
	}

	public function whereTitle(...$data): BlockModel {
		return $this->where('title', ...$data);
	}

	public function whereContent(...$data): BlockModel {
		return $this->where('content', ...$data);
	}

	public function whereMedia(...$data): BlockModel {
		return $this->where('media', ...$data);
	}

	public function whereUrl(...$data): BlockModel {
		return $this->where('url', ...$data);
	}

	public function whereSettings(...$data): BlockModel {
		return $this->where('settings', ...$data);
	}

	public function wherePosition(...$data): BlockModel {
		return $this->where('position', ...$data);
	}

	public function whereCreatedAt(...$data): BlockModel {
		return $this->where('createdAt', ...$data);
	}

	public function whereUpdatedAt(...$data): BlockModel {
		return $this->where('updatedAt', ...$data);
	}


}


abstract class BlockCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Block {

		$e = new Block();

		if(empty($id)) {
			Block::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Block::getSelection();
		}

		if(Block::model()
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
			$properties = Block::getSelection();
		}

		if($sort !== NULL) {
			Block::model()->sort($sort);
		}

		return Block::model()
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

	public static function getNewElement(array $properties = []): Block {

		return new Block($properties);

	}

	public static function create(Block $e): void {

		Block::model()->insert($e);

	}

	public static function update(Block $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Block::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Block $e, array $properties): void {

		self::$affected = Block::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Block $e): void {

		$e->expects(['id']);

		self::$affected = Block::model()->delete($e);

	}

}


class BlockPage extends \ModulePage {

	protected string $module = 'block\Block';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? BlockLib::getPropertiesCreate(),
		   $propertiesUpdate ?? BlockLib::getPropertiesUpdate()
		);
	}

}
?>