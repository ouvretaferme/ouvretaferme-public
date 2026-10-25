<?php
namespace map;

/**
 * La portée de pose du décor dans la scène, éprouvée sous node telle que le navigateur charge ses fichiers : un élément se
 * pose jusqu'à 100 m de l'enveloppe des zones (`base/scene/static/js/plan.js`, `ScenePlanLand`, et `static/js/sceneDecor.js`,
 * `MapSceneDecor.islandOf()` et `landRefusal()`), le zoom ne recule jusque-là que pendant la pose (`MapScene.zoomFloor()`), et le
 * décor par défaut garde l'emprise qui écarte deux ilots, à 20 m d'elles (`static/js/sceneDecorDefault.js`).
 * Seul le libellé du refus entre deux ilots se lit hors de node, dans `SceneUi::getDecorRefusals()`.
 *
 * Aucune écriture, aucune base ouverte : les zones sont écrites dans le scénario, en mètres.
 *
 * Lancement : dev/test package=map file=DecorReachAi
 */
class DecorReachAiTest extends \Test {

	public function testDecorIsLaidUpToAHundredMetresAway(): void {

		$result = $this->run('
			const land = new ScenePlanLand([{ id: 1, points: square(0, 0, 50) }]);

			emit({
				reach: ScenePlanLand.REACH,
				margin: ScenePlanLand.MARGIN,
				inside: islandOf(land, { x: 0, n: 0 }) !== null,
				near: islandOf(land, { x: 110, n: 0 }) !== null,
				far: islandOf(land, { x: 170, n: 0 }) !== null,
				plateauNear: ScenePlanGeometry.inside({ x: 110, n: 0 }, land.islands[0].plateau),
				corner: islandOf(land, { x: 115, n: 115 }) !== null,
				cornerFar: islandOf(land, { x: 125, n: 125 }) !== null
			});
		');

		$this->assertEquals(100, $result['reach'] ?? NULL, "la portée de pose est de 100 m");
		$this->assertEquals(20, $result['margin'] ?? NULL, "l'emprise d'un ilot déborde toujours de 20 m de ses zones");
		$this->assertTrue($result['inside'] ?? FALSE, "un point d'une zone est sur son ilot");
		$this->assertTrue($result['near'] ?? FALSE, "à 60 m de l'enveloppe, la pose est admise : la portée n'est pas revenue à MARGIN");
		$this->assertFalse($result['far'] ?? TRUE, "à 120 m de l'enveloppe, la pose est refusée");
		$this->assertFalse($result['plateauNear'] ?? TRUE, "à 60 m de l'enveloppe, le point est hors de l'emprise qui sépare les ilots");
		$this->assertTrue($result['corner'] ?? FALSE, "à 92 m d'un coin, en diagonale, la pose est admise");
		$this->assertFalse($result['cornerFar'] ?? TRUE, "à 106 m d'un coin, en diagonale, la pose est refusée : la portée s'arrondit autour des coins");

	}

	public function testAPointBelongsToTheNearestIsland(): void {

		// Deux zones à 150 m l'une de l'autre, au-delà de JOIN : deux ilots, que la scène rapproche et dont les portées se recouvrent
		$result = $this->run('
			const land = new ScenePlanLand([{ id: 1, points: square(0, 0, 50) }, { id: 2, points: square(250, 0, 50) }]);
			const [west, east] = [1, 2].map(id => land.islands.find(island => island.zones[0].id === id));
			const westEdge = Math.max(...land.moved(west).map(q => q.x));
			const eastEdge = Math.min(...land.moved(east).map(q => q.x));

			emit({
				islands: land.islands.length,
				between: eastEdge - westEdge,
				nearWest: islandOf(land, { x: westEdge + 20, n: 0 }) === west,
				nearEast: islandOf(land, { x: eastEdge - 20, n: 0 }) === east,
				inBothReaches: ScenePlanGeometry.inside({ x: eastEdge - 20, n: 0 }, west.reach)
			});
		');

		$this->assertEquals(2, $result['islands'] ?? NULL, "deux zones à 150 m font deux ilots");
		$this->assertTrue(($result['between'] ?? 0) > 40 and ($result['between'] ?? 0) < 100, "les deux ilots sont rapprochés à moins de 100 m : leurs portées se recouvrent");
		$this->assertTrue($result['nearWest'] ?? FALSE, "à 20 m de l'ilot de l'ouest, le point est à lui");
		$this->assertTrue($result['inBothReaches'] ?? FALSE, "à 20 m de l'ilot de l'est, le point est aussi dans la portée de celui de l'ouest");
		$this->assertTrue($result['nearEast'] ?? FALSE, "un point dans la portée de deux ilots est au plus proche");

	}

	public function testAPointIsLaidWhereItIsReadAgain(): void {

		// Deux zones qui se touchent font un ilot en L ; une troisième, dans son creux et au-delà de JOIN, en fait un second, dont
		// l'enveloppe est dans celle du premier. Le point est dans l'enveloppe du L, mais sa zone la plus proche est la troisième
		$result = $this->run('
			const rectangle = (x, n, halfX, halfN) => [{ x: x - halfX, n: n - halfN }, { x: x + halfX, n: n - halfN }, { x: x + halfX, n: n + halfN }, { x: x - halfX, n: n + halfN }];
			const land = new ScenePlanLand([{ id: 1, points: rectangle(0, 0, 150, 10) }, { id: 2, points: rectangle(-140, 150, 10, 140) }, { id: 3, points: square(90, 200, 15) }]);
			const [large, small] = [1, 3].map(id => land.islands.find(island => island.zones.some(zone => zone.id === id)));
			const p = { x: -10, n: 160 };
			const found = islandOf(land, p);

			emit({
				islands: land.islands.length,
				inLargeHull: land.away(p, large) === 0,
				inBothReaches: ScenePlanGeometry.inside(p, large.reach) && ScenePlanGeometry.inside(p, small.reach),
				found: (found === small) ? "small" : ((found === large) ? "large" : null),
				reread: found !== null && land.at(ScenePlanGeometry.sub(p, found.offset)) === found
			});
		');

		$this->assertEquals(2, $result['islands'] ?? NULL, "le L et la zone de son creux font deux ilots");
		$this->assertTrue($result['inLargeHull'] ?? FALSE, "le point est dans l'enveloppe du L, à 0 m d'elle");
		$this->assertTrue($result['inBothReaches'] ?? FALSE, "le point est dans la portée des deux ilots");
		$this->assertEquals('small', $result['found'] ?? NULL, "le point va à l'ilot qui le retrouve, pas au plus proche");
		$this->assertTrue($result['reread'] ?? FALSE, "le point se relit sur l'ilot où il a été posé");

	}

	public function testAnObjectStraddlesTwoIslands(): void {

		// Deux ilots rapprochés : une emprise de 30 m posée au milieu de l'écart a ses coins de part et d'autre
		$result = $this->run('
			const land = new ScenePlanLand([{ id: 1, points: square(0, 0, 50) }, { id: 2, points: square(250, 0, 50) }]);
			const [west, east] = [1, 2].map(id => land.islands.find(island => island.zones[0].id === id));
			const westEdge = Math.max(...land.moved(west).map(q => q.x));
			const eastEdge = Math.min(...land.moved(east).map(q => q.x));
			const decor = { geometry: { land }, islandOf: MapSceneDecor.prototype.islandOf };

			const footprint = (x, half) => ({ pieces: [{ rectangle: { origin: { x: x - half, n: -5 }, w: { x: 1, n: 0 }, u: { x: 0, n: 1 }, width: half * 2, length: 10 } }] });
			const refused = (traced, x, half) => MapSceneDecor.prototype.landRefusal.call(decor, { def: { traced }, p: { x, n: 0 } }, footprint(x, half));

			const middle = (westEdge + eastEdge) / 2;
			const corners = MapSceneDecor.corners(footprint(middle, 15).pieces[0].rectangle).map(p => decor.islandOf(p));
			const anchor = decor.islandOf({ x: middle + 1, n: 0 });

			emit({
				straddling: new Set(corners).size,
				object: refused(false, middle + 1, 15) === null,
				anchoredEast: anchor === east,
				reread: land.at(ScenePlanGeometry.sub({ x: middle + 1, n: 0 }, anchor.offset)) === anchor,
				trace: refused(true, middle + 1, 15),
				traceOnOne: refused(true, westEdge + 10, 5) === null,
				traceFar: refused(true, westEdge - 300, 15),
				objectFar: refused(false, westEdge - 300, 15),
				cornerFar: refused(false, westEdge - 190, 15),
				anchorIn: decor.islandOf({ x: westEdge - 190, n: 0 }) !== null,
				anchorFar: MapSceneDecor.prototype.landRefusal.call(decor, { def: { traced: false }, p: { x: westEdge - 300, n: 0 } }, footprint(westEdge + 10, 5))
			});
		');

		$this->assertEquals(2, $result['straddling'] ?? NULL, "les coins de l'emprise sont sur deux ilots");
		$this->assertTrue($result['object'] ?? FALSE, "un objet à cheval sur deux ilots se pose");
		$this->assertTrue($result['anchoredEast'] ?? FALSE, "il se rattache à l'ilot le plus proche de son point d'ancrage");
		$this->assertTrue($result['reread'] ?? FALSE, "son point d'ancrage se relit sur cet ilot");
		$this->assertEquals('apart', $result['trace'] ?? NULL, "un tracé à cheval sur deux ilots est refusé par sa propre raison : chacun de ses points s'enregistre sur le sien");
		$this->assertTrue($result['traceOnOne'] ?? FALSE, "un tracé sur un seul ilot se pose");
		$this->assertEquals('outside', $result['traceFar'] ?? NULL, "un tracé hors de toute portée est trop loin, pas entre deux ilots");
		$this->assertEquals('outside', $result['objectFar'] ?? NULL, "un objet hors de toute portée reste refusé");
		$this->assertTrue($result['anchorIn'] ?? FALSE, "à 90 m de l'enveloppe, le point d'ancrage est à portée");
		$this->assertEquals('outside', $result['cornerFar'] ?? NULL, "un objet dont un coin sort de la portée est refusé, son ancrage y serait-il");
		$this->assertEquals('outside', $result['anchorFar'] ?? NULL, "un objet dont l'ancrage sort de la portée est refusé, ses coins y seraient-ils");

	}

	public function testTheFoldIsDrawnBetweenTwoIslands(): void {

		$result = $this->run('
			// `lineGeometry()` ne demande à three.js que de porter ses positions
			const THREE = { BufferGeometry: class { setAttribute(name, attribute) { this.positions = attribute.array; } }, Float32BufferAttribute: class { constructor(array) { this.array = array; } } };
			const cell = MapSceneDecor.CELL;

			const lines = zones => {

				const land = new ScenePlanLand(zones);
				const all = land.islands.flatMap(island => island.reach);
				const bounds = {
					x0: Math.floor(Math.min(...all.map(p => p.x)) / cell) * cell,
					x1: Math.ceil(Math.max(...all.map(p => p.x)) / cell) * cell,
					n0: Math.floor(Math.min(...all.map(p => p.n)) / cell) * cell,
					n1: Math.ceil(Math.max(...all.map(p => p.n)) / cell) * cell
				};
				const decor = { THREE, geometry: { land, relief: null }, bounds, islandOf: MapSceneDecor.prototype.islandOf, groundLine: MapSceneDecor.prototype.groundLine };
				const { limit, fold } = MapSceneDecor.prototype.limitLines.call(decor);

				// Les deux cases que sépare chaque segment du pli : à 1 m de part et d\'autre de son milieu
				const sides = [];

				for(let k = 0; k < fold.positions.length; k += 6) {
					const [ax, , az, bx, , bz] = fold.positions.slice(k, k + 6);
					const middle = { x: (ax + bx) / 2, n: -(az + bz) / 2 };
					const across = (ax === bx) ? { x: 1, n: 0 } : { x: 0, n: 1 };
					sides.push([decor.islandOf(ScenePlanGeometry.add(middle, across)), decor.islandOf(ScenePlanGeometry.sub(middle, across))]);
				}

				const upright = fold.positions.filter((value, k) => k % 6 === 0 && value === fold.positions[k + 3]).length;

				return { limit: limit.positions.length / 6, fold: sides.length, upright, level: sides.length - upright, apart: sides.every(([a, b]) => a !== null && b !== null && a !== b) };

			};

			emit({
				one: lines([{ id: 1, points: square(0, 0, 50) }]),
				two: lines([{ id: 1, points: square(0, 0, 50) }, { id: 2, points: square(250, 0, 50) }]),
				// Deux ilots décalés en diagonale : leur pli monte en escalier, par des segments des deux sens
				slanted: lines([{ id: 1, points: square(0, 0, 50) }, { id: 2, points: square(250, 250, 50) }])
			});
		');

		$this->assertTrue(($result['one']['limit'] ?? 0) > 0, "le terrain permis a son contour");
		$this->assertEquals(0, $result['one']['fold'] ?? NULL, "un seul ilot n'a pas de pli");
		$this->assertTrue(($result['two']['fold'] ?? 0) > 0, "deux ilots rapprochés ont un pli entre eux");
		$this->assertTrue($result['two']['apart'] ?? FALSE, "chaque segment du pli sépare deux cases de deux ilots");
		$this->assertTrue(($result['slanted']['upright'] ?? 0) > 0 and ($result['slanted']['level'] ?? 0) > 0, "un pli en diagonale a des segments dans les deux sens");
		$this->assertTrue($result['slanted']['apart'] ?? FALSE, "chacun sépare deux cases de deux ilots, dans un sens comme dans l'autre");

	}

	public function testTheDefaultDecorStaysByTheParcels(): void {

		// Le décor par défaut se tire à chaque chargement tant que la structure n'a rien posé : qu'il suive la portée de pose, et
		// les arbres d'une structure dont les places proches sont prises partiraient dans le pré
		$result = $this->run('
			const land = new ScenePlanLand([{ id: 1, points: square(0, 0, 50) }]);
			const drawn = {
				geometry: { land },
				decor: { geometry: { land }, islandOf: MapSceneDecor.prototype.islandOf },
				islandOf: MapSceneDecorDefault.prototype.islandOf,
				hides: () => false,
				plots: [], zones: [], beds: [], glass: [], boards: [], placed: []
			};
			const fits = x => MapSceneDecorDefault.prototype.fits.call(drawn, { origin: { x: x - 5, n: -5 }, w: { x: 1, n: 0 }, u: { x: 0, n: 1 }, width: 10, length: 10 }, 5, 4, null);

			emit({ close: fits(60), far: fits(110), posed: drawn.decor.islandOf({ x: 110, n: 0 }) !== null });
		');

		$this->assertTrue($result['close'] ?? FALSE, "à 5 m de l'enveloppe, le décor par défaut tient");
		$this->assertTrue($result['posed'] ?? FALSE, "à 60 m de l'enveloppe, l'utilisateur pose");
		$this->assertFalse($result['far'] ?? TRUE, "à 60 m de l'enveloppe, le décor par défaut ne se tire pas : il reste dans l'emprise de 20 m");

	}

	public function testTheReachWidensTheZoomOnlyWhilePosing(): void {

		$result = $this->run('
			const framed = [];
			const scene = {
				framings: [{ width: 100, height: 100 }],
				framingReach: { width: 400, height: 400 },
				camera: { left: -50, right: 50, top: 50, bottom: -50, zoom: 1 },
				controls: { maxZoom: 10, minZoom: 0 },
				decor: { active: false },
				folds: [],
				fit: MapScene.prototype.fit,
				zoomFloor: MapScene.prototype.zoomFloor,
				usable: () => 1,
				frameChosen: () => framed.push(scene.controls.minZoom)
			};
			const setPosing = () => MapScene.prototype.setPosing.call(scene, null);

			const outside = scene.zoomFloor(1, false);
			const posing = scene.zoomFloor(1, true);

			scene.decor.active = true;
			setPosing();
			const opened = scene.controls.minZoom;
			const framedOnOpening = framed.length;

			// Reculée jusqu\'au plancher de la pose, puis la pose se ferme
			scene.camera.zoom = posing;
			scene.decor.active = false;
			setPosing();
			const closed = scene.controls.minZoom;
			const framedOnClosing = framed.length;

			// Restée plus près que le plancher, la vue ne bouge pas à la sortie
			scene.camera.zoom = outside * 2;
			scene.decor.active = true;
			setPosing();
			scene.decor.active = false;
			setPosing();

			emit({ wider: posing < outside, opened: opened === posing, closed: closed === outside, framedOnOpening, framedOnClosing, framedAtFloor: framed[0] === outside, framedWhenNear: framed.length });
		');

		$this->assertTrue($result['wider'] ?? FALSE, "la portée de pose recule le plancher du zoom");
		$this->assertTrue($result['opened'] ?? FALSE, "en mode Décor, le plancher va jusqu'à la portée de pose");
		$this->assertTrue($result['closed'] ?? FALSE, "hors du mode Décor, le plancher est celui des cadrages de la scène");
		$this->assertEquals(0, $result['framedOnOpening'] ?? NULL, "ouvrir la pose ne recadre rien");
		$this->assertEquals(1, $result['framedOnClosing'] ?? NULL, "sortir de la pose depuis le recul élargi ramène au cadrage choisi");
		$this->assertTrue($result['framedAtFloor'] ?? FALSE, "le cadrage de sortie se fait sous le plancher remis");
		$this->assertEquals(1, $result['framedWhenNear'] ?? NULL, "sortir de la pose d'une vue plus proche que le plancher ne recadre pas");

	}

	public function testTheGroundFoldLeavesTheSceneWhilePosing(): void {

		$result = $this->run('
			const scene = {
				framings: [{ width: 100, height: 100 }],
				framingReach: { width: 400, height: 400 },
				camera: { left: -50, right: 50, top: 50, bottom: -50, zoom: 10 },
				controls: { maxZoom: 10, minZoom: 0 },
				decor: { active: false },
				folds: [{ visible: true }, { visible: true }],
				fit: MapScene.prototype.fit,
				zoomFloor: MapScene.prototype.zoomFloor,
				usable: () => 1,
				frameChosen: () => null
			};
			const setPosing = () => MapScene.prototype.setPosing.call(scene, null);
			const shown = () => scene.folds.map(band => band.visible);

			scene.decor.active = true;
			setPosing();
			const posing = shown();

			scene.decor.active = false;
			setPosing();

			emit({ posing, outside: shown() });
		');

		$this->assertEquals([FALSE, FALSE], $result['posing'] ?? NULL, "en mode Décor, il ne reste aucun trait au sol entre deux ilots");
		$this->assertEquals([TRUE, TRUE], $result['outside'] ?? NULL, "hors du mode Décor, chaque trait au sol est revenu");

	}

	public function testEachRefusalOfTheSceneHasItsOwnWords(): void {

		$refusals = new \ReflectionMethod(SceneUi::class, 'getDecorRefusals')->invoke(NULL);

		// Les raisons que la scène rend : celles des trois juges de la pose, et celles qu'elle nomme elle-même au mot qu'elle dit
		$source = file_get_contents(LIME_DIRECTORY.'/production/map/static/js/sceneDecor.js');
		$reasons = [];

		foreach(['refusal(e)', 'startRefusal(e, p)', 'landRefusal(e, footprint)'] as $method) {

			preg_match('/\n\t'.preg_quote($method, '/').' \{\n(.*?)\n\t\}\n/s', $source, $body);
			preg_match_all('/return [^;]*/', $body[1] ?? '', $returns);
			preg_match_all("/'([a-z]+)'/", implode("\n", $returns[0]), $named);

			$reasons = array_merge($reasons, $named[1]);

		}

		preg_match_all("/refusalText\(e, '([a-z]+)'\)/", $source, $told);

		$reasons = array_unique(array_merge($reasons, $told[1]));
		sort($reasons);

		$keys = array_keys($refusals);
		sort($keys);

		$this->assertEquals($reasons, $keys, "chaque raison que la scène rend a ses mots, et aucuns mots n'attendent une raison qu'elle ne rend pas");
		$this->assertEquals(count($refusals), count(array_unique($refusals)), "deux raisons ne se disent pas avec les mêmes mots");
		$this->assertTrue(str_contains($refusals['long'], '{value}'), "le refus d'un tracé trop long porte la longueur que la scène y pose");

	}

	public function testARefusedTraceTurnsRedAndSaysSo(): void {

		$result = $this->run('
			let verdict = "bed";

			const decor = { active: true, geometry: { height: () => 0, relief: null }, material: "decor", refusedMaterial: "refused", pads: { allowed: "allowed", refused: "padRefused" }, changed: () => {}, refusal: () => verdict, fitGround: MapSceneDecor.prototype.fitGround };
			const element = def => ({ def, p: { x: 0, n: 0 }, angle: 0, plain: null, pad: { material: null }, root: { children: [{ material: "decor" }], position: { set: () => {} }, rotation: {}, updateMatrixWorld: () => {} } });
			const trace = element({ traced: true });
			const object = element({});

			MapSceneDecor.prototype.settle.call(decor, trace);
			MapSceneDecor.prototype.settle.call(decor, object);
			const refused = [trace.root.children[0].material, object.root.children[0].material];

			verdict = null;
			MapSceneDecor.prototype.settle.call(decor, trace);
			const accepted = trace.root.children[0].material;

			// Le mot du tracé en cours, que `shapeTrace()` dit après que `build()` a jugé la ligne
			const say = { element: { textContent: "", classList: { toggle: () => {}, add: () => {} } }, position: { set: () => {} }, userData: {}, visible: false };
			const tracer = Object.assign(Object.create(MapSceneDecor.prototype), {
				say,
				geometry: { height: () => 0 },
				refusals: { bed: "bed" },
				sheet: { dataset: { length: "{value} m" } },
				tracing: { e: { id: 1, seed: 1, def: { traced: true }, p: { x: 0, n: 0 }, height: 0, bad: null }, points: [{ x: 0, n: 0 }], cursor: { x: 10, n: 0 } },
				build: e => { e.bad = verdict; },
				refresh: () => {},
				drop: () => {}
			});

			verdict = "bed";
			tracer.shapeTrace();
			const saidRefused = say.userData.refused;

			verdict = null;
			tracer.tracing.cursor = { x: 12, n: 0 };
			tracer.shapeTrace();

			// Seul le refus `long` porte une valeur, la longueur du kit, ou celle de toute ligne quand le kit n\'en fixe pas
			tracer.refusals = { long: "plus de {value} m" };
			const long = [tracer.refusalText({ def: { longest: 80 } }, "long"), tracer.refusalText({ def: {} }, "long")];

			emit({ refused, accepted, pad: trace.pad.material, saidRefused, saidAccepted: say.userData.refused, long, length: MapSceneDecor.TRACE_LENGTH });
		');

		$this->assertEquals(['refused', 'decor'], $result['refused'] ?? NULL, "un tracé refusé rougit lui-même, un objet refusé garde sa matière et rougit par son emprise");
		$this->assertEquals('decor', $result['accepted'] ?? NULL, "le refus tombé, le tracé reprend la matière du décor");
		$this->assertEquals('allowed', $result['pad'] ?? NULL, "son emprise revient à la couleur permise");
		$this->assertTrue($result['saidRefused'] ?? FALSE, "le mot d'un tracé refusé est un refus");
		$this->assertFalse($result['saidAccepted'] ?? TRUE, "la longueur d'un tracé permis n'en est pas un");
		$this->assertEquals(['plus de 80 m', 'plus de '.$result['length'].' m'], $result['long'] ?? NULL, "un tracé trop long dit la longueur que son kit ne dépasse pas");

	}

	/**
	 * Un tracé se pose jusqu'à 1 000 m, et jusqu'au nombre de points que le serveur enregistre (`MapSetting::DECOR_TRACE_POINTS`),
	 * que la feuille porte : un point par clic, calé au mètre, en ferait tenir 1 001 sur 1 000 m.
	 */
	public function testATraceIsLaidUpToAThousandMetres(): void {

		$result = $this->run('
			// La ligne seule est jugée ici : le terrain l\'admet, aucune planche ni aucun autre élément ne la gêne
			const tracer = Object.assign(Object.create(MapSceneDecor.prototype), { elements: [], removals: [], areas: [], tracePoints: '.MapSetting::DECOR_TRACE_POINTS.', traceLimit: '.$this->getTraceLimit().', layers: { typeOf: () => null }, landRefusal: () => null, onBed: () => false });
			const straight = (length, count) => Array.from({ length: count }, (_, i) => ({ x: length * i / (count - 1), n: 0 }));
			// Un tracé admis se dit `accepted`, qu\'un résultat absent ne puisse pas passer pour lui
			const refusal = (kit, line) => tracer.refusal({ ...MapSceneDecor.element(kit, { x: 0, n: 0 }), thick: .6, path: line, trace: line }) ?? "accepted";

			emit({
				length: MapSceneDecor.TRACE_LENGTH,
				fence: refusal("cloture", straight(1000, 2)),
				fenceLong: refusal("cloture", straight(1001, 2)),
				road: refusal("route", straight(1000, 2)),
				ramp: refusal("rampe", straight(61, 2)),
				pointsMax: refusal("cloture", straight(499, '.MapSetting::DECOR_TRACE_POINTS.')),
				pointsOver: refusal("cloture", straight(500, '.(MapSetting::DECOR_TRACE_POINTS + 1).'))
			});
		');

		$this->assertEquals(1000, $result['length'] ?? NULL, "un tracé se pose jusqu'à 1 000 m");
		$this->assertEquals('accepted', $result['fence'] ?? NULL, "une clôture de 1 000 m est admise");
		$this->assertEquals('long', $result['fenceLong'] ?? NULL, "une clôture de 1 001 m est refusée");
		$this->assertEquals('accepted', $result['road'] ?? NULL, "une route de 1 000 m est admise");
		$this->assertEquals('long', $result['ramp'] ?? NULL, "une rampe garde la longueur de son kit, 60 m");
		$this->assertEquals('accepted', $result['pointsMax'] ?? NULL, "un tracé de ".MapSetting::DECOR_TRACE_POINTS." points, un par mètre, est admis");
		$this->assertEquals('points', $result['pointsOver'] ?? NULL, "le point de plus que le serveur n'enregistre pas est refusé avant l'enregistrement");

	}

	/**
	 * Le plafond du décor, que le serveur juge à l'enregistrement (`DecorLib::getTraceCost()`), arrive sur la page et s'y juge au
	 * tracé : une bande fleurie pèse trois fois sa longueur, et la scène vide en admet `DECOR_TRACE_LENGTH / 3` mètres.
	 */
	public function testTheSceneRefusesWhatTheDecorLimitRefuses(): void {

		$result = $this->run('
			const garden = { kind: "zone", id: 1, name: "Jardin", points: square(0, 0, 50) };
			const tracer = Object.assign(Object.create(MapSceneDecor.prototype), { elements: [], removals: [], areas: [garden], tracePoints: '.MapSetting::DECOR_TRACE_POINTS.', traceLimit: '.$this->getTraceLimit().', layers: { typeOf: () => null }, landRefusal: () => null, onBed: () => false });
			const straight = length => [{ x: 0, n: 0 }, { x: length, n: 0 }];
			const element = (kit, length) => ({ ...MapSceneDecor.element(kit, { x: 0, n: 0 }), thick: .6, path: straight(length), trace: straight(length) });
			// Une ligne enregistrée pèse ce que le serveur en compte
			const saved = e => Object.assign(e, { weighed: tracer.cost(e) });
			const refusal = e => tracer.refusal(e) ?? "accepted";

			const result = { strip: refusal(element("bande-fleurie", 899)), stripLong: refusal(element("bande-fleurie", 901)) };

			// Une clôture enregistrée laisse au plafond ce qu\'elle ne prend pas, et ne se compte pas deux fois quand on la reprend
			const fence = saved(element("cloture", 800));
			tracer.elements = [fence];
			result.stripAfterFence = refusal(element("bande-fleurie", 899));
			result.fenceAgain = refusal(fence);

			// Retirée pendant la séance, elle pèse encore : son retrait ne part qu\'à « Terminer »
			tracer.elements = [];
			tracer.removals = [fence];
			result.stripAfterRemoval = refusal(element("bande-fleurie", 899));

			// Au-dessus du plafond, raccourcir reste permis et allonger non
			const over = saved(element("bande-fleurie", 950));
			tracer.removals = [];
			tracer.elements = [over];
			result.shorter = refusal(Object.assign(over, { trace: straight(920), path: straight(920) }));
			result.longer = refusal(Object.assign(over, { trace: straight(960), path: straight(960) }));

			// Un enclos pèse le contour de son emprise, 400 m ici, et non son tour, écarté du contour
			const enclosure = Object.assign(MapSceneDecor.element("enclos", null), { area: { kind: "zone", id: 1, name: "Jardin" }, ring: square(0, 0, 60), hedge: false });
			result.enclosure = tracer.cost(enclosure);

			emit(result);
		');

		$length = MapSetting::DECOR_TRACE_LENGTH;

		$this->assertEquals('accepted', $result['strip'] ?? NULL, "une bande fleurie de 899 m est admise sur une scène vide");
		$this->assertEquals('limit', $result['stripLong'] ?? NULL, "une bande fleurie de 901 m passe le plafond pondéré de ".$length." m, et la scène la refuse au tracé");
		$this->assertEquals('limit', $result['stripAfterFence'] ?? NULL, "après 800 m de clôture, la même bande fleurie passe le plafond");
		$this->assertEquals('accepted', $result['fenceAgain'] ?? NULL, "un élément enregistré ne se compte pas deux fois");
		$this->assertEquals('limit', $result['stripAfterRemoval'] ?? NULL, "une clôture retirée pendant la séance pèse jusqu'à « Terminer »");
		$this->assertEquals('accepted', $result['shorter'] ?? NULL, "au-dessus du plafond, raccourcir une bande fleurie reste permis");
		$this->assertEquals('limit', $result['longer'] ?? NULL, "au-dessus du plafond, l'allonger est refusé");
		$this->assertEquals(400.0, round($result['enclosure'] ?? 0, 6), "un enclos pèse le contour de son emprise");

		$refusals = new \ReflectionMethod(SceneUi::class, 'getDecorRefusals')->invoke(NULL);
		$this->assertEquals(AlertUi::getError('Decor::trace.limit'), $refusals['limit'] ?? NULL, "la scène dit le refus du plafond avec les mots du serveur");

	}

	/** La poignée du milieu d'un tracé qui compte déjà tous ses points n'en insère pas un de plus, qui partirait au relâcher. */
	public function testTheMiddleHandleRefusesThePointTooMany(): void {

		$result = $this->run('
			const told = [];
			const updated = [];
			const trace = Array.from({ length: '.MapSetting::DECOR_TRACE_POINTS.' }, (_, i) => ({ x: i, n: 0 }));
			const e = { ...MapSceneDecor.element("cloture", { x: 0, n: 0 }), trace };
			const tracer = Object.assign(Object.create(MapSceneDecor.prototype), {
				active: true, tracing: null, selected: e, notice: 0, elements: [e], removals: [], areas: [], tracePoints: '.MapSetting::DECOR_TRACE_POINTS.', traceLimit: '.$this->getTraceLimit().',
				layers: { typeOf: () => null }, landRefusal: () => null, onBed: () => false,
				refusals: { points: "plus de {value} points" },
				describe: () => ({}),
				tell: (element, text, refused) => told.push([text, refused]),
				// La ligne se juge comme `build()` la juge, sans la monter dans une scène
				reshape: (element, line) => {
					MapSceneDecor.prototype.lay.call(tracer, element, line);
					element.path = element.trace;
					element.bad = tracer.refusal(element);
				},
				update: () => updated.push("update")
			});

			tracer.bend({ button: 0, isPrimary: true, preventDefault: () => {}, clientX: 0, clientY: 0 }, { dataset: { handle: "middle", index: "10" } });

			emit({ told, updated, points: e.trace.length, bad: e.bad ?? "accepted" });
		');

		$this->assertEquals([['plus de '.MapSetting::DECOR_TRACE_POINTS.' points', TRUE]], $result['told'] ?? NULL, "la poignée du milieu dit le refus du point de trop");
		$this->assertEquals([], $result['updated'] ?? NULL, "rien ne part au serveur");
		$this->assertEquals(MapSetting::DECOR_TRACE_POINTS, $result['points'] ?? NULL, "le tracé revient à ses points");
		$this->assertEquals('accepted', $result['bad'] ?? NULL, "et à sa ligne admise");

	}

	public function testTheRefusedMaterialKeepsTheDecorDrawing(): void {

		$result = $this->run('
			const material = new three.MeshToonMaterial({ vertexColors: true });
			const onBeforeCompile = () => {};
			const customProgramCacheKey = () => "map-scene-decor";

			Object.assign(material, { onBeforeCompile, customProgramCacheKey });

			const tinted = MapSceneDecor.tinted(material, "#d0563c");

			// `dispose()` ne lit que ce qui existe : chaque autre champ de la pose répond à tout, sans rien faire
			const anything = () => new Proxy(function() {}, { get: () => anything(), apply: () => anything() });
			let freed = false;
			const decor = new Proxy({ refusedMaterial: { dispose: () => { freed = true; } }, noticeAway: null }, { get: (target, key) => (key in target) ? target[key] : anything() });

			MapSceneDecor.prototype.dispose.call(decor);

			emit({
				copy: tinted !== material,
				compile: tinted.onBeforeCompile === onBeforeCompile,
				key: tinted.customProgramCacheKey === customProgramCacheKey,
				colors: tinted.vertexColors,
				color: tinted.color.getHexString(),
				untouched: material.color.getHexString(),
				freed
			});
		');

		$this->assertTrue($result['copy'] ?? FALSE, "la matière refusée est une copie");
		$this->assertTrue($result['compile'] ?? FALSE, "elle garde le dessin du décor, que clone() ne recopie pas");
		$this->assertTrue($result['key'] ?? FALSE, "et la clé de son programme");
		$this->assertTrue($result['colors'] ?? FALSE, "et les couleurs de chaque sommet");
		$this->assertEquals('d0563c', $result['color'] ?? NULL, "elle prend la teinte du refus");
		$this->assertEquals('ffffff', $result['untouched'] ?? NULL, "la matière du décor ne change pas");
		$this->assertTrue($result['freed'] ?? FALSE, "elle est libérée avec la pose");

	}

	public function testTheRefusalLeavesTheSign(): void {

		$result = $this->run('
			const frame = { left: 0, right: 1000, top: 60, bottom: 600 };
			const size = { width: 120, height: 22 };
			const sign = [{ x: 400, y: 280 }, { x: 600, y: 280 }, { x: 400, y: 320 }, { x: 600, y: 320 }];
			const C = MapSceneDecor.clear;

			// La scène vue de biais, le mot au-dessus du milieu d’une enseigne de 20 m sur 6, posée au sol sous lui
			const THREE = three;
			const camera = new THREE.OrthographicCamera(-60, 60, 36, -36, .1, 2000);
			camera.position.set(200, 200, 200);
			camera.lookAt(0, 0, 0);
			camera.updateMatrixWorld();

			const say = new THREE.Object3D();
			Object.assign(say, { element: { offsetWidth: 120, offsetHeight: 22, style: {} }, center: new THREE.Vector2(.5, .5), visible: true });
			say.position.set(0, 1, 0);
			say.updateMatrixWorld();

			const footprint = [{ x: -10, n: -3 }, { x: 10, n: -3 }, { x: -10, n: 3 }, { x: 10, n: 3 }];
			const width = 1000;
			const height = 600;
			const screen = (x, y, z) => {
				const v = new THREE.Vector3(x, y, z).project(camera);
				return { x: (v.x + 1) / 2 * width, y: (1 - v.y) / 2 * height };
			};

			const spread = signed => {

				const decor = Object.assign(Object.create(MapSceneDecor.prototype), {
					THREE, camera, say, sign: signed,
					canvas: { clientWidth: width, clientHeight: height, getBoundingClientRect: () => ({ top: 0 }) },
					container: { qs: () => ({ getBoundingClientRect: () => ({ bottom: 0 }) }) },
					sheet: { getBoundingClientRect: () => ({ top: height }) },
					lines: { grid: {} },
					active: true, elements: [], tracing: null, spreadQueued: true, spreadRetried: false, spreadSize: null
				});

				say.center.set(.5, .5);
				decor.spreadDiameters();

				const anchor = screen(0, 1, 0);

				return { left: anchor.x - say.center.x * 120, top: anchor.y - say.center.y * 22 };

			};

			const corners = footprint.flatMap(q => [0, MapSceneDecor.SIGN_HEIGHT].map(lift => screen(q.x, lift, -q.n)));
			const box = { left: Math.min(...corners.map(p => p.x)), right: Math.max(...corners.map(p => p.x)), top: Math.min(...corners.map(p => p.y)), bottom: Math.max(...corners.map(p => p.y)) };
			const covers = at => at.left < box.right && at.left + 120 > box.left && at.top < box.bottom && at.top + 22 > box.top;

			emit({
				away: C({ left: 100, top: 100 }, size, sign, frame),
				above: C({ left: 450, top: 290 }, size, sign, frame),
				below: C({ left: 450, top: 290 }, size, sign, { ...frame, top: 260 }),
				stuck: C({ left: 450, top: 290 }, size, sign, { ...frame, top: 260, bottom: 340 }),
				none: C({ left: 450, top: 290 }, size, [], frame),
				coveredWithout: covers(spread([])),
				coveredWith: covers(spread(footprint)),
				inside: (({ left, top }) => left >= 0 && top >= 0 && left + 120 <= width && top + 22 <= height)(spread(footprint))
			});
		');

		$edge = 4;

		$this->assertEquals(['left' => 100, 'top' => 100], $result['away'] ?? NULL, "un mot loin de l'enseigne ne bouge pas");
		$this->assertEquals(['left' => 450, 'top' => 280 - $edge - 22], $result['above'] ?? NULL, "un mot posé sur l'enseigne passe juste au-dessus d'elle");
		$this->assertEquals(['left' => 450, 'top' => 320 + $edge], $result['below'] ?? NULL, "sans place au-dessus, il passe juste en dessous");
		$this->assertEquals(['left' => 450, 'top' => 290], $result['stuck'] ?? NULL, "sans place ni dessus ni dessous, il reste où il était");
		$this->assertEquals(['left' => 450, 'top' => 290], $result['none'] ?? NULL, "sans enseigne, il ne bouge pas");
		$this->assertTrue($result['coveredWithout'] ?? FALSE, "le décor du cas est juste : sans évitement, le mot couvre l'enseigne");
		$this->assertFalse($result['coveredWith'] ?? TRUE, "la scène pose le mot hors de l'enseigne, lettres comprises");
		$this->assertTrue($result['inside'] ?? FALSE, "et le garde entier dans la scène");

	}

	public function testTheRefusalStaysInsideTheScene(): void {

		$result = $this->run('
			const edge = MapSceneDecor.SAY_EDGE;
			const frame = { left: 0, right: 360, top: 48, bottom: 600 };
			const label = { width: 170, height: 22 };

			emit({
				centred: MapSceneDecor.held({ ...label, x: 180, y: 300 }, frame),
				left: MapSceneDecor.held({ ...label, x: 4, y: 300 }, frame).left === edge,
				right: MapSceneDecor.held({ ...label, x: 356, y: 300 }, frame).left === 360 - edge - 170,
				top: MapSceneDecor.held({ ...label, x: 180, y: 50 }, frame).top === 48 + edge,
				bottom: MapSceneDecor.held({ ...label, x: 180, y: 598 }, frame).top === 600 - edge - 22
			});
		');

		$this->assertEquals(['left' => 95, 'top' => 289], $result['centred'] ?? NULL, "loin des bords, l'étiquette reste centrée sur son point");
		$this->assertTrue($result['left'] ?? FALSE, "à 4 px du bord gauche, l'étiquette est entière dans la scène");
		$this->assertTrue($result['right'] ?? FALSE, "à 4 px du bord droit, l'étiquette est entière dans la scène");
		$this->assertTrue($result['top'] ?? FALSE, "sous la rangée du haut, l'étiquette ne passe pas dessous");
		$this->assertTrue($result['bottom'] ?? FALSE, "au bord du bas, l'étiquette est entière dans la scène");

	}

	public function testTheGridFadesWhenItsCellsAreTooSmall(): void {

		$result = $this->run('
			const O = MapSceneDecor.gridOpacity;

			emit({ wide: O(10.47), edge: O(8), half: O(5.5), narrow: O(3.88), least: O(3), gone: O(2), full: MapSceneDecor.GRID_OPACITY, arity: O.length });
		');

		$full = $result['full'] ?? 0;

		$this->assertEquals($full, $result['wide'] ?? -1, "une case de 10,47 px, le recul maximal à 1199, garde la grille à pleine force");
		$this->assertEquals($full, $result['edge'] ?? -1, "une case de 8 px garde la grille à pleine force");
		$this->assertEquals(round($full / 2, 6), round($result['half'] ?? -1, 6), "une case de 5,5 px, à mi-chemin de 8 et de 3, garde la moitié de la grille");
		$this->assertTrue(($result['narrow'] ?? 1) > 0 and ($result['narrow'] ?? 1) < $full / 5, "une case de 3,88 px, le recul maximal à 360, garde moins d'un cinquième de la grille");
		$this->assertEquals(0, $result['least'] ?? NULL, "une case de 3 px n'a plus de grille");
		$this->assertEquals(0, $result['gone'] ?? NULL, "une case de 2 px non plus");
		$this->assertEquals(1, $result['arity'] ?? NULL, "la force de la grille ne lit que le côté de la case, jamais la largeur de l'écran");

	}

	/**
	 * Le plafond du décor tel que la page le porte, sur la feuille du décor : `null` s'il n'y arrive pas, ce qui fait échouer
	 * tout scénario qui le lit.
	 */
	private function getTraceLimit(): string {

		$h = new \ReflectionMethod(SceneUi::class, 'getDecorSheet')->invoke(NULL, new \structure\Structure(['id' => \structure\FixtureAiLib::F1]));

		return preg_match('/data-trace-limit="([^"]*)"/', $h, $match) ? html_entity_decode($match[1], ENT_QUOTES) : 'null';

	}

	/**
	 * Joue un scénario sous node avec les fichiers de la scène, et rend ce qu'il émet.
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
				// Un scénario qui esquisse three.js à sa main garde le nom de THREE pour son esquisse
				const three = await import("file://" + directory + "/base/scene/static/lib/three-0.170.0.module.min.js");

				// La page n\'est pas là : le mot n\'a pas de marge à lire, la fenêtre aucune touche à écouter, la pose aucun délai à poser ni à annuler
				const page = { window: { removeEventListener: () => {} }, document: { addEventListener: () => {} }, console, getComputedStyle: () => ({ marginTop: "0px" }), setTimeout: () => 0, clearTimeout };

				const source = ["base/scene/static/js/plan.js", "base/scene/static/js/crew.js", "production/series/static/js/seriesSheet.js", "production/map/static/js/scene.js", "production/map/static/js/sceneLayer.js", "production/map/static/js/sceneDecor.js", "production/map/static/js/sceneDecorDefault.js"].map(file => readFileSync(directory + "/" + file, "utf8")).join("\n");
				const { MapScene, ScenePlanLand, MapSceneDecor, MapSceneDecorDefault, ScenePlanGeometry } = vm.runInContext(source + "\n;({ MapScene, ScenePlanLand, MapSceneDecor, MapSceneDecorDefault, ScenePlanGeometry })", vm.createContext(page));

				const square = (x, n, half) => [{ x: x - half, n: n - half }, { x: x + half, n: n - half }, { x: x + half, n: n + half }, { x: x - half, n: n + half }];
				// `islandOf()` ne lit de son instance que le terrain
				const islandOf = (land, p) => MapSceneDecor.prototype.islandOf.call({ geometry: { land } }, p);

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
