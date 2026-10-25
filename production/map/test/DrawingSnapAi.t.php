<?php
namespace map;

/**
 * Une forme rigide qu'on déplace ou qu'on tourne (`MapDrawing.snap()`, `static/js/drawing.js`) se colle au côté qu'elle
 * longe : à moins de 12 px et de 5° de lui, elle en prend l'angle et s'y pose, contre le contour qui la reçoit. Au-delà,
 * elle reste où on la laisse, et une planche dessinée ne l'attire jamais : elle se poserait dessus.
 *
 * Le jardin du banc est un carré de 400 px, du haut (y = 50) au bas (y = 450). Le rang de dix planches de 20 m y est posé
 * à plat, ses planches allongées d'ouest en est, puis tourné de quelques degrés.
 *
 * Lancement : dev/test package=map file=DrawingSnapAi
 */
class DrawingSnapAiTest extends DrawingBenchAiTest {

	// Pose le rang à plat, le haut à `top` px, tourné de `degrees`, et rend son côté du haut après l'aimant
	const string PLACE = '
		const place = (top, degrees) => {

			const drawing = MapDrawing.current;
			const shape = drawing.shape;

			const start = [map.unproject([300, top]).lng, map.unproject([300, top]).lat];
			const flat = MapDrawingLine.getRow(start, 180, 90, MapDrawingLine.getSize(shape.beds));
			shape.points = turf.getCoords(turf.transformRotate(turf.lineString(flat), degrees, { pivot: shape.getPivot.call({ points: flat }) }));

			drawing.snap(drawing.getReferences());
			const corners = shape.getOutline().map(point => map.project(point));

			return {
				top: [corners[3].y, corners[0].y].map(y => Math.round(y * 100) / 100),
				degrees: Math.round(Math.atan2(corners[0].y - corners[3].y, corners[0].x - corners[3].x) * 180 / Math.PI * 100) / 100
			};

		};
	';

	// Une planche dessinée hors du geste, de y = 250 à y = 300
	const string DRAWN = '
		const [west, east] = [map.unproject([300, 0]).lng, map.unproject([556, 0]).lng];
		const [north, south] = [map.unproject([0, 250]).lat, map.unproject([0, 300]).lat];
		carto.features = { beds: new Map([["drawn", [turf.polygon([[[west, north], [east, north], [east, south], [west, south], [west, north]]], { plot: 1, id: 99 })]]]) };
	';

	public function testANearlyParallelRowSticksToTheContour(): void {

		$result = $this->run(1512, self::PLACE.'
			MapDrawing.start({ dataset: gestures.beds });
			result.stuck = place(56, 3);
			result.free = place(56, 8);
			result.far = place(70, 3);
			MapDrawing.current.cancel();
		');

		$this->assertEquals([50, 50], $result['stuck']['top'] ?? NULL, 'à 6 px et 3° du bord haut, le rang s\'y colle par ses deux coins');
		$this->assertEquals(180, abs($result['stuck']['degrees'] ?? 0), 'et prend son angle');

		$this->assertTrue(abs($result['free']['degrees'] ?? 180) < 175, 'à 8°, il garde son angle');
		$this->assertTrue(min($result['free']['top'] ?? [50]) < 50, 'et sa place, un coin passé au-dessus du bord');

		$this->assertTrue(abs($result['far']['degrees'] ?? 180) < 178, 'à 20 px du bord, il garde son angle');
		$this->assertTrue(min($result['far']['top'] ?? [50]) > 50, 'et sa place');

	}

	public function testARowNearADrawnBedKeepsItsPlace(): void {

		$result = $this->run(1512, self::PLACE.self::DRAWN.'
			MapDrawing.start({ dataset: gestures.beds });
			result.near = place(306, 2);
			MapDrawing.current.cancel();
		');

		$this->assertTrue(abs($result['near']['degrees'] ?? 180) < 179, 'à 6 px et 2° sous une planche dessinée, le rang garde son angle');
		$this->assertTrue(($result['near']['top'][0] ?? 0) !== ($result['near']['top'][1] ?? 0), 'sans se poser à plat contre elle');

	}

	public function testASingleBedMovedNearADrawnBedKeepsItsPlace(): void {

		$result = $this->run(1512, self::DRAWN.'
			checked.splice(1);

			MapDrawing.start({ dataset: gestures.beds });
			const drawing = MapDrawing.current;
			const shape = drawing.shape;

			// La planche à plat, son haut à 310 px, puis tenue par son milieu et montée de 5 px : à 5 px de la planche dessinée,
			// quelques centimètres au-delà de son passe-pied de 50 cm, où un aimant à l\'allée la collerait
			const start = [map.unproject([300, 310]).lng, map.unproject([300, 310]).lat];
			shape.points = MapDrawingLine.getRow(start, 180, 90, MapDrawingLine.getSize(shape.beds));

			const from = map.project(shape.getPivot());
			drawing.startHandle({ button: 0, clientX: from.x, clientY: from.y, preventDefault: () => {} }, { dataset: { drag: "point", index: "0" } });
			// Le premier pas franchit le seuil d\'un clic, le second pose la planche
			drawing.moveDrag({ clientX: from.x, clientY: from.y + 20 });
			drawing.moveDrag({ clientX: from.x, clientY: from.y - 5 });

			result.top = Math.min(...shape.getOutline().map(point => map.project(point).y));
			result.beds = shape.beds.length;

			drawing.endDrag();
			drawing.cancel();
		');

		$this->assertEquals(1, $result['beds'] ?? NULL, 'le geste ne porte qu\'une planche');
		$this->assertEquals(305.0, round($result['top'] ?? 0, 2), 'montée de 5 px, elle reste où on la laisse, sans se coller à la planche dessinée');

	}

	public function testAPlotIsMovedWholeAndStopsAtItsZoneWithoutTurning(): void {

		$result = $this->run(1512, '
			// Un jardin de 100 px de côté au milieu de la parcelle, un peu tourné
			const corner = (x, y) => [map.unproject([x, y]).lng, map.unproject([x, y]).lat];
			const plot = turf.getCoords(turf.transformRotate(turf.polygon([[corner(350, 200), corner(450, 200), corner(450, 300), corner(350, 300), corner(350, 200)]]), 2));

			MapDrawing.start({ dataset: { drawing: "polygon", drawingMap: "map", drawingTarget: "plot", drawingId: "5", drawingCoordinates: JSON.stringify(plot[0]), drawingContour: JSON.stringify(square) } });

			const drawing = MapDrawing.current;
			const shape = drawing.shape;
			const before = JSON.stringify(shape.points);
			const sides = () => shape.points.map(point => map.project(point));

			result.modes = shape.getModes();

			// Tiré d\'un bloc vers le haut, son coin le plus haut à 6 px du bord
			const top = Math.min(...sides().map(point => point.y));
			const pivot = map.project(shape.getPivot());
			const to = map.unproject([pivot.x, pivot.y - (top - 56)]);
			shape.moveTo([to.lng, to.lat]);

			const angle = () => { const [a, b] = sides(); return Math.round(Math.atan2(b.y - a.y, b.x - a.x) * 18000 / Math.PI) / 100; };
			const turned = angle();

			drawing.snap(drawing.getReferences(), false);

			result.top = Math.round(Math.min(...sides().map(point => point.y)) * 100) / 100;
			result.sameAngle = (angle() === turned);
			const motion = shape.getMotion();
			result.angle = (motion === null) ? null : Math.round(motion.angle * 1e6) / 1e6;
			result.gap = (motion === null) ? null : Math.max(...MapDrawingCopy.getTransformed(JSON.parse(before), motion.origin, motion.shift, motion.angle).map((point, index) => Math.hypot(point[0] - shape.points[index][0], point[1] - shape.points[index][1])));

			// Un sommet tiré seul : les planches ne suivent plus
			shape.move(0, corner(300, 300));
			result.reshaped = shape.getMotion();

			drawing.cancel();
			result.closed = (MapDrawing.current === null);
		');

		$this->assertEquals(['edit', 'move', 'rotate'], $result['modes'] ?? NULL, 'un contour refermé se modifie, se tire d\'un bloc ou tourne');
		$this->assertEquals(50, $result['top'] ?? NULL, 'à 6 px du bord de la parcelle, il s\'y colle par son coin le plus haut');
		$this->assertTrue($result['sameAngle'] ?? FALSE, 'sans tourner');
		$this->assertEquals(0, $result['angle'] ?? NULL, 'tiré d\'un bloc, il a un mouvement sans rotation, que ses planches suivent');
		$this->assertTrue(($result['gap'] ?? 1) < 1e-12, 'qui porte chacun de ses sommets, aimant compris');
		$this->assertTrue(array_key_exists('reshaped', $result) and $result['reshaped'] === NULL, 'un sommet tiré seul n\'en a plus');
		$this->assertTrue($result['closed'] ?? FALSE, 'Annuler quitte le geste');

	}

}
?>
