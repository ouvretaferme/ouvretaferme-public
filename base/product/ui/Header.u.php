<?php
namespace product;

class HeaderUi {

	public static function getProducts(\structure\Structure $eStructure, string $selectedView): string {

		$categories = self::getProductsCategories($eStructure);

		$eProduct = new \product\Product(['structure' => $eStructure]);
		$canCreate = $eProduct->canCreate();

		$tools = '';
		$create = '';

		switch($selectedView) {

			case \structure\Member::PRODUCT :
				$tools = '<a '.attr('onclick', 'Lime.Search.toggle("#product-search")').' class="btn btn-only-icon btn-outline-secondary">'.\Asset::icon('search').'</a>';
				if($canCreate) {
					$create = '<a class="btn btn-secondary dropdown-toggle" data-dropdown="bottom-end" data-dropdown-hover="true">'.\main\DesignUi::getGestureLabel('plus-circle', s("Nouveau produit")).'</a>';
					$create .= \product\ProductUi::getProfileDropdown($eProduct);
				}
				break;

			case \structure\Member::CATEGORY :
				if($canCreate) {
					$create = '<a href="/product/category:create?structure='.$eStructure['id'].'" class="btn btn-secondary">'.\Asset::icon('plus-circle').'<span class="hide-xs-down"> '.s("Nouvelle catégorie").'</span></a>';
				}
				break;

		}

		return \main\DesignUi::getPageHeader(
			$categories[$selectedView],
			tools: $tools,
			create: $create,
			pages: new \main\DesignUi()->getPagesFromCategories($categories, fn(string $view) => \structure\StructureUi::urlSellingProducts($eStructure, $view), $selectedView)
		);

	}

	protected static function getProductsCategories(\structure\Structure $eStructure): array {
		return [
			\structure\Member::PRODUCT => s("Produits"),
			\structure\Member::CATEGORY => s("Catégories de produits"),
		];
	}
	
}
?>
