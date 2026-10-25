<?php
new AdaptativeView('/ferme/{id}/roles', function($data, StructureTemplate $t) {

	$t->title = s("Roles");
	$t->canonical = \structure\StructureUi::urlRole($data->eStructure);
	$t->nav = 'team';

	$t->mainTitle = \structure\HeaderUi::getTeam($data->eStructure, \structure\Member::ROLE);
	$t->package('main')->updateNavTeam($t->canonical);

	echo \structure\HeaderUi::getTeamTabs($data->eStructure, \structure\Member::ROLE);
	echo new \structure\RoleUi()->getManage($data->eStructure, $data->cRole);

});

new AdaptativeView('create', function($data, PanelTemplate $t) {
	return new \structure\RoleUi()->create($data->e);
});

new AdaptativeView('update', function($data, PanelTemplate $t) {
	return new \structure\RoleUi()->update($data->e);
});
?>
