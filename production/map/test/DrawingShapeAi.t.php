<?php
namespace map;

/**
 * La forme d'une parcelle ou d'un jardin qu'on crée (`static/js/drawing.js`) : un contour posé point par point, que la paire
 * « Forme libre » / « Rectangle » de la rangée change en rectangle et ramène au contour. La forme qui en remplace une
 * autre relit le lien, donc un jardin garde le contour de sa parcelle où s'aimanter. Un abri reste un rectangle.
 *
 * Lancement : dev/test package=map file=DrawingShapeAi
 */
class DrawingShapeAiTest extends DrawingBenchAiTest {

	// Les liens de Plan.u.php : la parcelle et le jardin s'ouvrent en contour, l'abri en rectangle
	const string OPEN = '
		const zone = { drawing: "polygon", drawingMap: "map", drawingSheet: "zone-create", drawingCoordinates: "null", drawingRectangle: "", drawingFree: "" };
		const plot = { drawing: "polygon", drawingMap: "map", drawingSheet: "plot-create-open-field", drawingCoordinates: "null", drawingContour: JSON.stringify(square), drawingRectangle: "", drawingFree: "" };
		const greenhouse = { drawing: "rectangle", drawingMap: "map", drawingSheet: "plot-create-greenhouse", drawingCoordinates: "null", drawingContour: JSON.stringify(square), drawingDimensions: "" };

		const tool = (drawing, name) => drawing.useTool({ dataset: { tool: name }, classList: { contains: () => false } });

		const describe = (drawing) => ({
			shape: drawing.shape.constructor.name,
			tools: drawing.shape.getTools(),
			points: drawing.shape.points.length,
			contour: drawing.shape.contour !== null
		});
	';

	public function testAZoneOpensAsAContourThatTurnsIntoARectangleAndBack(): void {

		$result = $this->run(1512, self::OPEN.'
			MapDrawing.start({ dataset: zone });
			const drawing = MapDrawing.current;

			result.opened = describe(drawing);

			drawing.shape.add(map.unproject([300, 200]).toArray());
			tool(drawing, "rectangle");
			result.rectangle = describe(drawing);

			tool(drawing, "free");
			result.free = describe(drawing);

			drawing.cancel();
		');

		$this->assertEquals('MapDrawingPolygon', $result['opened']['shape'] ?? NULL, 'une parcelle s\'ouvre en contour');
		$this->assertEquals(['free', 'rectangle'], $result['opened']['tools'] ?? NULL, 'qui propose la paire des formes');
		$this->assertEquals('MapDrawingRectangle', $result['rectangle']['shape'] ?? NULL, 'le rectangle remplace le contour');
		$this->assertEquals(0, $result['rectangle']['points'] ?? NULL, 'et se trace à neuf, sans le point déjà posé');
		$this->assertEquals(['free', 'rectangle'], $result['rectangle']['tools'] ?? NULL, 'il garde la paire pour revenir au contour');
		$this->assertEquals('MapDrawingPolygon', $result['free']['shape'] ?? NULL, '« Forme libre » ramène au contour');
		$this->assertEquals(['free', 'rectangle'], $result['free']['tools'] ?? NULL, 'qui garde la paire');

	}

	public function testAPlotKeepsItsZoneContourWhenItChangesShape(): void {

		$result = $this->run(1512, self::OPEN.'
			MapDrawing.start({ dataset: plot });
			const drawing = MapDrawing.current;

			result.opened = describe(drawing);
			tool(drawing, "rectangle");
			result.rectangle = describe(drawing);
			tool(drawing, "free");
			result.free = describe(drawing);

			drawing.cancel();
		');

		$this->assertEquals('MapDrawingPolygon', $result['opened']['shape'] ?? NULL, 'un jardin de plein champ s\'ouvre en contour');
		$this->assertTrue($result['opened']['contour'] ?? FALSE, 'qui s\'aimante à sa parcelle');
		$this->assertTrue($result['rectangle']['contour'] ?? FALSE, 'en rectangle aussi');
		$this->assertTrue($result['free']['contour'] ?? FALSE, 'et revenu au contour');

	}

	public function testAGreenhouseStaysARectangle(): void {

		$result = $this->run(1512, self::OPEN.'
			MapDrawing.start({ dataset: greenhouse });
			result.opened = describe(MapDrawing.current);
			MapDrawing.current.cancel();
		');

		$this->assertEquals('MapDrawingRectangle', $result['opened']['shape'] ?? NULL, 'un abri s\'ouvre en rectangle');
		$this->assertEquals([], $result['opened']['tools'] ?? NULL, 'sans forme libre à proposer');

	}

}
