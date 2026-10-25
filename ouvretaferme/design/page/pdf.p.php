<?php
new Page(function($data) {

	\user\ConnectionLib::checkLogged();

})
	->get('index', function($data) {

		$data->documents = \design\PdfUi::getDocuments();

		throw new ViewAction($data);

	})
	// Le rendu d'une brique, servi par le gabarit des PDF et affiché en iframe par la
	// page : ce qu'on voit est produit par la même feuille de style que les documents,
	// et non par une imitation qui divergerait au premier changement de charte.
	->get('sample', function($data) {

		$data->case = GET('case', ['brand', 'structure', 'title', 'figures', 'toc', 'table', 'footer'], fn() => throw new NotExistsAction());

		throw new ViewAction($data);

	});

?>
