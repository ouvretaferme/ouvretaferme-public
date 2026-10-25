<?php
namespace entry;

/**
 * Le champ de saisie : une phrase tapée (« récolte courgette 12 kg ») devient une écriture.
 *
 * Le moteur ne connaît ni la récolte ni les kilos. Il découpe la phrase, cherche le verbe parmi les
 * intentions inscrites ici, puis passe les mots restants aux compléments inscrits, dans l'ordre de
 * leur inscription. Ajouter une intention ou un complément, c'est créer sa classe, son `Ui` et son
 * test, puis l'ajouter à ce registre : le moteur ne change pas (même motif que
 * `VatSetting::CERFA_VERSIONS`, `accounting/vat/Vat.s.php`).
 */
class EntrySetting extends \Settings {

	/**
	 * Les intentions, par clé : la clé voyage dans la réponse (`intention`) et dans le jeton posé par
	 * l'écran. `IntentionSentinel.t.php` tient la liste attendue.
	 */
	const array INTENTIONS = [
		'harvest' => HarvestIntentionLib::class,
		'time' => TimeIntentionLib::class,
		'done' => DoneIntentionLib::class,
		'intervention' => InterventionIntentionLib::class,
	];

	/**
	 * Les compléments, par clé. ⚠️ L'ordre est l'ordre de lecture : un complément réclame ses mots
	 * avant ceux qui le suivent. La semaine passe en tête : la durée prendrait le numéro qui la suit en cours
	 * de frappe (« semaine 4 », pour 46) pour le début d'une durée. La durée passe ensuite : en cours de
	 * frappe, le « h » de « 12 kg 1 h » commence aussi « hier », et la date le prendrait. Elle ne réclame
	 * qu'un nombre suivi d'un mot de durée, que ni « le 12 », ni « 12 octobre », ni « 12/10 » ne portent. La date passe ensuite, parce que
	 * ces trois formes portent un nombre que la quantité prendrait. La saison passe avant la quantité pour
	 * la même raison (« ail 2025 »). La quantité passe avant l'unité, qui lit le mot collé au nombre
	 * (« 12kg ») que la quantité a laissé. `Grammar.t.php` le vérifie, et `Response.t.php` pour la semaine.
	 */
	const array COMPLEMENTS = [
		'week' => WeekComplementLib::class,
		'duration' => DurationComplementLib::class,
		'date' => DateComplementLib::class,
		'season' => SeasonComplementLib::class,
		'quantity' => QuantityComplementLib::class,
		'unit' => UnitComplementLib::class,
	];

	/**
	 * Les unités qui se comptent au dixième. La lecture refuse une décimale posée sur une autre : une
	 * demi-botte n'est pas une quantité, et l'arrondir écrirait un nombre que personne n'a tapé.
	 */
	const array DECIMAL_UNITS = [\series\Task::KG];

	/**
	 * Jusqu'où une récolte ou un temps se saisit dans le passé, en jours (16/10 pour la récolte,
	 * 17/10 pour le temps, lot 2 Q6). Au-delà, l'une passe par l'écran de récolte de son intervention, l'autre
	 * par la fiche de temps, qui n'ont pas de borne.
	 */
	const int PAST_DAYS = 31;

	/**
	 * Combien de temps, en secondes, une saisie s'annule encore par sa pastille : le ticket d'annulation
	 * (`HarvestIntentionLib::getCancelKey()`, `TimeIntentionLib::getCancelKey()`) vit ce temps-là. Au-delà des 6 s de la pastille,
	 * pour un réseau lent ; bien en deçà de ce qui ferait d'« Annuler » une correction tardive, qui passe
	 * par l'écran de récolte de l'intervention.
	 */
	const int CANCEL_DELAY = 60;

	/**
	 * La longueur de phrase que le champ lit, en caractères. Chaque frappe relit la phrase entière : sans borne,
	 * un texte collé occuperait le serveur à chaque touche. La plus longue phrase utile, une vente de cent
	 * articles, tient en deçà ; au-delà, la fin ne se lit pas.
	 */
	const int TEXT_MAX = 8000;

	/**
	 * Les mots de liaison, ignorés partout : « 12 kg de courgettes » se lit comme « courgette 12 kg ».
	 */
	const array LINKS = ['de', 'des', 'du', 'd\''];

	/**
	 * Le champ est ouvert en dev et, en production, aux structures de `FEATURE_ENTRY`.
	 *
	 * ⚠️ Une closure et non une lecture directe de la constante : la constante se définit une fois par
	 * processus (`ouvretaferme/Lime.s.php`), et seule une closure laisse un test éprouver une structure
	 * hors liste. Même forme que `NotificationSetting::$enabled`.
	 */
	public static \Closure $enabled;

	public static function isEnabled(\structure\Structure $eStructure): bool {
		return (self::$enabled)($eStructure);
	}

	/**
	 * La règle du drapeau, séparée de sa valeur : `TRUE` ouvre toutes les structures, une liste
	 * n'ouvre que les siennes.
	 */
	public static function accepts(\structure\Structure $eStructure, bool|array $flag): bool {
		return ($flag === TRUE or (is_array($flag) and in_array($eStructure['id'], $flag, TRUE)));
	}

}

EntrySetting::$enabled = fn(\structure\Structure $eStructure): bool => EntrySetting::accepts($eStructure, FEATURE_ENTRY);
?>
