<?php
new AdaptativeView('/ferme/{id}/produits', function($data, StructureTemplate $t) {

	$t->title = s("Produits de {value}", $data->eStructure['name']);
	$t->canonical = \structure\StructureUi::urlSellingProducts($data->eStructure);

	$t->nav = 'selling';
	$t->subNav = 'product';
	$t->subNavTarget = $t->canonical;

	if(
		array_sum($data->products) === 0 and
		$data->search->empty()
	) {

		$t->mainTitle = \main\DesignUi::getPageHeader(s("Produits"));

		$start = '<p>'.s("Avant de commencer à vendre, vous devez enregistrer la gamme de produits que vous souhaitez proposer à vos clients. Pour ajouter un premier produit, sélectionnez son type parmi les propositions !").'</p>';

		echo new \main\DesignUi()->getStart(
			\Asset::icon('wallet'),
			s("Gérez votre gamme de produits sur {siteName}"),
			$start
		);

		echo '<br/>';

		if($data->eStructure->isVerifiedCountry() and $data->eStructure->isVatAccountingConfigured()) {

			echo '<h3>'.s("Ajouter un premier produit").'</h3>';

			echo new \product\ProductUi()->create(new \product\Product([
				'structure' => $data->eStructure,
				'profile' => NULL,
				'cCategory' => $data->cCategory,
				'cUnit' => $data->cUnit,
				'unit' => new \product\Unit(),
				'private' => TRUE,
				'pro' => FALSE,
				'vat' => $data->defaultVat,
				'duplicateOf' => new \product\Product(),
				'variantOf' => new \product\Product(),
				'cProductVariant' => new Collection()
			]))->body;

		} else if($data->eStructure->isVerifiedCountry() === FALSE) {

			echo new \structure\StructureUi()->getLegalForm($data->eStructure, onlyCountry: TRUE);

		} else if($data->eStructure->isVatAccountingConfigured() === FALSE) {

			echo '<h3>'.s("Informations requises par rapport à la TVA pour votre structure").'</h3>';
			echo new \structure\ConfigurationUi()->updateVat($data->eStructure, new Collection());

		}

	} else {
		
		$t->mainTitle = \product\HeaderUi::getProducts($data->eStructure, \structure\Member::PRODUCT);

		echo new \product\ProductUi()->getCategories($data->cCategory, $data->products, $data->search);
		echo \product\ProductUi::getIndexBanners($data->eStructure);

		echo new \product\ProductUi()->getSearch($data->eStructure, $data->search);
		echo new \product\ProductUi()->getList($data->eStructure, $data->cProduct, $data->products, $data->cCategory, $data->search, page: $data->page);

	}


});
?>
