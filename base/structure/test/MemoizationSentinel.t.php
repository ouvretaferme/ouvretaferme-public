<?php
namespace structure;

class MemoizationSentinelTest extends \dev\SentinelTest {

	const MEMOIZED = [
		'accounting/account/Account.s.php:summaryAccountingBalanceCategories' => ['global', 'réglage posé au chargement du package'],
		'accounting/account/lib/Account.l.php:cAccountByKey' => ['structure', 'clé par structure, exercice et jour (getKey())'],
		'accounting/account/lib/Account.l.php:usedByStructure' => ['structure', 'indexé par l\'id de la structure'],
		'accounting/asset/lib/Depreciation.l.php:cDepreciationByFinancialYear' => ['structure', 'clé par structure connectée et exercice (getCacheKey())'],
		'accounting/asset/lib/Expense.l.php:hasAssetsByStructure' => ['structure', 'indexé par l\'id de la structure'],
		'accounting/bank/lib/EnableBanking.l.php:networkErrors' => ['global', 'compte d\'erreurs réseau du processus, sans donnée de structure'],
		'accounting/bank/lib/Method.l.php:patterns' => ['global', 'motifs compilés depuis une constante'],
		'accounting/bank/lib/Method.l.php:transliterator' => ['global', 'translittérateur ICU, sans donnée'],
		'accounting/company/lib/StatisticAccountingAi.l.php:accountants' => ['global', 'statistiques du parc, remises à zéro à chaque calcul'],
		'accounting/company/lib/StatisticAccountingAi.l.php:annotationLast' => ['structure', 'indexé par l\'id de la structure, remis à zéro à chaque calcul'],
		'accounting/company/lib/StatisticAccountingAi.l.php:groups' => ['global', 'agrégat du parc, remis à zéro à chaque calcul'],
		'accounting/company/lib/StatisticAccountingAi.l.php:money' => ['structure', 'indexé par l\'id de la structure, remis à zéro à chaque calcul'],
		'accounting/company/lib/StatisticAccountingAi.l.php:started' => ['structure', 'indexé par l\'id de la structure, remis à zéro à chaque calcul'],
		'accounting/financialYear/lib/FinancialYear.l.php:cAllByStructure' => ['structure', 'indexé par l\'id de la structure'],
		'accounting/financialYear/lib/FinancialYear.l.php:cOpenFinancialYearByStructure' => ['structure', 'indexé par l\'id de la structure'],
		'accounting/financialYear/lib/FinancialYear.l.php:eLastByStructure' => ['structure', 'indexé par l\'id de la structure'],
		'accounting/journal/lib/JournalCode.l.php:cJournalCodeByStructure' => ['structure', 'indexé par l\'id de la structure'],
		'accounting/law/lib/Legifrance.l.php:token' => ['global', 'jeton d\'accès à l\'API Légifrance, commun à tout le parc'],
		'accounting/pdp/lib/Curl.l.php:requester' => ['global', 'closure posée par un test pour répondre à la place de SUPER PDP, sans donnée'],
		'accounting/pdp/lib/Superpdp.l.php:failDepth' => ['global', 'profondeur d\'appels imbriqués du processus'],
		'accounting/pdp/lib/SuperpdpBuilder.l.php:unknownStatusCodes' => ['global', 'codes de statut déjà signalés, communs à toutes les structures'],
		'accounting/pdp/lib/SuperpdpConnection.l.php:cachedConnectedPartnerByStructure' => ['structure', 'indexé par l\'id de la structure'],
		'accounting/pdp/lib/SuperpdpConnection.l.php:cachedValidPartnerByStructure' => ['structure', 'indexé par l\'id de la structure'],
		'accounting/preaccounting/lib/Fec.l.php:ccAccrualBySaleByStructure' => ['structure', 'indexé par l\'id de la structure, puis par vente'],
		'accounting/town/Town.s.php:sceneEnabled' => ['global', 'closure de SCENE_STRUCTURE_BETA, posée au chargement du package et reposée par un test'],
		'base/dashboard/Dashboard.s.php:enabled' => ['global', 'réglage posé au chargement du package'],
		'base/dashboard/Dashboard.s.php:landing' => ['global', 'réglage posé au chargement du package'],
		'base/dashboard/Dashboard.s.php:resolve' => ['global', 'réglage posé au chargement du package'],
		'base/dashboard/Dashboard.s.php:team' => ['global', 'réglage posé au chargement du package'],
		'base/dashboard/lib/Dashboard.l.php:blocks' => ['global', 'déclaration des blocs lue dans les packages'],
		'base/dashboard/lib/Dashboard.l.php:contributions' => ['global', 'déclaration des contributions lue dans les packages'],
		'base/entry/Entry.s.php:enabled' => ['global', 'closure du drapeau FEATURE_ENTRY, posée au chargement du package et reposée par un test'],
		'base/entry/lib/Entry.l.php:read' => ['global', 'numéro de la lecture en cours, un compteur qui ne porte aucune donnée'],
		'base/entry/lib/TimeIntention.l.php:objects' => ['structure', 'clé par lecture, structure et jour dit, une seule clé gardée'],
		'base/mail/Mail.s.php:brevoApiKey' => ['global', 'réglage posé au chargement de l\'application'],
		'base/mail/Mail.s.php:brevoWebhookToken' => ['global', 'réglage posé au chargement de l\'application'],
		'base/mail/Mail.s.php:emailName' => ['global', 'réglage posé au chargement de l\'application'],
		'base/mail/Mail.s.php:mailjetApiKeyPrivate' => ['global', 'réglage posé au chargement de l\'application'],
		'base/mail/Mail.s.php:mailjetApiKeyPublic' => ['global', 'réglage posé au chargement de l\'application'],
		'base/mail/Mail.s.php:mailjetApiToken' => ['global', 'réglage posé au chargement de l\'application'],
		'base/mail/lib/CustomizeTypeAi.l.php:types' => ['global', 'types d\'e-mails déclarés par les packages'],
		'ouvretaferme/main/Main.s.php:emailName' => ['global', 'réglage posé au chargement de l\'application'],
		'ouvretaferme/main/lib/Mail.l.php:sent' => ['global', 'journal des envois du processus, tenu en dev seulement pour les tests'],
		'base/media/Media.s.php:mediaDriver' => ['global', 'réglage posé au chargement de l\'application'],
		'base/media/Media.s.php:types' => ['global', 'réglage posé au chargement du package'],
		'base/media/lib/Media.l.php:hashesPending' => ['global', 'empreintes de fichiers en attente par type, uniques sur tout le stockage, vidées par deleteFilesPending()'],
		'base/notification/Notification.s.php:enabled' => ['global', 'réglage posé au chargement du package'],
		'base/notification/Notification.s.php:resolve' => ['global', 'réglage posé au chargement du package'],
		'base/notification/lib/Notification.l.php:databaseByStructure' => ['structure', 'indexé par l\'id de la structure'],
		'base/notification/lib/Notification.l.php:sources' => ['global', 'packages qui déclarent des notifications'],
		'base/scene/lib/Upload.l.php:node' => ['global', 'chemin réel du binaire node du serveur, le même pour tout le parc'],
		'base/scene/lib/Upload.l.php:rules' => ['global', 'règles d\'un profil de téléversement lues d\'un fichier du dépôt, les mêmes pour tout le parc'],
		'base/structure/Structure.s.php:communityDomain' => ['global', 'réglage posé au chargement du package'],
		'base/structure/lib/AffiliationAi.l.php:cCommunityByStructure' => ['structure', 'clé par structure et utilisateur'],
		'base/structure/lib/Conf.l.php:cachedByStructure' => ['structure', 'indexé par l\'id de la structure, une copie par Lib qui emploie le trait'],
		'base/structure/lib/Demo.l.php:demoMs' => ['global', 'modules de la base de démonstration'],
		'base/structure/lib/Demo.l.php:ms' => ['global', 'modules du schéma, sans donnée'],
		'base/structure/lib/Demo.l.php:names' => ['global', 'prénoms tirés d\'une constante'],
		'base/structure/lib/Demo.l.php:packages' => ['global', 'packages de la base'],
		'base/structure/lib/FixtureAi.l.php:writes' => ['global', 'compte d\'écritures du script de décor'],
		'base/structure/lib/Member.l.php:cMemberOnline' => ['user', 'indexé par l\'id de l\'utilisateur'],
		'base/structure/lib/Member.l.php:viewsCached' => ['structure', 'indexé par la clé redis, qui porte l\'id de la structure et celui de l\'utilisateur'],
		'base/structure/lib/WeatherAi.l.php:fetcher' => ['global', 'fonction de lecture substituée par les tests'],
		'base/structure/module/Structure.e.php:cProduction' => ['structure', 'indexé par l\'id de la structure'],
		'base/structure/module/Structure.e.php:eCommunity' => ['structure', 'indexé par l\'id de la structure'],
		'base/structure/module/Structure.e.php:eStructureConnected' => ['structure', 'la structure connectée elle-même, posée et retirée par setConnected()'],
		'base/structure/module/Structure.e.php:eSystem' => ['structure', 'indexé par l\'id de la structure'],
		'base/structure/module/Structure.e.php:producerType' => ['structure', 'indexé par l\'id de la structure'],
		'base/structure/module/Structure.e.php:seasonPlanned' => ['structure', 'indexé par l\'id de la structure'],
		'base/structure/module/Structure.e.php:sharedWithCommunity' => ['structure', 'clé par structure, utilisateur et droit'],
		'base/user/User.s.php:avatarUpload' => ['global', 'closure du drapeau FEATURE_UPLOAD_MODEL, posée au chargement du package et reposée par un test'],
		'base/user/lib/AdminLogAi.l.php:recorded' => ['global', 'une requête ne s\'inscrit qu\'une fois au journal'],
		'base/user/lib/Connection.l.php:eUserOnline' => ['user', 'l\'utilisateur connecté, unique dans une requête'],
		'base/user/lib/Country.l.php:countries' => ['global', 'liste des pays'],
		'base/user/lib/Tab.l.php:eUserWorkstation' => ['global', 'compte du poste, déclaré par son secret, le même pour toute structure'],
		'base/user/lib/UserAvatar.l.php:catalog' => ['global', 'catalogue de l\'éditeur d\'avatar lu d\'un fichier, le même pour tout le parc'],
		'commercialisation/amap/Amap.s.php:enabled' => ['global', 'closure du drapeau FEATURE_AMAP, posée au chargement du package et reposée par un test'],
		'commercialisation/amap/Amap.s.php:enabledByUser' => ['user', 'indexé par l\'id de l\'utilisateur, vidé quand la closure est reposée'],
		'commercialisation/amap/lib/Producer.l.php:cProducerByProducer' => ['structure', 'indexé par l\'id de la structure'],
		'commercialisation/amap/lib/Referent.l.php:producerIdsByAmapAndUser' => ['structure', 'clé par structure et utilisateur'],
		'commercialisation/payment/lib/Link.l.php:cacheSumup' => ['structure', 'indexé par l\'id de la structure, vidé à la désactivation des liens SumUp et par clearCache()'],
		'commercialisation/payment/lib/Method.l.php:cMethodByStructure' => ['structure', 'indexé par l\'id de la structure'],
		'commercialisation/payment/lib/PaymentTransaction.l.php:recalculate' => ['global', 'interrupteur posé par l\'appelant le temps d\'un lot'],
		'commercialisation/payment/lib/Stripe.l.php:cache' => ['structure', 'indexé par l\'id de la structure'],
		'commercialisation/payment/lib/StripeExpress.l.php:cache' => ['structure', 'indexé par l\'id de la structure, vidé par create(), update(), delete() et clearCache()'],
		'commercialisation/payment/lib/SumupCheckoutAi.l.php:requester' => ['global', 'closure posée par un test pour répondre à la place de SumUp, sans donnée'],
		'commercialisation/payment/lib/SumupReader.l.php:requester' => ['global', 'closure posée par un test pour répondre à la place de SumUp, sans donnée'],
		'commercialisation/payment/lib/SumupTransaction.l.php:reader' => ['global', 'closure posée par un test pour répondre à la place de l\'historique SumUp, sans donnée'],
		'commercialisation/payment/lib/SumupTransaction.l.php:transactionsByDay' => ['structure', 'indexé par l\'id de la structure puis par jour, vidé par setReader()'],
		'commercialisation/quotation/lib/Quotation.l.php:index' => ['global', 'index du référentiel de base, commun à tout le parc'],
		'commercialisation/quotation/lib/Reference.l.php:cCommunityByStructure' => ['structure', 'indexé par l\'id de la structure'],
		'commercialisation/quotation/lib/Reference.l.php:cStructure' => ['structure', 'indexé par l\'id de la structure, tout le parc lu d\'un coup'],
		'commercialisation/shop/Shop.s.php:domain' => ['global', 'réglage posé au chargement du package'],
		'commercialisation/shop/lib/Range.l.php:cCatalogOnline' => ['user', 'catalogues des structures de l\'utilisateur connecté, unique dans une requête'],
		'commercialisation/shop/lib/Share.l.php:cacheList' => ['global', 'indexé par l\'id de la boutique, unique dans la base commune'],
		'commercialisation/shop/lib/Share.l.php:cacheMatch' => ['structure', 'indexé par boutique puis par structure'],
		'internal/aggregate/lib/CultivationProjectionAi.l.php:monthByWeek' => ['global', 'calendrier, sans donnée'],
		'internal/data/lib/Migrate.l.php:running' => ['structure', 'doNN en cours, indexés par l\'id de la structure migrée, que la fonction d\'arrêt rend à leur version'],
		'ouvretaferme/admin/lib/StatisticAi.l.php:activeByDay' => ['global', 'statistique du parc, clé par période'],
		'ouvretaferme/admin/lib/StatisticAi.l.php:activeByRange' => ['global', 'statistique du parc, clé par période'],
		'ouvretaferme/admin/lib/StatisticAi.l.php:invoicesByDay' => ['global', 'statistique du parc, clé par période'],
		'ouvretaferme/admin/lib/StatisticAi.l.php:paymentsByDay' => ['global', 'statistique du parc, clé par période'],
		'ouvretaferme/admin/lib/StatisticAi.l.php:rolesByDay' => ['global', 'statistique du parc, clé par période'],
		'ouvretaferme/admin/lib/StatisticAi.l.php:salesByDay' => ['global', 'statistique du parc, clé par période'],
		'ouvretaferme/game/lib/Player.l.php:ePlayerOnline' => ['user', 'indexé par l\'id de l\'utilisateur connecté'],
		'ouvretaferme/game/module/Player.e.php:premium' => ['user', 'indexé par l\'id de l\'utilisateur du joueur'],
		'ouvretaferme/learning/lib/Share.l.php:cShareByUser' => ['user', 'indexé par l\'id de l\'utilisateur'],
		'ouvretaferme/learning/lib/Share.l.php:cShareNetworkByUser' => ['user', 'indexé par l\'id de l\'utilisateur'],
		'ouvretaferme/main/lib/Place.l.php:fetcher' => ['global', 'fonction de lecture substituée par les tests'],
		'ouvretaferme/subscription/lib/Subscription.l.php:softwareCache' => ['structure', 'indexé par l\'id de la structure, puis par date'],
		'production/map/Map.s.php:irrigationEnabled' => ['global', 'réglage posé au chargement du package'],
		'production/map/Map.s.php:reliefTransport' => ['global', 'transport vers l\'IGN posé au chargement du package, qu\'un test remplace'],
		'production/map/Map.s.php:decorUploadEnabled' => ['global', 'réglage posé au chargement du package'],
		'production/map/Map.s.php:mapboxToken' => ['global', 'réglage posé au chargement de l\'application'],
		'production/map/lib/DecorBuilding.l.php:choices' => ['global', 'choix de l\'éditeur lus dans un fichier du dépôt, les mêmes pour toutes les structures'],
		'production/plant/lib/PlantMappingAi.l.php:mapping' => ['global', 'table de correspondance lue dans un fichier du dépôt'],
		'production/plant/lib/VarietyJournalAi.l.php:cPlantReference' => ['global', 'espèces du référentiel, indexées par leur fqn'],
		'production/toolbox/lib/Composition.l.php:sales' => ['structure', 'indexé par l\'id de la structure'],
		'production/toolbox/lib/Composition.l.php:source' => ['global', 'source du référentiel et de la mercuriale, commune à tout le parc'],
		'production/toolbox/lib/Favorite.l.php:cachedByStructure' => ['structure', 'indexé par structure puis par utilisateur'],
	];

	const KEYS = ['structure', 'user', 'global'];

	public function init() {

		// Les `.m.php` sont générés par `framework/dev/lib/Module.l.php` : `model` y est le singleton du
		// module, `affected` le compte de la dernière écriture, et `cache` est indexé par la clé que
		// pose l'appelant de `getCache()`.
		foreach(['.l.php', '.e.php', '.s.php'] as $suffix) {

			foreach(\dev\SentinelLib::getFiles($suffix) as $file => $path) {

				if(str_starts_with($file, 'framework/')) {
					continue;
				}

				foreach(self::findMemoizations(\dev\SentinelLib::getTokens(file_get_contents($path))) as $property => $line) {
					$this->addFoundKey('memoization', $file, $property, $line);
				}

			}

		}

	}

	public function testDeclared(): void {
		$this->assertDeclared('memoization', array_map(fn(array $declaration) => $declaration[1], self::MEMOIZED), 'propriété statique réaffectée et non déclarée dans MEMOIZED : la déclarer avec sa clé (structure, user ou global) et sa raison, et indexer par l\'id de la structure connectée une donnée qui en dépend');
	}

	public function testKeys(): void {

		$invalid = [];

		foreach(self::MEMOIZED as $memoization => [$key]) {

			if(in_array($key, self::KEYS, TRUE) === FALSE) {
				$invalid[] = $memoization;
			}

		}

		$this->assertEquals([], $invalid, 'déclaration sans clé connue ('.implode(', ', self::KEYS).') : '.implode(' · ', $invalid));

	}

	public function testDetector(): void {

		$code = <<<'PHP'
<?php
class Fixture {

	private static array $byStructure = [];
	protected static ?Fixture $current = NULL;
	public static $counter = 0;
	private static array $nested = [];
	const LIST = [];
	private static array $configuration = ['a' => 1];
	private $instance = [];

	public static function run(): void {

		static $local = NULL;
		$local = 1;

		self::$byStructure[1] = TRUE;
		static::$current ??= new Fixture();
		Fixture::$counter++;
		self::$nested[1][] = 2;
		$read = self::$configuration['a'];
		$this->instance = [];

	}

}
PHP;

		$this->assertEquals(
			['byStructure' => 4, 'current' => 5, 'counter' => 6, 'nested' => 7],
			self::findMemoizations(\dev\SentinelLib::getTokens($code))
		);

	}

	/**
	 * Les propriétés statiques déclarées dans une classe du fichier et réaffectées dans ce même
	 * fichier, avec la ligne de leur déclaration. Une statique jamais réaffectée est une constante
	 * de configuration, pas une mémoïsation.
	 *
	 * @return array<string, int>
	 */
	public static function findMemoizations(array $tokens): array {

		$declared = [];

		foreach($tokens as $i => [$type]) {

			if($type !== T_STATIC) {
				continue;
			}

			$previous = $tokens[$i - 1][0] ?? NULL;
			$next = $tokens[$i + 1][0] ?? NULL;

			if(
				in_array($previous, [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_READONLY], TRUE) === FALSE and
				in_array($next, [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_READONLY], TRUE) === FALSE
			) {
				continue;
			}

			for($j = $i + 1; isset($tokens[$j]); $j++) {

				$current = $tokens[$j][0];

				if($current === T_VARIABLE) {
					$declared[substr($tokens[$j][1], 1)] = $tokens[$j][2];
					break;
				}

				if(in_array($current, [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_READONLY, T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_ARRAY, T_CALLABLE, '?', '|'], TRUE) === FALSE) {
					break;
				}

			}

		}

		$written = [];

		foreach($tokens as $i => [$type, $text]) {

			if(
				$type !== T_VARIABLE or
				($tokens[$i - 1][0] ?? NULL) !== T_DOUBLE_COLON or
				array_key_exists(substr($text, 1), $declared) === FALSE
			) {
				continue;
			}

			$after = $i + 1;

			while(($tokens[$after][0] ?? NULL) === '[') {
				$after = \dev\SentinelLib::getClosing($tokens, $after) + 1;
			}

			if(in_array($tokens[$after][0] ?? NULL, ['=', T_COALESCE_EQUAL, T_CONCAT_EQUAL, T_PLUS_EQUAL, T_MINUS_EQUAL, T_MUL_EQUAL, T_INC, T_DEC], TRUE)) {
				$written[substr($text, 1)] = TRUE;
			}

		}

		return array_intersect_key($declared, $written);

	}

}
?>
