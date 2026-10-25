<?php
namespace note;

/**
 * Un objet auquel une note est rattachée (`targetType` est `<package>-<quoi>`). La ligne n'a de sens que
 * par sa note, dont elle prend les droits.
 */
class Target extends TargetElement {

	/**
	 * La valeur qu'un rattachement poste depuis l'omnibox et que le filtre porte dans l'adresse.
	 */
	public function getValue(): string {

		$this->expects(['targetType', 'targetId']);
		return $this['targetType'].':'.$this['targetId'];

	}

	public function canRead(): bool {

		$this->expects(['note']);
		return $this['note']->canWrite();

	}

}
?>
