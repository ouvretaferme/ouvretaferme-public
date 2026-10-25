<?php
namespace main;

class CsvLib {

	public static function upload(string $key, \Closure $callback): bool {

		if(isset($_FILES['csv']) === FALSE) {
			return FALSE;
		}

		$file = $_FILES['csv']['tmp_name'];

		if(empty($file)) {
			return FALSE;
		}

		// Vérification de la taille (max 1 Mo)
		if(filesize($file) > 1024 * 1024) {
			\Fail::log('csvSize');
			return FALSE;
		}

		$content = file_get_contents($file);

		$encoding = mb_detect_encoding($content, ['UTF-8', 'UTF-16', 'ISO-8859-1']);
		$content = ltrim($content, "\xEF\xBB\xBF");

		if(in_array($encoding, ['UTF-16', 'ISO-8859-1'])) {
			$content = iconv($encoding, 'UTF-8', $content);
		}

		$content = trim($content);

		file_put_contents($file, $content);

		$delimiter = self::detectDelimiter($file);
		$csv = \util\CsvLib::parseCsv($file, $delimiter);

		if($csv === []) {
			\Fail::log('main\csvSource');
			return FALSE;
		}

		$output = $callback($csv);

		if($output === NULL) {
			return FALSE;
		}

		\Cache::redis()->set($key, $output);

		return TRUE;

	}

	public static function detectDelimiter($csvFile) {

		$delimiters = [";" => 0, "," => 0, "\t" => 0, "|" => 0];

		$handle = fopen($csvFile, "r");
		$firstLine = fgets($handle);
		fclose($handle);
		foreach($delimiters as $delimiter => &$count) {
		  $count = count(str_getcsv($firstLine, $delimiter, escape: ''));
		}
		return array_search(max($delimiters), $delimiters);

	}

	public static function checkDateField(mixed &$value, string $error): ?string {

		if(
			$value !== NULL and
			\Filter::check('date', $value) === FALSE
		) {
			return $error;
		} else {
			return NULL;
		}

	}

	public static function formatFloat(mixed $value, int $precision = 2): ?float {

		return round((float)str_replace(',', '.', $value), $precision);

	}

	/**
	 * Une date du jour au format de la base, quel que soit le séparateur que le tableur a produit.
	 *
	 * Les trois séparateurs se rencontrent dans les fichiers réels, et la même colonne peut passer
	 * de l'un à l'autre d'un export à l'autre ; le zéro de tête manque dès que la date a été tapée
	 * à la main. Le motif exige en revanche le même séparateur des deux côtés.
	 *
	 * Une valeur non reconnue ressort telle quelle, y compris AAAA-MM-JJ qui est déjà au bon
	 * format : c'est build() qui tranche ensuite, et son refus nomme la colonne. Passer par
	 * strtotime() datait au contraire du 1er janvier 1970 tout ce qu'il ne savait pas lire, sans
	 * rien signaler.
	 */
	public static function formatDateField(mixed $value): ?string {

		if(
			$value !== NULL and
			preg_match('/^([0-9]{1,2})([\/.-])([0-9]{1,2})\2([0-9]{4})$/', $value, $results)
		) {
			return sprintf('%s-%02d-%02d', $results[4], $results[3], $results[1]);
		} else {
			return $value;
		}

	}

}
?>
