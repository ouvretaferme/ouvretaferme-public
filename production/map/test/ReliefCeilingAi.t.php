<?php
namespace map;

/**
 * Le plafond du sol en relief (`static/js/scene.js`, `MapSceneRelief.GROUND_TRIANGLES`), éprouvé sous node telle que le navigateur
 * charge le fichier : l'emprise des îlots n'a pas de borne, le sol en a une. Ses pas grandissent jusqu'à tenir dessous, et pas
 * au-delà : une structure qui tient au pas de la grille le garde.
 *
 * Aucune écriture, aucune base ouverte : les emprises sont écrites dans le scénario.
 *
 * Lancement : dev/test package=map file=ReliefCeilingAi
 */
class ReliefCeilingAiTest extends \Test {

	public function testTheGroundStaysUnderItsCeiling(): void {

		$result = $this->run();

		$this->assertTrue(($result['ceiling'] ?? 0) > 0, 'le plafond du sol est lu');

		// Un îlot de 800 m de côté, une emprise carrée de 40 tuiles de 264 m, deux îlots de 20 tuiles chacun à 2 km l'un de l'autre en
		// diagonale, deux petits îlots aux deux bouts de l'emprise, et 40 tuiles par axe. Seuls les pas sous les îlots grandiraient
		// sans fin entre les deux petits îlots, où le sol n'a que des pas longs
		foreach(['island' => 'un îlot de 800 m', 'square' => 'une emprise carrée de 40 tuiles', 'apart' => 'deux îlots éloignés', 'scattered' => 'deux petits îlots aux deux bouts de l\'emprise', 'axis' => '40 tuiles par axe'] as $case => $label) {

			$this->assertTrue(($result[$case]['triangles'] ?? PHP_INT_MAX) <= $result['ceiling'], 'le sol tient sous le plafond pour '.$label);
			$this->assertTrue(($result[$case]['pitch'] ?? 0) > ($result['step'] ?? 0), 'le pas grandit pour '.$label);
			$this->assertTrue(($result[$case]['finer'] ?? 0) > $result['ceiling'], 'le pas est le plus fin qui tienne pour '.$label);
			$this->assertTrue(($result[$case]['across'] ?? 0) >= 3, 'chaque îlot garde des sommets en travers pour '.$label.' : '.($result[$case]['across'] ?? 'rien'));

		}

		$this->assertTrue(($result['small']['pitch'] ?? 0) === ($result['step'] ?? -1), 'une structure qui tient sous le plafond garde le pas de la grille');
		$this->assertTrue(($result['small']['triangles'] ?? PHP_INT_MAX) <= $result['ceiling'], 'son sol tient sous le plafond');

	}

	private function run(): array {

		$script = '
			const directory = '.json_encode(LIME_DIRECTORY).';
			const emit = value => process.stdout.write(JSON.stringify(value));

			const { readFileSync } = require("node:fs");
			const vm = require("node:vm");

			const document = { addEventListener: () => {}, createElement: () => ({ getContext: () => new Proxy({}, { get: () => () => ({}) }) }) };
			const source = ["base/scene/static/js/plan.js", "base/scene/static/js/crew.js", "production/series/static/js/seriesSheet.js", "production/map/static/js/scene.js"].map(file => readFileSync(directory + "/" + file, "utf8")).join("\n");
			const { MapSceneRelief } = vm.runInContext(source + "\n;({ MapSceneRelief })", vm.createContext({ window: {}, document, console }));

			const triangles = (xs, ns) => 2 * (xs.length - 1) * (ns.length - 1);

			// La portée des îlots déborde leurs plateaux de 100 m, et le sol plat suffit : seul le nombre de sommets est éprouvé
			const lay = plateaus => {

				const reach = plateaus.flat();
				const relief = Object.create(MapSceneRelief.prototype);

				Object.assign(relief, {
					x0: Math.min(...reach.map(p => p.x)) - 100,
					x1: Math.max(...reach.map(p => p.x)) + 100,
					n0: Math.min(...reach.map(p => p.n)) - 100,
					n1: Math.max(...reach.map(p => p.n)) + 100,
					base: 0,
					altitude: () => 0
				});
				relief.lattice({ land: { islands: plateaus.map(plateau => ({ plateau })) } });

				const spans = axis => plateaus.map(points => [Math.min(...points.map(p => p[axis])), Math.max(...points.map(p => p[axis]))]);
				const finer = relief.pitch - 1;

				// Le moins de sommets que l\'un des îlots porte en travers, sur l\'un ou l\'autre axe
				const across = Math.min(...["x", "n"].flatMap(axis => spans(axis).map(([a, b]) => (axis === "x" ? relief.xs : relief.ns).filter(value => value >= a && value <= b).length)));

				return {
					pitch: relief.pitch,
					across,
					triangles: triangles(relief.xs, relief.ns),
					finer: triangles(MapSceneRelief.axis(relief.x0, relief.x1, spans("x"), finer), MapSceneRelief.axis(relief.n0, relief.n1, spans("n"), finer))
				};

			};

			emit({
				ceiling: MapSceneRelief.GROUND_TRIANGLES,
				step: MapSceneRelief.STEP,
				island: lay([[{ x: -400, n: -400 }, { x: 400, n: 400 }]]),
				square: lay([[{ x: -735, n: -735 }, { x: 735, n: 735 }]]),
				apart: lay([[{ x: -1500, n: -1500 }, { x: -300, n: -300 }], [{ x: 300, n: 300 }, { x: 1500, n: 1500 }]]),
				scattered: lay([[{ x: -5000, n: -5000 }, { x: -4900, n: -4900 }], [{ x: 4900, n: 4900 }, { x: 5000, n: 5000 }]]),
				axis: lay([[{ x: -5180, n: -5180 }, { x: 5180, n: 5180 }]]),
				small: lay([[{ x: -20, n: -20 }, { x: 20, n: 10 }]])
			});
		';

		$process = proc_open(['timeout', '60', 'node', '-'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, NULL, ['PATH' => getenv('PATH')]);

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

		$this->assertTrue(is_array($result), 'node rend son résultat en moins de 60 s : '.$output.$errors);

		return $result ?? [];

	}

}
?>
