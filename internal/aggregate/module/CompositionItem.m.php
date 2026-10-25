<?php
namespace aggregate;

abstract class CompositionItemElement extends \Element {

	use \FilterElement;

	private static ?CompositionItemModel $model = NULL;

	public static function getSelection(): array {
		return CompositionItem::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): CompositionItemModel {
		if(self::$model === NULL) {
			self::$model = new CompositionItemModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('CompositionItem::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class CompositionItemModel extends \ModuleModel {

	protected string $module = 'aggregate\CompositionItem';
	protected string $package = 'aggregate';
	protected string $table = 'aggregateCompositionItem';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'composition' => ['element32', 'aggregate\Composition', 'cast' => 'element'],
			'plant' => ['element32', 'plant\Plant', 'cast' => 'element'],
			'quantity' => ['decimal', 'digits' => 8, 'decimal' => 2, 'min' => -999999.99, 'max' => 999999.99, 'null' => TRUE, 'cast' => 'float'],
			'unit' => ['element32', 'product\Unit', 'null' => TRUE, 'cast' => 'element'],
			'online' => ['bool', 'cast' => 'bool'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'composition', 'plant', 'quantity', 'unit', 'online'
		]);

		$this->propertiesToModule += [
			'composition' => 'aggregate\Composition',
			'plant' => 'plant\Plant',
			'unit' => 'product\Unit',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['composition'],
			['plant', 'online'],
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

	public function select(...$fields): CompositionItemModel {
		return parent::select(...$fields);
	}

	public function where(...$data): CompositionItemModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): CompositionItemModel {
		return $this->where('id', ...$data);
	}

	public function whereComposition(...$data): CompositionItemModel {
		return $this->where('composition', ...$data);
	}

	public function wherePlant(...$data): CompositionItemModel {
		return $this->where('plant', ...$data);
	}

	public function whereQuantity(...$data): CompositionItemModel {
		return $this->where('quantity', ...$data);
	}

	public function whereUnit(...$data): CompositionItemModel {
		return $this->where('unit', ...$data);
	}

	public function whereOnline(...$data): CompositionItemModel {
		return $this->where('online', ...$data);
	}


}


abstract class CompositionItemCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): CompositionItem {

		$e = new CompositionItem();

		if(empty($id)) {
			CompositionItem::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = CompositionItem::getSelection();
		}

		if(CompositionItem::model()
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
			$properties = CompositionItem::getSelection();
		}

		if($sort !== NULL) {
			CompositionItem::model()->sort($sort);
		}

		return CompositionItem::model()
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

	public static function getNewElement(array $properties = []): CompositionItem {

		return new CompositionItem($properties);

	}

	public static function create(CompositionItem $e): void {

		CompositionItem::model()->insert($e);

	}

	public static function update(CompositionItem $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = CompositionItem::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, CompositionItem $e, array $properties): void {

		self::$affected = CompositionItem::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(CompositionItem $e): void {

		$e->expects(['id']);

		self::$affected = CompositionItem::model()->delete($e);

	}

}


class CompositionItemPage extends \ModulePage {

	protected string $module = 'aggregate\CompositionItem';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? CompositionItemAiLib::getPropertiesCreate(),
		   $propertiesUpdate ?? CompositionItemAiLib::getPropertiesUpdate()
		);
	}

}
?>