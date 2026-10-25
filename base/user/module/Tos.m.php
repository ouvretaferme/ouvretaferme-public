<?php
namespace user;

abstract class TosElement extends \Element {

	use \FilterElement;

	private static ?TosModel $model = NULL;

	const SIGNUP = 'signup';
	const TACIT = 'tacit';
	const HISTORIC = 'historic';
	const EXPLICIT = 'explicit';

	public static function getSelection(): array {
		return Tos::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): TosModel {
		if(self::$model === NULL) {
			self::$model = new TosModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Tos::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class TosModel extends \ModuleModel {

	protected string $module = 'user\Tos';
	protected string $package = 'user';
	protected string $table = 'userTos';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'user' => ['element32', 'user\User', 'cast' => 'element'],
			'version' => ['int16', 'min' => 1, 'max' => NULL, 'cast' => 'int'],
			'mode' => ['enum', [\user\Tos::SIGNUP, \user\Tos::TACIT, \user\Tos::HISTORIC, \user\Tos::EXPLICIT], 'cast' => 'enum'],
			'ip' => ['ipv4', 'null' => TRUE, 'cast' => 'string'],
			'acceptedAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'user', 'version', 'mode', 'ip', 'acceptedAt'
		]);

		$this->propertiesToModule += [
			'user' => 'user\User',
		];

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['user', 'version']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

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

			case 'mode' :
				return ($value === NULL) ? NULL : (string)$value;

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

	public function select(...$fields): TosModel {
		return parent::select(...$fields);
	}

	public function where(...$data): TosModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): TosModel {
		return $this->where('id', ...$data);
	}

	public function whereUser(...$data): TosModel {
		return $this->where('user', ...$data);
	}

	public function whereVersion(...$data): TosModel {
		return $this->where('version', ...$data);
	}

	public function whereMode(...$data): TosModel {
		return $this->where('mode', ...$data);
	}

	public function whereIp(...$data): TosModel {
		return $this->where('ip', ...$data);
	}

	public function whereAcceptedAt(...$data): TosModel {
		return $this->where('acceptedAt', ...$data);
	}


}


abstract class TosCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Tos {

		$e = new Tos();

		if(empty($id)) {
			Tos::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Tos::getSelection();
		}

		if(Tos::model()
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
			$properties = Tos::getSelection();
		}

		if($sort !== NULL) {
			Tos::model()->sort($sort);
		}

		return Tos::model()
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

	public static function getNewElement(array $properties = []): Tos {

		return new Tos($properties);

	}

	public static function create(Tos $e): void {

		Tos::model()->insert($e);

	}

	public static function update(Tos $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Tos::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Tos $e, array $properties): void {

		self::$affected = Tos::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Tos $e): void {

		$e->expects(['id']);

		self::$affected = Tos::model()->delete($e);

	}

}


class TosPage extends \ModulePage {

	protected string $module = 'user\Tos';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? TosAiLib::getPropertiesCreate(),
		   $propertiesUpdate ?? TosAiLib::getPropertiesUpdate()
		);
	}

}
?>