<?php
namespace scene;

/**
 * Une scène n'a qu'un three par page, et rien à l'exécution ne le dit : deux adresses du même
 * fichier donnent deux exemplaires, dont les objets ne se reconnaissent pas entre eux, sans erreur
 * au chargement. Cette sentinelle tient les quatre choses dont l'adresse unique dépend : three
 * n'est nommé que par `static/js/loader.js` et par les compléments de `static/lib/`, chaque
 * complément vise un fichier présent et le même three, les modules ES de `static/js/` sont ceux
 * que `SceneLoader` importe, et rien du package ne se pose en script classique par `Asset`. Elle
 * tient aussi l'adresse des deux fichiers cuits, qui porte leur empreinte.
 *
 * Aucune écriture, aucune base ouverte : elle ne lit que des fichiers.
 *
 * Lancement : dev/test package=scene file=LoaderSentinel
 */
class LoaderSentinelTest extends \Test {

	const string THREE = 'three-0.170.0.module.min.js';

	/**
	 * Les modules ES de `static/js/`, avec ce qui les y tient. `/minify/` concatène ses fichiers en
	 * un script classique, où un `import` ou un `export` statique est une erreur de syntaxe qui
	 * emporte le lot entier : un module ne se charge donc que par `SceneLoader`.
	 */
	const array MODULES = [
		'model.js' => "le lecteur des modèles cuits, que la cuisson importe aussi sous node (bake/bake.mjs)",
		'geometry.js' => "les pièces de géométrie du socle, que SceneLoader passe aux fabriques de crop et fruit",
		'species.js' => "la résolution d'une culture vers une espèce du catalogue",
		'stage.js' => "le calendrier d'une série et son stade à une date",
		'crop.js' => "les plants d'une culture, fusionnés depuis les modèles cuits",
		'fruit.js' => "les fruits et les organes récoltés",
		'figure.js' => "les personnages articulés et ce qu'ils portent",
		'avatar.js' => "l'avatar d'un utilisateur, monté depuis sa fiche dans la page, que SceneLoader.loadAvatar() importe",
		'building.js' => "les bâtiments d'une structure et le cadre du skill 3d-batiment, que SceneLoader.loadCrop() importe et que les modules engendrés par le skill embarquent",
	];

	/**
	 * Les deux fichiers cuits, avec la clé de `data-scene-bake` qui porte leur empreinte. Sans elle,
	 * l'adresse ne change pas quand la cuisson change, et le navigateur sert l'ancienne quinze jours
	 * (`framework/dev/conf/rewrite/static.cfg`) : des pièces d'un modèle sur l'index d'un autre.
	 */
	const array BAKED = [
		'models.json' => 'json',
		'models.bin' => 'bin',
	];

	/**
	 * Ce qui lit une paire cuite dans `loader.js`, par la variable qui porte son répertoire, avec celle
	 * qui porte ses empreintes : `loadPair()` seul, auquel `loadCrop()` passe la paire commune et
	 * `loadBake()` celle d'un package qui a ses propres modèles (`BakeLib::getPair()`). Un second
	 * lecteur lirait la paire commune une seconde fois sur la page qui demande les deux.
	 */
	const array READERS = [
		'pair.directory' => 'pair',
	];

	/**
	 * Les appels de `loadPair()` dans `loader.js`, écrits en clair : l'empreinte que lit `READERS` n'est
	 * juste que si chaque appelant passe celle de `data-scene-bake`. `loadCrop()` la prend aux clés
	 * `json` et `bin` de la paire commune, `loadBake()` passe la paire de `pairs.<nom>` telle quelle.
	 */
	const array PAIRS = [
		'SceneLoader.loadPair({ directory, json: bake.json, bin: bake.bin })' => 'loadCrop()',
		'SceneLoader.loadPair(pair)' => 'loadBake()',
	];

	private function getDirectory(): string {
		return LIME_DIRECTORY.'/base/scene';
	}

	/**
	 * @return array<string, string> le contenu de chaque fichier `.js` d'un dossier de static/, par nom
	 */
	private function getScripts(string $directory): array {

		$scripts = [];

		foreach(glob($this->getDirectory().'/static/'.$directory.'/*.js') as $path) {
			$scripts[basename($path)] = file_get_contents($path);
		}

		return $scripts;

	}

	/**
	 * Les spécificateurs qu'un module importe ou réexporte, appels dynamiques à adresse écrite compris.
	 *
	 * @return string[]
	 */
	private static function getSpecifiers(string $code): array {

		preg_match_all('/(?:\bfrom\s*|\bimport\s*\(?\s*)([\'"])([^\'"\n]+)\1/', $code, $matches);

		return array_values(array_unique($matches[2]));

	}

	private static function isModule(string $code): bool {
		return preg_match('/^\s*(?:export\s+(?:default|function|async|class|const|let|var)\b|export\s*[{*]|import\s*[\w{*\'"])/m', $code) === 1;
	}

	/**
	 * Ce qu'un code PHP pose du package en script classique : un module déclaré par `Asset::js()`, ou
	 * n'importe quel fichier par `Asset::lib()`, static/lib/ ne portant que des modules.
	 *
	 * @return string[]
	 */
	private static function getAssetCalls(string $code): array {

		$calls = [];

		foreach(array_keys(self::MODULES) as $module) {

			if(preg_match('/Asset::js\(\s*[\'"]scene[\'"]\s*,\s*[\'"](?:[^\'"]*\/)?'.preg_quote($module, '/').'[\'"]/', $code) === 1) {
				$calls[] = "Asset::js('scene', '".$module."')";
			}

		}

		if(preg_match_all('/Asset::lib\(\s*[\'"]scene[\'"]\s*,\s*[\'"]([^\'"]*)[\'"]/', $code, $matches) > 0) {

			foreach($matches[1] as $file) {
				$calls[] = "Asset::lib('scene', '".$file."')";
			}

		}

		return $calls;

	}

	/**
	 * Les fichiers de static/lib/ que `loader.js` charge, à leur adresse nue. L'appel se lit en
	 * entier, parenthèse fermante comprise : une query ajoutée derrière le nom ferait une seconde
	 * adresse du même fichier, et l'appel sort alors de cette liste.
	 *
	 * @return string[]
	 */
	private static function getLoaded(string $loader): array {

		preg_match_all('/\bimport\(directory \+\'\/lib\/([\w.-]+\.js)\'\)/', $loader, $matches);

		return array_values(array_unique($matches[1]));

	}

	public function testDetectors(): void {

		$this->assertTrue(self::isModule("import { a } from './a.js';"));
		$this->assertTrue(self::isModule("\texport function build() {}"));
		$this->assertFalse(self::isModule("class SceneLoader { static load() { return import(directory +'/js/model.js'); } }"));
		$this->assertFalse(self::isModule("// un commentaire qui cite import et export"));

		$this->assertEquals(['three', './three-0.170.0.OrbitControls.js', './c.js', 'three/addons/x.js'], self::getSpecifiers("import {\n\tA,\n} from 'three';\nimport { B } from \"./three-0.170.0.OrbitControls.js\";\nexport * from './c.js';\nconst d = await import('three/addons/x.js');"));
		$this->assertEquals([], self::getSpecifiers("const directory = from; import(directory +'/lib/x.js');"));

		$this->assertTrue(self::isModule("import './a.js';"));
		$this->assertTrue(self::isModule("const a = 1;\nexport { a };"));
		$this->assertFalse(self::isModule("class SceneExport {\n\texport() {}\n}"));
		$this->assertFalse(self::isModule("const options = {\n\texport: true,\n};"));
		$this->assertFalse(self::isModule("/**\n * import et export, cités en tête d'une ligne de commentaire\n */"));

		$this->assertEquals(["Asset::js('scene', 'model.js')"], self::getAssetCalls("\\Asset::js('scene', 'model.js');"));
		$this->assertEquals(["Asset::lib('scene', 'three-0.170.0.module.min.js')"], self::getAssetCalls("\\Asset::lib( \"scene\", 'three-0.170.0.module.min.js' );"));
		$this->assertEquals([], self::getAssetCalls("\\Asset::js('scene', 'loader.js'); \\Asset::js('scene', 'viewmodel.js'); \\Asset::js('series', 'model.js'); \\Asset::lib('util', 'x.js');"));

		$this->assertEquals(['a.js'], self::getLoaded("import(directory +'/lib/a.js'), import(directory +'/lib/b.js' +'?'+ version), import(directory +'/lib/c.js'+ suffix)"));

	}

	public function testModulesAreDeclared(): void {

		$scripts = $this->getScripts('js');

		if($this->assertTrue(isset($scripts['loader.js']), "la sentinelle ne trouve plus base/scene/static/js/loader.js : elle ne sait plus lire ce qu'elle compare") === FALSE) {
			return;
		}

		$modules = array_keys(array_filter($scripts, self::isModule(...)));

		$new = array_diff($modules, array_keys(self::MODULES));
		$stale = array_diff(array_keys(self::MODULES), $modules);

		$this->assertEquals([], $new, "fichiers de base/scene/static/js qui portent un import ou un export statique sans être déclarés dans MODULES (un script classique reçoit THREE en argument de SceneLoader.load() ; un module se déclare et s'importe depuis loader.js) : ".implode(', ', $new));
		$this->assertEquals([], $stale, "modules déclarés dans MODULES qui ne sont plus des modules de base/scene/static/js, retirer la déclaration : ".implode(', ', $stale));

		$missing = [];
		foreach(array_keys(self::MODULES) as $module) {
			if(str_contains($scripts['loader.js'], "import(directory +'/js/".$module."?'+ Ajax.Asset.getVersion())") === FALSE) {
				$missing[] = $module;
			}
		}

		$this->assertEquals([], $missing, "modules que loader.js n'importe pas par Ajax.Asset.getVersion() (sans la version, le fichier corrigé garde son adresse et le cache du navigateur sert l'ancien) : ".implode(', ', $missing));

	}

	/**
	 * `loader.js` lit chaque fichier cuit à l'adresse qui porte son empreinte, et aucun autre fichier de model/.
	 */
	public function testBakedFilesAreVersioned(): void {

		$scripts = $this->getScripts('js');

		if($this->assertTrue(isset($scripts['loader.js']), "la sentinelle ne trouve plus base/scene/static/js/loader.js : elle ne sait plus lire ce qu'elle compare") === FALSE) {
			return;
		}

		$loader = $scripts['loader.js'];

		$missing = [];
		foreach(self::READERS as $directory => $bake) {
			foreach(self::BAKED as $file => $key) {
				// L'adresse est le premier argument de SceneLoader.read(), la virgule borne la clé
				if(str_contains($loader, "SceneLoader.read(".$directory." +'/model/".$file."?'+ ".$bake.".".$key.",") === FALSE) {
					$missing[] = $file.' ('.$bake.')';
				}
			}
		}

		$this->assertEquals([], $missing, "fichiers cuits que loader.js ne lit pas à l'adresse de leur empreinte, <répertoire> +'/model/<fichier>?'+ <bake ou pair>.<clé> (sans elle, le navigateur garde l'ancienne cuisson) : ".implode(', ', $missing));
		$this->assertEquals([], array_values(array_filter(array_keys(self::PAIRS), fn(string $call) => str_contains($loader, $call) === FALSE)), "appels de loadPair() déclarés dans PAIRS que loader.js n'écrit plus tels quels : la paire passée ne porte peut-être plus les empreintes de data-scene-bake (rétablir l'appel, ou déclarer le nouveau s'il passe toujours bake.json et bake.bin)");
		$this->assertEquals(count(self::PAIRS), substr_count($loader, 'SceneLoader.loadPair('), "loader.js appelle loadPair() hors des appels déclarés dans PAIRS : la paire qu'il passe n'est pas vérifiée (le déclarer, avec la provenance de ses empreintes)");
		$this->assertEquals(count(self::BAKED) * count(self::READERS), substr_count($loader, "'/model/"), "loader.js lit un fichier de static/model/ que BAKED ne déclare pas, par un lecteur que READERS ne déclare pas, ou en lit un deux fois : chaque fichier cuit se lit une fois par lecteur, à l'adresse de son empreinte");

	}

	public function testNothingIsAClassicScript(): void {

		$found = [];

		foreach(\dev\SentinelLib::getFiles('.php') as $file => $path) {

			// Un test ne sert aucune page : l'appel qu'il écrit ne pose rien chez le navigateur, et ceux
			// de testDetectors() sont les cas mêmes que la sentinelle doit reconnaître.
			if(str_ends_with($file, '.t.php')) {
				continue;
			}

			foreach(self::getAssetCalls(file_get_contents($path)) as $call) {
				$found[] = $file.' ('.$call.')';
			}

		}

		$this->assertEquals([], $found, "fichiers qui posent en script classique un module de base/scene, dont l'import ou l'export est alors une erreur de syntaxe, et qui par Asset::lib() donnerait à three une seconde adresse (retirer l'appel : SceneLoader.load() charge three, ses compléments et les modules) : ".implode(', ', $found));

	}

	public function testThreeIsNamedInOnePlace(): void {

		$found = [];

		// Ce que le navigateur reçoit : les dossiers static/ du dépôt. model/ et bake/ ne sont lus que par
		// node, qui résout three par bake/resolve.mjs.
		$scripts = \dev\SentinelLib::getFiles('.js');

		foreach(glob($this->getDirectory().'/static/js/*.js') as $path) {
			$scripts['base/scene/static/js/'.basename($path)] = $path;
		}

		foreach($scripts as $file => $path) {

			if(
				str_contains($file, '/static/') === FALSE or
				str_starts_with($file, 'base/scene/static/lib/') or
				$file === 'base/scene/static/js/loader.js'
			) {
				continue;
			}

			$code = file_get_contents($path);

			$bare = array_filter(self::getSpecifiers($code), fn(string $specifier) => $specifier === 'three' or str_starts_with($specifier, 'three/'));

			if($bare !== [] or preg_match('/three[-.@]\d|three\.module/', $code) === 1) {
				$found[] = $file;
			}

		}

		$this->assertEquals([], $found, "scripts servis qui nomment three eux-mêmes, par un import nu ou par une adresse (three se reçoit en argument de SceneLoader.load(), base/scene/static/js/loader.js, seul à écrire son adresse) : ".implode(', ', $found));

	}

	public function testAddonsShareOneThree(): void {

		$libraries = $this->getScripts('lib');
		$scripts = $this->getScripts('js');

		if($this->assertTrue(isset($scripts['loader.js']), "la sentinelle ne trouve plus base/scene/static/js/loader.js : elle ne sait plus lire ce qu'elle compare") === FALSE) {
			return;
		}

		$loader = $scripts['loader.js'];
		$loaded = self::getLoaded($loader);

		$this->assertEquals(
			count($loaded) + count(self::MODULES),
			substr_count($loader, 'import('),
			"loader.js porte un import() qui n'a ni la forme import(directory +'/lib/<fichier>.js') ni celle d'un module déclaré : une adresse de static/lib s'écrit nue, sans query ni suffixe, pour rester la seule adresse du fichier"
		);

		$this->assertTrue(isset($libraries[self::THREE]), "la sentinelle ne trouve plus ".self::THREE." dans base/scene/static/lib : elle ne sait plus lire ce qu'elle compare");
		$this->assertTrue(in_array(self::THREE, $loaded, TRUE), "loader.js ne charge plus ".self::THREE.", la version que les compléments de base/scene/static/lib visent");

		$wrong = [];
		$imported = [];

		foreach($libraries as $library => $code) {

			$specifiers = self::getSpecifiers($code);
			$three = FALSE;

			foreach($specifiers as $specifier) {

				$target = substr($specifier, 2);

				if(str_starts_with($specifier, './') === FALSE or isset($libraries[$target]) === FALSE) {
					$wrong[] = $library.' ('.$specifier.')';
					continue;
				}

				$imported[] = $target;

				if($target === self::THREE) {
					$three = TRUE;
				}

			}

			// Exigé de chaque complément, même de celui qui hérite d'un autre : un complément sans import
			// de three est un fichier dont la ligne d'import n'a pas été lue.
			if($library !== self::THREE and $three === FALSE) {
				$wrong[] = $library.' (aucun import de ./'.self::THREE.')';
			}

		}

		$this->assertEquals([], $wrong, "compléments de base/scene/static/lib dont un import ne vise pas un fichier du même dossier par chemin relatif (réécrire la ligne d'import du complément, three en './".self::THREE."', sans query) : ".implode(', ', $wrong));

		$absent = array_diff($loaded, array_keys($libraries));
		$orphan = array_diff(array_keys($libraries), $loaded, $imported);

		$this->assertEquals([], $absent, "fichiers que loader.js charge et qui manquent à base/scene/static/lib (loader.js fait foi : poser le fichier dans le dossier, à la version de three) : ".implode(', ', $absent));
		$this->assertEquals([], $orphan, "fichiers de base/scene/static/lib que ni loader.js ni un complément n'importent (loader.js fait foi : retirer le fichier du dossier, il y reviendra avec l'import qui le charge) : ".implode(', ', $orphan));

	}

}
