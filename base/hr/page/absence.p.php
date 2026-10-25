<?php
new Page()
	->get('/ferme/{id}/equipe/absences', function($data) {

		$data->eStructure = \structure\StructureLib::getById(GET('id'))->validate('canManage', 'acceptHr');

		// La navigation s'arrête à l'année en cours, comme l'onglet Personnes : une absence à venir qui la
		// déborde y paraît déjà.
		$data->year = min(GET('year', 'int', \Clock::year()), \Clock::year());

		$data->cAbsencePending = \hr\AbsenceLib::getPendingByStructure($data->eStructure);
		$data->months = \hr\AbsenceLib::getValidatedByStructureForYear($data->eStructure, $data->year, withType: TRUE);

		$data->eStructure['absencesPending'] = $data->cAbsencePending->count();

		throw new ViewAction($data);

	});

new \hr\AbsencePage(
		NULL,
		fn(\hr\Absence $e) => $e['chooseUser'] ?
			['user', 'unit', 'type', 'from', 'to', 'duration'] :
			['unit', 'type', 'from', 'to', 'duration'],
		['unit', 'type', 'from', 'to', 'duration']
	)
	// Le gérant choisit la personne et saisit une absence validée ; une personne au planning demande pour
	// elle-même, et ne poste jamais `user` : la liste construite se décide ici, sur le droit, et non dans le Lib,
	// qui ne connaît que l'origine. Depuis « Mes absences » du planning (`origin=planning`), le gérant aussi
	// saisit pour lui-même (décidé le 20/10) : `user` ne se construit pas, si bien qu'aucun POST
	// venu de là ne vise quelqu'un d'autre.
	->getCreateElement(function($data) {

		$eStructure = \structure\StructureLib::getById(INPUT('structure'))->validate();
		$eUserOnline = \user\ConnectionLib::getOnline();

		if($eStructure->canManage() and INPUT('origin') !== 'planning') {

			$cUser = \hr\TeammateLib::getUsersForAbsence($eStructure);
			$userId = INPUT('user', '?int');

			return new \hr\Absence([
				'structure' => $eStructure,
				'chooseUser' => TRUE,
				'cUser' => $cUser,
				'user' => ($userId !== NULL and $cUser->offsetExists($userId)) ? $cUser[$userId] : new \user\User(),
				'unit' => \hr\Absence::DAY,
				'status' => \hr\Absence::VALIDATED,
				'from' => NULL,
				'to' => NULL,
				'duration' => NULL
			]);

		}

		// Le gérant qui saisit pour lui-même passe la même porte qu'une personne au planning, que `canCreate()` ne
		// lui pose pas : une de ses périodes court aujourd'hui ou commence plus tard.
		if(
			$eStructure->canManage() and
			\hr\PresenceLib::isPresentBetween($eStructure, $eUserOnline, \Clock::date(), NULL) === FALSE
		) {
			throw new NotExpectedAction('Not present');
		}

		return new \hr\Absence([
			'structure' => $eStructure,
			'chooseUser' => FALSE,
			'user' => $eUserOnline,
			'unit' => \hr\Absence::DAY,
			'status' => $eStructure->canManage() ? \hr\Absence::VALIDATED : \hr\Absence::PENDING,
			'from' => NULL,
			'to' => NULL,
			'duration' => NULL
		]);

	})
	->create(function($data) {

		throw new \ViewAction($data);

	})
	->doCreate(function($data) {

		throw new ReloadAction('hr', $data->e->isPending() ? 'Absence::requested' : 'Absence::created');

	})
	->update()
	->doUpdate(function($data) {
		throw new ReloadAction('hr', 'Absence::updated');
	})
	->doDelete(function($data) {
		throw new ReloadAction('hr', 'Absence::deleted');
	})
	// Valider et Refuser demandent le droit et la RH, pas le statut : une demande déjà tranchée, depuis un second
	// onglet resté ouvert, se refuse dans `AbsenceLib::decide()` par `status.decided`, que la personne lit.
	// Refusée ici par `acceptValidate()`, elle s'arrêterait en erreur interne.
	->write('doValidate', function($data, \hr\Absence $e) {

		$fw = new FailWatch();

		\hr\AbsenceLib::validate($e);

		$fw->validate();

		throw new ReloadAction('hr', 'Absence::validated');

	}, ['canValidate', 'acceptWrite'])
	->write('doRefuse', function($data, \hr\Absence $e) {

		$fw = new FailWatch();

		\hr\AbsenceLib::refuse($e);

		$fw->validate();

		throw new ReloadAction('hr', 'Absence::refused');

	}, ['canValidate', 'acceptWrite']);
?>
