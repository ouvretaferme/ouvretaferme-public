<?php
new Page()
	->cron('index', function($data) {

		\dev\ErrorPhpLib::clean();
		\dev\QueryLib::clean();

	}, interval: '47 5 * * *');
?>
