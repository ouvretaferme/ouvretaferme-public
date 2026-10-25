<?php
namespace entry;

/**
 * Le jour dit dans la phrase : « hier », « avant-hier », « lundi », « le 12 », « le 12 octobre »,
 * « 12/10 ». Sans date dite, la saisie porte sur aujourd'hui (`EntryLib::getEmptyReading()`).
 *
 * Le vocabulaire est fermé : les mots ci-dessous, et rien d'autre. Un jour de semaine nu désigne
 * le dernier jour de ce nom strictement passé : « vendredi » dit un vendredi est celui d'avant. Une
 * date sans année est la dernière qui n'est pas à venir. Le complément lit aussi le futur (« demain »,
 * « lundi prochain ») : c'est l'intention qui dit si elle l'accepte (`IntentionLib::getDateRefusal()`).
 *
 * Sa valeur est la date (`Y-m-d`), `INCOMPLETE` quand la date se tape encore et ne dit pas encore un
 * jour (« le », « 12 ma »), `INVALID` quand le jour n'existe pas (« 31/02 »). Quand la phrase en dit
 * plusieurs, la dernière l'emporte : un jeton de date posé par l'écran vient en tête, et ce qu'on tape
 * ensuite le remplace. Pour une intention qui montre un récapitulatif (`IntentionLib::hasRecap()`), une
 * date qui se tape encore après un jour dit vaut `[INCOMPLETE, <ce jour>]` : la lecture reste sur ce jour
 * tant que l'autre ne l'a pas remplacé, et ne s'écrit pas pour autant hors du récapitulatif.
 */
class DateComplementLib extends ComplementLib {

	const string INCOMPLETE = 'incomplete';
	const string INVALID = 'invalid';

	/**
	 * Les mots qui disent un jour à eux seuls, par écart avec aujourd'hui.
	 */
	const array DAYS = [
		'aujourd\'hui' => 0,
		'aujourdhui' => 0,
		'hier' => -1,
		'avant-hier' => -2,
		'demain' => 1,
	];

	const array WEEKDAYS = [
		'lundi' => 1,
		'mardi' => 2,
		'mercredi' => 3,
		'jeudi' => 4,
		'vendredi' => 5,
		'samedi' => 6,
		'dimanche' => 7,
	];

	const array LAST = ['dernier', 'derniere'];
	const array NEXT = ['prochain', 'prochaine'];

	const array MONTHS = [
		'janvier' => 1,
		'fevrier' => 2,
		'mars' => 3,
		'avril' => 4,
		'mai' => 5,
		'juin' => 6,
		'juillet' => 7,
		'aout' => 8,
		'septembre' => 9,
		'octobre' => 10,
		'novembre' => 11,
		'decembre' => 12,
	];

	public static function getIntentions(): array {
		return ['harvest', 'time', 'done', 'intervention'];
	}

	public static function read(array &$words, mixed &$value, ?int $typing, string $intention): void {

		$positions = array_keys($words);
		$guess = (EntryLib::getIntentions()[$intention]::readsRest() === FALSE);
		$retains = EntryLib::getIntentions()[$intention]::hasRecap();

		for($index = 0; $index < count($positions); $index++) {

			$found = self::readAt($words, array_slice($positions, $index), $typing, $guess);

			if($found === NULL) {
				continue;
			}

			[$claimed, $date] = $found;

			$said = self::getSaid($value);
			$value = ($retains and $date === self::INCOMPLETE and $said !== NULL) ? [self::INCOMPLETE, $said] : $date;

			foreach($claimed as $position) {
				unset($words[$position]);
			}

			$index += count($claimed) - 1;

		}

	}

	/**
	 * La date qui commence au premier mot de `$positions`, avec les positions qu'elle occupe, ou `NULL`
	 * si aucune n'y commence. `$guess` laisse un début de mot valoir le jour qu'il commence, pour une
	 * intention dont aucun mot ne peut commencer comme lui (`IntentionLib::readsRest()`), et un nombre
	 * suivi d'un début de mot valoir une date. Après « le », le mois se devine toujours : le jour est
	 * déjà dit.
	 */
	private static function readAt(array $words, array $positions, ?int $typing, bool $guess): ?array {

		$word = fn(int $offset) => isset($positions[$offset]) ? $words[$positions[$offset]] : NULL;
		$isTyping = fn(int $offset) => (isset($positions[$offset]) and $positions[$offset] === $typing);
		$claim = fn(int $length, string $value) => [array_slice($positions, 0, $length), $value];

		$first = $word(0);

		// « 12/10 », et « 12/ » le temps de taper le mois.
		if(preg_match('/^([0-9]{1,2})\/([0-9]{0,2})$/', $first, $match) === 1) {

			if($match[2] === '') {
				return $isTyping(0) ? $claim(1, self::INCOMPLETE) : NULL;
			}

			return $claim(1, self::getDayMonth((int)$match[1], (int)$match[2]));

		}

		// « le 12 », « le 1er », « le 12 octobre ». Un « le » en fin de phrase attend son nombre.
		if($first === 'le') {

			if($word(1) === NULL) {
				return $claim(1, self::INCOMPLETE);
			}

			if(preg_match('/^[0-9]{1,2}$/', $word(1)) === 0) {
				return NULL;
			}

			$day = (int)$word(1);
			$length = 2 + self::countFirst($day, $word(2), $isTyping(2));

			$month = self::readMonth($word($length), $isTyping($length));

			return match($month) {
				NULL => $claim($length, self::getDay($day)),
				0 => $claim($length + 1, self::INCOMPLETE),
				default => $claim($length + 1, self::getDayMonth($day, $month)),
			};

		}

		// « 12 octobre » : un nombre n'est une date que suivi d'un mois, sinon il reste à la quantité. Sans
		// `$guess`, le mois ne se devine pas sur son début : « radis 5 m » est la quantité du radis suivie
		// du melon qui s'écrit, pas le 5 mai.
		if(preg_match('/^[0-9]{1,2}$/', $first) === 1) {

			$day = (int)$first;
			$length = 1 + self::countFirst($day, $word(1), $isTyping(1));

			$month = self::readMonth($word($length), ($guess and $isTyping($length)));

			return match($month) {
				NULL => NULL,
				0 => $claim($length + 1, self::INCOMPLETE),
				default => $claim($length + 1, self::getDayMonth($day, $month)),
			};

		}

		if(array_key_exists($first, self::DAYS)) {
			return $claim(1, self::getShift(self::DAYS[$first]));
		}

		// « avant hier » en deux mots.
		if($first === 'avant' and $word(1) !== NULL and ($word(1) === 'hier' or ($isTyping(1) and str_starts_with('hier', $word(1))))) {
			return $claim(2, self::getShift(-2));
		}

		if(array_key_exists($first, self::WEEKDAYS)) {

			$next = $word(1);

			if($next !== NULL and self::startsOne(self::NEXT, $next, $isTyping(1))) {
				return $claim(2, self::getWeekday(self::WEEKDAYS[$first], TRUE));
			}

			$length = ($next !== NULL and self::startsOne(self::LAST, $next, $isTyping(1))) ? 2 : 1;

			return $claim($length, self::getWeekday(self::WEEKDAYS[$first], FALSE));

		}

		// Un début de mot en cours de frappe : il vaut le jour qu'il commence quand il n'en commence qu'un.
		if($guess and $isTyping(0)) {

			$dates = [];

			foreach(self::DAYS + ['avant' => -2] as $dayWord => $shift) {
				if(str_starts_with($dayWord, $first)) {
					$dates[self::getShift($shift)] = TRUE;
				}
			}

			foreach(self::WEEKDAYS as $weekdayWord => $weekday) {
				if(str_starts_with($weekdayWord, $first)) {
					$dates[self::getWeekday($weekday, FALSE)] = TRUE;
				}
			}

			if($dates !== []) {
				return $claim(1, (count($dates) === 1) ? array_key_first($dates) : self::INCOMPLETE);
			}

		}

		return NULL;

	}

	/**
	 * « 1er » sort de `EntryLib::split()` en « 1 er » : le suffixe suit le seul premier du mois.
	 */
	private static function countFirst(int $day, ?string $word, bool $typing): int {

		return ($day === 1 and $word !== NULL and ($word === 'er' or ($typing and str_starts_with('er', $word)))) ? 1 : 0;

	}

	/**
	 * Le mois que dit un mot : son numéro, `0` pour un début de mot en cours de frappe qui en commence
	 * plusieurs, `NULL` si ce n'est pas un mois.
	 */
	private static function readMonth(?string $word, bool $typing): ?int {

		if($word === NULL) {
			return NULL;
		}

		if(array_key_exists($word, self::MONTHS)) {
			return self::MONTHS[$word];
		}

		if($typing === FALSE) {
			return NULL;
		}

		$months = array_filter(self::MONTHS, fn(int $month, string $name) => str_starts_with($name, $word), ARRAY_FILTER_USE_BOTH);

		return match(count($months)) {
			0 => NULL,
			1 => $months[array_key_first($months)],
			default => 0,
		};

	}

	private static function startsOne(array $candidates, string $word, bool $typing): bool {

		foreach($candidates as $candidate) {
			if($word === $candidate or ($typing and str_starts_with($candidate, $word))) {
				return TRUE;
			}
		}

		return FALSE;

	}

	private static function getShift(int $days): string {
		return date('Y-m-d', \Clock::shift($days.' DAYS'));
	}

	/**
	 * Le dernier jour de ce nom strictement passé, ou le prochain strictement à venir.
	 */
	private static function getWeekday(int $weekday, bool $next): string {

		$today = (int)\Clock::format('N');

		if($next) {
			return self::getShift((($weekday - $today + 6) % 7) + 1);
		} else {
			return self::getShift(-((($today - $weekday + 6) % 7) + 1));
		}

	}

	/**
	 * Le dernier jour de ce numéro qui n'est pas à venir : ce mois-ci, ou le mois d'avant.
	 */
	private static function getDay(int $day): string {

		[$year, $month, $today] = array_map('intval', explode('-', \Clock::date()));

		if($day > $today) {
			$month--;
			if($month === 0) {
				$month = 12;
				$year--;
			}
		}

		return self::getDate($year, $month, $day);

	}

	/**
	 * La dernière date de ce jour et de ce mois qui n'est pas à venir : cette année, ou l'an passé.
	 */
	private static function getDayMonth(int $day, int $month): string {

		$year = \Clock::year();

		if(sprintf('%02d-%02d', $month, $day) > substr(\Clock::date(), 5)) {
			$year--;
		}

		return self::getDate($year, $month, $day);

	}

	private static function getDate(int $year, int $month, int $day): string {

		if(checkdate($month, $day, $year) === FALSE) {
			return self::INVALID;
		}

		return sprintf('%04d-%02d-%02d', $year, $month, $day);

	}

	public static function isDate(mixed $value): bool {
		return (is_string($value) and $value !== self::INCOMPLETE and $value !== self::INVALID);
	}

	/**
	 * Le jour que la valeur dit déjà, y compris celui qu'une date en cours de frappe n'a pas encore remplacé.
	 */
	private static function getSaid(mixed $value): ?string {

		if(is_array($value)) {
			return $value[1];
		} else if(self::isDate($value)) {
			return $value;
		} else {
			return NULL;
		}

	}

	/**
	 * Le jour dit devient celui de la lecture. Un jour qui n'existe pas est refusé ici : rien de ce qui
	 * suit ne saurait sur quelle date travailler.
	 */
	public static function apply(array &$reading, mixed $value): void {

		if($value === self::INVALID) {
			EntryLib::refuse($reading, ['reason' => 'date']);
		} else if(self::getSaid($value) !== NULL) {
			$reading['date'] = self::getSaid($value);
		}

	}

	/**
	 * Une date qui se tape encore n'est pas un jour : la phrase ne s'écrit pas sur aujourd'hui à sa place.
	 */
	public static function isComplete(mixed $value): bool {
		return ($value !== self::INCOMPLETE and is_array($value) === FALSE);
	}

}
?>
