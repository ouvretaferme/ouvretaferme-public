<?php
// Un test fige cette horloge et le NOW() de chaque connexion MySQL d'un même geste (set()) : une heure
// lue ailleurs, par time(), date(), new DateTime() ou SYSDATE(), y échappe et dépend du jour du test.
class Clock {

	const FORMAT = '/^[0-9]{4}-[0-9]{2}-[0-9]{2}( [0-9]{2}:[0-9]{2}:[0-9]{2})?$/';

	private static ?int $timestamp = NULL;

	public static function timestamp(): int {
		return self::$timestamp ?? self::machine();
	}

	// Never set by a test: a loop bounded by the hour, a TTL or a timestamp checked by another server keeps the real time
	public static function machine(): int {
		return time();
	}

	public static function datetime(): string {
		return self::format('Y-m-d H:i:s');
	}

	public static function date(): string {
		return self::format('Y-m-d');
	}

	public static function timeOfDay(): string {
		return self::format('H:i:s');
	}

	public static function week(): string {
		return self::format('o-\WW');
	}

	public static function month(): string {
		return self::format('Y-m');
	}

	public static function year(): int {
		return (int)self::format('Y');
	}

	public static function format(string $pattern): string {
		return date($pattern, self::timestamp());
	}

	public static function shift(string $modifier): int {

		$timestamp = strtotime($modifier, self::timestamp());

		if($timestamp === FALSE) {
			throw new Exception('Invalid modifier \''.$modifier.'\'');
		}

		return $timestamp;

	}

	// PHP method names ignore case, so dateTime() would redeclare datetime()
	public static function now(): DateTime {
		return new DateTime('@'.self::timestamp())->setTimezone(new DateTimeZone(date_default_timezone_get()));
	}

	public static function isSet(): bool {
		return (self::$timestamp !== NULL);
	}

	public static function set(string $datetime): void {

		// Route::getRequestedWith() alone is not enough: in dev, an HTTP request chooses it with ?limeAccess=cli
		if(LIME_ENV !== 'dev' or PHP_SAPI !== 'cli' or Route::getRequestedWith() !== 'cli') {
			throw new Exception('Clock can only be set by a test');
		}

		if(preg_match(self::FORMAT, $datetime) === 0) {
			throw new Exception('Invalid datetime \''.$datetime.'\'');
		}

		$timestamp = strtotime($datetime);

		// MySQL reads 0 as DEFAULT, and strtotime() moves 02-30, 24:00:00 or an hour skipped by daylight saving to another date
		if($timestamp === FALSE or $timestamp <= 0 or date(strlen($datetime) === 10 ? 'Y-m-d' : 'Y-m-d H:i:s', $timestamp) !== $datetime) {
			throw new Exception('Invalid datetime \''.$datetime.'\'');
		}

		self::$timestamp = $timestamp;

		ModuleModel::setTimestamp($timestamp);

	}

	public static function reset(): void {

		if(self::$timestamp === NULL) {
			return;
		}

		ModuleModel::setTimestamp(NULL);

		self::$timestamp = NULL;

	}

}
?>
