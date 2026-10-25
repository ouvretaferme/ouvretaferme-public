<?php
namespace entry;

/**
 * La quantité : le premier nombre de la phrase, entier ou décimal, à la virgule comme au point. Précédé d'un
 * signe moins, « -12 », il retire du jour (`HarvestIntentionLib::resolve()`).
 *
 * Un second nombre n'est pas réclamé : il reste en attente et le moteur le refuse, plutôt que de
 * choisir entre deux quantités à la place de l'utilisateur.
 */
class QuantityComplementLib extends ComplementLib {

	public static function getIntentions(): array {
		return ['harvest'];
	}

	public static function read(array &$words, mixed &$value, ?int $typing, string $intention): void {

		$positions = array_keys($words);

		foreach($words as $position => $word) {

			// Tant que se tape l'unité qui le suit, le nombre n'est pas fini : « 0 k » n'est pas encore une quantité nulle.
			$next = $positions[array_search($position, $positions, TRUE) + 1] ?? NULL;
			$composing = ($position === $typing or ($next !== NULL and $next === $typing and preg_match('/^[a-z]+$/', $words[$next]) === 1));

			// « - » seul en cours de frappe : le début d'un retrait, que le chiffre suivant dira.
			if($word === '-' and $position === $typing) {
				unset($words[$position]);
				return;
			}

			// « 12, » en cours de frappe : la virgule attend sa décimale, le nombre vaut déjà 12.
			if(preg_match('/^(-?)([0-9]+)([.,]([0-9]*))?$/', $word, $match) === 0) {
				continue;
			}

			if(($match[4] ?? '') === '' and ($match[3] ?? '') !== '' and $composing === FALSE) {
				continue;
			}

			// La récolte se répartit au dixième (`TaskLib::buildHarvests()`, précision 1 de `distribute()`) :
			// « 12,24 » s'écrirait 12,2, donc la confirmation le dit dès la lecture.
			$number = round((float)($match[1].$match[2].'.'.($match[4] ?? '0')), 1);
			unset($words[$position]);

			// « 0 », « 0, », « -0,0 » en cours de frappe : le début de 0,5, pas encore une quantité nulle. `-0.0`
			// vaut `0.0` pour `===`.
			if($number === 0.0 and $composing) {
				return;
			}

			$value = $number;

			return;

		}

	}

}
?>
