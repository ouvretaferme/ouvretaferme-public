<?php
new AdaptativeView('createMember', function($data, PanelTemplate $t) {
	return new \structure\InviteUi()->createMember($data->e);
});

new AdaptativeView('updateRole', function($data, PanelTemplate $t) {
	return new \structure\InviteUi()->updateRole($data->e);
});

new AdaptativeView('/invitation', function($data, BaseTemplate $t) {

	$t->title = s("Accepter une invitation");
	$t->header = '';
	$t->metaNoindex = TRUE;

	echo '<div class="mt-2">'.new \structure\InviteUi()->check($data->eInvite).'</div>';

});

new AdaptativeView('join', function($data, BaseTemplate $t) {

	$t->title = s("J'ai un code d'invitation");
	$t->metaNoindex = TRUE;

	echo new \structure\InviteUi()->join();

});

new AdaptativeView('welcome', function($data, BaseTemplate $t) {

	$t->title = s("Invitation acceptée !");
	$t->header = '';
	$t->metaNoindex = TRUE;

	echo '<div class="mt-2">'.new \structure\InviteUi()->accept($data->eInvite, $data->eStructureProducer).'</div>';

});
?>
