<?php
namespace communication;

class CommunicationSetting extends \Settings {

	// Active la fonctionnalité d'invitation personnelle sur Discord
	const DISCORD_INVITATION = (LIME_ENV === 'dev');

	// Durée de vie du `state` du retour d'autorisation, sur le modèle de PaymentSetting::OAUTH_STATE_LIFETIME.
	const DISCORD_STATE_LIFETIME = 3600;

	const IS_WEBINAIRE_ACTIVATED = TRUE;
	const WEBINAIRE_MAX_SUBSCRIPTIONS = 40;
	const WEBINAIRE_DATES = [
		['date' => '2026-07-29', 'hour' => '13h00', 'url' => 'https://kmeet.infomaniak.com/urg5wiqlgu21y56msmfnxmjk'],
		['date' => '2026-08-04', 'hour' => '12h30'],
		['date' => '2026-08-06', 'hour' => '16h00'],
		['date' => '2026-08-13', 'hour' => '12h30'],
		['date' => '2026-08-18', 'hour' => '12h30'],
		['date' => '2026-08-20', 'hour' => '16h00'],
		['date' => '2026-08-25', 'hour' => '12h30'],
		['date' => '2026-08-28', 'hour' => '16h00'],
		['date' => '2026-08-31', 'hour' => '12h30'],
	];

	// La clé est ce que la note stocke. Le groupe de type `ACCOUNTING` n'est pas proposé : aucune
	// structure active n'en est une (23/09/2026).
	const NOTE_STRUCTURE_TYPES = [
		'producer-real' => [\structure\Structure::PRODUCER, 'producerType', \structure\Structure::REAL],
		'producer-installation' => [\structure\Structure::PRODUCER, 'producerType', \structure\Structure::INSTALLATION],
		'producer-test' => [\structure\Structure::PRODUCER, 'producerType', \structure\Structure::TEST],
		'producer-school' => [\structure\Structure::PRODUCER, 'producerType', \structure\Structure::SCHOOL],
		'producer-learner' => [\structure\Structure::PRODUCER, 'producerType', \structure\Structure::LEARNER],
		'producer-project' => [\structure\Structure::PRODUCER, 'producerType', \structure\Structure::PROJECT],
		'shop' => [\structure\Structure::SHOP, NULL, NULL],
		'amap' => [\structure\Structure::AMAP, NULL, NULL],
		'community-producer' => [\structure\Structure::COMMUNITY, 'communityType', \structure\Structure::PRODUCER],
		'community-education' => [\structure\Structure::COMMUNITY, 'communityType', \structure\Structure::EDUCATION],
		'community-network' => [\structure\Structure::COMMUNITY, 'communityType', \structure\Structure::NETWORK],
	];

	// Les colonnes de `structure\Feature` propres à un groupe, et `production`, ne disent rien de ce
	// qu'une structure utilise.
	const NOTE_FEATURES = ['hasCultivations', 'hasSales', 'hasShops', 'hasInvoices', 'hasStock', 'hasWallet', 'hasQuotation', 'hasPurchases', 'hasAccounting', 'hasFinancialYears', 'hasPdp', 'hasEnableBanking'];

	// Salon Discord de l'équipe, pour les alertes qui doivent être vues de tous (cf. DiscordWebhookLib)
	public static $discordTeamWebhookUrl = '';

	// Salon des migrations, où un push annonce ce qu'il y a à jouer sur une base de développement
	public static $discordMigrationWebhookUrl = '';

	// Salon de sécurité, pour les tentatives de connexion qui visent un administrateur ou qui aboutissent après des échecs
	public static $discordSecurityWebhookUrl = '';

	// Salon de la veille sur les textes de droit que le code cite (`law/cron/watch`)
	public static $discordLawWebhookUrl = '';

	// Salon de la veille sur les sources du catalogue des aides (`aid/cron/watch`)
	public static $discordAidWebhookUrl = '';

	public static $discord = [
		'clientId' => '',
		'clientSecret' => '',
		'botToken' => '',
		'guildId' => '',
		'roleId' => '',
	];

}

?>
<?php
