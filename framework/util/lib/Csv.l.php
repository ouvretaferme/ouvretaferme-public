<?php
namespace util;

/**
 * Handle CSV files
 */
class CsvLib {

	/**
	 * Transform multiple rows of data into CSV.
	 * Example: array[[1,2,3],array[4,5,6],array[7,8,9]]
	 *
	 * @param array $lines The different lines, in an array of arrays
	 * @return string The CSV formated data
	 */
	public static function toCsv(array $lines = [], string $separator = ';'): string {

		$stream = @fopen('php://temp', 'r+');

		foreach($lines as $line) {
			fputcsv($stream, self::neutralizeFormulas($line), $separator, escape: '');
		}

		rewind($stream);

		$csv = stream_get_contents($stream);

		fclose($stream);

		return $csv;
	}

	/**
	 * Excel and LibreOffice run a cell that starts with =, +, -, @, a tab or a carriage return as a formula
	 * when the file is opened: such a cell is prefixed with an apostrophe, a plain number is left alone
	 */
	public static function neutralizeFormulas(array $line): array {

		foreach($line as $key => $cell) {

			if(
				is_string($cell) and
				preg_match('/^[=+\-@\t\r]/', $cell) === 1 and
				preg_match('/^[+-]?[0-9]+([.,][0-9]+)?$/', $cell) === 0
			) {
				$line[$key] = "'".$cell;
			}

		}

		return $line;

	}

	/**
	 * Convert CSV file into an array
	 *
	 * @param string $path Path to CSV file
	 * @param string $separator CSV separator character
	 * @return array
	 */
	public static function parseCsv(string $path, string $separator = ';'): array {
	    
		$stream = fopen($path, 'r');

		if($stream === FALSE) {
			return [];
		}

		$lines = [];

		while(($line = fgetcsv($stream, 0, $separator, '"', escape: '')) !== FALSE) {
			$lines[] = $line;
		}

		fclose($stream);

		return $lines;
	}

}

?>
