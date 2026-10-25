<?php
namespace product;

class PriceUi {

	public function __construct() {

		\Asset::css('product', 'priceInitial.css');
		\Asset::js('product', 'priceInitial.js');

	}

	public function getDiscountAction(string|int $identifier, bool $hasDiscountPrice, ?string $onHide = null, array $attributes = [], bool $fromSteps = FALSE): string {

		return \util\FormUi::getFieldAction(
			$this->getDiscountLink($identifier, $hasDiscountPrice, $onHide, $attributes, $fromSteps)
		);

	}

	/**
	 * `$fromSteps` dit que la baisse affichée vient d'un palier de remise sur volume et non d'une remise
	 * saisie : le bouton la nomme. Au premier rendu le serveur seul le sait, ensuite ce sont les
	 * deux moteurs javascript qui entretiennent l'étiquette (`PriceInitial.markOrigin()`).
	 */
	public function getDiscountLink(string|int $identifier, bool $hasDiscountPrice, ?string $onHide = null, array $attributes = [], bool $fromSteps = FALSE): string {

		$labelFolded = \Asset::icon('tags').' '.s("Remiser");
		$labelDiscount = \Asset::icon('tags').' '.s("Prix remisé");
		$labelSteps = \Asset::icon('graph-down-arrow').' '.s("Remise sur volume");

		if($hasDiscountPrice) {
			$placeholder = $labelFolded;
			$label = $fromSteps ? $labelSteps : $labelDiscount;
		} else {
			$placeholder = $labelDiscount;
			$label = $labelFolded;
		}

		$attributes += [
			'class' => 'btn btn-xs btn-outline-primary',
			'onclick' => 'PriceInitial.switch(this'.($onHide ? ', '.$onHide : '').');',
			'data-visible' => $hasDiscountPrice ? '1' : '0',
			'data-price-discount-reference' => $identifier,
			'data-price-discount' => 'link',
			'data-placeholder' => $placeholder,
			'data-label-folded' => $labelFolded,
			'data-label-discount' => $labelDiscount,
			'data-label-steps' => $labelSteps,
		];

		return '<a '.attrs($attributes).'>'.$label.'</a>';

	}

	public function getDiscountTrashAddon(string|int $identifier, ?string $onHide = ''): string {

		$attributes = [
			'onclick' => 'PriceInitial.hide(\''.$identifier.'\', PriceInitial.getContainer(this, \''.$identifier.'\')); '.$onHide,
			'data-price-discount-reference' => $identifier,
			'data-price-discount' => 'trash',
			'title' => s("Supprimer la remise"),
		];

		return '<a '.attrs($attributes).'>'.\Asset::icon('trash').'</a>';

	}

	public function priceWithoutDiscount(string|float $price, string $unit = '', bool $isSmall = TRUE): string {

		$displayedPrice = is_float($price) ? \util\TextUi::money($price) : $price;

		$h = '<div>';
			$h .= '<span class="util-strikethrough '.($isSmall ? 'font-sm' : '').'">';
				$h .= $displayedPrice.$unit;
			$h .= '</span>';
		$h .= '</div>';

		return $h;
	}
}
?>
