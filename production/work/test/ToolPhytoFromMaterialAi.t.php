<?php
namespace work;

/**
 * Ajouter un produit de traitement qui porte le nom d'un matériel de la structure convertit ce
 * matériel au lieu de refuser un doublon que la liste des produits ne montre pas. Cas vu le
 * 2026-09-29 : un matériel « Essen'Ciel » saisi en avril empêchait d'ajouter le produit
 * « Essen'ciel » du catalogue, l'index unique du nom ignorant la casse.
 *
 * Isolation : une transaction par méthode, annulée par TransactionTest.
 *
 * Lancement : dev/test package=work file=ToolPhytoFromMaterialAi
 */
class ToolPhytoFromMaterialAiTest extends \dev\TransactionTest {

	protected function getTransactionModel(): \ModuleModel {
		return Tool::model();
	}

	private function build(\structure\Structure $eStructure, ?string $routineName, array $input): Tool {

		$eTool = ToolLib::getNewTool($eStructure, $routineName);

		$fw = new \FailWatch();
		$eTool->build(ToolLib::getPropertiesCreate(), $input + ['routineName' => $routineName]);
		$fw->validate(onKo: fn() => throw new \Exception('Tool not built'));

		return $eTool;

	}

	public function testPhytoProductTakesOverMaterialWithSameName(): void {

		$eStructure = \structure\StructureLib::getById(\structure\FixtureAiLib::F1);
		$eAction = ToolLib::getNewTool($eStructure, 'phyto')['action'];

		$name = 'Matériel test '.uniqid();

		$eToolMaterial = $this->build($eStructure, NULL, [
			'name' => $name,
			'action' => $eAction['id'],
			'comment' => 'Observation du matériel',
		]);

		ToolLib::create($eToolMaterial);

		$eToolProduct = $this->build($eStructure, 'phyto', [
			'name' => mb_strtoupper($name),
			'action' => $eAction['id'],
			'stock' => 4,
			'routineValue' => ['dose' => 4, 'unit' => 'L/ha', 'water' => 1000],
		]);

		$fw = new \FailWatch();
		ToolLib::create($eToolProduct);
		$this->assertTrue($fw->ok(), 'la création du produit a échoué');

		$cTool = Tool::model()
			->select(['id', 'name', 'routineName', 'stock', 'comment'])
			->whereStructure($eStructure)
			->whereName($name)
			->whereStatus(Tool::ACTIVE)
			->getCollection();

		$this->assertEquals([$eToolMaterial['id']], $cTool->getIds());

		$eTool = $cTool->first();

		$this->assertEquals('phyto', $eTool['routineName']);
		$this->assertEquals(mb_strtoupper($name), $eTool['name']);
		$this->assertEquals(4.0, (float)$eTool['stock']);
		$this->assertEquals('Observation du matériel', $eTool['comment']);

	}

	/**
	 * Un matériel dont le produit n'est pas au catalogue (Novodor, retiré en 2020) doit pouvoir
	 * devenir un produit saisi à la main. Cas vu le 2026-09-29 : le lien de saisie manuelle
	 * rechargeait la recherche, et le formulaire refusait ensuite faute de nom.
	 */
	public function testMaterialBecomesManualProduct(): void {

		$eStructure = \structure\StructureLib::getById(\structure\FixtureAiLib::F1);
		$eAction = ToolLib::getNewTool($eStructure, 'phyto')['action'];

		$eToolMaterial = $this->build($eStructure, NULL, [
			'name' => 'Matériel test '.uniqid(),
			'action' => $eAction['id'],
		]);

		ToolLib::create($eToolMaterial);

		$eToolForm = ToolLib::getById($eToolMaterial['id']);
		ToolLib::loadPhytoReprise($eToolForm, NULL, TRUE);

		$this->assertTrue($eToolForm['phytoManual']);
		$this->assertTrue($eToolForm['phytoReprise']);

		$eToolForm = ToolLib::getById($eToolMaterial['id']);
		ToolLib::loadPhytoReprise($eToolForm, NULL);

		$this->assertFalse($eToolForm['phytoManual']);

	}

}
?>
