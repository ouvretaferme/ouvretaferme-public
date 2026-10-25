<?php

namespace user;

/**
 * Banishment display.
 */
class BanUi {

	public function __construct() {

		\Asset::css('user', 'user.css');

	}

	public function create(User $eUser, $userToBanIp, $nUserOnIp): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/user/admin/ban:do');

		$type = $eUser->notEmpty() ? $form->checkbox('type[]', 'user', ['callbackLabel' => fn($input) => $input.' '.s("Utilisateur")]) : '';

		if($userToBanIp === NULL) {

			$type .= '<p class="util-warning">'.
				s("Note : l'adresse IP de cet utilisateur n'a pas pu être déterminée. Il n'est donc pas possible d'ajouter un bannissement par IP.").
				'</p>';

		} elseif($nUserOnIp < UserSetting::MAX_BAN_ON_SAME_IP) {

			$type .= $form->checkbox('type[]', 'ip', ['callbackLabel' => fn($input) => $input.' '.s("Adresse IP").' '.p("({value} personne connectée)", "({value} personnes connectées)", $nUserOnIp)]);

	} else {

			$type .= '<p class="util-warning">'.p("Note : le bannissement par adresse IP n'est pas possible quand plus de {value} personne est connectée sur cette même adresse.",
					"Note : le bannissement par adresse IP n'est pas possible quand plus de {value} personnes sont connectées sur cette même adresse.",
					UserSetting::MAX_BAN_ON_SAME_IP
				).'</p>';

		}

		$h .= $form->group(
			s("Étendue du bannissement"),
			$type
		);

		$h .= $form->group(
			s("Motif"),
			$form->textarea('reason', NULL, ['placeholder' => s("Indiquez le motif du bannissement.
Attention : ce texte sera affiché à l'utilisateur.")])
		);

		if($eUser->notEmpty()) {
			$h .= $form->hidden('user', $eUser);
		}

		if($userToBanIp !== NULL) {
			$h .= $form->hidden('ip', $userToBanIp);
		}

		$h .= $this->getDurationSelect($form);

		$h .= $form->submit(\Asset::icon('slash-circle-fill').' '.s("Bannir"), ['class' => 'btn btn-danger']);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-user-ban',
			title: $eUser->notEmpty() ? s("Bannir {email}", ['email' => encode($eUser['email'])]) : s("Bannir l'adresse {ip}", ['ip' => encode($userToBanIp)]),
			body: $h
		);

	}

	public function getSearch(string $status, User $eUserSelected, int $nBan): string {

		$form = new \util\FormUi();

		$h = $form->openAjax('/user/admin/ban', ['method' => 'get', 'class' => 'util-search']);
			$h .= '<fieldset>';
				$h .= $form->select('type', [
					BanLib::ACTIVE => s("En cours"),
					BanLib::ENDED => s("Terminés"),
				], $status, ['mandatory' => TRUE]);
			$h .= '</fieldset>';
			if($eUserSelected->notEmpty()) {
				$h .= $form->hidden('user', $eUserSelected);
			}
			$h .= '<div class="util-search-submit">';
				$h .= $form->submit(s("Chercher"));
				if($eUserSelected->notEmpty()) {
					$h .= ' <a href="/user/admin/ban?type='.$status.'" class="btn btn-outline-primary">'.encode($eUserSelected['email'] ?? $eUserSelected->getName()).' '.\Asset::icon('x-lg').'</a>';
				}
				$h .= '<span class="badge badge-solid bg-primary">'.p("{value} bannissement", "{value} bannissements", $nBan).'</span>';
			$h .= '</div>';
		$h .= $form->close();

		return $h;

	}

	public function getCollection(string $status, \Collection $cBan): string {

		if($cBan->empty()) {
			return '<div class="util-empty">'.match($status) {
				BanLib::ACTIVE => s("Aucun bannissement en cours."),
				BanLib::ENDED => s("Aucun bannissement terminé."),
			}.'</div>';
		}

		$h = '<div class="util-overflow-sm">';
			$h .= '<table class="tr-even tr-hover">';
				$h .= '<thead>';
					$h .= '<tr>';
						$h .= '<th>'.s("Utilisateur ou adresse").'</th>';
						$h .= '<th>'.s("Depuis le").'</th>';
						$h .= '<th>'.s("Jusqu'au").'</th>';
						$h .= '<th>'.s("Motif").'</th>';
						$h .= '<th class="hide-sm-down">'.s("Par").'</th>';
						if($status === BanLib::ACTIVE) {
							$h .= '<th></th>';
						}
					$h .= '</tr>';
				$h .= '</thead>';
				$h .= '<tbody>';

				foreach($cBan as $eBan) {

					$h .= '<tr>';
						$h .= '<td>'.$this->getTarget($eBan).'</td>';
						$h .= '<td>'.\util\DateUi::numeric($eBan['since'], \util\DateUi::DATE_HOUR_MINUTE).'</td>';
						$h .= '<td>'.($eBan['until'] === NULL ? s("Indéfiniment") : \util\DateUi::numeric($eBan['until'], \util\DateUi::DATE_HOUR_MINUTE)).'</td>';
						$h .= '<td>'.encode($eBan['reason']).'</td>';
						$h .= '<td class="hide-sm-down">'.$eBan['admin']->getName().'</td>';
						if($status === BanLib::ACTIVE) {
							$h .= '<td class="text-end">'.$this->getActions($eBan).'</td>';
						}
					$h .= '</tr>';

				}

				$h .= '</tbody>';
			$h .= '</table>';
		$h .= '</div>';

		return $h;

	}

	protected function getTarget(Ban $eBan): string {

		$h = '';

		if($eBan['user']->notEmpty()) {
			$h .= '<a href="/user/admin/?id='.$eBan['user']['id'].'">'.encode($eBan['user']['email']).'</a>';
		}

		if($eBan['ip'] !== NULL) {
			$h .= '<div class="font-sm color-muted">'.encode($eBan['ip']).'</div>';
		}

		return $h;

	}

	protected function getActions(Ban $eBan): string {

		$h = '<a class="dropdown-toggle btn btn-color-primary" data-dropdown="bottom-end">'.\Asset::icon('gear-fill').'</a>';
		$h .= '<div class="dropdown-list">';
			$h .= '<a href="/user/admin/ban:updateEndDate?id='.$eBan['id'].'" class="dropdown-item">'.s("Changer la date de fin").'</a>';
			$h .= '<a data-ajax="/user/admin/ban:doEnd" post-id="'.$eBan['id'].'" class="dropdown-item" data-confirm="'.s("Mettre fin à ce bannissement ?").'">'.s("Mettre fin").'</a>';
		$h .= '</div>';

		return $h;

	}

	public function updateEndDate(Ban $eBan): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/user/admin/ban:doUpdateEndDate');
			$h .= $this->getDurationSelect($form);
			$h .= $form->hidden('id', $eBan['id']);
			$h .= $form->submit(s("Enregistrer"));
		$h .= $form->close();

		return new \Panel(
			id: 'panel-user-ban-duration',
			title: s("Changer la fin du bannissement"),
			body: $h
		);

	}

	/**
	 * Display the ban duration select box.
	 *
	 * @param \util\FormUi $form
	 * @return string
	 */
	public function getDurationSelect(\util\FormUi $form): string {

		$list = [
			1 => $this->getDurationLabel(1, 'day'),
			3 => $this->getDurationLabel(3, 'day'),
			7 => $this->getDurationLabel(1, 'week'),
			14 => $this->getDurationLabel(2, 'week'),
			30 => $this->getDurationLabel(1, 'month'),
			90 => $this->getDurationLabel(3, 'month'),
			-1 => s("Indéfiniment")
		];

		$h = $form->group(
			s("Durée"),
			$form->select('duration', $list, NULL, ['required' => TRUE])
		);

		return $h;
	}

	public function getConnectionBanned(Ban $eBan): string {

		// Specific return for login throught networks
		if($eBan->empty()) {
			return s("Impossible de se connecter, car vous êtes banni");
		}

		// Limitless bannishement
		if($eBan['until'] === NULL) {
			return s(
				"Impossible de se connecter, car vous êtes banni pour la raison suivante : {reason}",
				['reason' => encode($eBan['reason'])]
			);
			// Regular bannishement
		} else {
			return s(
				"Impossible de se connecter, car vous êtes banni jusqu'au {date} pour la raison suivante : {reason}",
				['date' => \util\DateUi::textual($eBan['until'], \util\DateUi::DATE), 'reason' => encode($eBan['reason'])]
			);
		}

	}

	public function getSignUpBanned(Ban $eBan): string {

		return s(
			"Impossible de créer un compte, car votre adresse IP est bannie jusqu'au {date} pour la raison suivante : {reason}",
			['date' => \util\DateUi::textual($eBan['until'], \util\DateUi::DATE), 'reason' => encode($eBan['reason'])]
		);

	}

	/**
	 * Get a label for a time duration.
	 *
	 * @param int $number
	 * @param string $type
	 * @return string
	 */
	protected function getDurationLabel(int $number, string $type): string {

		switch($type) {

			case 'day' :
				return p('{value} jour', '{value} jours', $number);

			case 'week' :
				return p('{value} semaine', '{value} semaines', $number);

			case 'month' :
				return p('{value} mois', '{value} mois', $number);

		}

	}

}
?>
