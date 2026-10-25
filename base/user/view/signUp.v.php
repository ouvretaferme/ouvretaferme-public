<?php
new AdaptativeView('signUp', function($data, BaseTemplate $t) {

	\Asset::css('main', 'home.css');

	$t->title = s("Inscription sur {siteName}");
	$t->metaDescription = s("Inscrivez-vous comme producteur ou productrice sur {siteName} pour profiter de fonctionnalités de vente en ligne et de production du logiciel !");

	$t->template .= ' presentation';

	$t->header = '<div class="home-user-already">';
		$t->header .= s("Vous êtes déjà inscrit·e sur {siteName} ?").' &nbsp;&nbsp;';
		$t->header .= '<a href="/user/log:form" class="btn btn-outline-primary">'.s("Connectez-vous !").'</a>';
	$t->header .= '</div>';

	$t->header .= '<h1 class="text-center">'.s("Démarrer sur {siteName}").'</h1>';
	$t->header .= '<div class="home-user-types-container">';
		$t->header .= '<div class="home-user-types">';
			if($data->chooseRole) {
				$t->header .= new \main\HomeUi()->getCustomer($data->eUser, $data->eInvite);
				$t->header .= new \main\HomeUi()->getProducer($data->eUser, $data->eInvite);
			} else {

				if($data->eUser['roleCustomer']) {
					$t->header .= new \main\HomeUi()->getCustomer($data->eUser, $data->eInvite);
				} else if($data->eUser['roleProducer']) {
					$t->header .= new \main\HomeUi()->getProducer($data->eUser, $data->eInvite);
				} else if($data->eUser['roleConsultant']) {
					$t->header .= new \main\HomeUi()->getConsultant($data->eUser, $data->eInvite);
				} else if($data->eUser['roleTeacher']) {
					$t->header .= new \main\HomeUi()->getTeacher($data->eUser, $data->eInvite);
				}

			}
		$t->header .= '</div>';
	$t->header .= '</div>';

	if(
		$data->eUser['roleCustomer'] or
		$data->eUser['roleProducer'] or
		$data->eUser['roleConsultant'] or
		$data->eUser['roleTeacher']
	) {

		$start = new \user\UserUi()->getSignUpStart($data->eUser);

		if($start !== '') {

			echo $start;

			echo '<br/>';

		}

		echo '<h2 class="text-center mb-2">'.s("Mes informations").'</h2>';

		echo new \user\UserUi()->signUp($data->__page['name'], $data->eUser, $data->eInvite, REQUEST('redirect'));

	} else {

		if(FEATURE_INRAE or \learning\LearningSetting::$active) {

			echo '<div class="text-center">';
				echo '<h3>'.s("Autre").'</h3>';
				if(FEATURE_INRAE) {
					echo '<div>';
						echo '<a href="/inscription/formation'.new \main\HomeUi()->getInviteKey($data->eInvite).'">'.s("Je suis un organisme de formation").'</a>';
					echo '</div>';
					echo '<div>';
						echo '<a href="/inscription/accompagnement'.new \main\HomeUi()->getInviteKey($data->eInvite).'">'.s("Je suis une structure qui accompagne les producteurs").'</a>';
					echo '</div>';
				}
				if(\learning\LearningSetting::$active) {
					echo '<div>';
						echo '<a href="/formations/compte">'.s("Je veux seulement me former").'</a>';
					echo '</div>';
				}
			echo '</div>';

		}

	}


});

new JsonView('getCustomerFields', function($data, AjaxTemplate $t) {
	$t->qs('#user-signup-fields')->innerHtml(new \user\UserUi()->getSignUpFields($data->eUser, $data->eInvite));
});
?>
