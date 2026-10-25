<?php
Database::addServer([
	'name' => 'ouvretaferme',
	'type' => 'MySQL',
	'host' => 'mysql-otf',
	'port' => 3306,
	'login' => 'root',
	'password' => '',
	'bases' => ['dev_ouvretaferme', 'demo_ouvretaferme']
]);

RedisCache::addServer('default', 'redis-otf', 6379, ['timeout' => 2]);

require_once Lime::getPath().'/package.c.php';

\map\MapSetting::$mapboxToken = '[TOKEN]';

// Secrets des routes ->remote(), celles que le navigateur sans tête ouvre pour rendre un PDF.
// Une clé absente ferme les routes du paquet, elle ne les ouvre plus.
Lime::setRemoteKey('selling', '[KEY]');
Lime::setRemoteKey('accounting', '[KEY]');
Lime::setRemoteKey('invoicing', '[KEY]');

// Secret de la route /head, qui rend le commit de l'installation aux postes de l'équipe.
// La même valeur est rangée sur chaque poste par `.claude/conseil/tete.py cle`.
Lime::setRemoteKey('head', '[KEY]');

\main\MainSetting::$emailDevSendOnly = ['[EMAIL]'];

// Webhook du salon Discord de l'équipe (Paramètres du salon > Intégrations > Webhooks), pour les
// alertes que toute l'équipe doit voir : veille sur les millésimes de CERFA, etc.
// Laisser vide en développement : les alertes ne partent alors nulle part.
\communication\CommunicationSetting::$discordTeamWebhookUrl = '[WEBHOOK_URL]';

// Webhook du salon des migrations, où `/push` annonce les commandes à jouer pour remettre une base
// de développement au niveau du code qu'on vient de pousser.
// Celui-ci se remplit aussi en développement, contrairement au précédent : c'est depuis une machine
// de développement qu'on pousse, et le message ne sert qu'aux machines de développement.
\communication\CommunicationSetting::$discordMigrationWebhookUrl = '[WEBHOOK_URL]';

// Webhook du salon de la veille des aides, où `aid/cron/watch` dit chaque matin les sources qui ont
// cessé de répondre, changé d'adresse ou refusé la lecture. Laisser vide en développement : la veille
// s'y lance à la main et n'a personne à prévenir.
\communication\CommunicationSetting::$discordAidWebhookUrl = '[WEBHOOK_URL]';

// Clé de l'API de Mistral (console.mistral.ai > API Keys), pour le contrôle des tiers d'une demande
// d'aide comptable, qui envoie les libellés de tiers des FEC déposés. Vide en développement : la base
// de dev est une copie de la production, et une recette y enverrait de vrais libellés.

// Clé d'API du réseau des tutoriels en production (learning:read et learning:write), que lit
// `learning/configure/tutorialImportAi target=prod` depuis un poste de l'équipe. Seulement sur le
// poste qui charge les tutoriels, jamais sur le serveur.
// define('API_TUTORIAL_FAQ', '[KEY]');

// L'accueil comme point d'arrivée, et l'onglet Pilotage qui y mène
// (`dashboard\DashboardSetting::$landing`). Sans cette ligne la constante vaut TRUE et l'arrivée
// suit l'écran d'accueil (FEATURE_DASHBOARD). FALSE la referme sur ce poste, une liste d'identifiants
// ne la garde que pour celles-ci. Elle ne s'ouvre jamais pour une structure que FEATURE_DASHBOARD
// n'ouvre pas.
// define('FEATURE_DASHBOARD_LANDING', FALSE);

// Identité par onglet, en dev seulement : un onglet prend un compte du décor (/user/admin/agent), ou
// tout compte choisi par un administrateur dans la liste des utilisateurs, sans toucher au compte des
// autres onglets. Sans cette ligne, ces gestes n'existent pas et aucune requête n'ouvre de session
// d'onglet.
// \session\SessionLib::setTabEnabled(TRUE);

// Le compte du poste, que la commande user/tab:browserToken rend au navigateur quand un agent l'a
// déconnecté. Il suppose la ligne précédente.
// \user\TabLib::setWorkstationUser(new \user\User(['id' => 1]));
?>
