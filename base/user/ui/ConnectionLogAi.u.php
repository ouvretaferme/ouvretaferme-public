<?php
namespace user;

/**
 * L'écran « Connexions admin » de l'administration : les filtres et le tableau des connexions des
 * administrateurs.
 */
class ConnectionLogAiUi {

	public function getSearch(\Search $search, int $nLog, \Collection $cUserAdmin): string {

		$form = new \util\FormUi();

		$users = [];

		foreach($cUserAdmin as $eUser) {
			$users[$eUser['id']] = $eUser->getName();
		}

		$h = $form->openAjax('/user/admin/connectionAi', ['method' => 'get', 'class' => 'util-search']);
			$h .= '<fieldset>';
				$h .= $form->select('user', $users, $search->get('user'), ['placeholder' => s("Tous les administrateurs")]);
			$h .= '</fieldset>';
			$h .= '<fieldset>';
				$h .= $form->select('action', [
					ConnectionLogAiLib::FILTER_PASSWORD => s("Mot de passe"),
					ConnectionLogAiLib::FILTER_AUTO => s("Reconnexion automatique"),
					ConnectionLogAiLib::FILTER_UNKNOWN => s("Reconnexion depuis une adresse inconnue"),
					ConnectionLogAiLib::FILTER_EXTERNAL => s("En tant qu'un autre compte"),
				], $search->get('action'), ['placeholder' => s("Toutes les connexions")]);
			$h .= '</fieldset>';
			$h .= '<fieldset>';
				$h .= $form->text('ip', $search->get('ip'), ['placeholder' => s("Adresse")]);
			$h .= '</fieldset>';
			$h .= '<fieldset>';
				$h .= $form->date('from', $search->get('from'));
			$h .= '</fieldset>';
			$h .= '<fieldset>';
				$h .= $form->date('to', $search->get('to'));
			$h .= '</fieldset>';
			$h .= '<div class="util-search-submit">';
				$h .= $form->submit(s("Chercher"));
				if($search->notEmpty()) {
					$h .= ' <a href="/user/admin/connectionAi" class="btn btn-only-icon">'.\Asset::icon('x-lg').'</a>';
				}
				$h .= '<span class="badge badge-solid bg-primary">'.p("{value} connexion", "{value} connexions", $nLog).'</span>';
			$h .= '</div>';
		$h .= $form->close();

		return $h;

	}

	public function getCollection(\Collection $cLog): string {

		if($cLog->empty()) {
			return '<div class="util-empty">'.s("Aucune connexion à afficher.").'</div>';
		}

		$h = '<div class="util-overflow-xs">';
			$h .= '<table class="tr-even tr-hover">';
				$h .= '<thead>';
					$h .= '<tr>';
						$h .= '<th>'.s("Quand").'</th>';
						$h .= '<th>'.s("Administrateur").'</th>';
						$h .= '<th>'.s("Connexion").'</th>';
						$h .= '<th class="hide-sm-down">'.s("Appareil").'</th>';
						$h .= '<th>'.s("Adresse").'</th>';
					$h .= '</tr>';
				$h .= '</thead>';
				$h .= '<tbody>';

				foreach($cLog as $eLog) {

					$h .= '<tr>';
						$h .= '<td>'.\util\DateUi::numeric($eLog['createdAt']).'</td>';
						$h .= '<td>'.$this->getUser($eLog['eUserAdministrator']).'</td>';
						$h .= '<td>'.$this->getConnection($eLog).'</td>';
						$h .= '<td class="hide-sm-down">'.self::p('device')->values[$eLog['device']].'</td>';
						$h .= '<td>'.encode($eLog['ip']).'</td>';
					$h .= '</tr>';

				}

				$h .= '</tbody>';
			$h .= '</table>';
		$h .= '</div>';

		return $h;

	}

	protected function getUser(User $eUser): string {

		return '<a href="/user/admin/?id='.$eUser['id'].'">'.encode($eUser->getName()).'</a>';

	}

	protected function getConnection(Log $eLog): string {

		if($eLog['action'] === Log::LOGIN_EXTERNAL) {
			return s("En tant que {value}", $this->getUser($eLog['eUserBorrowed']));
		}

		$h = self::p('action')->values[$eLog['action']];

		if($eLog['unknownAddress']) {
			$h .= ' <span class="badge badge-warning">'.s("Adresse jamais vue en {value} jours", ConnectionLogAiLib::KNOWN_ADDRESS_IN_DAYS).'</span>';
		}

		return $h;

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Log::model()->describer($property, [
			'action' => s("Connexion"),
			'device' => s("Appareil"),
		]);

		switch($property) {

			case 'action' :
				$d->values = [
					Log::LOGIN => s("Mot de passe"),
					Log::LOGIN_AUTO => s("Reconnexion automatique"),
					Log::LOGIN_EXTERNAL => s("En tant qu'un autre compte"),
					Log::LOGOUT => s("Déconnexion"),
				];
				break;

			case 'device' :
				$d->values = [
					Log::WEB => s("Ordinateur"),
					Log::APP => s("Application"),
					Log::MOBILE_WEB => s("Mobile"),
					Log::TABLET_WEB => s("Tablette"),
					Log::CRAWLER => s("Robot"),
				];
				break;

		}

		return $d;

	}

}
?>
