<?php
namespace game;

class PlayerUi {

	public static function getTime(float $time): string {
		return \series\TaskUi::convertTime($time, showMinutes: NULL);
	}

	public function create(Player $e): \Panel {

		$form = new \util\FormUi();

		$h = '';

		$h .= $form->openAjax('/game/:doNew');

			$h .= $form->dynamicGroup($e, 'name');

			$h .= $form->group(
				content: $form->submit(s("C'est parti"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-game-create',
			title: s("Commencer à jouer"),
			body: $h
		);

	}

	/**
	 * Le portrait d'un autre joueur : l'initiale de son pseudonyme, jamais la vignette de l'utilisateur, qui dirait son
	 * nom réel (`UserUi::getVignette()`).
	 */
	/**
	 * Un rang du jeu, en abréviation courte (« 1er », « 102e »), et non celle de TextUi::th() qu'emploie le reste du produit.
	 */
	public static function getPosition(int $position): string {

		if($position === 1) {
			return s("{value}<sup>er</sup>", $position);
		} else {
			return s("{value}<sup>e</sup>", $position);
		}

	}

	public static function getInitial(Player $ePlayer): string {

		$ePlayer->expects(['id', 'name']);

		return '<div class="util-icon util-icon-round util-icon-sm util-icon-initials" style="--icon-background: '.\user\UserUi::getDefaultColor($ePlayer['id']).'">'.encode($ePlayer->getInitial()).'</div>';

	}

	/**
	 * Les trois premiers sur le podium, les suivants en rangs, et le joueur connecté au bout s'il est plus loin
	 * (`PlayerLib::getPointsRanking()`).
	 */
	public function getPointsRanking(\Collection $cPlayer, Player $ePlayerOnline): string {

		if($cPlayer->empty()) {
			return '<div class="util-empty">'.s("Le classement est encore vide et c'est normal le jeu vient de commencer !").'</div>';
		}

		$h = '';

		if($cPlayer->count() < 10) {
			$h .= '<div class="util-empty">'.s("Le classement n'est pas encore bien rempli, les premières cultures arrivent seulement à leur terme !").'</div>';
		}

		$h .= '<div class="game-podium">';

			foreach($cPlayer as $ePlayer) {

				if($ePlayer['position'] > 3) {
					break;
				}

				$h .= '<div class="game-podium-step game-podium-'.$ePlayer['position'].'">';
					$h .= '<span class="game-podium-medal">'.$ePlayer['position'].'</span>';
					$h .= self::getInitial($ePlayer);
					$h .= '<b>'.($ePlayer->is($ePlayerOnline) ? s("Vous") : encode($ePlayer['name'])).'</b>';
					$h .= '<span>'.\util\TextUi::number($ePlayer['points'], 0).' 🦌</span>';
				$h .= '</div>';

			}

		$h .= '</div>';

		$cPlayerNext = $cPlayer->find(fn($ePlayer) => $ePlayer['position'] > 3);

		if($cPlayerNext->notEmpty()) {

			$h .= '<div class="util-table" style="grid-template-columns: auto auto minmax(0, 1fr) auto">';

				$h .= '<div class="util-table-head">';
					$h .= '<div class="text-end">'.s("Position").'</div>';
					$h .= '<div></div>';
					$h .= '<div>'.s("Joueur").'</div>';
					$h .= '<div class="text-end">'.s("Rennes").'</div>';
				$h .= '</div>';

				$previous = 3;

				foreach($cPlayerNext as $ePlayer) {

					// Le joueur classé au-delà des vingt premiers vient après eux, et les rangs qui les séparent se marquent
					if($ePlayer['position'] > $previous + 1) {
						$h .= '<div class="util-table-row">';
							$h .= '<div class="text-end">…</div>';
							$h .= '<div></div>';
							$h .= '<div></div>';
							$h .= '<div></div>';
						$h .= '</div>';
					}

					$previous = $ePlayer['position'];

					$online = $ePlayer->is($ePlayerOnline);

					$h .= '<div class="util-table-row'.($online ? ' util-table-row-state util-deck-accent-info' : '').'">';
						$h .= '<div class="text-end">'.self::getPosition($ePlayer['position']).'</div>';
						$h .= '<div>'.self::getInitial($ePlayer).'</div>';
						$h .= '<div>'.($online ? '<b>'.s("Vous").'</b>' : encode($ePlayer['name'])).'</div>';
						$h .= '<div class="text-end"><b>'.\util\TextUi::number($ePlayer['points'], 0).'</b></div>';
					$h .= '</div>';

				}

			$h .= '</div>';

		}

		return $h;

	}

	public function getFoodRankings(\Collection $cFood): string {

		$h = '<div class="util-label">'.s("Votre production").'</div>';

		if($cFood->contains(fn($eFood) => $eFood['total'] > 0)) {

			$h .= '<div class="util-table" style="grid-template-columns: auto minmax(0, 1fr) auto auto">';

				$h .= '<div class="util-table-head">';
					$h .= '<div></div>';
					$h .= '<div>'.s("Légume").'</div>';
					$h .= '<div class="text-end">'.s("Total produit").'</div>';
					$h .= '<div class="text-end">'.s("Position").'</div>';
				$h .= '</div>';

				foreach($cFood as $eFood) {

					if($eFood['total'] === 0) {
						continue;
					}

					$h .= '<div class="util-table-row">';
						$h .= '<div>'.($eFood['growing']->empty() ? \Asset::icon('cup-hot', ['class' => 'asset-icon-lg']) : GrowingUi::getVignette($eFood['growing'], '2rem')).'</div>';
						$h .= '<div>'.($eFood['growing']->empty() ? s("Soupe") : $eFood['growing']['name']).'</div>';
						$h .= '<div class="text-end"><b>'.$eFood['total'].'</b></div>';
						$h .= '<div class="text-end"><b>'.self::getPosition($eFood['position']).'</b></div>';
					$h .= '</div>';

				}

			$h .= '</div>';

		} else {
			$h .= '<div class="util-empty">'.s("Vous n'avez pas encore produit de légume !").'</div>';
		}

		return $h;

	}


	public static function p(string $property): \PropertyDescriber {

		$d = Player::model()->describer($property, [
			'name' => s("Choisissez un nom de joueur"),
		]);

		switch($property) {

			case 'name' :
				$d->labelAfter = \util\FormUi::info(s("Ce n'est pas forcément votre nom réel !"));
				$d->placeholder = s("Ex. : Toto");
				break;

		}

		return $d;

	}

}
?>
