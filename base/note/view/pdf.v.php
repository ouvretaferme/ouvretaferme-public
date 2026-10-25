<?php
new HtmlView('getNote', function($data, PdfTemplate $t) {

	$t->title = $data->e['title'];

	echo new \note\PdfUi()->getNote($data->eStructure, $data->e);

});

new HtmlView('getNotebook', function($data, PdfTemplate $t) {

	$t->title = s("Carnet {value}", $data->e['name']);

	echo new \note\PdfUi()->getNotebook($data->eStructure, $data->e, $data->cNote);

});
?>
