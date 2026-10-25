<?php
namespace map;

/**
 * Le décor du plan se dessine sans écran (`static/js/sceneDecor.js`, constructeur de `MapSceneDecor`) : ni conteneur, ni canevas,
 * ni caméra, ni contrôles, que seul `attach()` lui apporte. Une scène qui montre le champ d'une structure ailleurs que dans le plan
 * (`MapSceneField`, `static/js/scene.js`) le monte ainsi, puis l'anime, change sa saison et le défait. Les calques
 * (`static/js/sceneLayer.js`) s'y lisent tous affichés, et un calque disparu ramène l'élément au calque de base.
 *
 * Éprouvé sous node, dans un contexte qui n'a pas de `document.qs` : une lecture de la page par le dessin y lève une erreur.
 * Le décor y est vide : construire un élément demande les modèles cuits, et `MapSceneField` le socle des cultures, que seul le
 * navigateur charge ici. Aucune écriture, aucune base ouverte.
 *
 * Lancement : dev/test package=map file=DecorWithoutScreenAi
 */
class DecorWithoutScreenAiTest extends \Test {

	public function testTheDecorIsDrawnWithoutAScreen(): void {

		$result = $this->run('
			const plan = { center: [4.85, 45.75], terrain: { grass: "meadow", soil: "brown" }, zones: [], decor: [], layers: [{ id: 12, name: "Irrigation", type: null }] };
			const geometry = { beds: [], plots: new Map(), zones: new Map(), height: () => 0, relief: null };
			let changed = 0;

			const decor = new MapSceneDecor({
				socle: { THREE, addons: {}, figure: null, baked: null },
				plan, geometry,
				parent: new THREE.Group(),
				gradientMap: null,
				diameterLabel: null,
				changed: () => {
					changed++;
				},
				walked: () => {}
			});

			const drawn = { attached: decor.attached, base: decor.layers.isShown(MapSceneLayer.BASE), layer: decor.layers.of(12), lost: decor.layers.of(99), boards: decor.boards.length, changed };

			decor.animate(1000);
			decor.season("2026-06-15", [1, 1, 1]);
			decor.glide(.5);
			decor.dispose();

			emit({ ...drawn, played: true, changedAfter: changed });
		');

		$this->assertEquals(FALSE, $result['attached'] ?? NULL, "le décor dessiné n'est attaché à aucun écran");
		$this->assertEquals(TRUE, $result['base'] ?? NULL, "sans carte, le calque de base est affiché");
		$this->assertEquals(12, $result['layer'] ?? NULL, "un élément garde son calque quand le plan le porte");
		$this->assertEquals(0, $result['lost'] ?? NULL, "un élément dont le calque a disparu revient au calque de base");
		$this->assertEquals(0, $result['boards'] ?? NULL, "le dessin ne connait aucune pancarte avant le décor par défaut");
		$this->assertTrue(($result['changed'] ?? 0) >= 1, "le dessin dit une fois que l'ombre est à refaire");
		$this->assertEquals(TRUE, $result['played'] ?? NULL, "animer, changer la saison et défaire le décor ne lisent rien de la page");
		$this->assertTrue(($result['changedAfter'] ?? 0) > ($result['changed'] ?? 0), "la saison redemande l'ombre");

	}

	/**
	 * Le bourg de la compta monte ainsi le champ et les arbres d'une structure, avec ses lignes de décor et sans feuille de pose : le
	 * plafond du décor, qui ne se juge que pendant la pose, ne s'y lit pas. Une route, sans modèle ni largeur, est un tracé que le
	 * plafond pèse et que le dessin monte sans les modèles cuits.
	 */
	public function testTheDecorIsDrawnWithItsRowsWithoutTheSheet(): void {

		$result = $this->run('
			const row = { id: 1, kit: "route", seed: 7, angle: 0, size: 1, building: null, layer: null, diameter: null, laying: null, width: null, model: null, hedge: false, gate: null, zone: null, plot: null, coordinates: [0, 0], trace: [[0, 0], [120, 0]] };
			const plan = { center: [4.85, 45.75], terrain: { grass: "meadow", soil: "brown" }, zones: [], decor: [row], layers: [] };
			const geometry = { beds: [], plots: new Map(), zones: new Map(), project: ([x, n]) => ({ x, n }), height: () => 0, relief: null };

			const decor = new MapSceneDecor({
				socle: { THREE, addons: { mergeGeometries }, figure: null, baked: null },
				plan, geometry,
				parent: new THREE.Group(),
				gradientMap: null,
				diameterLabel: null,
				changed: () => {},
				walked: () => {}
			});

			emit({ elements: decor.elements.map(e => e.kit) });
		');

		$this->assertEquals(['route'], $result['elements'] ?? NULL, "le décor se monte avec sa route, sans le plafond que porte la feuille de pose");

	}

	/**
	 * Joue un scénario sous node avec les fichiers de la scène chargés comme le navigateur les charge, et three, et rend ce qu'il
	 * émet.
	 */
	private function run(string $scenario): array {

		$script = '
			const directory = '.json_encode(LIME_DIRECTORY).';
			const emit = value => process.stdout.write(JSON.stringify(value));

			// Les crochets de la cuisson font lire three en module ES, qu\'aucun package.json ne déclare tel
			require("node:module").register("file://" + directory + "/base/scene/bake/resolve.mjs");

			(async () => {
				const { readFileSync } = await import("node:fs");
				const vm = await import("node:vm");
				const THREE = await import("file://" + directory + "/base/scene/static/lib/three-0.170.0.module.min.js");
				// Un tracé fond ses couches en une géométrie. Lu plus tard, après le contexte de la scène, ce module fait tomber node 18 lu sur son
				// entrée standard (erreur de segmentation, le 2026-10-22)
				const { mergeGeometries } = await import("file://" + directory + "/base/scene/static/lib/three-0.170.0.BufferGeometryUtils.js");

				const source = ["base/scene/static/js/plan.js", "base/scene/static/js/crew.js", "production/series/static/js/seriesSheet.js", "production/map/static/js/scene.js", "production/map/static/js/sceneLayer.js", "production/map/static/js/sceneDecor.js"].map(file => readFileSync(directory + "/" + file, "utf8")).join("\n");
				const context = vm.createContext({ window: {}, document: { addEventListener: () => {} }, console, clearTimeout });
				const { MapSceneDecor, MapSceneLayer } = vm.runInContext(source + "\n;({ MapSceneDecor, MapSceneLayer })", context);

				'.$scenario.'
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
