<?php

new Page()
	->cron('index', function($data) {

		\api\LogLib::clean();
		\api\RateLimitLib::clean();

	}, interval: '29 0 * * *');
?>
