<?php
new AdaptativeView('index', function($data, BaseTemplate $t) {

	\Asset::css('user', 'user.css');

	$t->title = s("Mon compte");

	$h = '<div class="user-account-header">';
		// L'image d'un avatar qui remplace la photo se refait dans l'éditeur, jamais par l'envoi d'une photo
		if($data->eUserOnline['eUserAvatar']->isReplacingPhoto()) {
			$h .= \user\UserUi::getVignette($data->eUserOnline, '5rem');
		} else {
			$h .= new \media\UserVignetteUi()->getCamera($data->eUserOnline, size: '5rem');
		}
		$h .= '<h1>'.$t->title.'</h1>';
	$h .= '</div>';

	$t->header = $h;

	$h = '<div class="util-cards util-cards-2 mb-2">';

		if($data->eUserOnline['eUserAvatar']->isReplacingPhoto()) {
			$h .= \main\DesignUi::getDeckEntry('/user/settings:updateUser', 'person-fill', 'settings', s("Modifier mes informations personnelles"), s("Votre nom et votre prénom"));
		} else {
			$h .= \main\DesignUi::getDeckEntry('/user/settings:updateUser', 'person-fill', 'settings', s("Modifier mes informations personnelles"), s("Votre nom, votre prénom et votre photo"));
		}

		if($data->canUpdate['email']) {
			$h .= \main\DesignUi::getDeckEntry('/user/settings:updateEmail', 'envelope-fill', 'settings', s("Changer mon adresse e-mail"), encode($data->eUserOnline['email']));
		}

		if($data->hasCustomer) {
			$h .= \main\DesignUi::getDeckEntry('/mail/contact:updateOptIn', 'envelope-paper-fill', 'settings', s("Gérer mes préférences de communication par e-mail"), s("Les messages que les producteurs chez qui vous commandez peuvent vous envoyer"));
		}

		if(OTF_DEMO === FALSE and $data->canUpdate['password']) {

			if($data->canUpdate['hasPassword']) {
				$title = s("Changer mon mot de passe");
			} else {
				$title = s("Créer un mot de passe");
			}

			$h .= \main\DesignUi::getDeckEntry('/user/settings:updatePassword', 'lock-fill', 'settings', $title);

		}

		// La suppression reste une entrée de la liste, en dernier : c'est un réglage du compte comme
		// les autres, et seul son intitulé porte la couleur du danger.
		if(OTF_DEMO === FALSE and $data->canUpdate['drop']) {

			if($data->userDeletedAt) {
				$title = s("Annuler la suppression de mon compte");
			} else {
				$title = s("Supprimer mon compte");
			}

			$h .= \main\DesignUi::getDeckEntry('/user/settings:dropAccount', 'trash', 'settings', '<span class="color-danger">'.$title.'</span>');

		}

	$h .= '</div>';

	$h .= '<h2>'.s("Vous avez un code d'invitation ?").'</h2>';
	$h .= new \structure\InviteUi()->join();
	$h .= '<br/><br/>';

	$h .= '<h2>'.s("Qui suis-je ?").'</h2>';

	$h .= '<p class="util-info">';
		$h .= s("Choisissez qui vous êtes sur Ouvretaferme afin d'utiliser les fonctionnalités qui vous correspondent.");
	$h .= '</p>';

	$h .= '<table class="table-unstyled tr-bordered mb-2">';
		$h .= '<tbody>';
			$h .= '<tr>';
				$h .= '<td>'.s("Client").'</td>';
				$h .= '<td><small><i>'.s("Je suis client d'un producteur").'</i></small></td>';
				$h .= '<td>';
					if($data->eUserOnline->isCustomer()) {
						$h .= '<b>'.s("Oui").'</b>';
					} else {
						$h .= '<b>'.s("Non").'</b>';
					}
				$h .= '</td>';
			$h .= '</tr>';
			$h .= '<tr>';
				$h .= '<td>'.s("Producteur").'</td>';
				$h .= '<td><small><i>'.s("Je suis producteur").'</i></small></td>';
				$h .= '<td>'.new \user\UserUi()->toggleRole($data->eUserOnline, 'roleProducer').'</td>';
			$h .= '</tr>';
			$h .= '<tr>';
				$h .= '<td>'.s("Conseiller").'</td>';
				$h .= '<td><small><i>'.s("J'accompagne des producteurs").'</i></small></td>';
				$h .= '<td>'.new \user\UserUi()->toggleRole($data->eUserOnline, 'roleConsultant').'</td>';
			$h .= '</tr>';
			$h .= '<tr>';
				$h .= '<td>'.s("Formateur").'</td>';
				$h .= '<td><small><i>'.s("Je forme des futurs producteurs").'</i></small></td>';
				$h .= '<td>'.new \user\UserUi()->toggleRole($data->eUserOnline, 'roleTeacher').'</td>';
			$h .= '</tr>';
		$h .= '</tbody>';
	$h .= '</table>';

	if($data->canCreateAmap) {

		$h .= '<h3>'.s("Coin des clients").'</h3>';

		$h .= '<div class="util-buttons">';

			$h .= '<a href="/demarrer/amap" class="util-button">';

				$h .= '<div>';
					$h .= '<h4>'.s("Créer une AMAP").'</h4>';
					$h .= '<div class="util-button-text">'.s("Pour gérer mon AMAP avec {siteName}").'</div>';
				$h .= '</div>';
				$h .= \Asset::icon('shop-window');

			$h .= '</a>';

		$h .= '</div>';

	}

	// Le réseau s'offre dans un seul coin, celui des conseillers, et à défaut celui des formateurs : un
	// compte peut tenir les deux rôles, et un producteur formateur ouvre aussi le premier coin.
	$network = '';

	if(
		FEATURE_INRAE and
		$data->eUserOnline->acceptCreateCommunityNetwork()
	) {

		$network .= '<a href="/demarrer/reseau" class="util-button">';

			$network .= '<div>';
				$network .= '<h4>'.s("Créer un réseau d'accompagnement").'</h4>';
				$network .= '<div class="util-button-text">'.s("Pour suivre des fermes en groupes de travail, avec des conseillers").'</div>';
			$network .= '</div>';
			$network .= \Asset::icon('people-fill');

		$network .= '</a>';

	}

	if(
		$data->eUserOnline->acceptCreateProducer() or
		$data->eUserOnline->isConsultant()
	) {

		$h .= '<h3>'.s("Coin des producteurs et des conseillers").'</h3>';

		$h .= '<div class="util-buttons">';

			if($data->eUserOnline->acceptCreateProducer()) {

				$h .= '<a href="/demarrer/ferme" class="util-button">';

					$h .= '<div>';
						$h .= '<h4>'.s("Créer une ferme").'</h4>';
						$h .= '<div class="util-button-text">'.s("Pour démarrer une nouvelle activité ou tester le logiciel").'</div>';
					$h .= '</div>';
					$h .= \Asset::icon('house-door-fill');

				$h .= '</a>';

			}

			if($data->eUserOnline->acceptCreateShop()) {

				$h .= '<a href="/demarrer/groupement" class="util-button">';

					$h .= '<div>';
						$h .= '<h4>'.s("Créer un groupement de producteurs").'</h4>';
						$h .= '<div class="util-button-text">'.s("Pour vendre en ligne avec un collectif de producteurs").'</div>';
					$h .= '</div>';
					$h .= \Asset::icon('cart4');

				$h .= '</a>';

			}

			if($data->eUserOnline->acceptCreateCommunityProducer()) {

				if(FEATURE_INRAE) {

					$h .= '<a href="/demarrer/communaute" class="util-button">';

						$h .= '<div>';
							$h .= '<h4>'.s("Créer une communauté de producteurs").'</h4>';
							$h .= '<div class="util-button-text">'.s("Pour partager des données de production entre les fermes").'</div>';
						$h .= '</div>';
						$h .= \Asset::icon('people-fill');

					$h .= '</a>';

				}
			}

			if($data->eUserOnline->isConsultant()) {
				$h .= $network;
			}

		$h .= '</div>';

	}

	if($data->eUserOnline->acceptCreateCommunityEducation()) {

		if(FEATURE_INRAE) {

			$h .= '<h3>'.s("Coin des formateurs").'</h3>';

			$h .= '<div class="util-buttons">';

				$h .= '<a href="/demarrer/education" class="util-button">';

					$h .= '<div>';
						$h .= '<h4>'.s("Créer un groupe de formation").'</h4>';
						$h .= '<div class="util-button-text">'.s("Pour utiliser {siteName} avec vos étudiants").'</div>';
					$h .= '</div>';
					$h .= \Asset::icon('people-fill');

				$h .= '</a>';

				if($data->eUserOnline->isConsultant() === FALSE) {
					$h .= $network;
				}

			$h .= '</div>';

		}

	}

	$h .= '<h2>'.s("Affichage").'</h2>';
	$h .= new \user\UserUi()->getNav($data->eUserOnline);

	echo $h;

});
?>
