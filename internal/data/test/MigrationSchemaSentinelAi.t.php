<?php
namespace data;

/**
 * Inventaire des écarts entre le schéma déclaré et la base commune réellement installée.
 *
 * Le contrat qu'elle tient : **un `.yml` ne part jamais sans sa migration** (`CLAUDE.md`, section
 * « Schémas `.yml` »). Les bases de structure se migrent seules à chaque connexion par les `doNN` de
 * `MigrateLib` ; la base commune n'a aucun mécanisme, et c'est là que vivent `shop`, `selling`,
 * `product`, `amap`, `quotation`, `structure`, `series`, `plant`, `payment`.
 *
 * Ce qu'elle rend possible, et qui n'existait pas : celui qui tire le code apprend en une commande
 * que sa base est en retard, **et quel fichier jouer**. Sans elle, il voit des tests rouges qu'il ne
 * peut rattacher à rien — deux jours perdus le 2026-09-17, mesurés, pour une colonne.
 *
 * ⚠️ **Elle ne protège pas l'auteur du schéma.** Sa base à lui est déjà migrée quand il commite,
 * donc elle est verte chez lui même s'il n'a écrit aucun fichier. C'est la porte de `/push` qui tient
 * ce côté-là. Les deux sont nécessaires et ne se remplacent pas.
 *
 * Lancement : dev/test package=data file=MigrationSchemaSentinelAi
 */
class MigrationSchemaSentinelAiTest extends \Test {

	/**
	 * Les écarts antérieurs à cette sentinelle, avec la raison de chacun.
	 *
	 * ⚠️ **Cette déclaration ne se compare pas dans les deux sens**, contrairement à celles des
	 * sentinelles de `framework/dev/test/`. Elle ne décrit pas le code, qui est le même partout, mais
	 * l'état d'une base, qui diffère d'une machine à l'autre : une entrée absente ici veut seulement
	 * dire que cette machine-là a déjà joué la migration, ce qui n'est pas une dette à résorber.
	 * Elle ne peut donc qu'écarter un écart présent, jamais en exiger un.
	 *
	 * Un écart ne s'ajoute ici que s'il est antérieur au 2026-09-17. Tout ce qui arrive après passe
	 * par `migration/`, qui est la seule réponse à la question « que dois-je jouer ».
	 */
	const HERITAGE = [
		// Colonne retirée du schéma et laissée en base : le cadriciel n'a pas de retrait de colonne,
		// `rebuild()` recrée la table et ne recopie que les colonnes communes.
		'structureConfiguration' => ['vatFrequency'],
		// Tables déclarées et jamais créées sur cette base : leur paquet n'y a pas encore tourné.
		'adminAccountingStructure' => [],
		'paymentSumup' => [],
	];

	/**
	 * Les valeurs d'enum que le schéma déclare et que la base n'a jamais eu à porter, avec la raison.
	 *
	 * Mêmes règles que `HERITAGE` : la déclaration ne se compare que dans un sens, parce qu'elle
	 * décrit l'état d'une base et non le code. Une entrée n'a sa place ici que si **aucune migration
	 * ne peut la résoudre**, c'est-à-dire si la valeur n'est jamais écrite dans cette colonne. Tout
	 * le reste est une migration à jouer, et c'est ce que le contrôle doit dire.
	 */
	const ENUM_HERITAGE = [
		// Déclarée depuis `4d299a1f2`, la création du paquet, et jamais installée : la colonne porte
		// `delivery-note` et `order-form` (36 380 et 1 467 lignes au 2026-09-29), et rien d'autre.
		// `Pdf::INVOICE` ne désigne pas une ligne de `sellingPdf`, c'est le type de rendu que
		// `PdfUi` reçoit pour composer une facture, dont le contenu vit en `PdfContent` par
		// `PdfLib::generateDocument()`. Aucune écriture ne peut donc porter la valeur, et
		// `PdfLib::delete()` la refuse explicitement. Il n'y a rien à jouer, seulement à déclarer.
		'sellingPdf.type' => ['invoice'],
	];

	/**
	 * Les index que le schéma déclare, que cette base n'a jamais portés et qu'aucun fichier de
	 * `migration/` ne peut installer, avec la date où leur déclaration est entrée dans git.
	 *
	 * Mêmes règles que `HERITAGE`, et pour la même raison : la déclaration décrit l'état d'une base,
	 * donc elle ne se compare que dans un sens. Une entrée n'a sa place ici que si sa déclaration est
	 * antérieure au 2026-09-17, date à partir de laquelle un `.yml` ne part plus sans son fichier.
	 * Le geste qui la résout est un `dev/module … flags=B`, qui recopie la table : il se décide, il ne
	 * se joue pas en passant, et c'est pourquoi ces trois-là sont déclarés plutôt que réclamés.
	 */
	const INDEX_HERITAGE = [
		// Déclaré par `4b45630df` le 2024-02-25.
		'websiteDesign.fqn (unique)',
		// Déclarés par `807237efc` le 2025-10-06. La base porte `(series)` et `(cultivation)` seuls,
		// qui servent les lectures par culture mais pas celles qui ajoutent l'intervention.
		'seriesTask.series.action',
		'seriesTask.cultivation.action',
	];

	/**
	 * Le fichier de module de chaque table, retenu par `getModules()`.
	 *
	 * Il sert à demander à git ce que `HEAD` déclare encore, seule façon de distinguer les deux causes
	 * opposées d'une colonne en base que le schéma ne déclare plus.
	 */
	private array $moduleFiles = [];

	public function testSchemaEstJoue(): void {

		$missingTables = [];
		$missingColumns = [];
		$extraColumns = [];
		$narrowEnums = [];
		$missingIndexes = [];

		foreach($this->getModules() as $table => $model) {

			$real = $this->getRealColumns($model, $table);

			if($real === NULL) {

				if(array_key_exists($table, self::HERITAGE) === FALSE) {
					$missingTables[] = $table;
				}

				continue;

			}

			$declared = $model->getProperties();
			$heritage = self::HERITAGE[$table] ?? [];

			foreach(array_diff($declared, array_keys($real)) as $column) {
				$missingColumns[] = $table.'.'.$column;
			}

			foreach(array_diff(array_keys($real), $declared, $heritage) as $column) {
				$extraColumns[] = $table.'.'.$column;
			}

			foreach($this->getNarrowEnums($model, $table, $real) as $narrow) {
				$narrowEnums[] = $narrow;
			}

			foreach($this->getMissingIndexes($model, $table) as $index) {
				$missingIndexes[] = $index;
			}

		}

		$this->assertEquals([], $missingTables, $this->explain("Tables déclarées et absentes de la base", $missingTables));
		$this->assertEquals([], $missingColumns, $this->explain("Colonnes déclarées et absentes de la base", $missingColumns));
		$this->assertEquals([], $extraColumns, $this->explainExtra($extraColumns, $this->getUncommittedRemovals($extraColumns)));
		$this->assertEquals([], $narrowEnums, $this->explain("Valeurs d'enum déclarées et absentes de la base", $narrowEnums));
		$this->assertEquals([], $missingIndexes, $this->explain("Index déclarés et absents de la base", $missingIndexes));

	}

	/**
	 * Les modules de la base commune, par nom de table.
	 *
	 * Les paquets de structure sont écartés : leurs tables vivent dans `structure_<id>` et se migrent
	 * seules. La liste vient de `StructureSetting`, qui évolue, et non d'une copie faite ici.
	 *
	 * @return \ModuleModel[]
	 */
	private function getModules(): array {

		$packagesStructure = array_merge(
			\structure\StructureSetting::getStartPackages(),
			\structure\StructureSetting::getAccountingPackages()
		);

		$modules = [];

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
			$modules[$model->getTable()] = $model;
			$this->moduleFiles[$model->getTable()] = $file;

		}

		return $modules;

	}

	/**
	 * Les colonnes réelles de la base, avec leur type, lues en une seule requête pour toutes les
	 * tables : une par table mettrait la sentinelle hors du budget de quelques secondes que tient la
	 * passe complète. Le type voyage avec le nom parce qu'`information_schema` le rend dans la même
	 * ligne, donc il ne coûte rien de plus ici et éviterait une seconde requête par table ailleurs.
	 *
	 * @return array<string, string>|null Le type par nom de colonne, NULL quand la table n'existe pas
	 */
	private function getRealColumns(\ModuleModel $model, string $table): ?array {

		static $columns = [];

		$base = $model->getDatabase();

		if(array_key_exists($base, $columns) === FALSE) {

			$columns[$base] = [];

			$query = $model->pdo()->query(
				"SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE FROM information_schema.columns WHERE TABLE_SCHEMA = ".$model->pdo()->quote($base)
			);

			foreach($query as $row) {
				$columns[$base][$row['TABLE_NAME']][$row['COLUMN_NAME']] = $row['COLUMN_TYPE'];
			}

		}

		return $columns[$base][$table] ?? NULL;

	}

	/**
	 * Les colonnes `enum` et `set` dont la base ne connaît pas toutes les valeurs que le schéma
	 * déclare, en `table.colonne (valeur, valeur)`.
	 *
	 * Pourquoi cette question s'ajoute à celle des colonnes : un élargissement d'enum est une
	 * migration comme une autre, et c'est la seule que le nom des colonnes ne peut pas voir. La
	 * colonne est là, la sentinelle était donc verte, et l'écriture de la valeur neuve échouait en
	 * production sur une donnée tronquée. Un contrôle aveugle à un cas ressemble exactement à un
	 * contrôle qui passe. Relevé le 2026-09-29 sur `toolboxFavorite.tool`, que
	 * `migration/2026-09-28-production-densites.md` élargit à `density` : son propre bloc
	 * `## Vérifier` le nommait, cette sentinelle non.
	 *
	 * ⚠️ **Le sens inverse n'est pas repris**, et c'est un choix : une base qui connaît une valeur
	 * que le schéma ne déclare plus n'empêche aucune écriture, et la retirer demanderait de migrer
	 * les lignes qui la portent, donc ce n'est pas une dette de mise à jour. Seule la base trop
	 * étroite fait échouer quelqu'un.
	 *
	 * ⚠️ **`set` est écarté, parce que le cadriciel ne l'installe pas en `SET` MySQL** mais en
	 * `int unsigned` portant un masque de bits, ses valeurs déclarées étant des puissances de deux
	 * (`structure.qualities` déclare 1, 2, 4 sur une colonne `int unsigned`). Une colonne `set` n'a
	 * donc aucune valeur à comparer, et un élargissement s'y joue sans migration. Mesuré le
	 * 2026-09-29 : comparées comme des enums, les 16 colonnes `set` de la base commune se
	 * déclaraient toutes trop étroites, soit 16 des 17 premiers écarts rendus par ce contrôle.
	 * Une colonne `enum` que la base n'installe pas en `enum(...)` est écartée pour la même raison,
	 * n'ayant pas non plus de valeurs à lire.
	 *
	 * @param array<string, string> $real Le type par nom de colonne
	 * @return string[]
	 */
	private function getNarrowEnums(\ModuleModel $model, string $table, array $real): array {

		$found = [];

		foreach($model->getProperties() as $property) {

			if($model->getPropertyType($property) !== 'enum') {
				continue;
			}

			if(str_starts_with($real[$property] ?? '', 'enum(') === FALSE) {
				continue;
			}

			$declared = $model->getPropertyEnum($property);

			// `information_schema` rend le type tel que MySQL l'a stocké, soit enum('a','b') : les
			// valeurs se relisent donc entre apostrophes, une apostrophe interne étant doublée.
			preg_match_all("/'((?:[^']|'')*)'/", $real[$property], $match);

			$installed = array_map(fn(string $value) => str_replace("''", "'", $value), $match[1]);

			$missing = array_diff($declared, $installed, self::ENUM_HERITAGE[$table.'.'.$property] ?? []);

			if($missing !== []) {
				$found[] = $table.'.'.$property.' ('.implode(', ', $missing).')';
			}

		}

		return $found;

	}

	/**
	 * Les index que le schéma déclare et que la base n'a pas, en `table.colonne[.colonne…]`, suivi de
	 * ` (unique)` pour une contrainte d'unicité.
	 *
	 * Pourquoi cette question s'ajoute à celle des colonnes : un index se déclare dans le même `.yml`
	 * que la colonne qu'il sert (`CLAUDE.md`, « toute colonne ajoutée pour filtrer, joindre ou
	 * agréger porte un index »), et la sentinelle était aveugle à la moitié du schéma. Les colonnes
	 * étant là, elle était verte, et la requête qui les lit balayait la table entière sans que rien le
	 * dise : mesuré le 2026-09-30 sur cette base, 8 index déclarés manquaient sur 229 tables lues,
	 * dont quatre que trois fichiers de `migration/` déjà poussés installent.
	 *
	 * ⚠️ **Le nom d'un index n'est pas comparable**, il est engendré (`sellingSale_index_10`) et
	 * n'apparaît dans aucun `.yml` : la comparaison porte sur la suite ordonnée de ses colonnes.
	 *
	 * ⚠️ **Et les deux natures ne se comparent pas de la même façon.** Un index de lecture est servi
	 * par tout index dont il est le préfixe gauche, donc `(structure, document)` couvre une
	 * déclaration `(structure)` ; une contrainte d'unicité, non, `UNIQUE (structure)` étant
	 * strictement plus forte que `UNIQUE (structure, season)`, qui l'autorise. Étendre la tolérance du
	 * préfixe aux uniques faisait disparaître `toolboxCompositionPlan.structure (unique)`, c'est-à-dire
	 * exactement l'écart que `migration/2026-09-23-paniers-toolbox-une-composition.md` installe.
	 *
	 * @return string[]
	 */
	private function getMissingIndexes(\ModuleModel $model, string $table): array {

		$found = [];
		$real = $this->getRealIndexes($model, $table);

		foreach(['indexConstraints' => FALSE, 'uniqueConstraints' => TRUE] as $property => $unique) {

			foreach($this->getDeclaredIndexes($model, $property) as $columns) {

				$signature = $table.'.'.implode('.', $columns).($unique ? ' (unique)' : '');

				if(in_array($signature, self::INDEX_HERITAGE, TRUE)) {
					continue;
				}

				$installed = FALSE;

				foreach($real as [$realColumns, $realUnique]) {

					$installed = $unique
						? ($realUnique and $realColumns === $columns)
						: (array_slice($realColumns, 0, count($columns)) === $columns);

					if($installed) {
						break;
					}

				}

				if($installed === FALSE) {
					$found[] = $signature;
				}

			}

		}

		return $found;

	}

	/**
	 * Les index déclarés d'un module, lus sur le modèle engendré et non sur le `.yml`, pour la même
	 * raison que les colonnes : c'est le modèle qui voyage dans le commit.
	 *
	 * Les deux propriétés sont protégées, d'où la réflexion. Un modèle qui n'en porte pas est un
	 * modèle sans index, pas une lecture manquée.
	 *
	 * @return string[][]
	 */
	private function getDeclaredIndexes(\ModuleModel $model, string $property): array {

		$reflection = new \ReflectionObject($model);

		if($reflection->hasProperty($property) === FALSE) {
			return [];
		}

		$declared = $reflection->getProperty($property);
		$declared->setAccessible(TRUE);

		return array_map(fn($columns) => (array)$columns, $declared->getValue($model) ?? []);

	}

	/**
	 * Les index réellement installés d'une table, en `[[colonnes ordonnées, unique], …]`.
	 *
	 * Une seule requête pour toute la base, comme `getRealColumns()` : 229 tables sinon.
	 *
	 * @return array<array{0: string[], 1: bool}>
	 */
	private function getRealIndexes(\ModuleModel $model, string $table): array {

		static $indexes = [];

		$base = $model->getDatabase();

		if(array_key_exists($base, $indexes) === FALSE) {

			$indexes[$base] = [];
			$columns = [];
			$unique = [];

			$query = $model->pdo()->query(
				"SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, COLUMN_NAME FROM information_schema.statistics"
				." WHERE TABLE_SCHEMA = ".$model->pdo()->quote($base)." ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX"
			);

			foreach($query as $row) {
				$columns[$row['TABLE_NAME']][$row['INDEX_NAME']][] = $row['COLUMN_NAME'];
				$unique[$row['TABLE_NAME']][$row['INDEX_NAME']] = ((int)$row['NON_UNIQUE'] === 0);
			}

			foreach($columns as $realTable => $realIndexes) {

				foreach($realIndexes as $name => $realColumns) {
					$indexes[$base][$realTable][] = [$realColumns, $unique[$realTable][$name]];
				}

			}

		}

		return $indexes[$base][$table] ?? [];

	}

	/**
	 * Parmi les colonnes que le schéma ne déclare plus, celles dont le retrait n'est pas commité.
	 *
	 * Le même écart a deux causes, et elles demandent des gestes contraires. Ou le schéma tiré est en
	 * avance sur cette base, et il faut jouer la migration qui retire la colonne, ce pour quoi cette
	 * sentinelle a été écrite. Ou une session a retiré la colonne de son `.yml` sans pousser, et jouer
	 * la retirerait de la base que toutes les fenêtres partagent, alors que celles restées à `HEAD` la
	 * déclarent encore. `HEAD` tranche : une colonne que le module de `HEAD` déclare toujours n'a pas
	 * vu son retrait partir, donc c'est cet arbre qui est en avance, et non cette base qui est en
	 * retard.
	 *
	 * La question se pose au module généré et non au `.yml`, parce que c'est lui qui porte les
	 * propriétés que `getProperties()` rend, et que son chemin est déjà connu du balayage.
	 *
	 * ⚠️ Un échec de git ne se lit dans aucun des deux sens. La colonne est alors rendue à part, et le
	 * message dit qu'il n'a pas pu poser la question, plutôt que de choisir la cause la plus probable.
	 *
	 * @return array{local: string[], indetermine: string[]}
	 */
	private function getUncommittedRemovals(array $extraColumns): array {

		$local = [];
		$indetermine = [];

		foreach($extraColumns as $column) {

			[$table, $property] = explode('.', $column, 2);

			$file = $this->moduleFiles[$table] ?? NULL;

			if($file === NULL) {
				$indetermine[] = $column;
				continue;
			}

			$output = [];
			$code = 0;

			exec('git show '.escapeshellarg('HEAD:'.$file).' 2>/dev/null', $output, $code);

			if($code !== 0) {
				$indetermine[] = $column;
				continue;
			}

			// La forme cherchée est celle que le générateur écrit dans la définition des propriétés,
			// trois tabulations puis la clé. Un motif sur le nom nu trouverait aussi la constante, les
			// accesseurs `where<Propriete>()` et les sous-sélections des autres modules, donc il
			// rendrait « encore déclarée » pour une colonne dont il ne reste qu'une trace annexe.
			$needle = "\t\t\t'".$property."' => [";

			foreach($output as $line) {

				if(str_starts_with($line, $needle)) {
					$local[] = $column;
					break;
				}

			}

		}

		return ['local' => $local, 'indetermine' => $indetermine];

	}

	/**
	 * Le message de l'écart « colonne en base que le schéma ne déclare plus », qui dit sa direction.
	 *
	 * Sans elle, le message nommait les fichiers de `migration/` « à jouer dans cet ordre » dans les
	 * deux cas, et la consigne était d'autant mieux suivie qu'elle était précise et ordonnée. Relevé
	 * par E-Dashboard le 2026-09-30 : sept fichiers nommés à jouer pour un retrait qui vivait dans
	 * l'arbre de travail d'une autre session, `payment.subscription`, `payment.dueAt` et
	 * `sellingItem.subscription`, que `HEAD` et `origin/main` déclaraient encore. Les jouer aurait
	 * retiré les colonnes pour toutes les fenêtres (`framework/dev/test/CLAUDE.md` : dès que
	 * l'autre côté est la base, les deux lectures du même écart mènent à des gestes opposés, et l'un
	 * des deux détruit).
	 */
	private function explainExtra(array $extraColumns, array $removals): string {

		$title = "Colonnes en base que le schéma ne déclare plus";

		if($removals['local'] === [] and $removals['indetermine'] === []) {
			return $this->explain($title, $extraColumns);
		}

		$message = $title." : ".implode(', ', $extraColumns);

		if($removals['local'] !== []) {

			$message .= "\nNE RIEN JOUER pour : ".implode(', ', $removals['local'])."\n"
				."Leur retrait n'est pas commité, `HEAD` les déclare encore : c'est cet arbre qui est en avance, pas cette base. "
				."Jouer la migration les retirerait de la base que toutes les fenêtres partagent, alors que celles restées à `HEAD` ou à `origin/main` les déclarent. "
				."Le geste est d'attendre que la session qui porte le retrait pousse.\n"
				."Pour le vérifier : git show HEAD:<paquet>/module/<Entite>.m.php | command grep \"'<colonne>' => [\"";

		}

		if($removals['indetermine'] !== []) {

			$message .= "\nDIRECTION INDÉTERMINÉE pour : ".implode(', ', $removals['indetermine'])."\n"
				."git n'a pas répondu sur le module de ces colonnes, donc la sentinelle n'a pas pu savoir si le retrait est commité. "
				."Ne rien jouer avant d'avoir posé la question à la main.";

		}

		$committed = array_values(array_diff($extraColumns, $removals['local'], $removals['indetermine']));

		if($committed !== []) {
			$message .= "\n".$this->explain("Colonnes dont le retrait est bien commité", $committed);
		}

		return $message;

	}

	/**
	 * Le message dit quoi jouer, pas seulement ce qui manque : c'est toute la valeur de ce contrôle
	 * pour celui qui vient de tirer et ne suit aucun des chantiers en cours.
	 *
	 * @param string[] $found
	 */
	private function explain(string $title, array $found): string {

		if($found === []) {
			return $title;
		}

		$filesNaming = $this->getMigrationsNaming($found);

		if($filesNaming !== []) {

			return $title." : ".implode(', ', $found)."\n"
				."Fichiers de migration/ qui nomment ce qui manque, à jouer dans cet ordre : ".$this->listFiles($filesNaming).$this->explainUntracked()."\n"
				."Leur ligne « En production le » ne dispense pas de les jouer : elle parle de la production, pas de cette base.";

		}

		$files = $this->getPendingMigrations();

		return $title." : ".implode(', ', $found)."\n"
			.($files === []
				? "Aucun fichier de migration/ n'est en attente : le schéma est parti sans sa migration, demander à la session qui l'a poussé."
				: "Aucun fichier de migration/ ne nomme cette colonne : demander à la session qui a écrit le schéma lequel la couvre. Ce qui est en attente, pour mémoire et non comme liste à jouer : ".$this->listFiles($files).$this->explainUntracked());

	}

	/**
	 * Les fichiers de `migration/` qui nomment une des tables ou des colonnes manquantes.
	 *
	 * ⚠️ Ceux-là se cherchent **sans** écarter les fichiers marqués `En production le`, à la
	 * différence de la liste d'attente. Une base de développement est une copie de production prise
	 * un jour donné : un fichier joué en production après ce jour-là y manque encore, et il est alors
	 * exactement celui qu'il faut jouer. Mesuré le 2026-09-23, sur une mise à jour qui apportait
	 * `userAdminLog` et `userLoginAttempt` : les deux fichiers qui les créent portaient « En
	 * production le 2026-09-23 », la liste d'attente ne les nommait donc pas, et elle envoyait jouer
	 * neuf autres fichiers dont sept appartenaient à des lots non poussés.
	 *
	 * @param string[] $found Des noms de table, ou des `table.colonne`.
	 * @return string[]
	 */
	/**
	 * Les fichiers de `migration/` que git suit.
	 *
	 * Un fichier non commité appartient à la session qui l'écrit, avec un lot que personne n'a tiré :
	 * le jouer avance la base commune sur du code absent de l'amont (skill `pull`, « Ne jouer que les
	 * fichiers suivis par git »). La sentinelle les nommait comme les autres, « à jouer dans cet
	 * ordre », donc elle envoyait jouer ce que la consigne interdit.
	 */
	private function getTrackedMigrations(): array {

		static $tracked = NULL;

		if($tracked === NULL) {

			$output = [];
			$code = 0;

			exec('git ls-files migration/ 2>/dev/null', $output, $code);

			// Sans réponse de git, aucun fichier ne se dit non suivi : la marque disparaît, elle ne
			// se pose pas à tort sur des fichiers qui sont peut-être commités.
			$tracked = ($code === 0) ? array_flip($output) : FALSE;

		}

		return $tracked === FALSE ? [] : $tracked;

	}

	/**
	 * Les fichiers nommés, un par ligne, ceux que git ne suit pas portant leur marque.
	 */
	private function listFiles(array $files): string {

		$tracked = $this->getTrackedMigrations();

		$lines = [];

		foreach($files as $file) {

			$lines[] = ($tracked === [] or isset($tracked[$file]))
				? $file
				: $file.' (NON COMMITÉ, ne pas jouer : il appartient à la session qui l\'écrit, avec un lot que personne n\'a tiré)';

		}

		return implode(', ', $lines);

	}

	/**
	 * Le motif lu et le nombre de fichiers non suivis qu'il ramène. Les deux listes de fichiers lisent
	 * le non suivi exprès, pour le marquer « NON COMMITÉ » plutôt que de le taire, et ne décident
	 * d'aucun verdict, qui vient de la base : ce compte dit au lecteur que la liste dépend de cet
	 * arbre (conseil des gardiens, `2026-09-28-rythme-des-tests`, piste 2).
	 */
	private function explainUntracked(): string {

		$tracked = $this->getTrackedMigrations();
		$files = glob('migration/*.md');

		if($tracked === []) {
			return ' [migration/*.md, '.count($files).' fichiers lus, git n\'a pas dit lesquels il suit]';
		}

		$untracked = array_filter($files, fn(string $file) => isset($tracked[$file]) === FALSE);

		return ' [migration/*.md, '.count($files).' fichiers lus dont '.count($untracked).' non suivi(s)]';

	}

	private function getMigrationsNaming(array $found): array {

		$files = [];

		foreach(glob('migration/*.md') as $file) {

			$content = file_get_contents($file);

			foreach($found as $item) {

				// Une valeur d'enum manquante arrive en `table.colonne (valeur, valeur)` : ce qui
				// se cherche dans un fichier de migration/ est le nom, pas ce qui le qualifie.
				$words = explode('.', preg_replace('/ \(.*$/', '', $item));
				$missing = FALSE;

				foreach($words as $word) {

					if(preg_match('/\b'.preg_quote($word, '/').'\b/', $content) === 0) {
						$missing = TRUE;
						break;
					}

				}

				if($missing === FALSE) {
					$files[] = $file;
					break;
				}

			}

		}

		sort($files);

		return $files;

	}

	/**
	 * Les fichiers de `migration/` qui ne portent pas de ligne `En production le`, dans l'ordre de
	 * leur nom, qui est celui dans lequel ils se jouent (`migration/README.md`).
	 *
	 * @return string[]
	 */
	private function getPendingMigrations(): array {

		$files = [];

		foreach(glob('migration/*.md') as $file) {

			if(str_contains(file_get_contents($file), 'En production le') === FALSE) {
				$files[] = $file;
			}

		}

		sort($files);

		return $files;

	}

}
?>
