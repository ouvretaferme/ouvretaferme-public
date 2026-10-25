<?php
namespace media;
/**
 * Manages item vignettes images
 *
 */
class ProductVignetteLib extends MediaLib {

	public function buildElement(): \product\Product {

		$eProduct = POST('id', 'product\Product');

		if(
			$eProduct->empty() or
			\product\Product::model()
				->select(\product\Product::getSelection())
				->get($eProduct) === FALSE
		) {
			throw new \NotExistsAction('Product');
		}

		if(
			\user\ConnectionLib::getOnline()->isAdmin() === FALSE and
			$eProduct->canWrite() === FALSE
		) {
			throw new \NotAllowedAction();
		}

		return $eProduct;

	}

}
?>
