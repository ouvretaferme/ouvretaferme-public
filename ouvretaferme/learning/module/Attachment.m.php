<?php
namespace learning;

abstract class AttachmentElement extends \Element {

	use \FilterElement;

	private static ?AttachmentModel $model = NULL;

	public static function getSelection(): array {
		return Attachment::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): AttachmentModel {
		if(self::$model === NULL) {
			self::$model = new AttachmentModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Attachment::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class AttachmentModel extends \ModuleModel {

	protected string $module = 'learning\Attachment';
	protected string $package = 'learning';
	protected string $table = 'learningAttachment';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'lesson' => ['element32', 'learning\Lesson', 'cast' => 'element'],
			'course' => ['element32', 'learning\Course', 'cast' => 'element'],
			'name' => ['text8', 'min' => 1, 'max' => 100, 'cast' => 'string'],
			'hash' => ['textFixed', 'min' => 20, 'max' => 20, 'charset' => 'ascii', 'cast' => 'string'],
			'position' => ['int8', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'createdAt' => ['datetime', 'cast' => 'string'],
			'createdBy' => ['element32', 'user\User', 'cast' => 'element'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'lesson', 'course', 'name', 'hash', 'position', 'createdAt', 'createdBy'
		]);

		$this->propertiesToModule += [
			'lesson' => 'learning\Lesson',
			'course' => 'learning\Course',
			'createdBy' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['lesson'],
			['course']
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

	public function select(...$fields): AttachmentModel {
		return parent::select(...$fields);
	}

	public function where(...$data): AttachmentModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): AttachmentModel {
		return $this->where('id', ...$data);
	}

	public function whereLesson(...$data): AttachmentModel {
		return $this->where('lesson', ...$data);
	}

	public function whereCourse(...$data): AttachmentModel {
		return $this->where('course', ...$data);
	}

	public function whereName(...$data): AttachmentModel {
		return $this->where('name', ...$data);
	}

	public function whereHash(...$data): AttachmentModel {
		return $this->where('hash', ...$data);
	}

	public function wherePosition(...$data): AttachmentModel {
		return $this->where('position', ...$data);
	}

	public function whereCreatedAt(...$data): AttachmentModel {
		return $this->where('createdAt', ...$data);
	}

	public function whereCreatedBy(...$data): AttachmentModel {
		return $this->where('createdBy', ...$data);
	}


}


abstract class AttachmentCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Attachment {

		$e = new Attachment();

		if(empty($id)) {
			Attachment::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Attachment::getSelection();
		}

		if(Attachment::model()
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
			$properties = Attachment::getSelection();
		}

		if($sort !== NULL) {
			Attachment::model()->sort($sort);
		}

		return Attachment::model()
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

	public static function getNewElement(array $properties = []): Attachment {

		return new Attachment($properties);

	}

	public static function create(Attachment $e): void {

		Attachment::model()->insert($e);

	}

	public static function update(Attachment $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Attachment::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Attachment $e, array $properties): void {

		self::$affected = Attachment::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Attachment $e): void {

		$e->expects(['id']);

		self::$affected = Attachment::model()->delete($e);

	}

}


class AttachmentPage extends \ModulePage {

	protected string $module = 'learning\Attachment';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? AttachmentLib::getPropertiesCreate(),
		   $propertiesUpdate ?? AttachmentLib::getPropertiesUpdate()
		);
	}

}
?>