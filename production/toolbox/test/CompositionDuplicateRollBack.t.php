<?php
namespace toolbox;

/**
 * Deux tailles du même nom dans un seul envoi (lot r40) : le contrôle `CompositionFormula::name.duplicate`
 * compare chaque taille à la base, où aucune des deux ne porte encore ce nom, et c'est l'unicité
 * `(plan, name)` du schéma qui refuse la seconde. Le départ (`CompositionPlanLib::create()`) et
 * « Modifier les paniers » (`CompositionFormulaLib::updateByPlan()`) ont alors déjà écrit, et annulent
 * tout par `rollBack()` avant de lever le refus.
 *
 * ⚠️ Ce `rollBack()` remet la pile du serveur à 0 quel que soit l'étage (`framework/core/Module.php`),
 * donc il défait aussi la transaction du test, et le décor avec elle : d'où `CLOSED_BY_TEST`, et la
 * classe à part, pour ne pas l'étendre aux cas de `CompositionDuplicateTest`. Rien ne s'écrit après
 * le refus, chaque cas ne faisant plus que lire : que plus aucune transaction n'est ouverte, et que
 * la structure, qui n'avait pas de plan avant le test, n'en a toujours pas.
 *
 * Lancement : dev/test package=toolbox file=CompositionDuplicateRollBack
 */
class CompositionDuplicateRollBackTest extends CompositionDuplicateFixtureTest {

	const CLOSED_BY_TEST = TRUE;

	/**
	 * Le départ avec deux tailles du même nom n'écrit rien : ni plan, ni taille, ni espèce.
	 */
	public function testStartWritesNothing(): void {

		$eStructure = $this->getStructureWithoutPlan();

		$eCompositionPlan = CompositionPlanLib::getForStart($eStructure, 2026);
		$eCompositionPlan['weeks'] = range(10, 40);
		$eCompositionPlan['reference'] = CompositionPlan::COMMUNITY;
		$eCompositionPlan['climate'] = NULL;
		$eCompositionPlan['cCompositionFormula']->first()->merge(['name' => 'Le panier', 'price' => 18.0, 'baskets' => 10]);
		$eCompositionPlan['cCompositionFormula'][] = new CompositionFormula(['name' => 'Le panier', 'price' => 30.0, 'baskets' => 5]);

		$this->assertEquals(['CompositionFormula::name.duplicate'], $this->getFails(fn() => CompositionPlanLib::create($eCompositionPlan)), 'le départ lève le refus du contrôle');
		$this->assertFalse(CompositionPlan::model()->inTransaction(), 'le refus a annulé toute la pile');
		$this->assertTrue(CompositionPlanLib::getByStructure($eStructure)->empty(), 'aucun plan ne reste de l\'envoi refusé');

	}

	/**
	 * « Modifier les paniers » qui donne le même nom neuf à deux tailles ne garde rien de l'envoi : la
	 * première, déjà renommée quand la base refuse la seconde, est annulée avec elle.
	 */
	public function testSettingsWritesNothing(): void {

		$eCompositionPlan = $this->createStarted();

		CompositionFormulaLib::add($this->getFormula($eCompositionPlan, 'Le grand panier', 30.0));

		$cCompositionFormula = CompositionFormulaLib::getByPlan($eCompositionPlan);
		$cCompositionFormula->setColumn('plan', $eCompositionPlan);

		$this->assertEquals(2, $cCompositionFormula->count(), 'deux tailles à renommer : le cas est monté');

		// Les noms se posent sans `build()` : chacun y passerait, « Le panier commun » n'étant porté par
		// aucune taille en base, et c'est l'écriture des deux qu'on éprouve.
		$cCompositionFormula->setColumn('name', 'Le panier commun');

		$this->assertEquals(['CompositionFormula::name.duplicate'], $this->getFails(fn() => CompositionFormulaLib::updateByPlan($eCompositionPlan, $cCompositionFormula)), 'l\'envoi lève le refus du contrôle');
		$this->assertFalse(CompositionFormula::model()->inTransaction(), 'le refus a annulé toute la pile');
		$this->assertEquals(0, CompositionFormula::model()->wherePlan($eCompositionPlan)->whereName('Le panier commun')->count(), 'la première taille renommée n\'a pas survécu au refus de la seconde');

	}

	/**
	 * Les refus que porte la `FailAction` levée par `$call`, `[]` quand rien n'est levé.
	 */
	private function getFails(\Closure $call): array {

		try {
			$call();
		} catch(\FailAction $action) {

			$fail = new \ReflectionProperty(\FailAction::class, 'fail')->getValue($action);

			// Le refus est porté par son nom complet, `toolbox\CompositionFormula::name.duplicate`.
			return [substr($fail, strlen(__NAMESPACE__) + 1)];

		}

		return [];

	}

}
?>
