<?php
namespace map;

/**
 * Les couches du geste sur la Carte (`static/js/cartography.js`, `MapCartography.getStyle()`) : chaque trait du geste passe
 * sur un cerne d'encre posé juste sous lui, sur la même source, le fantôme compris, et les planches cochées dans une liste
 * gardent leur cerne quand la liste change. La couleur ne s'éprouve pas ici, le banc ne lit aucun jeton : elle se juge
 * dans Chrome.
 *
 * Lancement : dev/test package=map file=MapStyleAi
 */
class MapStyleAiTest extends DrawingBenchAiTest {

	const string CARTOGRAPHY = '
		const cartography = Object.create(MapCartography.prototype);
		cartography.basemap = "satellite";
		cartography.selectedBeds = [11, 12];

		const layers = cartography.getStyle(18).layers;
		const ids = layers.map(layer => layer.id);
		const layer = id => layers.find(layer => layer.id === id);
	';

	public function testEachGestureLineRestsOnItsCasing(): void {

		$result = $this->run(1512, self::CARTOGRAPHY.'
			result.drawing = ids.indexOf("drawing-casing") === ids.indexOf("drawing-line") - 1;
			result.ghost = ids.indexOf("drawing-casing") < ids.indexOf("drawing-ghost");
			result.drawingSource = layer("drawing-casing").source;
			result.drawingFilter = (layer("drawing-casing").filter === undefined);
			result.drawingWidths = [layer("drawing-casing").paint["line-width"][2], layer("drawing-casing").paint["line-width"][3], layer("drawing-line").paint["line-width"], layer("drawing-ghost").paint["line-width"]];
			result.drawingCap = layer("drawing-casing").layout["line-cap"] === layer("drawing-line").layout["line-cap"];

			result.bed = ids.indexOf("bed-selected-casing") === ids.indexOf("bed-selected-line") - 1;
			result.bedSource = layer("bed-selected-casing").source;
			result.bedFilter = JSON.stringify(layer("bed-selected-casing").filter) === JSON.stringify(layer("bed-selected-line").filter);
			result.bedWidths = [layer("bed-selected-casing").paint["line-width"], layer("bed-selected-line").paint["line-width"]];
		');

		$this->assertTrue($result['drawing'] ?? NULL, 'le trait de la forme en cours de tracé passe juste sur son cerne');
		$this->assertTrue($result['ghost'] ?? NULL, 'le fantôme passe aussi sur le cerne');
		$this->assertEquals('drawing', $result['drawingSource'] ?? NULL, 'le cerne suit la source de la forme en cours de tracé');
		$this->assertTrue($result['drawingFilter'] ?? NULL, 'le cerne porte la forme comme son fantôme');
		$this->assertEquals([4, 5, 3, 2], $result['drawingWidths'] ?? NULL, 'le cerne déborde du trait et du fantôme qu\'il porte');
		$this->assertTrue($result['drawingCap'] ?? NULL, 'le cerne finit rond comme le trait, sans dépasser en carré');

		$this->assertTrue($result['bed'] ?? NULL, 'le trait des planches cochées passe juste sur son cerne');
		$this->assertEquals('beds', $result['bedSource'] ?? NULL, 'le cerne suit la source des planches');
		$this->assertTrue($result['bedFilter'] ?? NULL, 'le cerne ne prend que les planches cochées');
		$this->assertEquals([4.5, 2.5], $result['bedWidths'] ?? NULL, 'le cerne déborde du trait des planches cochées');

	}

	public function testTheBedCasingFollowsTheCheckedBeds(): void {

		$result = $this->run(1512, self::CARTOGRAPHY.'
			const filters = {};
			cartography.mapbox = { setFilter: (id, filter) => filters[id] = JSON.stringify(filter) };
			cartography.do = callback => callback();

			cartography.selectBeds([21]);

			result.casing = filters["bed-selected-casing"] ?? null;
			result.line = filters["bed-selected-line"] ?? null;
		');

		$this->assertEquals('["in",["get","id"],["literal",[21]]]', $result['casing'] ?? NULL, 'le cerne prend les planches que la liste coche');
		$this->assertEquals($result['line'] ?? NULL, $result['casing'] ?? NULL, 'le cerne et le trait gardent le même filtre');

	}

}
?>
