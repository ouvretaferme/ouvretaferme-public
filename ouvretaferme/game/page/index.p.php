<?php
new Page(function($data) {

		if(get_exists('player')) {

			$data->ePlayer = \game\PlayerLib::getById(GET('player'))->validate();

			if(\game\FriendLib::are($data->ePlayer) === FALSE) {
				throw new NotExpectedAction();
			}

		} else {
			$data->ePlayer = \game\PlayerLib::getOnline();
		}

	})
	->get('/jouer', function($data) {

		$data->cGrowing = \game\GrowingLib::getAll();
		$data->bake = \scene\BakeLib::getCatalogAndVersions();

		if(
			\game\GameSetting::isPlaying() and
			$data->ePlayer->notEmpty()
		) {

			// Sans plateau demandé, la page s'ouvre sur toute la cour
			$data->board = NULL;

			if(get_exists('board')) {

				$board = GET('board', 'int');

				if($board >= 1 and $board <= $data->ePlayer->getBoards()) {
					$data->board = $board;
				}

			}

			$data->ccTile = \game\BoardLib::getTiles($data->ePlayer);
			$data->scene = \game\BoardLib::getScene($data->ccTile, $data->ePlayer->getBoards(), $data->ePlayer->isOnline() === FALSE, $data->board);
			$data->scene['avatar'] = \game\BoardLib::getFiche($data->ePlayer);

			// Le stock, l'historique, les amis et le classement ne se lisent que pour le joueur connecté : la cour d'un ami
			// se regarde sans rien de ce qu'il garde pour lui
			if($data->ePlayer->isOnline()) {

				$data->cFood = \game\FoodLib::getByPlayer($data->ePlayer);
				$data->cHistory = \game\HistoryLib::getByPlayer($data->ePlayer);
				$data->cPlayerFriend = \game\FriendLib::getByPlayer($data->ePlayer);
				$data->cPlayerRanking = \game\PlayerLib::getPointsRanking($data->ePlayer);
				$data->ePlayer['position'] = \game\PlayerLib::getPosition($data->ePlayer);

				\game\FoodLib::fillRankings($data->cFood);
				\game\FoodLib::fillStock($data->ePlayer, $data->cFood);

				$data->today = \Clock::date();
				$data->ripe = \game\BoardLib::getRipe($data->ccTile);
				$data->adventDay = \game\GameSetting::getAdventDay();
				$data->lastDay = ($data->today === \game\GameSetting::END);
				$data->boardOpening = array_search($data->today, \game\GameSetting::BOARDS_OPENING, TRUE) ?: NULL;

				$data->new = get_exists('start');
				$data->sheet = match(GET('show')) {
					'story', 'rules' => 'rules',
					default => $data->new ? 'rules' : NULL
				};

			}

		} else {

			// L'accueil et la fin de partie montrent la cour sans personne : aucune case, tous les plateaux ouverts
			$data->ccTile = new \Collection();
			$data->scene = \game\BoardLib::getScene($data->ccTile, \game\GameSetting::BOARDS, TRUE, NULL);

		}

		if(\game\GameSetting::isFinished()) {
			$data->points = \game\PlayerLib::getTotalPoints();
			// Le classement se lit pour qui regarde, même depuis la cour d'un ami
			$data->ePlayerOnline = \game\PlayerLib::getOnline();
			$data->cPlayerRanking = \game\PlayerLib::getPointsRanking($data->ePlayerOnline);
		} else if($data->ePlayer->empty()) {
			throw new ViewAction($data, ':start');
		}

		throw new ViewAction($data);

	});

new \game\PlayerPage(function($data) {

		if(\game\GameSetting::isPlaying() === FALSE) {
			throw new NotAllowedAction();
		}

		\user\ConnectionLib::checkLogged();

		$ePlayer = \game\PlayerLib::getOnline();

		if($ePlayer->notEmpty()) {
			throw new RedirectAction('/jouer');
		}

	})
	->getCreateElement(function() {

		$eUser = \user\ConnectionLib::getOnline();

		return new \game\Player([
			'user' => $eUser
		]);

	})
	->create(page: 'new')
	->doCreate(fn() => throw new RedirectAction('/jouer?start'), page: 'doNew');
?>
