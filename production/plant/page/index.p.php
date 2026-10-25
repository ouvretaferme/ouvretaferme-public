<?php
new Page()
	->post('query', function($data) {

		$data->eStructure = \structure\StructureLib::getById(POST('structure', '?int'));

		if($data->eStructure->notEmpty()) {
			$data->eStructure->validate('canWrite');
		}

		if(post_exists('season')) {
			$ids = \series\CultivationLib::getPlantsBySeason($data->eStructure, POST('season', 'int'))->getIds();
		} else {
			$ids = POST('ids', 'array');
		}

		$search = new Search([
			'ids' => $ids
		]);

		$data->cPlant = \plant\PlantLib::getFromQuery(POST('query'), $data->eStructure, $search);

		$data->hasNew = post_exists('new');

		throw new \ViewAction($data);

	});
?>
