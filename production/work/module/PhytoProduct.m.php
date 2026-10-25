<?php
namespace work;

abstract class PhytoProductElement extends \Element {

	use \FilterElement;

	private static ?PhytoProductModel $model = NULL;

	const AUTHORIZED = 'authorized';
	const WITHDRAWN = 'withdrawn';

	public static function getSelection(): array {
		return PhytoProduct::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): PhytoProductModel {
		if(self::$model === NULL) {
			self::$model = new PhytoProductModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('PhytoProduct::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class PhytoProductModel extends \ModuleModel {

	protected string $module = 'work\PhytoProduct';
	protected string $package = 'work';
	protected string $table = 'workPhytoProduct';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'amm' => ['text8', 'min' => 1, 'max' => 50, 'charset' => 'ascii', 'unique' => TRUE, 'cast' => 'string'],
			'name' => ['text8', 'min' => 1, 'max' => NULL, 'collate' => 'general', 'cast' => 'string'],
			'otherNames' => ['json', 'cast' => 'array'],
			'functions' => ['text8', 'null' => TRUE, 'cast' => 'string'],
			'formulation' => ['text8', 'null' => TRUE, 'cast' => 'string'],
			'organic' => ['bool', 'cast' => 'bool'],
			'amateur' => ['bool', 'cast' => 'bool'],
			'substances' => ['json', 'cast' => 'array'],
			'status' => ['enum', [\work\PhytoProduct::AUTHORIZED, \work\PhytoProduct::WITHDRAWN], 'cast' => 'enum'],
			'usableUntil' => ['date', 'null' => TRUE, 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'amm', 'name', 'otherNames', 'functions', 'formulation', 'organic', 'amateur', 'substances', 'status', 'usableUntil'
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['amm']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'otherNames' :
				return [];

			case 'organic' :
				return FALSE;

			case 'amateur' :
				return FALSE;

			case 'substances' :
				return [];

			case 'status' :
				return PhytoProduct::AUTHORIZED;

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'otherNames' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			case 'substances' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			case 'status' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'otherNames' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			case 'substances' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			default :
				return parent::decode($property, $value);

		}

	}

	public function select(...$fields): PhytoProductModel {
		return parent::select(...$fields);
	}

	public function where(...$data): PhytoProductModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): PhytoProductModel {
		return $this->where('id', ...$data);
	}

	public function whereAmm(...$data): PhytoProductModel {
		return $this->where('amm', ...$data);
	}

	public function whereName(...$data): PhytoProductModel {
		return $this->where('name', ...$data);
	}

	public function whereOtherNames(...$data): PhytoProductModel {
		return $this->where('otherNames', ...$data);
	}

	public function whereFunctions(...$data): PhytoProductModel {
		return $this->where('functions', ...$data);
	}

	public function whereFormulation(...$data): PhytoProductModel {
		return $this->where('formulation', ...$data);
	}

	public function whereOrganic(...$data): PhytoProductModel {
		return $this->where('organic', ...$data);
	}

	public function whereAmateur(...$data): PhytoProductModel {
		return $this->where('amateur', ...$data);
	}

	public function whereSubstances(...$data): PhytoProductModel {
		return $this->where('substances', ...$data);
	}

	public function whereStatus(...$data): PhytoProductModel {
		return $this->where('status', ...$data);
	}

	public function whereUsableUntil(...$data): PhytoProductModel {
		return $this->where('usableUntil', ...$data);
	}


}


abstract class PhytoProductCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): PhytoProduct {

		$e = new PhytoProduct();

		if(empty($id)) {
			PhytoProduct::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = PhytoProduct::getSelection();
		}

		if(PhytoProduct::model()
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
			$properties = PhytoProduct::getSelection();
		}

		if($sort !== NULL) {
			PhytoProduct::model()->sort($sort);
		}

		return PhytoProduct::model()
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

	public static function getNewElement(array $properties = []): PhytoProduct {

		return new PhytoProduct($properties);

	}

	public static function create(PhytoProduct $e): void {

		PhytoProduct::model()->insert($e);

	}

	public static function update(PhytoProduct $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = PhytoProduct::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, PhytoProduct $e, array $properties): void {

		self::$affected = PhytoProduct::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(PhytoProduct $e): void {

		$e->expects(['id']);

		self::$affected = PhytoProduct::model()->delete($e);

	}

}


class PhytoProductPage extends \ModulePage {

	protected string $module = 'work\PhytoProduct';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? PhytoProductLib::getPropertiesCreate(),
		   $propertiesUpdate ?? PhytoProductLib::getPropertiesUpdate()
		);
	}

}
?>