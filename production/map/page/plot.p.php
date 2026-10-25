<?php

new \map\PlotPage(function($data) {

		\user\ConnectionLib::checkLogged();

		$data->season = \map\SeasonLib::getOnline(INPUT('season'));


	})
	->getCreateElement(function($data) {

		$data->eZone = \map\ZoneLib::getById(INPUT('zone'))->validate('canWrite');

		return new \map\Plot([
			'zone' => $data->eZone,
			'structure' => $data->eZone['structure'],
			'mode' => INPUT('mode') === \map\Plot::GREENHOUSE ? \map\Plot::GREENHOUSE : \map\Plot::OPEN_FIELD,
			'decorGreenhouse' => NULL,
			'coordinates' => NULL,
			'vertices' => NULL
		]);

	})
	->create(function($data) {

		// Le jardin se trace sur la carte de la saison regardée, parmi les jardins et les planches de sa parcelle
		\map\MapLib::loadZone($data, $data->e['zone'], $data->season);

		// Le lien ne se pose que sur une parcelle de la saison affichée
		if($data->eZone->empty()) {
			throw new NotExpectedAction('Zone not in season');
		}

		throw new \ViewAction($data);

	})
	->doCreate(function($data) {

		$data->success = 'Plot::created';

		// Un jardin dessiné sur la carte y prend place dans sa parcelle, qui le compte, et le bloc le désigne
		if(POST('origin') === \map\PlanUi::VIEW_MAP) {

			\map\MapLib::loadDrawing($data, $data->e, $data->season);

			// Un jardin créé hors de la saison affichée n'a pas de place sur sa carte
			if($data->ePlot->empty()) {
				throw new RedirectAction(\map\PlanUi::url($data->e['structure'], $data->season, \map\PlanUi::VIEW_MAP).'?zone='.$data->e['zone']['id'].'&success=map\\'.$data->success);
			}

			throw new ViewAction($data, ':doCreateMap');

		}

		throw new ViewAction($data);

	});

new \map\PlotPage(function($data) {

		\user\ConnectionLib::checkLogged();

		$data->season = \map\SeasonLib::getOnline(INPUT('season'));

	})
	->applyElement(function($data, \map\Plot $e) {

		$e->validate('canWrite');

		// Le contour avant l'écriture, qui dit à la réponse de la vue Carte si les traits ont suivi un contour déplacé (doUpdate)
		$data->coordinatesBefore = $e['coordinates'];

	})
	// Seule la vue Carte pose une origine, qui choisit la réponse de l'enregistrement
	->update(function($data) {

		\map\MapLib::loadOrigin($data);

		// Le contour se reprend sur la carte de la saison regardée, parmi les jardins et les planches de sa parcelle, comme à la création
		\map\MapLib::loadDrawing($data, $data->e, $data->season);

		if($data->eZone->empty()) {
			throw new NotExpectedAction('Zone not in season');
		}

		// Un abri rectangle se reprend en rectangle, dont les dimensions se saisissent : il s'ouvre sur ses quatre sommets, l'anneau
		// enregistré refermant le premier. Tout autre contour s'ouvre tel qu'il est, sans redressement
		$data->e['vertices'] = (
			$data->e['mode'] === \map\Plot::GREENHOUSE and
			$data->e['coordinates'] !== NULL and
			\map\MapLib::isRectangleRing($data->e['coordinates'])
		) ? \map\MapLib::getVertices($data->e['coordinates']) : NULL;

		throw new \ViewAction($data);

	})
	// Le troisième niveau de la vue Carte, rendu au clic sur un jardin : les planches de tous les jardins ne tiennent pas dans la page d'avance
	->read('drawing', function($data) {

		\map\MapLib::loadDrawing($data, $data->e, $data->season);

		if($data->ePlot->empty()) {
			throw new \FailAction('map\Draw::season.exploited');
		}

		\map\MapLib::putDrawing($data->ePlot);

		throw new \ViewAction($data);

	})
	// La copie d'un jardin dessiné se pose sur la carte de la saison regardée, parmi les jardins de sa parcelle
	->read('duplicate', function($data) {

		\map\MapLib::loadDrawing($data, $data->e, $data->season);

		throw new \ViewAction($data);

	})
	->write('doDuplicate', function($data) {

		$fw = new \FailWatch();

		$eDuplicate = new \map\Plot([
			'zone' => $data->e['zone'],
			'structure' => $data->e['structure'],
			'mode' => $data->e['mode'],
			'ePlotSource' => $data->e
		]);

		$eDuplicate->build(['name', 'placement'], $_POST);

		$fw->validate();

		$data->ePlotDuplicated = \map\PlotLib::duplicate($data->e, $eDuplicate);
		$data->success = 'Plot::duplicated';

		throw new ViewAction($data);

	})
	->read('updateGreenhouse', function($data) {

		\map\PlotLib::putFromZone($data->e['zone']);
		$data->e['dimensions'] = \map\PlotLib::getDimensionsDrawn($data->e);

		throw new \ViewAction($data);

	})
	// 'mode' en premier : la bascule se fait dans Plot::build(), et les dimensions comme la
	// surface se construisent d'après le mode qui en sort
	->doUpdateProperties('doUpdateGreenhouse', ['mode', 'decorGreenhouse', 'length', 'width', 'area'], function($data) {

		$data->success = match($data->e['mode']) {
			\map\Plot::GREENHOUSE => 'Plot::greenhouseAdded',
			\map\Plot::OPEN_FIELD => 'Plot::greenhouseRemoved'
		};

		throw new ViewAction($data);

	})
	// Le contour tracé sur la carte, comme celui d'une parcelle (zone:doUpdateShape) : `for: 'shape'` y refuse son absence.
	// Tiré d'un bloc ou tourné, ses traits le suivent dans la même écriture (PlotLib::update())
	->write('doUpdateShape', function($data) {

		$fw = new \FailWatch();

		// Un abri tracé tire ses dimensions de son contour, et sa surface de ses dimensions (Plot::properties(), étapes `drawn` et
		// `computed`), un jardin sa surface de son contour
		$properties = match($data->e['mode']) {
			\map\Plot::GREENHOUSE => ['coordinates', 'length', 'width', 'area'],
			\map\Plot::OPEN_FIELD => ['coordinates', 'area']
		};

		$data->e->build($properties, $_POST, new \PropertyList('shape'));

		$fw->validate();

		\map\PlotLib::update($data->e, $properties);

		\map\MapLib::loadDrawing($data, $data->e, $data->season);

		// Un jardin sorti de la saison depuis un autre onglet n'a plus de place sur la carte de cette saison
		if($data->ePlot->empty()) {
			throw new RedirectAction(\map\PlanUi::url($data->e['structure'], $data->season, \map\PlanUi::VIEW_MAP).'?success=map\\Plot::updated');
		}

		$data->bedsShown = POST('beds', 'bool');

		if($data->bedsShown) {
			\map\MapLib::putDrawing($data->ePlot);
		}

		throw new \ViewAction($data);

	}, ['canUpdate', 'acceptUpdate'], addValidate: ['acceptUpdateShape'])
	->doUpdate(function($data) {

		$data->success = 'Plot::updated';

		// Modifié depuis la vue Carte, le jardin y reste désigné parmi ceux de sa parcelle
		if(POST('origin') === \map\PlanUi::VIEW_MAP) {

			$data->shapeChanged = ($data->coordinatesBefore !== $data->e['coordinates']);

			\map\MapLib::loadDrawing($data, $data->e, $data->season);

			// Un jardin sorti de la saison affichée n'a plus de place sur sa carte
			if($data->ePlot->empty()) {
				throw new RedirectAction(\map\PlanUi::url($data->e['structure'], $data->season, \map\PlanUi::VIEW_MAP).'?zone='.$data->e['zone']['id'].'&success=map\\'.$data->success);
			}

			throw new ViewAction($data, ':doUpdateMap');

		}

		throw new ViewAction($data);

	})
	->doDelete(function($data) {

		$data->success = 'Plot::deleted';

		// Supprimé depuis la vue Carte, le jardin rend la main à sa parcelle, relue sans lui
		if(POST('origin') === \map\PlanUi::VIEW_MAP) {

			\map\MapLib::loadDrawing($data, $data->e, $data->season);

			// Une parcelle sortie de la saison depuis un autre onglet n'a plus de place sur la carte de cette saison
			if($data->eZone->empty()) {
				throw new RedirectAction(\map\PlanUi::url($data->e['structure'], $data->season, \map\PlanUi::VIEW_MAP).'?success=map\\'.$data->success);
			}

			throw new ViewAction($data, ':doDeleteMap');

		}

		throw new ViewAction($data);

	});
new Page()
	->post('doUpdatePositions', function($data) {

		$data->eZone = \map\ZoneLib::getById(POST('zone'))->validate('canWrite');

		$fw = new \FailWatch();

		\map\PlotLib::updatePositions($data->eZone, POST('ids', 'array', []));

		$fw->validate();

		throw new ViewAction($data);

	});
?>
