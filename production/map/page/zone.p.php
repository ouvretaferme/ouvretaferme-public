<?php
new \map\ZonePage(function($data) {

		\user\ConnectionLib::checkLogged();

		$data->season = \map\SeasonLib::getOnline(INPUT('season'));

	})
	->getCreateElement(function($data) {

		$data->eStructure = \structure\StructureLib::getById(INPUT('structure'));

		return new \map\Zone([
			'structure' => $data->eStructure
		]);

	})
	// La carte du panneau s'ouvre sur le lieu de la structure, à défaut sur sa première parcelle tracée : sans l'un ni l'autre,
	// rien ne dit où dessiner et la surface se saisit
	->create(function($data) {

		$data->mapCenter = \map\MapLib::getMapCenter($data->eStructure, \map\ZoneLib::getByStructure($data->eStructure));

		throw new \ViewAction($data);

	})
	// Les parcelles PAC autour d'un clic de l'outil « Parcelle PAC », sous la garde de la création, qui est aussi celle du retracé (Zone::canWrite()). Chaque appel
	// fait attendre le registre de l'IGN : un utilisateur qui en enchaîne au-delà du geste n'a plus de parcelle à reprendre
	->create(function($data) {

		$lngLat = [POST('lng', '?float'), POST('lat', '?float')];

		if($lngLat[0] === NULL or $lngLat[1] === NULL or abs($lngLat[0]) > 180 or abs($lngLat[1]) > 90) {
			throw new NotExpectedAction('Invalid point');
		}

		$eUser = \user\ConnectionLib::getOnline();

		if(\user\ThrottleAiLib::isExceeded('map-pac', (string)$eUser['id'], \map\MapSetting::PAC_LIMIT)) {
			throw new JsonAction(['year' => NULL, 'box' => NULL, 'parcels' => []]);
		}

		\user\ThrottleAiLib::hit('map-pac', (string)$eUser['id'], \map\MapSetting::PAC_WINDOW);

		throw new JsonAction(['year' => \map\PacLib::getYear()] + \map\PacLib::getAround($lngLat));

	}, method: 'post', page: 'pac')
	->doCreate(function($data) {

		// Une parcelle dessinée sur la carte y revient par une navigation ajax : une redirection rechargerait la page,
		// que le geste encore ouvert demande de ne pas quitter. La carte sans parcelle ne mémorise pas sa vue, elle se dit donc elle-même
		if(POST('origin') === \map\PlanUi::VIEW_MAP) {
			$data->url = \map\PlanUi::url($data->e['structure'], $data->season, \map\PlanUi::VIEW_MAP).'?zone='.$data->e['id'].'&success=map\\Zone::created';
			throw new ViewAction($data, ':doCreateMap');
		}

		throw new RedirectAction(\map\PlanUi::urlView($data->e['structure'], $data->season).'?zone='.$data->e['id'].'&success=map\\Zone::created');

	}, propertiesCreate: fn(\map\Zone $e) => \map\ZoneLib::getPropertiesCreate((POST('origin') === \map\PlanUi::VIEW_MAP) ? \map\PlanUi::VIEW_MAP : NULL));

// La surface d'une parcelle dessinée vient de son contour : la liste dépend de la parcelle (ZoneLib::getPropertiesUpdate())
new \map\ZonePage(function($data) {

		\user\ConnectionLib::checkLogged();

		$data->season = \map\SeasonLib::getOnline(INPUT('season'));

	}, propertiesUpdate: fn(\map\Zone $e) => \map\ZoneLib::getPropertiesUpdate(NULL, $e))
	->applyElement(function($data, \map\Zone $e) {

		$e->validate('canWrite');

	})
	// Seule la vue Carte pose une origine, qui choisit la réponse de l'enregistrement
	->update(function($data) {

		\map\MapLib::loadOrigin($data);

		throw new \ViewAction($data);

	})
	->doUpdate(function($data) {

		// Modifiée depuis la vue Carte, la parcelle y reste désignée
		if(POST('origin') === \map\PlanUi::VIEW_MAP) {

			\map\MapLib::loadZone($data, $data->e, $data->season);

			// Une parcelle sortie de la saison affichée n'a plus de place sur sa carte
			if($data->eZone->empty()) {
				throw new RedirectAction(\map\PlanUi::url($data->e['structure'], $data->season, \map\PlanUi::VIEW_MAP).'?success=map\\Zone::updated');
			}

			throw new ViewAction($data, ':doUpdateMap');

		}

		throw new RedirectAction(\map\PlanUi::urlView($data->e['structure'], $data->season).'?zone='.$data->e['id'].'&success=map\\Zone::updated');

	})
	// Le contour tracé sur la carte. `for: 'shape'` dit à Zone::properties() que cette route n'existe que pour
	// poser un contour : elle refuse son absence, que les autres écritures tiennent pour une parcelle non tracée.
	// Tirée d'un bloc ou tournée, ses jardins et leurs traits la suivent dans la même écriture (ZoneLib::updateShape())
	->write('doUpdateShape', function($data) {

		$fw = new \FailWatch();

		$data->e->build(['coordinates', 'area'], $_POST, new \PropertyList('shape'));

		$fw->validate();

		\map\ZoneLib::updateShape($data->e);

		// La fiche rendue en réponse porte l'étape suivante du pas à pas, qui se lit sur les jardins et les planches, et la colonne compte toutes les parcelles
		\map\MapLib::loadZone($data, $data->e, $data->season);

		// Une parcelle sortie de la saison depuis un autre onglet n'a plus de place dans la colonne ni sur la carte de cette saison
		if($data->eZone->empty()) {
			throw new RedirectAction(\map\PlanUi::url($data->e['structure'], $data->season, \map\PlanUi::VIEW_MAP).'?success=map\\Zone::updated');
		}

		// Relue avec les autres, la parcelle porte le contour tel que la base l'a refermé à l'écriture, pas celui qui a été posté
		$data->e = $data->eZone;

		throw new \ViewAction($data);

	}, ['canUpdate', 'acceptUpdate'])
	->doDelete(function($data) {

		// Supprimée depuis la vue Carte, la parcelle y rend la main à la structure, quelle que soit la vue retenue
		$url = (POST('origin') === \map\PlanUi::VIEW_MAP) ?
			\map\PlanUi::url($data->e['structure'], $data->season, \map\PlanUi::VIEW_MAP) :
			\map\PlanUi::urlView($data->e['structure'], $data->season);

		throw new RedirectAction($url.'?success=map\\Zone::deleted');

	});
?>
