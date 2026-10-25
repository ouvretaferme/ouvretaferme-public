<?php
namespace entry;

/**
 * La saison : un nombre d'année dit après l'espèce, « ail 2025 », que la quantité prendrait sinon pour
 * 2 025 pièces (17/10). Suivi d'une unité, « ail 2025 kg », il reste une quantité.
 *
 * Une saison dite restreint les cultures de l'espèce aux siennes, même hors phase, et l'espèce qui n'en
 * a aucune est refusée plutôt qu'écrite en quantité (`EntryLib::read()`).
 *
 * Les années comprises sont les saisons dont la saisie lit les cultures à la date du jour
 * (`CultivationResolveLib::getSeasons()`), plus une de chaque côté. Une saison hors de la fenêtre du
 * jour dit ne prend pas de récolte (`\series\Task::planned.season`) : la lecture la refuse en le
 * disant, plutôt que d'écrire « ail 2024 » en 2 024 pièces. Au-delà, le nombre reste une quantité,
 * et aucune récolte de la base de dev n'a pour quantité une année de 2024 à 2028 (S2f, plan § 4).
 * La marge couvre aussi le lundi de la première semaine d'une saison, où « hier » est de la saison
 * d'avant et décale la fenêtre d'un an.
 */
class SeasonComplementLib extends ComplementLib {

	/**
	 * Le mot qui peut précéder l'année, comme l'écran l'écrit (« Saison 2025 »).
	 */
	const string WORD = 'saison';

	public static function getIntentions(): array {
		return ['harvest'];
	}

	/**
	 * La première année comprise qu'aucune unité ne suit. L'année en cours de frappe est déjà la saison :
	 * Entrée tapée trop vite ne propose jamais d'écrire l'année en quantité.
	 */
	public static function read(array &$words, mixed &$value, ?int $typing, string $intention): void {

		$seasons = CultivationResolveLib::getSeasons(\Clock::date());
		$positions = array_keys($words);

		foreach($positions as $index => $position) {

			$word = $words[$position];

			if(
				preg_match('/^[0-9]{4}$/', $word) === 0 or
				(int)$word < min($seasons) - 1 or
				(int)$word > max($seasons) + 1
			) {
				continue;
			}

			$next = $positions[$index + 1] ?? NULL;

			if($next !== NULL and UnitComplementLib::getUnits($words[$next], $next === $typing) !== []) {
				continue;
			}

			$value = (int)$word;
			unset($words[$position]);

			$previous = $positions[$index - 1] ?? NULL;

			if($previous !== NULL and $words[$previous] === self::WORD) {
				unset($words[$previous]);
			}

			return;

		}

	}

	public static function apply(array &$reading, mixed $value): void {

		if($value !== NULL and in_array($value, CultivationResolveLib::getSeasons($reading['date']), TRUE) === FALSE) {
			EntryLib::refuse($reading, ['reason' => 'seasonDate', 'value' => $value]);
		}

	}

	public static function narrowsObjects(mixed $value): bool {
		return ($value !== NULL);
	}

}
?>
