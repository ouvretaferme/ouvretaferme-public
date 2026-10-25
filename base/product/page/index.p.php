<?php
new Page(function($data) {

		\structure\StructureLib::load($data, parameterName: 'id');

	})
	->get('/ferme/{id}/produits', function($data) {

		$data->eStructure->validate('canSelling');

		\structure\MemberLib::setView('viewSellingProducts', $data->eStructure, \structure\Member::PRODUCT);

		if(get_exists('column')) {
			\structure\MemberLib::setView('viewSellingProductsColumn', $data->eStructure, GET('column', [\structure\Member::TURNOVER, \structure\Member::ACCOUNT], \structure\Member::LENGTH));
		}

		$data->cCategory = \product\CategoryLib::getByStructure($data->eStructure, index: 'id');

		if(get_exists('category')) {

			$data->eCategory = \product\Product::GET('category', 'category', new \product\Category());

			if($data->eCategory->notEmpty()) {
				$data->cCategory->validateOffset($data->eCategory);
			}

			\structure\MemberLib::setViewCached('viewSellingCategoryCurrent', $data->eStructure, $data->eCategory);

		} else {

			$categoryViewed = \structure\MemberLib::getViewCached($data->eStructure, 'viewSellingCategoryCurrent');

			$data->eCategory = ($categoryViewed !== NULL and $data->cCategory->offsetExists($categoryViewed)) ?
				$data->cCategory[$categoryViewed] :
				new \product\Category();

		}

		$data->search = new Search([
			'category' => $data->eCategory,
			'profile' => GET('profile'),
			'distribution' => GET('distribution'),
			'name' => GET('name'),
			'reference' => GET('reference'),
			'plant' => GET('plant')
		], GET('sort', default: 'name'));

		if($data->eStructure->hasAccounting()) {

			\structure\StructureLib::connectDatabase($data->eStructure);

			if(get_exists('privateAccountNumber')) {

				if(GET('privateAccountNumber') === '0') {

					$data->search->set('noAccount', TRUE);

				} else {

					$data->search->set('privateAccountNumber', GET('privateAccountNumber'));

				}
			}
		}

		$data->products = \product\ProductLib::countByStructure($data->eStructure, $data->search);

		$data->page = GET('page', 'int');

		\product\ProductLib::loadIndex($data->eStructure);

		$data->cProduct = \product\ProductLib::getByStructure($data->eStructure, $data->eCategory, selectSales: TRUE, search: $data->search, page: $data->page);

		$variantIds = get_exists('variants') ? array_filter(array_map('intval', explode(',', GET('variants')))) : [];

		$accountNumbersByProfile = \product\ProductLib::getAccountNumbersByProfile($data->eStructure);

		$cProductFamily = new Collection();

		foreach($data->cProduct as $eProduct) {

			$eProduct['cProductVariant'] = new Collection();
			$eProduct['profileAccountNumber'] = $accountNumbersByProfile[$eProduct['profile']];

			if(
				$eProduct['variants'] > 0 and
				in_array($eProduct['id'], $variantIds)
			) {
				$cProductFamily[$eProduct['id']] = $eProduct;
			}

		}

		if($cProductFamily->notEmpty()) {

			foreach(\product\ProductLib::getVariantsByPrincipal($data->eStructure, $cProductFamily, selectSales: TRUE) as $eProductVariant) {
				$cProductFamily[$eProductVariant['variantOf']['id']]['cProductVariant']->append($eProductVariant);
			}

		}

		if($data->cProduct->empty()) {

			$data->cUnit = \product\UnitLib::getByStructure($data->eStructure);
			$data->defaultVat = \product\ProductLib::getDefaultVat($data->eStructure);

		}

		throw new ViewAction($data);

	});
?>
