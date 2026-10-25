<?php
namespace dev;

/**
 * Lancement : dev/test package=dev file=TestDiff
 */
class TestDiffTest extends \Test {

	public function testReadPatch(): void {

		$patch = <<<'PATCH'
diff --git a/framework/dev/lib/Old.l.php b/framework/dev/lib/New.l.php
--- a/framework/dev/lib/Old.l.php
+++ b/framework/dev/lib/New.l.php
@@ -1 +1 @@
-a
+b
diff --git a/production/map/static/css/map.css b/production/map/static/css/map.css
--- /dev/null
+++ b/production/map/static/css/map.css
@@ -0,0 +1 @@
+--- a/ne/compte/pas.php
PATCH;

		$this->assertEquals(['framework/dev/lib/Old.l.php', 'framework/dev/lib/New.l.php', 'production/map/static/css/map.css'], TestDiffLib::readPatch($patch), 'les deux côtés d\'un renommage, sans /dev/null ni une ligne ajoutée qui ressemble à un en-tête');

	}

	public function testGetPaths(): void {

		$this->assertEquals(['framework/dev/test/CLAUDE.md', 'framework/dev/test/ClaudeSizeSentinel.t.php'], TestDiffLib::getPaths('95834a7c9'), 'un commit désigne les fichiers qu\'il change');
		$this->assertEquals(['framework/dev/lib/Test.l.php', 'framework/dev/test/CLAUDE.md', 'framework/dev/test/ClaudeSizeSentinel.t.php'], TestDiffLib::getPaths('95834a7c9,framework/dev/lib/Test.l.php'), 'un fichier du dépôt s\'ajoute au commit');
		$this->assertEquals(NULL, TestDiffLib::getPaths('ni-commit-ni-fichier'), 'une entrée qui ne désigne rien refuse la borne au lieu de la vider');

	}

	public function testGetSentinels(): void {

		$sentinels = TestDiffLib::getSentinels(['framework/dev/lib/Sentinel.l.php']);
		$all = array_filter(array_keys(SentinelLib::getFiles('.t.php', FALSE)), fn(string $file) => fnmatch('*Sentinel*.t.php', basename($file)));

		$this->assertEquals(array_values($all), array_keys($sentinels), 'le socle des tests les joue toutes');
		$this->assertEquals(['socle des tests'], array_values(array_unique($sentinels)));

		$sentinels = TestDiffLib::getSentinels(['ouvretaferme/main/static/css/component.css']);

		$this->assertEquals('lit .css', $sentinels['framework/dev/test/CssSentinel.t.php'] ?? NULL, 'un suffixe de getFiles() désigne la sentinelle');
		$this->assertEquals('toujours jouée', $sentinels['framework/dev/test/AnchorSentinel.t.php'] ?? NULL);
		$this->assertEquals(FALSE, isset($sentinels['framework/dev/test/ClockSentinel.t.php']), 'une sentinelle qui ne lit que du PHP ne se joue pas pour une feuille de style');

		$sentinels = TestDiffLib::getSentinels(['base/structure/lib/Structure.l.php']);

		$this->assertEquals('dans base/structure', $sentinels['base/structure/test/CommunityFeatureSentinelAi.t.php'] ?? NULL, 'une sentinelle sans getFiles() se joue pour son package');

	}

}
?>
