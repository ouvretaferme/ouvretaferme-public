<?php
new JsonView('citiesByName', function($data, AjaxTemplate $t) {

	$results = [];

	foreach($data->locations as $location) {

		$results[] = [
			'value' => $location['name'],
			'itemHtml' => $location['address'],
			'itemText' => $location['address'],
			'lngLat' => $location['lngLat'],
			'department' => $location['department'],
			'country' => $location['country']
		];

	}
	$t->push('results', $results);

});

new JsonView('addressesByName', function($data, AjaxTemplate $t) {

	$results = [];

	foreach($data->addresses as $address) {

		$results[] = [
			'value' => $address['street'],
			'itemHtml' => encode($address['address']),
			'itemText' => $address['street'],
			'postcode' => $address['postcode'],
			'city' => $address['city'],
			'country' => $address['country']
		];

	}

	$t->push('results', $results);

});
