<?php
namespace product;

class StockUi {

	use \Notifiable;

	public function __construct() {

		\Asset::css('product', 'stock.css');

	}

	public static function link(Product $eProduct, string $class = '', bool $withExpired = FALSE): string {

		$h = '<a href="/product/stock:update?id='.$eProduct['id'].'" class="'.$class.'" title="'.\util\DateUi::smart($eProduct['stockUpdatedAt']).'">';
			if($withExpired) {
				$h .= \product\StockUi::getExpired($eProduct);
			}
			$h .= self::value($eProduct);
		$h .= '</a>';

		return $h;
	}

	public static function value(Product $eProduct, int $precision = 2): string {
		return ($eProduct['stock'] !== NULL) ? round($eProduct['stock'], $precision) : s("Illimité");
	}

	public function getNotes(\structure\Structure $eStructure): string {

		$eConfiguration = $eStructure->conf(Configuration::class);

		if($eConfiguration['stockNotes'] === NULL) {
			return '';
		}

		$h = '<div class="stock-description '.($eConfiguration['stockNotes'] === '' ? 'stock-description-empty' : '').' util-block">';

			$h .= '<span class="stock-description-icon">'.\Asset::icon('chat-right-text').'</span>';
			$h .= '<div>';
				$h .= '<div class="util-title">';
					if($eConfiguration['stockNotes'] !== '') {
						$h .= '<h4>'.\util\DateUi::smart($eConfiguration['stockNotesUpdatedAt']).'</h4>';
						$h .= '<a href="/product/stock:updateNote?id='.$eConfiguration['id'].'" class="btn btn-secondary">'.s("Modifier").'</a>';
					} else {
						$h .= '<div class="color-muted">'.s("Pas de notes de stock en ce moment").'</div>';
						$h .= '<a href="/product/stock:updateNote?id='.$eConfiguration['id'].'" class="btn btn-secondary">'.s("Ajouter").'</a>';
					}
				$h .= '</div>';

				if($eConfiguration['stockNotes'] !== '') {
					$h .= \util\TextUi::multiline($eConfiguration['stockNotes']);
				}

			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	public function getHistory(Product $eProduct, \Collection $cStock): string {

		if($cStock->empty()) {
			return '';
		}

		$h = '<h3 class="mt-2">'.s("Évolution du stock").'</h3>';

		$h .= '<div class="stick-xs">';

		$h .= '<table class="stock-item-table tr-even">';

			$h .= '<thead>';

				$h .= '<tr>';

					$h .= '<th>'.s("Date").'</th>';
					$h .= '<th class="text-center" colspan="2">'.s("Stock").'</th>';
					$h .= '<th class="text-end td-min-content">'.s("Variation").'</th>';
					$h .= '<th>'.s("Commentaire").'</th>';
					$h .= '<th class="hide-xs-down">'.s("Par").'</th>';
				$h .= '</tr>';

			$h .= '</thead>';

			$h .= '<tbody>';

			foreach($cStock as $eStock) {

				$h .= '<tr>';

					$h .= '<td class="stock-item-name">';
						$h .= \util\DateUi::smart($eStock['createdAt']);
					$h .= '</td>';

					$h .= '<td class="td-min-content font-lg text-end">';
						$h .= '<b>'.($eStock['balance'] ?? s("Illimité")).'</b>';
					$h .= '</td>';

					$h .= '<td class="td-min-content stock-unit">';
						if($eStock['balance'] !== NULL) {
							$h .= UnitUi::getSingular($eProduct['unit'], short: TRUE);
						}
					$h .= '</td>';

					$h .= '<td class="stock-item-stock-updated color-muted text-end">';
						if($eStock['quantity'] !== 0.0) {
							$h .= ($eStock['quantity'] > 0 ? '+' : '').$eStock['quantity'];
						}
					$h .= '</td>';

					$h .= '<td class="stock-item-comment">';

						if($eStock['comment'] !== NULL) {
							$h .= encode($eStock['comment']);
						} else if(\structure\StructureSetting::hasCommercialisationApp()) {
							$h .= implode('', self::notify('getStockHistorySale', $eStock));
						}

					$h .= '</td>';

					$h .= '<td class="stock-item-name hide-xs-down">';
						if($eStock['createdBy']->notEmpty()) {
							$h .= \user\UserUi::getVignette($eStock['createdBy'], '2rem').' ';
							$h .= $eStock['createdBy']->getName();
						}
					$h .= '</td>';

				$h .= '</tr>';

			}

			$h .= '</tbody>';

		$h .= '</table>';

		$h .= '</div>';

		return $h;

	}

	public function getBookmarks(Product $eProduct, \Collection $cBookmark): \Panel {

		if($cBookmark->empty()) {
			$h = '<div class="util-empty">'.s("Il n'y a aucune récolte en mémoire pour le stock de ce produit.").'</div>';
		} else {

			$h = '<p class="util-info">'.s("Les récoltes réalisés sur les espèces suivantes augmentent automatiquement le stock du produit {value}.", '<u>'.encode($eProduct['name']).'</u>').'</p>';

			$h .= '<div class="util-overflow-xs stick-xs">';

			$h .= '<table class="stock-item-table tr-even">';

				$h .= '<thead>';

					$h .= '<tr>';
						$h .= '<th>'.s("Espèce").'</th>';
						$h .= '<th>'.s("Variété").'</th>';
						$h .= '<th>'.s("Unité").'</th>';
						$h .= '<th>'.s("Par").'</th>';
						$h .= '<th></th>';
					$h .= '</tr>';

				$h .= '</thead>';

				$h .= '<tbody>';

				foreach($cBookmark as $eBookmark) {

					$h .= '<tr>';

						$h .= '<td>';
							$h .= \plant\PlantUi::getVignette($eBookmark['plant'], '2rem').'  ';
							$h .= encode($eBookmark['plant']['name']);
						$h .= '</td>';

						$h .= '<td>';
							if($eBookmark['variety']->notEmpty()) {
								$h .= encode($eBookmark['variety']['name']);
							} else {
								$h .= '<span class="color-muted">/</span>';
							}
						$h .= '</td>';

						$h .= '<td class="product-item-unit">';
							$h .= UnitUi::getSingular($eBookmark['unit'], short: TRUE);
						$h .= '</td>';

						$h .= '<td class="bookmark-item-name">';
							$h .= \user\UserUi::getVignette($eBookmark['createdBy'], '2rem').' ';
							$h .= $eBookmark['createdBy']->getName();
						$h .= '</td>';

						$h .= '<td class="text-end">';
							$h .= '<a data-ajax="/product/stock:doDeleteBookmark" post-id="'.$eBookmark['id'].'" data-confirm="'.s("Ne plus garder en mémoire ?").'" class="btn btn-only-icon btn-danger">'.\Asset::icon('trash').'</a>';
						$h .= '</td>';

					$h .= '</tr>';

				}

				$h .= '</tbody>';

			$h .= '</table>';

			$h .= '</div>';

		}

		return new \Panel(
			id: 'panel-stock-bookmark',
			title: s("Récoltes en mémoire"),
			body: $h,
			subTitle: ProductUi::getPanelHeader($eProduct),
			footer: '<div class="text-end"><a data-ajax="/product/stock:doDeleteBookmarks" post-id="'.$eProduct['id'].'" class="btn btn-danger" data-confirm="'.s("Vous préférence à la récolte pour ce stock seront effacées. Voulez-vous continuer ?").'">'.s("Tout supprimer").'</a></div>'
		);
	}

	public function add(\structure\Structure $eStructure): \Panel {

		$eProduct = new Product([
			'structure' => $eStructure
		]);

		$form = new \util\FormUi();

		$h = '';

		$h .= $form->openAjax('/product/stock:doEnableStock', ['autocomplete' => 'off']);

			$h .= $form->group(
				s("Produit"),
				$form->dynamicField($eProduct, 'id', function($d) use($eStructure) {
					$d->autocompleteBody = function(\util\FormUi $form, Product $e) {

						$e->expects(['structure']);

						return [
							'structure' => $e['structure']['id'],
							'stock' => 'enable'
						];

					};
				})
			);

			$h .= $form->group(
				content: $form->submit(s("Activer le suivi du stock"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-stock-add',
			title: s("Activer le suivi du stock pour un produit"),
			body: $h
		);

	}

	public static function getExpired(Product $eProduct): string {

		\Asset::css('product', 'product.css');

		if($eProduct['stockExpired']) {
			return '<span class="product-item-stock-expired" title="'.s("Mis à jour il y a plus d'une semaine").'">'.\Asset::icon('alarm').'</span>';
		} else {
			return '';
		}

	}

	public function update(Product $eProduct, \Collection $cStock): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/product/stock:doUpdate', ['class' => 'util-block']);

			$h .= $form->hidden('id', $eProduct['id']);

			$current = StockUi::value($eProduct);

			if($eProduct['stock'] !== NULL) {
				$current .= ' '.UnitUi::getSingular($eProduct['unit'], short: TRUE);
			}

			$h .= $form->group(
				s("Stock actuel"),
				$current
			);

			$h .= $form->group(
				s("Nouveau stock").\util\FormUi::info(s("Laissez vide pour définir un stock illimité.")),
				$form->inputGroup(
					$form->dynamicField(new Stock(), 'quantity', function(\PropertyDescriber $d) use($eProduct) {
						$d->placeholder = s("Illimité");
						$d->attributes['onfocus'] = 'this.select();';
						$d->attributes['onrender'] = 'this.focus();';
					}).
					'<div class="input-group-addon">'.UnitUi::getSingular($eProduct['unit']).'</div>'
				)
			);

			$h .= $form->dynamicGroup(new Stock(), 'comment');

			$h .= $form->group(
				content: $form->submit(s("Enregistrer le stock"))
			);

		$h .= $form->close();

		$h .= $this->getHistory($eProduct, $cStock);

		if($eProduct->acceptDisableStock()) {
			$h .= '<h3 class="mt-2">'.s("Vous n'avez plus besoin de suivre le stock de ce produit ?").'</h3>';
			$h .= '<a data-ajax="/product/stock:doDisableStock" post-id="'.$eProduct['id'].'" class="btn btn-outline-primary" data-confirm="'.encode(s("Confirmer la désactivation du suivi de stock pour ce produit ?")).'">'.s("Désactiver le suivi du stock").'</a>';
		}

		return new \Panel(
			id: 'panel-stock-update',
			title: s("Stock"),
			body: $h,
			subTitle: ProductUi::getPanelHeader($eProduct)
		);

	}

	public function increment(Product $eProduct): \Panel {

		return self::crement(
			$eProduct,
			'+',
			s("Ajouter au stock"),
			s("Augmenter le stock")
		);

	}

	public function decrement(Product $eProduct): \Panel {

		return self::crement(
			$eProduct,
			'-',
			s("Retirer du stock"),
			s("Diminuer le stock")
		);

	}

	public function crement(Product $eProduct, string $sign, string $label, string $header): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/product/stock:doUpdate');

			$h .= $form->hidden('id', $eProduct['id']);
			$h .= $form->hidden('sign', $sign);

			$h .= $form->group(
				$label,
				$form->inputGroup(
					'<div class="input-group-addon">'.$sign.'</div>'.
					$form->dynamicField(new Stock(), 'quantity', function(\PropertyDescriber $d) use($eProduct, $sign) {
						$d->attributes['onrender'] = 'this.focus();';
					}).
					'<div class="input-group-addon">'.UnitUi::getSingular($eProduct['unit']).'</div>'
				)
			);

			$h .= $form->dynamicGroup(new Stock(), 'comment');

			$h .= $form->group(
				content: $form->submit(s("Valider"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-stock-crement',
			title: $header,
			body: $h,
			subTitle: ProductUi::getPanelHeader($eProduct)
		);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Stock::model()->describer($property, [
			'comment' => s("Commentaire")
		]);

		switch($property) {

			case 'comment' :
				$d->placeholder = s("Tapez ici un commentaire facultatif sur l'évolution du stock");
				break;

		}

		return $d;

	}

}
?>
