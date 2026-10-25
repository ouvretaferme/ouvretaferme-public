<?php
namespace map;

/**
 * La séance de pose du décor (`static/js/sceneDecor.js`, `MapSceneDecor`), éprouvée sous node telle que le navigateur charge ses
 * fichiers : un retrait ne part qu'à « Terminer », « Annuler » rend l'élément retiré sous son id sans rien écrire, un enclos
 * rechoisi sur son emprise revient sous son id, un calque supprimé emporte ses retraits, et une erreur en ouvrant la pose laisse
 * la scène dans le mode que dit `active`.
 *
 * Aucune écriture, aucune base ouverte : `send()` relève ce qui partirait, et rend la réponse que le scénario lui donne.
 *
 * Lancement : dev/test package=map file=DecorCancelAi
 */
class DecorCancelAiTest extends \Test {

	public function testCancelGivesTheRemovedBackUnderTheirIds(): void {

		$result = $this->run('
			const enclosure = element({ id: 74644, area: { kind: "plot", id: 375 }, model: null, hedge: true });
			const bin = element({ id: 69310 });
			const decor = session([enclosure, bin]);

			decor.selected = enclosure;
			decor.remove();
			decor.selected = bin;
			decor.remove();

			const removed = decor.elements.length;

			decor.cancel();
			await settled();

			emit({ removed, sent: decor.sent, ids: decor.elements.map(e => e.id).sort(), drawn: decor.elements.every(e => e.root !== null), pending: decor.removals.length });
		');

		$this->assertEquals(0, $result['removed'] ?? NULL, "les deux éléments retirés quittent la scène");
		$this->assertEquals([], $result['sent'] ?? NULL, "ni le retrait ni « Annuler » n'écrivent quoi que ce soit");
		$this->assertEquals([69310, 74644], $result['ids'] ?? NULL, "« Annuler » rend les deux éléments sous leur id");
		$this->assertTrue($result['drawn'] ?? FALSE, "les deux éléments rendus sont de nouveau dessinés");
		$this->assertEquals(0, $result['pending'] ?? NULL, "« Annuler » ne laisse aucun retrait à envoyer");

	}

	public function testTheRemovalLeavesAtDone(): void {

		$result = $this->run('
			const enclosure = element({ id: 74644, area: { kind: "plot", id: 375 }, model: "SHEEP", hedge: false });
			const decor = session([enclosure]);

			decor.selected = enclosure;
			decor.remove();

			const before = decor.sent.length;

			decor.done();
			await settled();

			const accepted = { before, sent: decor.sent, elements: decor.elements.length, pending: decor.removals.length };

			const refused = session([element({ id: 69311 })], () => null);
			refused.selected = refused.elements[0];
			refused.remove();
			refused.done();
			await settled();

			emit({ accepted, refused: { sent: refused.sent, ids: refused.elements.map(e => e.id), active: refused.active } });
		');

		$this->assertEquals(0, $result['accepted']['before'] ?? NULL, "le retrait n'écrit rien avant « Terminer »");
		$this->assertEquals([['doDelete', ['id' => 74644]]], $result['accepted']['sent'] ?? NULL, "« Terminer » retire l'élément sous son id");
		$this->assertEquals(0, $result['accepted']['elements'] ?? NULL, "l'élément retiré reste hors de la scène");
		$this->assertEquals(0, $result['accepted']['pending'] ?? NULL, "« Terminer » ne laisse aucun retrait à envoyer");

		$this->assertEquals([['doDelete', ['id' => 69311]]], $result['refused']['sent'] ?? NULL, "« Terminer » envoie le retrait, que le serveur refuse");
		$this->assertEquals([69311], $result['refused']['ids'] ?? NULL, "un retrait refusé remet l'élément en scène");
		$this->assertFalse($result['refused']['active'] ?? TRUE, "le retrait refusé laisse la pose fermée");

	}

	public function testAFenceChosenAgainTakesBackTheRemovedEnclosure(): void {

		$result = $this->run('
			const area = { kind: "plot", id: 375 };
			const enclosure = element({ id: 74644, area, model: null, hedge: true, gate: .25 });
			const decor = session([enclosure]);

			decor.areas = [area];
			decor.enclosing = () => area;
			decor.selected = enclosure;

			// La haie, seule vignette marquée, se décoche : l\'enclos quitte la scène
			decor.fence(current => ({ hedge: current.hedge === false }));

			const removed = { elements: decor.elements.length, sent: decor.sent.length };

			// La ganivelle rechoisie sur la même emprise
			decor.fence(current => ({ model: "paling" }));
			await settled();

			const taken = { sent: decor.sent.splice(0), ids: decor.elements.map(e => e.id), model: enclosure.model, hedge: enclosure.hedge, selected: decor.selected === enclosure, pending: decor.removals.length };

			decor.cancel();
			await settled();

			emit({ removed, taken, cancelled: { sent: decor.sent, ids: decor.elements.map(e => e.id), bare: enclosure.model === null, hedge: enclosure.hedge } });
		');

		$this->assertEquals(['elements' => 0, 'sent' => 0], $result['removed'] ?? NULL, "décocher la dernière vignette retire l'enclos sans rien écrire");

		$this->assertEquals([['doUpdateEnclosure', ['id' => 74644, 'model' => 'paling', 'hedge' => 0, 'gate' => 0.25]]], $result['taken']['sent'] ?? NULL, "l'enclos rechoisi se change sous son id, sans pose ni retrait");
		$this->assertEquals([74644], $result['taken']['ids'] ?? NULL, "l'enclos rechoisi revient dans la scène sous son id");
		$this->assertEquals(['paling', FALSE], [$result['taken']['model'] ?? NULL, $result['taken']['hedge'] ?? NULL], "l'enclos rechoisi porte ce qu'on vient de choisir");
		$this->assertTrue($result['taken']['selected'] ?? FALSE, "l'enclos rechoisi est choisi, sa feuille marque ce qu'il porte");
		$this->assertEquals(0, $result['taken']['pending'] ?? NULL, "l'enclos rechoisi n'a plus de retrait à envoyer");

		$this->assertEquals([['doUpdateEnclosure', ['id' => 74644, 'model' => NULL, 'hedge' => 1, 'gate' => 0.25]]], $result['cancelled']['sent'] ?? NULL, "« Annuler » rend à la base l'enclos d'avant la séance, sous son id");
		$this->assertEquals([74644], $result['cancelled']['ids'] ?? NULL, "après « Annuler », l'enclos est en scène sous son id");
		$this->assertEquals([TRUE, TRUE], [$result['cancelled']['bare'] ?? NULL, $result['cancelled']['hedge'] ?? NULL], "après « Annuler », l'enclos porte sa haie seule");

	}

	public function testARefusedPoseRemovedMeanwhileStaysAway(): void {

		$result = $this->run('
			const decor = session([], route => (route === "doCreate") ? null : 1);
			const bin = element({ kit: "poubelle" });

			Object.assign(decor, { layers: { value: () => "" }, position: () => ({}), sheet: { dataset: { structure: 62 } } });

			decor.elements.push(bin);
			decor.create(bin, true);
			decor.selected = bin;
			decor.remove();
			await settled();

			const refused = { journal: decor.journal.map(operation => operation.type), pending: decor.removals.length };

			decor.cancel();
			await settled();

			emit({ refused, sent: decor.sent.map(([route]) => route), elements: decor.elements.length });
		');

		$this->assertEquals(['journal' => [], 'pending' => 0], $result['refused'] ?? NULL, "une pose refusée après son retrait ne laisse ni geste à défaire ni retrait à envoyer");
		$this->assertEquals(['doCreate'], $result['sent'] ?? NULL, "« Annuler » n'écrit rien pour une pose refusée");
		$this->assertEquals(0, $result['elements'] ?? NULL, "« Annuler » ne remet pas en scène une pose refusée");

	}

	public function testARemovedLayerTakesItsRemovalsAlong(): void {

		$result = $this->run('
			const pump = element({ id: 18000, layer: 9 });
			const bin = element({ id: 69310 });
			const decor = session([pump, bin]);

			decor.selected = pump;
			decor.remove();
			decor.removeLayer(9);
			decor.done();
			await settled();

			const done = decor.sent;

			const again = session([element({ id: 18001, layer: 9 })]);
			again.selected = again.elements[0];
			again.remove();
			again.removeLayer(9);
			again.cancel();
			await settled();

			emit({ done, cancelled: { sent: again.sent, ids: again.elements.map(e => e.id) } });
		');

		$this->assertEquals([], $result['done'] ?? NULL, "« Terminer » ne retire pas un élément que son calque a déjà emporté");
		$this->assertEquals([], $result['cancelled']['sent'] ?? NULL, "« Annuler » n'écrit rien pour un élément dont le calque est supprimé");
		$this->assertEquals([], $result['cancelled']['ids'] ?? NULL, "« Annuler » ne rend pas un élément dont le calque est supprimé");

	}

	public function testSetModeSwitchesEvenWhenEnteringFails(): void {

		$result = $this->run('
			const swapped = [];
			MapScene.swap = (from, to) => swapped.push([from.map(button => button.name), to.map(button => button.name)]);

			const toggled = name => ({ name, classList: { toggle: (token, on) => toggles.push([name, token, on]) } });
			const toggles = [];
			let switched = 0;

			const decor = Object.assign(Object.create(MapSceneDecor.prototype), {
				active: false,
				frozen: null,
				terrain: {},
				zoneTerrains: new Map(),
				grid: { visible: false },
				elements: [],
				sheet: toggled("sheet"),
				timeline: toggled("timeline"),
				button: { name: "decor" },
				cancelButton: { name: "cancel" },
				doneButton: { name: "done" },
				letGo: () => false,
				choose: () => {},
				select: () => {},
				refresh: () => {},
				showThumbnails: () => { throw new Error("vignettes"); },
				switched: () => switched++
			});
			decor.container = Object.assign(toggled("container"), { qs: () => null });

			let thrown = null;

			try {
				decor.setMode(true);
			} catch(error) {
				thrown = error.message;
			}

			emit({ thrown, active: decor.active, switched, grid: decor.grid.visible, swapped, posing: toggles.some(([name, token, on]) => name === "container" && token === "map-scene-posing" && on), timelineHidden: toggles.some(([name, token, on]) => name === "timeline" && token === "hide" && on) });
		');

		$this->assertEquals('vignettes', $result['thrown'] ?? NULL, "l'erreur du remplissage n'est pas tue");
		$this->assertTrue($result['active'] ?? FALSE, "la pose est ouverte");
		$this->assertEquals(1, $result['switched'] ?? NULL, "la scène suit l'ouverture de la pose, plancher et bande compris");
		$this->assertTrue($result['grid'] ?? FALSE, "la grille de la pose se montre");
		$this->assertEquals([[['decor'], ['cancel', 'done']]], $result['swapped'] ?? NULL, "Annuler et Terminer remplacent le bouton Décor");
		$this->assertTrue($result['posing'] ?? FALSE, "la scène prend la mise en page de la pose");
		$this->assertTrue($result['timelineHidden'] ?? FALSE, "la frise laisse sa place au catalogue");

	}

	/**
	 * Joue un scénario sous node avec les fichiers de la scène, et rend ce qu'il émet. `session(elements, answer)` ouvre une pose
	 * sur ces éléments, dont `send()` relève chaque requête dans `sent` et rend `answer(route, values)`, l'id posté par défaut :
	 * seuls les gestes de la séance sont réels, le dessin et la mise en page sont neutres. `element(values)` est un élément du décor
	 * déjà dessiné, et `settled()` attend que les réponses de la file soient lues.
	 */
	private function run(string $scenario): array {

		$script = '
			const directory = '.json_encode(LIME_DIRECTORY).';
			const emit = value => process.stdout.write(JSON.stringify(value));

			(async () => {
				const { readFileSync } = await import("node:fs");
				const vm = await import("node:vm");

				const source = ["base/scene/static/js/plan.js", "base/scene/static/js/crew.js", "production/series/static/js/seriesSheet.js", "production/map/static/js/scene.js", "production/map/static/js/sceneDecor.js", "production/map/static/js/sceneDecorDefault.js"].map(file => readFileSync(directory + "/" + file, "utf8")).join("\n");
				const { MapScene, MapSceneDecor } = vm.runInContext(source + "\n;({ MapScene, MapSceneDecor })", vm.createContext({ window: {}, document: { addEventListener: () => {} }, console }));

				const element = values => ({ id: null, kit: "enclos", area: null, layer: null, model: null, hedge: false, gate: null, seed: null, p: { x: 0, n: 0 }, root: {}, bad: null, ...values });

				const session = (elements, answer = (route, values) => values.id) => {

					const decor = Object.create(MapSceneDecor.prototype);

					return Object.assign(decor, {
						active: true,
						closed: false,
						frozen: null,
						tracing: null,
						selected: null,
						chosen: null,
						elements: [...elements],
						journal: [],
						removals: [],
						fallback: [],
						sent: [],
						terrainOpening: {},
						zoneTerrainOpening: new Map(),
						send: (route, body) => {
							const values = body();
							if(values === null) {
								return Promise.resolve(null);
							}
							decor.sent.push([route, values]);
							return Promise.resolve(answer(route, values));
						},
						describe: e => ({ p: e.p, model: e.model, hedge: e.hedge, gate: e.gate }),
						build: e => { e.root = {}; },
						drop: e => { e.root = null; },
						select: e => { decor.selected = e; },
						choose: area => { decor.chosen = area; },
						restore: (e, state) => Object.assign(e, state),
						letGo: () => false,
						adopt: () => {},
						queueWalk: () => {},
						refresh: () => {},
						refuseFence: () => {},
						chooseTerrain: () => {},
						setMode: on => { decor.active = on; }
					});

				};

				const settled = () => new Promise(resolve => setTimeout(resolve, 0));

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
