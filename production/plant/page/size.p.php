<?php
new \structure\StructurePage()
	->read('index', function($data) {

		\user\ConnectionLib::checkLogged();

		$data->ePlant = \plant\PlantLib::getById(GET('plant'))->validate('canRead');
		$data->cSize = \plant\SizeLib::getByStructureAndPlant($data->e, $data->ePlant);

		throw new \ViewAction($data);

	});

new \plant\SizePage(function($data) {

		\user\ConnectionLib::checkLogged();

		$data->eStructure = \structure\StructureLib::getById(INPUT('structure', '?int'))->validate('canManage');

	})
	->getCreateElement(function($data) {
		return new \plant\Size([
			'structure' => $data->eStructure
		]);
	})
	->create(function($data) {

		$data->e['plant'] = \plant\PlantLib::getById(GET('plant'))->validate('canRead');

		throw new ViewAction($data);

	})
	->doCreate(function($data) {
		throw new \ViewAction($data);

	});

new \plant\SizePage()
	->applyElement(function($data, \plant\Size $e) {

		$e->validate('canWrite');

		$data->ePlant = \plant\PlantLib::getById($e['plant']);

		$data->eStructure = $e['structure'];

		\structure\Structure::model()
			->select('status', 'name')
			->get($data->eStructure);

		$data->eStructure->validate('active');

	})
	->update()
	->doUpdate(function($data) {
		throw new ViewAction($data);

	})
	->doDelete(function($data) {
		throw new ViewAction($data);

	});
?>
