<?php
namespace scene;

/**
 * Le profil d'un abri (`ScenePlanGeometry.greenhouse()`, `static/js/plan.js`), éprouvé sous node tel que le navigateur charge le
 * fichier. Le modèle d'un abri est facultatif (`map\Plot.decorGreenhouse`) : sans modèle, l'abri se montre à pieds droits, et
 * aucune scène ne tombe sur lui.
 *
 * Aucune écriture, aucune base ouverte.
 *
 * Lancement : dev/test package=scene file=GreenhouseAi
 */
class GreenhouseAiTest extends \Test {

	public function testWithoutTypeIsStraight(): void {

		$result = $this->run('
			emit({
				none: ScenePlanGeometry.greenhouse(null, 8),
				straight: ScenePlanGeometry.greenhouse("straight", 8)
			});
		');

		$this->assertEquals($result['straight'] ?? NULL, $result['none'] ?? NULL, 'un abri sans modèle a le profil à pieds droits');

	}

	private function run(string $scenario): array {

		$script = '
			const directory = '.json_encode(LIME_DIRECTORY).';
			const emit = value => process.stdout.write(JSON.stringify(value));

			const { readFileSync } = require("node:fs");
			const vm = require("node:vm");

			const source = readFileSync(directory + "/base/scene/static/js/plan.js", "utf8");
			const { ScenePlanGeometry } = vm.runInContext(source + "\n;({ ScenePlanGeometry })", vm.createContext({ window: {}, document: { addEventListener: () => {} }, console }));

			'.$scenario.'
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
