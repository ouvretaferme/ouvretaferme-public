<?php
namespace map;

/**
 * Les écritures du décor ne passent que par des actions qui valident le droit, et jamais par l'API.
 *
 * Le décor posé depuis la 3D s'écrit par `page/decor.p.php`, ses calques par `page/decorLayer.p.php`,
 * ses bâtiments par `page/decorBuilding.p.php` et le terrain par `page/configuration.p.php`. Le droit
 * d'écrire est `canWrite()` de chaque module, que `ModulePage` valide sur ses actions ; un `->post()`, lui,
 * ne valide rien (`Page::post()`), et aucune autre sentinelle ne contrôle que ces actions valident le droit. D'où un
 * inventaire par page : elle n'appelle que les actions de sa liste (`DECOR_CALLS`, `DECOR_LAYER_CALLS`,
 * `DECOR_BUILDING_CALLS`, `CONFIGURATION_CALLS`), sans jamais leur passer de `validate:`, qui
 * remplacerait ce qu'elles valident. `doAdopt` et `doUnadopt`, deux `->post()`, valident eux-mêmes, comme
 * les actions de `page/decorUpload.p.php` (`DECOR_UPLOAD_CALLS`).
 *
 * L'envoi d'un modèle 3D à la place d'un bâtiment a son drapeau, `MapSetting::isDecorUploadEnabled()`, dont les
 * lecteurs sont ceux de `DECOR_UPLOAD_READERS`, dans les deux sens.
 *
 * Le décor ne passe jamais par l'API : aucun fichier `.a.php` ne nomme une classe `Decor*` du package.
 * Les colonnes `Plot.decorGreenhouse` et `Zone.decorGrass`, `decorSoil`, `decorMeadow`, qui ne sont pas des
 * classes, sont gardées hors de l'API par `PlotDecorGreenhouseAi` et `ZoneDecorTerrainAi`.
 *
 * Le réseau d'irrigation a son propre drapeau, `MapSetting::isIrrigationEnabled()` (en dev et aux structures
 * de `MapSetting::DECOR_IRRIGATION`), lu là où il se montre et là où il s'écrit. D'où trois inventaires :
 * les fichiers qui lisent ce drapeau sont ceux de `IRRIGATION_READERS`, dans les deux sens ; les étapes
 * de `IRRIGATION_STEPS` refusent à la création un calque ou un élément d'irrigation ; et
 * `MapSetting::DECOR_IRRIGATION_KITS` est la liste des kits que `MapSceneDecor.KITS` range dans le calque
 * `irrigation` (`sceneDecor.js`), sans quoi un kit ajouté au catalogue échapperait au drapeau.
 *
 * C'est le code qui fait foi : une action apparue se déclare, une action disparue se retire de la
 * déclaration, jamais la validation ne se retire d'une action.
 *
 * Inventaire pur, sans écriture en base.
 *
 * Lancement : dev/test package=map file=FlagSentinel
 */
class FlagSentinelTest extends \Test {

	/**
	 * Les seuls appels de `page/decor.p.php`, chacun avec ce qu'il fait.
	 */
	const DECOR_CALLS = [
		'getCreateElement' => 'l\'élément neuf, sa structure et le centre de son plan',
		'applyElement' => 'le centre du plan, sur l\'élément chargé',
		'doCreate' => 'poser un élément, canWrite() validé par ModulePage',
		'doUpdateProperties' => 'déplacer, tourner, retailler un élément, changer sa clôture ou son tuyau, canWrite() validé par ModulePage',
		'doDelete' => 'retirer un élément, canWrite() validé par ModulePage',
		'post' => 'doAdopt, enregistrer le décor par défaut au premier geste, canCreate et acceptCreate validés par l\'action elle-même avant toute construction ; doUnadopt, le défaire par Annuler, canDelete et acceptDelete validés de même',
	];

	/**
	 * Les seuls appels de `page/configuration.p.php`, chacun avec ce qu'il fait.
	 */
	const CONFIGURATION_CALLS = [
		'getCreateElement' => 'l\'élément neuf et sa structure',
		'doCreate' => 'enregistrer l\'herbe et le sol, canCreate validé par ModulePage',
		'write' => 'les saisons affichées et l\'écran « Les cultures », des réglages de culture',
		'read' => 'la frise de la période affichée, en lecture seule',
		'doUpdateProperties' => 'le retard affiché du planning, un réglage de culture',
		'applyElement' => 'la structure relue, pour hasWorkingTime()',
		'update' => 'l\'écran « Les cultures »',
	];

	/**
	 * Les seuls appels de `page/decorLayer.p.php`, chacun avec ce qu'il fait.
	 */
	const DECOR_LAYER_CALLS = [
		'getCreateElement' => 'le calque neuf et sa structure',
		'read' => 'compter les éléments qu\'une suppression emporte, en lecture seule',
		'doCreate' => 'créer un calque, canWrite() validé par ModulePage',
		'doUpdateProperties' => 'renommer un calque, canWrite() validé par ModulePage',
		'doDelete' => 'supprimer un calque avec ses éléments, canWrite() validé par ModulePage',
	];

	/**
	 * Les seuls appels de `page/decorBuilding.p.php`, chacun avec ce qu'il fait.
	 */
	const DECOR_BUILDING_CALLS = [
		'getCreateElement' => 'le bâtiment neuf, sa structure et le hangar posé qu\'il personnalise',
		'create' => 'ouvrir l\'éditeur d\'un bâtiment neuf, canWrite() validé par ModulePage',
		'doCreate' => 'enregistrer un bâtiment, canWrite() validé par ModulePage',
		'update' => 'ouvrir l\'éditeur d\'un bâtiment de la rangée, canWrite() validé par ModulePage',
		'doUpdate' => 'modifier un bâtiment, canWrite() validé par ModulePage',
		'doDelete' => 'retirer un bâtiment de la rangée et ses poses du plan, canWrite() validé par ModulePage',
	];

	/**
	 * Les seuls appels de `page/decorUpload.p.php`, une `Page` simple dont chaque action valide elle-même son droit.
	 */
	const DECOR_UPLOAD_CALLS = [
		'get' => 'model, le binaire d\'un envoi, canRead() et acceptRead() jugés par l\'action, une seule réponse pour tous les refus ; skill, l\'archive, canManage() validé sur la structure relue puis le drapeau',
		'post' => 'doPreview, vérifier un modèle sans rien écrire, et doCreate, en faire un bâtiment neuf, jugés par une même closure : canCreate() validé sur la structure relue, puis le drapeau, avant toute construction',
	];

	/**
	 * Les seuls fichiers, tests mis à part, qui lisent le drapeau des bâtiments téléversés, chacun avec ce qu'il ferme.
	 * La lecture du modèle en place et la suppression du bâtiment ne le lisent pas : drapeau refermé, un bâtiment importé se
	 * montre et se supprime encore.
	 */
	const DECOR_UPLOAD_READERS = [
		'production/map/lib/DecorUpload.l.php' => 'l\'onglet du modèle et son dépôt dans l\'éditeur du bâtiment',
		'production/map/page/decorUpload.p.php' => 'l\'aperçu et l\'envoi d\'un modèle, et le téléchargement du skill',
		'production/map/page/decorObject.p.php' => 'le panneau d\'un élément neuf d\'une rangée Eau, Nature ou Matériel, son aperçu et son envoi',
		'production/map/ui/Scene.u.php' => 'la tuile « Importer » au bout des rangées Eau, Nature et Matériel',
	];

	/**
	 * Les seuls fichiers, tests mis à part, qui lisent le drapeau de l'irrigation, par son accesseur ou par sa
	 * constante, chacun avec ce qu'il ferme.
	 */
	const IRRIGATION_READERS = [
		'production/map/Map.s.php' => 'la closure du drapeau, seule lectrice de MapSetting::DECOR_IRRIGATION',
		'production/map/module/Decor.e.php' => 'la pose d\'un kit d\'irrigation et la pose dans un calque d\'irrigation',
		'production/map/module/DecorLayer.e.php' => 'la création d\'un calque d\'irrigation',
		'production/map/lib/Scene.l.php' => 'les calques et les éléments d\'irrigation du format de la scène',
		'production/map/ui/Scene.u.php' => 'les vignettes d\'irrigation du catalogue du décor',
		'production/map/ui/DecorLayer.u.php' => 'les types proposés à la création d\'un calque',
	];

	/**
	 * Les étapes de `properties()` qui refusent l'irrigation hors drapeau, chacune avec ce qu'elle refuse.
	 */
	const IRRIGATION_STEPS = [
		Decor::class => [
			'kit.irrigation' => 'un kit de MapSetting::DECOR_IRRIGATION_KITS',
			'layer.irrigation' => 'un élément rangé dans un calque d\'irrigation',
		],
		DecorLayer::class => [
			'type.irrigation' => 'un calque de type IRRIGATION',
		],
	];

	public function testIrrigationReaders(): void {

		$readers = [];

		// Les fichiers non suivis comptent aussi : un écran qui montre l'irrigation doit échouer avant son commit
		foreach(\dev\SentinelLib::getFiles('.php', FALSE) as $file => $path) {

			if(str_ends_with($file, '.t.php') === FALSE and self::readsFlag(file_get_contents($path), ['isIrrigationEnabled', 'DECOR_IRRIGATION'])) {
				$readers[] = $file;
			}

		}

		$undeclared = array_diff($readers, array_keys(self::IRRIGATION_READERS));
		$missing = array_diff(array_keys(self::IRRIGATION_READERS), $readers);

		$this->assertEquals([], array_values($undeclared), 'un fichier lit MapSetting::isIrrigationEnabled() ou MapSetting::DECOR_IRRIGATION sans être déclaré dans FlagSentinelTest::IRRIGATION_READERS, c\'est la déclaration qui s\'ajoute, avec ce qu\'il ferme : '.implode(', ', $undeclared));
		$this->assertEquals([], array_values($missing), 'un fichier déclaré dans FlagSentinelTest::IRRIGATION_READERS ne lit plus le drapeau, et l\'irrigation qu\'il fermait s\'ouvre à tout le parc en production : c\'est la lecture qui se rétablit, et la déclaration ne se retire que si ce qu\'elle fermait a disparu : '.implode(', ', $missing));

	}

	public function testDecorUploadReaders(): void {

		$readers = [];

		foreach(\dev\SentinelLib::getFiles('.php', FALSE) as $file => $path) {

			if(str_ends_with($file, '.t.php') === FALSE and self::readsFlag(file_get_contents($path), ['isDecorUploadEnabled'])) {
				$readers[] = $file;
			}

		}

		$undeclared = array_diff($readers, array_keys(self::DECOR_UPLOAD_READERS));
		$missing = array_diff(array_keys(self::DECOR_UPLOAD_READERS), $readers);

		$this->assertEquals([], array_values($undeclared), 'un fichier lit MapSetting::isDecorUploadEnabled() sans être déclaré dans FlagSentinelTest::DECOR_UPLOAD_READERS, c\'est la déclaration qui s\'ajoute, avec ce qu\'il ferme : '.implode(', ', $undeclared));
		$this->assertEquals([], array_values($missing), 'un fichier déclaré dans FlagSentinelTest::DECOR_UPLOAD_READERS ne lit plus le drapeau, et l\'envoi qu\'il fermait s\'ouvre à tout le parc en production : c\'est la lecture qui se rétablit, et la déclaration ne se retire que si ce qu\'elle fermait a disparu : '.implode(', ', $missing));

	}

	public function testDecorUploadCallsDeclared(): void {

		$calls = self::getPageCalls(file_get_contents(\Lime::getPath('production').'/map/page/decorUpload.p.php'));

		$undeclared = array_diff(array_keys($calls), array_keys(self::DECOR_UPLOAD_CALLS));
		$missing = array_diff(array_keys(self::DECOR_UPLOAD_CALLS), array_keys($calls));

		$this->assertEquals([], array_values($undeclared), 'page/decorUpload.p.php appelle une action hors de FlagSentinelTest::DECOR_UPLOAD_CALLS, qui ne valide aucun droit d\'elle-même : c\'est l\'action qui valide canWrite() sur le bâtiment relu avant de se déclarer : '.implode(', ', $undeclared));
		$this->assertEquals([], array_values($missing), 'un appel de FlagSentinelTest::DECOR_UPLOAD_CALLS a quitté page/decorUpload.p.php, c\'est la déclaration qui se retire : '.implode(', ', $missing));

	}

	public function testIrrigationSteps(): void {

		$missing = [];

		foreach(self::IRRIGATION_STEPS as $class => $steps) {

			$l = new \PropertyList();
			$class::properties($l);

			foreach(array_keys($steps) as $step) {

				$property = strstr($step, '.', TRUE);
				$eProperty = $l->getDeclared($property);

				if($eProperty === NULL or array_key_exists($step, $eProperty->chain(TRUE)) === FALSE) {
					$missing[] = $class.' '.$step;
				}

			}

		}

		$this->assertEquals([], $missing, 'une étape qui refuse l\'irrigation hors drapeau manque à properties(), c\'est l\'étape qui se rétablit : '.implode(', ', $missing));

	}

	public function testIrrigationKits(): void {

		$kits = self::getIrrigationKits(file_get_contents(\Lime::getPath('production').'/map/static/js/sceneDecor.js'));

		$undeclared = array_diff($kits, MapSetting::DECOR_IRRIGATION_KITS);
		$missing = array_diff(MapSetting::DECOR_IRRIGATION_KITS, $kits);

		$this->assertEquals([], array_values($undeclared), 'un kit du calque irrigation de MapSceneDecor.KITS (sceneDecor.js) manque à MapSetting::DECOR_IRRIGATION_KITS, et se poserait hors drapeau : c\'est la constante qui s\'ajoute : '.implode(', ', $undeclared));
		$this->assertEquals([], array_values($missing), 'un kit de MapSetting::DECOR_IRRIGATION_KITS n\'est plus dans le calque irrigation de MapSceneDecor.KITS (sceneDecor.js), c\'est la constante qui se retire : '.implode(', ', $missing));

	}

	public function testIrrigationDetectors(): void {

		$this->assertTrue(self::readsFlag("<?php if(MapSetting::isIrrigationEnabled(\$e)) {} ?>", ['isIrrigationEnabled', 'DECOR_IRRIGATION']));
		$this->assertTrue(self::readsFlag("<?php \$x = \\map\\MapSetting::isIrrigationEnabled(\$e); ?>", ['isIrrigationEnabled', 'DECOR_IRRIGATION']));
		$this->assertFalse(self::readsFlag("<?php public static function isIrrigationEnabled(\$e) {} // MapSetting::isIrrigationEnabled(\$e) ?>", ['isIrrigationEnabled', 'DECOR_IRRIGATION']));
		$this->assertTrue(self::readsFlag("<?php \$x = MapSetting::DECOR_IRRIGATION; ?>", ['isIrrigationEnabled', 'DECOR_IRRIGATION']));
		$this->assertFalse(self::readsFlag("<?php const array|bool DECOR_IRRIGATION = TRUE; \$k = MapSetting::DECOR_IRRIGATION_KITS; ?>", ['isIrrigationEnabled', 'DECOR_IRRIGATION']));

		$this->assertEquals(['pompe', 'tuyau'], self::getIrrigationKits("\tstatic KITS = {\n\t\tcuve: { family: 'water', model: 'cuve' },\n\t\tpompe: { family: 'irrigation', layer: 'irrigation', shape: 'pump' },\n\t\t'tuyau': { family: 'irrigation', layer: 'irrigation', traced: true },\n\t\tchemin: { family: 'fence', layers: MapSceneDecor.TRACK }\n\t};"));

	}

	public function testDecorLayerCallsDeclared(): void {

		$calls = self::getPageCalls(file_get_contents(\Lime::getPath('production').'/map/page/decorLayer.p.php'));

		$undeclared = array_diff(array_keys($calls), array_keys(self::DECOR_LAYER_CALLS));
		$validated = array_keys(array_filter($calls));

		$this->assertEquals([], array_values($undeclared), 'page/decorLayer.p.php appelle une action hors de FlagSentinelTest::DECOR_LAYER_CALLS, qui n\'est pas sûre de valider canWrite() : c\'est l\'action qui passe par doCreate, doUpdateProperties ou doDelete, ou qui valide elle-même son droit avant de se déclarer : '.implode(', ', $undeclared));
		$this->assertEquals([], $validated, 'page/decorLayer.p.php passe un validate: qui remplace la validation de ModulePage et peut retirer canWrite(), c\'est l\'argument qui se retire : '.implode(', ', $validated));

	}

	public function testDecorBuildingCallsDeclared(): void {

		$calls = self::getPageCalls(file_get_contents(\Lime::getPath('production').'/map/page/decorBuilding.p.php'));

		$undeclared = array_diff(array_keys($calls), array_keys(self::DECOR_BUILDING_CALLS));
		$validated = array_keys(array_filter($calls));

		$this->assertEquals([], array_values($undeclared), 'page/decorBuilding.p.php appelle une action hors de FlagSentinelTest::DECOR_BUILDING_CALLS, qui n\'est pas sûre de valider canWrite() : c\'est l\'action qui passe par create, doCreate, update, doUpdate ou doDelete, ou qui valide elle-même son droit avant de se déclarer : '.implode(', ', $undeclared));
		$this->assertEquals([], $validated, 'page/decorBuilding.p.php passe un validate: qui remplace la validation de ModulePage et peut retirer canWrite(), c\'est l\'argument qui se retire : '.implode(', ', $validated));

	}

	public function testDecorOutOfApi(): void {

		$found = [];

		// Les fichiers non suivis comptent aussi : une route d'API qui écrit le décor doit échouer avant son commit
		foreach(\dev\SentinelLib::getFiles('.a.php', FALSE) as $file => $path) {

			if(self::namesDecor(file_get_contents($path))) {
				$found[] = $file;
			}

		}

		$this->assertEquals([], $found, 'une route d\'API nomme une classe Decor* du package map, alors que le décor ne passe jamais par l\'API : c\'est la route qui se retire : '.implode(', ', $found));

	}

	public function testConfigurationCallsDeclared(): void {

		$calls = self::getPageCalls(file_get_contents(\Lime::getPath('production').'/map/page/configuration.p.php'));

		$undeclared = array_diff(array_keys($calls), array_keys(self::CONFIGURATION_CALLS));
		$validated = array_keys(array_filter($calls));

		$this->assertEquals([], array_values($undeclared), 'page/configuration.p.php appelle une action hors de FlagSentinelTest::CONFIGURATION_CALLS, qui n\'est pas sûre de valider canWrite() : c\'est l\'action qui passe par doCreate, ou qui valide elle-même son droit avant de se déclarer : '.implode(', ', $undeclared));
		$this->assertEquals([], $validated, 'page/configuration.p.php passe un validate: qui remplace la validation de ModulePage et peut retirer canWrite(), c\'est l\'argument qui se retire : '.implode(', ', $validated));

	}

	public function testDecorCallsDeclared(): void {

		$calls = self::getPageCalls(file_get_contents(\Lime::getPath('production').'/map/page/decor.p.php'));

		$undeclared = array_diff(array_keys($calls), array_keys(self::DECOR_CALLS));
		$validated = array_keys(array_filter($calls));

		$this->assertEquals([], array_values($undeclared), 'page/decor.p.php appelle une action hors de FlagSentinelTest::DECOR_CALLS, qui n\'est pas sûre de valider canWrite() : c\'est l\'action qui passe par doCreate, doUpdateProperties ou doDelete, ou qui valide elle-même son droit avant de se déclarer : '.implode(', ', $undeclared));
		$this->assertEquals([], $validated, 'page/decor.p.php passe un validate: qui remplace la validation de ModulePage et peut retirer canWrite(), c\'est l\'argument qui se retire : '.implode(', ', $validated));

	}

	public function testDetectors(): void {

		$decorPage = "<?php\nnew \\map\\DecorPage()\n\t->doCreate(function(\$data) {\n\t\t\$c->find(fn(\$e) => TRUE);\n\t})\n\t->doDelete(fn(\$data) => throw new \\ViewAction(\$data), validate: ['canDelete'])\n\t->post('doMove', function(\$data) {\n\t})\n\t->doUpdateProperties('doMove', ['coordinates'], function(\$data) {\n\t\tf(\$a, \$b);\n\t})\n\t->doCreate(function(\$data) {\n\t}, 'doCreate', ['canCreate']);\n?>";

		$this->assertEquals(['doCreate' => TRUE, 'doDelete' => TRUE, 'post' => FALSE, 'doUpdateProperties' => FALSE], self::getPageCalls($decorPage));

		$decorPageProperties = "<?php\nnew \\map\\DecorPage()\n\t->doUpdateProperties('doMove', fn(\\map\\Decor \$e) => f(\$e, 1), function(\$data) {\n\t})\n\t->doDelete(fn(\$data) => g(\$data, 2));\n?>";

		$this->assertEquals(['doUpdateProperties' => FALSE, 'doDelete' => FALSE], self::getPageCalls($decorPageProperties));

		$decorPageValidated = "<?php\nnew \\map\\DecorPage()\n\t->doUpdateProperties('doMove', fn(\\map\\Decor \$e) => f(\$e), function(\$data) {\n\t}, ['canUpdate']);\n?>";

		$this->assertEquals(['doUpdateProperties' => TRUE], self::getPageCalls($decorPageValidated));

		$decorPageNamed = "<?php\nnew \\map\\DecorPage()\n\t->update(function(\$data) {\n\t}, page: 'updateCrop');\n?>";
		$this->assertEquals(['update' => FALSE], self::getPageCalls($decorPageNamed), 'un argument nommé autre que validate: ne retire rien à la validation');

		$this->assertTrue(self::namesDecor("<?php\nnew \\map\\DecorBuildingPage(); ?>"));
		$this->assertTrue(self::namesDecor("<?php\nnamespace map;\n\$c = DecorLib::getByStructure(\$e); ?>"));
		$this->assertFalse(self::namesDecor("<?php\nnamespace map;\n\$p = ['decorGreenhouse']; // DecorLib ?>"));
		$this->assertFalse(self::namesDecor("<?php\n\$d = new \\other\\Decor(); ?>"));

	}

	/**
	 * Les appels enchainés d'une page, chacun avec `TRUE` quand il reçoit un argument `validate:`, ou un
	 * argument quelconque après son action, qui le serait en position. L'action est la dernière closure
	 * passée : `doUpdateProperties()` peut en recevoir une avant elle, qui rend ses propriétés. Seuls
	 * comptent les appels de la chaîne : le corps d'une closure n'en est pas un.
	 *
	 * @return array<string, bool>
	 */
	private static function getPageCalls(string $source): array {

		$tokens = \dev\SentinelLib::getTokens($source);
		$count = count($tokens);
		$calls = [];

		for($i = 0; $i < $count - 3; $i++) {

			// Une closure rencontrée hors des arguments d'un appel, celle de `new Page` comme `$buildUpload` de decorUpload.p.php, ne
			// déclare aucune action : elle n'est qu'un corps que les actions appellent, et ses appels comptent avec elles
			if($tokens[$i][0] === T_FUNCTION) {

				while($tokens[$i][0] !== '{') {
					$i++;
				}

				$i = \dev\SentinelLib::getClosing($tokens, $i);
				continue;

			}

			if($tokens[$i][0] !== T_OBJECT_OPERATOR or $tokens[$i + 1][0] !== T_STRING or $tokens[$i + 2][0] !== '(') {
				continue;
			}

			$close = \dev\SentinelLib::getClosing($tokens, $i + 2);
			$validated = FALSE;
			$argument = 0;
			$action = NULL;

			for($j = $i + 3; $j < $close; $j++) {

				$type = $tokens[$j][0];

				if($type === T_STRING and $tokens[$j][1] === 'validate' and ($tokens[$j + 1][0] ?? NULL) === ':') {
					$validated = TRUE;
				} else if($type === T_FUNCTION or $type === T_FN) {
					$action = $argument;
				} else if($type === ',' and (($tokens[$j + 1][0] ?? NULL) !== T_STRING or ($tokens[$j + 2][0] ?? NULL) !== ':')) {
					$argument++;
				}

				if($type === '(' or $type === '[' or $type === '{') {
					$j = \dev\SentinelLib::getClosing($tokens, $j);
				}

			}

			// Un argument positionnel après l'action peut être celui de la validation : un argument nommé dit lui-même
			// ce qu'il est, et seul `validate:` remplace la validation de ModulePage
			if($action !== NULL and $argument > $action) {
				$validated = TRUE;
			}

			$calls[$tokens[$i + 1][1]] = (($calls[$tokens[$i + 1][1]] ?? FALSE) or $validated);

			$i = $close;

		}

		return $calls;

	}

	/**
	 * Le code lit-il un drapeau, par l'un des noms de `$names` appelé sur une classe (`MapSetting::isIrrigationEnabled()`,
	 * `MapSetting::DECOR_IRRIGATION`) ? La définition d'un de ces noms n'est pas une lecture.
	 */
	private static function readsFlag(string $source, array $names): bool {

		$tokens = \dev\SentinelLib::getTokens($source);

		foreach($tokens as $i => $token) {

			if($token[0] === T_DOUBLE_COLON and in_array($tokens[$i + 1][1] ?? NULL, $names, TRUE)) {
				return TRUE;
			}

		}

		return FALSE;

	}

	/**
	 * Les kits de `MapSceneDecor.KITS` rangés dans le calque `irrigation` : une entrée par ligne, `<clé>: { … layer: 'irrigation' … }`.
	 *
	 * @return string[]
	 */
	private static function getIrrigationKits(string $source): array {

		preg_match_all("/^\\s*'?([a-z0-9-]+)'?: \\{[^\\n]*\\blayer: 'irrigation'/m", $source, $matches);

		return $matches[1];

	}

	/**
	 * Le code nomme-t-il une classe `Decor*` du package `map`, en entier ou depuis l'espace de noms `map` ?
	 * Une propriété préfixée `decor` (`Plot.decorGreenhouse`) n'est pas une classe.
	 */
	private static function namesDecor(string $source): bool {

		$inMap = (preg_match('/\bnamespace map;/', $source) === 1);

		foreach(\dev\SentinelLib::getTokens($source) as $token) {

			if(
				($token[0] === T_NAME_FULLY_QUALIFIED and preg_match('/^\\\\map\\\\Decor[A-Za-z]*$/', $token[1]) === 1) or
				($token[0] === T_NAME_QUALIFIED and preg_match('/^map\\\\Decor[A-Za-z]*$/', $token[1]) === 1) or
				($inMap and $token[0] === T_STRING and preg_match('/^Decor[A-Za-z]*$/', $token[1]) === 1)
			) {
				return TRUE;
			}

		}

		return FALSE;

	}

}
?>
