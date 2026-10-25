<?php
namespace scene;

/**
 * Le lancement de node pour la conversion (`UploadLib::runNode()`) : une sonde lancée comme le serait `upload.mjs` ne reçoit
 * ni les connexions que PHP a ouvertes à la base et à Redis, ni son environnement. La même sonde lancée directement les
 * reçoit, sans quoi une sonde cassée rendrait le test vert.
 *
 * La sonde n'imprime que des nombres. Aucune écriture en base.
 *
 * Lancement : dev/test package=scene file=UploadAi
 */
class UploadAiTest extends \Test {

	const PROBE = <<<'JS'
		import { readdirSync, readlinkSync } from 'node:fs';
		import { spawn } from 'node:child_process';

		if(process.argv[2] === 'flood') {
			process.stdout.write('x'.repeat(100000));
			process.exit(0);
		}

		if(process.argv[2] === 'detach') {
			const child = spawn('/bin/sleep', ['30'], {detached: true, stdio: ['ignore', 'inherit', 'ignore']});
			child.unref();
			process.stdout.write(JSON.stringify({pid: child.pid}));
			process.exit(0);
		}

		const sockets = readdirSync('/proc/self/fd').filter(descriptor => {
			try {
				return readlinkSync('/proc/self/fd/' + descriptor).startsWith('socket:');
			} catch(e) {
				return false;
			}
		}).length;

		process.stdout.write(JSON.stringify({sockets, environment: Object.keys(process.env).length}));
		JS;

	private array $files = [];

	public function tearDown(): void {

		foreach($this->files as $file) {
			unlink($file);
		}

		$this->files = [];

	}

	public function testNodeInheritsNothing(): void {

		$this->openConnections();

		$result = $this->runNode($this->write(self::PROBE), [], 1000);

		$this->assertEquals(0, $result['code'], 'la sonde tourne jusqu\'au bout');

		$out = json_decode($result['output'], TRUE);

		// node ouvre lui-même des tubes et des eventfd, mais aucune socket avant qu'on la lui demande
		$this->assertEquals(0, $out['sockets'], 'node n\'hérite d\'aucune connexion de PHP');
		$this->assertEquals(0, $out['environment'], 'node ne reçoit pas l\'environnement de PHP');

	}

	public function testProbeSeesInheritanceWithoutHardening(): void {

		$this->openConnections();

		$process = proc_open(['node', '--input-type=module', '-'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, NULL, ['PATH' => getenv('PATH'), 'HOME' => getenv('HOME')]);

		fwrite($pipes[0], self::PROBE);
		fclose($pipes[0]);

		$out = json_decode(stream_get_contents($pipes[1]), TRUE);

		fclose($pipes[1]);
		proc_close($process);

		$this->assertTrue($out['sockets'] > 0, 'lancée directement, la sonde hérite des connexions de PHP');
		$this->assertTrue($out['environment'] > 0, 'lancée directement, la sonde reçoit l\'environnement passé');

	}

	public function testOutputLimit(): void {

		$result = $this->runNode($this->write(self::PROBE), ['flood'], 1000);

		$this->assertEquals(-1, $result['code'], 'une sortie au-delà du plafond tue le processus');
		$this->assertEquals('', $result['output'], 'une sortie au-delà du plafond n\'est pas rendue');

	}

	/**
	 * Un processus que node détache dans sa propre session en gardant la sortie standard ouverte ne retient pas la lecture : elle
	 * s'arrête quand node sort, bien avant que le détaché ne rende le tube.
	 */
	public function testDetachedChildDoesNotHoldTheRead(): void {

		$start = hrtime(TRUE);
		$result = $this->runNode($this->write(self::PROBE), ['detach'], 1000);
		$duration = (hrtime(TRUE) - $start) / 1000000000;

		$out = json_decode($result['output'], TRUE);

		if(is_array($out)) {
			posix_kill($out['pid'], 9);
		}

		$this->assertTrue($duration < UploadLib::TIMEOUT, 'la lecture rend la main sans attendre le processus détaché ('.round($duration, 1).' s)');
		$this->assertEquals(0, $result['code'], 'node sort normalement');
		$this->assertTrue(is_array($out), 'la sortie de node est lue en entier');

	}

	/**
	 * Le plafond de mémoire virtuelle laisse à node le double de ce qu'il lui faut pour convertir un modèle : sous la moitié
	 * de `UploadLib::MEMORY`, le modèle conforme du skill se convertit encore. Sous un plafond qui ne contient pas la réserve
	 * de V8, node meurt au démarrage et chaque fichier sort refusé en `format`, le conforme compris, ce qui est arrivé en
	 * production sous node 20 avec 512 Mo.
	 */
	public function testMemoryLeavesMargin(): void {

		$sample = LIME_DIRECTORY.'/base/scene/upload/avatar/exemples/conforme.glb';
		$node = new \ReflectionMethod(UploadLib::class, 'getNode')->invoke(NULL);

		$command = 'ulimit -c 0; ulimit -v '.intdiv(UploadLib::MEMORY, 2).'; exec env -i '.escapeshellarg($node).' --max-old-space-size='.UploadLib::HEAP
			.' '.escapeshellarg(LIME_DIRECTORY.'/base/scene/upload/upload.mjs').' --bake'
			.' '.escapeshellarg('--rules='.LIME_DIRECTORY.'/base/scene/upload/avatar.json')
			.' '.escapeshellarg('--in='.$sample);

		$process = proc_open(['bash', '-c', $command], [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
		$output = stream_get_contents($pipes[1]);
		fclose($pipes[1]);
		$code = proc_close($process);

		$this->assertEquals(0, $code, 'node convertit le modèle conforme sous la moitié du plafond de mémoire virtuelle');
		$this->assertEquals(json_decode(file_get_contents(LIME_DIRECTORY.'/base/scene/upload/avatar/exemples/attendu.json'), TRUE)['conforme.glb']['triangles'], json_decode($output, TRUE)['triangles'] ?? NULL, 'la conversion sous la moitié du plafond va jusqu\'au bout');

	}

	// Une requête et une lecture ouvrent les connexions de ce processus à la base et à Redis
	private function openConnections(): void {

		\user\User::model()->select('id')->get();
		\Cache::redis()->get('upload-probe');

	}

	private function runNode(string $script, array $arguments, int $outputLimit): array {
		return new \ReflectionMethod(UploadLib::class, 'runNode')->invoke(NULL, $script, $arguments, $outputLimit);
	}

	// Lancée par son chemin, la sonde est un module ES grâce à son extension
	private function write(string $content): string {

		$file = tempnam(sys_get_temp_dir(), 'upload-probe');
		rename($file, $file.'.mjs');
		file_put_contents($file.'.mjs', $content);

		$this->files[] = $file.'.mjs';

		return $file.'.mjs';

	}

}
?>
