<?php
/**
 * La feuille d'une case, remplie à son ouverture : le temps restant et ce que le joueur peut encore
 * payer sont donc ceux du moment.
 */
new \game\TilePage(function($data) {

		$data->ePlayer = \game\PlayerLib::getOnline();

		if($data->ePlayer->empty()) {
			throw new RedirectAction('/jouer');
		}

	})
	->read('get', function($data) {

		// La récolte et son temps se calculent sur tout le plateau de la case
		$cTile = \game\TileLib::getByBoard($data->ePlayer, $data->e['board']);
		\game\TileLib::fillState($cTile, $data->ePlayer);

		$data->e = $cTile[$data->e['tile']];
		$data->cGrowing = \game\GrowingLib::getAll();

		throw new ViewAction($data);

	}, addValidate: ['acceptPlay']);
?>
