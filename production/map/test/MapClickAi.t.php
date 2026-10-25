<?php
namespace map;

/**
 * Ce que désigne un clic sur la Carte (`static/js/cartography.js`, `MapCartography`) : une planche du jardin ouvert se clique
 * à quelques pixels près, un clic dans le jardin ou la parcelle désignés, hors de ce qui se désigne, garde la désignation, et
 * un clic loin de toute planche décoche celles du jardin ouvert, abri compris, même tombé dans la parcelle à côté de lui.
 *
 * Lancement : dev/test package=map file=MapClickAi
 */
class MapClickAiTest extends DrawingBenchAiTest {

	// Trois planches en pixels de la cartography factice : deux du jardin 1, de 10 px de large et 6 px d'écart, une du jardin 2.
	// Deux planches tiennent dans la boite d'un clic entre elles, comme à fort zoom où planches et allées font quelques pixels.
	// La cartography rend celles dont la boite du clic touche le rectangle, comme queryRenderedFeatures()
	const string BEDS = '
		const rectangle = (id, plot, left, right) => turf.polygon([[[left, 200], [right, 200], [right, 300], [left, 300], [left, 200]].map(point => map.unproject(point).toArray())], { id, plot, left, right });
		const beds = [rectangle(11, 1, 300, 310), rectangle(12, 1, 316, 326), rectangle(21, 2, 350, 360)];

		const cartography = Object.create(MapCartography.prototype);
		cartography.mapbox = Object.assign({}, map, {
			queryRenderedFeatures: ([[left, top], [right, bottom]]) => beds.filter(bed => bed.properties.left <= right && bed.properties.right >= left && top <= 300 && bottom >= 200)
		});
		cartography.bedHandler = () => {};
		cartography.bedsPlot = 1;

		const click = x => {
			const at = map.unproject([x, 250]);
			return { point: { x, y: 250 }, lngLat: { lng: at.lng, lat: at.lat, toArray: () => [at.lng, at.lat] } };
		};
	';

	public function testABedIsClickedWithinAFewPixels(): void {

		$result = $this->run(1512, self::BEDS.'
			result.inside = cartography.getBedAt(click(320));
			result.beside = cartography.getBedAt(click(296));
			result.nearestLeft = cartography.getBedAt(click(312));
			result.nearestRight = cartography.getBedAt(click(314.5));
			result.far = (cartography.getBedAt(click(290)) === null);
			result.otherPlot = (cartography.getBedAt(click(362)) === null);
			result.isBed = cartography.isBedClick(click(296));

			cartography.bedHandler = null;
			result.withoutConsole = cartography.isBedClick(click(305));
		');

		$this->assertEquals(12, $result['inside'] ?? NULL, 'un clic sur une planche la désigne');
		$this->assertEquals(11, $result['beside'] ?? NULL, 'un clic à 4 px d\'une planche la désigne');
		$this->assertEquals(11, $result['nearestLeft'] ?? NULL, 'entre deux planches, le clic désigne la plus proche');
		$this->assertEquals(12, $result['nearestRight'] ?? NULL, 'et l\'autre quand il s\'en rapproche');
		$this->assertTrue($result['far'] ?? NULL, 'à 10 px, au-delà de la tolérance, le clic ne désigne aucune planche');
		$this->assertTrue($result['otherPlot'] ?? NULL, 'une planche d\'un autre jardin que celui qu\'on ouvre ne se désigne pas');
		$this->assertTrue($result['isBed'] ?? NULL, 'le clic qui désigne une planche ne rouvre ni son jardin ni sa parcelle');
		$this->assertFalse($result['withoutConsole'] ?? NULL, 'sans console de planches, une planche n\'arrête aucun clic');

	}

	public function testAClickInsideTheSelectionChangesNothing(): void {

		$result = $this->run(1512, '
			const layers = {};
			const calls = [];

			const cartography = Object.create(MapCartography.prototype);
			cartography.mapbox = Object.assign({}, map, {
				on: (type, layer, handler) => { if(type === "click") layers[layer] = handler; },
				queryRenderedFeatures: () => []
			});
			cartography.plotsZones = { 5: 3, 6: 3, 7: 4 };
			cartography.features = { plots: new Map() };
			cartography.clickPlot = (plotId) => calls.push("plot "+ plotId);
			cartography.clickZone = (zoneId) => calls.push("zone "+ zoneId);
			cartography.selectStructure = () => calls.push("structure");
			cartography.clearBedsHandler = () => calls.push("beds");

			cartography.bindClicks();

			const event = id => ({
				originalEvent: { defaultPrevented: false, composedPath: () => [], preventDefault() { this.defaultPrevented = true; } },
				point: { x: 0, y: 0 },
				features: [{ properties: { id } }]
			});

			// Un clic dans un jardin atteint son bloc, puis sa parcelle
			const clickPlot = (plotId, zoneId) => {
				const e = event(plotId);
				layers["plot-fill"](e);
				e.features = [{ properties: { id: zoneId } }];
				layers["zone-fill"](e);
			};

			const scene = (name, play) => {
				calls.length = 0;
				play();
				result[name] = [...calls];
			};

			// Le jardin 5 de la parcelle 3 est désigné, avec des planches cochées mais la console des planches fermée (ni
			// bedHandler ni bedsPlot) : un clic dans la parcelle ne les décoche pas, testAClickBesideTheGardenClearsItsBeds ouvre la console
			cartography.selectedZone = 3;
			cartography.selectedPlot = 5;
			cartography.selectedBeds = [11];

			scene("selectedPlot", () => clickPlot(5, 3));
			scene("selectedZone", () => layers["zone-fill"](event(3)));
			scene("otherPlot", () => clickPlot(6, 3));
			scene("otherZone", () => layers["zone-fill"](event(4)));
			scene("outside", () => cartography.emptyHandler());

			// Seule la parcelle 3 est désignée
			cartography.selectedPlot = null;
			cartography.selectedBeds = [];

			scene("zoneOnly", () => layers["zone-fill"](event(3)));
		');

		$this->assertEquals([], $result['selectedPlot'] ?? NULL, 'un clic dans le jardin désigné, hors de ses planches, ne change rien');
		$this->assertEquals([], $result['selectedZone'] ?? NULL, 'un clic dans la parcelle du jardin désigné, hors de ses jardins, garde la désignation du jardin');
		$this->assertEquals(['plot 6'], $result['otherPlot'] ?? NULL, 'un autre jardin de la parcelle se désigne');
		$this->assertEquals(['zone 4'], $result['otherZone'] ?? NULL, 'une autre parcelle se désigne');
		$this->assertEquals(['beds'], $result['outside'] ?? NULL, 'hors de toute parcelle, le clic défait les planches cochées');
		$this->assertEquals([], $result['zoneOnly'] ?? NULL, 'un clic dans la parcelle désignée, hors de ses jardins, ne change rien');

	}

	public function testAClickBesideTheGardenClearsItsBeds(): void {

		$result = $this->run(1512, '
			const layers = {};
			const calls = [];

			const cartography = Object.create(MapCartography.prototype);
			cartography.mapbox = Object.assign({}, map, {
				on: (type, layer, handler) => { if(type === "click") layers[layer] = handler; },
				queryRenderedFeatures: () => []
			});
			cartography.plotsZones = { 5: 3, 8: 3 };
			cartography.features = { plots: new Map([[5, {}]]) };
			cartography.clickPlot = (plotId) => calls.push("plot "+ plotId);
			cartography.clickZone = (zoneId) => calls.push("zone "+ zoneId);
			cartography.clearBedsHandler = () => calls.push("beds");
			cartography.bedHandler = () => {};
			cartography.pickBedHandler = () => calls.push("pick");

			cartography.bindClicks();

			const event = id => ({
				originalEvent: { defaultPrevented: false, composedPath: () => [], preventDefault() { this.defaultPrevented = true; } },
				point: { x: 0, y: 0 },
				features: [{ properties: { id } }]
			});

			const scene = (name, play) => {
				calls.length = 0;
				play();
				result[name] = [...calls];
			};

			// Les planches de l abri 5, dessiné dans la parcelle 3, sont ouvertes et une est cochée
			cartography.selectedZone = 3;
			cartography.selectedPlot = 5;
			cartography.bedsPlot = 5;
			cartography.selectedBeds = [11];

			scene("drawn", () => layers["zone-fill"](event(3)));

			// Le clic tombe tout près d une planche du jardin 8, posée à même la parcelle, sans l atteindre
			cartography.getFilledPlotAt = () => 8;
			scene("nearOther", () => layers["zone-fill"](event(3)));
			delete cartography.getFilledPlotAt;

			cartography.locked = true;
			scene("locked", () => layers["zone-fill"](event(3)));
			cartography.locked = false;

			cartography.selectedBeds = [];
			scene("none", () => layers["zone-fill"](event(3)));

			// Les planches posées à même la parcelle : leur jardin 8 n a pas de forme
			cartography.selectedPlot = null;
			cartography.bedsPlot = 8;
			cartography.selectedBeds = [21];

			scene("filled", () => layers["zone-fill"](event(3)));
		');

		$this->assertEquals(['beds'], $result['drawn'] ?? NULL, 'un clic dans la parcelle, à côté de l\'abri, décoche ses planches');
		$this->assertEquals(['beds'], $result['nearOther'] ?? NULL, 'un clic près d\'une planche d\'un autre jardin décoche aussi celles de l\'abri');
		$this->assertEquals([], $result['locked'] ?? NULL, 'pendant un geste, le clic appartient au geste');
		$this->assertEquals([], $result['none'] ?? NULL, 'sans planche cochée, le clic ne fait rien');
		$this->assertEquals(['beds'], $result['filled'] ?? NULL, 'les planches posées à même la parcelle se décochent comme avant');

	}

}
?>
