<?php
// Le droit d'écrire se lit dans DecorBuilding::canWrite(), que les actions de ModulePage valident d'office :
// aucune route de cette page n'en écrit une autre (FlagSentinel)
new \map\DecorBuildingPage(function($data) {

		\user\ConnectionLib::checkLogged();

	})
	// Le hangar posé que l'éditeur personnalise se relit borné par la structure, dans le panneau comme dans l'action, qui le relit
	// encore sous verrou (`DecorBuildingLib::create()`)
	->getCreateElement(function($data) {

		$eStructure = \structure\StructureLib::getById(INPUT('structure'))->validate();

		return new \map\DecorBuilding([
			'structure' => $eStructure,
			'eDecor' => \map\DecorLib::getHangar($eStructure, INPUT('decor', '?int'))
		]);

	})
	// L'éditeur s'ouvre sur les choix de départ du type : un hangar quand il part d'un hangar posé, sinon le type demandé
	->create(function($data) {

		if($data->e['eDecor']->notEmpty()) {
			$type = \map\DecorBuilding::HANGAR;
		} else if(GET('type') === \map\DecorBuilding::HOUSE) {
			$type = \map\DecorBuilding::HOUSE;
		} else {
			$type = \map\DecorBuilding::BUILDING;
		}

		$initial = \map\DecorBuildingLib::getChoices()[$type]['initial'];

		$data->e->merge([
			'type' => $type,
			'name' => NULL,
			'length' => $initial['length'],
			'width' => $initial['width'],
			'height' => $initial['height'],
			'settings' => $initial['settings']
		]);

		$data->choices = \map\DecorBuildingLib::getChoices();
		$data->bake = \scene\BakeLib::getCatalogAndVersions();

		\map\DecorUploadLib::fillEditor($data->e);

		throw new \ViewAction($data);

	})
	// La scène reprend le bâtiment enregistré sans se recharger : il entre dans la rangée, et le hangar personnalisé devient sa pose
	->doCreate(function($data) {

		$data->building = \map\DecorBuildingLib::getScene($data->e);

		throw new \ViewAction($data);

	})
	->update(function($data) {

		$data->choices = \map\DecorBuildingLib::getChoices();
		$data->bake = \scene\BakeLib::getCatalogAndVersions();

		throw new \ViewAction($data);

	})
	->doUpdate(function($data) {

		$data->building = \map\DecorBuildingLib::getScene($data->e);

		throw new \ViewAction($data);

	})
	->doDelete(fn($data) => throw new \ViewAction($data));
?>
