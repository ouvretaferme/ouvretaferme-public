<?php
namespace entry;

class UnitComplementUi {

	/**
	 * Le vocabulaire du complément, pour le refus d'un mot que personne n'a réclamé : « en kilos, en
	 * pièces, en bottes ou en caisses ».
	 */
	public static function getVocabulary(array $units): string {

		$words = array_map(fn(string $unit) => self::getCountedIn($unit), $units);

		$last = array_pop($words);

		return ($words === []) ? $last : s("{list} ou {last}", ['list' => implode(', ', $words), 'last' => $last]);

	}

	/**
	 * « en kilos », « en bottes » : ce en quoi une récolte se compte.
	 */
	public static function getCountedIn(string $unit): string {

		return match($unit) {
			\series\Task::KG => s("en kilos"),
			\series\Task::UNIT => s("en pièces"),
			\series\Task::BUNCH => s("en bottes"),
			UnitComplementLib::CRATE => s("en caisses"),
		};

	}

	/**
	 * L'unité en puce, sous la ligne qui écrit, du même mot que le choix d'unité du panneau Consigner : les caisses
	 * avec leur poids, sans lequel elles ne s'offrent pas (`HarvestIntentionLib::getUnitChoices()`).
	 */
	public static function getChip(string $unit, \plant\Plant $ePlant): string {

		return ($unit === UnitComplementLib::CRATE) ?
			\planning\HarvestEntryUi::getCrateUnit($ePlant) :
			\planning\HarvestEntryUi::getUnitField($unit);

	}

	/**
	 * Le refus d'une décimale sur une unité qui se compte en entiers. Les kilos n'y passent jamais
	 * (`EntrySetting::DECIMAL_UNITS`), d'où leur absence du `match`.
	 */
	public static function getWhole(string $unit): string {

		return match($unit) {
			\series\Task::UNIT => s("Les pièces se comptent en nombre entier."),
			\series\Task::BUNCH => s("Les bottes se comptent en nombre entier."),
			UnitComplementLib::CRATE => s("Les caisses se comptent en nombre entier."),
		};

	}

}
?>
