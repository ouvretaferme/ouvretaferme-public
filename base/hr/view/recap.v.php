<?php
new AdaptativeView('/ferme/{id}/equipe/recapitulatif', function($data, StructureTemplate $t) {

	$t->title = s("Synthèse mensuelle");
	$t->canonical = \hr\RecapUi::urlStructure($data->eStructure, $data->month);
	$t->nav = 'team';

	$t->mainTitle = \structure\HeaderUi::getTeam($data->eStructure, \hr\RecapUi::TAB);
	$t->package('main')->updateNavTeam($t->canonical);

	echo \structure\HeaderUi::getTeamTabs($data->eStructure, \hr\RecapUi::TAB);

	echo new \hr\RecapUi()->getTeam($data->eStructure, $data->month, $data->navigation, $data->cUser, $data->total, $data->columns, $data->holidays);

});

new AdaptativeView('user', function($data, PanelTemplate $t) {
	return new \hr\RecapUi()->getUser($data->eStructure, $data->month, $data->navigation, $data->eUserSelected, $data->eMember, $data->cPresence, $data->weeks);
});
?>
