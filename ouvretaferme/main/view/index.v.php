<?php
new AdaptativeView('/espace-client', function($data, BaseTemplate $t) {

	$t->title = s("Espace client");

	echo new \main\HomeUi()->getCustomerSpace($data->customerSpace);
	echo new \main\HomeUi()->getCustomerSpaceEmpty($data->customerSpace);

});

new AdaptativeView('logged', function($data, BaseTemplate $t) {

	$t->title = s("Bienvenue sur {siteName}");
	$t->canonical = '/';

	$t->header = \structure\SurveyUi::getAnnouncement($data->hasSurveyAnnouncement);

	$t->header .= '<h1 class="text-start">'.s("Bienvenue, {userName}&nbsp;!", ['userName' => encode($data->eUserOnline['firstName'] ?? $data->eUserOnline['lastName'])]).'</h1>';

	if(\learning\LearningSetting::$active) {
		$t->header .= new \main\HomeUi()->getLearning();
	}

	echo new \main\HomeUi()->getInvites($data->cInvite, $data->customerSpace);

	if($data->hasHomeStructures) {
		echo new \main\HomeUi()->getStructures($data->eUserOnline, $data->ccStructureHome, $data->eStructureCreate);
	}

	if($data->eUserOnline->isProducer()) {

		if(FEATURE_GAME) {
			echo new \game\HelpUi()->getHome($data->ePlayer);
		}

		if($data->cCourseTraining->notEmpty()) {
			echo new \main\HomeUi()->getTrainingCourses($data->eStructureTraining['community'], $data->cCourseTraining);
		}

		if($data->ccStructureUser->notEmpty()) {

			if($data->hasNewsletterForm) {
				echo new \main\HomeUi()->getNewsletter($data->eUserOnline);
			}

			echo new \main\HomeUi()->getBlog($data->cNews);

		}

	}

	if($data->eUserOnline->isCustomer()) {

		echo '<div class="util-info">'.s("Vous êtes connecté sur l'espace client qui vous relie à tous les producteurs auxquels vous avez l'habitude de commander sur {value}.", '<a href="'.Lime::getUrl().'">'.s("{siteName}").'</a>').'</div>';

		if(FEATURE_GAME) {
			echo new \game\HelpUi()->getHome($data->ePlayer);
		}

	}

	echo new \main\HomeUi()->getCustomerSpace($data->customerSpace);

});

new AdaptativeView('/presentation/invitation', function($data, BaseTemplate $t) {

	$t->title = s("Invitation expirée");

	echo '<h2>'.s("Cette invitation a expiré, veuillez vous rapprocher de votre interlocuteur habituel pour en obtenir une nouvelle !").'</h2>';

});
?>
