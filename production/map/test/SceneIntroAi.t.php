<?php
namespace map;

/**
 * Le survol d'ouverture de la scène (`static/js/scene.js`, `MapSceneAmbience.startIntro()`, `fly()` et `landIntro()`), éprouvé
 * sous node avec three tel que le navigateur le charge : le premier geste pose le cadrage d'arrivée au lieu d'arrêter la vue en vol.
 *
 * Aucune écriture, aucune base ouverte.
 *
 * Lancement : dev/test package=map file=SceneIntroAi
 */
class SceneIntroAiTest extends \Test {

	public function testTheFirstGestureLandsTheIntro(): void {

		$result = $this->run('
			const ambience = new MapSceneAmbience(THREE, {}, { center: null });
			const camera = new THREE.OrthographicCamera(-50, 50, 50, -50, 1, 5000);
			const controls = { target: new THREE.Vector3(10, 0, -20), minZoom: .5 };

			camera.position.set(110, 100, 80);
			camera.zoom = 4;
			camera.lookAt(controls.target);

			const arrival = camera.position.toArray();

			ambience.startIntro(camera, controls);
			ambience.fly(1000, camera, controls);
			ambience.fly(2000, camera, controls);

			const inFlight = { zoom: camera.zoom, moved: camera.position.distanceTo(new THREE.Vector3(...arrival)) > 1 };

			listeners.get("pointerdown")();

			const round = values => values.map(value => Math.round(value * 1000) / 1000);

			emit({
				inFlight,
				zoom: camera.zoom,
				position: round(camera.position.toArray()),
				arrival: round(arrival),
				ended: ambience.intro === null,
				listening: [...listeners.keys()]
			});
		');

		$this->assertTrue(($result['inFlight']['zoom'] ?? 4) < 4, "à mi-survol, la vue n'a pas encore son zoom d'arrivée");
		$this->assertTrue($result['inFlight']['moved'] ?? FALSE, "à mi-survol, la vue n'est pas encore à sa place d'arrivée");
		$this->assertEquals(4, $result['zoom'] ?? NULL, "le premier geste pose le zoom d'arrivée");
		$this->assertEquals($result['arrival'] ?? NULL, $result['position'] ?? NULL, "le premier geste pose la vue à sa place d'arrivée");
		$this->assertTrue($result['ended'] ?? FALSE, "le survol est fini après le premier geste");
		$this->assertEquals([], $result['listening'] ?? NULL, "le survol fini n'écoute plus aucun geste");

	}

	/**
	 * Joue un scénario sous node avec les fichiers de la scène chargés comme le navigateur les charge, et rend ce qu'il émet.
	 * `listeners` porte ce que la scène écoute sur la fenêtre, par type d'événement.
	 */
	private function run(string $scenario): array {

		$script = '
			const directory = '.json_encode(LIME_DIRECTORY).';
			const emit = value => process.stdout.write(JSON.stringify(value));

			// Les crochets de la cuisson font lire three en module ES, qu\'aucun package.json ne déclare tel
			require("node:module").register("file://" + directory + "/base/scene/bake/resolve.mjs");

			(async () => {
				const { readFileSync } = await import("node:fs");
				const vm = await import("node:vm");
				const THREE = await import("file://" + directory + "/base/scene/static/lib/three-0.170.0.module.min.js");

				const listeners = new Map();
				const window = {
					addEventListener: (type, listener) => listeners.set(type, listener),
					removeEventListener: type => listeners.delete(type)
				};

				const source = ["base/scene/static/js/plan.js", "base/scene/static/js/crew.js", "production/series/static/js/seriesSheet.js", "production/map/static/js/scene.js"].map(file => readFileSync(directory + "/" + file, "utf8")).join("\n");
				const context = vm.createContext({ window, document: { addEventListener: () => {}, createElement: () => ({}) }, console });
				const { MapSceneAmbience } = vm.runInContext(source + "\n;({ MapSceneAmbience })", context);

				'.$scenario.'
			})();
		';

		$process = proc_open(['node', '-'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, NULL, ['PATH' => getenv('PATH')]);

		if($this->assertTrue(is_resource($process), "node ne se lance pas ici : ce test éprouve la scène sous node, l'installer sur la machine qui lance les tests") === FALSE) {
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
