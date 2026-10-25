<?php
namespace map;

/**
 * « Continuer » sur un tracé posé (`static/js/sceneDecor.js`, `MapSceneDecor`), éprouvé sous node : il ne se propose que sur un
 * point d'extrémité choisi, prolonge depuis ce point, à la fin depuis le dernier et avant le premier depuis le premier, et Échap
 * n'y retire que les points neufs avant de rendre le tracé d'avant. Suppr sur un bout d'un tracé de deux points, qui se choisit
 * désormais pour « Continuer », retire le tracé.
 *
 * Aucune écriture, aucune base ouverte : `update()` relève ce qui partirait.
 *
 * Lancement : dev/test package=map file=DecorContinueAi
 */
class DecorContinueAiTest extends \Test {

	public function testOnlyAnEndOffersToContinue(): void {

		$result = $this->run('
			const ends = (line, points) => {
				const decor = session(line);
				return points.map(point => {
					decor.point = point;
					return decor.chosenEnd();
				});
			};

			emit({ three: ends(LINE, [0, 1, 2, null]), two: ends(LINE.slice(0, 2), [0, 1]) });
		');

		$this->assertEquals(['first', NULL, 'last', NULL], $result['three'] ?? NULL, "sur trois points, seuls le premier et le dernier proposent « Continuer », ni le milieu ni le tracé entier");
		$this->assertEquals(['first', 'last'], $result['two'] ?? NULL, "sur deux points, chacun est un bout");

	}

	public function testAMiddlePointStartsNothing(): void {

		$result = $this->run('
			const middle = session(LINE);
			middle.point = 1;
			middle.extend();

			const whole = session(LINE);
			whole.point = null;
			whole.extend();

			emit({ middle: middle.tracing === null, whole: whole.tracing === null });
		');

		$this->assertTrue($result['middle'] ?? FALSE, "un point du milieu ne reprend pas le tracé");
		$this->assertTrue($result['whole'] ?? FALSE, "le tracé entier ne se reprend pas");

	}

	public function testFromTheLastPointNewPointsGoAtTheEnd(): void {

		$result = $this->run('
			const decor = session(LINE);
			decor.point = 2;
			decor.extend();
			decor.plot({ x: 30, n: 0 });
			decor.plot({ x: 40, n: 5 });
			decor.endTrace();

			emit({ line: line(decor.selected), updates: decor.updates.length, tracing: decor.tracing === null });
		');

		$this->assertEquals([[0, 0], [10, 0], [20, 0], [30, 0], [40, 5]], $result['line'] ?? NULL, "depuis le dernier point, les points neufs s'ajoutent à la fin");
		$this->assertEquals(1, $result['updates'] ?? NULL, "la reprise s'écrit d'une seule mise à jour");
		$this->assertTrue($result['tracing'] ?? FALSE, "la reprise est terminée");

	}

	public function testFromTheFirstPointNewPointsGoBefore(): void {

		$result = $this->run('
			const decor = session(LINE);
			decor.point = 0;
			decor.extend();
			decor.plot({ x: -10, n: 0 });

			const drawn = line(decor.selected);

			decor.plot({ x: -20, n: 5 });
			decor.endTrace();

			emit({ drawn, line: line(decor.selected), updates: decor.updates.length });
		');

		$this->assertEquals([[-10, 0], [0, 0], [10, 0], [20, 0]], $result['drawn'] ?? NULL, "pendant le geste, le tracé garde déjà son sens");
		$this->assertEquals([[-20, 5], [-10, 0], [0, 0], [10, 0], [20, 0]], $result['line'] ?? NULL, "depuis le premier point, les points neufs s'ajoutent avant, et le tracé garde son sens");
		$this->assertEquals(1, $result['updates'] ?? NULL, "la reprise s'écrit d'une seule mise à jour");

	}

	public function testEscapeRemovesOnlyTheNewPoints(): void {

		$result = $this->run('
			const steps = [];
			const decor = session(LINE);
			const before = decor.selected.trace;

			decor.point = 0;
			decor.extend();
			decor.plot({ x: -10, n: 0 });
			decor.plot({ x: -20, n: 0 });

			// Le pointeur quitte la scène : la ligne ne va plus jusqu\'à lui, elle s\'arrête à ses points
			decor.aimTrace(null);

			decor.unplot();
			steps.push(line(decor.selected));
			decor.unplot();
			steps.push(line(decor.selected));
			decor.unplot();

			emit({ steps, line: line(decor.selected), restored: decor.selected.trace === before, tracing: decor.tracing === null, updates: decor.updates.length });
		');

		$this->assertEquals([[[-10, 0], [0, 0], [10, 0], [20, 0]], [[0, 0], [10, 0], [20, 0]]], $result['steps'] ?? NULL, "Échap retire les points neufs, du dernier posé au premier");
		$this->assertEquals([[0, 0], [10, 0], [20, 0]], $result['line'] ?? NULL, "Échap ne retire aucun point du tracé repris");
		$this->assertTrue($result['restored'] ?? FALSE, "le dernier Échap rend la ligne d'avant");
		$this->assertTrue($result['tracing'] ?? FALSE, "et abandonne la reprise");
		$this->assertEquals(0, $result['updates'] ?? NULL, "une reprise abandonnée n'écrit rien");

	}

	public function testDeleteOnAnEndOfTwoPointsRemovesTheTrace(): void {

		$result = $this->run('
			const erased = (points, point) => {
				const decor = session(points);
				const calls = [];
				Object.assign(decor, { point, remove: () => calls.push("remove"), unbend: () => calls.push("unbend") });
				decor.erase();
				return calls;
			};

			emit({ two: erased(LINE.slice(0, 2), 1), three: erased(LINE, 2), none: erased(LINE, null) });
		');

		$this->assertEquals(['remove'], $result['two'] ?? NULL, "Suppr sur un bout d'un tracé de deux points retire le tracé, ce point ne pouvant pas se retirer");
		$this->assertEquals(['unbend'], $result['three'] ?? NULL, "Suppr sur un point d'un tracé de trois points retire ce point");
		$this->assertEquals(['remove'], $result['none'] ?? NULL, "Suppr sans point choisi retire le tracé");

	}

	/**
	 * Joue un scénario sous node avec les fichiers de la scène, et rend ce qu'il émet. `session(points)` ouvre une pose sur un chemin
	 * de ces points, choisi, dont `update()` relève chaque écriture dans `updates` ; le dessin et la mise en page sont neutres, et
	 * le pointeur d'un clic est le point du plan qu'il vise. `line(e)` rend la ligne d'un tracé en paires `[x, n]`, et `LINE` est un
	 * chemin droit de trois points.
	 */
	private function run(string $scenario): array {

		$script = '
			const directory = '.json_encode(LIME_DIRECTORY).';
			const emit = value => process.stdout.write(JSON.stringify(value));

			(async () => {
				const { readFileSync } = await import("node:fs");
				const vm = await import("node:vm");

				const source = ["base/scene/static/js/plan.js", "base/scene/static/js/crew.js", "production/series/static/js/seriesSheet.js", "production/map/static/js/scene.js", "production/map/static/js/sceneLayer.js", "production/map/static/js/sceneDecor.js"].map(file => readFileSync(directory + "/" + file, "utf8")).join("\n");
				const { MapSceneDecor } = vm.runInContext(source + "\n;({ MapSceneDecor })", vm.createContext({ window: {}, document: { addEventListener: () => {} }, console }));

				const LINE = [{ x: 0, n: 0 }, { x: 10, n: 0 }, { x: 20, n: 0 }];

				const line = e => MapSceneDecor.lineOf(e).map(q => [q.x, q.n]);

				const session = points => {

					const decor = Object.create(MapSceneDecor.prototype);
					const e = { ...MapSceneDecor.element("chemin", points[0]), id: 1, bad: null };

					decor.lay(e, points);

					return Object.assign(decor, {
						active: true,
						tracing: null,
						selected: e,
						point: null,
						elements: [e],
						updates: [],
						sheet: { dataset: { length: "{value} m" } },
						pick: event => event,
						build: () => {},
						settle: () => {},
						drop: () => {},
						tell: () => {},
						hush: () => {},
						refresh: () => {},
						showTracing: () => {},
						update: (e, before, after) => decor.updates.push(after)
					});

				};

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
