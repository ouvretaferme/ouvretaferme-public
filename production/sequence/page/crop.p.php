<?php
new \sequence\CropPage(function($data) {

		\user\ConnectionLib::checkLogged();

		$data->eSequence = \sequence\SequenceLib::getById(INPUT('sequence'))->validate('canWrite');

	})
	->post('addPlant', function($data) {

		$data->ePlant = \plant\PlantLib::getById(POST('plant'));

		$data->cAction = \work\ActionLib::getByStructure($data->eSequence['structure'], fqn: [ACTION_SEMIS_PEPINIERE, ACTION_SEMIS_DIRECT, ACTION_PLANTATION], index: 'fqn');

		throw new \ViewAction($data);

	})
	->getCreateElement(function($data) {
		return new \sequence\Crop([
			'structure' => $data->eSequence['structure'],
			'sequence' => $data->eSequence
		]);
	})
	->create()
	->doCreate(fn() => throw new ReloadAction());

new \sequence\CropPage()
	->applyElement(function($data, \sequence\Crop $e) {
		$e['sequence'] = \sequence\SequenceLib::getById($e['sequence'])->validate('canWrite');
	})
	->read('changePlant', function($data) {

		$data->ePlant = \plant\PlantLib::getById(POST('plant'));

		if($data->ePlant->notEmpty()) {

			$data->ePlant->validate('canRead');

			// On récupère les répartitions actuelles des variétés si l'espèce recherchée est celle actuellement utilisée
			if($data->ePlant['id'] === $data->e['plant']['id']) {
				$data->cSlice = $data->e['cSlice'];
			} else {
				$data->cSlice = new Collection();
			}

			$data->e['plant'] = $data->ePlant;

		} else {
			$data->cSlice = new Collection();
		}

		throw new \ViewAction($data);

	}, method: 'post')
	->update(function($data) {


		if($data->e['seedling'] === NULL) {

			$data->cAction = \work\ActionLib::getByStructure($data->e['structure'], fqn: [ACTION_SEMIS_PEPINIERE, ACTION_SEMIS_DIRECT, ACTION_PLANTATION], index: 'fqn');

			if(\sequence\FlowLib::hasCropActions($data->e, $data->cAction)) {
				$data->cAction = new Collection();
			}

		} else {
			$data->cAction = new Collection();
		}

		throw new ViewAction($data);

	})
	->quick(['yieldExpected', 'seedlingSeeds'])
	->doUpdate(function($data) {
		throw new ReloadAction('sequence', 'Crop::updated');
	})
	->doDelete(fn() => throw new ReloadAction());

new Page()
	->post('changePlant', function($data) {

		$data->ePlant = \plant\PlantLib::getById(POST('plant'));

		if($data->ePlant->notEmpty()) {

			$data->ePlant->validate('canRead');

			// On récupère les répartitions actuelles des variétés si l'espèce recherchée est celle actuellement utilisée
			if($data->ePlant['id'] === $data->e['plant']['id']) {
				$data->cSlice = $data->e['cSlice'];
			} else {
				$data->cSlice = new Collection();
			}

			$data->e['plant'] = $data->ePlant;

		} else {
			$data->cSlice = new Collection();
		}

		throw new \ViewAction($data);

	});
?>
