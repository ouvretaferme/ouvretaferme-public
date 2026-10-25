<?php
namespace map;

/**
 * La taille du matériel posé sur le plan (`static/js/sceneDecor.js`, kits de la famille `other`) : l'outil de taille s'ouvre au
 * panneau, au compost, aux caisses et aux quatre véhicules, entre la moitié et le double. La taille multiplie le rendu de la
 * taille 1, celui d'un véhicule déjà agrandi de `VEHICLE_SCALE` compris, et l'emprise comme le contrôle de collision la suivent.
 * Une taille de 1 rend sommet pour sommet la géométrie d'avant le lot : aucun matériel déjà posé ne bouge.
 *
 * Éprouvé sous node, avec le vrai modèle cuit (`base/scene/static/model/models.json` et `models.bin`) monté par
 * `sceneCreateFigure()`, comme le navigateur le monte. Aucune écriture, aucune base ouverte.
 *
 * Lancement : dev/test package=map file=DecorEquipmentSizeAi
 */
class DecorEquipmentSizeAiTest extends \Test {

	const KITS = ['panneau', 'compost', 'caisses', 'tracteur', 'utilitaire', 'pick-up', 'remorque'];

	/**
	 * Les sept kits du matériel, et eux seuls dans leur famille, ouvrent l'outil de taille entre 0,5 et 2.
	 */
	public function testEachEquipmentKitOpensTheSizeTool(): void {

		$result = $this->run('
			const kits = Object.entries(MapSceneDecor.KITS).filter(([, def]) => def.family === "other");
			emit({ kits: kits.map(([kit]) => kit).sort(), sizes: Object.fromEntries(kits.map(([kit, def]) => [kit, def.size ?? null])) });
		');

		$expected = self::KITS;
		sort($expected);

		$this->assertEquals($expected, $result['kits'] ?? NULL, "la famille Matériel compte les sept kits du lot");

		foreach(self::KITS as $kit) {
			$this->assertEquals([0.5, 2], $result['sizes'][$kit] ?? NULL, $kit." ouvre l'outil de taille entre 0,5 et 2");
		}

	}

	/**
	 * À la taille 1, chaque matériel rend, sommet pour sommet, son modèle cuit recentré puis agrandi de son seul facteur `grown`
	 * (`VEHICLE_SCALE` pour un véhicule, 1 sinon), sans échelle sur le maillage : c'est le rendu d'avant le lot.
	 */
	public function testSizeOneKeepsTheRenderingOfBefore(): void {

		$result = $this->run('
			const out = {};
			for(const kit of KITS) {
				const decor = draw([row(1, kit, 0, 1)]);
				const e = decor.elements[0];
				const mesh = e.root.children[0];
				const drawn = [...mesh.geometry.attributes.position.array];
				const expected = reference(kit);
				const gap = drawn.length === expected.length ? Math.max(...drawn.map((value, i) => Math.abs(value - expected[i]))) : null;
				const [min, max] = baked.index.models[kit].box;
				const grown = MapSceneDecor.KITS[kit].grown ?? 1;
				out[kit] = {
					scale: [mesh.scale.x, mesh.scale.y, mesh.scale.z],
					count: drawn.length, expected: expected.length, gap,
					width: e.width - (max[0] - min[0]) * grown,
					depth: e.depth - (max[2] - min[2]) * grown,
					height: e.height - max[1] * grown
				};
			}
			emit({ out, vehicle: MapSceneDecor.VEHICLE_SCALE });
		');

		$this->assertEquals(2, $result['vehicle'] ?? NULL, "un véhicule se montre deux fois plus grand que nature, comme avant le lot");

		foreach(self::KITS as $kit) {

			$measure = $result['out'][$kit] ?? [];

			$this->assertEquals([1, 1, 1], $measure['scale'] ?? NULL, $kit." à la taille 1 n'a aucune échelle sur son maillage");
			$this->assertEquals($measure['expected'] ?? -1, $measure['count'] ?? NULL, $kit." à la taille 1 garde les sommets de son modèle");
			$this->assertEquals(0.0, round($measure['gap'] ?? 99, 6), $kit." à la taille 1 rend chaque sommet du modèle agrandi de son seul facteur");

			foreach(['width', 'depth', 'height'] as $dimension) {
				$this->assertEquals(0.0, round($measure[$dimension] ?? 99, 6), $kit." à la taille 1 garde sa mesure (".$dimension.")");
			}

		}

	}

	/**
	 * À la taille 2, un véhicule double une seconde fois ce que `VEHICLE_SCALE` a déjà doublé, et son emprise au sol avec lui ;
	 * à 0,5, il revient à sa taille réelle.
	 */
	public function testTheSizeMultipliesTheVehicleScale(): void {

		$result = $this->run('
			const measure = size => {
				const e = draw([row(1, "tracteur", 0, size)]).elements[0];
				const rectangle = MapSceneDecor.rectangle(e);
				const box = new THREE.Box3().setFromObject(e.root);
				return { width: e.width, depth: e.depth, height: e.height, footprint: [rectangle.width, rectangle.length], drawn: [box.max.x - box.min.x, box.max.y, box.max.z - box.min.z] };
			};
			const [min, max] = baked.index.models.tracteur.box;
			emit({ model: [max[0] - min[0], max[1], max[2] - min[2]], one: measure(1), two: measure(2), half: measure(.5) });
		');

		[$width, $height, $depth] = $result['model'] ?? [0, 0, 0];

		$this->assertEquals(round($width * 4, 4), round($result['two']['width'] ?? 0, 4), "à la taille 2, le tracteur fait quatre fois sa longueur réelle");
		$this->assertEquals(round($depth * 4, 4), round($result['two']['depth'] ?? 0, 4), "à la taille 2, le tracteur fait quatre fois sa largeur réelle");
		$this->assertEquals(round($height * 4, 4), round($result['two']['height'] ?? 0, 4), "à la taille 2, le tracteur fait quatre fois sa hauteur réelle");
		$this->assertEquals([round($width * 4, 4), round($depth * 4, 4)], array_map(fn($value) => round($value, 4), $result['two']['footprint'] ?? []), "à la taille 2, l'emprise au sol suit");
		$this->assertEquals([round($width * 4, 3), round($height * 4, 3), round($depth * 4, 3)], array_map(fn($value) => round($value, 3), $result['two']['drawn'] ?? []), "à la taille 2, le tracteur dessiné suit sa mesure");
		$this->assertEquals([round($width, 4), round($depth, 4)], array_map(fn($value) => round($value, 4), $result['half']['footprint'] ?? []), "à la taille 0,5, le tracteur revient à sa taille réelle, emprise comprise");
		$this->assertEquals([round($width * 2, 3), round($height * 2, 3), round($depth * 2, 3)], array_map(fn($value) => round($value, 3), $result['one']['drawn'] ?? []), "à la taille 1, le tracteur dessiné garde le double de sa taille réelle");

	}

	/**
	 * Deux tracteurs côte à côte, libres à la taille 1, se chevauchent quand l'un passe à 2 : le contrôle de collision lit la
	 * taille comme le dessin.
	 */
	public function testTheCollisionFollowsTheSize(): void {

		$result = $this->run('
			const [min, max] = baked.index.models.tracteur.box;
			const apart = (max[0] - min[0]) * MapSceneDecor.VEHICLE_SCALE + 1;
			const clashes = size => {
				const decor = draw([row(1, "tracteur", 0, size), { ...row(2, "tracteur", apart, 1) }]);
				const [e, other] = decor.elements;
				return [decor.clash(e, other, MapSceneDecor.footprint(e), null), decor.clash(other, e, MapSceneDecor.footprint(other), null)];
			};
			emit({ one: clashes(1), two: clashes(2) });
		');

		$this->assertEquals([FALSE, FALSE], $result['one'] ?? NULL, "deux tracteurs à un mètre l'un de l'autre ne se gênent pas à la taille 1");
		$this->assertEquals([TRUE, TRUE], $result['two'] ?? NULL, "le tracteur passé à la taille 2 chevauche son voisin, dans les deux sens");

	}

	/**
	 * Joue un scénario sous node avec la scène et le vrai modèle cuit, et rend ce qu'il émet. `row(id, kit, x, size)` est une ligne
	 * du décor posée à `x` mètres à l'est du centre, `draw(rows)` monte la scène sur ces lignes, et `reference(kit)` rend les
	 * positions du modèle cuit recentré et agrandi de son facteur `grown`, comme le rendu d'avant le lot les montait.
	 */
	private function run(string $scenario): array {

		$script = '
			const directory = '.json_encode(LIME_DIRECTORY).';
			const emit = value => process.stdout.write(JSON.stringify(value));
			const KITS = '.json_encode(self::KITS).';

			// Les crochets de la cuisson font lire three en module ES, qu\'aucun package.json ne déclare tel
			require("node:module").register("file://" + directory + "/base/scene/bake/resolve.mjs");

			(async () => {
				const { readFileSync } = await import("node:fs");
				const vm = await import("node:vm");
				const THREE = await import("file://" + directory + "/base/scene/static/lib/three-0.170.0.module.min.js");
				const addons = await import("file://" + directory + "/base/scene/static/lib/three-0.170.0.BufferGeometryUtils.js");
				const { sceneBuildModel } = await import("file://" + directory + "/base/scene/static/js/model.js");
				const { sceneCreateGeometry } = await import("file://" + directory + "/base/scene/static/js/geometry.js");
				const { sceneCreateFigure } = await import("file://" + directory + "/base/scene/static/js/figure.js");

				const load = file => readFileSync(directory + "/" + file);
				const bin = load("base/scene/static/model/models.bin");
				const baked = { index: JSON.parse(load("base/scene/static/model/models.json").toString("utf8")), buffer: bin.buffer.slice(bin.byteOffset, bin.byteOffset + bin.byteLength) };
				const figure = sceneCreateFigure(THREE, addons, sceneCreateGeometry(THREE, addons), sceneBuildModel);

				const source = ["base/scene/static/js/plan.js", "base/scene/static/js/crew.js", "production/series/static/js/seriesSheet.js", "production/map/static/js/scene.js", "production/map/static/js/sceneLayer.js", "production/map/static/js/sceneDecor.js"].map(file => readFileSync(directory + "/" + file, "utf8")).join("\n");
				const context = vm.createContext({ window: {}, document: { addEventListener: () => {} }, console, clearTimeout });
				const { MapSceneDecor } = vm.runInContext(source + "\n;({ MapSceneDecor })", context);

				const row = (id, kit, x, size) => ({ id, kit, seed: id, angle: 0, size, building: null, layer: null, diameter: null, laying: null, width: null, model: null, hedge: false, gate: null, zone: null, plot: null, coordinates: [x, 0], trace: null });
				const draw = rows => new MapSceneDecor({
					socle: { THREE, addons, figure, baked },
					plan: { center: [0, 0], terrain: { grass: "meadow", soil: "brown" }, zones: [], decor: rows, layers: [] },
					geometry: { beds: [], plots: new Map(), zones: new Map(), project: ([x, n]) => ({ x, n }), height: () => 0, relief: null },
					parent: new THREE.Group(),
					gradientMap: null,
					diameterLabel: null,
					changed: () => {},
					walked: () => {}
				});
				const reference = kit => {
					const [min, max] = baked.index.models[kit].box;
					const grown = MapSceneDecor.KITS[kit].grown ?? 1;
					const geometry = figure.prop(baked, kit, new THREE.MeshBasicMaterial()).geometry;
					geometry.translate(-(min[0] + max[0]) / 2, 0, -(min[2] + max[2]) / 2).scale(grown, grown, grown);
					return [...geometry.attributes.position.array];
				};

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
