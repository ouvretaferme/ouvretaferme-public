<?php
new AdaptativeView('/ferme/{id}/equipe', function($data, DynamicTemplate $t) {

	// Le titre du document est celui de la catégorie affichée, et non un mot écrit deux fois : un
	// groupe nomme cet écran « Membres ».
	$t->title = \structure\HeaderUi::getTeamCategories($data->eStructure)[\structure\Member::TEAM];

	if($t instanceof StructureTemplate) {
		$t->nav = 'team';
	}

	if($t instanceof AmapTemplate) {
		$t->nav = 'settings';
		$t->subNav = 'team';
	}

	$t->canonical = \structure\StructureUi::urlTeam($data->eStructure);

	$t->mainTitle = \structure\HeaderUi::getTeam($data->eStructure, \structure\Member::TEAM);
	$t->package('main')->updateNavTeam($t->canonical);

	echo \structure\HeaderUi::getTeamTabs($data->eStructure, \structure\Member::TEAM);

	if($data->eStructure->acceptProduction()) {
		echo new \hr\TeammateUi()->getManage($data->eStructure, $data->team, $data->year, $data->cMemberOutside, $data->cInvite, $data->cInviteFinished);
	} else {
		echo new \structure\MemberUi()->getManage($data->eStructure, $data->ccMember, $data->cInvite, $data->cInviteFinished, $data->producers);
	}

});

new AdaptativeView('show', function($data, DynamicTemplate $t) {

	$t->title = $data->eMember['user']->getName();

	if($t instanceof StructureTemplate) {
		$t->nav = 'team';
	}

	if($t instanceof AmapTemplate) {
		$t->nav = 'settings';
		$t->subNav = 'team';
	}

	$t->mainTitle = new \structure\MemberUi()->getUserTitle($data->eMember, $data->eStructure);

	echo new \structure\MemberUi()->getUser($data->eStructure, $data->eMember, $data->cPresence, $data->cAbsence);

});

new AdaptativeView('updatePrivate', function($data, PanelTemplate $t) {
	return new \structure\MemberUi()->updatePrivate($data->eStructure, $data->eUserOnline);
});

new AdaptativeView('update', function($data, PanelTemplate $t) {
	return new \structure\MemberUi()->update($data->e, $data->eStructureMember, $data->cSection);
});

new AdaptativeView('doUpdateWeatherModel', function($data, AjaxTemplate $t) {

	// Ouvert, le menu est déplacé dans `<body>` (`lime.js`) : remplacer le bloc sans le fermer l'y laisserait.
	$t->js()->closeDropdowns();

	// `getByStructure()` retombe sur ICON quand le modèle choisi ne couvre pas l'heure, donc `NULL` ne
	// dit qu'une chose : le cache a expiré entre l'affichage et le clic. La météo reste alors telle quelle
	// plutôt que de se vider sous le geste, et le choix est enregistré.
	if($data->weather !== NULL) {
		$t->qs('#'.\dashboard\DashboardUi::getBlockId('structure-weather').' > .structure-weather')
			->outerHtml(new \structure\DashboardUi()->render('structure-weather', $data->eStructure, $data->weather));
	}

});
?>
