<?php
new Page(function($data) {

		\structure\StructureLib::load($data, parameterName: 'id');

		$data->eStructure->validate('canWrite');

	})
	->get('/ferme/{id}/outils', function($data) {

		$data->cFavorite = \toolbox\FavoriteLib::getByStructure($data->eStructure);
		$data->cGauge = \toolbox\RainLib::getGauges($data->eStructure);
		$data->cCompositionFormula = $data->eStructure->acceptComposition() ? \toolbox\CompositionFormulaLib::getByStructure($data->eStructure) : new \Collection();
		$data->treatments = \toolbox\TreatmentLib::getSummary($data->eStructure);
		$data->maintenances = \toolbox\EquipmentLib::getSummary($data->eStructure);
		$data->waters = \toolbox\WaterReadingLib::getSummary($data->eStructure);

		throw new ViewAction($data);

	})
	->post('doForget', function($data) {

		// Retirer un outil change les outils de la structure pour toute l'équipe : c'est le droit du
		// planning, que l'en-tête de l'outil demande aussi pour proposer le geste.
		$data->eStructure->validate('canPlanning');

		$tool = POST('tool', \toolbox\Favorite::model()->getPropertyEnum('tool'));

		if($tool === NULL) {
			throw new NotExpectedAction('Unknown tool');
		}

		// Quitter les paniers efface leur plan : le geste n'existe que dans l'en-tête de l'outil, donc
		// pas pour une structure à qui il est fermé, dont le plan reste en base.
		if(\toolbox\Favorite::isOpen($data->eStructure, $tool) === FALSE) {
			throw new NotExpectedAction('Closed tool');
		}

		\toolbox\ToolLib::forget($data->eStructure, $tool);

		$success = match($tool) {
			\toolbox\Favorite::BASKET => 'Tool::forgottenBasket',
			default => 'Tool::forgotten'
		};

		throw new RedirectAction(\toolbox\ToolUi::url($data->eStructure).'?success=toolbox\\'.$success);

	});
?>
