<?php
namespace map;

/**
 * La pose du décor sur le relief (F-relief R4, `static/js/sceneDecor.js`, `static/js/scene.js`) : le clic de pose touche le sol
 * rendu (`MapSceneDecor::pick()`), les plants d'un kit montent chacun avec le sol sous leur pied et l'emprise suit le sol, y compris
 * sous un bâtiment posé au plus haut (`MapSceneDecor::fitGround()`), le tour d'un tracé et celui d'une parcelle choisie se drapent,
 * la grille, la limite et le pli se coupent aux triangles du sol (`MapSceneDecor::groundLine()`, `MapSceneRelief::section()`), les
 * poignées d'un tracé se posent à la hauteur du sol (`showHandles()`, `project()`), et une pancarte au plus bas de ses deux poteaux
 * (`MapSceneBoards::frame()`).
 *
 * Éprouvé sous node, avec le vrai modèle cuit, sur un sol qui monte de 10 % vers l'est (grille de 4 m), bombé ou bosselé selon le
 * cas. Sans relief, chaque position est celle d'avant le relief. Aucune écriture, aucune base ouverte.
 *
 * Lancement : dev/test package=map file=DecorPoseReliefAi
 */
class DecorPoseReliefAiTest extends \Test {

	/**
	 * Le point visé par le pointeur est sur le sol rendu sous le rayon, à 1 cm près, sous une vue plongeante comme sous une vue
	 * rasante face à la pente. Sans relief, c'est le point du plan y = 0, comme avant le relief.
	 */
	public function testThePickMeetsTheGround(): void {

		$result = $this->run('
			const gaps = (decor, ground) => {
				let gap = 0, off = 0, missed = 0;
				for(let x = 40; x <= 760; x += 80) {
					for(let y = 40; y <= 560; y += 80) {
						const p = decor.pick({ clientX: x, clientY: y });
						if(p === null) {
							missed++;
							continue;
						}
						const { origin, direction } = decor.ray.ray;
						const t = (p.x - origin.x) / direction.x;
						gap = Math.max(gap, Math.abs(origin.y + t * direction.y - ground(p.x, p.n)));
						off = Math.max(off, Math.abs(-(origin.z + t * direction.z) - p.n));
					}
				}
				return { gap, off, missed };
			};
			const plunging = aimed(draw([], .1), [-60, 80, 70], [20, 2, -10]);
			const grazing = aimed(draw([], .1), [-120, 9, 10], [0, 0, 0]);
			const flat = aimed(draw([], null), [-60, 80, 70], [20, 2, -10]);
			const p = flat.pick({ clientX: 300, clientY: 200 });
			const { origin, direction } = flat.ray.ray;
			const t = -origin.y / direction.y;
			emit({
				plunging: gaps(plunging, (x, n) => .1 * x),
				grazing: gaps(grazing, (x, n) => .1 * x),
				flat: [p.x - (origin.x + t * direction.x), p.n + origin.z + t * direction.z, Object.keys(p)],
				keys: Object.keys(plunging.pick({ clientX: 400, clientY: 300 }))
			});
		');

		foreach(['plunging' => 'plongeante', 'grazing' => 'rasante'] as $view => $label) {
			$this->assertEquals(0, $result[$view]['missed'] ?? NULL, 'sous une vue '.$label.', chaque point visé touche le sol');
			$this->assertTrue(($result[$view]['gap'] ?? 1) < .01, 'sous une vue '.$label.', le point visé est sur le sol rendu à 1 cm près : '.($result[$view]['gap'] ?? 'rien'));
			$this->assertTrue(($result[$view]['off'] ?? 1) < 1e-6, 'sous une vue '.$label.', le point visé est sur le rayon');
		}

		$this->assertEquals([0.0, 0.0, ['x', 'n']], [round($result['flat'][0] ?? 1, 9), round($result['flat'][1] ?? 1, 9), $result['flat'][2] ?? NULL], 'sans relief, le point visé est celui du plan y = 0');
		$this->assertEquals(['x', 'n'], $result['keys'] ?? NULL, 'sur le relief, pick() rend un point du plan, sans hauteur');

	}

	/**
	 * Un bosquet, un verger et un massif tournés et grandis : chaque plant monte d'un seul tenant, son pied à la hauteur du sol sous
	 * lui, le point d'attache de ses feuilles avec lui, et une pose de plus ne cumule rien. Sur un relief plat, aucun sommet ne bouge.
	 */
	public function testKitPlantsStandOnTheGround(): void {

		$result = $this->run('
			const rows = [
				row(1, "bosquet", { coordinates: [40, 20], angle: .7, size: 1.6 }),
				row(2, "verger", { coordinates: [-40, 10], angle: 2.1, size: .8 }),
				row(3, "massif", { coordinates: [10, -30], angle: -1.2, size: 2.2 })
			];
			const decor = draw(rows, .1);
			const measure = e => {
				const geometry = e.root.children[0].geometry;
				const position = geometry.attributes.position;
				const anchor = geometry.attributes.anchor;
				let spread = 0, foot = 0, leaves = 0;
				e.feet.forEach(f => {
					const lifts = [];
					for(let i = f.first; i < f.last; i++) {
						const lift = position.getY(i) - e.plain.kit[i * 3 + 1];
						lifts.push(lift);
						leaves = Math.max(leaves, Math.abs(anchor.getY(i) - e.plain.anchor[i * 3 + 1] - lift));
					}
					spread = Math.max(spread, Math.max(...lifts) - Math.min(...lifts));
					const at = new THREE.Vector3(f.x, lifts[0], f.z).applyMatrix4(e.root.children[0].matrixWorld);
					foot = Math.max(foot, Math.abs(at.y - .1 * at.x));
				});
				return { spread, foot, leaves, blocks: e.feet.length, covered: e.feet.at(-1).last === position.count && e.feet[0].first === 0, printed: print(e) };
			};
			const once = decor.elements.map(e => { e.root.updateMatrixWorld(true); return measure(e); });
			decor.elements.forEach(e => { decor.settle(e); decor.settle(e); e.root.updateMatrixWorld(true); });
			const twice = decor.elements.map(measure);
			const flat = draw(rows, 0).elements.map(print);
			const none = draw(rows, null).elements.map(print);
			emit({ once: once.map(({ printed, ...rest }) => rest), twice: twice.map(({ printed, ...rest }) => rest), same: once.every((m, i) => m.printed === twice[i].printed), flat: flat.every((p, i) => p === none[i]) });
		');

		foreach($result['twice'] ?? [] as $k => $kit) {
			$this->assertTrue($kit['blocks'] > 1 and $kit['covered'], 'le kit '.$k.' a ses plants, et leurs plages couvrent tout son volume');
			$this->assertTrue($kit['spread'] < 1e-5, 'chaque plant du kit '.$k.' monte d\'un seul tenant : '.$kit['spread']);
			$this->assertTrue($kit['foot'] < 1e-3, 'chaque pied du kit '.$k.' est sur le sol : écart '.$kit['foot']);
			$this->assertTrue($kit['leaves'] < 1e-5, 'le point d\'attache des feuilles du kit '.$k.' monte avec son plant : '.$kit['leaves']);
		}

		$this->assertEquals(3, count($result['twice'] ?? []), 'les trois kits se montent');
		$this->assertTrue($result['same'] ?? FALSE, 'deux poses de plus ne cumulent rien');
		$this->assertTrue($result['flat'] ?? FALSE, 'sur un relief plat, aucun sommet d\'un kit ne bouge');

	}

	/**
	 * Sur un sol bombé qui monte de 10 % vers l'est, l'emprise d'un hangar posé au plus haut, celle d'un objet tourné, les carrés de
	 * pied d'un kit et le tour d'un chemin restent au-dessus du sol de leur levée, à chaque sommet comme au milieu de chaque case du
	 * carreau. Sans relief, l'emprise reste un carreau d'une case à .12.
	 */
	public function testPadsLieOnTheGround(): void {

		$result = $this->run('
			const rows = [
				row(1, "hangar", { coordinates: [0, 0], angle: .5 }),
				row(2, "tracteur", { coordinates: [-30, 15], angle: 1.1, size: 1.5 }),
				row(3, "bosquet", { coordinates: [40, 20], angle: .7, size: 1.6 }),
				row(4, "chemin", { width: 2, trace: [[-30, -20], [0, -25], [30, -20]] })
			];
			const ground = (x, n) => .1 * x + .002 * n * n;
			const decor = padded(draw(rows, ground));
			decor.elements.forEach(e => decor.settle(e));
			const measure = e => {
				e.root.updateMatrixWorld(true);
				const out = [];
				for(const mesh of [e.pad, ...e.pad.children]) {
					const position = mesh.geometry.attributes.position;
					const index = mesh.geometry.index;
					const v = new THREE.Vector3();
					const world = i => v.fromBufferAttribute(position, i).applyMatrix4(mesh.matrixWorld).clone();
					const above = p => p.y - decor.geometry.relief.height(p.x, -p.z);
					let low = Infinity, high = -Infinity;
					for(let i = 0; i < position.count; i++) {
						low = Math.min(low, above(world(i)));
						high = Math.max(high, above(world(i)));
					}
					const triangles = (index === null) ? position.count / 3 : index.count / 3;
					for(let t = 0; t < triangles; t++) {
						const [a, b, c] = [0, 1, 2].map(k => world((index === null) ? t * 3 + k : index.getX(t * 3 + k)));
						const middle = a.add(b).add(c).divideScalar(3);
						low = Math.min(low, above(middle));
						high = Math.max(high, above(middle));
					}
					out.push({ low, high, triangles });
				}
				return { kit: e.kit, pieces: out, root: e.root.position.y - decor.geometry.relief.height(e.p.x, e.p.n) };
			};
			const none = padded(draw(rows, null));
			const rectangle = none.elements[0].pad.geometry.attributes.position;
			emit({
				pads: decor.elements.map(measure),
				flat: { count: rectangle.count, heights: [...new Set(Array.from({ length: rectangle.count }, (_, i) => rectangle.getY(i)))] }
			});
		');

		$this->assertEquals(4, count($result['pads'] ?? []), 'les quatre éléments ont leur emprise');
		$this->assertTrue(($result['pads'][0]['root'] ?? 0) > .3, 'le hangar est posé au plus haut du sol, au-dessus du sol sous son centre : '.($result['pads'][0]['root'] ?? 'rien'));
		$this->assertTrue(($result['pads'][0]['pieces'][0]['triangles'] ?? 0) > 2, 'l\'emprise du hangar se coupe au pas du sol');

		foreach($result['pads'] ?? [] as $pad) {
			foreach($pad['pieces'] as $piece) {
				$this->assertTrue($piece['low'] > .05 and $piece['high'] < .2, 'l\'emprise du '.$pad['kit'].' reste au-dessus du sol de sa levée, au milieu de chaque case comme à chaque sommet : de '.$piece['low'].' à '.$piece['high']);
			}
		}

		$this->assertEquals(['count' => 4, 'heights' => [0.12]], ['count' => $result['flat']['count'] ?? NULL, 'heights' => array_map(fn($h) => round($h, 6), $result['flat']['heights'] ?? [])], 'sans relief, l\'emprise reste un carreau d\'une case à .12');

	}

	/**
	 * Sur un sol bosselé, chaque segment de la grille, de la grille fine, de la limite et du pli passe à sa levée au-dessus du sol en
	 * son milieu, comme à ses deux bouts : il reste dans un triangle du sol. Sans relief, les lignes sont celles d'avant le relief.
	 */
	public function testLinesStayAboveTheGround(): void {

		$result = $this->run('
			const noise = (x, n) => .1 * x + .4 * (Math.abs(Math.sin(x * 12.9898 + n * 78.233) * 43758.5453) % 1 - .5);
			const check = (decor, geometry, lift) => {
				const position = geometry.attributes.position;
				let gap = 0;
				for(let i = 0; i < position.count; i += 2) {
					const x = (position.getX(i) + position.getX(i + 1)) / 2;
					const z = (position.getZ(i) + position.getZ(i + 1)) / 2;
					const y = (position.getY(i) + position.getY(i + 1)) / 2;
					for(const k of [i, i + 1]) {
						gap = Math.max(gap, Math.abs(position.getY(k) - decor.geometry.relief.height(position.getX(k), -position.getZ(k)) - lift));
					}
					gap = Math.max(gap, Math.abs(y - decor.geometry.relief.height(x, -z) - lift));
				}
				return { gap, segments: position.count / 2 };
			};
			const bounds = { x0: -20, x1: 20, n0: -15, n1: 15 };
			const island = { id: 1 };
			const other = { id: 2 };
			const lines = slope => {
				const decor = draw([], slope);
				decor.bounds = bounds;
				decor.islandOf = p => (Math.hypot(p.x, p.n) > 14) ? null : ((p.x < 0) ? island : other);
				return { decor, grid: decor.gridLines(bounds, 5), fine: decor.gridLines(bounds, 1), ...decor.limitLines() };
			};
			const bumpy = lines(noise);
			const none = lines(null);
			// Les lignes d\'avant le relief, à plat : deux points par segment, à leur levée
			const before = (geometry, lift) => Array.from(geometry.attributes.position.array).every((value, k) => (k % 3 !== 1) || Math.abs(value - lift) < 1e-6);
			emit({
				grid: check(bumpy.decor, bumpy.grid, MapSceneDecor.GRID_LIFT),
				fine: check(bumpy.decor, bumpy.fine, MapSceneDecor.GRID_LIFT),
				limit: check(bumpy.decor, bumpy.limit, MapSceneDecor.LIMIT_LIFT),
				fold: check(bumpy.decor, bumpy.fold, MapSceneDecor.LIMIT_LIFT),
				flat: [none.grid.attributes.position.count, bumpy.grid.attributes.position.count, before(none.grid, MapSceneDecor.GRID_LIFT), before(none.limit, MapSceneDecor.LIMIT_LIFT), before(none.fold, MapSceneDecor.LIMIT_LIFT), none.limit.attributes.position.count, bumpy.limit.attributes.position.count]
			});
		');

		foreach(['grid' => 'la grille', 'fine' => 'la grille fine', 'limit' => 'la limite', 'fold' => 'le pli'] as $line => $label) {
			$this->assertTrue(($result[$line]['segments'] ?? 0) > 0, $label.' a des segments');
			$this->assertTrue(($result[$line]['gap'] ?? 1) < 1e-4, $label.' passe à sa levée au-dessus du sol, en son milieu comme à ses bouts : écart '.($result[$line]['gap'] ?? 'rien'));
		}

		[$noneGrid, $bumpyGrid, $gridFlat, $limitFlat, $foldFlat, $noneLimit, $bumpyLimit] = $result['flat'] ?? [0, 0, FALSE, FALSE, FALSE, 0, 0];

		$this->assertTrue($gridFlat and $limitFlat and $foldFlat, 'sans relief, chaque ligne reste à sa levée');
		$this->assertTrue($bumpyGrid > $noneGrid and $bumpyGrid <= 4 * $noneGrid, 'sur le relief, la grille se coupe sans quadrupler : '.$noneGrid.' sommets à plat, '.$bumpyGrid.' sur le relief');
		$this->assertTrue($bumpyLimit > $noneLimit, 'sur le relief, la limite se coupe aussi');

	}

	/**
	 * Une mare creuse le sol sous la grille déjà tracée : la grille, la limite et le pli s'abaissent avec lui, puis remontent quand
	 * la mare part ou se retire. Avant la feuille de pose, la grille n'existe pas et le creux se
	 * fait sans elle.
	 */
	public function testTheGridFollowsACarve(): void {

		$result = $this->run('
			const decor = draw([row(1, "mare", { coordinates: [30, 0] })], .1);
			const [mare] = decor.elements;
			const relief = decor.geometry.relief;
			const unborn = (decor.grid === null);

			decor.bounds = { x0: -20, x1: 80, n0: -20, n1: 20 };
			decor.islandOf = p => (Math.abs(p.n) > 18 || p.x < -18 || p.x > 78) ? null : { id: 1 };
			decor.grid = new THREE.LineSegments(decor.gridLines(decor.bounds, 5));
			const { limit, fold } = decor.limitLines();
			decor.grid.add(new THREE.LineSegments(limit), new THREE.LineSegments(fold));

			// Le plus grand écart à la levée, ligne par ligne
			const gap = () => [decor.grid, ...decor.grid.children].map((line, k) => {
				const position = line.geometry.attributes.position;
				let worst = 0;
				for(let i = 0; i < position.count; i++) {
					worst = Math.max(worst, Math.abs(position.getY(i) - relief.height(position.getX(i), -position.getZ(i)) - ((k === 0) ? MapSceneDecor.GRID_LIFT : MapSceneDecor.LIMIT_LIFT)));
				}
				return worst;
			});
			mare.p = { x: 60, n: 0 };
			decor.settle(mare);
			const moved = { gap: gap(), deep: .1 * 60 - relief.height(60, 0) };
			decor.drop(mare);
			emit({ unborn, moved, dropped: gap() });
		');

		$this->assertTrue($result['unborn'] ?? FALSE, 'avant la feuille de pose, la grille est nulle et le creux se fait sans elle');
		$this->assertTrue(($result['moved']['deep'] ?? -1) > 0, 'la mare déplacée creuse le sol sous elle : '.($result['moved']['deep'] ?? 'rien'));

		foreach(['moved' => 'la mare déplacée', 'dropped' => 'la mare retirée'] as $key => $label) {
			$gaps = ($key === 'moved') ? ($result['moved']['gap'] ?? []) : ($result['dropped'] ?? []);
			$this->assertEquals(3, count($gaps), $label.' : la grille, la limite et le pli se mesurent');
			$this->assertTrue($gaps !== [] and max($gaps) < 1e-4, $label.' : la grille, la limite et le pli restent à leur levée au-dessus du sol : '.json_encode($gaps));
		}

	}

	/**
	 * Les poignées d'un tracé se posent à la hauteur du sol sous leur point, et `project()` en rend la place à l'écran : la pastille
	 * qui retire un point revient de l'écran à la profondeur du sol.
	 */
	public function testHandlesStandOnTheGround(): void {

		$result = $this->run('
			const decor = aimed(draw([], .1), [-60, 80, 70], [20, 2, -10]);
			const points = [{ x: -20, n: 5 }, { x: 10, n: -5 }, { x: 35, n: 12 }];
			const box = decor.canvas.getBoundingClientRect();
			const screen = decor.project(points, box);
			const back = screen.map(at => decor.unproject(at, box));
			decor.active = true;
			decor.tracing = { e: { bad: null }, points, cursor: null };
			decor.point = null;
			decor.handles = [];
			decor.tools = {};
			decor.unpoint = { visible: false, position: new THREE.Vector3() };
			decor.handle = () => ({ position: new THREE.Vector3(), visible: false, element: { dataset: {}, classList: { toggle: () => {} } } });
			decor.showHandles();
			emit({
				back: Math.max(...back.map((v, i) => Math.hypot(v.x - points[i].x, v.y - .1 * points[i].x, v.z + points[i].n))),
				handles: Math.max(...decor.handles.filter(h => h.visible).map(h => Math.abs(h.position.y - .1 * h.position.x))),
				shown: decor.handles.filter(h => h.visible).length
			});
		');

		$this->assertTrue(($result['back'] ?? 1) < 1e-3, 'la place à l\'écran d\'un point est celle du sol sous lui : écart '.($result['back'] ?? 'rien'));
		$this->assertEquals(3, $result['shown'] ?? NULL, 'les trois points du tracé ont leur poignée');
		$this->assertTrue(($result['handles'] ?? 1) < 1e-6, 'chaque poignée est à la hauteur du sol sous son point');

	}

	/**
	 * Le tour d'une parcelle choisie, en coordonnées de la scène, se drape : chacun de ses sommets est à sa levée au-dessus du sol.
	 */
	public function testTheChosenOutlineDrapes(): void {

		$result = $this->run('
			const decor = padded(draw([], .1));
			decor.outline = decor.stroke(decor.pads.allowedLine);
			decor.outlined = null;
			decor.loopOf = () => [{ x: -20, n: -10 }, { x: 30, n: -10 }, { x: 30, n: 15 }, { x: -20, n: 15 }];
			decor.refresh = () => {};
			decor.reveal = () => {};
			decor.choose({ id: 1 });
			const lifts = mesh => {
				const position = mesh.geometry.attributes.position;
				const values = Array.from({ length: position.count }, (_, i) => position.getY(i) - .1 * position.getX(i));
				return [Math.min(...values), Math.max(...values)];
			};
			emit({ stroke: lifts(decor.outline), casing: lifts(decor.outline.children[0]) });
		');

		$this->assertEquals([0.12, 0.12], array_map(fn($value) => round($value, 4), $result['stroke'] ?? []), 'le trait du tour est à .12 au-dessus du sol');
		$this->assertEquals([0.11, 0.11], array_map(fn($value) => round($value, 4), $result['casing'] ?? []), 'son cerne est à .11 au-dessus du sol');

	}

	/**
	 * Une pancarte, tournée vers la caméra et grandie avec le recul, se pose au plus bas du sol sous ses deux poteaux ; sans relief,
	 * à 0.
	 */
	public function testTheBoardStandsOnItsLowerPost(): void {

		$result = $this->run('
			const camera = new THREE.OrthographicCamera(-50, 50, 37.5, -37.5, .1, 2000);
			camera.position.set(-60, 80, 70);
			camera.zoom = .5;
			const controls = { target: new THREE.Vector3(20, 0, -10) };
			const posts = (slope, turned) => {
				const plan = geometry(slope);
				const board = new THREE.Object3D();
				board.position.set(12, 0, 4);
				const boards = { boards: [{ board, base: { x: 12, n: -4 } }], ground: plan };
				MapSceneBoards.prototype.frame.call(boards, camera, controls);
				if(turned) {
					camera.position.set(70, 80, 60);
					MapSceneBoards.prototype.frame.call(boards, camera, controls);
				}
				board.updateMatrixWorld(true);
				const feet = [-1, 1].map(side => new THREE.Vector3(side * MapSceneBoards.POST, 0, 0).applyMatrix4(board.matrixWorld));
				return { y: board.position.y, feet: feet.map(foot => foot.y - plan.height(foot.x, -foot.z)) };
			};
			emit({ slope: posts(.1, false), turned: posts(.1, true), none: posts(null, false) });
		');

		foreach(['slope' => 'tournée vers la caméra', 'turned' => 'après un tour de la caméra'] as $case => $label) {
			$feet = $result[$case]['feet'] ?? [1, 1];
			$this->assertTrue(abs(max($feet)) < 1e-6 and min($feet) < -.1, 'une pancarte '.$label.' se pose sur son poteau le plus bas, l\'autre dans le sol : '.json_encode($feet));
		}

		$this->assertEquals(0, $result['none']['y'] ?? NULL, 'sans relief, une pancarte reste à 0');

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
				// Les poignées lisent la taille d\'une cible sur la feuille de pose
				const context = vm.createContext({ window: {}, document: { addEventListener: () => {} }, console, clearTimeout, getComputedStyle: () => ({ getPropertyValue: () => "40", left: "12" }) });
				const { MapSceneDecor, MapSceneRelief, MapSceneBoards, ScenePlanGeometry } = vm.runInContext(source + "\n;({ MapSceneDecor, MapSceneRelief, MapSceneBoards, ScenePlanGeometry })", context);

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

				// Une caméra orthographique de 800 × 600 pixels, de `from` vers `to`
				const aimed = (decor, from, to) => {
					const camera = new THREE.OrthographicCamera(-50, 50, 37.5, -37.5, .1, 2000);
					camera.position.set(...from);
					camera.lookAt(...to);
					camera.updateMatrixWorld();
					decor.camera = camera;
					decor.canvas = { getBoundingClientRect: () => ({ left: 0, top: 0, width: 800, height: 600 }) };
					decor.ray = new THREE.Raycaster();
					return decor;
				};

				const print = e => {
					const parts = [];
					e.root.traverse(object => {
						if(object.isMesh) {
							parts.push(Array.from(object.geometry.attributes.position.array, value => value.toFixed(5)).join(","));
						}
					});
					return parts.join("|");
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
