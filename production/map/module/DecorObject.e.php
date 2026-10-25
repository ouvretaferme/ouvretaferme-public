<?php
namespace map;

class DecorObject extends DecorObjectElement {

	// Le kit qui pose un élément importé, un par rangée, comme `batiment` pose un bâtiment (`Decor::properties()`)
	const array KITS = [
		DecorObject::WATER => 'objet-eau',
		DecorObject::TREE => 'objet-nature',
		DecorObject::OTHER => 'objet-materiel'
	];

	public static function getSelection(): array {

		return parent::getSelection() + [
			'structure' => \structure\StructureElement::getSelection(),
		];

	}

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canRead();

	}

	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canManage();

	}

	/**
	 * Un élément n'a pas de composition à retoucher : il se supprime, et un autre s'importe.
	 */
	public function acceptUpdate(): bool {
		return FALSE;
	}

}
?>
