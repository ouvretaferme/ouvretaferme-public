<?php
/**
 * A lost task is kept its full retention because it is the only trace of what was not done, and
 * replaying it by hand requires having it in sight.
 */
new Page()
	->cron('index', function($data) {

		\queue\QueueLib::clean();

	}, interval: '0 3 * * *');
?>
