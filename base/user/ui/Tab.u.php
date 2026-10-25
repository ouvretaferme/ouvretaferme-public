<?php
namespace user;

class TabUi {

	public function getList(\Collection $cUser, User $eUserTab): string {

		$h = '';

		if($eUserTab->notEmpty()) {

			$h .= '<div class="util-block-info">';
				$h .= '<p>'.s("Cet onglet est connecté au compte de {value}.", '<b>'.$eUserTab->getName().'</b>').'</p>';
				$h .= '<a data-ajax="/user/tab:doQuit" class="btn btn-outline-primary">'.s("Déconnecter cet onglet").'</a>';
			$h .= '</div>';

		}

		if($cUser->empty()) {

			$h .= '<div class="util-empty">';
				$h .= s("Le décor des tests n'est pas monté dans cette base. Il se monte par {command}.", ['command' => '<code>php framework/lime.php -a ouvretaferme -e dev structure/configure/fixtureAi write=1</code>']);
			$h .= '</div>';

			return $h;

		}

		$h .= '<div class="util-overflow-xs stick-sm">';

			$h .= '<table class="tr-even">';

				$h .= '<thead>';
					$h .= '<tr>';
						$h .= '<th>'.s("Compte").'</th>';
						$h .= '<th>'.s("Structures").'</th>';
						$h .= '<th></th>';
					$h .= '</tr>';
				$h .= '</thead>';

				$h .= '<tbody>';

					foreach($cUser as $eUser) {

						$h .= '<tr>';

							$h .= '<td>';
								$h .= $eUser->getName().'<br/>';
								$h .= '<small class="color-muted">'.encode($eUser['email']).'</small>';
							$h .= '</td>';

							$h .= '<td>';
								$h .= $this->getMembers($eUser['cMember']);
							$h .= '</td>';

							$h .= '<td class="text-end">';
								if($eUser->is($eUserTab)) {
									$h .= s("Dans cet onglet");
								} else {
									$h .= '<a data-ajax="/user/admin/agent:doLogin" post-user="'.$eUser['id'].'" class="btn btn-outline-primary">'.s("Utiliser dans cet onglet").'</a>';
								}
							$h .= '</td>';

						$h .= '</tr>';

					}

				$h .= '</tbody>';

			$h .= '</table>';

		$h .= '</div>';

		return $h;

	}

	protected function getMembers(\Collection $cMember): string {

		if($cMember->empty()) {
			return '<span class="color-muted">'.s("Aucune").'</span>';
		}

		$h = '';

		foreach($cMember as $eMember) {
			$h .= '<div>'.encode($eMember['structure']['name']).' <small class="color-muted">'.encode($eMember['role']['name']).'</small></div>';
		}

		return $h;

	}

	public function browser(string $token): string {

		$form = new \util\FormUi();

		$h = $form->openAjax('/user/tab:doBrowser');
			$h .= $form->hidden('token', $token);
			$h .= $form->submit(s("Reconnecter le navigateur au compte du poste"));
		$h .= $form->close();

		return $h;

	}

	public function badge(User $eUserOnline): string {

		// La pastille est sur chaque page : c'est le seul geste qui rend l'onglet au cookie du profil sans connaître l'adresse de /user/tab:doQuit
		$h = '<div class="tab-identity">';
			$h .= \Asset::icon('person-fill').' ';
			if($eUserOnline->empty()) {
				$h .= s("Onglet sans compte");
			} else {
				$h .= $eUserOnline->getName();
			}
			$h .= '<a data-ajax="/user/tab:doQuit">'.s("Déconnecter").'</a>';
		$h .= '</div>';

		return $h;

	}

}
?>
