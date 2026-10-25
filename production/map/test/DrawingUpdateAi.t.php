<?php
namespace map;

/**
 * Un abri qu'on modifie sur sa carte (`static/js/drawing.js`, MapDrawing.beginShape() et MapDrawingPolygon.getDimensions()) :
 * tracé d'une autre forme qu'un rectangle, il s'ouvre en contour, écrit ses dimensions sans les laisser saisir, et celles qu'il
 * écrit sont celles que le serveur lira sur le même contour (MapLib::getDimensions()). Repris en rectangle par l'outil Rectangle,
 * il se trace à neuf et ses dimensions se saisissent de nouveau. Un abri rectangle s'ouvre en rectangle, qui garde son centre et
 * son angle quand on retape ses dimensions.
 *
 * Lancement : dev/test package=map file=DrawingUpdateAi
 */
class DrawingUpdateAiTest extends DrawingBenchAiTest {

	// Un trapèze refermé, dont aucun angle n'est droit hors de sa base
	const array TRAPEZOID = [[3.0999, 45.5999], [3.1001, 45.5999], [3.10015, 45.6001], [3.0999, 45.6001], [3.0999, 45.5999]];

	// Le formulaire du geste, dont les deux champs portent `data-drawing-dimension`, comme celui du panneau (PlotUi::update())
	protected function open(): string {

		return '
		const nodes = [
			{ name: "length", dataset: { drawingDimension: "length" }, step: "0.01", value: "", disabled: false, readOnly: false },
			{ name: "width", dataset: { drawingDimension: "width" }, step: "0.01", value: "", disabled: false, readOnly: false }
		];

		const fallback = { get: (target, key) => (key in target) ? target[key] : any[key] };
		const dimensions = new Proxy({ qs: selector => nodes.find(node => selector.includes("\""+ node.name +"\"")) ?? any, qsa: (selector, callback) => nodes.forEach(callback) }, fallback);
		forms.level = new Proxy({ qs: selector => (selector === "form") ? dimensions : any }, fallback);

		const trapezoid = '.json_encode(self::TRAPEZOID).';

		const greenhouse = (drawing, coordinates, rectangle) => ({ drawing, drawingMap: "map", drawingCoordinates: JSON.stringify(coordinates), drawingContour: JSON.stringify(square), drawingDimensions: "", drawingLevel: "level", ...(rectangle ? { drawingRectangle: "" } : {}) });

		const type = (length, width) => {
			nodes[0].value = length;
			nodes[1].value = width;
			MapDrawing.current.changeDimensions(dimensions);
		};

		const read = (drawing) => ({
			shape: drawing.shape.constructor.name,
			closed: drawing.shape.closed,
			points: drawing.shape.points,
			dimensions: drawing.shape.getDimensions?.() ?? null,
			tools: drawing.shape.getTools(),
			fields: nodes.map(node => node.value),
			readOnly: nodes.map(node => node.readOnly)
		});
	';

	}

	public function testAnotherShapeWritesItsDimensionsWithoutTakingThem(): void {

		$result = $this->run(1512, $this->open().'
			MapDrawing.start({ dataset: greenhouse("polygon", trapezoid, true) });
			const drawing = MapDrawing.current;

			drawing.refresh();
			result.opened = read(drawing);

			// Une saisie forcée ne change rien à un contour qui n\'a pas de dimensions à recevoir
			type("50", "8");
			result.typed = read(drawing);

			drawing.cancel();
		');

		$this->assertEquals('MapDrawingPolygon', $result['opened']['shape'] ?? NULL, 'le contour s\'ouvre tel qu\'il est');
		$this->assertEquals(4, count($result['opened']['points'] ?? []), 'sur ses quatre sommets');
		$this->assertEquals(['rectangle', 'rotate', 'remove'], $result['opened']['tools'] ?? NULL, 'repris en rectangle par un outil');
		$this->assertEquals([TRUE, TRUE], $result['opened']['readOnly'] ?? NULL, 'ses dimensions ne se saisissent pas');
		$this->assertEquals(array_map('strval', MapLib::getDimensions(self::TRAPEZOID)), $result['opened']['fields'] ?? NULL, 'il les écrit comme le serveur les lira');
		$this->assertEquals($result['opened']['points'] ?? NULL, $result['typed']['points'] ?? NULL, 'une saisie ne le déforme pas');

	}

	public function testTheDimensionsReadInTheBrowserAreThoseOfTheServer(): void {

		$result = $this->run(1512, $this->open().'
			const measure = (coordinates) => {
				MapDrawing.start({ dataset: greenhouse("polygon", coordinates, true) });
				const dimensions = MapDrawing.current.shape.getDimensions();
				MapDrawing.current.cancel();
				return dimensions;
			};

			// Un rectangle tourné de 30° autour du centre de la carte, refermé
			const turn = Math.PI / 6;
			const [u, v] = [[Math.cos(turn) * 12, Math.sin(turn) * 12], [-Math.sin(turn) * 4, Math.cos(turn) * 4]];
			const turned = [[-1, -1], [1, -1], [1, 1], [-1, 1], [-1, -1]].map(([a, b]) => MapDrawing.shapes.rectangle.fromPlane([3.1, 45.6], [a * u[0] + b * v[0], a * u[1] + b * v[1]]));

			const hexagon = [[3.0999, 45.6], [3.09995, 45.5999], [3.10005, 45.5999], [3.1001, 45.6], [3.10005, 45.6001], [3.09995, 45.6001], [3.0999, 45.6]];

			result.shapes = [[trapezoid, measure(trapezoid)], [turned, measure(turned)], [hexagon, measure(hexagon)]];
		');

		$this->assertEquals(3, count($result['shapes'] ?? []), 'les trois contours sont mesurés');

		foreach($result['shapes'] ?? [] as [$coordinates, $dimensions]) {
			$this->assertEquals(MapLib::getDimensions($coordinates), array_map('floatval', $dimensions), 'les dimensions d\'un contour de '.(count($coordinates) - 1).' sommets');
		}

		$this->assertEquals([24, 8], $result['shapes'][1][1] ?? NULL, 'un rectangle tourné garde ses côtés');

	}

	public function testTheRectangleToolMakesTheDimensionsTypeable(): void {

		$result = $this->run(1512, $this->open().'
			MapDrawing.start({ dataset: greenhouse("polygon", trapezoid, true) });
			const drawing = MapDrawing.current;

			drawing.useTool({ dataset: { tool: "rectangle" }, classList: { contains: () => false } });
			result.tool = read(drawing);

			type("25", "9");
			result.typed = read(drawing);

			drawing.cancel();
		');

		$this->assertEquals('MapDrawingRectangle', $result['tool']['shape'] ?? NULL, 'l\'outil donne un rectangle');
		$this->assertFalse($result['tool']['closed'] ?? TRUE, 'à tracer à neuf, sans redresser le contour');
		$this->assertEquals([FALSE, FALSE], $result['tool']['readOnly'] ?? NULL, 'dont les dimensions se saisissent');
		$this->assertTrue($result['typed']['closed'] ?? FALSE, 'la saisie pose le rectangle');
		$this->assertEquals([25, 9], $result['typed']['dimensions'] ?? NULL, 'aux dimensions saisies');

	}

	public function testARectangleStartedByItsDimensionsLeavesTheContourBehind(): void {

		$result = $this->run(1512, $this->open().'
			MapDrawing.start({ dataset: greenhouse("polygon", trapezoid, true) });
			const drawing = MapDrawing.current;

			drawing.useTool({ dataset: { tool: "rectangle" }, classList: { contains: () => false } });
			result.confided = drawing.shape.getGeometry() !== null;

			type("25", "9");
			drawing.shape.tool("remove", null, null);
			result.restarted = (drawing.shape.getGeometry() === null);

			drawing.cancel();
		');

		$this->assertTrue($result['confided'] ?? FALSE, 'le contour reste dessiné tant que le rectangle n\'est pas commencé');
		$this->assertTrue($result['restarted'] ?? FALSE, 'des dimensions saisies le commencent : recommencé, il ne ramène pas le contour');

	}

	public function testARectangleWithoutDimensionsIgnoresTheForm(): void {

		$result = $this->run(1512, $this->open().'
			// Un jardin ou une parcelle tracés en rectangle : leur formulaire n\'a pas de champs de dimensions
			const bare = new Proxy({ qs: selector => selector.includes("data-drawing-dimension") ? null : any, qsa: () => {} }, fallback);
			forms.level = new Proxy({ qs: selector => (selector === "form") ? bare : any }, fallback);

			const vertices = [[3.0999, 45.5999], [3.1001, 45.5999], [3.1001, 45.6001], [3.0999, 45.6001]];
			MapDrawing.start({ dataset: { drawing: "rectangle", drawingMap: "map", drawingCoordinates: JSON.stringify(vertices), drawingContour: JSON.stringify(square), drawingLevel: "level" } });

			try {
				MapDrawing.current.changeDimensions(bare);
				result.error = "";
			} catch(error) {
				result.error = error.message;
			}

			result.points = MapDrawing.current.shape.points;
			MapDrawing.current.cancel();
		');

		$this->assertEquals('', $result['error'] ?? 'absent', 'le retour au dessin ne lit pas de dimensions qui n\'existent pas');
		$this->assertEquals(4, count($result['points'] ?? []), 'et le rectangle reste tel qu\'il est tracé');

	}

	public function testARectangleKeepsItsCenterAndAngle(): void {

		$result = $this->run(1512, $this->open().'
			// Les quatre sommets que la page prépare sur un rectangle tourné de 30°
			const turn = Math.PI / 6;
			const [u, v] = [[Math.cos(turn) * 10, Math.sin(turn) * 10], [-Math.sin(turn) * 4, Math.cos(turn) * 4]];
			const vertices = [[-1, -1], [1, -1], [1, 1], [-1, 1]].map(([a, b]) => MapDrawing.shapes.rectangle.fromPlane([3.1, 45.6], [a * u[0] + b * v[0], a * u[1] + b * v[1]]));

			MapDrawing.start({ dataset: greenhouse("rectangle", vertices, false) });
			const drawing = MapDrawing.current;

			const describe = () => {
				const points = drawing.shape.points;
				const center = [0, 1].map(axis => points.reduce((sum, point) => sum + point[axis], 0) / points.length);
				const [first, second] = points.slice(0, 2).map(point => MapDrawing.shapes.rectangle.toPlane(center, point));
				return {
					center: center.map(value => Math.round(value * 1e9) / 1e9),
					angle: Math.round(((Math.atan2(second[1] - first[1], second[0] - first[0]) % Math.PI + Math.PI) % Math.PI) * 1e6) / 1e6
				};
			};

			result.opened = { ...read(drawing), ...describe() };

			type("30", "6");
			result.typed = { ...read(drawing), ...describe() };

			drawing.cancel();
		');

		$this->assertEquals([20, 8], $result['opened']['dimensions'] ?? NULL, 'le rectangle s\'ouvre à ses dimensions');
		$this->assertEquals([FALSE, FALSE], $result['opened']['readOnly'] ?? NULL, 'qui se saisissent');
		$this->assertEquals([30, 6], $result['typed']['dimensions'] ?? NULL, 'la saisie le redimensionne');
		$this->assertEquals($result['opened']['center'] ?? NULL, $result['typed']['center'] ?? NULL, 'autour de son centre');
		$this->assertEquals($result['opened']['angle'] ?? NULL, $result['typed']['angle'] ?? NULL, 'sans le faire tourner');

	}

}
?>
