<?php
// Le matériel d'un entretien n'est pas construit (`Maintenance::properties()`) : il vient du lien qui
// ouvre le panneau, et doit être un matériel suivi de la structure. `frequency` dit ce que le panneau
// coche : « Une seule fois » pour un entretien neuf (retour du 01/10), « Régulièrement »
// pour un entretien modifié qui a une fréquence.
new \toolbox\MaintenancePage()
	->getCreateElement(function($data) {

		$eEquipment = \toolbox\EquipmentLib::getById(INPUT('equipment'), ['id', 'structure', 'meter', 'status', 'tool' => ['name']]);

		if($eEquipment->empty() or $eEquipment->acceptUpdate() === FALSE) {
			throw new NotExistsAction('Equipment');
		}

		\toolbox\EquipmentLib::fillReadingLast($eEquipment);

		return new \toolbox\Maintenance([
			'structure' => $eEquipment['structure'],
			'equipment' => $eEquipment,
			'frequency' => 'once'
		]);

	})
	->create()
	->doCreate(fn($data) => throw new ReloadAction('toolbox', 'Maintenance::created'));

new \toolbox\MaintenancePage()
	->getElement(function($data) {

		$select = \toolbox\Maintenance::getSelection();
		$select['equipment'] = ['meter', 'tool' => ['name']];

		$eMaintenance = \toolbox\MaintenanceLib::getById(INPUT('id'), $select);

		if($eMaintenance->notEmpty()) {
			$eMaintenance['frequency'] = $eMaintenance->isRegular() ? 'regular' : 'once';
		}

		return $eMaintenance;

	})
	->update(function($data) {

		\toolbox\EquipmentLib::fillReadingLast($data->e['equipment']);

		throw new ViewAction($data);

	})
	->doUpdate(fn($data) => throw new ReloadAction('toolbox', 'Maintenance::updated'), propertiesUpdate: ['name', 'everyValue', 'everyMonths', 'plannedValue', 'plannedDate'])
	->doUpdateProperties('doUpdateStatus', ['status'], fn($data) => throw new ReloadAction('toolbox', 'Maintenance::deleted'));

// « Ignorer ce retard » et la croix du planning se jugent sur l'échéance du jour : l'entretien se
// relit tel que le carnet le montre, avec son matériel, son compteur et son historique
// (`EquipmentLib::getByStructure()`).
new \toolbox\MaintenancePage()
	->getElement(function($data) {

		$eMaintenance = \toolbox\MaintenanceLib::getById(POST('id'), ['id', 'structure', 'equipment']);

		if($eMaintenance->empty()) {
			return $eMaintenance;
		}

		$cEquipment = \toolbox\EquipmentLib::getByStructure($eMaintenance['structure']);

		if($cEquipment->offsetExists($eMaintenance['equipment']['id']) === FALSE) {
			throw new NotExistsAction('Maintenance');
		}

		$cMaintenance = $cEquipment[$eMaintenance['equipment']['id']]['cMaintenance'];

		if($cMaintenance->offsetExists($eMaintenance['id']) === FALSE) {
			throw new NotExistsAction('Maintenance');
		}

		return $cMaintenance[$eMaintenance['id']];

	})
	->write('doIgnore', function($data) {

		\toolbox\MaintenanceLib::ignore($data->e);

		throw new ReloadAction('toolbox', 'Maintenance::ignored');

	}, validate: ['canUpdate', 'acceptIgnore'])
	// La croix du planning : le même geste qu'« Ignorer ce retard », sur un entretien bientôt dû aussi,
	// et dont la réponse ne remplace que les blocs dus de la semaine.
	->write('doSkip', function($data) {

		\toolbox\MaintenanceLib::ignore($data->e);

		$data->eStructure = $data->e['structure'];
		$data->origin = POST('origin', fn($value) => \planning\DueLib::isOrigin($value), \planning\DueLib::ORIGIN_BLOCK);
		$data->week = POST('week', fn($value) => \Filter::check('week', $value), \Clock::week());
		$data->dues = \planning\DueLib::getForWeek($data->eStructure, $data->week);
		$data->success = ['toolbox', 'Maintenance::skipped'];

		throw new ViewAction($data, 'planning/due:skip');

	}, validate: ['canUpdate', 'acceptSkip'])
	// Le décalage à la semaine suivante (retour 15 du 04/10), sur tout entretien que le planning
	// montre, « Une seule fois » compris : décalé, il reste dû.
	->write('doPostpone', function($data) {

		$data->eStructure = $data->e['structure'];
		$data->origin = POST('origin', fn($value) => \planning\DueLib::isOrigin($value), \planning\DueLib::ORIGIN_BLOCK);
		$data->week = POST('week', fn($value) => \Filter::check('week', $value), \Clock::week());

		\toolbox\MaintenanceLib::postpone($data->e);

		$data->dues = \planning\DueLib::getForWeek($data->eStructure, $data->week);
		$data->success = ['toolbox', 'Maintenance::postponed'];

		throw new ViewAction($data, 'planning/due:postpone');

	}, validate: ['canUpdate', 'acceptUpdate']);
?>
