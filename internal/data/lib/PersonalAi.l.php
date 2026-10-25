<?php
namespace data;

/**
 * Ce qu'un script de diagnostic imprime à la place d'une donnée qui peut nommer quelqu'un : un
 * libellé bancaire, un compte auxiliaire, un corps de réponse d'API.
 *
 * Claude lit la sortie de ces scripts, et rien de nominatif ne doit l'atteindre (chantier
 * données locales, lot 4). Une valeur masquée garde ce qui sert à un diagnostic :
 * - une empreinte, identique pour deux valeurs identiques, qui montre que douze opérations portent
 *   le même libellé sans le lire ;
 * - les mots techniques d'une liste fermée (`TOKENS`), qui suffisent le plus souvent à expliquer un
 *   rapprochement : un virement SumUp, un prélèvement, une remise de chèques.
 *
 * L'empreinte n'est pas salée : elle ne se compare qu'à l'intérieur d'une même sortie, et un nom
 * court se retrouverait en essayant tous les noms. Elle protège de la lecture, pas d'une attaque.
 */
class PersonalAiLib {

	/**
	 * Mots qu'une banque, une PA ou un prestataire de paiement pose dans un libellé, et qui ne
	 * désignent personne. Un mot n'est reconnu qu'entier, pour que `CB` ne se lise pas dans un nom
	 * qui le contient.
	 */
	const TOKENS = [
		'AVOIR', 'CARTE', 'CB', 'CHQ', 'CHEQUE', 'COMMISSION', 'COTIS', 'COTISATION', 'DAB', 'ECH',
		'ESP', 'ESPECES', 'FACT', 'FACTURE', 'FRAIS', 'INTERETS', 'PAYPAL', 'PRLV', 'PRELEVEMENT',
		'REMB', 'REMBOURSEMENT', 'REMISE', 'RETRAIT', 'SEPA', 'STRIPE', 'SUMUP', 'TPE', 'VERSEMENT',
		'VIR', 'VIREMENT',
	];

	public static function hide(?string $value): string {

		if($value === NULL or $value === '') {
			return '-';
		}

		$tokens = self::getTokens($value);

		return self::getFingerprint($value).($tokens ? ' '.implode(' ', $tokens) : '');

	}

	/**
	 * Un numéro de compte garde ses chiffres de tête, qui disent sa classe ; la suite, qu'une
	 * structure forme souvent sur le nom d'un tiers (« 401DUPONT »), passe en empreinte. Même
	 * découpe que la règle `ACCOUNT` de `AnonymizeAiSetting`.
	 */
	public static function hideAccount(?string $value): string {

		if($value === NULL or $value === '') {
			return '-';
		}

		preg_match('/^[0-9]*/', $value, $match);

		$digits = $match[0];
		$rest = substr($value, strlen($digits));

		return $digits.($rest === '' ? '' : '~'.self::getFingerprint($rest));

	}

	/**
	 * Un corps d'API dont seules les clés de `$keysKept` gardent leur valeur texte, à n'importe quelle
	 * profondeur ; les nombres et les booléens restent, ils ne nomment personne.
	 */
	public static function hideJson(mixed $value, array $keysKept): mixed {

		return self::hideJsonValue($value, $keysKept, NULL);

	}

	private static function hideJsonValue(mixed $value, array $keysKept, int|string|null $key): mixed {

		if(is_array($value)) {

			$hidden = [];

			foreach($value as $childKey => $child) {
				$hidden[$childKey] = self::hideJsonValue($child, $keysKept, is_int($childKey) ? $key : $childKey);
			}

			return $hidden;

		}

		if(is_string($value) and in_array($key, $keysKept, TRUE) === FALSE) {
			return self::hide($value);
		}

		return $value;

	}

	public static function getFingerprint(string $value): string {

		return '#'.substr(sha1(mb_strtoupper($value)), 0, 8);

	}

	public static function getTokens(string $value): array {

		$words = explode('-', strtoupper(toFqn($value)));

		return array_values(array_unique(array_intersect($words, self::TOKENS)));

	}

}
?>
