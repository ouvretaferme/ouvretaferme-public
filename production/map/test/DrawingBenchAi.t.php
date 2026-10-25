<?php
namespace map;

/**
 * Le banc des gestes de la carte (`static/js/drawing.js`, `MapDrawing`) : le fichier chargé sous node tel que le navigateur
 * le charge, avec turf et cartography.js. La carte est factice, un plan de 800 × 500 px où un pixel vaut un millionième de
 * degré, et chaque appel qui déplace la caméra est noté et la déplace. Le document, les marqueurs et la fiche répondent à
 * tout sans rien rendre. Un scénario peut dire ce que la carte rend sous un point (`rendered`) et ce que répond l'envoi du
 * geste (`answer`, les corps envoyés dans `posts`), changer la largeur de la fenêtre (`context.window.innerWidth`), et lit
 * les écouteurs posés sur le document (`listeners`). Il s'écrit
 * dans une fonction asynchrone, où il attend une réponse.
 *
 * Aucune écriture, aucune base ouverte.
 */
abstract class DrawingBenchAiTest extends \Test {

	protected function run(int $width, string $scenario, ?string $source = NULL): array {

		$script = '
			const directory = '.json_encode(LIME_DIRECTORY).';
			const width = '.$width.';
			const source = '.json_encode($source).';

			const { readFileSync } = require("node:fs");
			const vm = require("node:vm");

			// Les libellés que la fiche porte en data-*, et sa pose sur la carte, que drawing.js ne prend qu’au-dessus de 768 px
			const labels = new Proxy({}, { get: (target, key) => (key === "drawingPose") ? "" : "{value}" });

			// Ce qui répond à tout : un appel qui reçoit une fonction la rappelle, comme qs() quand il trouve
			const any = new Proxy(function() {}, {
				get: (target, key) => {
					if(key === Symbol.toPrimitive) return () => 0;
					if(key === Symbol.iterator) return function*() {};
					if(key === "then") return undefined;
					if(key === "length") return 0;
					if(key === "dataset") return labels;
					return any;
				},
				apply: (target, self, args) => {
					const callback = args.find(arg => typeof arg === "function" && arg !== any);
					if(callback !== undefined) callback(any);
					return any;
				},
				construct: () => any
			});

			// Un pixel vaut un millionième de degré autour du centre
			const SCALE = 1e-6;
			const camera = { center: [3.1, 45.6], zoom: 18 };
			const moves = [];

			// Ce que la carte rend sous un point, que le scénario peut remplacer, et les envois du geste, qui répondent `answer`
			let rendered = () => [];
			let answer = {};
			const posts = [];

			// Les écouteurs que drawing.js pose sur le document
			const listeners = [];

			const map = {
				getCenter: () => ({ lng: camera.center[0], lat: camera.center[1], toArray: () => [...camera.center] }),
				getZoom: () => camera.zoom,
				getPadding: () => ({ top: 0, right: 0, bottom: 0, left: 0 }),
				project: ([lng, lat]) => ({ x: 400 + (lng - camera.center[0]) / SCALE, y: 250 - (lat - camera.center[1]) / SCALE }),
				// Un point se donne en tableau ou en objet, comme le PointLike de Mapbox
				unproject: (point) => {
					const [x, y] = Array.isArray(point) ? point : [point.x, point.y];
					return { lng: camera.center[0] + (x - 400) * SCALE, lat: camera.center[1] - (y - 250) * SCALE, toArray() { return [this.lng, this.lat]; } };
				},
				getBounds: () => ({ toArray: () => [[camera.center[0] - 400 * SCALE, camera.center[1] - 250 * SCALE], [camera.center[0] + 400 * SCALE, camera.center[1] + 250 * SCALE]] }),
				getContainer: () => ({ getBoundingClientRect: () => ({ left: 0, top: 0, right: 800, bottom: 500, width: 800, height: 500 }), querySelector: () => null }),
				getCanvas: () => ({ style: {} }),
				queryRenderedFeatures: (point, options) => rendered(point, options),
				on: () => {}, off: () => {}, once: () => {},
				doubleClickZoom: { enable: () => {}, disable: () => {} }
			};

			// Tout ce qui déplace une caméra Mapbox, que la carte factice suit d’un cran de zoom à défaut d’en lire la cible
			["jumpTo", "fitBounds", "easeTo", "flyTo", "panBy", "panTo", "setCenter", "setZoom", "zoomTo", "setPadding", "setBearing", "setPitch", "resetNorth"].forEach(name => map[name] = (target) => {
				moves.push(name);
				camera.zoom = (typeof target === "object" && target !== null && target.zoom !== undefined) ? target.zoom : camera.zoom + 1;
			});

			// L’encart de la page est la carte moins dix pixels de chaque côté
			const carto = new Proxy({ mapbox: map, season: 2026, drawingHandler: null, inset: { left: 10, top: 10, right: 790, bottom: 490, controls: null }, isMarkerClick: () => false }, { get: (target, key) => (key in target) ? target[key] : any });

			// Le jardin, un carré de 400 px au centre de la carte, et ses dix planches de 20 m sur 80 cm, cochées
			const square = [[3.0998, 45.5998], [3.1002, 45.5998], [3.1002, 45.6002], [3.0998, 45.6002], [3.0998, 45.5998]];
			const checked = Array.from({ length: 10 }, (_, index) => ({ value: String(index + 1), dataset: { name: "P"+ (index + 1), length: "20", width: "80", alley: "50" } }));
			const fields = { number: "4", length: "20", width: "80", alleyWidth: "50" };
			// Les quatre planches à créer, un nom par planche comme le panneau les écrit (MapBed.changeNumber())
			const named = Array.from({ length: 4 }, (_, index) => ({ value: "P"+ (index + 1) }));

			const form = (dataset, nodes) => new Proxy({ dataset, qsa: () => nodes, qs: selector => ({ value: fields[selector.match(/name="([^"]+)"/)?.[1]] ?? "", max: "200" }) }, { get: (target, key) => (key in target) ? target[key] : any[key] });
			const forms = { beds: form({}, checked), create: form({}, named) };

			const qs = (selector, callback) => {
				const node = forms[selector.startsWith("#") ? selector.slice(1) : null] ?? any;
				if(callback !== undefined) callback(node);
				return node;
			};

			const context = vm.createContext({
				carto,
				window: { innerWidth: width, addEventListener: () => {} },
				document: { documentElement: { lang: "fr" }, delegateEventListener: () => {}, addEventListener: (type, listener, capture) => listeners.push({ type, listener, capture }), createElement: () => any, body: any },
				qs, qsa: () => [],
				mapboxgl: { Marker: function() { return any; } },
				Lime: any,
				Ajax: { Query: class { url() { return this; } method() { return this; } body(body) { posts.push(body); return this; } fetch() { return Promise.resolve(answer); } } },
				ResizeObserver: class { observe() {} disconnect() {} },
				getComputedStyle: () => any,
				setTimeout,
				console
			});

			vm.runInContext(readFileSync(directory + "/production/map/static/lib/turf-7.2.0.min.js", "utf8"), context);
			vm.runInContext(readFileSync(directory + "/production/map/static/js/cartography.js", "utf8") + "\n;MapCartography.get = () => carto;\n;Object.assign(this, { MapCartography });", context);
			vm.runInContext((source ?? readFileSync(directory + "/production/map/static/js/drawing.js", "utf8")) + "\n;Object.assign(this, { MapDrawing, MapDrawingEmbedded, MapDrawingPolygon, MapDrawingLine, MapDrawingCopy, MapDrawingPac });", context);

			const { MapCartography, MapDrawing, MapDrawingEmbedded, MapDrawingPolygon, MapDrawingLine, MapDrawingCopy, MapDrawingPac, turf } = context;

			const gestures = {
				zoneCreate: { drawing: "rectangle", drawingMap: "map", drawingSheet: "zone-create", drawingCoordinates: "null", drawingFree: "" },
				zoneShape: { drawing: "polygon", drawingMap: "map", drawingSheet: "zone", drawingCoordinates: JSON.stringify(square), drawingAnchor: JSON.stringify(square) },
				plotCreate: { drawing: "rectangle", drawingMap: "map", drawingCoordinates: "null", drawingContour: JSON.stringify(square), drawingFree: "" },
				beds: { drawing: "line", drawingMap: "map", drawingCoordinates: "null", drawingId: "1", drawingForm: "beds", drawingContour: JSON.stringify(square), drawingLast: "null" },
				bedsCreate: { drawing: "line", drawingMap: "map", drawingCoordinates: "null", drawingId: "1", drawingForm: "create", drawingCreate: "", drawingContour: JSON.stringify(square), drawingLast: "null" }
			};

			const result = {};

			(async () => {

				'.$scenario.'

				process.stdout.write(JSON.stringify(result));

			})().catch(error => process.stderr.write(String(error.stack)));
		';

		$process = proc_open(['node', '-'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, NULL, ['PATH' => getenv('PATH')]);

		if($this->assertTrue(is_resource($process), "node ne se lance pas ici : ce test éprouve le geste sous node, l'installer sur la machine qui lance les tests") === FALSE) {
			return [];
		}

		fwrite($pipes[0], $script);
		fclose($pipes[0]);

		$output = stream_get_contents($pipes[1]);
		$errors = stream_get_contents($pipes[2]);

		fclose($pipes[1]);
		fclose($pipes[2]);
		proc_close($process);

		$result = json_decode($output, TRUE);

		$this->assertTrue(is_array($result), 'node rend son résultat : '.$output.$errors);

		return $result ?? [];

	}

}
?>
