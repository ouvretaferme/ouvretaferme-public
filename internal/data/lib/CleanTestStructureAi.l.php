<?php
namespace data;

/**
 * La mécanique du nettoyage des structures de test (`data/cleanTestStructureAi`).
 *
 * Tout tient à la carte des rattachements, et cette carte se dérive du code : chaque module déclare
 * dans `getPropertiesToElement()` les colonnes qui portent un élément d'un autre module. La cascade
 * la parcourt en largeur depuis la table `structure` et s'arrête quand une passe n'ajoute plus rien,
 * ce qui la rend juste par construction plutôt que par inventaire.
 *
 * Elle ne descend jamais que du parent vers l'enfant, c'est-à-dire d'une ligne supprimée vers les
 * lignes qui la désignent. Une ligne partagée, un utilisateur ou une variété du référentiel, n'est
 * donc jamais emportée : elle ne désigne aucune structure.
 *
 * Les paquets de structure sont hors sujet ici, leurs tables vivant dans `structure_<id>`, que le
 * nettoyage supprime d'un `DROP DATABASE`. La liste vient de `StructureSetting`, qui évolue.
 */
class CleanTestStructureAiLib {

	/** Une cascade qui n'a pas convergé en dix passes signale un cycle, pas une base profonde. */
	private const PASSES = 10;

	/**
	 * Les lignes à supprimer, par table, et le nombre de passes qu'il a fallu.
	 *
	 * @return array{array<string, int[]>, int}
	 */
	public static function collect(string $database, array $structureIds): array {

		$models = self::getModels();
		$tables = self::getTables($database, array_keys($models));

		$rows = ['structure' => $structureIds];

		$pdo = \structure\Structure::model()->pdo();

		for($pass = 1; $pass <= self::PASSES; $pass++) {

			$added = 0;

			foreach($models as $table => $model) {

				if(in_array($table, $tables) === FALSE) {
					continue;
				}

				// Sans colonne id, rien ne peut désigner cette table : ses lignes se suppriment, elles
				// ne se propagent pas. Le cas n'existe pas aujourd'hui, la garde le dirait.
				if(in_array('id', $model->getProperties()) === FALSE) {
					continue;
				}

				foreach($model->getPropertiesToElement() as $property => $module) {

					$parent = $models[self::getTableOfModule($models, $module)] ?? NULL;

					if($parent === NULL) {
						continue;
					}

					$parentTable = $parent->getTable();

					if(($rows[$parentTable] ?? []) === []) {
						continue;
					}

					$known = $rows[$table] ?? [];

					$sql = 'SELECT id FROM '.$pdo->api->field($database).'.'.$pdo->api->field($table)
						.' WHERE '.$pdo->api->field($property).' IN ('.implode(', ', $rows[$parentTable]).')';

					if($known !== []) {
						$sql .= ' AND id NOT IN ('.implode(', ', $known).')';
					}

					$found = array_map('intval', $pdo->select($sql)->fetchAll(\PDO::FETCH_COLUMN));

					if($found === []) {
						continue;
					}

					$rows[$table] = array_merge($known, $found);
					$added += count($found);

				}

			}

			if($added === 0) {
				return [$rows, $pass];
			}

		}

		throw new \Exception('La cascade n\'a pas convergé en '.self::PASSES.' passes.');

	}

	/**
	 * Les structures qu'un chantier ouvert cite encore, par leur nom, dans `.claude/state/`.
	 *
	 * C'est la seule protection qui vaille, et elle remplace le filtre par la date, qui ne protégeait
	 * rien : un décor de recette survit à la recette qu'il sert, parfois de plusieurs jours, le temps
	 * qu'on la lise. Ce qui distingue un décor vivant d'un décor mort n'est pas son âge, c'est
	 * qu'un lot non clos s'y appuie encore, et cela s'écrit dans le dossier d'état du chantier,
	 * jamais dans la base.
	 *
	 * Le croisement se fait sur le nom et non sur l'id, qui se confondrait avec n'importe quel nombre.
	 * Un nom de décor est distinctif par construction, puisqu'il commence par `zz`. Un faux positif
	 * protège une structure de trop, ce qui est le bon sens de l'erreur.
	 *
	 * @return array<int, string> le nom, par id de structure
	 */
	public static function getReserved(array $cStructure): array {

		$patterns = tempnam(sys_get_temp_dir(), 'clean');

		file_put_contents($patterns, implode("\n", array_column($cStructure, 'name'))."\n");

		$output = [];

		// Une seule passe par arborescence : un grep par nom coûterait 78 parcours de 900 Mo.
		if(is_dir('.claude/state')) {
			exec('grep -rhoF -f '.escapeshellarg($patterns).' .claude/state 2>/dev/null', $output);
		}

		// Le dépôt compte autant que les dossiers d'état, et il attrape ce qu'ils ne voient pas
		// encore : un décor semé par une fixture ou par un script de `page/configure/` porte son nom
		// en clair dans le fichier qui le sème, avant qu'aucune page de recette ne le cite.
		exec(
			'grep -rhoF -f '.escapeshellarg($patterns)
			.' --include=\'*.php\' --include=\'*.md\' --include=\'*.py\' --include=\'*.js\' --include=\'*.html\''
			.' --exclude-dir=.git --exclude-dir=node_modules . 2>/dev/null',
			$output
		);

		unlink($patterns);

		$cited = array_flip($output);
		$reserved = [];

		foreach($cStructure as $structure) {

			if(isset($cited[$structure['name']])) {
				$reserved[(int)$structure['id']] = $structure['name'];
			}

		}

		return $reserved;

	}

	/**
	 * Prouve que la cascade n'a pas dérivé, avant qu'on écrive.
	 *
	 * Toute table qui porte elle-même une colonne `structure` donne la réponse sans passer par la
	 * cascade : aucune de ses lignes relevées ne doit désigner une structure hors de la liste. Le
	 * contrôle a du poids parce qu'il couvre la moitié des tables et les plus grosses, et qu'il sait
	 * échouer : ajouter un id étranger à la liste passée le fait nommer sa table.
	 *
	 * Les tables sans cette colonne ne s'y prêtent pas, tout le paquet `amap` en tête, dont la clé
	 * vers la structure s'appelle `amap`. Elles sont comptées dans le rendu pour que le silence sur
	 * elles se voie.
	 *
	 * @return array{string[], int, int} les écarts, les tables contrôlées, les tables sans colonne
	 */
	public static function check(string $database, array $rows, array $structureIds): array {

		$models = self::getModels();
		$pdo = \structure\Structure::model()->pdo();

		$gaps = [];
		$checked = 0;
		$skipped = 0;

		foreach($rows as $table => $ids) {

			$model = $models[$table] ?? NULL;

			if($table === 'structure') {
				continue;
			}

			if($model === NULL or $model->getPropertyToModule('structure') !== 'structure\Structure') {
				$skipped++;
				continue;
			}

			$checked++;

			foreach(array_chunk($ids, 1000) as $chunk) {

				$count = (int)$pdo->selectUnique(
					'SELECT COUNT(*) FROM '.$pdo->api->field($database).'.'.$pdo->api->field($table)
					.' WHERE id IN ('.implode(', ', $chunk).')'
					.' AND structure NOT IN ('.implode(', ', $structureIds).')'
				);

				if($count > 0) {
					$gaps[] = $table.' : '.$count.' ligne(s) hors des structures visées';
					break;
				}

			}

		}

		return [$gaps, $checked, $skipped];

	}

	/**
	 * Les bases `structure_<id>` qui existent pour ces structures.
	 *
	 * @return string[]
	 */
	public static function getDatabases(array $structureIds): array {

		$existing = self::getStructureDatabases();
		$bases = [];

		foreach($structureIds as $structureId) {

			$base = \structure\StructureSetting::getDatabaseName(new \structure\Structure(['id' => $structureId]));

			if(in_array($base, $existing)) {
				$bases[] = $base;
			}

		}

		return $bases;

	}

	/**
	 * Les bases de structure dont plus aucune ligne ne porte l'id.
	 *
	 * @return string[]
	 */
	public static function getOrphanDatabases(): array {

		$pdo = \structure\Structure::model()->pdo();
		$database = \structure\Structure::model()->getDatabase();

		$ids = array_map('intval', $pdo
			->select('SELECT id FROM '.$pdo->api->field($database).'.'.$pdo->api->field('structure'))
			->fetchAll(\PDO::FETCH_COLUMN));

		$orphans = [];

		foreach(self::getStructureDatabases() as $base) {

			if(preg_match('/structure_(\d+)$/', $base, $match) !== 1) {
				continue;
			}

			if(in_array((int)$match[1], $ids) === FALSE) {
				$orphans[] = $base;
			}

		}

		return $orphans;

	}

	/**
	 * Supprime les lignes relevées, les enfants avant leurs parents.
	 *
	 * L'ordre est celui de la cascade inversé : aucune contrainte d'intégrité ne l'impose en base,
	 * mais un arrêt en cours de route laisse alors des parents sans enfants plutôt que l'inverse,
	 * c'est-à-dire un état que la relance du script sait encore lire.
	 */
	public static function delete(string $database, array $rows): int {

		$pdo = \structure\Structure::model()->pdo();
		$deleted = 0;

		foreach(array_reverse($rows, TRUE) as $table => $ids) {

			foreach(array_chunk($ids, 1000) as $chunk) {

				$pdo->exec('DELETE FROM '.$pdo->api->field($database).'.'.$pdo->api->field($table)
					.' WHERE id IN ('.implode(', ', $chunk).')');

				$deleted += count($chunk);

			}

		}

		return $deleted;

	}

	public static function dropDatabases(array $bases): int {

		$pdo = \structure\Structure::model()->pdo();

		foreach($bases as $base) {
			$pdo->exec('DROP DATABASE IF EXISTS '.$pdo->api->field($base));
		}

		return count($bases);

	}

	/**
	 * Les modules de la base commune, par nom de table.
	 *
	 * @return \ModuleModel[]
	 */
	private static function getModels(): array {

		$packagesStructure = array_merge(
			\structure\StructureSetting::getStartPackages(),
			\structure\StructureSetting::getAccountingPackages()
		);

		$models = [];

		foreach(glob('*/*/module/*.m.php') as $file) {

			if(preg_match('#^[^/]+/([^/]+)/module/([A-Za-z0-9]+)\.m\.php$#', $file, $match) !== 1) {
				continue;
			}

			[, $package, $entity] = $match;

			if(in_array($package, $packagesStructure)) {
				continue;
			}

			$class = '\\'.$package.'\\'.$entity;

			if(class_exists($class) === FALSE or method_exists($class, 'model') === FALSE) {
				continue;
			}

			$model = $class::model();
			$models[$model->getTable()] = $model;

		}

		return $models;

	}

	private static function getTableOfModule(array $models, string $module): ?string {

		foreach($models as $table => $model) {

			if($model->getModule() === $module) {
				return $table;
			}

		}

		return NULL;

	}

	/**
	 * Les tables réellement installées, lues en une requête : une base de dev peut être en retard sur
	 * un module que le dépôt vient d'ajouter.
	 *
	 * @return string[]
	 */
	private static function getTables(string $database, array $tables): array {

		$pdo = \structure\Structure::model()->pdo();

		return $pdo
			->select('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '.$pdo->quote($database))
			->fetchAll(\PDO::FETCH_COLUMN);

	}

	/**
	 * @return string[]
	 */
	private static function getStructureDatabases(): array {

		$pdo = \structure\Structure::model()->pdo();
		$prefix = \structure\StructureSetting::getDatabaseName(new \structure\Structure(['id' => 0]));

		return $pdo
			->select('SHOW DATABASES LIKE '.$pdo->quote(substr($prefix, 0, -1).'%'))
			->fetchAll(\PDO::FETCH_COLUMN);

	}

}
?>
