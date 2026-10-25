<?php
namespace util;

/**
 * La liste d'une autocomplétion à la largeur de son groupe (`$d->autocompleteWidth`) : l'attribut que lit
 * `AutocompleteField` pour mesurer l'input-group plutôt que le champ.
 */
class FormAutocompleteWidthTest extends \Test {

	public function testAttribute() {

		$this->assertTrue(str_contains($this->getField('group'), ' data-autocomplete-width="group"'), 'la liste ne prend pas la largeur du groupe');

	}

	public function testDefault() {

		$this->assertFalse(str_contains($this->getField(NULL), 'data-autocomplete-width'), 'sans l\'option, la liste garde la largeur du champ');

	}

	public function testUnknownRefused() {

		try {
			$this->getField('groupe');
			$this->assertTrue(FALSE, 'une largeur inconnue est acceptée');
		} catch(\Exception $e) {
			$this->assertEquals('autocompleteWidth expects group', $e->getMessage(), 'la largeur inconnue est refusée pour une autre raison');
		}

	}

	private function getField(?string $width): string {

		return new FormUi()->dynamicField(new \plant\Plant(), 'name', function(\PropertyDescriber $d) use ($width) {
			$d->field = 'autocomplete';
			$d->autocompleteUrl = '/plant/plant:query';
			$d->autocompleteBody = ['structure' => 7];
			$d->autocompleteResults = fn($e) => ['value' => 1, 'itemText' => 'Carotte', 'itemHtml' => 'Carotte'];
			$d->autocompleteWidth = $width;
			$d->attributes = ['id' => 'plant-name'];
		});

	}

}
?>
