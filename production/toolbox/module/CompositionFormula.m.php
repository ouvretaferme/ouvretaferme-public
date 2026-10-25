<?php
namespace toolbox;

abstract class CompositionFormulaElement extends \Element {

	use \FilterElement;

	private static ?CompositionFormulaModel $model = NULL;

	public static function getSelection(): array {
		return CompositionFormula::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): CompositionFormulaModel {
		if(self::$model === NULL) {
			self::$model = new CompositionFormulaModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('CompositionFormula::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class CompositionFormulaModel extends \ModuleModel {

	protected string $module = 'toolbox\CompositionFormula';
	protected string $package = 'toolbox';
	protected string $table = 'toolboxCompositionFormula';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'plan' => ['element32', 'toolbox\CompositionPlan', 'cast' => 'element'],
			'base' => ['element32', 'toolbox\CompositionFormula', 'null' => TRUE, 'cast' => 'element'],
			'name' => ['text8', 'min' => 1, 'max' => NULL, 'collate' => 'general', 'cast' => 'string'],
			'price' => ['decimal', 'digits' => 8, 'decimal' => 2, 'min' => 0.01, 'max' => 999999.99, 'cast' => 'float'],
			'baskets' => ['int16', 'min' => 1, 'max' => NULL, 'cast' => 'int'],
			'gap' => ['int8', 'min' => 2, 'max' => 20, 'cast' => 'int'],
			'linesMin' => ['int8', 'min' => 1, 'max' => 12, 'cast' => 'int'],
			'linesMax' => ['int8', 'min' => 1, 'max' => 12, 'cast' => 'int'],
			'version' => ['int16', 'cast' => 'int'],
			'position' => ['int8', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'plan', 'base', 'name', 'price', 'baskets', 'gap', 'linesMin', 'linesMax', 'version', 'position'
		]);

		$this->propertiesToModule += [
			'plan' => 'toolbox\CompositionPlan',
			'base' => 'toolbox\CompositionFormula',
		];

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['plan', 'name']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'gap' :
				return 5;

			case 'linesMin' :
				return 4;

			case 'linesMax' :
				return 8;

			case 'version' :
				return 0;

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function select(...$fields): CompositionFormulaModel {
		return parent::select(...$fields);
	}

	public function where(...$data): CompositionFormulaModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): CompositionFormulaModel {
		return $this->where('id', ...$data);
	}

	public function wherePlan(...$data): CompositionFormulaModel {
		return $this->where('plan', ...$data);
	}

	public function whereBase(...$data): CompositionFormulaModel {
		return $this->where('base', ...$data);
	}

	public function whereName(...$data): CompositionFormulaModel {
		return $this->where('name', ...$data);
	}

	public function wherePrice(...$data): CompositionFormulaModel {
		return $this->where('price', ...$data);
	}

	public function whereBaskets(...$data): CompositionFormulaModel {
		return $this->where('baskets', ...$data);
	}

	public function whereGap(...$data): CompositionFormulaModel {
		return $this->where('gap', ...$data);
	}

	public function whereLinesMin(...$data): CompositionFormulaModel {
		return $this->where('linesMin', ...$data);
	}

	public function whereLinesMax(...$data): CompositionFormulaModel {
		return $this->where('linesMax', ...$data);
	}

	public function whereVersion(...$data): CompositionFormulaModel {
		return $this->where('version', ...$data);
	}

	public function wherePosition(...$data): CompositionFormulaModel {
		return $this->where('position', ...$data);
	}


}


abstract class CompositionFormulaCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): CompositionFormula {

		$e = new CompositionFormula();

		if(empty($id)) {
			CompositionFormula::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = CompositionFormula::getSelection();
		}

		if(CompositionFormula::model()
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
			$properties = CompositionFormula::getSelection();
		}

		if($sort !== NULL) {
			CompositionFormula::model()->sort($sort);
		}

		return CompositionFormula::model()
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

	public static function getNewElement(array $properties = []): CompositionFormula {

		return new CompositionFormula($properties);

	}

	public static function create(CompositionFormula $e): void {

		CompositionFormula::model()->insert($e);

	}

	public static function update(CompositionFormula $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = CompositionFormula::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, CompositionFormula $e, array $properties): void {

		self::$affected = CompositionFormula::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(CompositionFormula $e): void {

		$e->expects(['id']);

		self::$affected = CompositionFormula::model()->delete($e);

	}

}


class CompositionFormulaPage extends \ModulePage {

	protected string $module = 'toolbox\CompositionFormula';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? CompositionFormulaLib::getPropertiesCreate(),
		   $propertiesUpdate ?? CompositionFormulaLib::getPropertiesUpdate()
		);
	}

}
?>