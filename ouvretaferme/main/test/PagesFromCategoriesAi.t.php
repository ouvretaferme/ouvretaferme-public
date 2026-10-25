<?php
namespace main;

/**
 * Le menu des pages voisines d'un en-tête, construit depuis une liste de catégories
 * (`DesignUi::getPagesFromCategories()`).
 *
 * Trois en-têtes recopiaient la même boucle (ventes et clients, analyses de la production, produits)
 * avant qu'elle monte dans `DesignUi`. Le test fixe ce que la boucle promet, puis rend ceux des
 * produits et des analyses de la production.
 *
 * Les en-têtes se rendent sur la structure F1 du décor (`FixtureAiLib`), sans rien écrire.
 *
 * Lancement : dev/test package=main file=PagesFromCategoriesAi
 */
class PagesFromCategoriesAiTest extends \Test {

	const STRUCTURE = \structure\FixtureAiLib::F1;

	public function testCategoriesBecomePages(): void {

		$categories = [
			'first' => 'Fruits & légumes',
			NULL,
			'second' => '<span class="badge">Pro</span>',
		];

		$url = fn(string $key) => '/pages/'.$key;

		$expected = [
			['url' => '/pages/first', 'label' => 'Fruits & légumes', 'selected' => FALSE],
			NULL,
			['url' => '/pages/second', 'label' => '<span class="badge">Pro</span>', 'selected' => TRUE],
		];

		$this->assertEquals(new DesignUi()->getPages($expected), new DesignUi()->getPagesFromCategories($categories, $url, 'second'), 'une catégorie devient une page, NULL un séparateur, et la page choisie est marquée');
		$this->assertEquals(new DesignUi()->getPages($expected, html: TRUE), new DesignUi()->getPagesFromCategories($categories, $url, 'second', html: TRUE), 'le libellé passe tel quel quand l\'appelant le dit');

		$this->assertTrue(str_contains(new DesignUi()->getPagesFromCategories($categories, $url, 'second'), 'Fruits &amp; légumes'), 'sans quoi il est encodé');
		$this->assertEquals('', new DesignUi()->getPagesFromCategories([], $url, 'second'), 'sans catégorie, pas de menu');

	}

	public function testTheHeadersRender(): void {

		$eStructure = \structure\StructureLib::getById(self::STRUCTURE);

		if($this->assertDecorPresent($eStructure->notEmpty(), 'la structure F1 du décor (#'.self::STRUCTURE.')') === FALSE) {
			return;
		}

		$this->assertTrue(\product\HeaderUi::getProducts($eStructure, \structure\Member::CATEGORY) !== '', 'l\'en-tête des produits se rend');
		$this->assertTrue(\series\HeaderUi::getAnalyzeCultivation($eStructure, [2026], 2026, \structure\Member::PLANT) !== '', 'et celui des analyses de la production');

	}

}
?>
