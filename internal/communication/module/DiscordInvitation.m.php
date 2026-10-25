<?php
namespace communication;

abstract class DiscordInvitationElement extends \Element {

	use \FilterElement;

	private static ?DiscordInvitationModel $model = NULL;

	public static function getSelection(): array {
		return DiscordInvitation::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): DiscordInvitationModel {
		if(self::$model === NULL) {
			self::$model = new DiscordInvitationModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('DiscordInvitation::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class DiscordInvitationModel extends \ModuleModel {

	protected string $module = 'communication\DiscordInvitation';
	protected string $package = 'communication';
	protected string $table = 'communicationDiscordInvitation';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'unique' => TRUE, 'cast' => 'element'],
			'key' => ['text8', 'unique' => TRUE, 'cast' => 'string'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'key', 'createdAt'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
		];

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['structure'],
			['key']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'key' :
				return bin2hex(random_bytes(6));

			case 'createdAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function select(...$fields): DiscordInvitationModel {
		return parent::select(...$fields);
	}

	public function where(...$data): DiscordInvitationModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): DiscordInvitationModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): DiscordInvitationModel {
		return $this->where('structure', ...$data);
	}

	public function whereKey(...$data): DiscordInvitationModel {
		return $this->where('key', ...$data);
	}

	public function whereCreatedAt(...$data): DiscordInvitationModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class DiscordInvitationCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): DiscordInvitation {

		$e = new DiscordInvitation();

		if(empty($id)) {
			DiscordInvitation::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = DiscordInvitation::getSelection();
		}

		if(DiscordInvitation::model()
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
			$properties = DiscordInvitation::getSelection();
		}

		if($sort !== NULL) {
			DiscordInvitation::model()->sort($sort);
		}

		return DiscordInvitation::model()
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

	public static function getNewElement(array $properties = []): DiscordInvitation {

		return new DiscordInvitation($properties);

	}

	public static function create(DiscordInvitation $e): void {

		DiscordInvitation::model()->insert($e);

	}

	public static function update(DiscordInvitation $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = DiscordInvitation::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, DiscordInvitation $e, array $properties): void {

		self::$affected = DiscordInvitation::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(DiscordInvitation $e): void {

		$e->expects(['id']);

		self::$affected = DiscordInvitation::model()->delete($e);

	}

}


class DiscordInvitationPage extends \ModulePage {

	protected string $module = 'communication\DiscordInvitation';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? DiscordInvitationLib::getPropertiesCreate(),
		   $propertiesUpdate ?? DiscordInvitationLib::getPropertiesUpdate()
		);
	}

}
?>