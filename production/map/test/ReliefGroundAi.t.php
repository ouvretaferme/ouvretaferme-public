<?php
namespace map;

/**
 * La hauteur du relief dans la scène (`static/js/scene.js`, `MapSceneRelief::height()`), éprouvée sous node telle que le
 * navigateur charge le fichier : ce qui se pose sur le sol se pose sur le sol rendu, triangle par triangle, et non sur la grille
 * qu'il simplifie hors des plateaux. Le sol rendu se lit ici par un rayon de three sur le maillage de `ground()`, sans passer par
 * `height()`.
 *
 * Aucune écriture, aucune base ouverte : la grille d'altitudes est écrite dans le scénario.
 *
 * Lancement : dev/test package=map file=ReliefGroundAi
 */
class ReliefGroundAiTest extends \Test {

	public function testTheHeightIsTheRenderedGround(): void {

		$result = $this->run();

		$this->assertTrue(($result['cells']['fine'] ?? 0) > 0, 'des cases au pas de la grille, sous le plateau');
		$this->assertTrue(($result['cells']['mixed'] ?? 0) > 0, 'des cases mixtes, fines sur un axe et longues sur l\'autre');
		$this->assertTrue(($result['cells']['coarse'] ?? 0) > 0, 'des cases longues sur les deux axes');
		$this->assertTrue(($result['grid'] ?? 0) > .1, 'la grille s\'écarte du sol rendu dans les cases longues : le scénario éprouve bien l\'écart');

		foreach(['vertex' => 'aux sommets', 'middle' => 'au milieu des cases', 'inside' => 'ailleurs dans les cases'] as $where => $label) {
			$this->assertTrue(($result['gap'][$where] ?? 1) < 1e-6, 'la hauteur est celle du sol rendu '.$label);
		}

	}

	private function run(): array {

		$script = '
			const directory = '.json_encode(LIME_DIRECTORY).';
			const emit = value => process.stdout.write(JSON.stringify(value));

			// Les crochets de la cuisson font lire three en module ES, qu\'aucun package.json ne déclare tel
			require("node:module").register("file://" + directory + "/base/scene/bake/resolve.mjs");

			(async () => {
				const { readFileSync } = await import("node:fs");
				const vm = await import("node:vm");
				const THREE = await import("file://" + directory + "/base/scene/static/lib/three-0.170.0.module.min.js");

				const document = { addEventListener: () => {}, createElement: () => ({ getContext: () => new Proxy({}, { get: () => () => ({}) }) }) };
				const source = ["base/scene/static/js/plan.js", "base/scene/static/js/crew.js", "production/series/static/js/seriesSheet.js", "production/map/static/js/scene.js"].map(file => readFileSync(directory + "/" + file, "utf8")).join("\n");
				const { MapSceneRelief } = vm.runInContext(source + "\n;({ MapSceneRelief })", vm.createContext({ window: {}, document, console }));

				// Une grille de 200 m au pas de 4 m, en creux et en bosses de quelques mètres, et un plateau de 40 m sur 30 m en son
				// milieu : le sol rendu y est fin, mixte sur ses bandes, long ailleurs. Les courbes de niveau ne sont pas éprouvées ici
				const relief = Object.create(MapSceneRelief.prototype);
				Object.assign(relief, { x0: -100, n0: -100, x1: 100, n1: 100, nx: 51, nn: 51, base: 0, contours: () => {} });
				relief.altitudes = Float32Array.from({ length: 51 * 51 }, (_, k) => 3 * Math.sin((k % 51) / 3) + 2 * Math.cos(Math.floor(k / 51) / 2.3) + (k % 7) * .2);
				relief.lattice({ land: { islands: [{ plateau: [{ x: -20, n: -20 }, { x: 20, n: 10 }] }] } });

				const mesh = new THREE.Mesh(relief.ground(THREE, {}, {}), new THREE.MeshBasicMaterial({ side: THREE.DoubleSide }));
				const ray = new THREE.Raycaster();
				const rendered = (x, n) => {
					ray.set(new THREE.Vector3(x, 100, -n), new THREE.Vector3(0, -1, 0));
					return ray.intersectObject(mesh)[0].point.y;
				};

				const { xs, ns } = relief;
				const cells = { fine: 0, mixed: 0, coarse: 0 };
				const gap = { vertex: 0, middle: 0, inside: 0 };
				let grid = 0;

				for(let i = 0; i < xs.length - 1; i++) {
					for(let j = 0; j < ns.length - 1; j++) {

						if(xs[i] < -100 || xs[i + 1] > 100 || ns[j] < -100 || ns[j + 1] > 100) {
							continue;
						}

						const width = xs[i + 1] - xs[i];
						const depth = ns[j + 1] - ns[j];
						const fine = [width, depth].filter(size => size <= MapSceneRelief.STEP + .001).length;
						cells[["coarse", "mixed", "fine"][fine]]++;

						// Un sommet un peu dedans, le rayon tombant sinon sur une arête partagée
						const points = { vertex: [[.0001, .0001]], middle: [[.5, .5]], inside: [[.25, .6], [.7, .2], [.9, .9]] };

						Object.entries(points).forEach(([where, list]) => list.forEach(([u, v]) => {
							const x = xs[i] + u * width;
							const n = ns[j] + v * depth;
							gap[where] = Math.max(gap[where], Math.abs(rendered(x, n) - relief.height(x, n)));
							grid = Math.max(grid, Math.abs(rendered(x, n) - (relief.altitude(x, n) - relief.base)));
						}));

					}
				}

				emit({ cells, gap, grid });
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

		return $result ?? [];

	}

}
?>
