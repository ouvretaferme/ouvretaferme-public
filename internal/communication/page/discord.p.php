<?php
new Page()
	->get('/discord', function($data) {

		$code = GET('code', '?string', NULL);
		$state = GET('state', '?string', NULL);

		if($code === NULL or $state === NULL) {
			throw new RedirectAction(Lime::getUrl().'?error=communication\\DiscordAccess::failed');
		}

		$eDiscordAccess = \communication\DiscordAccessLib::handleCallback($code, $state);

		if($eDiscordAccess->empty()) {
			throw new RedirectAction(Lime::getUrl().'?error=communication\\DiscordAccess::failed');
		}

		throw new RedirectAction(Lime::getUrl().'?success=communication\\DiscordAccess::connected');

	})
;
