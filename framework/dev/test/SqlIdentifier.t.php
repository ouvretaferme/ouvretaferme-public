<?php
namespace dev;

/**
 * L'échappement d'un identifiant SQL (nom de base, de table, de colonne).
 *
 * `ModuleModel::field()` et `MySQLApi::field()` protégeaient le backtick par `addcslashes()`, donc par
 * un antislash. MySQL n'y voit pas un échappement : dans un identifiant entre backticks, le backtick se
 * double et l'antislash est un caractère comme un autre. Un identifiant portant un backtick fermait
 * donc la citation. Aucun nom de colonne ne vient de l'utilisateur aujourd'hui, l'échappement doit
 * néanmoins être le bon pour le jour où l'un en viendra.
 *
 * Lancement : dev/test package=dev file=SqlIdentifier
 */
class SqlIdentifierTest extends \Test {

	public function testTheModelDoublesTheBacktick(): void {

		$m = \user\User::model();

		$this->assertEquals('`a``b`', $m->field('a`b'), 'un backtick est doublé');
		$this->assertEquals('`a\\b`', $m->field('a\\b'), 'un antislash reste tel quel');
		$this->assertEquals('`m1`.`name`', $m->field('m1.name'), 'le point sépare l\'alias de la colonne');
		$this->assertEquals('`m1`.`a``b`', $m->field('m1.a`b'), 'les deux à la fois');

	}

	public function testTheApiDoublesTheBacktick(): void {

		$api = new \Database('user')->api;

		$this->assertEquals('`a``b`', $api->field('a`b'), 'un backtick est doublé');
		$this->assertEquals('`structure_7`', $api->field('structure_7'), 'un nom ordinaire est cité tel quel');

	}

}
?>
