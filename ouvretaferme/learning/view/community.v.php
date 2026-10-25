<?php
new AdaptativeView('/groupe/{id}/formations', function($data, CommunityTemplate $t) {

	$t->tab = 'learning';
	$t->title = s("Formations");

	if($data->cShare->notEmpty()) {

		echo new \learning\CatalogUi()->getCommunityMember($data->eStructure, $data->cShareShown, $data->types, $data->type);

		return;

	}

	// Le titre seul dans la bande, sans les gestes de la liste : créer une formation est déjà le
	// parcours juste dessous.
	if($data->eCourseStart->notEmpty()) {

		$ui = new \learning\CourseStartUi();

		echo $ui->getIntroduction($data->eStructure);
		echo $ui->getStart($data->eCourseStart, $data->step, FALSE);

		return;

	}

	$t->mainTitle = \learning\CourseUi::getCommunityTitle($data->eStructure, $data->commentsNew, $data->reviewsNew);
	$t->mainTabs = \learning\CourseUi::getCommunityTypeTabs($data->eStructure, $data->types, $data->type);

	echo new \learning\CourseUi()->getCommunityList($data->eStructure, $data->cCourse, $data->types, $data->type, $data->withRequested);

});

new AdaptativeView('/groupe/{id}/formations/integrer', function($data, CommunityTemplate $t) {

	$t->tab = 'learning';
	$t->title = s("Intégrer sur votre site");
	$t->mainTitle = new \learning\DateEnrollmentUi()->getIntegrationTitle($data->eStructure);

	echo new \learning\DateEnrollmentUi()->getIntegration($data->eStructure, $data->eCommunity, $data->withPublic);

});

new AdaptativeView('/groupe/{id}/formations/commentaires', function($data, CommunityTemplate $t) {

	$t->tab = 'learning';
	$t->title = s("Commentaires");
	$t->mainTitle = new \learning\CommentUi()->getInboxTitle($data->eStructure, $data->commentsNew);

	echo new \learning\CommentUi()->getInbox($data->eStructure, $data->cCommentNew, $data->cCommentRead, $data->older);

});

new AdaptativeView('/groupe/{id}/formations/avis', function($data, CommunityTemplate $t) {

	$t->tab = 'learning';
	$t->title = s("Avis");
	$t->mainTitle = new \learning\ReviewUi()->getInboxTitle($data->eStructure, $data->reviewsNew);

	echo new \learning\ReviewUi()->getInbox($data->eStructure, $data->cReviewNew, $data->cReviewRead, $data->older);

});

new AdaptativeView('/groupe/{id}/formations/encadrants', function($data, CommunityTemplate $t) {

	$t->tab = 'learning';
	$t->title = s("Encadrants");
	$t->mainTitle = new \learning\SupervisorUi()->getCommunityTitle($data->eStructure, $data->cSupervisor);

	echo new \learning\SupervisorUi()->getList($data->cSupervisor);

});

new AdaptativeView('updateEnrollment', function($data, PanelTemplate $t) {

	return new \learning\DateEnrollmentUi()->updateSettings($data->eStructure, $data->eCommunity);

});
?>
