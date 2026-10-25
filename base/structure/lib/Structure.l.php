<?php
namespace structure;

class StructureLib extends StructureCrud {

	use \Notifiable;

	public static function getPropertiesCreate(?string $origin = NULL): \Closure {

		return function(Structure $e) {

			$e->expects(['type']);

			$properties = [];

			if($e['type'] === Structure::COMMUNITY) {
				$properties[] = 'communityType';
			}

			if($e['type'] === Structure::PRODUCER) {
				$properties[] = 'producerType';
			}

			$properties = array_merge($properties, ['isLegal', 'name', 'legalEmail', 'legalCountry', 'startedAt']);

			if($e->acceptMarks()) {
				$properties[] = 'organic';
				$properties[] = 'qualities';
			}

			return $properties;

		};
	}

	public static function getPropertiesUpdate(?string $origin = NULL): \Closure {

		return function(Structure $e) {

			$properties = [];

			if($e->acceptUpdateProducerType()) {
				$properties[] = 'producerType';
			}

			$properties = array_merge($properties, ['name', 'legalEmail']);

			if($e->acceptPresentation()) {
				$properties = array_merge($properties, ['description', 'startedAt', 'url']);
			}

			if($e->acceptMarks()) {
				$properties[] = 'organic';
				$properties[] = 'qualities';
			}

			if(
				$e->isLegal() and
				$e->isVerifiedCountry()
			) {

				if($e->isLegalInfo()) {

					$properties = array_merge($properties, ['legalName', 'legalStreet1', 'legalStreet2', 'legalPostcode', 'legalCity', 'legalCategory']);

					if($e->isFrance()) {
						$properties[] = 'siret';
					}

				}

			} else {
				$properties[] = 'legalCountry';
			}

			return $properties;

		};

	}

	public static function getPropertiesLegal(Structure $e): array {

		$properties = ['legalName', 'legalStreet1', 'legalStreet2', 'legalPostcode', 'legalCity', 'legalCategory'];

		if($e->isVerifiedCountry() === FALSE) {
			$properties[] = 'legalCountry';
			$properties[] = 'verifiedCountry';
		}

		if($e->isFrance()) {
			$properties[] = 'siret';
		}

		return $properties;

	}

	public static function getOnline(): \Collection {

		static $ccStructureOnline = [];

		$eUserOnline = \user\ConnectionLib::getOnline();
		$key = $eUserOnline->empty() ? 0 : $eUserOnline['id'];

		$ccStructureOnline[$key] ??= self::getByUser(
			$eUserOnline,
			withFeature: TRUE,
			index: ['type', 'id']
		);

		return $ccStructureOnline[$key];

	}

	public static function getForMigrate(): \Collection {

		return Structure::model()
			->select(StructureElement::getSelection())
			->whereVersion('<', \data\MigrateLib::VERSION * 10)
			->sort([
				'id' => SORT_ASC
			])
			->getCollection();

	}


	/**
	 * Ramène sous la version donnée les structures qui l'ont passée, et rend leur nombre (`data/migrate
	 * replay=<N>`, en dev seulement). Une version en +5 a une migration en cours, que `MigrateLib::live()`
	 * ne reprend jamais, et reste telle. Une écriture de masse, d'où le tableau de valeurs.
	 */
	public static function rewindVersion(int $version, Structure $e = new Structure()): int {

		return Structure::model()
			->whereId($e, if: $e->notEmpty())
			->whereVersion('>', $version)
			->where('version % 10 = 0')
			->update([
				'version' => $version
			]);

	}

	public static function checkCharter(array $input): void {

		if((bool)($input['charter'] ?? FALSE) === FALSE) {
			Structure::fail('charter.accepted');
		}

	}

	/**
	 * Les fermes de ce groupe dont cette personne est administratrice aujourd'hui.
	 *
	 * Ce n'est pas « celles qu'elle y a mises », et la nuance porte : le périmètre appartient au
	 * couple (ferme, groupe) et non au triplet, donc la carte qui l'affiche et le geste qui la
	 * retire valent pour la ferme, quel que soit l'associé qui l'a rattachée. C'est déjà le
	 * contrôle de doRemoveStructure(), qui exige d'être administrateur maintenant et se moque de
	 * savoir qui a rattaché — un écran bâti sur l'auteur proposait donc un geste que l'action
	 * refusait, à qui a perdu son rôle depuis.
	 *
	 * Le périmètre accumulé entre associés vient de getStructuresByCommunity() et n'est pas relu
	 * ici : deux lectures du même périmètre finiraient par diverger sur l'une des deux.
	 */
	public static function getSharedByUser(\user\User $eUser, Structure $eStructureCommunity): \Collection {

		return self::findSharedByUser($eUser, AffiliationAiLib::getStructuresByCommunity($eStructureCommunity, $eUser));

	}

	/**
	 * Le choix de getSharedByUser(), parmi des fermes déjà regroupées par
	 * `AffiliationAiLib::groupByStructure()` : le gabarit d'un groupe lit les rattachements une
	 * fois pour son rail et pour ce menu.
	 */
	public static function findSharedByUser(\user\User $eUser, \Collection $cStructure): \Collection {

		if($cStructure->empty()) {
			return $cStructure;
		}

		$administered = self::getAdministeredIds($eUser, $cStructure);

		return $cStructure
			->find(fn($eStructure) => in_array($eStructure['id'], $administered))
			->sort(['name' => SORT_ASC]);

	}

	/**
	 * Parmi ces fermes, celles que cette personne administre — le rôle d'aujourd'hui, pas celui
	 * qu'elle avait au rattachement.
	 */
	private static function getAdministeredIds(\user\User $eUser, \Collection $cStructure): array {

		return Structure::model()
			->join(Member::model(), 'm1.id = m2.structure')
			->join(Role::model(), 'm2.role = m3.id')
			->select(['id'])
			->where('m1.id', 'IN', $cStructure)
			->where('m2.user', $eUser)
			->where('m2.status', Member::IN)
			->where('m2.structureStatus', Member::ACTIVE)
			->where('m3.admin', TRUE)
			->getCollection()
			->getIds();

	}

	/**
	 * Les fermes que cette personne peut encore apporter à ce groupe.
	 *
	 * L'anti-jointure ignore l'auteur : une ferme déjà rattachée par un associé n'est plus à
	 * partager, elle est partagée. La proposer rouvrirait, au nom d'un second associé de bonne
	 * foi, le périmètre que le premier avait fermé.
	 */
	public static function getShareableByUser(\user\User $eUser, Structure $eStructureCommunity): \Collection {

		return Structure::model()
			->join(Member::model(), 'm1.id = m2.structure')
			->join(Affiliation::model(), 'm1.id = m3.structureProducer AND m3.structureCommunity = '.Affiliation::model()->format($eStructureCommunity), 'LEFT')
			->join(Role::model(), 'm2.role = m4.id')
			->select(Structure::getSelection())
			->where('m1.type', Structure::PRODUCER)
			->where('m1.producerType', 'NOT IN', StructureSetting::PRODUCER_TYPES_COLLECTIVE)
			->where('m1.status', Structure::ACTIVE)
			->where('m2.user', $eUser)
			->where('m2.status', Member::IN)
			->where('m2.structureStatus', Member::ACTIVE)
			->where('m3.id IS NULL')
			->where('m4.admin', TRUE)
			->sort(['m1.name' => SORT_ASC])
			->getCollection(index: 'id');

	}

	public static function getInstalledSql(): \Sql {

		// Pas Structure::model() : la requête qui cite celle-ci peut être en train d'employer le singleton.
		return new StructureModel()
			->select(['id'])
			->whereType(Structure::PRODUCER)
			->whereProducerType(Structure::REAL)
			->whereId('NOT IN', StructureSetting::FIXTURE_STRUCTURES)
			->getSql();

	}

	public static function checkInstalled(Structure $eStructure): bool {

		if($eStructure->isInstalled()) {
			return TRUE;
		}

		Structure::fail('notInstalled', ['producerType' => $eStructure->producerType()]);

		return FALSE;

	}

	/**
	 * Le décor est désigné par `StructureSetting::FIXTURE_STRUCTURES` et non par `producerType` : ses
	 * fermes sont `REAL`, pour franchir les mêmes gardes qu'une ferme installée.
	 */
	public static function getNotInstalledSql(): \Sql {

		return new StructureModel()
			->select(['id'])
			->or(
				fn() => $this
					->whereType(Structure::PRODUCER)
					->whereProducerType('!=', Structure::REAL),
				fn() => $this->whereId('IN', StructureSetting::FIXTURE_STRUCTURES)
			)
			->getSql();

	}

	/**
	 * Les fermes école du site.
	 *
	 * La liste des identifiants vient de `StructureSetting::SCHOOL_STRUCTURES` et non d'une
	 * requête sur `producerType` : le statut de ferme école est une décision de déploiement, pas
	 * un état qu'on découvre. La conséquence est assumée — une structure `SCHOOL` absente de la
	 * constante n'apparaît nulle part et le script refuse de la mettre à jour.
	 *
	 * Le `producerType` reste vérifié ici : la constante dit lesquelles, la colonne dit qu'elles
	 * le sont bien. Une des deux qui mentirait se verrait, plutôt que d'ouvrir une ferme réelle.
	 */
	public static function getSchools(): \Collection {

		if(StructureSetting::SCHOOL_STRUCTURES === []) {
			return new \Collection();
		}

		return Structure::model()
			->select(Structure::getSelection())
			->whereId('IN', array_keys(StructureSetting::SCHOOL_STRUCTURES))
			->whereType(Structure::PRODUCER)
			->whereProducerType(Structure::SCHOOL)
			->whereStatus(Structure::ACTIVE)
			->sort(['name' => SORT_ASC])
			->getCollection(index: 'id');

	}

	public static function getProjectByCommunity(Structure $eStructureCommunity): \Collection {

		if($eStructureCommunity['communityType'] === Structure::ACCOUNTING) {
			return new \Collection();
		}

		return Structure::model()
			->select(Structure::getSelection())
			->whereParent($eStructureCommunity)
			->whereProducerType(Structure::PROJECT)
			->whereStatus(Structure::ACTIVE)
			->sort(['name' => SORT_ASC])
			->getCollection();

	}

	public static function getByUser(\user\User $eUser, ?string $type = NULL, bool $withFeature = FALSE, array $index = ['id']): \Collection {

		if($withFeature) {

			Structure::model()->select([
				'feature' => Feature::model()
					->select(Feature::getSelection())
					->delegateElement('structure')
			]);

		}

		return Structure::model()
			->join(Member::model(), 'm1.id = m2.structure')
			->select(Structure::getSelection())
			->where('m1.type', $type, if: $type !== NULL)
			->where('m2.user', $eUser)
			->where('m2.status', Member::IN)
			->where('m1.status', Structure::ACTIVE)
			->sort(new \Sql('FIELD(type, "'.Structure::PRODUCER.'", "'.Structure::SHOP.'", "'.Structure::AMAP.'"), m1.id ASC'))
			->getCollection(NULL, NULL, $index);

	}

	public static function hasProducerByUser(\user\User $eUser): bool {

		return Structure::model()
			->join(Member::model(), 'm1.id = m2.structure')
			->where('m1.type', Structure::PRODUCER)
			->where('m1.producerType', 'NOT IN', StructureSetting::PRODUCER_TYPES_COLLECTIVE)
			->where('m1.status', Structure::ACTIVE)
			->where('m2.user', $eUser)
			->where('m2.status', Member::IN)
			->exists();

	}

	public static function countByUser(\user\User $eUser): int {

		return Structure::model()
			->join(Member::model(), 'm1.id = m2.structure')
			->select(Structure::getSelection())
			->where('m2.user', $eUser)
			->where('m1.status', Structure::ACTIVE)
			->count();

	}

	public static function countConsentData(): int {

		return Structure::model()
			->whereConsentData(TRUE)
			->whereStatus(Structure::ACTIVE)
			->whereId('NOT IN', StructureSetting::FIXTURE_STRUCTURES)
			->count();

	}

	public static function getByUsers(\Collection $cUser, ?string $type = NULL): \Collection {

		return Structure::model()
			->select(Structure::getSelection())
			->join(Member::model(), 'm1.id = m2.structure')
			->where('m2.user', 'IN', $cUser)
			->where('m1.type', $type, if: ($type !== NULL))
			->where('m1.status', Structure::ACTIVE)
			->sort(new \Sql('FIELD(type, "'.Structure::PRODUCER.'", "'.Structure::SHOP.'", "'.Structure::AMAP.'"), m1.id ASC'))
			->getCollection();

	}

	public static function load(\stdClass $data, bool $accounting = FALSE, string $parameterName = 'structure', bool $summary = FALSE): Structure {

		if($data->__page['type'] !== 'remote') {
			\user\ConnectionLib::checkLogged();
		}

		$data->eStructure = \structure\StructureLib::getById(REQUEST($parameterName));

		if($data->__page['type'] === 'remote') {
			$data->eStructure->validate();
		} else {

			$data->eStructure->validate('canRead');

			MemberLib::visit($data->eStructure);

		}

		\structure\StructureLib::connectDatabase($data->eStructure);

		self::loadAccounting($data->eStructure);

		if($accounting) {
			self::validateAccounting($data, software: $summary === FALSE);
		}

		return $data->eStructure;


	}

	/**
	 * L'entrée des cagnottes au menu des ventes, que la structure affiche ou masque dans les réglages
	 * des ventes, absents de la version publique.
	 */
	public static function hasMenuWallet(Structure $eStructure): bool {

		if(StructureSetting::hasCommercialisationApp() === FALSE) {
			return FALSE;
		}

		return in_array(TRUE, self::notify('hasMenuWallet', $eStructure), TRUE);

	}

	/**
	 * Les pages de l'espace d'adhérent que le compte connecté ouvre dans l'AMAP, pour ses menus de
	 * groupe (`MenuLib::getAmapGroupMenus()`). La fiche d'adhérent vit dans le code fermé, qui seul
	 * sait la lire.
	 */
	public static function getAmapSpace(Structure $eStructure): array {

		if(StructureSetting::hasCommercialisationApp() === FALSE) {
			return [];
		}

		return array_merge(...self::notify('getAmapSpace', $eStructure));

	}

	/**
	 * Un compte Stripe Express ne sert qu'à recevoir ce que la commercialisation reverse à la structure,
	 * absente de la version publique.
	 */
	public static function isStripeExpressEligible(Structure $eStructure): bool {

		if(StructureSetting::hasCommercialisationApp() === FALSE) {
			return FALSE;
		}

		return self::notify('isStripeExpressEligible', $eStructure)[0];

	}

	/**
	 * La ferme qui donne accès à chacune de ces AMAP, pour celui qui les administre toutes deux, en
	 * `[identifiant d'AMAP => identifiant de ferme]`. Le lien entre une AMAP et ses fermes vit dans le
	 * code fermé.
	 */
	public static function getFarmByAmap(\Collection $cStructureAmap, \Collection $cStructureProducer): array {

		if(StructureSetting::hasCommercialisationApp() === FALSE) {
			return [];
		}

		return self::notify('getFarmByAmap', $cStructureAmap, $cStructureProducer)[0];

	}

	public static function validateAccounting(\stdClass $data, bool $software = TRUE): void {

		if($software) {
			self::validateSoftware($data, 'accounting');
		}

		if($data->eStructure->hasAccounting() === FALSE) {
			throw new \RedirectAction('/comptabilite/decouvrir?structure='.$data->eStructure['id'], 'replace');
		}

	}

	// Les exercices sont posés par financialYear\StructureObserverLib : l'export public n'a pas accounting/.
	public static function loadAccounting(Structure $eStructure): void {

		$eStructure['cFinancialYear'] = new \Collection();

		self::notify('loadAccounting', $eStructure);

	}

	// Sans association, aucune structure ne paie d'adhésion : toutes ont ce qu'elle donne.
	public static function isMembershipFree(): bool {

		if(StructureSetting::hasAssociationPackage() === FALSE) {
			return TRUE;
		}

		return self::notify('isMembershipFree')[0];

	}

	/**
	 * Les déclarations de TVA dont la période finit à partir de cette date, rendues par vat\StructureObserverLib
	 * avec `blocked` sur celles qui ne pourront plus générer leurs écritures. `$hasVat` est la redevabilité qui
	 * vaudra à partir de la date, NULL quand c'est l'exigibilité qui change.
	 */
	public static function getVatDeclarationsFrom(Structure $eStructure, string $date, ?bool $hasVat): \Collection {

		if(
			StructureSetting::hasAccountingApp() === FALSE or
			$eStructure->hasAccounting() === FALSE
		) {
			return new \Collection();
		}

		return self::notify('getVatDeclarationsFrom', $eStructure, $date, $hasVat)[0];

	}

	/**
	 * L'exercice qui couvre cette date est-il tenu au livre des recettes, rendu par financialYear\StructureObserverLib.
	 * Toute vente y va au compte de ventes (preaccounting\RatioTransactionLib::getAccountFromItem()) : un compte
	 * posé sur un produit ou un article n'y serait lu par personne.
	 */
	public static function isCashReceipts(Structure $eStructure, string $date): bool {

		if(
			StructureSetting::hasAccountingApp() === FALSE or
			$eStructure->hasAccounting() === FALSE
		) {
			return FALSE;
		}

		return self::notify('isCashReceipts', $eStructure, $date)[0];

	}

	// Les invitations d'AMAP qui attendent la personne (`cInvite`) et le lien pour en rejoindre une
	// (`amapJoin`) sont posés par amap\StructureObserverLib, et seuls les observateurs d'interface de
	// l'AMAP les lisent : aucun défaut ne les précède.
	public static function loadStart(Structure $eStructure, ?string $inviteKey): void {

		if(StructureSetting::hasCommercialisationApp()) {
			self::notify('loadStart', $eStructure, $inviteKey);
		}

	}

	public static function loadAccountingApi(Structure $eStructure): void {
		self::notify('loadAccountingApi', $eStructure);
	}

	// La collecte qu'un réseau ouvre au public (`eCollectPublic`) est posée par quotation\StructureObserverLib,
	// et aucun défaut ne la précède : il nommerait une classe que l'export public n'a pas. Ne s'émet
	// et ne se lit que pour un réseau qui tient des relevés (`acceptGroupQuotation()`).
	public static function loadCommunityPublic(Structure $eStructure): void {
		self::notify('loadCommunityPublic', $eStructure);
	}

	// R-61 : un geste que l'offre ferme ne s'affiche pas, et la route le refuse quand même pour un écran resté ouvert ou une requête forgée. Le nom sans point porte le message dans le bandeau global.
	public static function validateFeature(Structure $eStructure, string $feature): void {

		if($eStructure->acceptFeature($feature) === FALSE) {
			self::notify('failFeature', $eStructure, $feature);
		}

	}

	public static function discoverAccounting(Structure $eStructure): void {

		if($eStructure->hasAccounting()) {
			return;
		}

		self::notify('discoverAccounting', $eStructure);

		// Un événement sans observateur ne refuse rien : l'écran de découverte vit dans accounting/,
		// le refus reste ici.
		throw new \NotExistsAction();

	}

	public static function getDatabasesWithTable(string $table): array {

		$database = Structure::model()->pdo();

		$bases = $database->select('
			SELECT TABLE_SCHEMA FROM information_schema.TABLES
			WHERE TABLE_NAME = '.$database->api->quote($table).'
		')->fetchAll(\PDO::FETCH_COLUMN);

		$basesByStructure = [];

		foreach($bases as $base) {

			// Le préfixe change avec l'environnement (`structure_7` en prod, `dev_structure_7` en dev) :
			// l'identifiant se lit sans lui, et la comparaison à getDatabaseName() écarte les copies
			// à blanc et la démonstration.
			if(preg_match('/structure_([0-9]+)$/', $base, $match) === 0) {
				continue;
			}

			$eStructure = new Structure(['id' => (int)$match[1]]);

			if(StructureSetting::getDatabaseName($eStructure) !== $base) {
				continue;
			}

			$basesByStructure[$eStructure['id']] = $base;

		}

		return $basesByStructure;

	}

	public static function connectDatabase(\structure\Structure $eStructure, bool $migrate = TRUE, bool $isDemo = FALSE): void {

		if(Structure::isConnected($eStructure)) {
			return;
		}

		$base = StructureSetting::getDatabaseName($eStructure, $isDemo);

		foreach(StructureSetting::getPackages() as $package) {
			\Database::setPackage($package, $base);
		}

		\Database::addBase($base, 'ouvretaferme');

		Structure::setConnected($eStructure);

		\ModuleModel::resetDatabases();

		// La version d'une base de structure se tient dans la base commune, qu'un import à blanc ne peut
		// pas écrire. Sa copie part d'une base migrée par help\BlankLib::copy().
		if($migrate and OTF_BLANK === FALSE) {
			\data\MigrateLib::live($eStructure);
		}

	}

	public static function disconnectDatabase(array $packages): void {

		// En entier et non package par package : un package absent de la configuration n'a aucune
		// valeur à reprendre, et resterait rattaché à la base de la dernière structure connectée.
		\Database::setPackages($packages);

		Structure::resetConnected();

		\ModuleModel::resetDatabases();

	}

	public static function keepConnection(\Closure $closure): mixed {

		$packages = \Database::getPackages();
		$eUserOnline = \user\ConnectionLib::getOnline();

		// `getConnected()` lève quand aucune structure n'est connectée, et c'est la seule lecture qui
		// existe de cet état.
		try {
			$eStructureConnected = Structure::getConnected();
		} catch(\Exception) {
			$eStructureConnected = new Structure();
		}

		try {
			return $closure();
		} finally {

			\user\ConnectionLib::setOnline($eUserOnline);

			self::disconnectDatabase($packages);

			if($eStructureConnected->notEmpty()) {
				Structure::setConnected($eStructureConnected);
			}

		}

	}

	public static function runAccounting(\structure\Structure $eStructure, \Closure $closureOk, \Closure $closureKo): mixed {

		if($eStructure->hasAccounting() === FALSE) {
			return $closureKo($eStructure);
		}

		self::connectDatabase($eStructure);
		return $closureOk($eStructure);

	}

	public static function runAccountingAll(\Closure $closure): void {

		if(StructureSetting::hasAccountingApp() === FALSE) {
			return;
		}

		$cStructure = \company\CompanyLib::getAccountingStructures();

		foreach($cStructure as $eStructure) {

			\structure\StructureLib::connectDatabase($eStructure);
			$closure($eStructure);

		}

	}

	/**
	 * Le parcours du parc e-facturation, pendant de runAccountingAll() sur l'autre population.
	 *
	 * Les deux ne se recouvrent pas : l'e-facturation s'active sans la comptabilité et réciproquement.
	 * Une mesure qui porte sur les factures doit donc passer par ici, sous peine de ne voir que les
	 * structures qui ont fait les deux.
	 *
	 * Rend le nombre de structures qu'elle n'a pas pu parcourir, et c'est ce qui la distingue de
	 * runAccountingAll() : l'ouverture de la base est couverte par la garde, pas seulement le corps du
	 * parcours. Un appelant qui pose son propre try/catch autour du seul $closure ne protège rien,
	 * connectDatabase() étant appelé avant lui — une base absente ferait alors tomber la passe entière
	 * dès la première structure manquante, ce qu'un import de production partiel produit couramment en
	 * développement.
	 */
	public static function runPdpAll(\Closure $closure): int {

		$cStructure = \structure\Feature::model()
			->select(['structure' => ['id', 'version']])
			->whereHasPdp(TRUE)
			->getCollection()
			->getColumnCollection('structure');

		$failed = 0;

		foreach($cStructure as $eStructure) {

			try {

				\structure\StructureLib::connectDatabase($eStructure);
				$closure($eStructure);

			} catch(\Exception $e) {
				$failed++;
			}

		}

		return $failed;

	}

	public static function runAll(\Closure $closure): void {

		$cStructure = \structure\Feature::model()
			->select(['structure' => ['id', 'version'], 'hasAccounting'])
			->getCollection()
			->getColumnCollection('structure');

		foreach($cStructure as $eStructure) {

			\structure\StructureLib::connectDatabase($eStructure);
			$closure($eStructure);

		}

	}

	/**
	 * Le Lib qui répond pour ce groupe.
	 *
	 * Pendant de `StructureUi::getClass()` : le type se résout une fois, et un groupe qui enchaîne
	 * autrement écrit une classe plutôt qu'une branche. `StructureCommunityLib` est la classe de
	 * base et le défaut.
	 */
	public static function getCommunityClass(Structure $eStructure): string {

		return match($eStructure['communityType']) {
			Structure::EDUCATION => '\structure\StructureEducationLib',
			default => '\structure\StructureCommunityLib'
		};

	}

	public static function create(Structure $e, bool $withDefaults = TRUE): void {

		$e->expects(['type']);

		if(
			$e['type'] === Structure::PRODUCER and
			(
				$e->offsetExists('producerType') === FALSE or
				$e['producerType'] === NULL
			)
		) {
			throw new \Exception('A producer is created without producerType');
		}

		// La date dit que la catégorie a été **choisie**, ce que seul l'appelant sait : elle se pose
		// ici pour un choix arrivé par formulaire, et l'appelant qui présume la catégorie au lieu de
		// la demander pose NULL lui-même (`quotation\ContributeLib::createForSignUp()`).
		if(
			$e['type'] === Structure::PRODUCER and
			$e->offsetExists('producerTypeUpdatedAt') === FALSE
		) {
			$e['producerTypeUpdatedAt'] = new \Sql('NOW()');
		}

		if($e->isAmap()) {
			$e['verifiedCountry'] = TRUE;
		}

		Structure::model()->beginTransaction();

			Structure::model()->insert($e);

			ConfigurationLib::createForStructure($e);
			\map\ConfigurationLib::createForStructure($e);
			\product\ConfigurationLib::createForStructure($e);
			FeatureLib::createForStructure($e);
			SystemLib::createForStructure($e);
			RoleLib::createForStructure($e);
			CommunityLib::createForStructure($e);

			self::notify('createStructure', $e);

			self::createOwner($e);

			if($withDefaults) {
				self::createStructure($e);
			}

		Structure::model()->commit();

		// Create database
		if(OTF_DEMO === FALSE) {
			self::createDatabase($e);
		}

	}

	protected static function createStructure(Structure $e): void {

		self::createProduction($e);
		self::createCommercialisation($e);

	}

	protected static function createProduction(Structure $e): void {

		\work\ActionLib::duplicateForStructure($e);
		\plant\PlantLib::duplicateForStructure($e);

	}

	protected static function createCommercialisation(Structure $e): void {

		\product\UnitLib::duplicateForStructure($e);

		// Le produit des frais de livraison prend son nom et sa TVA dans selling/, absent de la version publique
		if(StructureSetting::hasCommercialisationApp()) {
			\product\ProductLib::createForStructure($e);
		}

	}

	protected static function createOwner(Structure $e): void {

		if(isset($e['owner'])) {

			$eUser = $e['owner'];

			$eMember = new Member([
				'user' => $eUser,
				'structure' => $e,
				'role' => RoleLib::getOne($e, 'admin'),
				'status' => Member::IN,
				'structureStatus' => $e['status']
			]);

			MemberLib::create($eMember);

			self::createOwnerPresence($e, $eUser);

			\user\TosAiLib::acceptOnStructure($eUser);

		}

	}

	public static function createOwnerPresence(Structure $e, \user\User $eUser): void {

		if($e->acceptProduction()) {

			$eTeammate = new \hr\Teammate([
				'structure' => $e,
				'user' => $eUser,
				'ePresence' => new \hr\Presence([
					'from' => (\Clock::year() - 1).'-01-01',
					'to' => NULL
				])
			]);

			\hr\TeammateLib::create($eTeammate);

		}

	}

	public static function createDatabase(Structure $e): void {

		Structure::model()->pdo()->exec('CREATE DATABASE IF NOT EXISTS '.StructureSetting::getDatabaseName($e));

		self::connectDatabase($e);

		self::initializeStart();

	}

	private static function initializeStart(): void {

		foreach(self::getModuleNames() as $class) {

			[$package] = explode('\\', $class);

			if(in_array($package, StructureSetting::getStartPackages())) {
				new \ModuleAdministration($class)->init();
			}

		}

	}

	public static function getModuleNames(): array {

		$modules = [];

		foreach(\Package::getList() as $package => $app) {

			$directory = LIME_DIRECTORY.'/'.$app.'/'.$package.'/module/';

			foreach(glob($directory.'*.m.php') as $path) {
				$modules[] = $package.'\\'.substr($path, strlen($directory), -6);
			}

		}

		return $modules;

	}

	/**
	 * Ce qui se règle depuis la page Profil, et rien d'autre.
	 *
	 * Séparée de getPropertiesUpdate(), qui sert l'écran administratif et l'API : les six champs
	 * y sont déjà pour cinq d'entre eux, mais la liste dit ce qu'un écran écrit, pas ce qu'une
	 * structure porte.
	 */
	public static function getPropertiesProfile(): array {

		// ⚠️ Ni `logo` ni `banner` : ils s'écrivent par le téléversement, qui a sa propre action, et
		// n'apparaissent jamais dans le POST de ce formulaire. Les construire ici les remettrait à
		// NULL à chaque enregistrement — `build()` écrit NULL sur une propriété qu'il construit
		// sans la trouver dans l'entrée.
		$properties = ['startedAt', 'url', 'description'];

		// ⚠️ Et `profileVisibility` suit le champ, par la même règle : le formulaire ne le propose
		// que sous le drapeau, donc le construire hors drapeau l'écraserait à chaque
		// enregistrement du profil — sur toutes les fermes de production, celles-là mêmes que la
		// migration ferme. Une liste de propriétés qui déborde du formulaire efface en silence.
		if(FEATURE_INRAE) {
			$properties[] = 'profileVisibility';
		}

		return $properties;

	}

	public static function update(Structure $e, array $properties): void {

		Structure::model()->beginTransaction();

		if(in_array('producerType', $properties)) {
			$e['producerTypeUpdatedAt'] = new \Sql('NOW()');
			$properties[] = 'producerTypeUpdatedAt';
		}

		parent::update($e, $properties);

		// Les ventes proposent le taux de départ du pays une fois celui-ci vérifié par l'utilisateur
		if(
			in_array('verifiedCountry', $properties) and
			$e['verifiedCountry'] and
			StructureSetting::hasCommercialisationApp()
		) {
			self::notify('verifyCountry', $e);
		}

		if(
			in_array('legalCountry', $properties) and
			$e->isCountryVatAllowed() === FALSE
		) {
			ConfigurationLib::updateProperty($e, 'hasVat', FALSE);
		}

		// Une AMAP fermée sert jusqu'au bout de leur saison les souscriptions déjà signées : son équipe
		// garde l'accès pour distribuer, pointer et encaisser, et seule l'AMAP refuse ce qui est neuf.
		if(
			in_array('status', $properties) and
			$e->isAmap() === FALSE
		) {

			Member::model()
				->whereStructure($e)
				->update([
					'structureStatus' => $e['status']
				]);

		}

		Structure::model()->commit();

	}

	/**
	 * Le profil du groupe écrit la structure et son adresse ensemble : un refus tardif de l'adresse
	 * annule aussi le nom. Le profil porte aussi l'organisme certificateur, rangé dans la configuration.
	 */
	public static function updateAdministrative(Structure $e, array $properties, Community $eCommunity, array $propertiesCommunity, Configuration $eConfiguration = new Configuration(), array $propertiesConfiguration = []): void {

		Structure::model()->beginTransaction();

		self::update($e, $properties);

		if($propertiesCommunity !== []) {
			CommunityLib::update($eCommunity, $propertiesCommunity);
		}

		if($propertiesConfiguration !== []) {
			ConfigurationLib::update($eConfiguration, $propertiesConfiguration);
		}

		Structure::model()->commit();

	}

	public static function updateLegal(Structure $e): void {

		if($e->acceptLegal() === FALSE) {
			throw new \UnsupportedException();
		}

		$e['isLegal'] = TRUE;
		self::update($e, ['isLegal']);

	}

	public static function close(Structure $e): void {

		Structure::model()->beginTransaction();

			\structure\StructureLib::update($e, ['status']);

			\api\AccessTokenLib::revokeByReference($e['id']);
			\api\AccessTokenLib::deleteAuthCodeByReference($e['id']);

			self::notify('closeStructure', $e);

		Structure::model()->commit();

	}

	public static function delete(Structure $e): void {

		throw new \UnsupportedException();

	}

	public static function getSiretApi(string $query): ?array {

		if(Structure::checkSiret($query) === FALSE) {
			return NULL;
		}

		return self::requestCompanyApi($query, $query);

	}

	public static function getSirenApi(string $query): ?array {

		if(Structure::checkSiren($query) === FALSE) {
			return NULL;
		}

		return self::requestCompanyApi($query);
	}

	// $siret posé (voie getSiretApi, valeur déjà épurée par checkSiret) : l'établissement rendu
	// est celui demandé, jamais déduit du siège — la recherche par SIRET est exacte, ou rien.
	private static function requestCompanyApi(string $query, ?string $siret = NULL): ?array {

		$params = [
			'q' => $query,
			'page' => 1,
			'per_page' => 1
		];

		$curl = new \util\CurlLib();

		try {
			$values = $curl->exec('https://recherche-entreprises.api.gouv.fr/search', $params);
		} catch(\Exception) {
			$values = NULL;
		}

		if(
			$values === NULL or
			$curl->getLastInfos()['httpCode'] !== 200
		) {
			return NULL;
		}

		$data = json_decode($values, TRUE)['results'];

		if($data === []) {
			return NULL;
		}

		$company = $data[0];

		if(empty($company['nom_raison_sociale']) and empty($company['nom_complet'])) {
			return NULL;
		}

		$siege = $company['siege'] ?? [];

		if($siret !== NULL and (($company['matching_etablissements'][0]['siret'] ?? NULL) !== $siret)) {
			return NULL;
		}

		$diffused = function(?string $value): ?string {
			if($value !== NULL and in_array(mb_strtoupper($value), ['[NON-DIFFUSIBLE]', '[ND]'], TRUE)) {
				return NULL;
			}
			return $value;
		};

		$company['nom_raison_sociale'] = $diffused($company['nom_raison_sociale'] ?? NULL);
		$company['nom_complet'] = $diffused($company['nom_complet'] ?? NULL);

		foreach(['libelle_commune', 'code_postal', 'numero_voie', 'type_voie', 'libelle_voie', 'complement_adresse'] as $field) {
			$siege[$field] = $diffused($siege[$field] ?? NULL);
		}

		if($siret !== NULL and ($siege['siret'] ?? NULL) !== $siret) {

			$etablissement = $company['matching_etablissements'][0];

			foreach(['adresse', 'code_postal', 'libelle_commune'] as $field) {
				$etablissement[$field] = $diffused($etablissement[$field] ?? NULL);
			}

			$siege = [
				'siret' => $siret,
				'libelle_commune' => $etablissement['libelle_commune'],
				'code_postal' => $etablissement['code_postal'],
				'numero_voie' => NULL,
				'type_voie' => NULL,
				'libelle_voie' => NULL,
				'complement_adresse' => NULL,
			];

			if($etablissement['adresse'] !== NULL and $etablissement['code_postal'] !== NULL and $etablissement['libelle_commune'] !== NULL) {

				$suffix = ' '.$etablissement['code_postal'].' '.$etablissement['libelle_commune'];

				if(str_ends_with($etablissement['adresse'], $suffix)) {
					$siege['libelle_voie'] = substr($etablissement['adresse'], 0, -strlen($suffix));
				}

			}

		} else if($siret === NULL and ($company['nombre_etablissements_ouverts'] ?? 0) === 1) {

			$siret = $siege['siret'] ?? NULL;

		}

		if($company['nom_raison_sociale'] !== NULL) {
			$legalName = mb_ucwords($company['nom_raison_sociale']);
		} else if($company['nom_complet'] !== NULL) {
			$legalName = mb_ucwords($company['nom_complet']);
		} else {
			$legalName = NULL;
		}

		$legalStreet1 = mb_ucwords(trim((($siege['numero_voie'] ?? NULL) ? $siege['numero_voie'].' ' : '').(($siege['type_voie'] ?? NULL) ? $siege['type_voie'].' ' : '').($siege['libelle_voie'] ?? '')));

		if($legalStreet1 === '') {
			$legalStreet1 = NULL;
		}

		return [
			'siren' => $company['siren'],
			'siret' => $siret,
			'legalName' => $legalName,
			'legalCity' => ($siege['libelle_commune'] ?? NULL) ? mb_ucwords($siege['libelle_commune']) : '',
			'legalPostcode' => $siege['code_postal'] ?? NULL,
			'legalStreet1' => $legalStreet1,
			'legalStreet2' => ($siege['complement_adresse'] ?? NULL) ? mb_ucwords($siege['complement_adresse']) : NULL,
			'isOrganic' => $company['complements']['est_bio'] ?? FALSE,
			'legalCategory' => self::matchLegalCategory($company['nature_juridique'] ?? NULL)
		];

	}

	public static function matchLegalCategory(?string $natureJuridique): ?int {

		if($natureJuridique === NULL or preg_match('/^[0-9]{4}$/', $natureJuridique) !== 1) {
			return NULL;
		}

		$category = (int)$natureJuridique;

		if(in_array($category, StructureSetting::CATEGORIES_JURIDIQUES, TRUE)) {
			return $category;
		}

		return StructureSetting::CATEGORIES_JURIDIQUES_FAMILLES[intdiv($category, 100)] ?? StructureSetting::CATEGORIE_JURIDIQUE_AUTRE;

	}

	// L'AMAP s'ouvre par un drapeau que seul son package lit.
	public static function isAmapEnabled(\user\User $eUser): bool {

		if(StructureSetting::hasCommercialisationApp() === FALSE) {
			return FALSE;
		}

		return self::notify('isAmapEnabled', $eUser)[0];

	}

	// Les régions de comparaison sont celles de la mercuriale des réseaux, qui en tient la liste.
	public static function acceptQuotationRegion(string $region): bool {

		if(StructureSetting::hasCommercialisationApp() === FALSE) {
			return FALSE;
		}

		return self::notify('acceptQuotationRegion', $region)[0];

	}

	public static function getCollectOpens(Structure $eStructureCommunity, \user\User $eUser): \Collection {

		if(StructureSetting::hasCommercialisationApp() === FALSE) {
			return new \Collection();
		}

		return self::notify('getCollectOpens', $eStructureCommunity, $eUser)[0];

	}

	// Les référents et les fonctionnalités d'une AMAP se lisent dans son package.
	public static function isAmapReferent(Structure $eStructure): bool {

		if(StructureSetting::hasCommercialisationApp() === FALSE) {
			return FALSE;
		}

		return in_array(TRUE, self::notify('isAmapReferent', $eStructure), TRUE);

	}

	// Pose sur chaque membre les producteurs qu'il tient dans l'AMAP, et dit si l'un d'eux en tient un.
	public static function loadAmapMemberProducers(Structure $eStructure, \Collection $ccMember): bool {

		if(
			StructureSetting::hasCommercialisationApp() === FALSE or
			$eStructure->isAmap() === FALSE
		) {
			return FALSE;
		}

		return in_array(TRUE, self::notify('loadAmapMemberProducers', $eStructure, $ccMember), TRUE);

	}

	public static function hasAmapFeature(Structure $eStructure, string $feature): bool {

		if(
			StructureSetting::hasCommercialisationApp() === FALSE or
			$eStructure->isAmap() === FALSE
		) {
			return FALSE;
		}

		return in_array(TRUE, self::notify('hasAmapFeature', $eStructure, $feature), TRUE);

	}

	// L'offre logiciel d'une structure se juge dans le package des abonnements. Sans lui, tout est ouvert,
	// et les plafonds d'e-mails sont ceux de l'offre la plus haute.
	public static function acceptSoftware(Structure $eStructure, string $software): bool {

		if(StructureSetting::hasSubscriptionPackage() === FALSE) {
			return TRUE;
		}

		return self::notify('acceptSoftware', $eStructure, $software)[0];

	}

	public static function acceptFeature(Structure $eStructure, string $feature): bool {

		// L'API reste gratuite pour un groupe (16/10/2026), sans offre logiciel à juger.
		if($feature === Structure::FEATURE_API and $eStructure->isApiPaid() === FALSE) {
			return TRUE;
		}

		if(StructureSetting::hasSubscriptionPackage() === FALSE) {
			return TRUE;
		}

		return self::notify('acceptFeature', $eStructure, $feature)[0];

	}

	// L'écran fermé qui remplace la page porte l'offre à prendre, `$closed` dit ce qu'elle ouvre ('accounting', 'api').
	public static function validateSoftware(\stdClass $data, string $closed): void {

		if(StructureSetting::hasSubscriptionPackage() === FALSE) {
			return;
		}

		self::notify('validateSoftware', $data, $closed);

	}

	public static function getCampaignLimit(Structure $eStructure): int {

		if(StructureSetting::hasSubscriptionPackage() === FALSE) {
			return Structure::getCampaignExpertLimit();
		}

		return self::notify('getCampaignLimit', $eStructure)[0];

	}

	public static function getContactLimit(Structure $eStructure): int {

		if(StructureSetting::hasSubscriptionPackage() === FALSE) {
			return Structure::getContactMemberLimit();
		}

		return self::notify('getContactLimit', $eStructure)[0];

	}

	/**
	 * Les réglages que le code fermé ajoute à l'écran des e-mails de la structure, validés avant toute
	 * écriture : chaque observateur rend l'écriture à jouer, que `ConfigurationLib::updateEmail()` place
	 * dans la transaction de la signature.
	 */
	public static function buildEmail(Structure $eStructure, array $input): array {

		if(StructureSetting::hasCommercialisationApp() === FALSE) {
			return [];
		}

		return self::notify('buildEmail', $eStructure, $input);

	}

}
