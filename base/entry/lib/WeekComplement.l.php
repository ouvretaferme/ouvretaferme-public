<?php
namespace entry;

/**
 * La semaine dite dans la phrase : « cette semaine », « semaine prochaine », « semaine 46 ». Seule
 * l'intervention la lit (`InterventionIntentionLib`), pour planifier une intervention à faire à la semaine,
 * sans jour.
 *
 * Sa valeur est la semaine (`o-\WW`, comme `toWeek()`), `INCOMPLETE` tant que « semaine » attend son
 * numéro, `INVALID` pour un numéro que l'année n'a pas. Une semaine dite par son numéro est la prochaine
 * de ce numéro, celle d'aujourd'hui comprise : on planifie ce qui vient, et la semaine 2 tapée en octobre
 * est celle de janvier.
 *
 * Elle passe en tête du registre : la durée prendrait sinon le numéro en cours de frappe pour le début
 * d'une durée (`DurationComplementLib::TYPING`).
 */
class WeekComplementLib extends ComplementLib {

	const string WORD = 'semaine';
	const string THIS = 'cette';
	const array NEXT = ['prochaine'];

	const string INCOMPLETE = 'incomplete';
	const string INVALID = 'invalid';

	public static function getIntentions(): array {
		return ['intervention'];
	}

	public static function read(array &$words, mixed &$value, ?int $typing, string $intention): void {

		$positions = array_values(array_keys($words));

		foreach($positions as $index => $position) {

			if($words[$position] !== self::WORD) {
				continue;
			}

			$previous = $positions[$index - 1] ?? NULL;
			$next = $positions[$index + 1] ?? NULL;

			if($previous !== NULL and $words[$previous] === self::THIS) {
				$value = \Clock::week();
				unset($words[$previous], $words[$position]);
				return;
			}

			if($next === NULL) {
				$value = self::INCOMPLETE;
				unset($words[$position]);
				return;
			}

			if(in_array($words[$next], self::NEXT, TRUE)) {
				$value = toWeek(\Clock::shift('+7 DAYS'));
				unset($words[$position], $words[$next]);
				return;
			}

			if(preg_match('/^[0-9]{1,2}$/', $words[$next]) === 1) {
				$value = self::getNumbered((int)$words[$next]);
				unset($words[$position], $words[$next]);
				return;
			}

		}

	}

	/**
	 * La prochaine semaine de ce numéro, celle d'aujourd'hui comprise.
	 */
	private static function getNumbered(int $number): string {

		// L'année ISO, celle des numéros de semaine : le 1er janvier peut encore être en semaine 53.
		$year = (int)\Clock::format('o');

		if(sprintf('%04d-W%02d', $year, $number) < \Clock::week()) {
			$year++;
		}

		if($number < 1 or $number > getWeeksInYear($year)) {
			return self::INVALID;
		}

		return sprintf('%04d-W%02d', $year, $number);

	}

	public static function apply(array &$reading, mixed $value): void {

		if($value === self::INVALID) {
			EntryLib::refuse($reading, ['reason' => 'week']);
		}

	}

	public static function isWeek(mixed $value): bool {
		return (is_string($value) and is_week($value));
	}

	public static function isComplete(mixed $value): bool {
		return ($value !== self::INCOMPLETE);
	}

}
?>
