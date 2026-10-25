<?php
namespace map;

/**
 * L'année des arbres du décor (`static/js/sceneDecor.js`, `MapSceneDecor.calendar()` et le paramètre `calendar` du constructeur) :
 * le plan ne passe pas de calendrier, et le sien doit rester celui que les statiques du décor écrivent. Une scène qui suit une autre
 * année (le bourg de la compta, `accounting/town/static/js/townTrees.js`) en passe un, qui change ses nuanceurs, sa clé de
 * programme et les attributs que le décor cuit.
 *
 * Éprouvé sous node, sur la paire de modèles commune, avec des arbres de chaque kit à fruits ou à feuilles caduques. Aucune
 * écriture, aucune base ouverte.
 *
 * Lancement : dev/test package=map file=DecorCalendarAi
 */
class DecorCalendarAiTest extends \Test {

	/**
	 * Sans calendrier, le décor du plan cuit et teint ses arbres comme avec un calendrier écrit à la main d'après les statiques :
	 * mêmes nuanceurs, même clé de programme, mêmes attributs `season`, `anchor` et `position`, mêmes uniformes d'un bout à l'autre
	 * de l'année. Le calendrier par défaut est ce calendrier, valeur pour valeur.
	 */
	public function testThePlanKeepsItsYear(): void {

		$result = $this->run('
			const D = MapSceneDecor;
			const written = {
				late: D.LEAF_LATE_DAYS,
				grow: { from: MapSceneCrops.LEAF_ON, days: D.LEAF_FADE_DAYS },
				fall: { from: D.LEAF_FALL_START, days: D.LEAF_FALL_DAYS, fade: D.LEAF_FADE_DAYS },
				turn: { days: D.LEAF_TURN_DAYS, share: 1 },
				fruit: { from: D.FRUIT_ON, days: D.LEAF_FADE_DAYS, share: 1, fall: null },
				tint: null,
				crown: false
			};

			const none = read({});
			const plan = read({ calendar: written });

			emit({
				calendar: JSON.stringify(D.calendar()) === JSON.stringify(written),
				different: Object.keys(none).filter(key => none[key] !== plan[key]),
				key: none.key,
				tinted: none.fragment.includes("decorTint"),
				elements: none.elements
			});
		');

		$this->assertEquals(TRUE, $result['calendar'] ?? NULL, 'MapSceneDecor.calendar() rend les statiques du décor, valeur pour valeur');
		$this->assertEquals([], $result['different'] ?? NULL, 'sans calendrier, le décor du plan cuit et teint ses arbres comme avec celui des statiques');
		$this->assertEquals('map-scene-decor|map-scene-decor-depth', $result['key'] ?? NULL, 'le plan garde ses clés de programme');
		$this->assertEquals(FALSE, $result['tinted'] ?? NULL, 'le nuanceur du plan ne porte pas de table de teinte');
		$this->assertTrue(($result['elements'] ?? 0) >= 40, 'les arbres de chaque kit sont montés');

	}

	/**
	 * Un autre calendrier change ce que le décor cuit et ses nuanceurs, et ne partage pas le programme du plan : deux scènes
	 * ouvertes ensemble garderaient sinon le premier compilé.
	 */
	public function testAnotherYearIsItsOwn(): void {

		$result = $this->run('
			const D = MapSceneDecor;
			const other = {
				...D.calendar(),
				late: 16,
				fall: { from: 22, days: 33, fade: 33 },
				turn: { days: D.LEAF_TURN_DAYS, share: .5 },
				fruit: { from: 201, days: 30, share: 0, fall: { from: 13, days: 15 } },
				tint: [[0, [1.6, 1.5, .7]], [140, [1, 1, 1]], [300, [8.5, .42, .4]], [365, [1.6, 1.5, .7]]],
				crown: true
			};

			const none = read({});
			const town = read({ calendar: other });

			emit({ different: Object.keys(none).filter(key => none[key] !== town[key]), key: town.key.startsWith("map-scene-decor:"), tinted: town.fragment.includes("decorTint") });
		');

		$this->assertEquals(['vertex', 'fragment', 'depthVertex', 'key', 'attributes'], $result['different'] ?? NULL, 'un autre calendrier change les nuanceurs, la clé de programme et les attributs cuits, pas les uniformes du jour');
		$this->assertEquals(TRUE, $result['key'] ?? NULL, 'la clé de programme porte le calendrier');
		$this->assertEquals(TRUE, $result['tinted'] ?? NULL, 'une table de teinte entre dans le nuanceur');

	}

	/**
	 * `leaves()` et `fruits()` rendent ce que le nuanceur garde d'une feuille et d'un fruit, au jour que portent les uniformes, et
	 * `glide()` les fait avancer comme lui.
	 */
	public function testLeavesAndFruitsReadTheShaderYear(): void {

		$result = $this->run('
			const D = MapSceneDecor;
			const decor = make({});
			const smooth = (a, b, x) => { const k = Math.max(0, Math.min(1, (x - a) / (b - a))); return k * k * (3 - 2 * k); };
			const fade = D.LEAF_FADE_DAYS / D.LEAF_FALL_DAYS;
			const left = (late, spring, autumn) => smooth(late, late + D.LEAF_FADE_DAYS, spring) * (1 - smooth(0, fade, (autumn - late - D.LEAF_FALL_START) / D.LEAF_FALL_DAYS));

			let error = 0;
			let seen = 0;

			for(let day = 0; day < 365; day += 2) {

				const date = new Date(Date.UTC(2026, 0, 1 + day)).toISOString().slice(0, 10);
				decor.season(date, [1, 1, 1]);
				decor.glide(.5);

				const { decorAutumn, decorSpring } = decor.seasonUniforms;

				for(const late of [0, 3, 7]) {
					const leaves = left(late, decorSpring.value, decorAutumn.value.w);
					const fruits = left(late, decorSpring.value - (D.FRUIT_ON - MapSceneCrops.LEAF_ON), decorAutumn.value.w);
					error = Math.max(error, Math.abs(decor.leaves(late) - leaves), Math.abs(decor.fruits(late) - fruits));
					seen += (leaves > 0 && leaves < 1) ? 1 : 0;
				}

			}

			emit({ error, seen });
		');

		$this->assertTrue(($result['error'] ?? 1) < 1e-9, 'leaves() et fruits() suivent le nuanceur du plan');
		$this->assertTrue(($result['seen'] ?? 0) > 0, 'les feuilles poussent ou tombent au moins un jour lu');

	}

	/**
	 * Joue un scénario sous node avec les fichiers de la scène chargés comme le navigateur les charge, three et la paire de modèles
	 * commune, et rend ce qu'il émet. `make(options)` monte un décor de chaque kit à arbres sans écran, `read(options)` rend ce
	 * qu'il cuit et teint, en empreintes : ses nuanceurs après `onBeforeCompile`, ses clés de programme, ses attributs et ses
	 * uniformes un jour sur trois de l'année.
	 */
	private function run(string $scenario): array {

		$script = '
			const directory = '.json_encode(LIME_DIRECTORY).';
			const emit = value => process.stdout.write(JSON.stringify(value));

			// Les crochets de la cuisson font lire three en module ES, qu\'aucun package.json ne déclare tel
			require("node:module").register("file://" + directory + "/base/scene/bake/resolve.mjs");

			(async () => {
				const { readFileSync } = await import("node:fs");
				const { createHash } = await import("node:crypto");
				const vm = await import("node:vm");
				const lib = directory + "/base/scene/static/lib/";
				const THREE = await import("file://" + lib + "three-0.170.0.module.min.js");
				const { mergeGeometries, mergeVertices, toCreasedNormals } = await import("file://" + lib + "three-0.170.0.BufferGeometryUtils.js");
				const { sceneBuildModel } = await import("file://" + directory + "/base/scene/static/js/model.js");
				const modules = Object.fromEntries(await Promise.all(["geometry", "species", "stage", "crop", "fruit", "figure"].map(async name => [name, await import("file://" + directory + "/base/scene/static/js/" + name + ".js")])));

				const load = file => readFileSync(directory + "/" + file);
				const json = file => JSON.parse(load(file).toString("utf8"));
				const bin = load("base/scene/static/model/models.bin");
				const common = { index: json("base/scene/static/model/models.json"), buffer: bin.buffer.slice(bin.byteOffset, bin.byteOffset + bin.byteLength) };
				const catalog = { species: json("base/scene/model/species.json"), alias: json("base/scene/model/alias.json") };

				const source = ["base/scene/static/js/loader.js", "base/scene/static/js/plan.js", "base/scene/static/js/crew.js", "production/series/static/js/seriesSheet.js", "production/map/static/js/scene.js", "production/map/static/js/sceneLayer.js", "production/map/static/js/sceneDecor.js"].map(file => load(file).toString("utf8")).join("\n");
				const context = vm.createContext({ window: {}, document: { addEventListener: () => {} }, console, clearTimeout });
				const { SceneLoader, MapSceneDecor, MapSceneCrops, ScenePlanGeometry } = vm.runInContext(source + "\n;({ SceneLoader, MapSceneDecor, MapSceneCrops, ScenePlanGeometry })", context);

				const socle = SceneLoader.create({ three: { THREE, addons: { mergeGeometries, mergeVertices, toCreasedNormals }, sceneBuildModel }, modules, baked: common, catalog }, ["figure.prop"]);
				const sha = value => createHash("sha1").update(value).digest("hex");

				// Douze arbres de chaque kit à fruits ou à feuilles caduques, sur une zone qui les porte tous, autour de (0 ; 0)
				const coordinates = (x, n) => [x / 111320, n / 110540];
				const rows = ["arbre", "fruitier", "bosquet", "massif", "verger", "buissons"].flatMap((kit, k) => Array.from({ length: 12 }, (_, i) => ({
					id: k * 12 + i + 1, kit, seed: i * 7 + k + 1, size: 1, angle: i, coordinates: coordinates(k * 20, i * 15),
					trace: null, zone: null, plot: null, width: null, model: null, layer: null, diameter: null, laying: null
				})));
				const corners = [[-50, 250], [150, 250], [150, -50], [-50, -50], [-50, 250]].map(([x, n]) => coordinates(x, n));
				const forest = { center: [0, 0], zones: [{ id: 1, name: null, area: 0, coordinates: corners }], plots: [], beds: [], bedsDrawn: [], layers: [], decor: rows, decorAdopted: true, terrain: { grass: "tender", soil: "loam" } };

				const make = options => new MapSceneDecor({ socle, plan: forest, geometry: new ScenePlanGeometry(forest), parent: new THREE.Group(), gradientMap: null, diameterLabel: null, changed: () => {}, walked: () => {}, ...options });

				const read = options => {

					const decor = make(options);
					const shader = name => ({ uniforms: {}, vertexShader: THREE.ShaderLib[name].vertexShader, fragmentShader: THREE.ShaderLib[name].fragmentShader });
					const toon = shader("toon");
					const depth = shader("depth");

					decor.material.onBeforeCompile(toon);
					decor.depthMaterial.onBeforeCompile(depth);

					const attributes = [];

					for(const e of decor.elements) {
						e.root.traverse(object => {
							if(object.geometry !== undefined && object.geometry.attributes.season !== undefined) {
								attributes.push(["season", "anchor", "position"].map(name => sha(Buffer.from(object.geometry.attributes[name].array.buffer))).join(""));
							}
						});
					}

					const uniforms = [];

					for(let day = 0; day < 366; day += 3) {
						decor.season(new Date(Date.UTC(2026, 0, 1 + day)).toISOString().slice(0, 10), [1, .9, .8]);
						decor.glide(.5);
						uniforms.push(Object.values(decor.seasonUniforms).map(({ value }) => (typeof value === "number") ? value : value.toArray()));
					}

					const result = {
						vertex: toon.vertexShader, fragment: toon.fragmentShader, depthVertex: depth.vertexShader, depthFragment: depth.fragmentShader,
						key: decor.material.customProgramCacheKey() +"|"+ decor.depthMaterial.customProgramCacheKey(),
						attributes: sha(attributes.join("")), uniforms: sha(JSON.stringify(uniforms)), elements: decor.elements.length
					};

					decor.dispose();

					return result;

				};

				'.$scenario.'
			})().catch(error => {
				process.stderr.write(String(error.stack));
				process.exit(1);
			});
		';

		$process = proc_open(['node', '-'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, NULL, ['PATH' => getenv('PATH')]);

		if($this->assertTrue(is_resource($process), "node ne se lance pas ici : ce test éprouve le décor sous node, l'installer sur la machine qui lance les tests") === FALSE) {
			return [];
		}

		fwrite($pipes[0], $script);
		fclose($pipes[0]);

		$output = stream_get_contents($pipes[1]);
		$errors = stream_get_contents($pipes[2]);

		fclose($pipes[1]);
		fclose($pipes[2]);
		proc_close($process);

		$result = json_decode($output, TRUE);

		$this->assertTrue(is_array($result), 'node rend son résultat : '.$output.$errors);

		return $result ?? [];

	}

}
