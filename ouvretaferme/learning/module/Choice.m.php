<?php
namespace learning;

abstract class ChoiceElement extends \Element {

	use \FilterElement;

	private static ?ChoiceModel $model = NULL;

	public static function getSelection(): array {
		return Choice::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): ChoiceModel {
		if(self::$model === NULL) {
			self::$model = new ChoiceModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Choice::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class ChoiceModel extends \ModuleModel {

	protected string $module = 'learning\Choice';
	protected string $package = 'learning';
	protected string $table = 'learningChoice';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'question' => ['element32', 'learning\Question', 'cast' => 'element'],
			'label' => ['text8', 'min' => 1, 'max' => 250, 'null' => TRUE, 'cast' => 'string'],
			'image' => ['textFixed', 'min' => 20, 'max' => 20, 'charset' => 'ascii', 'null' => TRUE, 'cast' => 'string'],
			'correct' => ['bool', 'cast' => 'bool'],
			'position' => ['int8', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'question', 'label', 'image', 'correct', 'position'
		]);

		$this->propertiesToModule += [
			'question' => 'learning\Question',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['question']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'correct' :
				return FALSE;

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function select(...$fields): ChoiceModel {
		return parent::select(...$fields);
	}

	public function where(...$data): ChoiceModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): ChoiceModel {
		return $this->where('id', ...$data);
	}

	public function whereQuestion(...$data): ChoiceModel {
		return $this->where('question', ...$data);
	}

	public function whereLabel(...$data): ChoiceModel {
		return $this->where('label', ...$data);
	}

	public function whereImage(...$data): ChoiceModel {
		return $this->where('image', ...$data);
	}

	public function whereCorrect(...$data): ChoiceModel {
		return $this->where('correct', ...$data);
	}

	public function wherePosition(...$data): ChoiceModel {
		return $this->where('position', ...$data);
	}


}


abstract class ChoiceCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Choice {

		$e = new Choice();

		if(empty($id)) {
			Choice::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Choice::getSelection();
		}

		if(Choice::model()
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
			$properties = Choice::getSelection();
		}

		if($sort !== NULL) {
			Choice::model()->sort($sort);
		}

		return Choice::model()
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

	public static function getNewElement(array $properties = []): Choice {

		return new Choice($properties);

	}

	public static function create(Choice $e): void {

		Choice::model()->insert($e);

	}

	public static function update(Choice $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Choice::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Choice $e, array $properties): void {

		self::$affected = Choice::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Choice $e): void {

		$e->expects(['id']);

		self::$affected = Choice::model()->delete($e);

	}

}


class ChoicePage extends \ModulePage {

	protected string $module = 'learning\Choice';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? ChoiceLib::getPropertiesCreate(),
		   $propertiesUpdate ?? ChoiceLib::getPropertiesUpdate()
		);
	}

}
?>