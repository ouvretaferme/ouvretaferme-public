<?php
/**
 * Clean PDP history
 *
 */
new Page()
	->cron('index', function($data) {

		if(\structure\StructureSetting::hasAccountingApp() === FALSE) {
			return;
		}

		\data\PdpHistoryLib::clean();

	}, interval: '37 3 * * *');
?>
