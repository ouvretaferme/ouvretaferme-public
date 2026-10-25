<?php
/**
 * Supprime les structures de test de la base de développement, et leur base `structure_<id>`.
 *
 * Une session de recette crée des structures pour éprouver un lot : une ferme, le groupe qui la
 * chapeaute, l'AMAP qui lui achète. Elles s'appellent toutes `zz…` (skill `recette`), et personne ne
 * les retire, parce que le produit ne sait pas supprimer une structure : `StructureLib::delete()`
 * lève `UnsupportedException`. Elles s'accumulent donc dans une base que tout le monde partage, et
 * chaque écran qui liste des structures les montre.
 *
 * Ce que le script fait, et pourquoi il ne passe pas par les Lib : il n'y a pas de chemin de
 * suppression dans le produit, et il n'y en aura pas. Le nettoyage écrit donc en SQL, sur la seule
 * base de développement, et il tient sa carte du code : les colonnes qui portent une structure sont
 * lues dans `getPropertiesToElement()` de chaque module, jamais dans une liste recopiée ici. Un
 * module ajouté demain est suivi sans qu'on rouvre ce fichier.
 *
 * La cascade est nécessaire, elle n'est pas un luxe : 124 des 243 tables de la base commune portent
 * une colonne `structure`, et les 119 autres se rattachent par un parent. Tout le paquet `amap` est
 * dans ce cas, sa clé vers la structure s'appelant `amap` et non `structure`
 * (`commercialisation/amap/amap.yml`) : un nettoyage qui ne balaierait que la colonne `structure`
 * laisserait les contrats, les souscriptions et les distributions derrière lui.
 *
 * Trois gardes, parce que le script supprime :
 *   - il refuse tout environnement autre que `dev`, la production n'ayant rien à nettoyer ;
 *   - il ne relève rien sans `zz` en tête du nom, et `also=` est le seul moyen d'emporter une
 *     structure qui n'en porte pas, nommément, par son id ;
 *   - au-delà de MAXIMUM structures visées, il s'arrête : la base de dev est une copie de
 *     production, un critère qui ramasse des centaines de lignes a dérapé.
 *
 * Sans `write=1` il n'écrit rien et imprime ce qu'il a examiné, table par table. C'est ce relevé
 * qu'on lit avant d'écrire.
 *
 * Usage :
 *   php framework/lime.php -a ouvretaferme -e dev data/cleanTestStructureAi
 *   php framework/lime.php -a ouvretaferme -e dev data/cleanTestStructureAi write=1
 *   php framework/lime.php -a ouvretaferme -e dev data/cleanTestStructureAi also=6550,6551 write=1
 *   php framework/lime.php -a ouvretaferme -e dev data/cleanTestStructureAi days=2 write=1
 *   php framework/lime.php -a ouvretaferme -e dev data/cleanTestStructureAi only=6553,6554 write=1
 */
new Page()
	->cli('index', function($data) {

		$maximum = 500;

		if(LIME_ENV !== 'dev') {
			throw new Exception('Ce nettoyage ne tourne qu\'en dev, jamais en '.LIME_ENV.'.');
		}

		$write = (bool)GET('write', 'int', 0);
		$also = array_filter(array_map('intval', explode(',', (string)GET('also', 'string', ''))));
		$days = GET('days', 'int', 0);
		$only = array_filter(array_map('intval', explode(',', (string)GET('only', 'string', ''))));
		$except = array_filter(array_map('intval', explode(',', (string)GET('except', 'string', ''))));

		$mStructure = \structure\Structure::model();
		$database = $mStructure->getDatabase();
		$pdo = $mStructure->pdo();

		// Le critère est le préfixe de la convention, et rien d'autre : la base de dev est une copie
		// de production, où « Test » est aussi le nom de vraies structures créées en 2022.
		// `only` remplace le critère au lieu de s'y ajouter : il sert à retirer le décor d'un lot
		// précis, sans emporter celui des autres fenêtres.
		$condition = $only !== [] ? 'id IN ('.implode(', ', $only).')' : 'name LIKE \'zz%\'';

		if($only === [] and $also !== []) {
			$condition .= ' OR id IN ('.implode(', ', $also).')';
		}

		$condition = '('.$condition.')';

		// Une structure née aujourd'hui est le décor d'une recette qu'une autre fenêtre est peut-être
		// en train de jouer : `days` la laisse tranquille sans avoir à nommer ses ids.
		if($days > 0) {
			$condition .= ' AND createdAt < DATE_SUB(NOW(), INTERVAL '.$days.' DAY)';
		}

		$cStructure = $pdo
			->select('SELECT id, name, type, createdAt FROM '.$pdo->api->field($database).'.'.$pdo->api->field('structure').' WHERE '.$condition.' ORDER BY id')
			->fetchAll(PDO::FETCH_ASSOC);

		echo 'Structures visées'.($days > 0 ? ' et créées il y a plus de '.$days.' jour(s)' : '').' : '.count($cStructure).' sur '.$pdo->selectUnique('SELECT COUNT(*) FROM '.$pdo->api->field($database).'.'.$pdo->api->field('structure')).' dans la base'."\n";

		if($cStructure === []) {
			echo 'Rien à nettoyer.'."\n";
			return;
		}

		if(count($cStructure) > $maximum) {
			throw new Exception('Le critère vise '.count($cStructure).' structures, au-delà de '.$maximum.' : il ramasse autre chose que des structures de test.');
		}

		$types = [];

		foreach($cStructure as $structure) {
			$types[$structure['type']] = ($types[$structure['type']] ?? 0) + 1;
		}

		foreach($types as $type => $count) {
			echo '  '.str_pad($type, 12).$count."\n";
		}

		// La réserve passe avant tout le reste : une structure qu'un chantier cite encore n'entre pas
		// dans la cascade, donc ni dans le relevé ni dans la suppression. `only=` la vise nommément,
		// et c'est alors la session qui tient le chantier qui a décidé, pas le critère.
		$reserved = $only !== [] ? [] : \data\CleanTestStructureAiLib::getReserved($cStructure);

		// `except` porte ce qu'une session a répondu et que rien n'écrit encore : un décor semé il y a
		// une heure n'est cité nulle part, et seule la fenêtre qui le tient sait qu'il sert.
		foreach($cStructure as $structure) {

			if(in_array((int)$structure['id'], $except)) {
				$reserved[(int)$structure['id']] = $structure['name'].' (annoncée par sa session)';
			}

		}

		if($reserved !== []) {

			echo "\n".'Réservées, citées par un dossier de chantier, par le dépôt, ou annoncées : '.count($reserved)."\n";

			foreach($reserved as $structureId => $name) {
				echo '  '.str_pad($structureId, 6).$name."\n";
			}

			$cStructure = array_values(array_filter($cStructure, fn($structure) => isset($reserved[(int)$structure['id']]) === FALSE));

		}

		if($cStructure === []) {
			echo "\n".'Tout ce que le critère vise est réservé. Rien à nettoyer.'."\n";
			return;
		}

		$structureIds = array_column($cStructure, 'id');

		[$rows, $depth] = \data\CleanTestStructureAiLib::collect($database, $structureIds);

		echo "\n".'Lignes rattachées, en '.$depth.' passe(s) de cascade :'."\n";

		$total = 0;

		foreach($rows as $table => $ids) {

			$total += count($ids);

			echo '  '.str_pad($table, 34).count($ids)."\n";

		}

		echo '  '.str_pad('TOTAL', 34).$total.' lignes dans '.count($rows).' tables'."\n";

		[$gaps, $checked, $skipped] = \data\CleanTestStructureAiLib::check($database, $rows, $structureIds);

		echo "\n".'Contrôle de la cascade : '.$checked.' tables vérifiées par leur colonne structure, '.$skipped.' sans cette colonne'."\n";

		if($gaps !== []) {
			throw new Exception('La cascade a relevé des lignes hors des structures visées : '.implode(' · ', $gaps));
		}

		echo '  aucun écart.'."\n";

		$bases = \data\CleanTestStructureAiLib::getDatabases($structureIds);
		$orphans = \data\CleanTestStructureAiLib::getOrphanDatabases();

		echo "\n".'Bases de structure à supprimer : '.count($bases)."\n";

		if($bases !== []) {
			echo '  '.implode(' ', $bases)."\n";
		}

		if($orphans !== []) {

			// Signalées sans être emportées : une base sans sa ligne peut appartenir à une structure
			// qu'une autre fenêtre est en train de créer.
			echo "\n".'Bases sans structure en base, laissées en place : '.count($orphans)."\n";
			echo '  '.implode(' ', $orphans)."\n";

		}

		if($write === FALSE) {
			echo "\n".'Relevé seulement. Ajouter write=1 pour supprimer.'."\n";
			return;
		}

		$deleted = \data\CleanTestStructureAiLib::delete($database, $rows);
		$dropped = \data\CleanTestStructureAiLib::dropDatabases($bases);

		echo "\n".'Supprimé : '.$deleted.' lignes, '.$dropped.' base(s) de structure.'."\n";

	});

?>
