<?php
namespace work;

/**
 * Un matériel sans routine peut devenir le produit dont l'action est la sienne : un matériel de
 * traitement un produit de traitement, un matériel de fertilisation un intrant. La fiche de
 * modification le propose (ToolUi::update()), et la page n'accepte la conversion que vers cette
 * routine-là.
 *
 * Lancement : dev/test package=work file=ToolProductRoutineAi
 */
class ToolProductRoutineAiTest extends \Test {

	private function getTool(?string $actionFqn, ?string $routineName): Tool {

		$eAction = ($actionFqn === NULL) ? new Action() : new Action(['fqn' => $actionFqn]);

		return new Tool([
			'action' => $eAction,
			'routineName' => $routineName,
		]);

	}

	public function testProductRoutineFollowsAction(): void {

		$this->assertEquals('phyto', $this->getTool(ACTION_TRAITEMENT, NULL)->getProductRoutine());
		$this->assertEquals('fertilizer', $this->getTool(ACTION_FERTILISATION, NULL)->getProductRoutine());
		$this->assertEquals(NULL, $this->getTool(ACTION_SEMIS_PEPINIERE, NULL)->getProductRoutine());
		$this->assertEquals(NULL, $this->getTool(NULL, NULL)->getProductRoutine());

	}

	public function testToolWithRoutineIsNotConverted(): void {

		$this->assertEquals(NULL, $this->getTool(ACTION_TRAITEMENT, 'sprayer')->getProductRoutine());
		$this->assertEquals(NULL, $this->getTool(ACTION_TRAITEMENT, 'phyto')->getProductRoutine());

	}

}
?>
