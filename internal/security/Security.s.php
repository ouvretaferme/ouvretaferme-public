<?php
namespace security;

class SecuritySetting extends \Settings {

	const MAX_BY_PAGE = 50;

	// Journal d'accès Nginx et sa rotation de la veille : ce que lit le rapport GoAccess, et ce que
	// lira le cron des événements. Les chemins sont ceux de la production ; une installation de
	// développement n'a pas de journal Nginx à cet endroit et laisse le rapport sans objet.
	public static string $accessLog = '/var/log/nginx/access.log';
	public static string $accessLogRotated = '/var/log/nginx/access.log.1';

	// Répertoire hors du dépôt et hors de la racine servie, où GoAccess écrit son rapport, en
	// écriture pour l'utilisateur des crons. Vide, rien n'est produit ni lu et l'écran dit que le
	// rapport n'est pas configuré : c'est le cas d'une installation de développement, dont Nginx
	// tourne dans un autre conteneur que PHP.
	public static string $accessDirectory = (LIME_ENV === 'prod') ? '/var/www/access-report' : '';

}
?>
