<?php
namespace plant;

/**
 * Les graphies sous lesquelles les structures ont saisi une variété de référence.
 *
 * Le référentiel est arbitré à la main par plant/configure/variety/arbitrageAi, mais ses
 * décisions n'y servent qu'au moment du rattachement en masse. Cette table les
 * rend disponibles à l'exécution : c'est elle qui permet de reconnaître une
 * variété au moment où quelqu'un la tape, plutôt que de la laisser partir en
 * doublon et d'attendre le prochain passage du script.
 *
 * Reconnaître sur le seul nom de la référence ne suffirait pas — mesuré sur les
 * 46 792 rattachements existants, un quart n'y survivrait pas : « Potimarron Red
 * Kuri » ne ressemble pas à « Red Kuri », ni « Pongo (nain) » à « Pongo ». Ce sont
 * précisément les rapprochements que la revue a tranchés, et qui n'existent nulle
 * part ailleurs que dans les graphies.
 */
class VarietyNameAiLib extends VarietyNameCrud {

	/**
	 * La forme sur laquelle deux saisies se comparent.
	 *
	 * Volontairement stricte : les accents, la casse et la ponctuation tombent,
	 * rien d'autre. Ni mot ni suffixe n'est retiré — les variantes de vocabulaire
	 * (« Akito » et « Akito F1 ») sont déjà dans la table comme deux graphies de la
	 * même référence, il n'y a donc rien à deviner ici. Toute règle supplémentaire
	 * rapprocherait des variétés que la revue a délibérément laissées distinctes.
	 */
	public static function getSearchKey(string $name): string {

		return str_replace(' ', '', self::getWords($name));

	}

	/**
	 * La même forme, mots remis en ordre alphabétique.
	 *
	 * Elle rattrape les inversions que personne n'a encore écrites : le référentiel
	 * ne connaît que les graphies réellement saisies, si bien que « Verte Zuboda »
	 * est reconnue et « zuboda verte » ne l'est pas, alors que c'est la même
	 * semence. Trier les mots efface la différence.
	 *
	 * Elle ne sert qu'en repli, jamais avant la clé stricte, et le prix est connu :
	 * elle rend indiscernables sept paires de références que la revue a
	 * délibérément séparées — « Grenobloise » et « Rouge Grenobloise », « Verna » et
	 * « Verna Orange », « Verte d'Alger » et « Petite verte d'Alger ». Ces
	 * paires-là deviennent ambiguës, donc non rattachées : on ne rapproche rien à
	 * tort, on renonce seulement au repli là où il se tromperait.
	 */
	public static function getSortKey(string $name): string {

		$words = array_filter(explode(' ', self::getWords($name)));

		sort($words);

		return implode('', $words);

	}

	/**
	 * Les mots d'un nom, minuscules et sans accent, séparés par une espace.
	 */
	private static function getWords(string $name): string {

		$key = mb_strtolower(trim($name));

		$key = strtr($key, [
			'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ç' => 'c',
			'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
			'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o',
			'ù' => 'u', 'û' => 'u', 'ü' => 'u',
		]);

		return trim(preg_replace('/[^a-z0-9]+/', ' ', $key));

	}

	/**
	 * La variété de référence que désigne un nom saisi, s'il n'y a pas de doute.
	 *
	 * Le doute existe : cinq graphies du référentiel se réduisent à la même clé pour
	 * deux références différentes — « Matador Viking » vaut pour Matador comme pour
	 * Matador Viking. Elles ne sont pas rattachées, et c'est le bon comportement :
	 * poser un parent au hasard vaut moins que ne rien poser, puisque personne ne
	 * viendra vérifier.
	 *
	 * $ePlant est l'espèce de la structure ; c'est son fqn qui mène à l'espèce de
	 * référence, seul identifiant que les deux partagent.
	 */
	public static function getVarietyByName(Plant $ePlant, string $name): Variety {

		$searchKey = self::getSearchKey($name);

		if($searchKey === '') {
			return new Variety();
		}

		$ePlantReference = self::getPlantReference($ePlant);

		if($ePlantReference->empty()) {
			return new Variety();
		}

		$eVariety = self::getVarietyByKey($ePlantReference, 'searchKey', $searchKey);

		if($eVariety->notEmpty()) {
			return $eVariety;
		}

		// Repli sur l'ordre des mots, et seulement si la graphie exacte n'a rien
		// donné : ce qui est reconnu aujourd'hui doit continuer de l'être à
		// l'identique, le repli n'ajoute que ce qui n'était pas reconnu du tout.
		return self::getVarietyByKey($ePlantReference, 'sortKey', self::getSortKey($name));

	}

	/**
	 * L'espèce de référence qui correspond à l'espèce d'une structure, et dont
	 * pendent les variétés de référence.
	 *
	 * La sélection est refaite au besoin : la méthode est appelée depuis une
	 * construction d'élément, où l'espèce arrive avec la sélection qu'a faite
	 * l'appelant.
	 */
	public static function getPlantReference(Plant $ePlant): Plant {

		if($ePlant->empty()) {
			return new Plant();
		}

		if($ePlant->offsetExists('reference') === FALSE or $ePlant->offsetExists('structure') === FALSE) {

			Plant::model()
				->select(['reference', 'structure'])
				->get($ePlant);

		}

		// Une espèce du catalogue de base est sa propre référence.
		return $ePlant['structure']->empty() ? $ePlant : $ePlant['reference'];

	}

	/**
	 * La référence désignée par une clé, à condition qu'elle soit seule à l'être.
	 *
	 * Indexé par variété, et c'est tout l'enjeu : une même clé porte presque
	 * toujours plusieurs graphies — « Verte Zuboda », « Verte - Zuboda » et
	 * « verte ZUBODA » n'en font qu'une une fois normalisées. Compter les lignes
	 * ferait passer pour ambiguës les 2 906 clés (sur 8 307) qui ne font que
	 * répéter la même réponse.
	 */
	private static function getVarietyByKey(Plant $ePlantReference, string $property, string $key): Variety {

		if($key === '') {
			return new Variety();
		}

		$cVarietyName = VarietyName::model()
			->select(['variety' => ['id', 'name', 'fqn']])
			->wherePlant($ePlantReference)
			->where($property, $key)
			->getCollection(index: 'variety');

		if($cVarietyName->count() !== 1) {
			return new Variety();
		}

		return $cVarietyName->first()['variety'];

	}

	/**
	 * Les variétés de référence d'une espèce dont une graphie contient ce qui est
	 * cherché, pour l'autocomplétion.
	 *
	 * On cherche dans les graphies et non dans les seuls noms retenus : c'est ce que
	 * les structures écrivent réellement qui doit ramener la variété. Taper
	 * « potimarron » ramène ainsi Red Kuri et Fictor, que leur nom retenu ne
	 * désigne pas.
	 *
	 * Une même variété ressort par plusieurs de ses graphies : la collection est donc
	 * indexée par variété, faute de quoi « butter » proposerait quatre fois Butternut
	 * Waltham et rien d'autre.
	 *
	 * $ePlant est l'espèce de la structure, pas celle de référence : l'appelant n'a
	 * que la sienne sous la main.
	 */
	public static function query(Plant $ePlant, string $search, int $limit): \Collection {

		$ePlantReference = self::getPlantReference($ePlant);

		if($ePlantReference->empty()) {
			return new \Collection();
		}

		$searchKey = self::getSearchKey($search);

		// Rien de tapé : c'est l'ouverture du champ, on montre ce qui se cultive le
		// plus plutôt qu'une liste alphabétique où l'on ne choisit pas.
		if($searchKey === '') {
			return self::getPopular($ePlantReference, $limit);
		}

		// La limite SQL porte sur les graphies, pas sur les variétés : on en tire
		// large pour que la déduplication ait de quoi remplir la liste, une variété
		// pouvant à elle seule aligner une dizaine de graphies.
		$cVarietyName = VarietyName::model()
			->select(['variety' => Variety::getSelection()])
			->wherePlant($ePlantReference)
			->whereSearchKey('LIKE', '%'.$searchKey.'%')
			->sort(['searchKey' => SORT_ASC])
			->getCollection(0, $limit * PlantSetting::VARIETY_QUERY_GRAPHIES, index: 'variety');

		$cVariety = $cVarietyName->getColumnCollection('variety');

		// Tronquée seulement s'il y a de quoi : Collection::slice() boucle jusqu'à la
		// longueur demandée sans regarder la taille réelle, et lève une exception dès
		// que la collection est plus courte.
		return ($cVariety->count() > $limit) ? $cVariety->slice(0, $limit) : $cVariety;

	}

	/**
	 * Les variétés de référence d'une espèce les plus cultivées.
	 *
	 * Le classement compte les structures et non les variétés rattachées : depuis
	 * que plusieurs variétés d'une même structure peuvent pointer la même référence
	 * — deux lots d'une même semence —, les compter ferait passer pour répandue une
	 * variété que deux structures suivent en quatre exemplaires.
	 */
	public static function getPopular(Plant $ePlantReference, int $limit): \Collection {

		$referenceIds = Variety::model()
			->select(['id'])
			->whereStructure(NULL)
			->wherePlant($ePlantReference)
			->getCollection()
			->getIds();

		if($referenceIds === []) {
			return new \Collection();
		}

		$cUsage = Variety::model()
			->select([
				'reference' => Variety::getSelection(),
				'structures' => new \Sql('COUNT(DISTINCT structure)', 'int'),
			])
			->whereReference('IN', $referenceIds)
			->whereStructure('IN', \structure\StructureLib::getInstalledSql())
			->group(['reference'])
			->sort(new \Sql('structures DESC, reference ASC'))
			->getCollection(0, $limit);

		$cVariety = $cUsage->getColumnCollection('reference');

		// Le classement sert à choisir lesquelles montrer, pas dans quel ordre les
		// lire : on rend l'alphabet, où l'œil retrouve un nom qu'il cherche. Et
		// l'effectif ne suit pas jusqu'à l'écran, il n'aiderait pas à choisir.
		$cVariety->sort(fn(Variety $e1, Variety $e2) => \L::getCollator()->compare($e1['name'], $e2['name']));

		return $cVariety;

	}

}
?>
