<?php
// Le droit d'écrire se lit dans Configuration::canWrite(), que les actions de ModulePage valident d'office (FlagSentinel)
new \map\ConfigurationPage(function($data) {

		\user\ConnectionLib::checkLogged();

	})
	->getCreateElement(function($data) {

		return new \map\Configuration([
			'structure' => \structure\StructureLib::getById(INPUT('structure'))->validate(),
		]);

	})
	->doCreate(function($data) {
		throw new \VoidAction();
	});

new \map\ConfigurationPage()
	->write('doSeasonFirst', function($data) {

		$data->increment = POST('increment', 'int');
		\map\ConfigurationLib::updateSeasonFirst($data->e, $data->increment);

		$redirect = \structure\StructureUi::urlCultivationSeries($data->e['structure'], \structure\Member::AREA, season: $data->e['seasonFirst'] + $data->increment);
		throw new RedirectAction($redirect);

	})
	->write('doSeasonLast', function($data) {

		$data->increment = POST('increment', 'int');
		\map\ConfigurationLib::updateSeasonLast($data->e, $data->increment);

		$redirect = \structure\StructureUi::urlCultivationSeries($data->e['structure'], \structure\Member::AREA, season: $data->e['seasonLast'] + $data->increment);
		throw new RedirectAction($redirect);

	})
	->read('calendarMonth', function($data) {

		$data->e['calendarMonthStart'] = GET('calendarMonthStart', '?int');
		$data->e['calendarMonthStop'] = GET('calendarMonthStop', '?int');
		$data->e['calendarMonths'] = ($data->e['calendarMonthStart'] ? (12 - $data->e['calendarMonthStart'] + 1) : 0) + 12 + ($data->e['calendarMonthStop'] ?? 0);

		throw new ViewAction($data);

	})
	->doUpdateProperties('doUpdatePlanningDelayedMax', ['planningDelayedMax'], fn() => throw new ReloadAction());

/**
 * L'écran « Les cultures ». Les horaires de la journée y restent alors qu'ils appartiennent au temps de travail
 * (`structure\Configuration.timeRange`) : le formulaire écrit les deux lignes, chacune construite par son `build()`.
 * `hasWorkingTime()` lit le type et les drapeaux de la structure, qu'un délégué ne garantit pas au-delà de son id :
 * la structure se relit.
 */
new \map\ConfigurationPage()
	->applyElement(function($data, \map\Configuration $e) {
		$e['structure'] = \structure\StructureLib::getById($e['structure']);
	})
	->update(function($data) {

		$data->eStructure = $data->e['structure'];

		$data->e['cPlantRotationExclude'] = \plant\PlantLib::getByIds($data->e['rotationExclude'], sort: 'name');
		$data->e['eConfigurationStructure'] = \structure\ConfigurationLib::readByStructure($data->eStructure);

		throw new ViewAction($data);

	}, page: 'updateCrop')
	->write('doUpdateCrop', function($data) {

		$fw = new FailWatch();

		$properties = \map\ConfigurationLib::getPropertiesCrop();
		$data->e->build($properties, $_POST, new \PropertyList('update'));

		$eConfigurationStructure = \structure\ConfigurationLib::readByStructure($data->e['structure']);
		$propertiesStructure = $data->e['structure']->hasWorkingTime() ? ['timeRange'] : [];

		if($propertiesStructure !== []) {
			$eConfigurationStructure->build($propertiesStructure, $_POST, new \PropertyList('update'));
		}

		$fw->validate();

		\map\Configuration::model()->beginTransaction();

			\map\ConfigurationLib::update($data->e, $properties);

			if($propertiesStructure !== []) {
				\structure\ConfigurationLib::update($eConfigurationStructure, $propertiesStructure);
			}

		\map\Configuration::model()->commit();

		throw new ReloadAction('map', 'Configuration::updatedCrop');

	});
?>
