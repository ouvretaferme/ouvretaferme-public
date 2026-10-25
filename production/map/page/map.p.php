<?php
new \structure\StructurePage()
	->read(['/ferme/{id}/plan', '/ferme/{id}/plan/{season}'], function($data) {

		\map\MapLib::load($data, $data->e);
		\structure\MemberLib::setView('viewMap', $data->eStructure, \structure\Member::PLAN);

		$zone = GET('zone', 'int');
		$data->eZone = $data->cZone->find(fn($eZone) => $eZone['id'] === $zone, limit: 1, clone: FALSE, default: new \map\Zone());

		$plot = GET('plot', 'int');
		$data->ePlot = $data->eZone->notEmpty() ?
			$data->eZone['cPlot']->find(fn($ePlot) => $ePlot['id'] === $plot, limit: 1, clone: FALSE, default: new \map\Plot()) :
			new \map\Plot();

		$data->table = ($data->ePlot->notEmpty() and GET('table', 'bool'));

		throw new \ViewAction($data, ':plan');

	})
	->remote('getPlan', 'selling', function($data) {

		\map\MapLib::load($data, $data->e, redirectOnEmpty: FALSE, validate: FALSE);

		$data->basemap = GET('basemap', [\structure\Member::DRAWING, \structure\Member::SATELLITE], \structure\Member::DRAWING);
		$data->mapBounds = \map\MapLib::getBounds($data->cZone);
		$data->cZonePrinted = \map\MapLib::getZonesPrinted($data->cZone);

		throw new \ViewAction($data, ':getPlan');

	})
	->read('downloadPlan', function($data) {

		$season = GET('season', 'int');
		$basemap = GET('basemap', [\structure\Member::DRAWING, \structure\Member::SATELLITE], \structure\Member::DRAWING);

		$filename = 'Plan de la ferme '.$season.'.pdf';
		$content = \main\PdfLib::build('/map/map:getPlan?id='.$data->e['id'].'&season='.$season.'&basemap='.$basemap, $filename, footer: \main\PdfUi::getPagingFooter());

		if($content === NULL) {
			throw new NotExistsAction();
		}

		throw new PdfAction($content, $filename);

	}, validate: ['canWrite'])
	->read(['/ferme/{id}/cartographie', '/ferme/{id}/cartographie/{season}'], function($data) {

		\map\MapLib::load($data, $data->e, redirectOnEmpty: FALSE);

		// Le menu du plan suit la vue mémorisée : sans parcelle, il doit continuer de mener au démarrage guidé
		if($data->cZone->notEmpty()) {
			\structure\MemberLib::setView('viewMap', $data->eStructure, \structure\Member::MAP);
		}

		$zone = GET('zone', 'int');
		$data->eZone = $data->cZone->find(fn($eZone) => $eZone['id'] === $zone, limit: 1, clone: FALSE, default: new \map\Zone());

		$plot = GET('plot', 'int');
		$data->ePlot = $data->eZone->notEmpty() ?
			$data->eZone['cPlot']->find(fn($ePlot) => $ePlot['id'] === $plot, limit: 1, clone: FALSE, default: new \map\Plot()) :
			new \map\Plot();

		// Désignée seule, une parcelle sans contour lance son tracé pour qui peut tracer, et un tracé ne part que d'un geste
		// de l'utilisateur, que son lien dit par `draw` : une adresse qui ne fait que revenir à la parcelle s'ouvre sur la liste.
		// Un jardin désigné garde sa parcelle, son adresse mène à lui
		if(
			$data->eZone->notEmpty() and
			$data->eZone['coordinates'] === NULL and
			$data->ePlot->empty() and
			$data->eStructure->canManage() and
			GET('draw', 'bool') === FALSE
		) {
			$data->eZone = new \map\Zone();
		}

		// Un jardin désigné s'ouvre sans ses planches. Elles s'affichent pour qui peut les modifier quand l'adresse le demande,
		// celle de « Planches » (`beds`) ou d'une planche à dessiner (`ids[]`)
		$ids = GET('ids', 'array');

		if($data->ePlot->notEmpty() and $data->ePlot->canWrite() and (GET('beds', 'bool') or $ids !== [])) {
			\map\MapLib::putDrawing($data->ePlot, $ids);
			$data->ePlotDrawing = $data->ePlot;
		} else {
			$data->ePlotDrawing = new \map\Plot();
		}

		$data->eMember = $data->eStructure->getMember();
		$data->basemap = $data->eStructure->getView('viewMapBasemap');
		$data->mapBounds = \map\MapLib::getBounds($data->cZone);

		throw new \ViewAction($data, ':map');

	})
	// L'ancienne adresse du dessin des planches, gardée parce qu'une adresse se garde et se partage : elle mène au jardin sur la vue Carte, avec les planches qu'elle désigne
	->read(['/ferme/{id}/planches', '/ferme/{id}/planches/{season}'], function($data) {

		\map\MapLib::load($data, $data->e, redirectOnEmpty: FALSE);

		$plot = GET('plot', 'int');
		$eZone = $data->cZone->find(fn($eZone) => $eZone['cPlot']->contains(fn($ePlot) => $ePlot['id'] === $plot), limit: 1, clone: FALSE, default: new \map\Zone());

		if($eZone->empty()) {
			throw new RedirectAction(\map\PlanUi::url($data->eStructure, $data->season, \map\PlanUi::VIEW_MAP));
		}

		$ePlot = $eZone['cPlot']->find(fn($ePlot) => $ePlot['id'] === $plot, limit: 1, clone: FALSE);

		throw new RedirectAction(\map\DrawUi::urlPlot($data->eStructure, $data->season, $ePlot, GET('ids', 'array')));

	});

new Page()
	->get('getZone', function($data) {

		$data->eZone = \map\ZoneLib::getById(GET('zone'))->validate('canRead');
		$data->eStructure = \structure\StructureLib::getById($data->eZone['structure']['id']);
		$data->season = \map\SeasonLib::getOnline(GET('season'));

		\map\PlotLib::putFromZone($data->eZone, withBeds: TRUE, withDraw: TRUE, season: $data->season);
		\map\MapLib::putDrawingState(new \Collection([$data->eZone]));

		$plot = GET('plot', 'int');
		$data->ePlot = $data->eZone['cPlot']->find(fn($ePlot) => $ePlot['id'] === $plot, limit: 1, clone: FALSE, default: new \map\Plot());
		$data->table = ($data->ePlot->notEmpty() and GET('table', 'bool'));

		throw new ViewAction($data);

	});
?>
