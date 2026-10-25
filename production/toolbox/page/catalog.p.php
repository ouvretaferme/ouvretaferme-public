<?php
new Page(function($data) {

		\structure\StructureLib::load($data, parameterName: 'id');

		$data->eStructure->validate('canWrite');

	})
	->get('/ferme/{id}/outils/produits', function($data) {

		$data->cFavorite = \toolbox\FavoriteLib::getByStructure($data->eStructure);

		$data->start = (
			get_exists('open') === FALSE and
			in_array(\toolbox\Favorite::CATALOG, $data->eStructure->conf(\structure\Feature::class)['hasToolbox']) === FALSE
		);

		if($data->start) {
			throw new ViewAction($data);
		}

		// Ouvrir l'outil l'inscrit parmi ceux de la structure : un observateur consulte la recherche sans rien écrire.
		if($data->eStructure->canPlanning()) {
			\toolbox\CatalogLib::open($data->eStructure);
		}

		$data->filters = \toolbox\CatalogLib::getFilters($data->eStructure, $_GET);

		$data->page = GET('page', 'int');

		$data->products = \toolbox\CatalogLib::getProducts($data->eStructure, $data->filters, $data->page);

		throw new ViewAction($data);

	})
	// Les suggestions de la barre, rappelées à chaque saisie avec les critères déjà posés.
	->post('query', function($data) {

		$data->filters = \toolbox\CatalogLib::getFilters($data->eStructure, $_POST);

		$data->suggestions = \toolbox\CatalogLib::getSuggestions($data->eStructure, $data->filters, POST('query'));

		throw new ViewAction($data);

	})
	->get('product', function($data) {

		$data->ePlant = \toolbox\CatalogLib::getFilters($data->eStructure, $_GET)['plant'];

		$data->ePhytoProduct = \toolbox\CatalogLib::getProduct($data->eStructure, GET('amm'), $data->ePlant)->validate();

		throw new ViewAction($data);

	});
?>
