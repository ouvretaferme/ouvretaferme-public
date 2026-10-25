<?php
namespace map;

/**
 * Le tracé dessiné d'un glissé (`static/js/sceneDecor.js`, `MapSceneDecor.startFreehand()` et `finishFreehand()`), éprouvé sous
 * node : un appui qui ne bouge pas de 4 px reste un clic, un glissé relève sa ligne puis la simplifie au relâché sous le plafond
 * de points et le poids du décor, et termine le tracé. Échap retire un glissé refusé d'un coup, un second doigt ou un pointeur
 * perdu annulent le glissé en cours, et la vue retrouve son déplacement à chaque sortie. Un tracé dessiné franchit la portée du
 * terrain, un tuyau non.
 *
 * Aucune écriture, aucune base ouverte : `land()` et `update()` relèvent ce qui partirait.
 *
 * Lancement : dev/test package=map file=DecorFreehandAi
 */
class DecorFreehandAiTest extends \Test {

	public function testSimplifyKeepsTheEndsWithinTheTolerance(): void {

		$result = $this->run('
			const arc = Array.from({ length: 401 }, (_, i) => ({ x: 50 * Math.cos(i / 400 * Math.PI / 2), n: 50 * Math.sin(i / 400 * Math.PI / 2) }));
			const kept = MapSceneDecor.simplify(arc, .25);

			// L\'écart de chaque point de la main à la ligne gardée
			const away = p => Math.min(...kept.slice(1).map((q, i) => ScenePlanGeometry.distanceToSegment(p, kept[i], q)));

			emit({
				count: kept.length,
				ends: kept[0] === arc[0] && kept.at(-1) === arc.at(-1),
				worst: Math.max(...arc.map(away)),
				straight: MapSceneDecor.simplify([{ x: 0, n: 0 }, { x: 1, n: 0 }, { x: 2, n: 0 }, { x: 3, n: 0 }], .25).length
			});
		');

		$this->assertTrue(($result['count'] ?? 401) < 30, "un quart de cercle de 50 m garde moins de 30 points des 401 relevés");
		$this->assertTrue(($result['count'] ?? 0) > 2, "et plus que ses deux bouts");
		$this->assertTrue($result['ends'] ?? FALSE, "les deux bouts restent");
		$this->assertTrue(($result['worst'] ?? 1) <= .25, "aucun point de la main ne s'écarte de plus de la tolérance");
		$this->assertEquals(2, $result['straight'] ?? NULL, "une ligne droite se réduit à ses deux bouts");

	}

	public function testAPressThatDoesNotMoveIsAClick(): void {

		$result = $this->run('
			const decor = session("cloture");

			decor.down(press(10.4, 0));
			const panning = decor.controls.enablePan;
			decor.up(press(10.4, 0));

			emit({ panning, points: decor.tracing.points.map(p => [p.x, p.n]), freehand: decor.tracing.freehand === null, pan: decor.controls.enablePan, listeners: listening() });
		');

		$this->assertFalse($result['panning'] ?? TRUE, "l'appui d'un tracé neuf retient la vue tant qu'il peut devenir un glissé");
		$this->assertEquals([[10, 0]], $result['points'] ?? NULL, "relâché sur place, il pose un point au mètre, comme un clic");
		$this->assertTrue($result['freehand'] ?? FALSE, "aucun glissé ne reste en cours");
		$this->assertTrue($result['pan'] ?? FALSE, "la vue se déplace de nouveau");
		$this->assertEquals(0, $result['listeners'] ?? NULL, "le glissé ne laisse aucun écouteur");

	}

	public function testAShakyClickUnderAMetreStaysAClick(): void {

		$result = $this->run('
			const decor = session("cloture");

			draw(decor, [{ x: 10.4, n: 0 }, { x: 10.8, n: 0 }, { x: 11.1, n: 0 }]);

			emit({ points: decor.tracing.points.map(p => [p.x, p.n]), strokes: decor.tracing.strokes.length, landed: decor.landed.length, pan: decor.controls.enablePan });
		');

		$this->assertEquals([[11, 0]], $result['points'] ?? NULL, "une main de moins d'un mètre pose un point au relâché, comme un clic");
		$this->assertEquals(0, $result['strokes'] ?? NULL, "elle ne compte pas comme un glissé");
		$this->assertEquals(0, $result['landed'] ?? NULL, "le tracé reste en main");
		$this->assertTrue($result['pan'] ?? FALSE, "la vue se déplace de nouveau");

	}

	public function testAStrokeIsSimplifiedAndEndsTheTrace(): void {

		$result = $this->run('
			const decor = session("cloture");
			const hand = Array.from({ length: 401 }, (_, i) => ({ x: 50 * Math.cos(i / 400 * Math.PI / 2) + .003, n: 50 * Math.sin(i / 400 * Math.PI / 2) }));

			let relieved = 0;
			draw(decor, hand, () => { relieved = Math.max(relieved, decor.armed.trace?.length ?? 0); });

			const line = decor.landed[0] ?? [];

			emit({
				landed: decor.landed.length,
				relieved,
				count: line.length,
				first: line[0],
				last: line.at(-1),
				centimetres: line.every(p => Math.abs(p.x * 100 - Math.round(p.x * 100)) < 1e-6 && Math.abs(p.n * 100 - Math.round(p.n * 100)) < 1e-6),
				spaced: line.slice(1, -1).every((p, i) => ScenePlanGeometry.length(ScenePlanGeometry.sub(p, line[i])) >= MapSceneDecor.FREEHAND_STEP - 1e-6),
				tracing: decor.tracing === null,
				pan: decor.controls.enablePan,
				listeners: listening()
			});
		');

		$this->assertEquals(1, $result['landed'] ?? NULL, "le relâché pose le tracé, d'une seule écriture");
		$this->assertTrue(($result['relieved'] ?? 0) > 60 and ($result['relieved'] ?? 999) <= 80, "pendant le glissé, la main se relève à un point par mètre, 79 au plus sur 78,5 m");
		$this->assertTrue(($result['count'] ?? 0) > 2 and ($result['count'] ?? 99) < 30, "la courbe garde ses virages, sans un point par mètre");
		$this->assertEquals(['x' => 50, 'n' => 0], $result['first'] ?? NULL, "le tracé part du point d'appui, au centimètre");
		$this->assertEquals(['x' => 0, 'n' => 50], $result['last'] ?? NULL, "et finit au point du relâché");
		$this->assertTrue($result['centimetres'] ?? FALSE, "chaque point est arrondi au centimètre");
		$this->assertTrue($result['spaced'] ?? FALSE, "deux points gardés sont à un mètre au moins l'un de l'autre, sauf le point du relâché");
		$this->assertTrue($result['tracing'] ?? FALSE, "le tracé est terminé");
		$this->assertTrue($result['pan'] ?? FALSE, "la vue se déplace de nouveau");
		$this->assertEquals(0, $result['listeners'] ?? NULL, "le glissé ne laisse aucun écouteur");

	}

	public function testPointsAndWeightAreJudgedAfterTheSimplification(): void {

		$result = $this->run('
			const wiggle = Array.from({ length: 401 }, (_, i) => ({ x: i / 2, n: 3 * Math.sin(i / 2 / 10 * Math.PI * 2) }));

			// Pendant le glissé, le tracé passe le plafond de points sans refus, qu\'il tiendra une fois simplifié
			const capped = session("cloture", { tracePoints: 20 });
			const during = [];
			draw(capped, wiggle, () => during.push([capped.armed.trace.length, capped.armed.bad]));

			// Le poids du décor admet six points de cette ligne, sans plus
			const weighed = session("cloture");
			const limit = weighed.traceLimit;
			weighed.traceLimit = { ...limit, length: (MapSceneDecor.lengthOf(wiggle) + limit.corner * 4) * (limit.weights.cloture ?? 1) + (limit.starts.cloture ?? 0) };
			draw(weighed, wiggle);

			const plain = session("cloture");
			draw(plain, wiggle);

			emit({
				passed: during.some(([count, bad]) => count > 20 && bad === null),
				refusedDuring: during.filter(([count, bad]) => bad !== null).length,
				capped: (capped.landed[0] ?? []).length,
				weighed: (weighed.landed[0] ?? []).length,
				plain: (plain.landed[0] ?? []).length
			});
		');

		$this->assertTrue($result['passed'] ?? FALSE, "pendant le glissé, plus de points que le plafond ne font pas refuser la ligne");
		$this->assertEquals(0, $result['refusedDuring'] ?? NULL, "ni le nombre de points ni le poids ne se disent pendant le glissé");
		$this->assertTrue(($result['plain'] ?? 0) > 20, "sans plafond serré, la ligne garde plus de 20 points");
		$this->assertTrue(($result['capped'] ?? 99) <= 20 and ($result['capped'] ?? 0) >= 2, "sous un plafond de 20 points, la tolérance double jusqu'à le tenir");
		$this->assertTrue(($result['weighed'] ?? 99) <= 6 and ($result['weighed'] ?? 0) >= 2, "sous le poids du décor, elle double jusqu'à le tenir");

	}

	public function testAFullTraceRefusesTheStrokeAndEscapeTakesItBack(): void {

		$result = $this->run('
			const line = Array.from({ length: 20 }, (_, i) => ({ x: i * 2, n: 0 }));
			const decor = session("cloture", { tracePoints: 20, line });
			const wiggle = Array.from({ length: 101 }, (_, i) => ({ x: 38 + i / 2, n: 3 * Math.sin(i / 20 * Math.PI * 2) }));

			draw(decor, wiggle);

			const refused = { bad: decor.selected.bad, tracing: decor.tracing !== null, points: decor.tracing?.points.length, strokes: decor.tracing?.strokes.length };

			decor.unplot();
			const back = { points: decor.tracing?.points.length, strokes: decor.tracing?.strokes.length };

			decor.unplot();

			emit({ refused, back, dropped: decor.tracing === null, updates: decor.updates.length, restored: decor.restored });
		');

		$this->assertEquals('points', $result['refused']['bad'] ?? NULL, "un tracé plein refuse le glissé, la tolérance ne pouvant pas le réduire sous deux points");
		$this->assertTrue($result['refused']['tracing'] ?? FALSE, "le tracé refusé reste en main");
		$this->assertEquals(21, $result['refused']['points'] ?? NULL, "avec le glissé, réduit à son point d'arrivée");
		$this->assertEquals(['points' => 20, 'strokes' => 0], $result['back'] ?? NULL, "Échap retire le glissé d'un coup");
		$this->assertTrue($result['dropped'] ?? FALSE, "Échap suivant abandonne la reprise");
		$this->assertEquals(0, $result['updates'] ?? NULL, "rien ne s'écrit");
		$this->assertEquals(1, $result['restored'] ?? NULL, "le tracé revient à sa ligne d'avant");

	}

	public function testASecondFingerOrALostPointerCancelsTheStroke(): void {

		$result = $this->run('
			const cancelled = finish => {
				const decor = session("cloture");
				decor.down(press(0, 0));
				[1, 2, 3, 4, 5].forEach(x => fire("pointermove", press(x, x)));
				const drawing = decor.tracing.freehand !== null && decor.armed.trace.length > 1;
				finish(decor);
				decor.up(press(5, 5));
				return { drawing, freehand: decor.tracing.freehand === null, points: decor.tracing.points.length, landed: decor.landed.length, pan: decor.controls.enablePan, listeners: listening() };
			};

			emit({
				finger: cancelled(decor => decor.down(press(9, 9, { pointerId: 2, isPrimary: false }))),
				lost: cancelled(() => fire("pointercancel", press(5, 5)))
			});
		');

		foreach(['finger' => "un second doigt", 'lost' => "un pointeur perdu"] as $case => $label) {
			$this->assertTrue($result[$case]['drawing'] ?? FALSE, "le glissé dessine avant ".$label);
			$this->assertTrue($result[$case]['freehand'] ?? FALSE, $label." annule le glissé");
			$this->assertEquals(0, $result[$case]['points'] ?? NULL, $label." rend le tracé à ses points d'avant, aucun");
			$this->assertEquals(0, $result[$case]['landed'] ?? NULL, "après ".$label.", le relâché ne pose rien");
			$this->assertTrue($result[$case]['pan'] ?? FALSE, "après ".$label.", la vue se déplace de nouveau");
			$this->assertEquals(0, $result[$case]['listeners'] ?? NULL, "après ".$label.", le glissé ne laisse aucun écouteur");
		}

	}

	public function testOnlyTheFreeEndOrAnEmptyTraceStartsAStroke(): void {

		$result = $this->run('
			const decor = session("cloture");
			decor.down(press(0, 0));
			decor.up(press(0, 0));
			decor.down(press(10, 0));
			decor.up(press(10, 0));

			// À 3 m du bout libre, l\'appui reste à la vue ; sur son rond, il dessine depuis lui
			decor.down(press(13, 0));
			const away = { freehand: decor.tracing.freehand === null, pan: decor.controls.enablePan };
			decor.up(press(30, 0));

			decor.down(press(10.5, .5));
			const onEnd = decor.tracing.freehand !== null;
			[12, 14, 16, 18, 20].forEach(x => fire("pointermove", press(x, 0)));
			decor.up(press(20, 0));

			const modifier = session("cloture");
			modifier.down(press(0, 0, { shiftKey: true }));

			const pipe = session("tuyau");
			pipe.down(press(0, 0));

			emit({ away, onEnd, line: (decor.landed[0] ?? []).map(p => [p.x, p.n]), modifier: modifier.tracing.freehand === null, pipe: pipe.tracing.freehand === null, pipePan: pipe.controls.enablePan });
		');

		$this->assertTrue($result['away']['freehand'] ?? FALSE, "loin du bout libre, l'appui ne dessine pas");
		$this->assertTrue($result['away']['pan'] ?? FALSE, "il laisse la vue se déplacer");
		$this->assertTrue($result['onEnd'] ?? FALSE, "sur le rond du bout libre, l'appui dessine");
		$this->assertEquals([[0, 0], [10, 0], [20, 0]], $result['line'] ?? NULL, "le glissé part du bout libre, compté une fois, et le relâché termine le tracé");
		$this->assertTrue($result['modifier'] ?? FALSE, "Maj fait tourner la vue, l'appui ne dessine pas");
		$this->assertTrue($result['pipe'] ?? FALSE, "un tuyau ne se dessine pas, il se pose au clic");
		$this->assertTrue($result['pipePan'] ?? FALSE, "et la vue reste libre");

	}

	public function testAFreehandTraceCrossesTheReach(): void {

		$result = $this->run('
			const square = (x, n, half) => [{ x: x - half, n: n - half }, { x: x + half, n: n - half }, { x: x + half, n: n + half }, { x: x - half, n: n + half }];
			const land = new ScenePlanLand([{ id: 1, points: square(0, 0, 50) }, { id: 2, points: square(250, 0, 50) }]);
			const east = land.islands.find(island => island.zones[0].id === 2);
			const origin = { lng0: 0, lat0: 0, kx: 1, ky: 1 };
			const decor = Object.assign(Object.create(MapSceneDecor.prototype), { geometry: { land }, origin, blockers: [] });
			const far = { x: Math.max(...land.moved(east).map(q => q.x)) + 160, n: 0 };
			const real = ScenePlanGeometry.sub(far, east.offset);

			emit({
				bounded: decor.islandOf(far) === null,
				road: decor.startRefusal({ def: MapSceneDecor.KITS.route }, far) === null,
				pipe: decor.startRefusal({ def: MapSceneDecor.KITS.tuyau }, far),
				coordinates: decor.coordinates(far),
				real: [real.x, real.n],
				reread: land.at(real) === east
			});
		');

		$this->assertTrue($result['bounded'] ?? FALSE, "à 160 m de l'îlot, le point est hors de portée");
		$this->assertTrue($result['road'] ?? FALSE, "une route y commence, au-delà du trait");
		$this->assertEquals('outside', $result['pipe'] ?? NULL, "un tuyau y reste refusé");
		$this->assertEquals($result['real'] ?? 'absent', $result['coordinates'] ?? NULL, "le point s'enregistre sur l'îlot le plus proche, sans son décalage");
		$this->assertTrue($result['reread'] ?? FALSE, "et se relit sur cet îlot");

	}

	public function testHandlesThinOutOnTheScreen(): void {

		$result = $this->run('
			const screen = Array.from({ length: 21 }, (_, i) => ({ x: i * 10, y: 0 }));
			emit({ plain: MapSceneDecor.sparse(screen, 44, null), chosen: MapSceneDecor.sparse(screen, 44, 7) });
		');

		$this->assertEquals([0, 5, 10, 15, 20], $result['plain'] ?? NULL, "un rond par cible de 44 px, les deux bouts compris");
		$this->assertEquals([0, 5, 7, 12, 17, 20], $result['chosen'] ?? NULL, "le point choisi garde son rond, et les suivants se comptent depuis lui");

	}

	/**
	 * Joue un scénario sous node avec les fichiers de la scène, et rend ce qu'il émet. `session(kit, { tracePoints, line })` ouvre
	 * une pose dont le tracé est en main : neuf, ou repris par « Continuer » depuis le dernier point de `line`. Le plan s'y voit à
	 * 10 px par mètre, `press(x, n)` est l'événement d'un pointeur sur ce point, `fire()` le rejoue aux écouteurs du canevas et
	 * `draw()` un glissé entier sur une ligne. `land()` relève la ligne posée dans `landed`, `update()` dans `updates`, et
	 * `listening()` compte les écouteurs que le glissé a laissés sur le canevas.
	 */
	private function run(string $scenario): array {

		$script = '
			const directory = '.json_encode(LIME_DIRECTORY).';
			const emit = value => process.stdout.write(JSON.stringify(value));

			(async () => {
				const { readFileSync } = await import("node:fs");
				const vm = await import("node:vm");

				const page = { window: {}, document: { addEventListener: () => {} }, console, getComputedStyle: () => ({ getPropertyValue: () => "44px" }) };
				const source = ["base/scene/static/js/plan.js", "base/scene/static/js/crew.js", "production/series/static/js/seriesSheet.js", "production/map/static/js/scene.js", "production/map/static/js/sceneLayer.js", "production/map/static/js/sceneDecor.js"].map(file => readFileSync(directory + "/" + file, "utf8")).join("\n");
				const { MapSceneDecor, ScenePlanGeometry, ScenePlanLand } = vm.runInContext(source + "\n;({ MapSceneDecor, ScenePlanGeometry, ScenePlanLand })", vm.createContext(page));

				const listeners = {};
				const canvas = {
					addEventListener: (type, listener) => { listeners[type] = [...(listeners[type] ?? []), listener]; },
					removeEventListener: (type, listener) => { listeners[type] = (listeners[type] ?? []).filter(other => other !== listener); },
					getBoundingClientRect: () => ({ left: 0, top: 0, width: 2000, height: 2000 })
				};
				const fire = (type, event) => (listeners[type] ?? []).slice().forEach(listener => listener(event));
				const listening = () => Object.values(listeners).reduce((total, list) => total + list.length, 0);

				const press = (x, n, more = {}) => ({ clientX: x * 10, clientY: -n * 10, pointerId: 1, button: 0, isPrimary: true, shiftKey: false, ctrlKey: false, metaKey: false, p: { x, n }, ...more });

				const draw = (decor, line, each = () => {}) => {
					decor.down(press(line[0].x, line[0].n));
					line.slice(1).forEach(p => {
						fire("pointermove", press(p.x, p.n));
						each();
					});
					decor.up(press(line.at(-1).x, line.at(-1).n));
				};

				const traceLimit = '.$this->getTraceLimit().';

				const session = (kit, { tracePoints = '.MapSetting::DECOR_TRACE_POINTS.', line = null } = {}) => {

					const decor = Object.create(MapSceneDecor.prototype);
					const e = { ...MapSceneDecor.element(kit, { x: 0, n: 0 }), thick: .6, bad: null };

					Object.assign(decor, {
						active: true, armed: null, selected: null, tracing: null, held: null, point: null, downAt: null,
						elements: [], removals: [], areas: [], landed: [], updates: [], restored: 0,
						tracePoints, traceLimit, canvas, tools: {}, controls: { enablePan: true },
						layers: { typeOf: () => null, active: e.layer },
						sheet: { dataset: { length: "{value} m" } },
						refusals: { points: "plus de {value} points", limit: "trop lourd", short: "trop court", long: "trop long" },
						pick: event => event.p,
						project: points => points.map(p => ({ x: p.x * 10, y: -p.n * 10, z: 0 })),
						landRefusal: () => null,
						startRefusal: () => null,
						onBed: () => false,
						build: built => {
							built.path = built.trace;
							built.root = (built.trace.length > 1) ? {} : null;
							built.bad = decor.refusal(built);
						},
						drop: dropped => { dropped.root = null; },
						tell: () => {}, tellPoint: () => {}, hush: () => {}, refresh: () => {}, showTracing: () => {},
						disarm: () => { decor.tracing = null; decor.armed = null; },
						land: () => { decor.landed.push(MapSceneDecor.lineOf(decor.armed)); },
						update: (updated, before, after) => decor.updates.push(after),
						restore: () => { decor.restored++; },
						describe: described => ({ trace: described.trace })
					});

					if(line === null) {
						decor.armed = e;
						decor.tracing = { e, points: [], kept: 0, cursor: null, before: null, reversed: false, freehand: null, strokes: [] };
					} else {
						e.id = 1;
						decor.lay(e, line);
						decor.elements.push(e);
						decor.selected = e;
						decor.point = line.length - 1;
						decor.extend();
					}

					return decor;

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

	// Le plafond du décor tel que la page le donne au navigateur
	private function getTraceLimit(): string {

		$h = new \ReflectionMethod(SceneUi::class, 'getDecorSheet')->invoke(NULL, new \structure\Structure(['id' => \structure\FixtureAiLib::F1]));

		return preg_match('/data-trace-limit="([^"]*)"/', $h, $match) ? html_entity_decode($match[1], ENT_QUOTES) : 'null';

	}

}
?>
