<?php
new \work\CategoryPage(function($data) {

		\user\ConnectionLib::checkLogged();

	})
	->getCreateElement(function($data) {

		$data->eStructure = \structure\StructureLib::getById(INPUT('structure'));

		return new \work\Category([
			'structure' => $data->eStructure,
		]);

	})
	->create()
	->doCreate(fn($data) => throw new ViewAction($data));

new \work\CategoryPage()
	->update()
	->doUpdate(fn($data) => throw new ViewAction($data))
	->write('doIncrementPosition', function($data) {

		$increment = POST('increment', 'int');
		\work\CategoryLib::incrementPosition($data->e, $increment);

		throw new ReloadAction();

	})
	->doDelete(fn($data) => throw new ViewAction($data));

new Page()
	->get('manage', function($data) {

		$data->eStructure = \structure\StructureLib::getById(GET('structure'))->validate('canManage');
		$data->cCategory = \work\CategoryLib::getByStructure($data->eStructure);

		throw new ViewAction($data);

	});
?>
