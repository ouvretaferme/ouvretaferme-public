<?php
namespace plant;

/**
 * Ce que la revue du référentiel a tranché sur le regroupement des noms.
 *
 * Les variétés de référence et leurs graphies vivent en base — `Variety` à
 * structure NULL et `VarietyName`. Restent ces décisions-ci, qui ne portent pas
 * sur les variétés mais sur la façon dont l'analyse les fait émerger des noms
 * saisis : quels mots structurent une espèce, quels rapprochements ont été
 * acceptés, lesquels refusés, quelles grappes écartées.
 *
 * Elles n'ont pas leur place en base. Rien ne les lit à l'exécution, elles ne
 * servent qu'à plant/configure/variety/arbitrageAi, et elles sont indexées par clés
 * de grappe — des formes normalisées qui dépendent de l'algorithme du jour, pas
 * des identifiants stables. Un fichier les garde relisibles et, surtout, les
 * garde dans git : un arbitrage modifié se relit dans un diff, ce qu'une ligne de
 * table ne permet pas.
 */
class PlantMappingAiLib {

	const FILE = __DIR__.'/plantMappingDataAi.php';

	/**
	 * Le fichier est relu une fois par processus. La donnée est commune à toutes
	 * les structures — c'est le référentiel partagé, pas du contenu de tenant —,
	 * une mémoïsation simple est donc sans risque ici.
	 */
	private static ?array $mapping = NULL;

	public static function get(): array {

		self::$mapping ??= require self::FILE;

		return self::$mapping;

	}

	/**
	 * Les décisions prises sur une espèce, complètes même quand le fichier n'en
	 * porte aucune : l'appelant travaille toujours sur les quatre listes.
	 */
	public static function getByPlant(string $plantFqn): array {

		$mapping = self::get()[$plantFqn] ?? [];

		return [
			'keywords' => $mapping['keywords'] ?? [],
			'merged' => $mapping['merged'] ?? [],
			'rejected' => $mapping['rejected'] ?? [],
			'ignored' => $mapping['ignored'] ?? [],
		];

	}

	public static function save(array $mapping): void {

		ksort($mapping);

		$h = self::getHeader();
		$h .= "return [\n\n";

		foreach($mapping as $plantFqn => $plant) {

			$h .= "\t".var_export($plantFqn, TRUE)." => [\n";

			// Les mots-clés gardent l'ordre où ils ont été validés, qui dit lesquels
			// structurent l'espèce ; les listes d'arbitrages n'en ont pas et se lisent
			// triées.
			$h .= "\t\t'keywords' => ".self::exportList($plant['keywords'] ?? [], 2, sort: FALSE).",\n";
			$h .= "\t\t'merged' => ".self::exportList($plant['merged'] ?? [], 2).",\n";
			$h .= "\t\t'rejected' => ".self::exportList($plant['rejected'] ?? [], 2).",\n";
			$h .= "\t\t'ignored' => ".self::exportList($plant['ignored'] ?? [], 2).",\n";
			$h .= "\t],\n\n";

		}

		file_put_contents(self::FILE, $h."];\n");

		self::$mapping = $mapping;

	}

	private static function exportList(array $values, int $depth, bool $sort = TRUE): string {

		$values = array_values(array_unique($values));

		if($values === []) {
			return '[]';
		}

		if($sort) {
			sort($values);
		}

		$tabs = str_repeat("\t", $depth);

		$h = "[\n";

		foreach($values as $value) {
			$h .= $tabs."\t".var_export($value, TRUE).",\n";
		}

		return $h.$tabs.']';

	}

	private static function getHeader(): string {

		$h = "<?php\n";
		$h .= "/**\n";
		$h .= " * Ce que la revue du référentiel a tranché sur le regroupement des noms, espèce\n";
		$h .= " * par espèce.\n";
		$h .= " *\n";
		$h .= " * Lu et écrit par plant\\PlantMappingAiLib, jamais en direct.\n";
		$h .= " *\n";
		$h .= " * Ces décisions ne portent pas sur les variétés — celles-là vivent en base — mais\n";
		$h .= " * sur la façon dont l'analyse les fait émerger des noms saisis. Elles n'ont donc\n";
		$h .= " * de sens que pour plant/configure/variety/arbitrageAi, et rien ne les lit à\n";
		$h .= " * l'exécution.\n";
		$h .= " *\n";
		$h .= " * 'keywords' décrit l'espèce du point de vue de qui cherche une variété : les\n";
		$h .= " * types, les ports et les couleurs par lesquels on la parcourt, et non les noms\n";
		$h .= " * de cultivars. Conservés dans l'ordre où ils ont été validés, du plus\n";
		$h .= " * structurant au plus anecdotique.\n";
		$h .= " *\n";
		$h .= " * 'merged' et 'rejected' gardent la trace des rapprochements arbitrés dans un\n";
		$h .= " * sens et dans l'autre, 'ignored' des grappes écartées du référentiel — sans\n";
		$h .= " * quoi la revue les reproposerait indéfiniment. Ce sont des clés de grappe, donc\n";
		$h .= " * des formes normalisées : elles dépendent de l'analyse du jour, et la revue\n";
		$h .= " * signale celles qu'elle ne produit plus.\n";
		$h .= " */\n";

		return $h;

	}

}
?>
