<?php
new AdaptativeView(['/ferme/{id}/varietes', '/ferme/{id}/varietes/voter'], function($data, StructureTemplate $t) {

	$t->nav = 'intelligence-vegetable';
	$t->subNav = 'intelligence-variety';

	$t->title = s("Variétés préférées");

	$ui = new \plant\VarietyRatingAiUi();

	$t->mainTitle = \main\DesignUi::getPageHeader($t->title);

	// Le bandeau et la frise sont ceux de la campagne : c'est le même écran en trois
	// temps, et la frise est ce par quoi on passe de l'un à l'autre.
	$communityUi = new \plant\CommunityAiUi();

	echo $communityUi->getIntro($data->eStructure, $data->votes);

	// La notation est l'entrée de la section : c'est donc ici que le consentement au
	// partage se demande, faute de quoi une structure qui n'a pas consenti n'aurait
	// plus aucun écran pour le faire.
	if($data->eStructure['consentData'] === FALSE) {

		echo new \structure\StructureUi()->getConsentDataForm($data->eStructure);

		return;

	}

	echo $communityUi->getCalendar($data->eStructure, 'rating');

	echo $ui->display($data->eStructure, $data->cPlant, $data->ePlantSelected);

});

/**
 * Le tiroir s'ouvre sous sa ligne, et un seul à la fois : le JS retire le
 * précédent avant d'appeler, la réponse n'a donc qu'à poser celui-ci.
 */
new JsonView(['drawer', 'doRateOverall'], function($data, AjaxTemplate $t) {

	$t->qs('#variety-line-'.$data->e['id'])->insertAdjacentHtml('afterend', new \plant\VarietyRatingAiUi()->getDrawer(
		$data->eStructure,
		$data->e,
		$data->cCriterion,
		$data->cCriterionCompletion,
		$data->cTag
	));

});

/**
 * Après écriture, la ligne garde sa place : seule la note qu'elle porte est
 * réécrite, et le tiroir se referme.
 */
$closeDrawer = function(\stdClass $data, AjaxTemplate $t, string $success): void {

	$t->qs('#variety-line-'.$data->e['id'].' .variety-line-rating')->outerHtml(new \plant\VarietyRatingAiUi()->getOverall($data->e));
	$t->qs('#variety-drawer-'.$data->e['id'])->remove();

	$t->js()->success('plant', $success);

};

new JsonView('doRate', function($data, AjaxTemplate $t) use($closeDrawer) {
	$closeDrawer($data, $t, 'Variety::rated');
});

new JsonView('doDeleteRating', function($data, AjaxTemplate $t) use($closeDrawer) {
	$closeDrawer($data, $t, 'Variety::ratingDeleted');
});

/**
 * Une variété ignorée quitte la liste. La bande de son espèce est dans le même
 * conteneur qu'elle : si elle était la dernière, le groupe se vide et disparaît
 * de lui-même.
 */
new JsonView('doSkip', function($data, AjaxTemplate $t) {

	$t->qs('#variety-drawer-'.$data->e['id'])->remove(vanish: TRUE);
	$t->qs('#variety-line-'.$data->e['id'])->remove(vanish: TRUE);

});
?>
