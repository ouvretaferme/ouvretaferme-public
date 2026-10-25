<?php
new Page(function($data) {

	if(\structure\StructureSetting::hasAccountingApp() === FALSE) {
		throw new NotExistsAction();
	}

	\structure\StructureLib::load($data);

})
->get('/historique', function($data) {

	\structure\StructureLib::connectDatabase($data->eStructure);

	$data->page = REQUEST('page', 'int', 0);

	[$data->cLog, $data->nLog] = \securing\LogLib::get($data->page);

	throw new DynamicAction($data);

});

?>
