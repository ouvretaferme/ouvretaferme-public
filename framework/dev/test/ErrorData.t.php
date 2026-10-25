<?php
namespace dev;

/**
 * Les données d'une erreur, telles que la page des erreurs et le terminal les impriment.
 *
 * Le rendu ne lisait qu'un niveau et imprimait « array » pour toute valeur composée. Un contrôle
 * d'intégrité, dont chaque anomalie est un tableau, ne montrait donc rien d'autre que « 0: array »
 * (cron du 2026-09-19 sur la structure 4875).
 *
 * Lancement : dev/test package=dev file=ErrorData
 */
class ErrorDataTest extends \Test {

	public function testEachValueGetsItsPath(): void {

		$data = [
			[
				'reconciliation' => 2,
				'cashflow' => 322,
				'issue' => 'le règlement ne porte aucune opération bancaire',
			],
		];

		$this->assertEquals(
			[
				'0.reconciliation' => '2',
				'0.cashflow' => '322',
				'0.issue' => 'le règlement ne porte aucune opération bancaire',
			],
			ErrorPhpLib::flattenData($data),
			'chaque valeur d\'une anomalie est imprimée sous son chemin'
		);

	}

	public function testScalarsKeepTheirName(): void {

		$data = ['structure' => 4875, 'done' => TRUE, 'skipped' => FALSE, 'reason' => NULL, 'ratio' => 1.5];

		$this->assertEquals(
			['structure' => '4875', 'done' => 'TRUE', 'skipped' => 'FALSE', 'reason' => 'NULL', 'ratio' => '1.5'],
			ErrorPhpLib::flattenData($data),
			'un booléen et une valeur absente se lisent, là où le rendu précédent imprimait 1 et rien'
		);

	}

	public function testAnEmptyArrayIsNamed(): void {

		$this->assertEquals(['ids' => '[]'], ErrorPhpLib::flattenData(['ids' => []]), 'une liste vide se dit, elle ne disparaît pas');

	}

	public function testDeepValuesAreReached(): void {

		$this->assertEquals(
			['a.b.c' => '3'],
			ErrorPhpLib::flattenData(['a' => ['b' => ['c' => 3]]]),
			'la descente ne s\'arrête pas au deuxième niveau'
		);

	}

	public function testAnElementIsNamedByItsClassAndId(): void {

		$data = ['user' => new \user\User(['id' => 4875])];

		$this->assertEquals(
			['user' => 'user\User #4875'],
			ErrorPhpLib::flattenData($data),
			'un élément dit sa classe et son identifiant, là où le rendu précédent imprimait « object »'
		);

	}

}
?>
