<?php
namespace main;

class MainSetting extends \Settings {

	const MAINTENANCE = FALSE;

	// Passe à TRUE quand document/cgu.pdf porte la dernière version déclarée dans UserSetting::$tos
	const CGU_POPUP = (LIME_ENV !== 'dev');

	const LIMIT_TRAINING = '2025-01-10';

	const URL_PHOTOS = 'https://www.dropbox.com/scl/fo/z3nkaufqkik90d0xs0b6q/h?rlkey=b69944c6ui40ck22gi6lk2imb&dl=0';

	const URL_ASSOCIATION = 'https://asso.ouvretaferme.org';

	// Le salon ne s'ouvre qu'à un membre du serveur ; l'invitation, à tout le monde
	const URL_DISCORD_CHANNEL = 'https://discord.com/channels/1344219338684497961/1444680100548448307';
	const URL_DISCORD_INVITE = 'https://discord.gg/bdSNc3PpwQ';

	const EMAIL_TEAM = 'contact@ouvretaferme.org';

	// L'envoi de MailLib, celui de la version publique ; base/mail garde les siens dans MailSetting
	public static $emailName;
	const EMAIL_FROM = 'ne-pas-repondre@ouvretaferme.org';
	const EMAIL_MAX_WIDTH = 700;

	// En dev, les seules adresses auxquelles un e-mail part.
	public static $emailDevSendOnly = [];

	const SAS = [
		'legalName' => 'SAS OTF',
		'legalForm' => 'Société par actions simplifiée',
		'capital' => 40000,
		'siren' => '102075736',
		'siret' => '10207573600011',
		'rcs' => 'Clermont-Ferrand',
		'vatNumber' => 'FR63102075736',
		'legalStreet1' => '21 rue de Pagnat',
		'legalPostcode' => '63450',
		'legalCity' => 'Saint-Amant-Tallende',
	];

	// La structure de la SAS dans le produit : elle encaisse pour la plateforme et facture les abonnements et les formations
	const SAS_STRUCTURE = 3994;

	const EXPORT_PRIVATE_PATHS = [
		'accounting/', // décision du 05/10 : la comptabilité n'est pas publiée
		'ouvretaferme/subscription/', // décision du 05/10 : les abonnements ne sont pas publiés
		'ouvretaferme/admin/', // décision du 19/10 : les statistiques du parc ne sont pas publiées
		'commercialisation/', // décision du 09/10 : la commercialisation n'est pas publiée
		'ouvretaferme/analyze/', // décision du 09/10 : les analyses ne sont pas publiées
		'ouvretaferme/presentation/', // décision du 09/10 : les pages de présentation ne sont pas publiées
		'ouvretaferme/association/', // décision du 24/10 : l'adhésion à l'association n'est pas publiée
		'base/mail/', // décision du 17/10 : les campagnes et la newsletter ne sont pas publiées
		'.github/', // les workflows lisent des secrets du dépôt de l'équipe et poussent ses assets
		'*CLAUDE.md', // décision du 25/10 : les consignes de l'équipe, publiées plus tard sous la forme du kit
		'base/scene/upload/*/SKILL.md', // décision du 25/10 : même raison
		'migration/', // journal de ce qui a été joué sur la production
		'*/page/configure/*', // scripts à usage unique sur la production
		'.claude/', // outillage interne de l'équipe, publié sous une forme dérivée
		'*/page/admin/*', // décision du 05/10 : l'administration de OTF n'est pas publiée
		'*/view/admin/*', // vues des pages d'administration
		'*/lib/Admin.l.php', // lus par les seules pages d'administration
		'base/*/ui/Admin*.u.php', // lus par les seules pages d'administration
		'framework/api/ui/Admin.u.php', // lu par les seules pages d'administration
		'internal/securing/ui/Admin.u.php', // lu par les seules pages d'administration
		'base/*/static/css/admin.css', // chargés par les seules interfaces d'administration
		'framework/api/static/*/admin.*', // chargés par la seule interface d'administration
		'internal/*/static/js/admin.js', // chargés par les seules interfaces d'administration
		'base/structure/test/AdminProducerTypeAi.t.php', // éprouve un écran d'administration
		// Tests publiés qui nomment une classe fermée, ou l'atteignent par un intermédiaire (ClosedBoundarySentinel::REACHING) : ils restent dans le dépôt, la copie publique ne les lance pas (décision du 24/10, ClosedBoundarySentinel les relève)
		'base/dashboard/test/Create.t.php',
		'base/dashboard/test/Head.t.php',
		'base/entry/test/IntentionSentinel.t.php',
		'base/note/test/NoteTargetSentinel.t.php',
		'base/notification/test/NotificationDescribeAi.t.php',
		'base/notification/test/NotificationFixture.t.php',
		'base/notification/test/NotificationPilotageAi.t.php',
		'base/notification/test/NotificationSetting.t.php',
		'base/notification/test/Notification.t.php',
		'base/notification/test/NotificationTypeSentinel.t.php',
		'base/product/test/CsvAi.t.php',
		'base/product/test/ProductDeleteEngagedAi.t.php',
		'base/product/test/ProductVariantAi.t.php',
		'base/product/test/ProductVignetteApiAi.t.php',
		'base/product/test/ProductVignetteCopyAi.t.php',
		'base/product/test/ProductWebAi.t.php',
		'base/product/test/StockAi.t.php',
		'base/product/test/StockApiAi.t.php',
		'base/product/test/TabFoldAi.t.php',
		'base/product/test/VariantListAi.t.php',
		'base/structure/test/AccessSectionAi.t.php',
		'base/structure/test/AccountingAnalyzeRightsAi.t.php',
		'base/structure/test/ApiKeyPageAi.t.php',
		'base/structure/test/BorrowedSentinelAi.t.php',
		'base/structure/test/CommunityAddressAi.t.php',
		'base/structure/test/CommunityDashboard.t.php',
		'base/structure/test/ConfForgetSentinel.t.php',
		'base/structure/test/CrossDatabaseJoinSentinelAi.t.php',
		'base/structure/test/DemoCopySentinelAi.t.php',
		'base/structure/test/DisconnectDatabaseAi.t.php',
		'base/structure/test/DuplicateAi.t.php',
		'base/structure/test/DuplicateSentinelAi.t.php',
		'base/structure/test/FixtureAi.t.php',
		'base/structure/test/FixtureSentinelAi.t.php',
		'base/structure/test/HasVatBooleanSentinelAi.t.php',
		'base/structure/test/ImportExportGuardSentinelAi.t.php',
		'base/structure/test/InviteApiAi.t.php',
		'base/structure/test/MenuAmapAi.t.php',
		'base/structure/test/MenuFindCategoryAi.t.php',
		'base/structure/test/ProducerTypeChoiceAi.t.php',
		'base/structure/test/ProducerTypeReaderAi.t.php',
		'base/structure/test/RealStructureSentinelAi.t.php',
		'base/structure/test/SchoolAi.t.php',
		'base/structure/test/TipSentinel.t.php',
		'base/structure/test/VatConfiguredAi.t.php',
		'base/user/test/TosAi.t.php',
		'framework/dev/test/ActionHighlight.t.php',
		'framework/dev/test/ApiDocFieldTypeSentinel.t.php',
		'framework/dev/test/ApiDocWriteSchemaAi.t.php',
		'framework/dev/test/ApiSigningKeySentinel.t.php',
		'framework/dev/test/ElementValidateSentinel.t.php',
		'framework/dev/test/ErrorSecret.t.php',
		'framework/dev/test/JoinResetAi.t.php',
		'framework/dev/test/LayerSentinel.t.php',
		'framework/dev/test/NumericInput.t.php',
		'framework/dev/test/OrmSentinel.t.php',
		'framework/dev/test/PageRightSentinel.t.php',
		'framework/dev/test/PropertySentinel.t.php',
		'framework/dev/test/SchemaClassSentinel.t.php',
		'framework/dev/test/SchemaSentinel.t.php',
		'framework/dev/test/SqlInputSentinel.t.php',
		'framework/dev/test/SubQueryAi.t.php',
		'framework/dev/test/TransactionSentinel.t.php',
		'framework/dev/test/WriteInGetSentinel.t.php',
		'framework/dev/test/WriteRightSentinel.t.php',
		'framework/session/test/SessionHijackSentinel.t.php',
		'internal/aggregate/test/CompositionSourceAi.t.php',
		'internal/data/test/MigrateReplayAi.t.php',
		'internal/data/test/MigrateTransactionAi.t.php',
		'internal/data/test/MigrationFixtureSentinel.t.php',
		'internal/data/test/MigrationFormatSentinelAi.t.php',
		'internal/data/test/MigrationIdempotentAi.t.php',
		'internal/securing/test/LogAi.t.php',
		'internal/security/test/NginxScanSentinelAi.t.php',
		'ouvretaferme/design/test/DecorSentinelAi.t.php',
		'ouvretaferme/learning/test/AccountWelcome.t.php',
		'ouvretaferme/learning/test/CustomizeNetwork.t.php',
		'ouvretaferme/learning/test/Offer.t.php',
		'ouvretaferme/learning/test/Pricing.t.php',
		'ouvretaferme/learning/test/StructureSettings.t.php',
		'ouvretaferme/main/test/ClosedBoundarySentinel.t.php',
		'ouvretaferme/main/test/LegacyTokenSentinelAi.t.php',
		'ouvretaferme/main/test/Mail.t.php',
		'ouvretaferme/main/test/SiteUrlAi.t.php',
		'ouvretaferme/website/test/DesignSubscriptionAi.t.php',
		'ouvretaferme/website/test/TemplateAmapAi.t.php',
		'ouvretaferme/website/test/TemplateAmapPagesAi.t.php',
		'ouvretaferme/website/test/TemplateWordingAi.t.php',
		'production/planning/test/HarvestFixture.t.php',
		'production/planning/test/HarvestSales.t.php',
		'production/toolbox/test/CompositionPrice.t.php',
		'production/toolbox/test/CompositionSale.t.php',
	];

	/**
	 * Un motif finissant par `/` désigne un répertoire depuis la racine, les autres se lisent comme
	 * une pathspec git, où `*` traverse les `/`.
	 */
	public static function isExportPrivate(string $file, array $patterns = self::EXPORT_PRIVATE_PATHS): bool {

		foreach($patterns as $pattern) {

			if(str_ends_with($pattern, '/') ? str_starts_with($file, $pattern) : fnmatch($pattern, $file)) {
				return TRUE;
			}

		}

		return FALSE;

	}

	public static ?\user\User $onlineUser = NULL;
	public static array $backupServer = ['user' => NULL, 'hostname' => NULL];
	public static array $crypt = [];
	public static array $hmacKeys = [];

	public static ?int $onlineSeason = NULL;

	public static $discord = [
		'clientId' => '',
		'clientSecret' => '',
		'botToken' => '',
		'guildId' => '',
	];

}

// Name shown as email sender
MainSetting::$emailName = \L::getVariable('siteName');
?>
