<?php
namespace communication;

class WebinaireSubscriptionUi {

	public static function getAnnouncement(bool $isSubscribed = FALSE): string {

		if(
			\Clock::date() >= last(CommunicationSetting::WEBINAIRE_DATES)['date'] or
			CommunicationSetting::IS_WEBINAIRE_ACTIVATED === FALSE or
			(LIME_REQUEST_PATH !== '/' and str_contains(LIME_REQUEST_PATH, 'fonctionnalite') === FALSE) or
			(\user\ConnectionLib::getOnline()->notEmpty() and \user\ConnectionLib::getOnline()['roleProducer'] === FALSE) or
			$isSubscribed
		) {
			return '';
		}

		$h = '<div class="mb-2" style="padding: 1.5rem 0; background-color: var(--color-terracota); color: white; text-align: center;">';
			$h .= '<h4 style="margin-bottom: 0.5rem">'.s("Nos webinaires pour tout comprendre sur la facturation électronique").'</h4>';
			$h .= '<div>'.s("Jusqu'à fin août, participez à nos webinaires pour décrypter la réforme et comprendre ce qu'elle implique pour vous.").'</div>';
			$h .= '<div>'.s("Une heure par webinaire et de nombreuses dates disponibles !").'</div>';
			$h .= '<a href="/communication/webinaire:create" target="_blank" class="btn btn-primary" style="margin-top: 0.25rem">'.s("En savoir plus").'</a>';
	  $h .= '</div>';

		return $h;

	}

	public static function p(string $property): \PropertyDescriber {

		$d = WebinaireSubscription::model()->describer($property, [
			'firstName' => s("Prénom"),
			'lastName' => s("Nom"),
			'date' => s("Session choisie"),
			'email' => s("E-mail"),
		]);

		switch($property) {

			case 'date' :
				$d->field = 'select';
				$d->required = TRUE;
				break;

		}

		return $d;

	}

	public function displaySubscriptions(\Collection $cWebinaireSubscription): string {

		if($cWebinaireSubscription->empty()) {
			return '<div class="util-empty">'.s("Il n'y a pas d'inscription pour le moment").'</div>';
		}

		$h = '<div class="util-overflow-xs">';
			$h .= '<table class="util-overflow-xs">';
				$h .= '<thead>';
					$h .= '<tr>';
						foreach(CommunicationSetting::WEBINAIRE_DATES as $index => $webinaireDate) {
							$h .= '<th class="td-min-content text-center '.($webinaireDate['date'] <= \Clock::date() ? 'color-muted' : '').'">';
							$h .= $index.'<br />'.\util\DateUi::numeric($webinaireDate['date']).'<br />'.$webinaireDate['hour'];
							$h .= '</th>';
						}
						$h .= '<th class="td-min-content text-center td-vertical-align-middle">'.s("Total").'</th>';
					$h .= '</tr>';
				$h .= '</thead>';

				$h .= '<tbody>';
					$h .= '<tr>';
						foreach(CommunicationSetting::WEBINAIRE_DATES as $index => $webinaireDate) {
							$count = $cWebinaireSubscription->find(fn($e) => $e['date'] === $index)->count();
							$h .= '<td class="text-center '.($webinaireDate['date'] <= \Clock::date() ? 'color-muted' : '').'">'.$count.'</td>';
						}
						$h .= '<td class="text-center"><b>'.$cWebinaireSubscription->count().'</b></td>';
					$h .= '</tr>';
				$h .= '</tbody>';
			$h .= '</table>';
		$h .= '</div>';


		$h .= '<div class="util-overflow-xs stick-sm">';

			$h .= '<table class="tr-even tr-hover structure-admin-table tr-bordered">';
				$h .= '<thead class="thead-sticky">';
					$h .= '<tr>';
						$h .= '<th>'.s("Nom").'</th>';
						$h .= '<th>'.s("Email").'</th>';
						$h .= '<th>'.s("Session").'</th>';
						$h .= '<th>'.s("Inscrit·e le").'</th>';
					$h .= '</tr>';
				$h .= '</thead>';

				$h .= '<tbody>';

				foreach($cWebinaireSubscription as $eWebinaireSubscription) {

					$date = CommunicationSetting::WEBINAIRE_DATES[$eWebinaireSubscription['date']];

					$h .= '<tr '.(\Clock::date() >= $date['date'] ? ' class="color-muted"' : '').'>';

						$h .= '<td>'.encode($eWebinaireSubscription['firstName']).' '.encode($eWebinaireSubscription['lastName']).'</td>';
						$h .= '<td><a href="/user/admin/?email='.urlencode($eWebinaireSubscription['email']).'">'.encode($eWebinaireSubscription['email']).'</a></td>';
						$h .= '<td>';
							$h .= s("{date} à {hour}", ['date' => \util\DateUi::numeric($date['date']), 'hour' => $date['hour']]);
						$h .= '</td>';
						$h .= '<td>';
							$h .= \util\DateUi::numeric($eWebinaireSubscription['createdAt']);
						$h .= '</td>';

					$h .= '</tr>';


				}
				$h .= '</tbody>';
			$h .= '</table>';

		$h .= '</div>';

		return $h;
	}

}
