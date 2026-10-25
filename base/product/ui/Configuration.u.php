<?php
namespace product;

class ConfigurationUi {

	public function updateStockNotes(Configuration $eConfiguration): \Panel {

		$form = new \util\FormUi();

		$h = '';

		$h .= $form->openAjax('/product/stock:doUpdateNote', ['autocomplete' => 'off']);

			$h .= $form->hidden('id', $eConfiguration);

			$h .= $form->dynamicField($eConfiguration, 'stockNotes', function($d) {
				$d->attributes['style'] = 'height: 20rem';
			});
			$h .= '<br/>';
			$h .= $form->submit(s("Enregistrer"));

		$h .= $form->close();

		return new \Panel(
			id: 'panel-product-update-stock-notes',
			title: s("Modifier les notes de stock"),
			body: $h
		);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Configuration::model()->describer($property);

		switch($property) {

			case 'stockNotes' :
				$d->attributes = [
					'onrender' => 'this.focus();'
				];
				break;

		}

		return $d;

	}

}
?>
