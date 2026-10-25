<?php
namespace structure;

abstract class StructureElement extends \Element {

	use \FilterElement;

	private static ?StructureModel $model = NULL;

	const PRODUCER = 'producer';
	const SHOP = 'shop';
	const AMAP = 'amap';
	const COMMUNITY = 'community';

	const EDUCATION = 'education';
	const NETWORK = 'network';
	const ACCOUNTING = 'accounting';

	const REAL = 'real';
	const PROJECT = 'project';
	const TEST = 'test';
	const SCHOOL = 'school';
	const LEARNER = 'learner';
	const INSTALLATION = 'installation';
	const TRAINING = 'training';

	const DUPLICATION = 'duplication';

	const PUBLIC = 'public';
	const MEMBERS = 'members';
	const NONE = 'none';

	const NO = 'no';
	const YES = 'yes';
	const CONVERSION = 'conversion';

	const DEMETER = 1;
	const NATURE_PROGRES = 2;
	const BIO_COHERENCE = 4;

	const ACTIVE = 'active';
	const CLOSED = 'closed';

	public static function getSelection(): array {
		return Structure::model()->getProperties();
	}

	public static function resetModel(): void {
		self::$model = NULL;
	}

	public static function model(): StructureModel {
		if(self::$model === NULL) {
			self::$model = new StructureModel();
		}
		return self::$model;
	}

	public static function fail(string|\FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		return \Fail::log('Structure::'.$failName, $arguments, $wrapper);
	}

	public static function getValidator(string $property): \PropertyValidator {
		return new \PropertyValidator(
			self::model(),
			$property,
		);
	}

}


class StructureModel extends \ModuleModel {

	protected string $module = 'structure\Structure';
	protected string $package = 'structure';
	protected string $table = 'structure';

	public function __construct() {

		parent::__construct();

		$this->properties = array_merge($this->properties, [
			'id' => ['serial32', 'cast' => 'int'],
			'name' => ['text8', 'min' => 1, 'max' => NULL, 'collate' => 'general', 'cast' => 'string'],
			'type' => ['enum', [\structure\Structure::PRODUCER, \structure\Structure::SHOP, \structure\Structure::AMAP, \structure\Structure::COMMUNITY], 'cast' => 'enum'],
			'communityType' => ['enum', [\structure\Structure::PRODUCER, \structure\Structure::EDUCATION, \structure\Structure::NETWORK, \structure\Structure::ACCOUNTING], 'null' => TRUE, 'cast' => 'enum'],
			'producerType' => ['enum', [\structure\Structure::REAL, \structure\Structure::PROJECT, \structure\Structure::TEST, \structure\Structure::SCHOOL, \structure\Structure::LEARNER, \structure\Structure::INSTALLATION, \structure\Structure::TRAINING], 'null' => TRUE, 'cast' => 'enum'],
			'parent' => ['element32', 'structure\Structure', 'null' => TRUE, 'cast' => 'element'],
			'source' => ['element32', 'structure\Structure', 'null' => TRUE, 'cast' => 'element'],
			'creation' => ['enum', [\structure\Structure::DUPLICATION], 'null' => TRUE, 'cast' => 'enum'],
			'producerTypeUpdatedAt' => ['datetime', 'null' => TRUE, 'cast' => 'string'],
			'isLegal' => ['bool', 'cast' => 'bool'],
			'legalName' => ['text8', 'null' => TRUE, 'cast' => 'string'],
			'legalEmail' => ['email', 'cast' => 'string'],
			'siret' => ['text8', 'min' => 1, 'max' => NULL, 'null' => TRUE, 'cast' => 'string'],
			'verifiedCountry' => ['bool', 'cast' => 'bool'],
			'legalStreet1' => ['text8', 'null' => TRUE, 'cast' => 'string'],
			'legalStreet2' => ['text8', 'null' => TRUE, 'cast' => 'string'],
			'legalPostcode' => ['text8', 'null' => TRUE, 'cast' => 'string'],
			'legalCity' => ['text8', 'null' => TRUE, 'cast' => 'string'],
			'legalCountry' => ['element32', 'user\Country', 'null' => TRUE, 'cast' => 'element'],
			'legalCategory' => ['int16', 'min' => 1000, 'max' => 9999, 'null' => TRUE, 'cast' => 'int'],
			'url' => ['url', 'null' => TRUE, 'cast' => 'string'],
			'description' => ['editor24', 'null' => TRUE, 'cast' => 'string'],
			'logo' => ['textFixed', 'min' => 30, 'max' => 30, 'charset' => 'ascii', 'null' => TRUE, 'cast' => 'string'],
			'banner' => ['textFixed', 'min' => 30, 'max' => 30, 'charset' => 'ascii', 'null' => TRUE, 'cast' => 'string'],
			'profileVisibility' => ['enum', [\structure\Structure::PUBLIC, \structure\Structure::MEMBERS, \structure\Structure::NONE], 'cast' => 'enum'],
			'organic' => ['enum', [\structure\Structure::NO, \structure\Structure::YES, \structure\Structure::CONVERSION], 'null' => TRUE, 'cast' => 'enum'],
			'qualities' => ['set', [\structure\Structure::DEMETER, \structure\Structure::NATURE_PROGRES, \structure\Structure::BIO_COHERENCE], 'null' => TRUE, 'cast' => 'set'],
			'membership' => ['bool', 'null' => TRUE, 'cast' => 'bool'],
			'version' => ['int16', 'cast' => 'int'],
			'interstitials' => ['json', 'cast' => 'array'],
			'consentData' => ['bool', 'null' => TRUE, 'cast' => 'bool'],
			'startedAt' => ['int16', 'min' => \Clock::year() - 100, 'max' => \Clock::year() + 10, 'null' => TRUE, 'cast' => 'int'],
			'createdAt' => ['datetime', 'cast' => 'string'],
			'status' => ['enum', [\structure\Structure::ACTIVE, \structure\Structure::CLOSED], 'cast' => 'enum'],
		]);

		$this->propertiesList = array_merge($this->propertiesList, [
			'id', 'name', 'type', 'communityType', 'producerType', 'parent', 'source', 'creation', 'producerTypeUpdatedAt', 'isLegal', 'legalName', 'legalEmail', 'siret', 'verifiedCountry', 'legalStreet1', 'legalStreet2', 'legalPostcode', 'legalCity', 'legalCountry', 'legalCategory', 'url', 'description', 'logo', 'banner', 'profileVisibility', 'organic', 'qualities', 'membership', 'version', 'interstitials', 'consentData', 'startedAt', 'createdAt', 'status'
		]);

		$this->propertiesToModule += [
			'parent' => 'structure\Structure',
			'source' => 'structure\Structure',
			'legalCountry' => 'user\Country',
		];

		$this->indexConstraints = array_merge($this->indexConstraints, [
			['type', 'status', 'producerType']
		]);

	}

	public function getDefaultValue(string $property) {

		switch($property) {

			case 'type' :
				return Structure::PRODUCER;

			case 'isLegal' :
				return FALSE;

			case 'verifiedCountry' :
				return FALSE;

			case 'profileVisibility' :
				return Structure::NONE;

			case 'membership' :
				return FALSE;

			case 'version' :
				return \data\MigrateLib::VERSION * 10;

			case 'interstitials' :
				return [];

			case 'createdAt' :
				return new \Sql('NOW()');

			case 'status' :
				return Structure::ACTIVE;

			default :
				return parent::getDefaultValue($property);

		}

	}

	public function encode(string $property, $value) {

		switch($property) {

			case 'type' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'communityType' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'producerType' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'creation' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'profileVisibility' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'organic' :
				return ($value === NULL) ? NULL : (string)$value;

			case 'interstitials' :
				return $value === NULL ? NULL : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

			case 'status' :
				return ($value === NULL) ? NULL : (string)$value;

			default :
				return parent::encode($property, $value);

		}

	}

	public function decode(string $property, $value) {

		switch($property) {

			case 'interstitials' :
				return $value === NULL ? NULL : json_decode($value, TRUE);

			default :
				return parent::decode($property, $value);

		}

	}

	public function select(...$fields): StructureModel {
		return parent::select(...$fields);
	}

	public function where(...$data): StructureModel {
		return parent::where(...$data);
	}

	public function whereId(...$data): StructureModel {
		return $this->where('id', ...$data);
	}

	public function whereName(...$data): StructureModel {
		return $this->where('name', ...$data);
	}

	public function whereType(...$data): StructureModel {
		return $this->where('type', ...$data);
	}

	public function whereCommunityType(...$data): StructureModel {
		return $this->where('communityType', ...$data);
	}

	public function whereProducerType(...$data): StructureModel {
		return $this->where('producerType', ...$data);
	}

	public function whereParent(...$data): StructureModel {
		return $this->where('parent', ...$data);
	}

	public function whereSource(...$data): StructureModel {
		return $this->where('source', ...$data);
	}

	public function whereCreation(...$data): StructureModel {
		return $this->where('creation', ...$data);
	}

	public function whereProducerTypeUpdatedAt(...$data): StructureModel {
		return $this->where('producerTypeUpdatedAt', ...$data);
	}

	public function whereIsLegal(...$data): StructureModel {
		return $this->where('isLegal', ...$data);
	}

	public function whereLegalName(...$data): StructureModel {
		return $this->where('legalName', ...$data);
	}

	public function whereLegalEmail(...$data): StructureModel {
		return $this->where('legalEmail', ...$data);
	}

	public function whereSiret(...$data): StructureModel {
		return $this->where('siret', ...$data);
	}

	public function whereVerifiedCountry(...$data): StructureModel {
		return $this->where('verifiedCountry', ...$data);
	}

	public function whereLegalStreet1(...$data): StructureModel {
		return $this->where('legalStreet1', ...$data);
	}

	public function whereLegalStreet2(...$data): StructureModel {
		return $this->where('legalStreet2', ...$data);
	}

	public function whereLegalPostcode(...$data): StructureModel {
		return $this->where('legalPostcode', ...$data);
	}

	public function whereLegalCity(...$data): StructureModel {
		return $this->where('legalCity', ...$data);
	}

	public function whereLegalCountry(...$data): StructureModel {
		return $this->where('legalCountry', ...$data);
	}

	public function whereLegalCategory(...$data): StructureModel {
		return $this->where('legalCategory', ...$data);
	}

	public function whereUrl(...$data): StructureModel {
		return $this->where('url', ...$data);
	}

	public function whereDescription(...$data): StructureModel {
		return $this->where('description', ...$data);
	}

	public function whereLogo(...$data): StructureModel {
		return $this->where('logo', ...$data);
	}

	public function whereBanner(...$data): StructureModel {
		return $this->where('banner', ...$data);
	}

	public function whereProfileVisibility(...$data): StructureModel {
		return $this->where('profileVisibility', ...$data);
	}

	public function whereOrganic(...$data): StructureModel {
		return $this->where('organic', ...$data);
	}

	public function whereQualities(...$data): StructureModel {
		return $this->where('qualities', ...$data);
	}

	public function whereMembership(...$data): StructureModel {
		return $this->where('membership', ...$data);
	}

	public function whereVersion(...$data): StructureModel {
		return $this->where('version', ...$data);
	}

	public function whereInterstitials(...$data): StructureModel {
		return $this->where('interstitials', ...$data);
	}

	public function whereConsentData(...$data): StructureModel {
		return $this->where('consentData', ...$data);
	}

	public function whereStartedAt(...$data): StructureModel {
		return $this->where('startedAt', ...$data);
	}

	public function whereCreatedAt(...$data): StructureModel {
		return $this->where('createdAt', ...$data);
	}

	public function whereStatus(...$data): StructureModel {
		return $this->where('status', ...$data);
	}


}


abstract class StructureCrud extends \ModuleCrud {

 private static array $cache = [];
 private static ?int $affected = NULL;

	public static function getById(mixed $id, array $properties = []): Structure {

		$e = new Structure();

		if(empty($id)) {
			Structure::model()->reset();
			return $e;
		}

		if($properties === []) {
			$properties = Structure::getSelection();
		}

		if(Structure::model()
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
			$properties = Structure::getSelection();
		}

		if($sort !== NULL) {
			Structure::model()->sort($sort);
		}

		return Structure::model()
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

	public static function getNewElement(array $properties = []): Structure {

		return new Structure($properties);

	}

	public static function create(Structure $e): void {

		Structure::model()->insert($e);

	}

	public static function update(Structure $e, array $properties): void {

		$e->expects(['id']);

		self::$affected = Structure::model()
			->select($properties)
			->update($e);

	}

	public static function updateCollection(\Collection $c, Structure $e, array $properties): void {

		self::$affected = Structure::model()
			->select($properties)
			->whereId('IN', $c)
			->update($e->extracts($properties));

	}

	public static function delete(Structure $e): void {

		$e->expects(['id']);

		self::$affected = Structure::model()->delete($e);

	}

}


class StructurePage extends \ModulePage {

	protected string $module = 'structure\Structure';

	public function __construct(
	   ?\Closure $start = NULL,
	   \Closure|array|null $propertiesCreate = NULL,
	   \Closure|array|null $propertiesUpdate = NULL
	) {
		parent::__construct(
		   $start,
		   $propertiesCreate ?? StructureLib::getPropertiesCreate(),
		   $propertiesUpdate ?? StructureLib::getPropertiesUpdate()
		);
	}

}
?>