<?php
namespace map;

/**
 * Un jardin qu'on tourne à l'outil (`tool('rotate')`, `static/js/drawing.js`) emmène ses planches et leurs numéros à l'écran,
 * par le mouvement rigide que le serveur relira à l'enregistrement (`MapDrawingCopy.getMotion()`, `MapLib::getMotion()`), et
 * Annuler les rend à leur place.
 *
 * La carte du banc ne porte que ce qu'il faut pour le voir : les sources de formes et les méthodes de `MapCartography` qui les
 * déplacent, le jardin 5 et une planche tracée sous la clé `drawn`.
 *
 * Lancement : dev/test package=map file=DrawingTurnAi
 */
class DrawingTurnAiTest extends DrawingBenchAiTest {

	// Le jardin, un carré de 100 px au milieu de la carte, sa planche de 60 × 10 px numérotée à plat, et la carte qui les porte
	const string GARDEN = '
		const corner = (x, y) => map.unproject([x, y]).toArray();
		const plot = [corner(350, 200), corner(450, 200), corner(450, 300), corner(350, 300)];
		const bed = turf.polygon([[corner(370, 240), corner(430, 240), corner(430, 250), corner(370, 250), corner(370, 240)]], { plot: 5, id: 99 });
		const label = turf.point(corner(400, 245), { rot: 0 });

		Object.assign(carto, {
			features: { drawing: new Map(), beds: new Map([["drawn", [bed]]]), bedLabels: new Map([["drawn", [label]]]), plots: new Map(), plotLabels: new Map(), pac: new Map() },
			masked: [],
			bedsDrawn: { 5: ["drawn"] },
			sourcesDirty: new Set(),
			// Le banc n\'a pas d\'image à redessiner
			flushFrame: 1
		});

		["moveBeds", "moveFeature", "setFeature", "clearMask"].forEach(name => carto[name] = MapCartography.prototype[name]);

		// Tourne la forme du geste de `degrees` dans le sens horaire, comme le pointeur qui tire l\'outil autour de son pivot
		const turn = (degrees) => {
			const drawing = MapDrawing.current;
			const pivot = map.project(drawing.shape.getPivot());
			const radians = degrees * Math.PI / 180;
			const move = drawing.shape.tool("rotate", { x: pivot.x, y: pivot.y - 100 }, pivot);
			move({ x: pivot.x + 100 * Math.sin(radians), y: pivot.y - 100 * Math.cos(radians) });
			drawing.refresh();
		};

		const gap = (points, expected) => Math.max(...points.map((point, index) => Math.hypot(point[0] - expected[index][0], point[1] - expected[index][1])));
		const shown = () => turf.getCoords(carto.features.beds.get("drawn")[0])[0];
	';

	public function testAPolygonTurnedTakesItsBedsAlong(): void {

		$result = $this->run(1512, self::GARDEN.'
			MapDrawing.start({ dataset: { drawing: "polygon", drawingMap: "map", drawingTarget: "plot", drawingId: "5", drawingCoordinates: JSON.stringify([...plot, plot[0]]) } });

			const shape = MapDrawing.current.shape;
			const center = shape.getPivot();
			turn(30);

			const motion = shape.getMotion();
			result.angle = (motion === null) ? null : Math.round(motion.angle * 1e6) / 1e6;
			result.followed = gap(shown(), MapDrawingCopy.getRotated(turf.getCoords(bed)[0], shape.getPlane(), center, 30));
			result.rot = Math.round(carto.features.bedLabels.get("drawn")[0].properties.rot * 1e6) / 1e6;

			MapDrawing.current.cancel();
			result.restored = gap(shown(), turf.getCoords(bed)[0]);
		');

		$this->assertEquals(30, $result['angle'] ?? NULL, 'tourné de 30°, le jardin a un mouvement que le serveur relira');
		$this->assertTrue(($result['followed'] ?? 1) < 1e-12, 'sa planche tourne avec lui, autour du même pivot');
		$this->assertEquals(30, $result['rot'] ?? NULL, 'et son numéro avec elle');
		$this->assertTrue(($result['restored'] ?? 1) < 1e-12, 'Annuler la rend à sa place');

	}

	public function testARectangleTurnedTakesItsBedsAlong(): void {

		$result = $this->run(1512, self::GARDEN.'
			MapDrawing.start({ dataset: { drawing: "rectangle", drawingMap: "map", drawingTarget: "plot", drawingId: "5", drawingCoordinates: JSON.stringify(plot) } });

			const shape = MapDrawing.current.shape;
			const center = shape.getPivot();
			turn(-120);

			const motion = shape.getMotion();
			result.rectangle = (shape.constructor.name === "MapDrawingRectangle");
			result.angle = (motion === null) ? null : Math.round(motion.angle * 1e6) / 1e6;
			result.followed = gap(shown(), MapDrawingCopy.getRotated(turf.getCoords(bed)[0], shape.getPlane(), center, -120));
			result.rot = Math.round(carto.features.bedLabels.get("drawn")[0].properties.rot * 1e6) / 1e6;

			MapDrawing.current.cancel();
			result.restored = gap(shown(), turf.getCoords(bed)[0]);
		');

		$this->assertTrue($result['rectangle'] ?? FALSE, 'le jardin se tourne en rectangle');
		$this->assertEquals(-120, $result['angle'] ?? NULL, 'tourné de 120° dans le sens contraire, il a un mouvement');
		$this->assertTrue(($result['followed'] ?? 1) < 1e-12, 'sa planche tourne avec lui');
		$this->assertEquals(60, $result['rot'] ?? NULL, 'et son numéro, retourné pour ne pas se lire la tête en bas');
		$this->assertTrue(($result['restored'] ?? 1) < 1e-12, 'Annuler la rend à sa place');

	}

	public function testAWidePlotPulledThenTurnedKeepsOneMotion(): void {

		$result = $this->run(1512, self::GARDEN.'
			// Un jardin de 1 km de côté, tiré de 1 km vers le nord puis tourné de 37°
			const side = 0.009;
			const wide = [[3.1, 45.6], [3.1 + side * 1.43, 45.6], [3.1 + side * 1.43, 45.6 + side], [3.1, 45.6 + side]];

			MapDrawing.start({ dataset: { drawing: "polygon", drawingMap: "map", drawingTarget: "plot", drawingId: "5", drawingCoordinates: JSON.stringify([...wide, wide[0]]) } });

			const shape = MapDrawing.current.shape;
			const pivot = shape.getPivot();
			shape.moveTo([pivot[0], pivot[1] + side]);
			const pulled = shape.getPivot();
			turn(37);

			const motion = shape.getMotion();
			result.angle = (motion === null) ? null : Math.round(motion.angle * 1e6) / 1e6;
			result.pivot = gap([shape.getPivot()], [pulled]);
			result.moved = (motion === null) ? null : gap(MapDrawingCopy.getTransformed(wide, motion.origin, motion.shift, motion.angle), shape.points);

			MapDrawing.current.cancel();
		');

		$this->assertEquals(37, $result['angle'] ?? NULL, 'le glisser et la rotation restent un seul mouvement, de l\'angle donné');
		$this->assertTrue(($result['pivot'] ?? 1) < 1e-12, 'la rotation garde le pivot où le glisser l\'a laissé');
		$this->assertTrue(($result['moved'] ?? 1) < 1e-12, 'ce mouvement porte chaque sommet d\'ouverture à sa place');

	}

}
?>
