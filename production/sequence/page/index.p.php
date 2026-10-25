<?php
new Page(function($data) {

		\structure\StructureLib::load($data, parameterName: 'id');

	})
	->get(['/ferme/{id}/itineraires', '/ferme/{id}/itineraires/{status}'], function($data) {

		$data->sequences = \sequence\SequenceLib::countByStructure($data->eStructure);

		if(
			get_exists('status') and
			$data->sequences[\sequence\Sequence::CLOSED] === 0
		) {
			throw new RedirectAction(\structure\StructureUi::urlCultivationSequences($data->eStructure));
		}

		$data->search = new Search([
			'plant' => \plant\PlantLib::getByStructure($data->eStructure, id: GET('plant')),
			'name' => GET('name'),
			'use' => GET('use'),
			'status' => GET('status', default: \sequence\Sequence::ACTIVE),
			'tool' => \work\ToolLib::getByStructure($data->eStructure, id: GET('tool')),
		]);

		$data->emptySearch = $data->search->empty(['status']);

		$data->cActionMain = \work\ActionLib::getMainByStructure($data->eStructure);
		$data->ccCrop = \sequence\CropLib::getByStructure($data->eStructure, $data->cActionMain, TRUE, search: $data->search);

		throw new ViewAction($data, ':sequence');

	});

?>
