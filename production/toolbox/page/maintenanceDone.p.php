<?php
// « Fait » sur un entretien (arbitrage Q7 de la planche pf-t4-v2) : le panneau part du jour, prend le
// compteur dans les relevés ou celui que la personne met à jour, et dit quand l'entretien reviendra. Le matériel et l'entretien ne sont
// pas construits (`MaintenanceDone`) : ils viennent du lien, l'entretien devant être suivi.
//
// « Consigner un entretien » (retours du 30/09 et du 01/10) ouvre le même panneau sur le matériel seul :
// la ligne entre à l'historique sous son libellé, sans entretien ni « Prochaine fois ».
new \toolbox\MaintenanceDonePage()
	->getCreateElement(function($data) {

		if(INPUT('equipment', '?int') !== NULL) {

			$eEquipment = \toolbox\EquipmentLib::getById(INPUT('equipment'), ['id', 'structure', 'meter', 'status', 'tool' => ['name']]);

			if($eEquipment->empty() or $eEquipment->acceptUpdate() === FALSE) {
				throw new NotExistsAction('Equipment');
			}

			return new \toolbox\MaintenanceDone([
				'structure' => $eEquipment['structure'],
				'equipment' => $eEquipment,
				'maintenance' => new \toolbox\Maintenance(),
				'name' => NULL,
				'date' => \Clock::date(),
				'value' => NULL,
				'photo' => NULL,
				'meterUpdate' => FALSE
			]);

		}

		$eMaintenance = \toolbox\MaintenanceLib::getById(INPUT('maintenance'), [
			'id', 'structure', 'name', 'everyValue', 'everyMonths', 'status',
			'equipment' => ['structure', 'meter', 'status', 'tool' => ['name']]
		]);

		if(
			$eMaintenance->empty() or
			$eMaintenance->acceptUpdate() === FALSE or
			$eMaintenance['equipment']->acceptUpdate() === FALSE
		) {
			throw new NotExistsAction('Maintenance');
		}

		return new \toolbox\MaintenanceDone([
			'structure' => $eMaintenance['structure'],
			'equipment' => $eMaintenance['equipment'],
			'maintenance' => $eMaintenance,
			'name' => NULL,
			'date' => \Clock::date(),
			'value' => NULL,
			'photo' => NULL,
			'meterUpdate' => FALSE
		]);

	})
	->create(function($data) {

		\toolbox\MaintenanceDoneLib::prepareValue($data->e);
		\toolbox\EquipmentLib::fillReadingLast($data->e['equipment']);

		// Quand l'entretien ne se note qu'avec le compteur, le panneau ouvre le champ sans demander s'il
		// faut le mettre à jour : « Non » mènerait à un refus.
		$data->e['valueRequired'] = \toolbox\MaintenanceDoneLib::isValueRequired($data->e);
		$data->e['meterUpdate'] = $data->e['valueRequired'];

		// Une intervention ou un entretien « Une seule fois » ne reviennent pas.
		if($data->e['maintenance']->notEmpty() and $data->e['maintenance']->isRegular()) {
			$data->next = \toolbox\MaintenanceLib::getPlanned($data->e['maintenance'], $data->e['date'], $data->e['value']);
		} else {
			$data->next = NULL;
		}

		// Ce que « Fait » écrit au planning : le temps, et l'intervention tant que la ferme ne l'a pas réglée.
		\toolbox\MaintenanceTaskLib::fillPanel($data->e);

		// Le compteur des relevés a fait « Prochaine fois », mais le champ s'ouvre vide, comme dans
		// « Consigner un entretien » : le compteur actuel est rappelé sous lui (retour du 01/10).
		$data->e['value'] = NULL;

		throw new ViewAction($data);

	})
	// « Prochaine fois », relu quand la date change. Rien ne s'écrit : une date invalide ne donne pas
	// d'échéance, et c'est l'envoi qui la refusera, sur son champ.
	->create(function($data) {

		// Une intervention ou un entretien « Une seule fois » n'ont pas d'échéance à relire.
		if($data->e['maintenance']->empty() or $data->e['maintenance']->isRegular() === FALSE) {
			throw new NotExistsAction('Maintenance');
		}

		$data->e['date'] = POST('date');

		// Le compteur mis à jour fait repartir l'échéance de ce qu'il porte, même encore refusé : l'envoi
		// le jugera, et la prochaine fois suit ce que la personne tape. Tant que le champ est vide, elle
		// part des relevés, comme avec « Non ».
		if(POST('meterUpdate') === '1' and POST('value', '?int') !== NULL) {
			$data->e['value'] = POST('value', '?int');
		} else if(\util\DateLib::isValid($data->e['date'])) {
			\toolbox\MaintenanceDoneLib::prepareValue($data->e);
		}

		$data->next = \toolbox\MaintenanceLib::getPlanned($data->e['maintenance'], $data->e['date'], $data->e['value']);

		throw new ViewAction($data, ':next');

	}, method: 'post', page: 'next')
	// Un compteur mis à jour qui sort de l'ordre des relevés demande une confirmation, comme « Relever »
	// (`page/equipmentReading.p.php`). Depuis le planning, la réponse ne remplace que les blocs dus de
	// la semaine (`planning/view/due.v.php`).
	->doCreate(function($data) {

		$success = ['toolbox', $data->e['maintenance']->empty() ? 'MaintenanceDone::createdIntervention' : 'MaintenanceDone::created'];

		if(\planning\DueLib::isOrigin(POST('origin'))) {

			$data->origin = POST('origin');
			$data->eStructure = $data->e['structure'];
			$data->week = POST('week', fn($value) => \Filter::check('week', $value), \Clock::week());
			$data->dues = \planning\DueLib::getForWeek($data->eStructure, $data->week);
			$data->success = $success;

			throw new ViewAction($data, 'planning/due:refresh');

		}

		throw new ReloadAction(...$success);

	}, onKo: function(\FailWatch $fw) {

		$eEquipmentReading = \toolbox\EquipmentReadingLib::getConflictFromFail($fw);

		if($eEquipmentReading->notEmpty()) {
			throw new ViewAction((object)['eEquipmentReading' => $eEquipmentReading], 'toolbox/equipmentReading:confirm');
		}

	});

new \toolbox\MaintenanceDonePage()
	->getElement(fn($data) => \toolbox\MaintenanceDoneLib::getById(INPUT('id'), [
		'id', 'structure', 'name', 'date', 'value', 'cost', 'comment', 'photo',
		'maintenance' => ['name'],
		'equipment' => ['structure', 'meter', 'status', 'tool' => ['name']]
	]))
	// La photo d'une ligne de l'historique, en grand : tout membre qui lit le carnet la voit.
	->read('photo', function($data) {

		if($data->e['photo'] === NULL) {
			throw new NotExistsAction();
		}

		throw new ViewAction($data);

	})
	->update()
	->doUpdate(fn($data) => throw new ReloadAction('toolbox', 'MaintenanceDone::updated'))
	->doDelete(fn($data) => throw new ReloadAction('toolbox', 'MaintenanceDone::deleted'));
?>
