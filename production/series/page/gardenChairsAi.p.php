<?php
new Page(function($data) {

	\structure\StructureLib::load($data);

	$data->eStructure->validate('canManage');

})
	->get('index', function($data) {

		$data->season = \structure\MemberLib::getDynamicSeason($data->eStructure, GET('season', 'int'));
		\map\SeasonLib::setOnline($data->season);

		$data->groups = \series\GardenChairsAiLib::getByStructure($data->eStructure, $data->season);

		throw new ViewAction($data, ':gardenChairs');

	})
	->post('doMove', function($data) {

		$season = POST('season', 'int');

		if($data->eStructure->conf(\map\Configuration::class)->isSeasonValid($season) === FALSE) {
			throw new NotExpectedAction('Invalid season');
		}

		$destinations = array_map('intval', POST('to', 'array'));

		$fw = new FailWatch();

		$groups = \series\GardenChairsAiLib::getByStructure($data->eStructure, $season);
		$cPlot = \series\GardenChairsAiLib::getGroupByPlot($groups, (int)array_key_first($destinations));

		$moves = \series\GardenChairsAiLib::move($data->eStructure, $cPlot, $destinations, $season);

		$fw->validate();

		$names = [];

		foreach(array_keys($moves) as $plotId) {
			$names[] = $cPlot[$plotId]['name'];
		}

		throw new ReloadAction('series', 'Place::plotsMoved', $names);

	});
?>
