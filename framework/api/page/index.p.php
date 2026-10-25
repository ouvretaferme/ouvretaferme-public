<?php
new Page()
	->match(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], 'index', function($data) {

		ApiRoute::run(GET('version'), GET('route'));

	});
?>
