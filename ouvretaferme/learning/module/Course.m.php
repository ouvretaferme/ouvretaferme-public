<?php
namespace learning;

abstract class CourseElement extends \Element {

	use \FilterElement;

	private static ?CourseModel $model = NULL;

	const ONSITE = 'onsite';
	const REMOTE = 'remote';
	const ONLINE = 'online';
	const MIXED = 'mixed';

	const DRAFT = 'draft';
	const PUBLISHED = 'published';

	const PRIVATE = 'private';
	const PROPOSED = 'proposed';
	const ACCEPTED = 'accepted';
	const REFUSED = 'refused';

	const NETWORK = 'network';
	const TRAINEE = 'trainee';
	const NONE = 'none';

	const VIVEA = 1;
	const OCAPIAT = 2;
	const FRANCE_TRAVAIL = 4;
	const CPF = 8;
	const REGION = 16;
	const OTHER = 32;

	public static function getSelection(): array {
		return Course::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): CourseModel {
		if(self::$model === NULL) {
			self::$model = new CourseModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Course::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class CourseModel extends \ModuleModel {

	protected string $module = 'learning\Course';
	protected string $package = 'learning';
	protected string $table = 'learningCourse';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'community' => ['element32', 'structure\Structure', 'null' => TRUE, 'cast' => 'element'],
			'type' => ['enum', [\learning\Course::ONSITE, \learning\Course::REMOTE, \learning\Course::ONLINE, \learning\Course::MIXED], 'cast' => 'enum'],
			'name' => ['text8', 'min' => 1, 'max' => 100, 'collate' => 'general', 'cast' => 'string'],
			'summary' => ['text8', 'min' => 1, 'max' => 250, 'null' => TRUE, 'cast' => 'string'],
			'description' => ['editor24', 'null' => TRUE, 'cast' => 'string'],
			'audience' => ['text8', 'min' => 1, 'max' => 250, 'null' => TRUE, 'cast' => 'string'],
			'prerequisites' => ['text8', 'min' => 1, 'max' => 250, 'null' => TRUE, 'cast' => 'string'],
			'objectives' => ['editor16', 'null' => TRUE, 'cast' => 'string'],
			'access' => ['text8', 'min' => 1, 'max' => 250, 'null' => TRUE, 'cast' => 'string'],
			'accessibility' => ['text16', 'min' => 1, 'max' => 2000, 'null' => TRUE, 'cast' => 'string'],
			'recognition' => ['text8', 'min' => 1, 'max' => 250, 'null' => TRUE, 'cast' => 'string'],
			'theme' => ['element32', 'learning\Theme', 'null' => TRUE, 'cast' => 'element'],
			'subtheme' => ['element32', 'learning\Subtheme', 'null' => TRUE, 'cast' => 'element'],
			'level' => ['element32', 'learning\Level', 'null' => TRUE, 'cast' => 'element'],
			'vignette' => ['textFixed', 'min' => 30, 'max' => 30, 'charset' => 'ascii', 'null' => TRUE, 'cast' => 'string'],
			'status' => ['enum', [\learning\Course::DRAFT, \learning\Course::PUBLISHED], 'cast' => 'enum'],
			'catalog' => ['enum', [\learning\Course::PRIVATE, \learning\Course::PROPOSED, \learning\Course::ACCEPTED, \learning\Course::REFUSED], 'cast' => 'enum'],
			'catalogReason' => ['text16', 'null' => TRUE, 'cast' => 'string'],
			'catalogAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'featured' => ['bool', 'cast' => 'bool'],
			'featuredAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'open' => ['bool', 'cast' => 'bool'],
			'offer' => ['enum', [\learning\Course::NETWORK, \learning\Course::TRAINEE, \learning\Course::NONE], 'cast' => 'enum'],
			'priceFunded' => ['decimal', 'digits' => 8, 'decimal' => 2, 'min' => 0.0, 'max' => 10000.0, 'null' => TRUE, 'cast' => 'float'],
			'price' => ['decimal', 'digits' => 8, 'decimal' => 2, 'min' => 0.0, 'max' => 10000.0, 'null' => TRUE, 'cast' => 'float'],
			'funding' => ['set', [\learning\Course::VIVEA, \learning\Course::OCAPIAT, \learning\Course::FRANCE_TRAVAIL, \learning\Course::CPF, \learning\Course::REGION, \learning\Course::OTHER], 'null' => TRUE, 'cast' => 'set'],
			'fundingNote' => ['text8', 'min' => 1, 'max' => 250, 'null' => TRUE, 'cast' => 'string'],
			'externalOnly' => ['bool', 'cast' => 'bool'],
			'externalUrl' => ['url', 'null' => TRUE, 'cast' => 'string'],
			'enrollmentPaused' => ['bool', 'cast' => 'bool'],
			'copiedFrom' => ['element32', 'learning\Course', 'null' => TRUE, 'cast' => 'element'],
			'publishedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'createdAt' => ['datetime', 'cast' => 'string'],
			'createdBy' => ['element32', 'user\User', 'cast' => 'element'],
			'updatedAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'community', 'type', 'name', 'summary', 'description', 'audience', 'prerequisites', 'objectives', 'access', 'accessibility', 'recognition', 'theme', 'subtheme', 'level', 'vignette', 'status', 'catalog', 'catalogReason', 'catalogAt', 'featured', 'featuredAt', 'open', 'offer', 'priceFunded', 'price', 'funding', 'fundingNote', 'externalOnly', 'externalUrl', 'enrollmentPaused', 'copiedFrom', 'publishedAt', 'createdAt', 'createdBy', 'updatedAt'
		]);

		$this->propertiesToModule += [
			'community' => 'structure\Structure',
			'theme' => 'learning\Theme',
			'subtheme' => 'learning\Subtheme',
			'level' => 'learning\Level',
			'copiedFrom' => 'learning\Course',
			'createdBy' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['community'],
			['catalog', 'status'],
			['theme'],
			['level'],
			['copiedFrom'],
			['subtheme'],
			['featured']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'type' :
				return Course::ONLINE;

			case 'status' :
				return Course::DRAFT;

			case 'catalog' :
				return Course::PRIVATE;

			case 'featured' :
				return FALSE;

			case 'open' :
				return FALSE;

			case 'offer' :
				return Course::NONE;

			case 'externalOnly' :
				return FALSE;

			case 'enrollmentPaused' :
				return FALSE;

			case 'createdAt' :
				return new \Sql('NOW()');

			case 'createdBy' :
				return \user\ConnectionLib::getOnline();

			case 'updatedAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'type' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'status' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'catalog' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'offer' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): CourseModel {
		return parent::select(...$fields);
	}

	public function where(...$data): CourseModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): CourseModel {
		return $this->where('id', ...$data);
	}

	public function whereCommunity(...$data): CourseModel {
		return $this->where('community', ...$data);
	}

	public function whereType(...$data): CourseModel {
		return $this->where('type', ...$data);
	}

	public function whereName(...$data): CourseModel {
		return $this->where('name', ...$data);
	}

	public function whereSummary(...$data): CourseModel {
		return $this->where('summary', ...$data);
	}

	public function whereDescription(...$data): CourseModel {
		return $this->where('description', ...$data);
	}

	public function whereAudience(...$data): CourseModel {
		return $this->where('audience', ...$data);
	}

	public function wherePrerequisites(...$data): CourseModel {
		return $this->where('prerequisites', ...$data);
	}

	public function whereObjectives(...$data): CourseModel {
		return $this->where('objectives', ...$data);
	}

	public function whereAccess(...$data): CourseModel {
		return $this->where('access', ...$data);
	}

	public function whereAccessibility(...$data): CourseModel {
		return $this->where('accessibility', ...$data);
	}

	public function whereRecognition(...$data): CourseModel {
		return $this->where('recognition', ...$data);
	}

	public function whereTheme(...$data): CourseModel {
		return $this->where('theme', ...$data);
	}

	public function whereSubtheme(...$data): CourseModel {
		return $this->where('subtheme', ...$data);
	}

	public function whereLevel(...$data): CourseModel {
		return $this->where('level', ...$data);
	}

	public function whereVignette(...$data): CourseModel {
		return $this->where('vignette', ...$data);
	}

	public function whereStatus(...$data): CourseModel {
		return $this->where('status', ...$data);
	}

	public function whereCatalog(...$data): CourseModel {
		return $this->where('catalog', ...$data);
	}

	public function whereCatalogReason(...$data): CourseModel {
		return $this->where('catalogReason', ...$data);
	}

	public function whereCatalogAt(...$data): CourseModel {
		return $this->where('catalogAt', ...$data);
	}

	public function whereFeatured(...$data): CourseModel {
		return $this->where('featured', ...$data);
	}

	public function whereFeaturedAt(...$data): CourseModel {
		return $this->where('featuredAt', ...$data);
	}

	public function whereOpen(...$data): CourseModel {
		return $this->where('open', ...$data);
	}

	public function whereOffer(...$data): CourseModel {
		return $this->where('offer', ...$data);
	}

	public function wherePriceFunded(...$data): CourseModel {
		return $this->where('priceFunded', ...$data);
	}

	public function wherePrice(...$data): CourseModel {
		return $this->where('price', ...$data);
	}

	public function whereFunding(...$data): CourseModel {
		return $this->where('funding', ...$data);
	}

	public function whereFundingNote(...$data): CourseModel {
		return $this->where('fundingNote', ...$data);
	}

	public function whereExternalOnly(...$data): CourseModel {
		return $this->where('externalOnly', ...$data);
	}

	public function whereExternalUrl(...$data): CourseModel {
		return $this->where('externalUrl', ...$data);
	}

	public function whereEnrollmentPaused(...$data): CourseModel {
		return $this->where('enrollmentPaused', ...$data);
	}

	public function whereCopiedFrom(...$data): CourseModel {
		return $this->where('copiedFrom', ...$data);
	}

	public function wherePublishedAt(...$data): CourseModel {
		return $this->where('publishedAt', ...$data);
	}

	public function whereCreatedAt(...$data): CourseModel {
		return $this->where('createdAt', ...$data);
	}

	public function whereCreatedBy(...$data): CourseModel {
		return $this->where('createdBy', ...$data);
	}

	public function whereUpdatedAt(...$data): CourseModel {
		return $this->where('updatedAt', ...$data);
	}


}


abstract class CourseCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Course {

		$e = new Course();

		if(empty($id)) {
			Course::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Course::getSelection();
		}

		if(Course::model()
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
			$properties = Course::getSelection();
		}

		if($sort !== NULL) {
			Course::model()->sort($sort);
		}

		return Course::model()
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

	public static function getNewElement(array $properties = []): Course {

		return new Course($properties);

	}

	public static function create(Course $e): void {

		Course::model()->insert($e);

	}

	public static function update(Course $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Course::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Course $e, array $properties): void {

		self::$affected = Course::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Course $e): void {

		$e->expects(['id']);

		self::$affected = Course::model()->delete($e);

	}

}


class CoursePage extends \ModulePage {

	protected string $module = 'learning\Course';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? CourseLib::getPropertiesCreate(),
		   $propertiesUpdate ?? CourseLib::getPropertiesUpdate()
		);
	}

}
?>