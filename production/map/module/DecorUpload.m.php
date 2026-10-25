<?php
namespace map;

abstract class DecorUploadElement extends \Element {

	use \FilterElement;

	private static ?DecorUploadModel $model = NULL;

	const NEW = 'new';
	const KEPT = 'kept';
	const WITHDRAWN = 'withdrawn';

	const PERSON = 'person';
	const BRAND = 'brand';
	const OFFENSIVE = 'offensive';
	const OTHER = 'other';

	public static function getSelection(): array {
		return DecorUpload::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): DecorUploadModel {
		if(self::$model === NULL) {
			self::$model = new DecorUploadModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('DecorUpload::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class DecorUploadModel extends \ModuleModel {

	protected string $module = 'map\DecorUpload';
	protected string $package = 'map';
	protected string $table = 'mapDecorUpload';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'building' => ['element32', 'map\DecorBuilding', 'null' => TRUE, 'cast' => 'element'],
			'object' => ['element32', 'map\DecorObject', 'null' => TRUE, 'cast' => 'element'],
			'hash' => ['textFixed', 'min' => 20, 'max' => 20, 'charset' => 'ascii', 'null' => TRUE, 'cast' => 'string'],
			'bakedIndex' => ['json', 'null' => TRUE, 'cast' => 'array'],
			'rules' => ['int16', 'min' => 1, 'max' => NULL, 'cast' => 'int'],
			'sharedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'chosenBy' => ['element32', 'user\User', 'null' => TRUE, 'cast' => 'element'],
			'chosenAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'status' => ['enum', [\map\DecorUpload::NEW, \map\DecorUpload::KEPT, \map\DecorUpload::WITHDRAWN], 'cast' => 'enum'],
			'reason' => ['enum', [\map\DecorUpload::PERSON, \map\DecorUpload::BRAND, \map\DecorUpload::OFFENSIVE, \map\DecorUpload::OTHER], 'null' => TRUE, 'cast' => 'enum'],
			'reasonText' => ['text16', 'min' => 1, 'max' => 500, 'null' => TRUE, 'cast' => 'string'],
			'moderatedBy' => ['element32', 'user\User', 'null' => TRUE, 'cast' => 'element'],
			'moderatedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'createdAt' => ['datetime', 'cast' => 'string'],
			'createdBy' => ['element32', 'user\User', 'cast' => 'element'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'building', 'object', 'hash', 'bakedIndex', 'rules', 'sharedAt', 'chosenBy', 'chosenAt', 'status', 'reason', 'reasonText', 'moderatedBy', 'moderatedAt', 'createdAt', 'createdBy'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
			'building' => 'map\DecorBuilding',
			'object' => 'map\DecorObject',
			'chosenBy' => 'user\User',
			'moderatedBy' => 'user\User',
			'createdBy' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['structure'],
			['building'],
			['object'],
			['status']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'status' :
				return DecorUpload::NEW;

			case 'createdAt' :
				return new \Sql('NOW()');

			case 'createdBy' :
				return \user\ConnectionLib::getOnline();

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'bakedIndex' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			case 'status' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'reason' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'bakedIndex' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			default :
				return parent::decode($property, $value);

		}

	}

	public function select(...$fields): DecorUploadModel {
		return parent::select(...$fields);
	}

	public function where(...$data): DecorUploadModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): DecorUploadModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): DecorUploadModel {
		return $this->where('structure', ...$data);
	}

	public function whereBuilding(...$data): DecorUploadModel {
		return $this->where('building', ...$data);
	}

	public function whereObject(...$data): DecorUploadModel {
		return $this->where('object', ...$data);
	}

	public function whereHash(...$data): DecorUploadModel {
		return $this->where('hash', ...$data);
	}

	public function whereBakedIndex(...$data): DecorUploadModel {
		return $this->where('bakedIndex', ...$data);
	}

	public function whereRules(...$data): DecorUploadModel {
		return $this->where('rules', ...$data);
	}

	public function whereSharedAt(...$data): DecorUploadModel {
		return $this->where('sharedAt', ...$data);
	}

	public function whereChosenBy(...$data): DecorUploadModel {
		return $this->where('chosenBy', ...$data);
	}

	public function whereChosenAt(...$data): DecorUploadModel {
		return $this->where('chosenAt', ...$data);
	}

	public function whereStatus(...$data): DecorUploadModel {
		return $this->where('status', ...$data);
	}

	public function whereReason(...$data): DecorUploadModel {
		return $this->where('reason', ...$data);
	}

	public function whereReasonText(...$data): DecorUploadModel {
		return $this->where('reasonText', ...$data);
	}

	public function whereModeratedBy(...$data): DecorUploadModel {
		return $this->where('moderatedBy', ...$data);
	}

	public function whereModeratedAt(...$data): DecorUploadModel {
		return $this->where('moderatedAt', ...$data);
	}

	public function whereCreatedAt(...$data): DecorUploadModel {
		return $this->where('createdAt', ...$data);
	}

	public function whereCreatedBy(...$data): DecorUploadModel {
		return $this->where('createdBy', ...$data);
	}


}


abstract class DecorUploadCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): DecorUpload {

		$e = new DecorUpload();

		if(empty($id)) {
			DecorUpload::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = DecorUpload::getSelection();
		}

		if(DecorUpload::model()
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
			$properties = DecorUpload::getSelection();
		}

		if($sort !== NULL) {
			DecorUpload::model()->sort($sort);
		}

		return DecorUpload::model()
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

	public static function getNewElement(array $properties = []): DecorUpload {

		return new DecorUpload($properties);

	}

	public static function create(DecorUpload $e): void {

		DecorUpload::model()->insert($e);

	}

	public static function update(DecorUpload $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = DecorUpload::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, DecorUpload $e, array $properties): void {

		self::$affected = DecorUpload::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(DecorUpload $e): void {

		$e->expects(['id']);

		self::$affected = DecorUpload::model()->delete($e);

	}

}


class DecorUploadPage extends \ModulePage {

	protected string $module = 'map\DecorUpload';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? DecorUploadLib::getPropertiesCreate(),
		   $propertiesUpdate ?? DecorUploadLib::getPropertiesUpdate()
		);
	}

}
?>