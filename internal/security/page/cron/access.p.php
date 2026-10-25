<?php
new Page()
	->cron('report', function($data) {

		\security\AccessReportLib::generate();

	}, interval: '*/10 * * * *')
	->cron('events', function($data) {

		\security\AccessEventLib::collect();
		\security\AccessEventLib::clean();

	}, interval: '*/10 * * * *')
	->cron('review', function($data) {

		\security\ScanReviewAiLib::notify();

	}, interval: '0 8 1 * *');
?>
