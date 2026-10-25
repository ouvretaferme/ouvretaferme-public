<?php
(new Page(function($data) {

		$cMember = \structure\MemberLib::getOnline();

		$data->eStructure = $cMember->notEmpty() ? $cMember->first()['structure'] : new \structure\Structure();

	}))
	->get('/doc/', fn($data) => throw new ViewAction($data))
	->get('design', fn($data) => throw new ViewAction($data))
	->get('help', fn($data) => throw new ViewAction($data));
?>
