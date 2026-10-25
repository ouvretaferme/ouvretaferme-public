<?php
namespace design;

/**
 * L'adresse de la page design system, avec ou sans barre finale.
 *
 * La page déclare la route `/design/` : c'est la déclaration qui donne à `/design` la redirection vers
 * `/design/` (`Route::getCompatiblePages()`), là où une page jointe par la seule convention
 * `package/index` répond 404 sans barre finale. Le sous-domaine `design.` réécrit sa racine en
 * `/design/` (`main/conf/rewrite/dev.cfg`), qui doit continuer de tomber sur la route sans détour.
 *
 * Lancement : dev/test package=design file=DesignRouteAi
 */
class DesignRouteAiTest extends \Test {

	public function testWithoutTrailingSlash(): void {

		$pages = \Route::getCompatiblePages('design', TRUE);

		$this->assertEquals(1, count($pages), '/design trouve une route');

		$page = current($pages);

		$this->assertEquals('design/index', $page[0], '/design mène à la page design system');
		$this->assertEquals('/design/', $page[1], 'par la route déclarée avec barre finale');
		$this->assertTrue($page[3] !== NULL, '/design est redirigé vers l\'adresse avec barre finale');

	}

	public function testWithTrailingSlash(): void {

		$pages = \Route::getCompatiblePages('design/', TRUE);

		$this->assertEquals(1, count($pages), '/design/ trouve une route');

		$page = current($pages);

		$this->assertEquals('design/index', $page[0], '/design/ mène à la page design system');
		$this->assertEquals(NULL, $page[3], 'sans redirection, ce que la racine du sous-domaine demande');

	}

}
?>
