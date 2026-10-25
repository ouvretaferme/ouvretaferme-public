<?php
namespace structure;

abstract class CommunityRedirectElement extends \Element {

	use \FilterElement;

	private static ?CommunityRedirectModel $model = NULL;

	public static function getSelection(): array {
		return CommunityRedirect::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): CommunityRedirectModel {
		if(self::$model === NULL) {
			self::$model = new CommunityRedirectModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('CommunityRedirect::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class CommunityRedirectModel extends \ModuleModel {

	protected string $module = 'structure\CommunityRedirect';
	protected string $package = 'structure';
	protected string $table = 'structureCommunityRedirect';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'fqn' => ['fqn', 'unique' => TRUE, 'cast' => 'string'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'fqn', 'structure', 'createdAt'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['structure']
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['fqn']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'createdAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function select(...$fields): CommunityRedirectModel {
		return parent::select(...$fields);
	}

	public function where(...$data): CommunityRedirectModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): CommunityRedirectModel {
		return $this->where('id', ...$data);
	}

	public function whereFqn(...$data): CommunityRedirectModel {
		return $this->where('fqn', ...$data);
	}

	public function whereStructure(...$data): CommunityRedirectModel {
		return $this->where('structure', ...$data);
	}

	public function whereCreatedAt(...$data): CommunityRedirectModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class CommunityRedirectCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): CommunityRedirect {

		$e = new CommunityRedirect();

		if(empty($id)) {
			CommunityRedirect::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = CommunityRedirect::getSelection();
		}

		if(CommunityRedirect::model()
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
			$properties = CommunityRedirect::getSelection();
		}

		if($sort !== NULL) {
			CommunityRedirect::model()->sort($sort);
		}

		return CommunityRedirect::model()
			->select($properties)
			->whereId('IN', $ids)
			->getCollection(NULL, NULL, $index);

	}

	public static function getByFqn(string $fqn, array $properties = []): CommunityRedirect {

		$e = new CommunityRedirect();

		if(empty($fqn)) {
			CommunityRedirect::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = CommunityRedirect::getSelection();
		}

		if(CommunityRedirect::model()
			->select($properties)
			->whereFqn($fqn)
			->get($e) === FALSE) {
				$e->setGhost($fqn);
		}

		return $e;

	}

	public static function getByFqns(array $fqns, array $properties = []): \Collection {

		if(empty($fqns)) {
			CommunityRedirect::model()->reset();
			return new \Collection();
		}

		if($properties === []) {
			$properties = CommunityRedirect::getSelection();
		}

		return CommunityRedirect::model()
			->select($properties)
			->whereFqn('IN', $fqns)
			->getCollection(NULL, NULL, 'fqn');

	}

	public static function getCache(mixed $key, \Closure $callback): mixed {
		self::$cache[$key] ??= $callback();
		return self::$cache[$key];
	}

	public static function getAffected(): ?int {
		return self::$affected;
	}

	public static function getNewElement(array $properties = []): CommunityRedirect {

		return new CommunityRedirect($properties);

	}

	public static function create(CommunityRedirect $e): void {

		CommunityRedirect::model()->insert($e);

	}

	public static function update(CommunityRedirect $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = CommunityRedirect::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, CommunityRedirect $e, array $properties): void {

		self::$affected = CommunityRedirect::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(CommunityRedirect $e): void {

		$e->expects(['id']);

		self::$affected = CommunityRedirect::model()->delete($e);

	}

}


class CommunityRedirectPage extends \ModulePage {

	protected string $module = 'structure\CommunityRedirect';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? CommunityRedirectLib::getPropertiesCreate(),
		   $propertiesUpdate ?? CommunityRedirectLib::getPropertiesUpdate()
		);
	}

}
?>