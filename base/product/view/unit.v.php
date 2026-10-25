<?php
new AdaptativeView('manage', function($data, StructureTemplate $t) {

	$t->title = s("Les unités de vente de {value}", $data->eStructure['name']);
	$t->nav = 'feature';
	$t->subNav = 'settings-commercialisation';

	$t->mainTitle = new \product\UnitUi()->getManageTitle($data->eStructure);
	echo new \product\UnitUi()->getManage($data->cUnit);

});

new AdaptativeView('create', function($data, PanelTemplate $t) {
	return new \product\UnitUi()->create($data->e);
});

new AdaptativeView('update', function($data, PanelTemplate $t) {
	return new \product\UnitUi()->update($data->e);
});
?>
