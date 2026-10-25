<?php
new \work\SupplierPage()
	->getCreateElement(function($data) {

		$data->eStructure = \structure\StructureLib::getById(INPUT('structure'));

		return new \work\Supplier([
			'structure' => $data->eStructure,
		]);

	})
	->create()
	->doCreate(fn($data) => throw new ViewAction($data));

new \work\SupplierPage()
	->applyElement(function($data, \work\Supplier $e) {

		$e->validate('canWrite');

		$data->eStructure = $e['structure'];

		\structure\Structure::model()
			->select('status', 'name')
			->get($data->eStructure);

		$data->eStructure->validate('active');

	})
	->quick(['name'])
	->update()
	->doUpdate(fn($data) => throw new ViewAction($data))
	->doDelete(fn($data) => throw new ViewAction($data));

new Page()
	->post('query', function($data) {

		$eStructure = \structure\StructureLib::getById(POST('structure'))->validate('canWrite');

		$data->cSupplier = \work\SupplierLib::getFromQuery(POST('query'), $eStructure);

		throw new \ViewAction($data);

	})
	->get('manage', function($data) {

		$data->eStructure = \structure\StructureLib::getById(GET('structure'))->validate('canManage');

		$data->cSupplier = \work\SupplierLib::getByStructure($data->eStructure);


		throw new \ViewAction($data);

	});
?>
