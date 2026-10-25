<?php
namespace learning;

abstract class LessonElement extends \Element {

	use \FilterElement;

	private static ?LessonModel $model = NULL;

	const TEXT = 'text';
	const VIDEO = 'video';
	const DIAGRAM = 'diagram';
	const LIVE = 'live';

	const DECLARED = 'declared';
	const QUIZ = 'quiz';
	const DELIVERABLE = 'deliverable';
	const PRESENCE = 'presence';

	public static function getSelection(): array {
		return Lesson::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): LessonModel {
		if(self::$model === NULL) {
			self::$model = new LessonModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Lesson::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class LessonModel extends \ModuleModel {

	protected string $module = 'learning\Lesson';
	protected string $package = 'learning';
	protected string $table = 'learningLesson';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'course' => ['element32', 'learning\Course', 'cast' => 'element'],
			'chapter' => ['element32', 'learning\Chapter', 'cast' => 'element'],
			'name' => ['text8', 'min' => 1, 'max' => 100, 'cast' => 'string'],
			'objective' => ['text8', 'min' => 1, 'max' => 250, 'null' => TRUE, 'cast' => 'string'],
			'format' => ['enum', [\learning\Lesson::TEXT, \learning\Lesson::VIDEO, \learning\Lesson::DIAGRAM, \learning\Lesson::LIVE], 'cast' => 'enum'],
			'minutes' => ['int16', 'min' => 1, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'activityMinutes' => ['int16', 'min' => 1, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'validation' => ['enum', [\learning\Lesson::DECLARED, \learning\Lesson::QUIZ, \learning\Lesson::DELIVERABLE, \learning\Lesson::PRESENCE], 'cast' => 'enum'],
			'quizThreshold' => ['int8', 'min' => 0, 'max' => 100, 'null' => TRUE, 'cast' => 'int'],
			'deliverable' => ['text8', 'min' => 1, 'max' => 250, 'null' => TRUE, 'cast' => 'string'],
			'mandatory' => ['bool', 'cast' => 'bool'],
			'written' => ['bool', 'cast' => 'bool'],
			'position' => ['int8', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'createdAt' => ['datetime', 'cast' => 'string'],
			'updatedAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'course', 'chapter', 'name', 'objective', 'format', 'minutes', 'activityMinutes', 'validation', 'quizThreshold', 'deliverable', 'mandatory', 'written', 'position', 'createdAt', 'updatedAt'
		]);

		$this->propertiesToModule += [
			'course' => 'learning\Course',
			'chapter' => 'learning\Chapter',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['course'],
			['chapter']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'format' :
				return Lesson::TEXT;

			case 'validation' :
				return Lesson::DECLARED;

			case 'mandatory' :
				return TRUE;

			case 'written' :
				return FALSE;

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

			case 'format' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'validation' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): LessonModel {
		return parent::select(...$fields);
	}

	public function where(...$data): LessonModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): LessonModel {
		return $this->where('id', ...$data);
	}

	public function whereCourse(...$data): LessonModel {
		return $this->where('course', ...$data);
	}

	public function whereChapter(...$data): LessonModel {
		return $this->where('chapter', ...$data);
	}

	public function whereName(...$data): LessonModel {
		return $this->where('name', ...$data);
	}

	public function whereObjective(...$data): LessonModel {
		return $this->where('objective', ...$data);
	}

	public function whereFormat(...$data): LessonModel {
		return $this->where('format', ...$data);
	}

	public function whereMinutes(...$data): LessonModel {
		return $this->where('minutes', ...$data);
	}

	public function whereActivityMinutes(...$data): LessonModel {
		return $this->where('activityMinutes', ...$data);
	}

	public function whereValidation(...$data): LessonModel {
		return $this->where('validation', ...$data);
	}

	public function whereQuizThreshold(...$data): LessonModel {
		return $this->where('quizThreshold', ...$data);
	}

	public function whereDeliverable(...$data): LessonModel {
		return $this->where('deliverable', ...$data);
	}

	public function whereMandatory(...$data): LessonModel {
		return $this->where('mandatory', ...$data);
	}

	public function whereWritten(...$data): LessonModel {
		return $this->where('written', ...$data);
	}

	public function wherePosition(...$data): LessonModel {
		return $this->where('position', ...$data);
	}

	public function whereCreatedAt(...$data): LessonModel {
		return $this->where('createdAt', ...$data);
	}

	public function whereUpdatedAt(...$data): LessonModel {
		return $this->where('updatedAt', ...$data);
	}


}


abstract class LessonCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Lesson {

		$e = new Lesson();

		if(empty($id)) {
			Lesson::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Lesson::getSelection();
		}

		if(Lesson::model()
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
			$properties = Lesson::getSelection();
		}

		if($sort !== NULL) {
			Lesson::model()->sort($sort);
		}

		return Lesson::model()
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

	public static function getNewElement(array $properties = []): Lesson {

		return new Lesson($properties);

	}

	public static function create(Lesson $e): void {

		Lesson::model()->insert($e);

	}

	public static function update(Lesson $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Lesson::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Lesson $e, array $properties): void {

		self::$affected = Lesson::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Lesson $e): void {

		$e->expects(['id']);

		self::$affected = Lesson::model()->delete($e);

	}

}


class LessonPage extends \ModulePage {

	protected string $module = 'learning\Lesson';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? LessonLib::getPropertiesCreate(),
		   $propertiesUpdate ?? LessonLib::getPropertiesUpdate()
		);
	}

}
?>