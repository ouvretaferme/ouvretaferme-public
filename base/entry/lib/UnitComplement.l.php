<?php
namespace entry;

/**
 * L'unité de récolte : kilos, pièces ou bottes, les trois unités qu'une intervention de récolte
 * porte (`series\Task::harvestUnit`), ou caisses, qui s'écrivent en kilos.
 *
 * Le complément ne dit que l'unité nommée. Celle qui vaut quand rien n'est dit, le refus d'une
 * unité autre que celle déjà tenue par l'intervention, et la conversion des caisses au poids de
 * l'espèce, appartiennent à l'intention, qui connaît l'intervention et l'espèce
 * (`HarvestIntentionLib::resolve()`).
 */
class UnitComplementLib extends ComplementLib {

	/**
	 * Les caisses ne sont pas une unité d'intervention : elles se comptent dans la phrase et s'écrivent en
	 * kilos, comme dans le panneau Consigner, où elles ne sont qu'un choix de saisie.
	 */
	const string CRATE = 'crate';

	const array WORDS = [
		'kg' => \series\Task::KG,
		'kgs' => \series\Task::KG,
		'kilo' => \series\Task::KG,
		'kilos' => \series\Task::KG,
		'kilogramme' => \series\Task::KG,
		'kilogrammes' => \series\Task::KG,
		'piece' => \series\Task::UNIT,
		'pieces' => \series\Task::UNIT,
		'unite' => \series\Task::UNIT,
		'unites' => \series\Task::UNIT,
		'botte' => \series\Task::BUNCH,
		'bottes' => \series\Task::BUNCH,
		'caisse' => self::CRATE,
		'caisses' => self::CRATE,
		'cs' => self::CRATE,
	];

	public static function getIntentions(): array {
		return ['harvest'];
	}

	public static function read(array &$words, mixed &$value, ?int $typing, string $intention): void {

		foreach($words as $position => $word) {

			$units = self::getUnits($word, $position === $typing);

			// Un début de mot ne désigne une unité que si tous les noms qu'il commence en désignent la même.
			if($units !== []) {
				$value = (count($units) === 1) ? $units[0] : NULL;
				unset($words[$position]);
				return;
			}

		}

	}

	/**
	 * Les unités qu'un mot désigne. Le mot en cours de frappe désigne déjà celles dont il commence un
	 * nom : « 12 k » ne se refuse pas, il s'écrit « 12 kg ».
	 */
	public static function getUnits(string $word, bool $typing): array {

		if(array_key_exists($word, self::WORDS)) {
			return [self::WORDS[$word]];
		}

		if($typing === FALSE) {
			return [];
		}

		$units = [];

		foreach(self::WORDS as $unitWord => $unit) {
			if(str_starts_with($unitWord, $word)) {
				$units[$unit] = TRUE;
			}
		}

		return array_keys($units);

	}

	/**
	 * Le mot qui dit une unité, le premier de son vocabulaire : celui que pose dans la phrase la puce d'une unité.
	 */
	public static function getWord(string $unit): string {
		return array_search($unit, self::WORDS, TRUE);
	}

	public static function getVocabulary(): array {
		return array_values(array_unique(self::WORDS));
	}

}
?>
