<?php

new AdaptativeView('index', function($data, BaseTemplate $t) {

	$t->title = s("Documents PDF");

	$t->header = \design\NavigationUi::getHeader($t->title, '/pdf');

	echo new \design\PdfUi()->get($data->documents);

});

new HtmlView('sample', function($data, PdfTemplate $t) {

	$t->title = s("Documents PDF");

	echo new \design\PdfUi()->getSample($data->case);

});

?>
