<?php
/**
 * Close expired user accounts
 *
 */
new Page()
	->cron('index', function($data) {

		\map\ConfigurationLib::createNextSeason();

	}, interval: '0 0 1 10 *');
?>