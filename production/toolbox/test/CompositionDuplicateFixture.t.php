<?php
namespace toolbox;

/**
 * Le décor des tests de doublons (lot r40), commun à `CompositionDuplicateTest`, dont les refus
 * laissent la transaction du test ouverte, et à `CompositionDuplicateRollBackTest`, dont les refus
 * l'annulent.
 *
 * La structure éprouvée n'a pas de plan : tout ce que le test écrit est neuf, et rien d'elle ne peut
 * être abîmé par un `rollBack()` qui viderait la pile.
 */
abstract class CompositionDuplicateFixtureTest extends \dev\TransactionTest {

	protected function getTransactionModel(): \ModuleModel {
		return CompositionPlan::model();
	}

	/**
	 * Une taille telle que le panneau « Ajouter une taille » la remet à `CompositionFormulaLib::add()`.
	 * Elle se pose sans `build()` : `build()` vérifierait le nom contre la base, et les filets
	 * s'éprouvent justement sur deux tailles que ce contrôle a laissées passer ensemble.
	 */
	protected function getFormula(CompositionPlan $eCompositionPlan, string $name, float $price): CompositionFormula {

		return new CompositionFormula([
			'plan' => $eCompositionPlan,
			'name' => $name,
			'price' => $price,
			'baskets' => 10,
		]);

	}

	/**
	 * La première structure active qui n'a pas de plan de paniers.
	 */
	protected function getStructureWithoutPlan(): \structure\Structure {

		return \structure\StructureLib::getById(\structure\Structure::model()
			->select(['id' => new \Sql('m1.id')])
			->join(CompositionPlan::model(), 'm1.id = m2.structure', 'LEFT')
			->where('m2.id IS NULL')
			->where('m1.status', \structure\Structure::ACTIVE)
			->sort(['m1.id' => SORT_ASC])
			->get()['id']);

	}

	/**
	 * Un plan écrit par le départ, une taille « Le panier » à 18 €, sur une structure qui n'en avait pas.
	 */
	protected function createStarted(): CompositionPlan {

		$eStructure = $this->getStructureWithoutPlan();

		$eCompositionPlan = CompositionPlanLib::getForStart($eStructure, 2026);
		$eCompositionPlan['weeks'] = range(10, 40);
		$eCompositionPlan['reference'] = CompositionPlan::COMMUNITY;
		$eCompositionPlan['climate'] = NULL;
		$eCompositionPlan['cCompositionFormula']->first()->merge(['name' => 'Le panier', 'price' => 18.0, 'baskets' => 10]);

		CompositionPlanLib::create($eCompositionPlan);

		$eCompositionPlanWritten = CompositionPlanLib::getByStructure($eStructure);
		$eCompositionPlanWritten['structure'] = $eStructure;

		return $eCompositionPlanWritten;

	}

}
?>
