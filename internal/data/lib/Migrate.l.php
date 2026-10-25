<?php
namespace data;

/**
 * Pour le lancer à la main : php framework/lime.php -a ouvretaferme -e prod data/migrate
 */
Class MigrateLib {

	const VERSION = 254;

	/**
	 * Le dernier doNN retiré : toute base de production l'avait joué quand les doNN jusqu'à lui ont quitté ce
	 * fichier. Une base restée en deçà n'a plus de quoi se migrer, elle se recharge.
	 */
	const FLOOR = 253;

	private static ?array $running = NULL;

	/**
	 * Les parts d'une ligne de frais dans la valeur des biens de sa facture (`invoicing\LineShare`, chantier
	 * normalisation-comptes-achats, I4), créées vides : aucune ligne ne se répartissait avant elles.
	 */
	public static function do254(\structure\Structure $eStructure, string $base, string $structureBase) {

		if(
			self::hasDatabase($structureBase) === FALSE
			or self::hasTable($structureBase, 'invoicingLineShare')
		) {
			return;
		}

		new \ModuleAdministration('invoicing\LineShare')->init();

	}

	public static function all(): void {

		$cStructure = \structure\StructureLib::getForMigrate();

		$i = 1;

		foreach($cStructure as $eStructure) {

			self::one($eStructure);

			$i++;

			if($i % 100 === 0) {
				echo $i."\r";
			}

		}

	}

	/**
	 * Rejoue doN et les suivants sur les bases de dev qui les ont déjà passés. `live()` ne compare qu'un
	 * entier : une base de dev qui a joué le doNN d'un arbre partagé avant qu'une collision le renumérote
	 * saute en silence celui qui prend sa place. Le rejeu ne vaut qu'en dev, la production ne voyant que
	 * l'ordre d'origin/main, et il est sans dommage parce que chaque doNN est idempotent
	 * (`MigrationIdempotentAi`). Une structure donnée borne le rejeu à sa base.
	 */
	public static function replay(int $from, \structure\Structure $eStructure = new \structure\Structure()): void {

		$rewound = self::rewind($from, $eStructure);

		echo $rewound.' structure(s) rewound to version '.(($from - 1) * 10)."\n";

		if($eStructure->notEmpty()) {
			self::one(\structure\StructureLib::getById($eStructure['id']));
		} else {
			self::all();
		}

	}

	/** Ramène sous doN les bases qui l'ont passé, et rend leur nombre. */
	public static function rewind(int $from, \structure\Structure $eStructure = new \structure\Structure()): int {

		if(LIME_ENV !== 'dev') {
			throw new \Exception('Replay rewinds dev databases only');
		}

		if($from <= self::FLOOR or $from > self::VERSION) {
			throw new \Exception('Replay starts between do'.(self::FLOOR + 1).' and do'.self::VERSION);
		}

		return \structure\StructureLib::rewindVersion(($from - 1) * 10, $eStructure);

	}

	public static function one(\structure\Structure $eStructure): void {

		\structure\StructureLib::connectDatabase($eStructure);

		echo 'Structure #'.$eStructure['id']."\n";
		self::live($eStructure);


	}

	public static function live(\structure\Structure $eStructure): void {

		// MySQL valide implicitement la transaction ouverte au premier DDL d'une migration : on attend la
		// prochaine connexion hors transaction, ou data/migrate.
		if(\structure\Structure::model()->pdo()->inTransaction()) {
			return;
		}

		if($eStructure['version'] % 10 !== 0) {
			return;
		}

		if($eStructure['version'] < self::FLOOR * 10) {
			self::raiseToFloor($eStructure);
		}

		for($version = $eStructure['version']; $version < self::VERSION * 10; $version += 10) {

			$newVersion = $version + 10;

			$method = 'do'.($newVersion / 10);

			if(method_exists('\data\MigrateLib', $method) === FALSE) {
				throw new \Exception('Missing method for version '.$newVersion);
			}

			if(
				\structure\Structure::model()
					->whereId($eStructure)
					->whereVersion($version)
					->update($eStructure, [
						'version' => $version + 5
					]) === 0
			) {
				return;
			}

			self::start($eStructure, $version);

			try {

				self::$method(
					$eStructure,
					(LIME_ENV === 'dev' ? 'dev_ouvretaferme' : 'ouvretaferme'),
					\structure\StructureSetting::getDatabaseName($eStructure)
				);

			} catch(\Throwable $e) {

				self::stop($eStructure);
				self::restoreVersion($eStructure, $version);

				throw $e;

			}

			self::stop($eStructure);

			\structure\Structure::model()
				->whereId($eStructure)
				->whereVersion($version + 5)
				->update($eStructure, [
					'version' => $newVersion
				]);

		}


	}

	/**
	 * Une structure sans base n'a rien à migrer : ses tables naîtront de leur `.yml`, dans l'état où les
	 * doNN retirés les auraient laissées. Une base en deçà du plancher, elle, attendrait des doNN qui
	 * n'existent plus.
	 */
	private static function raiseToFloor(\structure\Structure $eStructure): void {

		if(self::hasDatabase(\structure\StructureSetting::getDatabaseName($eStructure))) {
			throw new \Exception('Version '.$eStructure['version'].' of structure #'.$eStructure['id'].' is under the floor '.(self::FLOOR * 10).', whose migrations were removed: reload this database');
		}

		\structure\Structure::model()
			->whereId($eStructure)
			->whereVersion($eStructure['version'])
			->update($eStructure, [
				'version' => self::FLOOR * 10
			]);

		$eStructure['version'] = self::FLOOR * 10;

	}

	/**
	 * Une erreur fatale (classe incomplète, mémoire ou temps d'exécution épuisés) arrête le processus sans
	 * passer par le catch de `live()` : seule une fonction d'arrêt est encore appelée après elle. Elle rend
	 * la version de chaque doNN resté en cours, indexé par structure puisque `runAll()` en migre plusieurs
	 * dans le même processus.
	 */
	private static function start(\structure\Structure $eStructure, int $version): void {

		if(self::$running === NULL) {

			register_shutdown_function(function() {

				foreach(self::$running as ['eStructure' => $eStructure, 'version' => $version]) {
					self::restoreVersion($eStructure, $version);
				}

			});

			self::$running = [];

		}

		self::$running[$eStructure['id']] = [
			'eStructure' => $eStructure,
			'version' => $version
		];

	}

	private static function stop(\structure\Structure $eStructure): void {

		unset(self::$running[$eStructure['id']]);

	}

	/**
	 * Une version laissée en +5 ne migre plus jamais : le doNN interrompu rend la sienne et se rejoue à la
	 * connexion suivante, ce que MigrationIdempotentAi garantit sans dommage.
	 */
	private static function restoreVersion(\structure\Structure $eStructure, int $version): void {

		\structure\Structure::model()
			->whereId($eStructure)
			->whereVersion($version + 5)
			->update($eStructure, [
				'version' => $version
			]);

	}

	private static function sql($query): void {

		\structure\Structure::model()->pdo()->exec($query);

	}

	private static function hasColumn(string $base, string $table, string $column): bool {

		$database = \structure\Structure::model()->pdo();

		return $database->select('SHOW COLUMNS FROM '.$database->api->field($base).'.'.$database->api->field($table).' LIKE \''.$column.'\'')->fetch() !== FALSE;

	}

	private static function getColumnType(string $base, string $table, string $column): string {

		$database = \structure\Structure::model()->pdo();

		return $database->select('SHOW COLUMNS FROM '.$database->api->field($base).'.'.$database->api->field($table).' LIKE \''.$column.'\'')->fetch()['Type'];

	}

	private static function hasIndex(string $base, string $table, string $index): bool {

		$database = \structure\Structure::model()->pdo();

		return $database->select('SHOW INDEX FROM '.$database->api->field($base).'.'.$database->api->field($table).' WHERE Key_name = \''.$index.'\'')->fetch() !== FALSE;

	}

	private static function hasDatabase(string $base): bool {

		$database = \structure\Structure::model()->pdo();

		return $database->select('SHOW DATABASES LIKE \''.$base.'\'')->fetch() !== FALSE;

	}

	private static function countRows(string $base, string $table): int {

		$database = \structure\Structure::model()->pdo();

		return (int)$database->selectUnique('SELECT COUNT(*) FROM '.$database->api->field($base).'.'.$database->api->field($table));

	}

	private static function hasTable(string $base, string $table): bool {

		$database = \structure\Structure::model()->pdo();

		return $database->select('SHOW TABLES FROM '.$database->api->field($base).' LIKE \''.$table.'\'')->fetch() !== FALSE;

	}

}
