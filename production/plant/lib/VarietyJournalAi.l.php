<?php
namespace plant;

/**
 * Le journal des décisions prises sur le référentiel de variétés.
 *
 * L'état, c'est la base — `Variety` à structure NULL et `VarietyName`. Le journal
 * ne la double pas : il porte les décisions, une ligne chacune, pour qu'elles se
 * rejouent d'un environnement à l'autre. Arbitrer sur une copie de travail puis
 * appliquer en production n'a pas d'autre chemin.
 *
 * Ce n'est donc pas une sauvegarde et il ne prétend pas reconstruire le
 * référentiel à partir de rien : une base vierge se peuple par restauration, pas
 * en rejouant un fichier. Ce qu'il garantit, c'est qu'une décision prise quelque
 * part puisse être portée ailleurs, et relue dans un diff avant de l'être.
 *
 * Chaque ligne décrit un **état voulu**, jamais un geste :
 *
 *   ['reference', 'tomate', 'tomate-teton-venus', 'Teton de Venus']
 *       cette référence existe sur cette espèce et porte ce nom
 *
 *   ['graphie', 'tomate', 'tomate-teton-venus', 'Tomate tétons de vénus']
 *       ce nom saisi revient à cette référence
 *
 *   ['fusion', 'tomate', 'tomate-teton-venus-jaune', 'tomate-teton-venus']
 *       cette référence est repliée sur celle-là
 *
 *   ['retrait', 'tomate', 'tomate-teton-venus', 'Tétons de Vénus Yellow']
 *       ce nom ne revient plus à cette référence
 *
 *   ['detachement', 'tomate', 'tomate-teton-venus', 'Tétons de Vénus Yellow']
 *       les variétés de structure qui portent ce nom ne sont plus rattachées à
 *       cette référence, et ne doivent plus l'être
 *
 * C'est ce qui rend le journal rejouable sans registre : appliquer deux fois la
 * même ligne ne fait rien la seconde fois, et personne n'a à tenir la liste de ce
 * qui a déjà tourné.
 *
 * Le fichier ne se vide jamais.
 */
class VarietyJournalAiLib {

	const FILE = __DIR__.'/varietyJournalDataAi.php';

	const REFERENCE = 'reference';
	const GRAPHIE = 'graphie';
	const FUSION = 'fusion';
	const RETRAIT = 'retrait';
	const DETACHEMENT = 'detachement';

	private static array $cPlantReference = [];

	public static function get(): array {

		return require self::FILE;

	}

	/**
	 * La commande complète pour lancer une route, telle qu'on la tape.
	 *
	 * Les scripts se renvoient les uns aux autres dans leurs messages, et une route
	 * seule ne se copie pas dans un terminal. L'application et l'environnement sont
	 * ceux du passage en cours : ce qui s'affiche est donc la commande qui vaut
	 * ici, et non une forme générique qu'il faudrait adapter — c'est justement en
	 * l'adaptant qu'on se trompe d'environnement.
	 */
	public static function getCommand(string $route, string $arguments = ''): string {

		return 'php framework/lime.php -a '.LIME_APP.' -e '.LIME_ENV.' '.$route.($arguments === '' ? '' : ' '.$arguments);

	}

	/**
	 * L'espèce de référence que désigne un fqn d'espèce du journal.
	 *
	 * Ici plutôt que dans chaque script : les trois qui appliquent le journal ont
	 * besoin de la même résolution, et deux définitions qui divergeraient feraient
	 * qu'une ligne s'applique dans l'un et pas dans l'autre.
	 */
	public static function getPlantReference(string $plantFqn): Plant {

		// Une requête par ligne de journal, et le fichier ne se vide jamais : sans
		// cache, le coût de la migration croît avec l'historique des décisions alors
		// que les espèces, elles, restent 71. Le référentiel est commun à toutes les
		// structures — ce n'est pas du contenu de tenant —, une mémoïsation simple
		// est donc sans risque ici.
		self::$cPlantReference[$plantFqn] ??= Plant::model()
			->select(['id', 'fqn', 'name'])
			->whereStructure(NULL)
			->whereFqn($plantFqn)
			->get();

		return self::$cPlantReference[$plantFqn];

	}

	public static function getVarietyReference(Plant $ePlantReference, string $varietyFqn): Variety {

		return Variety::model()
			->select(['id', 'fqn', 'name'])
			->whereStructure(NULL)
			->wherePlant($ePlantReference)
			->whereFqn($varietyFqn)
			->get();

	}

	/**
	 * Ajoute des lignes à la suite, sans jamais toucher aux précédentes.
	 *
	 * Les doublons exacts sont écartés : une décision reprise à l'identique lors
	 * d'une seconde revue n'a pas à figurer deux fois. Deux lignes qui portent sur
	 * la même cible sans être identiques restent toutes les deux — c'est un
	 * changement d'avis, et l'ordre dit lequel gagne.
	 */
	public static function append(array $entries): int {

		if($entries === []) {
			return 0;
		}

		$journal = self::get();

		$known = [];

		foreach($journal as $entry) {
			$known[implode("\0", $entry)] = TRUE;
		}

		$added = [];

		foreach($entries as $entry) {

			$key = implode("\0", $entry);

			if(isset($known[$key])) {
				continue;
			}

			$known[$key] = TRUE;
			$added[] = $entry;

		}

		if($added === []) {
			return 0;
		}

		self::write(array_merge($journal, $added));

		return count($added);

	}

	/**
	 * Retire du journal toutes les lignes qui portent sur une référence, et rend
	 * leur nombre.
	 *
	 * Le journal ne s'efface pas d'ordinaire — une décision appliquée ailleurs ne se
	 * reprend pas en la faisant disparaître. Mais l'arbitrage se fait sur un seul
	 * environnement, et le journal n'en part qu'une fois commité : tant qu'il n'a
	 * pas voyagé, se raviser doit pouvoir effacer, sinon la revue laisse derrière
	 * elle l'ordre de créer ce qu'on vient d'écarter.
	 *
	 * L'appelant vérifie que la référence n'est pas déjà en base avant d'appeler :
	 * si elle y est, d'autres environnements l'ont peut-être reçue, et effacer ne
	 * les corrigerait pas.
	 */
	public static function removeReference(string $plantFqn, string $varietyFqn): int {

		$journal = self::get();

		$reste = array_values(array_filter(
			$journal,
			fn($entry) => ($entry[1] !== $plantFqn or $entry[2] !== $varietyFqn)
		));

		$retires = count($journal) - count($reste);

		if($retires > 0) {
			self::write($reste);
		}

		return $retires;

	}

	/**
	 * Remet le journal à zéro, en-tête compris.
	 *
	 * Réservé à plant/configure/variety/journalPurgeAi, qui ne l'appelle qu'après
	 * avoir vérifié que la base porte chaque ligne. Vider sans cette garde ferait
	 * disparaître des décisions que d'autres environnements n'ont pas reçues.
	 */
	public static function purge(): void {

		self::write([]);

	}

	private static function write(array $journal): void {

		$h = self::getHeader();
		$h .= "return [\n\n";

		// Les colonnes sont alignées sur la ligne la plus large, pas sur une valeur
		// fixe : c'est ce qui rend le fichier lisible en diagonale, et l'alignement ne
		// se recalcule que sur ce que le fichier contient déjà.
		$widths = [0, 0, 0];

		foreach($journal as $entry) {

			foreach([0, 1, 2] as $column) {
				$widths[$column] = max($widths[$column], mb_strlen(var_export($entry[$column], TRUE)));
			}

		}

		foreach($journal as $entry) {

			$h .= "\t[";

			foreach([0, 1, 2] as $column) {
				$h .= mb_str_pad(var_export($entry[$column], TRUE).',', $widths[$column] + 2);
			}

			$h .= var_export($entry[3], TRUE)."],\n";

		}

		file_put_contents(self::FILE, $h."\n];\n");

	}

	private static function getHeader(): string {

		$h = "<?php\n";
		$h .= "/**\n";
		$h .= " * Les décisions prises sur le référentiel de variétés, une ligne chacune.\n";
		$h .= " *\n";
		$h .= " * Écrit par plant/configure/variety/arbitrageAi, appliqué par\n";
		$h .= " * variety/referenceEnrichAi puis variety/nameEnrichAi. Ne se vide jamais :\n";
		$h .= " * c'est lui qui porte une décision d'un environnement à l'autre.\n";
		$h .= " *\n";
		$h .= " * Chaque ligne décrit un état voulu, pas un geste, et se rejoue donc sans\n";
		$h .= " * effet :\n";
		$h .= " *\n";
		$h .= " *   reference  <espèce> <fqn>              <nom retenu>\n";
		$h .= " *   graphie    <espèce> <fqn>              <nom saisi qui lui revient>\n";
		$h .= " *   fusion     <espèce> <fqn abandonné>    <fqn qui le remplace>\n";
		$h .= " *   retrait    <espèce> <fqn>              <nom saisi qui ne lui revient plus>\n";
		$h .= " *   detachement <espèce> <fqn>             <nom saisi à ne plus rattacher>\n";
		$h .= " */\n";

		return $h;

	}

}
?>
