<?php
namespace game;

class FriendUi {

	public function display(\Collection $cPlayer, Player $ePlayerOnline): string {

		$h = '<p>'.s("Un joueur pourra vous ajouter comme ami si vous lui communiquez le code <b>{value}</b>. Si un autre joueur vous a communiqué son code, entrez-le ici :", $ePlayerOnline['code']).'</p>';

		$form = new \util\FormUi();

		$h .= $form->openAjax('/game/action:doFriendAdd', ['class' => 'mb-2']);
			$h .= $form->inputGroup(
				$form->text('code', attributes: ['placeholder' => s("Tapez un code ami")]).
				$form->submit(s("Ajouter cet ami"))
			);
		$h .= $form->close();

		if($cPlayer->empty()) {
			$h .= '<div class="util-empty">'.s("Vous n'avez pas encore ajouté d'ami sur le jeu !").'</div>';
			return $h;
		}

		$h .= '<p>'.s("Vous pouvez motiver un de vos amis par jour, ce qui lui rapporte {value} de temps de travail !", PlayerUi::getTime(GameSetting::BONUS_MOTIVATION)).'</p>';

		$h .= '<div class="game-decks">';

			foreach($cPlayer as $ePlayer) {

				$h .= '<div class="util-deck">';
					$h .= '<div class="util-deck-head">';
						$h .= PlayerUi::getInitial($ePlayer);
						$h .= '<a href="/jouer?player='.$ePlayer['id'].'" class="util-deck-body">';
							$h .= '<div class="util-deck-title">'.encode($ePlayer['name']).'</div>';
							$h .= '<p>'.p("{value} renne attiré", "{value} rennes attirés", $ePlayer['points']).'</p>';
						$h .= '</a>';
						$h .= '<div class="util-deck-aside">';
							if($ePlayerOnline->assertMotivate($ePlayer)) {
								$h .= '<a data-ajax="/game/action:doMotivation" post-friend="'.$ePlayer['user']['id'].'" class="btn btn-outline-primary btn-sm">'.s("💪 Motiver").'</a> ';
							}
							$h .= '<a data-ajax="/game/action:doFriendRemove" post-friend="'.$ePlayer['user']['id'].'" class="btn btn-only-icon btn-outline-danger btn-sm" data-confirm="'.s("Fin de l'amitié ?").'">'.\Asset::icon('trash').'</a>';
						$h .= '</div>';
					$h .= '</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

}
?>
