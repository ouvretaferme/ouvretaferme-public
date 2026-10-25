<?php
new \structure\StructurePage()
	->read('index', function($data) {

		\user\ConnectionLib::checkLogged();

		$data->ePlant = \plant\PlantLib::getById(GET('plant'))->validate('canRead');
		$data->cVariety = \plant\VarietyLib::getByStructureAndPlant($data->e, $data->ePlant);

		$data->cSupplier = \work\SupplierLib::getByStructure($data->e);

		throw new \ViewAction($data);

	});

new Page()
	->post('query', function($data) {

		\user\ConnectionLib::checkLogged();

		$data->eStructure = \structure\StructureLib::getById(POST('structure'))->validate('canWrite');

		$ePlant = \plant\PlantLib::getById(POST('plant'))->validate('canRead');

		$search = trim(POST('query'));

		$data->search = $search;
		$data->cVariety = \plant\VarietyLib::query($data->eStructure, $ePlant, $search);

		$data->cVarietyReference = \plant\VarietyNameAiLib::query($ePlant, $search, \plant\PlantSetting::VARIETY_QUERY)
			->find(fn($eVariety) => $data->cVariety->find(fn($e) => $e['fqn'] === $eVariety['fqn'])->empty());

		throw new \ViewAction($data);

	});

new \plant\VarietyPage(function($data) {

		\user\ConnectionLib::checkLogged();

		$data->eStructure = \structure\StructureLib::getById(INPUT('structure', '?int'))->validate('canManage');

	})
	->getCreateElement(function($data) {
		return new \plant\Variety([
			'structure' => $data->eStructure
		]);
	})
	->create(function($data) {

		$data->e['plant'] = \plant\PlantLib::getById(GET('plant'))->validate('canRead');
		$data->e['cSupplier'] = \work\SupplierLib::getByStructure($data->e['structure']);

		throw new ViewAction($data);

	})
	->doCreate(function($data) {
		throw new \ViewAction($data);

	});

new \plant\VarietyPage()
	->applyElement(function($data, \plant\Variety $e) {

		$e->validate('canWrite');

		$data->ePlant = \plant\PlantLib::getById($e['plant']);

		$data->eStructure = $e['structure'];

		\structure\Structure::model()
			->select('status', 'name')
			->get($data->eStructure);

		$data->eStructure->validate('active');

	})
	->quick(['supplierSeed', 'supplierPlant', 'weightSeed1000', 'numberPlantKilogram', 'stock' => ['unit', 'stock']], [
		'supplierSeed' => function($data) {
			$data->e['cSupplier'] = \work\SupplierLib::getByStructure($data->e['structure']);
		},
		'supplierPlant' => function($data) {
			$data->e['cSupplier'] = \work\SupplierLib::getByStructure($data->e['structure']);
		}
	])
	->update(function($data) {

		$data->e['cSupplier'] = \work\SupplierLib::getByStructure($data->e['structure']);

		throw new ViewAction($data);

	})
	->doUpdate(function($data) {
		throw new ViewAction($data);

	})
	->doDelete(function($data) {
		throw new ViewAction($data);

	});
?>
