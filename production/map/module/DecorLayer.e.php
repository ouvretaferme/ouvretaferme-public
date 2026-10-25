<?php
namespace map;

/**
 * Un calque du décor : les éléments qu'une structure range ensemble pour les montrer ou les masquer
 * d'un geste, et qui apportent leur catalogue (l'irrigation d'abord). Le calque de base « Décor »
 * n'a pas de ligne : un décor sans calque lui appartient.
 */
class DecorLayer extends DecorLayerElement {

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

	public static function properties(\PropertyList $l): void {

		$l->property('type')
			->addAfterCheck('irrigation', function(string $type): bool {

				$this->expects(['structure']);

				return ($type !== DecorLayer::IRRIGATION or MapSetting::isIrrigationEnabled($this['structure']));

			});

	}

}
?>
