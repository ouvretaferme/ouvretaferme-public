<?php
new AdaptativeView('/ferme/{id}/varietes/criteres', function($data, StructureTemplate $t) {

	$t->nav = 'intelligence-vegetable';
	$t->subNav = 'intelligence-variety';

	$t->title = s("Variétés préférées");

	$t->mainTitle = \main\DesignUi::getPageHeader($t->title);

	// Le bandeau et la frise sont ceux de la campagne : c'est le même écran en trois
	// temps, et la frise est ce par quoi on passe de l'un à l'autre.
	$communityUi = new \plant\CommunityAiUi();

	echo $communityUi->getIntro($data->eStructure, $data->votes);
	echo $communityUi->getCalendar($data->eStructure, 'criteria');

	echo new \plant\PlantUi()->updateCriterion(
		$data->eStructure,
		$data->cPlant,
		$data->cCriterionByType,
		\structure\StructureUi::urlIntelligenceVarietyCriteria($data->eStructure),
		$data->filter
	);

});

// Une action ne renvoie que ce qui change : poser un critère modifie la case, le
// compteur de la ligne et le plafond des cases restantes, tous dans la même ligne.
new AdaptativeView('doToggle', function($data, AjaxTemplate $t) {

	$t->qs('#plant-criterion-row-'.$data->e['id'])->innerHtml(
		new \plant\PlantUi()->getCriterionCells($data->e, $data->cCriterionByType)
	);

});
?>
