<?php
namespace map;

/**
 * Le décor sur le relief (`static/js/sceneDecor.js`, `static/js/scene.js`) : un tracé, une haie, une clôture et un tuyau épousent le
 * sol (`MapSceneRelief.conform()`), une haie travée par travée et ses arbres d'un seul tenant ; un bâtiment et une étendue d'eau
 * restent de niveau, posés au plus haut du sol sous eux (`highest()`) sur un soubassement refait à chaque `settle()` (`plinth()`) ;
 * un pont se pose à la plus haute de ses deux berges, et le marquage d'une route suit le sol.
 *
 * Éprouvé sous node sur un sol qui monte de 10 % vers l'est (hauteur 0,1 × x, grille de 4 m), avec le vrai modèle cuit. Un sol plat
 * ne bouge aucun sommet : le drapé n'y change rien. Aucune écriture, aucune base ouverte.
 *
 * Lancement : dev/test package=map file=DecorReliefAi
 */
class DecorReliefAiTest extends \Test {

	/**
	 * Sur un relief plat, le drapé ne bouge aucun sommet : un tracé, une haie, une clôture et un bosquet ont exactement les positions
	 * qu'ils ont sans relief.
	 */
	public function testAFlatReliefMovesNothing(): void {

		$result = $this->run('
			const rows = [
				row(1, "chemin", { width: 2, trace: [[-30, 10], [30, 10]] }),
				row(2, "route", { trace: [[-30, 20], [30, 20]] }),
				row(3, "cours-eau", { width: 2, trace: [[-20, -30], [-20, 40]] }),
				row(4, "haie", { trace: [[-30, 30], [30, 30]] }),
				row(5, "cloture", { model: "sheep", trace: [[-30, 35], [30, 35]] }),
				row(6, "bosquet", { coordinates: [50, 50] })
			];
			const flat = draw(rows, null);
			const draped = draw(rows, 0);
			await settled();
			emit({ flat: flat.elements.map(print), draped: draped.elements.map(print) });
		');

		$this->assertEquals(6, count($result['flat'] ?? []), 'les six éléments se montent');
		$this->assertEquals($result['flat'] ?? NULL, $result['draped'] ?? NULL, 'un relief plat ne bouge aucun sommet du décor');

	}

	/**
	 * Un chemin, une route, un cours d'eau, une haie, une clôture et un enclos à portail, sur 60 m d'ouest en est, montent avec le sol, et
	 * aucun de leurs sommets ne s'en écarte : le plus bas reste à moins de 3 cm sous le sol, le plus haut au-dessus de la bande garde
	 * sa hauteur à plat. Sans le drapé, tout le tracé resterait à la hauteur de son centre, à 3 m du sol à chaque bout.
	 */
	public function testTracesFollowTheGround(): void {

		$result = $this->run('
			const decor = draw([
				row(1, "chemin", { width: 2, trace: [[-30, 10], [30, 10]] }),
				row(2, "route", { trace: [[-30, 20], [30, 20]] }),
				row(3, "cours-eau", { width: 2, trace: [[-30, -10], [30, -10]] }),
				row(4, "haie", { trace: [[-30, 30], [30, 30]] }),
				row(5, "cloture", { model: "sheep", trace: [[-30, 35], [30, 35]] }),
				row(6, "enclos", { model: "sheep", zone: 1, gate: .1, trace: null })
			], .1, enclosed);
			const flat = draw([
				row(1, "chemin", { width: 2, trace: [[-30, 10], [30, 10]] }),
				row(2, "route", { trace: [[-30, 20], [30, 20]] }),
				row(3, "cours-eau", { width: 2, trace: [[-30, -10], [30, -10]] }),
				row(4, "haie", { trace: [[-30, 30], [30, 30]] }),
				row(5, "cloture", { model: "sheep", trace: [[-30, 35], [30, 35]] }),
				row(6, "enclos", { model: "sheep", zone: 1, gate: .1, trace: null })
			], null, enclosed);
			await settled();
			emit(Object.fromEntries(decor.elements.map((e, i) => [e.kit, { ...span(e, .1), flat: span(flat.elements[i], null) }])));
		');

		foreach(['chemin', 'route', 'cours-eau', 'haie', 'cloture', 'enclos'] as $kit) {
			$this->assertTrue(($result[$kit]['rise'] ?? 0) > 5.5, 'le kit '.$kit.' monte avec le sol sur ses 60 m, de 6 m au plus : '.($result[$kit]['rise'] ?? 'rien'));
		}

		foreach($result as $kit => $span) {
			$this->assertTrue($span['below'] > -.03, 'le kit '.$kit.' ne passe pas sous le sol de plus de 3 cm : '.$span['below']);
			$this->assertTrue(abs($span['above'] - $span['flat']['above']) < .05, 'le kit '.$kit.' garde au-dessus du sol la hauteur qu\'il a à plat : '.$span['above'].' contre '.$span['flat']['above']);
		}

	}

	/**
	 * Une haie se drape travée par travée, et chacun de ses arbres monte d'un seul tenant avec le sol sous son pied : aucun tronc ne
	 * s'incline, tous les sommets d'un arbre montent d'autant, quand ceux des travées suivent la pente.
	 */
	public function testHedgeTreesStayUpright(): void {

		$result = $this->run('
			const blocks = [];
			let spread = 0;
			const decor = draw([row(1, "haie", { trace: [[-30, 0], [30, 0]], seed: 11 }), row(2, "haie", { trace: [[-30, 5], [30, 5]], seed: 12 })], .1, geometry => {
				const conform = geometry.relief.conform;
				geometry.relief.conform = function(piece, origin, given = []) {
					const before = Float64Array.from({ length: piece.attributes.position.count }, (_, i) => piece.attributes.position.getY(i));
					conform.call(this, piece, origin, given);
					const lift = i => piece.attributes.position.getY(i) - before[i];
					given.forEach(({ first, last }) => {
						const lifts = Array.from({ length: last - first }, (_, k) => lift(first + k));
						blocks.push(Math.max(...lifts) - Math.min(...lifts));
					});
					spread = Math.max(spread, Math.max(...before.map((_, i) => lift(i))) - Math.min(...before.map((_, i) => lift(i))));
				};
			});
			emit({ blocks, spread, shown: decor.elements.length });
		');

		$this->assertEquals(2, $result['shown'] ?? NULL, 'les deux haies se montent');
		$this->assertTrue(count($result['blocks'] ?? []) > 0, 'les haies du cas plantent des arbres, drapés d\'un seul tenant');
		$this->assertEquals([0.0], array_values(array_unique(array_map(fn($spread) => round($spread, 6), $result['blocks'] ?? [1]))), 'chaque arbre de haie monte d\'un seul tenant');
		$this->assertTrue(($result['spread'] ?? 0) > .25, 'les travées de 3 m, elles, suivent la pente : '.($result['spread'] ?? 'rien'));

	}

	/**
	 * Un hangar et un bâtiment de la structure restent de niveau, posés au plus haut du sol sous leur emprise, sur un soubassement
	 * qui descend sous le sol tout du long. Déplacé, le hangar reprend un soubassement neuf à sa nouvelle place, et l'ancien est
	 * libéré.
	 */
	public function testBuildingsStandLevelOnAPlinth(): void {

		$result = $this->run('
			const decor = draw([row(1, "hangar", { coordinates: [0, 0] }), row(3, "batiment", { building: 5, coordinates: [-30, 0] })], .1);
			await settled();
			const measure = e => {
				const rectangle = MapSceneDecor.rectangle(e);
				const corners = [0, 1].flatMap(i => [0, 1].map(j => ScenePlanGeometry.add(rectangle.origin, ScenePlanGeometry.add(ScenePlanGeometry.mul(rectangle.u, rectangle.length * i), ScenePlanGeometry.mul(rectangle.w, rectangle.width * j)))));
				const plinth = e.plinth.geometry.attributes.position;
				const v = new THREE.Vector3();
				let top = -Infinity, sunk = Infinity;
				for(let i = 0; i < plinth.count; i++) {
					v.fromBufferAttribute(plinth, i).applyMatrix4(e.root.matrixWorld);
					top = Math.max(top, v.y);
					sunk = Math.min(sunk, v.y - .1 * v.x);
				}
				return { y: e.root.position.y, highest: Math.max(...corners.map(p => .1 * p.x)), tilt: Math.abs(e.root.rotation.x) + Math.abs(e.root.rotation.z), top, sunk, triangles: plinth.count / 3 };
			};
			const [hangar, batiment] = decor.elements;
			const before = measure(hangar);
			const old = hangar.plinth.geometry;
			let disposed = false;
			old.addEventListener("dispose", () => { disposed = true; });
			hangar.p = { x: 20, n: 40 };
			decor.settle(hangar);
			emit({ hangar: before, batiment: measure(batiment), moved: measure(hangar), disposed, replaced: hangar.plinth.geometry !== old });
		');

		foreach(['le hangar' => 'hangar', 'le bâtiment de la structure' => 'batiment', 'le hangar déplacé' => 'moved'] as $name => $key) {
			$measure = $result[$key] ?? [];
			$this->assertEquals(round($measure['highest'] ?? -1, 3), round($measure['y'] ?? -2, 3), $name.' se pose au plus haut du sol sous son emprise');
			$this->assertEquals(0.0, round($measure['tilt'] ?? 1, 6), $name.' reste de niveau');
			$this->assertEquals(round($measure['y'] ?? -1, 3), round($measure['top'] ?? -2, 3), 'le soubassement sous '.$name.' monte jusqu\'à son pied');
			$this->assertTrue(($measure['sunk'] ?? 0) <= -.29, 'le soubassement sous '.$name.' descend partout sous le sol : '.($measure['sunk'] ?? 'rien'));
		}

		$this->assertTrue($result['disposed'] ?? FALSE, "le soubassement de l'ancienne place est libéré");
		$this->assertTrue($result['replaced'] ?? FALSE, 'le hangar déplacé prend un soubassement neuf');

	}

	/**
	 * Une mare et une cuve descendent au plus bas du sol sous leur emprise, de niveau et sans soubassement, et le sol se creuse sous
	 * elles jusqu'à leur hauteur, le sol rendu compris. Le creux ne descend pas de plus que le dénivelé entre l'eau et le plus haut
	 * du terrain sous l'emprise, si bien que la rive amont ne surplombe l'eau que de la pente sur une case au plus. Un arbre posé
	 * avant elle dans le creux s'y repose. Poussée vers le haut de la pente, la mare se repose sur le sol sans son ancien creux ;
	 * déplacée, elle rend au sol de sa place sa hauteur et y repose l'arbre ; retirée, elle rend celle de la nouvelle, y repose
	 * l'arbre et y reprend les ponts. Le sol ne monte nulle part.
	 */
	public function testWaterSinksIntoTheGround(): void {

		$result = $this->run('
			const decor = draw([row(1, "arbre", { coordinates: [30.5, .5] }), row(2, "mare", { coordinates: [30, 0] }), row(3, "cuve", { coordinates: [-30, 0] }), row(4, "arbre", { coordinates: [60.5, .5] })], .1);
			await settled();
			const relief = decor.geometry.relief;
			const [tree, mare, tank, far] = decor.elements;
			let raised = 0;
			const rise = () => { raised = relief.heights.reduce((most, h, k) => Math.max(most, h - relief.raw[k]), raised); };
			const under = e => {
				const rectangle = MapSceneDecor.rectangle(e);
				const points = [];
				for(let i = 0; i <= 4; i++) {
					for(let j = 0; j <= 4; j++) {
						points.push(ScenePlanGeometry.add(rectangle.origin, ScenePlanGeometry.add(ScenePlanGeometry.mul(rectangle.u, rectangle.length * i / 4), ScenePlanGeometry.mul(rectangle.w, rectangle.width * j / 4))));
					}
				}
				const box = relief.cover(rectangle);
				let inside = 0, drawn = 0;
				for(let j = box.j0; j <= box.j1; j++) {
					for(let i = box.i0; i <= box.i1; i++) {
						const k = j * relief.xs.length + i;
						const p = { x: relief.xs[i], n: relief.ns[j] };
						const along = ScenePlanGeometry.dot(ScenePlanGeometry.sub(p, rectangle.origin), rectangle.u);
						const across = ScenePlanGeometry.dot(ScenePlanGeometry.sub(p, rectangle.origin), rectangle.w);
						if(along >= 0 && along <= rectangle.length && across >= 0 && across <= rectangle.width) {
							inside = Math.max(inside, Math.abs(relief.heights[k] - e.root.position.y));
						}
						drawn = Math.max(drawn, Math.abs(relief.surface.attributes.position.getY(k) - relief.heights[k]));
					}
				}
				return {
					y: e.root.position.y,
					lowest: Math.min(...points.map(p => .1 * p.x)),
					tilt: Math.abs(e.root.rotation.x) + Math.abs(e.root.rotation.z),
					plinth: e.plinth ?? null,
					above: Math.max(...points.map(p => relief.height(p.x, p.n) - e.root.position.y)),
					inside,
					drawn,
					...carving(relief)
				};
			};
			const sunk = { mare: under(mare), tank: under(tank), tree: tree.root.position.y - relief.height(tree.p.x, tree.p.n) };
			rise();
			mare.p = { x: 31.5, n: 0 };
			decor.settle(mare);
			const nudged = under(mare);
			rise();
			mare.p = { x: 60, n: 0 };
			decor.settle(mare);
			const moved = { mare: under(mare), left: relief.height(30, 0), tree: tree.root.position.y, far: far.root.position.y - relief.height(far.p.x, far.p.n) };
			rise();
			let bridges = 0;
			const queue = decor.queueBridges;
			decor.queueBridges = function() { bridges++; return queue.call(this); };
			decor.drop(mare);
			emit({ sunk, nudged, moved, raised, bridges, far: far.root.position.y, dropped: relief.height(60, 0), surface: relief.surface.attributes.position.getY(MapSceneRelief.cell(relief.ns, 0) * relief.xs.length + MapSceneRelief.cell(relief.xs, 60)) });
		');

		foreach(['la mare' => $result['sunk']['mare'] ?? [], 'la cuve' => $result['sunk']['tank'] ?? [], 'la mare poussée sur la pente' => $result['nudged'] ?? [], 'la mare déplacée' => $result['moved']['mare'] ?? []] as $name => $measure) {
			$this->assertEquals(round($measure['lowest'] ?? -1, 3), round($measure['y'] ?? -2, 3), $name.' se pose au plus bas du sol sous son emprise');
			$this->assertEquals(0.0, round($measure['tilt'] ?? 1, 6), $name.' reste de niveau');
			$this->assertTrue(array_key_exists('plinth', $measure) and $measure['plinth'] === NULL, $name.' n\'a pas de soubassement');
			$this->assertTrue(($measure['above'] ?? 1) <= .1 * 4 + .001, 'le sol ne passe au-dessus de '.$name.' que de la pente sur une case au plus : '.($measure['above'] ?? 'rien'));
			$this->assertTrue(($measure['gap'] ?? 1) < .001, 'chaque sommet autour de '.$name.' descend au niveau de l\'eau, borné par le dénivelé : '.($measure['gap'] ?? 'rien'));
			$this->assertTrue(($measure['deep'] ?? 1) < .001, 'le creux autour de '.$name.' ne descend pas de plus que le dénivelé sous son emprise : '.($measure['deep'] ?? 'rien'));
			$this->assertTrue(($measure['inside'] ?? 1) < .001, 'le sol sous '.$name.' se creuse à sa hauteur : '.($measure['inside'] ?? 'rien'));
			$this->assertTrue(($measure['drawn'] ?? 1) < .001, 'le sol rendu sous '.$name.' se creuse aussi');
		}

		$this->assertEquals(0.0, round($result['sunk']['tree'] ?? 1, 3), "l'arbre posé avant la mare se repose dans son creux");
		$this->assertEquals(3.0, round($result['moved']['left'] ?? 0, 3), 'la place que la mare a quittée reprend sa hauteur');
		$this->assertEquals(3.05, round($result['moved']['tree'] ?? 0, 3), "l'arbre de cette place s'y repose");
		$this->assertEquals(6.0, round($result['dropped'] ?? 0, 3), 'la mare retirée rend au sol sa hauteur');
		$this->assertEquals(6.0, round($result['surface'] ?? 0, 3), 'et au sol rendu');
		$this->assertEquals(0.0, round($result['moved']['far'] ?? 1, 3), "l'arbre de la nouvelle place se repose dans son creux");
		$this->assertEquals(6.05, round($result['far'] ?? 0, 3), 'et remonte quand la mare est retirée');
		$this->assertTrue(($result['bridges'] ?? 0) > 0, 'la mare retirée reprend la pose des ponts');
		$this->assertTrue(($result['raised'] ?? 1) <= 0, 'le creux ne remblaie jamais le sol : '.($result['raised'] ?? 'rien'));

	}

	/**
	 * Le sol change aussi dans les cases qui entourent les sommets creusés : un arbre posé à 1 m au-delà, de chaque côté, s'y
	 * repose quand la mare se pose et quand elle est retirée, comme le soubassement d'un hangar dont l'emprise mord ces cases sans que son centre y soit.
	 */
	public function testWaterResettlesTheCellsAround(): void {

		$result = $this->run('
			const runs = {};
			for(const slope of [.1, -.1]) {
				const decor = draw([row(1, "mare", { coordinates: [-120, 0] }), row(2, "arbre", { coordinates: [-60, 0] }), row(3, "hangar", { coordinates: [-90, 0] }), row(4, "arbre", { coordinates: [-60, 20] }), row(5, "arbre", { coordinates: [-60, 40] }), row(6, "arbre", { coordinates: [-60, 60] })], slope);
				await settled();
				const relief = decor.geometry.relief;
				const [mare, east, hangar, west, north, south] = [1, 2, 3, 4, 5, 6].map(id => decor.elements.find(e => e.id === id));
				const trees = { east, west, north, south };
				const gaps = () => Object.fromEntries(Object.entries(trees).map(([side, tree]) => [side, tree.root.position.y - relief.height(tree.p.x, tree.p.n)]));
				const place = { x: 30, n: 0 };
				const box = relief.cover(MapSceneDecor.rectangle({ ...mare, p: place }));
				const footing = () => {
					const plinth = hangar.plinth.geometry.attributes.position;
					const v = new THREE.Vector3();
					let shallowest = -Infinity;
					for(let i = 0; i < plinth.count; i++) {
						v.fromBufferAttribute(plinth, i).applyMatrix4(hangar.root.matrixWorld);
						if(v.y < hangar.root.position.y - .001) {
							shallowest = Math.max(shallowest, v.y - relief.height(v.x, -v.z));
						}
					}
					return shallowest;
				};
				east.p = { x: relief.xs[box.i1] + 1, n: place.n };
				west.p = { x: relief.xs[box.i0] - 1, n: place.n };
				north.p = { x: place.x, n: relief.ns[box.j1] + 1 };
				south.p = { x: place.x, n: relief.ns[box.j0] - 1 };
				Object.values(trees).forEach(tree => decor.settle(tree));
				const extent = MapSceneDecor.extent(MapSceneDecor.corners(MapSceneDecor.rectangle(hangar)));
				hangar.p = { x: place.x, n: relief.ns[box.j1 + 1] + (extent.n1 - extent.n0) / 2 - 1 };
				decor.settle(hangar);
				mare.p = place;
				decor.settle(mare);
				hangar.root.updateMatrixWorld(true);
				const sunk = { trees: gaps(), footing: footing(), moved: Object.fromEntries(Object.entries(trees).map(([side, tree]) => [side, tree.root.position.y - relief.height(tree.p.x, tree.p.n, relief.raw)])) };
				decor.drop(mare);
				hangar.root.updateMatrixWorld(true);
				runs[slope > 0 ? "rising" : "falling"] = { sunk, dropped: { trees: gaps(), footing: footing() } };
			}
			emit(runs);
		');

		// L'amont d'une pente est la seule rive que le creux abaisse : la pente montante éprouve le côté est, la descendante le côté ouest
		foreach(['est' => ['rising', 'east'], 'ouest' => ['falling', 'west'], 'nord' => ['rising', 'north'], 'sud' => ['rising', 'south']] as $side => [$run, $key]) {
			$this->assertTrue(($result[$run]['sunk']['moved'][$key] ?? 0) < 0, "le sol sous l'arbre du côté ".$side.' descend avec le creux : '.($result[$run]['sunk']['moved'][$key] ?? 'rien'));
		}

		foreach(['sur la pente montante' => 'rising', 'sur la pente descendante' => 'falling'] as $slope => $run) {
			foreach(['est' => 'east', 'ouest' => 'west', 'nord' => 'north', 'sud' => 'south'] as $side => $key) {
				$this->assertEquals(0.0, round($result[$run]['sunk']['trees'][$key] ?? 1, 3), "l'arbre du côté ".$side.' se repose quand la mare se pose, '.$slope);
				$this->assertEquals(0.0, round($result[$run]['dropped']['trees'][$key] ?? 1, 3), "l'arbre du côté ".$side.' se repose quand elle est retirée, '.$slope);
			}
			$this->assertTrue(($result[$run]['sunk']['footing'] ?? 0) <= -.29, 'le pied du soubassement du hangar voisin descend partout sous le sol creusé, '.$slope.' : '.($result[$run]['sunk']['footing'] ?? 'rien'));
			$this->assertTrue(($result[$run]['dropped']['footing'] ?? 0) <= -.29, 'et sous le sol rendu, '.$slope.' : '.($result[$run]['dropped']['footing'] ?? 'rien'));
		}

	}

	/**
	 * Trois mares en file sur la pente, dont les creux se touchent de proche en proche sans que les deux du bout se touchent,
	 * prennent toutes le niveau de la plus basse, dans tous les ordres de pose, et le sol se creuse sous chacune à ce niveau ; une
	 * mare éloignée garde le sien. Celle du milieu retirée, la mare du bout amont remonte au plus bas du terrain sous elle, et le
	 * sol sous elle avec. Deux mares dont un sommet creusé de l'une est à une case au plus d'un sommet creusé de l'autre partagent
	 * leur niveau, et plus à l'écart, chacune garde le sien. Il en va de même le long des deux axes et des deux diagonales, la plus
	 * haute de chaque côté, pour deux mares d'angles différents, et pour deux mares tournées dont les boites se touchent par un coin
	 * sans que leurs sommets creusés se touchent. Tournée, une mare ne creuse le sol qu'à moins d'une case de son emprise.
	 */
	public function testTouchingWaterTakesTheLowestLevel(): void {

		$result = $this->run('
			const probe = draw([row(9, "mare", { coordinates: [0, 0] })], .1);
			// Les emprises de deux mares voisines s\'écartent d\'une case, celles des deux du bout de la largeur d\'une mare et de deux cases
			const spacing = probe.elements[0].width + 4;
			const runs = [];
			for(const order of [[1, 2, 3, 4], [3, 2, 1, 4], [2, 4, 3, 1]]) {
				const rows = { 1: row(1, "mare", { coordinates: [30, 0] }), 2: row(2, "mare", { coordinates: [30 + spacing, 0] }), 3: row(3, "mare", { coordinates: [30 + 2 * spacing, 0] }), 4: row(4, "mare", { coordinates: [-100, 0] }) };
				const decor = draw(order.map(id => rows[id]), .1);
				await settled();
				const relief = decor.geometry.relief;
				const lowest = e => relief.extreme(MapSceneDecor.rectangle(e), Math.min, Infinity, relief.raw);
				const [low, middle, high, far] = [1, 2, 3, 4].map(id => decor.elements.find(e => e.id === id));
				const ends = relief.carves.get(high).i0 - relief.carves.get(low).i1;
				const levels = [low, middle, high].map(e => e.root.position.y - lowest(low));
				const run = { ends, levels, far: far.root.position.y - lowest(far), climb: lowest(high) - lowest(low), raised: relief.heights.reduce((most, h, k) => Math.max(most, h - relief.raw[k]), -Infinity), ...carving(relief) };
				decor.drop(middle);
				const after = carving(relief);
				run.dropped = { high: high.root.position.y - lowest(high), low: low.root.position.y - lowest(low), gap: after.gap, deep: after.deep };
				runs.push(run);
			}
			// La mare amont s\'écarte par demi-mètre jusqu\'à ce que sa boite de cases tienne à `cells` cases de celle de l\'autre
			const pair = draw([row(1, "mare", { coordinates: [30, 0] }), row(2, "mare", { coordinates: [30 + spacing, 0] })], .1);
			await settled();
			const [downhill, uphill] = pair.elements;
			const carved = pair.geometry.relief.carves;
			const below = e => pair.geometry.relief.extreme(MapSceneDecor.rectangle(e), Math.min, Infinity, pair.geometry.relief.raw);
			const part = cells => {
				while(carved.get(uphill).i0 - carved.get(downhill).i1 < cells) {
					uphill.p = { x: uphill.p.x + .5, n: 0 };
					pair.settle(uphill);
				}
				return { shared: uphill.root.position.y - below(downhill), own: uphill.root.position.y - below(uphill) };
			};
			const neighbours = part(1);
			const parted = part(2);
			// La seconde mare s\'écarte de la première par demi-mètre de `d` vers (dx, dn), décalée de `offset` en x, sur une pente
			// `slope` qui monte vers l\'est : l\'écart de leurs sommets creusés se compte en cases, sur le plus long des deux axes. La
			// plus haute se prend au nord, au sud, à l\'ouest et sur les deux diagonales, l\'est revenant à `part()` : chaque côté du
			// voisinage d\'un sommet n\'est lu que depuis la plus haute des deux
			const sweep = (angles, offset, from, to, dx, dn, slope = .1) => {
				const steps = [];
				for(let d = from; d <= to; d += .5) {
					const decor = draw([row(1, "mare", { coordinates: [30, 0], angle: angles[0] }), row(2, "mare", { coordinates: [30 + offset + d * dx, d * dn], angle: angles[1] })], slope);
					const relief = decor.geometry.relief;
					const [a, b] = decor.elements;
					const carved = e => {
						const carve = relief.carves.get(e);
						const list = [];
						for(let j = carve.j0; j <= carve.j1; j++) {
							for(let i = carve.i0; i <= carve.i1; i++) {
								if(relief.near(carve.rectangle, i, j)) {
									list.push([i, j]);
								}
							}
						}
						return list;
					};
					const vertices = carved(b);
					const cells = Math.min(...carved(a).flatMap(([i, j]) => vertices.map(([k, l]) => Math.max(Math.abs(i - k), Math.abs(j - l)))));
					const [ca, cb] = [a, b].map(e => relief.carves.get(e));
					const boxes = (ca.i0 <= cb.i1 + 1 && cb.i0 <= ca.i1 + 1 && ca.j0 <= cb.j1 + 1 && cb.j0 <= ca.j1 + 1);
					const below = e => relief.extreme(MapSceneDecor.rectangle(e), Math.min, Infinity, relief.raw);
					const group = Math.min(below(a), below(b));
					steps.push({
						cells,
						boxes,
						shared: Math.max(...[a, b].map(e => Math.abs(e.root.position.y - group))),
						own: Math.max(...[a, b].map(e => Math.abs(e.root.position.y - below(e)))),
						climb: Math.abs(below(b) - below(a))
					});
				}
				return steps;
			};
			const diagonal = sweep([Math.PI / 4, Math.PI / 4], 0, 8, 22, 1, 1);
			const southeast = sweep([Math.PI / 4, Math.PI / 4], 0, 8, 22, 1, -1);
			const northwest = sweep([Math.PI / 4, Math.PI / 4], 0, 8, 22, -1, 1, -.1);
			const southwest = sweep([Math.PI / 4, Math.PI / 4], 0, 8, 22, -1, -1, -.1);
			const northward = sweep([0, 0], 3, 10, 24, 0, 1);
			const westward = sweep([0, 0], 0, 6, 22, -1, 0, -.1);
			const southward = sweep([0, 0], 3, 10, 24, 0, -1);
			const askew = sweep([0, Math.PI / 6], 0, 6, 22, 1, 0);
			const decor = draw([row(1, "mare", { coordinates: [30, 0] })], .1);
			await settled();
			const relief = decor.geometry.relief;
			const [mare] = decor.elements;
			const turned = [];
			for(const angle of [Math.PI / 6, Math.PI / 4]) {
				mare.angle = angle;
				decor.settle(mare);
				turned.push(carving(relief));
			}
			const rectangle = MapSceneDecor.rectangle(mare);
			let far = 0;
			relief.ns.forEach((n, j) => relief.xs.forEach((x, i) => {
				const k = j * relief.xs.length + i;
				if(relief.heights[k] !== relief.raw[k]) {
					const p = { x, n };
					const along = ScenePlanGeometry.dot(ScenePlanGeometry.sub(p, rectangle.origin), rectangle.u);
					const across = ScenePlanGeometry.dot(ScenePlanGeometry.sub(p, rectangle.origin), rectangle.w);
					far = Math.max(far, -along, along - rectangle.length, -across, across - rectangle.width);
				}
			}));
			emit({ runs, far, turned, neighbours, parted, diagonal, southeast, northwest, southwest, northward, westward, southward, askew });
		');

		foreach(['dans leur ordre' => 0, 'de l\'amont vers l\'aval' => 1, 'dans le désordre' => 2] as $order => $index) {

			$run = $result['runs'][$index] ?? [];

			$this->assertTrue(($run['ends'] ?? 0) > 1, 'les creux des deux mares du bout ne se touchent pas, '.$order);
			$this->assertTrue(($run['climb'] ?? 0) > .5, 'le terrain sous la mare amont est plus haut que sous la mare aval, '.$order);

			foreach(['la mare aval', 'la mare du milieu', 'la mare amont'] as $position => $name) {
				$this->assertEquals(0.0, round($run['levels'][$position] ?? 1, 3), $name.' prend le niveau de la plus basse, '.$order);
			}

			$this->assertEquals(0.0, round($run['far'] ?? 1, 3), 'la mare éloignée garde le plus bas du terrain sous elle, '.$order);
			$this->assertTrue(($run['gap'] ?? 1) < .001, 'le sol se creuse sous chacune au niveau du groupe, '.$order.' : '.($run['gap'] ?? 'rien'));
			$this->assertTrue(($run['deep'] ?? 1) < .001, 'sans descendre de plus que le dénivelé sous chaque emprise, '.$order.' : '.($run['deep'] ?? 'rien'));
			$this->assertTrue(($run['raised'] ?? 1) <= 0, 'les creux ne remblaient pas le sol, '.$order);
			$this->assertEquals(0.0, round($run['dropped']['high'] ?? 1, 3), 'celle du milieu retirée, la mare amont remonte au plus bas du terrain sous elle, '.$order);
			$this->assertEquals(0.0, round($run['dropped']['low'] ?? 1, 3), 'et la mare aval garde le sien, '.$order);
			$this->assertTrue(($run['dropped']['gap'] ?? 1) < .001, 'et le sol sous elles suit, '.$order.' : '.($run['dropped']['gap'] ?? 'rien'));

		}

		$this->assertEquals(0.0, round($result['neighbours']['shared'] ?? 1, 3), 'deux mares à une case l\'une de l\'autre partagent le niveau de la plus basse');
		$this->assertTrue(($result['neighbours']['own'] ?? 0) < -.1, 'la plus haute descend sous le terrain qui la porte');
		$this->assertEquals(0.0, round($result['parted']['own'] ?? 1, 3), 'plus à l\'écart, la mare amont garde le plus bas du terrain sous elle');

		foreach(['tournées vers le nord-est' => 'diagonal', 'tournées vers le sud-est' => 'southeast', 'tournées vers le nord-ouest' => 'northwest', 'tournées vers le sud-ouest' => 'southwest', 'vers le nord' => 'northward', 'vers l\'ouest' => 'westward', 'vers le sud' => 'southward', 'd\'angles différents' => 'askew'] as $label => $case) {

			$steps = $result[$case] ?? [];

			$this->assertTrue(count(array_filter($steps, fn($step) => $step['cells'] <= 1 and $step['climb'] > .1)) > 0, 'des mares '.$label.' dont les sommets creusés se touchent, sur deux terrains de hauteurs différentes');
			$this->assertTrue(count(array_filter($steps, fn($step) => $step['cells'] >= 2)) > 0, 'des mares '.$label.' dont les sommets creusés ne se touchent pas');

			foreach($steps as $step) {
				if($step['cells'] <= 1) {
					$this->assertEquals(0.0, round($step['shared'], 3), 'deux mares '.$label.' à '.$step['cells'].' case partagent le niveau de la plus basse');
				} else {
					$this->assertEquals(0.0, round($step['own'], 3), 'deux mares '.$label.' à '.$step['cells'].' cases gardent chacune le leur');
				}
			}

		}

		$this->assertTrue(count(array_filter($result['diagonal'] ?? [], fn($step) => $step['cells'] >= 2 and $step['boxes'])) > 0, 'des mares tournées dont les boites se touchent par un coin, sans que leurs sommets creusés se touchent');

		foreach(['de 30°' => 0, 'de 45°' => 1] as $angle => $index) {
			$this->assertTrue(($result['turned'][$index]['gap'] ?? 1) < .001, 'le sol se creuse autour d\'une mare tournée '.$angle.' jusqu\'à une case de son emprise : '.($result['turned'][$index]['gap'] ?? 'rien'));
		}

		$this->assertTrue(($result['far'] ?? 99) <= 4 * sqrt(2) + .001, 'une mare tournée ne creuse qu\'à moins d\'une case de son emprise : '.($result['far'] ?? 'rien'));

	}

	/**
	 * Un tuyau posé au sol, une rampe et un tuyau enterré suivent le sol sur 60 m : coupés tous les 4 m, aucun ne s'enfonce, et le
	 * tuyau au sol garde son écart à plat.
	 */
	public function testPipesFollowTheGround(): void {

		$result = $this->run('
			const rows = [
				row(1, "tuyau", { layer: 12, diameter: 50, laying: "surface", trace: [[-30, -10], [30, -10]] }),
				row(2, "rampe", { layer: 12, trace: [[-30, -14], [20, -14]] }),
				row(3, "tuyau", { layer: 12, diameter: 50, laying: "buried", trace: [[-30, -18], [30, -18]] })
			];
			const decor = draw(rows, .1);
			const flat = draw(rows, null);
			await settled();
			emit(decor.elements.map((e, i) => ({ ...span(e, .1), flat: span(flat.elements[i], null) })));
		');

		$this->assertEquals(3, count($result), 'les trois tracés du réseau se montent');

		foreach($result as $i => $span) {
			$this->assertTrue($span['rise'] > 4.9, 'le tracé '.$i.' du réseau monte avec le sol : '.$span['rise']);
			$this->assertTrue($span['below'] - $span['flat']['below'] > -.01, 'le tracé '.$i.' du réseau ne s\'enfonce pas plus qu\'à plat : '.$span['below'].' contre '.$span['flat']['below']);
		}

	}

	/**
	 * Un chemin qui monte vers l'est passe un cours d'eau sur un pont, posé à la plus haute de ses deux berges ; le marquage d'une
	 * route reste à sa hauteur au-dessus du sol tout du long.
	 */
	public function testBridgeAndRoadMarkFollowTheGround(): void {

		$result = $this->run('
			const decor = draw([row(1, "chemin", { width: 1, trace: [[-20, 0], [20, 0]] }), row(2, "cours-eau", { width: 2, trace: [[0, -20], [0, 20]] }), row(3, "route", { trace: [[-30, 30], [30, 30]] })], .1);
			await settled();
			const crossings = decor.bridge();
			const [crossing] = crossings;
			const chord = (crossing.water + MapSceneDecor.BRIDGE_BANK) / crossing.sine;
			const banks = [-1, 1].map(side => .1 * (crossing.point.x + crossing.axis.x * side * chord / 2));
			const base = new THREE.Vector3().setFromMatrixPosition(crossing.place).y;
			const stripes = decor.stripes.geometry.attributes.position;
			const v = new THREE.Vector3();
			let low = Infinity, high = -Infinity;
			for(let i = 0; i < stripes.count; i++) {
				v.fromBufferAttribute(stripes, i);
				const above = v.y - .1 * v.x;
				low = Math.min(low, above);
				high = Math.max(high, above);
			}
			emit({ count: crossings.length, base, banks, low, high, mark: MapSceneDecor.ROAD_MARK });
		');

		$this->assertEquals(1, $result['count'] ?? NULL, 'le chemin pose un pont sur le cours d\'eau');
		$this->assertEquals(round(max($result['banks'] ?? [0]), 3), round($result['base'] ?? -1, 3), 'le pont se pose à la plus haute de ses deux berges');
		$this->assertTrue(abs(($result['low'] ?? 0) - ($result['mark'] ?? 1)) < .01, 'le marquage de la route garde sa hauteur au-dessus du sol au plus bas : '.($result['low'] ?? 'rien'));
		$this->assertTrue(abs(($result['high'] ?? 0) - ($result['mark'] ?? 1)) < .01, 'et au plus haut : '.($result['high'] ?? 'rien'));

	}

	/**
	 * Lance un scénario sous node et rend ce qu'il émet. Le scénario dispose de :
	 * - `row(id, kit, values)`, une ligne de décor telle que la scène la reçoit, coordonnées en mètres ;
	 * - `geometry(slope)`, la géométrie du plan sur un sol à `slope` vers l'est (null : sans relief) ;
	 * - `draw(rows, slope)`, le décor monté sur ce sol, et `settled()`, qui attend ses microtâches ;
	 * - `span(e)`, la montée du premier maillage d'un élément de l'ouest à l'est (`rise`), et son plus bas et son plus haut sommet
	 *   au-dessus du sol (`below`, `above`) ;
	 * - `print(e)`, l'empreinte des positions de tous les maillages d'un élément.
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
				const { MapSceneDecor, MapSceneRelief, ScenePlanGeometry } = vm.runInContext(source + "\n;({ MapSceneDecor, MapSceneRelief, ScenePlanGeometry })", context);

				// Le sol rendu, une grille de 4 m sur 2 km : ce que `height()`, `highest()` et `plinth()` lisent
				const geometry = slope => {
					let relief = null;
					if(slope !== null) {
						relief = Object.create(MapSceneRelief.prototype);
						relief.xs = Array.from({ length: 501 }, (_, i) => -1000 + 4 * i);
						relief.ns = relief.xs.slice();
						relief.heights = new Float32Array(501 * 501);
						relief.ns.forEach((n, j) => relief.xs.forEach((x, i) => { relief.heights[j * 501 + i] = slope * x; }));
						relief.raw = relief.heights.slice();
						relief.carves = new Map();
						relief.base = 0;
						// Le sol rendu, sommet pour sommet, comme le pose `ground()`
						relief.surface = new THREE.BufferGeometry();
						relief.surface.setAttribute("position", new THREE.BufferAttribute(Float32Array.from({ length: 501 * 501 * 3 }, (_, k) => (k % 3 === 1) ? relief.heights[(k - 1) / 3] : 0), 3));
						relief.surface.setAttribute("reliefAltitude", new THREE.BufferAttribute(relief.heights.slice(), 1));
					}
					return { beds: [], plots: new Map(), zones: new Map(), project: ([x, n]) => ({ x, n }), relief, height: (x, n) => (relief === null) ? 0 : relief.height(x, n) };
				};

				const row = (id, kit, values) => ({ id, kit, seed: id, angle: 0, size: 1, building: null, layer: null, diameter: null, laying: null, width: null, model: null, hedge: false, gate: null, zone: null, plot: null, coordinates: [0, 0], trace: null, ...values });
				const draw = (rows, slope, prepare = () => {}) => {
					const plan = geometry(slope);
					prepare(plan);
					// Un bâtiment de la structure se compose en un bloc de 8 m sur 6
					return new MapSceneDecor({
						socle: { THREE, addons, figure, baked, building: { build: () => ({ parts: [{ geometry: new THREE.BoxGeometry(8, 4, 6).translate(0, 2, 0), color: "#b0a08a" }] }) } },
						plan: { center: [0, 0], terrain: { grass: "meadow", soil: "brown" }, zones: [{ id: 1, name: "Pré" }], buildings: [{ id: 5, name: "Atelier", upload: null }], decor: rows, layers: [{ id: 12, name: "Irrigation", type: "irrigation" }] },
						geometry: plan,
						parent: new THREE.Group(),
						gradientMap: null,
						diameterLabel: null,
						changed: () => {},
						walked: () => {}
					});
				};
				// Le jardin que clôt un enclos, 60 m en long sur la pente
				const enclosed = plan => plan.zones.set(1, [{ x: -30, n: 40 }, { x: 30, n: 40 }, { x: 30, n: 52 }, { x: -30, n: 52 }]);
				// Les ponts et le marquage se refont dans une microtâche du contexte de la scène : un tour de la boucle les laisse passer
				const settled = () => new Promise(resolve => setTimeout(resolve, 0));

				const span = (e, slope) => {
					e.root.updateMatrixWorld(true);
					const mesh = e.root.children[0];
					const position = mesh.geometry.attributes.position;
					const v = new THREE.Vector3();
					const points = [];
					let below = Infinity, above = -Infinity;
					for(let i = 0; i < position.count; i++) {
						v.fromBufferAttribute(position, i).applyMatrix4(mesh.matrixWorld);
						const height = (slope ?? 0) * v.x;
						below = Math.min(below, v.y - height);
						above = Math.max(above, v.y - height);
						points.push([v.x, v.y]);
					}
					// Le plus bas sommet à moins de 2 m de chaque bout : le pied du tracé, quelle que soit la forme posée au-dessus
					const west = Math.min(...points.map(([x]) => x)), east = Math.max(...points.map(([x]) => x));
					const foot = end => Math.min(...points.filter(([x]) => Math.abs(x - end) < 2).map(([, y]) => y));
					return { rise: foot(east) - foot(west), below, above };
				};

				// Le creux attendu, recompté ici sur la grille de 4 m : un sommet des cases sous une étendue, sous elle ou à moins d\'une
				// diagonale de case, descend au niveau de son groupe sans descendre de plus que le dénivelé entre l\'eau et le plus haut du
				// terrain sous elle. `gap` est le plus grand écart au sol creusé, `deep` le plus grand dépassement de ce dénivelé, le plus
				// large des étendues qui touchent le sommet
				const carving = relief => {
					const margin = Math.hypot(4, 4);
					const carves = [...relief.carves.values()].map(carve => ({ ...carve, highest: relief.extreme(carve.rectangle, Math.max, -Infinity, relief.raw) }));
					let gap = 0, deep = -Infinity;
					relief.ns.forEach((n, j) => relief.xs.forEach((x, i) => {
						const k = j * relief.xs.length + i;
						let expected = relief.raw[k];
						let bound = -Infinity;
						for(const carve of carves) {
							const r = carve.rectangle;
							const along = ScenePlanGeometry.dot(ScenePlanGeometry.sub({ x, n }, r.origin), r.u);
							const across = ScenePlanGeometry.dot(ScenePlanGeometry.sub({ x, n }, r.origin), r.w);
							if(i >= carve.i0 && i <= carve.i1 && j >= carve.j0 && j <= carve.j1 && along >= -margin && along <= r.length + margin && across >= -margin && across <= r.width + margin) {
								expected = Math.min(expected, Math.max(carve.top, relief.raw[k] - (carve.highest - carve.top)));
								bound = Math.max(bound, carve.highest - carve.top);
							}
						}
						if(bound > -Infinity) {
							deep = Math.max(deep, relief.raw[k] - relief.heights[k] - bound);
						}
						gap = Math.max(gap, Math.abs(relief.heights[k] - expected), Math.abs(relief.surface.attributes.position.getY(k) - relief.heights[k]));
					}));
					return { gap, deep };
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
