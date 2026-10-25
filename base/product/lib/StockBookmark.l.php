<?php
namespace product;

class StockBookmarkLib extends StockBookmarkCrud {

	public static function deleteByProduct(Product $eProduct): void {

		$eProduct->expects(['structure']);

		StockBookmark::model()
			->whereStructure($eProduct['structure'])
			->whereProduct($eProduct)
			->delete();

	}

}
?>
