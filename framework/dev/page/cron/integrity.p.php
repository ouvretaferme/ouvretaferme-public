<?php
new Page()
	->cron('index', function($data) {

		\dev\IntegrityLib::calculate();

	}, interval: '29 0 * * *');
