<?php
namespace security;

/**
 * La revue des chemins que Nginx refuse avant PHP, dans `framework/dev/conf/rewrite/scan.cfg`.
 *
 * Ce fichier refuse une liste close de chemins, et une liste close se périme : les balayages
 * changent de cible à mesure que des logiciels sortent et que d'autres tombent en désuétude. Rien
 * dans le produit ne le signalera, un chemin refusé coûtant exactement zéro et un chemin non refusé
 * ne coûtant qu'un 404 que personne ne lit. La revue est donc le seul mécanisme qui tient la liste à
 * jour, et c'est pour cela qu'un cron la rappelle tous les mois plutôt que de compter sur la mémoire
 * de quelqu'un.
 *
 * Elle lit les journaux Nginx encore présents sur le serveur, compte les réponses 4xx qu'aucun motif
 * ne couvre et les classe par volume. Elle ne propose aucun motif : reconnaître une console
 * d'administration d'un logiciel qu'on n'a pas est un jugement, et un motif trop large coupe une
 * adresse du produit sans que rien ne le dise. Ce que la revue apporte est l'ordre de grandeur, qui
 * dit s'il y a lieu d'écrire un motif.
 *
 * Le volume refusé qu'elle rend par ailleurs dit ce que les motifs en place épargnent à PHP, et il
 * reste visible dans la surveillance : `AccessEventLib::accumulate()` range tout statut de 400 à 499
 * en balayage, et 444 en est un.
 *
 * Le compte des 4xx qu'un motif couvre ne tombe pas à zéro après un déploiement : le vhost des
 * médias n'inclut pas le fichier de refus, n'atteignant pas PHP, donc un balayage dirigé contre lui
 * continue de rendre ses 404.
 */
class ScanReviewAiLib {

	/** Le fichier de refus, relatif à la racine du dépôt */
	const PATH = 'framework/dev/conf/rewrite/scan.cfg';

	/** Le statut que `scan.cfg` rend, et que Nginx journalise sans envoyer de réponse */
	const STATUS_REFUSED = 444;

	/** En dessous, un chemin est du bruit de fond et n'appelle aucun motif */
	const CANDIDATE_THRESHOLD = 10;

	/** Ce que la revue imprime, le reste étant compté en bloc */
	const CANDIDATE_LIMIT = 40;

	/**
	 * Les motifs que `scan.cfg` oppose à `$request_uri`, dans l'ordre du fichier.
	 */
	public static function getPatterns(): array {

		$content = file_get_contents(LIME_DIRECTORY.'/'.self::PATH);

		if($content === FALSE) {
			throw new \Exception(self::PATH.' est illisible');
		}

		preg_match_all('#\$request_uri\s+~\*\s+"([^"]+)"#', $content, $matches);

		return $matches[1];

	}

	/**
	 * Un motif Nginx en motif PCRE insensible à la casse, comme le fait `~*`. Le délimiteur est
	 * échappé dans le motif plutôt que choisi parmi plusieurs : un fichier de configuration n'a
	 * aucune raison de se plier au délimiteur de son lecteur.
	 */
	public static function compile(string $pattern): string {
		return '~'.str_replace('~', '\~', $pattern).'~i';
	}

	/**
	 * Les journaux d'accès présents, du plus ancien au plus récent, compressés compris. La revue lit
	 * ce que la rotation a laissé et non une durée convenue : combien de jours tiennent là est un
	 * réglage du serveur, que le dépôt ne connaît pas.
	 */
	public static function getFiles(): array {

		$files = glob(SecuritySetting::$accessLog.'*') ?: [];

		// access.log, access.log.1, access.log.2.gz… : le suffixe numérique croît avec l'âge, donc
		// l'ordre chronologique est l'inverse de l'ordre naturel des noms
		usort($files, fn($a, $b) => strnatcmp($b, $a));

		return $files;

	}

	/**
	 * Le relevé : ce que les journaux portent, ce que les motifs refusent déjà, et les chemins en 4xx
	 * qu'aucun motif ne couvre.
	 */
	public static function review(): array {

		$patterns = array_map([self::class, 'compile'], self::getPatterns());

		$files = self::getFiles();

		$lines = 0;
		$refused = 0;
		$covered = 0;
		$unreadable = 0;
		$from = NULL;
		$to = NULL;
		$paths = [];

		foreach($files as $file) {

			$compressed = str_ends_with($file, '.gz');
			$handle = $compressed ? gzopen($file, 'rb') : fopen($file, 'rb');

			if($handle === FALSE) {
				continue;
			}

			while(($line = $compressed ? gzgets($handle) : fgets($handle)) !== FALSE) {

				$lines++;

				$hit = AccessEventLib::parse($line);

				if($hit === NULL) {
					$unreadable++;
					continue;
				}

				// Deux extremums et non la liste des heures : un mois de journal porte plus d'un million
				// de lignes, dont on ne tire que ces deux bornes
				if($from === NULL or $hit['hour'] < $from) {
					$from = $hit['hour'];
				}

				if($to === NULL or $hit['hour'] > $to) {
					$to = $hit['hour'];
				}

				if($hit['status'] === self::STATUS_REFUSED) {
					$refused++;
					continue;
				}

				if($hit['status'] < 400 or $hit['status'] >= 500) {
					continue;
				}

				foreach($patterns as $pattern) {
					if(preg_match($pattern, $hit['path']) === 1) {
						$covered++;
						continue 2;
					}
				}

				$paths[$hit['path']] = ($paths[$hit['path']] ?? 0) + 1;

			}

			if($compressed) {
				gzclose($handle);
			} else {
				fclose($handle);
			}

		}

		arsort($paths);

		$candidates = array_filter($paths, fn($count) => $count >= self::CANDIDATE_THRESHOLD);

		return [
			'files' => $files,
			'lines' => $lines,
			'unreadable' => $unreadable,
			'from' => $from,
			'to' => $to,
			'refused' => $refused,
			'covered' => $covered,
			'distinct' => count($paths),
			'uncovered' => array_sum($paths),
			'candidates' => array_slice($candidates, 0, self::CANDIDATE_LIMIT, TRUE),
			'below' => array_sum($paths) - array_sum($candidates),
		];

	}

	/**
	 * Le rappel mensuel envoyé sur le salon de sécurité. Il porte des nombres et aucun chemin : un
	 * relevé en poste des centaines d'un coup, là où une alerte de balayage n'en porte qu'un, et
	 * Discord n'est déclaré destinataire dans aucun des deux documents que l'article 13 du RGPD
	 * oblige à tenir (cf. `AccessEventLib::getMessage()`). Les chemins se lisent sur le serveur, par
	 * la commande que le message donne.
	 */
	public static function notify(): bool {

		$review = self::review();

		if($review['lines'] === 0) {
			return FALSE;
		}

		$days = max(1, (int)round((strtotime($review['to']) - strtotime($review['from'])) / 86400));

		$message = '🧹 Revue des chemins refusés avant PHP. Sur '.$days.' jours de journal, '
			.$review['refused'].' requêtes ont été refusées et '.$review['covered'].' autres le seraient par un motif déjà écrit. '
			.$review['uncovered'].' réponses 4xx ne tombent sous aucun motif, sur '.$review['distinct'].' chemins distincts, dont '
			.count($review['candidates']).' vus au moins '.self::CANDIDATE_THRESHOLD.' fois. '
			.'Pour les lire et compléter '.self::PATH.', lancer sur le serveur php framework/lime.php -a ouvretaferme -e prod security/configure/scanReviewAi';

		return \communication\DiscordWebhookLib::send($message, [], \communication\DiscordWebhookLib::SECURITY);

	}

}
?>
