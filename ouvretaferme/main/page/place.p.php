<?php
new Page()
	->post('citiesByName', function($data) {

		$query = POST('query');
		$eStructure = \structure\StructureLib::getById(POST('structure'))->validate('canWrite');

		$data->locations = \main\PlaceLib::searchCitiesByName($eStructure, $query);

		throw new ViewAction($data);

	})
	->post('addressesByName', function($data) {

		$query = POST('query');
		$eStructure = \structure\StructureLib::getById(POST('structure'))->validate('canWrite');

		$data->addresses = \main\PlaceLib::searchAddressesByName($eStructure, $query);

		throw new ViewAction($data);

	})
	->post('citiesByLngLat', function($data) {

		\user\ConnectionLib::checkLogged();

		$lngLat = [POST('lng', '?float'), POST('lat', '?float')];

		if($lngLat[0] === NULL or $lngLat[1] === NULL or abs($lngLat[0]) > 180 or abs($lngLat[1]) > 90) {
			throw new NotExpectedAction('Invalid point');
		}

		throw new JsonAction([
			'city' => \main\PlaceLib::searchCityByLngLat($lngLat)
		]);

	})
	->post('altitudeByLngLat', function($data) {

		\user\ConnectionLib::checkLogged();

		$lngLat = [POST('lng', '?float'), POST('lat', '?float')];

		if($lngLat[0] === NULL or $lngLat[1] === NULL or abs($lngLat[0]) > 180 or abs($lngLat[1]) > 90) {
			throw new NotExpectedAction('Invalid point');
		}

		throw new JsonAction([
			'altitude' => \main\PlaceLib::searchAltitudeByLngLat($lngLat)
		]);

	})
	->get('siret', function($data) {

		$siret = \structure\StructureLib::getSiretApi(GET('siret'));

		throw new JsonAction(['result' => $siret]);

	})
	->get('siren', function($data) {

		$siret = \structure\StructureLib::getSirenApi(GET('siren'));

		throw new JsonAction(['result' => $siret]);

	});
?>
