<?php
namespace util;

/**
 * Le texte tapé gardé après un choix (`$d->autocompleteKeepQuery`) : l'attribut que lit `AutocompleteField.apply()`,
 * posé sur un champ à plusieurs choix seulement. Le geste lui-même se joue dans `FormAutocompleteSelectTest`.
 */
class FormAutocompleteKeepQueryTest extends \Test {

	public function testAttribute() {

		$this->assertTrue(str_contains($this->getField(TRUE, TRUE), ' data-autocomplete-keep-query="1"'), 'le champ à plusieurs choix ne garde pas le texte tapé');

	}

	public function testDefault() {

		$this->assertFalse(str_contains($this->getField(TRUE, NULL), 'data-autocomplete-keep-query'), 'sans l\'option, le texte tapé est gardé');
		$this->assertFalse(str_contains($this->getField(FALSE, NULL), 'data-autocomplete-keep-query'), 'un choix unique garde le texte tapé');

	}

	public function testSingleRefused() {

		try {
			$this->getField(FALSE, TRUE);
			$this->assertTrue(FALSE, 'un choix unique accepte l\'option');
		} catch(\Exception) {
			$this->assertTrue(TRUE);
		}

	}

	private function getField(bool $multiple, ?bool $keepQuery): string {

		return new FormUi()->dynamicField(new \plant\Plant(), 'name', function(\PropertyDescriber $d) use ($multiple, $keepQuery) {
			$d->field = 'autocomplete';
			$d->autocompleteUrl = '/plant/plant:query';
			$d->autocompleteBody = ['structure' => 7];
			$d->autocompleteResults = fn($e) => ['value' => 1, 'itemText' => 'Carotte', 'itemHtml' => 'Carotte'];
			$d->autocompleteKeepQuery = $keepQuery;
			$d->multiple = $multiple;
			$d->attributes = ['id' => 'plant-name'];
		});

	}

}
?>
