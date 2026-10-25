<?php
new \structure\SectionPage(function($data) {

		\user\ConnectionLib::checkLogged();

	})
	->getCreateElement(function($data) {

		$data->eStructure = \structure\StructureLib::getById(INPUT('structure'));

		return new \structure\Section([
			'structure' => $data->eStructure
		]);

	})
	->create()
	->doCreate(fn($data) => throw new ReloadAction('structure', 'Section::created'))
	->applyElement(function($data, \structure\Section $eSection) {

		// Le groupe arrive fantôme de getById() : le sous-groupe ne charge que son id. Les écrans
		// en veulent le nom, et acceptWrite() son type.
		$data->eStructure = \structure\StructureLib::getById($eSection['structure']);
		$eSection['structure'] = $data->eStructure;

	})
	->update()
	->doUpdate(fn($data) => throw new BackAction())
	->doUpdateProperties('doUpdateStatus', ['status'], fn($data) => throw new ReloadAction('structure', 'Section::updated'))
	->update(function($data) {

		$data->cMemberStructure = \structure\MemberLib::getByStructure($data->eStructure, index: ['id']);

		$data->e['cMemberStructure'] = $data->cMemberStructure;

		\structure\SectionLib::fillUsers(
			new \Collection([$data->e]),
			\structure\MemberLib::getUsersByStructure($data->eStructure)
		);

		throw new ViewAction($data);

	}, page: 'compose')
	->doUpdateProperties('doCompose', ['users'], fn($data) => throw new BackAction())
	->doDelete(fn($data) => throw new ReloadAction('structure', 'Section::deleted'));

?>
