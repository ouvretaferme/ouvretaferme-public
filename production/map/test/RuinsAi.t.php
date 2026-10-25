<?php
namespace map;

/**
 * Les ruines de la scène (`static/js/scene.js`, `MapSceneRuins` et `MapSceneHold`) et le masque qu'elles posent sur le décor
 * (`sceneDecor.js`), éprouvés sous node tels que le navigateur charge les deux fichiers : la découpe d'un élément en morceaux,
 * la copie qui prend la place de l'original et le rend, un effet joué jusqu'au bout puis défait, le minutage de l'appui long, et
 * le chemin des calques, qui ne doit pas rallumer un élément masqué.
 *
 * Aucune écriture, aucune base ouverte : un effet ne laisse rien en base, et la scène se monte sans page ni carte graphique.
 *
 * Lancement : dev/test package=map file=RuinsAi
 */
class RuinsAiTest extends \Test {

	public function testShatter(): void {

		$result = $this->run('
			const parent = new THREE.Group();
			const shown = [];
			const ruins = new MapSceneRuins({ THREE, parent, gradientMap: null, shadow: () => {}, still: false });

			const box = new THREE.Mesh(new THREE.BoxGeometry(6, 3, 2), new THREE.MeshToonMaterial({ color: "#aa8844" }));
			box.position.set(10, 1.5, -4);
			parent.add(box);

			const target = MapSceneRuins.measure(THREE, {
				key: "box", base: 0, floor: () => 0, least: .6, angle: 0,
				parts: () => [MapSceneRuins.part(box)],
				hide: () => shown.push(false),
				show: () => shown.push(true)
			});

			ruins.prepare(target);
			const shatter = ruins.prepared.get("box");
			const position = shatter.mesh.geometry.attributes.position;

			// Le plus long côté d’un triangle de la copie, pour la coupe des grands triangles
			let longest = 0;
			for(let i = 0; i < position.count; i += 3) {
				for(let e = 0; e < 3; e++) {
					const a = i + e, b = i + (e + 1) % 3;
					longest = Math.max(longest, Math.hypot(position.getX(a) - position.getX(b), position.getY(a) - position.getY(b), position.getZ(a) - position.getZ(b)));
				}
			}

			const prepared = { chunks: shatter.chunks.length, triangles: shatter.triangles, longest, shown: [...shown], center: [target.x, target.z, target.width, target.depth, target.height] };

			ruins.forget("box");

			const single = MapSceneRuins.measure(THREE, { key: "board", base: 0, floor: () => 0, angle: .4, pieces: 1, parts: () => [MapSceneRuins.part(box)], hide: () => {}, show: () => {} });
			ruins.prepare(single);

			emit({ prepared, forgotten: { shown, prepared: ruins.prepared.has("box"), children: parent.children.includes(shatter.mesh) }, single: ruins.prepared.get("board").chunks.length });
		');

		// La grille approche le nombre voulu par en dessous : une boite creuse n'occupe pas toutes ses cases
		$this->assertTrue($result['prepared']['chunks'] >= 12 and $result['prepared']['chunks'] <= 20, 'une boite de 6 m se découpe en 20 morceaux au plus, et pas en miettes : '.$result['prepared']['chunks']);
		$this->assertTrue($result['prepared']['triangles'] > 12, 'les grands triangles de la boite se coupent');
		$this->assertTrue($result['prepared']['longest'] <= 3 + 1e-6, 'aucun côté de la copie ne dépasse la coupe : '.$result['prepared']['longest']);
		$this->assertEquals([FALSE], $result['prepared']['shown'], "la copie masque l'original");
		$this->assertEquals([10.0, -4.0, 6.0, 2.0, 3.0], array_map(fn($value) => round($value, 3), $result['prepared']['center']), "l'emprise de la cible est celle de la boite");

		$this->assertEquals([FALSE, TRUE], $result['forgotten']['shown'], "un appui lâché rend l'original");
		$this->assertFalse($result['forgotten']['prepared']);
		$this->assertFalse($result['forgotten']['children'], 'la copie rendue quitte la scène');

		$this->assertEquals(1, $result['single'], "une pancarte tombe d'une pièce");

	}

	public function testGround(): void {

		$result = $this->run('
			const parent = new THREE.Group();
			const ruins = new MapSceneRuins({ THREE, parent, gradientMap: null, shadow: () => {}, still: true });

			// Une planche : sa terre, qui porte son repère, et un plant qui ne le porte pas
			const soil = new THREE.BoxGeometry(20, .1, 1);
			soil.setAttribute("bedSize", new THREE.BufferAttribute(new Float32Array(soil.attributes.position.count * 2).fill(5), 2));
			const plant = new THREE.Mesh(new THREE.ConeGeometry(.3, 1, 6), new THREE.MeshToonMaterial());
			plant.position.set(2, .6, 0);

			const target = MapSceneRuins.measure(THREE, {
				key: "bed:1", base: .05, floor: () => .05, least: 1.2, bed: true, angle: 0,
				look: { material: new THREE.MeshToonMaterial(), depth: null, name: "bed", attributes: { bedSize: 2 }, points: [] },
				parts: () => [{ ...MapSceneRuins.part(new THREE.Mesh(soil, new THREE.MeshToonMaterial())), ground: true }, MapSceneRuins.part(plant)],
				hide: () => {}, show: () => {}
			});

			ruins.prepare(target);
			const shatter = ruins.prepared.get("bed:1");
			const { chunk, bedSize } = shatter.mesh.geometry.attributes;

			// Chaque morceau est tout de terre ou tout de plant : la terre reste au sol pendant que les plants tombent
			const kinds = new Map();
			for(let i = 0; i < chunk.count; i++) {
				const kind = bedSize.getX(i) > 0;
				kinds.set(chunk.getX(i), [...new Set([...(kinds.get(chunk.getX(i)) ?? []), kind])]);
			}

			emit({ itemSize: bedSize.itemSize, mixed: [...kinds.values()].filter(list => list.length > 1).length, grounds: shatter.chunks.filter(c => c.ground).length, plants: shatter.chunks.filter(c => c.ground === false).length });
		');

		$this->assertEquals(2, $result['itemSize'], "un attribut de l'original garde sa taille dans la copie");
		$this->assertEquals(0, $result['mixed'], 'aucun morceau ne mêle terre et plant');
		$this->assertTrue($result['grounds'] >= 4, 'la terre fait ses propres morceaux');
		$this->assertTrue($result['plants'] >= 1);

	}

	public function testEffects(): void {

		$result = $this->run('
			const out = {};

			for(const kind of ["burn", "bomb", "quake"]) {

				const parent = new THREE.Group();
				const shown = [];
				const ruins = new MapSceneRuins({ THREE, parent, gradientMap: null, shadow: () => {}, still: false });
				const box = new THREE.Mesh(new THREE.BoxGeometry(3, 4, 3).translate(0, 2, 0), new THREE.MeshToonMaterial());
				const target = MapSceneRuins.measure(THREE, { key: "shed", base: 0, floor: () => 0, angle: 0, parts: () => [MapSceneRuins.part(box)], hide: () => shown.push(false), show: () => shown.push(true) });

				const started = ruins.begin(kind, target, 1000);
				const again = ruins.begin(kind, target, 1000);
				let shaken = false;

				for(let now = 1000; now <= 1000 + MapSceneRuins[kind.toUpperCase()].END * 1000 + 50; now += 50) {
					ruins.frame(now);
					shaken = shaken || ruins.shake.lengthSq() > 0;
				}

				const ruined = ruins.ruined.has("shed");
				// Les morceaux finissent au sol, pas sous lui
				const lowest = Math.min(...ruins.ruined.get("shed").chunks.map(c => c.position.y - c.half.y * c.scale.y));

				ruins.reset();

				out[kind] = { started, again, ruined, lowest, shaken, shown, busy: ruins.busy("shed"), meshes: parent.children.filter(child => child.isMesh && child.isInstancedMesh !== true).length };

			}

			emit(out);
		');

		foreach(['burn', 'bomb', 'quake'] as $kind) {
			$this->assertTrue($result[$kind]['started'], $kind.' se déclenche');
			$this->assertFalse($result[$kind]['again'], $kind.' ne se rejoue pas sur un élément déjà touché');
			$this->assertTrue($result[$kind]['ruined'], $kind.' laisse des ruines à la fin');
			$this->assertTrue($result[$kind]['lowest'] > -.5, $kind.' ne fait pas passer de morceau sous le sol : '.$result[$kind]['lowest']);
			$this->assertEquals([FALSE, TRUE], $result[$kind]['shown'], $kind.' masque puis rend à l\'entrée dans la pose du décor');
			$this->assertFalse($result[$kind]['busy']);
			$this->assertEquals(0, $result[$kind]['meshes'], 'les copies quittent la scène');
		}

		$this->assertFalse($result['burn']['shaken'], 'le feu ne secoue pas la vue');
		$this->assertTrue($result['bomb']['shaken'], 'la bombe secoue la vue');
		$this->assertTrue($result['quake']['shaken'], 'le séisme secoue la vue');

	}

	public function testStill(): void {

		$result = $this->run('
			const parent = new THREE.Group();
			const ruins = new MapSceneRuins({ THREE, parent, gradientMap: null, shadow: () => {}, still: true });
			const box = new THREE.Mesh(new THREE.BoxGeometry(2, 2, 2), new THREE.MeshToonMaterial());
			const target = MapSceneRuins.measure(THREE, { key: "box", base: 0, floor: () => 0, angle: 0, parts: () => [MapSceneRuins.part(box)], hide: () => {}, show: () => {} });

			ruins.prepare(target);
			ruins.mark(target, 1, 1);
			ruins.frame(1234);
			const trembled = ruins.prepared.get("box").mesh.position.length();

			ruins.begin("quake", target, 0);
			let shaken = 0;
			for(let now = 0; now < 2000; now += 50) {
				ruins.frame(now);
				shaken = Math.max(shaken, ruins.shake.length());
			}

			emit({ trembled, shaken });
		');

		$this->assertEquals(0, $result['trembled'], "sous prefers-reduced-motion, l'élément ne frémit pas");
		$this->assertEquals(0, $result['shaken'], 'sous prefers-reduced-motion, la vue ne tremble pas');

	}

	public function testHold(): void {

		$result = $this->run('
			emit([0, 1000, 1500, 1600, 2000, 2500, 3999, 4000, 6000].map(ms => MapSceneHold.progress(ms)));
		');

		$this->assertEquals([
			['glow' => 0.0, 'tremble' => 0.0, 'open' => FALSE],
			['glow' => 0.0, 'tremble' => 0.0, 'open' => FALSE],
			['glow' => 0.0, 'tremble' => 0.0, 'open' => FALSE],
			['glow' => .04, 'tremble' => 0.0, 'open' => FALSE],
			['glow' => .2, 'tremble' => 0.0, 'open' => FALSE],
			['glow' => .4, 'tremble' => .25, 'open' => FALSE],
			['glow' => .9996, 'tremble' => .9995, 'open' => FALSE],
			['glow' => 1.0, 'tremble' => 1.0, 'open' => TRUE],
			['glow' => 1.0, 'tremble' => 1.0, 'open' => TRUE],
		], array_map(fn($step) => ['glow' => round($step['glow'], 4), 'tremble' => round($step['tremble'], 4), 'open' => $step['open']], $result), "la cible s'éclaire à 1,5 s, l'élément frémit dès 2 s et les pastilles s'ouvrent à 4 s");

	}

	/**
	 * Un élément masqué par un effet reste masqué quand son calque se rallume, et revient quand l'effet le rend, si son calque se
	 * montre.
	 */
	public function testLayers(): void {

		$result = $this->run('
			const layers = new Set([1]);
			const e = { layer: 1, root: { visible: true } };
			const other = { layer: 1, root: { visible: true } };
			const decor = Object.assign(Object.create(MapSceneDecor.prototype), {
				layers: { active: null, isShown: layer => layers.has(layer) },
				elements: [e, other],
				ruined: new Set(),
				active: false,
				changed: () => {}
			});

			const steps = [];
			const look = () => steps.push([e.root.visible, other.root.visible]);

			decor.mask(e, true);
			look();
			layers.delete(1);
			decor.layersChanged();
			look();
			layers.add(1);
			decor.layersChanged();
			look();
			decor.mask(e, false);
			look();
			layers.delete(1);
			decor.layersChanged();
			decor.mask(e, true);
			decor.mask(e, false);
			look();

			emit(steps);
		');

		$this->assertEquals([
			[FALSE, TRUE],
			[FALSE, FALSE],
			[FALSE, TRUE],
			[TRUE, TRUE],
			[FALSE, FALSE],
		], $result);

	}

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

				// Les fichiers tels que la page les charge, l’un après l’autre dans la même portée ; la page n’y est qu’esquissée
				const source = ["base/scene/static/js/plan.js", "base/scene/static/js/crew.js", "production/series/static/js/seriesSheet.js", "production/map/static/js/scene.js", "production/map/static/js/sceneDecor.js"].map(file => readFileSync(directory + "/" + file, "utf8")).join("\n");
				const context = vm.createContext({ window: {}, document: { addEventListener: () => {} }, console });
				const { MapSceneRuins, MapSceneHold, MapSceneDecor } = vm.runInContext(source + "\n;({ MapSceneRuins, MapSceneHold, MapSceneDecor })", context);

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
