<?php
namespace plant;

/**
 * Les mots qui accompagnent un nom de variété sans l'identifier.
 *
 * Trois familles, arbitrées à la main : ce qui décrit la production, ce qui lie
 * les mots entre eux, et ce qui annote — semencier, conditionnement, note de
 * commande.
 *
 * Ici plutôt que dans l'un des scripts parce que deux d'entre eux en ont besoin
 * et n'en tirent pas la même conclusion : le rattrapage s'en sert pour
 * **reconnaître** un nom malgré ces mots, la moisson pour **écarter** une graphie
 * qu'ils suffisent à ramener à une graphie connue. Deux listes qui divergeraient
 * feraient enregistrer comme nouvelle une graphie que l'autre sait déjà lire.
 *
 * Rien de ce qui décrit la variété n'y entre — ni couleur, ni type, ni saison.
 */
class VarietyVocabularyAiLib {


	/**
	 * Ce qui décrit la façon de produire ou de certifier, jamais l'identité de la
	 * variété.
	 */
	const NOISE = ['f1', 'f-1', 'bio', 'ab', 'hybride', 'greffe', 'greffee', 'greffees', 'greffes', 'plant', 'plants', 'semis', 'graines', 'semences'];

	/**
	 * Mots de liaison. Ils ne distinguent jamais deux variétés — « Coeur de boeuf »
	 * et « Coeur boeuf » sont la même — et une graphie arbitrée peut les porter ou
	 * non.
	 */
	const LINKS = ['de', 'du', 'des', 'la', 'le', 'les', 'et', 'ou', 'aux', 'di', 'da'];

	/**
	 * Ce qui accompagne un nom sans en faire partie : le semencier, le
	 * conditionnement, la note de commande.
	 *
	 * Arbitré à la main, une fois, en regardant les noms — pas déduit. La frontière
	 * ne se lit pas dans un classement : « pep » et « rge » se ressemblent, l'un
	 * abrège un semencier et l'autre abrège « rouge », qui distingue deux variétés.
	 * Ce sont ces mots-là, et pas d'autres, qui bloquaient le plus de rattachements
	 * en étant seuls à les bloquer.
	 *
	 * Rien de ce qui décrit la variété n'y entre, et surtout pas les couleurs, les
	 * types ni les saisons : le référentiel a délibérément séparé « Rouge
	 * Grenobloise » de « Grenobloise » et « Violette de Toulouse » de « Toulouse ».
	 * Les vérifier un par un se fait avec word=<mot>.
	 */
	const ANNOTATIONS = [
		// Semenciers, en toutes lettres et abrégés.
		'agrosemens', 'agr', 'sativa', 'sat', 'essem', 'rov', 'pep',
		// Conditionnement et conduite : la taille de motte, la graine enrobée, la
		// vente en botte, le plein champ.
		'motte', 'mottes', 'm4', 'enrobee', 'enrobees', 'botte', 'pc',
		// Notes de commande laissées dans le nom.
		'vente',
		// Troncatures de mots déjà écartés ailleurs : le nom est coupé dans la saisie.
		'greff', 'gref', 'agros',
		// Étape de production, race locale, et l'espèce abrégée par qui la saisit.
		'pepiniere', 'race', 'pdt',
		// Kultursaat.
		'ks',
	];

	/**
	 * Les mots qu'un nom peut porter sans cesser de désigner sa variété, pour une
	 * espèce donnée : les trois familles, plus l'espèce elle-même.
	 *
	 * L'espèce y entre par son fqn et par son nom, ce qui neutralise aussi les mots
	 * qui la qualifient — « rouge » sur chou-rouge, « rave » sur celeri-rave. C'est
	 * voulu : ces mots ne distinguent rien **à l'intérieur** de leur espèce, tous les
	 * choux rouges étant rouges. La même couleur reste bloquante partout ailleurs.
	 */
	public static function getNeutral(Plant $ePlantReference): array {

		$words = array_merge(
			explode('-', $ePlantReference['fqn']),
			self::getWords($ePlantReference['name']),
			self::NOISE,
			self::LINKS,
			self::ANNOTATIONS
		);

		return array_fill_keys($words, TRUE);

	}

	/**
	 * Les mots d'un nom, minuscules, sans accent ni ponctuation.
	 *
	 * Même normalisation que VarietyNameAiLib::getSearchKey(), dont c'est la
	 * définition de référence — elle y est privée.
	 *
	 * Rien n'est écarté, pas même un jeton d'un seul caractère : les écarter
	 * revenait à confondre « Kagraner Sommer » avec « Kagraner Sommer 2 », or c'est
	 * précisément ce que le référentiel sépare.
	 */
	public static function getWords(string $name): array {

		$key = mb_strtolower(trim($name));

		$key = strtr($key, [
			'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ç' => 'c',
			'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
			'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o',
			'ù' => 'u', 'û' => 'u', 'ü' => 'u',
		]);

		$words = explode(' ', trim(preg_replace('/[^a-z0-9]+/', ' ', $key)));

		return array_values(array_unique(array_filter($words)));

	}

	/**
	 * Un mot en trop qui n'empêche pas de conclure.
	 *
	 * Les nombres à deux chiffres et plus sont des quantités ou des calibres. Un
	 * chiffre seul, non : c'est un numéro de cultivar, et « Imanis 2 » n'est pas
	 * « Imanis ». Même prudence pour une lettre isolée.
	 */
	public static function isNeutral(string $word, array $neutral): bool {

		if(isset($neutral[$word])) {
			return TRUE;
		}

		return (ctype_digit($word) and (int)$word >= 10);

	}

}
?>
