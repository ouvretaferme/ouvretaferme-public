<?php
new \structure\StructurePage()
	->read('/ferme/{id}/varietes/criteres', function($data) {

		$data->eStructure = $data->e->validate('canManage');

		$data->cPlant = \plant\PlantLib::getForCriteria($data->eStructure);
		$data->cCriterionByType = \plant\CriterionLib::getAllByType();
		$data->votes = \plant\VarietyLib::countVotes();

		$data->filter = GET('filter', ['none'], NULL);

		throw new \ViewAction($data);

	});

// ReloadAction et non une redirection en dur : le bloc s'affiche aussi sur la page
// des variétés préférées, et l'enregistrement doit y rester.
new \plant\PlantPage()
	->doUpdateProperties('doToggle', ['criteria'], function($data) {

		// La ligne rendue en réponse montre aussi ce que la communauté retient, que
		// l'écriture ne touche pas et que la sélection de l'élément ne charge pas.
		$data->e['criteriaReference'] = \plant\CriterionLib::getReferenceByPlant(new \Collection([$data->e]))[$data->e['id']];

		$data->cCriterionByType = \plant\CriterionLib::getAllByType();

		throw new \ViewAction($data);

	});
?>
