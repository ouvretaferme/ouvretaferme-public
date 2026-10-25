<?php
namespace learning;

abstract class FaqElement extends \Element {

	use \FilterElement;

	private static ?FaqModel $model = NULL;

	const SERIES = 'series';
	const SOIL = 'soil';
	const SEQUENCE = 'sequence';
	const PLANNING = 'planning';
	const EXPORT = 'export';
	const SEEDLING = 'seedling';
	const FORECAST = 'forecast';

	const POSSIBLE = 'possible';
	const PARTIAL = 'partial';
	const INTENDED = 'intended';
	const IMPOSSIBLE = 'impossible';

	const DRAFT = 'draft';
	const PUBLISHED = 'published';

	public static function getSelection(): array {
		return Faq::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): FaqModel {
		if(self::$model === NULL) {
			self::$model = new FaqModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Faq::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class FaqModel extends \ModuleModel {

	protected string $module = 'learning\Faq';
	protected string $package = 'learning';
	protected string $table = 'learningFaq';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'community' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'question' => ['text8', 'min' => 1, 'max' => 250, 'collate' => 'general', 'cast' => 'string'],
			'answer' => ['editor24', 'cast' => 'string'],
			'symptoms' => ['editor16', 'null' => TRUE, 'cast' => 'string'],
			'steps' => ['editor16', 'null' => TRUE, 'cast' => 'string'],
			'notes' => ['editor16', 'null' => TRUE, 'cast' => 'string'],
			'demoPage' => ['text8', 'min' => 1, 'max' => 250, 'null' => TRUE, 'cast' => 'string'],
			'demoPath' => ['text8', 'min' => 1, 'max' => 100, 'null' => TRUE, 'cast' => 'string'],
			'keywords' => ['text8', 'min' => 1, 'max' => 250, 'null' => TRUE, 'cast' => 'string'],
			'screen' => ['enum', [\learning\Faq::SERIES, \learning\Faq::SOIL, \learning\Faq::SEQUENCE, \learning\Faq::PLANNING, \learning\Faq::EXPORT, \learning\Faq::SEEDLING, \learning\Faq::FORECAST], 'null' => TRUE, 'cast' => 'enum'],
			'verdict' => ['enum', [\learning\Faq::POSSIBLE, \learning\Faq::PARTIAL, \learning\Faq::INTENDED, \learning\Faq::IMPOSSIBLE], 'cast' => 'enum'],
			'status' => ['enum', [\learning\Faq::DRAFT, \learning\Faq::PUBLISHED], 'cast' => 'enum'],
			'chapter' => ['element32', 'learning\Chapter', 'null' => TRUE, 'cast' => 'element'],
			'discordUrl' => ['url', 'null' => TRUE, 'cast' => 'string'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'community', 'question', 'answer', 'symptoms', 'steps', 'notes', 'demoPage', 'demoPath', 'keywords', 'screen', 'verdict', 'status', 'chapter', 'discordUrl', 'createdAt'
		]);

		$this->propertiesToModule += [
			'community' => 'structure\Structure',
			'chapter' => 'learning\Chapter',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['community'],
			['chapter']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'status' :
				return Faq::DRAFT;

			case 'createdAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'screen' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'verdict' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'status' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): FaqModel {
		return parent::select(...$fields);
	}

	public function where(...$data): FaqModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): FaqModel {
		return $this->where('id', ...$data);
	}

	public function whereCommunity(...$data): FaqModel {
		return $this->where('community', ...$data);
	}

	public function whereQuestion(...$data): FaqModel {
		return $this->where('question', ...$data);
	}

	public function whereAnswer(...$data): FaqModel {
		return $this->where('answer', ...$data);
	}

	public function whereSymptoms(...$data): FaqModel {
		return $this->where('symptoms', ...$data);
	}

	public function whereSteps(...$data): FaqModel {
		return $this->where('steps', ...$data);
	}

	public function whereNotes(...$data): FaqModel {
		return $this->where('notes', ...$data);
	}

	public function whereDemoPage(...$data): FaqModel {
		return $this->where('demoPage', ...$data);
	}

	public function whereDemoPath(...$data): FaqModel {
		return $this->where('demoPath', ...$data);
	}

	public function whereKeywords(...$data): FaqModel {
		return $this->where('keywords', ...$data);
	}

	public function whereScreen(...$data): FaqModel {
		return $this->where('screen', ...$data);
	}

	public function whereVerdict(...$data): FaqModel {
		return $this->where('verdict', ...$data);
	}

	public function whereStatus(...$data): FaqModel {
		return $this->where('status', ...$data);
	}

	public function whereChapter(...$data): FaqModel {
		return $this->where('chapter', ...$data);
	}

	public function whereDiscordUrl(...$data): FaqModel {
		return $this->where('discordUrl', ...$data);
	}

	public function whereCreatedAt(...$data): FaqModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class FaqCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Faq {

		$e = new Faq();

		if(empty($id)) {
			Faq::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Faq::getSelection();
		}

		if(Faq::model()
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
			$properties = Faq::getSelection();
		}

		if($sort !== NULL) {
			Faq::model()->sort($sort);
		}

		return Faq::model()
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

	public static function getNewElement(array $properties = []): Faq {

		return new Faq($properties);

	}

	public static function create(Faq $e): void {

		Faq::model()->insert($e);

	}

	public static function update(Faq $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Faq::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Faq $e, array $properties): void {

		self::$affected = Faq::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Faq $e): void {

		$e->expects(['id']);

		self::$affected = Faq::model()->delete($e);

	}

}


class FaqPage extends \ModulePage {

	protected string $module = 'learning\Faq';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? FaqLib::getPropertiesCreate(),
		   $propertiesUpdate ?? FaqLib::getPropertiesUpdate()
		);
	}

}
?>