<?php
new Page()
	->get('/ferme/{id}/roles', function($data) {

		$data->eStructure = \structure\StructureLib::getById(GET('id'))->validate('acceptRoles', 'canManage');

		\structure\MemberLib::setView('viewTeamCategory', $data->eStructure, \structure\Member::ROLE);

		$data->cRole = \structure\RoleLib::getByStructure($data->eStructure, withAdmin: FALSE);

		if($data->eStructure->acceptHr()) {
			$data->eStructure['absencesPending'] = \hr\AbsenceLib::countPending($data->eStructure);
		}

		throw new ViewAction($data);

	});

new \structure\RolePage(function($data) {

		if(OTF_DEMO) {
			throw new \FailAction('user\User::demo.write');
		}

	})
	->getCreateElement(function($data) {

		return new \structure\Role([
			'structure' => \structure\StructureLib::getById(INPUT('structure')),
		]);

	})
	->create()
	->doCreate(fn($data) => throw new ReloadAction('structure', 'Role::created'))
	->update()
	->doUpdate(fn($data) => throw new ReloadAction('structure', 'Role::updated'))
	->doDelete(fn($data) => throw new RedirectAction(\structure\StructureUi::urlRole($data->e['structure']).'?&success=structure\\Role::deleted'));
?>
