<?php
/**
 * Close expired user accounts
 *
 */
new Page()
	->cron('clean', function($data) {

		\structure\InviteLib::clean();

	}, interval: '16 0 * * *');
?>