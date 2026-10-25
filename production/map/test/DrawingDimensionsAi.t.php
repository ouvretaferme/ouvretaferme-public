<?php
namespace map;

/**
 * Les dimensions d'un abri qu'on crée (`static/js/drawing.js`, MapDrawing.changeDimensions() et refreshDimensions()) : saisies
 * dans le formulaire du geste, elles posent le rectangle au milieu de la vue ou le redimensionnent autour de son centre, sans
 * tourner. Le geste les écrit en retour au pas du champ, sauf pendant qu'on tape.
 *
 * Lancement : dev/test package=map file=DrawingDimensionsAi
 */
class DrawingDimensionsAiTest extends DrawingBenchAiTest {

	// Le bloc « Nouvel abri » de la console (Plan.u.php, getMapPlotCreateLevel()), dont le formulaire porte les deux champs
	const string OPEN = '
		const nodes = [
			{ name: "length", dataset: { drawingDimension: "length" }, step: "0.01", value: "", disabled: false },
			{ name: "width", dataset: { drawingDimension: "width" }, step: "0.01", value: "", disabled: false }
		];

		const fallback = { get: (target, key) => (key in target) ? target[key] : any[key] };
		const dimensions = new Proxy({ qs: selector => nodes.find(node => selector.includes("\""+ node.name +"\"")) ?? any, qsa: (selector, callback) => nodes.forEach(callback) }, fallback);
		forms.level = new Proxy({ qs: selector => (selector === "form") ? dimensions : any }, fallback);

		const greenhouse = { drawing: "rectangle", drawingMap: "map", drawingSheet: "plot-create-greenhouse", drawingCoordinates: "null", drawingContour: JSON.stringify(square), drawingDimensions: "", drawingLevel: "level" };

		const type = (length, width) => {
			nodes[0].value = length;
			nodes[1].value = width;
			MapDrawing.current.changeDimensions(dimensions);
		};

		// Le coin tiré de vingt pixels vers l\'est, en « Modifier »
		const pull = (drawing, index) => {
			const from = map.project(drawing.shape.points[index]);
			drawing.startHandle({ button: 0, clientX: from.x, clientY: from.y, preventDefault: () => {} }, { dataset: { drag: "point", index: String(index) } });
			drawing.moveDrag({ clientX: from.x + 20, clientY: from.y });
			drawing.endDrag();
		};

		// Le centre au milliardième de degré, l\'angle du grand côté au millionième de radian, sur le plan en mètres
		const describe = (drawing) => {
			const points = drawing.shape.points;
			const center = [0, 1].map(axis => points.reduce((sum, point) => sum + point[axis], 0) / points.length);
			const [first, second, third] = points.slice(0, 3).map(point => MapDrawing.shapes.rectangle.toPlane(center, point));
			const long = [[second[0] - first[0], second[1] - first[1]], [third[0] - second[0], third[1] - second[1]]].sort((u, v) => Math.hypot(...v) - Math.hypot(...u))[0];
			return {
				closed: drawing.shape.closed,
				dimensions: drawing.shape.getDimensions(),
				center: center.map(value => Math.round(value * 1e9) / 1e9),
				angle: Math.round(((Math.atan2(long[1], long[0]) % Math.PI + Math.PI) % Math.PI) * 1e6) / 1e6,
				fields: nodes.map(node => node.value)
			};
		};
	';

	public function testTypedDimensionsLayTheGreenhouseInTheMiddleOfTheView(): void {

		$result = $this->run(1512, self::OPEN.'
			MapDrawing.start({ dataset: greenhouse });
			const drawing = MapDrawing.current;

			type("30", "");
			result.half = drawing.shape.closed;

			type("30", "8");
			result.laid = describe(drawing);

			drawing.cancel();
		');

		$this->assertFalse($result['half'] ?? TRUE, 'une seule dimension ne pose rien');
		$this->assertTrue($result['laid']['closed'] ?? FALSE, 'les deux posent le rectangle');
		$this->assertEquals([30, 8], $result['laid']['dimensions'] ?? NULL, 'aux dimensions saisies');
		$this->assertEquals([3.1, 45.6], $result['laid']['center'] ?? NULL, 'au milieu de ce que la carte laisse voir');
		$this->assertEquals(0, $result['laid']['angle'] ?? NULL, 'droit sur le nord, la longueur d\'ouest en est');
		$this->assertEquals(['30', '8'], $result['laid']['fields'] ?? NULL, 'les champs gardent ce qu\'on a tapé');

	}

	public function testTypingResizesAroundTheSameCenterAndAngle(): void {

		$result = $this->run(1512, self::OPEN.'
			MapDrawing.start({ dataset: greenhouse });
			const drawing = MapDrawing.current;

			type("30", "8");

			// Le rectangle qu\'une rotation laisse, tourné de 30° autour du centre de la vue
			const turn = Math.PI / 6;
			const [u, v] = [[Math.cos(turn) * 15, Math.sin(turn) * 15], [-Math.sin(turn) * 4, Math.cos(turn) * 4]];
			drawing.shape.points = [[-1, -1], [1, -1], [1, 1], [-1, 1]].map(([a, b]) => MapDrawing.shapes.rectangle.fromPlane([3.1, 45.6], [a * u[0] + b * v[0], a * u[1] + b * v[1]]));
			result.turned = describe(drawing);

			type("40", "10");
			result.resized = describe(drawing);

			drawing.cancel();
		');

		$this->assertEquals([30, 8], $result['turned']['dimensions'] ?? NULL, 'le rectangle tourné garde ses dimensions');
		$this->assertEquals([40, 10], $result['resized']['dimensions'] ?? NULL, 'la saisie le redimensionne');
		$this->assertEquals($result['turned']['center'] ?? NULL, $result['resized']['center'] ?? NULL, 'autour de son centre');
		$this->assertEquals($result['turned']['angle'] ?? NULL, $result['resized']['angle'] ?? NULL, 'sans le faire tourner');

	}

	public function testTheFieldsKeepWhatWasTypedUntilAGesture(): void {

		$result = $this->run(1512, self::OPEN.'
			MapDrawing.start({ dataset: greenhouse });
			const drawing = MapDrawing.current;

			type("20", "10");
			result.laid = describe(drawing);
			nodes.forEach(node => node.disabled = true);

			// Une longueur tapée plus courte que la largeur d\'avant, puis la largeur : le rectangle est celui qu\'on a tapé
			type("8", "10");
			result.length = describe(drawing);
			type("8", "4");
			result.width = describe(drawing);

			// « 0.5 » en route vers « 0.55 » : la forme prend le côté minimal, le champ garde la frappe
			type("0.5", "8");
			result.minimum = describe(drawing);

			// Tirer la forme rend les champs à la forme, au décimètre qu\'elle mesure
			pull(drawing, 1);
			result.gesture = describe(drawing);

			result.disabled = nodes.map(node => node.disabled);

			drawing.cancel();
		');

		$this->assertEquals(['8', '10'], $result['length']['fields'] ?? NULL, 'une saisie ne réécrit aucun champ');
		$this->assertEquals([8, 4], $result['width']['dimensions'] ?? NULL, 'les deux dimensions tapées l\'une après l\'autre font le rectangle');
		$this->assertEquals($result['laid']['angle'] ?? NULL, $result['width']['angle'] ?? NULL, 'sans tourner');
		$this->assertEquals([8, 1], $result['minimum']['dimensions'] ?? NULL, 'la forme ne descend pas sous le côté minimal');
		$this->assertEquals(['0.5', '8'], $result['minimum']['fields'] ?? NULL, 'le champ garde la frappe');
		$this->assertEquals(array_map('strval', $result['gesture']['dimensions'] ?? []), $result['gesture']['fields'] ?? NULL, 'un geste écrit les champs d\'après la forme');
		$this->assertDiffer([8, 1], $result['gesture']['dimensions'] ?? NULL, 'qu\'il a changée');
		$this->assertEquals([TRUE, TRUE], $result['disabled'] ?? NULL, 'sans toucher à leur état');

	}

	public function testRetypingAShorterLengthKeepsTheAngle(): void {

		$result = $this->run(1512, self::OPEN.'
			MapDrawing.start({ dataset: greenhouse });
			const drawing = MapDrawing.current;

			type("20", "10");

			// Tourné de 30° autour de son centre, tiré par un coin, puis remis à 20 × 10
			const turn = Math.PI / 6;
			const [u, v] = [[Math.cos(turn) * 10, Math.sin(turn) * 10], [-Math.sin(turn) * 5, Math.cos(turn) * 5]];
			drawing.shape.points = [[-1, -1], [1, -1], [1, 1], [-1, 1]].map(([a, b]) => MapDrawing.shapes.rectangle.fromPlane([3.1, 45.6], [a * u[0] + b * v[0], a * u[1] + b * v[1]]));
			pull(drawing, 2);
			type("20", "10");
			result.turned = describe(drawing);

			// La longueur retapée « 15 », chiffre par chiffre
			type("1", "10");
			type("15", "10");
			result.retyped = describe(drawing);

			drawing.cancel();
		');

		$this->assertEquals([20, 10], $result['turned']['dimensions'] ?? NULL, 'le rectangle tourné reprend la saisie');
		$this->assertEquals([15, 10], $result['retyped']['dimensions'] ?? NULL, 'la longueur retapée');
		$this->assertEquals($result['turned']['angle'] ?? NULL, $result['retyped']['angle'] ?? NULL, 'reste sur le même côté pendant la frappe');

	}

	public function testStartingOverForgetsTheSideOfTheLength(): void {

		$result = $this->run(1512, self::OPEN.'
			MapDrawing.start({ dataset: greenhouse });
			const drawing = MapDrawing.current;

			// Un rectangle haut du nord au sud, dont la longueur se retape sur le côté nord-sud
			drawing.shape.points = [[-4, -15], [4, -15], [4, 15], [-4, 15]].map(offset => MapDrawing.shapes.rectangle.fromPlane([3.1, 45.6], offset));
			drawing.shape.closed = true;
			type("30", "8");
			result.tall = describe(drawing);

			// « Recommencer », puis un abri tapé
			drawing.useTool({ dataset: { tool: "remove" }, classList: { contains: () => false } });
			type("30", "8");
			result.again = describe(drawing);

			drawing.cancel();
		');

		$this->assertEquals([30, 8], $result['tall']['dimensions'] ?? NULL, 'le rectangle haut garde sa longueur');
		$this->assertEquals(1.570796, $result['tall']['angle'] ?? NULL, 'du nord au sud');
		$this->assertEquals([30, 8], $result['again']['dimensions'] ?? NULL, 'l\'abri tapé après « Recommencer »');
		$this->assertEquals(0, $result['again']['angle'] ?? NULL, 'se pose droit sur le nord, la longueur d\'ouest en est');

	}

	public function testTypedMetersAreTheMetersMeasured(): void {

		$result = $this->run(1512, self::OPEN.'
			MapDrawing.start({ dataset: greenhouse });
			const drawing = MapDrawing.current;

			type("50", "8");
			result.laid = describe(drawing);

			// Une saisie reposée, comme au retour de « Dessiner plus tard », ne fait pas dériver la forme
			[1, 2, 3, 4, 5].forEach(() => type("100", "8"));
			result.again = describe(drawing);

			drawing.cancel();
		');

		$this->assertEquals([50, 8], $result['laid']['dimensions'] ?? NULL, '50 m tapés mesurent 50 m, comme le serveur les mesurera');
		$this->assertEquals([100, 8], $result['again']['dimensions'] ?? NULL, 'et ne perdent rien à chaque saisie reposée');

	}

	public function testAnotherFormLeavesTheShapeAlone(): void {

		$result = $this->run(1512, self::OPEN.'
			MapDrawing.start({ dataset: greenhouse });
			const drawing = MapDrawing.current;

			nodes[0].value = "30";
			nodes[1].value = "8";
			drawing.changeDimensions(new Proxy({}, fallback));
			result.closed = drawing.shape.closed;

			drawing.cancel();
		');

		$this->assertFalse($result['closed'] ?? TRUE, 'les dimensions d\'un autre formulaire ne posent rien');

	}

}
?>
