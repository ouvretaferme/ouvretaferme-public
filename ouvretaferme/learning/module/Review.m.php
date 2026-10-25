<?php
namespace learning;

abstract class ReviewElement extends \Element {

	use \FilterElement;

	private static ?ReviewModel $model = NULL;

	const PUBLISHED = 'published';
	const REPORTED = 'reported';
	const KEPT = 'kept';
	const HIDDEN = 'hidden';

	public static function getSelection(): array {
		return Review::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): ReviewModel {
		if(self::$model === NULL) {
			self::$model = new ReviewModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Review::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class ReviewModel extends \ModuleModel {

	protected string $module = 'learning\Review';
	protected string $package = 'learning';
	protected string $table = 'learningReview';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'course' => ['element32', 'learning\Course', 'cast' => 'element'],
			'user' => ['element32', 'user\User', 'null' => TRUE, 'cast' => 'element'],
			'dateEnrollment' => ['element32', 'learning\DateEnrollment', 'null' => TRUE, 'unique' => TRUE, 'cast' => 'element'],
			'rating' => ['int8', 'min' => 1, 'max' => 5, 'cast' => 'int'],
			'text' => ['text16', 'min' => 1, 'max' => 2000, 'null' => TRUE, 'cast' => 'string'],
			'experiencedAt' => ['date', 'cast' => 'string'],
			'status' => ['enum', [\learning\Review::PUBLISHED, \learning\Review::REPORTED, \learning\Review::KEPT, \learning\Review::HIDDEN], 'cast' => 'enum'],
			'reportReason' => ['text16', 'min' => 1, 'max' => 1000, 'null' => TRUE, 'cast' => 'string'],
			'reportedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'reportedBy' => ['element32', 'user\User', 'null' => TRUE, 'cast' => 'element'],
			'hideReason' => ['text16', 'min' => 1, 'max' => 1000, 'null' => TRUE, 'cast' => 'string'],
			'moderatedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'moderatedBy' => ['element32', 'user\User', 'null' => TRUE, 'cast' => 'element'],
			'answer' => ['text16', 'min' => 1, 'max' => 2000, 'null' => TRUE, 'cast' => 'string'],
			'answeredAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'answeredBy' => ['element32', 'user\User', 'null' => TRUE, 'cast' => 'element'],
			'readAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'readBy' => ['element32', 'user\User', 'null' => TRUE, 'cast' => 'element'],
			'createdAt' => ['datetime', 'cast' => 'string'],
			'updatedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'course', 'user', 'dateEnrollment', 'rating', 'text', 'experiencedAt', 'status', 'reportReason', 'reportedAt', 'reportedBy', 'hideReason', 'moderatedAt', 'moderatedBy', 'answer', 'answeredAt', 'answeredBy', 'readAt', 'readBy', 'createdAt', 'updatedAt'
		]);

		$this->propertiesToModule += [
			'course' => 'learning\Course',
			'user' => 'user\User',
			'dateEnrollment' => 'learning\DateEnrollment',
			'reportedBy' => 'user\User',
			'moderatedBy' => 'user\User',
			'answeredBy' => 'user\User',
			'readBy' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['course', 'createdAt'],
			['status'],
			['course', 'readAt']
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['course', 'user'],
			['dateEnrollment']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'status' :
				return Review::PUBLISHED;

			case 'createdAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'status' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): ReviewModel {
		return parent::select(...$fields);
	}

	public function where(...$data): ReviewModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): ReviewModel {
		return $this->where('id', ...$data);
	}

	public function whereCourse(...$data): ReviewModel {
		return $this->where('course', ...$data);
	}

	public function whereUser(...$data): ReviewModel {
		return $this->where('user', ...$data);
	}

	public function whereDateEnrollment(...$data): ReviewModel {
		return $this->where('dateEnrollment', ...$data);
	}

	public function whereRating(...$data): ReviewModel {
		return $this->where('rating', ...$data);
	}

	public function whereText(...$data): ReviewModel {
		return $this->where('text', ...$data);
	}

	public function whereExperiencedAt(...$data): ReviewModel {
		return $this->where('experiencedAt', ...$data);
	}

	public function whereStatus(...$data): ReviewModel {
		return $this->where('status', ...$data);
	}

	public function whereReportReason(...$data): ReviewModel {
		return $this->where('reportReason', ...$data);
	}

	public function whereReportedAt(...$data): ReviewModel {
		return $this->where('reportedAt', ...$data);
	}

	public function whereReportedBy(...$data): ReviewModel {
		return $this->where('reportedBy', ...$data);
	}

	public function whereHideReason(...$data): ReviewModel {
		return $this->where('hideReason', ...$data);
	}

	public function whereModeratedAt(...$data): ReviewModel {
		return $this->where('moderatedAt', ...$data);
	}

	public function whereModeratedBy(...$data): ReviewModel {
		return $this->where('moderatedBy', ...$data);
	}

	public function whereAnswer(...$data): ReviewModel {
		return $this->where('answer', ...$data);
	}

	public function whereAnsweredAt(...$data): ReviewModel {
		return $this->where('answeredAt', ...$data);
	}

	public function whereAnsweredBy(...$data): ReviewModel {
		return $this->where('answeredBy', ...$data);
	}

	public function whereReadAt(...$data): ReviewModel {
		return $this->where('readAt', ...$data);
	}

	public function whereReadBy(...$data): ReviewModel {
		return $this->where('readBy', ...$data);
	}

	public function whereCreatedAt(...$data): ReviewModel {
		return $this->where('createdAt', ...$data);
	}

	public function whereUpdatedAt(...$data): ReviewModel {
		return $this->where('updatedAt', ...$data);
	}


}


abstract class ReviewCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Review {

		$e = new Review();

		if(empty($id)) {
			Review::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Review::getSelection();
		}

		if(Review::model()
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
			$properties = Review::getSelection();
		}

		if($sort !== NULL) {
			Review::model()->sort($sort);
		}

		return Review::model()
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

	public static function getNewElement(array $properties = []): Review {

		return new Review($properties);

	}

	public static function create(Review $e): void {

		Review::model()->insert($e);

	}

	public static function update(Review $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Review::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Review $e, array $properties): void {

		self::$affected = Review::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Review $e): void {

		$e->expects(['id']);

		self::$affected = Review::model()->delete($e);

	}

}


class ReviewPage extends \ModulePage {

	protected string $module = 'learning\Review';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? ReviewLib::getPropertiesCreate(),
		   $propertiesUpdate ?? ReviewLib::getPropertiesUpdate()
		);
	}

}
?>