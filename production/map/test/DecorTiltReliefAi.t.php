<?php
namespace map;

/**
 * Sur le relief, un objet à large base posée au sol s'incline sur la pente (`MapSceneDecor::tilt()`, `static/js/sceneDecor.js`) :
 * un véhicule, une remorque, des caisses, le compost, un rocher, et un objet importé de la rangée Matériel qui n'est pas plus haut
 * que `TILT_TALLEST` fois le plus petit côté de son emprise. Ce qui pousse ou se plante reste debout, un bâtiment et une étendue
 * d'eau restent de niveau. L'orientation de l'objet ne change pas, et son emprise suit le sol sous lui.
 *
 * Éprouvé sous node, avec le vrai modèle cuit, sur une grille de 4 m qui monte de 14,5 % vers l'est, bombée, bosselée ou en crête
 * selon le cas. Aucune écriture, aucune base ouverte.
 *
 * Lancement : dev/test package=map file=DecorTiltReliefAi
 */
class DecorTiltReliefAiTest extends \Test {

	/**
	 * Un tracteur sur 14,5 %, sous quatre orientations : le dessous de ses roues touche le sol à 2 cm près, les coins de son
	 * emprise sont sur le sol, son avant regarde toujours devant lui, sa base est directe et une seconde pose ne cumule rien. Le
	 * même tracteur debout sur son centre, comme avant, a une roue dans le sol et l'autre en l'air.
	 */
	public function testAVehicleLiesOnTheSlope(): void {

		$result = $this->run('
			const angles = [0, Math.PI / 2, 2.1, -1.2];
			const decor = draw(angles.map((angle, k) => row(k + 1, "tracteur", { coordinates: [40 * k - 60, 20], angle })), .145);
			const relief = decor.geometry.relief;
			const measure = e => {
				e.root.updateMatrixWorld(true);
				const gaps = vertices(e).map(p => p.y - relief.height(p.x, -p.z));
				const corners = [-1, 1].flatMap(a => [-1, 1].map(b => new THREE.Vector3(a * e.width / 2, 0, b * e.depth / 2).applyMatrix4(e.root.matrixWorld)));
				const forward = new THREE.Vector3(0, 0, 1).transformDirection(e.root.matrixWorld);
				const front = { x: Math.sin(e.angle), z: Math.cos(e.angle) };
				return {
					low: Math.min(...gaps),
					corners: corners.map(p => p.y - relief.height(p.x, -p.z)),
					forward: (forward.x * front.x + forward.z * front.z) / Math.hypot(forward.x, forward.z),
					determinant: e.root.matrixWorld.determinant(),
					yaw: Math.atan2(-e.root.matrixWorld.elements[2], e.root.matrixWorld.elements[0]) - e.angle,
					matrix: Array.from(e.root.matrixWorld.elements)
				};
			};
			const once = decor.elements.map(measure);
			decor.elements.forEach(e => decor.settle(e));
			const twice = decor.elements.map(measure);
			// Le tracteur droit sur son centre, ce que la pose faisait avant
			const [first] = decor.elements;
			first.root.rotation.set(0, first.angle, 0);
			first.root.position.y = relief.height(first.p.x, first.p.n);
			const upright = measure(first);
			emit({ once, drift: once.map((m, k) => Math.max(...m.matrix.map((value, i) => Math.abs(value - twice[k].matrix[i])))), upright: upright.low, wheels: [Math.min(...vertices(first).map(p => p.y - relief.height(p.x, -p.z))), Math.max(...upright.corners)] });
		');

		$this->assertEquals(4, count($result['once'] ?? []), 'les quatre tracteurs sont posés');

		foreach($result['once'] ?? [] as $k => $measure) {
			$label = 'le tracteur '.($k + 1);
			$this->assertTrue(abs($measure['low']) < .02, $label.' touche le sol du dessous de ses roues, à 2 cm près : '.$measure['low']);
			$this->assertTrue(max(array_map('abs', $measure['corners'])) < .001, $label.' a les quatre coins de son emprise sur le sol : '.json_encode($measure['corners']));
			$this->assertTrue($measure['forward'] > .99, $label.' regarde devant lui : '.$measure['forward']);
			$this->assertTrue(abs($measure['determinant'] - 1) < 1e-6, $label.' garde une base directe, sans miroir ni écrasement : '.$measure['determinant']);
			$this->assertTrue(abs(atan2(sin($measure['yaw']), cos($measure['yaw']))) < 1e-9, $label.' garde son orientation, que les ruines lisent (`MapScene::yaw()`) : '.$measure['yaw']);
			$this->assertTrue(($result['drift'][$k] ?? 1) < 1e-9, $label.' posé deux fois ne cumule pas sa pente : '.($result['drift'][$k] ?? 'rien'));
		}

		$this->assertTrue(($result['upright'] ?? 0) < -.2, 'debout sur son centre, le tracteur enfonce une roue dans le sol, ce que l\'inclinaison corrige : '.($result['upright'] ?? 'rien'));

	}

	/**
	 * Sous une crête qui passe sous son centre, comme sur un sol bosselé, ni le sol sous le centre ni un coin de l'emprise ne passent
	 * au-dessus du bas de l'objet : il monte de l'écart plutôt que d'être traversé.
	 */
	public function testNothingCrossesTheBase(): void {

		$result = $this->run('
			const measure = (ground, rows) => {
				const decor = draw(rows, ground);
				const relief = decor.geometry.relief;
				return decor.elements.map(e => {
					e.root.updateMatrixWorld(true);
					const points = [[0, 0], ...[-1, 1].flatMap(a => [-1, 1].map(b => [a, b]))].map(([a, b]) => new THREE.Vector3(a * e.width / 2, 0, b * e.depth / 2).applyMatrix4(e.root.matrixWorld));
					const gaps = points.map(p => p.y - relief.height(p.x, -p.z));
					return { low: Math.min(...gaps), high: Math.max(...gaps) };
				});
			};
			emit({
				ridge: measure((x, n) => 3 - .3 * Math.abs(x - 8), [row(1, "remorque", { coordinates: [8, 0], angle: 0 }), row(2, "caisses", { coordinates: [8, 6], angle: .4, size: 2 })]),
				bumps: measure((x, n) => .6 * Math.sin(x / 3) * Math.cos(n / 5) + .05 * x, [-1.2, -.3, .5, 1.4, 2.6].map((angle, k) => row(k + 1, ["tracteur", "utilitaire", "pick-up", "remorque", "compost"][k], { coordinates: [11 * k - 20, 7 * k - 9], angle })))
			});
		');

		// Un objet incliné se tient un peu plus court au sol que son emprise, et ses coins tombent à quelques millimètres de là où le
		// sol a été lu : sur un sol bosselé, l'écart reste sous le millimètre
		foreach(['ridge' => 'en crête', 'bumps' => 'bosselé'] as $case => $label) {
			$this->assertTrue(count($result[$case] ?? []) > 1, 'les objets du sol '.$label.' sont posés');
			foreach($result[$case] ?? [] as $k => $measure) {
				$this->assertTrue($measure['low'] > -.001, 'sur un sol '.$label.', aucun point du sol sous l\'emprise de l\'objet '.($k + 1).' ne passe au-dessus de sa base : '.$measure['low']);
			}
		}

		foreach($result['ridge'] ?? [] as $k => $measure) {
			$this->assertTrue($measure['high'] > .2, 'sur la crête, l\'objet '.($k + 1).' monte au-dessus de ses coins, de ce que le sol sous son centre dépasse leur plan : '.$measure['high']);
		}

	}

	/**
	 * Ce qui pousse ou se plante reste debout, un bâtiment et une étendue d'eau restent de niveau, une pompe tient son raccord à
	 * sa hauteur ; un véhicule, des caisses, le compost, un rocher et un pierrier s'inclinent. Un objet importé de la rangée
	 * Matériel s'incline tant qu'il n'est pas plus haut que `TILT_TALLEST` fois son plus petit côté.
	 */
	public function testTheKitsThatTilt(): void {

		$result = $this->run('
			const kits = ["tracteur", "utilitaire", "pick-up", "remorque", "caisses", "compost", "rocher", "pierrier", "arbre", "fruitier", "chene", "souche", "arbre-mort", "roncier", "panneau", "portail", "hangar", "poulailler-mobile", "cuve", "puits"];
			const decor = draw(kits.map((kit, k) => row(k + 1, kit, { coordinates: [12 * (k % 5) - 24, 12 * Math.floor(k / 5) - 18], angle: .3 * k })), (x, n) => .145 * x + .08 * n);
			const tilted = {};
			decor.elements.forEach(e => {
				const straight = new THREE.Quaternion().setFromEuler(new THREE.Euler(0, e.angle, 0));
				tilted[e.kit] = Math.abs(Math.abs(e.root.quaternion.dot(straight)) - 1) > 1e-9;
			});
			const object = (width, depth, height) => MapSceneDecor.tilts({ def: MapSceneDecor.KITS["objet-materiel"], width, depth, height });
			emit({
				tilted,
				objects: { bench: object(1.8, .55, .85), table: object(2, .9, .75), scarecrow: object(.8, .4, 1.8), ladder: object(.5, .2, 3), nature: MapSceneDecor.tilts({ def: MapSceneDecor.KITS["objet-nature"], width: 2, depth: 2, height: .5 }) },
				pump: MapSceneDecor.KITS.pompe.tilts === true
			});
		');

		foreach(['tracteur', 'utilitaire', 'pick-up', 'remorque', 'caisses', 'compost', 'rocher', 'pierrier'] as $kit) {
			$this->assertTrue($result['tilted'][$kit] ?? FALSE, $kit.' s\'incline sur la pente');
		}

		foreach(['arbre', 'fruitier', 'chene', 'souche', 'arbre-mort', 'roncier', 'panneau', 'portail', 'hangar', 'poulailler-mobile', 'cuve', 'puits'] as $kit) {
			$this->assertFalse($result['tilted'][$kit] ?? TRUE, $kit.' reste debout ou de niveau sur la pente');
		}

		$this->assertFalse($result['pump'] ?? TRUE, 'la pompe reste debout, son raccord au tuyau à sa hauteur');
		$this->assertEquals(['bench' => TRUE, 'table' => TRUE, 'scarecrow' => FALSE, 'ladder' => FALSE, 'nature' => FALSE], $result['objects'] ?? NULL, 'un banc et une table importés s\'inclinent, un épouvantail, une échelle et un objet de la rangée Nature restent debout');

	}

	/**
	 * Le rocher, kit de plants à un seul pied, s'incline d'un bloc sans que son plant monte une seconde fois, et le carré de son pied
	 * comme le carreau d'un tracteur restent au sol de leur levée, à chaque sommet et au milieu de chaque triangle.
	 */
	public function testTheFootprintFollowsTheGround(): void {

		$result = $this->run('
			const decor = padded(draw([row(1, "rocher", { coordinates: [10, 4], angle: .9, size: 2 }), row(2, "tracteur", { coordinates: [-20, 12], angle: 2.4, size: 1.5 }), row(3, "pierrier", { coordinates: [30, -10], angle: -.6 })], (x, n) => .145 * x + .002 * n * n));
			const relief = decor.geometry.relief;
			decor.elements.forEach(e => decor.settle(e));
			emit(decor.elements.map(e => {
				e.root.updateMatrixWorld(true);
				const plant = e.root.children[0].geometry.attributes.position;
				let lifted = 0;
				for(let i = 0; e.plain.kit !== null && i < plant.count; i++) {
					lifted = Math.max(lifted, Math.abs(plant.getY(i) - e.plain.kit[i * 3 + 1]));
				}
				const lift = e.plain.pad[1];
				const pieces = [e.pad, ...e.pad.children].map(mesh => {
					const position = mesh.geometry.attributes.position;
					const index = mesh.geometry.index;
					const world = i => new THREE.Vector3().fromBufferAttribute(position, i).applyMatrix4(mesh.matrixWorld);
					const gaps = [];
					const triangles = (index === null) ? position.count / 3 : index.count / 3;
					for(let t = 0; t < triangles; t++) {
						const points = [0, 1, 2].map(k => world((index === null) ? t * 3 + k : index.getX(t * 3 + k)));
						const middle = points[0].clone().add(points[1]).add(points[2]).divideScalar(3);
						[...points, middle].forEach(p => gaps.push(p.y - relief.height(p.x, -p.z) - lift));
					}
					return [Math.min(...gaps), Math.max(...gaps)];
				});
				return { kit: e.kit, tilted: Math.abs(e.root.rotation.x) + Math.abs(e.root.rotation.z) > 1e-6, lifted, pieces };
			}));
		');

		$this->assertEquals(3, count($result), 'le rocher, le tracteur et le pierrier sont posés');

		foreach($result as $measure) {
			$this->assertTrue($measure['tilted'], $measure['kit'].' s\'incline');
			$this->assertTrue($measure['lifted'] < 1e-6, $measure['kit'].' ne monte pas son plant une seconde fois : '.$measure['lifted']);
			// Le carré du pied d'un rocher n'est pas coupé au pas du sol : au milieu d'un triangle, il peut le survoler, dans la
			// marge que l'emprise d'un bâtiment admet (`DecorPoseReliefAi`)
			foreach($measure['pieces'] as [$low, $high]) {
				$this->assertTrue($low > -.01 and $high < .08, 'l\'emprise de '.$measure['kit'].' reste au-dessus du sol de sa levée, au milieu de chaque triangle compris : '.json_encode([$low, $high]));
			}
		}

	}

	/**
	 * Un tracteur incliné se redresse quand le relief s'éteint, comme la scène se remonte sans lui, et sans relief chaque élément
	 * reste à plat, sa seule rotation autour de la verticale.
	 */
	public function testWithoutReliefNothingTilts(): void {

		$result = $this->run('
			const rows = [row(1, "tracteur", { coordinates: [20, 0], angle: .8 }), row(2, "rocher", { coordinates: [-10, 5], angle: 1.3 }), row(3, "caisses", { coordinates: [0, -12], angle: -2 })];
			const decor = draw(rows, .145);
			const leaning = decor.elements.map(e => Math.abs(e.root.rotation.x) + Math.abs(e.root.rotation.z));
			decor.geometry.relief = null;
			decor.geometry.height = () => 0;
			decor.elements.forEach(e => {
				e.plain = null;
				decor.settle(e);
			});
			const none = draw(rows, null);
			emit({
				leaning,
				righted: decor.elements.map(e => [e.root.rotation.x, e.root.rotation.z, e.root.rotation.y - e.angle, e.root.position.y]),
				flat: none.elements.map(e => [e.root.rotation.x, e.root.rotation.z, e.root.rotation.y - e.angle, e.root.position.y])
			});
		');

		$this->assertTrue(min($result['leaning'] ?? [0]) > .01, 'sur le relief, les trois objets sont inclinés');

		foreach(['righted' => 'le relief éteint, chaque objet se redresse', 'flat' => 'sans relief, chaque objet reste à plat'] as $case => $label) {
			foreach($result[$case] ?? [] as $values) {
				$this->assertTrue(max(array_map('abs', $values)) < 1e-9, $label.' : '.json_encode($values));
			}
		}

	}

	/**
	 * Une mare creusée sous un coin d'un tracteur, et pas sous son centre : le tracteur se repose sur le sol neuf ('resettle()',
	 * 'rests()', qui le lit par ses coins), à 2 cm près.
	 */
	public function testAVehicleResettlesOnACarve(): void {

		$result = $this->run('
			const decor = draw([row(1, "tracteur", { coordinates: [30, 200], angle: 0, size: 2 }), row(2, "mare", { coordinates: [80, 0] })], .145);
			const [tractor, mare] = decor.elements;
			const relief = decor.geometry.relief;
			const carved = (x, n) => .145 * x - relief.height(x, n);
			const corners = n => {
				tractor.p = { x: 30, n };
				return MapSceneDecor.corners(MapSceneDecor.rectangle(tractor));
			};
			// La place du tracteur où la mare creuse sous un coin, son centre hors de la boite du sol changé (`recarve()`)
			let box = null;
			const recarve = decor.recarve.bind(decor);
			decor.recarve = (changed, e) => {
				box ??= changed;
				recarve(changed, e);
			};
			mare.p = { x: 30, n: 0 };
			decor.settle(mare);
			let place = null;
			// Le sud et le nord de la boite ne dépendent pas de la place que la mare quitte, au même nord qu\'elle
			for(let n = 0; n < 30 && place === null && box !== null; n += .25) {
				if(n > box.n1 && Math.max(...corners(n).map(c => carved(c.x, c.n))) > .01) {
					place = n;
				}
			}
			mare.p = { x: 80, n: 0 };
			decor.settle(mare);
			tractor.p = { x: 30, n: place ?? 0 };
			decor.settle(tractor);
			const before = tractor.root.matrixWorld.elements.slice();
			mare.p = { x: 30, n: 0 };
			decor.settle(mare);
			tractor.root.updateMatrixWorld(true);
			const placed = [-1, 1].flatMap(a => [-1, 1].map(b => new THREE.Vector3(a * tractor.width / 2, 0, b * tractor.depth / 2).applyMatrix4(tractor.root.matrixWorld)));
			emit({
				place,
				moved: Math.max(...tractor.root.matrixWorld.elements.map((value, i) => Math.abs(value - before[i]))),
				low: Math.min(...placed.map(p => p.y - relief.height(p.x, -p.z)))
			});
		');

		$this->assertTrue(($result['place'] ?? NULL) !== NULL, 'une place du tracteur a un coin dans le creux de la mare et son centre hors de la boite du sol changé');
		$this->assertTrue(($result['moved'] ?? 0) > 1e-3, 'le tracteur se repose sur le sol creusé sous son coin');
		// Le bord d'un creux est raide : les coins de l'objet incliné, un peu plus courts que son emprise, y tombent à 2 cm près. Le
		// sol sous le centre, que la mare ne creuse pas, le tient au-dessus du coin creusé
		$this->assertTrue(($result['low'] ?? -1) > -.02, 'aucun coin du tracteur ne passe sous le sol creusé, à 2 cm près : '.($result['low'] ?? 'rien'));

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
				const addons = await import("file://" + directory + "/base/scene/static/lib/three-0.170.0.BufferGeometryUtils.js");
				const { sceneBuildModel } = await import("file://" + directory + "/base/scene/static/js/model.js");
				const { sceneCreateGeometry } = await import("file://" + directory + "/base/scene/static/js/geometry.js");
				const { sceneCreateFigure } = await import("file://" + directory + "/base/scene/static/js/figure.js");
				const load = file => readFileSync(directory + "/" + file);
				const bin = load("base/scene/static/model/models.bin");
				const baked = { index: JSON.parse(load("base/scene/static/model/models.json").toString("utf8")), buffer: bin.buffer.slice(bin.byteOffset, bin.byteOffset + bin.byteLength) };
				const figure = sceneCreateFigure(THREE, addons, sceneCreateGeometry(THREE, addons), sceneBuildModel);

				const source = ["base/scene/static/js/plan.js", "base/scene/static/js/crew.js", "production/series/static/js/seriesSheet.js", "production/map/static/js/scene.js", "production/map/static/js/sceneLayer.js", "production/map/static/js/sceneDecor.js"].map(file => readFileSync(directory + "/" + file, "utf8")).join("\n");
				const context = vm.createContext({ window: {}, document: { addEventListener: () => {} }, console, clearTimeout, getComputedStyle: () => ({ getPropertyValue: () => "40", left: "12" }) });
				const { MapSceneDecor, MapSceneRelief } = vm.runInContext(source + "\n;({ MapSceneDecor, MapSceneRelief })", context);

				// Le sol rendu, une grille de 4 m sur 2 km : `shape` est la pente vers l\'est, ou la hauteur de chaque sommet
				const geometry = shape => {
					let relief = null;
					if(shape !== null) {
						const at = (typeof shape === "function") ? shape : (x => shape * x);
						relief = Object.create(MapSceneRelief.prototype);
						relief.xs = Array.from({ length: 501 }, (_, i) => -1000 + 4 * i);
						relief.ns = relief.xs.slice();
						relief.heights = new Float32Array(501 * 501);
						relief.ns.forEach((n, j) => relief.xs.forEach((x, i) => { relief.heights[j * 501 + i] = at(x, n); }));
						relief.raw = relief.heights.slice();
						relief.carves = new Map();
						relief.base = 0;
						relief.surface = new THREE.BufferGeometry();
						relief.surface.setAttribute("position", new THREE.BufferAttribute(Float32Array.from({ length: 501 * 501 * 3 }, (_, k) => (k % 3 === 1) ? relief.heights[(k - 1) / 3] : 0), 3));
						relief.surface.setAttribute("reliefAltitude", new THREE.BufferAttribute(relief.heights.slice(), 1));
					}
					return { beds: [], plots: new Map(), zones: new Map(), project: ([x, n]) => ({ x, n }), relief, height: (x, n) => (relief === null) ? 0 : relief.height(x, n) };
				};

				const row = (id, kit, values) => ({ id, kit, seed: id, angle: 0, size: 1, building: null, layer: null, diameter: null, laying: null, width: null, model: null, hedge: false, gate: null, zone: null, plot: null, coordinates: [0, 0], trace: null, ...values });
				const draw = (rows, shape) => new MapSceneDecor({
					socle: { THREE, addons, figure, baked, building: { build: () => ({ parts: [{ geometry: new THREE.BoxGeometry(8, 4, 6).translate(0, 2, 0), color: "#b0a08a" }] }) } },
					plan: { center: [0, 0], terrain: { grass: "meadow", soil: "brown" }, zones: [], buildings: [], decor: rows, layers: [] },
					geometry: geometry(shape),
					parent: new THREE.Group(),
					gradientMap: null,
					diameterLabel: null,
					changed: () => {},
					walked: () => {}
				});

				// Les emprises n\'existent que sur la feuille de pose (`attach()`) : chaque élément se refait avec elles
				const padded = decor => {
					const material = () => new THREE.MeshBasicMaterial();
					decor.pads = { allowed: material(), refused: material(), allowedLine: material(), refusedLine: material(), casing: material() };
					decor.elements.forEach(e => decor.build(e));
					return decor;
				};

				// Les sommets du volume d\'un élément, dans la scène
				const vertices = e => {
					const mesh = e.root.children[0];
					const position = mesh.geometry.attributes.position;
					return Array.from({ length: position.count }, (_, i) => new THREE.Vector3().fromBufferAttribute(position, i).applyMatrix4(mesh.matrixWorld));
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
