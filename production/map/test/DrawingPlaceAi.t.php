<?php
namespace map;

/**
 * Des planches tracées rouvertes à leur place (`MapDrawingLine.getPlaced()`, `static/js/drawing.js`) s'y dessinent telles que
 * la carte les montre, et le rang posé d'office (`getDefault()`) ne tombe pas sur les planches déjà posées du jardin.
 *
 * Aucune écriture, aucune base ouverte.
 *
 * Lancement : dev/test package=map file=DrawingPlaceAi
 */
class DrawingPlaceAiTest extends DrawingBenchAiTest {

	public function testPlacedBedsOpenWhereTheMapDrawsThem(): void {

		$result = $this->run(1512, '
			const beds = [
				{id: 1, name: "1", length: 20, width: 80, alley: 40, corner: [3.1, 45.6], angle: 1.2},
				{id: 2, name: "2", length: 15, width: 120, alley: 40, corner: [3.10005, 45.60003], angle: 1.2},
				{id: 3, name: "3", length: 10, width: 80, alley: 40, corner: [3.10002, 45.60008], angle: 2.9}
			];

			const shape = new MapDrawingLine(null);
			shape.beds = beds;
			[shape.points, shape.placed] = MapDrawingLine.getPlaced(beds);
			shape.closed = true;

			// Au centimètre, sous lequel turf, qui suit la sphère, laisse ses arrondis
			const round = polygon => JSON.stringify(polygon.geometry.coordinates[0].map(([x, y]) => [Math.round(x * 1e7), Math.round(y * 1e7)]));

			result.same = shape.getRows().polygons.map(round).join() === beds.map(bed => round(MapCartography.getBed(bed, bed.corner, bed.angle).polygon)).join();
			result.parallel = shape.isParallel();
			result.modes = shape.getModes();

			const places = shape.getRows().polygons.map(polygon => MapDrawingLine.getCanonical(polygon));
			result.posted = places.map((place, position) => turf.distance(place.corner, beds[position].corner, {units: "meters"}) < 0.01 && Math.abs(place.angle - beds[position].angle) < 0.0001);
		');

		$this->assertTrue($result['same'] ?? FALSE, 'chaque planche s\'ouvre à la place que la carte lui dessine');
		$this->assertFalse($result['parallel'] ?? TRUE, 'une planche tournée autrement que les autres rend la sélection non parallèle');
		$this->assertEquals(['move', 'rotate'], $result['modes'] ?? NULL, 'qui ne propose donc pas de régler ses allées');
		$this->assertEquals([TRUE, TRUE, TRUE], $result['posted'] ?? NULL, 'rien n\'ayant bougé, chaque planche repart avec son coin et son angle');

	}

	public function testDefaultRowAvoidsThePlacedBeds(): void {

		$result = $this->run(1512, '
			// Un jardin carré d\'une trentaine de mètres, et une planche posée là où le rang continuerait le dernier trait
			const contour = [[3.1, 45.6], [3.1004, 45.6], [3.1004, 45.6003], [3.1, 45.6003], [3.1, 45.6]];
			const last = MapDrawingLine.getRow([3.1, 45.6003], 90, 180, {width: 0.0008, length: 0.02});
			const beds = [{id: 1, name: "1", length: 20, width: 80, alley: 40}];

			const next = MapDrawingLine.getDefault(contour, last, beds, []);
			const other = turf.polygon([[...MapDrawingLine.getCorners(next), next[0]]]).geometry;

			const row = MapDrawingLine.getDefault(contour, last, beds, [other]);

			result.continued = MapDrawingLine.isFree(next, []);
			result.free = MapDrawingLine.isFree(row, [other]);
			result.inside = MapDrawingLine.isInside(row, contour);
			result.moved = JSON.stringify(row) !== JSON.stringify(next);

			// Une planche voisine contre laquelle le rang se pose, sans passe-pied, le touche sans le recouvrir
			const touching = turf.polygon([[...MapDrawingLine.getCorners(last), last[0]]]).geometry;
			result.touching = MapDrawingLine.isFree(MapDrawingLine.getRow(last[1], 90, 180, {width: 0.0008, length: 0.02}), [touching]);
		');

		$this->assertTrue($result['continued'] ?? FALSE, 'sans planche posée, le rang continue le dernier trait');
		$this->assertTrue($result['moved'] ?? FALSE, 'une planche posée à cette place le fait partir ailleurs');
		$this->assertTrue($result['free'] ?? FALSE, 'où il ne la recouvre pas');
		$this->assertTrue($result['inside'] ?? FALSE, 'et reste dans le jardin');
		$this->assertTrue($result['touching'] ?? FALSE, 'une planche qui ne fait que toucher le rang ne le chasse pas');

	}

	public function testDefaultRowSlidesAlongTheSideToTheFreePlace(): void {

		$result = $this->run(1512, '
			// Un jardin plus haut que large, dont le plus grand côté est à l\'ouest, et des planches qui en couvrent toute la moitié nord
			const contour = [[3.1, 45.6], [3.1004, 45.6], [3.1004, 45.6003], [3.1, 45.6003], [3.1, 45.6]];
			const north = turf.polygon([[[3.0999, 45.60015], [3.1005, 45.60015], [3.1005, 45.6004], [3.0999, 45.6004], [3.0999, 45.60015]]]).geometry;
			const beds = [{id: 1, name: "1", length: 10, width: 80, alley: 40}, {id: 2, name: "2", length: 10, width: 80, alley: 40}];

			const row = MapDrawingLine.getDefault(contour, null, beds, [north]);

			result.free = MapDrawingLine.isFree(row, [north]);
			result.inside = MapDrawingLine.isInside(row, contour);
			result.west = turf.distance(row[0], [3.1, row[0][1]], {units: "meters"}) < 0.5;
		');

		$this->assertTrue($result['free'] ?? FALSE, 'le coin du plus grand côté étant pris sur toute la largeur, le rang glisse le long de lui jusqu\'à la place libre');
		$this->assertTrue($result['inside'] ?? FALSE, 'sans sortir du jardin');
		$this->assertTrue($result['west'] ?? FALSE, 'et reste contre ce côté');

	}

	public function testNewRowIsLaidAgainUntilItIsMoved(): void {

		$result = $this->run(1512, '
			// Le rang continue le dernier trait, et une planche posée là où le rang déborderait une fois élargi
			const contour = [[3.1, 45.6], [3.1004, 45.6], [3.1004, 45.6003], [3.1, 45.6003], [3.1, 45.6]];
			const last = MapDrawingLine.getRow([3.1, 45.6003], 90, 180, {width: 0.0008, length: 0.02});
			const one = [{id: 0, name: "1", length: 20, width: 80, alley: 40}];
			const three = [0, 1, 2].map(id => ({id: id, name: String(id + 1), length: 20, width: 80, alley: 40}));

			const wide = MapDrawingLine.getRow(turf.destination(last[1], 0.0004, 90).geometry.coordinates, 90, 180, MapDrawingLine.getSize(three));
			const wider = turf.destination(wide[0], 0.0025, 90).geometry.coordinates;
			const other = turf.polygon([[...MapDrawingLine.getCorners(MapDrawingLine.getRow(wider, 90, 180, {width: 0.0008, length: 0.02})), wider]]).geometry;

			const shape = new MapDrawingLine(null);
			shape.create = true;
			shape.contour = contour;
			shape.last = last;
			shape.visible = [[3.09, 45.59], [3.11, 45.61]];
			shape.neighbours = () => [other];

			shape.setBeds(one);
			result.firstFree = MapDrawingLine.isFree(shape.points, [other]);

			shape.setBeds(three);
			result.laidFree = MapDrawingLine.isFree(shape.points, [other]);
			result.laidInside = MapDrawingLine.isInside(shape.points, contour);

			const pivot = shape.getPivot();
			shape.moveTo([pivot[0] + 0.00001, pivot[1]]);
			const start = JSON.stringify(shape.points[0]);

			shape.setBeds(one);
			result.kept = JSON.stringify(shape.points[0]) === start;
		');

		$this->assertTrue($result['firstFree'] ?? FALSE, 'une planche seule se pose d\'office là où le rang continue');
		$this->assertTrue($result['laidFree'] ?? FALSE, 'trois planches, saisies avant qu\'on touche au rang, le reposent d\'office au lieu de l\'élargir sur la planche voisine');
		$this->assertTrue($result['laidInside'] ?? FALSE, 'dans le jardin');
		$this->assertTrue($result['kept'] ?? FALSE, 'un rang qu\'on a déplacé garde son premier point quand les planches changent');

	}

	public function testRowLaidBySlidingIsLaidAgain(): void {

		$result = $this->run(1512, '
			// Le jardin du glissement : la moitié nord est prise, le rang se pose d\'office plus au sud, le long du côté ouest
			const contour = [[3.1, 45.6], [3.1004, 45.6], [3.1004, 45.6003], [3.1, 45.6003], [3.1, 45.6]];
			const north = turf.polygon([[[3.0999, 45.60015], [3.1005, 45.60015], [3.1005, 45.6004], [3.0999, 45.6004], [3.0999, 45.60015]]]).geometry;
			const beds = count => [...Array(count).keys()].map(id => ({id: id, name: String(id + 1), length: 10, width: 80, alley: 40}));

			const shape = new MapDrawingLine(null);
			shape.create = true;
			shape.contour = contour;
			shape.last = null;
			shape.visible = [[3.09, 45.59], [3.11, 45.61]];
			shape.neighbours = () => [north];

			result.counts = [3, 4, 9, 2].map(count => {
				shape.setBeds(beds(count));
				const fresh = MapDrawingLine.getDefault(contour, null, beds(count), [north]);
				return MapDrawingLine.isFree(shape.points, [north]) && MapDrawingLine.isInside(shape.points, contour) && JSON.stringify(shape.points) === JSON.stringify(fresh);
			});
		');

		$this->assertEquals([TRUE, TRUE, TRUE, TRUE], $result['counts'] ?? NULL, 'un rang posé par glissement, auquel on n\'a pas touché, se repose là où une pose fraîche le mettrait, à chaque nombre de planches');

	}

	public function testDefaultRowTriesABoundedNumberOfRows(): void {

		$result = $this->run(1512, '
			// Un jardin de 300 m couvert de planches : aucune place, et la recherche doit s\'arrêter d\'elle-même
			const contour = [[3.1, 45.6], [3.104, 45.6], [3.104, 45.6027], [3.1, 45.6027], [3.1, 45.6]];
			const others = [...Array(300).keys()].map(index => {
				const west = 3.1 + (index % 30) * 0.000134;
				const south = 45.6 + Math.floor(index / 30) * 0.00027;
				return turf.polygon([[[west, south], [west + 0.000134, south], [west + 0.000134, south + 0.00027], [west, south + 0.00027], [west, south]]]).geometry;
			});

			const isFree = MapDrawingLine.isFree;
			let calls = 0;
			MapDrawingLine.isFree = (points, list) => { calls++; return isFree(points, list); };

			const row = MapDrawingLine.getDefault(contour, null, [{id: 1, name: "1", length: 10, width: 80, alley: 40}], others);

			MapDrawingLine.isFree = isFree;

			result.calls = calls;
			result.inside = MapDrawingLine.isInside(row, contour);
		');

		$this->assertTrue(($result['calls'] ?? PHP_INT_MAX) <= 2000, 'un jardin plein ne fait pas essayer plus de MapDrawingLine.CANDIDATES rangs : '.($result['calls'] ?? 'aucun'));
		$this->assertTrue($result['inside'] ?? FALSE, 'et le rang se pose malgré tout dans le jardin, où la main le placera');

	}

	public function testUncheckingABedKeepsTheMoveOfTheOthers(): void {

		$result = $this->run(1512, '
			const beds = [
				{id: 1, name: "1", length: 20, width: 80, alley: 40, corner: [3.1, 45.6], angle: 1.2},
				{id: 2, name: "2", length: 20, width: 80, alley: 40, corner: [3.10002, 45.60001], angle: 1.2}
			];

			const shape = new MapDrawingLine(null);
			shape.create = false;
			shape.setBeds(beds);

			const pivot = shape.getPivot();
			shape.moveTo([pivot[0] + 0.00005, pivot[1] + 0.00002]);

			const round = polygon => JSON.stringify(polygon.geometry.coordinates[0].map(([x, y]) => [Math.round(x * 1e7), Math.round(y * 1e7)]));
			const moved = round(shape.getRows().polygons[0]);

			shape.setBeds([beds[0]]);

			result.kept = round(shape.getRows().polygons[0]) === moved;
			result.stored = round(shape.getRows().polygons[0]) === round(MapCartography.getBed(beds[0], beds[0].corner, beds[0].angle).polygon);
		');

		$this->assertTrue($result['kept'] ?? FALSE, 'la planche qui reste garde la place où le geste vient de la mettre');
		$this->assertFalse($result['stored'] ?? TRUE, 'et ne revient pas à celle qui est enregistrée');

	}

}
?>
