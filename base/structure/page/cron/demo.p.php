<?php
/**
 * Regerates demo
 *
 */
new Page()
	->cron('index', function($data) {

		\structure\DemoLib::rebuild();

	}, interval: '0 4 * * *');
?>
