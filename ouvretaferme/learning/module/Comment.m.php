<?php
namespace learning;

abstract class CommentElement extends \Element {

	use \FilterElement;

	private static ?CommentModel $model = NULL;

	public static function getSelection(): array {
		return Comment::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): CommentModel {
		if(self::$model === NULL) {
			self::$model = new CommentModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Comment::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class CommentModel extends \ModuleModel {

	protected string $module = 'learning\Comment';
	protected string $package = 'learning';
	protected string $table = 'learningComment';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'lesson' => ['element32', 'learning\Lesson', 'cast' => 'element'],
			'course' => ['element32', 'learning\Course', 'cast' => 'element'],
			'parent' => ['element32', 'learning\Comment', 'null' => TRUE, 'cast' => 'element'],
			'text' => ['text16', 'min' => 1, 'max' => 2000, 'cast' => 'string'],
			'readAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'readBy' => ['element32', 'user\User', 'null' => TRUE, 'cast' => 'element'],
			'answeredAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'createdAt' => ['datetime', 'cast' => 'string'],
			'createdBy' => ['element32', 'user\User', 'cast' => 'element'],
			'updatedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'lesson', 'course', 'parent', 'text', 'readAt', 'readBy', 'answeredAt', 'createdAt', 'createdBy', 'updatedAt'
		]);

		$this->propertiesToModule += [
			'lesson' => 'learning\Lesson',
			'course' => 'learning\Course',
			'parent' => 'learning\Comment',
			'readBy' => 'user\User',
			'createdBy' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['lesson'],
			['course', 'readAt'],
			['parent']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'createdAt' :
				return new \Sql('NOW()');

			case 'createdBy' :
				return \user\ConnectionLib::getOnline();

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function select(...$fields): CommentModel {
		return parent::select(...$fields);
	}

	public function where(...$data): CommentModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): CommentModel {
		return $this->where('id', ...$data);
	}

	public function whereLesson(...$data): CommentModel {
		return $this->where('lesson', ...$data);
	}

	public function whereCourse(...$data): CommentModel {
		return $this->where('course', ...$data);
	}

	public function whereParent(...$data): CommentModel {
		return $this->where('parent', ...$data);
	}

	public function whereText(...$data): CommentModel {
		return $this->where('text', ...$data);
	}

	public function whereReadAt(...$data): CommentModel {
		return $this->where('readAt', ...$data);
	}

	public function whereReadBy(...$data): CommentModel {
		return $this->where('readBy', ...$data);
	}

	public function whereAnsweredAt(...$data): CommentModel {
		return $this->where('answeredAt', ...$data);
	}

	public function whereCreatedAt(...$data): CommentModel {
		return $this->where('createdAt', ...$data);
	}

	public function whereCreatedBy(...$data): CommentModel {
		return $this->where('createdBy', ...$data);
	}

	public function whereUpdatedAt(...$data): CommentModel {
		return $this->where('updatedAt', ...$data);
	}


}


abstract class CommentCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Comment {

		$e = new Comment();

		if(empty($id)) {
			Comment::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Comment::getSelection();
		}

		if(Comment::model()
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
			$properties = Comment::getSelection();
		}

		if($sort !== NULL) {
			Comment::model()->sort($sort);
		}

		return Comment::model()
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

	public static function getNewElement(array $properties = []): Comment {

		return new Comment($properties);

	}

	public static function create(Comment $e): void {

		Comment::model()->insert($e);

	}

	public static function update(Comment $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Comment::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Comment $e, array $properties): void {

		self::$affected = Comment::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Comment $e): void {

		$e->expects(['id']);

		self::$affected = Comment::model()->delete($e);

	}

}


class CommentPage extends \ModulePage {

	protected string $module = 'learning\Comment';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? CommentLib::getPropertiesCreate(),
		   $propertiesUpdate ?? CommentLib::getPropertiesUpdate()
		);
	}

}
?>