<?php
namespace dev;

/**
 * Le compte de référence se prend par substr_count, jamais par la regex de
 * ApiDocLib::getExposedModules() : les deux moyens doivent rester indépendants, sinon une regex qui
 * cesse de reconnaître une forme d'appel perd des classes de la documentation sans rien faire
 * échouer.
 */
class ApiExposedCountSentinelTest extends \Test {

	public function testExposedCountMatchesPackage(): void {

		$path = \Lime::getPath(LIME_APP).'/package.c.php';
		$content = file_get_contents($path);

		$real = substr_count($content, 'ApiRoute::expose(');
		$found = ApiDocLib::getExposedModules();

		preg_match_all('/ApiRoute::expose\([^;]*;/', $content, $calls);

		$missed = [];

		foreach($calls[0] as $call) {

			preg_match('/\'([^\']+)\'/', $call, $class);

			if(in_array($class[1] ?? NULL, $found, TRUE) === FALSE) {
				$missed[] = trim($call);
			}

		}

		$this->assertEquals($real, count($found), $real.' appel(s) ApiRoute::expose( dans package.c.php, '.count($found).' classe(s) trouvée(s) par getExposedModules() : '.implode(' | ', $missed));

	}

}
?>
