<?php
namespace entry;

class DurationComplementUi {

	/**
	 * Une durée en minutes, comme le champ la lit et comme la feuille de temps l'écrit : « 30 min », « 1 h »,
	 * « 1 h 30 ». Pas par `series\TimesheetUi::convertTimeField()`, qui prend des heures en flottant et
	 * tronque ses minutes (1 h 20 y devient 1 h 19) et préfixe un signe ou une espace.
	 */
	public static function getDuration(int $minutes): string {

		$hours = intdiv($minutes, 60);
		$rest = $minutes % 60;

		if($hours === 0) {
			return s("{value} min", $rest);
		} else if($rest === 0) {
			return s("{value} h", $hours);
		} else {
			return s("{hours} h {minutes}", ['hours' => $hours, 'minutes' => sprintf('%02d', $rest)]);
		}

	}

	/**
	 * Les puces qui ajoutent au temps déjà dit (`DurationComplementLib::getAdditions()`) : chacune dit ce qu'elle
	 * ajoute, « +30 min », et son jeton le temps qu'elle donne, « 1 h 30 », que la phrase relit.
	 */
	public static function getAdditionResults(string $group, array $additions): array {

		$results = [];

		foreach($additions as $added => $minutes) {
			$results[] = [
				'group' => $group,
				'label' => s("+{duration}", ['duration' => self::getDuration($added)]),
				'token' => self::getDuration($minutes),
				'value' => 'duration:'.$minutes,
			];
		}

		return $results;

	}

	/**
	 * Le participe qui suit une durée s'accorde à son unité : une heure, une heure et quelques minutes ou
	 * une minute le veulent au singulier, moins d'une heure ou deux heures et plus au pluriel.
	 */
	public static function isSingular(int $minutes): bool {
		return ($minutes === 1 or ($minutes >= 60 and $minutes < 120));
	}

	public static function getRefusal(): string {
		return s("Indiquez une durée de plus de zéro minute et de 24 heures au plus.");
	}

	public static function getMinutesRefusal(): string {
		return s("Les minutes se comptent jusqu'à 59.");
	}

	public static function getUntrackedRefusal(): string {
		return s("Le temps de travail n'est pas suivi dans cette structure.");
	}

}
?>
