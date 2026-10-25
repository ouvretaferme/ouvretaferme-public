<?php
// Le droit d'écrire se lit dans Decor::canWrite(), que les actions de ModulePage valident d'office
// et que doAdopt et doUnadopt valident elles-mêmes : aucune route de cette page n'en écrit une autre (FlagSentinel)
new \map\DecorPage(function($data) {

		\user\ConnectionLib::checkLogged();

	})
	->getCreateElement(function($data) {

		$eStructure = \structure\StructureLib::getById(INPUT('structure'))->validate();

		return new \map\Decor([
			'structure' => $eStructure,
			'center' => \map\DecorLib::getCenter($eStructure)
		]);

	})
	->applyElement(function($data, \map\Decor $e) {

		$e['center'] = \map\DecorLib::getCenter($e['structure']);

	})
	->doCreate(function($data) {
		throw new \ViewAction($data);
	})
	->doUpdateProperties('doUpdatePosition', fn(\map\Decor $e) => \map\DecorLib::getPropertiesPosition($e), function($data) {
		throw new \ViewAction($data);
	})
	->doUpdateProperties('doUpdateEnclosure', ['model', 'hedge', 'gate'], function($data) {
		throw new \ViewAction($data);
	})
	->doUpdateProperties('doUpdatePipe', ['diameter', 'laying'], function($data) {
		throw new \ViewAction($data);
	})
	->doDelete(function($data) {
		throw new \ViewAction($data);
	})
	// Une action hors de ModulePage ne valide rien d'office : le droit se pose ici, avant toute construction
	->post('doAdopt', function($data) {

		$eStructure = \structure\StructureLib::getById(POST('structure'))->validate();

		$e = new \map\Decor([
			'structure' => $eStructure,
			'center' => \map\DecorLib::getCenter($eStructure)
		]);

		$e->validate('canCreate', 'acceptCreate');

		$fw = new \FailWatch();

		$data->c = \map\DecorLib::buildDefault($e, $_POST);

		$fw->validate();

		\map\DecorLib::adopt($eStructure, $data->c);

		$fw->validate();

		throw new \ViewAction($data);

	})
	->post('doUnadopt', function($data) {

		$eStructure = \structure\StructureLib::getById(POST('structure'))->validate();

		new \map\Decor(['structure' => $eStructure])->validate('canDelete', 'acceptDelete');

		$fw = new \FailWatch();

		\map\DecorLib::unadopt($eStructure, array_map('intval', POST('id', 'array', [])));

		$fw->validate();

		throw new \VoidAction();

	});
?>
