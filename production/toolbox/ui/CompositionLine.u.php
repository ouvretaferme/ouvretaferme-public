<?php
namespace toolbox;

class CompositionLineUi {

	public static function p(string $property): \PropertyDescriber {

		$d = CompositionLine::model()->describer($property, [
			'plant' => s("Espèce"),
			'quantity' => s("Quantité"),
		]);

		switch($property) {

			// L'unité est celle de la règle, que la ligne garde : la quantité se règle dedans, et le
			// champ la dit au lieu de la laisser choisir.
			case 'quantity' :
				$d->append = fn(\util\FormUi $form, CompositionLine $e) => $form->addon(encode(\product\UnitUi::getSingular($e['unit'], short: TRUE)));
				break;

		}

		return $d;

	}

}
?>
