<?php
/**
 * Relit le joueur connecté après une action qui a changé son temps, ses rennes ou ses dons. La relecture ne porte que
 * l'id de l'utilisateur, quand premium et journée du joueur, que le bandeau affiche, se calculent sur l'utilisateur
 * complet de l'appelant.
 */
$reload = function(\stdClass $data): void {

	$ePlayer = \game\PlayerLib::getById($data->ePlayer['id']);
	$ePlayer['user'] = $data->ePlayer['user'];

	$data->ePlayer = $ePlayer;

};

/**
 * Une soupe cuisinée ou mangée change le stock et les rennes, et une soupe mangée le temps : la page relit ce que
 * montrent le cellier, la marmite, le bandeau, le classement et Ma partie.
 */
$prepareFood = function(\stdClass $data) use($reload): void {

	$reload($data);

	$data->cGrowing = \game\GrowingLib::getAll();
	$data->cFood = \game\FoodLib::getByPlayer($data->ePlayer);
	$data->cHistory = \game\HistoryLib::getByPlayer($data->ePlayer);
	$data->cPlayerRanking = \game\PlayerLib::getPointsRanking($data->ePlayer);
	$data->ePlayer['position'] = \game\PlayerLib::getPosition($data->ePlayer);

	\game\FoodLib::fillRankings($data->cFood);
	\game\FoodLib::fillStock($data->ePlayer, $data->cFood);

};

// Une amitié ajoutée, retirée ou un ami motivé ne changent que la liste des amis, relue avec le joueur, dont la
// motivation du jour vient de partir
$prepareFriends = function(\stdClass $data) use($reload): void {

	$reload($data);

	$data->cPlayerFriend = \game\FriendLib::getByPlayer($data->ePlayer);

};

new Page(function($data) {

		if(\game\GameSetting::isPlaying() === FALSE) {
			throw new NotAllowedAction();
		}

		$data->ePlayer = \game\PlayerLib::getOnline();

		if($data->ePlayer->empty()) {
			throw new RedirectAction('/jouer');
		}

	})
	->post('doEat', function($data) use($prepareFood) {

		$data->ePlayer->validate('acceptEat');

		$value = POST('value', 'int');

		$fw = new \FailWatch();

		\game\ActionLib::eat($data->ePlayer, $value);

		$fw->validate();

		$prepareFood($data);

		throw new ViewAction($data);

	})
	->post('doCook', function($data) use($prepareFood) {

		$value = POST('value', 'int');

		$fw = new \FailWatch();

		\game\ActionLib::cook($data->ePlayer, $value);

		$fw->validate();

		$prepareFood($data);

		throw new ViewAction($data);

	})
	->post('doRestart', function($data) {

		\game\PlayerLib::restart($data->ePlayer);

		throw new RedirectAction('/jouer');

	})
	->post('doFriendAdd', function($data) use($prepareFriends) {

		$fw = new \FailWatch();

		\game\PlayerLib::addFriend($data->ePlayer, POST('code'));

		$fw->validate();

		$prepareFriends($data);

		throw new ViewAction($data);

	})
	->post('doMotivation', function($data) use($prepareFriends) {

		$data->ePlayerFriend = \game\PlayerLib::getByUser(POST('friend', 'user\User'))->validate(
			fn(\game\Player $e) => \game\FriendLib::exists($data->ePlayer, $e),
			fn(\game\Player $e) => $data->ePlayer->acceptMotivate($e)
		);

		$fw = new \FailWatch();

		\game\PlayerLib::motivate($data->ePlayer, $data->ePlayerFriend);

		$fw->validate();

		$prepareFriends($data);

		throw new ViewAction($data);

	})
	->post('doFriendRemove', function($data) use($prepareFriends) {

		$data->ePlayerFriend = \game\PlayerLib::getByUser(POST('friend', 'user\User'))->validate();

		\game\PlayerLib::removeFriend($data->ePlayer, $data->ePlayerFriend);

		$prepareFriends($data);

		throw new ViewAction($data);

	});


/**
 * Un geste ne renvoie que ce qu'il change : les cases relues après lui, que la scène applique, le temps du bandeau et
 * l'historique de Ma partie, où chaque geste s'inscrit. Seule une récolte change les stocks, donc le cellier, les rennes
 * et le classement (`$harvest`), et dit combien de rennes elle a attirés (`gain`). `$data->e` est la case lue avant le
 * geste.
 */
$prepare = function(\stdClass $data, bool $harvest) use($reload): void {

	$data->cTile = \game\BoardLib::getChanged($data->e, $data->ePlayer);
	$data->tiles = \game\BoardLib::export($data->cTile);

	$points = $data->ePlayer['points'];

	$reload($data);

	$data->cHistory = \game\HistoryLib::getByPlayer($data->ePlayer);
	$data->ePlayer['position'] = \game\PlayerLib::getPosition($data->ePlayer);
	$data->gain = $data->ePlayer['points'] - $points;

	if($harvest) {
		$data->cFood = \game\FoodLib::getByPlayer($data->ePlayer);
		\game\FoodLib::fillRankings($data->cFood);
		\game\FoodLib::fillStock($data->ePlayer, $data->cFood);
		$data->cPlayerRanking = \game\PlayerLib::getPointsRanking($data->ePlayer);
	}

};

new \game\TilePage(function($data) {

		$data->ePlayer = \game\PlayerLib::getOnline();

		if($data->ePlayer->empty()) {
			throw new RedirectAction('/jouer');
		}

	})
	->write('doSeedling', function($data) use($prepare) {

		$data->eGrowing = \game\GrowingLib::getById(POST('growing'))->validate();

		$fw = new \FailWatch();

		\game\ActionLib::seed($data->ePlayer, $data->e, $data->eGrowing);

		$fw->validate();

		$prepare($data, FALSE);

		throw new ViewAction($data);

	}, addValidate: ['acceptPlay'])
	->write('doWatering', function($data) use($prepare) {

		$fw = new \FailWatch();

		\game\ActionLib::water($data->ePlayer, $data->e);

		$fw->validate();

		$prepare($data, FALSE);

		throw new ViewAction($data);

	}, addValidate: ['acceptPlay'])
	->write('doWeed', function($data) use($prepare) {

		$fw = new \FailWatch();

		\game\ActionLib::weed($data->ePlayer, $data->e);

		$fw->validate();

		$prepare($data, FALSE);

		throw new ViewAction($data);

	}, addValidate: ['acceptPlay'])
	->write('doHarvest', function($data) use($prepare) {

		$fw = new \FailWatch();

		\game\ActionLib::harvest($data->ePlayer, $data->e);

		$fw->validate();

		$prepare($data, TRUE);

		throw new ViewAction($data);

	}, addValidate: ['acceptPlay']);
?>
