<?php
namespace map;

/**
 * Un geste de la carte (`static/js/drawing.js`, `MapDrawing`) ne déplace jamais la caméra : ni en s'ouvrant, ni quand on
 * l'annule. Le contour d'une parcelle neuve ou retracée, le rectangle d'un jardin neuf et le rang des planches se posent
 * dans la vue que l'utilisateur a laissée, et un rang dont la place habituelle sort de la vue repart de ce que la vue
 * montre du jardin. Le rectangle d'un jardin neuf part d'une carte vide et se trace d'un glisser, que la carte ne suit pas.
 *
 * Éprouvé sur le banc de DrawingBenchAiTest, où chaque appel qui déplace la caméra est noté et la déplace.
 *
 * Aucune écriture, aucune base ouverte.
 *
 * Lancement : dev/test package=map file=DrawingCameraAi
 */
class DrawingCameraAiTest extends DrawingBenchAiTest {

	const array GESTURES = ['zoneCreate', 'zoneShape', 'plotCreate', 'beds', 'bedsCreate'];

	public function testOpeningAndCancellingNeverMoveTheCamera(): void {

		foreach([1512, 360] as $width) {

			$result = $this->run($width, '
				Object.keys(gestures).forEach(name => {

					const before = JSON.stringify(camera);

					MapDrawing.start({ dataset: gestures[name] });
					const opened = { moves: [...moves], same: JSON.stringify(camera) === before, points: MapDrawing.current.shape.points.length };

					MapDrawing.current.cancel();

					result[name] = { opened, left: { moves: [...moves], same: JSON.stringify(camera) === before }, closed: MapDrawing.current === null };
					moves.length = 0;

				});
			');

			foreach(self::GESTURES as $gesture) {

				$this->assertEquals([], $result[$gesture]['opened']['moves'] ?? NULL, $gesture.' à '.$width.' px : l\'ouverture n\'appelle aucun mouvement de caméra');
				$this->assertTrue($result[$gesture]['opened']['same'] ?? FALSE, $gesture.' à '.$width.' px : centre et zoom inchangés à l\'ouverture');
				$this->assertEquals([], $result[$gesture]['left']['moves'] ?? NULL, $gesture.' à '.$width.' px : Annuler n\'appelle aucun mouvement de caméra');
				$this->assertTrue($result[$gesture]['left']['same'] ?? FALSE, $gesture.' à '.$width.' px : centre et zoom inchangés après Annuler');
				$this->assertTrue($result[$gesture]['closed'] ?? FALSE, $gesture.' à '.$width.' px : Annuler quitte le geste');

			}

			$this->assertEquals(0, $result['plotCreate']['opened']['points'] ?? NULL, 'le rectangle d\'un jardin neuf ne se pose pas à l\'ouverture');

			// Le cas n'éprouve rien si la forme ne se pose pas : le rang se pose d'office
			$this->assertEquals(3, $result['beds']['opened']['points'] ?? NULL, 'le rang des planches cochées se pose à l\'ouverture');

		}

	}

	public function testANewGardenIsDrawnByDragging(): void {

		$result = $this->run(1512, '
			const at = point => {
				const lngLat = map.unproject(point);
				return { lng: lngLat.lng, lat: lngLat.lat, toArray: () => [lngLat.lng, lngLat.lat] };
			};

			const pull = (from, to) => {
				MapDrawing.current.startBody({ lngLat: at(from), preventDefault: () => {} }, { button: 0, clientX: from[0], clientY: from[1], preventDefault: () => {} });
				MapDrawing.current.moveDrag({ clientX: to[0], clientY: to[1] });
				MapDrawing.current.endDrag();
			};

			const state = () => ({ points: MapDrawing.current.shape.points.length, ready: MapDrawing.current.shape.isReady(), changed: MapDrawing.current.changed });

			MapDrawing.start({ dataset: gestures.plotCreate });

			MapDrawing.current.clickMap({ originalEvent: { detail: 1 }, point: { x: 300, y: 200 }, lngLat: at([300, 200]) });
			result.clicked = state();

			// 1 px vaut 1e-6°, soit une dizaine de centimètres : 6 px ne font pas le mètre d\'un côté
			pull([300, 200], [306, 206]);
			result.short = state();

			// Le dernier geste du banc a levé `dragged`, que le navigateur rabat avant le geste suivant
			MapDrawing.current.dragged = false;

			pull([300, 200], [450, 300]);
			result.drawn = { ...state(), moves: [...moves], sides: MapDrawing.current.shape.getSides().map(side => Math.round(side.value)) };

			MapDrawing.current.cancel();
		');

		$this->assertEquals(['points' => 0, 'ready' => FALSE, 'changed' => FALSE], $result['clicked'] ?? NULL, 'un clic sans glisser ne trace rien');
		$this->assertEquals(['points' => 0, 'ready' => FALSE, 'changed' => FALSE], $result['short'] ?? NULL, 'un glisser plus court que le mètre ne trace rien');
		$this->assertEquals(4, $result['drawn']['points'] ?? NULL, 'un glisser trace le rectangle d\'un coin au coin opposé');
		$this->assertTrue($result['drawn']['ready'] ?? FALSE, 'lâché, il s\'enregistre');
		$this->assertTrue($result['drawn']['changed'] ?? FALSE, 'et compte comme un changement');
		$this->assertEquals([], $result['drawn']['moves'] ?? NULL, 'la carte ne glisse pas sous le tracé');

	}

	public function testARowWhosePlaceLeavesTheViewIsLaidInView(): void {

		$result = $this->run(1512, '
			// La vue descend de 150 px : le haut du jardin, d\'où part le rang, sort de la carte
			camera.center[1] -= 150 * SCALE;
			const [[west, south], [east, north]] = map.getBounds().toArray();

			MapDrawing.start({ dataset: gestures.beds });
			const corners = MapDrawingLine.getCorners(MapDrawing.current.shape.points);
			MapDrawing.current.cancel();

			result.inView = corners.every(([lng, lat]) => lng >= west && lng <= east && lat >= south && lat <= north);
			result.inGarden = MapDrawingLine.isInside(corners.slice(0, 3), square);
		');

		$this->assertTrue($result['inView'] ?? FALSE, 'le rang se pose dans la vue, sans que la carte bouge');
		$this->assertTrue($result['inGarden'] ?? FALSE, 'et dans le jardin');

	}

	public function testTheBenchSeesACameraMove(): void {

		$source = file_get_contents(LIME_DIRECTORY.'/production/map/static/js/drawing.js');
		$witness = preg_replace('/\tcancel\(\) \{\n\t\tthis\.shut\(false\);/', "$0\n\t\tthis.map.jumpTo({zoom: 12});", $source, count: $count);

		if($this->assertDecorPresent($count === 1, 'cancel() ouvre sur this.shut(false), où le témoin injecte un mouvement') === FALSE) {
			return;
		}

		$result = $this->run(1512, '
			MapDrawing.start({ dataset: gestures.zoneShape });
			MapDrawing.current.cancel();
			result.moves = [...moves];
			result.zoom = camera.zoom;
		', $witness);

		$this->assertEquals(['jumpTo'], $result['moves'] ?? NULL, 'le banc note un mouvement de caméra');
		$this->assertEquals(12, $result['zoom'] ?? NULL, 'et la caméra factice le suit');

	}

}
?>
