<?php
new \toolbox\GaugePage()
	->getCreateElement(function($data) {

		$data->eStructure = \structure\StructureLib::getById(INPUT('structure'))->validate('canWrite');

		return new \toolbox\Gauge([
			'structure' => $data->eStructure
		]);

	})
	->create(function($data) {

		$data->cZoneFree = \toolbox\GaugeLib::getFree($data->eStructure, \map\ZoneLib::getByStructure($data->eStructure, season: \Clock::year()));

		throw new ViewAction($data);

	})
	->doCreate(fn($data) => throw new ReloadAction('toolbox', 'Gauge::created'));

new \toolbox\GaugePage()
	->update(function($data) {

		$data->cZoneFree = \toolbox\GaugeLib::getFree($data->e['structure'], \map\ZoneLib::getByStructure($data->e['structure'], season: \Clock::year()), $data->e);

		throw new ViewAction($data);

	}, propertiesUpdate: ['zone'])
	->doUpdate(fn($data) => throw new ReloadAction('toolbox', 'Gauge::updated'), propertiesUpdate: ['zone'])
	->doUpdateProperties('doUpdateStatus', ['status'], fn($data) => throw new ReloadAction('toolbox', 'Gauge::deleted'));

// L'import d'un fichier de relevés, dans un panneau ouvert depuis la roue crantée du pluviomètre : la
// documentation et le choix du fichier, puis l'aperçu dans le même panneau (arbitrage Q22 B du 24/09).
new \toolbox\GaugePage()
	->getElement(fn($data) => \toolbox\GaugeLib::getById(INPUT('id'), ['id', 'structure', 'status', 'zone' => ['name']]))
	->read('import', function($data) {

		// L'entrée de la roue crantée repart toujours du choix du fichier : un fichier lu puis abandonné
		// ne revient pas au prochain import.
		\toolbox\RainLib::resetImport($data->e);

		$data->import = NULL;

		throw new ViewAction($data);

	}, validate: ['canManage', 'acceptUpdate'])
	->write('doUpload', function($data) {

		$fw = new FailWatch();

		\toolbox\RainLib::uploadImport($data->e);

		$fw->validate();

		$data->import = \toolbox\RainLib::getImport($data->e);

		throw new ViewAction($data);

	}, validate: ['canManage', 'acceptUpdate'])
	->write('doImport', function($data) {

		$fw = new FailWatch();

		\toolbox\RainLib::import($data->e);

		$fw->validate();

		throw new ReloadAction('toolbox', 'Rain::imported');

	}, validate: ['canManage', 'acceptUpdate']);
?>
