<?php

new AdaptativeView('create', function($data, BaseTemplate $t) {

	$t->title = s("{siteName} - Webinaires");
	$t->metaDescription = s("Décryptez la réforme de la facturation électronique avec {siteName} !");

	if(\Clock::date() < last(\communication\CommunicationSetting::WEBINAIRE_DATES)['date'] and \communication\CommunicationSetting::IS_WEBINAIRE_ACTIVATED) {

		$t->header .= '<h1>'.s("Décryptez la réforme de la facturation électronique avec les webinaires organisés par {siteName} !").'</h1>';

		echo '<div style="font-size: 1.2rem;">';

			echo '<h2 class="mt-2">'.s("Contenu").'</h2>';
			echo '<p>';
				echo s("Les webinaires se découpent en trois parties : ");
			echo '</p>';
			echo '<ol>';
				echo '<li>'.s("<b>Présentation de la réforme</b> : le calendrier, les impacts pour vous").'</li>';
				echo '<li>'.s("Comment aborder la <b>facturation électronique avec {siteName}</b> avec quelques démonstrations").'</li>';
				echo '<li>'.s("Un temps d'échange où vous pouvez <b>poser toutes vos questions</b>").'</li>';
			echo '</ol>';

			echo '<h2 class="mt-2">'.s("En pratique ?").'</h2>';

			echo '<p>';
				echo s("Les webinaires dureront <b>1 heure</b> : une vingtaine de minutes pour la présentation et les démonstrations, et le reste sera consacré aux questions et réponses. Vous pouvez quitter le webinaire après avoir vu la présentation et les démonstrations si votre temps est compté.");
			echo '</p>';

			echo '<p>';
				echo s("Chaque webinaire est limité à <b>{value} participant·e·s</b> et les inscriptions sont ouvertes jusqu'à la veille à 18h.", \communication\CommunicationSetting::WEBINAIRE_MAX_SUBSCRIPTIONS);
			echo '</p>';

			echo '<h2 class="mt-2">'.s("Quelles dates ?").'</h2>';
			echo '<p>'.s("<b>{number} sessions</b> vous sont proposées à des horaires différents :", ['number' => count(\communication\CommunicationSetting::WEBINAIRE_DATES)]).'</p>';
			echo '<ul>';
				foreach(\communication\CommunicationSetting::WEBINAIRE_DATES as $index => $dateData) {

					$date = $dateData['date'];
					$hour = $dateData['hour'];

					$canSubscribe = \Clock::datetime() <= date('Y-m-d 18:00:00', strtotime($date.' - 1 day'));

					$left = \communication\CommunicationSetting::WEBINAIRE_MAX_SUBSCRIPTIONS - ($data->cWebinaireSubscriptionCount[$index]['count'] ?? 0);

					echo '<li';
						if(\Clock::date() >= $date) {
							echo ' class="color-muted" style="text-decoration: line-through;"';
						}
					echo '>';

						echo s("{date} à {hour}", ['date' => \util\DateUi::numeric($date), 'hour' => $hour]);

						if(\Clock::date() < $date) {

							if($canSubscribe === FALSE) {

								echo ' - <span class="util-annotation">'.s("Les inscriptions sont terminées !").'</span>';

							} else if($left === 0) {

								echo ' - <span class="util-annotation">'.s("Ce webinaire est complet !").'</span>';

							} else {

								echo ' <span class="util-annotation">('.p("{value} place restante", "{value} places restantes", $left).')</span>';

							}
						}
					echo '</li>';
				}
			echo '</ul>';

			echo '<h2 class="mt-2">'.s("Je suis intéressé·e, je m'inscris !").'</h2>';

			if($data->eWebinaireSubscription->notEmpty()) {

				$date = \communication\CommunicationSetting::WEBINAIRE_DATES[$data->eWebinaireSubscription['date']];

				if(isset($date['url'])) {
					$buttonWebinaire = '<a href="'.$date['url'].'" class="btn btn-outline-primary" target="_blank">'.Asset::icon('camera-video').' '.s("Aller au webinaire").'</a> ';
				} else {
					$buttonWebinaire = '';
				}

				echo '<div class="util-block-success">';

					$endDate = date('Y-m-d H:i:s', strtotime($date['date'].' '.str_replace('h', ':', $date['hour']).':00'.' + 1 hour'));
					$beforeDate = date('Y-m-d H:i:s', strtotime($date['date'].' 18:00:00'.' - 1 day'));
					if(\Clock::datetime() >= $endDate) {

						echo s("Nous espérons que la session du <b>{date}</b> a répondu à toutes vos attentes. Merci pour votre participation !", ['date' => \util\DateUi::numeric($date['date'])]);

					} else if(\Clock::datetime() >= $beforeDate) {

						echo s("Vous êtes bien inscrit·e à la session du <b>{date}</b> ! Vous avez du recevoir le lien du webinaire par e-mail ou le recevrez très prochainement.", [
							'date' => s("{date} à {hour}", ['date' => \util\DateUi::numeric($date['date']), 'hour' => $date['hour']]),
						]);

					} else {

						echo s("Vous êtes bien inscrit·e à la session du <b>{date}</b> !<br />Vous recevrez le lien du webinaire sur lequel vous connecter par e-mail le {dateEmail} après 18h.", [
							'date' => s("{date} à {hour}", ['date' => \util\DateUi::numeric($date['date']), 'hour' => $date['hour']]),
							'dateEmail' => \util\DateUi::numeric(date('Y-m-d', strtotime($date['date'].' - 1 day'))),
						]);

					}


				echo '</div>';

				echo $buttonWebinaire;

				if($data->eWebinaireSubscription->acceptDelete()) {
					echo '<a data-ajax="/communication/webinaire:doDelete" post-id="'.$data->eWebinaireSubscription['id'].'" data-confirm="'.s("Voulez-vous réellement annuler votre inscription ?").'" class="btn btn-outline-danger">';
						echo s("Annuler mon inscription");
					echo '</a>';
				}

			} else {

			echo '<ol>';
				echo '<li>'.s("Réservez votre place en remplissant le formulaire ci-dessous").'</li>';
				echo '<li>'.s("Attendez l'email avec le lien du webinaire que vous recevrez la veille du webinaire").'</li>';
				echo '<li>'.s("Le jour J, rendez-vous sur le lien reçu par email !").'</li>';
			echo '</ol>';

				$form = new \util\FormUi();
				echo $form->openAjax('/communication/webinaire:doCreate');

				if($data->eUserOnline->empty()) {

					echo '<div class="util-info">';
						echo s("<link>Connectez-vous</link> pour pré-remplir automatiquement le formulaire", ['link' => '<a href="/user/log:form?redirect='.urlencode(LIME_REQUEST).'">']);
					echo '</div>';

				}


				echo $form->dynamicGroups($data->e, ['firstName', 'lastName', 'email'], ['firstName' => function($d) use($data) {

					if($data->eUserOnline->notEmpty()) {
						$d->default = $data->eUserOnline['firstName'];
					}
				}, 'lastName' => function($d) use($data) {

					if($data->eUserOnline->notEmpty()) {
						$d->default = $data->eUserOnline['lastName'];
					}
				}, 'email' => function($d) use($data) {

					if($data->eUserOnline->notEmpty()) {
						$d->default = $data->eUserOnline['email'];
					}
				},
				]);

				$cWebinaireSubscriptionCount = $data->cWebinaireSubscriptionCount;

				echo $form->dynamicGroup($data->e, 'date', function($d) use($cWebinaireSubscriptionCount) {

					$d->values = array_filter(array_map(function($date, $index) use($cWebinaireSubscriptionCount) {

						$left = \communication\CommunicationSetting::WEBINAIRE_MAX_SUBSCRIPTIONS - ($cWebinaireSubscriptionCount[(int)$index]['count'] ?? 0);
						$canSubscribe = \Clock::datetime() <= date('Y-m-d 18:00:00', strtotime($date['date'].' - 1 day'));

						if($canSubscribe and $left > 0) {

					    return s("{date} à {hour}", ['date' => \util\DateUi::numeric($date['date']), 'hour' => $date['hour']]);

						} else {

							return NULL;

						}

					}, \communication\CommunicationSetting::WEBINAIRE_DATES, array_keys(\communication\CommunicationSetting::WEBINAIRE_DATES)));
				});

				echo $form->submit(s("Réserver ma place"));
				echo $form->close();

			}


		echo '</div>';

	} else {

		$t->header .= '<h1>'.s("Dates de webinaires").'</h1>';

		echo s("Il n'y a plus de webinaire disponible.");
	}

	echo '<br/>';
	echo '<br/>';

});
