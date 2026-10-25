<?php
new Page()
	->get('/developer/postman-collection.json', function($data) {

		$path = \Package::getPath('main').'/static/document/postman-collection.json';

		if(is_file($path) === FALSE) {
			throw new StatusAction(404);
		}

		throw new DataAction(file_get_contents($path), 'application/json');

	});
?>