<?php
namespace aid;

abstract class AidLinkElement extends \Element {

	use \FilterElement;

	private static ?AidLinkModel $model = NULL;

	const REQUIRES = 'requires';
	const EXCLUDES = 'excludes';
	const COMPLEMENTS = 'complements';

	public static function getSelection(): array {
		return AidLink::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): AidLinkModel {
		if(self::$model === NULL) {
			self::$model = new AidLinkModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('AidLink::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class AidLinkModel extends \ModuleModel {

	protected string $module = 'aid\AidLink';
	protected string $package = 'aid';
	protected string $table = 'aidAidLink';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'aid' => ['element32', 'aid\Aid', 'cast' => 'element'],
			'target' => ['element32', 'aid\Aid', 'cast' => 'element'],
			'kind' => ['enum', [\aid\AidLink::REQUIRES, \aid\AidLink::EXCLUDES, \aid\AidLink::COMPLEMENTS], 'cast' => 'enum'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'aid', 'target', 'kind'
		]);

		$this->propertiesToModule += [
			'aid' => 'aid\Aid',
			'target' => 'aid\Aid',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['target']
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['aid', 'target', 'kind']
		]);

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'kind' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): AidLinkModel {
		return parent::select(...$fields);
	}

	public function where(...$data): AidLinkModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): AidLinkModel {
		return $this->where('id', ...$data);
	}

	public function whereAid(...$data): AidLinkModel {
		return $this->where('aid', ...$data);
	}

	public function whereTarget(...$data): AidLinkModel {
		return $this->where('target', ...$data);
	}

	public function whereKind(...$data): AidLinkModel {
		return $this->where('kind', ...$data);
	}


}


abstract class AidLinkCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): AidLink {

		$e = new AidLink();

		if(empty($id)) {
			AidLink::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = AidLink::getSelection();
		}

		if(AidLink::model()
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
			$properties = AidLink::getSelection();
		}

		if($sort !== NULL) {
			AidLink::model()->sort($sort);
		}

		return AidLink::model()
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

	public static function getNewElement(array $properties = []): AidLink {

		return new AidLink($properties);

	}

	public static function create(AidLink $e): void {

		AidLink::model()->insert($e);

	}

	public static function update(AidLink $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = AidLink::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, AidLink $e, array $properties): void {

		self::$affected = AidLink::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(AidLink $e): void {

		$e->expects(['id']);

		self::$affected = AidLink::model()->delete($e);

	}

}


class AidLinkPage extends \ModulePage {

	protected string $module = 'aid\AidLink';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? AidLinkLib::getPropertiesCreate(),
		   $propertiesUpdate ?? AidLinkLib::getPropertiesUpdate()
		);
	}

}
?>