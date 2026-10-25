<?php
namespace dev;

/**
 * Vérifie Test::assertArraySimilar() (framework/core/Test.php), qui plantait en TypeError sur tout
 * appel : helper récursif sans return, array_key_exists() à un argument, $includePath non défini.
 *
 * Les cas négatifs enregistrent des échecs sur l'instance qui les évalue : on les évalue donc sur
 * une instance annexe (AssertArraySimilarAiProbe), dont TestLib ne rapporte pas le score — il
 * n'instancie que la classe qui porte le nom du fichier.
 *
 * Lancement : dev/test package=plant file=AssertArraySimilarAi
 */
class AssertArraySimilarAiTest extends \Test {

	protected AssertArraySimilarAiProbe $probe;

	public function setUp(): void {
		$this->probe = new AssertArraySimilarAiProbe();
	}

	public function testIdentical(): void {
		$this->assertTrue($this->probe->check(['a' => 1, 'b' => 2], ['a' => 1, 'b' => 2]));
	}

	public function testOrderDoesNotMatter(): void {
		$this->assertTrue($this->probe->check(['a' => 1, 'b' => 2], ['b' => 2, 'a' => 1]));
	}

	public function testNested(): void {
		$this->assertTrue($this->probe->check(['a' => ['b' => ['c' => 3]]], ['a' => ['b' => ['c' => 3]]]));
	}

	public function testEmpty(): void {
		$this->assertTrue($this->probe->check([], []));
	}

	public function testMissingKey(): void {
		$this->assertFalse($this->probe->check(['a' => 1, 'b' => 2], ['a' => 1]));
	}

	public function testUnexpectedKey(): void {
		$this->assertFalse($this->probe->check(['a' => 1], ['a' => 1, 'b' => 2]));
	}

	public function testDifferentValue(): void {
		$this->assertFalse($this->probe->check(['a' => 1], ['a' => 2]));
	}

	/** Une seule différence au fond d'un tableau imbriqué doit remonter jusqu'en haut. */
	public function testNestedDifference(): void {
		$this->assertFalse($this->probe->check(['a' => ['b' => 1]], ['a' => ['b' => 2]]));
	}

	public function testNotAnArray(): void {
		$this->assertFalse($this->probe->check(['a' => 1], 'pas un tableau'));
	}

}

class AssertArraySimilarAiProbe extends \Test {

	public function check($expected, $compareTo): bool {
		return $this->assertArraySimilar($expected, $compareTo);
	}

}
?>
