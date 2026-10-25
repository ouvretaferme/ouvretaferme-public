<?php
new \product\ProductPage()
	->read('list', function($data) {

		if($data->e['variants'] === 0) {
			throw new NotExpectedAction('Product #'.$data->e['id'].' has no variant');
		}

		$data->eStructure = \structure\StructureLib::getById($data->e['structure']);

		// Le nombre de colonnes de la table est décidé sur toute la page par
		// ProductUi::getList() : une sous-ligne insérée sans les mêmes colonnes décalerait la
		// table. Les deux valeurs viennent du lien que le serveur a lui-même écrit sur le
		// chevron, jamais d'un état que le client aurait composé.
		$data->displayStock = GET('stock', 'bool');
		$data->displayColumn = GET('column', [\structure\Member::TURNOVER, \structure\Member::ACCOUNT], \structure\Member::TURNOVER);

		$data->cProductVariant = \product\ProductLib::getVariantsByPrincipal($data->eStructure, new Collection([$data->e]), selectSales: TRUE);

		throw new ViewAction($data);

	});

new Page()
	->post('query', function($data) {

		$eProductPrincipal = \product\ProductLib::getById(POST('id', '?int'))
			->validate('canWrite');

		\structure\StructureLib::validateFeature($eProductPrincipal['structure'], \structure\Structure::FEATURE_VARIANTS);

		// L'autocomplétion sert deux écrans : « Rattacher un produit existant » sur une famille, et
		// l'onglet « Produit existant » d'une première variante, où le rattachement fait naître la
		// famille (doCreateFirstVariant). Le filtre de getAttachableFromQuery() ne dépend pas de
		// l'état de variante du principal, seule cette garde le faisait.
		if(
			$eProductPrincipal->acceptAttachVariant() === FALSE and
			$eProductPrincipal->acceptEnableVariants() === FALSE
		) {
			throw new NotExpectedAction('Product #'.$eProductPrincipal['id'].' can neither attach nor enable variants');
		}

		$data->eProductPrincipal = $eProductPrincipal;
		$data->cProduct = \product\VariantLib::getAttachableFromQuery($eProductPrincipal, POST('query'));

		throw new \ViewAction($data);

	});
?>
