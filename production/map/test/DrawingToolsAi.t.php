<?php
namespace map;

/**
 * Ce que fait la rangée du tracé (`static/js/drawing.js`, `MapDrawing`) : en rotation, un appui sur la forme la tourne au
 * lieu de la déplacer, et « Recommencer » rend le tracé point par point. La fiche du geste passe sous la carte quand la fenêtre se réduit sous 768 px pendant
 * le tracé, et y revient quand elle s'élargit. Une forme qu'on crée a ses issues sous son nom, dans le bloc, à toute largeur. Un sommet de contour qu'on pose s'aimante au sommet voisin, sinon au côté voisin.
 * Un bouton de la fiche qui la déplace, comme « Planches » refermé, ne désigne pas ce que la carte a dessous. La paire « Forme libre » /
 * « Rectangle » reste en tête de la rangée quel que soit l'état du tracé et désigne la forme qu'on trace, et un contour refermé
 * qu'on passe en rectangle revient intact tant que rien n'est tracé, sans offrir de mode pendant ce temps, et sa fiche reste où elle était, sur la surface qui sera gardée.
 *
 * Lancement : dev/test package=map file=DrawingToolsAi
 */
class DrawingToolsAiTest extends DrawingBenchAiTest {

	// Le contour du jardin rouvert, avec un bouton « Enregistrer » prêt, et l'appui qui tire sa surface d'un point à un autre
	const string OPEN = '
		const plot = { drawing: "polygon", drawingMap: "map", drawingSheet: "plot", drawingTarget: "plot", drawingId: "5", drawingUrl: "/map/plot:doUpdateShape", drawingCoordinates: JSON.stringify(square), drawingAnchor: JSON.stringify(square) };

		const open = () => {
			MapDrawing.start({ dataset: plot });
			const button = { dataset: {}, classList: { contains: () => false, toggle: () => null }, qs: () => ({ textContent: "" }) };
			MapDrawing.current.getFinishButtons = () => [button];
			return MapDrawing.current;
		};

		const pull = (drawing, from, to) => {
			const at = map.unproject(from);
			drawing.startBody({ lngLat: { lng: at.lng, lat: at.lat, toArray: () => [at.lng, at.lat] }, preventDefault: () => {} }, { button: 0, clientX: from[0], clientY: from[1], preventDefault: () => {} });
			drawing.moveDrag({ clientX: to[0], clientY: to[1] });
			drawing.endDrag();
		};

		const center = drawing => {
			const point = map.project(turf.centroid(turf.polygon([[...drawing.shape.points, drawing.shape.points[0]]])).geometry.coordinates);
			return [point.x, point.y];
		};
	';

	// La rangée de la séance, dont chaque case note ses classes : ce qu'elle montre, et la forme qu'elle désigne
	const string ROW = '
		const tools = ["free", "rectangle", "rotate", "remove"].map(name => {
			const classes = new Set();
			return { dataset: { tool: name }, classes, classList: { toggle: (name, on) => on ? classes.add(name) : classes.delete(name), contains: name => classes.has(name) } };
		});

		const modes = ["edit", "move", "rotate"].map(name => {
			const classes = new Set();
			return { dataset: { drawingMode: name }, classes, classList: { toggle: (name, on) => on ? classes.add(name) : classes.delete(name), contains: name => classes.has(name) } };
		});

		// La rangée des formes, et la case de chaque forme en tête des gestes (PlanUi::getMapShapeChoice())
		const node = dataset => {
			const classes = new Set();
			return { dataset, classes, classList: { toggle: (name, on) => on ? classes.add(name) : classes.delete(name), contains: name => classes.has(name) } };
		};

		const shapes = node({});
		const choices = ["free", "rectangle", "pac"].map(name => node({ drawingChoice: name }));

		const watch = drawing => {
			drawing.bar = {
				qsa: (selector, callback) => {
					if(selector === "[data-tool]") {
						tools.forEach(callback);
					} else if(selector === "[data-drawing-mode]") {
						modes.forEach(callback);
					} else if(selector === "[data-drawing-shapes]") {
						callback(shapes);
					} else if(selector === "[data-drawing-choice]") {
						choices.forEach(callback);
					}
					return [];
				},
				getBoundingClientRect: () => ({ width: 0 }),
				remove: () => {}
			};
			drawing.barNames = null;
			drawing.refreshTools();
			return drawing;
		};

		const row = () => {
			const shown = tools.filter(tool => tool.classes.has("hide") === false);
			return { shown: shown.map(tool => tool.dataset.tool), selected: shown.filter(tool => tool.classes.has("selected")).map(tool => tool.dataset.tool) };
		};

		const gestures = () => modes.filter(mode => mode.classes.has("hide") === false).map(mode => mode.dataset.drawingMode);

		// Ce que la rangée montre des formes : leur propre rangée, ou la case de la forme tracée, seule ou avec son menu
		const choice = () => {
			const shown = choices.filter(choice => choice.classes.has("hide") === false);
			return { shapes: shapes.classes.has("hide") === false, choice: shown.map(choice => choice.dataset.drawingChoice), alone: shown.some(choice => choice.classes.has("util-mapbar-choice-alone")) };
		};

		const use = (drawing, name) => drawing.useTool(tools.find(tool => tool.dataset.tool === name));

		const zone = coordinates => ({ drawing: "polygon", drawingMap: "map", drawingSheet: "zone-create", drawingCoordinates: JSON.stringify(coordinates), drawingRectangle: "", drawingFree: "" });
	';

	public function testTheShapePairLeadsTheRowInEveryState(): void {

		$result = $this->run(1512, self::ROW.'
			MapDrawing.start({ dataset: zone(null) });
			const drawing = watch(MapDrawing.current);
			result.nothing = row();

			drawing.clickMap({ originalEvent: { detail: 1 }, point: { x: 100, y: 100 }, lngLat: { toArray: () => [map.unproject([100, 100]).lng, map.unproject([100, 100]).lat] } });
			drawing.clickMap({ originalEvent: { detail: 1 }, point: { x: 300, y: 100 }, lngLat: { toArray: () => [map.unproject([300, 100]).lng, map.unproject([300, 100]).lat] } });
			result.open = row();

			use(drawing, "rectangle");
			result.rectangle = row();

			// La forme qu\'on trace ne se remplace pas par elle-même
			const shape = drawing.shape;
			use(drawing, "rectangle");
			result.same = (drawing.shape === shape);

			drawing.cancel();

			MapDrawing.start({ dataset: zone(square) });
			watch(MapDrawing.current);
			result.closed = row();
			MapDrawing.current.cancel();
		');

		$this->assertEquals(['shown' => ['free', 'rectangle'], 'selected' => ['free']], $result['nothing'] ?? NULL, 'rien de posé, la paire est là, sur « Forme libre »');
		$this->assertEquals(['shown' => ['free', 'rectangle'], 'selected' => ['free']], $result['open'] ?? NULL, 'un contour à moitié posé la garde');
		$this->assertEquals(['shown' => ['free', 'rectangle'], 'selected' => ['rectangle']], $result['rectangle'] ?? NULL, 'passée en rectangle, elle désigne « Rectangle »');
		$this->assertTrue($result['same'] ?? FALSE, 'un clic sur la forme désignée ne la recommence pas');
		$this->assertEquals(['shown' => ['free', 'rectangle', 'rotate', 'remove'], 'selected' => ['free']], $result['closed'] ?? NULL, 'un contour refermé garde la paire avant ses outils');

	}

	public function testTheShapesJoinTheGesturesOnceTheyShow(): void {

		$result = $this->run(1512, self::ROW.'
			MapDrawing.start({ dataset: zone(null) });
			const drawing = watch(MapDrawing.current);
			result.nothing = choice();

			drawing.clickMap({ originalEvent: { detail: 1 }, point: { x: 100, y: 100 }, lngLat: { toArray: () => [map.unproject([100, 100]).lng, map.unproject([100, 100]).lat] } });
			result.open = choice();

			use(drawing, "rectangle");
			result.rectangle = choice();
			drawing.cancel();

			MapDrawing.start({ dataset: zone(square) });
			watch(MapDrawing.current);
			result.closed = choice();
			MapDrawing.current.cancel();

			// Un abri tracé en contour, qui ne se reprend qu\'en rectangle, puis passé en rectangle : il n\'a plus d\'autre forme
			MapDrawing.start({ dataset: { drawing: "polygon", drawingMap: "map", drawingCoordinates: JSON.stringify(square), drawingRectangle: "", drawingDimensions: "" } });
			const greenhouse = watch(MapDrawing.current);
			result.contour = choice();
			use(greenhouse, "rectangle");
			result.retraced = choice();
			greenhouse.cancel();

			// Un contour dont le lien ne propose aucune autre forme
			MapDrawing.start({ dataset: { drawing: "polygon", drawingMap: "map", drawingCoordinates: JSON.stringify(square) } });
			watch(MapDrawing.current);
			result.single = choice();
			MapDrawing.current.cancel();
		');

		$this->assertEquals(['shapes' => TRUE, 'choice' => [], 'alone' => FALSE], $result['nothing'] ?? NULL, 'avant le premier point, les formes gardent leur rangée');
		$this->assertEquals(['shapes' => TRUE, 'choice' => [], 'alone' => FALSE], $result['open'] ?? NULL, 'un contour à moitié posé n\'a pas encore de geste');
		$this->assertEquals(['shapes' => FALSE, 'choice' => ['rectangle'], 'alone' => FALSE], $result['rectangle'] ?? NULL, 'un rectangle montre ses modes, sa forme passe en tête avec son menu');
		$this->assertEquals(['shapes' => FALSE, 'choice' => ['free'], 'alone' => FALSE], $result['closed'] ?? NULL, 'un contour refermé a sa forme en tête des gestes');
		$this->assertEquals(['shapes' => FALSE, 'choice' => ['free'], 'alone' => FALSE], $result['contour'] ?? NULL, 'le contour d\'un abri garde « Rectangle » dans son menu');
		$this->assertEquals(['shapes' => TRUE, 'choice' => [], 'alone' => FALSE], $result['retraced'] ?? NULL, 'confié au rectangle, il n\'offre aucun geste et les formes reprennent leur rangée');
		$this->assertEquals(['shapes' => FALSE, 'choice' => ['free'], 'alone' => TRUE], $result['single'] ?? NULL, 'sans autre forme proposée, la tête n\'a rien à dérouler');

	}

	public function testAGreenhouseKeepsItsOwnRow(): void {

		$result = $this->run(1512, self::ROW.'
			// Un abri tracé en contour, qui ne se reprend qu\'en rectangle (PlotUi::getDrawing())
			MapDrawing.start({ dataset: { drawing: "polygon", drawingMap: "map", drawingCoordinates: JSON.stringify(square), drawingRectangle: "", drawingDimensions: "" } });
			const drawing = watch(MapDrawing.current);
			result.contour = row();

			use(drawing, "rectangle");
			result.retraced = row();
			drawing.cancel();

			MapDrawing.start({ dataset: { drawing: "rectangle", drawingMap: "map", drawingCoordinates: JSON.stringify(square.slice(0, 4)), drawingDimensions: "" } });
			watch(MapDrawing.current);
			result.rectangle = row();
			MapDrawing.current.cancel();
		');

		$this->assertEquals(['shown' => ['rectangle', 'rotate', 'remove'], 'selected' => []], $result['contour'] ?? NULL, 'un abri tracé en contour ne propose que « Rectangle »');
		$this->assertEquals(['shown' => ['rectangle'], 'selected' => ['rectangle']], $result['retraced'] ?? NULL, 'et le garde seul une fois passé en rectangle');
		$this->assertEquals(['shown' => ['rotate', 'remove'], 'selected' => []], $result['rectangle'] ?? NULL, 'un abri rectangle n\'a pas la paire');

	}

	public function testAContourLeftToTheRectangleOffersNoGesture(): void {

		$result = $this->run(1512, self::ROW.'
			MapDrawing.start({ dataset: zone(square) });
			const drawing = watch(MapDrawing.current);
			result.closed = gestures();

			use(drawing, "rectangle");
			result.confided = { gestures: gestures(), tools: row() };

			use(drawing, "free");
			result.back = gestures();
			drawing.cancel();

			// Un rectangle qui ne reprend aucun contour garde ses modes, éteints tant qu\'il n\'est pas tracé
			MapDrawing.start({ dataset: zone(null) });
			use(watch(MapDrawing.current), "rectangle");
			result.fresh = gestures();
			MapDrawing.current.cancel();
		');

		$this->assertEquals(['edit', 'move', 'rotate'], $result['closed'] ?? NULL, 'un contour refermé a ses trois modes');
		$this->assertEquals(['gestures' => [], 'tools' => ['shown' => ['free', 'rectangle'], 'selected' => ['rectangle']]], $result['confided'] ?? NULL, 'confié au rectangle, il n\'offre plus que la paire, sur « Rectangle »');
		$this->assertEquals(['edit', 'move', 'rotate'], $result['back'] ?? NULL, 'revenu en « Forme libre », il les retrouve');
		$this->assertEquals(['edit', 'move', 'rotate'], $result['fresh'] ?? NULL, 'un rectangle à tracer à neuf garde les siens');

	}

	public function testAContourLeftToTheRectangleKeepsTheCardItHad(): void {

		$result = $this->run(1512, self::ROW.'
			// Une parcelle rouverte, dont la surface a été saisie à la main : la fiche la dit tant que le contour ne change pas
			MapDrawing.start({ dataset: { ...zone(square), drawingSheet: "zone", drawingId: "5", drawingUrl: "/map/zone:doUpdateShape", drawingArea: "saisie" } });
			const drawing = watch(MapDrawing.current);

			const said = [];
			const card = drawing.card;
			drawing.card = { dataset: { areaLarge: "{value} ha", areaSmall: "{value} m²" }, qsa: (selector, callback) => (selector === "[data-drawing-summary]") ? callback({ set textContent(value) { said.push(value); } }) : [] };
			drawing.getFinishButtons = () => [];

			const read = () => {
				drawing.refreshIssues();
				return { at: JSON.stringify(drawing.getCardAt()), summary: said.at(-1) };
			};

			result.contour = read();

			use(drawing, "rectangle");
			result.confided = read();

			drawing.card = card;
			drawing.cancel();
		');

		$this->assertEquals('saisie', $result['contour']['summary'] ?? NULL, 'le contour rouvert dit la surface saisie');
		$this->assertEquals($result['contour'] ?? 'avant', $result['confided'] ?? 'après', 'confié au rectangle, il garde la place de sa fiche et la surface qui sera gardée');

	}

	public function testAClosedContourComesBackFromTheRectangleUntouched(): void {

		$result = $this->run(1512, self::ROW.'
			MapDrawing.start({ dataset: zone(square) });
			const drawing = watch(MapDrawing.current);
			const before = JSON.stringify(drawing.shape.points);

			use(drawing, "rectangle");
			result.drawn = JSON.stringify(drawing.shape.getGeometry()?.coordinates[0].slice(0, -1));

			use(drawing, "free");
			result.back = { points: JSON.stringify(drawing.shape.points), closed: drawing.shape.closed, same: drawing.isSame() };
			result.before = before;

			drawing.cancel();
		');

		$this->assertEquals($result['before'] ?? 'avant', $result['drawn'] ?? 'pendant', 'le contour reste dessiné tant que le rectangle n\'est pas tracé');
		$this->assertEquals(['points' => $result['before'] ?? 'avant', 'closed' => TRUE, 'same' => TRUE], $result['back'] ?? NULL, 'revenu en « Forme libre » sans glisser, le contour est celui qu\'on avait');

	}

	public function testACardButtonThatMovesTheCardDesignatesNothing(): void {

		$result = $this->run(1512, '
			const marker = { classList: { contains: name => name === "mapboxgl-marker" } };
			const card = { classList: { contains: () => false } };
			const button = { classList: { contains: () => false }, closest: () => null };

			// La fiche a quitté le marqueur pendant l\'écouteur du bouton : sa cible n\'a plus le marqueur pour ancêtre
			result.moved = MapCartography.prototype.isMarkerClick({ originalEvent: { target: button, composedPath: () => [button, card, marker] } });
			result.map = MapCartography.prototype.isMarkerClick({ originalEvent: { target: card, composedPath: () => [card] } });
		');

		$this->assertTrue($result['moved'] ?? NULL, 'un clic parti d\'un bouton de la fiche reste celui de la fiche, même déplacée');
		$this->assertFalse($result['map'] ?? NULL, 'un clic sur la carte désigne ce qui est dessous');

	}

	public function testModifyMoveAndRotateAreThreeGestures(): void {

		$result = $this->run(1512, self::OPEN.'
			["edit", "move", "rotate"].forEach(mode => {

				const drawing = open();
				drawing.setMode(mode);

				// Le jardin est un carré de 400 px au centre de la carte (400, 250) : l\'appui part de son haut et va 100 px à droite
				pull(drawing, [400, 150], [500, 150]);

				result[mode] = { handles: drawing.handles.length, center: center(drawing), same: drawing.isSame() };

				drawing.cancel();

			});
		');

		$this->assertTrue(($result['edit']['handles'] ?? 0) > 0, 'en modification, les sommets ont leurs poignées');
		$this->assertTrue(($result['edit']['same'] ?? FALSE) and abs(($result['edit']['center'][0] ?? 0) - 400) < 1, 'en modification, l\'appui sur le jardin ne le déplace pas');
		$this->assertEquals(0, $result['move']['handles'] ?? NULL, 'déplacé, aucun sommet ne se tire');
		$this->assertTrue(abs(($result['move']['center'][0] ?? 0) - 500) < 1, 'déplacé, le jardin suit ce que le pointeur a parcouru');
		$this->assertEquals(0, $result['rotate']['handles'] ?? NULL, 'en rotation, aucun sommet ne se tire');
		$this->assertTrue(abs(($result['rotate']['center'][0] ?? 0) - 400) < 1 and abs(($result['rotate']['center'][1] ?? 0) - 250) < 1, 'en rotation, le jardin tourne sur son centre');
		$this->assertFalse($result['rotate']['same'] ?? TRUE, 'et son contour change');

	}

	public function testTheCopyPreviewIsWhatTheServerSaves(): void {

		$shift = [12.5, -7.25];
		$angle = 33.5;

		$result = $this->run(1512, '
			const copy = new MapDrawingCopy(square);
			copy.origin = MapDrawingCopy.getOrigin(square);
			copy.shift = '.json_encode($shift).';
			copy.angle = '.json_encode($angle).';

			result.origin = copy.origin;
			result.points = copy.transform(square);
			result.body = JSON.parse(copy.getBody().placement);
		');

		$square = [[3.0998, 45.5998], [3.1002, 45.5998], [3.1002, 45.6002], [3.0998, 45.6002], [3.0998, 45.5998]];
		$origin = MapLib::getOrigin($square);
		$points = MapLib::getTransformed($square, $origin, $shift, $angle);

		$this->assertEquals($origin, $result['origin'] ?? NULL, 'l\'aperçu tourne autour du même point que le serveur');

		$gap = max(array_map(fn(array $point, array $preview) => max(abs($point[0] - $preview[0]), abs($point[1] - $preview[1])), $points, $result['points'] ?? array_fill(0, 5, [0, 0])));

		$this->assertTrue($gap < 1e-9, 'la copie que montre le panneau est le contour que le serveur enregistre');
		$this->assertEquals(['shift' => $shift, 'angle' => $angle], $result['body'] ?? NULL, 'et elle part en intention, un décalage et un angle');

	}

	public function testStartingOverGoesBackToEditing(): void {

		$result = $this->run(1512, self::OPEN.'
			const drawing = open();
			drawing.setMode("rotate");
			drawing.useTool({ dataset: { tool: "remove" }, classList: { contains: () => false } });

			result.mode = drawing.mode;
			result.points = drawing.shape.points.length;

			drawing.cancel();
		');

		$this->assertEquals('edit', $result['mode'] ?? NULL, 'un contour recommencé se trace point par point');
		$this->assertEquals(0, $result['points'] ?? NULL, 'sans aucun de ses sommets');

	}

	public function testARowOfBedsOpensOnMove(): void {

		$result = $this->run(1512, '
			MapDrawing.start({ dataset: gestures.beds });
			const drawing = MapDrawing.current;

			result.opened = { mode: drawing.mode, modes: drawing.shape.getModes() };

			// Le rang perd ses planches, qui reviennent : il regagne ses modes, et rouvre sur le premier
			const beds = drawing.shape.beds;
			drawing.setMode("edit");
			drawing.shape.beds = [];
			drawing.refresh();
			drawing.shape.beds = beds;
			drawing.refresh();

			result.regained = drawing.mode;

			drawing.cancel();
		');

		$this->assertEquals('move', $result['opened']['mode'] ?? NULL, 'un rang posé d\'office ouvre sur « Déplacer »');
		$this->assertEquals(['move', 'rotate', 'edit'], $result['opened']['modes'] ?? NULL, 'et ses allées se modifient en dernier');
		$this->assertEquals('move', $result['regained'] ?? NULL, 'un rang qui regagne ses planches rouvre sur « Déplacer »');

	}

	public function testTheCardFollowsTheWidthDuringTheGesture(): void {

		$result = $this->run(1512, self::OPEN.'
			const drawing = open();
			const place = () => ({ posed: drawing.cardPosed, marker: drawing.cardMarker !== null });

			result.wide = place();

			context.window.innerWidth = 360;
			drawing.resize();
			result.narrow = place();

			context.window.innerWidth = 1512;
			drawing.resize();
			result.wideAgain = place();

			drawing.cancel();
		');

		$this->assertEquals(['posed' => TRUE, 'marker' => TRUE], $result['wide'] ?? NULL, 'au-dessus de 767 px, la fiche se pose sur la carte');
		$this->assertEquals(['posed' => FALSE, 'marker' => FALSE], $result['narrow'] ?? NULL, 'la fenêtre réduite pendant le geste, elle passe sous la carte');
		$this->assertEquals(['posed' => TRUE, 'marker' => TRUE], $result['wideAgain'] ?? NULL, 'et revient sur la carte quand la fenêtre s\'élargit');

	}

	public function testAShapeBeingCreatedHasItsIssuesUnderItsName(): void {

		$result = $this->run(1512, self::OPEN.'
			const zone = { drawing: "polygon", drawingMap: "map", drawingSheet: "zone-create", drawingLevel: "plan-console-create", drawingCoordinates: "null", drawingRectangle: "", drawingFree: "" };
			const place = () => ({ posed: MapDrawing.current.cardPosed, issues: MapDrawing.current.issues !== null });

			MapDrawing.start({ dataset: zone });
			result.wide = place();

			context.window.innerWidth = 360;
			MapDrawing.current.resize();
			result.narrow = place();
			MapDrawing.current.cancel();

			context.window.innerWidth = 1512;
			open();
			result.update = place();
			MapDrawing.current.cancel();
		');

		$this->assertEquals(['posed' => TRUE, 'issues' => TRUE], $result['wide'] ?? NULL, 'une parcelle qu\'on crée a ses issues sous son nom, la fiche posée sur la carte');
		$this->assertEquals(['posed' => FALSE, 'issues' => TRUE], $result['narrow'] ?? NULL, 'et les garde sous son nom quand la fenêtre se réduit');
		$this->assertEquals(['posed' => TRUE, 'issues' => FALSE], $result['update'] ?? NULL, 'un contour rouvert garde les siennes dans la rangée');

	}

	public function testAVertexSticksToACornerThenToASide(): void {

		$result = $this->run(1512, '
			MapDrawing.start({ dataset: { drawing: "polygon", drawingMap: "map", drawingTarget: "plot", drawingId: "5", drawingCoordinates: "null", drawingContour: JSON.stringify(square) } });

			const drawing = MapDrawing.current;
			const click = (x, y) => drawing.clickMap({ originalEvent: { detail: 1 }, point: { x, y }, lngLat: { toArray: () => [map.unproject([x, y]).lng, map.unproject([x, y]).lat] } });
			const last = () => { const point = map.project(drawing.shape.points.at(-1)); return [Math.round(point.x * 100) / 100, Math.round(point.y * 100) / 100]; };

			// Le coin haut gauche de la parcelle est en (200, 50)
			click(205, 57);
			result.corner = last();

			// À 6 px du bord haut, à 6 px aussi d\'un sommet posé ailleurs : le sommet l\'emporte
			click(300, 56);
			result.side = last();

			click(400, 80);
			result.free = last();

			drawing.cancel();
		');

		$this->assertEquals([200, 50], $result['corner'] ?? NULL, 'à 9 px du coin de la parcelle, le sommet s\'y pose');
		$this->assertEquals([300, 50], $result['side'] ?? NULL, 'à 6 px de son bord, il se pose dessus');
		$this->assertEquals([400, 80], $result['free'] ?? NULL, 'à 30 px, il reste où on clique');

	}

}
?>
