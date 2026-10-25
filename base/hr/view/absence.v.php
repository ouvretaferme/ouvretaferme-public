<?php
new AdaptativeView('/ferme/{id}/equipe/absences', function($data, StructureTemplate $t) {

	$t->title = s("Absences");
	$t->canonical = \hr\AbsenceUi::urlStructure($data->eStructure);
	$t->nav = 'team';

	$t->mainTitle = \structure\HeaderUi::getTeam($data->eStructure, \hr\AbsenceUi::TAB);
	$t->package('main')->updateNavTeam($t->canonical);

	echo \structure\HeaderUi::getTeamTabs($data->eStructure, \hr\AbsenceUi::TAB);
	echo new \hr\AbsenceUi()->getManage($data->eStructure, $data->year, $data->cAbsencePending, $data->months);

});

new AdaptativeView('create', function($data, PanelTemplate $t) {
	return new \hr\AbsenceUi()->create($data->e['structure'], $data->e);
});

new AdaptativeView('update', function($data, PanelTemplate $t) {
	return new \hr\AbsenceUi()->update($data->e);
});
?>
