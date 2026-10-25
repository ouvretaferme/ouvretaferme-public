<?php
namespace map;

/**
 * Ce qui bouge ou se pose sur le sol suit le relief (F-relief R3), éprouvé sous node tel que le navigateur charge les fichiers :
 * le rayon qui touche le sol (`MapSceneRelief::onRelief()`), la cible de la vue recalée le long du regard (`MapScene::seat()`),
 * l'oiseau posé et en vol (`MapSceneFauna::drawBird()`), le hérisson (`MapSceneHedgehog::trot()`), le paysan, son ombre et la
 * poussière de ses pas (`SceneCrew::update()`, `puff()`), la place d'un papillon
 * (`MapSceneInsects::spot()`), la bulle de récolte (`MapSceneBubbles::show()`), la pastille d'un tuyau
 * (`MapSceneDecor::pipeLift()`), le cratère, la lueur et les fissures d'une ruine (`MapSceneRuins::mound()`, `spread()`,
 * `reveal()`). Le sol monte de 10 % vers l'est, et aussi de 5 % vers le nord sous le paysan ; sans
 * relief, chaque position est celle d'avant le relief.
 *
 * Aucune écriture, aucune base ouverte : le sol est écrit dans le scénario.
 *
 * Lancement : dev/test package=map file=ReliefMobileAi
 */
class ReliefMobileAiTest extends \Test {

	public function testTheRayMeetsTheGround(): void {

		$result = $this->run('
			const ray = { origin: new THREE.Vector3(0, 100, 0), direction: new THREE.Vector3(1, -1, .5).normalize() };
			const point = slope.onRelief(ray, .05);
			const t = (point.x - ray.origin.x) / ray.direction.x;
			// La cible de la vue, sous le sol, regarde vers le bas : le point tombe derrière elle
			const under = slope.onRelief({ origin: new THREE.Vector3(30, 0, 0), direction: new THREE.Vector3(-1, -1, 0).normalize() }, 0);
			// Une vue rasante, à 3° sous l\'horizon, face à la pente : reprendre la hauteur sous le point trouvé s\'en éloignerait
			const grazing = slope.onRelief({ origin: new THREE.Vector3(0, 5, 0), direction: new THREE.Vector3(1, -Math.tan(Math.PI / 60), 0).normalize() }, 0);
			emit({
				gap: Math.abs(point.y - (.05 + rise(point.x))),
				off: Math.abs(ray.origin.z + t * ray.direction.z + point.n),
				flat: flat.onRelief(ray, .05),
				up: slope.onRelief({ origin: new THREE.Vector3(0, 0, 0), direction: new THREE.Vector3(0, 1, 0) }, 0),
				under: [under.x > 30, Math.abs(under.y - rise(under.x))],
				grazing: [grazing.x, Math.abs(grazing.y - rise(grazing.x))],
				expected: 5 / (.1 + Math.tan(Math.PI / 60))
			});
		');

		$this->assertTrue($result['gap'] < 1e-3, 'le point touché est sur le sol levé de la base : écart '.$result['gap']);
		$this->assertTrue($result['off'] < 1e-9, 'le point touché est sur le rayon');
		$this->assertEquals(['x' => 99.95, 'y' => .05, 'n' => -49.975], array_map(fn($value) => round($value, 6), $result['flat']), 'un sol plat rend le point du plan levé de la base');
		$this->assertEquals(NULL, $result['up'], 'un rayon qui monte ne touche rien');
		$this->assertTrue($result['under'][0] === TRUE and $result['under'][1] < 1e-3, 'une origine sous le sol trouve le sol derrière elle');
		$this->assertTrue(abs($result['grazing'][0] - $result['expected']) < 1e-3 and $result['grazing'][1] < 1e-3, 'une vue rasante face à la pente touche le sol : '.json_encode($result['grazing']));

	}

	public function testTheTargetSeatsAlongTheView(): void {

		$result = $this->run('
			const scene = relief => ({ geometry: { relief }, controls: { target: new THREE.Vector3(40, 0, -10) }, camera: { position: new THREE.Vector3(140, 100, 90) } });
			const seated = scene(slope);
			const look = seated.controls.target.clone().sub(seated.camera.position).normalize();
			MapScene.prototype.seat.call(seated);
			const after = seated.controls.target.clone().sub(seated.camera.position).normalize();
			const again = seated.controls.target.clone();
			MapScene.prototype.seat.call(seated);
			const level = scene(null);
			MapScene.prototype.seat.call(level);
			emit({
				height: seated.controls.target.y,
				ground: rise(seated.controls.target.x),
				turned: after.angleTo(look),
				distance: seated.camera.position.distanceTo(seated.controls.target),
				before: Math.hypot(100, 100, 100),
				still: seated.controls.target.distanceTo(again),
				level: level.controls.target.toArray().concat(level.camera.position.toArray())
			});
		');

		$this->assertTrue(abs($result['height'] - $result['ground']) < 1e-3, 'la cible se pose sur le sol : '.$result['height'].' pour '.$result['ground']);
		$this->assertTrue($result['height'] > 1, 'la cible quitte le plan de sa hauteur de départ');
		$this->assertTrue($result['turned'] < 1e-9, 'la caméra glisse avec la cible le long du regard : l\'image ne bouge pas');
		$this->assertTrue(abs($result['distance'] - $result['before']) < 1e-6, 'la caméra garde sa distance à la cible');
		$this->assertTrue($result['still'] < 1e-6, 'une cible posée ne bouge plus');
		$this->assertEquals([40, 0, -10, 140, 100, 90], $result['level'], 'sans relief, la vue ne bouge pas');

	}

	public function testTheAnimalsWalkOnTheGround(): void {

		$result = $this->run('
			const bones = names => Object.fromEntries(names.map(name => [name, { rotation: new THREE.Euler() }]));
			const bird = (place, flight) => ({
				perched: { root: new THREE.Object3D(), bones: bones(["tete", "queue"]) },
				flying: { root: new THREE.Object3D(), bones: bones(["aile-g", "aile-d", "queue"]) },
				flight, place, scale: 1, seed: 0, faint: false, heading: 0
			});
			const fauna = geometry => Object.assign(Object.create(MapSceneFauna.prototype), { geometry });

			// Posé sur la place que perch() a lue, à 20 m à l\'est
			const perched = bird({ p: { x: 20, n: 0 }, height: rise(20) }, null);
			MapSceneFauna.prototype.drawBird.call(fauna(slopeGeometry), perched, 0);

			// À mi-vol d\'une place à 2 m vers une place à 4 m, sans arc : la hauteur est celle du milieu
			const from = { p: { x: 20, n: 0 }, height: 2 };
			const to = { p: { x: 40, n: 0 }, height: 4, tree: null };
			const flying = bird(from, { from: from.p, base: 2, to, span: { x: 20, n: 0 }, aside: { x: 0, n: 0 }, bank: 0, peak: 0, duration: 2, elapsed: 1 });
			MapSceneFauna.prototype.drawBird.call(fauna(slopeGeometry), flying, 0);

			const hedgehog = geometry => {
				const h = Object.assign(Object.create(MapSceneHedgehog.prototype), {
					fauna: { geometry }, figure: { root: new THREE.Object3D(), bones: bones(["corps", "tete"]) },
					scale: 1, faint: false, path: [{ x: 10, n: 0 }, { x: 30, n: 0 }], s: 5, goal: 5, rest: 10,
					at: { x: 0, n: 0, dx: 1, dn: 0 }, heading: 0
				});
				h.trot(.016, 0, 20, null, null);
				return h.figure.root.position.toArray();
			};

			emit({
				perched: perched.perched.root.position.y,
				flying: flying.flying.root.position.y,
				hedgehog: hedgehog(slopeGeometry),
				level: hedgehog(flatGeometry)
			});
		');

		$this->assertTrue($result['perched'] >= 2 and $result['perched'] <= 2.05, 'l\'oiseau posé est sur le sol de sa place : '.$result['perched']);
		$this->assertTrue(abs($result['flying'] - 3.05) < 1e-9, 'l\'oiseau en vol passe entre la hauteur de son départ et celle de son arrivée : '.$result['flying']);
		$this->assertEquals([15.0, 1.5, 0.0], array_map(fn($value) => round($value, 6), $result['hedgehog']), 'le hérisson trotte sur le sol');
		$this->assertEquals([15.0, 0.0, 0.0], array_map(fn($value) => round($value, 6), $result['level']), 'sans relief, le hérisson est à la hauteur d\'avant');

	}

	public function testTheFarmerWalksOnTheGround(): void {

		$result = $this->run('
			// Un sol qui monte vers l\'est et vers le nord, des paysans à l\'échelle des grands tunnels, accroupis de 0,2 m à l\'échelle 1, à 20 m à l\'est
			// et 10 m au nord, l\'un entier, l\'autre à mi-fondu : son corps, son ombre, puis une bouffée de ses pas à 30 m à l\'est
			const tilted = { height: (x, n) => .1 * x + .05 * n };
			const crew = (geometry, shown) => {
				const farmer = { on: true, shown, root: new THREE.Object3D(), shadow: new THREE.Object3D(), at: { x: 20, n: 10 }, path: [], pose: "stand", work: "stand", heading: 0, facing: 0, wait: 100, crate: null, drop: -.2 };
				farmer.root.add(farmer.shadow);
				const puffs = [{ start: 0, at: new THREE.Vector3() }];
				const scene = Object.assign(Object.create(SceneCrew.prototype), { geometry, farmers: [farmer], last: null, playing: true, scale: SceneCrew.SCALE_MAX, speed: 1, shadowGeometry: new THREE.CircleGeometry(.5, 16), puffs, puffNext: 0, pose() {}, drawDust() {} });
				scene.update(1000);
				scene.puff({ x: 30, n: 10 }, 1);
				farmer.root.updateMatrixWorld(true);
				return [farmer.root.position.y, new THREE.Vector3().setFromMatrixPosition(farmer.shadow.matrixWorld).y, puffs[0].at.y];
			};
			emit({ scale: SceneCrew.SCALE_MAX, slope: crew(tilted, 1), fading: crew(tilted, .5), level: crew(flatGeometry, 1) });
		');

		// Le sol est à 2,5 m sous le paysan et à 3,5 m sous la bouffée. L'ombre monte de 0,03 et de ce que la pente lève le bord
		// d'un disque de 0,5 m à l'échelle du corps : |(0,1 ; 0,05)| · 0,5 · scale · shown
		$scale = $result['scale'];
		$rise = fn(float $shown) => hypot(.1, .05) * .5 * $scale * $shown;

		$this->assertEquals(array_map(fn($value) => round($value, 6), [2.5 - .2 * $scale, 2.53 + $rise(1), 3.6]), array_map(fn($value) => round($value, 6), $result['slope']), 'le paysan, son ombre levée du bord amont et la poussière de ses pas suivent le sol');
		// Le corps à mi-fondu n'est pas éprouvé : son drop ne suit que l'échelle de l'équipe et non le fondu, un défaut antérieur au relief
		// qu'une assertion figerait
		$this->assertEquals(round(2.53 + $rise(.5), 6), round($result['fading'][1], 6), 'à mi-fondu, l\'ombre se lève de ce que la pente monte au bord du disque réduit');
		$this->assertEquals(array_map(fn($value) => round($value, 6), [-.2 * $scale, .03, .1]), array_map(fn($value) => round($value, 6), $result['level']), 'sans relief, le paysan, son ombre et la poussière gardent leur hauteur');

	}

	public function testTheLabelsStandOnTheGround(): void {

		$result = $this->run('
			// Un papillon visite le haut d\'une culture, au-dessus du sol sous elle : le tirage choisit la planche, et le papillon est
			// seul, tout le cadre lui étant ouvert
			const blooms = [{ greenhouse: false, height: .5, top: .6, a: { x: 20, n: 0 }, b: { x: 20, n: 0 } }];
			const insects = geometry => Object.assign(Object.create(MapSceneInsects.prototype), { geometry, meadow: { flowersInView: () => [], footprints: [] }, butterflies: [], frustum: { containsPoint: () => true }, framed: () => true, projection: new THREE.Matrix4(), half: [1, 1], perMetre: 1, scale: 1, probe: new THREE.Vector3(), random: () => 0 });
			const spot = MapSceneInsects.prototype.spot.call(insects(slopeGeometry), blooms, null);

			// La bulle d\'une série récoltée, au-dessus du sol sous la série
			const placed = [];
			const addons = { CSS2DObject: class { constructor(element) { this.element = element; this.position = new THREE.Vector3(); placed.push(this); } } };
			globalThis.document = { createElement: () => ({ append() {}, style: {} }) };
			globalThis.Lime = { Asset: { icon: () => "" } };
			SeriesSheet.quantities = () => "";
			new MapSceneBubbles(addons, { add() {} }, null, () => [], slopeGeometry).show(1, [{ at: { x: 30, n: 0 }, harvests: [{ quantity: 1, unit: "kg" }] }], {});

			// La pastille d\'un tuyau dont la racine, tournée d\'un quart de tour, est à 10 m à l\'est : un point à 5 m de son tracé
			// tombe à 15 m à l\'est
			const pipe = geometry => {
				const root = new THREE.Object3D();
				root.position.set(10, geometry.height(10, 0), 0);
				root.rotation.y = Math.PI / 2;
				root.updateMatrixWorld(true);
				return MapSceneDecor.prototype.pipeLift.call({ geometry }, { root, label: { userData: { height: .2 } } }, { x: 0, n: -5 }, new THREE.Vector3());
			};

			// Le bourrelet d\'un cratère, posé sur le sol sous chaque perle
			const matrices = [];
			const ruins = Object.assign(Object.create(MapSceneRuins.prototype), {
				THREE, moundCount: 0, matrix: new THREE.Matrix4(), quaternion: new THREE.Quaternion(), up: new THREE.Vector3(0, 1, 0), color: new THREE.Color(), random: () => 0,
				mounds: { setMatrixAt: (i, matrix) => matrices.push(new THREE.Vector3().setFromMatrixPosition(matrix).y), setColorAt() {}, instanceMatrix: {}, instanceColor: {} }
			});
			ruins.mound([{ x: 10, z: 0, radius: 1 }, { x: 30, z: 0, radius: 1 }], (x, z) => rise(x), .4, "#000000");

			emit({ spot: spot.y, bubble: placed[0].position.y, pipe: pipe(slopeGeometry), level: pipe(flatGeometry), mounds: matrices });
		');

		$this->assertTrue(abs($result['spot'] - 2.6) < 1e-9, 'le papillon vise le haut de la culture au-dessus de son sol : '.$result['spot']);
		$this->assertTrue(abs($result['bubble'] - 4.5) < 1e-9, 'la bulle de récolte se lève au-dessus du sol de sa série : '.$result['bubble']);
		$this->assertTrue(abs($result['pipe'] - .7) < 1e-9, 'la pastille monte de ce que le sol sous son point dépasse le sol sous la racine : '.$result['pipe']);
		$this->assertTrue(abs($result['level'] - .2) < 1e-9, 'sans relief, la pastille garde la hauteur de son gabarit');
		$this->assertEquals([1.0, 3.0], array_map(fn($value) => round($value, 6), $result['mounds']), 'le cratère se pose sur le sol sous chaque perle');

	}

	public function testTheRuinsLieOnTheSlope(): void {

		$result = $this->run('
			// Une planche de 100 m le long de la pente : 10 m de dénivelé d\'un bout à l\'autre
			const target = { x: 50, z: 0, angle: 0, width: 100, depth: 1.2, bed: true, floor: (x, z) => rise(x) };
			const matrices = new Map();
			const mesh = { setMatrixAt: (i, matrix) => matrices.set(i, matrix.clone()), setColorAt() {}, instanceMatrix: {}, count: 0 };
			const ruins = Object.assign(Object.create(MapSceneRuins.prototype), {
				THREE, crackCount: 0, matrix: new THREE.Matrix4(), quaternion: new THREE.Quaternion(), tilt: new THREE.Quaternion(), up: new THREE.Vector3(0, 1, 0), across: new THREE.Vector3(0, 0, 1), random: () => .5, cracks: mesh
			});
			// Ce qu\'un point de la forme dépasse le sol sous lui
			const gaps = (matrix, points) => points.map(([x, z]) => {
				const p = new THREE.Vector3(x, 0, z).applyMatrix4(matrix);
				return p.y - rise(p.x);
			});

			ruins.spread(mesh, 0, target, 51, 1.6, new THREE.Color());
			const glow = gaps(matrices.get(0), [[1, 0], [-1, 0], [0, 1], [0, -1]]);

			matrices.clear();
			const cracks = ruins.crack(target);
			ruins.reveal({ done: {}, cracks }, 10);
			const first = cracks.filter(segment => segment.at === 0);
			const ends = first.flatMap(segment => gaps(matrices.get(segment.index), [[.5, 0], [-.5, 0]]));

			emit({ glow, ends, segments: first.length });
		');

		$this->assertEquals([.03, .03, .03, .03], array_map(fn($value) => round($value, 6), $result['glow']), 'la lueur couvre la planche à quelques centimètres du sol, d\'un bout à l\'autre de la pente');
		$this->assertEquals(2, $result['segments'], 'une planche se fissure de deux lignes');
		$this->assertEquals([.003, .003, .003, .003], array_map(fn($value) => round($value, 6), $result['ends']), 'un segment de fissure touche le sol à ses deux bouts');

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

				const document = { addEventListener: () => {}, createElement: () => ({ getContext: () => new Proxy({}, { get: () => () => ({}) }) }) };
				const files = ["base/scene/static/js/plan.js", "base/scene/static/js/crew.js", "production/series/static/js/seriesSheet.js", "production/map/static/js/scene.js", "production/map/static/js/sceneDecor.js", "production/map/static/js/sceneFauna.js", "production/map/static/js/sceneHedgehog.js"];
				const source = files.map(file => readFileSync(directory + "/" + file, "utf8")).join("\n");
				const context = vm.createContext({ window: {}, document, console });
				const { MapScene, MapSceneRelief, MapSceneFauna, MapSceneHedgehog, MapSceneInsects, MapSceneBubbles, MapSceneDecor, MapSceneRuins, SceneCrew, SeriesSheet } = vm.runInContext(source + "\n;({ MapScene, MapSceneRelief, MapSceneFauna, MapSceneHedgehog, MapSceneInsects, MapSceneBubbles, MapSceneDecor, MapSceneRuins, SceneCrew, SeriesSheet })", context);
				const globalThis = context;

				// Le sol monte de 10 % vers l\'est : 1 m tous les 10 m
				const rise = x => .1 * x;
				const slope = Object.assign(Object.create(MapSceneRelief.prototype), { height: (x, n) => rise(x), heights: Float32Array.of(-20, 40) });
				const flat = Object.assign(Object.create(MapSceneRelief.prototype), { height: () => 0, heights: Float32Array.of(0) });
				const slopeGeometry = { relief: slope, height: (x, n) => slope.height(x, n) };
				const flatGeometry = { relief: null, height: () => 0 };

				'.$scenario.'
			})().catch(error => {
				emit({ error: String(error.stack) });
			});
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
		$this->assertFalse(isset($result['error']), 'le scénario passe sans erreur : '.($result['error'] ?? ''));

		return $result ?? [];

	}

}
?>
