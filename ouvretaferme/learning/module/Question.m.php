<?php
namespace learning;

abstract class QuestionElement extends \Element {

	use \FilterElement;

	private static ?QuestionModel $model = NULL;

	const SINGLE = 'single';
	const MULTIPLE = 'multiple';

	public static function getSelection(): array {
		return Question::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): QuestionModel {
		if(self::$model === NULL) {
			self::$model = new QuestionModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Question::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class QuestionModel extends \ModuleModel {

	protected string $module = 'learning\Question';
	protected string $package = 'learning';
	protected string $table = 'learningQuestion';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'lesson' => ['element32', 'learning\Lesson', 'null' => TRUE, 'cast' => 'element'],
			'statement' => ['text8', 'min' => 1, 'max' => 250, 'cast' => 'string'],
			'image' => ['textFixed', 'min' => 20, 'max' => 20, 'charset' => 'ascii', 'null' => TRUE, 'cast' => 'string'],
			'type' => ['enum', [\learning\Question::SINGLE, \learning\Question::MULTIPLE], 'cast' => 'enum'],
			'explanation' => ['text16', 'min' => 1, 'max' => 2000, 'null' => TRUE, 'cast' => 'string'],
			'position' => ['int8', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'lesson', 'statement', 'image', 'type', 'explanation', 'position'
		]);

		$this->propertiesToModule += [
			'lesson' => 'learning\Lesson',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['lesson']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'type' :
				return Question::SINGLE;

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'type' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): QuestionModel {
		return parent::select(...$fields);
	}

	public function where(...$data): QuestionModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): QuestionModel {
		return $this->where('id', ...$data);
	}

	public function whereLesson(...$data): QuestionModel {
		return $this->where('lesson', ...$data);
	}

	public function whereStatement(...$data): QuestionModel {
		return $this->where('statement', ...$data);
	}

	public function whereImage(...$data): QuestionModel {
		return $this->where('image', ...$data);
	}

	public function whereType(...$data): QuestionModel {
		return $this->where('type', ...$data);
	}

	public function whereExplanation(...$data): QuestionModel {
		return $this->where('explanation', ...$data);
	}

	public function wherePosition(...$data): QuestionModel {
		return $this->where('position', ...$data);
	}


}


abstract class QuestionCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Question {

		$e = new Question();

		if(empty($id)) {
			Question::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Question::getSelection();
		}

		if(Question::model()
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
			$properties = Question::getSelection();
		}

		if($sort !== NULL) {
			Question::model()->sort($sort);
		}

		return Question::model()
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

	public static function getNewElement(array $properties = []): Question {

		return new Question($properties);

	}

	public static function create(Question $e): void {

		Question::model()->insert($e);

	}

	public static function update(Question $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Question::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Question $e, array $properties): void {

		self::$affected = Question::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Question $e): void {

		$e->expects(['id']);

		self::$affected = Question::model()->delete($e);

	}

}


class QuestionPage extends \ModulePage {

	protected string $module = 'learning\Question';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? QuestionLib::getPropertiesCreate(),
		   $propertiesUpdate ?? QuestionLib::getPropertiesUpdate()
		);
	}

}
?>