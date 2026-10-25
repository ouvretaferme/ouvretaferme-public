<?php
namespace map;

/**
 * Le trait du geste sur la scène (`static/js/sceneDecor.js`, `MapSceneDecor.stroke()` et `strokeAlong()`) : le tour d'un tracé et
 * celui d'une parcelle choisie passent, comme le geste de la Carte (`MapCartography.GESTURE`), sur un cerne d'encre posé juste
 * sous eux, qui suit la même ligne et déborde du trait dans le rapport du trait de la Carte à son cerne. La couleur ne s'éprouve
 * pas ici, le banc ne lit aucun jeton : elle se juge dans Chrome.
 *
 * Éprouvé sous node, sur un décor qu'aucun écran n'attache : `attach()`, qui crée les matériaux en lisant la page, n'y passe
 * pas, et le test donne au décor le seul matériau d'encre dont le trait a besoin. Aucune écriture, aucune base ouverte.
 *
 * Lancement : dev/test package=map file=DecorStrokeAi
 */
class DecorStrokeAiTest extends \Test {

	public function testTheStrokeRestsOnItsCasing(): void {

		$result = $this->run('
			const material = new THREE.MeshBasicMaterial();
			decor.pads = { casing: new THREE.MeshBasicMaterial() };

			const mesh = decor.stroke(material);
			decor.strokeAlong(mesh, [{ x: 0, n: 0 }, { x: 10, n: 0 }], .8);

			const [casing] = mesh.children;
			const box = geometry => {
				geometry.computeBoundingBox();
				return geometry.boundingBox;
			};

			const line = box(mesh.geometry);
			const under = box(casing.geometry);

			emit({
				children: mesh.children.length,
				material: mesh.material === material,
				ink: casing.material === decor.pads.casing,
				order: [casing.renderOrder, mesh.renderOrder],
				widths: [Math.round((line.max.z - line.min.z) * 1000) / 1000, Math.round((under.max.z - under.min.z) * 1000) / 1000],
				lengths: [Math.round((line.max.x - line.min.x) * 1000) / 1000, Math.round((under.max.x - under.min.x) * 1000) / 1000],
				lower: under.max.y < line.min.y
			});
		');

		$this->assertEquals(1, $result['children'] ?? NULL, 'le trait porte son cerne, et lui seul');
		$this->assertTrue($result['material'] ?? NULL, 'le trait garde le matériau du geste qu\'on lui donne');
		$this->assertTrue($result['ink'] ?? NULL, 'le cerne prend l\'encre du décor');
		$this->assertEquals([4, 5], $result['order'] ?? NULL, 'le cerne se dessine juste avant son trait');
		$this->assertEquals([0.8, 1.333], $result['widths'] ?? NULL, 'le cerne déborde du trait dans le rapport du trait de la Carte à son cerne, 3 à 5');
		$this->assertEquals([10.8, 11.333], $result['lengths'] ?? NULL, 'le cerne suit la ligne du trait et finit rond au-delà de ses bouts');
		$this->assertTrue($result['lower'] ?? NULL, 'le cerne passe sous le trait');

	}

	public function testAStrokeRedrawnFreesItsFormerLine(): void {

		$result = $this->run('
			decor.pads = { casing: new THREE.MeshBasicMaterial() };

			const mesh = decor.stroke(new THREE.MeshBasicMaterial());
			decor.strokeAlong(mesh, [{ x: 0, n: 0 }, { x: 10, n: 0 }], 1.2);

			const freed = [];
			mesh.geometry.addEventListener("dispose", () => freed.push("line"));
			mesh.children[0].geometry.addEventListener("dispose", () => freed.push("casing"));

			decor.strokeAlong(mesh, [{ x: 0, n: 0 }, { x: 0, n: 4 }, { x: 4, n: 4 }], 1.2);

			emit({ freed: freed.sort(), children: mesh.children.length, drawn: mesh.geometry.attributes.position !== undefined && mesh.children[0].geometry.attributes.position !== undefined });
		');

		$this->assertEquals(['casing', 'line'], $result['freed'] ?? NULL, 'le trait redessiné libère son ancienne ligne et celle de son cerne');
		$this->assertEquals(1, $result['children'] ?? NULL, 'le trait redessiné garde un seul cerne');
		$this->assertTrue($result['drawn'] ?? NULL, 'le trait et son cerne prennent la nouvelle ligne');

	}

	/**
	 * Joue un scénario sous node avec les fichiers de la scène chargés comme le navigateur les charge, et three, sur un décor
	 * vide qu'aucun écran n'attache (`DecorWithoutScreenAi`), et rend ce qu'il émet.
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

				const source = ["base/scene/static/js/plan.js", "base/scene/static/js/crew.js", "production/series/static/js/seriesSheet.js", "production/map/static/js/scene.js", "production/map/static/js/sceneLayer.js", "production/map/static/js/sceneDecor.js"].map(file => readFileSync(directory + "/" + file, "utf8")).join("\n");
				const context = vm.createContext({ window: {}, document: { addEventListener: () => {} }, console, clearTimeout });
				const { MapSceneDecor } = vm.runInContext(source + "\n;({ MapSceneDecor })", context);

				const decor = new MapSceneDecor({
					socle: { THREE, addons: {}, figure: null, baked: null },
					plan: { center: [4.85, 45.75], terrain: { grass: "meadow", soil: "brown" }, zones: [], decor: [], layers: [] },
					geometry: { beds: [], plots: new Map(), zones: new Map() },
					parent: new THREE.Group(),
					gradientMap: null,
					diameterLabel: null,
					changed: () => {},
					walked: () => {}
				});

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
?>
