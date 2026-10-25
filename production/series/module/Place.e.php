<?php
namespace series;

class Place extends PlaceElement {

	public function canRead(): bool {
		return TRUE;
	}

	/**
	 * Le nom sous lequel l'assolement écrit le groupe de l'emplacement : la zone quand sa parcelle la remplit,
	 * puisqu'une telle parcelle n'a pas de nom propre, la parcelle sinon (`CultivationUi::displayPlaces()`).
	 */
	public function getGroupName(): string {

		$this->expects(['zone' => ['name'], 'plot' => ['name', 'zoneFill']]);

		return ($this['plot']['zoneFill'] === FALSE) ? $this['plot']['name'] : $this['zone']['name'];

	}

	/**
	 * Une planche qui remplit sa parcelle n'a pas de nom propre : l'assolement n'écrit que la parcelle.
	 */
	public function hasBedName(): bool {

		$this->expects(['bed' => ['plotFill']]);

		return ($this['bed']['plotFill'] === FALSE);

	}

}
?>