<?php
/**
 * Le bac des séries, servi à part.
 *
 * Il pèse le plus gros du HTML de la page d'assolement — une carte par culture —
 * alors qu'il ne s'ouvre pas toujours. La page ne rend plus que sa coque, et son
 * contenu arrive au premier clic sur « Assoler mes séries ».
 */
new \structure\StructurePage()
	->read('index', function($data) {

		$data->e->validate('canRead');

		$data->eStructure = $data->e;
		$data->season = GET('season', 'int') ?: \Clock::year();

		$data->ccCultivation = \series\CultivationLib::getForSelector($data->eStructure, $data->season);

		throw new \ViewAction($data);

	});
?>
