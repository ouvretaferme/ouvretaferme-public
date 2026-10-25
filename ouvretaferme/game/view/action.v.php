<?php
/**
 * Une action ne rend que ce qu'elle change, nœud par nœud : le temps et les rennes du bandeau, la marque du cellier
 * que la scène repose (`GameBoard.apply()`), et le corps des feuilles dont le contenu a bougé. Une feuille se remplace
 * par son corps et non en entier : c'est elle que le joueur a peut-être ouverte.
 */
$sheet = fn(AjaxTemplate $t, string $name, string $body) => $t->qs('#game-sheet-'.$name.' .util-sheet-body')->innerHtml($body);

/**
 * Un geste sur une case rend à la scène les cases qu'il a changées et les gestes que le joueur y enchaîne, puis le temps
 * et Ma partie où il s'inscrit. Il ferme la feuille de la case. `$labels` porte les marques hors des cases qu'il change.
 */
$respond = function(AjaxTemplate $t, \game\Player $ePlayer, \Collection $cTile, array $tiles, \Collection $cHistory, bool $hasAvatar, string $success, array $gestures, array $labels = []) use($sheet): void {

	foreach($cTile as $eTile) {
		$labels[$eTile['id']] = new \game\TileUi()->getLabel($eTile);
	}

	$t->js()->eval('GameBoard.apply('.json_encode($tiles).', '.json_encode($labels, JSON_UNESCAPED_UNICODE).', '.json_encode($gestures).')');
	$t->js()->eval('GameDesk.closeTile()');
	$t->qs('#game-time')->outerHtml(new \game\DeskUi()->getTime($ePlayer));
	$sheet($t, 'player', new \game\DeskUi()->getPlayer($ePlayer, $cHistory, $hasAvatar));

	$t->js()->success('game', $success);

};

// Une récolte remplit en plus le cellier, les rennes et le classement, et vole de sa case jusqu'au cellier
$respondHarvest = function(AjaxTemplate $t, \game\Player $ePlayer, \game\Tile $eTile, \Collection $cTile, array $tiles, \Collection $cHistory, bool $hasAvatar, \Collection $cFood, \Collection $cPlayerRanking, int $gain) use($sheet, $respond): void {

	$respond($t, $ePlayer, $cTile, $tiles, $cHistory, $hasAvatar, 'Action::harvested', ['recolte', 'joyeux'], ['cellar' => new \game\DeskUi()->getCellar($ePlayer, $cFood)]);

	$t->qs('#game-points')->outerHtml(new \game\DeskUi()->getPoints($ePlayer));
	$sheet($t, 'ranking', new \game\DeskUi()->getRanking($ePlayer, $cPlayerRanking, $cFood));
	$t->js()->eval('GameDesk.harvested('.$eTile['id'].', '.$eTile['growing']['id'].', '.$gain.')');

};

/**
 * Une soupe cuisinée ou mangée : le cellier, le bandeau, la marmite, le classement et Ma partie. Manger ferme la marmite
 * pour que le joueur boive sa soupe sous les yeux.
 */
$respondFood = function(AjaxTemplate $t, \game\Player $ePlayer, \Collection $cGrowing, \Collection $cFood, \Collection $cPlayerRanking, \Collection $cHistory, bool $hasAvatar, string $success, bool $eat) use($sheet): void {

	$t->js()->eval('GameBoard.apply([], '.json_encode(['cellar' => new \game\DeskUi()->getCellar($ePlayer, $cFood)], JSON_UNESCAPED_UNICODE).', '.json_encode($eat ? ['boire'] : []).')');
	$t->qs('#game-time')->outerHtml(new \game\DeskUi()->getTime($ePlayer));
	$t->qs('#game-points')->outerHtml(new \game\DeskUi()->getPoints($ePlayer));
	$sheet($t, 'cellar', new \game\DeskUi()->getMarmite($ePlayer, $cGrowing));
	$sheet($t, 'ranking', new \game\DeskUi()->getRanking($ePlayer, $cPlayerRanking, $cFood));
	$sheet($t, 'player', new \game\DeskUi()->getPlayer($ePlayer, $cHistory, $hasAvatar));

	if($eat) {
		$t->js()->eval('GameDesk.closeSheet("cellar")');
	}

	$t->js()->success('game', $success);

};

// La liste des amis et leur compte sur le bandeau
$respondFriends = function(AjaxTemplate $t, \game\Player $ePlayer, \Collection $cPlayerFriend, ?string $success) use($sheet): void {

	$t->qs('#game-friends')->outerHtml(new \game\DeskUi()->getFriendsTool($cPlayerFriend->count()));
	$sheet($t, 'friends', new \game\FriendUi()->display($cPlayerFriend, $ePlayer));

	if($success !== NULL) {
		$t->js()->success('game', $success);
	}

};

new JsonView('doSeedling', fn($data, AjaxTemplate $t) => $respond($t, $data->ePlayer, $data->cTile, $data->tiles, $data->cHistory, $data->hasAvatar, 'Action::planted', ['crouch']));
new JsonView('doWatering', fn($data, AjaxTemplate $t) => $respond($t, $data->ePlayer, $data->cTile, $data->tiles, $data->cHistory, $data->hasAvatar, 'Action::watered', ['arroser']));
new JsonView('doWeed', fn($data, AjaxTemplate $t) => $respond($t, $data->ePlayer, $data->cTile, $data->tiles, $data->cHistory, $data->hasAvatar, 'Action::weeded', ['hoe']));
new JsonView('doHarvest', fn($data, AjaxTemplate $t) => $respondHarvest($t, $data->ePlayer, $data->e, $data->cTile, $data->tiles, $data->cHistory, $data->hasAvatar, $data->cFood, $data->cPlayerRanking, $data->gain));

new JsonView('doCook', fn($data, AjaxTemplate $t) => $respondFood($t, $data->ePlayer, $data->cGrowing, $data->cFood, $data->cPlayerRanking, $data->cHistory, $data->hasAvatar, 'Action::cooked', FALSE));
new JsonView('doEat', fn($data, AjaxTemplate $t) => $respondFood($t, $data->ePlayer, $data->cGrowing, $data->cFood, $data->cPlayerRanking, $data->cHistory, $data->hasAvatar, 'Action::eaten', TRUE));

new JsonView('doFriendAdd', fn($data, AjaxTemplate $t) => $respondFriends($t, $data->ePlayer, $data->cPlayerFriend, 'Action::friendAdded'));
new JsonView('doMotivation', fn($data, AjaxTemplate $t) => $respondFriends($t, $data->ePlayer, $data->cPlayerFriend, 'Action::motivated'));
new JsonView('doFriendRemove', fn($data, AjaxTemplate $t) => $respondFriends($t, $data->ePlayer, $data->cPlayerFriend, NULL));
?>
