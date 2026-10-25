<?php
new Page(function($data) {

		\structure\StructureLib::load($data, parameterName: 'id');

		$data->eStructure->validate('canWrite');

	})
	->get('/ferme/{id}/outils/eau', function($data) {

		$data->waters = \toolbox\WaterReadingLib::getSummary($data->eStructure);
		$data->eWaterMeterSelected = $data->waters['cWaterMeter']->findById(GET('meter', 'int'), default: new \toolbox\WaterMeter());
		$data->cFavorite = \toolbox\FavoriteLib::getByStructure($data->eStructure);

		throw new ViewAction($data);

	})
	// Sortir les relevés en masse demande de gérer la structure (`CLAUDE.md` du package, « Qui écrit
	// dans la Boîte »), et il n'y a rien à sortir avant le premier relevé : l'écran ne propose alors
	// pas « Télécharger » (`WaterReadingUi::getTools()`).
	->get('exportCsv', function($data) {

		$data->eStructure->validate('canManage');

		$waters = \toolbox\WaterReadingLib::getSummary($data->eStructure);

		if($waters['eWaterReadingLast']->empty()) {
			throw new NotExpectedAction('No water reading');
		}

		$export = \toolbox\WaterReadingLib::getExport($waters['cWaterMeter']);
		array_unshift($export, new \toolbox\WaterReadingUi()->getExportHeader());

		throw new CsvAction($export, \toolbox\WaterReadingUi::getExportFilename());

	});

// Un relevé se pose depuis la page, sur la ligne de son compteur, ou depuis le planning (alertes lot 2),
// par un panneau dont la réponse ne remplace que les blocs dus de la semaine (`planning/view/due.v.php`).
new \toolbox\WaterReadingPage()
	->getCreateElement(function($data) {

		$data->eStructure = \structure\StructureLib::getById(INPUT('structure'))->validate('canWrite');

		return new \toolbox\WaterReading([
			'structure' => $data->eStructure
		]);

	})
	->create(function($data) {

		$eWaterMeter = \toolbox\WaterMeterLib::getById(GET('meter'), ['id', 'structure', 'name', 'status']);

		if(
			$eWaterMeter->empty() or
			$eWaterMeter['structure']->is($data->e['structure']) === FALSE or
			$eWaterMeter->acceptUpdate() === FALSE
		) {
			throw new NotExistsAction('WaterMeter');
		}

		$data->e['meter'] = $eWaterMeter;
		$data->e['date'] = \Clock::date();
		$data->e['value'] = NULL;

		throw new ViewAction($data);

	})
	->doCreate(function($data) {

		// Un relevé plus bas que le précédent s'enregistre, puis se signale (arbitrage Q2 C de la planche
		// de l'eau) : le message dit ce que le tour du compteur a fait compter. Un écart au-delà du seuil
		// d'alerte s'enregistre aussi, sans confirmation qui bloquerait la saisie : la liste rechargée le
		// porte avec ses deux corrections (retour du 02/10).
		$gap = \toolbox\WaterReadingLib::getGap($data->e);

		if($gap !== [] and $gap['alert']) {
			$success = ['toolbox', 'WaterReading::alerted', ['consumption' => $gap['consumption'], 'date' => $gap['date']]];
		} else if($gap !== [] and $gap['turned']) {
			$success = ['toolbox', 'WaterReading::turned', ['consumption' => $gap['consumption'], 'date' => $gap['date']]];
		} else {
			$success = ['toolbox', 'WaterReading::created'];
		}

		if(\planning\DueLib::isOrigin(POST('origin'))) {

			$data->origin = POST('origin');
			$data->week = POST('week', fn($value) => \Filter::check('week', $value), \Clock::week());
			$data->dues = \planning\DueLib::getForWeek($data->eStructure, $data->week);
			$data->success = $success;

			throw new ViewAction($data, 'planning/due:refresh');

		}

		throw new ReloadAction(...$success);

	});

new \toolbox\WaterReadingPage()
	->getElement(fn($data) => \toolbox\WaterReadingLib::getById(INPUT('id'), ['id', 'structure', 'date', 'value', 'changed', 'changedValue', 'changedDigits', 'meter' => ['name', 'digits', 'status']]))
	->update(fn($data) => throw new ViewAction($data))
	->doUpdate(fn($data) => throw new ReloadAction('toolbox', 'WaterReading::updated'))
	->doDelete(fn($data) => throw new ReloadAction('toolbox', 'WaterReading::deleted'));
?>
