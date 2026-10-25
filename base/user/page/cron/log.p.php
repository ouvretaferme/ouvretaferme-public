<?php
/**
 * Clean user logs
 *
 */
new Page()
	->cron('index', function($data) {

		user\LogLib::clean();

		user\AdminLogAiLib::clean();

		user\LoginAttemptAiLib::clean();

		user\ChallengeAiLib::notifyExpired();
		user\ChallengeAiLib::clean();

		user\LoginLinkAiLib::clean();

		user\LogLib::cleanAuto();

		user\UserLib::cleanForgottenPasswordHash();

	}, interval: '0 6 * * *');
?>
