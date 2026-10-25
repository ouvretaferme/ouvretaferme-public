<?php
new AdaptativeView('/produit/{id}', function($data, StructureTemplate $t) {

	$t->title = s("Produit {value}", $data->e['name']);

	$t->nav = 'selling';
	$t->subNav = 'product';

	$t->mainTitle = new \product\ProductUi()->displayTitle($data->e);

	if($data->e['status'] === \product\Product::DELETED) {
		echo '<div class="util-danger mb-1">'.s("Ce produit a été supprimé et n'est plus disponible.").'</div>';
	} else {
		// La fiche d'une variante redirige avant d'arriver ici (product.p.php) : ce qui
		// reste à dire de la variante demandée est le bandeau ci-dessous, sur la famille.
		if($data->eProductVariantRequested->notEmpty()) {
			echo new \product\VariantAiUi()->getRedirectedBanner($data->e, $data->eProductVariantRequested);
		}
		echo new \product\ProductUi()->display($data->e);
		if($data->e['variants'] === 0) {
			echo new \product\ProductUi()->getAnalyze($data->e);
		}
		echo new \product\ProductUi()->getTabs($data->e);
	}

});

new AdaptativeView('create', function($data, PanelTemplate $t) {
	return new \product\ProductUi()->create($data->e, profilesExcluded: $data->profilesExcluded, autocomplete: $data->autocomplete);
});

new JsonView('doCreate', function($data, AjaxTemplate $t) {

	$t->js()->success('product', 'Product::created');
	$t->js()->closePanel('#panel-product-create');
	// Le champ a disparu si la page a été rechargée pendant la création : le produit est créé et le
	// panneau refermé, il n'y a plus de champ où le poser.
	$t->js()->eval('{ const input = document.getElementById('.json_encode($data->autocomplete).'); if(input !== null) { AutocompleteField.apply(input, '.json_encode(\product\ProductUi::getAutocomplete($data->e)).'); } }');

});

new AdaptativeView('update', function($data, PanelTemplate $t) {
	return new \product\ProductUi()->update($data->e);
});

new AdaptativeView('attachVariant', function($data, PanelTemplate $t) {
	return new \product\VariantAiUi()->attach($data->e);
});

new AdaptativeView('unifyPrices', function($data, PanelTemplate $t) {
	return new \product\VariantAiUi()->unifyPrices($data->e, $data->unify);
});


new JsonView('doUpdateStatus', function($data, AjaxTemplate $t) {

	$t->qs('#product-switch-'.$data->e['id'])->toggleSwitch('post-status', [\product\Product::ACTIVE, \product\Product::INACTIVE]);
	$t->qs('#product-switch-grid-'.$data->e['id'])->toggleSwitch('post-status', [\product\Product::ACTIVE, \product\Product::INACTIVE]);
	$t->qs('#product-switch-card-'.$data->e['id'])->toggleSwitch('post-status', [\product\Product::ACTIVE, \product\Product::INACTIVE]);

	$isActive = ($data->e['status'] === \product\Product::ACTIVE);
	$postStatus = $isActive ? \product\Product::INACTIVE : \product\Product::ACTIVE;

	$setDisabled = function(string $selector, string $class) use ($t, $isActive) {

		if($isActive) {
			$t->qsa($selector)->removeClass($class);
		} else {
			$t->qsa($selector)->addClass($class);
		}

	};

	$setDisabled('#product-family-'.$data->e['id'].' tr, tr[data-variant-id="'.$data->e['id'].'"], [data-variant-id="'.$data->e['id'].'"] tr', 'tr-disabled');
	$setDisabled('.util-deck[data-variant-id="'.$data->e['id'].'"]', 'product-variant-card-disabled');

	foreach($data->cProductVariant as $eProductVariant) {

		foreach(['#product-switch-', '#product-switch-card-'] as $prefix) {

			$instruction = $t->qs($prefix.$eProductVariant['id'])->setAttribute('post-status', $postStatus);

			if($isActive) {
				$instruction->switchOn();
			} else {
				$instruction->switchOff();
			}

		}

		$setDisabled('tr[data-variant-id="'.$eProductVariant['id'].'"], [data-variant-id="'.$eProductVariant['id'].'"] tr', 'tr-disabled');
		$setDisabled('.util-deck[data-variant-id="'.$eProductVariant['id'].'"]', 'product-variant-card-disabled');

	}

});

new JsonView('query', function($data, AjaxTemplate $t) {

	$results = \product\ProductUi::getAutocompleteResults($data->cProduct, $data->withPlantTitle, withPrincipal: TRUE);

	if($data->hasNew) {
		$results[] = \product\ProductUi::getAutocompleteCreate($data->eStructure, $data->query, $data->autocomplete, $data->withComposition);
	}

	$t->push('results', $results);

});

new AdaptativeView('updateAccountNumber', function($data, PanelTemplate $t) {
	return new \product\ProductUi()->updateAccount($data->eStructure, $data->cProduct);
});
?>
