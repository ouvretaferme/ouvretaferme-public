<?php
namespace map;

/**
 * Le calque « Parcelles et jardins » de la scène (`static/js/sceneLimits.js`, `MapSceneLimits`), éprouvé sous node
 * tel que le navigateur charge le fichier : un trait par côté de parcelle et de jardin tracé, aucun pour un jardin qui occupe sa
 * parcelle, chaque sommet sur le sol rendu du relief, aucun triangle, et un calque éteint tant que l'utilisateur ne l'a pas allumé.
 *
 * Aucune écriture, aucune base ouverte : le plan, la grille d'altitudes, le stockage et la carte des calques sont écrits dans le
 * scénario.
 *
 * Lancement : dev/test package=map file=SceneLimitsAi
 */
class SceneLimitsAiTest extends \Test {

	public function testOutlinesFollowTheGround(): void {

		$result = $this->run();

		$this->assertEquals(4 * 2, $result['flat']['zone'], 'à plat, un segment par côté de chacune des deux parcelles');
		$this->assertEquals(4, $result['flat']['plot'], 'à plat, un segment par côté du seul jardin tracé, rien pour celui qui occupe sa parcelle');
		$this->assertTrue(($result['flat']['lift'] ?? 0) === 1, 'à plat, tous les sommets à la levée du calque');
		$this->assertTrue($result['flat']['dashed'], 'le trait du jardin porte ses distances, sans quoi il ne se dessine pas en tirets');

		$this->assertTrue(($result['relief']['zone'] ?? 0) > 4 * 2, 'sur le relief, les côtés se coupent aux triangles du sol');
		$this->assertTrue(($result['relief']['gap'] ?? 1) < 1e-6, 'sur le relief, chaque sommet à la levée au-dessus du sol rendu');

		$this->assertTrue(($result['carved'] ?? 1) < 1e-6, 'un sol qui descend refait le tracé à la hauteur neuve');
		$this->assertEquals(0, $result['meshes'], 'des lignes seules, aucun triangle');

	}

	public function testTheLayerIsOffUntilTurnedOn(): void {

		$result = $this->run()['toggle'] ?? [];

		$this->assertEquals([FALSE, 'field-switch-off'], $result['start'] ?? NULL, 'éteint par défaut');
		$this->assertEquals([TRUE, 'field-switch-on', '1'], $result['on'] ?? NULL, 'la ligne l\'allume et le retient pour la structure et l\'utilisateur');
		$this->assertEquals([FALSE, 'field-switch-off', NULL], $result['off'] ?? NULL, 'le second clic l\'éteint et oublie le choix');
		$this->assertEquals([TRUE, 'field-switch-on'], $result['remembered'] ?? NULL, 'une scène remontée retrouve le calque allumé');
		$this->assertEquals([FALSE, 'field-switch-off'], $result['blocked'] ?? NULL, 'sans stockage, éteint');
		$this->assertEquals('map-scene-limits-shown-4087-12', $result['key'] ?? NULL, 'la clé porte la structure et l\'utilisateur');

	}

	private ?array $result = NULL;

	private function run(): array {

		if($this->result !== NULL) {
			return $this->result;
		}

		$script = '
			const directory = '.json_encode(LIME_DIRECTORY).';
			const emit = value => process.stdout.write(JSON.stringify(value));

			// Les crochets de la cuisson font lire three en module ES, qu\'aucun package.json ne déclare tel
			require("node:module").register("file://" + directory + "/base/scene/bake/resolve.mjs");

			(async () => {
				const { readFileSync } = await import("node:fs");
				const vm = await import("node:vm");
				const THREE = await import("file://" + directory + "/base/scene/static/lib/three-0.170.0.module.min.js");

				const storage = new Map();
				const localStorage = {
					blocked: false,
					getItem(key) { if(this.blocked) { throw new Error("bloqué"); } return storage.get(key) ?? null; },
					setItem(key, value) { if(this.blocked) { throw new Error("bloqué"); } storage.set(key, value); },
					removeItem(key) { if(this.blocked) { throw new Error("bloqué"); } storage.delete(key); }
				};

				const document = { addEventListener: () => {}, createElement: () => ({ getContext: () => new Proxy({}, { get: () => () => ({}) }) }) };
				const source = ["base/scene/static/js/plan.js", "base/scene/static/js/crew.js", "production/series/static/js/seriesSheet.js", "production/map/static/js/scene.js", "production/map/static/js/sceneLayer.js", "production/map/static/js/sceneDecor.js", "production/map/static/js/sceneLimits.js"].map(file => readFileSync(directory + "/" + file, "utf8")).join("\n");
				const { MapSceneRelief, MapSceneDecor, MapSceneLimits } = vm.runInContext(source + "\n;({ MapSceneRelief, MapSceneDecor, MapSceneLimits })", vm.createContext({ window: {}, document, console, localStorage }));

				// Deux parcelles de 40 m sur 30 m, l\'une portant un jardin tracé et l\'autre un jardin qui l\'occupe
				const square = (x, n, w, d) => [{ x, n }, { x: x + w, n }, { x: x + w, n: n + d }, { x, n: n + d }];
				const geometry = {
					relief: null,
					zones: new Map([[1, square(-50, -20, 40, 30)], [2, square(10, -20, 40, 30)]]),
					plots: new Map([
						[11, { drawn: true, points: square(-45, -15, 20, 10) }],
						[12, { drawn: false, points: square(10, -20, 40, 30) }]
					])
				};

				const ground = (positions, a, b, lift) => MapSceneDecor.prototype.groundLine.call({ geometry }, positions, a, b, lift);
				const count = line => line.geometry.attributes.position.count / 2;
				const lift = MapSceneLimits.LIFT;
				const result = {};

				let limits = new MapSceneLimits({ THREE, geometry, ground, parent: new THREE.Group() });
				const flatY = [...limits.lines.zone.geometry.attributes.position.array, ...limits.lines.plot.geometry.attributes.position.array].filter((v, i) => i % 3 === 1);
				result.flat = {
					zone: count(limits.lines.zone),
					plot: count(limits.lines.plot),
					lift: flatY.every(y => Math.abs(y - lift) < 1e-6) ? 1 : 0,
					dashed: limits.lines.plot.geometry.attributes.lineDistance !== undefined
				};

				let meshes = 0;
				limits.group.traverse(object => meshes += object.isMesh ? 1 : 0);
				result.meshes = meshes;

				// Une grille de 200 m au pas de 4 m, en creux et en bosses de quelques mètres, comme dans ReliefGroundAi
				const relief = Object.create(MapSceneRelief.prototype);
				Object.assign(relief, { x0: -100, n0: -100, x1: 100, n1: 100, nx: 51, nn: 51, base: 0, contours: () => {} });
				relief.altitudes = Float32Array.from({ length: 51 * 51 }, (_, k) => 3 * Math.sin((k % 51) / 3) + 2 * Math.cos(Math.floor(k / 51) / 2.3) + (k % 7) * .2);
				relief.lattice({ land: { islands: [{ plateau: [{ x: -20, n: -20 }, { x: 20, n: 10 }] }] } });
				geometry.relief = relief;

				const gap = line => {
					const array = line.geometry.attributes.position.array;
					let worst = 0;
					for(let i = 0; i < array.length; i += 3) {
						worst = Math.max(worst, Math.abs(array[i + 1] - (relief.height(array[i], -array[i + 2]) + lift)));
					}
					return worst;
				};

				limits = new MapSceneLimits({ THREE, geometry, ground, parent: new THREE.Group() });
				result.relief = { zone: count(limits.lines.zone), gap: Math.max(gap(limits.lines.zone), gap(limits.lines.plot)) };

				// Le sol descend partout de 1,5 m, comme sous un creux : le tracé refait le suit
				relief.heights = relief.heights.map(h => h - 1.5);
				limits.draw();
				result.carved = Math.max(gap(limits.lines.zone), gap(limits.lines.plot));

				// La ligne de la carte des calques
				const make = () => {
					const toggle = { classes: new Set(), classList: { toggle(name, on) { on ? toggle.classes.add(name) : toggle.classes.delete(name); } } };
					const row = { listeners: [], qs: () => toggle, addEventListener(type, listener) { this.listeners.push(listener); } };
					const card = { dataset: { structure: "4087", user: "12" } };
					const container = { qs: selector => (selector === "[data-scene-limits]") ? row : card };
					const scene = new MapSceneLimits({ THREE, geometry, ground, parent: new THREE.Group() });
					scene.attach(container);
					const state = () => [scene.group.visible, [...toggle.classes][0]];
					return { scene, row, container, state };
				};

				const key = MapSceneLimits.key(make().container);
				const first = make();
				const toggle = { key, start: first.state() };
				first.row.listeners.forEach(listener => listener());
				toggle.on = [...first.state(), storage.get(key) ?? null];
				first.row.listeners.forEach(listener => listener());
				toggle.off = [...first.state(), storage.get(key) ?? null];
				first.row.listeners.forEach(listener => listener());
				toggle.remembered = make().state();
				localStorage.blocked = true;
				toggle.blocked = make().state();
				result.toggle = toggle;

				emit(result);
			})();
		';

		$process = proc_open(['node', '-'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, NULL, ['PATH' => getenv('PATH')]);

		if($this->assertTrue(is_resource($process), "node ne se lance pas ici : ce test éprouve la scène sous node, l'installer sur la machine qui lance les tests") === FALSE) {
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

		$this->result = $result ?? [];

		return $this->result;

	}

}
?>
