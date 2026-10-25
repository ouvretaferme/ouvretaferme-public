<?php
namespace game;

/**
 * L'écran de la partie : la cour en 3D (`GameBoard`, board.js), et ce que l'habillage pose par-dessus (`GameDesk`,
 * desk.js), le bandeau du joueur, le calendrier de l'Avent, la porte du jour, puis les feuilles qu'il ouvre.
 */
class DeskUi {

	public static function getFonts(): string {

		$h = '<link rel="preconnect" href="https://fonts.googleapis.com">';
		$h .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
		$h .= '<link href="https://fonts.googleapis.com/css2?family='.urlencode('Mystery Quest').'&family=Fraunces:opsz,wght@9..144,600;9..144,800&display=swap" rel="stylesheet">';

		return $h;

	}

	/**
	 * La scène de la cour, sa feuille de case et ses marques. La scène ne reçoit aucun texte : chaque marque est un
	 * `<template data-game-label>` qu'elle pose sans en connaître la forme, une case par son id, un plateau fermé par
	 * `board-<n>`, le joueur par `avatar`, le cellier par `cellar`. `$overlay` est ce qui se pose par-dessus la cour.
	 */
	public function getScene(Player $ePlayer, array $scene, \Collection $ccTile, array $bake, string $cellar, string $overlay): string {

		\Asset::js('game', 'path.js');
		\Asset::js('game', 'court.js');
		\Asset::js('game', 'board.js');
		\Asset::js('game', 'desk.js');

		$h = '<div class="game-court scene-frame" id="game-court">';

			$h .= '<div id="game-board" class="game-board-scene" data-game-board="'.encode(json_encode($scene)).'" data-error="'.encode(s("La vue en 3D n'a pas pu s'ouvrir. Rechargez la page pour réessayer.")).'" onrender="GameDesk.start(this)">';
				$h .= new \scene\SceneUi()->getContainer('game-board-container', $bake);

				foreach($ccTile as $cTile) {
					foreach($cTile as $eTile) {
						$h .= '<template data-game-label="'.$eTile['id'].'">'.new TileUi()->getLabel($eTile).'</template>';
					}
				}

				foreach($scene['boards'] as $board => $content) {

					if($content['open'] === FALSE) {
						$h .= '<template data-game-label="board-'.$board.'"><span class="game-mark game-mark-locked">'.s("Ouverture le {value}", \util\DateUi::numeric(GameSetting::BOARDS_OPENING[$board])).'</span></template>';
					}

				}

				if($ePlayer->notEmpty()) {
					$h .= '<template data-game-label="avatar"><span class="game-mark game-mark-avatar">'.encode($ePlayer['name']).'</span></template>';
				}

				if($cellar !== '') {
					$h .= '<template data-game-label="cellar">'.$cellar.'</template>';
				}

			$h .= '</div>';

			$h .= $overlay;

		$h .= '</div>';

		if($ePlayer->isOnline()) {
			$h .= new TileUi()->getSheetShell();
		}

		return $h;

	}

	/**
	 * Le bandeau du joueur connecté, en haut de la cour. Le temps et les rennes portent chacun leur id, qu'une action
	 * remplace seuls quand elle les change (`view/action.v.php`).
	 */
	public function getHud(Player $ePlayer, int $friends): string {

		$h = '<div class="game-hud" id="game-hud">';

			$h .= '<a class="game-hud-player" data-game-open="player">';
				$h .= \user\UserUi::getVignette($ePlayer['user'], '2.25rem');
				$h .= '<span class="game-hud-name">'.encode($ePlayer['name']).'</span>';
			$h .= '</a>';

			$h .= $this->getTime($ePlayer);
			$h .= $this->getPoints($ePlayer);

			$h .= '<div class="game-hud-tools">';
				$h .= $this->getFriendsTool($friends);
				$h .= '<a class="game-tool" data-game-open="ranking" data-game-gesture="fier">'.\Asset::icon('trophy-fill').'<span class="game-tool-label">'.s("Classement").'</span></a>';
				$h .= '<a class="game-tool" data-game-open="rules">'.\Asset::icon('question-lg').'<span class="game-tool-label">'.s("Règles").'</span></a>';
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le bandeau de la cour d'un ami, qui se regarde sans se jouer : son initiale, son nom, ses rennes, et le retour à sa
	 * propre partie.
	 */
	public function getHudFriend(Player $ePlayer): string {

		$h = '<div class="game-hud" id="game-hud">';

			$h .= '<span class="game-hud-player">';
				$h .= PlayerUi::getInitial($ePlayer);
				$h .= '<span class="game-hud-name">'.encode($ePlayer['name']).'</span>';
			$h .= '</span>';

			$h .= $this->getPoints($ePlayer);

			$h .= '<div class="game-hud-tools">';
				$h .= '<a href="/jouer" class="game-tool">'.\Asset::icon('arrow-left').'<span class="game-tool-label">'.s("Revenir sur ma partie").'</span></a>';
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	public function getTime(Player $ePlayer): string {

		$h = '<div class="game-hud-item game-time" id="game-time">';
			$h .= \Asset::icon('lamp-fill');
			$h .= '<span class="game-time-value">';
				$h .= '<b>'.PlayerUi::getTime($ePlayer->getRemainingTime()).'</b>';
				$h .= '<small>'.s("sur {value}", PlayerUi::getTime($ePlayer->getDailyTime())).'</small>';
			$h .= '</span>';
			$h .= '<span class="game-gauge">';
				for($hour = 0; $hour < $ePlayer->getDailyTime(); $hour++) {
					$h .= '<i'.($hour < $ePlayer->getGauge() ? ' class="game-gauge-on"' : '').'></i>';
				}
			$h .= '</span>';
		$h .= '</div>';

		return $h;

	}

	public function getPoints(Player $ePlayer): string {

		return '<div class="game-hud-item game-points" id="game-points"><span>🦌</span><b>'.\util\TextUi::number($ePlayer['points'], 0).'</b></div>';

	}

	public function getFriendsTool(int $friends): string {

		$h = '<a class="game-tool" id="game-friends" data-game-open="friends">';
			$h .= \Asset::icon('people-fill');
			$h .= '<span class="game-tool-label">'.s("Amis").'</span>';
			if($friends > 0) {
				$h .= '<span class="util-counter util-counter-solid">'.$friends.'</span>';
			}
		$h .= '</a>';

		return $h;

	}

	/**
	 * Le calendrier de l'Avent, jusqu'au 24 décembre où passent les rennes. Un téléphone n'en montre que sept jours autour
	 * d'aujourd'hui, les autres portent `game-advent-far`.
	 */
	public function getAdvent(int $today): string {

		$first = min(max(1, $today - 3), 24 - 6);

		$h = '<div class="game-advent">';

			for($day = 1; $day <= 24; $day++) {

				$class = 'game-advent-day';

				if($day < $today) {
					$class .= ' game-advent-past';
				} else if($day === $today) {
					$class .= ' game-advent-today';
				}

				if($day < $first or $day > $first + 6) {
					$class .= ' game-advent-far';
				}

				$h .= '<span class="'.$class.'">'.($day === 24 ? '🦌' : $day).'</span>';

			}

		$h .= '</div>';

		return $h;

	}

	public function getBanner(bool $lastDay, ?int $boardOpening): string {

		if($lastDay) {
			return '<div class="game-banner"><b>'.s("Dernier jour !").'</b> '.s("Vous pouvez jouer jusqu'à {value}.", GameSetting::END_TIME).' '.s("Ensuite, ce sont les rennes qui travaillent 🦌").'</div>';
		}

		if($boardOpening !== NULL) {
			return '<a href="/jouer?board='.$boardOpening.'" class="game-banner">🎉 '.s("Vous pouvez maintenant jouer avec le plateau {value}", $boardOpening).'</a>';
		}

		return '';

	}

	/**
	 * La porte du jour, que l'habillage n'ouvre qu'une fois par jour (`GameDesk.door()`).
	 */
	public function getDoor(Player $ePlayer, string $date, int $ripe): string {

		$h = '<div class="game-door" data-game-door="'.$date.'" hidden>';
			$h .= '<div class="game-door-frame">';
				$h .= '<div class="game-door-inside">';
					$h .= \user\UserUi::getVignette($ePlayer['user'], '5rem');
					$h .= '<p>'.\util\DateUi::textual($date, \util\DateUi::DAY_MONTH).'</p>';
					$h .= '<h2>'.($ripe === 0 ? s("Aucune récolte n'est prête") : p("{value} récolte est prête", "{value} récoltes sont prêtes", $ripe)).'</h2>';
					$h .= '<p>'.s("{value} de travail aujourd'hui", PlayerUi::getTime($ePlayer->getRemainingTime())).'</p>';
					$h .= '<a class="btn btn-game-gold game-door-open" data-game-door-close data-game-gesture="saluer">'.s("Ouvrir la cour").'</a>';
				$h .= '</div>';
				$h .= '<div class="game-door-leaf">'.\Asset::icon('stars').'</div>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La marque du cellier : le stock de chaque légume et les soupes. Chaque légume porte l'id de sa culture, où
	 * atterrit la récolte (`GameDesk.harvested()`).
	 */
	public function getCellar(Player $ePlayer, \Collection $cFood): string {

		$h = '<span class="game-mark game-cellar">';

			$h .= '<span class="game-cellar-stock">';

				foreach($cFood as $eFood) {

					if($eFood['growing']->notEmpty()) {
						$h .= '<span class="game-cellar-item'.($eFood['current'] === 0 ? ' game-cellar-empty' : '').'" data-game-stock="'.$eFood['growing']['id'].'">'.GrowingUi::getVignette($eFood['growing'], '1.5rem').'<b>'.$eFood['current'].'</b></span>';
					}

				}

			$h .= '</span>';

			$h .= '<span class="game-cellar-soup">'.\Asset::icon('cup-hot-fill').'<b>'.$ePlayer['soups'].'</b></span>';

		$h .= '</span>';

		return $h;

	}

	/**
	 * Les feuilles que le bandeau et la cour ouvrent, fermées. `$open` est celle que la page ouvre à l'affichage.
	 */
	public function getSheets(Player $ePlayer, \Collection $cGrowing, \Collection $cFood, \Collection $cPlayerRanking, \Collection $cPlayerFriend, \Collection $cHistory, bool $hasAvatar, ?string $open, bool $new): string {

		$h = $this->getSheet('cellar', \Asset::icon('cup-hot-fill').'  '.s("La marmite"), $this->getMarmite($ePlayer, $cGrowing), $open);
		$h .= $this->getSheet('ranking', \Asset::icon('trophy-fill').'  '.s("Classement"), $this->getRanking($ePlayer, $cPlayerRanking, $cFood), $open);
		$h .= $this->getSheet('friends', \Asset::icon('people-fill').'  '.s("Amis"), new FriendUi()->display($cPlayerFriend, $ePlayer), $open);
		$h .= $this->getSheet('rules', \Asset::icon('question-lg').'  '.s("Règles du jeu"), $this->getRules($ePlayer, $cGrowing, $new), $open);
		$h .= $this->getSheet('player', \Asset::icon('person-fill').'  '.s("Ma partie"), $this->getPlayer($ePlayer, $cHistory, $hasAvatar), $open);

		return $h;

	}

	protected function getSheet(string $name, string $title, string $body, ?string $open): string {

		$h = '<div class="util-sheet game-sheet" id="game-sheet-'.$name.'"'.($open === $name ? '' : ' hidden').'>';

			$h .= '<div class="util-sheet-scrim" data-game-close></div>';

			$h .= '<div class="util-sheet-panel">';

				$h .= '<div class="util-sheet-grab"></div>';

				$h .= '<div class="util-sheet-head">';
					$h .= '<h4>'.$title.'</h4>';
					$h .= '<a class="util-sheet-close" data-game-close>'.\Asset::icon('x-lg').'</a>';
				$h .= '</div>';

				$h .= '<div class="util-sheet-body">'.$body.'</div>';

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La marmite : cuisiner une soupe prend un légume de chaque espèce, la manger rend du temps de travail aux adhérents.
	 * Un nombre de soupes qui ne se fait pas se montre grisé plutôt que de disparaître, sauf les lots de 5 et le tout.
	 */
	public function getMarmite(Player $ePlayer, \Collection $cGrowing): string {

		$cookable = $ePlayer['cookable'];
		$soups = $ePlayer['soups'];

		$h = '<div class="game-pot">';
			$h .= \Asset::icon('cup-hot-fill');
			$h .= '<b>'.$soups.'</b>';
			$h .= '<span>'.p("soupe", "soupes", $soups).'</span>';
		$h .= '</div>';

		$h .= '<div class="util-rows">';

			$h .= '<div class="util-label">'.s("Côté cuisine").'</div>';

				$h .= '<div class="util-row">';
					$h .= '<span class="game-recipe">';
						foreach($cGrowing as $eGrowing) {
							if($eGrowing['harvest'] !== NULL) {
								$h .= GrowingUi::getVignette($eGrowing, '1.5rem');
							}
						}
						$h .= \Asset::icon('arrow-right');
						$h .= \Asset::icon('cup-hot-fill');
					$h .= '</span>';
					$h .= '<span>'.s("1 légume de chaque").'</span>';
				$h .= '</div>';

				$h .= $this->getMarmiteAction('/game/action:doCook', 1, s("Cuisiner une soupe"), $cookable >= 1);

				if($cookable >= 5) {
					$h .= $this->getMarmiteAction('/game/action:doCook', 5, s("Cuisiner 5 soupes"), TRUE);
				}

				if($cookable > 5) {
					$h .= $this->getMarmiteAction('/game/action:doCook', $cookable, s("Cuisiner {value} soupes", $cookable), TRUE, s("C'est beaucoup, vous êtes sûr ?"));
				}

		$h .= '</div>';

		$h .= '<div class="util-rows">';

			$h .= '<div class="util-label">'.s("Côté salon").'</div>';

				if($ePlayer->isPremium()) {

					$h .= $this->getMarmiteAction('/game/action:doEat', 1, s("Manger une soupe"), $soups >= 1, detail: s("+{value} de temps de travail aujourd'hui", PlayerUi::getTime(GameSetting::BONUS_SOUP)));

					if($soups >= 5) {
						$h .= $this->getMarmiteAction('/game/action:doEat', 5, s("Manger 5 soupes"), TRUE, detail: s("+{value} de temps de travail aujourd'hui", PlayerUi::getTime(5 * GameSetting::BONUS_SOUP)));
					}

				} else {

					$h .= '<div class="util-row">';
						$h .= '<span>'.s("Manger une soupe").'<small class="game-sheet-detail util-annotation">'.s("+{value} de temps de travail aujourd'hui", PlayerUi::getTime(GameSetting::BONUS_SOUP)).'</small></span>';
						$h .= '<span>'.s("Disponible pour les adhérents !").'</span>';
					$h .= '</div>';

				}

		$h .= '</div>';

		return $h;

	}

	protected function getMarmiteAction(string $url, int $value, string $label, bool $can, ?string $confirm = NULL, ?string $detail = NULL): string {

		$h = '<a data-ajax="'.$url.'" post-value="'.$value.'" class="util-row game-sheet-action'.($can ? '' : ' disabled').'"'.($confirm === NULL ? '' : ' data-confirm="'.$confirm.'"').'>';
			$h .= '<span>';
				$h .= $label;
				if($detail !== NULL) {
					$h .= '<small class="game-sheet-detail util-annotation">'.$detail.'</small>';
				}
			$h .= '</span>';
			$h .= '<span>'.\Asset::icon('chevron-right').'</span>';
		$h .= '</a>';

		return $h;

	}

	/**
	 * Le classement des rennes, podium puis rangs suivants, et la production du joueur, espèce par espèce.
	 */
	public function getRanking(Player $ePlayer, \Collection $cPlayerRanking, \Collection $cFood): string {

		return new PlayerUi()->getPointsRanking($cPlayerRanking, $ePlayer).new PlayerUi()->getFoodRankings($cFood);

	}

	/**
	 * Les règles, l'histoire du jeu et le tableau des cultures. La partie qui commence (`?start`) s'ouvre sur les règles
	 * et leur premier geste ; une partie en cours y retrouve la remise à zéro.
	 */
	public function getRules(Player $ePlayer, \Collection $cGrowing, bool $new): string {

		$h = '';

		if($new) {
			$h .= '<p class="game-sheet-lead">'.s("Votre partie est bien créée, {value} !", encode($ePlayer['name'])).' '.s("Il ne vous reste qu'à lire les règles du jeu avant de commencer le travail.").'</p>';
		} else {
			$h .= new HelpUi()->getSynopsis();
		}

		$h .= new HelpUi()->getRules($ePlayer, $new);

		$h .= '<div class="util-label">'.s("Tableau des cultures").'</div>';
		$h .= new HelpUi()->getCrops($cGrowing);

		return $h;

	}

	/**
	 * Ma partie : le joueur, ses rennes, le lien vers son avatar, et l'historique de ses gestes.
	 */
	public function getPlayer(Player $ePlayer, \Collection $cHistory, bool $hasAvatar): string {

		$h = '<div class="game-card">';
			$h .= \user\UserUi::getVignette($ePlayer['user'], '5rem');
			$h .= '<div>';
				$h .= '<div class="game-card-name">'.encode($ePlayer['name']).'</div>';
				$h .= '<div>';
					$h .= p("{value} renne", "{value} rennes", $ePlayer['points'], ['value' => \util\TextUi::number($ePlayer['points'], 0)]);
					if($ePlayer['position'] !== NULL) {
						$h .= ' · '.s("{value} au classement", PlayerUi::getPosition($ePlayer['position']));
					}
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		if($hasAvatar) {
			$h .= '<a href="/user/avatar" class="btn btn-outline-primary">'.\Asset::icon('person-bounding-box').' '.s("Personnaliser mon avatar").'</a>';
		}

		$h .= '<div class="util-label">'.s("Historique").'</div>';

		if($cHistory->empty()) {
			$h .= '<div class="util-empty">'.s("Vous n'avez encore rien fait dans votre cour.").'</div>';
		} else {
			$h .= new HistoryUi()->display($cHistory);
		}

		return $h;

	}

	/**
	 * Ce qui se pose sur la cour sans personne de l'accueil et de la fin de partie.
	 */
	public function getPanel(string $content): string {

		return '<div class="game-panel">'.$content.'</div>';

	}

}
?>
