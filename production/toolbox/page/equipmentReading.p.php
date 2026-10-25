<?php
// Un relevé qui sort de l'ordre du compteur n'est pas refusé mais demande une confirmation (retour
// du 01/10, « ne pas bloquer mais mettre un confirm, car cela peut être la correction d'une erreur »). Le
// refus du compteur, quand il est le seul, devient la question de l'instruction `confirmReading`
// (`static/js/instruction.js`), qui renvoie le formulaire avec `meterConfirmed`. C'est le rôle que `onKo` tient ici : la couche ajax reste
// celle du formulaire, seul le refus qu'une réponse lève prend une autre forme qu'un message.
$confirm = function(\FailWatch $fw) {

	$eEquipmentReading = \toolbox\EquipmentReadingLib::getConflictFromFail($fw);

	if($eEquipmentReading->notEmpty()) {
		throw new ViewAction((object)['eEquipmentReading' => $eEquipmentReading], 'toolbox/equipmentReading:confirm');
	}

};

// Le relevé de la ligne « Compteur » d'un onglet du carnet (arbitrage Q7 de la planche pf-t4-v2) : le
// matériel est celui de l'onglet, la date et les heures sont saisies. Depuis le planning (alertes lot 2),
// le même relevé passe par un panneau, et la réponse ne remplace que les blocs dus de la semaine
// (`planning/view/due.v.php`).
new \toolbox\EquipmentReadingPage()
	->getCreateElement(function($data) {

		$eStructure = \structure\StructureLib::getById(INPUT('structure'))->validate('canWrite');

		return new \toolbox\EquipmentReading([
			'structure' => $eStructure
		]);

	})
	->create(function($data) {

		$eEquipment = \toolbox\EquipmentLib::getById(GET('equipment'), ['id', 'structure', 'meter', 'status', 'tool' => ['name']]);

		if(
			$eEquipment->empty() or
			$eEquipment['structure']->is($data->e['structure']) === FALSE or
			$eEquipment->acceptUpdate() === FALSE or
			$eEquipment->hasMeter() === FALSE
		) {
			throw new NotExistsAction('Equipment');
		}

		$data->e['equipment'] = $eEquipment;
		$data->e['date'] = \Clock::date();
		$data->e['value'] = NULL;

		throw new ViewAction($data);

	})
	->doCreate(function($data) {

		if(\planning\DueLib::isOrigin(POST('origin'))) {

			$data->origin = POST('origin');
			$data->eStructure = $data->e['structure'];
			$data->week = POST('week', fn($value) => \Filter::check('week', $value), \Clock::week());
			$data->dues = \planning\DueLib::getForWeek($data->eStructure, $data->week);
			$data->success = ['toolbox', 'EquipmentReading::created'];

			throw new ViewAction($data, 'planning/due:refresh');

		}

		throw new ReloadAction('toolbox', 'EquipmentReading::created');

	}, onKo: $confirm);

// Un relevé se corrige ou se supprime depuis l'historique du matériel (retour du 30/09).
new \toolbox\EquipmentReadingPage()
	->getElement(fn($data) => \toolbox\EquipmentReadingLib::getById(INPUT('id'), [
		'id', 'structure', 'date', 'value',
		'equipment' => ['structure', 'meter', 'status', 'tool' => ['name']]
	]))
	->update()
	->doUpdate(fn($data) => throw new ReloadAction('toolbox', 'EquipmentReading::updated'), onKo: $confirm)
	->doDelete(fn($data) => throw new ReloadAction('toolbox', 'EquipmentReading::deleted'));
?>
