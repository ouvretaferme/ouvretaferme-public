<?php
namespace entry;

/**
 * Le contrat d'une intention : ce que le verbe de la phrase fait faire.
 *
 * Une intention publique s'inscrit dans `EntrySetting::INTENTIONS`, celle d'un package fermé par son
 * observateur (`EntryLib::getIntentions()`). Le moteur (`EntryLib`) lui confie, dans
 * cet ordre : les mots qui désignent son objet (`readObject()`), puis, une fois les compléments lus,
 * la résolution de ce qui reste à décider (`resolve()`), enfin la préparation et l'écriture
 * (`prepare()`, `write()`). Les compléments déclarent à quelles intentions publiques ils s'accrochent
 * (`ComplementLib::getIntentions()`) ; une intention déclarée, qu'un complément de `base/entry` ne peut
 * pas nommer, dit elle-même ceux qu'elle lit (`getComplements()`).
 *
 * Aucune méthode ne rend de texte : la réponse est faite de données, et c'est l'écran de l'intention
 * qui les dit, une classe `IntentionUi` (`HarvestIntentionUi` pour `HarvestIntentionLib`).
 */
abstract class IntentionLib {

	/**
	 * Le droit d'écrire ce que l'intention écrit. Le champ ne lit que les intentions que le membre peut
	 * écrire (`EntryLib::getAllowed()`), et sa page refuse celui qui n'en a aucune.
	 */
	abstract public static function canWrite(\structure\Structure $eStructure): bool;

	/**
	 * L'écriture et l'annulation touchent-elles le planning de la semaine : leur réponse en rend alors les
	 * lignes qui changent (`PlanningLib::getChangeCollection()`), d'après les interventions de la confirmation
	 * et de l'annulation (`getPlanningTasks()`), et le temps de l'une (`minutes`) et de l'autre (`time`).
	 */
	public static function writesPlanning(): bool {
		return FALSE;
	}

	/**
	 * Les pièces de l'accueil que l'écriture et l'annulation peuvent changer, par leur nom de
	 * `dashboard\DashboardLib::getBlocks()` ou, pour une ligne de la file de ce qui attend un geste, de
	 * `dashboard\DashboardLib::getContributions()`. Faites depuis le champ de l'accueil, leur réponse redessine
	 * celles que l'écran montre (`HomeLib::getChange()`). L'intention les nomme parce qu'elle seule sait ce qu'elle
	 * écrit, et le moteur de l'accueil n'a pas à connaître le champ.
	 */
	public static function getDashboardBlocks(): array {
		return [];
	}

	/**
	 * Les interventions que le planning redessine, dans leur état du moment, lues sur la confirmation ou sur
	 * l'annulation : celle qu'elles portent (`eTask`), et plusieurs pour la coche (`DoneIntentionLib`). Lue
	 * avant l'écriture, celle qui va se créer n'a pas encore d'id.
	 */
	public static function getPlanningTasks(array $values): \Collection {
		return new \Collection([$values['eTask']]);
	}

	/**
	 * Les clés de `EntrySetting::COMPLEMENTS` que l'intention lit en plus de ceux qui s'accrochent à elle
	 * (`EntryLib::getComplements()`).
	 */
	public static function getComplements(): array {
		return [];
	}

	/**
	 * L'intention lit-elle elle-même les mots que les compléments laissent (les articles d'une vente) : un
	 * début de mot en cours de frappe peut alors être l'un des siens, et un complément ne le devine pas
	 * (`DateComplementLib`). Le « s » de « salade » n'est pas samedi.
	 */
	public static function readsRest(): bool {
		return FALSE;
	}

	/**
	 * L'intention montre-t-elle un récapitulatif avant d'écrire (`IntentionUi::getChips()`) : la lecture ne
	 * prend la réponse `recap` que d'elle, et le jour qu'il retient tient pendant qu'un autre se tape
	 * (`DateComplementLib`).
	 */
	public static function hasRecap(): bool {
		return FALSE;
	}

	/**
	 * Les mots qui désignent l'intention, sans accent et en minuscules, tels que `EntryLib::split()`
	 * les rend.
	 */
	abstract public static function getWords(): array;

	/**
	 * Le verbe est-il obligatoire : la phrase sans verbe ne se lit pas avec lui (`EntryLib::readImplicit()`).
	 * C'est le choix d'une intention dont l'objet peut porter le nom d'un objet d'une autre (un client et
	 * une espèce) : deviner le verbe écrirait la mauvaise.
	 */
	public static function isExplicit(): bool {
		return FALSE;
	}

	/**
	 * Ce qui départage, sans verbe, deux intentions qui lisent la même phrase aussi bien et nomment le même
	 * objet par les mêmes mots (`EntryLib::readImplicit()`) : la plus haute l'emporte, 0 par défaut. La coche
	 * lit la phrase du temps et ne s'en distingue que par ce qu'elle trouve à cocher.
	 */
	public static function getImplicitPrecedence(array $reading): int {
		return 0;
	}

	/**
	 * Écartée par la précédence, la lecture a-t-elle encore quelque chose à écrire que l'écran de la gagnante
	 * peut proposer (`EntryLib::readImplicit()`) : seule la coche le dit, sous le temps (lot S4c).
	 */
	public static function isOffered(array $reading): bool {
		return FALSE;
	}

	/**
	 * Les objets que l'intention sait nommer pour cette structure, dans l'ordre où l'écran les
	 * propose. C'est la liste où `readObject()` cherche, et celle que l'écran filtre par le mot en
	 * attente.
	 *
	 * `$reading` porte le jour dit et les compléments lus, vides au premier appel, qui précède leur
	 * lecture. `$cElementRead` est ce que le même appel a rendu pour la même phrase avant qu'un complément
	 * ne dise un autre jour ou ne restreigne les objets. L'intention y reprend ce qui ne dépend ni de l'un
	 * ni de l'autre, plutôt que de le relire.
	 */
	abstract public static function getObjects(\structure\Structure $eStructure, array $reading, \Collection $cElementRead = new \Collection()): \Collection;

	/**
	 * Les objets que la liste montre, parmi ceux que la frappe a trouvés (`EntryLib::findObjects()`) : une
	 * intention aux objets nombreux y borne la liste et la range, et seule la liste en paie le coût, jamais la
	 * lecture de la phrase.
	 */
	public static function getShown(\structure\Structure $eStructure, array $reading, \Collection $cElement): \Collection {
		return $cElement;
	}

	/**
	 * Les objets que l'écran propose et que la frappe trouve par le début de leur nom : tous, sauf ceux
	 * qu'une intention ne garde que pour lire une phrase entière.
	 */
	public static function getListed(\Collection $cElement): \Collection {
		return $cElement;
	}

	/**
	 * Les objets que l'écran ne propose qu'une fois le début de leur nom tapé (`getShown()`), et que la frappe
	 * complète alors comme les autres (`EntryLib::getCompletion()`) : aucun par défaut.
	 */
	public static function getTyped(\Collection $cElement): \Collection {
		return new \Collection();
	}

	/**
	 * Réclame dans `$words` les mots qui nomment l'objet, et rend l'objet trouvé (un élément vide
	 * sinon). Passe avant les compléments : un nom d'objet peut contenir un mot qu'un complément
	 * réclamerait (« Radis botte », « Pomme de terre »).
	 */
	abstract public static function readObject(\Collection $cElement, array &$words): \Element;

	/**
	 * Réclame dans `$words` les mots qui précisent l'objet posé (la variété et le calibre d'une récolte), et en
	 * pose la lecture dans `$reading`. Passe après l'objet, nommé ou posé par la case, et avant les compléments :
	 * un tel nom peut porter un nombre que la quantité prendrait (« De Détroit 2 »). Rien par défaut.
	 *
	 * `$cElement` est la collection où l'objet a été lu (`getObjects()`).
	 */
	public static function readQualifiers(\structure\Structure $eStructure, array &$reading, array &$words, \Collection $cElement): void {
	}

	/**
	 * La clé de l'objet dont le nom est la plus longue suite de mots de la phrase, chaque mot au singulier
	 * ou au pluriel (« pommes de terre », « courgettes », « choux »), `NULL` si aucun ne l'est. Ses mots sont
	 * retirés de `$words`.
	 *
	 * `$withoutLinks` compare les noms sans leurs liaisons, pour des mots que le moteur a déjà lus sans
	 * elles (`EntryLib::withoutLinks()`) : « pommes terre » nomme alors Pomme de terre.
	 *
	 * `$anchored` ne cherche le nom qu'au premier des mots, pour une phrase qui en nomme plusieurs à la suite
	 * (les articles d'une vente) : chacun se lit là où le précédent s'arrête. Une telle phrase relit les mêmes
	 * noms à chaque article : `$split` les reçoit découpés une fois pour toutes (`splitNames()`), le découpage
	 * étant ce qui coûte.
	 */
	protected static function readName(\Collection $cElement, array &$words, bool $withoutLinks = FALSE, bool $anchored = FALSE, array $split = []): mixed {

		if($split === []) {
			$split = self::splitNames($cElement, $withoutLinks);
		}

		$positions = array_keys($words);

		$keyFound = NULL;
		$found = [];

		foreach($cElement as $key => $eElement) {

			$names = $split[$key];

			if(count($names) <= count($found)) {
				continue;
			}

			$starts = $anchored ? 1 : count($positions);

			for($start = 0; $start < $starts and $start + count($names) <= count($positions); $start++) {

				$matched = [];

				foreach($names as $offset => $name) {

					$position = $positions[$start + $offset];

					if(in_array($words[$position], [$name, $name.'s', $name.'x'], TRUE) === FALSE) {
						$matched = [];
						break;
					}

					$matched[] = $position;

				}

				if($matched !== []) {
					$keyFound = $key;
					$found = $matched;
					break;
				}

			}

		}

		foreach($found as $position) {
			unset($words[$position]);
		}

		return $keyFound;

	}

	/**
	 * Le nom de chaque objet découpé comme la phrase (`EntryLib::split()`), par clé, sans ses liaisons avec
	 * `$withoutLinks`.
	 */
	protected static function splitNames(\Collection $cElement, bool $withoutLinks = FALSE): array {

		$split = [];

		foreach($cElement as $key => $eElement) {

			$names = EntryLib::split($eElement['name']);

			$split[$key] = $withoutLinks ? array_values(EntryLib::withoutLinks($names)) : $names;

		}

		return $split;

	}

	/**
	 * Le refus d'un jour dit autre qu'aujourd'hui, en données (`['reason' => …]`), `NULL` s'il est
	 * accepté. Tous le sont par défaut : les bornes appartiennent à ce que l'intention écrit.
	 */
	public static function getDateRefusal(string $date): ?array {
		return NULL;
	}

	/**
	 * Le jour le plus ancien que la saisie admet, `EntrySetting::PAST_DAYS` jours avant aujourd'hui.
	 */
	public static function getPastLimit(): string {
		return date('Y-m-d', \Clock::shift('- '.EntrySetting::PAST_DAYS.' DAYS'));
	}

	/**
	 * Les bornes communes à ce qui s'écrit au passé : rien à l'avance, rien au-delà de
	 * `EntrySetting::PAST_DAYS`.
	 */
	protected static function getPastRefusal(string $date): ?array {

		if($date > \Clock::date()) {
			return ['reason' => 'future'];
		}

		if($date < self::getPastLimit()) {
			return ['reason' => 'past', 'days' => EntrySetting::PAST_DAYS];
		}

		return NULL;

	}

	/**
	 * Décide ce que les mots ne disent pas (la culture, l'unité tenue…), pose l'unique question si
	 * elle manque (`EntryLib::ask()`), le refus si la phrase ne peut pas s'écrire, et la confirmation
	 * quand tout est là.
	 *
	 * `$answers` porte les réponses que l'écran a déjà reçues à une question, par nom de question.
	 */
	abstract public static function resolve(\structure\Structure $eStructure, array &$reading, array $answers): void;

	/**
	 * Les mots en attente cherchent-ils encore ce que l'intention lit elle-même après son objet, le
	 * produit d'une ligne de vente (`lines`) ou l'espèce sous l'action seule d'un temps : le moteur ne les refuse pas comme des mots que personne n'a
	 * réclamés, l'écran les propose comme il propose les objets.
	 */
	public static function isSearching(array $reading): bool {
		return FALSE;
	}

	/**
	 * Construit et valide, hors transaction, tout ce que l'écriture posera. ⚠️ Un `Fail` journalisé
	 * dans une transaction ne la défait pas : c'est pourquoi rien de ce qui peut échouer à la
	 * validation ne doit rester pour `write()`.
	 */
	abstract public static function prepare(\structure\Structure $eStructure, array &$reading): void;

	/**
	 * Écrit, dans la transaction ouverte par `EntryLib::write()`, sur le modèle que rend `getModel()`.
	 * Un refus s'y lève par `$fw->validate()`, que `EntryLib::write()` rattrape pour défaire la
	 * transaction avant de le relancer.
	 */
	abstract public static function write(\structure\Structure $eStructure, array &$reading): void;

	/**
	 * Ce qui suit l'écriture une fois sa transaction validée, hors base. Rien par défaut.
	 */
	public static function afterCommit(\structure\Structure $eStructure, array &$reading): void {
	}

	/**
	 * Ce que l'annulation défera, lu et contrôlé hors transaction à partir du jeton que la pastille poste
	 * (`undo`, posé par `afterCommit()`), `NULL` si le jeton ne désigne plus rien que le membre connecté
	 * puisse annuler. Un refus s'y journalise, `EntryLib::prepareCancel()` le lève. La clé `date` dit le
	 * jour de la saisie annulée.
	 */
	abstract public static function getCancellation(\structure\Structure $eStructure, string $ticket): ?array;

	/**
	 * Annule, dans la transaction ouverte par `EntryLib::cancel()` sur le modèle de `getModel()`. Rend
	 * `FALSE` quand une autre annulation l'a devancée : rien ne s'est alors écrit.
	 */
	abstract public static function cancel(array $cancellation): bool;

	/**
	 * Ce qui suit l'annulation une fois sa transaction validée, hors base. Rien par défaut.
	 */
	public static function afterCancel(\structure\Structure $eStructure, array $cancellation): void {
	}

	/**
	 * Le fail d'une annulation qui ne peut plus se faire, dont le message vit dans l'`Alert.u.php` du
	 * package de l'intention.
	 */
	abstract public static function getCancelFail(): string;

	/**
	 * Le modèle qui porte la transaction de l'écriture et de l'annulation : toutes les tables écrites doivent vivre sur
	 * son serveur, les transactions s'empilant par serveur (`framework/core/Module.php`).
	 */
	abstract public static function getModel(): \ModuleModel;

}
?>
