<?php
new Page(function($data) {

	$cMember = \structure\MemberLib::getOnline();

	$data->eStructure = $cMember->notEmpty() ? \structure\StructureLib::getById($cMember->first()['structure']['id']) : new \structure\Structure();

})
	->get('/doc/import:series', fn($data) => throw new ViewAction($data));
?>
