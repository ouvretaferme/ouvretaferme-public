<?php
namespace entry;

/**
 * Ce qu'une intention dit à l'écran, pour la classe du moteur qui porte le même nom
 * (`HarvestIntentionUi` pour `HarvestIntentionLib`). `EntryUi` garde ce qui ne dépend d'aucune intention,
 * le verbe qui manque, le refus d'un complément, la complétion du mot en frappe, et confie le reste à
 * l'intention de la lecture : ses suggestions, son étape, ses consignes, ses refus, et les
 * pastilles de l'écriture et de l'annulation (`EntryUi::getIntentions()`).
 */
abstract class IntentionUi {

	/**
	 * Le nom de l'intention, en tête de sa ligne d'écriture et sur son jeton de verbe.
	 */
	abstract public static function getLabel(): string;

	/**
	 * Le jeton du verbe dit dans la phrase, que la lecture suivante relit comme s'il était tapé : un mot de
	 * l'intention (`IntentionLib::getWords()`), par défaut son nom.
	 */
	public static function getToken(): string {
		return static::getLabel();
	}

	/**
	 * Les suggestions d'une lecture qui a trouvé l'intention et ne refuse rien : ses objets tant que
	 * l'objet manque, les choix de sa question, puis la ligne qui écrit (`getWriteResult()`).
	 *
	 * `$cElement` arrive filtré par les mots en attente (`EntryLib::findObjects()`).
	 */
	abstract public static function getResults(\structure\Structure $eStructure, array $reading, \Collection $cElement, \Collection $cAction): array;

	/**
	 * Les familles de ses suggestions que la liste pose en puces (`OmniboxUi::get()`, `chips`), par leur nom.
	 * La boîte les reçoit une fois, au rendu du champ : une famille se nomme donc sans dépendre de la lecture.
	 */
	public static function getChips(): array {
		return [];
	}

	/**
	 * Les jours qu'une écriture retient, quand ses puces en ajoutent au jour dit : le champ retire alors les jetons
	 * de jour qu'elle écarte (`EntryField.load()`). `NULL` pour une intention sans puces de jour.
	 */
	public static function getDays(array $reading): ?array {
		return NULL;
	}

	/**
	 * L'étape d'une lecture dont l'objet est résolu, sans question ni écriture possible : ce que la phrase
	 * attend encore (`EntryUi::getStep()`).
	 */
	abstract public static function getAwaitedStep(): string;

	/**
	 * La question que la liste pose en tête à cette étape (`EntryUi::getResults()`), au-dessus des puces qui la
	 * précisent. `NULL` pour une intention dont les suggestions disent déjà ce qu'elle attend.
	 */
	public static function getAwaited(array $reading): ?string {
		return NULL;
	}

	/**
	 * Le texte du champ au repos à chaque étape qui suit le verbe.
	 */
	abstract public static function getPlaceholder(array $reading, string $step): string;

	/**
	 * Le refus d'une raison que l'intention pose ou dit à sa façon, `NULL` pour celles des compléments,
	 * que `EntryUi::getRefusal()` dit pour toutes.
	 */
	abstract public static function getRefusal(array $reading): ?string;

	/**
	 * Les suggestions qui suivent le refus : ce qui le corrige dans la phrase même (une coche, une puce), ou un
	 * lien vers l'écran où se corrige ce que la phrase ne peut pas changer (créer le client qu'elle nomme).
	 * Aucune par défaut.
	 */
	public static function getRefusalResults(\structure\Structure $eStructure, array $reading): array {
		return [];
	}

	/**
	 * L'icône de l'objet nommé, sur son jeton.
	 */
	abstract public static function getObjectIcon(\Element $eElement): ?string;

	/**
	 * Les jetons des lignes complètes, pour une intention qui en lit après son objet (`lines`) : chacun
	 * `['value', 'label', 'icon']`, son libellé relu comme s'il était tapé. Avec eux, `rest`, ce que la frappe
	 * garde quand un objet choisi dans la liste remplace les mots qui le cherchaient. `NULL` sans lignes.
	 */
	public static function getLines(array $reading): ?array {
		return NULL;
	}

	/**
	 * Ce que l'écriture fera, en une ligne, et sa précision en dessous.
	 */
	abstract public static function getWriteLabel(array $reading): string;

	abstract public static function getWriteDetail(array $reading): string;

	/**
	 * La pastille d'une écriture, comme `toast()` la prend (`package`, `fqn`, `options`, `icon`, `detail`) :
	 * son message vit dans l'`Alert.u.php` du package de l'intention. La vue y ajoute le lien « Annuler » et
	 * le point de départ.
	 */
	abstract public static function getWriteToast(array $reading): array;

	/**
	 * Ce que l'écriture dit, dans l'événement `entryWrite` d'`entry.js`, aux écrans que sa réponse ne
	 * réécrit pas.
	 */
	abstract public static function getWriteEvent(\structure\Structure $eStructure, array $reading): array;

	/**
	 * La pastille et l'événement `entryCancel` d'une annulation, sur ce que
	 * `IntentionLib::getCancellation()` a lu.
	 */
	abstract public static function getCancelToast(array $cancellation): array;

	abstract public static function getCancelEvent(\structure\Structure $eStructure, array $cancellation): array;

	/**
	 * L'adresse où « Annuler » poste l'intention et le jeton de son ticket (`EntryUi::getUndo()`).
	 */
	public static function getCancelUrl(): string {
		return '/entry/entry:doCancel';
	}

	/**
	 * La ligne qui écrit, en dernier dans la liste.
	 */
	protected static function getWriteResult(array $reading): array {

		return [
			'group' => static::getLabel(),
			'label' => static::getWriteLabel($reading),
			'value' => 'write',
			'detail' => static::getWriteDetail($reading),
			'cursor' => TRUE,
		];

	}

	/**
	 * « de courgette », « d'ail » : l'élision devant une voyelle, pas devant un h.
	 */
	public static function of(string $name): string {

		$name = self::lower($name);

		$first = \Normalizer::normalize(mb_substr($name, 0, 1), \Normalizer::FORM_D)[0];

		return in_array($first, ['a', 'e', 'i', 'o', 'u', 'y'], TRUE) ? s("d'{value}", $name) : s("de {value}", $name);

	}

	private static function lower(string $name): string {
		return mb_strtolower(mb_substr($name, 0, 1)).mb_substr($name, 1);
	}

	protected static function getPlantIcon(\plant\Plant $ePlant): ?string {
		return ($ePlant->empty() or $ePlant['fqn'] === NULL) ? NULL : \plant\PlantUi::getVignetteHref($ePlant['fqn']);
	}

}
?>
