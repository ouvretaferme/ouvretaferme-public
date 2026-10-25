<?php
new AdaptativeView('start', function($data, GameTemplate $t) {

	$t->title = s("Sauvez Noël !");

	$panel = '<h1>'.s("Des légumes pour les rennes").'</h1>';
	$panel .= new \game\HelpUi()->getStory();
	$panel .= '<div class="text-center">';
		$panel .= '<a href="'.($data->eUserOnline->empty() ? '/user/log:form?withSignUp=1' : '/game/:new').'" class="btn btn-lg btn-game">'.s("Commencer à jouer").'</a>';
	$panel .= '</div>';

	echo new \game\DeskUi()->getScene($data->ePlayer, $data->scene, $data->ccTile, $data->bake, '', new \game\DeskUi()->getPanel($panel));

});

new AdaptativeView('/jouer', function($data, GameTemplate $t) {

	$t->title = s("Jouer !");

	if(\game\GameSetting::isFinished()) {

		$panel = '<h1>'.s("Des légumes pour les rennes").'</h1>';
		$panel .= new \game\HelpUi()->getEnd($data->points);
		$panel .= new \game\PlayerUi()->getPointsRanking($data->cPlayerRanking, $data->ePlayerOnline);

		// La fin de partie montre la cour sans personne : ni joueur, ni case
		echo new \game\DeskUi()->getScene(new \game\Player(), $data->scene, $data->ccTile, $data->bake, '', new \game\DeskUi()->getPanel($panel));

	} else if($data->ePlayer->isOnline() === FALSE) {

		echo new \game\DeskUi()->getScene($data->ePlayer, $data->scene, $data->ccTile, $data->bake, '', '<div class="game-court-top">'.new \game\DeskUi()->getHudFriend($data->ePlayer).'</div>');

	} else {

		$overlay = '<div class="game-court-top">';

			$overlay .= new \game\DeskUi()->getHud($data->ePlayer, $data->cPlayerFriend->count());

			if($data->adventDay !== NULL) {
				$overlay .= new \game\DeskUi()->getAdvent($data->adventDay);
			}

			$overlay .= new \game\DeskUi()->getBanner($data->lastDay, $data->boardOpening);

		$overlay .= '</div>';

		if($data->sheet === NULL) {
			$overlay .= new \game\DeskUi()->getDoor($data->ePlayer, $data->today, $data->ripe);
		}

		echo new \game\DeskUi()->getScene($data->ePlayer, $data->scene, $data->ccTile, $data->bake, new \game\DeskUi()->getCellar($data->ePlayer, $data->cFood), $overlay);
		echo new \game\DeskUi()->getSheets($data->ePlayer, $data->cGrowing, $data->cFood, $data->cPlayerRanking, $data->cPlayerFriend, $data->cHistory, $data->hasAvatar, $data->sheet, $data->new);

	}

});

new AdaptativeView('new', function($data, PanelTemplate $t) {
	return new \game\PlayerUi()->create($data->e);
});
?>
