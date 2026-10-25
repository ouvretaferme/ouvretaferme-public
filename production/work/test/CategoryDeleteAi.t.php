<?php
namespace work;

/**
 * Suppression d'une catégorie de travail : le refus « indispensable » ne vaut que pour les catégories
 * que series lit (WorkSetting::CATEGORIES_STRUCTURELLES), plus pour toute catégorie qui porte un fqn.
 *
 * Commercialisation et Administration ont reçu un fqn pour que les tableaux de bord les
 * reconnaissent, sans devenir indispensables : elles restent supprimables quand rien ne les cite.
 *
 * Chaque test crée au plus une catégorie sur F1, et init() vérifie que WorkSetting::CATEGORIES_LIMIT
 * lui en laisse la place : une seconde création pourrait être refusée. Tout est annulé en fin de
 * test. La catégorie Administration de la structure n'est pas utilisée : citée par des actions ou
 * des interventions, elle ne prouverait rien sur la suppression.
 *
 * Lancement : dev/test package=work file=CategoryDeleteAi
 */
class CategoryDeleteAiTest extends \Test {

	const STRUCTURE = \structure\FixtureAiLib::F1;

	private \structure\Structure $eStructure;

	public function init(): void {

		$this->eStructure = \structure\Structure::model()
			->select(\structure\Structure::getSelection())
			->whereId(self::STRUCTURE)
			->get();

		if($this->eStructure->empty()) {
			throw new \Exception('Structure de référence #'.self::STRUCTURE.' introuvable : sème le décor par structure/configure/fixtureAi write=1, ou importe la copie de production pour une structure réelle');
		}

		$categories = Category::model()
			->whereStructure($this->eStructure)
			->count();

		if($categories >= WorkSetting::CATEGORIES_LIMIT) {
			throw new \Exception('La structure de référence #'.self::STRUCTURE.' porte '.$categories.' catégories, la limite de '.WorkSetting::CATEGORIES_LIMIT.' empêche ces tests d\'en créer une');
		}

	}

	public function setUp(): void {
		new CategoryModel()->beginTransaction();
	}

	public function tearDown(): void {
		new CategoryModel()->rollBack();
	}

	public function testStructuralCategoryIsRefused(): void {

		$eCategory = $this->createCategory('Culture test', CATEGORIE_CULTURE);

		$fw = new \FailWatch();
		CategoryLib::delete($eCategory);

		$this->assertTrue($fw->has('Category::deleteMandatory'), 'une catégorie '.CATEGORIE_CULTURE.' reste indispensable');
		$this->assertTrue($this->exists($eCategory), 'la catégorie '.CATEGORIE_CULTURE.' est toujours là');

	}

	public function testKeyedCategoryIsDeletableOnceUnused(): void {

		$eCategory = $this->createCategory('Administration test', CATEGORIE_ADMINISTRATION);

		$eAction = new Action([
			'structure' => $this->eStructure,
			'fqn' => NULL
		]);

		$fw = new \FailWatch();

		$eAction->build(ActionLib::getPropertiesCreate(), [
			'name' => 'Action test',
			'categories' => [$eCategory['id']],
			'color' => ''
		]);

		ActionLib::create($eAction);

		$this->assertTrue($fw->ok(), 'l\'action citant la catégorie est créée : '.$fw);

		$fw = new \FailWatch();
		CategoryLib::delete($eCategory);

		$this->assertFalse($fw->has('Category::deleteMandatory'), 'une catégorie '.CATEGORIE_ADMINISTRATION.' n\'est pas indispensable');
		$this->assertTrue($fw->has('Category::deleteUsed'), 'une catégorie citée par une action est refusée comme utilisée');
		$this->assertTrue($this->exists($eCategory), 'la catégorie citée est toujours là');

		$fw = new \FailWatch();
		ActionLib::delete($eAction);
		CategoryLib::delete($eCategory);

		$this->assertTrue($fw->ok(), 'une fois l\'action retirée, la catégorie '.CATEGORIE_ADMINISTRATION.' se supprime : '.$fw);
		$this->assertFalse($this->exists($eCategory), 'la catégorie '.CATEGORIE_ADMINISTRATION.' a disparu');

	}

	public function testUnkeyedCategoryIsDeletable(): void {

		$eCategory = $this->createCategory('Sans clé test', NULL);

		$fw = new \FailWatch();
		CategoryLib::delete($eCategory);

		$this->assertTrue($fw->ok(), 'une catégorie sans fqn se supprime : '.$fw);
		$this->assertFalse($this->exists($eCategory), 'la catégorie sans fqn a disparu');

	}

	public function testManageOffersDeleteExceptForStructural(): void {

		$cCategory = new \Collection([
			new Category(['id' => 1, 'name' => 'Culture', 'fqn' => CATEGORIE_CULTURE, 'position' => 1]),
			new Category(['id' => 2, 'name' => 'Administration', 'fqn' => CATEGORIE_ADMINISTRATION, 'position' => 2]),
			new Category(['id' => 3, 'name' => 'Divers', 'fqn' => NULL, 'position' => 3]),
		]);

		$html = new CategoryUi()->getManage($this->eStructure, $cCategory);

		preg_match_all('/data-ajax="\/work\/category:doDelete"[^>]* post-id="(\d+)"/', $html, $matches);

		$this->assertEquals(['2', '3'], $matches[1], 'corbeilles actives pour Administration et la catégorie sans clé, aucune pour Culture');

		preg_match_all('/data-ajax="\/work\/category:doIncrementPosition" post-id="(\d+)"/', $html, $matches);

		$this->assertEquals(['1', '2', '2', '3'], $matches[1], 'les flèches de position postent un id entre guillemets');

	}

	/** Crée la catégorie par le Lib, puis pose sa clé par le modèle, le fqn n'étant pas une propriété construite. */
	private function createCategory(string $name, ?string $fqn): Category {

		$eCategory = new Category([
			'structure' => $this->eStructure
		]);

		$fw = new \FailWatch();

		$eCategory->build(CategoryLib::getPropertiesCreate(), ['name' => $name]);
		CategoryLib::create($eCategory);

		if($fw->ko()) {
			throw new \Exception('Création de la catégorie « '.$name.' » refusée : '.$fw);
		}

		Category::model()->update($eCategory, ['fqn' => $fqn]);

		$eCategory['fqn'] = $fqn;

		return $eCategory;

	}

	private function exists(Category $eCategory): bool {

		return Category::model()
			->whereId($eCategory)
			->exists();

	}

}
?>
