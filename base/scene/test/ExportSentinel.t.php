<?php
namespace scene;

/**
 * Le socle 3D se donne par noms : une scène demande à `SceneLoader.loadCrop()` les seules fonctions
 * dont elle se sert (`'species.get'`, `'crop.layout'`…), et le reste de ce que rendent les fabriques
 * de `static/js/` ne lui parvient pas. Rien à l'exécution ne relie ces noms à ce que les fabriques
 * rendent vraiment avant qu'une scène s'ouvre sur un nom mort. Cette sentinelle tient l'inventaire
 * des deux côtés : chaque clé rendue par une fabrique est déclarée ici avec ce qu'elle garantit,
 * chaque nom demandé par un script du dépôt en fait partie, et aucun module du socle ne lit l'heure,
 * ne crée de scène, ne touche la page ni ne nomme la comptabilité, dont le code est fermé à la
 * publication.
 *
 * Il n'y a pas de contrôle « nom que personne ne demande » : la scène comptable en demande sept,
 * et elle est absente du dépôt publié, où ses noms paraitraient morts.
 *
 * Aucune écriture, aucune base ouverte : elle ne lit que des fichiers.
 *
 * Lancement : dev/test package=scene file=ExportSentinel
 */
class ExportSentinelTest extends \Test {

	/**
	 * Les noms qu'une scène peut demander, `<module>.<clé>`, avec ce que chacun garantit.
	 */
	const array EXPORTS = [
		'species.list' => "les entrées du catalogue, espèces dérivées d'un alias à couleurs comprises",
		'species.get' => "get(fqn, name) : l'entrée du fqn, sinon celle du nom sans casse ni espaces de bord, sinon celle de l'alias, sinon null, jamais une forme de remplacement",
		'species.woody' => "woody(entry) : vrai pour un arbre ou un arbuste, déjà formé, faux pour le fraisier et ce qui en dérive",
		'crop.models' => "models({ index, buffer }, list) : une Map slug → modèle fusionné depuis les fichiers cuits, avec bare, leaves, fruits, picked et dressed ou null",
		'crop.size' => "size(model, width, scale) : { d, h } du plant en mètres réels",
		'crop.pitch' => "pitch(model, d, density) : l'écart sur le rang en mètres réels",
		'crop.rows' => "rows(model) : le nombre de rangs que l'espèce déclare, ou celui de sa forme",
		'crop.layout' => "layout(model, { width, length, scale, density, minPitch, inset, whole, spacing }) : { size, pitch, rows, count, band }, pure, un arbre rendant un rang, whole pour une planche entière, spacing écartant les pieds sur le rang",
		'crop.scatter' => "scatter(model) : l'écart de pose désordonnée d'un parterre, 0 sinon",
		'crop.material' => "material({ gradientMap, wind, sway, leaves }) : le matériau toon des plants, vent et teinte d'instance compris",
		'crop.tint' => "tint(target, steps) : la teinte d'instance qui ramène un plant vers la paille, le gris ou la neige",
		'crop.pool' => "pool(parent, material, model, needed, pool) : le maillage instancié d'une espèce, agrandi au besoin, ajouté à parent",
		'crop.ridge' => "ridge(slug) : la butte d'une espèce buttée, ou null",
		'crop.ridgeGeometry' => "ridgeGeometry() : la géométrie unitaire d'une butte",
		'crop.placeRidges' => "placeRidges(parent, material, geometry, rows, pool) : les buttes posées en instances",
		'fruit.registry' => "les fruits de chaque espèce qui en porte, slug → random => fruits dans le repère du plant",
		'fruit.underground' => "les espèces dont l'organe récolté est sous terre",
		'fruit.shapes' => "shapes() : une Map nom → géométrie, une par forme de fruit",
		'fruit.material' => "material({ gradientMap }) : le matériau des fruits",
		'fruit.placePass' => "placePass(…) : la pose des fruits en générateur, que l'appelant reprend entre deux images",
		'fruit.place' => "place(…) : la même pose, d'une traite",
		'fruit.harvestedWhole' => "harvestedWhole(entry, cycle) : vrai si la récolte emporte le plant entier",
		'stage.week' => "week('AAAA-MM-JJ') : { year, week } ISO",
		'stage.monday' => "monday(year, week) : le lundi de la semaine ISO, en 'AAAA-MM-JJ'",
		'stage.timeline' => "timeline(series, tasks, harvests, season) : le calendrier prévu et réalisé d'une série, null sans ses deux bouts",
		'stage.at' => "at(timeline, date, mode) : { key, on, progress, growth } à une date, key sans libellé",
		'stage.harvestWeeks' => "harvestWeeks(timeline, mode) : la première et la dernière semaine de récolte, ou null",
		'figure.assemble' => "assemble(baked, slug, material, { tool }) : un personnage cuit monté en os autour de ses pivots, { root, bones, pivots, anchors, h, torso, rest, legs, arms, fists, tool, knees, soles }, fists le poing de chaque bras dans le repère de son épaule, tool l'outil du bras gauche monté à part avec sa place portée, null sans tool, knees null pour un corps sans tibias, soles (talon et pointe de chaque pied) null pour un corps sans jambes, une pièce colorée par sommet gardant ses couleurs",
		'figure.prop' => "prop(baked, slug, material, colors) : un objet sans os en un maillage, la couleur d'une pièce remplacée par son nom, une pièce colorée par sommet gardant ses couleurs sauf si son nom est remplacé",
		'figure.pose' => "pose(figure, geste, { phase, time, carry, push, depth }) : walk, hoe, crouch ou stand, et de combien le corps descend sous ses pieds ; depth plie l'accroupi en part de la pose entière ; carry { crate, hold, lift } place la caisse dans le buste et les mains contre elle ; push { grips, bend } pose les bras sur les poignées d'une brouette en marchant ; l'outil se porte en walk et en stand",
		'figure.ground' => "ground(figure) : de combien le corps descend pour que le plus bas de ses talons et de ses pointes touche le sol, ses os tels qu'ils sont, en mètres du modèle",
		'figure.hold' => "hold(figure, caisse) : la prise d'une caisse serrée contre la poitrine, { hands, low, high, height, depth } en unités du buste",
		'avatar.build' => "build(fiche) : le personnage du skill 3d-perso habillé selon la fiche, { d, h, aligned, parts, pivots, parents, anchors, expressions, emotions, emotion, poses, animations } au contrat d'un modèle, debout, sans outil ; coudes avantBrasG et avantBrasD, genoux tibiaG et tibiaD, chaque trait du visage sur l'os de son rôle, paupiereG et paupiereD qui ferment l'œil ramenées à plat en y ; emotions { <nom>: { pose, visage } }, visage par rôle dans le repère du modèle ; descenteAssis dans anchors ; poses, celles de POSES avec la descente qui pose le pied le plus bas de cette personne au sol, et la descente de chaque émotion mesurée de même ; animations { <nom>: { periode, fixe, cles, visages } }, les clés des gestes d'ANIMATIONS calculées sur cette personne",
		'avatar.POSES' => "les postures du personnage par nom, angles par os au nom du contrat (le mollet en tibia), descente en part de descenteAssis, expression éventuelle de la bouche",
		'avatar.EMOTIONS' => "les émotions du personnage par nom, angles par os au nom du contrat, descente, posture de départ (base) et traits du visage",
		'avatar.poseEmotion' => "poseEmotion(nom) : les angles par os d'une émotion posée sur sa posture de départ, au nom du contrat, descente comprise",
		'avatar.ANIMATIONS' => "les gestes animés de l'avatar par nom (boire, parler, arroser, saluer), { periode, objet, fixe } : la durée du cycle en secondes, l'entrée du catalogue à mettre en main ou null, l'instant de l'image fixe",
		'avatar.animer' => "animer(modele, nom, t) : ce que montre le geste `nom` de l'avatar rendu par build() à `t` secondes de son début, sur l'horloge de l'appelant, { pose, visage }, pose au format de poses, visage par rôle ou null ; t null ou absent rend l'image fixe, celle de prefers-reduced-motion",
		'building.build' => "build({ type, length, width, height, settings }) : un bâtiment de la structure, tel que map\\DecorBuilding le porte, au contrat d'un modèle { d, h, aligned, parts, anchors }, ou null s'il ne se compose pas, sans jamais lever ; un choix absent prend celui du type dans choices.json",
		'building.construct' => "construct(fiche) : une fiche du skill 3d-batiment au contrat d'un modèle { d, h, aligned, parts, anchors }, une erreur qui nomme tous les écarts si elle ne se résout pas",
	];

	/**
	 * Les noms que `SceneLoader.create()` ajoute à ce que rend une fabrique, demandables comme ceux d'EXPORTS : ils ne
	 * viennent pas du module, que la comparaison des fabriques lit seul, mais de `loader.js`, qui leur passe ce que le
	 * module n'importe pas.
	 */
	const array LOADER = [
		'building.uploaded' => "uploaded(index, buffer) : un bâtiment téléversé, cuit par base/scene/upload/upload.mjs sous la famille building, au contrat d'un modèle { d, h, aligned, parts, anchors }, ses pièces vitre, vantailGauche et vantailDroit nommées quand le fichier les porte",
	];

	/**
	 * Ce que rend `sceneCreateGeometry()`, qui n'est pas demandable : `SceneLoader` le passe aux
	 * fabriques de `crop`, `fruit` et `figure`, seule place des pièces de géométrie du socle.
	 */
	const array GEOMETRY = [
		'flat' => "une géométrie sans index, attributs recopiés par sommet",
		'facets' => "des normales par face",
		'smooth' => "des normales lissées sous l'angle de pli",
		'paint' => "une pièce à une couleur unie, posée sur chaque sommet",
		'merge' => "plusieurs pièces en une géométrie",
		'ico' => "un icosaèdre",
		'cone' => "un cône fermé",
		'triangles' => "le nombre de triangles d'une géométrie",
	];

	/**
	 * Ce qu'un module du socle ne fait jamais : il reçoit l'heure, la scène, la page et les fichiers
	 * de l'appelant, pour que deux scènes d'une même page ne se le disputent pas et que la preuve
	 * sous node rejoue ce que voit le navigateur.
	 */
	const array FORBIDDEN = [
		'/\bDate\.now\b/' => "l'heure lue (Date.now)",
		'/\bnew\s+Date\b(?!\s*\(\s*[^\s)])/' => "l'heure lue (new Date sans argument)",
		'/(?<![\w$.])(?<!new )Date\s*\(/' => "l'heure lue (Date(), qui rend la date du jour en texte)",
		'/\bperformance\.now\b/' => "l'heure lue (performance.now)",
		'/\brequestAnimationFrame\b/' => "une boucle d'images (requestAnimationFrame)",
		'/\bnew\s+THREE\.Scene\b/' => "une scène créée (new THREE.Scene)",
		'/\bfetch\s*\(/' => "un fichier lu (fetch)",
		'/\bdocument\./' => "la page touchée (document.)",
	];

	/**
	 * Les scripts classiques du socle, sans fabrique, qui tiennent la même pureté que les modules. Ils
	 * ne sont pas des modules ES parce que leurs appelants les lisent en synchrone, là où un module
	 * demanderait d'attendre son import : chaque entrée dit comment.
	 */
	const array CLASSIC = [
		'plan.js' => "le plan projeté en mètres et le foncier rapproché, ScenePlanGeometry et ScenePlanLand : la scène et son décor y prennent leur géométrie au milieu d'un calcul, qui n'attend pas d'import",
		'crew.js' => "l'équipe au travail dans les passe-pieds, SceneCrew : elle lit ScenePlanGeometry en classe globale, et scene.js la lit (SceneCrew.turnGap, BARROW_AHEAD) dans sa boucle d'images, qui n'attend pas d'import",
	];

	/**
	 * Les scripts de static/js/ sans fabrique que la pureté ne tient pas, chacun parce que ce qu'elle
	 * interdit est son rôle. Un script sans fabrique qui n'est ni ici ni dans CLASSIC est refusé : il
	 * échapperait sinon au contrôle sans que personne l'ait décidé.
	 */
	const array OUTSIDE = [
		'loader.js' => "SceneLoader, qui importe three et lit les fichiers cuits",
		'model.js' => "le lecteur des fichiers cuits, sceneBuildModel, que la cuisson exécute aussi hors d'un navigateur",
		'scroll.js' => "SceneScroll, qui lit la page",
		'fullscreen.js' => "SceneFullscreen, qui met la page en plein écran",
		'dropdown.js' => "SceneDropdown, qui déplace une liste ouverte dans la page",
		'speed.js' => "SceneSpeed, qui lit et écrit la page",
		'swap.js' => "SceneSwap, qui cache et montre des boutons de la page et y déplace le focus",
		'upload.js' => "SceneUploadDrop, qui lit et écrit le dépôt d'un modèle téléversé et poste le fichier",
	];

	/**
	 * La comptabilité, cherchée partout, commentaires compris : le socle est publié, elle ne l'est pas.
	 */
	const string ACCOUNTING = '/\b(?:accounting|comptab\w*|compta|journal\w*|factur\w*|invoic\w*|ledger\w*|tva|vat)\b/i';

	private function getDirectory(): string {
		return LIME_DIRECTORY.'/base/scene/static/js';
	}

	/**
	 * Deux copies du code à la même longueur, octet pour octet : `code` sans les commentaires, et
	 * `skeleton` sans les commentaires ni le contenu des chaines, gabarits et expressions régulières.
	 * La même longueur permet de chercher la structure dans le squelette, où une accolade de texte ne
	 * compte pas, puis de relire le texte au même endroit.
	 *
	 * @return array{code: string, skeleton: string}
	 */
	private static function strip(string $source): array {

		$code = '';
		$skeleton = '';
		$length = strlen($source);

		// Une accolade fermante ne rend au gabarit que lorsqu'elle ferme le `${` qui l'en a fait sortir
		$templates = [];
		$depth = 0;
		$inTemplate = FALSE;

		$blank = fn(string $text) => preg_replace('/[^\n]/', ' ', $text);
		$keep = function(string $both, ?string $hidden = NULL) use (&$code, &$skeleton) {
			$code .= $both;
			$skeleton .= $hidden ?? $both;
		};

		$i = 0;

		while($i < $length) {

			$char = $source[$i];
			$next = $source[$i + 1] ?? '';

			if($inTemplate) {

				if($char === '\\') {
					$keep(substr($source, $i, 2), $blank(substr($source, $i, 2)));
					$i += 2;
				} else if($char === '`') {
					$keep('`');
					$inTemplate = FALSE;
					$i++;
				} else if($char === '$' and $next === '{') {
					$keep('${');
					$templates[] = $depth;
					$depth = 0;
					$inTemplate = FALSE;
					$i += 2;
				} else {
					$keep($char, $blank($char));
					$i++;
				}

				continue;

			}

			if($char === '/' and $next === '/') {

				$end = strpos($source, "\n", $i);
				$end = ($end === FALSE) ? $length : $end;
				$comment = substr($source, $i, $end - $i);
				$keep($blank($comment));
				$i = $end;

			} else if($char === '/' and $next === '*') {

				$end = strpos($source, '*/', $i + 2);
				$end = ($end === FALSE) ? $length : $end + 2;
				$keep($blank(substr($source, $i, $end - $i)));
				$i = $end;

			} else if($char === '\'' or $char === '"') {

				$end = $i + 1;
				while($end < $length and $source[$end] !== $char and $source[$end] !== "\n") {
					$end += ($source[$end] === '\\') ? 2 : 1;
				}

				$inner = substr($source, $i + 1, $end - $i - 1);
				$keep($char.$inner.$char, $char.$blank($inner).$char);
				$i = $end + 1;

			} else if($char === '`') {

				$keep('`');
				$inTemplate = TRUE;
				$i++;

			} else if($char === '/' and self::startsRegex($skeleton)) {

				$end = $i + 1;
				$class = FALSE;

				while($end < $length and $source[$end] !== "\n" and ($source[$end] !== '/' or $class)) {

					if($source[$end] === '\\') {
						$end += 2;
						continue;
					}

					if($source[$end] === '[') {
						$class = TRUE;
					} else if($source[$end] === ']') {
						$class = FALSE;
					}

					$end++;

				}

				$inner = substr($source, $i + 1, $end - $i - 1);
				$keep('/'.$inner.'/', '/'.$blank($inner).'/');
				$i = $end + 1;

			} else {

				if($char === '{') {
					$depth++;
				} else if($char === '}') {

					if($depth === 0 and $templates !== []) {
						$depth = array_pop($templates);
						$keep('}');
						$inTemplate = TRUE;
						$i++;
						continue;
					}

					$depth--;

				}

				$keep($char);
				$i++;

			}

		}

		return ['code' => $code, 'skeleton' => $skeleton];

	}

	/**
	 * Une barre oblique ouvre une expression régulière là où le code attend une valeur : après un
	 * opérateur, une ouverture, ou un mot qui précède une valeur. Ailleurs, elle divise.
	 */
	private static function startsRegex(string $before): bool {

		$before = rtrim($before);

		if($before === '') {
			return TRUE;
		}

		if(preg_match('/\b(?:return|typeof|case|do|else|in|of|new|delete|void|throw|yield|await)$/', $before) === 1) {
			return TRUE;
		}

		return str_contains('(,=:[!&|?{};+-*%<>~^', substr($before, -1));

	}

	/**
	 * La position de ce qui ferme l'ouverture placée à `$open`, toutes paires confondues.
	 */
	private static function getClose(string $skeleton, int $open): int {

		$depth = 0;
		$length = strlen($skeleton);

		for($i = $open; $i < $length; $i++) {

			if(str_contains('([{', $skeleton[$i])) {
				$depth++;
			} else if(str_contains(')]}', $skeleton[$i])) {

				$depth--;

				if($depth === 0) {
					return $i;
				}

			}

		}

		return -1;

	}

	/**
	 * Les morceaux séparés par une virgule hors de toute paire, en positions [début, fin[.
	 *
	 * @return array<array{int, int}>
	 */
	private static function split(string $skeleton, int $start, int $end): array {

		$parts = [];
		$depth = 0;
		$from = $start;

		for($i = $start; $i < $end; $i++) {

			if(str_contains('([{', $skeleton[$i])) {
				$depth++;
			} else if(str_contains(')]}', $skeleton[$i])) {
				$depth--;
			} else if($skeleton[$i] === ',' and $depth === 0) {
				$parts[] = [$from, $i];
				$from = $i + 1;
			}

		}

		$parts[] = [$from, $end];

		return array_values(array_filter($parts, fn(array $part) => trim(substr($skeleton, $part[0], $part[1] - $part[0])) !== ''));

	}

	/**
	 * Chaque fabrique `export function sceneCreate<Module>()` d'un code, avec les clés de l'objet
	 * littéral que rend son dernier `return {` de premier niveau, ou la raison pour laquelle elles ne
	 * se lisent pas.
	 *
	 * @return array<string, string[]|string> module en minuscule initiale → clés, ou raison
	 */
	private static function getFactories(string $source): array {

		$skeleton = self::strip($source)['skeleton'];
		$factories = [];

		preg_match_all('/\bexport\s+function\s+sceneCreate(\w+)\s*\(/', $skeleton, $matches, PREG_OFFSET_CAPTURE);

		foreach($matches[0] as $index => [$match, $offset]) {

			$module = lcfirst($matches[1][$index][0]);
			$parameters = self::getClose($skeleton, $offset + strlen($match) - 1);
			$open = strpos($skeleton, '{', $parameters);
			$close = self::getClose($skeleton, $open);

			if($parameters === -1 or $open === FALSE or $close === -1) {
				$factories[$module] = "corps de la fabrique introuvable";
				continue;
			}

			$return = NULL;
			$depth = 0;

			for($i = $open + 1; $i < $close; $i++) {

				if(str_contains('([{', $skeleton[$i])) {
					$depth++;
				} else if(str_contains(')]}', $skeleton[$i])) {
					$depth--;
				} else if($depth === 0 and preg_match('/\Greturn\s*\{/', $skeleton, $found, 0, $i) === 1 and preg_match('/[\w$]/', $skeleton[$i - 1]) === 0) {
					$return = $i + strlen($found[0]) - 1;
				}

			}

			if($return === NULL) {
				$factories[$module] = "aucun return { de premier niveau";
				continue;
			}

			$keys = [];

			foreach(self::split($skeleton, $return + 1, self::getClose($skeleton, $return)) as [$start, $end]) {

				$entry = trim(substr($skeleton, $start, $end - $start));

				if(str_starts_with($entry, '...')) {
					$factories[$module] = "un étalement (".$entry.") cache les clés qu'il apporte";
					continue 2;
				}

				if(preg_match('/^(?:async\s+)?(?:\*\s*)?([A-Za-z_$][\w$]*)\s*(?:[:(]|$)/', $entry, $key) !== 1) {
					$factories[$module] = "entrée illisible (".substr($entry, 0, 40).")";
					continue 2;
				}

				$keys[] = $key[1];

			}

			$factories[$module] = $keys;

		}

		return $factories;

	}

	/**
	 * Les appels `.loadCrop(conteneur, [noms])` d'un code : la liste des noms demandés quand elle est
	 * écrite en littéral, sinon le texte de l'appel.
	 *
	 * @return array<array{names: string[]|null, call: string}>
	 */
	private static function getCalls(string $source): array {

		['code' => $code, 'skeleton' => $skeleton] = self::strip($source);
		$calls = [];

		preg_match_all('/\.loadCrop\s*\(/', $skeleton, $matches, PREG_OFFSET_CAPTURE);

		foreach($matches[0] as [$match, $offset]) {

			$open = $offset + strlen($match) - 1;
			$close = self::getClose($skeleton, $open);
			$call = trim(preg_replace('/\s+/', ' ', substr($code, $offset, ($close === -1 ? 60 : $close - $offset + 1))));

			$arguments = ($close === -1) ? [] : self::split($skeleton, $open + 1, $close);
			$names = NULL;

			if(count($arguments) === 2) {

				[$start, $end] = $arguments[1];
				$list = trim(substr($code, $start, $end - $start));

				if(preg_match('/^\[\s*(?:([\'"])[\w.]+\1\s*,\s*)*(?:([\'"])[\w.]+\2\s*,?\s*)?\]$/', $list) === 1) {
					preg_match_all('/([\'"])([\w.]+)\1/', $list, $found);
					$names = $found[2];
				}

			}

			$calls[] = ['names' => $names, 'call' => $call];

		}

		return $calls;

	}

	public function testDetectors(): void {

		$this->assertEquals(
			['code' => "a = '}'; b = `x\${ {c: 1}.c }y`;    \nd = /[/]/g; e = f / 2;",
			 'skeleton' => "a = ' '; b = ` \${ {c: 1}.c } `;    \nd = /   /g; e = f / 2;"],
			self::strip("a = '}'; b = `x\${ {c: 1}.c }y`; //{\nd = /[/]/g; e = f / 2;")
		);

		$this->assertEquals(['crop' => ['size', 'layout', 'models', 'placePass']], self::getFactories("export function sceneCreateCrop(THREE, { a, b }) {\n\tconst inner = () => { return { wrong: 1 }; };\n\treturn {\n\t\t// une note, avec virgule\n\t\tsize,\n\t\tlayout: (x) => '{', models(baked, list) { return { other: 1 }; },\n\t\t*placePass() {},\n\t};\n}"));
		$this->assertEquals(['fruit' => []], self::getFactories("export function sceneCreateFruit() {\n\treturn {};\n}"));
		$this->assertTrue(is_string(self::getFactories("export function sceneCreateFruit() {\n\treturn { ...shared, place };\n}")['fruit']));
		$this->assertTrue(is_string(self::getFactories("export function sceneCreateFruit() {\n\treturn api;\n}")['fruit']));
		$this->assertTrue(is_string(self::getFactories("export function sceneCreateFruit() {\n\treturn { 'place': 1 };\n}")['fruit']));

		$this->assertEquals(
			[['names' => ['species.get', 'crop.layout'], 'call' => ".loadCrop(this.qs('#a, #b'), ['species.get', \"crop.layout\",])"]],
			self::getCalls("const scene = await SceneLoader.loadCrop(this.qs('#a, #b'), ['species.get', // la scène\n\"crop.layout\",]);")
		);
		$this->assertEquals([NULL, NULL, NULL], array_column(self::getCalls("SceneLoader.loadCrop(node, names); SceneLoader.loadCrop(node, ['a.b', ...more]); SceneLoader.loadCrop(['a.b']);"), 'names'));
		$this->assertEquals([], self::getCalls("this.uploadCrop(context, id, files); static loadCrop(container, names) {} // SceneLoader.loadCrop(x, ['y.z'])"));

		$forbidden = fn(string $code) => array_values(array_filter(self::FORBIDDEN, fn(string $pattern) => preg_match($pattern, $code) === 1, ARRAY_FILTER_USE_KEY));
		$this->assertEquals(4, count(array_merge(...array_map($forbidden, ['const a = new Date;', 'const a = new Date( );', 'const a = Date();', 'const a = Date.now();']))));
		$this->assertEquals([], array_merge(...array_map($forbidden, ['new Date(day * DAY)', 'Date.UTC(year, 0, 4)', 'new Date(january4 * DAY).getUTCDay()', 'toDate(text)'])));

		$this->assertEquals(1, preg_match(self::ACCOUNTING, 'la scène comptable'));
		$this->assertEquals(0, preg_match(self::ACCOUNTING, 'le comptage des plants, la vatte, une revue'));

	}

	public function testFactoriesReturnWhatIsDeclared(): void {

		$declared = ['geometry' => array_keys(self::GEOMETRY)];
		foreach(array_keys(self::EXPORTS) as $name) {
			[$module, $key] = explode('.', $name, 2);
			$declared[$module][] = $key;
		}

		$found = [];
		$broken = [];
		$unread = [];

		foreach(glob($this->getDirectory().'/*.js') as $path) {

			$file = basename($path);

			foreach(self::getFactories(file_get_contents($path)) as $module => $keys) {

				if($module.'.js' !== $file or isset($found[$module])) {
					$broken[] = $file.' (sceneCreate'.ucfirst($module).' : une seule fabrique par module, nommée d\'après son fichier)';
					$unread[] = $module;
				} else if(is_string($keys)) {
					$broken[] = $file.' ('.$keys.')';
					$unread[] = $module;
				} else if($keys === []) {
					$broken[] = $file.' (la fabrique ne rend aucune clé)';
					$unread[] = $module;
				} else {
					$found[$module] = $keys;
				}

			}

		}

		$this->assertEquals([], $broken, "fabriques de base/scene/static/js dont la sentinelle ne lit pas les clés (une fabrique par fichier, export function sceneCreate<Fichier>(), qui rend en dernier un objet littéral aux clés écrites, sans étalement) : ".implode(', ', $broken));

		$new = [];
		$stale = [];

		// Une fabrique illisible ne dit rien de ses clés : la comparer ferait retirer des déclarations justes
		foreach(array_diff(array_unique(array_merge(array_keys($declared), array_keys($found))), $unread) as $module) {

			$label = ($module === 'geometry') ? 'GEOMETRY' : 'EXPORTS';

			foreach(array_diff($found[$module] ?? [], $declared[$module] ?? []) as $key) {
				$new[] = $module.'.'.$key.' ('.$label.')';
			}

			foreach(array_diff($declared[$module] ?? [], $found[$module] ?? []) as $key) {
				$stale[] = $module.'.'.$key.' ('.$label.')';
			}

		}

		$this->assertEquals([], $new, "clés rendues par une fabrique du socle sans être déclarées (la fabrique fait foi : déclarer la clé avec ce qu'elle garantit, ou la garder hors de l'objet rendu si elle est interne) : ".implode(', ', $new));
		$this->assertEquals([], $stale, "noms déclarés qu'aucune fabrique ne rend plus (la fabrique fait foi : retirer la déclaration, après avoir ôté le nom des listes de loadCrop() qui le demandent, que testCallersAskDeclaredNames nomme) : ".implode(', ', $stale));

	}

	public function testLoaderAddsDeclaredNames(): void {

		$loader = file_get_contents($this->getDirectory().'/loader.js');

		$stale = [];

		foreach(array_keys(self::LOADER) as $name) {

			[$module, $key] = explode('.', $name, 2);

			// La clé s'écrit dans la fabrique du module, à côté de l'étalement de ce qu'il rend
			if(preg_match('/\b'.$module.': \(\) => \(\{\s*\.\.\.modules\.'.$module.'\.sceneCreate'.ucfirst($module).'\([^)]*\),\s*'.$key.':/', $loader) !== 1) {
				$stale[] = $name;
			}

		}

		$this->assertEquals([], $stale, "noms de LOADER que la fabrique de SceneLoader.create() n'ajoute plus (loader.js fait foi : retirer la déclaration, ou rétablir la clé à côté de l'étalement de sceneCreate<Module>()) : ".implode(', ', $stale));

	}

	public function testCallersAskDeclaredNames(): void {

		// Tous les scripts servis, suivis ou non : un appel écrit dans l'arbre et pas encore commité
		// est déjà un appel qui s'ouvrira sur un nom mort, et le nom qu'il demande ne dépend pas de git.
		$scripts = glob(LIME_DIRECTORY.'/*/*/static/js/*.js');

		$this->assertTrue(count($scripts) > 100, "la sentinelle ne trouve plus les scripts de */*/static/js : elle ne sait plus lire ce qu'elle compare");

		$unknown = [];
		$opaque = [];

		foreach($scripts as $path) {

			$file = substr($path, strlen(LIME_DIRECTORY) + 1);

			foreach(self::getCalls(file_get_contents($path)) as $call) {

				if($call['names'] === NULL) {
					$opaque[] = $file.' ('.$call['call'].')';
					continue;
				}

				foreach(array_diff($call['names'], array_keys(self::EXPORTS), array_keys(self::LOADER)) as $name) {
					$unknown[] = $file.' ('.$name.')';
				}

			}

		}

		$this->assertEquals([], $unknown, "noms demandés à SceneLoader.loadCrop() hors de EXPORTS et de LOADER, qui lèveront à l'ouverture de la scène (EXPORTS fait foi : corriger le nom dans le script, ou ajouter d'abord la clé à la fabrique puis la déclarer) : ".implode(', ', $unknown));
		$this->assertEquals([], $opaque, "appels de SceneLoader.loadCrop() dont la liste des noms n'est pas écrite en littéral, que la sentinelle ne peut pas lire (écrire loadCrop(conteneur, ['module.nom', …]) avec les noms en clair) : ".implode(', ', $opaque));

	}

	public function testModulesStayPure(): void {

		$found = [];
		$read = 0;
		$classic = [];
		$outside = [];
		$undeclared = [];

		foreach(glob($this->getDirectory().'/*.js') as $path) {

			$source = file_get_contents($path);
			$file = basename($path);

			if(isset(self::CLASSIC[$file])) {
				$classic[] = $file;
			} else if(self::getFactories($source) === []) {

				if(isset(self::OUTSIDE[$file])) {
					$outside[] = $file;
				} else {
					$undeclared[] = $file;
				}

				continue;

			} else {
				$read++;
			}

			$skeleton = self::strip($source)['skeleton'];

			foreach(self::FORBIDDEN as $pattern => $what) {
				if(preg_match($pattern, $skeleton) === 1) {
					$found[] = $file.' ('.$what.')';
				}
			}

			if(preg_match_all(self::ACCOUNTING, $source, $words) > 0) {
				$found[] = $file.' (la comptabilité nommée : '.implode(', ', array_unique($words[0])).')';
			}

		}

		$this->assertTrue($read >= count(array_unique(array_map(fn(string $name) => explode('.', $name)[0], array_keys(self::EXPORTS)))) + 1, "la sentinelle lit moins de fabriques que EXPORTS et GEOMETRY ne déclarent de modules : une fabrique a disparu ou ne se lit plus, testFactoriesReturnWhatIsDeclared nomme laquelle");
		$this->assertEquals([], array_values(array_diff(array_keys(self::CLASSIC), $classic)), "scripts classiques déclarés dans CLASSIC qui ne sont plus dans base/scene/static/js (retirer la déclaration, ou la porter au nom du fichier qui les a repris)");
		$this->assertEquals([], array_values(array_diff(array_keys(self::OUTSIDE), $outside)), "scripts déclarés dans OUTSIDE qui ne sont plus dans base/scene/static/js, ou qui portent désormais une fabrique (retirer la déclaration)");
		$this->assertEquals([], $undeclared, "scripts de base/scene/static/js sans fabrique, déclarés ni dans CLASSIC ni dans OUTSIDE, que la pureté ne contrôle donc pas (le déclarer dans CLASSIC s'il est du socle, dans OUTSIDE avec sa raison sinon) : ".implode(', ', $undeclared));
		$this->assertEquals([], $found, "modules du socle 3D qui font eux-mêmes ce que l'appelant leur passe, ou qui nomment la comptabilité, absente du dépôt publié (le recevoir en argument de la fabrique ou de la fonction) : ".implode(', ', $found));

	}

}
