<?php
new Page(function($data) {

		\structure\StructureLib::load($data, parameterName: 'id');

		$data->eStructure->validate('canWrite');

	})
	->get('/ferme/{id}/outils/pluviometrie', function($data) {

		$data->cGauge = \toolbox\RainLib::getGauges($data->eStructure);
		$data->eGaugeSelected = $data->cGauge->findById(GET('gauge', 'int'), default: new \toolbox\Gauge());
		$data->cZoneFree = \toolbox\GaugeLib::getFree($data->eStructure, \map\ZoneLib::getByStructure($data->eStructure, season: \Clock::year()));
		$data->cFavorite = \toolbox\FavoriteLib::getByStructure($data->eStructure);

		throw new ViewAction($data);

	})
	// Sortir les relevés en masse demande de gérer la structure (`CLAUDE.md` du package, « Qui écrit
	// dans la Boîte »), comme l'export de l'eau.
	->get('exportCsv', function($data) {

		$data->eStructure->validate('canManage');

		$export = \toolbox\RainLib::getExport($data->eStructure);

		if($export === []) {
			throw new NotExpectedAction('No rain');
		}

		array_unshift($export, new \toolbox\RainUi()->getExportHeader());

		throw new CsvAction($export, \toolbox\RainUi::getExportFilename());

	});

new \toolbox\RainPage()
	->getCreateElement(function($data) {

		$data->eStructure = \structure\StructureLib::getById(INPUT('structure'))->validate('canWrite');

		return new \toolbox\Rain([
			'structure' => $data->eStructure,
			'gauge' => new \toolbox\Gauge(),
			'date' => \Clock::date()
		]);

	})
	->create(function($data) {

		$data->cGauge = \toolbox\GaugeLib::getByStructure($data->eStructure);

		// Sans pluviomètre, il n'y a pas où noter la pluie : la page Pluviométrie propose d'en poser un.
		if($data->cGauge->empty()) {
			throw new RedirectAction(\toolbox\RainUi::url($data->eStructure));
		}

		// La porte d'une carte désigne son pluviomètre ; sans elle, le premier de la liste est proposé.
		$data->e['gauge'] = $data->cGauge->findById(GET('gauge', 'int'), default: $data->cGauge->first());
		$data->day = \toolbox\RainLib::getDay($data->e['gauge'], $data->e['date']);

		throw new ViewAction($data);

	})
	// Ce que le jour porte déjà, relu quand la date ou le pluviomètre change dans le formulaire. Rien
	// ne s'écrit : un jour invalide ou un pluviomètre inconnu rendent zéro, et c'est l'envoi du relevé
	// qui les refusera, sur leur champ.
	->create(function($data) {

		$data->cGauge = \toolbox\GaugeLib::getByStructure($data->eStructure);

		$data->e['gauge'] = $data->cGauge->findById(POST('gauge', 'int'), default: new \toolbox\Gauge());

		$data->e['date'] = POST('date');
		$data->e['rain'] = POST('rain', '?float');

		$data->day = ($data->e['gauge']->notEmpty() and \util\DateLib::isValid($data->e['date'])) ? \toolbox\RainLib::getDay($data->e['gauge'], $data->e['date']) : 0.0;

		throw new ViewAction($data, ':day');

	}, method: 'post', page: 'day')
	->doCreate(fn($data) => throw new ReloadAction('toolbox', ($data->e['rain'] < 0) ? 'Rain::removed' : 'Rain::created'));

new \toolbox\RainPage()
	->getElement(fn($data) => \toolbox\RainLib::getById(INPUT('id'), ['id', 'structure', 'date', 'rain', 'gauge' => ['zone' => ['name']]]))
	->applyElement(function($data, \toolbox\Rain $e) {

		$e->validate('canWrite');

	})
	->update(fn($data) => throw new ViewAction($data))
	->doUpdate(fn($data) => throw new ReloadAction('toolbox', 'Rain::updated'))
	->doDelete(fn($data) => throw new ReloadAction('toolbox', 'Rain::deleted'));
?>
