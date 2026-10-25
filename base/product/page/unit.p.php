<?php
new \product\UnitPage()
	->getCreateElement(function($data) {

		return new \product\Unit([
			'structure' => \structure\StructureLib::getById(INPUT('structure'))->validate('acceptUnits'),
		]);

	})
	->create()
	->doCreate(fn() => throw new ReloadAction('product', 'Unit::created'));

new \product\UnitPage()
	->applyElement(function($data, \product\Unit $e) {

		if($e['structure']->notEmpty()) {
			\structure\StructureLib::getById($e['structure'])->validate('acceptUnits');
		}

	})
	->quick(['singular', 'plural', 'short'])
	->update()
	->doUpdate(fn() => throw new ReloadAction('product', 'Unit::updated'))
	->doDelete(fn() => throw new ReloadAction('product', 'Unit::deleted'));

new Page()
	->get('manage', function($data) {

		$data->eStructure = \structure\StructureLib::getById(GET('structure'))->validate('canManage', 'acceptUnits');
		$data->cUnit = \product\UnitLib::getByStructure($data->eStructure, sort: new \Sql('fqn IS NULL, id ASC'));

		throw new \ViewAction($data);

	});
?>
