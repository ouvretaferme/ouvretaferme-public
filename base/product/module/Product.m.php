<?php
namespace product;

abstract class ProductElement extends \Element {

	use \FilterElement;

	private static ?ProductModel $model = NULL;

	const COMPOSITION = 'composition';
	const PLANT = 'plant';
	const ANIMAL = 'animal';
	const FOOD = 'food';
	const PRODUCT = 'product';
	const OTHER = 'other';
	const SERVICE = 'service';
	const SHIPPING = 'shipping';

	const PRODUCTION = 'production';
	const RESALE = 'resale';

	const PUBLIC = 'public';
	const PRIVATE = 'private';

	const NO = 'no';
	const YES = 'yes';
	const CONVERSION = 'conversion';

	const DEMETER = 1;
	const NATURE_PROGRES = 2;
	const BIO_COHERENCE = 4;
	const IGP = 8;
	const AOP = 16;
	const STG = 32;
	const LABEL_ROUGE = 64;

	const SHARED = 'shared';
	const VARIANT = 'variant';

	const ACTIVE = 'active';
	const INACTIVE = 'inactive';

	const SPECIAL = 'special';
	const DELETED = 'deleted';

	public static function getSelection(): array {
		return Product::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): ProductModel {
		if(self::$model === NULL) {
			self::$model = new ProductModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Product::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class ProductModel extends \ModuleModel {

	protected string $module = 'product\Product';
	protected string $package = 'product';
	protected string $table = 'product';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'name' => ['text8', 'min' => 1, 'max' => NULL, 'collate' => 'general', 'cast' => 'string'],
			'additional' => ['text16', 'min' => 1, 'max' => NULL, 'collate' => 'general', 'null' => TRUE, 'cast' => 'string'],
			'description' => ['editor24', 'null' => TRUE, 'cast' => 'string'],
			'vignette' => ['textFixed', 'min' => 30, 'max' => 30, 'charset' => 'ascii', 'null' => TRUE, 'cast' => 'string'],
			'profile' => ['enum', [\product\Product::COMPOSITION, \product\Product::PLANT, \product\Product::ANIMAL, \product\Product::FOOD, \product\Product::PRODUCT, \product\Product::OTHER, \product\Product::SERVICE, \product\Product::SHIPPING], 'cast' => 'enum'],
			'distribution' => ['enum', [\product\Product::PRODUCTION, \product\Product::RESALE], 'null' => TRUE, 'cast' => 'enum'],
			'category' => ['element32', 'product\Category', 'null' => TRUE, 'cast' => 'element'],
			'variantOf' => ['element32', 'product\Product', 'null' => TRUE, 'cast' => 'element'],
			'variantPosition' => ['int8', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'variants' => ['int16', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'reference' => ['text8', 'min' => 1, 'max' => NULL, 'charset' => 'ascii', 'null' => TRUE, 'cast' => 'string'],
			'plant' => ['element32', 'plant\Plant', 'null' => TRUE, 'cast' => 'element'],
			'variety' => ['text8', 'min' => 1, 'max' => NULL, 'collate' => 'general', 'null' => TRUE, 'cast' => 'string'],
			'frozen' => ['bool', 'null' => TRUE, 'cast' => 'bool'],
			'packaging' => ['text8', 'min' => 1, 'max' => NULL, 'null' => TRUE, 'cast' => 'string'],
			'allergens' => ['text8', 'min' => 1, 'max' => NULL, 'null' => TRUE, 'cast' => 'string'],
			'ingredients' => ['text16', 'min' => 1, 'max' => NULL, 'null' => TRUE, 'cast' => 'string'],
			'compositionVisibility' => ['enum', [\product\Product::PUBLIC, \product\Product::PRIVATE], 'null' => TRUE, 'cast' => 'enum'],
			'origin' => ['text8', 'min' => 1, 'max' => NULL, 'collate' => 'general', 'null' => TRUE, 'cast' => 'string'],
			'preparationDays' => ['int8', 'min' => 0, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'preparationTime' => ['time', 'null' => TRUE, 'cast' => 'string'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'unit' => ['element32', 'product\Unit', 'null' => TRUE, 'cast' => 'element'],
			'private' => ['bool', 'cast' => 'bool'],
			'privatePrice' => ['decimal', 'digits' => 10, 'decimal' => 4, 'min' => -999999.9999, 'max' => 999999.9999, 'null' => TRUE, 'cast' => 'float'],
			'privatePriceInitial' => ['decimal', 'digits' => 10, 'decimal' => 4, 'min' => -999999.9999, 'max' => 999999.9999, 'null' => TRUE, 'cast' => 'float'],
			'privateStep' => ['decimal', 'digits' => 8, 'decimal' => 4, 'min' => 0.0001, 'max' => 9999.9999, 'null' => TRUE, 'cast' => 'float'],
			'privatePriceSteps' => ['json', 'cast' => 'array'],
			'pro' => ['bool', 'cast' => 'bool'],
			'proPrice' => ['decimal', 'digits' => 10, 'decimal' => 4, 'min' => -999999.9999, 'max' => 999999.9999, 'null' => TRUE, 'cast' => 'float'],
			'proPriceInitial' => ['decimal', 'digits' => 10, 'decimal' => 4, 'min' => -999999.9999, 'max' => 999999.9999, 'null' => TRUE, 'cast' => 'float'],
			'proPackaging' => ['float32', 'min' => 0.01, 'max' => NULL, 'null' => TRUE, 'cast' => 'float'],
			'proStep' => ['decimal', 'digits' => 8, 'decimal' => 4, 'min' => 0.0001, 'max' => 9999.9999, 'null' => TRUE, 'cast' => 'float'],
			'proPriceSteps' => ['json', 'cast' => 'array'],
			'vat' => ['int16', 'min' => 1, 'max' => NULL, 'cast' => 'int'],
			'organic' => ['enum', [\product\Product::NO, \product\Product::YES, \product\Product::CONVERSION], 'cast' => 'enum'],
			'qualities' => ['set', [\product\Product::DEMETER, \product\Product::NATURE_PROGRES, \product\Product::BIO_COHERENCE, \product\Product::IGP, \product\Product::AOP, \product\Product::STG, \product\Product::LABEL_ROUGE], 'cast' => 'set'],
			'discountable' => ['bool', 'cast' => 'bool'],
			'priceMode' => ['enum', [\product\Product::SHARED, \product\Product::VARIANT], 'cast' => 'enum'],
			'stock' => ['decimal', 'digits' => 10, 'decimal' => 4, 'min' => 0.0, 'max' => 999999.9999, 'null' => TRUE, 'cast' => 'float'],
			'stockStatus' => ['enum', [\product\Product::ACTIVE, \product\Product::INACTIVE], 'cast' => 'enum'],
			'stockLast' => ['element32', 'product\Stock', 'null' => TRUE, 'cast' => 'element'],
			'stockUpdatedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'duplicateOf' => ['element32', 'product\Product', 'null' => TRUE, 'cast' => 'element'],
			'createdAt' => ['datetime', 'cast' => 'string'],
			'privateAccountNumber' => ['text32', 'null' => TRUE, 'cast' => 'string'],
			'proAccountNumber' => ['text32', 'null' => TRUE, 'cast' => 'string'],
			'status' => ['enum', [\product\Product::ACTIVE, \product\Product::INACTIVE, \product\Product::SPECIAL, \product\Product::DELETED], 'cast' => 'enum'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'name', 'additional', 'description', 'vignette', 'profile', 'distribution', 'category', 'variantOf', 'variantPosition', 'variants', 'reference', 'plant', 'variety', 'frozen', 'packaging', 'allergens', 'ingredients', 'compositionVisibility', 'origin', 'preparationDays', 'preparationTime', 'structure', 'unit', 'private', 'privatePrice', 'privatePriceInitial', 'privateStep', 'privatePriceSteps', 'pro', 'proPrice', 'proPriceInitial', 'proPackaging', 'proStep', 'proPriceSteps', 'vat', 'organic', 'qualities', 'discountable', 'priceMode', 'stock', 'stockStatus', 'stockLast', 'stockUpdatedAt', 'duplicateOf', 'createdAt', 'privateAccountNumber', 'proAccountNumber', 'status'
		]);

		$this->propertiesToModule += [
			'category' => 'product\Category',
			'variantOf' => 'product\Product',
			'plant' => 'plant\Plant',
			'structure' => 'structure\Structure',
			'unit' => 'product\Unit',
			'stockLast' => 'product\Stock',
			'duplicateOf' => 'product\Product',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['structure'],
			['plant'],
			['variantOf', 'variantPosition']
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['structure', 'reference']
		]);

		$this->searchConstraints = array_merge($this->searchConstraints, [
			['name', 'origin', 'variety']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'variantPosition' :
				return 0;

			case 'variants' :
				return 0;

			case 'unit' :
				return \product\ProductSetting::UNIT_DEFAULT_ID;

			case 'private' :
				return TRUE;

			case 'privatePriceSteps' :
				return [];

			case 'pro' :
				return TRUE;

			case 'proPriceSteps' :
				return [];

			case 'discountable' :
				return TRUE;

			case 'priceMode' :
				return Product::VARIANT;

			case 'stockStatus' :
				return Product::INACTIVE;

			case 'createdAt' :
				return new \Sql('NOW()');

			case 'status' :
				return Product::ACTIVE;

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'profile' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'distribution' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'compositionVisibility' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'privatePriceSteps' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			case 'proPriceSteps' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			case 'organic' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'priceMode' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'stockStatus' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'status' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'privatePriceSteps' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			case 'proPriceSteps' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			default :
				return parent::decode($property, $value);

		}

	}

	public function select(...$fields): ProductModel {
		return parent::select(...$fields);
	}

	public function where(...$data): ProductModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): ProductModel {
		return $this->where('id', ...$data);
	}

	public function whereName(...$data): ProductModel {
		return $this->where('name', ...$data);
	}

	public function whereAdditional(...$data): ProductModel {
		return $this->where('additional', ...$data);
	}

	public function whereDescription(...$data): ProductModel {
		return $this->where('description', ...$data);
	}

	public function whereVignette(...$data): ProductModel {
		return $this->where('vignette', ...$data);
	}

	public function whereProfile(...$data): ProductModel {
		return $this->where('profile', ...$data);
	}

	public function whereDistribution(...$data): ProductModel {
		return $this->where('distribution', ...$data);
	}

	public function whereCategory(...$data): ProductModel {
		return $this->where('category', ...$data);
	}

	public function whereVariantOf(...$data): ProductModel {
		return $this->where('variantOf', ...$data);
	}

	public function whereVariantPosition(...$data): ProductModel {
		return $this->where('variantPosition', ...$data);
	}

	public function whereVariants(...$data): ProductModel {
		return $this->where('variants', ...$data);
	}

	public function whereReference(...$data): ProductModel {
		return $this->where('reference', ...$data);
	}

	public function wherePlant(...$data): ProductModel {
		return $this->where('plant', ...$data);
	}

	public function whereVariety(...$data): ProductModel {
		return $this->where('variety', ...$data);
	}

	public function whereFrozen(...$data): ProductModel {
		return $this->where('frozen', ...$data);
	}

	public function wherePackaging(...$data): ProductModel {
		return $this->where('packaging', ...$data);
	}

	public function whereAllergens(...$data): ProductModel {
		return $this->where('allergens', ...$data);
	}

	public function whereIngredients(...$data): ProductModel {
		return $this->where('ingredients', ...$data);
	}

	public function whereCompositionVisibility(...$data): ProductModel {
		return $this->where('compositionVisibility', ...$data);
	}

	public function whereOrigin(...$data): ProductModel {
		return $this->where('origin', ...$data);
	}

	public function wherePreparationDays(...$data): ProductModel {
		return $this->where('preparationDays', ...$data);
	}

	public function wherePreparationTime(...$data): ProductModel {
		return $this->where('preparationTime', ...$data);
	}

	public function whereStructure(...$data): ProductModel {
		return $this->where('structure', ...$data);
	}

	public function whereUnit(...$data): ProductModel {
		return $this->where('unit', ...$data);
	}

	public function wherePrivate(...$data): ProductModel {
		return $this->where('private', ...$data);
	}

	public function wherePrivatePrice(...$data): ProductModel {
		return $this->where('privatePrice', ...$data);
	}

	public function wherePrivatePriceInitial(...$data): ProductModel {
		return $this->where('privatePriceInitial', ...$data);
	}

	public function wherePrivateStep(...$data): ProductModel {
		return $this->where('privateStep', ...$data);
	}

	public function wherePrivatePriceSteps(...$data): ProductModel {
		return $this->where('privatePriceSteps', ...$data);
	}

	public function wherePro(...$data): ProductModel {
		return $this->where('pro', ...$data);
	}

	public function whereProPrice(...$data): ProductModel {
		return $this->where('proPrice', ...$data);
	}

	public function whereProPriceInitial(...$data): ProductModel {
		return $this->where('proPriceInitial', ...$data);
	}

	public function whereProPackaging(...$data): ProductModel {
		return $this->where('proPackaging', ...$data);
	}

	public function whereProStep(...$data): ProductModel {
		return $this->where('proStep', ...$data);
	}

	public function whereProPriceSteps(...$data): ProductModel {
		return $this->where('proPriceSteps', ...$data);
	}

	public function whereVat(...$data): ProductModel {
		return $this->where('vat', ...$data);
	}

	public function whereOrganic(...$data): ProductModel {
		return $this->where('organic', ...$data);
	}

	public function whereQualities(...$data): ProductModel {
		return $this->where('qualities', ...$data);
	}

	public function whereDiscountable(...$data): ProductModel {
		return $this->where('discountable', ...$data);
	}

	public function wherePriceMode(...$data): ProductModel {
		return $this->where('priceMode', ...$data);
	}

	public function whereStock(...$data): ProductModel {
		return $this->where('stock', ...$data);
	}

	public function whereStockStatus(...$data): ProductModel {
		return $this->where('stockStatus', ...$data);
	}

	public function whereStockLast(...$data): ProductModel {
		return $this->where('stockLast', ...$data);
	}

	public function whereStockUpdatedAt(...$data): ProductModel {
		return $this->where('stockUpdatedAt', ...$data);
	}

	public function whereDuplicateOf(...$data): ProductModel {
		return $this->where('duplicateOf', ...$data);
	}

	public function whereCreatedAt(...$data): ProductModel {
		return $this->where('createdAt', ...$data);
	}

	public function wherePrivateAccountNumber(...$data): ProductModel {
		return $this->where('privateAccountNumber', ...$data);
	}

	public function whereProAccountNumber(...$data): ProductModel {
		return $this->where('proAccountNumber', ...$data);
	}

	public function whereStatus(...$data): ProductModel {
		return $this->where('status', ...$data);
	}


}


abstract class ProductCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Product {

		$e = new Product();

		if(empty($id)) {
			Product::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Product::getSelection();
		}

		if(Product::model()
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
			$properties = Product::getSelection();
		}

		if($sort !== NULL) {
			Product::model()->sort($sort);
		}

		return Product::model()
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

	public static function getNewElement(array $properties = []): Product {

		return new Product($properties);

	}

	public static function create(Product $e): void {

		Product::model()->insert($e);

	}

	public static function update(Product $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Product::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Product $e, array $properties): void {

		self::$affected = Product::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Product $e): void {

		$e->expects(['id']);

		self::$affected = Product::model()->delete($e);

	}

}


class ProductPage extends \ModulePage {

	protected string $module = 'product\Product';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? ProductLib::getPropertiesCreate(),
		   $propertiesUpdate ?? ProductLib::getPropertiesUpdate()
		);
	}

}
?>