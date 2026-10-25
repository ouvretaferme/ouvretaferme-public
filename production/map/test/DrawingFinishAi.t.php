<?php
namespace map;

/**
 * Comment on sort d'un geste de la carte (`static/js/drawing.js`, `MapDrawing`) sans passer par « Terminer ». Sur une forme
 * qu'on modifie, un clic simple hors de la forme enregistre, et sur un autre jardin le désigne une fois la forme enregistrée ;
 * le retour du bloc enregistre puis fait son chemin. Une forme qu'on crée ne s'enregistre que par son bouton : le clic à côté
 * n'y fait rien, et le retour la quitte comme « Annuler ». Un clic au bout d'un glisser, ou sur la forme, n'enregistre rien, et
 * un refus garde le geste ouvert. Le rang se prend par toute son emprise, ses allées comprises. Une forme qui ne peut pas
 * s'enregistrer ne part pas : « Enregistrer » oppose sans requête le refus que porte le lien, qui part au premier point posé, et un clic à côté n'en dit rien.
 * Un contour passé en rectangle reste la forme qui part tant qu'aucun rectangle n'est commencé.
 *
 * Lancement : dev/test package=map file=DrawingFinishAi
 */
class DrawingFinishAiTest extends DrawingBenchAiTest {

	// Ouvre le rang des planches cochées, avec un bouton « Terminer » prêt, et clique au point (x, y) de la carte. Le même rang
	// ouvert depuis un bloc où l'on nomme la forme (`named`) se crée, même à sa place
	const string OPEN = '
		const open = (dataset = gestures.beds) => {
			MapDrawing.start({ dataset });
			const button = { dataset: {}, classList: { contains: () => false, toggle: () => null }, qs: () => ({ textContent: "" }) };
			MapDrawing.current.getFinishButtons = () => [button];
			// Un rang rouvert à sa place est réputé avoir bougé : il a de quoi s\'enregistrer
			MapDrawing.current.before = null;
			return MapDrawing.current;
		};

		const click = (drawing, x, y) => {
			const at = map.unproject([x, y]);
			drawing.clickMap({ originalEvent: { detail: 1 }, point: { x, y }, lngLat: { lng: at.lng, lat: at.lat, toArray: () => [at.lng, at.lat] } });
		};

		const wait = () => new Promise(resolve => setTimeout(resolve, 0));

		const named = { ...gestures.beds, drawingLevel: "plan-console-create" };

		// Les planches cochées prennent la place où le rang les pose : rouvertes, elles se modifient au lieu de se créer
		const place = () => {
			const drawing = open();
			const places = drawing.shape.getRows().polygons.map(polygon => MapDrawingLine.getCanonical(polygon));
			drawing.cancel();
			checked.forEach((input, position) => {
				input.dataset.corner = JSON.stringify(places[position].corner);
				input.dataset.angle = JSON.stringify(places[position].angle);
			});
		};
	';

	public function testAClickBesideTheShapeSavesIt(): void {

		$result = $this->run(1512, self::OPEN.'
			place();
			answer = { __redirect: "/" };

			// Hors du jardin, en haut à gauche de la carte
			click(open(), 20, 20);
			await wait();
			result.beside = { posts: posts.length, closed: MapDrawing.current === null };
		');

		$this->assertEquals(1, $result['beside']['posts'] ?? NULL, 'un clic à côté du rang l\'enregistre');
		$this->assertTrue($result['beside']['closed'] ?? FALSE, 'et quitte le geste');

	}

	public function testAClickOnAnotherPlotSavesThenSelectsIt(): void {

		$result = $this->run(1512, self::OPEN.'
			place();
			answer = { __redirect: "/" };
			rendered = (point, options) => options.layers.includes("plot-fill") ? [{ properties: { id: 7 } }] : [];
			carto.plotsZones = { 7: 3 };
			carto.clickPlot = (plotId, zoneId) => result.selected = [plotId, zoneId];

			click(open(), 20, 20);
			await wait();
			result.posts = posts.length;
		');

		$this->assertEquals(1, $result['posts'] ?? NULL, 'un clic sur un autre jardin enregistre d\'abord');
		$this->assertEquals([7, 3], $result['selected'] ?? NULL, 'puis le désigne');

	}

	public function testAClickThatIsNotOneSavesNothing(): void {

		$result = $this->run(1512, self::OPEN.'
			place();
			answer = { __redirect: "/" };

			const drawing = open();

			// Au bout d\'un glisser
			drawing.dragged = true;
			click(drawing, 20, 20);
			drawing.dragged = false;
			await wait();
			result.dragged = { posts: posts.length, open: MapDrawing.current === drawing };

			// Au milieu du rang
			const pivot = map.project(drawing.shape.getPivot());
			click(drawing, pivot.x, pivot.y);
			await wait();
			result.inside = { posts: posts.length, open: MapDrawing.current === drawing };

			drawing.cancel();
		');

		$this->assertEquals(0, $result['dragged']['posts'] ?? NULL, 'un glisser n\'enregistre rien');
		$this->assertTrue($result['dragged']['open'] ?? FALSE, 'et laisse le geste ouvert');
		$this->assertEquals(0, $result['inside']['posts'] ?? NULL, 'un clic sur le rang n\'enregistre rien');
		$this->assertTrue($result['inside']['open'] ?? FALSE, 'et laisse le geste ouvert');

	}

	public function testARefusalKeepsTheGesture(): void {

		$result = $this->run(1512, self::OPEN.'
			place();
			// Un refus répond sans coordonnées
			answer = {};

			const drawing = open();
			click(drawing, 20, 20);
			await wait();

			result.posts = posts.length;
			result.open = (MapDrawing.current === drawing);

			drawing.cancel();
		');

		$this->assertEquals(1, $result['posts'] ?? NULL, 'le clic à côté envoie le rang');
		$this->assertTrue($result['open'] ?? FALSE, 'refusé, le geste reste ouvert');

	}

	public function testTheBackButtonSavesThenGoesUp(): void {

		$result = $this->run(1512, self::OPEN.'
			place();
			const back = listeners.find(listener => listener.type === "click" && listener.capture === true).listener;

			const press = () => {
				const up = { clicked: 0, click() { this.clicked++; } };
				const event = { target: { closest: selector => (selector === ".plan-console-up a") ? up : null }, stopped: false, preventDefault() {}, stopImmediatePropagation() { this.stopped = true; } };
				back(event);
				return { up, event };
			};

			answer = { __redirect: "/" };
			open();
			const saved = press();
			await wait();
			result.saved = { posts: posts.length, stopped: saved.event.stopped, clicked: saved.up.clicked, closed: MapDrawing.current === null };

			answer = {};
			const drawing = open();
			const refused = press();
			await wait();
			result.refused = { posts: posts.length, clicked: refused.up.clicked, open: MapDrawing.current === drawing };
			drawing.cancel();

			// Une forme qui ne peut pas s\'enregistrer
			const empty = open();
			empty.shape.isReady = () => false;
			const left = press();
			result.empty = { posts: posts.length, clicked: left.up.clicked, closed: MapDrawing.current === null };

			result.idle = press().event.stopped;
		');

		$this->assertTrue($result['saved']['stopped'] ?? FALSE, 'pendant un geste, le retour ne fait pas son chemin tout de suite');
		$this->assertEquals(1, $result['saved']['posts'] ?? NULL, 'il enregistre d\'abord');
		$this->assertTrue($result['saved']['closed'] ?? FALSE, 'quitte le geste');
		$this->assertEquals(1, $result['saved']['clicked'] ?? NULL, 'puis remonte');

		$this->assertEquals(2, $result['refused']['posts'] ?? NULL, 'refusé');
		$this->assertEquals(0, $result['refused']['clicked'] ?? NULL, 'il ne remonte pas');
		$this->assertTrue($result['refused']['open'] ?? FALSE, 'et le geste reste ouvert');

		$this->assertEquals(2, $result['empty']['posts'] ?? NULL, 'une forme qui ne peut pas s\'enregistrer n\'envoie rien');
		$this->assertTrue($result['empty']['closed'] ?? FALSE, 'le retour la quitte');
		$this->assertEquals(1, $result['empty']['clicked'] ?? NULL, 'et remonte');

		$this->assertFalse($result['idle'] ?? TRUE, 'hors geste, le retour fait son chemin sans attendre');

	}

	public function testACreationIsTheShapeNamedInABlockOrABedNotYetPlaced(): void {

		$result = $this->run(1512, self::OPEN.'
			const creating = (dataset) => {
				MapDrawing.start({ dataset });
				const value = MapDrawing.current.creating;
				MapDrawing.current.cancel();
				return value;
			};

			result.described = creating(gestures.bedsCreate);
			result.first = creating(gestures.beds);

			place();
			result.placed = creating(gestures.beds);
			result.named = creating(named);
		');

		$this->assertTrue($result['named'] ?? FALSE, 'une forme nommée dans un bloc se crée');
		$this->assertTrue($result['described'] ?? FALSE, 'un rang de planches à créer se crée');
		$this->assertTrue($result['first'] ?? FALSE, 'un rang dont aucune planche n\'a sa place se crée');
		$this->assertFalse($result['placed'] ?? TRUE, 'un rang de planches à leur place se modifie');

	}

	public function testAClickBesideACreationSavesNothing(): void {

		$result = $this->run(1512, self::OPEN.'
			answer = { __redirect: "/" };
			rendered = (point, options) => options.layers.includes("plot-fill") ? [{ properties: { id: 7 } }] : [];
			carto.plotsZones = { 7: 3 };
			carto.clickPlot = (plotId, zoneId) => result.selected = [plotId, zoneId];

			const beside = (dataset) => {
				const drawing = open(dataset);
				click(drawing, 20, 20);
				return wait().then(() => {
					const kept = (MapDrawing.current === drawing);
					drawing.cancel();
					return kept;
				});
			};

			result.first = await beside(gestures.beds);
			place();
			result.named = await beside(named);
			result.posts = posts.length;
		');

		$this->assertEquals(0, $result['posts'] ?? NULL, 'un clic à côté d\'une forme qu\'on crée ne l\'enregistre pas');
		$this->assertEquals(NULL, $result['selected'] ?? NULL, 'ne désigne pas le jardin cliqué');
		$this->assertTrue($result['first'] ?? FALSE, 'et laisse ouvert le premier tracé d\'une planche');
		$this->assertTrue($result['named'] ?? FALSE, 'comme une forme nommée dans un bloc');

	}

	public function testTheBackButtonLeavesACreationWithoutSaving(): void {

		$result = $this->run(1512, self::OPEN.'
			const back = listeners.find(listener => listener.type === "click" && listener.capture === true).listener;

			answer = { __redirect: "/" };
			const up = { clicked: 0, click() { this.clicked++; } };

			const leave = (dataset) => {
				open(dataset);
				back({ target: { closest: selector => (selector === ".plan-console-up a") ? up : null }, preventDefault() {}, stopImmediatePropagation() {} });
				return wait().then(() => MapDrawing.current === null);
			};

			result.first = await leave(gestures.beds);
			place();
			result.named = await leave(named);
			result.posts = posts.length;
			result.clicked = up.clicked;
		');

		$this->assertEquals(0, $result['posts'] ?? NULL, 'le retour n\'enregistre pas une forme qu\'on crée');
		$this->assertTrue($result['first'] ?? FALSE, 'il quitte le premier tracé d\'une planche');
		$this->assertTrue($result['named'] ?? FALSE, 'comme une forme nommée dans un bloc');
		$this->assertEquals(2, $result['clicked'] ?? NULL, 'puis remonte');

	}

	public function testAShapeNotReadyIsRefusedWithoutAQuery(): void {

		$result = $this->run(1512, self::OPEN.'
			const refusals = [];
			let cleared = 0;
			context.Lime = new Proxy({}, { get: (target, key) => (key === "Alert") ? { showErrors: (node, errors) => refusals.push(errors), hideErrors: () => {}, hideStaticErrors: () => cleared++ } : any });

			const refuse = { drawingErrorName: "Zone::coordinates.drawingMap", drawingError: "refus" };

			// Une parcelle qu\'on crée, sans rien de posé, puis avec deux sommets
			open({ drawing: "polygon", drawingMap: "map", drawingSheet: "zone-create", drawingUrl: "/map/zone:doCreate", drawingCoordinates: "null", drawingRectangle: "", drawingFree: "", ...refuse });
			const drawing = MapDrawing.current;
			result.nothing = { saved: await drawing.finish(), posts: posts.length, open: MapDrawing.current === drawing };

			click(drawing, 100, 100);
			click(drawing, 300, 100);
			result.open = { saved: await drawing.finish(), posts: posts.length };
			cleared = 0;
			drawing.cancel();
			result.cleared = cleared;

			// Un jardin recommencé
			open({ drawing: "polygon", drawingMap: "map", drawingSheet: "plot", drawingUrl: "/map/plot:doUpdateShape", drawingId: "5", drawingCoordinates: JSON.stringify(square), drawingErrorName: "Plot::coordinates.redraw", drawingError: "refus" });
			MapDrawing.current.shape.tool("remove", null, null);
			result.restarted = { saved: await MapDrawing.current.finish(), posts: posts.length };
			MapDrawing.current.cancel();

			result.refusals = refusals;
		');

		$this->assertEquals(['saved' => FALSE, 'posts' => 0, 'open' => TRUE], $result['nothing'] ?? NULL, 'une parcelle sans rien de posé ne part pas, et le geste reste ouvert');
		$this->assertEquals(['saved' => FALSE, 'posts' => 0], $result['open'] ?? NULL, 'à deux sommets non plus');
		$this->assertEquals(['saved' => FALSE, 'posts' => 0], $result['restarted'] ?? NULL, 'un jardin recommencé ne s\'enregistre pas vide');
		$this->assertEquals([
			[['Zone::coordinates.drawingMap', 'refus', 'coordinates']],
			[['Zone::coordinates.drawingMap', 'refus', 'coordinates']],
			[['Plot::coordinates.redraw', 'refus', 'coordinates']]
		], $result['refusals'] ?? NULL, 'chaque refus est celui que porte le lien, rattaché au tracé');
		$this->assertEquals(1, $result['cleared'] ?? NULL, 'et il quitte la page avec le geste qu\'il refusait');

	}

	public function testARefusalLeavesAtTheFirstPoint(): void {

		$result = $this->run(1512, self::OPEN.'
			let cleared = 0;
			context.Lime = new Proxy({}, { get: (target, key) => (key === "Alert") ? { showErrors: () => {}, hideErrors: () => {}, hideStaticErrors: () => cleared++ } : any });

			open({ drawing: "polygon", drawingMap: "map", drawingSheet: "zone-create", drawingUrl: "/map/zone:doCreate", drawingCoordinates: "null", drawingRectangle: "", drawingFree: "", drawingErrorName: "Zone::coordinates.drawingMap", drawingError: "refus" });
			const drawing = MapDrawing.current;

			await drawing.finish();
			drawing.refresh();
			result.refused = cleared;

			click(drawing, 100, 100);
			result.point = cleared;

			click(drawing, 300, 100);
			result.next = cleared;

			// Refusé de nouveau à deux sommets, il repart au suivant
			await drawing.finish();
			click(drawing, 300, 300);
			result.again = cleared;

			drawing.cancel();
		');

		$this->assertEquals(0, $result['refused'] ?? NULL, 'le refus reste tant que rien n\'est posé');
		$this->assertEquals(1, $result['point'] ?? NULL, 'il part au premier point');
		$this->assertEquals(1, $result['next'] ?? NULL, 'une seule fois');
		$this->assertEquals(2, $result['again'] ?? NULL, 'un nouveau refus part au point suivant');

	}

	public function testAContourLeftForARectangleIsTheShapeThatLeaves(): void {

		$result = $this->run(1512, self::OPEN.'
			// Une parcelle refermée en contour, puis passée en rectangle sans qu\'aucun ne soit commencé
			open({ drawing: "polygon", drawingMap: "map", drawingSheet: "zone-create", drawingUrl: "/map/zone:doCreate", drawingCoordinates: "null", drawingRectangle: "", drawingFree: "", drawingErrorName: "Zone::coordinates.drawingMap", drawingError: "refus" });
			const drawing = MapDrawing.current;

			click(drawing, 100, 100);
			click(drawing, 300, 100);
			click(drawing, 300, 300);
			drawing.close();
			const contour = drawing.shape.points.map(point => [...point]);

			drawing.useTool({ dataset: { tool: "rectangle" }, classList: { contains: () => false } });

			answer = { __redirect: "/" };
			result.shape = drawing.shape.constructor.name;
			result.saved = await drawing.finish();
			result.coordinates = JSON.parse(posts[0]?.coordinates ?? "null");
			result.contour = contour;
			result.area = posts[0]?.area ?? null;
		');

		$this->assertEquals('MapDrawingRectangle', $result['shape'] ?? NULL, 'la forme tracée est le rectangle');
		$this->assertTrue($result['saved'] ?? FALSE, 'pas encore commencé, il laisse partir la parcelle');
		$this->assertEquals($result['contour'] ?? 'absent', $result['coordinates'] ?? NULL, 'sous le contour resté à l\'écran');
		$this->assertTrue(($result['area'] ?? 0) > 0, 'avec sa surface');

	}

	public function testAClickBesideARowWithoutBedsSaysNothing(): void {

		$result = $this->run(1512, self::OPEN.'
			const refusals = [];
			let cleared = 0;
			context.Lime = new Proxy({}, { get: (target, key) => (key === "Alert") ? { showErrors: (node, errors) => refusals.push(errors), hideErrors: () => {}, hideStaticErrors: () => cleared++ } : any });

			const drawing = open({ ...gestures.beds, drawingErrorName: "Draw::corner.placed", drawingError: "refus" });
			drawing.shape.beds = [];
			drawing.refresh();

			click(drawing, 20, 20);
			await wait();

			result.beside = { posts: posts.length, refusals: refusals.length, open: MapDrawing.current === drawing };

			drawing.cancel();
		');

		$this->assertEquals(['posts' => 0, 'refusals' => 0, 'open' => TRUE], $result['beside'] ?? NULL, 'un clic à côté d\'un rang sans planche n\'enregistre rien et n\'oppose aucun refus');

	}

	public function testARowIsTakenByItsAlleys(): void {

		$result = $this->run(1512, self::OPEN.'
			const drawing = open();

			// Le milieu de l\'allée entre les deux premières planches
			const [first, second] = drawing.shape.getRows().polygons.map(polygon => turf.centroid(polygon).geometry.coordinates);
			const alley = [(first[0] + second[0]) / 2, (first[1] + second[1]) / 2];
			const point = map.project(alley);

			result.inBed = drawing.shape.getRows().polygons.some(polygon => turf.booleanPointInPolygon(alley, polygon));

			const press = () => {
				let prevented = false;
				drawing.startBody({ point, lngLat: { lng: alley[0], lat: alley[1], toArray: () => alley } , preventDefault: () => prevented = true }, { button: 0, clientX: point.x, clientY: point.y, preventDefault: () => {} });
				return { drag: drawing.drag?.type ?? null, prevented: prevented };
			};

			// Un rang de plusieurs planches qu\'on modifie règle ses allées : l\'appui y laisse glisser la carte
			drawing.setMode("edit");
			result.edit = press();

			drawing.setMode("move");
			Object.assign(result, press());

			drawing.cancel();
		');

		$this->assertFalse($result['inBed'] ?? TRUE, 'le point est dans une allée, sur aucune planche');
		$this->assertEquals(['drag' => NULL, 'prevented' => FALSE], $result['edit'] ?? NULL, 'en modification, l\'appui ne prend pas le rang et la carte glisse');
		$this->assertEquals('body', $result['drag'] ?? NULL, 'déplacé, un appui dans l\'allée prend le rang');
		$this->assertTrue($result['prevented'] ?? FALSE, 'et la carte ne glisse pas dessous');

	}

}
?>
