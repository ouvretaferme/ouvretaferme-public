<?php
namespace planning;

/**
 * Une chose due hors cultures pour une structure, posée par la source qui la calcule
 * (`DueLib::refresh()`) : son type (`<package>-<quoi>`, comme les notifications), l'élément qu'elle
 * désigne dans sa source, et le jour où elle commence, NULL quand elle est due dès maintenant.
 *
 * Elle se lit par tout membre, et ne s'écrit par aucun geste : seul le recalcul de sa source la pose
 * ou la retire, à la suite d'un geste que la source a déjà gardé de son propre droit.
 */
class Due extends DueElement {

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canRead();

	}

	public function canWrite(): bool {
		return FALSE;
	}

}
?>
