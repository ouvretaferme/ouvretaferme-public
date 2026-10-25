<?php
namespace map;

/**
 * Le poids de chaque kit de décor dans la scène, en triangles (`DecorLib::KIT_TRIANGLES`), sous lequel une structure pose ses objets
 * jusqu'à `MapSetting::DECOR_TRIANGLES`. Inventaire des kits de `MapSceneDecor.KITS` (`static/js/sceneDecor.js`) : chacun a son
 * poids, recalculé ici par construction depuis le champ `triangles` de `base/scene/static/model/models.json`, ce que la scène bâtit
 * elle-même (`makeShape()`, les rides d'une étendue d'eau) et le plafond d'un bâtiment importé (`base/scene/upload/decor.json`).
 * Un modèle retouché dans `base/scene` fait rougir ce test dans la passe des sentinelles.
 *
 * Le code fait foi : un écart se corrige dans `KIT_TRIANGLES`, et dans `TREES` d'abord quand le tirage d'un kit d'arbres change.
 *
 * Éprouvé sous node, sur les sources servies et le modèle cuit. Aucune écriture, aucune base ouverte.
 *
 * Lancement : dev/test package=map file=DecorWeightSentinel
 */
class DecorWeightSentinelTest extends \Test {

	/**
	 * Ce qu'un kit d'arbres tire au plus, d'après sa fonction de tirage dans `sceneDecor.js` : son nombre de plants et les modèles
	 * qu'il peut planter. Son poids est le nombre multiplié par le plus lourd des modèles. `SEEDS` graines par kit vérifient
	 * qu'aucun tirage n'en sort.
	 */
	const TREES = [
		// `tree()` parmi les essences `rond`, `sapin` et `peuplier`
		'arbre' => [1, ['arbre', 'arbre.sapin', 'arbre.peuplier']],
		'fruitier' => [1, ['fruitier', 'fruitier.rouge', 'fruitier.or']],
		// `wood()` : trois massifs (`WOOD`) d'une même nature, huit arbres au plus (`GROVES`)
		'bosquet' => [24, ['arbre', 'arbre.sapin', 'arbre.peuplier']],
		// `grove()` : huit arbres au plus (`GROVES`)
		'massif' => [8, ['arbre', 'arbre.sapin', 'arbre.peuplier']],
		// `orchard()` : deux rangs de trois fruitiers
		'verger' => [6, ['fruitier', 'fruitier.rouge', 'fruitier.or']],
		// `thicket()` : de cinq à neuf buissons
		'buissons' => [9, ['buisson']],
		// `alignment()` : quatre arbres d'une essence, `peuplier` ou `rond`
		'alignement' => [4, ['arbre', 'arbre.peuplier']],
	];

	const SEEDS = 20000;

	public function testEachKitWeighsItsHeaviestDraw(): void {

		$measures = $this->measure();

		if($this->assertTrue($measures !== [], 'la mesure des kits sous node ne rend rien') === FALSE) {
			return;
		}

		$index = json_decode(file_get_contents(LIME_DIRECTORY.'/base/scene/static/model/models.json'), TRUE)['models'];
		$rules = json_decode(file_get_contents(LIME_DIRECTORY.'/base/scene/upload/decor.json'), TRUE);

		$expected = [];
		$unknown = [];

		foreach($measures as $kit => $measure) {

			$expected[$kit] = match($measure['kind']) {
				'traced' => 0,
				'building' => $rules['families']['building']['maxTriangles'],
				'object' => $rules['families'][$measure['family']]['maxTriangles'],
				'shape' => $measure['triangles'],
				'model' => $index[$measure['model']]['triangles'] + $measure['ripples'],
				'trees' => $this->weighTrees($kit, $measure, $index),
				'unknown' => NULL
			};

			if($expected[$kit] === NULL) {
				$unknown[] = $kit;
				unset($expected[$kit]);
			}

		}

		$this->assertEquals([], $unknown, 'kits de MapSceneDecor.KITS dont ce test ne sait pas compter les triangles, à lui apprendre : '.implode(', ', $unknown));

		$missing = array_diff_key($expected, DecorLib::KIT_TRIANGLES);
		$this->assertEquals([], array_keys($missing), 'kits de MapSceneDecor.KITS sans poids dans DecorLib::KIT_TRIANGLES, à y ajouter : '.implode(', ', array_map(fn($kit) => $kit.' => '.$missing[$kit], array_keys($missing))));

		$stale = array_diff_key(DecorLib::KIT_TRIANGLES, $expected);
		$this->assertEquals([], array_keys($stale), 'kits de DecorLib::KIT_TRIANGLES que MapSceneDecor.KITS ne connait plus, à retirer : '.implode(', ', array_keys($stale)));

		$wrong = [];

		foreach(array_intersect_key($expected, DecorLib::KIT_TRIANGLES) as $kit => $triangles) {
			if(DecorLib::KIT_TRIANGLES[$kit] !== $triangles) {
				$wrong[] = $kit.' pèse '.$triangles.' et non '.DecorLib::KIT_TRIANGLES[$kit];
			}
		}

		$this->assertEquals([], $wrong, 'poids de DecorLib::KIT_TRIANGLES à corriger, ainsi que sa borne dans le commentaire : '.implode(', ', $wrong));

	}

	/** Le poids d'un kit d'arbres d'après `TREES`, après avoir vérifié que ses tirages y tiennent. */
	private function weighTrees(string $kit, array $measure, array $index): ?int {

		if(array_key_exists($kit, self::TREES) === FALSE) {
			return NULL;
		}

		[$count, $slugs] = self::TREES[$kit];

		$this->assertTrue($measure['count'] <= $count, 'le kit '.$kit.' tire jusqu\'à '.$measure['count'].' plants sur '.self::SEEDS.' graines, plus que les '.$count.' de TREES : corriger TREES, puis KIT_TRIANGLES');

		$outside = array_values(array_diff($measure['slugs'], $slugs));
		$this->assertEquals([], $outside, 'le kit '.$kit.' plante des modèles que TREES ne lui connait pas, à y ajouter avant KIT_TRIANGLES : '.implode(', ', $outside));

		return $count * max(array_map(fn(string $slug) => $index[$slug]['triangles'], array_merge($slugs, $outside)));

	}

	/**
	 * Chaque kit de `MapSceneDecor.KITS`, rangé d'après sa définition : `traced` (tracé, enclos ou tuyau, comptés au mètre),
	 * `building` (bâtiment de la structure), `object` (élément importé, au plafond de sa famille), `shape` (forme bâtie, ses triangles mesurés), `model` (modèle cuit, et les rides d'une
	 * étendue d'eau qui en porte), `trees` (le plus de plants et les modèles tirés sur `SEEDS` graines).
	 */
	private function measure(): array {

		$script = '
			const directory = '.json_encode(LIME_DIRECTORY).';
			const seeds = '.self::SEEDS.';

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
				const { MapSceneDecor, MapSceneCrops } = vm.runInContext(source + "\n;({ MapSceneDecor, MapSceneCrops })", context);
				const D = MapSceneDecor;

				const decor = new D({
					socle: { THREE, addons, figure, baked },
					plan: { center: [4.85, 45.75], terrain: { grass: "meadow", soil: "brown" }, zones: [], decor: [], layers: [] },
					geometry: { beds: [], plots: new Map(), zones: new Map(), project: ([x, n]) => ({ x, n }), height: () => 0, relief: null },
					parent: new THREE.Group(),
					gradientMap: null,
					diameterLabel: null,
					changed: () => {},
					walked: () => {}
				});

				const triangles = geometry => (geometry.index ? geometry.index.count : geometry.attributes.position.count) / 3;
				const ripples = D.RIPPLES * triangles(decor.rippleGeometry);
				const measures = {};

				for(const [kit, def] of Object.entries(D.KITS)) {

					if(def.traced === true || def.enclosure === true || def.pipe === true) {
						measures[kit] = { kind: "traced" };
					} else if(def.building === true) {
						measures[kit] = { kind: "building" };
					} else if(def.object === true) {
						measures[kit] = { kind: "object", family: def.family };
					} else if(def.shape !== undefined) {
						measures[kit] = { kind: "shape", triangles: triangles(decor.makeShape({ kit, def, seated: null }).geometry) };
					} else if(def.wild !== undefined) {
						measures[kit] = { kind: "model", model: def.wild, ripples: 0 };
					} else if(def.model !== undefined) {
						measures[kit] = { kind: "model", model: def.model, ripples: (def.water === true) ? ripples : 0 };
					} else if(def.plants !== undefined) {

						let count = 0;
						const slugs = new Set();

						for(let seed = 1; seed <= seeds; seed++) {
							const plants = def.plants(MapSceneCrops.random(seed));
							count = Math.max(count, plants.length);
							plants.forEach(plant => slugs.add(plant.slug));
						}

						measures[kit] = { kind: "trees", count, slugs: [...slugs].sort() };

					} else {
						measures[kit] = { kind: "unknown" };
					}

				}

				process.stdout.write(JSON.stringify(measures));
			})();
		';

		$process = proc_open(['node', '-'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, NULL, ['PATH' => getenv('PATH')]);

		if($this->assertTrue(is_resource($process), "node ne se lance pas ici : ce test mesure les kits sous node, l'installer sur la machine qui lance les tests") === FALSE) {
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

		$this->assertTrue(is_array($result), 'node rend sa mesure : '.$output.$errors);

		return $result ?? [];

	}

}
