<?php
namespace main;

/**
 * Le commit d'une installation se lit dans les fichiers de git, sous les quatre formes qu'un dépôt
 * prend : la branche dans son fichier, la branche rangée dans `packed-refs`, HEAD détaché pendant un
 * rebase, et un dépôt absent.
 *
 * Aucun cas ne touche la base ni le réseau : chacun monte son dépôt dans un répertoire temporaire.
 *
 * Lancement : dev/test package=main file=HeadAi
 */
class HeadAiTest extends \Test {

	const HEAD = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
	const UPSTREAM = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

	private string $directory;

	public function setUp(): void {

		$this->directory = sys_get_temp_dir().'/main-head-'.uniqid();

		mkdir($this->directory.'/refs/heads', 0777, TRUE);
		mkdir($this->directory.'/refs/remotes/origin', 0777, TRUE);

	}

	public function tearDown(): void {

		foreach(['HEAD', 'packed-refs', 'refs/heads/main', 'refs/remotes/origin/main'] as $file) {
			if(is_file($this->directory.'/'.$file)) {
				unlink($this->directory.'/'.$file);
			}
		}

		foreach(['refs/remotes/origin', 'refs/remotes', 'refs/heads', 'refs', ''] as $directory) {
			rmdir($this->directory.'/'.$directory);
		}

	}

	public function testBranchInItsFile() {

		file_put_contents($this->directory.'/HEAD', 'ref: '.HeadLib::BRANCH."\n");
		file_put_contents($this->directory.'/'.HeadLib::BRANCH, self::HEAD."\n");
		file_put_contents($this->directory.'/'.HeadLib::UPSTREAM, self::UPSTREAM."\n");

		$this->assertEquals(['head' => self::HEAD, 'upstream' => self::UPSTREAM], HeadLib::get($this->directory));

	}

	public function testPackedReferences() {

		file_put_contents($this->directory.'/HEAD', 'ref: '.HeadLib::BRANCH."\n");
		file_put_contents($this->directory.'/packed-refs', '# pack-refs with: peeled fully-peeled sorted'."\n".self::HEAD.' '.HeadLib::BRANCH."\n".self::UPSTREAM.' '.HeadLib::UPSTREAM."\n");

		$this->assertEquals(['head' => self::HEAD, 'upstream' => self::UPSTREAM], HeadLib::get($this->directory));

	}

	/** Le fichier de la branche l'emporte sur `packed-refs`, que git ne réécrit pas à chaque commit. */
	public function testFileWinsOverPackedReferences() {

		file_put_contents($this->directory.'/HEAD', 'ref: '.HeadLib::BRANCH."\n");
		file_put_contents($this->directory.'/'.HeadLib::BRANCH, self::HEAD."\n");
		file_put_contents($this->directory.'/packed-refs', self::UPSTREAM.' '.HeadLib::BRANCH."\n");

		$this->assertEquals(self::HEAD, HeadLib::get($this->directory)['head']);

	}

	public function testDetachedHead() {

		file_put_contents($this->directory.'/HEAD', self::HEAD."\n");

		$this->assertEquals(['head' => self::HEAD, 'upstream' => NULL], HeadLib::get($this->directory));

	}

	public function testNoRepository() {

		$this->assertEquals(['head' => NULL, 'upstream' => NULL], HeadLib::get($this->directory.'/absent'));

	}

	/** Ce qui n'a pas la forme d'un commit n'est pas rendu : la route ne recopie pas un fichier. */
	public function testAnythingButACommitIsDropped() {

		file_put_contents($this->directory.'/HEAD', 'ref: '.HeadLib::BRANCH."\n");
		file_put_contents($this->directory.'/'.HeadLib::BRANCH, 'autre chose'."\n");

		$this->assertEquals(NULL, HeadLib::get($this->directory)['head']);

	}

}
?>
