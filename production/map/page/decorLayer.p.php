<?php
// Le droit d'écrire se lit dans DecorLayer::canWrite(), que les trois actions de ModulePage
// valident d'office : aucune route de cette page n'en écrit une autre (FlagSentinel)
new \map\DecorLayerPage(function($data) {

		\user\ConnectionLib::checkLogged();

	})
	->getCreateElement(function($data) {

		return new \map\DecorLayer([
			'structure' => \structure\StructureLib::getById(INPUT('structure'))->validate()
		]);

	})
	->read('getDelete', function($data) {

		$data->decor = \map\DecorLib::countByLayer($data->e);

		throw new \ViewAction($data);

	})
	->doCreate(function($data) {
		throw new \ViewAction($data);
	})
	->doUpdateProperties('doUpdateName', ['name'], function($data) {
		throw new \ViewAction($data);
	})
	->doDelete(function($data) {
		throw new \ViewAction($data);
	});
?>
