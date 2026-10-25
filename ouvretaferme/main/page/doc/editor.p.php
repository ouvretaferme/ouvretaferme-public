<?php
(new Page(function($data) {

		$cMember = \structure\MemberLib::getOnline();

		$data->eStructure = $cMember->notEmpty() ? $cMember->first()['structure'] : new \structure\Structure();

	}))
	->get('index', fn($data) => throw new ViewAction($data));
?>
