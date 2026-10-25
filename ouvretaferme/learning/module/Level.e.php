<?php
namespace learning;

class Level extends LevelElement {

	public function canRead(): bool {
		return TRUE;
	}

	public function canWrite(): bool {
		return \user\ConnectionLib::getOnline()->isAdmin();
	}

	/**
	 * Un niveau qui porte des formations ne se supprime pas : elles en changent d'abord.
	 * Le parent garde la règle d'écriture (`acceptWrite()`), qu'une surcharge qui ne le
	 * rappellerait pas perdrait.
	 */
	public function acceptDelete(): bool {

		$this->expects(['courses']);

		return (
			parent::acceptDelete() and
			$this['courses'] === 0
		);

	}

}
?>
