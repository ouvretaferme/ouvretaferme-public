<?php
namespace map;

/**
 * Le pré autour des cultures (`MapSceneMeadow`, `static/js/scene.js`) : une bande unie de `BAND` mètres autour des parcelles, des
 * planches et des zones, que ni le masque ni les volumes ne fleurissent ; des touffes et des fleurs semées sur la lisière au-delà,
 * hors de l'emprise du décor, dans la proportion du fond de leur case, celui de la zone quand elle en a un ; des fleurs fermées
 * hors de leur saison, et jamais plus de `TRIANGLES` triangles.
 *
 * Éprouvé sous node sur un plan plat : une parcelle carrée de 20 m, une zone de 40 m sur 40 à 20 m d'elle, un hangar posé sur la
 * lisière. Aucune écriture, aucune base ouverte.
 *
 * Lancement : dev/test package=map file=MeadowAi
 */
class MeadowAiTest extends \Test {

	/**
	 * Une parcelle au fond inconnu, retiré du schéma ou plus récent que le client, prend celui de la structure, comme une parcelle
	 * sans fond : ses volumes et son masque sont ceux de la structure.
	 */
	public function testUnknownZoneMeadowFollowsTheStructure(): void {

		$result = $this->run('
			const unknown = build("flowery", "moor");
			unknown.frame(30);
			const none = build("flowery", null);
			none.frame(30);
			const print = meadow => ({ zone: String(meadow.zones.get(7)), mask: meadow.uniforms.meadowMask.value.image.data.reduce((sum, value, i) => (sum * 31 + value * (i % 7 + 1)) % 1000000007, 0), flowers: meadow.kinds.flower.mesh.count, tufts: meadow.kinds.tuft.mesh.count });
			emit({ unknown: print(unknown), none: print(none) });
		');

		$this->assertEquals('null', $result['unknown']['zone'] ?? NULL, 'un fond de parcelle inconnu se lit comme NULL');
		$this->assertEquals($result['none'] ?? NULL, $result['unknown'] ?? [], 'une parcelle au fond inconnu se peint et se sème comme une parcelle sans fond');

	}

	/**
	 * La transformée de distance rend, case pour case, la distance exacte au centre de la case source la plus proche, comparée à un
	 * calcul brut sur une grille de 37 × 23 cases à sources tirées au hasard.
	 */
	public function testDistanceIsExact(): void {

		$result = $this->run('
			const columns = 37, rows = 23, step = .5;
			const random = MapSceneCrops.random(9);
			const sources = new Uint8Array(columns * rows).map(() => random() < .03 ? 1 : 0);
			sources[0] = 1;
			const distance = MapSceneMeadow.distance(sources, columns, rows, step);
			let worst = 0;
			for(let j = 0; j < rows; j++) {
				for(let i = 0; i < columns; i++) {
					let exact = Infinity;
					sources.forEach((source, k) => { if(source) { exact = Math.min(exact, Math.hypot(i - k % columns, j - Math.floor(k / columns)) * step); } });
					worst = Math.max(worst, Math.abs(exact - distance[j * columns + i]));
				}
			}
			emit({ worst, sources: sources.reduce((a, b) => a + b, 0) });
		');

		$this->assertTrue(($result['sources'] ?? 0) > 10, 'la grille porte plusieurs sources : '.($result['sources'] ?? 'rien'));
		$this->assertTrue(($result['worst'] ?? 1) < .0001, 'la distance est exacte sur chaque case : écart '.($result['worst'] ?? 'rien'));

	}

	/**
	 * La bande reste unie : le masque la porte pleine dans la parcelle et à 2 m d'elle, vide à 6 m, et aucun volume ne pousse à moins
	 * de `BAND` mètres d'une parcelle ni du contour d'une zone. Il en pousse pourtant sur la lisière.
	 */
	public function testBandStaysPlain(): void {

		$result = $this->run('
			const meadow = build("mixed", null);
			meadow.frame(30);
			const red = (x, n) => meadow.uniforms.meadowMask.value.image.data[meadow.cell(x, n) * 4];
			const near = instances(meadow).map(p => Math.min(toSquare(p, 0, 20, 0, 20), toOutline(p, ZONE))).reduce((a, b) => Math.min(a, b), Infinity);
			emit({ inside: red(10, 10), two: red(22, 10), six: red(26, 10), near, count: instances(meadow).length });
		');

		$this->assertEquals(255, $result['inside'] ?? NULL, 'le masque est plein dans la parcelle');
		$this->assertEquals(255, $result['two'] ?? NULL, 'le masque est plein à 2 m de la parcelle');
		$this->assertEquals(0, $result['six'] ?? NULL, 'le masque est vide à 6 m de la parcelle');
		$this->assertTrue(($result['count'] ?? 0) > 100, 'des volumes poussent sur la lisière : '.($result['count'] ?? 'rien'));
		$this->assertTrue(($result['near'] ?? 0) >= 3, 'aucun volume à moins de 3 m d\'une parcelle ou d\'un contour de zone : '.($result['near'] ?? 'rien'));

	}

	/**
	 * Un jardin sans tracé occupe toute sa parcelle, qui n'est pas une culture : le pré y pousse comme ailleurs, seul le contour de
	 * la parcelle porte la bande.
	 */
	public function testUndrawnGardenIsMeadow(): void {

		$result = $this->run('
			const meadow = build("mixed", null);
			meadow.frame(30);
			const red = meadow.uniforms.meadowMask.value.image.data[meadow.cell(20, -40) * 4];
			const inside = instances(meadow).filter(p => ScenePlanGeometry.inside(p, FIELD));
			emit({ red, inside: inside.length, near: Math.min(...inside.map(p => toOutline(p, FIELD))) });
		');

		$this->assertEquals(0, $result['red'] ?? NULL, 'le masque est vide au milieu de la parcelle au jardin sans tracé');
		$this->assertTrue(($result['inside'] ?? 0) > 50, 'le pré pousse dans la parcelle : '.($result['inside'] ?? 'rien'));
		$this->assertTrue(($result['near'] ?? 0) >= 3, 'à 3 m au moins de son contour : '.($result['near'] ?? 'rien'));

	}

	/**
	 * Le décor prévient à chaque jour montré, saison des feuilles comprise : le pré ne se repose que si une emprise a bougé, et se
	 * repose dès qu'elle bouge.
	 */
	public function testMeadowSettlesOnlyWhenTheDecorMoves(): void {

		$result = $this->run('
			const meadow = build("tufted", null, [row(1, "hangar", { coordinates: [-9, 10] })]);
			let settles = 0;
			const settle = meadow.settle;
			meadow.settle = function(...args) { settles++; return settle.apply(this, args); };
			meadow.frame(30);
			const first = settles;
			for(let day = 0; day < 20; day++) {
				meadow.changed();
				meadow.frame(30);
			}
			const still = settles - first;
			const hangar = meadow.decor.elements[0];
			hangar.p = { x: hangar.p.x - 20, n: hangar.p.n };
			meadow.changed();
			meadow.frame(30);
			const footprints = meadow.occupied();
			emit({ first, still, moved: settles - first - still, under: instances(meadow).filter(p => MapSceneMeadow.covers(footprints, p)).length });
		');

		$this->assertEquals(1, $result['first'] ?? NULL, 'le pré se pose à la première image');
		$this->assertEquals(0, $result['still'] ?? NULL, 'vingt jours sans emprise déplacée ne le reposent pas');
		$this->assertEquals(1, $result['moved'] ?? NULL, 'le hangar déplacé le repose une fois');
		$this->assertEquals(0, $result['under'] ?? NULL, 'rien ne pousse sous le hangar à sa nouvelle place');

	}

	/**
	 * Aucun volume ne pousse dans l'emprise d'un élément du décor. Le hangar est posé là où la lisière en sème : sans lui, des volumes
	 * pousseraient sous son toit.
	 */
	public function testNothingGrowsUnderTheDecor(): void {

		$result = $this->run('
			const meadow = build("tufted", null, [row(1, "hangar", { coordinates: [-9, 10] })]);
			meadow.frame(30);
			const footprints = meadow.occupied();
			const under = instances(meadow).filter(p => MapSceneMeadow.covers(footprints, p)).length;
			const sown = meadow.seeds.filter(p => MapSceneMeadow.covers(footprints, p)).length;
			emit({ under, sown, footprints: footprints.length });
		');

		$this->assertEquals(1, $result['footprints'] ?? NULL, 'le hangar se pose');
		$this->assertTrue(($result['sown'] ?? 0) > 0, 'la lisière sème des places sous le hangar : '.($result['sown'] ?? 'rien'));
		$this->assertEquals(0, $result['under'] ?? NULL, 'aucun volume ne pousse sous le hangar');

	}

	/**
	 * Le fond d'une zone l'emporte sur celui de la structure dans la zone, et seulement dans la zone : touffes seules autour de la
	 * parcelle (touffes), fleurs seules dans la zone (prairie fleurie). Le masque porte les forces de chaque fond. Une zone remise au
	 * fond de la structure reprend ses touffes.
	 */
	public function testZoneMeadowWins(): void {

		$result = $this->run('
			const meadow = build("tufted", "flowery");
			meadow.frame(30);
			const split = () => {
				const inside = { tuft: 0, flower: 0 }, outside = { tuft: 0, flower: 0 };
				for(const kind of ["tuft", "flower"]) {
					instances(meadow, kind).forEach(p => { (ScenePlanGeometry.inside(p, ZONE) ? inside : outside)[kind]++; });
				}
				return { inside, outside };
			};
			const data = meadow.uniforms.meadowMask.value.image.data;
			const force = (x, n) => [data[meadow.cell(x, n) * 4 + 1], data[meadow.cell(x, n) * 4 + 2]];
			const before = split();
			const forces = { zone: force(60, 20), around: force(30, -10) };
			meadow.choose(null, 7);
			meadow.frame(30);
			emit({ before, forces, after: split() });
		');

		$this->assertTrue(($result['before']['inside']['flower'] ?? 0) > 0, 'des fleurs poussent dans la zone fleurie');
		$this->assertEquals(0, $result['before']['inside']['tuft'] ?? NULL, 'aucune touffe dans la zone fleurie');
		$this->assertTrue(($result['before']['outside']['tuft'] ?? 0) > 0, 'des touffes poussent autour de la parcelle');
		$this->assertEquals(0, $result['before']['outside']['flower'] ?? NULL, 'aucune fleur hors de la zone, le fond de la structure étant en touffes');
		$this->assertEquals([59, 255], $result['forces']['zone'] ?? NULL, 'le masque porte les forces de la prairie fleurie dans la zone, touffes à 0,03 sur 0,13');
		$this->assertEquals([255, 0], $result['forces']['around'] ?? NULL, 'le masque porte les forces des touffes hors de la zone');
		$this->assertEquals(0, $result['after']['inside']['flower'] ?? NULL, 'la zone rendue au fond de la structure perd ses fleurs');
		$this->assertTrue(($result['after']['inside']['tuft'] ?? 0) > 0, 'la zone rendue au fond de la structure prend ses touffes');

	}

	/**
	 * L'herbe d'une parcelle se peint dans sa limite et nulle part ailleurs : le rang de chaque point se lit comme le nuanceur le
	 * lit (`MapScene.grassTexture()`), par la boite rendue et au plus proche, de part et d'autre de deux sommets opposés de la zone.
	 * Une parcelle sans tracé ne peint rien, une herbe inconnue ou remise à celle de la structure efface la sienne, et un plan sans
	 * tracé n'a pas de masque, ce que le nuanceur lit comme la structure partout.
	 */
	public function testZoneGrassStaysInsideItsLimit(): void {

		$result = $this->run('
			const meadow = build("mixed", null, [], { grasses: { 7: "dry", 11: "lush" } });
			const zones = meadow.grassZones;
			const rank = (x, n) => {
				const box = zones.grassBox.value;
				const { data, width, height } = zones.grassMask.value.image;
				const u = (x - box.x) / box.z, v = (n - box.y) / box.w;
				return (u < 0 || v < 0 || u >= 1 || v >= 1) ? 0 : data[Math.floor(v * height) * width + Math.floor(u * width)];
			};
			const count = value => Array.from(zones.grassMask.value.image.data).filter(r => r === value).length;
			const names = Object.keys(MapScene.GRASSES);
			const texture = zones.grassMask.value;
			const ranks = { zone: rank(60, 20), corner: rank(40.2, .2), beforeCorner: rank(39.8, .2), farCorner: rank(79.8, 39.8), beyondFarCorner: rank(80.2, 39.8), plot: rank(10, 10), field: rank(20, -40) };
			const filters = [texture.magFilter === THREE.NearestFilter, texture.minFilter === THREE.NearestFilter, texture.generateMipmaps];
			const lush = count(names.indexOf("lush") + 1);
			meadow.chooseGrass("moor", 7);
			const unknown = count(0) === zones.grassMask.value.image.data.length && meadow.grasses.get(7) === null;
			const disposed = texture !== zones.grassMask.value;
			meadow.chooseGrass("dry", 7);
			meadow.chooseGrass(null, 7);
			const reset = count(0) === zones.grassMask.value.image.data.length;
			const undrawn = build("mixed", null, [], { grasses: { 7: "dry" }, drawn: false }).grassZones;
			emit({ ranks, dry: names.indexOf("dry") + 1, filters, lush, unknown, disposed, reset, undrawn: { mask: undrawn.grassMask.value, width: undrawn.grassBox.value.z } });
		');

		$dry = $result['dry'] ?? NULL;

		$this->assertEquals(['zone' => $dry, 'corner' => $dry, 'beforeCorner' => 0, 'farCorner' => $dry, 'beyondFarCorner' => 0, 'plot' => 0, 'field' => 0], $result['ranks'] ?? NULL, 'l\'herbe sèche de la zone 7 tient dans sa limite, à 20 cm près de part et d\'autre de ses sommets, et le reste du plan garde l\'herbe de la structure');
		$this->assertEquals([TRUE, TRUE, FALSE], $result['filters'] ?? NULL, 'le masque se lit au plus proche et sans réductions, qui fondraient deux rangs');
		$this->assertEquals(0, $result['lush'] ?? NULL, 'une parcelle sans tracé ne peint son herbe nulle part');
		$this->assertTrue($result['unknown'] ?? FALSE, 'une herbe inconnue se lit comme NULL et reprend celle de la structure');
		$this->assertTrue($result['disposed'] ?? FALSE, 'un masque repeint remplace le précédent');
		$this->assertTrue($result['reset'] ?? FALSE, 'une parcelle remise à « Comme la ferme » reprend l\'herbe de la structure');
		$this->assertEquals(['mask' => NULL, 'width' => 0], $result['undrawn'] ?? NULL, 'un plan sans tracé n\'a pas de masque, et sa boite vide fait lire la structure partout');

	}

	/**
	 * Les fleurs, semées et peintes, se ferment hors de leur saison et reviennent avec elle ; les touffes restent. Le pré ne passe
	 * jamais `TRIANGLES`, au fond le plus fleuri comme au plus touffu.
	 */
	public function testFlowersFollowTheSeasonUnderBudget(): void {

		$result = $this->run('
			const out = {};
			for(const name of Object.keys(MapScene.MEADOWS)) {
				const meadow = build(name, null);
				meadow.frame(2);
				const winter = { flowers: meadow.kinds.flower.mesh.count, tufts: meadow.kinds.tuft.mesh.count, bloom: meadow.uniforms.meadowBloom.value, painted: meadow.flowers.length };
				meadow.frame(30);
				const summer = { flowers: meadow.kinds.flower.mesh.count, tufts: meadow.kinds.tuft.mesh.count, bloom: meadow.uniforms.meadowBloom.value, painted: meadow.flowers.length };
				const counted = meadow.kinds.flower.mesh.count * meadow.kinds.flower.triangles + meadow.kinds.tuft.mesh.count * meadow.kinds.tuft.triangles;
				out[name] = { winter, summer, counted, triangles: meadow.triangles, budget: MapSceneMeadow.TRIANGLES };
			}
			emit(out);
		');

		foreach(['mixed', 'flowery', 'tufted'] as $name) {
			$this->assertEquals(0, $result[$name]['winter']['flowers'] ?? NULL, 'aucune fleur semée en hiver, fond '.$name);
			$this->assertEquals(0, $result[$name]['winter']['bloom'] ?? NULL, 'aucune fleur peinte en hiver, fond '.$name);
			$this->assertEquals($result[$name]['summer']['tufts'] ?? NULL, $result[$name]['winter']['tufts'] ?? NULL, 'les touffes restent en hiver, fond '.$name);
			$this->assertEquals(1, $result[$name]['summer']['bloom'] ?? NULL, 'les fleurs peintes reviennent en saison, fond '.$name);
			$this->assertEquals($result[$name]['summer']['flowers'] ?? NULL, $result[$name]['summer']['painted'] ?? NULL, 'chaque fleur semée est offerte aux insectes, fond '.$name);
			$this->assertEquals($result[$name]['counted'] ?? NULL, $result[$name]['triangles'] ?? NULL, 'le compte de triangles est celui des instances posées, fond '.$name);
			$this->assertTrue(($result[$name]['triangles'] ?? PHP_INT_MAX) <= ($result[$name]['budget'] ?? 0), 'le fond '.$name.' tient sous le budget : '.($result[$name]['triangles'] ?? 'rien'));
		}

		$this->assertTrue(($result['flowery']['summer']['flowers'] ?? 0) > 0, 'la prairie fleurie fleurit en saison');
		$this->assertTrue(($result['tufted']['summer']['tufts'] ?? 0) > 0, 'les touffes poussent');

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
				const context = vm.createContext({ window: {}, document: { addEventListener: () => {} }, console, clearTimeout });
				const { MapScene, MapSceneMeadow, MapSceneDecor, MapSceneCrops, ScenePlanGeometry } = vm.runInContext(source + "\n;({ MapScene, MapSceneMeadow, MapSceneDecor, MapSceneCrops, ScenePlanGeometry })", context);

				// Le jardin tracé carré de 20 m, et la zone 7 de 40 m sur 40, à 20 m à l\'est
				const PLOT = [{ x: 0, n: 0 }, { x: 20, n: 0 }, { x: 20, n: 20 }, { x: 0, n: 20 }];
				const ZONE = [{ x: 40, n: 0 }, { x: 80, n: 0 }, { x: 80, n: 40 }, { x: 40, n: 40 }];
				// La zone 9, à 20 m au sud, et son jardin sans tracé, qui prend le contour de toute la zone comme en base
				const FIELD = [{ x: 0, n: -60 }, { x: 40, n: -60 }, { x: 40, n: -20 }, { x: 0, n: -20 }];

				const row = (id, kit, values) => ({ id, kit, seed: id, angle: 0, size: 1, building: null, layer: null, diameter: null, laying: null, width: null, model: null, hedge: false, gate: null, zone: null, plot: null, coordinates: [0, 0], trace: null, ...values });

				// Un plan plat, le fond de la structure `meadow`, celui de la zone 7 `zone` (null : celui de la structure), le décor `rows`,
				// l\'herbe des zones 7, 9 et 11 dans `grasses`, la 11 n\'ayant pas de tracé ; sans `drawn`, rien n\'est tracé
				const build = (meadow, zone, rows = [], { grasses = {}, drawn = true } = {}) => {
					const geometry = { beds: [], plots: new Map(drawn ? [[1, { points: PLOT, drawn: true, plot: { mode: "open-field" }, rectangle: null }], [2, { points: FIELD, drawn: false, plot: { mode: "open-field" }, rectangle: null }]] : []), zones: new Map(drawn ? [[7, ZONE], [9, FIELD]] : []), project: ([x, n]) => ({ x, n }), relief: null, height: () => 0, corners: () => [] };
					const parent = new THREE.Group();
					const decor = new MapSceneDecor({
						socle: { THREE, addons, figure, baked, building: null },
						plan: { center: [0, 0], terrain: { grass: "meadow", soil: "brown", meadow }, zones: [{ id: 7, name: "Pré" }, { id: 9, name: "Champ" }], buildings: [], decor: rows, layers: [] },
						geometry,
						parent,
						gradientMap: null,
						diameterLabel: null,
						changed: () => {},
						walked: () => {}
					});
					const zones = [{ id: 7, decorMeadow: zone }, { id: 9, decorMeadow: null }, { id: 11, decorMeadow: null }].map(row => ({ ...row, decorGrass: grasses[row.id] ?? null }));
					return new MapSceneMeadow({ THREE, plan: { terrain: { meadow }, zones, plots: [], beds: [], series: [], places: [] }, geometry, decor, material: new THREE.MeshToonMaterial(), grassZones: MapScene.grassZones(THREE), parent, gradientMap: null });
				};

				// Le pied de chaque instance posée, d\'un volume ou des deux
				const instances = (meadow, only = null) => {
					const matrix = new THREE.Matrix4(), v = new THREE.Vector3();
					return Object.entries(meadow.kinds).filter(([name]) => only === null || name === only).flatMap(([, kind]) => Array.from({ length: kind.mesh.count }, (_, i) => {
						kind.mesh.getMatrixAt(i, matrix);
						v.setFromMatrixPosition(matrix);
						return { x: v.x, n: -v.z };
					}));
				};

				const toSquare = (p, x0, x1, n0, n1) => Math.hypot(Math.max(x0 - p.x, 0, p.x - x1), Math.max(n0 - p.n, 0, p.n - n1));
				const toOutline = (p, points) => Math.min(...points.map((a, i) => {
					const b = points[(i + 1) % points.length];
					const d = ScenePlanGeometry.sub(b, a);
					const t = Math.max(0, Math.min(1, ScenePlanGeometry.dot(ScenePlanGeometry.sub(p, a), d) / ScenePlanGeometry.dot(d, d)));
					return Math.hypot(p.x - a.x - t * d.x, p.n - a.n - t * d.n);
				}));

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
