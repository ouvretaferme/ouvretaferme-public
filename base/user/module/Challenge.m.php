<?php
namespace user;

abstract class ChallengeElement extends \Element {

	use \FilterElement;

	private static ?ChallengeModel $model = NULL;

	const WEB = 'web';
	const APP = 'app';
	const MOBILE_WEB = 'mobile-web';
	const TABLET_WEB = 'tablet-web';
	const CRAWLER = 'crawler';

	const CODE = 'code';
	const LINK = 'link';
	const FAILURES = 'failures';
	const RESENT = 'resent';
	const EXPIRED = 'expired';

	public static function getSelection(): array {
		return Challenge::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): ChallengeModel {
		if(self::$model === NULL) {
			self::$model = new ChallengeModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Challenge::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class ChallengeModel extends \ModuleModel {

	protected string $module = 'user\Challenge';
	protected string $package = 'user';
	protected string $table = 'userChallenge';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'user' => ['element32', 'user\User', 'cast' => 'element'],
			'code' => ['text8', 'min' => 1, 'max' => 255, 'cast' => 'string'],
			'token' => ['textFixed', 'min' => 64, 'max' => 64, 'charset' => 'ascii', 'unique' => TRUE, 'cast' => 'string'],
			'ip' => ['ipv4', 'cast' => 'string'],
			'device' => ['enum', [\user\Challenge::WEB, \user\Challenge::APP, \user\Challenge::MOBILE_WEB, \user\Challenge::TABLET_WEB, \user\Challenge::CRAWLER], 'cast' => 'enum'],
			'createdAt' => ['datetime', 'cast' => 'string'],
			'expiresAt' => ['datetime', 'cast' => 'string'],
			'closedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'closedBy' => ['enum', [\user\Challenge::CODE, \user\Challenge::LINK, \user\Challenge::FAILURES, \user\Challenge::RESENT, \user\Challenge::EXPIRED], 'null' => TRUE, 'cast' => 'enum'],
			'trustedUntil' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'user', 'code', 'token', 'ip', 'device', 'createdAt', 'expiresAt', 'closedAt', 'closedBy', 'trustedUntil'
		]);

		$this->propertiesToModule += [
			'user' => 'user\User',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['user', 'createdAt']
		]);

		$this->uniqueConstraints = array_merge($this->uniqueConstraints, [
			['token']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'ip' :
				return getIp();

			case 'createdAt' :
				return new \Sql('NOW()');

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'ip' :
				return $value === NULL ? NULL : (int)first(unpack('l', pack('l', ip2long($value))));

			case 'device' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'closedBy' :
				return ($value === NULL) ? NULL : (string)$value;

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

	public function select(...$fields): ChallengeModel {
		return parent::select(...$fields);
	}

	public function where(...$data): ChallengeModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): ChallengeModel {
		return $this->where('id', ...$data);
	}

	public function whereUser(...$data): ChallengeModel {
		return $this->where('user', ...$data);
	}

	public function whereCode(...$data): ChallengeModel {
		return $this->where('code', ...$data);
	}

	public function whereToken(...$data): ChallengeModel {
		return $this->where('token', ...$data);
	}

	public function whereIp(...$data): ChallengeModel {
		return $this->where('ip', ...$data);
	}

	public function whereDevice(...$data): ChallengeModel {
		return $this->where('device', ...$data);
	}

	public function whereCreatedAt(...$data): ChallengeModel {
		return $this->where('createdAt', ...$data);
	}

	public function whereExpiresAt(...$data): ChallengeModel {
		return $this->where('expiresAt', ...$data);
	}

	public function whereClosedAt(...$data): ChallengeModel {
		return $this->where('closedAt', ...$data);
	}

	public function whereClosedBy(...$data): ChallengeModel {
		return $this->where('closedBy', ...$data);
	}

	public function whereTrustedUntil(...$data): ChallengeModel {
		return $this->where('trustedUntil', ...$data);
	}


}


abstract class ChallengeCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Challenge {

		$e = new Challenge();

		if(empty($id)) {
			Challenge::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Challenge::getSelection();
		}

		if(Challenge::model()
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
			$properties = Challenge::getSelection();
		}

		if($sort !== NULL) {
			Challenge::model()->sort($sort);
		}

		return Challenge::model()
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

	public static function getNewElement(array $properties = []): Challenge {

		return new Challenge($properties);

	}

	public static function create(Challenge $e): void {

		Challenge::model()->insert($e);

	}

	public static function update(Challenge $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Challenge::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Challenge $e, array $properties): void {

		self::$affected = Challenge::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Challenge $e): void {

		$e->expects(['id']);

		self::$affected = Challenge::model()->delete($e);

	}

}


class ChallengePage extends \ModulePage {

	protected string $module = 'user\Challenge';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? ChallengeAiLib::getPropertiesCreate(),
		   $propertiesUpdate ?? ChallengeAiLib::getPropertiesUpdate()
		);
	}

}
?>