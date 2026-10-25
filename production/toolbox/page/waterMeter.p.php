<?php
new \toolbox\WaterMeterPage()
	->getCreateElement(function($data) {

		$data->eStructure = \structure\StructureLib::getById(INPUT('structure'))->validate('canWrite');

		return new \toolbox\WaterMeter([
			'structure' => $data->eStructure
		]);

	})
	->create(fn($data) => throw new ViewAction($data))
	// Le compteur qu'on vient d'ajouter attend son premier relevé : l'écran s'ouvre sur son onglet.
	->doCreate(fn($data) => throw new RedirectAction(\toolbox\WaterReadingUi::urlMeter($data->e['structure'], $data->e).'&success=toolbox\\WaterMeter::created'));

new \toolbox\WaterMeterPage()
	->update(fn($data) => throw new ViewAction($data), propertiesUpdate: ['name', 'digits'])
	->doUpdate(fn($data) => throw new ReloadAction('toolbox', 'WaterMeter::updated'), propertiesUpdate: ['name', 'digits'])
	->doUpdateProperties('doUpdateStatus', ['status'], fn($data) => throw new ReloadAction('toolbox', 'WaterMeter::deleted'))
	// La croix du planning sur un compteur à relever, comme sur celui d'un matériel
	// (`page/equipment.p.php`, `doSkipReading`).
	->write('doSkipReading', function($data) {

		\toolbox\WaterMeterLib::skipReading($data->e);

		$data->eStructure = $data->e['structure'];
		$data->origin = POST('origin', fn($value) => \planning\DueLib::isOrigin($value), \planning\DueLib::ORIGIN_BLOCK);
		$data->week = POST('week', fn($value) => \Filter::check('week', $value), \Clock::week());
		$data->dues = \planning\DueLib::getForWeek($data->eStructure, $data->week);
		$data->success = ['toolbox', 'WaterMeter::readingSkipped'];

		throw new ViewAction($data, 'planning/due:skip');

	}, validate: ['canUpdate', 'acceptUpdate'])
	// Le décalage à la semaine suivante d'un compteur d'eau à relever (retour 15 du 04/10).
	->write('doPostponeReading', function($data) {

		$data->eStructure = $data->e['structure'];
		$data->origin = POST('origin', fn($value) => \planning\DueLib::isOrigin($value), \planning\DueLib::ORIGIN_BLOCK);
		$data->week = POST('week', fn($value) => \Filter::check('week', $value), \Clock::week());

		\toolbox\WaterMeterLib::postponeReading($data->e);

		$data->dues = \planning\DueLib::getForWeek($data->eStructure, $data->week);
		$data->success = ['toolbox', 'WaterMeter::readingPostponed'];

		throw new ViewAction($data, 'planning/due:postpone');

	}, validate: ['canUpdate', 'acceptUpdate'])
	// Un nouveau compteur installé : le compteur garde son onglet et son historique, un relevé du jour du
	// changement dit d'où le nouveau repart (`WaterReadingLib::createChange()`). Le panneau s'ouvre
	// sur aujourd'hui et sur un compteur neuf, à zéro. Ouvert depuis un écart signalé (`reading`), il
	// s'ouvre sur le jour de ce relevé, ce que le compteur affichait étant le premier relevé du nouveau.
	->read('change', function($data) {

		$eWaterReadingAlert = \toolbox\WaterReadingLib::getAlertable($data->e, GET('reading', 'int'));

		$data->eWaterReading = new \toolbox\WaterReading([
			'structure' => $data->e['structure'],
			'meter' => $data->e,
			'date' => $eWaterReadingAlert->empty() ? \Clock::date() : $eWaterReadingAlert['date'],
			'changedValue' => NULL,
			'value' => $eWaterReadingAlert->empty() ? 0.0 : $eWaterReadingAlert['value']
		]);

		throw new ViewAction($data);

	}, validate: ['canUpdate', 'acceptUpdate'])
	->write('doChange', function($data) {

		$fw = new \FailWatch();

		// Les chiffres du nouveau compteur d'abord : ce sont eux qui bornent son premier relevé.
		$data->e->build(\toolbox\WaterMeterLib::getPropertiesUpdate('change'), $_POST, new \PropertyList('update', 'change'));

		$eWaterReading = new \toolbox\WaterReading([
			'structure' => $data->e['structure'],
			'meter' => $data->e
		]);

		$eWaterReading->build(\toolbox\WaterReadingLib::getPropertiesCreate('change'), $_POST, new \PropertyList('create', 'change'));

		$fw->validate();

		\toolbox\WaterReadingLib::createChange($eWaterReading);

		throw new ReloadAction('toolbox', 'WaterReading::changed', ['value' => $eWaterReading['value']]);

	}, validate: ['canUpdate', 'acceptUpdate']);
?>
