<?php


/**
 * Filter : Variable manager
 */
class Filter {

	/**
	 * Max value of a 8-bits signed int
	 *
	 */
	const MAX_INT8 = "127";

	/**
	 * Max value of a 16-bits signed int
	 *
	 */
	const MAX_INT16 = "32767";

	/**
	 * Max value of a 16-bits signed int
	 *
	 */
	const MAX_INT24 = "8388607";

	/**
	 * Max value of a 32-bits signed int
	 *
	 */
	const MAX_INT32 = "2147483647";

	/**
	 * Max value of a 64-bits signed int
	 *
	 */
	const MAX_INT64 = "9223372036854775807";

	/**
	 * Min value of a 8-bits signed int
	 *
	 */
	const MIN_INT8 = "-128";

	/**
	 * Min value of a 16-bits signed int
	 *
	 */
	const MIN_INT16 = "-32768";

	/**
	 * Min value of a 24-bits signed int
	 *
	 */
	const MIN_INT24 = "-8388608";

	/**
	 * Min value of a 32-bits signed int
	 *
	 */
	const MIN_INT32 = "-2147483648";

	/**
	 * Min value of a 64-bits signed int
	 *
	 */
	const MIN_INT64 = "-9223372036854775807";

	/**
	 * Max value of a 8-bits unsigned int
	 *
	 */
	const MAX_INT8_UNSIGNED = "255";

	/**
	 * Max value of a 16-bits unsigned int
	 *
	 */
	const MAX_INT16_UNSIGNED = "65535";

	/**
	 * Max value of a 24-bits unsigned int
	 *
	 */
	const MAX_INT24_UNSIGNED = "16777215";

	/**
	 * Max value of a 32-bits unsigned int
	 *
	 */
	const MAX_INT32_UNSIGNED = "4294967295";

	/**
	 * Max value of a 64-bits unsigned int
	 *
	 */
	const MAX_INT64_UNSIGNED = "18446744073709551615";

	/**
	 * Max value of a 32-bits signed float
	 *
	 */
	const MAX_FLOAT32 = "999999";

	/**
	 * Max value of a 64-bits signed float
	 *
	 */
	const MAX_FLOAT64 = "999999999999999";

	/**
	 * Max value of a 32-bits signed float
	 *
	 */
	const MIN_FLOAT32 = "-999999";

	/**
	 * Max value of a 64-bits signed float
	 *
	 */
	const MIN_FLOAT64 = "-999999999999999";

	/**
	 * Max length for TEXT8 fields
	 *
	 */
	const MAX_TEXT8_SIZE = 255;

	/**
	 * Max length for TEXT16 fields
	 *
	 */
	const MAX_TEXT16_SIZE = 32767;

	/**
	 * Fully qualified name max size
	 */
	const MAX_FQN_SIZE = 255;

	/**
	 * Fully qualified name min size
	 */
	const MIN_FQN_SIZE = 1;

	/**
	 * Maximum default length for STRING values
	 */
	const MAX_STRING_SIZE = 255;

	/**
	 * Un nombre décimal écrit en chiffres : « .5 », qu'un champ `number` poste tel quel, et « 6. »,
	 * qu'un client de l'API peut envoyer, en sont ; ni la notation scientifique ni une espace n'en sont.
	 */
	const DECIMAL_PATTERN = '/^-?(\d+\.?\d*|\.\d+)\z/';

	/**
	 * Les espaces qui séparent les milliers : l'espace, l'insécable et l'insécable fine, que pose
	 * l'écriture française d'un nombre (`NumberFormatter`, un tableur, un copier-coller).
	 */
	const THOUSANDS_SEPARATORS = ' \x{A0}\x{202F}';

	/**
	 *  Flag to accept NULL value
	 *
	 */
	const NULL = 1;

	/**
	 *  Flag to specify that it is a unique value
	 *
	 */
	const UNIQUE = 2;

	/**
	 * Get the range of a string, float or integer structure
	 *
	 * @param array $name A name of structure
	 */
	public static function getRange(array $mask): ?array {

		$type = $mask[0];

		if(
			strpos($type, 'int') === 0 or
			strpos($type, 'float') === 0 or
			$type === 'decimal'
		) {

			$start = $mask['min'] ?? NULL;
			$stop = $mask['max'] ?? NULL;

			if($start === NULL or $stop === NULL) {

				$bits = (int)substr($type, -1);

				switch($bits) {

					case 8 :
						$start ??= (int)self::MIN_INT8;
						$stop ??= ($start < 0 ? (int)self::MAX_INT8 : (int)self::MAX_INT8_UNSIGNED);
						break;

					case /*1*/6 :
						$start ??= (int)self::MIN_INT16;
						$stop ??= ($start < 0 ? (int)self::MAX_INT16 : (int)self::MAX_INT16_UNSIGNED);
						break;

					case /*6*/4 :
						$start ??= (int)self::MIN_INT64;
						$stop ??= ($start < 0 ? (int)self::MAX_INT64 : (int)self::MAX_INT64_UNSIGNED);
						break;

					case /*3*/2 :
					default :
						$start ??= (int)self::MIN_INT32;
						$stop ??= ($start < 0 ? (int)self::MAX_INT32 : (int)self::MAX_INT32_UNSIGNED);
						break;

				}

			}

			return [$start, $stop];

		} else if(
			strpos($type, 'serial') === 0 or
			strpos($type, 'text') === 0 or
			strpos($type, 'editor') === 0 or
			strpos($type, 'binary') === 0
		) {

			$start = $mask['min'] ?? NULL;
			$stop = $mask['max'] ?? NULL;

			if($start === NULL or $stop === NULL) {

				$bits = (int)substr($type, -1);

				switch($bits) {

					case 8 :
						$start ??= 0;
						$stop ??= (int)self::MAX_INT8_UNSIGNED;
						break;

					case /*1*/6 :
						$start ??= 0;
						$stop ??= (int)self::MAX_INT16_UNSIGNED;
						break;

					case /*6*/4 :
						$start ??= 0;
						$stop ??= (int)self::MAX_INT64; // PHP does not support unsigned 64bits numbers
						break;

					case /*3*/2 :
					default :
						$start ??= 0;
						$stop ??= (int)self::MAX_INT32_UNSIGNED;
						break;

				}

			}

			return [$start, $stop];

		} else if(
			$type === 'char'
		) {

			$start = $mask['min'] ?? 0;
			$stop = $mask['max'] ?? self::MAX_INT8_UNSIGNED;

			return [$start, $stop];

		} else if($type === 'date' or $type === 'datetime' or $type === 'week' or $type === 'month' or $type === 'year') {

			$start = $mask['min'] ?? NULL;
			$stop = $mask['max'] ?? NULL;

			if($start !== NULL or $stop !== NULL) {

				switch($type) {
					case 'date' :
						$format = 'Y-m-d';
						break;
					case 'datetime' :
						$format = 'Y-m-d H:i:s';
						break;
					case 'week' :
						$format = 'o-\WW';
						break;
					case 'month' :
						$format = 'Y-m';
						break;
					case 'year' :
						$format = 'Y';
						break;
				}

				if($start !== NULL) {
					$date = new DateTime($start);
					$start = $date->format($format);
				}

				if($stop !== NULL) {
					$date = new DateTime($stop);
					$stop = $date->format($format);
				}

				return [$start, $stop];

			}

		} else if($type === 'collection') {
			return [0, (int)$mask[2]];
		} else if($type === 'set') {
			return $mask[1];
		}

		return NULL;

	}

	/**
	 * Check a variable with a Filter structure
	 *
	 * <code>
	 *
	 * $value = $_GET['id'];
	 *
	 * if(Filter::check("int8", $value)) {
	 * 	echo "No SQL injection!";
	 * }
	 * </code>
	 *
	 */
	public static function check(string|array $mask, mixed $value): bool {

		if(is_string($mask)) {

			$type = $mask;
			$isNull = FALSE;

			$mask = [
				$type
			];

		} else {
			$type = $mask[0];
			$isNull = $mask['null'] ?? FALSE;
		}

		if(str_starts_with($type, '?')) {
			$type = substr($type, 1);
			$isNull = TRUE;
		}

		if(
			$value !== NULL and
			isset($mask['charset']) and
			mb_check_encoding($value, $mask['charset']) === FALSE
		) {
			return FALSE;
		}

		// NULL values
		if($isNull) {

			if(strpos($type, 'element') === 0) {

				if(
					$value === NULL or
					($value instanceof Element and $value->exists() === FALSE)
				) {
					return TRUE;
				}

			} else {

				if($value === NULL) {
					return TRUE;
				}

			}

		} else {
			if($value === NULL) {
				return FALSE;
			}
		}

		if($type === 'bool') {
			return TRUE;
		} else if(
			str_starts_with($type, 'int') or
			str_starts_with($type, 'serial') or
			str_starts_with($type, 'float') or
			$type === 'decimal' or
			str_starts_with($type, 'text') or
			str_starts_with($type, 'editor') or
			str_starts_with($type, 'json') or
			str_starts_with($type, 'binary') or
			$type === 'char'
		) {

			if(is_array($value) === TRUE) {
				if(strpos($type, 'json') === 0) { // test if the value is a json string
					json_decode(json_encode($value)); // from array to string then from string to array

					if(json_last_error() !== JSON_ERROR_NONE) {
						return FALSE;
					}
				} else {
					return FALSE;
				}
			}
			else {
				if(is_scalar($value) === FALSE or is_bool($value)) {
					return FALSE;
				}

				$scalar = $value;
				$value = (string)$value;

				if(str_starts_with($type, 'int')) {
					if(!preg_match("/^\-?\d+\z/", $value)) {
						return FALSE;
					}
				} else if(str_starts_with($type, 'serial')) {
					if(ctype_digit($value) === FALSE) {
						return FALSE;
					}
				} else if(str_starts_with($type, 'float') or $type === 'decimal') {
					// PHP écrit un float en notation scientifique sous 1e-4 ((string)0.00003 vaut "3.0E-5") :
					// un float se juge sur sa valeur, seule une chaîne passe par l'expression
					if(is_float($scalar)) {
						if(is_finite($scalar) === FALSE) {
							return FALSE;
						}
					} else if(preg_match(self::DECIMAL_PATTERN, $value) !== 1) {
						return FALSE;
					}
					// Un masque écrit en chaîne ('decimal') ne dit pas son échelle : seul celui d'une colonne la porte
					if(isset($mask['decimal']) and self::exceedsScale($mask['decimal'], $scalar)) {
						return FALSE;
					}
				}
			}

			$range = self::getRange($mask);

			if($range) {

				$start = $range[0];
				$stop = $range[1];

				if(str_starts_with($type, 'int') or str_starts_with($type, 'serial')) {
					$size = (int)$value;
				} else if(str_starts_with($type, 'float') or $type === 'decimal') {
					$size = (float)$value;
				} else if(str_starts_with($type, 'binary')) {
					$size = strlen($value);
				} else {
					$size = mb_strlen($value);
				}

				if(is_null($start) === FALSE and $size < $start) {
					return FALSE;
				}
				if(is_null($stop) === FALSE and $size > $stop) {
					return FALSE;
				}

			}

			return TRUE;

		} else if(strpos($type, 'element') === 0) {

			if($value instanceof Element === FALSE) {
				return FALSE;
			}

			$id = $value['id'] ?? NULL;

			if($id === NULL) {
				return FALSE;
			}

			if(
				is_scalar($id) === FALSE or ((string)(int)$id) !== (string)$id or
				bccomp((string)$id, self::getOverflowMin($type)) !== 1 or // Smaller than zero
				bccomp((string)$id, self::getOverflow($type)) !== -1 // Greater than max allowed value
			) {
				return FALSE;
			}

			return TRUE;

		} else if($type === 'enum') {

			if(in_array($value, $mask[1], TRUE)) {
				return TRUE;
			} else {
				return FALSE;
			}

		} else if($type === 'set') {

			if($value instanceof Set) {
				$value = $value->get();
			}

			// Chaque valeur d'un set est un bit, donc la valeur maximale légale est la somme des bits,
			// toutes cochées : un set de 1, 2 et 4 vaut au plus 7. Une borne tirée du plus grand bit b,
			// 2 ** (b - 1), refuse ce cas sur un set de deux valeurs (3 contre 2), et au delà de trois
			// valeurs laisse passer des bits hors du set.
			$max = array_sum(self::getRange($mask));

			if(is_scalar($value) and preg_match("/^[0-9]+\z/", $value) and $value <= $max) {
				return TRUE;
			} else {
				return FALSE;
			}

		} else if($type === 'collection') {

			$listType = $mask[1];
			$range = self::getRange($mask);

			if($value instanceof Collection === FALSE) {
				return FALSE;
			} else if(count($value) > $range[1]) {
				return FALSE;
			} else {

				switch($listType) {

					case 'serial8' :
						$min = 0;
						$max = (int)self::MAX_INT8_UNSIGNED;
						break;
					case 'serial16' :
						$min = 0;
						$max = (int)self::MAX_INT16_UNSIGNED;
						break;
					case 'serial32' :
						$min = 0;
						$max = (int)self::MAX_INT32_UNSIGNED;
						break;
					case 'serial64' :
						$min = 0;
						$max = (int)self::MAX_INT64; // PHP does not support unsigned 64bits numbers
						break;

				}

				foreach($value as $e) {

					if(is_int($e['id'])) {

						if($e['id'] < $min or $e['id'] > $max) {
							return FALSE;
						}

					} else if($e['id'] !== NULL) {
						return FALSE;
					}

				}
			}

			return TRUE;

		} else if($type === 'point') {

			// Un point arrive décodé d'un JSON posté : un tableau imbriqué, des clés nommées ou un booléen
			// y sont aussi possibles qu'un nombre, et preg_match() lèverait ou lirait TRUE comme « 1 ».
			if(is_array($value) === FALSE or array_is_list($value) === FALSE or count($value) !== 2) {
				return FALSE;
			}

			foreach($value as $coordinate) {

				if(
					(is_int($coordinate) or is_float($coordinate) or is_string($coordinate)) === FALSE or
					preg_match("/^\-?\d+(\.\d+)?\z/", (string)$coordinate) !== 1
				) {
					return FALSE;
				}

			}

			return TRUE;

		} else if($type === 'polygon') {

			if(is_array($value) === FALSE) {
				return FALSE;
			}

			foreach($value as $point) {

				if(self::check('point', $point) === FALSE) {
					return FALSE;
				}

			}

			return TRUE;

		} else {

			$date = '[0-9]{4}\\-(0[1-9]|1[0-2])\\-(0[1-9]|[12][0-9]|3[0-1])';
			$hours = '([01][0-9]|2[0-3])';
			$minutes = '[0-5][0-9]';
			$seconds = '[0-5][0-9]';
			$time = $hours.'\\:'.$minutes.'\\:'.$seconds;

			$pcreDate = ':^'.$date.'\z:s';
			$pcreDateTime = ':^('.$date.' '.$time.'|'.$date.'T'.$hours.'\\:'.$minutes.')\z:s';
			$pcreTime = ':^('.$time.'|'.$hours.'\\:'.$minutes.')\z:s';

			switch($type) {
				case 'date' :
					$pcre = $pcreDate;
					break;
				case 'datetime' :
					$pcre = $pcreDateTime;
					break;
				case 'year' :
					$pcre = ':^[0-9]{4}\z:s';
					break;
				case 'month' :
					$pcre = ':^[0-9]{4}\\-(0[1-9]|1[0-2])\z:s';
					break;
				case 'week' :
					$pcre = ':^[0-9]{4}\\-W(0[1-9]|[1-4][0-9]|50|51|52|53)\z:s';
					break;
				case 'time' :
					$pcre = $pcreTime;
					break;
				case 'email' :
					$domains = '[a-z]{2,20}';
					$pcre = '#^\w[-\+.\w]*@[-a-z0-9]+(?:\.[-a-z0-9]+)*\.(?:'.$domains.')\z#si';
					break;
				case 'url' :
					$pcre = ':^(https?|ftps?)\\://[0-9a-z\\-\\.]{1,'.self::MAX_TEXT16_SIZE.'}(/|\z):si';
					break;
				case 'fqn' :
					$pcre = ':^[a-z0-9\\-\\.]{'.self::MIN_FQN_SIZE.','.self::MAX_FQN_SIZE.'}\z:i';
					break;
				case 'ipv4' :
					$pcre = '/^[1-2]?[0-9]{1,2}(\.[1-2]?[0-9]{1,2}){3}\z/i';
					break;
				case 'ipv6' :
					$pcre = '/^[a-f0-9]{4}(:[a-f0-9]{4}){7}\z/i';
					break;
				case 'ip' :
					$pcre = '/^[1-2]?[0-9]{1,2}(\.[1-2]?[0-9]{1,2}){3}\z|^[a-f0-9]{4}(:[a-f0-9]{4}){7}\z/i';
					break;
				case 'md5' :
					$pcre = '/^[a-f0-9]{32}\z/i';
					break;
				case 'sid' :
					$pcre = '/^[a-z0-9-\,]{22,40}\z/i';
					break;
				case 'color' :
					$pcre = '/^\#[a-f0-9]{6}\z/i';
					break;
				default :
					$pcre = NULL;
			}

			if($pcre !== NULL) {

				if(is_scalar($value) and !is_bool($value)) {

					$value = (string)$value;

					if((bool)preg_match($pcre, $value) === FALSE) {
						return FALSE;
					}

					if($type === 'date' or $type === 'datetime') {

					 	$date = substr($value, 0, 10);

				 		list($year, $month, $day) = explode('-', $date);

				 		if(!checkdate((int)$month, (int)$day, (int)$year)) {
				 			return FALSE;
				 		}

					}

					if($type === 'week') {

				 		if(strtotime($value) === FALSE) {
				 			return FALSE;
				 		}

					}

					if($type === 'date' or $type === 'datetime' or $type === 'week' or $type === 'month' or $type === 'year') {

				 		$range = self::getRange($mask);

				 		if($range) {

							[$min, $max] = $range;

							if($min !== NULL and strcmp($min, $value) > 0) {
								return FALSE;
							}

							if($max !== NULL and strcmp($max, $value) < 0) {
								return FALSE;
							}

				 		}

					}

					return TRUE;

				}

			}

		}

		return FALSE;

	}

	/**
	 * Une décimale de plus que la colonne n'en garde : MySQL l'arrondirait à l'écriture sans rien dire,
	 * et l'utilisateur relirait un chiffre qu'il n'a pas écrit (« 6.25 » sur une colonne à une
	 * décimale). Un zéro final n'en est pas une.
	 */
	private static function exceedsScale(int $scale, int|float|string $number): bool {

		if(is_int($number)) {
			return FALSE;
		}

		// Un float se juge sur sa valeur. Un calcul y laisse un résidu binaire (0.1 + 0.2 vaut
		// 0.30000000000000004, 1000.10 - 999.99 vaut 0.11000000000001364) que l'arrondi de MySQL
		// efface sans rien perdre. Ce résidu suit la grandeur des opérandes, que la valeur ne dit
		// pas : l'écart toléré est donc fixé six ordres sous la dernière décimale de la colonne, et
		// ne descend jamais sous la précision du float lui-même.
		if(is_float($number)) {
			return (abs(round($number, $scale) - $number) > max(10 ** -($scale + 6), 4 * PHP_FLOAT_EPSILON * abs($number)));
		}

		$dot = strpos($number, '.');

		return (
			$dot !== FALSE and
			strlen(rtrim(substr($number, $dot + 1), '0')) > $scale
		);

	}

	/**
	 * Ramène un nombre écrit à la française à l'écriture que check() et cast() lisent : sur un type
	 * décimal, la virgule devient un point ; sur un type décimal comme sur un entier, les espaces
	 * entre des groupes de trois chiffres disparaissent. Une chaîne qui n'a pas exactement l'une de ces
	 * formes est laissée telle quelle, pour que le contrôle la refuse : « 12 5 » ou « 1,2,3 » sont des
	 * saisies à refaire, pas des nombres mal écrits. Sur un entier la virgule reste, « 6,5 » n'y ayant
	 * aucune lecture juste, et un identifiant (`serial`) ne se normalise pas.
	 *
	 * La virgule vaut un point quelle que soit la langue de qui écrit : « 1,500 » posté par un client
	 * anglophone de l'API se lit 1,5 et non 1 500.
	 */
	public static function normalize(string|array $mask, mixed &$value): void {

		if(is_string($value) === FALSE) {
			return;
		}

		$type = ltrim(is_string($mask) ? $mask : $mask[0], '?');

		$isDecimal = (str_starts_with($type, 'float') or $type === 'decimal');
		$isInteger = str_starts_with($type, 'int');

		if($isDecimal === FALSE and $isInteger === FALSE) {
			return;
		}

		$normalized = trim($value);

		if(preg_match('/^-?\d{1,3}(['.self::THOUSANDS_SEPARATORS.']\d{3})+([.,]\d*)?\z/u', $normalized) === 1) {
			$normalized = preg_replace('/['.self::THOUSANDS_SEPARATORS.']/u', '', $normalized);
		}

		if($isDecimal and preg_match('/^-?(\d+,\d*|,\d+)\z/', $normalized) === 1) {
			$normalized = str_replace(',', '.', $normalized);
		}

		// La valeur rognée est réécrite aussi : le cast d'un entier refuse « 7 » entouré d'espaces
		if($normalized !== $value) {
			$value = $normalized;
		}

	}

	public static function cast(string|array $mask, mixed &$value): void {

		if(is_string($mask)) {
			$type = $mask;
			$isNull = FALSE;
		} else {
			$type = $mask[0];
			$isNull = $mask['null'] ?? FALSE;
		}

		if(str_starts_with($type, '?')) {
			$type = substr($type, 1);
			$isNull = TRUE;
		}

		if(
			$isNull and
			$value === NULL
		) {
			return;
		}

		// check() a reconnu la valeur par comparaison stricte à un membre de la liste : la convertir
		// la ferait sortir d'une liste d'entiers ou de tableaux.
		if($type === 'enum') {
			return;
		}

		if($type === 'bool') {
			$value = (bool)$value;
		} else if(
			str_starts_with($type, 'int') or
			str_starts_with($type, 'serial')
		) {
			$value = (int)$value;
		} else if(
			str_starts_with($type, 'float') or
			$type === 'decimal' or
			$type === 'year'
		) {
			$value = (float)$value;
		} else if(
			str_starts_with($type, 'text') or
			str_starts_with($type, 'editor') or
			str_starts_with($type, 'json') or
			str_starts_with($type, 'binary') or
			$type === 'char' or
			$type === 'date' or
			$type === 'datetime' or
			$type === 'month' or
			$type === 'week' or
			$type === 'time' or
			$type === 'email' or
			$type === 'url' or
			$type === 'fqn' or
			$type === 'ipv4' or
			$type === 'ipv6' or
			$type === 'ip' or
			$type === 'md5' or
			$type === 'sid' or
			$type === 'color'
		) {
			$value = (string)$value;
		} else {
			throw new UnsupportedException();
		}

	}

	/**
	 * Return the max possible value of a type
	 *
	 * @param string $type
	 *
	 * @return int
	 */
	public static function getOverflow(string $type): int {
		switch($type) {
			case 'int8' :
				return self::MAX_INT8;
			case 'element8' :
				return self::MAX_INT8;
			case 'int16' :
				return self::MAX_INT16;
			case 'element16' :
				return self::MAX_INT16;
			case 'int24' :
				return self::MAX_INT24;
			case 'element24' :
				return self::MAX_INT24;
			case 'serial32' :
			case 'int32' :
			case 'int' :
				return self::MAX_INT32;
			case 'element' :
			case 'element32' :
				return self::MAX_INT32;
			case 'int64' :
			case 'serial64' :
				return self::MAX_INT64;
			case 'element64' :
				return self::MAX_INT64;
			case 'float32' :
			case 'float' :
				return self::MAX_FLOAT32;
			case 'float64' :
				return self::MAX_FLOAT64;
		}
	}

	/**
	 * Return the max possible value of a type
	 *
	 * @param string $type
	 *
	 * @return int
	 */
	public static function getOverflowMin(string $type): int {
		switch($type) {
			case 'int8' :
				return self::MIN_INT8;
			case 'element8' :
				return self::MIN_INT8;
			case 'int16' :
				return self::MIN_INT16;
			case 'element16' :
				return self::MIN_INT16;
			case 'int24' :
				return self::MIN_INT24;
			case 'element24' :
				return self::MIN_INT24;
			case 'int32' :
			case 'int' :
				return self::MIN_INT32;
			case 'element' :
			case 'element32' :
				return self::MIN_INT32;
			case 'int64' :
				return self::MIN_INT64;
			case 'element64' :
				return self::MIN_INT64;
			case 'float32' :
			case 'float' :
				return self::MIN_FLOAT32;
			case 'float64' :
				return self::MIN_FLOAT64;
		}
	}

}


class FilterValidator extends Validator {

	public function __construct(
		private array|string $mask
	) {
	}

	public function validate(string $name, mixed &$value): void {

		if(Filter::check($this->mask, $value)) {
			Filter::cast($this->mask, $value);
		} else {
			Fail::log($name.'.check');
		}

	}

}
?>
