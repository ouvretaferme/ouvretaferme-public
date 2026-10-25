<?php
new \product\CategoryPage(function($data) {

		\user\ConnectionLib::checkLogged();

	})
	->getCreateElement(function($data) {

		$data->eStructure = \structure\StructureLib::getById(INPUT('structure'));

		return new \product\Category([
			'structure' => $data->eStructure,
		]);

	})
	->create()
	->doCreate(fn($data) => throw new ViewAction($data));

new \product\CategoryPage()
	->update()
	->doUpdate(fn($data) => throw new ViewAction($data))
	->write('doIncrementPosition', function($data) {

		$increment = POST('increment', 'int');
		\product\CategoryLib::incrementPosition($data->e, $increment);

		throw new ReloadAction();

	})
	->doDelete(fn($data) => throw new ViewAction($data));

new Page()
	->get('manage', function($data) {

		$data->eStructure = \structure\StructureLib::getById(GET('structure'))->validate('canSelling');
		$data->cCategory = \product\CategoryLib::getByStructure($data->eStructure);

		\structure\MemberLib::setView('viewSellingProducts', $data->eStructure, \structure\Member::CATEGORY);

		throw new ViewAction($data);

	});
?>
