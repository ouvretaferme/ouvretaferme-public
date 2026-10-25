<?php
namespace entry;

/**
 * Le contrat d'un complément : un morceau de phrase qui précise l'intention (une quantité, une
 * unité, plus tard une durée ou une date).
 *
 * Un complément s'inscrit dans `EntrySetting::COMPLEMENTS`, et c'est lui qui dit à quelles
 * intentions publiques il s'accroche : l'intention n'a pas à le connaître. Une intention déclarée par
 * un package fermé, qu'il ne peut pas nommer, le demande par sa clé (`IntentionLib::getComplements()`).
 * Le moteur lui passe les mots que personne n'a encore réclamés, dans l'ordre du registre.
 */
abstract class ComplementLib {

	/**
	 * Les clés d'intention (`EntrySetting::INTENTIONS`) que le complément précise.
	 */
	abstract public static function getIntentions(): array;

	/**
	 * Réclame ses mots dans `$words` (en les retirant) et pose sa valeur dans `$value`.
	 *
	 * `$typing` est la position du mot en cours de frappe, `NULL` quand la phrase se termine par une
	 * espace : un complément peut accepter un début de mot à cette position seulement (« 12 k »), le
	 * mot n'étant pas fini.
	 *
	 * `$intention` est la clé de l'intention lue : un même mot ne dit pas la même chose à une intention
	 * qui lit une quantité et à une autre qui n'en lit pas (`DurationComplementLib`).
	 */
	abstract public static function read(array &$words, mixed &$value, ?int $typing, string $intention): void;

	/**
	 * Le vocabulaire que le complément comprend, sous une forme que son `Ui` sait dire, pour le refus
	 * d'un mot que personne n'a réclamé. `NULL` quand il n'y a rien à en dire.
	 */
	public static function getVocabulary(): ?array {
		return NULL;
	}

	/**
	 * Porte sur la lecture ce que la valeur du complément change pour toute la phrase (la date, pour
	 * `DateComplementLib`), ou la refuse. Appelé une fois tous les compléments lus, avant que
	 * l'intention ne résolve son objet. Rien par défaut.
	 */
	public static function apply(array &$reading, mixed $value): void {
	}

	/**
	 * Une valeur qui restreint les objets de l'intention (la saison, pour `SeasonComplementLib`) : le
	 * moteur les relit avec elle, et refuse un objet nommé qui n'y est plus, par la clé du complément.
	 */
	public static function narrowsObjects(mixed $value): bool {
		return FALSE;
	}

	/**
	 * Une valeur qui se tape encore : la lecture ne s'écrit pas tant qu'un complément en porte une.
	 */
	public static function isComplete(mixed $value): bool {
		return TRUE;
	}

	/**
	 * Après l'écriture de l'intention, dans la même transaction. Rien par défaut.
	 */
	public static function afterWrite(\structure\Structure $eStructure, array $reading): void {
	}

}
?>
