<?php
namespace aid;

class AidLinkUi {

	public static function p(string $property): \PropertyDescriber {

		$d = AidLink::model()->describer($property, [
			'target' => s("Aide"),
			'kind' => s("Lien"),
		]);

		switch($property) {

			// Une aide ne se lie pas à elle-même (AidLink::target.self) : elle ne se propose pas.
			case 'target' :
				$d->autocompleteBody = fn(\util\FormUi $form, AidLink $e) => ['aid' => $e['aid']['id']];
				AidUi::query($d);
				break;

			case 'kind' :
				$d->field = 'select';
				$d->values = [
					AidLink::REQUIRES => s("Demande d'avoir"),
					AidLink::EXCLUDES => s("Ne se cumule pas avec"),
					AidLink::COMPLEMENTS => s("Se complète par"),
				];
				$d->placeholder = s("Lien");
				break;

		}

		return $d;

	}

}
?>
