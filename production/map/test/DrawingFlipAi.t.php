<?php
namespace map;

/**
 * « Inverser » retourne le rang sur place (`MapDrawingLine.tool('flip')`, `static/js/drawing.js`) : sa première planche
 * passe au bout, l'emprise et la caméra ne bougent pas, et deux clics le rendent tel qu'il était.
 *
 * Lancement : dev/test package=map file=DrawingFlipAi
 */
class DrawingFlipAiTest extends DrawingBenchAiTest {

	public function testFlipTurnsTheRowOverInPlace(): void {

		$result = $this->run(1512, '
			MapDrawing.start({ dataset: gestures.beds });

			const shape = MapDrawing.current.shape;
			const round = points => points.map(([x, y]) => [Math.round(x * 1e9) / 1e9, Math.round(y * 1e9) / 1e9]);
			const first = () => round([turf.centroid(shape.getRows().polygons[0]).geometry.coordinates])[0];
			const last = () => round([turf.centroid(shape.getRows().polygons.at(-1)).geometry.coordinates])[0];
			// Au centimètre, sous lequel la rotation de turf, qui suit la sphère, laisse ses arrondis
			const footprint = () => JSON.stringify(MapDrawingLine.getCorners(shape.points).map(([x, y]) => [Math.round(x * 1e7), Math.round(y * 1e7)]).map(String).sort());

			const before = { points: JSON.stringify(round(shape.points)), first: first(), last: last(), footprint: footprint() };

			result.tools = shape.getTools();
			result.click = shape.tool("flip", null, null);

			result.firstAtEnd = JSON.stringify(first()) === JSON.stringify(before.last);
			result.lastAtStart = JSON.stringify(last()) === JSON.stringify(before.first);
			result.sameFootprint = footprint() === before.footprint;

			shape.tool("flip", null, null);
			result.back = JSON.stringify(round(shape.points)) === before.points;

			result.moves = [...moves];
			MapDrawing.current.cancel();
		');

		$this->assertEquals(['rotate', 'flip'], $result['tools'] ?? NULL, 'le rang propose Tourner puis Inverser');
		$this->assertTrue(array_key_exists('click', $result) and $result['click'] === NULL, 'Inverser agit d\'un clic, sans se tirer');
		$this->assertTrue($result['firstAtEnd'] ?? FALSE, 'la première planche passe à la place de la dernière');
		$this->assertTrue($result['lastAtStart'] ?? FALSE, 'et la dernière à celle de la première');
		$this->assertTrue($result['sameFootprint'] ?? FALSE, 'l\'emprise du rang ne bouge pas');
		$this->assertTrue($result['back'] ?? FALSE, 'deux clics le rendent tel qu\'il était');
		$this->assertEquals([], $result['moves'] ?? NULL, 'la caméra ne bouge pas');

	}

}
?>
