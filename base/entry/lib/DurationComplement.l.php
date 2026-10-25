<?php
namespace entry;

/**
 * La durée : le temps de travail de l'utilisateur connecté, dit avec la récolte, « courgette 12 kg 30 min »,
 * ou seul sur une intervention de la semaine, « désherbage carotte 2 h » (`TimeIntentionLib`).
 * Sa valeur est un nombre de minutes : le temps s'écrit en heures (`series\Timesheet::time`), mais une
 * durée tapée se compte en minutes, et un tiers d'heure ne s'écrit pas juste en décimales.
 *
 * Un nombre n'est une durée que suivi d'un mot de durée : `30 min`, `1 h`, `1h30` (que `EntryLib::split()`
 * rend `1`, `h30`), `1 h 30`, `1,5 h`. La quantité, lue après, garde tout nombre nu. Aucune unité de
 * récolte ne commence par `h` ni par `m` (`UnitComplementLib::WORDS`) : un début de mot de durée en cours
 * de frappe ne peut pas être celui d'une unité.
 */
class DurationComplementLib extends ComplementLib {

	const array HOURS = ['h', 'heure', 'heures'];
	const array MINUTES = ['min', 'mn', 'minute', 'minutes'];

	/**
	 * Une journée, en minutes : la borne de `\planning\HarvestWeekLib::checkTimeDay()`, qui la vérifie
	 * encore sur le temps déjà posé ce jour-là.
	 */
	const int DAY = 1440;

	/**
	 * Un nombre en cours de frappe après la quantité, ou seul pour une intention qui ne lit pas de quantité :
	 * le début d'une durée dont le mot n'est pas encore tapé. Il ne s'écrit pas (`isComplete()`), et n'est pas
	 * refusé comme un mot que personne ne lit.
	 */
	const string TYPING = 'typing';

	/**
	 * Des minutes de 60 ou plus après des heures, `1h75` : la phrase ne dit pas une durée.
	 */
	const string OVERFLOW = 'overflow';

	/**
	 * La coche lit la durée pour écrire aussi le temps (lot S4c) : sans verbe, la phrase reste celle du temps
	 * (`DoneIntentionLib::getImplicitPrecedence()`). L'intervention y lit le temps passé ou prévu (HN-B16).
	 */
	public static function getIntentions(): array {
		return ['harvest', 'time', 'done', 'intervention'];
	}

	public static function read(array &$words, mixed &$value, ?int $typing, string $intention): void {

		$positions = array_values(array_keys($words));

		foreach($positions as $index => $position) {

			$number = self::getNumber($words[$position]);

			if($number === NULL) {
				continue;
			}

			$next = $positions[$index + 1] ?? NULL;

			if($next === NULL) {

				// Un second nombre, tapé après la quantité, ou un nombre seul là où aucune quantité ne se lit : la
				// durée qu'il commence attend son mot.
				if($position === $typing and self::startsDuration($words, array_slice($positions, 0, $index), $intention)) {
					$value = self::TYPING;
					unset($words[$position]);
				}

				return;

			}

			if(self::isWord($words[$next], self::MINUTES, $next === $typing)) {

				$value = (int)round($number);
				unset($words[$position], $words[$next]);
				return;

			}

			$minutes = NULL;
			$claimed = [$position, $next];

			if(preg_match('/^h([0-9]{1,2})$/', $words[$next], $match) === 1) {

				$minutes = (int)$match[1];
				$after = $positions[$index + 2] ?? NULL;

				if($after !== NULL and self::isWord($words[$after], self::MINUTES, $after === $typing)) {
					$claimed[] = $after;
				}

			} else if(self::isWord($words[$next], self::HOURS, $next === $typing)) {

				$after = $positions[$index + 2] ?? NULL;
				$unit = $positions[$index + 3] ?? NULL;

				// « 1 h 30 » : les minutes sans leur mot, sauf quand un autre mot les suit, « 1 h 30 kg ».
				if(
					$after !== NULL and
					preg_match('/^[0-9]{1,2}$/', $words[$after]) === 1 and
					($unit === NULL or self::isWord($words[$unit], self::MINUTES, $unit === $typing))
				) {

					$minutes = (int)$words[$after];
					$claimed[] = $after;

					if($unit !== NULL) {
						$claimed[] = $unit;
					}

				}

			} else {
				continue;
			}

			foreach($claimed as $claim) {
				unset($words[$claim]);
			}

			if($minutes === NULL) {
				$value = (int)round($number * 60);
			} else if($minutes >= 60 or $number !== round($number)) {
				$value = self::OVERFLOW;
			} else {
				$value = (int)$number * 60 + $minutes;
			}

			return;

		}

	}

	public static function apply(array &$reading, mixed $value): void {

		if($value === self::OVERFLOW) {
			EntryLib::refuse($reading, ['reason' => 'minutes']);
		} else if(is_int($value) and ($value <= 0 or $value > self::DAY)) {
			EntryLib::refuse($reading, ['reason' => 'duration']);
		}

	}

	public static function isComplete(mixed $value): bool {
		return ($value !== self::TYPING);
	}

	/**
	 * Un nombre en cours de frappe commence-t-il une durée ? Oui après un autre nombre, la quantité, et
	 * toujours pour une intention dont aucune quantité ne lit les nombres (`QuantityComplementLib::getIntentions()`).
	 */
	private static function startsDuration(array $words, array $positionsBefore, string $intention): bool {

		if(in_array($intention, QuantityComplementLib::getIntentions(), TRUE) === FALSE) {
			return TRUE;
		}

		foreach($positionsBefore as $position) {
			if(self::getNumber($words[$position]) !== NULL) {
				return TRUE;
			}
		}

		return FALSE;

	}

	/**
	 * Le temps en heures, comme `series\Timesheet::time` l'écrit, d'une durée lue : `NULL` sans durée.
	 */
	public static function getHours(mixed $value): ?float {
		return is_int($value) ? $value / 60 : NULL;
	}

	private static function getNumber(string $word): ?float {

		if(preg_match('/^([0-9]+)([.,]([0-9]+))?$/', $word, $match) === 0) {
			return NULL;
		}

		return (float)($match[1].'.'.($match[3] ?? '0'));

	}

	/**
	 * Un mot de durée, ou en cours de frappe le début d'un seul genre de mot : « mi » commence les minutes,
	 * « h » les heures. Un « m » seul attend sa deuxième lettre, parce qu'après un nombre il commence aussi
	 * « mai » et « mars », que la date lit ensuite sans rien écrire tant que le mois n'est pas tapé.
	 */
	private static function isWord(string $word, array $words, bool $typing): bool {

		if(in_array($word, $words, TRUE)) {
			return TRUE;
		}

		if($typing === FALSE) {
			return FALSE;
		}

		if($word === 'm') {
			return FALSE;
		}

		foreach($words as $candidate) {
			if(str_starts_with($candidate, $word)) {
				return TRUE;
			}
		}

		return FALSE;

	}

}
?>
