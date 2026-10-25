<?php
new Page(function($data) {

		$data->eStructure = \structure\StructureLib::getById(POST('structure'))->validate('canWrite');

	})
	// L'étoile d'un outil : elle le met au menu, ou l'en retire s'il y était déjà.
	->post('doToggle', function($data) {

		$eFavorite = \toolbox\FavoriteLib::getOne($data->eStructure, POST('tool'));

		if($eFavorite->notEmpty()) {

			\toolbox\FavoriteLib::delete($eFavorite);

		} else {

			$eFavorite = new \toolbox\Favorite([
				'structure' => $data->eStructure,
				'user' => \user\ConnectionLib::getOnline()
			]);

			$fw = new \FailWatch();

			$eFavorite->build(['tool'], $_POST);

			$fw->validate();

			\toolbox\FavoriteLib::create($eFavorite);

		}

		$data->tool = $eFavorite['tool'];
		$data->cFavorite = \toolbox\FavoriteLib::getByStructure($data->eStructure);
		$data->cGauge = \toolbox\RainLib::getGauges($data->eStructure);
		$data->cCompositionFormula = $data->eStructure->acceptComposition() ? \toolbox\CompositionFormulaLib::getByStructure($data->eStructure) : new \Collection();
		$data->treatments = \toolbox\TreatmentLib::getSummary($data->eStructure);
		$data->maintenances = \toolbox\EquipmentLib::getSummary($data->eStructure);
		$data->waters = \toolbox\WaterReadingLib::getSummary($data->eStructure);

		throw new ViewAction($data);

	});
?>
