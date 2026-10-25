<?php
new JsonView('get', function($data, AjaxTemplate $t) {

	$t->qs('#game-tile-sheet')->outerHtml(new \game\TileUi()->getSheet($data->e, $data->ePlayer, $data->cGrowing));

});
?>
