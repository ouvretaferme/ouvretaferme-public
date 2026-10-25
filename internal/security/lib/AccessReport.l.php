<?php
namespace security;

/**
 * Le rapport GoAccess des accès Nginx : un fichier HTML autonome, produit toutes les dix minutes par
 * le cron du package et lu par l'admin derrière validateAdmin().
 *
 * Le format du journal est celui de la production, sans référent et avec l'hôte entre guillemets et
 * le temps de réponse en fin de ligne : éprouvé le 23/09/2026 sur le journal du jour (80 994 lignes
 * lues, 0 rejetée) en GoAccess 1.7 (conteneur de dev) et 1.12 (poste), la production ayant la 1.8.1.
 */
class AccessReportLib {

	const FILE = 'report.html';
	const FILE_TEMPORARY = 'report.new.html';

	const LOG_FORMAT = '%h - %^ [%d:%t %^] "%v" "%r" %s %b "%u" %T';
	const DATE_FORMAT = '%d/%b/%Y';
	const TIME_FORMAT = '%H:%M:%S';

	public static function isConfigured(): bool {
		return SecuritySetting::$accessDirectory !== '';
	}

	public static function getFile(): string {
		return rtrim(SecuritySetting::$accessDirectory, '/').'/'.self::FILE;
	}

	public static function getTemporaryFile(): string {
		return rtrim(SecuritySetting::$accessDirectory, '/').'/'.self::FILE_TEMPORARY;
	}

	public static function exists(): bool {
		return (self::isConfigured() and is_file(self::getFile()));
	}

	/**
	 * La date du dernier rapport produit, celle du fichier, ou NULL s'il n'existe pas
	 */
	public static function getGeneratedAt(): ?string {

		if(self::exists() === FALSE) {
			return NULL;
		}

		return date('Y-m-d H:i:s', filemtime(self::getFile()));

	}

	/**
	 * Les journaux que GoAccess va lire : le courant et la rotation de la veille, pour 24 à 48 heures
	 * de recul. Un fichier absent ou illisible est écarté sans erreur : après la rotation de minuit,
	 * le journal courant est vide pendant quelques minutes, et une installation sans rotation n'a
	 * pas de fichier de la veille.
	 */
	public static function getLogs(): array {

		$logs = [];

		foreach([SecuritySetting::$accessLog, SecuritySetting::$accessLogRotated] as $log) {

			if(is_file($log) and is_readable($log)) {
				$logs[] = $log;
			}

		}

		return $logs;

	}

	/**
	 * Produit le rapport. Rend FALSE sans rien écrire quand le répertoire n'est pas configuré, qu'aucun
	 * journal n'est lisible, ou que GoAccess a échoué : le rapport précédent reste en place.
	 */
	public static function generate(): bool {

		if(self::isConfigured() === FALSE) {
			return FALSE;
		}

		$logs = self::getLogs();

		if($logs === []) {
			return FALSE;
		}

		$file = self::getFile();

		$args = '';

		foreach($logs as $log) {
			$args .= ' '.escapeshellarg($log);
		}

		$args .= ' '.escapeshellarg('--log-format='.self::LOG_FORMAT);
		$args .= ' '.escapeshellarg('--date-format='.self::DATE_FORMAT);
		$args .= ' '.escapeshellarg('--time-format='.self::TIME_FORMAT);
		// GoAccess écrit sa progression sur la sortie d'erreur, que cron transformerait en un courriel
		// toutes les dix minutes
		$args .= ' --no-progress';
		// Le rapport s'écrit à côté puis remplace l'ancien d'un seul geste : l'admin qui le lit pendant
		// la production ne voit jamais un fichier à moitié écrit. Le nom temporaire garde l'extension
		// .html, parce que GoAccess choisit le format de sortie d'après elle et n'écrit rien sur un
		// nom qu'il ne reconnaît pas, avec un code de retour 0 (vu sur report.html.tmp).
		$temporary = self::getTemporaryFile();

		$args .= ' '.escapeshellarg('--output='.$temporary);

		exec('goaccess'.$args, $output, $code);

		if($code !== 0 or is_file($temporary) === FALSE) {
			return FALSE;
		}

		return rename($temporary, $file);

	}

}
?>
