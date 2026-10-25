<?php
namespace api;

class ClientUi {

	public static function p(string $property): \PropertyDescriber {

		$d = Client::model()->describer($property, [
			'name' => s("Nom de l'application"),
			'redirectUris' => s("Adresses de retour"),
			'cScope' => s("Accès"),
		]);

		switch($property) {

			case 'name' :
				$d->labelAfter = \util\FormUi::info(s("Les structures le lisent sur l'écran où elles autorisent l'application"));
				break;

			case 'redirectUris' :
				$d->field = fn(\util\FormUi $form, Client $e) => $form->textarea('redirectUris', implode("\n", $e['redirectUris']), ['placeholder' => "https://\nhttp://localhost:3000/"]);
				$d->labelAfter = \util\FormUi::info(s("Une adresse par ligne"));
				break;

			case 'cScope' :
				$d->field = function(\util\FormUi $form, Client $e) {
					$names = $e['cScopeAvailable']->getColumn('name');
					return $form->checkboxes('cScope[]', array_combine($names, $names), $e['cScope']->getColumn('name'), ['all' => FALSE]);
				};
				break;

		}

		return $d;

	}

}
?>
