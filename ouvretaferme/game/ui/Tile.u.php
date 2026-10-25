<?php
namespace game;

class TileUi {

	/**
	 * La marque que la scène pose au-dessus d'une case, vide quand la case n'a rien à dire : une plante
	 * compagne ne se récolte pas, et une case vide ne s'annonce qu'à qui peut la semer.
	 */
	public function getLabel(Tile $e): string {

		if($e['growing']->empty()) {
			return $e['actionable'] ? '<span class="game-mark game-mark-empty">'.\Asset::icon('plus-lg').'</span>' : '';
		}

		if($e->isHarvestable() === FALSE) {
			return '';
		}

		if($e['isRipe']) {
			return '<span class="game-mark game-mark-ripe"><b>'.$e['harvest'].'</b> '.GameSetting::EMOJI_HARVEST.'</span>';
		}

		return '<span class="game-mark"><b>'.$e['harvest'].'</b> '.$this->getRemaining($e).'</span>';

	}

	/**
	 * La feuille d'une case, ouverte au toucher, avec les gestes que le joueur peut y faire.
	 */
	public function getSheet(Tile $e, Player $ePlayer, \Collection $cGrowing): string {

		if($e['growing']->empty()) {
			$title = GameSetting::EMOJI_SEEDLING.'  '.s("Semer");
			$body = $this->getSeedling($e, $ePlayer, $cGrowing);
		} else {
			$title = GrowingUi::getVignette($e['growing'], '2rem').'  '.encode($e['growing']['name']);
			$body = $this->getGrowing($e, $ePlayer);
		}

		return $this->getSheetFrame($title, $body, FALSE);

	}

	/**
	 * La feuille fermée et vide que la page pose sous la scène, remplie à l'ouverture (`tile:get`).
	 */
	public function getSheetShell(): string {

		return $this->getSheetFrame('', '', TRUE);

	}

	protected function getSheetFrame(string $title, string $body, bool $hidden): string {

		$h = '<div class="util-sheet game-tile-sheet" id="game-tile-sheet"'.($hidden ? ' hidden' : '').'>';

			$h .= '<div class="util-sheet-scrim" onclick="GameDesk.closeTile()"></div>';

			$h .= '<div class="util-sheet-panel">';

				$h .= '<div class="util-sheet-grab"></div>';

				$h .= '<div class="util-sheet-head">';
					$h .= '<h4>'.$title.'</h4>';
					$h .= '<a class="util-sheet-close" onclick="GameDesk.closeTile()">'.\Asset::icon('x-lg').'</a>';
				$h .= '</div>';

				$h .= '<div class="util-sheet-body">'.$body.'</div>';

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	protected function getGrowing(Tile $e, Player $ePlayer): string {

		$eGrowing = $e['growing'];

		if($e->isHarvestable() === FALSE) {
			return '<p>'.s("Cette plante compagne reste en place et profite aux cultures du plateau.").'</p>';
		}

		$h = '<div class="util-rows">';

			if($e->assertHarvest()) {

				$h .= $this->getAction(
					'/game/action:doHarvest',
					$e,
					[],
					GameSetting::EMOJI_HARVEST.'  '.s("Récolter"),
					s("Rapporte {value}", $e['harvest'].' '.GrowingUi::getVignette($eGrowing, '1rem')),
					$e['harvestTime'],
					$ePlayer->acceptTime($e['harvestTime'])
				);

			} else {

				$h .= '<div class="util-row">';
					$h .= '<span>'.GameSetting::EMOJI_HARVEST.'  '.s("Récolte dans {value}", $this->getRemaining($e)).$this->getDetail(s("Récolte le {value}", \util\DateUi::numeric($e['harvestedAt'], \util\DateUi::DATE_HOUR_MINUTE))).'</span>';
					$h .= '<span>'.s("Rapportera {value}", $e['harvest'].' '.GrowingUi::getVignette($eGrowing, '1rem')).'</span>';
				$h .= '</div>';

				if($e->assertWater()) {

					$h .= $this->getAction(
						'/game/action:doWatering',
						$e,
						[],
						GameSetting::EMOJI_WATERING.'  '.s("Arroser"),
						s("{value} de plus à la récolte", $eGrowing['bonusWatering'].' '.GrowingUi::getVignette($eGrowing, '1rem')),
						GameSetting::TIME_WATERING,
						$ePlayer->acceptTime(GameSetting::TIME_WATERING)
					);

				}

				if($e->assertWeed()) {

					$h .= $this->getAction(
						'/game/action:doWeed',
						$e,
						[],
						GameSetting::EMOJI_WEED.'  '.s("Désherber"),
						s("Récolte avancée de {value}", $this->getDays(GameSetting::BONUS_WEED)),
						GameSetting::TIME_WEED,
						$ePlayer->acceptTime(GameSetting::TIME_WEED)
					);

				}

			}

		$h .= '</div>';

		return $h;

	}

	protected function getSeedling(Tile $e, Player $ePlayer, \Collection $cGrowing): string {

		$h = '<div class="util-rows">';

			$eGrowingBefore = new Growing();

			foreach($cGrowing as $eGrowing) {

				if(
					$eGrowing['harvest'] === NULL and
					$eGrowingBefore->notEmpty() and
					$eGrowingBefore['harvest'] !== NULL
				) {
					$h .= '<div class="util-label">'.s("Plantes compagnes").'</div>';
				}

				$can = (
					$ePlayer->acceptTime(GameSetting::TIME_SEEDLING) and
					(
						$eGrowing['fqn'] !== 'pivoine' or
						$e['harvestTime'] > 1
					)
				);

				$h .= $this->getAction(
					'/game/action:doSeedling',
					$e,
					['growing' => $eGrowing['id']],
					'<span class="util-vignette">'.GrowingUi::getVignette($eGrowing, '2rem').'<span>'.encode($eGrowing['name']).$this->getDetail(($eGrowing['harvest'] !== NULL) ? s("Récolté en {value}", $this->getDays($eGrowing['days'])) : NULL).'</span></span>',
					NULL,
					GameSetting::TIME_SEEDLING,
					$can
				);

				$eGrowingBefore = $eGrowing;

			}

		$h .= '</div>';

		return $h;

	}

	protected function getAction(string $url, Tile $e, array $post, string $label, ?string $detail, float $time, bool $can): string {

		$attributes = 'data-ajax="'.$url.'" post-id="'.$e['id'].'"';

		foreach($post as $name => $value) {
			$attributes .= ' post-'.$name.'="'.encode($value).'"';
		}

		$h = '<a '.$attributes.' class="util-row game-tile-sheet-action'.($can ? '' : ' disabled').'">';
			$h .= '<span>';
				$h .= $label;
				$h .= $this->getDetail($detail);
			$h .= '</span>';
			$h .= '<span>'.\Asset::icon('clock').'  '.PlayerUi::getTime($time).'</span>';
		$h .= '</a>';

		return $h;

	}

	protected function getDetail(?string $detail): string {

		return ($detail === NULL) ? '' : '<small class="game-tile-sheet-detail util-annotation">'.$detail.'</small>';

	}

	protected function getRemaining(Tile $e): string {

		return \util\DateUi::secondToDuration($e['remaining'], \util\DateUi::SHORT, maxNumber: 1);

	}

	// Une durée en jours, écrite comme le temps qui reste avant la récolte
	protected function getDays(int $days): string {

		return \util\DateUi::secondToDuration($days * 86400, \util\DateUi::SHORT, maxNumber: 1);

	}

}
?>
