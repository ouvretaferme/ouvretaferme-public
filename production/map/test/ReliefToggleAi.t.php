<?php
namespace map;

/**
 * La bascule du relief dans la scène (`static/js/scene.js`, `MapScene::toggleRelief()` et `MapScene::restart()`), éprouvée
 * sous node telle que le navigateur charge le fichier : éteindre ou rallumer le relief remonte la scène sans quitter l'écran où
 * elle était. La semaine choisie reste, le décor vient d'une scène relue après les écritures de la séance, et le conteneur,
 * élément du plein écran, n'est pas remplacé.
 *
 * Le document, la requête et le conteneur sont des doublures écrites dans le scénario : aucune base, aucun appel.
 *
 * Lancement : dev/test package=map file=ReliefToggleAi
 */
class ReliefToggleAiTest extends \Test {

	public function testTheSceneComesBackOnTheSameScreen(): void {

		$result = $this->run();

		$restart = $result['restart'];

		$this->assertTrue($restart['sameContainer'], 'le conteneur reste, et avec lui le plein écran');
		$this->assertTrue($restart['closed'], 'la scène d\'avant se ferme');
		$this->assertEquals(23, $restart['week'], 'la semaine choisie passe sur le curseur neuf');
		$this->assertEquals([9], $restart['decor'], 'la scène montée est celle relue');
		$this->assertTrue($restart['childrenFresh'], 'les nœuds de la page repartent de ceux qu\'elle a rendus');
		$this->assertEquals('10', $restart['pristineWeek'], 'le conteneur d\'origine n\'est pas touché');
		$this->assertEquals(1, $restart['rendered'], 'la scène se remonte une fois');

		$toggle = $result['toggle'];

		$this->assertEquals('/map/scene:get?id=6986&season=2026', $toggle['url'], 'la scène se relit au serveur, pour sa structure et sa saison');
		$this->assertTrue($toggle['afterWrites'], 'derrière les écritures du décor encore en file');
		$this->assertEquals([[7], 24, [['fiche' => 'page']]], $toggle['restarted'], 'remontée sur la scène relue, à la semaine montrée à son retour, avec l\'équipe de la page');
		$this->assertEquals([TRUE], $toggle['remembered'], 'le choix se retient');

		$this->assertEquals([[], []], $result['failed'], 'un échec ne retient ni ne remonte rien');
		$this->assertEquals([[], []], $result['closedMeanwhile'], 'une scène fermée pendant la lecture ne remonte rien');

		$this->assertTrue($result['fullscreenReleased'], 'la fermeture retire l\'écouteur du plein écran du conteneur qui reste');

	}

	private function run(): array {

		$script = '
			const directory = '.json_encode(LIME_DIRECTORY).';
			const { readFileSync } = require("node:fs");
			const vm = require("node:vm");

			// Une doublure de nœud : ce que restart() et close() lisent du conteneur et de ses enfants
			class Node {
				constructor(attributes = {}, children = []) {
					this.attributes = attributes;
					this.children = children;
					this.dataset = {};
					this.value = attributes.value ?? null;
					this.listeners = [];
					this.rendered = 0;
				}
				get childNodes() { return [...this.children]; }
				cloneNode() { const clone = new Node({ ...this.attributes }, this.children.map(child => child.cloneNode())); clone.dataset = { ...this.dataset }; clone.value = this.value; return clone; }
				replaceChildren(...children) { this.children = children; }
				qs(selector) { return this.children.find(child => selector === "[" + Object.keys(child.attributes)[0] + "]") ?? null; }
				addEventListener(type, listener) { this.listeners.push([type, listener]); }
				removeEventListener(type, listener) { this.listeners = this.listeners.filter(([t, l]) => t !== type || l !== listener); }
				parseRender() { this.rendered++; }
			}

			const calls = { urls: [], remembered: [], restarted: [] };
			let respond = () => Promise.resolve({ scene: { decor: [{ id: 7 }] } });
			let onFetch = () => {};

			const Ajax = { Query: class {
				method() { return this; }
				url(url) { this.address = url; return this; }
				fetch() { calls.urls.push([this.address, writes.done]); onFetch(); return respond(); }
			} };

			const writes = { done: false };
			const document = { addEventListener: () => {}, createElement: () => ({ getContext: () => new Proxy({}, { get: () => () => ({}) }) }) };
			const source = ["base/scene/static/js/plan.js", "base/scene/static/js/crew.js", "production/series/static/js/seriesSheet.js", "production/map/static/js/scene.js"].map(file => readFileSync(directory + "/" + file, "utf8")).join("\n");
			const { MapScene, MapSceneRelief } = vm.runInContext(source + "\n;({ MapScene, MapSceneRelief })", vm.createContext({ window: {}, document, console, Ajax, clearInterval }));

			(async () => {

				// restart() : un conteneur ouvert, la page rendue à la semaine 10
				const page = () => new Node({}, [new Node({ "data-scene-week": true, value: "10" }), new Node({ "data-scene-directory": true })]);
				const container = page();
				const before = container.children;
				let closed = false;

				MapScene.pristine = page();
				MapScene.current = { container, close: () => { closed = true; } };
				MapScene.restart({ decor: [{ id: 9 }] }, 23);

				const restart = {
					sameContainer: MapScene.current === null && container.rendered === 1,
					closed,
					week: Number(container.qs("[data-scene-week]").value),
					decor: JSON.parse(container.dataset.scene).decor.map(row => row.id),
					childrenFresh: container.children.every(child => before.includes(child) === false),
					pristineWeek: MapScene.pristine.qs("[data-scene-week]").value,
					rendered: container.rendered
				};

				// toggleRelief() : une écriture du décor encore en file quand on bascule
				const restartOriginal = MapScene.restart;
				const rememberOriginal = MapSceneRelief.remember;
				MapScene.restart = (scene, week) => calls.restarted.push([scene.decor.map(row => row.id), week, scene.team]);
				MapSceneRelief.remember = (node, shown) => calls.remembered.push(shown);

				const scene = new MapScene(new Node());
				scene.week = 23;
				scene.decor = { queue: new Promise(resolve => setTimeout(() => { writes.done = true; resolve(); }, 10)) };

				const plan = { structure: 6986, season: 2026, team: [{ fiche: "page" }] };

				// La semaine change pendant la lecture : la scène revient à celle que l\'on montre alors
				onFetch = () => { scene.week = 24; };
				await scene.toggleRelief(plan, true);
				onFetch = () => {};

				const toggle = { url: calls.urls[0][0], afterWrites: calls.urls[0][1], restarted: calls.restarted[0], remembered: calls.remembered };

				// Un échec de la lecture
				calls.restarted = []; calls.remembered = [];
				respond = () => Promise.reject("");
				await scene.toggleRelief(plan, false);
				const failed = [calls.restarted, calls.remembered];

				// Une navigation pendant la lecture
				respond = () => { scene.closed = true; return Promise.resolve({ scene: { decor: [] } }); };
				await scene.toggleRelief(plan, false);
				const closedMeanwhile = [calls.restarted, calls.remembered];

				MapScene.restart = restartOriginal;
				MapSceneRelief.remember = rememberOriginal;

				// close() : l écouteur du plein écran posé sur un conteneur qui survit à la scène
				const kept = new Node();
				const mounted = new MapScene(kept);
				mounted.onFullscreen = () => {};
				kept.addEventListener("fullscreenchange", mounted.onFullscreen);
				mounted.close();

				process.stdout.write(JSON.stringify({ restart, toggle, failed, closedMeanwhile, fullscreenReleased: kept.listeners.length === 0 }));

			})().catch(error => { process.stdout.write(JSON.stringify({ error: String(error.stack) })); });
		';

		$file = tempnam(sys_get_temp_dir(), 'relief-toggle-');
		file_put_contents($file, $script);

		$output = shell_exec('node '.escapeshellarg($file).' 2>&1');
		unlink($file);

		$result = json_decode((string)$output, TRUE);

		if(is_array($result) === FALSE or array_key_exists('error', $result)) {
			throw new \Exception('Le scénario n\'a pas rendu : '.$output);
		}

		return $result;

	}

}
?>
