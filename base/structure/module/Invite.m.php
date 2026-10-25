<?php
namespace structure;

abstract class InviteElement extends \Element {

	use \FilterElement;

	private static ?InviteModel $model = NULL;

	const MEMBER = 'member';
	const CUSTOMER = 'customer';
	const PRODUCER = 'producer';

	const PENDING = 'pending';
	const INVALID = 'invalid';

	public static function getSelection(): array {
		return Invite::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): InviteModel {
		if(self::$model === NULL) {
			self::$model = new InviteModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Invite::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class InviteModel extends \ModuleModel {

	protected string $module = 'structure\Invite';
	protected string $package = 'structure';
	protected string $table = 'structureInvite';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'structure' => ['element32', 'structure\Structure', 'cast' => 'element'],
			'email' => ['email', 'null' => TRUE, 'cast' => 'string'],
			'type' => ['enum', [\structure\Invite::MEMBER, \structure\Invite::CUSTOMER, \structure\Invite::PRODUCER], 'cast' => 'enum'],
			'memberRole' => ['element32', 'structure\Role', 'null' => TRUE, 'cast' => 'element'],
			'memberSection' => ['element32', 'structure\Section', 'null' => TRUE, 'cast' => 'element'],
			'member' => ['element32', 'structure\Member', 'null' => TRUE, 'cast' => 'element'],
			'used' => ['int32', 'min' => 0, 'max' => NULL, 'cast' => 'int'],
			'lastUsedAt' => ['date', 'null' => TRUE, 'cast' => 'string'],
			'limit' => ['int32', 'min' => 0, 'max' => NULL, 'null' => TRUE, 'cast' => 'int'],
			'expiresAt' => ['date', 'null' => TRUE, 'cast' => 'string'],
			'key' => ['text8', 'collate' => 'general', 'null' => TRUE, 'unique' => TRUE, 'cast' => 'string'],
			'notifyEmail' => ['bool', 'cast' => 'bool'],
			'status' => ['enum', [\structure\Invite::PENDING, \structure\Invite::INVALID], 'cast' => 'enum'],
			'createdAt' => ['datetime', 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'structure', 'email', 'type', 'memberRole', 'memberSection', 'member', 'used', 'lastUsedAt', 'limit', 'expiresAt', 'key', 'notifyEmail', 'status', 'createdAt'
		]);

		$this->propertiesToModule += [
			'structure' => 'structure\Structure',
			'memberRole' => 'structure\Role',
			'memberSection' => 'structure\Section',
			'member' => 'structure\Member',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['email'],
			['key'],
			['structure'],
			['member']
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['key']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'used' :
				return 0;

			case 'key' :
				return base_convert(bin2hex(random_bytes(6)), 16, 36);

			case 'notifyEmail' :
				return TRUE;

			case 'status' :
				return Invite::PENDING;

			case 'createdAt' :
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

			default :
				return parent::encode($property, $value);

		}

	}

	public function select(...$fields): InviteModel {
		return parent::select(...$fields);
	}

	public function where(...$data): InviteModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): InviteModel {
		return $this->where('id', ...$data);
	}

	public function whereStructure(...$data): InviteModel {
		return $this->where('structure', ...$data);
	}

	public function whereEmail(...$data): InviteModel {
		return $this->where('email', ...$data);
	}

	public function whereType(...$data): InviteModel {
		return $this->where('type', ...$data);
	}

	public function whereMemberRole(...$data): InviteModel {
		return $this->where('memberRole', ...$data);
	}

	public function whereMemberSection(...$data): InviteModel {
		return $this->where('memberSection', ...$data);
	}

	public function whereMember(...$data): InviteModel {
		return $this->where('member', ...$data);
	}

	public function whereUsed(...$data): InviteModel {
		return $this->where('used', ...$data);
	}

	public function whereLastUsedAt(...$data): InviteModel {
		return $this->where('lastUsedAt', ...$data);
	}

	public function whereLimit(...$data): InviteModel {
		return $this->where('limit', ...$data);
	}

	public function whereExpiresAt(...$data): InviteModel {
		return $this->where('expiresAt', ...$data);
	}

	public function whereKey(...$data): InviteModel {
		return $this->where('key', ...$data);
	}

	public function whereNotifyEmail(...$data): InviteModel {
		return $this->where('notifyEmail', ...$data);
	}

	public function whereStatus(...$data): InviteModel {
		return $this->where('status', ...$data);
	}

	public function whereCreatedAt(...$data): InviteModel {
		return $this->where('createdAt', ...$data);
	}


}


abstract class InviteCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Invite {

		$e = new Invite();

		if(empty($id)) {
			Invite::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Invite::getSelection();
		}

		if(Invite::model()
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
			$properties = Invite::getSelection();
		}

		if($sort !== NULL) {
			Invite::model()->sort($sort);
		}

		return Invite::model()
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

	public static function getNewElement(array $properties = []): Invite {

		return new Invite($properties);

	}

	public static function create(Invite $e): void {

		Invite::model()->insert($e);

	}

	public static function update(Invite $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Invite::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Invite $e, array $properties): void {

		self::$affected = Invite::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Invite $e): void {

		$e->expects(['id']);

		self::$affected = Invite::model()->delete($e);

	}

}


class InvitePage extends \ModulePage {

	protected string $module = 'structure\Invite';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? InviteLib::getPropertiesCreate(),
		   $propertiesUpdate ?? InviteLib::getPropertiesUpdate()
		);
	}

}
?>