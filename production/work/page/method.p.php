<?php
new \work\MethodPage()
	->getCreateElement(function($data) {

		$data->eAction = \work\ActionLib::getById(INPUT('action'))->validate('canWrite');

		return new \work\Method([
			'action' => $data->eAction,
			'structure' => $data->eAction['structure'],
		]);

	})
	->create()
	->doCreate(fn() => throw new ReloadAction('work', 'Method::created'));

new \work\MethodPage()
	->quick(['name'])
	->doDelete(fn() => throw new ReloadAction('work', 'Method::deleted'));

new Page()
	->post('query', function($data) {

		$eStructure = \structure\StructureLib::getById(POST('structure'))->validate('canWrite');
		$eAction = \work\ActionLib::getById(POST('action'))->validate();

		$data->cMethod = \work\MethodLib::getFromQuery(POST('query'), $eStructure, $eAction);

		throw new \ViewAction($data);

	});
?>
