<?php
namespace plant;

abstract class VarietyRatingElement extends \Element {

	use \FilterElement;

	private static ?VarietyRatingModel $model = NULL;

	public static function getSelection(): array {
		return VarietyRating::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): VarietyRatingModel {
		if(self::$model === NULL) {
			self::$model = new VarietyRatingModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('VarietyRating::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class VarietyRatingModel extends \ModuleModel {

	protected string $module = 'plant\VarietyRating';
	protected string $package = 'plant';
	protected string $table = 'plantVarietyRating';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'variety' => ['element32', 'plant\Variety', 'cast' => 'element'],
			'criterion' => ['element32', 'plant\Criterion', 'cast' => 'element'],
			'value' => ['int32', 'min' => 1, 'max' => 5, 'cast' => 'int'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'variety', 'criterion', 'value'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
			'variety' => 'plant\Variety',
			'criterion' => 'plant\Criterion',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['criterion'],
			['variety']
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['structure', 'variety', 'criterion']
		]);

	}

	public function select(...$fields): VarietyRatingModel {
		return parent::select(...$fields);
	}

	public function where(...$data): VarietyRatingModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): VarietyRatingModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): VarietyRatingModel {
		return $this->where('structure', ...$data);
	}

	public function whereVariety(...$data): VarietyRatingModel {
		return $this->where('variety', ...$data);
	}

	public function whereCriterion(...$data): VarietyRatingModel {
		return $this->where('criterion', ...$data);
	}

	public function whereValue(...$data): VarietyRatingModel {
		return $this->where('value', ...$data);
	}


}


abstract class VarietyRatingCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): VarietyRating {

		$e = new VarietyRating();

		if(empty($id)) {
			VarietyRating::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = VarietyRating::getSelection();
		}

		if(VarietyRating::model()
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
			$properties = VarietyRating::getSelection();
		}

		if($sort !== NULL) {
			VarietyRating::model()->sort($sort);
		}

		return VarietyRating::model()
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

	public static function getNewElement(array $properties = []): VarietyRating {

		return new VarietyRating($properties);

	}

	public static function create(VarietyRating $e): void {

		VarietyRating::model()->insert($e);

	}

	public static function update(VarietyRating $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = VarietyRating::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, VarietyRating $e, array $properties): void {

		self::$affected = VarietyRating::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(VarietyRating $e): void {

		$e->expects(['id']);

		self::$affected = VarietyRating::model()->delete($e);

	}

}


class VarietyRatingPage extends \ModulePage {

	protected string $module = 'plant\VarietyRating';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? VarietyRatingAiLib::getPropertiesCreate(),
		   $propertiesUpdate ?? VarietyRatingAiLib::getPropertiesUpdate()
		);
	}

}
?>