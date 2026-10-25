<?php
namespace structure;

class DemoLib {

	use \Notifiable;

	const DATABASE = 'demo_ouvretaferme';
	const DATABASE_STRUCTURE_1 = 'demo_structure_1';
	const DATABASE_STRUCTURE_2 = 'demo_structure_2';

	const USER = 1;

	const COPY_STRUCTURE = 7;
	const COPY_STRUCTURE_2 = 3994;

	const COPY_PACKAGE_EXCLUDE = [
		'dev', 'session', 'storage', 'mail', 'game', 'quotation',
		'analyze', 'shop', 'website', 'api', 'amap', 'aggregate', 'learning', 'training', 'block', 'security',
		// Une tâche de la file est un geste à exécuter, pas une donnée à recopier : dupliquée vers
		// la copie, elle serait rejouée par le runner au nom de la structure de démonstration.
		'queue',
		// Les photos d'un ticket de balance portent en tête le nom et l'adresse de la structure d'origine.
		'external',
		// Les notes de la structure copiée sont écrites pour ses seuls membres, souvent pour ses seuls
		// administrateurs : la démonstration les montrerait à tout visiteur.
		'note',
		// Les statistiques du parc et l'instantané des structures comptables décrivent toutes les structures, pas celle qu'on copie.
		'admin'
	];

	const COPY_MODULE_EXCLUDE = [
		// La copie ne duplique pas les fichiers : un hash recopié désigne la pièce de la structure
		// d'origine sur le disque commun, et l'ouvrirait à tout visiteur de la démonstration.
		// Les documents de l'exercice se régénèrent à la demande.
		'financialYear\Document',
		'financialYear\Pdf',
		'financialYear\PdfContent',
		'invoicing\PdfContent',
		// Sans colonne `structure`, la table se recopierait entière : les hash des fichiers de toutes
		// les structures. Une image de la démonstration se sert depuis le disque sans sa ligne, et
		// un envoi la recrée (`MediaLib::addMedia()`).
		'media\Media',
		// Les modèles 3D téléversés à la place d'un bâtiment, pour la même raison que les documents : leur hash ouvrirait le
		// fichier de la structure d'origine
		'map\DecorUpload',
		'company\AccountingHelp',
		// Les lignes attendues du catalogue ne se lisent que dans l'admin des aides, que la démonstration n'ouvre pas.
		'aid\Expected',
		// Sans colonne `structure`, le dictionnaire des articles se recopierait entier : les SIREN, les
		// vendeurs et les libellés reçus par tout le parc.
		'company\DictionaryItem',
		// Les choix d'une structure sur les natures et leur historique suivent le dictionnaire, que la copie
		// ne prend pas : la démonstration repart des propositions du parc, comme une structure qui n'a
		// encore rien retenu.
		'company\DictionaryItemHistory',
		'company\DictionaryItemStructure',
		'company\NatureAccount',
		'data\Analytics',
		'data\PdpHistory',
		'help\Check',
		'help\File',
		'help\Gap',
		'help\Import',
		'help\Message',
		'help\Proposal',
		'help\Run',
		'help\Step',
		'help\Year',
		'structure\Affiliation',
		'structure\Invite',
		'structure\Tip',
		'notification\Notification',
		'invoicing\Event',
		'invoicing\OutgoingEvent',
		'invoicing\Invoicing',
		'invoicing\Pdf',
		'invoicing\Line',
		// Une part relie deux lignes de facture, que la copie écarte
		'invoicing\LineShare',
		'communication\DiscordAccess',
		'communication\DiscordInvitation',
		'communication\Note',
		'communication\NoteRecipient',
		'communication\WebinaireSubscription',
		'pdp\Address',
		'pdp\Partner',
		'preaccounting\Suggestion',
		'securing\Log',
		'securing\Signature',
		'series\Comment',
		'series\History',
		'subscription\Statement',
		'subscription\StatementLine',
		// Le plan de composition ne se copie pas, et c'est sa forme qui l'interdit : quatre de ses
		// cinq tables n'ont pas de colonne `structure`, elles pendent au plan. La copie ne sait
		// reprendre qu'une table qui porte sa structure, donc elle emporterait le plan sans ses
		// formules — une coquille vide pour la ferme école comme pour la démonstration. Le jour où
		// ces écrans valent d'être montrés, c'est la copie qui apprendra à descendre une chaîne,
		// pas le schéma qui dénormalisera pour elle.
		'toolbox\CompositionPlan',
		'toolbox\CompositionFormula',
		'toolbox\CompositionWeek',
		'toolbox\CompositionLine',
		'toolbox\CompositionSpecies',
		// Le référentiel du calculateur des besoins en fertilisation est commun à toutes les
		// structures, sans colonne `structure` : le copier le doublerait pour tout le monde.
		'toolbox\FertilizationCrop',
		'toolbox\FertilizationMaterial',
		'toolbox\FertilizationSource',
		'toolbox\FertilizationYield',
		// Ce que le planning dit dû est un état que chaque source recalcule, et dont chaque ligne
		// désigne un élément de la source par son identifiant dans la structure d'origine.
		'planning\Due',
		'planning\Reminder',
		'user\AdminLog',
		'user\Challenge',
		'user\LoginAttempt',
		'user\LoginLink',
		'user\Log',
		'user\Tos',
		'user\UserAuto',
		'user\UserAuth',
		// Un avatar, sa demande et ses modèles téléversés appartiennent à une personne, sans colonne
		// `structure` : la copie les prendrait tous, avec l'empreinte des photos de profil mises de côté
		'user\UserAvatar',
		'user\UserAvatarRequest',
		'user\UserAvatarUpload'
	];

	const COPY_MODULE_ONCE = [
		// Le catalogue des aides est commun à toutes les structures et tenu par l'équipe : la
		// démonstration le montre tel quel.
		'aid\Aid',
		'aid\AidLink',
		'aid\AidOrganization',
		'aid\AidPiece',
		'aid\AidTag',
		'aid\Area',
		'aid\Edition',
		'aid\Organization',
		'aid\Piece',
		'aid\Source',
		'aid\Tag',
		'company\AmortizationDuration',
		'company\GenericAccount',
		'company\JournalCode',
		'company\Plan',
		'gallery\Photo',
		'map\Bed',
		'map\Draw',
		'map\Plot',
		'map\Zone',
		'plant\Criterion',
		'plant\Family',
		'plant\Forecast',
		'plant\Plant',
		'plant\Size',
		'plant\Tag',
		'plant\Variety',
		'plant\VarietyName',
		'sequence\Crop',
		'sequence\Flow',
		'sequence\Sequence',
		'sequence\Slice',
		'series\Cultivation',
		'series\Harvest',
		'series\Place',
		'series\Repeat',
		'series\Series',
		'series\Slice',
		'series\Task',
		'series\Timesheet',
		'user\Ban',
		'user\Country',
		'user\User',
		'util\Cache',
		'work\PhytoProduct',
		'work\PhytoUsage',
	];

	const COPY_PROPERTY_EXCLUDE = [
		'bank\BankAccount' => ['bankId', 'accountId', 'enableBankingSessionId', 'enableBankingAccountId', 'enableBankingIban', 'aspspName', 'aspspCountry', 'enableBankingAccessValidUntil', 'enableBankingConnectedAt', 'enableBankingImportFrom', 'enableBankingBalance', 'enableBankingBalanceType'],
		'asset\Asset' => ['description'],
		'thirdParty\ThirdParty' => ['name', 'legalName', 'siren', 'vatNumber', 'country', 'legalStreet1', 'legalStreet2', 'legalPostcode', 'legalCity', 'contactName', 'contactEmail', 'contactPhone'],
		'bank\Cashflow' => ['memo', 'name', 'thirdPartyIban'],
		'bank\Import' => ['ofx', 'hash', 'raw'],
		'journal\Operation' => ['document', 'description'],
		'user\User' => ['phone', 'vignette', 'siret', 'legalName', 'deliveryStreet1', 'deliveryStreet2', 'deliveryPostcode', 'deliveryCity', 'invoiceStreet1', 'invoiceStreet2', 'invoicePostcode', 'invoiceCity'],
		'series\Repeat' => ['description'],
		'series\Series' => ['comment'],
		// Le justificatif garde ses valeurs lues et perd son fichier, comme un fichier effacé
		'proof\File' => ['hash'],
		// Le bâtiment copié montre sa composition : l'envoi qu'il désignait ne se copie pas
		'map\DecorBuilding' => ['upload']
	];

	/**
	 * Vidées par le tableau vide et non par NULL : leur défaut est `[]`, et tout le code qui les relit
	 * ne tolère pas NULL (`ThirdPartyLib::updateElectronicAddresses()` fait un `array_merge()` dessus).
	 * La reconnaissance de tiers n'y perd rien : elle s'appuie sur des libellés bancaires que
	 * `anonymizeAccounting()` réécrit.
	 */
	const COPY_PROPERTY_EMPTY = [
		'thirdParty\ThirdParty' => ['electronicAddresses', 'memos', 'fingerprints', 'normalizedName'],
	];

	private static array $ms = [];
	private static array $demoMs = [];

	/**
	 * Les prénoms dont la démonstration et les fermes école tirent leurs personnes.
	 *
	 * La constante et non le tableau tiré au sort de `getFirstName()` : une ferme école doit rendre
	 * la même équipe à chaque rejeu, et un `shuffle()` la renommerait à chaque passage.
	 */
	const FIRST_NAMES = [
		"Alma", "Louise", "Emma", "Jeanne", "Anna", "Adèle", "Rose", "Gabrielle", "Chloé", "Jade", "Élise", "Cerise", "Juliette", "Julie", "Ariane", "Sandrine", "Lola", "Léna", "Éléonore", "Karine",
		"Gabriel", "Adam", "Raphaël", "Louis", "Mohamed", "Arthur", "Isaac", "Noah", "Gaspard", "Léon", "Roméo", "Simon", "Samuel", "Victor", "Pierre", "Franck", "Justin", "Emmanuel", "Gustave", "Thomas", "William"
	];

	private static ?array $names = NULL;
	private static ?array $packages = NULL;

	public static function rebuild() {

		$eStructure = StructureLib::getById(self::COPY_STRUCTURE);
		\structure\StructureLib::connectDatabase($eStructure);

		// prod instance
		self::$ms = self::getModules();

		self::$packages = \Database::getPackages();

		// demo mode
		self::activate();

		// demo instances
		self::$demoMs = self::getModules();

		// maintenance mode
		self::close();

		// drop old demo table
		self::dropTables();

		// create new demo tables
		self::createTables();

		// copy data in demo tables
		self::copy(Structure::DEMO);
		self::copy(Structure::DEMO_BIS);

		// update data
		self::update();

		// anonymize data
		self::anonymize();

		// end of maintenance mode
		self::open();

	}

	/**
	 * La démonstration est-elle en cours de rechargement ?
	 *
	 * Le rechargement démonte la base : entre le DROP et la recopie de `structure`, la table n'existe
	 * pas, et une requête dessus lève. Cet état-là est aussi un rechargement, d'où le catch.
	 */
	public static function isRebuilding(): bool {

		try {
			return Structure::model()
				->whereId(Structure::DEMO)
				->whereStatus(Structure::CLOSED)
				->exists();
		} catch(\Exception) {
			return TRUE;
		}

	}

	public static function close(): void {

		Structure::model()
			->whereId('IN', [Structure::DEMO, Structure::DEMO_BIS])
			->update(['status' => Structure::CLOSED]);

	}

	public static function open(): void {

		Structure::model()
			->whereId('IN', [Structure::DEMO, Structure::DEMO_BIS])
			->update(['status' => Structure::ACTIVE]);

	}

	public static function activate(): void {

		$newDatabase = [];

		foreach(\Database::getPackages() as $package => $database) {
			$newDatabase[$package] = self::DATABASE;
		}

		\Database::setPackages($newDatabase);

	}

	public static function dropTables(): void {

		$pdo = first(self::$demoMs)->pdo();

		$databases = [self::DATABASE, self::DATABASE_STRUCTURE_1, self::DATABASE_STRUCTURE_2];

		foreach($databases as $database) {

			$rs = $pdo->select('SHOW TABLES FROM '.$pdo->api->field($database));

			while($row = $rs->fetch(\PDO::FETCH_ASSOC)) {

				$table = first($row);

				$pdo->exec('DROP TABLE IF EXISTS '.$pdo->api->field($database).'.'.$pdo->api->field($table));

			}

		}

	}

	public static function createTables(): void {

		foreach(self::$demoMs as $m) {

			if(in_array($m->getPackage(), StructureSetting::getPackages())) {

				StructureLib::connectDatabase(new Structure(['id' => Structure::DEMO]), migrate: FALSE, isDemo: TRUE);
				new \ModuleAdministration($m->getModule())->createTable();

				StructureLib::connectDatabase(new Structure(['id' => Structure::DEMO_BIS]), migrate: FALSE, isDemo: TRUE);
				new \ModuleAdministration($m->getModule())->createTable();

			} else {

				new \ModuleAdministration($m->getModule())->createTable();

			}


		}

	}

	public static function copy(int $structureId): void {

		$modulesExclude = self::getModulesExclude();
		$modulesOnce = self::getModulesOnce();
		$propertiesExclude = self::getPropertiesExclude();

		foreach(self::$ms as $module => $m) {

			if(self::canCopyModule($m) === FALSE) {
				continue;
			}

			$pdo = $m->pdo();
			$table = $m->getTable();

			$properties = $m->getProperties();
			$demoProperties = $m->getProperties();

			array_walk($demoProperties, function(&$value) use($pdo) {
				$value = $pdo->api->field($value);
			});

			array_walk($properties, function(&$value, $key) use($module, $m, $pdo, $structureId, $modulesExclude, $propertiesExclude) {

				if(
					($m->getModule() === 'structure\\Structure' and $value === 'id') or
					($m->getPropertyToModule('structure') === 'structure\\Structure' and $value === 'structure') or
					($m->getPropertyToModule('structure') === 'structure\\Structure' and $value === 'structure')
				) {
					$value = 'IF('.$pdo->api->field($value).' IS NULL, NULL, '.$structureId.') AS '.$pdo->api->field($value);
				} else if( // La copie a lieu au milieu du rechargement : reprendre le statut de la source rouvrirait la démonstration avant que les autres tables soient en place
					($m->getModule() === 'structure\\Structure' and $value === 'status')
				) {
					$value = '"'.Structure::CLOSED.'" AS '.$pdo->api->field($value);
				} else if(
					($m->getModule() === 'series\\Task' and $value === 'description')
				) {
					$value = 'NULL AS '.$pdo->api->field($value);
				} else if( // Champs not nullable de l'app accounting
					($m->getModule() === 'thirdParty\\ThirdParty' and $value === 'name') or
					($m->getModule() === 'asset\\Asset' and $value === 'description') or
					($m->getModule() === 'journal\\Operation' and $value === 'description')
				) {
					$value = 'id AS '.$pdo->api->field($value);
				} else if(in_array($value, self::COPY_PROPERTY_EMPTY[$module] ?? [])) {
					$value = '"[]" AS '.$pdo->api->field($value);
				} else if(in_array($value, $propertiesExclude[$module] ?? [])) {
					$value = 'NULL AS '.$pdo->api->field($value);
				} else {

					$valueModule = $m->getPropertyToModule($value);

					if($valueModule) {

						// On met à NULL les propriétés des packages et modules exclus
						foreach(self::COPY_PACKAGE_EXCLUDE as $package) {
							if(str_starts_with($valueModule, $package.'\\')) {
								$value = 'NULL AS '.$pdo->api->field($value);
								return;
							}
						}

						foreach($modulesExclude as $moduleExclude) {
							if($valueModule === $moduleExclude) {
								$value = 'NULL AS '.$pdo->api->field($value);
								return;
							}
						}

					}

					$value = $pdo->api->field($value);

				}

			});

			if(in_array($m->getPackage(), StructureSetting::getPackages())) {

				$database = match($structureId) {
					Structure::DEMO => self::DATABASE_STRUCTURE_1,
					Structure::DEMO_BIS => self::DATABASE_STRUCTURE_2,
				};

			} else if(in_array($m->getModule(), $modulesOnce) and $structureId === Structure::DEMO_BIS) { // Certaines tables ne sont pas recopiées 2 fois

				continue;

			} else {

				$database = self::DATABASE;

			}

			$sql = 'INSERT INTO '.$pdo->api->field($database).'.'.$pdo->api->field($table).'('.implode(', ', $demoProperties).') SELECT '.implode(', ', $properties).' FROM '.$pdo->api->field(self::$packages[$m->getPackage()]).'.'.$pdo->api->field($table);

			$sql .= ' WHERE '.self::getCopyCondition($m, $structureId);

			$pdo->exec($sql);

		}

	}

	private static function getCopyCondition(\ModuleModel $m, int $structureId): string {

		$conditions = [];

		$copyStructure = match($structureId) {
			Structure::DEMO => self::COPY_STRUCTURE,
			Structure::DEMO_BIS => self::COPY_STRUCTURE_2,
		};

		if($m->getModule() === 'structure\\Structure') {

			$conditions[] = 'id = '.$copyStructure;

		} else if($m->hasProperty('structure')) {

			if($structureId === Structure::DEMO) {
				$conditions[] = '(structure = '.$copyStructure.' OR structure IS NULL)';
			} else {
				$conditions[] = '(structure = '.$copyStructure.')';
			}

		} else if($m->hasProperty('structure')) {

			if($structureId === Structure::DEMO) {
				$conditions[] = '(structure = '.$copyStructure.' OR structure IS NULL)';
			} else {
				$conditions[] = '(structure = '.$copyStructure.')';
			}

		}

		// Celles d'un module des ventes vivent dans son package, absent de la version publique.
		$conditions = array_merge($conditions, ...self::notify('getDemoCopyConditions', $m, $structureId));

		return $conditions ? implode(' AND ', $conditions) : 1;

	}

	public static function update(): void {

		self::updateClosedSeries();

		self::notify('updateDemo');

	}

	public static function updateClosedSeries(): void {

		\series\Series::model()
			->whereStatus(\series\Series::CLOSED)
			->update([
				'status' => \series\Series::OPEN
			]);


	}

	public static function anonymize(): void {

		self::anonymizeStructure();
		self::anonymizeUsers();

		self::notify('anonymizeDemoSales');

		self::anonymizeAccounting(Structure::DEMO);
		self::anonymizeAccounting(Structure::DEMO_BIS);

	}

	public static function anonymizeStructure(): void {

		// Pas par COPY_PROPERTY_EXCLUDE, qui copie NULL dans une colonne NOT NULL. Écriture de masse, donc un tableau.
		Member::model()
			->whereStructure('IN', [Structure::DEMO, Structure::DEMO_BIS])
			->update([
				'notificationsUnseen' => 0
			]);

		Structure::model()
			->whereId(Structure::DEMO)
			->update([
				'name' => 'Ferme des Mots',
				'description' => 'Ferme de démonstration pour présenter {siteName} !',
				'logo' => NULL,
				'legalName' => 'GAEC de Démo',
				'legalEmail' => 'demo@ouvretaferme.org',
				'legalStreet1' => '3 rue des champignons',
				'legalStreet2' => NULL,
				'legalPostcode' => '00000',
				'legalCity' => 'Sous la forêt',
				'siret' => '00000000100010',
				'url' => NULL,
			]);

		Structure::model()
			->whereId(Structure::DEMO_BIS)
			->update([
				'name' => "Ferme des Lettres",
				'description' => 'Deuxième ferme de démonstration pour présenter {siteName} !',
				'logo' => NULL,
				'legalName' => 'GAEC des Lettres',
				'legalEmail' => 'deslettres@ouvretaferme.org',
				'legalStreet1' => "A rue de l'alphabet",
				'legalStreet2' => NULL,
				'legalPostcode' => '88888',
				'legalCity' => 'Dans les livres',
				'siret' => '00000000200010',
				'url' => NULL,
			]);

		ConfigurationLib::updateProperties(new Structure(['id' => Structure::DEMO]), [
			'hasVat' => TRUE,
			'emailBanner' => NULL,
			'vatChargeability' => Configuration::CASH,
			'vatNumber' => NULL,
		]);

		ConfigurationLib::updateProperties(new Structure(['id' => Structure::DEMO_BIS]), [
			'hasVat' => TRUE,
			'emailBanner' => NULL,
			'vatChargeability' => Configuration::CASH,
			'vatNumber' => NULL,
		]);

		// Les mentions et la numérotation des factures vivent dans la configuration des ventes, absente
		// de la version publique.
		if(StructureSetting::hasCommercialisationApp()) {

			foreach([Structure::DEMO, Structure::DEMO_BIS] as $structure) {

				$eStructure = new Structure(['id' => $structure]);

				self::notify('anonymizeDemo', $eStructure);

			}

		}

		Feature::model()
			->whereStructure('IN', [Structure::DEMO, Structure::DEMO_BIS])
			->update([
				'hasPdp' => FALSE,
				'hasEnableBanking' => FALSE,
			]);

		FeatureLib::forgetAll();

	}

	public static function anonymizeUsers(): void {

		$structureUsers = array_merge(
			new MemberModel()->getColumn('user')->getIds(),
			new \series\TimesheetModel()
				->whereStructure('IN', [Structure::DEMO, Structure::DEMO_BIS])
				->getColumn(new \Sql('DISTINCT(user)', 'int'))
		);

		// Un client qui a un compte le garde : la boutique de démonstration le connecte.
		$customerUsers = array_merge(...self::notify('getDemoUsersKept'));

		new \user\UserModel()
			->whereId('NOT IN', $structureUsers)
			->whereId('NOT IN', $customerUsers, if: $customerUsers !== [])
			->delete();

		foreach(new \user\UserModel()
        ->select('id')
        ->getCollection() as $eUser) {

			$eUser['firstName'] = self::getFirstName($eUser['id']);
			$eUser['lastName'] = self::getLastName();
			$eUser['email'] = $eUser['id'].'@'.\Lime::getDomain();

			new \user\UserModel()
				->select('firstName', 'lastName', 'email')
				->update($eUser);

			$eUserAuth = new \user\UserAuth([
				'user' => $eUser,
				'type' => \user\UserAuth::BASIC,
				'login' => $eUser['email'],
				'password' => password_hash('123456', PASSWORD_DEFAULT),
			]);

			\user\UserAuth::model()->insert($eUserAuth);

		}

	}

	public static function anonymizeAccounting(int $structureId): void {

		StructureLib::connectDatabase(new Structure(['id' => $structureId]), migrate: FALSE, isDemo: TRUE);

		foreach(new \thirdParty\ThirdPartyModel()
        ->select('id')
        ->getCollection() as $eThirdParty) {

			$eThirdParty['name'] = self::getFirstName($eThirdParty['id']).' '.self::getLastName().' '.$eThirdParty['id'];

			new \thirdParty\ThirdPartyModel()
				->select('name')
				->update($eThirdParty);

		}

		foreach(new \bank\CashflowModel()
        ->select('id')
        ->getCollection() as $eCashflow) {

			$eCashflow['memo'] = 'Transaction '.$eCashflow['id'];
			$eCashflow['name'] = self::getFirstName($eCashflow['id']).' '.self::getLastName();

			new \bank\CashflowModel()
				->select(['memo', 'name'])
				->update($eCashflow);
		}

		new \journal\OperationModel()
			->where(TRUE)
			->update([
				'description' => new \Sql('CONCAT("Écriture ", id)'),
				'document' => new \Sql('CONCAT("Document ", id)'),
			]);

		new \asset\AssetModel()->where(TRUE)->update(['description' => new \Sql('CONCAT("Immobilisation ", id)')]);
		new \cash\CashModel()->where(TRUE)->update(['description' => new \Sql('CONCAT("Écriture de caisse ", id)')]);
		new \journal\JournalCodeModel()->whereIsCustom(TRUE)->update(['name' => new \Sql('CONCAT("Journal ", id)')]);
		new \account\AccountModel()
			->or(
				fn() => $this->whereClass('LIKE', '4551%'),
				fn() => $this->whereCustom(TRUE)
			)
			->update(['description' => new \Sql('CONCAT("Compte personnalisé ", id)')]);

		new \bank\BankAccountModel()
			->where(TRUE)
			->update([
				'bankId' => new \Sql('CONCAT("Numéro banque ", id)'),
				'accountId' => new \Sql('CONCAT("Numéro compte ", id)'),
			]);

	}

	public static function getModules(): array {

		$modules = [];

		foreach(\Package::getList() as $package => $app) {

			$directory = LIME_DIRECTORY.'/'.$app.'/'.$package.'/module/';

			foreach(glob($directory.'*.m.php') as $path) {

				$module = $package.'\\'.substr($path, strlen($directory), -6);

				$m = new \ReflectionClass($module.'Model')->newInstance();

				$modules[$module] = $m;

			}

		}

		return $modules;

	}

	public static function canCopyModule(\ModuleModel $m): bool {

		return (
			in_array($m->getPackage(), self::COPY_PACKAGE_EXCLUDE) === FALSE and
			in_array($m->getModule(), self::getModulesExclude()) === FALSE
		);

	}

	/**
	 * Les listes de la copie : celles de ce fichier, et celles que chaque package fermé déclare dans
	 * son `Structure.o.php`, absent de la version publique, qui ne peut pas nommer ses modules.
	 */
	public static function getModulesExclude(): array {
		return array_merge(self::COPY_MODULE_EXCLUDE, ...self::notify('getDemoModulesExclude'));
	}

	public static function getModulesOnce(): array {
		return array_merge(self::COPY_MODULE_ONCE, ...self::notify('getDemoModulesOnce'));
	}

	public static function getPropertiesExclude(): array {
		return array_merge(self::COPY_PROPERTY_EXCLUDE, ...self::notify('getDemoPropertiesExclude'));
	}

	public static function getLastName(): string {
		return substr('ABCDEFGHIJKLMNOPQRSTUVWXYZ', mt_rand(0, 25), 1).'.';
	}

	public static function getFirstName(int $position): string {

		if(self::$names === NULL) {

			self::$names = self::FIRST_NAMES;

			shuffle(self::$names);

		}

		return self::$names[$position % count(self::$names)];

	}

	public static function getElectronicAddressSiren(int $structureId): string {

		return match($structureId) {
			Structure::DEMO => '315143296_4945',
			Structure::DEMO_BIS => '315143296_4946',
		};

	}

}
?>
