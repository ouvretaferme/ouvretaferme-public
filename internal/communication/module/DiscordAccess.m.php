<?php
namespace communication;

abstract class DiscordAccessElement extends \Element {

	use \FilterElement;

	private static ?DiscordAccessModel $model = NULL;

	const JOINED = 'joined';
	const REVOKED = 'revoked';

	public static function getSelection(): array {
		return DiscordAccess::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): DiscordAccessModel {
		if(self::$model === NULL) {
			self::$model = new DiscordAccessModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('DiscordAccess::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class DiscordAccessModel extends \ModuleModel {

	protected string $module = 'communication\DiscordAccess';
	protected string $package = 'communication';
	protected string $table = 'communicationDiscordAccess';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'user' => ['element32', 'user\User', 'unique' => TRUE, 'cast' => 'element'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'status' => ['enum', [\communication\DiscordAccess::JOINED, \communication\DiscordAccess::REVOKED], 'cast' => 'enum'],
			'discordId' => ['text8', 'cast' => 'string'],
			'refreshToken' => ['text8', 'null' => TRUE, 'cast' => 'string'],
			'joinedAt' => ['datetime', 'cast' => 'string'],
			'revokedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'user', 'structure', 'status', 'discordId', 'refreshToken', 'joinedAt', 'revokedAt'
		]);

		$this->propertiesToModule += [
			'user' => 'user\User',
			'structure' => 'structure\Structure',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['status']
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['user']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'status' :
				return DiscordAccess::JOINED;

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

	public function select(...$fields): DiscordAccessModel {
		return parent::select(...$fields);
	}

	public function where(...$data): DiscordAccessModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): DiscordAccessModel {
		return $this->where('id', ...$data);
	}

	public function whereUser(...$data): DiscordAccessModel {
		return $this->where('user', ...$data);
	}

	public function whereStructure(...$data): DiscordAccessModel {
		return $this->where('structure', ...$data);
	}

	public function whereStatus(...$data): DiscordAccessModel {
		return $this->where('status', ...$data);
	}

	public function whereDiscordId(...$data): DiscordAccessModel {
		return $this->where('discordId', ...$data);
	}

	public function whereRefreshToken(...$data): DiscordAccessModel {
		return $this->where('refreshToken', ...$data);
	}

	public function whereJoinedAt(...$data): DiscordAccessModel {
		return $this->where('joinedAt', ...$data);
	}

	public function whereRevokedAt(...$data): DiscordAccessModel {
		return $this->where('revokedAt', ...$data);
	}


}


abstract class DiscordAccessCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): DiscordAccess {

		$e = new DiscordAccess();

		if(empty($id)) {
			DiscordAccess::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = DiscordAccess::getSelection();
		}

		if(DiscordAccess::model()
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
			$properties = DiscordAccess::getSelection();
		}

		if($sort !== NULL) {
			DiscordAccess::model()->sort($sort);
		}

		return DiscordAccess::model()
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

	public static function getNewElement(array $properties = []): DiscordAccess {

		return new DiscordAccess($properties);

	}

	public static function create(DiscordAccess $e): void {

		DiscordAccess::model()->insert($e);

	}

	public static function update(DiscordAccess $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = DiscordAccess::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, DiscordAccess $e, array $properties): void {

		self::$affected = DiscordAccess::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(DiscordAccess $e): void {

		$e->expects(['id']);

		self::$affected = DiscordAccess::model()->delete($e);

	}

}


class DiscordAccessPage extends \ModulePage {

	protected string $module = 'communication\DiscordAccess';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? DiscordAccessLib::getPropertiesCreate(),
		   $propertiesUpdate ?? DiscordAccessLib::getPropertiesUpdate()
		);
	}

}
?>
