<?php
/**
 * Les encadrants d'une formation, depuis son éditeur : on les ajoute, on règle leur rôle et le
 * contact de la formation, on les retire, dans un panneau. Ils se lisent sur la fiche, dans l'ordre
 * où ils ont été ajoutés. Chaque ligne rattache une personne du réseau (`learning\Supervisor`).
 */
new \learning\StaffPage()
	->getCreateElement(function($data) {

		return new \learning\Staff([
			'course' => \learning\CourseLib::getById(INPUT('course'))->validate('acceptWrite'),
			'supervisor' => new \learning\Supervisor()
		]);

	})
	// `origin=self` vient de « M'ajouter comme encadrant » : le panneau s'ouvre sur ce que le compte
	// sait de la personne. Il ne fait que préremplir ; `doCreate` valide cet envoi comme tout autre.
	->create(function($data) {

		$data->eSupervisor = \learning\SupervisorLib::getCreateElement($data->e['course']['community']);
		$data->e['cSupervisor'] = \learning\SupervisorLib::getForCourse($data->e['course']);

		if(GET('origin') === 'self') {
			\learning\StaffLib::prepareSelf($data->e, $data->eSupervisor, \user\ConnectionLib::getOnline());
		}

		throw new ViewAction($data);

	})
	// Deux chemins, que `source` départage : `pick` rattache une personne que le réseau connait déjà,
	// tout autre envoi crée la personne dans le réseau de la formation puis la rattache. Le second
	// construit la personne d'abord et s'arrête à ses refus : l'étape `Staff::contact.noEmail` lit
	// l'adresse qu'elle vient de recevoir. Le rôle est lu par les deux `build()` : celui de la
	// formation devient le rôle habituel de la personne.
	->post('doCreate', function($data) {

		$fw = new FailWatch();

		$eStaff = new \learning\Staff([
			'course' => \learning\CourseLib::getById(POST('course'))->validate('acceptWrite')
		]);

		$eStaff->validate('canCreate', 'acceptCreate');

		if(POST('source') === 'pick') {

			$eStaff['cStaff'] = \learning\StaffLib::getByCourse($eStaff['course']);
			$eStaff['cSupervisor'] = \learning\SupervisorLib::getForCourse($eStaff['course']);

			$eStaff->build(\learning\StaffLib::getPropertiesCreate(), $_POST, new \PropertyList('create'));

			$fw->validate();

			\learning\StaffLib::create($eStaff);

		} else {

			$eSupervisor = \learning\SupervisorLib::getCreateElement($eStaff['course']['community']);

			$eSupervisor->validate('canCreate', 'acceptCreate');

			$eSupervisor->build(\learning\SupervisorLib::getPropertiesCreate('course'), $_POST, new \PropertyList('create'));

			$fw->validate();

			$eStaff['supervisor'] = $eSupervisor;

			$eStaff->build(\learning\StaffLib::getPropertiesCreate('supervisor'), $_POST, new \PropertyList('create'));

			$fw->validate();

			\learning\StaffLib::createWithSupervisor($eStaff, $eSupervisor);

		}

		$fw->validate();

		throw new ReloadAction('learning', 'Staff::created');

	})
	->update()
	->doUpdate(fn() => throw new ReloadAction('learning', 'Staff::updated'))
	->doDelete(fn() => throw new ReloadAction('learning', 'Staff::deleted'));
?>
