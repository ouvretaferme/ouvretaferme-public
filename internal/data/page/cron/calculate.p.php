<?php
/**
 * Compute structure data
 *
 */
new Page()
	->cron('index', function($data) {

		if(\structure\StructureSetting::hasAccountingApp() === FALSE) {
			return;
		}

		\data\AnalyticsLib::calculate();

	}, interval: '13 0 * * *');
