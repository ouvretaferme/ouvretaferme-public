<?php
namespace structure;

abstract class TermsElement extends \Element {

	use \FilterElement;

	private static ?TermsModel $model = NULL;

	public static function getSelection(): array {
		return Terms::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): TermsModel {
		if(self::$model === NULL) {
			self::$model = new TermsModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Terms::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class TermsModel extends \ModuleModel {

	protected string $module = 'structure\Terms';
	protected string $package = 'structure';
	protected string $table = 'structureTerms';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'user' => ['element32', 'user\User', 'cast' => 'element'],
			'version' => ['int16', 'min' => 1, 'max' => NULL, 'cast' => 'int'],
			'ip' => ['ipv4', 'null' => TRUE, 'cast' => 'string'],
			'acceptedAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'user', 'version', 'ip', 'acceptedAt'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
			'user' => 'user\User',
		];

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['structure', 'version']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'user' :
				return \user\ConnectionLib::getOnline();

			case 'ip' :
				return getIp();

			case 'acceptedAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'ip' :
				return $value === NULL ? NULL : (int)first(unpack('l', pack('l', ip2long($value))));

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'ip' :
				return $value === NULL ? NULL : long2ip($value);

			default :
				return parent::decode($property, $value);

		}

	}

	public function select(...$fields): TermsModel {
		return parent::select(...$fields);
	}

	public function where(...$data): TermsModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): TermsModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): TermsModel {
		return $this->where('structure', ...$data);
	}

	public function whereUser(...$data): TermsModel {
		return $this->where('user', ...$data);
	}

	public function whereVersion(...$data): TermsModel {
		return $this->where('version', ...$data);
	}

	public function whereIp(...$data): TermsModel {
		return $this->where('ip', ...$data);
	}

	public function whereAcceptedAt(...$data): TermsModel {
		return $this->where('acceptedAt', ...$data);
	}


}


abstract class TermsCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Terms {

		$e = new Terms();

		if(empty($id)) {
			Terms::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Terms::getSelection();
		}

		if(Terms::model()
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
			$properties = Terms::getSelection();
		}

		if($sort !== NULL) {
			Terms::model()->sort($sort);
		}

		return Terms::model()
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

	public static function getNewElement(array $properties = []): Terms {

		return new Terms($properties);

	}

	public static function create(Terms $e): void {

		Terms::model()->insert($e);

	}

	public static function update(Terms $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Terms::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Terms $e, array $properties): void {

		self::$affected = Terms::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Terms $e): void {

		$e->expects(['id']);

		self::$affected = Terms::model()->delete($e);

	}

}


class TermsPage extends \ModulePage {

	protected string $module = 'structure\Terms';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? TermsAiLib::getPropertiesCreate(),
		   $propertiesUpdate ?? TermsAiLib::getPropertiesUpdate()
		);
	}

}
?>