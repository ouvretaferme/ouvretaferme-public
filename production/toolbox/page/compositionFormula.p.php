<?php
new \toolbox\CompositionFormulaPage(function($data) {

	\structure\StructureLib::load($data);

	$data->eStructure->validate('canManage', 'acceptComposition');

})
	->getElement(function($data) {

		return \toolbox\CompositionFormulaLib::getById(POST('id', 'int'), \toolbox\CompositionFormula::getSelection() + [
			'plan' => \toolbox\CompositionPlan::getSelection(),
		])->validate();

	})
	->applyElement(function($data, \toolbox\CompositionFormula $e) {

		if($e['plan']['structure']['id'] !== $data->eStructure['id']) {
			throw new NotExistsAction('CompositionFormula');
		}

		$e['plan']['structure'] = $data->eStructure;

	})
	// « Rejouer les paniers » ne lit aucune valeur postée : la formule passe au tirage suivant
	// (`CompositionFormulaLib::replay()`).
	->write('doReplay', function($data) {

		\toolbox\CompositionFormulaLib::replay($data->e);

		$data->eCompositionPlan = \toolbox\CompositionPlanLib::getForScreen($data->eStructure, $data->e['id']);

		throw new ViewAction($data);

	}, validate: ['canUpdate', 'acceptUpdate'])
	->getCreateElement(function($data) {
		$eCompositionPlan = \toolbox\CompositionPlanLib::getByStructure($data->eStructure)->validate();
		$eCompositionPlan['structure'] = $data->eStructure;

		return new \toolbox\CompositionFormula([
			'plan' => $eCompositionPlan,
			'base' => new \toolbox\CompositionFormula(),
		]);

	})
	->create(function($data) {

		$data->e['baseChoices'] = \toolbox\CompositionFormulaLib::getBaseChoices($data->e, \toolbox\CompositionFormulaLib::getByPlan($data->e['plan']));
		$data->prices = \aggregate\CompositionSourceAiLib::getBasketPriceQuartiles();
		$data->formula = GET('formula', '?int');
		$data->origin = GET('origin');

		throw new ViewAction($data);

	})
	// La taille ajoutée ne construit que ses réponses et s'écrit par `CompositionFormulaLib::add()`,
	// qui lui donne sa position et compose ses paniers : `doCreate()` du cadriciel appellerait
	// `create()`, qui n'en fait rien.
	->post('doCreate', function($data) {
		$eCompositionPlan = \toolbox\CompositionPlanLib::getByStructure($data->eStructure)->validate();
		$eCompositionPlan['structure'] = $data->eStructure;

		$eCompositionFormula = new \toolbox\CompositionFormula([
			'id' => NULL,
			'plan' => $eCompositionPlan,
			'base' => new \toolbox\CompositionFormula(),
		]);

		$eCompositionFormula->validate('canCreate', 'acceptCreate');

		$fw = new \FailWatch();

		// La base après le prix : son refus `cheaper` compare le prix construit.
		$eCompositionFormula->build(['name', 'price', 'baskets', 'base'], $_POST, new \PropertyList('create'));

		$fw->validate();

		\toolbox\CompositionFormulaLib::add($eCompositionFormula);

		// Un second envoi simultané du même nom n'est refusé qu'à l'écriture (`add()`, lot r40).
		$fw->validate();

		$data->formula = POST('formula', '?int');
		$data->origin = POST('origin', '?string');
		$data->tab = \toolbox\CompositionPlanLib::getTab($data->origin);
		$data->eCompositionPlan = \toolbox\CompositionPlanLib::getForScreen($data->eStructure, $data->formula, tab: $data->tab);

		// « Modifier les paniers », d'où part l'ajout (r51b), se rend de nouveau avec la taille ajoutée.
		\toolbox\CompositionPlanLib::loadSettings($eCompositionPlan);
		$data->eCompositionPlanSettings = $eCompositionPlan;

		throw new ViewAction($data);

	});
?>
